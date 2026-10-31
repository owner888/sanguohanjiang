<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Battle\BattleEngine;
use Hanjiang\Battle\TeamBuilder;
use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\General\TavernHandler;
use Hanjiang\MasterData\GeneraltaskMission;
use Hanjiang\MasterData\Soul;
use Hanjiang\Model\FreeLeap;
use Hanjiang\Model\General;
use Hanjiang\Model\Member;
use Hanjiang\Model\MemberActivity;
use Hanjiang\Model\MemberJade;
use Hanjiang\Model\OwnGem;
use Hanjiang\Model\OwnSoul;
use Hanjiang\Model\StatPay;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 神将之门（移植旧版 controllers/generaltask.php + models/generaltask_model.php + battle_model->battle_general）
 *
 * 主公 71 级开启。挑选一名出征武将单挑关卡（MasterData GeneraltaskMission → GeneraltaskMonster，怪物血量乘天赋系数；
 * 武将不用阵法、站 4 号位、没有阵法加成），每天 10 次（member_activity.generaltasktimes，每日计划任务重置；可花 30 元宝买 1 次）。
 * 首杀发首杀奖励（first_type / first_value，不能押注）；已首杀的关卡发普通奖励（re_type / soul），押注 type 1 / 2 花 270 / 3000
 * 元宝、奖励 × 10 / 100。打通下一关时推进进度（generaltask_log.stage）并记战报（generaltask_report，每关列表取最近 7 条）。
 * 客户端 generaltaskworld.js 调用 index、get_list（p 页码）、action（id 关卡、gid 武将、type 押注）、get_report_list（id）、
 * get_report（id 战报）、buy_number（t 1 购买，否则只查价格）。
 *
 * 同一玩家的挑战 / 购买在玩家行锁里串行执行。
 * 客户端的 generaltask&m=send_to_chat 旧版没有，不提供。
 */
final class GeneraltaskHandler
{
    /** 开启等级 */
    private const int OPEN_LEVEL = 71;

    /** 押注价格 */
    private const array PRICE = [1 => 270, 2 => 3000];

    /** 购买挑战次数价格（旧版改为恒定价格） */
    private const int BUY_PRICE = 30;

    /** 每页关卡数 */
    private const int PERPAGE = 3;

    /**
     * 初始化接口: c=generaltask&m=index
     */
    public static function index(Context $ctx): array
    {
        /**
         *-1 等级不够，未开启
         * 2 没有出征武将
         */
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        $uid = (int)$member['uid'];
        if ((int)$member['level'] < self::OPEN_LEVEL) {
            $return['status'] = -1;
            return $return;
        }
        // [将领模型]获得出征将领列表,需要排序: 等级
        $list = General::battleList($uid);
        if ($list === []) {
            $return['status'] = 2;
            return $return;
        }
        $newlist = [];
        foreach ($list as $val) {
            $newlist[] = [
                'id' => (int)$val['id'],
                'generalid' => (int)$val['generalid'],
                'quality' => (int)$val['quality'],
                'name' => (string)$val['name'],
                'level' => (int)$val['level'],
                'image' => (int)$val['image'],
            ];
        }
        // 得到我的活动次数
        $return['status'] = 1;
        $return['number'] = self::times($uid);
        $return['list'] = $newlist;
        return $return;
    }

