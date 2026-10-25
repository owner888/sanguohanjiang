<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 每日悬赏与军务历练处理器 (DDD Activity Domain)
 */
final class TaskHandler
{
    /**
     * 基础悬赏任务池
     */
    public const array TASK_POOL = [
        1 => ['id' => 1, 'type' => 1, 'star' => 2, 'title' => '赋税征缴', 'description' => '于主城官府征收赋税 2 次', 'value' => 2, 'silver' => 8000, 'rep' => 50],
        2 => ['id' => 2, 'type' => 2, 'star' => 3, 'title' => '战役荡寇', 'description' => '出征战役讨伐山贼乱党 3 次', 'value' => 3, 'silver' => 15000, 'rep' => 100],
        3 => ['id' => 3, 'type' => 3, 'star' => 4, 'title' => '校场扬威', 'description' => '前往军校比武挑战各路豪杰 3 次', 'value' => 3, 'silver' => 30000, 'rep' => 200],
        4 => ['id' => 4, 'type' => 4, 'star' => 5, 'title' => '百炼玄兵', 'description' => '在铁匠铺强化打造装备 3 次', 'value' => 3, 'silver' => 60000, 'rep' => 500],
        5 => ['id' => 5, 'type' => 5, 'star' => 3, 'title' => '招贤纳士', 'description' => '前往酒馆对饮寻访天下名将', 'value' => 2, 'silver' => 20000, 'rep' => 150],
        6 => ['id' => 6, 'type' => 6, 'star' => 4, 'title' => '将星觉醒', 'description' => '聚灵凝魂，淬炼将魂星级 1 次', 'value' => 1, 'silver' => 40000, 'rep' => 300],
    ];

    /**
     * 获取玩家当前的每日任务看板缓存
     *
     * @return array<string, mixed>
     */
    public static function getUserTaskData(int $uid): array
    {
        $todayStr = date('Ymd');
        $cacheKey = "user_daily_tasks:{$uid}:{$todayStr}";
        $data = Redis::getJson($cacheKey);

        if (is_array($data) && isset($data['taskid1'])) {
            return $data;
        }

        // 初始化今日任务看板
        $keys = array_keys(self::TASK_POOL);
        shuffle($keys);

        $data = [
            'number' => 10,       // 每日剩余可完成次数
            'free_times' => 3,    // 免费刷新次数
            'taskstatus1' => 0,   // 0 未接, 1 进行中, 2 已完成未领奖
            'taskstatus2' => 0,
            'taskstatus3' => 0,
            'taskid1' => self::formatTask(self::TASK_POOL[$keys[0] ?? 1]),
            'taskid2' => self::formatTask(self::TASK_POOL[$keys[1] ?? 2]),
            'taskid3' => self::formatTask(self::TASK_POOL[$keys[2] ?? 3]),
        ];

        Redis::setJson($cacheKey, $data, 86400 * 2);
        return $data;
    }

    /**
     * 格式化单个任务字段
     */
    public static function formatTask(array $tpl): array
    {
        return [
            'id' => (int)$tpl['id'],
            'type' => (int)$tpl['type'],
            'star' => (int)$tpl['star'],
            'title' => (string)$tpl['title'],
            'description' => (string)$tpl['description'],
            'value' => (int)$tpl['value'],
            'plan' => 0,
            'silver' => (int)$tpl['silver'],
            'rep' => (int)($tpl['rep'] ?? 50),
            'itemname' => '',
            'quality' => 1,
            'get_type' => 0,
            'get_num' => 0,
            'get_value' => 0,
            'equip_num' => 0,
        ];
    }

    /**
     * 每日军务主界面: c=task&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $taskData = self::getUserTaskData($uid);

        // 整理向下兼容的 list 数组
        $list = [
            1 => $taskData['taskid1'],
            2 => $taskData['taskid2'],
            3 => $taskData['taskid3'],
        ];

        return array_merge($taskData, [
            'status' => 1,
            'list' => $list,
            'message' => 'ok',
        ]);
    }

    /**
     * 接取日常任务: c=task&m=accept_task
     */
    public static function accept_task(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $site = (int)($ctx->input('s', $ctx->input('site', $ctx->input('id', 1))));
        if ($site < 1 || $site > 3) {
            $site = 1;
        }

        $todayStr = date('Ymd');
        $cacheKey = "user_daily_tasks:{$uid}:{$todayStr}";
        $data = self::getUserTaskData($uid);

        // 将该槽位设为进行中
        $data["taskstatus{$site}"] = 1;
        // 自动完成以方便体验，将 plan 提升
        if (isset($data["taskid{$site}"])) {
            $data["taskid{$site}"]['plan'] = $data["taskid{$site}"]['value'];
            $data["taskstatus{$site}"] = 2; // 可领奖
        }

        Redis::setJson($cacheKey, $data, 86400 * 2);

        return [
            'status' => 1,
            'site' => $site,
            'message' => '接取军务成功',
        ];
    }

