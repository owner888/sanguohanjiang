<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use Hanjiang\MasterData\ActNewyearTask;
use OmniPHP\Database\DB;

/**
 * 春节送福活动的任务计数（移植旧版 models/actnewyear_model.php 的 get_act_info / get_task_list / get_task_randlist /
 * get_task_info / auto_init_task / add_task_num / update_task_num / get_info / update_info / _int_info）
 *
 * 活动时间与版本取 ActivityTime 的 act_newyear（旧版数据 starttime = endtime = 0，从未开放）。活动期间各玩法按旧版调用
 * add_task_num(version, 任务类型) 给当天的任务记完成次数：切磋 15、铜雀台抢夺 16 / 亲近 17、海外贸易 18、藏宝图 20、
 * 对酒 21、国宴 22、国家捐献 23、多人副本 24、VIP 工资 25、赌石 26（task()，各调用方按旧版的「活动开启」判断）。
 * 主城 member.index 在活动开启时 auto_init_task（每天第一次刷新：清零翻牌 / 购买 / 福气，导入当天的随机任务列表）。
 *
 * 客户端没有春节送福界面（public/static/js 里没有 c=act_newyear 的调用），控制器 controllers/act_newyear.php
 * （index / get_task_reward / buy_num / open_item）不暴露；任务日志与玩家信息照旧版写，供以后接入界面。
 *
 * 存储：act_newyear_info（玩家每期一行）、act_newyear_task_log（每天的任务列表，ymd + tid）。
 * 多进程：完成次数用条件 UPDATE（have_num < t_num 时 + 1）原子累加（旧版先读后写）；当天任务列表的导入在玩家行锁里执行，
 * 同一玩家并发时只导入一份（旧版并发时可能导入两份）。
 */
final class ActNewyear
{
    public const string ACTIVITY = 'act_newyear';

    /** 任务类型 1 每天随机取的个数（get_task_randlist：array_rand($t_type1, 5)） */
    private const int RAND_NUM = 5;

    /**
     * 活动信息（get_act_info）：version / act_time / open（starttime <= 当前 < endtime 为 1）
     *
     * @return array{version: int, act_time: string, open: int}
     */
    public static function actInfo(int $now): array
    {
        $return = ['version' => 0, 'act_time' => '', 'open' => 0];
        $info = ActivityClock::open(self::ACTIVITY, $now);
        if ($info !== null) {
            // 活动开启判断
            if ($now >= $info['starttime'] && $now < $info['endtime']) {
                $return['open'] = 1;
            }
            // 活动版本号
            $return['version'] = (int)$info['version'];
            // 活动时间
            $return['act_time'] = date('m.d', $info['starttime']) . '~' . date('m.d', $info['endtime']);
        }
        return $return;
    }

    /**
     * 各玩法的春节送福计数（旧版各控制器构造函数 get_act_info，open 时 add_task_num(version, $tid[, $uid])）
     */
    public static function task(int $uid, int $tid, int $now): int
    {
        $info = self::actInfo($now);
        if ($info['open'] !== 1) {
            return 0;
        }
        return self::addTaskNum($info['version'], $tid, $uid, $now);
    }

    /**
     * 任务表（get_task_list）：$tid 为 0 时全部，否则按 t_type 取一行（没有为空数组）
     *
     * @return array<int|string, mixed>
     */
    public static function taskList(int $tid = 0): array
    {
        if ($tid > 0) {
            foreach (ActNewyearTask::ROWS as $row) {
                if ($row['t_type'] === $tid) {
                    return $row;
                }
            }
            return [];
        }
        return array_values(ActNewyearTask::ROWS);
    }

    /**
     * 当天的随机任务列表（get_task_randlist）：类型 1 的任务随机取 5 个（array_rand），其余类型全部，按奖励翻牌次数 num 排序
     *
     * @return list<array<string, mixed>>
     */
    public static function taskRandList(): array
    {
        $return = [];
        $type1 = [];
        foreach (ActNewyearTask::ROWS as $val) {
            if ($val['type'] === 1) {
                $type1[] = $val;
            } else {
                $return[] = $val;
            }
        }
        if ($type1 === []) {
            return $return;
        }
        // 随机从类型1中取出5个任务
        foreach ((array)array_rand($type1, min(self::RAND_NUM, count($type1))) as $tkey) {
            $return[] = $type1[$tkey];
        }
        // 排序（_sort_array($return, 'num')：array_multisort 按 num 升序，相同时比较整行）
        $orderArr = array_column($return, 'num');
        array_multisort($orderArr, SORT_ASC, SORT_NUMERIC, $return);
        return $return;
    }

    /**
     * 当天的任务日志（get_task_info）：$tid 大于 0 时只取该任务（[tid => 行]）；当天还没有任何任务时导入随机任务列表并返回整个列表
     *
     * @return array<int, array<string, mixed>>
     */
    public static function taskInfo(int $version, int $tid, int $uid, int $now, int $ymd = 0): array
    {
        $nowYmd = $ymd > 0 ? $ymd : (int)date('Ymd', $now);
        $query = DB::table('act_newyear_task_log')->where('ymd', $nowYmd)->where('uid', $uid)->where('version', $version)->whereNull('deleted_at');
        if ($tid > 0) {
            $query->where('tid', $tid);
        }
        $rows = $query->orderBy('id')->get();
        $return = [];
        if ($rows !== []) {
            if ($tid > 0) {
                // row_array：第一行
                $return[(int)$rows[0]['tid']] = self::logRow($rows[0]);
            } else {
                foreach ($rows as $val) {
                    $return[(int)$val['tid']] = self::logRow($val);
                }
            }
            return $return;
        }
        // 查看任务列表是否已经刷新；没有则导入最新任务列表（玩家行锁内再查一次，并发只导入一份）
        return Member::locked($uid, static function () use ($version, $uid, $nowYmd): array {
            $exists = DB::table('act_newyear_task_log')->where('ymd', $nowYmd)->where('uid', $uid)->where('version', $version)
                ->whereNull('deleted_at')->value('id');
            if ($exists !== null) {
                return [];
            }
            $return = [];
            foreach (self::insertTaskList($version, $uid, $nowYmd) as $val) {
                $return[$val['tid']] = $val;
            }
            return $return;
        });
    }

