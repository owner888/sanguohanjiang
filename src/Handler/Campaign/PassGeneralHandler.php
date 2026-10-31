<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Campaign;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\General\TavernHandler;
use Hanjiang\MasterData\ActkemariExchangeLottery;
use Hanjiang\MasterData\Equip;
use Hanjiang\MasterData\EquipEssence;
use Hanjiang\MasterData\General as GeneralTemplate;
use Hanjiang\MasterData\PassgeneralAchievement;
use Hanjiang\MasterData\PassgeneralInfo;
use Hanjiang\MasterData\PassgeneralReward;
use Hanjiang\MasterData\Pub;
use Hanjiang\MasterData\Skill;
use Hanjiang\MasterData\Talent;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\Chat;
use Hanjiang\Model\Equipment;
use Hanjiang\Model\General;
use Hanjiang\Model\Member;
use Hanjiang\Model\MemberJade;
use Hanjiang\Model\MemberStone;
use Hanjiang\Model\OwnGem;
use Hanjiang\Model\OwnMaterials;
use Hanjiang\Model\PassGeneral;
use Hanjiang\Model\PubLog;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 过关斩将（移植旧版 controllers/pass_general.php + models/passgeneral_model.php，路径相对旧版 s1/application/）
 *
 * 限时活动（activity_time.name = pass_general），蹴鞠题材，没有战斗：11 名守将分 4 层（PassgeneralInfo），
 * 每次「过人」按守将的过人概率（rate，强行突破为 100）掷 0 ~ 100，无论成败都按 PassgeneralReward（层数 + 成败）
 * 加权抽一份奖励；过人失败退回本层第一名守将。突破一层后领取该层宝箱（PassgeneralBoxReward；第 4 层为加权抽取的
 * 超级大奖），领完第 4 层回到第 1 名守将并累计通关次数，通关次数达到 PassgeneralAchievement 可领成就奖励；
 * 兑奖券（member_integral.act_kemari_int，与蹴鞠共用）在 ActkemariExchangeLottery 兑换奖品。
 * 普通过人每日 3 次免费（间隔 30 分钟，用完到次日零点），其余按 price1 / price2 扣元宝。
 * 每日首次请求（及计划任务 PassGeneralCron）自动发放未领取的宝箱并重置进度。
 *
 * 活动时间取 MasterData ActivityTime（pass_general：starttime 0 ~ endtime 1483113600，即 2016-12-31 00:00 结束），
 * 现在请求一律返回 -1 活动未开启。当前时间取 BossClock（生产恒为 time()，只有 PHPUnit 能固定时间）。
 *
 * 客户端调用：index、action、achievement_reward、action_achievement_reward、reward_info、action_reward、
 * lottery_index、action_lottery（breakthroughworld.js）。
 *
 * 奖励类型 6 玉石（MemberJade）、9 宝石碎片（OwnGem::addChip）、14 ~ 16 精炼石 / 五彩石 / 上古血石（MemberStone，member 三列）、
 * 17 ~ 25 装备精华（Equipment::addTowerItem）照旧版发放并记流水。
 *
 * 紫装以上装备发小喇叭，7 星以上武将 / 神将发小喇叭与聊天室系统消息（get_message_str 45 / 46）。
 *
 * 依赖未移植：
 * - sub_gold 的翻牌活动计数（flop，旧版数据中已结束）。
 * （元宝 / 声望 / 原魂 / 军令的增减由 Member / OwnSoul 记流水，元宝消耗另计日常任务 5）
 */
final class PassGeneralHandler
{
    /** 过人随机结果值范围（$_start_min / $_end_max） */
    private const int START_MIN = 0;
    private const int END_MAX = 100;

    /** 免费过人间隔（分钟，$_cd_time） */
    private const int CD_TIME = 30;

    /** 防刷锁（旧版 key 后接 uid，30 秒过期） */
    private const string ACTION_LOCK = '_passgeneral_action_lock';
    private const string LOTTERY_LOCK = '_passgeneral_lottery_lock';
    private const string ACHIEVE_ACTION_LOCK = '_passgeneral_acieve_action_lock';
    private const string REWARD_ACTION_LOCK = '_passgeneral_reward_action_lock';
    private const int LOCK_TTL = 30;

    /** 物品流水的执行类（旧版 $this->class，即控制器名） */
    private const string LOG_CLASS = 'pass_general';

    /** 旧版语言包 passgeneral_lang.php */
    private const string LANG_MONTH = '月';
    private const string LANG_DAY = '日';
    private const string LANG_NOT_OPEN = '活动未开启！';
    private const string LANG_ERROR = '获取用户信息失败';
    private const string LANG_INFO_ERROR = '获取过人概率表失败';
    private const string LANG_BOX_REWARD_ERROR = '获取宝箱奖励失败';
    private const string LANG_REWARD_ERROR = '获取过人奖励失败';
    private const string LANG_GOLD_ERROR = '元宝不足';
    /** 7 星以上武将 / 神将小喇叭（passgeneral_lang.php：pass_general_general_get / pass_general_general_pr_get） */
    private const string LANG_GENERAL_GET = '恭喜玩家【%s】在过关斩将中获得%s星武将-%s';
    private const string LANG_GENERAL_PR_GET = '恭喜玩家【%s】在过关斩将中获得%s星神将-%s';
    private const string LANG_FAST_ERROR = '操作太快了！';
    private const string LANG_STYPE_ERROR = '传参类型错误';
    private const string LANG_IS_PASS = '已经突破过该球员';
    private const string LANG_ACHIEVEMENT_ERROR = '成就基础表错误';
    private const string LANG_LOTTERY_REWARD_ERROR = '获取奖券基础表信息失败';
    private const string LANG_LOTTERY_ERROR = '兑奖券不足';
    private const string LANG_USE_LOTTERY_ERROR = '兑换失败';
    private const string LANG_ACHIEVE_NOT_ACHIEVE = '成就未达成';
    private const string LANG_ACHIEVE_ALREADY_ACTION = '已经领取成就奖励';
    private const string LANG_ACHIEVE_LOG_ADDFIELD_ERROR = '添加领取成就日志失败';
    private const string LANG_REWARD_NOT_ENOUGH = '不能领取该宝箱奖励';
    private const string LANG_REWARD_ALREADY_ACTION = '已经领取奖励';
    private const string LANG_REWARD_LOG_ADDFIELD_ERROR = '添加领取奖励日志失败';
    private const string LANG_PASS_ID_IS_NOT = '您不能挑战该球员';
    private const string LANG_REWARD_NOT_GET = '请先领取您的宝箱奖励';
    private const string LANG_GET_REWARD = '恭喜您获得';
    /** pass_general_{id}_reward_info（只定义了 1 ~ 4，其余层旧版为 false） */
    private const array LANG_BOX_INFO = [1 => '第1层奖励', 2 => '第2层奖励', 3 => '第3层奖励', 4 => '超级奖励'];
    /** pass_general_{type}（28 未定义，旧版为 false） */
    private const array LANG_TYPE = [
        1 => '元宝', 2 => '银币', 3 => '黄宝石', 4 => '紫宝石', 5 => '原魂', 6 => '玉石', 7 => '声望', 8 => '装备',
        9 => '魔石碎片', 10 => '突飞卡', 11 => '蓝宝石', 12 => '武将', 13 => '军令', 14 => '精炼石', 15 => '五彩石',
        16 => '上古血石', 17 => '武器精华', 18 => '防具精华', 19 => '饰品精华', 20 => '武器精华', 21 => '防具精华',
        22 => '饰品精华', 23 => '武器精华', 24 => '防具精华', 25 => '饰品精华', 26 => '神将', 27 => '兑奖券',
    ];

