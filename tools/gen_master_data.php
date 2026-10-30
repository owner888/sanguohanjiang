<?php

declare(strict_types=1);

/**
 * 旧版配置表 → src/MasterData/*.php 常量数组生成器
 *
 * 用法: php tools/gen_master_data.php /path/to/old_dump.sql
 *
 * 输入为旧版 MySQL 5.x 的 mysqldump（含 zc_sanguo 库）；只导出 TABLES 白名单里的配置表，
 * 玩家数据 / 日志 / 运行时状态表一律不导。每张表生成一个 final class，ROWS 按单列主键为键，
 * 复合主键或无主键时为 list。整型列转 int，float/double 转 float，decimal 保留为 string。
 */

const SOURCE_DB = 'zc_sanguo';
const TABLE_PREFIX = 'zc_sanguo_';
const NAMESPACE_NAME = 'Hanjiang\\MasterData';

/** 配置表白名单（不含前缀） */
const TABLES = [
    'act_celebration_member', 'act_celebration_reward', 'act_moon_ach_reward', 'act_moon_eat', 'act_moon_give',
    'act_newyear_buynum', 'act_newyear_multiple', 'act_newyear_reward', 'act_newyear_task', 'act_paychecks_reward',
    'act_tanabata_rankreward', 'act_tanabata_reward', 'act_treasure_hanziach', 'act_treasure_hanzireward',
    'act_treasure_rank', 'act_treasure_reward', 'act_veteran_reward', 'actenrichment_reward', 'activationcode',
    'activationcode_reward', 'activity_time', 'actkemari_exchange_lottery', 'actkemari_rank_reward', 'actkemari_reward',
    'actsevenstar_actionprice', 'actsevenstar_actionreward', 'actsevenstar_generaltemp', 'actsevenstar_rankreward',
    'advanced_info', 'advanced_skill', 'arena_exchange', 'arena_rankreward', 'arena_reward',
    'beatengod_box_reward', 'beatengod_hit_chip', 'beatengod_hit_reward', 'beatengod_rank_reward',
    'beauty_advanced', 'beauty_chip', 'beauty_dialogue', 'beauty_loot_box', 'beauty_pamper', 'beauty_skill', 'beauty_temp',
    'blackmarket', 'book', 'book_achievement', 'book_addition', 'business',
    'chingming_achievement', 'chingming_rank_reward', 'chingming_reward',
    'christmasbuy_discount', 'christmasbuy_gift', 'christmasbuy_goods', 'christmasgift_reward', 'city_addition',
    'compete_reward', 'copies_buy_times_gold', 'copies_info', 'copies_reward', 'copies_vip_buy_times',
    'country_banquet', 'country_boss_reward', 'country_city_effect', 'country_donate', 'country_job',
    'country_learn', 'country_level', 'country_mine_event', 'country_mine_ratio', 'country_mission', 'country_monster',
    'country_shop', 'country_taxes_shop', 'country_worship', 'dice_rank_reward', 'dice_reward',
    'dinner_achievement', 'dinner_menu', 'drink', 'elite_city', 'elite_mission', 'elite_monster', 'elite_price',
    'epic', 'epic_chip', 'equip', 'equip_essence', 'equip_level', 'equip_strengachieve', 'exercise', 'exercise_addition',
    'expostulation_temp', 'first_recharge', 'first_recharge_lottery', 'flop', 'flop_reward', 'game_activity',
    'gem', 'gem_levelup', 'general', 'general_culture', 'general_inherit', 'general_only_sale',
    'generaltask_mission', 'generaltask_monster', 'gvg_reward', 'halloween_candy', 'halloween_pumpkin', 'halloween_shop',
    'heaven_mission', 'heaven_monster', 'hero_talent_insight', 'hero_talent_star', 'herothrone_mission', 'herothrone_monster',
    'hit_egg_price', 'hit_egg_reward', 'invitation_reward', 'island', 'island_monster', 'jade_gamble', 'jade_price',
    'jadegamble_fb_reward', 'japan_attack', 'japan_blood', 'japan_city', 'japan_instance', 'japan_monster', 'japan_rank',
    'japan_reward', 'japan_strategy', 'kemari_price', 'lantern_material', 'lantern_profit', 'lantern_reward', 'leap_crit',
    'level_func', 'level_gift', 'levelgift_reward', 'logined_reward', 'loginreward_reward', 'loginreward_reward_index',
    'lottery', 'main_quest', 'map', 'materials_temp', 'matrix', 'matrix_level', 'mission', 'mission_newreward',
    'mission_reward', 'mission_temp', 'monster', 'mount_level', 'mount_ratio', 'mount_temp', 'new_sacredtree',
    'newcompete_reward', 'newequip_consume', 'newequip_quality', 'newequip_resolve', 'newequip_suit', 'newgift_reward',
    'newrolereward', 'newsacrifices_achievement', 'newsacrifices_rank_reward', 'newyear_boss_achievement',
    'newyear_boss_rank', 'newyear_vipreward', 'overseas_trade_shop', 'passgeneral_achievement', 'passgeneral_box_reward',
    'passgeneral_info', 'passgeneral_reward', 'pay_reward', 'price', 'pub', 'pub_on_sale', 'refine_achieve',
    'refine_master', 'refine_promote', 'role_gift', 'sacredtree', 'sacredtree_rate', 'sacrifice', 'sanctum_mission',
    'sanctum_monster', 'sanctum_reward', 'scene', 'scene_temp', 'shop', 'skill', 'smash', 'soul', 'soul_mission',
    'soul_monster', 'soul_reward', 'soul_scene', 'soul_type', 'spring_extend', 'spring_lottery', 'spring_lottery_reward',
    'spring_needgold', 'springshop', 'star_grid', 'star_refine', 'stone', 'stone_rule', 'sword_rankreward', 'sword_reward',
    'talent', 'talent_scroll', 'task', 'thirty_reward', 'totallogin_reward', 'trader', 'vip', 'vip_first_reward',
    'vip_reward', 'vip_wage', 'war_college_gold_info', 'war_college_skill', 'war_college_skill_info', 'war_shop',
    'world_boss', 'world_boss_intreward', 'world_boss_reward', 'world_exploit', 'world_shop', 'world_silver',
    'worldcup_box_reward', 'worldcup_hit_chip', 'worldcup_hit_reward', 'worldcup_rank_reward',
];

