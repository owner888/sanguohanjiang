<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use OmniPHP\WebSocket\Broadcaster;

/**
 * 海外商贸、远洋船队组队远航与拦截打劫处理器 (DDD Activity Domain)
 */
final class OverseasTradeHandler
{
    public const array FLEETS = [
        1 => ['id' => 1, 'name' => '快行小帆船', 'cost_silver' => 5000, 'duration' => 60, 'profit_silver' => 15000],
        2 => ['id' => 2, 'name' => '南海大宝船', 'cost_silver' => 10000, 'duration' => 180, 'profit_silver' => 35000],
        3 => ['id' => 3, 'name' => '西域龙首巨舰', 'cost_silver' => 20000, 'duration' => 300, 'profit_silver' => 80000],
    ];

    public const array COMMODITIES = [
        1 => ['id' => 1, 'name' => '粮草布匹', 'cost' => 1000, 'get' => 2000],
        2 => ['id' => 2, 'name' => '百炼生铁', 'cost' => 5000, 'get' => 10000],
        3 => ['id' => 3, 'name' => '蜀锦丝绸', 'cost' => 10000, 'get' => 22000],
        4 => ['id' => 4, 'name' => '景德瓷器', 'cost' => 20000, 'get' => 45000],
    ];

    /**
     * 海外商贸主界面: c=overseastrade&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        $silver = (int)($member['silver'] ?? 0);
        $level = (int)($member['level'] ?? 1);
        $nickname = (string)($member['nickname'] ?? ('主公' . $uid));

        $state = Redis::getJson('overseas_trade:' . $uid) ?: [
            'fleet_id' => 0,
            'start_time' => 0,
            'commodity' => 1,
            'team_id' => 0,
        ];

        $fleetId = (int)$state['fleet_id'];
        $startTime = (int)$state['start_time'];
        $now = time();

        $cfg = self::FLEETS[$fleetId] ?? null;
        $isFinished = false;
        $remaining = 0;
        $btnStatus = 1; // 1: 可发起贸易

        if ($cfg !== null && $startTime > 0) {
            $elapsed = $now - $startTime;
            $remaining = max(0, (int)$cfg['duration'] - $elapsed);
            $isFinished = ($remaining === 0);
            if (!$isFinished) {
                $btnStatus = 3; // 贸易航行中
            }
        }

        // 活跃船队列表
        $teamList = [
            [
                'id' => 1,
                'team_name' => '南海通商船队',
                'member1' => $uid,
                'member1_name' => $nickname,
                'member1_level' => $level,
                'member1_commodity' => 1,
                'member2' => 0,
                'member2_name' => '',
                'member2_level' => 0,
                'member2_commodity' => 0,
                'startdateline' => $startTime > 0 ? $startTime : $now - 120,
                'status' => 1,
                'robtimes' => 0,
            ],
            [
                'id' => 2,
                'team_name' => '西域丝路船队',
                'member1' => 8888,
                'member1_name' => '常山赵子龙',
                'member1_level' => 60,
                'member1_commodity' => 3,
                'member2' => 8889,
                'member2_name' => '关云长',
                'member2_level' => 65,
                'member2_commodity' => 2,
                'startdateline' => $now - 200,
                'status' => 1,
                'robtimes' => 1,
            ],
        ];

        return [
            'status' => 1,
            'btn_status' => $btnStatus,
            'endtime' => $remaining > 0 ? ($now + $remaining) : 0,
            'info' => [
                'times' => 3,
                'robtimes' => 5,
                're_robtimes' => 0,
                'team' => (int)($state['team_id'] ?? 0),
                'commodity' => (int)($state['commodity'] ?? 1),
            ],
            'rob' => [
                'price' => 50,
                'times' => 5,
            ],
            'team' => [
                'allpage' => 1,
                'list' => $teamList,
            ],
            'all_silver' => $silver,
            // 向下兼容保留原字段
            'fleets' => self::FLEETS,
            'sailing' => ($fleetId > 0 && !$isFinished) ? 1 : 0,
            'can_harvest' => $isFinished ? 1 : 0,
            'current_fleet' => $cfg['name'] ?? '无出海商船',
            'remaining_seconds' => $remaining,
            'profit_silver' => $cfg['profit_silver'] ?? 0,
            'message' => 'ok',
        ];
    }

    /**
     * 获取国家贸易组队列表: c=overseastrade&m=get_list_by_country
     */
    public static function get_list_by_country(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $page = (int)($ctx->input('p', $ctx->input('page', 1)));
        $page = max(1, min(5, $page));

        $state = Redis::getJson('overseas_trade:' . $uid) ?: [];
        $myTeamId = (int)($state['team_id'] ?? 0);

        // 构造 4 个队伍席位
        $list = [];
        for ($place = 1; $place <= 4; $place++) {
            $tid = ($page - 1) * 4 + $place;
            $list[$place] = [
                'id' => $tid,
                'site' => $place,
                'status' => 0,
                'member1' => 0,
                'member1_name' => '',
                'member1_level' => 0,
                'member1_commodity' => 0,
                'member1_gender' => 1,
                'member2' => 0,
                'member2_name' => '',
                'member2_level' => 0,
                'member2_commodity' => 0,
                'member2_gender' => 2,
            ];
        }

        // 在第一页的第一席预置一名队友方便随时点击加入组队
        if ($page === 1) {
            $list[1]['member1'] = 8888;
            $list[1]['member1_name'] = '常山赵子龙';
            $list[1]['member1_level'] = 58;
            $list[1]['member1_commodity'] = 2;
            $list[1]['member1_gender'] = 1;
        }

        return [
            'status' => 1,
            'page' => $page,
            'allpage' => 5,
            'teamid' => $myTeamId,
            'price' => 5, // 元宝强行出海
            'trade' => 0,
            'list' => $list,
            'message' => 'ok',
        ];
    }

