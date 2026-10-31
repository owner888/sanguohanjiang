<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use Hanjiang\MasterData\ExpostulationTemp;
use OmniPHP\Database\DB;

/**
 * 国家谏言（移植旧版 models/expostulation_model.php）
 *
 * expostulation：每条谏言一行（cid 国家、eid 模版、type 任务类型、status 0 待审核 / 1 已通过 / 2 已完成 / 3 奖励已领完、plan 进度）；
 * expostulation_support：玩家支持过的谏言（status 0 未领取 / 1 已领取；发起者领奖时也补一行）；
 * member_country.eid / support / refresh：玩家可提出的 4 条谏言模版、当天首次支持时间、当天首次刷新时间。
 *
 * 任务计数 action：强化 1（strengthen）、武将升级 2（General::addExp）、培养 3（cultivate）、贸易 4（tavern）、
 * 征收 5（city）、通商 6（business）、征战主线 7（map）。
 *
 * 与旧版的差别：
 * - action 的进度用一条 UPDATE 原子累加并在同一条语句里判断完成（旧版先读后写，多进程并发会丢计数）；
 * - 支持记录加主键 (eid, uid)，重复支持只记一次（旧版先查再插，并发会插出两行）；
 * - 旧版 clear_sup_status / clear_expos 物理删除，这里软删除（软删除的支持记录再次支持时恢复为未领取）。
 */
final class Expostulation
{
    /** 谏言品级权重（get_quality_by_rate） */
    private const array QUALITY_RATE = [1 => 60, 2 => 20, 3 => 10, 4 => 5, 5 => 2];

    /** 谏言任务说明（country_lang.php country_expostulation_*） */
    public const array DESCRIPTION = [
        1 => '全国强化装备累计',
        2 => '全国武将升级',
        3 => '全国培养属性',
        4 => '全国贸易',
        5 => '全国征收',
        6 => '全国通商',
        7 => '全国征战主线',
    ];

    private const array FIELDS = ['id', 'cid', 'eid', 'type', 'uid', 'nickname', 'gender', 'job', 'status', 'plan', 'dateline'];

    /**
     * 谏言任务说明（控制器 get_description：类型不存在按 1）
     */
    public static function description(int $type): string
    {
        return self::DESCRIPTION[$type] ?? self::DESCRIPTION[1];
    }

    /**
     * 国家的谏言列表（get_list_by_pass）：$type 0 待审核、1 已通过、其他为已完成或已通过；按 status 降序
     *
     * @return list<array<string, mixed>>
     */
    public static function listByPass(int $cid, int $type = 0): array
    {
        $query = DB::table('expostulation')->select(...self::FIELDS)->where('cid', $cid)->whereNull('deleted_at');
        if ($type === 1) {
            $query->where('status', 1);
        } elseif ($type === 0) {
            $query->where('status', 0);
        } else {
            $query->whereIn('status', [1, 2]);
        }
        $rows = $query->orderBy('status', 'DESC')->orderBy('id')->get();
        return array_map(self::row(...), $rows);
    }

    /**
     * 按 id 取国家的一条谏言（get_info_by_id）；没有为 null
     *
     * @return array<string, mixed>|null
     */
    public static function infoById(int $cid, int $id): ?array
    {
        $row = DB::table('expostulation')->select(...self::FIELDS)->where('cid', $cid)->where('id', $id)->whereNull('deleted_at')->first();
        return $row === null ? null : self::row($row);
    }

    /**
     * 谏言模版（get_temp_by_id 单个）；没有为 null
     *
     * @return array<string, mixed>|null
     */
    public static function temp(int $eid): ?array
    {
        return ExpostulationTemp::ROWS[$eid] ?? null;
    }

    /**
     * 多个谏言模版（get_temp_by_id 数组：id => 模版，查不到的不在结果里）
     *
     * @param list<int> $eids
     * @return array<int, array<string, mixed>>
     */
    public static function temps(array $eids): array
    {
        $return = [];
        foreach ($eids as $eid) {
            if (isset(ExpostulationTemp::ROWS[(int)$eid])) {
                $return[(int)$eid] = ExpostulationTemp::ROWS[(int)$eid];
            }
        }
        return $return;
    }

    /**
     * 玩家对一条谏言的支持记录（get_support_by_id 单个）；没有为 null
     *
     * @return array{eid: int, uid: int, status: int}|null
     */
    public static function support(int $uid, int $eid): ?array
    {
        return self::supports($uid, [$eid])[$eid] ?? null;
    }

