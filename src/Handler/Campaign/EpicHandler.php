<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Campaign;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\MasterData\Epic;
use Hanjiang\MasterData\EpicChip;
use Hanjiang\Model\DailyTask;
use Hanjiang\Model\Member;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 天下史诗（移植旧版 controllers/epic.php + models/epic_model.php，路径相对旧版 s1/application/）
 *
 * 拼图玩法，没有战斗：选一幅史诗，每次刷新按残卷权重（EpicChip.rate）随机 5 个位置的残卷，可锁定位置不参与刷新；
 * 5 个位置正好是该史诗的 5 张残卷（按 id 顺序）即完成，得声望（Epic.reward）；也可花元宝（Epic.price）强行领取。
 * 每日刷新 / 锁定次数按 VIP 重置，次数不够可用元宝购买（购买的次数不重置）。
 *
 * 配置：Epic（史诗：reward 声望、price 强行领取元宝）、EpicChip（残卷：epicid、rate 权重）。
 * 玩家存储：epic_log（每人一行，同旧版 zc_sanguo_epic_log）。
 *
 * 客户端调用：index、select_epic、refresh、force、buy_lock_times、buy_refresh_times（epicpop.js）。
 *
 * 刷新计日常任务 14（主公 41 级以上）；元宝 / 声望增减由 Member 记流水，元宝消耗另计日常任务 5。
 *
 * 依赖未移植：sub_gold 的翻牌活动计数（flop，旧版数据中已结束）。
 */
final class EpicHandler
{
    /** 功能开放等级（主公等级 >= 150） */
    private const int OPEN_LEVEL = 150;

    /** 购买刷新次数价格 */
    private const int REFRESH_PRICE = 10;

    /** 购买锁定次数价格 */
    private const int LOCK_PRICE = 5;

    /** index 返回的 times（旧版写死 10） */
    private const int TIMES = 10;

    /** 旧版 zc_sanguo_epic_log 的字段（响应 member 只带这些，不带新版的三个时间字段） */
    private const array LOG_COLUMNS = ['uid', 'refreshtimes', 'buy_refreshtimes', 'locktimes', 'buy_locktimes', 'epicid', 'site1', 'site2', 'site3', 'site4', 'site5', 'lock_site', 'dateline'];

    /** 拼图完成 / 强行领取后清空的数据 */
    private const array CLEAR_DATA = [
        'site1' => 0,
        'site2' => 0,
        'site3' => 0,
        'site4' => 0,
        'site5' => 0,
        'epicid' => 0,
        'lock_site' => '',
    ];

    /**
     * 初始化: c=epic&m=index
     *
     * status：-1 功能未开启；-2 获取用户数据失败；-3 获取史诗列表失败；-4 获取残卷失败；1 成功
     */
    public static function index(Context $ctx): array
    {
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        // 判断功能是否开启
        if (!self::isOpen($member)) {
            $return['status'] = -1;
            return $return;
        }
        $now = time();
        // 获得用户数据
        $log = DB::transaction(static fn(): ?array => self::getMemberInfo($member, $now));
        if ($log === null) {
            $return['status'] = -2;
            return $return;
        }

        // 获取史诗列表
        $epic = array_values(Epic::ROWS);

        // 强行领取价格
        $getPrice = 0;
        foreach ($epic as $val) {
            if ($val['id'] === $log['epicid']) {
                $getPrice = $val['price'];
            }
        }

        // 被锁定的位置
        $lock = explode(',', $log['lock_site']);

        if ($log['epicid'] > 0) {
            // 获取所属自己的史诗的所有残卷
            $chip = self::getEpicChip($log['epicid']);
            if ($chip === []) {
                $return['status'] = -4;
                return $return;
            }

            // 判断是否有要提示锁定的图片
            $i = 1;
            foreach ($chip as $val) {
                if (!in_array($i, $lock) && $log['site' . $i] === $val['id']) {
                    $log['site' . $i . '_lock'] = 2;
                }
                $i++;
            }
        }

        $return['status'] = 1;
        $return['get_price'] = $getPrice;
        $return['times'] = self::TIMES;
        $return['list'] = $epic;
        $return['member'] = self::output($log);
        $return['refreshprice'] = self::REFRESH_PRICE;
        $return['lockprice'] = self::LOCK_PRICE;
        return $return;
    }

