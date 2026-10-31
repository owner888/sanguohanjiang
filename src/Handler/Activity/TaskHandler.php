<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\MasterData\Equip;
use Hanjiang\Model\DailyTask;
use Hanjiang\Model\Equipment;
use Hanjiang\Model\MainQuestTask;
use Hanjiang\Model\Member;
use Hanjiang\Model\MemberJade;
use Hanjiang\Model\StatPay;
use Hanjiang\Model\TraderLog;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 日常任务（移植旧版 controllers/task.php + models/task_model.php，见 Model\DailyTask）
 *
 * 客户端 dailytaskpop.js 调用 index、accept_task（s 任务位置 1~3）、give_up、refresh、get_reward（s）。
 * 同一玩家的领取 / 放弃 / 刷新 / 领奖在玩家行锁里串行执行（多 Worker 下免费刷新、领奖不会重复）。
 */
final class TaskHandler
{
    /** 旧版语言包 base_lang.php base_chip */
    private const string LANG_BASE_CHIP = '碎片';

    /**
     * 初始化接口: c=task&m=index
     */
    public static function index(Context $ctx): array
    {
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        if ((int)$member['level'] < DailyTask::OPEN_LEVEL) {
            // 角色等级不够
            $return['status'] = -1;
            return $return;
        }
        $uid = (int)$member['uid'];
        $level = (int)$member['level'];
        $now = time();

        // 查询用户记录
        $log = DailyTask::taskLog($uid, $level, $now);
        // 得到正在进行中的任务
        $site = 0;
        $itemarr = [];
        for ($i = 1; $i <= 3; $i++) {
            if ($log['taskstatus' . $i] === 1) {
                $site = $i;
            }
            $task = $log['taskid' . $i];
            if (($task['equip_type'] ?? 0) <= 2 && ($task['equip_id'] ?? 0)) {
                // 查询道具名字
                $itemarr[$i] = $task['equip_id'];
            }
            $log['taskid' . $i] = self::withReward($task, $level);
        }
        if ($itemarr !== []) {
            $item = DailyTask::tempInfo(array_values($itemarr));
            if ($item === []) {
                // 装备获取失败
                $return['status'] = -2;
                return $return;
            }
            foreach ($itemarr as $key => $val) {
                $log['taskid' . $key]['itemname'] = $item[$val]['name'] ?? null;
                $log['taskid' . $key]['quality'] = $item[$val]['quality'] ?? null;
                if ($log['taskid' . $key]['equip_type'] === 2) {
                    $log['taskid' . $key]['itemname'] .= self::LANG_BASE_CHIP;
                }
                unset($log['taskid' . $key]['equip_type']);
            }
        }

        // 根据任务id查询任务进度
        if ($site) {
            $info = DailyTask::plan($uid, $now);
            $log['taskid' . $site]['plan'] = 0;
            if ($info !== [] && $info['id'] === $log['taskid' . $site]['id']) {
                if ($info['plan'] >= $log['taskid' . $site]['value']) {
                    // 任务已完成，修改任务状态（旧版只改返回值，库里的状态由 task_action 完成时修改）
                    $log['taskstatus' . $site] = 2;
                    // 主线任务 12完成任意日常任务
                    MainQuestTask::action($uid, 12, 1);
                } else {
                    $log['taskid' . $site]['plan'] = $info['plan'];
                }
            }
        }
        unset($log['dateline']);
        $return = $log;
        $return['status'] = 1;
        return $return;
    }

    /**
     * 接受任务: c=task&m=accept_task（s 任务位置）
     */
    public static function accept_task(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $site = self::site($ctx);
        if ($uid <= 0) {
            return ['status' => 0];
        }
        $now = time();
        return Member::locked($uid, static function () use ($uid, $site, $now): array {
            $return = ['status' => 0];
            // 取用户信息
            $log = DailyTask::log($uid);
            if ($log === []) {
                // 用户信息不存在
                $return['status'] = -1;
                return $return;
            }
            if (!$log['number']) {
                $return['status'] = -2;
                return $return;
            }
            for ($i = 1; $i <= 3; $i++) {
                if ($log['taskstatus' . $i] >= 1) {
                    // 还有正在进行中的任务，不能同时领取2个任务
                    $return['status'] = -3;
                    return $return;
                }
            }
            // 领取任务，修改任务状态
            if (DailyTask::updateStatus($uid, $site, 1) === 1) {
                // 取任务信息，把任务信息存到redis里面
                $info = DailyTask::task($log['taskid' . $site]);
                DailyTask::startPlan($uid, $info, $site, $now);
                $return['status'] = 1;
            }
            return $return;
        });
    }

