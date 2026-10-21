<?php

declare(strict_types=1);

namespace Hanjiang\Handler;

use OmniPHP\Http\Context;
use OmniPHP\Database\DB;
use OmniPHP\Cache\Redis;

final class TavernHandler
{
    /**
     * 酒馆常驻名将池配置
     *
     * @var array<int, array<string, mixed>>
     */
    public const array HERO_POOL = [
        10 => [
            'id' => 10,
            'name' => '张飞',
            'quality' => 3,
            'cost_silver' => 5000,
            'wuli' => 98,
            'zhili' => 45,
            'hp' => 1800,
            'attack' => 360,
            'defense' => 130,
            'skill' => '万夫莫敌',
        ],
        11 => [
            'id' => 11,
            'name' => '关羽',
            'quality' => 4,
            'cost_silver' => 10000,
            'wuli' => 96,
            'zhili' => 75,
            'hp' => 2000,
            'attack' => 400,
            'defense' => 150,
            'skill' => '青龙斩',
        ],
        12 => [
            'id' => 12,
            'name' => '诸葛亮',
            'quality' => 5,
            'cost_silver' => 20000,
            'wuli' => 55,
            'zhili' => 100,
            'hp' => 1600,
            'attack' => 380,
            'defense' => 140,
            'skill' => '东风破',
        ],
        13 => [
            'id' => 13,
            'name' => '赵云',
            'quality' => 4,
            'cost_silver' => 15000,
            'wuli' => 95,
            'zhili' => 78,
            'hp' => 1900,
            'attack' => 390,
            'defense' => 145,
            'skill' => '一身是胆',
        ],
    ];

    /**
     * 酒馆主界面: c=tavern&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $ownedGenerals = DB::table('general')->where('uid', $uid)->get();
        $ownedMap = [];
        foreach ($ownedGenerals as $g) {
            $ownedMap[(int)$g['generalid']] = true;
        }

        $list = [];
        $idx = 1;
        foreach (self::HERO_POOL as $hero) {
            $gid = (int)$hero['id'];
            $list[$idx] = array_merge($hero, [
                'is_recruited' => isset($ownedMap[$gid]) ? 1 : 0,
            ]);
            $idx++;
        }

        return [
            'status' => 1,
            'list' => $list,
            'jade' => 0,
            'message' => 'ok',
        ];
    }

    /**
     * 可招募武将列表: c=tavern&m=get_list
     */
    public static function get_list(Context $ctx): array
    {
        return self::index($ctx);
    }

    /**
     * 招募将领: c=tavern&m=buy
     */
    public static function buy(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $gid = (int)($ctx->input('id', $ctx->input('generalid', 10)));

        if (!isset(self::HERO_POOL[$gid])) {
            return [
                'status' => 0,
                'message' => '未找到该武将招募信息',
            ];
        }

        $heroConfig = self::HERO_POOL[$gid];
        $costSilver = (int)$heroConfig['cost_silver'];

        // 检查是否已经拥有
        $exist = DB::table('general')
            ->where('uid', $uid)
            ->where('generalid', $gid)
            ->first();

        if ($exist !== null) {
            return [
                'status' => 4,
                'message' => '已拥有该武将，不可重复招募',
            ];
        }

        // 检查银两
        $member = DB::table('member')->where('uid', $uid)->first();
        // 原子扣除银两 (防并发超招)
        if (!MemberHandler::deductCurrency($uid, silver: $costSilver)) {
            return [
                'status' => 3,
                'message' => '银两不足，招募需要 ' . $costSilver . ' 银两',
            ];
        }

        $remainSilver = max(0, (int)$member['silver'] - $costSilver);

        // 插入武将
        $newGenId = DB::table('general')->insert([
            'uid' => $uid,
            'generalid' => $gid,
            'name' => $heroConfig['name'],
            'level' => 1,
            'exp' => 0,
            'pos' => 0, // 初始在后备营
            'matrix_id' => 1,
            'status' => 1,
        ]);

        Redis::del('player:' . $uid);

        $nickname = $member['nickname'] ?? ('主公' . $uid);
        \OmniPHP\WebSocket\Broadcaster::broadcast('marquee', [
            'content' => "恭喜主公【{$nickname}】在酒馆成功招募名将【{$heroConfig['name']}】！",
            'type' => 'system_broadcast',
        ]);

        return [
            'status' => 1,
            'message' => '成功招募名将【' . $heroConfig['name'] . '】！',
            'id' => $newGenId,
            'generalid' => $gid,
            'name' => $heroConfig['name'],
            'cost_silver' => $costSilver,
            'price' => $costSilver,
            'type' => 1,
            'silver' => $remainSilver,
        ];
    }

    /**
     * 会客商人买卖: c=tavern&m=trade
     */
    public static function trade(Context $ctx): array
    {
        return [
            'status' => 1,
            'message' => '交易成功',
        ];
    }
}
