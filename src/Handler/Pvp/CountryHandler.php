<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Pvp;

use Hanjiang\Battle\BattleEngine;
use Hanjiang\Battle\TeamBuilder;
use Hanjiang\Handler\Activity\MessageHandler;
use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\General\GeneralHandler;
use Hanjiang\MasterData\CountryDonate;
use Hanjiang\MasterData\CountryJob;
use Hanjiang\MasterData\CountryLearn;
use Hanjiang\MasterData\CountryLevel;
use Hanjiang\Model\Member;
use Hanjiang\Model\OwnMatrix;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 国家（玩家军团）系统，移植自旧版 controllers/country.php + models/country_model.php
 *
 * member.country 存放的是军团 id（zc_sanguo_country.id），0 表示未加入国家。
 */
final class CountryHandler
{
    private const int NO_OUTPUT = 0;          // 关闭输出
    private const int DONATE = 10;            // 每日贡献
    private const int CREATE_DONATE = 1000;   // 建国贡献
    private const int CREATE_PRICE = 500;     // 建国花费元宝数
    private const int COUNTRY_JOIN_NUM = 50;  // 每日国家加入人数
    private const int PERPAGE = 5;            // 国家排行每页条数
    private const int TTL = 86400;

    private const string AUDIT_KEY = 'country_audit_';
    private const string JOIN_NUM_KEY = 'country_join_num_';
    private const string FLOG_KEY = 'country_update';

    /** 学艺次数：次数 => 所需国家等级 */
    private const array LEARN_TIMES = [1 => 30, 2 => 45, 3 => 60, 4 => 75, 5 => 90];

    // 语言包 country_lang / message_lang
    private const string LANG_COUNTRY_MASTER = '君主';
    private const string LANG_MESSAGE_COUNTRY_TITLE = '国家通知';
    private const string LANG_MESSAGE_COUNTRY_YIELD_ME = '君主%s将王座禅让给您。';
    private const string LANG_MESSAGE_COUNTRY_YIELD = '君主%s禅让。%s<em>%s</em>将登基为新君主。望各位辅佐新君，一统天下。';
    private const string LANG_MESSAGE_COUNTRY_KICK = '您已被踢出国家。';
    private const string LANG_MESSAGE_COUNTRY_BEAT_YOU = '把您击败，取代了的您官职。';
    private const string LANG_MESSAGE_COUNTRY_CHALLENGE_LOSE = '无视您的权威向您挑战，被您打的落花流水。';
    private const string LANG_MESSAGE_COUNTRY_APPROVED = '同意了你的入国申请。';
    private const string LANG_MESSAGE_COUNTRY_USURPER = '篡位成功成为新的君主。';
    private const string LANG_MESSAGE_COUNTRY_TREASON = '贵国君主%s叛国，望诸君在此动荡之际切勿自乱阵脚，尽快推举出新君主。';

    private const int MESSAGE_TYPE_COUNTRY = 7;

    private const array CREATE_BIND_WORDS = ['钓鱼岛', '尖阁列岛'];

