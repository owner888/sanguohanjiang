<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\General\TavernHandler;
use Hanjiang\MasterData\ActkemariExchangeLottery;
use Hanjiang\MasterData\ActkemariRankReward;
use Hanjiang\MasterData\ActkemariReward;
use Hanjiang\MasterData\Equip;
use Hanjiang\MasterData\EquipEssence;
use Hanjiang\MasterData\General as GeneralTemplate;
use Hanjiang\MasterData\KemariPrice;
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
use Hanjiang\Model\MemberIntegral;
use Hanjiang\Model\MemberJade;
use Hanjiang\Model\MemberStone;
use Hanjiang\Model\OwnGem;
use Hanjiang\Model\OwnMaterials;
use Hanjiang\Model\PassGeneral;
use Hanjiang\Model\PubLog;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 蹴鞠大赛（移植旧版 controllers/act_kemari.php + models/actkemari_model.php，计划任务 cron_kemari.php）
 *
 * 活动时间与版本取 ActivityTime 的 actkemari（旧版数据已结束）；活动结束后图标多挂一天，所以活动提前一天结束
 * （_end = endtime - 86401，结束后只能查看排行与领排行奖励）。射门分小球 1 / 大球 2（MasterData KemariPrice 的价格）：每天小球 3 次、
 * 大球 1 次免费（间隔 30 分钟，用完到次日零点），冷却中射门扣元宝。小球 20%、大球 80% 进球（mt_rand(0, 100)），按是否进球从
 * ActkemariReward 加权抽一份奖励（VIP 1 以下不出武将，VIP 5 以下不出神将，已拥有的武将 / 神将不出）；进球数排行（VIP 2 以上）
 * 前 20 名领 ActkemariRankReward。兑奖券（member_integral.act_kemari_int，与过关斩将 / 世界杯共用）在 ActkemariExchangeLottery 兑换。
 * 每日 0 点计划任务（cron_kemari::index）重置射门次数（活动开始 5 小时内不执行）；活动结束后 10 小时内把兑奖券按每张 2 万银币兑换、
 * 给未领取的前 20 名发排行奖励（均发信件），同一任务里发我爱世界杯的排行奖励（WorldcupHandler::cronRankReward）。
 * 每次请求本控制器时（构造函数）先按 refresh_time 做每日刷新。
 * 客户端 kemaripop.js 调用 index、action（type）、kemari_rank、kemari_rank_reward、kemari_reward（type）、get_lottery、
 * action_lottery（id）。当前时间取 BossClock。
 *
 * 照旧版：射门的元宝在抽奖之前扣（抽不到奖励回 3，元宝不退）；免费次数用完时首页的 cd 为「0 - 当前时间」；奖励里带 rate_in；
 * 装备奖励同时写进 equip 与 general；kemari_rank_reward 返回的 reward 是排行奖励行（不带 info）；action_lottery 参数错误时
 * 兑奖锁不释放（旧版解的是射门锁），30 秒后过期。
 * 与旧版的差别：各操作锁改为 Redis SET NX；兑奖券先原子扣减（不足回 3）再发奖（旧版先发奖后写回余额）；排行奖励状态用条件更新；
 * 剩余免费次数不小于 0（旧版无符号列）。
 */
final class ActKemariHandler
{
    public const string ACTIVITY = 'actkemari';

    /** 小球间隔时间（分钟） */
    private const int SMALL_TIME = 30;
    /** 排行榜 VIP 限制 */
    private const int RANK_VIP = 2;
    /** vip5 级以上获得稀有神将 */
    private const int REWARD_VIP_RARE = 5;
    /** vip1 级以上获得武将 */
    private const int REWARD_VIP_NORMAL = 1;
    /** 每天免费次数（小球、大球） */
    private const array FREE_TIMES = [1 => 3, 2 => 1];
    /** 宝石折扣 */
    private const float REBATE = 0.8;

    private const string KEMARI_LOCK = 'kemari_lock_';
    private const string RANK_LOCK = 'rank_lock';
    private const string LOTTERY_LOCK = 'lottery_lock';

    /** kemari_lang.php + chat_lang.php + cron_lang.php */
    private const array LANG = [
        'kemari_month' => '月',
        'kemari_day' => '日',
        'kemari_not_open' => '功能未开启！',
        'kemari_price_error' => '获取射门信息失败！',
        'kemari_reward_error' => '获取射门奖励信息失败！',
        'kemari_fast_error' => '操作太快了！',
        'kemari_end_error' => '活动已经结束！',
        'kemari_kemari_error' => '射门奖励信息错误',
        'stype_error' => '传参类型错误',
        'get_userinfo_error' => '获取玩家信息失败',
        'kemari_gold_error' => '元宝不足',
        'kemari_not_base_error' => '排行奖励表错误',
        'kemari_op_fast_error' => '操作不要太频繁！',
        'kemari_rank_get_error' => '领取排行奖励时间未到！',
        'kemari_not_rank_error' => '您不在射门排名中，不能领取奖励。',
        'kemari_rank_gone_error' => '您已经领取排行奖励！',
        'lottery_reward_error' => '获取奖励信息失败',
        'lottery_error' => '奖券不足！',
        'kemari_general_get' => '恭喜玩家【%s】在蹴鞠大赛中获得%s星武将-%s',
        'kemari_general_pr_get' => '恭喜玩家【%s】在蹴鞠大赛中获得%s星神将-%s',
        'kemari_get_reward' => '恭喜您获得',
        'chat_kemari_get_quality' => '恭喜【%s】在蹴鞠大赛中获得装备-%s',
        'cron_autokemari_get' => '亲爱的玩家！蹴鞠活动已结束，您未领取的进球榜排行奖励，系统已自动领取，',
        'cron_kemarifinish_get1' => '蹴鞠活动已结束，您未兑换的兑奖券，系统已自动兑换，',
        'cron_kemarifinish_get2' => '兑换',
        'cron_get' => '获得了第',
        'cron_award' => '名的奖励：',
        'cron_check' => '请查收!',
        'cron_ge' => '个，',
        'message_system_title' => '系统通知',
    ];

    /** 奖励类型名（kemari_1 ~ 27） */
    private const array TYPE_NAME = [
        1 => '元宝', 2 => '银币', 3 => '黄宝石', 4 => '紫宝石', 5 => '原魂', 6 => '玉石', 7 => '声望', 8 => '装备', 9 => '魔石碎片',
        10 => '突飞卡', 11 => '蓝宝石', 12 => '武将', 13 => '军令', 14 => '精炼石', 15 => '五彩石', 16 => '上古血玉',
        17 => '武器精华(白)', 18 => '防具精华(白)', 19 => '饰品精华(白)', 20 => '武器精华(绿)', 21 => '防具精华(绿)', 22 => '饰品精华(绿)',
        23 => '武器精华(蓝)', 24 => '防具精华(蓝)', 25 => '饰品精华(蓝)', 26 => '神将', 27 => '兑奖券',
    ];

