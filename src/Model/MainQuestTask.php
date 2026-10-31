<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use Hanjiang\MasterData\MainQuest;
use Hanjiang\MasterData\Mission;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;

/**
 * 主线任务（移植旧版 models/mainquest_model.php 与控制器 mainquest.php / member.php 共用的 get_task_info、任务列表组装）
 *
 * 存储同旧版：main_quest_log 每人每条任务线一行（task_id 当前任务、task_type 目标类型、task_value 目标参数、task_plan 当前进度、
 * status 0 进行中 / 1 可领取 / 2 已终结）；main_quest_reward 领奖记录；任务配置取 MasterData\MainQuest（旧版 main_quest）。
 * Redis 键同旧版：mainquest_lock_{uid}（初始化锁，600 秒）、mainquest_refresh_{uid}（当天已检查新任务，86400 秒）、
 * rank_up_{uid}（演武榜累计上升名次，86400 秒）。不移植旧版的两份缓存 mainquest_member_{uid} / mainquest_task_list
 * （任务记录直接读库、任务配置是 MasterData 常量）。
 *
 * 目标类型（action 的 $type）：1 击败关卡、2 任意装备强化到 X 级、3 任意武将提升到 X 级、4 任意武将全属性洗到 ≥X 点、
 * 5 任意阵法升到 X 级、6 同时上阵 5 个将领、7 占领或抢夺一次银矿、8 征战一次演武榜、9 演武榜提升 X 名、10 参加一次多人副本、
 * 11 参加一次世界BOSS、12 完成任意日常任务、13 完成任意 X 星级任务、14 完成一次海外贸易、15 获得任意将魂、16 将魂等级提升至 X 级、
 * 17 完成一次每日通商、18 完成通商 X 星级、19 获得 X 星级武将、20 连续登录 X 天。
 *
 * 多 Worker：领奖记录 (uid, task_id) 加唯一键，并发重复领奖只有一个能插入领奖记录（另一个回 -5 添加领奖记录失败）；
 * 初始化锁用 SET NX（旧版先 GET 再 SETEX）。
 */
final class MainQuestTask
{
    /** 事件型任务：达成即完成（task_action） */
    private const array EVENT_TYPE = [6, 7, 8, 10, 11, 12, 14, 15, 17];
    /** 不显示具体进度的类型（控制器 $event_type：进度显示为 0 / 1，目标显示为 1） */
    private const array SHOW_EVENT_TYPE = [1, 6, 9, 13, 18, 19];

    /** main_quest_log 旧版字段 */
    private const array LOG_FIELDS = ['id', 'uid', 'task_id', 'task_type', 'task_value', 'task_plan', 'status', 'dateline'];

    private const string RANK_UP_KEY = 'rank_up_';
    private const string LOCK_KEY = 'mainquest_lock_';
    private const string REFRESH_KEY = 'mainquest_refresh_';

    /**
     * 用户的任务列表（get_member_info）
     *
     * @return list<array<string, int>>
     */
    public static function memberInfo(int $uid): array
    {
        $rows = DB::table('main_quest_log')->select(...self::LOG_FIELDS)->where('uid', $uid)->whereNull('deleted_at')
            ->orderBy('id')->get();
        return array_map(static fn (array $row): array => array_map('intval', $row), $rows);
    }

    /**
     * 根据 id 查询任务配置（get_task_by_id）：传数组返回 [id => 任务]，传单个返回任务；取不到为空数组
     *
     * @param int|list<int> $id
     * @return array<int|string, mixed>
     */
    public static function taskById(int|array $id): array
    {
        if (!is_array($id)) {
            return MainQuest::ROWS[$id] ?? [];
        }
        $return = [];
        foreach (MainQuest::ROWS as $val) {
            if (in_array($val['id'], $id, true)) {
                $return[$val['id']] = $val;
            }
        }
        return $return;
    }

