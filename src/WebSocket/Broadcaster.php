<?php

declare(strict_types=1);

namespace Hanjiang\WebSocket;

use Hanjiang\Database\Redis;
use OmniPHP\WebSocket\Broadcaster as BaseBroadcaster;

/**
 * 游戏业务层消息广播器
 *
 * 继承自 OmniPHP 框架官方 Broadcaster 连接管理池，扩展游戏专属的 Redis 跑马灯历史缓存。
 */
class Broadcaster extends BaseBroadcaster
{
    /**
     * 向全服在线客户端广播消息并持久化到 Redis
     *
     * @param array<string, mixed> $data
     */
    public static function broadcast(string $type, array $data): int
    {
        $sentCount = parent::broadcast($type, $data);

        // 存入 Redis 广播历史
        $history = Redis::getJson('broadcast_history') ?: [];
        $history[] = [
            'type' => $type,
            'data' => $data,
            'time' => time(),
        ];
        if (count($history) > 50) {
            $history = array_slice($history, -50);
        }
        Redis::setJson('broadcast_history', $history, 86400);

        return $sentCount;
    }

    /**
     * 发送全服跑马灯大喇叭公告
     */
    public static function notifyMarquee(string $message): void
    {
        self::broadcast('marquee', [
            'content' => $message,
            'type' => 'system_broadcast',
        ]);
    }
}
