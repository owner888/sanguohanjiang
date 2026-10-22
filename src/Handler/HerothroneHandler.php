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

        // 2. 王座守将
        $rightHeroes = [
            new Hero(7500 + $cfg['id'], $cfg['defender'], Position::FrontMiddle, $cfg['hp'], $cfg['hp'], $cfg['attack'], $cfg['defense'], 130),
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
            'rounds' => $simResult['rounds'],
            'message' => "霸主加冕！成功击败守将，登顶【{$cfg['name']}】！斩获银两 {$cfg['silver']}、声望 {$cfg['rep']}！",
        ];
    }
}
