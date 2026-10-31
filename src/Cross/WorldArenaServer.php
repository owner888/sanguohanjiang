<?php

declare(strict_types=1);

namespace Hanjiang\Cross;

use Hanjiang\Battle\TeamBuilder;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\Message;
use Hanjiang\Model\Sacredtree;
use Hanjiang\Model\WorldArena;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;

/**
 * 跨服演武的战斗服务器部分（旧版 battle controllers/worldarena.php + cron.php 的 reset_rank_list / reset_member_tree，
 * 以及 s1 controllers/uc_worldarena.php 里供 battle 调用的 get_top_rank / update_top_rank / send_message / get_member_tree）
 *
 * 旧版 s1 的 worldarena 控制器用 _curl_post_to_uc 请求 battle_url；单服部署下 battle 是本进程内的模块，s1 的调用方直接调用
 * 这里的方法，返回值与 battle 控制器 api_return 的数组相同。表名前缀 zc_sanguo_battle_。
 * battle 的 worldarena_model / user_model / server_model 不在旧版代码包里，按控制器、s1 调用方、客户端与表结构还原
 * （按控制器与表结构还原（旧版 model 不在代码包里））：
 * - 排行榜 world_arena：各服昨日演武榜前三（serverid_uid），名次 1..n；排行为空时按各服前三初始化（init_rank_list）；
 * - 排行分页每页 RANK_PAGE_SIZE 人（推定）；search_rank 取该玩家所在的那一页；
 * - 支持数存 Redis 哈希 worldarena_support（field serverid_uid），每日重置排行时清空（推定）；
 * - 战报 world_report_log（列表）/ world_report（id 相同，带战斗过程），列表按时间倒序每页 5 条（客户端满 5 条才翻页）；
 * - 商城购买记录取最近 SHOP_LOG_NUM 条（推定）。
 */
final class WorldArenaServer
{
    /** 排行榜每页人数（推定） */
    public const int RANK_PAGE_SIZE = 10;

    /** 战报列表每页条数（客户端 worldarenapop.js：满 5 条才可翻页） */
    public const int REPORT_PAGE_SIZE = 5;

    /** 商城购买记录条数（推定） */
    public const int SHOP_LOG_NUM = 10;

    private const string SUPPORT_KEY = 'worldarena_support';

    private const array RANK_COLUMNS = ['id', 'uid', 'serverid', 'groupid', 'groupname', 'nickname', 'rank', 'changed', 'tree', 'dateline'];

    /** s1 lang message_worldarena_title / win / lose（信件模板，type 8） */
    private const string LANG_MESSAGE_TITLE = '跨服演武';
    private const string LANG_MESSAGE_WIN = '%s玩家<em>%s</em>在跨服排行榜向您挑战，被您打的落花流水。';
    private const string LANG_MESSAGE_LOSE = '%s玩家<em>%s</em>在跨服排行榜将您击败，排名跌落至第%s名。';

    // ---------------------------------------------------------------------------------------------
    // 排行榜
    // ---------------------------------------------------------------------------------------------

    /**
     * get_rank_list：uid + serverid 时取该玩家所在页，否则取第 page 页
     *
     * status：-1 获取服务器列表失败；-2 获取排行榜分页失败；1 成功（list 以 serverid_uid 为键）
     *
     * @return array<string, mixed>
     */
    public static function getRankList(int $uid = 0, int $serverid = 0, int $page = 0): array
    {
        $return = ['status' => 0];
        // 取排行榜人数
        if (self::count() === 0) {
            // 获取服务器列表
            $list = Server::worldList();
            if ($list === []) {
                $return['status'] = -1;
                return $return;
            }
            // 初始化排行榜
            self::initRankList($list);
        }
        $rank = $uid > 0 && $serverid > 0 ? self::searchRank($uid, $serverid) : self::rankPage($page);
        if ($rank === null) {
            $return['status'] = -2;
            return $return;
        }
        $return['status'] = 1;
        $return['list'] = $rank['list'];
        $return['page'] = $rank['page'];
        $return['pages'] = $rank['pages'];
        return $return;
    }

    private static function count(): int
    {
        return (int)DB::table('battle_world_arena')->whereNull('deleted_at')->count();
    }

