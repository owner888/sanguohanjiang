<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use Hanjiang\MasterData\ActivityTime;
use Hanjiang\MasterData\AlipayProduct;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use PDOException;

/**
 * 充值到账核心（移植旧版 controllers/pay.php 各渠道 confirm_* 回调共同的到账部分 + models/pay_model.php 的商品 / 订单）
 *
 * 旧版每个渠道回调（confirm_91_order / confirm_pp_order / szf_confirm / googlepay_confirm / alipay …）校验完渠道签名后
 * 做的事都一样：按订单金额和返利活动找商品 → 发元宝（返利 / 节日返利另加）→ vip_model->pay 记 VIP（累计金额、升级、
 * 首充日志、档次首充、清 cd、信件、pay_log）→ 写渠道订单表 → 各充值活动记账。这里只移植这部分，与渠道无关：
 * 渠道（下单 create_* 与回调验签 confirm_*）待开发者确定后接入，接入时验签通过后调用 complete()。
 *
 * 订单表 pay_order 合并旧版各渠道结构相同的成功订单表（alipay_order / 91pay_order / pppay_order …），paychannel 记渠道；
 * 订单号唯一，同一订单重复回调只到账一次（返回 6，同旧版 confirm_91_order 的「已领取」）。
 * 整个到账在玩家行锁事务里执行，多 Worker 并发回调互斥。
 */
final class Pay
{
    /** 订单号列宽（pay_order.orderid / pay_log.orderid） */
    public const int ORDERID_LENGTH = 50;

    /**
     * 运营活动是否开启（activity_model->activity_status：starttime <= 当前 <= endtime），开启时返回活动行
     *
     * @return array<string, int|string>|null
     */
    public static function activity(string $name, int $now): ?array
    {
        foreach (ActivityTime::ROWS as $row) {
            if ($row['name'] === $name) {
                return $row['starttime'] <= $now && $row['endtime'] >= $now ? $row : null;
            }
        }
        return null;
    }

    /**
     * 商品列表（pay_model->get_product_list：返利活动开启时取 type 2 返利商品，否则 type 1）
     *
     * @return list<array<string, int|string>>
     */
    public static function productList(int $activity): array
    {
        $type = $activity === 0 ? 1 : 2;
        return array_values(array_filter(AlipayProduct::ROWS, static fn (array $row): bool => $row['type'] === $type));
    }

    /**
     * 按订单金额查商品（pay_model->get_order_product：返利活动时 type 2，否则 type 1）
     *
     * @return array<string, int|string>|null
     */
    public static function orderProduct(int $amount, int $activity): ?array
    {
        $type = $activity ? 2 : 1;
        foreach (AlipayProduct::ROWS as $row) {
            if ($row['type'] === $type && $row['amount'] === $amount) {
                return $row;
            }
        }
        return null;
    }

    /**
     * 充值到账（订单号幂等、多 Worker 安全）
     *
     * 渠道回调验签通过后调用。商品按 $productId 取得金额，再同旧版按金额和玩家当前的返利资格重新取商品（返利期间首充
     * 的玩家拿 type 2 返利商品）。状态码（沿用 confirm_91_order / complete_alipay_order）：
     *  -1 参数错误（订单号为空或超过 50 字符、金额不大于 0）
     *  -4 玩家不存在
     *   3 商品不存在
     *   6 订单已到账（重复回调）
     *   7 支付金额与商品价格不符
     *   1 成功：gold 为到账元宝（含返利），add_gold 为附赠元宝，vip 为充值后 VIP 等级，viptype 为首充状态（1 首充）
     *
     * @param string $channel 渠道标识（记入 pay_order.paychannel / pay_log.paytype），如 manual
     * @param int $amount 实付金额（人民币元）
     * @return array<string, int>
     */
    public static function complete(int $uid, int $productId, string $orderId, string $channel, int $amount, ?int $now = null): array
    {
        $now ??= time();
        if ($orderId === '' || mb_strlen($orderId) > self::ORDERID_LENGTH || $amount <= 0) {
            return ['status' => -1];
        }

        try {
            $result = Member::locked($uid, static fn (): array => self::credit($uid, $productId, $orderId, $channel, $amount, $now));
        } catch (PDOException $e) {
            // 订单号唯一键冲突：同一订单被另一个玩家的回调抢先到账
            if ($e->getCode() === '23000') {
                return ['status' => 6];
            }
            throw $e;
        }

        if ($result['status'] === 1) {
            Vip::sendPayMail($uid, $result['mail']);
            Redis::del('player:' . $uid);
        }
        unset($result['mail']);
        return $result;
    }

    /**
     * 手工到账（tools/pay_grant.php 与开发者测试接口 c=pay&m=test_grant 共用）
     *
     * 按商品价格走 complete()，订单号 manual_<uid>_<时间>_<随机数>，渠道 manual。返回 complete() 的结果加 orderid。
     *
     * @return array<string, int|string>
     */
    public static function grantManual(int $uid, int $productId, ?int $now = null): array
    {
        $now ??= time();
        $orderId = sprintf('manual_%d_%s_%04d', $uid, date('ymdHis', $now), random_int(0, 9999));
        $product = AlipayProduct::ROWS[$productId] ?? null;
        $result = $product === null
            ? ['status' => 3]
            : self::complete($uid, $productId, $orderId, 'manual', (int)$product['amount'], $now);
        return $result + ['orderid' => $orderId];
    }

