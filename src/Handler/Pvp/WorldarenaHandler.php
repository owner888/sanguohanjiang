<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Pvp;

use Hanjiang\Cross\Server;
use Hanjiang\Cross\WorldArenaServer;
use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\General\TavernHandler;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\Chat;
use Hanjiang\Model\Edifice;
use Hanjiang\Model\FreeLeap;
use Hanjiang\Model\Member;
use Hanjiang\Model\Sacredtree;
use Hanjiang\Model\StatPay;
use Hanjiang\Model\WorldArena;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 跨服演武 / 跨服远征（移植旧版 s1 controllers/worldarena.php + models/worldarena_model.php）
 *
 * 每天 0 点后本服演武榜的昨日前三（61 级以上）进入跨服排行榜（Cross\WorldArenaServer，旧版 battle 服务器），可挑战排在自己前面
 * 5 名以内及后面所有人，胜利交换名次（每天免费 10 次，30 元宝买 1 次）；榜外玩家每天可押注一名榜上玩家。第二天按昨日名次变化
 * （MasterData WorldSilver）领银币：上榜玩家按 reward 系数，押注者按 bet 系数（× 主公等级）；上榜玩家按昨日名次领功勋
 * （MasterData WorldExploit），功勋在跨服商城（MasterData WorldShop）兑换跨服物品（背包 type 11 ~ 18，c=pack&m=use_item 使用）。
 * 客户端 worldarenapop.js / miscpop.js / player.js 调用 index、get_rank_reward、get_bet_reward、arena、get_rank_list、betting、
 * buy_times、pk、get_report_list、get_report_info、shop、exchange_item、send_to_chat、get_server_reward。
 *
 * 每天 0 点后 10 分钟内为更新时间（-10）；serverid 大于 7000 的服务器关闭（-20，只 index 判断）。当前时间取 BossClock
 * （生产恒为 time()）。旧版 s1 的 _curl_post_to_uc 调用 battle 服务器，单服部署下直接调用 Cross\WorldArenaServer。
 * 不移植：clear_bet_info / clear_top_rank / clear_report_cache（运维口令接口）、test（空方法）。
 */
final class WorldarenaHandler
{
    /** 购买次数价格 */
    private const int TIMES_PRICE = 30;

    /** 分享战报价格 */
    private const int SHARE_PRICE = 2;

    /** 开放等级（各接口：等级不足 61 级回 2） */
    private const int LEVEL = 61;

    /** s1 lang worldarena_lang.php */
    private const string LANG_WIN = '胜利';
    private const string LANG_LOSE = '失败';
    private const string LANG_NICKNAME = '【%s%s服】%s';

    /**
     * 初始化接口: c=worldarena&m=index
     */
    public static function index(Context $ctx): array
    {
        $env = self::env();
        if ($env['lock']) {
            // 跨服远征更新
            return ['status' => -10];
        }
        if ($env['close']) {
            // 跨服远征关闭
            return ['status' => -20];
        }
        /**
         * -12 获取银币奖励规则失败（押注目标）
         * -11 获取押注目标排名失败（今日押注）
         * -9 获取银币奖励规则失败（今日排名）
         * -8 获取当前排名失败
         * -4 获取押注目标排名失败
         * -3 获取跨服信息失败
         * -2 获取银币奖励规则失败
         * -1 获取排名失败
         *  0 失败
         *  1 成功
         *  2 等级不足
         */
        $return = ['status' => 0, 'top' => [], 'bet' => [], 'reward' => []];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        if ($member['level'] < self::LEVEL) {
            // 等级不足 61 级
            $return['status'] = 2;
            return $return;
        }
        $uid = $member['uid'];
        $now = $env['now'];
        $today = $env['today'];
        $cd = $env['cd'];
        $rank = [];
        $reward = ['exploit' => 0, 'rank' => 0, 'cd' => 0];
        // 获取跨服信息
        $worldInfo = WorldArena::worldInfo($uid, $now);
        // 获取昨天前三名（新服第一天没有）
        $top = self::top($now);

        // *************************处理功勋 开始***************************
        // 领取功勋奖励时间
        if ($worldInfo['get_time'] === 0) {
            // 初始化上次领取功勋时间为上线时间 11 月 22 日
            $worldInfo['get_time'] = WorldArena::ONLINE_TIME;
        }
        $exploitDay = (int)WorldArena::date('Ymd', $worldInfo['get_time']);
        // 检查上次领取后 再次进入排行榜的日期(Ymd)
        $rewardDay = WorldArena::topLog($exploitDay, $uid, $now);
        while ($reward['exploit'] === 0) {
            if ($rewardDay < $today) {
                // 再次进入排行榜的日期 小于 当前日期（有可领取的功勋）：检查 $rewardDay 那天在跨服榜的排名
                $log = WorldArenaServer::getRankLog($uid, Server::id(), $rewardDay);
                if ($log === []) {
                    // 获取排名记录失败
                    $rewardDay = WorldArena::topLog($rewardDay + 1, $uid, $now);
                    continue;
                }
                // 获取功勋奖励
                $reward = ['exploit' => WorldArena::exploitReward($log['rank']), 'rank' => $log['rank'], 'cd' => 0];
                break;
            }
            break;
        }
        if (isset($top[$uid])) {
            // 已经进入跨服排行的，显示当前排名
            $rank = WorldArenaServer::getRankInfo($uid, Server::id());
            if ($rank === []) {
                // 获取排名失败
                $return['status'] = -1;
                return $return;
            }
            $reward['rank'] = $rank['rank'];
            if ($reward['exploit'] === 0) {
                // 如果没有需要领取的功勋，查询当前排名功勋奖励
                $reward['exploit'] = WorldArena::exploitReward($rank['rank']);
                $reward['cd'] = $cd;
            }
        }
        // **************************处理功勋 结束*******************************

        $bet = self::betRewardInfo($member, $worldInfo, $top, $rank, $env);
        if (is_int($bet)) {
            $return['status'] = $bet;
            return $return;
        }

        $topArr = $topUid = [];
        foreach ($top as $value) {
            $value['tree'] = 0;
            $topArr[] = $value;
            $topUid[] = $value['uid'];
        }
        // 查询 TOP3 神树等级
        $treeVersion = Sacredtree::openVersion($now);
        if ($treeVersion !== null && $topUid !== []) {
            $tree = Sacredtree::treeByUid($treeVersion, $topUid);
            foreach ($topArr as $key => $val) {
                if (isset($tree[$val['uid']])) {
                    $topArr[$key]['tree'] = $tree[$val['uid']]['treeid'];
                }
            }
        }
        // 首次打开操作记录（类型 6）
        Edifice::addLog($uid, 6);

        $return['status'] = 1;
        $return['top'] = $topArr;
        $return['bet'] = $bet;
        $return['reward'] = $reward['rank'] === 0 ? [] : $reward;
        return $return;
    }

