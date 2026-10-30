<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Campaign;

use Hanjiang\Battle\BattleEngine;
use Hanjiang\Battle\TeamBuilder;
use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\Pvp\SiegeHandler;
use Hanjiang\MasterData\ActivityTime;
use Hanjiang\MasterData\Price;
use Hanjiang\MasterData\Soul;
use Hanjiang\MasterData\SoulMission;
use Hanjiang\MasterData\SoulReward;
use Hanjiang\MasterData\SoulScene;
use Hanjiang\MasterData\SoulType;
use Hanjiang\Model\Member;
use Hanjiang\Model\MemberActivity;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 将魂星路（移植旧版 controllers/tower.php + models/tower_model.php，路径相对旧版 s1/application/）
 *
 * 配置：场景 SoulScene、关卡 SoulMission（position1~9 → 怪物 SoulMonster，battle_model->battle_soul 即
 * battle_mission type 2）、每日奖励倍数 SoulReward、次数价格 Price（act 列）、将魂模板 Soul / SoulType、
 * 双倍活动 ActivityTime（double_tower）。
 * 玩家存储：member_activity（towerlevel / towerstage / towermission / freetower / towertimes / souldate）、
 * member_field.soultimes（今日购买次数）、soul_open_map（已开启地图）、soul_log（首杀记录，即攻略列表）、
 * soul_report（首杀战报）。
 *
 * 客户端调用：get_scene_list、get_mission_list、get_mission_info、get_mopup_price、mop_up、pk、get_report_list、
 * get_report_info、get_price、buy、reward_info、get_reward、send_to_chat（战报分享）。
 * 旧版 index（按 member_activity 记录的位置转发到上面三个列表接口）前端没有调用，不移植。
 *
 * 依赖未移植：
 * - 将魂背包 member.soulpack / souluse（旧版 _isfull：背包满时掉落将魂的关卡返回 2），新版按未满处理；
 * - 将魂掉落 soul_model->add_hero_soul（own_soul），掉落判定照旧版掷点，但不发放，hero_soul 为空；
 * - member_model->sub_gold 的 gold_log 元宝流水、日常任务 task_model->task_action(5)、四方来朝元宝计数；
 *   add_soul 的原魂流水 _soul_log；stat_model 消费统计（tower_buy / tower_jiabei）。
 */
final class TowerHandler
{
    /** 开放等级：主公等级大于 50（旧版 $this->member['level'] > 50） */
    private const int OPEN_LEVEL = 50;

    /** 最大场景（tower_model->_max_step） */
    private const int MAX_STEP = 7;

    /** 战报分享到聊天室的元宝花费（旧版 send_to_chat $price） */
    private const int SHARE_PRICE = 2;

    /** 扫荡允许的次数 */
    private const array MOPUP_TIMES = [5, 10, 50, 100];

    /** 旧版 zc_sanguo_member_activity 中本玩法读写的字段 */
    private const array ACTIVITY_COLUMNS = ['uid', 'towerlevel', 'towerstage', 'towermission', 'freetower', 'towertimes', 'souldate'];

    /** 旧版 zc_sanguo_soul_log 的字段 */
    private const array LOG_COLUMNS = ['id', 'uid', 'nickname', 'missionlevel', 'missionstage', 'missionid', 'dateline', 'level', 'disable', 'tree', 'first_reward'];

    /** 旧版 zc_sanguo_soul_open_map 的字段 */
    private const array MAP_COLUMNS = ['id', 'uid', 'level', 'stage', 'mission', 'dateline'];

    /**
     * 获取场景列表: c=tower&m=get_scene_list
     */
    public static function get_scene_list(Context $ctx): array
    {
        /**
         * -3 获取场景信息失败
         * -2 获取地图信息失败
         * -1 等级不足
         *  0 失败
         *  1 成功
         */
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        if (!self::isOpen($member)) {
            // 等级不足
            $return['status'] = -1;
            return $return;
        }
        $uid = (int)$member['uid'];
        // 获取活动信息
        $map = self::getOpenMap($uid, time());
        if ($map === null) {
            // 获取地图信息失败
            $return['status'] = -2;
            return $return;
        }

        $level = $map['level'];
        // 获取场景列表
        $list = self::getSceneList($level);
        if ($list === []) {
            // 获取场景列表失败
            $return['status'] = -3;
            return $return;
        }

        $stage = $map['stage'];
        $listArr = [];
        foreach ($list as $key => $value) {
            // 按场景id开启
            $listArr[$key]['id'] = $value['id'];
            $listArr[$key]['name'] = $value['name'];
            if ((int)$value['openmethod'] === 1) {
                if ($stage > $value['opencondition']) {
                    $listArr[$key]['openstatus'] = 1;
                    $listArr[$key]['killed'] = 1;
                } elseif ($stage < $value['opencondition']) {
                    $listArr[$key]['openstatus'] = 0;
                    $listArr[$key]['killed'] = 0;
                } else {
                    $listArr[$key]['openstatus'] = 1;
                    $listArr[$key]['killed'] = 0;
                }
            }
        }
        self::updateActivityInfo($uid, ['towerlevel' => 1, 'towerstage' => 0, 'towermission' => 0]);
        $return['status'] = 1;
        $return['scene'] = $listArr;
        return $return;
    }