    /**
     * 分页（page 不在范围内按 1 / 最后一页）；排行为空为 null
     *
     * @return array{list: array<string, array<string, mixed>>, page: int, pages: int}|null
     */
    private static function rankPage(int $page): ?array
    {
        $count = self::count();
        if ($count === 0) {
            return null;
        }
        $pages = (int)ceil($count / self::RANK_PAGE_SIZE);
        $page = max(1, min($page, $pages));
        $rows = DB::table('battle_world_arena')->select(...self::RANK_COLUMNS)->whereNull('deleted_at')
            ->orderBy('rank')->limit(self::RANK_PAGE_SIZE)->offset(($page - 1) * self::RANK_PAGE_SIZE)->get();
        $list = [];
        foreach ($rows as $row) {
            $row = self::rankRow($row);
            $list[$row['id']] = $row;
        }
        return ['list' => $list, 'page' => $page, 'pages' => $pages];
    }

    /**
     * search_rank：该玩家所在的那一页；不在榜上为 null
     *
     * @return array{list: array<string, array<string, mixed>>, page: int, pages: int}|null
     */
    private static function searchRank(int $uid, int $serverid): ?array
    {
        $info = self::rankRowOf($uid, $serverid);
        if ($info === null) {
            return null;
        }
        $before = (int)DB::table('battle_world_arena')->where('rank', '<', $info['rank'])->whereNull('deleted_at')->count();
        return self::rankPage(intdiv($before, self::RANK_PAGE_SIZE) + 1);
    }

    /**
     * 初始化排行榜（init_rank_list）：各服当天的前三按服务器顺序依次排名
     *
     * @param list<array<string, mixed>> $serverList
     */
    private static function initRankList(array $serverList): void
    {
        Server::transaction(static function () use ($serverList): void {
            // 并发初始化只做一次
            DB::query('SELECT GET_LOCK(\'battle_world_arena_init\', 10)');
            try {
                if (self::count() > 0) {
                    return;
                }
                $rank = 0;
                foreach ($serverList as $server) {
                    foreach (self::serverTopRank((int)$server['id']) as $value) {
                        $rank++;
                        self::insertRankRow($value, $rank);
                    }
                }
            } finally {
                DB::query('SELECT RELEASE_LOCK(\'battle_world_arena_init\')');
            }
        });
    }

    /**
     * s1 uc_worldarena->get_top_rank（battle 调用各服）：某服当天的前三；单服部署下只有本服
     *
     * @return array<int, array<string, mixed>>
     */
    private static function serverTopRank(int $serverid, int $ymd = 0): array
    {
        if ($serverid !== Server::id()) {
            // 多服部署需要请求其他服（见 README 跨服），单服部署不会出现
            return [];
        }
        $now = BossClock::now();
        return WorldArena::topRank($now, $ymd === 0 ? (int)WorldArena::date('Ymd', $now) : $ymd);
    }

    /**
     * 以前上过榜（退出时软删除）的玩家再次进榜时恢复原行。
     *
     * @param array<string, mixed> $value s1 前三的一行（uid / serverid / nickname）
     */
    private static function insertRankRow(array $value, int $rank): void
    {
        $serverid = (int)$value['serverid'];
        $server = Server::info($serverid);
        $group = $server === null ? null : Server::group((int)$server['servergroup']);
        DB::execute(
            'INSERT INTO `zc_sanguo_battle_world_arena` (`id`, `uid`, `serverid`, `groupid`, `groupname`, `nickname`, `rank`, `changed`, `tree`, `dateline`)
             VALUES (:id, :uid, :serverid, :groupid, :groupname, :nickname, :rank, 0, 0, 0)
             ON DUPLICATE KEY UPDATE `nickname` = VALUES(`nickname`), `rank` = VALUES(`rank`), `changed` = 0, `deleted_at` = NULL',
            [
                'id' => $serverid . '_' . (int)$value['uid'],
                'uid' => (int)$value['uid'],
                'serverid' => $serverid,
                'groupid' => (int)($group['groupid'] ?? 0),
                'groupname' => (string)($group['name'] ?? ''),
                'nickname' => (string)$value['nickname'],
                'rank' => $rank,
            ]
        );
    }

    /**
     * get_rank_info：排行榜里的一行 + 支持数；不在榜上为空数组
     *
     * @return array<string, mixed>
     */
    public static function getRankInfo(int $uid, int $serverid): array
    {
        $return = self::rankRowOf($uid, $serverid);
        if ($return === null) {
            return [];
        }
        $return['support'] = self::getSupport($uid, $serverid);
        return $return;
    }

