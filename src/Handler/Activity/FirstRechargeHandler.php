<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Model\PubLog;
use Hanjiang\Model\FreeLeap;
use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\General\GeneralHandler;
use Hanjiang\Handler\General\TavernHandler;
use Hanjiang\MasterData\Equip as EquipTemplate;
use Hanjiang\MasterData\FirstRecharge;
use Hanjiang\MasterData\FirstRechargeLottery;
use Hanjiang\MasterData\General as GeneralTemplate;
use Hanjiang\MasterData\Pub;
use Hanjiang\Model\Chat;
use Hanjiang\Model\Equipment;
use Hanjiang\Model\FirstRechargeLog;
use Hanjiang\Model\General;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 首充返利与天命转盘（移植旧版 controllers/first_recharge.php：index / get_reward / lottery / action）
 *
 * 玩家状态存 first_recharge_log（首充元宝、已领档位、是否已抽转盘）与 member_field.first_recharge（首充图标）；
 * 首充日志由充值核心写入（Model\Pay::complete → Model\Vip::pay → FirstRechargeLog::recordRecharge）。
 * 首充紫装以上装备、天命转盘装备与 7 星以上武将发聊天室系统消息（get_message_str 33 / 34 / 35）与小喇叭。
 */
final class FirstRechargeHandler
{
    /** 领取首充奖励锁（旧版 _reward_lock，30 秒） */
    private const string REWARD_LOCK = 'first_reward_lock_';

    /** 转盘抽奖锁（旧版 _lottery_lock，30 秒） */
    private const string LOTTERY_LOCK = 'first_lottery_lock_';

    /** 转盘抽到已拥有的武将时，按酒馆紫宝石价格的该折扣返还（旧版 $rebate） */
    private const float REBATE = 0.8;

    /** 天命转盘 7 星以上武将小喇叭（general_lang.php：general_firstrecharge_get） */
    private const string LANG_GENERAL_FIRSTRECHARGE_GET = '恭喜玩家【%s】在天命转盘中获得%s星武将-%s';

    /**
     * 首充页面: c=first_recharge&m=index（旧版 first_recharge.php:index）
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $pf = (string)$ctx->input('platform', '');

        $log = self::log($uid);
        $gold = (int)($log['gold'] ?? 0);
        $status = (int)($log['status'] ?? 0);

        $rewardInfo = self::rewardList($gold, $status, $pf);
        return ['status' => 1, 'list' => $rewardInfo['list']];
    }

    /**
     * 领取首充奖励: c=first_recharge&m=get_reward（旧版 first_recharge.php:get_reward）
     *
     * 状态码：-2 参数错误，2 没有充值记录，3 已领取，4 充值元宝不足，5 档位错误，6 操作太频繁
     * 奖励类型 gettype：1 银币、2 装备、3 黄宝石、4 紫宝石、5 元宝、6 突飞卡
     */
    public static function get_reward(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $id = (int)$ctx->input('id', 0);
        $pf = (string)$ctx->input('platform', '');
        $return = ['status' => 0, 'info' => []];

        $lockKey = self::REWARD_LOCK . $uid;
        if (!self::lock($lockKey)) {
            return ['status' => 6, 'info' => [], 'msg' => '操作不要太频繁'];
        }

        if ($id <= 0) {
            Redis::del($lockKey);
            return ['status' => -2, 'info' => [], 'msg' => '传递参数错误'];
        }

        $log = self::log($uid);
        if ($log === null) {
            Redis::del($lockKey);
            // 旧版此处取了文案但没有写入返回
            return ['status' => 2, 'info' => []];
        }
        $gold = (int)$log['gold'];
        $status = (int)$log['status'];

        $rewardList = self::rewardList($gold, $status, $pf);
        if ($id !== $rewardList['rewardID']) {
            Redis::del($lockKey);
            return ['status' => 5, 'info' => [], 'msg' => '没有充值该档次的首充信息'];
        }
        // 旧版按排序后（gold 降序）的下标 id - 1 取档位
        $rewardInfo = $rewardList['list'][$id - 1];
        if ($status === $id) {
            Redis::del($lockKey);
            return ['status' => 3, 'info' => [], 'msg' => '已经领取该档次奖励'];
        }
        if ($gold < (int)$rewardInfo['gold']) {
            Redis::del($lockKey);
            return ['status' => 4, 'info' => []];
        }

        FirstRechargeLog::query()->where('uid', $uid)->whereNull('deleted_at')->update(['status' => $id, 'dateline' => time()]);

        // 不是最高档，或最高档且已抽过转盘：首充图标不再显示
        $maxInfo = self::maxInfo();
        if ((int)$rewardInfo['gold'] < (int)$maxInfo['gold'] || (int)$log['lottery'] >= 1) {
            DB::table('member_field')->where('uid', $uid)->update(['first_recharge' => 0]);
        }

        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        $allSilver = (int)($member['silver'] ?? 0);
        $allGold = (int)($member['gold'] ?? 0);
        for ($i = 1; $i <= 4; $i++) {
            $value = (int)$rewardInfo['getvalue' . $i];
            switch ((int)$rewardInfo['gettype' . $i]) {
                case 1: // 银币
                    MemberHandler::addCurrency($uid, silver: $value);
                    $allSilver += $value;
                    $return['info']['silver'] = $value;
                    $return['info']['all_silver'] = $allSilver;
                    break;
                case 2: // 装备
                    $tpl = EquipTemplate::ROWS[$value] ?? null;
                    if ($tpl !== null) {
                        Equipment::addFromTemplate($uid, $value);
                    }
                    // 发送广播到聊天室和小喇叭：只发紫装以上品质的装备
                    if ((int)($tpl['quality'] ?? 0) >= 5) {
                        $name = Chat::quality($tpl['quality'], (string)$tpl['name']);
                        $sender = Chat::sender($uid);
                        Chat::sendToAll(Chat::getMessageStr(33, ['springequipment' => $name], $sender), Chat::TYPE_SYSTEM, 0, 0, $sender);
                        Chat::sendToHorn(sprintf(Chat::LANG['chat_first_recharge_get'], $sender['nickname'] ?? '', $name), $sender);
                    }
                    $return['info']['equip'] = self::equipInfo($tpl);
                    break;
                case 3: // 黄宝石
                    TavernHandler::addJade($uid, 2, $value, 'first_recharge', 'get_reward');
                    $return['info']['jadetype'] = 2;
                    $return['info']['jadenum'] = $value;
                    break;
                case 4: // 紫宝石
                    TavernHandler::addJade($uid, 3, $value, 'first_recharge', 'get_reward');
                    $return['info']['jadetype'] = 3;
                    $return['info']['jadenum'] = $value;
                    break;
                case 5: // 元宝
                    MemberHandler::addCurrency($uid, gold: $value);
                    $allGold += $value;
                    $return['info']['gold'] = $value;
                    $return['info']['all_gold'] = $allGold;
                    break;
                case 6: // 突飞卡
                    FreeLeap::add($uid, $value);
                    break;
            }
        }
        Redis::del('player:' . $uid);
        Redis::del($lockKey);

        $return['status'] = 1;
        $return['list'] = self::rewardList($gold, $id, $pf)['list'];
        return $return;
    }

