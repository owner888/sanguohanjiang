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

        // 模拟远洋活跃船队列表
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
                'team' => $fleetId > 0 ? 1 : 0,
                'commodity' => 1,
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
     * 派遣商船启航出海: c=overseastrade&m=goout
     */
    public static function goout(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $fleetId = (int)($ctx->input('id', $ctx->input('fleet_id', 1)));

        if (!isset(self::FLEETS[$fleetId])) {
            return ['status' => 0, 'message' => '商船编号不存在'];
        }

        $cfg = self::FLEETS[$fleetId];
        $costSilver = (int)$cfg['cost_silver'];

        $state = Redis::getJson('overseas_trade:' . $uid);
        if (is_array($state) && !empty($state['fleet_id'])) {
            $elapsed = time() - (int)$state['start_time'];
            $curCfg = self::FLEETS[$state['fleet_id']] ?? null;
            if ($curCfg && $elapsed < $curCfg['duration']) {
                return ['status' => 0, 'message' => '已有商船正在远洋贸易中，请等待返航'];
            }
        }

        if (!MemberHandler::deductCurrency($uid, silver: $costSilver)) {
            return ['status' => -1, 'message' => "银两不足，筹备商队需要 {$costSilver} 银两"];
        }

        Redis::setJson('overseas_trade:' . $uid, [
            'fleet_id' => $fleetId,
            'start_time' => time(),
            'commodity' => 1,
        ], 86400 * 3);

        return [
            'status' => 1,
            'fleet_id' => $fleetId,
            'fleet_name' => $cfg['name'],
            'duration' => $cfg['duration'],
            'profit_silver' => $cfg['profit_silver'],
            'message' => "商船【{$cfg['name']}】扬帆起航！预计 {$cfg['duration']} 秒后满载珍宝归来！",
        ];
    }

    /**
     * 商船靠岸结算利润: c=overseastrade&m=trade
     */
    public static function trade(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $state = Redis::getJson('overseas_trade:' . $uid);

        if (!is_array($state) || empty($state['fleet_id'])) {
            return ['status' => 0, 'message' => '当前没有商船航行'];
        }

        $fleetId = (int)$state['fleet_id'];
        $cfg = self::FLEETS[$fleetId] ?? null;
        if ($cfg === null) {
            Redis::del('overseas_trade:' . $uid);
            return ['status' => 0, 'message' => '商船数据异常'];
        }

        $profit = (int)$cfg['profit_silver'];
        MemberHandler::addCurrency($uid, silver: $profit);
        Redis::del('overseas_trade:' . $uid);

        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();

        return [
            'status' => 1,
            'profit' => $profit,
            'silver' => (int)($member['silver'] ?? 0),
            'message' => "远洋商船顺利靠岸！本次海外贸易获利丰厚，进账 {$profit} 银两！",
        ];
    }
}
