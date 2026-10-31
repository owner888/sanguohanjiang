<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Campaign;

use Hanjiang\Battle\BattleEngine;
use Hanjiang\Battle\NewbieBattleReport;
use Hanjiang\Battle\TeamBuilder;
use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\General\TavernHandler;
use Hanjiang\Handler\Pvp\CountryHandler;
use Hanjiang\MasterData\Equip as EquipTemplate;
use Hanjiang\MasterData\EquipEssence;
use Hanjiang\MasterData\Gem;
use Hanjiang\MasterData\General as GeneralTemplate;
use Hanjiang\MasterData\Map as MapTemplate;
use Hanjiang\MasterData\MountTemp;
use Hanjiang\MasterData\Pub;
use Hanjiang\MasterData\Skill;
use Hanjiang\MasterData\Soul;
use Hanjiang\MasterData\Talent;
use Hanjiang\MasterData\TalentScroll;
use Hanjiang\Model\Chat;
use Hanjiang\Model\DailyTask;
use Hanjiang\Model\Equipment;
use Hanjiang\Model\Expostulation;
use Hanjiang\Model\General;
use Hanjiang\Model\MainQuestTask;
use Hanjiang\Model\Member;
use Hanjiang\Model\MemberJade;
use Hanjiang\Model\MemberStone;
use Hanjiang\Model\OpenMap;
use Hanjiang\Model\OwnGem;
use Hanjiang\Model\OwnMount;
use Hanjiang\Model\OwnSoul;
use Hanjiang\Model\PassGeneral;
use Hanjiang\Model\Pay;
use Hanjiang\Model\Practice;
use Hanjiang\Model\PubLog;
use Hanjiang\Model\StatPay;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 主线推图（征战）（移植旧版 controllers/map.php + models/map_model.php）
 *
 * 客户端调用的方法：index、get_scene_list、get_mission_list、mission、action、clear_cd、report、report_detail、
 * get_reward_list、get_mission_reward、send_to_chat、get_newreward_index、get_newreward、get_newresult。
 * 旧版其余公开方法 get_level_func / get_popo_status / get_add_reward 是控制器内部用的，这里改为私有。
 *
 * 配置取 MasterData：Map（势力）、Scene（场景）、Mission（关卡，守军 position1~9 → Monster）、Price（强攻价格 storm）、
 * MissionReward（闯关竞速）、MissionNewreward（关卡宝箱）、LevelFunc（升级功能提示）、Equip / TalentScroll / EquipEssence /
 * General / Skill / Talent / Pub / MountTemp / Soul / Gem（奖励展示与发放）。玩家进度见 Model\OpenMap。
 *
 * 旧版构造函数：exercise 活动（整军）开启时每日刷怪次数翻倍；double_miss 活动开启时 mission 显示的掉落概率翻倍
 * （action 里的掉落翻倍只在 first == 0 时生效，而 first 只会是 1 或 -1，永不生效）。两个活动都按 ActivityTime 判断。
 * vip_model->cd_status() 恒为 1：战斗不检查、不增加攻击 CD（info.cd 恒 0），cd_model->update_attack 走不到，不移植；
 * clear_cd 仍按 member_field.attackstatus / attackdateline 计算。
 *
 * 主公升级时结算训练位经验（_update_practice → Practice::updatePractice）；有国家时帮派谏言任务 7（征战主线，按次数）。
 *
 * 依赖未移植：主公 61 级以上升级时的全服福利 worldarena_model->get_last_reward / update_server_reward（跨服）。
 */
final class MapHandler
{
    /** 闯关竞速活动时长（$end_time，从 member_field.start_time 起 3 天） */
    private const int RACE_TIME = 259200;

    /** 通关处理：最大势力 / 场景 / 关卡（李世民） */
    private const int MAX_LEVEL = 17;
    private const int MAX_STAGE = 7;
    private const int MAX_MID = 10;

    /** 战报分享到聊天室的元宝花费（send_to_chat 的 $price） */
    private const int SHARE_PRICE = 2;

    /** 旧版语言包（general_lang / base_lang / chat_lang） */
    private const string LANG_BASE_CHIP = '碎片';
    private const string LANG_GENERAL_GET = '恭喜玩家【%s】获得%s星武将-%s';
    private const string LANG_GENERAL_PASS = '恭喜玩家【%s】本服首度击杀-%s';
    private const string LANG_GENERAL_LEVELUP = '主公好厉害，又升级了！';
    private const string LANG_MAP_GENERAL_GET = '恭喜玩家【%s】在通关奖励中获得%s星武将-%s';
    private const string LANG_MAP_GET_REWARD = '恭喜您获得';
    private const string LANG_MAP_4 = '紫宝石';
    private const string LANG_MAP_REWARD_ERROR = '势力关卡奖励信息错误';
    private const string LANG_MAP_PARAM_ERROR = '传递参数错误';
    private const string LANG_MAP_REWARDED_ERROR = '已经领取该关卡宝箱奖励';
    private const string LANG_MAP_NOTGO_ERROR = '未通过该关卡';
    private const string LANG_MAP_FAST_ERROR = '操作太频繁了';
    private const string LANG_CHAT_MAP_GET_QUALITY = '恭喜【%s】在通关奖励中获得装备-%s';
    private const string LANG_CHAT_MAP_MOUNT_GET = '恭喜【%s】在通关奖励中获得名马-%s';

    /**
     * 世界地图（势力列表）: c=map&m=index（world 要进入的世界）
     *
     * 返回按势力 id 索引的势力行（Map 行 + status 开启 / pass_status 通关奖励已领），只留当前世界的势力
     * （第一张地图 1~14，第二张 15 以后）；first 是否首次进入、next_status 第一张地图时第二张是否可进、enter_world。
     * 第二张地图开启（势力 id > 14 有开启的）时才按参数 / member.missionworld 进入。修改 member 推图位置为 1（世界）。
     */
    public static function index(Context $ctx): array
    {
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return ['status' => 0];
        }
        $uid = (int)$member['uid'];
        $world = self::intInput($ctx, 'world');

