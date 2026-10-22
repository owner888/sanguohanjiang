<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use OmniPHP\WebSocket\Broadcaster;

final class DiceHandler
{
    /**
     * 逍遥神骰主界面: c=dice&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $times = (int)(Redis::get('dice_times:' . $uid) ?? 10);
        $lastRoll = Redis::getJson('dice_last:' . $uid) ?: [6, 6, 6];

        return [
            'status' => 1,
            'left_times' => $times,
            'last_dice' => $lastRoll,
            'rules' => '掷出三枚骰子：豹子(三同点)斩获50元宝+5万银两；顺子斩获2万银两；普通按点数倍数瓜分银两！',
            'message' => 'ok',
        ];
    }

    /**
     * 投掷骰子: c=dice&m=shake_dice
     */
    public static function shake_dice(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $times = (int)(Redis::get('dice_times:' . $uid) ?? 10);

        if ($times <= 0) {
            return ['status' => 0, 'message' => '今日投掷神骰次数已用尽'];
        }

        // 投掷三枚骰子
        $d1 = mt_rand(1, 6);
        $d2 = mt_rand(1, 6);
        $d3 = mt_rand(1, 6);
        $dices = [$d1, $d2, $d3];
        sort($dices);

        $isBaozi = ($d1 === $d2 && $d2 === $d3);
        $isShunzi = ($dices[0] + 1 === $dices[1] && $dices[1] + 1 === $dices[2]);

        $rewardGold = 0;
        $rewardSilver = 0;
        $desc = '';

        if ($isBaozi) {
            $rewardGold = 50;
            $rewardSilver = 50000;
            $desc = "六六大顺！惊现天选【{$d1}-{$d2}-{$d3}】至尊豹子！";
        } elseif ($isShunzi) {
            $rewardSilver = 20000;
            $desc = "势如长虹！掷出【{$dices[0]}-{$dices[1]}-{$dices[2]}】如意大顺子！";
        } else {
            $sum = $d1 + $d2 + $d3;
            $rewardSilver = $sum * 1000;
            $desc = "点数和为 {$sum} 点！";
        }

        // 扣除次数
        Redis::set('dice_times:' . $uid, (string)($times - 1), 86400);
        Redis::setJson('dice_last:' . $uid, [$d1, $d2, $d3], 86400 * 7);

        // 发放奖励
        MemberHandler::addCurrency($uid, silver: $rewardSilver, gold: $rewardGold);

        if ($isBaozi) {
            $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
            $pName = $member['nickname'] ?? ('主公' . $uid);
            Broadcaster::broadcast('marquee', [
                'content' => "气运滔天！主公【{$pName}】逍遥掷骰掷出【{$d1}-{$d2}-{$d3}】至尊豹子，独揽 50 元宝与 5 万巨额银饷！",
                'type' => 'system_broadcast',
            ]);
        }

        return [
            'status' => 1,
            'dices' => [$d1, $d2, $d3],
            'left_times' => $times - 1,
            'is_baozi' => $isBaozi ? 1 : 0,
            'is_shunzi' => $isShunzi ? 1 : 0,
            'silver' => $rewardSilver,
            'gold' => $rewardGold,
            'message' => "骰子落定！{$desc}获得银两 {$rewardSilver}" . ($rewardGold > 0 ? "、元宝 {$rewardGold}" : '') . "！",
        ];
    }
}
