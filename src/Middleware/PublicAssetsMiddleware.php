<?php

declare(strict_types=1);

namespace Hanjiang\Middleware;

use OmniPHP\Config;
use OmniPHP\Http\Context;
use OmniPHP\Http\Middleware;
use OmniPHP\Http\StaticServer;
use Workerman\Protocols\Http\Response as WorkermanResponse;

/**
 * public/ 下的游戏客户端静态资源（全局中间件的第一层，位于 CORS / 协议分发之前）
 *
 * 为什么不用 OmniPHP Router::static()：框架的静态目录在路由匹配之前对所有请求生效，而旧版客户端的约定是
 *   1. 带 c 参数、或路径是 *.php 的请求一律是接口（`/?c=ios&m=...` 不能被 index.html 截走）；
 *   2. /config.js 由服务端按访问域名动态生成（public/config.js 只是本地直开 html 时的兜底，不能被直出）；
 *   3. autoload.js 走 no-cache、音频走 206 Range；
 * 这几条框架的静态目录表达不了，所以在这里判断，普通文件仍交给框架的 StaticServer（ETag / 304 / MIME）。
 * 不是静态资源的请求交给后续中间件，按游戏协议处理。
 */
final class PublicAssetsMiddleware implements Middleware
{
    public function __construct(
        private readonly string $publicPath
    ) {
    }

    public function handle(Context $ctx, callable $next): mixed
    {
        $path = $ctx->path();
        $isApi = $path === '/index.php' || str_ends_with($path, '.php') || $ctx->query('c') !== null;
        if ($isApi) {
            return $next($ctx);
        }

        // 1. 动态生成配置中心 config.js (权威服务端配置与当前访问环境自适应绑定)
        if ($path === '/config.js' || str_ends_with($path, 'config.js')) {
            return $this->configJs($ctx);
        }

        // 2. 媒体流式分段请求 (针对 Safari / iOS WebKit 提供标准的 HTTP 206 Partial Content 支持)
        if (preg_match('/\.(mp3|wav|ogg|m4a|aac)$/i', $path) === 1) {
            $media = $this->mediaWithRange($ctx, $path);
            if ($media !== null) {
                return $media;
            }
        }

        // 3. autoload.js 不缓存（其余统一走 OmniPHP 官方 StaticServer）
        if (str_ends_with($path, 'autoload.js')) {
            $realFile = realpath($this->publicPath . $path);
            if ($realFile !== false && is_file($realFile)) {
                return new WorkermanResponse(200, [
                    'Content-Type' => 'application/javascript; charset=utf-8',
                    'Cache-Control' => 'no-cache',
                ], (string)file_get_contents($realFile));
            }
        }

        // StaticServer 自己发送响应；返回 null 告诉 Kernel 响应已发出
        if (StaticServer::serve($ctx->connection(), $ctx->request(), '', $this->publicPath, ['index' => 'index.html', 'spaFallback' => false])) {
            return null;
        }

        return $next($ctx);
    }

    private function configJs(Context $ctx): WorkermanResponse
    {
        $hostName = explode(':', $ctx->request()->host())[0] ?: '127.0.0.1';
        $wsPort = Config::get('app.ws.public_port', 18101);
        $proto = ($ctx->header('x-forwarded-proto') === 'https') ? 'wss:' : 'ws:';
        $defaultWs = "{$proto}//{$hostName}:{$wsPort}";

        $assets = Config::get('app.assets', []);
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

        return new WorkermanResponse(200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'no-cache',
        ], $jsContent);
    }

    /**
     * 静态音频/媒体流式文件直出 (支持 HTTP 206 Partial Content 与 Byte-Range 请求)；文件不存在返回 null
     */
    private function mediaWithRange(Context $ctx, string $path): ?WorkermanResponse
    {
        $realDir = realpath($this->publicPath);
        if ($realDir === false) {
            return null;
        }

        $realFile = realpath($realDir . '/' . ltrim($path, '/'));

        // 防路径穿越安全沙箱
        if ($realFile === false || !str_starts_with($realFile, $realDir) || !is_file($realFile)) {
            return null;
        }

        $fileSize = (int)filesize($realFile);
        $mtime = (int)filemtime($realFile);
        $etag = sprintf('"%x-%x"', $mtime, $fileSize);

        if ($ctx->header('if-none-match') === $etag) {
            return new WorkermanResponse(304);
        }

        $headers = [
            'Content-Type' => StaticServer::detectMimeType($realFile),
            'Accept-Ranges' => 'bytes',
            'ETag' => $etag,
            'Cache-Control' => 'public, max-age=86400',
            'Last-Modified' => gmdate('D, d M Y H:i:s', $mtime) . ' GMT',
        ];

        // 处理 Range: bytes=start-end (对标 Safari / iOS WebKit 分段请求规范)
        $range = $ctx->header('range');
        if ($range !== null && preg_match('/bytes=(\d*)-(\d*)/i', $range, $matches)) {
            $startStr = $matches[1] ?? '';
            $endStr = $matches[2] ?? '';

            if ($startStr === '' && $endStr === '') {
                return new WorkermanResponse(416, ['Content-Range' => "bytes */{$fileSize}"]);
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
                return new WorkermanResponse(416, ['Content-Range' => "bytes */{$fileSize}"]);
            }

            $end = min($end, $fileSize - 1);
            $length = $end - $start + 1;

            $headers['Content-Range'] = "bytes {$start}-{$end}/{$fileSize}";
            $headers['Content-Length'] = (string)$length;

            return new WorkermanResponse(206, $headers, (string)file_get_contents($realFile, false, null, $start, $length));
        }

        // 普通请求返回 200 并显式宣示 Accept-Ranges: bytes
        $headers['Content-Length'] = (string)$fileSize;
        return new WorkermanResponse(200, $headers, (string)file_get_contents($realFile));
    }
}