        // 地图2的开启状态
        $open = 0;
        $return = OpenMap::mapList($uid);
        foreach ($return as $val) {
            if ($val['id'] > 14 && $val['status'] === 1) {
                $open = 1;
            }
        }
        // 判断是否进入新地图
        $enterWorld = 1;
        if ($open === 1) {
            $enterWorld = $world !== 0 ? $world : (int)$member['missionworld'];
        }
        if ($enterWorld === 0) {
            $enterWorld = 1;
        }
        // 筛选数据
        foreach ($return as $key => $val) {
            if ($enterWorld === 2) {
                // 删除第一张地图的数据
                if ($val['id'] <= 14) {
                    unset($return[$key]);
                }
            } elseif ($val['id'] > 14) {
                // 删除第二张地图的数据
                unset($return[$key]);
            }
        }
        // 如果当前是进入第一张地图，返回是否可进入第二张地图的状态
        $nextStatus = 0;
        if ($enterWorld === 1 && $open === 1) {
            $nextStatus = 1;
        }
        // 判断是否首次进入
        $firstEnter = 0;
        if ($nextStatus === 1 && (int)$member['firstworld'] !== $enterWorld + 1) {
            $firstEnter = 1;
        }
        if ($firstEnter === 0 && $open === 1 && (int)$member['firstworld'] < $enterWorld) {
            $firstEnter = 1;
        }
        // 判断是否修改首次进入状态
        $updateFirst = 0;
        if ((int)$member['firstworld'] < $enterWorld) {
            $updateFirst = $enterWorld;
        }
        $return['first'] = $firstEnter;
        $return['next_status'] = $nextStatus;
        $return['enter_world'] = $enterWorld;
        // 修改member中的推图位置missionsite
        self::updateMissionsite($uid, 1, 0, 0, $enterWorld, $updateFirst);
        return $return;
    }

    /**
     * 场景列表: c=map&m=get_scene_list（l 势力 id，为 0 时取 member.missionlevel）
     *
     * status：-1 势力未开启；-2 势力没有场景；1 成功（list 按场景 id 索引：Scene 行 + status 已通过 / openstatus 已开启，
     * next_status 下个势力是否开启）；popo 本势力关卡宝箱是否有可领取的。成功时修改 member 推图位置为 2（场景）。
     */
    public static function get_scene_list(Context $ctx): array
    {
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return ['status' => 0];
        }
        $uid = (int)$member['uid'];
        $now = time();
        $level = self::intInput($ctx, 'l');
        // 势力id为假就取member里面的当前所在势力id，
        if ($level === 0) {
            $level = (int)$member['missionlevel'];
        }
        $return = OpenMap::sceneList($uid, $level, $now);
        // 修改member中的推图位置missionsite
        if ($return['status'] >= 1) {
            self::updateMissionsite($uid, 2, $level);
        }
        // 获取通关奖励领取状态
        $popo = self::popoStatus($uid, $level);
        if ($popo === null) {
            return self::rewardError();
        }
        $return['popo'] = $popo;
        return $return;
    }

    /**
     * 关卡列表: c=map&m=get_mission_list（l 势力，s 场景；有一个为 0 时取 member 的 missionlevel / missionstage）
     *
     * status：-1 势力未开启；-2 场景未开启；-3 场景没有关卡；1 成功（list 按关卡 id 索引：id / missionlevel / missionstage /
     * missionid / level / type / generalid / x / y / background / maxtimes / name（掉落装备卷轴或统帅君主才有名字）/
     * status 已通过 / openstatus 已开启，next_status 下个场景是否开启）；popo 同 get_scene_list。
     * 成功时修改 member 推图位置为 3（关卡）。
     */
    public static function get_mission_list(Context $ctx): array
    {
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return ['status' => 0];
        }
        $uid = (int)$member['uid'];
        $level = self::intInput($ctx, 'l');
        $stage = self::intInput($ctx, 's');
        // 势力id为假就取member里面的当前所在势力id
        if ($level === 0 || $stage === 0) {
            $level = (int)$member['missionlevel'];
            $stage = (int)$member['missionstage'];
        }
        $return = OpenMap::missionList($uid, $level, $stage, time());
        // 修改member中的推图位置missionsite
        if ($return['status'] >= 1) {
            self::updateMissionsite($uid, 3, $level, $stage);
        }
        $popo = self::popoStatus($uid, $level);
        if ($popo === null) {
            return self::rewardError();
        }
        $return['popo'] = $popo;
        return $return;
    }

    /**
     * 关卡信息（攻击前的弹窗）: c=map&m=mission（l 势力，s 场景，id 关卡；有一个为 0 时取 member 的位置）
     *
     * status：-1 没有地图记录；-2 场景未开启；-4 没有找到关卡（不判断关卡是否开启）；1 成功
     * info：关卡的 id / missionlevel / missionstage / missionid / level / name / type / recruitrate / generalid / background /
     * firstkillexp / exp / gfirstkillexp / gexp / maxtimes / gettype / getrate / getvalue / image / add_type（Mission 行）；
     * first 1 首杀、-1 非首杀；nowmaxtimes 今日剩余次数，用完时 coin 为强攻一次的元宝；非首杀时 five_sub / ten_sub /
     * fifty_sub / hundred_sub 刷 5 / 10 / 50 / 100 次的元宝，主公比关卡高 5 级以上 exp 为 0；getitemname / quality 掉落物；
     * getrate 掉落概率档（'1' 低于等于 50%、'2' 低于 100%、'0' 其他）；own_general 将领已招募。mop_up 可扫荡（非首杀且 VIP 3）。
     */
    public static function mission(Context $ctx): array
    {
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return ['status' => 0];
        }
        $uid = (int)$member['uid'];
        $now = time();
        $level = self::intInput($ctx, 'l');
        $stage = self::intInput($ctx, 's');
        $id = self::intInput($ctx, 'id');
        // 传值有误的话就取用户里面的信息
        if ($level === 0 || $stage === 0 || $id === 0) {
            $level = (int)$member['missionlevel'];
            $stage = (int)$member['missionstage'];
            $id = (int)$member['missionid'];
        }
        // 取关卡信息
        $mission = OpenMap::missionById($uid, $level, $stage, $id, 1, $now);
        // 如果mission返回状态错误，返回状态信息
        if ($mission['status'] < 1) {
            return $mission;
        }
        $field = ['id', 'missionlevel', 'missionstage', 'missionid', 'level', 'name', 'type', 'recruitrate', 'generalid', 'background', 'firstkillexp', 'exp', 'gfirstkillexp', 'gexp', 'maxtimes', 'gettype', 'getrate', 'getvalue', 'image', 'add_type'];
        $info = [];
        foreach ($field as $fieldVal) {
            $info[$fieldVal] = $mission['info'][$fieldVal];
        }
        // 查询限制攻击次数
        $times = OpenMap::attackTimes($uid, $level, $stage, $id, $now);
        // 检查活动是否开启，活动开启，次数双倍
        if ($info['maxtimes'] > 0 && self::exerciseOpen($now)) {
            $info['maxtimes'] *= 2;
        }
        if ($info['maxtimes'] === 0) {
            // 没有次数限制
        } elseif ($times['status'] === 1) {
            $info['nowmaxtimes'] = max(0, $info['maxtimes'] - $times['times']);
            if ($info['nowmaxtimes'] <= 0) {
                // 查询强攻花费
                $info['coin'] = OpenMap::goldSpent($times['strom']);
            }
        } else {
            $info['nowmaxtimes'] = $info['maxtimes'];
        }
        // 根据返回状态判断是否首杀
        $info['first'] = -1;
        if ($times['status'] === -1) {
            $info['first'] = 1;
        } else {
            // 当主公比怪高超过5级，非首杀不再获得任何主公经验
            if ((int)$member['level'] - $info['level'] >= 5) {
                $info['exp'] = 0;
            }
            if ($info['maxtimes'] !== 0) {
                // 今日已强攻次数
                $getTimesSup = $times['status'] === 1 ? $times['strom'] : 0;
                // 返回重复刷图消耗（5 / 10 / 50 / 100 次）
                foreach (self::sweepPrice($getTimesSup, $info['nowmaxtimes']) as $key => $value) {
                    $info[$key] = $value;
                }
            } else {
                $info['five_sub'] = 0;
                $info['ten_sub'] = 0;
                $info['fifty_sub'] = 0;
                $info['hundred_sub'] = 0;
            }
        }
        // 查询掉落物品
        if ($info['gettype'] === 1 || $info['gettype'] === 2) {
            $item = EquipTemplate::ROWS[$info['getvalue']] ?? null;
            $info['getitemname'] = $item['name'] ?? null;
            $info['quality'] = $item['quality'] ?? null;
            if ($info['gettype'] === 2) {
                $info['getitemname'] .= self::LANG_BASE_CHIP;
            }
        }
        // 查询掉落的天赋卷轴
        if ($info['gettype'] === 5 || $info['gettype'] === 6) {
            $talent = TalentScroll::ROWS[$info['getvalue']] ?? null;
            $info['getitemname'] = Equipment::scrollName((int)($talent['type'] ?? 0));
            $info['quality'] = $talent['quality'] ?? null;
            if ($info['gettype'] === 6) {
                $info['getitemname'] .= self::LANG_BASE_CHIP;
            }
        }
        // 双倍活动期间，奖励翻倍
        if (self::doubleOpen($now)) {
            $info['getrate'] *= 2;
        }
        // 装备获得概率
        if ($info['getrate'] > 0 && $info['getrate'] <= 50) {
            $info['getrate'] = '1';
        } elseif ($info['getrate'] > 0 && $info['getrate'] < 100) {
            $info['getrate'] = '2';
        } else {
            $info['getrate'] = '0';
        }
        // 查询将领是否被招募
        $info['own_general'] = 0;
        if ($info['recruitrate'] && $info['generalid']) {
            $info['recruitrate'] = 1;
            $pubLog = PubLog::byGeneral($uid, $info['generalid']);
            if ($pubLog !== [] && $pubLog['own']) {
                $info['own_general'] = 1;
                $info['recruitrate'] = 0;
            }
        }
        // 判断是否可扫荡
        $mopUp = $info['first'] !== 1 && (int)$member['vip'] >= 3 ? 1 : 0;

        return ['status' => 1, 'info' => $info, 'mop_up' => $mopUp];
    }

    /**
     * 攻击关卡: c=map&m=action（l 势力，s 场景，id 关卡，times 攻击次数：1 普通攻击，5 / 10 / 50 / 100 扫荡）
     *
     * status：-1 军令不足；-3 没有地图记录；-4 场景未开启；-5 关卡未开启；-6 没有找到关卡；-7 元宝不足（强攻）；
     * -8 首杀不能扫荡；-9 VIP 不到 3 级不能扫荡；1 成功（-2 攻击 CD 旧版恒不触发，见类注释）
     *
     * 消耗 times 个军令；当日普通攻击次数（maxtimes，整军活动翻倍）用完后按 Price.storm 收强攻元宝（stat_pay qianggong）。
     * 战斗：玩家阵法 vs 关卡 position1~9 的 Monster 守军（battle_model->battle_mission）；扫荡强制胜利、不返回 battle。
     * 胜利：推进 open_map（打通场景开下一场景 openstatus 1，打通势力 openstatus 2 并记 member_field.newresult、开启后续势力）；
     * 首杀得 firstkillexp / gfirstkillexp，否则 exp × 次数（主公比关卡高 5 级以上为 0）/ gexp × 次数；add_type 1 将领经验
     * 给出战武将、2 加声望；按 getrate% 每次掷一次掉落；按 recruitrate‰ 招募 generalid；首杀记 mission_log + 战报
     * （info.report、share），主线任务 1；非首杀累计今日次数。打通场景或势力且全服首次击杀时小喇叭 general_pass。
     * 主公 41 级以上日常任务 6（按次数）。势力 1 场景 1 首杀时用新手引导的预制战报（NewbieBattleReport）。
     */
    public static function action(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        if (self::member($uid) === null) {
            return ['status' => 0];
        }
        $level = self::intInput($ctx, 'l');
        $stage = self::intInput($ctx, 's');
        $id = self::intInput($ctx, 'id');
        $number = self::intInput($ctx, 'times');
        $number = $number === 0 ? 1 : $number;
        // 同一玩家的攻击串行执行（首杀判定、次数、进度读改写）
        return Member::locked($uid, static fn (): array => self::doAction($uid, $level, $stage, $id, $number, time()));
    }

    /**
     * @return array<string, mixed>
     */
    private static function doAction(int $uid, int $level, int $stage, int $id, int $number, int $now): array
    {
        $member = self::member($uid);
        $return = [
            'status' => 0,
            'info' => [
                'win' => 0,
                'exp' => 0,
                'gexp' => 0,
                'background' => 0,
                'openstatus' => 0,
                'gettype' => 0,
                'getvalue' => 0,
                'itemid' => 0,
                'itemname' => '',
                'levelup' => 0,
                'add_type' => 1,
                'levelupexp' => 0,
                'image' => 0,
                'generalstatus' => 0,
                'get_level' => 0,
                'report' => 0,
                'generalinfo' => [],
            ],
            'battle' => [],
        ];
        // 判断军令数量（旧版负数次数会让 sub_act 反加军令，这里按军令不足返回）
        if ((int)$member['act'] === 0 || (int)$member['act'] - $number < 0 || $number < 0) {
            $return['status'] = -1;
            return $return;
        }
        // 取战斗cd信息：vip_model->cd_status() 恒为 1，不检查攻击 CD（-2 不会出现）

        // 取关卡信息
        $mission = OpenMap::missionById($uid, $level, $stage, $id, 0, $now);
        switch ($mission['status']) {
            case -1:
                // 没有地图记录
                return ['status' => -3];
            case -2:
                // 场景未开启
                return ['status' => -4];
            case -3:
                // 验证关卡是否开启
                return ['status' => -5];
            case -4:
                // 没有找到关卡
                return ['status' => -6];
        }
        $info = $mission['info'];
        $return['info']['background'] = $info['background'];
        // 查询限制攻击次数
        $times = OpenMap::attackTimes($uid, $level, $stage, $id, $now);
        if ($info['maxtimes'] > 0 && self::exerciseOpen($now)) {
            $info['maxtimes'] *= 2;
        }
        // 根据返回状态判断是否首杀
        $info['first'] = -1;
        if ($times['status'] === -1) {
            $info['first'] = 1;
            if ($number > 1) {
                // 首杀没法刷图
                return ['status' => -8];
            }
        }
        // 判断vip等级是否能够刷图
        if ((int)$member['vip'] < 3 && $number > 1) {
            return ['status' => -9];
        }
        // 取剩余免费攻击次数 和 当前已强攻次数
        if ($times['status'] === 1) {
            $getTimesSup = $times['strom'];
            $nowMaxtimes = $info['maxtimes'] - $times['times'];
        } else {
            $getTimesSup = 0;
            $nowMaxtimes = $info['maxtimes'];
        }
        // 取攻击次数对应消耗（旧版条件 !first != 1 恒成立，即只看 maxtimes > 0）
        $coin = 0;
        if ($info['maxtimes'] > 0) {
            // 攻击次数小于剩余免费次数，则消耗免费；否则取超过免费次数需要消耗的元宝
            $coin = $number - $nowMaxtimes < 0 ? 0 : OpenMap::priceByTimes($getTimesSup, $number - $nowMaxtimes);
            if ($coin > 0 && (int)$member['gold'] < $coin) {
                // 元宝不足
                return ['status' => -7];
            }
        }
        $return['status'] = 1;
        // 强攻扣元宝、消耗军令
        if (!Member::deductCurrency($uid, gold: $coin, act: $number)) {
            // 行锁内已校验过军令与元宝，正常走不到
            $return['status'] = $coin > 0 ? -7 : -1;
            return $return;
        }
        if ($coin > 0) {
            // 消费统计记录
            StatPay::record($uid, 'qianggong', $coin);
        }
        // 取map_log，在胜利后会用到
        $mapLog = OpenMap::openMapLog($uid, $level, $stage, $now);
        // 调用算法
        $battle = self::battleMission($uid, $info);
        // 如果刷怪,强制改成胜利
        if ($number > 1) {
            $battle['info']['assess'] = 1;
        }

        $levelNow = (int)$member['level'];
        if ($battle['status'] === 1 && $battle['info']['assess'] >= 1) {
            // 胜利处理：关卡升级
            if ($stage === $mapLog['info']['stage'] && $id > $mapLog['info']['mission']) {
                $num = OpenMap::missionNum($level, $stage);
                if ($id === $num) {
                    // 获取下个场景信息
                    $scene = OpenMap::openScene($level, $stage);
                    if ($scene['status'] === 1 && $level === $scene['info']['level']) {
                        // 开放场景
                        $status = OpenMap::openLog($uid, $level, $scene['info']['stage'], 0, $now);
                        if ($status['status'] === 1) {
                            // 场景开放成功（旧版 update_member_map 只在势力、场景都为 0 时才写，这里不会写）
                            $return['info']['openstatus'] = 1;
                            $return['info']['missionlevel'] = $level;
                            $return['info']['missionstage'] = $scene['info']['stage'];
                        }
                    } else {
                        // 取当前攻击的势力是否有通关奖励
                        $map = MapTemplate::ROWS[$level] ?? null;
                        if ($map !== null && $map['rewardnum'] > 0) {
                            // 开启势力奖励，记录该势力id到member_field表当中
                            self::updateFieldNewresult($uid, $level);
                            $return['info']['openstatus'] = 2;
                            $return['info']['missionlevel'] = 0;
                            $return['info']['missionstage'] = 0;
                        }
                        $return['info']['get_level'] = $level;
                        // 当前势力所有场景关卡开启
                        OpenMap::openLog($uid, $level, $stage, $num, $now);
                        // 根据level查询可开启的势力，排除已开启的
                        $maplist = OpenMap::openMapList($uid, $level);
                        if ($maplist['status'] === 1 && $maplist['info'] !== []) {
                            // 得到可开启的势力id,开启势力
                            $addstatus = OpenMap::addMapLog($uid, array_keys($maplist['info']), $now);
                            if ($addstatus['status'] === 1) {
                                $missionlevel = reset($maplist['info']);
                                $return['info']['openstatus'] = 2;
                                $return['info']['missionlevel'] = $missionlevel['id'];
                                $return['info']['missionstage'] = 1;
                            }
                        }
                        // 所有势和所有场景都通过了时旧版 update_member_map 同样不写
                    }
                } else {
                    // 修改成当前记录就行了（通过关卡）
                    OpenMap::openLog($uid, $level, $stage, $id, $now);
                }
            }
            // 增加经验
            if ($info['first'] === 1) {
                // 首杀经验
                $return['info']['exp'] = $info['firstkillexp'];
                $return['info']['gexp'] = $info['gfirstkillexp'];
            } else {
                // 普通经验(当主公比怪高超过5级，非首杀不再获得任何主公经验)
                $return['info']['exp'] = $levelNow - $info['level'] >= 5 ? 0 : $info['exp'] * $number;
                $return['info']['gexp'] = $info['gexp'] * $number;
            }
            // 主公经验
            $addstatus = Member::addExp($uid, $return['info']['exp'], $now);
            $levelNow = $addstatus['level'] ?? $levelNow;
            // 等级奖励和等级任务信息
            $levelReward = $levelFunc = [];
            if ($addstatus['levelup'] > 0) {
                // 更新训练状态
                Practice::updatePractice($uid, $now);
                // 依赖未移植：等级大于61时修改全服福利记录（worldarena，跨服）
                // 主公等级升级提示
                $levelFuncArr = self::levelFunc($uid, $levelNow);
                $levelReward = $levelFuncArr['levelReward'];
                $levelFunc = $levelFuncArr['levelFunc'];
            }
            $return['info']['levelReward'] = $levelReward;
            $return['info']['levelFunc'] = $levelFunc;
            $return['info']['levelup'] = $addstatus['levelup'];
            $return['info']['levelexp'] = $addstatus['exp'] ?? null;
            $return['info']['levelupexp'] = $addstatus['levelupexp'] ?? null;

            if ($info['add_type'] === 1) {
                // 将领经验（主公等级为加经验后的等级）
                foreach ($battle['info']['leftteam'] ?? [] as $val) {
                    CountryHandler::addGeneralExp($uid, $levelNow, (int)$val['heroid'], (int)$return['info']['gexp']);
                }
            } elseif ($info['add_type'] === 2) {
                // 声望
                Member::addReputation($uid, (int)$return['info']['gexp']);
            }

            // 处理概率掉落（双倍活动只在 first == 0 时翻倍，first 只会是 1 / -1，不生效）
            $return['info']['get_number'] = 0;
            for ($i = 0; $i < $number; $i++) {
                if (mt_rand(1, 100) <= $info['getrate']) {
                    $return['info']['get_number'] += 1;
                }
            }
            // 添加道具
            if ($return['info']['get_number'] > 0) {
                self::missionDrop($uid, $levelNow, $info, $return['info']);
            }

            // 限制38关卡掉落概率（旧版拿场景内关卡号比较，永不成立，照搬）
            if ($id === 38) {
                $info['recruitrate'] = 0;
            }
            // 招募武将
            if ($info['generalid'] > 0) {
                self::recruit($uid, $member, $level, $info, $number, $return['info'], $now);
            }

            // 检查是否是最终守将，并且判断本服是否首次击杀，是就发一条公告
            if ($return['info']['openstatus']) {
                $result = OpenMap::missionReport($level, $stage, $id);
                if ($result['status'] === -1) {
                    $str = $info['name'];
                    if ($level > 13) {
                        // 英雄集结势力 击杀通知修改
                        $str = '';
                        $map = MapTemplate::ROWS[$level] ?? null;
                        if ($map !== null) {
                            $str .= $map['name'] . '·';
                        }
                        $sence = OpenMap::sceneById($level, $stage);
                        if ($sence !== []) {
                            $str .= $sence['name'];
                        }
                        $str .= $info['name'];
                    }
                    // 发送公告
                    Chat::sendToHorn(sprintf(self::LANG_GENERAL_PASS, $member['nickname'], $str), Chat::sender($uid));
                }
            }

            // 添加攻击记录
            if ($info['first'] === 1) {
                // 用户神树信息
                $treeId = (int)($battle['info']['leftbuff']['tree'] ?? 0);
                // 首杀记录
                $missionLogId = OpenMap::addMissionLog(['level' => $levelNow] + $member, $level, $stage, $id, $treeId, $now);
                if ($missionLogId > 0) {
                    $gzipreport = base64_encode((string)gzcompress((string)json_encode($battle['info'])));
                    OpenMap::addMissionReport($missionLogId, $gzipreport, ['level' => $levelNow] + $member, $info);
                    $return['info']['report'] = $missionLogId;
                    $return['share'] = ['id' => $missionLogId, 'type' => 'map'];
                }
                // 主线任务 1击败关卡
                MainQuestTask::action($uid, 1, $info['id']);
            } elseif ($info['maxtimes'] > 0) {
                // 本次攻击强攻次数
                $strom = $number - $nowMaxtimes > 0 ? $number - $nowMaxtimes : 0;
                $data = [];
                if ($strom > 0) {
                    // 有强攻次数，说明普通攻击次数已满
                    $data['times'] = $info['maxtimes'];
                    $data['strom'] = $times['status'] === -2 ? $strom : $times['strom'] + $strom;
                } else {
                    $data['strom'] = 0;
                    $data['times'] = $times['status'] === -2 ? $number : $times['times'] + $number;
                }
                $data['dateline'] = $now;
                OpenMap::updateMissionLog($uid, $level, $stage, $id, $data);
            }
        } elseif ($battle['status'] < 0) {
            // -1 攻击方没有阵法信息；-3 攻击方武将信息错误
            $battle['info']['assess'] = 0;
        }
        // 成功状态
        $return['info']['win'] = $battle['info']['assess'];
        // 攻击对象
        $return['info']['type'] = $info['type'];
        $return['info']['first'] = $info['first'];
        $return['info']['image'] = $info['image'];
        $return['info']['name'] = $info['name'];
        $return['info']['add_type'] = $info['add_type'];
        // 攻击 cd（cd_status 恒为 1，不增加，恒 0）
        $return['info']['cd'] = 0;

        $return['battle'] = $battle['info'];
        $return['battle']['left_role'] = [
            'name' => $member['nickname'],
            'level' => $levelNow,
            'gender' => (int)$member['gender'],
        ];
        $return['battle']['right_role'] = [
            'name' => $info['name'],
            'level' => $info['level'],
            'type' => $info['type'],
        ];
        $leftRole = $return['battle']['left_role'];
        $rightRole = $return['battle']['right_role'];
        $resultInfo = $return['info'];

        // 刷图不需要返回战斗过程,需要返回新的关卡信息和刷图需要元宝
        if ($number > 1) {
            unset($return['battle']);
            // 查询限制攻击次数，取元宝消耗
            if ($info['maxtimes'] === 0) {
                $return['info']['five_sub'] = 0;
                $return['info']['ten_sub'] = 0;
                $return['info']['fifty_sub'] = 0;
                $return['info']['hundred_sub'] = 0;
            } else {
                $times = OpenMap::attackTimes($uid, $level, $stage, $id, $now);
                if ($times['status'] === 1) {
                    $getTimesSup = $times['strom'];
                    $nowMaxtimes = $info['maxtimes'] - $times['times'];
                } else {
                    $getTimesSup = 0;
                    $nowMaxtimes = $info['maxtimes'];
                }
                foreach (self::sweepPrice($getTimesSup, $nowMaxtimes) as $key => $value) {
                    $return['info'][$key] = $value;
                }
            }
        }

        // 日常任务(6是关卡挑战对应的类型)
        if ($levelNow >= DailyTask::OPEN_LEVEL) {
            DailyTask::action($uid, DailyTask::TYPE_MAP, $number, $now);
        }
        // 帮派任务(谏言：7是征战主线的类型)
        $country = (int)DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('country');
        if ($country > 0) {
            Expostulation::action($country, 7, $number);
        }

        // 战报数据模拟（新手引导）
        if ($level === 1 && $stage === 1 && $return['info']['first'] === 1) {
            $reportInfo = self::newbieReport($id, $resultInfo, $leftRole, $rightRole);
            if ($reportInfo !== null) {
                $return = $reportInfo;
            }
        }
        return $return;
    }

    /**
     * 清除攻击 CD: c=map&m=clear_cd（type 0 查看消耗，1 确认清除）
     *
     * status：-1 不需要清除；-2 元宝不足；-3 清除失败；0 查看（gold 为消耗）；1 成功（all_gold）。
     * 每 180 秒 1 元宝（向上取整），主公 20 级以下免费；消费统计 zhengzhan_cd。
     */
    public static function clear_cd(Context $ctx): array
    {
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return ['gold' => 0, 'status' => 0];
        }
        $uid = (int)$member['uid'];
        $now = time();
        $return = ['gold' => 0, 'status' => 0];
        $type = self::intInput($ctx, 'type');
        $cd = self::getAttack($uid, $now);
        if ($cd['cd'] === 0) {
            // 不需要清除
            $return['status'] = -1;
            return $return;
        }
        $return['gold'] = (int)ceil($cd['cd'] / 180);
        if ((int)$member['level'] <= 20) {
            $return['gold'] = 0;
        }
        if ((int)$member['gold'] < $return['gold']) {
            // 金币不足
            $return['status'] = -2;
            return $return;
        }
        if ($type === 1) {
            if (Member::deductCurrency($uid, gold: $return['gold'])) {
                // 成功
                $return['status'] = 1;
                $return['all_gold'] = (int)DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('gold');
                // clear_attack
                DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')
                    ->update(['attackstatus' => 0, 'attackdateline' => $now]);
                // 消费统计记录
                StatPay::record($uid, 'zhengzhan_cd', $return['gold']);
            } else {
                // 清除失败
                $return['status'] = -3;
            }
        }
        return $return;
    }

    /**
     * 关卡战报列表（攻略）: c=map&m=report（l 势力，s 场景，id 关卡）
     *
     * status：-2 参数不完整；-1 没有战报；1 成功（list 按记录 id 索引，全服最近 7 条首杀：id / uid / level / nickname / tree）
     */
    public static function report(Context $ctx): array
    {
        $level = self::intInput($ctx, 'l');
        $stage = self::intInput($ctx, 's');
        $id = self::intInput($ctx, 'id');
        $return = ['status' => 0, 'list' => []];
        if ($level === 0 || $stage === 0 || $id === 0) {
            // 参数不完整
            $return['status'] = -2;
            return $return;
        }
        $result = OpenMap::missionReport($level, $stage, $id);
        $return['status'] = $result['status'];
        if ($return['status'] === 1) {
            // 获取用户昵称，填充昵称为空的记录
            if ($result['uid_arr'] !== []) {
                $nicknames = [];
                foreach (DB::table('member')->select('uid', 'nickname')->whereIn('uid', $result['uid_arr'])->whereNull('deleted_at')->get() as $value) {
                    $nicknames[(int)$value['uid']] = (string)$value['nickname'];
                }
                foreach ($result['list'] as $key => $value) {
                    if ($value['nickname'] === '') {
                        $result['list'][$key]['nickname'] = $nicknames[$value['uid']] ?? null;
                    }
                }
            }
            $return['list'] = $result['list'];
        }
        return $return;
    }

    /**
     * 单条战报: c=map&m=report_detail（id 战报 id）
     *
     * status：-1 没有指定的战报；1 成功（battle 战报 + left_role / right_role，info.background / info.win）
     */
    public static function report_detail(Context $ctx): array
    {
        $id = self::intInput($ctx, 'id');
        $return = ['status' => 0, 'battle' => [], 'info' => []];
        $result = OpenMap::missionReportById($id);
        if ($result['status'] === -1) {
            // 没有指定的战报
            $return['status'] = -1;
            return $return;
        }
        $return['status'] = 1;
        $return['battle'] = $result['report'];
        $return['info']['background'] = $result['info']['background'];
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
     * 闯关竞速礼包列表: c=map&m=get_reward_list
     *
     * status：-1 活动已结束（注册 3 天后）；1 成功（list 为 MissionReward 行 + image 关卡头像 + open_status：0 可领取、
     * 1 未通过、2 已领取，按 open_status 排序；race_dateline 剩余秒数）
     */
    public static function get_reward_list(Context $ctx): array
    {
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return ['status' => 0];
        }
        $uid = (int)$member['uid'];
        $now = time();
        // 取field信息，判断活动是否结束
        $startTime = self::startTime($uid);
        if ($now - $startTime > self::RACE_TIME) {
            // 活动已结束
            return ['status' => -1];
        }
        $list = self::raceList($uid);
        $status = array_column($list, 'open_status');
        array_multisort($status, SORT_ASC, $list);
        return [
            'status' => 1,
            'list' => $list,
            'race_dateline' => self::RACE_TIME - ($now - $startTime),
        ];
    }

    /**
     * 领取闯关竞速礼包: c=map&m=get_mission_reward（id 关卡 id）
     *
     * status：-1 活动已结束；-2 未通过该关卡；-3 已领取过；-4 奖励不存在；1 成功（gold 剩余元宝，popo 是否还有可领取的）。
     * 记一次参加人次（map_action_log）。旧版 id 为 0 时会按「全部奖励」发 0 元宝并写 missionid 0 的领取记录，这里按 -4 返回。
     */
    public static function get_mission_reward(Context $ctx): array
    {
        $member = self::member(MemberHandler::resolveUid($ctx));
        $return = ['popo' => 0];
        if ($member === null) {
            return $return + ['status' => 0];
        }
        $uid = (int)$member['uid'];
        $now = time();
        $id = self::intInput($ctx, 'id');
        // 取field信息，判断活动是否结束
        if ($now - self::startTime($uid) > self::RACE_TIME) {
            // 活动已结束
            $return['status'] = -1;
            return $return;
        }
        // 取通过最大的关卡id，判断是否通过
        $getId = OpenMap::missionMax($uid);
        if ($id > $getId['id']) {
            $return['status'] = -2;
            return $return;
        }
        if ($id <= 0) {
            $return['status'] = -4;
            return $return;
        }
        // 判断是否领取过了
        if (OpenMap::rewardLog($uid, $id)['status'] === 1) {
            $return['status'] = -3;
            return $return;
        }
        // 取奖励信息
        $info = OpenMap::rewardList($id);
        if ($info['status'] === 0) {
            $return['status'] = -4;
            return $return;
        }
        // 添加领取记录（唯一键，并发重复领取按已领取返回），添加金币
        if (!OpenMap::addRewardLog($uid, $id, $now)) {
            $return['status'] = -3;
            return $return;
        }
        Member::addCurrency($uid, gold: (int)$info['list']['gold']);
        // 返回剩余金币
        $return['status'] = 1;
        $return['gold'] = (int)DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('gold');
        // 竞速提醒状态：有可领取的为 1
        foreach (self::raceList($uid) as $val) {
            if ($val['open_status'] !== 0) {
                // 不可领取状态
                $return['popo'] = 0;
            } else {
                $return['popo'] = 1;
                break;
            }
        }
        // 记录活动人次
        OpenMap::addActionLog($uid, (int)$member['level'], $now);
        return $return;
    }

    /**
     * 发送战报到聊天室: c=map&m=send_to_chat（l 势力，s 场景，id 关卡，report 战报 id；扣 2 元宝）
     *
     * status：-1 参数不完整；-2 元宝不足 / 关卡不存在或未开启；1 成功（all_gold）
     */
    public static function send_to_chat(Context $ctx): array
    {
        // 战报id
        $reportId = self::intInput($ctx, 'report');
        $level = self::intInput($ctx, 'l');
        $stage = self::intInput($ctx, 's');
        $id = self::intInput($ctx, 'id');
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($level === 0 || $stage === 0 || $id === 0 || $reportId === 0 || $member === null) {
            return ['status' => -1];
        }
        $uid = (int)$member['uid'];
        // 判断金币是否足够
        if ((int)$member['gold'] < self::SHARE_PRICE) {
            return ['status' => -2];
        }
        // 查询关卡名字
        $info = OpenMap::missionById($uid, $level, $stage, $id);
        if ($info['status'] !== 1) {
            return ['status' => -2];
        }
        // 扣除金币
        $allGold = Chat::chargeShare($uid, self::SHARE_PRICE);
        if ($allGold === null) {
            return ['status' => -2];
        }
        $sender = Chat::sender($uid);
        $message = Chat::getMessageStr(4, ['general' => $info['info']['name'], 'id' => $reportId], $sender);
        Chat::sendToAll($message, 4, $reportId, 0, $sender);
        return ['status' => 1, 'all_gold' => $allGold];
    }

    /**
     * 关卡宝箱列表: c=map&m=get_newreward_index（levelid 势力 id）
     *
     * status：-1 奖励信息失败（msg）；1 成功（list 为 MissionNewreward 行 + is_get：0 不能领取、1 可以领取、2 已领取；
     * 装备 / 武将 / 天赋卷轴 / 装备碎片（re_type 8 / 12 / 29 / 30）带 info 展示信息）。通关李世民（17-7-10）后都可领取。
     */
    public static function get_newreward_index(Context $ctx): array
    {
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return ['status' => 0];
        }
        return self::newrewardIndex($member, self::intInput($ctx, 'levelid'), time());
    }

    /**
     * 领取关卡宝箱: c=map&m=get_newreward（id 宝箱 id）
     *
     * status：-3 操作太频繁了；-1 参数错误；-2 奖励信息失败；2 未通过该关卡；3 已经领取；1 成功（popo、reward 领取后的
     * 宝箱信息、gold / silver 当前元宝银币）。通关李世民后不校验关卡。
     */
    public static function get_newreward(Context $ctx): array
    {
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return ['status' => 0];
        }
        $uid = (int)$member['uid'];
        $now = time();
        // 判断是否领取操作结束
        if (!OpenMap::lock($uid)) {
            return ['status' => -3, 'msg' => self::LANG_MAP_FAST_ERROR];
        }
        try {
            return self::doNewreward($member, self::intInput($ctx, 'id'), self::intInput($ctx, 'levelid'), $now);
        } finally {
            // 删除领取key
            OpenMap::unlock($uid);
        }
    }

    /**
     * 领取势力通关奖励: c=map&m=get_newresult（id 势力 id）
     *
     * status：-3 操作太频繁 / 已经领取过；-1 没有可领取的奖励（id 不是 member_field.newresult）；-2 势力未开启；
     * -4 势力没有奖励；1 成功（gettype：1 装备 item、2 天赋卷轴 item、3 声望、4 坐骑 info；rewardnum 数量）。
     * 先把 member_field.newresult 清 0、open_map.newresult 记 1，再发奖（旧版顺序，-4 时也已标记）。
     */
    public static function get_newresult(Context $ctx): array
    {
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return ['status' => 0];
        }
        $uid = (int)$member['uid'];
        // 判断是否领取操作结束
        if (!OpenMap::lock($uid)) {
            return ['status' => -3];
        }
        try {
            return self::doNewresult($uid, self::intInput($ctx, 'id'), time());
        } finally {
            // 删除领取key
            OpenMap::unlock($uid);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function doNewresult(int $uid, int $id, int $now): array
    {
        $return = ['status' => 0];
        // 取member_field信息
        $newresult = (int)(DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->value('newresult') ?? 0);
        if ($id === 0 || $id !== $newresult) {
            // 没有可领取的奖励
            $return['status'] = -1;
            return $return;
        }
        // 取map_log信息
        $log = OpenMap::openMapLog($uid, $id, 0, $now);
        if ($log['status'] === -1) {
            // 势力未开启，不能领取
            $return['status'] = -2;
            return $return;
        }
        if ($log['info']['newresult'] === 1) {
            // 奖励已经领取过了
            $return['status'] = -3;
            return $return;
        }
        // 修改member_field信息、修改领取状态
        self::updateFieldNewresult($uid, 0);
        OpenMap::updateNewresult($uid, $id);

        // 根据id取势力奖励
        $info = MapTemplate::ROWS[$id] ?? null;
        if ($info === null || !$info['rewardtype'] || !$info['reward']) {
            $return['status'] = -4;
            return $return;
        }
        switch ($info['rewardtype']) {
            case 1:
                // 添加装备
                $item = EquipTemplate::ROWS[$info['reward']] ?? null;
                if ($item !== null) {
                    Equipment::addFromTemplate($uid, $info['reward']);
                }
                $return['status'] = 1;
                $return['gettype'] = $info['rewardtype'];
                $return['item'] = $item;
                $return['rewardnum'] = $info['rewardnum'];
                break;
            case 2:
                // 添加天赋卷轴
                $item = Equipment::addTalentScroll($uid, $info['reward'], 0, $info['rewardnum'], 'map', 'get_newresult', $now);
                if ($item['status'] === 1) {
                    $return['status'] = 1;
                    $return['gettype'] = $info['rewardtype'];
                    $return['item'] = $item['info'];
                    $return['rewardnum'] = $info['rewardnum'];
                }
                break;
            case 3:
                // 添加声望
                Member::addReputation($uid, $info['rewardnum']);
                $return['status'] = 1;
                $return['gettype'] = $info['rewardtype'];
                $return['rewardnum'] = $info['rewardnum'];
                break;
            case 4:
                // 添加坐骑
                $mount = OwnMount::addMount($uid, $info['reward'], 0, $info['rewardnum'], 'map', 'get_newresult', $now);
                $return['status'] = 1;
                $return['gettype'] = $info['rewardtype'];
                $return['info'] = $mount['info'] ?? null;
                $return['rewardnum'] = $info['rewardnum'];
                break;
        }
        return $return;
    }

    /**
     * @param array<string, mixed> $member
     * @return array<string, mixed>
     */
    private static function doNewreward(array $member, int $id, int $levelidInput, int $now): array
    {
        $uid = (int)$member['uid'];
        if ($id <= 0) {
            return ['status' => -1, 'msg' => self::LANG_MAP_PARAM_ERROR];
        }
        // 查看奖励信息是否存在
        $reward = OpenMap::missionNewreward($id);
        if ($reward === []) {
            return ['status' => -2, 'msg' => self::LANG_MAP_REWARD_ERROR];
        }
        // 势力ID和关卡ID
        $levelid = $reward['levelid'];
        $missid = $reward['missid'];
        // 通关李世民的不校验关卡
        if (!self::passedAll($uid, $now)) {
            // 取通过最大的关卡id
            $getMaxmiss = OpenMap::missionMax($uid);
            if ($getMaxmiss['id'] < $missid) {
                return ['status' => 2, 'msg' => self::LANG_MAP_NOTGO_ERROR];
            }
        }
        // 验证是否已经领取过该关卡奖励；添加奖励领取日志（唯一键，并发重复领取按已领取返回）
        if (OpenMap::newrewardlogInfo($uid, $levelid, $missid) !== [] || OpenMap::addNewrewardlog($uid, $levelid, $missid, $now) === 0) {
            return ['status' => 3, 'msg' => self::LANG_MAP_REWARDED_ERROR];
        }
        // 发送奖励
        self::addReward($member, $reward, 1, $now);
        // 处理宝箱信息（旧版内部调用 get_newreward_index(1)，levelid 取本次请求的参数，客户端不传即全部宝箱）
        foreach (self::newrewardIndex($member, $levelidInput, $now)['list'] ?? [] as $val) {
            if ($val['id'] === $id) {
                $reward = $val;
            }
        }
        $popo = self::popoStatus($uid, $levelid);
        $row = DB::table('member')->select('gold', 'silver')->where('uid', $uid)->whereNull('deleted_at')->first();
        return [
            'status' => 1,
            'popo' => $popo,
            'reward' => $reward,
            'gold' => (int)$row['gold'],
            'silver' => (int)$row['silver'],
        ];
    }

    /**
     * 关卡宝箱列表（get_newreward_index）
     *
     * @param array<string, mixed> $member
     * @return array<string, mixed>
     */
    private static function newrewardIndex(array $member, int $levelid, int $now): array
    {
        $uid = (int)$member['uid'];
        // 获取奖励信息
        $rewardList = OpenMap::missionNewreward(0, $levelid);
        if ($rewardList === []) {
            return self::rewardError();
        }
        // 取通过最大的关卡id
        $getMaxmiss = OpenMap::missionMax($uid);
        // 查询已经领取的关卡宝箱奖励信息
        $getRelist = OpenMap::newrewardlogList($uid);
        // 默认没有通关李世民
        $passed = self::passedAll($uid, $now);
        // 处理宝箱列表信息
        foreach ($rewardList as &$val) {
            // 是否已经领取 0，不能领取；1，可以领取；2，已领取
            $val['is_get'] = 0;
            if ($getMaxmiss['id'] >= $val['missid']) {
                $val['is_get'] = 1;
            }
            // 对通关李世民的做容错处理
            if ($passed) {
                $val['is_get'] = 1;
            }
            if (isset($getRelist[$val['levelid'] . '-' . $val['missid']])) {
                $val['is_get'] = 2;
            }
            // 奖励信息处理
            if (in_array($val['re_type'], [8, 12, 29, 30], true)) {
                $info = self::addReward($member, $val, 0, $now);
                $val['info'] = $info['info'] ?? [];
            }
        }
        unset($val);
        return ['status' => 1, 'list' => $rewardList];
    }

    /**
     * 是否通了李世民的关卡（open_map 里势力 17 的记录为 17-7-10）
     */
    private static function passedAll(int $uid, int $now): bool
    {
        $result = OpenMap::openMapLog($uid, self::MAX_LEVEL, 0, $now);
        return $result['status'] === 1
            && $result['info']['level'] === self::MAX_LEVEL
            && $result['info']['stage'] === self::MAX_STAGE
            && $result['info']['mission'] === self::MAX_MID;
    }

    /**
     * 势力关卡宝箱是否有可领取的（get_popo_status：1 有，0 没有）
     *
     * 旧版势力没有宝箱配置时先输出「奖励信息错误」再继续输出原接口的结果（客户端只认第一段），这里返回 null，
     * 调用方按第一段（rewardError）返回。
     */
    private static function popoStatus(int $uid, int $levelid): ?int
    {
        // 获取奖励信息
        $rewardList = OpenMap::missionNewreward(0, $levelid);
        if ($rewardList === []) {
            return null;
        }
        //取通过最大的关卡id
        $getMaxmiss = OpenMap::missionMax($uid);
        $isGet = 0;
        foreach ($rewardList as $val) {
            if ($getMaxmiss['id'] >= $val['missid']
                && OpenMap::newrewardlogInfo($uid, $levelid, $val['missid']) === []) {
                $isGet += 1;
            }
        }
        return $isGet > 0 ? 1 : 0;
    }

    /**
     * 添加 / 查看奖励信息（get_add_reward：$isCheck 0 只查看展示信息，1 发放）
     *
     * 旧版照搬：元宝 / 银币 / 宝石 / 原魂 / 玉石 / 声望 / 宝石碎片 / 突飞卡 / 军令 / 三种石头 / 精华不看 $isCheck
     * （查看只会传装备 / 武将 / 天赋卷轴 / 装备碎片）；宝石、将魂按 0 级。
     *
     * @param array<string, mixed> $member
     * @param array<string, mixed> $reward MissionNewreward 行
     * @return array<string, mixed>
     */
    private static function addReward(array $member, array $reward, int $isCheck, int $now): array
    {
        $uid = (int)$member['uid'];
        $return = [];
        $type = (int)$reward['re_type'];
        $num = (int)$reward['re_num'];
        $level = 0;
        $id = (int)$reward['re_value'];
        switch ($type) {
            case 1: // 元宝
                Member::addCurrency($uid, gold: $num);
                break;
            case 2: // 银币(具体数值)
                Member::addCurrency($uid, silver: $num);
                break;
            case 3: // 黄宝石
                TavernHandler::addJade($uid, 2, $num, 'map', 'get_newreward');
                break;
            case 4: // 紫宝石
                TavernHandler::addJade($uid, 3, $num, 'map', 'get_newreward');
                break;
            case 5: // 原魂
                OwnSoul::addSoul($uid, $num, 0, 'map', 'get_newreward', $now);
                break;
            case 6: // 玉石
                MemberJade::add($uid, $num, 'map', 'get_newreward', $now);
                break;
            case 7: // 声望
                Member::addReputation($uid, $num);
                break;
            case 8: // 装备
                $item = EquipTemplate::ROWS[$id] ?? null;
                if ($isCheck === 1 && $item !== null) {
                    // 添加装备
                    Equipment::addFromTemplate($uid, $id, 1);
                    // 发送广播到小喇叭：只发紫装以上品质的装备
                    if ((int)$item['quality'] >= 5) {
                        $name = Chat::quality($item['quality'], $item['name']);
                        Chat::sendToHorn(sprintf(self::LANG_CHAT_MAP_GET_QUALITY, $member['nickname'], $name), Chat::sender($uid));
                    }
                }
                $return['info'] = [
                    'id' => $item['id'] ?? null,
                    'image' => $item['id'] ?? null,
                    'name' => $item['name'] ?? null,
                    'quality' => $item['quality'] ?? null,
                    'type' => $item['type'] ?? null,
                    'needlevel' => $item['needlevel'] ?? null,
                    'power' => $item['power'] ?? null,
                ];
                break;
            case 9: // 宝石碎片
                OwnGem::addChip($uid, $num, 'map', 'get_newreward', $now);
                break;
            case 10: // 突飞卡
                PassGeneral::addFreeleap($uid, $num);
                break;
            case 11: // 蓝宝石
                TavernHandler::addJade($uid, 1, $num, 'map', 'get_newreward');
                break;
            case 12: // 武将
                $gid = $id;
                if ($isCheck !== 1) {
                    // 返回武将信息
                    $generalInfo = GeneralTemplate::ROWS[$gid] ?? null;
                    $return['info'] = $generalInfo;
                    $return['info']['skill'] = Skill::ROWS[$generalInfo['skillid'] ?? 0] ?? [];
                    $return['info']['talent'] = Talent::ROWS[$generalInfo['talentid'] ?? 0] ?? [];
                } elseif (PassGeneral::ownsGeneral($uid, $gid)) {
                    // 已经获得武将：按酒馆价格的 8 成给紫宝石
                    $pub = Pub::ROWS[$gid] ?? null;
                    if ($pub !== null) {
                        $jadenum = (int)$pub['price'] * 0.8;
                        TavernHandler::addJade($uid, 3, (int)round($jadenum), 'map', 'get_newreward');
                        $return['info']['msg'] = self::LANG_MAP_GET_REWARD . self::LANG_MAP_4 . '*' . self::number($jadenum);
                    }
                } else {
                    // 添加武将
                    $general = General::addGeneral($uid, $gid);
                    if ($general !== null) {
                        $return['info'] = $general;
                        $star = Chat::starWord((int)$general['star']);
                        if ($star !== null) {
                            $sender = Chat::sender($uid);
                            Chat::sendToHorn(sprintf(self::LANG_MAP_GENERAL_GET, $member['nickname'], $star, $general['name']), $sender);
                            // 聊天框发送消息
                            $chat = Chat::getMessageStr(43, ['star_general' => $star, 'general' => $general['name']], $sender);
                            Chat::sendToAll($chat, Chat::TYPE_SYSTEM, 0, 0, $sender);
                        }
                        // 添加酒馆日志(武将id, 拥有状态, 获取方式)
                        PubLog::add($uid, $gid, 1, 11, $now);
                    }
                }
                break;
            case 13: // 军令
                Member::addCurrency($uid, act: $num);
                break;
            case 14: // 精炼石
            case 15: // 五彩石
            case 16: // 上古血石
                MemberStone::add($uid, $num, $type - 13, 'map', 'get_newreward', $now);
                break;
            case 17: case 18: case 19: case 20: case 21: case 22: case 23: case 24: case 25: // 装备精华
                $essid = PassGeneral::ESSENCE_ID[$type];
                $essInfo = EquipEssence::ROWS[$essid] ?? [];
                if ($essInfo !== [] && $isCheck === 1) {
                    // 去除描述信息
                    unset($essInfo['desc']);
                    $essInfo['value'] = $num;
                    Equipment::addTowerItem($uid, $essid, $num);
                }
                $return['info'] = $essInfo;
                $return['info']['type'] = $type;
                $return['info']['num'] = $num;
                break;
            case 26: // 坐骑
                $return['info'] = ['id' => $id, 'name' => '', 'quality' => 0];
                if ($isCheck !== 1) {
                    // 获取坐骑信息
                    $mount = MountTemp::ROWS[$id] ?? null;
                    if ($mount !== null) {
                        $return['info']['name'] = $mount['name'];
                        $return['info']['quality'] = $mount['quality'];
                    }
                } else {
                    // 添加坐骑
                    $result = OwnMount::addMount($uid, $id, 0, 1, 'map', 'get_newreward', $now);
                    if ($result['status'] === 1) {
                        $quality = (int)$result['info']['quality'];
                        // 如果获得的坐骑品级大于5  发送广播到小喇叭
                        if ($quality >= 5) {
                            $qualityStr = Chat::quality($quality, (string)$result['info']['name']);
                            Chat::sendToHorn(sprintf(self::LANG_CHAT_MAP_MOUNT_GET, $member['nickname'], $qualityStr), Chat::sender($uid));
                        }
                        $return['info'] = [
                            'id' => $result['info']['mid'],
                            'name' => $result['info']['name'],
                            'quality' => $quality,
                        ];
                    }
                }
                break;
            case 27: // 将魂
                $soul = Soul::ROWS[$id] ?? null;
                if ($isCheck === 1) {
                    OwnSoul::addHeroSoul($uid, $id, $level);
                }
                if ($soul !== null) {
                    $return['info'] = $soul;
                    $return['info']['image'] = $soul['type'] . $soul['quality'];
                }
                break;
            case 28: // 宝石
                $gemNum = 1;
                // 查看宝石信息
                $gemInfo = Gem::ROWS[$id] ?? null;
                if ($isCheck === 1) {
                    // 添加宝石，修改宝石格子占用数
                    if (OwnGem::addGem($uid, $id, $level, $gemNum, $now)['status'] === 1) {
                        OwnGem::addGemuse($uid, $gemNum);
                    }
                }
                if ($gemInfo !== null) {
                    $return['info'] = [
                        'gem' => $id,
                        'gem_name' => $gemInfo['name'],
                        'gem_type' => $gemInfo['type'],
                        'gem_power' => $gemInfo['level' . $level] ?? null,
                        'gem_level' => $level,
                        'gem_num' => $gemNum,
                        'gem_quality' => $level > 0 ? (int)ceil($level / 4) : 1,
                    ];
                }
                break;
            case 29: // 天赋卷轴
                $talentInfo = TalentScroll::ROWS[$id] ?? null;
                if ($isCheck === 1) {
                    Equipment::addTalentScroll($uid, $id, 0, $num, 'map', 'get_newreward', $now);
                }
                if ($talentInfo !== null) {
                    $return['info'] = [
                        'itemtype' => $talentInfo['type'],
                        'itemid' => $id,
                        'quality' => $talentInfo['quality'],
                        'itemname' => Equipment::scrollName((int)$talentInfo['type']),
                    ];
                }
                break;
            case 30: // 装备碎片
                $item = EquipTemplate::ROWS[$id] ?? null;
                if ($isCheck === 1 && $item !== null) {
                    // 添加碎片
                    Equipment::addChip($uid, $id, $num, 'map', 'get_newreward', $now);
                }
                $return['info'] = [
                    'id' => $item['id'] ?? null,
                    'image' => $item['id'] ?? null,
                    'name' => ($item['name'] ?? '') . self::LANG_BASE_CHIP,
                    'quality' => $item['quality'] ?? null,
                    'type' => $item['type'] ?? null,
                    'needlevel' => $item['needlevel'] ?? null,
                    'power' => $item['power'] ?? null,
                ];
                break;
        }
        return $return;
    }

    /**
     * 主线关卡战斗（battle_model->battle_mission）
     *
     * 玩家 -1（没有阵法）/ -3（武将信息错误）时不战斗，status 为该值；-2（阵法里没有武将）按空队伍继续（直接判大败）。
     *
     * @param array<string, mixed> $mission Mission 行
     * @return array{status: int, info: array<string, mixed>}
     */
    private static function battleMission(int $uid, array $mission): array
    {
        $left = TeamBuilder::forMember($uid, pve: true);
        if ($left['status'] === -1 || $left['status'] === -3) {
            return ['status' => $left['status'], 'info' => []];
        }
        $right = TeamBuilder::forMonsters($mission, TeamBuilder::MONSTER_MISSION);
        $engine = new BattleEngine($left['matrix_type'], $left['matrix_coe']);
        return ['status' => 1, 'info' => $engine->battle($left['team'], $right['team'])];
    }

    /**
     * 胜利后添加掉落道具（action「添加道具」：get_number > 0 时按 gettype 发）
     *
     * 1 装备 / 2 装备碎片（itemtype 1）、3 银币 getvalue × 主公等级 × 件数、4 元宝 getvalue × 件数、
     * 5 天赋卷轴 / 6 卷轴碎片（itemtype 2，itemid 为卷轴类型）。
     *
     * @param array<string, mixed> $mission
     * @param array<string, mixed> $info    返回的 info（引用修改）
     */
    private static function missionDrop(int $uid, int $memberLevel, array $mission, array &$info): void
    {
        $getNumber = (int)$info['get_number'];
        $info['gettype'] = $mission['gettype'];
        switch ($mission['gettype']) {
            case 1:
            case 2:
                // 查询.添加道具
                $item = EquipTemplate::ROWS[$mission['getvalue']] ?? null;
                if ($item === null) {
                    break;
                }
                // itemtype 传2是卷轴，1是装备
                $info['itemtype'] = 1;
                $info['itemid'] = $item['id'];
                $info['quality'] = $item['quality'];
                $info['itemname'] = $item['name'];
                if ($mission['gettype'] === 2) {
                    // 添加碎片
                    Equipment::addChip($uid, $item['id'], $getNumber, 'map', 'action');
                    $info['itemname'] .= self::LANG_BASE_CHIP;
                } else {
                    // 添加道具
                    Equipment::addFromTemplate($uid, $item['id'], $getNumber);
                }
                break;
            case 3:
                // 增加银币
                $silver = $mission['getvalue'] * $memberLevel * $getNumber;
                Member::addCurrency($uid, silver: $silver);
                $info['getvalue'] = $silver;
                break;
            case 4:
                // 增加金币
                $gold = $mission['getvalue'] * $getNumber;
                Member::addCurrency($uid, gold: $gold);
                $info['getvalue'] = $gold;
                break;
            case 5:
            case 6:
                // 增加天赋卷轴
                $talent = TalentScroll::ROWS[$mission['getvalue']] ?? null;
                if ($talent === null) {
                    break;
                }
                $info['itemtype'] = 2;
                $info['itemid'] = $talent['type'];
                $info['quality'] = $talent['quality'];
                $info['itemname'] = Equipment::scrollName((int)$talent['type']);
                if ($mission['gettype'] === 5) {
                    Equipment::addTalentScroll($uid, $mission['getvalue'], 0, $getNumber, 'map', 'action');
                } else {
                    Equipment::addTalentScroll($uid, $mission['getvalue'], 1, $getNumber, 'map', 'action');
                    $info['itemname'] .= self::LANG_BASE_CHIP;
                }
                break;
        }
    }

    /**
     * 招募关卡武将（action「招募武将」：每次攻击掷 mt_rand(1, 1000) <= recruitrate，招到一次即停）
     *
     * 招到：add_general 成功时 generalstatus 1、generalinfo；7 星以上小喇叭 general_get；势力 2 以上聊天室系统消息
     * get_message_str(10)；酒馆日志 add_pub_log(gid, 1, 2)。没招到：add_pub_log(gid, 0, 0)（已击败可在酒馆招募）。
     *
     * @param array<string, mixed> $member
     * @param array<string, mixed> $mission
     * @param array<string, mixed> $info 返回的 info（引用修改）
     */
    private static function recruit(int $uid, array $member, int $level, array $mission, int $number, array &$info, int $now): void
    {
        for ($i = 0; $i < $number; $i++) {
            if (mt_rand(1, 1000) <= $mission['recruitrate']) {
                // 成功招募
                $general = General::addGeneral($uid, $mission['generalid']);
                if ($general !== null) {
                    $info['generalstatus'] = 1;
                    $info['generalinfo'] = $general;
                    $sender = Chat::sender($uid);
                    // 发送通知
                    $star = Chat::starWord((int)$general['star']);
                    if ($star !== null) {
                        Chat::sendToHorn(sprintf(self::LANG_GENERAL_GET, $member['nickname'], $star, $general['name']), $sender);
                    }
                    // 发送到聊天室
                    if ($level >= 2) {
                        $chat = Chat::getMessageStr(10, ['general' => $general['name']], $sender);
                        Chat::sendToAll($chat, Chat::TYPE_SYSTEM, 0, 0, $sender);
                    }
                }
                // 添加酒馆日志(武将id, 拥有状态, 获取方式)
                PubLog::add($uid, $mission['generalid'], 1, 2, $now);
                break;
            }
            PubLog::add($uid, $mission['generalid'], 0, 0, $now);
        }
    }

    /**
     * 主公升级奖励与功能提示（get_level_func）：加银币 等级 × 5000（levelReward），levelFunc 为 LevelFunc 表的提示
     *
     * @return array{levelReward: array<string, int>, levelFunc: array<string, mixed>}
     */
    private static function levelFunc(int $uid, int $level): array
    {
        // 增加银币奖励 = 玩家等级*5000
        $silver = $level * 5000;
        Member::addCurrency($uid, silver: $silver);
        $return = ['levelReward' => ['1' => $silver]];

        // 功能开启提示
        $levelFunc = [
            'all_silver' => (int)DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('silver'), // 当前玩家总银币数量
            'func_mark' => '', // 开启功能标识
            'func_name' => '', // 开启功能名称
            'func_level' => 0, // 开启功能等级
            'func_info' => self::LANG_GENERAL_LEVELUP, // 开启功能描述信息（如果没有功能开启，这里默认信息）
            'func_open' => 0, // 是否达到开启等级需求（1，达到；0，未达到；）
            'imageid' => 0, // 图片ID（奖励武将）
            'open' => ['mark' => '', 'name' => '', 'info' => ''], // 达到功能开启等级信息
        ];
        // 获取等级功能开启信息
        $levelFuncInfo = OpenMap::levelupFunc($level);
        if ($levelFuncInfo !== []) {
            // 功能开启提示
            if (!empty($levelFuncInfo['nextLevel'])) {
                $levelFunc['func_mark'] = $levelFuncInfo['nextLevel']['func_mark'];
                $levelFunc['func_name'] = $levelFuncInfo['nextLevel']['func_name'];
                $levelFunc['func_info'] = $levelFuncInfo['nextLevel']['func_info'];
                $levelFunc['func_level'] = $levelFuncInfo['nextLevel']['level'];
            }
            // 达到等级开启功能提示
            if (!empty($levelFuncInfo['openLevel'])) {
                $levelFunc['func_open'] = 1;
                $levelFunc['func_mark'] = $levelFunc['open']['mark'] = $levelFuncInfo['openLevel']['func_mark'];
                $levelFunc['func_name'] = $levelFunc['open']['name'] = $levelFuncInfo['openLevel']['func_name'];
                $levelFunc['func_info'] = $levelFunc['open']['info'] = $levelFuncInfo['openLevel']['func_info'];
                $levelFunc['func_level'] = $levelFuncInfo['openLevel']['level'];
            }
            if ($level > 20 && $level <= 30) {
                $levelFunc['func_open'] = 0;
                $levelFunc['imageid'] = 5;
            }
        }
        $return['levelFunc'] = $levelFunc;
        return $return;
    }

    /**
     * 新手引导战报（battlereport_model->get_report）：该关卡有预制战报时返回预制战报，并按本次结算覆盖字段；否则 null
     *
     * 预制战报解码成对象（空对象 rightresult 等原样输出 {}），只改旧版改的字段。
     *
     * @param array<string, mixed> $info
     * @param array<string, mixed> $leftRole
     * @param array<string, mixed> $rightRole
     * @return array<string, mixed>|null
     */
    private static function newbieReport(int $id, array $info, array $leftRole, array $rightRole): ?array
    {
        if (!isset(NewbieBattleReport::REPORTS[$id])) {
            return null;
        }
        $return = (array)json_decode(NewbieBattleReport::REPORTS[$id]);
        $i = $return['info'];
        $i->win = $info['win'];
        $i->exp = $info['exp'];
        $i->gexp = $info['gexp'];
        $i->background = $info['background'];
        $i->openstatus = $info['openstatus'];
        $i->gettype = $info['gettype'];
        $i->getvalue = $info['getvalue'];
        $i->itemid = $info['itemid'];
        $i->itemname = $info['itemname'];
        $i->quality = $info['quality'] ?? 0;
        $i->type = $info['type'];
        $i->itemtype = $info['itemtype'] ?? 0;
        $i->levelup = $info['levelup'];
        $i->add_type = $info['add_type'];
        $i->levelupexp = $info['levelupexp'];
        $i->image = $info['image'];
        $i->generalstatus = $info['generalstatus'];
        $i->get_level = $info['get_level'];
        $i->report = $info['report'];
        $i->generalinfo = $info['generalinfo'];
        $i->levelexp = $info['levelexp'] ?? null;
        $i->get_number = $info['get_number'] ?? null;
        $i->first = $info['first'];
        $i->name = $info['name'];
        $i->levelReward = $info['levelReward'] ?? null;
        $i->levelFunc = $info['levelFunc'] ?? null;
        $b = $return['battle'];
        $b->assess = $info['win'];
        $b->left_role->name = $leftRole['name'];
        $b->left_role->level = $leftRole['level'];
        $b->left_role->gender = $leftRole['gender'];
        $b->right_role->name = $rightRole['name'];
        $b->right_role->level = $rightRole['level'];
        $b->right_role->type = $rightRole['type'];
        return $return;
    }

    /**
     * 闯关竞速列表（MissionReward 行 + image 关卡头像 + open_status：0 可领取、1 未通过、2 已领取），未排序
     *
     * @return list<array<string, mixed>>
     */
    private static function raceList(int $uid): array
    {
        // 取通过最大的关卡id
        $id = OpenMap::missionMax($uid);
        // 查询奖励表
        $list = OpenMap::rewardList()['list'];
        // 通过id得到关卡的头像
        $missionlist = OpenMap::missionInfo(array_column($list, 'missionid'), ['id', 'image']);
        // 查询领取过了的关卡奖励
        $missionid = OpenMap::rewardLog($uid)['missionid'];
        foreach ($list as $key => $val) {
            $list[$key]['image'] = $missionlist[$val['missionid']]['image'] ?? null;
            $list[$key]['open_status'] = 1;
            if ($val['missionid'] <= $id['id']) {
                $list[$key]['open_status'] = 0;
            }
            if (in_array($val['missionid'], (array)$missionid, true)) {
                $list[$key]['open_status'] = 2;
            }
        }
        return $list;
    }

    /**
     * 刷 5 / 10 / 50 / 100 次需要的元宝（剩余免费次数够时为 0）
     *
     * @return array{five_sub: int, ten_sub: int, fifty_sub: int, hundred_sub: int}
     */
    private static function sweepPrice(int $getTimesSup, int $nowMaxtimes): array
    {
        $return = [];
        foreach (['five_sub' => 5, 'ten_sub' => 10, 'fifty_sub' => 50, 'hundred_sub' => 100] as $key => $times) {
            $return[$key] = $times - $nowMaxtimes < 0 ? 0 : OpenMap::priceByTimes($getTimesSup, $times - $nowMaxtimes);
        }
        return $return;
    }

    /**
     * 战斗 CD（cd_model->get_attack：member_field.attackstatus / attackdateline）
     *
     * @return array{status: int, cd: int}
     */
    private static function getAttack(int $uid, int $now): array
    {
        $row = DB::table('member_field')->select('attackstatus', 'attackdateline')->where('uid', $uid)->whereNull('deleted_at')->first();
        if ($row === null) {
            return ['status' => 0, 'cd' => 0];
        }
        return ['status' => (int)$row['attackstatus'], 'cd' => max(0, (int)$row['attackdateline'] - $now)];
    }

    /**
     * 修改推图位置（member_model->update_missionsite）：势力 / 场景 / 世界 / 首次进入的世界为 0 时不改
     */
    private static function updateMissionsite(int $uid, int $site, int $level = 0, int $stage = 0, int $world = 0, int $first = 0): void
    {
        $data = ['missionsite' => $site];
        if ($level !== 0) {
            $data['missionlevel'] = $level;
        }
        if ($stage !== 0) {
            $data['missionstage'] = $stage;
        }
        if ($world !== 0) {
            $data['missionworld'] = $world;
        }
        if ($first !== 0) {
            $data['firstworld'] = $first;
        }
        DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->update($data);
        Redis::del('player:' . $uid);
    }

    /**
     * 修改通关奖励记录（member_model->update_newresult：member_field.newresult）
     */
    private static function updateFieldNewresult(int $uid, int $mapId): void
    {
        DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->update(['newresult' => $mapId]);
        Redis::del('player:' . $uid);
    }

    /**
     * 首次进入游戏时间（member_field.start_time）
     */
    private static function startTime(int $uid): int
    {
        return (int)(DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->value('start_time') ?? 0);
    }

    /**
     * 整军活动（activity_status('exercise')）：开启时每日刷怪次数翻倍
     */
    private static function exerciseOpen(int $now): bool
    {
        return Pay::activity('exercise', $now) !== null;
    }

    /**
     * 双倍关卡奖励活动（activity_status('double_miss')）
     */
    private static function doubleOpen(int $now): bool
    {
        return Pay::activity('double_miss', $now) !== null;
    }

    /**
     * 旧版 PHP 5 输出浮点数（如 600 × 0.8 = 480）
     */
    private static function number(float $value): string
    {
        return (string)(float)sprintf('%.14G', $value);
    }

    /**
     * @return array{status: int, msg: string}
     */
    private static function rewardError(): array
    {
        return ['status' => -1, 'msg' => self::LANG_MAP_REWARD_ERROR];
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

    private static function intInput(Context $ctx, string $key): int
    {
        $value = $ctx->input($key);
        return is_scalar($value) ? (int)$value : 0;
    }
}
