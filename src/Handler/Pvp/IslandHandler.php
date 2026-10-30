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

final class IslandHandler
{
    public const array ISLANDS = [
        1 => ['id' => 1, 'name' => '蓬莱仙境银矿岛', 'boss' => '蓬莱护岛神蛟', 'hp' => 2500, 'attack' => 350, 'defense' => 130, 'silver' => 20000, 'gold' => 10],
        2 => ['id' => 2, 'name' => '瀛洲极品赤金岛', 'boss' => '瀛洲双头火雕', 'hp' => 3800, 'attack' => 420, 'defense' => 160, 'silver' => 35000, 'gold' => 20],
        3 => ['id' => 3, 'name' => '方丈远古秘境岛', 'boss' => '方丈通天神龟', 'hp' => 5200, 'attack' => 500, 'defense' => 200, 'silver' => 60000, 'gold' => 50],
    ];

    /**
     * 金银岛主界面: c=island&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $times = (int)(Redis::get('island_times:' . $uid) ?? 5);

        return [
            'status' => 1,
            'left_times' => $times,
            'islands' => array_values(self::ISLANDS),
            'message' => 'ok',
        ];
    }

    /**
     * 登陆挑战金银岛: c=island&m=pk
     */
    public static function pk(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $islandId = (int)($ctx->input('id', $ctx->input('island_id', 1)));
        $times = (int)(Redis::get('island_times:' . $uid) ?? 5);

        if ($times <= 0) {
            return ['status' => -1, 'message' => '今日金银岛探索次数已用尽'];
        }

        if (!isset(self::ISLANDS[$islandId])) {
            return ['status' => 0, 'message' => '海岛不存在'];
        }

        $cfg = self::ISLANDS[$islandId];
        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();

        // 1. 我方队伍（旧版 island 控制器走 battle_model->battle_island：init_member_team_info，PVE）
        $left = TeamBuilder::forMember($uid, pve: true);
        if ($left['status'] === -1 || $left['status'] === -3) {
            // 旧版 battle_model 返回 -1（没有阵法）/ -3（武将信息错误）；本玩法尚未按旧版控制器移植，直接回传该状态
            return ['status' => $left['status'], 'message' => '出战阵容信息错误'];
        }
        // -2（阵法里没有武将）旧版按空队伍继续战斗，空队伍直接判负

        // 2. 护岛神兽
        // 依赖未移植：旧版金银洞守军取 island 关卡的 position1~9 → island_monster（TeamBuilder::MONSTER_ISLAND），本玩法的关卡 id 与守军数值是新版自定的，对不上旧版关卡
        // 依赖未移植：守军数值仍为新版自定
        $rightTeam = [Position::FrontMiddle->value => TeamBuilder::unportedUnit(6500 + $cfg['id'], $cfg['boss'], $cfg['hp'], $cfg['attack'], $cfg['defense'])];

        // 3. 推演
        $engine = new BattleEngine($left['matrix_type'], $left['matrix_coe']);
        $battle = $engine->battle($left['team'], $rightTeam);
        $isWin = $battle['assess'] >= 1;

        Redis::set('island_times:' . $uid, (string)($times - 1), 86400);

        if (!$isWin) {
            return [
                'status' => 1,
                'win' => -1,
                'message' => "探索海岛遭遇重创，未能击退【{$cfg['boss']}】！",
            ];
        }

        // 胜利发奖
        $rewardSilver = (int)$cfg['silver'];
        $rewardGold = (int)$cfg['gold'];
        MemberHandler::addCurrency($uid, silver: $rewardSilver, gold: $rewardGold);

        if ($islandId === 3) {
            $pName = $member['nickname'] ?? ('主公' . $uid);
            Broadcaster::broadcast('marquee', [
                'content' => "海内伏波！主公【{$pName}】扬帆远航，成功征服【{$cfg['name']}】，夺取海外龙脉宝藏！",
                'type' => 'system_broadcast',
            ]);
        }

        return [
            'status' => 1,
            'win' => 1,
            'island_name' => $cfg['name'],
            'silver' => $rewardSilver,
            'gold' => $rewardGold,
            'message' => "海外探险大获全胜！征服【{$cfg['name']}】，斩获银两 {$rewardSilver}、元宝 {$rewardGold}！",
        ];
    }
}