    /**
     * 选择背景图: c=epic&m=select_epic（id 史诗 id）
     *
     * status：-1 功能未开启；-2 id 为空；-3 获取用户数据失败；-4 已选择过史诗；-5 史诗不存在；-6 修改失败；1 成功
     */
    public static function select_epic(Context $ctx): array
    {
        $return = ['status' => 0];
        // 史诗id
        $id = (int)$ctx->input('id', 0);
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        // 判断功能是否开启
        if (!self::isOpen($member)) {
            $return['status'] = -1;
            return $return;
        }
        // 判断id是否有值
        if ($id === 0) {
            $return['status'] = -2;
            return $return;
        }
        $now = time();

        return DB::transaction(static function () use ($member, $id, $now, $return): array {
            // 获取自己的信息（行锁：同一玩家的并发请求依次执行）
            $log = self::getMemberInfo($member, $now);
            if ($log === null) {
                $return['status'] = -3;
                return $return;
            }
            // 判断自己是否已经选择过图了
            if ($log['epicid'] > 0) {
                $return['status'] = -4;
                return $return;
            }
            // 获取图的信息
            if (!isset(Epic::ROWS[$id])) {
                $return['status'] = -5;
                return $return;
            }
            // 修改用户信息
            if (self::updateMemberInfo((int)$member['uid'], ['epicid' => $id], $now) !== 1) {
                $return['status'] = -6;
                return $return;
            }
            $return['status'] = 1;
            $return['epicid'] = $id;
            return $return;
        });
    }

    /**
     * 刷新列表: c=epic&m=refresh（l 锁定的位置，逗号分隔）
     *
     * status：-1 功能未开启；-2 获取用户数据失败；-3 未选择史诗；-4 5 个位置全部锁定；-5 刷新次数不足；
     * -6 锁定次数不足；-7 获取残卷列表失败；-8 扣除锁定次数失败；-9 扣除刷新次数失败；-10 获取史诗残卷失败；1 成功
     */
    public static function refresh(Context $ctx): array
    {
        $return = [
            'status' => 0,
            'complete' => 0,
            'add_reputation' => 0,
            'all_reputation' => 0,
        ];
        // 已锁定的图
        $locksite = (string)$ctx->input('l', '');
        $lock = explode(',', $locksite);
        $locktimes = $locksite !== '' && $locksite !== '0' ? count($lock) : 0;

        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        // 判断功能是否开启
        if (!self::isOpen($member)) {
            $return['status'] = -1;
            return $return;
        }
        $uid = (int)$member['uid'];
        $now = time();

        $result = DB::transaction(static function () use ($member, $uid, $now, $locksite, $lock, $locktimes, $return): array {
            // 获得用户数据（行锁：同一玩家的并发刷新依次扣次数）
            $log = self::getMemberInfo($member, $now);
            if ($log === null) {
                $return['status'] = -2;
                return $return;
            }
            // 判断是否选择背景
            if ($log['epicid'] === 0) {
                $return['status'] = -3;
                return $return;
            }
            // 判断是否所有图都锁定了
            if ($locktimes === 5) {
                $return['status'] = -4;
                return $return;
            }
            // 判断刷新次数是否足够
            if ($log['refreshtimes'] + $log['buy_refreshtimes'] <= 0) {
                $return['status'] = -5;
                return $return;
            }
            // 判断锁定次数是否足够
            if ($log['locktimes'] + $log['buy_locktimes'] < $locktimes) {
                $return['status'] = -6;
                return $return;
            }
            // 获取所有残卷列表
            $list = self::getEpicChip();

            // 开始刷新（旧版 $min 与数组比较恒不成立，下限恒为 0；mt_rand 的浮点上限按 PHP 5 截断取整）
            $number = 0;
            foreach ($list as $val) {
                $number += $val['rate'];
            }
            $site = [];
            for ($i = 1; $i <= 5; $i++) {
                if (!in_array($i, $lock)) {
                    $randnum = mt_rand(0, (int)$number);
                    $nowRate = 0;
                    foreach ($list as $val) {
                        $nowRate += $val['rate'];
                        if ($randnum <= $nowRate) {
                            $site['site' . $i] = $val['id'];
                            $log['site' . $i] = $val['id'];
                            break;
                        }
                    }
                }
            }

            // 扣除锁定次数
            if ($locktimes > 0) {
                if ($log['locktimes'] < $locktimes) {
                    // 扣除免费次数和购买的次数
                    $data = [
                        'locktimes' => 0,
                        'buy_locktimes' => ($log['buy_locktimes'] - $locktimes) + $log['locktimes'],
                    ];
                } else {
                    // 扣除免费次数
                    $data = ['locktimes' => $log['locktimes'] - $locktimes];
                }
                $status = self::updateMemberInfo($uid, $data, $now);
                $log = array_merge($log, $data);
                // 判断次数是否修改成功
                if ($status !== 1) {
                    $return['status'] = -8;
                    return $return;
                }
            }

            // 扣除刷新次数
            if ($log['refreshtimes'] !== 0) {
                // 扣除免费次数
                $data = ['refreshtimes' => $log['refreshtimes'] - 1];
            } else {
                // 扣除购买的次数
                $data = ['buy_refreshtimes' => $log['buy_refreshtimes'] - 1];
            }
            $status = self::updateMemberInfo($uid, $data, $now);
            $log = array_merge($log, $data);
            // 判断次数是否修改成功
            if ($status !== 1) {
                $return['status'] = -9;
                return $return;
            }
            // 获取所属自己的史诗的所有残卷
            $chip = self::getEpicChip($log['epicid']);
            if ($chip === []) {
                $return['status'] = -10;
                return $return;
            }
            // 判断拼图是否已完成
            $complete = 1;
            $i = 1;
            foreach ($chip as $val) {
                if ($log['site' . $i] !== $val['id']) {
                    $complete = 0;
                }
                // 判断是否有要提示锁定的图片
                if (!in_array($i, $lock) && $log['site' . $i] === $val['id']) {
                    $log['site' . $i . '_lock'] = 2;
                }
                $i++;
            }

            // 如果拼图完成，所有拼图清空，添加奖励
            if ($complete === 1) {
                self::updateMemberInfo($uid, self::CLEAR_DATA, $now);
                // 获取史诗信息，添加奖励
                $reward = Epic::ROWS[$log['epicid']]['reward'];
                Member::addCurrency($uid, reputation: $reward);
                $return['add_reputation'] = $reward;
                $log = array_merge($log, self::CLEAR_DATA);
            } else {
                // 记录刷新后的数据
                $site['lock_site'] = $locksite;
                // lock_site 为 varchar(20)：旧版 MySQL 非严格模式截断写入，这里同样截断（响应仍是传入值，同旧版）
                self::updateMemberInfo($uid, ['lock_site' => substr($locksite, 0, 20)] + $site, $now);
                $log = array_merge($log, $site);
            }

            // 日常任务(14是天下史诗刷新对应的类型)
            if ((int)$member['level'] >= DailyTask::OPEN_LEVEL) {
                DailyTask::action($uid, DailyTask::TYPE_EPIC);
            }

            $return['status'] = 1;
            $return['member'] = self::output($log);
            $return['complete'] = $complete;
            return $return;
        });
        if ($result['status'] === 1) {
            $return = $result;
            $return['all_reputation'] = self::memberValue($uid, 'reputation');
        } else {
            $return['status'] = $result['status'];
        }
        return $return;
    }

