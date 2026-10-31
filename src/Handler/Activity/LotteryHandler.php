<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\General\TavernHandler;
use Hanjiang\MasterData\Equip as EquipTemplate;
use Hanjiang\MasterData\General as GeneralTemplate;
use Hanjiang\MasterData\Lottery;
use Hanjiang\Model\Chat;
use Hanjiang\Model\Equipment;
use Hanjiang\Model\FreeLeap;
use Hanjiang\Model\General;
use Hanjiang\Model\Member;
use Hanjiang\Model\PubLog;
use Hanjiang\Model\StatPay;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 幸运大转盘（移植旧版 controllers/lottery.php + models/lottery_model.php）
 *
 * 客户端（lotterypop.js）调用：index / action / buy_lottery_num / refresh。主公 16 级以上且已选角色（gender > 0）开放。
 * 奖品取 MasterData Lottery 的一组（4 组，每人每天随机一组存 Redis lottery_group_{uid}_{ymd}，花 20 元宝刷新为另一组）；
 * 抽奖次数存 lottery_log：每天第一次请求的每日计划任务按连续登录天数发免费次数（update_day：1 / 2 / 3 / 7 / 15 / 30 天
 * 对应 1 / 2 / 3 / 4 / 5 / 7 次），另可花 10 元宝买次数（每天 10 次，Redis lottery_buy_times_{ymd}_{uid}）。
 * gettype：1 银币（× 主公等级）、2 元宝、3 军令、4 武将（已拥有的不抽）、5 黄宝石、6 紫宝石、7 装备（整件）、8 突飞卡；
 * 武将与装备 VIP 1 以上才可能抽中。抽中 100 元宝以上 / 武将 / 装备 / 100 张以上突飞卡发聊天室系统消息（get_message_str 6 / 7）。
 *
 * 照旧版保留的写法：buy_times_total = min(10 - 已买次数, 0)（恒不大于 0）；注册 120 秒内抽奖的账号按刷接口封号
 * （member.status = 4，10 天）。依赖未移植：封号记录 stat_opear_user（后台统计表）。
 * 与旧版的差别：action / buy_lottery_num / refresh 在 Member::locked（玩家行锁）里执行；lottery_log 以 uid 为唯一键
 * （旧版无索引，并发首次进入可能插出两行）。
 */
final class LotteryHandler
{
    /** 奖励列表组 id 的 Redis 键前缀（lottery_group_{uid}_{ymd}） */
    private const string GROUP_KEY = 'lottery_group_';
    /** 已购买次数的 Redis 键前缀（lottery_buy_times_{ymd}_{uid}） */
    private const string BUY_TIMES_KEY = 'lottery_buy_times_';
    /** 刷新需要元宝 */
    private const int REFRESH_PRICE = 20;
    /** 抽奖次数价格（lottery_model->price） */
    private const int TIMES_PRICE = 10;
    /** 每天可购买的抽奖次数 */
    private const int BUY_NUM = 10;
    /** 开放等级 */
    private const int OPEN_LEVEL = 16;
    /** 连续登录天数 => 免费抽奖次数（get_num_by_day） */
    private const array DAY_NUM = [1 => 1, 2 => 2, 3 => 3, 7 => 4, 15 => 5, 30 => 7];

