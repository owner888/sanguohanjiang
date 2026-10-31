<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Pvp;

use Hanjiang\Battle\BattleEngine;
use Hanjiang\Battle\TeamBuilder;
use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\MasterData\ActivityTime;
use Hanjiang\MasterData\EquipEssence;
use Hanjiang\MasterData\HeavenMission;
use Hanjiang\Model\Equipment;
use Hanjiang\Model\Chat;
use Hanjiang\Model\Member;
use Hanjiang\Model\MemberActivity;
use Hanjiang\Model\MemberStone;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 通天塔（移植旧版 controllers/heaven.php + models/heaven_model.php，路径相对旧版 s1/application/）
 *
 * 配置：关卡 HeavenMission（200 关；position1~9 → 怪物 HeavenMonster，battle_model->battle_heaven 即 battle_mission type 8）、
 * 装备精华模板 EquipEssence、双倍活动 ActivityTime（double_heaven）。
 * 玩家存储：member_activity（freeheaven 免费次数、heaventimes 购买的次数、heavendate 每日奖励领取时间、heavenmission）、
 * member_field.heaventimes（扫荡次数不足时补买的次数）、heaven_open_map（已过关卡）、heaven_log（首杀记录，即攻略列表）、
 * heaven_report（首杀战报）。
 *
 * 客户端调用（heavenworld.js / miscpop.js）：index、get_mopup_price、mop_up、pk、get_report_list、get_report_info、buy、
 * reward_info、get_reward、send_to_chat、get_price。旧版公开方法另有 get_lastreward（内部计算用，前端不调用，不移植）。
 *
 * 精炼石 / 五彩石 / 上古血玉存 member 三列（MemberStone），装备精华存 equipment type 100（Equipment::addTowerItem），
 * 首杀、掉落、扫荡、每日奖励照旧版发放并记流水；resource 为当前三种石头数量。
 *
 * 元宝消耗由 Member::deductCurrency 记元宝流水与日常任务 5（元宝消费）。
 *
 * 依赖未移植：sub_gold 的翻牌活动计数（flop，旧版数据中已结束）。
 */
final class HeavenHandler
{
    /** 主公等级 160 起开放 */
    private const int OPEN_LEVEL = 160;

    /** 战报分享到聊天室的元宝花费（send_to_chat 的 $price） */
    private const int SHARE_PRICE = 2;

    /** 物品流水的执行类（旧版 $this->class） */
    private const string LOG_CLASS = 'heaven';

    /** 购买次数 / 扫荡补次数每次元宝（旧版 $_price，不累加） */
    private const int PRICE = 20;

    /** 每日奖励 2 / 4 / 8 倍所消耗的元宝（旧版 $_two / $_four / $_eight） */
    private const int TWO = 100;
    private const int FOUR = 200;
    private const int EIGHT = 400;

    /** get_reward 类型 => 元宝 */
    private const array TYPE_GOLD = [0 => 0, 1 => self::TWO, 2 => self::FOUR, 3 => self::EIGHT];

    /** 扫荡允许的次数 */
    private const array MOPUP_TIMES = [5, 10, 50, 100];

    /** 旧版语言包 language/chinese_s/heaven_lang.php */
    private const array LANG = [
        1 => '抱歉，您的等级不足！',
        2 => '获取活动信息失败',
        3 => '获取地图信息失败',
        4 => '获取关卡列表失败',
        5 => '无法扫荡',
        7 => '未击败该关卡',
        9 => '传递参数失败',
        10 => '元宝不足',
        11 => '扣除元宝失败',
        12 => '获得五彩石失败',
        13 => '获得上古血玉失败',
        14 => '添加装备精华失败',
        15 => '获得精炼石失败',
        16 => '次数不足',
        17 => '关卡未开启',
        18 => '获取战斗信息失败',
        19 => '获取次数失败',
        20 => '攻略不存在',
        21 => '获取攻略列表失败',
        22 => '传递类型错误',
        23 => '今日已领取过奖励',
        24 => '首杀阶段不能扫荡',
    ];

    /** 旧版 zc_sanguo_member_activity 中本玩法读写的字段 */
    private const array ACTIVITY_COLUMNS = ['uid', 'heavenmission', 'heaventimes', 'freeheaven', 'heavendate'];

    /** 旧版 zc_sanguo_heaven_open_map 的字段 */
    private const array MAP_COLUMNS = ['id', 'uid', 'level', 'stage', 'mission', 'dateline'];

    /** 旧版 zc_sanguo_heaven_log 的字段 */
    private const array LOG_COLUMNS = ['id', 'uid', 'nickname', 'missionlevel', 'missionid', 'dateline', 'level', 'tree', 'first_reward'];

