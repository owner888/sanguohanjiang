<?php

declare(strict_types=1);

/**
 * 充值并发测试用的独立进程（PayTest 同时起多个，模拟多个 Workerman 进程同时收到渠道回调）
 *
 * 用法：php pay_worker.php <uid> <productId> <amount> <orderId>[,<orderId>...]
 * 逐个订单调用 Model\Pay::complete，输出各次的状态码（逗号分隔）。
 */

require dirname(__DIR__, 2) . '/bootstrap.php';

use Hanjiang\Model\Pay;
use Hanjiang\Support\Connections;

Connections::init();

$uid = (int)($argv[1] ?? 0);
$productId = (int)($argv[2] ?? 0);
$amount = (int)($argv[3] ?? 0);
$status = [];
foreach (explode(',', $argv[4] ?? '') as $orderId) {
    $status[] = Pay::complete($uid, $productId, $orderId, 'test', $amount)['status'];
}
echo implode(',', $status);
