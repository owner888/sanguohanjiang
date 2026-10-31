<?php

declare(strict_types=1);

namespace Hanjiang\Tests\Feature;

use Hanjiang\Http\ExceptionHandler;
use Hanjiang\Model\FlowLog;
use OmniPHP\Http\Context;
use OmniPHP\Http\Kernel;
use PHPUnit\Framework\TestCase;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response as WorkermanResponse;

/**
 * HTTP 网关（server.php 的 onMessage = OmniPHP Kernel::handle + routes.php）对旧版客户端的约定：
 * 静态资源 / 动态 config.js / 接口分发 / CORS / 403 / 异常信封
 */
final class HttpGatewayTest extends TestCase
{
    private const array CORS = [
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
        'Access-Control-Allow-Headers' => '*',
    ];

    /**
     * 经 Kernel::handle 处理一个原始 HTTP 请求，返回发到连接上的响应
     */
    private function send(string $target, string $method = 'GET', array $headers = [], string $body = ''): WorkermanResponse
    {
        $connection = new class extends TcpConnection {
            /** @var list<mixed> */
            public array $sent = [];

            public function __construct()
            {
            }

            public function send(mixed $data = null, bool $raw = false): bool
            {
                $this->sent[] = $data;
                return true;
            }

            public function close(mixed $data = null, bool $raw = false): void
            {
            }

            public function getRemoteIp(): string
            {
                return '127.0.0.1';
            }
        };

        $headerLines = "Host: game.example:18100\r\n";
        if ($body !== '') {
            $headerLines .= "Content-Type: application/x-www-form-urlencoded\r\nContent-Length: " . strlen($body) . "\r\n";
        }
        foreach ($headers as $name => $value) {
            $headerLines .= "{$name}: {$value}\r\n";
        }

        ob_start();
        try {
            Kernel::handle($connection, new Request("{$method} {$target} HTTP/1.1\r\n{$headerLines}\r\n{$body}"));
        } finally {
            ob_end_clean();
        }

        $this->assertCount(1, $connection->sent, "{$method} {$target} 应只发送一个响应");
        $this->assertInstanceOf(WorkermanResponse::class, $connection->sent[0]);
        return $connection->sent[0];
    }

    /**
     * @return array<string, mixed>
     */
    private function json(WorkermanResponse $res): array
    {
        $json = json_decode((string)$res->rawBody(), true);
        $this->assertIsArray($json, (string)$res->rawBody());
        return $json;
    }

    public function testApiEntryPointsShareOnePipeline(): void
    {
        foreach (['/index.php?c=ios&m=update_world_common', '/?c=ios&m=update_world_common', '/touch.js?c=ios&m=update_world_common'] as $target) {
            $res = $this->send($target);
            $this->assertSame(200, $res->getStatusCode(), $target);
            $this->assertEquals(self::CORS + ['Content-Type' => 'application/json; charset=utf-8'], $res->getHeaders(), $target);
            $this->assertSame(1, $this->json($res)['status'], $target);
            $this->assertStringNotContainsString('\/', (string)$res->rawBody(), 'JSON 不转义斜杠');
        }

        $post = $this->send('/index.php', 'POST', [], 'c=ios&m=update_world_common');
        $this->assertSame(1, $this->json($post)['status']);

        // 未覆盖的接口：平滑降级信封
        $fallback = $this->json($this->send('/foo/bar.php?c=index&m=nosuch'));
        $this->assertSame(1, $fallback['is_fallback']);
    }

    public function testCorsPreflightAndAuthRejection(): void
    {
        $preflight = $this->send('/index.php?c=ios', 'OPTIONS');
        $this->assertSame(204, $preflight->getStatusCode());
        $this->assertEquals(self::CORS, $preflight->getHeaders());
        $this->assertSame('', (string)$preflight->rawBody());

        $denied = $this->send('/index.php?c=member&m=index&token=forged&uid=1');
        $this->assertSame(200, $denied->getStatusCode());
        $this->assertEquals(self::CORS + ['Content-Type' => 'text/plain; charset=utf-8'], $denied->getHeaders());
        $this->assertSame('403', (string)$denied->rawBody());
    }

