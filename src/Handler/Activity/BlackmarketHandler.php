<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\MasterData\Blackmarket;
use Hanjiang\MasterData\General as GeneralTemplate;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\Chat;
use Hanjiang\Model\FreeLeap;
use Hanjiang\Model\General;
use Hanjiang\Model\Member;
use Hanjiang\Model\Pay;
use Hanjiang\Model\PubLog;
use Hanjiang\Model\TraderLog;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 运营活动：黑市（移植旧版 controllers/blackmarket.php + models/blackmarket_model.php）
 *
 * 客户端（blackmarketpop.js）调用：index / refresh / lock(site, status) / buy(id)。活动时间取 ActivityTime 的 blackmarket
 * （旧版数据 2016-12-31 已结束，接口回 2）；当前时间取 BossClock（生产恒为 time()）。
 * 每人一个 3 格货架（blackmarket_log）：商品取 MasterData Blackmarket 按 rate 权重抽（排除已在货架上的与已拥有的武将），
 * 锁定的格子刷新时保留；每天 5 次免费刷新，之后每次 10 元宝。商品：1 银币（value × 主公等级）、2 / 3 / 4 突飞卡（× 1 / 2 / 4）、
 * 5 武将（7 星以上发小喇叭，聊天室系统消息 14）、6 军令；价格：1 银币（buy_value × 主公等级，不足 26 级按 26）、2 元宝、
 * 3 / 4 / 5 蓝 / 黄 / 紫宝石（5 另要扣声望 reputation）。买完的格子立即补一个新商品。
 * 记录：blackmarket_buy_log、blackmarket_refresh（刷新 / 购买后的货架）、blackmarket_action_log（刷新人次与元宝）。
 *
 * 与旧版的差别：refresh / lock / buy 在 Member::locked（玩家行锁）里执行。
 */
final class BlackmarketHandler
{
    /** 运营活动名（ActivityTime.name） */
    private const string ACTIVITY = 'blackmarket';
    /** 免费刷新次数（blackmarket_model->$times） */
    private const int TIMES = 5;
    /** 需要随机几个商品（$num） */
    private const int NUM = 3;
    /** 刷新价格（$price） */
    private const int PRICE = 10;
    /** 银币价格的最低等级系数 */
    private const int MIN_LEVEL = 26;
    /** 酒馆日志获取方式：黑市 */
    private const int PUB_LOG_TYPE = 6;
    /** 小喇叭（general_lang） */
    private const string LANG_GENERAL_GET = '【%s】鸿运当头，在黑市中以【%s折】购买了 %s星武将-%s';
    /** 货架记录字段 */
    private const array LOG_FIELDS = ['uid', 'times', 'site1', 'site2', 'site3', 'site1_status', 'site2_status', 'site3_status', 'dateline'];

    /**
     * 初始化接口: c=blackmarket&m=index
     */
    public static function index(Context $ctx): array
    {
        $return = ['status' => 0];
        $now = BossClock::now();
        $activity = Pay::activity(self::ACTIVITY, $now);
        // 判断活动是否结束
        if ($activity === null) {
            $return['status'] = 2;
            return $return;
        }
        $uid = MemberHandler::resolveUid($ctx);
        $member = self::member($uid);
        // 获取用户信息
        $info = $member === null ? null : Member::locked($uid, static fn(): ?array => self::getInfo($uid, $now));
        if ($info === null) {
            $return['status'] = -1;
            return $return;
        }
        $level = (int)$member['level'];
        // 奖励处理
        $gid = [];
        foreach ($info['list'] as $key => $val) {
            if ($val['type'] === 5) {
                $gid[$key] = $val['value'];
            }
            if ($val['type'] === 1) {
                $info['list'][$key]['image'] = 0;
                $info['list'][$key]['value'] = $val['value'] * $level;
            }
            if ($val['buy_type'] === 1) {
                $info['list'][$key]['buy_value'] = $val['buy_value'] * max(self::MIN_LEVEL, $level);
            }
        }
        // 查询将领资源id
        foreach ($gid as $key => $val) {
            $info['list'][$key]['image'] = GeneralTemplate::ROWS[$val]['image'] ?? null;
        }
        // 获取用户资源数据（宝石数量）
        $jade = TraderLog::get($uid);
        unset($info['info']['site1'], $info['info']['site2'], $info['info']['site3']);
        $return['status'] = 1;
        $return['info'] = $info['info'];
        $return['info']['type'] = $activity['type'];
        $return['info']['get1'] = $jade['get1'] ?? 0;
        $return['info']['get2'] = $jade['get2'] ?? 0;
        $return['info']['get3'] = $jade['get3'] ?? 0;
        $return['info']['price'] = self::PRICE;
        $return['info']['cd'] = (int)$activity['endtime'] - $now;
        $return['info']['reputation'] = (int)$member['reputation'];
        $return['list'] = $info['list'];
        return $return;
    }

