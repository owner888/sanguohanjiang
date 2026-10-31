<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\Pvp\CountryHandler;
use Hanjiang\Model\Member;
use Hanjiang\Model\Message;
use Hanjiang\Model\PassGeneral;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 信件（移植旧版 controllers/message.php + models/message_model.php，存储见 Model\Message）
 *
 * 客户端：主城气泡信件 mailpop.js（index 无 id 取未读气泡信件 / 有 id 读单封、update_read_status、战报 report_detail、
 * 附件 open_gift），邮箱 mailboxpop.js（get_message、index、send_message、del_message、get_notice），每日动态 trendspop.js
 * （get_new_trends、update_trends_status）。
 *
 * 依赖未移植：公告 get_notice 与每日动态 get_new_trends / update_trends_status 的内容由 uc 服务器 inform 接口提供
 * （uc_model->get_inform，uc 的 inform_model 不在旧版代码包里），按 uc 没有返回数据处理：get_notice 回 status 0，
 * get_new_trends 回 status 1、list 为空，update_trends_status 回 status 0。
 * 不移植：platform 为 android / wp8 的原生客户端文本转换（get_android_message / get_android_line，H5 客户端不传）；
 * 已注释掉的 test。
 */
final class MessageHandler
{
    /** 旧版语言包 message_lang.php / base_lang.php */
    private const string LANG_MESSAGE_GM_REPLAY = '以下是GM回复内容';
    private const string LANG_BASE_MONTH = '月';
    private const string LANG_BASE_DAY = '日';

    /** 发往 GM（旧 GM 信箱）的收件人 uid */
    private const int GM_UID = 10000;
    /** 发给别国的国家信件价格（元宝） */
    private const int COUNTRY_PRICE = 100;

    /** 关键字屏蔽（旧版 send_message 的 $bind_word） */
    private const array BIND_WORD = [
        '毛泽东', '周恩来', '孙中山', '刘少奇', '邓小平', '宋庆龄',
        '彭德怀', '刘伯承', '罗荣桓', '聂荣臻', '叶剑英', '习近平',
        '李克强', '张德江', '俞正声', '刘云山', '王岐山', '张高丽',
        '王沪宁', '刘延东', '刘奇葆', '许其亮', '孙春兰', '孙政才',
        '李建国', '李源潮', '张春贤', '范长龙', '孟建柱', '赵乐际',
        '胡春华', '栗战书', '郭金龙', '赵洪祝', '杜青林', '常万全',
        '房峰辉', '赵克石', '张又侠', '吴胜利', '马晓天', '魏凤和',
        '江泽民', '朱镕基', '胡锦涛', '温家宝', '吴邦国', '李长春',
        '贺国强', '周永康', '李肇星', '杨洁篪', '董必武', '杨尚昆',
        '李先念', '华国锋', '张国焘', '邓颖超', '赵紫阳', '王立军',
        '邓子恢', '乌兰夫', '薄熙来', '薄一波', '共产党', '悍将三国',
        '张阳', '马凯', '韩正', '杨晶', '汪洋', '鲁迅', '陈云', '李鹏',
        '朱德', '林彪', '贺龙', '陈毅', '粟裕', '贴吧', '微信', 'whatapps',
        '官方', '黑卡', '收費', '收费', '管理', '客服', '充值', '活动', '运营',
        '出售', '优惠', '淘宝', '交易', '联系', '加群', 'gm', 'vip', 'admin',
        '{', '}', '$', '<', '>', '\'', '"', '元=', '4282887', '1606790114', '24906045', 'qq',
        'taobao', '玩家群', '加q', '822725227', '70890995', '15867604357',
        'q谈', '付款', '钓鱼岛', '尖阁列岛',
    ];

    /** 单封信件不返回的字段 */
    private const array UNSET_FIELDS = ['toname', 'reply', 'fromname', 'touid'];

