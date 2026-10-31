<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use Hanjiang\Battle\LegacyStats;
use Hanjiang\MasterData\Equip;
use Hanjiang\MasterData\EquipEssence;
use Hanjiang\MasterData\TalentScroll;
use OmniPHP\Database\DB;
use OmniPHP\Database\SoftDeletes;
use OmniPHP\Model;

/**
 * 装备道具数据模型 (自动过滤软删除；战斗数值见 Battle\TeamBuilder 与 Battle\LegacyStats)
 *
 * 同旧版 own_equip，一张表存所有道具，靠 type / chip / number 区分：
 * - type 1 ~ 5、chip 0：整件装备（addFromTemplate）；
 * - type 1 ~ 5、chip > 0：装备碎片行（addChip；每种装备一行，满 LegacyStats::chipNum(品质) 片合成整件）；
 * - type 6 ~ 10：天赋卷轴（卷轴类型 + 5；number > 0、chip 0 为卷轴行，chip > 0、number 0 为卷轴碎片行；addTalentScroll）；
 * - type 100：装备精华（equipid 为 equip_essence.id + 100，quality 为精华品级 + 100，number 为数量；addTowerItem）。
 * 碎片 / 卷轴 / 精华的读改写在 Member::locked 的玩家行锁下进行（这几类没有唯一键可做 upsert），多 Worker 安全。
 */
class Equipment extends Model
{
    use SoftDeletes;

    protected string $table = 'equipment';
    protected string $primaryKey = 'id';

    protected array $casts = [
        'id' => 'int',
        'uid' => 'int',
        'equipid' => 'int',
        'type' => 'int',
        'quality' => 'int',
        'level' => 'int',
        'generalid' => 'int',
        'number' => 'int',
        'saleprice' => 'int',
        'needlevel' => 'int',
        'power' => 'int',
        'powerup' => 'int',
        'chip' => 'int',
        'sale_site' => 'int',
        'hammer' => 'int',
        'forgeid' => 'int',
        'newquality' => 'int',
        'oldequipid' => 'int',
    ];

    /**
     * 玩家获得装备的唯一入口（移植旧版 models/equipment_model.php:add_item 的整件分支，$type = 0）
     *
     * 每件一行；名称、类型、品质、装配等级、初始值、售价从 MasterData Equip 复制，
     * powerup = power（旧版同），强化等级 0，未穿戴。碎片分支（add_item $type = 1）见 addChip。
     *
     * @return list<int> 新装备 id；模板不存在（旧版 -1）时返回空数组
     */
    public static function addFromTemplate(int $uid, int $equipid, int $num = 1): array
    {
        $tpl = Equip::ROWS[$equipid] ?? null;
        if ($tpl === null || $num <= 0) {
            return [];
        }

        $data = self::templateRow($uid, $tpl);

        $ids = [];
        for ($i = 0; $i < $num; $i++) {
            $ids[] = (int)static::query()->insert($data);
        }
        return $ids;
    }

