<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\MasterData\ActMoonAchReward;
use Hanjiang\MasterData\ActMoonEat;
use Hanjiang\MasterData\ActMoonGive;
use Hanjiang\Model\ActivityClock;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\FlowLog;
use Hanjiang\Model\FreeLeap;
use Hanjiang\Model\Member;
use Hanjiang\Model\MemberIntegral;
use Hanjiang\Model\MemberJade;
use Hanjiang\Model\MemberStone;
use Hanjiang\Model\OwnGem;
use Hanjiang\Model\TraderLog;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 中秋节送月饼（移植旧版 controllers/act_moon.php + models/moon_model.php，计划任务 cron_acttask.php:clear_moon，
 * 充值记账 pay.php:pay_act_init 的 add_pay_moon）
 *
 * 活动时间与版本取 ActivityTime 的 moon（旧版数据从未开放），50 级开放。玩家有两种月饼（act_moon 表）：自己的月饼 mine_moon
 * （充值送：每充 30 元送 1 个，VIP 0 首充另送 1 个；VIP 1 ~ 4 每期可花 50 元宝一个买 10 / 50 / 100 / 200 个，VIP 5 以上不限；
 * 建档时 VIP 1 以上送 1 个）只能送人；好友送的 / 玉兔每天第一次进入时送的 10 个小月饼 friend_moon 只能自己吃。送月饼加爱心
 * （love，前 10 名排行，爱心达到 MasterData ActMoonAchReward 的成就可领奖），送 / 吃按 ActMoonGive / ActMoonEat 的权重
 * 每个月饼抽一次奖。活动结束后 10 小时内计划任务把剩余月饼按大月饼 5 万、小月饼 1 万银币兑换并发信件（cronReward）。
 * 客户端 midautumnpop.js 调用 index、get_friend_list、give_moon（fuid、num 1 / 10 / 50、type 1 月饼不够时自动购买）、
 * get_give_info、eat_moon（num）、get_reward（id）、get_ach_list、buy_moon（id 1 / 2 / 3）。当前时间取 BossClock。
 *
 * 奖励类型（_add_reward）：1 元宝 2 银币 3 黄宝石 4 紫宝石 5 原魂 6 玉石 7 声望 9 宝石碎片 10 突飞卡 11 蓝宝石 13 军令
 * 14 精炼石 15 五彩石 16 上古血石 27 玫瑰 28 钻石（8 装备、12 武将、17 ~ 26 旧版已注释掉，不发）。
 * 照旧版：月饼记账的日志 act_moon_log 记在当前玩家名下（送给好友的月饼也记在送的人名下）；好友建档时按送的人的 VIP 决定是否送
 * 1 个月饼；排行与好友列表的 uid 不做安卓合服转换（旧版只在 server_merge 配置开启时转换，合服未移植）；成就表为空时旧版不输出
 * 任何内容（不会发生）。
 * 与旧版的差别：操作锁（mn_lk_{方法}{uid}，10 秒）与好友月饼锁（mn_lk_{uid}）改为 Redis SET NX，只删除自己加的锁
 * （旧版析构时不论是否自己加的都删）；月饼与爱心用原子增减，送月饼在送的人的行锁事务里执行；成就领取记录加唯一键，写入成功
 * 才发奖；送月饼时自己还没有活动记录（旧版会读到空记录继续执行）回 -7；计划任务的已处理玩家列表从日志文件改为 Redis 集合
 * moon_cron_list{version}（保留 7 天）。
 */
final class ActMoonHandler
{
    public const string ACTIVITY = 'moon';

    /** 活动开启等级 */
    private const int OPEN_LEVEL = 50;
    /** 月饼价格 */
    private const int MOON_PRICE = 50;
    /** 每天赠送的系统月饼个数 */
    private const int SYS_MOON = 10;
    /** VIP 可购买数量（VIP 5 以上不限） */
    private const array VIP_LIST = [0 => 0, 1 => 10, 2 => 50, 3 => 100, 4 => 200];
    /** 购买列表（_get_buy_list） */
    private const array BUY_LIST = [
        1 => ['id' => 1, 'gold' => 50, 'moonnum' => 1],
        2 => ['id' => 2, 'gold' => 500, 'moonnum' => 10],
        3 => ['id' => 3, 'gold' => 2500, 'moonnum' => 50],
    ];

    /** 锁（_class_lock，10 秒） */
    private const string LOCK_KEY = 'mn_lk_';
    private const int LOCK_TTL = 10;

    /** 计划任务已处理的玩家（旧版 application/logs/moon_cron_list{version}.logs） */
    private const string CRON_LIST_KEY = 'moon_cron_list';
    /** 计划任务：大月饼 / 小月饼兑换的银币 */
    private const int DA_MOON = 50000;
    private const int XIAO_MOON = 10000;

