<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Pvp;

use Hanjiang\Battle\BattleEngine;
use Hanjiang\Battle\TeamBuilder;
use Hanjiang\Handler\Activity\EliteHandler;
use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\General\TavernHandler;
use Hanjiang\MasterData\ActivityTime;
use Hanjiang\MasterData\Equip;
use Hanjiang\MasterData\HerothroneMission;
use Hanjiang\MasterData\MountTemp;
use Hanjiang\MasterData\TalentScroll;
use Hanjiang\Model\Equipment;
use Hanjiang\Model\Member;
use Hanjiang\Model\MemberJade;
use Hanjiang\Model\MemberStone;
use Hanjiang\Model\OwnMount;
use Hanjiang\Model\PassGeneral;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 英雄王座（移植旧版 s1 controllers/herothrone.php + models/herothrone_model.php，路径相对旧版 s1/application/）
 *
 * 客户端 herothroneworld.js 走游戏服的 c=herothrone（index / go_back / start / action / buy_times / end_battle），
 * 即 s1 的控制器；uc / war 下同名的 herothrone 控制器只有 get_top_rank / update_top_rank，是 s1 通过
 * uc_model 调用的跨服排行服务，客户端不直接访问。
 *
 * 配置：关卡 HerothroneMission（300 关，每关 position1~9 → 怪物 HerothroneMonster，battle_model->battle_herothrone 即
 * battle_mission type 6；gettype1~3 / getvalue1~3 / getnum1~3 为过关奖励，id 为 10 的倍数的关卡即宝箱）、
 * 装备 Equip、天赋卷轴 TalentScroll、坐骑 MountTemp、免费次数 +1 活动 ActivityTime（double_hero）。
 * 玩家存储：herothrone_log（剩余免费 / 购买次数、今日购买次数、当前进度、状态、历史最高）。
 *
 * 奖励类型（gettype）：1 银币、2 玉石、3 装备、4 装备碎片、5 天赋卷轴碎片、6 天赋卷轴、7 声望、8 原魂、9 突飞卡、
 * 10 紫宝石、11 精炼石、12 五彩石、13 上古血玉、14 坐骑碎片、15 坐骑。HerothroneMission 里用到 1 / 2 / 4 / 7 / 8 / 14。
 *
 * 依赖未移植：
 * - 跨服排行 uc_model->get_top_rank / update_top_rank（uc 服务器 herothrone 排行，旧版缓存在 Redis herothrone 键 1800 秒）：
 *   index / go_back 的 list 恒为空数组，过关刷新历史最高时不上报；
 * - member_model->sub_gold 的 gold_log 元宝流水、日常任务 task_model->task_action(5)、翻牌活动计数；银币 / 声望 / 原魂的流水。
 */
final class HerothroneHandler
{
    /** 开启功能需要等级 */
    private const int OPEN_LEVEL = 101;

    /** 物品流水的执行类（旧版 $this->class） */
    private const string LOG_CLASS = 'herothrone';

    /** 失败后，用元宝继续攻击需要元宝 */
    private const int PRICE = 50;

    /** 购买次数价格 */
    private const int BUY_PRICE = 1000;

    /** 最高关卡（herothrone_model->_max_stage） */
    private const int MAX_STAGE = 300;

    /** 每日免费次数（herothrone_model->_times，double_hero 活动期间 +1） */
    private const int FREE_TIMES = 2;

    /** 一页显示几个怪物（herothrone_model->_num） */
    private const int PAGE_NUM = 5;

    /** 旧版语言包 language/chinese_s/base_lang.php */
    private const string LANG_BASE_CHIP = '碎片';
    private const string LANG_BASE_SCROLL = '天赋卷轴';

    /** 旧版 zc_sanguo_herothrone_log 的字段 */
    private const array LOG_COLUMNS = ['id', 'uid', 'nickname', 'times', 'buytimes', 'today_buytimes', 'today_buydate', 'stage', 'status', 'dateline', 'laststage'];

    /**
     * 初始化接口: c=herothrone&m=index
     */
    public static function index(Context $ctx): array
    {
        /**
         * 2 活动未开启
         *-1 初始化用户信息失败
         * 1 成功
         * 3 直接进入开始界面
         */
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }

        // 判断活动是否开启
        if (!self::isOpen($member)) {
            $return['status'] = 2;
            return $return;
        }
        $uid = (int)$member['uid'];
        $now = time();

        // 获取用户信息；不存在时初始化用户信息
        $info = self::getMemberInfo($uid) ?? self::addMemberInfo($member, $now);
        if ($info === null) {
            // 初始化用户信息失败
            $return['status'] = -1;
            return $return;
        }

        // 每日更新用户挑战次数
        $info = self::dailyReset($uid, $info, $now);

        // 判断上次战斗是否结束，或则是否失败
        if ($info['status'] === 1 || ($info['status'] === 2 && (int)$member['vip'] >= 10)) {
            // 直接进入开始界面
            $return['status'] = 3;
            $return['price'] = self::BUY_PRICE;
            return $return;
        }

        // 成功返回
        $return['status'] = 1;
        $return['info'] = self::infoFields($info);
        $return['buy'] = (int)$member['vip'] >= 9 ? 1 : 0;
        $return['price'] = self::BUY_PRICE;
        // 返回排行列表（全服列表中前几个）
        $return['list'] = self::getTopRank();
        return $return;
    }

    /**
     * 返回接口: c=herothrone&m=go_back
     */
    public static function go_back(Context $ctx): array
    {
        /**
         * 2 活动未开启
         *-1 初始化用户信息失败
         * 1 成功
         */
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }

        // 判断活动是否开启
        if (!self::isOpen($member)) {
            $return['status'] = 2;
            return $return;
        }
        $uid = (int)$member['uid'];

        // 获取用户信息
        $info = self::getMemberInfo($uid);
        if ($info === null) {
            // 初始化用户信息失败
            $return['status'] = -1;
            return $return;
        }

        // 每日更新用户挑战次数
        $info = self::dailyReset($uid, $info, time());

        // 成功返回
        $return['status'] = 1;
        $return['buy'] = (int)$member['vip'] >= 9 ? 1 : 0;
        $return['info'] = self::infoFields($info);
        $return['price'] = self::BUY_PRICE;
        // 返回排行列表（全服列表中前几个）
        $return['list'] = self::getTopRank();
        return $return;
    }

    /**
     * 开始界面: c=herothrone&m=start（新开一局时扣 1 次挑战次数，并发放起始层之前的宝箱奖励）
     */
    public static function start(Context $ctx): array
    {
        /**
         * 2 活动未开启
         *-1 初始化用户信息失败
         *-2 次数不足无法开始
         *-3 没有找到关卡信息
         */
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }

        // 判断活动是否开启
        if (!self::isOpen($member)) {
            $return['status'] = 2;
            return $return;
        }
        $uid = (int)$member['uid'];
        $vip = (int)$member['vip'];

        // 同一玩家并发开始 / 攻击：在事务里锁住用户记录行，次数与进度读写串行
        return DB::transaction(static function () use ($return, $member, $uid, $vip): array {
            // 获取用户信息
            $info = self::getMemberInfo($uid, true);
            if ($info === null) {
                // 初始化用户信息失败
                $return['status'] = -1;
                return $return;
            }

            // 判断次数是否足够
            if ($info['status'] === 0 && $info['times'] + $info['buytimes'] < 1) {
                $return['status'] = -2;
                return $return;
            } elseif ($info['status'] === 2 && $vip < 10 && $info['times'] + $info['buytimes'] < 1) {
                $return['status'] = -2;
                return $return;
            }

            $stage = 0;
            if ($info['stage'] > 0 && ($info['status'] === 1 || ($info['status'] === 2 && $vip >= 10))) {
                // 直接用用户信息里面的信息取最近的关卡信息
                $missionList = self::getMissionByPage($info['stage'] + 1);
            } else {
                // 从历史最高记录所在的十层再往前十层开始（laststage 去掉个位后减 10）
                $stage = $info['laststage'] - $info['laststage'] % 10 - 10;
                $stage = $stage <= 0 ? 1 : $stage + 1;
                $missionList = self::getMissionByPage($stage);
                $info['stage'] = $stage - 1;
            }

            if ($missionList === []) {
                $return['status'] = -3;
                return $return;
            }

            // 遍历关卡，赋给通过状态
            $newlist = [];
            foreach ($missionList as $key => $val) {
                if ($val['id'] <= $info['stage']) {
                    $open = 2;
                } elseif ($val['id'] <= $info['stage'] + 1) {
                    $open = 1;
                } else {
                    $open = 0;
                }
                $newlist[$key] = ['id' => $val['id'], 'image' => $val['image'], 'open' => $open];
            }

            // 取十个宝箱的奖励信息
            $reward = self::getRewardList();
            $newReward = [];
            // 判断扣除挑战次数
            if ($info['status'] === 0 || ($info['status'] === 2 && $vip < 10)) {
                // 修改个人状态为已开启
                $data = ['status' => 1];
                $info['status'] = 1;
                // 判断消耗免费次数还是购买的次数
                if ($info['times'] > 0) {
                    $data['times'] = $info['times'] - 1;
                    $info['times'] -= 1;
                } elseif ($info['buytimes'] > 0) {
                    $data['buytimes'] = $info['buytimes'] - 1;
                    $info['buytimes'] -= 1;
                }
                $data['stage'] = $stage - 1;
                // 执行修改
                self::updateMemberInfo($uid, $data);

                // 组装奖励信息：起始层之前的宝箱奖励
                $getReward = [];
                foreach ($reward as $key => $val) {
                    if ($val['id'] > $stage) {
                        continue;
                    }
                    for ($i = 1; $i <= 3; $i++) {
                        $type = $val['gettype' . $i];
                        $value = $val['getvalue' . $i];
                        if ($type <= 0 || !self::positive($value)) {
                            continue;
                        }
                        if (in_array($type, [1, 2, 7, 8, 9, 10, 11, 12, 13], true)) {
                            if (isset($getReward[$type])) {
                                $getReward[$type]['getvalue'] += (int)$value;
                            } else {
                                $getReward[$type] = ['gettype' => $type, 'getvalue' => (int)$value];
                            }
                        } elseif ($type === 14) {
                            $k = $type . self::valueId($value);
                            if (isset($getReward[$k])) {
                                $getReward[$k]['getnum'] += $val['getnum' . $i];
                            } else {
                                $getReward[$k] = ['gettype' => $type, 'getvalue' => $value, 'getnum' => $val['getnum' . $i]];
                            }
                        } else {
                            $getReward[$type . $key] = ['gettype' => $type, 'getvalue' => $value, 'getnum' => $val['getnum' . $i]];
                        }
                    }
                }
                // 发放前面关卡的奖励
                foreach ($getReward as $val) {
                    $item = self::grantReward($uid, $val['gettype'], $val['gettype'] >= 3 && $val['gettype'] <= 6 || $val['gettype'] >= 14 ? self::valueId($val['getvalue']) : (int)$val['getvalue'], (int)($val['getnum'] ?? 0), 'start');
                    if ($item === null) {
                        continue;
                    }
                    $entry = ['gettype' => $val['gettype'], 'getvalue' => $item];
                    if ($val['gettype'] === 14 || $val['gettype'] === 15) {
                        $entry['getnum'] = $val['getnum'];
                        $entry['getsort'] = 1;
                    } elseif ($val['gettype'] >= 3 && $val['gettype'] <= 6) {
                        $entry['getsort'] = $val['gettype'];
                    } else {
                        $entry['getsort'] = 0;
                    }
                    $newReward[] = $entry;
                }
            }

            // 声望插入到数组头部
            foreach ($newReward as $key => $val) {
                if ($val['gettype'] === 7) {
                    unset($newReward[$key]);
                    array_unshift($newReward, $val);
                }
            }

            // 排序（_array_sort：按 getsort 升序 asort）
            $lastlist = [];
            if ($newReward !== []) {
                $sort = array_column($newReward, 'getsort');
                $sort = array_combine(array_keys($newReward), $sort);
                asort($sort);
                foreach (array_keys($sort) as $k) {
                    $lastlist[] = $newReward[$k];
                }
            }

            // 成功返回
            $return['status'] = 1;
            $return['buy'] = $vip >= 9 ? 1 : 0;
            $return['mission'] = $newlist;
            $return['reward'] = $reward;
            $return['price'] = self::BUY_PRICE;
            $return['info'] = $info;
            $return['info']['price'] = self::PRICE;
            $return['get_reward'] = $lastlist;
            $return['all_silver'] = self::memberValue($uid, 'silver');
            return $return;
        });
    }

    /**
     * 攻击: c=herothrone&m=action（攻击当前进度的下一关）
     */
    public static function action(Context $ctx): array
    {
        /**
         *  2 功能未开启
         * -1 获取用户信息失败
         * -2 还未开始
         * -3 元宝不足
         * -4 先结束上一局再开始挑战
         * -5 获取关卡信息失败
         */
        $return = [
            'status' => 1,
            'info' => [
                'reward' => [],
                'win' => 0,
                'view' => 0,
            ],
        ];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            $return['status'] = 0;
            return $return;
        }

        // 判断活动是否开启
        if (!self::isOpen($member)) {
            $return['status'] = 2;
            return $return;
        }
        $uid = (int)$member['uid'];
        $vip = (int)$member['vip'];

        return DB::transaction(static function () use ($return, $member, $uid, $vip): array {
            // 获取用户信息
            $info = self::getMemberInfo($uid, true);
            if ($info === null) {
                $return['status'] = -1;
                return $return;
            }

            // 是否使用元宝消费
            $useGold = 0;
            if ($info['status'] === 0) {
                // 还未开启关卡
                $return['status'] = -2;
                return $return;
            } elseif ($info['status'] === 2) {
                // 失败后继续之前的关卡
                if ($vip >= 10) {
                    // 判断元宝是否足够
                    if ((int)$member['gold'] < self::PRICE) {
                        $return['status'] = -3;
                        return $return;
                    }
                    $useGold = 1;
                } else {
                    // 先结束后，在重新开始挑战
                    $return['status'] = -4;
                    return $return;
                }
            }

            // 查询关卡信息
            $missionid = min($info['stage'] + 1, self::MAX_STAGE);
            $mission = HerothroneMission::ROWS[$missionid] ?? null;
            if ($mission === null) {
                $return['status'] = -5;
                return $return;
            }

            // 调用战斗算法
            $battle = self::battleHerothrone($uid, $mission);

            // 扣除元宝（旧版不看 sub_gold 结果；并发扣元宝导致余额不足时按元宝不足返回，不记战果）
            if ($useGold === 1 && !Member::deductCurrency($uid, gold: self::PRICE)) {
                $return['status'] = -3;
                return $return;
            }

            // 胜利失败处理
            $reward = [];
            $assess = $battle['info']['assess'] ?? null;
            if ($assess >= 1) {
                // 添加奖励
                for ($i = 1; $i <= 3; $i++) {
                    $type = $mission['gettype' . $i];
                    $value = $mission['getvalue' . $i];
                    if ($type <= 0 || $value <= 0) {
                        continue;
                    }
                    $item = self::grantReward($uid, $type, $value, $mission['getnum' . $i], 'action');
                    if ($item === null) {
                        continue;
                    }
                    $entry = ['gettype' => $type, 'getvalue' => $item];
                    if ($type === 14 || $type === 15) {
                        $entry['getnum'] = $mission['getnum' . $i];
                    }
                    $reward[] = $entry;
                }
                // 修改关卡
                $data = ['stage' => $missionid, 'status' => 1];
                // 判断本次攻击的关卡是否大于自己之前的历史最高记录
                if ($missionid > $info['laststage']) {
                    // 修改历史最高过关记录
                    $data['laststage'] = $missionid;
                    // 依赖未移植：update_top_rank（uc_model 跨服排行上报 + 本服 Redis herothrone 排行缓存）
                }
                // 判断是否通关
                if ($missionid === self::MAX_STAGE) {
                    // 状态修改为未开启
                    $data['stage'] = 0;
                    $data['status'] = 0;
                    $return['info']['view'] = 0;
                }
                self::updateMemberInfo($uid, $data);
            } else {
                // 判断自己的vip等级是否能够花元宝继续攻击
                if ($vip >= 10) {
                    // 返回继续攻击需要元宝；状态改为失败
                    $return['info']['price'] = self::PRICE;
                    $return['info']['view'] = 1;
                    $data = ['status' => 2];
                } else {
                    // 失败修改状态(直接返回到初试界面)
                    $data = ['stage' => 0, 'status' => 0];
                    $return['info']['view'] = 0;
                }
                self::updateMemberInfo($uid, $data);
            }

            // 成功返回(战斗过程，奖励信息)
            $return['status'] = 1;
            $return['battle'] = $battle['info'];
            // 左右攻击方（守方取 5 号位的守将）
            $return['battle']['left_role'] = [
                'name' => (string)$member['nickname'],
                'level' => (int)$member['level'],
                'gender' => (int)$member['gender'],
            ];
            $boss = $battle['info']['rightteam'][5] ?? null;
            $return['battle']['right_role'] = [
                'name' => $boss['name'] ?? null,
                'level' => $boss['level'] ?? null,
                'type' => $boss['herotype'] ?? null,
            ];

            $return['info']['win'] = $assess;
            $return['info']['all_gold'] = self::memberValue($uid, 'gold');
            $return['info']['all_silver'] = self::memberValue($uid, 'silver');
            $return['info']['reward'] = $reward;
            return $return;
        });
    }

    /**
     * 购买次数: c=herothrone&m=buy_times（VIP 9 / 10 / 11 / 12 每日可买 1 / 3 / 5 / 10 次，每次 1000 元宝）
     */
    public static function buy_times(Context $ctx): array
    {
        /**
         * 2 等级不够
         *-1 获取用户信息失败
         *-2 今日购买次数已达上限
         *-3 元宝不足
         */
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }

        // 判断活动是否开启
        if (!self::isOpen($member)) {
            $return['status'] = 2;
            return $return;
        }
        $uid = (int)$member['uid'];
        $vip = (int)$member['vip'];
        $now = time();

        return DB::transaction(static function () use ($return, $member, $uid, $vip, $now): array {
            // 获取用户信息
            $info = self::getMemberInfo($uid, true);
            if ($info === null) {
                $return['status'] = -1;
                return $return;
            }

            // 得到今日购买次数
            if ($info['today_buydate'] > 0 && date('ymd', $info['today_buydate']) === date('ymd', $now)) {
                $today = $info['today_buytimes'];
            } else {
                $today = 0;
            }
            $moreDay = match (true) {
                $vip >= 12 => 10,
                $vip >= 11 => 5,
                $vip >= 10 => 3,
                $vip >= 9 => 1,
                default => 0,
            };

            // 判断今日是否还能购买挑战次数
            if ($moreDay - $today < 1) {
                $return['status'] = -2;
                return $return;
            }

            // 判断元宝是否足够；扣除元宝（并发扣元宝导致余额不足时同样按元宝不足返回）
            if ((int)$member['gold'] < self::BUY_PRICE || !Member::deductCurrency($uid, gold: self::BUY_PRICE)) {
                $return['status'] = -3;
                return $return;
            }
            // 修改次数
            $data = [
                'buytimes' => $info['buytimes'] + 1,
                'today_buytimes' => $today + 1,
                'today_buydate' => $now,
            ];
            self::updateMemberInfo($uid, $data);
            // 成功返回
            $return['status'] = 1;
            $return['times'] = $info['times'];
            $return['buytimes'] = $data['buytimes'];
            $return['all_gold'] = self::memberValue($uid, 'gold');
            return $return;
        });
    }

    /**
     * 结束战斗: c=herothrone&m=end_battle
     */
    public static function end_battle(Context $ctx): array
    {
        /**
         * 2国家等级不够
         *-1获取用户信息失败
         *-2当前未开启战斗
         *-3结束战斗成功
         * （-3 实为修改失败）
         */
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }

        // 判断活动是否开启
        if (!self::isOpen($member)) {
            $return['status'] = 2;
            return $return;
        }
        $uid = (int)$member['uid'];
        $now = time();

        return DB::transaction(static function () use ($return, $uid, $now): array {
            // 查询用户信息
            $info = self::getMemberInfo($uid, true);
            if ($info === null) {
                $return['status'] = -1;
                return $return;
            }

            // 判断当前状态
            if ($info['status'] === 0) {
                $return['status'] = -2;
                return $return;
            }

            // 修改状态信息
            if (self::updateMemberInfo($uid, ['status' => 0, 'stage' => 0, 'dateline' => $now]) !== 1) {
                $return['status'] = -3;
                return $return;
            }

            // 结束成功
            $return['status'] = 1;
            return $return;
        });
    }

    /**
     * 发放一项奖励并返回回报给客户端的 getvalue（旧版 start / action 里按 gettype 分支的发放逻辑）
     *
     * 道具 / 卷轴 / 坐骑模板查不到时旧版不回报，返回 null。$method 为当前接口（start / action），记物品流水用。
     *
     * @return int|array<string, mixed>|null
     */
    private static function grantReward(int $uid, int $type, int $value, int $num, string $method): int|array|null
    {
        switch ($type) {
            case 1:
                // 奖励银币
                Member::addCurrency($uid, silver: $value);
                return $value;
            case 2:
                // 奖励玉石
                MemberJade::add($uid, $value, self::LOG_CLASS, $method);
                return $value;
            case 3:
            case 4:
                // 查询.添加道具
                $item = Equip::ROWS[$value] ?? null;
                if ($item === null) {
                    return null;
                }
                $itemInfo = [
                    'itemtype' => 1,
                    'itemid' => $item['id'],
                    'quality' => $item['quality'],
                    'itemname' => $item['name'],
                ];
                if ($type === 4) {
                    // 添加碎片
                    Equipment::addChip($uid, $value, 1, self::LOG_CLASS, $method);
                    $itemInfo['itemname'] .= self::LANG_BASE_CHIP;
                } else {
                    // 添加道具
                    Equipment::addFromTemplate($uid, $value);
                }
                return $itemInfo;
            case 5:
            case 6:
                // 增加天赋卷轴
                $talent = TalentScroll::ROWS[$value] ?? null;
                if ($talent === null) {
                    return null;
                }
                // itemtype 传2是卷轴，1是装备
                $itemInfo = [
                    'itemtype' => 2,
                    'itemid' => $talent['type'],
                    'quality' => $talent['quality'],
                    'itemname' => (EliteHandler::SCROLL_NAME[$talent['type']] ?? '') . self::LANG_BASE_SCROLL,
                ];
                // 6 卷轴、5 碎片
                Equipment::addTalentScroll($uid, $value, $type === 6 ? 0 : 1, 1, self::LOG_CLASS, $method);
                if ($type === 5) {
                    $itemInfo['itemname'] .= self::LANG_BASE_CHIP;
                }
                return $itemInfo;
            case 7:
                // 声望
                Member::addCurrency($uid, reputation: $value);
                return $value;
            case 8:
                // 原魂
                Member::addCurrency($uid, soul: $value);
                return $value;
            case 9:
                // 突飞
                PassGeneral::addFreeleap($uid, $value);
                return $value;
            case 10:
                // 紫宝石
                TavernHandler::addJade($uid, 3, $value, self::LOG_CLASS, $method);
                return $value;
            case 11:
            case 12:
            case 13:
                // 精炼石 / 五彩石 / 上古血玉（add_stone 类型 1 / 2 / 3）
                MemberStone::add($uid, $value, $type - 10, self::LOG_CLASS, $method);
                return $value;
            case 14:
            case 15:
                $mount = MountTemp::ROWS[$value] ?? null;
                if ($mount === null) {
                    return null;
                }
                // 14 坐骑碎片；15 坐骑
                $mountInfo = ['id' => $value, 'itemname' => $mount['name'], 'quality' => $mount['quality']];
                if ($type === 14) {
                    OwnMount::addMount($uid, $value, 1, $num, self::LOG_CLASS, $method);
                    $mountInfo['itemname'] .= self::LANG_BASE_CHIP;
                } else {
                    OwnMount::addMount($uid, $value, class: self::LOG_CLASS, method: $method);
                }
                return $mountInfo;
        }
        return null;
    }

    /**
     * 宝箱奖励列表（移植 get_reward_list：id 为 10 的倍数的关卡；道具 / 卷轴 / 坐骑的 getvalue 换成模板信息）
     *
     * 旧版缓存在 Redis herothrone_reward 键 86400 秒；配置已在 MasterData，直接计算。
     *
     * @return list<array<string, mixed>>
     */
    private static function getRewardList(): array
    {
        $newreward = [];
        foreach (HerothroneMission::ROWS as $val) {
            if ($val['id'] % 10 !== 0) {
                continue;
            }
            $info = ['id' => $val['id']];
            foreach (['gettype1', 'gettype2', 'gettype3', 'getvalue1', 'getvalue2', 'getvalue3', 'getnum1', 'getnum2', 'getnum3'] as $field) {
                $info[$field] = $val[$field];
            }
            for ($i = 1; $i <= 3; $i++) {
                $type = $val['gettype' . $i];
                $value = $val['getvalue' . $i];
                $info['getnum' . $i] = $val['getnum' . $i] > 0 ? $val['getnum' . $i] : 0;
                if (($type === 3 || $type === 4) && isset(Equip::ROWS[$value])) {
                    $item = Equip::ROWS[$value];
                    $info['getvalue' . $i] = [
                        'id' => $item['id'],
                        'name' => $item['name'] . ($type === 4 ? self::LANG_BASE_CHIP : ''),
                        'type' => $item['type'],
                        'quality' => $item['quality'],
                    ];
                }
                if (($type === 5 || $type === 6) && isset(TalentScroll::ROWS[$value])) {
                    $scroll = TalentScroll::ROWS[$value];
                    $info['getvalue' . $i] = [
                        'id' => $scroll['id'],
                        'name' => (EliteHandler::SCROLL_NAME[$scroll['type']] ?? null) . ($type === 6 ? self::LANG_BASE_CHIP : ''),
                        'type' => $scroll['type'],
                        'quality' => $scroll['quality'],
                    ];
                }
                if (($type === 14 || $type === 15) && isset(MountTemp::ROWS[$value])) {
                    $mount = MountTemp::ROWS[$value];
                    $info['getvalue' . $i] = [
                        'id' => $mount['id'],
                        'name' => $mount['name'] . ($type === 14 ? self::LANG_BASE_CHIP : ''),
                        'quality' => $mount['quality'],
                    ];
                }
            }
            $newreward[] = $info;
        }
        return $newreward;
    }

    /**
     * 奖励值是否大于 0（旧版 $val['getvalue'] > 0：模板信息数组恒大于 0）
     */
    private static function positive(int|array $value): bool
    {
        return is_array($value) || $value > 0;
    }

    /**
     * 奖励值里的模板 id（模板信息数组取 id；模板查不到时旧版保持原值）
     *
     * @param int|array<string, mixed> $value
     */
    private static function valueId(int|array $value): int
    {
        return is_array($value) ? (int)$value['id'] : $value;
    }

    /**
     * 按进度查询临近关卡列表（移植 herothrone_model->get_mission_by_page：每页 5 关）
     *
     * @return list<array<string, mixed>>
     */
    private static function getMissionByPage(int $stage): array
    {
        $stage = min($stage, self::MAX_STAGE);
        $page = (int)ceil($stage / self::PAGE_NUM);
        $page = $page <= 0 ? 1 : $page;
        return array_slice(array_values(HerothroneMission::ROWS), ($page - 1) * self::PAGE_NUM, self::PAGE_NUM);
    }

    /**
     * 排行列表（旧版 get_top_rank：Redis herothrone 缓存，没有时从 uc 服务器取前 10 名，servername 为分组名 + 服务器 id + 「服」）
     *
     * 依赖未移植：uc 服务器的英雄王座跨服排行（uc 的 herothrone_model 不在旧版代码包里），返回空数组。
     *
     * @return list<array<string, mixed>>
     */
    private static function getTopRank(): array
    {
        return [];
    }

    /**
     * 每日更新用户挑战次数（index / go_back：dateline 不是今天时重置免费次数与今日购买次数）
     *
     * @param array<string, mixed> $info
     * @return array<string, mixed>
     */
    private static function dailyReset(int $uid, array $info, int $now): array
    {
        if (date('ymd', $info['dateline']) === date('ymd', $now)) {
            return $info;
        }
        $data = [
            'times' => self::freeTimes($now),
            'today_buytimes' => 0,
            'today_buydate' => 0,
            'dateline' => $now,
        ];
        // 按读到的 dateline 条件更新，并发请求只重置一次
        DB::table('herothrone_log')->where('uid', $uid)->where('dateline', $info['dateline'])->whereNull('deleted_at')->update($data);
        // 旧版只回写 times 到返回信息
        $info['times'] = $data['times'];
        return $info;
    }

    /**
     * 每日免费次数（herothrone_model 构造函数：double_hero 活动期间 +1）
     */
    private static function freeTimes(int $now): int
    {
        foreach (ActivityTime::ROWS as $row) {
            if ($row['name'] === 'double_hero') {
                return $row['starttime'] <= $now && $row['endtime'] >= $now ? self::FREE_TIMES + 1 : self::FREE_TIMES;
            }
        }
        return self::FREE_TIMES;
    }

    /**
     * 英雄王座战斗（移植 battle_model->battle_herothrone → battle_mission($mission_info, 6)）
     *
     * 旧版 check_user_team：玩家 -1（没有阵法）/ -3（武将信息错误）时不战斗、info 为空数组（控制器不看 status，
     * 照旧版按失败处理）；-2（阵法里没有武将）按空队伍继续战斗（空队伍直接判 -3 大败）。
     *
     * @param array<string, mixed> $mission HerothroneMission 行
     * @return array{status: int, info: array<string, mixed>}
     */
    private static function battleHerothrone(int $uid, array $mission): array
    {
        $left = TeamBuilder::forMember($uid, pve: true);
        if ($left['status'] === -1 || $left['status'] === -3) {
            return ['status' => $left['status'], 'info' => []];
        }
        $right = TeamBuilder::forMonsters($mission, TeamBuilder::MONSTER_HEROTHRONE);
        $engine = new BattleEngine($left['matrix_type'], $left['matrix_coe']);
        return [
            'status' => 1,
            'info' => $engine->battle($left['team'], $right['team']),
        ];
    }

    /**
     * 主公等级 101 起开放（构造函数 _open）
     *
     * @param array<string, mixed> $member
     */
    private static function isOpen(array $member): bool
    {
        return (int)$member['level'] >= self::OPEN_LEVEL;
    }

    /**
     * index / go_back 返回的用户信息字段
     *
     * @param array<string, mixed> $info
     * @return array{uid: int, times: int, buytimes: int, laststage: int, status: int}
     */
    private static function infoFields(array $info): array
    {
        return [
            'uid' => $info['uid'],
            'times' => $info['times'],
            'buytimes' => $info['buytimes'],
            'laststage' => $info['laststage'],
            'status' => $info['status'],
        ];
    }

    /**
     * 查询用户信息（移植 herothrone_model->get_member_info）
     *
     * @param bool $lock 在事务里加行锁
     * @return array<string, mixed>|null
     */
    private static function getMemberInfo(int $uid, bool $lock = false): ?array
    {
        $rows = DB::query(
            'SELECT `' . implode('`, `', self::LOG_COLUMNS) . '` FROM `zc_sanguo_herothrone_log` WHERE `uid` = :uid AND `deleted_at` IS NULL'
            . ($lock ? ' FOR UPDATE' : ''),
            ['uid' => $uid]
        );
        if ($rows === []) {
            return null;
        }
        $row = $rows[0];
        foreach ($row as $key => $value) {
            $row[$key] = $key === 'nickname' ? (string)$value : (int)$value;
        }
        return $row;
    }

    /**
     * 初始化用户信息（移植 herothrone_model->add_member_info；并发首次请求只插入一行，唯一键 uid）
     *
     * @param array<string, mixed> $member
     * @return array<string, mixed>|null
     */
    private static function addMemberInfo(array $member, int $now): ?array
    {
        DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_herothrone_log` (`uid`, `nickname`, `times`, `stage`, `status`, `buytimes`, `dateline`, `laststage`)
             VALUES (:uid, :nickname, :times, 0, 0, 0, :now, 0)',
            ['uid' => (int)$member['uid'], 'nickname' => (string)$member['nickname'], 'times' => self::freeTimes($now), 'now' => $now]
        );
        return self::getMemberInfo((int)$member['uid']);
    }

    /**
     * 更新用户信息（移植 herothrone_model->update_member_info，写入成功返回 1）
     *
     * @param array<string, int> $data
     */
    private static function updateMemberInfo(int $uid, array $data): int
    {
        return DB::table('herothrone_log')->where('uid', $uid)->whereNull('deleted_at')->update($data) > 0 ? 1 : 0;
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