    /**
     * get_rank_log：某天（ymd）结束时的排名记录 + 支持数；没有为空数组
     *
     * @return array<string, mixed>
     */
    public static function getRankLog(int $uid, int $serverid, int $ymd): array
    {
        $row = DB::table('battle_world_log')->select('uid', 'serverid', 'ymd', 'rank', 'changed')
            ->where('serverid', $serverid)->where('uid', $uid)->where('ymd', $ymd)->whereNull('deleted_at')->first();
        if ($row === null) {
            return [];
        }
        $return = array_map('intval', $row);
        $return['support'] = self::getSupport($uid, $serverid);
        return $return;
    }

    /** get_support：支持数 */
    public static function getSupport(int $uid, int $serverid): int
    {
        return (int)(Redis::hGet(self::SUPPORT_KEY, $serverid . '_' . $uid) ?: 0);
    }

    /** incr_support：支持数加一，返回加后的数 */
    public static function incrSupport(int $uid, int $serverid): int
    {
        return (int)Redis::hIncrBy(self::SUPPORT_KEY, $serverid . '_' . $uid, 1);
    }

    /**
     * get_team_info：挑战前检查双方并取对手信息
     *
     * status：-1 不能挑战自己；-2 自己不在排行榜中；-3 对手不在排行榜中；-4 目标玩家不可挑战（只能打前面 5 名以内）；
     * -6 获取对手信息失败；2 双方在同一服务器（base 带对手昵称与服务器组，s1 用本服 battle_pk）；
     * 1 不同服务器（返回对手队伍，s1 用 battle_world_pk）——单服部署下榜上都是本服玩家，不会出现，按获取对手信息失败回 -6。
     *
     * @return array<string, mixed>
     */
    public static function getTeamInfo(int $uid, int $serverid, int $touid, int $toserverid): array
    {
        if ($uid === $touid && $serverid === $toserverid) {
            return ['status' => -1];
        }
        $left = self::rankRowOf($uid, $serverid);
        if ($left === null) {
            return ['status' => -2];
        }
        $right = self::rankRowOf($touid, $toserverid);
        if ($right === null) {
            return ['status' => -3];
        }
        // 双方在同一服务器
        if ($left['serverid'] === $right['serverid']) {
            return [
                'status' => 2,
                'base' => [
                    'groupid' => $left['groupid'],
                    'group' => $left['groupname'],
                    'togroupid' => $right['groupid'],
                    'togroup' => $right['groupname'],
                    'serverid' => $right['serverid'],
                    'nickname' => $right['nickname'],
                ],
            ];
        }
        // 只有打自己前面 5 名以内(包括 5 名)，以及后面所有人
        if ($left['rank'] - $right['rank'] > 5) {
            return ['status' => -4];
        }
        // 依赖未移植：其他服务器玩家的队伍（user_model->init_member_team_info 请求对方服的 uc_worldarena），多服部署才有
        return ['status' => -6];
    }

