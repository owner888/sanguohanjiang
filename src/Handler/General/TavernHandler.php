<?php

declare(strict_types=1);

namespace Hanjiang\Handler\General;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\MasterData\General as GeneralTemplate;
use Hanjiang\MasterData\Pub;
use Hanjiang\MasterData\Skill;
use Hanjiang\MasterData\Talent;
use Hanjiang\MasterData\Trader;
use Hanjiang\Model\Chat;
use Hanjiang\Model\DailyTask;
use Hanjiang\Model\Expostulation;
use Hanjiang\Model\FreeLeap;
use Hanjiang\Model\General;
use Hanjiang\Model\Member;
use Hanjiang\Model\PubLog;
use Hanjiang\Model\StatPay;
use Hanjiang\Model\TraderLog;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 酒馆（移植旧版 controllers/tavern.php + models/tavern_model.php）
 *
 * 贸易（黑市商人）：商人取 MasterData Trader（cost 银币 / trading 元宝、goto1 ~ 5 转到下一位商人的概率、get1 ~ 3 获得蓝 / 黄 / 紫
 * 宝石的概率、getvalue 数量），玩家的宝石与商人位置存 trader_log（Model\TraderLog）；每天第一次银币贸易免费。批量贸易花 1000 万银币。
 * 招募：名将取 MasterData Pub（tab 1 征服榜：须先在征战中击败（pub_log）、2 名将榜、3 突飞卡、4 英雄祭坛：须主公 150 级且扣声望），
 * 用宝石购买；招到的武将记 pub_log（type 1 酒馆），7 星以上发小喇叭。
 * 客户端 tradepop.js / barpop.js 调用 index、trade（type、s）、trade_batch、get_list（page、tab）、buy（generalid、num）。
 * 主公 31 级开启。
 *
 * 旧版首次打开记录 edifice_model->add_edifice_log（5 / 9 / 10）只写日志、不影响返回，不移植。
 * 与旧版的差别：贸易 / 购买在玩家行锁里执行（宝石、免费次数与扣费不会被并发绕过）；宝石不足以外的支付失败与旧版一样返回 2。
 */
final class TavernHandler
{
    /** 开启等级 */
    private const int OPEN_LEVEL = 31;

    /** 批量贸易花费 1000 万银币 */
    private const int BATCH_SILVER = 10000000;

    /** 英雄祭坛（tab 4）开放的主公等级 */
    private const int ALTAR_LEVEL = 150;

    /** 旧版语言包 general_lang.php general_get */
    private const string LANG_GENERAL_GET = '恭喜玩家【%s】获得%s星武将-%s';

