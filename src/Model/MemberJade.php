<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use OmniPHP\Database\DB;

/**
 * 玉石（移植旧版 models/member_model.php 的 add_jade / sub_jade / _jade_log）
 *
 * 存储同旧版：member.jade（玉石个数）；每次增减在 jade_log 记一条（type 1 获得 / 2 花费，jade 为操作数，
 * balance 为变动后的余额，level / vip 为当时的玩家等级与 VIP）。与酒馆的蓝 / 黄 / 紫宝石（trader_log，见 TraderLog）
 * 是两种东西：国家矿区、国家俸禄、国战、群雄逐鹿、英雄王座、过关斩将等发的「玉石」都是这里。
 *
 * 增减都是单条原子 UPDATE（Member::incrementColumn / decrementColumn），多 Worker 并发不会超扣、余额不丢。
 * 与旧版的差别：数量为 0 时不写库不记流水，直接成功（旧版 add_jade(0) 照样记一条流水）；不移植 sub_jade 的 $debug
 * （旧版调试用，允许扣成负数）；流水只在写库成功时记（旧版玩家存在时不论成败都记）。
 */
final class MemberJade
{
    /**
     * 当前玉石数；玩家不存在为 0
     */
    public static function get(int $uid): int
    {
        return (int)(DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('jade') ?? 0);
    }

    /**
     * 增加玉石（移植 add_jade）
     *
     * @return array{status: int, jade: int} status 1 成功，-1 玩家不存在；jade 为增加后的余额
     */
    public static function add(int $uid, int $jade, string $class = '', string $method = '', ?int $now = null): array
    {
        if ($jade <= 0) {
            return self::noop($uid, -1);
        }
        $balance = Member::incrementColumn($uid, 'jade', $jade);
        if ($balance === null) {
            return ['status' => -1, 'jade' => 0];
        }
        self::log($uid, 1, $jade, $balance, $class, $method, $now);
        return ['status' => 1, 'jade' => $balance];
    }

    /**
     * 消耗玉石（移植 sub_jade）
     *
     * @return array{status: int, jade: int} status 1 成功，-1 玉石不足，-2 玩家不存在；jade 为扣除后的余额（失败为当前余额）
     */
    public static function sub(int $uid, int $jade, string $class = '', string $method = '', ?int $now = null): array
    {
        if ($jade <= 0) {
            return self::noop($uid, -2);
        }
        $balance = Member::decrementColumn($uid, 'jade', $jade);
        if ($balance === null) {
            return Member::logInfo($uid) === null
                ? ['status' => -2, 'jade' => 0]
                : ['status' => -1, 'jade' => self::get($uid)];
        }
        self::log($uid, 2, $jade, $balance, $class, $method, $now);
        return ['status' => 1, 'jade' => $balance];
    }

    /**
     * @return array{status: int, jade: int}
     */
    private static function noop(int $uid, int $missing): array
    {
        return Member::logInfo($uid) === null ? ['status' => $missing, 'jade' => 0] : ['status' => 1, 'jade' => self::get($uid)];
    }

    /**
     * jade_log 一条（移植 _jade_log；argument 旧版取未定义的 $this->action，恒为空串）
     */
    private static function log(int $uid, int $type, int $jade, int $balance, string $class, string $method, ?int $now): void
    {
        $info = Member::logInfo($uid) ?? ['level' => 0, 'vip' => 0];
        DB::table('jade_log')->insert([
            'uid' => $uid,
            'level' => $info['level'],
            'type' => $type,
            'jade' => $jade,
            'balance' => max(0, $balance),
            'vip' => $info['vip'],
            'class' => Member::logText($class, 20),
            'method' => Member::logText($method, 20),
            'argument' => '',
            'dateline' => $now ?? time(),
        ]);
    }
}
