<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use Hanjiang\MasterData\MaterialsTemp;
use OmniPHP\Database\DB;

/**
 * 拥有的锻造材料（移植旧版 models/newequip/copies_model.php 的 get_materials_info / materials_change）
 *
 * 存储同旧版：own_materials 每个玩家每种材料一行（唯一键 uid + materials_id，num 为数量），
 * 每次增减在 own_materials_log 记一条（type 1 增加 / 0 消耗，now_num 为变动后的数量）。
 * 材料模板取 MasterData MaterialsTemp。旧版副本、过关斩将、蹴鞠、圣诞团购等玩法都经 copies_model 发材料，
 * 打造（equip_forge）读取 / 消耗同一张表。
 */
final class OwnMaterials
{
    /**
     * 材料模板（移植 get_materials_info：materials_temp 按 materials_id 取一行；id 为 0 时不加条件，取表中第一行）
     *
     * @return array<string, mixed>|null
     */
    public static function template(int $id = 0): ?array
    {
        foreach (MaterialsTemp::ROWS as $row) {
            if ($id <= 0 || $row['materials_id'] === $id) {
                return $row;
            }
        }
        return null;
    }

    /**
     * 添加或消耗材料（移植 materials_change）
     *
     * 消耗时数量不足按 0 计；没有这种材料时消耗失败。旧版调用方传的 $id 是 materials_temp.id
     * （该表 id 与 materials_id 相同）。
     *
     * @param int $type 1 加资源，0 减资源
     * @return int 1 成功，0 失败
     */
    public static function change(int $uid, int $id, int $quality, string $name, int $chip, int $num, int $type = 0, ?int $now = null): int
    {
        $now ??= time();
        $result = DB::table('own_materials')->select('num')
            ->where('uid', $uid)->where('materials_id', $id)->whereNull('deleted_at')->first();
        if ($result !== null) {
            $own = (int)$result['num'];
            $nowNum = $own - $num;
            $nowNum = $nowNum > 0 ? $nowNum : 0;
            if ($type === 1) {
                $nowNum = $num + $own;
            }
            // 添加日志
            self::addLog($uid, $type, $id, $name, $quality, $chip, $num, $nowNum, $now);
            // 更新数量
            DB::table('own_materials')->where('uid', $uid)->where('materials_id', $id)->whereNull('deleted_at')
                ->update(['num' => $nowNum]);
            return 1;
        }

        if ($type === 0) {
            return 0;
        }
        // 添加日志
        self::addLog($uid, $type, $id, $name, $quality, $chip, $num, $num, $now);
        // 初始化数据
        DB::table('own_materials')->insert([
            'uid' => $uid,
            'materials_id' => $id,
            'name' => $name,
            'quality' => $quality,
            'chip' => $chip,
            'num' => $num,
            'dateline' => $now,
        ]);
        return 1;
    }

    /**
     * own_materials_log 一条（旧版 copies_model 不写 class / method 列，按空串）
     */
    private static function addLog(int $uid, int $type, int $id, string $name, int $quality, int $chip, int $num, int $nowNum, int $now): void
    {
        DB::table('own_materials_log')->insert([
            'uid' => $uid,
            'type' => $type,
            'materials_id' => $id,
            'name' => $name,
            'quality' => $quality,
            'chip' => $chip,
            'num' => $num,
            'now_num' => $nowNum,
            'dateline' => $now,
        ]);
    }
}
