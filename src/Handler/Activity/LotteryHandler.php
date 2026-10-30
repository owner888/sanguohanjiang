<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use OmniPHP\WebSocket\Broadcaster;

/**
 * 天赐幸运大转盘、扇区抽奖与每日购买次数处理器 (DDD Activity Domain)
 */
final class LotteryHandler
{
    /**
     * 转盘 8 大固定奖品配置
     */
    public const array LOTTERY_ITEMS = [
        0 => ['id' => 1, 'group' => 1, 'gettype' => 1, 'getvalue' => 10000, 'itemname' => '万两银票'],
        1 => ['id' => 2, 'group' => 1, 'gettype' => 8, 'getvalue' => 10, 'itemname' => '突飞卡 x10'],
        2 => ['id' => 3, 'group' => 1, 'gettype' => 3, 'getvalue' => 15, 'itemname' => '军令 x15'],
        3 => ['id' => 4, 'group' => 1, 'gettype' => 7, 'getvalue' => 101, 'itemid' => 101, 'itemquality' => 3, 'itemtype' => 1, 'itemname' => '青钢名剑'],
        4 => ['id' => 5, 'group' => 1, 'gettype' => 5, 'getvalue' => 5, 'itemname' => '黄宝石 x5'],
        5 => ['id' => 6, 'group' => 1, 'gettype' => 2, 'getvalue' => 50, 'itemname' => '五十元宝'],
        6 => ['id' => 7, 'group' => 1, 'gettype' => 1, 'getvalue' => 30000, 'itemname' => '三万银两'],
        7 => ['id' => 8, 'group' => 1, 'gettype' => 2, 'getvalue' => 200, 'itemname' => '二百元宝'],
    ];

    /**
     * 兼容已有单测的扇区定义
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
        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        $level = max(1, (int)($member['level'] ?? 1));

        $times = (int)(Redis::get('user_lottery_times:' . $uid) ?? 3);
        $buyTimes = (int)(Redis::get('user_lottery_buy_times:' . $uid) ?? 0);
        $buyLimit = 10;

        // 根据玩家等级动态计算银两奖赏
        $items = self::LOTTERY_ITEMS;
        foreach ($items as &$item) {
            if ($item['gettype'] === 1) {
                $item['getvalue'] = $item['getvalue'] * max(1, (int)($level / 5));
            }
        }
        unset($item);

        $logInfo = [
            'lavenumber' => $times,
            'buy_lavenumber' => 0,
            'total_num' => $times,
            'price' => 20,
            'day' => 1,
        ];

        return [
            'status' => 1,
            'list' => array_values($items),
            'log' => [
                'status' => 1,
                'info' => $logInfo,
            ],
            'buy_times' => $buyTimes,
            'buy_times_limit' => $buyLimit,
            'buy_times_total' => max(0, $buyLimit - $buyTimes),
            // 向下兼容保留原字段
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
        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();

        // 如果没有免费次数，消耗 20 元宝
        if ($times <= 0) {
            if (!MemberHandler::deductCurrency($uid, gold: 20)) {
                return ['status' => -2, 'message' => '今日抽奖次数已用尽，且元宝不足 20'];
            }
            $newTimes = 0;
        } else {
            $newTimes = max(0, $times - 1);
            Redis::set('user_lottery_times:' . $uid, (string)$newTimes, 86400);
        }

        // 随机抽取 0~7 号奖品 (对应 id 1~8)
        $rand = mt_rand(1, 100);
        $itemIdx = match (true) {
            $rand <= 5  => 7, // 5% 概率抽中 8 号 200 元宝
            $rand <= 15 => 5, // 10% 概率抽中 6 号 50 元宝
            $rand <= 30 => 3, // 装备
            $rand <= 50 => 1, // 突飞卡
            $rand <= 70 => 2, // 军令
            default     => 0, // 银两
        };

        $prizeItem = self::LOTTERY_ITEMS[$itemIdx];
        $sectorId = (int)$prizeItem['id'];

        $rewardSilver = 0;
        $rewardGold = 0;
        $rewardAct = 0;

        switch ($prizeItem['gettype']) {
            case 1:
                $rewardSilver = (int)$prizeItem['getvalue'];
                MemberHandler::addCurrency($uid, silver: $rewardSilver);
                break;
            case 2:
                $rewardGold = (int)$prizeItem['getvalue'];
                MemberHandler::addCurrency($uid, gold: $rewardGold);
                break;
            case 3:
                $rewardAct = (int)$prizeItem['getvalue'];
                MemberHandler::addCurrency($uid, act: $rewardAct);
                break;
            case 7:
                // 依赖未移植：旧版转盘按 MasterData Lottery 分组/概率抽奖（gettype 7 发 getvalue 号装备）；
                // 本格「青钢名剑」不在 MasterData Equip 中（101 是玄武杖），没有可对应的模板，不发装备。
                break;
            case 8:
                // 突飞卡
                $field = DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->first();
                if ($field !== null) {
                    DB::table('member_field')->where('uid', $uid)->update([
                        'freeleap' => (int)($field['freeleap'] ?? 0) + (int)$prizeItem['getvalue'],
                    ]);
                }
                break;
        }

        Redis::del('player:' . $uid);

        // 重新获取最新货币资产
        $freshMember = DB::table('member')->where('uid', $uid)->first();

        if ($rewardGold >= 50) {
            $pName = $freshMember['nickname'] ?? ('主公' . $uid);
            Broadcaster::broadcast('marquee', [
                'content' => "天赐鸿运！主公【{$pName}】转动幸运大转盘命中【{$prizeItem['itemname']}】，斩获 {$rewardGold} 元宝！",
                'type' => 'system_broadcast',
            ]);
        }

        $info = array_merge($prizeItem, [
            'all_silver' => (int)($freshMember['silver'] ?? 0),
            'all_gold' => (int)($freshMember['gold'] ?? 0),
            'all_act' => (int)($freshMember['act'] ?? 0),
        ]);

        return [
            'status' => 1,
            'info' => $info,
            'total_num' => $newTimes,
            'generalinfo' => [],
            // 向下兼容保留原字段
            'sector_id' => $sectorId,
            'prize_name' => $prizeItem['itemname'],
            'silver' => $rewardSilver,
            'gold' => $rewardGold,
            'left_times' => $newTimes,
            'message' => "指针停驻！恭喜抽中【{$prizeItem['itemname']}】！",
        ];
    }
}
