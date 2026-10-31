<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\MasterData\ChristmasbuyDiscount;
use Hanjiang\MasterData\ChristmasbuyGift;
use Hanjiang\MasterData\ChristmasbuyGoods;
use Hanjiang\MasterData\MountTemp;
use Hanjiang\Model\ActivityClock;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\Equipment;
use Hanjiang\Model\FestReward;
use Hanjiang\Model\FreeLeap;
use Hanjiang\Model\Member;
use Hanjiang\Model\MemberIntegral;
use Hanjiang\Model\MemberJade;
use Hanjiang\Model\MemberStone;
use Hanjiang\Model\OwnGem;
use Hanjiang\Model\OwnMaterials;
use Hanjiang\Model\OwnMount;
use Hanjiang\Model\TraderLog;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 圣诞团购（移植旧版 controllers/christmasbuy.php + models/christmasbuy_model.php，计划任务 cron_acttask.php:get_christmasbuy）
 *
 * 活动时间与版本取 ActivityTime 的 christmasbuy（旧版数据 2016-12-31 已结束）。商品取 MasterData ChristmasbuyGoods：每件商品
 * 每人每天限购 num 次（christmasbuy_log 按当天统计），一次得 gnum 个（锻造材料 14、装备 15、坐骑 16 的 value 为 id）。
 * 商品先加进购物车（Redis car_key_{uid}，到当天结束过期），结算时购物车总价按 ChristmasbuyDiscount 打折（总价达到 price
 * 时折扣为 discount 折，取最后一个达到的），向下取整后扣元宝，记购买日志与消费记录 christmasbuy_info。活动期间累计消费达到
 * ChristmasbuyGift 的 limit 可领返利礼包（christmasbuy_gift_log）；活动结束后 10 小时内计划任务补发没领的返利并发信件
 * （cronReward）。
 * 客户端 christmasbuypop.js 调用 index、add_good（id）、del_car_good（id）、buy_good、rebate_reward_index、rebate_reward（id）、
 * play_introduce。当前时间取 BossClock。
 *
 * 奖励类型（_add_reward）：1 银币 2 黄宝石 3 紫宝石 4 突飞卡 5 原魂 6 玉石 7 宝石碎片 8 声望 9 精炼石 10 五彩石 11 上古血玉
 * 12 玫瑰 13 钻石 14 锻造材料 15 装备 16 坐骑（14 ~ 16 的 num 为 id、value 为数量）。
 * 照旧版：商品列表的 num 为今天还能买的数量（全部商品时最小 0，单个商品时不取 0）；rebate_reward 的操作太快回 -2；返利领取的
 * 奖励列表 info 会沿用前一项的（$arr1 跨循环复用）；没有消费记录时 allgold 为 null（SUM 为 NULL）。
 * 与旧版的差别：四个操作锁改为 Redis SET NX（旧版 get + setex 30 秒）；结算在玩家行锁事务里执行（扣元宝、日志、发奖一起提交）；
 * 返利领取记录加唯一键，写入成功才发奖；计划任务合并同类锻造材料 / 装备 / 坐骑时逐个发放（旧版只发最后一个，返利表里没有这些类型）。
 */
final class ChristmasbuyHandler
{
    public const string ACTIVITY = 'christmasbuy';

    /** 领取成就 key（_rebate_reward_key） */
    private const string REBATE_REWARD_KEY = 'rebate_reward_key';
    /** 购买 key（_buy_good_lock_key） */
    private const string BUY_GOOD_KEY = '_buy_good_lock_key';
    /** 加入购物车 key（_add_good_key） */
    private const string ADD_GOOD_KEY = '_add_good_key';
    /** 移出购物车 key（_del_car_good_key） */
    private const string DEL_CAR_GOOD_KEY = 'del_car_good_key';
    private const int LOCK_TTL = 30;

    /** 购物车（christmasbuy_model->_car_key） */
    private const string CAR_KEY = 'car_key_';