    /** 计划任务发奖的信件文字（cron_lang.php，get_reward_item） */
    private const array LANG_CRON_TYPE = [
        1 => '元宝', 2 => '银币', 3 => '黄宝石', 4 => '紫宝石', 5 => '将魂', 6 => '玉石', 7 => '声望', 9 => '宝石碎片', 10 => '突飞卡',
        11 => '蓝宝石', 14 => '精炼石', 15 => '五彩石', 16 => '上古血石', 17 => '白色武器精华', 18 => '白色防具精华', 19 => '白色饰品精华',
        20 => '绿色武器精华', 21 => '绿色防具精华', 22 => '绿色饰品精华', 23 => '蓝色武器精华', 24 => '蓝色防具精华', 25 => '蓝色饰品精华',
        27 => '兑奖券',
    ];

    /**
     * 首页: c=act_kemari&m=index
     *
     * -1 功能未开启；-2 获取信息失败；0 失败；1 成功
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $act = self::init($uid, $now);
        if ($act === null) {
            return ['status' => -1, 'msg' => self::LANG['kemari_not_open']];
        }
        // 获取射门列表
        $list = array_values(KemariPrice::ROWS);
        if ($list === []) {
            return ['status' => -2, 'msg' => self::LANG['kemari_price_error']];
        }
        // 是否可以进行免费射门操作
        $kemariInfo = self::kemariInfo($uid, $act['version'], $now);
        [$cdArr, $openArr] = self::kemariCd($kemariInfo, $now);
        foreach ($list as $key => $val) {
            $list[$key]['id'] = (int)$val['id'];
            if ($cdArr[$key + 1] > 0) {
                $list[$key]['cd'] = $cdArr[$key + 1] - $now;
                $list[$key]['price'] = $val['price'];
            } elseif ($openArr[$key + 1] > 0) {
                $list[$key]['cd'] = 0;
                $list[$key]['price'] = 0;
            } else {
                // 照旧版：免费次数用完且没有冷却时为 0 - 当前时间
                $list[$key]['cd'] = $cdArr[$key + 1] - $now;
                $list[$key]['price'] = $val['price'];
            }
            $list[$key]['times'] = $openArr[$key + 1];
        }
        // 获取玩家奖券个数
        $lotteryNum = PassGeneral::lotteryInfo($uid);
        // 判断vip级别是否有变更，获取进球榜前三名(vip2以上的玩家)
        self::syncVip($uid, $act['version'], $now);
        return [
            'status' => 1,
            'list' => $list,
            'time' => $act['act_start'] . '-' . $act['act_end'],
            'lottery_num' => $lotteryNum,
            'rank_list' => self::topThree($act['version']),
        ];
    }

    /**
     * 射门: c=act_kemari&m=action（type 1 小球 2 大球）
     *
     * -5 获取玩家信息失败；-4 球类型错误；-3 蹴鞠大赛已经结束；-2 奖励表错误；-1 功能未开启；0 失败；1 成功；2 操作太快了；
     * 3 射门奖励信息错误；4 元宝不足
     */
    public static function action(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $lockKey = self::KEMARI_LOCK . $uid;
        if ($uid <= 0 || !FestLock::acquire($lockKey)) {
            return ['status' => 2, 'msg' => self::LANG['kemari_fast_error']];
        }
        try {
            $act = self::init($uid, $now);
            if ($act === null) {
                return ['status' => -1, 'msg' => self::LANG['kemari_not_open']];
            }
            // 活动已经结束
            if ($act['reward_cd'] <= 0) {
                return ['status' => -3, 'msg' => self::LANG['kemari_end_error']];
            }
            // 获取射门奖励信息  1 小球  2大球
            $sType = (int)$ctx->input('type', 0);
            if (!in_array($sType, [1, 2], true)) {
                return ['status' => -4, 'msg' => self::LANG['stype_error']];
            }
            $rewardList = self::rewardList($sType);
            if ($rewardList === []) {
                return ['status' => -2, 'msg' => self::LANG['kemari_reward_error']];
            }
            $kemariInfo = self::kemariInfo($uid, $act['version'], $now);
            if ($kemariInfo === null) {
                return ['status' => -5, 'msg' => self::LANG['get_userinfo_error']];
            }
            return Member::locked($uid, static fn (): array => self::doAction($uid, $act, $sType, $rewardList, $kemariInfo, $now));
        } finally {
            FestLock::release($lockKey);
        }
    }