    /**
     * 根据前置 id 查询任务（get_task_by_pre，按 task_type 升序）
     *
     * - $preid 为数组：返回 [前置 id => 任务]（同一前置有多个时取排在最后的）；
     * - 单个：多于一个返回列表（任务初始化），只有一个返回该任务，没有返回空数组。
     *
     * @param int|array<int|string, int> $preid
     * @return array<int|string, mixed>
     */
    public static function taskByPre(int|array $preid, int $type = 0): array
    {
        $ids = is_array($preid) ? array_values($preid) : [$preid];
        $rows = [];
        foreach (MainQuest::ROWS as $val) {
            if (in_array($val['prepose'], $ids, true) && ($type <= 0 || $val['type'] === $type)) {
                $rows[] = $val;
            }
        }
        // ORDER BY task_type ASC（同 task_type 按主键）
        usort($rows, static fn (array $a, array $b): int => [$a['task_type'], $a['id']] <=> [$b['task_type'], $b['id']]);
        if (is_array($preid)) {
            $return = [];
            foreach ($rows as $val) {
                $return[$val['prepose']] = $val;
            }
            return $return;
        }
        if (count($rows) > 1) {
            return $rows;
        }
        return $rows[0] ?? [];
    }

    /**
     * 根据任务 id 查询用户的任务记录（get_usertask_by_id）
     *
     * @return array<string, int>
     */
    public static function userTaskById(int $uid, int $taskId): array
    {
        $row = DB::table('main_quest_log')->select(...self::LOG_FIELDS)->where('uid', $uid)->where('task_id', $taskId)
            ->whereNull('deleted_at')->orderBy('id')->first();
        return $row === null ? [] : array_map('intval', $row);
    }

    /**
     * 把下一个任务写到用户的这条任务线上（update_new_task）
     *
     * @param array<string, mixed> $task
     */
    public static function updateNewTask(int $uid, int $id, array $task, int $plan, int $status): int
    {
        return DB::table('main_quest_log')->where('uid', $uid)->where('id', $id)->whereNull('deleted_at')->update([
            'task_id' => $task['id'],
            'task_type' => $task['type'],
            'task_value' => $task['value'],
            'task_plan' => max(0, $plan),
            'status' => $status,
        ]) > 0 ? 1 : 0;
    }

    /**
     * 修改任务状态（update_task_status）
     */
    public static function updateStatus(int $uid, int $id, int $status): void
    {
        DB::table('main_quest_log')->where('uid', $uid)->where('id', $id)->whereNull('deleted_at')->update(['status' => $status]);
    }

    /**
     * 添加用户任务列表（add_task_list）
     *
     * @param array<int|string, array<string, int>> $data
     */
    public static function addTaskList(array $data): void
    {
        foreach ($data as $row) {
            DB::table('main_quest_log')->insert($row);
        }
    }

    /**
     * 任务完成，供其他地方调用（task_action）
     *
     * 自己的任务列表里有该类型、未完成的任务时：事件型任务直接完成；等级 / 次数型任务进度记为 $times（不是累加），
     * 达到目标时完成。
     *
     * @return int 1 修改成功，-1 任务不存在或已完成，0 没有修改
     */
    public static function action(int $uid, int $type, int $times = 1): int
    {
        // 查询自己的任务列表中是否有该类型任务
        $task = DB::table('main_quest_log')->select(...self::LOG_FIELDS)->where('uid', $uid)->where('task_type', $type)
            ->where('status', '!=', 2)->whereNull('deleted_at')->orderBy('id')->first();
        if ($task === null || (int)$task['status'] === 1) {
            // 任务不存在或任务已完成
            return -1;
        }
        $query = DB::table('main_quest_log')->where('uid', $uid)->where('id', (int)$task['id'])->where('status', 0)->whereNull('deleted_at');
        if (in_array($type, self::EVENT_TYPE, true)) {
            // 事件型任务  直接修改任务完成状态
            $data = ['task_plan' => 1, 'status' => 1];
        } elseif ($times >= (int)$task['task_value']) {
            // 任务完成 修改任务状态
            $data = ['task_plan' => (int)$task['task_value'], 'status' => 1];
        } else {
            // 列为无符号整数（演武榜上升名次为负时旧版库截为 0）
            $data = ['task_plan' => max(0, $times), 'status' => 0];
        }
        return $query->update($data) > 0 ? 1 : 0;
    }

