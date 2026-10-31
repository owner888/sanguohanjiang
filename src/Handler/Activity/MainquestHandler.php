<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Model\MainQuestTask;
use Hanjiang\Model\Member;
use Hanjiang\Model\OwnSoul;
use Hanjiang\Model\PassGeneral;
use Hanjiang\Model\TraderLog;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 主线任务（移植旧版 controllers/mainquest.php + models/mainquest_model.php，见 Model\MainQuestTask）
 *
 * 客户端 missionpop.js 调用 index、get_task_reward（传任务 id）；主城的任务条走 c=member&m=index 的 mainquest。
 */
final class MainquestHandler
{
    /**
     * 初始化: c=mainquest&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        if ($uid <= 0) {
            return ['status' => 0, 'list' => []];
        }
        $result = MainQuestTask::taskList($uid, time());
        if ($result['status'] !== 1) {
            // 0 有锁直接返回；-1 初始化任务配置为空；-2 获取任务信息失败
            return ['status' => $result['status'], 'list' => []];
        }
        return ['status' => 1, 'list' => $result['list']];
    }

    /**
     * 领取任务奖励: c=mainquest&m=get_task_reward（id 任务 id）
     */
    public static function get_task_reward(Context $ctx): array
    {
        /**
         * -5 添加领奖记录失败
         * -4 当前任务奖励已领取（跳到下个任务，带 list）
         * -3 获取任务奖励信息（模版信息）失败
         * -2 任务未完成
         * -1 任务不存在
         *  0 失败
         *  1 成功
         */
        $return = ['status' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        if ($uid <= 0) {
            return $return;
        }
        $now = time();

        // 任务id
        $id = (int)$ctx->input('id', 0);

        // 查询要领取的任务信息
        $result = MainQuestTask::userTaskById($uid, $id);
        if ($result === []) {
            // 任务不存在
            $return['status'] = -1;
            return $return;
        }
        if ($result['status'] !== 1) {
            // 任务未完成
            $return['status'] = -2;
            return $return;
        }
        // 查询任务的奖励信息
        $task = MainQuestTask::taskById($result['task_id']);
        if ($task === []) {
            $return['status'] = -3;
            return $return;
        }

        // 查询该任务的奖励是否已经领取
        if (MainQuestTask::hasRewardLog($uid, $task['id'])) {
            MainQuestTask::next($uid, $result, $task['id']);
            // 当前任务奖励已领取 跳到下个任务去
            $return['status'] = -4;
            $return['list'] = MainQuestTask::taskTemp($uid, MainQuestTask::memberInfo($uid));
            return $return;
        }

        // 修改该任务的领取状态（领奖记录 (uid, task_id) 唯一，并发重复领取只有一个成功）
        if (MainQuestTask::addRewardLog($uid, $task, $now) !== 1) {
            $return['status'] = -5;
            return $return;
        }

        // 添加奖励
        $value = (int)$task['reward_value'];
        switch ((int)$task['reward_type']) {
            case 1:
                // 1金币
                Member::addCurrency($uid, gold: $value);
                break;
            case 2:
                // 2银币
                Member::addCurrency($uid, silver: $value);
                break;
            case 3:
                // 3军令
                Member::addCurrency($uid, act: $value);
                break;
            case 4:
            case 5:
            case 6:
                // 4蓝宝石 5黄宝石 6紫宝石
                TraderLog::addJade($uid, (int)$task['reward_type'] - 3, $value, class: 'mainquest', method: 'get_task_reward', now: $now);
                break;
            case 7:
                // 7突飞卡
                PassGeneral::addFreeleap($uid, $value);
                break;
            case 8:
                // 8原魂
                OwnSoul::addSoul($uid, $value, 0, 'mainquest', 'get_task_reward', $now);
                break;
            case 9:
                // 9声望
                Member::addReputation($uid, $value);
                break;
        }

        // 查询下个任务
        MainQuestTask::next($uid, $result, $task['id']);

        $member = DB::table('member')->select('silver', 'gold', 'act')->where('uid', $uid)->whereNull('deleted_at')->first() ?? [];
        // 成功返回（奖励数据，及新任务数据）
        $return['status'] = 1;
        $return['all_silver'] = (int)($member['silver'] ?? 0);
        $return['all_gold'] = (int)($member['gold'] ?? 0);
        $return['all_act'] = (int)($member['act'] ?? 0);
        $return['list'] = MainQuestTask::taskTemp($uid, MainQuestTask::memberInfo($uid));
        $return['reward'][] = ['type' => $task['reward_type'], 'value' => $task['reward_value']];
        return $return;
    }
}