    private const array NOTICE_BIND_WORDS = [
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

    /**
     * 创建国家: c=country&m=create
     */
    public static function create(Context $ctx): array
    {
        /**
         * -7 扣除元宝失败
         * -6 更新用户信息失败
         * -5 添加或更新用户国家信息失败
         * -4 建国失败
         * -3 国家名称已存在
         * -2 名称长度大于7个字符
         * -1 名字不能为空
         *  0 失败
         *  1 成功
         *  2 已有国家
         *  3 元宝不足
         *  4 等级不足
         */
        $return = ['status' => 0];
        $member = self::currentMember($ctx);
        if ($member === null) {
            return $return;
        }
        $name = (string)$ctx->input('name', '');
        $confirm = (int)$ctx->input('confirm', 0);
        if ($confirm === 0) {
            // 返回建国价格
            $return['price'] = self::CREATE_PRICE;
            $return['status'] = 1;
            return $return;
        }
        if (!self::isOpen($member)) {
            // 等级不足
            $return['status'] = 4;
            return $return;
        }

        if ((int)$member['country'] > 0) {
            // 已有国家
            $return['status'] = 2;
            $return['country'] = (int)$member['country'];
            return $return;
        }

        if (self::CREATE_PRICE > (int)$member['gold']) {
            // 元宝不足
            $return['status'] = 3;
            return $return;
        }

        if ($name === '') {
            // 名字不能为空
            $return['status'] = -1;
            return $return;
        }

        if (mb_strlen($name) > 7) {
            // 名称长度超过7个字符
            $return['status'] = -2;
            return $return;
        }
        foreach (self::CREATE_BIND_WORDS as $value) {
            if (str_contains(strtolower($name), $value)) {
                $return['status'] = -2;
                return $return;
            }
        }
        // 根据名称获取国家信息
        if (self::countryByName($name) !== null) {
            // 国家名称已存在
            $return['status'] = -3;
            return $return;
        }
        // 添加国家(名称)
        $add = self::addCountry($name, $member);
        if ($add === []) {
            // 建国失败
            $return['status'] = -4;
            return $return;
        }
        $uid = (int)$member['uid'];
        $info = self::memberCountry($uid);
        if ($info === null) {
            // 添加成员信息
            $result = self::addMemberInfo((int)$add['id'], 1, $uid, (string)$member['nickname']);
        } else {
            // 更新国家成员信息
            $data = [
                'country' => (int)$add['id'],
                'job' => 1,
                'contribute' => (int)$info['contribute'] + self::CREATE_DONATE,
                'allcontribute' => (int)$info['allcontribute'] + self::CREATE_DONATE,
            ];
            $result = self::updateMemberInfo($data, $uid);
            // 记录贡献log
            self::addDonateLog($member, 'create', [
                'type' => 1,
                'cost' => self::CREATE_PRICE,
                'donate' => self::CREATE_DONATE,
                'contribute' => $data['contribute'],
                'allcontribute' => $data['allcontribute'],
            ]);
        }

        if ($result !== 1) {
            // 添加或更新用户国家信息失败
            $return['status'] = -5;
            return $return;
        }
        // 更新玩家国别
        if (self::updateMemberCountry((int)$add['id'], $uid) !== 1) {
            // 更新用户信息失败
            $return['status'] = -6;
            return $return;
        }
        // 扣除建国金币花费
        if (!Member::deductCurrency($uid, gold: self::CREATE_PRICE)) {
            // 元宝扣除失败
            $return['status'] = -7;
            return $return;
        }
        $data = [
            'uid' => $uid,
            'nickname' => $member['nickname'],
            'level' => (int)$member['level'],
            'country' => (int)$add['id'],
            'job' => 1,
            'job_name' => self::LANG_COUNTRY_MASTER,
            'contribute' => 0,
            'allcontribute' => 0,
            'dateline' => 0,
        ];

        if ($info !== null) {
            $data['contribute'] = (int)$info['contribute'];
            $data['allcontribute'] = (int)$info['allcontribute'];
        }
        // 依赖未移植：stat_model->create_country 消费统计记录

        $return['status'] = 1;
        $return['country'] = $add;
        $return['list'] = [0 => $data];
        $return['info'] = ['job' => 1, 'rank' => 1, 'make_way' => 0, 'usurp' => 0];
        $return['gold'] = (int)(self::member($uid)['gold'] ?? 0);
        return $return;
    }

    /**
     * 获取国家排行: c=country&m=get_rank
     */
    public static function get_rank(Context $ctx): array
    {
        /**
         * -1 获取公告失败
         *  0 失败
         *  1 成功
         *  2 等级不足
         */
        $return = ['status' => 0, 'list' => [], 'page' => 1, 'perpage' => 0, 'pages' => 1];
        $member = self::currentMember($ctx);
        if ($member === null) {
            return $return;
        }
        $page = (int)$ctx->input('page', 0);
        if (!self::isOpen($member)) {
            // 等级不足
            $return['status'] = 2;
            return $return;
        }
        $myCountry = (int)$member['country'];
        // 获取我的国家排行
        if ($page === 0 && $myCountry > 0) {
            $country = self::countryRank($myCountry, 0, 1);
        } else {
            $country = self::countryList($page);
        }

        if ($country !== []) {
            $id = 0;
            foreach ($country['list'] as $value) {
                $return['list'][] = $value;
                // page为0返回我的国家信息
                if ((int)$value['id'] === $myCountry && $page === 0) {
                    $return['country'] = $value;
                    $id = (int)$value['id'];
                }
            }
            // 传page返回第一条信息
            if ($page > 0 || $id === 0) {
                $return['country'] = $return['list'][0];
                $id = (int)$return['list'][0]['id'];
            }
            $notice = self::notice($id);
            $return['country']['notice'] = $notice['notice'];
            $return['perpage'] = $country['perpage'];
            $return['page'] = $country['page'];
            $return['pages'] = $country['pages'];
        }

        $return['status'] = 1;
        return $return;
    }

    /**
     * 获取国家成员列表: c=country&m=get_member_list
     */
    public static function get_member_list(Context $ctx): array
    {
        /**
         * -4 获取国家成员失败
         * -3 获取公告失败
         * -2 获取国家信息失败
         * -1 没有加入国家
         *  0 失败
         *  1 成功
         *  2 等级不足
         */
        $return = [
            'status' => 0,
            'info' => [],
            'country' => [],
            'list' => [],
            'audit' => 0,
        ];
        $member = self::currentMember($ctx);
        if ($member === null) {
            return $return;
        }
        if (!self::isOpen($member)) {
            // 等级不足
            $return['status'] = 2;
            return $return;
        }
        $id = (int)$member['country'];
        $uid = (int)$member['uid'];
        if ($id === 0) {
            // 没有加入国家
            $return['status'] = -1;
            return $return;
        }
        // 修复member国家信息未清0
        if (self::memberInfo($uid, $id) === null) {
            self::updateMemberCountry(0, $uid);
            $return['status'] = -1;
            return $return;
        }

        $country = self::countryInfo($id);
        if ($country === null) {
            // 获取国家信息失败
            $return['status'] = -2;
            return $return;
        }

        $notice = self::notice($id);
        $country['notice'] = $notice['notice'];

        $list = self::memberList($id);
        if ($list === []) {
            // 获取国家成员失败
            $return['status'] = -4;
            return $return;
        }

        $count = count($list);
        if ($count !== (int)$country['member']) {
            // 修复人数不一致
            self::updateCountryInfo($id, ['member' => $count]);
        }

        $return['info'] = self::sortMemberList($list, $member);
        if (($return['info']['job'] ?? null) <= 3) {
            $return['audit'] = self::getAuditInfo($id);
        }

        $return['status'] = 1;
        $return['list'] = $list;
        $return['country'] = $country;
        return $return;
    }

    /**
     * 申请: c=country&m=apply
     */
    public static function apply(Context $ctx): array
    {
        /**
         * -4 申请加入失败
         * -3 申请已存在
         * -2 国家不存在
         * -1 已加入国家
         *  0 失败
         *  1 成功
         *  2 国家成员已满
         *  3 等级不足
         */
        $return = ['status' => 0];
        $member = self::currentMember($ctx);
        if ($member === null) {
            return $return;
        }
        $id = (int)$ctx->input('id', 0);
        if (!self::isOpen($member)) {
            // 等级不足
            $return['status'] = 3;
            return $return;
        }
        if ((int)$member['country'] > 0) {
            // 已加入国家
            $return['status'] = -1;
            $return['country'] = (int)$member['country'];
            return $return;
        }
        $info = self::countryInfo($id);
        if ($info === null) {
            // 国家不存在
            $return['status'] = -2;
            return $return;
        }

        if ((int)$info['member'] === 50) {
            // 国家成员已满
            $return['status'] = 2;
            return $return;
        }
        $uid = (int)$member['uid'];
        if (self::applyInfo($id, $uid) !== null) {
            // 申请已存在
            $return['status'] = -3;
            return $return;
        }
        $rank = self::countryRank($id, 0);
        if ($rank === []) {
            // 获取国家排名失败
            $return['status'] = -4;
            return $return;
        }
        // 加入申请队列
        $add = DB::table('country_apply')->insert([
            'uid' => $uid,
            'country' => $id,
            'level' => (int)$member['level'],
            'nickname' => $member['nickname'],
            'rank' => (int)$rank['rank'],
        ]);
        if ($add === false) {
            // 申请加入失败
            $return['status'] = -5;
            return $return;
        }
        // 增加待审核信息
        self::addAuditInfo($id);
        $return['status'] = 1;
        return $return;
    }

    /**
     * 取消申请: c=country&m=del_apply
     */
    public static function del_apply(Context $ctx): array
    {
        /**
         * -1 申请不存在
         *  0 失败
         *  1 成功
         *  2 已加入国家
         *  3 等级不足
         */
        $return = ['status' => 0];
        $member = self::currentMember($ctx);
        if ($member === null) {
            return $return;
        }
        $id = (int)$ctx->input('id', 0);
        if (!self::isOpen($member)) {
            // 等级不足
            $return['status'] = 3;
            return $return;
        }
        if ((int)$member['country'] > 0) {
            // 已加入国家
            $return['status'] = 2;
            $return['country'] = (int)$member['country'];
            return $return;
        }
        $uid = (int)$member['uid'];
        if (self::applyInfo($id, $uid) === null) {
            // 申请不存在
            $return['status'] = -1;
            return $return;
        }
        // 删除申请
        self::delApply($uid, $id);
        // 减少待审核信息
        self::subAuditInfo($id);
        $return['status'] = 1;
        return $return;
    }

    /**
     * 已申请的国家列表: c=country&m=get_apply_list
     */
    public static function get_apply_list(Context $ctx): array
    {
        /**
         * -2 获取公告失败
         * -1 已加入国家
         *  0 失败
         *  1 成功
         *  2 等级不足
         */
        $return = ['status' => 0];
        $member = self::currentMember($ctx);
        if ($member === null) {
            return $return;
        }
        if ((int)$member['country'] > 0) {
            $return['status'] = -1;
            $return['country'] = (int)$member['country'];
            return $return;
        }
        $list = self::applyList((int)$member['uid']);
        $listArr = [];
        if ($list !== []) {
            $ids = array_map(static fn(array $v): int => (int)$v['country'], $list);
            $field = ['id', 'name', 'uid', 'nickname', 'rank', 'member', 'level', 'exp', 'levelupexp'];
            $country = DB::table('country')->whereIn('id', $ids)->whereNull('deleted_at')->get();
            foreach ($list as $value) {
                foreach ($country as $v1) {
                    if ((int)$value['country'] === (int)$v1['id']) {
                        $row = [];
                        foreach ($field as $v2) {
                            $row[$v2] = $v2 === 'rank' ? $value[$v2] : $v1[$v2];
                        }
                        $listArr[] = $row;
                    }
                }
            }
            if ($listArr !== []) {
                $notice = self::notice((int)$listArr[0]['id']);
                $return['country'] = $listArr[0];
                $return['country']['notice'] = $notice['notice'];
            }
        }
        $return['status'] = 1;
        $return['list'] = $listArr;
        return $return;
    }

    /**
     * 审核列表: c=country&m=get_audit_list
     */
    public static function get_audit_list(Context $ctx): array
    {
        /**
         * -2 未加入国家
         * -1 没有权限
         *  0 失败
         *  1 成功
         *  2 等级不足
         */
        $return = ['status' => 0];
        $member = self::currentMember($ctx);
        if ($member === null) {
            return $return;
        }
        if ((int)$member['country'] === 0) {
            // 未加入国家
            $return['status'] = -2;
            return $return;
        }
        if (!self::isOpen($member)) {
            // 等级不足
            $return['status'] = 2;
            return $return;
        }
        $info = self::memberInfo((int)$member['uid'], (int)$member['country']);
        if ($info === null || (int)$info['job'] > 3) {
            // 没有权限
            $return['status'] = -1;
            return $return;
        }
        $list = DB::table('country_apply')
            ->where('country', (int)$info['country'])
            ->whereNull('deleted_at')
            ->orderBy('level', 'DESC')
            ->get();
        $listArr = [];
        foreach ($list as $key => $value) {
            foreach (['uid', 'nickname', 'level'] as $v) {
                $listArr[$key][$v] = $value[$v];
            }
        }

        $return['status'] = 1;
        $return['list'] = $listArr;
        return $return;
    }

    /**
     * 忽略所有: c=country&m=ignore_all
     */
    public static function ignore_all(Context $ctx): array
    {
        /**
         * -2 没有权限
         * -1 未加入国家
         *  0 失败
         *  1 成功
         *  2 等级不足
         */
        $return = ['status' => 0];
        $member = self::currentMember($ctx);
        if ($member === null) {
            return $return;
        }
        if (!self::isOpen($member)) {
            // 等级不足
            $return['status'] = 2;
            return $return;
        }

        if ((int)$member['country'] === 0) {
            // 未加入国家
            $return['status'] = -1;
            return $return;
        }

        $info = self::memberCountry((int)$member['uid']);
        if ($info === null || (int)$info['job'] > 3) {
            // 没有权限
            $return['status'] = -2;
            return $return;
        }

        $del = DB::table('country_apply')
            ->where('country', (int)$info['country'])
            ->whereNull('deleted_at')
            ->update(['deleted_at' => date('Y-m-d H:i:s')]);
        if ($del > 0) {
            self::updateAuditInfo((int)$info['country']);
        }

        $return['status'] = 1;
        $return['audit'] = 0;
        return $return;
    }

    /**
     * 审核: c=country&m=audit
     *
     * type(1 同意;2 忽略)
     */
    public static function audit(Context $ctx): array
    {
        /**
         * -11 获取官职失败
         * -10 未加入国家
         * -9 类型错误
         * -8 更新国家信息失败
         * -7 更新申请状态失败(废弃)
         * -6 更新成员失败
         * -5 添加成员失败
         * -4 用户不存在
         * -3 国家不存在
         * -2 该玩家已加入别的国家,或已取消申请
         * -1 没有权限
         *  0 失败
         *  1 成功
         *  2 国家成员已满
         *  3 等级不足
         *  4 国战期间无法审核
         *  5 国家boss期间无法审核
         */
        $return = ['status' => 0];
        $self = self::currentMember($ctx);
        if ($self === null) {
            return $return;
        }
        $uid = (int)$ctx->input('uid', 0);
        $type = (int)$ctx->input('type', 0);
        $now = time();
        if (!self::isOpen($self)) {
            // 等级不足
            $return['status'] = 3;
            return $return;
        }

        $freeze = self::freeze($now);
        if ($freeze === 1) {
            // 国战期间无法审核
            $return['status'] = 4;
            return $return;
        }

        if ($freeze === 2) {
            // 国家boss期间无法审核
            $return['status'] = 5;
            return $return;
        }

        $myCountry = (int)$self['country'];
        $myUid = (int)$self['uid'];
        if ($myCountry === 0) {
            // 未加入国家
            $return['status'] = -10;
            return $return;
        }
        if (self::applyInfo($myCountry, $uid) === null) {
            // 申请已存在
            $return['status'] = -2;
            return $return;
        }

        $info = self::memberCountry($myUid);
        if ($info === null || (int)$info['job'] > 3) {
            // 没有权限
            $return['status'] = -1;
            return $return;
        }

        $country = self::countryInfo((int)$info['country']);
        if ($country === null) {
            // 国家不存在
            $return['status'] = -3;
            return $return;
        }

        if ($type === 1 && (int)$country['member'] > 49) {
            // 国家成员已满
            $return['status'] = 2;
            return $return;
        }

        $member = self::member($uid);
        if ($member === null) {
            // 用户不存在
            $return['status'] = -4;
            return $return;
        }

        $exists = self::memberCountry($uid);
        if ($exists !== null && (int)$exists['country'] > 0) {
            // 该玩家已加入别的国家,或已取消申请
            self::delApply($uid, $myCountry);
            self::subAuditInfo($myCountry);
            $return['status'] = -2;
            $return['audit'] = self::getAuditInfo($myCountry);
            return $return;
        }

        // 获取国家最低职位 范围:0~51(0 - 非正常值; 1~51 - 正常值)
        $last = self::lastJob($myCountry);
        if ($last < 1) {
            // 获取官职失败
            $return['status'] = -11;
            return $return;
        }

        $job = $last > 50 ? 51 : $last + 1;
        if ($type === 1) {
            if ((int)$country['level'] >= 10) {
                // 查询今日加入人数
                $joinNumber = self::countryJoinNumber($myCountry, $now);
                if ($joinNumber >= self::COUNTRY_JOIN_NUM) {
                    $return['status'] = 6;
                    return $return;
                }
                // 添加加入国家人数log
                Redis::set(self::joinNumberKey($myCountry, $now), $joinNumber + 1, 86400);
            }
            // 同意
            if ($exists === null) {
                if (self::addMemberInfo($myCountry, $job, (int)$member['uid'], (string)$member['nickname']) !== 1) {
                    // 添加成员失败
                    $return['status'] = -5;
                    return $return;
                }
            } else {
                $data = [
                    'job' => $job,
                    'country' => $myCountry,
                    'joindateline' => $now,
                ];
                if (self::updateMemberInfo($data, (int)$member['uid']) !== 1) {
                    // 更新成员失败
                    $return['status'] = -6;
                    return $return;
                }
            }

            if (self::updateMemberCountry($myCountry, (int)$member['uid']) !== 1) {
                // 更新用户国家id失败
                $return['status'] = -10;
                return $return;
            }
            $countryData = ['member' => (int)$country['member'] + 1];
            if (self::updateCountryInfo($myCountry, $countryData) !== 1) {
                // 更新国家信息失败
                $return['status'] = -8;
                return $return;
            }
            // 获取申请列表
            foreach (self::applyList($uid) as $value) {
                // 减少待审核信息
                self::subAuditInfo((int)$value['country']);
            }
            // 删除申请
            DB::table('country_apply')
                ->where('uid', $uid)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => date('Y-m-d H:i:s')]);
            // 发信件
            self::sendMessage([$uid], self::emUser($self) . self::LANG_MESSAGE_COUNTRY_APPROVED);
            // 更新计划任务标志位（依赖未移植：消费方 cron_country 计划任务）
            Redis::set(self::FLOG_KEY, 1, self::TTL);
            $return['country'] = $countryData;
            // 添加国家退出记录(状态 1退出，2进入，3被踢出)
            self::addCountryLog([
                'uid' => $uid,
                'operator_uid' => $myUid,
                'country' => $myCountry,
                'status' => 2,
            ], 'audit', $now);
        } elseif ($type === 2) {
            // 忽略
            self::delApply($uid, $myCountry);
            // 减少待审核信息
            self::subAuditInfo($myCountry);
        } else {
            // 类型错误
            $return['status'] = -9;
            return $return;
        }
        $return['status'] = 1;
        $return['audit'] = self::getAuditInfo($myCountry);
        return $return;
    }

    /**
     * 发布公告: c=country&m=publish_notice
     */
    public static function publish_notice(Context $ctx): array
    {
        /**
         * -4 未加入国家
         * -3 更新公告失败
         * -2 权限不足
         * -1 内容太长
         *  0 失败
         *  1 成功
         *  2 等级不足
         *  3 内容未改变
         */
        $return = ['status' => 0];
        $member = self::currentMember($ctx);
        if ($member === null) {
            return $return;
        }
        $id = (int)$ctx->input('country', 0);
        $content = (string)$ctx->input('content', '');
        if (!self::isOpen($member)) {
            // 等级不足
            $return['status'] = 2;
            return $return;
        }

        // 关键字屏蔽
        $status = 0;
        foreach (self::NOTICE_BIND_WORDS as $value) {
            if (str_contains(strtolower($content), $value)) {
                $status = 1;
            }
        }
        if ($status > 0) {
            // 修改公告:你输入的信息违法
            $return['status'] = -5;
            return $return;
        }

        $myCountry = (int)$member['country'];
        if ($myCountry === 0 || $id !== $myCountry) {
            // 未加入该国家
            $return['status'] = -4;
            return $return;
        }

        if (mb_strlen($content) > 70) {
            // 内容太长
            $return['status'] = -1;
            return $return;
        }

        $info = self::memberInfo((int)$member['uid'], $myCountry);
        if ($info === null || (int)$info['job'] > 3) {
            // 权限不足
            $return['status'] = -2;
            return $return;
        }
        self::notice($myCountry);
        $update = DB::table('country_notice')
            ->where('id', (int)$info['country'])
            ->whereNull('deleted_at')
            ->update(['notice' => $content]);
        if ($update <= 0) {
            // 内容未改变
            $return['status'] = 3;
            return $return;
        }

        $return['status'] = 1;
        $return['notice'] = $content;
        return $return;
    }

    /**
     * 叛国: c=country&m=betray
     */
    public static function betray(Context $ctx): array
    {
        /**
         * -5 更新玩家信息失败
         * -4 更新国家信息失败
         * -3 获取国家信息失败
         * -2 更新成员信息失败
         * -1 未加入国家
         *  0 失败
         *  1 成功
         *  2 等级不足
         */
        $return = ['status' => 0];
        $member = self::currentMember($ctx);
        if ($member === null) {
            return $return;
        }
        $now = time();
        if (!self::isOpen($member)) {
            // 等级不足
            $return['status'] = 2;
            return $return;
        }

        $myCountry = (int)$member['country'];
        $uid = (int)$member['uid'];
        if ($myCountry === 0) {
            // 未加入国家
            $return['status'] = -1;
            return $return;
        }

        $freeze = self::freeze($now);
        if ($freeze === 1) {
            // 国战期间冻结加入退出操作
            $return['status'] = 4;
            return $return;
        }

        if ($freeze === 2) {
            // 国家boss期间无法审核
            $return['status'] = 5;
            return $return;
        }

        // 修改member_country信息
        $data = [
            'country' => 0,
            'job' => 0,
            'contribute' => 0,
            'leavedateline' => $now,
        ];
        if (self::updateMemberInfo($data, $uid) !== 1) {
            // 更新成员信息失败
            $return['status'] = -2;
            return $return;
        }

        $info = self::countryInfo($myCountry);
        if ($info === null) {
            // 获取国家信息失败
            $return['status'] = -3;
            return $return;
        }

        if ((int)$info['member'] <= 1) {
            // 删除此国家记录
            $deletedAt = date('Y-m-d H:i:s');
            DB::table('country')
                ->where('id', (int)$info['id'])
                ->where('uid', $uid)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => $deletedAt]);
            DB::table('country_notice')
                ->where('id', (int)$info['id'])
                ->whereNull('deleted_at')
                ->update(['deleted_at' => $deletedAt]);
            // 依赖未移植：siege_model->clear_battle_status 清除国战状态
        } else {
            $data = ['member' => (int)$info['member'] - 1];
            // 君主叛国清空国家君主信息
            if ((int)$info['uid'] === $uid) {
                $data['uid'] = 0;
                $data['nickname'] = '';
                $uidarr = [];
                foreach (self::memberList((int)$info['id']) as $value) {
                    if ((int)$value['uid'] !== $uid) {
                        $uidarr[] = (int)$value['uid'];
                    }
                }

                if ($uidarr !== []) {
                    // 发公告
                    self::sendMessage($uidarr, sprintf(self::LANG_MESSAGE_COUNTRY_TREASON, self::emUser($member)));
                }
            }
            if (self::updateCountryInfo($myCountry, $data) !== 1) {
                // 更新国家信息失败
                $return['status'] = -4;
                return $return;
            }
        }
        // 修改country信息
        if (self::updateMemberCountry(0, $uid) !== 1) {
            // 更新玩家信息失败
            $return['status'] = -5;
            return $return;
        }
        // 国家排行列表
        $list = self::allCountryPages();

        // 依赖未移植：overseastrade_model->clear_team 海外贸易队伍清理
        // 依赖未移植：banquet_model->repaire_banquet 国家宴会队伍清理
        // 依赖未移植：siege_model->clear_siege_status 国战报名状态清理
        // 依赖未移植：expostulation_model->clear_sup_status / clear_expos 国家谏言状态清理
        // 依赖未移植：dice_model->clear_dice_info 清除国家骰子信息
        // 依赖未移植：warcollege_use_model->clear_buffcd 清理国家学院buffcd

        // 添加国家退出记录(状态 1退出，2进入，3被踢出)
        self::addCountryLog([
            'uid' => $uid,
            'operator_uid' => 0,
            'country' => $myCountry,
            'status' => 1,
        ], 'betray', $now);

        // 依赖未移植：action_celebration_reward 国家庆典活动未领奖励自动发放

        $return['status'] = 1;
        $return['list'] = $list;
        return $return;
    }

    /**
     * 获取禅让信息: c=country&m=make_way_info
     */
    public static function make_way_info(Context $ctx): array
    {
        /**
         * -3 获取成员信息失败
         * -2 获取国家信息失败
         * -1 未加入国家
         *  0 失败
         *  1 成功
         *  2 等级不足
         */
        $return = ['status' => 0];
        $self = self::currentMember($ctx);
        if ($self === null) {
            return $return;
        }
        if (!self::isOpen($self)) {
            // 等级不足
            $return['status'] = 2;
            return $return;
        }
        $myCountry = (int)$self['country'];
        if ($myCountry === 0) {
            // 未加入国家
            $return['status'] = -1;
            return $return;
        }
        if (self::countryInfo($myCountry) === null) {
            // 获取国家信息失败
            $return['status'] = -2;
            return $return;
        }
        // 根据职位获取用户国家信息(丞相, 国家id)
        $member = self::secondJob($myCountry);
        if ($member === null) {
            // 获取成员信息失败
            $return['status'] = -3;
            return $return;
        }

        $return['status'] = 1;
        $return['info'] = [
            'uid' => (int)$member['uid'],
            'nickname' => $member['nickname'],
            'job_name' => self::jobName((int)$member['job']),
        ];
        return $return;
    }

    /**
     * 禅让: c=country&m=make_way
     */
    public static function make_way(Context $ctx): array
    {
        /**
         * -7 非君主无权禅让（旧版缺此校验，开发者要求补上）
         * -4 获取国家成员失败
         * -3 更新国家信息失败
         * -2 获取成员信息失败
         * -1 获取国家信息失败
         *  0 失败
         *  1 成功
         *  2 等级不足
         */
        $return = ['status' => 0];
        $self = self::currentMember($ctx);
        if ($self === null) {
            return $return;
        }
        if (!self::isOpen($self)) {
            // 等级不足
            $return['status'] = 2;
            return $return;
        }
        $myCountry = (int)$self['country'];
        $myUid = (int)$self['uid'];
        if (self::countryInfo($myCountry) === null) {
            // 获取国家信息失败
            $return['status'] = -1;
            return $return;
        }
        $mine = self::memberCountry($myUid);
        if ($mine === null || (int)$mine['country'] !== $myCountry || (int)$mine['job'] !== 1) {
            // 非君主无权禅让
            $return['status'] = -7;
            return $return;
        }
        // 获取第二职位成员
        $member = self::secondJob($myCountry);
        if ($member === null) {
            // 获取成员信息失败
            $return['status'] = -2;
            return $return;
        }
        // 更新用户信息  直接变成丞相
        if (self::updateMemberInfo(['job' => 2], $myUid) !== 1) {
            $return['status'] = -5;
            return $return;
        }
        if (self::updateMemberInfo(['job' => 1], (int)$member['uid']) !== 1) {
            $return['status'] = -6;
            return $return;
        }
        $data = [
            'uid' => (int)$member['uid'],
            'nickname' => $member['nickname'],
        ];
        if (self::updateCountryInfo($myCountry, $data) !== 1) {
            // 更新国家信息失败
            $return['status'] = -3;
            return $return;
        }
        $list = self::memberList($myCountry);
        if ($list === []) {
            // 获取国家成员失败
            $return['status'] = -4;
            return $return;
        }
        $now = time();
        foreach ($list as $key => $value) {
            if ((int)$value['uid'] === $myUid) {
                $return['info']['job'] = $value['job'];
                $return['info']['rank'] = $value['rank'];
            }
            $list[$key]['dateline'] = $now - (int)$value['dateline'];
            $list[$key]['job_name'] = self::jobName((int)$value['job']);
        }

        self::sendMessage([(int)$member['uid']], sprintf(self::LANG_MESSAGE_COUNTRY_YIELD_ME, self::emUser($self)));
        // 发公告
        $uidarr = [];
        $value = [];
        foreach ($list as $value) {
            if ((int)$value['uid'] !== $myUid && (int)$value['uid'] !== (int)$member['uid']) {
                $uidarr[] = (int)$value['uid'];
            }
        }

        if ($uidarr !== []) {
            // 旧版即取循环结束后残留的 $value（列表最后一名成员）的官职名
            self::sendMessage($uidarr, sprintf(
                self::LANG_MESSAGE_COUNTRY_YIELD,
                self::emUser($self),
                self::jobName((int)$value['job']),
                $member['nickname']
            ));
        }

        $return['status'] = 1;
        $return['list'] = $list;
        $return['country'] = $data;
        return $return;
    }

    /**
     * 获取踢人提示: c=country&m=kick_out_info
     */
    public static function kick_out_info(Context $ctx): array
    {
        /**
         * -6 未加入国家
         * -5 获取职位信息
         * -4 对方贡献更高
         * -3 对方职位更高
         * -2 玩家信息不存在
         * -1 没有权限
         *  0 失败
         *  1 成功
         *  2 等级不足
         */
        $return = ['status' => 0];
        $self = self::currentMember($ctx);
        if ($self === null) {
            return $return;
        }
        $uid = (int)$ctx->input('uid', 0);
        $myUid = (int)$self['uid'];
        $myCountry = (int)$self['country'];

        if ($uid === $myUid || $uid === 0) {
            $return['status'] = -7;
            return $return;
        }

        if (!self::isOpen($self)) {
            // 等级不足
            $return['status'] = 2;
            return $return;
        }
        if ($myCountry === 0) {
            // 未加入国家
            $return['status'] = -6;
            return $return;
        }

        $country = self::memberInfo($myUid, $myCountry);
        if ($country === null || (int)$country['job'] > 3) {
            // 没有权限
            $return['status'] = -1;
            return $return;
        }
        $member = self::memberCountry($uid);
        if ($member === null || (int)$member['country'] !== $myCountry) {
            // 玩家信息不存在
            $return['status'] = -2;
            return $return;
        }

        if ((int)$member['job'] < (int)$country['job']) {
            // 对方职位更高
            $return['status'] = -3;
            return $return;
        }

        if ((int)$member['allcontribute'] > (int)$country['allcontribute']) {
            // 对方贡献更高
            $return['status'] = -4;
            return $return;
        }

        $return['status'] = 1;
        $return['info'] = [
            'nickname' => $member['nickname'],
            'job_name' => self::jobName((int)$member['job']),
        ];
        return $return;
    }

    /**
     * 踢出: c=country&m=kick_out
     */
    public static function kick_out(Context $ctx): array
    {
        /**
         * -10 未加入国家
         * -9 获取成员列表失败
         * -8 更新国家id失败
         * -7 更新国家信息失败
         * -6 获取国家信息失败
         * -5 更新成员信息失败
         * -4 对方贡献更高
         * -3 对方职位更高
         * -2 玩家信息不存在
         * -1 没有权限
         *  0 失败
         *  1 成功
         *  2 等级不足
         */
        $return = ['status' => 0];
        $self = self::currentMember($ctx);
        if ($self === null) {
            return $return;
        }
        $uid = (int)$ctx->input('uid', 0);
        $myUid = (int)$self['uid'];
        $myCountry = (int)$self['country'];
        $now = time();

        if ($uid === $myUid || $uid === 0) {
            $return['status'] = -11;
            return $return;
        }

        if (!self::isOpen($self)) {
            // 等级不足
            $return['status'] = 2;
            return $return;
        }
        if ($myCountry === 0) {
            // 未加入国家
            $return['status'] = -10;
            return $return;
        }

        $freeze = self::freeze($now);
        if ($freeze === 1) {
            // 国战期间无法审核
            $return['status'] = 4;
            return $return;
        }

        if ($freeze === 2) {
            // 国家boss期间无法审核
            $return['status'] = 5;
            return $return;
        }

        $country = self::memberCountry($myUid);
        if ($country === null || (int)$country['job'] > 3) {
            // 没有权限
            $return['status'] = -1;
            return $return;
        }
        $member = self::memberCountry($uid);
        if ($member === null || (int)$member['country'] !== $myCountry) {
            // 玩家信息不存在
            $return['status'] = -2;
            return $return;
        }

        if ((int)$member['job'] < (int)$country['job']) {
            // 对方职位更高
            $return['status'] = -3;
            return $return;
        }

        if ((int)$member['allcontribute'] > (int)$country['allcontribute']) {
            // 对方贡献更高
            $return['status'] = -4;
            return $return;
        }

        $countryInfo = self::countryInfo($myCountry);
        if ($countryInfo === null) {
            // 获取国家信息失败
            $return['status'] = -6;
            return $return;
        }

        if ((int)$countryInfo['member'] < 2) {
            return $return;
        }

        // 旧版此处读的是踢人前缓存的成员列表（仍含被踢者），用于计算下一个选中的 uid
        $beforeList = self::memberList($myCountry);

        $info = ['country' => 0, 'contribute' => 0, 'job' => 0, 'leavedateline' => $now];
        if (self::updateMemberInfo($info, $uid) !== 1) {
            // 更新玩家国家信息失败
            $return['status'] = -5;
            return $return;
        }

        $data = ['member' => (int)$countryInfo['member'] - 1];
        if (self::updateCountryInfo($myCountry, $data) !== 1) {
            // 更新国家信息失败
            $return['status'] = -7;
            return $return;
        }
        if (self::updateMemberCountry(0, $uid) !== 1) {
            // 更新国家id失败
            $return['status'] = -8;
            return $return;
        }

        // 得到下个需要选中的uid
        $nextUid = 0;
        $lastUid = $myUid;
        $site = 0;
        // 按官职排序
        self::sortMemberList($beforeList, $self);
        foreach ($beforeList as $val) {
            if ($site === 0) {
                if ((int)$val['uid'] === (int)$member['uid']) {
                    $site = 1;
                } else {
                    $lastUid = (int)$val['uid'];
                }
            } elseif ((int)$val['job'] >= (int)$member['job']) {
                $nextUid = (int)$val['uid'];
                break;
            }
        }

        if ($nextUid === 0) {
            $nextUid = $lastUid;
        }

        $list = self::memberList($myCountry);
        if ($list === []) {
            // 获取成员列表失败
            $return['status'] = -9;
            return $return;
        }

        $return['info'] = self::sortMemberList($list, $self);
        if (($return['info']['job'] ?? null) <= 3) {
            $return['audit'] = self::getAuditInfo($myCountry);
        }

        self::sendMessage([$uid], self::LANG_MESSAGE_COUNTRY_KICK);
        // 更新计划任务标志位（依赖未移植：消费方 cron_country 计划任务）
        Redis::set(self::FLOG_KEY, 1, self::TTL);

        // 依赖未移植：overseastrade_model->clear_team 海外贸易队伍清理
        // 依赖未移植：banquet_model->repaire_banquet 国家宴会队伍清理
        // 依赖未移植：expostulation_model->clear_sup_status / clear_expos 国家谏言状态清理
        // 依赖未移植：siege_model->clear_siege_status 国战报名状态清理
        // 依赖未移植：dice_model->clear_dice_info 清除国家骰子信息

        // 添加国家退出记录(状态 1退出，2进入，3被踢出)
        self::addCountryLog([
            'uid' => $uid,
            'operator_uid' => $myUid,
            'country' => $myCountry,
            'status' => 3,
        ], 'kick_out', $now);

        // 依赖未移植：action_celebration_reward 国家庆典活动未领奖励自动发放

        $return['status'] = 1;
        $return['country'] = $data;
        $return['list'] = $list;
        $return['next_uid'] = $nextUid;
        return $return;
    }

    /**
     * 获取升官信息: c=country&m=get_usurp_info
     */
    public static function get_usurp_info(Context $ctx): array
    {
        /**
         * -3 未加入国家
         * -2 获取官职列表失败
         * -1 贡献不足
         *  0 失败
         *  1 成功
         *  2 已是君主
         *  3 等级不足
         */
        $return = ['status' => 0];
        $self = self::currentMember($ctx);
        if ($self === null) {
            return $return;
        }
        if (!self::isOpen($self)) {
            // 等级不足
            $return['status'] = 3;
            return $return;
        }
        $myCountry = (int)$self['country'];
        if ($myCountry === 0) {
            // 未加入国家
            $return['status'] = -3;
            return $return;
        }
        $info = self::memberInfo((int)$self['uid'], $myCountry);
        // 取得职位
        $myJob = (int)($info['job'] ?? 0);
        if ($myJob > 1) {
            $job = $myJob - 1;
        } else {
            // 已经是君主
            $return['status'] = 2;
            return $return;
        }
        // 获取上一个职位的玩家信息
        $member = self::memberByJob($job, (int)$info['country']);
        if ($member === null) {
            // 直接升官
            $type = 1;
        } else {
            // 需要挑战
            $type = 2;
            // 比较总贡献
            if ((int)$member['allcontribute'] >= (int)$info['allcontribute']) {
                // 贡献不足
                $return['status'] = -1;
                return $return;
            }
            $return['info']['nickname'] = $member['nickname'];
        }

        $return['status'] = 1;
        $return['type'] = $type;
        $return['info']['job_name'] = self::jobName($job);
        return $return;
    }

    /**
     * 升官/篡位: c=country&m=usurp
     */
    public static function usurp(Context $ctx): array
    {
        /**
         * -8 获取玩家信息失败
         * -7 未加入国家
         * -6 添加战报失败
         * -5 获取对手信息失败
         * -4 获取成员信息失败
         * -3 挑战失败
         * -2 贡献不足
         * -1 获取成员信息失败
         *  0 失败
         *  1 成功
         *  2 已经是君主
         *  3 等级不足
         */
        $return = ['status' => 0, 'info' => []];
        $self = self::currentMember($ctx);
        if ($self === null) {
            return $return;
        }
        if (!self::isOpen($self)) {
            // 等级不足
            $return['status'] = 3;
            return $return;
        }

        $myUid = (int)$self['uid'];
        if ((int)$self['country'] === 0) {
            // 未加入国家
            $return['status'] = -7;
            return $return;
        }
        $info = self::memberCountry($myUid);
        if ($info === null) {
            // 获取玩家信息失败
            $return['status'] = -8;
            return $return;
        }
        // 取得职位
        if ((int)$info['job'] > 1) {
            $job = (int)$info['job'] - 1;
        } else {
            // 已经是君主
            $return['status'] = 2;
            return $return;
        }
        $infoCountry = (int)$info['country'];
        // 获取上一个职位的玩家信息
        $member = self::memberByJob($job, $infoCountry);
        $success = 0;
        $battle = [];
        $base = null;
        $battleInfo = null;
        if ($member === null) {
            // 职位空缺
            self::updateMemberInfo(['job' => $job], $myUid);
            $success = 1;
        } else {
            // 比较总贡献
            if ((int)$member['allcontribute'] >= (int)$info['allcontribute']) {
                // 贡献不足
                $return['status'] = -2;
                return $return;
            }
            // pk
            $battle = self::battlePk($myUid, (int)$member['uid']);
            if ((int)$battle['status'] !== 1) {
                // 挑战失败
                $return['status'] = -3;
                return $return;
            }
            if ((int)$battle['info']['assess'] >= 1) {
                if (self::updateMemberInfo(['job' => $job], $myUid) !== 1) {
                    $return['status'] = -9;
                    return $return;
                }
                if (self::updateMemberInfo(['job' => (int)$info['job']], (int)$member['uid']) !== 1) {
                    $return['status'] = -10;
                    return $return;
                }
                // 升官成功
                $success = 1;
            }

            $base = self::member((int)$member['uid']);
            if ($base === null) {
                // 获取对手信息失败
                $return['status'] = -5;
                return $return;
            }

            $battleInfo = ['win' => $battle['info']['assess']];
            $return['battle'] = $battle['info'];
            $return['battle']['left_role'] = [
                'name' => $self['nickname'],
                'level' => (int)$self['level'],
            ];
            $return['battle']['right_role'] = [
                'name' => $base['nickname'],
                'level' => (int)$base['level'],
            ];
        }
        $pmid = 0;
        if ($success === 1) {
            $list = self::memberList($infoCountry);
            // 篡位
            if ($job === 1) {
                self::updateCountryInfo($infoCountry, [
                    'uid' => $myUid,
                    'nickname' => $self['nickname'],
                ]);
                // 给成员发,不包括自己和原君主
                $uidarr = [];
                foreach ($list as $value) {
                    $vUid = (int)$value['uid'];
                    if ($member !== null) {
                        if ($myUid !== $vUid && $vUid !== (int)$member['uid']) {
                            $uidarr[] = $vUid;
                        }
                    } elseif ($myUid !== $vUid) {
                        $uidarr[] = $vUid;
                    }
                }
                if ($uidarr !== []) {
                    self::sendMessage($uidarr, self::emUser($self) . self::LANG_MESSAGE_COUNTRY_USURPER, 1);
                }
            }
            if ($member !== null) {
                // 给原君主发
                $pmid = self::sendMessage([(int)$member['uid']], self::emUser($self) . self::LANG_MESSAGE_COUNTRY_BEAT_YOU, 1);
            }
        } else {
            // 升官失败
            $pmid = self::sendMessage([(int)$member['uid']], self::emUser($self) . self::LANG_MESSAGE_COUNTRY_CHALLENGE_LOSE, 1);
            $list = self::memberList($infoCountry);
        }
        if ($member !== null && $battleInfo !== null) {
            // 依赖未移植：message_model->record_pk_report 信件战报（from/fromlevel/to/tolevel/report 落库供信件回放）
            $battleInfo['report'] = $pmid;
            $battleInfo['uid'] = (int)$member['uid'];
        }
        if ($list === []) {
            // 获取国家成员失败
            $return['status'] = -4;
            return $return;
        }

        if ($battleInfo !== null) {
            $return['info'] = array_merge(self::sortMemberList($list, $self), $battleInfo);
        } else {
            $return['info'] = self::sortMemberList($list, $self);
        }
        $return['status'] = 1;
        $return['list'] = $list;
        return $return;
    }

    /**
     * 国库: c=country&m=storage
     */
    public static function storage(Context $ctx): array
    {
        /**
         * -4 获取俸禄信息失败
         * -3 获取捐献规则失败
         * -2 获取成员列表失败
         * -1 获取国家信息失败
         *  0 失败
         *  1 成功
         *  2 等级不足
         */
        $return = ['status' => 0];
        $member = self::currentMember($ctx);
        if ($member === null) {
            return $return;
        }
        $level = (int)$member['level'];
        $return['islock'] = $level < 50 ? 1 : 0;
        if (!self::isOpen($member)) {
            // 等级不足
            $return['status'] = 2;
            return $return;
        }
        $country = self::countryInfo((int)$member['country']);
        if ($country === null) {
            // 获取国家信息失败
            $return['status'] = -1;
            return $return;
        }

        $countryArr = [];
        foreach (['level', 'exp', 'levelupexp'] as $value) {
            $countryArr[$value] = $country[$value];
        }
        $list = self::memberList((int)$country['id'], 2);
        if ($list === []) {
            // 获取国家成员失败
            $return['status'] = -2;
            return $return;
        }
        $uid = (int)$member['uid'];
        // 旧版成员不在列表时 $ratio 未定义（按 0 参与计算）
        $ratio = 0.0;
        foreach ($list as $value) {
            if ((int)$value['uid'] === $uid) {
                $return['info']['allcontribute'] = $value['contribute'];
                $return['info']['level'] = $country['level'];
                $return['info']['job'] = $value['job'];
                $return['info']['rank'] = $value['rank'];
                $return['info']['job_name'] = self::jobName((int)$value['job']);
                $ratio = (float)(CountryJob::ROWS[(int)$value['job']]['ratio'] ?? 0.0);
            }
        }
        $info = self::memberInfo($uid, (int)$member['country']);
        if ($info === null) {
            // 获取成员信息失败
            $return['status'] = -5;
            return $return;
        }

        $get = 0;
        if (date('Ymd') <= date('Ymd', (int)$info['salarytime'])) {
            // 今日已领取
            $get = 1;
        }
        $rule = [];
        // 获取捐献规则
        $rule['silver'] = self::donatePrice('silver', (int)$info['silvertimes'] + 1);
        $rule['gold'] = self::donatePrice('gold', (int)$info['goldtimes'] + 1);

        $rule['silver'] *= $level;
        $rule['contribute'] = self::DONATE;

        // 获取俸禄信息
        $salary = self::levelupRule((int)$country['level']);
        if ($salary === null) {
            // 获取俸禄信息失败
            $return['status'] = -4;
            return $return;
        }

        $salary['silver'] *= $level;
        $salary['jade'] *= $level;

        $salaryArr = [];
        foreach (['silver', 'act', 'soul', 'jade'] as $value) {
            $salaryArr[$value] = (int)round($salary[$value] * $ratio, 0);
        }

        if ($level < 91) {
            unset($salaryArr['jade']);
        }

        if ($level < 51) {
            unset($salaryArr['soul']);
        }

        $return['status'] = 1;
        $return['country'] = $countryArr;
        $return['list'] = array_slice($list, 0, 19);
        $return['donate'] = $rule;
        $return['salary'] = $salaryArr;
        $return['get'] = $get;
        return $return;
    }

    /**
     * 捐献: c=country&m=donate
     */
    public static function donate(Context $ctx): array
    {
        /**
         * -8 加贡献失败
         * -7 更新贡献信息失败
         * -6 获取成员信息失败
         * -5 参数错误
         * -4 扣除元宝失败
         * -3 扣除银币失败
         * -2 获取捐献规则失败
         * -1 获取国家信息失败
         *  O 失败
         *  1 成功
         *  2 国家已满级
         *  3 银币不足
         *  4 元宝不足
         *  5 等级不足
         */
        $return = ['status' => 0];
        $self = self::currentMember($ctx);
        if ($self === null) {
            return $return;
        }
        $type = (int)$ctx->input('type', 0);
        $level = (int)$self['level'];
        $uid = (int)$self['uid'];
        if ($level < 50) {
            // 等级不足
            $return['status'] = 5;
            return $return;
        }
        $country = self::countryInfo((int)$self['country']);
        if ($country === null) {
            // 获取国家信息失败
            $return['status'] = -1;
            return $return;
        }

        if ((int)$country['level'] === 200 && (int)$country['exp'] === (int)$country['levelupexp']) {
            // 国家已满级
            $return['status'] = 2;
            return $return;
        }
        $member = self::memberInfo($uid, (int)$self['country']);
        if ($member === null) {
            $return['status'] = -6;
            return $return;
        }

        if ((int)$self['vip'] === 0 && time() - (int)$member['joindateline'] < 7 * 86400 && (int)$member['contribute'] + self::DONATE > 100) {
            // 新加入国家7天内贡献不能超过100
            $return['status'] = -11;
            return $return;
        }
        // 依赖未移植：每日计划任务 member_model->clear_donate（goldtimes/silvertimes/learntimes 清零）与 clear_contribute（今日贡献清零）
        if ($type === 1) {
            // 银币
            $times = (int)$member['silvertimes'] + 1;
            $cost = self::donatePrice('silver', $times) * $level;
            if (!Member::deductCurrency($uid, silver: $cost)) {
                // 银币不足
                $return['status'] = 3;
                return $return;
            }

            // 加贡献
            $data = [
                'silvertimes' => $times,
                'contribute' => (int)$member['contribute'] + self::DONATE,
                'allcontribute' => (int)$member['allcontribute'] + self::DONATE,
            ];

            $rule = [];
            $rule['silver'] = self::donatePrice('silver', $times + 1) * $level;
        } elseif ($type === 2) {
            // 元宝
            $times = (int)$member['goldtimes'] + 1;
            $cost = self::donatePrice('gold', $times);
            if (!Member::deductCurrency($uid, gold: $cost)) {
                // 元宝不足
                $return['status'] = 4;
                return $return;
            }
            // 加贡献
            $data = [
                'goldtimes' => $times,
                'contribute' => (int)$member['contribute'] + self::DONATE,
                'allcontribute' => (int)$member['allcontribute'] + self::DONATE,
            ];

            $rule = [];
            $rule['gold'] = self::donatePrice('gold', $times + 1);
            // 依赖未移植：stat_model->country_donate 消费统计记录
        } else {
            // 参数错误
            $return['status'] = -5;
            return $return;
        }

        // 更新玩家信息
        if (self::updateMemberInfo($data, $uid) !== 1) {
            // 更新贡献信息失败
            $return['status'] = -7;
            return $return;
        }
        // 增加贡献
        $add = self::addExp($country, self::DONATE);
        if ($add['status'] !== 1) {
            // 加贡献失败
            $return['status'] = -8;
            return $return;
        }
        $list = self::memberList((int)$country['id'], 2);
        if ($list === []) {
            // 获取国家成员失败
            $return['status'] = -9;
            return $return;
        }
        foreach ($list as $value) {
            if ((int)$value['uid'] === $uid) {
                $return['info']['allcontribute'] = $value['contribute'];
                $return['info']['level'] = $add['info']['level'];
                $return['info']['job'] = $value['job'];
                $return['info']['rank'] = $value['rank'];
                $return['info']['job_name'] = self::jobName((int)$value['job']);
            }
        }

        // 记录贡献log
        self::addDonateLog($self, 'donate', [
            'type' => $type,
            'cost' => $cost,
            'donate' => self::DONATE,
            'contribute' => $data['contribute'],
            'allcontribute' => $data['allcontribute'],
        ]);

        // 依赖未移植：actnewyear_model->add_task_num 春节送福活动日常任务计数(23)

        $after = self::member($uid);
        $return['status'] = 1;
        $return['list'] = array_slice($list, 0, 19);
        $return['country'] = $add['info'];
        $return['donate'] = $rule;
        $return['donate']['contribute'] = self::DONATE;
        $return['silver'] = (int)($after['silver'] ?? 0);
        $return['gold'] = (int)($after['gold'] ?? 0);
        return $return;
    }

    /**
     * 领取俸禄: c=country&m=get_salary
     */
    public static function get_salary(Context $ctx): array
    {
        /**
         * -5 更新玩家信息失败
         * -4 领取俸禄失败
         * -3 获取俸禄信息失败
         * -2 获取国家信息失败
         * -1 获取成员信息失败
         *  0 失败
         *  1 成功
         *  2 今日已领取
         *  3 等级不足
         */
        $return = ['status' => 0];
        $self = self::currentMember($ctx);
        if ($self === null) {
            return $return;
        }
        if (!self::isOpen($self)) {
            // 等级不足
            $return['status'] = 3;
            return $return;
        }
        $uid = (int)$self['uid'];
        $level = (int)$self['level'];
        $info = self::memberInfo($uid, (int)$self['country']);
        if ($info === null) {
            // 获取成员信息失败
            $return['status'] = -1;
            return $return;
        }

        $time = time();
        if (date('Ymd', $time) <= date('Ymd', (int)$info['salarytime'])) {
            // 今日已领取
            $return['status'] = 2;
            return $return;
        }

        $country = self::countryInfo((int)$self['country']);
        if ($country === null) {
            // 获取国家信息失败
            $return['status'] = -2;
            return $return;
        }

        $salary = self::levelupRule((int)$country['level']);
        if ($salary === null) {
            // 获取俸禄信息失败
            $return['status'] = -3;
            return $return;
        }

        $salary['silver'] *= $level;
        $salary['jade'] *= $level;

        $ratio = (float)(CountryJob::ROWS[(int)$info['job']]['ratio'] ?? 0.0);

        foreach (['silver', 'act', 'soul', 'jade'] as $value) {
            $salary[$value] = (int)round($salary[$value] * $ratio, 0);
        }

        if ($level < 91) {
            unset($salary['jade']);
        }

        if ($level < 51) {
            unset($salary['soul']);
        }

        if (self::updateMemberInfo(['salarytime' => $time], $uid) !== 1) {
            // 更新玩家信息失败
            $return['status'] = -5;
            return $return;
        }

        Member::addCurrency(
            $uid,
            silver: $salary['silver'],
            act: $salary['act'],
            soul: $salary['soul'] ?? 0
        );
        // 依赖未移植：member_model->add_jade 玉石（新服 member 表无 jade 字段，91 级以上的玉石俸禄未发放）

        $after = self::member($uid);
        $return['status'] = 1;
        $return['silver'] = (int)($after['silver'] ?? 0);
        $return['act'] = (int)($after['act'] ?? 0);
        $return['soul'] = (int)($after['soul'] ?? 0);
        return $return;
    }

    /**
     * 搜索国家: c=country&m=search
     */
    public static function search(Context $ctx): array
    {
        /**
         * -4 获取公告失败
         * -3 获取国家排名失败
         * -2 该国家不存在
         * -1 名称不能为空,或大于7个字符
         *  0 失败
         *  1 成功
         *  2 等级不足
         */
        $return = ['status' => 0];
        $self = self::currentMember($ctx);
        if ($self === null) {
            return $return;
        }
        $name = (string)$ctx->input('name', '');
        if (!self::isOpen($self)) {
            // 等级不足
            $return['status'] = 2;
            return $return;
        }

        if ($name === '' || mb_strlen($name) > 7) {
            // 名称不能为空,或大于7个字符
            $return['status'] = -1;
            return $return;
        }
        $country = self::countryByName($name);
        if ($country === null) {
            // 该国家不存在
            $return['status'] = -2;
            return $return;
        }
        $rank = self::countryRank((int)$country['id']);
        if ($rank === []) {
            // 获取国家排名失败
            $return['status'] = -3;
            return $return;
        }

        $notice = self::notice((int)$country['id']);
        $country['notice'] = $notice['notice'];
        $return['status'] = 1;
        $return['country'] = $country;
        $return['list'] = [$rank];
        return $return;
    }

    /**
     * 国家信息: c=country&m=get_country_info
     */
    public static function get_country_info(Context $ctx): array
    {
        /**
         * -2 获取国家公告失败
         * -1 获取国家信息失败
         *  0 失败
         *  1 成功
         *  2 等级不足
         */
        $return = ['status' => 0];
        $self = self::currentMember($ctx);
        if ($self === null) {
            return $return;
        }
        $id = (int)$ctx->input('id', 0);
        if (!self::isOpen($self)) {
            // 等级不足
            $return['status'] = 2;
            return $return;
        }
        $country = self::countryInfo($id);
        if ($country === null) {
            // 获取国家信息失败
            $return['status'] = -1;
            return $return;
        }
        $notice = self::notice($id);
        $country['notice'] = $notice['notice'];
        $return['status'] = 1;
        $return['country'] = $country;
        return $return;
    }

    /**
     * 国师授艺-入口: c=country&m=learn_index
     */
    public static function learn_index(Context $ctx): array
    {
        /**
         * -3 获取授艺规则失败
         * -2 获取国家信息失败
         * -1 未加入国家
         *  0 失败
         *  1 成功
         *  2 等级不足
         */
        $return = ['status' => 0];
        $self = self::currentMember($ctx);
        if ($self === null) {
            return $return;
        }

        $level = (int)$self['level'];
        if ($level < 30) {
            // 等级不足
            $return['status'] = 2;
            return $return;
        }

        if ((int)$self['country'] === 0) {
            // 未加入国家
            $return['status'] = -1;
            return $return;
        }

        $country = self::countryInfo((int)$self['country']);
        if ($country === null) {
            // 获取国家信息失败
            $return['status'] = -2;
            return $return;
        }

        $times = self::learnTimes((int)$country['level']);

        $list = [];
        foreach (CountryLearn::ROWS as $value) {
            $value['exp'] *= $level;
            $list[] = $value;
        }

        $info = self::memberCountry((int)$self['uid']);
        if ($info === null) {
            // 获取成员国家信息失败
            $return['status'] = -4;
            return $return;
        }

        $return['status'] = 1;
        $return['level'] = (int)$country['level'];
        $return['all_times'] = $times;
        $return['times'] = $times - (int)$info['learntimes'];
        $return['rule'] = self::LEARN_TIMES;
        $return['list'] = $list;
        return $return;
    }

    /**
     * 国师授艺-学艺: c=country&m=learn
     */
    public static function learn(Context $ctx): array
    {
        /**
         * -7 更新次数失败
         * -6 扣元宝失败
         * -5 获取阵法信息失败
         * -4 获取学艺规则失败
         * -3 获取成员信息失败
         * -2 获取国家信息失败
         * -1 未加入国家
         *  0 失败
         *  1 成功
         *  2 等级不足
         *  3 元宝不足
         *  4 没有次数
         *  5 阵上没有将领
         */
        $return = ['status' => 0];
        $self = self::currentMember($ctx);
        if ($self === null) {
            return $return;
        }

        $type = (int)$ctx->input('type', 0);
        $uid = (int)$self['uid'];
        $level = (int)$self['level'];

        if ($level < 30) {
            // 等级不足
            $return['status'] = 2;
            return $return;
        }

        if ((int)$self['country'] === 0) {
            // 未加入国家
            $return['status'] = -1;
            return $return;
        }

        $country = self::countryInfo((int)$self['country']);
        if ($country === null) {
            // 获取国家信息失败
            $return['status'] = -2;
            return $return;
        }

        $member = self::memberCountry($uid);
        if ($member === null) {
            // 获取成员信息失败
            $return['status'] = -3;
            return $return;
        }

        $info = CountryLearn::ROWS[$type] ?? null;
        if ($info === null) {
            // 获取学艺规则失败
            $return['status'] = -4;
            return $return;
        }

        if ((int)$info['gold'] > (int)$self['gold']) {
            // 元宝不足
            $return['status'] = 3;
            return $return;
        }

        $times = self::learnTimes((int)$country['level']);
        if ((int)$member['learntimes'] >= $times) {
            // 次数已使用完
            $return['status'] = 4;
            return $return;
        }

        // 上阵武将加经验
        $matrix = OwnMatrix::own($uid, OwnMatrix::activeId($uid));
        if ($matrix === null) {
            // 获取阵法信息失败
            $return['status'] = -5;
            return $return;
        }

        $gidArr = [];
        for ($i = 1; $i <= 9; $i++) {
            if ($matrix['position' . $i] > 0) {
                $gidArr[] = $matrix['position' . $i];
            }
        }

        if ($gidArr === []) {
            // 阵法上没有将领
            $return['status'] = 5;
            return $return;
        }

        $general = [];
        foreach (DB::table('general')->whereIn('id', $gidArr)->where('uid', $uid)->whereNull('deleted_at')->get() as $row) {
            $general[(int)$row['id']] = $row;
        }
        // 扣元宝
        if (!Member::deductCurrency($uid, gold: (int)$info['gold'])) {
            // 扣元宝失败
            $return['status'] = -6;
            return $return;
        }

        // 增加已使用次数
        $data = ['learntimes' => (int)$member['learntimes'] + 1];
        if (self::updateMemberInfo($data, $uid) !== 1) {
            // 更新次数失败
            $return['status'] = -7;
            return $return;
        }

        $now = time();
        $addexp = (int)$info['exp'] * $level;
        $log = [];
        foreach ($gidArr as $key => $gid) {
            $add = self::addGeneralExp($uid, $level, $gid, $addexp);
            $gLevel = (int)($general[$gid]['level'] ?? 0);
            $gExp = (int)($general[$gid]['exp'] ?? 0);
            // 依赖未移植：武将转生 turn（gturn1 未记录，按列默认 0）
            $log[$key] = [
                'uid' => $uid,
                'level' => $level,
                'vip' => (int)$self['vip'],
                'cost' => (int)$info['gold'],
                'gid' => $gid,
                'addexp' => $addexp,
                'glevel1' => $gLevel,
                'gexp1' => $gExp,
                'glevelupexp1' => self::generalLevelupExp($gLevel),
                'dateline' => $now,
            ];

            if ($add['status'] === 1) {
                $log[$key]['glevel2'] = $add['info']['level'];
                $log[$key]['gexp2'] = $add['info']['exp'];
                $log[$key]['glevelupexp2'] = $add['info']['levelupexp'];
                $log[$key]['status'] = $add['status'];
            } else {
                $log[$key]['glevel2'] = $gLevel;
                $log[$key]['gexp2'] = $gExp;
                $log[$key]['glevelupexp2'] = self::generalLevelupExp($gLevel);
                // status (3 等级上限、4需要转生)；旧库非严格模式下负值落库为 0
                $log[$key]['status'] = max(0, $add['status']);
            }
        }

        DB::table('country_learn_log')->insertBatch(array_values($log));

        $return['status'] = 1;
        $return['addexp'] = $addexp;
        $return['all_times'] = $times;
        $return['times'] = $times - $data['learntimes'];
        $return['gold'] = (int)(self::member($uid)['gold'] ?? 0);
        return $return;
    }

    /**
     * 国家战报 - 国家玉矿、海外贸易战斗日志: c=country&m=fight_notice
     */
    public static function fight_notice(Context $ctx): array
    {
        /**
         *  0 失败
         *  1 成功
         *  2 等级不足
         */
        $return = ['status' => 0, 'log' => []];
        $self = self::currentMember($ctx);
        if ($self === null) {
            return $return;
        }
        // 判断开启等级
        if ((int)$self['level'] < 26) {
            $return['status'] = 2;
            return $return;
        }
        // 依赖未移植：countryfight_model（zc_sanguo_country_fight_notice 国家玉矿/海外贸易战斗日志的写入与按 type(1 荣誉/0 屈辱)、page 分页查询）
        $return['status'] = 1;
        $return['log'] = [];
        $return['total'] = 0;
        return $return;
    }

    // ------------------------------------------------------------------
    // 内部逻辑（移植自 country_model 等）
    // ------------------------------------------------------------------

    /**
     * @return array<string, mixed>|null
     */
    private static function currentMember(Context $ctx): ?array
    {
        return self::member(MemberHandler::resolveUid($ctx));
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function member(int $uid): ?array
    {
        return DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
    }

    /**
     * _open：主公等级 > 25 开启国家
     */
    private static function isOpen(array $member): bool
    {
        return (int)$member['level'] > 25;
    }

    /**
     * _freeze：1 国战期间(周六 17:30~21:00)；2 国家boss期间(周五 20:30~20:45)
     */
    private static function freeze(int $now): int
    {
        $week = (int)date('w', $now);
        $hour = (int)date('Hi', $now);
        if ($week === 6 && $hour >= 1730 && $hour <= 2100) {
            return 1;
        }
        if ($week === 5 && $hour >= 2030 && $hour <= 2045) {
            return 2;
        }
        return 0;
    }

    /**
     * 去掉新服表的时间戳列，保持旧版输出字段
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function strip(array $row): array
    {
        unset($row['created_at'], $row['updated_at'], $row['deleted_at']);
        return $row;
    }

    private static function jobName(int $job): ?string
    {
        return CountryJob::ROWS[$job]['job'] ?? null;
    }

    private static function emUser(array $member): string
    {
        return '<em uid="' . (int)$member['uid'] . '">' . $member['nickname'] . '</em>';
    }

    /**
     * 国家信件（旧版 message_model->send_message type 7，发件人为系统「国家通知」）
     *
     * @param list<int> $touids
     */
    private static function sendMessage(array $touids, string $message, int $report = 0): int
    {
        return MessageHandler::sendSystemMessage($touids, self::MESSAGE_TYPE_COUNTRY, self::LANG_MESSAGE_COUNTRY_TITLE, $message, $report);
    }

    /**
     * 更新玩家国别（member_model->update_country），同步失效玩家缓存
     */
    private static function updateMemberCountry(int $country, int $uid): int
    {
        $affected = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->update(['country' => $country]);
        Redis::del('player:' . $uid);
        return $affected > 0 ? 1 : 0;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function memberCountry(int $uid): ?array
    {
        $row = DB::table('member_country')->where('uid', $uid)->whereNull('deleted_at')->first();
        return $row === null ? null : self::strip($row);
    }

    /**
     * get_member_info($uid, $country, NO_OUTPUT)
     *
     * @return array<string, mixed>|null
     */
    private static function memberInfo(int $uid, int $country): ?array
    {
        $row = DB::table('member_country')
            ->where('country', $country)
            ->where('uid', $uid)
            ->whereNull('deleted_at')
            ->first();
        return $row === null ? null : self::strip($row);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function memberByJob(int $job, int $country): ?array
    {
        $row = DB::table('member_country')
            ->where('country', $country)
            ->where('job', $job)
            ->whereNull('deleted_at')
            ->limit(1)
            ->first();
        return $row === null ? null : self::strip($row);
    }

    /**
     * 获取国家第二官职
     *
     * @return array<string, mixed>|null
     */
    private static function secondJob(int $country): ?array
    {
        $row = DB::table('member_country')
            ->where('country', $country)
            ->where('job', '>', 1)
            ->whereNull('deleted_at')
            ->orderBy('job', 'ASC')
            ->limit(1)
            ->first();
        return $row === null ? null : self::strip($row);
    }

    /**
     * 获取最低职位
     */
    private static function lastJob(int $country): int
    {
        $row = DB::table('member_country')
            ->where('country', $country)
            ->whereNull('deleted_at')
            ->orderBy('job', 'DESC')
            ->limit(1)
            ->first();
        return $row === null ? 0 : (int)$row['job'];
    }

    private static function addMemberInfo(int $country, int $job, int $uid, string $nickname): int
    {
        $data = [
            'uid' => $uid,
            'nickname' => $nickname,
            'country' => $country,
            'job' => $job,
            'contribute' => 0,
            'allcontribute' => 0,
            'salarytime' => 0,
            'joindateline' => time(),
        ];
        if ($job === 1) {
            $data['contribute'] = self::CREATE_DONATE;
            $data['allcontribute'] = self::CREATE_DONATE;
        }
        return DB::table('member_country')->insert($data) !== false ? 1 : 0;
    }

    /**
     * 更新玩家国家信息（update_member_info）
     *
     * @param array<string, mixed> $data
     */
    private static function updateMemberInfo(array $data, int $uid): int
    {
        if (isset($data['contribute']) && $data['contribute'] > 0) {
            // 依赖未移植：member_country_taxes_model->change_taxes('+', 新增今日贡献 * 0.1) 国家税金累计
        }
        $affected = DB::table('member_country')->where('uid', $uid)->whereNull('deleted_at')->update($data);
        return $affected > 0 ? 1 : 0;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function updateCountryInfo(int $id, array $data): int
    {
        $affected = DB::table('country')->where('id', $id)->whereNull('deleted_at')->update($data);
        return $affected > 0 ? 1 : 0;
    }

    /**
     * 国家信息（country_model->get_country_info 按 id），查不到返回 null
     *
     * @return array<string, mixed>|null
     */
    public static function countryInfo(int $id): ?array
    {
        $row = DB::table('country')->where('id', $id)->whereNull('deleted_at')->first();
        return $row === null ? null : self::strip($row);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function countryByName(string $name): ?array
    {
        $row = DB::table('country')->where('name', $name)->whereNull('deleted_at')->first();
        return $row === null ? null : self::strip($row);
    }

    /**
     * 添加国家
     *
     * @return array<string, mixed>
     */
    private static function addCountry(string $name, array $member): array
    {
        $rule = self::levelupRule(2);
        if ($rule === null) {
            return [];
        }
        $data = [
            'name' => $name,
            'level' => 1,
            'member' => 1,
            'uid' => (int)$member['uid'],
            'nickname' => $member['nickname'],
            'exp' => 0,
            'levelupexp' => (int)$rule['exp'],
        ];
        $id = DB::table('country')->insert($data);
        if (!is_int($id) || $id <= 0) {
            return [];
        }
        return array_merge(['id' => $id], $data);
    }

    private static function countryCount(): int
    {
        return DB::table('country')->whereNull('deleted_at')->count();
    }

    /**
     * 根据页码获取国家列表（list 以国家 id 为键）
     *
     * @return array<string, mixed>
     */
    private static function countryList(int $page): array
    {
        $page = $page > 0 ? $page : 1;
        $totals = self::countryCount();
        if ($totals === 0) {
            return [];
        }
        // 取得总页数
        $pages = (int)ceil($totals / self::PERPAGE);
        // 检查页码值合法性
        $page = $page > $pages ? $pages : $page;
        // 偏移量
        $offset = ($page - 1) * self::PERPAGE;
        $rows = DB::table('country')
            ->whereNull('deleted_at')
            ->orderBy('level', 'DESC')
            ->orderBy('exp', 'DESC')
            ->orderBy('id', 'ASC')
            ->limit(self::PERPAGE)
            ->offset($offset)
            ->get();
        if ($rows === []) {
            return [];
        }
        $return = ['list' => []];
        $i = 1;
        foreach ($rows as $value) {
            $value = self::strip($value);
            $value['rank'] = $offset + $i++;
            $return['list'][(int)$value['id']] = $value;
        }
        $return['page'] = $page;
        $return['pages'] = $pages;
        $return['perpage'] = self::PERPAGE;
        return $return;
    }

    /**
     * 全部国家分页列表（update_country_list 的返回值）
     *
     * @return list<array<string, mixed>>
     */
    private static function allCountryPages(): array
    {
        $return = [];
        $pages = (int)ceil(self::countryCount() / self::PERPAGE);
        for ($page = 1; $page <= $pages; $page++) {
            $return[] = self::countryList($page);
        }
        return $return;
    }

    /**
     * 获取国家信息(包括排名)
     *
     * @param int $all 0 输出单个; 1 输出所有
     * @return array<string, mixed>
     */
    private static function countryRank(int $id, int $page = 0, int $all = 0): array
    {
        if ($id === 0) {
            return [];
        }
        $pages = (int)ceil(self::countryCount() / self::PERPAGE);
        if ($page === 0) {
            $page = (int)ceil($pages / 2);
        }
        $rank = self::countryList($page);
        if (isset($rank['list'][$id])) {
            return $all === 1 ? $rank : $rank['list'][$id];
        }

        $prev = $next = $page;
        while ($prev > 0 || $next <= $pages) {
            if ($prev-- < 1) {
                break;
            }

            if ($next++ > $pages) {
                break;
            }
            $rank = self::countryList($prev);
            if (isset($rank['list'][$id])) {
                return $all === 1 ? $rank : $rank['list'][$id];
            }
            $rank = self::countryList($next);
            if (isset($rank['list'][$id])) {
                return $all === 1 ? $rank : $rank['list'][$id];
            }
        }
        return [];
    }

    /**
     * 获取成员列表信息
     *
     * @param int $sortType 排序:1 总贡献; 2 今日贡献
     * @return list<array<string, mixed>>
     */
    private static function memberList(int $id, int $sortType = 1, int $output = 1): array
    {
        $rows = DB::table('member_country')->where('country', $id)->whereNull('deleted_at')->get();
        if ($rows === []) {
            return [];
        }
        $return = array_map(self::strip(...), $rows);

        if ($output === 1) {
            $uidarr = array_map(static fn(array $v): int => (int)$v['uid'], $return);
            $info = [];
            foreach (DB::table('member')->whereIn('uid', $uidarr)->whereNull('deleted_at')->get() as $m) {
                $info[(int)$m['uid']] = [
                    'uid' => $m['uid'],
                    'nickname' => $m['nickname'],
                    'level' => $m['level'],
                    // 依赖未移植：member.dateline（最后活动时间），以 member.updated_at 近似
                    'dateline' => (int)strtotime((string)$m['updated_at']),
                ];
            }
            $sort = [];
            $sortId = [];
            foreach ($return as $key => $value) {
                if (isset($info[(int)$value['uid']])) {
                    $return[$key] = array_merge($value, $info[(int)$value['uid']]);
                }
                $sort[] = $sortType === 2 ? $value['contribute'] : $value['allcontribute'];
                $sortId[] = $value['job'];
            }
            array_multisort($sort, SORT_DESC, $sortId, SORT_ASC, $return);
            $i = 1;
            foreach ($return as $key => $value) {
                $return[$key]['rank'] = $i++;
            }
        }
        return $return;
    }

    /**
     * 计算我的官职/禅让/升官状态，并按官职排序成员列表（_sort_member_list）
     *
     * @param list<array<string, mixed>> $list
     * @return array<string, mixed>
     */
    private static function sortMemberList(array &$list, array $member): array
    {
        $return = [];
        $now = time();
        $myUid = (int)$member['uid'];
        $sort = [];
        foreach ($list as $key => $value) {
            if ((int)$value['uid'] === $myUid) {
                $myJob = (int)$value['job'];
                $return['job'] = $value['job'];
                $return['rank'] = $value['rank'];
                $return['closeday'] = $now - (int)$value['joindateline'] < 7 * 86400 && (int)$member['vip'] === 0 ? 1 : 0;
                // 不可升官
                $return['usurp'] = 0;
                if ($myJob === 1) {
                    // 可禅让 / 不可禅让
                    $return['make_way'] = count($list) > 1 ? 1 : 0;
                } else {
                    $exists = 0;
                    foreach ($list as $v) {
                        $exists = 0;
                        if ((int)$v['job'] === $myJob - 1) {
                            $exists = 1;
                            if ($v['rank'] > $value['rank']) {
                                // 2 可篡位; 1 可升官
                                $return['usurp'] = $myJob === 2 ? 2 : 1;
                                break;
                            }
                        }
                    }
                    if ($exists === 0) {
                        // 职位空缺
                        $return['usurp'] = $myJob === 2 ? 2 : 1;
                    }
                    // 不可禅让
                    $return['make_way'] = 0;
                }
            }
            $list[$key]['dateline'] = $now - (int)$value['dateline'];
            $list[$key]['job_name'] = self::jobName((int)$value['job']);
            $sort[] = $value['job'];
        }
        array_multisort($sort, SORT_ASC, $list);
        return $return;
    }

    /**
     * 获取公告（不存在则插入空公告）
     *
     * @return array<string, mixed>
     */
    private static function notice(int $country): array
    {
        $row = DB::table('country_notice')->where('id', $country)->whereNull('deleted_at')->first();
        if ($row !== null) {
            return self::strip($row);
        }
        $row = ['id' => $country, 'notice' => ''];
        if (DB::table('country_notice')->where('id', $country)->exists()) {
            // 旧版 del_notice 为物理删除后重新插入；软删除下复用该行（主键即国家 id）
            DB::table('country_notice')->where('id', $country)->update(['notice' => '', 'deleted_at' => null]);
        } else {
            DB::table('country_notice')->insert($row);
        }
        return $row;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function applyList(int $uid): array
    {
        return DB::table('country_apply')->where('uid', $uid)->whereNull('deleted_at')->get();
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function applyInfo(int $country, int $uid): ?array
    {
        return DB::table('country_apply')
            ->where('uid', $uid)
            ->where('country', $country)
            ->whereNull('deleted_at')
            ->first();
    }

    private static function delApply(int $uid, int $country): void
    {
        DB::table('country_apply')
            ->where('uid', $uid)
            ->where('country', $country)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * 增加待审核信息
     */
    private static function addAuditInfo(int $id): void
    {
        $key = self::AUDIT_KEY . $id;
        if (Redis::exists($key)) {
            Redis::incr($key);
        } else {
            self::updateAuditInfo($id);
        }
    }

    /**
     * 减少待审核信息
     */
    private static function subAuditInfo(int $id): void
    {
        $key = self::AUDIT_KEY . $id;
        if (Redis::exists($key)) {
            if ((int)Redis::get($key) > 0) {
                Redis::decr($key);
            }
        } else {
            self::updateAuditInfo($id);
        }
    }

    /**
     * 获取待审核信息
     */
    private static function getAuditInfo(int $id): int
    {
        $key = self::AUDIT_KEY . $id;
        $info = Redis::get($key);
        if ($info !== null) {
            return (int)$info;
        }
        $return = self::auditCount($id);
        Redis::set($key, $return, self::TTL);
        return $return;
    }

    /**
     * 更新审核信息
     */
    private static function updateAuditInfo(int $id): int
    {
        $return = self::auditCount($id);
        Redis::set(self::AUDIT_KEY . $id, $return, self::TTL);
        return $return;
    }

    private static function auditCount(int $id): int
    {
        return DB::table('country_apply')->where('country', $id)->whereNull('deleted_at')->count();
    }

    private static function joinNumberKey(int $country, int $now): string
    {
        return self::JOIN_NUM_KEY . $country . '_' . date('Ymd', $now);
    }

    /**
     * 获取一个国家单日加入人数
     */
    private static function countryJoinNumber(int $country, int $now): int
    {
        return (int)(Redis::get(self::joinNumberKey($country, $now)) ?? 0);
    }

    /**
     * 获取贡献价格
     */
    private static function donatePrice(string $field, int $times): int
    {
        if (isset(CountryDonate::ROWS[$times])) {
            return (int)CountryDonate::ROWS[$times][$field];
        }
        if ($times > 1) {
            $rows = CountryDonate::ROWS;
            return (int)end($rows)[$field];
        }
        return 0;
    }

    /**
     * 获取国家升级规则
     *
     * @return array<string, mixed>|null
     */
    private static function levelupRule(int $level): ?array
    {
        if ($level > 200) {
            return null;
        }
        return CountryLevel::ROWS[$level] ?? null;
    }

    private static function learnTimes(int $countryLevel): int
    {
        $times = 0;
        foreach (self::LEARN_TIMES as $key => $value) {
            if ($value <= $countryLevel) {
                // 获取当前次数
                $times = $key;
            }
        }
        return $times;
    }

    /**
     * 增加国家经验
     *
     * @return array{status: int, info?: array<string, int>}
     */
    private static function addExp(array $country, int $exp): array
    {
        if ($exp <= 0) {
            return ['status' => -2];
        }

        $check = self::checkLevelup($exp + (int)$country['exp'], (int)$country['level'], 200);
        if ($check['status'] < 1) {
            return ['status' => -3];
        }
        $data = [
            'level' => $check['level'],
            'exp' => $check['exp'],
            'levelupexp' => $check['levelupexp'],
        ];
        $return = ['status' => 0, 'info' => $data];

        if (self::updateCountryInfo((int)$country['id'], $data) !== 1) {
            $return['status'] = -4;
            return $return;
        }

        // 依赖未移植：warcollege_model->update_country_gold_info 增加国家税金（新增经验 * 0.1）

        $return['status'] = 1;
        return $return;
    }

    /**
     * 国家检查是否升级
     *
     * @return array<string, int>
     */
    private static function checkLevelup(int $exp, int $level, int $maxlevel): array
    {
        /**
         *  0 失败
         *  1 成功
         *  2 到达主公等级上限
         *  3 需要转生
         */
        if ($level > $maxlevel) {
            return ['status' => 2];
        }

        if ($level === $maxlevel) {
            $rule = self::levelupRule($maxlevel);
            if (!isset($rule['exp'])) {
                return ['status' => -1];
            }
            $levelupexp = (int)$rule['exp'];
            if ($exp >= $levelupexp) {
                return ['exp' => $levelupexp, 'level' => $maxlevel, 'levelupexp' => $levelupexp, 'status' => 2];
            }
            return ['exp' => $exp, 'level' => $maxlevel, 'levelupexp' => $levelupexp, 'status' => 1];
        }

        $rule = self::levelupRule($level + 1);
        if (!isset($rule['exp'])) {
            return ['status' => -1];
        }
        $levelupexp = (int)$rule['exp'];
        if ($exp >= $levelupexp) {
            $result = self::checkLevelup($exp - $levelupexp, $level + 1, $maxlevel);
            return [
                'level' => $result['level'],
                'exp' => $result['exp'],
                'levelupexp' => $result['levelupexp'],
                'status' => $result['status'],
            ];
        }
        return ['level' => $level, 'exp' => $exp, 'levelupexp' => $levelupexp, 'status' => 1];
    }

    /**
     * 武将升级所需经验（general_model->get_levelupexp）
     */
    private static function generalLevelupExp(int $level): int
    {
        return $level < 1 ? 0 : GeneralHandler::getLevelUpExp($level);
    }

    /**
     * 武将加经验（general_model->add_exp；$heroLevel 为将领所属玩家的主公等级）
     *
     * @return array{status: int, info: array<string, int>}
     */
    public static function addGeneralExp(int $uid, int $heroLevel, int $gid, int $addexp): array
    {
        /**
         * -2 获取将领信息失败
         * -1 更新拥有将领表失败
         *  0 失败
         *  1 成功
         *  2 不在出征队列中
         *  3 等级已达主公等级上限
         *  4 需要转生
         */
        $return = ['status' => 0, 'info' => []];
        if ($addexp <= 0) {
            $return['status'] = -6;
            return $return;
        }
        $general = DB::table('general')->where('id', $gid)->where('uid', $uid)->whereNull('deleted_at')->first();
        if ($general === null) {
            $return['status'] = -2;
            return $return;
        }
        $level = (int)$general['level'];
        $exp = (int)$general['exp'];
        $levelupexp = self::generalLevelupExp($level);

        if ((int)$general['goout'] === 0) {
            // 不在出征队列中 2
            $return['status'] = 2;
            return $return;
        }

        // 等级限制(不超过主公)
        if ($level > $heroLevel) {
            $return['status'] = 3;
            return $return;
        }

        if ($level === $heroLevel && $exp === $levelupexp) {
            $return['status'] = 3;
            return $return;
        }
        // 依赖未移植：武将转生 turn/get_turn_rule（转生等级上限 50 + 转数*5，到达时返回 4 需要转生）

        $info = self::checkGeneralLevelup($exp + $addexp, $level, $heroLevel);
        if ($info['level'] > $level) {
            // 依赖未移植：expostulation_model->action(2, 升级数) 帮派谏言任务
            // 依赖未移植：mainquest_model->task_action(3, 等级) 主线任务
        }

        $affected = DB::table('general')
            ->where('uid', $uid)
            ->where('id', $gid)
            ->whereNull('deleted_at')
            ->update(['level' => $info['level'], 'exp' => $info['exp']]);
        if ($affected > 0) {
            $return['info'] = [
                'gid' => $gid,
                'exp' => $info['exp'],
                'level' => $info['level'],
                'levelupexp' => $info['levelupexp'],
                'addexp' => $addexp,
                'isturn' => $info['status'] === 3 ? 1 : 0,
            ];
            $return['status'] = 1;
        } else {
            $return['status'] = -1;
        }
        return $return;
    }

    /**
     * 武将检查是否升级（general_model->check_levelup，不含转生上限）
     *
     * @return array{level: int, exp: int, levelupexp: int, status: int}
     */
    private static function checkGeneralLevelup(int $exp, int $level, int $heroLevel): array
    {
        $levelupexp = self::generalLevelupExp($level);
        // 大于主公等级
        if ($level > $heroLevel) {
            $level -= 1;
            $levelupexp = self::generalLevelupExp($level);
            return ['level' => $level, 'exp' => $levelupexp, 'levelupexp' => $levelupexp, 'status' => 2];
        }
        if ($exp >= $levelupexp) {
            return self::checkGeneralLevelup($exp - $levelupexp, $level + 1, $heroLevel);
        }
        return ['level' => $level, 'exp' => $exp, 'levelupexp' => $levelupexp, 'status' => 1];
    }

    /**
     * 玩家对战（移植 battle_model->battle_pk），双方以各自阵法出战
     *
     * 旧版 battle_pk 先按左侧 check_user_team 设 status，再被右侧的结果覆盖：
     * 只有右侧（被挑战方）-1（没有阵法）/ -3（武将信息错误）时返回失败；任一侧 -2（阵法里没有武将）或左侧 -1 / -3
     * 都按空队伍继续战斗（空队伍直接判负）。这里照旧版保留该行为。
     *
     * @return array{status: int, info?: array<string, mixed>} status 1 成功（info 为旧版 battle() 的战斗结果），否则为旧版 battle_pk 的失败状态
     */
    private static function battlePk(int $uid, int $touid): array
    {
        $left = TeamBuilder::forMember($uid);
        $right = TeamBuilder::forMember($touid, defense: true, self: false);
        if ($right['status'] === -1 || $right['status'] === -3) {
            return ['status' => $right['status']];
        }

        // 攻方阵法记在左侧、守方（init_member_team_info($touid, 1)）记在右侧
        $engine = new BattleEngine($left['matrix_type'], $left['matrix_coe'], $right['matrix_type'], $right['matrix_coe']);
        return [
            'status' => 1,
            'info' => $engine->battle($left['team'], $right['team']),
        ];
    }

    /**
     * 记录贡献log
     *
     * @param array{type: int, cost: int, donate: int, contribute: int, allcontribute: int} $info
     */
    private static function addDonateLog(array $member, string $method, array $info): void
    {
        DB::table('country_donate_log')->insert([
            'uid' => (int)$member['uid'],
            'level' => (int)$member['level'],
            'vip' => (int)$member['vip'],
            'type' => $info['type'],
            'cost' => $info['cost'],
            'donate' => $info['donate'],
            'contribute' => $info['contribute'],
            'allcontribute' => $info['allcontribute'],
            'class' => 'country',
            'method' => $method,
            'argument' => '',
            'dateline' => time(),
        ]);
    }

    /**
     * 记录用户加入退出记录
     *
     * @param array{uid: int, operator_uid: int, country: int, status: int} $info
     */
    private static function addCountryLog(array $info, string $method, int $now): void
    {
        DB::table('country_log')->insert([
            'uid' => $info['uid'],
            'operator_uid' => $info['operator_uid'],
            'country' => $info['country'],
            'status' => $info['status'],
            'class' => 'country',
            'method' => $method,
            'dateline' => $now,
        ]);
    }
}
