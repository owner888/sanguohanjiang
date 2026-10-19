<?php

declare(strict_types=1);

namespace Hanjiang\Database;

use Redis as PhpRedis;
use Throwable;

final class Redis
{
    private static ?PhpRedis $client = null;
    /** @var array<string, mixed> */
    private static array $config = [];

    /**
     * @param array<string, mixed> $config
     */
    public static function init(array $config): void
    {
        self::$config = $config;
        self::connect();
    }

    private static function connect(): void
    {
        $redis = new PhpRedis();
        $host = self::$config['host'] ?? '127.0.0.1';
        $port = (int)(self::$config['port'] ?? 6379);
        $timeout = (float)(self::$config['timeout'] ?? 2.5);

        $redis->connect($host, $port, $timeout);

        if (!empty(self::$config['password'])) {
            $redis->auth((string)self::$config['password']);
        }

        if (isset(self::$config['database'])) {
            $redis->select((int)self::$config['database']);
        }

        if (!empty(self::$config['prefix'])) {
            $redis->setOption(PhpRedis::OPT_PREFIX, (string)self::$config['prefix']);
        }

        self::$client = $redis;
    }

    public static function client(): PhpRedis
    {
        if (self::$client === null) {
            self::connect();
        } else {
            try {
                self::$client->ping();
            } catch (Throwable) {
                self::connect();
            }
        }
        return self::$client;
    }

    public static function get(string $key): ?string
    {
        $val = self::client()->get($key);
        return $val === false ? null : (string)$val;
    }

    public static function set(string $key, string $val, int $ttl = 0): bool
    {
        if ($ttl > 0) {
            return (bool)self::client()->setex($key, $ttl, $val);
        }
        return (bool)self::client()->set($key, $val);
    }

    public static function getJson(string $key): mixed
    {
        $val = self::get($key);
        return $val === null ? null : json_decode($val, true);
    }

    public static function setJson(string $key, mixed $val, int $ttl = 0): bool
    {
        return self::set($key, json_encode($val, JSON_UNESCAPED_UNICODE), $ttl);
    }

    public static function del(string ...$keys): int
    {
        return (int)self::client()->del(...$keys);
    }

    public static function exists(string $key): bool
    {
        return (bool)self::client()->exists($key);
    }

    public static function expire(string $key, int $seconds): bool
    {
        return (bool)self::client()->expire($key, $seconds);
    }
}
