<?php

declare(strict_types=1);

/**
 * 世界 / 国家 BOSS 并发测试用的独立进程（GameApiTest 同时起多个，模拟多个 Workerman 进程同时写同一只 BOSS）
 *
 * 用法：php boss_worker.php hit <country> <uid> <hit> <times>   国家 BOSS 与世界 BOSS（20:00 场）各记 times 次伤害
 *       php boss_worker.php claim <uid>                          世界 BOSS（20:00 场）领奖删记录，输出 1 / 0
 */

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use Hanjiang\Model\CountryBoss;
use Hanjiang\Model\WorldBoss;
use OmniPHP\Cache\Redis;

date_default_timezone_set('Asia/Shanghai');
$dbConfig = require dirname(__DIR__, 2) . '/config/database.php';
Redis::init($dbConfig['redis']);

$mode = $argv[1] ?? '';
if ($mode === 'hit') {
    [$country, $uid, $hit, $times] = array_map('intval', array_slice($argv, 2, 4));
    for ($i = 0; $i < $times; $i++) {
        CountryBoss::updateMyHit($country, $uid, $hit);
        WorldBoss::updateMyHit(1, $uid, $hit, 600);
    }
    echo 'done';
} elseif ($mode === 'claim') {
    echo WorldBoss::delMyHit(1, (int)($argv[2] ?? 0)) ? '1' : '0';
}
