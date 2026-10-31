<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\MasterData\CountryJob;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\Chat;
use Hanjiang\Model\Sacredtree;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 好友（移植旧版 controllers/friend.php + models/friend_model.php）
 *
 * 好友是单向的（friend 表 uid → fuid，status 1 好友、2 已删除），最多 100 个。列表在线的排在前面（在线 = 聊天室在线列表
 * Chat::onlineList，旧版 get_online_list），再按在线、等级降序排序；显示国家、官职（MasterData CountryJob）与神树等级
 * （旧版 friend.php 的神树开关默认就是开着的：活动未开启时按版本 0 查，取不到为 0）。
 * 好友 uid 列表缓存在 Redis friend_list_{uid}（旧版键名，1 天），国家矿区显示好友标记用它（CountryMineHandler）。
 * 客户端 friendspop.js / miscpop.js 调用 index（p、l）、add_friend（f）、del_friend（f、p、l）；check 客户端不调用，不暴露。
 *
 * 与旧版的差别：friend 表加 (uid, fuid) 唯一键（并发添加不会插出两行），添加以 INSERT IGNORE 完成。
 */
final class FriendHandler
{
    /** 每页显示几条 */
    private const int LIMIT = 10;

    /** 好友列表总人数 */
    private const int ALL_NUMBER = 100;

    /** 好友 uid 列表缓存（旧版 _friend_list） */
    private const string LIST_KEY = 'friend_list_';
    private const int LIST_TTL = 86400;

    /**
     * 显示我的好友列表: c=friend&m=index（p 页码，l 每页条数）
     */
    public static function index(Context $ctx): array
    {
        $return = ['status' => 0, 'all_page' => 0, 'list' => []];
        $uid = MemberHandler::resolveUid($ctx);
        if ($uid <= 0) {
            return $return;
        }
        $page = (int)$ctx->input('p', 0);
        $limit = (int)$ctx->input('l', 0);
        $page = $page > 0 ? $page : 1;
        $limit = $limit > 0 ? $limit : self::LIMIT;
        // 取所有在线用户id（加上自己）
        $online = Chat::onlineList();
        $online[] = $uid;
        return self::listReturn($uid, $page, $limit, $online);
    }

