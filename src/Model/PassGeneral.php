<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use Hanjiang\Handler\Activity\MessageHandler;
use Hanjiang\Handler\General\TavernHandler;
use Hanjiang\MasterData\ActivityTime;
use Hanjiang\MasterData\Equip;
use Hanjiang\MasterData\EquipEssence;
use Hanjiang\MasterData\PassgeneralBoxReward;
use Hanjiang\MasterData\PassgeneralInfo;
use Hanjiang\MasterData\Pub;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;

/**
 * 过关斩将共用逻辑（移植旧版 models/passgeneral_model.php，以及 controllers/pass_general.php 与
 * controllers/cron_acttask.php 中两边重复的 rand_arr / get_reward_item / 每日清理，路径相对旧版 s1/application/）
 *
 * 玩家存储：passgeneral（每人每个活动版本一行）、passgeneral_log（过人日志）、passgeneral_box_log（宝箱领取日志）、
 * passgeneral_achievement_log（成就领取日志）、passgeneral_exchange_log（兑奖日志）、
 * member_integral.act_kemari_int（兑奖券，与蹴鞠活动共用）。
 */
final class PassGeneral
{
    /** 活动名（activity_time.name） */
    public const string ACTIVITY = 'pass_general';

    /** 每层每日免费过人次数（init_pass_general / init_user_pass_general） */
    public const int FREE_TIMES = 3;

    /** 将领已拥有时折算紫宝石的折扣（$_rebate / get_reward_item 写死 0.8） */
    public const float REBATE = 0.8;

    /** 系统信件（message_model->send_message type 1，发件人 message_system_title） */
    private const int MESSAGE_TYPE_SYSTEM = 1;
    private const string LANG_MESSAGE_SYSTEM_TITLE = '系统通知';

    /** 旧版语言包 cron_lang.php */
    public const string LANG_CRON_AUTOPASS_GENERAL_GET = '亲爱的玩家！您今天未领取的过关斩将奖励，系统已自动领取，共获得：';
    public const string LANG_CRON_CHECK = '请查收!';
    public const string LANG_CRON_PASSGENERAL = '亲爱的玩家！过关斩将活动已结束，您未领取的成就奖励已自动帮您领取，共获得：';
    public const string LANG_CRON_PASSGENERALFINISH_GET1 = '过关斩将活动已结束，您未兑换的兑奖券，系统已自动兑换，';
    public const string LANG_CRON_PASSGENERALFINISH_GET2 = '兑换';
    public const string LANG_CRON_SILVER = '银币';
    public const string LANG_CRON_GE = '个，';
    private const array LANG_CRON_ITEM = [
        1 => '元宝',
        2 => '银币',
        3 => '黄宝石',
        4 => '紫宝石',
        5 => '将魂',
        6 => '玉石',
        7 => '声望',
        9 => '宝石碎片',
        10 => '突飞卡',
        11 => '蓝宝石',
        14 => '精炼石',
        15 => '五彩石',
        16 => '上古血石',
        17 => '白色武器精华',
        18 => '白色防具精华',
        19 => '白色饰品精华',
        20 => '绿色武器精华',
        21 => '绿色防具精华',
        22 => '绿色饰品精华',
        23 => '蓝色武器精华',
        24 => '蓝色防具精华',
        25 => '蓝色饰品精华',
        27 => '兑奖券',
    ];
    private const string LANG_CRON_EQUIP = '装备-';
    private const string LANG_CRON_CHANGE_PURPLE = '武将兑换紫宝石';
    private const string LANG_CRON_GENERAL = '武将-';

    /** 装备精华奖励类型 → 精华模板 id（add_reward / get_reward_item case 17 ~ 25） */
    public const array ESSENCE_ID = [17 => 1, 18 => 7, 19 => 13, 20 => 2, 21 => 8, 22 => 14, 23 => 3, 24 => 9, 25 => 15];

    /** 旧版 zc_sanguo_passgeneral 的字段（不带新版的三个时间字段） */
    private const array COLUMNS = ['id', 'uid', 'version', 'num', 'free_times1', 'free_times2', 'normal_time1', 'normal_time2', 'pass_id', 'is_pass', 'succ_num', 'is_get', 'dateline', 'refresh_time'];

