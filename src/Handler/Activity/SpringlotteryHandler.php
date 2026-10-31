<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\MasterData\Equip;
use Hanjiang\MasterData\EquipEssence;
use Hanjiang\MasterData\General as GeneralTemplate;
use Hanjiang\MasterData\MountTemp;
use Hanjiang\MasterData\Skill;
use Hanjiang\MasterData\SpringLottery;
use Hanjiang\MasterData\SpringLotteryReward;
use Hanjiang\MasterData\SpringNeedgold;
use Hanjiang\MasterData\Talent;
use Hanjiang\Model\ActivityClock;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\Chat;
use Hanjiang\Model\Equipment;
use Hanjiang\Model\General;
use Hanjiang\Model\Member;
use Hanjiang\Model\OwnMaterials;
use Hanjiang\Model\OwnMount;
use Hanjiang\Model\PubLog;
use Hanjiang\Model\SpringReward;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 春节转盘抽奖（移植旧版 controllers/springlottery.php + models/springlottery_model.php）
 *
 * 活动时间取 ActivityTime 的 springlottery（旧版数据 starttime = endtime = 0，从未开放）；活动结束后图标多挂一天，抽奖到
 * endtime - 1 天为止（_end），活动第几天 _day = ceil((当前 - starttime) / 86400)。转盘取 MasterData SpringLottery（group 1 普通、
 * group 2 至尊），每次取出后打乱顺序；每天第一次免费，之后按 spring_costgold 的价格扣元宝（每天从 50 起，每抽一次按
 * SpringNeedgold 涨到下一档，最高 200）。每第 6、12、18 … 次（spring_extend 的 times + 序号 + 1）是免费的至尊转盘。
 * 每天的抽奖次数记 spring_lottery_ranklog，VIP 2 以上的前 20 名次日可领 SpringLotteryReward（get_reward，领昨天的）。
 * 客户端 newyearlotterypop.js 调用 index、action、get_reward。当前时间取 BossClock（生产恒为 time()）。
 *
 * spring_extend：旧版是会被 judge_times 补行的表（行 k 的 times 为 5k，抽奖次数到 5 的倍数且 times + 5 不存在时插一行）。
 * MasterData SpringExtend 的 293 行正是这个规律，judge_times 补的行也按同一规律，所以这里按规律计算：第 n 次抽奖是至尊转盘
 * 当且仅当 n 是 6 的倍数（times + 序号 = 6k）；距离至尊转盘的次数为 min(6k - 1 - n ≥ 0)。不建表、不补行。
 *
 * 照旧版：action 在活动结束（_end）后回 status 0（活动未开启时 _end 为 0，同样回 0）；get_reward 不判断活动是否开启
 * （未开启时 _day 为 1，回 -1）；排行与名次只算 VIP 2 以上的记录（自己 VIP 不足时名次为排在自己前面的人数）；
 * 23:55 以后一秒内的第二次抽奖回 -2 且不解锁；get_reward 的排行奖励银币类型旧版先按等级乘过一次、发放时再乘一次。
 * 旧版 copies_model->get_materials_info_by_arr 不在旧版代码包里（转盘的锻造材料奖品在 index / action 里一定会调用它，
 * 旧版代码包里的版本会因方法不存在而中断）；这里按调用方还原：按材料 id 取 MaterialsTemp 行（info 以材料 id 为键）。
 * 抽奖记录 spring_lottery_info_log.getvalue 旧版对坐骑 / 武将写入的是数组（int 列存成 0），这里记 0。
 * 与旧版的差别：抽奖锁 / 领奖锁改为 Redis SET NX（旧版 get + setex，有并发窗口）；领奖以 get = 0 条件更新抢占（并发只发一次）；
 * 转盘权重 getrate × 100 取整后再累加（旧版浮点累加，结果相同）；武将奖品旧版取 add_general 返回值里不存在的 info 去查天赋 /
 * 技能（SQL 出错中断），当前数据没有武将奖品，这里按武将模板取。
 */
final class SpringlotteryHandler
{
    public const string ACTIVITY = 'springlottery';

    /** 用户排行的人数（_max_rank） */
    private const int MAX_RANK = 20;
    /** VIP 限制排行（_vip / _rank_vip） */
    private const int RANK_VIP = 2;
    /** 主页面一共显示的用户数量（_limit） */
    private const int LIMIT = 20;
    /** 不免费抽奖时最低消耗的元宝数 / 最高花费元宝限制 */
    private const int MIN_NUM = 50;
    private const int MAX_NUM = 200;
    /** 玩家一直保持着 5 次到达至尊转盘（last_num；spring_extend 的 times 步长） */
    private const int LAST_NUM = 5;
    /** 抽奖锁 */
    private const string LOCK_KEY = 'spring_lottery_lock_';
    private const int LOCK_TTL = 30;
    /** 23:55 之后转盘一秒钟才能请求一次 */
    private const string SPRING_KEY = 'spring_key_';

    /** spring_lang.php */
    private const array LANG = [
        'spring_lottery_error1' => '活动未开启',
        'spring_lottery_error3' => '获取用户记录失败',
        'spring_lottery_error7' => '您慢点抽奖',
        'spring_lottery_error10' => '元宝不足',
        'spring_lottery_error12' => '今天不能领取奖励',
        'spring_lottery_error13' => '您已经领取过奖励了',
        'general_get' => '恭喜玩家【%s】获得%s星武将-%s',
    ];

