<?php

declare(strict_types=1);

/**
 * 客户端缺图回退表生成器：public/static/js/core/base.js 的 xGame.imgFallback
 *
 * 用法: php tools/gen_img_fallback.php            # 打印 imgFallback 的 JS 对象（整行替换 base.js 里的值）
 *       php tools/gen_img_fallback.php --missing  # 打印既无本地美术、也找不到原型将领的 image id（区间）
 *
 * 本地只有 image 1–114、201–212 的武将美术（img/hero、img/avatar），其余旧版从已失效的 CDN
 * img2.hanjiangsanguo.com 加载。神 / 魔将领去掉前缀（神· / 神 / 魔化 / 魔）后，回退到 General 里
 * 同名、image 在本地区间内的原型将领。某个 image 的名字：有 General 行用它就只看 General，
 * 否则看其他 MasterData 表（怪物 / 关卡）里用它的名字。avatar / miniavatar / hero 用同一张表。
 */

require dirname(__DIR__) . '/bootstrap.php';

$isLocal = static fn(int $id): bool => ($id >= 1 && $id <= 114) || ($id >= 201 && $id <= 212);

/** @var array<int, array{general: list<string>, other: list<string>}> $names image => 用到它的名字 */
$names = [];
/** @var array<string, int> $base 名字 => 本地 image（只取 General） */
$base = [];
foreach (glob(dirname(__DIR__) . '/src/MasterData/*.php') as $file) {
    $table = basename($file, '.php');
    $class = 'Hanjiang\\MasterData\\' . $table;
    foreach ($class::ROWS as $row) {
        if (!is_array($row) || !isset($row['name'], $row['image']) || !is_int($row['image']) || $row['image'] <= 0) {
            continue;
        }
        $names[$row['image']][$table === 'General' ? 'general' : 'other'][] = $row['name'];
        if ($table === 'General' && $isLocal($row['image'])) {
            $base[$row['name']] ??= $row['image'];
        }
    }
}
ksort($names);

$map = [];
$missing = [];
foreach ($names as $image => $byTable) {
    if ($isLocal($image)) {
        continue;
    }
    foreach ($byTable['general'] ?? $byTable['other'] as $name) {
        $stripped = preg_replace('/^(神·|神|魔化|魔)/u', '', $name);
        if ($stripped !== $name && isset($base[$stripped])) {
            $map[$image] = $base[$stripped];
            continue 2;
        }
    }
    $missing[] = $image;
}

if (in_array('--missing', $argv, true)) {
    $ranges = [];
    foreach ($missing as $id) {
        $last = array_key_last($ranges);
        if ($last !== null && $ranges[$last][1] === $id - 1) {
            $ranges[$last][1] = $id;
        } else {
            $ranges[] = [$id, $id];
        }
    }
    echo count($missing), ' 个: ', implode('、', array_map(
        static fn(array $r): string => $r[0] === $r[1] ? (string)$r[0] : $r[0] . '–' . $r[1],
        $ranges,
    )), "\n";
    exit(0);
}

$json = json_encode($map, JSON_FORCE_OBJECT);
echo '{"avatar":', $json, ',"miniavatar":', $json, ',"hero":', $json, "}\n";
