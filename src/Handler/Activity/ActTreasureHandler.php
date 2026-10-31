<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\General\TavernHandler;
use Hanjiang\MasterData\ActTreasureHanziach;
use Hanjiang\MasterData\ActTreasureHanzireward;
use Hanjiang\MasterData\ActTreasureRank;
use Hanjiang\MasterData\ActTreasureReward;
use Hanjiang\MasterData\Equip;
use Hanjiang\MasterData\EquipEssence;
use Hanjiang\MasterData\General as GeneralTemplate;
use Hanjiang\MasterData\MountTemp;
use Hanjiang\MasterData\Skill;
use Hanjiang\MasterData\Talent;
use Hanjiang\Model\ActivityClock;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\Chat;
use Hanjiang\Model\Equipment;
use Hanjiang\Model\FestLock;
use Hanjiang\Model\FestReward;
use Hanjiang\Model\FreeLeap;
use Hanjiang\Model\General;
use Hanjiang\Model\Member;
use Hanjiang\Model\MemberJade;
use Hanjiang\Model\MemberStone;
use Hanjiang\Model\OwnGem;
use Hanjiang\Model\OwnMount;
use Hanjiang\Model\PubLog;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 踏青寻宝（移植旧版 controllers/act_treasure.php + models/acttreasure_model.php，计划任务 cron_acttask.php:treasure，
 * 主城 member.php index 的 VIP 同步与 acttreasure_popo、member_model->daily_cron 的 treasure_free）
 *
 * 活动时间与版本取 ActivityTime 的 acttreasure（旧版数据已结束）；活动结束后图标多挂一天，所以寻宝截止在 endtime - 86401。
 * 50 级开放。19 格寻宝圈（act_treasure.reward_str，首次进入或回到起点时按 MasterData ActTreasureReward 的价值类型 p_type 与
 * get_rate 刷新：上半圈 4 个汉字 + 价值 1 两个 + 价值 2 两个 + 价值 3 一个 + 第 10 格双倍格，下半圈其余 4 个汉字与同样的搭配，
 * 汉字与奖励间隔排列）。每次寻宝（每天第一次免费 member_field.treasure_free，否则 50 元宝）在前方 6 格里按 site_rate 加权停一格
 * （VIP 5 以下不出装备）：问号格从基础表再抽一份，双倍格让下一次奖励翻倍，第 14 格以后落到起点附近即回起点（刷新奖励圈、下次免费）。
 * 百次寻宝 4800 元宝（96 折），直接从基础表抽 100 次（不移动）。汉字（悍将三国谁与争锋）集齐对应字可在 ActTreasureHanzireward
 * 兑换（扣对应字各 1 个），累计集齐 8 字的次数达到 ActTreasureHanziach 领集字成就。寻宝次数（VIP 2 以上）前 30 名在活动结束后
 * 领 ActTreasureRank。每天 0 点计划任务把玩家剩余的每个汉字自动兑换 30 元宝（参与奖），活动结束 10 小时内补发未领的寻宝排行与
 * 集字成就奖励（均发信件）。
 * 客户端 acttreasurepop.js 调用 index、treasure、treasure_two（type 1）、today_reward、hanzi_index、hanzi_exchange（hid）、
 * treasure_rank、treasure_rank_reward、hanzi_achieve、hanzi_achieve_reward（aid）。当前时间取 BossClock。
 *
 * 照旧版保留：随机（rand_arr）按 rate × 10 划分区间、区间下界不中，去重后再加入、最后一次加入不再去重（刷新出的同一行可能出现两次）；
 * 寻宝时元宝在抽奖之前扣（抽不到回 3，不退）；部分失败分支不释放操作锁（等级不足、兑换时活动未开启、百次寻宝参数错误，30 秒后过期）；
 * 奖励数量翻倍时兑换的武将 / 坐骑仍只发一份；蓝宝石 12 与军令 14 不发放（旧版发奖函数没有这两类）；集字兑换的奖励用不到 id
 * （装备 / 坐骑 / 武将不会发出）；计划任务的集字成就统计里某个玩家缺字后，后面的玩家都按 0 次处理。
 * 与旧版的差别：各操作锁改为 Redis SET NX；寻宝 / 兑换 / 领奖在玩家行锁里执行；领奖状态与成就记录用条件更新 / 唯一键，成功才发奖；
 * 计划任务的汉字兑换按汉字字符串条件清空成功才发元宝。
 */
final class ActTreasureHandler
{
    public const string ACTIVITY = 'acttreasure';

    /** 寻宝花费元宝数量 */
    private const int SUB_GOLD = 50;
    /** 寻100次折扣值 */
    private const float TREA_REBATE = 0.96;
    /** 宝石折扣 */
    private const float REBATE = 0.8;
    /** 活动开启等级 */
    private const int OPEN_LEVEL = 50;
    /** 寻宝排行 VIP 限制 */
    private const int VIP = 2;
    /** 寻宝获得橙装或装备的 VIP 限制 */
    private const int TVIP = 5;
    /** 取寻宝排行数据 */
    private const int RANK_NUM = 30;

    private const string TREASURE_LOCK = 'treasure_lock_';
    private const string EXCHANGE_LOCK = 'exchange_lock';
    private const string RANK_LOCK = 'rank_lock';
    private const string HANZIACH_LOCK = 'hanziach_lock';

    /** 奖励 ID => 字体 ID 数组（$_hanzi_arr） */
    private const array HANZI_ARR = [
        1 => ['1', '2', '3', '4', '5', '6', '7', '8'],
        2 => ['1', '2', '3', '4'],
        3 => ['5', '6', '7', '8'],
        4 => ['1', '2'],
        5 => ['3', '4'],
        6 => ['5', '6'],
        7 => ['7', '8'],
    ];

    /** 装备精华类型 21 ~ 29 对应的 equip_essence.id */
    private const array ESSENCE_ID = [21 => 1, 22 => 7, 23 => 13, 24 => 2, 25 => 8, 26 => 14, 27 => 3, 28 => 9, 29 => 15];

    /** treasure_lang.php + chat_lang.php */
    private const array LANG = [
        'treasure_month' => '月',
        'treasure_day' => '日',
        'treasure_not_level' => '50级开启寻宝！',
        'treasure_not_open' => '功能未开启！',
        'treasure_reward_error' => '奖励表错误！',
        'treasure_fast_error' => '寻宝步伐太快了！',
        'treasure_gold_error' => '元宝不足！',
        'treasure_treasure_error' => '寻宝奖励信息错误！',
        'treasure_param_error' => '传递参数错误！',
        'treasure_hanzi_error' => '获取汉字兑换奖励信息失败！',
        'treasure_not_exchage_error' => '汉字数量不满足兑换条件！',
        'treasure_hanzi_failed' => '汉字兑换失败！',
        'treasure_not_base_error' => '寻宝排行奖励表错误！',
        'treasure_rank_get_error' => '领取寻宝排行奖励时间未到！',
        'treasure_not_rank_error' => '您不在寻宝排名中，不能领取奖励。',
        'treasure_rank_gone_error' => '您已经领取排行奖励！',
        'treasure_rank_update_error' => '更新寻宝排行次数信息错误！',
        'treasure_op_fast_error' => '操作不要太频繁！',
        'treasure_end_error' => '活动已经结束！',
        'treasure_not_hanziachbase_error' => '集字成就基础表信息错误！',
        'treasure_hanziach_none_error' => '未达到此成就！',
        'treasure_hanziach_rewarded_error' => '已经领取此成就奖励！',
        'treasure_hanziach_addfield_error' => '添加集字成就日志失败！',
        'treasure_general_get' => '恭喜玩家【%s】在寻宝活动中获得%s星武将-%s',
        'treasure_get_reward' => '恭喜您获得',
        'chat_treasure_get' => '恭喜【%s】在寻宝活动中获得装备-%s',
        'chat_treasure_mount_get' => '恭喜【%s】在寻宝活动中获得名马-%s',
    ];

    /** 奖励类型名（treasure_1 ~ 29） */
    private const array TYPE_NAME = [
        1 => '元宝', 2 => '银币', 3 => '黄宝石', 4 => '紫宝石', 5 => '原魂', 6 => '玉石', 7 => '声望', 8 => '装备', 9 => '宝石碎片',
        10 => '突飞卡', 11 => '坐骑', 12 => '蓝宝石', 13 => '武将', 14 => '军令', 15 => '精炼石', 16 => '五彩石', 17 => '上古血石',
        18 => '问号格', 19 => '汉字', 20 => '双倍格', 21 => '武器精华（白）', 22 => '防具精华（白）', 23 => '饰品精华（白）',
        24 => '武器精华（绿）', 25 => '防具精华（绿）', 26 => '饰品精华（绿）', 27 => '武器精华（蓝）', 28 => '防具精华（蓝）', 29 => '饰品精华（蓝）',
    ];

    /** cron_lang.php */
    private const array LANG_CRON = [
        'cron_greet' => '您好，您昨日在寻宝活动中总共收集了',
        'cron_word' => '个汉字，共计',
        'cron_please' => '元宝，系统已发放到您的账户，请查收。',
        'cron_automatic_get' => '亲爱的玩家！寻宝活动已结束，您未领取的寻宝次数排行奖励，系统已自动领取，',
        'cron_jizi' => '亲爱的玩家！寻宝活动已结束，您未领取的集字成就奖励已自动帮您领取，共获得：',
        'cron_get' => '获得了第',
        'cron_award' => '名的奖励：',
        'cron_check' => '请查收!',
        'cron_ge' => '个，',
        'message_system_title' => '系统通知',
        1 => '元宝', 2 => '银币', 3 => '黄宝石', 4 => '紫宝石', 5 => '将魂', 6 => '玉石', 7 => '声望', 8 => '装备-', 9 => '宝石碎片',
        10 => '突飞卡', 11 => '坐骑-', 12 => '蓝宝石', 14 => '军令', 15 => '精炼石', 16 => '五彩石', 17 => '上古血石',
        21 => '白色武器精华', 22 => '白色防具精华', 23 => '白色饰品精华', 24 => '绿色武器精华', 25 => '绿色防具精华', 26 => '绿色饰品精华',
        27 => '蓝色武器精华', 28 => '蓝色防具精华', 29 => '蓝色饰品精华',
        'cron_change_purple' => '武将兑换紫宝石',
        'cron_general' => '武将-',
    ];

