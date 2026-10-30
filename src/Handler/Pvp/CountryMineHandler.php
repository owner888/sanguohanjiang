<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Pvp;

use Hanjiang\Handler\City\MemberHandler;

use Hanjiang\Battle\BattleEngine;
use Hanjiang\Battle\Position;
use Hanjiang\Battle\TeamBuilder;
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

        // 1. 我方队伍（旧版 countrymine 控制器抢矿走 battle_model->battle_pk：init_member_team_info，非 PVE）
        $left = TeamBuilder::forMember($uid);
        if ($left['status'] === -1 || $left['status'] === -3) {
            // 旧版 battle_model 返回 -1（没有阵法）/ -3（武将信息错误）；本玩法尚未按旧版控制器移植，直接回传该状态
            return ['status' => $left['status'], 'message' => '出战阵容信息错误'];
        }
        // -2（阵法里没有武将）旧版按空队伍继续战斗，空队伍直接判负

        // 2. 敌国矿山护卫
        // 依赖未移植：旧版抢矿对手是占矿玩家（battle_pk(mine_log.uid)），本玩法的护卫与数值是新版自定的
        // 依赖未移植：守军数值仍为新版自定
        $rightTeam = [Position::FrontMiddle->value => TeamBuilder::unportedUnit(7700 + $mineId, '敌国护矿校尉', 2500, 340, 130)];

        // 3. 推演
        $engine = new BattleEngine($left['matrix_type'], $left['matrix_coe']);
        $battle = $engine->battle($left['team'], $rightTeam);
        $isWin = $battle['assess'] >= 1;

        if (!$isWin) {
            return [
                'status' => 1,
                'win' => -1,
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