    /**
     * 加为好友: c=friend&m=add_friend（f 好友 uid）
     */
    public static function add_friend(Context $ctx): array
    {
        /**
         * -5 不能加自己
         * -4 参数错误 / 玩家不存在
         * -3 添加好友失败
         * -2 已经是好友
         * -1 好友列表已满
         *  1 成功
         */
        $return = ['status' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        // 要添加的好友id
        $fuid = (int)$ctx->input('f', 0);
        if ($uid <= 0) {
            return $return;
        }
        if ($uid === $fuid) {
            $return['status'] = -5;
            return $return;
        }
        // 判断参数是否正常
        if ($fuid === 0) {
            $return['status'] = -4;
            return $return;
        }
        // 判断好友列表是否已满
        if (self::friendNumber($uid) >= self::ALL_NUMBER) {
            $return['status'] = -1;
            return $return;
        }
        // 获取用户信息
        if (DB::table('member')->where('uid', $fuid)->whereNull('deleted_at')->value('uid') === null) {
            $return['status'] = -4;
            return $return;
        }
        // 判断是否有该好友数据
        $info = self::friendById($uid, $fuid);
        if ($info !== null && $info['status'] === 1) {
            // 已经是好友，就不加
            $return['status'] = -2;
            return $return;
        }
        $now = time();
        if ($info !== null && $info['status'] === 2) {
            // 曾今是好友，就修改一下状态
            $added = self::updateStatus($uid, $fuid, 1, $now);
        } else {
            // 没有，就添加好友
            $added = DB::execute(
                'INSERT IGNORE INTO `zc_sanguo_friend` (`uid`, `fuid`, `status`, `dateline`) VALUES (:uid, :fuid, 1, :now)',
                ['uid' => $uid, 'fuid' => $fuid, 'now' => $now]
            ) > 0;
        }
        if (!$added) {
            // 添加好友失败
            $return['status'] = -3;
            return $return;
        }
        // 更新好友列表缓存
        self::friendList($uid, true);
        $return['status'] = 1;
        return $return;
    }

    /**
     * 删除好友: c=friend&m=del_friend（f 好友 uid，p / l 删除后返回的列表页）
     */
    public static function del_friend(Context $ctx): array
    {
        /**
         * -2 删除失败
         * -1 不是好友
         *  1 成功
         */
        $return = ['status' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        if ($uid <= 0) {
            return $return;
        }
        $fuid = (int)$ctx->input('f', 0);
        $page = (int)$ctx->input('p', 0);
        $limit = (int)$ctx->input('l', 0);
        $page = $page > 0 ? $page : 1;
        $limit = $limit > 0 ? $limit : self::LIMIT;
        // 判断是否有该好友数据（旧版只拦没有记录的，已删除的也能再删一次）
        if (self::friendById($uid, $fuid) === null) {
            $return['status'] = -1;
            return $return;
        }
        // 执行删除
        if (!self::updateStatus($uid, $fuid, 2, time())) {
            $return['status'] = -2;
            return $return;
        }
        // 取所有在线用户id（旧版删除后的列表不加自己）
        $result = self::listReturn($uid, $page, $limit, Chat::onlineList());
        // 更新好友列表缓存
        self::friendList($uid, true);
        return $result;
    }

    /**
     * 好友 uid 列表（friend_model->get_list：读缓存，$refresh 或没有缓存时查库并写缓存）
     *
     * @return list<int>
     */
    public static function friendList(int $uid, bool $refresh = false): array
    {
        $list = Redis::get(self::LIST_KEY . $uid);
        if (is_array($list) && !$refresh) {
            return array_map('intval', $list);
        }
        $rows = DB::table('friend')->select('fuid')->where('uid', $uid)->where('status', 1)->whereNull('deleted_at')->orderBy('id')->get();
        $list = array_map(static fn (array $r): int => (int)$r['fuid'], $rows);
        Redis::set(self::LIST_KEY . $uid, $list, self::LIST_TTL);
        return $list;
    }

    /**
     * 单个好友记录（get_friend_by_id）；没有为 null
     *
     * @return array{fuid: int, status: int}|null
     */
    public static function friendById(int $uid, int $fuid): ?array
    {
        $row = DB::table('friend')->select('fuid', 'status')->where('uid', $uid)->where('fuid', $fuid)->whereNull('deleted_at')->first();
        return $row === null ? null : ['fuid' => (int)$row['fuid'], 'status' => (int)$row['status']];
    }

    /**
     * 好友列表的返回值（index / del_friend 共用的组装）
     *
     * @param list<int> $online
     * @return array<string, mixed>
     */
    private static function listReturn(int $uid, int $page, int $limit, array $online): array
    {
        $list = self::friendPage($uid, $page, $limit, $online);
        $friendList = [];
        $uidArr = [];
        foreach ($list['list'] as $key => $val) {
            $friendList[$key] = ['fuid' => $val, 'online' => in_array($val, $online, true) ? 1 : 0];
            $uidArr[] = $val;
        }
        if ($uidArr !== []) {
            // 查询好友国家官职
            $memberJob = [];
            foreach (DB::table('member_country')->select('uid', 'job')->whereIn('uid', $uidArr)->whereNull('deleted_at')->get() as $row) {
                $memberJob[(int)$row['uid']] = (int)$row['job'];
            }
            // 查询好友普通信息
            $members = [];
            foreach (DB::table('member')->select('uid', 'nickname', 'level', 'country')->whereIn('uid', $uidArr)->whereNull('deleted_at')->get() as $row) {
                $members[(int)$row['uid']] = $row;
            }
            // 查询好友神树信息（活动未开启时按版本 0 查）
            $tree = Sacredtree::treeByUid(Sacredtree::openVersion(BossClock::now()) ?? 0, $uidArr);
            $onlineSort = [];
            $levelSort = [];
            $countryIds = [];
            foreach ($friendList as $key => $val) {
                $m = $members[$val['fuid']] ?? null;
                $friendList[$key]['nickname'] = $m['nickname'] ?? null;
                $friendList[$key]['level'] = isset($m['level']) ? (int)$m['level'] : null;
                $friendList[$key]['countryid'] = isset($m['country']) ? (int)$m['country'] : null;
                $friendList[$key]['country'] = '';
                if ((int)($m['country'] ?? 0) > 0) {
                    $countryIds[] = (int)$m['country'];
                }
                $friendList[$key]['job'] = (int)($m['country'] ?? 0) > 0 && isset($memberJob[$val['fuid']])
                    ? (CountryJob::ROWS[$memberJob[$val['fuid']]]['job'] ?? null)
                    : '';
                $friendList[$key]['tree'] = isset($tree[$val['fuid']]) ? $tree[$val['fuid']]['treeid'] : 0;
                $onlineSort[$key] = $friendList[$key]['online'];
                $levelSort[$key] = $friendList[$key]['level'];
            }
            // 查询国家名称
            if ($countryIds !== []) {
                $names = [];
                foreach (DB::table('country')->select('id', 'name')->whereIn('id', array_values(array_unique($countryIds)))->whereNull('deleted_at')->get() as $row) {
                    $names[(int)$row['id']] = (string)$row['name'];
                }
                foreach ($friendList as $key => $val) {
                    if ($val['countryid'] > 0 && isset($names[$val['countryid']])) {
                        $friendList[$key]['country'] = $names[$val['countryid']];
                    }
                }
            }
            // 排序：在线优先，再按等级
            array_multisort($onlineSort, SORT_DESC, $levelSort, SORT_DESC, $friendList);
        }
        return [
            'status' => 1,
            'all_page' => $list['all_page'] === 0 ? 1 : $list['all_page'],
            'page' => $page,
            'list' => $friendList,
            'all_number' => self::ALL_NUMBER,
            'now_number' => $list['now_number'],
            'online' => $list['online'],
        ];
    }

    /**
     * 按页取好友（get_friend_list：先取在线的，在线的不够一页再按离线补）
     *
     * @param list<int> $online
     * @return array{all_page: int, list: list<int>, online: int, now_number: int}
     */
    private static function friendPage(int $uid, int $page, int $limit, array $online): array
    {
        $offset = ($page - 1) * $limit;
        // 获得好友总页数
        $number = self::friendNumber($uid);
        $allOnline = 0;
        $list = [];
        if ($number > 0) {
            // 先查询在线的好友（在线列表为空时旧版不加条件，全部好友都算在线）
            $query = DB::table('friend')->select('fuid')->where('uid', $uid)->where('status', 1)->whereNull('deleted_at');
            if ($online !== []) {
                $query = $query->whereIn('fuid', $online);
            }
            $result = array_map(static fn (array $r): int => (int)$r['fuid'], $query->orderBy('id')->get());
            $allOnline = count($result);
            $onlineList = array_slice($result, $offset, $limit);
            $now = count($onlineList);
            $offlineList = [];
            if ($now < $limit) {
                // 在线人数不够一页再查询离线好友
                $newOffset = max(0, $offset - $allOnline);
                $placeholders = [];
                $params = ['uid' => $uid];
                foreach ($online as $i => $o) {
                    $placeholders[] = ':o' . $i;
                    $params['o' . $i] = $o;
                }
                $sql = 'SELECT `fuid` FROM `zc_sanguo_friend` WHERE `uid` = :uid AND `status` = 1 AND `deleted_at` IS NULL';
                if ($placeholders !== []) {
                    $sql .= ' AND `fuid` NOT IN (' . implode(', ', $placeholders) . ')';
                }
                $sql .= ' ORDER BY `id` LIMIT ' . $newOffset . ', ' . ($limit - $now);
                $offlineList = array_map(static fn (array $r): int => (int)$r['fuid'], DB::query($sql, $params));
            }
            $list = array_merge($onlineList, $offlineList);
        }
        return ['all_page' => (int)ceil($number / $limit), 'list' => $list, 'online' => $allOnline, 'now_number' => $number];
    }

    /**
     * 好友总数（get_friend_number）
     */
    private static function friendNumber(int $uid): int
    {
        return DB::table('friend')->where('uid', $uid)->where('status', 1)->whereNull('deleted_at')->count();
    }

    /**
     * 修改状态（update_friend_status：1 改为好友，2 删除好友）
     */
    private static function updateStatus(int $uid, int $fuid, int $status, int $now): bool
    {
        return DB::table('friend')->where('uid', $uid)->where('fuid', $fuid)->whereNull('deleted_at')
            ->update(['status' => $status, 'dateline' => $now]) > 0;
    }
}
