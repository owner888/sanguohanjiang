<?php

declare(strict_types=1);

namespace Hanjiang\Handler;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use OmniPHP\WebSocket\Broadcaster;

final class MineHandler
{
    /**
     * 矿山品质配置
     *
     * @var array<int, array<string, mixed>>
     */
    public const array MINES = [
        1 => ['id' => 1, 'name' => '微型普通银矿', 'rate' => 10, 'yield_desc' => '10银两/秒'],
        2 => ['id' => 2, 'name' => '小型丰盛银矿', 'rate' => 25, 'yield_desc' => '25银两/秒'],
        3 => ['id' => 3, 'name' => '大型富饶银矿', 'rate' => 50, 'yield_desc' => '50银两/秒'],
        4 => ['id' => 4, 'name' => '绝世极品赤金矿', 'rate' => 100, 'yield_desc' => '100银两/秒'],
    ];

    /**
     * 银矿主界面: c=mine&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $mineState = Redis::getJson('user_mine:' . $uid) ?: [
            'mine_id' => 0,
            'start_time' => 0,
        ];

        $mineId = (int)$mineState['mine_id'];
        $startTime = (int)$mineState['start_time'];

        $elapsed = ($mineId > 0 && $startTime > 0) ? max(0, time() - $startTime) : 0;
        $rate = self::MINES[$mineId]['rate'] ?? 0;
        $accumulatedSilver = $elapsed * $rate;

        return [
            'status' => 1,
            'my_mine' => $mineId,
            'mine_name' => self::MINES[$mineId]['name'] ?? '未占领任何矿区',
            'elapsed' => $elapsed,
            'accumulated_silver' => $accumulatedSilver,
            'mines' => self::MINES,
            'message' => 'ok',
        ];
    }

    /**
     * 占领/开始采矿: c=mine&m=zhanling 或 m=caikuang
     */
    public static function zhanling(Context $ctx): array
    {
        return self::caikuang($ctx);
    }

    public static function caikuang(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $mineId = (int)($ctx->input('mine_id', $ctx->input('id', 1)));

        if (!isset(self::MINES[$mineId])) {
            return ['status' => 0, 'message' => '无效的银矿编号'];
        }

        $mineConfig = self::MINES[$mineId];

        // 占领新矿
        Redis::setJson('user_mine:' . $uid, [
            'mine_id' => $mineId,
            'start_time' => time(),
        ], 86400 * 7);

        if ($mineId >= 3) {
            $member = DB::table('member')->where('uid', $uid)->first();
            $name = $member['nickname'] ?? ('主公' . $uid);
            Broadcaster::broadcast('marquee', [
                'content' => "勘探奇迹！主公【{$name}】挥师入驻【{$mineConfig['name']}】，大举开采银两！",
                'type' => 'system_broadcast',
            ]);
        }

        return [
            'status' => 1,
            'mine_id' => $mineId,
            'mine_name' => $mineConfig['name'],
            'rate' => $mineConfig['rate'],
            'message' => "成功占领【{$mineConfig['name']}】，开始源源不断开采产出银两！",
        ];
    }

    /**
     * 收取开采银两: c=mine&m=get_silver
     */
    public static function get_silver(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $mineState = Redis::getJson('user_mine:' . $uid);

        if (!is_array($mineState) || empty($mineState['mine_id'])) {
            return ['status' => 0, 'message' => '您当前未占领任何银矿'];
        }

        $mineId = (int)$mineState['mine_id'];
        $startTime = (int)$mineState['start_time'];
        $elapsed = max(1, time() - $startTime);
        $rate = self::MINES[$mineId]['rate'] ?? 10;
        $earnedSilver = $elapsed * $rate;

        // 增加玩家银两并重置计时
        $member = DB::table('member')->where('uid', $uid)->first();
        $newSilver = (int)($member['silver'] ?? 0) + $earnedSilver;

        DB::table('member')->where('uid', $uid)->update(['silver' => $newSilver]);
        Redis::del('player:' . $uid);

        // 重置开采时间
        Redis::setJson('user_mine:' . $uid, [
            'mine_id' => $mineId,
            'start_time' => time(),
        ], 86400 * 7);

        return [
            'status' => 1,
            'silver' => $earnedSilver,
            'all_silver' => $newSilver,
            'message' => "收获颇丰！成功提取开采银两 {$earnedSilver}！",
        ];
    }

    /**
     * 放弃/退出矿区: c=mine&m=give_up
     */
    public static function give_up(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        Redis::del('user_mine:' . $uid);

        return [
            'status' => 1,
            'message' => '已撤军放弃当前矿位',
        ];
    }
}
