<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Campaign;

use Hanjiang\Battle\BattleEngine;
use Hanjiang\Battle\TeamBuilder;
use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\MasterData\ActivityTime;
use Hanjiang\MasterData\CopiesBuyTimesGold;
use Hanjiang\MasterData\CopiesInfo;
use Hanjiang\MasterData\CopiesReward;
use Hanjiang\MasterData\CopiesVipBuyTimes;
use Hanjiang\MasterData\Equip;
use Hanjiang\Model\Chat;
use Hanjiang\Model\Equipment;
use Hanjiang\Model\Member;
use Hanjiang\Model\OwnMaterials;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 平乱副本 / 新副本（移植旧版 controllers/newequip/copies.php + models/newequip/copies_model.php，
 * 路径相对旧版 s1/application/；旧版 CI 以 d=newequip 路由到子目录控制器，新版忽略 d）
 *
 * 配置：副本怪物 CopiesInfo（每行即一只怪，守军为该行 position1~9 指向的 CopiesInfo 行，battle_model->battle_copies 即
 * battle_mission type 9）、奖励 CopiesReward、购买次数价格 CopiesBuyTimesGold、各 VIP 可购买次数 CopiesVipBuyTimes、
 * 锻造材料 MaterialsTemp、装备 Equip、免费次数翻倍活动 ActivityTime（double_copies）。
 * 玩家存储：copies_beat（每只怪一行：是否已击败、今日免费次数、今日剩余可购买次数，跨天在 get_beat_info 里惰性重置）、
 * copies_beat_log（首杀记录，即攻略列表）、copies_report（首杀战报）、own_materials / own_materials_log（锻造材料，Model\OwnMaterials）。
 *
 * 客户端调用（pacifypop.js / miscpop.js / strategypop.js，均带 d=newequip）：index、get_monster_list、get_monster_info、
 * buy_times、pk、mop_up、get_mopup_price、get_report_list、get_report_info、send_to_chat。旧版公开方法即这 10 个。
 *
 * 依赖未移植：
 * - member_model 的流水与附带计数：sub_gold 的 gold_log、日常任务 task_model->task_action(5)、翻牌活动 flopgold；
 *   sub_act 的 act_log、翻牌活动 flopact；add_reputation 的 reputation_log。
 */
final class CopiesHandler
{
    /** 开启等级（旧版 $_open_level；语言包写的是 65 级，代码判断 110 级，照代码） */
    private const int OPEN_LEVEL = 110;

    /** 每次攻打消耗军令 */
    private const int ACT = 1;

    /** 免费攻打次数（copies_model->_free_times，double_copies 活动期间翻倍） */
    private const int FREE_TIMES = 5;

    /** 战报分享到聊天室的元宝花费（旧版 send_to_chat $price） */
    private const int SHARE_PRICE = 2;

    /** 副本场景 / 难度 */
    private const array SCENE_LIST = [1, 2, 3];
    private const array DIFF_LIST = [1, 2, 3];

    /** 扫荡允许的次数 */
    private const array MOPUP_TIMES = [1, 5, 10];

    private const string PK_LOCK = 'copies_pk_lock';
    private const string MOP_UP_LOCK = '_mop_up_lock';
    private const string BUY_FREE_TIMES_LOCK = 'copies_buy_free_times_lock';

    /** 旧版 zc_sanguo_copies_beat 的字段 */
    private const array BEAT_COLUMNS = ['id', 'uid', 'vip', 'copies_id', 'diff_id', 'monster_id', 'status', 'free_times', 'vip_times', 'dateline'];

    /** 旧版 zc_sanguo_copies_beat_log 的字段 */
    private const array LOG_COLUMNS = ['id', 'uid', 'nickname', 'level', 'copies_id', 'diff_id', 'monster_id', 'status', 'dateline'];

    // 旧版语言包 language/chinese_s/newequip/copies_lang.php
    private const string LANG_LEVEL_ERROR = '副本65级开启。';
    private const string LANG_COPIES_DIFFICULTY_ERROR = '获取副本难度信息失败。';
    private const string LANG_PARAM_ERROR = '传参错误。';
    private const string LANG_MONSTER_ERROR = '获取怪物信息失败。';
    private const string LANG_COPIES_INFO_ERROR = '获取过关信息失败。';
    private const string LANG_VIP_TIMES_ERROR = '可购买次数不足。';
    private const string LANG_FAST_ERROR = '操作太快了。';
    private const string LANG_ACT_ERROR = '军令不足。';
    private const string LANG_BEAT_FREE_TIMES_ERROR = '攻击次数不足。';
    private const string LANG_BATTLE_INFO_ERROR = '获取战斗信息失败。';
    private const string LANG_USE_ACT_ERROR = '扣除军令失败';
    private const string LANG_DIFF_ERROR = '当前副本难度尚未解锁';
    private const string LANG_MONSTER_NOT_OPEN = '当前怪物尚未解锁';
    private const array LANG_SCENE = [1 => '天公张角', 2 => '魔王董卓', 3 => '战神吕布'];
    private const string LANG_MOP_UP_TIMES_ERROR = '次数错误';
    private const string LANG_MOP_UP_TIMES_NOT = '次数不足';
    private const string LANG_GOLD_ERROR = '元宝不足';
    /** 副本难度名（diff_id_1 ~ diff_id_3） */
    private const array LANG_DIFF_ID = [1 => '简单', 2 => '普通', 3 => '困难'];
    private const string LANG_REPORT_LIST_ERROR = '获取攻略列表失败';
    private const string LANG_REPORT_ERROR = '攻略不存在';
    private const string LANG_SWEEP_ERROR = '无法扫荡';
    private const string LANG_SMALLER_RATE = '极小概率';
    private const string LANG_SMALL_RATE = '小概率';
    private const string LANG_BIG_RATE = '大概率';
    private const string LANG_BIGGER_RATE = '必得';
    private const string LANG_FIRST_BIGGER_RATE = '首杀必得';

    /**
     * 新副本首页: c=copies&m=index
     */
    public static function index(Context $ctx): array
    {
        $return = ['status' => 0];

        /**
         * 错误信息返回
         * -2 获取场景失败
         * -1 等级不足
         * 0 请求失败
         * 1 请求成功
         */
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        if (!self::isOpen($member)) {
            // 等级不足
            $return['status'] = -1;
            $return['msg'] = self::LANG_LEVEL_ERROR;
            return $return;
        }
        $uid = (int)$member['uid'];

        // 旧版场景列表为空时返回 -1（获取场景失败）；场景列表是常量，不会为空

        // 获取每个副本下的每个难度的怪物信息
        $list = [];
        foreach (self::SCENE_LIST as $key => $val) {
            $list[$key]['id'] = $val;
            $list[$key]['name'] = self::LANG_SCENE[$val];
            // 获取通关信息
            $list[$key]['succ'] = 1; // 默认通关
            foreach (self::DIFF_LIST as $diff) {
                $status = self::lastDiffStatus($uid, $val, $diff);
                if ($status === null || $status === 0) {
                    $list[$key]['succ'] = 0;
                    break;
                }
            }
        }
        $return['status'] = 1;
        $return['list'] = $list;
        return $return;
    }