    /**
     * 取抽奖页面信息: c=springlottery&m=index
     *
     * status：2 活动未开启；-2 获取用户记录失败；1 成功
     */
    public static function index(Context $ctx): array
    {
        $now = BossClock::now();
        $member = self::member(MemberHandler::resolveUid($ctx));
        $act = self::activity($now);
        $return = ['status' => 0, 'end' => 0, 'vip' => 0];
        if ($now > $act['end']) {
            $return['end'] = 1;
        }
        if ($member !== null && $member['vip'] < self::RANK_VIP) {
            $return['vip'] = self::RANK_VIP;
        }
        // 判断活动是否结束
        if ($act['open'] === 0 || $member === null) {
            $return['status'] = 2;
            $return['msg'] = self::LANG['spring_lottery_error1'];
            return $return;
        }
        $uid = $member['uid'];
        $version = $act['version'];
        $day = $act['day'];
        // 取用户记录
        $log = self::lotteryLog($member, $version, $now);
        if ($log === []) {
            $return['status'] = -2;
            $return['msg'] = self::LANG['spring_lottery_error3'];
            return $return;
        }
        // 主页面显示还剩多少次到达至尊转盘的信息
        $return['times'] = self::judgeTimes($log['buy_lavenumber']);
        // 记录一个是免费抽奖还是元宝抽奖的数字(1:免费抽奖0:元宝抽奖)
        $return['lottery'] = $log['lavenumber'];
        // 倒计时时间显示
        $return['cd'] = strtotime(date('Ymd', $now)) + 86400 - $now;
        // 取到今日玩家排名
        $return['userlist'] = self::userRank($version, $day);

        if ($day <= 1) {
            // 玩家今日排名
            $today = self::myRank($uid, $version, $day, 1);
            $return = array_merge($return, $today <= 0 || $today > self::MAX_RANK
                ? self::unranked($member['vip'], false)
                : self::ranked($today, $member, 1));
        } else {
            // 玩家昨日排名 / 今日排名
            $yesterday = self::myRank($uid, $version, $day, 2);
            $today = self::myRank($uid, $version, $day, 1);
            if ($yesterday <= 0 || $yesterday > self::MAX_RANK) {
                // 昨天没抽奖 / 昨日没有排上名，就显示今日的
                $return = array_merge($return, $today <= 0 || $today > self::MAX_RANK
                    ? self::unranked($member['vip'], false)
                    : self::ranked($today, $member, 1));
            } else {
                // 昨日排上榜了，先判断是否已经领取过奖励
                $myGet = self::myTimes($uid, $version, $day, 2);
                if ((int)($myGet['get'] ?? 0) === 0) {
                    // 表示还未领取奖励，右侧显示昨天的排名和奖励，左侧显示奖品图案
                    $return = array_merge($return, self::ranked($yesterday, $member, 0));
                } else {
                    // 表示已经领取过奖励
                    $return = array_merge($return, $today <= 0 || $today > self::MAX_RANK
                        ? self::unranked($member['vip'], false)
                        : self::ranked($today, $member, 1));
                }
            }
        }

        // 合并用户排名+奖励
        $return['userlist'] = self::mergeReward($return['userlist'], $member['level'], true);

        $buy = $log['buy_lavenumber'];
        if (self::isExtreme($buy + 2)) {
            // 开启至尊转盘的背影颜色
            $return['extreme'] = 1;
        }
        if (self::isExtreme($buy + 1)) {
            // 开启至尊转盘
            $list = self::lotteryInfo(2);
            $return['lottery'] = 1;
        } else {
            // 开启普通转盘
            $list = self::lotteryInfo(1);
            $return['extreme'] = 0;
        }
        foreach ($list as $key => $val) {
            unset($list[$key]['getrate']);
        }
        $return['list'] = self::decorate($list, $member['level']);
        $return['status'] = 1;
        // 下次抽奖需要消耗的元宝数
        $return['time'] = date('n.d', $act['start']) . '~' . date('n.d', $act['end'] - 100);
        $return['costgold'] = self::costGold($uid);
        return $return;
    }

