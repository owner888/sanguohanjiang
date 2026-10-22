<?php

declare(strict_types=1);

namespace Hanjiang\Handler\General;

use Hanjiang\Handler\City\MemberHandler;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

final class CultivateHandler
{
    public const array MODES = [
        1 => ['id' => 1, 'name' => '普通培养', 'cost_silver' => 2000, 'cost_gold' => 0, 'min' => -2, 'max' => 5],
        2 => ['id' => 2, 'name' => '加强培养', 'cost_silver' => 2000, 'cost_gold' => 2, 'min' => 0, 'max' => 7],
        3 => ['id' => 3, 'name' => '白金培养', 'cost_silver' => 0, 'cost_gold' => 10, 'min' => 2, 'max' => 10],
        4 => ['id' => 4, 'name' => '至尊培养', 'cost_silver' => 0, 'cost_gold' => 50, 'min' => 5, 'max' => 15],
    ];

    /**
     * 洗髓培养主界面: c=cultivate&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $gid = (int)($ctx->input('gid', 0));

        if ($gid <= 0) {
            $firstGen = DB::table('general')->where('uid', $uid)->first();
            $gid = (int)($firstGen['id'] ?? 1);
        }

        $general = DB::table('general')->where('uid', $uid)->where('id', $gid)->first();
        if ($general === null) {
            return ['status' => -1, 'message' => '武将不存在'];
        }

        $pending = Redis::getJson("cultivate_pending:{$uid}:{$gid}");

        return [
            'status' => 1,
            'gid' => $gid,
            'name' => $general['name'],
            'current_attrs' => [
                'wuliup' => (int)($general['wuliup'] ?? 0),
                'zhiliup' => (int)($general['zhiliup'] ?? 0),
                'tiliup' => (int)($general['tiliup'] ?? 0),
            ],
            'pending_roll' => $pending,
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
        $gid = (int)($ctx->input('gid', 1));
        $mode = (int)($ctx->input('mode', 1));

        $general = DB::table('general')->where('uid', $uid)->where('id', $gid)->first();
        if ($general === null) {
            return ['status' => -1, 'message' => '武将不存在'];
        }

        $config = self::MODES[$mode] ?? self::MODES[1];
        $member = DB::table('member')->where('uid', $uid)->first();

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

        // 扣除货币
        DB::table('member')->where('uid', $uid)->update([
            'silver' => $curSilver - $costSilver,
            'gold' => $curGold - $costGold,
        ]);
        Redis::del('player:' . $uid);

        // 随机生成三围洗髓增量
        $wuliAdd = mt_rand($config['min'], $config['max']);
        $zhiliAdd = mt_rand($config['min'], $config['max']);
        $tiliAdd = mt_rand($config['min'], $config['max']);

        $pending = [
            'wuli' => $wuliAdd,
            'zhili' => $zhiliAdd,
            'tili' => $tiliAdd,
            'time' => time(),
        ];

        // 暂存至 Redis 等待玩家决定是否保存
        Redis::setJson("cultivate_pending:{$uid}:{$gid}", $pending, 3600);

        return [
            'status' => 1,
            'gid' => $gid,
            'mode' => $mode,
            'roll' => $pending,
            'silver' => $curSilver - $costSilver,
            'gold' => $curGold - $costGold,
            'message' => "洗髓成功！武力 {$wuliAdd}、智力 {$zhiliAdd}、体力 {$tiliAdd}，是否确认保存？",
        ];
    }

    /**
     * 确认保存洗髓属性: c=cultivate&m=save
     */
    public static function save(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $gid = (int)($ctx->input('gid', 1));

        $general = DB::table('general')->where('uid', $uid)->where('id', $gid)->first();
        if ($general === null) {
            return ['status' => -1, 'message' => '武将不存在'];
        }

        $pending = Redis::getJson("cultivate_pending:{$uid}:{$gid}");
        if (!is_array($pending)) {
            return ['status' => 0, 'message' => '没有待保存的洗髓属性，请先执行洗髓'];
        }

        $newWuliUp = max(0, (int)($general['wuliup'] ?? 0) + (int)$pending['wuli']);
        $newZhiliUp = max(0, (int)($general['zhiliup'] ?? 0) + (int)$pending['zhili']);
        $newTiliUp = max(0, (int)($general['tiliup'] ?? 0) + (int)$pending['tili']);

        DB::table('general')->where('id', $gid)->update([
            'wuliup' => $newWuliUp,
            'zhiliup' => $newZhiliUp,
            'tiliup' => $newTiliUp,
        ]);

        Redis::del("cultivate_pending:{$uid}:{$gid}");
        Redis::del('player:' . $uid);

        return [
            'status' => 1,
            'gid' => $gid,
            'wuliup' => $newWuliUp,
            'zhiliup' => $newZhiliUp,
            'tiliup' => $newTiliUp,
            'message' => '洗髓属性已永久注入武将体内！',
        ];
    }
}
