<?php

declare(strict_types=1);

namespace Hanjiang\Http;

use Hanjiang\Middleware\MiddlewareInterface;
use OmniPHP\Http\Context;
use OmniPHP\Http\StaticServer;
use OmniPHP\Logger;
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
        $isApi = $path === '/index.php' || str_ends_with($path, '.php') || $request->get('c') !== null;

        if (!$isApi) {
            // 1. 动态生成配置中心 config.js (权威服务端配置与当前访问环境自适应绑定)
            if ($path === '/config.js' || str_ends_with($path, 'config.js')) {
                $appConfig = require dirname(__DIR__, 2) . '/config/app.php';
                $host = $request->host();
                $hostName = explode(':', $host)[0] ?: '127.0.0.1';
                $wsPort = $appConfig['ws']['public_port'] ?? 18101;
                $proto = ($request->header('x-forwarded-proto') === 'https') ? 'wss:' : 'ws:';
                $defaultWs = "{$proto}//{$hostName}:{$wsPort}";

                $assets = $appConfig['assets'] ?? [];
                $version = $assets['version'] ?? '20261024_1';
                $staticUrl = $assets['static_url'] ?? 'static/';
                $cdnUrl = $assets['cdn_url'] ?? $staticUrl;
                $apiUrl = $assets['api_url'] ?? 'index.php';

                $jsContent = <<<JS
/**
 * 悍将三国 - 全局集中配置中心 (由服务端 config/app.php 统一动态注入驱动)
 */
(function(window) {
    'use strict';
    window.HJ_CONFIG = {
        version: '{$version}',
        apiUrl: '{$apiUrl}',
        staticUrl: '{$staticUrl}',
        cdnUrl: '{$cdnUrl}',
        wsUrl: '{$defaultWs}',
        cdn: function(p) {
            if (!p) return '';
            if (/^https?:\/\//i.test(p) || p.indexOf('//') === 0) return p;
            var base = this.cdnUrl.replace(/\/+$/, '');
            return base + '/' + p.replace(/^\/+/, '');
        },
        static: function(p) {
            if (!p) return '';
            if (/^https?:\/\//i.test(p) || p.indexOf('//') === 0) return p;
            var base = this.staticUrl.replace(/\/+$/, '');
            return base + '/' + p.replace(/^\/+/, '');
        }
    };
    window.staticUrl = window.HJ_CONFIG.staticUrl;
    window.cdnUrl = window.HJ_CONFIG.cdnUrl;
})(typeof window !== 'undefined' ? window : this);
JS;
                $connection->send(new WorkermanResponse(200, [
                    'Content-Type' => 'application/javascript; charset=utf-8',
                    'Cache-Control' => 'no-cache',
                ], $jsContent));
                return;
            }

            // 2. 媒体流式分段请求 (针对 Safari / iOS WebKit 提供标准的 HTTP 206 Partial Content 支持)
            if (preg_match('/\.(mp3|wav|ogg|m4a|aac)$/i', $path) === 1) {
                if ($this->serveMediaWithRange($connection, $request, $path)) {
                    return;
                }
            }

            // 3. 静态文件直出 (autoload 动态注入端口兼容，其余统一走 OmniPHP 官方 StaticServer)
            if (str_ends_with($path, 'autoload.js')) {
                $realFile = realpath($this->publicPath . $path);
                if ($realFile !== false && is_file($realFile)) {
                    $content = (string)file_get_contents($realFile);
                    $connection->send(new WorkermanResponse(200, [
                        'Content-Type' => 'application/javascript; charset=utf-8',
                        'Cache-Control' => 'no-cache',
                    ], $content));
                    return;
                }
            }

            if (StaticServer::serve($connection, $request, '', $this->publicPath, ['index' => 'index.html', 'spaFallback' => false])) {
                return;
            }
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
            Logger::error("Unhandled HTTP Exception: {$e->getMessage()}", [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'path' => $request->path(),
                'query' => $request->queryString(),
                'trace' => $e->getTraceAsString(),
            ]);

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

    /**
     * 静态音频/媒体流式文件直出 (支持 HTTP 206 Partial Content 与 Byte-Range 请求)
     */
    private function serveMediaWithRange(TcpConnection $connection, Request $request, string $path): bool
    {
        $realDir = realpath($this->publicPath);
        if ($realDir === false) {
            return false;
        }

        $targetFile = $realDir . '/' . ltrim($path, '/');
        $realFile = realpath($targetFile);

        // 防路径穿越安全沙箱
        if ($realFile === false || !str_starts_with($realFile, $realDir) || !is_file($realFile)) {
            return false;
        }

        $fileSize = (int)filesize($realFile);
        $mtime = (int)filemtime($realFile);
        $etag = sprintf('"%x-%x"', $mtime, $fileSize);

        $ifNoneMatch = $request->header('if-none-match');
        if ($ifNoneMatch === $etag) {
            $connection->send(new WorkermanResponse(304));
            return true;
        }

        $mime = StaticServer::detectMimeType($realFile);
        $range = $request->header('range');

        $headers = [
            'Content-Type' => $mime,
            'Accept-Ranges' => 'bytes',
            'ETag' => $etag,
            'Cache-Control' => 'public, max-age=86400',
            'Last-Modified' => gmdate('D, d M Y H:i:s', $mtime) . ' GMT',
        ];

        // 处理 Range: bytes=start-end (对标 Safari / iOS WebKit 分段请求规范)
        if ($range !== null && preg_match('/bytes=(\d*)-(\d*)/i', $range, $matches)) {
            $startStr = $matches[1] ?? '';
            $endStr = $matches[2] ?? '';

            if ($startStr === '' && $endStr === '') {
                $connection->send(new WorkermanResponse(416, ['Content-Range' => "bytes */{$fileSize}"]));
                return true;
            }

            if ($startStr === '') {
                $length = (int)$endStr;
                $start = max(0, $fileSize - $length);
                $end = $fileSize - 1;
            } else {
                $start = (int)$startStr;
                $end = ($endStr !== '') ? (int)$endStr : ($fileSize - 1);
            }

            if ($start > $end || $start >= $fileSize) {
                $connection->send(new WorkermanResponse(416, ['Content-Range' => "bytes */{$fileSize}"]));
                return true;
            }

            $end = min($end, $fileSize - 1);
            $length = $end - $start + 1;

            $headers['Content-Range'] = "bytes {$start}-{$end}/{$fileSize}";
            $headers['Content-Length'] = (string)$length;

            $chunk = (string)file_get_contents($realFile, false, null, $start, $length);
            $connection->send(new WorkermanResponse(206, $headers, $chunk));
            return true;
        }

        // 普通请求返回 200 并显式宣示 Accept-Ranges: bytes
        $headers['Content-Length'] = (string)$fileSize;
        $content = (string)file_get_contents($realFile);
        $connection->send(new WorkermanResponse(200, $headers, $content));
        return true;
    }
}
