<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

final class TaskHandler
{
    public const array BOUNTIES = [
        1 => ['id' => 1, 'star' => 2, 'title' => '平定黑山盗贼', 'silver' => 8000, 'rep' => 50],
        2 => ['id' => 2, 'star' => 3, 'title' => '剿灭白波余孽', 'silver' => 15000, 'rep' => 100],
        3 => ['id' => 3, 'star' => 4, 'title' => '伏击西凉斥候', 'silver' => 30000, 'rep' => 200],
        4 => ['id' => 4, 'star' => 5, 'title' => '斩首黄巾渠帅', 'silver' => 60000, 'rep' => 500],
    ];

    /**
     * 获取玩家悬赏任务看板
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getBounties(int $uid): array
    {
        $cached = Redis::getJson('user_tasks:' . $uid);
        if (is_array($cached)) {
            return $cached;
        }

        $list = [];
        foreach (self::BOUNTIES as $id => $b) {
            $list[$id] = array_merge($b, [
                'accepted' => 0,
                'finished' => 1, // 达成条件后可领
            ]);
        }
        Redis::setJson('user_tasks:' . $uid, $list, 86400);
        return $list;
    }

    /**
     * 悬赏大厅主界面: c=task&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $tasks = self::getBounties($uid);

        return [
            'status' => 1,
            'list' => array_values($tasks),
            'refresh_cost' => 3000,
            'message' => 'ok',
        ];
    }

    /**
     * 接取悬赏令: c=task&m=accept_task
     */
    public static function accept_task(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $taskId = (int)($ctx->input('id', $ctx->input('task_id', 1)));

        $tasks = self::getBounties($uid);
        if (!isset($tasks[$taskId])) {
            return ['status' => 0, 'message' => '悬赏任务不存在'];
        }

        $tasks[$taskId]['accepted'] = 1;
        Redis::setJson('user_tasks:' . $uid, $tasks, 86400);

        return [
            'status' => 1,
            'task_id' => $taskId,
            'title' => $tasks[$taskId]['title'],
            'message' => "成功接取悬赏令【{$tasks[$taskId]['title']}】！速速出征讨伐！",
        ];
    }

    /**
     * 交付悬赏领赏: c=task&m=get_reward
     */
    public static function get_reward(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $taskId = (int)($ctx->input('id', $ctx->input('task_id', 1)));

        $tasks = self::getBounties($uid);
        if (!isset($tasks[$taskId])) {
            return ['status' => 0, 'message' => '悬赏任务不存在'];
        }

        $t = $tasks[$taskId];
        $rewardSilver = (int)$t['silver'];
        $rewardRep = (int)$t['rep'];

        MemberHandler::addCurrency($uid, silver: $rewardSilver, reputation: $rewardRep);

        // 领奖后重置该悬赏位
        $tasks[$taskId]['accepted'] = 0;
        Redis::setJson('user_tasks:' . $uid, $tasks, 86400);

        return [
            'status' => 1,
            'silver' => $rewardSilver,
            'reputation' => $rewardRep,
            'message' => "悬赏军务顺利交付！领取朝廷赏金：银两 {$rewardSilver}、声望 {$rewardRep}！",
        ];
    }

    /**
     * 揭榜刷新悬赏令: c=task&m=refresh
     */
    public static function refresh(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $costSilver = 3000;

        if (!MemberHandler::deductCurrency($uid, silver: $costSilver)) {
            return ['status' => -1, 'message' => "银两不足，刷新悬赏榜需要 {$costSilver} 银两"];
        }

        Redis::del('user_tasks:' . $uid);
        $newTasks = self::getBounties($uid);

        return [
            'status' => 1,
            'list' => array_values($newTasks),
            'message' => '悬赏公告栏已重新揭榜张贴！',
        ];
    }
}
