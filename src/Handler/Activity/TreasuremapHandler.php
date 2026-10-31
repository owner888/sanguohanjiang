<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Model\Member;
use Hanjiang\Model\MemberActivity;
use Hanjiang\Model\StatPay;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 藏宝图（移植旧版 controllers/treasuremap.php + models/treasuremap_model.php，计划任务 cron::sale_map）
 *
 * 客户端（treasuremappop.js）调用：index / create_team / join_team / exit_team / levelup / refresh / sale。主公 26 级开放。
 * 征收（主公 17 级以上）/ 强征时 30% 概率获得一块藏宝图（getMap，由 CityHandler 调用：部位 1 ~ 3 随机，品级 1 ~ 5 按
 * 60 / 20 / 10 / 5 / 2 的权重），存 own_treasuremap。玩家拿最早获得的一块组队：同品级、不同部位的三块凑齐后三人各得
 * 品级系数（100 / 200 / 400 / 800 / 1600）× 该块记录的主公等级的银币，最后加入的人当场到账，其余人记 member_activity.mapreward、
 * 下次打开界面时到账。队伍存 Redis 列表 treasuremap_team_{队长 uid}，玩家状态 treasuremap_status_{uid}（-1 队伍已解散、
 * 0 空闲、> 0 队伍 id），藏宝图个数缓存 treasuremap_count_{uid}（均 1 天过期）。不在队伍中时可花元宝升品（2 / 4 / 10 / 20）、
 * 花 2 元宝刷新部位、按品级系数（25 / 50 / 100 / 200 / 400）× 主公等级卖银币。每日 7 点（同征收计划任务）把昨天以前获得、
 * 未在队伍中的藏宝图自动卖掉并发信件（TreasuremapCron）。
 *
 * 照旧版保留的写法：组队完成时其余队员的 mapreward 是覆盖写入（上一次未领的奖励会被覆盖）；index 的 team_status 在队伍中
 * 为 1、空闲 0、队伍已解散 -1；sale 只卖最早的一块。依赖未移植：组队完成的春节送福计数 actnewyear_model->add_task_num(20)
 * （节日活动）。不移植：clear_all / clear_map_count（运维口令接口，客户端不调用）、get_team_info（客户端不调用）。
 * 与旧版的差别：队伍的创建 / 加入 / 退出在 MySQL 命名锁 hj_treasuremap 里串行执行（旧版无锁，并发加入可能凑出 4 人队）；
 * 藏宝图删除为软删除。
 */
final class TreasuremapHandler
{
    /** 刷新价格 */
    private const int REFRESH_PRICE = 2;
    /** 功能开启等级 */
    private const int OPEN_LEVEL = 26;
    /** Redis 键过期时间 */
    private const int TTL = 86400;
    /** 队伍 key */
    private const string TEAM_KEY = 'treasuremap_team_';
    /** 用户状态 key（-1 队伍已解散；0 空闲；> 0 队伍 id） */
    private const string STATUS_KEY = 'treasuremap_status_';
    /** 藏宝图个数 key */
    private const string COUNT_KEY = 'treasuremap_count_';
    /** 组队串行用的 MySQL 命名锁 */
    private const string LOCK_NAME = 'hj_treasuremap';
    /** 旧版语言包 treasuremap_lang.php */
    private const array NAMES = [1 => '普通藏宝图', 2 => '高级藏宝图', 3 => '无暇藏宝图', 4 => '完美藏宝图', 5 => '上古藏宝图'];
    /** 组队完成奖励系数（get_reward） */
    private const array REWARD_RULE = [1 => 100, 2 => 200, 3 => 400, 4 => 800, 5 => 1600];
    /** 出售价格系数（get_price） */
    private const array PRICE_RULE = [1 => 25, 2 => 50, 3 => 100, 4 => 200, 5 => 400];
    /** 升级价格（get_levelup_price） */
    private const array LEVELUP_RULE = [1 => 2, 2 => 4, 3 => 10, 4 => 20];