    /**
     * 获取关卡列表，初始化接口: c=heaven&m=index
     */
    public static function index(Context $ctx): array
    {
        /**
         *  0：未知错误
         *  1：成功
         * -1：您的等级不足
         * -2：获取活动信息失败
         * -3：获取地图信息失败
         * -4：获取关卡列表失败
         */
        $return = [
            'status' => 0,
            'index' => 0,
            'resource' => [],
        ];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        if (!self::isOpen($member)) {
            // 等级不足
            return self::fail($return, -1, 1);
        }
        $uid = (int)$member['uid'];

        $activity = self::getActivityInfo($uid);
        if ($activity === null) {
            // 获取活动信息失败
            return self::fail($return, -2, 2);
        }
        $times = $activity['heaventimes'] + $activity['freeheaven'];

        // 旧版判断的是 $activity['hevanedate']（字段名拼错，恒不存在），get_reward 恒为 0，照旧版
        $getReward = 0;

        // 获取已开启地图信息
        $map = self::getOpenMap($uid, time());
        if ($map === null) {
            // 获取地图信息失败
            return self::fail($return, -3, 3);
        }
        // 获取关卡信息（旧版缓存在 Redis heaven_tower2s_ 10 天，配置已在 MasterData，直接读）
        $list = array_values(HeavenMission::ROWS);
        if ($list === []) {
            // 获取关卡列表失败
            return self::fail($return, -4, 4);
        }

        $field = ['id', 'level', 'name', 'type', 'image', 'refinestone', 'colorstone', 'color_getrate', 'bloodstone', 'blood_getrate', 'equip', 'equip_value', 'background', 'first_type', 'first_value'];
        // 获取玩家通过关卡信息
        $umissList = self::getMissionLogs($uid);
        $listArr = [];
        foreach ($list as $key => $value) {
            foreach ($field as $v) {
                $listArr[$key][$v] = $value[$v];
            }
            if ($map['mission'] + 1 > $value['id']) {
                $listArr[$key]['openstatus'] = 1;
                $return['index'] = $key + 1 === 200 ? $key : $key + 1;
                $listArr[$key]['killed'] = 1;
                $listArr[$key]['mop_up'] = 1;
            } elseif ($map['mission'] + 1 < $value['id']) {
                $listArr[$key]['openstatus'] = 0;
                $listArr[$key]['killed'] = 0;
                $listArr[$key]['mop_up'] = 0;
            } else {
                $listArr[$key]['openstatus'] = 1;
                $listArr[$key]['killed'] = 0;
                $listArr[$key]['mop_up'] = 0;
            }
        }

        $double = self::isDouble(time());
        foreach ($list as $key => $val) {
            $listArr[$key]['essence'] = ['id' => 0];
            $essence = EquipEssence::ROWS[$val['equip']] ?? null;
            if ($essence !== null) {
                $listArr[$key]['essence']['id'] = $essence['id'];
                $listArr[$key]['essence']['quality'] = $essence['quality'];
                $listArr[$key]['essence']['num'] = $val['equip_value'];
            }
            // 判断是否已经领取首杀奖励
            $listArr[$key]['firstkill'] = 1;
            if (($umissList[$val['id']]['first_reward'] ?? null) === 1) {
                $listArr[$key]['firstkill'] = 0;
            }
            // 此关卡为首杀的时候不显示扫荡按钮
            if ($listArr[$key]['firstkill'] === 1) {
                $listArr[$key]['mop_up'] = 0;
            } elseif ($double) {
                if (($listArr[$key]['essence']['num'] ?? 0) > 0) {
                    $listArr[$key]['essence']['num'] *= 2;
                }
                $listArr[$key]['refinestone'] *= 2;
                $listArr[$key]['color_getrate'] = min(30, $listArr[$key]['color_getrate'] * 2);
                $listArr[$key]['colorstone'] *= 2;
                $listArr[$key]['blood_getrate'] = min(30, $listArr[$key]['blood_getrate'] * 2);
                $listArr[$key]['bloodstone'] *= 2;
            }
        }

        MemberActivity::update($uid, ['heavenmission' => 0]);
        $return['status'] = 1;
        // 关卡列表
        $return['list'] = $listArr;
        // 剩余次数
        $return['times'] = $times;
        $return['get_reward'] = $getReward;
        $return['resource'] = self::resource($uid);
        return $return;
    }

    /**
     * 获取扫荡价格: c=heaven&m=get_mopup_price（id 关卡id）
     */
    public static function get_mopup_price(Context $ctx): array
    {
        /**
         *  0：未知错误
         *  1：成功
         * -1：您的等级不足
         * -2：获取关卡信息失败
         * -3：获取地图信息失败
         * -4：无法扫荡
         * -5：获取活动信息失败
         * -6：获取价格失败
         */
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        if (!self::isOpen($member)) {
            // 等级不足
            return self::fail($return, -1, 1);
        }
        $uid = (int)$member['uid'];

        $id = (int)$ctx->input('id', 0);
        $info = HeavenMission::ROWS[$id] ?? null;
        if ($info === null) {
            // 获取关卡信息失败
            return self::fail($return, -2, 4);
        }

        // 获取已开启地图信息
        $map = self::getOpenMap($uid, time());
        if ($map === null) {
            // 获取地图信息失败
            return self::fail($return, -3, 3);
        }

        if ($id > $map['mission']) {
            // 无法扫荡
            return self::fail($return, -4, 5);
        }
        // 扫荡信息
        $activity = self::getActivityInfo($uid);
        if ($activity === null) {
            // 获取活动信息失败
            return self::fail($return, -5, 2);
        }
        // 当前剩余次数
        $leavetimes = $activity['freeheaven'] + $activity['heaventimes'];

        // 旧版 _get_price_by_times 不会返回负数，-6 获取价格失败走不到
        $return['status'] = 1;
        $return['info'] = self::mopupPrice($leavetimes);
        return $return;
    }