    /**
     * 天命转盘页面: c=first_recharge&m=lottery（旧版 first_recharge.php:lottery）
     *
     * lottery = 1 表示可抽（首充达到最高档且未抽过）。武将格为模板行 + talent / talent_desc / skill / skill_desc
     * （旧版整行替换，武将格没有 gettype 字段），装备格为 gettype + 装备展示字段。
     */
    public static function lottery(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $return = ['status' => 0, 'lottery' => 0, 'list' => []];

        $maxInfo = self::maxInfo();
        $log = self::log($uid);
        if ($log !== null) {
            if ((int)$log['gold'] >= (int)$maxInfo['gold']) {
                $return['lottery'] = 1;
            }
            if ((int)$log['lottery'] === 1) {
                $return['lottery'] = 0;
            }
        }

        foreach (array_values(FirstRechargeLottery::ROWS) as $key => $val) {
            $return['list'][$key]['gettype'] = $val['gettype'];
            switch ((int)$val['gettype']) {
                case 1: // 武将
                    $tpl = GeneralTemplate::ROWS[(int)$val['getvalue']] ?? null;
                    if ($tpl !== null) {
                        $talent = GeneralHandler::talentById((int)$tpl['talentid']);
                        $skill = GeneralHandler::skillById((int)$tpl['skillid']);
                        $return['list'][$key] = $tpl + [
                            'talent' => $talent['name'],
                            'talent_desc' => $talent['description'],
                            'skill' => $skill['name'],
                            'skill_desc' => $skill['description'],
                        ];
                    }
                    break;
                case 2: // 装备
                    $tpl = EquipTemplate::ROWS[(int)$val['getvalue']] ?? null;
                    if ($tpl !== null) {
                        $return['list'][$key] += self::equipInfo($tpl);
                    }
                    break;
            }
        }

        $return['status'] = 1;
        return $return;
    }