    /**
     * 初始化接口: c=treasuremap&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        return self::serialized($uid, static function () use ($uid): array {
            $return = ['status' => 0];
            $member = self::member($uid);
            if ($member === null || $member['level'] < self::OPEN_LEVEL) {
                // 等级不足，无法进入
                $return['status'] = -1;
                return $return;
            }
            // 获取队伍列表
            $list = self::teamList();
            $team = [];
            $silver = 0;
            $status = self::status($uid);
            if ($status > 0) {
                // 已加入队伍：清除异常队伍状态
                $team = self::teamInfo($status);
                $uidarr = array_map(static fn (array $v): int => (int)$v['uid'], $team['list'] ?? []);
                if (!in_array($uid, $uidarr, true)) {
                    self::updateStatus($uid, 0);
                }
            } elseif ($status === 0) {
                // 空闲：领取组队完成的奖励
                MemberActivity::ensure($uid);
                $silver = (int)(MemberActivity::many([$uid], ['mapreward'])[$uid]['mapreward'] ?? 0);
                if ($silver > 0) {
                    Member::addCurrency($uid, silver: $silver);
                    MemberActivity::update($uid, ['mapreward' => 0]);
                }
            } else {
                // 队伍已解散
                self::updateStatus($uid, 0);
            }
            $map = self::mapInfo($uid, $member['level']);
            $count = self::mapCount($uid);
            $return['status'] = 1;
            $return['team_status'] = $status > 0 ? 1 : $status;
            $return['list'] = $list;
            $return['map'] = $map;
            $return['team'] = $team;
            $return['refresh_price'] = self::REFRESH_PRICE;
            $return['num'] = $count['num'];
            $return['add_silver'] = $silver > 0 ? $silver : 0;
            $return['silver'] = self::memberValue($uid, 'silver');
            return $return;
        });
    }

    /**
     * 创建队伍: c=treasuremap&m=create_team
     */
    public static function create_team(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        return self::serialized($uid, static function () use ($uid): array {
            /**
             * -3 创建队伍信息失败
             * -2 藏宝图不存在
             *  0 失败
             *  1 成功
             *  2 已加入队伍
             */
            $return = ['status' => 0];
            $member = self::member($uid);
            if ($member === null || $member['level'] < self::OPEN_LEVEL) {
                $return['status'] = -1;
                return $return;
            }
            if (self::status($uid) > 0) {
                // 已加入队伍
                $return['status'] = 2;
                return $return;
            }
            // 强制清空队伍信息
            if (self::teamInfo($uid) !== []) {
                self::delTeam($uid);
            }
            $map = self::mapInfo($uid, $member['level']);
            if ($map === []) {
                // 藏宝图不存在
                $return['status'] = -2;
                return $return;
            }
            if (self::addTeam($map) !== 1) {
                // 创建队伍信息失败
                $return['status'] = -3;
                return $return;
            }
            $return['status'] = 1;
            $return['list'] = self::teamList();
            $return['team'] = self::teamInfo($uid);
            return $return;
        });
    }

