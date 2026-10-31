<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\Pvp\CountryHandler;
use Hanjiang\Model\Chat;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 聊天室（移植旧版 controllers/chat.php：index / send / read / getuid / ban_uid）
 *
 * 客户端 cd.js 启动时调 index 取公共 + 国家队列，之后每 5 ~ 30 秒轮询 read 取自己的私人队列；GameWS 鉴权成功后
 * 改由 ws 进程推送同样的 read 结果（{@see \Hanjiang\WebSocket\ChatPush}），断线后恢复轮询。存储与规则见 {@see Chat}。
 *
 * 发言（send）：主公 50 级以上；内容 htmlspecialchars 后不超过 200 字节；3 秒间隔；后台屏蔽词替换成 ***。
 * 去掉符号后含聊天屏蔽字、或与 5 分钟内上次发的内容相同时按 status 2 处理；禁言（member.status 1 / 2）同样处理：
 * 世界频道只发给自己、国家频道只进国家队列和自己、私聊不发，客户端照常显示发送成功。
 *
 * 未移植：
 * - index 的战神上线公告（chat_model->god_login + transfer_model 通知跨服，依赖未移植：战神殿 god）；
 * - send 的神树活动 tree（依赖未移植：sacredtree_model->get_tree_by_uid，tree 恒为 0）；
 * - index / read 的 platform = android / wp8 原生客户端格式转换（H5 客户端不传 platform）；
 * - send_system_chat（旧版不校验身份即可发全服系统消息）、clear_ban_key（运维口令接口），客户端都不调用；
 *   服务端代码发系统消息直接用 Chat::sendToAll(…, 7)。
 */
final class ChatHandler
{
    /** 发言限制等级（旧版 $_limit_level） */
    private const int LIMIT_LEVEL = 50;
    /** 信息最大字节数 */
    private const int MAX_LENGTH = 200;

    /** 旧版语言包 olderror_lang.php */
    private const string LANG_WORLD_ERROR2 = '消息不能为空！';
    private const string LANG_WORLD_ERROR3 = '消息太长！';
    private const string LANG_WORLD_ERROR4 = '发送太快！';
    private const string LANG_WORLD_ERROR5 = '未加入国家，无法发送！';

    /**
     * 取公共队列消息: c=chat&m=index（国家队列在前，去掉我屏蔽的人在公共队列里的消息）
     */
    public static function index(Context $ctx): array
    {
        $return = ['status' => 0, 'list' => []];
        $uid = MemberHandler::resolveUid($ctx);
        $member = Chat::sender($uid);
        if ($member === null) {
            return $return;
        }
        $return['list'] = self::publicList($member);
        // 依赖未移植：每月 15 日以外，战神（member_model->get_god / transfer_model->get_god_info）本人上线时
        // chat_model->god_login 发全服喇叭 + 系统公告并通知跨服（战神殿 god 未移植）
        $return['status'] = 1;
        return $return;
    }

    /**
     * 发送消息: c=chat&m=send（message 内容；uid 私聊对象，country = 1 国家频道，都不传为世界频道）
     *
     * status：6 等级不足；2 内容为空；3 内容太长；4 发送太快；5 未加入国家；1 成功（list 为发信人的 read 结果）
     */
    public static function send(Context $ctx): array
    {
        $return = ['status' => 0, 'message' => '', 'list' => []];
        $uid = MemberHandler::resolveUid($ctx);
        $row = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        $member = Chat::sender($uid);
        if ($row === null || $member === null) {
            return $return;
        }

        $touid = (int)$ctx->input('uid', 0);
        $country = (int)$ctx->input('country', 0);
        $message = htmlspecialchars((string)$ctx->input('message', ''), ENT_COMPAT | ENT_HTML401, 'UTF-8');
        if ((int)$row['level'] < self::LIMIT_LEVEL) {
            // 50 级以下不能发言
            $return['status'] = 6;
            $return['msg'] = sprintf(Chat::LANG['chat_send_need_level'], self::LIMIT_LEVEL);
            return $return;
        }
        if ($message === '') {
            // 信息内容为空
            $return['status'] = 2;
            $return['msg'] = self::LANG_WORLD_ERROR2;
            return $return;
        }
        if (strlen($message) > self::MAX_LENGTH) {
            // 信息过长
            $return['status'] = 3;
            $return['msg'] = self::LANG_WORLD_ERROR3;
            return $return;
        }
        if (Chat::checkDelay($uid)) {
            // 发送太快
            $return['status'] = 4;
            $return['msg'] = self::LANG_WORLD_ERROR4;
            return $return;
        }
        if ((int)$row['country'] === 0 && $country > 0) {
            // 未加入国家
            $return['status'] = 5;
            $return['msg'] = self::LANG_WORLD_ERROR5;
            return $return;
        }

        // 依赖未移植：神树活动中拥有的树（sacredtree_model->get_tree_by_uid），tree 恒为 0
        $tree = 0;
        $status = self::memberStatus($row);
        $message = Chat::checkBadword($message);

        $msg = str_replace(Chat::STRIP_CHARS, '', $message);
        foreach (Chat::chatBanWords() as $value) {
            if (str_contains(strtolower($msg), $value)) {
                // 含屏蔽字：按禁言处理（旧版记录到 illegal_chat 的调用已注释掉）
                $status = 2;
            }
        }
        // 如果该条信息没有被屏蔽，判断是否是重复信息
        if ($status !== 2) {
            // 获取上次发送的语言
            if ($msg == str_replace(Chat::STRIP_CHARS, '', (string)Chat::getLastSendMessage($uid))) {
                $status = 2;
            }
        }

        if ($touid) {
            // 私聊
            if ($status === 0) {
                Chat::sendToUid($message, $touid, Chat::TYPE_PRIVATE, $tree, $member);
            }
        } elseif ($country) {
            // 国家：禁言 / 屏蔽时只发给自己
            $uidArr = [$uid];
            if ($status === 0) {
                $uidArr = array_map(static fn(array $v): int => (int)$v['uid'], CountryHandler::memberList((int)$row['country'], 1, 0));
            }
            Chat::sendToCountry($message, $uidArr, Chat::TYPE_COUNTRY, 0, 0, $tree, $member);
        } elseif ($status === 2 || $status === 1) {
            // 公共：禁言仅自己可见
            Chat::sendToUid($message, $uid, Chat::TYPE_PUBLIC, $tree, $member);
        } else {
            Chat::sendToAll($message, Chat::TYPE_PUBLIC, 0, $tree, $member);
        }
        $return['status'] = 1;
        $return['list'] = Chat::readMessage($uid, $member['nickname']);
        return $return;
    }

