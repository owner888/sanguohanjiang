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
     * 获取全局聊天消息池
     *
     * @return list<array<string, mixed>>
     */
    private static function getMessages(): array
    {
        $cached = Redis::getJson('chat_messages');
        if (is_array($cached) && !empty($cached)) {
            return $cached;
        }

        // 默认第一条系统公告
        $initial = [
            [
                'id' => 1,
                'uid' => 0,
                'nickname' => '系统公告',
                'name' => '系统公告',
                'type' => 7, // 7为系统公告类型
                'system' => 1,
                'message' => '欢迎来到悍将三国 Modern Edition！天下风云出我辈，一入江湖岁月催。',
                'time' => time(),
            ],
        ];
        Redis::setJson('chat_messages', $initial, 86400);
        return $initial;
    }

    /**
     * 初始拉取聊天全量公共频道: c=chat&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $messages = self::getMessages();

        // 初始化时，将该玩家的游标同步到最新一条消息，防止随后的增量轮询重复追加历史记录
        if (!empty($messages)) {
            $latestId = (int)($messages[count($messages) - 1]['id'] ?? 1);
            if ($uid > 0 && $latestId > 0) {
                Redis::set("chat_last_read:{$uid}", (string)$latestId, 86400);
            }
        }

        $marquee = Redis::getJson('broadcast_history') ?: [];

        return [
            'status' => 1,
            'list' => $messages,
            'marquee' => array_slice($marquee, -5),
            'message' => 'ok',
        ];
    }

    /**
     * 增量拉取新聊天消息: c=chat&m=read
     * 严格遵循基于自增 ID 游标的增量消费同步模型，杜绝 DOM 重复追加
     */
    public static function read(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $lastReadId = (int)(Redis::get("chat_last_read:{$uid}") ?? 0);
        $messages = self::getMessages();
        $marquee = Redis::getJson('broadcast_history') ?: [];

        if (empty($messages)) {
            return [
                'status' => 1,
                'list' => [],
                'marquee' => array_slice($marquee, -5),
                'message' => 'ok',
            ];
        }

        // 若未记录过游标（如直接调用 read），首次拉取全量并初始化游标
        if ($lastReadId === 0) {
            $latestId = (int)($messages[count($messages) - 1]['id'] ?? 1);
            if ($uid > 0 && $latestId > 0) {
                Redis::set("chat_last_read:{$uid}", (string)$latestId, 86400);
            }
            return [
                'status' => 1,
                'list' => $messages,
                'marquee' => array_slice($marquee, -5),
                'message' => 'ok',
            ];
        }

        // 仅筛选大于当前游标的新消息
        $newMessages = [];
        foreach ($messages as $msg) {
            if (($msg['id'] ?? 0) > $lastReadId) {
                $newMessages[] = $msg;
            }
        }

        // 推进玩家游标至最新新消息
        if (!empty($newMessages)) {
            $latestId = (int)($newMessages[count($newMessages) - 1]['id'] ?? $lastReadId);
            if ($uid > 0 && $latestId > 0) {
                Redis::set("chat_last_read:{$uid}", (string)$latestId, 86400);
            }
        }

        return [
            'status' => 1,
            'list' => $newMessages,
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

        $seq = (int)(Redis::incr('chat_msg_seq') ?? (time() * 1000));
        $newMsg = [
            'id' => $seq,
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

        // 核心关键：发送者本人的游标立即与该条新消息对齐，防止紧随其后的 read 增量轮询再次重复推送给自己
        if ($uid > 0) {
            Redis::set("chat_last_read:{$uid}", (string)$seq, 86400);
        }

        // 原生 WebSocket 实时广播给所有其他在线客户端
        Broadcaster::broadcast('chat_message', $newMsg);

        return [
            'status' => 1,
            'message' => '发送成功',
            'msg' => '发送成功',
            'list' => [$newMsg], // 客户端 chatpop.js:611 核心依赖 res.list 渲染当前发送项
            'data' => $newMsg,
        ];
    }
}
