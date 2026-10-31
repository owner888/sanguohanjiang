<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use Hanjiang\MasterData\Equip;
use Hanjiang\MasterData\Task;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;

/**
 * 日常任务（移植旧版 models/task_model.php）
 *
 * 存储同旧版：task_log 每人一行（三个任务位的任务 id 与状态 0 未领取 / 1 进行中 / 2 已完成、免费刷新次数、最后完成时间）；
 * 今日剩余次数在 member_activity.task_number（建档 6，每日计划任务重置为 6）；进行中任务的进度存 Redis
 * task_plan_{ymd}_{uid}（旧版同名键，当天有效 86400 秒）。任务配置取 MasterData\Task（旧版 zc_sanguo_task）。
 *
 * 任务进度计数 action() 供其他玩法调用（旧版 task_model->task_action，调用方各自判断主公 41 级以上）：
 * 1 武将突飞、2 银币征收、3 银矿抢占、4 每日通商、5 元宝消费、6 关卡挑战、7 属性培养、8 装备升级、9 金银守卫、
 * 10 演武竞技、11 宝石贸易、12 世界BOSS、13 国家矿脉、14 天下史诗。
 *
 * 多 Worker：进度键改为 Redis 哈希，计数用 HINCRBY 原子累加（旧版 JSON 串读改写，并发会丢计数）；完成时按
 * taskstatus = 1 条件更新为 2，只会完成一次。task_log.uid 加唯一键，并发首次建档用 INSERT IGNORE 只建一行
 * （旧版无唯一键，并发会插出两行）。
 */
final class DailyTask
{
    /** 开启等级（旧版控制器 $level、member_model->sub_gold 等调用方的 41 级判断） */
    public const int OPEN_LEVEL = 41;
    /** 刷新价格（元宝） */
    public const int PRICE = 5;
    /** 每日任务次数（get_activity_info 建档值 / daily_cron 重置值） */
    public const int DAILY_NUMBER = 6;

    public const int TYPE_LEAP = 1;
    public const int TYPE_LEVY = 2;
    public const int TYPE_MINE = 3;
    public const int TYPE_BUSINESS = 4;
    public const int TYPE_GOLD = 5;
    public const int TYPE_MAP = 6;
    public const int TYPE_CULTIVATE = 7;
    public const int TYPE_STRENGTHEN = 8;
    public const int TYPE_ISLAND = 9;
    public const int TYPE_ARENA = 10;
    public const int TYPE_TRADER = 11;
    public const int TYPE_WORLDBOSS = 12;
    public const int TYPE_COUNTRYMINE = 13;
    public const int TYPE_EPIC = 14;

    private const string PLAN_KEY = 'task_plan_';
    private const int PLAN_TTL = 86400;

    /** task_log 旧版字段（旧版 task_log 还有一列 number，get_log 读出后即被 member_activity.task_number 覆盖，新版不建） */
    private const array LOG_FIELDS = ['id', 'uid', 'taskid1', 'taskid2', 'taskid3', 'taskstatus1', 'taskstatus2', 'taskstatus3', 'number', 'free_times', 'dateline'];

    /** 星级概率（get_star_rate，单位千分之一） */
    private const array STAR_RATE = [1 => 30, 2 => 30, 3 => 15, 4 => 10, 5 => 5, 6 => 4, 7 => 3, 8 => 2, 9 => 0.9, 10 => 0.1];

    /**
     * 用户记录（get_log）：task_log 行 + number（member_activity.task_number，没有活动记录时按旧版建档）；没有记录返回空数组
     *
     * @return array<string, int>
     */
    public static function log(int $uid): array
    {
        $row = DB::table('task_log')->select(...array_diff(self::LOG_FIELDS, ['number']))
            ->where('uid', $uid)->whereNull('deleted_at')->first();
        if ($row === null) {
            return [];
        }
        $row = array_map('intval', $row);
        MemberActivity::ensure($uid);
        $row['number'] = (int)DB::table('member_activity')->where('uid', $uid)->whereNull('deleted_at')->value('task_number');
        return self::ordered($row);
    }

