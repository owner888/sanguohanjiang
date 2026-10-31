<?php

declare(strict_types=1);

namespace Hanjiang\Cron;

use Hanjiang\Cross\GvgServer;
use Hanjiang\Cross\Server;
use Hanjiang\Handler\Activity\MessageHandler;
use Hanjiang\Handler\General\TavernHandler;
use Hanjiang\MasterData\Equip;
use Hanjiang\Model\ActivityClock;
use Hanjiang\Model\Equipment;
use Hanjiang\Model\Gvg;
use Hanjiang\Model\Member;
use Hanjiang\Model\MemberJade;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Logger;

/**
 * 群服争霸计划任务（移植旧版 battle controllers/gvg.php:run_pk 与 s1 controllers/cron_gvg.php:bet_reward / push_member_reward）
 *
 * 由 server.php 在 HTTP Worker #0 上执行，旧版代码包里没有 crontab：
 * - run_pk 每 PK_INTERVAL 秒（推定每分钟：开打前 5 分钟的锁定期内都会执行，重复执行不会重打），只在旧版注释的
 *   「计划任务时间：15 日 5 点至 18 日 22 点」内执行；
 * - bet_reward 与 push_member_reward 每 REWARD_INTERVAL 秒（推定 5 分钟）。bet_reward 加的 10 分钟锁旧版解错了键
 *   （解的是 push_member_reward），照搬：最多 10 分钟发一次押注奖励。
 * 押注奖励与晋级奖励记录要求活动表 gvg 开启（旧版 activity_status('gvg')，旧版数据 starttime = endtime = 0，从未开启；
 * 开发环境可用 ACTIVITY_OPEN 打开）；已生成的奖励记录（_get_reward）不看活动表，照常发放。
 */
final class GvgCron
{
    /** run_pk 执行间隔（秒，推定） */
    public const int PK_INTERVAL = 60;

    /** 奖励计划任务执行间隔（秒，推定） */
    public const int REWARD_INTERVAL = 300;

    private const int MESSAGE_TYPE_SYSTEM = 1;
    private const string LANG_MESSAGE_SYSTEM_TITLE = '系统通知';

    /** s1 lang gvg_lang.php / base_lang.php */
    private const string LANG_BET_REWARD = '您在群服争霸%s中，押注正确，获得奖励%s。';
    private const string LANG_SERVER_REWARD1 = '您所在的服务器在群服争霸中获得冠军，您获得奖励%s。';
    private const string LANG_SERVER_REWARD2 = '您所在的服务器在群服争霸中晋级%s，您获得奖励%s。';
    private const array LANG_BASE = ['gold' => '元宝', 'silver' => '银币', 'jade' => '玉石', 'jade3' => '紫宝石', 'soul' => '原魂'];

    /**
     * battle run_pk（只在每月 15 日 5:00 到 18 日 22:00 执行）
     */
    public static function runPk(int $now): int
    {
        $ym = date('Y-m-', $now);
        if ($now < (int)strtotime($ym . '15 05:00:00') || $now > (int)strtotime($ym . '18 22:00:00')) {
            return 0;
        }
        return GvgServer::runPk($now);
    }

    /**
     * bet_reward：押注奖励
     *
     * @return int 发放了几笔；-1 活动未开启；-2 被锁
     */
    public static function betReward(int $now): int
    {
        if (!ActivityClock::isOpen('gvg', $now)) {
            // 活动未开启
            return -1;
        }
        if (self::lock('bet_reward')) {
            return -2;
        }
        $phase = (int)date('Ym', $now);
        // 获取已押注场次与比赛结果
        foreach (Gvg::betRound($phase) as $value) {
            $result = GvgServer::betResult($value['round'], $value['roundid']);
            if ($result['status'] === 1) {
                Gvg::updateBetResult($phase, $value['round'], $value['roundid'], $result['win']);
            }
        }
        // 发放有结果的场次
        $reward = Gvg::rewardList();
        $num = 0;
        foreach (Gvg::betResult($phase) as $value) {
            $gold = $reward[$value['round']]['bet'] ?? 0;
            if (Gvg::updateBetStatus($value['id'], $now) !== 1) {
                continue;
            }
            $roundName = Gvg::LANG_ROUND[$value['round']] ?? '';
            MessageHandler::sendSystemMessage([$value['uid']], self::MESSAGE_TYPE_SYSTEM, self::LANG_MESSAGE_SYSTEM_TITLE,
                sprintf(self::LANG_BET_REWARD, $roundName, $gold . self::LANG_BASE['gold']));
            Member::addCurrency($value['uid'], gold: $gold);
            $num++;
        }
        // 旧版解锁写成了 push_member_reward（bet_reward 的锁一直到过期）
        self::unlock('push_member_reward');
        return $num;
    }