    /**
     * 射门（在玩家行锁事务里执行）
     *
     * @param array<string, mixed> $act
     * @param list<array<string, mixed>> $rewardList
     * @param array<string, int> $kemariInfo
     * @return array<string, mixed>
     */
    private static function doAction(int $uid, array $act, int $sType, array $rewardList, array $kemariInfo, int $now): array
    {
        $member = DB::table('member')->select('gold', 'vip', 'nickname')->where('uid', $uid)->whereNull('deleted_at')->first() ?? [];
        $price = (int)(KemariPrice::ROWS[$sType]['price'] ?? 0);
        // cd时间<=0 不扣除元宝
        $cd = $kemariInfo['normal_time' . $sType] - $now;
        if ($cd > 0) {
            if ((int)($member['gold'] ?? 0) < $price || !Member::deductCurrency($uid, gold: $price)) {
                // 元宝不足
                return ['status' => 4, 'msg' => self::LANG['kemari_gold_error']];
            }
        }
        // VIP1以上获得武将   VIP5以上获得稀有神将；已拥有的武将不出
        $vip = (int)($member['vip'] ?? 0);
        $typeArr1 = $typeArr2 = [];
        $rateTotal1 = $rateTotal2 = 0.0;
        foreach (self::shiftReward($rewardList, $vip) as $reward) {
            if (in_array($reward['type'], [12, 26], true) && General::ownsTemplate($uid, (int)$reward['value'])) {
                continue;
            }
            if ($reward['is_goalin'] === 0) {
                // 非进球奖励
                $typeArr1[] = $reward;
                $rateTotal1 += $reward['rate'];
            } else {
                // 进球奖励
                $typeArr2[] = $reward;
                $rateTotal2 += $reward['rate'];
            }
        }
        $randNum = mt_rand(0, 100);
        // 小球 20 以内进球，大球 80 以内进球
        $result = $randNum <= ($sType === 1 ? 20 : 80) ? 1 : 0;
        $lastReward = $result === 1 ? self::randArr($typeArr2, $rateTotal2, 1) : self::randArr($typeArr1, $rateTotal1, 1);
        if (!isset($lastReward[0])) {
            // 射门奖励信息错误（照旧版：元宝已扣）
            return ['status' => 3, 'msg' => self::LANG['kemari_kemari_error']];
        }
        $reward = $lastReward[0];
        if (in_array($reward['type'], [8, 12, 26], true)) {
            $reward['id'] = $reward['value'];
        }
        $addReward = self::addReward($uid, (string)($member['nickname'] ?? ''), $reward, 'action', $now);
        switch ($reward['type']) {
            case 8: // 装备（旧版没有 break，同时写进 general）
                $reward['equip'] = $addReward['info'];
                // no break
            case 12: // 武将
            case 26:
                $reward['general'] = $addReward['info'];
                break;
            case 17: case 18: case 19: case 20: case 21: case 22: case 23: case 24: case 25:
                $reward['info'] = $addReward['info'];
                break;
        }
        // cd > 0 不更新免费剩余次数 不更新normal_time
        if ($cd <= 0) {
            $freeTimes = max(0, $kemariInfo['free_times' . $sType] - 1);
            $normalTime = $freeTimes <= 0 ? (int)strtotime(date('Y-m-d', $now + 86400)) : $now + self::SMALL_TIME * 60;
        } else {
            $freeTimes = $kemariInfo['free_times' . $sType];
            $normalTime = $kemariInfo['normal_time' . $sType];
        }
        DB::execute(
            "UPDATE `zc_sanguo_actkemari` SET `free_times{$sType}` = :free, `normal_time{$sType}` = :normal, `goal_in` = `goal_in` + :goal,
                 `total_num` = `total_num` + 1 WHERE `uid` = :uid AND `version` = :version AND `deleted_at` IS NULL",
            ['free' => $freeTimes, 'normal' => $normalTime, 'goal' => $result, 'uid' => $uid, 'version' => $act['version']]
        );
        // 增加射门日志
        DB::table('actkemari_log')->insert([
            'uid' => $uid,
            'version' => $act['version'],
            'nickname' => Member::logText((string)($member['nickname'] ?? ''), 50),
            'vip' => $vip,
            's_type' => $sType,
            'is_goalin' => $result,
            'type' => $reward['type'],
            'num' => $reward['value'],
            'dateline' => $now,
        ]);
        // 获取我的信息
        $info = self::kemariInfo($uid, $act['version'], $now) ?? $kemariInfo;
        self::syncVip($uid, $act['version'], $now);
        return [
            'status' => 1,
            'times' => $info['free_times' . $sType],
            'price' => $price,
            'cd' => $info['normal_time' . $sType] - $now,
            'lottery_num' => PassGeneral::lotteryInfo($uid),
            'all_gold' => (int)(DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('gold') ?? 0),
            'reward_info' => $reward,
            'is_succ' => $result,
            'rank_list' => self::topThree($act['version']),
        ];
    }

    /**
     * 进球榜: c=act_kemari&m=kemari_rank
     *
     * -1 活动未开启；-2 排行奖励表错误；0 失败；1 成功。is_get：0 不能领，1 可领，2 已领
     */
    public static function kemari_rank(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $act = self::init($uid, $now);
        if ($act === null) {
            return ['status' => -1, 'msg' => self::LANG['kemari_not_open']];
        }
        $baseRank = ActkemariRankReward::ROWS;
        if ($baseRank === []) {
            return ['status' => -2, 'msg' => self::LANG['kemari_not_base_error']];
        }
        self::syncVip($uid, $act['version'], $now);
        $nowRank = 0;
        $rankInfo = $list = [];
        foreach (self::rankList($act['version'], 20) as $key => $value) {
            $key += 1;
            if ($value['uid'] === $uid) {
                $nowRank = $key;
                $rankInfo = $value;
            }
            $list[$key] = ['rank' => $key, 'nickname' => $value['nickname'], 'num' => $value['goal_in'], 'uid' => $value['uid']];
        }
        // 处理排行基础奖励信息
        $baseRankList = [];
        foreach ($baseRank as $rank => $rval) {
            if (!isset($list[$rank])) {
                continue;
            }
            $rankArr = [
                'rank' => $rank,
                'nickname' => $list[$rank]['nickname'],
                'num' => $list[$rank]['num'],
                'uid' => $list[$rank]['uid'],
                'type' => $rval['type'],
                'value' => $rval['value'],
            ];
            if (in_array($rval['type'], [8, 12], true)) {
                $rankArr['info'] = self::rewardItem($rval['type'], $rval['value'])['info'] ?? [];
            }
            $baseRankList[] = $rankArr;
        }
        $isGet = 0;
        if ($act['reward_cd'] <= 0 && $rankInfo !== []) {
            $isGet = $rankInfo['rank_reward_status'] > 0 ? 2 : 1;
        }
        // 排名奖励
        $rankReward = $nowRank > 0 && isset($baseRank[$nowRank]) ? self::rewardChongzhi([$baseRank[$nowRank]], 2)[0] : [];
        return [
            'status' => 1,
            'list' => $baseRankList,
            'rank' => $nowRank,
            'rank_reward' => $rankReward,
            'reward_cd' => $act['reward_cd'],
            'is_get' => $isGet,
            'vip' => self::RANK_VIP,
        ];
    }