    /**
     * 读取消息: c=chat&m=read（first = 1 为客户端恢复后第一次读取，不取消息，避免重复）
     */
    public static function read(Context $ctx): array
    {
        $return = ['status' => 0, 'list' => []];
        $uid = MemberHandler::resolveUid($ctx);
        $member = Chat::sender($uid);
        if ($member === null) {
            return $return;
        }
        if ((string)$ctx->input('first', '') !== '1') {
            $return['list'] = Chat::filterBanned(Chat::readMessage($uid, $member['nickname']), $uid);
        }
        $return['status'] = 1;
        return $return;
    }

    /**
     * 按照昵称查找用户: c=chat&m=getuid（name 昵称）
     *
     * status：1 成功；2 用户不存在
     */
    public static function getuid(Context $ctx): array
    {
        $return = ['status' => 0, 'uid' => 0];
        $nickname = (string)$ctx->input('name', '');
        $uid = (int)(DB::table('member')->where('nickname', $nickname)->whereNull('deleted_at')->value('uid') ?? 0);
        if ($uid) {
            $return['status'] = 1;
            $return['uid'] = $uid;
        } else {
            $return['status'] = 2;
        }
        return $return;
    }

    /**
     * 屏蔽某人 24 小时: c=chat&m=ban_uid（touid）
     *
     * status：-1 已屏蔽过；1 成功（list 同 index，已去掉此人的消息）
     */
    public static function ban_uid(Context $ctx): array
    {
        $return = ['status' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        $member = Chat::sender($uid);
        if ($member === null) {
            return $return;
        }
        $touid = (int)$ctx->input('touid', 0);
        if (Chat::banKey($touid, $uid)) {
            // 请不要重复操作
            $return['status'] = -1;
            return $return;
        }
        // 添加成功
        Chat::addBanUid($touid, $uid);
        $return['list'] = self::publicList($member);
        $return['status'] = 1;
        return $return;
    }

    /**
     * 国家队列 + 公共队列（公共队列去掉我屏蔽的人发的消息）
     *
     * @param array{uid: int, nickname: string, country: int, rank: int} $member
     * @return list<array<string, mixed>>
     */
    private static function publicList(array $member): array
    {
        $country = Chat::readCountry($member['country']);
        $list = Chat::filterBanned(Chat::readPublicMessage(), $member['uid']);
        return array_merge($country, $list);
    }

    /**
     * 禁言状态（ZC_Controller::_init_member：禁言到期则清除）；0 正常、1 禁言、2 永久禁言
     *
     * @param array<string, mixed> $row member 行
     */
    private static function memberStatus(array $row): int
    {
        $status = (int)($row['status'] ?? 0);
        $statustime = (int)($row['statustime'] ?? 0);
        if ($status && $statustime && $statustime < time()) {
            // 清除状态
            DB::table('member')->where('uid', (int)$row['uid'])->update(['status' => 0, 'statustime' => 0]);
            $status = 0;
        }
        return $status;
    }
}
