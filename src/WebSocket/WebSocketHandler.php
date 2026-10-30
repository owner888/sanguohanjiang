<?php

declare(strict_types=1);

namespace Hanjiang\WebSocket;

use OmniPHP\Database\DB;
use OmniPHP\Cache\Redis;
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

        // 除心跳与鉴权外，所有事件必须先通过 auth 绑定 uid
        if (!in_array($action, ['ping', 'auth', 'login'], true) && (int)($ctx->uid() ?? 0) <= 0) {
            $ctx->emit('auth_fail', [
                'message' => '鉴权失败，Token 无效或已过期',
            ]);
            return;
        }

        match ($action) {
            'ping' => self::handlePing($ctx),
            'auth', 'login' => self::handleAuth($ctx),
            'chat' => self::handleChat($ctx),
            'marquee' => self::handleMarquee($ctx),
            'teamlist' => self::handleTeamList($ctx),
            'createTeam' => self::handleCreateTeam($ctx),
            'jointeam' => self::handleJoinTeam($ctx),
            'exitteam', 'fireteam' => self::handleExitTeam($ctx),
            default => $ctx->error("未知的事件类型: {$action}", 404),
        };
    }

    public static function handlePing(Context $ctx): void
    {
        $ctx->emit('pong', [
            'time' => time(),
        ]);
    }

    public static function handleTeamList(Context $ctx): void
    {
        $fid = (int)($ctx->input('id', 1));
        $teams = Redis::getJson("elite_teams:{$fid}") ?: [];
        $ctx->emit('teamlist', [
            'list' => array_values($teams),
            'firstkill' => 0,
            'exp' => 500,
            'itemname' => '百炼强化玄铁',
            'quality' => 3,
            'getrate' => 100,
        ]);
    }

    public static function handleCreateTeam(Context $ctx): void
    {
        $uid = (int)$ctx->uid();
        $fid = (int)($ctx->input('id', 1));
        $type = (int)($ctx->input('type', 1));
        $topteam = (bool)($ctx->input('topteam', false));

        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        $nickname = (string)($member['nickname'] ?? ('主公' . $uid));

        $teamId = $uid;
        $team = [
            'teamid' => $teamId,
            'name' => "{$nickname}的队伍",
            'num' => 1,
            'countryid' => 0,
            'countryname' => '',
            'leader' => $uid,
            'topteam' => $topteam ? 1 : 0,
            'list' => [
                ['uid' => $uid, 'nickname' => $nickname, 'level' => (int)($member['level'] ?? 1), 'order' => 1],
            ],
        ];

        $teams = Redis::getJson("elite_teams:{$fid}") ?: [];
        $teams[$teamId] = $team;
        Redis::setJson("elite_teams:{$fid}", $teams, 86400);

        $ctx->emit('teamdetail', $team);
        Broadcaster::broadcast('teamlist', [
            'list' => array_values($teams),
        ]);
    }

    public static function handleJoinTeam(Context $ctx): void
    {
        $uid = (int)$ctx->uid();
        $fid = (int)($ctx->input('id', 1));
        $teamId = (int)($ctx->input('teamid', 0));

        $teams = Redis::getJson("elite_teams:{$fid}") ?: [];
        if (!isset($teams[$teamId])) {
            $ctx->emit('error', ['message' => '队伍不存在']);
            return;
        }

        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        $nickname = (string)($member['nickname'] ?? ('主公' . $uid));

        $team = $teams[$teamId];
        $team['num'] = count($team['list']) + 1;
        $team['list'][] = [
            'uid' => $uid,
            'nickname' => $nickname,
            'level' => (int)($member['level'] ?? 1),
            'order' => $team['num'],
        ];

        $teams[$teamId] = $team;
        Redis::setJson("elite_teams:{$fid}", $teams, 86400);

        $ctx->emit('teamdetail', $team);
        Broadcaster::broadcast('teamlist', [
            'list' => array_values($teams),
        ]);
    }

    public static function handleExitTeam(Context $ctx): void
    {
        $uid = (int)$ctx->uid();
        $fid = (int)($ctx->input('id', 1));
        $teams = Redis::getJson("elite_teams:{$fid}") ?: [];
        unset($teams[$uid]);
        Redis::setJson("elite_teams:{$fid}", $teams, 86400);

        $ctx->emit('exitteam', ['status' => 1]);
        Broadcaster::broadcast('teamlist', [
            'list' => array_values($teams),
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
    }

    public static function handleChat(Context $ctx): void
    {
        $uid = (int)$ctx->uid();
        $name = (string)$ctx->input('name', '');
        if ($name === '' && $uid > 0) {
            $member = DB::table('member')->where('uid', $uid)->first();
            $name = (string)($member['nickname'] ?? ('主公' . $uid));
        }
        if ($name === '') {
            $name = '无名主公';
        }

        $msg = trim((string)$ctx->input('message', ''));
        if ($msg === '') {
            $ctx->error('聊天内容不能为空');
            return;
        }

        $chatMsg = [
            'uid' => $uid,
            'nickname' => $name,
            'name' => $name,
            'type' => 0,
            'system' => 0,
            'message' => htmlspecialchars($msg),
            'time' => time(),
        ];

        $ctx->broadcast('chat_message', $chatMsg);
    }

    public static function handleMarquee(Context $ctx): void
    {
        $content = trim((string)$ctx->input('content', $ctx->input('message', '')));
        if ($content !== '') {
            Broadcaster::broadcast('marquee', [
                'content' => $content,
                'type' => 'system_broadcast',
            ]);

            // 存储历史
            $history = Redis::getJson('broadcast_history') ?: [];
            $history[] = [
                'type' => 'marquee',
                'data' => ['content' => $content],
                'time' => time(),
            ];
            if (count($history) > 50) {
                $history = array_slice($history, -50);
            }
            Redis::setJson('broadcast_history', $history, 86400);
        }
    }
}
