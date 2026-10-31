<?php

declare(strict_types=1);

/**
 * 物品存储并发测试用的独立进程（ItemStorageTest 同时起多个，模拟多个 Workerman 进程同时给同一玩家加减物品）
 *
 * 用法：php storage_worker.php <op> <uid> <times>
 *   op：jade_add / jade_sub（玉石各 1 个）、trader_add（紫宝石 1 个）、chip_add（装备 3 碎片 1 片）、
 *       mount_chip_add（坐骑 1 碎片 1 片）、scroll_chip_add（天赋卷轴 1 碎片 1 片）、essence_sub（装备精华 1 扣 1 个）
 * 输出成功次数。
 */

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use Hanjiang\Model\Equipment;
use Hanjiang\Model\MemberJade;
use Hanjiang\Model\OwnMount;
use Hanjiang\Model\TraderLog;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;

date_default_timezone_set('Asia/Shanghai');
$dbConfig = require dirname(__DIR__, 2) . '/config/database.php';
DB::init($dbConfig['mysql']);
Redis::init($dbConfig['redis']);

$op = $argv[1] ?? '';
$uid = (int)($argv[2] ?? 0);
$times = (int)($argv[3] ?? 0);
$ok = 0;
for ($i = 0; $i < $times; $i++) {
    $ok += match ($op) {
        'jade_add' => MemberJade::add($uid, 1, 'test', 'worker')['status'] === 1 ? 1 : 0,
        'jade_sub' => MemberJade::sub($uid, 1, 'test', 'worker')['status'] === 1 ? 1 : 0,
        'trader_add' => TraderLog::addJade($uid, 3, 1)['status'] === 1 ? 1 : 0,
        'chip_add' => Equipment::addChip($uid, 3, 1)['status'] === 1 ? 1 : 0,
        'mount_chip_add' => OwnMount::addMount($uid, 1, 1, 1)['status'] === 1 ? 1 : 0,
        'scroll_chip_add' => ($r = Equipment::addTalentScroll($uid, 1, 1, 1)) !== [] && $r['status'] === 1 ? 1 : 0,
        'essence_sub' => Equipment::subTowerItem($uid, 1, 1),
        default => throw new InvalidArgumentException("unknown op {$op}"),
    };
}
echo $ok;