    /**
     * push_member_reward：发放晋级奖励（包括服务器全体和参赛者）
     */
    public static function pushMemberReward(int $now): int
    {
        if (self::lock('push_member_reward')) {
            return -2;
        }
        try {
            self::addRewardLog($now);
            return self::getReward($now);
        } finally {
            self::unlock('push_member_reward');
        }
    }

    // ---------------------------------------------------------------------------------------------

    /**
     * _add_reward_log：从战斗服务器取本服的胜场，每次为一个还没生成记录的胜场（轮次最早的）生成奖励记录：
     * 本服参赛玩家记参赛奖励（type 1），其余 60 级以上玩家记全服奖励（type 2）
     */
    private static function addRewardLog(int $now): void
    {
        if (!ActivityClock::isOpen('gvg', $now)) {
            return;
        }
        $phase = (int)date('Ym', $now);
        // 检查锁、设置锁（180 秒）
        if (!Gvg::rewardLock()) {
            return;
        }
        // 获取本地存的胜场
        $local = [];
        foreach (DB::table('gvg_round')->select('round', 'islock')->where('phase', $phase)->whereNull('deleted_at')->get() as $value) {
            $local[] = (int)$value['round'];
            if ((int)$value['round'] === 1 && (int)$value['islock'] === 1) {
                // 所有奖励记录已生成
                return;
            }
        }
        $serverid = Server::id();
        // 从战斗服务器获取胜场
        $result = GvgServer::roundResult($serverid);
        if ($result['status'] !== 1) {
            return;
        }
        // 取出新胜场
        foreach (array_diff(array_column($result['info'], 'round'), $local) as $value) {
            DB::execute('INSERT IGNORE INTO `zc_sanguo_gvg_round` (`phase`, `round`) VALUES (:phase, :round)', ['phase' => $phase, 'round' => $value]);
        }
        // 获取未锁定场次
        $unlock = DB::table('gvg_round')->select('round')->where('phase', $phase)->where('islock', 0)->whereNull('deleted_at')
            ->orderBy('round', 'DESC')->first();
        if ($unlock === null) {
            return;
        }
        $round = (int)$unlock['round'];
        // 获取参赛者
        $list = Gvg::entryMember($serverid);
        if ($list === []) {
            Logger::error('cron_gvg get member list failed![serverid=' . $serverid . ']');
            return;
        }
        // 验证参赛者是否为此服务器用户
        $member = DB::table('member')->select('uid')->whereIn('uid', array_column($list, 'uid'))->whereNull('deleted_at')->get();
        if ($member === []) {
            Logger::error('cron_gvg entry member not exists!');
        }
        $reward = $uidArr = [];
        foreach ($member as $value) {
            $uidArr[(int)$value['uid']] = true;
            $reward[] = ['uid' => (int)$value['uid'], 'round' => $round, 'type' => 1, 'phase' => $phase];
        }
        // 获取 60 级及以上玩家（去除参赛者）
        $memberList = DB::table('member')->select('uid')->where('level', '>=', 60)->whereNull('deleted_at')->orderBy('uid')->get();
        if ($memberList === []) {
            Logger::error('cron_gvg lv60+ player not found!');
        }
        foreach ($memberList as $value) {
            if (!isset($uidArr[(int)$value['uid']])) {
                $reward[] = ['uid' => (int)$value['uid'], 'round' => $round, 'type' => 2, 'phase' => $phase];
            }
        }
        if ($reward !== []) {
            DB::transaction(static function () use ($reward, $phase, $round): void {
                // 以 islock = 0 为条件锁定，并发只生成一次
                if (DB::table('gvg_round')->where('phase', $phase)->where('round', $round)->where('islock', 0)->update(['islock' => 1]) !== 1) {
                    return;
                }
                DB::table('gvg_reward_log')->insertBatch($reward);
            });
        }
    }