    /**
     * 加入队伍: c=treasuremap&m=join_team&tid=
     */
    public static function join_team(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $tid = (int)$ctx->input('tid', 0);
        return self::serialized($uid, static function () use ($uid, $tid): array {
            /**
             * -3 获取藏宝图信息失败
             * -2 队伍不存在
             *  0 失败
             *  1 成功
             *  2 已加入队伍
             *  3 部件已存在
             *  4 品级不一致
             */
            $return = ['status' => 0];
            $member = self::member($uid);
            if ($member === null || $member['level'] < self::OPEN_LEVEL) {
                $return['status'] = -1;
                return $return;
            }
            $team = self::teamInfo($tid);
            if ($team === []) {
                // 队伍不存在
                $return['status'] = -2;
                return $return;
            }
            if (self::status($uid) > 0) {
                // 已加入队伍
                $return['status'] = 2;
                return $return;
            }
            $map = self::mapInfo($uid, $member['level']);
            if ($map === []) {
                // 获取藏宝图信息失败
                $return['status'] = -3;
                return $return;
            }
            $idArr = $typeArr = $uidArr = $levelArr = [];
            foreach ($team['list'] as $value) {
                $idArr[] = (int)$value['id'];
                $typeArr[] = (int)$value['type'];
                $uidArr[] = (int)$value['uid'];
                $levelArr[(int)$value['uid']] = (int)$value['level'];
            }
            if ($map['quality'] !== (int)$team['quality']) {
                // 品级不一致
                $return['status'] = 4;
                return $return;
            }
            if (in_array($uid, $uidArr, true)) {
                // 已加入队伍
                $return['status'] = 2;
                return $return;
            }
            if (in_array($map['type'], $typeArr, true)) {
                // 部件已存在
                $return['status'] = 3;
                return $return;
            }
            $finish = $silver = 0;
            $size = count($team['list']);
            if ($size === 1) {
                // 加入队伍
                self::updateTeam($uid, $tid, $map);
                $team = self::teamInfo($tid);
            } elseif ($size === 2) {
                // 完成获取奖励
                $finish = 1;
                $idArr[] = $map['id'];
                $levelArr[$map['uid']] = $map['level'];
                foreach ($levelArr as $memberUid => $level) {
                    // 获取奖励银币
                    $silver = self::reward((int)$team['quality'], $level);
                    if ($memberUid === $map['uid']) {
                        Member::addCurrency($memberUid, silver: $silver);
                    } else {
                        MemberActivity::update($memberUid, ['mapreward' => $silver]);
                    }
                    // 状态置为 0 空闲
                    self::updateStatus($memberUid, 0);
                }
                // 删除队伍
                self::delTeam($tid);
                // 删除藏宝图，扣除藏宝图总数
                if (self::delMap($idArr) > 0) {
                    foreach (array_keys($levelArr) as $memberUid) {
                        self::subMap($memberUid);
                        // 依赖未移植：春节送福活动计数 actnewyear_model->add_task_num($version, 20, $uid)（节日活动）
                    }
                }
                $map = self::mapInfo($uid, $member['level']);
            } else {
                return $return;
            }
            $count = self::mapCount($uid);
            $return['status'] = 1;
            $return['finish'] = $finish;
            $return['map'] = $finish > 0 ? $map : [];
            $return['team'] = $size < 2 ? $team : [];
            $return['list'] = self::teamList();
            $return['add_silver'] = $silver;
            $return['silver'] = self::memberValue($uid, 'silver');
            $return['num'] = $count['num'];
            return $return;
        });
    }

    /**
     * 退出队伍: c=treasuremap&m=exit_team
     */
    public static function exit_team(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        return self::serialized($uid, static function () use ($uid): array {
            /**
             * -3 队伍不存在
             * -2 队伍已解散
             *  0 失败
             *  1 成功
             */
            $return = ['status' => 0];
            $member = self::member($uid);
            if ($member === null || $member['level'] < self::OPEN_LEVEL) {
                $return['status'] = -1;
                return $return;
            }
            $id = self::status($uid);
            if ($id <= 0) {
                self::updateStatus($uid, 0);
                // 队伍已解散
                $return['status'] = -2;
                return $return;
            }
            $team = self::teamInfo($id);
            if ($team === []) {
                // 队伍不存在
                self::updateStatus($uid, 0);
                $return['status'] = -3;
                return $return;
            }
            $type = $mapId = 0;
            $size = count($team['list']);
            $idArr = [];
            foreach ($team['list'] as $key => $value) {
                if ((int)$value['uid'] === $uid) {
                    if ($key === 0) {
                        if ($size === 1) {
                            // 解散
                            $type = 1;
                        }
                        if ($size > 1) {
                            // 队长退出
                            $type = 3;
                        }
                    }
                    if ($key === 1) {
                        // 队员退出
                        $type = 2;
                    }
                    $mapId = (int)$value['id'];
                }
                $idArr[] = (int)$value['id'];
            }
            if ($type === 1) {
                // 解散
                self::delTeam($uid);
                self::updateStatus($uid, 0);
                foreach ($team['list'] as $value) {
                    if ((int)$value['uid'] !== $uid) {
                        self::updateStatus((int)$value['uid'], -1);
                    }
                }
                self::updateMap($idArr, ['isuse' => 0]);
            } elseif ($type === 2) {
                // 队员退出
                self::exitTeam((int)$team['id']);
                self::updateStatus($uid, 0);
                self::updateMap([$mapId], ['isuse' => 0]);
            } elseif ($type === 3) {
                // 队长退出：第二个人的藏宝图重新建队
                self::delTeam($uid);
                self::updateStatus($uid, 0);
                self::updateMap($idArr, ['isuse' => 0]);
                self::addTeam($team['list'][1]);
            } else {
                $return['status'] = -4;
                return $return;
            }
            $return['list'] = self::teamList();
            $return['status'] = 1;
            return $return;
        });
    }

