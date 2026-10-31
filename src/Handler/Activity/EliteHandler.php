<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Battle\BattleRush;
use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\Pvp\CountryHandler;
use Hanjiang\MasterData\EliteCity;
use Hanjiang\MasterData\EliteMission;
use Hanjiang\MasterData\ElitePrice;
use Hanjiang\MasterData\Equip;
use Hanjiang\MasterData\Mission;
use Hanjiang\MasterData\Price;
use Hanjiang\MasterData\TalentScroll;
use Hanjiang\Model\Equipment;
use Hanjiang\Model\Member;
use Hanjiang\Model\MemberActivity;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use Workerman\Protocols\Http\Response as WorkermanResponse;

/**
 * 多人副本 / 团队副本（移植旧版 controllers/elite.php + models/elite_model.php，路径相对旧版 s1/application/）
 *
 * 配置：军团（含奖励）EliteCity、关卡 EliteMission（按 city 归属军团，position1~9 → 怪物 EliteMonster，
 * 关卡 id 与主线 Mission.id 对应，用于判断军团是否开启）、至尊组队 ElitePrice、购买次数价格 Price（elite 列）、
 * 奖励模板 Equip / TalentScroll。
 * 玩家存储：member_activity（elitetimes 今日免费次数、elitesurplustimes 购买的剩余次数、elitebuytimes 今日购买次数、
 * elitebuydateline 最后购买时间）、elite_mission_log（军团首杀记录）、elite_log（战斗记录，也用于 10 秒内重复开战限制）。
 *
 * 旧版是实时组队玩法：客户端 elitepop.js 用 io.connect 连 c=elite&m=get_server 返回的实时服务器，组队 / 队伍列表 /
 * 开战都走实时服务器；index、get_member_info、join_team、get_country_info、action 由实时服务器代为调用
 * （客户端没有直接调用），客户端直接调用的只有 get_server、buy_number、sendinvite。旧版实时服务器不在旧版代码包里，
 * 新版实时组队层是 Hanjiang\WebSocket\EliteRoom（按 elitepop.js 的事件还原），它直接调用这里的 memberInfo / fight 等静态方法；
 * HTTP 的 action 不再开放（见 action 注释）。
 *
 * 依赖未移植：
 * - 春节送福活动 actnewyear_model（_init 读活动状态；action 里 add_task_num(…, 24, uid) 计数）；
 * - 主线任务 mainquest_model->task_action(10, 1, uid)（参加一次多人副本）；
 * - 聊天室邀请广播 chat_model->get_message_str(11, …) / send_message_to_all(…, 7)（sendinvite 返回 0）；
 * - 消费统计 stat_model->elite_times；member_model->sub_gold 的 gold_log 元宝流水、日常任务 task_model->task_action(5)、
 *   四方来朝元宝计数；
 * - 征战进度：旧版 map_model->get_mission_max 读 zc_sanguo_open_map（未移植），这里按新版 zc_sanguo_map 推算。
 */
final class EliteHandler
{
    /** 旧版语言包 language/chinese_s/base_lang.php */
    private const string LANG_BASE_CHIP = '碎片';
    private const string LANG_BASE_SCROLL = '天赋卷轴';

    /** 碎片 / 卷轴流水的执行类与方法（旧版开战接口 c=elite&m=action） */
    private const string LOG_CLASS = 'elite';
    private const string LOG_METHOD = 'action';

    /** 旧版语言包 language/chinese_s/equipment_lang.php（equipment_model->get_scroll_name：卷轴类型 → 名称） */
    public const array SCROLL_NAME = [1 => '物攻', 2 => '物防', 3 => '策攻', 4 => '策防', 5 => '生命'];

    /** 至尊组队队长 VIP 等级要求 */
    private const int TOPTEAM_VIP = 8;

    /** 同一队长两次开战的最短间隔秒数（elite_model->get_battlelog_by_time） */
    private const int BATTLE_INTERVAL = 10;

    /** 旧版 zc_sanguo_elite_log.member / reward 的列宽（旧版库非严格模式下超长截断） */
    private const int LOG_MEMBER_LEN = 20;
    private const int LOG_REWARD_LEN = 255;

