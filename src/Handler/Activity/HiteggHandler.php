<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\General\TavernHandler;
use Hanjiang\MasterData\General as GeneralTemplate;
use Hanjiang\MasterData\HitEggPrice;
use Hanjiang\MasterData\HitEggReward;
use Hanjiang\MasterData\Pub;
use Hanjiang\MasterData\Skill;
use Hanjiang\MasterData\Talent;
use Hanjiang\Model\Chat;
use Hanjiang\Model\FreeLeap;
use Hanjiang\Model\General;
use Hanjiang\Model\Member;
use Hanjiang\Model\PubLog;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 砸金蛋（移植旧版 controllers/hitegg.php + models/hitegg_model.php）
 *
 * 客户端（eggpop.js）调用：index / hit_egg / get_hitegg_reward；主城 member.index 的 hitegg（有可免费砸的蛋时为 1）取 init()。
 * 主公 20 级开放。金蛋价格取 MasterData HitEggPrice，奖励取 HitEggReward（按 rate 权重抽；type 1 ~ 3 蓝 / 黄 / 紫宝石、
 * 4 突飞卡、5 武将，已拥有的武将不抽，金蛋 2 / 3 的武将 VIP 1 以上才可能抽中）。每个金蛋有免费倒计时（hit_egg 表）：
 * 注册后第一次免费分别在 5 / 5 / 60 分钟后，之后每 1 / 2 / 3 天一次；免费可砸时不扣元宝并重置倒计时。金蛋 2 / 3 砸出 7 星
 * 以上武将发小喇叭（general_get_by_hitegg），金蛋 2 / 3 砸出武将发聊天室系统消息（get_message_str 22）。
 *
 * 照旧版保留的写法：hit_egg 用 hitegg_cd($id) 判断免费，只看前 $id 个蛋，新老用户标识取第 $id 个蛋；免费砸蛋时
 * 新用户还会把 egg{id}_f_time 改成当前时间（此后按老用户每 N 天一次）；get_hitegg_reward 非武将奖励每种 type 只留第一条，
 * 按 price（武将取酒馆价格，其他为 value + 100000，突飞卡为 type + 120000）降序。
 * 与旧版的差别：hit_egg 先用 Redis 锁 hitege_lock_{uid}（同旧版：锁已存在时删锁回 -8）挡并发，再在 Member::locked
 * （玩家行锁）里执行；奖励全部被过滤掉（总权重为 0）时回 -5（旧版 mt_rand(1, 0) 后取 $list[0] 发空奖励）。
 */
final class HiteggHandler
{
    /** 开启等级 */
    private const int OPEN_LEVEL = 20;
    /** 初始化砸蛋倒计时（分钟） */
    private const array FIRST_TIME = [5, 5, 60];
    /** 正常砸蛋倒计时（分钟） */
    private const array NORMAL_TIME = [1440, 2880, 4320];
    /** 砸蛋加锁 key */
    private const string LOCK_KEY = 'hitege_lock_';
    /** 旧版语言包 general_get_by_hitegg */
    private const string LANG_GENERAL_GET = '恭喜玩家【%s】在砸金蛋活动中砸到了%s星武将-%s';

