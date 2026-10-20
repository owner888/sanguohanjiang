<?php

declare(strict_types=1);

namespace Hanjiang\Handler;

use Hanjiang\Context;
use Hanjiang\Database\Redis;

final class ChatHandler
{
    /**
     * 聊天频道拉取: c=chat&m=index 或 m=read
     */
    public static function index(Context $ctx): array
    {
        return self::read($ctx);
    }

    public static function read(Context $ctx): array
    {
        $cached = Redis::getJson('chat_messages');

        return [
            'status' => 1,
            'list' => is_array($cached) ? $cached : [
                [
                    'uid' => 0,
                    'name' => '系统公告',
                    'message' => '欢迎来到悍将三国 Modern Edition！天下风云出我辈，一入江湖岁月催。',
                    'time' => time(),
                ],
            ],
            'message' => 'ok',
        ];
    }

    /**
     * 发送聊天: c=chat&m=send
     */
    public static function send(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $msg = trim((string)$ctx->input('message', ''));

        $messages = Redis::getJson('chat_messages') ?: [];
        $messages[] = [
            'uid' => $uid,
            'name' => '主公' . $uid,
            'message' => htmlspecialchars($msg),
            'time' => time(),
        ];
        if (count($messages) > 30) {
            $messages = array_slice($messages, -30);
        }
        Redis::setJson('chat_messages', $messages, 86400);

        return [
            'status' => 1,
            'message' => '发送成功',
        ];
    }
}