    /**
     * 升级藏宝图: c=treasuremap&m=levelup
     */
    public static function levelup(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        return self::serialized($uid, static function () use ($uid): array {
            /**
             * -5 更新失败
             * -4 扣除元宝失败
             * -3 没有藏宝图
             * -2 队伍中无法升级
             *  0 失败
             *  1 成功
             *  2 藏宝图已满级
             *  3 元宝不足
             */
            $return = ['status' => 0];
            $member = self::member($uid);
            if ($member === null || $member['level'] < self::OPEN_LEVEL) {
                $return['status'] = -1;
                return $return;
            }
            if (self::status($uid) > 0) {
                // 队伍中无法升级
                $return['status'] = -2;
                return $return;
            }
            $map = self::mapInfo($uid, $member['level']);
            if ($map === []) {
                // 没有藏宝图
                $return['status'] = -3;
                return $return;
            }
            if ($map['ismax']) {
                // 已满级
                $return['status'] = 2;
                return $return;
            }
            if (!Member::deductCurrency($uid, gold: $map['levelup_price'])) {
                // 元宝不足
                $return['status'] = 3;
                return $return;
            }
            $quality = $map['quality'] + 1;
            $result = [
                'quality' => $quality,
                'name' => self::NAMES[$quality] ?? null,
                'sale_price' => self::price($quality, $member['level']),
            ];
            if ($quality > 4) {
                $result['ismax'] = 1;
            } else {
                $result['levelup_price'] = self::LEVELUP_RULE[$quality] ?? 0;
                $result['ismax'] = 0;
            }
            if (self::updateMap([$map['id']], ['quality' => $quality]) !== 1) {
                // 更新失败
                $return['status'] = -5;
                return $return;
            }
            if ($result['ismax'] < 1) {
                // 消费统计记录（旧版记的是升级后的下一级价格）
                StatPay::record($uid, 'map_levelup', $result['levelup_price']);
            }
            $return['status'] = 1;
            $return['map'] = $result;
            $return['gold'] = self::memberValue($uid, 'gold');
            return $return;
        });
    }

    /**
     * 部位刷新: c=treasuremap&m=refresh
     */
    public static function refresh(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        return self::serialized($uid, static function () use ($uid): array {
            /**
             * -5 更新藏宝图失败
             * -4 扣除元宝失败
             * -3 没有藏宝图
             * -2 队伍中无法刷新
             *  0 失败
             *  1 成功
             *  2 元宝不足
             */
            $return = ['status' => 0];
            $member = self::member($uid);
            if ($member === null || $member['level'] < self::OPEN_LEVEL) {
                $return['status'] = -1;
                return $return;
            }
            if (self::status($uid) > 0) {
                $return['status'] = -2;
                return $return;
            }
            $map = self::mapInfo($uid, $member['level']);
            if ($map === []) {
                $return['status'] = -3;
                return $return;
            }
            if (!Member::deductCurrency($uid, gold: self::REFRESH_PRICE)) {
                // 元宝不足
                $return['status'] = 2;
                return $return;
            }
            $type = self::refreshType($map['type']);
            if (self::updateMap([$map['id']], ['type' => $type]) !== 1) {
                $return['status'] = -5;
                return $return;
            }
            // 消费统计记录
            StatPay::record($uid, 'map_refresh', self::REFRESH_PRICE);
            $return['status'] = 1;
            $return['type'] = $type;
            $return['gold'] = self::memberValue($uid, 'gold');
            return $return;
        });
    }