    /**
     * 用户任务信息（get_task_log）：taskid1~3 换成任务详情；跨天时免费刷新次数重置为 1；没有记录时随机 3 个任务建档
     *
     * @return array<string, mixed>
     */
    public static function taskLog(int $uid, int $level, int $now): array
    {
        $result = self::log($uid);
        if ($result !== []) {
            // 把任务的详细信息放到以前的任务id里面
            $return = $result;
            for ($i = 1; $i <= 3; $i++) {
                $return['taskid' . $i] = self::task($result['taskid' . $i]);
            }
            // 更新任务次数
            if (date('ymd', $result['dateline']) !== date('ymd', $now)) {
                $affected = DB::table('task_log')->where('uid', $uid)->whereNull('deleted_at')
                    ->update(['free_times' => 1, 'dateline' => $now]);
                if ($affected > 0) {
                    $return['free_times'] = 1;
                    $return['dateline'] = $now;
                }
            }
            return $return;
        }
        // 随机取出3条任务数据
        $task = self::rand($level, 3);
        // 创建用户数据
        $data = [
            'uid' => $uid,
            'taskid1' => $task[1]['id'],
            'taskid2' => $task[2]['id'],
            'taskid3' => $task[3]['id'],
            'taskstatus1' => 0,
            'taskstatus2' => 0,
            'taskstatus3' => 0,
            'free_times' => 1,
            'dateline' => $now,
        ];
        $inserted = DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_task_log` (`uid`, `taskid1`, `taskid2`, `taskid3`, `taskstatus1`, `taskstatus2`, `taskstatus3`, `free_times`, `dateline`)
             VALUES (:uid, :taskid1, :taskid2, :taskid3, :taskstatus1, :taskstatus2, :taskstatus3, :free_times, :dateline)',
            $data
        );
        if ($inserted === 0) {
            // 并发请求先建了档：按已有记录返回
            return self::taskLog($uid, $level, $now);
        }
        $data['number'] = self::DAILY_NUMBER;
        // 把任务的详细信息放到以前的任务id里面
        $data['taskid1'] = $task[1];
        $data['taskid2'] = $task[2];
        $data['taskid3'] = $task[3];
        return $data;
    }

    /**
     * 修改任务状态（update_task_status）
     *
     * @return int 1 成功，0 没有修改
     */
    public static function updateStatus(int $uid, int $site, int $status): int
    {
        return DB::table('task_log')->where('uid', $uid)->whereNull('deleted_at')
            ->update(['taskstatus' . $site => $status]) > 0 ? 1 : 0;
    }

    /**
     * 修改任务记录（update_task_log，刷新用：新任务状态置 0；$refresh 为 1 时免费刷新次数置 0）
     *
     * @param array<int, int> $list 任务位 => 任务 id
     * @return int 1 成功，0 没有修改
     */
    public static function updateTasks(int $uid, array $list, int $refresh = 1): int
    {
        $data = [];
        foreach ($list as $key => $val) {
            $data['taskid' . $key] = $val;
            $data['taskstatus' . $key] = 0;
        }
        if ($refresh) {
            $data['free_times'] = 0;
        }
        return DB::table('task_log')->where('uid', $uid)->whereNull('deleted_at')->update($data) > 0 ? 1 : 0;
    }

    /**
     * 修改任务数量（update_task_number：最后完成时间记为现在，剩余次数为 $number - 1）
     *
     * @return int 修改后的剩余次数；没有修改返回 0
     */
    public static function updateNumber(int $uid, int $number, int $now): int
    {
        DB::table('task_log')->where('uid', $uid)->whereNull('deleted_at')->update(['dateline' => $now]);
        // 列为无符号整数：旧版库非严格模式下 -1 截为 0
        $left = max(0, $number - 1);
        $affected = DB::table('member_activity')->where('uid', $uid)->whereNull('deleted_at')->update(['task_number' => $left]);
        return $affected > 0 ? $left : 0;
    }

    /**
     * 任务配置（get_task_by_id 单个）；取不到返回空数组
     *
     * @return array<string, mixed>
     */
    public static function task(int $id): array
    {
        return Task::ROWS[$id] ?? [];
    }

    /**
     * 随机查询任务（get_task_rand）：每个位置先按星级概率随机星级，再在该星级的任务里随机取一个（同一次取的不重复）；
     * 演武类（type 4 每日通商）主公不足 61 级、或任务开启等级高于主公等级时换一个。返回 1~$num => 任务
     *
     * 旧版某星级的可选任务被排除光时 array_rand 空数组出错；按现有配置每个星级都有 10 个以上 0 级可做的任务，不会发生，
     * 这里遇到时跳过该位置。
     *
     * @return array<int, array<string, mixed>>
     */
    public static function rand(int $level, int $num = 1): array
    {
        $star = [];
        for ($i = 1; $i <= $num; $i++) {
            $star[$i] = self::starRate();
        }
        // 把查询出来的任务按星级分类
        $list = [];
        foreach (Task::ROWS as $val) {
            if (in_array($val['star'], $star, true)) {
                $list[$val['star']][$val['id']] = $val;
            }
        }
        $return = [];
        for ($i = 1; $i <= $num; $i++) {
            if (empty($list[$star[$i]])) {
                continue;
            }
            $j = array_rand($list[$star[$i]]);
            $info = $list[$star[$i]][$j];
            unset($list[$star[$i]][$j]);
            if ($info['type'] === 4 && $level < 61) {
                // 没有达到该任务条件，无法领取
                $i--;
            } elseif ($level < $info['open_level']) {
                // 该任务需求等级大于主公等级
                $i--;
            } else {
                $return[$i] = $info;
            }
        }
        return $return;
    }

    /**
     * 道具模板（equipment_model->get_temp_info）：[id => Equip 行]；一个都取不到返回空数组
     *
     * @param list<int> $ids
     * @return array<int, array<string, mixed>>
     */
    public static function tempInfo(array $ids): array
    {
        $return = [];
        foreach ($ids as $id) {
            if (isset(Equip::ROWS[$id])) {
                $return[$id] = Equip::ROWS[$id];
            }
        }
        return $return;
    }

    /**
     * 当天进度键
     */
    public static function planKey(int $uid, int $now): string
    {
        return self::PLAN_KEY . date('ymd', $now) . '_' . $uid;
    }

    /**
     * 写入进行中任务的进度（accept_task：plan 0）
     *
     * @param array<string, mixed> $info 任务配置
     */
    public static function startPlan(int $uid, array $info, int $site, int $now): void
    {
        $key = self::planKey($uid, $now);
        Redis::del($key);
        Redis::hMSet($key, ['id' => $info['id'], 'type' => $info['type'], 'value' => $info['value'], 'plan' => 0, 'site' => $site]);
        Redis::expire($key, self::PLAN_TTL);
    }

    /**
     * 取任务进度（_get_task_redis）；没有返回空数组
     *
     * @return array<string, int>
     */
    public static function plan(int $uid, int $now): array
    {
        $task = Redis::hGetAll(self::planKey($uid, $now));
        if (!is_array($task) || !isset($task['id'], $task['plan'])) {
            return [];
        }
        return array_map('intval', $task);
    }

    /**
     * 删除进度（give_up）
     */
    public static function clearPlan(int $uid, int $now): void
    {
        Redis::del(self::planKey($uid, $now));
    }

    /**
     * 任务完成计数，供其他地方调用（task_action）
     *
     * 当天没有进度键时从 task_log 取进行中的任务建键；类型不是进行中任务的类型不计；累计达到任务要求时把任务状态改为
     * 2（已完成）并删除进度键。
     *
     * @return int 1 计数成功，-1 类型不正确，-2 没有用户记录，-3 没有进行中的任务，-4 不是任务中的类型
     */
    public static function action(int $uid, int $type, int $times = 1, ?int $now = null): int
    {
        $now ??= time();
        if ($type <= 0) {
            // 任务类型不正确
            return -1;
        }
        $key = self::planKey($uid, $now);
        $task = self::plan($uid, $now);
        if ($task === []) {
            // 查询用户记录
            $log = self::log($uid);
            if ($log === []) {
                // 没有用户记录
                return -2;
            }
            // 取任务id
            $id = 0;
            $site = 0;
            for ($i = 1; $i <= 3; $i++) {
                if ($log['taskstatus' . $i] === 1) {
                    $id = $log['taskid' . $i];
                    $site = $i;
                    break;
                }
            }
            if ($id === 0) {
                // 没有任务
                return -3;
            }
            // 根据id查询任务信息
            $info = self::task($id);
            $task = ['id' => $id, 'type' => (int)($info['type'] ?? 0), 'value' => (int)($info['value'] ?? 0), 'plan' => 0, 'site' => $site];
            // 并发时只建一次，不覆盖别的请求已累加的进度
            foreach ($task as $field => $value) {
                Redis::hSetNx($key, $field, $value);
            }
            Redis::expire($key, self::PLAN_TTL);
            $task = self::plan($uid, $now);
        }

        // 执行任务
        if ($type !== $task['type']) {
            // 不是任务中的类型
            return -4;
        }
        $plan = (int)Redis::hIncrBy($key, 'plan', $times);
        Redis::expire($key, self::PLAN_TTL);
        // 检查任务是否已完成
        if ($plan >= $task['value']) {
            // 任务已完成（修改任务状态， 删除redis）
            DB::table('task_log')->where('uid', $uid)->where('taskstatus' . $task['site'], 1)->whereNull('deleted_at')
                ->update(['taskstatus' . $task['site'] => 2]);
            Redis::del($key);
        }
        return 1;
    }

    /**
     * 取正在进行中的任务详情（get_action_task_info，主城 daily_task 用）
     *
     * status：0 没有剩余次数；1 开启、没有进行中的任务；2 有进行中 / 已完成的任务（带 name / value / type / plan）
     *
     * @return array<string, mixed>
     */
    public static function actionTaskInfo(int $uid, int $now): array
    {
        $return = ['status' => 0];
        $userLog = self::log($uid);
        if ($userLog === []) {
            // 用户数据为空，但符合开启条件，返回日常任务开启状态
            return ['status' => 1, 'number' => self::DAILY_NUMBER];
        }
        if (!$userLog['number']) {
            // 没有剩余次数，任务关闭
            return $return;
        }
        // 剩余次数
        $return['number'] = $userLog['number'];
        $id = 0;
        $site = 0;
        for ($i = 1; $i <= 3; $i++) {
            if ($userLog['taskstatus' . $i] >= 1) {
                $id = $userLog['taskid' . $i];
                $site = $i;
                break;
            }
        }
        if (!$id) {
            // 没有正在进行中的任务，返回日常任务开启状态
            $return['status'] = 1;
            return $return;
        }
        // 取任务详细信息
        $task = self::task($id);
        if ($task === []) {
            // 获取任务信息失败
            $return['status'] = 1;
            return $return;
        }
        $return['status'] = 2;
        $return['name'] = $task['title'];
        $return['value'] = $task['value'];
        $return['type'] = $task['type'];
        $return['plan'] = 0;
        if ($userLog['taskstatus' . $site] === 2) {
            $return['plan'] = $task['value'];
        } else {
            // 取任务进度
            $taskPlan = self::plan($uid, $now);
            if ($taskPlan !== [] && $taskPlan['id'] === $id) {
                $return['plan'] = $taskPlan['plan'];
            }
        }
        return $return;
    }

    /**
     * 星级概率（get_star_rate）
     */
    private static function starRate(): int
    {
        $couser = 0;
        foreach (self::STAR_RATE as $val) {
            $couser += (int)round($val * 10);
        }
        $rand = mt_rand(1, $couser);
        $couser2 = 0;
        for ($i = 1; $i <= 10; $i++) {
            $couser2 += (int)round(self::STAR_RATE[$i] * 10);
            if ($rand <= $couser2) {
                return $i;
            }
        }
        return 1;
    }

    /**
     * task_log 行按旧版字段顺序（SELECT * 的列序；number 取 member_activity.task_number，见 log()）
     *
     * @param array<string, int> $row
     * @return array<string, int>
     */
    private static function ordered(array $row): array
    {
        $return = [];
        foreach (self::LOG_FIELDS as $field) {
            $return[$field] = $row[$field];
        }
        return $return;
    }
}
