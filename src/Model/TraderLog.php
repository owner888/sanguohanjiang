<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use OmniPHP\Database\DB;

/**
 * 酒馆宝石与商贾会客位置（移植旧版 models/tavern_model.php 的 add_jade / sub_jade / _tavernjade_log）
 *
 * 存储同旧版：trader_log 每个玩家一行（唯一键 uid）：get1 / get2 / get3 为蓝 / 黄 / 紫宝石数量，site 为当前商人位置，
 * site1 ~ site5 为各位置开启状态，dateline 为最后（免费）交易时间（当天交易过即不再免费）。
 * 每次宝石增减在 tavernjade_log 记一条（jadetype 宝石类型，type 1 获得 / 2 花费，balance 为该类宝石变动后的数量）。
 * 酒馆招募名将、过关斩将 / 首充 / 演武榜 / 国家 BOSS 等奖励发的「蓝 / 黄 / 紫宝石」都是这里，与 member.jade 玉石（MemberJade）不同。
 *
 * 并发：add 在 Member::locked 的玩家行锁下读改写（首行插入与位置更新要一起做）；sub 是单条条件 UPDATE。
 * 与旧版的差别：数量 <= 0 或类型不在 1 ~ 3 时不写库（旧版类型不对会拼出不存在的列）。
 */
final class TraderLog
{
    /**
     * 玩家的会客记录；没有记录返回 null
     *
     * @return array<string, int>|null
     */
    public static function get(int $uid): ?array
    {
        $row = DB::table('trader_log')
            ->select('uid', 'get1', 'get2', 'get3', 'site', 'site1', 'site2', 'site3', 'site4', 'site5', 'dateline')
            ->where('uid', $uid)->whereNull('deleted_at')->first();
        return $row === null ? null : array_map('intval', $row);
    }

    /**
     * 某类宝石的数量；没有记录为 0
     *
     * @param int $type 1 蓝宝石、2 黄宝石、3 紫宝石
     */
    public static function jade(int $uid, int $type): int
    {
        return $type >= 1 && $type <= 3 ? (int)(self::get($uid)['get' . $type] ?? 0) : 0;
    }

    /**
     * 获得宝石并修改会客位置（移植 add_jade；$site 传 0 只加宝石不动位置，奖励发放都是这种用法）
     *
     * 已有记录：get{type} 累加；$site 非 0 时 site 改为 $site 并开启 site{$site}，$free 为 1 时 dateline 记为当前时间，
     * 返回里带 site1 ~ site5；$toSite > 1 且不等于 $site 时关闭 site{$toSite}。
     * 没有记录：插入一行（site1 开启，dateline 为当前时间；$site 为 0 时 site 记 1，$site 不为 1 时开启 site{$site}）。
     *
     * @param int $type 1 蓝宝石、2 黄宝石、3 紫宝石
     * @return array<string, int> status 1 成功 / -1 失败 / 0 参数不对，num 为该类宝石增加后的数量，type 为宝石类型；
     *                            另按旧版带 site1 ~ site5（见上）
     */
    public static function addJade(
        int $uid,
        int $type,
        int $num,
        int $site = 0,
        int $toSite = 1,
        int $free = 0,
        string $class = '',
        string $method = '',
        ?int $now = null,
    ): array {
        if ($type < 1 || $type > 3 || $num <= 0) {
            return ['status' => 0, 'num' => 0, 'type' => 0];
        }
        $now ??= time();
        return Member::locked($uid, static function () use ($uid, $type, $num, $site, $toSite, $free, $class, $method, $now): array {
            $return = ['status' => 0, 'num' => 0, 'type' => 0];
            $rows = DB::query(
                'SELECT * FROM `zc_sanguo_trader_log` WHERE `uid` = :uid AND `deleted_at` IS NULL FOR UPDATE',
                ['uid' => $uid]
            );
            $field = 'get' . $type;
            if ($rows !== []) {
                $result = $rows[0];
                $data = [$field => (int)$result[$field] + $num];
                if ($site !== 0) {
                    // 返回已打开的位置
                    for ($i = 1; $i <= 5; $i++) {
                        $return['site' . $i] = (int)$result['site' . $i];
                    }
                    // 当前位置
                    if ($free !== 0) {
                        $data['dateline'] = $now;
                    }
                    $data['site'] = $site;
                    $data['site' . $site] = 1;
                    $return['site' . $site] = 1;
                }
                if ($toSite > 1 && $toSite !== $site) {
                    // 传进来的位置关闭
                    $data['site' . $toSite] = 0;
                    $return['site' . $toSite] = 0;
                }
                $ok = DB::table('trader_log')->where('uid', $uid)->whereNull('deleted_at')->update($data) > 0;
            } else {
                $data = [
                    'uid' => $uid,
                    'site' => $site,
                    $field => $num,
                    'site1' => 1,
                    'dateline' => $now,
                ];
                for ($i = 1; $i <= 5; $i++) {
                    $return['site' . $i] = 0;
                }
                $return['site1'] = 1;
                if ($site === 0) {
                    $data['site'] = 1;
                } elseif ($site !== 1) {
                    $data['site' . $site] = 1;
                    $return['site' . $site] = 1;
                }
                $ok = (int)DB::table('trader_log')->insert($data) > 0;
            }

            if (!$ok) {
                $return['status'] = -1;
                return $return;
            }
            $return['status'] = 1;
            $return['num'] = $data[$field];
            $return['type'] = $type;
            self::log($uid, $type, 1, $num, $data[$field], $class, $method, $now);
            return $return;
        });
    }