    /**
     * 通天塔扫荡: c=heaven&m=mop_up（id 关卡id，times 5 / 10 / 50 / 100）
     */
    public static function mop_up(Context $ctx): array
    {
        /**
         *  3 元宝不足
         *  2 包裹已满
         *  1 成功
         *  0 失败
         * -1 等级不足
         * -2 获取活动信息失败
         * -3 获取关卡信息失败
         * -4 获取地图信息失败
         * -5 当前场景未开启
         * -6 未击败该关卡
         * -7 次数不正确
         * -8 获取元宝消耗失败
         * -9 扣除失败
         * -10 获取将魂失败
         * -11 加原魂失败
         * -12 获取扫荡价格失败
         * （旧版注释与代码不一致，以代码为准：-5 未击败该关卡，-8 元宝不足，-9 扣除失败，-24 首杀阶段不能扫荡；
         *   -10 / -11 加五彩石 / 上古血玉失败（旧版判空，走不到），-12 加装备精华失败，-13 加精炼石失败，-14 取价失败（走不到））
         */
        $now = time();
        $return = [
            'status' => 0,
            'resource' => [],
        ];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        $uid = (int)$member['uid'];
        $id = (int)$ctx->input('id', 0);
        $times = (int)$ctx->input('times', 0);

        $result = self::getMissionLog($uid, $id);
        if ($result !== null && $result['first_reward'] === 0) {
            // 防刷接口：首杀不能扫荡
            return self::fail($return, -24, 24);
        }
        if (!in_array($times, self::MOPUP_TIMES, true)) {
            // 次数不允许
            return self::fail($return, -7, 9);
        }

        if (!self::isOpen($member)) {
            // 等级不足
            return self::fail($return, -1, 1);
        }

        // 同一玩家并发扫荡 / 攻打 / 购买：在事务里锁住活动记录行，次数读写串行
        return DB::transaction(static function () use ($return, $uid, $id, $times, $now): array {
            $activity = self::lockActivity($uid);
            if ($activity === null) {
                // 获取活动信息失败
                return self::fail($return, -2, 2);
            }

            $info = HeavenMission::ROWS[$id] ?? null;
            if ($info === null) {
                // 获取关卡信息失败
                return self::fail($return, -3, 4);
            }

            // 获取已开启地图信息
            $map = self::getOpenMap($uid, $now);
            if ($map === null) {
                // 获取地图信息失败
                return self::fail($return, -4, 3);
            }

            if ($id > $map['mission']) {
                // 未击败该关卡
                return self::fail($return, -5, 7);
            }

            // 当前剩余次数
            $leavetimes = $activity['freeheaven'] + $activity['heaventimes'];
            // 今日购买次数
            $fieldHeaventimes = self::fieldHeaventimes($uid);

            // 旧版 _get_price_by_times 不会返回负数，-6 获取元宝消耗失败走不到
            $gold = self::priceByTimes($times, $leavetimes);
            if (!Member::deductCurrency($uid, gold: $gold)) {
                // 元宝不足（新版原子扣除不区分旧版 -9 扣除失败）
                return self::fail($return, -8, 10);
            }

            if ($times <= $leavetimes) {
                // 剩余次数足够
                if ($activity['freeheaven'] >= $times) {
                    // 免费次数足够
                    $data = ['freeheaven' => $activity['freeheaven'] - $times];
                } else {
                    // 免费次数不足，扣购买次数
                    $data = ['freeheaven' => 0, 'heaventimes' => $leavetimes - $times];
                }
                MemberActivity::update($uid, $data);
            } else {
                // 剩余次数不足：增加购买次数，扣剩余次数
                DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')
                    ->update(['heaventimes' => $times - $leavetimes + $fieldHeaventimes]);
                MemberActivity::update($uid, ['freeheaven' => 0, 'heaventimes' => 0]);
            }

            $double = self::isDouble($now);
            $colorstone = $info['colorstone'];          // 五彩石
            $colorstoneRate = $info['color_getrate'];   // 五彩石概率
            $bloodstone = $info['bloodstone'];          // 上古血玉
            $bloodstoneRate = $info['blood_getrate'];   // 上古血玉概率
            $equipid = $info['equip'];                  // 装备id
            $equipValue = $info['equip_value'];         // 装备数量
            $equipRate = $info['equip_getrate'];        // 装备掉落概率
            $essence = EquipEssence::ROWS[$equipid] ?? null;

            // 双倍活动期间，掉率概率和奖励数量都翻倍
            if ($double) {
                $colorstone *= 2;
                $colorstoneRate *= 2;
                $bloodstone *= 2;
                $bloodstoneRate *= 2;
                $equipValue *= 2;
                $equipRate *= 2;
            }

            // 根据概率添加五彩石、上古血玉和装备精华
            $num = 0;
            $number = 0;
            $numb = 0;
            if ($colorstone > 0 || $bloodstone > 0 || $equipid > 0) {
                for ($i = 0; $i < $times; $i++) {
                    if (self::roll($colorstoneRate) > 0) {
                        ++$num;
                    }
                }
                for ($i = 0; $i < $times; $i++) {
                    if (self::roll($bloodstoneRate) > 0) {
                        ++$number;
                    }
                }
                for ($i = 0; $i < $times; $i++) {
                    if (self::roll($equipRate) > 0) {
                        ++$numb;
                    }
                }
                // 旧版以 empty(add_stone 返回值) 判 -10 / -11，返回值恒为非空数组，走不到
                if ($num > 0) {
                    // 添加五彩石
                    MemberStone::add($uid, $colorstone * $num, MemberStone::COLOR, self::LOG_CLASS, 'mop_up', $now);
                }
                if ($number > 0) {
                    // 添加上古血玉
                    MemberStone::add($uid, $bloodstone * $number, MemberStone::BLOOD, self::LOG_CLASS, 'mop_up', $now);
                }
            }
            $essenceValue = 0;
            if ($numb > 0) {
                // 添加装备精华
                $essenceValue = $equipValue * $numb;
                if (Equipment::addTowerItem($uid, (int)$equipid, $essenceValue) === 0) {
                    // 添加装备失败
                    return self::fail($return, -12, 14);
                }
            }

            // 精炼石100%掉落
            $refinestone = $info['refinestone'];
            if ($refinestone > 0) {
                // 双倍活动期间，奖励双倍
                if ($double) {
                    $refinestone *= 2;
                }
                $refinestone *= $times;
                if (MemberStone::add($uid, $refinestone, MemberStone::REFINE, self::LOG_CLASS, 'mop_up', $now)['status'] !== 1) {
                    // 加精炼石失败
                    return self::fail($return, -13, 15);
                }
            }

            $activity = self::lockActivity($uid);
            $leavetimes = $activity['freeheaven'] + $activity['heaventimes'];

            $return['status'] = 1;
            // 下一次扫荡价格
            $return['info'] = self::mopupPrice($leavetimes);
            // 精华数量
            $return['info']['essence']['num'] = 0;
            // 精炼石数量
            $return['info']['refinestone'] = $refinestone;
            // 五彩石数量
            $return['info']['colorstone'] = $num * $colorstone;
            // 上古血玉数量
            $return['info']['bloodstone'] = $number * $bloodstone;
            // 精华信息
            if ($equipid > 0 && $numb > 0 && $essence !== null) {
                $return['info']['essence']['id'] = $essence['id'];
                $return['info']['essence']['quality'] = $essence['quality'];
                $return['info']['essence']['num'] = $essenceValue;
            }
            $return['times'] = $leavetimes;
            $return['gold'] = self::memberValue($uid, 'gold');
            $return['resource'] = self::resource($uid);
            return $return;
        });
    }

