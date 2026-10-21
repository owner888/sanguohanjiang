<?php

declare(strict_types=1);

namespace Hanjiang;

use OmniPHP\Http\Context as OmniContext;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;

/**
 * HTTP 请求上下文 (对齐 Go Gin 设计模式)
 *
 * 继承自 OmniPHP 框架核心 HTTP 上下文，具备类似 Gin 的全部请求封装与流畅 API，
 * 并支持纯内存极速单元测试 Mock。
 */
class Context extends OmniContext
{
    /** @var array<string, mixed> */
    private array $mockInputs = [];
    /** @var array<string, string> */
    private array $mockHeaders = [];
    private string $mockMethod = 'GET';
    private string $mockPath = '/';

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $mockInputs
     * @param array<string, string> $mockHeaders
     */
    public function __construct(
        ?TcpConnection $connection = null,
        ?Request $request = null,
        array $params = [],
        array $mockInputs = [],
        array $mockHeaders = [],
        string $mockMethod = 'GET',
        string $mockPath = '/'
    ) {
        $fakeConnection = $connection ?? new class extends TcpConnection {
            public function __construct() {}
            public function send($data, $raw = false): bool { return true; }
            public function close(mixed $data = null, bool $raw = false): void {}
        };
        $fakeRequest = $request ?? new Request("GET / HTTP/1.1\r\nHost: 127.0.0.1:18100\r\n\r\n");

        parent::__construct($fakeConnection, $fakeRequest, $params);

        $this->mockInputs = $mockInputs;
        $this->mockHeaders = $mockHeaders;
        $this->mockMethod = $mockMethod;
        $this->mockPath = $mockPath;
    }

    /**
     * 极速单元测试 / 纯内存 Mock 实例
     *
     * @param array<string, mixed> $inputs
     * @param array<string, string> $headers
     */
    public static function mock(array $inputs = [], array $headers = [], string $method = 'GET', string $path = '/'): self
    {
        return new self(
            connection: null,
            request: null,
            params: [],
            mockInputs: $inputs,
            mockHeaders: $headers,
            mockMethod: $method,
            mockPath: $path
        );
    }

    public function input(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->mockInputs)) {
            return $this->mockInputs[$key];
        }
        return parent::input($key, $default);
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key !== null && array_key_exists($key, $this->mockInputs)) {
            return $this->mockInputs[$key];
        }
        return parent::query($key, $default);
    }

    public function post(?string $key = null, mixed $default = null): mixed
    {
        if ($key !== null && array_key_exists($key, $this->mockInputs)) {
            return $this->mockInputs[$key];
        }
        return parent::post($key, $default);
    }

    public function header(?string $key = null, mixed $default = null): mixed
    {
        if ($key !== null && array_key_exists($key, $this->mockHeaders)) {
            return $this->mockHeaders[$key];
        }
        return parent::header($key, $default);
    }

    public function path(): string
    {
        if ($this->mockPath !== '/') {
            return $this->mockPath;
        }
        return parent::path();
    }

    public function host(): string
    {
        return (string)($this->header('host', '127.0.0.1:18100'));
    }

    public function method(): string
    {
        if ($this->mockMethod !== 'GET') {
            return $this->mockMethod;
        }
        return parent::method();
    }
}