const RESERVED_CLASS_NAMES = [
    'array', 'bool', 'callable', 'enum', 'false', 'float', 'int', 'iterable', 'list', 'match', 'mixed', 'never',
    'null', 'object', 'parent', 'readonly', 'self', 'static', 'string', 'true', 'void', 'function', 'exit',
];

main($argv);

function main(array $argv): void
{
    if (!isset($argv[1]) || !is_file($argv[1])) {
        fwrite(STDERR, "用法: php tools/gen_master_data.php /path/to/old_dump.sql\n");
        exit(1);
    }

    $outDir = dirname(__DIR__) . '/src/MasterData';
    if (!is_dir($outDir) && !mkdir($outDir, 0755, true)) {
        throw new RuntimeException("无法创建目录: {$outDir}");
    }

    $tables = parseDump($argv[1]);
    $wanted = array_flip(TABLES);

    $written = 0;
    foreach (TABLES as $name) {
        if (!isset($tables[$name])) {
            throw new RuntimeException("dump 中缺少白名单表: " . TABLE_PREFIX . $name);
        }
        $class = className($name);
        $code = renderClass($class, $name, $tables[$name]);
        file_put_contents("{$outDir}/{$class}.php", $code);
        $written++;
    }

    $skipped = array_diff_key($tables, $wanted);
    fwrite(STDOUT, "生成 {$written} 个文件 → {$outDir}\n");
    fwrite(STDOUT, '未导出（不在白名单）: ' . count($skipped) . " 张表\n");
}

/**
 * @return array<string, array{comment: string, columns: array<string, array{type: string, comment: string}>, pk: list<string>, rows: list<list<int|float|string|null>>}>
 */