    /** 装备精华奖励类型 */
    private const array ESSENCE_TYPES = [17, 18, 19, 20, 21, 22, 23, 24, 25];

    /**
     * 过关斩将-首页: c=pass_general&m=index
     *
     * status：-1 活动未开启；-2 获取玩家信息失败；-3 获取每层宝箱奖励失败；-4 获取过人信息失败；0 失败；1 成功
     */
    public static function index(Context $ctx): array
    {
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        $now = BossClock::now();
        $activity = self::init($member, $now, 'index');
        // 活动未开启
        if ($activity === null) {
            return ['status' => -1, 'msg' => self::LANG_NOT_OPEN];
        }
        $uid = (int)$member['uid'];
        $version = $activity['version'];
        $userInfo = PassGeneral::getInfo($uid, $version, true, $now);
        if ($userInfo === null) {
            return ['status' => -2, 'msg' => self::LANG_ERROR];
        }

        $return['status'] = 1;
        $return['pass_info'] = self::getGeneralStatus($userInfo);
        $return['reward_info'] = self::getBoxRewardStatus($userInfo);
        $return['price_list'] = self::getPriceInfo($userInfo, $now);
        $return['achieve_popo'] = self::getAchieveStatus($userInfo, $version);
        $return['pass_id'] = $userInfo['pass_id'];
        $return['time'] = date('m' . self::LANG_MONTH . 'd' . self::LANG_DAY, $activity['starttime'])
            . '-' . date('m' . self::LANG_MONTH . 'd' . self::LANG_DAY, $activity['endtime']);
        return $return;
    }

    /**
     * 开始过人: c=pass_general&m=action（id 过人 id，type 1 过人 / 2 强行突破）
     *
     * status：-1 活动未开启；-2 传参错误；-3 获取玩家信息错误；-4 获取过人奖励失败；-5 获取过人概率信息错误；
     * 0 失败；1 成功；2 操作太快了；3 元宝不足；4 不能挑战该球员；5 奖励未领取（旧版未使用，未领取时返回 4）
     */
    public static function action(Context $ctx): array
    {
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        $uid = (int)$member['uid'];
        $now = BossClock::now();
        $activity = self::init($member, $now, 'action');
        // 防止刷接口
        if (!self::addLock(self::ACTION_LOCK, $uid)) {
            return ['status' => 2, 'msg' => self::LANG_FAST_ERROR];
        }
        // 活动未开启（旧版此处不解锁，30 秒内再请求返回 2）
        if ($activity === null) {
            return ['status' => -1, 'msg' => self::LANG_NOT_OPEN];
        }
        $version = $activity['version'];

        $gId = (int)$ctx->input('id', 0); // 过人id
        $type = (int)$ctx->input('type', 0); // 1 过人 2强制过人
        if ($gId <= 0 || !in_array($type, [1, 2], true)) {
            self::unlock(self::ACTION_LOCK, $uid);
            return ['status' => -2, 'msg' => self::LANG_STYPE_ERROR];
        }

        // 获取我的信息
        $userInfo = PassGeneral::getInfo($uid, $version);
        if ($userInfo === null) {
            self::unlock(self::ACTION_LOCK, $uid);
            return ['status' => -3, 'msg' => self::LANG_ERROR];
        }
        // 判断是否有未领取的奖励
        if ($userInfo['is_get'] === 2) {
            self::unlock(self::ACTION_LOCK, $uid);
            return ['status' => 4, 'msg' => self::LANG_REWARD_NOT_GET];
        }
        // 已经挑战过该球员
        if ($gId <= $userInfo['is_pass']) {
            self::unlock(self::ACTION_LOCK, $uid);
            return ['status' => 4, 'msg' => self::LANG_IS_PASS];
        }
        // 球员id不正确
        if ($gId !== $userInfo['pass_id']) {
            self::unlock(self::ACTION_LOCK, $uid);
            return ['status' => 4, 'msg' => self::LANG_PASS_ID_IS_NOT];
        }

        $gInfo = PassgeneralInfo::ROWS[$gId] ?? null;
        if ($gInfo === null) {
            self::unlock(self::ACTION_LOCK, $uid);
            return ['status' => -5, 'msg' => self::LANG_INFO_ERROR];
        }
        $price = $gInfo['price' . $type];

        // cd时间<=0 不扣除元宝（普通过人：CD 中或没有免费次数时扣元宝；强行突破总是扣元宝）
        $cd = 0;
        $pay = true;
        if ($type === 1) {
            $cd = $userInfo['normal_time' . $type] - $now;
            if ($cd <= 0 && $userInfo['free_times' . $type] > 0) {
                $pay = false;
            }
        }
        // 判断元宝并扣除（原子扣减，余额不足时同旧版返回 3）
        if ($pay && !Member::deductCurrency($uid, gold: $price)) {
            self::unlock(self::ACTION_LOCK, $uid);
            return ['status' => 3, 'msg' => self::LANG_GOLD_ERROR];
        }

        // 过人概率判断
        $gMt = $type === 2 ? 100 : (int)$gInfo['rate'];
        $gResult = mt_rand(self::START_MIN, self::END_MAX) <= $gMt ? 1 : 0;

        $num = $userInfo['num'] + 1; // 当前正在过的层数
        // 获取奖励信息 层数 是否过人
        $rewardInfo = [];
        foreach (PassgeneralReward::ROWS as $row) {
            if ($row['num'] === $num && $row['is_succ'] === $gResult) {
                $rewardInfo[] = $row;
            }
        }
        if ($rewardInfo === []) {
            // 旧版此时元宝已扣，不退还
            self::unlock(self::ACTION_LOCK, $uid);
            return ['status' => -4, 'msg' => self::LANG_REWARD_ERROR];
        }
        // 已拥有的武将 / 神将不参与抽奖
        foreach ($rewardInfo as $k => $val) {
            if (($val['type'] === 12 || $val['type'] === 26) && PassGeneral::ownsGeneral($uid, $val['value'])) {
                unset($rewardInfo[$k]);
            }
        }
        $typeArr = [];
        $rateTotal = 0;
        foreach ($rewardInfo as $value) {
            $typeArr[] = $value;
            $rateTotal += $value['rate'];
        }
        $reward = PassGeneral::randArr($typeArr, $rateTotal, 1)['info'][0] ?? null; // 最终奖励信息
        if ($reward === null) {
            // 区间都没掷中（旧版同样在扣元宝之后返回 -4，不退还）
            self::unlock(self::ACTION_LOCK, $uid);
            return ['status' => -4, 'msg' => self::LANG_REWARD_ERROR];
        }
        unset($reward['rate_in'], $reward['rate']);
        if (in_array($reward['type'], [8, 12, 26, 28], true)) {
            $reward['id'] = $reward['value'];
        }
        $addReward = self::addReward($member, $reward, 'action', $now);
        switch ($reward['type']) {
            case 8: // 装备（旧版没有 break，同时写入 general）
                $reward['equip'] = $addReward['info'];
                // no break
            case 12: // 武将
            case 26:
                $reward['general'] = $addReward['info'];
                break;
            case 28: // 打造材料
                $reward['equipmairt'] = $addReward['info'];
                break;
            case 17: case 18: case 19: case 20: case 21: case 22: case 23: case 24: case 25: // 装备精华
                $reward['info'] = $addReward['info'];
                break;
        }

        // 添加奖励日志记录
        DB::table('passgeneral_log')->insert([
            'uid' => $uid,
            'version' => $version,
            'num' => $num,
            'num_id' => $gId,
            'g_id' => $gInfo['g_id'],
            'succ_num' => $userInfo['succ_num'],
            'is_succ' => $gResult,
            'type' => $reward['type'],
            'value' => $reward['value'],
            'dateline' => $now,
        ]);

        // 更新用户信息
        $lastId = PassGeneral::lastId();
        if ($gResult === 1) {
            // 过关成功：当前层最后一关突破后宝箱可领取；最后一名守将突破后 pass_id 为 12、通关次数 + 1
            $isGet = $gId === self::lastByNum($num) ? 2 : 1;
            $succNum = $userInfo['succ_num'];
            if ($gId === $lastId) {
                $passId = 12;
                $succNum++;
            } else {
                $passId = $gId + 1;
            }
            $data = [
                'pass_id' => $passId,
                'is_get' => $isGet,
                'is_pass' => $userInfo['is_pass'] + 1,
                'succ_num' => $succNum,
                'dateline' => $now,
            ];
        } else {
            // 过人失败  pass_id 返回到当前层的第一个
            $passId = self::firstByNum($num);
            $data = [
                'pass_id' => $passId,
                'num' => $num - 1,
                'is_pass' => $passId - 1,
                'dateline' => $now,
            ];
        }
        PassGeneral::update($uid, $version, $data);

        // 更新cd时间
        if ($type === 1) {
            if ($cd <= 0) {
                $freeTimes = $userInfo['free_times' . $type] - 1;
                if ($freeTimes <= 0) {
                    $normalTime = strtotime(date('Y-m-d', strtotime('+1 day', $now)));
                    $freeTimes = 0;
                } else {
                    $normalTime = $now + self::CD_TIME * 60;
                }
            } else {
                $freeTimes = $userInfo['free_times' . $type];
                $normalTime = $userInfo['normal_time' . $type];
            }
            PassGeneral::update($uid, $version, [
                'free_times' . $type => $freeTimes,
                'normal_time' . $type => $normalTime,
            ]);
        }

        $userInfo = PassGeneral::getInfo($uid, $version);
        $priceList = self::getPriceInfo($userInfo, $now);
        $achieveStatus = self::getAchieveStatus($userInfo, $version);
        self::unlock(self::ACTION_LOCK, $uid);
        return [
            'status' => 1,
            'reward' => $reward,
            'is_succ' => $gResult,
            'pass_info' => self::getGeneralStatus($userInfo),
            'pass_id' => $userInfo['pass_id'],
            'reward_info' => self::getBoxRewardStatus($userInfo),
            'price_list' => $priceList,
            'all_gold' => self::memberValue($uid, 'gold'),
            'achieve_popo' => $achieveStatus,
        ];
    }

