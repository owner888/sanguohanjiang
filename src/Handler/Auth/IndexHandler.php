<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Auth;

use OmniPHP\Http\Context;

/**
 * 根控制器探活与状态探测: c=index&m=index
 */
final class IndexHandler
{
    public static function index(Context $ctx): array
    {
        return [
            'status' => 1,
            'message' => 'ok',
            'time' => time(),
        ];
    }
}
