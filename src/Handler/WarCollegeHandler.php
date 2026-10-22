<?php

declare(strict_types=1);

namespace Hanjiang\Handler;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

final class WarCollegeHandler
{
    public const array TECHS = [
        1 => ['id' => 1, 'name' => '步兵·玄甲圆阵方略', 'type' => 'defense', 'val' => 2, 'unit' => '%全军防御'],
        2 => ['id' => 2, 'name' => '骑兵·铁骑破阵锋矢', 'type' => 'attack', 'val' => 2, 'unit' => '%全军攻击'],
        3 => ['id' => 3, 'name' => '弓手·神臂连珠齐射', 'type' => 'crit', 'val' => 1, 'unit' => '%全军暴击'],
        4 => ['id' => 4, 'name' => '策士·奇门遁甲八阵', 'type' => 'hp', 'val' => 3, 'unit' => '%全军生命'],
    ];

    /**
     * 战争学院主界面: c=war_college&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $techLevels = Redis::getJson('war_college_techs:' . $uid) ?: [1 => 1, 2 => 0, 3 => 0, 4 => 0];

        $list = [];
        foreach (self::TECHS as $id => $t) {
            $lvl = (int)($techLevels[$id] ?? 0);
            $list[$id] = array_merge($t, [
                'level' => $lvl,
                'cost_silver' => max(10000, $lvl * 10000),
                'cost_rep' => max(50, $lvl * 50),
                'total_effect' => ($lvl * $t['val']) . $t['unit'],
            ]);
        }

        return [
            'status' => 1,
            'college_name' => '三国内阁国家战争学院',
            'techs' => array_values($list),
            'message' => 'ok',
        ];
    }

    /**
     * 研发升级战争科技: c=war_college&m=level_up
     */
    public static function level_up(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $techId = (int)($ctx->input('id', $ctx->input('tech_id', 1)));

        if (!isset(self::TECHS[$techId])) {
            return ['status' => 0, 'message' => '兵种科技不存在'];
        }

        $techLevels = Redis::getJson('war_college_techs:' . $uid) ?: [1 => 1, 2 => 0, 3 => 0, 4 => 0];
        $curLvl = (int)($techLevels[$techId] ?? 0);
        $costSilver = max(10000, $curLvl * 10000);

        if (!MemberHandler::deductCurrency($uid, silver: $costSilver)) {
            return ['status' => -1, 'message' => "银两不足，研发科技需要 {$costSilver} 银两"];
        }

        $newLvl = $curLvl + 1;
        $techLevels[$techId] = $newLvl;
        Redis::setJson('war_college_techs:' . $uid, $techLevels, 86400 * 30);

        $cfg = self::TECHS[$techId];

        return [
            'status' => 1,
            'tech_id' => $techId,
            'name' => $cfg['name'],
            'level' => $newLvl,
            'effect' => ($newLvl * $cfg['val']) . $cfg['unit'],
            'message' => "科技研发大成！【{$cfg['name']}】升级至 {$newLvl} 级！{$cfg['name']} 提升 " . ($newLvl * $cfg['val']) . "{$cfg['unit']}！",
        ];
    }
}