    /**
     * 过人成就奖励界面: c=pass_general&m=achievement_reward
     *
     * status：-1 活动未开启；-2 成就基础表错误；-3 个人信息错误；0 失败；1 成功
     */
    public static function achievement_reward(Context $ctx): array
    {
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return ['status' => 0];
        }
        $now = BossClock::now();
        $activity = self::init($member, $now, 'achievement_reward');
        if ($activity === null) {
            return ['status' => -1, 'msg' => self::LANG_NOT_OPEN];
        }
        $version = $activity['version'];
        $userInfo = PassGeneral::getInfo((int)$member['uid'], $version);
        if ($userInfo === null) {
            return ['status' => -3, 'msg' => self::LANG_ERROR];
        }

        $logged = self::achievementLogs((int)$member['uid'], $version);
        $newList = [];
        $numList = [];
        foreach (PassgeneralAchievement::ROWS as $value) {
            $tmp = [];
            $tmp['id'] = $value['id'];
            $tmp['desc'] = $value['desc'];
            $tmp['open'] = 0; // 是否可以领取奖励 2，已经领取；1，可以领取；0，不能领取；
            if ($userInfo['succ_num'] >= $value['num']) {
                $tmp['open'] = isset($logged[$value['id']]) ? 2 : 1;
            }
            // 过关次数
            $numList[] = $value['num'];
            // 奖励信息 3种奖励信息
            for ($i = 1; $i <= 3; $i++) {
                $type = $value['type' . $i];
                $tmp['type' . $i] = $type;
                $tmp['value' . $i] = $value['value' . $i];
                // 特殊处理
                if (in_array($type, [8, 13, 26, 28], true)) {
                    $tmp['info' . $i] = self::rewardItem($type, $value['value' . $i])['info'] ?? null;
                } elseif (in_array($type, self::ESSENCE_TYPES, true)) {
                    $tmp['info' . $i]['id'] = PassGeneral::ESSENCE_ID[$type];
                    $essInfo = self::rewardItem($type, $tmp['info' . $i]['id']);
                    if ($essInfo !== []) {
                        $tmp['info' . $i]['value'] = $value['value' . $i]; // 奖励数量
                        $tmp['info' . $i]['quality'] = $essInfo['info']['quality']; // 材料品级
                    }
                }
            }
            $newList[] = $tmp;
        }