    /**
     * 进球榜领取奖励: c=act_kemari&m=kemari_rank_reward
     *
     * -1 活动未开启；-2 排行奖励表错误；0 失败；1 成功；2 领取时间未到；3 不在排名中；4 已经领取；5 操作不要太频繁 / 更新失败
     */
    public static function kemari_rank_reward(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $lockKey = self::RANK_LOCK . $uid;
        if ($uid <= 0 || !FestLock::acquire($lockKey)) {
            return ['status' => 5, 'msg' => self::LANG['kemari_op_fast_error']];
        }
        try {
            $act = self::init($uid, $now);
            if ($act === null) {
                return ['status' => -1, 'msg' => self::LANG['kemari_not_open']];
            }
            // 验证是否达到领取的时间
            if ($act['reward_cd'] > 0) {
                return ['status' => 2, 'msg' => self::LANG['kemari_rank_get_error']];
            }
            $baseRank = ActkemariRankReward::ROWS;
            if ($baseRank === []) {
                return ['status' => -2, 'msg' => self::LANG['kemari_not_base_error']];
            }
            $nowRank = 0;
            $rankInfo = [];
            foreach (self::rankList($act['version'], 20) as $key => $value) {
                if ($value['uid'] === $uid) {
                    $nowRank = $key + 1;
                    $rankInfo = $value;
                }
            }
            if ($nowRank <= 0) {
                return ['status' => 3, 'msg' => self::LANG['kemari_not_rank_error']];
            }
            if ($rankInfo['rank_reward_status'] > 0) {
                return ['status' => 4, 'msg' => self::LANG['kemari_rank_gone_error']];
            }
            $rankReward = $baseRank[$nowRank] ?? null;
            if ($rankReward === null) {
                return ['status' => -2, 'msg' => self::LANG['kemari_not_base_error']];
            }
            // 更新玩家领取奖励状态（条件更新）
            $updated = DB::table('actkemari')->where('uid', $uid)->where('version', $act['version'])->where('rank_reward_status', 0)
                ->whereNull('deleted_at')->update(['rank_reward_status' => 1]);
            if ($updated <= 0) {
                // 旧版语言包键名写错（kearmi_rank_update_error），msg 为 null
                return ['status' => 5, 'msg' => null];
            }
            $reward = ['type' => $rankReward['type'], 'value' => $rankReward['value']];
            if (in_array($reward['type'], [8, 12, 26], true)) {
                $reward['id'] = $reward['value'];
            }
            $nickname = (string)(DB::table('member')->where('uid', $uid)->value('nickname') ?? '');
            self::addReward($uid, $nickname, $reward, 'kemari_rank_reward', $now);
            return [
                'status' => 1,
                'reward' => $rankReward,
                'all_gold' => (int)(DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('gold') ?? 0),
            ];
        } finally {
            FestLock::release($lockKey);
        }
    }

    /**
     * 奖励预览: c=act_kemari&m=kemari_reward（type 1 小球 2 大球）
     *
     * -1 活动未开启；-2 参数错误；-3 获取奖励信息失败；0 失败；1 成功
     */
    public static function kemari_reward(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $act = self::init($uid, BossClock::now());
        if ($act === null) {
            return ['status' => -1, 'msg' => self::LANG['kemari_not_open']];
        }
        $sType = (string)$ctx->input('type', '');
        if (!in_array($sType, ['1', '2'], true)) {
            return ['status' => -2, 'msg' => self::LANG['stype_error']];
        }
        $reward = self::rewardList((int)$sType);
        if ($reward === []) {
            return ['status' => -3, 'msg' => self::LANG['kemari_reward_error']];
        }
        return ['status' => 1, 'list' => self::assocUnique(self::rewardChongzhi($reward, 0))];
    }

    /**
     * 进入兑奖界面: c=act_kemari&m=get_lottery
     *
     * -1 活动未开启；-2 获取奖励信息失败；0 失败；1 成功
     */
    public static function get_lottery(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $act = self::init($uid, BossClock::now());
        if ($act === null) {
            return ['status' => -1, 'msg' => self::LANG['kemari_not_open']];
        }
        if (ActkemariExchangeLottery::ROWS === []) {
            return ['status' => -2, 'msg' => self::LANG['kemari_reward_error']];
        }
        $lotteryNum = PassGeneral::lotteryInfo($uid);
        return ['status' => 1, 'list' => self::lotteryList($lotteryNum), 'lottery_num' => $lotteryNum];
    }

    /**
     * 奖券兑换: c=act_kemari&m=action_lottery（id 兑换项 id）
     *
     * -1 活动未开启；-2 参数错误；-3 获取奖励信息失败 / 活动已结束；0 失败；1 成功；2 操作太频繁；3 奖券不足；4 兑换失败
     */
    public static function action_lottery(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $lockKey = self::LOTTERY_LOCK . $uid;
        if ($uid <= 0 || !FestLock::acquire($lockKey)) {
            return ['status' => 2, 'msg' => self::LANG['kemari_fast_error']];
        }
        $act = self::init($uid, $now);
        if ($act === null) {
            FestLock::release($lockKey);
            return ['status' => -1, 'msg' => self::LANG['kemari_not_open']];
        }
        // 活动已经结束
        if ($act['reward_cd'] <= 0) {
            FestLock::release($lockKey);
            return ['status' => -3, 'msg' => self::LANG['kemari_end_error']];
        }
        $id = (int)$ctx->input('id', 0);
        if ($id <= 0) {
            // 旧版解的是射门锁，兑奖锁留到 30 秒过期
            return ['status' => -2, 'msg' => self::LANG['stype_error']];
        }
        try {
            $rewardInfo = ActkemariExchangeLottery::ROWS[$id] ?? null;
            if ($rewardInfo === null) {
                return ['status' => -3, 'msg' => self::LANG['lottery_reward_error']];
            }
            // 兑换兑换券（原子扣减，不足回 3）
            $balance = MemberIntegral::change($uid, $rewardInfo['lottery_num'], 'act_kemari_int', 2, 'act_kemari', 'action_lottery', $now);
            if ($balance === []) {
                return ['status' => 3, 'msg' => self::LANG['lottery_error']];
            }
            $num = $balance['act_kemari_int'];
            // 发奖
            if (in_array($rewardInfo['type'], [8, 12, 26], true)) {
                $rewardInfo['id'] = $rewardInfo['value'];
            } elseif ($rewardInfo['type'] === 28) {
                // 打造材料
                $rewardInfo['id'] = $rewardInfo['value'];
                $rewardInfo['value'] = $rewardInfo['value1'];
            }
            $member = DB::table('member')->select('nickname', 'vip')->where('uid', $uid)->whereNull('deleted_at')->first() ?? [];
            $addReward = self::addReward($uid, (string)($member['nickname'] ?? ''), $rewardInfo, 'action_lottery', $now);
            // 添加兑奖日志
            DB::table('actkemari_exchange_log')->insert([
                'uid' => $uid,
                'version' => $act['version'],
                'nickname' => Member::logText((string)($member['nickname'] ?? ''), 50),
                'vip' => (int)($member['vip'] ?? 0),
                'type' => $rewardInfo['type'],
                'value' => $rewardInfo['value'],
                'value1' => $rewardInfo['value1'],
                'lottery_num' => $rewardInfo['lottery_num'],
                'dateline' => $now,
            ]);
            $rewardInfo['is_exch'] = $num >= $rewardInfo['lottery_num'] ? 1 : 0;
            switch ($rewardInfo['type']) {
                case 8: // 装备
                    $rewardInfo['equip'] = $addReward['info'];
                    break;
                case 12: // 武将
                case 26:
                    $rewardInfo['general'] = $addReward['info'];
                    break;
                case 17: case 18: case 19: case 20: case 21: case 22: case 23: case 24: case 25:
                    $rewardInfo['info'] = $addReward['info'];
                    break;
                case 28: // 锻造材料
                    $info = OwnMaterials::template($rewardInfo['id']);
                    if ($info !== null) {
                        $rewardInfo['name'] = $info['name'];
                    }
                    break;
            }
            return [
                'status' => 1,
                'lottery_num' => $num,
                'list' => self::lotteryList(PassGeneral::lotteryInfo($uid)),
                'reward' => $rewardInfo,
            ];
        } finally {
            FestLock::release($lockKey);
        }
    }

