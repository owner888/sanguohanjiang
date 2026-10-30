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

final class HerothroneHandler
{
    public const array THRONES = [
        1 => ['id' => 1, 'name' => '青铜铁血王座', 'defender' => '王座禁卫军长', 'hp' => 3000, 'attack' => 400, 'defense' => 150, 'silver' => 20000, 'rep' => 100],
        2 => ['id' => 2, 'name' => '白银霸烈王座', 'defender' => '王座大统领', 'hp' => 4500, 'attack' => 500, 'defense' => 180, 'silver' => 35000, 'rep' => 200],
        3 => ['id' => 3, 'name' => '至尊天下第一王座', 'defender' => '无双神武战尊', 'hp' => 6000, 'attack' => 600, 'defense' => 220, 'silver' => 60000, 'rep' => 500],
    ];

    /**
     * 英雄王座主界面: c=herothrone&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $throneLvl = (int)(Redis::get('hero_throne_lvl:' . $uid) ?: 1);

        return [
            'status' => 1,
            'current_throne' => $throneLvl,
            'thrones' => array_values(self::THRONES),
            'message' => 'ok',
        ];
    }

    /**
     * 冲击王座争霸: c=herothrone&m=action 或 m=start
     */
    public static function action(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $throneId = (int)($ctx->input('id', $ctx->input('throne_id', 1)));
        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();

        if (!isset(self::THRONES[$throneId])) {
            return ['status' => 0, 'message' => '王座不存在'];
        }

        $cfg = self::THRONES[$throneId];

        // 1. 我方队伍（旧版 herothrone 控制器走 battle_model->battle_herothrone：init_member_team_info，PVE）
        $left = TeamBuilder::forMember($uid, pve: true);
        if ($left['status'] === -1 || $left['status'] === -3) {
            // 旧版 battle_model 返回 -1（没有阵法）/ -3（武将信息错误）；本玩法尚未按旧版控制器移植，直接回传该状态
            return ['status' => $left['status'], 'message' => '出战阵容信息错误'];
        }
        // -2（阵法里没有武将）旧版按空队伍继续战斗，空队伍直接判负

        // 2. 王座守将
        // 依赖未移植：旧版英雄王座守军取 herothrone_mission 的 position1~9 → herothrone_monster（TeamBuilder::MONSTER_HEROTHRONE），本玩法的关卡 id 与守军数值是新版自定的，对不上旧版关卡
        // 依赖未移植：守军数值仍为新版自定
        $rightTeam = [Position::FrontMiddle->value => TeamBuilder::unportedUnit(7500 + $cfg['id'], $cfg['defender'], $cfg['hp'], $cfg['attack'], $cfg['defense'])];

        // 3. 推演
        $engine = new BattleEngine($left['matrix_type'], $left['matrix_coe']);
        $battle = $engine->battle($left['team'], $rightTeam);
        $isWin = $battle['assess'] >= 1;

        if (!$isWin) {
            return [
                'status' => 1,
                'win' => -1,
                'message' => "登顶冲击失败，惜败于【{$cfg['defender']}】！",
            ];
        }

        // 登顶成功
        $nextThrone = min(3, $throneId + 1);
        Redis::set('hero_throne_lvl:' . $uid, (string)$nextThrone, 86400 * 30);

        MemberHandler::addCurrency($uid, silver: (int)$cfg['silver'], reputation: (int)$cfg['rep']);

        if ($throneId === 3) {
            $pName = $member['nickname'] ?? ('主公' . $uid);
            Broadcaster::broadcast('marquee', [
                'content' => "万王之王！主公【{$pName}】踏破重霄，成功登顶【{$cfg['name']}】，受天下万民俯首称臣！",
                'type' => 'system_broadcast',
            ]);
        }

        return [
            'status' => 1,
            'win' => 1,
            'throne_name' => $cfg['name'],
            'silver' => $cfg['silver'],
            'reputation' => $cfg['rep'],
            'message' => "霸主加冕！成功击败守将，登顶【{$cfg['name']}】！斩获银两 {$cfg['silver']}、声望 {$cfg['rep']}！",
        ];
    }
}
