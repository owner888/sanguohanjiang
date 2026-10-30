<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use Hanjiang\Battle\LegacyStats;
use Hanjiang\MasterData\Book;
use Hanjiang\MasterData\General as GeneralTemplate;
use OmniPHP\Database\SoftDeletes;
use OmniPHP\Model;

/**
 * 武将英雄数据模型 (自动过滤软删除，内聚升级经验算法与全维属性推算)
 */
class General extends Model
{
    use SoftDeletes;

    protected string $table = 'general';
    protected string $primaryKey = 'id';

    protected array $casts = [
        'id' => 'int',
        'uid' => 'int',
        'generalid' => 'int',
        'level' => 'int',
        'exp' => 'int',
        'pos' => 'int',
        'matrix_id' => 'int',
        'status' => 'int',
        'wuliup' => 'int',
        'zhiliup' => 'int',
        'tiliup' => 'int',
        'quality' => 'int',
        'levelupexp' => 'int',
        'image' => 'int',
        'type' => 'int',
        'wuli' => 'int',
        'zhili' => 'int',
        'tili' => 'int',
        'talentid' => 'int',
        'skillid' => 'int',
        'star' => 'int',
        'turn' => 'int',
        'addtype' => 'int',
        'addvalue' => 'int',
        'mid0' => 'int',
        'mid1' => 'int',
        'mid2' => 'int',
        'mid3' => 'int',
        'mid4' => 'int',
        'goout' => 'int',
    ];

    /**
     * 新账号初始武将模板（旧版 controllers/login.php：init_general(1, uid)）
     */
    public const int INITIAL_GENERALID = 1;

    /**
     * 初始武将所在阵法与站位（旧版 controllers/login.php：init_matrix(1, uid, 2, gid)）
     */
    public const int INITIAL_MATRIX_ID = 1;
    public const int INITIAL_POS = 2;

    /**
     * 玩家获得武将的唯一入口（移植旧版 models/general_model.php:add_general）
     *
     * - 模板字段（名称、形象、职业、三围、天赋、技能、星级）从 MasterData General 复制成实列；
     * - $type = 1 为 1 级，其他为 30 级（旧版同）；levelupexp 固定为 1 级所需经验（旧版同，即使 30 级）；
     * - 名将图鉴有该将时写入 addtype / addvalue；
     * - 不出征、不上阵（goout = 0、pos = 0）。
     * 依赖未移植：获得武将气泡（member_field.ongeneral）、主公 80 级以上的名将成就加成重算、主线任务 19。
     *
     * @return int|null 新武将 id；模板不存在（旧版 -1）或已拥有同模板武将（旧版 -3）时返回 null
     */
    public static function createFromTemplate(int $uid, int $generalid, int $type = 1): ?int
    {
        $tpl = GeneralTemplate::ROWS[$generalid] ?? null;
        if ($tpl === null) {
            return null;
        }

        $owned = static::query()
            ->where('uid', $uid)
            ->where('generalid', $generalid)
            ->whereNull('deleted_at')
            ->first();
        if ($owned !== null) {
            return null;
        }

        $data = self::templateColumns($uid, $tpl) + [
            'level' => $type !== 1 ? 30 : 1,
            'exp' => 0,
            'levelupexp' => LegacyStats::levelUpExp(1),
        ];

        $book = Book::ROWS[$generalid] ?? null;
        if ($book !== null) {
            $data['addtype'] = $book['addtype'];
            $data['addvalue'] = $book['addvalue'];
        }

        return (int)static::query()->insert($data);
    }

    /**
     * 新账号初始武将（移植旧版 models/general_model.php:init_general）
     *
     * 与 add_general 的差别照旧版保留：levelupexp = 0、goout = 1、不查图鉴、不查重。
     * 站位按旧版 init_matrix 放在 1 号阵法 2 号位；阵法表 position2 由调用方写入。
     *
     * @return int 新武将 id
     */
    public static function initGeneral(int $uid): int
    {
        $tpl = GeneralTemplate::ROWS[self::INITIAL_GENERALID];

        return (int)static::query()->insert(self::templateColumns($uid, $tpl) + [
            'level' => 1,
            'exp' => 0,
            'levelupexp' => 0,
            'goout' => 1,
            'pos' => self::INITIAL_POS,
            'matrix_id' => self::INITIAL_MATRIX_ID,
        ]);
    }