    /**
     * 出售: c=treasuremap&m=sale
     */
    public static function sale(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        return self::serialized($uid, static function () use ($uid): array {
            /**
             * -4 加银币失败
             * -3 没有藏宝图
             * -2 队伍中无法出售
             *  0 失败
             *  1 成功
             */
            $return = ['status' => 0];
            $member = self::member($uid);
            if ($member === null || $member['level'] < self::OPEN_LEVEL) {
                $return['status'] = -1;
                return $return;
            }
            if (self::status($uid) > 0) {
                $return['status'] = -2;
                return $return;
            }
            $map = self::mapInfo($uid, $member['level']);
            if ($map === [] || $map['uid'] !== $uid) {
                $return['status'] = -3;
                return $return;
            }
            $price = self::price($map['quality'], $member['level']);
            // 删除藏宝图，减少藏宝图个数，增加银币
            if (self::delMap([$map['id']]) > 0) {
                self::subMap($map['uid']);
                Member::addCurrency($uid, silver: $price);
            }
            $map = self::mapInfo($uid, $member['level']);
            $count = self::mapCount($uid);
            $return['status'] = 1;
            $return['map'] = $map;
            $return['add_silver'] = $price;
            $return['silver'] = self::memberValue($uid, 'silver');
            $return['num'] = $count['num'];
            return $return;
        });
    }

    /**
     * 获取藏宝图部件（get_map：征收 / 强征时调用；30% 概率获得一块，部位随机、品级按权重）
     *
     * @param array{uid: int|string, nickname: string} $member
     * @return array<string, mixed> 获得的藏宝图（含 id 与名称），没有获得为空数组
     */
    public static function getMap(array $member, int $now): array
    {
        if (self::roll([0 => 70, 1 => 30]) === 0) {
            return [];
        }
        $type = mt_rand(1, 3);
        $quality = self::roll([1 => 60, 2 => 20, 3 => 10, 4 => 5, 5 => 2]);
        $uid = (int)$member['uid'];
        $data = [
            'uid' => $uid,
            'nickname' => (string)$member['nickname'],
            'type' => $type,
            'quality' => $quality,
            'dateline' => $now,
        ];
        $id = (int)DB::table('own_treasuremap')->insert($data);
        if ($id <= 0) {
            return [];
        }
        self::addMap($uid);
        $data['id'] = $id;
        $data['name'] = self::NAMES[$quality];
        return $data;
    }