    /** christmasbuy_lang.php + cron_lang.php */
    private const array LANG = [
        'activity_open_error' => '活动未开启。',
        'param_error' => '参数错误。',
        'fast_error' => '操作太快了。',
        'reward_list_error' => '获取商品信息失败！',
        'num_error' => '剩余购买数量不足。',
        'car_nogood' => '购物车中没有商品。',
        'gold_error' => '您的元宝不足了。',
        'rebate_error' => '获取返利信息失败。',
        'noreach_ach' => '未达成此成就。',
        'achievement_get_error' => '已经领取该成就。',
        'insert_error' => '成就领取日志没有插入成功',
        'cron_chrismasbuy_end' => '亲爱的玩家！圣诞团购活动已结束，您未领取奖励系统已自动帮您领取，',
        'message_system_title' => '系统通知',
    ];

    /** 计划任务信件里的资源名（cron_lang.php；cron_soul 后一次定义「将魂」生效） */
    private const array LANG_CRON_ITEM = [
        1 => '银币', 2 => '黄宝石', 3 => '紫宝石', 4 => '突飞卡', 5 => '将魂', 6 => '玉石', 7 => '宝石碎片', 8 => '声望',
        9 => '精炼石', 10 => '五彩石', 11 => '上古血石', 12 => '玫瑰花', 13 => '钻石',
    ];

    /**
     * 圣诞团购入口: c=christmasbuy&m=index
     *
     * 1 成功；-1 活动未开启；-3 获取商品信息失败
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $act = self::init($now);
        if ($act === null || $uid <= 0) {
            return ['status' => -1, 'msg' => self::LANG['activity_open_error']];
        }
        // 获取商品列表信息
        $shopList = self::buyList($uid, $act['version'], $now);
        if ($shopList === []) {
            return ['status' => -3, 'msg' => self::LANG['reward_list_error']];
        }
        // 获取用户购物车的商品
        $carinfo = self::car($uid);
        return [
            'status' => 1,
            'cd' => $act['cd'],
            'list' => $shopList,
            'carinfo' => $carinfo,
            'car_array' => self::carInfo($shopList, $carinfo),
        ];
    }

    /**
     * 加入购物车: c=christmasbuy&m=add_good（id 商品 id）
     *
     * 1 成功；-1 活动未开启；-2 参数错误；-3 获取商品信息失败；-4 剩余购买数量不足；-5 操作太快了
     */
    public static function add_good(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        if ($uid <= 0 || !self::lock(self::ADD_GOOD_KEY . $uid)) {
            return ['status' => -5, 'msg' => self::LANG['fast_error']];
        }
        try {
            $act = self::init($now);
            if ($act === null) {
                return ['status' => -1, 'msg' => self::LANG['activity_open_error']];
            }
            $id = (int)$ctx->input('id', 0);
            if ($id <= 0) {
                return ['status' => -2, 'msg' => self::LANG['param_error']];
            }
            $goods = self::buyItem($uid, $act['version'], $id, $now);
            if ($goods === null) {
                return ['status' => -3, 'msg' => self::LANG['reward_list_error']];
            }
            if ($goods['num'] <= 0) {
                return ['status' => -4, 'msg' => self::LANG['num_error']];
            }
            // 用户购物车的全部商品（以商品 id 为键）
            $goodlist = self::car($uid);
            $goodlist[$id] = ($goodlist[$id] ?? 0) + 1;
            // 加入购物车
            self::saveCar($uid, $goodlist, $now);
            // 刷新商品信息与折扣信息
            $shopList = self::buyList($uid, $act['version'], $now);
            return ['status' => 1, 'list' => $shopList, 'car_info' => $goodlist, 'car_array' => self::carInfo($shopList, $goodlist)];
        } finally {
            Redis::del(self::ADD_GOOD_KEY . $uid);
        }
    }