    /**
     * @param array<string, mixed> $tpl MasterData General 行
     * @return array<string, mixed>
     */
    private static function templateColumns(int $uid, array $tpl): array
    {
        return [
            'uid' => $uid,
            'generalid' => $tpl['id'],
            'name' => $tpl['name'],
            'image' => $tpl['image'],
            'type' => $tpl['type'],
            'wuli' => $tpl['wuli'],
            'zhili' => $tpl['zhili'],
            'tili' => $tpl['tili'],
            'talentid' => $tpl['talentid'],
            'skillid' => $tpl['skillid'],
            'star' => $tpl['star'],
        ];
    }

    /**
     * 原版经典武将升级经验公式 (80 * 等级^2)
     */
    public static function getLevelUpExp(int $level): int
    {
        return max(80, 80 * $level * $level);
    }

    /**
     * 计算武将的综合战斗属性 (基础成长 + 穿戴装备 + 将魂命格 + 洗髓培养)
     *
     * @param list<array<string, mixed>> $equippedItems
     * @return array<string, mixed>
     */
    public function calculateStats(array $equippedItems = []): array
    {
        $level = max(1, (int)($this->attributes['level'] ?? 1));
        $wuli = (int)($this->attributes['wuli'] ?? 85);
        $zhili = (int)($this->attributes['zhili'] ?? 70);
        $tili = (int)($this->attributes['tili'] ?? 90);

        // 基础血量与攻防：体力决定气血厚度，武力/智力决定物理/策术攻击力，智力+体力决定防御
        $baseHp = 800 + $tili * 10 + ($level - 1) * 120;
        $baseAtk = 160 + max($wuli, $zhili) * 2 + ($level - 1) * 25;
        $baseDef = 60 + (int)(($zhili + $tili) / 2) + ($level - 1) * 12;
        $baseSpeed = 100 + (int)($wuli * 0.5) + ($level - 1) * 5;

        $addAtk = 0;
        $addDef = 0;
        $addHp = 0;

        // 1. 穿戴装备加成
        foreach ($equippedItems as $item) {
            $eqModel = Equipment::fromArray($item);
            $bonus = $eqModel->calculateBonus();
            $addAtk += $bonus['attack'];
            $addDef += $bonus['defense'];
            $addHp += $bonus['hp'];
        }

        // 2. 将魂命格加成
        $uid = (int)($this->attributes['uid'] ?? 0);
        $gid = (int)($this->attributes['id'] ?? 0);
        if ($uid > 0 && $gid > 0) {
            $soul = GeneralSoul::query()->where('uid', $uid)->where('gid', $gid)->first();
            if ($soul !== null) {
                $soulModel = GeneralSoul::fromArray($soul);
                $soulBonus = $soulModel->calculateBonus();
                $addHp += $soulBonus['hp'];
                $addAtk += $soulBonus['attack'];
                $addDef += $soulBonus['defense'];
            }
        }

        // 3. 武将洗髓培养永久加成 (体力加生命，武力加攻击，智力加防御)
        $wuliUp = (int)($this->attributes['wuliup'] ?? 0);
        $zhiliUp = (int)($this->attributes['zhiliup'] ?? 0);
        $tiliUp = (int)($this->attributes['tiliup'] ?? 0);
        $addAtk += $wuliUp * 2;
        $addDef += $zhiliUp * 2;
        $addHp += $tiliUp * 10;

        $totalHp = $baseHp + $addHp;
        $totalAtk = $baseAtk + $addAtk;
        $totalDef = $baseDef + $addDef;

        return [
            'level' => $level,
            'hp' => $totalHp,
            'allblood' => $totalHp,
            'attack' => $totalAtk,
            'defense' => $totalDef,
            'speed' => $baseSpeed,
            'base_hp' => $baseHp,
            'base_attack' => $baseAtk,
            'base_defense' => $baseDef,
            'add_hp' => $addHp,
            'add_attack' => $addAtk,
            'add_defense' => $addDef,
        ];
    }
}