    /**
     * 获得装备碎片（移植旧版 add_item 的碎片分支，$type = 1；碎片获得记 itemchip_log）
     *
     * 已有该装备的碎片行（同模板、同类型、chip > 0）就累加：恰好满 LegacyStats::chipNum(品质) 片时碎片行本身清零
     * 变成整件；超过时另插 floor(总片数 / 满片数) 件整件，碎片行留余数（余数为 0 时碎片行同样变成整件，旧版如此，
     * 会多出一件）。没有碎片行时：$num 不足满片数插一行碎片；够满片数插 floor($num / 满片数) 件整件，余数大于 0
     * 再插一行碎片。
     *
     * @return array{status: int} status 1 成功，-1 模板不存在，0 失败
     */
    public static function addChip(int $uid, int $equipid, int $num = 1, string $class = '', string $method = '', ?int $now = null): array
    {
        $tpl = Equip::ROWS[$equipid] ?? null;
        if ($tpl === null) {
            return ['status' => -1];
        }
        if ($num <= 0) {
            return ['status' => 0];
        }
        $now ??= time();
        return Member::locked($uid, static function () use ($uid, $tpl, $num, $class, $method, $now): array {
            $return = ['status' => 0];
            $item = DB::table('equipment')->where('uid', $uid)->where('equipid', $tpl['id'])->where('type', $tpl['type'])
                ->where('chip', '>', 0)->whereNull('deleted_at')->first();
            if ($item !== null) {
                // 碎片兑换道具个数
                $fullNum = LegacyStats::chipNum((int)$item['quality']);
                $chip = (int)$item['chip'] + $num;
                $status = 0;
                if ($chip > $fullNum) {
                    // 碎片已超出，添加装备，碎片个数减去一件装备需要个数
                    $status = intdiv($chip, $fullNum);
                    $chip -= $fullNum * $status;
                } elseif ($chip === $fullNum) {
                    // 碎片刚好，直接转装备
                    $chip = 0;
                }
                $affected = DB::table('equipment')->where('id', (int)$item['id'])->where('uid', $uid)
                    ->where('equipid', $tpl['id'])->whereNull('deleted_at')->update(['chip' => $chip]);
                if ($affected > 0) {
                    $return['status'] = 1;
                }
                if ($status > 0) {
                    self::addFromTemplate($uid, (int)$tpl['id'], $status);
                }
                self::chipLog($uid, (int)$item['quality'], (int)$item['type'], $num, $chip, (int)$tpl['id'], (string)$item['name'], $class, $method, $now);
                return $return;
            }

            $data = self::templateRow($uid, $tpl);
            $fullNum = LegacyStats::chipNum((int)$tpl['quality']);
            if ($num >= $fullNum) {
                $status = intdiv($num, $fullNum);
                $balance = $num - $fullNum * $status;
                $rows = array_fill(0, $status, $data);
                if ($balance > 0) {
                    $rows[] = array_merge($data, ['chip' => $balance]);
                }
            } else {
                $balance = $num;
                $rows = [array_merge($data, ['chip' => $num])];
            }
            self::chipLog($uid, (int)$tpl['quality'], (int)$tpl['type'], $num, $balance, (int)$tpl['id'], (string)$tpl['name'], $class, $method, $now);
            if (DB::table('equipment')->insertBatch($rows) > 0) {
                $return['status'] = 1;
            }
            return $return;
        });
    }

    /**
     * 获得天赋卷轴或卷轴碎片（移植 add_talent_scroll）
     *
     * $type 为 1 时加碎片（碎片行 chip > 0、number 0；满 LegacyStats::chipNum(品质) 片合成卷轴，碎片行余数为 0 时删除，
     * 记 itemchip_log）；$type 为 0 时加卷轴（同类型同品级的卷轴叠在一行 number 上，记 talent_scroll_log）。
     * 名称为「物攻 / 物防 / 策攻 / 策防 / 生命」+「天赋卷轴」，type 为卷轴类型 + 5，售价取模板 price。
     *
     * @param int $id MasterData TalentScroll id
     * @return array<string, mixed> status 1 成功，-1 模板不存在，0 失败；加卷轴成功时 info 为卷轴行（info.id 照旧版改成卷轴类型）。
     *         碎片合成出卷轴时返回合成那一次（加卷轴）的结果
     */
    public static function addTalentScroll(int $uid, int $id, int $type = 0, int $num = 1, string $class = '', string $method = '', ?int $now = null): array
    {
        $temp = TalentScroll::ROWS[$id] ?? null;
        if ($temp === null) {
            // 获取天赋模版错误
            return ['status' => -1];
        }
        if ($num <= 0) {
            return ['status' => 0];
        }
        $now ??= time();
        return Member::locked($uid, static function () use ($uid, $id, $type, $num, $temp, $class, $method, $now): array {
            $return = ['status' => 0];
            $scrollType = (int)$temp['type'] + 5;
            $name = self::scrollName((int)$temp['type']);
            if ($type === 1) {
                // 添加卷轴碎片
                $info = self::talentScroll($uid, (int)$temp['type'], (int)$temp['quality'], 1);
                $status = 0;
                $balance = 0;
                $affected = 0;
                if ($info !== null) {
                    $fullNum = LegacyStats::chipNum((int)$info['quality']);
                    $chip = (int)$info['chip'] + $num;
                    $where = DB::table('equipment')->where('id', (int)$info['id'])->where('uid', $uid)->whereNull('deleted_at');
                    if ($chip >= $fullNum) {
                        $status = intdiv($chip, $fullNum);
                        $chip -= $status * $fullNum;
                        if ($chip > 0) {
                            $balance = $chip;
                            $affected = $where->update(['chip' => $chip]);
                        } else {
                            // 删除碎片
                            $affected = $where->update(['deleted_at' => date('Y-m-d H:i:s', $now)]);
                        }
                    } else {
                        // 旧版这里不记余数，碎片流水的剩余数量为 0
                        $affected = $where->update(['chip' => $chip]);
                    }
                } else {
                    $fullNum = LegacyStats::chipNum((int)$temp['quality']);
                    if ($num >= $fullNum) {
                        $status = intdiv($num, $fullNum);
                        $chip = $num - $status * $fullNum;
                    } else {
                        $chip = $num;
                    }
                    if ($chip > 0) {
                        $balance = $chip;
                        $affected = DB::table('equipment')->insert(self::scrollRow($uid, $id, $temp, $name, ['chip' => $chip, 'number' => 0])) ? 1 : 0;
                    }
                }
                if ($affected > 0) {
                    $return['status'] = 1;
                }
                if ($status > 0) {
                    $return = self::addTalentScroll($uid, $id, 0, $status, $class, $method, $now);
                }
                // 添加卷轴碎片获得记录
                self::chipLog($uid, (int)$temp['quality'], $scrollType, $num, $balance, $id, $name, $class, $method, $now);
                return $return;
            }

            // 添加卷轴
            $info = self::talentScroll($uid, (int)$temp['type'], (int)$temp['quality'], 0);
            if ($info !== null) {
                $data = ['number' => (int)$info['number'] + $num];
                $affected = DB::table('equipment')->where('id', (int)$info['id'])->where('uid', $uid)->whereNull('deleted_at')->update($data);
            } else {
                $data = self::scrollRow($uid, $id, $temp, $name, ['chip' => 0, 'number' => $num]);
                $affected = DB::table('equipment')->insert($data) ? 1 : 0;
            }
            if ($affected > 0) {
                $return['status'] = 1;
                $return['info'] = $info ?? $data;
                $return['info']['id'] = (int)$temp['type'];
            }
            $memberInfo = Member::logInfo($uid) ?? ['level' => 0, 'vip' => 0];
            self::scrollLog($uid, $memberInfo, 1, (int)$temp['type'], (int)$temp['quality'], $num, (int)$data['number'], $class, $method, $now);
            return $return;
        });
    }

