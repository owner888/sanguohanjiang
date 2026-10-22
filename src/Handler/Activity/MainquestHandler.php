<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

final class MainquestHandler
{
    /**
     * 主线任务里程碑任务池
     *
     * @var array<int, array<string, mixed>>
     */
    public const array QUEST_POOL = [
        1 => [
            'id' => 1,
            'name' => '初出茅庐',
            'desc' => '通关大兴山据点【黄巾前哨】',
            'silver' => 500,
            'gold' => 10,
            'exp' => 200,
        ],
        2 => [
            'id' => 2,
            'name' => '招兵买马',
            'desc' => '在酒馆成功招募至少一位名将',
            'silver' => 1000,
            'gold' => 20,
            'exp' => 300,
        ],
        3 => [
            'id' => 3,
            'name' => '神兵利器',
            'desc' => '在铁匠铺将任意装备强化至 2 级以上',
            'silver' => 1500,
            'gold' => 20,
            'exp' => 400,
        ],
        4 => [
            'id' => 4,
            'name' => '布阵有方',
            'desc' => '在阵法中配置两名及以上武将出战',
            'silver' => 2000,
            'gold' => 30,
            'exp' => 500,
        ],
        5 => [
            'id' => 5,
            'name' => '威震演武',
            'desc' => '在演武榜竞技场成功挑战一次对手',
            'silver' => 2500,
            'gold' => 50,
            'exp' => 600,
        ],
        6 => [
            'id' => 6,
            'name' => '声名远扬',
            'desc' => '主公等级达到 3 级以上',
            'silver' => 5000,
            'gold' => 100,
            'exp' => 1000,
        ],
    ];

    /**
     * 动态检测某个主线任务是否已达成条件
     */
    public static function checkTaskCompletion(int $taskId, int $uid): bool
    {
        return match ($taskId) {
            1 => true, // 初始第一个任务在创角后直接可完成或在击败第一个据点后完成
            2 => DB::table('general')->where('uid', $uid)->count() >= 2,
            3 => DB::table('equipment')->where('uid', $uid)->where('level', '>=', 2)->count() >= 1,
            4 => DB::table('general')->where('uid', $uid)->where('pos', '>', 0)->count() >= 2,
            5 => (function () use ($uid): bool {
                $a = DB::table('arena')->where('uid', $uid)->first();
                return $a !== null && ((int)$a['times'] < 15 || (int)$a['dateline'] > 0);
            })(),
            6 => (function () use ($uid): bool {
                $m = DB::table('member')->where('uid', $uid)->first();
                return $m !== null && (int)$m['level'] >= 3;
            })(),
            default => false,
        };
    }

    /**
     * 主线任务: c=mainquest&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->first();
        $curTaskId = max(1, (int)($member['missionid'] ?? 1));

        $taskConfig = self::QUEST_POOL[$curTaskId] ?? self::QUEST_POOL[count(self::QUEST_POOL)];
        $isCompleted = self::checkTaskCompletion($taskConfig['id'], $uid);

        return [
            'status' => 1,
            'task' => array_merge($taskConfig, [
                'status' => $isCompleted ? 1 : 0, // 0未完成 1已完成待领奖
            ]),
            'message' => 'ok',
        ];
    }

    /**
     * 领取主线奖励: c=mainquest&m=get_task_reward
     */
    public static function get_task_reward(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->first();
        $curTaskId = max(1, (int)($member['missionid'] ?? 1));

        if (!isset(self::QUEST_POOL[$curTaskId])) {
            return [
                'status' => 0,
                'message' => '已完成全部主线任务',
            ];
        }

        $taskConfig = self::QUEST_POOL[$curTaskId];
        $isCompleted = self::checkTaskCompletion($curTaskId, $uid);

        if (!$isCompleted) {
            return [
                'status' => -1,
                'message' => '主线任务尚未达成，请继续努力',
            ];
        }

        $rewardSilver = (int)$taskConfig['silver'];
        $rewardGold = (int)$taskConfig['gold'];
        $rewardExp = (int)$taskConfig['exp'];

        $newSilver = (int)($member['silver'] ?? 0) + $rewardSilver;
        $newGold = (int)($member['gold'] ?? 0) + $rewardGold;
        $newExp = (int)($member['exp'] ?? 0) + $rewardExp;
        $curLevel = (int)($member['level'] ?? 1);

        while ($newExp >= $curLevel * 100) {
            $newExp -= $curLevel * 100;
            $curLevel++;
        }

        $nextTaskId = $curTaskId + 1;

        DB::table('member')->where('uid', $uid)->update([
            'silver' => $newSilver,
            'gold' => $newGold,
            'exp' => $newExp,
            'level' => $curLevel,
            'missionid' => $nextTaskId,
        ]);

        Redis::del('player:' . $uid);

        $nextTask = self::QUEST_POOL[$nextTaskId] ?? null;

        return [
            'status' => 1,
            'reward_task_id' => $curTaskId,
            'silver' => $rewardSilver,
            'gold' => $rewardGold,
            'exp' => $rewardExp,
            'next_task' => $nextTask,
            'message' => "恭喜完成主线任务【{$taskConfig['name']}】，已领取银两 {$rewardSilver}、元宝 {$rewardGold}、经验 {$rewardExp}！",
        ];
    }
}