    /**
     * 计划任务：出售昨天以前获得、未在队伍中的藏宝图（cron::sale_map，跟征收一起每日 7 点执行）
     *
     * 按每块品级系数 × 玩家当前等级加银币并发信件；有人卖出时删除全部这些藏宝图（旧版条件），最后清空藏宝图个数缓存。
     *
     * @return int 卖出的玩家数
     */
    public static function saleMap(int $now): int
    {
        $yesterday = strtotime(date('Y-m-d', $now));
        $uids = array_map('intval', array_column(
            DB::query('SELECT DISTINCT `uid` FROM `zc_sanguo_own_treasuremap` WHERE `uid` > 0 AND `deleted_at` IS NULL'),
            'uid'
        ));
        $sold = [];
        if ($uids !== []) {
            $levels = [];
            foreach (DB::table('member')->select('uid', 'level')->whereIn('uid', $uids)->whereNull('deleted_at')->get() as $row) {
                $levels[(int)$row['uid']] = (int)$row['level'];
            }
            foreach ($levels as $uid => $level) {
                $count = 0;
                $price = 0;
                foreach (DB::table('own_treasuremap')->select('quality')->where('uid', $uid)->where('isuse', 0)
                    ->where('dateline', '<=', $yesterday)->whereNull('deleted_at')->get() as $map) {
                    $count++;
                    $price += self::price((int)$map['quality'], $level);
                }
                if ($price > 0) {
                    Member::addCurrency($uid, silver: $price);
                    $sold[] = $uid;
                    MessageHandler::sendSystemMessage([$uid], 1, self::LANG_SYSTEM_TITLE, sprintf(self::LANG_CRON_SALE_MAP, $count, $price));
                }
            }
        }
        if ($sold !== []) {
            DB::table('own_treasuremap')->where('isuse', 0)->where('dateline', '<=', $yesterday)->whereNull('deleted_at')
                ->update(['deleted_at' => date('Y-m-d H:i:s')]);
        }
        self::clearMapCount();
        return count($sold);
    }

    /** 系统信件标题（message_model->send_message type 1） */
    private const string LANG_SYSTEM_TITLE = '系统通知';
    /** 旧版语言包 cron_sale_map */
    private const string LANG_CRON_SALE_MAP = '昨日剩余的%s件藏宝图，系统已帮您出售，共获得%s银币';

    /**
     * 在玩家行锁 + 藏宝图命名锁里执行（队伍是全服共享的 Redis 列表）
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private static function serialized(int $uid, callable $callback): mixed
    {
        $got = (int)(DB::query('SELECT GET_LOCK(:name, 10) AS `l`', ['name' => self::LOCK_NAME])[0]['l'] ?? 0);
        try {
            return Member::locked($uid, $callback);
        } finally {
            if ($got === 1) {
                DB::query('SELECT RELEASE_LOCK(:name) AS `l`', ['name' => self::LOCK_NAME]);
            }
        }
    }

    /**
     * @return array{uid: int, nickname: string, level: int}|null
     */
    private static function member(int $uid): ?array
    {
        if ($uid <= 0) {
            return null;
        }
        $row = DB::table('member')->select('uid', 'nickname', 'level')->where('uid', $uid)->whereNull('deleted_at')->first();
        return $row === null ? null : ['uid' => (int)$row['uid'], 'nickname' => (string)$row['nickname'], 'level' => (int)$row['level']];
    }

    private static function memberValue(int $uid, string $column): int
    {
        return (int)DB::table('member')->where('uid', $uid)->value($column);
    }

    /**
     * 玩家最早获得的藏宝图（get_map_info：带名称、当前主公等级、出售价、升级价 / 满级标识），没有为空数组
     *
     * @return array<string, mixed>
     */
    private static function mapInfo(int $uid, int $level): array
    {
        $row = DB::table('own_treasuremap')->select('id', 'uid', 'nickname', 'type', 'quality', 'dateline', 'isuse')
            ->where('uid', $uid)->whereNull('deleted_at')->orderBy('dateline', 'ASC')->orderBy('id', 'ASC')->first();
        if ($row === null) {
            return [];
        }
        foreach ($row as $key => $value) {
            $row[$key] = $key === 'nickname' ? (string)$value : (int)$value;
        }
        $row['name'] = self::NAMES[$row['quality']] ?? null;
        $row['level'] = $level;
        $row['sale_price'] = self::price($row['quality'], $level);
        if ($row['quality'] > 4) {
            $row['ismax'] = 1;
        } else {
            $row['levelup_price'] = self::LEVELUP_RULE[$row['quality']] ?? 0;
            $row['ismax'] = 0;
        }
        return $row;
    }

