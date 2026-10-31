<?php

declare(strict_types=1);

/**
 * HTTP 路由（OmniPHP Router）
 *
 * server.php 在 master 里 require 一次，fork 后子进程继承（改路由必须 restart，reload 不生效）；
 * PHPUnit 由 tests/bootstrap.php 加载同一份，测试走的就是线上这条管道。
 *
 * 旧版 H5 客户端只有一个接口入口 index.php?c={控制器}&m={方法}（GET / POST 均可），
 * 外加 public/ 下的静态资源，所以只注册两条兜底路由，全部请求进同一条全局中间件管道：
 *   PublicAssets → Cors → ResponseEnvelope → Auth → Throttle → DailyCron → GameProtocol → GameProtocolMiddleware::execute
 */

use Hanjiang\Handler\HandlerRegistry;
use Hanjiang\Http\ExceptionHandler;
use Hanjiang\Middleware\AuthMiddleware;
use Hanjiang\Middleware\CorsMiddleware;
use Hanjiang\Middleware\DailyCronMiddleware;
use Hanjiang\Middleware\GameProtocolMiddleware;
use Hanjiang\Middleware\PublicAssetsMiddleware;
use Hanjiang\Middleware\ResponseEnvelopeMiddleware;
use Hanjiang\Middleware\ThrottleMiddleware;
use OmniPHP\Http\Router;

// 未捕获异常 → 500 + 游戏信封 {"status":-500,"message":"系统异常: ..."}
Router::setExceptionHandler(new ExceptionHandler());

Router::middleware([
    // 静态资源 / 动态 config.js 最先处理，不经过 CORS 与协议分发
    new PublicAssetsMiddleware(BASE_PATH . '/public'),
    new CorsMiddleware(),
    new ResponseEnvelopeMiddleware(),
    new AuthMiddleware(),
    new ThrottleMiddleware(),
    new DailyCronMiddleware(),
    // 自动挂载 7 大领域子目录下的全部 Handler 映射 (驼峰与下划线双向兼容)
    new GameProtocolMiddleware(HandlerRegistry::all()),
]);

// 根路径与其余任意路径（index.php、*.php、静态资源、未知路径）都进同一条管道；
// 路径含 [A-Za-z0-9/_.-] 以外字符的请求匹配不到，由框架回 404
Router::any('/', GameProtocolMiddleware::execute(...));
Router::any('/{path:path}', GameProtocolMiddleware::execute(...));
