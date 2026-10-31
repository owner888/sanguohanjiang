<?php

declare(strict_types=1);

/**
 * 重新扫描旧版接口缺口，刷新 docs/LEGACY_GAP.md 开头的统计与「缺失接口」一节
 *
 * 用法: php tools/scan_legacy_gap.php /path/to/HJServer/wwwroot
 *
 * 对比三方：
 *   1. 前端 public/static/js 中写死的 c/m 调用（单行 / 多行对象、?c=…&m=… URL、x.c = …; x.m = … 赋值）
 *   2. 新版 src/Handler/{领域}/{Xxx}Handler.php 的 public static 方法（按 HandlerRegistry 的小写 / 蛇形别名匹配）
 *   3. 旧版 {s1,uc,battle,war}/application/controllers/ 下全部控制器（含子目录）的 public 方法
 *
 * 只替换文档开头到「## 已实现但为写死 / 空壳的接口」之前的部分，其余手工维护的章节原样保留。
 */

require dirname(__DIR__) . '/bootstrap.php';

const LEGACY_APPS = ['s1', 'uc', 'battle', 'war'];
const KEEP_FROM = '## 已实现但为写死 / 空壳的接口';

main($argv);

function main(array $argv): void
{
    $legacyRoot = rtrim($argv[1] ?? '', '/');
    if ($legacyRoot === '' || !is_dir($legacyRoot . '/s1/application/controllers')) {
        fwrite(STDERR, "用法: php tools/scan_legacy_gap.php /path/to/HJServer/wwwroot\n");
        exit(1);
    }
    $root = dirname(__DIR__);
    $docPath = $root . '/docs/LEGACY_GAP.md';

    $calls = scanClientCalls($root . '/public');
    $legacy = scanLegacyMethods($legacyRoot);
    [$implemented, $registered] = scanHandlers($root . '/src/Handler');

    $missing = array_values(array_filter(array_keys($calls), static fn(string $k): bool => !isset($implemented[$k])));
    sort($missing);

    $doc = (string)file_get_contents($docPath);
    $keepAt = strpos($doc, KEEP_FROM);
    if ($keepAt === false) {
        throw new RuntimeException("docs/LEGACY_GAP.md 中找不到「" . KEEP_FROM . "」，无法定位手工维护部分");
    }

    file_put_contents($docPath, render($calls, $legacy, $missing, $registered) . "\n" . substr($doc, $keepAt));

    $legacyHas = count(array_filter($missing, static fn(string $k): bool => isset($legacy[$k])));
    fwrite(STDOUT, sprintf(
        "前端调用 %d；已实现 %d；缺失 %d（旧版有 %d，旧版也无 %d）→ %s\n",
        count($calls), count($calls) - count($missing), count($missing), $legacyHas, count($missing) - $legacyHas, $docPath
    ));
}

/**
 * preg_match_all 失败（回溯上限等）时返回 false 而不报错，会悄悄漏掉匹配——这里直接抛出
 *
 * @param array<int, mixed> $matches
 */
function matchAll(string $pattern, string $subject, ?array &$matches, string $where): void
{
    if (preg_match_all($pattern, $subject, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) === false) {
        throw new RuntimeException("正则匹配失败（{$where}）：" . preg_last_error_msg());
    }
}

/**
 * @return array<string, list<string>> "c.m" => ["static/js/x.js:行号", …]
 */
function scanClientCalls(string $publicDir): array
{
    $patterns = [
        // 'c':'x', 'm':'y' / c: 'x',\n m: 'y'（键可带或不带引号，中间可有换行与行注释）
        '/[\'"]?\bc[\'"]?\s*:\s*[\'"](\w+)[\'"]\s*,(?:\s|\/\/[^\n]*)*[\'"]?\bm[\'"]?\s*:\s*[\'"]\s*(\w+)\s*[\'"]/',
        // ?c=x&m=y
        '/[?&]c=(\w+)&m=(\w+)/',
    ];
    // _tmp.c = 'x'; _tmp.m = 'y';
    $assign = '/(\w+)\.c\s*=\s*[\'"](\w+)[\'"];(?:\s|\/\/[^\n]*)*\1\.m\s*=\s*[\'"](\w+)[\'"]/';

    $calls = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($publicDir . '/static/js', FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        $path = $file->getPathname();
        if (!str_ends_with($path, '.js') || str_contains($path, 'quark') || str_contains($path, 'zepto') || str_contains($path, '.min.')) {
            continue;
        }
        $src = (string)file_get_contents($path);
        $rel = substr($path, strlen($publicDir) + 1);
        $add = static function (string $c, string $m, int $offset) use (&$calls, $src, $rel): void {
            $calls[strtolower($c) . '.' . strtolower($m)][$rel . ':' . (substr_count($src, "\n", 0, $offset) + 1)] = true;
        };
        foreach ($patterns as $p) {
            matchAll($p, $src, $ms, $rel);
            foreach ($ms as $m) {
                $add($m[1][0], $m[2][0], $m[0][1]);
            }
        }
        matchAll($assign, $src, $ms, $rel);
        foreach ($ms as $m) {
            $add($m[2][0], $m[3][0], $m[0][1]);
        }
    }

    $out = [];
    foreach ($calls as $k => $locs) {
        $list = array_keys($locs);
        sort($list);
        $out[$k] = $list;
    }
    return $out;
}

