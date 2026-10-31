<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\MasterData\Smash;
use Hanjiang\Model\Member;
use Hanjiang\Model\MemberActivity;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 威震天下（移植旧版 controllers/smash.php + models/smash_model.php）
 *
 * 客户端（smashpop.js）调用：index / action。主公 150 级开放，每天一次：花 MasterData Smash 的元宝（sub）得声望（get）；
 * VIP 12 有 20% 概率声望 ×2，VIP 13 有 30% ×2、5% ×4（up_rate）。上次参加时间记 member_activity.smashtime。
 *
 * 照旧版保留的写法：VIP 14 以上不在暴击表里，倍率为 0（不暴击）；action 没有 -2 以外的参数校验。
 * 与旧版的差别：action 在 Member::locked（玩家行锁）里执行。
 */
final class SmashHandler
{
    /** 功能开启等级 */
    private const int OPEN_LEVEL = 150;
    /** VIP => [倍率 => 概率]（up_rate） */
    private const array RATE = [
        12 => [2 => 20, 4 => 0],
        13 => [2 => 30, 4 => 5],
    ];

    /**
     * 初始化，获得威震天下列表: c=smash&m=index
     */
    public static function index(Context $ctx): array
    {
        $return = ['status' => 0, 'isuse' => 0, 'list' => []];
        $uid = MemberHandler::resolveUid($ctx);
        $level = (int)(DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('level') ?? 0);
        if ($level < self::OPEN_LEVEL) {
            // 判断功能是否开启
            $return['status'] = -1;
            return $return;
        }
        // 获得自己上次参加时间
        $smashtime = self::smashtime($uid);
        if ($smashtime === null) {
            $return['status'] = -2;
            return $return;
        }
        // 判断自己今日是否已经参加过了
        if (date('ymd') === date('ymd', $smashtime)) {
            $return['isuse'] = 1;
        }
        $return['status'] = 1;
        $return['list'] = array_values(Smash::ROWS);
        return $return;
    }

    /**
     * 开始任务: c=smash&m=action&id=
     */
    public static function action(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $id = (int)$ctx->input('id', 0);
        return Member::locked($uid, static function () use ($uid, $id): array {
            $return = [];
            $member = DB::table('member')->select('level', 'vip', 'gold')->where('uid', $uid)->whereNull('deleted_at')->first();
            if ($member === null || (int)$member['level'] < self::OPEN_LEVEL) {
                // 判断功能是否开启
                $return['status'] = -1;
                return $return;
            }
            if (!$id) {
                // 判断id是否合法
                $return['status'] = -2;
                return $return;
            }
            $now = time();
            $smashtime = self::smashtime($uid);
            if ($smashtime === null) {
                $return['status'] = -3;
                return $return;
            }
            // 获得任务信息
            $info = Smash::ROWS[$id] ?? null;
            if ($info === null) {
                $return['status'] = -4;
                return $return;
            }
            // 判断今日是否已经执行过任务
            if (date('ymd', $now) === date('ymd', $smashtime)) {
                $return['status'] = -5;
                return $return;
            }
            // 判断元宝是否足够
            if ((int)$member['gold'] < $info['sub']) {
                $return['status'] = -6;
                return $return;
            }
            // 扣除元宝
            Member::deductCurrency($uid, gold: $info['sub']);
            // 开始
            MemberActivity::update($uid, ['smashtime' => $now]);
            $rate = self::upRate((int)$member['vip']);
            if ($rate > 0) {
                $info['get'] = $info['get'] * $rate;
            }
            // 添加声望
            $reputation = Member::addReputation($uid, $info['get']);
            if ($reputation < 0) {
                $return['status'] = -7;
                return $return;
            }
            $return['status'] = 1;
            $return['rate'] = $rate;
            $return['add'] = $info['get'];
            $return['reputation'] = $reputation;
            $return['all_gold'] = (int)DB::table('member')->where('uid', $uid)->value('gold');
            return $return;
        });
    }

    /**
     * 上次参加时间（get_activity_info(0, ['smashtime'])：没有活动记录时建档）
     */
    private static function smashtime(int $uid): ?int
    {
        if ($uid <= 0) {
            return null;
        }
        MemberActivity::ensure($uid);
        $row = MemberActivity::many([$uid], ['smashtime'])[$uid] ?? null;
        return $row === null ? null : $row['smashtime'];
    }

    /**
     * 本次威震天下暴击倍率（up_rate：VIP 12 以下为 0）
     */
    private static function upRate(int $vip): int
    {
        if ($vip < 12 || !isset(self::RATE[$vip])) {
            return 0;
        }
        $rands = mt_rand(1, 100);
        $nowRate = 0;
        foreach (self::RATE[$vip] as $key => $val) {
            $nowRate += $val;
            if ($rands <= $nowRate) {
                return $key;
            }
        }
        return 0;
    }
}
