<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use OmniPHP\Cache\Redis;

/**
 * 节日活动第三批（寻宝、蹴鞠、七星灯、充值送豪礼、领工资、老兵召回、貔貅聚财、绝版神将）共用的操作锁
 *
 * 旧版各 model 的 add_lock / get_lock / unlock：键为「锁名 + uid」，get 后 setex 30 秒（先读后写，并发时两个请求都能拿到锁）。
 * 新版改为一次 SET NX EX，拿到锁才继续；键名照旧版。
 */
final class FestLock
{
    /** 旧版 setex 的秒数 */
    public const int TTL = 30;

    /**
     * 加锁（get_lock + add_lock）；锁已存在返回 false
     */
    public static function acquire(string $key, int $ttl = self::TTL): bool
    {
        return (bool)Redis::instance()->set($key, 1, ['nx', 'ex' => $ttl]);
    }

    /**
     * 解锁（unlock）
     */
    public static function release(string $key): void
    {
        Redis::del($key);
    }
}