    /**
     * 抽奖: c=springlottery&m=action（每天第一次免费，每日 0 点刷新免费次数，免费次数不累加）
     *
     * status：0 活动已结束；2 活动未开启；-2 请求过快；-5 没有用户记录；-7 元宝不足；1 成功
     */
    public static function action(Context $ctx): array
    {
        $now = BossClock::now();
        $member = self::member(MemberHandler::resolveUid($ctx));
        $act = self::activity($now);
        // 先设定状态值（extreme 0 为普通转盘，1 为至尊转盘）
        $return = ['status' => 0, 'extreme' => 0, 'vip' => 0];
        if ($now > $act['end']) {
            $return['msg'] = self::LANG['spring_lottery_error1'];
            return $return;
        }
        if ($member !== null && $member['vip'] < self::RANK_VIP) {
            $return['vip'] = self::RANK_VIP;
        }
        // 判断活动是否结束
        if ($act['open'] === 0 || $member === null) {
            $return['status'] = 2;
            $return['msg'] = self::LANG['spring_lottery_error1'];
            return $return;
        }
        $uid = $member['uid'];
        $version = $act['version'];
        $day = $act['day'];
        // 防止刷接口
        if (!self::lock($uid)) {
            $return['status'] = -2;
            $return['msg'] = self::LANG['spring_lottery_error7'];
            return $return;
        }
        // 23:55之后转盘一秒钟才能请求一次（旧版此处返回时不解锁）
        if ((int)date('Hi', $now) > 2355) {
            if (!(bool)Redis::instance()->set(self::SPRING_KEY . $uid, 1, ['nx', 'ex' => 1])) {
                $return['status'] = -2;
                $return['msg'] = self::LANG['spring_lottery_error7'];
                return $return;
            }
        }

        // 判断玩家今天是否抽奖：添加今天的抽奖数
        if (self::myTimes($uid, $version, $day, 1) === []) {
            self::insertRanklog($member, $day, $version);
        }
        // 取用户记录
        $data = self::lotteryLog($member, $version, $now);
        if ($data === []) {
            $return['status'] = -5;
            self::unlock($uid);
            return $return;
        }
        // 记录一个是免费抽奖还是元宝抽奖的数字(1:免费抽奖0:元宝抽奖)
        $return['lottery'] = $data['lavenumber'];
        $extreme = self::isExtreme($data['buy_lavenumber'] + 1);
        $gold = 0;
        // 已经免费抽奖过一次（开始使用元宝抽奖，最低50）
        if ($data['lavenumber'] === 0 && !$extreme) {
            // 查找用户需要消耗的元宝数
            $info = self::costRow($uid);
            if ($info === null) {
                self::addCostGold($uid);
                $gold = self::MIN_NUM;
            } else {
                $gold = (int)$info['gold'];
            }
            // 消耗元宝前查看现有元宝数是否足够
            if (!Member::deductCurrency($uid, gold: $gold)) {
                // 元宝不足
                $return['status'] = -7;
                self::unlock($uid);
                $return['msg'] = self::LANG['spring_lottery_error10'];
                return $return;
            }
            // 消耗完元宝更新下次所消费的元宝价格
            DB::table('spring_costgold')->where('uid', $uid)->whereNull('deleted_at')->update(['gold' => self::needGold($gold)]);
        }
        // 今天的抽奖次数 + 1（同时记当前 VIP）
        DB::execute(
            'UPDATE `zc_sanguo_spring_lottery_ranklog` SET `times` = `times` + 1, `vip` = :vip
             WHERE `uid` = :uid AND `version` = :version AND `day` = :day AND `deleted_at` IS NULL',
            ['vip' => $member['vip'], 'uid' => $uid, 'version' => $version, 'day' => $day]
        );

        if ($extreme) {
            // 开启免费的至尊转盘（只增加抽奖次数，不改变时间）
            $list = self::lotteryInfo(2);
            self::buyLotteryNum($member, $version, $now, false);
        } else {
            $list = self::lotteryInfo(1);
            // 抽奖一次则相应向该用户lottery_log添加一次记录
            self::buyLotteryNum($member, $version, $now, true);
            if ($data['lavenumber'] > 0) {
                // 消耗免费抽奖次数
                DB::table('spring_lottery_log')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')->update(['lavenumber' => 0]);
                // 返回下一次是否免费，需扣除本次免费次数
                $return['lottery'] = $data['lavenumber'] - 1;
            }
        }

        // 去掉已拥有的武将；VIP 5 以下不出装备、坐骑
        $rate = 0;
        foreach ($list as $key => $val) {
            $weight = (int)round($val['getrate'] * 100);
            $rate += $weight;
            if ($val['gettype'] === 13 && General::ownsTemplate($uid, $val['getvalue'])) {
                unset($list[$key]);
                $rate -= $weight;
            }
            if ($member['vip'] < 5 && ($val['gettype'] === 8 || $val['gettype'] === 11)) {
                unset($list[$key]);
                $rate -= $weight;
            }
        }
        $getinfo = null;
        if ($rate > 0) {
            $rand = mt_rand(1, $rate);
            $cursor = 0;
            foreach ($list as $val) {
                $cursor += (int)round($val['getrate'] * 100);
                if ($rand <= $cursor) {
                    $getinfo = $val;
                    break;
                }
            }
        }
        if ($getinfo === null) {
            // 旧版 $getinfo 未定义（转盘全部被排除时），不发奖励
            $getinfo = ['id' => 0, 'group' => 0, 'gettype' => 0, 'getvalue' => 0, 'getvalue1' => 0];
        }

        // 主页面显示还剩多少次到达至尊转盘的信息
        $return['times'] = self::judgeTimes($data['buy_lavenumber'] + 1);

        // 发奖
        $sender = null;
        $logValue = $getinfo['getvalue'];
        switch ($getinfo['gettype']) {
            case 2:
                // 添加银币（银币 × 主公等级）
                $getinfo['getvalue'] = $getinfo['getvalue'] * $member['level'];
                Member::addCurrency($uid, silver: $getinfo['getvalue']);
                $logValue = $getinfo['getvalue'];
                break;
            case 8:
                // 添加装备
                $item = Equip::ROWS[$getinfo['getvalue']] ?? null;
                if ($item !== null) {
                    Equipment::addFromTemplate($uid, $getinfo['getvalue']);
                }
                $getinfo['getvalue'] = $item ?? [];
                // 发送广播到聊天室和小喇叭
                $name = Chat::quality($item['quality'] ?? '', (string)($item['name'] ?? ''));
                $sender = Chat::sender($uid);
                Chat::sendToAll(Chat::getMessageStr(32, ['springequipment' => $name], $sender), Chat::TYPE_SYSTEM, 0, 0, $sender);
                Chat::sendToHorn(sprintf(Chat::LANG['chat_springequipment_get'], $member['nickname'], $name), $sender);
                break;
            case 11:
                // 添加坐骑
                $mountInfo = OwnMount::addMount($uid, $getinfo['getvalue'], 0, 1, 'springlottery', 'action', $now)['info'] ?? [];
                $getinfo['getvalue'] = $mountInfo;
                $logValue = 0;
                $name = Chat::quality($mountInfo['quality'] ?? '', (string)($mountInfo['name'] ?? ''));
                $sender = Chat::sender($uid);
                Chat::sendToAll(Chat::getMessageStr(31, ['springmount' => $name], $sender), Chat::TYPE_SYSTEM, 0, 0, $sender);
                Chat::sendToHorn(sprintf(Chat::LANG['chat_springmount_get'], $member['nickname'], $name), $sender);
                break;
            case 13:
                // 添加武将
                $generalid = $getinfo['getvalue'];
                $general = General::addGeneral($uid, $generalid);
                if ($general !== null) {
                    $sender = Chat::sender($uid);
                    $num = Chat::starWord((int)$general['star']);
                    if ($num !== null) {
                        Chat::sendToHorn(sprintf(self::LANG['general_get'], $member['nickname'], $num, $general['name']), $sender);
                    }
                    Chat::sendToAll(Chat::getMessageStr(30, ['general' => $general['name']], $sender), Chat::TYPE_SYSTEM, 0, 0, $sender);
                    // 添加酒馆日志(武将id, 拥有状态, 获取方式)
                    PubLog::add($uid, $generalid, 1, 11, $now);
                }
                $getinfo['getvalue'] = $general ?? ['status' => -3];
                // 查询天赋 / 技能（只要名称与描述）
                $tpl = GeneralTemplate::ROWS[$generalid] ?? null;
                $talent = Talent::ROWS[$tpl['talentid'] ?? 0] ?? [];
                $skill = Skill::ROWS[$tpl['skillid'] ?? 0] ?? [];
                $getinfo['getvalue']['talent'] = ['name' => $talent['name'] ?? null, 'description' => $talent['description'] ?? null];
                $getinfo['getvalue']['skill'] = ['name' => $skill['name'] ?? null, 'description' => $skill['description'] ?? null];
                $logValue = 0;
                break;
            case 18:
                // 各种精华
                $data1 = EquipEssence::ROWS[$getinfo['getvalue']] ?? [];
                $data1['value'] = $getinfo['getvalue1'];
                Equipment::addTowerItem($uid, $getinfo['getvalue'], $getinfo['getvalue1']);
                $getinfo['getvalue'] = $data1;
                break;
            case 19:
                // 锻造材料
                $data1 = OwnMaterials::template($getinfo['getvalue']) ?? [];
                $data1['num'] = $getinfo['getvalue1'];
                unset($data1['desc']);
                if (isset($data1['materials_id'])) {
                    OwnMaterials::change($uid, (int)$data1['materials_id'], (int)$data1['quality'], (string)$data1['name'], 0, $getinfo['getvalue1'], 1, $now);
                }
                $getinfo['getvalue'] = $data1;
                break;
            default:
                SpringReward::grant($uid, $getinfo['gettype'], $getinfo['getvalue'], 'springlottery', 'action', $now);
                break;
        }
        Redis::del('player:' . $uid);

        // 插入用户抽奖信息log表
        DB::table('spring_lottery_info_log')->insert([
            'uid' => $uid,
            'nickname' => $member['nickname'],
            'lottery_id' => $getinfo['id'],
            'group' => $getinfo['group'],
            'gettype' => $getinfo['gettype'],
            'getvalue' => is_int($logValue) ? $logValue : 0,
            'version' => $version,
            'getvalue1' => $getinfo['getvalue1'],
            'lastdateline' => $now,
        ]);

        // 下一次的转盘奖励
        if (self::isExtreme($data['buy_lavenumber'] + 2)) {
            // 开启至尊转盘的背影颜色
            $return['extreme'] = 1;
            $newlist = self::lotteryInfo(2);
        } else {
            $return['extreme'] = 0;
            $newlist = self::lotteryInfo(1);
        }
        $return['list'] = self::decorate($newlist, $member['level']);
        // 倒计时时间显示
        $return['cd'] = strtotime(date('Ymd', $now)) + 86400 - $now;
        // 取到今日玩家排名，合并用户排名+奖励
        $return['userlist'] = self::mergeReward(self::userRank($version, $day), $member['level'], true);

        if ($day <= 1) {
            $today = self::myRank($uid, $version, $day, 1);
            $return = array_merge($return, $today <= 0 || $today > self::MAX_RANK
                ? self::unranked($member['vip'], false)
                : self::ranked($today, $member, 1));
        } else {
            // 玩家昨日排名
            $yesterday = self::myRank($uid, $version, $day, 2);
            if ($yesterday === 0 || $yesterday > self::MAX_RANK) {
                // 昨日没有排上名，就显示今日的
                $today = self::myRank($uid, $version, $day, 1);
                $return = array_merge($return, $today === 0 || $today > self::MAX_RANK
                    ? self::unranked($member['vip'], true)
                    : self::ranked($today, $member, 1));
            } else {
                // 昨日排上榜了，先判断是否已经领取过奖励
                $myGet = self::myTimes($uid, $version, $day, 2);
                if ((int)($myGet['get'] ?? 0) === 0) {
                    $return = array_merge($return, self::ranked($yesterday, $member, 0));
                } else {
                    $today = self::myRank($uid, $version, $day, 1);
                    $return = array_merge($return, $today <= 0 || $today > self::MAX_RANK
                        ? self::unranked($member['vip'], false)
                        : self::ranked($today, $member, 1));
                }
            }
        }

        $after = self::member($uid) ?? $member;
        $return['status'] = 1;
        $return['info'] = [
            'id' => $getinfo['id'],
            'group' => $getinfo['group'],
            'getvalue' => $getinfo['getvalue'],
            'gettype' => $getinfo['gettype'],
            'all_silver' => $after['silver'],
            'all_gold' => $after['gold'],
            'all_act' => $after['act'],
        ];
        // 下次抽奖需要消耗的元宝数
        $return['costgold'] = self::costGold($uid);
        self::unlock($uid);
        $return['act'] = $after['act'];
        $return['gold'] = $after['gold'];
        $return['silver'] = $after['silver'];
        // 记录活动人次（免费抽奖或至尊转盘记 0 元宝）
        if ($data['lavenumber'] > 0 || $extreme) {
            $gold = 0;
        }
        DB::table('springlottery_action_log')->insert(['uid' => $uid, 'gold' => $gold, 'level' => $member['level'], 'dateline' => $now]);
        return $return;
    }