    /**
     * 踏青寻宝-首页: c=act_treasure&m=index
     *
     * -3 等级不足；-2 奖励表错误；-1 功能未开启；0 失败；1 成功
     */
    public static function index(Context $ctx): array
    {
        $return = ['status' => 0, 'vip' => self::VIP];
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $act = self::init($now);
        if ($act === null || $uid <= 0) {
            return ['status' => -1, 'vip' => self::VIP, 'msg' => self::LANG['treasure_not_open']];
        }
        $member = self::member($uid);
        if ($member['level'] < self::OPEN_LEVEL) {
            return ['status' => -3, 'vip' => self::VIP, 'msg' => self::LANG['treasure_not_level']];
        }
        $site = $hzexchReward = $hzachReward = 0;
        $reward = self::treasureRow($uid, $act['version']);
        if ($reward !== null) {
            $refreshList = self::decodeList($reward['reward_str']);
            $site = $reward['site'];
            $hzachReward = $reward['hzach_reward'];
            $hzexchReward = $reward['hzexch_reward'];
        } else {
            // 获取刷新奖励列表
            $refreshList = self::refreshReward($uid, $act['version'], $member, $now);
            if ($refreshList === null) {
                return ['status' => -2, 'vip' => self::VIP, 'msg' => self::LANG['treasure_reward_error']];
            }
        }
        // 查看玩家当前位置之前的信息（最近 20 条日志里回到起点之后的位置）
        $siteArr = [];
        if ($site > 0) {
            foreach (DB::table('act_treasure_log')->select('site')->where('uid', $uid)->where('version', $act['version'])->whereNull('deleted_at')
                ->orderBy('dateline', 'DESC')->orderBy('id', 'DESC')->limit(20)->get() as $log) {
                if ((int)$log['site'] === 0) {
                    break;
                }
                $siteArr[] = (int)$log['site'];
            }
        }
        $return['status'] = 1;
        $return['list'] = self::getRefreshList($refreshList, $siteArr);
        $return['sub_gold'] = self::SUB_GOLD;
        $return['sub_h_gold'] = self::subGold100();
        $return['free'] = max(0, self::treasureFree($uid));
        $return['site'] = $site;
        $return['time'] = $act['act_start'] . '-' . $act['act_end'];
        $return['all_gold'] = $member['gold'];
        $return['all_silver'] = $member['silver'];
        $return['hzexch_popo'] = $hzexchReward;
        $return['hzach_popo'] = $hzachReward;
        return $return;
    }

    /**
     * 踏青寻宝-寻宝: c=act_treasure&m=treasure
     *
     * -3 等级不足 / 活动已经结束；-2 奖励表错误；-1 活动未开启；0 失败；1 成功；2 寻宝步伐太快了；3 寻宝奖励信息错误；4 元宝不足
     */
    public static function treasure(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $lockKey = self::TREASURE_LOCK . $uid;
        if ($uid <= 0 || !FestLock::acquire($lockKey)) {
            return ['status' => 2, 'vip' => self::VIP, 'msg' => self::LANG['treasure_fast_error']];
        }
        $release = true;
        try {
            $act = self::init($now);
            if ($act === null) {
                return ['status' => -1, 'vip' => self::VIP, 'msg' => self::LANG['treasure_not_open']];
            }
            if (self::member($uid)['level'] < self::OPEN_LEVEL) {
                // 照旧版：等级不足不解锁
                $release = false;
                return ['status' => -3, 'vip' => self::VIP, 'msg' => self::LANG['treasure_not_level']];
            }
            // 判断活动是否结束
            if ($act['reward_cd'] <= 0) {
                return ['status' => -3, 'vip' => self::VIP, 'msg' => self::LANG['treasure_end_error']];
            }
            if (ActTreasureReward::ROWS === []) {
                return ['status' => -2, 'vip' => self::VIP, 'msg' => self::LANG['treasure_reward_error']];
            }
            return Member::locked($uid, static fn (): array => self::doTreasure($uid, $act['version'], $now));
        } finally {
            if ($release) {
                FestLock::release($lockKey);
            }
        }
    }