    /**
     * 战斗: c=heaven&m=pk（id 关卡id）
     */
    public static function pk(Context $ctx): array
    {
        /**
         * -10 加原魂失败
         * -9 获取将魂失败
         * -7 获取战斗信息失败
         * -6 关卡未开启
         * -5 场景未开启
         * -4 获取地图信息失败
         * -3 获取关卡信息失败
         * -2 获取活动信息失败
         * -1 等级不足
         *  0 失败
         *  1 成功
         *  2 包裹已满
         *  3 次数不足
         * （旧版注释与代码不一致，以代码为准：-3 次数不足，-4 获取关卡信息失败，-5 获取地图信息失败，-8 扣次数失败；
         *   -9 / -10 加五彩石 / 上古血玉失败（旧版判空，走不到），-11 加装备精华失败，-12 加精炼石失败）
         */
        $now = time();
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        $uid = (int)$member['uid'];
        // 获取关卡id
        $id = (int)$ctx->input('id', 0);
        if (!self::isOpen($member)) {
            // 等级不足
            return self::fail($return, -1, 1);
        }

        // 同一玩家并发攻打 / 扫荡 / 购买：在事务里锁住活动记录行，次数读写串行
        return DB::transaction(static function () use ($return, $member, $uid, $id, $now): array {
            $activity = self::lockActivity($uid);
            if ($activity === null) {
                // 获取活动信息失败
                return self::fail($return, -2, 2);
            }

            $times = $activity['heaventimes'] + $activity['freeheaven'];
            if ($times < 1) {
                // 次数不足
                return self::fail($return, -3, 16);
            }

            $info = HeavenMission::ROWS[$id] ?? null;
            if ($info === null) {
                // 获取关卡信息失败
                return self::fail($return, -4, 4);
            }

            // 获取已开启地图信息
            $map = self::getOpenMap($uid, $now);
            if ($map === null) {
                // 获取地图信息失败
                return self::fail($return, -5, 3);
            }

            if ($id > $map['mission'] + 1) {
                // 关卡未开启
                return self::fail($return, -6, 17);
            }

            // [战斗模型]战斗（取 position1~9）
            $battle = self::battleHeaven($uid, $info);
            if ($battle['status'] !== 1) {
                // 获取战斗信息失败
                return self::fail($return, -7, 18);
            }
            // 扣次数
            if ($activity['freeheaven'] > 0) {
                $data = ['freeheaven' => $activity['freeheaven'] - 1];
            } elseif ($activity['heaventimes'] > 0) {
                $data = ['heaventimes' => $activity['heaventimes'] - 1];
            } else {
                return self::fail($return, -8, 19);
            }
            MemberActivity::update($uid, $data);

            $clear = 0;
            $report = 0;
            $colorStone = 0;
            $bloodStone = 0;
            $refinestone = 0;
            $equipid = 0;
            $equip = 0;
            $colorstone = 0;
            $bloodstone = 0;
            $essence = null;
            // 首杀标识：默认不是首杀
            $isFirst = 0;
            // 默认是首杀（旧版战败时也返回 1）
            $return['info']['firstkill'] = 1;
            if ($battle['info']['assess'] > 0) {
                // 胜利：当前id 大于 已过关卡 id
                if ($id > $map['mission']) {
                    self::updateOpenMap($uid, ['mission' => $id]);
                }

                $colorstone = $info['colorstone'];          // 五彩石
                $colorstoneRate = $info['color_getrate'];   // 五彩石概率
                $bloodstone = $info['bloodstone'];          // 上古血玉
                $bloodstoneRate = $info['blood_getrate'];   // 上古血玉概率
                // 获取战斗记录
                $log = self::getMissionLog($uid, $info['id']);
                if ($log === null) {
                    // 用户神树信息
                    $treeId = (int)$battle['info']['leftbuff']['tree'];
                    $reportId = self::addMissionLog($member, 1, $info['id'], $treeId, $now);
                    // 战斗记录不存在则添加
                    if (!self::checkReport($reportId)) {
                        // 压缩各种战报
                        $gzipreport = base64_encode(gzcompress((string)json_encode($battle['info'])));
                        self::addMissionReport($reportId, $gzipreport, $info, $member);
                        $report = $reportId;
                    }
                    // 标识首杀；更新首杀领奖记录
                    $isFirst = 1;
                    self::updateMissionLog($uid, $id);
                } elseif ($log['first_reward'] === 0) {
                    // 对之前过关老玩家没有领取首杀奖励处理
                    $isFirst = 1;
                    self::updateMissionLog($uid, $id);
                }
                $return['info']['firstkill'] = 0;
                // 如果是首杀，奖励精炼石或五彩石或上古血石
                if ($isFirst === 1) {
                    if ($info['first_type'] > 0 && $info['first_value'] > 0) {
                        $return['info']['first_type'] = $info['first_type'];
                        $return['info']['first_value'] = $info['first_value'];
                        $return['info']['firstkill'] = 1;
                        // 添加各种石头（1 精炼石 / 2 五彩石 / 3 上古血玉）
                        MemberStone::add($uid, (int)$info['first_value'], (int)$info['first_type'], self::LOG_CLASS, 'pk', $now);
                    }
                } else {
                    $double = self::isDouble($now);
                    // 根据概率添加五彩石和上古血玉
                    if ($colorstone > 0 || $bloodstone > 0) {
                        // 双倍活动期间，奖励翻倍
                        if ($double) {
                            $colorstoneRate *= 2;
                            $bloodstoneRate *= 2;
                        }
                        $colorStone = self::roll($colorstoneRate);
                        $bloodStone = self::roll($bloodstoneRate);
                        if ($double) {
                            $colorstone *= 2;
                            $bloodstone *= 2;
                        }
                        // 旧版以 empty(add_stone 返回值) 判 -9 / -10，返回值恒为非空数组，走不到
                        if ($colorStone > 0) {
                            MemberStone::add($uid, $colorstone, MemberStone::COLOR, self::LOG_CLASS, 'pk', $now);
                        }
                        if ($bloodStone > 0) {
                            MemberStone::add($uid, $bloodstone, MemberStone::BLOOD, self::LOG_CLASS, 'pk', $now);
                        }
                    }

                    $equipid = $info['equip'];             // 装备id
                    $equipValue = $info['equip_value'];    // 装备数量
                    // 双倍活动期间，奖励和掉率都翻倍（旧版掷点时用的仍是原掉率 equip_getrate，照旧版）
                    if ($double) {
                        $equipValue *= 2;
                    }

                    // 根据概率添加装备信息
                    if ($equipid > 0) {
                        $equip = self::roll($info['equip_getrate']);
                        if ($equip > 0) {
                            $essence = EquipEssence::ROWS[$equipid] ?? null;
                            if (Equipment::addTowerItem($uid, (int)$equipid, $equipValue) === 0) {
                                // 添加装备失败
                                return self::fail($return, -11, 14);
                            }
                        }
                    }

                    // 精炼石100%掉落
                    $refinestone = $info['refinestone'];
                    if ($refinestone > 0 && $double) {
                        $refinestone *= 2;
                    }
                    if ($refinestone > 0
                        && MemberStone::add($uid, $refinestone, MemberStone::REFINE, self::LOG_CLASS, 'pk', $now)['status'] !== 1) {
                        // 添加精炼石失败
                        return self::fail($return, -12, 15);
                    }
                }
            }

            $return['info']['id'] = $info['id'];
            $return['status'] = 1;
            $return['clear'] = $clear;
            $return['info']['report'] = $report;
            $return['info']['times'] = $activity['freeheaven'] + $activity['heaventimes'] - 1;
            $return['info']['win'] = $battle['info']['assess'];
            $return['info']['refinestone'] = $refinestone;
            // 旧版 $return['info']['colorstone'] = $return['info']['bloodstone'] = 0：先建 bloodstone 键
            $return['info']['bloodstone'] = $bloodStone > 0 ? $bloodstone : 0;
            $return['info']['colorstone'] = $colorStone > 0 ? $colorstone : 0;
            $return['info']['essence'] = ['id' => 0, 'quality' => 0, 'num' => 0];
            if ($equipid > 0 && $equip > 0 && $essence !== null) {
                $return['info']['essence']['id'] = $essence['id'];
                $return['info']['essence']['quality'] = $essence['quality'];
                $return['info']['essence']['num'] = $info['equip_value'];
            }

            $return['battle'] = $battle['info'];
            $return['battle']['left_role'] = [
                'name' => (string)$member['nickname'],
                'level' => (int)$member['level'],
            ];
            $return['battle']['right_role'] = [
                'name' => $info['name'],
                'level' => $info['level'],
            ];
            return $return;
        });
    }