    /**
     * 获取副本列表: c=elite&m=index（旧版由实时服务器调用）
     */
    public static function index(Context $ctx): array
    {
        $return = ['status' => 0];
        $list = self::rewardList();
        $return['status'] = 1;
        $return['list'] = $list;
        return $return;
    }

    /**
     * 军团列表及奖励说明（index 的列表；实时组队层的 teamlist 也带上当前军团这一行）
     *
     * @return list<array<string, mixed>>
     */
    public static function rewardList(): array
    {
        // 获取军团列表
        $list = self::getCityList();
        foreach ($list as $key => $val) {
            if ($val['get_rate'] > 0 && $val['get_rate'] <= 50) {
                $list[$key]['getrate'] = 1;
            } elseif ($val['get_rate'] > 50 && $val['get_rate'] < 100) {
                $list[$key]['getrate'] = 2;
            } else {
                $list[$key]['getrate'] = 1;
            }
        }
        foreach ($list as $key => $val) {
            if ($val['type'] == 1 || $val['type'] == 2) {
                $item = Equip::ROWS[$val['get_value']] ?? null;
                $list[$key]['itemname'] = $item['name'] ?? null;
                $list[$key]['quality'] = $item['quality'] ?? null;
                if ($val['type'] == 2) {
                    $list[$key]['itemname'] .= self::LANG_BASE_CHIP;
                }
            } elseif ($val['type'] == 3 || $val['type'] == 4) {
                $scroll = TalentScroll::ROWS[$val['get_value']] ?? null;
                $list[$key]['itemname'] = self::scrollName($scroll['type'] ?? null);
                $list[$key]['quality'] = $scroll['quality'] ?? null;
                $list[$key]['itemname'] .= self::LANG_BASE_SCROLL;
                if ($val['type'] == 4) {
                    $list[$key]['itemname'] .= self::LANG_BASE_CHIP;
                }
            }
            // 首杀奖励信息
            if ($val['first_type'] == 1 || $val['first_type'] == 2) {
                $item = Equip::ROWS[$val['first_value']] ?? null;
                $list[$key]['firstitemname'] = $item['name'] ?? null;
                $list[$key]['firstquality'] = $item['quality'] ?? null;
                if ($val['first_type'] == 2) {
                    $list[$key]['firstitemname'] .= self::LANG_BASE_CHIP;
                }
            } elseif ($val['first_type'] == 3 || $val['first_type'] == 4) {
                $scroll = TalentScroll::ROWS[$val['first_value']] ?? null;
                $list[$key]['firstitemname'] = self::scrollName($scroll['type'] ?? null);
                $list[$key]['firstquality'] = $scroll['quality'] ?? null;
                $list[$key]['firstitemname'] .= self::LANG_BASE_SCROLL;
                if ($val['first_type'] == 4) {
                    $list[$key]['firstitemname'] .= self::LANG_BASE_CHIP;
                }
            }
        }
        return $list;
    }

    /**
     * 获取用户信息: c=elite&m=get_member_info（旧版由实时服务器调用）
     */
    public static function get_member_info(Context $ctx): array
    {
        return self::memberInfo(MemberHandler::resolveUid($ctx));
    }

    /**
     * get_member_info 的核心（实时组队层 eliteindex 直接调用）
     *
     * @return array<string, mixed>
     */
    public static function memberInfo(int $uid): array
    {
        $return = ['status' => 0];

        // 判断用户信息
        $member = self::member($uid);
        if ($member === null) {
            return $return;
        }
        $uid = (int)$member['uid'];

        // 获取军团列表
        $list = self::getCityList();
        // 取通过的最大军团
        $maxCity = self::getMaxCity(self::getMissionMax($uid));
        if ($list === []) {
            $return['status'] = -1;
            return $return;
        }
        $id = [];
        $newlist = [];
        foreach ($list as $val) {
            // 军团的id
            $id[] = $val['id'];
            $newlist[$val['id']]['open'] = $val['id'] > $maxCity ? 0 : 1;
        }

        $missionid = [];
        $result = self::getMissionLogs($uid, $id);
        if ($result !== []) {
            foreach ($result as $val) {
                // 找出所有的已过关卡
                $missionid[] = $val['missionid'];
                $newlist[$val['missionid']]['firstkill'] = 0;
            }
            // 找到所有没打过的关卡
            foreach (array_diff($id, $missionid) as $val) {
                $newlist[$val]['firstkill'] = 1;
            }
        } else {
            foreach ($newlist as $key => $val) {
                // 无任何记录说明为首杀
                $newlist[$key]['firstkill'] = 1;
            }
        }

        // 查询用户信息(剩余次数)
        $info = self::getActivityInfo($uid);
        $return['status'] = 1;

        // 如果加入国家，返回国家名称
        if ((int)$member['country'] > 0) {
            $country = CountryHandler::countryInfo((int)$member['country']);
            $return['countryname'] = $country['name'] ?? null;
        } else {
            $return['countryname'] = 0;
        }

        // 返回至尊组队价格和加成
        $return['supper_group'] = self::supperGroup();
        $return['number'] = $info['elitetimes'] + $info['elitesurplustimes'];
        $return['list'] = $newlist;
        return $return;
    }

