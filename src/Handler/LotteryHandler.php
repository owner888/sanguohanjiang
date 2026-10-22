<?php

declare(strict_types=1);

namespace Hanjiang\Handler;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use OmniPHP\WebSocket\Broadcaster;

final class LotteryHandler
{
    /**
     * 转盘八大扇区奖池
     */
    public const array SECTORS = [
        1 => ['id' => 1, 'name' => '万两银票', 'silver' => 10000, 'gold' => 0],
        2 => ['id' => 2, 'name' => '特级军令酒', 'silver' => 5000, 'gold' => 0],
        3 => ['id' => 3, 'name' => '精炼玄铁 x2', 'silver' => 8000, 'gold' => 0],
        4 => ['id' => 4, 'name' => '赤炎红宝石 x1', 'silver' => 12000, 'gold' => 10],
        5 => ['id' => 5, 'name' => '天命星魂晶 x30', 'silver' => 15000, 'gold' => 0],
        6 => ['id' => 6, 'name' => '极品将魂礼包', 'silver' => 20000, 'gold' => 20],
        7 => ['id' => 7, 'name' => '百两元宝金盒', 'silver' => 0, 'gold' => 100],
        8 => ['id' => 8, 'name' => '至尊天下无双大奖', 'silver' => 88888, 'gold' => 200],
    ];

    /**
     * 幸运大转盘主界面: c=lottery&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $times = (int)(Redis::get('user_lottery_times:' . $uid) ?? 3);

        return [
            'status' => 1,
            'left_times' => $times,
            'cost_gold' => 20,
            'sectors' => array_values(self::SECTORS),
            'message' => 'ok',
        ];
    }

    /**
     * 旋转转盘抽奖: c=lottery&m=action
     */
    public static function action(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $times = (int)(Redis::get('user_lottery_times:' . $uid) ?? 3);

        // 如果没有免费次数，消耗 20 元宝
        if ($times <= 0) {
            if (!MemberHandler::deductCurrency($uid, gold: 20)) {
                return ['status' => -1, 'message' => '今日免费抽奖次数已用尽，且元宝不足 20'];
            }
        } else {
            Redis::set('user_lottery_times:' . $uid, (string)($times - 1), 86400);
        }

        // 随机抽取扇区
        $rand = mt_rand(1, 100);
        $sectorId = match (true) {
            $rand <= 5  => 8, // 5% 概率开出 8 号至尊大奖
            $rand <= 15 => 7, // 10% 概率开出 100 元宝
            $rand <= 30 => 6,
            $rand <= 50 => 5,
            $rand <= 70 => 4,
            default     => mt_rand(1, 3),
        };

        $prize = self::SECTORS[$sectorId];
        $rewardSilver = (int)$prize['silver'];
        $rewardGold = (int)$prize['gold'];

        MemberHandler::addCurrency($uid, silver: $rewardSilver, gold: $rewardGold);

        if ($sectorId >= 7) {
            $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
            $pName = $member['nickname'] ?? ('主公' . $uid);
            Broadcaster::broadcast('marquee', [
                'content' => "气运冲霄！主公【{$pName}】天赐幸运大转盘指针锁定【{$prize['name']}】，独揽 {$rewardGold} 元宝天赐大奖！",
                'type' => 'system_broadcast',
            ]);
        }

        return [
            'status' => 1,
            'sector_id' => $sectorId,
            'prize_name' => $prize['name'],
            'silver' => $rewardSilver,
            'gold' => $rewardGold,
            'left_times' => max(0, $times - 1),
            'message' => "指针停驻！恭喜抽中【{$prize['name']}】！获得银两 {$rewardSilver}" . ($rewardGold > 0 ? "、元宝 {$rewardGold}" : '') . "！",
        ];
    }
}