    /**
     * 玩家对多条谏言的支持记录（get_support_by_id 数组：谏言 id => 记录）
     *
     * @param list<int> $eids
     * @return array<int, array{eid: int, uid: int, status: int}>
     */
    public static function supports(int $uid, array $eids): array
    {
        if ($eids === []) {
            return [];
        }
        $rows = DB::table('expostulation_support')->select('eid', 'uid', 'status')
            ->where('uid', $uid)->whereIn('eid', $eids)->whereNull('deleted_at')->get();
        $return = [];
        foreach ($rows as $row) {
            $return[(int)$row['eid']] = array_map('intval', $row);
        }
        return $return;
    }

    /**
     * 修改谏言状态（update_status_by_id；$from 为改之前须处于的状态，并发采纳只成功一次）
     */
    public static function updateStatus(int $id, int $status, int $from): bool
    {
        return DB::table('expostulation')->where('id', $id)->where('status', $from)->whereNull('deleted_at')
            ->update(['status' => $status]) > 0;
    }

    /**
     * 随机取谏言模版并写入玩家的谏言列表（get_list_by_rand）
     *
     * 按品级权重抽 $num 个品级，每个品级里随机取一条：将魂奖励（get_type 2）要求主公 51 级、通商（type 6）要求 61 级，
     * 不满足或已在 $eidArr 里的重抽。$eidArr 不为空时把抽到的第一条替换到 $site 位置（提出谏言后补一条）。
     *
     * @param list<int|string> $eidArr
     * @return array{status: int, list: list<array<string, mixed>>}
     */
    public static function listByRand(int $uid, int $level, int $num, array $eidArr = [], int $site = 0): array
    {
        $quality = [];
        for ($i = 0; $i < $num; $i++) {
            $quality[] = self::qualityByRate();
        }
        $tempList = [];
        foreach (ExpostulationTemp::ROWS as $row) {
            if (in_array($row['quality'], $quality, true)) {
                $tempList[$row['quality']][$row['id']] = $row;
            }
        }
        if ($tempList === []) {
            return ['status' => -1, 'list' => []];
        }
        $eid = [];
        $list = [];
        for ($i = 0; $i < $num; $i++) {
            $type = isset($quality[$i]) ? $i : array_rand($quality);
            $t = $quality[$type];
            if (!isset($tempList[$t])) {
                // 旧版这里 array_rand(null) 报错后死循环；该品级已取空时换一个仍有模版的品级
                $t = array_key_first($tempList);
                if ($t === null) {
                    break;
                }
            }
            $j = array_rand($tempList[$t]);
            $info = $tempList[$t][$j];
            // 判断这条谏言符不符合用户等级
            if (($info['get_type'] === 2 && $level < 51)
                || ($info['type'] === 6 && $level < 61)
                || ($eidArr !== [] && in_array($j, array_map('intval', $eidArr), true))) {
                unset($tempList[$t][$j]);
                if ($tempList[$t] === []) {
                    unset($tempList[$t]);
                }
                $i -= 1;
                continue;
            }
            $eid[] = $info['id'];
            $list[$i] = $info;
            unset($tempList[$t][$j]);
            if ($tempList[$t] === []) {
                unset($tempList[$t]);
                $quality = array_flip($quality);
                unset($quality[$t]);
                $quality = array_flip($quality);
            }
        }

        // 把刷新出来的记录到数据库
        if ($eid !== []) {
            if ($eidArr !== []) {
                $eidArr[$site] = $eid[0];
                $eidStr = implode(',', $eidArr);
            } else {
                $eidStr = implode(',', $eid);
            }
            DB::table('member_country')->where('uid', $uid)->whereNull('deleted_at')->update(['eid' => $eidStr]);
        }
        return ['status' => 1, 'list' => array_values($list)];
    }

    /**
     * 添加谏言（add_expostulation）
     *
     * @param array{uid: int, nickname: string, gender: int, country: int} $member
     */
    public static function add(array $member, int $job, array $temp, int $now): bool
    {
        return (int)DB::table('expostulation')->insert([
            'cid' => $member['country'],
            'eid' => $temp['id'],
            'type' => $temp['type'],
            'uid' => $member['uid'],
            'nickname' => $member['nickname'],
            'gender' => $member['gender'],
            'job' => $job,
            'dateline' => $now,
        ]) > 0;
    }