    /** moon_lang.php（chinese_s）+ cron_lang.php */
    private const array LANG = [
        'moon_month' => '月',
        'moon_day' => '日',
        'moon_msg' => '%s送给你了%s个小月饼',
        'moon_zhugong' => '玉兔',
        'moon_pay_message' => '恭喜您在充值送月饼活动中获得%s个月饼，祝您游戏愉快！',
        'cron_moon_msg' => '亲爱的玩家!中秋活动已结束,您剩余的%s月饼和%s个小月饼,已经自动兑换成%s请注意查收!',
        'cron_moon_msg1' => '亲爱的玩家!中秋活动已结束,您剩余的%s月饼,已经自动兑换成%s请注意查收!',
        'cron_moon_msg2' => '亲爱的玩家!中秋活动已结束,您剩余%s个小月饼,已经自动兑换成%s请注意查收!',
        'moon_act_not_open' => '活动未开启！',
        'moon_act_request' => '操作太快！',
        'moon_act_undefined_erro' => '未知错误！',
        'moon_act_param' => '缺少参数！',
        'moon_act_gold_insufficient' => '元宝不足！',
        'moon_act_sub_gold' => '扣除元宝失败！',
        'moon_act_moon_info' => '获取活动信息失败！',
        'moon_act_add' => '添加月饼信息失败！',
        'moon_get_ach_info' => '获取成就信息失败！',
        'moon_get_ach_reward' => '该成就已经领取过！',
        'moon_add_ach_log' => '添加成就记录失败！',
        'moon_insufficien' => '剩余小月饼不足！',
        'moon_insufficient' => '月饼不足！',
        'moon_get_friend' => '获取朋友信息失败！',
        'moon_get_num' => '当前vip剩余购买次数不足！',
        'moon_give_me' => '不能赠送给自己！',
        'moon_not_level' => '活动50级开启！',
        'moon_vip_buy' => '达到VIP1可购买10个月饼！',
        // 旧版语言包里没有 moon_act_ach_info，lang->line 返回 false
        'moon_act_ach_info' => false,
        'cron_silver' => '银币',
        'message_system_title' => '系统通知',
    ];

    /**
     * 首页: c=act_moon&m=index（当天第一次进入时玉兔送 10 个小月饼）
     *
     * 1 成功；-1 活动未开启 / 等级不足；0 建档失败
     */
    public static function index(Context $ctx): array
    {
        $now = BossClock::now();
        [$member, $act, $gate] = self::gate($ctx, $now);
        if ($gate !== null) {
            return $gate;
        }
        $uid = $member['uid'];
        $version = $act['version'];
        $moon = self::moon($uid, $version) ?? self::insertMoon($uid, $version, $member['nickname'], $member['vip']);
        if ($moon === null) {
            return ['status' => 0, 'msg' => self::LANG['moon_act_undefined_erro']];
        }
        $giftimg = $moon['friend_moon'] > 0 ? 1 : 0;
        // 成就感叹号
        $achimg = self::achImg($uid, $version, $moon['love']);
        // 计算 VIP 5 以下的剩余购买次数
        $buymoon = 0;
        if ($member['vip'] <= 5 && isset(self::VIP_LIST[$member['vip']])) {
            $buymoon = max(0, self::VIP_LIST[$member['vip']] - $moon['buynum']);
        }
        // 当天第一次登陆发送免费月饼
        if (self::systemMoon($member, $version, $moon, $now)) {
            $giftimg = 1;
        }
        return [
            'status' => 1,
            'love' => $moon['love'],
            'mine_moon' => $moon['mine_moon'],
            'giftimg' => $giftimg,
            'achimg' => $achimg,
            'buymoon' => $buymoon,
            'vip' => $member['vip'],
            'moon_price' => self::MOON_PRICE,
            'timestr' => $act['timestr'],
            'rank_list' => self::rankList($version),
            'buy_list' => self::BUY_LIST,
        ];
    }

    /**
     * 好友列表（带爱心）: c=act_moon&m=get_friend_list
     *
     * 1 成功；-1 活动未开启 / 等级不足。有活动记录的好友按爱心降序在前，其余在后
     */
    public static function get_friend_list(Context $ctx): array
    {
        $now = BossClock::now();
        [$member, $act, $gate] = self::gate($ctx, $now);
        if ($gate !== null) {
            return $gate;
        }
        // 好友（旧版 get_friend_list(1, 100, 自己的 uid)：前 100 个好友）
        $friendRankList = [];
        foreach (DB::table('friend')->select('fuid')->where('uid', $member['uid'])->where('status', 1)->whereNull('deleted_at')
            ->orderBy('id')->limit(100)->get() as $row) {
            $friendRankList[(int)$row['fuid']] = ['fuid' => (int)$row['fuid'], 'love' => 0];
        }
        $fRank = [];
        if ($friendRankList !== []) {
            $fuids = array_keys($friendRankList);
            // 批量查询朋友的活动信息与昵称
            $nicknames = [];
            foreach (DB::table('member')->select('uid', 'nickname')->whereIn('uid', $fuids)->whereNull('deleted_at')->get() as $row) {
                $nicknames[(int)$row['uid']] = (string)$row['nickname'];
            }
            $moons = DB::table('act_moon')->select('uid', 'love')->whereIn('uid', $fuids)->where('version', $act['version'])
                ->whereNull('deleted_at')->orderBy('love', 'DESC')->orderBy('id')->get();
            foreach ($moons as $vx) {
                $fuid = (int)$vx['uid'];
                $tmpl = $friendRankList[$fuid];
                $tmpl['love'] = (int)$vx['love'];
                $tmpl['nickname'] = $nicknames[$fuid] ?? null;
                $fRank[] = $tmpl;
                unset($friendRankList[$fuid]);
            }
            foreach ($friendRankList as $fuid => $vxl) {
                $vxl['nickname'] = $nicknames[$fuid] ?? null;
                $fRank[] = $vxl;
            }
        }
        return ['status' => 1, 'friend_list' => $fRank];
    }