    /**
     * 寻宝一次（在玩家行锁事务里执行）
     *
     * @return array<string, mixed>
     */
    private static function doTreasure(int $uid, int $version, int $now): array
    {
        $return = ['status' => 0, 'vip' => self::VIP];
        $member = self::member($uid);
        $site = $free = 0;
        // 寻宝记录次数
        $tnum = 1;
        $reward = self::treasureRow($uid, $version);
        if ($reward !== null) {
            $refreshList = self::decodeList($reward['reward_str']);
            $site = $reward['site'];
        } else {
            $refreshList = self::refreshReward($uid, $version, $member, $now);
            if ($refreshList === null) {
                return ['status' => -2, 'vip' => self::VIP, 'msg' => self::LANG['treasure_reward_error']];
            }
        }
        $free = max(0, self::treasureFree($uid));
        // 判断玩家元宝是否足够
        if ($free === 0) {
            if ($member['gold'] < self::SUB_GOLD || !Member::deductCurrency($uid, gold: self::SUB_GOLD)) {
                return ['status' => 4, 'vip' => self::VIP, 'msg' => self::LANG['treasure_gold_error']];
            }
        } else {
            // 免费的不记录寻宝次数的排行
            $tnum = 0;
        }
        $vip = $member['vip'];
        // 下次开始 / 结束位置数
        $siteStart = $site + 1;
        $siteEnd = $siteStart + 5;
        $totalRate = 0.0;
        $newReward = [];
        foreach ($refreshList as $key => $value) {
            if ($key >= $siteStart && $key <= $siteEnd) {
                // 获得橙装或装备VIP等级限制
                if ($value !== null && $vip < self::TVIP && ($value['type'] ?? 0) === 8) {
                    $value['site_rate'] = 0;
                    $refreshList[$key] = $value;
                }
                $newReward[$key] = $value;
                $totalRate += (float)($value['site_rate'] ?? 0);
            }
        }
        // 奖励不足6个时，添加起点后的奖励信息
        $wantNum = 6 - count($newReward);
        for ($i = 1; $i <= $wantNum; $i++) {
            $value = $refreshList[$i] ?? null;
            if ($value !== null && $vip < self::TVIP && ($value['type'] ?? 0) === 8) {
                $value['site_rate'] = 0;
                $refreshList[$i] = $value;
            }
            $newReward[$i] = $value;
            $totalRate += (float)($value['site_rate'] ?? 0);
        }
        [$markArr, $markTotalRate] = self::markArr($vip);
        $rewardInfo = ['type' => 0];
        $newSite = 0;
        if ($newReward !== []) {
            $last = self::randArr($newReward, $totalRate, 1, 'site_rate', $vip);
            if (!isset($last[0])) {
                // 寻宝奖励信息错误（照旧版：元宝已扣）
                return ['status' => 3, 'vip' => self::VIP, 'msg' => self::LANG['treasure_treasure_error']];
            }
            $rewardInfo = $last[0];
            // 获取新位置数
            foreach ($newReward as $rkey => $reval) {
                if ($reval !== null && $reval['id'] === $rewardInfo['id']) {
                    $newSite = $site >= 14 && $rkey <= 6 && $rkey > 0 ? 0 : $rkey;
                }
            }
        }
        $nowSite = $newSite;
        // 验证玩家上次是否抽到双倍奖励标识
        $doubleM = ($reward['double'] ?? 0) === 1 ? 1 : 0;
        $lastRewardList = [];
        if ($nowSite === 0) {
            // 位置数大于等于20，玩家回到起点，刷新奖励列表
            $relist = self::refreshReward($uid, $version, $member, $now) ?? [];
            $lastRewardList = self::getRefreshList($relist);
            self::addRewardLog($uid, $version, 0, 0, 0, 0, $now);
        } elseif ($nowSite === 10) {
            // 对双倍奖励进行单独处理
            $return['info']['type'] = 20;
            self::addRewardLog($uid, $version, 10, 20, 0, 0, $now);
        } else {
            // 问号格奖励格处理，从奖励基础表随机出一个奖励
            if (($rewardInfo['type'] ?? 0) === 18 && $markArr !== []) {
                $rewardInfo = self::randArr($markArr, $markTotalRate, 1, 'site_rate', $vip)[0] ?? null;
            }
            if ($rewardInfo === null) {
                // 问号格没有抽到（旧版取到空奖励照常写日志、不发奖）
                self::addRewardLog($uid, $version, $nowSite, 0, 0, 0, $now);
            } elseif ($rewardInfo['type'] === 19) {
                // 处理汉字奖励
                $return = self::doHanziReward($uid, $version, $rewardInfo, (int)$rewardInfo['num'], $doubleM, $nowSite, 1, $now);
            } else {
                // 普通奖励处理
                $num = (int)$rewardInfo['num'];
                if (in_array($rewardInfo['type'], [8, 11, 13], true)) {
                    $rewardInfo['id'] = $num;
                    $rewardInfo['num'] = 1;
                }
                $return = self::getReward($uid, $version, $rewardInfo, $doubleM, $nowSite, 'treasure', $now);
            }
        }
        // 更新玩家寻宝当前位置/双倍奖励标识（下次）
        $double = $nowSite === 10 ? 1 : 0;
        DB::table('act_treasure')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')->update([
            'dateline' => $now,
            'site' => $nowSite,
            'double' => $double,
            'num' => ($reward['num'] ?? 0) + $tnum,
            'vip' => $vip,
        ]);
        // 更新玩家免费次数
        $free = $nowSite === 0 ? 1 : 0;
        DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->update(['treasure_free' => $free]);
        [$hzexchPopo, $hzachPopo] = self::popo($uid, $version);
        $after = self::member($uid);
        $return['status'] = 1;
        $return['now_site'] = $nowSite;
        $return['double'] = $double;
        $return['free'] = $free;
        $return['sub_gold'] = self::SUB_GOLD;
        $return['refresh_list'] = $lastRewardList;
        $return['all_gold'] = $after['gold'];
        $return['all_silver'] = $after['silver'];
        $return['hzexch_popo'] = $hzexchPopo;
        $return['hzach_popo'] = $hzachPopo;
        return $return;
    }

    /**
     * 踏青寻宝-寻宝100次: c=act_treasure&m=treasure_two（type 1）
     *
     * -5 等级不足；-4 活动已经结束；-3 传递参数错误；-2 奖励表错误；-1 活动未开启；0 失败；1 成功；2 寻宝步伐太快了；3 元宝不足
     */
    public static function treasure_two(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $lockKey = self::TREASURE_LOCK . $uid;
        if ($uid <= 0 || !FestLock::acquire($lockKey)) {
            return ['status' => 2, 'vip' => self::VIP, 'msg' => self::LANG['treasure_fast_error']];
        }
        $release = true;
        try {
            $type = (int)$ctx->input('type', 0);
            if ($type <= 0) {
                // 照旧版：解的是集字成就锁，寻宝锁留到过期
                $release = false;
                return ['status' => -3, 'vip' => self::VIP, 'msg' => self::LANG['treasure_param_error']];
            }
            // 寻宝次数（只有 type 1：100 次）
            $tnum = $type === 1 ? 100 : 0;
            $act = self::init($now);
            if ($act === null) {
                return ['status' => -1, 'vip' => self::VIP, 'msg' => self::LANG['treasure_not_open']];
            }
            if (self::member($uid)['level'] < self::OPEN_LEVEL) {
                $release = false;
                return ['status' => -5, 'vip' => self::VIP, 'msg' => self::LANG['treasure_not_level']];
            }
            if ($act['reward_cd'] <= 0) {
                return ['status' => -4, 'vip' => self::VIP, 'msg' => self::LANG['treasure_end_error']];
            }
            if (ActTreasureReward::ROWS === []) {
                return ['status' => -2, 'vip' => self::VIP, 'msg' => self::LANG['treasure_reward_error']];
            }
            return Member::locked($uid, static fn (): array => self::doTreasureTwo($uid, $act['version'], $tnum, $now));
        } finally {
            if ($release) {
                FestLock::release($lockKey);
            }
        }
    }

    /**
     * 百次寻宝（在玩家行锁事务里执行）
     *
     * @return array<string, mixed>
     */
    private static function doTreasureTwo(int $uid, int $version, int $tnum, int $now): array
    {
        $member = self::member($uid);
        $site = 0;
        $reward = self::treasureRow($uid, $version);
        if ($reward !== null) {
            $site = $reward['site'];
        } elseif (self::refreshReward($uid, $version, $member, $now) === null) {
            return ['status' => -2, 'vip' => self::VIP, 'msg' => self::LANG['treasure_reward_error']];
        }
        $subGold = self::subGold100();
        if ($member['gold'] < $subGold || !Member::deductCurrency($uid, gold: $subGold)) {
            return ['status' => 3, 'vip' => self::VIP, 'msg' => self::LANG['treasure_gold_error']];
        }
        [$markArr, $markTotalRate] = self::markArr($member['vip']);
        // 奖励信息（抽不到时沿用上一次的奖励，同旧版）
        $rewardArr = [];
        $rewardInfo = null;
        for ($n = 1; $n <= $tnum; $n++) {
            if ($markArr !== []) {
                $rewardInfo = self::randArr($markArr, $markTotalRate, 1, 'site_rate', $member['vip'])[0] ?? null;
            }
            if ($rewardInfo === null) {
                continue;
            }
            $type = $rewardInfo['type'];
            $num = (int)$rewardInfo['num'];
            $key = $type . '-' . $num;
            if ($type === 19) {
                // 处理汉字奖励
                if (isset($rewardArr[$key])) {
                    $rewardArr[$key]['num'] += 1;
                } else {
                    $rewardArr[$key] = ['type' => 19, 'hid' => $num, 'num' => 1];
                }
            } elseif (in_array($type, [8, 11, 13], true)) {
                // 处理装备/坐骑/武将奖励
                if (isset($rewardArr[$key])) {
                    $rewardArr[$key]['num'] += 1;
                } else {
                    $rewardArr[$key] = ['type' => $type, 'info' => self::rewardItem($type, $num)['info'] ?? null, 'num' => 1, 'id' => $num];
                }
            } elseif ($type >= 21 && $type <= 29) {
                // 处理精华奖励
                if (isset($rewardArr[$key])) {
                    $rewardArr[$key]['num'] += $num;
                } else {
                    $rewardArr[$key] = ['type' => $type, 'info' => self::rewardItem($type, $num)['info'] ?? null, 'num' => $num];
                }
            } elseif (isset($rewardArr[$type])) {
                $rewardArr[$type]['num'] += $num;
            } else {
                $rewardArr[$type] = ['type' => $type, 'num' => $num];
            }
        }
        foreach ($rewardArr as $value) {
            if ($value['type'] === 19) {
                self::doHanziReward($uid, $version, $value, $value['hid'], 0, $site, $value['num'], $now);
            } else {
                self::getReward($uid, $version, $value, 0, $site, 'treasure_two', $now);
            }
        }
        // 排序奖励信息
        sort($rewardArr);
        // 更新玩家寻宝次数
        DB::table('act_treasure')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')->update([
            'dateline' => $now,
            'num' => ($reward['num'] ?? 0) + $tnum,
            'vip' => $member['vip'],
        ]);
        [$hzexchPopo, $hzachPopo] = self::popo($uid, $version);
        $after = self::member($uid);
        return [
            'status' => 1,
            'vip' => self::VIP,
            'reward' => $rewardArr,
            'all_gold' => $after['gold'],
            'all_silver' => $after['silver'],
            'now_site' => $site,
            'hzexch_popo' => $hzexchPopo,
            'hzach_popo' => $hzachPopo,
        ];
    }

    /**
     * 踏青寻宝-今天奖励: c=act_treasure&m=today_reward
     *
     * -2 等级不足；-1 功能未开启；0 失败；1 成功
     */
    public static function today_reward(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $act = self::init($now);
        if ($act === null || $uid <= 0) {
            return ['status' => -1, 'vip' => self::VIP, 'msg' => self::LANG['treasure_not_open']];
        }
        if (self::member($uid)['level'] < self::OPEN_LEVEL) {
            return ['status' => -2, 'vip' => self::VIP, 'msg' => self::LANG['treasure_not_level']];
        }
        // 查看今天的奖励信息（type 降序）
        $date = (int)strtotime(date('Y-m-d', $now));
        $rows = DB::table('act_treasure_log')->select('type', 'num', 'max_num')->where('uid', $uid)->where('version', $act['version'])
            ->where('dateline', '>=', $date)->whereNull('deleted_at')->orderBy('type', 'DESC')->orderBy('id', 'ASC')->get();
        $list = [];
        foreach ($rows as $key => $value) {
            $type = (int)$value['type'];
            $num = (int)$value['num'];
            $maxNum = (int)$value['max_num'];
            if (in_array($type, [20, 0, 18], true)) {
                continue;
            }
            if (in_array($type, [8, 11, 13], true)) {
                $list[$type . '-' . $key . '-' . $maxNum] = ['type' => $type, 'num' => $maxNum];
            } elseif ($type === 19) {
                if ($num > 0) {
                    $k = '19' . $maxNum;
                    $list[$k] = ['type' => $type, 'num' => ($list[$k]['num'] ?? 0) + $num, 'id' => $maxNum];
                }
            } else {
                $list[$type] = ['type' => $type, 'num' => ($list[$type]['num'] ?? 0) + $num];
            }
        }
        // 特殊处理
        $hanArr = $lastList = [];
        foreach ($list as $val) {
            $tmp = ['type' => $val['type'], 'num' => $val['num']];
            if (isset($val['id'])) {
                $tmp['id'] = $val['id'];
            }
            switch ($val['type']) {
                case 8: // 装备
                    $tmp['equip'] = self::rewardItem(8, $val['num'])['info'] ?? null;
                    break;
                case 11: // 坐骑
                    $tmp['mount'] = self::rewardItem(11, $val['num'])['info'] ?? null;
                    break;
                case 13: // 武将
                    $tmp['general'] = self::rewardItem(13, $val['num'])['info'] ?? null;
                    break;
                case 21: case 22: case 23: case 24: case 25: case 26: case 27: case 28: case 29: // 装备精华
                    $essId = self::ESSENCE_ID[$val['type']];
                    $tmp['info'] = ['id' => $essId];
                    $ess = EquipEssence::ROWS[$essId] ?? null;
                    if ($ess !== null) {
                        $tmp['info']['value'] = $val['num'];
                        $tmp['info']['quality'] = $ess['quality'];
                    }
                    break;
            }
            // 排序 sort_type
            $tmp['sort_type'] = $tmp['type'];
            if ($tmp['type'] === 19) {
                $tmp['sort_type'] = $tmp['id'] + 100;
                $hanArr[] = $tmp;
                continue;
            }
            if ($tmp['type'] === 8) {
                $tmp['sort_type'] = $val['num'];
            }
            $lastList[] = $tmp;
        }
        // 汉字升序、其他奖励降序（_sort_array：array_multisort，排序键相同时按元素升序）
        self::sortArray($hanArr, 'sort_type', 'asc');
        self::sortArray($lastList, 'sort_type', 'desc');
        return ['status' => 1, 'vip' => self::VIP, 'list' => array_merge($hanArr, $lastList)];
    }

    /**
     * 踏青寻宝-神秘大奖-汉字兑换首页: c=act_treasure&m=hanzi_index
     *
     * -1 活动未开启；-2 获取汉字兑换奖励信息失败；-3 等级不足；0 失败；1 成功
     */
    public static function hanzi_index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $act = self::init($now);
        if ($act === null || $uid <= 0) {
            return ['status' => -1, 'vip' => self::VIP, 'msg' => self::LANG['treasure_not_open']];
        }
        if (self::member($uid)['level'] < self::OPEN_LEVEL) {
            return ['status' => -3, 'vip' => self::VIP, 'msg' => self::LANG['treasure_not_level']];
        }
        $return = self::getHanziInfo($uid, $act['version']);
        if ($return['status'] === -2) {
            return ['status' => -2, 'msg' => self::LANG['treasure_hanzi_error']];
        }
        $return['status'] = 1;
        unset($return['base_hanzi']);
        return $return;
    }

    /**
     * 踏青寻宝-神秘大奖-汉字兑换: c=act_treasure&m=hanzi_exchange（hid 兑换奖励 id）
     *
     * -1 活动未开启；-2 缺少参数；-3 获取汉字兑换奖励信息失败；-4 等级不足；0 失败；1 成功；2 操作太频繁；3 汉字数量不满足兑换条件；
     * 4 汉字兑换失败
     */
    public static function hanzi_exchange(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $lockKey = self::EXCHANGE_LOCK . $uid;
        if ($uid <= 0 || !FestLock::acquire($lockKey)) {
            return ['status' => 2, 'vip' => self::VIP];
        }
        $act = self::init($now);
        if ($act === null) {
            // 照旧版：不解锁
            return ['status' => -1, 'vip' => self::VIP, 'msg' => self::LANG['treasure_not_open']];
        }
        if (self::member($uid)['level'] < self::OPEN_LEVEL) {
            return ['status' => -4, 'vip' => self::VIP, 'msg' => self::LANG['treasure_not_level']];
        }
        try {
            $hid = (int)$ctx->input('hid', 0);
            if ($hid <= 0 || $hid === 8) {
                return ['status' => -2, 'vip' => self::VIP, 'msg' => self::LANG['treasure_param_error']];
            }
            return Member::locked($uid, static fn (): array => self::doExchange($uid, $act['version'], $hid, $now));
        } finally {
            FestLock::release($lockKey);
        }
    }

    /**
     * 汉字兑换（在玩家行锁事务里执行）
     *
     * @return array<string, mixed>
     */
    private static function doExchange(int $uid, int $version, int $hid, int $now): array
    {
        $hanziInfo = self::getHanziInfo($uid, $version);
        if ($hanziInfo['status'] !== 1) {
            return ['status' => -3, 'vip' => self::VIP, 'msg' => self::LANG['treasure_hanzi_error']];
        }
        // 是否可以兑换
        $isExch = 0;
        foreach ($hanziInfo['list'] as $value) {
            if ($value['id'] === $hid) {
                $isExch = $value['open'] > 0 ? 1 : 0;
                break;
            }
        }
        if ($isExch === 0) {
            return ['status' => 3, 'vip' => self::VIP, 'msg' => self::LANG['treasure_not_exchage_error']];
        }
        $rewardInfo = $hanziInfo['base_hanzi'][$hid] ?? null;
        if ($rewardInfo === null) {
            return ['status' => -3, 'vip' => self::VIP, 'msg' => self::LANG['treasure_hanzi_error']];
        }
        // 奖励信息处理（get_reward($reward_info, 1, 1)：依次发 type1 ~ type3，遇到空的停止；id 不传，装备 / 坐骑 / 武将发不出）
        for ($i = 1; $i <= 3; $i++) {
            if ($rewardInfo['type' . $i] <= 0 || $rewardInfo['value' . $i] <= 0) {
                break;
            }
            self::addReward($uid, ['type' => $rewardInfo['type' . $i], 'num' => $rewardInfo['value' . $i]], 0, 'hanzi_exchange', $now);
        }
        // 兑换奖励需要的汉字信息，目前拥有的汉字数量信息
        $idarr = self::HANZI_ARR[$hid];
        $parts = [];
        foreach ($hanziInfo['hanzi_list'] as $hval) {
            $num = $hval['num'];
            if (in_array((string)$hval['id'], $idarr, true)) {
                $num -= 1;
                if ($num <= 0) {
                    continue;
                }
            }
            $parts[] = $hval['id'] . ',' . $num;
        }
        // 更新玩家汉字数量（旧版拼好的字符串去掉最后一个字符）
        DB::table('act_treasure_hanzi')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')
            ->update(['type_str' => implode(';', $parts), 'dateline' => $now]);
        // 记录玩家兑换日志
        DB::table('act_treasure_exchange')->insert(['uid' => $uid, 'version' => $version, 'hid' => $hid, 'dateline' => $now]);
        // 获取新的汉字数据信息，更新兑换提示状态
        $newHanziList = self::getHanziInfo($uid, $version);
        $hzexchPopo = 0;
        if ($newHanziList['hzexch_open'] === 0) {
            self::updateTreasure($uid, $version, ['hzexch_reward' => 0]);
        } else {
            $hzexchPopo = 1;
        }
        $after = self::member($uid);
        return [
            'status' => 1,
            'vip' => self::VIP,
            'all_gold' => $after['gold'],
            'all_silver' => $after['silver'],
            'hanzi_list' => $newHanziList['hanzi_list'],
            'list' => $newHanziList['list'],
            'hzexch_popo' => $hzexchPopo,
        ];
    }

    /**
     * 寻宝排行榜: c=act_treasure&m=treasure_rank
     *
     * -1 活动未开启；-2 寻宝排行奖励表错误；-3 等级不足；0 失败；1 成功。is_get：0 不能领，1 可领，2 已领
     */
    public static function treasure_rank(Context $ctx): array
    {
        $return = ['status' => 0, 'vip' => self::VIP];
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $act = self::init($now);
        if ($act === null || $uid <= 0) {
            return ['status' => -1, 'vip' => self::VIP, 'msg' => self::LANG['treasure_not_open']];
        }
        if (self::member($uid)['level'] < self::OPEN_LEVEL) {
            return ['status' => -3, 'vip' => self::VIP, 'msg' => self::LANG['treasure_not_level']];
        }
        $baseRank = ActTreasureRank::ROWS;
        if ($baseRank === []) {
            return ['status' => -2, 'vip' => self::VIP, 'msg' => self::LANG['treasure_not_base_error']];
        }
        $nowRank = $isGet = 0;
        $rankInfo = $list = [];
        foreach (self::rankList($act['version']) as $key => $value) {
            $key += 1;
            if ($value['uid'] === $uid) {
                $nowRank = $key;
                $rankInfo = $value;
            }
            $list[$key] = ['tank' => $key, 'nickname' => $value['nickname'], 'num' => $value['num'], 'uid' => $value['uid']];
        }
        // 处理排行基础奖励信息
        $baseRankList = [];
        foreach ($baseRank as $rank => $rval) {
            if (!isset($list[$rank])) {
                continue;
            }
            $rankArr = [
                'tank' => $rank,
                'nickname' => $list[$rank]['nickname'],
                'num' => $list[$rank]['num'],
                'uid' => $list[$rank]['uid'],
                'type' => $rval['type'],
                'value' => $rval['value'],
            ];
            // 特殊处理（照旧版：精华的数量取的是还没有赋值的当前排名奖励，为 null；22 ~ 29 的 id 写到了返回值的 reward 里）
            switch ($rval['type']) {
                case 8: // 装备
                case 10: // 坐骑（旧版按坐骑查，类型 10 实际是突飞卡）
                case 13: // 武将
                    $rankArr['info'] = self::rewardItem($rval['type'] === 10 ? 11 : $rval['type'], $rval['value'])['info'] ?? null;
                    break;
                case 21: case 22: case 23: case 24: case 25: case 26: case 27: case 28: case 29:
                    if ($rval['type'] === 21) {
                        $rankArr['info']['id'] = 1;
                    } else {
                        $return['reward']['id'] = self::ESSENCE_ID[$rval['type']];
                    }
                    $ess = EquipEssence::ROWS[self::ESSENCE_ID[$rval['type']]] ?? null;
                    if ($ess !== null) {
                        $rankArr['info']['value'] = null;
                        $rankArr['info']['quality'] = $ess['quality'];
                    }
                    break;
            }
            $baseRankList[] = $rankArr;
        }
        // 排名奖励
        $rankReward = $nowRank > 0 ? ($baseRank[$nowRank] ?? null) : [];
        // 领取排行奖励CD时间、领取状态
        if ($act['reward_cd'] <= 0 && $rankInfo !== []) {
            $isGet = $rankInfo['rank_reward'] > 0 ? 2 : 1;
        }
        $return['status'] = 1;
        $return['list'] = $baseRankList;
        $return['rank'] = $nowRank;
        $return['rank_reward'] = $rankReward;
        $return['reward_cd'] = $act['reward_cd'];
        $return['is_get'] = $isGet;
        return $return;
    }

    /**
     * 寻宝排行榜领取奖励: c=act_treasure&m=treasure_rank_reward
     *
     * -1 活动未开启；-2 寻宝排行奖励表错误；-4 等级不足；0 失败；1 成功；2 领取时间未到；3 不在排名中；4 已经领取；
     * 5 操作不要太频繁 / 更新失败
     */
    public static function treasure_rank_reward(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $lockKey = self::RANK_LOCK . $uid;
        if ($uid <= 0 || !FestLock::acquire($lockKey)) {
            return ['status' => 5, 'vip' => self::VIP, 'msg' => self::LANG['treasure_op_fast_error']];
        }
        $release = true;
        try {
            $act = self::init($now);
            if ($act === null) {
                return ['status' => -1, 'vip' => self::VIP, 'msg' => self::LANG['treasure_not_open']];
            }
            if (self::member($uid)['level'] < self::OPEN_LEVEL) {
                $release = false;
                return ['status' => -4, 'vip' => self::VIP, 'msg' => self::LANG['treasure_not_level']];
            }
            $baseRank = ActTreasureRank::ROWS;
            if ($baseRank === []) {
                return ['status' => -2, 'vip' => self::VIP, 'msg' => self::LANG['treasure_not_base_error']];
            }
            if ($act['reward_cd'] > 0) {
                return ['status' => 2, 'vip' => self::VIP, 'msg' => self::LANG['treasure_rank_get_error']];
            }
            $nowRank = 0;
            $rankInfo = [];
            foreach (self::rankList($act['version']) as $key => $value) {
                if ($value['uid'] === $uid) {
                    $nowRank = $key + 1;
                    $rankInfo = $value;
                }
            }
            if ($nowRank <= 0) {
                return ['status' => 3, 'vip' => self::VIP, 'msg' => self::LANG['treasure_not_rank_error']];
            }
            if ($rankInfo['rank_reward'] > 0) {
                return ['status' => 4, 'vip' => self::VIP, 'msg' => self::LANG['treasure_rank_gone_error']];
            }
            $rankReward = $baseRank[$nowRank] ?? null;
            if ($rankReward === null) {
                return ['status' => -2, 'vip' => self::VIP, 'msg' => self::LANG['treasure_not_base_error']];
            }
            // 更新玩家领取奖励状态（条件更新）
            $updated = DB::table('act_treasure')->where('uid', $uid)->where('version', $act['version'])->where('rank_reward', 0)
                ->whereNull('deleted_at')->update(['rank_reward' => 1]);
            if ($updated <= 0) {
                return ['status' => 5, 'vip' => self::VIP, 'msg' => self::LANG['treasure_rank_update_error']];
            }
            $reward = ['type' => $rankReward['type'], 'num' => $rankReward['value']];
            if (in_array($reward['type'], [8, 11, 13], true)) {
                $reward['id'] = $rankReward['value'];
            }
            self::addReward($uid, $reward, 0, 'treasure_rank_reward', $now);
            return [
                'status' => 1,
                'vip' => self::VIP,
                'reward' => $rankReward,
                'all_gold' => self::member($uid)['gold'],
            ];
        } finally {
            if ($release) {
                FestLock::release($lockKey);
            }
        }
    }

    /**
     * 集字成就: c=act_treasure&m=hanzi_achieve
     *
     * -1 活动未开启；-2 集字成就基础表信息错误；-3 等级不足；0 失败；1 成功。open：2 已领取，1 可领取，0 不能领取
     */
    public static function hanzi_achieve(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $act = self::init($now);
        if ($act === null || $uid <= 0) {
            return ['status' => -1, 'vip' => self::VIP, 'msg' => self::LANG['treasure_not_open']];
        }
        if (self::member($uid)['level'] < self::OPEN_LEVEL) {
            return ['status' => -3, 'vip' => self::VIP, 'msg' => self::LANG['treasure_not_level']];
        }
        if (ActTreasureHanziach::ROWS === []) {
            return ['status' => -2, 'vip' => self::VIP, 'msg' => self::LANG['treasure_not_hanziachbase_error']];
        }
        $num = self::hanziNum($uid, $act['version']);
        $aidArr = self::hanziachAids($uid, $act['version']);
        $list = $numList = [];
        foreach (ActTreasureHanziach::ROWS as $hanziach) {
            $tmp = ['id' => $hanziach['id'], 'desc' => $hanziach['desc'], 'open' => 0];
            if (in_array($hanziach['id'], $aidArr, true)) {
                $tmp['open'] = 2;
            } elseif ($num >= $hanziach['num']) {
                $tmp['open'] = 1;
            }
            $numList[] = $hanziach['num'];
            for ($i = 1; $i <= 3; $i++) {
                $type = $hanziach['type' . $i];
                $value = $hanziach['value' . $i];
                $tmp['type' . $i] = $type;
                $tmp['value' . $i] = $value;
                if (in_array($type, [8, 11, 13], true)) {
                    $tmp['info' . $i] = self::rewardItem($type, $value)['info'] ?? null;
                } elseif ($type >= 21 && $type <= 29) {
                    $essId = self::ESSENCE_ID[$type];
                    $tmp['info' . $i] = ['id' => $essId];
                    $ess = EquipEssence::ROWS[$essId] ?? null;
                    if ($ess !== null) {
                        $tmp['info' . $i]['value'] = $value;
                        $tmp['info' . $i]['quality'] = $ess['quality'];
                    }
                }
            }
            $list[] = $tmp;
        }
        return ['status' => 1, 'vip' => self::VIP, 'list' => $list, 'num_list' => $numList, 'num' => $num];
    }

    /**
     * 集字成就领取奖励: c=act_treasure&m=hanzi_achieve_reward（aid 成就 id）
     *
     * -1 活动未开启；-2 集字成就基础表信息错误；-3 传递参数错误；-4 等级不足；0 失败；1 成功；2 未达到成就；3 已经领取；
     * 4 添加集字成就奖励日志失败；5 操作不要太频繁
     */
    public static function hanzi_achieve_reward(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $lockKey = self::HANZIACH_LOCK . $uid;
        if ($uid <= 0 || !FestLock::acquire($lockKey)) {
            return ['status' => 5, 'vip' => self::VIP, 'msg' => self::LANG['treasure_op_fast_error']];
        }
        $release = true;
        try {
            $act = self::init($now);
            if ($act === null) {
                return ['status' => -1, 'vip' => self::VIP, 'msg' => self::LANG['treasure_not_open']];
            }
            if (self::member($uid)['level'] < self::OPEN_LEVEL) {
                $release = false;
                return ['status' => -4, 'vip' => self::VIP, 'msg' => self::LANG['treasure_not_level']];
            }
            $aid = (int)$ctx->input('aid', 0);
            if ($aid <= 0) {
                return ['status' => -3, 'vip' => self::VIP, 'msg' => self::LANG['treasure_param_error']];
            }
            $base = ActTreasureHanziach::ROWS[$aid] ?? null;
            if ($base === null) {
                return ['status' => -2, 'vip' => self::VIP, 'msg' => self::LANG['treasure_not_hanziachbase_error']];
            }
            if (self::hanziNum($uid, $act['version']) < $base['num']) {
                return ['status' => 2, 'vip' => self::VIP, 'msg' => self::LANG['treasure_hanziach_none_error']];
            }
            if (in_array($aid, self::hanziachAids($uid, $act['version']), true)) {
                return ['status' => 3, 'vip' => self::VIP, 'msg' => self::LANG['treasure_hanziach_rewarded_error']];
            }
            // 添加领取奖励日志（uid + version + aid 唯一）
            $added = DB::execute(
                'INSERT IGNORE INTO `zc_sanguo_act_treasure_hanziach_log` (`uid`, `version`, `aid`, `dateline`) VALUES (:uid, :version, :aid, :now)',
                ['uid' => $uid, 'version' => $act['version'], 'aid' => $aid, 'now' => $now]
            );
            if ($added <= 0) {
                return ['status' => 4, 'vip' => self::VIP, 'msg' => self::LANG['treasure_hanziach_addfield_error']];
            }
            // 发放奖励（$reward 跨奖励复用，同旧版）
            $reward = [];
            for ($i = 1; $i <= 3; $i++) {
                $reward['type'] = $base['type' . $i];
                $reward['num'] = $base['value' . $i];
                if (in_array($reward['type'], [8, 11, 13], true)) {
                    $reward['id'] = $base['value' . $i];
                }
                self::addReward($uid, $reward, 0, 'hanzi_achieve_reward', $now);
            }
            $hzachPopo = self::hzachPopo($uid, $act['version']);
            $after = self::member($uid);
            return [
                'status' => 1,
                'vip' => self::VIP,
                'all_gold' => $after['gold'],
                'all_silver' => $after['silver'],
                'hzach_popo' => $hzachPopo,
            ];
        } finally {
            if ($release) {
                FestLock::release($lockKey);
            }
        }
    }

    /**
     * 主城（member.php index 的寻宝活动分支）：活动开启时同步排行用的 VIP（记录的 VIP 低于 2 且玩家已到 VIP 2），返回
     * acttreasure_popo（集字兑换或集字成就有奖励可领时为 1）
     */
    public static function memberIndex(int $uid, int $version, int $vip): int
    {
        $info = self::treasureRow($uid, $version);
        if ($info === null) {
            return 0;
        }
        // 更新玩家VIP信息
        if ($info['vip'] < 2 && $vip >= 2) {
            self::updateTreasure($uid, $version, ['vip' => $vip]);
        }
        return $info['hzexch_reward'] > 0 || $info['hzach_reward'] > 0 ? 1 : 0;
    }

    /**
     * 计划任务：寻宝系统（cron_acttask.php:treasure&type=1，旧版注释「每天零点后重置」）
     *
     * 活动开始 5 小时内整个任务不执行。每日清理汉字（参与奖），活动结束后自动领取寻宝排行与集字成就奖励。
     *
     * @return array<string, int>
     */
    public static function cronTreasure(int $now): array
    {
        $activity = ActivityClock::byName(self::ACTIVITY);
        if ($now - (int)($activity['starttime'] ?? 0) < 18000) {
            return [];
        }
        return [
            'push_treasure_reward' => self::cronHanziReward($now),
            'get_treasure_num_reward' => self::cronRankReward($now),
            'get_treasure_hzachieve_reward' => self::cronAchieveReward($now),
        ];
    }

    /**
     * 每日清理用户的汉字收集情况（push_treasure_reward，type 1）：活动期间，每个玩家剩余的每个汉字兑换 30 元宝（参与奖）、
     * 集齐 8 字的次数各记一条兑换日志（hid 1），清空汉字并发信件
     *
     * @return int -1 无活动；-2 活动已结束；-3 活动未开始；1 已执行
     */
    public static function cronHanziReward(int $now): int
    {
        $activity = ActivityClock::byName(self::ACTIVITY);
        if ($activity === null) {
            return -1;
        }
        if ((int)$activity['endtime'] < $now) {
            return -2;
        }
        if ((int)$activity['starttime'] > $now) {
            return -3;
        }
        $version = (int)$activity['version'];
        $rows = DB::table('act_treasure_hanzi')->select('uid', 'type_str')->where('version', $version)->where('type_str', '!=', '')
            ->whereNull('deleted_at')->get();
        foreach ($rows as $val) {
            $uid = (int)$val['uid'];
            $typeStr = (string)$val['type_str'];
            $info = explode(';', $typeStr);
            // 集齐数量：8 个字都有时取最少的
            $hznumArr = [];
            if (count($info) >= 8) {
                foreach ($info as $value) {
                    $hz = explode(',', $value);
                    if (isset($hz[0], $hz[1]) && (int)$hz[1] > 0) {
                        $hznumArr[$hz[0]] = (int)$hz[1];
                    }
                }
                sort($hznumArr);
            }
            // 计算汉字数量
            $count = 0;
            foreach ($info as $small) {
                $count += (int)(explode(',', $small)[1] ?? 0);
            }
            $addGold = $count * 30;
            // 修改用户的汉字信息（条件清空，成功才发）
            $cleared = DB::table('act_treasure_hanzi')->where('uid', $uid)->where('version', $version)->where('type_str', $typeStr)
                ->whereNull('deleted_at')->update(['type_str' => '', 'dateline' => $now]);
            if ($cleared <= 0) {
                continue;
            }
            for ($i = 0; $i < ($hznumArr[0] ?? 0); $i++) {
                // 添加兑换日志
                DB::table('act_treasure_exchange')->insert(['uid' => $uid, 'version' => $version, 'hid' => 1, 'dateline' => $now]);
            }
            if ($addGold <= 0) {
                continue;
            }
            // 更新汉字兑换状态
            self::updateTreasure($uid, $version, ['hzexch_reward' => 0]);
            Member::addCurrency($uid, gold: $addGold);
            $message = self::LANG_CRON['cron_greet'] . $count . self::LANG_CRON['cron_word'] . $addGold . self::LANG_CRON['cron_please'];
            MessageHandler::sendSystemMessage([$uid], 1, self::LANG_CRON['message_system_title'], $message);
        }
        return 1;
    }

    /**
     * 自动发放寻宝次数排行奖励（get_treasure_num_reward：活动结束后 10 小时内，前 30 名中未领取的发奖并发信件）
     *
     * @return int -1 无活动；-2 活动未结束；-3 超过 10 小时；1 已执行
     */
    public static function cronRankReward(int $now): int
    {
        $version = self::cronWindow($now);
        if ($version < 0) {
            return $version;
        }
        foreach (self::rankList($version) as $i => $val) {
            $rank = $i + 1;
            $reward = ActTreasureRank::ROWS[$rank] ?? null;
            if ($val['rank_reward'] !== 0 || $reward === null) {
                continue;
            }
            $updated = DB::table('act_treasure')->where('uid', $val['uid'])->where('version', $version)->where('rank_reward', 0)
                ->whereNull('deleted_at')->update(['rank_reward' => 1]);
            if ($updated <= 0) {
                continue;
            }
            $message = self::LANG_CRON['cron_automatic_get'] . self::LANG_CRON['cron_get'] . $rank . self::LANG_CRON['cron_award']
                . self::cronReward($reward['type'], $reward['value'], $val['uid'], 'get_treasure_num_reward', $now) . self::LANG_CRON['cron_check'];
            MessageHandler::sendSystemMessage([$val['uid']], 1, self::LANG_CRON['message_system_title'], $message);
        }
        return 1;
    }

    /**
     * 自动发放寻宝集字的成就奖励（get_treasure_hzachieve_reward：活动结束后 10 小时内）
     *
     * 每个玩家的集齐次数取 8 个字各自累计获得数的最小值（照旧版：统计时某个玩家缺字后，之后遍历到的玩家都按 0 次）；达到而没有领取
     * 记录的成就写记录、同类奖励合并后发放，发信件。
     *
     * @return int -1 无活动；-2 活动未结束；-3 超过 10 小时；-4 成就表为空；1 已执行
     */
    public static function cronAchieveReward(int $now): int
    {
        $version = self::cronWindow($now);
        if ($version < 0) {
            return $version;
        }
        if (ActTreasureHanziach::ROWS === []) {
            return -4;
        }
        // 获取所有玩家汉字收集信息（get_hanzi_all_exlog($version, 1)）
        $rows = DB::table('act_treasure_log')->select('uid', 'num', 'max_num')->where('version', $version)->where('type', 19)
            ->whereNull('deleted_at')->orderBy('id')->get();
        $allHanziLog = [];
        if (count($rows) === 1) {
            $allHanziLog[(int)$rows[0]['uid']] = 0;
        } elseif ($rows !== []) {
            $sums = [];
            foreach ($rows as $value) {
                $sums[(int)$value['uid']][(int)$value['max_num']] = ($sums[(int)$value['uid']][(int)$value['max_num']] ?? 0) + (int)$value['num'];
            }
            $isNum = null;
            foreach ($sums as $uid => $hanziArr) {
                for ($i = 1; $i <= 8; $i++) {
                    if (!isset($hanziArr[$i]) || $hanziArr[$i] === 0) {
                        $isNum = 0;
                    }
                }
                sort($hanziArr);
                $allHanziLog[$uid] = $isNum === 0 ? 0 : $hanziArr[0];
            }
        }
        $reward = [];
        foreach ($allHanziLog as $uid => $num) {
            foreach (ActTreasureHanziach::ROWS as $ach) {
                if ($num < $ach['num']) {
                    continue;
                }
                $added = DB::execute(
                    'INSERT IGNORE INTO `zc_sanguo_act_treasure_hanziach_log` (`uid`, `version`, `aid`, `dateline`) VALUES (:uid, :version, :aid, :now)',
                    ['uid' => $uid, 'version' => $version, 'aid' => $ach['id'], 'now' => $now]
                );
                if ($added <= 0) {
                    continue;
                }
                for ($i = 1; $i <= 3; $i++) {
                    $reward[$uid][$ach['type' . $i]] = ($reward[$uid][$ach['type' . $i]] ?? 0) + $ach['value' . $i];
                }
            }
        }
        foreach ($reward as $uid => $val) {
            $message = self::LANG_CRON['cron_jizi'];
            foreach ($val as $type => $num) {
                $message .= self::cronReward($type, $num, $uid, 'get_treasure_hzachieve_reward', $now);
            }
            $message .= self::LANG_CRON['cron_check'];
            MessageHandler::sendSystemMessage([$uid], 1, self::LANG_CRON['message_system_title'], $message);
        }
        return 1;
    }

    /**
     * 活动信息（构造函数）：活动开启时返回版本、寻宝截止倒计时与日期文字；否则 null
     *
     * @return array{version: int, reward_cd: int, act_start: string, act_end: string}|null
     */
    private static function init(int $now): ?array
    {
        $info = ActivityClock::open(self::ACTIVITY, $now);
        if ($info === null) {
            return null;
        }
        $start = (int)$info['starttime'];
        // 活动结束后图标要多挂一天，所以活动会提前一天结束
        $end = (int)$info['endtime'] - 86401;
        $format = 'm' . self::LANG['treasure_month'] . 'd' . self::LANG['treasure_day'];
        return [
            'version' => (int)$info['version'],
            'reward_cd' => max(0, $end - $now),
            'act_start' => date($format, $start),
            'act_end' => date($format, $end),
        ];
    }

    /**
     * 计划任务的发放窗口：活动结束后 10 小时内返回版本，否则状态码
     */
    private static function cronWindow(int $now): int
    {
        $activity = ActivityClock::byName(self::ACTIVITY);
        if ($activity === null) {
            return -1;
        }
        if ((int)$activity['endtime'] > $now) {
            return -2;
        }
        if ((int)$activity['endtime'] + 36000 < $now) {
            return -3;
        }
        return (int)$activity['version'];
    }

    /** 百次寻宝扣除元宝（round(50 × 100 × 0.96)） */
    private static function subGold100(): int
    {
        return (int)round(self::SUB_GOLD * 100 * self::TREA_REBATE);
    }

    /**
     * @return array{level: int, vip: int, gold: int, silver: int, nickname: string}
     */
    private static function member(int $uid): array
    {
        $row = DB::table('member')->select('level', 'vip', 'gold', 'silver', 'nickname')->where('uid', $uid)->whereNull('deleted_at')->first() ?? [];
        return [
            'level' => (int)($row['level'] ?? 0),
            'vip' => (int)($row['vip'] ?? 0),
            'gold' => (int)($row['gold'] ?? 0),
            'silver' => (int)($row['silver'] ?? 0),
            'nickname' => (string)($row['nickname'] ?? ''),
        ];
    }

    /** 寻宝免费次数（member_field.treasure_free） */
    private static function treasureFree(int $uid): int
    {
        return (int)(DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->value('treasure_free') ?? 0);
    }

    /**
     * 玩家寻宝记录（get_refresh_reward）；没有为 null
     *
     * @return array<string, int|string>|null
     */
    private static function treasureRow(int $uid, int $version): ?array
    {
        $row = DB::table('act_treasure')->select('uid', 'nickname', 'vip', 'site', 'num', 'double', 'rank_reward', 'hzexch_reward', 'hzach_reward', 'reward_str', 'dateline')
            ->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')->first();
        if ($row === null) {
            return null;
        }
        foreach ($row as $key => $value) {
            $row[$key] = in_array($key, ['nickname', 'reward_str'], true) ? (string)$value : (int)$value;
        }
        return $row;
    }

    /**
     * @param array<string, int> $data
     */
    private static function updateTreasure(int $uid, int $version, array $data): int
    {
        return DB::table('act_treasure')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')->update($data);
    }

    /**
     * 奖励圈 JSON 解码（键 1 ~ 19）
     *
     * @return array<int, array<string, mixed>|null>
     */
    private static function decodeList(string $json): array
    {
        $list = json_decode($json, true);
        if (!is_array($list)) {
            return [];
        }
        $return = [];
        foreach ($list as $key => $value) {
            $return[(int)$key] = is_array($value) ? $value : null;
        }
        return $return;
    }

    /**
     * 写寻宝日志（add_reward_log：act_treasure_log）
     */
    private static function addRewardLog(int $uid, int $version, int $site, int $type, int $num, int $maxNum, int $now): void
    {
        DB::table('act_treasure_log')->insert([
            'uid' => $uid,
            'version' => $version,
            'site' => $site,
            'type' => $type,
            'num' => $num,
            'max_num' => $maxNum,
            'dateline' => $now,
        ]);
    }

    /**
     * 显示提示状态（act_treasure 的 hzexch_reward / hzach_reward 不为 0 时为 1）
     *
     * @return array{0: int, 1: int}
     */
    private static function popo(int $uid, int $version): array
    {
        $info = self::treasureRow($uid, $version);
        if ($info === null) {
            return [0, 0];
        }
        return [$info['hzexch_reward'] !== 0 ? 1 : 0, $info['hzach_reward'] !== 0 ? 1 : 0];
    }

    /**
     * 问号格 / 百次寻宝的基础奖励（基础表去掉问号格 18、双倍格 20，VIP 5 以下去掉装备），按 id 索引，与 site_rate 合计
     *
     * @return array{0: array<int, array<string, mixed>>, 1: float}
     */
    private static function markArr(int $vip): array
    {
        $markArr = [];
        $total = 0.0;
        foreach (self::baseReward() as $row) {
            if (in_array($row['type'], [18, 20], true) || ($vip < self::TVIP && $row['type'] === 8)) {
                continue;
            }
            $markArr[$row['id']] = $row;
            $total += $row['site_rate'];
        }
        return [$markArr, $total];
    }

    /**
     * 寻宝奖励基础表（get_reward_list：ORDER BY p_type DESC）
     *
     * @return list<array<string, mixed>>
     */
    private static function baseReward(): array
    {
        $rows = array_values(ActTreasureReward::ROWS);
        usort($rows, static fn (array $a, array $b): int => [$b['p_type'], $a['id']] <=> [$a['p_type'], $b['id']]);
        return $rows;
    }

    /**
     * 踏青寻宝-刷新奖励（refresh_reward）：生成 19 格奖励圈并写入 act_treasure（没有记录时建档）；奖励表为空为 null
     *
     * @param array{nickname: string} $member
     * @return array<int, array<string, mixed>|null>|null
     */
    private static function refreshReward(int $uid, int $version, array $member, int $now): ?array
    {
        $rewardList = self::baseReward();
        if ($rewardList === []) {
            return null;
        }
        $typeArr1 = $typeArr2 = $typeArr3 = $hanziArr = [];
        $typeArr4 = null;
        $rateTotal1 = $rateTotal2 = $rateTotal3 = $hanziTotal = 0.0;
        foreach ($rewardList as $reward) {
            switch ($reward['p_type']) {
                case 1:
                    if ($reward['type'] === 19) {
                        $hanziArr[] = $reward;
                        $hanziTotal += $reward['get_rate'];
                    } else {
                        $typeArr1[] = $reward;
                        $rateTotal1 += $reward['get_rate'];
                    }
                    break;
                case 2:
                    $typeArr2[] = $reward;
                    $rateTotal2 += $reward['get_rate'];
                    break;
                case 3:
                    $typeArr3[] = $reward;
                    $rateTotal3 += $reward['get_rate'];
                    break;
                case 4:
                    $typeArr4 = $reward;
                    break;
            }
        }
        // 根据刷新权重获取4个汉字（上半区域），下半区域4个汉字
        $hanzi1 = self::randArr($hanziArr, $hanziTotal, 4, 'get_rate', 0, 0);
        $hanzi1Arr = array_column($hanzi1, 'num');
        $hanzi2 = array_values(array_filter($hanziArr, static fn (array $v): bool => !in_array($v['num'], $hanzi1Arr, true)));
        // 价值类型1的奖品，必出2个（类型不重复）
        $randarr1 = self::randArr($typeArr1, $rateTotal1, 2, 'get_rate', 0, 1);
        array_push($hanzi1, $randarr1[0] ?? null, $randarr1[1] ?? null);
        $rand1 = array_column(array_filter($randarr1), 'type');
        $randarr2 = array_values(array_filter($typeArr1, static fn (array $v): bool => !in_array($v['type'], $rand1, true)));
        array_push($hanzi2, $randarr2[0] ?? null, $randarr2[1] ?? null);
        // 价值类型2的奖品，随机出2个；剩余的再取2个放入下半区域
        $refresh2 = self::randArr($typeArr2, $rateTotal2, 2, 'get_rate', 0, 1);
        array_push($hanzi1, $refresh2[0] ?? null, $refresh2[1] ?? null);
        $rand2 = array_column(array_filter($refresh2), 'id');
        $randarr3 = [];
        $downTotal = 0.0;
        foreach ($typeArr2 as $t2) {
            if (in_array($t2['id'], $rand2, true)) {
                continue;
            }
            $randarr3[] = $t2;
            $downTotal += $t2['get_rate'];
        }
        $down = self::randArr($randarr3, $downTotal, 2, 'get_rate', 0, 1);
        array_push($hanzi2, $down[0] ?? null, $down[1] ?? null);
        // 价值类型3的奖品，随机出2个：上下半区域各放1个
        $refresh3 = self::randArr($typeArr3, $rateTotal3, 2, 'get_rate', 0, 1);
        $hanzi1[] = $refresh3[0] ?? null;
        $hanzi2[] = $refresh3[1] ?? null;
        // 随机奖励并排序（汉字之间不能相连出现）；上半区域末尾加双倍格
        $hanzi1 = self::randSort($hanzi1);
        $hanzi1[] = $typeArr4;
        $hanzi2 = self::randSort($hanzi2);
        $list = [];
        foreach (array_merge($hanzi1, $hanzi2) as $key => $value) {
            $list[$key + 1] = $value;
        }
        $json = json_encode($list, JSON_UNESCAPED_UNICODE);
        // 插入数据库（已有记录时更新奖励圈）
        $affected = DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_act_treasure` (`uid`, `version`, `nickname`, `reward_str`, `dateline`) VALUES (:uid, :version, :nickname, :str, :now)',
            ['uid' => $uid, 'version' => $version, 'nickname' => Member::logText($member['nickname'], 50), 'str' => $json, 'now' => $now]
        );
        if ($affected <= 0) {
            self::updateTreasure($uid, $version, ['reward_str' => $json, 'dateline' => $now]);
        }
        return $list;
    }

    /**
     * 奖品随机计算（rand_arr）
     *
     * 按元素排序（同 sort，即按 id）后 rate × 10 划分区间 [k + 1, rate × 10 + k]（rate 为 site_rate 时 VIP 5 以下的装备没有区间），
     * 尝试「元素个数」次 mt_rand(1, 总权重 × 10)，落在某个区间（不含下界）就选中；汉字或 $isRe 为 1 时加入前按 id 去重；选够 $num 个为止。
     *
     * @param array<int, array<string, mixed>|null> $arr
     * @return list<array<string, mixed>>
     */
    private static function randArr(array $arr, float $totalNum, int $num, string $rate, int $vip, int $isRe = 0): array
    {
        $arr = array_values($arr);
        sort($arr);
        $k = 0;
        foreach ($arr as $key => $value) {
            if ($value === null || ($rate === 'site_rate' && ($value['type'] ?? 0) === 8 && $vip < self::TVIP)) {
                // 获得橙装或装备VIP限制（旧版 unset 引用变量，元素留着但没有区间）
                continue;
            }
            $width = (int)round((float)($value[$rate] ?? 0) * 10);
            $arr[$key]['rate_in'] = [$k + 1, $width + $k];
            $k += $width;
        }
        $return = [];
        $count = count($arr);
        $max = (int)round($totalNum * 10);
        for ($i = 1; $i <= $count; $i++) {
            $randNum = mt_rand(1, max(1, $max));
            foreach ($arr as $val) {
                if (!isset($val['rate_in']) || $randNum <= $val['rate_in'][0] || $randNum > $val['rate_in'][1]) {
                    continue;
                }
                // 去重
                if ($val['type'] === 19 || $isRe === 1) {
                    $return = self::assocUnique($return);
                }
                // 达到需求数量自动退出
                if (count($return) >= $num) {
                    break;
                }
                $return[] = $val;
            }
        }
        foreach ($return as $key => $val) {
            unset($return[$key]['rate_in']);
        }
        return $return;
    }

    /**
     * 二维数组按 id 去重（assoc_unique，去重后重新排序）
     *
     * @param list<array<string, mixed>> $arr
     * @return list<array<string, mixed>>
     */
    private static function assocUnique(array $arr): array
    {
        $tmp = [];
        foreach ($arr as $k => $v) {
            if (in_array($v['id'], $tmp, true)) {
                unset($arr[$k]);
            } else {
                $tmp[] = $v['id'];
            }
        }
        sort($arr);
        return $arr;
    }

    /**
     * 数组排序（rand_sort：两个汉字不能相连；奖励与汉字各自打乱后交错排列，起始奇偶随机）
     *
     * @param list<array<string, mixed>|null> $randarr
     * @return list<array<string, mixed>|null>
     */
    private static function randSort(array $randarr): array
    {
        $rArr = $hanziArr = [];
        foreach ($randarr as $reward) {
            if (($reward['type'] ?? 0) !== 19) {
                $rArr[] = $reward;
            } else {
                $hanziArr[] = $reward;
            }
        }
        if (mt_rand(1, 2) === 1) {
            $i = 1;
            $k = 0;
        } else {
            $i = 0;
            $k = 1;
        }
        shuffle($rArr);
        shuffle($hanziArr);
        $newArr = [];
        foreach ($rArr as $arr) {
            $newArr[$i] = $arr;
            $i += 2;
        }
        foreach ($hanziArr as $hanzi) {
            $newArr[$k] = $hanzi;
            $k += 2;
        }
        ksort($newArr);
        return array_values($newArr);
    }

    /**
     * 寻宝刷新奖励列表处理（get_refresh_list：每格的展示信息；已经走过的格子清空）
     *
     * @param array<int, array<string, mixed>|null> $refreshList
     * @param list<int> $siteArr
     * @return array<int, array<string, mixed>>
     */
    private static function getRefreshList(array $refreshList, array $siteArr = []): array
    {
        $return = [];
        foreach ($refreshList as $key => $value) {
            $tmp = ['num' => $value['num'] ?? null];
            if (isset($value['type'])) {
                $tmp['type'] = $value['type'];
                switch ($value['type']) {
                    case 8: // 装备
                        $tmp['equip'] = self::rewardItem(8, (int)$value['num'])['info'] ?? null;
                        break;
                    case 11: // 坐骑
                        $tmp['mount'] = self::rewardItem(11, (int)$value['num'])['info'] ?? null;
                        break;
                    case 13: // 武将
                        $tmp['general'] = self::rewardItem(13, (int)$value['num'])['info'] ?? null;
                        break;
                    case 19: // 汉字
                        $tmp['id'] = $value['num'];
                        break;
                    case 21: case 22: case 23: case 24: case 25: case 26: case 27: case 28: case 29: // 装备精华
                        $essId = self::ESSENCE_ID[$value['type']];
                        $tmp['info'] = ['id' => $essId];
                        $ess = EquipEssence::ROWS[$essId] ?? null;
                        if ($ess !== null) {
                            $tmp['info']['num'] = $value['num'];
                            $tmp['info']['quality'] = $ess['quality'];
                        }
                        break;
                }
            } else {
                $tmp['type'] = 0;
            }
            if (isset($value['name'])) {
                $tmp['name'] = '×' . $tmp['num'];
                if (in_array($tmp['type'], [18, 20], true)) {
                    $tmp['name'] = self::TYPE_NAME[$tmp['type']];
                } elseif ($tmp['type'] === 19) {
                    $tmp['name'] = '×1';
                }
            } else {
                $tmp['name'] = '';
            }
            $tmp['p_type'] = $value['p_type'] ?? 0;
            // 过滤已经获得的奖励位置
            if ($siteArr !== [] && in_array($key, $siteArr, true)) {
                $tmp['type'] = 0;
                $tmp['p_type'] = 0;
                $tmp['name'] = '';
                $tmp['num'] = 0;
            }
            $return[$key] = $tmp;
        }
        return $return;
    }

    /**
     * 寻宝奖励处理（get_reward，$hanzi 为 0：记寻宝日志后发奖；双倍时数量翻倍）
     *
     * @param array<string, mixed> $reward type、num，装备 / 坐骑 / 武将另含 id
     * @return array{info: array<string, mixed>}
     */
    private static function getReward(int $uid, int $version, array $reward, int $doubleM, int $nowSite, string $method, int $now): array
    {
        $num = $doubleM === 1 ? (int)$reward['num'] * 2 : (int)$reward['num'];
        self::addRewardLog($uid, $version, $nowSite, (int)$reward['type'], $num, (int)($reward['id'] ?? 0), $now);
        return self::addReward($uid, $reward, $doubleM, $method, $now);
    }

    /**
     * 添加奖励信息（add_reward）
     *
     * @param array<string, mixed> $reward type、num，装备 / 坐骑 / 武将另含 id
     * @return array{info: array<string, mixed>}
     */
    private static function addReward(int $uid, array $reward, int $doubleM, string $method, int $now): array
    {
        $num = $doubleM === 1 ? (int)$reward['num'] * 2 : (int)$reward['num'];
        $type = (int)$reward['type'];
        $id = (int)($reward['id'] ?? 0);
        $return = ['info' => [
            'num' => $num,
            'type' => $type,
            'msg' => self::LANG['treasure_get_reward'] . (self::TYPE_NAME[$type] ?? ''),
            'image' => $reward['image'] ?? 1,
        ]];
        $class = 'act_treasure';
        switch ($type) {
            case 1: // 元宝
                Member::addCurrency($uid, gold: $num);
                break;
            case 2: // 银币
                Member::addCurrency($uid, silver: $num);
                break;
            case 3: // 黄宝石
                TavernHandler::addJade($uid, 2, $num, $class, $method);
                break;
            case 4: // 紫宝石
                TavernHandler::addJade($uid, 3, $num, $class, $method);
                break;
            case 5: // 原魂
                Member::addCurrency($uid, soul: $num);
                break;
            case 6: // 玉石
                MemberJade::add($uid, $num, $class, $method, $now);
                break;
            case 7: // 声望
                Member::addReputation($uid, $num);
                break;
            case 8: // 装备
                $item = Equip::ROWS[$id] ?? null;
                if ($item !== null) {
                    Equipment::addFromTemplate($uid, $id, $num);
                    // 发送广播到小喇叭（只发紫装以上品质的装备）
                    if ((int)$item['quality'] >= 5) {
                        $sender = Chat::sender($uid);
                        $name = Chat::quality($item['quality'], (string)$item['name']);
                        Chat::sendToHorn(sprintf(self::LANG['chat_treasure_get'], (string)($sender['nickname'] ?? ''), $name), $sender);
                    }
                }
                $return['info'] = [
                    'id' => $item['id'] ?? null,
                    'image' => $item['id'] ?? null,
                    'name' => $item['name'] ?? null,
                    'quality' => $item['quality'] ?? null,
                    'type' => 8,
                ];
                break;
            case 9: // 宝石碎片
                OwnGem::addChip($uid, $num, $class, $method, $now);
                break;
            case 10: // 突飞卡
                FreeLeap::add($uid, $num, $now);
                break;
            case 11: // 坐骑（只加一匹）
                $mount = MountTemp::ROWS[$id] ?? null;
                $result = OwnMount::addMount($uid, $id, 0, 1, $class, $method, $now);
                if ($result['status'] === 1 && $mount !== null) {
                    if ((int)$mount['quality'] >= 5) {
                        $sender = Chat::sender($uid);
                        $qualityStr = Chat::quality($mount['quality'], (string)$mount['name']);
                        Chat::sendToHorn(sprintf(self::LANG['chat_treasure_mount_get'], (string)($sender['nickname'] ?? ''), $qualityStr), $sender);
                    }
                    $return['info'] = ['id' => $result['info']['mid'] ?? $id, 'name' => $mount['name'], 'quality' => $mount['quality'], 'type' => 11];
                }
                break;
            case 13: // 武将
                $return['info'] = GeneralTemplate::ROWS[$id] ?? [];
                if (General::ownsTemplate($uid, $id)) {
                    // 查询武将的紫宝石价格信息
                    $jade = FestReward::generalJade($id);
                    if ($jade !== null) {
                        TavernHandler::addJade($uid, 3, (int)$jade, $class, $method);
                        $return['info']['msg'] = self::LANG['treasure_get_reward'] . self::TYPE_NAME[4] . '*' . $jade;
                    }
                } else {
                    $general = General::addGeneral($uid, $id);
                    if ($general !== null) {
                        $star = Chat::starWord((int)$general['star']);
                        if ($star !== null) {
                            $sender = Chat::sender($uid);
                            Chat::sendToHorn(sprintf(self::LANG['treasure_general_get'], (string)($sender['nickname'] ?? ''), $star, $general['name']), $sender);
                            // 聊天框发送消息
                            $chat = Chat::getMessageStr(40, ['star_general' => $star, 'general' => $general['name']], $sender);
                            Chat::sendToAll($chat, Chat::TYPE_SYSTEM, 0, 0, $sender);
                        }
                        // 添加酒馆日志(武将id, 拥有状态, 获取方式)
                        PubLog::add($uid, $id, 1, 11, $now);
                    }
                }
                break;
            case 15: // 精炼石
            case 16: // 五彩石
            case 17: // 上古血石
                MemberStone::add($uid, $num, $type - 14, $class, $method, $now);
                break;
            case 21: case 22: case 23: case 24: case 25: case 26: case 27: case 28: case 29: // 装备精华
                $essId = self::ESSENCE_ID[$type];
                $essInfo = EquipEssence::ROWS[$essId] ?? [];
                if ($essInfo !== []) {
                    unset($essInfo['desc']);
                    $essInfo['value'] = $num;
                    Equipment::addTowerItem($uid, $essId, $num);
                }
                $return['info'] = $essInfo;
                $return['info']['type'] = $type;
                $return['info']['num'] = $num;
                break;
        }
        return $return;
    }

    /**
     * 寻宝获得汉字奖励处理（do_hanzi_reward + hanzi_reward）
     *
     * 双倍时多得 1 个；记日志（type 19、num 数量、max_num 汉字 id），累加汉字收集记录，更新集字兑换 / 集字成就提示状态。
     *
     * @param array<string, mixed> $rewardInfo
     * @return array{info: array<string, mixed>}
     */
    private static function doHanziReward(int $uid, int $version, array $rewardInfo, int $hzid, int $doubleM, int $nowSite, int $hznum, int $now): array
    {
        $hanziNum = $doubleM === 1 ? $hznum + 1 : $hznum;
        self::addRewardLog($uid, $version, $nowSite, 19, $hanziNum, $hzid, $now);
        // 记录汉字收集记录
        $row = DB::table('act_treasure_hanzi')->select('type_str')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')->first();
        if ($row === null) {
            DB::execute(
                'INSERT INTO `zc_sanguo_act_treasure_hanzi` (`uid`, `version`, `type_str`, `dateline`) VALUES (:uid, :version, :str, :now)
                 ON DUPLICATE KEY UPDATE `type_str` = VALUES(`type_str`), `dateline` = VALUES(`dateline`)',
                ['uid' => $uid, 'version' => $version, 'str' => $hzid . ',' . $hanziNum, 'now' => $now]
            );
        } else {
            $old = (string)$row['type_str'];
            $newTypeArr = [];
            if ($old !== '') {
                $newTypeArr = explode(';', $old);
                $add = false;
                foreach ($newTypeArr as $typeKey => $typeNum) {
                    $info = explode(',', $typeNum);
                    if ((int)($info[0] ?? 0) > 0 && (int)$info[0] === $hzid && (int)($info[1] ?? 0) > 0) {
                        $newTypeArr[$typeKey] = $info[0] . ',' . ((int)$info[1] + $hanziNum);
                        $add = true;
                        break;
                    }
                }
                // 不在已拥有的汉字列表中
                if (!$add) {
                    $newTypeArr[] = $hzid . ',' . $hanziNum;
                }
            } else {
                $newTypeArr[] = $hzid . ',' . $hanziNum;
            }
            DB::table('act_treasure_hanzi')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')
                ->update(['type_str' => implode(';', $newTypeArr), 'dateline' => $now]);
        }
        // 集字兑换 or 集字成就是否达成可领取的条件
        $hanziList = self::getHanziInfo($uid, $version);
        if ($hanziList['status'] === 1 && $hanziList['hzexch_open'] === 1) {
            self::updateTreasure($uid, $version, ['hzexch_reward' => 1]);
        }
        self::hzachPopo($uid, $version);
        return ['info' => [
            'type' => $rewardInfo['type'],
            'msg' => self::LANG['treasure_get_reward'] . (self::TYPE_NAME[(int)$rewardInfo['type']] ?? ''),
            'num' => $hzid,
            'hanzi_id' => $rewardInfo['num'],
            'hanzi_num' => $doubleM > 0 ? $hznum + 1 : $hznum,
        ]];
    }

    /**
     * 汉字兑换信息（get_hanzi_info：8 个字的数量、各兑换奖励是否可兑换）
     *
     * @return array<string, mixed>
     */
    private static function getHanziInfo(int $uid, int $version): array
    {
        $baseHanzi = ActTreasureHanzireward::ROWS;
        if ($baseHanzi === []) {
            return ['status' => -2];
        }
        $hanziInfo = $newHanArr = [];
        $row = DB::table('act_treasure_hanzi')->select('type_str')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')->first();
        if ($row !== null) {
            $hanziInfo = explode(';', (string)$row['type_str']);
            foreach ($hanziInfo as $hanzi) {
                $hanInfo = explode(',', $hanzi);
                if ((int)($hanInfo[0] ?? 0) > 0 && (int)($hanInfo[1] ?? 0) > 0) {
                    $newHanArr[(int)$hanInfo[0]] = (int)$hanInfo[1];
                }
            }
        }
        $hanziList = [];
        for ($id = 1; $id <= 8; $id++) {
            $hanziList[] = ['id' => $id, 'num' => $newHanArr[$id] ?? 0];
        }
        $return = ['hanzi_list' => $hanziList, 'hzexch_open' => 0, 'hzach_open' => 0];
        $list = [];
        foreach ($baseHanzi as $value) {
            $tmp = ['id' => $value['id'], 'desc' => $value['desc'], 'level' => $value['level'], 'hzexch_open' => 0];
            // 是否可以兑换状态
            $openArr = self::hanziOpen($value, $hanziInfo);
            $tmp['open'] = $openArr[$value['id']] ?? 0;
            if ($tmp['open'] > 0) {
                $return['hzexch_open'] = 1;
            }
            for ($i = 1; $i <= 3; $i++) {
                $tmp['type' . $i] = $value['type' . $i];
                $tmp['value' . $i] = $value['value' . $i];
                if ($value['type' . $i] === 8) {
                    $tmp['equip'] = self::rewardItem(8, $value['value' . $i])['info'] ?? null;
                } elseif ($value['type' . $i] === 11) {
                    $tmp['mount'] = self::rewardItem(11, $value['value' . $i])['info'] ?? null;
                } elseif ($value['type' . $i] === 13) {
                    $tmp['general'] = self::rewardItem(13, $value['value' . $i])['info'] ?? null;
                }
            }
            $list[] = $tmp;
        }
        $return['list'] = $list;
        $return['base_hanzi'] = $baseHanzi;
        $return['status'] = 1;
        return $return;
    }

    /**
     * 汉字兑换标识处理（hanzi_open：拥有的字包含兑换奖励需要的全部字为 1；参与奖 8 恒为 0；没有收集记录时返回空数组）
     *
     * @param array<string, mixed> $reward
     * @param list<string> $hanziInfo 收集记录按「;」拆开的「id,num」
     * @return array<int, int>
     */
    private static function hanziOpen(array $reward, array $hanziInfo): array
    {
        if ($hanziInfo === []) {
            return [];
        }
        $open = 0;
        $hInfo = [];
        foreach ($hanziInfo as $hanzi) {
            if ($hanzi === '') {
                continue;
            }
            $arr = explode(',', $hanzi);
            if ((int)($arr[1] ?? 0) > 0) {
                $hInfo[] = $arr[0];
            }
        }
        if ($reward['id'] !== 8 && isset(self::HANZI_ARR[$reward['id']]) && $hInfo !== []) {
            $hanziIds = self::HANZI_ARR[$reward['id']];
            $newH = array_values(array_intersect($hInfo, $hanziIds));
            sort($newH);
            if ($newH === $hanziIds) {
                $open = 1;
            }
        }
        return [$reward['id'] => $open];
    }

    /**
     * 集齐 8 字的次数（get_hanzi_num：寻宝日志里每个字累计获得数；8 个字都有记录时取最小值，否则 0）
     */
    private static function hanziNum(int $uid, int $version): int
    {
        $rows = DB::query(
            'SELECT `max_num`, SUM(`num`) AS `hnum` FROM `zc_sanguo_act_treasure_log` WHERE `version` = :version AND `uid` = :uid AND `type` = 19
             AND `deleted_at` IS NULL GROUP BY `max_num`',
            ['version' => $version, 'uid' => $uid]
        );
        if (count($rows) < 8) {
            return 0;
        }
        return max(0, (int)min(array_column($rows, 'hnum')));
    }

    /**
     * 已领取的集字成就 id
     *
     * @return list<int>
     */
    private static function hanziachAids(int $uid, int $version): array
    {
        return array_map('intval', array_column(
            DB::table('act_treasure_hanziach_log')->select('aid')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')->get(),
            'aid'
        ));
    }

    /**
     * 获取汉字集字成就状态并写入 hzach_reward（get_hzach_popo：按成就表顺序，最后一个判断的成就决定结果）
     */
    private static function hzachPopo(int $uid, int $version): int
    {
        $num = self::hanziNum($uid, $version);
        $aidArr = self::hanziachAids($uid, $version);
        $return = 0;
        foreach (ActTreasureHanziach::ROWS as $hanziach) {
            if (in_array($hanziach['id'], $aidArr, true)) {
                $return = 0;
            } elseif ($num >= $hanziach['num']) {
                $return = 1;
            }
        }
        self::updateTreasure($uid, $version, ['hzach_reward' => $return]);
        return $return;
    }

    /**
     * 寻宝次数排行（get_rank_list：VIP 2 以上、寻宝次数大于 0，次数降序、时间升序，前 30）
     *
     * @return list<array{uid: int, nickname: string, num: int, rank_reward: int}>
     */
    private static function rankList(int $version): array
    {
        $rows = DB::table('act_treasure')->select('uid', 'nickname', 'num', 'rank_reward')->where('version', $version)
            ->where('vip', '>=', self::VIP)->where('num', '>', 0)->whereNull('deleted_at')
            ->orderBy('num', 'DESC')->orderBy('dateline', 'ASC')->orderBy('id', 'ASC')->limit(self::RANK_NUM)->get();
        return array_map(static fn (array $r): array => [
            'uid' => (int)$r['uid'],
            'nickname' => (string)$r['nickname'],
            'num' => (int)$r['num'],
            'rank_reward' => (int)$r['rank_reward'],
        ], $rows);
    }

    /**
     * 对奖励信息处理（reward_item：装备、坐骑、武将、精华的展示信息）
     *
     * @return array<string, mixed>
     */
    private static function rewardItem(int $type, int $id): array
    {
        switch ($type) {
            case 8: // 装备
                $item = Equip::ROWS[$id] ?? null;
                return ['info' => $item === null ? [] : [
                    'id' => $item['id'],
                    'image' => $item['id'],
                    'name' => $item['name'],
                    'type' => $item['type'],
                    'quality' => $item['quality'],
                    'needlevel' => $item['needlevel'],
                    'power' => $item['power'],
                    'desc' => $item['description'],
                ]];
            case 11: // 坐骑
                $mount = MountTemp::ROWS[$id] ?? null;
                return ['info' => $mount === null ? [] : ['id' => $mount['id'], 'name' => $mount['name'], 'quality' => $mount['quality']]];
            case 13: // 武将（旧版技能也按 talentid 查）
                $general = GeneralTemplate::ROWS[$id] ?? null;
                if ($general === null) {
                    return ['info' => []];
                }
                $general['talent'] = Talent::ROWS[$general['talentid']] ?? [];
                $general['skill'] = Skill::ROWS[$general['talentid']] ?? [];
                return ['info' => $general];
            case 21: case 22: case 23: case 24: case 25: case 26: case 27: case 28: case 29: // 装备精华
                $essInfo = EquipEssence::ROWS[self::ESSENCE_ID[$type]] ?? null;
                if ($essInfo === null) {
                    return [];
                }
                unset($essInfo['desc']);
                return ['info' => $essInfo];
        }
        return [];
    }

    /**
     * 对二维数组进行排序（_sort_array：array_multisort，排序键相同时按元素本身升序）
     *
     * @param list<array<string, mixed>> $array
     */
    private static function sortArray(array &$array, string $keyid, string $order): void
    {
        usort($array, static function (array $a, array $b) use ($keyid, $order): int {
            $cmp = $order === 'asc' ? $a[$keyid] <=> $b[$keyid] : $b[$keyid] <=> $a[$keyid];
            return $cmp !== 0 ? $cmp : $a <=> $b;
        });
    }

    /**
     * 计划任务发奖并返回信件文字（get_treasure_reward）
     */
    private static function cronReward(int $type, int $num, int $uid, string $method, int $now): string
    {
        $l = self::LANG_CRON;
        $ge = $l['cron_ge'];
        $class = 'cron_acttask';
        switch ($type) {
            case 1:
                Member::addCurrency($uid, gold: $num);
                return $l[1] . $num . $ge;
            case 2:
                Member::addCurrency($uid, silver: $num);
                return $l[2] . $num . $ge;
            case 3:
                TavernHandler::addJade($uid, 2, $num, $class, $method);
                return $l[3] . $num . $ge;
            case 4:
                TavernHandler::addJade($uid, 3, $num, $class, $method);
                return $l[4] . $num . $ge;
            case 5:
                Member::addCurrency($uid, soul: $num);
                return $l[5] . $num . $ge;
            case 6:
                MemberJade::add($uid, $num, $class, $method, $now);
                return $l[6] . $num . $ge;
            case 7:
                Member::addReputation($uid, $num);
                return $l[7] . $num . $ge;
            case 8:
                $item = Equip::ROWS[$num] ?? null;
                if ($item !== null) {
                    Equipment::addFromTemplate($uid, $num);
                }
                return $l[8] . ($item['name'] ?? '') . '，';
            case 9:
                OwnGem::addChip($uid, $num, $class, $method, $now);
                return $l[9] . $num . $ge;
            case 10:
                FreeLeap::add($uid, $num, $now);
                return $l[10] . $num . $ge;
            case 11:
                $result = OwnMount::addMount($uid, $num, 0, 1, $class, $method, $now);
                return $result['status'] === 1 ? $l[11] . (MountTemp::ROWS[$num]['name'] ?? '') : '';
            case 12:
                TavernHandler::addJade($uid, 1, $num, $class, $method);
                return $l[12] . $num . $ge;
            case 13:
                $result = FestReward::grantGeneral($uid, $num, $class, $method, $now);
                if ($result['owned']) {
                    return $result['jade'] !== null ? $l['cron_change_purple'] . $result['jade'] . $ge : '';
                }
                return $result['general'] !== null ? $l['cron_general'] . $result['general']['name'] : '';
            case 14:
                Member::addCurrency($uid, act: $num);
                return $l[14] . $num . $ge;
            case 15:
            case 16:
            case 17:
                MemberStone::add($uid, $num, $type - 14, $class, $method, $now);
                return $l[$type] . $num . $ge;
            case 21: case 22: case 23: case 24: case 25: case 26: case 27: case 28: case 29:
                $essId = self::ESSENCE_ID[$type];
                if (!isset(EquipEssence::ROWS[$essId])) {
                    return '';
                }
                Equipment::addTowerItem($uid, $essId, $num);
                return $l[$type] . $num . $ge;
        }
        // 依赖未移植：类型 18（按等级颜色拼精华名的分支，奖励表里没有这一类）
        return '';
    }
}