    /**
     * 刷新: c=blackmarket&m=refresh
     */
    public static function refresh(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        return Member::locked($uid, static function () use ($uid): array {
            $return = ['status' => 0];
            $now = BossClock::now();
            // 判断活动是否结束
            if (Pay::activity(self::ACTIVITY, $now) === null) {
                $return['status'] = 2;
                return $return;
            }
            $member = self::member($uid);
            // 查询用户信息
            $memberInfo = self::memberInfo($uid);
            if ($memberInfo === null || $member === null) {
                $return['status'] = -1;
                return $return;
            }
            // 判断修改每日免费刷新次数
            if (date('ymd', $memberInfo['dateline']) !== date('ymd', $now)) {
                self::updateTimes($uid, self::TIMES, [], $now);
                $memberInfo['times'] = self::TIMES;
            }
            // 判断刷新次数和剩余金币
            if ($memberInfo['times'] === 0 && (int)$member['gold'] < self::PRICE) {
                $return['status'] = -2;
                return $return;
            }
            $num = 0;
            $itemArr = [];
            for ($i = 1; $i <= self::NUM; $i++) {
                $itemArr[$i] = ['id' => $memberInfo['site' . $i], 'status' => $memberInfo['site' . $i . '_status']];
                if ($memberInfo['site' . $i . '_status'] === 1) {
                    $num += 1;
                }
            }
            // 判断已存在商品是否都是锁定状态，是就不能继续刷新
            if ($num === self::NUM) {
                $return['status'] = -3;
                return $return;
            }
            $tempList = self::listByRate($uid, $itemArr);
            if ($tempList === null) {
                $return['status'] = -4;
                return $return;
            }
            if ($memberInfo['times'] > 0) {
                $updateTimes = $memberInfo['times'] - 1;
            } else {
                $updateTimes = 0;
                // 减少金币
                if (!Member::deductCurrency($uid, gold: self::PRICE)) {
                    $return['status'] = -2;
                    return $return;
                }
            }
            [$list, $data] = self::shelfList($tempList, (int)$member['level']);
            self::updateTimes($uid, $updateTimes, $data, $now);
            $return['status'] = 1;
            $return['times'] = $updateTimes;
            $return['all_gold'] = (int)DB::table('member')->where('uid', $uid)->value('gold');
            $return['price'] = self::PRICE;
            $return['list'] = $list;
            // 刷新后的记录（1:免费刷新，2:金币刷新）
            $refresh = ['status' => $memberInfo['times'] > 0 ? 1 : 2];
            foreach ($list as $key => $val) {
                $refresh['site' . $key] = $val['id'];
            }
            self::addRefreshLog($uid, (int)$member['level'], $refresh, 'refresh', $now);
            // 记录参加人次（免费刷新记 0 元宝）
            DB::table('blackmarket_action_log')->insert([
                'uid' => $uid,
                'gold' => $refresh['status'] === 1 ? 0 : self::PRICE,
                'level' => (int)$member['level'],
                'dateline' => $now,
            ]);
            return $return;
        });
    }

