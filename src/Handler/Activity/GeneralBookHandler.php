<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;
use Hanjiang\Handler\City\MemberHandler;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

final class GeneralBookHandler
{
    public const array COMBOS = [
        1 => ['id' => 1, 'name' => '桃园结义', 'desc' => '刘备、关羽、张飞并肩作战', 'attr' => '全军攻击 +5% 与生命 +500', 'cost_silver' => 20000],
        2 => ['id' => 2, 'name' => '蜀汉五虎上将', 'desc' => '关羽、张飞、赵云、马超、黄忠', 'attr' => '全军暴击概率 +5%', 'cost_silver' => 50000],
        3 => ['id' => 3, 'name' => '曹魏五子良将', 'desc' => '张辽、乐进、于禁、张郃、徐晃', 'attr' => '全军物理防御 +8%', 'cost_silver' => 30000],
        4 => ['id' => 4, 'name' => '东吴四大都督', 'desc' => '周瑜、鲁肃、吕蒙、陆逊', 'attr' => '全军策略防御 +8%', 'cost_silver' => 30000],
    ];

    /**
     * 名将谱主界面: c=general_book&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $unlockedCombos = Redis::getJson('general_book:' . $uid) ?: [1 => 1];

        $list = [];
        foreach (self::COMBOS as $id => $c) {
            $lvl = (int)($unlockedCombos[$id] ?? 0);
            $list[$id] = array_merge($c, [
                'level' => $lvl,
                'is_active' => ($lvl > 0) ? 1 : 0,
            ]);
        }

        return [
            'status' => 1,
            'book_title' => '三国英雄名将谱',
            'list' => array_values($list),
            'message' => 'ok',
        ];
    }

    /**
     * 点亮激活名将羁绊: c=general_book&m=levelup
     */
    public static function levelup(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $comboId = (int)($ctx->input('id', $ctx->input('combo_id', 1)));

        if (!isset(self::COMBOS[$comboId])) {
            return ['status' => 0, 'message' => '羁绊组合不存在'];
        }

        $cfg = self::COMBOS[$comboId];
        $costSilver = (int)$cfg['cost_silver'];

        if (!MemberHandler::deductCurrency($uid, silver: $costSilver)) {
            return ['status' => -1, 'message' => "银两不足，点亮名将羁绊需要 {$costSilver} 银两"];
        }

        $unlockedCombos = Redis::getJson('general_book:' . $uid) ?: [1 => 1];
        $curLvl = (int)($unlockedCombos[$comboId] ?? 0);
        $newLvl = $curLvl + 1;
        $unlockedCombos[$comboId] = $newLvl;
        Redis::setJson('general_book:' . $uid, $unlockedCombos, 86400 * 30);

        return [
            'status' => 1,
            'combo_name' => $cfg['name'],
            'level' => $newLvl,
            'message' => "名将羁绊【{$cfg['name']}】点亮成功！永久赋予：{$cfg['attr']}！",
        ];
    }
}
