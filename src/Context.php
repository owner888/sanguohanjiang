<?php

declare(strict_types=1);

namespace Hanjiang;

use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;

final class Context
{
    /** @var array<string, mixed> */
    private array $attributes = [];

    /**
     * @param array<string, mixed> $mockInputs
     * @param array<string, string> $mockHeaders
     */
    public function __construct(
        public readonly ?TcpConnection $connection = null,
        public readonly ?Request $request = null,
        private readonly array $mockInputs = [],
        private readonly array $mockHeaders = [],
        private readonly string $mockMethod = 'GET',
        private readonly string $mockPath = '/'
    ) {
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
        return $this->request?->post($key) ?? $this->request?->get($key) ?? $default;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->mockInputs)) {
            return $this->mockInputs[$key];
        }
        return $this->request?->get($key) ?? $default;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->mockInputs)) {
            return $this->mockInputs[$key];
        }
        return $this->request?->post($key) ?? $default;
    }

    public function header(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->mockHeaders)) {
            return $this->mockHeaders[$key];
        }
        return $this->request?->header($key) ?? $default;
    }

    public function path(): string
    {
        return $this->request?->path() ?? $this->mockPath;
    }

    public function host(): string
    {
        return $this->request?->host() ?? ($this->mockHeaders['host'] ?? '127.0.0.1:18100');
    }

    public function method(): string
    {
        return $this->request?->method() ?? $this->mockMethod;
    }

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