    /**
     * 任务需要的用户数据（控制器 get_task_info：如等级、数量）
     */
    public static function taskInfo(int $uid, int $type, int $value): int
    {
        return match ($type) {
            // 击败关卡
            1 => OpenMap::missionStatus($uid, $value),
            // 任意装备强化到x级  获取最大等级的装备
            2 => (int)(DB::table('equipment')->where('uid', $uid)->whereNull('deleted_at')->orderBy('level', 'DESC')->value('level') ?? 0),
            // 任意武将提升到X级  获取等级最高的武将
            3 => (int)(DB::table('general')->where('uid', $uid)->whereNull('deleted_at')->orderBy('level', 'DESC')->value('level') ?? 0),
            // 任意武将全属性（武智体）洗到≥X点  获取3属性最大并相同的值
            4 => self::attrValue($uid),
            // 任意阵法升到X级	获取阵法最高等级
            5 => (int)(DB::table('matrix')->where('uid', $uid)->whereNull('deleted_at')->orderBy('level', 'DESC')->value('level') ?? 0),
            // 同时上阵5个将领	获取阵法人数
            6 => self::matrixPeopleNumber($uid),
            // 获得x星级武将		获取拥有星级最高的武将
            19 => (int)(DB::table('general')->where('uid', $uid)->where('star', '>=', $value)->whereNull('deleted_at')->value('star') ?? 0),
            // 获得历史上升排名
            9 => self::arenaRankUp($uid),
            // 连续登录x天：依赖未移植 member_field.login_day（连续登录天数，member.php _update_continuous_login_day），按 0
            20 => 0,
            default => 0,
        };
    }

    /**
     * 演武榜累计上升名次（get_arena_rank_up）：任务不存在返回 -1
     */
    public static function arenaRankUp(int $uid, int $addrank = 0): int
    {
        $key = self::RANK_UP_KEY . $uid;
        $rank = Redis::get($key);
        if ($rank === null) {
            // 查询任务中是否有任务进度
            $plan = DB::table('main_quest_log')->where('uid', $uid)->where('task_type', 9)->whereNull('deleted_at')->orderBy('id')->value('task_plan');
            // 任务不存在 直接返回错误
            $rank = $plan === null ? -1 : (int)$plan;
        }
        $rank = (int)$rank;
        if ($rank === -1) {
            return -1;
        }
        // 新的上升名次
        $rank += $addrank;
        Redis::set($key, $rank, 86400);
        return $rank;
    }

    /**
     * 查询奖励领取记录（get_reward_log）
     */
    public static function hasRewardLog(int $uid, int $taskId): bool
    {
        return DB::table('main_quest_reward')->where('uid', $uid)->where('task_id', $taskId)->whereNull('deleted_at')->value('id') !== null;
    }

