<?php

declare(strict_types=1);

namespace Hanjiang\Handler;

use Hanjiang\Battle\BattleEngine;
use Hanjiang\Battle\Hero;
use Hanjiang\Battle\Position;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use OmniPHP\WebSocket\Broadcaster;

final class CountryMineHandler
{
    public const array MINES = [
        1 => ['id' => 1, 'name' => '魏国·官渡铜矿', 'country' => 1, 'rate' => 30],
        2 => ['id' => 2, 'name' => '蜀国·剑阁银脉', 'country' => 2, 'rate' => 30],
        3 => ['id' => 3, 'name' => '吴国·赤壁赤金矿', 'country' => 3, 'rate' => 30],
        4 => ['id' => 4, 'name' => '中立·华夏龙脉总督矿', 'country' => 0, 'rate' => 80],
    ];

    /**
     * 国家矿山主界面: c=countrymine&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $mineState = Redis::getJson('user_country_mine:' . $uid) ?: [
            'mine_id' => 0,
            'start_time' => 0,
        ];

        $mineId = (int)$mineState['mine_id'];
        $startTime = (int)$mineState['start_time'];
        $elapsed = ($mineId > 0 && $startTime > 0) ? max(0, time() - $startTime) : 0;
        $rate = self::MINES[$mineId]['rate'] ?? 0;
        $accumulatedSilver = $elapsed * $rate;

        return [
            'status' => 1,
            'my_mine' => $mineId,
            'mine_name' => self::MINES[$mineId]['name'] ?? '未在国家矿山驻军',
            'elapsed_seconds' => $elapsed,
            'accumulated_silver' => $accumulatedSilver,
            'mines' => array_values(self::MINES),
            'message' => 'ok',
        ];
    }

    /**
     * 占领国家矿山采矿: c=countrymine&m=caikuang
     */
    public static function caikuang(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $mineId = (int)($ctx->input('id', $ctx->input('mine_id', 1)));

        if (!isset(self::MINES[$mineId])) {
            return ['status' => 0, 'message' => '国家矿脉不存在'];
        }

        $cfg = self::MINES[$mineId];

        Redis::setJson('user_country_mine:' . $uid, [
            'mine_id' => $mineId,
            'start_time' => time(),
        ], 86400 * 7);

        return [
            'status' => 1,
            'mine_id' => $mineId,
            'mine_name' => $cfg['name'],
            'message' => "成功进驻【{$cfg['name']}】，开始为本国勘探开采国库银饷！",
        ];
    }

    /**
     * 掠夺敌国矿山车队: c=countrymine&m=rob
     */
    public static function rob(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $mineId = (int)($ctx->input('id', $ctx->input('mine_id', 1)));
        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();

        // 1. 我方队伍
        $generals = DB::table('general')->where('uid', $uid)->where('pos', '>', 0)->whereNull('deleted_at')->get();
        if (empty($generals)) {
            $generals = DB::table('general')->where('uid', $uid)->whereNull('deleted_at')->get();
        }

        $matrix = DB::table('matrix')->where('uid', $uid)->whereNull('deleted_at')->first();
        $matrixMid = (int)($matrix['mid'] ?? 1);
        $matrixLevel = max(1, (int)($matrix['level'] ?? 1));
        $matrixAddition = 1.0 + 0.05 + ($matrixLevel - 1) * 0.01;

        $leftHeroes = [];
        foreach ($generals as $g) {
            $gid = (int)$g['id'];
            $posInt = (int)$g['pos'] > 0 ? (int)$g['pos'] : 2;
            $pos = Position::tryFrom($posInt) ?? Position::FrontMiddle;
            $equipped = DB::table('equipment')->where('uid', $uid)->where('generalid', $gid)->whereNull('deleted_at')->get();
            $stats = GeneralHandler::calculateGeneralStats($g, $equipped);
            $finalAtk = ($matrixMid === 1) ? (int)round($stats['attack'] * $matrixAddition) : $stats['attack'];
            $finalDef = ($matrixMid === 3) ? (int)round($stats['defense'] * $matrixAddition) : $stats['defense'];
            $leftHeroes[] = new Hero($gid, $g['name'], $pos, $stats['hp'], $stats['hp'], $finalAtk, $finalDef, $stats['speed']);
        }
        if (empty($leftHeroes)) {
            $leftHeroes[] = new Hero(1, '天命主公', Position::FrontMiddle, 2000, 2000, 380, 150, 150);
        }

        // 2. 敌国矿山护卫
        $rightHeroes = [
            new Hero(7700 + $mineId, '敌国护矿校尉', Position::FrontMiddle, 2500, 2500, 340, 130, 120),
        ];

        // 3. 推演
        $engine = new BattleEngine($leftHeroes, $rightHeroes);
        $simResult = $engine->simulate();
        $isWin = $simResult['win'];

        if (!$isWin) {
            return [
                'status' => 1,
                'win' => -1,
                'rounds' => $simResult['rounds'],
                'message' => '掠夺战役惜败，敌国防备森严，撤军休整！',
            ];
        }

        $robSilver = 20000;
        MemberHandler::addCurrency($uid, silver: $robSilver, reputation: 50);

        $pName = $member['nickname'] ?? ('主公' . $uid);
        Broadcaster::broadcast('marquee', [
            'content' => "边境捷报！主公【{$pName}】奇袭敌国边境矿山，大破护矿校尉，豪夺 20000 巨额军饷！",
            'type' => 'system_broadcast',
        ]);

        return [
            'status' => 1,
            'win' => 1,
            'rob_silver' => $robSilver,
            'rounds' => $simResult['rounds'],
            'message' => "突袭大捷！成功抢夺敌国矿饷 {$robSilver} 银两、功勋声望 +50！",
        ];
    }

    /**
     * 提取开采累积银饷: c=countrymine&m=get_reward
     */
    public static function get_reward(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $mineState = Redis::getJson('user_country_mine:' . $uid);

        if (!is_array($mineState) || empty($mineState['mine_id'])) {
            return ['status' => 0, 'message' => '当前未在任何国家矿山采矿'];
        }

        $mineId = (int)$mineState['mine_id'];
        $startTime = (int)$mineState['start_time'];
        $elapsed = max(1, time() - $startTime);
        $rate = self::MINES[$mineId]['rate'] ?? 30;
        $earnedSilver = $elapsed * $rate;

        MemberHandler::addCurrency($uid, silver: $earnedSilver);

        $mineState['start_time'] = time();
        Redis::setJson('user_country_mine:' . $uid, $mineState, 86400 * 7);

        return [
            'status' => 1,
            'silver' => $earnedSilver,
            'message' => "成功收缴国家矿山产出银饷 {$earnedSilver}！",
        ];
    }

    /**
     * 放弃/撤军: c=countrymine&m=give_up
     */
    public static function give_up(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        Redis::del('user_country_mine:' . $uid);

        return [
            'status' => 1,
            'message' => '部队已安全撤出国家矿区',
        ];
    }
}