    /**
     * 完成任务（action）：国家已通过的同类型谏言进度 + $times，达到模版 value 时改为已完成
     */
    public static function action(int $cid, int $type, int $times = 1): bool
    {
        if ($cid <= 0) {
            return true;
        }
        // 查询该类型的任务（旧版 row_array 取第一条）
        $result = DB::table('expostulation')->select('id', 'eid')->where('cid', $cid)->where('type', $type)->where('status', 1)
            ->whereNull('deleted_at')->orderBy('id')->first();
        if ($result === null) {
            return true;
        }
        $info = self::temp((int)$result['eid']);
        if ($info === null) {
            // 没有找到任务信息
            return false;
        }
        // MySQL 的 SET 按顺序求值：status 判断用的是累加后的 plan
        DB::execute(
            'UPDATE `zc_sanguo_expostulation` SET `plan` = `plan` + :times, `status` = IF(`plan` >= :value, 2, `status`)
             WHERE `cid` = :cid AND `id` = :id AND `status` = 1 AND `deleted_at` IS NULL',
            ['times' => $times, 'value' => (int)$info['value'], 'cid' => $cid, 'id' => (int)$result['id']]
        );
        return true;
    }

    /**
     * 修改奖励领取状态（update_get_status）；返回是否改到（并发重复领奖只成功一次）
     */
    public static function updateGetStatus(int $uid, int $eid): bool
    {
        return DB::table('expostulation_support')->where('eid', $eid)->where('uid', $uid)->where('status', 0)->whereNull('deleted_at')
            ->update(['status' => 1]) > 0;
    }

    /**
     * 发起者已领取且没有未领取的支持者时，谏言改为奖励已领完（get_reward_member）
     */
    public static function rewardMember(int $cid, int $eid, int $uid): void
    {
        $owner = DB::table('expostulation_support')->where('eid', $eid)->where('uid', $uid)->where('status', 1)->whereNull('deleted_at')->value('eid');
        if ($owner === null) {
            return;
        }
        $left = DB::table('expostulation_support')->where('eid', $eid)->where('status', 0)->whereNull('deleted_at')->value('eid');
        if ($left === null) {
            DB::table('expostulation')->where('cid', $cid)->where('id', $eid)->whereNull('deleted_at')->update(['status' => 3]);
        }
    }

    /**
     * 添加支持者（add_support_log）；软删除过的记录恢复为未领取；已有有效记录时返回 false
     */
    public static function addSupportLog(int $uid, int $eid): bool
    {
        $affected = DB::execute(
            'INSERT INTO `zc_sanguo_expostulation_support` (`eid`, `uid`, `status`) VALUES (:eid, :uid, 0)
             ON DUPLICATE KEY UPDATE `status` = IF(`deleted_at` IS NULL, `status`, 0), `deleted_at` = NULL',
            ['eid' => $eid, 'uid' => $uid]
        );
        return $affected > 0;
    }

    /**
     * 修改首次支持谏言时间（update_sup_time）
     */
    public static function updateSupTime(int $uid, int $now): void
    {
        DB::table('member_country')->where('uid', $uid)->whereNull('deleted_at')->update(['support' => $now]);
    }

    /**
     * 修改刷新谏言列表时间（update_refresh_dateline）
     */
    public static function updateRefreshDateline(int $uid, int $now): void
    {
        DB::table('member_country')->where('uid', $uid)->whereNull('deleted_at')->update(['refresh' => $now]);
    }

    /**
     * 按权重取品级（get_quality_by_rate）
     */
    public static function qualityByRate(): int
    {
        $rands = mt_rand(1, array_sum(self::QUALITY_RATE));
        $cursor = 0;
        foreach (self::QUALITY_RATE as $quality => $rate) {
            $cursor += $rate;
            if ($rands <= $cursor) {
                return $quality;
            }
        }
        return 1;
    }

    /**
     * 清除玩家未领取的支持记录（clear_sup_status，离开国家时）
     */
    public static function clearSupStatus(int $uid): int
    {
        if ($uid <= 0) {
            return 0;
        }
        DB::table('expostulation_support')->where('uid', $uid)->where('status', 0)->whereNull('deleted_at')
            ->update(['deleted_at' => date('Y-m-d H:i:s')]);
        return 1;
    }

    /**
     * 清除玩家未采纳的谏言（clear_expos，叛国 / 被踢出国家时）
     */
    public static function clearExpos(int $uid): void
    {
        DB::table('expostulation')->where('uid', $uid)->where('status', 0)->whereNull('deleted_at')
            ->update(['deleted_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function row(array $row): array
    {
        foreach ($row as $key => $value) {
            $row[$key] = $key === 'nickname' ? (string)$value : (int)$value;
        }
        return $row;
    }
}
