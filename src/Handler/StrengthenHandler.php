<?php

declare(strict_types=1);

namespace Hanjiang\Handler;

use Hanjiang\Context;

final class StrengthenHandler
{
    /**
     * 装备强化主界面: c=strengthen&m=index
     */
    public static function index(Context $ctx): array
    {
        return [
            'status' => 1,
            'rate' => 100, // 成功率 100%
            'cd' => 0,
            'list' => [
                1 => [
                    'id' => 1,
                    'name' => '青铜剑',
                    'level' => 1,
                    'silver' => 200,
                ],
            ],
            'message' => 'ok',
        ];
    }

    /**
     * 执行强化: c=strengthen&m=strengthen_start
     */
    public static function strengthen_start(Context $ctx): array
    {
        return [
            'status' => 1,
            'message' => '强化成功',
            'level' => 2,
            'cd' => 0,
        ];
    }

    /**
     * 获取单件强化信息: c=strengthen&m=strengthen_info
     */
    public static function strengthen_info(Context $ctx): array
    {
        return [
            'status' => 1,
            'info' => [
                'cost_silver' => 200,
                'rate' => 100,
            ],
        ];
    }

    /**
     * 清理强化冷却: c=strengthen&m=clear_cd
     */
    public static function clear_cd(Context $ctx): array
    {
        return [
            'status' => 1,
            'message' => 'CD已清除',
        ];
    }
}
