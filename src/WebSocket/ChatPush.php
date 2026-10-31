<?php

declare(strict_types=1);

namespace Hanjiang\WebSocket;

use Hanjiang\Model\Chat;
use OmniPHP\WebSocket\Context;
use Workerman\Connection\TcpConnection;
use Workerman\Timer;

/**
 * 聊天室实时推送（ws 进程侧）
 *
 * 旧版客户端每 5 ~ 30 秒轮询 c=chat&m=read，取走自己私人队列 chat_message_{uid} 里的消息；客户端 cd.js 在
 * GameWS 鉴权成功后不再轮询，由这里代替：
 * - auth 成功：登记连接，刷新在线标记 chat_online_{uid} 并记下所在进程 chat_ws_{uid}（之后每 20 秒刷新一次，
 *   在线标记 60 秒过期同旧版），立即推送积压的消息。
 * - HTTP 进程（或计划任务）往在线玩家的私人队列写消息后，把 uid 放进该玩家所在 ws 进程的通知集合
 *   chat_push_{进程号}（{@see Chat::sendToAll} 等）；本进程每 0.1 秒取出通知，弹出这些玩家的私人队列
 *   （同旧版 read：去掉屏蔽的人发的消息）推送。
 * - 推送事件 chat_message，载荷与 c=chat&m=read 的响应相同：{status: 1, list: [...]}，客户端按 read 的结果渲染。
 * - 连接断开：去掉 chat_ws_{uid}，之后的消息留在私人队列，客户端改回轮询 read 取走（在线标记照旧 60 秒过期）。
 *
 * 每个 ws 进程只推送本进程上的连接；队列弹出是原子的，同一条消息只会经 ws 或 read 之一送达一次。
 */
final class ChatPush
{
    public const string EVENT = 'chat_message';
    /** 取通知的间隔（秒） */
    private const float DRAIN_INTERVAL = 0.1;
    /** 刷新在线标记的间隔（秒，小于 Chat::ONLINE_EXPIRE） */
    private const int REFRESH_INTERVAL = 20;

    private static int $workerId = 0;
    /** @var array<int, array{cid: int, nickname: string, send: \Closure(string): void}> uid => 本进程上的连接 */
    private static array $local = [];
    private static ?int $drainTimer = null;
    private static ?int $refreshTimer = null;

    /**
     * ws 进程启动：清掉上次运行残留的通知，启动取通知 / 刷新在线标记的定时器
     */
    public static function init(int $workerId): void
    {
        self::$workerId = $workerId;
        self::$local = [];
        Chat::resetPush($workerId);
        foreach ([self::$drainTimer, self::$refreshTimer] as $timer) {
            if ($timer !== null) {
                Timer::del($timer);
            }
        }
        self::$drainTimer = Timer::add(self::DRAIN_INTERVAL, static function (): void {
            self::drain();
        });
        self::$refreshTimer = Timer::add(self::REFRESH_INTERVAL, static function (): void {
            self::refresh();
        });
    }

    /**
     * 测试用：设定本进程号并清空登记（不启动定时器）
     */
    public static function reset(int $workerId = 0): void
    {
        self::$workerId = $workerId;
        self::$local = [];
    }

    /**
     * auth 成功：登记该玩家的连接（同一玩家后连上的连接替换前一个），推送积压的消息
     */
    public static function bind(Context $ctx, int $uid, string $nickname): void
    {
        $conn = $ctx->connection();
        if ($conn !== null) {
            // 同一连接换号重新鉴权：注销旧号
            foreach (self::$local as $other => $session) {
                if ($other !== $uid && $session['cid'] === $conn->id) {
                    self::unbind($other);
                }
            }
        }
        self::$local[$uid] = [
            'cid' => $conn?->id ?? 0,
            'nickname' => $nickname,
            'send' => $conn !== null
                ? static function (string $frame) use ($conn): void {
                    $conn->send($frame);
                }
                : static function (string $frame) use ($ctx): void {
                    $ctx->sentMessages[] = $frame;
                },
        ];
        Chat::markWs($uid, $nickname, self::$workerId);
        self::deliver($uid);
    }

    /**
     * 连接断开：是该玩家当前登记的连接才注销
     */
    public static function onClose(TcpConnection $connection): void
    {
        $uid = (int)($connection->uid ?? 0);
        if ($uid > 0 && isset(self::$local[$uid]) && self::$local[$uid]['cid'] === $connection->id) {
            self::unbind($uid);
        }
    }

    public static function unbind(int $uid): void
    {
        unset(self::$local[$uid]);
        Chat::unmarkWs($uid, self::$workerId);
    }

    /**
     * 取出本进程的推送通知，推送这些玩家私人队列里的消息
     */
    public static function drain(): void
    {
        do {
            $uids = Chat::popPushUids(self::$workerId);
            foreach (array_unique($uids) as $uid) {
                if (isset(self::$local[$uid])) {
                    self::deliver($uid);
                }
            }
        } while ($uids !== []);
    }

    /**
     * 刷新本进程上全部玩家的在线标记（代替旧版轮询 read 时的 _update_online_arr）
     */
    public static function refresh(): void
    {
        foreach (self::$local as $uid => $session) {
            Chat::markWs($uid, $session['nickname'], self::$workerId);
        }
    }

    /**
     * 弹出私人队列推送（同 c=chat&m=read：去掉屏蔽的人发的消息；队列空时不推）
     */
    private static function deliver(int $uid): void
    {
        $session = self::$local[$uid];
        $list = Chat::filterBanned(Chat::readMessage($uid, $session['nickname']), $uid);
        if ($list === []) {
            return;
        }
        ($session['send'])((string)json_encode([
            'type' => self::EVENT,
            'data' => ['status' => 1, 'list' => $list],
            'timestamp' => time(),
        ], JSON_UNESCAPED_UNICODE));
    }
}
