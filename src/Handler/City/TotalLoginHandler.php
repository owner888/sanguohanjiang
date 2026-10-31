<?php

declare(strict_types=1);

namespace Hanjiang\Handler\City;

use Hanjiang\Model\ActivityClock;
use Hanjiang\MasterData\General as GeneralTemplate;
use Hanjiang\MasterData\Skill;
use Hanjiang\MasterData\Talent;
use Hanjiang\MasterData\TotalloginReward;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\Chat;
use Hanjiang\Model\General;
use Hanjiang\Model\PubLog;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 累计登陆（移植旧版 controllers/totallogin.php + models/totallogin_model.php）
 *
 * 活动时间与版本取 ActivityTime 的 total_login（旧版数据中未开启，接口回 -1）。活动期间每天打开主城累计登录天数
 * （totallogin，member.php 调 update_day → MemberHandler::index）；累计 15 天可领 MasterData TotalloginReward 第一行的武将
 * （已拥有不能领），领取记 totallogin_reward_log，7 星以上发小喇叭。客户端 signinpop.js 调用 index、action。
 * 当前时间取 BossClock（生产恒为 time()）。
 *
 * 与旧版的差别：领奖锁改为 Redis SET NX（旧版 get + setex，有并发窗口）。
 */
final class TotalLoginHandler
{
    public const string ACTIVITY = 'total_login';

    /** 领取条件：累计登录天数 */
    private const int DAY = 15;

    /** 领奖锁（旧版 _totallogin_lock{uid}，30 秒） */
    private const string LOCK_KEY = '_totallogin_lock';
    private const int LOCK_TTL = 30;

    private const array LANG = [
        'active_not_open' => '活动未开启！',
        'user_info_error' => '累计登陆天数不足！',
        'reward_info_error' => '获取奖励信息失败！',
        'fast_error' => '操作太快了！',
        'general_is_get' => '已经招募该武将！',
        'add_general_error' => '添加将领失败！',
        'general_get' => '恭喜玩家【%s】在累计登陆活动中获得%s星武将-%s',
    ];