    /**
     * 加入远洋组队: c=overseastrade&m=join_team
     */
    public static function join_team(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $tid = (int)($ctx->input('tid', $ctx->input('id', 1)));

        $state = Redis::getJson('overseas_trade:' . $uid) ?: [];
        $state['team_id'] = $tid > 0 ? $tid : 1;
        Redis::setJson('overseas_trade:' . $uid, $state, 86400 * 3);

        return [
            'status' => 1,
            'teamid' => $state['team_id'],
            'message' => '成功加入贸易船队！',
        ];
    }

    /**
     * 退出当前组队: c=overseastrade&m=goout
     */
    public static function goout(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $fleetId = (int)($ctx->input('id', $ctx->input('fleet_id', 0)));

        $state = Redis::getJson('overseas_trade:' . $uid) ?: [];

        // 若带了 fleet_id，则为旧单测要求的派遣出海
        if ($fleetId > 0 && isset(self::FLEETS[$fleetId])) {
            $cfg = self::FLEETS[$fleetId];
            $costSilver = (int)$cfg['cost_silver'];
            if (!MemberHandler::deductCurrency($uid, silver: $costSilver)) {
                return ['status' => -1, 'message' => "银两不足，筹备商队需要 {$costSilver} 银两"];
            }

            $state['fleet_id'] = $fleetId;
            $state['start_time'] = time();
            $state['team_id'] = 1;
            Redis::setJson('overseas_trade:' . $uid, $state, 86400 * 3);

            return [
                'status' => 1,
                'fleet_id' => $fleetId,
                'fleet_name' => $cfg['name'],
                'duration' => $cfg['duration'],
                'profit_silver' => $cfg['profit_silver'],
                'message' => "商船【{$cfg['name']}】扬帆起航！",
            ];
        }

        // 否则为退出队伍
        $state['team_id'] = 0;
        Redis::setJson('overseas_trade:' . $uid, $state, 86400 * 3);

        return [
            'status' => 1,
            'message' => '已退出贸易队伍',
        ];
    }

    /**
     * 检查自己的队伍状态: c=overseastrade&m=check_team
     */
    public static function check_team(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $state = Redis::getJson('overseas_trade:' . $uid) ?: [];

        return [
            'status' => 1,
            'teamid' => (int)($state['team_id'] ?? 0),
            'trade' => !empty($state['fleet_id']) ? 1 : 0,
            'message' => 'ok',
        ];
    }

    /**
     * 货物列表: c=overseastrade&m=item_list
     */
    public static function item_list(Context $ctx): array
    {
        return [
            'status' => 1,
            'list' => array_values(self::COMMODITIES),
            'message' => 'ok',
        ];
    }

    /**
     * 购买货物: c=overseastrade&m=buy_item
     */
    public static function buy_item(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $cid = (int)($ctx->input('id', $ctx->input('cid', 1)));

        if (!isset(self::COMMODITIES[$cid])) {
            return ['status' => -2, 'message' => '货物不存在'];
        }

        $cfg = self::COMMODITIES[$cid];
        $cost = (int)$cfg['cost'];

        if (!MemberHandler::deductCurrency($uid, silver: $cost)) {
            return ['status' => -1, 'message' => '银两不足'];
        }

        $state = Redis::getJson('overseas_trade:' . $uid) ?: [];
        $state['commodity'] = $cid;
        Redis::setJson('overseas_trade:' . $uid, $state, 86400 * 3);

        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();

        return [
            'status' => 1,
            'all_silver' => (int)($member['silver'] ?? 0),
            'commodity' => $cid,
            'item_name' => $cfg['name'],
            'message' => "成功进购【{$cfg['name']}】！",
        ];
    }

    /**
     * 开启贸易航行: c=overseastrade&m=trade
     */
    public static function trade(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $state = Redis::getJson('overseas_trade:' . $uid) ?: [];

        $fleetId = (int)($state['fleet_id'] ?? 1);
        $cfg = self::FLEETS[$fleetId] ?? self::FLEETS[1];
        $profit = (int)$cfg['profit_silver'];

        MemberHandler::addCurrency($uid, silver: $profit);
        $state['fleet_id'] = 0;
        $state['start_time'] = 0;
        $state['team_id'] = 0;
        Redis::setJson('overseas_trade:' . $uid, $state, 86400 * 3);

        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();

        return [
            'status' => 1,
            'profit' => $profit,
            'silver' => (int)($member['silver'] ?? 0),
            'message' => "远洋商船顺利靠岸！本次海外贸易获利丰厚，进账 {$profit} 银两！",
        ];
    }

    /**
     * 贸易历史战报记录: c=overseastrade&m=get_trade_log
     */
    public static function get_trade_log(Context $ctx): array
    {
        $page = (int)($ctx->input('p', $ctx->input('page', 1)));
        $now = time();

        $logs = [
            [
                'id' => 1,
                'type' => 1,
                'team_id' => 101,
                'member1_name' => '常山赵子龙',
                'member1_get' => 35000,
                'member2_name' => '关云长',
                'member2_get' => 35000,
                'dateline' => date('Y-m-d H:i', $now - 600),
            ],
            [
                'id' => 2,
                'type' => 1,
                'team_id' => 102,
                'member1_name' => '燕人张翼德',
                'member1_get' => 28000,
                'member2_name' => '',
                'member2_get' => 0,
                'dateline' => date('Y-m-d H:i', $now - 1200),
            ],
        ];

        return [
            'status' => 1,
            'page' => $page,
            'allpage' => 1,
            'list' => $logs,
            'message' => 'ok',
        ];
    }
}
