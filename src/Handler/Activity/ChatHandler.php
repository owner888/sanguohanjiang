<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
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

        $list = is_array($cached) ? $cached : [
            [
                'uid' => 0,
                'nickname' => '系统公告',
                'name' => '系统公告',
                'type' => 7, // 7为系统公告类型
                'system' => 1,
                'message' => '欢迎来到悍将三国 Modern Edition！天下风云出我辈，一入江湖岁月催。',
                'time' => time(),
            ],
        ];

        return [
            'status' => 1,
            'list' => $list,
            'marquee' => array_slice($marquee, -5),
            'message' => 'ok',
        ];
    }

    /**
     * 发送聊天: c=chat&m=send
     * 严格对齐原版 H5 客户端 chatpop.js 期望协议
     */
    public static function send(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $msg = trim((string)$ctx->input('message', ''));
        $chatType = (int)$ctx->input('type', 0); // 0世界 1私聊 6国家

        if ($msg === '') {
            return [
                'status' => 2,
                'message' => '聊天内容不能为空',
                'msg' => '聊天内容不能为空',
            ];
        }

        if (mb_strlen($msg) > 60) {
            return [
                'status' => 3,
                'message' => '信息长度不能超过60个字符',
                'msg' => '信息长度不能超过60个字符',
            ];
        }

        $member = DB::table('member')->where('uid', $uid)->first();
        $senderName = $member['nickname'] ?? ('主公' . $uid);

        $newMsg = [
            'uid' => $uid,
            'nickname' => $senderName,
            'name' => $senderName,
            'type' => $chatType, // 0世界公共
            'system' => 0,
            'message' => htmlspecialchars($msg),
            'time' => time(),
        ];

        $messages = Redis::getJson('chat_messages') ?: [];
        $messages[] = $newMsg;
        if (count($messages) > 50) {
            $messages = array_slice($messages, -50);
        }
        Redis::setJson('chat_messages', $messages, 86400);

        // 原生 WebSocket 实时广播给所有在线全双工客户端
        Broadcaster::broadcast('chat_message', $newMsg);

        return [
            'status' => 1,
            'message' => '发送成功',
            'msg' => '发送成功',
            'list' => [$newMsg], // 客户端 chatpop.js:611 核心依赖 res.list 渲染并追加到 DOM
            'data' => $newMsg,
        ];
    }
}