    /**
     * 领取排行奖励（功勋）: c=worldarena&m=get_rank_reward
     */
    public static function get_rank_reward(Context $ctx): array
    {
        $env = self::env();
        if ($env['lock']) {
            return ['status' => -10];
        }
        /**
         * -4 领取功勋失败
         * -3 前300才可领奖
         * -2 获取排名记录失败
         * -1 已领取奖励
         *  0 失败
         *  1 成功
         *  2 等级不足
         */
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        if ($member['level'] < self::LEVEL) {
            $return['status'] = 2;
            return $return;
        }
        $uid = $member['uid'];
        $now = $env['now'];
        return Member::locked($uid, static function () use ($uid, $now, $env, $return): array {
            // 获取跨服信息
            $worldInfo = WorldArena::worldInfo($uid, $now);
            if ($worldInfo['get_time'] === 0) {
                $worldInfo['get_time'] = WorldArena::ONLINE_TIME;
            }
            $exploit = 0;
            $exploitDay = (int)WorldArena::date('Ymd', $worldInfo['get_time']);
            // 检查上次领取后 再次进入排行榜的日期(Ymd)
            $rewardDay = WorldArena::topLog($exploitDay, $uid, $now);
            while ($exploit === 0) {
                if ($rewardDay < $env['today']) {
                    $log = WorldArenaServer::getRankLog($uid, Server::id(), $rewardDay);
                    if ($log === []) {
                        // 获取排名记录失败
                        $rewardDay = WorldArena::topLog($rewardDay + 1, $uid, $now);
                        continue;
                    }
                    // 获取功勋奖励
                    $exploit = WorldArena::exploitReward($log['rank']);
                }
                break;
            }
            if ($exploit === 0) {
                // 没有可领取的功勋
                $return['status'] = -1;
                return $return;
            }
            $data = ['exploit' => $worldInfo['exploit'] + $exploit, 'get_time' => $now];
            if (WorldArena::updateWorldInfo($uid, $data) !== 1) {
                // 领取功勋失败
                $return['status'] = -4;
                return $return;
            }
            $return['status'] = 1;
            $return['add'] = $exploit;
            $return['exploit'] = $data['exploit'];
            $return['cd'] = $env['cd'];
            return $return;
        });
    }

    /**
     * 领取押注 / 排行银币奖励: c=worldarena&m=get_bet_reward
     */
    public static function get_bet_reward(Context $ctx): array
    {
        $env = self::env();
        if ($env['lock']) {
            return ['status' => -10];
        }
        /**
         * -9 获取银币奖励规则失败（今日排名）
         * -8 获取当前排名失败
         * -7 加银币失败
         * -4 获取押注目标排名失败
         * -2 获取银币奖励规则失败
         *  0 失败
         *  1 成功
         *  2 等级不足
         */
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        if ($member['level'] < self::LEVEL) {
            $return['status'] = 2;
            return $return;
        }
        $uid = $member['uid'];
        $now = $env['now'];
        return Member::locked($uid, static function () use ($member, $uid, $now, $env, $return): array {
            // 获取昨天前三名（新服第一天没有）
            $top = self::top($now);
            $worldInfo = WorldArena::worldInfo($uid, $now);
            $bet = ['rank' => ['before' => 0, 'after' => 0], 'nickname' => '', 'silver' => 0, 'cd' => $env['cd']];
            // **************************排行奖励 开始*******************************
            $silver = self::rankSilver($member, $worldInfo, $env);
            if (is_array($silver)) {
                // 获取银币奖励规则失败
                $return['status'] = $silver['status'];
                return $return;
            }
            // **************************押注奖励 开始*******************************
            if ($silver === 0) {
                // 未获得排名奖励
                $betDay = (int)WorldArena::date('Ymd', $worldInfo['bettime']);
                $getDay = (int)WorldArena::date('Ymd', $worldInfo['get_bet']);
                if ($getDay <= $betDay && $betDay < $env['today']) {
                    // 上次押注之后未领取，以前押的可以领取奖励
                    $log = WorldArena::betLog($uid, $now, $betDay);
                    if ($log !== []) {
                        $rankInfo = WorldArenaServer::getRankLog((int)$log['touid'], (int)$log['serverid'], $betDay);
                        if ($rankInfo === []) {
                            // 获取押注目标排名失败
                            $return['status'] = -4;
                            return $return;
                        }
                        if ($rankInfo['changed'] >= 0) {
                            $rule = WorldArena::silverReward($rankInfo['changed']);
                            if ($rule === null) {
                                $return['status'] = -2;
                                return $return;
                            }
                            $silver = (int)($member['level'] * $rule['bet']);
                        }
                    }
                }
            }
            if ($silver > 0) {
                // 加银币
                Member::addCurrency($uid, silver: $silver);
            }
            // 更新领取押注 / 排行奖励时间
            WorldArena::updateWorldInfo($uid, ['get_bet' => $now]);
            if (isset($top[$uid])) {
                // 今天在排行里，查询当前排名
                $rank = WorldArenaServer::getRankInfo($uid, Server::id());
                if ($rank === []) {
                    $return['status'] = -8;
                    return $return;
                }
                $returnSilver = 0;
                if ($rank['changed'] >= 0) {
                    $rule = WorldArena::silverReward($rank['changed']);
                    if ($rule === null) {
                        $return['status'] = -9;
                        return $return;
                    }
                    $returnSilver = (int)($member['level'] * $rule['reward']);
                }
                $bet = [
                    'rank' => ['before' => $rank['rank'] + $rank['changed'], 'after' => $rank['rank']],
                    'support' => $rank['support'],
                    'silver' => $returnSilver,
                    'cd' => $env['cd'],
                ];
            }
            // 删除押注信息
            WorldArena::delBetInfo($uid);
            $return['status'] = 1;
            $return['add'] = $silver;
            // 旧版返回 $this->member['silver']（add_silver 已同步）
            $return['silver'] = self::memberValue($uid, 'silver');
            $return['bet'] = $bet;
            return $return;
        });
    }

