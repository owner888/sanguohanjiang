<?php

declare(strict_types=1);

namespace Hanjiang\WebSocket;

use Hanjiang\Database\DB;
use Hanjiang\Database\Redis;
use Workerman\Connection\TcpConnection;

final class WebSocketHandler
{
    public static function onConnect(TcpConnection $connection): void
    {
        Broadcaster::register($connection);
        $connection->send(json_encode([
            'type' => 'connected',
            'message' => '悍将三国 WebSocket 实时网关连接成功',
            'time' => time(),
        ], JSON_UNESCAPED_UNICODE));
    }

    public static function onClose(TcpConnection $connection): void
    {
        Broadcaster::unregister($connection);
    }

    public static function onMessage(TcpConnection $connection, string $data): void
    {
        $payload = json_decode($data, true);
        if (!is_array($payload)) {
            $connection->send(json_encode([
                'type' => 'error',
                'message' => '无效的 JSON 格式',
            ], JSON_UNESCAPED_UNICODE));
            return;
        }

        $action = (string)($payload['action'] ?? $payload['type'] ?? 'ping');

        match ($action) {
            'ping' => $connection->send(json_encode([
                'action' => 'pong',
                'time' => time(),
            ], JSON_UNESCAPED_UNICODE)),

            'auth' => self::handleAuth($connection, $payload),

            'chat' => self::handleChat($connection, $payload),

            'marquee' => self::handleMarquee($connection, $payload),

            default => $connection->send(json_encode([
                'type' => 'unknown_action',
                'action' => $action,
            ], JSON_UNESCAPED_UNICODE)),
        };
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function handleAuth(TcpConnection $connection, array $payload): void
    {
        $token = (string)($payload['token'] ?? '');
        $uid = (int)($payload['uid'] ?? 0);

        if ($token !== '') {
            $session = Redis::getJson('token:' . $token);
            if (is_array($session) && isset($session['uid'])) {
                $uid = (int)$session['uid'];
            }
        }

        if ($uid <= 0) {
            $connection->send(json_encode([
                'type' => 'auth_fail',
                'message' => '鉴权失败，Token 无效或已过期',
            ], JSON_UNESCAPED_UNICODE));
            return;
        }

        Broadcaster::bindUid($connection, $uid);

        $member = DB::table('member')->where('uid', $uid)->first();
        $nickname = $member['nickname'] ?? ('主公' . $uid);

        $connection->send(json_encode([
            'type' => 'auth_success',
            'uid' => $uid,
            'nickname' => $nickname,
            'message' => "欢迎上线，{$nickname}！",
        ], JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function handleChat(TcpConnection $connection, array $payload): void
    {
        $uid = (int)($payload['uid'] ?? 0);
        $name = (string)($payload['name'] ?? '无名主公');
        $msg = trim((string)($payload['message'] ?? ''));

        if ($msg === '') {
            return;
        }

        Broadcaster::broadcast('chat_message', [
            'uid' => $uid,
            'name' => $name,
            'message' => htmlspecialchars($msg),
            'time' => time(),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function handleMarquee(TcpConnection $connection, array $payload): void
    {
        $content = trim((string)($payload['content'] ?? $payload['message'] ?? ''));
        if ($content !== '') {
            Broadcaster::notifyMarquee($content);
        }
    }
}