    /**
     * 取抽奖页面信息: c=lottery&m=index
     */
    public static function index(Context $ctx): array
    {
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null || !self::open($member)) {
            // 限制开放等级
            $return['status'] = -1;
            return $return;
        }
        $uid = (int)$member['uid'];
        $now = time();
        // 取用户记录
        $return['log'] = self::lotteryLog($uid, $now);
        // 取抽奖列表
        $key = self::groupKey($uid, $now);
        $group = (int)(Redis::get($key) ?? 0);
        if ($group) {
            // 根据组的 id 查询
            $list = self::lottery($group);
        } else {
            // 随机一组奖励，把 group 存进 redis
            $list = self::lottery();
            Redis::set($key, (int)reset($list)['group'], 86400);
        }
        $return['list'] = self::decorate($list, (int)$member['level'], true);
        $return['buy_times'] = self::buyTimes($uid, $now);
        $return['buy_times_limit'] = self::BUY_NUM;
        $return['buy_times_total'] = min(self::BUY_NUM - $return['buy_times'], 0);
        $return['status'] = 1;
        return $return;
    }

    /**
     * 抽奖: c=lottery&m=action
     */
    public static function action(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        return Member::locked($uid, static function () use ($uid): array {
            $return = ['status' => 0, 'generalinfo' => []];
            $member = self::member($uid);
            if ($member === null || !self::open($member)) {
                // 限制开放等级
                $return['status'] = -1;
                return $return;
            }
            $now = time();
            if ($now - (int)$member['jointime'] < 120) {
                // 刷接口的帐号，直接封号（user_model->update_member_status(uid, 4, 10 天)）
                // 依赖未移植：封号记录 stat_opear_user（后台统计表）
                DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->update(['status' => 4, 'statustime' => $now + 86400 * 10]);
                Redis::del('player:' . $uid);
                $return['status'] = -1;
                return $return;
            }
            $log = self::log($uid);
            if ($log === null) {
                // 没有用户 log 记录
                $return['status'] = -1;
                return $return;
            }
            // 取 redis 中奖品列表
            $group = (int)(Redis::get(self::groupKey($uid, $now)) ?? 0);
            $list = $group ? self::lottery($group) : self::lottery();
            $level = (int)$member['level'];
            $list = self::decorate($list, $level, false);
            $return['list'] = $list;

            $rate = 0;
            foreach ($list as $key => $val) {
                $weight = (int)round($val['getrate'] * 100);
                $rate += $weight;
                if ($val['gettype'] === 4 && General::ownsTemplate($uid, $val['getvalue'])) {
                    // 已拥有的将领从抽奖列表中去掉，总概率减少
                    unset($list[$key]);
                    $rate -= $weight;
                    continue;
                }
                // 道具和武将 vip1 以上才可抽中
                if (in_array($val['gettype'], [4, 7], true) && (int)$member['vip'] < 1) {
                    unset($list[$key]);
                    $rate -= $weight;
                }
            }
            $getinfo = null;
            $rand = mt_rand(1, max(1, $rate));
            $cursor = 0;
            foreach ($list as $val) {
                $cursor += (int)round($val['getrate'] * 100);
                if ($rand <= $cursor) {
                    $getinfo = $val;
                    break;
                }
            }
            if ($log['lavenumber'] > 0) {
                // 消耗免费抽奖次数
                self::subNum($uid, 'lavenumber', $log['lavenumber']);
                $log['lavenumber']--;
            } elseif ($log['buy_lavenumber'] > 0) {
                // 消耗购买的抽奖次数
                self::subNum($uid, 'buy_lavenumber', $log['buy_lavenumber']);
                $log['buy_lavenumber']--;
            } else {
                // 免费次数和购买的次数都没有了
                $return['status'] = -2;
                return $return;
            }
            // 剩余次数
            $return['lavenumber'] = $log['lavenumber'];
            $return['buy_lavenumber'] = $log['buy_lavenumber'];
            $return['total_num'] = $log['total_num'] - 1;
            if ($getinfo === null) {
                // 旧版 mt_rand 落在浮点累计误差之外时 $getinfo 未定义、不发奖励（概率表整数化后不会出现）
                $getinfo = ['gettype' => 0, 'getvalue' => 0];
            }

            $sender = null;
            $value = (int)$getinfo['getvalue'];
            switch ((int)$getinfo['gettype']) {
                case 1:
                    // 增加银币（主公等级*银币数量）
                    Member::addCurrency($uid, silver: $value);
                    break;
                case 2:
                    // 增加元宝
                    Member::addCurrency($uid, gold: $value);
                    if ($value >= 100) {
                        $sender = Chat::sender($uid);
                        Chat::sendToAll(Chat::getMessageStr(6, ['gold' => $value], $sender), Chat::TYPE_SYSTEM, 0, 0, $sender);
                    }
                    break;
                case 3:
                    // 增加军令
                    Member::addCurrency($uid, act: $value);
                    break;
                case 4:
                    // 增加将领
                    $general = General::addGeneral($uid, $value);
                    if ($general !== null) {
                        $sender = Chat::sender($uid);
                        Chat::sendToAll(Chat::getMessageStr(7, ['general' => $general['name']], $sender), Chat::TYPE_SYSTEM, 0, 0, $sender);
                        // 添加酒馆日志(武将id, 拥有状态, 获取方式)
                        PubLog::add($uid, $value, 1, 5, $now);
                        $return['generalinfo'] = $general;
                    } else {
                        // 旧版 add_general 失败返回 {status: -3}（已拥有）
                        $return['generalinfo'] = ['status' => -3];
                    }
                    break;
                case 5:
                    // 黄宝石
                    TavernHandler::addJade($uid, 2, $value, 'lottery', 'action');
                    break;
                case 6:
                    // 紫宝石
                    TavernHandler::addJade($uid, 3, $value, 'lottery', 'action');
                    break;
                case 7:
                    // 增加道具（整件）
                    $item = EquipTemplate::ROWS[$value] ?? null;
                    if ($item !== null) {
                        Equipment::addFromTemplate($uid, $value);
                        // 发送到聊天室
                        $sender = Chat::sender($uid);
                        $itemname = Chat::quality((int)$item['quality'], (string)$item['name']);
                        Chat::sendToAll(Chat::getMessageStr(6, ['equip' => $itemname], $sender), Chat::TYPE_SYSTEM, 0, 0, $sender);
                    }
                    break;
                case 8:
                    // 突飞卡
                    FreeLeap::add($uid, $value, $now);
                    if ($value >= 100) {
                        $sender = Chat::sender($uid);
                        Chat::sendToAll(Chat::getMessageStr(6, ['leap' => $value . ' ' . self::LANG_LEAP1], $sender), Chat::TYPE_SYSTEM, 0, 0, $sender);
                    }
                    break;
            }
            Redis::del('player:' . $uid);

            $after = self::member($uid) ?? $member;
            $return['status'] = 1;
            $getinfo['all_silver'] = (int)$after['silver'];
            $getinfo['all_gold'] = (int)$after['gold'];
            $getinfo['all_act'] = (int)$after['act'];
            $return['info'] = $getinfo;
            $return['buy_times'] = self::buyTimes($uid, $now);
            $return['buy_times_limit'] = self::BUY_NUM;
            $return['buy_times_total'] = min(self::BUY_NUM - $return['buy_times'], 0);
            return $return;
        });
    }

    /**
     * 购买抽奖次数: c=lottery&m=buy_lottery_num
     */
    public static function buy_lottery_num(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        return Member::locked($uid, static function () use ($uid): array {
            $return = ['status' => 0];
            $member = self::member($uid);
            if ($member === null || !self::open($member)) {
                // 限制开放等级
                $return['status'] = -1;
                return $return;
            }
            $now = time();
            // 获取次数价格
            $price = self::TIMES_PRICE;
            if ((int)$member['gold'] < $price) {
                // 元宝不足
                $return['status'] = -1;
                return $return;
            }
            // 查询已购买的次数
            $times = self::buyTimes($uid, $now);
            if ($times >= self::BUY_NUM) {
                $return['status'] = -3;
                return $return;
            }
            // 添加次数
            $log = self::log($uid);
            if ($log === null) {
                $return['status'] = -2;
                return $return;
            }
            $number = $log['buy_lavenumber'] + 1;
            DB::table('lottery_log')->where('uid', $uid)->whereNull('deleted_at')->update(['buy_lavenumber' => $number]);
            // 扣除元宝
            Member::deductCurrency($uid, gold: $price);
            // 添加购买次数
            Redis::set(self::buyTimesKey($uid, $now), $times + 1, 86400);

            $return['status'] = 1;
            $return['gold'] = (int)DB::table('member')->where('uid', $uid)->value('gold');
            $return['number'] = $number;
            // 消费统计记录
            StatPay::record($uid, 'choujiang_times', $price);
            // 查询用户总次数
            $return['total_num'] = self::lotteryLog($uid, $now)['info']['total_num'];
            $return['buy_times'] = $times + 1;
            $return['buy_times_limit'] = self::BUY_NUM;
            $return['buy_times_total'] = min(self::BUY_NUM - $return['buy_times'], 0);
            return $return;
        });
    }

    /**
     * 刷新奖品组: c=lottery&m=refresh
     */
    public static function refresh(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        return Member::locked($uid, static function () use ($uid): array {
            $return = ['status' => 0];
            $member = self::member($uid);
            if ($member === null || !self::open($member)) {
                // 限制开放等级
                $return['status'] = -1;
                return $return;
            }
            // 判断元宝是否足够
            if ((int)$member['gold'] < self::REFRESH_PRICE) {
                $return['status'] = -1;
                return $return;
            }
            $now = time();
            $key = self::groupKey($uid, $now);
            $group = (int)(Redis::get($key) ?? 0);
            $list = self::decorate(self::lottery($group, 1), (int)$member['level'], true);
            // 存进 redis
            Redis::set($key, (int)reset($list)['group'], 86400);
            // 扣除元宝
            Member::deductCurrency($uid, gold: self::REFRESH_PRICE);
            // 消费统计记录
            StatPay::record($uid, 'choujiang_shuaxin', self::REFRESH_PRICE);

            $return['status'] = 1;
            $return['list'] = $list;
            $return['gold'] = (int)DB::table('member')->where('uid', $uid)->value('gold');
            return $return;
        });
    }

    /**
     * 每日计划任务：更新抽奖连续登录次数（lottery_model->update_day，没有记录时不处理）
     *
     * 与上次登录相差 1 天：连续天数 + 1，按天数重置免费次数；否则从 1 天、1 次重新算。剩余的购买次数不动。
     */
    public static function updateDay(int $uid, int $now): void
    {
        $info = DB::table('lottery_log')->where('uid', $uid)->whereNull('deleted_at')->first();
        if ($info === null) {
            return;
        }
        $time1 = strtotime(date('y-m-d', (int)$info['lastdateline']));
        $time2 = strtotime(date('y-m-d', $now));
        if ($time2 === $time1) {
            return;
        }
        if (($time2 - $time1) / 86400 == 1) {
            // 连续登录天数
            $num = (int)$info['day'] + 1;
            $number = self::numByDay($num);
            $data = ['day' => $num, 'number' => $number, 'lavenumber' => $number, 'lastdateline' => $now];
        } else {
            // 非连续登录
            $data = ['day' => 1, 'number' => 1, 'lavenumber' => 1, 'lastdateline' => $now];
        }
        DB::table('lottery_log')->where('uid', $uid)->whereNull('deleted_at')->update($data);
    }

    /** 旧版语言包 base_leap1 */
    private const string LANG_LEAP1 = '初级突飞卡';

    /**
     * @return array<string, mixed>|null
     */
    private static function member(int $uid): ?array
    {
        if ($uid <= 0) {
            return null;
        }
        return DB::table('member')->select('uid', 'level', 'gender', 'vip', 'jointime', 'silver', 'gold', 'act')
            ->where('uid', $uid)->whereNull('deleted_at')->first();
    }

    /**
     * 抽奖功能开启状态（构造函数：主公 16 级以上且已选角色）
     *
     * @param array<string, mixed> $member
     */
    private static function open(array $member): bool
    {
        return (int)$member['level'] >= self::OPEN_LEVEL && (int)$member['gender'] > 0;
    }

    private static function groupKey(int $uid, int $now): string
    {
        return self::GROUP_KEY . $uid . '_' . date('ymd', $now);
    }

    private static function buyTimesKey(int $uid, int $now): string
    {
        return self::BUY_TIMES_KEY . date('ymd', $now) . '_' . $uid;
    }

    /**
     * 今日已购买次数（get_buy_times）
     */
    private static function buyTimes(int $uid, int $now): int
    {
        return (int)(Redis::get(self::buyTimesKey($uid, $now)) ?? 0);
    }

    /**
     * 抽奖奖品列表（get_lottery：$type 1 且有组时换成另外三组之一，否则指定组或随机一组；组内乱序）
     *
     * @return list<array<string, mixed>>
     */
    private static function lottery(int $group = 0, int $type = 0): array
    {
        if ($type === 1 && $group) {
            $arr = range(1, 4);
            unset($arr[$group - 1]);
            $group = $arr[array_rand($arr)];
        } else {
            $group = $group > 0 ? $group : mt_rand(1, 4);
        }
        $result = array_values(array_filter(Lottery::ROWS, static fn (array $row): bool => $row['group'] === $group));
        shuffle($result);
        return $result;
    }

    /**
     * 奖品显示信息：银币 × 主公等级、武将头像 / 名称 / 类型 / 星级、道具 id / 品级 / 名称；index / refresh 去掉 getrate
     *
     * @param list<array<string, mixed>> $list
     * @return list<array<string, mixed>>
     */
    private static function decorate(array $list, int $level, bool $hideRate): array
    {
        foreach ($list as $key => $val) {
            if ($hideRate) {
                unset($list[$key]['getrate']);
            }
            if ($val['gettype'] === 1) {
                $list[$key]['getvalue'] = $val['getvalue'] * $level;
            }
            if ($val['gettype'] === 4) {
                // 将领，查询将领信息
                $general = GeneralTemplate::ROWS[$val['getvalue']] ?? null;
                $list[$key]['gimage'] = $general['image'] ?? null;
                $list[$key]['gname'] = $general['name'] ?? null;
                $list[$key]['generaltype'] = $general['type'] ?? null;
                $list[$key]['star'] = $general['star'] ?? null;
            }
            if ($val['gettype'] === 7) {
                // 道具
                $item = EquipTemplate::ROWS[$val['getvalue']] ?? null;
                if ($item !== null) {
                    $list[$key]['itemid'] = $item['id'];
                    $list[$key]['itemquality'] = $item['quality'];
                    // 旧版 itemtype 取的也是 quality
                    $list[$key]['itemtype'] = $item['quality'];
                    $list[$key]['itemname'] = $item['name'];
                }
            }
        }
        return $list;
    }

    /**
     * 用户抽奖记录（get_lottery_log：没有记录时按连续登录 1 天建档），info 带 total_num 与 price
     *
     * @return array{status: int, info: array<string, int>}
     */
    private static function lotteryLog(int $uid, int $now): array
    {
        $info = self::log($uid);
        if ($info === null) {
            $number = self::numByDay(1);
            DB::execute(
                'INSERT IGNORE INTO `zc_sanguo_lottery_log` (`uid`, `day`, `number`, `lavenumber`, `buy_lavenumber`, `lastdateline`)
                 VALUES (:uid, 1, :number, :lavenumber, 0, :now)',
                ['uid' => $uid, 'number' => $number, 'lavenumber' => $number, 'now' => $now]
            );
            $info = self::log($uid) ?? ['uid' => $uid, 'day' => 1, 'number' => $number, 'lavenumber' => $number, 'buy_lavenumber' => 0, 'lastdateline' => $now, 'total_num' => $number];
        }
        $info['price'] = self::TIMES_PRICE;
        return ['status' => 1, 'info' => $info];
    }

    /**
     * 用户 log（get_log），带 total_num；没有记录返回 null
     *
     * @return array<string, int>|null
     */
    private static function log(int $uid): ?array
    {
        $row = DB::table('lottery_log')->select('uid', 'day', 'number', 'lavenumber', 'buy_lavenumber', 'lastdateline')
            ->where('uid', $uid)->whereNull('deleted_at')->first();
        if ($row === null) {
            return null;
        }
        $row = array_map('intval', $row);
        $row['total_num'] = $row['lavenumber'] + $row['buy_lavenumber'];
        return $row;
    }

    /**
     * 消耗次数（sub_num：按读到的次数写回减 1）
     */
    private static function subNum(int $uid, string $field, int $current): void
    {
        DB::table('lottery_log')->where('uid', $uid)->whereNull('deleted_at')->update([$field => $current - 1]);
    }

    /**
     * 根据连续登录天数获取免费抽奖次数（get_num_by_day）
     */
    private static function numByDay(int $day): int
    {
        $number = 0;
        foreach (self::DAY_NUM as $key => $val) {
            if ($day >= $key) {
                $number = $val;
            }
        }
        return $number;
    }
}