    /**
     * 获取攻略列表: c=heaven&m=get_report_list（id 关卡id）
     */
    public static function get_report_list(Context $ctx): array
    {
        /**
         * -1 获取攻略列表失败
         *  0 失败
         *  1 成功
         *  2 攻略不存在
         */
        $return = ['status' => 0, 'list' => []];
        $id = (int)$ctx->input('id', 0);
        $log = self::getMissionLogList($id);
        if ($log === []) {
            // 攻略不存在 返回2
            return self::fail($return, 2, 20);
        }
        $return['status'] = 1;
        $return['list'] = $log;
        return $return;
    }

    /**
     * 获取战报: c=heaven&m=get_report_info（id 战报id）
     */
    public static function get_report_info(Context $ctx): array
    {
        /**
         * -1 获取攻略列表失败
         *  0 失败
         *  1 成功
         *  2 攻略不存在
         */
        $return = ['status' => 0, 'battle' => []];
        $id = (int)$ctx->input('id', 0);
        $result = self::getReportById($id);
        if ($result === null) {
            // 攻略不存在 返回2
            return self::fail($return, 2, 20);
        }
        $return['status'] = 1;
        $return['battle'] = $result['report'];
        $return['battle']['left_role'] = [
            'name' => $result['nickname'],
            'level' => $result['userlevel'],
        ];
        $return['battle']['right_role'] = [
            'name' => $result['name'],
            'level' => $result['level'],
        ];
        $return['win'] = $result['report']['assess'] ?? null;
        return $return;
    }

    /**
     * 购买次数: c=heaven&m=buy
     */
    public static function buy(Context $ctx): array
    {
        /**
         * -3 扣元宝失败
         * -2 获取活动信息失败
         * -1 等级不足
         *  0 失败
         *  1 成功
         *  2 元宝不足
         * （旧版注释与代码不一致，以代码为准：-2 元宝不足，-3 获取活动信息失败，-4 扣元宝失败）
         */
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        if (!self::isOpen($member)) {
            // 等级不足
            return self::fail($return, -1, 1);
        }
        $uid = (int)$member['uid'];

        if (self::PRICE > (int)$member['gold']) {
            // 金币不足
            return self::fail($return, -2, 10);
        }

        return DB::transaction(static function () use ($return, $uid): array {
            $activity = self::lockActivity($uid);
            if ($activity === null) {
                // 获取活动信息失败
                return self::fail($return, -3, 2);
            }

            $times = $activity['freeheaven'] + $activity['heaventimes'] + 1;
            // 旧版先加次数再扣元宝（扣除失败时次数已加上）；这里先扣元宝，扣除失败时不加次数
            if (!Member::deductCurrency($uid, gold: self::PRICE)) {
                // 扣除金币失败
                return self::fail($return, -4, 11);
            }
            MemberActivity::update($uid, ['heaventimes' => $activity['heaventimes'] + 1]);

            $return['status'] = 1;
            $return['times'] = $times;
            $return['gold'] = self::memberValue($uid, 'gold');
            return $return;
        });
    }

    /**
     * 奖励信息: c=heaven&m=reward_info
     */
    public static function reward_info(Context $ctx): array
    {
        /**
         * -5 获取关卡信息失败
         * -4 获取地图信息失败
         * -3 获取奖励失败
         * -2 获取活动信息失败
         * -1 等级不足
         *  0 失败
         *  1 成功
         *  2 已领取
         *  3 未击败关卡
         * （旧版注释与代码不一致，以代码为准：-3 获取地图信息失败，-4 获取关卡信息失败）
         */
        $now = time();
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        if (!self::isOpen($member)) {
            // 等级不足
            return self::fail($return, -1, 1);
        }
        $uid = (int)$member['uid'];

        $activity = self::getActivityInfo($uid);
        if ($activity === null) {
            // 获取活动信息失败
            return self::fail($return, -2, 2);
        }
        if ((int)date('Ymd', $activity['heavendate']) >= (int)date('Ymd', $now)) {
            // 奖励已领取
            return self::fail($return, 2, 23);
        }

        // 获取已开启地图信息
        $map = self::getOpenMap($uid, $now);
        if ($map === null) {
            // 获取地图信息失败
            return self::fail($return, -3, 3);
        }

        if ($map['mission'] <= 0) {
            // 未击败关卡
            return self::fail($return, 3, 17);
        }

        $mission = HeavenMission::ROWS[$map['mission']] ?? null;
        if ($mission === null) {
            // 获取关卡信息失败
            return self::fail($return, -4, 4);
        }

        // 取都城占领加成（占领效果 type 7：精炼石 / 五彩石 / 上古血玉按 value% 加成）
        $effect = SiegeHandler::getCityEffect($member, $now);
        $addType = $effect['status'] === 1 && $effect['type'] === 7;
        $return['effect'] = $addType ? $effect['value'] + 100 : 0;
        if ($addType) {
            $mission['refinestone'] = self::lastReward($mission['refinestone'], $effect['value']);
        }

        // 不花费元宝时：每日可领取的基础奖励（精炼石）
        $return['base_reward'] = $mission['refinestone'] * 3;
        $return['status'] = 1;

        // 当前进度
        $return['info'] = [
            'name' => $mission['name'],
            'mission' => $mission['id'],
        ];

        // 五彩石（距离最近的五彩石关卡）、上古血玉（本关 boss）的数量
        $colorValue = self::colorValue($mission['id']);
        $bloodValue = self::bloodValue($mission['id']);
        if ($addType) {
            $colorValue = self::lastReward($colorValue, $effect['value']);
            $bloodValue = self::lastReward($bloodValue, $effect['value']);
        }

        // 花费元宝时：(100 *2倍; 200 *4倍; 400 *8倍)
        $return['two_reward'] = [
            'gold' => self::TWO,
            'refine_reward' => $mission['refinestone'] * 7,
            'color_reward' => $colorValue * 3,
        ];
        $return['four_reward'] = [
            'gold' => self::FOUR,
            'refine_reward' => $mission['refinestone'] * 11,
            'color_reward' => $colorValue * 6,
            'blood_reward' => $bloodValue * 3,
        ];
        $return['eight_reward'] = [
            'gold' => self::EIGHT,
            'refine_reward' => $mission['refinestone'] * 19,
            'color_reward' => $colorValue * 12,
            'blood_reward' => $bloodValue * 6,
        ];
        return $return;
    }

