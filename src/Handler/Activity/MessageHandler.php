<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 系统邮件、飞鸽传书与战报信箱处理器 (DDD Activity Domain)
 */
final class MessageHandler
{
    /**
     * 邮件信箱列表 / 单封信件查阅: c=message&m=index
     */
    public static function index(Context $ctx): array
    {
        $id = (int)($ctx->input('id', 0));
        if ($id > 0) {
            return self::getSingleMessage($ctx, $id);
        }

        return self::get_message($ctx);
    }

    /**
     * 邮件分类与分页列表: c=message&m=get_message
     */
    public static function get_message(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $id = (int)($ctx->input('id', 0));
        if ($id > 0) {
            return self::getSingleMessage($ctx, $id);
        }

        $type = (int)($ctx->input('t', 1));
        $from = (int)($ctx->input('f', 0)); // 0: 收件箱, 1: 发件箱
        $page = max(1, (int)($ctx->input('p', 1)));
        $perpage = max(1, min(50, (int)($ctx->input('per', 10))));

        $allMessages = self::getUserMessages($uid);

        // 过滤邮件方向 (收件/发件) 与类型
        $filtered = [];
        foreach ($allMessages as $msg) {
            $msgFrom = (int)($msg['from'] ?? 0);
            $msgType = (int)($msg['type'] ?? 1);

            if ($from === 1) {
                // 发件箱：发件人为自己的信件
                if ($msgFrom === 1 || ((int)($msg['fromuid'] ?? 0) === $uid && (int)($msg['touid'] ?? 0) !== $uid)) {
                    $filtered[] = $msg;
                }
            } else {
                // 收件箱：发给自己的信件
                if ($msgFrom === 0 || ((int)($msg['touid'] ?? 0) === $uid)) {
                    if ($type === 4) {
                        // 战报类型
                        if ($msgType === 4 || $msgType === 10) {
                            $filtered[] = $msg;
                        }
                    } else {
                        // 普通与系统信件
                        if ($msgType !== 4) {
                            $filtered[] = $msg;
                        }
                    }
                }
            }
        }

        // 按时间倒序排序
        usort($filtered, static fn(array $a, array $b): int => ((int)($b['dateline'] ?? 0)) <=> ((int)($a['dateline'] ?? 0)));

        $total = count($filtered);
        $offset = ($page - 1) * $perpage;
        $paged = array_slice($filtered, $offset, $perpage);

        $list = [];
        foreach ($paged as $val) {
            $isRead = (int)($val['isread'] ?? 0);
            $msgType = (int)($val['type'] ?? 1);
            $name = ($from === 1) ? (string)($val['toname'] ?? '受件人') : (string)($val['fromname'] ?? '发件人');

            $list[] = [
                'id' => (int)$val['id'],
                'name' => $name,
                'fromname' => (string)($val['fromname'] ?? '发件人'),
                'toname' => (string)($val['toname'] ?? '受件人'),
                'fromuid' => (int)($val['fromuid'] ?? 0),
                'message' => (string)($val['message'] ?? ''),
                'title' => (string)($val['title'] ?? ''),
                'isread' => $isRead,
                'status' => (int)($val['status'] ?? 0),
                'type' => $msgType,
                'dateline' => date('Y-m-d H:i', (int)($val['dateline'] ?? time())),
            ];
        }

        $firstInfo = !empty($list) ? [
            'id' => (int)$list[0]['id'],
            'name' => $list[0]['name'],
            'fromuid' => ($from === 0) ? (int)$list[0]['fromuid'] : 0,
            'message' => $list[0]['message'],
            'title' => $list[0]['title'] ?? '',
            'dateline' => $list[0]['dateline'],
            'type' => (int)$list[0]['type'],
            'read' => 1,
            'isread' => 1,
            'status' => 0,
            'giftstatus' => 0,
            'act' => 0,
            'silver' => 0,
            'gold' => 0,
            'barcard' => 0,
        ] : [
            'id' => 0,
            'name' => '',
            'fromuid' => 0,
            'message' => '',
            'title' => '',
            'dateline' => '',
            'type' => 1,
            'read' => 0,
            'isread' => 0,
            'status' => 0,
            'giftstatus' => 0,
            'act' => 0,
            'silver' => 0,
            'gold' => 0,
            'barcard' => 0,
        ];

        return [
            'status' => 1,
            'list' => $list,
            'info' => $firstInfo,
            'total' => $total,
            'page' => $page,
            'unread_count' => count(array_filter($allMessages, fn($m) => (int)($m['isread'] ?? 0) === 0)),
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
        $targetName = trim((string)$ctx->input('toname', ''));
        $title = trim((string)$ctx->input('title', '飞鸽传书'));
        $content = trim((string)$ctx->input('content', $ctx->input('message', '')));

        if ($content === '') {
            return ['status' => 0, 'message' => '信件内容不能为空'];
        }

        if ($targetUid <= 0 && $targetName !== '') {
            $targetMember = DB::table('member')->where('nickname', $targetName)->first();
            if ($targetMember !== null) {
                $targetUid = (int)$targetMember['uid'];
            }
        }

        if ($targetUid <= 0) {
            $targetUid = 1; // 默认发送给系统朝廷
            $targetName = '三国朝廷';
        }

        $now = time();
        $newMsgId = (int)(microtime(true) * 1000) % 10000000;

        // 1. 发送至目标收件人信箱
        $targetKey = 'user_messages:' . $targetUid;
        $targetMsgs = Redis::getJson($targetKey) ?: [];
        $targetMsgs[] = [
            'id' => $newMsgId,
            'fromuid' => $uid,
            'touid' => $targetUid,
            'fromname' => $senderName,
            'toname' => $targetName ?: ('玩家' . $targetUid),
            'title' => htmlspecialchars($title),
            'message' => htmlspecialchars($content),
            'isread' => 0,
            'status' => 0,
            'type' => 1,
            'from' => 0, // 对方视角为收件
            'dateline' => $now,
        ];
        Redis::setJson($targetKey, $targetMsgs, 86400 * 30);

        // 2. 存入自己发件箱
        $selfKey = 'user_messages:' . $uid;
        $selfMsgs = Redis::getJson($selfKey) ?: [];
        $selfMsgs[] = [
            'id' => $newMsgId + 1,
            'fromuid' => $uid,
            'touid' => $targetUid,
            'fromname' => $senderName,
            'toname' => $targetName ?: ('玩家' . $targetUid),
            'title' => htmlspecialchars($title),
            'message' => htmlspecialchars($content),
            'isread' => 1,
            'status' => 0,
            'type' => 1,
            'from' => 1, // 自己视角为发件
            'dateline' => $now,
        ];
        Redis::setJson($selfKey, $selfMsgs, 86400 * 30);

        return [
            'status' => 1,
            'message' => '飞鸽传书已成功送达对方信箱！',
        ];
    }

    /**
     * 投递系统类信件（旧版 message_model->send_message 中 type 1/2/7/8 的发件人取系统定义名字，fromuid=1000）
     *
     * @param list<int> $touids
     * @return int 信件 id
     */
    public static function sendSystemMessage(array $touids, int $type, string $fromname, string $message, int $report = 0): int
    {
        $now = time();
        $id = (int)(microtime(true) * 1000) % 10000000;
        foreach ($touids as $touid) {
            $key = 'user_messages:' . $touid;
            $messages = Redis::getJson($key) ?: [];
            $messages[] = [
                'id' => $id,
                'fromuid' => 1000,
                'touid' => $touid,
                'fromname' => $fromname,
                'toname' => '',
                'title' => '',
                'message' => $message,
                'isread' => 0,
                'status' => 0,
                'type' => $type,
                'report' => $report,
                'from' => 0,
                'dateline' => $now,
            ];
            Redis::setJson($key, $messages, 86400 * 30);
        }
        return $id;
    }

    /**
     * 删除邮件: c=message&m=del_message
     */
    public static function del_message(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $mid = (int)($ctx->input('id', $ctx->input('mid', 0)));
        $isAll = (int)($ctx->input('all', 0));

        $key = 'user_messages:' . $uid;
        $messages = Redis::getJson($key) ?: [];

        if ($isAll === 1) {
            $messages = [];
        } elseif ($mid > 0) {
            $messages = array_values(array_filter($messages, fn($m) => (int)($m['id'] ?? 0) !== $mid));
        }

        Redis::setJson($key, $messages, 86400 * 30);

        return [
            'status' => 1,
            'message' => '邮件已成功删除',
        ];
    }

    /**
     * 读取单封邮件详情
     */
    private static function getSingleMessage(Context $ctx, int $mid): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $from = (int)($ctx->input('f', 0));
        $messages = self::getUserMessages($uid);

        $found = null;
        foreach ($messages as &$msg) {
            if ((int)$msg['id'] === $mid) {
                $msg['isread'] = 1;
                $found = $msg;
                break;
            }
        }
        unset($msg);

        if ($found !== null) {
            Redis::setJson('user_messages:' . $uid, $messages, 86400 * 30);
            $name = ($from === 1) ? (string)($found['toname'] ?? '受件人') : (string)($found['fromname'] ?? '发件人');

            return [
                'status' => 1,
                'list' => [
                    'id' => (int)$found['id'],
                    'name' => $name,
                    'fromuid' => ($from === 0) ? (int)($found['fromuid'] ?? 0) : 0,
                    'message' => (string)($found['message'] ?? ''),
                    'title' => (string)($found['title'] ?? ''),
                    'dateline' => date('Y-m-d H:i', (int)($found['dateline'] ?? time())),
                    'isread' => 1,
                    'status' => 0,
                    'type' => (int)($found['type'] ?? 1),
                ],
                'info' => [
                    'id' => (int)$found['id'],
                    'name' => $name,
                    'fromuid' => ($from === 0) ? (int)($found['fromuid'] ?? 0) : 0,
                    'message' => (string)($found['message'] ?? ''),
                    'dateline' => date('Y-m-d H:i', (int)($found['dateline'] ?? time())),
                    'type' => (int)($found['type'] ?? 1),
                    'read' => 1,
                    'isread' => 1,
                    'status' => 0,
                    'giftstatus' => 0,
                    'act' => 0,
                    'silver' => 0,
                    'gold' => 0,
                    'barcard' => 0,
                ],
                'message' => 'ok',
            ];
        }

        return [
            'status' => 0,
            'list' => [],
            'info' => [],
            'message' => '信件不存在或已被销毁',
        ];
    }