    /**
     * update_rank：挑战胜利后与对手交换名次
     *
     * status：-1 / -2 双方不在排行榜中；-3 / -4 更新失败；2 自己排名本来就高于对方（不交换）；
     * 1 成功（changed 本日累计变化、torank 新名次、left_rank 原名次，list / page / pages 为自己所在页）。
     * 两行在同一事务里按 autoid 顺序加锁后重新读名次，多 Worker 同时挑战不会把名次换乱。
     *
     * @return array<string, mixed>
     */
    public static function updateRank(int $uid, int $serverid, int $touid, int $toserverid, int $now): array
    {
        $result = Server::transaction(static function () use ($uid, $serverid, $touid, $toserverid, $now): array {
            $rows = DB::query(
                'SELECT `autoid`, `id`, `rank`, `changed` FROM `zc_sanguo_battle_world_arena` WHERE `id` IN (:a, :b) AND `deleted_at` IS NULL ORDER BY `autoid` FOR UPDATE',
                ['a' => $serverid . '_' . $uid, 'b' => $toserverid . '_' . $touid]
            );
            $byId = [];
            foreach ($rows as $row) {
                $byId[(string)$row['id']] = ['autoid' => (int)$row['autoid'], 'rank' => (int)$row['rank'], 'changed' => (int)$row['changed']];
            }
            $left = $byId[$serverid . '_' . $uid] ?? null;
            if ($left === null) {
                return ['status' => -1];
            }
            $right = $byId[$toserverid . '_' . $touid] ?? null;
            if ($right === null) {
                return ['status' => -2];
            }
            if ($left['rank'] <= $right['rank']) {
                // 排名高于对方
                return ['status' => 2];
            }
            // 更新右方（此时右方排名）
            $update = DB::table('battle_world_arena')->where('autoid', $right['autoid'])
                ->update(['rank' => $left['rank'], 'changed' => $right['changed'] + $right['rank'] - $left['rank']]);
            if ($update !== 1) {
                return ['status' => -3];
            }
            // 更新左方（此时左方排名）
            $update = DB::table('battle_world_arena')->where('autoid', $left['autoid'])
                ->update(['rank' => $right['rank'], 'changed' => $left['changed'] + $left['rank'] - $right['rank'], 'dateline' => $now]);
            if ($update !== 1) {
                return ['status' => -4];
            }
            return ['status' => 1, 'left' => $left, 'right' => $right];
        });
        if ($result['status'] !== 1) {
            return $result;
        }
        $left = $result['left'];
        $right = $result['right'];
        // 获取排名列表（旧版先 update_rank_list 刷新排名缓存；进程内直接查表）
        $rank = self::searchRank($uid, $serverid);
        return [
            'status' => 1,
            'changed' => $left['changed'] + $left['rank'] - $right['rank'],
            'torank' => $right['rank'],
            'left_rank' => $left['rank'],
            'list' => $rank['list'] ?? [],
            'page' => $rank['page'] ?? 1,
            'pages' => $rank['pages'] ?? 1,
        ];
    }

    /**
     * update_worldarena_tree：更新榜上玩家的神树等级
     *
     * status：-1 参数不完整；1 成功
     *
     * @return array{status: int}
     */
    public static function updateTree(int $serverid, int $uid, int $tree): array
    {
        if ($serverid === 0 || $uid === 0 || $tree === 0) {
            return ['status' => -1];
        }
        DB::table('battle_world_arena')->where('id', $serverid . '_' . $uid)->whereNull('deleted_at')->update(['tree' => $tree]);
        return ['status' => 1];
    }

    // ---------------------------------------------------------------------------------------------
    // 战报 / 商城记录 / 信件
    // ---------------------------------------------------------------------------------------------

    /**
     * save_report：保存战报，返回战报 id（s1 的 report_info：fromserverid / uid / from / fromlevel / fromtree / torank /
     * toserverid / touid / to / tolevel / totree / win / report）
     *
     * @param array<string, mixed> $data
     * @return array{status: int, reportid: int}
     */
    public static function saveReport(array $data, int $now): array
    {
        $fromGroup = self::groupOf((int)$data['fromserverid']);
        $toGroup = self::groupOf((int)$data['toserverid']);
        $log = [
            'fromserverid' => (int)$data['fromserverid'],
            'fromgroupid' => $fromGroup['groupid'],
            'fromgroup' => $fromGroup['name'],
            'fromtree' => (int)$data['fromtree'],
            'uid' => (int)$data['uid'],
            'nickname' => (string)$data['from'],
            'userlevel' => (int)$data['fromlevel'],
            'torank' => (int)$data['torank'],
            'toserverid' => (int)$data['toserverid'],
            'togroupid' => $toGroup['groupid'],
            'togroup' => $toGroup['name'],
            'touid' => (int)$data['touid'],
            'totree' => (int)$data['totree'],
            'name' => (string)$data['to'],
            'level' => (int)$data['tolevel'],
            'win' => (int)$data['win'],
            'dateline' => $now,
        ];
        $id = Server::transaction(static function () use ($log, $data): int {
            $id = (int)DB::table('battle_world_report_log')->insertGetId($log);
            // 战斗背景：s1 的战报信息里没有，记 0
            DB::table('battle_world_report')->insert(['id' => $id, 'report' => (string)json_encode($data['report'], JSON_UNESCAPED_UNICODE), 'background' => 0] + $log);
            return $id;
        });
        return ['status' => 1, 'reportid' => $id];
    }