    /**
     * 取关卡列表: c=generaltask&m=get_list（p 页码，不传为当前进度所在页）
     */
    public static function get_list(Context $ctx): array
    {
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        $uid = (int)$member['uid'];
        if ((int)$member['level'] < self::OPEN_LEVEL) {
            $return['status'] = -1;
            return $return;
        }
        $page = (int)$ctx->input('p', 0);
        // 取我的信息
        $info = self::memberInfo($uid);
        if (!$page) {
            $page = (int)ceil(($info['stage'] + 1) / self::PERPAGE);
        }
        // 取所有关卡信息
        $list = self::missionPage($page);
        $passed = self::missionLogIds($uid, array_column($list, 'id'));
        foreach ($list as $key => $val) {
            // 查看本关卡是否有记录（首杀过为 0）
            $list[$key]['firstkill'] = isset($passed[$val['id']]) ? 0 : 1;
            $list[$key]['open'] = $val['id'] <= $info['stage'] + 1 ? 1 : 0;
            $list[$key]['pass'] = $val['id'] <= $info['stage'] ? 1 : 0;
            // 对首杀 / 普通奖励类型将魂（18）特殊处理：换成将魂模板
            if ($val['first_type'] === 18 && $val['first_value'] > 0) {
                $list[$key]['first_value'] = Soul::ROWS[$val['first_value']] ?? [];
            }
            if ($val['re_type'] === 18 && $val['soul'] > 0) {
                $list[$key]['soul'] = Soul::ROWS[$val['soul']] ?? [];
            }
        }
        // 取关卡总页数
        $return['perpage'] = (int)ceil(count(GeneraltaskMission::ROWS) / self::PERPAGE);
        $return['status'] = 1;
        $return['list'] = $list;
        $return['page'] = $page;
        $return['price'] = self::PRICE;
        return $return;
    }

    /**
     * 战斗: c=generaltask&m=action（id 关卡、gid 武将、type 押注 0 / 1 / 2）
     */
    public static function action(Context $ctx): array
    {
        /**
         *-100 首杀不能押注
         *-1 等级不够
         *-2 参数丢失
         *-3 获取奖励信息失败（武将不存在）
         *-4 获取关卡信息失败
         *-5 获取用户信息失败
         *-6 关卡未开启
         *-7 挑战次数不足
         *-8 元宝不足
         */
        $uid = MemberHandler::resolveUid($ctx);
        $id = (int)$ctx->input('id', 0);
        $gid = (int)$ctx->input('gid', 0);
        $type = (int)$ctx->input('type', 0);
        $base = ['status' => 0, 'info' => ['win' => 0, 'soul' => 0], 'battle' => []];
        if (self::member($uid) === null) {
            return $base;
        }
        $now = time();
        return Member::locked($uid, static function () use ($uid, $id, $gid, $type, $now, $base): array {
            $return = $base;
            $member = self::member($uid);
            if ((int)$member['level'] < self::OPEN_LEVEL) {
                $return['status'] = -1;
                return $return;
            }
            // 检查参数是否完整（旧版押注倍数不在 1 / 2 时价格取不到、按 100 倍发奖；这里按参数错误处理）
            if (!$id || !$gid || !in_array($type, [0, 1, 2], true)) {
                $return['status'] = -2;
                return $return;
            }
            // 查询将领信息
            if (General::own($uid, $gid) === null) {
                $return['status'] = -3;
                return $return;
            }
            // 取关卡信息
            $missionInfo = GeneraltaskMission::ROWS[$id] ?? null;
            if ($missionInfo === null) {
                $return['status'] = -4;
                return $return;
            }
            // 获取用户信息
            $memberInfo = self::memberInfo($uid);
            // 关卡未开启
            if ($id > $memberInfo['stage'] + 1) {
                $return['status'] = -6;
                return $return;
            }
            // 取我的挑战次数
            $times = self::times($uid);
            if ($times === 0) {
                $return['status'] = -7;
                return $return;
            }
            // 判断金币是否足够
            if ($type && (int)$member['gold'] < self::PRICE[$type]) {
                $return['status'] = -8;
                return $return;
            }
            // 查看本关卡是否有记录
            $umiss = self::missionLogIds($uid, [$id]) !== [];
            if (!$umiss && $type) {
                // 首杀不能押注
                $return['status'] = -100;
                return $return;
            }
            // 调用战斗算法
            $battle = self::battleGeneral($uid, $missionInfo, $gid);
            $battle['left_role'] = [
                'name' => (string)$member['nickname'],
                'level' => (int)$member['level'],
                'gender' => (int)$member['gender'],
            ];
            $battle['right_role'] = ['name' => $missionInfo['name'], 'level' => $missionInfo['level']];
            $assess = (int)($battle['assess'] ?? 0);

            // 默认是首杀
            $return['info']['firstkill'] = 1;
            if ($assess >= 1) {
                // 添加记录，修改关卡挑战记录
                self::addMissionLog($member, $id, $now);
                if ($id === $memberInfo['stage'] + 1) {
                    DB::table('generaltask_log')->where('uid', $uid)->whereNull('deleted_at')->update(['stage' => $id]);
                }
                // 添加奖励
                $soulNum = 1;
                if ($type) {
                    $ante = $type === 1 ? 10 : 100;
                    if ($missionInfo['re_type'] === 8) {
                        $soulNum = $ante;
                    } else {
                        $missionInfo['soul'] = $missionInfo['soul'] * $ante;
                    }
                }
                if ($umiss) {
                    // 已经首杀过
                    $return['info']['firstkill'] = 0;
                    if ($missionInfo['soul'] && $missionInfo['re_type'] > 0) {
                        $reward = self::addReward($uid, $missionInfo['re_type'], $missionInfo['soul'], 0, $soulNum);
                        if (isset($reward['re_type'], $reward['soul'])) {
                            $return['info']['re_type'] = $reward['re_type'];
                            $return['info']['soul'] = $reward['soul'];
                            if ($missionInfo['re_type'] === 8) {
                                $return['info']['soul_num'] = $soulNum;
                            }
                        }
                    }
                } else {
                    // 首杀奖励
                    $reward = self::addReward($uid, $missionInfo['first_type'], $missionInfo['first_value'], 1);
                    if (isset($reward['first_type'], $reward['first_value'])) {
                        $return['info']['first_type'] = $reward['first_type'];
                        $return['info']['first_value'] = $reward['first_value'];
                    }
                }
            } else {
                $return['info']['firstkill'] = 0;
            }
            // 减少挑战次数
            $number = $times - 1;
            MemberActivity::update($uid, ['generaltasktimes' => $number]);
            // 如果押注，减少金币
            if ($type) {
                Member::deductCurrency($uid, gold: self::PRICE[$type]);
                // 消费统计记录
                StatPay::record($uid, 'generaltask_battle', self::PRICE[$type]);
            }
            // 首杀记录战报
            if ($id === $memberInfo['stage'] + 1 && $assess >= 1) {
                DB::table('generaltask_report')->insert([
                    'report' => json_encode($battle, JSON_UNESCAPED_UNICODE),
                    'missionid' => $id,
                    'name' => $missionInfo['name'],
                    'level' => (int)$member['level'],
                    'nickname' => (string)$member['nickname'],
                ]);
            }
            $after = self::member($uid);
            $return['battle'] = $battle;
            $return['info']['number'] = $number;
            $return['info']['win'] = $battle['assess'] ?? null;
            $return['info']['gold'] = (int)$after['gold'];
            $return['info']['all_soul'] = (int)$after['soul'];
            $return['status'] = 1;
            return $return;
        });
    }

