<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Campaign;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\General\GeneralHandler;

use Hanjiang\Battle\BattleEngine;
use Hanjiang\Battle\Hero;
use Hanjiang\Battle\Position;
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

        // 2. 敌军
        $rightHeroes = [
            new Hero(9500 + $cfg['id'], $cfg['boss'], Position::FrontMiddle, $cfg['hp'], $cfg['hp'], $cfg['attack'], $cfg['defense'], 130),
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
            'rounds' => $simResult['rounds'],
            'message' => "史诗战役大捷！成功通关【{$cfg['name']}】，斩获银两 {$rewardSilver}、元宝 {$rewardGold}！",
        ];
    }
}
