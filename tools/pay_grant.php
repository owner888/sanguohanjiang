<?php

declare(strict_types=1);

/**
 * 开发者手工充值（命令行工具，不经 HTTP，服务器上没有任何路由能调到它）
 *
 * 用法: php tools/pay_grant.php <uid> <productId>
 *
 * 按商品（MasterData AlipayProduct 的 id，见 c=pay&m=get_product_list）的价格走与渠道回调相同的到账核心
 * Model\Pay::complete（经 Pay::grantManual，与开发者测试接口 c=pay&m=test_grant 共用）：发元宝、累计 VIP、首充 / 档次首充、充值信件、pay_order / pay_log 记录。
 * 订单号为 manual_<uid>_<时间>_<随机数>，渠道记为 manual。用于在渠道接入前测试 VIP 与首充。
 */

use Hanjiang\MasterData\AlipayProduct;
use Hanjiang\Model\Pay;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require dirname(__DIR__) . '/vendor/autoload.php';

date_default_timezone_set('Asia/Shanghai');

$uid = (int)($argv[1] ?? 0);
$productId = (int)($argv[2] ?? 0);
$product = AlipayProduct::ROWS[$productId] ?? null;
if ($uid <= 0 || $product === null) {
    fwrite(STDERR, "用法: php tools/pay_grant.php <uid> <productId>\n商品：\n");
    foreach (AlipayProduct::ROWS as $row) {
        fwrite(STDERR, sprintf("  %2d  %s  %d 元（type %d）\n", $row['id'], $row['subject'], $row['amount'], $row['type']));
    }
    exit(1);
}

$dbConfig = require dirname(__DIR__) . '/config/database.php';
DB::init($dbConfig['mysql']);
Redis::init($dbConfig['redis']);

$result = Pay::grantManual($uid, $productId);
$orderId = $result['orderid'];

$messages = [
    1 => '到账成功',
    -1 => '参数错误',
    -4 => '玩家不存在',
    3 => '商品不存在',
    6 => '订单已到账',
    7 => '金额与商品价格不符',
];
fwrite(STDOUT, ($messages[$result['status']] ?? '失败') . "（订单 {$orderId}）\n");
fwrite(STDOUT, json_encode($result, JSON_UNESCAPED_UNICODE) . "\n");
exit($result['status'] === 1 ? 0 : 1);