    /**
     * 初始化: c=totallogin&m=index
     */
    public static function index(Context $ctx): array
    {
        /**
         * -1 活动未开启 / 奖励表错误
         *  0 失败
         *  1 成功
         */
        $return = ['status' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $activity = self::openActivity($now);
        if ($activity === null || $uid <= 0) {
            // 获取活动未开启
            $return['status'] = -1;
            $return['msg'] = self::LANG['active_not_open'];
            return $return;
        }
        $version = (int)$activity['version'];
        // 查看用户信息 返回用户累计登陆天数 和 领取按钮的状态
        $userData = self::memberInfo($uid, $version, $now);
        $status = 0;
        $day = (int)$userData['day'];
        if ($day >= self::DAY) {
            $status = 1;
        }
        if (self::rewardLogged($uid, $version)) {
            $status = 2;
        }
        $rewardInfo = self::rewardInfo();
        if ($rewardInfo === null) {
            // 奖励表错误
            $return['status'] = -1;
            $return['msg'] = self::LANG['reward_info_error'];
            return $return;
        }
        // 判断是否招募该武将
        if (self::ownsGeneral($uid, (int)$rewardInfo['value'])) {
            $status = 2;
        }
        $listArr = [];
        $general = GeneralTemplate::ROWS[(int)$rewardInfo['value']] ?? null;
        if ($general !== null) {
            $listArr['info'] = $general;
            $listArr['info']['talent'] = Talent::ROWS[$general['talentid']] ?? [];
            $listArr['info']['skill'] = Skill::ROWS[$general['skillid']] ?? [];
        }
        $return['status'] = 1;
        $return['day'] = $day;
        $return['is_get'] = $status;
        $return['list'] = $listArr;
        $return['time'] = date('m.d', (int)$activity['starttime']) . '~' . date('m.d', (int)$activity['endtime'] - 1);
        $return['total_login_bg'] = (int)$rewardInfo['total_login_bg'];
        return $return;
    }

    /**
     * 领取武将: c=totallogin&m=action
     */
    public static function action(Context $ctx): array
    {
        /**
         * -6 已经领取奖励
         * -5 操作太快了
         * -4 获取用户信息失败
         * -3 累计登陆天数不足 / 添加将领失败
         * -2 获取奖励信息失败
         * -1 活动未开启
         *  0 失败
         *  1 成功
         */
        $return = ['status' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        if ($uid <= 0) {
            return $return;
        }
        // 防止刷接口
        $redis = Redis::instance();
        if ($redis === null || !(bool)$redis->set(self::LOCK_KEY . $uid, '1', ['nx', 'ex' => self::LOCK_TTL])) {
            $return['status'] = -5;
            $return['msg'] = self::LANG['fast_error'];
            return $return;
        }
        $unlock = true;
        try {
            return self::claim($uid, $return, $unlock);
        } finally {
            if ($unlock) {
                $redis->del(self::LOCK_KEY . $uid);
            }
        }
    }

    /**
     * 每天第一次打开主城时累计登录天数（totallogin_model->update_day：没有记录时插入第 1 天）
     */
    public static function updateDay(int $uid, int $now): void
    {
        $activity = self::openActivity($now);
        if ($activity === null) {
            return;
        }
        $version = (int)$activity['version'];
        $info = DB::table('totallogin')->select('day', 'dateline')->where('version', $version)->where('uid', $uid)->whereNull('deleted_at')->first();
        if ($info === null) {
            // 第一次登陆添加一个数据
            self::memberInfo($uid, $version, $now);
            return;
        }
        // 今天第一次登陆：更新累计登陆次数与登陆时间（以旧的登陆时间条件更新，并发只加一次）
        if ((int)$info['dateline'] < strtotime(date('Y-m-d', $now))) {
            DB::table('totallogin')->where('uid', $uid)->where('version', $version)->where('dateline', (int)$info['dateline'])
                ->whereNull('deleted_at')->update(['day' => (int)$info['day'] + 1, 'dateline' => $now]);
        }
    }

    /**
     * @param array<string, mixed> $return
     * @return array<string, mixed>
     */
    private static function claim(int $uid, array $return, bool &$unlock): array
    {
        $now = BossClock::now();
        $activity = self::openActivity($now);
        if ($activity === null) {
            // 获取活动未开启
            $return['status'] = -1;
            $return['msg'] = self::LANG['active_not_open'];
            return $return;
        }
        $version = (int)$activity['version'];
        // 查看用户信息
        $userData = self::memberInfo($uid, $version, $now);
        if ((int)$userData['day'] < self::DAY) {
            // 累计登陆天数不足
            $return['status'] = -3;
            $return['msg'] = self::LANG['user_info_error'];
            return $return;
        }
        $rewardInfo = self::rewardInfo();
        if ($rewardInfo === null) {
            // 获取奖励信息失败
            $return['status'] = -2;
            $return['msg'] = self::LANG['reward_info_error'];
            return $return;
        }
        $generalid = (int)$rewardInfo['value'];
        if (self::rewardLogged($uid, $version) || self::ownsGeneral($uid, $generalid)) {
            // 已领取 / 已经招募该武将
            $return['status'] = -6;
            $return['msg'] = self::LANG['general_is_get'];
            return $return;
        }
        // 发武将（同一玩家的领取由领奖锁串行；已拥有时 addGeneral 不会重复发）
        $info = General::addGeneral($uid, $generalid);
        if ($info === null) {
            // 添加将领失败：旧版此处解的是另一个键（_general_lock），领奖锁要等 30 秒过期
            $unlock = false;
            $return['status'] = -3;
            $return['msg'] = self::LANG['add_general_error'];
            return $return;
        }
        PubLog::add($uid, $generalid, 1, 102, $now);
        unset($info['status']);
        $num = Chat::starWord((int)$info['star']);
        if ($num !== null) {
            $sender = Chat::sender($uid);
            Chat::sendToHorn(sprintf(self::LANG['general_get'], $sender['nickname'] ?? '', $num, $info['name']), $sender);
        }
        // 添加奖励日志
        DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_totallogin_reward_log` (`uid`, `version`, `dateline`) VALUES (:uid, :version, :now)',
            ['uid' => $uid, 'version' => $version, 'now' => $now]
        );
        $return['status'] = 1;
        $return['general'] = $info;
        $return['is_get'] = 2;
        return $return;
    }

    /**
     * 活动开启时的活动行（activity_status('total_login')）；未开启为 null
     *
     * @return array<string, int|string>|null
     */
    private static function openActivity(int $now): ?array
    {
        return ActivityClock::open(self::ACTIVITY, $now);
    }

    /**
     * 用户累计登录信息（get_member_info：没有记录时插入第 1 天）
     *
     * @return array{day: int, dateline: int}
     */
    private static function memberInfo(int $uid, int $version, int $now): array
    {
        $row = DB::table('totallogin')->select('day', 'dateline')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')->first();
        if ($row !== null) {
            return array_map('intval', $row);
        }
        DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_totallogin` (`uid`, `day`, `version`, `dateline`) VALUES (:uid, 1, :version, :now)',
            ['uid' => $uid, 'version' => $version, 'now' => $now]
        );
        return ['day' => 1, 'dateline' => $now];
    }

    private static function rewardLogged(int $uid, int $version): bool
    {
        return DB::table('totallogin_reward_log')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')->value('id') !== null;
    }

    /**
     * 奖励信息（get_reward_info：奖励表第一行）
     *
     * @return array<string, int>|null
     */
    private static function rewardInfo(): ?array
    {
        $row = array_values(TotalloginReward::ROWS)[0] ?? null;
        return $row;
    }

    private static function ownsGeneral(int $uid, int $generalid): bool
    {
        return DB::table('general')->where('uid', $uid)->where('generalid', $generalid)->whereNull('deleted_at')->value('id') !== null;
    }
}
