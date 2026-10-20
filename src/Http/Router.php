<?php

declare(strict_types=1);

namespace Hanjiang\Http;

use Hanjiang\Context;
use Hanjiang\Middleware\MiddlewareInterface;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response as WorkermanResponse;

final class Router
{
    /** @var list<MiddlewareInterface> */
    private array $middlewares = [];

    public function __construct(
        private readonly string $publicPath
    ) {
    }

    public function use(MiddlewareInterface $middleware): self
    {
        $this->middlewares[] = $middleware;
        return $this;
    }

    public function dispatch(TcpConnection $connection, Request $request): void
    {
        $path = $request->path();

        // 1. 静态文件直出
        if ($this->serveStatic($connection, $path, $request)) {
            return;
        }

        // 2. 组装请求上下文 Context
        $ctx = new Context($connection, $request);

        try {
            /** @var mixed $result */
            $result = $this->handleContext($ctx);

            if ($result instanceof WorkermanResponse) {
                $connection->send($result);
            } else {
                $connection->send(new WorkermanResponse(
                    200,
                    ['Content-Type' => 'application/json; charset=utf-8'],
                    json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                ));
            }
        } catch (\Throwable $e) {
            $connection->send(new WorkermanResponse(
                500,
                ['Content-Type' => 'application/json; charset=utf-8'],
                json_encode([
                    'status' => -500,
                    'message' => '系统异常: ' . $e->getMessage(),
                ], JSON_UNESCAPED_UNICODE)
            ));
        }
    }

    /**
     * 纯内存流水线执行器 (供 HTTP Gateway 与毫秒级 PHPUnit 共享)
     */
    public function handleContext(Context $ctx): mixed
    {
        $pipeline = array_reduce(
            array_reverse($this->middlewares),
            static fn(callable $next, MiddlewareInterface $middleware): callable =>
                static fn(Context $ctx): mixed => $middleware->handle($ctx, $next),
            static fn(Context $ctx): mixed => self::executeHandler($ctx)
        );

        return $pipeline($ctx);
    }

    private static function executeHandler(Context $ctx): mixed
    {
        /** @var callable|null $target */
        $target = $ctx->get('handler_target');
        if (is_callable($target)) {
            return $target($ctx);
        }

        return [
            'status' => 0,
            'message' => '未定义执行动作',
        ];
    }

    private function serveStatic(TcpConnection $connection, string $path, Request $request): bool
    {
        if ($path === '/' || $path === '') {
            $path = '/index.html';
        }

        $realFile = realpath($this->publicPath . $path);
        if ($realFile === false || !str_starts_with($realFile, $this->publicPath) || !is_file($realFile)) {
            return false;
        }

        // 动态注入服务端端口，保持与 autoload.js 兼容
        if (str_ends_with($path, 'autoload.js')) {
            $content = (string)file_get_contents($realFile);
            $connection->send(new WorkermanResponse(200, [
                'Content-Type' => 'application/javascript; charset=utf-8',
                'Cache-Control' => 'no-cache',
            ], $content));
            return true;
        }

        $mime = $this->getMimeType($realFile);
        $content = (string)file_get_contents($realFile);
        $connection->send(new WorkermanResponse(200, ['Content-Type' => $mime], $content));
        return true;
    }

    private function getMimeType(string $file): string
    {
        return match (pathinfo($file, PATHINFO_EXTENSION)) {
            'html', 'htm' => 'text/html; charset=utf-8',
            'js' => 'application/javascript; charset=utf-8',
            'css' => 'text/css; charset=utf-8',
            'json' => 'application/json; charset=utf-8',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            default => 'application/octet-stream',
        };
    }
}
