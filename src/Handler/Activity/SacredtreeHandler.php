<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Cross\Server;
use Hanjiang\Cross\WorldArenaServer;
use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\General\GeneralInheritHandler;
use Hanjiang\Handler\General\TavernHandler;
use Hanjiang\MasterData\Equip;
use Hanjiang\MasterData\Gem;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\Equipment;
use Hanjiang\Model\FreeLeap;
use Hanjiang\Model\Member;
use Hanjiang\Model\MemberJade;
use Hanjiang\Model\OwnGem;
use Hanjiang\Model\OwnSoul;
use Hanjiang\Model\Sacredtree;
use Hanjiang\Model\WorldArena;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 运营活动：神树（移植旧版 controllers/sacredtree.php；存储与规则见 Model\Sacredtree）
 *
 * 浇水：type 1 花 100 元宝得 100 经验（每天第一次免费），type 2 花 1000 元宝得 1100 经验，经验按 SacredtreeRate 暴击 1 / 2 / 4 / 8 倍；
 * 升级按神树模板发奖励（8 ~ 11 免费继承次数、12 ~ 15 宝石、17 装备，其余元宝 / 突飞卡 / 银币 / 黄紫宝石 / 原魂 / 玉石 / 声望汇总后发放）。
 * 活动时间取 ActivityTime 的 sacredtree（旧版数据 2016-12-31 已结束，接口回 -1）。客户端 sacredtreepop.js 调用 index、watering（type）。
 * 当前时间取 BossClock（生产恒为 time()）。
 *
 * 升级后在跨服演武榜上时更新榜上的神树等级（transfer_model->update_worldarena_tree → Cross\WorldArenaServer::updateTree）。
 * 与旧版的差别：浇水锁改为 Redis SET NX（旧版 get + setex，有并发窗口），免费次数以条件更新扣减。
 */
final class SacredtreeHandler
{
    /** 浇水价格（_price：type => 元宝 / 经验） */
    private const array PRICE = [
        1 => ['price' => 100, 'exp' => 100],
        2 => ['price' => 1000, 'exp' => 1100],
    ];

    /** 神树操作锁（lock_key） */
    private const string LOCK_KEY = 'sacredtree_lock_';
    private const int LOCK_TTL = 600;

    /** 宝石奖励类型 → 宝石模板 id（青龙石 1 / 朱雀石 3 / 玄武石 2 / 白虎石 4） */
    private const array GEM_ID = [12 => 1, 13 => 3, 14 => 2, 15 => 4];