    /**
     * 跨服竞技场（排行榜首页）: c=worldarena&m=arena
     */
    public static function arena(Context $ctx): array
    {
        $env = self::env();
        if ($env['lock']) {
            return ['status' => -10];
        }
        /**
         * -2 获取押注信息失败
         * -1 获取排行榜失败
         *  0 失败
         *  1 成功
         *  2 等级不足
         */
        $return = ['status' => 0, 'info' => []];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        if ($member['level'] < self::LEVEL) {
            $return['status'] = 2;
            return $return;
        }
        $uid = $member['uid'];
        $now = $env['now'];
        // 演武榜前三（新服第一天没有）
        $top = self::top($now);
        // 获取跨服信息
        $world = WorldArena::worldInfo($uid, $now);
        if (isset($top[$uid])) {
            // 取我的页面
            $post = ['uid' => $uid, 'serverid' => Server::id()];
            // 剩余功勋
            $return['info']['exploit'] = $world['exploit'];
            // 重置次数
            self::resetFreedateline($uid, $now);
            $world = WorldArena::worldInfo($uid, $now);
            // 剩余次数
            $return['info']['times'] = $world['buytimes'] + $world['freetimes'];
            $return['info']['times_price'] = self::TIMES_PRICE;
        } else {
            // 取第一页
            $post = ['page' => 1];
        }
        $data = self::rankList($uid, $post, $world, $top, $env);
        if ($data['status'] !== 1) {
            $return['status'] = $data['status'];
            return $return;
        }
        $return['status'] = 1;
        $return['rank'] = $data['rank'];
        $return['top'] = $data['top'];
        return $return;
    }

    /**
     * 排行榜翻页: c=worldarena&m=get_rank_list&page=
     */
    public static function get_rank_list(Context $ctx): array
    {
        $env = self::env();
        if ($env['lock']) {
            return ['status' => -10];
        }
        /**
         * -2 获取押注信息失败
         * -1 获取排行榜失败
         *  0 失败
         *  1 成功
         *  2 等级不足
         */
        $return = ['status' => 0];
        $page = (int)$ctx->input('page', 0);
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        if ($member['level'] < self::LEVEL) {
            $return['status'] = 2;
            return $return;
        }
        $uid = $member['uid'];
        $now = $env['now'];
        $top = self::top($now);
        $world = WorldArena::worldInfo($uid, $now);
        $data = self::rankList($uid, ['page' => $page], $world, $top, $env);
        if ($data['status'] !== 1) {
            $return['status'] = $data['status'];
            return $return;
        }
        $return['status'] = 1;
        $return['rank'] = $data['rank'];
        $return['top'] = $data['top'];
        return $return;
    }

    /**
     * 押注: c=worldarena&m=betting&touid=&serverid=
     */
    public static function betting(Context $ctx): array
    {
        $env = self::env();
        if ($env['lock']) {
            return ['status' => -10];
        }
        /**
         * -3 获取押注目标信息失败
         * -2 缓存未清除
         * -1 在排行榜中不能押注
         *  0 失败
         *  1 成功
         *  2 等级不足
         *  3 已押注
         */
        $return = ['status' => 0];
        $touid = (int)$ctx->input('touid', 0);
        $serverid = (int)$ctx->input('serverid', 0);
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        if ($member['level'] < self::LEVEL) {
            $return['status'] = 2;
            return $return;
        }
        $uid = $member['uid'];
        $now = $env['now'];
        return Member::locked($uid, static function () use ($uid, $touid, $serverid, $now, $env, $return): array {
            // 是否已押注
            $worldInfo = WorldArena::worldInfo($uid, $now);
            if ((int)WorldArena::date('Ymd', $worldInfo['bettime']) >= $env['today']) {
                $return['status'] = 3;
                return $return;
            }
            // 在前三不能押注（旧版这里没有新服第一天的判断）
            if (isset(WorldArena::topRank($now)[$uid])) {
                $return['status'] = -1;
                return $return;
            }
            // 获取押注目标
            $target = WorldArenaServer::getRankInfo($touid, $serverid);
            if ($target === []) {
                $return['status'] = -3;
                return $return;
            }
            // 押注（目标昵称 / 当前排名）
            WorldArena::setBetInfo($uid, ['serverid' => $target['serverid'], 'touid' => $target['uid'], 'nickname' => $target['nickname'], 'rank' => $target['rank']]);
            // 记录 log
            WorldArena::recordBetLog([
                'uid' => $uid,
                'touid' => $target['uid'],
                'serverid' => $target['serverid'],
                'nickname' => $target['nickname'],
                'rank' => $target['rank'],
                'dateline' => $now,
            ]);
            // 更新押注时间
            WorldArena::updateWorldInfo($uid, ['bettime' => $now]);
            // 增加支持数
            $support = WorldArenaServer::incrSupport($touid, $serverid);
            $return['status'] = 1;
            // 押注对象
            $return['target'] = ['uid' => $touid, 'serverid' => $serverid];
            $return['support'] = $support;
            return $return;
        });
    }

    /**
     * 购买挑战次数: c=worldarena&m=buy_times
     */
    public static function buy_times(Context $ctx): array
    {
        /**
         * -3 增加次数失败
         * -2 元宝扣除失败
         * -1 没有资格
         *  0 失败
         *  1 成功
         *  2 元宝不足
         */
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        $uid = $member['uid'];
        $now = BossClock::now();
        // 演武榜前三（新服第一天没有）
        if (!isset(self::top($now)[$uid])) {
            // 没有资格
            $return['status'] = -1;
            return $return;
        }
        return Member::locked($uid, static function () use ($uid, $now, $return): array {
            if (self::TIMES_PRICE > self::memberValue($uid, 'gold')) {
                // 元宝不足
                $return['status'] = 2;
                return $return;
            }
            if (!Member::deductCurrency($uid, gold: self::TIMES_PRICE)) {
                // 元宝扣除失败
                $return['status'] = -2;
                return $return;
            }
            $worldInfo = WorldArena::worldInfo($uid, $now);
            $times = $worldInfo['buytimes'] + 1;
            if (WorldArena::updateWorldInfo($uid, ['buytimes' => $times]) !== 1) {
                // 增加次数失败
                $return['status'] = -3;
                return $return;
            }
            // 消费统计记录
            StatPay::record($uid, 'worldarena_buytimes', self::TIMES_PRICE);
            $return['status'] = 1;
            $return['times'] = $times + $worldInfo['freetimes'];
            $return['gold'] = self::memberValue($uid, 'gold');
            return $return;
        });
    }