    /**
     * 强行领取: c=epic&m=force
     *
     * status：-1 功能未开启；-2 获取用户数据失败；-3 未选择史诗；-4 史诗不存在；-5 元宝不足；1 成功
     */
    public static function force(Context $ctx): array
    {
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        // 判断功能是否开启
        if (!self::isOpen($member)) {
            $return['status'] = -1;
            return $return;
        }
        $uid = (int)$member['uid'];
        $now = time();

        $result = DB::transaction(static function () use ($member, $uid, $now, $return): array {
            // 获取member信息（行锁：同一玩家的并发领取只领一次）
            $log = self::getMemberInfo($member, $now);
            if ($log === null) {
                $return['status'] = -2;
                return $return;
            }
            // 判断是否选择过史诗
            if ($log['epicid'] === 0) {
                $return['status'] = -3;
                return $return;
            }
            // 获取史诗信息
            $epic = Epic::ROWS[$log['epicid']] ?? null;
            if ($epic === null) {
                $return['status'] = -4;
                return $return;
            }
            // 判断元宝是否足够，扣除元宝（原子扣减，余额不足时同旧版返回 -5）
            if (!Member::deductCurrency($uid, gold: $epic['price'])) {
                $return['status'] = -5;
                return $return;
            }
            // 添加奖励
            Member::addCurrency($uid, reputation: $epic['reward']);

            // 把所有图的信息清空
            self::updateMemberInfo($uid, self::CLEAR_DATA, $now);
            $log = array_merge($log, self::CLEAR_DATA);

            $return['status'] = 1;
            $return['add'] = $epic['reward'];
            $return['member'] = self::output($log, false);
            return $return;
        });
        if ($result['status'] !== 1) {
            return $result;
        }
        return [
            'status' => 1,
            'add' => $result['add'],
            'reputation' => self::memberValue($uid, 'reputation'),
            'all_gold' => self::memberValue($uid, 'gold'),
            'member' => $result['member'],
        ];
    }

