<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Pvp;

use Hanjiang\Cross\Server;
use Hanjiang\Cross\WarServer;
use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\General\TavernHandler;
use Hanjiang\MasterData\Equip;
use Hanjiang\MasterData\EquipEssence;
use Hanjiang\MasterData\MountTemp;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\Chat;
use Hanjiang\Model\Equipment;
use Hanjiang\Model\FreeLeap;
use Hanjiang\Model\Member;
use Hanjiang\Model\MemberJade;
use Hanjiang\Model\MemberStone;
use Hanjiang\Model\OwnGem;
use Hanjiang\Model\OwnMount;
use Hanjiang\Model\War;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 武斗会（移植旧版 s1 controllers/war.php + models/war_model.php；服务器端见 Cross\WarServer，计划任务见 Cron\WarCron）
 *
 * 客户端 warworld.js / warheroworld.js / scoreshoppop.js / miscpop.js 调用 index、entry、sync_battle_info、support、get_support、
 * get_report_list、get_reward_list、get_reward、get_report_info、share_report、get_palace_list、get_rank_list、point_shop、
 * point_buy。不移植：palace / palace_report（战帝堂的副本，客户端不调用）。
 *
 * 照旧版的等级判断：构造函数先判断「80 级以下为 1」再判断「60 级以下为 2」，2 永远不会出现，所以只有报名、同步、历程
 * 要求 80 级（其余写着「等级太低」的分支都走不到）。旧版几处等级太低输出后没有 return（历程、奖励、支持数），这里输出即返回。
 * 时间（每月 19 ~ 26 日 18 点报名、26 日起每天 19 点开打）由服务器端按自然月计算，当前时间取 BossClock（生产恒为 time()）。
 */
final class WarHandler
{
    /** 分享战报价格 */
    private const int SHARE_PRICE = 2;

    /** s1 lang war_lang.php */
    private const array LANG_TURN = [1 => '第一场', 2 => '第二场', 3 => '第三场'];
    private const array LANG_RANK = [
        1 => '第一名', 2 => '第二名', 4 => '四强', 8 => '八强', 16 => '十六强', 32 => '三十二强', 64 => '海选半决赛', 128 => '海选第一轮',
        256 => '海选第二轮', 512 => '海选第三轮', 1024 => '海选第四轮', 2048 => '海选第五轮', 4096 => '海选第六轮', 8192 => '海选第七轮',
        16384 => '海选第八轮',
    ];
    private const array LANG_PRE_ROUND = [
        1 => '第一轮', 2 => '第二轮', 3 => '第三轮', 4 => '第四轮', 5 => '第五轮', 6 => '第六轮', 7 => '第七轮', 8 => '第八轮', 9 => '第九轮',
        10 => '第十轮', 11 => '第十一轮', 12 => '第十二轮', 13 => '第十三轮', 14 => '第十四轮', 15 => '第十五轮',
    ];
    private const array LANG_ROUND = [
        1 => '决赛', 2 => '半决赛', 4 => '四强赛', 8 => '八强赛', 16 => '十六强赛', 32 => '海选决赛', 64 => '海选半决赛', 128 => '海选第一轮',
        256 => '海选第二轮', 512 => '海选第三轮', 1024 => '海选第四轮', 2048 => '海选第五轮', 4096 => '海选第六轮', 8192 => '海选第七轮',
        16384 => '海选第八轮',
    ];
    private const array LANG_ROUND_DAY = [1 => '预赛', 2 => '初赛', 3 => '复赛', 4 => '决赛'];
    private const array LANG_ROUND_NAME = [
        1 => '海选第一轮', 2 => '海选第二轮', 4 => '海选第三轮', 8 => '海选第四轮', 16 => '海选第五轮', 32 => '海选第六轮', 64 => '海选第七轮',
        128 => '海选第八轮',
    ];

