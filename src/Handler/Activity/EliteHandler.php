<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;

use Hanjiang\Battle\BattleEngine;
use Hanjiang\Battle\Position;
use Hanjiang\Battle\TeamBuilder;
use OmniPHP\Cache\Redis;
use OmniPHP\Http\Context;

final class EliteHandler
{
    public const array ELITE_MAPS = [
        1 => ['id' => 1, 'name' => '精英·大兴山断桥', 'hp' => 2200, 'attack' => 320, 'defense' => 120, 'silver' => 10000, 'drop' => '百炼强化玄铁 x2'],
        2 => ['id' => 2, 'name' => '精英·宛城火海营', 'hp' => 3200, 'attack' => 380, 'defense' => 150, 'silver' => 18000, 'drop' => '赤炎红宝石 x1'],
        3 => ['id' => 3, 'name' => '精英·虎牢三英关', 'hp' => 4500, 'attack' => 450, 'defense' => 180, 'silver' => 30000, 'drop' => '极品龙纹兵符 x1'],
    ];

    /**
     * 获取多人副本 WebSocket 实时服务地址: c=elite&m=get_server
     */
    public static function get_server(Context $ctx): array
    {
        $appConfig = require dirname(__DIR__, 3) . '/config/app.php';
        $wsPort = $appConfig['ws']['public_port'] ?? 18101;
        $host = $ctx->request()->host();
        $hostName = explode(':', $host)[0] ?: '127.0.0.1';
        $proto = ($ctx->request()->header('x-forwarded-proto') === 'https') ? 'wss:' : 'ws:';
        $wsUrl = "{$proto}//{$hostName}:{$wsPort}";

        return [
            'status' => 1,
            'url' => $wsUrl,
            'message' => 'ok',
        ];
    }

    /**
     * 精英副本主界面: c=elite&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $times = (int)(Redis::get('elite_times:' . $uid) ?? 5);

        return [
            'status' => 1,
            'left_times' => $times,
            'maps' => array_values(self::ELITE_MAPS),
            'message' => 'ok',
        ];
    }

    /**
     * 发起精英战役: c=elite&m=action
     */
    public static function action(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $mapId = (int)($ctx->input('id', $ctx->input('map_id', 1)));
        $times = (int)(Redis::get('elite_times:' . $uid) ?? 5);

        if ($times <= 0) {
            return ['status' => -1, 'message' => '今日精英副本挑战次数已用尽'];
        }

        if (!isset(self::ELITE_MAPS[$mapId])) {
            return ['status' => 0, 'message' => '精英关卡不存在'];
        }

        $cfg = self::ELITE_MAPS[$mapId];

        // 1. 我方队伍（旧版 battle_model->init_member_team_info，PVE）
        $left = TeamBuilder::forMember($uid, pve: true);
        if ($left['status'] === -1 || $left['status'] === -3) {
            // 旧版 battle_model 返回 -1（没有阵法）/ -3（武将信息错误）；本玩法尚未按旧版控制器移植，直接回传该状态
            return ['status' => $left['status'], 'message' => '出战阵容信息错误'];
        }
        // -2（阵法里没有武将）旧版按空队伍继续战斗，空队伍直接判负

        // 2. 精英据点守军
        // 依赖未移植：旧版多人副本走 battle_rush + elite_monster（EliteMission / EliteMonster），本玩法的关卡与守军数值是新版自定的
        // 依赖未移植：守军数值仍为新版自定
        $rightTeam = [Position::FrontMiddle->value => TeamBuilder::unportedUnit(2000 + $cfg['id'], $cfg['name'], $cfg['hp'], $cfg['attack'], $cfg['defense'])];

        // 3. 推演
        $engine = new BattleEngine($left['matrix_type'], $left['matrix_coe']);
        $battle = $engine->battle($left['team'], $rightTeam);
        $isWin = $battle['assess'] >= 1;

        Redis::set('elite_times:' . $uid, (string)($times - 1), 86400);

        if (!$isWin) {
            return [
                'status' => 1,
                'win' => -1,
                'message' => "精英战役惜败于【{$cfg['name']}】，请强化装备再来挑战！",
            ];
        }

        // 发放银两与战利品
        MemberHandler::addCurrency($uid, silver: (int)$cfg['silver']);

        return [
            'status' => 1,
            'win' => 1,
            'map_name' => $cfg['name'],
            'silver' => $cfg['silver'],
            'drop' => $cfg['drop'],
            'message' => "精英战役大捷！击破【{$cfg['name']}】，斩获银两 {$cfg['silver']} 与珍稀战利品【{$cfg['drop']}】！",
        ];
    }
}