    /**
     * 领取奖励（领取的是昨天的排行奖励）: c=springlottery&m=get_reward
     *
     * status：-1 今天不能领取（活动第一天 / 活动未开启）；-2 请求过快或已领取过；1 成功
     */
    public static function get_reward(Context $ctx): array
    {
        $now = BossClock::now();
        $member = self::member(MemberHandler::resolveUid($ctx));
        $act = self::activity($now);
        $return = ['status' => 0, 'end' => 0, 'vip' => 0];
        if ($now > $act['end']) {
            $return['end'] = 1;
        }
        if ($member === null) {
            return $return;
        }
        if ($member['vip'] < self::RANK_VIP) {
            $return['vip'] = self::RANK_VIP;
        }
        $uid = $member['uid'];
        $version = $act['version'];
        $day = $act['day'];
        // 防止刷接口
        if (!self::lock($uid)) {
            $return['status'] = -2;
            $return['msg'] = self::LANG['spring_lottery_error7'];
            return $return;
        }
        if ($day <= 1) {
            // 第一天不能领取奖励
            $return['status'] = -1;
            self::unlock($uid);
            $return['msg'] = self::LANG['spring_lottery_error12'];
            return $return;
        }
        $myGet = self::myTimes($uid, $version, $day, 2);
        if ($myGet !== [] && (int)$myGet['get'] === 1) {
            // 已领取过奖励
            $return['status'] = -2;
            self::unlock($uid);
            $return['msg'] = self::LANG['spring_lottery_error13'];
            return $return;
        }
        // 标记昨天的奖励已领取（以 get = 0 为条件：并发只有一个请求能改到）
        $marked = DB::table('spring_lottery_ranklog')->where('uid', $uid)->where('version', $version)->where('day', $day - 1)
            ->where('get', 0)->whereNull('deleted_at')->update(['get' => 1, 'dateline' => $now]);
        if ($myGet !== [] && $marked === 0) {
            $return['status'] = -2;
            self::unlock($uid);
            $return['msg'] = self::LANG['spring_lottery_error13'];
            return $return;
        }
        // 取到昨日玩家排名
        $return['userlist'] = self::userRank($version, $day, 0);
        // 玩家自己昨日排名
        $yesterday = self::myRank($uid, $version, $day, 2);
        $myType = 0;
        if ($yesterday <= 30) {
            // 根据排名算出其排名应该得到的类型（名次 0 时旧版取到整张表，type 为 null）
            $myType = $yesterday > 0 ? (SpringLotteryReward::ROWS[$yesterday]['type'] ?? null) : null;
            $return['type'] = $myType;
        }
        $reward = null;
        $getvalue = null;
        if ($return['userlist'] !== []) {
            // 合并用户排名+奖励（这里精华的 reward 不换成数量）
            $return['userlist'] = self::mergeReward($return['userlist'], $member['level'], false);
            // 找到uid所对应的奖励
            $reward = 0;
            $reward1 = null;
            foreach ($return['userlist'] as $val) {
                if ($uid === (int)$val['uid']) {
                    $reward = $val['reward'] ?? 0;
                    if (($val['type'] ?? 0) === 18) {
                        $reward1 = $val['info']['num'] ?? null;
                    }
                }
            }
            $getvalue = 0;
            if ($myType !== null && $myType !== 0) {
                switch ($myType) {
                    case 2:
                        // 银币 = 银币 * 等级（列表里的银币已乘过一次等级，旧版这里再乘一次）
                        $getvalue = $reward * $member['level'];
                        Member::addCurrency($uid, silver: $getvalue);
                        break;
                    case 8:
                        // 装备
                        $item = Equip::ROWS[$reward] ?? null;
                        if ($item !== null) {
                            Equipment::addFromTemplate($uid, $reward);
                        }
                        $getvalue = $item ?? [];
                        break;
                    case 11:
                        // 坐骑
                        OwnMount::addMount($uid, $reward, 0, 1, 'springlottery', 'get_reward', $now);
                        $getvalue = MountTemp::ROWS[$reward] ?? [];
                        break;
                    case 13:
                        // 武将
                        General::addGeneral($uid, $reward);
                        $tpl = GeneralTemplate::ROWS[$reward] ?? null;
                        $getvalue = $tpl ?? [];
                        $talent = Talent::ROWS[$tpl['talentid'] ?? 0] ?? [];
                        $skill = Skill::ROWS[$tpl['skillid'] ?? 0] ?? [];
                        $getvalue['talent'] = ['name' => $talent['name'] ?? null, 'description' => $talent['description'] ?? null];
                        $getvalue['skill'] = ['name' => $skill['name'] ?? null, 'description' => $skill['description'] ?? null];
                        break;
                    case 18:
                        // 精华
                        $data = EquipEssence::ROWS[$reward] ?? [];
                        $data['value'] = $reward1;
                        unset($data['desc']);
                        Equipment::addTowerItem($uid, $reward, (int)$reward1);
                        $getvalue = $data;
                        break;
                    default:
                        if (SpringReward::grant($uid, $myType, $reward, 'springlottery', 'get_reward', $now)) {
                            $getvalue = $reward;
                        }
                        break;
                }
                Redis::del('player:' . $uid);
            }
        }

        // 今日的排名 可以为空
        $return['userlist'] = self::userRank($version, $day);
        // 玩家今日排名
        $today = self::myRank($uid, $version, $day, 1);
        $return = array_merge($return, $today === 0 || $today > self::MAX_RANK
            ? self::unranked($member['vip'], false)
            : self::ranked($today, $member, 1));

        $after = self::member($uid) ?? $member;
        $return['status'] = 1;
        $return['cd'] = strtotime(date('Ymd', $now)) + 86400 - $now;
        $return['reward'] = $reward;
        // 奖励信息
        $return['info'] = [
            'getvalue' => $getvalue,
            'gettype' => $myType,
            'all_silver' => $after['silver'],
            'all_gold' => $after['gold'],
            'all_act' => $after['act'],
        ];
        self::unlock($uid);
        return $return;
    }