    /**
     * 玩家拥有的天赋卷轴行（移植 get_talent_scroll；$chip 为 1 取碎片行，0 取卷轴行）；没有返回 null
     *
     * @param int $type 卷轴类型 1 ~ 5（表里存 type + 5）
     * @return array<string, mixed>|null
     */
    public static function talentScroll(int $uid, int $type, int $quality, int $chip = 0): ?array
    {
        $query = DB::table('equipment')->where('uid', $uid)->whereNull('deleted_at')
            ->where('type', $type + 5)->where('quality', $quality);
        if ($chip === 1) {
            $query->where('chip', '>', 0)->where('number', 0);
        } else {
            $query->where('chip', 0)->where('number', '>', 0);
        }
        return $query->first();
    }

    /**
     * 消耗天赋卷轴（移植 sub_talent_scroll；扣完的卷轴行软删除，记 talent_scroll_log）
     *
     * 扣除用条件 UPDATE（number >= 数量），多 Worker 不会超扣。
     *
     * @param int $type 卷轴类型 1 ~ 5
     * @return array{status: int} status 1 成功，-1 没有卷轴，-2 卷轴不够
     */
    public static function subTalentScroll(int $uid, int $type, int $quality, int $num = 1, string $class = '', string $method = '', ?int $now = null): array
    {
        $now ??= time();
        $result = self::talentScroll($uid, $type, $quality, 0);
        if ($result === null) {
            // 没有卷轴
            return ['status' => -1];
        }
        if ((int)$result['number'] < $num) {
            // 卷轴不够
            return ['status' => -2];
        }
        $affected = DB::execute(
            'UPDATE `zc_sanguo_equipment` SET `number` = LAST_INSERT_ID(`number` - :num)
             WHERE `id` = :id AND `uid` = :uid AND `deleted_at` IS NULL AND `number` >= :need',
            ['num' => $num, 'id' => (int)$result['id'], 'uid' => $uid, 'need' => $num]
        );
        if ($affected === 0) {
            // 并发下已被别的请求扣掉
            return $num > 0 ? ['status' => -2] : ['status' => 1];
        }
        $balance = (int)DB::query('SELECT LAST_INSERT_ID() AS `v`')[0]['v'];
        if ($balance === 0) {
            // 扣完直接删除
            DB::table('equipment')->where('id', (int)$result['id'])->where('uid', $uid)->where('number', 0)->whereNull('deleted_at')
                ->update(['deleted_at' => date('Y-m-d H:i:s', $now)]);
        }
        $memberInfo = Member::logInfo($uid) ?? ['level' => 0, 'vip' => 0];
        self::scrollLog($uid, $memberInfo, 2, $type, (int)$result['quality'], $num, $balance, $class, $method, $now);
        return ['status' => 1];
    }