    /**
     * 初始界面: c=hitegg&m=index
     */
    public static function index(Context $ctx): array
    {
        /**
         * -1 功能未开启
         * -2 获取金蛋信息失败
         *  0 请求失败
         *  1 请求成功
         */
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null || (int)$member['level'] < self::OPEN_LEVEL) {
            $return['status'] = -1;
            return $return;
        }
        // 获取金蛋列表
        $list = array_values(HitEggPrice::ROWS);
        // 获取玩家砸蛋CD和是否可以免费砸蛋标识
        $info = self::cd((int)$member['uid'], (int)$member['jointime'], time());
        foreach ($list as $key => $val) {
            $list[$key]['cd'] = $info['cdArr'][$val['id']] ?? 0;
            $list[$key]['open'] = in_array($val['id'], $info['openArr'], true) ? 1 : 0;
        }
        $return['status'] = 1;
        $return['list'] = $list;
        return $return;
    }

    /**
     * 砸蛋: c=hitegg&m=hit_egg&id=
     */
    public static function hit_egg(Context $ctx): array
    {
        /**
         * -1 功能未开启
         * -2 参数错误
         * -3 获取金蛋信息失败
         * -4 元宝不足
         * -5 获取奖励信息失败
         * -6 将领已存在
         * -7 角色身份不合法
         * -8 砸蛋不要太快了
         *  0 请求失败
         *  1 请求成功
         */
        $uid = MemberHandler::resolveUid($ctx);
        $id = (int)$ctx->input('id', 0);
        // 防止重复请求，刷接口
        $lockKey = self::LOCK_KEY . $uid;
        if (!Redis::instance()->set($lockKey, '1', ['nx', 'ex' => 30])) {
            Redis::del($lockKey);
            return ['status' => -8];
        }
        try {
            return Member::locked($uid, static fn (): array => self::hit($uid, $id, time()));
        } finally {
            // 解锁
            Redis::del($lockKey);
        }
    }

    /**
     * 获得奖励信息: c=hitegg&m=get_hitegg_reward&id=
     */
    public static function get_hitegg_reward(Context $ctx): array
    {
        /**
         * -1 功能未开启
         * -2 参数错误
         * -3 获取金蛋奖励信息失败
         * -4 获取武将信息失败
         *  0 请求失败
         *  1 请求成功
         */
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null || (int)$member['level'] < self::OPEN_LEVEL) {
            $return['status'] = -1;
            return $return;
        }
        $id = (int)$ctx->input('id', 0);
        if ($id <= 0) {
            $return['status'] = -2;
            return $return;
        }
        $reward = self::rewardList($id);
        if ($reward === []) {
            $return['status'] = -3;
            return $return;
        }
        $list = [];
        $gids = [];
        foreach ($reward as $value) {
            $list[] = ['type' => $value['type'], 'value' => $value['value'], 'name' => $value['name']];
            if ($value['type'] === 5) {
                $gids[] = $value['value'];
            }
        }
        // 获取奖励武将信息与酒馆售价
        if ($gids !== []) {
            $found = array_filter($gids, static fn (int $gid): bool => isset(GeneralTemplate::ROWS[$gid]));
            $prices = array_filter($gids, static fn (int $gid): bool => isset(Pub::ROWS[$gid]));
            if ($found === [] || $prices === []) {
                $return['status'] = -4;
                return $return;
            }
        }
        // 重置砸蛋奖励信息
        $itemType = [];
        foreach ($list as $key => $val) {
            if ($val['type'] === 5) {
                $general = GeneralTemplate::ROWS[$val['value']] ?? null;
                $list[$key]['general'] = $general;
                $list[$key]['price'] = (int)(Pub::ROWS[$general['id'] ?? 0]['price'] ?? 0);
                if ($general !== null) {
                    // 查询天赋 / 技能
                    $talent = Talent::ROWS[$general['talentid']] ?? null;
                    $list[$key]['general']['talent'] = $talent['name'] ?? null;
                    $list[$key]['general']['talent_desc'] = $talent['description'] ?? null;
                    $skill = Skill::ROWS[$general['skillid']] ?? null;
                    $list[$key]['general']['skill'] = $skill['name'] ?? null;
                    $list[$key]['general']['skill_desc'] = $skill['description'] ?? null;
                }
            } else {
                $list[$key]['price'] = $val['type'] === 4 ? $val['type'] + 120000 : $val['value'] + 100000;
                if (!in_array($val['type'], $itemType, true)) {
                    $itemType[] = $val['type'];
                } else {
                    unset($list[$key]);
                }
            }
        }
        // 按价格降序
        $sorted = array_map(static fn (array $v): int => $v['price'], $list);
        arsort($sorted);
        $return['list'] = [];
        foreach (array_keys($sorted) as $k) {
            $return['list'][] = $list[$k];
        }
        $return['status'] = 1;
        return $return;
    }

    /**
     * 主城砸蛋提示（member.php:_hitegg_init：三个蛋中有可免费砸的为 1）
     */
    public static function init(int $uid, int $jointime, int $now): int
    {
        return count(self::cd($uid, $jointime, $now)['openArr']) > 0 ? 1 : 0;
    }

    /**
     * 砸蛋（在玩家行锁里）
     *
     * @return array<string, mixed>
     */
    private static function hit(int $uid, int $id, int $now): array
    {
        $return = ['status' => 0];
        $member = self::member($uid);
        if ($member === null || (int)$member['level'] < self::OPEN_LEVEL) {
            // 判断功能是否开启
            $return['status'] = -1;
            return $return;
        }
        if ((int)$member['gender'] === 0) {
            // 防止玩家刷接口，没有角色不让砸蛋
            $return['status'] = -7;
            return $return;
        }
        if ($id <= 0) {
            $return['status'] = -2;
            return $return;
        }
        $info = HitEggPrice::ROWS[$id] ?? null;
        if ($info === null) {
            $return['status'] = -3;
            return $return;
        }
        $list = self::rewardList($id);
        if ($list === []) {
            $return['status'] = -5;
            return $return;
        }
        // 获取砸蛋时间记录信息
        $cd = self::cd($uid, (int)$member['jointime'], $now, $id);
        $isOld = $cd['isOld'];
        $isSubGold = in_array($id, $cd['openArr'], true) ? 1 : 0;
        // 判断元宝是否足够
        if ($isSubGold <= 0 && (int)$member['gold'] < $info['price']) {
            $return['status'] = -4;
            return $return;
        }
        // 筛选出将领：已拥有的不抽；金蛋 2 / 3 VIP 1 以下不抽将领
        foreach ($list as $key => $val) {
            if ($val['type'] !== 5) {
                continue;
            }
            if (($id === 2 || $id === 3) && (int)$member['vip'] < 1) {
                unset($list[$key]);
            } elseif (General::ownsTemplate($uid, $val['value'])) {
                unset($list[$key]);
            }
        }
        // 随机出奖励id
        $allcursor = 0;
        foreach ($list as $val) {
            $allcursor += (int)round($val['rate'] * 1000);
        }
        if ($allcursor <= 0) {
            $return['status'] = -5;
            return $return;
        }
        $randnumber = mt_rand(1, $allcursor);
        $cursor = 0;
        $rewardRow = reset($list);
        foreach ($list as $val) {
            $cursor += (int)round($val['rate'] * 1000);
            if ($cursor >= $randnumber) {
                $rewardRow = $val;
                break;
            }
        }
        $reward = ['type' => $rewardRow['type'], 'value' => $rewardRow['value'], 'quality' => $rewardRow['quality']];
        // 扣除元宝
        if ($isSubGold <= 0) {
            Member::deductCurrency($uid, gold: $info['price']);
            $return['all_gold'] = (int)DB::table('member')->where('uid', $uid)->value('gold');
        }
        // 添加奖励
        switch ($rewardRow['type']) {
            case 1:
            case 2:
            case 3:
                // 蓝 / 金 / 紫宝石
                TavernHandler::addJade($uid, $rewardRow['type'], $rewardRow['value'], 'hitegg', 'hit_egg');
                break;
            case 4:
                // 蓝色突飞卡
                FreeLeap::add($uid, $rewardRow['value'], $now);
                break;
            case 5:
                // 将领
                $addstatus = General::addGeneral($uid, $rewardRow['value']);
                if ($addstatus === null) {
                    // 将领已存在（旧版元宝已扣、不再返还）
                    $return['status'] = -6;
                    return $return;
                }
                // 添加酒馆日志(武将id, 拥有状态, 获取方式)
                PubLog::add($uid, $rewardRow['value'], 1, 8, $now);
                $reward['value'] = $addstatus;
                // 不是砸的第一个金蛋，就发送广播
                if ($id > 1) {
                    $sender = Chat::sender($uid);
                    $star = Chat::starWord((int)$addstatus['star']);
                    if ($star !== null) {
                        Chat::sendToHorn(sprintf(self::LANG_GENERAL_GET, $member['nickname'], $star, $addstatus['name']), $sender);
                    }
                    // 发送到聊天室
                    Chat::sendToAll(Chat::getMessageStr(22, ['general' => $addstatus['name']], $sender), Chat::TYPE_SYSTEM, 0, 0, $sender);
                }
                break;
        }
        // 添加砸蛋记录
        DB::table('hit_egg_log')->insert(['uid' => $uid, 'eggid' => $id, 'rewardid' => $rewardRow['id'], 'dateline' => $now]);
        // 更新砸蛋时间记录信息
        if ($isSubGold >= 1) {
            $data = ['egg' . $id . '_time' => $now];
            if ($isOld !== 1) {
                $data['egg' . $id . '_f_time'] = $now;
            }
            DB::table('hit_egg')->where('uid', $uid)->whereNull('deleted_at')->update($data);
        }
        // 记录活动人次
        DB::table('hitegg_action_log')->insert([
            'uid' => $uid,
            'gold' => $isSubGold <= 0 ? $info['price'] : 0,
            'level' => (int)$member['level'],
            'dateline' => $now,
        ]);
        Redis::del('player:' . $uid);
        $return['status'] = 1;
        $return['reward'] = $reward;
        $return['cd'] = self::NORMAL_TIME[$id - 1] * 60;
        return $return;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function member(int $uid): ?array
    {
        if ($uid <= 0) {
            return null;
        }
        return DB::table('member')->select('uid', 'nickname', 'level', 'gender', 'vip', 'gold', 'jointime')
            ->where('uid', $uid)->whereNull('deleted_at')->first();
    }

    /**
     * 按金蛋 id 获取奖励列表（get_reward_list）
     *
     * @return array<int, array<string, mixed>>
     */
    private static function rewardList(int $id): array
    {
        return array_values(array_filter(HitEggReward::ROWS, static fn (array $row): bool => $row['eggid'] === $id));
    }

    /**
     * 砸蛋 CD 和是否可以免费砸蛋（hitegg_cd）：注册时间 >= 第一次开始时间的蛋按新用户（注册后 5 / 5 / 60 分钟），
     * 否则按上次砸蛋时间 + 1 / 2 / 3 天；isOld 取最后一个蛋的判断
     *
     * @return array{isOld: int, cdArr: array<int, int>, openArr: list<int>}
     */
    private static function cd(int $uid, int $jointime, int $now, int $num = 3): array
    {
        $info = self::hiteggInfo($uid, $jointime, $now);
        $openArr = [];
        $cdArr = [];
        $isOld = 0;
        for ($i = 1; $i <= $num; $i++) {
            if ($jointime >= $info['egg' . $i . '_f_time']) {
                // 用户第一次砸蛋
                $isOld = 0;
                $left = $now - ($jointime + self::FIRST_TIME[$i - 1] * 60);
            } else {
                $isOld = 1;
                $left = $now - ($info['egg' . $i . '_time'] + self::NORMAL_TIME[$i - 1] * 60);
            }
            if ($left >= 0) {
                $cdArr[$i] = 0;
                // 可以免费砸蛋
                $openArr[] = $i;
            } else {
                $cdArr[$i] = -$left;
            }
        }
        return ['isOld' => $isOld, 'cdArr' => $cdArr, 'openArr' => $openArr];
    }

    /**
     * 玩家砸蛋时间记录（get_hitegg_info：没有时建档，开始时间为当前、第一次开始时间为注册时间）
     *
     * @return array<string, int>
     */
    private static function hiteggInfo(int $uid, int $jointime, int $now): array
    {
        $columns = ['egg1_time', 'egg2_time', 'egg3_time', 'egg1_f_time', 'egg2_f_time', 'egg3_f_time'];
        $row = DB::table('hit_egg')->select(...$columns)->where('uid', $uid)->whereNull('deleted_at')->first();
        if ($row !== null) {
            return array_map('intval', $row);
        }
        $data = [
            'egg1_time' => $now, 'egg2_time' => $now, 'egg3_time' => $now,
            'egg1_f_time' => $jointime, 'egg2_f_time' => $jointime, 'egg3_f_time' => $jointime,
        ];
        DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_hit_egg` (`uid`, `' . implode('`, `', $columns) . '`) VALUES (:uid, :' . implode(', :', $columns) . ')',
            ['uid' => $uid] + $data
        );
        $row = DB::table('hit_egg')->select(...$columns)->where('uid', $uid)->whereNull('deleted_at')->first();
        return $row === null ? $data : array_map('intval', $row);
    }
}