    /**
     * 主城转盘抽奖活动：排行记录的 VIP 低于 2 而玩家已到 VIP 2 时同步（member.php：get_info_by_uid / update_springlottery）
     */
    public static function syncVip(int $uid, int $version, int $vip): void
    {
        $info = DB::table('spring_lottery_ranklog')->select('vip')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')
            ->orderBy('id')->first();
        if ($info !== null && (int)$info['vip'] < self::RANK_VIP && $vip >= self::RANK_VIP) {
            DB::table('spring_lottery_ranklog')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')->update(['vip' => $vip]);
        }
    }

    /**
     * 活动信息（_init_activity）：结束时间提前一天（活动结束后图标要多挂一天）
     *
     * @return array{open: int, version: int, start: int, end: int, day: int}
     */
    private static function activity(int $now): array
    {
        $info = ActivityClock::open(self::ACTIVITY, $now);
        if ($info === null) {
            return ['open' => 0, 'version' => 0, 'start' => 0, 'end' => 0, 'day' => 1];
        }
        return [
            'open' => 1,
            'version' => (int)$info['version'],
            'start' => (int)$info['starttime'],
            'end' => (int)$info['endtime'] - 86400,
            'day' => (int)ceil(($now - (int)$info['starttime']) / 86400),
        ];
    }

