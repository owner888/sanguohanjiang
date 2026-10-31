<?php

declare(strict_types=1);

namespace Hanjiang\Handler\City;

use Hanjiang\Handler\General\TavernHandler;
use Hanjiang\MasterData\Equip;
use Hanjiang\MasterData\General as GeneralTemplate;
use Hanjiang\MasterData\LevelgiftReward;
use Hanjiang\MasterData\Skill;
use Hanjiang\MasterData\Soul;
use Hanjiang\MasterData\Talent;
use Hanjiang\Model\Equipment;
use Hanjiang\Model\FreeLeap;
use Hanjiang\Model\General;
use Hanjiang\Model\Member;
use Hanjiang\Model\MemberJade;
use Hanjiang\Model\OwnSoul;
use Hanjiang\Model\PubLog;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 等级礼包（移植旧版 controllers/levelgift.php + models/levelgift_model.php，主城标识 member_model->level_gift）
 *
 * 奖励在 MasterData LevelgiftReward（按主公等级）；领取记 levelgift_log（status 3）。奖励类型 1 银币、2 突飞卡、3 装备、4 军令、
 * 5 黄宝石、6 紫宝石、7 玉石、8 元宝、9 将魂（参数 2 为将魂等级）、10 武将（已拥有不发；武将 2 按 30 级发）。
 * 主城图标 member_field.levelgift（0 消失、1 显示、2 可领取）：主公升级时有未领的可领礼包置 2（levelGift），
 * 领奖后没有可领的置 1、全部领完置 0（第 25 级的记录不计）。
 * 旧版活动开启时间 opentime 为 -1（任何开服时间都满足），开服时间（uc_model->get_date_by_serverid）的判断不移植，status 3 不会出现。
 * 客户端 levelgiftpop.js 调用 index、get_reward（level）。
 *
 * 与旧版的差别：领奖锁改为 Redis SET NX；领取记录先以 (uid, level) 唯一键插入再发奖励（旧版先发后记，并发可重复领）。
 */
final class LevelgiftHandler
{
    /** 领奖锁（旧版 levelgift_lock_，60 秒） */
    private const string LOCK_KEY = 'levelgift_lock_';
    private const int LOCK_TTL = 60;

    /**
     * 等级礼包初始化: c=levelgift&m=index
     */
    public static function index(Context $ctx): array
    {
        $return = ['status' => 0, 'popo' => 1, 'lost' => 1];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        $uid = (int)$member['uid'];
        // 获取等级奖励信息
        $reward = array_values(LevelgiftReward::ROWS);
        if ($reward === []) {
            $return['status'] = -1;
            return $return;
        }
        [$reward, $pop] = self::withStatus($reward, self::claimedLevels($uid), (int)$member['level']);
        $return['popo'] = $pop;
        $return['list'] = self::decorate($uid, $reward);
        $return['status'] = 1;
        return $return + self::balances($uid);
    }

    /**
     * 领取等级大礼包奖励: c=levelgift&m=get_reward（level 礼包等级）
     */
    public static function get_reward(Context $ctx): array
    {
        /**
         * -7 操作太快
         * -2 已领取
         * -1 等级不足 / 参数错误 / 奖励不存在
         *  1 成功
         */
        $return = ['status' => 0, 'popo' => 1, 'lost' => 1];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        $uid = (int)$member['uid'];
        // 增加锁标志
        $redis = Redis::instance();
        if ($redis === null || !(bool)$redis->set(self::LOCK_KEY . $uid, '1', ['nx', 'ex' => self::LOCK_TTL])) {
            $return['status'] = -7;
            return $return;
        }
        try {
            return self::getReward($member, (int)$ctx->input('level', 0), $return);
        } finally {
            // 解锁
            $redis->del(self::LOCK_KEY . $uid);
        }
    }

    /**
     * 主公升级时刷新主城等级礼包标识（member_model->level_gift：有未领取且等级已达到的礼包时 levelgift = 2）
     */
    public static function levelGift(int $uid, int $level): void
    {
        $levels = self::claimedLevels($uid);
        $pop = 0;
        foreach (LevelgiftReward::ROWS as $val) {
            if (!in_array((int)$val['level'], $levels, true) && $level >= (int)$val['level']) {
                $pop = 1;
            }
        }
        if ($pop >= 1) {
            DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->update(['levelgift' => 2]);
        }
    }

