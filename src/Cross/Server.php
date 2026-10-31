<?php

declare(strict_types=1);

namespace Hanjiang\Cross;

use OmniPHP\Config;
use OmniPHP\Database\DB;

/**
 * 服务器信息（旧版 s1 models/server_model.php + uc / battle 的 server_model->server_info / group_info / world_server_list）
 *
 * 游戏按单服部署，旧版独立的 uc（用户中心）与 battle（战斗服务器）在本进程内（Hanjiang\Cross），表在同一个库里；
 * 本服的服务器 id 为 config app.server_id（默认 1，旧版 config serverid），服务器列表取 uc_server（本服一行）。
 * uc / battle 的 server_model 不在旧版代码包里：server_info 按 uc_server 的行、group_info 按 uc_group 的 groupid 还原，
 * world_server_list 按「开放中的服务器」还原（按控制器与表结构还原（旧版 model 不在代码包里））。
 */
final class Server
{
    /** 旧版 lang/server_lang.php */
    private const array LANG = [
        'server_name_1' => '【官方%s服】',
        'server_name_2' => '【港台%s服】',
        'server_name_3' => '【日本%s服】',
        'server_name_4' => '【韩国%s服】',
        'server_group_1' => '官方',
        'server_group_2' => '港台',
        'server_group_3' => '日本',
        'server_group_4' => '韩国',
    ];

    /** 本服的服务器 id（旧版 $this->config->item('serverid')；server_merge 未移植，同 serverid） */
    public static function id(): int
    {
        return (int)Config::get('app.server_id', 1);
    }

    /** server_model->get_sid：服务器 id 的后三位 */
    public static function sid(int $serverid): int
    {
        return $serverid % 1000;
    }

    /** server_model->get_group_name：按 id 段取地区名 */
    public static function groupName(int $serverid): string
    {
        return match (true) {
            $serverid < 3000 => self::LANG['server_group_1'],
            $serverid < 4000 => self::LANG['server_group_2'],
            $serverid > 5000 && $serverid < 6000 => self::LANG['server_group_3'],
            $serverid > 6000 && $serverid < 7000 => self::LANG['server_group_4'],
            default => 's',
        };
    }

    /** server_model->get_server_name：如【官方1服】；0 为空串 */
    public static function serverName(int $serverid): string
    {
        if ($serverid === 0) {
            return '';
        }
        $sid = self::sid($serverid);
        return match (true) {
            $serverid < 3000 => sprintf(self::LANG['server_name_1'], $sid),
            $serverid < 4000 => sprintf(self::LANG['server_name_2'], $sid),
            $serverid > 5000 && $serverid < 6000 => sprintf(self::LANG['server_name_3'], $sid),
            $serverid > 6000 && $serverid < 7000 => sprintf(self::LANG['server_name_4'], $sid),
            default => 's' . $serverid,
        };
    }

    /**
     * uc / battle 的 server_model->server_info：uc_server 的一行，没有为 null
     *
     * @return array<string, mixed>|null
     */
    public static function info(int $serverid): ?array
    {
        return DB::table('uc_server')->where('id', $serverid)->whereNull('deleted_at')->first();
    }

    /**
     * uc server_model->group_info：按服务器分组取 uc_group 行（id / groupid / name），没有为 null
     *
     * @return array<string, mixed>|null
     */
    public static function group(int $servergroup): ?array
    {
        return DB::table('uc_group')->select('id', 'groupid', 'name')->where('groupid', $servergroup)->whereNull('deleted_at')->first();
    }

    /**
     * battle server_model->world_server_list：参加跨服的服务器（开放中的 uc_server），id 升序
     *
     * @return list<array<string, mixed>>
     */
    public static function worldList(): array
    {
        return DB::table('uc_server')->where('status', 1)->whereNull('deleted_at')->orderBy('id')->get();
    }

    /**
     * uc_model->get_time_cache：本服开服时间（uc levelgift->get_date_by_serverid，取 uc_server.starttime；没有本服行为 0）
     *
     * 旧版把结果缓存在 Redis levelgift_{serverid} 100 天；进程内直接查表，不再缓存。
     */
    public static function openTime(): int
    {
        return (int)(DB::table('uc_server')->where('id', self::id())->whereNull('deleted_at')->value('starttime') ?? 0);
    }

    /**
     * 在事务里执行（调用方已在事务中时沿用调用方的事务，同 Member::locked）
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public static function transaction(callable $callback): mixed
    {
        return DB::inTransaction() ? $callback() : DB::transaction($callback);
    }
}