        return [
            'status' => 1,
            'list' => $newList,
            'num_list' => $numList,
            'num' => $userInfo['succ_num'],
        ];
    }

    /**
     * 领取过人成就奖励: c=pass_general&m=action_achievement_reward（aid 成就 id）
     *
     * status：-1 活动未开启；-2 成就基础信息表错误；-3 参数错误；0 失败；1 成功；2 未达成成就；3 已经领取成就奖励；
     * 4 添加成就奖励日志失败；5 操作太频繁
     */
    public static function action_achievement_reward(Context $ctx): array
    {
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return ['status' => 0];
        }
        $uid = (int)$member['uid'];
        $now = BossClock::now();
        $activity = self::init($member, $now, 'action_achievement_reward');
        // 防刷接口
        if (!self::addLock(self::ACHIEVE_ACTION_LOCK, $uid)) {
            return ['status' => 5, 'msg' => self::LANG_FAST_ERROR];
        }
        if ($activity === null) {
            self::unlock(self::ACHIEVE_ACTION_LOCK, $uid);
            return ['status' => -1, 'msg' => self::LANG_NOT_OPEN];
        }
        $version = $activity['version'];
        // 接收参数 成就id
        $id = (int)$ctx->input('aid', 0);
        if ($id <= 0) {
            self::unlock(self::ACHIEVE_ACTION_LOCK, $uid);
            return ['status' => -3, 'msg' => self::LANG_STYPE_ERROR];
        }
        // 获取成就基础信息表
        $rewardInfo = PassgeneralAchievement::ROWS[$id] ?? null;
        if ($rewardInfo === null) {
            self::unlock(self::ACHIEVE_ACTION_LOCK, $uid);
            return ['status' => -2, 'msg' => self::LANG_LOTTERY_REWARD_ERROR];
        }
        // 获取用户通关次数（没有记录时旧版按 null < num 返回 2）
        $userInfo = PassGeneral::getInfo($uid, $version);
        if ($userInfo === null || $userInfo['succ_num'] < $rewardInfo['num']) {
            self::unlock(self::ACHIEVE_ACTION_LOCK, $uid);
            return ['status' => 2, 'msg' => self::LANG_ACHIEVE_NOT_ACHIEVE];
        }
        // 获取玩家领取成就日志
        if (isset(self::achievementLogs($uid, $version)[$id])) {
            self::unlock(self::ACHIEVE_ACTION_LOCK, $uid);
            return ['status' => 3, 'msg' => self::LANG_ACHIEVE_ALREADY_ACTION];
        }
        // 添加领取成就奖励日志（唯一键：与活动结束后的自动领取并发时只发一次）
        if (PassGeneral::addAchievementLog($uid, $version, $id, $now) !== 1) {
            // 旧版这里调用未加载的 acttreasure_model->unlock（致命错误，锁留到过期）；按旧版状态返回并解锁
            self::unlock(self::ACHIEVE_ACTION_LOCK, $uid);
            return ['status' => 4, 'msg' => self::LANG_ACHIEVE_LOG_ADDFIELD_ERROR];
        }
        // 发放奖励
        $reward = [];
        for ($i = 1; $i <= 3; $i++) {
            $reward['type'] = $rewardInfo['type' . $i];
            $reward['value'] = $rewardInfo['value' . $i];
            if (in_array($reward['type'], [8, 12, 26, 28], true)) {
                $reward['id'] = $reward['value'];
            }
            self::addReward($member, $reward, 'action_achievement_reward', $now);
        }
        // 获取当前成就状态信息
        $achieveStatus = self::getAchieveStatus($userInfo, $version);
        self::unlock(self::ACHIEVE_ACTION_LOCK, $uid);
        return [
            'achieve_popo' => $achieveStatus,
            'status' => 1,
            'all_gold' => self::memberValue($uid, 'gold'),
            'all_silver' => self::memberValue($uid, 'silver'),
        ];
    }

    /**
     * 宝箱奖励预览: c=pass_general&m=reward_info（id 层数）
     *
     * status：-1 活动未开启；-2 获取奖励信息失败；0 失败；1 成功；2 参数错误
     */
    public static function reward_info(Context $ctx): array
    {
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return ['status' => 0];
        }
        $now = BossClock::now();
        $activity = self::init($member, $now, 'reward_info');
        if ($activity === null) {
            return ['status' => -1, 'msg' => self::LANG_NOT_OPEN];
        }
        // 判断参数类型
        $id = (int)$ctx->input('id', 0);
        if ($id <= 0) {
            return ['status' => 2, 'msg' => self::LANG_STYPE_ERROR];
        }
        // 根据type值获得奖励信息
        $rewardInfo = PassGeneral::boxReward($id);
        if ($rewardInfo === []) {
            return ['status' => -2, 'msg' => self::LANG_BOX_REWARD_ERROR];
        }

        // 排除 type = 0 的奖励；装备、武将、神将以外的类型只取第一次出现的
        $newArray = [];
        $typeArray = [];
        for ($i = 1; $i <= 3; $i++) {
            foreach ($rewardInfo as $val) {
                if ($val['type' . $i] !== 0) {
                    $array = ['type' => $val['type' . $i], 'value' => $val['value' . $i]];
                    if (!in_array($array['type'], [8, 12, 26], true)) {
                        if (!in_array($array['type'], $typeArray, true)) {
                            $typeArray[] = $array['type'];
                            $newArray[] = $array;
                        }
                    } else {
                        $newArray[] = $array;
                    }
                }
            }
        }
        $list = self::rewardChongzhi($newArray);
        // 武将按星级从高到低排在最后
        $generals = [];
        foreach ($list as $key => $val) {
            if ($val['type'] === 12 || $val['type'] === 26) {
                $generals[] = $val;
                unset($list[$key]);
            }
        }
        $list = array_values($list);
        foreach (self::arraySort($generals, 'star', 'desc') as $val) {
            $list[] = $val;
        }

        // 获取当前宝箱奖励是否可领取
        $userInfo = PassGeneral::getInfo((int)$member['uid'], $activity['version']);
        if ($userInfo === null) {
            return ['status' => -2, 'msg' => self::LANG_ERROR];
        }
        // 过关层数
        $num = $userInfo['num'] + 1;
        if ($num > $id) {
            $rewardStatus = 3;
        } elseif ($num < $id) {
            $rewardStatus = 1;
        } else {
            $rewardStatus = $userInfo['is_get'];
        }

        return [
            'status' => 1,
            'reward' => $list,
            'info' => self::LANG_BOX_INFO[$id] ?? false,
            'reward_status' => $rewardStatus,
        ];
    }

    /**
     * 领取宝箱奖励: c=pass_general&m=action_reward（id 层数）
     *
     * status：-1 活动未开启；-2 宝箱信息表错误；-3 参数错误；0 失败；1 成功；2 不能领取该奖励；
     * 3 已经领取奖励 / 添加领取日志失败；4 操作太频繁
     */
    public static function action_reward(Context $ctx): array
    {
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return ['status' => 0];
        }
        $uid = (int)$member['uid'];
        $now = BossClock::now();
        $activity = self::init($member, $now, 'action_reward');
        // 防刷接口
        if (!self::addLock(self::REWARD_ACTION_LOCK, $uid)) {
            return ['status' => 4, 'msg' => self::LANG_FAST_ERROR];
        }
        if ($activity === null) {
            self::unlock(self::REWARD_ACTION_LOCK, $uid);
            return ['status' => -1, 'msg' => self::LANG_NOT_OPEN];
        }
        $version = $activity['version'];
        $id = (int)$ctx->input('id', 0);
        if ($id <= 0) {
            self::unlock(self::REWARD_ACTION_LOCK, $uid);
            return ['status' => -3, 'msg' => self::LANG_STYPE_ERROR];
        }
        // 获取基础信息表
        $rewardInfo = PassGeneral::boxReward($id);
        if ($rewardInfo === []) {
            self::unlock(self::REWARD_ACTION_LOCK, $uid);
            return ['status' => -2, 'msg' => self::LANG_BOX_REWARD_ERROR];
        }
        // 判断是否可以领取奖励 1 未达成 2可领取 3 已领取
        $userInfo = PassGeneral::getInfo($uid, $version);
        if ($userInfo !== null && $userInfo['is_get'] === 3) {
            self::unlock(self::REWARD_ACTION_LOCK, $uid);
            return ['status' => 3, 'msg' => self::LANG_REWARD_ALREADY_ACTION];
        }
        // 没有玩家记录时旧版会按空记录发放第 1 层并写入空日志；这里按不能领取返回
        if ($userInfo === null || $userInfo['is_get'] === 1 || $userInfo['num'] + 1 !== $id) {
            self::unlock(self::REWARD_ACTION_LOCK, $uid);
            return ['status' => 2, 'msg' => self::LANG_REWARD_NOT_ENOUGH];
        }

        // 获取最高层数是否到可领取超级大奖
        $maxNum = PassGeneral::maxNum();
        if ($id === $maxNum) {
            $rateTotal = 0;
            foreach ($rewardInfo as $value) {
                $rateTotal += $value['rate'];
            }
            $newReward = PassGeneral::randArr($rewardInfo, $rateTotal, 1)['info']; // 随机获取奖励
        } else {
            $newReward = $rewardInfo;
        }

        // 更新层数 过人id 过关次数（旧版发奖后才更新；这里先以 is_get = 2 原子抢占，与每日自动领取并发时只发一次）
        if ($id === $maxNum) {
            $data = ['num' => 0, 'pass_id' => 1, 'is_pass' => 0, 'is_get' => 1, 'dateline' => $now];
        } else {
            $data = ['num' => $userInfo['num'] + 1, 'is_get' => 1, 'dateline' => $now];
        }
        $claimed = DB::table('passgeneral')->where('id', $userInfo['id'])->where('is_get', 2)->where('num', $userInfo['num'])
            ->whereNull('deleted_at')->update($data);
        if ($claimed <= 0) {
            self::unlock(self::REWARD_ACTION_LOCK, $uid);
            return ['status' => 2, 'msg' => self::LANG_REWARD_NOT_ENOUGH];
        }

        // 添加领取日志（旧版 value1 记的是 type1）
        foreach ($newReward as $value) {
            $added = PassGeneral::addBoxLog($uid, [
                'version' => $version,
                'num' => $userInfo['num'],
                'type1' => $value['type1'],
                'value1' => $value['type1'],
                'type2' => $value['type2'],
                'value2' => $value['value2'],
                'type3' => $value['type3'],
                'value3' => $value['value3'],
            ], $now);
            if ($added !== 1) {
                // 旧版这里调用未加载的 acttreasure_model->unlock（致命错误）；按旧版状态返回并解锁
                self::unlock(self::REWARD_ACTION_LOCK, $uid);
                return ['status' => 3, 'msg' => self::LANG_REWARD_LOG_ADDFIELD_ERROR];
            }
        }

        // 发放奖励（只发第一行）
        $newReturn = [];
        $reward = [];
        for ($i = 1; $i <= 3; $i++) {
            if (isset($newReward[0]['type' . $i])) {
                $reward['type'] = $newReward[0]['type' . $i];
                $reward['value'] = $newReward[0]['value' . $i];
                if ($reward['type'] !== 0) {
                    if (in_array($reward['type'], [8, 12, 26, 28], true)) {
                        $reward['id'] = $reward['value'];
                    }
                    $added = self::addReward($member, $reward, 'action_reward', $now);
                    if (!empty($added['info'])) {
                        $newReturn[] = $added['info'];
                    }
                }
            }
        }

        $userInfo = PassGeneral::getInfo($uid, $version);
        $info = self::getGeneralStatus($userInfo);
        $boxInfo = self::getBoxRewardStatus($userInfo);
        $priceList = self::getPriceInfo($userInfo, $now);
        self::unlock(self::REWARD_ACTION_LOCK, $uid);
        return [
            'status' => 1,
            'pass_id' => $userInfo['pass_id'],
            'all_gold' => self::memberValue($uid, 'gold'),
            'is_full' => 0,
            'reward' => $newReturn,
            'pass_info' => $info,
            'reward_info' => $boxInfo,
            'price_list' => $priceList,
        ];
    }

    /**
     * 进入奖券兑换界面: c=pass_general&m=lottery_index
     *
     * status：-1 活动未开启；-2 获取奖励信息失败；-3 获取武将信息失败；0 失败；1 成功
     */
    public static function lottery_index(Context $ctx): array
    {
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return ['status' => 0];
        }
        $now = BossClock::now();
        if (self::init($member, $now, 'lottery_index') === null) {
            return ['status' => -1, 'msg' => self::LANG_NOT_OPEN];
        }
        // 获取我的兑奖券
        $lotteryNum = PassGeneral::lotteryInfo((int)$member['uid']);
        return [
            'status' => 1,
            'list' => self::lotteryList($lotteryNum),
            'lottery_num' => $lotteryNum,
        ];
    }

    /**
     * 兑换奖券: c=pass_general&m=action_lottery（id 兑换项 id）
     *
     * status：-1 活动未开启；-2 参数错误；-3 获取奖励信息失败；0 失败；1 成功；2 操作太频繁；3 奖券不足；4 兑换失败
     */
    public static function action_lottery(Context $ctx): array
    {
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return ['status' => 0];
        }
        $uid = (int)$member['uid'];
        $now = BossClock::now();
        $activity = self::init($member, $now, 'action_lottery');
        // 防刷接口
        if (!self::addLock(self::LOTTERY_LOCK, $uid)) {
            return ['status' => 2, 'msg' => self::LANG_FAST_ERROR];
        }
        if ($activity === null) {
            self::unlock(self::LOTTERY_LOCK, $uid);
            return ['status' => -1, 'msg' => self::LANG_NOT_OPEN];
        }
        $id = (int)$ctx->input('id', 0);
        if ($id <= 0) {
            // 旧版解的是未定义的 $this->_kemari_lock，兑奖锁留到 30 秒过期
            return ['status' => -2, 'msg' => self::LANG_STYPE_ERROR];
        }
        // 获取奖励
        $rewardInfo = ActkemariExchangeLottery::ROWS[$id] ?? null;
        if ($rewardInfo === null) {
            self::unlock(self::LOTTERY_LOCK, $uid);
            return ['status' => -3, 'msg' => self::LANG_LOTTERY_REWARD_ERROR];
        }
        // 兑换兑换券（旧版先读后写绝对值；这里原子扣减，不足时同旧版返回 3）
        PassGeneral::lotteryInfo($uid);
        $affected = DB::execute(
            'UPDATE `zc_sanguo_member_integral` SET `act_kemari_int` = `act_kemari_int` - :num
             WHERE `uid` = :uid AND `act_kemari_int` >= :need AND `deleted_at` IS NULL',
            ['num' => $rewardInfo['lottery_num'], 'need' => $rewardInfo['lottery_num'], 'uid' => $uid]
        );
        if ($affected <= 0) {
            self::unlock(self::LOTTERY_LOCK, $uid);
            return ['status' => 3, 'msg' => self::LANG_LOTTERY_ERROR];
        }
        $num = PassGeneral::lotteryInfo($uid);

        // 添加积分变化日志
        PassGeneral::addMemintLog($uid, 2, $rewardInfo['lottery_num'], $num, 'action_lottery', $now);
        // 发奖
        if (in_array($rewardInfo['type'], [8, 12, 26], true)) {
            $rewardInfo['id'] = $rewardInfo['value'];
        } elseif ($rewardInfo['type'] === 28) { // 打造材料
            $rewardInfo['id'] = $rewardInfo['value'];
            $rewardInfo['value'] = $rewardInfo['value1'];
        }
        $addReward = self::addReward($member, $rewardInfo, 'action_lottery', $now);

        // 添加兑奖日志
        DB::table('passgeneral_exchange_log')->insert([
            'uid' => $uid,
            'version' => $activity['version'],
            'nickname' => (string)$member['nickname'],
            'type' => $rewardInfo['type'],
            'value' => $rewardInfo['value'],
            'value1' => $rewardInfo['value1'],
            'lottery_num' => $rewardInfo['lottery_num'],
            'dateline' => $now,
        ]);
        $rewardInfo['is_exch'] = $num >= $rewardInfo['lottery_num'] ? 1 : 0;
        switch ($rewardInfo['type']) {
            case 8: // 装备
                $rewardInfo['equip'] = $addReward['info'];
                break;
            case 12: // 武将（旧版没有 break，同时写入 equipmairt）
            case 26:
                $rewardInfo['general'] = $addReward['info'];
                // no break
            case 28: // 打造材料
                $rewardInfo['equipmairt'] = $addReward['info'];
                break;
            case 17: case 18: case 19: case 20: case 21: case 22: case 23: case 24: case 25: // 装备精华
                $rewardInfo['info'] = $addReward['info'];
                break;
        }

        // 获取我的兑奖券
        $list = self::lotteryList(PassGeneral::lotteryInfo($uid));
        self::unlock(self::LOTTERY_LOCK, $uid);
        return [
            'status' => 1,
            'lottery_num' => $num,
            'list' => $list,
            'reward' => $rewardInfo,
        ];
    }

    /**
     * 每次请求本控制器时的初始化（构造函数 _init）：活动开启时执行每日清理（$method 为当前接口，记物品流水用）
     *
     * @param array<string, mixed> $member
     * @return array<string, mixed>|null 活动信息；未开启时为 null
     */
    private static function init(array $member, int $now, string $method): ?array
    {
        if (!PassGeneral::isOpen($now)) {
            return null;
        }
        $activity = PassGeneral::activity();
        // 每日清理过关斩将信息
        PassGeneral::dailyUpdate((int)$member['uid'], $activity['version'], $now, self::LOG_CLASS, $method);
        return $activity;
    }

    /**
     * 添加奖励信息（add_reward，$num 恒为 0：数量取 value）
     *
     * @param array<string, mixed> $member
     * @param array<string, mixed> $rewardInfo 至少含 type / value；装备、武将、神将、材料另含 id
     * @return array{info: array<string, mixed>}
     */
    private static function addReward(array $member, array $rewardInfo, string $method, int $now): array
    {
        $uid = (int)$member['uid'];
        $num = (int)$rewardInfo['value'];
        $type = (int)$rewardInfo['type'];
        $id = (int)($rewardInfo['id'] ?? 0);
        $return = [];
        $return['info'] = [
            'type' => $type,
            'value' => $num,
            'name' => self::LANG_TYPE[$type] ?? false,
        ];

        switch ($type) {
            case 1: // 元宝
                Member::addCurrency($uid, gold: $num);
                break;
            case 2: // 银币
                Member::addCurrency($uid, silver: $num);
                break;
            case 3: // 黄宝石
                TavernHandler::addJade($uid, 2, $num, self::LOG_CLASS, $method);
                break;
            case 4: // 紫宝石
                TavernHandler::addJade($uid, 3, $num, self::LOG_CLASS, $method);
                break;
            case 5: // 原魂
                Member::addCurrency($uid, soul: $num);
                break;
            case 6: // 玉石
                MemberJade::add($uid, $num, self::LOG_CLASS, $method, $now);
                break;
            case 7: // 声望
                Member::addCurrency($uid, reputation: $num);
                break;
            case 8: // 装备
                $item = Equip::ROWS[$id] ?? null;
                if ($item !== null) {
                    Equipment::addFromTemplate($uid, $id);
                }
                // 发送广播到小喇叭：只发紫装以上品质的装备
                if ((int)($item['quality'] ?? 0) >= 5) {
                    $name = Chat::quality($item['quality'], (string)$item['name']);
                    $sender = Chat::sender($uid);
                    Chat::sendToHorn(sprintf(Chat::LANG['chat_passgeneral_get_quality'], $member['nickname'], $name), $sender);
                }
                $return['info'] = [
                    'id' => $item['id'] ?? null,
                    'image' => $item['id'] ?? null,
                    'name' => $item['name'] ?? null,
                    'quality' => $item['quality'] ?? null,
                    'type' => 8,
                    'value' => 1,
                ];
                break;
            case 9: // 宝石碎片
                OwnGem::addChip($uid, $num, self::LOG_CLASS, $method, $now);
                break;
            case 10: // 突飞卡
                PassGeneral::addFreeleap($uid, $num);
                break;
            case 11: // 蓝宝石
                TavernHandler::addJade($uid, 1, $num, self::LOG_CLASS, $method);
                break;
            case 12: // 武将
            case 26: // 神将
                $gid = $id;
                // 查询自己是否已经获得武将
                if (PassGeneral::ownsGeneral($uid, $gid)) {
                    // 查询武将的紫宝石价格信息
                    $pub = Pub::ROWS[$gid] ?? null;
                    if ($pub !== null) {
                        $jadenum = (int)$pub['price'] * PassGeneral::REBATE;
                        TavernHandler::addJade($uid, 3, (int)round($jadenum), self::LOG_CLASS, $method);
                        $return['info']['type'] = 3;
                        $return['info']['value'] = $jadenum;
                        $return['info']['name'] = self::LANG_TYPE[4];
                        $return['info']['msg'] = self::LANG_GET_REWARD . self::LANG_TYPE[4] . '*' . $jadenum;
                    }
                } else {
                    // 添加武将
                    $general = General::addGeneral($uid, $gid);
                    if ($general !== null) {
                        $return['info'] = $general;
                        // 7 星以上：小喇叭 + 聊天框（武将 pass_general_general_get / 45，神将 pass_general_general_pr_get / 46）
                        $num = Chat::starWord((int)$general['star']);
                        if ($num !== null) {
                            $sender = Chat::sender($uid);
                            $horn = $type === 26 ? self::LANG_GENERAL_PR_GET : self::LANG_GENERAL_GET;
                            Chat::sendToHorn(sprintf($horn, $member['nickname'], $num, $general['name']), $sender);
                            // 聊天框发送消息
                            $chat = Chat::getMessageStr($type === 26 ? 46 : 45, ['star_general' => $num, 'general' => $general['name']], $sender);
                            Chat::sendToAll($chat, Chat::TYPE_SYSTEM, 0, 0, $sender);
                        }
                        // 酒馆获得日志（tavern_model->add_pub_log(gid, 1, 11)）
                        PubLog::add($uid, $gid, 1, 11);
                    }
                }
                break;
            case 13: // 军令
                Member::addCurrency($uid, act: $num);
                break;
            case 14: // 精炼石
            case 15: // 五彩石
            case 16: // 上古血玉（member_model->add_stone 类型 1 / 2 / 3，存 member 三列）
                MemberStone::add($uid, $num, $type - 13, self::LOG_CLASS, $method, $now);
                break;
            case 17: case 18: case 19: case 20: case 21: case 22: case 23: case 24: case 25: // 装备精华
                $essInfo = EquipEssence::ROWS[PassGeneral::ESSENCE_ID[$type]] ?? [];
                unset($essInfo['desc']); // 去除描述信息
                if ($essInfo !== []) {
                    $essInfo['value'] = $num; // 奖励数量
                    Equipment::addTowerItem($uid, PassGeneral::ESSENCE_ID[$type], $num);
                }
                $return['info'] = $essInfo;
                $return['info']['type'] = $type;
                $return['info']['num'] = $num;
                break;
            case 27: // 兑奖券
                $balance = PassGeneral::addLottery($uid, (int)$rewardInfo['value']);
                if ($method !== 'action_lottery') {
                    // 添加积分变化日志
                    PassGeneral::addMemintLog($uid, 1, (int)$rewardInfo['value'], $balance, $method, $now);
                }
                break;
            case 28: // 打造材料
                $info = OwnMaterials::template($id);
                if ($info !== null) {
                    OwnMaterials::change($uid, $info['id'], $info['quality'], $info['name'], $info['chip'], (int)$rewardInfo['value'], 1, $now);
                    $return['info'] = $info;
                }
                break;
        }
        return $return;
    }

    /**
     * 对奖励信息处理（reward_item：成就界面的装备、武将（13 / 26）、精华、材料展示信息）
     *
     * @return array<string, mixed>
     */
    private static function rewardItem(int $type, int $id): array
    {
        $return = [];
        if ($type <= 0) {
            return $return;
        }
        switch ($type) {
            case 8: // 装备
                $item = Equip::ROWS[$id] ?? null;
                $return['info'] = [];
                if ($item !== null) {
                    $return['info'] = [
                        'id' => $item['id'],
                        'image' => $item['id'],
                        'name' => $item['name'],
                        'type' => $item['type'],
                        'quality' => $item['quality'],
                        'needlevel' => $item['needlevel'],
                        'power' => $item['power'],
                        'desc' => $item['description'],
                    ];
                }
                break;
            case 13: // 武将
            case 26: // 神将
                $return['info'] = [];
                $general = GeneralTemplate::ROWS[$id] ?? null;
                if ($general !== null) {
                    $return['info'] = $general;
                    $return['info']['talent'] = Talent::ROWS[$general['talentid']] ?? [];
                    // 旧版神将的技能也按 talentid 查（照搬）
                    $return['info']['skill'] = Skill::ROWS[$type === 26 ? $general['talentid'] : $general['skillid']] ?? [];
                }
                break;
            case 17: case 18: case 19: case 20: case 21: case 22: case 23: case 24: case 25: // 装备精华
                $essInfo = EquipEssence::ROWS[PassGeneral::ESSENCE_ID[$type]] ?? null;
                if ($essInfo !== null) {
                    unset($essInfo['desc']); // 去除描述信息
                    $return['info'] = $essInfo;
                }
                break;
            case 28: // 锻造材料
                $info = OwnMaterials::template($id);
                if ($info !== null) {
                    $return['info'] = $info;
                }
                break;
        }
        return $return;
    }

    /**
     * 统一返回（_reward_chongzhi：宝箱预览 / 兑奖列表的奖励展示信息）
     *
     * @param list<array<string, mixed>> $newReward
     * @return list<array<string, mixed>>
     */
    private static function rewardChongzhi(array $newReward, bool $lottery = false): array
    {
        $list = [];
        foreach ($newReward as $value) {
            $tmp = [];
            $tmp['type'] = $value['type'];
            if ($value['type'] === 28) {
                $tmp['mid'] = $value['value'];
                $tmp['value'] = $value['value1'];
            } else {
                $tmp['value'] = $value['value'];
            }
            if ($lottery) {
                $tmp['lottery_num'] = $value['lottery_num'];
                $tmp['id'] = $value['id'];
            }
            $list[] = $tmp;
        }
        // 重置奖励信息
        foreach ($list as &$val) {
            switch ($val['type']) {
                case 12: // 武将或者神将
                case 26:
                    $general = GeneralTemplate::ROWS[$val['value']] ?? [];
                    $val['general'] = $general;
                    $val['name'] = $general['name'] ?? null;
                    $val['star'] = $general['star'] ?? null;
                    // 查询天赋、技能
                    $talent = Talent::ROWS[$general['talentid'] ?? 0] ?? [];
                    $val['general']['talent'] = ['name' => $talent['name'] ?? null, 'description' => $talent['description'] ?? null];
                    $skill = Skill::ROWS[$general['skillid'] ?? 0] ?? [];
                    $val['general']['skill'] = ['name' => $skill['name'] ?? null, 'description' => $skill['description'] ?? null];
                    break;
                case 8:
                    $item = Equip::ROWS[$val['value']] ?? [];
                    $val['name'] = $item['name'] ?? null;
                    $val['info'] = [
                        'id' => $item['id'] ?? null,
                        'image' => $item['id'] ?? null,
                        'name' => $item['name'] ?? null,
                        'quality' => $item['quality'] ?? null,
                        'needlevel' => $item['needlevel'] ?? null,
                        'power' => $item['power'] ?? null,
                        'type' => $item['type'] ?? null,
                    ];
                    break;
                case 17: case 18: case 19: case 20: case 21: case 22: case 23: case 24: case 25: // 装备精华
                    $essInfo = EquipEssence::ROWS[PassGeneral::ESSENCE_ID[$val['type']]] ?? null;
                    if ($essInfo !== null) {
                        unset($essInfo['desc']); // 去除描述信息
                        $val['info'] = $essInfo;
                    }
                    break;
                case 28: // 锻造材料
                    $info = OwnMaterials::template($val['mid']);
                    if ($info !== null) {
                        $val['info'] = $info;
                    }
                    unset($val['mid']);
                    break;
                default:
                    if (isset(self::LANG_TYPE[$val['type']])) {
                        $val['name'] = self::LANG_TYPE[$val['type']];
                    }
                    break;
            }
        }
        unset($val);
        return $list;
    }

    /**
     * 兑奖列表（lottery_index / action_lottery：ActkemariExchangeLottery 全表 + 是否可兑换）
     *
     * @return list<array<string, mixed>>
     */
    private static function lotteryList(int $lotteryNum): array
    {
        $list = self::rewardChongzhi(array_values(ActkemariExchangeLottery::ROWS), true);
        foreach ($list as &$value) {
            $value['is_exch'] = $lotteryNum >= $value['lottery_num'] ? 1 : 0;
        }
        unset($value);
        return $list;
    }

    /**
     * 武将排序（_array_sort）
     *
     * @param list<array<string, mixed>> $arr
     * @return list<array<string, mixed>>
     */
    private static function arraySort(array $arr, string $keys, string $type = 'asc'): array
    {
        $keysvalue = [];
        foreach ($arr as $k => $v) {
            $keysvalue[$k] = $v[$keys];
        }
        if ($type === 'asc') {
            asort($keysvalue);
        } else {
            arsort($keysvalue);
        }
        $newArray = [];
        foreach (array_keys($keysvalue) as $k) {
            $newArray[] = $arr[$k];
        }
        return $newArray;
    }

    /**
     * 获取球员状态（get_general_status：1 已突破、2 当前要突破、3 未到）
     *
     * @param array<string, int> $userInfo
     * @return list<array<string, mixed>>
     */
    private static function getGeneralStatus(array $userInfo): array
    {
        $lastId = PassGeneral::lastId();
        $return = [];
        foreach (array_values(PassgeneralInfo::ROWS) as $key => $val) {
            $return[$key]['id'] = $val['id'];
            $general = GeneralTemplate::ROWS[$val['g_id']] ?? null;
            if ($general !== null) {
                $return[$key]['name'] = $general['name'];
                $return[$key]['image'] = $general['image'];
            }
            if ($userInfo['pass_id'] === $lastId + 1) {
                // 最后一个武将已突破 球消失
                $return[$key]['show_status'] = 1;
            } elseif ($userInfo['pass_id'] === $val['id']) {
                $return[$key]['show_status'] = 2;
            } elseif ($userInfo['pass_id'] < $val['id']) {
                $return[$key]['show_status'] = 3;
            } else {
                $return[$key]['show_status'] = 1;
            }
        }
        return $return;
    }

    /**
     * 获取当前过人价格信息（get_price_info + passgeneral_model->pass_cd）
     *
     * 旧版照搬：没有 CD 且没有免费次数时 cd 为 0 - 当前时间（负数）。
     *
     * @param array<string, int> $userInfo
     * @return list<array<string, int|null>>
     */
    private static function getPriceInfo(array $userInfo, int $now): array
    {
        $cdArr = [];
        $openArr = [];
        for ($i = 1; $i <= 2; $i++) {
            $cdArr[$i] = $userInfo['normal_time' . $i] - $now <= 0 ? 0 : $userInfo['normal_time' . $i];
            $openArr[$i] = $userInfo['free_times' . $i];
        }
        // 当前过人ID（全部突破后按第 1 名守将的价格）
        $id = $userInfo['pass_id'];
        $reward = $id > PassGeneral::lastId() ? PassgeneralInfo::ROWS[1] : (PassgeneralInfo::ROWS[$id] ?? []);
        $return = [];
        for ($i = 0; $i <= 1; $i++) {
            $return[$i]['id'] = $i + 1;
            if ($cdArr[$i + 1] > 0) {
                $return[$i]['cd'] = $cdArr[$i + 1] - $now;
                $return[$i]['price'] = $reward['price' . ($i + 1)] ?? null;
            } elseif ($openArr[$i + 1] > 0) {
                $return[$i]['cd'] = 0;
                $return[$i]['price'] = 0;
            } else {
                $return[$i]['cd'] = $cdArr[$i + 1] - $now;
                $return[$i]['price'] = $reward['price' . ($i + 1)] ?? null;
            }
            $return[$i]['times'] = $openArr[$i + 1];
        }
        return $return;
    }

    /**
     * 是否有可领取的成就（get_achieve_status：1 有、0 没有）
     *
     * @param array<string, int> $userInfo
     */
    private static function getAchieveStatus(array $userInfo, int $version): int
    {
        $logged = self::achievementLogs($userInfo['uid'], $version);
        foreach (PassgeneralAchievement::ROWS as $value) {
            if ($userInfo['succ_num'] >= $value['num'] && !isset($logged[$value['id']])) {
                return 1;
            }
        }
        return 0;
    }

    /**
     * 获取宝箱状态（get_box_reward_status：1 未达成、2 可领取、3 已领取）
     *
     * @param array<string, int> $userInfo
     * @return list<array{reward_status: int, box_id: int}>
     */
    private static function getBoxRewardStatus(array $userInfo): array
    {
        $num = $userInfo['num'] + 1;
        $rewardInfo = [];
        for ($i = 1; $i <= PassGeneral::maxNum(); $i++) {
            if ($num > $i) {
                $status = 3;
            } elseif ($num < $i) {
                $status = 1;
            } else {
                $status = $userInfo['is_get'];
            }
            $rewardInfo[] = ['reward_status' => $status, 'box_id' => $i];
        }
        return $rewardInfo;
    }

    /**
     * 本版本已领取的成就（get_passgeral_achievement_log 逐条查询的批量版）
     *
     * @return array<int, true> achieve_id => true
     */
    private static function achievementLogs(int $uid, int $version): array
    {
        $return = [];
        $rows = DB::table('passgeneral_achievement_log')->select('achieve_id')
            ->where('version', $version)->where('uid', $uid)->whereNull('deleted_at')->get();
        foreach ($rows as $row) {
            $return[(int)$row['achieve_id']] = true;
        }
        return $return;
    }

    /**
     * 每层最后一个过人 id（get_passgeneral_last_by_num）
     */
    private static function lastByNum(int $num): int
    {
        $passId = 0;
        foreach (PassgeneralInfo::ROWS as $row) {
            if ($row['num'] === $num && $row['id'] > $passId) {
                $passId = $row['id'];
            }
        }
        return $passId;
    }

    /**
     * 每层第一个过人 id（get_passgeneral_first_by_num）
     */
    private static function firstByNum(int $num): int
    {
        $passId = 0;
        foreach (PassgeneralInfo::ROWS as $row) {
            if ($row['num'] === $num && ($passId === 0 || $row['id'] < $passId)) {
                $passId = $row['id'];
            }
        }
        return $passId;
    }

    /**
     * 防刷锁（add_lock / get_lock：旧版先查后设；这里 SET NX 原子加锁，30 秒过期）
     */
    private static function addLock(string $key, int $uid): bool
    {
        return (bool)Redis::instance()->set($key . $uid, 1, ['nx', 'ex' => self::LOCK_TTL]);
    }

    private static function unlock(string $key, int $uid): void
    {
        Redis::del($key . $uid);
    }

    private static function memberValue(int $uid, string $column): int
    {
        return (int)(DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value($column) ?? 0);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function member(int $uid): ?array
    {
        if ($uid <= 0) {
            return null;
        }
        return DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
    }
}