    /**
     * 取战报列表: c=generaltask&m=get_report_list（id 关卡；最近 7 条）
     */
    public static function get_report_list(Context $ctx): array
    {
        $return = ['status' => 0];
        $id = (int)$ctx->input('id', 0);
        $rows = DB::table('generaltask_report')->select('id', 'missionid', 'report', 'nickname', 'level', 'name', 'tree')
            ->where('missionid', $id)->whereNull('deleted_at')->orderBy('id', 'DESC')->limit(7)->get();
        if ($rows !== []) {
            $return['status'] = 1;
            $return['list'] = array_map(static fn (array $row): array => [
                'id' => (int)$row['id'],
                'missionid' => (int)$row['missionid'],
                'report' => (string)$row['report'],
                'nickname' => (string)$row['nickname'],
                'level' => (int)$row['level'],
                'name' => (string)$row['name'],
                'tree' => (int)$row['tree'],
            ], $rows);
        }
        return $return;
    }

    /**
     * 获取单条战报: c=generaltask&m=get_report（id 战报）
     */
    public static function get_report(Context $ctx): array
    {
        $return = ['status' => 0];
        $id = (int)$ctx->input('id', 0);
        $report = DB::table('generaltask_report')->where('id', $id)->whereNull('deleted_at')->value('report');
        if ($report === null) {
            $return['status'] = -1;
            return $return;
        }
        $info = json_decode((string)$report, true);
        $return['status'] = 1;
        $return['info']['win'] = $info['assess'] ?? null;
        $return['battle'] = $info;
        return $return;
    }