    /**
     * 计划任务：蹴鞠系统（cron_kemari.php:index，旧版注释「每天零点」）
     *
     * 活动开始 5 小时内整个任务不执行（旧版按蹴鞠活动的开始时间判断，世界杯排行奖励同样跳过）。
     *
     * @return array<string, int>
     */
    public static function cronIndex(int $now): array
    {
        $activity = ActivityClock::byName(self::ACTIVITY);
        if ($now - (int)($activity['starttime'] ?? 0) < 18000) {
            // 如果当前时间距离活动开始时间小于5小时 就不刷新次数
            return [];
        }
        return [
            // 每日清理蹴鞠活动射门次数
            'update_kemari_info' => self::cronResetTimes($now),
            // 蹴鞠活动结束自动兑换玩家手中的兑奖券
            'get_kemari_num_reward' => self::cronLotteryReward($now),
            // 蹴鞠活动自动领取排行奖励
            'get_kemari_rank_reward' => self::cronRankReward($now),
            // 自动领取我爱世界杯排行奖励
            'get_worldcup_num_reward' => WorldcupHandler::cronRankReward($now),
        ];
    }

    /**
     * 每日清理蹴鞠活动射门次数（update_kemari_info：活动期间，免费次数不是默认值的玩家重置）
     *
     * @return int -1 无活动；-2 活动已结束；-3 活动未开始；1 已执行
     */
    public static function cronResetTimes(int $now): int
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
        DB::execute(
            'UPDATE `zc_sanguo_actkemari` SET `free_times1` = 3, `free_times2` = 1, `normal_time1` = 0, `normal_time2` = 0
             WHERE `version` = :version AND (`free_times1` != 3 OR `free_times2` != 1) AND `deleted_at` IS NULL',
            ['version' => (int)$activity['version']]
        );
        return 1;
    }

    /**
     * 蹴鞠活动结束自动兑换玩家手中兑奖券（get_kemari_num_reward：结束前一天 ~ 之后 10 小时内，每张 2 万银币，发信件）
     *
     * 照旧版：兑换全部玩家的兑奖券（不分活动版本，与过关斩将 / 世界杯共用）。与旧版的差别：逐个玩家条件清零（等于读到的值）成功才发银币。
     *
     * @return int -1 无活动；-2 未到；-3 超过 10 小时；1 已执行
     */
    public static function cronLotteryReward(int $now): int
    {
        $activity = ActivityClock::byName(self::ACTIVITY);
        if ($activity === null) {
            return -1;
        }
        $endTime = (int)$activity['endtime'] - 86400;
        if ($endTime > $now) {
            return -2;
        }
        if ($endTime + 36000 < $now) {
            return -3;
        }
        foreach (DB::table('member_integral')->select('uid', 'act_kemari_int')->where('act_kemari_int', '>', 0)->whereNull('deleted_at')->get() as $data) {
            $uid = (int)$data['uid'];
            $lott = (int)$data['act_kemari_int'];
            $cleared = DB::table('member_integral')->where('uid', $uid)->where('act_kemari_int', $lott)->whereNull('deleted_at')
                ->update(['act_kemari_int' => 0]);
            if ($cleared <= 0) {
                continue;
            }
            // 兑换券兑换成银币
            $num = $lott * 20000;
            Member::addCurrency($uid, silver: $num);
            $message = self::LANG['cron_kemarifinish_get1'] . self::LANG['cron_kemarifinish_get2'] . self::LANG_CRON_TYPE[2] . $num
                . self::LANG['cron_ge'] . self::LANG['cron_check'];
            MessageHandler::sendSystemMessage([$uid], 1, self::LANG['message_system_title'], $message);
        }
        return 1;
    }

    /**
     * 蹴鞠自动兑换排行榜奖励（get_kemari_rank_reward：活动结束后 10 小时内，前 20 名（VIP 2 以上）未领取的发奖并发信件）
     *
     * @return int -1 无活动；-2 活动未结束；-3 超过 10 小时；1 已执行
     */
    public static function cronRankReward(int $now): int
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
        $version = (int)$activity['version'];
        foreach (self::rankList($version, 20) as $i => $val) {
            $rank = $i + 1;
            $reward = ActkemariRankReward::ROWS[$rank] ?? null;
            if ($val['rank_reward_status'] !== 0 || $reward === null) {
                continue;
            }
            // 修改领奖状态
            $updated = DB::table('actkemari')->where('uid', $val['uid'])->where('version', $version)->where('rank_reward_status', 0)
                ->whereNull('deleted_at')->update(['rank_reward_status' => 1]);
            if ($updated <= 0) {
                continue;
            }
            $message = self::LANG['cron_autokemari_get'] . self::LANG['cron_get'] . $rank . self::LANG['cron_award']
                . self::cronReward($reward['type'], $reward['value'], $val['uid'], $now) . self::LANG['cron_check'];
            MessageHandler::sendSystemMessage([$val['uid']], 1, self::LANG['message_system_title'], $message);
        }
        return 1;
    }

    /**
     * 活动信息与每日刷新（构造函数 _init + _update_kemari_info）：活动开启时返回版本、射门截止倒计时与日期文字；未开启为 null
     *
     * @return array{version: int, reward_cd: int, act_start: string, act_end: string}|null
     */
    private static function init(int $uid, int $now): ?array
    {
        $info = ActivityClock::open(self::ACTIVITY, $now);
        if ($info === null || $uid <= 0) {
            return null;
        }
        $version = (int)$info['version'];
        $start = (int)$info['starttime'];
        // 活动结束后图标要多挂一天，所以活动会提前一天结束
        $end = (int)$info['endtime'] - 86401;
        $format = 'm' . self::LANG['kemari_month'] . 'd' . self::LANG['kemari_day'];
        // 每日清理蹴鞠活动射门次数
        $userData = self::kemariInfo($uid, $version, $now);
        if ($userData !== null && date('Ymd', $now) !== date('Ymd', $userData['refresh_time'])) {
            $reset = DB::execute(
                'UPDATE `zc_sanguo_actkemari` SET `free_times1` = 3, `free_times2` = 1, `normal_time1` = 0, `normal_time2` = 0
                 WHERE `uid` = :uid AND `version` = :version AND `deleted_at` IS NULL
                   AND (`free_times1` != 3 OR `free_times2` != 1 OR `normal_time1` != 0 OR `normal_time2` != 0)',
                ['uid' => $uid, 'version' => $version]
            );
            if ($reset > 0) {
                // 更新刷新时间（旧版重置有变化时才更新）
                DB::table('actkemari')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')->update(['refresh_time' => $now]);
            }
        }
        return [
            'version' => $version,
            'reward_cd' => max(0, $end - $now),
            'act_start' => date($format, $start),
            'act_end' => date($format, $end),
        ];
    }

    /** 每天的默认次数（init_user_kemari） */
    private const array DEFAULT_TIMES = ['free_times1' => 3, 'free_times2' => 1, 'normal_time1' => 0, 'normal_time2' => 0];

    /**
     * 玩家射门记录（get_kemari_info：没有记录时建档；小球下次时间不是今天时重置免费次数，返回值只改小球的两项，照旧版）
     *
     * @return array<string, int|string>|null
     */
    private static function kemariInfo(int $uid, int $version, int $now): ?array
    {
        $row = self::row($uid, $version);
        if ($row === null) {
            $member = DB::table('member')->select('nickname', 'vip')->where('uid', $uid)->whereNull('deleted_at')->first();
            if ($member === null) {
                return null;
            }
            DB::execute(
                'INSERT IGNORE INTO `zc_sanguo_actkemari` (`uid`, `version`, `nickname`, `vip`, `free_times1`, `free_times2`, `normal_time1`,
                     `normal_time2`, `goal_in`, `total_num`, `rank_reward_status`, `dateline`)
                 VALUES (:uid, :version, :nickname, :vip, 3, 1, 0, 0, 0, 0, 0, :now)',
                ['uid' => $uid, 'version' => $version, 'nickname' => Member::logText((string)$member['nickname'], 50), 'vip' => (int)$member['vip'], 'now' => $now]
            );
            return self::row($uid, $version);
        }
        if (date('ymd', $row['normal_time1']) !== date('ymd', $now) && $row['normal_time1'] > 0) {
            DB::table('actkemari')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')->update(self::DEFAULT_TIMES);
            $row['free_times1'] = 3;
            $row['normal_time1'] = 0;
        }
        return $row;
    }

    /**
     * @return array<string, int|string>|null
     */
    private static function row(int $uid, int $version): ?array
    {
        $row = DB::table('actkemari')->select('uid', 'nickname', 'vip', 'free_times1', 'free_times2', 'normal_time1', 'normal_time2', 'goal_in',
            'total_num', 'rank_reward_status', 'dateline', 'refresh_time')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')->first();
        return $row === null ? null : self::cast($row);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, int|string>
     */
    private static function cast(array $row): array
    {
        foreach ($row as $key => $value) {
            $row[$key] = $key === 'nickname' ? (string)$value : (int)$value;
        }
        return $row;
    }

    /**
     * 射门 CD 与免费次数（kemari_cd：冷却中为下次时间，否则 0）
     *
     * @param array<string, int|string>|null $kemariInfo
     * @return array{0: array<int, int>, 1: array<int, int>}
     */
    private static function kemariCd(?array $kemariInfo, int $now): array
    {
        $cdArr = $openArr = [];
        for ($i = 1; $i <= 2; $i++) {
            $normal = (int)($kemariInfo['normal_time' . $i] ?? 0);
            $cdArr[$i] = $normal - $now <= 0 ? 0 : $normal;
            $openArr[$i] = (int)($kemariInfo['free_times' . $i] ?? 0);
        }
        return [$cdArr, $openArr];
    }

    /**
     * 判断vip级别是否有变更（index / action / kemari_rank 里的 update_kemari_info(vip)）
     */
    private static function syncVip(int $uid, int $version, int $now): void
    {
        $vip = (int)(DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('vip') ?? 0);
        $info = self::kemariInfo($uid, $version, $now);
        if ($info !== null && $info['vip'] !== $vip) {
            DB::table('actkemari')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')->update(['vip' => $vip]);
        }
    }

    /**
     * 进球榜（get_rank_list：VIP 2 以上、进球大于 0，进球降序、时间升序）
     *
     * @return list<array<string, int|string>>
     */
    private static function rankList(int $version, int $limit): array
    {
        $rows = DB::table('actkemari')->select('uid', 'nickname', 'vip', 'goal_in', 'rank_reward_status', 'dateline')->where('version', $version)
            ->where('vip', '>=', self::RANK_VIP)->where('goal_in', '>', 0)->whereNull('deleted_at')
            ->orderBy('goal_in', 'DESC')->orderBy('dateline', 'ASC')->orderBy('id', 'ASC')->limit($limit)->get();
        return array_map(self::cast(...), $rows);
    }

    /**
     * 进球榜前三名（rank / uid / nickname / num）
     *
     * @return list<array<string, int|string>>
     */
    private static function topThree(int $version): array
    {
        $return = [];
        foreach (self::rankList($version, 3) as $key => $val) {
            $return[$key] = ['rank' => $key + 1, 'uid' => $val['uid'], 'nickname' => $val['nickname'], 'num' => $val['goal_in']];
        }
        return $return;
    }

    /**
     * 射门奖励列表（get_reward_list(s_type)）
     *
     * @return list<array<string, mixed>>
     */
    private static function rewardList(int $sType): array
    {
        return array_values(array_filter(ActkemariReward::ROWS, static fn (array $r): bool => $r['s_type'] === $sType));
    }

    /**
     * 剔除奖励信息（_shift_reward：VIP 1 以下没有武将 / 神将，VIP 5 以下没有神将）
     *
     * @param list<array<string, mixed>> $rewardList
     * @return list<array<string, mixed>>
     */
    private static function shiftReward(array $rewardList, int $vip): array
    {
        $newReward = [];
        foreach ($rewardList as $reward) {
            if ($vip < self::REWARD_VIP_NORMAL) {
                if (!in_array($reward['type'], [12, 26], true)) {
                    $newReward[] = $reward;
                }
            } elseif ($vip < self::REWARD_VIP_RARE) {
                if ($reward['type'] !== 26) {
                    $newReward[] = $reward;
                }
            } else {
                $newReward[] = $reward;
            }
        }
        return $newReward;
    }

    /**
     * 奖品随机计算（rand_arr，$state 1）：按 id 排序后 rate × 10 划分区间 [k + 1, rate × 10 + k]，尝试「奖品个数」次，
     * 每次 mt_rand(1, 总 rate × 10) 落在某个区间（不含下界）就选中，选够 $num 个为止；选中的行带 rate_in
     *
     * @param list<array<string, mixed>> $arr
     * @return list<array<string, mixed>>
     */
    private static function randArr(array $arr, float $totalNum, int $num): array
    {
        usort($arr, static fn (array $a, array $b): int => $a['id'] <=> $b['id']);
        $k = 0;
        foreach ($arr as $key => $value) {
            $arr[$key]['rate_in'] = [$k + 1, (int)round($value['rate'] * 10) + $k];
            $k += (int)round($value['rate'] * 10);
        }
        $return = [];
        $count = count($arr);
        $max = (int)round($totalNum * 10);
        for ($i = 1; $i <= $count; $i++) {
            $randNum = mt_rand(1, max(1, $max));
            foreach ($arr as $val) {
                if ($randNum > $val['rate_in'][0] && $randNum <= $val['rate_in'][1]) {
                    if (count($return) >= $num) {
                        break;
                    }
                    $return[] = $val;
                }
            }
        }
        return $return;
    }

    /**
     * 添加奖励信息（add_reward，$num 恒为 0：数量取 value）
     *
     * @param array<string, mixed> $rewardInfo 至少含 type / value；装备、武将、神将、材料另含 id
     * @return array{info: array<string, mixed>}
     */
    private static function addReward(int $uid, string $nickname, array $rewardInfo, string $method, int $now): array
    {
        $num = (int)$rewardInfo['value'];
        $type = (int)$rewardInfo['type'];
        $id = (int)($rewardInfo['id'] ?? 0);
        $return = ['info' => ['type' => $type, 'value' => $num, 'msg' => self::LANG['kemari_get_reward'] . (self::TYPE_NAME[$type] ?? '')]];
        switch ($type) {
            case 1: // 元宝
                Member::addCurrency($uid, gold: $num);
                break;
            case 2: // 银币
                Member::addCurrency($uid, silver: $num);
                break;
            case 3: // 黄宝石
                TavernHandler::addJade($uid, 2, $num, 'act_kemari', $method);
                break;
            case 4: // 紫宝石
                TavernHandler::addJade($uid, 3, $num, 'act_kemari', $method);
                break;
            case 5: // 原魂
                Member::addCurrency($uid, soul: $num);
                break;
            case 6: // 玉石
                MemberJade::add($uid, $num, 'act_kemari', $method, $now);
                break;
            case 7: // 声望
                Member::addReputation($uid, $num);
                break;
            case 8: // 装备
                $item = Equip::ROWS[$id] ?? null;
                if ($item !== null) {
                    Equipment::addFromTemplate($uid, $id);
                }
                // 发送广播到小喇叭：只发紫装以上品质的装备
                if ((int)($item['quality'] ?? 0) >= 5) {
                    $name = Chat::quality($item['quality'], (string)$item['name']);
                    Chat::sendToHorn(sprintf(self::LANG['chat_kemari_get_quality'], $nickname, $name), Chat::sender($uid));
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
                OwnGem::addChip($uid, $num, 'act_kemari', $method, $now);
                break;
            case 10: // 突飞卡
                FreeLeap::add($uid, $num, $now);
                break;
            case 11: // 蓝宝石
                TavernHandler::addJade($uid, 1, $num, 'act_kemari', $method);
                break;
            case 12: // 武将
            case 26: // 神将
                $gid = $id;
                if (General::ownsTemplate($uid, $gid)) {
                    // 查询武将的紫宝石价格信息
                    $jade = FestReward::generalJade($gid);
                    if ($jade !== null) {
                        TavernHandler::addJade($uid, 3, (int)$jade, 'act_kemari', $method);
                        $return['info']['msg'] = self::LANG['kemari_get_reward'] . self::TYPE_NAME[4] . '*' . $jade;
                    }
                } else {
                    $general = General::addGeneral($uid, $gid);
                    if ($general !== null) {
                        $return['info'] = $general;
                        $star = Chat::starWord((int)$general['star']);
                        if ($star !== null) {
                            // 小喇叭 + 聊天框（武将 kemari_general_get / 43，神将 kemari_general_pr_get / 44）
                            $sender = Chat::sender($uid);
                            $horn = $type === 26 ? self::LANG['kemari_general_pr_get'] : self::LANG['kemari_general_get'];
                            Chat::sendToHorn(sprintf($horn, $nickname, $star, $general['name']), $sender);
                            $chat = Chat::getMessageStr($type === 26 ? 44 : 43, ['star_general' => $star, 'general' => $general['name']], $sender);
                            Chat::sendToAll($chat, Chat::TYPE_SYSTEM, 0, 0, $sender);
                        }
                        // 添加酒馆日志(武将id, 拥有状态, 获取方式)
                        PubLog::add($uid, $gid, 1, 11, $now);
                    }
                }
                break;
            case 13: // 军令（add_act(1, value)）
                Member::addCurrency($uid, act: $num);
                break;
            case 14: // 精炼石
            case 15: // 五彩石
            case 16: // 上古血玉
                MemberStone::add($uid, $num, $type - 13, 'act_kemari', $method, $now);
                break;
            case 17: case 18: case 19: case 20: case 21: case 22: case 23: case 24: case 25: // 装备精华
                $essId = PassGeneral::ESSENCE_ID[$type];
                $essInfo = EquipEssence::ROWS[$essId] ?? [];
                unset($essInfo['desc']);
                if ($essInfo !== []) {
                    $essInfo['value'] = $num;
                    Equipment::addTowerItem($uid, $essId, $num);
                }
                $return['info'] = $essInfo;
                $return['info']['type'] = $type;
                $return['info']['num'] = $num;
                break;
            case 27: // 兑奖券（兑奖时不记积分变化日志）
                if ($method === 'action_lottery') {
                    PassGeneral::addLottery($uid, $num);
                } else {
                    MemberIntegral::change($uid, $num, 'act_kemari_int', 1, 'act_kemari', $method, $now);
                }
                break;
            case 28: // 锻造材料
                $info = OwnMaterials::template($id);
                if ($info !== null) {
                    OwnMaterials::change($uid, $info['id'], $info['quality'], $info['name'], $info['chip'], $num, 1, $now);
                    $return['info'] = $info;
                }
                break;
        }
        return $return;
    }

    /**
     * 对奖励信息处理（reward_item：进球榜的装备、武将展示信息）
     *
     * @return array<string, mixed>
     */
    private static function rewardItem(int $type, int $id): array
    {
        $return = [];
        if ($type === 8) {
            $item = Equip::ROWS[$id] ?? null;
            $return['info'] = $item === null ? [] : [
                'id' => $item['id'],
                'image' => $item['id'],
                'name' => $item['name'],
                'type' => $item['type'],
                'quality' => $item['quality'],
                'needlevel' => $item['needlevel'],
                'power' => $item['power'],
                'desc' => $item['description'],
            ];
        } elseif ($type === 12) {
            $general = GeneralTemplate::ROWS[$id] ?? null;
            $return['info'] = [];
            if ($general !== null) {
                $return['info'] = $general;
                $return['info']['talent'] = Talent::ROWS[$general['talentid']] ?? [];
                $return['info']['skill'] = Skill::ROWS[$general['skillid']] ?? [];
            }
        } elseif ($type >= 17 && $type <= 25) {
            $essInfo = EquipEssence::ROWS[PassGeneral::ESSENCE_ID[$type]] ?? null;
            if ($essInfo !== null) {
                unset($essInfo['desc']);
                $return['info'] = $essInfo;
            }
        }
        return $return;
    }

    /**
     * 统一返回（_reward_chongzhi：$lottery 0 带 name，1 带 lottery_num / id，2 都不带）
     *
     * @param list<array<string, mixed>> $newReward
     * @return list<array<string, mixed>>
     */
    private static function rewardChongzhi(array $newReward, int $lottery): array
    {
        $list = [];
        foreach ($newReward as $value) {
            $tmp = ['type' => $value['type']];
            if ($value['type'] === 28) {
                $tmp['mid'] = $value['value'];
                $tmp['value'] = $value['value1'];
            } else {
                $tmp['value'] = $value['value'];
            }
            if ($lottery === 0) {
                $tmp['name'] = $value['name'] ?? null;
            } elseif ($lottery === 1) {
                $tmp['lottery_num'] = $value['lottery_num'];
                $tmp['id'] = $value['id'];
            }
            $list[] = $tmp;
        }
        foreach ($list as &$val) {
            switch ($val['type']) {
                case 12: // 武将或者神将
                case 26:
                    $general = FestReward::generalInfo((int)$val['value']);
                    $val['general'] = $general;
                    $val['name'] = $general['name'] ?? null;
                    $val['star'] = $general['star'] ?? null;
                    break;
                case 8:
                    $item = Equip::ROWS[$val['value']] ?? [];
                    $val['name'] = $item['name'] ?? null;
                    $val['info'] = [
                        'id' => $item['id'] ?? null,
                        'image' => $item['id'] ?? null,
                        'name' => $item['name'] ?? null,
                        'quality' => $item['quality'] ?? null,
                        'needlevel' => $item['needlevel'] ?? null,
                        'power' => $item['power'] ?? null,
                        'type' => $item['type'] ?? null,
                    ];
                    break;
                case 17: case 18: case 19: case 20: case 21: case 22: case 23: case 24: case 25: // 装备精华
                    $essInfo = EquipEssence::ROWS[PassGeneral::ESSENCE_ID[$val['type']]] ?? null;
                    if ($essInfo !== null) {
                        unset($essInfo['desc']);
                        $val['info'] = $essInfo;
                    }
                    break;
                case 28: // 锻造材料
                    $info = OwnMaterials::template((int)$val['mid']);
                    if ($info !== null) {
                        $val['info'] = $info;
                    }
                    break;
                default:
                    if (isset(self::TYPE_NAME[$val['type']])) {
                        $val['name'] = self::TYPE_NAME[$val['type']];
                    }
                    break;
            }
        }
        unset($val);
        return $list;
    }

    /**
     * 二维数组去重（assoc_unique：除武将 / 神将外同类型只留第一个；武将 / 神将移到最后按星级降序）
     *
     * @param list<array<string, mixed>> $arr
     * @return list<array<string, mixed>>
     */
    private static function assocUnique(array $arr): array
    {
        $seen = [];
        $rest = $generals = [];
        foreach ($arr as $v) {
            if (in_array($v['type'], [12, 26], true)) {
                $generals[] = $v;
                continue;
            }
            if (in_array($v['type'], $seen, true)) {
                continue;
            }
            $seen[] = $v['type'];
            $rest[] = $v;
        }
        // sort 函数对数组进行排序（同类型只剩一个，按元素比较即按 type 升序）
        usort($rest, static fn (array $a, array $b): int => $a <=> $b);
        $keysvalue = [];
        foreach ($generals as $k => $v) {
            $keysvalue[$k] = $v['star'];
        }
        arsort($keysvalue);
        foreach (array_keys($keysvalue) as $k) {
            $rest[] = $generals[$k];
        }
        return $rest;
    }

    /**
     * 兑奖列表（get_lottery / action_lottery：ActkemariExchangeLottery 全表 + 是否可兑换）
     *
     * @return list<array<string, mixed>>
     */
    private static function lotteryList(int $lotteryNum): array
    {
        $list = self::rewardChongzhi(array_values(ActkemariExchangeLottery::ROWS), 1);
        foreach ($list as &$value) {
            $value['is_exch'] = $lotteryNum >= $value['lottery_num'] ? 1 : 0;
        }
        unset($value);
        return $list;
    }

    /**
     * 计划任务发奖并返回信件文字（cron_kemari get_reward_item；装备 8 / 武将 12 / 神将 26 的 $num 为 id）
     */
    private static function cronReward(int $type, int $num, int $uid, int $now): string
    {
        $l = self::LANG_CRON_TYPE;
        $ge = self::LANG['cron_ge'];
        $class = 'cron_kemari';
        $method = 'get_kemari_rank_reward';
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
                return '装备-' . ($item['name'] ?? '') . '，';
            case 9:
                OwnGem::addChip($uid, $num, $class, $method, $now);
                return $l[9] . $num . $ge;
            case 10:
                FreeLeap::add($uid, $num, $now);
                return $l[10] . $num . $ge;
            case 11:
                TavernHandler::addJade($uid, 1, $num, $class, $method);
                return $l[11] . $num . $ge;
            case 12: // 武将（旧版另一个 case '12' 军令走不到）
            case 26: // 神将
                $result = FestReward::grantGeneral($uid, $num, $class, $method, $now);
                if ($result['owned']) {
                    return $result['jade'] !== null ? '武将兑换紫宝石' . $result['jade'] . $ge : '';
                }
                return $result['general'] !== null ? '武将-' . $result['general']['name'] : '';
            case 14:
            case 15:
            case 16:
                MemberStone::add($uid, $num, $type - 13, $class, $method, $now);
                return $l[$type] . $num . $ge;
            case 17: case 18: case 19: case 20: case 21: case 22: case 23: case 24: case 25:
                $essId = PassGeneral::ESSENCE_ID[$type];
                if (!isset(EquipEssence::ROWS[$essId])) {
                    return '';
                }
                Equipment::addTowerItem($uid, $essId, $num);
                return $l[$type] . $num . $ge;
            case 27: // 兑奖券
                PassGeneral::addLottery($uid, $num);
                return $l[27] . $num . $ge;
        }
        return '';
    }
}
