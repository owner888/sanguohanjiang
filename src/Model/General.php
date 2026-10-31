<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use Hanjiang\Battle\LegacyStats;
use Hanjiang\MasterData\Book;
use Hanjiang\MasterData\General as GeneralTemplate;
use OmniPHP\Database\DB;
use OmniPHP\Database\SoftDeletes;
use OmniPHP\Model;

/**
 * 武将英雄数据模型 (自动过滤软删除；战斗数值见 Battle\TeamBuilder 与 Battle\LegacyStats)
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
     * 玩家获得武将的唯一入口（移植旧版 models/general_model.php:add_general）
     *
     * - 模板字段（名称、形象、职业、三围、天赋、技能、星级）从 MasterData General 复制成实列；
     * - $type = 1 为 1 级，其他为 30 级（旧版同）；levelupexp 固定为 1 级所需经验（旧版同，即使 30 级）；
     * - 名将图鉴有该将时写入 addtype / addvalue；
     * - 不出征（goout = 0），不在任何阵法上；
     * - 主公 80 级以上时重算名将谱加成（GeneralBook::refresh）。
     * 依赖未移植：获得武将气泡（member_field.ongeneral）、主线任务 19。
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

        $id = (int)static::query()->insert($data);

        // 主公 80 级以上：重算名将谱加成（get_own_achievement_list → get_addition → achievement_addition → 写 own_addition）
        $heroLevel = (int)(DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('level') ?? 0);
        if ($heroLevel > 80) {
            GeneralBook::refresh($uid);
        }

        return $id;
    }

    /**
     * 新账号初始武将（移植旧版 models/general_model.php:init_general）
     *
     * 与 add_general 的差别照旧版保留：levelupexp = 0、goout = 1、不查图鉴、不查重。
     * 阵法由调用方按旧版 init_matrix 写入（OwnMatrix::init：1 号阵法 2 号位）。
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
}