function parseDump(string $file): array
{
    $fh = fopen($file, 'rb');
    if ($fh === false) {
        throw new RuntimeException("无法读取: {$file}");
    }

    $db = '';
    $tables = [];
    $current = null;

    while (($line = fgets($fh)) !== false) {
        if (preg_match('/^USE `(\w+)`;/', $line, $m) === 1) {
            $db = $m[1];
            continue;
        }
        if ($db !== SOURCE_DB) {
            continue;
        }

        if (preg_match('/^CREATE TABLE `' . TABLE_PREFIX . '(\w+)` \(/', $line, $m) === 1) {
            $current = $m[1];
            $tables[$current] = ['comment' => '', 'columns' => [], 'pk' => [], 'rows' => []];
            continue;
        }

        if ($current !== null) {
            if (preg_match('/^\s+`(\w+)` (\w+)(?:\([^)]*\))?(.*?),?$/', $line, $m) === 1) {
                $comment = preg_match("/COMMENT '((?:[^'\\\\]|\\\\.|'')*)'/", $m[3], $c) === 1 ? unescape($c[1]) : '';
                $tables[$current]['columns'][$m[1]] = ['type' => strtolower($m[2]), 'comment' => $comment];
                continue;
            }
            if (preg_match('/^\s+PRIMARY KEY \((.+)\)/', $line, $m) === 1) {
                $tables[$current]['pk'] = array_map(static fn(string $c): string => trim($c, '` '), explode(',', $m[1]));
                continue;
            }
            if (str_starts_with($line, ')')) {
                if (preg_match("/COMMENT='((?:[^'\\\\]|\\\\.|'')*)'/", $line, $m) === 1) {
                    $tables[$current]['comment'] = unescape($m[1]);
                }
                $current = null;
            }
            continue;
        }

        if (preg_match('/^INSERT INTO `' . TABLE_PREFIX . '(\w+)` VALUES /', $line, $m) === 1) {
            if (!isset($tables[$m[1]])) {
                throw new RuntimeException("INSERT 出现在 CREATE TABLE 之前: {$m[1]}");
            }
            $body = substr(rtrim($line), strlen($m[0]));
            foreach (parseTuples($body) as $tuple) {
                $tables[$m[1]]['rows'][] = $tuple;
            }
        }
    }
    fclose($fh);

    return $tables;
}

/**
 * 解析 mysqldump 的 VALUES (..),(..); 段
 *
 * @return list<list<string|null>>
 */
function parseTuples(string $s): array
{
    $tuples = [];
    $len = strlen($s);
    $i = 0;
    $tuple = null;
    $buf = '';
    $isStr = false;
    $hasValue = false;

    while ($i < $len) {
        $ch = $s[$i];

        if ($tuple === null) {
            if ($ch === '(') {
                $tuple = [];
                $buf = '';
                $isStr = false;
                $hasValue = false;
            } elseif ($ch !== ',' && $ch !== ';') {
                throw new RuntimeException("VALUES 语法异常，位置 {$i}: " . substr($s, $i, 40));
            }
            $i++;
            continue;
        }

        if ($ch === "'") {
            $i++;
            $str = '';
            while ($i < $len) {
                $c = $s[$i];
                if ($c === '\\') {
                    $next = $s[$i + 1] ?? '';
                    $str .= match ($next) {
                        'n' => "\n",
                        'r' => "\r",
                        't' => "\t",
                        '0' => "\0",
                        'Z' => "\x1a",
                        'b' => "\x08",
                        default => $next,
                    };
                    $i += 2;
                    continue;
                }
                if ($c === "'") {
                    if (($s[$i + 1] ?? '') === "'") {
                        $str .= "'";
                        $i += 2;
                        continue;
                    }
                    $i++;
                    break;
                }
                $str .= $c;
                $i++;
            }
            $buf = $str;
            $isStr = true;
            $hasValue = true;
            continue;
        }

        if ($ch === ',' || $ch === ')') {
            if (!$hasValue && trim($buf) === '') {
                throw new RuntimeException("空字段，位置 {$i}");
            }
            $raw = $isStr ? $buf : trim($buf);
            $tuple[] = (!$isStr && strtoupper($raw) === 'NULL') ? null : $raw;
            $buf = '';
            $isStr = false;
            $hasValue = false;
            if ($ch === ')') {
                $tuples[] = $tuple;
                $tuple = null;
            }
            $i++;
            continue;
        }

        $buf .= $ch;
        $hasValue = true;
        $i++;
    }

    if ($tuple !== null) {
        throw new RuntimeException('VALUES 未闭合');
    }

    return $tuples;
}