    /**
     * 挑战: c=worldarena&m=pk&touid=&serverid=
     */
    public static function pk(Context $ctx): array
    {
        $env = self::env();
        if ($env['lock']) {
            return ['status' => -10];
        }
        /**
         * -7 获取排行榜失败
         * -6 更新排名失败
         * -5 扣除次数失败
         * -4 获取对方信息失败
         * -3 剩余次数不足
         * -2 未进入排行榜
         * -1 获取本服前三失败
         *  0 失败
         *  1 成功
         *  2 等级不足
         */
        $return = ['status' => 0, 'rank' => [], 'top' => [], 'torank' => 0];
        $touid = (int)$ctx->input('touid', 0);
        $toserverid = (int)$ctx->input('serverid', 0);
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        if ($member['level'] < self::LEVEL) {
            $return['status'] = 2;
            return $return;
        }
        $uid = $member['uid'];
        $now = $env['now'];
        $serverid = Server::id();
        // 检查玩家是否有资格（旧版这里没有新服第一天的判断）
        $rank = WorldArena::topRank($now);
        if ($rank === []) {
            // 获取本服排行榜失败
            $return['status'] = -1;
            return $return;
        }
        if (!isset($rank[$uid])) {
            // 没有资格参加跨服战
            $return['status'] = -2;
            return $return;
        }
        return Member::locked($uid, static function () use ($member, $uid, $serverid, $touid, $toserverid, $now, $env, $return): array {
            self::resetFreedateline($uid, $now);
            // 获取跨服战剩余次数
            $info = WorldArena::worldInfo($uid, $now);
            if ($info['freetimes'] + $info['buytimes'] === 0) {
                // 剩余次数不足
                $return['status'] = -3;
                return $return;
            }
            $right = WorldArenaServer::getTeamInfo($uid, $serverid, $touid, $toserverid);
            if ($right['status'] !== 2) {
                // 获取对方信息失败（status 1 为其他服的对手，单服部署不会出现，见 WorldArenaServer::getTeamInfo）
                $return['status'] = -4;
                $return['error'] = $right;
                return $return;
            }
            // 双方在同一服务器：获取对方等级，本服 battle_pk
            $right['base']['level'] = self::memberValue($touid, 'level');
            $battle = ArenaHandler::battlePk($uid, $touid);
            if (!isset($battle['info'])) {
                // 对方没有阵法 / 武将信息错误：旧版 battle_pk 不返回战斗，后面按空战斗写战报、扣次数；这里按获取对方信息失败返回
                $return['status'] = -4;
                $return['error'] = $battle;
                return $return;
            }
            $info2 = $battle['info'];
            // 扣除次数（先扣免费次数）
            $times = $info['freetimes'] > 0 ? ['freetimes' => $info['freetimes'] - 1] : ['buytimes' => $info['buytimes'] - 1];
            if (WorldArena::updateWorldInfo($uid, $times) !== 1) {
                $return['status'] = -5;
                return $return;
            }
            $update = null;
            $message = null;
            if ($info2['assess'] >= 1) {
                // 胜利时 返回 rank / top：更新排名
                $update = WorldArenaServer::updateRank($uid, $serverid, $touid, $toserverid, $now);
                if ($update['status'] !== 1 && $update['status'] !== 2) {
                    $return['status'] = -6;
                    return $return;
                }
                if ($update['status'] === 1) {
                    // 我的排名，可挑战排名
                    $myRank = $update['list'][$serverid . '_' . $uid]['rank'];
                    $enable = $myRank - 5;
                    $list = [];
                    foreach ($update['list'] as $value) {
                        $value['myself'] = 0;
                        $value['sid'] = Server::sid($value['serverid']);
                        $value['groupname'] = Server::groupName($value['serverid']);
                        if ($value['rank'] < $enable) {
                            // 不可挑战
                            $value['challenge'] = 0;
                        } elseif ($value['uid'] === $uid && $value['serverid'] === $serverid) {
                            $value['challenge'] = 0;
                            $value['myself'] = 1;
                        } else {
                            $value['challenge'] = 1;
                        }
                        // 神树活动结束，神树图标重置为 0
                        if (!$env['tree_activity']) {
                            $value['tree'] = 0;
                        }
                        $list[] = $value;
                    }
                    // 排名变化
                    $return['torank'] = $myRank;
                    $return['rank']['page'] = $update['page'];
                    $return['rank']['pages'] = $update['pages'];
                    $return['list'] = $list;
                    // 返回前三名
                    if ($update['page'] === 1) {
                        $top = WorldArenaServer::getRankList(page: 1);
                        if ($top['status'] !== 1) {
                            $return['status'] = -7;
                            return $return;
                        }
                        $topArr = [];
                        foreach ($top['list'] as $value) {
                            if ($value['rank'] < 4) {
                                $topArr[] = [
                                    'sid' => Server::sid($value['serverid']),
                                    'groupname' => Server::groupName($value['serverid']),
                                    'rank' => $value['rank'],
                                    'serverid' => $value['serverid'],
                                    'nickname' => $value['nickname'],
                                    'uid' => $value['uid'],
                                ];
                            }
                        }
                        $return['top'] = $topArr;
                    }
                    $message = ['touid' => $touid, 'type' => 8, 'win' => 1, 'data' => [$serverid, $member['nickname'], $update['left_rank']]];
                }
            } else {
                // 失败
                $message = ['touid' => $touid, 'type' => 8, 'win' => 0, 'data' => [$serverid, $member['nickname']]];
            }
            $return['status'] = 1;
            // 剩余次数
            $return['times'] = $info['buytimes'] + $info['freetimes'] - 1;
            // 战斗结果
            $return['info']['win'] = $info2['assess'];
            // 战斗过程
            $return['battle'] = $info2;
            // 左方 / 右方对阵信息
            $return['battle']['left_role'] = [
                'sid' => Server::sid($serverid),
                'groupname' => Server::groupName($serverid),
                'name' => $member['nickname'],
                'level' => $member['level'],
            ];
            $return['battle']['right_role'] = [
                'sid' => Server::sid($right['base']['serverid']),
                'groupname' => Server::groupName($right['base']['serverid']),
                'name' => $right['base']['nickname'],
                'level' => $right['base']['level'],
            ];
            // 战报
            $reportInfo = [
                'fromserverid' => $serverid,
                'uid' => $uid,
                'from' => $member['nickname'],
                'fromlevel' => $member['level'],
                'fromtree' => (int)($info2['leftbuff']['tree'] ?? 0),
                'torank' => (int)($update['torank'] ?? 0),
                'toserverid' => $right['base']['serverid'],
                'touid' => $touid,
                'to' => $right['base']['nickname'],
                'tolevel' => $right['base']['level'],
                'totree' => (int)($info2['rightbuff']['tree'] ?? 0),
                'win' => $info2['assess'] > 0 ? 1 : 0,
                'report' => $info2,
            ];
            $report = WorldArenaServer::saveReport($reportInfo, $now);
            // 战报 id
            $return['info']['report'] = $report['reportid'];
            if ($message !== null) {
                $message['title'] = $report['reportid'];
                // 发信件（本服 message_model->send_message；其他服经 battle send_message，同一处理）
                WorldArenaServer::sendMessage($right['base']['serverid'], $message, $now);
            }
            $return['share'] = ['id' => $report['reportid'], 'type' => 'worldarena'];
            return $return;
        });
    }