    /**
     * get_report_list：战报列表（全服，按时间倒序）；没有为空数组
     *
     * 每行 id / fromserverid / fromgroup / fromtree / fromuid / from / torank / toserverid / togroup / touid / totree / to / win
     * （客户端 worldarenapop.js 的字段）
     *
     * @return array{list: list<array<string, mixed>>, page: int}|array{}
     */
    public static function getReportList(int $page): array
    {
        $page = max(1, $page);
        $rows = DB::table('battle_world_report_log')
            ->select('id', 'fromserverid', 'fromgroup', 'fromtree', 'uid', 'nickname', 'userlevel', 'torank', 'toserverid', 'togroup', 'touid', 'totree', 'name', 'level', 'win', 'dateline')
            ->whereNull('deleted_at')->orderBy('id', 'DESC')->limit(self::REPORT_PAGE_SIZE)->offset(($page - 1) * self::REPORT_PAGE_SIZE)->get();
        if ($rows === []) {
            return [];
        }
        $list = [];
        foreach ($rows as $row) {
            $list[] = [
                'id' => (int)$row['id'],
                'fromserverid' => (int)$row['fromserverid'],
                'fromgroup' => (string)$row['fromgroup'],
                'fromtree' => (int)$row['fromtree'],
                'fromuid' => (int)$row['uid'],
                'from' => (string)$row['nickname'],
                'fromlevel' => (int)$row['userlevel'],
                'torank' => (int)$row['torank'],
                'toserverid' => (int)$row['toserverid'],
                'togroup' => (string)$row['togroup'],
                'touid' => (int)$row['touid'],
                'totree' => (int)$row['totree'],
                'to' => (string)$row['name'],
                'tolevel' => (int)$row['level'],
                'win' => (int)$row['win'],
                'dateline' => (int)$row['dateline'],
            ];
        }
        return ['list' => $list, 'page' => $page];
    }

    /**
     * get_report_info：战报行（report 为战斗过程数组）；没有为空数组
     *
     * @return array<string, mixed>
     */
    public static function getReportInfo(int $id): array
    {
        $row = DB::table('battle_world_report')
            ->select('id', 'report', 'fromserverid', 'fromgroupid', 'fromgroup', 'fromtree', 'uid', 'nickname', 'userlevel', 'torank', 'toserverid', 'togroupid', 'togroup', 'touid', 'totree', 'name', 'level', 'win', 'background', 'dateline')
            ->where('id', $id)->whereNull('deleted_at')->first();
        if ($row === null) {
            return [];
        }
        $report = json_decode((string)$row['report'], true);
        $row = array_map(static fn (mixed $v): mixed => is_numeric($v) ? (int)$v : $v, $row);
        $row['report'] = is_array($report) ? $report : [];
        return $row;
    }

    /**
     * get_shop_log：最近的跨服商城购买记录
     *
     * @return list<array<string, mixed>>
     */
    public static function getShopLog(): array
    {
        $rows = DB::table('battle_world_shop_log')->select('uid', 'serverid', 'groupid', 'groupname', 'itemid', 'type', 'name', 'nickname', 'dateline')
            ->whereNull('deleted_at')->orderBy('id', 'DESC')->limit(self::SHOP_LOG_NUM)->get();
        return array_map(static fn (array $r): array => array_map(static fn (mixed $v): mixed => is_numeric($v) ? (int)$v : $v, $r), $rows);
    }

    /**
     * add_shop_log：记录购买，返回最新记录
     *
     * status：-1 服务器不存在；1 成功（log 为最新记录）
     *
     * @param array{uid: int, itemid: int, type: int, name: string, serverid: int, nickname: string} $post
     * @return array<string, mixed>
     */
    public static function addShopLog(array $post, int $now): array
    {
        $server = Server::info($post['serverid']);
        if ($server === null) {
            return ['status' => -1];
        }
        $group = Server::group((int)$server['servergroup']);
        DB::table('battle_world_shop_log')->insert([
            'uid' => $post['uid'],
            'itemid' => $post['itemid'],
            'type' => $post['type'],
            'name' => $post['name'],
            'serverid' => $post['serverid'],
            'groupid' => (int)($group['groupid'] ?? 0),
            'groupname' => (string)($group['name'] ?? ''),
            'nickname' => $post['nickname'],
            'dateline' => $now,
        ]);
        return ['status' => 1, 'log' => self::getShopLog()];
    }