    /**
     * 送月饼: c=act_moon&m=give_moon（fuid 好友、num 1 / 10 / 50、type 大于 0 时月饼不够自动花元宝购买）
     *
     * 1 成功；-1 活动未开启 / 等级不足；-2 操作太快（自己或好友被锁）；-3 缺少参数；-4 不能赠送给自己；-6 获取朋友信息失败；
     * -7 获取活动信息失败；-8 好友建档失败；-9 元宝不足；-10 扣除元宝失败；-11 月饼不足；-13 当前 VIP 剩余购买次数不足
     */
    public static function give_moon(Context $ctx): array
    {
        $now = BossClock::now();
        [$member, $act, $gate] = self::gate($ctx, $now);
        if ($gate !== null) {
            return $gate;
        }
        $uid = $member['uid'];
        if (!self::lock('give_moon' . $uid)) {
            return ['status' => -2, 'msg' => self::LANG['moon_act_request']];
        }
        $friendLock = null;
        try {
            $fuid = (int)$ctx->input('fuid', 0);
            $num = (int)$ctx->input('num', 0);
            $type = (int)$ctx->input('type', 0);
            if ($fuid <= 0 || !in_array($num, [1, 10, 50], true)) {
                return ['status' => -3, 'msg' => self::LANG['moon_act_param']];
            }
            if ($uid === $fuid) {
                return ['status' => -4, 'msg' => self::LANG['moon_give_me']];
            }
            // 检查朋友是否被锁住
            if (!self::lock((string)$fuid)) {
                return ['status' => -2, 'msg' => self::LANG['moon_act_request']];
            }
            $friendLock = (string)$fuid;
            return Member::locked($uid, static fn (): array => self::doGive($member, $act['version'], $fuid, $num, $type, $now));
        } finally {
            if ($friendLock !== null) {
                self::unlock($friendLock);
            }
            self::unlock('give_moon' . $uid);
        }
    }

    /**
     * 送月饼（在送的人的行锁事务里执行）
     *
     * @param array{uid: int, nickname: string, vip: int, level: int} $member
     * @return array<string, mixed>
     */
    private static function doGive(array $member, int $version, int $fuid, int $num, int $type, int $now): array
    {
        $uid = $member['uid'];
        // 获取朋友的信息
        $fNickname = DB::table('member')->where('uid', $fuid)->whereNull('deleted_at')->value('nickname');
        if ($fNickname === null) {
            return ['status' => -6, 'msg' => self::LANG['moon_get_friend']];
        }
        $mine = self::moon($uid, $version);
        $friend = self::moon($fuid, $version);
        if ($mine === null) {
            // 旧版两人都没有记录时回 -7；只有自己没有时读到空记录继续执行，这里同样回 -7
            return ['status' => -7, 'msg' => self::LANG['moon_act_moon_info']];
        }
        // 好友没有记录时建档（照旧版按送的人的 VIP 决定是否送 1 个月饼）
        $friend ??= self::insertMoon($fuid, $version, (string)$fNickname, $member['vip']);
        if ($friend === null) {
            return ['status' => -8, 'msg' => self::LANG['moon_act_undefined_erro']];
        }
        $balance = $mine['mine_moon'] - $num;
        // 判断月饼够不够，type > 0 自动购买月饼
        $incTimes = 0;
        $allGold = 0;
        if ($type > 0 && $mine['mine_moon'] < $num) {
            $balance = 0;
            // 需要购买的个数
            $buyNum = $num - $mine['mine_moon'];
            // 购买次数是否足够
            if ($member['vip'] < 5) {
                $buynum = self::VIP_LIST[$member['vip']] ?? 0;
                if ($mine['buynum'] + $buyNum > $buynum) {
                    return ['status' => -13, 'msg' => self::LANG['moon_get_num']];
                }
                // 剩余购买次数
                $incTimes = $buynum - $buyNum - $mine['buynum'];
            }
            // 购买月饼
            $allGold = $buyNum * self::MOON_PRICE;
            $gold = (int)(DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('gold') ?? 0);
            if ($gold < $allGold) {
                return ['status' => -9, 'msg' => self::LANG['moon_act_gold_insufficient']];
            }
            if (!Member::deductCurrency($uid, gold: $allGold)) {
                return ['status' => -10, 'msg' => self::LANG['moon_act_sub_gold']];
            }
            // 添加月饼（记录购买次数）
            self::addMoon($uid, $uid, $version, $buyNum, 1, $now, buynum: $mine['buynum'] + $buyNum);
        }
        if ($balance < 0) {
            return ['status' => -11, 'msg' => self::LANG['moon_insufficient']];
        }
        // 扣除自己的月饼
        DB::table('act_moon')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')->update(['mine_moon' => $balance]);
        // 添加朋友的月饼
        self::addMoon($uid, $fuid, $version, $num, 2, $now, friendAll: true);
        // 添加赠送记录
        self::addGivefriendLog($uid, $member['nickname'], $version, $fuid, (string)$fNickname, $num, 2, $now);
        // 添加爱心
        $love = $mine['love'] + $num;
        DB::table('act_moon')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')->update(['love' => $love, 'firsttime' => $now]);
        // 添加奖励
        $rewardList = self::rewardAndLog($uid, $version, 2, $num, $now);
        // 添加自己的月饼信息 log
        self::addMoonLog($uid, $version, 2, $num, $balance, 1, $now);
        return [
            'status' => 1,
            'love' => $love,
            'achimg' => self::achImg($uid, $version, $love),
            'gold' => (int)(DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('gold') ?? 0),
            'spent_gold' => $allGold,
            'mine_moon' => $balance,
            'buymoon' => $incTimes,
            'rank_list' => self::rankList($version),
            'reward_list' => $rewardList,
        ];
    }

