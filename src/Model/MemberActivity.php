<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use OmniPHP\Database\DB;

/**
 * 玩家活动信息（移植旧版 models/member_model.php 的 zc_sanguo_member_activity 读写：get_activity_info / update_activity_info）
 *
 * 新版 member_activity 只有已移植玩法的字段（将魂星路、多人副本、金银岛、四象圣域、通天塔），其余旧版字段随对应玩法补充。
 */
final class MemberActivity
{
    /**
     * get_activity_info 首次建档写入的值（只列新版表里已有的字段；旧版其余字段的建档值随对应玩法补充）
     */
    public const array INSERT_DEFAULTS = [
        // 将魂星路
        'towerlevel' => 1,
        'towerstage' => 0,
        'towermission' => 0,
        'freetower' => 12,
        'towertimes' => 0,
        'souldate' => 0,
        // 多人副本（elitebuytimes / elitebuydateline 旧版建档语句不写，取列默认值 0）
        'elitetimes' => 10,
        'elitesurplustimes' => 0,
        // 金银岛
        'islandid' => 0,
        'islandtimes' => 0,
        'islanddate' => 0,
        // 通天塔（sanctumdate / heavenstage 旧版建档语句不写，取列默认值 0）
        'heavenlevel' => 1,
        'heavenmission' => 0,
        'freeheaven' => 0,
        'heaventimes' => 10,
        'heavendate' => 0,
    ];

    /**
     * get_activity_info 单个 uid：没有记录时按旧版建档值插入（并发首请求用 INSERT IGNORE 只建一次）
     */
    public static function ensure(int $uid): void
    {
        if (DB::table('member_activity')->where('uid', $uid)->whereNull('deleted_at')->value('uid') !== null) {
            return;
        }
        $columns = array_keys(self::INSERT_DEFAULTS);
        DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_member_activity` (`uid`, `' . implode('`, `', $columns) . '`)
             VALUES (:uid, :' . implode(', :', $columns) . ')',
            ['uid' => $uid] + self::INSERT_DEFAULTS
        );
    }

    /**
     * get_activity_info 多个 uid（旧版传数组时不建档）：uid => 行，查不到的 uid 不在结果里
     *
     * @param list<int> $uids
     * @param list<string> $columns
     * @return array<int, array<string, int>>
     */
    public static function many(array $uids, array $columns): array
    {
        if ($uids === []) {
            return [];
        }
        $return = [];
        $rows = DB::table('member_activity')->select('uid', ...$columns)->whereIn('uid', $uids)->whereNull('deleted_at')->get();
        foreach ($rows as $row) {
            $return[(int)$row['uid']] = array_map('intval', $row);
        }
        return $return;
    }

    /**
     * 更新活动记录（移植 update_activity_info；没有记录时不插入，同旧版）
     *
     * @param array<string, int|float|string> $data
     */
    public static function update(int $uid, array $data): void
    {
        DB::table('member_activity')->where('uid', $uid)->whereNull('deleted_at')->update(array_map('intval', $data));
    }
}