    /**
     * 购买锁定次数: c=epic&m=buy_lock_times（t 为 1 时只返回价格；num 购买次数，默认 1）
     *
     * status：-1 功能未开启；-2 获取用户数据失败；-3 元宝不足；1 成功
     */
    public static function buy_lock_times(Context $ctx): array
    {
        $return = ['status' => 0];
        $type = (int)$ctx->input('t', 0);
        $num = (int)$ctx->input('num', 0);
        $num = $num > 0 ? $num : 1;
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        // 判断功能是否开启
        if (!self::isOpen($member)) {
            $return['status'] = -1;
            return $return;
        }
        $uid = (int)$member['uid'];
        $now = time();

        $result = DB::transaction(static function () use ($member, $uid, $now, $type, $num, $return): array {
            // 获取member信息
            $log = self::getMemberInfo($member, $now);
            if ($log === null) {
                $return['status'] = -2;
                return $return;
            }
            // 价格（num 极大时乘积溢出为浮点，元宝必然不足）
            $price = self::LOCK_PRICE * $num;

            // 购买(type为假为购买，1就只返回价格)
            if ($type !== 1) {
                // 判断元宝是否足够，扣除元宝
                if (!is_int($price) || !Member::deductCurrency($uid, gold: $price)) {
                    $return['status'] = -3;
                    return $return;
                }
                // 添加次数
                $data = ['buy_locktimes' => $log['buy_locktimes'] + $num];
                self::updateMemberInfo($uid, $data, $now);
                $log['buy_locktimes'] = $data['buy_locktimes'];
            }

            $return['status'] = 1;
            $return['price'] = $price;
            $return['buy_times'] = $num;
            $return['locktimes'] = $log['buy_locktimes'] + $log['locktimes'];
            return $return;
        });
        if ($result['status'] === 1) {
            $result['all_gold'] = self::memberValue($uid, 'gold');
        }
        return $result;
    }

    /**
     * 购买刷新次数: c=epic&m=buy_refresh_times
     *
     * status：-1 功能未开启；-2 获取用户数据失败；-3 元宝不足；1 成功
     */
    public static function buy_refresh_times(Context $ctx): array
    {
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        // 判断功能是否开启
        if (!self::isOpen($member)) {
            $return['status'] = -1;
            return $return;
        }
        $uid = (int)$member['uid'];
        $now = time();

        $result = DB::transaction(static function () use ($member, $uid, $now, $return): array {
            // 获取member信息
            $log = self::getMemberInfo($member, $now);
            if ($log === null) {
                $return['status'] = -2;
                return $return;
            }
            // 判断元宝是否足够，扣除元宝
            if (!Member::deductCurrency($uid, gold: self::REFRESH_PRICE)) {
                $return['status'] = -3;
                return $return;
            }
            // 添加次数
            self::updateMemberInfo($uid, ['buy_refreshtimes' => $log['buy_refreshtimes'] + 1], $now);
            $return['status'] = 1;
            $return['refreshtimes'] = $log['buy_refreshtimes'] + 1 + $log['refreshtimes'];
            return $return;
        });
        if ($result['status'] === 1) {
            $result['all_gold'] = self::memberValue($uid, 'gold');
        }
        return $result;
    }