    /**
     * 获取关卡列表: c=tower&m=get_mission_list（s 场景id）
     */
    public static function get_mission_list(Context $ctx): array
    {
        /**
         * -5 获取关卡列表失败
         * -4 当前关卡未开启
         * -3 获取地图信息失败
         * -2 获取活动信息失败
         * -1 等级不足
         *  0 失败
         *  1 成功
         */
        $now = time();
        $return = ['status' => 0];
        $stage = (int)$ctx->input('s', 0);
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        if (!self::isOpen($member)) {
            // 等级不足
            $return['status'] = -1;
            return $return;
        }
        $uid = (int)$member['uid'];

        $activity = self::getActivityInfo($uid);
        if ($activity === null) {
            // 获取活动信息失败
            $return['status'] = -2;
            return $return;
        }
        $times = $activity['towertimes'] + $activity['freetower'];

        $today = (int)date('Ymd', $now);
        $last = (int)date('Ymd', $activity['souldate']);
        $getReward = 0;
        if ($last >= $today) {
            // 奖励已领取
            $getReward = 1;
        }
        // 获取已开启地图信息
        $map = self::getOpenMap($uid, $now);
        if ($map === null) {
            // 获取地图信息失败
            $return['status'] = -3;
            return $return;
        }
        $sceneid = self::getOpenScene($map['stage']);
        if ($stage > $sceneid) {
            // 当前关卡未开启
            $return['status'] = -4;
            return $return;
        }

        // 获取关卡信息
        $list = self::getMissionList($stage);
        if ($list === []) {
            // 获取关卡列表失败
            $return['status'] = -5;
            return $return;
        }

        $double = self::isDouble($now);
        // 获取玩家通过关卡信息
        $umissList = self::getMissionLogs($uid);
        $field = ['id', 'level', 'name', 'type', 'image'];
        $listArr = [];
        $nowKey = 0;
        foreach ($list as $key => $value) {
            foreach ($field as $v) {
                $listArr[$key][$v] = $value[$v];
            }
            if (($map['mission'] + 1) > $value['id']) {
                $nowKey = $key;
                $listArr[$key]['openstatus'] = 1;
                $listArr[$key]['killed'] = 1;
            } elseif (($map['mission'] + 1) < $value['id']) {
                $listArr[$key]['openstatus'] = 0;
                $listArr[$key]['killed'] = 0;
            } else {
                $nowKey = $key;
                $listArr[$key]['openstatus'] = 1;
                $listArr[$key]['killed'] = 0;
            }
            // 判断是否已经领取首杀奖励
            $listArr[$key]['firstkill'] = 1;
            if (isset($umissList[$value['id']]) && $umissList[$value['id']]['first_reward'] === 1) {
                $listArr[$key]['firstkill'] = 0;

                // 双倍活动期间，奖励翻倍（旧版翻倍的是当前关卡 $list[$now]，照旧版）
                if ($double) {
                    $list[$nowKey]['soul'] *= 2;
                }
            }
        }

        $heroSoul = [];
        if ($list[$nowKey]['soulid'] > 0 && $list[$nowKey]['getrate'] > 0) {
            $heroSoul = self::heroSoulInfo($list[$nowKey]['soulid']);
        }

        $return['now']['id'] = $list[$nowKey]['id'];
        $return['now']['soul'] = $list[$nowKey]['soul'];
        // 首杀奖励原魂数量
        $return['now']['first_soul_num'] = $list[$nowKey]['first_soul_num'];
        // 判断当前关卡是否是首杀
        $return['now']['firstkill'] = 1;
        if (isset($umissList[$return['now']['id']]) && $umissList[$return['now']['id']]['first_reward'] === 1) {
            $return['now']['firstkill'] = 0;

            // 双倍活动期间，奖励翻倍（旧版在 now.soul 赋值之后才翻倍，不影响返回值，照旧版）
            if ($double) {
                $list[$nowKey]['soul'] *= 2;
            }
        }

        self::updateActivityInfo($uid, ['towerstage' => $stage, 'towermission' => 0]);
        $return['status'] = 1;
        // 关卡列表
        $return['list'] = $listArr;
        // 剩余次数
        $return['times'] = $times;
        $return['get_reward'] = $getReward;
        $return['now']['hero_soul'] = $heroSoul;
        return $return;
    }

    /**
     * 获取关卡信息: c=tower&m=get_mission_info（id 关卡id）
     */
    public static function get_mission_info(Context $ctx): array
    {
        /**
         * -4 当前关卡未开启
         * -3 获取地图信息失败
         * -2 获取关卡信息失败
         * -1 等级不足
         *  0 失败
         *  1 成功
         * （旧版注释未列出：-5 关卡未开启；-6 获取活动信息失败；-7 获取价格失败）
         */
        $now = time();
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        if (!self::isOpen($member)) {
            // 等级不足
            $return['status'] = -1;
            return $return;
        }
        $uid = (int)$member['uid'];

        $id = (int)$ctx->input('id', 0);
        // 根据id获取关卡信息
        $info = self::getMissionById($id);
        if ($info === null) {
            // 获取关卡信息失败
            $return['status'] = -2;
            return $return;
        }

        // 获取已开启地图信息
        $map = self::getOpenMap($uid, $now);
        if ($map === null) {
            // 获取地图信息失败
            $return['status'] = -3;
            return $return;
        }
        $sceneid = self::getOpenScene($map['stage']);
        if ($info['stage'] > $sceneid) {
            // 当前关卡未开启
            $return['status'] = -4;
            return $return;
        }

        if ($id > $map['mission'] + 1) {
            // 关卡未开启
            $return['status'] = -5;
            return $return;
        }

        // 获取玩家当前关卡信息
        $uminfo = self::getMissionLog($uid, $id);
        // 关卡奖励信息处理
        $infoArr = [];
        foreach (['id', 'soul', 'first_soul_num'] as $v) {
            $infoArr[$v] = $info[$v];
        }
        // 确定是否首杀
        $infoArr['firstkill'] = 1;
        if ($uminfo !== null && $uminfo['first_reward'] === 1) {
            $infoArr['firstkill'] = 0;

            // 双倍活动期间，奖励翻倍
            if (self::isDouble($now)) {
                $infoArr['soul'] *= 2;
            }
        }
        $infoArr['hero_soul'] = [];
        if ($info['getrate'] > 0) {
            $infoArr['hero_soul'] = self::heroSoulInfo($info['soulid']);
        }
        self::updateActivityInfo($uid, ['towermission' => $id]);

        $mopUp = 0;
        if ($id <= $map['mission']) {
            // 扫荡信息
            $mopUp = 1;
            if ($infoArr['firstkill'] === 1) {
                $mopUp = 0;
            }
            $activity = self::getActivityInfo($uid);
            if ($activity === null) {
                // 获取活动信息失败
                $return['status'] = -6;
                return $return;
            }
            // 当前剩余次数
            $leavetimes = $activity['freetower'] + $activity['towertimes'];
            // 今日将魂购买次数（旧版这里 + 1，get_mopup_price / mop_up 不加，照旧版）
            $soultimes = self::soultimes($uid) + 1;
            $infoArr['five_sub'] = self::getPriceByTimes(5, $soultimes, $leavetimes);
            $infoArr['ten_sub'] = self::getPriceByTimes(10, $soultimes, $leavetimes);
            $infoArr['fifty_sub'] = self::getPriceByTimes(50, $soultimes, $leavetimes);
            $infoArr['hundred_sub'] = self::getPriceByTimes(100, $soultimes, $leavetimes);
            if ($infoArr['five_sub'] < 0 || $infoArr['ten_sub'] < 0 || $infoArr['fifty_sub'] < 0 || $infoArr['hundred_sub'] < 0) {
                // 获取价格失败
                $return['status'] = -7;
                return $return;
            }
        }

        $return['status'] = 1;
        $return['mission'] = $infoArr;
        $return['mop_up'] = $mopUp;
        return $return;
    }