    /**
     * 放弃任务: c=task&m=give_up
     */
    public static function give_up(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        if ($uid <= 0) {
            return ['status' => 0];
        }
        $now = time();
        return Member::locked($uid, static function () use ($uid, $now): array {
            $return = ['status' => 0];
            // 取用户数据
            $log = DailyTask::log($uid);
            if ($log === []) {
                $return['status'] = -1;
                return $return;
            }
            $site = 0;
            for ($i = 1; $i <= 3; $i++) {
                if ($log['taskstatus' . $i] === 1) {
                    $site = $i;
                    break;
                }
            }
            if (!$site) {
                // 操作无效(没有可放弃的任务)
                $return['status'] = -2;
                return $return;
            }
            // 执行放弃（修改任务状态，删除redis）
            DailyTask::updateStatus($uid, $site, 0);
            DailyTask::clearPlan($uid, $now);
            $return['status'] = 1;
            return $return;
        });
    }

    /**
     * 刷新任务: c=task&m=refresh（未领取的任务位换新任务；每天第一次免费，之后每次 5 元宝）
     */
    public static function refresh(Context $ctx): array
    {
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return ['status' => 0];
        }
        $uid = (int)$member['uid'];
        $level = (int)$member['level'];
        return Member::locked($uid, static function () use ($uid, $level): array {
            $return = ['status' => 0];
            // 查询用户记录
            $log = DailyTask::log($uid);
            if ($log === []) {
                // 用户记录为空
                $return['status'] = -2;
                return $return;
            }
            $gold = (int)DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('gold');
            // 检查金币银币是否足够
            if ($log['free_times'] === 0 && $gold < DailyTask::PRICE) {
                $return['status'] = -1;
                return $return;
            }

            $arr = [];
            for ($i = 1; $i <= 3; $i++) {
                if ($log['taskstatus' . $i] === 0) {
                    $arr[] = $i;
                }
            }
            // 按个数查询
            $task = DailyTask::rand($level, count($arr));

            $list = $uplist = $itemarr = [];
            foreach ($arr as $key => $val) {
                if (!isset($task[$key + 1])) {
                    continue;
                }
                $one = $task[$key + 1];
                $uplist[$val] = $one['id'];
                if ($one['equip_type'] <= 2 && $one['equip_id']) {
                    $itemarr[$val] = $one['equip_id'];
                }
                $list[$val] = self::withReward($one, $level);
            }
            if ($itemarr !== []) {
                $item = DailyTask::tempInfo(array_values($itemarr));
                if ($item === []) {
                    // 装备获取失败
                    $return['status'] = -3;
                    return $return;
                }
                foreach ($itemarr as $key => $val) {
                    if (isset($item[$val])) {
                        $list[$key]['itemname'] = $item[$val]['name'];
                        $list[$key]['quality'] = $item[$val]['quality'];
                        if ($list[$key]['equip_type'] === 2) {
                            $list[$key]['itemname'] .= self::LANG_BASE_CHIP;
                        }
                    }
                    unset($list[$key]['equip_type']);
                }
            }

            // 扣除金币
            if ($log['free_times'] === 0) {
                // 不是免费才扣钱
                Member::deductCurrency($uid, gold: DailyTask::PRICE);
                // 消费统计记录
                StatPay::record($uid, 'task_refresh', DailyTask::PRICE);
            }

            // 修改数据
            if ($uplist !== []) {
                $return = ['status' => DailyTask::updateTasks($uid, $uplist)];
            }
            $return['price'] = DailyTask::PRICE;
            $return['all_gold'] = (int)DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('gold');
            $return['list'] = $list;
            return $return;
        });
    }

    /**
     * 领取奖励: c=task&m=get_reward（s 任务位置）
     */
    public static function get_reward(Context $ctx): array
    {
        $member = self::member(MemberHandler::resolveUid($ctx));
        $site = self::site($ctx);
        if ($member === null) {
            return ['status' => 0];
        }
        $uid = (int)$member['uid'];
        $level = (int)$member['level'];
        $now = time();
        $return = Member::locked($uid, static function () use ($uid, $level, $site, $now): array {
            $return = ['status' => 0];
            // 取用户信息
            $log = DailyTask::log($uid);
            if ($log === []) {
                $return['status'] = -1;
                return $return;
            }
            if ($log['taskstatus' . $site] !== 2) {
                // 任务未完成，无法领取
                $return['status'] = -2;
                return $return;
            }

            // 查询该任务奖励
            $info = DailyTask::task($log['taskid' . $site]);
            $silver = (int)$info['silver'] * $level;
            // 添加银币
            Member::addCurrency($uid, silver: $silver);
            if ($info['equip_type'] <= 2 && $info['equip_id']) {
                $item = Equip::ROWS[$info['equip_id']] ?? null;
                if ($item !== null) {
                    // 道具存在
                    $return['itemid'] = $item['id'];
                    $return['quality'] = $item['quality'];
                    $return['itemname'] = $item['name'];
                    $return['itemnum'] = $info['equip_num'];
                    if ($info['equip_type'] === 2) {
                        // 添加碎片
                        Equipment::addChip($uid, $item['id'], $info['equip_num'], 'task', 'get_reward', $now);
                        $return['itemname'] .= self::LANG_BASE_CHIP;
                    } else {
                        // 添加道具
                        Equipment::addFromTemplate($uid, $item['id']);
                    }
                }
            } elseif ($info['equip_type'] === 3) {
                // 紫宝石
                TraderLog::addJade($uid, 3, $info['equip_id'], class: 'task', method: 'get_reward', now: $now);
            } elseif ($info['equip_type'] === 4) {
                // 玉石
                MemberJade::add($uid, $info['equip_id'], 'task', 'get_reward', $now);
            } elseif ($info['equip_type'] === 5) {
                // 声望
                Member::addReputation($uid, $info['equip_id']);
            }

            // 修改状态
            DailyTask::updateStatus($uid, $site, 0);
            // 刷新数据,得到一个新的任务
            $task = DailyTask::rand($level, 1);
            $list = $uplist = [];
            $list[$site] = $task[1];
            $uplist[$site] = $task[1]['id'];
            // 奖励中的银币
            $list[$site]['silver'] = $list[$site]['silver'] * $level;
            // 奖励中的道具
            if ($task[1]['equip_type'] <= 2 && $task[1]['equip_id']) {
                $item = Equip::ROWS[$task[1]['equip_id']] ?? null;
                if ($item === null) {
                    // 装备获取失败
                    $return['status'] = -3;
                    return $return;
                }
                $list[$site]['itemname'] = $item['name'];
                $list[$site]['quality'] = $item['quality'];
                if ($list[$site]['equip_type'] === 2) {
                    $list[$site]['itemname'] .= self::LANG_BASE_CHIP;
                }
            }
            $list[$site]['get_type'] = $list[$site]['equip_type'];
            $list[$site]['get_value'] = $list[$site]['equip_id'];
            $list[$site]['get_num'] = $list[$site]['equip_num'];
            unset($list[$site]['equip_id'], $list[$site]['equip_type']);
            // 修改数据
            DailyTask::updateTasks($uid, $uplist, 0);
            // 修改任务次数
            $return['number'] = DailyTask::updateNumber($uid, $log['number'], $now);
            $return['status'] = 1;
            $return['get_type'] = $info['equip_type'];
            $return['get_value'] = $info['equip_id'];
            $return['get_num'] = $info['equip_num'];
            $return['silver'] = $silver;
            $return['list'] = $list;
            $return['star'] = $info['star'];
            return $return;
        });
        if ($return['status'] !== 1) {
            return $return;
        }
        $star = $return['star'];
        unset($return['star']);

        // 主线任务 12完成任意日常任务
        MainQuestTask::action($uid, 12, 1);
        // 主线任务 13完成任意X星级任务
        MainQuestTask::action($uid, 13, $star);

        // 数据返回
        $return['all_silver'] = (int)DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('silver');
        return self::orderReward($return);
    }

    /**
     * 任务的奖励展示字段：silver 按主公等级倍数，get_type / get_value / get_num，去掉 equip_id（旧版 index / refresh）
     *
     * @param array<string, mixed> $task
     * @return array<string, mixed>
     */
    private static function withReward(array $task, int $level): array
    {
        if ($task === []) {
            return $task;
        }
        $task['silver'] = $task['silver'] * $level;
        $task['get_type'] = $task['equip_type'];
        $task['get_value'] = $task['equip_id'];
        $task['get_num'] = $task['equip_num'];
        unset($task['equip_id']);
        return $task;
    }

    /**
     * get_reward 的字段顺序同旧版（itemid… 在前，number、status、get_* 在后）
     *
     * @param array<string, mixed> $return
     * @return array<string, mixed>
     */
    private static function orderReward(array $return): array
    {
        $order = ['status', 'itemid', 'quality', 'itemname', 'itemnum', 'number', 'get_type', 'get_value', 'get_num', 'silver', 'all_silver', 'list'];
        $sorted = [];
        foreach ($order as $key) {
            if (array_key_exists($key, $return)) {
                $sorted[$key] = $return[$key];
            }
        }
        return $sorted;
    }

    /**
     * 任务位置（s，旧版 0 及以下按 1、超过 3 按 3）
     */
    private static function site(Context $ctx): int
    {
        $site = (int)$ctx->input('s', 0);
        $site = $site > 0 ? $site : 1;
        return $site > 3 ? 3 : $site;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function member(int $uid): ?array
    {
        if ($uid <= 0) {
            return null;
        }
        return DB::table('member')->select('uid', 'level', 'gold', 'silver', 'vip')->where('uid', $uid)->whereNull('deleted_at')->first();
    }
}