    /**
     * 加入国家队伍，验证是否是属于同一个国家: c=elite&m=join_team（旧版由实时服务器调用；country 队伍所属国家）
     *
     * 旧版直接输出整数 1（同一国家）/ 0，不是数组。
     */
    public static function join_team(Context $ctx): WorkermanResponse
    {
        $return = 0;
        $country = $ctx->input('country');
        // 取用户的国家信息
        $member = self::member(MemberHandler::resolveUid($ctx));
        $myCountry = CountryHandler::countryInfo((int)($member['country'] ?? 0));
        // 判断是否是在同一个国家（旧版松散比较：没有国家时 id 为 null）
        if (($myCountry['id'] ?? null) == $country) {
            $return = 1;
        }
        return self::rawJson($return);
    }

    /**
     * 取国家信息（创建国家队伍时用）: c=elite&m=get_country_info（旧版由实时服务器调用）
     *
     * 旧版直接输出国家行（没有国家时为空数组），不带 status。
     */
    public static function get_country_info(Context $ctx): WorkermanResponse
    {
        $member = self::member(MemberHandler::resolveUid($ctx));
        return self::rawJson(CountryHandler::countryInfo((int)($member['country'] ?? 0)) ?? []);
    }

    /**
     * 获取socket服务器地址: c=elite&m=get_server
     *
     * 旧版：12:00~14:00、19:00~21:00 开放，返回 config.php 的 socket_server_url（新版 config/app.php 同名配置）。
     */
    public static function get_server(Context $ctx): array
    {
        // 判断多人副本开启时间
        if (!self::isOpen(time())) {
            return ['status' => -1];
        }

        $appConfig = require dirname(__DIR__, 3) . '/config/app.php';
        return [
            'status' => 1,
            'url' => $appConfig['socket_server_url'],
        ];
    }

    /**
     * 开始战斗: c=elite&m=action —— 只供实时组队层内部调用，HTTP 一律拒绝
     *
     * 旧版由实时服务器在队长开战时以 HTTP 调用，参数（fid / teamid / topteam / leader / members）全由调用方给出，
     * 任何玩家都能拿自己的 token 直接调用、替别人开战扣次数。新版开战只走 WebSocket 的 startfight
     * （EliteRoom 按服务端的队伍状态调用 fight）。旧版没有「不允许调用」的状态码，这里按旧版第一个失败分支
     * （用户信息无效）的返回 {status: 0, info: []}。
     */
    public static function action(Context $ctx): array
    {
        return ['status' => 0, 'info' => []];
    }

