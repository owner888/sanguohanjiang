<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Campaign;

use Hanjiang\Battle\BattleEngine;
use Hanjiang\Battle\TeamBuilder;
use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\Pvp\SiegeHandler;
use Hanjiang\MasterData\ActivityTime;
use Hanjiang\MasterData\Gem;
use Hanjiang\MasterData\SanctumMission;
use Hanjiang\MasterData\SanctumReward;
use Hanjiang\Model\Chat;
use Hanjiang\Model\Member;
use Hanjiang\Model\MemberActivity;
use Hanjiang\Model\OwnGem;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 四象圣域（移植旧版 controllers/sanctum.php + models/sanctum_model.php，路径相对旧版 s1/application/）
 *
 * 配置：关卡 SanctumMission（5 个圣域 × 5 层 × 10 关，id 1~250；position1~9 → 怪物 SanctumMonster，
 * battle_model->battle_sanctum 即 battle_mission type 7）、每日奖励倍率 SanctumReward、宝石模板 Gem、
 * 双倍活动 ActivityTime（double_sanctum）。
 * 玩家存储：sanctum_log（今日剩余 / 购买次数，跨天在 get_member_info 里惰性重置为 10）、sanctum_open_map（每个圣域的
 * 已过层数 / 关卡）、sanctum_mission_log（关卡通过记录，即攻略列表）、sanctum_report（首通战报）、
 * member_activity.sanctumdate（每日奖励领取时间）。
 *
 * 客户端调用（sanctumworld.js / miscpop.js）：index、select_map、get_mission_info、action（num 为扫荡次数）、buy_times、
 * get_mission_report_list、get_report_info、send_to_chat、get_reward。旧版公开方法即这 9 个。
 *
 * 宝石碎片存 member.gem（OwnGem::addChip，首杀 / 掉落 / 每日奖励），宝石存 own_gem 并按颗数加 member.gemuse
 * （OwnGem::addGem / addGemuse）；gem / all_chip / all_gem 为当前碎片数。
 *
 * 元宝消耗由 Member::deductCurrency 记元宝流水与日常任务 5（元宝消费）。
 *
 * 依赖未移植：sub_gold 的翻牌活动计数（flop，旧版数据中已结束）。
 */
final class SanctumHandler
{
    /** 次数购买价格（旧版 $_price） */
    private const int PRICE = 20;

    /** 物品流水的执行类（旧版 $this->class） */
    private const string LOG_CLASS = 'sanctum';

    /** 开启等级（旧版 $_level：等级小于 140 不开放） */
    private const int OPEN_LEVEL = 140;

    /** 战报分享到聊天室的元宝花费（send_to_chat 的 $price） */
    private const int SHARE_PRICE = 2;

    /** 最高关卡ID（旧版 $_max_id） */
    private const int MAX_ID = 250;

    /** 每日更新次数（sanctum_model->_times） */
    private const int DAILY_TIMES = 10;

    /** 旧版语言包 language/chinese_s/sanctum_lang.php */
    private const array LANG_MAP = [1 => '青龙圣域', 2 => '玄武圣域', 3 => '朱雀圣域', 4 => '白虎圣域', 5 => '黄麟圣域'];
    private const array LANG_STAGE = [1 => '一重天', 2 => '二重天', 3 => '三重天', 4 => '四重天', 5 => '五重天'];
    private const string LANG_MISSIONID = '第%s关';

    /** 旧版 zc_sanguo_sanctum_log 的字段 */
    private const array LOG_COLUMNS = ['uid', 'missionlevel', 'missionstage', 'missionid', 'times', 'buy_times', 'dateline'];

    /** 旧版 zc_sanguo_sanctum_open_map 的字段 */
    private const array MAP_COLUMNS = ['uid', 'city', 'stage', 'missionid', 'dateline'];

    /** 旧版 zc_sanguo_sanctum_mission_log 的字段 */
    private const array MISSION_LOG_COLUMNS = ['id', 'missionid', 'uid', 'nickname', 'level', 'dateline', 'tree', 'first_reward'];

    /**
     * 初试界面: c=sanctum&m=index
     */
    public static function index(Context $ctx): array
    {
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }

        // 判断功能开启状态
        if (!self::isOpen($member)) {
            $return['status'] = 2;
            return $return;
        }

        // 取自己的mission_log(判断是否通关)
        $missionLog = self::getOpenMap((int)$member['uid']);
        for ($i = 1; $i <= 5; $i++) {
            if (isset($missionLog[$i]) && $missionLog[$i]['missionid'] === $i * 50) {
                $return['list'][$i]['pass'] = 1;
            } else {
                $return['list'][$i]['pass'] = 0;
            }
        }

