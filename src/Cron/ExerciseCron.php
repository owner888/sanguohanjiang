<?php

declare(strict_types=1);

namespace Hanjiang\Cron;

use Hanjiang\Handler\Activity\MessageHandler;
use Hanjiang\MasterData\Exercise as ExerciseRule;
use Hanjiang\Model\Exercise;
use Hanjiang\Model\Member;
use OmniPHP\Database\DB;

/**
 * 整军演武计划任务（移植旧版 controllers/cron.php:clear_exercise「清除整军演武排行」与 sale_gift「活动结束后自动开礼包」）
 *
 * 由 server.php 在 HTTP Worker #0 上调用。旧版代码包里没有 crontab：
 * - clear_exercise 清排行（旧版还清 exercise_rule 缓存，新版加成表在 MasterData，无缓存）：排行按今日征收次数，
 *   每日 7 点征收计划任务把次数归零，按每天 CLEAR_AT 执行；
 * - sale_gift 只在活动结束后 3 天内执行，按每 SALE_INTERVAL 秒执行（已开的礼包清零，不会重复发放）。
 */
final class ExerciseCron
{
    /** 清排行时间（时:分） */
    public const string CLEAR_AT = '07:00';

    /** 自动开礼包的执行间隔（秒） */
    public const int SALE_INTERVAL = 3600;

    /** 活动结束后可自动开礼包的时长（秒） */
    private const int SALE_WINDOW = 86400 * 3;

    private const int MESSAGE_TYPE_SYSTEM = 1;
    private const string LANG_MESSAGE_SYSTEM_TITLE = '系统通知';
    private const string LANG_SALE_EXERCISE_GIFT = '整军演武活动已结束。剩余的%s个礼包，系统已帮您领取，共获得银币%s、军令%s个。';

    /**
     * 清除整军演武排行（clear_exercise）
     */
    public static function clearRank(): void
    {
        Exercise::clearRank();
    }

    /**
     * 活动结束后自动开启剩余礼包（sale_gift）：银币礼包数 = 礼包数 × 银币出现概率%，军令礼包数同理；
     * 银币 = 参数 × 获得礼包时等级 × 银币礼包数，军令 = 参数 × 军令礼包数（不抽倍数）；以礼包数清零抢占，发信通知
     *
     * @return int -1 无活动；-2 活动未结束；-3 结束超过 3 天；0 没有礼包；1 已发放
     */
    public static function saleGift(int $now): int
    {
        $activity = Exercise::activity();
        if ($activity === null || $activity['starttime'] > $now) {
            return -1;
        }
        // 判断活动是否结束
        if ($activity['endtime'] > $now) {
            return -2;
        }
        // 判断是否可发放奖励（活动已结束，并且在活动结束后三天内）
        if ($activity['endtime'] + self::SALE_WINDOW < $now) {
            return -3;
        }
        $gift = DB::table('member_activity')->select('uid', 'exercise_gift', 'exercise_level')->where('exercise_gift', '>', 0)
            ->whereNull('deleted_at')->get();
        if ($gift === []) {
            return 0;
        }
        $rule = array_values(ExerciseRule::ROWS);
        foreach ($gift as $value) {
            $uid = (int)$value['uid'];
            $num = (int)$value['exercise_gift'];
            $gift1 = (int)($num * $rule[0]['rate'] / 100);
            $gift2 = (int)($num * $rule[1]['rate'] / 100);
            $silver = (int)$rule[0]['num'] * (int)$value['exercise_level'] * $gift1;
            $act = (int)$rule[1]['num'] * $gift2;
            // 更新整军演武礼包为0（clear_exercise_gift，以礼包数条件更新抢占）
            $clear = DB::table('member_activity')->where('uid', $uid)->where('exercise_gift', $num)->whereNull('deleted_at')
                ->update(['exercise_gift' => 0, 'exercise_level' => 0]);
            if ($clear !== 1) {
                continue;
            }
            // open_exercise_gift
            Member::addCurrency($uid, silver: $silver, act: $act);
            MessageHandler::sendSystemMessage([$uid], self::MESSAGE_TYPE_SYSTEM, self::LANG_MESSAGE_SYSTEM_TITLE,
                sprintf(self::LANG_SALE_EXERCISE_GIFT, $num, $silver, $act));
        }
        return 1;
    }
}
