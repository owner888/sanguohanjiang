<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use OmniPHP\WebSocket\Broadcaster;

/**
 * 国家逍遥神骰行大运、掷骰积分榜与大顺子/豹子暴击处理器 (DDD Activity Domain)
 */
final class DiceHandler
{
    /**
     * 逍遥神骰主界面: c=dice&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();

        $diceData = self::getUserDiceData($uid);
        $nickname = (string)($member['nickname'] ?? ('主公' . $uid));

        // 上周活动排行与领奖信息 (完全匹配 countrydicepop.js buildLastWeek 结构)
        $isClaimed = (int)(Redis::get("dice_last_claimed:{$uid}") ?? 0);
        $lastWeek = [
            'status' => 1,
            'points' => max(100, (int)$diceData['points'] * 2),
            'now_rank' => 1,
            'reward' => '100元宝 + 50000银两',
            'get_rank' => $isClaimed,
        ];

        // 积分达标大礼
        $curPoints = (int)$diceData['points'];
        $rewardList = [
            1 => ['id' => 1, 'points' => 100, 'status' => ($curPoints >= 100 ? 1 : 0)],
            2 => ['id' => 2, 'points' => 300, 'status' => ($curPoints >= 300 ? 1 : 0)],
            3 => ['id' => 3, 'points' => 600, 'status' => ($curPoints >= 600 ? 1 : 0)],
        ];

        // 本国成员积分排行榜
        $rankList = [
            [
                'rank' => 1,
                'uid' => $uid,
                'nickname' => $nickname,
                'points' => $curPoints,
                'reward' => '100元宝 + 50000银两',
            ],
            [
                'rank' => 2,
                'uid' => 10001,
                'nickname' => '关云长',
                'points' => max(0, $curPoints - 20),
                'reward' => '50元宝 + 30000银两',
            ],
            [
                'rank' => 3,
                'uid' => 10002,
                'nickname' => '张翼德',
                'points' => max(0, $curPoints - 40),
                'reward' => '30元宝 + 20000银两',
            ],
        ];

        $memberInfo = [
            'points' => $curPoints,
            'times' => (int)$diceData['times'],
            'cost' => (int)$diceData['cost'],
            'dice1' => (int)$diceData['dice1'],
            'dice2' => (int)$diceData['dice2'],
            'dice3' => (int)$diceData['dice3'],
        ];

        return [
            'status' => 1,
            'member' => $memberInfo,
            'last_week' => $lastWeek,
            'list' => $rankList,
            'reward' => $rewardList,
            // 向下兼容保留原字段
            'left_times' => (int)$diceData['times'],
            'last_dice' => [$diceData['dice1'], $diceData['dice2'], $diceData['dice3']],
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
        $diceData = self::getUserDiceData($uid);

        if ((int)$diceData['times'] <= 0) {
            return ['status' => -5, 'message' => '今日投掷神骰次数已用尽'];
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
            $pointsGained = 50;
            $desc = "六六大顺！惊现天选【{$d1}-{$d2}-{$d3}】至尊豹子！";
        } elseif ($isShunzi) {
            $rewardSilver = 20000;
            $pointsGained = 30;
            $desc = "势如长虹！掷出【{$dices[0]}-{$dices[1]}-{$dices[2]}】如意大顺子！";
        } else {
            $sum = $d1 + $d2 + $d3;
            $rewardSilver = $sum * 1000;
            $pointsGained = $sum;
            $desc = "点数和为 {$sum} 点！";
        }

        $diceData['times'] = max(0, (int)$diceData['times'] - 1);
        $diceData['points'] = (int)$diceData['points'] + $pointsGained;
        $diceData['dice1'] = $d1;
        $diceData['dice2'] = $d2;
        $diceData['dice3'] = $d3;

        Redis::setJson("user_dice_info:{$uid}", $diceData, 86400 * 30);
        Redis::set('dice_times:' . $uid, (string)$diceData['times'], 86400);

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

        $curPoints = (int)$diceData['points'];
        $rewardList = [
            1 => ['id' => 1, 'points' => 100, 'status' => ($curPoints >= 100 ? 1 : 0)],
            2 => ['id' => 2, 'points' => 300, 'status' => ($curPoints >= 300 ? 1 : 0)],
            3 => ['id' => 3, 'points' => 600, 'status' => ($curPoints >= 600 ? 1 : 0)],
        ];

        $memberInfo = [
            'points' => $curPoints,
            'times' => (int)$diceData['times'],
            'cost' => (int)$diceData['cost'],
            'dice1' => $d1,
            'dice2' => $d2,
            'dice3' => $d3,
        ];

        return [
            'status' => 1,
            'member' => $memberInfo,
            'reward' => $rewardList,
            'list' => [
                ['rank' => 1, 'uid' => $uid, 'nickname' => '主公', 'points' => $curPoints, 'reward' => '100元宝 + 50000银两'],
            ],
            // 向下兼容保留原字段
            'dices' => [$d1, $d2, $d3],
            'left_times' => $diceData['times'],
            'is_baozi' => $isBaozi ? 1 : 0,
            'is_shunzi' => $isShunzi ? 1 : 0,
            'silver' => $rewardSilver,
            'gold' => $rewardGold,
            'message' => "骰子落定！{$desc}获得银两 {$rewardSilver}" . ($rewardGold > 0 ? "、元宝 {$rewardGold}" : '') . "！",
        ];
    }

    /**
     * 领取上周神骰排名大奖: c=dice&m=get_last_week_reward
     */
    public static function get_last_week_reward(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $claimedKey = "dice_last_claimed:{$uid}";

        if (Redis::get($claimedKey) !== null) {
            return ['status' => -1, 'message' => '上周神骰排名奖励已领取过'];
        }

        // 发放奖励 100 元宝 + 50000 银两
        MemberHandler::addCurrency($uid, silver: 50000, gold: 100);
        Redis::set($claimedKey, '1', 86400 * 7);

        return [
            'status' => 1,
            'message' => '恭喜主公！上周神骰排名奖励领取成功，获得 100 元宝与 50000 银两！',
        ];
    }

    /**
     * 获取玩家掷骰数据
     *
     * @return array<string, int>
     */
    private static function getUserDiceData(int $uid): array
    {
        $key = "user_dice_info:{$uid}";
        $data = Redis::getJson($key);

        if (!is_array($data)) {
            $data = [
                'points' => 50,
                'times' => 10,
                'cost' => 10,
                'dice1' => 6,
                'dice2' => 6,
                'dice3' => 6,
            ];
            Redis::setJson($key, $data, 86400 * 30);
        }

        return $data;
    }
}
