<?php

declare(strict_types=1);

namespace Hanjiang;

use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;

final class Context
{
    /** @var array<string, mixed> */
    private array $attributes = [];

    public function __construct(
        public readonly TcpConnection $connection,
        public readonly Request $request
    ) {
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->request->post($key) ?? $this->request->get($key) ?? $default;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->request->get($key) ?? $default;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $this->request->post($key) ?? $default;
    }

    public function header(string $key, mixed $default = null): mixed
    {
        return $this->request->header($key) ?? $default;
    }

    public function path(): string
    {
        return $this->request->path();
    }

    public function method(): string
    {
        return $this->request->method();
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
