<?php

declare(strict_types=1);

/**
 * 多人副本端到端冒烟用的服务启动入口（只在测试时手动运行，生产用 server.php）：
 * 把实时组队的开放时间判断固定在开放时段内，再照常启动 server.php。
 *
 *   php tests/e2e/elite_smoke_server.php start
 *   node tests/e2e/elite_smoke.mjs
 *   php tests/e2e/elite_smoke_server.php stop
 */

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

\Hanjiang\WebSocket\EliteRoom::useClock(static fn (): int => (int)strtotime(date('Y-m-d') . ' 12:30:00'));

require dirname(__DIR__, 2) . '/server.php';