    /**
     * send_message：给（其他服的）被挑战者发跨服演武信件（s1 uc_worldarena->send_message → message_model->send_message type 8）
     *
     * $message：touid / type 8 / win（0 挑战方输了、1 挑战方赢了）/ data（[挑战方服务器 id, 挑战方昵称, 被挑战者新名次]）/ title（战报 id）
     *
     * @param array<string, mixed> $message
     */
    public static function sendMessage(int $serverid, array $message, int $now): void
    {
        if ($serverid > 6000) {
            // 阻止向韩国发信，没有语言包
            return;
        }
        if ($serverid !== Server::id()) {
            // 多服部署需要投递到对方服（见 README 跨服），单服部署不会出现
            return;
        }
        $data = $message['data'];
        $serverName = Server::serverName((int)$data[0]);
        if ((int)$message['win'] === 0) {
            $text = sprintf(self::LANG_MESSAGE_WIN, $serverName, $data[1] ?? 0);
        } else {
            $text = sprintf(self::LANG_MESSAGE_LOSE, $serverName, $data[1] ?? 0, $data[2] ?? 0);
        }
        Message::send([
            'touid' => (int)$message['touid'],
            'type' => 8,
            'title' => (string)$message['title'],
            'message' => $text,
            'fromuid' => 1000,
            'fromname' => self::LANG_MESSAGE_TITLE,
        ], ['uid' => 1000, 'nickname' => self::LANG_MESSAGE_TITLE], $now);
    }

    // ---------------------------------------------------------------------------------------------
    // 计划任务（battle cron.php）
    // ---------------------------------------------------------------------------------------------

    /**
     * reset_rank_list（0 点后）：记录昨日排名与名次变化，按各服今天的前三更新排行榜
     *
     * record_change_log / reset_rank_list 按控制器与表结构还原（旧版 model 不在代码包里）：昨日每人一行 world_log；
     * 各服 update_top_rank 的 exit（昨天在前三、今天不在）从榜上删除，剩下的按原名次重新排成 1..n，into（今天新进前三）
     * 依次排在后面；所有人本日名次变化 changed 清零，支持数清空。排行榜为空时按各服今天的前三初始化（同 get_rank_list）。
     */
    public static function resetRankList(int $now): int
    {
        $ymd = (int)date('Ymd', $now - 86400);
        Server::transaction(static function () use ($ymd, $now): void {
            // 记录排名变化
            DB::execute(
                'INSERT IGNORE INTO `zc_sanguo_battle_world_log` (`uid`, `serverid`, `ymd`, `rank`, `changed`)
                 SELECT `uid`, `serverid`, :ymd, `rank`, `changed` FROM `zc_sanguo_battle_world_arena` WHERE `deleted_at` IS NULL',
                ['ymd' => $ymd]
            );
            $serverList = Server::worldList();
            if ($serverList === []) {
                return;
            }
            if (self::count() === 0) {
                // 排行榜还没有初始化（get_rank_list 第一次打开时才初始化）：按各服今天的前三初始化（推定）
                self::initRankList($serverList);
                return;
            }
            $into = [];
            foreach ($serverList as $server) {
                $diff = self::updateTopRank((int)$server['id'], $now);
                foreach ($diff['exit'] as $value) {
                    DB::table('battle_world_arena')->where('id', (int)$value['serverid'] . '_' . (int)$value['uid'])->whereNull('deleted_at')
                        ->update(['deleted_at' => date('Y-m-d H:i:s', $now)]);
                }
                foreach ($diff['into'] as $value) {
                    $into[] = $value;
                }
            }
            $rows = DB::table('battle_world_arena')->select('autoid')->whereNull('deleted_at')->orderBy('rank')->orderBy('autoid')->get();
            $rank = 0;
            foreach ($rows as $row) {
                $rank++;
                DB::table('battle_world_arena')->where('autoid', (int)$row['autoid'])->update(['rank' => $rank, 'changed' => 0]);
            }
            foreach ($into as $value) {
                $exists = DB::table('battle_world_arena')->where('id', (int)$value['serverid'] . '_' . (int)$value['uid'])->whereNull('deleted_at')->value('autoid');
                if ($exists === null) {
                    $rank++;
                    self::insertRankRow($value, $rank);
                }
            }
        });
        Redis::del(self::SUPPORT_KEY);
        return 1;
    }