    /**
     * 武斗会入口: c=war&m=index
     */
    public static function index(Context $ctx): array
    {
        /**
         * -1 获取武斗会信息失败
         *  1 成功
         */
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return ['status' => 0];
        }
        $uc = WarServer::index($member['uid'], Server::id());
        if ($uc['status'] !== 1) {
            return ['status' => -1];
        }
        if ($uc['stage'] === 3 && isset($uc['info']['play'])) {
            $info = $uc['info'];
            $round = $info['round'];
            // 赛制：决赛三局两胜，其余一局定胜负
            $info['mode'] = $uc['day'] !== 4 ? 1 : 2;
            $info['play']['left']['server'] = Server::serverName($info['play']['left']['serverid']);
            $info['play']['right']['server'] = Server::serverName($info['play']['right']['serverid']);
            $info['play']['left']['first'] = $info['play']['right']['first'] = 0;
            $maxRound = $info['max_round'];
            if ($info['play']['left']['win'] === 2 || $info['play']['right']['win'] === 2) {
                $info['type'] = self::LANG_ROUND[intdiv($round, 2)] ?? '';
            } else {
                if ($round <= 64) {
                    $info['type'] = self::LANG_ROUND[$round] ?? '';
                } else {
                    $info['type'] = $round > 0 ? (self::LANG_ROUND_NAME[intdiv($maxRound, $round)] ?? '') : '';
                }
                if ($info['play']['right']['support'] > $info['play']['left']['support']) {
                    $info['play']['right']['first'] = 1;
                } else {
                    $info['play']['left']['first'] = 1;
                }
                if ($info['stage'] === 2) {
                    $info['play']['right']['first'] = $info['play']['left']['first'];
                    $info['play']['left']['first'] = 1 - $info['play']['left']['first'];
                } elseif ($info['stage'] === 3 && $info['play']['left']['win'] + $info['play']['right']['win'] < 2) {
                    // 异常情况处理
                    $info['play']['left']['win'] = 2;
                }
            }
            $uc['info'] = $info;
        } elseif ($uc['stage'] === 4) {
            foreach ($uc['info']['list'] as $key => $value) {
                $uc['info']['list'][$key]['round'] = self::LANG_RANK[$value['round']] ?? '';
            }
            War::setEndStage();
        } elseif ($uc['stage'] === 6) {
            foreach ($uc['info']['list'] as $key => $val) {
                $uc['info']['list'][$key]['server'] = Server::serverName($val['serverid']);
            }
        }
        return [
            'status' => 1,
            'stage' => $uc['stage'],
            // 斗士堂是否开启
            'palace' => $uc['palace'],
            'cd' => $uc['cd'],
            'limit_level' => $uc['limit_level'],
            'day' => $uc['day'],
            'is_entry' => $uc['info']['is_entry'],
            'info' => $uc['info'],
        ];
    }

    /**
     * 本届武神榜: c=war&m=get_rank_list
     */
    public static function get_rank_list(Context $ctx): array
    {
        /**
         * -1 获取排名失败
         *  1 成功
         */
        $result = WarServer::rankListResult();
        if ($result['status'] !== 1) {
            return ['status' => -1, 'list' => []];
        }
        $list = [];
        foreach ($result['list'] as $key => $val) {
            $val['round'] = self::LANG_RANK[$val['round']] ?? '';
            $val['server'] = Server::serverName($val['serverid']);
            $list[$key] = $val;
        }
        return ['status' => 1, 'list' => $list];
    }

    /**
     * 报名: c=war&m=entry
     */
    public static function entry(Context $ctx): array
    {
        /**
         * -5 已报名
         * -4 获取报名条件失败
         * -3 输入参数有误
         * -2 报名结束
         * -1 报名未开启
         *  0 失败
         *  1 成功
         *  2 等级不足
         */
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return ['status' => 0];
        }
        if (self::levelLimit($member['level']) >= 1) {
            // 等级太低
            return ['status' => 2];
        }
        $limitLevel = War::entryLevel();
        if ($limitLevel === 0) {
            return ['status' => -4];
        }
        if ($member['level'] < $limitLevel) {
            return ['status' => 2];
        }
        $uc = WarServer::entry(Server::id(), $member['uid'], $member['nickname'], $member['gender']);
        $return = ['status' => match ($uc['status']) {
            -4 => -5,
            -3 => -3,
            -2 => -2,
            -1 => -1,
            1 => 1,
            default => 0,
        }];
        if ($return['status'] === 1) {
            // 添加报名缓存
            War::addEntryKey($member['uid']);
            $return['list'] = $uc['list'];
        }
        return $return;
    }

    /**
     * 斗士堂（各期四强）: c=war&m=get_palace_list&all=（all 为 1 时按期给出全部，否则只给最近一期）
     */
    public static function get_palace_list(Context $ctx): array
    {
        /**
         * -1 比赛还未结束，还未决出武神
         *  1 成功
         */
        $all = (int)$ctx->input('all', 0);
        $result = WarServer::palaceList($all);
        if ($result['status'] !== 1) {
            return ['status' => -1, 'list' => []];
        }
        $return = ['status' => 1, 'list' => []];
        foreach ($result['list'] as $val) {
            $list = [];
            foreach ($val as $v) {
                $v['server'] = Server::serverName($v['serverid']);
                $list[] = $v;
            }
            if ($all !== 1) {
                $return['list'] = $list;
            } else {
                $return['list'][] = $list;
            }
        }
        return $return;
    }

    /**
     * 同步玩家战斗信息: c=war&m=sync_battle_info
     */
    public static function sync_battle_info(Context $ctx): array
    {
        /**
         * -6 等级太低
         * -5 保存信息失败
         * -4 获取信息失败
         * -3 报名未结束
         * -2 同步过于频繁
         * -1 未报名
         *  1 成功
         */
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return ['status' => 0];
        }
        if (self::levelLimit($member['level']) >= 1) {
            return ['status' => -6];
        }
        $uid = $member['uid'];
        if (War::isSync($uid)) {
            return ['status' => -2];
        }
        $sync = WarServer::syncBattleInfo($uid, Server::id());
        if ($sync['status'] === 1) {
            War::sync($uid, BossClock::now());
        }
        return ['status' => $sync['status'], 'info' => $sync['info']];
    }

    /**
     * 支持战斗中的一方: c=war&m=support&side=
     */
    public static function support(Context $ctx): array
    {
        /**
         * -1 支持失败
         *  0 失败
         *  1 成功
         */
        $uid = MemberHandler::resolveUid($ctx);
        if (self::member($uid) === null) {
            return ['status' => 0];
        }
        $support = WarServer::support((int)$ctx->input('side', 0), Server::id(), $uid);
        return ['status' => $support['status'] === 1 ? 1 : -1];
    }

    /**
     * 获得支持数: c=war&m=get_support&id=
     */
    public static function get_support(Context $ctx): array
    {
        /**
         * -1 获取失败
         *  1 成功
         */
        $id = (int)$ctx->input('id', 0);
        $cache = War::supportCache($id);
        if ($cache === []) {
            $result = WarServer::getSupport($id);
            if ($result['status'] !== 1) {
                return ['status' => -1];
            }
            War::setSupportCache($id, $result['support']);
            return ['status' => 1, 'left' => $result['support']['left'], 'right' => $result['support']['right']];
        }
        return ['status' => 1, 'left' => (int)$cache['left'], 'right' => (int)$cache['right']];
    }

    /**
     * 历程: c=war&m=get_report_list&uid=&serverid=&phase=
     */
    public static function get_report_list(Context $ctx): array
    {
        /**
         * -4 等级太低
         * -3 获取历程失败
         *  0 失败
         *  1 成功
         */
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return ['status' => 0];
        }
        if (self::levelLimit($member['level']) >= 1) {
            return ['status' => -4];
        }
        $uid = (int)$ctx->input('uid', 0);
        $serverid = $uid === $member['uid'] ? Server::id() : (int)$ctx->input('serverid', 0);
        $phase = (int)$ctx->input('phase', 0);
        $list = WarServer::reportList($serverid, $uid, $phase);
        if ($list['status'] !== 1) {
            return ['status' => -3];
        }
        $info = $list['info'];
        $info['rank'] = $info['round'] > 32 ? '' : (self::LANG_RANK[$info['round']] ?? '');
        $info['server'] = Server::serverName($info['serverid']);
        $listArr = [];
        foreach ($list['list'] as $value) {
            if ($value['day'] <= 3) {
                $value['turn'] = self::LANG_PRE_ROUND[$value['round']] ?? '';
                $value['round'] = self::LANG_ROUND_DAY[$value['day']] ?? '';
            } else {
                $value['round'] = self::LANG_ROUND[$value['round']] ?? '';
                $value['turn'] = self::LANG_TURN[$value['stage']] ?? '';
            }
            $listArr[] = $value;
        }
        return ['status' => 1, 'info' => $info, 'list' => $listArr];
    }

    /**
     * 奖励列表: c=war&m=get_reward_list&type=
     *
     * 武斗会没有物品奖励（积分在比赛中直接记入），活动结束后服务器端一律回取不到（-3）；奖励的名称、已领取修复与
     * 装备 / 坐骑信息补全随之不执行。
     */
    public static function get_reward_list(Context $ctx): array
    {
        /**
         * -3 获取奖励信息失败
         * -2 活动未结束
         * -1 获取信息失败
         */
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return ['status' => 0];
        }
        $type = (int)$ctx->input('type', 0);
        $type = $type > 0 ? $type : 1;
        $uc = WarServer::index($member['uid'], Server::id());
        if ($uc['status'] !== 1) {
            return ['status' => -1];
        }
        if ($uc['stage'] !== 4) {
            return ['status' => -2];
        }
        $reward = WarServer::rewardList(Server::id(), $member['uid'], $type);
        if ($reward['status'] !== 1) {
            return ['status' => -3];
        }
        return ['status' => -3];
    }

    /**
     * 领取奖励: c=war&m=get_reward&type=&id=（服务器端没有物品奖励，回 -2）
     */
    public static function get_reward(Context $ctx): array
    {
        /**
         * -4 领取装备失败
         * -3 添加领取记录失败
         * -2 获取奖励信息失败
         * -1 已领取
         *  0 失败
         *  1 成功
         */
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return ['status' => 0];
        }
        $type = (int)$ctx->input('type', 0);
        $id = (int)$ctx->input('id', 0);
        if (War::rewardLog($member['uid'], $id, $type, BossClock::now()) !== []) {
            return ['status' => -1];
        }
        $reward = WarServer::reward(Server::id(), $member['uid'], $type, $id);
        if ($reward['status'] !== 1) {
            return ['status' => -2];
        }
        return ['status' => -2];
    }

    /**
     * 战报信息: c=war&m=get_report_info&id=&stage=
     */
    public static function get_report_info(Context $ctx): array
    {
        /**
         * -1 获取战报失败
         *  1 成功
         */
        $id = (int)$ctx->input('id', 0);
        $stage = (int)$ctx->input('stage', 0);
        $return = self::reportInfo($id, $stage);
        $return['share'] = ['id' => $id . '_' . $stage, 'type' => 'war'];
        return $return;
    }

    /**
     * 一局的战报回放（_get_report_info）：Redis 缓存 15 天（旧版别的请求正在取时最多等 5 秒；进程内直接取，不等）
     *
     * @return array<string, mixed>
     */
    public static function reportInfo(int $id, int $stage): array
    {
        $info = War::reportCache($id, $stage);
        if ($info === []) {
            War::addReportStatus($id, $stage);
            $result = WarServer::reportInfo($id, $stage);
            if ($result['status'] !== 1) {
                return ['status' => -1];
            }
            $info = $result['report'];
            War::cacheReport($id, $stage, $info);
        }
        $return = ['status' => 1];
        $return['info']['win'] = $info['report']['assess'] ?? null;
        $return['info']['integral'] = $info['integral'] ?? 0;
        $return['battle'] = $info['report'];
        $return['battle']['left_role'] = ['name' => $info['nickname'], 'level' => $info['userlevel']];
        $return['battle']['right_role'] = ['name' => $info['name'], 'level' => $info['level']];
        return $return;
    }

    /**
     * 分享战报到聊天室: c=war&m=share_report&id=&stage=
     *
     * 照旧版：先扣 2 元宝再判断——5 分钟内同一局分享过的不再广播（照样扣元宝、回 1）、战报取不到回 -2（同样已扣元宝）；
     * 决出胜负的局（up 1 晋级、up 2 决赛）才有聊天内容，其余回 2（已扣元宝）。旧版 chat_model 不认 war_round 参数，
     * 聊天里不显示轮次，照搬。
     */
    public static function share_report(Context $ctx): array
    {
        /**
         * -2 元宝不足 / 获取比赛信息失败
         *  1 成功
         *  2 生成内容失败
         */
        $uid = MemberHandler::resolveUid($ctx);
        if (self::member($uid) === null) {
            return ['status' => 0];
        }
        $id = (int)$ctx->input('id', 0);
        $stage = (int)$ctx->input('stage', 0);
        $allGold = Chat::chargeShare($uid, self::SHARE_PRICE);
        if ($allGold === null) {
            return ['status' => -2];
        }
        if (War::shareCache($id, $stage)) {
            return ['status' => 1, 'all_gold' => $allGold];
        }
        $info = self::reportInfo($id, $stage);
        if ($info['status'] !== 1) {
            return ['status' => -2];
        }
        if ($info['info']['win'] > 0) {
            $warLeft = $info['battle']['left_role']['name'];
            $warRight = $info['battle']['right_role']['name'];
        } else {
            $warLeft = $info['battle']['right_role']['name'];
            $warRight = $info['battle']['left_role']['name'];
        }
        $day = (int)($info['battle']['day'] ?? 0);
        if ($day <= 3) {
            $warRound = self::LANG_ROUND_DAY[$day] ?? '';
        } else {
            $toround = intdiv((int)($info['battle']['round'] ?? 0), 2);
            $warRound = $toround ? (self::LANG_ROUND[$toround] ?? '') : '';
        }
        $messageInfo = [
            'id' => $id . '|' . $stage,
            'from' => ['nickname' => $warLeft, 'uid' => 0],
            'to' => ['nickname' => $warRight, 'uid' => 0],
            'war_round' => $warRound,
        ];
        $sender = Chat::sender($uid);
        $message = match ((int)($info['battle']['up'] ?? 0)) {
            // 晋级赛
            1 => Chat::getMessageStr(36, $messageInfo, $sender),
            // 决赛
            2 => Chat::getMessageStr(37, $messageInfo, $sender),
            default => '',
        };
        if ($message !== '') {
            $status = 1;
            Chat::sendToAll($message, 12, 0, 0, $sender);
        } else {
            $status = 2;
        }
        War::setShareCache($id, $stage);
        return ['status' => $status, 'all_gold' => $allGold];
    }

    /**
     * 积分商城: c=war&m=point_shop
     */
    public static function point_shop(Context $ctx): array
    {
        /**
         * -2 坐骑 / 精华信息缺失
         * -1 积分商城数据表没有信息
         *  1 成功
         */
        $return = ['status' => 0, 'list' => [], 'point' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        $list = War::pointList();
        if ($list === []) {
            $return['status'] = -1;
            return $return;
        }
        // 获取当前玩家积分
        $point = WarServer::getPoint($uid, Server::id());
        if ($point > 0) {
            $return['point'] = $point;
        }
        // 对宝箱奖励信息处理(装备/坐骑/精华)
        foreach ($list as &$val) {
            for ($i = 1; $i <= 5; $i++) {
                switch ($val['gettype' . $i]) {
                    case 7: // 装备
                        $val['info']['equip' . $i] = Equip::ROWS[$val['getvalue' . $i]] ?? [];
                        break;
                    case 10: // 坐骑
                        $mount = MountTemp::ROWS[$val['getvalue' . $i]] ?? null;
                        if ($mount === null) {
                            $return['status'] = -2;
                            return $return;
                        }
                        $val['info']['mount' . $i] = $mount;
                        break;
                    case 15: // 精华
                        $essence = EquipEssence::ROWS[$val['getvalue' . $i]] ?? null;
                        if ($essence === null) {
                            $return['status'] = -2;
                            return $return;
                        }
                        $val['info']['essence' . $i] = $essence;
                        $val['info']['essence' . $i]['num'] = $val['getvalue' . $i . '2'] ?? null;
                        $val['getvalue' . $i] = $val['getvalue' . $i . '2'] ?? null;
                        break;
                }
            }
        }
        unset($val);
        $return['list'] = $list;
        $return['status'] = 1;
        return $return;
    }

    /**
     * 积分购买宝箱: c=war&m=point_buy&id=
     *
     * 照旧版：购买锁被占时回 3 并顺手解锁；扣积分后发放宝箱内容，坐骑 / 精华发放失败时直接返回（积分已扣、前面的物品已发、
     * 不解锁，30 秒后锁过期）。
     */
    public static function point_buy(Context $ctx): array
    {
        /**
         * -6 / -5 / -4 添加坐骑失败
         * -2 精华信息缺失
         * -1 积分商城数据表没有信息
         *  0 失败
         *  1 成功
         *  2 积分不足
         *  3 购买操作不要太频繁
         */
        $return = ['status' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        if (self::member($uid) === null) {
            return $return;
        }
        $id = (int)$ctx->input('id', 0);
        $lock = War::POINT_BUY_LOCK;
        // 防刷接口（加锁）
        if (!War::addLock($lock, $uid)) {
            $return['status'] = 3;
            War::unlock($lock, $uid);
            return $return;
        }
        // 旧版 id 为 0 时取到整张表、按 integral 为 0 继续发放空宝箱；这里按没有信息回 -1
        $pointInfo = War::pointList($id);
        if ($pointInfo === [] || $id <= 0) {
            $return['status'] = -1;
            War::unlock($lock, $uid);
            return $return;
        }
        $serverid = Server::id();
        // 宝箱积分
        $chestPoint = (int)$pointInfo['integral'];
        $point = max(0, WarServer::getPoint($uid, $serverid));
        if ($chestPoint > $point) {
            $return['status'] = 2;
            War::unlock($lock, $uid);
            return $return;
        }
        // 更新玩家积分（积分不足时不扣）
        if (WarServer::updatePoint($uid, $chestPoint, $serverid)['status'] !== 1) {
            $return['status'] = 2;
            War::unlock($lock, $uid);
            return $return;
        }
        // 玩家最终积分 = 玩家当前积分 - 宝箱积分
        $return['point'] = $point - $chestPoint;
        $now = BossClock::now();
        for ($i = 1; $i <= 5; $i++) {
            $value = (int)$pointInfo['getvalue' . $i];
            switch ($pointInfo['gettype' . $i]) {
                case 1: // 银币
                    Member::addCurrency($uid, silver: $value);
                    $return['silver'] = $value;
                    $return['allsilver'] = (int)DB::table('member')->where('uid', $uid)->value('silver');
                    break;
                case 2: // 黄宝石
                    TavernHandler::addJade($uid, 2, $value, 'war', 'point_buy');
                    break;
                case 3: // 紫宝石
                    TavernHandler::addJade($uid, 3, $value, 'war', 'point_buy');
                    break;
                case 4: // 原魂
                    Member::addCurrency($uid, soul: $value);
                    break;
                case 5: // 水晶
                    MemberJade::add($uid, $value, 'war', 'point_buy', $now);
                    break;
                case 6: // 声望
                    Member::addReputation($uid, $value);
                    break;
                case 7: // 装备
                    $item = Equip::ROWS[$value] ?? null;
                    if ($item !== null) {
                        Equipment::addFromTemplate($uid, $value);
                    }
                    $pointInfo['info']['equip' . $i] = $item ?? [];
                    break;
                case 8: // 魔石碎片
                    OwnGem::addChip($uid, $value, 'war', 'point_buy', $now);
                    break;
                case 9: // 突飞卡
                    FreeLeap::add($uid, $value, $now);
                    break;
                case 10: // 坐骑
                    $result = OwnMount::addMount($uid, $value, 0, 1, 'war', 'point_buy', $now);
                    if ($result['status'] === -1) {
                        // 获取坐骑模版信息失败
                        $return['status'] = -4;
                        return $return;
                    }
                    if ($result['status'] === -2) {
                        // 获取坐骑升级信息失败
                        $return['status'] = -5;
                        return $return;
                    }
                    if ($result['status'] !== 1) {
                        $return['status'] = -6;
                        return $return;
                    }
                    $pointInfo['info']['mount' . $i] = $result['info'];
                    break;
                case 11: // 精炼石
                    MemberStone::add($uid, $value, MemberStone::REFINE, 'war', 'point_buy', $now);
                    break;
                case 12: // 五彩石
                    MemberStone::add($uid, $value, MemberStone::COLOR, 'war', 'point_buy', $now);
                    break;
                case 13: // 上古血玉
                    MemberStone::add($uid, $value, MemberStone::BLOOD, 'war', 'point_buy', $now);
                    break;
                case 14: // 军令
                    Member::addCurrency($uid, act: $value);
                    break;
                case 15: // 精华
                    $essence = EquipEssence::ROWS[$value] ?? null;
                    if ($essence === null) {
                        $return['status'] = -2;
                        return $return;
                    }
                    $num = (int)($pointInfo['getvalue' . $i . '2'] ?? 0);
                    $pointInfo['info']['essence' . $i] = $essence;
                    $pointInfo['info']['essence' . $i]['num'] = $num;
                    Equipment::addTowerItem($uid, $value, $num);
                    break;
            }
        }
        // 记录购买日志
        War::addShopLog($uid, $id, $now);
        War::unlock($lock, $uid);
        $return['status'] = 1;
        $return['list'] = $pointInfo;
        return $return;
    }

    /**
     * 主城的武斗会报名状态（member 控制器 _war_entry_status）：0 不可报名，1 可报名，2 开战
     *
     * 19 日 0 点到 26 日 18:00 未报名为 1；26 日 19:00 到 28 日 0 点比赛没结束为 2（旧版按 date('jHi') 的数值比较，照搬）。
     */
    public static function entryStatus(int $uid, int $level, int $now): int
    {
        $dayHour = (int)date('jHi', $now);
        if ($dayHour < 190000 || $dayHour >= 280000) {
            return 0;
        }
        if ($level < War::entryLevel()) {
            return 0;
        }
        if ($dayHour < 261800) {
            return War::isEntry($uid) ? 0 : 1;
        }
        if ($dayHour >= 261900 && $dayHour <= 280000 && !War::endStage()) {
            return 2;
        }
        return 0;
    }

    /**
     * 等级限制（构造函数 _level_limit）：80 级以下为 1（旧版的「60 级以下为 2」写在后面，永远走不到）
     */
    private static function levelLimit(int $level): int
    {
        return $level < 80 ? 1 : 0;
    }

    /**
     * @return array{uid: int, nickname: string, level: int, gender: int}|null
     */
    private static function member(int $uid): ?array
    {
        if ($uid <= 0) {
            return null;
        }
        $row = DB::table('member')->select('uid', 'nickname', 'level', 'gender')->where('uid', $uid)->whereNull('deleted_at')->first();
        return $row === null ? null : ['uid' => (int)$row['uid'], 'nickname' => (string)$row['nickname'], 'level' => (int)$row['level'], 'gender' => (int)$row['gender']];
    }
}