    /**
     * _get_reward：发放所有未领取的奖励记录并发信件（旧版不论奖励是否为空都发信件）
     *
     * @return int 发放了几条
     */
    private static function getReward(int $now): int
    {
        $rewardList = Gvg::rewardList();
        $num = 0;
        foreach (DB::table('gvg_reward_log')->select('id', 'uid', 'round', 'type')->where('dateline', 0)->whereNull('deleted_at')->orderBy('id')->get() as $value) {
            $uid = (int)$value['uid'];
            $round = (int)$value['round'];
            // 修改领取状态（以未领为条件，并发只发一次）
            if (DB::table('gvg_reward_log')->where('id', (int)$value['id'])->where('uid', $uid)->where('dateline', 0)->update(['dateline' => $now]) !== 1) {
                continue;
            }
            $result = match ((int)$value['type']) {
                // 参赛奖励
                1 => self::addReward($rewardList[$round]['reward'] ?? [], $uid, $now),
                // 全服奖励
                2 => self::addReward($rewardList[$round]['server'] ?? [], $uid, $now),
                default => [],
            };
            if (($result['status'] ?? 0) === 1) {
                $reward = implode(',', $result['reward']);
                $message = $round === 1
                    ? sprintf(self::LANG_SERVER_REWARD1, $reward)
                    : sprintf(self::LANG_SERVER_REWARD2, Gvg::LANG_ROUND[$round] ?? '', $reward);
                MessageHandler::sendSystemMessage([$uid], self::MESSAGE_TYPE_SYSTEM, self::LANG_MESSAGE_SYSTEM_TITLE, $message);
                $num++;
            }
        }
        return $num;
    }

    /**
     * _add_reward：按奖励项发放，返回奖励文字
     *
     * @param array<string, int> $reward
     * @return array{status: int, reward: list<string>}
     */
    private static function addReward(array $reward, int $uid, int $now): array
    {
        $return = ['status' => 1, 'reward' => []];
        if (($reward['gold'] ?? 0) > 0) {
            $return['reward'][] = self::LANG_BASE['gold'] . 'x' . $reward['gold'];
            Member::addCurrency($uid, gold: $reward['gold']);
        }
        if (($reward['silver'] ?? 0) > 0) {
            $return['reward'][] = self::LANG_BASE['silver'] . 'x' . $reward['silver'];
            Member::addCurrency($uid, silver: $reward['silver']);
        }
        if (($reward['jade'] ?? 0) > 0) {
            $return['reward'][] = self::LANG_BASE['jade'] . 'x' . $reward['jade'];
            MemberJade::add($uid, $reward['jade'], 'cron_gvg', 'push_member_reward', $now);
        }
        if (isset($reward['tavernjade'])) {
            $return['reward'][] = self::LANG_BASE['jade3'] . 'x' . $reward['tavernjade'];
            TavernHandler::addJade($uid, 3, $reward['tavernjade'], 'cron_gvg', 'push_member_reward');
        }
        if (isset($reward['soul'])) {
            $return['reward'][] = self::LANG_BASE['soul'] . 'x' . $reward['soul'];
            Member::addCurrency($uid, soul: $reward['soul']);
        }
        if (isset($reward['equip'])) {
            // 旧版 add_item($info, $uid) 把 uid 传到了 $type 位置（发给当前请求的玩家，计划任务里没有），这里按本意发给该玩家
            $item = Equip::ROWS[$reward['equip']] ?? null;
            if ($item === null || Equipment::addFromTemplate($uid, $reward['equip']) === []) {
                Logger::error('cron_gvg add item failed![ERROR: uid=' . $uid . ']');
                // 添加装备失败
                $return['status'] = -1;
            }
            $return['reward'][] = (string)($item['name'] ?? '');
        }
        return $return;
    }

    /**
     * _lock：已锁返回 true，否则加锁（600 秒）返回 false
     */
    private static function lock(string $key, int $ttl = 600): bool
    {
        $redis = Redis::instance();
        return $redis === null || !(bool)$redis->set($key, '1', ['nx', 'ex' => $ttl]);
    }

    private static function unlock(string $key): void
    {
        Redis::del($key);
    }
}