    /**
     * 消耗宝石（移植 sub_jade）
     *
     * @param int $type 1 蓝宝石、2 黄宝石、3 紫宝石
     * @return array{status: int, type: int, number: int, jade?: int} status 1 成功，-1 没有记录或数量不足；
     *         number / jade 为该类宝石扣除后的数量
     */
    public static function subJade(int $uid, int $type, int $num, string $class = '', string $method = '', ?int $now = null): array
    {
        $return = ['status' => 0, 'type' => $type, 'number' => 0];
        if ($type < 1 || $type > 3 || $num < 0) {
            return $return;
        }
        $field = 'get' . $type;
        $affected = DB::execute(
            "UPDATE `zc_sanguo_trader_log` SET `{$field}` = LAST_INSERT_ID(`{$field}` - :num)
             WHERE `uid` = :uid AND `deleted_at` IS NULL AND `{$field}` >= :need",
            ['num' => $num, 'uid' => $uid, 'need' => $num]
        );
        if ($affected === 0) {
            $row = self::get($uid);
            if ($row === null || $row[$field] < $num) {
                // 没找到数据或宝石不足
                $return['status'] = -1;
                return $return;
            }
            // 扣 0 个：数据不变，照旧版算成功
            $balance = $row[$field];
        } else {
            $balance = (int)DB::query('SELECT LAST_INSERT_ID() AS `v`')[0]['v'];
        }
        $return['jade'] = $balance;
        $return['number'] = $balance;
        $return['type'] = $type;
        $return['status'] = 1;
        if ($num > 0) {
            self::log($uid, $type, 2, $num, $balance, $class, $method, $now ?? time());
        }
        return $return;
    }

    /**
     * tavernjade_log 一条（移植 _tavernjade_log；argument 恒为空串）
     */
    private static function log(int $uid, int $jadeType, int $type, int $num, int $balance, string $class, string $method, int $now): void
    {
        $info = Member::logInfo($uid) ?? ['level' => 0, 'vip' => 0];
        DB::table('tavernjade_log')->insert([
            'uid' => $uid,
            'level' => $info['level'],
            'vip' => $info['vip'],
            'type' => $type,
            'jadetype' => $jadeType,
            'jade' => $num,
            'balance' => $balance,
            'class' => Member::logText($class, 20),
            'method' => Member::logText($method, 20),
            'argument' => '',
            'dateline' => $now,
        ]);
    }
}