    /**
     * 好友赠送信息: c=act_moon&m=get_give_info（最近 30 条收到的月饼）
     *
     * 1 成功；-1 活动未开启 / 等级不足；-7 获取活动信息失败
     */
    public static function get_give_info(Context $ctx): array
    {
        $now = BossClock::now();
        [$member, $act, $gate] = self::gate($ctx, $now);
        if ($gate !== null) {
            return $gate;
        }
        $msgList = [];
        foreach (DB::table('act_moon_givefriend_log')->where('frienduid', $member['uid'])->where('version', $act['version'])
            ->whereNull('deleted_at')->orderBy('dateline', 'DESC')->orderBy('id', 'DESC')->limit(30)->get() as $val) {
            $n = (int)$val['num'];
            $msgList[] = [
                'type' => $n === 1 ? 1 : ($n === 10 ? 2 : 3),
                'fuid' => (int)$val['uid'],
                'nickname' => (string)$val['nickname'],
                'msg_str' => date('H:i', (int)$val['dateline']) . sprintf(self::LANG['moon_msg'], $val['nickname'], $n),
            ];
        }
        $moon = self::moon($member['uid'], $act['version']);
        if ($moon === null) {
            return ['status' => -7, 'msg' => self::LANG['moon_act_moon_info']];
        }
        return ['status' => 1, 'friend_moon' => $moon['friend_moon'], 'friend_all' => $moon['friend_all'], 'msg_list' => $msgList];
    }

    /**
     * 吃月饼: c=act_moon&m=eat_moon（num 1 / 10 / 50，吃好友 / 玉兔送的小月饼）
     *
     * 1 成功；-1 活动未开启 / 等级不足；-2 操作太快；-3 缺少参数；-4 获取活动信息失败；-5 剩余小月饼不足
     */
    public static function eat_moon(Context $ctx): array
    {
        $now = BossClock::now();
        [$member, $act, $gate] = self::gate($ctx, $now);
        if ($gate !== null) {
            return $gate;
        }
        $uid = $member['uid'];
        if (!self::lock('eat_moon' . $uid)) {
            return ['status' => -2, 'msg' => self::LANG['moon_act_request']];
        }
        $ownLock = null;
        try {
            $num = (int)$ctx->input('num', 0);
            if (!in_array($num, [1, 10, 50], true)) {
                return ['status' => -3, 'msg' => self::LANG['moon_act_param']];
            }
            // 检查自己是否被锁住（好友正在给自己送月饼）
            if (!self::lock((string)$uid)) {
                return ['status' => -2, 'msg' => self::LANG['moon_act_request']];
            }
            $ownLock = (string)$uid;
            $version = $act['version'];
            // 扣除朋友赠送或者系统赠送的月饼（原子扣减，不足不扣）
            $moon = self::moon($uid, $version);
            if ($moon === null) {
                return ['status' => -4, 'msg' => self::LANG['moon_act_moon_info']];
            }
            $affected = DB::execute(
                'UPDATE `zc_sanguo_act_moon` SET `friend_moon` = `friend_moon` - :num
                 WHERE `uid` = :uid AND `version` = :version AND `friend_moon` >= :need AND `deleted_at` IS NULL',
                ['num' => $num, 'uid' => $uid, 'version' => $version, 'need' => $num]
            );
            if ($affected <= 0) {
                return ['status' => -5, 'msg' => self::LANG['moon_insufficien']];
            }
            $balance = (int)(self::moon($uid, $version)['friend_moon'] ?? 0);
            // 添加奖励
            $rewardList = self::rewardAndLog($uid, $version, 1, $num, $now);
            return ['status' => 1, 'friend_moon' => $balance, 'reward_list' => $rewardList];
        } finally {
            if ($ownLock !== null) {
                self::unlock($ownLock);
            }
            self::unlock('eat_moon' . $uid);
        }
    }

    /**
     * 领取成就奖励: c=act_moon&m=get_reward（id 成就 id）
     *
     * 1 成功；-1 活动未开启 / 等级不足；-2 操作太快；-3 缺少参数；-4 获取成就信息失败；-5 该成就已经领取过；-6 获取活动信息失败；
     * -7 成就未达成；-8 添加成就记录失败
     */
    public static function get_reward(Context $ctx): array
    {
        $now = BossClock::now();
        [$member, $act, $gate] = self::gate($ctx, $now);
        if ($gate !== null) {
            return $gate;
        }
        $uid = $member['uid'];
        if (!self::lock('get_reward' . $uid)) {
            return ['status' => -2, 'msg' => self::LANG['moon_act_request']];
        }
        try {
            $achid = (int)$ctx->input('id', 0);
            if ($achid <= 0) {
                return ['status' => -3, 'msg' => self::LANG['moon_act_param']];
            }
            $ach = ActMoonAchReward::ROWS[$achid] ?? null;
            if ($ach === null) {
                return ['status' => -4, 'msg' => self::LANG['moon_get_ach_info']];
            }
            $version = $act['version'];
            if (isset(self::achLog($uid, $version)[$achid])) {
                return ['status' => -5, 'msg' => self::LANG['moon_get_ach_reward']];
            }
            $moon = self::moon($uid, $version);
            if ($moon === null) {
                return ['status' => -6, 'msg' => self::LANG['moon_act_moon_info']];
            }
            if ($ach['achlove'] > $moon['love']) {
                return ['status' => -7, 'msg' => self::LANG['moon_act_ach_info']];
            }
            // 添加领取 log（唯一键，写入成功才发奖）
            $added = DB::execute(
                'INSERT IGNORE INTO `zc_sanguo_act_moon_ach_log` (`uid`, `version`, `achid`, `dateline`) VALUES (:uid, :version, :achid, :now)',
                ['uid' => $uid, 'version' => $version, 'achid' => $achid, 'now' => $now]
            );
            if ($added <= 0) {
                return ['status' => -8, 'msg' => self::LANG['moon_add_ach_log']];
            }
            // 发送资源
            for ($i = 1; $i <= 3; $i++) {
                self::addReward($uid, $ach['gettype' . $i], $ach['getvalue' . $i], 'get_reward', $now);
            }
            $row = DB::table('member')->select('gold', 'silver')->where('uid', $uid)->whereNull('deleted_at')->first() ?? [];
            return ['status' => 1, 'all_gold' => (int)($row['gold'] ?? 0), 'all_silver' => (int)($row['silver'] ?? 0)];
        } finally {
            self::unlock('get_reward' . $uid);
        }
    }

