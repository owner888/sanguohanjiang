<?php

declare(strict_types=1);

namespace Hanjiang\Middleware;

use Hanjiang\Context;

interface MiddlewareInterface
{
    /**
     * @param callable(Context): mixed $next
     */
    public function handle(Context $ctx, callable $next): mixed;
}