    /**
     * 转盘抽奖: c=first_recharge&m=action（旧版 first_recharge.php:action）
     *
     * 状态码：-3 武将价格信息不存在，2 操作太频繁，3 没有充值记录，4 首充元宝不足最高档，5 已抽过
     * 按 getrate 权重抽一格；武将已拥有时按酒馆紫宝石价格 * 0.8 返还紫宝石。
     */
    public static function action(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $return = ['status' => 0, 'info' => []];

        $lockKey = self::LOTTERY_LOCK . $uid;
        if (!self::lock($lockKey)) {
            $return['status'] = 2;
            return $return;
        }

        $maxInfo = self::maxInfo();
        $log = self::log($uid);
        if ($log === null) {
            Redis::del($lockKey);
            $return['status'] = 3;
            return $return;
        }
        if ((int)$log['gold'] < (int)$maxInfo['gold']) {
            Redis::del($lockKey);
            $return['status'] = 4;
            return $return;
        }
        if ((int)$log['lottery'] > 0) {
            Redis::del($lockKey);
            $return['status'] = 5;
            return $return;
        }

        $getinfo = self::lotteryRow(mt_rand(1, self::lotteryTotalRate()));

        switch ((int)$getinfo['gettype']) {
            case 1: // 武将
                $generalid = (int)$getinfo['getvalue'];
                $tpl = GeneralTemplate::ROWS[$generalid] ?? [];
                $talent = GeneralHandler::talentById((int)($tpl['talentid'] ?? 0));
                $skill = GeneralHandler::skillById((int)($tpl['skillid'] ?? 0));
                $return['info'] = $tpl;
                $return['info']['talent'] = ['name' => $talent['name'], 'description' => $talent['description']];
                $return['info']['skill'] = ['name' => $skill['name'], 'description' => $skill['description']];

                $owned = DB::table('general')->where('uid', $uid)->where('generalid', $generalid)->whereNull('deleted_at')->first();
                if ($owned !== null) {
                    // 已拥有：按酒馆紫宝石价格折价返还
                    $pub = Pub::ROWS[$generalid] ?? null;
                    if ($pub === null) {
                        Redis::del($lockKey);
                        $return['status'] = -3;
                        return $return;
                    }
                    $return['info']['jadetype'] = 3;
                    $return['info']['jadenum'] = (int)$pub['price'] * self::REBATE;
                    TavernHandler::addJade($uid, 3, (int)$return['info']['jadenum'], 'first_recharge', 'action');
                } else {
                    if (General::createFromTemplate($uid, $generalid) !== null) {
                        // 7 星以上：小喇叭 + 聊天框
                        $num = Chat::starWord((int)($tpl['star'] ?? 0));
                        if ($num !== null) {
                            $sender = Chat::sender($uid);
                            Chat::sendToHorn(sprintf(self::LANG_GENERAL_FIRSTRECHARGE_GET, $sender['nickname'] ?? '', $num, $tpl['name']), $sender);
                            $chat = Chat::getMessageStr(35, ['star_general' => $num, 'general' => $tpl['name']], $sender);
                            Chat::sendToAll($chat, Chat::TYPE_SYSTEM, 0, 0, $sender);
                        }
                        // 酒馆日志（add_pub_log(generalid, 1, 11)）
                        PubLog::add($uid, (int)$tpl['id'], 1, 11);
                    }
                }
                break;
            case 2: // 装备
                $tpl = EquipTemplate::ROWS[(int)$getinfo['getvalue']] ?? null;
                if ($tpl !== null) {
                    Equipment::addFromTemplate($uid, (int)$getinfo['getvalue']);
                }
                // 发送广播到聊天室和小喇叭（旧版不判断品级）
                $name = Chat::quality($tpl['quality'] ?? '', (string)($tpl['name'] ?? ''));
                $sender = Chat::sender($uid);
                Chat::sendToAll(Chat::getMessageStr(34, ['springequipment' => $name], $sender), Chat::TYPE_SYSTEM, 0, 0, $sender);
                Chat::sendToHorn(sprintf(Chat::LANG['chat_first_lottery_get'], $sender['nickname'] ?? '', $name), $sender);
                $return['info'] = self::equipInfo($tpl);
                break;
        }
        $return['info']['gettype'] = $getinfo['gettype'];

        FirstRechargeLog::query()->where('uid', $uid)->whereNull('deleted_at')->update(['lottery' => 1, 'dateline' => time()]);

        // 已领过最高档首充奖励：首充图标不再显示
        if ((int)$log['status'] === (int)$maxInfo['id']) {
            DB::table('member_field')->where('uid', $uid)->update(['first_recharge' => 0]);
        }
        Redis::del('player:' . $uid);
        Redis::del($lockKey);

        $return['status'] = 1;
        return $return;
    }

    /**
     * 加锁（firstrecharge_model->get_lock / add_lock：30 秒；SET NX 原子加锁，多 Worker 并发只有一个拿到）
     */
    private static function lock(string $key): bool
    {
        $redis = Redis::instance();
        return $redis !== null && (bool)$redis->set($key, '1', ['nx', 'ex' => 30]);
    }