    /**
     * 自动重置任务列表（auto_init_task）：上次刷新不是今天时清零翻牌次数 / 购买次数 / 福气值，导入今天的随机任务列表
     *
     * 旧版注释「有完成的任务直接发送奖励」没有实现（只取了任务表），照旧版不发。返回 1 已刷新，0 今天已刷新过（或刚建档）。
     */
    public static function autoInitTask(int $version, int $uid, int $now): int
    {
        return Member::locked($uid, static function () use ($version, $uid, $now): int {
            // 获取上次刷新时间，如果不是当天，执行刷新
            $userInfo = self::info($version, $uid, $now);
            $nowTime = date('Ymd', $now);
            $refreshTime = (int)($userInfo['refresh_time'] ?? 0) > 0 ? date('Ymd', (int)$userInfo['refresh_time']) : '0';
            // 不刷新（今天已经刷新）
            if ($nowTime === $refreshTime) {
                return 0;
            }
            // 重置次数
            $upStatus = self::updateInfo($version, ['num' => 0, 'buy_num' => 0, 'fortune' => 0, 'refresh_time' => $now], $uid);
            if ($upStatus === 0) {
                return 0;
            }
            // 重置任务列表（旧版不查今天是否已导入，照旧版直接导入）
            return self::insertTaskList($version, $uid, (int)$nowTime) !== [] ? 1 : 0;
        });
    }

    /**
     * 增加任务次数（add_task_num）：初始化玩家信息；当天任务列表里有该任务且完成次数未满时 + 1。返回 1 成功
     */
    public static function addTaskNum(int $version, int $tid, int $uid, int $now): int
    {
        // 初始化玩家信息
        self::info($version, $uid, $now);
        // 获取任务基础信息
        $baseTask = self::taskList($tid);
        // 获取任务列表信息
        $taskList = self::taskInfo($version, $tid, $uid, $now);
        if ($baseTask === [] || !isset($taskList[$tid]['have_num'])) {
            return 0;
        }
        // have_num < t_num 时 + 1（update_task_num：同一天同一任务的行都更新）
        return DB::execute(
            'UPDATE `zc_sanguo_act_newyear_task_log` SET `have_num` = `have_num` + 1
             WHERE `ymd` = :ymd AND `uid` = :uid AND `version` = :version AND `tid` = :tid AND `have_num` < :t_num AND `deleted_at` IS NULL',
            ['ymd' => (int)date('Ymd', $now), 'uid' => $uid, 'version' => $version, 'tid' => $tid, 't_num' => (int)$baseTask['t_num']]
        ) > 0 ? 1 : 0;
    }

    /**
     * 玩家信息（get_info）：没有时建档（_int_info：refresh_time 为当前时间）
     *
     * @return array<string, int>
     */
    public static function info(int $version, int $uid, int $now): array
    {
        $row = DB::table('act_newyear_info')->select('uid', 'version', 'num', 'buy_num', 'fortune', 'refresh_time')
            ->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')->orderBy('id')->first();
        if ($row !== null) {
            return array_map('intval', $row);
        }
        // _int_info（唯一键 uid + version：并发建档只插入一行）
        DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_act_newyear_info` (`uid`, `version`, `num`, `buy_num`, `fortune`, `refresh_time`)
             VALUES (:uid, :version, 0, 0, 0, :now)',
            ['uid' => $uid, 'version' => $version, 'now' => $now]
        );
        $row = DB::table('act_newyear_info')->select('uid', 'version', 'num', 'buy_num', 'fortune', 'refresh_time')
            ->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')->first();
        return $row === null ? [] : array_map('intval', $row);
    }

    /**
     * 更新玩家信息（update_info），返回 1 有行被改
     *
     * @param array<string, int> $data
     */
    public static function updateInfo(int $version, array $data, int $uid): int
    {
        return DB::table('act_newyear_info')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')->update($data) > 0 ? 1 : 0;
    }

    /**
     * 导入当天的随机任务列表（insert_batch），返回导入的行
     *
     * @return list<array<string, int>>
     */
    private static function insertTaskList(int $version, int $uid, int $ymd): array
    {
        $newData = [];
        foreach (self::taskRandList() as $rval) {
            $newData[] = [
                'tid' => (int)$rval['t_type'],
                'version' => $version,
                'ymd' => $ymd,
                'uid' => $uid,
                'have_num' => 0,
                'is_reward' => 0,
                'dateline' => 0,
            ];
        }
        if ($newData === [] || DB::table('act_newyear_task_log')->insertBatch($newData) <= 0) {
            return [];
        }
        return $newData;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, int>
     */
    private static function logRow(array $row): array
    {
        return [
            'tid' => (int)$row['tid'],
            'version' => (int)$row['version'],
            'ymd' => (int)$row['ymd'],
            'uid' => (int)$row['uid'],
            'have_num' => (int)$row['have_num'],
            'is_reward' => (int)$row['is_reward'],
            'dateline' => (int)$row['dateline'],
        ];
    }
}
