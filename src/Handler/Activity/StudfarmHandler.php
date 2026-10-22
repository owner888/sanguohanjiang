<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use OmniPHP\WebSocket\Broadcaster;

final class StudfarmHandler
{
    public const array TIERS = [
        1 => ['id' => 1, 'name' => '初级相马', 'cost_silver' => 5000, 'cost_gold' => 0, 'desc' => '小概率觅得神驹'],
        2 => ['id' => 2, 'name' => '伯乐鉴马', 'cost_silver' => 15000, 'cost_gold' => 20, 'desc' => '极大概率寻得高阶名驹'],
        3 => ['id' => 3, 'name' => '天命御马', 'cost_silver' => 0, 'cost_gold' => 100, 'desc' => '必定寻得绝世神驹赤兔或的卢'],
    ];

    /**
     * 相马场主界面: c=studfarm&m=index
     */
    public static function index(Context $ctx): array
    {
        return [
            'status' => 1,
            'list' => array_values(self::TIERS),
            'jade1' => 100,
            'jade2' => 50,
            'jade3' => 20,
            'message' => 'ok',
        ];
    }

    /**
     * 执行相马: c=studfarm&m=action
     */
    public static function action(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $tierId = (int)($ctx->input('id', 1));

        if (!isset(self::TIERS[$tierId])) {
            return ['status' => 0, 'message' => '相马品级不存在'];
        }

        $cfg = self::TIERS[$tierId];
        $costSilver = (int)$cfg['cost_silver'];
        $costGold = (int)$cfg['cost_gold'];

        // 原子安全扣费
        if (!MemberHandler::deductCurrency($uid, silver: $costSilver, gold: $costGold)) {
            return [
                'status' => -2,
                'message' => ($costGold > 0) ? "元宝不足，相马需要 {$costGold} 元宝" : "银两不足，相马需要 {$costSilver} 银两",
            ];
        }

        // 随机抽取名驹
        $mountPool = MountHandler::MOUNT_CONFIGS;
        $mountId = match ($tierId) {
            3 => (mt_rand(1, 100) <= 60) ? 1 : 2, // 赤兔或的卢
            2 => (mt_rand(1, 100) <= 40) ? 2 : 3, // 的卢或绝影
            default => (mt_rand(1, 100) <= 20) ? 3 : 4, // 绝影或爪黄飞电
        };

        $mount = $mountPool[$mountId];

        // 注入到玩家战马库
        $userMounts = MountHandler::getUserMounts($uid);
        if (!isset($userMounts[$mountId])) {
            $userMounts[$mountId] = ['id' => $mountId, 'level' => 1, 'gid' => 0];
        } else {
            // 已有则等级+1
            $userMounts[$mountId]['level'] = (int)$userMounts[$mountId]['level'] + 1;
        }
        Redis::setJson('user_mounts:' . $uid, $userMounts, 86400 * 30);

        // 高品级名驹全服广播
        if ((int)$mount['quality'] >= 5) {
            $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
            $pName = $member['nickname'] ?? ('主公' . $uid);
            Broadcaster::broadcast('marquee', [
                'content' => "天降神骏！主公【{$pName}】慧眼识珠，于伯乐马场成功相中绝世名驹【{$mount['name']}】！",
                'type' => 'system_broadcast',
            ]);
        }

        return [
            'status' => 1,
            'info' => $mount,
            'mount_name' => $mount['name'],
            'quality' => $mount['quality'],
            'message' => "恭喜伯乐识马！成功寻得名驹【{$mount['name']}】！",
        ];
    }
}