    /**
     * 怪物列表: c=copies&m=get_monster_list（id 副本id）
     */
    public static function get_monster_list(Context $ctx): array
    {
        $return = ['status' => 0];

        /**
         * 错误信息返回
         * -3 获取副本信息失败
         * -2 参数错误
         * -1 等级不足
         * 0 请求失败
         * 1 请求成功
         */
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        if (!self::isOpen($member)) {
            // 等级不足
            $return['status'] = -1;
            $return['msg'] = self::LANG_LEVEL_ERROR;
            return $return;
        }

        $copiesId = (int)$ctx->input('id', 0);
        if ($copiesId <= 0) {
            // 传参错误
            $return['status'] = -2;
            $return['msg'] = self::LANG_PARAM_ERROR;
            return $return;
        }

        // 获取副本难度信息
        $diffInfo = self::diffInfo($copiesId);
        if ($diffInfo === []) {
            // 获取副本难度信息失败
            $return['status'] = -3;
            $return['msg'] = self::LANG_COPIES_DIFFICULTY_ERROR;
            return $return;
        }

        $diffArray = [];
        $diffArrayInfo = [];
        foreach ($diffInfo as $val) {
            if (!in_array($val['diff_id'], $diffArray, true)) {
                $diffArray[] = $val['diff_id'];
                $diffArrayInfo[]['diff_id'] = $val['diff_id'];
            }
        }

        // 获取当前层所有怪物的攻打信息
        $info = self::allBeatInfo($member, $copiesId, time());
        // 获取当前层所有怪物静态信息
        $newInfo = [];
        $newMonsterInfo = [];
        foreach ($info as $val) {
            $newInfo[$val['diff_id']][] = $val;
        }
        foreach ($diffInfo as $val) {
            $newMonsterInfo[$val['diff_id']][] = $val;
        }

        foreach ($diffArrayInfo as &$val) {
            $val['status'] = 1;
            $val['list'] = [];
            $new = [];
            if ($val['diff_id'] !== 1) {
                // 获取最后一个怪物的状态（旧版用本难度的怪物数去取上一难度的最后一只）
                $count = count($newInfo[$val['diff_id']] ?? []);
                $lastMonsterInfo = $newInfo[$val['diff_id'] - 1][$count - 1] ?? null;
                if ($lastMonsterInfo !== null) {
                    $val['status'] = $lastMonsterInfo['status'];
                } else {
                    $val['status'] = 0;
                }
            }
            $mon = $newMonsterInfo[$val['diff_id']];
            foreach ($mon as $key => $li) {
                $new[$key]['copies_id'] = $li['copies_id'];
                $new[$key]['diff_id'] = $li['diff_id'];
                $new[$key]['monster_id'] = $li['monster_id'];
                $new[$key]['name'] = $li['name'];
                $new[$key]['pass_type'] = $li['pass_type'];
                $new[$key]['killed'] = $newInfo[$val['diff_id']][$key]['status'] ?? null;
                $new[$key]['openstatus'] = $val['status'];
                $new[$key]['x'] = $li['x'];
                $new[$key]['y'] = $li['y'];
                if ($new[$key]['openstatus'] === 1 && $key !== 0 && $new[$key - 1]['killed'] !== 1) {
                    $new[$key]['openstatus'] = 0;
                }
            }
            $val['list'] = $new;
        }
        unset($val);
        $return['status'] = 1;
        $return['list'] = $diffArrayInfo;
        $return['info']['act'] = (int)$member['act'];
        $return['info']['gold'] = (int)$member['gold'];
        return $return;
    }

    /**
     * 获取怪物信息: c=copies&m=get_monster_info（id 副本id，diff_id 副本难度，monster_id 怪物id）
     */
    public static function get_monster_info(Context $ctx): array
    {
        $return = ['status' => 0];

        /**
         * 错误信息返回
         * -5 获取怪物信息失败
         * -4 当前怪物尚未解锁
         * -3 当前副本难度尚未解锁
         * -2 参数错误
         * -1 等级不足
         * 0 请求失败
         * 1 请求成功
         * （旧版注释与代码相反：代码里 -4 是获取怪物信息失败，-5 是当前怪物尚未解锁）
         */
        $now = time();
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        if (!self::isOpen($member)) {
            // 等级不足
            $return['status'] = -1;
            $return['msg'] = self::LANG_LEVEL_ERROR;
            return $return;
        }

        // 副本id / 副本难度 / 怪物id
        [$copiesId, $diffId, $monsterId] = self::params($ctx);
        if ($copiesId <= 0 || $diffId <= 0 || $monsterId <= 0) {
            // 传参错误
            $return['status'] = -2;
            $return['msg'] = self::LANG_PARAM_ERROR;
            return $return;
        }
        $uid = (int)$member['uid'];

        if (!self::diffOpen($uid, $copiesId, $diffId)) {
            // 当前副本难度尚未解锁
            $return['status'] = -3;
            $return['msg'] = self::LANG_DIFF_ERROR;
            return $return;
        }

        $beatInfo = self::beatInfo($member, $copiesId, $diffId, $monsterId, $now);
        if ($beatInfo === []) {
            // 获取怪物信息失败（旧版此处的提示是 diff_error）
            $return['status'] = -4;
            $return['msg'] = self::LANG_DIFF_ERROR;
            return $return;
        }
        // 获取当前怪物是否解锁
        if ($monsterId !== 1) {
            // 判断上个怪物是否攻击成功
            $beatInfoStatus = self::beatInfo($member, $copiesId, $diffId, $monsterId - 1, $now);
            if (($beatInfoStatus['status'] ?? 0) === 0) {
                // 当前怪物尚未解锁
                $return['status'] = -5;
                $return['msg'] = self::LANG_MONSTER_NOT_OPEN;
                return $return;
            }
        }

        // 获取怪物信息
        $info = self::monsterInfo($copiesId, $diffId, $monsterId);

        // 获取奖励信息
        $reward = self::rewardInfo($member, $copiesId, $diffId, $monsterId, 1, $now, 'get_monster_info');

        $price = 0;
        if ($beatInfo['free_times'] <= 0) {
            // 获取可购买次数
            $timesInfo = self::vipGoldTimes((int)$member['vip']);
            // 获取已经购买次数
            $nextTime = ($timesInfo['times'] ?? 0) - $beatInfo['vip_times'];
            // 获取下一次可购买次数价格
            $goldInfo = self::goldByTimes($nextTime + 1);
            $price = $goldInfo['gold'] ?? null;
        }

        $return['status'] = 1;
        $return['info'] = $reward;
        $return['info']['free_times'] = $beatInfo['free_times'];
        $return['info']['vip_times'] = $beatInfo['vip_times'];
        $return['info']['name'] = $info['name'] ?? null;
        $return['info']['image'] = $info['image'] ?? null;
        $return['info']['monster_level'] = $info['level'] ?? null;
        $return['info']['pass_type'] = $info['pass_type'] ?? null;
        $return['info']['price'] = $price;
        return $return;
    }

    /**
     * 战斗: c=copies&m=pk（id 副本id，diff_id 副本难度，monster_id 怪物id）
     */
    public static function pk(Context $ctx): array
    {
        $return = ['status' => 0];

        /**
         * 错误信息返回
         * -7 军令不足
         * -6 攻击次数不足
         * -5 当前怪物尚未解锁
         * -4 获取怪物信息失败
         * -3 当前副本难度尚未解锁
         * -2 参数错误
         * -1 等级不足
         * 0 请求失败
         * 1 请求成功
         * （旧版注释未列出：-8 操作太快了 / 扣除军令失败，-7 获取战斗信息失败，-6 怪物配置不存在）
         */
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        $uid = (int)$member['uid'];
        $lockKey = self::PK_LOCK . $uid;
        if (Redis::get($lockKey) !== null) {
            // 操作太快了
            $return['status'] = -8;
            $return['msg'] = self::LANG_FAST_ERROR;
            return $return;
        }
        // 加锁（旧版每个返回前解锁）
        Redis::set($lockKey, 1, 30);
        try {
            return self::doPk($ctx, $member);
        } finally {
            Redis::del($lockKey);
        }
    }

