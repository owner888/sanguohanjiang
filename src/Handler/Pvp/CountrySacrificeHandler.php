<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Pvp;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\MasterData\CountryWorship;
use Hanjiang\Model\Chat;
use Hanjiang\Model\Member;
use Hanjiang\Model\StatPay;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 国家祭祀（移植旧版 controllers/countrysacrifice.php + models/countrysacrifice_model.php）
 *
 * 客户端（countryworshippop.js）调用：index / action(id)。主公 50 级开启（status 3）。祭祀取 MasterData CountryWorship
 * （type 1 银币价格 × 主公等级、2 元宝；get_value 获得的国家贡献），2 黄金祭祀要 VIP 3、3 至尊祭祀要 VIP 5。
 * 每人每天祭祀一次（member_country.worshipdateline），每个国家每天最多 50 次（按今天的 country_worship_log 计数）。
 * 祭祀加今日贡献与总贡献（含国家税金累计）、国家经验与贡献 log（CountryHandler::addContribute，log type 为祭祀消耗类型、
 * cost 为价格）；元宝祭祀向国家成员发国家聊天室系统消息（get_message_str 9，send_message_to_country type 6）。
 *
 * 与旧版的差别（多 Worker 安全）：action 在 Member::locked 里执行并锁住国家行（同一国家的祭祀串行，50 次上限不会超）；
 * 贡献用一条 UPDATE 原子累加（旧版按读到的贡献写回）。
 * 依赖未移植：国家计划任务标志位 country_model->set_flog（消费方 cron_country 未移植，同国宴）。
 */
final class CountrySacrificeHandler
{
    /** 开启等级 */
    private const int OPEN_LEVEL = 50;
    /** 国家每日最多祭祀次数（旧版 $_times） */
    private const int TIMES = 50;
    /** 祭祀日志条数（get_log 的 $limit） */
    private const int LOG_LIMIT = 7;
    /** 语言包（country_lang） */
    private const array LANG_NAME = [2 => '黄金祭祀', 3 => '至尊祭祀'];

    /**
     * 初始化接口: c=countrysacrifice&m=index
     */
    public static function index(Context $ctx): array
    {
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        // 判断是否达到开启等级
        if ($member === null || (int)$member['level'] < self::OPEN_LEVEL) {
            $return['status'] = 3;
            return $return;
        }
        $now = time();
        $country = CountryHandler::memberInfo((int)$member['uid'], (int)$member['country']);
        if ($country === null || (int)$country['country'] === 0) {
            // 未进入国家，无法参加每日祭祀
            $return['status'] = -1;
            return $return;
        }
        // 查询祭祀log
        $logList = DB::table('country_worship_log')->select('id', 'country', 'nickname', 'type', 'get_value', 'dateline')
            ->where('country', (int)$member['country'])->where('type', '>', 1)->whereNull('deleted_at')
            ->orderBy('id', 'DESC')->limit(self::LOG_LIMIT)->get();
        // 取每日祭祀次数
        $number = self::countNumber((int)$member['country'], $now);

        $return['status'] = 1;
        $return['list'] = self::worshipList((int)$member['level']);
        $return['log_list'] = array_map(static fn(array $v): array => array_map(
            static fn(mixed $x): mixed => is_numeric($x) ? (int)$x : $x,
            $v
        ), $logList);
        $return['now_times'] = self::TIMES - $number;
        $return['all_times'] = self::TIMES;
        if (date('ymd', (int)$country['worshipdateline']) === date('ymd', $now)) {
            // 今日已祭祀，不能祭祀了
            $return['status'] = 2;
        }
        return $return;
    }