    /**
     * @return array{uid: int, nickname: string, level: int, vip: int, gold: int, silver: int, act: int}|null
     */
    private static function member(int $uid): ?array
    {
        if ($uid <= 0) {
            return null;
        }
        $row = DB::table('member')->select('uid', 'nickname', 'level', 'vip', 'gold', 'silver', 'act')->where('uid', $uid)->whereNull('deleted_at')->first();
        if ($row === null) {
            return null;
        }
        return [
            'uid' => (int)$row['uid'],
            'nickname' => (string)$row['nickname'],
            'level' => (int)$row['level'],
            'vip' => (int)$row['vip'],
            'gold' => (int)$row['gold'],
            'silver' => (int)$row['silver'],
            'act' => (int)$row['act'],
        ];
    }

    /**
     * 抽奖奖品列表（get_lottery_info：按 group 取出后打乱顺序）
     *
     * @return list<array<string, int|float>>
     */
    private static function lotteryInfo(int $group): array
    {
        $result = array_values(array_filter(SpringLottery::ROWS, static fn (array $row): bool => $row['group'] === $group));
        shuffle($result);
        return $result;
    }

    /**
     * 第 $n 次抽奖是否至尊转盘（$n 在 spring_extend 的 times + 序号 + 1 = 6k 里）
     */
    private static function isExtreme(int $n): bool
    {
        return $n > 0 && $n % (self::LAST_NUM + 1) === 0;
    }

    /**
     * 还需要多少次到达至尊抽奖（judge_times：min(id - 1 + times - num ≥ 0)，id - 1 + times = 6k - 1）
     */
    private static function judgeTimes(int $num): int
    {
        $step = self::LAST_NUM + 1;
        $k = max(1, intdiv($num + 1 + $step - 1, $step));
        return $k * $step - 1 - $num;
    }