    /**
     * 获取扫荡价格: c=copies&m=get_mopup_price（id 副本id，diff_id 副本难度，monster_id 怪物id）
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
         * （旧版注释照抄将魂星路；代码里 -2 是参数错误，-3 是获取怪物信息失败）
         */
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        if (!self::isOpen($member)) {
            // 等级不足
            $return['status'] = -1;
            $return['msg'] = self::LANG_LEVEL_ERROR;
            return $return;
        }

        [$copiesId, $diffId, $monsterId] = self::params($ctx);
        if ($copiesId <= 0 || $diffId <= 0 || $monsterId <= 0) {
            // 传参错误
            $return['status'] = -2;
            $return['msg'] = self::LANG_PARAM_ERROR;
            return $return;
        }

        // 获取怪物信息
        if (self::monsterInfo($copiesId, $diffId, $monsterId) === null) {
            // 获取怪物信息失败
            $return['status'] = -3;
            $return['msg'] = self::LANG_MONSTER_ERROR;
            return $return;
        }

        // 获取怪物当前状态
        $monsterStatusInfo = self::monsterStatus((int)$member['uid'], $copiesId, $diffId, $monsterId);
        if ($monsterStatusInfo['status'] === 0) {
            // 无法扫荡
            $return['status'] = -5;
            $return['msg'] = self::LANG_SWEEP_ERROR;
            return $return;
        }

        // 当前剩余次数
        $freeTimes = $monsterStatusInfo['free_times'];
        // 查看可购买次数
        $vipTimes = self::vipGoldTimes((int)$member['vip']);
        $buyTimes = ($vipTimes['times'] ?? 0) - $monsterStatusInfo['vip_times'];
        $info = [];
        $info['one_sub'] = self::getPriceByTimes(1, $buyTimes, $freeTimes);
        $info['five_sub'] = self::getPriceByTimes(5, $buyTimes, $freeTimes);
        $info['ten_sub'] = self::getPriceByTimes(10, $buyTimes, $freeTimes);
        if ($info['one_sub'] < 0 || $info['five_sub'] < 0 || $info['ten_sub'] < 0) {
            // 获取价格失败
            $return['status'] = -7;
            return $return;
        }