    /**
     * 转盘总权重：Σ getrate * 100（旧版 action 中的 $rate）
     */
    public static function lotteryTotalRate(): int
    {
        $rate = 0;
        foreach (FirstRechargeLottery::ROWS as $val) {
            $rate += (int)($val['getrate'] * 100);
        }
        return $rate;
    }

    /**
     * 落点 $rand（1 ~ lotteryTotalRate）对应的转盘格：按主键顺序累加 getrate * 100，第一个 rand <= 累计值的格
     * 来源：controllers/first_recharge.php:action「抽奖操作」
     *
     * @return array<string, mixed>
     */
    public static function lotteryRow(int $rand): array
    {
        $cursor = 0;
        foreach (FirstRechargeLottery::ROWS as $val) {
            $cursor += (int)($val['getrate'] * 100);
            if ($rand <= $cursor) {
                return $val;
            }
        }
        throw new \InvalidArgumentException("转盘落点超出总权重: {$rand}");
    }

    /**
     * 首充档位列表与可领档位（旧版 first_recharge.php:get_reward_id）
     *
     * 按 gold 升序找出首充元宝可达的最高档（rewardID），再按 gold 降序输出；
     * 每档 status：1 可领取、0 不可领取、2 已领取。$pf = 'android' 时把 <em> 高亮换成客户端颜色标签。
     *
     * @return array{list: list<array<string, mixed>>, rewardID: int}
     */
    private static function rewardList(int $gold, int $status, string $pf): array
    {
        $rows = array_values(FirstRecharge::ROWS);
        usort($rows, static fn (array $a, array $b): int => [(int)$a['gold'], (int)$a['id']] <=> [(int)$b['gold'], (int)$b['id']]);
        $maxGold = (int)self::maxInfo()['gold'];

        $rewardID = 0;
        foreach ($rows as &$val) {
            // 旧版 lang firstrecharge_name / firstrecharge_lottery
            $perNum = (int)$val['getvalue1'] / (int)$val['gold'] * 100;
            $val['name'] = sprintf('首充%s元宝，赠送<em class="red">%s</em>元宝(额度<em class="red">%s</em>)', $val['gold'], $val['getvalue1'], $perNum . '%');
            if ((int)$val['gold'] === $maxGold) {
                $val['name'] .= '，可开启一次<em class="red">天命转盘</em>';
            }
            if ($pf === 'android') {
                $val['name'] = str_replace(['<em class="red">', '</em>'], ['[color=ffff0000]', '[/color]'], $val['name']);
            }
            if ($gold >= (int)$val['gold']) {
                $rewardID = (int)$val['id'];
            }
        }
        unset($val);

        usort($rows, static fn (array $a, array $b): int => [(int)$b['gold'], (int)$a['id']] <=> [(int)$a['gold'], (int)$b['id']]);

        foreach ($rows as &$v) {
            for ($i = 1; $i <= 4; $i++) {
                if ((int)$v['gettype' . $i] === 2) {
                    $v['equip'] = self::equipInfo(EquipTemplate::ROWS[(int)$v['getvalue' . $i]] ?? null);
                }
            }
            $v['status'] = $rewardID === (int)$v['id'] ? 1 : 0;
            if ($status === (int)$v['id']) {
                $v['status'] = 2;
            }
        }
        unset($v);

        return ['list' => $rows, 'rewardID' => $rewardID];
    }

    /**
     * 首充最高档（旧版 firstrecharge_model::get_maxgold_reward：SELECT *, MAX(gold) 不带 GROUP BY，
     * 取到的是主键第一行；该行 gold 即为最高档 6480）
     *
     * @return array<string, mixed>
     */
    private static function maxInfo(): array
    {
        return FirstRecharge::ROWS[array_key_first(FirstRecharge::ROWS)];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function log(int $uid): ?array
    {
        return FirstRechargeLog::query()->where('uid', $uid)->whereNull('deleted_at')->first();
    }

    /**
     * 装备展示字段（旧版 first_recharge.php 中 id / image / str / type / quality / needlevel / power / desc）
     *
     * @param array<string, mixed>|null $tpl MasterData Equip 行
     * @return array<string, mixed>
     */
    private static function equipInfo(?array $tpl): array
    {
        return [
            'id' => $tpl['id'] ?? null,
            'image' => $tpl['id'] ?? null,
            'str' => $tpl['name'] ?? null,
            'type' => $tpl['type'] ?? null,
            'quality' => $tpl['quality'] ?? null,
            'needlevel' => $tpl['needlevel'] ?? null,
            'power' => $tpl['power'] ?? null,
            'desc' => $tpl['description'] ?? null,
        ];
    }
}