    /**
     * 删除购物车中的商品: c=christmasbuy&m=del_car_good（id 商品 id，一次减 1 个）
     *
     * 1 成功；-1 活动未开启；-2 参数错误；-3 获取商品信息失败；-4 购物车是空的；-5 操作太快了
     */
    public static function del_car_good(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        if ($uid <= 0 || !self::lock(self::DEL_CAR_GOOD_KEY . $uid)) {
            return ['status' => -5, 'msg' => self::LANG['fast_error']];
        }
        try {
            $act = self::init($now);
            if ($act === null) {
                return ['status' => -1, 'msg' => self::LANG['activity_open_error']];
            }
            $id = (int)$ctx->input('id', 0);
            if ($id <= 0) {
                return ['status' => -2, 'msg' => self::LANG['param_error']];
            }
            if (self::buyItem($uid, $act['version'], $id, $now) === null) {
                return ['status' => -3, 'msg' => self::LANG['reward_list_error']];
            }
            $carinfo = self::car($uid);
            if ($carinfo === []) {
                return ['status' => -4, 'msg' => self::LANG['car_nogood']];
            }
            if (isset($carinfo[$id])) {
                $sy = $carinfo[$id] - 1;
                if ($sy <= 0) {
                    unset($carinfo[$id]);
                } else {
                    $carinfo[$id] = $sy;
                }
            }
            self::saveCar($uid, $carinfo, $now);
            // 重新获取购物车商品
            $shopList = self::buyList($uid, $act['version'], $now);
            return ['status' => 1, 'list' => $shopList, 'car_info' => $carinfo, 'car_array' => self::carInfo($shopList, $carinfo)];
        } finally {
            Redis::del(self::DEL_CAR_GOOD_KEY . $uid);
        }
    }

    /**
     * 购买购物车商品: c=christmasbuy&m=buy_good
     *
     * 1 成功；-1 活动未开启；-2 获取商品信息失败；-3 购物车是空的；-4 元宝不足；-5 操作太快了
     */
    public static function buy_good(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        if ($uid <= 0 || !self::lock(self::BUY_GOOD_KEY . $uid)) {
            return ['status' => -5, 'msg' => self::LANG['fast_error']];
        }
        try {
            $act = self::init($now);
            if ($act === null) {
                return ['status' => -1, 'msg' => self::LANG['activity_open_error']];
            }
            $result = Member::locked($uid, static fn (): array => self::doBuy($uid, $act['version'], $now));
            if ($result['status'] !== 1) {
                return $result;
            }
            // 重新获取购物车商品
            $shopList = self::buyList($uid, $act['version'], $now);
            $member = DB::table('member')->select('gold', 'silver')->where('uid', $uid)->whereNull('deleted_at')->first() ?? [];
            return [
                'status' => 1,
                'list' => $shopList,
                'car_info' => [],
                'car_array' => self::carInfo($shopList, []),
                'silver' => (int)($member['silver'] ?? 0),
                'gold' => (int)($member['gold'] ?? 0),
            ];
        } finally {
            Redis::del(self::BUY_GOOD_KEY . $uid);
        }
    }

    /**
     * 结算（在玩家行锁事务里执行）
     *
     * @return array<string, mixed>
     */
    private static function doBuy(int $uid, int $version, int $now): array
    {
        // 查询购物车的商品
        $carinfo = self::car($uid);
        if ($carinfo === []) {
            return ['status' => -3, 'msg' => self::LANG['car_nogood']];
        }
        $shopList = array_values(array_intersect_key(ChristmasbuyGoods::ROWS, $carinfo));
        if ($shopList === [] || count($shopList) !== count($carinfo)) {
            // 商品列表信息错误
            return ['status' => -2, 'msg' => self::LANG['reward_list_error']];
        }
        // 获取购物车商品信息（所需元宝），扣除元宝
        $money = self::carInfo($shopList, $carinfo);
        if (!Member::deductCurrency($uid, gold: (int)$money['gold'])) {
            return ['status' => -4, 'msg' => self::LANG['gold_error']];
        }
        // 玩家购买商品的日志表
        $data = [];
        foreach ($carinfo as $id => $num) {
            $data[] = ['version' => $version, 'goods_id' => $id, 'goods_num' => $num, 'uid' => $uid, 'dateline' => $now];
        }
        DB::table('christmasbuy_log')->insertBatch($data);
        // 记录玩家元宝消费情况
        DB::table('christmasbuy_info')->insert(['version' => $version, 'gold' => (int)$money['gold'], 'dateline' => $now, 'uid' => $uid]);
        // 发放玩家资源
        foreach ($shopList as $val) {
            $reward = ['type' => $val['type'], 'num' => $val['gnum'] * $carinfo[$val['id']]];
            if (in_array($val['type'], [14, 15, 16], true)) {
                $reward['num'] = $val['value'];
                $reward['value'] = $val['gnum'] * $carinfo[$val['id']];
            }
            self::addReward($uid, $reward, 0, 'buy_good', $now);
        }
        // 清空购物车
        Redis::del(self::CAR_KEY . $uid);
        return ['status' => 1];
    }

