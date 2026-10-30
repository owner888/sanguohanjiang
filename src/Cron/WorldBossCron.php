<?php

declare(strict_types=1);

namespace Hanjiang\Cron;

use Hanjiang\Handler\Activity\MessageHandler;
use Hanjiang\Model\WorldBoss;

/**
 * 世界 BOSS 计划任务（移植旧版 controllers/cron_acttask.php:worldboss「每日清理世界boss未领取的奖励」）
 *
 * 由 server.php 在 HTTP Worker #0 上每日 RUN_AT 调用。旧版代码包里没有 crontab，执行时间按旧版键的过期时间推定：
 * 本任务先取 12:00 场的排行，没有才取 20:00 场，发完一场就清空全部世界 BOSS 键。12:00 场的数据在最后一次攻击后 12 小时
 * （最晚 00:15）过期，20:00 场的数据最晚 08:15 过期；每人每天只能参加一场（status 5「明天再来」），
 * 所以每日一次、在 00:15 ~ 08:15 之间执行才能补发 20:00 场且不清掉当天还在进行的数据。取 00:30。
 */
final class WorldBossCron
{
    /** 每日执行时间（时:分，Asia/Shanghai） */
    public const string RUN_AT = '00:30';

    /** 旧版语言包 cron_auto_worldboss_reward */
    private const string LANG_AUTO_REWARD = '亲爱的玩家！您上次参加世界BOSS获得积分%s，系统已经帮您自动领取！';
    /** 系统信件（message_model->send_message type 1，发件人 message_system_title） */
    private const int MESSAGE_TYPE_SYSTEM = 1;
    private const string LANG_MESSAGE_SYSTEM_TITLE = '系统通知';

    /**
     * 自动发放未领取的世界 BOSS 奖励，然后清空全部世界 BOSS 键
     *
     * @return int 自动领奖的人数；两场都没有排行时返回 0 且不清空（旧版同）
     */
    public static function settle(int $now): int
    {
        // get_hit_key：12:00 以后执行时，get_reward 的最后一击判断用 20:00 场的键
        $rewardPhase = date('His', $now) >= WorldBoss::START_TIME ? 1 : 0;
        // 获取没有领取的玩家信息：先取 12:00 场，没有再取 20:00 场
        $phase = 0;
        $rewardlist = WorldBoss::rankList(0);
        if ($rewardlist === []) {
            $phase = 1;
            $rewardlist = WorldBoss::rankList(1);
            if ($rewardlist === []) {
                // 所有玩家已经领取世界BOSS奖励
                return 0;
            }
        }
        $finalUid = WorldBoss::finalUidValue($phase);

        $count = 0;
        foreach ($rewardlist as $val) {
            $uid = (int)$val['uid'];
            // 查看玩家是否已经领取过奖励
            if (WorldBoss::myHit($phase, $uid) === 0) {
                continue;
            }
            // 发送排行奖励
            $reward = WorldBoss::reward($rewardPhase, $uid, $val['rank']);
            if ($reward['intnum'] <= 0) {
                continue;
            }
            $intnum = $reward['intnum'];
            if ($uid === $finalUid) {
                $intnum *= 1.5;
            }
            // 删除伤害记录，防止重复领取（与玩家手动领奖并发时只发一次）；已经参与过活动的记录（100 秒）
            if (!WorldBoss::delMyHit($phase, $uid, 100)) {
                continue;
            }
            // 演武榜积分（旧版写回 int 字段，小数按 MySQL 四舍五入）
            WorldBoss::addArenaInt($uid, (int)round($intnum), 'cron_acttask', 'worldboss', $now);
            // 发信
            MessageHandler::sendSystemMessage([$uid], self::MESSAGE_TYPE_SYSTEM, self::LANG_MESSAGE_SYSTEM_TITLE, sprintf(self::LANG_AUTO_REWARD, $intnum));
            $count++;
        }

        // 清空所有世界BOSS缓存数据
        WorldBoss::clear();
        return $count;
    }
}
