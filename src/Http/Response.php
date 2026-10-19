<?php

declare(strict_types=1);

namespace Hanjiang\Http;

use Workerman\Protocols\Http\Response as WorkermanResponse;

final class Response
{
    /**
     * @param array<string, mixed> $data
     */
    public static function json(array $data, int $status = 200): WorkermanResponse
    {
        return new WorkermanResponse(
            $status,
            [
                'Content-Type' => 'application/json; charset=utf-8',
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
                'Access-Control-Allow-Headers' => '*',
            ],
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    public static function success(array $data = [], string $msg = 'ok'): WorkermanResponse
    {
        return self::json(array_merge([
            'status' => 1,
            'message' => $msg,
            'msg' => $msg,
        ], $data));
    }

    public static function error(string $msg, int $status = 0, int $httpCode = 200): WorkermanResponse
    {
        return self::json([
            'status' => $status,
            'message' => $msg,
            'msg' => $msg,
        ], $httpCode);
    }
}