    /**
     * 锁定|解锁: c=blackmarket&m=lock&site=1~3&status=1 锁定|2 解锁
     */
    public static function lock(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $site = (int)$ctx->input('site', 0);
        $status = (int)$ctx->input('status', 0);
        return Member::locked($uid, static function () use ($uid, $site, $status): array {
            $return = ['status' => 0];
            // 判断活动是否结束
            if (Pay::activity(self::ACTIVITY, BossClock::now()) === null) {
                $return['status'] = 2;
                return $return;
            }
            // 判断参数
            if (!$site || !$status || !in_array($site, range(1, 3), true)) {
                $return['status'] = -1;
                return $return;
            }
            // 查询用户信息
            $memberInfo = self::memberInfo($uid);
            if ($memberInfo === null) {
                $return['status'] = -2;
                return $return;
            }
            // 判断该商品是否已锁定,或是已解锁
            if (($status === 1 && $memberInfo['site' . $site . '_status'] === 1) || ($status === 2 && $memberInfo['site' . $site . '_status'] === 0)) {
                $return['status'] = -3;
                return $return;
            }
            // 执行操作(状态2就是解锁状态)
            if ($status === 2) {
                $status = 0;
            }
            $updated = DB::table('blackmarket_log')->where('uid', $uid)->whereNull('deleted_at')->update(['site' . $site . '_status' => $status]);
            // 判断是否修改成功
            if ($updated <= 0) {
                $return['status'] = -3;
                return $return;
            }
            $return['status'] = 1;
            return $return;
        });
    }

