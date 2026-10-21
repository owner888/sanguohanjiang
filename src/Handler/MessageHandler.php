<?php

declare(strict_types=1);

namespace Hanjiang\Handler;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

final class MessageHandler
{
    /**
     * 邮件信箱列表: c=message&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $key = 'user_messages:' . $uid;
        $messages = Redis::getJson($key);

        if (!is_array($messages)) {
            $messages = [
                1 => [
                    'id' => 1,
                    'sender' => '三国朝廷',
                    'title' => '天下大赦与开国封赏',
                    'content' => '恭喜主公建功立业，朝廷特敕封赏，赐粮饷军资！',
                    'read' => 0,
                    'time' => time() - 3600,
                ],
                2 => [
                    'id' => 2,
                    'sender' => '军师诸葛亮',
                    'title' => '九宫阵法推演密报',
                    'content' => '主公，亮夜观天象，破军星动，请尽早排布战位，以应大敌！',
                    'read' => 1,
                    'time' => time() - 7200,
                ],
            ];
            Redis::setJson($key, $messages, 86400 * 30);
        }

        return [
            'status' => 1,
            'list' => $messages,
            'unread_count' => count(array_filter($messages, fn($m) => (int)$m['read'] === 0)),
            'message' => 'ok',
        ];
    }

    /**
     * 读取单封邮件详情: c=message&m=get_message
     */
    public static function get_message(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $mid = (int)($ctx->input('id', $ctx->input('mid', 1)));

        $key = 'user_messages:' . $uid;
        $messages = Redis::getJson($key) ?: [];

        if (!isset($messages[$mid])) {
            return [
                'status' => 0,
                'message' => '信件不存在或已被销毁',
            ];
        }

        $messages[$mid]['read'] = 1;
        Redis::setJson($key, $messages, 86400 * 30);

        return [
            'status' => 1,
            'info' => $messages[$mid],
            'message' => 'ok',
        ];
    }

    /**
     * 发送私信邮件: c=message&m=send_message
     */
    public static function send_message(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->first();
        $senderName = $member['nickname'] ?? ('主公' . $uid);

        $targetUid = (int)($ctx->input('target_uid', $ctx->input('touid', 0)));
        $title = trim((string)$ctx->input('title', '飞鸽传书'));
        $content = trim((string)$ctx->input('content', ''));

        if ($content === '') {
            return ['status' => 0, 'message' => '信件内容不能为空'];
        }

        if ($targetUid <= 0) {
            return ['status' => 0, 'message' => '收件人无效'];
        }

        $targetKey = 'user_messages:' . $targetUid;
        $targetMsgs = Redis::getJson($targetKey) ?: [];
        $newId = count($targetMsgs) + 1;

        $targetMsgs[$newId] = [
            'id' => $newId,
            'sender' => $senderName,
            'title' => htmlspecialchars($title),
            'content' => htmlspecialchars($content),
            'read' => 0,
            'time' => time(),
        ];

        Redis::setJson($targetKey, $targetMsgs, 86400 * 30);

        return [
            'status' => 1,
            'message' => '飞鸽传书已成功送达对方信箱！',
        ];
    }

    /**
     * 删除邮件: c=message&m=del_message
     */
    public static function del_message(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $mid = (int)($ctx->input('id', $ctx->input('mid', 0)));

        $key = 'user_messages:' . $uid;
        $messages = Redis::getJson($key) ?: [];

        if (isset($messages[$mid])) {
            unset($messages[$mid]);
            Redis::setJson($key, $messages, 86400 * 30);
        }

        return [
            'status' => 1,
            'message' => '邮件已成功删除',
        ];
    }
}