    /**
     * 成就（返利）列表: c=christmasbuy&m=rebate_reward_index
     *
     * 1 成功；-1 活动未开启；-2 获取返利信息失败。button_status：0 未达成，1 可领取，2 已领取
     */
    public static function rebate_reward_index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $act = self::init($now);
        if ($act === null || $uid <= 0) {
            return ['status' => -1, 'msg' => self::LANG['activity_open_error']];
        }
        if (ChristmasbuyGift::ROWS === []) {
            return ['status' => -2, 'msg' => self::LANG['rebate_error']];
        }
        // 查询玩家消费情况（没有消费记录时为 null）
        $allgold = self::payLog($uid, $act['version']);
        // 查询玩家成就领取记录
        $achLog = self::achLog($uid, $act['version']);
        $newArray = [];
        foreach (array_values(ChristmasbuyGift::ROWS) as $key => $val) {
            $newArray[$key] = ['id' => $val['id'], 'button_status' => 0, 'limit' => $val['limit']];
            if ($allgold !== null && $allgold >= $val['limit']) {
                $newArray[$key]['button_status'] = 1;
            }
            if (isset($achLog[$val['id']])) {
                $newArray[$key]['button_status'] = 2;
            }
            // 奖励信息处理
            $reward = [];
            for ($i = 1; $i <= 3; $i++) {
                $arr1 = ['type' => $val['type' . $i], 'num' => $val['num' . $i]];
                if (in_array($arr1['type'], [14, 15, 16], true)) {
                    $arr2 = self::addReward($uid, $arr1, 1, 'rebate_reward_index', $now);
                    if (!empty($arr2['info'])) {
                        $arr1['info'] = $arr2['info'];
                    }
                }
                $reward[] = $arr1;
            }
            $newArray[$key]['reward_info'] = $reward;
        }
        return ['status' => 1, 'allgold' => $allgold, 'list' => $newArray];
    }

    /**
     * 玩家领取返利: c=christmasbuy&m=rebate_reward（id 成就 id）
     *
     * 1 成功；-1 活动未开启；-2 参数错误 / 操作太快了；-3 获取返利信息失败；-4 未达成此成就；-5 已经领取 / 领取日志写入失败
     */
    public static function rebate_reward(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        if ($uid <= 0 || !self::lock(self::REBATE_REWARD_KEY . $uid)) {
            return ['status' => -2, 'msg' => self::LANG['fast_error']];
        }
        try {
            $act = self::init($now);
            if ($act === null) {
                return ['status' => -1, 'msg' => self::LANG['activity_open_error']];
            }
            $id = (int)$ctx->input('id', 0);
            if ($id <= 0) {
                return ['status' => -2, 'msg' => self::LANG['param_error']];
            }
            $rebate = ChristmasbuyGift::ROWS[$id] ?? null;
            if ($rebate === null) {
                return ['status' => -3, 'msg' => self::LANG['rebate_error']];
            }
            // 是否达成了此成就（查询消费元宝数）
            $allgold = self::payLog($uid, $act['version']);
            if ($allgold === null || $allgold < $rebate['limit']) {
                return ['status' => -4, 'msg' => self::LANG['noreach_ach']];
            }
            // 判断玩家是否已经领取了奖励
            if (isset(self::achLog($uid, $act['version'])[$id])) {
                return ['status' => -5, 'msg' => self::LANG['achievement_get_error']];
            }
            // 插入日志记录（唯一键，写入成功才发奖）
            if (!self::addAchLog($uid, $act['version'], $id, $now)) {
                return ['status' => -5, 'msg' => self::LANG['insert_error']];
            }
            $reward = [];
            // 照旧版：$arr1 跨循环复用，上一项的 info 会留在下一项里
            $arr1 = [];
            for ($i = 1; $i <= 3; $i++) {
                $arr1['type'] = $rebate['type' . $i];
                $arr1['num'] = $rebate['num' . $i];
                $arr1['value'] = $rebate['value' . $i];
                $arr2 = self::addReward($uid, $arr1, 0, 'rebate_reward', $now);
                if (!empty($arr2['info'])) {
                    $arr1['info'] = $arr2['info'];
                }
                $reward[] = $arr1;
            }
            $member = DB::table('member')->select('gold', 'silver')->where('uid', $uid)->whereNull('deleted_at')->first() ?? [];
            return [
                'status' => 1,
                'reward' => $reward,
                'allgold' => $allgold,
                'gold' => (int)($member['gold'] ?? 0),
                'silver' => (int)($member['silver'] ?? 0),
            ];
        } finally {
            Redis::del(self::REBATE_REWARD_KEY . $uid);
        }
    }

    /**
     * 玩法介绍: c=christmasbuy&m=play_introduce（折扣表，去掉 id；不判断活动）
     */
    public static function play_introduce(Context $ctx): array
    {
        $info = array_map(static function (array $val): array {
            unset($val['id']);
            return $val;
        }, array_values(ChristmasbuyDiscount::ROWS));
        return ['status' => 1, 'info' => $info];
    }

    /**
     * 计划任务：自动发放返利成就奖励（cron_acttask.php:get_christmasbuy）
     *
     * 只在活动结束后 10 小时内执行（endtime <= 当前 <= endtime + 36000）。累计消费达到且没领过的返利礼包写领取记录，
     * 同类资源合并发放，每人一封信件。
     *
     * @return int -1 无活动；-2 活动未结束；-3 结束超过 10 小时；-4 返利表为空；-5 没有消费记录；1 已执行
     */
    public static function cronReward(int $now): int
    {
        $activity = ActivityClock::byName(self::ACTIVITY);
        if ($activity === null) {
            return -1;
        }
        if ($activity['endtime'] > $now) {
            return -2;
        }
        if ($activity['endtime'] + 36000 < $now) {
            return -3;
        }
        $version = (int)$activity['version'];
        if (ChristmasbuyGift::ROWS === []) {
            return -4;
        }
        // 获取所有玩家消费元宝信息
        $allLog = [];
        foreach (DB::table('christmasbuy_info')->select('uid', 'gold')->where('version', $version)->whereNull('deleted_at')->orderBy('id')->get() as $val) {
            $allLog[(int)$val['uid']] = ($allLog[(int)$val['uid']] ?? 0) + (int)$val['gold'];
        }
        if ($allLog === []) {
            return -5;
        }
        $reward = [];
        foreach ($allLog as $uid => $num) {
            foreach (ChristmasbuyGift::ROWS as $ach) {
                // 消耗元宝数量大于等于成就值并且没领过奖（领取记录写入成功）
                if ($num < $ach['limit'] || !self::addAchLog($uid, $version, $ach['id'], $now)) {
                    continue;
                }
                for ($i = 1; $i <= 3; $i++) {
                    $type = $ach['type' . $i];
                    if (in_array($type, [14, 15, 16], true)) {
                        $value = $ach['value' . $i] === 0 ? 1 : $ach['value' . $i];
                        $reward[$uid][$type][$ach['num' . $i]] = ($reward[$uid][$type][$ach['num' . $i]] ?? 0) + $value;
                    } else {
                        $reward[$uid][$type] = ($reward[$uid][$type] ?? 0) + $ach['num' . $i];
                    }
                }
            }
        }
        foreach ($reward as $uid => $val) {
            $msg = self::LANG['cron_chrismasbuy_end'] . FestReward::LANG_CRON['cron_get_reward'];
            foreach ($val as $type => $small) {
                if (is_array($small)) {
                    foreach ($small as $k => $v) {
                        $msg .= self::cronGrant($uid, ['type' => $type, 'num' => $k, 'value' => $v], $now);
                    }
                } else {
                    $msg .= self::cronGrant($uid, ['type' => $type, 'num' => $small], $now);
                }
            }
            $msg .= FestReward::LANG_CRON['cron_check'];
            MessageHandler::sendSystemMessage([$uid], 1, self::LANG['message_system_title'], $msg);
        }
        return 1;
    }

    /**
     * 计划任务发奖（chrismasbuy_reward），返回信件里的奖励描述
     *
     * @param array{type: int, num: int, value?: int} $info
     */
    private static function cronGrant(int $uid, array $info, int $now): string
    {
        $type = $info['type'];
        $num = $info['num'];
        if ($type === 14) {
            $material = OwnMaterials::template($num);
            if ($material === null) {
                return '';
            }
            OwnMaterials::change($uid, (int)$material['id'], (int)$material['quality'], (string)$material['name'], (int)$material['chip'], (int)$info['value'], 1, $now);
            return $material['name'] . ';';
        }
        if ($type === 15) {
            $item = FestReward::equipInfo($num);
            if ($item !== null) {
                Equipment::addFromTemplate($uid, $num, (int)$info['value']);
            }
            return FestReward::LANG_CRON['cron_equip'] . ($item['name'] ?? '') . ';';
        }
        if ($type === 16) {
            OwnMount::addMount($uid, $num, 0, (int)$info['value'], 'cron_acttask', 'get_christmasbuy', $now);
            return FestReward::LANG_CRON['cron_mount'] . (MountTemp::ROWS[$num]['name'] ?? '') . ';';
        }
        if (!isset(self::LANG_CRON_ITEM[$type])) {
            return '';
        }
        self::addReward($uid, $info, 0, 'get_christmasbuy', $now, 'cron_acttask');
        return self::LANG_CRON_ITEM[$type] . $num . ';';
    }

    /**
     * 向玩家发送奖励 / 取展示信息（_add_reward；$isGet 0 增加奖励，1 只取信息）
     *
     * @param array{type: int, num: int, value?: int} $reward
     * @return array<string, mixed> 锻造材料 / 装备 / 坐骑的 info
     */
    private static function addReward(int $uid, array $reward, int $isGet, string $method, int $now, string $class = 'christmasbuy'): array
    {
        $num = $reward['num'];
        $value = (int)($reward['value'] ?? 0);
        switch ($reward['type']) {
            case 1: // 银币
                Member::addCurrency($uid, silver: $num);
                break;
            case 2: // 黄宝石
                TraderLog::addJade($uid, 2, $num, class: $class, method: $method, now: $now);
                break;
            case 3: // 紫宝石
                TraderLog::addJade($uid, 3, $num, class: $class, method: $method, now: $now);
                break;
            case 4: // 突飞卡
                FreeLeap::add($uid, $num, $now);
                break;
            case 5: // 原魂
                Member::addCurrency($uid, soul: $num);
                break;
            case 6: // 玉石
                MemberJade::add($uid, $num, $class, $method, $now);
                break;
            case 7: // 宝石碎片
                OwnGem::addChip($uid, $num, $class, $method, $now);
                break;
            case 8: // 声望
                Member::addReputation($uid, $num);
                break;
            case 9: // 精炼石
                MemberStone::add($uid, $num, MemberStone::REFINE, $class, $method, $now);
                break;
            case 10: // 五彩石
                MemberStone::add($uid, $num, MemberStone::COLOR, $class, $method, $now);
                break;
            case 11: // 上古血玉
                MemberStone::add($uid, $num, MemberStone::BLOOD, $class, $method, $now);
                break;
            case 12: // 玫瑰
                MemberIntegral::change($uid, $num, 'beauty_rose', 1, $class, $method, $now);
                break;
            case 13: // 钻石
                MemberIntegral::change($uid, $num, 'beauty_diamond', 1, $class, $method, $now);
                break;
            case 14: // 打造材料
                $info = OwnMaterials::template($num);
                if ($info === null) {
                    return [];
                }
                if ($isGet === 0) {
                    OwnMaterials::change($uid, (int)$info['id'], (int)$info['quality'], (string)$info['name'], (int)$info['chip'], $value, 1, $now);
                }
                return ['info' => $info];
            case 15: // 装备
                $item = FestReward::equipInfo($num);
                if ($item === null) {
                    return [];
                }
                if ($isGet === 0) {
                    Equipment::addFromTemplate($uid, $num, $value);
                }
                return ['info' => $item];
            case 16: // 坐骑
                if ($isGet === 0) {
                    OwnMount::addMount($uid, $num, 0, $value, $class, $method, $now);
                }
                return ['info' => FestReward::mountInfo($num, true)];
        }
        return [];
    }

    /**
     * 全部商品（get_buy_list(0)）：num 为今天还能买的数量（减去今天买过的、减去购物车里的，最小 0）；
     * 14 ~ 16 加 info；去掉 value
     *
     * @return list<array<string, mixed>>
     */
    private static function buyList(int $uid, int $version, int $now): array
    {
        $buyList = self::buyInfo($uid, $version, $now);
        $carinfo = self::car($uid);
        $list = [];
        foreach (ChristmasbuyGoods::ROWS as $val) {
            $val['num'] = max(0, $val['num'] - ($buyList[$val['id']] ?? 0));
            if (($carinfo[$val['id']] ?? 0) > 0) {
                $val['num'] = max(0, $val['num'] - $carinfo[$val['id']]);
            }
            $list[] = self::showGoods($uid, $val, $now);
        }
        return $list;
    }

    /**
     * 单个商品（get_buy_list($goods_id)）：num 不取 0；没有该商品为 null
     *
     * @return array<string, mixed>|null
     */
    private static function buyItem(int $uid, int $version, int $id, int $now): ?array
    {
        $val = ChristmasbuyGoods::ROWS[$id] ?? null;
        if ($val === null) {
            return null;
        }
        $val['num'] -= self::buyInfo($uid, $version, $now, $id)[$id] ?? 0;
        $val['num'] -= self::car($uid)[$id] ?? 0;
        return self::showGoods($uid, $val, $now);
    }

    /**
     * @param array<string, mixed> $val
     * @return array<string, mixed>
     */
    private static function showGoods(int $uid, array $val, int $now): array
    {
        if (in_array($val['type'], [14, 15, 16], true)) {
            $reInfo = self::addReward($uid, ['type' => $val['type'], 'num' => $val['value'], 'value' => $val['gnum']], 1, 'index', $now);
            if ($reInfo !== []) {
                $val['info'] = $reInfo['info'];
            }
        }
        unset($val['value']);
        return $val;
    }

    /**
     * 购物车折扣信息（_get_car_info）：price 总价、discount 折扣（总价达到折扣表 price 时为 discount × 0.1，取最后一个达到的）、
     * gold 实际花费（向下取整）
     *
     * @param list<array<string, mixed>> $shopList
     * @param array<int, int> $carinfo
     * @return array{price: int|float, discount: int|float, gold: int|float}
     */
    private static function carInfo(array $shopList, array $carinfo): array
    {
        $return = ['price' => 0, 'discount' => 0, 'gold' => 0];
        $cargoods = array_column($shopList, null, 'id');
        $price = 0;
        foreach ($carinfo as $kid => $gnum) {
            $price += $gnum * ($cargoods[$kid]['price'] ?? 0);
        }
        $return['price'] = $price;
        if ($price > 0) {
            $dis = 1;
            foreach (ChristmasbuyDiscount::ROWS as $val) {
                if ($price >= $val['price']) {
                    $dis = $val['discount'] * 0.1;
                }
            }
            $return['discount'] = $dis;
            $return['gold'] = floor($price * $dis);
        }
        return $return;
    }

    /**
     * 玩家今天的购买数量（get_buyinfo_by_id：goods_id => 合计）
     *
     * @return array<int, int>
     */
    private static function buyInfo(int $uid, int $version, int $now, int $goodsId = 0): array
    {
        $start = (int)strtotime(date('Y-m-d', $now));
        $query = DB::table('christmasbuy_log')->select('goods_id', 'goods_num')->where('version', $version)->where('uid', $uid)
            ->where('dateline', '>=', $start)->where('dateline', '<', $start + 86400)->whereNull('deleted_at');
        if ($goodsId > 0) {
            $query->where('goods_id', $goodsId);
        }
        $return = [];
        foreach ($query->get() as $row) {
            $return[(int)$row['goods_id']] = ($return[(int)$row['goods_id']] ?? 0) + (int)$row['goods_num'];
        }
        return $return;
    }

    /**
     * 购物车（get_car_good）：商品 id => 数量
     *
     * @return array<int, int>
     */
    private static function car(int $uid): array
    {
        $car = Redis::get(self::CAR_KEY . $uid);
        if (!is_array($car)) {
            return [];
        }
        $return = [];
        foreach ($car as $id => $num) {
            $return[(int)$id] = (int)$num;
        }
        return $return;
    }

    /**
     * 保存购物车（add_good）：到今天结束过期（_get_cache_time）
     *
     * @param array<int, int> $goods
     */
    private static function saveCar(int $uid, array $goods, int $now): void
    {
        $ttl = (int)strtotime(date('Ymd', $now + 86400)) - $now - 1;
        Redis::set(self::CAR_KEY . $uid, (object)$goods, max(1, $ttl));
    }

    /**
     * 玩家本期累计消费（get_pay_log 的 allgold：没有记录时 SUM 为 NULL）
     */
    private static function payLog(int $uid, int $version): ?int
    {
        $query = DB::table('christmasbuy_info')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at');
        return $query->exists() ? (int)$query->sum('gold') : null;
    }

    /**
     * 返利领取记录（get_ach_log，按 achieve_id 索引）
     *
     * @return array<int, true>
     */
    private static function achLog(int $uid, int $version): array
    {
        $return = [];
        foreach (DB::table('christmasbuy_gift_log')->select('achieve_id')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')->get() as $row) {
            $return[(int)$row['achieve_id']] = true;
        }
        return $return;
    }

    private static function addAchLog(int $uid, int $version, int $id, int $now): bool
    {
        return DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_christmasbuy_gift_log` (`uid`, `version`, `achieve_id`, `dateline`) VALUES (:uid, :version, :id, :now)',
            ['uid' => $uid, 'version' => $version, 'id' => (string)$id, 'now' => $now]
        ) > 0;
    }

    private static function lock(string $key): bool
    {
        return (bool)Redis::instance()->set($key, 1, ['nx', 'ex' => self::LOCK_TTL]);
    }

    /**
     * 活动信息（_init_activity）：activity_status 开启时返回版本与剩余 cd；否则 null
     *
     * @return array{version: int, cd: int}|null
     */
    private static function init(int $now): ?array
    {
        $info = ActivityClock::open(self::ACTIVITY, $now);
        if ($info === null) {
            return null;
        }
        return ['version' => (int)$info['version'], 'cd' => max(0, (int)$info['endtime'] - $now)];
    }
}
