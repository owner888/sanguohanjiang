<?php

declare(strict_types=1);

namespace Hanjiang\Handler\General;

use Hanjiang\Handler\City\MemberHandler;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 武将洗髓培养、属性重铸与潜力激发处理器 (DDD General Domain)
 */
final class CultivateHandler
{
    public const array MODES = [
        1 => ['id' => 1, 'name' => '普通培养', 'silver' => 2000, 'gold' => 0, 'cost_silver' => 2000, 'cost_gold' => 0, 'min' => -2, 'max' => 5],
        2 => ['id' => 2, 'name' => '加强培养', 'silver' => 2000, 'gold' => 2, 'cost_silver' => 2000, 'cost_gold' => 2, 'min' => 0, 'max' => 7],
        3 => ['id' => 3, 'name' => '白金培养', 'silver' => 0, 'gold' => 10, 'cost_silver' => 0, 'cost_gold' => 10, 'min' => 2, 'max' => 10],
        4 => ['id' => 4, 'name' => '至尊培养', 'silver' => 0, 'gold' => 50, 'cost_silver' => 0, 'cost_gold' => 50, 'min' => 5, 'max' => 15],
    ];

    /**
     * 洗髓培养主界面: c=cultivate&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $generals = DB::table('general')->where('uid', $uid)->whereNull('deleted_at')->get();

        if (empty($generals)) {
            return ['status' => 2, 'message' => '没有出征将领'];
        }

        $list = [];
        $idx = 0;
        foreach ($generals as $g) {
            $gid = (int)$g['id'];
            $image = (string)($g['image'] ?? 'hero_1');
            if (empty($image)) {
                $image = 'hero_' . (int)$g['generalid'];
            }
            $pending = Redis::getJson("cultivate_pending:{$uid}:{$gid}") ?: [];

            $list[$idx] = [
                'id' => $gid,
                'name' => (string)$g['name'],
                'quality' => (int)($g['quality'] ?? 3),
                'level' => (int)($g['level'] ?? 1),
                'image' => $image,
                'type' => (int)($g['type'] ?? 1),
                'wuli' => (int)($g['wuli'] ?? 80),
                'zhili' => (int)($g['zhili'] ?? 80),
                'tili' => (int)($g['tili'] ?? 80),
                'wuliup' => (int)($g['wuliup'] ?? 0),
                'zhiliup' => (int)($g['zhiliup'] ?? 0),
                'tiliup' => (int)($g['tiliup'] ?? 0),
                'star' => (int)($g['star'] ?? 3),
                'goout' => 1,
                'cultivate' => $pending,
            ];
            $idx++;
        }

        $rule = [
            1 => ['id' => 1, 'name' => '普通培养', 'silver' => 2000],
            2 => ['id' => 2, 'name' => '加强培养', 'silver' => 2000, 'gold' => 2],
            3 => ['id' => 3, 'name' => '白金培养', 'gold' => 10],
            4 => ['id' => 4, 'name' => '至尊培养', 'gold' => 50],
        ];

        $firstGen = $generals[0];
        $firstGid = (int)$firstGen['id'];
        $firstPending = Redis::getJson("cultivate_pending:{$uid}:{$firstGid}");

        return [
            'status' => 1,
            'list' => $list,
            'rule' => $rule,
            // 向下兼容保留原字段
            'gid' => $firstGid,
            'name' => (string)$firstGen['name'],
            'current_attrs' => [
                'wuliup' => (int)($firstGen['wuliup'] ?? 0),
                'zhiliup' => (int)($firstGen['zhiliup'] ?? 0),
                'tiliup' => (int)($firstGen['tiliup'] ?? 0),
            ],
            'pending_roll' => $firstPending,
            'modes' => self::MODES,
            'message' => 'ok',
        ];
    }

    /**
     * 执行洗髓洗点: c=cultivate&m=roll
     */
    public static function roll(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $gid = (int)($ctx->input('id', $ctx->input('gid', 1)));
        $mode = (int)($ctx->input('type', $ctx->input('mode', 1)));

        $general = DB::table('general')->where('uid', $uid)->where('id', $gid)->whereNull('deleted_at')->first();
        if ($general === null) {
            return ['status' => -1, 'message' => '武将不存在'];
        }

        $config = self::MODES[$mode] ?? self::MODES[1];
        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();

        $costSilver = (int)$config['cost_silver'];
        $costGold = (int)$config['cost_gold'];

        $curSilver = (int)($member['silver'] ?? 0);
        $curGold = (int)($member['gold'] ?? 0);

        if ($curSilver < $costSilver) {
            return ['status' => -2, 'message' => "银两不足，洗髓需要 {$costSilver} 银两"];
        }
        if ($curGold < $costGold) {
            return ['status' => -3, 'message' => "元宝不足，洗髓需要 {$costGold} 元宝"];
        }

        if ($costSilver > 0) {
            MemberHandler::deductCurrency($uid, silver: $costSilver);
        }
        if ($costGold > 0) {
            MemberHandler::deductCurrency($uid, gold: $costGold);
        }

        $freshMember = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();

        // 随机生成三围洗髓增量
        $wuliAdd = mt_rand($config['min'], $config['max']);
        $zhiliAdd = mt_rand($config['min'], $config['max']);
        $tiliAdd = mt_rand($config['min'], $config['max']);

        $pending = [
            'wuli' => $wuliAdd,
            'zhili' => $zhiliAdd,
            'tili' => $tiliAdd,
        ];
        Redis::setJson("cultivate_pending:{$uid}:{$gid}", $pending, 86400 * 7);

        return [
            'status' => 1,
            'gid' => $gid,
            'wuli' => $wuliAdd,
            'zhili' => $zhiliAdd,
            'tili' => $tiliAdd,
            'roll' => $pending,
            'silver' => (int)($freshMember['silver'] ?? 0),
            'gold' => (int)($freshMember['gold'] ?? 0),
            'message' => "洗髓完成！武力增减 {$wuliAdd}，智力增减 {$zhiliAdd}，体力增减 {$tiliAdd}",
        ];
    }

