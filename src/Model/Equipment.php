<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use Hanjiang\Battle\LegacyStats;
use Hanjiang\MasterData\Equip;
use OmniPHP\Database\SoftDeletes;
use OmniPHP\Model;

/**
 * 装备道具数据模型 (自动过滤软删除；战斗数值见 Battle\TeamBuilder 与 Battle\LegacyStats)
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
     * powerup = power（旧版同），强化等级 0，未穿戴。
     * 依赖未移植：碎片分支（add_item $type = 1，含 itemchip_log 记录）。
     *
     * @return list<int> 新装备 id；模板不存在（旧版 -1）时返回空数组
     */
    public static function addFromTemplate(int $uid, int $equipid, int $num = 1): array
    {
        $tpl = Equip::ROWS[$equipid] ?? null;
        if ($tpl === null || $num <= 0) {
            return [];
        }

        $data = [
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

        $ids = [];
        for ($i = 0; $i < $num; $i++) {
            $ids[] = (int)static::query()->insert($data);
        }
        return $ids;
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