    /**
     * 到账（在玩家行锁事务里执行）
     *
     * @return array<string, mixed>
     */
    private static function credit(int $uid, int $productId, string $orderId, string $channel, int $amount, int $now): array
    {
        // 订单已到账
        if (DB::table('pay_order')->where('orderid', $orderId)->value('id') !== null) {
            return ['status' => 6];
        }
        // 查询用户信息
        $user = DB::table('member')->select('uid', 'nickname', 'level', 'gold', 'rebate_firstpay', 'created_at')
            ->where('uid', $uid)->whereNull('deleted_at')->first();
        if ($user === null) {
            return ['status' => -4];
        }
        $product = AlipayProduct::ROWS[$productId] ?? null;
        if ($product === null) {
            return ['status' => 3];
        }
        if ($amount !== $product['amount']) {
            // 支付金额不足
            return ['status' => 7];
        }

        // 返利活动（构造函数 activity_status('rebate')）；玩家返利期间已充过值时不再返利
        $rebateActivity = self::activity('rebate', $now) !== null ? 1 : 0;
        $activity = (int)$user['rebate_firstpay'] ? 0 : $rebateActivity;
        // 查询订单商品
        $product = self::orderProduct($amount, $activity);
        if ($product === null) {
            return ['status' => 3];
        }

        // 发放元宝
        $gold = $product['gold'];
        $addGold = $product['add_gold'];
        if ($activity) {
            // 返利
            $gold += $product['add_gold'];
        }
        // 圣诞 / 春节充值活动开启：每笔充值按比例返利（christmaspay 比例为 0；newyearpay 10%，type 1 时 20%）
        [$christmas, $christmasRebate] = self::christmas($now);
        if ($christmas) {
            $extra = (int)($product['gold'] / 100 * $christmasRebate);
            $gold += $extra;
            $addGold += $extra;
        }
        Member::addCurrency($uid, gold: $gold);

        // vip 记录（RMB），返回数据中 type 是 1 的话就是首充
        $vip = Vip::pay($uid, $amount, 0, $addGold, $orderId, $channel, $now);

        // 创建后台充值订单（insert_91pay_order 等）
        $joinTime = (int)strtotime((string)$user['created_at']);
        DB::table('pay_order')->insert([
            'uid' => $uid,
            'name' => (string)$user['nickname'],
            'level' => (int)$user['level'],
            'pay_befor' => (int)$user['gold'],
            'pay_after' => (int)$user['gold'] + $gold,
            'type' => $vip['type'],
            'amount' => $amount,
            'gold' => $gold,
            'orderid' => $orderId,
            'status' => 1,
            'dateline' => $now,
            'regchannel' => (int)(DB::table('user')->where('uid', $uid)->value('channel') ?? 0),
            'paychannel' => Member::logText($channel, 20),
            'jointime' => $joinTime,
        ]);
        // 返利活动期间首次充值：之后不再享受返利
        if ($rebateActivity && (int)$user['rebate_firstpay'] === 0) {
            DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->update(['rebate_firstpay' => 1]);
        }

        // 依赖未移植：圣诞 / 春节充值记录 christmas_model->add_christmas_log（christmaspay / newyearpay）、
        // 暴打名将 beatengod_model->add_beatengod_pay_order、我爱世界杯 worldcup_model->add_beatengod_pay_order、
        // 充值送豪奖 actthirty_model->add_thirty_pay_order
        // 依赖未移植：pay_act_init 的充值类活动计数——七夕 acttanabata、国家庆典 actcelebration、中秋 moon、
        // 发工资 actpaychecks、貔貅聚财 actenrichment（这些活动未移植，旧版数据也均已结束）

        // 跳楼返利活动开启：记录本次充值金额（上一期返利 30 天未领完时不记）
        $jumpRebate = self::activity('jump_rebate', $now);
        if ($jumpRebate !== null) {
            $rebateDateline = (int)(DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->value('rebate_dateline') ?? 0);
            $endtime = strtotime(date('y-m-d', $rebateDateline)) + 30 * 86400;
            if ($now > $endtime) {
                Rebate::addOrder($uid, (int)$user['level'], $amount, $orderId, (int)$jumpRebate['version'], $now);
            }
        }

        return [
            'status' => 1,
            'gold' => $gold,
            'add_gold' => $addGold,
            'vip' => $vip['rank'],
            'viptype' => $vip['type'],
            'mail' => $vip['mail'],
        ];
    }

    /**
     * 圣诞 / 春节充值返利（构造函数：christmaspay 开启时比例 0；否则 newyearpay 开启时 10%，活动 type 1 为 20%）
     *
     * @return array{0: int, 1: int} [是否开启, 返利百分比]
     */
    private static function christmas(int $now): array
    {
        if (self::activity('christmaspay', $now) !== null) {
            return [1, 0];
        }
        $info = self::activity('newyearpay', $now);
        if ($info !== null) {
            return [1, (int)($info['type'] ?? 0) === 1 ? 20 : 10];
        }
        return [0, 0];
    }
}