    /**
     * 天赋卷轴名字（移植 get_scroll_name，语言包 equipment_lang）
     */
    public static function scrollName(int $type): string
    {
        return match ($type) {
            1 => '物攻',
            2 => '物防',
            3 => '策攻',
            4 => '策防',
            5 => '生命',
            default => '',
        } . '天赋卷轴';
    }

    /**
     * 增加装备精华（移植 add_tower_item：每种精华一行，type 100，equipid 为精华 id + 100，number 累加 $value）
     *
     * @param int $id MasterData EquipEssence id
     * @return int 1 成功，0 失败（精华模板不存在或数量 <= 0）
     */
    public static function addTowerItem(int $uid, int $id, int $value): int
    {
        $info = EquipEssence::ROWS[$id] ?? null;
        if ($info === null || $value <= 0) {
            return 0;
        }
        return Member::locked($uid, static function () use ($uid, $id, $value, $info): int {
            $item = self::towerItem($uid, $id);
            if ($item === []) {
                // 添加道具
                return DB::table('equipment')->insert([
                    'uid' => $uid,
                    'equipid' => $id + 100,
                    'name' => $info['name'],
                    'type' => 100,
                    'quality' => (int)$info['quality'] + 100,
                    'saleprice' => $info['price'],
                    'chip' => 0,
                    'number' => $value,
                ]) ? 1 : 0;
            }
            return DB::execute(
                'UPDATE `zc_sanguo_equipment` SET `number` = `number` + :value
                 WHERE `id` = :id AND `uid` = :uid AND `type` = 100 AND `deleted_at` IS NULL',
                ['value' => $value, 'id' => (int)$item['id'], 'uid' => $uid]
            ) > 0 ? 1 : 0;
        });
    }

    /**
     * 玩家的装备精华（移植 get_tower_item）
     *
     * $type 为 1 时 $id 是单个精华 id，返回该行（没有为空数组）；否则 $id 是精华 id 数组，返回行列表。
     *
     * @param int|list<int> $id MasterData EquipEssence id
     * @return array<int|string, mixed>
     */
    public static function towerItem(int $uid, int|array $id, int $type = 1): array
    {
        $query = DB::table('equipment')->where('uid', $uid)->where('type', 100)->whereNull('deleted_at');
        if ($type === 1) {
            return $query->where('equipid', (is_array($id) ? (int)($id[0] ?? 0) : $id) + 100)->first() ?? [];
        }
        $ids = array_map(static fn (int $v): int => $v + 100, is_array($id) ? $id : [$id]);
        return $ids === [] ? [] : $query->whereIn('equipid', $ids)->get();
    }

    /**
     * 设置装备精华数量（移植 update_tower_item：直接写 number，数量为 0 时行保留）
     *
     * @return int 1 成功，0 没有该精华或数量没变
     */
    public static function updateTowerItem(int $uid, int $id, int $number): int
    {
        return DB::table('equipment')->where('uid', $uid)->where('equipid', $id + 100)->where('type', 100)->whereNull('deleted_at')
            ->update(['number' => max(0, $number)]) > 0 ? 1 : 0;
    }

    /**
     * 扣除装备精华（旧版调用方先 get_tower_item 再 update_tower_item(数量 - 消耗)；这里用条件 UPDATE 一步扣，
     * 多 Worker 不会超扣。扣完行保留，同 update_tower_item）
     *
     * @return int 1 成功，0 精华不足或没有
     */
    public static function subTowerItem(int $uid, int $id, int $num): int
    {
        if ($num <= 0) {
            return self::towerItem($uid, $id) === [] ? 0 : 1;
        }
        return DB::execute(
            'UPDATE `zc_sanguo_equipment` SET `number` = `number` - :num
             WHERE `uid` = :uid AND `equipid` = :equipid AND `type` = 100 AND `deleted_at` IS NULL AND `number` >= :need',
            ['num' => $num, 'uid' => $uid, 'equipid' => $id + 100, 'need' => $num]
        ) > 0 ? 1 : 0;
    }