    /**
     * 成就列表: c=act_moon&m=get_ach_list
     *
     * 1 成功；-1 活动未开启 / 等级不足；-2 获取活动信息失败。reward：0 未达成，1 未领取，2 领取过
     */
    public static function get_ach_list(Context $ctx): array
    {
        $now = BossClock::now();
        [$member, $act, $gate] = self::gate($ctx, $now);
        if ($gate !== null) {
            return $gate;
        }
        $moon = self::moon($member['uid'], $act['version']);
        if ($moon === null) {
            return ['status' => -2, 'msg' => self::LANG['moon_act_moon_info']];
        }
        $achLog = self::achLog($member['uid'], $act['version']);
        $list = [];
        foreach (ActMoonAchReward::ROWS as $val) {
            $val['reward'] = isset($achLog[$val['id']]) ? 2 : ($val['achlove'] <= $moon['love'] ? 1 : 0);
            $list[] = $val;
        }
        return ['status' => 1, 'love' => $moon['love'], 'ach_list' => $list];
    }

    /**
     * 购买月饼: c=act_moon&m=buy_moon（id 1 / 2 / 3：50 元宝 1 个、500 元宝 10 个、2500 元宝 50 个）
     *
     * 1 成功；-1 活动未开启 / 等级不足 / VIP 0 不能买；-2 操作太快；-3 缺少参数；-4 元宝不足；-5 扣除元宝失败；
     * -6 获取活动信息失败；-7 添加月饼失败；-13 当前 VIP 剩余购买次数不足
     */
    public static function buy_moon(Context $ctx): array
    {
        $now = BossClock::now();
        [$member, $act, $gate] = self::gate($ctx, $now);
        if ($gate !== null) {
            return $gate;
        }
        if ($member['vip'] <= 0) {
            return ['status' => -1, 'msg' => self::LANG['moon_vip_buy']];
        }
        $uid = $member['uid'];
        if (!self::lock('buy_moon' . $uid)) {
            return ['status' => -2, 'msg' => self::LANG['moon_act_request']];
        }
        try {
            $buyInfo = self::BUY_LIST[(int)$ctx->input('id', 0)] ?? null;
            if ($buyInfo === null) {
                return ['status' => -3, 'msg' => self::LANG['moon_act_param']];
            }
            return Member::locked($uid, static function () use ($uid, $member, $act, $buyInfo, $now): array {
                $gold = (int)(DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('gold') ?? 0);
                if ($buyInfo['gold'] > $gold) {
                    return ['status' => -4, 'msg' => self::LANG['moon_act_gold_insufficient']];
                }
                $moon = self::moon($uid, $act['version']);
                if ($moon === null) {
                    return ['status' => -6, 'msg' => self::LANG['moon_act_moon_info']];
                }
                $insTimes = 0;
                // 购买次数是否足够
                if ($member['vip'] < 5) {
                    $buynum = self::VIP_LIST[$member['vip']];
                    if ($moon['buynum'] + $buyInfo['moonnum'] > $buynum) {
                        return ['status' => -13, 'msg' => self::LANG['moon_get_num']];
                    }
                    $insTimes = $buynum - $moon['buynum'] - $buyInfo['moonnum'];
                }
                if (!Member::deductCurrency($uid, gold: $buyInfo['gold'])) {
                    return ['status' => -5, 'msg' => self::LANG['moon_act_sub_gold']];
                }
                // 添加月饼（记录购买次数）
                $result = self::addMoon($uid, $uid, $act['version'], $buyInfo['moonnum'], 1, $now, buynum: $moon['buynum'] + $buyInfo['moonnum']);
                if ($result < 0) {
                    return ['status' => -7, 'msg' => self::LANG['moon_act_add']];
                }
                return [
                    'status' => 1,
                    'mine_moon' => $result,
                    'buymoon' => $insTimes,
                    'gold' => (int)(DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('gold') ?? 0),
                ];
            });
        } finally {
            self::unlock('buy_moon' . $uid);
        }
    }

