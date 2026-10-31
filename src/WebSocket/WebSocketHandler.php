<?php

declare(strict_types=1);

namespace Hanjiang\WebSocket;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\WebSocket\Broadcaster;
use OmniPHP\WebSocket\Context;
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
    }

    /**
     * WebSocket 握手正式完成事件（协议级 Upgrade 成功）
     * 只有在此阶段，才能向客户端发送 WebSocket 数据帧，彻底根治浏览器 1006 异常断开与无限重连！
     */
    public static function onWebSocketConnect(TcpConnection $connection, mixed $httpHeader = null): void
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
        EliteRoom::onClose($connection);
        ChatPush::onClose($connection);
    }

    public static function onMessage(TcpConnection $connection, string $data): void
    {
        self::touch($connection);

        $ctx = new Context($connection, $data);
        self::dispatch($ctx);
    }

    /**
     * 基于 OmniPHP 官方 WebSocket Context 的事件分发流水线
     */
    public static function dispatch(Context $ctx): void
    {
        $action = $ctx->action();
        if ($action === '') {
            $ctx->error('未指定事件动作 action');
            return;
        }

        // 多人副本组队事件（elitepop.js 经 socket.io.min.js 适配层发来，自带 login 鉴权）
        if (EliteRoom::handles($action)) {
            EliteRoom::dispatch($ctx);
            return;
        }

        // 除心跳与鉴权外，所有事件必须先通过 auth 绑定 uid
        if (!in_array($action, ['ping', 'auth'], true) && (int)($ctx->uid() ?? 0) <= 0) {
            $ctx->emit('auth_fail', [
                'message' => '鉴权失败，Token 无效或已过期',
            ]);
            return;
        }

        match ($action) {
            'ping' => self::handlePing($ctx),
            'auth' => self::handleAuth($ctx),
            default => $ctx->error("未知的事件类型: {$action}", 404),
        };
    }

    public static function handlePing(Context $ctx): void
    {
        $ctx->emit('pong', [
            'time' => time(),
        ]);
    }

    public static function handleAuth(Context $ctx): void
    {
        $token = trim((string)$ctx->input('token', ''));
        $cached = $token !== '' ? Redis::get('token:' . $token) : null;
        $uid = $cached !== null ? (int)$cached : 0;

        if ($uid <= 0) {
            $ctx->emit('auth_fail', [
                'message' => '鉴权失败，Token 无效或已过期',
            ]);
            return;
        }

        $ctx->bindUid($uid);

        $member = DB::table('member')->where('uid', $uid)->first();
        $nickname = $member['nickname'] ?? ('主公' . $uid);

        $ctx->emit('auth_success', [
            'uid' => $uid,
            'nickname' => $nickname,
            'message' => "欢迎上线，{$nickname}！",
        ]);

        // 聊天室：鉴权后由本进程推送该玩家的消息（客户端不再轮询 c=chat&m=read）
        ChatPush::bind($ctx, $uid, (string)$nickname);
    }
}