    /**
     * 购买: c=blackmarket&m=buy&id=商品id
     */
    public static function buy(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $id = (int)$ctx->input('id', 0);
        return Member::locked($uid, static function () use ($uid, $id): array {
            $return = [];
            $now = BossClock::now();
            // 判断活动是否结束
            if (Pay::activity(self::ACTIVITY, $now) === null) {
                $return['status'] = 2;
                return $return;
            }
            $member = self::member($uid);
            // 查询用户信息
            $memberInfo = self::memberInfo($uid);
            if ($memberInfo === null || $member === null) {
                $return['status'] = -1;
                return $return;
            }
            // 判断该商品是否在自己的商品列表里面
            $itemArr = [];
            for ($i = 1; $i <= self::NUM; $i++) {
                $itemArr[$i] = $memberInfo['site' . $i];
            }
            if (!in_array($id, $itemArr, true)) {
                $return['status'] = -2;
                return $return;
            }
            // 按id查询商品信息
            $itemInfo = Blackmarket::ROWS[$id] ?? null;
            if ($itemInfo === null) {
                $return['status'] = -3;
                return $return;
            }
            $level = (int)$member['level'];
            // 判断购买该商品的资源是否足够
            $buyLevel = max(self::MIN_LEVEL, $level);
            $jade = TraderLog::get($uid);
            if ($itemInfo['buy_type'] === 1 && (int)$member['silver'] < $itemInfo['buy_value'] * $buyLevel) {
                // 所需银币不足
                $return['status'] = -4;
                return $return;
            } elseif ($itemInfo['buy_type'] === 2 && (int)$member['gold'] < $itemInfo['buy_value']) {
                // 所需元宝不足
                $return['status'] = -5;
                return $return;
            } elseif ($itemInfo['buy_type'] === 3 && ($jade['get1'] ?? 0) < $itemInfo['buy_value']) {
                // 所需蓝色玉石不足
                $return['status'] = -6;
                return $return;
            } elseif ($itemInfo['buy_type'] === 4 && ($jade['get2'] ?? 0) < $itemInfo['buy_value']) {
                // 所需金色玉石不足
                $return['status'] = -7;
                return $return;
            } elseif ($itemInfo['buy_type'] === 5) {
                if (($jade['get3'] ?? 0) < $itemInfo['buy_value']) {
                    // 所需紫色玉石不足
                    $return['status'] = -8;
                    return $return;
                }
                if ($itemInfo['reputation'] > 0 && (int)$member['reputation'] < $itemInfo['reputation']) {
                    // 所需声望不足
                    $return['status'] = -10;
                    return $return;
                }
            }
            // 检查将领是否已存在
            if ($itemInfo['type'] === 5 && DB::table('general')->where('uid', $uid)->where('generalid', $itemInfo['value'])->whereNull('deleted_at')->exists()) {
                $return['status'] = -9;
                return $return;
            }
            // 当前声望
            $reputation = (int)$member['reputation'];
            $surplus = null;
            // 扣除资源
            if ($itemInfo['buy_type'] === 1) {
                $itemInfo['buy_value'] = $itemInfo['buy_value'] * $buyLevel;
                if (!Member::deductCurrency($uid, silver: $itemInfo['buy_value'])) {
                    $return['status'] = -4;
                    return $return;
                }
                $surplus = (int)DB::table('member')->where('uid', $uid)->value('silver');
            } elseif ($itemInfo['buy_type'] === 2) {
                if (!Member::deductCurrency($uid, gold: $itemInfo['buy_value'])) {
                    $return['status'] = -5;
                    return $return;
                }
                $surplus = (int)DB::table('member')->where('uid', $uid)->value('gold');
            } elseif ($itemInfo['buy_type'] >= 3) {
                $sub = null;
                if ($itemInfo['buy_type'] === 3) {
                    $sub = TraderLog::subJade($uid, 1, $itemInfo['buy_value'], 'blackmarket', 'buy', $now);
                } elseif ($itemInfo['buy_type'] === 4) {
                    $sub = TraderLog::subJade($uid, 2, $itemInfo['buy_value'], 'blackmarket', 'buy', $now);
                } elseif ($itemInfo['buy_type'] === 5) {
                    if ($itemInfo['buy_value'] > 0) {
                        $sub = TraderLog::subJade($uid, 3, $itemInfo['buy_value'], 'blackmarket', 'buy', $now);
                    }
                    if ($itemInfo['reputation'] > 0) {
                        $reputation -= $itemInfo['reputation'];
                        if (Member::subReputation($uid, $itemInfo['reputation']) === null) {
                            $return['status'] = -11;
                            return $return;
                        }
                    }
                }
                $surplus = $sub['number'] ?? null;
            }

            // 添加商品
            if ($itemInfo['type'] === 1) {
                // 添加银币
                $itemInfo['value'] = $itemInfo['value'] * $level;
                Member::addCurrency($uid, silver: $itemInfo['value']);
            } elseif (in_array($itemInfo['type'], [2, 3, 4], true)) {
                // 添加突飞卡
                $num = match ($itemInfo['type']) {
                    2 => $itemInfo['value'],
                    3 => $itemInfo['value'] * 2,
                    default => $itemInfo['value'] * 4,
                };
                FreeLeap::add($uid, $num, $now);
            } elseif ($itemInfo['type'] === 5) {
                // 添加武将
                $addstatus = General::addGeneral($uid, $itemInfo['value']);
                if ($addstatus === null) {
                    $return['status'] = -2;
                    return $return;
                }
                // 添加酒馆日志(武将id, 拥有状态, 获取方式)
                PubLog::add($uid, $itemInfo['value'], 1, self::PUB_LOG_TYPE, $now);
                $itemInfo['value'] = $addstatus;
                // 发送广播
                $sender = Chat::sender($uid);
                $star = Chat::starWord((int)$addstatus['star']);
                if ($star !== null) {
                    Chat::sendToHorn(sprintf(self::LANG_GENERAL_GET, (string)$member['nickname'], $itemInfo['discount'], $star, $addstatus['name']), $sender);
                }
                // 发送到聊天室
                $message = Chat::getMessageStr(14, ['general' => $addstatus['name'], 'rebate' => $itemInfo['discount']], $sender);
                Chat::sendToAll($message, Chat::TYPE_SYSTEM, 0, 0, $sender);
            } elseif ($itemInfo['type'] === 6) {
                // 添加军令
                Member::addCurrency($uid, act: $itemInfo['value']);
            }

            // 刷新一个新的商品（只补买掉的这一格）
            $refreshArr = [];
            for ($i = 1; $i <= self::NUM; $i++) {
                $refreshArr[$i] = ['id' => $memberInfo['site' . $i], 'status' => $memberInfo['site' . $i] === $id ? 0 : 1];
            }
            [$list, $data] = self::shelfList(self::listByRate($uid, $refreshArr) ?? [], $level);
            self::updateTimes($uid, $memberInfo['times'], $data, $now);
            // 添加购买记录
            DB::table('blackmarket_buy_log')->insert([
                'uid' => $uid,
                'itemid' => $id,
                'sub_type' => $itemInfo['buy_type'],
                'sub_value' => $itemInfo['buy_value'],
                'surplus' => (int)($surplus ?? 0),
                'dateline' => $now,
            ]);
            $after = self::member($uid);
            // 成功返回
            $return['status'] = 1;
            $return['get_type'] = $itemInfo['type'];
            $return['get_value'] = $itemInfo['value'];
            $return['sub_type'] = $itemInfo['buy_type'];
            $return['sub_value'] = $itemInfo['buy_value'];
            $return['surplus'] = $surplus;
            $return['all_silver'] = (int)$after['silver'];
            $return['all_gold'] = (int)$after['gold'];
            $return['all_act'] = (int)$after['act'];
            $return['all_reputation'] = $reputation;
            $return['list'] = $list;
            // 购买记录（1:免费刷新，2:金币刷新）
            $refresh = ['status' => $memberInfo['times'] > 0 ? 1 : 2];
            foreach ($list as $key => $val) {
                $refresh['site' . $key] = $val['id'];
            }
            self::addRefreshLog($uid, $level, $refresh, 'buy', $now);
            return $return;
        });
    }