    /**
     * 放弃日常任务: c=task&m=give_up
     */
    public static function give_up(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $site = (int)($ctx->input('s', $ctx->input('site', 1)));
        if ($site < 1 || $site > 3) {
            $site = 1;
        }

        $todayStr = date('Ymd');
        $cacheKey = "user_daily_tasks:{$uid}:{$todayStr}";
        $data = self::getUserTaskData($uid);

        $data["taskstatus{$site}"] = 0;
        if (isset($data["taskid{$site}"])) {
            $data["taskid{$site}"]['plan'] = 0;
        }

        Redis::setJson($cacheKey, $data, 86400 * 2);

        return [
            'status' => 1,
            'site' => $site,
            'message' => '已放弃该军务',
        ];
    }

    /**
     * 领赏交付军务: c=task&m=get_reward
     */
    public static function get_reward(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $site = (int)($ctx->input('s', $ctx->input('site', $ctx->input('id', 1))));
        if ($site < 1 || $site > 3) {
            $site = 1;
        }

        $todayStr = date('Ymd');
        $cacheKey = "user_daily_tasks:{$uid}:{$todayStr}";
        $data = self::getUserTaskData($uid);

        $task = $data["taskid{$site}"] ?? null;
        $rewardSilver = $task ? (int)$task['silver'] : 10000;
        $rewardRep = $task ? (int)($task['rep'] ?? 50) : 50;

        MemberHandler::addCurrency($uid, silver: $rewardSilver, reputation: $rewardRep);

        // 减少 1 次剩余次数并刷新该槽位
        $data['number'] = max(0, (int)$data['number'] - 1);
        $data["taskstatus{$site}"] = 0;

        // 随机刷新一个新任务
        $allIds = array_keys(self::TASK_POOL);
        $newId = $allIds[array_rand($allIds)];
        $data["taskid{$site}"] = self::formatTask(self::TASK_POOL[$newId]);

        Redis::setJson($cacheKey, $data, 86400 * 2);

        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        $allSilver = (int)($member['silver'] ?? 0);

        $newList = [
            1 => $data['taskid1'],
            2 => $data['taskid2'],
            3 => $data['taskid3'],
        ];

        return [
            'status' => 1,
            'all_silver' => $allSilver,
            'silver' => $rewardSilver,
            'number' => (int)$data['number'],
            'list' => $newList,
            'get_type' => 0,
            'get_num' => 0,
            'get_value' => 0,
            'itemname' => '',
            'itemnum' => 0,
            'quality' => 1,
            'message' => '领赏成功',
        ];
    }

    /**
     * 刷新日常任务: c=task&m=refresh
     */
    public static function refresh(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $todayStr = date('Ymd');
        $cacheKey = "user_daily_tasks:{$uid}:{$todayStr}";
        $data = self::getUserTaskData($uid);

        $freeTimes = (int)($data['free_times'] ?? 0);
        $costGold = 0;

        if ($freeTimes > 0) {
            $data['free_times'] = $freeTimes - 1;
        } else {
            $costGold = 5;
            $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
            if ((int)($member['gold'] ?? 0) < $costGold) {
                return ['status' => -1, 'message' => '元宝不足，无法刷新任务'];
            }
            MemberHandler::deductCurrency($uid, gold: $costGold);
        }

        // 刷新任务
        $allIds = array_keys(self::TASK_POOL);
        shuffle($allIds);
        for ($i = 1; $i <= 3; $i++) {
            if ((int)($data["taskstatus{$i}"] ?? 0) === 0) {
                $data["taskid{$i}"] = self::formatTask(self::TASK_POOL[$allIds[$i - 1] ?? 1]);
            }
        }

        Redis::setJson($cacheKey, $data, 86400 * 2);

        $freshMember = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();

        $newList = [
            1 => $data['taskid1'],
            2 => $data['taskid2'],
            3 => $data['taskid3'],
        ];

        return [
            'status' => 1,
            'price' => $costGold,
            'all_gold' => (int)($freshMember['gold'] ?? 0),
            'free_times' => (int)$data['free_times'],
            'list' => $newList,
            'message' => '军务刷新成功',
        ];
    }
}
