<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

final class BusinessHandler
{
    /**
     * 四大通商都市行情
     */
    public const array CITIES = [
        1 => ['id' => 1, 'name' => '中原·洛阳商市', 'goods' => '宣城官窑瓷器', 'base_cost' => 5000, 'base_yield' => 12000],
        2 => ['id' => 2, 'name' => '蜀道·成都锦肆', 'goods' => '极品蜀绣罗绢', 'base_cost' => 10000, 'base_yield' => 25000],
        3 => ['id' => 3, 'name' => '江东·建业盐铁', 'goods' => '会稽海青云盐', 'base_cost' => 20000, 'base_yield' => 55000],
        4 => ['id' => 4, 'name' => '荆襄·襄阳粮仓', 'goods' => '南阳陈酿杜康', 'base_cost' => 40000, 'base_yield' => 120000],
    ];

    /**
     * 通商主界面: c=business&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $businessState = Redis::getJson('user_business:' . $uid) ?: [
            'city_id' => 0,
            'start_time' => 0,
        ];

        $cityId = (int)$businessState['city_id'];
        $startTime = (int)$businessState['start_time'];
        $now = time();

        $curCity = self::CITIES[$cityId] ?? null;
        $isFinished = false;
        if ($curCity !== null && $startTime > 0) {
            $elapsed = $now - $startTime;
            $isFinished = ($elapsed >= 60); // 通商历时 60 秒
        }

        return [
            'status' => 1,
            'cities' => array_values(self::CITIES),
            'current_trade' => $curCity ? $curCity['name'] : '无商旅在途',
            'is_trading' => ($cityId > 0 && !$isFinished) ? 1 : 0,
            'can_harvest' => $isFinished ? 1 : 0,
            'profit' => $curCity['base_yield'] ?? 0,
            'message' => 'ok',
        ];
    }

    /**
     * 派遣商队行商通商: c=business&m=go_business 或 m=buy
     */
    public static function go_business(Context $ctx): array
    {
        // 依赖未移植：旧版通商达成幸运通商时 chat_model->send_message_to_horn(business_accomplish) 小喇叭（本玩法为新版自定，随旧版 business 移植）
        return self::buy($ctx);
    }

    public static function buy(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $cityId = (int)($ctx->input('id', $ctx->input('city_id', 1)));

        if (!isset(self::CITIES[$cityId])) {
            return ['status' => 0, 'message' => '通商城市不存在'];
        }

        $cfg = self::CITIES[$cityId];
        $costSilver = (int)$cfg['base_cost'];

        // 检查当前是否有商旅未归
        $state = Redis::getJson('user_business:' . $uid);
        if (is_array($state) && !empty($state['city_id'])) {
            $elapsed = time() - (int)$state['start_time'];
            if ($elapsed < 60) {
                return ['status' => 0, 'message' => '已有商队正在通商路上，请等待商旅归来'];
            }
        }

        if (!MemberHandler::deductCurrency($uid, silver: $costSilver)) {
            return ['status' => -1, 'message' => "银两不足，筹备【{$cfg['goods']}】需要 {$costSilver} 银两"];
        }

        Redis::setJson('user_business:' . $uid, [
            'city_id' => $cityId,
            'start_time' => time(),
        ], 86400 * 2);

        return [
            'status' => 1,
            'city_name' => $cfg['name'],
            'goods' => $cfg['goods'],
            'profit' => $cfg['base_yield'],
            'message' => "商队满载【{$cfg['goods']}】启程前往【{$cfg['name']}】！预计 60 秒后售得高额利润！",
        ];
    }

    /**
     * 刷新商市行情: c=business&m=refresh
     */
    public static function refresh(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $state = Redis::getJson('user_business:' . $uid);

        // 如果已完成直接结算
        if (is_array($state) && !empty($state['city_id'])) {
            $elapsed = time() - (int)$state['start_time'];
            if ($elapsed >= 60) {
                $cityId = (int)$state['city_id'];
                $cfg = self::CITIES[$cityId] ?? self::CITIES[1];
                $yield = (int)$cfg['base_yield'];

                MemberHandler::addCurrency($uid, silver: $yield);
                Redis::del('user_business:' . $uid);

                return [
                    'status' => 1,
                    'silver' => $yield,
                    'message' => "商旅凯旋！【{$cfg['goods']}】在【{$cfg['name']}】抢购一空，斩获银两 {$yield}！",
                ];
            }
        }

        return [
            'status' => 1,
            'message' => '四海商市行情已刷新，物价平稳！',
        ];
    }
}
