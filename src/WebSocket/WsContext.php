<?php

declare(strict_types=1);

namespace Hanjiang\WebSocket;

use Hanjiang\Database\DB;
use Workerman\Connection\TcpConnection;

/**
 * WebSocket 请求与事件上下文 (对标 Go 全双工 Context 架构设计)
 *
 * 封装 WebSocket 交互周期的连接实例、报文载荷、会话玩家与应答通道，
 * 实现业务逻辑与底层 Socket 彻底解耦，并原生支持毫秒级无头单元测试 Mock。
 */
final class WsContext
{
    /** @var array<string, mixed> */
    private array $data;
    /** @var array<string, mixed> */
    private array $attributes = [];
    public readonly float $timestamp;

    /** @var list<string> 测试用捕获的出站消息日志 */
    public array $sentMessages = [];

    /**
     * @param array<string, mixed> $parsedData
     */
    public function __construct(
        public readonly ?TcpConnection $connection = null,
        public readonly string $raw = '',
        array $parsedData = [],
        private readonly ?int $mockUid = null
    ) {
        $this->timestamp = microtime(true);
        $this->data = !empty($parsedData) ? $parsedData : (json_decode($raw, true) ?: []);
    }

    /**
     * 极速单元测试 / 纯内存 Mock 实例
     *
     * @param array<string, mixed> $payload
     */
    public static function mock(array $payload = [], ?int $uid = null): self
    {
        return new self(
            connection: null,
            raw: json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '',
            parsedData: $payload,
            mockUid: $uid
        );
    }

    /**
     * 获取事件类型 / 动作名称
     */
    public function action(): string
    {
        return (string)($this->data['action'] ?? $this->data['type'] ?? '');
    }

    /**
     * 获取入参
     */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * 获取全部入参数据
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * 获取当前连接绑定的玩家 UID
     */
    public function uid(): ?int
    {
        if ($this->mockUid !== null) {
            return $this->mockUid;
        }

        if ($this->connection === null) {
            return null;
        }

        return $this->connection->uid ?? null;
    }

    /**
     * 绑定玩家 UID 到当前连接
     */
    public function bindUid(int $uid): self
    {
        if ($this->connection !== null) {
            $this->connection->uid = $uid;
            Broadcaster::bindUid($this->connection, $uid);
        }
        return $this;
    }

    /**
     * 获取当前登录玩家的数据库档案
     *
     * @return array<string, mixed>|null
     */
    public function member(): ?array
    {
        $uid = $this->uid();
        if ($uid === null || $uid <= 0) {
            return null;
        }

        return DB::table('member')->where('uid', $uid)->first();
    }

    /**
     * 回复单发消息给当前连接的客户端
     *
     * @param array<string, mixed> $data
     */
    public function emit(string $type, array $data = []): bool
    {
        $payload = json_encode([
            'type' => $type,
            'data' => $data,
            'timestamp' => time(),
        ], JSON_UNESCAPED_UNICODE);

        if ($this->connection !== null) {
            $this->connection->send($payload);
            return true;
        }

        $this->sentMessages[] = $payload ?: '';
        return true;
    }

    /**
     * 回复错误信息
     */
    public function error(string $message, int $code = -1): bool
    {
        return $this->emit('error', [
            'code' => $code,
            'message' => $message,
        ]);
    }

    /**
     * 向全服在线客户端发起广播
     *
     * @param array<string, mixed> $data
     */
    public function broadcast(string $type, array $data = []): int
    {
        return Broadcaster::broadcast($type, $data);
    }

    /**
     * 上下文属性传递 (注入与提取)
     */
    public function set(string $key, mixed $value): self
    {
        $this->attributes[$key] = $value;
        return $this;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }
}
