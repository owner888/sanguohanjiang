<?php

declare(strict_types=1);

namespace Hanjiang\WebSocket;

use Hanjiang\Database\Redis;
use Workerman\Connection\TcpConnection;

final class Broadcaster
{
    /** @var array<int, TcpConnection> connection_id => connection */
    private static array $connections = [];

    /** @var array<int, int> uid => connection_id */
    private static array $uidToConnId = [];

    /**
     * 注册客户端连接
     */
    public static function register(TcpConnection $connection, ?int $uid = null): void
    {
        $connId = $connection->id;
        self::$connections[$connId] = $connection;
        if ($uid !== null && $uid > 0) {
            self::$uidToConnId[$uid] = $connId;
        }
    }

    /**
     * 绑定连接与玩家 UID
     */
    public static function bindUid(TcpConnection $connection, int $uid): void
    {
        $connId = $connection->id;
        self::$connections[$connId] = $connection;
        self::$uidToConnId[$uid] = $connId;
    }

    /**
     * 注销连接
     */
    public static function unregister(TcpConnection $connection): void
    {
        $connId = $connection->id;
        unset(self::$connections[$connId]);

        foreach (self::$uidToConnId as $uid => $cid) {
            if ($cid === $connId) {
                unset(self::$uidToConnId[$uid]);
                break;
            }
        }
    }

    /**
     * 向全服在线客户端广播消息
     *
     * @param array<string, mixed> $data
     */
    public static function broadcast(string $type, array $data): int
    {
        $payload = json_encode([
            'type' => $type,
            'data' => $data,
            'timestamp' => time(),
        ], JSON_UNESCAPED_UNICODE);

        $sentCount = 0;
        foreach (self::$connections as $conn) {
            $conn->send($payload);
            $sentCount++;
        }

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

    /**
     * 向指定玩家单播消息
     *
     * @param array<string, mixed> $data
     */
    public static function sendToUid(int $uid, string $type, array $data): bool
    {
        $connId = self::$uidToConnId[$uid] ?? null;
        if ($connId === null || !isset(self::$connections[$connId])) {
            return false;
        }

        $payload = json_encode([
            'type' => $type,
            'data' => $data,
            'timestamp' => time(),
        ], JSON_UNESCAPED_UNICODE);

        self::$connections[$connId]->send($payload);
        return true;
    }

    /**
     * 获取当前 Worker 进程内在线连接总数
     */
    public static function count(): int
    {
        return count(self::$connections);
    }
}
