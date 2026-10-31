<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use Hanjiang\MasterData\Soul;
use Hanjiang\MasterData\SoulType;
use OmniPHP\Database\DB;

/**
 * 将魂、将领将魂槽与原魂
 *
 * 移植旧版 models/soul_model.php（全部客户端用到的方法）与 models/member_model.php 的 add_soul / sub_soul / _soul_log、
 * add_souluse / sub_souluse / add_soulpack，以及 controllers/tower.php 的 _isfull 判断。
 *
 * 存储同旧版：
 * - own_soul 每个将魂一行（name / type / quality 取 MasterData Soul，image 为「类型」接「品质」的数字，
 *   power 为 MasterData SoulType 的 lv{等级}；gid 为装备该将魂的将领，未装备为 0；旧版 disable 改为软删除）；
 * - own_general_soul 每个将领一行（gid 唯一），site1~site8 为该槽位装备的 own_soul.id（0 为空）；
 * - own_soul_mergelog 将魂合成日志；
 * - member.soul 为原魂，增减记 usesoul_log（type 1 获得 / 2 花费）；member.soulpack 为包裹格数（旧版注册时写 4），
 *   member.souluse 为占用格数。
 *
 * 并发：插入是单条批量 INSERT，包裹占用与原魂增减是单条原子 UPDATE；读改写多行的操作（装备 / 卸下 / 转换 / 合成 / 升级）
 * 由调用方在 Member::locked 玩家行锁内执行。
 */
final class OwnSoul
{
    /** 将魂等级上限（旧版 get_levelup_rule 的 $level_rule，按品质） */
    private const array MAX_LEVEL = [1 => 7, 2 => 9, 3 => 11, 4 => 13, 5 => 15, 6 => 20];

    /** 将魂最高等级（soul_model->_max_level，convert 的等级上限） */
    private const int TOP_LEVEL = 20;

    /** 按转生次数已解锁的槽位数（unlock_site 的 $rule） */
    private const array UNLOCK_SITE = [0 => 0, 1 => 3, 2 => 4, 3 => 4, 4 => 5, 5 => 5, 6 => 6, 7 => 6, 8 => 7, 9 => 7, 10 => 8];

    /** 槽位解锁所需转生次数（unlock_level 的 $rule） */
    private const array UNLOCK_LEVEL = [1 => 1, 2 => 1, 3 => 1, 4 => 2, 5 => 4, 6 => 6, 7 => 8, 8 => 10];

    /** get_soul_by_id 的字段 */
    private const array SOUL_FIELDS = ['id', 'uid', 'gid', 'name', 'image', 'type', 'quality', 'level', 'power'];

    /** get_unequip_list 的字段 */
    private const array LIST_FIELDS = ['id', 'gid', 'name', 'image', 'type', 'quality', 'level', 'power'];

    /** 旧版 own_general_soul 的字段 */
    private const array GENERAL_FIELDS = ['gid', 'uid', 'site1', 'site2', 'site3', 'site4', 'site5', 'site6', 'site7', 'site8'];

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
     * 增加原魂（移植 member_model->add_soul；$sub 为 1 时同时把包裹占用减 1）
     *
     * 旧版玩家存在时不论写库成败都记 usesoul_log；这里只在写库成功时记。
     *
     * @return array{status: int} 1 成功，-1 玩家不存在
     */
    public static function addSoul(int $uid, int $soul, int $sub = 0, string $class = '', string $method = '', ?int $now = null): array
    {
        if ($sub === 1) {
            self::subSouluse($uid);
        }
        if ($soul <= 0) {
            // 数量为 0：库里数值不变，不记流水
            return ['status' => Member::logInfo($uid) === null ? -1 : 1];
        }
        $balance = Member::incrementColumn($uid, 'soul', $soul);
        if ($balance === null) {
            return ['status' => -1];
        }
        self::soulLog($uid, 1, $soul, $balance, $class, $method, $now);
        return ['status' => 1];
    }

