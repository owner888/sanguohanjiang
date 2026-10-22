<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;

use OmniPHP\Http\Context;
use OmniPHP\Database\DB;
use OmniPHP\Cache\Redis;
use OmniPHP\WebSocket\Broadcaster;

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
        $marquee = Redis::getJson('broadcast_history') ?: [];

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
            'marquee' => array_slice($marquee, -5),
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

        if ($msg === '') {
            return [
                'status' => 0,
                'message' => '聊天内容不能为空',
            ];
        }

        $member = DB::table('member')->where('uid', $uid)->first();
        $senderName = $member['nickname'] ?? ('主公' . $uid);

        $newMsg = [
            'uid' => $uid,
            'name' => $senderName,
            'message' => htmlspecialchars($msg),
            'time' => time(),
        ];

        $messages = Redis::getJson('chat_messages') ?: [];
        $messages[] = $newMsg;
        if (count($messages) > 30) {
            $messages = array_slice($messages, -30);
        }
        Redis::setJson('chat_messages', $messages, 86400);

        // 原生 WebSocket 实时广播给所有在线全双工客户端
        Broadcaster::broadcast('chat_message', $newMsg);

        return [
            'status' => 1,
            'message' => '发送成功',
            'data' => $newMsg,
        ];
    }
}