    /**
     * 充值送月饼（pay.php:pay_act_init → moon_model->add_pay_moon）：活动开启时记一笔中秋充值订单（money 为元宝 / 10），
     * 累计每满 30 元送 1 个月饼（上次不足 30 的余数累加），VIP 0 的首笔充值另送 1 个；送了月饼时发信件。
     * 照旧版：$vip 为到账前的 VIP；没有活动记录时建档（充值回调里没有当前玩家，建档不送 VIP 月饼）。
     */
    public static function addPayMoon(int $uid, string $nickname, int $level, int $vip, int $gold, string $orderId, string $class, string $method, int $now): void
    {
        $info = ActivityClock::open(self::ACTIVITY, $now);
        if ($info === null || (int)$info['endtime'] <= $now) {
            return;
        }
        $version = (int)$info['version'];
        $money = intdiv($gold, 10);
        // 本期之前的充值合计
        $allMoney = (int)DB::table('act_moon_order')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')->sum('money');
        // 累加上次充值未达成 30 多余的部分
        $addMoon = $allMoney % 30 > 0 ? intdiv($allMoney % 30 + $money, 30) : intdiv($money, 30);
        DB::table('act_moon_order')->insert([
            'uid' => $uid,
            'version' => $version,
            'nickname' => Member::logText($nickname, 80),
            'level' => $level,
            'money' => $money,
            'orderid' => $orderId,
            'class' => Member::logText($class, 20),
            'method' => Member::logText($method, 20),
            'dateline' => $now,
        ]);
        $moon = self::moon($uid, $version) ?? self::insertMoon($uid, $version, $nickname, 0);
        if ($moon === null) {
            return;
        }
        $addMoonRx = $addMoon;
        if ($vip === 0 && $allMoney === 0) {
            $addMoonRx++;
        }
        if ($addMoonRx > 0) {
            DB::execute(
                'UPDATE `zc_sanguo_act_moon` SET `mine_moon` = `mine_moon` + :add WHERE `uid` = :uid AND `version` = :version AND `deleted_at` IS NULL',
                ['add' => $addMoonRx, 'uid' => $uid, 'version' => $version]
            );
            self::addMoonLog($uid, $version, 1, $addMoonRx, (int)(self::moon($uid, $version)['mine_moon'] ?? 0), 1, $now, $class, $method);
        }
        if ($addMoon === 0) {
            return;
        }
        // 发送赠送信息
        MessageHandler::sendSystemMessage([$uid], 1, self::LANG['message_system_title'], sprintf(self::LANG['moon_pay_message'], $addMoon));
    }

    /**
     * 计划任务：活动结束后把剩余月饼自动兑换成银币（cron_acttask.php:clear_moon）
     *
     * 只在活动结束后 10 小时内执行（endtime <= 当前 <= endtime + 36000）。每个还有月饼的玩家：大月饼 × 5 万 + 小月饼 × 1 万银币，
     * 发信件；处理过的玩家记入 Redis 集合，重复执行不重复发（旧版记日志文件；月饼数不清零，同旧版）。
     *
     * @return int -1 不在发放窗口；-2 正在执行（锁）；1 已执行
     */
    public static function cronReward(int $now): int
    {
        $info = ActivityClock::byName(self::ACTIVITY);
        if ($info === null || $now < $info['endtime'] || $info['endtime'] + 36000 < $now) {
            return -1;
        }
        $version = (int)$info['version'];
        $lockKey = 'act_key_moon_' . $version;
        if (!(bool)Redis::instance()->set($lockKey, 1, ['nx', 'ex' => 30])) {
            return -2;
        }
        try {
            $listKey = self::CRON_LIST_KEY . $version;
            foreach (DB::table('act_moon')->select('uid', 'mine_moon', 'friend_moon')->where('version', $version)->whereNull('deleted_at')->orderBy('id')->get() as $val) {
                $uid = (int)$val['uid'];
                $mine = (int)$val['mine_moon'];
                $friend = (int)$val['friend_moon'];
                if (($mine === 0 && $friend === 0) || (int)Redis::instance()->sAdd($listKey, (string)$uid) === 0) {
                    continue;
                }
                Redis::expire($listKey, 7 * 86400);
                $allsilver = $mine * self::DA_MOON + $friend * self::XIAO_MOON;
                $msgStr = $allsilver . self::LANG['cron_silver'] . ',';
                if ($mine > 0 && $friend > 0) {
                    $msg = sprintf(self::LANG['cron_moon_msg'], $mine, $friend, $msgStr);
                } elseif ($mine > 0) {
                    $msg = sprintf(self::LANG['cron_moon_msg1'], $mine, $msgStr);
                } else {
                    $msg = sprintf(self::LANG['cron_moon_msg2'], $friend, $msgStr);
                }
                Member::addCurrency($uid, silver: $allsilver);
                MessageHandler::sendSystemMessage([$uid], 1, self::LANG['message_system_title'], $msg);
            }
            return 1;
        } finally {
            Redis::del($lockKey);
        }
    }