    /**
     * 更新藏宝图（update_map）：1 有修改，0 没有
     *
     * @param list<int> $ids
     * @param array<string, int> $data
     */
    private static function updateMap(array $ids, array $data): int
    {
        return DB::table('own_treasuremap')->whereIn('id', $ids)->whereNull('deleted_at')->update($data) > 0 ? 1 : 0;
    }

    /**
     * 删除藏宝图（del_map，软删除）：1 有删除，0 没有
     *
     * @param list<int> $ids
     */
    private static function delMap(array $ids): int
    {
        return DB::table('own_treasuremap')->whereIn('id', $ids)->whereNull('deleted_at')
            ->update(['deleted_at' => date('Y-m-d H:i:s')]) > 0 ? 1 : 0;
    }

    /**
     * 玩家藏宝图总数（get_map_count：有缓存用缓存，否则查库并缓存 1 天）
     *
     * @return array{cache: int, num: int}
     */
    private static function mapCount(int $uid): array
    {
        $value = Redis::get(self::COUNT_KEY . $uid);
        if ($value !== null) {
            return ['cache' => 1, 'num' => (int)$value];
        }
        $num = DB::table('own_treasuremap')->where('uid', $uid)->whereNull('deleted_at')->count();
        Redis::set(self::COUNT_KEY . $uid, $num, self::TTL);
        return ['cache' => 0, 'num' => $num];
    }

    /**
     * 增加藏宝图个数（add_map：有缓存时缓存 + 1）
     */
    private static function addMap(int $uid): void
    {
        $count = self::mapCount($uid);
        if ($count['cache'] === 1) {
            Redis::set(self::COUNT_KEY . $uid, $count['num'] + 1, self::TTL);
        }
    }

    /**
     * 减少藏宝图个数（sub_map：有缓存时缓存 - 1，不小于 0）
     */
    private static function subMap(int $uid): void
    {
        $count = self::mapCount($uid);
        if ($count['cache'] === 1) {
            Redis::set(self::COUNT_KEY . $uid, $count['num'] > 1 ? $count['num'] - 1 : 0, self::TTL);
        }
    }

    /**
     * 清空所有玩家的藏宝图个数缓存（clear_map_count）
     */
    private static function clearMapCount(): void
    {
        $prefix = (string)Redis::instance()->getOption(\Redis::OPT_PREFIX);
        $keys = [];
        foreach (Redis::keys(self::COUNT_KEY . '*') as $key) {
            $keys[] = substr((string)$key, strlen($prefix));
        }
        if ($keys !== []) {
            Redis::del(...$keys);
        }
    }

    /**
     * 添加队伍（add_team：队长 uid 的队伍键不存在时写入队长的藏宝图、过期 1 天并把队长状态置为自己的 uid；
     * 不论成败都把这块藏宝图标为使用中）
     *
     * @param array<string, mixed> $map
     * @return int 写入后的队伍长度，队伍已存在为 0
     */
    private static function addTeam(array $map): int
    {
        $uid = (int)$map['uid'];
        $key = self::TEAM_KEY . $uid;
        $return = 0;
        if ((int)Redis::ttl($key) < 0) {
            $return = (int)Redis::rPush($key, (string)json_encode($map, JSON_UNESCAPED_UNICODE));
            Redis::expire($key, self::TTL);
            self::updateStatus($uid, $uid);
        }
        self::updateMap([(int)$map['id']], ['isuse' => 1]);
        return $return;
    }

    /**
     * 加入组队（update_team：队伍还在时写入并把自己的状态置为队伍 id；不论成败都把这块藏宝图标为使用中）
     *
     * @param array<string, mixed> $map
     */
    private static function updateTeam(int $uid, int $id, array $map): void
    {
        $key = self::TEAM_KEY . $id;
        if ((int)Redis::ttl($key) > 0) {
            Redis::rPush($key, (string)json_encode($map, JSON_UNESCAPED_UNICODE));
            self::updateStatus($uid, $id);
        }
        self::updateMap([(int)$map['id']], ['isuse' => 1]);
    }

