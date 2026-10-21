<?php

declare(strict_types=1);

namespace Hanjiang\Middleware;

use OmniPHP\Http\Context;

interface MiddlewareInterface
{
    /**
     * @param callable(Context): mixed $next
     */
    public function handle(Context $ctx, callable $next): mixed;
}