    /**
     * 整件装备 / 装备碎片行的公共字段（add_item 的 $data：模板复制，powerup = power）
     *
     * @param array<string, mixed> $tpl MasterData Equip 行
     * @return array<string, mixed>
     */
    private static function templateRow(int $uid, array $tpl): array
    {
        return [
            'uid' => $uid,
            'equipid' => $tpl['id'],
            'name' => $tpl['name'],
            'type' => $tpl['type'],
            'quality' => $tpl['quality'],
            'needlevel' => $tpl['needlevel'],
            'power' => $tpl['power'],
            'powerup' => $tpl['power'],
            'saleprice' => $tpl['saleprice'],
            'chip' => 0,
        ];
    }

    /**
     * 天赋卷轴 / 卷轴碎片行（add_talent_scroll 插入的字段）
     *
     * @param array<string, mixed> $temp MasterData TalentScroll 行
     * @param array{chip: int, number: int} $count
     * @return array<string, mixed>
     */
    private static function scrollRow(int $uid, int $id, array $temp, string $name, array $count): array
    {
        return [
            'uid' => $uid,
            'equipid' => $id,
            'name' => $name,
            'type' => (int)$temp['type'] + 5,
            'quality' => $temp['quality'],
            'saleprice' => $temp['price'],
            'chip' => $count['chip'],
            'number' => $count['number'],
        ];
    }

    /**
     * itemchip_log 一条（移植 _add_itemchip_log；type 恒为 1 获得，argument 恒为空串）
     */
    private static function chipLog(int $uid, int $quality, int $itemType, int $num, int $balance, int $itemId, string $itemName, string $class, string $method, int $now): void
    {
        DB::table('itemchip_log')->insert([
            'uid' => $uid,
            'type' => 1,
            'itemtype' => $itemType,
            'quality' => $quality,
            'num' => $num,
            'blance' => $balance,
            'itemid' => $itemId,
            'itemname' => $itemName,
            'class' => Member::logText($class, 50),
            'method' => Member::logText($method, 50),
            'argument' => '',
            'dateline' => $now,
        ]);
    }

    /**
     * talent_scroll_log 一条（移植 _scroll_log；旧版加卷轴只在发给当前玩家时记，这里都记）
     *
     * @param array{level: int, vip: int} $memberInfo
     */
    private static function scrollLog(int $uid, array $memberInfo, int $type, int $scrollType, int $quality, int $scroll, int $balance, string $class, string $method, int $now): void
    {
        DB::table('talent_scroll_log')->insert([
            'uid' => $uid,
            'level' => $memberInfo['level'],
            'type' => $type,
            'scrolltype' => $scrollType,
            'quality' => $quality,
            'scroll' => $scroll,
            'balance' => $balance,
            'vip' => $memberInfo['vip'],
            'class' => Member::logText($class, 20),
            'method' => Member::logText($method, 20),
            'argument' => '',
            'dateline' => $now,
        ]);
    }

    /**
     * 装备对应的武将装配位置 (1武器, 2铠甲, 3披风, 4兵符)；类型 > 5 不可装配返回 null
     * 旧版 get_item_site：猛将武器(1)、谋士武器(2) 同在 1 号位
     */
    public function getSite(): ?int
    {
        return LegacyStats::itemSite((int)($this->attributes['type'] ?? 0));
    }

    /**
     * 与 $type 同一装配位置的全部装备类型（武器位含 1、2）
     *
     * @return list<int>
     */
    public static function typesSharingSite(int $type): array
    {
        $site = LegacyStats::itemSite($type);
        if ($site === null) {
            return [$type];
        }
        return array_values(array_filter(
            range(1, 5),
            static fn (int $t): bool => LegacyStats::itemSite($t) === $site,
        ));
    }

    /**
     * 是否已穿戴在某个武将身上
     */
    public function isEquipped(): bool
    {
        return ((int)($this->attributes['generalid'] ?? 0)) > 0;
    }
}