    /**
     * 获取扫荡价格: c=tower&m=get_mopup_price（id 关卡id）
     */
    public static function get_mopup_price(Context $ctx): array
    {
        /**
         * -7 获取价格失败
         * -6 获取活动信息失败
         * -5 无法扫荡
         * -4 当前关卡未开启
         * -3 获取地图信息失败
         * -2 获取关卡信息失败
         * -1 等级不足
         *  0 失败
         *  1 成功
         */
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        if (!self::isOpen($member)) {
            // 等级不足
            $return['status'] = -1;
            return $return;
        }
        $uid = (int)$member['uid'];

        $id = (int)$ctx->input('id', 0);
        // 根据id获取关卡信息
        $info = self::getMissionById($id);
        if ($info === null) {
            // 获取关卡信息失败
            $return['status'] = -2;
            return $return;
        }

        // 获取已开启地图信息
        $map = self::getOpenMap($uid, time());
        if ($map === null) {
            // 获取地图信息失败
            $return['status'] = -3;
            return $return;
        }
        $sceneid = self::getOpenScene($map['stage']);
        if ($info['stage'] > $sceneid) {
            // 当前关卡未开启
            $return['status'] = -4;
            return $return;
        }

        if ($id > $map['mission']) {
            // 无法扫荡
            $return['status'] = -5;
            return $return;
        }
        // 扫荡信息
        $activity = self::getActivityInfo($uid);
        if ($activity === null) {
            // 获取活动信息失败
            $return['status'] = -6;
            return $return;
        }
        // 当前剩余次数
        $leavetimes = $activity['freetower'] + $activity['towertimes'];
        // 今日将魂购买次数
        $soultimes = self::soultimes($uid);
        $price = self::mopupPrice($soultimes, $leavetimes);
        if ($price === null) {
            // 获取价格失败
            $return['status'] = -7;
            return $return;
        }

        $return['status'] = 1;
        $return['info'] = $price;
        return $return;
    }

    /**
     * 扫荡: c=tower&m=mop_up（id 关卡id，times 次数 5 / 10 / 50 / 100）
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
         * （旧版注释未列出：-13 首杀奖励未领取，不能扫荡）
         */
        $now = time();
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        $uid = (int)$member['uid'];
        $id = (int)$ctx->input('id', 0);
        $times = (int)$ctx->input('times', 0);
        $result = self::getMissionLog($uid, $id);
        if ($result !== null) {
            // 防刷接口
            if ($result['first_reward'] === 0) {
                // 首杀不能扫荡
                $return['status'] = -13;
                return $return;
            }
        }
        if (!in_array($times, self::MOPUP_TIMES, true)) {
            // 次数不允许
            $return['status'] = -7;
            return $return;
        }

        if (!self::isOpen($member)) {
            // 等级不足
            $return['status'] = -1;
            return $return;
        }

        $activity = self::getActivityInfo($uid);
        if ($activity === null) {
            // 获取活动信息失败
            $return['status'] = -2;
            return $return;
        }

        // 根据id获取关卡信息
        $info = self::getMissionById($id);
        if ($info === null) {
            // 获取关卡信息失败
            $return['status'] = -3;
            return $return;
        }

        if (self::isFull() && $info['soulid'] > 0) {
            // 包裹已满
            $return['status'] = 2;
            return $return;
        }

        // 获取已开启地图信息
        $map = self::getOpenMap($uid, $now);
        if ($map === null) {
            // 获取地图信息失败
            $return['status'] = -4;
            return $return;
        }

        $sceneid = self::getOpenScene($map['stage']);
        if ($info['stage'] > $sceneid) {
            // 当前场景未开启
            $return['status'] = -5;
            return $return;
        }

        if ($id > $map['mission']) {
            // 未击败该关卡
            $return['status'] = -6;
            return $return;
        }

        // 当前剩余次数
        $leavetimes = $activity['freetower'] + $activity['towertimes'];
        // 今日将魂购买次数
        $soultimes = self::soultimes($uid);
        $gold = self::getPriceByTimes($times, $soultimes, $leavetimes);
        if ($gold < 0) {
            // 获取元宝消耗失败
            $return['status'] = -8;
            return $return;
        }

        if ($gold > (int)$member['gold']) {
            // 元宝不足
            $return['status'] = 3;
            return $return;
        }
        if (!Member::deductCurrency($uid, gold: $gold)) {
            // 扣除失败
            $return['status'] = -9;
            return $return;
        }

        if ($times <= $leavetimes) {
            // 剩余次数足够
            $data = [];
            if ($activity['freetower'] >= $times) {
                // 免费次数足够
                $data['freetower'] = $activity['freetower'] - $times;
            } else {
                // 免费次数不足
                $data['freetower'] = 0;
                // 扣购买次数
                $data['towertimes'] = $leavetimes - $times;
            }
            self::updateActivityInfo($uid, $data);
        } else {
            // 剩余次数不足
            $soultimes = $times - $leavetimes + $soultimes;
            // 增加购买次数
            self::updateFieldInfo($uid, ['soultimes' => $soultimes]);
            // 扣剩余次数
            self::updateActivityInfo($uid, ['freetower' => 0, 'towertimes' => 0]);
        }