    /**
     * 添加领奖记录（add_reward_log）
     *
     * @param array<string, mixed> $task
     * @return int 1 成功，0 失败（已有记录）
     */
    public static function addRewardLog(int $uid, array $task, int $now): int
    {
        return DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_main_quest_reward` (`uid`, `task_id`, `reward_type`, `reward_value`, `dateline`)
             VALUES (:uid, :task_id, :reward_type, :reward_value, :dateline)',
            ['uid' => $uid, 'task_id' => $task['id'], 'reward_type' => $task['reward_type'], 'reward_value' => $task['reward_value'], 'dateline' => $now]
        ) > 0 ? 1 : 0;
    }

    /**
     * 加初始化锁（add_lock + get_lock）：拿到锁返回 true
     */
    public static function lock(int $uid): bool
    {
        $redis = Redis::instance();
        return $redis !== null && (bool)$redis->set(self::LOCK_KEY . $uid, '1', ['nx', 'ex' => 600]);
    }

    public static function unlock(int $uid): void
    {
        Redis::del(self::LOCK_KEY . $uid);
    }

    /**
     * 今日是否已经检查过新任务（get_refesh_status）
     */
    public static function refreshed(int $uid): bool
    {
        return Redis::exists(self::REFRESH_KEY . $uid);
    }

    public static function addRefreshStatus(int $uid): void
    {
        Redis::set(self::REFRESH_KEY . $uid, 1, 86400);
    }

    /**
     * 任务列表（mainquest 控制器 index / member 控制器 get_task_list）
     *
     * 第一次进入时按前置 0 的任务建立各任务线（加锁防重复初始化；锁被占时返回 null，旧版直接回空列表）；已终结的任务线
     * 以其任务为前置查下一个任务，没有「连续登录」类任务线时补一条（每天检查一次）；整理成界面用的列表。
     *
     * status：1 成功；0 初始化锁被占（旧版直接回空列表）；-1 没有初始任务配置；-2 取任务配置失败
     *
     * @return array{status: int, list: list<array<string, mixed>>}
     */
    public static function taskList(int $uid, int $now): array
    {
        $list = self::memberInfo($uid);
        $data = [];
        if ($list === []) {
            if (!self::lock($uid)) {
                // 有锁直接返回
                return ['status' => 0, 'list' => []];
            }
            try {
                // 初始化任务列表
                $init = self::taskByPre(0);
                if ($init === [] || !array_is_list($init)) {
                    // 按现有配置前置 0 的任务有 8 个；只有 1 个时旧版把单个任务当列表遍历写出垃圾数据，同样按失败返回
                    return ['status' => -1, 'list' => []];
                }
                foreach ($init as $key => $val) {
                    [$number, $taskStatus, $val['value']] = self::evaluate($uid, $val);
                    $data[$key] = [
                        'uid' => $uid,
                        'task_id' => $val['id'],
                        'task_type' => $val['type'],
                        'task_value' => $val['value'],
                        'task_plan' => $number,
                        'status' => $taskStatus,
                        'dateline' => $now,
                    ];
                }
                // 把列表插入到用户数据中
                self::addTaskList($data);
                foreach ($data as $key => $val) {
                    $data[$key]['type'] = $init[$key]['task_type'];
                    $data[$key]['name'] = $init[$key]['name'];
                    $data[$key]['description'] = $init[$key]['description'];
                    $data[$key]['reward'][] = ['type' => $init[$key]['reward_type'], 'value' => $init[$key]['reward_value']];
                }
                $taskList = $data;
            } finally {
                self::unlock($uid);
            }
        } else {
            if (self::taskById(array_map(static fn (array $val): int => $val['task_id'], $list)) === []) {
                return ['status' => -2, 'list' => []];
            }
            $taskList = self::withTemp($list);
        }

        // 把已经完成的任务选出来
        $idArr = [];
        $newType = 0;
        foreach ($taskList as $key => $val) {
            // 如果当前任务列表中没有关于新类型的任务，就执行更新
            if ($val['task_type'] === 20) {
                $newType = 1;
            }
            if ($val['status'] === 2) {
                $idArr[$val['id']] = $val['task_id'];
                unset($taskList[$key]);
            }
        }

        // 如果没有需要检查新任务列表的任务，并且已超过上次更新时间，就检查是否有新的任务
        if (($idArr !== [] || $newType === 0) && !self::refreshed($uid)) {
            $refreshList = [];
            if ($idArr !== []) {
                $refreshList = self::taskByPre($idArr);
            }
            if ($newType === 0) {
                $idArr[0] = 0;
                // 根据类型获取新任务
                $newTask = self::taskByPre(0, 20);
                if ($newTask !== []) {
                    $refreshList[0] = $newTask;
                }
            }
            foreach ($idArr as $key => $val) {
                if (empty($refreshList[$val])) {
                    continue;
                }
                $next = $refreshList[$val];
                [$number, $taskStatus, $next['value']] = self::evaluate($uid, $next);
                if ($next['prepose'] === 0) {
                    // 该类型的第一个任务, 执行插入
                    self::addTaskList([[
                        'uid' => $uid,
                        'task_id' => $next['id'],
                        'task_type' => $next['type'],
                        'task_value' => $next['value'],
                        'task_plan' => $number,
                        'status' => $taskStatus,
                        'dateline' => $now,
                    ]]);
                } else {
                    // 把当前任务修改成新的任务数据，如果任务完成，任务的状态直接置成已完成
                    self::updateNewTask($uid, $key, $next, $number, $taskStatus);
                }
                // 把新的任务加到返回的任务列表中
                $taskList[] = [
                    'uid' => $uid,
                    'task_id' => $next['id'],
                    'type' => $next['task_type'],
                    'task_type' => $next['type'],
                    'task_value' => $next['value'],
                    'task_plan' => $number,
                    'status' => $taskStatus,
                    'dateline' => $now,
                    'name' => $next['name'],
                    'description' => $next['description'],
                    'reward' => [['type' => $next['reward_type'], 'value' => $next['reward_value']]],
                ];
            }
            // 添加更新任务列表状态 每天更新一次
            self::addRefreshStatus($uid);
        }

        return ['status' => 1, 'list' => self::display($uid, $taskList)];
    }

    /**
     * 当前任务列表（控制器 get_task_temp：领奖后的列表，已终结的不显示）
     *
     * @param list<array<string, int>> $list
     * @return list<array<string, mixed>>
     */
    public static function taskTemp(int $uid, array $list): array
    {
        $taskIds = array_map(static fn (array $val): int => $val['task_id'], $list);
        $taskList = self::taskById($taskIds);
        if ($taskList === []) {
            return [];
        }
        $rows = [];
        foreach (self::withTemp($list) as $val) {
            if ($val['status'] !== 2) {
                $rows[] = $val;
            }
        }
        return self::display($uid, $rows);
    }

    /**
     * 按目标类型取当前进度与状态（新任务进入列表时）：返回 [进度, 状态 0 / 1, 目标（0 按 1）]
     *
     * @param array<string, mixed> $task
     * @return array{0: int, 1: int, 2: int}
     */
    private static function evaluate(int $uid, array $task): array
    {
        $number = self::taskInfo($uid, $task['type'], $task['value']);
        $value = $task['value'] === 0 ? 1 : $task['value'];
        if ($number > 0 && $number >= $value) {
            $taskStatus = 1;
            $number = $value;
        } else {
            $taskStatus = 0;
        }
        // 任务类型1 完成
        if ($task['type'] === 1 && $number === 1) {
            $taskStatus = 1;
        }
        return [max(0, $number), $taskStatus, $value];
    }

    /**
     * 领奖后的下一个任务（get_task_reward：没有下一个任务时这条任务线终结）
     *
     * @param array<string, int> $result 用户这条任务线的记录
     */
    public static function next(int $uid, array $result, int $taskId): void
    {
        $nextTask = self::taskByPre($taskId);
        if ($nextTask === [] || array_is_list($nextTask)) {
            // 任务列表为空，把该条任务状态置为已结束
            // （按现有配置每个任务最多一个后续任务；有多个时旧版把列表当任务写出垃圾数据，这里按没有后续处理）
            self::updateStatus($uid, $result['id'], 2);
            return;
        }
        // 根据类型获得需要的任务数据（如强化等级 ，装备等级）
        $number = self::taskInfo($uid, $nextTask['type'], $nextTask['value']);
        if ($nextTask['value'] === 0) {
            $nextTask['value'] = 1;
        }
        if ($number >= $nextTask['value']) {
            $taskStatus = 1;
            $number = $nextTask['value'];
        } else {
            $taskStatus = 0;
        }
        // 任务类型是通过关卡
        if ($nextTask['type'] === 1 && $number === 1) {
            $taskStatus = 1;
        }
        // 把当前任务修改成新的任务数据，如果任务完成，任务的状态直接置成已完成
        self::updateNewTask($uid, $result['id'], $nextTask, $number, $taskStatus);
    }

    /**
     * 任务记录加上任务配置（type 主线 / 支线、name、description、reward）
     *
     * @param list<array<string, int>> $list
     * @return list<array<string, mixed>>
     */
    private static function withTemp(array $list): array
    {
        $taskList = self::taskById(array_map(static fn (array $val): int => $val['task_id'], $list));
        foreach ($list as $key => $val) {
            if (isset($taskList[$val['task_id']])) {
                $task = $taskList[$val['task_id']];
                $list[$key]['type'] = $task['task_type'];
                $list[$key]['name'] = $task['name'];
                $list[$key]['description'] = $task['description'];
                $list[$key]['reward'][] = ['type' => $task['reward_type'], 'value' => $task['reward_value']];
            }
        }
        return $list;
    }

    /**
     * 界面列表：关卡类任务带要前往的势力 / 场景；不显示进度的类型进度改为 0 / 1、目标改为 1；按主线 / 支线、任务 id 排序
     *
     * @param array<int|string, array<string, mixed>> $taskList
     * @return list<array<string, mixed>>
     */
    private static function display(int $uid, array $taskList): array
    {
        $typeSort = [];
        $idSort = [];
        foreach ($taskList as $key => $val) {
            $typeSort[] = $val['type'] ?? 0;
            $idSort[] = $val['task_id'];
            // 主线关卡 类型 把要前往的关卡势力和场景查询出来
            if ($val['task_type'] === 1) {
                // 最高场景
                $maxmission = OpenMap::missionMax($uid)['id'];
                $goMission = $val['task_value'] > $maxmission ? $maxmission : $val['task_value'];
                $mission = Mission::ROWS[$goMission] ?? null;
                $taskList[$key]['missionlevel'] = $mission !== null ? $mission['missionlevel'] : 0;
                $taskList[$key]['missionstage'] = $mission !== null ? $mission['missionstage'] : 0;
            }
            if (in_array($val['task_type'], self::SHOW_EVENT_TYPE, true)) {
                $taskList[$key]['task_plan'] = $val['task_plan'] >= $val['task_value'] ? 1 : 0;
            }
            if (in_array($val['task_type'], self::SHOW_EVENT_TYPE, true) || $val['task_value'] === 0) {
                $taskList[$key]['task_value'] = 1;
            }
        }
        $taskList = array_values($taskList);
        array_multisort($typeSort, SORT_ASC, $idSort, SORT_ASC, $taskList);
        return $taskList;
    }

    /**
     * 所有将领中「武智体洗练值最小的一项」的最大值（get_attr_value）
     */
    private static function attrValue(int $uid): int
    {
        $rows = DB::table('general')->select('wuliup', 'zhiliup', 'tiliup')->where('uid', $uid)->whereNull('deleted_at')->get();
        $attr = [0];
        foreach ($rows as $key => $val) {
            $attr[$key] = min((int)$val['wuliup'], (int)$val['zhiliup'], (int)$val['tiliup']);
        }
        return max($attr);
    }

    /**
     * 有阵法正好上阵 5 人时为 5（get_matrix_people_number）
     */
    private static function matrixPeopleNumber(int $uid): int
    {
        foreach (DB::table('matrix')->where('uid', $uid)->whereNull('deleted_at')->get() as $val) {
            $number = 0;
            for ($i = 1; $i <= 9; $i++) {
                if ((int)$val['position' . $i] > 0) {
                    $number++;
                }
            }
            if ($number === 5) {
                return 5;
            }
        }
        return 0;
    }
}
