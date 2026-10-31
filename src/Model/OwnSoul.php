<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use Hanjiang\MasterData\Soul;
use Hanjiang\MasterData\SoulType;
use OmniPHP\Database\DB;

/**
 * 将魂与将魂包裹
 *
 * 移植旧版 models/soul_model.php 的 add_hero_soul / del_soul / get_unequip_count / get_levelup_rule 的属性部分，
 * 与 models/member_model.php 的 add_souluse / sub_souluse / add_soulpack，以及 controllers/tower.php 的 _isfull 判断。
 *
 * 存储同旧版：own_soul 每个将魂一行（name / type / quality 取 MasterData Soul，image 为「类型」接「品质」的数字，
 * power 为 MasterData SoulType 的 lv{等级}；gid 为装备该将魂的将领，未装备为 0；旧版 disable 改为软删除）；
 * member.soulpack 为包裹格数（旧版注册时写 4），member.souluse 为占用格数。
 * 新版另有自定的 general_soul 命格表（SoulHandler），与这里无关。
 *
 * 并发：插入是单条批量 INSERT，包裹占用是单条原子 UPDATE。
 */
final class OwnSoul
{
    /** 将魂等级上限（旧版 get_levelup_rule 的 $level_rule，按品质） */
    private const array MAX_LEVEL = [1 => 7, 2 => 9, 3 => 11, 4 => 13, 5 => 15, 6 => 20];

    /**
     * 添加将魂（移植 add_hero_soul），成功后包裹占用加 $num（add_souluse）
     *
     * 属性取 SoulType 的 lv{$level}；等级超过该品质上限时旧版取不到属性，按 0 记。旧版 type > 3 的属性带「%」后缀
     * （get_levelup_rule），写进整数列时被截掉，返回值里仍带「%」；这里库里存整数，返回值照旧版带「%」。
     *
     * @return array<string, mixed> 成功返回将魂信息（单个时不含 uid、带 id；多个时含 uid）与 num；模板不存在或失败返回空数组
     */
    public static function addHeroSoul(int $uid, int $id, int $level = 0, int $num = 1): array
    {
        $info = Soul::ROWS[$id] ?? null;
        if ($info === null || $num <= 0) {
            return [];
        }
        $data = [
            'uid' => $uid,
            'name' => $info['name'],
            'type' => $info['type'],
            'quality' => $info['quality'],
            'image' => (int)($info['type'] . $info['quality']),
            'level' => $level,
            'power' => self::power($level, $info['type'], $info['quality']),
        ];

        if ($num > 1) {
            if (DB::table('own_soul')->insertBatch(array_fill(0, $num, $data)) <= 0) {
                return [];
            }
            // 背包处理
            self::addSouluse($uid, $num);
            $return = $data;
            $return['power'] = self::powerText($data['power'], $data['type']);
            $return['num'] = $num;
            return $return;
        }

        $newId = (int)DB::table('own_soul')->insert($data);
        if ($newId <= 0) {
            return [];
        }
        self::addSouluse($uid, 1);
        unset($data['uid']);
        $return = $data;
        $return['power'] = self::powerText($data['power'], $data['type']);
        $return['id'] = $newId;
        $return['num'] = $num;
        return $return;
    }

    /**
     * 删除将魂（移植 del_soul：旧版 disable 写时间戳，这里软删除）
     *
     * @return int 1 成功，0 不存在或已删除
     */
    public static function delSoul(int $uid, int $sid): int
    {
        $affected = DB::table('own_soul')->where('id', $sid)->where('uid', $uid)->whereNull('deleted_at')
            ->update(['deleted_at' => date('Y-m-d H:i:s')]);
        return $affected > 0 ? 1 : 0;
    }

    /**
     * 未装备的将魂个数（移植 get_unequip_count；$quality > 0 时只数该品质）
     */
    public static function unequipCount(int $uid, int $quality = 0): int
    {
        $query = DB::table('own_soul')->where('uid', $uid)->where('gid', 0)->whereNull('deleted_at');
        if ($quality > 0) {
            $query->where('quality', $quality);
        }
        return $query->count();
    }

    /**
     * 将魂包裹是否已满（旧版 controllers/tower.php：soulpack <= souluse）；玩家不存在按满
     */
    public static function isPackFull(int $uid): bool
    {
        $row = DB::table('member')->select('souluse', 'soulpack')->where('uid', $uid)->whereNull('deleted_at')->first();
        return $row === null || (int)$row['soulpack'] <= (int)$row['souluse'];
    }

    /**
     * 增加将魂包裹占用（移植 add_souluse：$add 不大于 1 时按 1 算）
     *
     * @return int 1 成功，0 失败
     */
    public static function addSouluse(int $uid, int $add = 1): int
    {
        return Member::incrementColumn($uid, 'souluse', $add > 1 ? $add : 1) === null ? 0 : 1;
    }

    /**
     * 减少将魂包裹占用（移植 sub_souluse：$sub 不大于 1 时按 1 算；旧版不检查下限，无符号列截到 0）
     *
     * @return int 1 成功，0 失败
     */
    public static function subSouluse(int $uid, int $sub = 1): int
    {
        return Member::decrementColumnFloor($uid, 'souluse', $sub > 1 ? $sub : 1) === null ? 0 : 1;
    }

    /**
     * 增加将魂包裹格数（移植 add_soulpack：$add 不大于 1 时按 1 算）
     *
     * @return int 1 成功，0 失败
     */
    public static function addSoulpack(int $uid, int $add = 1): int
    {
        return Member::incrementColumn($uid, 'soulpack', $add > 1 ? $add : 1) === null ? 0 : 1;
    }

    /**
     * 输出用的将魂属性（旧版 type > 3 为百分比，带「%」后缀）
     */
    public static function powerText(int $power, int $type): int|string
    {
        return $type > 3 ? $power . '%' : $power;
    }

    /**
     * 将魂属性（get_levelup_rule 的 addition：SoulType 第 $type 行的 lv{$level}；超过品质等级上限或取不到为 0）
     */
    public static function power(int $level, int $type, int $quality): int
    {
        $max = self::MAX_LEVEL[$quality] ?? null;
        if ($max === null || $level > $max) {
            return 0;
        }
        return (int)(SoulType::ROWS[$type]['lv' . $level] ?? 0);
    }
}