    /**
     * 战报列表（分页）: c=worldarena&m=get_report_list&page=
     */
    public static function get_report_list(Context $ctx): array
    {
        /**
         * -1 没有更多战报
         *  0 失败
         *  1 成功
         *  2 等级不足
         *  3 暂无战报
         */
        $return = ['status' => 0];
        $page = (int)$ctx->input('page', 0);
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        if ($member['level'] < self::LEVEL) {
            $return['status'] = 2;
            return $return;
        }
        $list = WorldArenaServer::getReportList($page);
        if ($list === []) {
            // page 1 暂无战报，否则没有更多战报
            $return['status'] = $page === 1 ? 3 : -1;
            return $return;
        }
        $listArr = [];
        foreach ($list['list'] as $value) {
            $value['sid'] = Server::sid($value['fromserverid']);
            $value['fromgroup'] = Server::groupName($value['fromserverid']);
            $value['togroup'] = Server::groupName($value['toserverid']);
            $value['tosid'] = Server::sid($value['toserverid']);
            $listArr[] = $value;
        }
        $return['status'] = 1;
        $return['list'] = $listArr;
        $return['page'] = $list['page'];
        return $return;
    }

    /**
     * 查看战报: c=worldarena&m=get_report_info&id=（旧版不判断等级）
     */
    public static function get_report_info(Context $ctx): array
    {
        /**
         * -1 获取战报失败
         *  0 失败
         *  1 成功
         */
        return self::reportInfo((int)$ctx->input('id', 0));
    }

    /**
     * 跨服战报回放（get_report_info / report 控制器 _worldarena_report 共用）
     *
     * @return array<string, mixed>
     */
    public static function reportInfo(int $id): array
    {
        $list = self::reportById($id);
        if ($list === []) {
            return ['status' => -1];
        }
        $return = ['status' => 1];
        // 战斗结果
        $return['info']['win'] = $list['report']['assess'] ?? null;
        // 战斗过程
        $return['battle'] = $list['report'];
        $return['battle']['left_role'] = [
            'sid' => Server::sid((int)$list['fromserverid']),
            'groupname' => Server::groupName((int)$list['fromserverid']),
            'name' => $list['nickname'],
            'level' => $list['userlevel'],
        ];
        $return['battle']['right_role'] = [
            'sid' => Server::sid((int)$list['toserverid']),
            'groupname' => Server::groupName((int)$list['toserverid']),
            'name' => $list['name'],
            'level' => $list['level'],
        ];
        return $return;
    }

    /**
     * 跨服商城（功勋兑换商品）: c=worldarena&m=shop
     */
    public static function shop(Context $ctx): array
    {
        /**
         * -1 获取商品列表失败
         *  0 失败
         *  1 成功
         *  2 等级不足
         */
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        if ($member['level'] < self::LEVEL) {
            $return['status'] = 2;
            return $return;
        }
        $list = WorldArena::itemList();
        if ($list === []) {
            $return['status'] = -1;
            return $return;
        }
        // 获取购买记录
        $log = array_map(self::logRow(...), WorldArenaServer::getShopLog());
        $info = WorldArena::worldInfo($member['uid'], BossClock::now());
        $return['status'] = 1;
        $return['list'] = $list;
        $return['log'] = $log;
        $return['exploit'] = $info['exploit'];
        return $return;
    }

    /**
     * 兑换商品: c=worldarena&m=exchange_item&id=
     */
    public static function exchange_item(Context $ctx): array
    {
        /**
         * -4 添加 log 失败
         * -3 添加道具失败
         * -2 扣除功勋失败
         * -1 获取商品信息失败
         *  0 失败
         *  1 成功
         *  2 等级不足
         *  3 功勋不足
         */
        $return = ['status' => 0];
        $id = (int)$ctx->input('id', 0);
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        if ($member['level'] < self::LEVEL) {
            $return['status'] = 2;
            return $return;
        }
        $item = WorldArena::itemById($id);
        if ($item === null) {
            $return['status'] = -1;
            return $return;
        }
        $uid = $member['uid'];
        $now = BossClock::now();
        return Member::locked($uid, static function () use ($member, $uid, $item, $now, $return): array {
            $info = WorldArena::worldInfo($uid, $now);
            if ($info['exploit'] < $item['exploit']) {
                // 功勋不足
                $return['status'] = 3;
                return $return;
            }
            $data = ['exploit' => $info['exploit'] - $item['exploit']];
            if (WorldArena::updateWorldInfo($uid, $data) !== 1) {
                // 扣除功勋失败（兑换 0 功勋的商品时旧版同样因影响 0 行回 -2）
                $return['status'] = -2;
                return $return;
            }
            if (self::addWorldItem($uid, $item) !== 1) {
                // 添加物品失败
                $return['status'] = -3;
                return $return;
            }
            $add = WorldArenaServer::addShopLog([
                'uid' => $uid,
                'itemid' => $item['id'],
                'type' => $item['type'],
                'name' => $item['name'],
                'serverid' => Server::id(),
                'nickname' => $member['nickname'],
            ], $now);
            if ($add['status'] !== 1) {
                // 添加商店 log 失败
                $return['status'] = -4;
                return $return;
            }
            $return['status'] = 1;
            $return['exploit'] = $data['exploit'];
            $return['log'] = array_map(self::logRow(...), $add['log']);
            return $return;
        });
    }

