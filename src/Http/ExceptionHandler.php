<?php

declare(strict_types=1);

namespace Hanjiang\Http;

use OmniPHP\Http\Context;
use OmniPHP\Http\ExceptionHandler as BaseExceptionHandler;
use OmniPHP\Logger;
use Throwable;
use Workerman\Protocols\Http\Response as WorkermanResponse;

/**
 * 全局异常处理器（routes.php 用 Router::setExceptionHandler 注册）
 *
 * 继承框架的 ExceptionHandler，只改响应体：旧版客户端认的是游戏信封 {"status":-500,"message":"系统异常: ..."}，
 * 框架默认的 {"error":...,"status_code":500} 它不认识。日志照旧走 Logger::error（runtime/logs/error-*.log）。
 */
final class ExceptionHandler extends BaseExceptionHandler
{
    public function handle(Throwable $e, Context $ctx): WorkermanResponse
    {
        Logger::error("Unhandled HTTP Exception: {$e->getMessage()}", [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'path' => $ctx->path(),
            'query' => $ctx->request()->queryString(),
            'trace' => $e->getTraceAsString(),
        ]);

        return new WorkermanResponse(
            500,
            ['Content-Type' => 'application/json; charset=utf-8'],
            json_encode([
                'status' => -500,
                'message' => '系统异常: ' . $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE)
        );
    }
}