        // 计算结果
        $heroSoulArr = [];
        $soulid = $info['soulid'];
        // 加将魂
        if ($soulid > 0) {
            $num = 0;
            for ($i = 0; $i < $times; $i++) {
                $heroSoul = self::roll($info['soulid'], $info['getrate']);
                if ($heroSoul > 0) {
                    ++$num;
                }
            }

            if ($num > 0) {
                // 双倍活动期间，奖励翻倍
                if (self::isDouble($now)) {
                    $num *= 2;
                }
                // 依赖未移植：soul_model->add_hero_soul($soulid, 0, $num)（own_soul + member.souluse，失败返回 -10），
                // 发放后 hero_soul 为 name / quality / level / num；主线任务 mainquest_model->task_action(15, 1)
            }
        }

        $soul = $info['soul'];
        // 加原魂
        if ($soul > 0) {
            $soul *= $times;

            // 双倍活动期间，奖励翻倍
            if (self::isDouble($now)) {
                $soul *= 2;
            }

            // 加原魂失败（-11）：Member::addCurrency 不返回结果
            Member::addCurrency($uid, soul: $soul);
        }

        $activity = self::getActivityInfo($uid) ?? ['freetower' => 0, 'towertimes' => 0];
        $leavetimes = $activity['freetower'] + $activity['towertimes'];
        // 下一次扫荡价格
        $price = self::mopupPrice($soultimes, $leavetimes);
        if ($price === null) {
            // 获取价格失败
            $return['status'] = -12;
            return $return;
        }