    /**
     * 跨服战报分享到聊天室: c=worldarena&m=send_to_chat&reportid=
     *
     * 与旧版的差别：旧版先发聊天再扣元宝（sub_gold 不检查结果）；这里先扣元宝，扣不到回 2（元宝不足），同 arena send_to_chat。
     */
    public static function send_to_chat(Context $ctx): array
    {
        /**
         * -2 获取战报失败
         * -1 参数错误
         *  0 失败
         *  1 成功
         *  2 元宝不足
         */
        $return = ['status' => 0];
        $reportid = (int)$ctx->input('reportid', 0);
        $uid = MemberHandler::resolveUid($ctx);
        $member = self::member($uid);
        if ($member === null) {
            return $return;
        }
        // 判断参数是否完整
        if ($reportid === 0) {
            $return['status'] = -1;
            return $return;
        }
        // 判断元宝是否足够
        if (self::memberValue($uid, 'gold') < self::SHARE_PRICE) {
            $return['status'] = 2;
            return $return;
        }
        // 根据 id 获取战报
        $list = self::reportById($reportid);
        if ($list === []) {
            $return['status'] = -2;
            return $return;
        }
        // 扣除元宝
        $gold = Chat::chargeShare($uid, self::SHARE_PRICE);
        if ($gold === null) {
            $return['status'] = 2;
            return $return;
        }
        $togroup = Server::groupName((int)$list['toserverid']);
        $sender = Chat::sender($uid);
        $message = Chat::getMessageStr(16, [
            'id' => $reportid,
            'worldarena' => sprintf(self::LANG_NICKNAME, $togroup, $list['toserverid'], $list['name']),
            'win' => (int)$list['win'] === 1 ? self::LANG_WIN : self::LANG_LOSE,
        ], $sender);
        // 旧版 type 9 + 战报 id
        Chat::sendToAll($message, 9, $reportid, 0, $sender);
        // 消费统计记录
        StatPay::record($uid, 'worldarena_report', self::SHARE_PRICE);
        $return['status'] = 1;
        $return['gold'] = $gold;
        return $return;
    }