    /**
     * 购买挑战次数: c=generaltask&m=buy_number（t 1 购买，否则只返回价格与剩余次数）
     */
    public static function buy_number(Context $ctx): array
    {
        /**
         *-1 元宝不足
         * 1 成功
         */
        $uid = MemberHandler::resolveUid($ctx);
        $t = (int)$ctx->input('t', 0);
        if (self::member($uid) === null) {
            return ['status' => 0];
        }
        $now = time();
        return Member::locked($uid, static function () use ($uid, $t, $now): array {
            $return = ['status' => 1, 'price' => self::BUY_PRICE];
            // 查询今日购买次数
            $number = self::buyTimes($uid, $now);
            // 取自己的剩余次数
            $times = self::times($uid);
            $return['times'] = $times;
            if ($t === 1) {
                // 扣除金币
                if (!Member::deductCurrency($uid, gold: self::BUY_PRICE)) {
                    $return['status'] = -1;
                    return $return;
                }
                // 修改次数,添加购买记录
                if ($number['status'] === 0) {
                    DB::execute(
                        'INSERT INTO `zc_sanguo_generaltask_log` (`uid`, `buytimes`, `dateline`) VALUES (:uid, 1, :now)
                         ON DUPLICATE KEY UPDATE `buytimes` = 1, `dateline` = :now2',
                        ['uid' => $uid, 'now' => $now, 'now2' => $now]
                    );
                } else {
                    DB::table('generaltask_log')->where('uid', $uid)->whereNull('deleted_at')
                        ->update(['buytimes' => $number['times'] + 1, 'dateline' => $now]);
                }
                // 增加次数
                MemberActivity::update($uid, ['generaltasktimes' => $times + 1]);
                // 消费统计记录
                StatPay::record($uid, 'generaltask_times', self::BUY_PRICE);
                // 返回剩余金币和次数
                $return['all_gold'] = (int)self::member($uid)['gold'];
                $return['times'] = $times + 1;
            }
            return $return;
        });
    }

    /**
     * 神将之门战斗（battle_model->battle_general：PVE，玩家只上一名武将，怪物血量乘天赋系数）
     *
     * @param array<string, mixed> $missionInfo
     * @return array<string, mixed> 旧版 battle() 的战斗结果；玩家队伍出错（-1 / -3）时为空数组（旧版同样不战斗）
     */
    private static function battleGeneral(int $uid, array $missionInfo, int $gid): array
    {
        $left = TeamBuilder::forMember($uid, pve: true, arguGid: $gid);
        if ($left['status'] === -1 || $left['status'] === -3) {
            return [];
        }
        $right = TeamBuilder::forMonsters($missionInfo, TeamBuilder::MONSTER_GENERALTASK);
        $engine = new BattleEngine($left['matrix_type'], $left['matrix_coe']);
        return $engine->battle($left['team'], $right['team']);
    }

