<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;

/**
 * 主城建筑首次打开记录（移植旧版 models/edifice_model.php）
 *
 * 主公等级达到建筑开放等级后的 10 级内（开放等级 > 主公等级 - 10），没打开过的建筑在主城显示「新」标记
 * （member.index 的 edifice）；第一次打开时记 edifice_log。已打开列表缓存在 Redis edifice_status_{uid}（旧版键名，1 天）。
 * 建筑列表照旧版写死（edifice_list）。
 */
final class Edifice
{
    /** 功能列表（id => [name, 开放等级]） */
    public const array LIST = [
        1 => ['name' => 'practice', 'level' => 11],
        2 => ['name' => 'mine', 'level' => 26],
        3 => ['name' => 'arena', 'level' => 26],
        4 => ['name' => 'inherit', 'level' => 26],
        5 => ['name' => 'trade', 'level' => 31],
        6 => ['name' => 'worldarena', 'level' => 61],
        7 => ['name' => 'book', 'level' => 81],
        8 => ['name' => 'workshop', 'level' => 91],
        9 => ['name' => 'herotavern', 'level' => 150],
        10 => ['name' => 'tavern', 'level' => 150],
        11 => ['name' => 'beauty', 'level' => 60],
    ];

    public const int PRACTICE = 1;
    public const int MINE = 2;
    public const int ARENA = 3;
    public const int INHERIT = 4;
    public const int TRADE = 5;
    public const int BOOK = 7;
    public const int WORKSHOP = 8;
    public const int HEROTAVERN = 9;
    public const int TAVERN = 10;
    public const int BEAUTY = 11;

    private const string CACHE_KEY = 'edifice_status_';
    private const int CACHE_TTL = 86400;

    /**
     * 功能开启状态（get_open_status：当前等级阶段新开放、还没打开过的建筑）
     *
     * @return list<array{name: string, open: int}>
     */
    public static function openStatus(int $uid, int $level): array
    {
        $returnList = [];
        foreach (self::LIST as $id => $val) {
            if ($val['level'] > $level - 10 && $val['level'] <= $level) {
                $returnList[$id] = ['name' => $val['name'], 'open' => 1];
            }
        }
        if ($returnList === []) {
            return [];
        }
        foreach (self::openList($uid) as $val) {
            unset($returnList[(int)$val['edifice_id']]);
        }
        return array_values($returnList);
    }

    /**
     * 添加操作记录（add_edifice_log：只记当前等级阶段的建筑，已记过不再记）
     */
    public static function addLog(int $uid, int $id): int
    {
        $data = self::LIST[$id] ?? null;
        $level = (int)(DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('level') ?? 0);
        // 判断该用户等级是否超过判断范围
        if ($data === null || $data['level'] <= $level - 10 || $data['level'] > $level) {
            return 0;
        }
        $result = self::openList($uid);
        foreach ($result as $val) {
            if ((int)$val['edifice_id'] === $id) {
                return 0;
            }
        }
        $row = ['uid' => $uid, 'edifice_id' => $id, 'edifice_name' => $data['name'], 'level' => $level, 'dateline' => time()];
        $inserted = DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_edifice_log` (`uid`, `edifice_id`, `edifice_name`, `level`, `dateline`)
             VALUES (:uid, :edifice_id, :edifice_name, :level, :dateline)',
            $row
        );
        if ($inserted === 0) {
            return 0;
        }
        // 更新缓存
        $result[] = $row;
        Redis::set(self::CACHE_KEY . $uid, $result, self::CACHE_TTL);
        return 1;
    }

    /**
     * 已开启的功能列表（get_open_list：读缓存，没有时查库并写缓存）
     *
     * @return list<array<string, mixed>>
     */
    private static function openList(int $uid): array
    {
        $result = Redis::get(self::CACHE_KEY . $uid);
        if (!is_array($result)) {
            $result = DB::table('edifice_log')->select('uid', 'edifice_id', 'edifice_name', 'level', 'dateline')
                ->where('uid', $uid)->whereNull('deleted_at')->orderBy('id')->get();
            Redis::set(self::CACHE_KEY . $uid, $result, self::CACHE_TTL);
        }
        return $result;
    }
}