    /**
     * 开始战斗的核心（旧版 action 的全部流程；实时组队层 startfight 调用，selfUid 为队长）
     *
     * id 军团id，teamid 队伍id（原样返回），teamType 1 为至尊组队，leader 队长uid，members 队员uid（逗号分隔，按出场顺序）
     *
     * @return array<string, mixed>
     */
    public static function fight(int $selfUid, int $id, int $teamid, int $teamType, int $leader, string $members): array
    {
        $return = ['status' => 0, 'info' => []];

        // 判断用户信息
        $self = self::member($selfUid);
        if ($self === null) {
            return $return;
        }
        $selfUid = (int)$self['uid'];

        if (!$leader || !$members || !$id) {
            $return['status'] = -1;
            return $return;
        }

        $team = array_unique(explode(',', $members));
        if (count($team) == 1) {
            // 自己不能和自己组队
            $return['status'] = 0;
            return $return;
        }
        $now = time();
        // 判断上次请求时间间隔是否太短
        if (self::battleLogByTime($leader, $now)) {
            $return['status'] = -8;
            return $return;
        }
        // 判断3个人的次数
        $activity = MemberActivity::many(array_map('intval', array_values($team)), ['elitetimes', 'elitesurplustimes']);
        foreach ($team as $val) {
            // 判断次数
            if (!isset($activity[$val]) || $activity[$val]['elitetimes'] + $activity[$val]['elitesurplustimes'] < 1) {
                $return['status'] = -2;
                $return['uid'] = $val;
                return $return;
            }
            // 判断该关卡是否开启（get_mission_max 的 uid 为 0 时取当前玩家）
            $missionUid = (int)$val === 0 ? $selfUid : (int)$val;
            if ($id > self::getMaxCity(self::getMissionMax($missionUid))) {
                $return['status'] = -4;
                return $return;
            }
        }

        $supperGroup = self::supperGroup();
        // 至尊组队，判断金币是否足够,判断队长是否满足vip条件
        if ($teamType == 1) {
            $memberInfo = self::member($leader);
            if (($memberInfo['vip'] ?? null) < self::TOPTEAM_VIP) {
                $return['status'] = -7;
                return $return;
            }
            if ($memberInfo['gold'] < $supperGroup['gold']) {
                $return['status'] = -6;
                return $return;
            }
        }

        // 按id取军团信息
        $city = EliteCity::ROWS[$id] ?? null;
        if ($city === null) {
            $return['status'] = -3;
            return $return;
        }

        // 取关卡信息
        $mission = self::getMissionById($id);
        if ($mission === []) {
            $return['status'] = -5;
            return $return;
        }

        $attackName = $defenseName = [];
        // 得到攻击方姓名
        $nicknames = DB::table('member')->select('uid', 'nickname')
            ->whereIn('uid', array_map('intval', array_values($team)))->whereNull('deleted_at')->orderBy('uid')->get();
        foreach ($nicknames as $val) {
            $attackName[(int)$val['uid']] = $val['nickname'];
        }
        // 得到怪物名称
        foreach ($mission as $val) {
            $defenseName[$val['id']] = $val['name'];
        }
        // 调用战斗算法
        $battle = (new BattleRush($selfUid))->run(array_values($team), $mission, 2);
        // 胜利处理
        if ($battle['win'] == 1) {
            foreach ($battle['general_arr'] as $key => $val) {
                if ($key == $leader && $teamType == 1) {
                    // 是队长，并且是至尊组队，加300%
                    $exp = $city['exp'] / 100 * $supperGroup['caption'];
                    $firstExp = $city['first_exp'] / 100 * $supperGroup['caption'];
                } elseif ($teamType == 1) {
                    // 不是队长，但是是至尊组队，加200%
                    $exp = $city['exp'] / 100 * $supperGroup['player'];
                    $firstExp = $city['first_exp'] / 100 * $supperGroup['player'];
                } else {
                    // 普通队伍，只加普通经验
                    $exp = $city['exp'];
                    $firstExp = $city['first_exp'];
                }
                $key = (int)$key;
                // 计算队伍的信息
                $info = self::getMissionLog($key, $id);

                $return['info'][$key] = [
                    'exp' => self::legacyNumber($exp),
                    'first_exp' => self::legacyNumber($firstExp),
                    'itemid' => 0,
                ];
                if ($info === null) {
                    // 添加通关记录--首杀
                    self::addMissionLog($id, $key, $now);
                    foreach ($val as $smallVal) {
                        // 加武将经验（add_exp 内 intval）
                        self::addGeneralExp($key, $smallVal, (int)$firstExp);
                    }
                    $return['info'][$key]['firstkill'] = 1;
                    // 首杀奖励信息
                    if ($city['first_type'] == 1 || $city['first_type'] == 2) {
                        // 查询.添加道具
                        $item = Equip::ROWS[$city['first_value']] ?? null;
                        if ($item !== null) {
                            // 道具存在：itemtype 传2是卷轴，1是装备
                            $return['info'][$key]['firstitemtype'] = 1;
                            $return['info'][$key]['firstitemid'] = $item['id'];
                            $return['info'][$key]['firstquality'] = $item['quality'];
                            $return['info'][$key]['firstitemname'] = $item['name'];
                            if ($city['first_type'] == 2) {
                                // 添加碎片
                                Equipment::addChip($key, $item['id'], 1, self::LOG_CLASS, self::LOG_METHOD);
                                $return['info'][$key]['firstitemname'] .= self::LANG_BASE_CHIP;
                            } else {
                                // 添加道具
                                Equipment::addFromTemplate($key, $item['id']);
                            }
                        }
                    } elseif ($city['first_type'] == 3 || $city['first_type'] == 4) {
                        // 旧版首杀分支不判断卷轴模板是否存在
                        $talent = TalentScroll::ROWS[$city['first_value']] ?? null;
                        $return['info'][$key]['firstitemtype'] = 2;
                        $return['info'][$key]['firstitemname'] = self::scrollName($talent['type'] ?? null);
                        $return['info'][$key]['firstitemid'] = $talent['type'] ?? null;
                        $return['info'][$key]['firstquality'] = $talent['quality'] ?? null;
                        $return['info'][$key]['firstitemname'] .= self::LANG_BASE_SCROLL;
                        Equipment::addTalentScroll($key, (int)$city['first_value'], $city['first_type'] == 3 ? 0 : 1, 1, self::LOG_CLASS, self::LOG_METHOD);
                        if ($city['first_type'] == 4) {
                            $return['info'][$key]['firstitemname'] .= self::LANG_BASE_CHIP;
                        }
                    }
                } else {
                    // 循环增加道具，每个人都要经过概率计算
                    foreach ($val as $smallVal) {
                        // 加武将经验（add_exp 内 intval）
                        self::addGeneralExp($key, $smallVal, (int)$exp);
                    }
                    $rands = mt_rand(1, 100);
                    if ($rands <= $city['get_rate']) {
                        // 奖励道具
                        if ($city['type'] == 1 || $city['type'] == 2) {
                            $item = Equip::ROWS[$city['get_value']] ?? null;
                            if ($item !== null) {
                                // 道具存在：itemtype 传2是卷轴，1是装备
                                $return['info'][$key]['itemtype'] = 1;
                                $return['info'][$key]['itemid'] = $item['id'];
                                $return['info'][$key]['quality'] = $item['quality'];
                                $return['info'][$key]['itemname'] = $item['name'];
                                if ($city['type'] == 2) {
                                    // 添加碎片
                                    Equipment::addChip($key, $item['id'], 1, self::LOG_CLASS, self::LOG_METHOD);
                                    $return['info'][$key]['itemname'] .= self::LANG_BASE_CHIP;
                                } else {
                                    // 添加道具
                                    Equipment::addFromTemplate($key, $item['id']);
                                }
                            }
                        } elseif ($city['type'] == 3 || $city['type'] == 4) {
                            // 增加天赋卷轴
                            $talent = TalentScroll::ROWS[$city['get_value']] ?? null;
                            if ($talent !== null) {
                                $return['info'][$key]['itemtype'] = 2;
                                $return['info'][$key]['itemid'] = $talent['type'];
                                $return['info'][$key]['quality'] = $talent['quality'];
                                $return['info'][$key]['itemname'] = self::scrollName($talent['type']) . self::LANG_BASE_SCROLL;
                                Equipment::addTalentScroll($key, (int)$city['get_value'], $city['type'] == 3 ? 0 : 1, 1, self::LOG_CLASS, self::LOG_METHOD);
                                if ($city['type'] == 4) {
                                    $return['info'][$key]['itemname'] .= self::LANG_BASE_CHIP;
                                }
                            }
                        }
                    }
                }
            }
        }
        // 减少次数
        $reward = $times = [];
        foreach ($team as $val) {
            $number = $activity[$val]['elitetimes'] + $activity[$val]['elitesurplustimes'];
            if ($activity[$val]['elitetimes'] > 0) {
                $info = ['elitetimes' => $activity[$val]['elitetimes'] - 1];
            } else {
                $info = ['elitesurplustimes' => $activity[$val]['elitesurplustimes'] - 1];
            }
            $number -= 1;

            if ($battle['win'] == 1) {
                $reward[$val] = $return['info'][$val] ?? null;
            }
            $times[$val] = $number;
            $return['info'][$val]['number'] = $number;
            MemberActivity::update((int)$val, $info);
        }

        // 至尊组队，扣除队长金币（旧版 sub_gold 元宝不足时不扣、不报错）
        if ($teamType == 1) {
            Member::deductCurrency($leader, gold: (int)$supperGroup['gold']);
        }

        // 存储战斗记录
        // 旧版库非严格模式：member varchar(20) / reward varchar(255) 超长截断；times 是 int 列，写入的 json 字符串存成 0
        DB::table('elite_log')->insert([
            'fid' => $id,
            'leader' => $leader,
            'member' => substr($members, 0, self::LOG_MEMBER_LEN),
            'topteam' => $teamType,
            'reward' => substr((string)json_encode($reward), 0, self::LOG_REWARD_LEN),
            'times' => 0,
            'dateline' => $now,
        ]);

        // 依赖未移植：主线任务 mainquest_model->task_action(10, 1, uid)（参加一次多人副本，无论胜负）
        // 依赖未移植：春节送福活动 actnewyear_model->add_task_num(version, 24, uid)

        // 成功返回
        $return['status'] = 1;
        $return['info']['win'] = $battle['win'];
        $return['info']['teamid'] = $teamid;
        unset($battle['general_arr']);
        $return['battle'] = $battle;
        $return['battle']['attack_name'] = $attackName;
        $return['battle']['defense_name'] = $defenseName;
        return $return;
    }