    /**
     * 获取用户数据（移植 epic_model->get_member_info：每日首次更新刷新 / 锁定次数；没有记录时插入）
     *
     * 须在事务内调用：读取时加行锁，同一玩家的并发请求依次执行（每日重置与扣次数都只发生一次）。
     *
     * @param array<string, mixed> $member
     * @return array<string, mixed>|null 旧版字段（整型已转换）；插入失败时为 null
     */
    private static function getMemberInfo(array $member, int $now): ?array
    {
        $uid = (int)$member['uid'];
        [$refreshtimes, $locktimes] = self::dailyTimes((int)$member['vip']);
        $row = self::lockRow($uid);
        if ($row !== null) {
            // 判断每日次数是否更新
            if (date('ymd', $now) !== date('ymd', $row['dateline'])) {
                $data = [
                    'refreshtimes' => $refreshtimes,
                    'locktimes' => $locktimes,
                    'dateline' => $now,
                ];
                if (DB::table('epic_log')->where('uid', $uid)->whereNull('deleted_at')->update($data) > 0) {
                    $row = array_merge($row, $data);
                }
            }
            return $row;
        }
        // 首次今日添加用户数据
        $data = [
            'uid' => $uid,
            'refreshtimes' => $refreshtimes,
            'locktimes' => $locktimes,
            'buy_refreshtimes' => 0,
            'buy_locktimes' => 0,
            'epicid' => 0,
            'site1' => 0,
            'site2' => 0,
            'site3' => 0,
            'site4' => 0,
            'site5' => 0,
            'lock_site' => '',
            'dateline' => $now,
        ];
        // 并发首次请求只插入一行（主键 uid）；插入后再取一次并加锁
        DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_epic_log` (`' . implode('`, `', array_keys($data)) . '`) VALUES (:' . implode(', :', array_keys($data)) . ')',
            $data
        );
        return self::lockRow($uid);
    }

    /**
     * 读取玩家行并加行锁
     *
     * @return array<string, mixed>|null
     */
    private static function lockRow(int $uid): ?array
    {
        $rows = DB::query(
            'SELECT `' . implode('`, `', self::LOG_COLUMNS) . '` FROM `zc_sanguo_epic_log` WHERE `uid` = :uid AND `deleted_at` IS NULL FOR UPDATE',
            ['uid' => $uid]
        );
        if ($rows === []) {
            return null;
        }
        $row = $rows[0];
        foreach (self::LOG_COLUMNS as $column) {
            if ($column !== 'lock_site') {
                $row[$column] = (int)$row[$column];
            }
        }
        $row['lock_site'] = (string)$row['lock_site'];
        return $row;
    }

    /**
     * 修改玩家数据（移植 epic_model->update_member_info：同时更新 dateline，写入成功返回 1）
     *
     * @param array<string, int|string> $data
     */
    private static function updateMemberInfo(int $uid, array $data, int $now): int
    {
        if ($data === []) {
            return 0;
        }
        $data['dateline'] = $now;
        return DB::table('epic_log')->where('uid', $uid)->whereNull('deleted_at')->update($data) > 0 ? 1 : 0;
    }

    /**
     * 每日刷新 / 锁定次数（epic_model 构造函数：VIP 11 / 12 / 13 以上递增）
     *
     * @return array{0: int, 1: int}
     */
    private static function dailyTimes(int $vip): array
    {
        return match (true) {
            $vip >= 13 => [55, 50],
            $vip >= 12 => [40, 35],
            $vip >= 11 => [25, 20],
            default => [10, 5],
        };
    }

    /**
     * 残卷列表（移植 epic_model->get_epic_chip：id 为 0 时取全部，否则取该史诗的残卷，按 id 顺序）
     *
     * @return list<array{id: int, epicid: int, rate: float}>
     */
    private static function getEpicChip(int $id = 0): array
    {
        $return = [];
        foreach (EpicChip::ROWS as $row) {
            if ($id === 0 || $row['epicid'] === $id) {
                $return[] = $row;
            }
        }
        return $return;
    }

    /**
     * 响应里的 member：锁定提示（1 已锁定、2 位置正确可锁定、0 无），刷新 / 锁定次数合并购买次数，去掉 buy_* 与 dateline
     *
     * @param array<string, mixed> $log
     * @param bool $lockFlags force 不带锁定提示（旧版同）
     * @return array<string, mixed>
     */
    private static function output(array $log, bool $lockFlags = true): array
    {
        if ($lockFlags) {
            $lock = explode(',', $log['lock_site']);
            for ($i = 1; $i <= 5; $i++) {
                if (in_array($i, $lock)) {
                    $log['site' . $i . '_lock'] = 1;
                } elseif (!isset($log['site' . $i . '_lock'])) {
                    $log['site' . $i . '_lock'] = 0;
                }
            }
        }
        $log['refreshtimes'] += $log['buy_refreshtimes'];
        $log['locktimes'] += $log['buy_locktimes'];
        unset($log['buy_refreshtimes'], $log['buy_locktimes'], $log['dateline']);
        return $log;
    }

    /**
     * 主公等级 >= 150 开放（构造函数 _open）
     *
     * @param array<string, mixed> $member
     */
    private static function isOpen(array $member): bool
    {
        return (int)$member['level'] >= self::OPEN_LEVEL;
    }

    private static function memberValue(int $uid, string $column): int
    {
        return (int)(DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value($column) ?? 0);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function member(int $uid): ?array
    {
        if ($uid <= 0) {
            return null;
        }
        return DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
    }
}
