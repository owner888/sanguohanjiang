<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;

use Hanjiang\Battle\BattleEngine;
use Hanjiang\Battle\Hero;
use Hanjiang\Battle\Position;
use Hanjiang\Battle\TeamBuilder;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use OmniPHP\WebSocket\Broadcaster;

final class ChallengeHandler
{
    public const array MASTERS = [
        1 => ['id' => 1, 'name' => '擂主·西凉锦马超', 'hp' => 3000, 'attack' => 450, 'defense' => 180, 'silver' => 15000, 'rep' => 100],
        2 => ['id' => 2, 'name' => '擂主·常山赵子龙', 'hp' => 4000, 'attack' => 500, 'defense' => 200, 'silver' => 25000, 'rep' => 200],
        3 => ['id' => 3, 'name' => '擂主·武圣关云长', 'hp' => 5000, 'attack' => 580, 'defense' => 240, 'silver' => 50000, 'rep' => 500],
    ];

    /**
     * 擂台主界面: c=challenge&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $masterId = (int)(Redis::get('challenge_master:' . $uid) ?: 1);

        return [
            'status' => 1,
            'current_stage' => $masterId,
            'masters' => array_values(self::MASTERS),
            'message' => 'ok',
        ];
    }

    /**
     * 发起擂台挑战: c=challenge&m=action
     */
    public static function action(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $masterId = (int)($ctx->input('id', $ctx->input('master_id', 1)));
        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();

        if (!isset(self::MASTERS[$masterId])) {
            return ['status' => 0, 'message' => '擂台擂主不存在'];
        }

        $cfg = self::MASTERS[$masterId];

        // 1. 我方队伍（旧版 challenge 控制器走 battle_model->battle_pk：init_member_team_info，非 PVE）
        $left = TeamBuilder::forMember($uid);
        if ($left['status'] === -1 || $left['status'] === -3) {
            // 旧版 battle_model 返回 -1（没有阵法）/ -3（武将信息错误）；本玩法尚未按旧版控制器移植，直接回传该状态
            return ['status' => $left['status'], 'message' => '出战阵容信息错误'];
        }
        // -2（阵法里没有武将）旧版按空队伍继续战斗，空队伍直接判负
        $leftHeroes = array_values(TeamBuilder::heroes($left['team'], $left['matrix_type'], $left['matrix_coe']));

        // 2. 擂主神将
        // 依赖未移植：旧版擂台对手是其他玩家（battle_pk($touid)），本玩法的擂主与数值是新版自定的
        $rightHeroes = [
            Hero::unported(3000 + $cfg['id'], $cfg['name'], Position::FrontMiddle, $cfg['hp'], $cfg['attack'], $cfg['defense']),
        ];

        // 3. 推演
        $engine = new BattleEngine($leftHeroes, $rightHeroes);
        $simResult = $engine->simulate();
        $isWin = $simResult['win'];

        if (!$isWin) {
            return [
                'status' => 1,
                'win' => -1,
                'master_id' => $masterId,
                'rounds' => $simResult['rounds'],
                'message' => "攻擂惜败！【{$cfg['name']}】武艺精湛，请重整旗鼓再来攻擂！",
            ];
        }

        // 战胜：发奖并推进擂台
        $nextMaster = min(3, $masterId + 1);
        Redis::set('challenge_master:' . $uid, (string)$nextMaster, 86400 * 30);

        MemberHandler::addCurrency($uid, silver: (int)$cfg['silver'], reputation: (int)$cfg['rep']);

        if ($masterId === 3) {
            $pName = $member['nickname'] ?? ('主公' . $uid);
            Broadcaster::broadcast('marquee', [
                'content' => "威震擂台！主公【{$pName}】力克终极擂主【{$cfg['name']}】，荣登英雄试炼总擂主宝座！",
                'type' => 'system_broadcast',
            ]);
        }

        return [
            'status' => 1,
            'win' => 1,
            'master_name' => $cfg['name'],
            'silver' => $cfg['silver'],
            'reputation' => $cfg['rep'],
            'rounds' => $simResult['rounds'],
            'message' => "攻擂大捷！成功击溃【{$cfg['name']}】，夺取擂主荣耀！获得银两 {$cfg['silver']}、声望 {$cfg['rep']}！",
        ];
    }
}