    /**
     * 获取奖励: c=heaven&m=get_reward（type 0 免费，1 / 2 / 3 花 100 / 200 / 400 元宝）
     *
     * status：-1 等级不足；-2 获取活动信息失败；2 今日已领取；-3 类型错误；-4 获取地图信息失败；3 未击败关卡；
     * -5 获取关卡信息失败；-6 元宝不足；1 成功
     */
    public static function get_reward(Context $ctx): array
    {
        $now = time();
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        if (!self::isOpen($member)) {
            // 等级不足
            return self::fail($return, -1, 1);
        }
        $uid = (int)$member['uid'];
        // 前端默认传0
        $type = (int)$ctx->input('type', 0);

        // 同一玩家并发领取：在事务里锁住活动记录行
        return DB::transaction(static function () use ($return, $member, $uid, $type, $now): array {
            $activity = self::lockActivity($uid);
            if ($activity === null) {
                // 获取活动信息失败
                return self::fail($return, -2, 2);
            }
            if ((int)date('Ymd', $activity['heavendate']) >= (int)date('Ymd', $now)) {
                // 奖励已领取
                return self::fail($return, 2, 23);
            }

            if ($type > 3 || $type < 0) {
                // 传递类型错误
                return self::fail($return, -3, 22);
            }
            $gold = self::TYPE_GOLD[$type];

            // 获取已开启地图信息
            $map = self::getOpenMap($uid, $now);
            if ($map === null) {
                // 获取地图信息失败
                return self::fail($return, -4, 3);
            }

            if ($map['mission'] <= 0) {
                // 未击败关卡
                return self::fail($return, 3, 7);
            }

            $mission = HeavenMission::ROWS[$map['mission']] ?? null;
            if ($mission === null) {
                // 获取关卡信息失败
                return self::fail($return, -5, 4);
            }

            // 取都城占领加成（占领效果 type 7：精炼石 / 五彩石 / 上古血玉按 value% 加成）
            $effect = SiegeHandler::getCityEffect($member, $now);
            $addType = $effect['status'] === 1 && $effect['type'] === 7;
            $return['effect'] = $addType ? $effect['value'] + 100 : 0;
            if ($addType) {
                $mission['refinestone'] = self::lastReward($mission['refinestone'], $effect['value']);
            }

            // 五彩石（距离最近的五彩石关卡）、上古血玉（本关 boss）的数量
            $colorValue = self::colorValue($mission['id']);
            $bloodValue = self::bloodValue($mission['id']);
            if ($addType) {
                $colorValue = self::lastReward($colorValue, $effect['value']);
                $bloodValue = self::lastReward($bloodValue, $effect['value']);
            }

            // 减少金币（并发扣元宝导致余额不足时同样按元宝不足返回）
            if ($gold > (int)$member['gold'] || !Member::deductCurrency($uid, gold: $gold)) {
                // 金币不足
                return self::fail($return, -6, 10);
            }
            // 精炼石 / 五彩石 2 / 上古血玉 3（add_stone）
            $return['reward'] = match ($type) {
                // 精炼石(免费)
                0 => ['refinestone' => $mission['refinestone'] * 3, 'colorstone' => 0, 'bloodstone' => 0],
                // 精炼石(2倍)、五彩石
                1 => ['refinestone' => $mission['refinestone'] * 7, 'colorstone' => $colorValue * 3, 'bloodstone' => 0],
                // 精炼石(4倍)、五彩石、上古血玉
                2 => ['refinestone' => $mission['refinestone'] * 11, 'colorstone' => $colorValue * 6, 'bloodstone' => $bloodValue * 3],
                // 精炼石(八倍)、五彩石、上古血玉
                3 => ['refinestone' => $mission['refinestone'] * 19, 'colorstone' => $colorValue * 12, 'bloodstone' => $bloodValue * 6],
            };
            MemberStone::add($uid, (int)$return['reward']['refinestone'], MemberStone::REFINE, self::LOG_CLASS, 'get_reward', $now);
            MemberStone::add($uid, (int)$return['reward']['colorstone'], MemberStone::COLOR, self::LOG_CLASS, 'get_reward', $now);
            MemberStone::add($uid, (int)$return['reward']['bloodstone'], MemberStone::BLOOD, self::LOG_CLASS, 'get_reward', $now);

            MemberActivity::update($uid, ['heavendate' => $now]);

            $return['status'] = 1;
            $return['gold'] = self::memberValue($uid, 'gold');
            $return['resource'] = self::resource($uid);
            return $return;
        });
    }

    /**
     * 发送战报到聊天室: c=heaven&m=send_to_chat（id 关卡id，report 战报id）
     *
     * status：-1 参数不完整；-2 元宝不足；-3 关卡不存在；1 成功
     */
    public static function send_to_chat(Context $ctx): array
    {
        // 战报id
        $reportId = (int)$ctx->input('report', 0);
        $id = (int)$ctx->input('id', 0);
        $uid = MemberHandler::resolveUid($ctx);
        $member = self::member($uid);
        if ($id === 0 || $reportId === 0 || $member === null) {
            return self::fail([], -1, 22);
        }
        // 判断金币是否足够
        if ((int)$member['gold'] < self::SHARE_PRICE) {
            return self::fail([], -2, 10);
        }
        // 查询关卡名字
        $info = HeavenMission::ROWS[$id] ?? null;
        if ($info === null) {
            return self::fail([], -3, 4);
        }
        // 扣除金币
        $allGold = Chat::chargeShare($uid, self::SHARE_PRICE);
        if ($allGold === null) {
            return self::fail([], -2, 10);
        }
        $sender = Chat::sender($uid);
        $message = Chat::getMessageStr(38, ['general' => $info['name'], 'id' => $reportId], $sender);
        Chat::sendToAll($message, 38, $reportId, 0, $sender);
        return ['status' => 1, 'all_gold' => $allGold];
    }

