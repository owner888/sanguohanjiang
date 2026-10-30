<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Campaign;

use Hanjiang\Handler\City\MemberHandler;

use Hanjiang\Battle\BattleEngine;
use Hanjiang\Battle\Position;
use Hanjiang\Battle\TeamBuilder;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use OmniPHP\WebSocket\Broadcaster;

final class EpicHandler
{
    public const array EPICS = [
        1 => ['id' => 1, 'name' => '史诗·白马之战', 'boss' => '史诗·河北双煞', 'hp' => 3500, 'attack' => 450, 'defense' => 180, 'silver' => 30000, 'gold' => 50],
        2 => ['id' => 2, 'name' => '史诗·火烧乌巢', 'boss' => '史诗·淳于琼部众', 'hp' => 5000, 'attack' => 520, 'defense' => 200, 'silver' => 50000, 'gold' => 80],
        3 => ['id' => 3, 'name' => '史诗·华容道劫曹', 'boss' => '史诗·虎豹骑亲卫', 'hp' => 7000, 'attack' => 600, 'defense' => 240, 'silver' => 80000, 'gold' => 120],
    ];

    /**
     * 史诗战纪主界面: c=epic&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $cleared = (int)(Redis::get('epic_cleared:' . $uid) ?: 0);

        $list = [];
        foreach (self::EPICS as $id => $e) {
            $list[$id] = array_merge($e, [
                'status' => ($id <= $cleared) ? 2 : (($id === $cleared + 1) ? 1 : 0),
            ]);
        }

        return [
            'status' => 1,
            'cleared_epics' => $cleared,
            'list' => array_values($list),
            'message' => 'ok',
        ];
    }

    /**
     * 发起史诗战役推演: c=epic&m=force
     */
    public static function force(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $epicId = (int)($ctx->input('id', $ctx->input('epic_id', 1)));

        if (!isset(self::EPICS[$epicId])) {
            return ['status' => 0, 'message' => '史诗战役不存在'];
        }

        $cfg = self::EPICS[$epicId];
        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();

        // 1. 我方队伍（旧版 epic 控制器不调用 battle_model，本玩法的战斗是新版自定的；按 PVE 组队）
        $left = TeamBuilder::forMember($uid, pve: true);
        if ($left['status'] === -1 || $left['status'] === -3) {
            // 旧版 battle_model 返回 -1（没有阵法）/ -3（武将信息错误）；本玩法尚未按旧版控制器移植，直接回传该状态
            return ['status' => $left['status'], 'message' => '出战阵容信息错误'];
        }
        // -2（阵法里没有武将）旧版按空队伍继续战斗，空队伍直接判负

        // 2. 敌军
        // 依赖未移植：旧版 epic 无战斗，敌军与数值是新版自定的
        // 依赖未移植：守军数值仍为新版自定
        $rightTeam = [Position::FrontMiddle->value => TeamBuilder::unportedUnit(9500 + $cfg['id'], $cfg['boss'], $cfg['hp'], $cfg['attack'], $cfg['defense'])];

        // 3. 推演
        $engine = new BattleEngine($left['matrix_type'], $left['matrix_coe']);
        $battle = $engine->battle($left['team'], $rightTeam);
        $isWin = $battle['assess'] >= 1;

        if (!$isWin) {
            return [
                'status' => 1,
                'win' => -1,
                'message' => "史诗战役【{$cfg['name']}】冲击受挫，请调整阵型战位！",
            ];
        }

        // 胜利发奖
        $cleared = (int)(Redis::get('epic_cleared:' . $uid) ?: 0);
        if ($epicId > $cleared) {
            Redis::set('epic_cleared:' . $uid, (string)$epicId, 86400 * 30);
        }

        $rewardSilver = (int)$cfg['silver'];
        $rewardGold = (int)$cfg['gold'];
        MemberHandler::addCurrency($uid, silver: $rewardSilver, gold: $rewardGold);

        if ($epicId === 3) {
            $pName = $member['nickname'] ?? ('主公' . $uid);
            Broadcaster::broadcast('marquee', [
                'content' => "万古流芳！主公【{$pName}】亲率精锐，一举荡平【{$cfg['name']}】，史诗丰碑永载史册！",
                'type' => 'system_broadcast',
            ]);
        }

        return [
            'status' => 1,
            'win' => 1,
            'epic_name' => $cfg['name'],
            'silver' => $rewardSilver,
            'gold' => $rewardGold,
            'message' => "史诗战役大捷！成功通关【{$cfg['name']}】，斩获银两 {$rewardSilver}、元宝 {$rewardGold}！",
        ];
    }
}