    /**
     * 初始化用户信息（get_info：没有货架时随机 3 个商品建档；隔天把免费刷新次数重置为 5）
     *
     * @return array{info: array<string, int>, list: array<int, array<string, mixed>>}|null
     */
    private static function getInfo(int $uid, int $now): ?array
    {
        $memberInfo = self::memberInfo($uid);
        if ($memberInfo !== null) {
            $data = $memberInfo;
            // 判断修改每日免费刷新次数
            if (date('ymd', $data['dateline']) !== date('ymd', $now)) {
                self::updateTimes($uid, self::TIMES, [], $now);
                $data['times'] = self::TIMES;
            }
            // 根据商品id，查询商品的详细信息
            $list = [];
            for ($i = 1; $i <= self::NUM; $i++) {
                $info = Blackmarket::ROWS[$data['site' . $i]] ?? null;
                if ($info === null) {
                    return null;
                }
                $list[$i] = $info;
                $list[$i]['status'] = $data['site' . $i . '_status'];
                unset($data['site' . $i . '_status']);
            }
            return ['info' => $data, 'list' => $list];
        }
        // 随机取出3个商品
        $tempList = self::listByRate($uid);
        if ($tempList === null) {
            return null;
        }
        $data = ['uid' => $uid, 'times' => self::TIMES, 'dateline' => $now];
        $list = [];
        foreach ($tempList as $key => $val) {
            $data['site' . $key] = $val['id'];
            $val['status'] = 0;
            $list[$key] = $val;
        }
        DB::table('blackmarket_log')->insert($data);
        return ['info' => $data, 'list' => $list];
    }