    /**
     * 次数购买: c=elite&m=buy_number（t 为 1 时购买，否则只查价格）
     */
    public static function buy_number(Context $ctx): array
    {
        $return = ['status' => 0];

        $type = $ctx->input('t');

        // 判断用户信息
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        $uid = (int)$member['uid'];
        $now = time();

        // 查询今日购买次数
        $activity = self::getActivityInfo($uid);
        if ($activity['elitebuydateline'] && date('ymd', $activity['elitebuydateline']) == date('ymd', $now)) {
            $number = $activity['elitebuytimes'];
        } else {
            $number = 0;
        }
        // 得到购买需要的价格
        $price = self::getBuyPrice($number + 1);

        $return['status'] = 1;
        $return['price'] = $price;
        $return['times'] = $activity['elitesurplustimes'] + $activity['elitetimes'];
        if ($type == 1) {
            // 判断金币是否足够
            // 旧版先改次数再 sub_gold；新版先原子扣元宝（并发下余额不足同样返回 -1），扣成功再改次数
            if ((int)$member['gold'] < $price || !Member::deductCurrency($uid, gold: $price)) {
                $return['status'] = -1;
                return $return;
            }
            // 修改次数,添加购买记录
            $info = [
                'elitesurplustimes' => $activity['elitesurplustimes'] + 1,
                'elitebuytimes' => $number + 1,
                'elitebuydateline' => $now,
            ];
            MemberActivity::update($uid, $info);

            // 取下一次购买花费
            $price = self::getBuyPrice($number + 2);
            $return['price'] = $price;

            // 依赖未移植：消费统计 stat_model->elite_times

            // 返回剩余金币和次数
            $return['all_gold'] = (int)DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('gold');
            $return['times'] = $info['elitesurplustimes'] + $activity['elitetimes'];
        }
        return $return;
    }