/**
 * @return array<string, string> "c.m" => "s1/application/controllers/x.php:行号"（同名取 LEGACY_APPS 顺序的第一个）
 */
function scanLegacyMethods(string $legacyRoot): array
{
    $legacy = [];
    foreach (LEGACY_APPS as $app) {
        $dir = "{$legacyRoot}/{$app}/application/controllers";
        if (!is_dir($dir)) {
            continue;
        }
        $files = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (str_ends_with($file->getPathname(), '.php')) {
                $files[] = $file->getPathname();
            }
        }
        sort($files);
        foreach ($files as $path) {
            $c = strtolower(basename($path, '.php'));
            $src = (string)file_get_contents($path);
            $rel = substr($path, strlen($legacyRoot) + 1);
            matchAll('/\n(\s*)(public\s+|private\s+|protected\s+)?function\s+(\w+)\s*\(/', $src, $ms, $rel);
            foreach ($ms as $m) {
                $name = $m[3][0];
                $vis = trim($m[2][0] ?? '');
                if (str_starts_with($name, '_') || strtolower($name) === $c || $vis === 'private' || $vis === 'protected') {
                    continue;
                }
                $key = $c . '.' . strtolower($name);
                $legacy[$key] ??= $rel . ':' . (substr_count($src, "\n", 0, $m[0][1]) + 2);
            }
        }
    }
    return $legacy;
}

/**
 * @return array{0: array<string, true>, 1: array<string, true>} [已实现 "c.m", 已注册的控制器别名]
 */
function scanHandlers(string $handlerDir): array
{
    $implemented = [];
    $registered = [];
    foreach (glob($handlerDir . '/*/*Handler.php') ?: [] as $path) {
        $raw = basename($path, 'Handler.php');
        $aliases = array_unique([strtolower($raw), strtolower((string)preg_replace('/(?<!^)[A-Z]/', '_$0', $raw))]);
        matchAll('/public\s+static\s+function\s+(\w+)\s*\(/', (string)file_get_contents($path), $ms, $path);
        foreach ($aliases as $a) {
            $registered[$a] = true;
            foreach (array_column(array_column($ms, 1), 0) as $method) {
                $implemented[$a . '.' . strtolower($method)] = true;
            }
        }
    }
    return [$implemented, $registered];
}

/**
 * @param array<string, list<string>> $calls
 * @param array<string, string> $legacy
 * @param list<string> $missing
 * @param array<string, true> $registered
 */
function render(array $calls, array $legacy, array $missing, array $registered): string
{
    $byController = [];
    foreach ($missing as $k) {
        $byController[explode('.', $k)[0]][] = $k;
    }
    uksort($byController, static fn(string $a, string $b): int => [count($byController[$b]), $a] <=> [count($byController[$a]), $b]);
    $legacyHas = count(array_filter($missing, static fn(string $k): bool => isset($legacy[$k])));

    $lines = [
        '# 旧版接口缺口清单',
        '',
        '对比基线：前端 `public/static/js` 中写死的 `c`/`m` 调用（含单行对象、多行对象、`?c=…&m=…` URL、`x.c = …; x.m = …` 赋值四种写法）vs 新版 `src/Handler` 的 `public static` 方法 vs 旧版 CodeIgniter `wwwroot/{s1,uc,battle,war}/application/controllers/` 下全部控制器（含 `newequip/`、`lc/` 等子目录）。完全动态拼接的调用未覆盖。',
        '',
        sprintf('- 前端调用：%d；已实现：%d；缺失：%d（旧版有 %d，旧版也无 %d）', count($calls), count($calls) - count($missing), count($missing), $legacyHas, count($missing) - $legacyHas),
        '- 「旧版位置」为相对旧服务端 `HJServer/wwwroot/` 的路径；「—」表示四个旧服务里都没有对应方法（多为渠道 SDK 登录），需按前端字段反推。',
        '- 标记 🆕 的控制器在新版中整个 Handler 都不存在。',
        '- 本节由 `tools/scan_legacy_gap.php` 生成，勿手改；其余章节手工维护。',
        '',
        '## 缺失接口（按控制器，缺口多的在前）',
        '',
    ];
    foreach ($byController as $c => $keys) {
        $lines[] = '### ' . $c . (isset($registered[$c]) ? '' : ' 🆕') . '（' . count($keys) . '）';
        $lines[] = '';
        $lines[] = '| 接口 | 旧版位置 | 前端调用 |';
        $lines[] = '|---|---|---|';
        foreach ($keys as $k) {
            [$cc, $mm] = explode('.', $k);
            $fe = $calls[$k];
            $feText = implode('<br>', array_map(static fn(string $x): string => "`{$x}`", array_slice($fe, 0, 3)))
                . (count($fe) > 3 ? '<br>…共 ' . count($fe) . ' 处' : '');
            $lines[] = "| `c={$cc}&m={$mm}` | " . (isset($legacy[$k]) ? '`' . $legacy[$k] . '`' : '—') . " | {$feText} |";
        }
        $lines[] = '';
    }
    return implode("\n", $lines);
}