        $return['status'] = 1;
        return $return;
    }

    /**
     * 进入关卡: c=sanctum&m=select_map（l 圣域id，s 层数；不传时取上次进入的地方）
     */
    public static function select_map(Context $ctx): array
    {
        $now = time();
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }

        // 判断功能开启状态
        if (!self::isOpen($member)) {
            $return['status'] = 2;
            return $return;
        }
        $uid = (int)$member['uid'];

        // 圣域id
        $city = (int)$ctx->input('l', 0);
        $stage = (int)$ctx->input('s', 0);

        // 获取用户信息
        $log = self::getMemberInfo($uid, $now, false);
        if ($log === null) {
            $return['status'] = -1;
            return $return;
        }

        // 如果没有传参数进来，就取上次进入的地方
        if ($city === 0) {
            $city = $log['missionlevel'];
            $stage = $log['missionstage'];
        }

        // 获取该圣域的关卡通过情况
        $logs = self::getStageStatus($uid, $city, $now);
        if ($logs === null) {
            $return['status'] = -2;
            return $return;
        }

        if ($stage === 0) {
            $stage = $logs['stage'];
        }

        if ($stage > $logs['stage']) {
            $return['status'] = -3;
            return $return;
        }

        // 通过的关卡id
        $missionid = $logs['missionid'];

        // 按圣域id和层数获取关卡信息
        $list = self::getMissionList($city, $stage);
        if ($list === []) {
            $return['status'] = -4;
            return $return;
        }

        // 已开启的关卡id
        $missionid += 1;
        $i = ($stage - 1) * 10;
        $newList = [];
        // 循环列表，判断关卡是否开启；字段筛选
        foreach ($list as $key => $val) {
            if ($val['id'] < $missionid) {
                $open = 2;
            } elseif ($val['id'] === $missionid) {
                $open = 1;
            } else {
                $open = 3;
            }
            $i++;
            $newList[$key] = [
                'id' => $val['id'],
                'name' => $val['name'],
                'type' => $val['type'],
                'city' => $val['city'],
                'stage' => $val['stage'],
                'x' => $val['x'],
                'y' => $val['y'],
                'image' => $val['image'],
                'open' => $open,
                'missionid' => $i,
                // 判断是否已经领取首杀奖励（旧版恒为 1）
                'firstkill' => 1,
            ];
        }

        $return['status'] = 1;
        $return['list'] = $newList;
        $return['times'] = $log['times'] + $log['buy_times'];
        $return['gem'] = OwnGem::chip($uid);
        $return['stage'] = $stage;
        $return['all_stage'] = $logs['stage'];
        // 旧版 lang->line 取不到时为 FALSE，拼接后为空串
        $return['name'] = (self::LANG_MAP[$city] ?? '') . (self::LANG_STAGE[$stage] ?? '');
        $return['price'] = self::PRICE;
        return $return;
    }

    /**
     * 获得关卡信息: c=sanctum&m=get_mission_info（id 关卡id）
     */
    public static function get_mission_info(Context $ctx): array
    {
        $now = time();
        $return = [];
        $id = (int)$ctx->input('id', 0);
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($id === 0 || $member === null) {
            $return['status'] = -1;
            return $return;
        }

        // 判断功能开启状态
        if (!self::isOpen($member)) {
            $return['status'] = 2;
            return $return;
        }
        $uid = (int)$member['uid'];

        // 获取用户信息
        $log = self::getMemberInfo($uid, $now, false);
        if ($log === null) {
            $return['status'] = -2;
            return $return;
        }

        // 获取关卡信息
        $missionTemp = self::getMissionInfo($id);
        if ($missionTemp === null) {
            $return['status'] = -3;
            return $return;
        }

        // 关卡信息字段筛选
        $mission = [];
        foreach (['city', 'stage', 'type', 'chip_num', 'background', 'id', 'image', 'level', 'name', 'gem', 'rate', 'gem_level', 'first_chip_num'] as $val) {
            $mission[$val] = $missionTemp[$val];
        }
        // 获取玩家通关记录；默认是首杀
        $isFirst = 1;
        $umiss = self::getMissionLogByUid($uid, $id);
        if ($umiss !== null && $umiss['first_reward'] === 1) {
            // 已经首杀过
            $isFirst = 0;
        }

        // 获取该圣域关卡通过记录
        $logs = self::getStageStatus($uid, $mission['city'], $now);
        if ($logs === null) {
            $return['status'] = -4;
            return $return;
        }

        // 判断当前关卡是否开启
        $mission['attack'] = $id > $logs['missionid'] + 1 ? 0 : 1;

        if ($mission['gem'] > 0) {
            // 获取奖励宝石信息（旧版模板查不到时取到 null）
            $gem = Gem::ROWS[$mission['gem']] ?? null;
            $mission['gem_name'] = $gem['name'] ?? null;
            $mission['gem_value'] = $gem['level' . $mission['gem_level']] ?? null;
            $mission['gem_type'] = $gem['type'] ?? null;
            $mission['gem_quality'] = self::gemQuality($mission['gem_level']);
        } else {
            $mission['gem_name'] = '';
            $mission['gem_value'] = 0;
            $mission['gem_type'] = 0;
            $mission['gem_quality'] = 0;
        }

        // 取 5 / 10 / 50 / 100 次消耗
        $ownTimes = $log['times'] + $log['buy_times'];
        $mission += self::mopupPrice($ownTimes);

        // 获取该关卡的通过记录，没打过就不能扫荡
        $missionLog = self::getMissionLog($uid, $id);
        if ($missionLog === null) {
            $mopUp = 0;
        } else {
            $mopUp = $isFirst === 1 ? 0 : 1;
        }

        // 双倍奖励活动期间，掉落概率双倍
        if (self::isDouble($now) && $isFirst === 0) {
            $mission['rate'] *= 2;
            $mission['chip_num'] *= 2;
        }

        // 装备获得概率
        if ($mission['rate'] > 0 && $mission['rate'] <= 50) {
            $mission['getrate'] = '1';
        } elseif ($mission['rate'] > 0 && $mission['rate'] < 100) {
            $mission['getrate'] = '2';
        } else {
            $mission['getrate'] = '0';
        }
        unset($mission['rate']);

        // 成功返回
        $return['status'] = 1;
        $return['mop_up'] = $mopUp;
        $return['mission'] = $mission;
        $return['mission']['firstkill'] = $isFirst;
        return $return;
    }

    /**
     * 攻打: c=sanctum&m=action（id 关卡id，num 次数，大于 1 为扫荡）
     *
     * status：2 功能未开启；-1 获取用户信息失败；-2 关卡不存在；-3 获取圣域进度失败；-4 关卡未开启；
     * -5 未通过的关卡不能扫荡；-6 次数不足；-8 元宝不足；-24 首杀不能扫荡；1 成功
     */
    public static function action(Context $ctx): array
    {
        $now = time();
        $return = [
            'status' => 0,
            'info' => [
                'win' => 0,
                'gem' => 0,
                'chip' => 0,
                'gem_name' => '',
                'gem_type' => 0,
                'gem_power' => 0,
                'gem_num' => 0,
                'gem_quality' => 0,
                'complete' => 0,
                'stage' => 0,
            ],
        ];
        $id = (int)$ctx->input('id', 0);
        $num = (int)$ctx->input('num', 0);
        if ($num === 0) {
            $num = 1;
        }
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        if ($num < 0) {
            // 旧版没有校验：负数次数会跳过次数判断、给次数和元宝倒加（写入垃圾数据），这里按失败返回
            return $return;
        }

        // 判断功能开启状态
        if (!self::isOpen($member)) {
            $return['status'] = 2;
            return $return;
        }
        $uid = (int)$member['uid'];

        // 同一玩家并发攻打：在事务里锁住用户记录行，次数读写串行
        return DB::transaction(static function () use ($return, $member, $uid, $id, $num, $now): array {
            // 获取用户信息
            $log = self::getMemberInfo($uid, $now, true);
            if ($log === null) {
                $return['status'] = -1;
                return $return;
            }

            // 获取关卡信息
            $mission = self::getMissionInfo($id);
            if ($mission === null) {
                $return['status'] = -2;
                return $return;
            }

            // 判断该关卡是否已开启
            $logs = self::getStageStatus($uid, $mission['city'], $now);
            if ($logs === null) {
                $return['status'] = -3;
                return $return;
            }

            // 判断当前关卡是否开启
            if ($id > $logs['missionid'] + 1) {
                $return['status'] = -4;
                return $return;
            }

            $missionLog = self::getMissionLog($uid, $id);
            // 如果是扫荡，判断该关卡是否已经通过
            if ($num > 1 && $missionLog === null) {
                $return['status'] = -5;
                return $return;
            }

            // 如果不是扫荡，判断次数是否足够
            if ($num === 1 && $log['times'] + $log['buy_times'] === 0) {
                $return['status'] = -6;
                return $return;
            }

            $price = 0;
            // 如果是扫荡，判断价格是否足够
            if ($num > 1) {
                if ($log['buy_times'] + $log['times'] - $num < 0) {
                    // 总价格
                    $price = ($num - ($log['times'] + $log['buy_times'])) * self::PRICE;
                    if ((int)$member['gold'] < $price) {
                        $return['status'] = -8;
                        return $return;
                    }
                }
                $result = self::getMissionLogByUid($uid, $id);
                if ($result !== null && $result['first_reward'] === 0) {
                    // 防刷接口：首杀不能扫荡
                    $return['status'] = -24;
                    return $return;
                }
            }

            // 调用战斗
            $battle = self::battleSanctum($uid, $mission);
            // 扫荡默认为赢
            if ($num > 1) {
                $battle['info']['assess'] = 1;
            }

            // 如果有元宝消耗，扣除元宝
            if ($price > 0 && !Member::deductCurrency($uid, gold: $price)) {
                // 旧版不看 sub_gold 结果；并发扣元宝导致余额不足时按元宝不足返回，不扣次数
                $return['status'] = -8;
                return $return;
            }

            // 扣除次数（照旧版：免费次数不够时 buy_times 按「购买次数 - 本次次数」计，免费次数清零）
            if ($log['times'] + $log['buy_times'] - $num >= 0) {
                if ($log['times'] - $num >= 0) {
                    $times = $log['times'] - $num;
                    $buyTimes = $log['buy_times'];
                } else {
                    $times = 0;
                    // buy_times 为 unsigned，旧版 MySQL 非严格模式把负数存成 0
                    $buyTimes = max(0, $log['buy_times'] - $num);
                }
            } else {
                $times = 0;
                $buyTimes = 0;
            }
            self::updateTimes($uid, $times, $buyTimes, $now);
            $log['times'] = $times;
            $log['buy_times'] = $buyTimes;

            // 胜利处理
            $reportid = 0;
            $return['info']['stage'] = $mission['stage'];
            $isFirst = 0;
            $assess = $battle['info']['assess'] ?? null;
            if ($assess > 0) {
                // 更新成已首杀
                if ($missionLog !== null) {
                    if ($missionLog['first_reward'] === 0) {
                        self::updateMissionLog($uid, $id);
                        $isFirst = 1;
                    }
                } else {
                    $isFirst = 1;
                }
                // 判断该关卡是否是首次击杀
                if ($id === $logs['missionid'] + 1) {
                    // 查询下一关的关卡信息
                    $next = self::getMissionInfo($id + 1);
                    if ($next !== null) {
                        if ($logs['city'] === $next['city']) {
                            if ($logs['stage'] !== $next['stage']) {
                                // 进入下一层
                                $return['info']['stage'] = $next['stage'];
                                $mission['stage'] = $next['stage'];
                            }
                        } else {
                            // 通关
                            $return['info']['complete'] = 1;
                        }
                    }
                    // 修改用户关卡信息
                    self::updateMissionInfo($uid, $mission, $now);
                    $first = 1;
                } else {
                    $first = 0;
                }

                if ($id === self::MAX_ID && $first === 1) {
                    // 通关
                    $return['info']['complete'] = 1;
                }

                // 判断是否可获得宝石
                $double = self::isDouble($now);
                $gemNum = 0;
                for ($i = 0; $i < $num; $i++) {
                    $rands = mt_rand(1, 100);
                    // 双倍奖励活动期间，掉率翻倍（旧版在循环里每次都乘 2，照旧版）
                    if ($double && $isFirst === 0) {
                        $mission['rate'] *= 2;
                    }
                    // 概率计算
                    if ($rands <= $mission['rate']) {
                        $gemNum++;
                    }
                }

                // 添加宝石
                if ($gemNum > 0) {
                    // 双倍活动期间，奖励数量翻倍
                    if ($double && $isFirst === 0) {
                        $gemNum *= 2;
                    }
                    // 添加宝石数量
                    $gem = Gem::ROWS[$mission['gem']] ?? null;
                    if (OwnGem::addGem($uid, (int)$mission['gem'], (int)$mission['gem_level'], $gemNum, $now)['status'] === 1) {
                        $return['info']['gem'] = $mission['gem'];
                        $return['info']['gem_name'] = $gem['name'];
                        $return['info']['gem_type'] = $gem['type'];
                        $return['info']['gem_power'] = $gem['level0'];
                        $return['info']['gem_level'] = $mission['gem_level'];
                        $return['info']['gem_num'] = $gemNum;
                        $return['info']['gem_quality'] = self::gemQuality($mission['gem_level']);
                        // 修改宝石格子占用数
                        OwnGem::addGemuse($uid, $gemNum);
                    }
                }

                // 默认不是首杀
                $return['info']['firstkill'] = 0;
                // 首通记录战报
                if ($first === 1) {
                    // 用户神树信息
                    $treeId = (int)($battle['info']['leftbuff']['tree'] ?? 0);
                    // 添加关卡通过记录
                    $reportid = self::addMissionLog($member, $mission['id'], $treeId, $now);
                    if ($reportid > 0) {
                        $gzipreport = base64_encode(gzcompress((string)json_encode($battle['info'])));
                        // 添加战报
                        self::addReport($reportid, $gzipreport, $mission, $member);
                    }
                }
                if ($isFirst === 1) {
                    // 首杀奖励宝石碎片
                    OwnGem::addChip($uid, (int)$mission['first_chip_num'], self::LOG_CLASS, 'action', $now);
                    $return['info']['first_chip_num'] = $mission['first_chip_num'];
                    $return['info']['firstkill'] = 1;
                } elseif ($mission['chip_num'] > 0) {
                    // 添加宝石碎片
                    $chipNum = $mission['chip_num'] * $num;
                    // 双倍奖励活动期间，奖励翻倍
                    if ($double) {
                        $chipNum *= 2;
                    }
                    OwnGem::addChip($uid, $chipNum, self::LOG_CLASS, 'action', $now);
                    $return['info']['chip'] = $chipNum;
                }
            }

            // 获取新的扫荡价格
            $nowTimes = $log['times'] + $log['buy_times'];
            $return['info'] += self::mopupPrice($nowTimes);

            // 成功返回
            $return['status'] = 1;
            if ($num === 1) {
                $return['battle'] = $battle['info'];
                $return['battle']['left_role'] = [
                    'name' => (string)$member['nickname'],
                    'level' => (int)$member['level'],
                    'gender' => (int)$member['gender'],
                ];
                $return['battle']['right_role'] = [
                    'name' => $mission['name'],
                    'level' => $mission['level'],
                    'type' => $mission['type'],
                ];
            }
            $return['info']['report'] = $reportid;
            $return['info']['times'] = $nowTimes;
            $return['info']['all_chip'] = OwnGem::chip($uid);
            $return['info']['all_gold'] = self::memberValue($uid, 'gold');
            $return['mission']['firstkill'] = $isFirst;
            $return['info']['win'] = $assess;
            return $return;
        });
    }

    /**
     * 购买挑战次数: c=sanctum&m=buy_times（num 次数，type 非 0 为购买，0 只查询价格）
     *
     * status：2 功能未开启；-1 获取用户信息失败；-2 元宝不足；1 成功
     */
    public static function buy_times(Context $ctx): array
    {
        $now = time();
        $return = [];
        $num = (int)$ctx->input('num', 0);
        $type = (int)$ctx->input('type', 0);
        if ($num === 0) {
            $num = 1;
        }
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null || $num < 0) {
            // 旧版没有校验：负数次数会让价格为负、sub_gold 倒加元宝（写入垃圾数据），这里按失败返回
            return ['status' => 0];
        }

        // 判断功能开启状态
        if (!self::isOpen($member)) {
            $return['status'] = 2;
            return $return;
        }
        $uid = (int)$member['uid'];

        return DB::transaction(static function () use ($return, $member, $uid, $num, $type, $now): array {
            // 获取用户信息
            $log = self::getMemberInfo($uid, $now, true);
            if ($log === null) {
                $return['status'] = -1;
                return $return;
            }

            // 购买次数价格
            $price = $num * self::PRICE;

            if ($type !== 0) {
                // 判断元宝是否足够；扣除元宝（并发扣元宝导致余额不足时同样按元宝不足返回）
                if ((int)$member['gold'] < $price || !Member::deductCurrency($uid, gold: $price)) {
                    $return['status'] = -2;
                    return $return;
                }

                // 添加次数
                $times = $log['buy_times'] + $num;
                if (self::updateTimes($uid, $log['times'], $times, $now) === 1) {
                    $log['buy_times'] = $times;
                }
            }
            $return['status'] = 1;
            $return['price'] = $price;
            $return['all_gold'] = self::memberValue($uid, 'gold');
            $return['times'] = $log['times'] + $log['buy_times'];
            return $return;
        });
    }

    /**
     * 获取战报列表: c=sanctum&m=get_mission_report_list（id 关卡id）
     */
    public static function get_mission_report_list(Context $ctx): array
    {
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }

        // 判断功能开启状态
        if (!self::isOpen($member)) {
            $return['status'] = 2;
            return $return;
        }

        $id = (int)$ctx->input('id', 0);
        $id = $id > 0 ? $id : 1;

        $missionLog = self::getMissionLogList($id);

        // 查询关卡名称
        $result = self::getMissionInfo($id);
        $missionid = 1;
        if ($result !== null) {
            $i = ($result['stage'] - 1) * 10;
            $id = $id - ($result['city'] - 1) * 50;
            $n = $id - $i;
            $missionid = sprintf(self::LANG_MISSIONID, $i + $n);
        }

        $return['status'] = 1;
        $return['missionid'] = $missionid;
        $return['list'] = $missionLog;
        return $return;
    }

    /**
     * 获取战报信息: c=sanctum&m=get_report_info（id 战报id）
     *
     * status：2 战报不存在；1 成功
     */
    public static function get_report_info(Context $ctx): array
    {
        $return = ['status' => 0];
        $id = (int)$ctx->input('id', 0);
        $id = $id > 0 ? $id : 1;

        $result = self::getReportById($id);
        if ($result === null) {
            // 攻略不存在 返回2
            $return['status'] = 2;
            return $return;
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
        $return['info']['win'] = $result['report']['assess'] ?? null;
        $return['info']['background'] = $result['background'];
        return $return;
    }

    /**
     * 发送战报到聊天室: c=sanctum&m=send_to_chat（id 关卡id，report 战报id）
     *
     * status：-1 参数不完整；-2 元宝不足 / 关卡不存在；1 成功
     */
    public static function send_to_chat(Context $ctx): array
    {
        // 战报id
        $reportId = (int)$ctx->input('report', 0);
        $id = (int)$ctx->input('id', 0);
        $uid = MemberHandler::resolveUid($ctx);
        $member = self::member($uid);
        if ($id === 0 || $reportId === 0 || $member === null) {
            return ['status' => -1];
        }
        // 判断金币是否足够
        if ((int)$member['gold'] < self::SHARE_PRICE) {
            return ['status' => -2];
        }
        // 查询关卡名字
        $info = self::getMissionInfo($id);
        if ($info === null) {
            return ['status' => -2];
        }
        // 扣除金币
        $allGold = Chat::chargeShare($uid, self::SHARE_PRICE);
        if ($allGold === null) {
            return ['status' => -2];
        }
        $sender = Chat::sender($uid);
        $message = Chat::getMessageStr(25, ['general' => $info['name'], 'id' => $reportId . '|' . $info['city']], $sender);
        Chat::sendToAll($message, 11, $reportId, 0, $sender);
        return ['status' => 1, 'all_gold' => $allGold];
    }

    /**
     * 查看 / 领取每日奖励: c=sanctum&m=get_reward（type 1 领取，multiple 倍率 id：0 不翻倍，1 / 2 为 SanctumReward）
     *
     * status：2 功能未开启；-1 没有圣域记录；-2 获取活动信息失败；-3 今日已领取；-4 还未通过一关；-5 关卡不存在；
     * -6 倍率不存在；-7 元宝不足；1 成功
     */
    public static function get_reward(Context $ctx): array
    {
        $now = time();
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }

        // type 传1是领取
        $type = (int)$ctx->input('type', 0);
        // 几倍奖励
        $multiple = (int)$ctx->input('multiple', 0);

        // 判断功能开启状态
        if (!self::isOpen($member)) {
            $return['status'] = 2;
            return $return;
        }
        $uid = (int)$member['uid'];

        // 同一玩家并发领取：在事务里锁住活动记录行
        return DB::transaction(static function () use ($return, $member, $uid, $type, $multiple, $now): array {
            // 取自己的mission_log
            $missionLog = self::getOpenMap($uid);
            if ($missionLog === []) {
                // 旧版这里写成 $this->output->output->api_return 直接报错退出，这里返回 -1
                $return['status'] = -1;
                return $return;
            }

            $activity = self::lockActivity($uid);
            if ($activity === null) {
                // 获取活动信息失败
                $return['status'] = -2;
                return $return;
            }

            // 判断是否领取过奖励
            if (date('ymd', $activity['sanctumdate']) === date('ymd', $now)) {
                $return['status'] = -3;
                return $return;
            }

            // 获取自己的最大通过关卡
            $missionid = [];
            foreach ($missionLog as $key => $val) {
                $small = $val['missionid'] - (($val['city'] - 1) * 50);
                if ($small > 0) {
                    $missionid[$key] = $small;
                }
            }
            if ($missionid === []) {
                $return['status'] = -4;
                return $return;
            }
            $id = 0;
            $missionKey = 1;
            foreach ($missionid as $key => $val) {
                if ($val >= $id) {
                    $missionKey = $key;
                    $id = $val;
                }
            }

            // 判断是否有通过的关卡
            if ($missionLog[$missionKey]['missionid'] === 0) {
                $return['status'] = -4;
                return $return;
            }

            // 查询关卡奖励
            $missionInfo = self::getMissionInfo($missionLog[$missionKey]['missionid']);
            if ($missionInfo === null) {
                $return['status'] = -5;
                return $return;
            }

            // 取都城占领加成（占领效果 type 5：reward 按 value% 加成）
            $effect = SiegeHandler::getCityEffect($member, $now);
            if ($effect['status'] === 1 && $effect['type'] === 5) {
                $missionInfo['reward'] = (int)($missionInfo['reward'] + $missionInfo['reward'] * ($effect['value'] / 100));
                $return['effect'] = $effect['value'] + 100;
            } else {
                $return['effect'] = 0;
            }

            // 查询领取倍率信息
            $ratio = SanctumReward::ROWS;
            $reward = $missionInfo['reward'];

            if ($type === 1) {
                // 判断是否翻倍
                if ($multiple > 0) {
                    // 判断倍数是否存在
                    if (!isset($ratio[$multiple])) {
                        $return['status'] = -6;
                        return $return;
                    }
                    // 检查元宝；扣除元宝（并发扣元宝导致余额不足时同样按元宝不足返回）
                    $sub = $ratio[$multiple]['sub'];
                    if ((int)$member['gold'] < $sub || !Member::deductCurrency($uid, gold: $sub)) {
                        $return['status'] = -7;
                        return $return;
                    }
                    $reward = (int)($reward * ($ratio[$multiple]['ratio'] / 100));
                }

                // 修改领取状态
                MemberActivity::update($uid, ['sanctumdate' => $now]);
                // 领取奖励
                OwnGem::addChip($uid, $reward, self::LOG_CLASS, 'get_reward', $now);
            }

            // 成功返回
            $return['status'] = 1;
            $return['gem'] = $reward;
            $return['all_gold'] = self::memberValue($uid, 'gold');
            $return['all_gem'] = OwnGem::chip($uid);
            $return['mission'] = [
                'stage' => self::LANG_STAGE[$missionInfo['stage']] ?? false,
                'id' => ($missionInfo['id'] - ($missionInfo['city'] - 1) * 50) - (($missionInfo['stage'] - 1) * 10),
            ];
            $return['normal_reward'] = [
                'gold' => $ratio[1]['sub'],
                'multiple' => $ratio[1]['ratio'] / 100,
            ];
            $return['best_reward'] = [
                'gold' => $ratio[2]['sub'],
                'multiple' => $ratio[2]['ratio'] / 100,
            ];
            return $return;
        });
    }

    /**
     * 四象圣域战斗（移植 battle_model->battle_sanctum → battle_mission($mission_info, 7)）
     *
     * 旧版 check_user_team：玩家 -1（没有阵法）/ -3（武将信息错误）时不战斗、status 非 1、info 为空数组（控制器不看 status，
     * 照旧版继续，assess 取不到按失败）；-2（阵法里没有武将）按空队伍继续战斗（空队伍直接判 -3 大败）。
     *
     * @param array<string, mixed> $mission SanctumMission 行
     * @return array{status: int, info: array<string, mixed>}
     */
    private static function battleSanctum(int $uid, array $mission): array
    {
        $left = TeamBuilder::forMember($uid, pve: true);
        if ($left['status'] === -1 || $left['status'] === -3) {
            return ['status' => $left['status'], 'info' => []];
        }
        $right = TeamBuilder::forMonsters($mission, TeamBuilder::MONSTER_SANCTUM);
        $engine = new BattleEngine($left['matrix_type'], $left['matrix_coe']);
        return [
            'status' => 1,
            'info' => $engine->battle($left['team'], $right['team']),
        ];
    }

    /**
     * 5 / 10 / 50 / 100 次扫荡价格：次数不足部分每次 PRICE 元宝
     *
     * @return array{five_sub: int, ten_sub: int, fifty_sub: int, hundred_sub: int}
     */
    private static function mopupPrice(int $ownTimes): array
    {
        $price = static fn (int $times): int => $times - $ownTimes < 0 ? 0 : ($times - $ownTimes) * self::PRICE;
        return [
            'five_sub' => $price(5),
            'ten_sub' => $price(10),
            'fifty_sub' => $price(50),
            'hundred_sub' => $price(100),
        ];
    }

    /**
     * 宝石品质：gem_level 大于 0 时 ceil(gem_level / 4)，否则 1
     */
    private static function gemQuality(int $gemLevel): int
    {
        return $gemLevel > 0 ? (int)ceil($gemLevel / 4) : 1;
    }

    /**
     * 双倍奖励活动（sanctum 控制器 _initdouble：activity_model->activity_status('double_sanctum')）
     */
    private static function isDouble(int $now): bool
    {
        foreach (ActivityTime::ROWS as $row) {
            if ($row['name'] === 'double_sanctum') {
                return $row['starttime'] <= $now && $row['endtime'] >= $now;
            }
        }
        return false;
    }

    /**
     * 主公等级 140 起开放（构造函数 _open）
     *
     * @param array<string, mixed> $member
     */
    private static function isOpen(array $member): bool
    {
        return (int)$member['level'] >= self::OPEN_LEVEL;
    }

    /**
     * 按圣域 / 层数取关卡（移植 sanctum_model->get_mission_list：ORDER BY id）
     *
     * @return list<array<string, mixed>>
     */
    private static function getMissionList(int $city, int $stage): array
    {
        $return = [];
        foreach (SanctumMission::ROWS as $row) {
            if ($row['city'] === $city && $row['stage'] === $stage) {
                $return[] = $row;
            }
        }
        usort($return, static fn (array $a, array $b): int => $a['id'] <=> $b['id']);
        return $return;
    }

    /**
     * 按 id 取关卡（移植 sanctum_model->get_mission_info）
     *
     * @return array<string, mixed>|null
     */
    private static function getMissionInfo(int $id): ?array
    {
        static $byId = null;
        if ($byId === null) {
            $byId = array_column(SanctumMission::ROWS, null, 'id');
        }
        return $byId[$id] ?? null;
    }

    /**
     * 圣域第 1 层第一个关卡的 id（移植 sanctum_model->get_id_by_level），没有关卡时 0
     */
    private static function getIdByLevel(int $city): int
    {
        $ids = [];
        foreach (SanctumMission::ROWS as $row) {
            if ($row['city'] === $city && $row['stage'] === 1) {
                $ids[] = $row['id'];
            }
        }
        return $ids === [] ? 0 : min($ids);
    }

    /**
     * 按圣域id获取圣域进度（移植 sanctum_model->get_stage_status：没有记录时插入 stage 1、missionid 为第一关 id - 1）
     *
     * 旧版对没有关卡的圣域 id 也会插入一行 missionid -1（unsigned 列存成 0）的垃圾记录；这里不插入，
     * 返回同样的进度（stage 1、missionid -1），后续流程与旧版一致（select_map 返回 -3 / -4）。
     *
     * @return array{uid: int, city: int, stage: int, missionid: int, dateline: int}|null
     */
    private static function getStageStatus(int $uid, int $city, int $now): ?array
    {
        $row = self::openMapRow($uid, $city);
        if ($row !== null) {
            return $row;
        }
        // 查询该圣域的第一个关卡的关卡id
        $missionid = self::getIdByLevel($city);
        $data = ['uid' => $uid, 'city' => $city, 'stage' => 1, 'missionid' => $missionid - 1];
        if ($missionid === 0) {
            return $data + ['dateline' => 0];
        }
        // 并发首次请求只插入一行（主键 uid + city）
        DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_sanctum_open_map` (`uid`, `city`, `stage`, `missionid`) VALUES (:uid, :city, :stage, :missionid)',
            $data
        );
        return self::openMapRow($uid, $city);
    }

    /**
     * @return array{uid: int, city: int, stage: int, missionid: int, dateline: int}|null
     */
    private static function openMapRow(int $uid, int $city): ?array
    {
        $row = DB::table('sanctum_open_map')->select(...self::MAP_COLUMNS)
            ->where('uid', $uid)->where('city', $city)->whereNull('deleted_at')->first();
        return $row === null ? null : array_map('intval', $row);
    }

    /**
     * 玩家全部圣域进度，按圣域 id 索引（移植 sanctum_model->get_open_map）
     *
     * @return array<int, array{uid: int, city: int, stage: int, missionid: int, dateline: int}>
     */
    private static function getOpenMap(int $uid): array
    {
        $return = [];
        $rows = DB::table('sanctum_open_map')->select(...self::MAP_COLUMNS)->where('uid', $uid)->whereNull('deleted_at')->get();
        foreach ($rows as $row) {
            $row = array_map('intval', $row);
            $return[$row['city']] = $row;
        }
        return $return;
    }

    /**
     * 修改圣域进度（移植 sanctum_model->update_mission_info）
     *
     * @param array<string, mixed> $mission
     */
    private static function updateMissionInfo(int $uid, array $mission, int $now): void
    {
        DB::table('sanctum_open_map')->where('uid', $uid)->where('city', (int)$mission['city'])->whereNull('deleted_at')
            ->update(['stage' => (int)$mission['stage'], 'missionid' => (int)$mission['id'], 'dateline' => $now]);
    }

    /**
     * 获取用户信息（移植 sanctum_model->get_member_info：没有记录时建档；跨天把剩余次数重置为 10）
     *
     * @param bool $lock 在事务里加行锁（攻打 / 购买次数）
     * @return array{uid: int, missionlevel: int, missionstage: int, missionid: int, times: int, buy_times: int, dateline: int}|null
     */
    private static function getMemberInfo(int $uid, int $now, bool $lock): ?array
    {
        $row = self::logRow($uid, $lock);
        if ($row === null) {
            // 并发首次请求只插入一行（主键 uid）
            DB::execute(
                'INSERT IGNORE INTO `zc_sanguo_sanctum_log` (`uid`, `times`, `buy_times`, `missionlevel`, `missionstage`, `missionid`, `dateline`)
                 VALUES (:uid, :times, 0, 1, 1, 1, :now)',
                ['uid' => $uid, 'times' => self::DAILY_TIMES, 'now' => $now]
            );
            return self::logRow($uid, $lock);
        }
        // 判断今日是否已经更新过次数（按读到的 dateline 条件更新，并发请求只重置一次）
        if (date('ymd', $now) !== date('ymd', $row['dateline'])) {
            $affected = DB::table('sanctum_log')->where('uid', $uid)->where('dateline', $row['dateline'])->whereNull('deleted_at')
                ->update(['times' => self::DAILY_TIMES, 'dateline' => $now]);
            if ($affected > 0) {
                $row['times'] = self::DAILY_TIMES;
                $row['dateline'] = $now;
            } else {
                $row = self::logRow($uid, $lock);
            }
        }
        return $row;
    }

    /**
     * @return array{uid: int, missionlevel: int, missionstage: int, missionid: int, times: int, buy_times: int, dateline: int}|null
     */
    private static function logRow(int $uid, bool $lock): ?array
    {
        $rows = DB::query(
            'SELECT `' . implode('`, `', self::LOG_COLUMNS) . '` FROM `zc_sanguo_sanctum_log` WHERE `uid` = :uid AND `deleted_at` IS NULL'
            . ($lock ? ' FOR UPDATE' : ''),
            ['uid' => $uid]
        );
        return $rows === [] ? null : array_map('intval', $rows[0]);
    }

    /**
     * 修改攻击次数（移植 sanctum_model->update_times，写入成功返回 1）
     */
    private static function updateTimes(int $uid, int $times, int $buyTimes, int $now): int
    {
        return DB::table('sanctum_log')->where('uid', $uid)->whereNull('deleted_at')
            ->update(['times' => $times, 'buy_times' => $buyTimes, 'dateline' => $now]) > 0 ? 1 : 0;
    }

    /**
     * 玩家活动信息并加行锁（member_model->get_activity_info：没有记录时按旧版初始值建档）
     *
     * @return array{uid: int, sanctumdate: int}|null
     */
    private static function lockActivity(int $uid): ?array
    {
        MemberActivity::ensure($uid);
        $rows = DB::query(
            'SELECT `uid`, `sanctumdate` FROM `zc_sanguo_member_activity` WHERE `uid` = :uid AND `deleted_at` IS NULL FOR UPDATE',
            ['uid' => $uid]
        );
        return $rows === [] ? null : array_map('intval', $rows[0]);
    }

    /**
     * 玩家某关的通过记录（移植 sanctum_model->get_mission_log($id, 1)：按 dateline 倒序第一条）
     *
     * @return array<string, mixed>|null
     */
    private static function getMissionLog(int $uid, int $missionid): ?array
    {
        $row = DB::table('sanctum_mission_log')->select(...self::MISSION_LOG_COLUMNS)
            ->where('missionid', $missionid)->where('uid', $uid)->whereNull('deleted_at')
            ->orderBy('dateline', 'DESC')->first();
        return $row === null ? null : self::castLog($row);
    }

    /**
     * 玩家某关的通过记录（移植 sanctum_model->get_mission_log_by_uid($uid, $id)）
     *
     * @return array<string, mixed>|null
     */
    private static function getMissionLogByUid(int $uid, int $missionid): ?array
    {
        $row = DB::table('sanctum_mission_log')->select(...self::MISSION_LOG_COLUMNS)
            ->where('uid', $uid)->where('missionid', $missionid)->whereNull('deleted_at')->first();
        return $row === null ? null : self::castLog($row);
    }

    /**
     * 关卡攻略列表：最近 7 条通过记录（移植 sanctum_model->get_mission_log($id)：ORDER BY dateline DESC LIMIT 7）
     *
     * @return list<array<string, mixed>>
     */
    private static function getMissionLogList(int $missionid): array
    {
        $rows = DB::table('sanctum_mission_log')->select(...self::MISSION_LOG_COLUMNS)
            ->where('missionid', $missionid)->whereNull('deleted_at')
            ->orderBy('dateline', 'DESC')->orderBy('id', 'DESC')->limit(7)->get();
        return array_map(self::castLog(...), $rows);
    }

    /**
     * 更新成已首杀（移植 sanctum_model->update_mission_log）
     */
    private static function updateMissionLog(int $uid, int $missionid): void
    {
        DB::table('sanctum_mission_log')->where('uid', $uid)->where('missionid', $missionid)->whereNull('deleted_at')
            ->update(['first_reward' => 1]);
    }

    /**
     * 添加关卡通过记录（移植 sanctum_model->add_mission_log），返回记录 id（即战报 id）
     *
     * @param array<string, mixed> $member
     */
    private static function addMissionLog(array $member, int $missionid, int $tree, int $now): int
    {
        return (int)DB::table('sanctum_mission_log')->insertGetId([
            'missionid' => $missionid,
            'uid' => (int)$member['uid'],
            'nickname' => (string)$member['nickname'],
            'level' => (int)$member['level'],
            'dateline' => $now,
            'tree' => $tree,
            'first_reward' => 1,
        ]);
    }

    /**
     * 添加战报（移植 sanctum_model->add_report：report 列存 json_encode(base64(gzcompress(战报 json)))）
     *
     * @param array<string, mixed> $mission
     * @param array<string, mixed> $member
     */
    private static function addReport(int $id, string $report, array $mission, array $member): void
    {
        DB::table('sanctum_report')->insert([
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
     * 按 id 取战报（移植 sanctum_model->get_report_by_id），不存在时 null
     *
     * @return array{id: int, nickname: string, userlevel: int, name: string, level: int, background: int, report: mixed}|null
     */
    private static function getReportById(int $id): ?array
    {
        $result = DB::table('sanctum_report')->select('id', 'report', 'nickname', 'userlevel', 'name', 'level', 'background')
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

    /**
     * 请求结束时主公的某个数值（旧版 $this->member 在 sub_gold 后同步更新）
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