    /**
     * 领取全服奖励: c=worldarena&m=get_server_reward
     *
     * 别人使用跨服商城物品时发放的全服福利（world_reward，add2），61 级以上玩家按发放顺序逐个领取（member.server_reward 为已领到的 id），
     * 自己发放的跳过。旧版不判断等级（主城只给 61 级以上显示）。
     */
    public static function get_server_reward(Context $ctx): array
    {
        /**
         * -3 增加领取记录失败（已领过）
         * -2 更新领取状态失败
         * -1 无法领取
         *  0 失败
         *  1 成功
         */
        $return = ['status' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        if (self::member($uid) === null) {
            return $return;
        }
        $now = BossClock::now();
        return Member::locked($uid, static function () use ($uid, $now, $return): array {
            $row = DB::table('member')->select('level', 'server_reward')->where('uid', $uid)->whereNull('deleted_at')->first();
            $level = (int)$row['level'];
            $reward = WorldArena::nextReward((int)$row['server_reward'], $uid);
            if ($reward === []) {
                $return['status'] = -1;
                return $return;
            }
            // 增加领取记录
            if (WorldArena::addRewardLog($uid, (int)$reward['id'], $now) !== 1) {
                $return['status'] = -3;
                return $return;
            }
            // 更新领取状态
            if (WorldArena::updateServerReward($uid, (int)$reward['id']) !== 1) {
                $return['status'] = -2;
                return $return;
            }
            $add = (int)$reward['add'];
            match ((int)$reward['type']) {
                // 银币
                1 => Member::addCurrency($uid, silver: $add * $level),
                // 蓝 / 金 / 紫宝石
                2 => TavernHandler::addJade($uid, 1, $add, 'worldarena', 'get_server_reward'),
                3 => TavernHandler::addJade($uid, 2, $add, 'worldarena', 'get_server_reward'),
                4 => TavernHandler::addJade($uid, 3, $add, 'worldarena', 'get_server_reward'),
                // 军令
                5 => Member::addCurrency($uid, act: $add),
                // 蓝 / 金 / 紫色突飞卡（张数 1 / 2 / 4 倍）
                6 => FreeLeap::add($uid, $add, $now),
                7 => FreeLeap::add($uid, $add * 2, $now),
                8 => FreeLeap::add($uid, $add * 4, $now),
                default => null,
            };
            // 是否有未领取的跨服奖励
            $return['server_reward'] = self::serverRewardInfo((int)$reward['id'], $uid, $level);
            $after = DB::table('member')->select('silver', 'act')->where('uid', $uid)->first();
            $return['status'] = 1;
            $return['silver'] = (int)$after['silver'];
            $return['act'] = (int)$after['act'];
            return $return;
        });
    }

    /**
     * 下一个可领的全服奖励（member index / get_server_reward 的 server_reward）：nickname / type / num（银币为 add × 主公等级）；没有为空数组
     *
     * @return array{nickname: string, type: int, num: int}|array{}
     */
    public static function serverRewardInfo(int $rewardid, int $uid, int $level): array
    {
        $next = WorldArena::nextReward($rewardid, $uid);
        if ($next === []) {
            return [];
        }
        return [
            'nickname' => (string)$next['nickname'],
            'type' => (int)$next['type'],
            'num' => (int)$next['type'] === 1 ? (int)$next['add'] * $level : (int)$next['add'],
        ];
    }

    // ---------------------------------------------------------------------------------------------

    /**
     * 构造函数里的公共状态：当前时间、日期、本日剩余秒数、更新锁（0 点后 10 分钟；日本 / 韩国服 70 分钟）、关闭、神树活动
     *
     * @return array{now: int, today: int, cd: int, lock: bool, close: bool, tree_activity: bool}
     */
    private static function env(): array
    {
        $now = BossClock::now();
        $serverid = Server::id();
        $cd = (int)strtotime(date('Ymd', $now + 86400)) - $now;
        $lock = $cd >= 86400 - 600;
        if ($serverid > 4000 && $serverid < 7000 && $cd >= 86400 - 4200) {
            $lock = true;
        }
        return [
            'now' => $now,
            'today' => (int)WorldArena::date('Ymd', $now),
            'cd' => $cd,
            'lock' => $lock,
            // 跨服远征关闭
            'close' => $serverid > 7000,
            'tree_activity' => Sacredtree::openVersion($now) !== null,
        ];
    }

    /**
     * 昨天前三名；新服第一天（开服当天）没有
     *
     * @return array<int, array<string, mixed>>
     */
    private static function top(int $now): array
    {
        if (date('Ymd', $now) === date('Ymd', Server::openTime())) {
            return [];
        }
        return WorldArena::topRank($now);
    }

    /**
     * 排行银币（index / get_bet_reward 的「排行奖励」段）：上次领取后再次进榜那天的名次变化 × 主公等级 × reward 系数
     *
     * @param array<string, mixed> $member
     * @param array<string, int> $worldInfo
     * @param array<string, mixed> $env
     * @param array<string, mixed>|null $bet 传入时（index）同时填 bet 显示
     * @return int|array{status: int} 规则取不到时为 status -2
     */
    private static function rankSilver(array $member, array $worldInfo, array $env, ?array &$bet = null): int|array
    {
        $uid = $member['uid'];
        $silver = 0;
        // 领取押注奖励时间
        $getDay = (int)WorldArena::date('Ymd', $worldInfo['get_bet']);
        // 检查上次领取排行奖励后 再次进入排行榜的日期(Ymd)
        $rewardDay = WorldArena::topLog($getDay, $uid, $env['now']);
        while ($silver === 0) {
            if ($rewardDay >= $env['today']) {
                break;
            }
            $log = WorldArenaServer::getRankLog($uid, Server::id(), $rewardDay);
            if ($log === []) {
                // 获取排名记录失败
                $rewardDay = WorldArena::topLog($rewardDay + 1, $uid, $env['now']);
                continue;
            }
            // 排名奖励未领取
            if ($log['changed'] >= 0) {
                $rule = WorldArena::silverReward($log['changed']);
                if ($rule === null) {
                    return ['status' => -2];
                }
                $silver = (int)($member['level'] * $rule['reward']);
                $bet = [
                    'rank' => ['before' => $log['rank'] + $log['changed'], 'after' => $log['rank']],
                    'silver' => $silver,
                    'support' => $log['support'],
                    'cd' => 0,
                ];
                // 旧版 get_bet_reward 在这里 break，index 靠 while 条件退出（WorldSilver 的 reward 系数都大于 0，silver 必然大于 0）
                break;
            }
            $rewardDay = WorldArena::topLog($rewardDay + 1, $uid, $env['now']);
        }
        return $silver;
    }

    /**
     * index 的押注 / 排行奖励显示（bet）
     *
     * @param array<string, mixed> $member
     * @param array<string, int> $worldInfo
     * @param array<int, array<string, mixed>> $top
     * @param array<string, mixed> $rank 自己的当前排名（不在榜上为空）
     * @param array<string, mixed> $env
     * @return array<string, mixed>|int 失败时为状态码
     */
    private static function betRewardInfo(array $member, array $worldInfo, array $top, array $rank, array $env): array|int
    {
        $uid = $member['uid'];
        $now = $env['now'];
        $cd = $env['cd'];
        // 初始化排行 和 押注奖励返回
        $bet = ['rank' => ['before' => 0, 'after' => 0], 'nickname' => '', 'silver' => 0, 'cd' => $cd];
        // **************************排行奖励*******************************
        $silver = self::rankSilver($member, $worldInfo, $env, $bet);
        if (is_array($silver)) {
            return $silver['status'];
        }
        if ($silver !== 0) {
            return $bet;
        }
        // **************************押注奖励*******************************
        $betDay = (int)WorldArena::date('Ymd', $worldInfo['bettime']);
        $getDay = (int)WorldArena::date('Ymd', $worldInfo['get_bet']);
        if ($getDay <= $betDay) {
            // 上次押注之后未领取
            if ($betDay < $env['today']) {
                // 以前押的，可以领取奖励，显示押注奖励
                $log = WorldArena::betLog($uid, $now, $betDay);
                if ($log !== []) {
                    $rankInfo = WorldArenaServer::getRankLog((int)$log['touid'], (int)$log['serverid'], $betDay);
                    if ($rankInfo === []) {
                        // 获取押注目标排名失败
                        return -4;
                    }
                    if ($rankInfo['changed'] >= 0) {
                        $rule = WorldArena::silverReward($rankInfo['changed']);
                        if ($rule === null) {
                            return -2;
                        }
                        $bet = [
                            'rank' => ['before' => $rankInfo['rank'] + $rankInfo['changed'], 'after' => $rankInfo['rank']],
                            'silver' => (int)($member['level'] * $rule['bet']),
                            'nickname' => (string)$log['nickname'],
                            'cd' => 0,
                        ];
                    } elseif (isset($rank['changed']) && $rank['changed'] >= 0) {
                        // 昨日押注今日进榜
                        $rule = WorldArena::silverReward($rank['changed']);
                        if ($rule === null) {
                            return -2;
                        }
                        $bet = [
                            'rank' => ['before' => $rank['rank'] + $rank['changed'], 'after' => $rank['rank']],
                            'silver' => (int)($member['level'] * $rule['reward']),
                            'support' => $rank['support'],
                            'cd' => $cd,
                        ];
                    }
                }
            } else {
                // 今天押的，不能领取，显示押注排名
                $info = WorldArena::betLog($uid, $now);
                if ($info !== []) {
                    // 获取押注对象当前排名
                    $rankInfo = WorldArenaServer::getRankInfo((int)$info['touid'], (int)$info['serverid']);
                    if ($rankInfo === []) {
                        return -11;
                    }
                    $betSilver = 0;
                    if ($rankInfo['changed'] >= 0) {
                        $rule = WorldArena::silverReward($rankInfo['changed']);
                        if ($rule === null) {
                            return -12;
                        }
                        $betSilver = (int)($member['level'] * $rule['bet']);
                    }
                    $bet = [
                        'rank' => ['before' => $rankInfo['rank'] + $rankInfo['changed'], 'after' => $rankInfo['rank']],
                        'nickname' => $rankInfo['nickname'],
                        'silver' => $betSilver,
                        'cd' => $cd,
                    ];
                }
            }
        } elseif (isset($top[$uid])) {
            // 上次押注已经领取，今天在排行里：查询当前排名
            $myRank = WorldArenaServer::getRankInfo($uid, Server::id());
            if ($myRank === []) {
                return -8;
            }
            $rankSilver = 0;
            if ($myRank['changed'] >= 0) {
                $rule = WorldArena::silverReward($myRank['changed']);
                if ($rule === null) {
                    return -9;
                }
                $rankSilver = (int)($member['level'] * $rule['reward']);
            }
            $bet = [
                'rank' => ['before' => $myRank['rank'] + $myRank['changed'], 'after' => $myRank['rank']],
                'support' => $myRank['support'],
                'silver' => $rankSilver,
                'cd' => $cd,
            ];
        }
        return $bet;
    }

    /**
     * 排行列表（_get_rank_list）：标出可挑战（challenge）/ 自己（myself）/ 可押注（bet 1 可押、2 已押、0 不可），前三带服务器
     *
     * @param array<string, mixed> $post uid + serverid 或 page
     * @param array<string, int> $world
     * @param array<int, array<string, mixed>> $top
     * @param array<string, mixed> $env
     * @return array<string, mixed>
     */
    private static function rankList(int $uid, array $post, array $world, array $top, array $env): array
    {
        $serverid = Server::id();
        $list = WorldArenaServer::getRankList((int)($post['uid'] ?? 0), (int)($post['serverid'] ?? 0), (int)($post['page'] ?? 0));
        $serverTop = $list;
        if ($list['status'] !== 1) {
            // 获取排行榜失败
            return ['status' => -1];
        }
        $uuid = $serverid . '_' . $uid;
        if (isset($list['list'][$uuid])) {
            // 我的排名，可挑战排名
            $enable = $list['list'][$uuid]['rank'] - 5;
            foreach ($list['list'] as $k => $value) {
                $list['list'][$k]['myself'] = 0;
                if ($value['rank'] < $enable) {
                    $list['list'][$k]['challenge'] = 0;
                } elseif ($value['uid'] === $uid && $value['serverid'] === $serverid) {
                    $list['list'][$k]['challenge'] = 0;
                    $list['list'][$k]['myself'] = 1;
                } else {
                    $list['list'][$k]['challenge'] = 1;
                }
            }
        } elseif (isset($top[$uid])) {
            // 获取我的排名
            $info = WorldArenaServer::getRankInfo($uid, $serverid);
            $enable = (int)($info['rank'] ?? 0) - 5;
            foreach ($list['list'] as $k => $value) {
                $list['list'][$k]['myself'] = 0;
                $list['list'][$k]['challenge'] = $value['rank'] < $enable ? 0 : 1;
            }
        } else {
            $touid = 0;
            $bet = [];
            if ((int)WorldArena::date('Ymd', $world['bettime']) === $env['today']) {
                $bet = WorldArena::betInfo($uid);
                if ($bet !== []) {
                    $touid = (int)$bet['touid'];
                }
            }
            // 已押注对象
            foreach ($list['list'] as $k => $value) {
                if ($touid === 0) {
                    // 可押注
                    $list['list'][$k]['bet'] = 1;
                } elseif ($value['uid'] === $touid && $value['serverid'] === (int)$bet['serverid']) {
                    // 已押注
                    $list['list'][$k]['bet'] = 2;
                } else {
                    // 不可押注
                    $list['list'][$k]['bet'] = 0;
                }
            }
        }
        if ($list['page'] !== 1) {
            // 跨服前三
            $serverTop = WorldArenaServer::getRankList(page: 1);
            if ($serverTop['status'] !== 1) {
                return ['status' => -1];
            }
        }
        $topArr = [];
        foreach ($serverTop['list'] as $value) {
            if ($value['rank'] < 4) {
                $topArr[] = [
                    'sid' => Server::sid($value['serverid']),
                    'rank' => $value['rank'],
                    'groupname' => Server::groupName($value['serverid']),
                    'serverid' => $value['serverid'],
                    'nickname' => $value['nickname'],
                    'uid' => $value['uid'],
                    // 神树活动结束，神树图标重置为 0
                    'tree' => $env['tree_activity'] ? $value['tree'] : 0,
                ];
            }
        }
        $listArr = [];
        foreach ($list['list'] as $value) {
            if (!$env['tree_activity']) {
                $value['tree'] = 0;
            }
            $value['sid'] = Server::sid($value['serverid']);
            $value['groupname'] = Server::groupName($value['serverid']);
            $listArr[] = $value;
        }
        return [
            'status' => 1,
            'rank' => ['list' => $listArr, 'page' => $list['page'], 'pages' => $list['pages']],
            'top' => $topArr,
        ];
    }

    /**
     * 跨服战报（缓存一天，没有缓存从 battle 取）；取不到为空数组
     *
     * @return array<string, mixed>
     */
    private static function reportById(int $id): array
    {
        $cache = WorldArena::reportCache($id);
        if ($cache !== []) {
            return $cache;
        }
        $list = WorldArenaServer::getReportInfo($id);
        if ($list !== []) {
            WorldArena::cacheReportInfo($id, $list);
        }
        return $list;
    }

    /**
     * 每天第一次挑战 / 打开榜单时把免费次数重置为 10（reset_freedateline）
     */
    private static function resetFreedateline(int $uid, int $now): void
    {
        $world = WorldArena::worldInfo($uid, $now);
        if (date('Ymd', $world['freedateline']) !== date('Ymd', $now)) {
            WorldArena::updateWorldInfo($uid, ['freedateline' => $now]);
            WorldArena::addWorldFree($uid);
        }
    }

    /**
     * 兑换的跨服物品放进背包（equipment_model->add_world_item：已有同一物品时数量 + 1，否则新增 type = 商品 type + 10、
     * quality = 排序 + 10 的一行）
     *
     * @param array<string, mixed> $item
     */
    private static function addWorldItem(int $uid, array $item): int
    {
        $exists = DB::table('equipment')->select('id', 'number')->where('uid', $uid)->where('equipid', (int)$item['id'])
            ->whereBetween('type', 11, 18)->whereNull('deleted_at')->first();
        if ($exists === null) {
            $id = (int)DB::table('equipment')->insertGetId([
                'uid' => $uid,
                'equipid' => (int)$item['id'],
                'name' => (string)$item['name'],
                'type' => (int)$item['type'] + 10,
                'quality' => (int)$item['sort'] + 10,
                'saleprice' => 0,
                'chip' => 0,
                'number' => 1,
            ]);
            return $id > 0 ? 1 : 0;
        }
        return DB::table('equipment')->where('id', (int)$exists['id'])->update(['number' => (int)$exists['number'] + 1]) > 0 ? 1 : 0;
    }

    /**
     * 商城记录的一行加上 sid / groupname
     *
     * @param array<string, mixed> $value
     * @return array<string, mixed>
     */
    private static function logRow(array $value): array
    {
        $value['sid'] = Server::sid((int)$value['serverid']);
        $value['groupname'] = Server::groupName((int)$value['serverid']);
        return $value;
    }

    /**
     * @return array{uid: int, nickname: string, level: int}|null
     */
    private static function member(int $uid): ?array
    {
        if ($uid <= 0) {
            return null;
        }
        $row = DB::table('member')->select('uid', 'nickname', 'level')->where('uid', $uid)->whereNull('deleted_at')->first();
        return $row === null ? null : ['uid' => (int)$row['uid'], 'nickname' => (string)$row['nickname'], 'level' => (int)$row['level']];
    }

    private static function memberValue(int $uid, string $column): int
    {
        return (int)(DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value($column) ?? 0);
    }
}
