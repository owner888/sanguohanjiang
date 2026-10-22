<?php

declare(strict_types=1);

namespace Hanjiang\Handler;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

final class GeneraltaskHandler
{
    public const array EXPEDITIONS = [
        1 => ['id' => 1, 'name' => '古栈道斥候侦察', 'duration' => 60, 'silver' => 8000, 'soul' => 20],
        2 => ['id' => 2, 'name' => '南蛮瘴气密林巡狩', 'duration' => 180, 'silver' => 20000, 'soul' => 50],
        3 => ['id' => 3, 'name' => '昆仑仙山探秘远征', 'duration' => 300, 'silver' => 50000, 'soul' => 120],
    ];

    /**
     * 远征主界面: c=generaltask&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $state = Redis::getJson('general_task:' . $uid) ?: [
            'task_id' => 0,
            'gid' => 0,
            'start_time' => 0,
        ];

        $taskId = (int)$state['task_id'];
        $startTime = (int)$state['start_time'];
        $now = time();

        $cfg = self::EXPEDITIONS[$taskId] ?? null;
        $isFinished = false;
        $remaining = 0;

        if ($cfg !== null && $startTime > 0) {
            $elapsed = $now - $startTime;
            $remaining = max(0, (int)$cfg['duration'] - $elapsed);
            $isFinished = ($remaining === 0);
        }

        return [
            'status' => 1,
            'list' => array_values(self::EXPEDITIONS),
            'current_task' => $cfg['name'] ?? '无远征队伍',
            'is_running' => ($taskId > 0 && !$isFinished) ? 1 : 0,
            'can_harvest' => $isFinished ? 1 : 0,
            'remaining_seconds' => $remaining,
            'message' => 'ok',
        ];
    }

    /**
     * 派遣武将出征远征: c=generaltask&m=action
     */
    public static function action(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $taskId = (int)($ctx->input('id', $ctx->input('task_id', 1)));
        $gid = (int)($ctx->input('gid', 1));

        if (!isset(self::EXPEDITIONS[$taskId])) {
            return ['status' => 0, 'message' => '远征路线不存在'];
        }

        $general = DB::table('general')->where('uid', $uid)->where('id', $gid)->whereNull('deleted_at')->first();
        if ($general === null) {
            return ['status' => -1, 'message' => '武将不存在'];
        }

        $state = Redis::getJson('general_task:' . $uid);
        if (is_array($state) && !empty($state['task_id'])) {
            $elapsed = time() - (int)$state['start_time'];
            $curCfg = self::EXPEDITIONS[$state['task_id']] ?? null;
            if ($curCfg && $elapsed < $curCfg['duration']) {
                return ['status' => 0, 'message' => '已有队伍正在远征中，请等待凯旋归来'];
            }
        }

        $cfg = self::EXPEDITIONS[$taskId];

        Redis::setJson('general_task:' . $uid, [
            'task_id' => $taskId,
            'gid' => $gid,
            'start_time' => time(),
        ], 86400 * 2);

        return [
            'status' => 1,
            'task_name' => $cfg['name'],
            'general_name' => $general['name'],
            'duration' => $cfg['duration'],
            'message' => "【{$general['name']}】率部启程前往【{$cfg['name']}】！预计 {$cfg['duration']} 秒后凯旋！",
        ];
    }

    /**
     * 远征归来凯旋领赏: c=generaltask&m=get_report
     */
    public static function get_report(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $state = Redis::getJson('general_task:' . $uid);

        if (!is_array($state) || empty($state['task_id'])) {
            return ['status' => 0, 'message' => '当前没有正在远征的队伍'];
        }

        $taskId = (int)$state['task_id'];
        $cfg = self::EXPEDITIONS[$taskId] ?? null;
        if ($cfg === null) {
            Redis::del('general_task:' . $uid);
            return ['status' => 0, 'message' => '远征任务数据异常'];
        }

        $elapsed = time() - (int)$state['start_time'];
        if ($elapsed < $cfg['duration']) {
            $wait = $cfg['duration'] - $elapsed;
            return ['status' => 0, 'message' => "远征队伍尚在途中，还需 {$wait} 秒方可凯旋归来！"];
        }

        $rewardSilver = (int)$cfg['silver'];
        $rewardSoul = (int)$cfg['soul'];

        MemberHandler::addCurrency($uid, silver: $rewardSilver, soul: $rewardSoul);
        Redis::del('general_task:' . $uid);

        return [
            'status' => 1,
            'silver' => $rewardSilver,
            'soul' => $rewardSoul,
            'message' => "远征大捷！队伍凯旋归来，上缴战利品：银两 {$rewardSilver}、将魂 {$rewardSoul}！",
        ];
    }
}