    /**
     * 获取玩家邮件集合 (若为空则初始化新手欢迎信件)
     *
     * @return list<array<string, mixed>>
     */
    private static function getUserMessages(int $uid): array
    {
        $key = 'user_messages:' . $uid;
        $messages = Redis::getJson($key);

        if (!is_array($messages)) {
            $now = time();
            $messages = [
                [
                    'id' => 1,
                    'fromuid' => 0,
                    'touid' => $uid,
                    'fromname' => '三国朝廷',
                    'toname' => '天命主公',
                    'title' => '天下大赦与开国封赏',
                    'message' => '恭喜主公建功立业，朝廷特敕封赏，赐粮饷军资，祝主公早日荡平中原！',
                    'isread' => 0,
                    'status' => 0,
                    'type' => 3,
                    'from' => 0,
                    'dateline' => $now - 3600,
                ],
                [
                    'id' => 2,
                    'fromuid' => 0,
                    'touid' => $uid,
                    'fromname' => '军师诸葛亮',
                    'toname' => '天命主公',
                    'title' => '九宫阵法推演密报',
                    'message' => '主公，亮夜观天象，破军星动，请尽早排布战位，以应大敌！',
                    'isread' => 1,
                    'status' => 0,
                    'type' => 1,
                    'from' => 0,
                    'dateline' => $now - 7200,
                ],
                [
                    'id' => 3,
                    'fromuid' => 0,
                    'touid' => $uid,
                    'fromname' => '战况军情处',
                    'toname' => '天命主公',
                    'title' => '黄巾讨伐战报汇总',
                    'message' => '前锋大捷！大兴山战役我军大获全胜，缴获敌军精铁军资，全军士气大振！',
                    'isread' => 1,
                    'status' => 0,
                    'type' => 4,
                    'from' => 0,
                    'dateline' => $now - 14400,
                ],
            ];
            Redis::setJson($key, $messages, 86400 * 30);
        }

        return array_values($messages);
    }
}
