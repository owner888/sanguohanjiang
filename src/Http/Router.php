<?php

declare(strict_types=1);

namespace Hanjiang\Http;

use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response as WorkermanResponse;

final class Router
{
    /** @var array<string, object> */
    private array $controllers = [];

    public function __construct(
        private readonly string $publicPath
    ) {
    }

    public function register(string $name, object $controller): void
    {
        $this->controllers[strtolower($name)] = $controller;
    }

    public function dispatch(TcpConnection $connection, Request $request): void
    {
        $path = $request->path();

        // 1. 静态文件优先
        if ($this->serveStatic($connection, $path, $request)) {
            return;
        }

        // 2. 跨域 OPTIONS 预检请求直接通过
        if ($request->method() === 'OPTIONS') {
            $connection->send(new WorkermanResponse(204, [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
                'Access-Control-Allow-Headers' => '*',
            ], ''));
            return;
        }

        // 3. 业务接口路由 (兼容原版 c=xxx&m=yyy 协议)
        $c = (string)($request->get('c') ?? $request->post('c') ?? 'index');
        $m = (string)($request->get('m') ?? $request->post('m') ?? 'index');

        $cLower = strtolower($c);
        if (!isset($this->controllers[$cLower])) {
            $connection->send(Response::error("接口未实现: {$c}.{$m}", 0));
            return;
        }

        $controller = $this->controllers[$cLower];
        if (!method_exists($controller, $m)) {
            $connection->send(Response::error("接口方法未实现: {$c}.{$m}", 0));
            return;
        }

        try {
            /** @var WorkermanResponse $res */
            $res = $controller->$m($request);
            $connection->send($res);
        } catch (\Throwable $e) {
            $connection->send(Response::error('系统异常: ' . $e->getMessage(), -500, 500));
        }
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
        $connection->send((new WorkermanResponse())->file($realFile)->withHeader('Content-Type', $mime));
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