    /**
     * 初始化接口: c=tavern&m=index
     */
    public static function index(Context $ctx): array
    {
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return ['status' => 0];
        }
        if ((int)$member['level'] < self::OPEN_LEVEL) {
            return ['status' => 5];
        }
        return self::info((int)$member['uid']);
    }

    /**
     * 交易: c=tavern&m=trade（type 1 元宝强行贸易、其他银币；s 要去的商人位置）
     */
    public static function trade(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $type = (int)$ctx->input('type', 0);
        $site = (int)$ctx->input('s', 0);
        $member = self::member($uid);
        if ($member === null) {
            return ['status' => 0];
        }
        if ((int)$member['level'] < self::OPEN_LEVEL) {
            return ['status' => 5];
        }
        $now = time();
        return Member::locked($uid, static function () use ($uid, $type, $site, $now): array {
            $return = [];
            $member = self::member($uid);
            // 得到交易规则
            $rule = self::tavernRule($uid, $type, $site, $now);
            // 金币交易
            $usefree = 0;
            if ($type === 1) {
                if ((int)$member['gold'] < $rule['now']['trading']) {
                    // 金币不够
                    return ['status' => -1];
                }
            } elseif ($rule['isfree'] !== 1) {
                // 银币交易
                if ((int)$member['silver'] < $rule['now']['cost']) {
                    // 银币不够
                    return ['status' => -2];
                }
            } else {
                $usefree = 1;
            }
            // 修改位置和增加宝石
            $addstatus = TraderLog::addJade($uid, $rule['gettype'], $rule['now']['getvalue'], $rule['site'], $rule['to_site'], $usefree, 'tavern', 'trade', $now);
            // 减少银币or金币
            if ($type === 1) {
                Member::deductCurrency($uid, gold: $rule['now']['trading']);
                // 消费统计记录
                StatPay::record($uid, 'maoyi', $rule['now']['trading']);
            } elseif ($rule['isfree'] !== 1) {
                Member::deductCurrency($uid, silver: $rule['now']['cost']);
            }
            $after = self::member($uid);
            $return['id'] = $rule['new']['id'];
            $return['cost'] = $rule['new']['cost'];
            $return['trading'] = $rule['new']['trading'];
            $return['status'] = 1;
            $return['silver'] = (int)$after['silver'];
            $return['gold'] = (int)$after['gold'];
            $return['number'] = $addstatus['num'];
            $return['addnum'] = $rule['now']['getvalue'];
            $return['site'] = $rule['site'];
            $return['isfree'] = $usefree !== 1 && $rule['isfree'] === 1 ? 1 : 0;
            $return['addtype'] = $rule['gettype'];
            for ($i = 1; $i <= 5; $i++) {
                $return['site' . $i] = $addstatus['site' . $i] ?? null;
            }
            // 日常任务(11是贸易对应的类型)
            if ((int)$member['level'] >= DailyTask::OPEN_LEVEL) {
                DailyTask::action($uid, DailyTask::TYPE_TRADER, 1, $now);
            }
            // 帮派任务(谏言：4是贸易对应的类型)
            if ((int)$member['country'] > 0) {
                Expostulation::action((int)$member['country'], 4);
            }
            return $return;
        });
    }

    /**
     * 批量贸易: c=tavern&m=trade_batch（花 1000 万银币，一次得到约 1000 次贸易的宝石，商人位置随机重置）
     */
    public static function trade_batch(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = self::member($uid);
        if ($member === null) {
            return [];
        }
        if ((int)$member['level'] < self::OPEN_LEVEL) {
            return ['status' => -1];
        }
        $now = time();
        return Member::locked($uid, static function () use ($uid, $now): array {
            $return = [];
            $member = self::member($uid);
            // 银币交易：减少银币
            if (!Member::deductCurrency($uid, silver: self::BATCH_SILVER)) {
                // 银币不够
                $return['status'] = -2;
                return $return;
            }
            // 获得奖励数据
            $result = self::tradeBatch();
            // 增加宝石
            TraderLog::addJade($uid, 1, $result['get1'], class: 'tavern', method: 'trade_batch', now: $now);
            TraderLog::addJade($uid, 2, $result['get2'], class: 'tavern', method: 'trade_batch', now: $now);
            TraderLog::addJade($uid, 3, $result['get3'], class: 'tavern', method: 'trade_batch', now: $now);
            // 重置商人位置
            $pos = mt_rand(1, 5);
            $siteArr = ['site2' => 0, 'site3' => 0, 'site4' => 0, 'site5' => 0, 'site1' => 1];
            $siteArr['site' . $pos] = 1;
            $siteArr['site'] = $pos;
            DB::table('trader_log')->where('uid', $uid)->whereNull('deleted_at')->update($siteArr);
            $jade = self::info($uid);
            $after = self::member($uid);
            $return['status'] = 1;
            $return['silver'] = (int)$after['silver'];
            $return['gold'] = (int)$after['gold'];
            foreach ($siteArr as $key => $value) {
                $return[$key] = $value;
            }
            $return['isfree'] = $jade['isfree'];
            $return['site'] = $pos;
            $return['get'] = $result;
            $return['info'] = [1 => $jade['get1'], 2 => $jade['get2'], 3 => $jade['get3']];
            $return['cost'] = $jade['site_info'][$pos]['cost'];
            $return['trading'] = $jade['site_info'][$pos]['trading'];
            $times = mt_rand(530, 570);
            // 日常任务(11是贸易对应的类型)
            if ((int)$member['level'] >= DailyTask::OPEN_LEVEL) {
                DailyTask::action($uid, DailyTask::TYPE_TRADER, $times, $now);
            }
            // 帮派任务(谏言：4是贸易对应的类型)
            if ((int)$member['country'] > 0) {
                Expostulation::action((int)$member['country'], 4, $times);
            }
            return $return;
        });
    }

    /**
     * 购买: c=tavern&m=buy（generalid 将领模板id；突飞卡 tab 3 可带 num 批量，最多 100）
     */
    public static function buy(Context $ctx): array
    {
        /**
         * 接口返回值
         * -4 扣除声望失败
         * -3 声望不足
         * -2 未击败该武将
         * -1 添加将领失败
         *  0 失败
         *  1 成功
         *  2 购买失败
         *  3 翡翠不足
         *  4 已拥有该武将
         *  5 未开启
         */
        $return = ['status' => 0, 'price' => 0, 'type' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        $member = self::member($uid);
        if ($member === null) {
            return $return;
        }
        if ((int)$member['level'] < self::OPEN_LEVEL) {
            $return['status'] = 5;
            return $return;
        }
        $generalid = (int)$ctx->input('generalid', 0);
        $leapNum = min(100, (int)$ctx->input('num', 0));
        $now = time();
        return Member::locked($uid, static function () use ($uid, $generalid, $leapNum, $now, $return): array {
            $member = self::member($uid);
            // [将领model]获取已拥有武将（按模板id）
            if (DB::table('general')->where('uid', $uid)->where('generalid', $generalid)->whereNull('deleted_at')->value('id') !== null) {
                $return['status'] = 4;
                return $return;
            }
            // [酒馆模型]支付宝石
            $result = self::pay($member, $generalid, $leapNum, $now);
            if ($result['status'] !== 1) {
                $return['status'] = match ($result['status']) {
                    -4 => 3,
                    -6 => -2,
                    -7 => -3,
                    -8 => -4,
                    default => 2,
                };
                return $return;
            }
            $info = [];
            if ($result['tab'] !== 3) {
                // [将领模型]添加将领(generalid)
                $info = General::addGeneral($uid, $generalid);
                if ($info === null) {
                    // 添加将领失败返回-1（旧版宝石与酒馆日志已扣 / 已记，照旧版）
                    $return['status'] = -1;
                    return $return;
                }
                $star = Chat::starWord((int)$info['star']);
                if ($star !== null) {
                    $sender = Chat::sender($uid);
                    Chat::sendToHorn(sprintf(self::LANG_GENERAL_GET, (string)$member['nickname'], $star, $info['name']), $sender);
                }
                unset($info['status']);
            }
            $return['status'] = 1;
            $return['price'] = $result['price'];
            $return['type'] = $result['paytype'];
            $return['jade'] = $result['jade'];
            $return['general'] = $info;
            $return['all_reputation'] = (int)self::member($uid)['reputation'];
            Redis::del('player:' . $uid);
            return $return;
        });
    }

    /**
     * 获得商品列表: c=tavern&m=get_list（page、tab 1 征服榜 / 2 名将榜 / 3 突飞卡 / 4 英雄祭坛；每页固定 100）
     */
    public static function get_list(Context $ctx): array
    {
        /**
         * 接口返回值
         * -3 获得翡翠数量失败
         * -1 获取价格列表失败 / 等级小于150无法进入英雄祭坛
         *  0 失败
         *  1 成功
         *  5 未开启
         */
        $return = ['status' => 0, 'list' => [], 'jade' => []];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        $uid = (int)$member['uid'];
        $page = (int)$ctx->input('page', 0);
        $perpage = 100;
        $tab = (int)$ctx->input('tab', 0);
        if ((int)$member['level'] < self::OPEN_LEVEL) {
            $return['status'] = 5;
            return $return;
        }
        // [酒馆模型]获得宝石数量
        $jade = self::info($uid);
        $return['jade'][1] = $jade['get1'];
        $return['jade'][2] = $jade['get2'];
        $return['jade'][3] = $jade['get3'];
        // [酒馆模型]获得商品价格列表,按position顺序排序
        $list = self::priceList($page, $perpage, $tab);
        if ($list['status'] !== 1) {
            // 获取价格列表失败返回-1
            $return['status'] = -1;
            return $return;
        }
        if ((int)$member['level'] < self::ALTAR_LEVEL && $tab === 4) {
            // 等级小于150，无法进入神将台
            $return['status'] = -1;
            return $return;
        }
        $info = null;
        if ($tab !== 3) {
            // 获取酒馆招募记录
            $ownIdList = [];
            $openIdList = [];
            foreach (DB::table('pub_log')->select('generalid', 'own')->where('uid', $uid)->whereNull('deleted_at')->get() as $value) {
                if (in_array($tab, [1, 2, 4], true) && (int)$value['own'] === 1) {
                    $ownIdList[] = (int)$value['generalid'];
                }
                if ($tab === 1) {
                    $openIdList[] = (int)$value['generalid'];
                }
            }
            $info = [];
            foreach ($list['list'] as $key => $value) {
                // 标记是否已拥有该将领
                $value['isown'] = in_array($value['generalid'], $ownIdList, true) ? 1 : 0;
                if ($tab === 1) {
                    $value['open'] = in_array($value['generalid'], $openIdList, true) ? 1 : 0;
                }
                // [将领模型]将领模板信息（技能 / 天赋只取 id、name、description）
                $tpl = GeneralTemplate::ROWS[$value['generalid']] ?? null;
                if ($tpl !== null) {
                    $value['info'] = [
                        'image' => $tpl['image'],
                        'name' => $tpl['name'],
                        'type' => $tpl['type'],
                        'wuli' => $tpl['wuli'],
                        'zhili' => $tpl['zhili'],
                        'tili' => $tpl['tili'],
                        'star' => $tpl['star'],
                        'skill' => self::ext(Skill::ROWS[$tpl['skillid']] ?? null),
                        'talent' => self::ext(Talent::ROWS[$tpl['talentid']] ?? null),
                    ];
                }
                $info[$key] = $value;
            }
        }
        $return['page'] = $list['page'];
        $return['perpage'] = $list['perpage'];
        $return['totals'] = $list['totals'];
        $return['list'] = $info ?? $list['list'];
        $return['reputation'] = (int)$member['reputation'];
        $return['status'] = 1;
        return $return;
    }

    /**
     * 增加宝石（旧版 models/tavern_model.php:add_jade 的 site = 0 用法：只累加 get{type}，不动商人位置）
     *
     * 存储与流水见 Model\TraderLog（trader_log / tavernjade_log；$class / $method 为流水的执行类与方法，旧版取当前请求）。
     *
     * @param int $type 1 蓝宝石、2 黄宝石、3 紫宝石
     * @return int 增加后的该类宝石数量
     */
    public static function addJade(int $uid, int $type, int $num, string $class = '', string $method = ''): int
    {
        $result = TraderLog::addJade($uid, $type, $num, class: $class, method: $method);
        Redis::del('player:' . $uid);
        return $result['status'] === 1 ? (int)$result['num'] : TraderLog::jade($uid, $type);
    }

    /**
     * 宝石数量与商人位置（get_info：没有记录时位置 1、全部免费；site1 恒为 1；site_info 为全部商人）
     *
     * @return array<string, mixed>
     */
    private static function info(int $uid): array
    {
        $return = ['status' => 1, 'get1' => 0, 'get2' => 0, 'get3' => 0, 'isfree' => 0, 'site' => []];
        $result = TraderLog::get($uid);
        if ($result !== null) {
            $return['get1'] = $result['get1'];
            $return['get2'] = $result['get2'];
            $return['get3'] = $result['get3'];
            // 是否免费
            if (date('ymd', $result['dateline']) !== date('ymd', time())) {
                $return['isfree'] = 1;
            }
            $site = $result['site'];
            for ($i = 1; $i <= 5; $i++) {
                $return['site' . $i] = $result['site' . $i];
            }
        } else {
            // 没有找到数据
            $return['isfree'] = $site = 1;
            for ($i = 1; $i <= 5; $i++) {
                $return['site' . $i] = 0;
            }
        }
        $return['site'] = $site;
        $return['site1'] = 1;
        foreach (Trader::ROWS as $val) {
            $return['site_info'][$val['id']] = [
                'id' => $val['id'],
                'name' => $val['name'],
                'description' => $val['description'],
                'cost' => $val['cost'],
                'trading' => $val['trading'],
            ];
        }
        return $return;
    }

    /**
     * 贸易规则（get_tavern_rule：返回下一步在哪儿，和这次能得到什么）
     *
     * @return array{isfree: int, to_site: int, gettype: int, site: int, now: array<string, mixed>, new: array<string, mixed>}
     */
    private static function tavernRule(int $uid, int $type, int $toSite, int $now): array
    {
        $return = ['isfree' => 1];
        $siteArr = [];
        $result = TraderLog::get($uid);
        if ($result !== null) {
            // 是否免费
            if (date('ymd', $result['dateline']) === date('ymd', $now)) {
                $return['isfree'] = 0;
            }
            $site = $result['site'];
            for ($i = 1; $i <= 5; $i++) {
                if ($result['site' . $i] === 1) {
                    $siteArr[] = $i;
                }
            }
        } else {
            $site = 1;
        }
        if ($toSite && in_array($toSite, $siteArr, true)) {
            $site = $toSite;
        }
        $return['to_site'] = $site;
        // 商人（旧版位置 0 时取不到商人；这里按 1 号商人，新档的位置都是 1）
        $shangren = Trader::ROWS;
        if (!isset($shangren[$site])) {
            $site = 1;
        }
        // 得到下次会不会转到下个商人
        $rands = mt_rand(1, 100);
        $newsite = 1;
        $cursor = 0;
        for ($i = 1; $i <= 5; $i++) {
            $cursor += $shangren[$site]['goto' . $i];
            if ($rands <= $cursor) {
                $newsite = $i;
                break;
            }
        }
        // 如果是金币消耗，必须进入下一级
        if ($type === 2) {
            $site = 1;
        }
        if ($type === 1) {
            $newsite = $site + 1 >= 6 ? 5 : $site + 1;
        }
        // 得到这次会得到什么奖励
        $total = 0;
        for ($i = 1; $i <= 3; $i++) {
            $total += $shangren[$site]['get' . $i];
        }
        $rand = mt_rand(1, $total);
        $gettype = 1;
        $cursor = 0;
        for ($i = 1; $i <= 3; $i++) {
            $cursor += $shangren[$site]['get' . $i];
            if ($rand <= $cursor) {
                $gettype = $i;
                break;
            }
        }
        $return['gettype'] = $gettype;
        $return['site'] = $newsite;
        $return['now'] = $shangren[$site];
        $return['new'] = $shangren[$newsite];
        return $return;
    }

    /**
     * 批量贸易获得的宝石（get_trade_batch：蓝 500 ~ 600、黄 100 ~ 130、紫 10 ~ 25，按折算总量修正蓝宝石）
     *
     * @return array{get1: int, get2: int, get3: int}
     */
    private static function tradeBatch(): array
    {
        $get1 = mt_rand(500, 600);
        $get2 = mt_rand(100, 130);
        $get3 = mt_rand(10, 25);
        $min = $get1 + $get2 * 3 + $get3 * 2.5;
        $max = $get1 + $get2 * 4 + $get3 * 4;
        if ($min > 1000) {
            $get1 -= $min - 900;
        }
        if ($max < 1000) {
            $get1 += 1100 - $max;
        }
        return ['get1' => (int)$get1, 'get2' => (int)$get2, 'get3' => (int)$get3];
    }

    /**
     * 商品列表（get_price_list：tab 1 ~ 4，按 position 排序分页，键从 1 开始；突飞卡带 times 张数）
     *
     * @return array{status: int, list: array<int, array<string, mixed>>, page?: int, perpage?: int, totals?: int}
     */
    private static function priceList(int $page, int $perpage, int $tab): array
    {
        if (!in_array($tab, [1, 2, 3, 4], true)) {
            return ['status' => -2, 'list' => []];
        }
        $page = $page > 0 ? $page : 1;
        $perpage = $perpage > 3 ? $perpage : 3;
        $rows = array_values(array_filter(Pub::ROWS, static fn (array $row): bool => $row['tab'] === $tab));
        usort($rows, static fn (array $a, array $b): int => [$a['position'], $a['generalid']] <=> [$b['position'], $b['generalid']]);
        $totals = count($rows);
        $pages = (int)ceil($totals / $perpage) > 0 ? (int)ceil($totals / $perpage) : 1;
        $page = min($page, $pages);
        $list = array_slice($rows, ($page - 1) * $perpage, $perpage);
        if ($list === []) {
            return ['status' => -1, 'list' => []];
        }
        $result = [];
        foreach ($list as $key => $value) {
            if ($tab === 3) {
                $value['times'] = self::leapTimes($value['paytype']);
            }
            $result[$key + 1] = $value;
        }
        return ['status' => 1, 'list' => $result, 'page' => $page, 'perpage' => $perpage, 'totals' => $totals];
    }

    /**
     * 支付（pay）：征服榜须先击败（pub_log）、英雄祭坛扣声望；招募武将记 pub_log（type 1）、突飞卡加免费突飞次数
     *
     * @param array<string, mixed> $member
     * @return array{status: int, paytype?: int, jade?: int, price?: int, tab?: int}
     *         status：-8 扣除声望失败；-7 声望不足；-6 未击败该武将 / 增加突飞卡失败；-5 添加酒馆日志错误；-4 宝石不足；
     *         -2 商品不存在；2 将领已招募；1 成功
     */
    private static function pay(array $member, int $generalid, int $leapNum, int $now): array
    {
        $uid = (int)$member['uid'];
        // 获得花费
        $cash = Pub::ROWS[$generalid] ?? null;
        if ($cash === null) {
            return ['status' => -2];
        }
        $tab = $cash['tab'];
        $paytype = $cash['paytype'];
        $price = $cash['price'];
        if ($leapNum) {
            $price *= $leapNum;
        }
        if ($tab === 1 && PubLog::byGeneral($uid, $generalid) === []) {
            return ['status' => -6];
        }
        // 判断声望
        if ($tab === 4 && $cash['need_reputation'] && (int)$member['reputation'] < $cash['need_reputation']) {
            return ['status' => -7];
        }
        // 支付宝石（旧版 $price 为负数时 sub_jade 等于加宝石；这里按宝石不足处理）
        if ($price < 0) {
            return ['status' => -4];
        }
        $result = TraderLog::subJade($uid, $paytype, $price, 'tavern', 'buy', $now);
        if ($result['status'] !== 1) {
            return ['status' => $result['status'] === -1 ? -4 : -3];
        }
        // 扣除声望
        if ($tab === 4 && $cash['need_reputation'] && Member::subReputation($uid, $cash['need_reputation']) === null) {
            return ['status' => -8];
        }
        if (in_array($tab, [1, 2, 4], true)) {
            $add = PubLog::add($uid, $generalid, 1, 1, $now);
            if ($add !== 1) {
                // 将领已招募 2 / 添加酒馆日志错误 -5
                return ['status' => $add === 2 ? 2 : -5];
            }
        }
        if ($tab === 3) {
            $num = self::leapTimes($paytype);
            if ($leapNum) {
                $num *= $leapNum;
            }
            if (FreeLeap::add($uid, $num, $now) !== 1) {
                // 增加免费突飞次数失败
                return ['status' => -6];
            }
        }
        return ['status' => 1, 'paytype' => $paytype, 'jade' => $result['jade'], 'price' => $price, 'tab' => $tab];
    }

    /**
     * 突飞卡张数（支付类型 1 / 2 / 3 → 1 / 2 / 4 张）
     */
    private static function leapTimes(int $paytype): int
    {
        return match ($paytype) {
            2 => 2,
            3 => 4,
            default => 1,
        };
    }

    /**
     * 技能 / 天赋的输出字段（id、name、description；不存在时为空串）
     *
     * @param array<string, mixed>|null $row
     * @return array{id: mixed, name: mixed, description: mixed}
     */
    private static function ext(?array $row): array
    {
        return [
            'id' => $row['id'] ?? '',
            'name' => $row['name'] ?? '',
            'description' => $row['description'] ?? '',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function member(int $uid): ?array
    {
        return $uid > 0 ? DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first() : null;
    }
}
