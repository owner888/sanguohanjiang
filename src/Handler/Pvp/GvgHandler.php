<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Pvp;

use Hanjiang\Handler\City\MemberHandler;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

final class GvgHandler
{
    /**
     * 跨服军团八强争霸对阵表
     */
    public const array MATCH_TEAMS = [
        1 => ['id' => 1, 'name' => '【天命无双】军团', 'server' => '1区-桃园结义', 'leader' => '常山赵子龙', 'odds' => 1.8],
        2 => ['id' => 2, 'name' => '【魏武挥鞭】军团', 'server' => '2区-赤壁之战', 'leader' => '曹孟德', 'odds' => 2.1],
        3 => ['id' => 3, 'name' => '【锦帆义从】军团', 'server' => '3区-官渡之战', 'leader' => '甘兴霸', 'odds' => 2.5],
        4 => ['id' => 4, 'name' => '【西凉铁骑】军团', 'server' => '4区-中原逐鹿', 'leader' => '马孟起', 'odds' => 3.0],
    ];

    /**
     * GVG 争霸赛主界面: c=gvg&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $myBet = Redis::getJson('gvg_bet:' . $uid);

        return [
            'status' => 1,
            'tournament_name' => '第一届【天下第一军团】GVG 巅峰对抗赛',
            'current_round' => '总决赛',
            'teams' => self::MATCH_TEAMS,
            'my_bet' => $myBet,
            'message' => 'ok',
        ];
    }

    /**
     * 押注竞猜军团冠军: c=gvg&m=betting
     */
    public static function betting(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $teamId = (int)($ctx->input('team_id', $ctx->input('id', 1)));
        $amount = max(1000, (int)($ctx->input('amount', 5000)));

        if (!isset(self::MATCH_TEAMS[$teamId])) {
            return ['status' => 0, 'message' => '参赛军团不存在'];
        }

        $myBet = Redis::getJson('gvg_bet:' . $uid);
        if (is_array($myBet)) {
            return ['status' => 0, 'message' => '您本届比赛已经完成押注，不可重复下注'];
        }

        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        if ((int)($member['silver'] ?? 0) < $amount) {
            return ['status' => -1, 'message' => "银两不足，押注需要 {$amount} 银两"];
        }

        // 扣除押注银两
        $newSilver = (int)$member['silver'] - $amount;
        DB::table('member')->where('uid', $uid)->update(['silver' => $newSilver]);
        Redis::del('player:' . $uid);

        $team = self::MATCH_TEAMS[$teamId];
        $betData = [
            'team_id' => $teamId,
            'team_name' => $team['name'],
            'amount' => $amount,
            'odds' => $team['odds'],
            'estimated_reward' => (int)round($amount * $team['odds']),
            'time' => time(),
        ];
        Redis::setJson('gvg_bet:' . $uid, $betData, 86400 * 7);

        return [
            'status' => 1,
            'team_id' => $teamId,
            'team_name' => $team['name'],
            'bet_amount' => $amount,
            'silver' => $newSilver,
            'message' => "成功支持押注【{$team['name']}】{$amount} 银两，若其夺冠可获得双倍大分红！",
        ];
    }

    /**
     * 战况同步: c=gvg&m=sync_info
     */
    public static function sync_info(Context $ctx): array
    {
        return self::index($ctx);
    }
}