        $return['status'] = 1;
        $return['info'] = $price;
        $return['info']['soul'] = $soul;
        $return['info']['hero_soul'] = $heroSoulArr;
        $return['times'] = $leavetimes;
        $return['gold'] = self::memberValue($uid, 'gold');
        return $return;
    }

    /**
     * 战斗: c=tower&m=pk（id 关卡id）
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
         * （旧版注释未列出：-8 扣次数失败）
         */
        $now = time();
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        $uid = (int)$member['uid'];
        $id = (int)$ctx->input('id', 0);
        if (!self::isOpen($member)) {
            // 等级不足
            $return['status'] = -1;
            return $return;
        }

        $activity = self::getActivityInfo($uid);
        if ($activity === null) {
            // 获取活动信息失败
            $return['status'] = -2;
            return $return;
        }
        $times = $activity['towertimes'] + $activity['freetower'];
        if ($times < 1) {
            // 次数不足
            $return['status'] = 3;
            return $return;
        }

        // 根据id获取关卡信息
        $info = self::getMissionById($id);
        if ($info === null) {
            // 获取关卡信息失败
            $return['status'] = -3;
            return $return;
        }

        if (self::isFull() && $info['soulid'] > 0) {
            // 包裹已满
            $return['status'] = 2;
            return $return;
        }

        // 获取已开启地图信息
        $map = self::getOpenMap($uid, $now);
        if ($map === null) {
            // 获取地图信息失败
            $return['status'] = -4;
            return $return;
        }
        $sceneid = self::getOpenScene($map['stage']);
        if ($info['stage'] > $sceneid) {
            // 当前场景未开启
            $return['status'] = -5;
            return $return;
        }

        if ($id > $map['mission'] + 1) {
            // 关卡未开启
            $return['status'] = -6;
            return $return;
        }

        // [战斗模型]战斗（battle_model->battle_soul：关卡 position1~9）
        $battle = self::battleSoul($uid, $info);
        if ($battle['status'] !== 1) {
            // 获取战斗信息失败
            $return['status'] = -7;
            return $return;
        }
        // 扣次数
        if ($activity['freetower'] > 0) {
            $data = ['freetower' => $activity['freetower'] - 1];
        } elseif ($activity['towertimes'] > 0) {
            $data = ['towertimes' => $activity['towertimes'] - 1];
        } else {
            $return['status'] = -8;
            return $return;
        }
        self::updateActivityInfo($uid, $data);

        $clear = 0;
        $report = 0;
        $soul = null;
        $heroSoulArr = [];
        if ($battle['info']['assess'] > 0) {
            // 胜利
            // 当前id 大于 已过关卡 id
            if ($id > $map['mission']) {
                $tower = ['mission' => $id];
                $last = self::getLastMission($info['stage']);
                // 当前关卡最后一个id
                if ($last === $id) {
                    $clear = 1;
                    $tower['stage'] = $info['stage'];
                }
                self::updateOpenMap($uid, $tower);
            }

            $soulid = $info['soulid'];
            // 加将魂
            if ($soulid > 0) {
                $heroSoul = self::roll($info['soulid'], $info['getrate']);
                if ($heroSoul > 0) {
                    // 依赖未移植：soul_model->add_hero_soul($soulid)（own_soul + member.souluse，失败返回 -9），
                    // 发放后 hero_soul 为 id / image / name / quality / power；主线任务 mainquest_model->task_action(15, 1)
                }
            }

            $soul = $info['soul'];

            // 是否首杀
            $isFirst = 0;

            // 获取战斗记录
            $log = self::getMissionLog($uid, $info['id']);
            if ($log === null) {
                // 用户神树信息
                $treeId = (int)$battle['info']['leftbuff']['tree'];
                $reportId = self::addMissionLog($member, 1, $info['stage'], $info['id'], $treeId, $now);
                // 战斗记录不存在则添加
                if (!self::checkReport($reportId)) {
                    $gzipreport = base64_encode(gzcompress((string)json_encode($battle['info'])));
                    // 记录log
                    self::addMissionReport($reportId, $gzipreport, $info, $member);
                    $report = $reportId;
                }
                // 首杀
                $isFirst = 1;
            } elseif ($log['first_reward'] === 0) {
                // 对之前过关老玩家没有领取首杀奖励处理
                $isFirst = 1;
                // 更新首杀领奖记录
                self::updateMissionLog($uid, ['first_reward' => 1], $id);
            }
            // 首杀奖励
            if ($isFirst === 1) {
                if ($info['first_soul_num'] > 0) {
                    // 加原魂失败（-10）：Member::addCurrency 不返回结果
                    Member::addCurrency($uid, soul: $info['first_soul_num']);
                    $return['info']['first_soul_num'] = $info['first_soul_num'];
                    $return['info']['firstkill'] = 1;
                }
            } elseif ($soul > 0) {
                // 加原魂
                // 双倍活动期间，奖励翻倍
                if (self::isDouble($now)) {
                    $soul *= 2;
                }
                // 加原魂失败（-10）：Member::addCurrency 不返回结果
                Member::addCurrency($uid, soul: $soul);
            }
        }

        foreach (['id', 'background'] as $value) {
            $return['info'][$value] = $info[$value];
        }
        $return['status'] = 1;
        $return['clear'] = $clear;
        $return['info']['report'] = $report;
        $return['info']['times'] = $activity['freetower'] + $activity['towertimes'] - 1;
        $return['info']['win'] = $battle['info']['assess'];
        $return['info']['reward_soul'] = $soul ?? 0;
        $return['info']['hero_soul'] = $heroSoulArr;
        $return['info']['soul'] = self::memberValue($uid, 'soul');
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
    }

    /**
     * 获取攻略列表: c=tower&m=get_report_list（id 关卡id）
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
            $return['status'] = 2;
            return $return;
        }
        $return['status'] = 1;
        $return['list'] = $log;
        return $return;
    }

    /**
     * 获取战报: c=tower&m=get_report_info（id 战报id）
     */
    public static function get_report_info(Context $ctx): array
    {
        /**
         * -1 获取攻略列表失败
         *  0 失败
         *  1 成功
         *  2 攻略不存在
         */
        $return = ['status' => 0, 'battle' => [], 'info' => []];
        $id = (int)$ctx->input('id', 0);
        $result = self::getReportById($id);
        if ($result['status'] !== 1) {
            if ($result['status'] === -1) {
                // 攻略不存在 返回2
                $return['status'] = 2;
            } else {
                // 获取攻略列表失败 返回-1
                $return['status'] = -1;
            }
            return $return;
        }
        $info = $result['info'];
        $return['status'] = 1;
        $return['battle'] = $info['report'];
        $return['battle']['left_role'] = [
            'name' => $info['nickname'],
            'level' => $info['userlevel'],
        ];
        $return['battle']['right_role'] = [
            'name' => $info['name'],
            'level' => $info['level'],
        ];
        $return['info']['win'] = $info['report']['assess'] ?? null;
        $return['info']['background'] = $info['background'];
        return $return;
    }

    /**
     * 获取购买价格: c=tower&m=get_price
     */
    public static function get_price(Context $ctx): array
    {
        /**
         * -1 等级不足
         *  0 失败
         *  1 成功
         */
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        if (!self::isOpen($member)) {
            // 等级不足
            $return['status'] = -1;
            return $return;
        }

        $daytimes = self::soultimes((int)$member['uid']) + 1;
        $gold = self::actPrice($daytimes);

        $return['status'] = 1;
        $return['price'] = $gold;
        return $return;
    }

    /**
     * 购买次数: c=tower&m=buy
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
         */
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        if (!self::isOpen($member)) {
            // 等级不足
            $return['status'] = -1;
            return $return;
        }
        $uid = (int)$member['uid'];

        $daytimes = self::soultimes($uid) + 1;
        $gold = self::actPrice($daytimes);
        if ($gold > (int)$member['gold']) {
            // 金币不足
            $return['status'] = 2;
            return $return;
        }

        $activity = self::getActivityInfo($uid);
        if ($activity === null) {
            // 获取活动信息失败
            $return['status'] = -2;
            return $return;
        }

        $times = $activity['freetower'] + $activity['towertimes'] + 1;
        // 旧版先加次数再扣元宝，照旧版
        self::updateActivityInfo($uid, ['towertimes' => $activity['towertimes'] + 1]);

        if (!Member::deductCurrency($uid, gold: $gold)) {
            // 扣除金币失败
            $return['status'] = -3;
            return $return;
        }
        self::updateFieldInfo($uid, ['soultimes' => $daytimes]);

        $daytimes += 1;
        $next = self::actPrice($daytimes);

        // 依赖未移植：消费统计 stat_model->tower_buy

        $return['status'] = 1;
        $return['times'] = $times;
        $return['gold'] = self::memberValue($uid, 'gold');
        $return['next'] = $next;
        return $return;
    }

    /**
     * 奖励信息: c=tower&m=reward_info
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
         * （旧版注释未列出：-6 获取场景信息失败）
         */
        $now = time();
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        if (!self::isOpen($member)) {
            // 等级不足
            $return['status'] = -1;
            return $return;
        }
        $uid = (int)$member['uid'];

        $activity = self::getActivityInfo($uid);
        if ($activity === null) {
            // 获取活动信息失败
            $return['status'] = -2;
            return $return;
        }
        if ((int)date('Ymd', $activity['souldate']) >= (int)date('Ymd', $now)) {
            // 奖励已领取
            $return['status'] = 2;
            return $return;
        }

        $reward = array_values(SoulReward::ROWS);
        if ($reward === []) {
            // 获取奖励失败
            $return['status'] = -3;
            return $return;
        }
        // 获取已开启地图信息
        $map = self::getOpenMap($uid, $now);
        if ($map === null) {
            // 获取地图信息失败
            $return['status'] = -4;
            return $return;
        }

        if ($map['mission'] <= 0) {
            // 未击败关卡
            $return['status'] = 3;
            return $return;
        }

        $mission = self::getMissionById($map['mission']);
        if ($mission === null) {
            // 获取关卡信息失败
            $return['status'] = -5;
            return $return;
        }

        // 取都城占领加成（占领效果 type 4：reward 按 value% 加成）
        $effect = SiegeHandler::getCityEffect($member, $now);
        if ($effect['status'] === 1 && $effect['type'] === 4) {
            $mission['reward'] = (int)($mission['reward'] + $mission['reward'] * ($effect['value'] / 100));
            $return['effect'] = $effect['value'] + 100;
        } else {
            $return['effect'] = 0;
        }

        $scene = self::getSceneById($map['stage'] + 1);
        if ($scene === null) {
            $return['status'] = -6;
            return $return;
        }

        $return['status'] = 1;
        $return['info'] = [
            'stage' => $scene['name'],
            'mission' => $mission['id'] + 1,
        ];
        $return['base_reward'] = $mission['reward'];
        $return['normal_reward'] = [
            'gold' => $reward[0]['gold'],
            'multiple' => $reward[0]['ratio'] / 100,
        ];
        $return['best_reward'] = [
            'gold' => $reward[1]['gold'],
            'multiple' => $reward[1]['ratio'] / 100,
        ];
        return $return;
    }

    /**
     * 获取奖励: c=tower&m=get_reward（type 1 / 2 元宝加倍，3 不加倍）
     */
    public static function get_reward(Context $ctx): array
    {
        /**
         * -7 加原魂失败
         * -6 扣除金币失败
         * -5 获取关卡信息失败
         * -4 获取地图信息失败
         * -3 获取奖励信息失败
         * -2 获取活动信息失败
         * -1 等级不足
         *  0 失败
         *  1 成功
         *  2 已领取
         *  3 未击败关卡
         *  4 金币不足
         */
        $now = time();
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        if (!self::isOpen($member)) {
            // 等级不足
            $return['status'] = -1;
            return $return;
        }
        $uid = (int)$member['uid'];

        $activity = self::getActivityInfo($uid);
        if ($activity === null) {
            // 获取活动信息失败
            $return['status'] = -2;
            return $return;
        }
        if ((int)date('Ymd', $activity['souldate']) >= (int)date('Ymd', $now)) {
            // 奖励已领取
            $return['status'] = 2;
            return $return;
        }

        $type = (int)$ctx->input('type', 0);
        $reward = null;
        if ($type < 3) {
            $reward = SoulReward::ROWS[$type] ?? null;
            if ($reward === null) {
                $return['status'] = -3;
                return $return;
            }
            $gold = $reward['gold'];
        } else {
            $gold = 0;
        }

        // 获取已开启地图信息
        $map = self::getOpenMap($uid, $now);
        if ($map === null) {
            // 获取地图信息失败
            $return['status'] = -4;
            return $return;
        }

        if ($map['mission'] <= 0) {
            // 未击败关卡
            $return['status'] = 3;
            return $return;
        }

        $mission = self::getMissionById($map['mission']);
        if ($mission === null) {
            // 获取关卡信息失败
            $return['status'] = -5;
            return $return;
        }

        // 取都城占领加成（占领效果 type 4：reward 按 value% 加成）
        $effect = SiegeHandler::getCityEffect($member, $now);
        if ($effect['status'] === 1 && $effect['type'] === 4) {
            $mission['reward'] = (int)($mission['reward'] + $mission['reward'] * ($effect['value'] / 100));
            $return['effect'] = $effect['value'] + 100;
        } else {
            $return['effect'] = 0;
        }

        if ($gold > 0 && $reward !== null) {
            if ($gold > (int)$member['gold']) {
                // 金币不足
                $return['status'] = 4;
                return $return;
            }
            if (!Member::deductCurrency($uid, gold: $gold)) {
                $return['status'] = -6;
                return $return;
            }
            $soul = (int)($reward['ratio'] / 100 * $mission['reward']);
        } else {
            $soul = $mission['reward'];
        }

        // 加原魂失败（-7）：Member::addCurrency 不返回结果
        Member::addCurrency($uid, soul: $soul);

        self::updateActivityInfo($uid, ['souldate' => $now]);

        // 依赖未移植：消费统计 stat_model->tower_jiabei

        $return['status'] = 1;
        $return['gold'] = self::memberValue($uid, 'gold');
        $return['soul'] = self::memberValue($uid, 'soul');
        return $return;
    }

    /**
     * 发送战报到聊天室: c=tower&m=send_to_chat（id 关卡id，report 战报id）
     *
     * status：-1 参数不完整；-2 元宝不足 / 关卡不存在；1 成功
     */
    public static function send_to_chat(Context $ctx): array
    {
        // 战报id
        $reportId = (int)$ctx->input('report', 0);
        $id = (int)$ctx->input('id', 0);
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($id === 0 || $reportId === 0 || $member === null) {
            return ['status' => -1];
        }
        // 查询关卡名字
        if (self::getMissionById($id) === null) {
            return ['status' => -2];
        }
        // 依赖未移植：chat_model->get_message_str(5, general / id) + send_message_to_all(…, 5, report) 聊天室战报广播。
        // 旧版在这里先校验元宝(-2)、扣 SHARE_PRICE 再广播；聊天室未移植前广播发不出去，
        // 开发者要求暂不扣元宝，返回 0（失败），客户端提示「暂时无法分享战报」。聊天室移植后恢复旧版流程。
        return ['status' => 0];
    }

    /**
     * 将魂星路战斗（移植 battle_model->battle_soul → battle_mission($mission_info, 2)）
     *
     * 旧版 check_user_team：玩家 -1（没有阵法）/ -3（武将信息错误）时不战斗、status 非 1；
     * -2（阵法里没有武将）按空队伍继续战斗（空队伍直接判 -3 大败）。
     *
     * @param array<string, mixed> $info SoulMission 行
     * @return array{status: int, info: array<string, mixed>}
     */
    private static function battleSoul(int $uid, array $info): array
    {
        $left = TeamBuilder::forMember($uid, pve: true);
        if ($left['status'] === -1 || $left['status'] === -3) {
            return ['status' => $left['status'], 'info' => []];
        }
        $right = TeamBuilder::forMonsters($info, TeamBuilder::MONSTER_SOUL);
        $engine = new BattleEngine($left['matrix_type'], $left['matrix_coe']);
        return [
            'status' => 1,
            'info' => $engine->battle($left['team'], $right['team']),
        ];
    }

    /**
     * 将魂掉落掷点（移植 tower_model->roll）：getrate% 返回 soulid，否则 0
     */
    private static function roll(int $soulid, int $getrate): int
    {
        $return = 0;
        $rule = [0 => 100 - $getrate, $soulid => $getrate];
        $roll = mt_rand(1, 100);
        $cursor = 0;
        for ($i = 0; $i <= $soulid; $i++) {
            if (isset($rule[$i])) {
                $cursor += $rule[$i];
                $return = $i;
            }
            if ($cursor >= $roll) {
                break;
            }
        }
        return $return;
    }

    /**
     * 将魂模板 + 图片 + 0 级属性（tower 控制器里 soul_model->get_base_info + get_levelup_rule(0, type, quality)）
     *
     * @return array<string, mixed>
     */
    private static function heroSoulInfo(int $soulid): array
    {
        $soul = Soul::ROWS[$soulid] ?? null;
        if ($soul === null) {
            // 旧版模板查不到时 get_base_info 返回空数组（随后补的字段无意义），这里返回空
            return [];
        }
        $soul['image'] = (int)($soul['type'] . $soul['quality']);
        // 0 级不会超过任何品质的等级上限；soul_type 查不到时旧版没有 addition（null）
        $row = SoulType::ROWS[$soul['type']] ?? null;
        $addition = null;
        if ($row !== null) {
            $addition = (int)$row['lv0'];
            if ($soul['type'] > 3) {
                $addition .= '%';
            }
        }
        $soul['addition'] = $addition;
        return $soul;
    }

    /**
     * 双倍奖励活动（tower 控制器 _initdouble：activity_model->activity_status('double_tower')）
     */
    private static function isDouble(int $now): bool
    {
        foreach (ActivityTime::ROWS as $row) {
            if ($row['name'] === 'double_tower') {
                return $row['starttime'] <= $now && $row['endtime'] >= $now;
            }
        }
        return false;
    }

    /**
     * 主公等级大于 50 开放（构造函数 _isopen）
     *
     * @param array<string, mixed> $member
     */
    private static function isOpen(array $member): bool
    {
        return (int)$member['level'] > self::OPEN_LEVEL;
    }

    /**
     * 将魂包裹是否已满（构造函数 _isfull：member.soulpack <= member.souluse）
     *
     * 依赖未移植：将魂背包（member.soulpack / souluse、own_soul），按未满处理。
     */
    private static function isFull(): bool
    {
        return false;
    }

    /**
     * 扫荡所需元宝（tower 控制器 _get_price_by_times）
     *
     * @param int $totimes   目标次数
     * @param int $soultimes 今日将魂购买次数
     * @param int $leavetimes 剩余推关次数
     */
    private static function getPriceByTimes(int $totimes, int $soultimes, int $leavetimes): int
    {
        $times = $totimes - $leavetimes;
        if ($times <= 0) {
            return 0;
        }
        return self::priceByTimes($soultimes, $times);
    }

    /**
     * 从第 start + 1 次起连续 times 次的价格之和（移植 tower_model->get_price_by_times：price.act，50 次起按第 50 次）
     */
    private static function priceByTimes(int $start, int $times): int
    {
        if (Price::ROWS === []) {
            return -1;
        }
        $sub = 0;
        for ($i = 0; $i < $times; $i++) {
            $start = $start + 1 > 50 ? 50 : $start + 1;
            $sub += (int)(Price::ROWS[$start]['act'] ?? 0);
        }
        return $sub;
    }

    /**
     * 5 / 10 / 50 / 100 次扫荡价格；任一取价失败返回 null
     *
     * @return array{five_sub: int, ten_sub: int, fifty_sub: int, hundred_sub: int}|null
     */
    private static function mopupPrice(int $soultimes, int $leavetimes): ?array
    {
        $info = [
            'five_sub' => self::getPriceByTimes(5, $soultimes, $leavetimes),
            'ten_sub' => self::getPriceByTimes(10, $soultimes, $leavetimes),
            'fifty_sub' => self::getPriceByTimes(50, $soultimes, $leavetimes),
            'hundred_sub' => self::getPriceByTimes(100, $soultimes, $leavetimes),
        ];
        if ($info['five_sub'] < 0 || $info['ten_sub'] < 0 || $info['fifty_sub'] < 0 || $info['hundred_sub'] < 0) {
            return null;
        }
        return $info;
    }

    /**
     * 第 n 次购买的价格（移植 shop_model->get_act_price：50 次起固定 50 元宝）
     */
    private static function actPrice(int $num): int
    {
        if ($num >= 50) {
            return 50;
        }
        return (int)(Price::ROWS[$num]['act'] ?? 0);
    }

    /**
     * 场景列表（移植 tower_model->get_scene_list：LIMIT (level-1)*7, level*7，第二个参数是条数，照旧版）
     *
     * @return list<array<string, mixed>>
     */
    private static function getSceneList(int $level, int $step = self::MAX_STEP): array
    {
        $start = ($level - 1) * $step > 0 ? ($level - 1) * $step : 0;
        $end = $level * $step;
        return array_slice(array_values(SoulScene::ROWS), $start, max(0, $end));
    }

    /**
     * 按 id 取场景（移植 tower_model->get_scene_by_id：id 超过最大场景时取最大场景）
     *
     * @return array<string, mixed>|null
     */
    private static function getSceneById(int $id): ?array
    {
        if ($id >= self::MAX_STEP) {
            $id = self::MAX_STEP;
        }
        return SoulScene::ROWS[$id] ?? null;
    }

    /**
     * 已开启的最大场景 id（移植 tower_model->get_open_scene）
     */
    private static function getOpenScene(int $stage): int
    {
        if ($stage > self::MAX_STEP - 1) {
            return self::MAX_STEP;
        }
        foreach (SoulScene::ROWS as $row) {
            if ((int)$row['openmethod'] === 1 && (int)$row['opencondition'] === $stage) {
                return (int)$row['id'];
            }
        }
        return 0;
    }

    /**
     * 场景内的关卡（移植 tower_model->get_mission_list）
     *
     * @return list<array<string, mixed>>
     */
    private static function getMissionList(int $stage): array
    {
        $return = [];
        foreach (SoulMission::ROWS as $row) {
            if ((int)$row['stage'] === $stage) {
                $return[] = $row;
            }
        }
        return $return;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function getMissionById(int $id): ?array
    {
        return SoulMission::ROWS[$id] ?? null;
    }

    /**
     * 场景最后一个关卡的 id（移植 tower_model->get_last_mission）
     */
    private static function getLastMission(int $stage): int
    {
        $ids = array_column(self::getMissionList($stage), 'id');
        return $ids === [] ? 0 : (int)max($ids);
    }

    /**
     * 已开启地图（移植 tower_model->get_open_map：没有记录时插入 level 1 / stage 0 / mission 0）
     *
     * @return array{id: int, uid: int, level: int, stage: int, mission: int, dateline: int}|null
     */
    private static function getOpenMap(int $uid, int $now): ?array
    {
        $row = self::openMapRow($uid);
        if ($row === null) {
            DB::execute(
                'INSERT IGNORE INTO `zc_sanguo_soul_open_map` (`uid`, `level`, `stage`, `mission`, `dateline`) VALUES (:uid, 1, 0, 0, :now)',
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
        $row = DB::table('soul_open_map')->select(...self::MAP_COLUMNS)->where('uid', $uid)->whereNull('deleted_at')->first();
        return $row === null ? null : array_map('intval', $row);
    }

    /**
     * @param array<string, int> $data
     */
    private static function updateOpenMap(int $uid, array $data): void
    {
        DB::table('soul_open_map')->where('uid', $uid)->whereNull('deleted_at')->update(array_map('intval', $data));
    }

    /**
     * 玩家活动信息（移植 member_model->get_activity_info：没有记录时按旧版初始值插入）
     *
     * @return array{uid: int, towerlevel: int, towerstage: int, towermission: int, freetower: int, towertimes: int, souldate: int}|null
     */
    private static function getActivityInfo(int $uid): ?array
    {
        $row = self::activityRow($uid);
        if ($row === null) {
            MemberActivity::ensure($uid);
            $row = self::activityRow($uid);
        }
        return $row;
    }

    /**
     * @return array{uid: int, towerlevel: int, towerstage: int, towermission: int, freetower: int, towertimes: int, souldate: int}|null
     */
    private static function activityRow(int $uid): ?array
    {
        $row = DB::table('member_activity')->select(...self::ACTIVITY_COLUMNS)->where('uid', $uid)->whereNull('deleted_at')->first();
        return $row === null ? null : array_map('intval', $row);
    }

    /**
     * 更新活动记录（移植 member_model->update_activity_info；没有记录时不插入，同旧版）
     *
     * @param array<string, int> $data
     */
    private static function updateActivityInfo(int $uid, array $data): void
    {
        DB::table('member_activity')->where('uid', $uid)->whereNull('deleted_at')->update(array_map('intval', $data));
    }

    /**
     * 今日将魂星路购买次数（member_field.soultimes）
     */
    private static function soultimes(int $uid): int
    {
        return (int)(DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->value('soultimes') ?? 0);
    }

    /**
     * 更新用户扩展信息（移植 member_model->update_field_info）
     *
     * @param array<string, int> $data
     */
    private static function updateFieldInfo(int $uid, array $data): void
    {
        DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->update(array_map('intval', $data));
    }

    /**
     * 玩家某关的首杀记录（移植 tower_model->get_mission_log_by_uid($uid, $id)）
     *
     * @return array<string, mixed>|null
     */
    private static function getMissionLog(int $uid, int $missionid): ?array
    {
        $row = DB::table('soul_log')->select(...self::LOG_COLUMNS)
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
        $rows = DB::table('soul_log')->select(...self::LOG_COLUMNS)->where('uid', $uid)->whereNull('deleted_at')->orderBy('id')->get();
        foreach ($rows as $row) {
            $row = self::castLog($row);
            $return[$row['missionid']] = $row;
        }
        return $return;
    }

    /**
     * 关卡攻略列表：最近 7 条首杀记录，按记录 id 索引（移植 tower_model->get_mission_log）
     *
     * @return array<int, array{id: int, uid: int, level: int, nickname: string, tree: int}>
     */
    private static function getMissionLogList(int $missionid): array
    {
        $return = [];
        $rows = DB::table('soul_log')->select('id', 'uid', 'level', 'nickname', 'tree')
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
     * 添加首杀记录（移植 tower_model->add_mission_log），返回记录 id（即战报 id）
     *
     * @param array<string, mixed> $member
     */
    private static function addMissionLog(array $member, int $level, int $stage, int $missionid, int $tree, int $now): int
    {
        return (int)DB::table('soul_log')->insertGetId([
            'uid' => (int)$member['uid'],
            'nickname' => (string)$member['nickname'],
            'missionlevel' => $level,
            'missionstage' => $stage,
            'missionid' => $missionid,
            'dateline' => $now,
            'level' => (int)$member['level'],
            'tree' => $tree,
            'first_reward' => 1,
        ]);
    }

    /**
     * @param array<string, int> $data
     */
    private static function updateMissionLog(int $uid, array $data, int $missionid): void
    {
        DB::table('soul_log')->where('uid', $uid)->where('missionid', $missionid)->whereNull('deleted_at')->update($data);
    }

    /**
     * 战报是否已存在（移植 tower_model->check_report）
     */
    private static function checkReport(int $id): bool
    {
        return DB::table('soul_report')->where('id', $id)->whereNull('deleted_at')->exists();
    }

    /**
     * 添加战报（移植 tower_model->add_mission_report：report 列存 json_encode(base64(gzcompress(战报 json)))）
     *
     * @param array<string, mixed> $mission
     * @param array<string, mixed> $member
     */
    private static function addMissionReport(int $id, string $report, array $mission, array $member): void
    {
        DB::table('soul_report')->insert([
            'id' => $id,
            'report' => (string)json_encode($report),
            'nickname' => (string)$member['nickname'],
            'userlevel' => (int)$member['level'],
            'name' => $mission['name'],
            'level' => $mission['level'],
            'background' => $mission['background'],
        ]);
    }

    /**
     * 按 id 取战报（移植 tower_model->get_report_by_id）：status 1 成功，-1 不存在
     *
     * @return array{status: int, info?: array<string, mixed>}
     */
    private static function getReportById(int $id): array
    {
        $result = DB::table('soul_report')->select('id', 'report', 'nickname', 'userlevel', 'name', 'level', 'background')
            ->where('id', $id)->whereNull('deleted_at')->first();
        if ($result === null) {
            return ['status' => -1];
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
            'status' => 1,
            'info' => [
                'id' => (int)$result['id'],
                'nickname' => (string)$result['nickname'],
                'userlevel' => (int)$result['userlevel'],
                'name' => (string)$result['name'],
                'level' => (int)$result['level'],
                'background' => (int)$result['background'],
                'report' => $report,
            ],
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

    /**
     * 请求结束时主公的某个数值（旧版 $this->member 在 sub_gold / add_soul 后同步更新）
     */
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
