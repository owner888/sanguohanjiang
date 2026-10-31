<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

final class FlopHandler
{
    /**
     * 翻牌九宫格奖池
     */
    public const array CARDS = [
        1 => ['id' => 1, 'name' => '万两银票', 'silver' => 10000, 'gold' => 0],
        2 => ['id' => 2, 'name' => '至尊元宝盒', 'silver' => 0, 'gold' => 50],
        3 => ['id' => 3, 'name' => '极品红宝石', 'silver' => 5000, 'gold' => 10],
        4 => ['id' => 4, 'name' => '百炼玄铁', 'silver' => 8000, 'gold' => 0],
        5 => ['id' => 5, 'name' => '天命星魂石', 'silver' => 12000, 'gold' => 0],
        6 => ['id' => 6, 'name' => '传国玉玺碎片', 'silver' => 20000, 'gold' => 30],
        7 => ['id' => 7, 'name' => '特级军令酒', 'silver' => 5000, 'gold' => 0],
        8 => ['id' => 8, 'name' => '洗髓神丹', 'silver' => 6000, 'gold' => 0],
        9 => ['id' => 9, 'name' => '天下无双大礼', 'silver' => 50000, 'gold' => 100],
    ];

    /**
     * 获取玩家当前翻牌状态
     *
     * @return array{turned: list<int>, left_times: int}
     */
    public static function getFlopState(int $uid): array
    {
        $cached = Redis::getJson('user_flop:' . $uid);
        if (is_array($cached)) {
            return $cached;
        }

        $init = [
            'turned' => [],
            'left_times' => 5,
        ];
        Redis::setJson('user_flop:' . $uid, $init, 86400);
        return $init;
    }

    /**
     * 翻牌主界面: c=flop&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $state = self::getFlopState($uid);
        $turned = $state['turned'] ?? [];

        $cards = [];
        for ($i = 1; $i <= 9; $i++) {
            $isTurned = in_array($i, $turned, true);
            $cards[$i] = [
                'id' => $i,
                'turned' => $isTurned ? 1 : 0,
                'prize' => $isTurned ? self::CARDS[$i]['name'] : '神秘金边卡牌',
            ];
        }

        return [
            'status' => 1,
            'left_times' => max(0, (int)$state['left_times']),
            'cards' => array_values($cards),
            'message' => 'ok',
        ];
    }

    /**
     * 翻转卡牌夺宝: c=flop&m=turnover
     */
    public static function turnover(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $cardId = (int)($ctx->input('id', 1));

        if ($cardId < 1 || $cardId > 9) {
            return ['status' => 0, 'message' => '卡牌编号必须在 1 到 9 之间'];
        }

        $state = self::getFlopState($uid);
        $times = (int)$state['left_times'];
        $turned = $state['turned'] ?? [];

        if (in_array($cardId, $turned, true)) {
            return ['status' => 0, 'message' => '该卡牌已经翻开，请选择其他卡牌'];
        }

        if ($times <= 0) {
            // 消耗 10 元宝继续翻牌
            if (!MemberHandler::deductCurrency($uid, gold: 10)) {
                return ['status' => -1, 'message' => '今日免费翻牌次数已用尽，且元宝不足 10'];
            }
        } else {
            $state['left_times'] = $times - 1;
        }

        $prize = self::CARDS[$cardId];
        $rewardSilver = (int)$prize['silver'];
        $rewardGold = (int)$prize['gold'];

        MemberHandler::addCurrency($uid, silver: $rewardSilver, gold: $rewardGold);

        $state['turned'][] = $cardId;
        Redis::setJson('user_flop:' . $uid, $state, 86400);

        return [
            'status' => 1,
            'card_id' => $cardId,
            'prize_name' => $prize['name'],
            'silver' => $rewardSilver,
            'gold' => $rewardGold,
            'left_times' => max(0, (int)$state['left_times']),
            'message' => "金牌翻转！揭晓大奖【{$prize['name']}】，获得银两 {$rewardSilver}" . ($rewardGold > 0 ? "、元宝 {$rewardGold}" : '') . "！",
        ];
    }
}