    /**
     * 按权重随机商品（get_list_by_rate：$idArr 为各格子当前商品与锁定状态，补未锁定 / 没有的格子；
     * 排除已在货架上的商品与已拥有的武将，同一次抽到重复的商品 / 武将时重抽）
     *
     * @param array<int, array{id: int, status: int}> $idArr
     * @return array<int, array<string, mixed>>|null 格子 => 商品；商品表为空时为 null
     */
    private static function listByRate(int $uid, array $idArr = []): ?array
    {
        if (Blackmarket::ROWS === []) {
            return null;
        }
        $ownId = $site = [];
        // 取出已锁定的商品位置
        for ($i = 1; $i <= self::NUM; $i++) {
            if (isset($idArr[$i])) {
                $ownId[$i] = $idArr[$i]['id'];
            }
            if (!isset($idArr[$i]) || $idArr[$i]['status'] !== 1) {
                $site[] = $i;
            }
        }
        // 已拥有的将领
        $generalArr = [];
        foreach (Blackmarket::ROWS as $val) {
            if ($val['type'] === 5) {
                $generalArr[] = $val['value'];
            }
        }
        $gidList = $generalArr === [] ? [] : array_map('intval', DB::table('general')->where('uid', $uid)
            ->whereIn('generalid', $generalArr)->whereNull('deleted_at')->pluck('generalid'));
        // 排除已拥有的将领商品,排除已刷新出来的商品
        $sum = 0;
        $newlist = [];
        foreach (Blackmarket::ROWS as $val) {
            if (!in_array($val['id'], $ownId, true) && ($val['type'] !== 5 || !in_array($val['value'], $gidList, true))) {
                $sum += $val['rate'];
                $newlist[$val['id']] = $val;
            }
        }
        $item = $tempId = $gid = [];
        for ($i = 0, $count = count($site); $i < $count; $i++) {
            $rands = mt_rand(1, $sum);
            $number = 0;
            $id = 0;
            foreach ($newlist as $val) {
                $number += $val['rate'];
                if ($rands <= $number) {
                    $id = $val['id'];
                    break;
                }
            }
            if (in_array($id, $tempId, true) || ($newlist[$id]['type'] === 5 && in_array($newlist[$id]['value'], $gid, true))) {
                $i -= 1;
            } else {
                if ($newlist[$id]['type'] === 5) {
                    $gid[] = $newlist[$id]['value'];
                }
                $tempId[] = $id;
                $item[$site[$i]] = $newlist[$id];
            }
        }
        return $item;
    }

    /**
     * 刷新 / 购买后补上的商品：数值按主公等级换算，status 0，image 为武将资源 id（其余 0）
     *
     * @param array<int, array<string, mixed>> $tempList
     * @return array{0: array<int, array<string, mixed>>, 1: array<string, int>} [列表, 要写入的 site{格子} => 商品 id]
     */
    private static function shelfList(array $tempList, int $level): array
    {
        $list = $data = [];
        foreach ($tempList as $key => $val) {
            if ($val['type'] === 1) {
                $val['value'] = $level * $val['value'];
            }
            if ($val['buy_type'] === 1) {
                $val['buy_value'] = $val['buy_value'] * max(self::MIN_LEVEL, $level);
            }
            $data['site' . $key] = $val['id'];
            $val['status'] = 0;
            $val['image'] = $val['type'] === 5 ? (GeneralTemplate::ROWS[$val['value']]['image'] ?? 0) : 0;
            $list[$key] = $val;
        }
        return [$list, $data];
    }

    /**
     * 货架记录（get_member_info）
     *
     * @return array<string, int>|null
     */
    private static function memberInfo(int $uid): ?array
    {
        $row = DB::table('blackmarket_log')->select(...self::LOG_FIELDS)->where('uid', $uid)->whereNull('deleted_at')->first();
        return $row === null ? null : array_map('intval', $row);
    }

    /**
     * 修改免费次数（update_times：同时写入要换的格子商品，dateline 记当前时间）
     *
     * @param array<string, int> $data
     */
    private static function updateTimes(int $uid, int $times, array $data, int $now): void
    {
        $data['times'] = $times;
        $data['dateline'] = $now;
        DB::table('blackmarket_log')->where('uid', $uid)->whereNull('deleted_at')->update($data);
    }

    /**
     * 刷新记录（add_refresh_log）
     *
     * @param array<string, int> $refresh
     */
    private static function addRefreshLog(int $uid, int $level, array $refresh, string $method, int $now): void
    {
        DB::table('blackmarket_refresh')->insert($refresh + [
            'uid' => $uid,
            'level' => $level,
            'dateline' => $now,
            'class' => 'blackmarket',
            'method' => $method,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function member(int $uid): ?array
    {
        return DB::table('member')->select('uid', 'nickname', 'level', 'gold', 'silver', 'act', 'reputation')->where('uid', $uid)->whereNull('deleted_at')->first();
    }
}