    public function testDynamicConfigJs(): void
    {
        $res = $this->send('/config.js?v=1');
        $this->assertSame(200, $res->getStatusCode());
        $this->assertEquals(['Content-Type' => 'application/javascript; charset=utf-8', 'Cache-Control' => 'no-cache'], $res->getHeaders());
        $this->assertStringContainsString("wsUrl: 'ws://game.example:18101'", (string)$res->rawBody());
        // 动态生成，不是直出 public/config.js
        $this->assertNotSame(file_get_contents(dirname(__DIR__, 2) . '/public/config.js'), (string)$res->rawBody());

        $tls = $this->send('/config.js', 'GET', ['X-Forwarded-Proto' => 'https']);
        $this->assertStringContainsString("wsUrl: 'wss://game.example:18101'", (string)$tls->rawBody());
    }

    public function testStaticAssets(): void
    {
        $public = dirname(__DIR__, 2) . '/public';

        $autoload = $this->send('/static/js/autoload.js?v=1');
        $this->assertEquals(['Content-Type' => 'application/javascript; charset=utf-8', 'Cache-Control' => 'no-cache'], $autoload->getHeaders());
        $this->assertSame(file_get_contents($public . '/static/js/autoload.js'), (string)$autoload->rawBody());

        $index = $this->send('/');
        $this->assertSame(200, $index->getStatusCode());
        $this->assertSame('text/html; charset=utf-8', $index->getHeader('Content-Type'));
        $this->assertSame('public, max-age=86400', $index->getHeader('Cache-Control'));
        $this->assertArrayNotHasKey('Access-Control-Allow-Origin', $index->getHeaders());
        $this->assertSame(file_get_contents($public . '/index.html'), (string)$index->rawBody());

        $etag = (string)$index->getHeader('ETag');
        $this->assertNotSame('', $etag);
        $this->assertSame(304, $this->send('/index.html', 'GET', ['If-None-Match' => $etag])->getStatusCode());

        // 不存在的文件不是 404：与旧版一致，进接口管道（c 缺省为 index）
        $missing = $this->send('/static/img/nope.png');
        $this->assertSame(200, $missing->getStatusCode());
        $this->assertSame(1, $this->json($missing)['status']);
    }

    public function testUncaughtExceptionUsesGameEnvelope(): void
    {
        ob_start();
        try {
            $res = (new ExceptionHandler())->handle(new \RuntimeException('boom'), Context::mock(['c' => 'x']));
        } finally {
            ob_end_clean();
        }
        $this->assertSame(500, $res->getStatusCode());
        $this->assertSame(['Content-Type' => 'application/json; charset=utf-8'], $res->getHeaders());
        $this->assertSame('{"status":-500,"message":"系统异常: boom"}', (string)$res->rawBody());
    }

    public function testFlowLogRouteIsFiberLocal(): void
    {
        FlowLog::route('outer', 'main');
        $seen = [];
        $a = new \Fiber(static function () use (&$seen): void {
            FlowLog::route('arena', 'pk');
            \Fiber::suspend();
            $seen['a'] = FlowLog::current();
        });
        $b = new \Fiber(static function () use (&$seen): void {
            FlowLog::route('shop', 'buy');
            \Fiber::suspend();
            $seen['b'] = FlowLog::current();
        });
        $a->start();
        $b->start();
        $b->resume();
        $a->resume();

        try {
            $this->assertSame(['arena', 'pk'], $seen['a']);
            $this->assertSame(['shop', 'buy'], $seen['b']);
            $this->assertSame(['outer', 'main'], FlowLog::current());
        } finally {
            FlowLog::route('', '');
        }
    }
}
