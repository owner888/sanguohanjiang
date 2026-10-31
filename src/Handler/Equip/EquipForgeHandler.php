<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Equip;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Model\Equipment;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

final class EquipForgeHandler
{
    /**
     * 依赖未移植：旧版打造走 newequip_* / materials_temp 体系，图纸与价格不是旧版数据。
     * equipid 为产物对应的 MasterData Equip 模板：只有「青龙偃月刀」能按名称、类型、品质对上（22），
     * 另两张图纸的产物在 MasterData 中不存在，equipid 为 null，不可锻造。
     */
    public const array RECIPES = [
        1 => ['id' => 1, 'name' => '神兵·青龙偃月刀', 'type' => 1, 'quality' => 5, 'cost_silver' => 50000, 'cost_iron' => 10, 'equipid' => 22],
        2 => ['id' => 2, 'name' => '宝甲·兽面连环铠', 'type' => 2, 'quality' => 5, 'cost_silver' => 40000, 'cost_iron' => 8, 'equipid' => null],
        3 => ['id' => 3, 'name' => '仙衣·八卦紫绶袍', 'type' => 3, 'quality' => 5, 'cost_silver' => 40000, 'cost_iron' => 8, 'equipid' => null],
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
        // 依赖未移植：旧版打造成功 chat_model->send_message_to_horn(chat_msg) 小喇叭（随旧版 newequip 打造移植）
        $uid = MemberHandler::resolveUid($ctx);
        $recipeId = (int)($ctx->input('id', $ctx->input('recipe_id', 1)));

        if (!isset(self::RECIPES[$recipeId])) {
            return ['status' => 0, 'message' => '铸造图纸配方不存在'];
        }

        $cfg = self::RECIPES[$recipeId];
        if ($cfg['equipid'] === null) {
            return ['status' => 0, 'message' => '该铸造图纸尚未开放'];
        }
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

        // 铸造成品写入背包（入库走唯一入口，属性取 MasterData Equip 模板）
        Equipment::addFromTemplate($uid, (int)$cfg['equipid']);

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