    /**
     * 用户抽奖信息（get_lottery_log）：进入第二天时元宝价格回到 50、免费次数改为 1；没有记录时建档
     *
     * @param array{uid: int, nickname: string} $member
     * @return array{lavenumber: int, buy_lavenumber: int, lastdateline: int}|array{}
     */
    private static function lotteryLog(array $member, int $version, int $now): array
    {
        $uid = $member['uid'];
        $info = DB::table('spring_lottery_log')->select('lavenumber', 'buy_lavenumber', 'lastdateline')
            ->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')->first();
        if ($info !== null) {
            $info = array_map('intval', $info);
            if (date('Ymd', $now) !== date('Ymd', $info['lastdateline'])) {
                // 进入第二天，修改用户元宝消耗初始值
                DB::table('spring_costgold')->where('uid', $uid)->whereNull('deleted_at')->update(['gold' => self::MIN_NUM]);
                // 修改用户免费次数更改时间（以旧的时间为条件：同一天只改一次）
                $affected = DB::table('spring_lottery_log')->where('uid', $uid)->where('version', $version)
                    ->where('lastdateline', $info['lastdateline'])->whereNull('deleted_at')->update(['lavenumber' => 1, 'lastdateline' => $now]);
                if ($affected > 0) {
                    $info['lavenumber'] = 1;
                    $info['lastdateline'] = $now;
                }
            }
            return $info;
        }
        DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_spring_lottery_log` (`uid`, `nickname`, `lavenumber`, `buy_lavenumber`, `lastdateline`, `version`)
             VALUES (:uid, :nickname, 1, 0, 0, :version)',
            ['uid' => $uid, 'nickname' => $member['nickname'], 'version' => $version]
        );
        return ['lavenumber' => 1, 'buy_lavenumber' => 0, 'lastdateline' => 0];
    }

    /**
     * 抽奖次数 + 1（buy_lottery_num：普通转盘同时记最后一次抽奖时间，至尊转盘不改时间）
     *
     * @param array{uid: int, nickname: string} $member
     */
    private static function buyLotteryNum(array $member, int $version, int $now, bool $touch): void
    {
        // 用户log（旧版先 get_lottery_log：跨天时同样重置）
        self::lotteryLog($member, $version, $now);
        $set = $touch ? ', `lastdateline` = :now' : '';
        $params = ['uid' => $member['uid'], 'version' => $version];
        if ($touch) {
            $params['now'] = $now;
        }
        DB::execute(
            "UPDATE `zc_sanguo_spring_lottery_log` SET `buy_lavenumber` = `buy_lavenumber` + 1{$set}
             WHERE `uid` = :uid AND `version` = :version AND `deleted_at` IS NULL",
            $params
        );
    }

    /**
     * 今日（$type 1）或昨日的抽奖记录（get_mytimes_by_uid），没有为空数组
     *
     * @return array<string, mixed>
     */
    private static function myTimes(int $uid, int $version, int $day, int $type): array
    {
        if ($type !== 1) {
            $day -= 1;
        }
        return DB::table('spring_lottery_ranklog')->where('uid', $uid)->where('version', $version)->where('day', $day)->whereNull('deleted_at')
            ->orderBy('id')->first() ?? [];
    }

    /**
     * 今日（$type 1）或昨日的名次（get_my_rank）：没抽过为 0；抽奖次数不少于自己的 VIP 2 以上记录数，自己在其中时取自己的位置
     */
    private static function myRank(int $uid, int $version, int $day, int $type): int
    {
        $info = self::myTimes($uid, $version, $day, $type);
        if ($info === [] || (int)$info['times'] <= 0) {
            return 0;
        }
        $number = (int)$info['times'];
        if ($type !== 1) {
            $day -= 1;
        }
        // 要求:次数相同的谁先进入游戏谁就拍前面
        $result = DB::table('spring_lottery_ranklog')->select('uid')->where('times', '>=', $number)->where('vip', '>=', self::RANK_VIP)
            ->where('version', $version)->where('day', $day)->whereNull('deleted_at')->orderBy('times', 'DESC')->orderBy('id')->get();
        $return = count($result);
        foreach ($result as $key => $val) {
            if ($uid === (int)$val['uid']) {
                $return = $key + 1;
            }
        }
        return $return;
    }

    /**
     * 今日（$type 1）或昨日的排行（get_userrank）：VIP 2 以上、抽过奖的前 20 名
     *
     * @return list<array<string, mixed>>
     */
    private static function userRank(int $version, int $day, int $type = 1): array
    {
        if ($type !== 1) {
            $day -= 1;
        }
        $rows = DB::table('spring_lottery_ranklog')->select('id', 'day', 'uid', 'nickname', 'vip', 'times', 'version', 'get', 'dateline')
            ->where('version', $version)->where('vip', '>=', self::RANK_VIP)->where('day', $day)->where('times', '>', 0)->whereNull('deleted_at')
            ->orderBy('times', 'DESC')->orderBy('id')->limit(self::LIMIT)->get();
        return array_map(static function (array $row): array {
            foreach ($row as $key => $value) {
                $row[$key] = $key === 'nickname' ? (string)$value : (int)$value;
            }
            return $row;
        }, $rows);
    }

    /**
     * 排行合并奖励：第 n 名配排名奖励表的第 n 行；银币 × 主公等级，装备 / 武将 / 坐骑 / 精华附模板信息
     *
     * $essenceAsNum 为 true 时精华的 reward 换成数量（index / action），get_reward 不换。
     *
     * @param list<array<string, mixed>> $userlist
     * @return list<array<string, mixed>>
     */
    private static function mergeReward(array $userlist, int $level, bool $essenceAsNum): array
    {
        $rewardList = array_values(SpringLotteryReward::ROWS);
        foreach ($userlist as $key => $value) {
            $val = $rewardList[$key] ?? null;
            if ($val === null) {
                continue;
            }
            $userlist[$key]['reward'] = $val['reward'];
            $userlist[$key]['type'] = $val['type'];
            if ($val['type'] === 2) {
                // 银币
                $userlist[$key]['reward'] *= $level;
            }
            if ($val['type'] === 8) {
                // 装备
                $userlist[$key]['info'] = Equip::ROWS[$val['reward']] ?? [];
            } elseif ($val['type'] === 13) {
                // 武将
                $userlist[$key]['info'] = GeneralTemplate::ROWS[$val['reward']] ?? null;
            } elseif ($val['type'] === 11) {
                // 坐骑
                $userlist[$key]['info'] = MountTemp::ROWS[$val['reward']] ?? [];
            } elseif ($val['type'] === 18) {
                // 精华
                $userlist[$key]['info'] = EquipEssence::ROWS[$val['reward']] ?? [];
                $userlist[$key]['info']['num'] = $val['reward1'];
                if ($essenceAsNum) {
                    $userlist[$key]['reward'] = $val['reward1'];
                }
            }
        }
        return $userlist;
    }

    /**
     * 未上榜的显示：$vipLess 为 false 时 VIP 大于 2 才显示 VIP 标识（旧版多数分支），为 true 时 VIP 不小于 2 即显示
     *
     * @return array{my_rank: array{rank: string, is_vip: int}, is_cd: int}
     */
    private static function unranked(int $vip, bool $vipLess): array
    {
        return [
            'my_rank' => ['rank' => '', 'is_vip' => ($vipLess ? $vip >= self::RANK_VIP : $vip > self::RANK_VIP) ? 1 : 0],
            'is_cd' => 1,
        ];
    }

    /**
     * 上榜的显示：排名 + 奖励（is_cd 1 显示倒计时，0 显示奖品）；VIP 不足 2 时不显示排名
     *
     * @param array{level: int, vip: int} $member
     * @return array{my_rank: array<string, mixed>, is_cd: int}
     */
    private static function ranked(int $rank, array $member, int $isCd): array
    {
        $reward = SpringLotteryReward::ROWS[$rank] ?? ['type' => null, 'reward' => null, 'reward1' => null];
        $myRank = ['rank' => $rank, 'type' => $reward['type'], 'my_reward' => $reward['reward']];
        if ($myRank['type'] === 2) {
            // 银币
            $myRank['my_reward'] *= $member['level'];
        }
        if ($myRank['type'] === 18) {
            // 精华处理
            $myRank['quality'] = EquipEssence::ROWS[$reward['reward']]['quality'] ?? null;
            $myRank['essence_id'] = $reward['reward'];
            $myRank['num'] = $reward['reward1'];
            $myRank['my_reward'] = $myRank['num'];
        } elseif ($myRank['type'] === 8) {
            // 装备
            $myRank['quality'] = Equip::ROWS[$myRank['my_reward']]['quality'] ?? null;
            $myRank['name'] = Equip::ROWS[$myRank['my_reward']]['name'] ?? null;
        } elseif ($myRank['type'] === 11) {
            // 坐骑
            $myRank['quality'] = MountTemp::ROWS[$myRank['my_reward']]['quality'] ?? null;
            $myRank['name'] = MountTemp::ROWS[$myRank['my_reward']]['name'] ?? null;
        } elseif ($myRank['type'] === 13) {
            // 武将
            $myRank['name'] = GeneralTemplate::ROWS[$myRank['my_reward']]['name'] ?? null;
        }
        $myRank['is_vip'] = 1;
        if ($member['vip'] < self::RANK_VIP) {
            $myRank['is_vip'] = 0;
            $myRank['rank'] = '';
        }
        return ['my_rank' => $myRank, 'is_cd' => $isCd];
    }

    /**
     * 转盘奖品附加显示信息：银币 × 主公等级；武将 / 装备 / 坐骑的 image、str（名称）、quality；精华 / 锻造材料的 quality、name、
     * essence_id / materials_id、num
     *
     * @param list<array<string, int|float>> $list
     * @return list<array<string, mixed>>
     */
    private static function decorate(array $list, int $level): array
    {
        foreach ($list as $key => $val) {
            $type = (int)$val['gettype'];
            $value = (int)$val['getvalue'];
            if ($type === 13 && isset(GeneralTemplate::ROWS[$value])) {
                $list[$key]['image'] = GeneralTemplate::ROWS[$value]['image'];
                $list[$key]['str'] = GeneralTemplate::ROWS[$value]['name'];
            }
            if ($type === 2) {
                $list[$key]['getvalue'] = $value * $level;
            }
            if ($type === 8 && isset(Equip::ROWS[$value])) {
                $list[$key]['image'] = Equip::ROWS[$value]['id'];
                $list[$key]['str'] = Equip::ROWS[$value]['name'];
                $list[$key]['quality'] = Equip::ROWS[$value]['quality'];
            }
            if ($type === 11 && isset(MountTemp::ROWS[$value])) {
                $list[$key]['image'] = MountTemp::ROWS[$value]['id'];
                $list[$key]['str'] = MountTemp::ROWS[$value]['name'];
                $list[$key]['quality'] = MountTemp::ROWS[$value]['quality'];
            }
            if ($type === 18 && isset(EquipEssence::ROWS[$value])) {
                $list[$key]['quality'] = EquipEssence::ROWS[$value]['quality'];
                $list[$key]['name'] = EquipEssence::ROWS[$value]['name'];
                $list[$key]['essence_id'] = EquipEssence::ROWS[$value]['id'];
                $list[$key]['num'] = $val['getvalue1'];
                $list[$key]['gettype'] = 18;
            }
            if ($type === 19) {
                // 锻造材料（get_materials_info_by_arr，按调用方还原）
                $material = OwnMaterials::template($value);
                if ($material !== null) {
                    $list[$key]['quality'] = $material['quality'];
                    $list[$key]['name'] = $material['name'];
                    $list[$key]['materials_id'] = $material['materials_id'];
                    $list[$key]['num'] = $val['getvalue1'];
                    $list[$key]['gettype'] = 19;
                }
            }
        }
        return $list;
    }

    /**
     * 下次抽奖需要消耗的元宝数（get_costgold_by_uid，没有就添加一条）
     */
    private static function costGold(int $uid): int
    {
        $info = self::costRow($uid);
        if ($info === null) {
            self::addCostGold($uid);
            return self::MIN_NUM;
        }
        return (int)$info['gold'];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function costRow(int $uid): ?array
    {
        return DB::table('spring_costgold')->select('gold')->where('uid', $uid)->whereNull('deleted_at')->orderBy('id')->first();
    }

    private static function addCostGold(int $uid): void
    {
        DB::execute('INSERT IGNORE INTO `zc_sanguo_spring_costgold` (`uid`, `gold`) VALUES (:uid, :gold)', ['uid' => $uid, 'gold' => self::MIN_NUM]);
    }

    /**
     * 下次所需元宝（get_needgold：小于 200 时取 SpringNeedgold 里第一个更大的价格，否则 200）
     */
    private static function needGold(int $gold): int
    {
        if ($gold >= self::MAX_NUM) {
            return self::MAX_NUM;
        }
        foreach (SpringNeedgold::ROWS as $row) {
            if ($row['gold'] > $gold) {
                return $row['gold'];
            }
        }
        // 旧版查不到时 $return 未定义（写入 0）；SpringNeedgold 最高 200，不会走到
        return 0;
    }

    /**
     * 添加今天的记录（insert_ranklog）
     *
     * @param array{uid: int, nickname: string, vip: int} $member
     */
    private static function insertRanklog(array $member, int $day, int $version): void
    {
        DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_spring_lottery_ranklog` (`uid`, `nickname`, `day`, `times`, `version`, `get`, `vip`)
             VALUES (:uid, :nickname, :day, 0, :version, 0, :vip)',
            ['uid' => $member['uid'], 'nickname' => $member['nickname'], 'day' => $day, 'version' => $version, 'vip' => $member['vip']]
        );
    }

    private static function lock(int $uid): bool
    {
        return (bool)Redis::instance()->set(self::LOCK_KEY . $uid, 1, ['nx', 'ex' => self::LOCK_TTL]);
    }

    private static function unlock(int $uid): void
    {
        Redis::del(self::LOCK_KEY . $uid);
    }
}
