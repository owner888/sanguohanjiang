<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\Pay;
use Hanjiang\Model\Vip;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 充值商品（移植旧版 controllers/pay.php:get_product_list，路径相对旧版 s1/application/）
 *
 * 商品为旧版 alipay_product（MasterData AlipayProduct：type 1 正常、type 2 返利，返利活动 ActivityTime rebate 开启时
 * 未在返利期间充过值的玩家取 type 2）。到账核心见 Model\Pay::complete（开发者可用 tools/pay_grant.php 手工到账）。
 *
 * 开发者测试充值（新版新增，旧版没有）：test_info / test_grant，仅在环境变量 PAY_TEST=1（config/app.php pay_test）时可用，
 * 客户端设置弹窗据 test_info 显示「测试充值」按钮。生产环境绝不能开启。
 *
 * 客户端调用（iospaypop.js / rebatepop.js / miscpop.js）：get_product_list 已移植；
 * 待定渠道（渠道待开发者确定，不移植）：get_pay_info（支付宝下单签名）、create_91_order、create_pp_order、
 * create_kuaiyong_order、szf_order，以及旧版只由渠道服务器回调的 confirm_* / googlepay_* / elex_* / *_sf_* / 网站充值接口。
 */
final class PayHandler
{
    /** base_lang.php：base_first_info */
    private const string LANG_FIRST_INFO = '赠送%s元宝(限购1次)';

    /**
     * 充值商品列表: c=pay&m=get_product_list
     *
     * 每个商品带 info：该元宝档次还没充过时为「赠送 X 元宝(限购1次)」（档次首充另送同额元宝，见 Model\Vip），充过为空串。
     */
    public static function get_product_list(Context $ctx): array
    {
        /**
         * 3 获取订单出错
         * 2 没有商品
         * 1 成功
         */
        $return = ['status' => 0, 'list' => [], 'cd' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->select('rebate_firstpay')->where('uid', $uid)->whereNull('deleted_at')->first();
        if ($member === null) {
            return $return;
        }
        $now = BossClock::now();

        // 返利活动开启状态；玩家返利期间已充过值时不再返利
        $rebate = Pay::activity('rebate', $now);
        $activity = $rebate !== null ? 1 : 0;
        if ((int)$member['rebate_firstpay'] !== 0) {
            $activity = 0;
        }
        if ($activity) {
            $return['cd'] = (int)$rebate['endtime'] - $now;
        }

        $result = Pay::productList($activity);
        // 获取充值列表充值信息
        $payLog = Vip::rechargeLog($uid);
        if ($result !== []) {
            foreach ($result as &$val) {
                // 奖励信息
                $val['info'] = sprintf(self::LANG_FIRST_INFO, $val['gold']);
                // 充值档次
                $site = Vip::PAY_LIST[$val['gold']] ?? null;
                if ($site !== null && isset($payLog[$site])) {
                    $val['info'] = '';
                }
            }
            unset($val);
            // 成功返回
            $return['status'] = 1;
            $return['list'] = $result;
        } else {
            // 没有商品
            $return['status'] = 2;
        }
        return $return;
    }

    /**
     * 开发者测试充值开关与商品: c=pay&m=test_info
     *
     * enabled 为 false 时 list 为空；开启时 list 同 get_product_list 的 list。
     */
    public static function test_info(Context $ctx): array
    {
        if (!self::testEnabled()) {
            return ['status' => 1, 'enabled' => false, 'list' => []];
        }
        return ['status' => 1, 'enabled' => true, 'list' => self::get_product_list($ctx)['list']];
    }

    /**
     * 开发者测试充值: c=pay&m=test_grant&id=商品 id
     *
     * 与 tools/pay_grant.php 相同走 Model\Pay::grantManual（渠道 manual），返回 Pay::complete 的状态码与结果加 orderid；
     * 未开启（PAY_TEST 不为 1）时返回 status 0，什么也不做。
     */
    public static function test_grant(Context $ctx): array
    {
        if (!self::testEnabled()) {
            return ['status' => 0];
        }
        $uid = MemberHandler::resolveUid($ctx);
        if ($uid <= 0) {
            return ['status' => -4];
        }
        return Pay::grantManual($uid, (int)$ctx->input('id', 0));
    }

    /** 开发者测试充值是否开启（每次读取配置，环境变量 PAY_TEST=1） */
    private static function testEnabled(): bool
    {
        $appConfig = require dirname(__DIR__, 3) . '/config/app.php';
        return ($appConfig['pay_test'] ?? false) === true;
    }
}