    /**
     * 点击购买时弹出的消息框: c=heaven&m=get_price
     */
    public static function get_price(Context $ctx): array
    {
        return ['status' => 1, 'price' => self::PRICE];
    }

    /**
     * 通天塔战斗（移植 battle_model->battle_heaven → battle_mission($position, 8)）
     *
     * 旧版 check_user_team：玩家 -1（没有阵法）/ -3（武将信息错误）时不战斗、status 非 1（pk 返回 -7）；
     * -2（阵法里没有武将）按空队伍继续战斗（空队伍直接判 -3 大败）。
     *
     * @param array<string, mixed> $info HeavenMission 行
     * @return array{status: int, info: array<string, mixed>}
     */
    private static function battleHeaven(int $uid, array $info): array
    {
        $left = TeamBuilder::forMember($uid, pve: true);
        if ($left['status'] === -1 || $left['status'] === -3) {
            return ['status' => $left['status'], 'info' => []];
        }
        $right = TeamBuilder::forMonsters($info, TeamBuilder::MONSTER_HEAVEN);
        $engine = new BattleEngine($left['matrix_type'], $left['matrix_coe']);
        return [
            'status' => 1,
            'info' => $engine->battle($left['team'], $right['team']),
        ];
    }

    /**
     * 掉落掷点（移植 heaven_model->roll）：getrate 为百分比，精确到 0.01%
     */
    private static function roll(int|float $getrate): int
    {
        return $getrate * 100 >= mt_rand(1, 10000) ? 1 : 0;
    }

    /**
     * 扫荡所需元宝（heaven 控制器 _get_price_by_times：次数不足的部分每次 20 元宝，不累加）
     */
    private static function priceByTimes(int $totimes, int $leavetimes): int
    {
        $times = $totimes - $leavetimes;
        return $times <= 0 ? 0 : $times * self::PRICE;
    }

    /**
     * @return array{five_sub: int, ten_sub: int, fifty_sub: int, hundred_sub: int}
     */
    private static function mopupPrice(int $leavetimes): array
    {
        return [
            'five_sub' => self::priceByTimes(5, $leavetimes),
            'ten_sub' => self::priceByTimes(10, $leavetimes),
            'fifty_sub' => self::priceByTimes(50, $leavetimes),
            'hundred_sub' => self::priceByTimes(100, $leavetimes),
        ];
    }

    /**
     * 距离最近的五彩石数量（移植 heaven_model->get_color_value：id 小于本关、colorstone 非 0 的最后一关）
     */
    private static function colorValue(int $id): int
    {
        $value = 0;
        foreach (HeavenMission::ROWS as $row) {
            if ($row['id'] < $id && $row['colorstone'] !== 0) {
                $value = $row['colorstone'];
            }
        }
        return $value;
    }

    /**
     * 都城占领加成后的奖励数（移植 heaven 控制器 get_lastreward：基础值 + 基础值 × value%）
     */
    private static function lastReward(int $base, int $add): int
    {
        return (int)($base + $base * ($add / 100));
    }

    /**
     * 本关 boss 的上古血玉数量（移植 heaven_model->get_blood_value：id 取整到 10 的关卡，bloodstone 非 0）
     */
    private static function bloodValue(int $id): int
    {
        $row = HeavenMission::ROWS[intdiv($id, 10) * 10] ?? null;
        return $row !== null && $row['bloodstone'] !== 0 ? $row['bloodstone'] : 0;
    }

    /**
     * 三种石头的当前数量（旧版 $this->member 的 refinestone / colorstone / bloodstone）
     *
     * @return array{refinestone: int, colorstone: int, bloodstone: int}
     */
    private static function resource(int $uid): array
    {
        return MemberStone::all($uid);
    }

    /**
     * 失败返回：status + 旧版语言包提示 msg
     *
     * @param array<string, mixed> $return
     * @return array<string, mixed>
     */
    private static function fail(array $return, int $status, int $lang): array
    {
        $return['status'] = $status;
        $return['msg'] = self::LANG[$lang];
        return $return;
    }

    /**
     * 双倍奖励活动（heaven 控制器 _initdouble：activity_model->activity_status('double_heaven')）
     */
    private static function isDouble(int $now): bool
    {
        foreach (ActivityTime::ROWS as $row) {
            if ($row['name'] === 'double_heaven') {
                return $row['starttime'] <= $now && $row['endtime'] >= $now;
            }
        }
        return false;
    }

    /**
     * 主公等级 160 起开放（构造函数 _isopen）
     *
     * @param array<string, mixed> $member
     */
    private static function isOpen(array $member): bool
    {
        return (int)$member['level'] >= self::OPEN_LEVEL;
    }

    /**
     * 已开启地图（移植 heaven_model->get_open_map：没有记录时插入 level 1 / stage 0 / mission 0）
     *
     * @return array{id: int, uid: int, level: int, stage: int, mission: int, dateline: int}|null
     */
    private static function getOpenMap(int $uid, int $now): ?array
    {
        $row = self::openMapRow($uid);
        if ($row === null) {
            // 并发首次请求只插入一行（唯一键 uid）
            DB::execute(
                'INSERT IGNORE INTO `zc_sanguo_heaven_open_map` (`uid`, `level`, `stage`, `mission`, `dateline`) VALUES (:uid, 1, 0, 0, :now)',
                ['uid' => $uid, 'now' => $now]
            );
            $row = self::openMapRow($uid);
        }
        return $row;
    }

    /**
     * @return array{id: int, uid: int, level: int, stage: int, mission: int, dateline: int}|null
     */
    private static function openMapRow(int $uid): ?array
    {
        $row = DB::table('heaven_open_map')->select(...self::MAP_COLUMNS)->where('uid', $uid)->whereNull('deleted_at')->first();
        return $row === null ? null : array_map('intval', $row);
    }

    /**
     * @param array<string, int> $data
     */
    private static function updateOpenMap(int $uid, array $data): void
    {
        DB::table('heaven_open_map')->where('uid', $uid)->whereNull('deleted_at')->update(array_map('intval', $data));
    }

    /**
     * 玩家活动信息（移植 member_model->get_activity_info：没有记录时按旧版初始值建档）
     *
     * @return array{uid: int, heavenmission: int, heaventimes: int, freeheaven: int, heavendate: int}|null
     */
    private static function getActivityInfo(int $uid): ?array
    {
        MemberActivity::ensure($uid);
        $row = DB::table('member_activity')->select(...self::ACTIVITY_COLUMNS)->where('uid', $uid)->whereNull('deleted_at')->first();
        return $row === null ? null : array_map('intval', $row);
    }