    /**
     * @param array<string, mixed> $member
     * @param array<string, mixed> $return
     * @return array<string, mixed>
     */
    private static function getReward(array $member, int $level, array $return): array
    {
        $uid = (int)$member['uid'];
        $now = time();
        if ($level === 0 || (int)$member['level'] < $level) {
            $return['status'] = -1;
            return $return;
        }
        // 判断本用户是否已将领取过奖励
        if (DB::table('levelgift_log')->where('uid', $uid)->where('level', $level)->whereNull('deleted_at')->value('id') !== null) {
            $return['status'] = -2;
            return $return;
        }
        // 通过level寻找信息
        $reward = LevelgiftReward::ROWS[$level] ?? null;
        if ($reward === null) {
            $return['status'] = -1;
            return $return;
        }
        // 更新到已领取状态（add_reward_log(level, 2)：status 3；唯一键保证只领一次）
        $inserted = DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_levelgift_log` (`uid`, `level`, `dateline`, `status`) VALUES (:uid, :level, :now, 3)',
            ['uid' => $uid, 'level' => $level, 'now' => $now]
        );
        if ($inserted === 0) {
            $return['status'] = -2;
            return $return;
        }
        // 添加奖励(1银币2突飞卡3装备4军令5黄宝石6紫宝石7水晶8元宝9将魂10武将)
        for ($i = 1; $i <= 4; $i++) {
            $value = (int)$reward['getvalue' . $i];
            switch ((int)$reward['gettype' . $i]) {
                case 1: // 银币
                    Member::addCurrency($uid, silver: $value);
                    break;
                case 2: // 突飞卡
                    FreeLeap::add($uid, $value, $now);
                    break;
                case 3: // 装备
                    if (isset(Equip::ROWS[$value])) {
                        Equipment::addFromTemplate($uid, $value);
                    }
                    break;
                case 4: // 军令
                    Member::addCurrency($uid, act: $value);
                    break;
                case 5: // 黄宝石
                    TavernHandler::addJade($uid, 2, $value);
                    break;
                case 6: // 紫宝石
                    TavernHandler::addJade($uid, 3, $value);
                    break;
                case 7: // 水晶(玉石)
                    MemberJade::add($uid, $value, now: $now);
                    break;
                case 8: // 元宝
                    Member::addCurrency($uid, gold: $value);
                    break;
                case 9: // 将魂
                    OwnSoul::addHeroSoul($uid, $value, (int)$reward['getvalue' . $i . '2']);
                    break;
                case 10: // 武将（已拥有不发）
                    if (DB::table('general')->where('uid', $uid)->where('generalid', $value)->whereNull('deleted_at')->value('id') === null) {
                        General::addGeneral($uid, $value, $value === 2 ? 2 : 1);
                        PubLog::add($uid, $value, 1, 14, $now);
                    }
                    break;
            }
        }

        // 获取用户log信息
        $levels = self::claimedLevels($uid);
        $list = array_values(LevelgiftReward::ROWS);
        [$list, $pop] = self::withStatus($list, $levels, (int)$member['level']);
        $return['popo'] = $pop;
        if ($pop !== 1) {
            DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->update(['levelgift' => 1]);
        }
        // 如果之前存在25级的礼包就删除掉
        $claimed = array_filter($levels, static fn (int $l): bool => $l !== 25);
        $return['lost'] = 1;
        if (count($claimed) === count($list)) {
            $return['lost'] = 0;
            DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->update(['levelgift' => 0]);
        }
        Redis::del('player:' . $uid);

        $return['status'] = 1;
        $return['list'] = self::decorate($uid, $list);
        return $return + self::balances($uid);
    }

    /**
     * 礼包状态（type：3 已领取、1 可领取、2 未达成）与是否有可领取的（popo）
     *
     * @param list<array<string, mixed>> $reward
     * @param list<int> $levels 已领取的等级
     * @return array{0: list<array<string, mixed>>, 1: int}
     */
    private static function withStatus(array $reward, array $levels, int $level): array
    {
        $pop = 0;
        foreach ($reward as $key => $val) {
            if (in_array((int)$val['level'], $levels, true)) {
                $reward[$key]['type'] = 3;
            } elseif ($level >= (int)$val['level']) {
                $reward[$key]['type'] = 1;
                $pop = 1;
            } else {
                $reward[$key]['type'] = 2;
            }
        }
        return [$reward, $pop];
    }

    /**
     * 合并装备 / 武将 / 将魂的展示信息（information / renew / soul）
     *
     * @param list<array<string, mixed>> $reward
     * @return list<array<string, mixed>>
     */
    private static function decorate(int $uid, array $reward): array
    {
        foreach ($reward as $key => $val) {
            for ($i = 1; $i <= 4; $i++) {
                $type = (int)$val['gettype' . $i];
                $value = (int)$val['getvalue' . $i];
                if ($type === 3) {
                    // 装备
                    $reward[$key]['information'][$i] = Equip::ROWS[$value] ?? [];
                }
                if ($type === 10) {
                    // 武将
                    $info = GeneralTemplate::ROWS[$value] ?? [];
                    $talent = Talent::ROWS[$info['talentid'] ?? 0] ?? [];
                    $skill = Skill::ROWS[$info['skillid'] ?? 0] ?? [];
                    $reward[$key]['information'] = $info;
                    $reward[$key]['information']['talent'] = $talent;
                    $reward[$key]['information']['skill'] = $skill;
                    $reward[$key]['information'][$i] = $info;
                    $reward[$key]['information'][$i]['id'] = $info['image'] ?? null;
                    $reward[$key]['information'][$i]['talent'] = $talent;
                    $reward[$key]['information'][$i]['skill'] = $skill;
                    // 判断不在酒馆里的武将信息（前三个礼包）：已拥有为折旧 1
                    if ($key === 0 || $key === 1 || $key === 2) {
                        $owned = DB::table('general')->where('uid', $uid)->where('generalid', $value)->whereNull('deleted_at')->value('id') !== null;
                        $reward[$key]['renew'] = $owned ? 1 : 0;
                    }
                }
                if ($type === 9) {
                    // 将魂
                    $soul = Soul::ROWS[$value] ?? null;
                    if ($soul !== null) {
                        $attribute = OwnSoul::levelupRule((int)$val['getvalue' . $i . '2'], (int)$soul['type'], (int)$soul['quality']);
                        $reward[$key]['soul'][$i] = $attribute['info'] ?? [];
                        $reward[$key]['soul'][$i]['name'] = $soul['name'];
                        $reward[$key]['soul'][$i]['quality'] = $soul['quality'];
                        $reward[$key]['soul'][$i]['type'] = $soul['type'];
                        $reward[$key]['soul'][$i]['image'] = $soul['type'] . $soul['quality'];
                        $reward[$key]['soul'][$i]['power'] = $attribute['info']['addition'] ?? null;
                    }
                }
            }
        }
        return $reward;
    }

    /**
     * 已领取的礼包等级（get_reward_log）
     *
     * @return list<int>
     */
    private static function claimedLevels(int $uid): array
    {
        $rows = DB::table('levelgift_log')->select('level')->where('uid', $uid)->whereNull('deleted_at')->orderBy('id')->get();
        return array_map(static fn (array $r): int => (int)$r['level'], $rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function member(int $uid): ?array
    {
        return $uid > 0 ? DB::table('member')->select('uid', 'level')->where('uid', $uid)->whereNull('deleted_at')->first() : null;
    }

    /**
     * @return array{all_silver: int, all_gold: int, all_act: int}
     */
    private static function balances(int $uid): array
    {
        $member = DB::table('member')->select('silver', 'gold', 'act')->where('uid', $uid)->whereNull('deleted_at')->first() ?? [];
        return [
            'all_silver' => (int)($member['silver'] ?? 0),
            'all_gold' => (int)($member['gold'] ?? 0),
            'all_act' => (int)($member['act'] ?? 0),
        ];
    }
}
