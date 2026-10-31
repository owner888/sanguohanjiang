<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use Hanjiang\Battle\LegacyStats;
use Hanjiang\MasterData\Book;
use Hanjiang\MasterData\General as GeneralTemplate;
use Hanjiang\MasterData\Skill;
use Hanjiang\MasterData\Talent;
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

    /** general.levelupexp 列（MEDIUMINT UNSIGNED）的上限 */
    private const int LEVELUPEXP_MAX = 16777215;

    /**
     * 玩家获得武将的唯一入口（移植旧版 models/general_model.php:add_general）
     *
     * - 模板字段（名称、形象、职业、三围、天赋、技能、星级）从 MasterData General 复制成实列；
     * - $type = 1 为 1 级，其他为 30 级（旧版同）；levelupexp 固定为 1 级所需经验（旧版同，即使 30 级）；
     * - 名将图鉴有该将时写入 addtype / addvalue；
     * - 不出征（goout = 0），不在任何阵法上；
     * - 主公 80 级以上时重算名将谱加成（GeneralBook::refresh）。
     * - 主线任务 19（获得 X 星级武将，按模板星级）；
     * - 获得武将气泡：member_field.ongeneral = 1（主城 ongeneral 提示，进入点将台时清除）。
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

        // 添加获得武将状态，主城显示气泡通知（update_ongeneral($uid, 1)）
        DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->update(['ongeneral' => 1]);

        // 主线任务 19获得X星级武将
        MainQuestTask::action($uid, 19, (int)$tpl['star']);

        // 主公 80 级以上：重算名将谱加成（get_own_achievement_list → get_addition → achievement_addition → 写 own_addition）
        $heroLevel = (int)(DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('level') ?? 0);
        if ($heroLevel > 80) {
            GeneralBook::refresh($uid);
        }

        return $id;
    }

    /**
     * 添加武将并返回旧版 general_model->add_general 的返回值（status / type / generalid / name / image / star / 三围 /
     * skill / talent 技能与天赋行）；入库走 createFromTemplate
     *
     * @return array<string, mixed>|null 模板不存在或已拥有时为 null
     */
    public static function addGeneral(int $uid, int $gid): ?array
    {
        $tpl = GeneralTemplate::ROWS[$gid] ?? null;
        if ($tpl === null || self::createFromTemplate($uid, $gid) === null) {
            return null;
        }
        return [
            'status' => 1,
            'type' => $tpl['type'],
            'generalid' => $tpl['id'],
            'name' => $tpl['name'],
            'image' => $tpl['image'],
            'star' => $tpl['star'],
            'wuli' => $tpl['wuli'],
            'zhili' => $tpl['zhili'],
            'tili' => $tpl['tili'],
            'skill' => Skill::ROWS[$tpl['skillid']] ?? [],
            'talent' => Talent::ROWS[$tpl['talentid']] ?? [],
        ];
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

    /**
     * 升到下一级所需经验（general_model->get_levelupexp：80 × 等级²，等级 < 1 为 0）
     */
    public static function levelupExp(int $level): int
    {
        return LegacyStats::levelUpExp($level);
    }

    /**
     * 当前转数的等级上限（general_model->get_turn_rule：50 + 转数 × 5）
     */
    public static function turnRule(int $turn): int
    {
        return LegacyStats::levelCap($turn);
    }

    /**
     * 下一转的转生系数（turn_coefficient；表外的转数旧版取 0）
     */
    public static function nextTurnRatio(int $turn): float|int
    {
        return LegacyStats::TURN_COEFFICIENT[$turn + 1] ?? 0;
    }

    /**
     * 拥有将领（general_model->get_own_by_id 单个 id：uid + id，未禁用）；没有为 null
     *
     * @return array<string, mixed>|null
     */
    public static function own(int $uid, int $gid): ?array
    {
        return DB::table('general')->where('uid', $uid)->where('id', $gid)->whereNull('deleted_at')->first();
    }

    /**
     * 出征将领列表（general_model->get_battle_list(1)：goout = 1，按转数、等级降序，键从 1 开始）
     *
     * @return array<int, array<string, mixed>>
     */
    public static function battleList(int $uid): array
    {
        $result = DB::table('general')->where('uid', $uid)->where('goout', 1)->whereNull('deleted_at')->orderBy('id')->get();
        if ($result === []) {
            return [];
        }
        // array_multisort($sort_turn, SORT_DESC, $sort_level, SORT_DESC, $result)：同转同级时按整行比较（id 在前，升序）
        usort($result, static fn (array $a, array $b): int => [(int)$b['turn'], (int)$b['level'], (int)$a['id']]
            <=> [(int)$a['turn'], (int)$a['level'], (int)$b['id']]);
        $return = [];
        foreach ($result as $key => $value) {
            $return[$key + 1] = $value;
        }
        return $return;
    }

    /**
     * 武将加经验（移植 general_model->add_exp + check_levelup；含转生等级上限）
     *
     * 升级时：主公有国家则帮派谏言任务 2（武将升级，按升级数，Expostulation::action）；主线任务 3（任意武将提升到 X 级）。
     * 写 level / exp / levelupexp。旧版谏言计数取当前请求玩家的国家，这里取将领所属玩家的国家（旧版各调用处两者相同）。
     *
     * @return array{status: int, info: array<string, int>} status：-6 经验 <= 0；-5 玩家不存在；-2 将领不存在；-1 更新失败；
     *         1 成功；2 不在出征队列中；3 等级已达主公等级上限；4 需要转生
     */
    public static function addExp(int $uid, int $gid, int $addexp): array
    {
        $return = ['status' => 0, 'info' => []];
        if ($addexp <= 0) {
            $return['status'] = -6;
            return $return;
        }
        $member = DB::table('member')->select('uid', 'level', 'country')->where('uid', $uid)->whereNull('deleted_at')->first();
        if ($member === null) {
            // 获取用户信息失败
            $return['status'] = -5;
            return $return;
        }
        $heroLevel = (int)$member['level'];
        $general = self::own($uid, $gid);
        if ($general === null) {
            // 获取将领信息失败 -2
            $return['status'] = -2;
            return $return;
        }
        $level = (int)$general['level'];
        $exp = (int)$general['exp'];
        $levelupexp = self::levelupExp($level);
        $maxlevel = self::turnRule((int)$general['turn']);

        if ((int)$general['goout'] === 0) {
            // 不在出征队列中 2
            $return['status'] = 2;
            return $return;
        }
        // 等级限制(不超过主公)
        if ($level > $heroLevel) {
            $return['status'] = 3;
            return $return;
        }
        if ($level === $heroLevel && $exp === $levelupexp) {
            $return['status'] = 3;
            return $return;
        }
        if ($level === $maxlevel) {
            // 需要转生
            $return['status'] = 4;
            return $return;
        }
        // 检查升级
        $info = self::checkLevelup($exp + $addexp, $level, $maxlevel, $heroLevel);
        // levelupexp 列同旧版为 MEDIUMINT UNSIGNED：旧版库非严格模式写入超大值时截到列上限，这里照样截断
        $data = ['level' => $info['level'], 'exp' => $info['exp'], 'levelupexp' => min($info['levelupexp'], self::LEVELUPEXP_MAX)];
        $isturn = $info['status'] === 3 ? 1 : 0;
        if ($info['level'] > $level) {
            // 帮派任务(谏言：2是武将升级对应的类型)
            if ((int)$member['country'] > 0) {
                Expostulation::action((int)$member['country'], 2, $info['level'] - $level);
            }
            // 主线任务 3任意武将提升到X级
            MainQuestTask::action($uid, 3, $info['level']);
        }
        $affected = DB::table('general')->where('uid', $uid)->where('id', $gid)->whereNull('deleted_at')->update($data);
        if ($affected > 0) {
            $return['info'] = [
                'gid' => $gid,
                'exp' => $data['exp'],
                'level' => $data['level'],
                'levelupexp' => $data['levelupexp'],
                'addexp' => $addexp,
                'isturn' => $isturn,
            ];
            $return['status'] = 1;
        } else {
            $return['status'] = -1;
        }
        return $return;
    }

    /**
     * 检查是否升级（general_model->check_levelup）
     *
     * @return array{level: int, exp: int, levelupexp: int, status: int} status 1 成功；2 到达主公等级上限；3 需要转生
     */
    public static function checkLevelup(int $exp, int $level, int $maxlevel, int $heroLevel): array
    {
        $levelupexp = self::levelupExp($level);
        // 大于主公等级
        if ($level > $heroLevel) {
            $level -= 1;
            $levelupexp = self::levelupExp($level);
            return ['level' => $level, 'exp' => $levelupexp, 'levelupexp' => $levelupexp, 'status' => 2];
        }
        // 大于等于转生等级
        if ($level >= $maxlevel) {
            return ['level' => $level, 'exp' => 0, 'levelupexp' => $levelupexp, 'status' => 3];
        }
        if ($exp >= $levelupexp) {
            return self::checkLevelup($exp - $levelupexp, $level + 1, $maxlevel, $heroLevel);
        }
        return ['level' => $level, 'exp' => $exp, 'levelupexp' => self::levelupExp($level), 'status' => 1];
    }

    /**
     * 总经验（general_model->get_exp：当前经验 + 已转生各转的经验 + 当前等级之前各级的经验）
     */
    public static function totalExp(int $turn, int $level, int $exp): int
    {
        if ($turn > 0) {
            // get_turn_exp
            for ($i = 0; $i < $turn; $i++) {
                $exp += self::levelExp(self::turnRule($i));
            }
        }
        if ($level > 1) {
            $exp += self::levelExp($level);
        }
        return $exp;
    }

    /**
     * 等级总经验（general_model->get_level_exp：1 ~ 等级-1 各级所需经验之和）
     */
    public static function levelExp(int $level): int
    {
        $return = 0;
        for ($l = $level - 1; $l > 0; $l--) {
            $return += self::levelupExp($l);
        }
        return $return;
    }

    /**
     * 按总经验升级（general_model->inherit_levelup；$heroLevel 为主公等级）
     *
     * @return array{level: int, exp: int|float, levelupexp: int, turn?: int, status?: int} status 1 成功；2 到达主公等级上限；
     *         3 转生等级上限（$autoTurn 为 false 时）
     */
    public static function inheritLevelup(int $heroLevel, int $turn, int $level, int|float $exp, bool $autoTurn = true): array
    {
        $return = ['level' => 0, 'exp' => 0, 'levelupexp' => 0];
        while (true) {
            if ($heroLevel < $level) {
                $return['level'] = $heroLevel;
                $return['exp'] = self::levelupExp($heroLevel);
                $return['levelupexp'] = self::levelupExp($heroLevel);
                $return['turn'] = $turn;
                $return['status'] = 2;
                break;
            }
            // 获得转生规则
            $maxlevel = self::turnRule($turn);
            if ($maxlevel <= $level) {
                if ($autoTurn) {
                    // 需要转生
                    $turn++;
                    $level = 1;
                } else {
                    $return['status'] = 3;
                    return $return;
                }
            }
            // 当前升级所需经验
            $levelupexp = self::levelupExp($level);
            if ($exp >= $levelupexp) {
                $exp -= $levelupexp;
                $level++;
            } else {
                $return['level'] = $level;
                $return['exp'] = $exp;
                $return['levelupexp'] = $levelupexp;
                $return['turn'] = $turn;
                $return['status'] = 1;
                break;
            }
        }
        return $return;
    }
}