    /**
     * 玩家活动信息并加行锁（同 getActivityInfo，在事务里使用）
     *
     * @return array{uid: int, heavenmission: int, heaventimes: int, freeheaven: int, heavendate: int}|null
     */
    private static function lockActivity(int $uid): ?array
    {
        MemberActivity::ensure($uid);
        $rows = DB::query(
            'SELECT `' . implode('`, `', self::ACTIVITY_COLUMNS) . '` FROM `zc_sanguo_member_activity` WHERE `uid` = :uid AND `deleted_at` IS NULL FOR UPDATE',
            ['uid' => $uid]
        );
        return $rows === [] ? null : array_map('intval', $rows[0]);
    }

    /**
     * 扫荡补买的次数（$this->member_field['heaventimes']）
     */
    private static function fieldHeaventimes(int $uid): int
    {
        return (int)(DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->value('heaventimes') ?? 0);
    }

    /**
     * 玩家某关的首杀记录（移植 heaven_model->get_mission_log_by_uid($uid, $id)）
     *
     * @return array<string, mixed>|null
     */
    private static function getMissionLog(int $uid, int $missionid): ?array
    {
        $row = DB::table('heaven_log')->select(...self::LOG_COLUMNS)
            ->where('uid', $uid)->where('missionid', $missionid)->whereNull('deleted_at')->first();
        return $row === null ? null : self::castLog($row);
    }

    /**
     * 玩家全部首杀记录，按关卡 id 索引（移植 get_mission_log_by_uid($uid)）
     *
     * @return array<int, array<string, mixed>>
     */
    private static function getMissionLogs(int $uid): array
    {
        $return = [];
        $rows = DB::table('heaven_log')->select(...self::LOG_COLUMNS)->where('uid', $uid)->whereNull('deleted_at')->orderBy('id')->get();
        foreach ($rows as $row) {
            $row = self::castLog($row);
            $return[$row['missionid']] = $row;
        }
        return $return;
    }

    /**
     * 关卡攻略列表：最近 7 条首杀记录，按记录 id 索引（移植 heaven_model->get_mission_log）
     *
     * @return array<int, array{id: int, uid: int, level: int, nickname: string, tree: int}>
     */
    private static function getMissionLogList(int $missionid): array
    {
        $return = [];
        $rows = DB::table('heaven_log')->select('id', 'uid', 'level', 'nickname', 'tree')
            ->where('missionid', $missionid)->whereNull('deleted_at')->orderBy('id', 'DESC')->limit(7)->get();
        foreach ($rows as $row) {
            $return[(int)$row['id']] = [
                'id' => (int)$row['id'],
                'uid' => (int)$row['uid'],
                'level' => (int)$row['level'],
                'nickname' => (string)$row['nickname'],
                'tree' => (int)$row['tree'],
            ];
        }
        return $return;
    }

    /**
     * 添加首杀记录（移植 heaven_model->add_mission_log；first_reward 取列默认值 0，随后由 update_mission_log 置 1），
     * 返回记录 id（即战报 id）
     *
     * @param array<string, mixed> $member
     */
    private static function addMissionLog(array $member, int $level, int $missionid, int $tree, int $now): int
    {
        return (int)DB::table('heaven_log')->insertGetId([
            'uid' => (int)$member['uid'],
            'nickname' => (string)$member['nickname'],
            'missionlevel' => $level,
            'missionid' => $missionid,
            'dateline' => $now,
            'level' => (int)$member['level'],
            'tree' => $tree,
        ]);
    }

    /**
     * 更新首杀领奖记录（移植 heaven_model->update_mission_log(['first_reward' => 1], $mid)）
     */
    private static function updateMissionLog(int $uid, int $missionid): void
    {
        DB::table('heaven_log')->where('uid', $uid)->where('missionid', $missionid)->whereNull('deleted_at')->update(['first_reward' => 1]);
    }

    /**
     * 战报是否已存在（移植 heaven_model->check_report）
     */
    private static function checkReport(int $id): bool
    {
        return DB::table('heaven_report')->where('id', $id)->whereNull('deleted_at')->exists();
    }

    /**
     * 添加战报（移植 heaven_model->add_mission_report：report 列存 json_encode(base64(gzcompress(战报 json)))；
     * 旧版不写 background）
     *
     * @param array<string, mixed> $mission
     * @param array<string, mixed> $member
     */
    private static function addMissionReport(int $id, string $report, array $mission, array $member): void
    {
        DB::table('heaven_report')->insert([
            'id' => $id,
            'report' => (string)json_encode($report),
            'nickname' => (string)$member['nickname'],
            'userlevel' => (int)$member['level'],
            'name' => $mission['name'],
            'level' => $mission['level'],
        ]);
    }

    /**
     * 按 id 取战报（移植 heaven_model->get_report_by_id），不存在时 null
     *
     * @return array{id: int, nickname: string, userlevel: int, name: string, level: int, background: int, report: mixed}|null
     */
    private static function getReportById(int $id): ?array
    {
        $result = DB::table('heaven_report')->select('id', 'report', 'nickname', 'userlevel', 'name', 'level', 'background')
            ->where('id', $id)->whereNull('deleted_at')->first();
        if ($result === null) {
            return null;
        }
        $raw = (string)$result['report'];
        $report = json_decode($raw, true);
        if (!is_array($report)) {
            // 压缩存储：json 字符串里是 base64(gzcompress(json))
            $binary = base64_decode(is_string($report) ? $report : $raw);
            $json = $binary !== false ? gzuncompress($binary) : false;
            $report = $json !== false ? json_decode($json, true) : null;
        }
        return [
            'id' => (int)$result['id'],
            'nickname' => (string)$result['nickname'],
            'userlevel' => (int)$result['userlevel'],
            'name' => (string)$result['name'],
            'level' => (int)$result['level'],
            'background' => (int)$result['background'],
            'report' => $report,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function castLog(array $row): array
    {
        foreach ($row as $key => $value) {
            $row[$key] = $key === 'nickname' ? (string)$value : (int)$value;
        }
        return $row;
    }

    private static function memberValue(int $uid, string $column): int
    {
        return (int)(DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value($column) ?? 0);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function member(int $uid): ?array
    {
        if ($uid <= 0) {
            return null;
        }
        return DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
    }
}