        $return['status'] = 1;
        $return['info'] = $info;
        $return['vip_times'] = $monsterStatusInfo['vip_times'];
        return $return;
    }

    /**
     * 扫荡: c=copies&m=mop_up（id 副本id，diff_id 副本难度，monster_id 怪物id，times 扫荡次数）
     */
    public static function mop_up(Context $ctx): array
    {
        /**
         * -11 军令不足
         * -10 获取元宝消耗失败
         * -9 当前怪物尚未解锁
         * -8 获取怪物信息失败
         * -7 当前副本难度尚未解锁
         * -6 攻击次数不足
         * -5 首杀不能扫荡
         * -4 次数不允许
         * -3 参数错误
         * -2 操作太快了
         * -1 等级不足
         * 1 成功
         * 0 失败
         * （旧版代码里 -10 是元宝不足；-6 没有用到）
         */
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        if (!self::isOpen($member)) {
            // 等级不足
            $return['status'] = -1;
            $return['msg'] = self::LANG_LEVEL_ERROR;
            return $return;
        }

        $lockKey = self::MOP_UP_LOCK . (int)$member['uid'];
        if (Redis::get($lockKey) !== null) {
            $return['status'] = -2;
            $return['msg'] = self::LANG_FAST_ERROR;
            return $return;
        }
        // 加锁（旧版每个返回前解锁）
        Redis::set($lockKey, 1, 30);
        try {
            return self::doMopUp($ctx, $member);
        } finally {
            Redis::del($lockKey);
        }
    }

    /**
     * 获取攻略列表: c=copies&m=get_report_list（id 副本id，diff_id 副本难度，monster_id 怪物id）
     */
    public static function get_report_list(Context $ctx): array
    {
        /**
         * -2 参数错误
         * -1 等级不足
         *  0 失败
         *  1 成功
         *  2 攻略不存在
         */
        $return = ['status' => 0, 'list' => []];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        if (!self::isOpen($member)) {
            // 等级不足
            $return['status'] = -1;
            $return['msg'] = self::LANG_LEVEL_ERROR;
            return $return;
        }
        [$copiesId, $diffId, $monsterId] = self::params($ctx);
        if ($copiesId <= 0 || $diffId <= 0 || $monsterId <= 0) {
            // 传参错误
            $return['status'] = -2;
            $return['msg'] = self::LANG_PARAM_ERROR;
            return $return;
        }

        $return['status'] = 1;
        $return['list'] = self::pkLog($copiesId, $diffId, $monsterId);
        return $return;
    }

    /**
     * 查看战报: c=copies&m=get_report_info（id 战报id）
     */
    public static function get_report_info(Context $ctx): array
    {
        /**
         * -3 功略不存在
         * -2 获取攻略列表失败
         * -1 等级不足
         *  0 失败
         *  1 成功
         *  2 攻略不存在
         */
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        if (!self::isOpen($member)) {
            // 等级不足
            $return['status'] = -1;
            $return['msg'] = self::LANG_LEVEL_ERROR;
            return $return;
        }
        $id = (int)$ctx->input('id', 0);
        $result = self::reportById($id);
        if ($result['status'] !== 1) {
            if ($result['status'] === -1) {
                // 攻略不存在 返回-3
                $return['msg'] = self::LANG_REPORT_ERROR;
                $return['status'] = -3;
            } else {
                // 获取攻略列表失败
                $return['msg'] = self::LANG_REPORT_LIST_ERROR;
                $return['status'] = -2;
            }
            return $return;
        }
        $return['status'] = 1;
        $return['battle'] = $result['report'];
        $return['battle']['left_role'] = [
            'name' => $result['info']['nickname'],
            'level' => $result['info']['userlevel'],
        ];
        $return['battle']['right_role'] = [
            'name' => $result['info']['name'],
            'level' => $result['info']['level'],
        ];
        $return['info']['win'] = $result['report']['assess'] ?? null;
        return $return;
    }

    /**
     * 全服分享战报: c=copies&m=send_to_chat（report 战报id，id 副本id，diff_id 副本难度，monster_id 怪物id）
     */
    public static function send_to_chat(Context $ctx): array
    {
        /**
         * -4 金币不足
         * -3 获取怪物信息失败
         * -2 参数错误
         * -1 等级不足
         * 0 失败
         * 1成功
         */
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        if (!self::isOpen($member)) {
            // 等级不足
            $return['status'] = -1;
            $return['msg'] = self::LANG_LEVEL_ERROR;
            return $return;
        }
        $report = $ctx->input('report');
        $id = $ctx->input('id');
        $diffId = $ctx->input('diff_id');
        $monsterId = $ctx->input('monster_id');
        // 判断参数是否完整
        if (!$id || !$report || !$diffId || !$monsterId) {
            $return['status'] = -2;
            $return['msg'] = self::LANG_PARAM_ERROR;
            return $return;
        }

        // 获取怪物信息
        $monsterInfo = self::monsterInfo((int)$id, (int)$diffId, (int)$monsterId);
        if ($monsterInfo === null) {
            $return['status'] = -3;
            $return['msg'] = self::LANG_MONSTER_ERROR;
            return $return;
        }

        // 判断金币是否足够 / 扣除金币
        $allGold = (int)$member['gold'] < self::SHARE_PRICE ? null : Chat::chargeShare((int)$member['uid'], self::SHARE_PRICE);
        if ($allGold === null) {
            $return['status'] = -4;
            $return['msg'] = self::LANG_GOLD_ERROR;
            return $return;
        }
        $sender = Chat::sender((int)$member['uid']);
        $message = Chat::getMessageStr(53, [
            'id' => $report . '|' . $id,
            'monster_info' => $monsterInfo['name'] . '(' . (self::LANG_DIFF_ID[(int)$diffId] ?? '') . ')',
        ], $sender);
        // 发送战报
        Chat::sendToAll($message, 53, (string)$report, 0, $sender);
        $return['status'] = 1;
        $return['all_gold'] = $allGold;
        return $return;
    }

    /**
     * 购买攻打次数: c=copies&m=buy_times（id 副本id，diff_id 副本难度，monster_id 怪物id）
     */
    public static function buy_times(Context $ctx): array
    {
        $return = ['status' => 0];

        /**
         * 错误信息返回
         * -6 操作太快了
         * -5 元宝不足
         * -4 购买次数不足
         * -3 获取用户攻打副本基础信息失败
         * -2 参数错误
         * -1 等级不足
         * 0 请求失败
         * 1 请求成功
         * （旧版代码里操作太快了返回 2）
         */
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        // 防刷接口处理
        $lockKey = self::BUY_FREE_TIMES_LOCK . (int)$member['uid'];
        if (Redis::get($lockKey) !== null) {
            $return['status'] = 2;
            $return['msg'] = self::LANG_FAST_ERROR;
            return $return;
        }
        // 加锁（旧版每个返回前解锁）
        Redis::set($lockKey, 1, 30);
        try {
            return self::doBuyTimes($ctx, $member);
        } finally {
            Redis::del($lockKey);
        }
    }

    /**
     * pk 加锁后的流程
     *
     * @param array<string, mixed> $member
     */
    private static function doPk(Context $ctx, array $member): array
    {
        $now = time();
        $return = ['status' => 0];
        if (!self::isOpen($member)) {
            // 等级不足
            $return['status'] = -1;
            $return['msg'] = self::LANG_LEVEL_ERROR;
            return $return;
        }

        [$copiesId, $diffId, $monsterId] = self::params($ctx);
        if ($copiesId <= 0 || $diffId <= 0 || $monsterId <= 0) {
            // 传参错误
            $return['status'] = -2;
            $return['msg'] = self::LANG_PARAM_ERROR;
            return $return;
        }
        $uid = (int)$member['uid'];

        if (!self::diffOpen($uid, $copiesId, $diffId)) {
            // 当前副本难度尚未解锁
            $return['status'] = -3;
            $return['msg'] = self::LANG_DIFF_ERROR;
            return $return;
        }

        $beatInfo = self::beatInfo($member, $copiesId, $diffId, $monsterId, $now);
        if ($beatInfo === []) {
            // 获取怪物信息失败
            $return['status'] = -4;
            $return['msg'] = self::LANG_MONSTER_ERROR;
            return $return;
        }
        // 获取当前怪物是否解锁
        if ($monsterId !== 1) {
            // 判断上个怪物是否攻击成功
            $beatInfoUp = self::beatInfo($member, $copiesId, $diffId, $monsterId - 1, $now);
            if (($beatInfoUp['status'] ?? 0) === 0) {
                // 当前怪物尚未解锁
                $return['status'] = -5;
                $return['msg'] = self::LANG_MONSTER_NOT_OPEN;
                return $return;
            }
        }

        // 获取怪物信息
        $monsterInfo = self::monsterInfo($copiesId, $diffId, $monsterId);
        if ($monsterInfo === null) {
            // 获取怪物信息失败
            $return['status'] = -6;
            $return['msg'] = self::LANG_MONSTER_ERROR;
            return $return;
        }

        // 判断攻击次数
        $beatInfo = self::beatInfo($member, $copiesId, $diffId, $monsterId, $now);
        if ($beatInfo['free_times'] <= 0) {
            // 攻击次数不足
            $return['status'] = -6;
            $return['msg'] = self::LANG_BEAT_FREE_TIMES_ERROR;
            return $return;
        }

        // 判断军令
        if ((int)$member['act'] < self::ACT) {
            // 军令不足
            $return['status'] = -7;
            $return['msg'] = self::LANG_ACT_ERROR;
            return $return;
        }

        // [战斗模型]战斗（battle_model->battle_copies：怪物的 position1~9）
        $battle = self::battleCopies($uid, $monsterInfo);
        if ($battle['status'] !== 1) {
            // 获取战斗信息失败
            $return['status'] = -7;
            $return['msg'] = self::LANG_BATTLE_INFO_ERROR;
            return $return;
        }

        // 扣军令
        if (!Member::deductCurrency($uid, act: self::ACT)) {
            // 扣除军令失败
            $return['status'] = -8;
            $return['msg'] = self::LANG_USE_ACT_ERROR;
            return $return;
        }
        $member['act'] = (int)$member['act'] - self::ACT;

        // 奖励信息返回
        $reward = [];
        $reportId = 0;
        // 胜利
        if ($battle['info']['assess'] > 0) {
            // 判断当前副本当前难度是否首次通关
            $lastInfo = self::monsterList($copiesId, $diffId);
            if ($beatInfo['status'] === 0 && $monsterId === count($lastInfo)) {
                $return['first_succ'] = 1;
            }

            // 扣除免费次数
            self::changeFreeTimes($member, $copiesId, $diffId, $monsterId, 1, 0, 0, $now);
            // 修改攻击成功状态 pk时间
            self::updateMonsterStatus($uid, $copiesId, $diffId, $monsterId, 1, $now);

            // 发奖
            $reward = self::rewardInfo($member, $copiesId, $diffId, $monsterId, 0, $now, 'pk');
            if (($reward['first_kill'] ?? 0) === 1) {
                // 添加攻击日志
                $reportId = self::addBeatLog($member, $copiesId, $diffId, $monsterId, (int)$battle['info']['assess'], $now);
                if ($reportId > 0) {
                    $gzipreport = base64_encode((string)gzcompress((string)json_encode($battle['info'])));
                    self::addReportLog($reportId, $gzipreport, $monsterInfo, $member);
                }
            }
        }

        $return['status'] = 1;
        $return['battle'] = $battle['info'];
        $return['battle']['left_role'] = [
            'name' => (string)$member['nickname'],
            'level' => (int)$member['level'],
            'gender' => (int)$member['gender'],
        ];
        $return['battle']['right_role'] = [
            'name' => $monsterInfo['name'],
            'level' => $monsterInfo['level'],
        ];

        $return['reward_info'] = $reward;
        $return['info']['win'] = $battle['info']['assess'];
        $return['info']['act'] = (int)$member['act'];
        $return['info']['gold'] = (int)$member['gold'];
        $return['info']['report'] = $reportId;
        return $return;
    }

    /**
     * mop_up 加锁后的流程
     *
     * @param array<string, mixed> $member
     */
    private static function doMopUp(Context $ctx, array $member): array
    {
        $now = time();
        $return = ['status' => 0];
        $times = (int)$ctx->input('times', 0);
        [$copiesId, $diffId, $monsterId] = self::params($ctx);
        if ($copiesId <= 0 || $diffId <= 0 || $monsterId <= 0) {
            // 传参错误
            $return['status'] = -3;
            $return['msg'] = self::LANG_PARAM_ERROR;
            return $return;
        }

        if (!in_array($times, self::MOPUP_TIMES, true)) {
            // 次数不允许
            $return['status'] = -4;
            $return['msg'] = self::LANG_MOP_UP_TIMES_ERROR;
            return $return;
        }
        $uid = (int)$member['uid'];

        $monsterStatusInfo = self::monsterStatus($uid, $copiesId, $diffId, $monsterId);
        // 首杀不能扫荡
        if ($monsterStatusInfo['status'] === 0) {
            // 无法扫荡
            $return['status'] = -5;
            $return['msg'] = self::LANG_SWEEP_ERROR;
            return $return;
        }

        if (!self::diffOpen($uid, $copiesId, $diffId)) {
            // 当前副本难度尚未解锁
            $return['status'] = -7;
            $return['msg'] = self::LANG_DIFF_ERROR;
            return $return;
        }

        $beatInfo = self::beatInfo($member, $copiesId, $diffId, $monsterId, $now);
        if ($beatInfo === []) {
            // 获取怪物信息失败
            $return['status'] = -8;
            $return['msg'] = self::LANG_MONSTER_ERROR;
            return $return;
        }
        // 获取当前怪物是否解锁（旧版这里判断的是当前怪物自己的状态）
        if ($monsterId !== 1 && $beatInfo['status'] === 0) {
            // 当前怪物尚未解锁
            $return['status'] = -9;
            $return['msg'] = self::LANG_MONSTER_NOT_OPEN;
            return $return;
        }

        // 判断军令
        $needAct = self::ACT * $times;
        if ((int)$member['act'] < $needAct) {
            // 军令不足
            $return['status'] = -11;
            $return['msg'] = self::LANG_ACT_ERROR;
            return $return;
        }

        $newTime = 0;
        // 当前剩余次数
        $freeTimes = $beatInfo['free_times'];
        $vipTimes = (int)(self::vipGoldTimes((int)$member['vip'])['times'] ?? 0);
        // 免费次数不足 扣元宝
        if ($freeTimes < $times) {
            $buyTimes = $vipTimes - $beatInfo['vip_times'];
            $gold = self::getPriceByTimes($times, $buyTimes, $freeTimes);
            if ((int)$member['gold'] < $gold) {
                // 元宝不足
                $return['status'] = -10;
                $return['msg'] = self::LANG_GOLD_ERROR;
                return $return;
            }

            // 购买次数不足（旧版拿扫荡次数与该怪物未重置前的剩余可购买次数比较）
            if ($times > $monsterStatusInfo['vip_times']) {
                // 次数不允许
                $return['status'] = -4;
                $return['msg'] = self::LANG_MOP_UP_TIMES_NOT;
                return $return;
            }
            // 减元宝
            if (Member::deductCurrency($uid, gold: $gold)) {
                $member['gold'] = (int)$member['gold'] - $gold;
            }
            // 减购买次数
            $newTime = $times - $freeTimes;
            self::changeFreeTimes($member, $copiesId, $diffId, $monsterId, 0, $newTime, 0, $now);
        }

        // 扣除攻打次数
        if ($freeTimes > 0) {
            self::changeFreeTimes($member, $copiesId, $diffId, $monsterId, $times, 0, 0, $now);
        }
        // 扣除军令
        if (Member::deductCurrency($uid, act: $needAct)) {
            $member['act'] = (int)$member['act'] - $needAct;
        }

        $reputation = 0;
        $reward = [];
        for ($i = 0; $i < $times; $i++) {
            // 发奖
            $rewardRe = self::rewardInfo($member, $copiesId, $diffId, $monsterId, 0, $now, 'mop_up');
            $reputation += (int)$rewardRe['reputation'];
            foreach ($rewardRe['reward'] as $val) {
                $reward[] = $val;
            }
        }

        foreach ($reward as &$val) {
            if ($val['type'] === 1 || $val['type'] === 2) {
                $val['type_1'] = $val['id'] ?? null;
            } elseif ($val['type'] === 3) {
                $val['type_1'] = $val['materials_id'] ?? null;
            }
        }
        unset($val);

        // 相同奖励累加（按 类型 . 物品id 拼接的键）
        $tmpArray = [];
        foreach ($reward as $row) {
            $key = $row['type'] . ($row['type_1'] ?? '');
            if (array_key_exists($key, $tmpArray)) {
                $tmpArray[$key]['value'] += $row['value'];
            } else {
                $tmpArray[$key] = $row;
            }
        }
        $newtmp = array_values($tmpArray);

        // 修改pk时间
        self::updateMonsterStatus($uid, $copiesId, $diffId, $monsterId, 1, $now);
        $buyTimesInfo = $vipTimes - ($beatInfo['vip_times'] - $newTime);
        $freeTimesInfo = $beatInfo['free_times'] - $times;
        // 返回剩余免费次数
        if ($freeTimesInfo < 0) {
            $freeTimesInfo = 0;
        }
        $afterVipTimes = $beatInfo['vip_times'] - $newTime;
        if ($afterVipTimes < 0) {
            $afterVipTimes = 0;
        }
        // 扫荡需要价格
        $info = [];
        $info['one_sub'] = self::getPriceByTimes(1, $buyTimesInfo, $freeTimesInfo);
        $info['five_sub'] = self::getPriceByTimes(5, $buyTimesInfo, $freeTimesInfo);
        $info['ten_sub'] = self::getPriceByTimes(10, $buyTimesInfo, $freeTimesInfo);

        $return['status'] = 1;
        $return['reputation'] = $reputation;
        $return['reward'] = $newtmp;
        $return['info'] = $info;
        $return['info']['act'] = (int)$member['act'];
        $return['info']['gold'] = (int)$member['gold'];
        $return['info']['vip_times'] = $afterVipTimes;
        $return['info']['free_times'] = $freeTimesInfo;
        return $return;
    }

    /**
     * buy_times 加锁后的流程
     *
     * @param array<string, mixed> $member
     */
    private static function doBuyTimes(Context $ctx, array $member): array
    {
        $now = time();
        $return = ['status' => 0];
        if (!self::isOpen($member)) {
            // 等级不足
            $return['status'] = -1;
            $return['msg'] = self::LANG_LEVEL_ERROR;
            return $return;
        }

        [$copiesId, $diffId, $monsterId] = self::params($ctx);
        if ($copiesId <= 0 || $monsterId <= 0 || $diffId <= 0) {
            // 传参错误
            $return['status'] = -2;
            $return['msg'] = self::LANG_PARAM_ERROR;
            return $return;
        }

        // 查询信息
        $beatInfo = self::beatInfo($member, $copiesId, $diffId, $monsterId, $now);
        if ($beatInfo === []) {
            // 获取用户攻打副本基础信息失败
            $return['status'] = -3;
            $return['msg'] = self::LANG_COPIES_INFO_ERROR;
            return $return;
        }

        // 查看当前vip可购买次数
        $times = (int)(self::vipGoldTimes((int)$member['vip'])['times'] ?? 0);
        if ($times <= 0) {
            // 可购买次数不足
            $return['status'] = -4;
            $return['msg'] = self::LANG_VIP_TIMES_ERROR;
            return $return;
        }

        if (($times - $beatInfo['vip_times']) >= $times) {
            // 可购买次数不足
            $return['status'] = -4;
            $return['msg'] = self::LANG_VIP_TIMES_ERROR;
            return $return;
        }

        // 取购买攻打次数价格
        $gold = (int)(self::goldByTimes($times - $beatInfo['vip_times'] + 1)['gold'] ?? 0);
        if ((int)$member['gold'] < $gold) {
            // 元宝不足
            $return['status'] = -5;
            $return['msg'] = self::LANG_GOLD_ERROR;
            return $return;
        }

        // 减元宝 加攻击次数 减购买次数
        if (Member::deductCurrency((int)$member['uid'], gold: $gold)) {
            $member['gold'] = (int)$member['gold'] - $gold;
        }
        self::changeFreeTimes($member, $copiesId, $diffId, $monsterId, 1, 1, 1, $now);

        // 返回下一次购买攻击次数元宝
        $goldInfo = self::goldByTimes($times - $beatInfo['vip_times'] + 2);
        // 返回当前攻击次数
        $freeTimes = $beatInfo['free_times'] + 1;
        $vipTimes = $beatInfo['vip_times'] - 1;

        $return['status'] = 1;
        $return['price'] = $goldInfo['gold'] ?? null;
        $return['free_times'] = $freeTimes;
        $return['vip_times'] = $vipTimes;
        $return['gold'] = (int)$member['gold'];
        return $return;
    }

    /**
     * 新副本战斗（移植 battle_model->battle_copies → battle_mission($mission_info, 9)）
     *
     * 旧版 check_user_team：玩家 -1（没有阵法）/ -3（武将信息错误）时不战斗、status 非 1；
     * -2（阵法里没有武将）按空队伍继续战斗（空队伍直接判 -3 大败）。
     *
     * @param array<string, mixed> $monsterInfo CopiesInfo 行
     * @return array{status: int, info: array<string, mixed>}
     */
    private static function battleCopies(int $uid, array $monsterInfo): array
    {
        $left = TeamBuilder::forMember($uid, pve: true);
        if ($left['status'] === -1 || $left['status'] === -3) {
            return ['status' => $left['status'], 'info' => []];
        }
        $right = TeamBuilder::forMonsters($monsterInfo, TeamBuilder::MONSTER_COPIES);
        $engine = new BattleEngine($left['matrix_type'], $left['matrix_coe']);
        return [
            'status' => 1,
            'info' => $engine->battle($left['team'], $right['team']),
        ];
    }

    /**
     * 获取奖励信息 / 发奖（控制器 get_reward_info）
     *
     * @param array<string, mixed> $member
     * @param int $type 0 发奖，1 获取奖励信息
     * @param string $method 当前接口（记碎片流水用）
     * @return array<string, mixed>
     */
    private static function rewardInfo(array $member, int $copiesId, int $diffId, int $monsterId, int $type, int $now, string $method): array
    {
        $uid = (int)$member['uid'];
        $return = [];
        $reInfo = [];
        $firstReward = [];
        $firstKill = 1; // 默认是首杀
        $beatLog = self::beatLog($uid, $copiesId, $diffId, $monsterId);
        if ($beatLog !== null && $beatLog['status'] > 0) {
            $firstKill = 0;
        }

        // 获取当前怪物的状态信息
        $beatInfo = self::beatInfo($member, $copiesId, $diffId, $monsterId, $now);
        // 扫荡标识
        $return['mop_up'] = $beatInfo['status'] ?? null;

        // 获取奖励信息
        $reward = self::rewardRow($copiesId, $diffId, $monsterId) ?? [];
        if ($firstKill === 1) {
            // 取首杀声望
            $return['reputation'] = $reward['first_reputation'] ?? null;
            if (($reward['first_type'] ?? 0) > 0) {
                $firstReward = [
                    'type' => $reward['first_type'],
                    'value' => $reward['first_value'],
                    'num' => $reward['first_num'],
                ];
                $return['first_reward'] = self::reward($uid, $firstReward, $type, 1, $now, $method);
                $return['first_reward']['rate_info'] = self::rateInfo(100, 1);
            }
        } else {
            // 取正常声望
            $return['reputation'] = $reward['reputation'] ?? null;
            $return['first_reward'] = $firstReward;
        }

        // 加声望
        if ($type === 0) {
            Member::addCurrency($uid, reputation: (int)$return['reputation']);
        }

        // 取其他奖励信息
        for ($i = 1; $i <= 5; $i++) {
            if (($reward['type' . $i] ?? 0) <= 0) {
                continue;
            }
            $rewardInfo = [
                'type' => $reward['type' . $i],
                'value' => $reward['value' . $i],
                'num' => $reward['num' . $i],
                'rate' => $reward['rate' . $i],
            ];
            if ($type === 0) {
                // 随机发奖（旧版 rand(0, 100)，PHP 7.1 起即 mt_rand）
                $rand = mt_rand(0, 100);
                if ($reward['rate' . $i] >= $rand) {
                    $reInfo[] = self::reward($uid, $rewardInfo, 0, 0, $now, $method);
                }
            } else {
                $reInfo[] = self::reward($uid, $rewardInfo, $type, 0, $now, $method);
            }
        }

        $return['reward'] = $reInfo;
        $return['first_kill'] = $firstKill;
        return $return;
    }

    /**
     * 获取奖励信息或添加玩家奖励（控制器 get_reward）：奖励类型 1 装备，2 装备碎片，3 材料
     *
     * @param array{type: int, value: int, num: int, rate?: float} $rewardInfo
     * @param int $isGet 0 添加奖励，1 获取奖励信息
     * @param int $isFirst 是否首杀奖励（首杀不带概率信息）
     * @return array<string, mixed>
     */
    private static function reward(int $uid, array $rewardInfo, int $isGet, int $isFirst, int $now, string $method): array
    {
        $return = [];
        $type = $rewardInfo['type']; // 奖励类型
        $value = $rewardInfo['value']; // 奖励值
        $num = $rewardInfo['num']; // 奖励数量
        switch ($type) {
            case 1: // 装备
                $item = Equip::ROWS[$value] ?? null;
                if ($item !== null) {
                    if ($isGet === 0) {
                        // 添加装备（旧版 add_item($item['info']) 只加 1 件）
                        Equipment::addFromTemplate($uid, $item['id']);
                    }
                    // 返回装备信息
                    $return = $item;
                }
                break;
            case 2: // 装备碎片
                $item = Equip::ROWS[$value] ?? null;
                if ($item !== null) {
                    if ($isGet === 0) {
                        // 添加装备碎片
                        Equipment::addChip($uid, (int)$item['id'], (int)$num, 'copies', $method, $now);
                    }
                    // 返回装备信息
                    $return = $item;
                }
                break;
            case 3: // 材料
                // 获取材料信息
                $info = OwnMaterials::template($value);
                if ($info !== null) {
                    if ($isGet === 0) {
                        OwnMaterials::change($uid, $info['id'], $info['quality'], $info['name'], $info['chip'], $num, 1, $now);
                    }
                    $return = $info;
                }
                break;
        }
        if ($isFirst === 0) {
            $return['rate_info'] = self::rateInfo((float)($rewardInfo['rate'] ?? 0));
        }
        $return['type'] = $type;
        $return['value'] = $num;
        return $return;
    }

    /**
     * 概率文案（控制器 rate_info）：0~5 极小概率，6~29 小概率，30~99 大概率，100 必得（首杀必得）；其余为空串
     */
    private static function rateInfo(float $rate, int $type = 0): string
    {
        $return = '';
        if ($rate >= 0 && $rate <= 5) {
            // 极小概率
            $return = self::LANG_SMALLER_RATE;
        } elseif ($rate >= 6 && $rate <= 29) {
            // 小概率
            $return = self::LANG_SMALL_RATE;
        } elseif ($rate >= 30 && $rate <= 99) {
            // 大概率
            $return = self::LANG_BIG_RATE;
        } elseif ($rate == 100) {
            // 必得
            $return = self::LANG_BIGGER_RATE;
            if ($type > 0) {
                $return = self::LANG_FIRST_BIGGER_RATE;
            }
        }
        return $return;
    }

    /**
     * 当前难度是否解锁：难度 1 恒解锁，否则看上一难度最后一只怪是否已击败（各接口里重复的 get_last_diff_info 判断）
     */
    private static function diffOpen(int $uid, int $copiesId, int $diffId): bool
    {
        $status = 1;
        if ($diffId !== 1) {
            // 获取当前难度是否解锁
            $status = self::lastDiffStatus($uid, $copiesId, $diffId - 1) ?? 0;
        }
        return $status !== 0;
    }

    /**
     * id / diff_id / monster_id 参数
     *
     * @return array{0: int, 1: int, 2: int}
     */
    private static function params(Context $ctx): array
    {
        return [
            (int)$ctx->input('id', 0),
            (int)$ctx->input('diff_id', 0),
            (int)$ctx->input('monster_id', 0),
        ];
    }

    /**
     * 扫荡所需元宝（控制器 _get_price_by_times）
     *
     * @param int $totimes 目标次数
     * @param int $soultimes 已经购买次数
     * @param int $leavetimes 剩余攻击次数
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
     * 从第 start + 1 次起连续 times 次的价格之和（移植 copies_model->get_price_by_times：100 次起按第 100 次）
     */
    private static function priceByTimes(int $start, int $times): int
    {
        if (CopiesBuyTimesGold::ROWS === []) {
            return -1;
        }
        $list = [];
        foreach (CopiesBuyTimesGold::ROWS as $val) {
            $list[$val['times']] = $val;
        }
        $sub = 0;
        for ($i = 0; $i < $times; $i++) {
            $start = $start + 1 > 100 ? 100 : $start + 1;
            $sub += (int)($list[$start]['gold'] ?? 0);
        }
        return $sub;
    }

    /**
     * 第 times 次购买的价格行（移植 get_gold_by_times）
     *
     * @return array{id: int, times: int, gold: int}|null
     */
    private static function goldByTimes(int $times): ?array
    {
        foreach (CopiesBuyTimesGold::ROWS as $row) {
            if ($row['times'] === $times) {
                return $row;
            }
        }
        return null;
    }

    /**
     * 该 VIP 等级每只怪每日可购买次数（移植 get_copies_vip_gold_times）
     *
     * @return array{id: int, vip: int, times: int}|null
     */
    private static function vipGoldTimes(int $vip): ?array
    {
        foreach (CopiesVipBuyTimes::ROWS as $row) {
            if ($row['vip'] === $vip) {
                return $row;
            }
        }
        return null;
    }

    /**
     * 免费攻打次数（copies_model 构造函数：double_copies 活动期间翻倍）
     */
    private static function freeTimes(int $now): int
    {
        $freeTimes = self::FREE_TIMES;
        foreach (ActivityTime::ROWS as $row) {
            if ($row['name'] === 'double_copies') {
                if ($row['starttime'] <= $now && $row['endtime'] >= $now) {
                    $freeTimes *= 2;
                }
                break;
            }
        }
        return $freeTimes;
    }

    /**
     * 副本全部怪物（移植 get_diff_info）
     *
     * @return list<array<string, mixed>>
     */
    private static function diffInfo(int $copiesId): array
    {
        $return = [];
        foreach (CopiesInfo::ROWS as $row) {
            if ($row['copies_id'] === $copiesId) {
                $return[] = $row;
            }
        }
        return $return;
    }

    /**
     * 某难度全部怪物（get_monster_info($copies_id, $diff_id)）
     *
     * @return list<array<string, mixed>>
     */
    private static function monsterList(int $copiesId, int $diffId): array
    {
        $return = [];
        foreach (CopiesInfo::ROWS as $row) {
            if ($row['copies_id'] === $copiesId && $row['diff_id'] === $diffId) {
                $return[] = $row;
            }
        }
        return $return;
    }

    /**
     * 单只怪物（get_monster_info($copies_id, $diff_id, $monster_id)）
     *
     * @return array<string, mixed>|null
     */
    private static function monsterInfo(int $copiesId, int $diffId, int $monsterId): ?array
    {
        foreach (self::monsterList($copiesId, $diffId) as $row) {
            if ($row['monster_id'] === $monsterId) {
                return $row;
            }
        }
        return null;
    }

    /**
     * 怪物奖励（移植 copies_model->get_reward_info）
     *
     * @return array<string, mixed>|null
     */
    private static function rewardRow(int $copiesId, int $diffId, int $monsterId): ?array
    {
        foreach (CopiesReward::ROWS as $row) {
            if ($row['copies_id'] === $copiesId && $row['diff_id'] === $diffId && $row['monster_id'] === $monsterId) {
                return $row;
            }
        }
        return null;
    }

    /**
     * 每个难度最后一只怪的攻打状态（移植 get_last_diff_info）：没有攻打记录返回 null
     */
    private static function lastDiffStatus(int $uid, int $copiesId, int $diffId): ?int
    {
        $max = 0;
        foreach (self::monsterList($copiesId, $diffId) as $row) {
            $max = max($max, $row['monster_id']);
        }
        if ($max <= 0) {
            return null;
        }
        $status = DB::table('copies_beat')->where('copies_id', $copiesId)->where('diff_id', $diffId)
            ->where('monster_id', $max)->where('uid', $uid)->whereNull('deleted_at')->value('status');
        return $status === null ? null : (int)$status;
    }

    /**
     * 副本全部怪物的攻打信息（移植 get_all_beat_info：没有记录时初始化；不做跨天重置）
     *
     * @param array<string, mixed> $member
     * @return list<array<string, mixed>>
     */
    private static function allBeatInfo(array $member, int $copiesId, int $now): array
    {
        $rows = DB::table('copies_beat')->select(...self::BEAT_COLUMNS)
            ->where('uid', (int)$member['uid'])->where('copies_id', $copiesId)->whereNull('deleted_at')
            ->orderBy('id')->get();
        if ($rows !== []) {
            return array_map(self::castRow(...), $rows);
        }
        // 初始化怪物信息
        return self::initBeatInfo($member, $copiesId, $now);
    }

    /**
     * 初始化攻打副本数据表（移植 init_beat_info）：副本每只怪一行，返回插入的数据（不含 id）
     *
     * @param array<string, mixed> $member
     * @return list<array<string, int>>
     */
    private static function initBeatInfo(array $member, int $copiesId, int $now): array
    {
        $vipInfo = self::vipGoldTimes((int)$member['vip']);
        $data = [];
        // 获取副本数据信息
        foreach (self::diffInfo($copiesId) as $val) {
            $data[] = [
                'uid' => (int)$member['uid'],
                'vip' => (int)$member['vip'],
                'copies_id' => $copiesId,
                'diff_id' => $val['diff_id'],
                'monster_id' => $val['monster_id'],
                'status' => 0,
                'free_times' => self::freeTimes($now),
                'vip_times' => (int)($vipInfo['times'] ?? 0),
                'dateline' => $now,
            ];
        }
        // 旧版副本不存在时 insert_batch 空数组报错退出；这里不插入，返回空数组
        if ($data !== []) {
            DB::table('copies_beat')->insertBatch($data);
        }
        return $data;
    }

    /**
     * 单只怪物的攻打信息（移植 get_beat_info）
     *
     * VIP 等级变化时剩余可购买次数改为新等级的次数；上次攻打不是今天时重置免费次数与可购买次数（跨天惰性重置）。
     * 没有记录时初始化整个副本，并照旧版按下标 diff_id × monster_id - 1 取初始化数据
     * （难度 2、3 取到的是别的怪物的初始数据，数值相同）。
     *
     * @param array<string, mixed> $member
     * @return array<string, int> 查不到时为空数组
     */
    private static function beatInfo(array $member, int $copiesId, int $diffId, int $monsterId, int $now): array
    {
        $uid = (int)$member['uid'];
        $vip = (int)$member['vip'];
        $vipInfo = self::vipGoldTimes($vip);
        $row = self::beatRow($uid, $copiesId, $diffId, $monsterId);
        if ($row === null) {
            // 该副本已有攻打记录、只是没有这只怪时，旧版 init_beat_info 的 insert_batch 撞唯一键报错退出；这里按查不到处理
            if (DB::table('copies_beat')->where('uid', $uid)->where('copies_id', $copiesId)->whereNull('deleted_at')->exists()) {
                return [];
            }
            // 初始化怪物信息
            $allData = self::initBeatInfo($member, $copiesId, $now);
            return $allData[$diffId * $monsterId - 1] ?? [];
        }

        $data = $row;
        $query = static fn () => DB::table('copies_beat')->where('uid', $uid)->where('copies_id', $copiesId)
            ->where('diff_id', $diffId)->where('monster_id', $monsterId)->whereNull('deleted_at');
        // 查询最后一次攻打时间 / 更新攻打次数 购买次数
        if ($vip !== $data['vip']) {
            $data['vip'] = $vip;
            $data['vip_times'] = (int)($vipInfo['times'] ?? 0);
            $query()->update(['vip' => $data['vip'], 'vip_times' => $data['vip_times']]);
        }
        if (date('ymd', $data['dateline']) !== date('ymd', $now) && $data['dateline'] > 0) {
            $dataUpdate = [
                'vip_times' => (int)($vipInfo['times'] ?? 0),
                'free_times' => self::freeTimes($now),
                'dateline' => $now,
            ];
            $query()->update($dataUpdate);
            $data['vip_times'] = $dataUpdate['vip_times'];
            $data['free_times'] = $dataUpdate['free_times'];
        }
        return $data;
    }

    /**
     * 单只怪物的攻打记录原样（不重置）
     *
     * @return array<string, int>|null
     */
    private static function beatRow(int $uid, int $copiesId, int $diffId, int $monsterId): ?array
    {
        $row = DB::table('copies_beat')->select(...self::BEAT_COLUMNS)
            ->where('uid', $uid)->where('copies_id', $copiesId)->where('diff_id', $diffId)
            ->where('monster_id', $monsterId)->whereNull('deleted_at')->first();
        return $row === null ? null : self::castRow($row);
    }

    /**
     * 单个怪物状态信息（移植 get_monster_status：不做跨天 / VIP 重置）
     *
     * 没有记录时旧版向 copies_beat 插入一行（漏写 uid、monster_id 误写成 diff_id，同一怪物第二次即撞唯一键），
     * 调用方只用来判断 status 0（无法扫荡）；这里不插入，返回同样的 status 0。
     *
     * @return array<string, int>
     */
    private static function monsterStatus(int $uid, int $copiesId, int $diffId, int $monsterId): array
    {
        return self::beatRow($uid, $copiesId, $diffId, $monsterId) ?? [
            'copies_id' => $copiesId,
            'diff_id' => $diffId,
            'monster_id' => $diffId,
            'status' => 0,
            'dateline' => time(),
        ];
    }

    /**
     * 修改单个怪物状态信息（移植 update_monster_status）
     */
    private static function updateMonsterStatus(int $uid, int $copiesId, int $diffId, int $monsterId, int $status, int $now): void
    {
        DB::table('copies_beat')->where('uid', $uid)->where('copies_id', $copiesId)->where('diff_id', $diffId)
            ->where('monster_id', $monsterId)->whereNull('deleted_at')
            ->update(['status' => $status, 'dateline' => $now]);
    }

    /**
     * 更新攻击怪物信息（移植 change_free_times）
     *
     * @param array<string, mixed> $member
     * @param int $times 更新攻击次数
     * @param int $vTimes 减少的可购买次数
     * @param int $type 1 增加攻击次数，0 减少
     */
    private static function changeFreeTimes(array $member, int $copiesId, int $diffId, int $monsterId, int $times, int $vTimes, int $type, int $now): void
    {
        $beatInfo = self::beatInfo($member, $copiesId, $diffId, $monsterId, $now);
        if ($beatInfo === []) {
            return;
        }
        $freeTimes = $beatInfo['free_times'];
        if ($times > 0) {
            $freeTimes = $beatInfo['free_times'] - $times;
            if ($type === 1) {
                $freeTimes = $beatInfo['free_times'] + $times;
            }
        }

        $vipTimes = $beatInfo['vip_times'];
        if ($vTimes > 0) {
            $vipTimes = $beatInfo['vip_times'] - $vTimes;
        }

        // 两列在旧版是 unsigned，旧库非严格模式下负数存为 0（扫荡次数大于剩余免费次数时会出现）
        DB::table('copies_beat')->where('uid', (int)$member['uid'])->where('copies_id', $copiesId)
            ->where('monster_id', $monsterId)->where('diff_id', $diffId)->whereNull('deleted_at')
            ->update(['free_times' => max(0, $freeTimes), 'vip_times' => max(0, $vipTimes)]);
    }

    /**
     * 首杀记录（移植 get_beat_log）
     *
     * @return array<string, mixed>|null
     */
    private static function beatLog(int $uid, int $copiesId, int $diffId, int $monsterId): ?array
    {
        $row = DB::table('copies_beat_log')->select(...self::LOG_COLUMNS)
            ->where('uid', $uid)->where('copies_id', $copiesId)->where('diff_id', $diffId)
            ->where('monster_id', $monsterId)->whereNull('deleted_at')->first();
        return $row === null ? null : self::castLog($row);
    }

    /**
     * 添加首杀记录（移植 add_beat_log），返回记录 id（即战报 id）
     *
     * @param array<string, mixed> $member
     */
    private static function addBeatLog(array $member, int $copiesId, int $diffId, int $monsterId, int $status, int $now): int
    {
        return (int)DB::table('copies_beat_log')->insertGetId([
            'uid' => (int)$member['uid'],
            'nickname' => (string)$member['nickname'],
            'level' => (int)$member['level'],
            'copies_id' => $copiesId,
            'diff_id' => $diffId,
            'monster_id' => $monsterId,
            'status' => $status,
            'dateline' => $now,
        ]);
    }

    /**
     * 攻略列表：该怪物最近 7 条首杀记录（移植 get_pk_log；同一秒的记录按 id 倒序）
     *
     * @return list<array<string, mixed>>
     */
    private static function pkLog(int $copiesId, int $diffId, int $monsterId): array
    {
        $rows = DB::table('copies_beat_log')->select(...self::LOG_COLUMNS)
            ->where('copies_id', $copiesId)->where('diff_id', $diffId)->where('monster_id', $monsterId)
            ->whereNull('deleted_at')->orderBy('dateline', 'DESC')->orderBy('id', 'DESC')->limit(7)->get();
        return array_map(self::castLog(...), $rows);
    }

    /**
     * 添加战报（移植 add_report_log：report 列存 base64(gzcompress(战报 json))）
     *
     * @param array<string, mixed> $monsterInfo
     * @param array<string, mixed> $member
     */
    private static function addReportLog(int $id, string $report, array $monsterInfo, array $member): void
    {
        DB::table('copies_report')->insert([
            'id' => $id,
            'report' => $report,
            'nickname' => (string)$member['nickname'],
            'userlevel' => (int)$member['level'],
            'name' => $monsterInfo['name'],
            'level' => $monsterInfo['level'],
        ]);
    }

    /**
     * 按 id 取战报（移植 get_report_by_id）：status 1 成功，-1 不存在
     *
     * @return array{status: int, report?: mixed, info?: array<string, mixed>}
     */
    private static function reportById(int $id): array
    {
        $result = DB::table('copies_report')->select('id', 'report', 'nickname', 'userlevel', 'name', 'level')
            ->where('id', $id)->whereNull('deleted_at')->first();
        if ($result === null) {
            return ['status' => -1];
        }
        $raw = (string)$result['report'];
        $report = json_decode($raw, true);
        if (!is_array($report)) {
            $binary = base64_decode($raw, true);
            $json = $binary !== false ? gzuncompress($binary) : false;
            $report = $json !== false ? json_decode($json, true) : null;
        }
        return [
            'status' => 1,
            'report' => $report,
            'info' => [
                'id' => (int)$result['id'],
                'nickname' => (string)$result['nickname'],
                'userlevel' => (int)$result['userlevel'],
                'name' => (string)$result['name'],
                'level' => (int)$result['level'],
            ],
        ];
    }

    /**
     * 等级达到 110 开启（构造函数 _init）
     *
     * @param array<string, mixed> $member
     */
    private static function isOpen(array $member): bool
    {
        return (int)$member['level'] >= self::OPEN_LEVEL;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, int>
     */
    private static function castRow(array $row): array
    {
        return array_map(intval(...), $row);
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