    /**
     * 保存/替换洗髓属性: c=cultivate&m=save
     */
    public static function save(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $gid = (int)($ctx->input('id', $ctx->input('gid', 1)));

        $pending = Redis::getJson("cultivate_pending:{$uid}:{$gid}");
        if (!is_array($pending) || !isset($pending['wuli'])) {
            return ['status' => -1, 'message' => '没有待保存的洗髓结果'];
        }

        $general = DB::table('general')->where('uid', $uid)->where('id', $gid)->whereNull('deleted_at')->first();
        if ($general === null) {
            return ['status' => -1, 'message' => '武将不存在'];
        }

        $newWuli = max(0, (int)($general['wuliup'] ?? 0) + (int)$pending['wuli']);
        $newZhili = max(0, (int)($general['zhiliup'] ?? 0) + (int)$pending['zhili']);
        $newTili = max(0, (int)($general['tiliup'] ?? 0) + (int)$pending['tili']);

        DB::table('general')->where('id', $gid)->update([
            'wuliup' => $newWuli,
            'zhiliup' => $newZhili,
            'tiliup' => $newTili,
        ]);

        Redis::del("cultivate_pending:{$uid}:{$gid}");
        Redis::del('player:' . $uid);

        return [
            'status' => 1,
            'gid' => $gid,
            'wuliup' => $newWuli,
            'zhiliup' => $newZhili,
            'tiliup' => $newTili,
            'message' => '属性替换保存成功！大将潜能全面觉醒！',
        ];
    }

    /**
     * 维持原属性/放弃洗髓: c=cultivate&m=cancel
     */
    public static function cancel(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $gid = (int)($ctx->input('id', $ctx->input('gid', 1)));

        Redis::del("cultivate_pending:{$uid}:{$gid}");

        return [
            'status' => 1,
            'gid' => $gid,
            'message' => '已维持武将原有洗髓属性',
        ];
    }
}