    /**
     * 初始化: c=sacredtree&m=index
     */
    public static function index(Context $ctx): array
    {
        $return = ['status' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $activity = Sacredtree::activity();
        // 活动未开启
        if (!Sacredtree::isOpen($now) || $uid <= 0) {
            $return['status'] = -1;
            return $return;
        }
        $version = (int)$activity['version'];
        $type = (int)$activity['type'];
        // 查询用户活动信息
        $member = Sacredtree::memberInfo($uid, $version, $type, $now);
        // 查询神树奖励列表
        $list = Sacredtree::treeList($type);
        if ($list === []) {
            $return['status'] = -3;
            return $return;
        }
        // 当前祝福
        $tree = ['now_tree' => '', 'name' => '', 'buff' => 0, 'tree_id' => 0];
        foreach ($list as $key => $val) {
            $data = [];
            for ($i = 1; $i <= 4; $i++) {
                $rewardType = (int)$val['get' . $i . '_type'];
                $data[$i] = [
                    'type' => $rewardType,
                    'value' => $val['get' . $i . '_value1'],
                    'num' => $val['get' . $i . '_value2'],
                    'name' => '',
                    'gem_quality' => 0,
                    'gem_type' => 0,
                    'itemquality' => 0,
                    'itemname' => 0,
                ];
                // 这几个类型是宝石，查询宝石名称
                if (isset(self::GEM_ID[$rewardType])) {
                    $gem = Gem::ROWS[self::GEM_ID[$rewardType]] ?? null;
                    $data[$i]['name'] = $gem['name'] ?? null;
                    $data[$i]['gem_type'] = $gem['type'] ?? null;
                    $data[$i]['gem_quality'] = (int)ceil($val['get' . $i . '_value1'] / 4);
                }
                // 装备 查询装备信息
                if ($rewardType === 17) {
                    $item = Equip::ROWS[(int)$val['get' . $i . '_value1']] ?? null;
                    if ($item !== null) {
                        $data[$i]['itemquality'] = $item['quality'];
                        $data[$i]['itemname'] = $item['name'];
                    }
                }
                unset($list[$key]['get' . $i . '_type'], $list[$key]['get' . $i . '_value1'], $list[$key]['get' . $i . '_value2']);
            }
            if ($val['id'] === $member['treeid']) {
                $tree['now_tree'] = $val['name'];
                $tree['tree_id'] = $val['id'];
            }
            if ($val['id'] === $member['treeid'] || $val['exp'] === $member['exp']) {
                $tree['name'] = $val['name'];
                $tree['buff'] = $val['buff'];
            }
            $list[$key]['status'] = $val['id'] <= $member['treeid'] ? 1 : 0;
            $list[$key]['reward'] = $data;
            // 第一棵树不显示在右边
            unset($list[0]);
        }
        // 要返回的神树列表
        $newList = array_values($list);

        $return['status'] = 1;
        $return['exp'] = $member['exp'];
        $return['btn_status'] = $member['treeid'] >= Sacredtree::MAX_LEVEL ? 1 : 0;
        $return['levelup_exp'] = $member['levelup_exp'];
        $return['time'] = $member['time'];
        $return['tree'] = $tree;
        $return['cd'] = max(0, $activity['endtime'] - $now);
        $return['price'] = ['1' => self::PRICE[1]['price'], '2' => self::PRICE[2]['price']];
        $return['list'] = $newList;
        $return['type'] = $type;
        return $return;
    }

    /**
     * 浇水: c=sacredtree&m=watering（type 1 普通，大于 1 为 1000 元宝）
     */
    public static function watering(Context $ctx): array
    {
        /**
         * -6 上次浇水未结束
         * -5 已满级
         * -4 元宝不足
         * -3 免费次数使用失败
         * -2 获取神树信息失败
         * -1 活动未开启
         *  1 成功
         */
        $return = ['status' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        // 浇水的类型
        $type = (int)$ctx->input('type', 0) > 1 ? 2 : 1;
        $now = BossClock::now();
        $activity = Sacredtree::activity();
        // 判断活动是否开启
        if (!Sacredtree::isOpen($now) || $uid <= 0) {
            $return['status'] = -1;
            return $return;
        }
        // 判断是否领取操作结束
        $redis = \OmniPHP\Cache\Redis::instance();
        if ($redis === null || !(bool)$redis->set(self::LOCK_KEY . $uid, '1', ['nx', 'ex' => self::LOCK_TTL])) {
            $return['status'] = -6;
            return $return;
        }
        try {
            return self::water($uid, $type, (int)$activity['version'], (int)$activity['type'], $now);
        } finally {
            // 解锁
            $redis->del(self::LOCK_KEY . $uid);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function water(int $uid, int $type, int $version, int $activityType, int $now): array
    {
        $return = ['status' => 0];
        $memberRow = DB::table('member')->select('uid', 'level', 'gold')->where('uid', $uid)->whereNull('deleted_at')->first();
        // 获取用户活动信息
        $member = Sacredtree::memberInfo($uid, $version, $activityType, $now);
        if ($memberRow === null) {
            $return['status'] = -2;
            return $return;
        }
        // 已满级，不必再浇水
        if ($member['treeid'] >= Sacredtree::MAX_LEVEL) {
            $return['status'] = -5;
            return $return;
        }
        $isFree = 0;
        // 如果是使用的100元宝的类型，判断用户今日免费次数是否用掉了
        if ($type === 1 && $member['time'] > 0) {
            // 扣除用户次数（以剩余免费次数条件更新）
            $status = DB::table('sacredtree_log')->where('uid', $uid)->where('version', $version)->where('time', $member['time'])
                ->whereNull('deleted_at')->update(['time' => $member['time'] - 1]);
            $member['time'] = max(0, $member['time'] - 1);
            if ($status === 0) {
                $return['status'] = -3;
                return $return;
            }
            // 添加未使用元宝标识
            $isFree = 1;
        } else {
            // 否则判断元宝是否足够，扣除元宝
            if ((int)$memberRow['gold'] < self::PRICE[$type]['price'] || !Member::deductCurrency($uid, gold: self::PRICE[$type]['price'])) {
                $return['status'] = -4;
                return $return;
            }
        }

        // 获得本次暴击概率
        $rate = Sacredtree::rate();
        // 要增加的经验
        $addExp = self::PRICE[$type]['exp'] * $rate;
        // 添加经验
        $expResult = Sacredtree::addExp($uid, $version, $addExp, 0, 0, $activityType, $now);

        // 判断是否升级
        $reward = [];
        $buff = 0;
        $treeId = 0;
        $treeName = '';
        if ($expResult['level'] > $member['treeid']) {
            // 这些奖励统计到一起后再添加
            $rewardArr = [];
            for ($i = $member['treeid'] + 1; $i <= $expResult['level']; $i++) {
                // 查询该等级的奖励
                $info = Sacredtree::tree($i, $activityType);
                $buff = $info['buff'];
                $treeId = $info['id'];
                $treeName = $info['name'];
                $nowReward = [];
                for ($j = 1; $j <= 4; $j++) {
                    $name = '';
                    $gemType = 0;
                    $quality = 0;
                    $itemname = '';
                    $itemquality = 0;
                    $getType = (int)$info['get' . $j . '_type'];
                    $value1 = (int)$info['get' . $j . '_value1'];
                    $value2 = (int)$info['get' . $j . '_value2'];
                    switch ($getType) {
                        case 8: // 免费普通继承
                        case 9: // 免费高级继承
                        case 10: // 免费白金继承
                        case 11: // 免费至尊继承
                            GeneralInheritHandler::addInheritTimes($uid, $getType - 7);
                            break;
                        case 12: // 青龙石
                        case 13: // 朱雀石
                        case 14: // 玄武石
                        case 15: // 白虎石
                            $result = OwnGem::addGem($uid, self::GEM_ID[$getType], $value1, $value2, $now);
                            // 修改宝石格子占用数
                            OwnGem::addGemuse($uid, $value2);
                            $name = $result['name'] ?? null;
                            $gemType = $result['type'] ?? null;
                            $quality = (int)ceil($value1 / 4);
                            break;
                        case 17: // 装备
                            $item = Equip::ROWS[$value1] ?? null;
                            if ($item !== null) {
                                // 添加道具
                                Equipment::addFromTemplate($uid, $value1);
                                $itemname = $item['name'];
                                $itemquality = $item['quality'];
                            }
                            break;
                        default:
                            $rewardArr[$getType] = ($rewardArr[$getType] ?? 0) + $value1;
                            break;
                    }
                    $nowReward[$j] = [
                        'type' => $getType,
                        'value' => $value1,
                        'num' => $value2,
                        'name' => $name,
                        'gem_type' => $gemType,
                        'gem_quality' => $quality,
                        'itemname' => $itemname,
                        'itemquality' => $itemquality,
                    ];
                }
                $nowReward['name'] = $treeName;
                $nowReward['id'] = $treeId;
                $reward[] = $nowReward;
            }
            // 奖励集中发放
            foreach ($rewardArr as $key => $val) {
                switch ($key) {
                    case 1: // 元宝
                        Member::addCurrency($uid, gold: $val);
                        break;
                    case 2: // 突飞卡
                        FreeLeap::add($uid, $val, $now);
                        break;
                    case 3: // 银币
                        Member::addCurrency($uid, silver: $val);
                        break;
                    case 4: // 黄宝石
                        TavernHandler::addJade($uid, 2, $val);
                        break;
                    case 5: // 紫宝石
                        TavernHandler::addJade($uid, 3, $val);
                        break;
                    case 6: // 原魂
                        OwnSoul::addSoul($uid, $val, now: $now);
                        break;
                    case 7: // 玉石
                        MemberJade::add($uid, $val, now: $now);
                        break;
                    case 16: // 声望
                        Member::addReputation($uid, $val);
                        break;
                }
            }
        }

        // 按钮开启状态
        $btnStatus = $expResult['level'] >= Sacredtree::MAX_LEVEL ? 1 : 0;
        // 有升级，判断是否需要更新跨服列表中的神树：在跨服榜（本服昨日前三）中时更新战斗服务器里的神树等级
        if ($expResult['level'] - $member['treeid'] > 0 && isset(WorldArena::topRank($now)[$uid])) {
            WorldArenaServer::updateTree(Server::id(), $uid, $expResult['level']);
        }

        // 添加获得经验log
        DB::table('sacredtreeexp_log')->insert([
            'version' => $version,
            'uid' => $uid,
            'add_exp' => $addExp,
            'now_level' => $expResult['level'],
            'now_exp' => $expResult['exp'],
            'dateline' => $now,
        ]);
        // 记录活动人次
        $gold = $isFree === 1 ? 0 : self::PRICE[$type]['price'];
        DB::table('sacredtree_action_log')->insert(['uid' => $uid, 'gold' => $gold, 'level' => (int)$memberRow['level'], 'dateline' => $now]);

        $after = DB::table('member')->select('gold', 'silver')->where('uid', $uid)->first();
        $return['status'] = 1;
        $return['add_exp'] = $addExp;
        $return['tree_id'] = $treeId;
        $return['tree_name'] = $treeName;
        $return['btn_status'] = $btnStatus;
        $return['buff'] = $buff;
        $return['multiple'] = $rate;
        $return['levelup'] = $expResult['level'] - $member['treeid'];
        $return['time'] = $member['time'];
        $return['now_exp'] = $expResult['exp'];
        $return['all_gold'] = (int)$after['gold'];
        $return['all_silver'] = (int)$after['silver'];
        $return['levelup_exp'] = $expResult['levelup_exp'];
        $return['reward'] = $reward;
        $return['type'] = $activityType;
        return $return;
    }
}
