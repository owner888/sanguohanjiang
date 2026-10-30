<?php

declare(strict_types=1);

namespace Hanjiang\Tests\Support;

use Random\Engine;

/**
 * 按脚本出数的随机引擎：Randomizer::getInt($min, $max) 依次返回 $min + 偏移量
 *
 * 每次 generate() 输出一个 32 位小端整数；Randomizer 对非 Mt19937 引擎取 结果 % (max - min + 1)，
 * 偏移量小于区间长度时 getInt 即 $min + 偏移量。脚本用完后一直输出 $default。
 */
final class ScriptedRandomEngine implements Engine
{
    /** @var list<int> 已输出的偏移量（调试用） */
    public array $drawn = [];

    /**
     * @param list<int> $offsets
     */
    public function __construct(private array $offsets = [], private readonly int $default = 99)
    {
    }

    public function generate(): string
    {
        $value = $this->offsets === [] ? $this->default : array_shift($this->offsets);
        $this->drawn[] = $value;
        return pack('V', $value);
    }
}