    /**
     * 消耗原魂（移植 member_model->sub_soul：数量为 0 直接成功；不足返回 -1）
     *
     * @return array{status: int} 1 成功，-1 原魂不足，-2 玩家不存在
     */
    public static function subSoul(int $uid, int $soul, string $class = '', string $method = '', ?int $now = null): array
    {
        if ($soul === 0) {
            return ['status' => 1];
        }
        $balance = Member::decrementColumn($uid, 'soul', $soul);
        if ($balance === null) {
            return ['status' => Member::logInfo($uid) === null ? -2 : -1];
        }
        self::soulLog($uid, 2, $soul, $balance, $class, $method, $now);
        return ['status' => 1];
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

    /**
     * 将魂模板（移植 get_base_info_by_type：按类型与品质取第一行）
     *
     * @return array<string, mixed>
     */
    public static function baseInfoByType(int $type, int $quality): array
    {
        foreach (Soul::ROWS as $row) {
            if ((int)$row['type'] === $type && (int)$row['quality'] === $quality) {
                return $row;
            }
        }
        return [];
    }

    /**
     * 按 id 取拥有将魂（移植 get_soul_by_id：不按玩家过滤，只取未删除的）
     *
     * $ids 为单个 id 时返回一行（没有为空数组），为数组时返回列表（按 id 顺序）。
     * $output 为真时 type > 3 的 power 带「%」（旧版用于输出）。
     *
     * @param int|list<int> $ids
     * @return array<mixed>
     */
    public static function byId(int|array $ids, bool $output = true): array
    {
        $single = !is_array($ids);
        $list = $single ? [$ids] : array_values($ids);
        if ($list === []) {
            return [];
        }
        $rows = DB::table('own_soul')->select(...self::SOUL_FIELDS)->whereIn('id', $list)->whereNull('deleted_at')->orderBy('id')->get();
        $return = [];
        foreach ($rows as $row) {
            $item = self::typed($row, self::SOUL_FIELDS);
            if ($output) {
                $item['power'] = self::powerText($item['power'], $item['type']);
            }
            $return[] = $item;
        }
        if ($single) {
            return $return[0] ?? [];
        }
        return $return;
    }

    /**
     * 未装备将魂列表（移植 get_unequip_list）
     *
     * 页数按包裹格数 soulpack 计（ceil(soulpack / perpage)），页码大于页数时取最后一页；排序为品质、等级、id 倒序。
     * 列表项 image 重算为「类型」接「品质」，sort 恒为 1（旧版每行都把计数器重置为 1）；use 为未装备将魂个数，
     * all 为 max(use, soulpack)，base_soul 为当前原魂。$quality > 0 时只取该品质（合成界面用 5）。
     *
     * @return array<string, mixed>
     */
    public static function unequipList(int $uid, int $page = 1, int $perpage = 12, int $quality = 0): array
    {
        $member = DB::table('member')->select('soul', 'soulpack')->where('uid', $uid)->whereNull('deleted_at')->first() ?? ['soul' => 0, 'soulpack' => 0];
        $soulpack = (int)$member['soulpack'];
        $page = $page > 0 ? $page : 1;
        $perpage = $perpage > 0 ? $perpage : 12;

        // 统计背包将魂个数
        $totals = self::unequipCount($uid, $quality);

        // 总页数；页码大于总页数时取总页数
        $pages = (int)ceil($soulpack / $perpage);
        $page = $page > $pages ? $pages : $page;
        $offset = ($page - 1) * $perpage;
        if ($offset < 0) {
            $offset = 0;
        }

        $query = DB::table('own_soul')->select(...self::LIST_FIELDS)->where('uid', $uid);
        if ($quality > 0) {
            $query->where('quality', $quality);
        }
        $rows = $query->where('gid', 0)->whereNull('deleted_at')
            ->orderBy('quality', 'DESC')->orderBy('level', 'DESC')->orderBy('id', 'DESC')
            ->limit($perpage)->offset($offset)->get();

        $return = ['list' => []];
        foreach ($rows as $key => $row) {
            $item = self::typed($row, self::LIST_FIELDS);
            $item['sort'] = 1;
            $item['image'] = (int)($item['type'] . $item['quality']);
            $item['power'] = self::powerText($item['power'], $item['type']);
            $return['list'][$key] = $item;
        }
        $return['page'] = $page;
        $return['pages'] = $pages;
        $return['last'] = $page === $pages ? 1 : 0;
        $return['base_soul'] = (int)$member['soul'];
        $return['use'] = $totals;
        $return['all'] = $totals > $soulpack ? $totals : $soulpack;
        return $return;
    }

    /**
     * 将领装备的指定类型将魂（移植 get_equip_list：按 own_soul.gid 查，本人、未删除）
     *
     * @param list<int> $types
     * @return list<array<string, int|string>>
     */
    public static function equipList(int $uid, int $gid, array $types): array
    {
        if ($types === []) {
            return [];
        }
        $rows = DB::table('own_soul')->where('uid', $uid)->where('gid', $gid)->whereIn('type', $types)->whereNull('deleted_at')->orderBy('id')->get();
        return array_map(static fn (array $row): array => self::typed($row, self::SOUL_FIELDS), $rows);
    }

    /**
     * 将领的将魂槽（移植 get_general_soul：按 gid 查，不按玩家过滤）
     *
     * $gid 为单个 id 时返回一行（没有为空数组），为数组时返回列表。
     *
     * @param int|list<int> $gid
     * @return array<mixed>
     */
    public static function generalSoul(int|array $gid): array
    {
        $single = !is_array($gid);
        $list = $single ? [$gid] : array_values($gid);
        if ($list === []) {
            return [];
        }
        $rows = DB::table('own_general_soul')->select(...self::GENERAL_FIELDS)->whereIn('gid', $list)->whereNull('deleted_at')->orderBy('gid')->get();
        $rows = array_map(static fn (array $row): array => array_map('intval', $row), $rows);
        if ($single) {
            return $rows[0] ?? [];
        }
        return $rows;
    }

    /**
     * 新建将领的将魂槽（移植 add_general_soul）
     *
     * @return int 1 成功，-1 插入失败（已有该将领的记录时同样失败，旧版主键冲突）
     */
    public static function addGeneralSoul(int $gid, int $uid): int
    {
        $affected = DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_own_general_soul` (`gid`, `uid`) VALUES (:gid, :uid)',
            ['gid' => $gid, 'uid' => $uid]
        );
        return $affected > 0 ? 1 : -1;
    }

    /**
     * 升级规则（移植 get_levelup_rule）
     *
     * @return array{status: int, info?: array{addition: int|string, price: int, level: int}}
     *         status 1 成功（addition 为目标等级属性，type > 3 带「%」；price 为原魂花费），2 等级已满，0 取不到属性表
     */
    public static function levelupRule(int $level, int $type, int $quality): array
    {
        $return = ['status' => 0];
        // 旧版 $level_rule[$quality] 取不到为 null，任何等级都大于 null，按等级已满
        $maxlevel = self::MAX_LEVEL[$quality] ?? null;
        if ($maxlevel === null || $level > $maxlevel) {
            // 等级已满
            $return['status'] = 2;
            return $return;
        }
        $row = SoulType::ROWS[$type] ?? null;
        if ($row === null || !array_key_exists('lv' . $level, $row)) {
            return $return;
        }
        $return['info'] = [
            'addition' => self::powerText((int)$row['lv' . $level], $type),
            'price' => self::levelupPrice($level),
            'level' => $level,
        ];
        $return['status'] = 1;
        return $return;
    }

    /**
     * 升级花费（移植 _levelup_price：10 × 2^(目标等级 - 1) 原魂）
     */
    public static function levelupPrice(int $level): int
    {
        return (int)(10 * (2 ** ($level - 1)));
    }

    /**
     * 升级将魂（移植 levelup：本人、未删除；属性取整去掉「%」）
     *
     * 多加了「当前等级为 $level - 1」的条件：并发的两次升级只有一次生效（旧版同等级重复写入时 affected_rows 为 0，同样返回 0）。
     *
     * @return int 1 成功，0 失败
     */
    public static function levelup(int $uid, int $id, int $level, int|string $addition): int
    {
        $affected = DB::table('own_soul')->where('id', $id)->where('uid', $uid)->where('level', $level - 1)->whereNull('deleted_at')
            ->update(['level' => $level, 'power' => (int)$addition]);
        return $affected > 0 ? 1 : 0;
    }

    /**
     * 转换可得原魂（移植 convert：品质 + 1 级到当前等级的升级花费之和；等级不在 0 ~ 20 返回 -1）
     */
    public static function convertNum(int $level, int $quality): int
    {
        if ($level < 0 || $level > self::TOP_LEVEL) {
            return -1;
        }
        $return = $quality;
        while ($level > 0) {
            $return += self::levelupPrice($level);
            $level--;
        }
        return $return;
    }

    /**
     * 按转生次数获得已解锁槽位数（移植 unlock_site：10 转以上按 10）
     */
    public static function unlockSite(int $turn): int
    {
        $turn = $turn > 10 ? 10 : $turn;
        return self::UNLOCK_SITE[$turn] ?? 0;
    }

    /**
     * 槽位解锁所需转生次数（移植 unlock_level；没有该槽位为 0）
     */
    public static function unlockLevel(int $site): int
    {
        return self::UNLOCK_LEVEL[$site] ?? 0;
    }

    /**
     * 背包解锁价格（移植 get_pack_price：第 $start ~ $end 格，第 i 格 i < 10 时 5i 元宝，否则 50）
     */
    public static function packPrice(int $start, int $end): int
    {
        $cost = 0;
        for ($i = $start; $i <= $end; $i++) {
            $cost += $i < 10 ? $i * 5 : 50;
        }
        return $cost;
    }

    /**
     * 装备将魂（移植 soul_model->equip）
     *
     * 状态同旧版：1 成功，-1 将领没有将魂槽或不是自己的，2 已被其他将领装备，3 同种类型只能装备一件，4 位置未改变，
     * 5 将魂不是自己的（含不存在）。type 为操作类型：1 与本将领另一槽位互换，2 换到本将领的空槽位，3 覆盖已有将魂，4 新装配。
     * 调用方需在 Member::locked 内调用。
     *
     * @return array{status: int, type: int}
     */
    public static function equip(int $uid, int $gid, int $sid, int $site): array
    {
        $return = ['status' => 0, 'type' => 0];
        $general = self::generalSoul($gid);
        if ($general === [] || $general['uid'] !== $uid) {
            // 获取将领拥有将魂信息失败 或 将领不属于自己
            $return['status'] = -1;
            return $return;
        }

        // 将魂位置数组，修改后将魂位置数组，已装备将魂id数组
        $nowSite = $newSite = $sidArr = [];
        for ($i = 1; $i < 9; $i++) {
            $nowSite[$i] = $general['site' . $i];
            if ($general['site' . $i]) {
                $sidArr[$i] = $general['site' . $i];
            }
        }

        if ($nowSite[$site] === $sid) {
            // 位置未改变
            $return['status'] = 4;
            return $return;
        }

        // 判断装备操作类型
        $oldSite = 0;
        if (in_array($sid, $nowSite, true)) {
            $oldSite = (int)array_search($sid, $sidArr, true);
            // 1 互换槽位 / 2 换个槽位
            $type = $nowSite[$site] > 0 ? 1 : 2;
        } elseif ($nowSite[$site] > 0) {
            // 覆盖已有将魂
            $type = 3;
        } else {
            // 新装配将魂
            $type = 4;
        }

        // 查询将魂详情
        if ($type === 3 || $type === 4) {
            $sidArr[] = $sid;
        }
        $soulInfo = [];
        $ownType = [];
        foreach (self::byId(array_values($sidArr)) as $value) {
            $soulInfo[$value['id']] = $value;
            if ($value['id'] !== $sid) {
                // 已装备将魂类型
                $ownType[$value['id']] = $value['type'];
            }
            if ($type === 3) {
                // 覆盖已有将魂：被替换的将魂不算
                unset($ownType[$nowSite[$site]]);
            }
        }

        // 要装配的将魂不是自己的（旧版取不到将魂时同样走这里）
        if (($soulInfo[$sid]['uid'] ?? null) !== $uid) {
            $return['status'] = 5;
            return $return;
        }
        // 将魂已装配到其他武将身上
        if ($soulInfo[$sid]['gid'] !== 0 && $soulInfo[$sid]['gid'] !== $gid) {
            $return['status'] = 2;
            return $return;
        }
        // 类型限制：有同类型将魂
        if (in_array($soulInfo[$sid]['type'], $ownType, true)) {
            $return['status'] = 3;
            return $return;
        }

        switch ($type) {
            case 1:
                // 互换槽位
                $newSite[$oldSite] = $nowSite[$site];
                break;
            case 2:
                // 换个槽位
                $newSite[$oldSite] = 0;
                break;
            case 3:
                // 覆盖已有将魂
                self::updateSoul($uid, $nowSite[$site], ['gid' => 0]);
                self::updateSoul($uid, $sid, ['gid' => $gid]);
                break;
            case 4:
                // 新装配将魂
                self::updateSoul($uid, $sid, ['gid' => $gid]);
                break;
        }
        $newSite[$site] = $sid;
        $data = [];
        foreach ($newSite as $key => $value) {
            $data['site' . $key] = $value;
        }
        self::updateGeneral($uid, $gid, $data);

        $return['type'] = $type;
        $return['status'] = 1;
        return $return;
    }

    /**
     * 卸下将魂（移植 soul_model->unequip）
     *
     * @return int 1 成功，-1 未找到该将魂或不是自己的，-2 将领没有将魂槽，-3 将领没有装备此将魂，-4 卸下失败，-5 更新将魂失败
     */
    public static function unequip(int $uid, int $gid, int $sid): int
    {
        $own = DB::table('own_soul')->where('id', $sid)->where('gid', $gid)->whereNull('deleted_at')->first();
        if ($own === null || (int)$own['uid'] !== $uid) {
            // 未找到该将魂 或 将领不属于自己
            return -1;
        }
        $general = self::generalSoul($gid);
        if ($general === []) {
            // 获取将领拥有将魂信息失败
            return -2;
        }
        $site = null;
        for ($i = 1; $i < 9; $i++) {
            if ($general['site' . $i] === $sid) {
                $site = ['site' . $i => 0];
            }
        }
        if ($site === null) {
            // 将领没有装备此将魂
            return -3;
        }
        if (self::updateGeneral($uid, $gid, $site) !== 1) {
            // 卸下将魂失败
            return -4;
        }
        if (self::updateSoul($uid, $sid, ['gid' => 0]) !== 1) {
            // 更新将魂信息失败
            return -5;
        }
        return 1;
    }

    /**
     * 更新将魂（移植 update_soul：本人、未删除）
     *
     * @param array<string, int|string|null> $data
     * @return int 1 有改动，0 没有
     */
    public static function updateSoul(int $uid, int $id, array $data): int
    {
        $affected = DB::table('own_soul')->where('id', $id)->where('uid', $uid)->whereNull('deleted_at')->update($data);
        return $affected > 0 ? 1 : 0;
    }

    /**
     * 更新将领将魂槽（移植 update_general：按 gid 与玩家）
     *
     * @param array<string, int> $data
     * @return int 1 有改动，0 没有
     */
    public static function updateGeneral(int $uid, int $gid, array $data): int
    {
        $affected = DB::table('own_general_soul')->where('gid', $gid)->where('uid', $uid)->whereNull('deleted_at')->update($data);
        return $affected > 0 ? 1 : 0;
    }

    /**
     * 将魂合成日志（移植 add_soul_mergelog；new_sid 同旧版记新将魂的模板 id）
     */
    public static function addMergeLog(int $uid, int $sid1, int $sid2, int $newSid, int $now): int
    {
        $id = DB::table('own_soul_mergelog')->insert([
            'uid' => $uid,
            'sid1' => $sid1,
            'sid2' => $sid2,
            'new_sid' => $newSid,
            'dateline' => $now,
        ]);
        return (int)$id > 0 ? 1 : 0;
    }

    /**
     * 战斗用的将领将魂（battle_model->init_member_team_info「获得武将将魂」）
     *
     * 只看转生 > 0 的将领：取其将魂槽 site1~site8 上的将魂 id，再按 id 取将魂（未删除，不按玩家过滤），
     * 按将魂自己的 gid 记 [gid => [type => power]]（旧版 _general_soul[serverid_gid]，同类型后取到的覆盖先取到的）。
     *
     * @param list<int> $gids
     * @return array<int, array<int, int>>
     */
    public static function battleSouls(array $gids): array
    {
        if ($gids === []) {
            return [];
        }
        $soulIds = [];
        foreach (self::generalSoul($gids) as $row) {
            for ($i = 1; $i <= 8; $i++) {
                if ($row['site' . $i]) {
                    $soulIds[] = $row['site' . $i];
                }
            }
        }
        $return = [];
        foreach ($soulIds === [] ? [] : self::byId($soulIds, false) as $value) {
            $return[$value['gid']][$value['type']] = $value['power'];
        }
        return $return;
    }

    /**
     * usesoul_log 一条（移植 member_model->_soul_log；argument 旧版取未定义的 $this->action，恒为空串）
     */
    private static function soulLog(int $uid, int $type, int $soul, int $balance, string $class, string $method, ?int $now): void
    {
        $info = Member::logInfo($uid) ?? ['level' => 0, 'vip' => 0];
        DB::table('usesoul_log')->insert([
            'uid' => $uid,
            'level' => $info['level'],
            'type' => $type,
            'reputation' => $soul,
            'balance' => max(0, $balance),
            'class' => Member::logText($class, 20),
            'method' => Member::logText($method, 20),
            'argument' => '',
            'dateline' => $now ?? time(),
            'vip' => $info['vip'],
        ]);
    }

    /**
     * 库里的行按字段取出，name 为字符串，其余为整数
     *
     * @param array<string, mixed> $row
     * @param list<string> $fields
     * @return array<string, int|string>
     */
    private static function typed(array $row, array $fields): array
    {
        $item = [];
        foreach ($fields as $field) {
            $item[$field] = $field === 'name' ? (string)$row[$field] : (int)$row[$field];
        }
        return $item;
    }
}