    /**
     * 按条数查询信件: c=message&m=index（id 信件 id，f 0 收件箱 / 1 发件箱，t 2 邮件 / 其他按气泡信件）
     *
     * 不传 id：返回未读的气泡信件 [类型 => [id…]]；传 id：读单封（标为已读，更新新信件提示）。
     */
    public static function index(Context $ctx): array
    {
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return ['status' => 0, 'list' => []];
        }
        $uid = $member['uid'];
        // 信件id
        $id = (int)$ctx->input('id', 0);
        // 信件方向(0是收件箱，1是发件箱)
        $from = (int)$ctx->input('f', 0);
        // 判断是信件还是邮件
        $type = (int)$ctx->input('t', 0) === 2 ? 2 : 1;
        $return = Message::pmBuyNum($member, $type, $id, $from);
        if ($id > 0 && $return['status'] === 1) {
            $list = $return['list'];
            if ($from === 1 || ($list['fromuid'] === $uid && $list['type'] !== 10)) {
                $list['name'] = $list['toname'];
                if ($list['type'] === 10) {
                    $list['king'] = $list['fromname'];
                }
            } elseif ($list['type'] === 10) {
                $list['name'] = $list['title'];
                $list['king'] = $list['fromname'];
            } else {
                $list['name'] = $list['fromname'];
            }
            if ($from === 1) {
                $list['fromuid'] = $list['touid'];
            }
            if ($list['reply'] !== '') {
                // 以下是GM回复内容
                $list['message'] .= '<br/>----' . self::LANG_MESSAGE_GM_REPLAY . '----<br/>';
                $list['message'] .= $list['reply'];
            }
            $list['dateline'] = date('Y-m-d H:i', $list['dateline']);
            $list['isread'] = 1;
            foreach (self::UNSET_FIELDS as $value) {
                unset($list[$value]);
            }
            $return['list'] = $list;
        }
        return $return;
    }

    /**
     * 查询公告: c=message&m=get_notice
     *
     * 依赖未移植：公告由 uc 服务器 inform 接口（type 1）提供，按 uc 没有返回数据处理（旧版 status 0）。
     */
    public static function get_notice(Context $ctx): array
    {
        return ['status' => 0];
    }

    /**
     * 获取每日动态提示: c=message&m=get_new_trends
     *
     * 依赖未移植：每日动态由 uc 服务器 inform 接口（type 2）提供；uc 没有返回数据时旧版照样回 status 1，list 为空。
     */
    public static function get_new_trends(Context $ctx): array
    {
        return ['status' => 1, 'list' => null];
    }

    /**
     * 不再提示操作: c=message&m=update_trends_status
     *
     * 依赖未移植：取 uc 每日动态的 id 写 member_field.trends_times；uc 没有返回数据时旧版不写，回 status 0。
     */
    public static function update_trends_status(Context $ctx): array
    {
        return ['status' => 0];
    }

    /**
     * 邮件系统信息查询: c=message&m=get_message（t 1 私人信件 / 2 GM 信件 / 4 国家信箱 / 其他系统，f 0 收件箱 / 1 发件箱，
     * p 页码，per 每页条数）
     *
     * list 为该页信件（内容截取 20 个宽度）；第一页时 info 为第一封信件的全文（读取并标为已读）。
     */
    public static function get_message(Context $ctx): array
    {
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return ['status' => 0, 'list' => []];
        }
        // 信件类型（1私人信件，2GM信件，3，系统, 4国家信箱）
        $type = (int)$ctx->input('t', 0);
        // 信件方向（0是收件箱，1是发件箱）
        $from = (int)$ctx->input('f', 0);
        $page = (int)$ctx->input('p', 0);
        $perpage = (int)$ctx->input('per', 0);
        $page = $page > 0 ? $page : 1;
        $message = Message::byType($member['uid'], $type, $from, $page, $perpage);
        if ($message['status'] !== 1) {
            return $message;
        }
        $list = [];
        foreach ($message['list'] as $key => $val) {
            $newlist = ['id' => $val['id'], 'isread' => $val['isread'], 'status' => $val['status'], 'type' => $val['type']];
            if ($from === 1 || ($from === 0 && $type === 2)) {
                $newlist['name'] = $val['toname'];
            } elseif ($val['type'] === 10) {
                $newlist['name'] = $val['title'];
                $newlist['king'] = $val['fromname'];
            } else {
                $newlist['name'] = $val['fromname'];
            }
            $list[$key] = $newlist;
            $list[$key]['message'] = Message::substr($val['message']);
        }
        $message['list'] = $list;

        // 取第一条信息
        if ($page <= 1) {
            $first = reset($list);
            $message['list'][0]['isread'] = 1;
            if ($type === 2) {
                $from = 1;
            }
            $info = Message::pmBuyNum($member, 2, $first['id'], $from)['list'];
            $newlist = [];
            foreach (['id', 'type', 'isread', 'status', 'giftstatus', 'act', 'silver', 'gold', 'barcard', 'dateline'] as $val) {
                $newlist[$val] = $info[$val] ?? null;
            }
            if ($from === 1 || ($from === 0 && $type === 2)) {
                $newlist['name'] = $info['toname'] ?? null;
            } elseif (($info['type'] ?? 0) === 10) {
                $newlist['name'] = $info['title'];
                $newlist['king'] = $info['fromname'];
            } else {
                $newlist['name'] = $info['fromname'] ?? null;
            }
            // 如果是普通信件收件箱，第一条信息返回发送人uid
            $newlist['fromuid'] = $from === 0 && $type === 1 ? ($info['fromuid'] ?? null) : 0;
            $newlist['message'] = $info['message'] ?? null;
            $newlist['dateline'] = date('Y-m-d H:i', (int)($info['dateline'] ?? 0));
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
     * 玩家互动信件: c=message&m=send_message（t 1 发给 GM / 2 国家信件 / 其他发给玩家，n 收信人昵称或国家名，i 内容，
     * sure 1 确认花 100 元宝发给别国）
     */
    public static function send_message(Context $ctx): array
    {
        /**
         * -5 元宝不足
         * -4 发给别国需确认（price 价格）
         * -3 国家不存在或没有成员
         * -2 不是君主
         * -1 用户不存在或是自己
         *  1 成功
         */
        $return = [];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return ['status' => 0];
        }
        $uid = $member['uid'];
        $now = time();
        // type用来判断是否是给GM发送
        $type = (int)$ctx->input('t', 0);
        $name = (string)$ctx->input('n', '');
        $sure = (int)$ctx->input('sure', 0);
        $message = htmlspecialchars((string)$ctx->input('i', ''), ENT_COMPAT);

        // 关键字屏蔽（status 为 1 时收件方收到的信息为已删除）
        // 旧版在判断禁言前又把 $status 置 0，屏蔽字的结果实际不起作用，照搬
        $status = 0;
        if ($type !== 1) {
            foreach (self::BIND_WORD as $value) {
                if (str_contains(strtolower($message), $value)) {
                    $status = 1;
                }
            }
        }

        $title = 'VIP' . $member['vip'];
        if ($type === 1) {
            // 发送到GM邮箱
            $touid = self::GM_UID;
            $name = 'GM';
            $type = 6;
        } elseif ($type === 2) {
            // 取自己的国家信息（如果不是君主，无法使用该功能）
            $myCountry = $member['country'] > 0 ? CountryHandler::countryInfo($member['country']) : null;
            if ($myCountry === null || (int)$myCountry['uid'] !== $uid) {
                return ['status' => -2];
            }
            $title = (string)$myCountry['name'];
            // 发送到国家邮箱，按国家名称渠道国家id
            $countryInfo = CountryHandler::countryByName($name);
            if ($countryInfo === null) {
                return ['status' => -3];
            }
            // 发送给其他国家
            if ((int)$countryInfo['id'] !== $member['country']) {
                if ($sure !== 1) {
                    // 需确定发送
                    return ['status' => -4, 'price' => self::COUNTRY_PRICE];
                }
                // 元宝不足（扣费为原子条件扣除，余额不足时同样回 -5）
                if ($member['gold'] < self::COUNTRY_PRICE || !Member::deductCurrency($uid, gold: self::COUNTRY_PRICE)) {
                    return ['status' => -5];
                }
            }
            // 查询国家用户列表
            $touid = [];
            foreach (CountryHandler::memberList((int)$countryInfo['id'], 1, 0) as $val) {
                $touid[] = (int)$val['uid'];
            }
            if ($touid === []) {
                return ['status' => -3];
            }
            $return['king'] = $member['nickname'];
            $type = 10;
        } else {
            // 发送给某个人
            $touid = (int)(DB::table('member')->where('nickname', $name)->whereNull('deleted_at')->value('uid') ?? 0);
            if (!$touid || $touid === $uid) {
                // 用户不存在，或者uid是自己，提示信件发送失败
                return ['status' => -1];
            }
            $type = 5;
        }

        // 判断用户是否被禁言（禁言用户发出去的信件，默认状态是收件方删除）
        $status = 0;
        if ($member['status'] === 2 || ($member['status'] === 1 && $member['statustime'] > $now) || $status > 0) {
            $status = 3;
        }

        // 发送
        $id = Message::send([
            'touid' => $touid,
            'toname' => $name,
            'type' => $type,
            'message' => $message,
            'status' => $status,
            'title' => $title,
        ], $member, $now);
        $return['id'] = ['id' => $id];
        $return['status'] = 1;
        $return['uid'] = $touid;
        $return['dateline'] = date('m' . self::LANG_BASE_MONTH . 'd' . self::LANG_BASE_DAY . ' H:i', $now);
        $return['all_gold'] = (int)DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('gold');
        return $return;
    }

    /**
     * 删除信件: c=message&m=del_message（id 信件 id，0 为整箱；t 0 / 1 私人 / 2 GM / 3 国家；f 0 收件箱 / 1 发件箱）
     */
    public static function del_message(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        if ($uid <= 0) {
            return ['status' => 0];
        }
        return Message::del($uid, (int)$ctx->input('id', 0), (int)$ctx->input('t', 0), (int)$ctx->input('f', 0));
    }

    /**
     * 获取 pk 战报: c=message&m=report_detail（id 信件 id）
     *
     * 旧版不校验战报是否属于自己（信件分享出去的战报任何人可看）。
     */
    public static function report_detail(Context $ctx): array
    {
        return self::reportDetail((int)$ctx->input('id', 0));
    }

    /**
     * 信件战报回放（message.php / beauty.php 的 report_detail 相同）
     *
     * status：-1 没有指定的战报；1 成功（battle 为战报，带 left_role / right_role；info.win 为评级）
     *
     * @return array<string, mixed>
     */
    public static function reportDetail(int $id): array
    {
        $return = ['status' => 0, 'battle' => [], 'info' => []];
        $result = Message::pkReportById($id);
        if ($result['status'] === -1) {
            // 没有指定的战报
            $return['status'] = -1;
            return $return;
        }
        $return['status'] = 1;
        $return['battle'] = $result['report'];
        $return['battle']['left_role'] = [
            'name' => $result['info']['nickname'],
            'level' => $result['info']['userlevel'],
        ];
        $return['battle']['right_role'] = [
            'name' => $result['info']['name'],
            'level' => $result['info']['level'],
        ];
        $return['info']['win'] = $result['report']['assess'] ?? null;
        return $return;
    }

    /**
     * 开启附件: c=message&m=open_gift（id 信件 id）
     */
    public static function open_gift(Context $ctx): array
    {
        /**
         * -5 礼包领取失败（已领取）
         * -4 礼包已领取过了
         * -3 没有礼包
         * -2 信件打开错误
         * -1 没有找到信件
         *  1 成功
         */
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        $uid = $member['uid'];
        $id = (int)$ctx->input('id', 0);
        $info = Message::pmBuyNum($member, 2, $id);
        if ($info['status'] !== 1) {
            // 没有找到信件（旧版此处误写 api_retur 致命错误，客户端收不到回包；这里回旧版定义的 -1）
            $return['status'] = -1;
            return $return;
        }
        $list = $info['list'];
        if ($list['touid'] !== $uid) {
            // 信件打开错误（同上，旧版误写 api_retur）
            $return['status'] = -2;
            return $return;
        }
        if (!$list['gold'] && !$list['act'] && !$list['silver'] && !$list['barcard']) {
            // 没有礼包
            $return['status'] = -3;
            return $return;
        }
        if ($list['giftstatus'] === 1) {
            // 礼包已领取过了
            $return['status'] = -4;
            return $return;
        }
        // 修改领取状态（条件更新，并发重复领取只有一个成功）
        if (Message::updateGiftStatus($uid, $id) !== 1) {
            // 礼包领取失败
            $return['status'] = -5;
            return $return;
        }
        // 添加奖励
        Member::addCurrency($uid, silver: $list['silver'], gold: $list['gold'], act: $list['act']);
        if ($list['barcard']) {
            PassGeneral::addFreeleap($uid, $list['barcard']);
        }
        $after = DB::table('member')->select('silver', 'gold', 'act')->where('uid', $uid)->whereNull('deleted_at')->first() ?? [];
        // 返回数据
        $return['status'] = 1;
        $return['info'] = [
            'add_gold' => $list['gold'],
            'add_silver' => $list['silver'],
            'add_act' => $list['act'],
            'add_barcard' => $list['barcard'],
            'all_silver' => (int)($after['silver'] ?? 0),
            'all_gold' => (int)($after['gold'] ?? 0),
            'all_act' => (int)($after['act'] ?? 0),
        ];
        return $return;
    }

    /**
     * 所有信件标为已读: c=message&m=update_read_status（type 信件类型）
     *
     * 旧版不输出任何内容（客户端不处理回包），这里回空数组。
     */
    public static function update_read_status(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        if ($uid > 0) {
            Message::updateReadStatus($uid, (int)$ctx->input('type', 0));
        }
        return [];
    }

    /**
     * 投递系统类 / 玩法信件（旧版 message_model->send_message；信件内容由调用方按旧版模板拼好）
     *
     * 1 / 2 / 7 / 8 为系统类信件：发件人 uid 1000、名字为系统定义的标题（$fromname）；其余类型（如 3 夺矿、4 演武榜 / 切磋、
     * 11 国家矿区）旧版发件人为当前玩家：传 $fromuid / $fromname 为玩家本人。$report 1 为战报信件（7 国家通知）。
     *
     * @param list<int> $touids
     * @return int 信件 id（多个收件人时为第一封）
     */
    public static function sendSystemMessage(array $touids, int $type, string $fromname, string $message, int $report = 0, int $fromuid = 1000): int
    {
        return Message::send([
            'touid' => $touids,
            'type' => $type,
            'message' => $message,
            'report' => $report,
            'fromuid' => $fromuid,
            'fromname' => $fromname,
        ], ['uid' => $fromuid, 'nickname' => $fromname]);
    }

    /**
     * 当前玩家（旧版 $this->member 用到的字段）
     *
     * @return array{uid: int, nickname: string, vip: int, gold: int, country: int, status: int, statustime: int, newmail: int}|null
     */
    private static function member(int $uid): ?array
    {
        if ($uid <= 0) {
            return null;
        }
        $row = DB::table('member')->select('uid', 'nickname', 'vip', 'gold', 'country', 'status', 'statustime', 'newmail')
            ->where('uid', $uid)->whereNull('deleted_at')->first();
        if ($row === null) {
            return null;
        }
        return [
            'uid' => (int)$row['uid'],
            'nickname' => (string)$row['nickname'],
            'vip' => (int)$row['vip'],
            'gold' => (int)$row['gold'],
            'country' => (int)$row['country'],
            'status' => (int)$row['status'],
            'statustime' => (int)$row['statustime'],
            'newmail' => (int)$row['newmail'],
        ];
    }
}