    /**
     * 公共判断：活动未开启 / 等级不足回 -1
     *
     * @return array{0: array{uid: int, nickname: string, vip: int, level: int}, 1: array{version: int, timestr: string}, 2: array<string, mixed>|null}
     */
    private static function gate(Context $ctx, int $now): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $row = $uid > 0 ? DB::table('member')->select('uid', 'nickname', 'vip', 'level')->where('uid', $uid)->whereNull('deleted_at')->first() : null;
        $member = ['uid' => $uid, 'nickname' => (string)($row['nickname'] ?? ''), 'vip' => (int)($row['vip'] ?? 0), 'level' => (int)($row['level'] ?? 0)];
        $info = ActivityClock::open(self::ACTIVITY, $now);
        if ($info === null || $row === null) {
            return [$member, ['version' => 0, 'timestr' => ''], ['status' => -1, 'msg' => self::LANG['moon_act_not_open']]];
        }
        $act = [
            'version' => (int)$info['version'],
            'timestr' => self::dateText((int)$info['starttime']) . '-' . self::dateText((int)$info['endtime']),
        ];
        if (self::OPEN_LEVEL > $member['level']) {
            return [$member, $act, ['status' => -1, 'msg' => self::LANG['moon_not_level']]];
        }
        return [$member, $act, null];
    }

    /**
     * 活动日期（中文语言包：m月d日）
     */
    private static function dateText(int $time): string
    {
        return date('m', $time) . self::LANG['moon_month'] . date('d', $time) . self::LANG['moon_day'];
    }

    private static function lock(string $suffix): bool
    {
        return (bool)Redis::instance()->set(self::LOCK_KEY . $suffix, 1, ['nx', 'ex' => self::LOCK_TTL]);
    }

    private static function unlock(string $suffix): void
    {
        Redis::del(self::LOCK_KEY . $suffix);
    }

    /**
     * 玩家月饼信息（get_moon）；没有为 null
     *
     * @return array{uid: int, mine_moon: int, love: int, friend_moon: int, firsttime: int, nickname: string, buynum: int, sysdate: int, friend_all: int}|null
     */
    private static function moon(int $uid, int $version): ?array
    {
        $row = DB::table('act_moon')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')->first();
        if ($row === null) {
            return null;
        }
        return [
            'uid' => (int)$row['uid'],
            'mine_moon' => (int)$row['mine_moon'],
            'love' => (int)$row['love'],
            'friend_moon' => (int)$row['friend_moon'],
            'firsttime' => (int)$row['firsttime'],
            'nickname' => (string)$row['nickname'],
            'buynum' => (int)$row['buynum'],
            'sysdate' => (int)$row['sysdate'],
            'friend_all' => (int)$row['friend_all'],
        ];
    }

    /**
     * 初始化月饼信息（insert_moon：$vip 为当前玩家的 VIP，大于 0 时送 1 个可以赠送的月饼；唯一键 uid + version，并发只插一行）
     *
     * @return array<string, int|string>|null
     */
    private static function insertMoon(int $uid, int $version, string $nickname, int $vip): ?array
    {
        DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_act_moon` (`uid`, `mine_moon`, `version`, `love`, `friend_moon`, `firsttime`, `nickname`, `buynum`, `sysdate`, `friend_all`)
             VALUES (:uid, :mine, :version, 0, 0, 0, :nickname, 0, 0, 0)',
            ['uid' => $uid, 'mine' => $vip > 0 ? 1 : 0, 'version' => $version, 'nickname' => Member::logText($nickname, 250)]
        );
        return self::moon($uid, $version);
    }

    /**
     * 添加月饼（_add_moon：type 1 自己的月饼（购买 / 充值），2 好友 / 系统送的小月饼；原子增加），记月饼日志（记在当前玩家名下）
     *
     * $friendAll 为 true 时同时累加 friend_all；$sysdate 为 true 时记今天已送系统月饼；$buynum 大于 0 时写已购买数量。
     *
     * @return int 增加后的数量；没有记录为 -2
     */
    private static function addMoon(int $logUid, int $uid, int $version, int $num, int $type, int $now, int $buynum = 0, bool $sysdate = false, bool $friendAll = false): int
    {
        $column = $type === 1 ? 'mine_moon' : 'friend_moon';
        $set = "`{$column}` = `{$column}` + :num";
        $bind = ['num' => $num, 'uid' => $uid, 'version' => $version];
        if ($sysdate) {
            $set .= ', `sysdate` = :sysdate';
            $bind['sysdate'] = $now;
        }
        if ($friendAll) {
            $set .= ', `friend_all` = `friend_all` + :all';
            $bind['all'] = $num;
        }
        if ($buynum > 0) {
            $set .= ', `buynum` = :buynum';
            $bind['buynum'] = $buynum;
        }
        $affected = DB::execute("UPDATE `zc_sanguo_act_moon` SET {$set} WHERE `uid` = :uid AND `version` = :version AND `deleted_at` IS NULL", $bind);
        if ($affected <= 0) {
            return -2;
        }
        $balance = (int)(self::moon($uid, $version)[$column] ?? 0);
        self::addMoonLog($logUid, $version, 1, $num, $balance, $type, $now);
        return $balance;
    }

    /**
     * 当天第一次进入发送免费月饼（_system_moon）：玉兔送 10 个小月饼，记赠送记录；发了返回 true
     *
     * @param array{uid: int, nickname: string} $member
     * @param array{sysdate: int} $moon
     */
    private static function systemMoon(array $member, int $version, array $moon, int $now): bool
    {
        if (date('Ymd', $moon['sysdate']) === date('Ymd', $now)) {
            return false;
        }
        // 条件更新：并发只发一次
        $affected = DB::execute(
            'UPDATE `zc_sanguo_act_moon` SET `friend_moon` = `friend_moon` + :num, `friend_all` = `friend_all` + :all, `sysdate` = :now
             WHERE `uid` = :uid AND `version` = :version AND `sysdate` = :old AND `deleted_at` IS NULL',
            ['num' => self::SYS_MOON, 'all' => self::SYS_MOON, 'now' => $now, 'uid' => $member['uid'], 'version' => $version, 'old' => $moon['sysdate']]
        );
        if ($affected <= 0) {
            return false;
        }
        self::addMoonLog($member['uid'], $version, 1, self::SYS_MOON, (int)(self::moon($member['uid'], $version)['friend_moon'] ?? 0), 2, $now);
        // 插入系统赠送信息
        self::addGivefriendLog(0, self::LANG['moon_zhugong'], $version, $member['uid'], $member['nickname'], self::SYS_MOON, 1, $now);
        return true;
    }

    /**
     * 送 / 吃月饼抽奖、发奖并记奖励日志（_reward + _add_reward + insert_giveeat_log）
     *
     * @return list<array{type: int, params: int}>
     */
    private static function rewardAndLog(int $uid, int $version, int $type, int $num, int $now): array
    {
        $rewardList = $type === 1 ? array_values(ActMoonEat::ROWS) : array_values(ActMoonGive::ROWS);
        if ($rewardList === []) {
            return [];
        }
        // 得到概率总数与最小值
        $total = array_sum(array_column($rewardList, 'rate'));
        $min = min(array_column($rewardList, 'rate'));
        // 按次数随机，同类型的奖励参数加起来
        $addReward = [];
        for ($i = 1; $i <= $num; $i++) {
            $randNum = mt_rand($min, $total);
            $nowRate = 0;
            foreach ($rewardList as $val) {
                $nowRate += $val['rate'];
                if ($randNum <= $nowRate) {
                    if (isset($addReward[$val['type']])) {
                        $addReward[$val['type']]['params'] += $val['params'];
                    } else {
                        $addReward[$val['type']] = ['type' => $val['type'], 'params' => $val['params']];
                    }
                    break;
                }
            }
        }
        $data = [];
        foreach ($addReward as $reward) {
            self::addReward($uid, $reward['type'], $reward['params'], $type === 1 ? 'eat_moon' : 'give_moon', $now);
            $data[] = ['uid' => $uid, 'version' => $version, 'type' => $type, 'rewardtype' => $reward['type'], 'rewardid' => $reward['params'], 'dateline' => $now];
        }
        if ($data !== []) {
            DB::table('act_moon_giveeat_log')->insertBatch($data);
        }
        return array_values($addReward);
    }

    /**
     * 组织奖励（_add_reward；8 装备、12 武将、17 ~ 26 旧版已注释掉）
     */
    private static function addReward(int $uid, int $type, int $num, string $method, int $now): void
    {
        match ($type) {
            1 => Member::addCurrency($uid, gold: $num),
            2 => Member::addCurrency($uid, silver: $num),
            3 => TraderLog::addJade($uid, 2, $num, class: 'act_moon', method: $method, now: $now),
            4 => TraderLog::addJade($uid, 3, $num, class: 'act_moon', method: $method, now: $now),
            5 => Member::addCurrency($uid, soul: $num),
            6 => MemberJade::add($uid, $num, 'act_moon', $method, $now),
            7 => Member::addReputation($uid, $num),
            9 => OwnGem::addChip($uid, $num, 'act_moon', $method, $now),
            10 => FreeLeap::add($uid, $num, $now),
            11 => TraderLog::addJade($uid, 1, $num, class: 'act_moon', method: $method, now: $now),
            13 => Member::addCurrency($uid, act: $num),
            14 => MemberStone::add($uid, $num, MemberStone::REFINE, 'act_moon', $method, $now),
            15 => MemberStone::add($uid, $num, MemberStone::COLOR, 'act_moon', $method, $now),
            16 => MemberStone::add($uid, $num, MemberStone::BLOOD, 'act_moon', $method, $now),
            27 => MemberIntegral::change($uid, $num, 'beauty_rose', 1, 'act_moon', $method, $now),
            28 => MemberIntegral::change($uid, $num, 'beauty_diamond', 1, 'act_moon', $method, $now),
            default => null,
        };
    }

    /**
     * 爱心排行（get_rank_list：firsttime > 0、love > 0，按爱心降序、到达时间升序，前 10）
     *
     * @return list<array{uid: int, nickname: string, love: int}>
     */
    private static function rankList(int $version): array
    {
        $rows = DB::table('act_moon')->select('uid', 'nickname', 'love')->where('version', $version)->where('firsttime', '>', 0)->where('love', '>', 0)
            ->whereNull('deleted_at')->orderBy('love', 'DESC')->orderBy('firsttime')->orderBy('id')->limit(10)->get();
        return array_map(static fn (array $r): array => ['uid' => (int)$r['uid'], 'nickname' => (string)$r['nickname'], 'love' => (int)$r['love']], $rows);
    }

    /**
     * 是否有可领的成就（成就感叹号）
     */
    private static function achImg(int $uid, int $version, int $love): int
    {
        $achLog = self::achLog($uid, $version);
        foreach (ActMoonAchReward::ROWS as $val) {
            if ($val['achlove'] <= $love && !isset($achLog[$val['id']])) {
                return 1;
            }
        }
        return 0;
    }

    /**
     * 成就领取记录（get_ach_log，按 achid 索引）
     *
     * @return array<int, true>
     */
    private static function achLog(int $uid, int $version): array
    {
        $return = [];
        foreach (DB::table('act_moon_ach_log')->select('achid')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')->get() as $row) {
            $return[(int)$row['achid']] = true;
        }
        return $return;
    }

    /**
     * 月饼日志（add_moon_log：type 1 获得 2 消耗；moontype 1 自己的月饼，2 系统 / 好友的月饼）
     */
    private static function addMoonLog(int $uid, int $version, int $type, int $num, int $balance, int $moontype, int $now, ?string $class = null, ?string $method = null): void
    {
        // class / method 记当前请求的控制器与方法
        [$currentClass, $currentMethod] = FlowLog::current();
        DB::table('act_moon_log')->insert([
            'uid' => $uid,
            'version' => $version,
            'type' => $type,
            'moontype' => $moontype,
            'num' => $num,
            'balance' => max(0, $balance),
            'class' => Member::logText($class ?? $currentClass, 80),
            'method' => Member::logText($method ?? $currentMethod, 80),
            'dateline' => $now,
        ]);
    }

    /**
     * 好友 / 系统送月饼记录（insert_givefriend_log：type 1 系统赠送，2 好友赠送）
     */
    private static function addGivefriendLog(int $uid, string $nickname, int $version, int $fuid, string $fnickname, int $num, int $type, int $now): void
    {
        DB::table('act_moon_givefriend_log')->insert([
            'uid' => $uid,
            'nickname' => Member::logText($nickname, 80),
            'version' => $version,
            'frienduid' => $fuid,
            'friendname' => Member::logText($fnickname, 80),
            'num' => $num,
            'type' => $type,
            'dateline' => $now,
        ]);
    }
}