    /** 零点初始化的字段（init_user_pass_general） */
    private const array RESET_DATA = [
        'num' => 0,
        'pass_id' => 1,
        'is_pass' => 0,
        'is_get' => 1,
        'free_times1' => self::FREE_TIMES,
        'normal_time1' => 0,
    ];

    /**
     * 活动信息（activity_model->get_info_by_name：activity_time 按名字取一行）
     *
     * @return array{id: int, name: string, starttime: int, endtime: int, version: int, type: int}|null
     */
    public static function activity(): ?array
    {
        foreach (ActivityTime::ROWS as $row) {
            if ($row['name'] === self::ACTIVITY) {
                return $row;
            }
        }
        return null;
    }

    /**
     * 活动是否开启（activity_model->activity_status：starttime <= 当前 <= endtime）
     */
    public static function isOpen(int $now): bool
    {
        $info = self::activity();
        return $info !== null && $info['starttime'] <= $now && $info['endtime'] >= $now;
    }

    /**
     * 玩家过人信息（get_passgeneral_info；$init 为 true 时没有记录就初始化，即旧版 $action == 'index'）
     *
     * @return array<string, int>|null
     */
    public static function getInfo(int $uid, int $version, bool $init = false, ?int $now = null): ?array
    {
        $row = DB::table('passgeneral')->select(...self::COLUMNS)
            ->where('version', $version)->where('uid', $uid)->whereNull('deleted_at')->first();
        if ($row !== null) {
            return array_map('intval', $row);
        }
        if (!$init) {
            return null;
        }
        // 初始化过门信息（init_pass_general；唯一键 uid + version，并发首次请求只插入一行）
        DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_passgeneral` (`uid`, `version`, `num`, `free_times1`, `free_times2`, `normal_time1`, `normal_time2`, `pass_id`, `is_pass`, `succ_num`, `is_get`, `dateline`)
             VALUES (:uid, :version, 0, :free, 0, 0, 0, 1, 0, 0, 1, :now)',
            ['uid' => $uid, 'version' => $version, 'free' => self::FREE_TIMES, 'now' => $now ?? time()]
        );
        return self::getInfo($uid, $version);
    }

    /**
     * 更新玩家过关斩将信息（update_passgeneral：写入成功返回 1）
     *
     * @param array<string, int> $data
     */
    public static function update(int $uid, int $version, array $data): int
    {
        return DB::table('passgeneral')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')->update($data) > 0 ? 1 : 0;
    }

    /**
     * 最高层数（get_passgeneral_num：MAX(num)）
     */
    public static function maxNum(): int
    {
        return max(array_column(PassgeneralInfo::ROWS, 'num'));
    }

    /**
     * 最后一个过人 id（get_passgeneral_last）
     */
    public static function lastId(): int
    {
        return max(array_keys(PassgeneralInfo::ROWS));
    }

    /**
     * 每层宝箱奖励（get_passgeneral_box_reward：num = 层数）
     *
     * @return list<array<string, int|float>>
     */
    public static function boxReward(int $num): array
    {
        $return = [];
        foreach (PassgeneralBoxReward::ROWS as $row) {
            if ($row['num'] === $num) {
                $return[] = $row;
            }
        }
        return $return;
    }

    /**
     * 奖品随机计算（rand_arr，控制器与计划任务两份相同）
     *
     * 旧版按权重 × 10 划分区间 [k + 1, rate × 10 + k]，随机数需「大于」区间下限才命中（下限本身落空）；
     * 最多掷 count($arr) 次，凑够 $num 个就停，都没命中时返回空。mt_rand 的浮点上限按 PHP 5 截断取整。
     *
     * @param list<array<string, mixed>> $arr
     * @return array{info: list<array<string, mixed>>}
     */
    public static function randArr(array $arr, float $totalNum, int $num, string $rate = 'rate', int $state = 1): array
    {
        $newReturn = [];
        // 重置数组顺序（旧版 sort 二维数组：各行字段相同，按首字段 id 排序）
        sort($arr);
        // 区间计算
        $k = 0;
        foreach ($arr as &$value) {
            $value['rate_in'] = [$k + 1, $value[$rate] * 10 + $k];
            $k += $value[$rate] * 10;
        }
        unset($value);

        // 随机值计算
        $arrCount = count($arr);
        for ($i = $state; $i <= $arrCount; $i++) {
            $randNum = mt_rand($state, (int)($totalNum * 10));
            foreach ($arr as $val) {
                if ($randNum > $val['rate_in'][0] && $randNum <= $val['rate_in'][1]) {
                    // 达到需求数量自动退出
                    if (count($newReturn) >= $num) {
                        break;
                    }
                    $newReturn[] = $val;
                }
            }
        }
        return ['info' => $newReturn];
    }

    /**
     * 每日清理一个玩家的过关斩将信息（控制器 _update_pass_general_info，每次请求本控制器时执行）
     *
     * 刷新时间不是今天时：有未领取的宝箱奖励（is_get = 2）就自动发放并发信，然后重置层数 / 过人进度 / 免费次数、
     * 记录刷新时间。以刷新时间做比较交换，多 Worker 并发请求只执行一次。
     */
    public static function dailyUpdate(int $uid, int $version, int $now, string $class = '', string $method = ''): void
    {
        $userData = self::getInfo($uid, $version);
        if ($userData === null || date('Ymd', $now) === date('Ymd', $userData['refresh_time'])) {
            return;
        }
        // 重置免费次数并更新刷新时间（旧版先发奖再重置；这里先以刷新时间抢占，抢到的请求才发奖）
        $claimed = DB::table('passgeneral')->where('id', $userData['id'])->where('refresh_time', $userData['refresh_time'])
            ->whereNull('deleted_at')->update(self::RESET_DATA + ['refresh_time' => $now]);
        if ($claimed <= 0 || $userData['is_get'] !== 2) {
            return;
        }
        // 如果有未领取的奖励 自动发放给玩家
        self::autoBoxReward($userData, $version, $now, $class, $method);
    }

    /**
     * 自动发放未领取的宝箱奖励并发信（_update_pass_general_info / cron_acttask::update_pass_general_info 的循环体）
     *
     * 旧版计划任务按循环计数发放 $new_reward[$i - 1]，而每层宝箱只有一行，第二个玩家起取不到奖励；
     * 开发者要求修正：每个玩家都发放本层奖励 $new_reward[0]。
     *
     * @param array<string, int> $userData 发放前的玩家记录
     * @param string $class / $method 物品流水的执行类与方法（旧版取当前请求）
     */
    public static function autoBoxReward(array $userData, int $version, int $now, string $class = '', string $method = ''): void
    {
        $uid = $userData['uid'];
        // 获取奖励
        $rewardInfo = self::boxReward($userData['num'] + 1);
        // 获取最高层数是否到可领取超级大奖
        $id = $userData['num'] + 1;
        if ($id === self::maxNum()) {
            $rateTotal = 0;
            foreach ($rewardInfo as $value) {
                $rateTotal += $value['rate'];
            }
            $newReward = self::randArr($rewardInfo, $rateTotal, 1)['info']; // 随机获取奖励
        } else {
            $newReward = $rewardInfo;
        }

        $message = self::LANG_CRON_AUTOPASS_GENERAL_GET;
        // 发放奖励
        for ($ii = 1; $ii <= 3; $ii++) {
            if (isset($newReward[0]['type' . $ii]) && $newReward[0]['type' . $ii] !== 0) {
                $message .= self::getRewardItem((int)$newReward[0]['type' . $ii], (int)$newReward[0]['value' . $ii], $uid, $now, $class, $method);
            }
        }

        // 添加领取日志（旧版只写层数与三个类型；超级大奖没掷中时类型为空，按 0 记）
        self::addBoxLog($uid, [
            'version' => $version,
            'num' => $userData['num'],
            'type1' => (int)($newReward[0]['type1'] ?? 0),
            'type2' => (int)($newReward[0]['type2'] ?? 0),
            'type3' => (int)($newReward[0]['type3'] ?? 0),
        ], $now);

        $message .= self::LANG_CRON_CHECK;
        // 发信
        self::sendSystemMessage($uid, $message);
    }

    /**
     * 零点初始化一批玩家的过关斩将信息（init_user_pass_general；计划任务用，不更新刷新时间）
     */
    public static function resetVersion(int $version): int
    {
        return DB::table('passgeneral')->where('version', $version)->whereNull('deleted_at')->update(self::RESET_DATA);
    }

    /**
     * 抢占一个待自动领取宝箱的玩家（计划任务：与玩家手动领取 / 请求时的每日清理并发时只发一次）
     *
     * @param array<string, int> $userData
     */
    public static function claimBox(array $userData): bool
    {
        return DB::table('passgeneral')->where('id', $userData['id'])->where('is_get', 2)->where('num', $userData['num'])
            ->whereNull('deleted_at')->update(self::RESET_DATA) > 0;
    }

    /**
     * 奖励信息处理，并且向玩家发送信件提示（get_reward_item，控制器与计划任务两份相同；返回信件里的奖励描述）
     *
     * 旧版照搬：两个 case '12' 只有第一个（武将）生效，军令（13）、锻造材料（28）不处理；
     * 新获得武将时信件拼 $general['info']['name']（add_general 返回值没有 info，名字为空）。
     */
    public static function getRewardItem(int $type, int $num, int $uid, int $now, string $class = '', string $method = ''): string
    {
        $message = '';
        $item = self::LANG_CRON_ITEM[$type] ?? '';
        switch ($type) {
            case 1: // 元宝
                Member::addCurrency($uid, gold: $num);
                $message .= $item . $num . self::LANG_CRON_GE;
                break;
            case 2: // 银币
                Member::addCurrency($uid, silver: $num);
                $message .= $item . $num . self::LANG_CRON_GE;
                break;
            case 3: // 黄宝石
                TavernHandler::addJade($uid, 2, $num, $class, $method);
                $message .= $item . $num . self::LANG_CRON_GE;
                break;
            case 4: // 紫宝石
                TavernHandler::addJade($uid, 3, $num, $class, $method);
                $message .= $item . $num . self::LANG_CRON_GE;
                break;
            case 5: // 原魂
                Member::addCurrency($uid, soul: $num);
                $message .= $item . $num . self::LANG_CRON_GE;
                break;
            case 6: // 玉石
                MemberJade::add($uid, $num, $class, $method, $now);
                $message .= $item . $num . self::LANG_CRON_GE;
                break;
            case 7: // 声望
                Member::addCurrency($uid, reputation: $num);
                $message .= $item . $num . self::LANG_CRON_GE;
                break;
            case 8: // 装备
                $equip = Equip::ROWS[$num] ?? null;
                if ($equip !== null) {
                    Equipment::addFromTemplate($uid, $num);
                }
                $message .= self::LANG_CRON_EQUIP . ($equip['name'] ?? '') . '，';
                break;
            case 9: // 宝石碎片
                OwnGem::addChip($uid, $num, $class, $method, $now);
                $message .= $item . $num . self::LANG_CRON_GE;
                break;
            case 10: // 突飞卡
                self::addFreeleap($uid, $num);
                $message .= $item . $num . self::LANG_CRON_GE;
                break;
            case 11: // 蓝宝石
                TavernHandler::addJade($uid, 1, $num, $class, $method);
                $message .= $item . $num . self::LANG_CRON_GE;
                break;
            case 12: // 武将
            case 26: // 神将
                $gid = $num;
                // 查询自己是否已经获得武将
                if (self::ownsGeneral($uid, $gid)) {
                    // 查询武将的紫宝石价格信息
                    $pub = Pub::ROWS[$gid] ?? null;
                    if ($pub !== null) {
                        $jadenum = (int)$pub['price'] * self::REBATE;
                        TavernHandler::addJade($uid, 3, (int)round($jadenum), $class, $method);
                        $message .= self::LANG_CRON_CHANGE_PURPLE . $jadenum . self::LANG_CRON_GE;
                    }
                } elseif (General::createFromTemplate($uid, $gid) !== null) {
                    $message .= self::LANG_CRON_GENERAL;
                    // 酒馆获得日志（tavern_model->add_pub_log(gid, 1, 11, uid)）
                    PubLog::add($uid, $gid, 1, 11, $now);
                }
                break;
            case 14: // 精炼石
            case 15: // 五彩石
            case 16: // 上古血石（member_model->add_stone 类型 1 / 2 / 3，存 member 三列）
                MemberStone::add($uid, $num, $type - 13, $class, $method, $now);
                $message .= $item . $num . self::LANG_CRON_GE;
                break;
            case 17: case 18: case 19: case 20: case 21: case 22: case 23: case 24: case 25: // 装备精华
                if (isset(EquipEssence::ROWS[self::ESSENCE_ID[$type]])) {
                    Equipment::addTowerItem($uid, self::ESSENCE_ID[$type], $num);
                    $message .= $item . $num . self::LANG_CRON_GE;
                }
                break;
            case 27: // 兑奖券（actkemari_model：不记积分日志）
                self::addLottery($uid, $num);
                $message .= $item . $num . self::LANG_CRON_GE;
                break;
        }
        return $message;
    }

    /**
     * 我的兑奖券（get_lottery_info：没有记录时初始化为 0）
     */
    public static function lotteryInfo(int $uid): int
    {
        $value = DB::table('member_integral')->where('uid', $uid)->whereNull('deleted_at')->value('act_kemari_int');
        if ($value !== null) {
            return (int)$value;
        }
        // 初始化兑奖券（init_lottery；唯一键 uid）
        DB::execute('INSERT IGNORE INTO `zc_sanguo_member_integral` (`uid`, `act_kemari_int`) VALUES (:uid, 0)', ['uid' => $uid]);
        return 0;
    }

    /**
     * 增加兑奖券（旧版先读后写绝对值；这里原子累加）
     *
     * @return int 增加后的兑奖券
     */
    public static function addLottery(int $uid, int $num): int
    {
        self::lotteryInfo($uid);
        DB::execute(
            'UPDATE `zc_sanguo_member_integral` SET `act_kemari_int` = `act_kemari_int` + :num WHERE `uid` = :uid AND `deleted_at` IS NULL',
            ['num' => $num, 'uid' => $uid]
        );
        return self::lotteryInfo($uid);
    }

    /**
     * 积分变化记录（member_model->add_memint_log：class / method 为当前控制器与方法）
     */
    public static function addMemintLog(int $uid, int $type, int $intnum, int $balance, string $method, int $now): void
    {
        DB::table('integral_log')->insert([
            'uid' => $uid,
            'type' => $type,
            'intnum' => $intnum,
            'balance' => $balance,
            'class' => self::ACTIVITY,
            'method' => $method,
            'argument' => 'act_kemari_int',
            'dateline' => $now,
        ]);
    }

    /**
     * 添加领取宝箱奖励日志（add_reward_log → passgeneral_box_log）
     *
     * @param array<string, int> $data
     */
    public static function addBoxLog(int $uid, array $data, int $now): int
    {
        $data['uid'] = $uid;
        $data['dateline'] = $now;
        return DB::table('passgeneral_box_log')->insert($data) !== false ? 1 : 0;
    }

    /**
     * 添加领取成就奖励日志（add_achievement_log；唯一键 uid + version + achieve_id，已有记录时不插入返回 0）
     */
    public static function addAchievementLog(int $uid, int $version, int $achieveId, int $now): int
    {
        return DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_passgeneral_achievement_log` (`uid`, `version`, `achieve_id`, `dateline`) VALUES (:uid, :version, :aid, :now)',
            ['uid' => $uid, 'version' => $version, 'aid' => (string)$achieveId, 'now' => $now]
        ) > 0 ? 1 : 0;
    }

    /**
     * 增加突飞卡（tavern_model->add_freeleap：member_field.freeleap）
     */
    public static function addFreeleap(int $uid, int $num): void
    {
        FreeLeap::add($uid, $num);
        Redis::del('player:' . $uid);
    }

    public static function ownsGeneral(int $uid, int $generalid): bool
    {
        return DB::table('general')->where('uid', $uid)->where('generalid', $generalid)->whereNull('deleted_at')->first() !== null;
    }

    public static function sendSystemMessage(int $uid, string $message): void
    {
        MessageHandler::sendSystemMessage([$uid], self::MESSAGE_TYPE_SYSTEM, self::LANG_MESSAGE_SYSTEM_TITLE, $message);
    }
}