    /**
     * 祭祀: c=countrysacrifice&m=action&id=祭祀id
     */
    public static function action(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $id = (int)$ctx->input('id', 0);
        return Member::locked($uid, static function () use ($uid, $id): array {
            $return = ['status' => 0];
            $now = time();
            $member = self::member($uid);
            // 判断是否达到开启等级
            if ($member === null || (int)$member['level'] < self::OPEN_LEVEL) {
                $return['status'] = 3;
                return $return;
            }
            $country = CountryHandler::memberInfo($uid, (int)$member['country']);
            if ($country === null || (int)$country['country'] === 0) {
                // 未进入国家，无法参加每日祭祀
                $return['status'] = -1;
                return $return;
            }
            if (date('ymd', (int)$country['worshipdateline']) === date('ymd', $now)) {
                // 今日已祭祀，不能祭祀了
                $return['status'] = -2;
                return $return;
            }
            $info = null;
            foreach (self::worshipList((int)$member['level']) as $row) {
                if ($row['id'] === $id) {
                    $info = $row;
                }
            }
            if ($info === null) {
                // 获取祭祀信息失败
                $return['status'] = -3;
                return $return;
            }
            // 判断vip等级是否足够
            if ((int)$member['vip'] < $info['need_vip']) {
                $return['status'] = -6;
                return $return;
            }
            // 同一国家的祭祀串行（锁国家行），再取每日祭祀次数
            DB::query('SELECT `id` FROM `zc_sanguo_country` WHERE `id` = :id FOR UPDATE', ['id' => (int)$member['country']]);
            $number = self::countNumber((int)$member['country'], $now);
            if ($number >= self::TIMES) {
                $return['status'] = -7;
                return $return;
            }
            // 获得的贡献
            $get = $info['get_value'];
            if ((int)$member['vip'] === 0 && $now - (int)$country['joindateline'] < 7 * 86400 && (int)$country['contribute'] + $get > 100) {
                // 新加入国家7天内贡献不能超过100
                $return['status'] = -8;
                return $return;
            }
            if ($info['type'] === 1) {
                // 扣除消耗的银币
                if (!Member::deductCurrency($uid, silver: $info['price'])) {
                    // 银币不够
                    $return['status'] = -4;
                    return $return;
                }
            } else {
                // 扣除消耗的金币
                if (!Member::deductCurrency($uid, gold: $info['price'])) {
                    // 金币不够
                    $return['status'] = -5;
                    return $return;
                }
                // 消费记录
                StatPay::record($uid, 'country_sacrifice', $info['price']);
            }

            // 添加国家贡献（含国家税金累计、国家经验与贡献 log）
            CountryHandler::addContribute($member, $get, $info['type'], 'countrysacrifice', 'action', $info['price']);
            // 添加记录
            DB::table('country_worship_log')->insert([
                'country' => (int)$member['country'],
                'nickname' => (string)$member['nickname'],
                'type' => $info['id'],
                'get_value' => $get,
                'dateline' => $now,
            ]);
            if ($info['type'] === 2) {
                // 发信到国家聊天室
                $uidarr = array_map(static fn(array $v): int => (int)$v['uid'], CountryHandler::memberList((int)$member['country'], 1, 0));
                if ($uidarr !== []) {
                    $sender = Chat::sender($uid);
                    $message = Chat::getMessageStr(9, ['sacrificename' => self::LANG_NAME[$info['id']] ?? ''], $sender);
                    Chat::sendToCountry($message, $uidarr, Chat::TYPE_COUNTRY, 1, 0, 0, $sender);
                }
            }
            // 修改祭祀时间
            DB::table('member_country')->where('uid', $uid)->whereNull('deleted_at')->update(['worshipdateline' => $now]);
            // 依赖未移植：更新计划任务标志位 country_model->set_flog（消费方 cron_country 未移植）

            $after = DB::table('member')->select('gold', 'silver')->where('uid', $uid)->first();
            Redis::del('player:' . $uid);
            $return['status'] = 1;
            $return['get'] = $get;
            $return['nickname'] = (string)$member['nickname'];
            $return['type'] = $info['id'];
            $return['now_times'] = self::TIMES - ($number + 1);
            $return['all_times'] = self::TIMES;
            $return['all_gold'] = (int)$after['gold'];
            $return['all_silver'] = (int)$after['silver'];
            return $return;
        });
    }

    /**
     * 祭祀列表（get_list / get_info_by_id：银币价格 × 主公等级，加 need_vip）
     *
     * @return list<array<string, int>>
     */
    private static function worshipList(int $level): array
    {
        $list = [];
        foreach (CountryWorship::ROWS as $value) {
            if ($value['type'] === 1) {
                $value['price'] *= $level;
            }
            $value['need_vip'] = match ($value['id']) {
                2 => 3,
                3 => 5,
                default => 0,
            };
            $list[] = $value;
        }
        return $list;
    }

    /**
     * 统计今日国家祭祀次数（count_sacrifice_number）
     */
    private static function countNumber(int $country, int $now): int
    {
        return (int)DB::table('country_worship_log')->where('country', $country)
            ->where('dateline', '>=', strtotime(date('Y-m-d', $now)))->whereNull('deleted_at')->count();
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function member(int $uid): ?array
    {
        return DB::table('member')->select('uid', 'nickname', 'level', 'vip', 'country', 'gold', 'silver')->where('uid', $uid)->whereNull('deleted_at')->first();
    }
}