    /**
     * 增加奖励（add_reward）
     *
     * 类型：1 元宝；2 银币；3 黄宝石；4 紫宝石；5 原魂；6 玉石；7 声望；9 魔石碎片；10 突飞卡；18 将魂（$value 为将魂 id，数量 $num）。
     * 旧版 8 号（装备）没有发放分支，返回值 soul / first_value 为空值，照旧版。
     *
     * @return array<string, mixed>
     */
    private static function addReward(int $uid, int $type, int $value, int $isFirst = 0, int $num = 1): array
    {
        $return = [];
        if ($type <= 0 || $value <= 0) {
            return $return;
        }
        switch ($type) {
            case 1:
                Member::addCurrency($uid, gold: $value);
                break;
            case 2:
                Member::addCurrency($uid, silver: $value);
                break;
            case 3:
                TavernHandler::addJade($uid, 2, $value, 'generaltask', 'action');
                break;
            case 4:
                TavernHandler::addJade($uid, 3, $value, 'generaltask', 'action');
                break;
            case 5:
                OwnSoul::addSoul($uid, $value, 0, 'generaltask', 'action');
                break;
            case 6:
                MemberJade::add($uid, $value, 'generaltask', 'action');
                break;
            case 7:
                Member::addReputation($uid, $value);
                break;
            case 9:
                OwnGem::addChip($uid, $value, 'generaltask', 'action');
                break;
            case 10:
                FreeLeap::add($uid, $value);
                break;
            case 18:
                if (isset(Soul::ROWS[$value])) {
                    OwnSoul::addHeroSoul($uid, $value, 0, $num);
                }
                break;
        }
        if ($isFirst === 1) {
            $return['first_type'] = $type;
            $return['first_value'] = $type === 8 ? null : $value;
        } else {
            $return['re_type'] = $type;
            $return['soul'] = $type === 8 ? null : $value;
        }
        return $return;
    }

    /**
     * 神将之门记录（get_member_info：没有时建档 stage 0）
     *
     * @return array{uid: int, stage: int, buytimes: int, dateline: int}
     */
    private static function memberInfo(int $uid): array
    {
        DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_generaltask_log` (`uid`, `stage`, `buytimes`, `dateline`) VALUES (:uid, 0, 0, 0)',
            ['uid' => $uid]
        );
        $row = DB::table('generaltask_log')->select('uid', 'stage', 'buytimes', 'dateline')->where('uid', $uid)->whereNull('deleted_at')->first();
        return array_map('intval', $row ?? ['uid' => $uid, 'stage' => 0, 'buytimes' => 0, 'dateline' => 0]);
    }

    /**
     * 今日购买次数（get_times：status 0 没有记录）
     *
     * @return array{status: int, times: int}
     */
    private static function buyTimes(int $uid, int $now): array
    {
        $row = DB::table('generaltask_log')->select('buytimes', 'dateline')->where('uid', $uid)->whereNull('deleted_at')->first();
        if ($row === null) {
            return ['status' => 0, 'times' => 0];
        }
        return ['status' => 1, 'times' => date('ymd', (int)$row['dateline']) === date('ymd', $now) ? (int)$row['buytimes'] : 0];
    }

    /**
     * 关卡分页（get_info(0, page)：每页 3 关，页码 0 按第 1 页）
     *
     * @return list<array<string, mixed>>
     */
    private static function missionPage(int $page): array
    {
        $page = $page ? $page - 1 : 0;
        $rows = GeneraltaskMission::ROWS;
        ksort($rows);
        return array_slice(array_values($rows), max(0, $page) * self::PERPAGE, self::PERPAGE);
    }

    /**
     * 玩家通过过的关卡（get_mission_log）：关卡 id => true
     *
     * @param list<int> $ids
     * @return array<int, bool>
     */
    private static function missionLogIds(int $uid, array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $return = [];
        foreach (DB::table('generaltask_mission_log')->select('missionid')->where('uid', $uid)->whereIn('missionid', $ids)->whereNull('deleted_at')->get() as $row) {
            $return[(int)$row['missionid']] = true;
        }
        return $return;
    }

    /**
     * 添加关卡通过记录（add_mission_log：每次胜利一条）
     *
     * @param array<string, mixed> $member
     */
    private static function addMissionLog(array $member, int $id, int $now): void
    {
        DB::table('generaltask_mission_log')->insert([
            'missionid' => $id,
            'uid' => (int)$member['uid'],
            'nickname' => (string)$member['nickname'],
            'level' => (int)$member['level'],
            'dateline' => $now,
            'first_reward' => 1,
        ]);
    }

    /**
     * 今日剩余挑战次数（member_activity.generaltasktimes；没有记录时建档）
     */
    private static function times(int $uid): int
    {
        MemberActivity::ensure($uid);
        return (int)(MemberActivity::many([$uid], ['generaltasktimes'])[$uid]['generaltasktimes'] ?? 0);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function member(int $uid): ?array
    {
        return $uid > 0 ? DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first() : null;
    }
}
