<?php

declare(strict_types=1);

namespace Hanjiang\Handler;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use OmniPHP\WebSocket\Broadcaster;

final class EquipForgeHandler
{
    public const array RECIPES = [
        1 => ['id' => 1, 'name' => '神兵·青龙偃月刀', 'type' => 1, 'quality' => 5, 'cost_silver' => 50000, 'cost_iron' => 10],
        2 => ['id' => 2, 'name' => '宝甲·兽面连环铠', 'type' => 2, 'quality' => 5, 'cost_silver' => 40000, 'cost_iron' => 8],
        3 => ['id' => 3, 'name' => '仙衣·八卦紫绶袍', 'type' => 3, 'quality' => 5, 'cost_silver' => 40000, 'cost_iron' => 8],
    ];

    /**
     * 铸造工坊主界面: c=equip_forge&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $iron = (int)(Redis::get('forge_iron:' . $uid) ?: 20);

        return [
            'status' => 1,
            'iron_count' => $iron,
            'recipes' => array_values(self::RECIPES),
            'message' => 'ok',
        ];
    }

    /**
     * 神兵铸造出炉: c=equip_forge&m=fabrication
     */
    public static function fabrication(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $recipeId = (int)($ctx->input('id', $ctx->input('recipe_id', 1)));

        if (!isset(self::RECIPES[$recipeId])) {
            return ['status' => 0, 'message' => '铸造图纸配方不存在'];
        }

        $cfg = self::RECIPES[$recipeId];
        $costSilver = (int)$cfg['cost_silver'];
        $costIron = (int)$cfg['cost_iron'];

        $iron = (int)(Redis::get('forge_iron:' . $uid) ?: 20);
        if ($iron < $costIron) {
            return ['status' => -1, 'message' => "精铁矿石不足，铸造需要 {$costIron} 块精铁"];
        }

        if (!MemberHandler::deductCurrency($uid, silver: $costSilver)) {
            return ['status' => -2, 'message' => "银两不足，铸造需要 {$costSilver} 银两"];
        }

        // 扣除精铁
        $newIron = $iron - $costIron;
        Redis::set('forge_iron:' . $uid, (string)$newIron, 86400 * 30);

        // 铸造成品直接写入背包
        DB::table('equipment')->insert([
            'uid' => $uid,
            'equipid' => 100 + $cfg['id'],
            'name' => $cfg['name'],
            'type' => $cfg['type'],
            'quality' => $cfg['quality'],
            'level' => 1,
            'generalid' => 0,
            'number' => 1,
            'saleprice' => 1000,
        ]);

        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        $pName = $member['nickname'] ?? ('主公' . $uid);

        Broadcaster::broadcast('marquee', [
            'content' => "神工开物！主公【{$pName}】于天工坊开炉淬火，成功锻造出绝世神兵【{$cfg['name']}】！",
            'type' => 'system_broadcast',
        ]);

        return [
            'status' => 1,
            'equip_name' => $cfg['name'],
            'left_iron' => $newIron,
            'message' => "神装淬火出炉！成功铸造神品【{$cfg['name']}】并已放入背包！",
        ];
    }

    /**
     * 装备熔炼分解: c=equip_forge&m=resolve
     */
    public static function resolve(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $eid = (int)($ctx->input('id', $ctx->input('eid', 0)));

        if ($eid <= 0) {
            return ['status' => 0, 'message' => '请选择需要熔炼的装备'];
        }

        $equip = DB::table('equipment')->where('uid', $uid)->where('id', $eid)->whereNull('deleted_at')->first();
        if ($equip === null) {
            return ['status' => -1, 'message' => '装备不存在'];
        }

        if ((int)$equip['generalid'] > 0) {
            return ['status' => 0, 'message' => '穿戴中的装备无法熔炼，请先卸下'];
        }

        // 软删除装备
        DB::table('equipment')->where('id', $eid)->update(['deleted_at' => date('Y-m-d H:i:s')]);

        // 产出精铁
        $iron = (int)(Redis::get('forge_iron:' . $uid) ?: 20) + 3;
        Redis::set('forge_iron:' . $uid, (string)$iron, 86400 * 30);

        return [
            'status' => 1,
            'get_iron' => 3,
            'total_iron' => $iron,
            'message' => "熔炉化铁！成功熔炼【{$equip['name']}】，提取返还 3 块精铁矿石！",
        ];
    }
}
