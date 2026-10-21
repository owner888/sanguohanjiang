<?php

declare(strict_types=1);

namespace Hanjiang\WebSocket;

use Hanjiang\Database\DB;
use Hanjiang\Database\Redis;
use Workerman\Connection\TcpConnection;
use Workerman\Timer;

final class WebSocketHandler
{
    private static ?int $sweepTimerId = null;

    /**
     * 初始化定时器任务：定时清理超时僵尸连接 (对齐 smart-book 标准)
     */
    public static function init(int $sweepInterval = 15): void
    {
        if (self::$sweepTimerId !== null) {
            Timer::del(self::$sweepTimerId);
            self::$sweepTimerId = null;
        }

        if (class_exists(Timer::class)) {
            self::$sweepTimerId = Timer::add($sweepInterval, static function (): void {
                Broadcaster::sweep();
            });
        }
    }

    /**
     * 刷新连接最后心跳活跃时间戳
     */
    public static function touch(TcpConnection $connection): void
    {
        Broadcaster::touch($connection);
    }

    /**
     * WebSocket RFC 6455 协议级 ping（操作码 0x9）：
     * 对齐 smart-book 标准，注册此回调后 Workerman 将 $connection->websocketType 置为 pong 帧（\x8a），
     * 由 handler 刷新活跃时间戳并原样将 $pingData 作为 pong 响应载荷发回客户端。
     */
    public static function onWebSocketPing(TcpConnection $connection, string $pingData): void
    {
        self::touch($connection);
        $connection->send($pingData);
    }

    public static function onConnect(TcpConnection $connection): void
    {
        self::touch($connection);
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
        self::touch($connection);

        $ctx = new WsContext($connection, $data);
        self::dispatch($ctx);
    }

    /**
     * 基于 WsContext 的全双工事件分发流水线 (完全对齐 Handler + Context 架构)
     */
    public static function dispatch(WsContext $ctx): void
    {
        $action = $ctx->action();
        if ($action === '') {
            $ctx->error('未指定事件动作 action');
            return;
        }

        match ($action) {
            'auth' => self::handleAuth($ctx),
            'chat' => self::handleChat($ctx),
            'marquee' => self::handleMarquee($ctx),
            default => $ctx->error("未知的事件类型: {$action}", 404),
        };
    }

    public static function handleAuth(WsContext $ctx): void
    {
        $token = (string)$ctx->input('token', '');
        $uid = (int)$ctx->input('uid', 0);

        if ($token !== '') {
            $session = Redis::getJson('token:' . $token);
            if (is_array($session) && isset($session['uid'])) {
                $uid = (int)$session['uid'];
            }
        }

        if ($uid <= 0) {
            $ctx->emit('auth_fail', [
                'message' => '鉴权失败，Token 无效或已过期',
            ]);
            return;
        }

        $ctx->bindUid($uid);

        $member = $ctx->member();
        $nickname = $member['nickname'] ?? ('主公' . $uid);

        $ctx->emit('auth_success', [
            'uid' => $uid,
            'nickname' => $nickname,
            'message' => "欢迎上线，{$nickname}！",
        ]);
    }

    public static function handleChat(WsContext $ctx): void
    {
        $uid = $ctx->uid() ?? (int)$ctx->input('uid', 0);
        $name = (string)$ctx->input('name', '');
        if ($name === '' && $ctx->member() !== null) {
            $name = (string)($ctx->member()['nickname'] ?? ('主公' . $uid));
        }
        if ($name === '') {
            $name = '无名主公';
        }

        $msg = trim((string)$ctx->input('message', ''));
        if ($msg === '') {
            $ctx->error('聊天内容不能为空');
            return;
        }

        $ctx->broadcast('chat_message', [
            'uid' => $uid,
            'name' => $name,
            'message' => htmlspecialchars($msg),
            'time' => time(),
        ]);
    }

    public static function handleMarquee(WsContext $ctx): void
    {
        $content = trim((string)$ctx->input('content', $ctx->input('message', '')));
        if ($content !== '') {
            Broadcaster::notifyMarquee($content);
        }
    }
}
