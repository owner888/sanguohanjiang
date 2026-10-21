<?php

declare(strict_types=1);

namespace Hanjiang\Handler;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use OmniPHP\WebSocket\Broadcaster;

final class JadegambleHandler
{
    /**
     * 原石毛料配置
     */
    public const array STONES = [
        1 => ['id' => 1, 'name' => '普通缅甸毛料', 'cost_silver' => 5000, 'cost_gold' => 0, 'base_val' => 8000],
        2 => ['id' => 2, 'name' => '帕敢场口老坑料', 'cost_silver' => 20000, 'cost_gold' => 0, 'base_val' => 35000],
        3 => ['id' => 3, 'name' => '莫西沙玻璃种原石', 'cost_silver' => 0, 'cost_gold' => 50, 'base_val' => 80000],
    ];

    /**
     * 赌石工坊主界面: c=jadegamble&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $stoneState = Redis::getJson('jadegamble_stone:' . $uid);

        return [
            'status' => 1,
            'list' => array_values(self::STONES),
            'current_stone' => $stoneState,
            'has_stone' => $stoneState ? 1 : 0,
            'message' => 'ok',
        ];
    }

    /**
     * 选购开采原石: c=jadegamble&m=mining
     */
    public static function mining(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $stoneId = (int)($ctx->input('id', 1));

        if (!isset(self::STONES[$stoneId])) {
            return ['status' => 0, 'message' => '原石毛料不存在'];
        }

        $exist = Redis::getJson('jadegamble_stone:' . $uid);
        if (is_array($exist)) {
            return ['status' => 0, 'message' => '当前已有未切开或未收取的原石，请先解石或收玉'];
        }

        $cfg = self::STONES[$stoneId];
        $costSilver = (int)$cfg['cost_silver'];
        $costGold = (int)$cfg['cost_gold'];

        if (!MemberHandler::deductCurrency($uid, silver: $costSilver, gold: $costGold)) {
            return [
                'status' => -1,
                'message' => ($costGold > 0) ? "元宝不足，购买原石需要 {$costGold} 元宝" : "银两不足，购买原石需要 {$costSilver} 银两",
            ];
        }

        $newStone = [
            'stone_id' => $stoneId,
            'name' => $cfg['name'],
            'base_val' => $cfg['base_val'],
            'is_cut' => 0,
            'cut_result' => null,
            'time' => time(),
        ];
        Redis::setJson('jadegamble_stone:' . $uid, $newStone, 86400 * 2);

        return [
            'status' => 1,
            'stone' => $newStone,
            'message' => "成功买入【{$cfg['name']}】！可以下刀解石了！",
        ];
    }

    /**
     * 解石开刀: c=jadegamble&m=gambling
     */
    public static function gambling(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $stone = Redis::getJson('jadegamble_stone:' . $uid);

        if (!is_array($stone)) {
            return ['status' => 0, 'message' => '请先选购一块原石毛料'];
        }

        if (!empty($stone['is_cut'])) {
            return ['status' => 0, 'message' => '该原石已经切开，请收取翡翠'];
        }

        // 随机解石倍率与翡翠品相
        $rand = mt_rand(1, 100);
        $outcome = match (true) {
            $rand <= 15 => ['title' => '绝世帝王绿满色翡翠', 'multiplier' => 3.5, 'is_jackpot' => 1],
            $rand <= 45 => ['title' => '冰种春带彩高翠', 'multiplier' => 2.0, 'is_jackpot' => 0],
            $rand <= 80 => ['title' => '细糯种苹果绿翡翠', 'multiplier' => 1.5, 'is_jackpot' => 0],
            default     => ['title' => '豆种晴水翡翠', 'multiplier' => 1.1, 'is_jackpot' => 0],
        };

        $finalValue = (int)round((int)$stone['base_val'] * $outcome['multiplier']);

        $stone['is_cut'] = 1;
        $stone['cut_result'] = $outcome['title'];
        $stone['final_value'] = $finalValue;
        Redis::setJson('jadegamble_stone:' . $uid, $stone, 86400 * 2);

        if (!empty($outcome['is_jackpot'])) {
            $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
            $pName = $member['nickname'] ?? ('主公' . $uid);
            Broadcaster::broadcast('marquee', [
                'content' => "一刀暴富！主公【{$pName}】于赌石坊一刀切出【{$outcome['title']}】，身价瞬间暴涨！",
                'type' => 'system_broadcast',
            ]);
        }

        return [
            'status' => 1,
            'result' => $outcome['title'],
            'multiplier' => $outcome['multiplier'],
            'final_value' => $finalValue,
            'message' => "一刀见绿！喜提【{$outcome['title']}】，估价高达 {$finalValue} 银两！",
        ];
    }

    /**
     * 收取翡翠折现: c=jadegamble&m=harvest
     */
    public static function harvest(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $stone = Redis::getJson('jadegamble_stone:' . $uid);

        if (!is_array($stone) || empty($stone['is_cut'])) {
            return ['status' => 0, 'message' => '没有切开的翡翠可收取'];
        }

        $silver = (int)$stone['final_value'];
        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        $newSilver = (int)($member['silver'] ?? 0) + $silver;

        DB::table('member')->where('uid', $uid)->update(['silver' => $newSilver]);
        Redis::del('jadegamble_stone:' . $uid);
        Redis::del('player:' . $uid);

        return [
            'status' => 1,
            'silver' => $silver,
            'all_silver' => $newSilver,
            'message' => "成功出让翡翠玉石，变现入袋 {$silver} 银两！",
        ];
    }
}