    /**
     * 队员退出（exit_team：弹出队伍最后一人）
     */
    private static function exitTeam(int $id): void
    {
        $key = self::TEAM_KEY . $id;
        if ((int)Redis::ttl($key) > 0) {
            Redis::rPop($key);
        }
    }

    private static function delTeam(int $id): void
    {
        Redis::del(self::TEAM_KEY . $id);
    }

    /**
     * 队伍信息（get_team_info：id 为队长 uid、quality、list 为队员的藏宝图），没有为空数组
     *
     * @return array<string, mixed>
     */
    private static function teamInfo(int $uid): array
    {
        $return = [];
        $team = Redis::lRange(self::TEAM_KEY . $uid, 0, -1);
        if (!is_array($team) || $team === []) {
            return $return;
        }
        foreach (array_values($team) as $key => $value) {
            $valueArr = json_decode((string)$value, true);
            if (!is_array($valueArr)) {
                continue;
            }
            if ($key === 0) {
                $return['id'] = (int)$valueArr['uid'];
            }
            $return['quality'] = $valueArr['quality'];
            $return['list'][$key] = $valueArr;
        }
        return $return;
    }

    /**
     * 全服队伍列表（get_team_list：按品级降序）
     *
     * @return list<array<string, mixed>>
     */
    private static function teamList(): array
    {
        $prefix = (string)Redis::instance()->getOption(\Redis::OPT_PREFIX) . self::TEAM_KEY;
        $return = [];
        $sortQuality = [];
        foreach (Redis::keys(self::TEAM_KEY . '*') as $key) {
            $info = self::teamInfo((int)substr((string)$key, strlen($prefix)));
            if ($info !== []) {
                $return[] = $info;
                $sortQuality[] = (int)$info['quality'];
            }
        }
        if ($return !== []) {
            array_multisort($sortQuality, SORT_DESC, $return);
        }
        return $return;
    }

    /**
     * 用户状态（get_status：-1 队伍已解散；0 空闲；> 0 队伍 id）
     */
    private static function status(int $uid): int
    {
        return (int)(Redis::get(self::STATUS_KEY . $uid) ?? 0);
    }

    private static function updateStatus(int $uid, int $status): void
    {
        Redis::set(self::STATUS_KEY . $uid, $status, self::TTL);
    }

    /**
     * 组队完成奖励银币（get_reward：品级系数 × 等级，品级不对为 -1）
     */
    private static function reward(int $quality, int $level): int
    {
        return isset(self::REWARD_RULE[$quality]) ? self::REWARD_RULE[$quality] * $level : -1;
    }

    /**
     * 出售价格（get_price：品级系数 × 等级，品级不对为 -1）
     */
    private static function price(int $quality, int $level): int
    {
        return isset(self::PRICE_RULE[$quality]) ? self::PRICE_RULE[$quality] * $level : -1;
    }

    /**
     * 刷新部位（refresh：去掉当前部位，另外两个各 10 权重）
     */
    private static function refreshType(int $type): int
    {
        $typeArr = [1 => 10, 2 => 10, 3 => 10];
        unset($typeArr[$type]);
        $rand = mt_rand(1, array_sum($typeArr));
        $cursor = 0;
        $return = 0;
        for ($i = 1; $i < 4; $i++) {
            if (isset($typeArr[$i])) {
                $cursor += $typeArr[$i];
                $return = $i;
            }
            if ($cursor >= $rand) {
                break;
            }
        }
        return $return;
    }

    /**
     * 概率计算（_roll：按键从小到大累计权重，返回第一个累计值不小于随机数的键）
     *
     * @param array<int, int> $rule
     */
    private static function roll(array $rule): int
    {
        $rand = mt_rand(1, array_sum($rule));
        $cursor = 0;
        $return = 0;
        for ($i = min(array_keys($rule)); $i <= max(array_keys($rule)); $i++) {
            $cursor += $rule[$i];
            $return = $i;
            if ($cursor >= $rand) {
                break;
            }
        }
        return $return;
    }
}