function unescape(string $s): string
{
    return str_replace(["''", "\\'", '\\\\'], ["'", "'", '\\'], $s);
}

function className(string $table): string
{
    $class = str_replace(' ', '', ucwords(str_replace('_', ' ', $table)));
    if (in_array(strtolower($class), RESERVED_CLASS_NAMES, true)) {
        throw new RuntimeException("表名生成的类名是 PHP 保留字: {$table} → {$class}");
    }
    return $class;
}

function castValue(?string $raw, string $type): int|float|string|null
{
    if ($raw === null) {
        return null;
    }
    return match ($type) {
        'tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint' => (int)$raw,
        'float', 'double' => (float)$raw,
        default => $raw,
    };
}

/**
 * @param array{comment: string, columns: array<string, array{type: string, comment: string}>, pk: list<string>, rows: list<list<string|null>>} $t
 */
function renderClass(string $class, string $table, array $t): string
{
    $columns = $t['columns'];
    $names = array_keys($columns);
    $keyed = count($t['pk']) === 1;
    $pkIndex = $keyed ? array_search($t['pk'][0], $names, true) : false;
    if ($keyed && $pkIndex === false) {
        throw new RuntimeException("{$table}: 主键列 {$t['pk'][0]} 不在字段列表中");
    }

    $doc = [];
    foreach ($columns as $name => $col) {
        $doc[] = "     *   - {$name} ({$col['type']})" . ($col['comment'] !== '' ? " {$col['comment']}" : '');
    }

    $rows = [];
    $seen = [];
    foreach ($t['rows'] as $n => $tuple) {
        if (count($tuple) !== count($names)) {
            throw new RuntimeException("{$table}: 第 {$n} 行字段数 " . count($tuple) . ' ≠ ' . count($names));
        }
        $pairs = [];
        foreach ($names as $idx => $name) {
            $pairs[] = var_export($name, true) . ' => ' . exportScalar(castValue($tuple[$idx], $columns[$name]['type']));
        }
        $row = '[' . implode(', ', $pairs) . ']';
        if ($keyed) {
            $key = castValue($tuple[$pkIndex], $columns[$t['pk'][0]]['type']);
            if (isset($seen[$key])) {
                throw new RuntimeException("{$table}: 主键重复 {$key}");
            }
            $seen[$key] = true;
            $rows[] = '        ' . exportScalar($key) . ' => ' . $row . ',';
        } else {
            $rows[] = '        ' . $row . ',';
        }
    }

    $title = $t['comment'] !== '' ? $t['comment'] : $table;
    $keyDesc = $keyed ? "按主键 {$t['pk'][0]} 索引" : ($t['pk'] === [] ? '无主键，按原顺序 list' : '复合主键 (' . implode(', ', $t['pk']) . ')，按原顺序 list');
    $rowsCode = $rows === [] ? '[]' : "[\n" . implode("\n", $rows) . "\n    ]";
    $docCode = implode("\n", $doc);
    $ns = NAMESPACE_NAME;
    $full = TABLE_PREFIX . $table;
    $count = count($t['rows']);

    return <<<PHP
<?php

declare(strict_types=1);

namespace {$ns};

/**
 * {$title}（旧版 {$full}）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class {$class}
{
    public const string TABLE = '{$full}';

    /**
     * {$keyDesc}，共 {$count} 行。字段：
{$docCode}
     */
    public const array ROWS = {$rowsCode};
}

PHP;
}

function exportScalar(int|float|string|null $v): string
{
    if ($v === null) {
        return 'null';
    }
    if (is_float($v)) {
        $s = var_export($v, true);
        return str_contains($s, '.') || str_contains($s, 'E') || str_contains($s, 'N') ? $s : $s . '.0';
    }
    return var_export($v, true);
}