    /**
     * 邀请玩家加入队伍: c=elite&m=sendinvite（fid 军团id，teamid 队伍id，countryid 国家队伍所属国家）
     */
    public static function sendinvite(Context $ctx): array
    {
        $return = ['status' => 0];
        $id = self::intInput($ctx, 'fid');
        $teamid = self::intInput($ctx, 'teamid');
        if (!$id || !$teamid) {
            // 旧版参数缺失时返回 1
            $return['status'] = 1;
            return $return;
        }

        // 判断用户信息
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }

        // 按id取军团信息
        if (!isset(EliteCity::ROWS[$id])) {
            $return['status'] = -2;
            return $return;
        }

        // 取通过的最大军团
        if ($id > self::getMaxCity(self::getMissionMax((int)$member['uid']))) {
            $return['status'] = -2;
            return $return;
        }

        // 依赖未移植：chat_model->get_message_str(11, ['elite' => "fid|teamid|countryid", 'general' => 军团名])
        // + send_message_to_all(…, 7) 聊天室组队邀请广播。聊天室未移植前广播发不出去，
        // 按开发者要求的同类做法返回 0（失败），客户端提示「邀请失败」。聊天室移植后恢复旧版流程（成功返回 1）。
        return $return;
    }

    /**
     * 多人副本开放时间（旧版控制器构造函数：date('Hi') 在 1200~1400 或 1900~2100）
     */
    public static function isOpen(int $now): bool
    {
        $nowTime = (int)date('Hi', $now);
        return ($nowTime >= 1200 && $nowTime <= 1400) || ($nowTime >= 1900 && $nowTime <= 2100);
    }

    /**
     * 军团存在且该玩家已开启（action / sendinvite 的「取通过的最大军团」判断）
     */
    public static function cityOpen(int $uid, int $id): bool
    {
        return isset(EliteCity::ROWS[$id]) && $id <= self::getMaxCity(self::getMissionMax($uid));
    }

    /**
     * 该军团对玩家是否还是首杀（get_member_info 的 firstkill：没有首杀记录为 1）
     */
    public static function firstKill(int $uid, int $id): int
    {
        return self::getMissionLog($uid, $id) === null ? 1 : 0;
    }

    /**
     * 至尊组队价格和加成（elite_model->get_topteam_price：elite_price 第一行）
     *
     * @return array<string, int>
     */
    public static function supperGroup(): array
    {
        $rows = ElitePrice::ROWS;
        ksort($rows);
        $row = reset($rows);
        return $row === false ? [] : $row;
    }

    /**
     * 军团列表（elite_model->get_city_list()：全部行，主键顺序）
     *
     * @return list<array<string, mixed>>
     */
    private static function getCityList(): array
    {
        $list = EliteCity::ROWS;
        ksort($list);
        return array_values($list);
    }

    /**
     * 军团下的关卡（elite_model->get_mission_by_id：city = id ORDER BY id）
     *
     * @return list<array<string, mixed>>
     */
    private static function getMissionById(int $id): array
    {
        $return = [];
        foreach (EliteMission::ROWS as $row) {
            if ($row['city'] === $id) {
                $return[$row['id']] = $row;
            }
        }
        ksort($return);
        return array_values($return);
    }

    /**
     * 开启的最大军团（elite_model->get_max_city）
     *
     * 取 id <= 主线最大关卡id 的最后一个团队副本关卡；若该关卡所在军团还有更大 id 的关卡，说明该军团没打通，取前一个军团。
     */
    private static function getMaxCity(int $id): int
    {
        $max = null;
        foreach (EliteMission::ROWS as $row) {
            if ($row['id'] <= $id && ($max === null || $row['id'] > $max['id'])) {
                $max = $row;
            }
        }
        if ($max === null) {
            return 0;
        }
        foreach (EliteMission::ROWS as $row) {
            if ($row['city'] === $max['city'] && $row['id'] > $id) {
                return $max['city'] - 1;
            }
        }
        return $max['city'];
    }

    /**
     * 主线已通关的最大关卡 id（map_model->get_mission_max 的 id）
     *
     * 旧版读 zc_sanguo_open_map（每势力一行：stage 最大场景、mission 该场景已过关卡）取 level 最大的一行，
     * mission 为 0 时按 1，再按 (level, stage, mission) 查 zc_sanguo_mission 的 id；查不到时取 mission 表最后一关；
     * 没有记录为 0。依赖未移植：open_map，新版征战进度在 zc_sanguo_map（每势力每场景一行，mission 为该场景已通关据点），
     * 这里取 level、stage 最大的一行代替。
     */
    private static function getMissionMax(int $uid): int
    {
        $row = DB::table('map')->where('uid', $uid)->whereNull('deleted_at')
            ->orderBy('level', 'DESC')->orderBy('stage', 'DESC')->first();
        if ($row === null) {
            return 0;
        }
        $mission = (int)$row['mission'];
        if ($mission === 0) {
            $mission = 1;
        }
        $last = null;
        foreach (Mission::ROWS as $value) {
            if ($value['missionlevel'] === (int)$row['level'] && $value['missionstage'] === (int)$row['stage'] && $value['missionid'] === $mission) {
                return $value['id'];
            }
            if ($last === null || [$value['missionlevel'], $value['missionstage'], $value['missionid']] > [$last['missionlevel'], $last['missionstage'], $last['missionid']]) {
                $last = $value;
            }
        }
        // 如果没有查询到关卡信息，就取最大的关卡信息
        return $last['id'] ?? 0;
    }

    /**
     * 玩家多个军团的首杀记录（elite_model->get_mission_log($uid, $ids, 2)）
     *
     * @param list<int> $ids
     * @return list<array<string, int>>
     */
    private static function getMissionLogs(int $uid, array $ids): array
    {
        $rows = DB::table('elite_mission_log')->select('id', 'missionid', 'uid', 'dateline', 'first_reward')
            ->whereIn('missionid', $ids)->where('uid', $uid)->whereNull('deleted_at')->orderBy('id')->get();
        return array_map(static fn (array $row): array => array_map('intval', $row), $rows);
    }

    /**
     * 玩家某军团的首杀记录（elite_model->get_mission_log($uid, $id)）
     *
     * @return array<string, mixed>|null
     */
    private static function getMissionLog(int $uid, int $id): ?array
    {
        return DB::table('elite_mission_log')->where('missionid', $id)->where('uid', $uid)->whereNull('deleted_at')->first();
    }

    /**
     * 添加军团首杀记录（elite_model->add_mission_log）
     */
    private static function addMissionLog(int $id, int $uid, int $now): void
    {
        DB::table('elite_mission_log')->insert([
            'missionid' => $id,
            'uid' => $uid,
            'dateline' => $now,
            'first_reward' => 1,
        ]);
    }

    /**
     * 同一队长 10 秒内是否开战过（elite_model->get_battlelog_by_time）
     */
    private static function battleLogByTime(int $leader, int $now): bool
    {
        return DB::table('elite_log')->where('leader', $leader)->where('dateline', '>=', $now - self::BATTLE_INTERVAL)
            ->whereNull('deleted_at')->value('id') !== null;
    }

    /**
     * 购买次数价格（elite_model->get_buy_price：第 50 次起都按第 50 次）
     */
    private static function getBuyPrice(int $num): int
    {
        if ($num >= 50) {
            $num = 50;
        }
        return (int)Price::ROWS[$num]['elite'];
    }

    /**
     * 玩家活动信息（member_model->get_activity_info 单个 uid：没有记录时按旧版建档值插入）
     *
     * @return array{elitetimes: int, elitesurplustimes: int, elitebuytimes: int, elitebuydateline: int}
     */
    private static function getActivityInfo(int $uid): array
    {
        MemberActivity::ensure($uid);
        return MemberActivity::many([$uid], ['elitetimes', 'elitesurplustimes', 'elitebuytimes', 'elitebuydateline'])[$uid];
    }

    /**
     * 武将加经验（general_model->add_exp，主公等级取将领所属玩家；玩家不存在时旧版返回 -5，不加）
     */
    private static function addGeneralExp(int $uid, int $gid, int $exp): void
    {
        $heroLevel = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('level');
        if ($heroLevel === null) {
            return;
        }
        CountryHandler::addGeneralExp($uid, (int)$heroLevel, $gid, $exp);
    }

    /**
     * 卷轴名称（equipment_model->get_scroll_name），类型不存在为 null
     */
    private static function scrollName(mixed $type): ?string
    {
        return self::SCROLL_NAME[(int)$type] ?? null;
    }

    /**
     * 旧版 PHP 5 的 json_encode 按 14 位有效数字输出浮点数（如 3380 / 100 * 200 输出 6760）
     */
    private static function legacyNumber(int|float $value): int|float
    {
        if (is_int($value)) {
            return $value;
        }
        $value = (float)sprintf('%.14G', $value);
        return $value === floor($value) ? (int)$value : $value;
    }

    private static function intInput(Context $ctx, string $key): int
    {
        $value = $ctx->input($key);
        return is_scalar($value) ? (int)$value : 0;
    }

    /**
     * 旧版 api_return 直接 json_encode 的非数组返回值
     */
    private static function rawJson(mixed $data): WorkermanResponse
    {
        return new WorkermanResponse(200, ['Content-Type' => 'application/json; charset=utf-8'], (string)json_encode($data, JSON_UNESCAPED_UNICODE));
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
