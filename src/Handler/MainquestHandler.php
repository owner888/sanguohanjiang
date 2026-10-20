<?php

declare(strict_types=1);

namespace Hanjiang\Handler;

use Hanjiang\Context;

final class MainquestHandler
{
    /**
     * 主线任务: c=mainquest&m=index
     */
    public static function index(Context $ctx): array
    {
        return [
            'status' => 1,
            'task' => [
                'id' => 1,
                'name' => '初出茅庐',
                'desc' => '通关大兴山第一个据点【黄巾前哨】',
                'status' => 1, // 0未完成 1已完成 2已领奖
                'silver' => 500,
                'gold' => 10,
                'exp' => 200,
            ],
            'message' => 'ok',
        ];
    }

    /**
     * 领取主线奖励: c=mainquest&m=get_task_reward
     */
    public static function get_task_reward(Context $ctx): array
    {
        return [
            'status' => 1,
            'silver' => 500,
            'gold' => 10,
            'message' => '领奖成功',
        ];
    }
}