    /**
     * s1 uc_worldarena->update_top_rank：今天与昨天前三的差集（into 新进、exit 退出），键为 serverid_uid
     *
     * @return array{into: array<string, array<string, mixed>>, exit: array<string, array<string, mixed>>}
     */
    private static function updateTopRank(int $serverid, int $now): array
    {
        $return = ['exit' => [], 'into' => []];
        // 昨日本服前三（今天 0 点的排名） / 前日本服前三
        $rank1 = self::serverTopRank($serverid, (int)date('Ymd', $now));
        $rank2 = self::serverTopRank($serverid, (int)date('Ymd', $now - 86400));
        foreach (array_diff_key($rank1, $rank2) as $value) {
            $return['into'][$value['serverid'] . '_' . $value['uid']] = $value;
        }
        foreach (array_diff_key($rank2, $rank1) as $value) {
            $return['exit'][$value['serverid'] . '_' . $value['uid']] = $value;
        }
        return $return;
    }

    /**
     * reset_member_tree（每天 00:10 后每 10 分钟）：按各服神树活动更新榜上玩家的神树等级
     *
     * reset_member_tree 按控制器与表结构还原（旧版 model 不在代码包里）：各服 get_member_tree 返回活动开启时的神树行，
     * 有记录的写 treeid，没有（或活动未开启）的写 0。
     *
     * status：-1 没有服务器；-2 排行榜为空；1 成功
     */
    public static function resetMemberTree(int $now): int
    {
        if (Server::worldList() === []) {
            return -1;
        }
        $rankList = DB::table('battle_world_arena')->select('uid', 'serverid')->whereNull('deleted_at')->get();
        if ($rankList === []) {
            return -2;
        }
        // 把要查询的用户列表按服务器区分
        $list = [];
        foreach ($rankList as $val) {
            $list[(int)$val['serverid']][] = (int)$val['uid'];
        }
        foreach ($list as $serverid => $uids) {
            if ($serverid !== Server::id()) {
                // 其他服的神树需请求对方服（多服部署）
                continue;
            }
            // s1 uc_worldarena->get_member_tree：神树活动开启时取本活动版本的神树
            $version = Sacredtree::openVersion($now);
            $tree = $version === null ? [] : Sacredtree::treeByUid($version, $uids);
            foreach ($uids as $uid) {
                DB::table('battle_world_arena')->where('id', $serverid . '_' . $uid)->whereNull('deleted_at')
                    ->update(['tree' => (int)($tree[$uid]['treeid'] ?? 0)]);
            }
        }
        return 1;
    }

    // ---------------------------------------------------------------------------------------------

    /**
     * 榜上一行（world_arena），没有为 null
     *
     * @return array{id: string, uid: int, serverid: int, groupid: int, groupname: string, nickname: string, rank: int, changed: int, tree: int, dateline: int}|null
     */
    private static function rankRowOf(int $uid, int $serverid): ?array
    {
        $row = DB::table('battle_world_arena')->select(...self::RANK_COLUMNS)->where('id', $serverid . '_' . $uid)->whereNull('deleted_at')->first();
        return $row === null ? null : self::rankRow($row);
    }

    /**
     * @param array<string, mixed> $row
     * @return array{id: string, uid: int, serverid: int, groupid: int, groupname: string, nickname: string, rank: int, changed: int, tree: int, dateline: int}
     */
    private static function rankRow(array $row): array
    {
        return [
            'id' => (string)$row['id'],
            'uid' => (int)$row['uid'],
            'serverid' => (int)$row['serverid'],
            'groupid' => (int)$row['groupid'],
            'groupname' => (string)$row['groupname'],
            'nickname' => (string)$row['nickname'],
            'rank' => (int)$row['rank'],
            'changed' => (int)$row['changed'],
            'tree' => (int)$row['tree'],
            'dateline' => (int)$row['dateline'],
        ];
    }

    /**
     * @return array{groupid: int, name: string}
     */
    private static function groupOf(int $serverid): array
    {
        $server = Server::info($serverid);
        $group = $server === null ? null : Server::group((int)$server['servergroup']);
        return ['groupid' => (int)($group['groupid'] ?? 0), 'name' => (string)($group['name'] ?? '')];
    }
}
