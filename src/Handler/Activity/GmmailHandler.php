<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Model\Message;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 新 GM 信箱（移植旧版 controllers/gmmail.php，信件存 gm 表，见 Model\Message）
 *
 * 客户端 mailboxpop.js 调用 get_message（列表）、index（读单封）、send_message（反馈）、del_message、grade（给 GM 回复评分）。
 * GM 回复（message_model->reply）是后台功能，不移植；没有后台时 reply / reply_uid 恒为空，grade 一律 -3。
 * 不移植：platform 为 android 的原生客户端文本转换（H5 客户端不传）。
 */
final class GmmailHandler
{
    /** 旧版语言包 message_lang.php / base_lang.php */
    private const string LANG_MESSAGE_GM_REPLAY = '以下是GM回复内容';
    private const string LANG_BASE_MONTH = '月';
    private const string LANG_BASE_DAY = '日';

    /** 新 GM 信箱的收件人 uid */
    private const int GM_UID = 1000;

    /**
     * 按条数查询信件: c=gmmail&m=index（id 信件 id；只查自己发出的）
     */
    public static function index(Context $ctx): array
    {
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return ['status' => 0, 'list' => []];
        }
        $id = (int)$ctx->input('id', 0);
        // 信件方向 1 发件箱，查看新邮箱信息（type 3）
        $return = Message::pmBuyNum($member, 3, $id, 1);
        if ($id > 0 && $return['status'] === 1) {
            $list = $return['list'];
            $list['name'] = $list['toname'];
            if ($list['reply'] !== '') {
                // 以下是GM回复内容
                $list['message'] .= '<br/>----' . self::LANG_MESSAGE_GM_REPLAY . '----<br/>';
                $list['message'] .= $list['reply'];
            }
            $list['dateline'] = date('m' . self::LANG_BASE_MONTH . 'd' . self::LANG_BASE_DAY . ' H:i', $list['dateline']);
            $list['isread'] = 1;
            foreach (['toname', 'reply', 'fromname', 'touid'] as $value) {
                unset($list[$value]);
            }
            $return['list'] = $list;
        }
        return $return;
    }

    /**
     * 查询新GM信箱: c=gmmail&m=get_message（p 页码，per 每页条数）
     */
    public static function get_message(Context $ctx): array
    {
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return ['status' => 0, 'list' => []];
        }
        $page = (int)$ctx->input('p', 0);
        $perpage = (int)$ctx->input('per', 0);
        $page = $page > 0 ? $page : 1;
        // type 5 新GM信箱，from 1 发件箱
        $message = Message::byType($member['uid'], 5, 1, $page, $perpage);
        if ($message['status'] !== 1) {
            return $message;
        }
        $list = [];
        foreach ($message['list'] as $key => $val) {
            $list[$key] = [
                'id' => $val['id'],
                'isread' => $val['isread'],
                'status' => $val['status'],
                'type' => $val['type'],
                'name' => $val['toname'],
                'message' => Message::substr($val['message']),
            ];
        }
        $message['list'] = $list;
        // 取第一条信息
        if ($page <= 1) {
            $first = reset($list);
            $message['list'][0]['isread'] = 1;
            $info = Message::pmBuyNum($member, 3, $first['id'], 1)['list'];
            $newlist = [];
            foreach (['id', 'type', 'isread', 'status', 'message', 'dateline'] as $val) {
                $newlist[$val] = $info[$val] ?? null;
            }
            $newlist['name'] = $info['toname'] ?? null;
            // 旧版此处用 12 小时制 h
            $newlist['dateline'] = date('m' . self::LANG_BASE_MONTH . 'd' . self::LANG_BASE_DAY . ' h:i', (int)($info['dateline'] ?? 0));
            if (($info['reply'] ?? '') !== '') {
                $newlist['message'] .= '<br /><br />----' . self::LANG_MESSAGE_GM_REPLAY . '----<br /><br />';
                $newlist['message'] .= $info['reply'];
            }
            unset($newlist['isread']);
            $message['info'] = $newlist;
        }
        return $message;
    }

    /**
     * 给 GM 发反馈: c=gmmail&m=send_message（i 内容）
     */
    public static function send_message(Context $ctx): array
    {
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return ['status' => 0];
        }
        $now = time();
        $message = htmlspecialchars((string)$ctx->input('i', ''), ENT_COMPAT);
        // 判断用户是否被禁言（禁言用户发出去的信件，默认状态是收件方删除）
        $status = 0;
        if ($member['status'] === 2 || ($member['status'] === 1 && $member['statustime'] > $now)) {
            $status = 3;
        }
        $id = Message::send([
            'touid' => self::GM_UID,
            'toname' => 'GM',
            'type' => 6,
            'message' => $message,
            'status' => $status,
            'title' => 'VIP' . $member['vip'],
            'new' => 1,
        ], $member, $now);
        return [
            'id' => ['id' => $id],
            'status' => 1,
            'dateline' => date('m' . self::LANG_BASE_MONTH . 'd' . self::LANG_BASE_DAY . ' H:i', $now),
            'all_gold' => $member['gold'],
        ];
    }

    /**
     * 删除信件: c=gmmail&m=del_message（id 信件 id，0 为全部）
     */
    public static function del_message(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        if ($uid <= 0) {
            return ['status' => 0];
        }
        // type 4 新GM信箱，from 1 发件箱
        return Message::del($uid, (int)$ctx->input('id', 0), 4, 1);
    }

    /**
     * 评分: c=gmmail&m=grade（id 信件 id，grade 1~5）
     */
    public static function grade(Context $ctx): array
    {
        /**
         * -4 已经评过分
         * -3 GM 还没有回复
         * -2 信件不存在
         * -1 分数不对
         *  1 成功
         */
        $return = ['status' => 0];
        if (MemberHandler::resolveUid($ctx) <= 0) {
            return $return;
        }
        $id = (int)$ctx->input('id', 0);
        $grade = (int)$ctx->input('grade', 0);
        if (!in_array($grade, [1, 2, 3, 4, 5], true)) {
            $return['status'] = -1;
            return $return;
        }
        // 查询该信件的信息（旧版不校验信件是不是自己的）
        $info = Message::gmById($id);
        if ($info === []) {
            $return['status'] = -2;
            return $return;
        }
        // 判断该信件是否已经回复过了
        if ($info['reply_uid'] === 0) {
            $return['status'] = -3;
            return $return;
        }
        // 判断该内容是否已经被评分
        if ($info['grade'] > 0) {
            $return['status'] = -4;
            return $return;
        }
        // 执行评分
        if (Message::updateGrade($id, $grade, time()) === 1) {
            $return['status'] = 1;
        }
        return $return;
    }

    /**
     * @return array{uid: int, nickname: string, vip: int, gold: int, status: int, statustime: int, newmail: int}|null
     */
    private static function member(int $uid): ?array
    {
        if ($uid <= 0) {
            return null;
        }
        $row = DB::table('member')->select('uid', 'nickname', 'vip', 'gold', 'status', 'statustime', 'newmail')
            ->where('uid', $uid)->whereNull('deleted_at')->first();
        return $row === null ? null : [
            'uid' => (int)$row['uid'],
            'nickname' => (string)$row['nickname'],
            'vip' => (int)$row['vip'],
            'gold' => (int)$row['gold'],
            'status' => (int)$row['status'],
            'statustime' => (int)$row['statustime'],
            'newmail' => (int)$row['newmail'],
        ];
    }
}
