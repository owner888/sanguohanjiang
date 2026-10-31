<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\Pvp\CountryHandler;
use Hanjiang\MasterData\CountryJob;
use Hanjiang\Model\Expostulation;
use Hanjiang\Model\Member;
use Hanjiang\Model\OwnSoul;
use Hanjiang\Model\StatPay;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 国家谏言（移植旧版 controllers/expostulation.php，存储见 Model\Expostulation，模版取 MasterData ExpostulationTemp）
 *
 * 玩家（50 级、入国满 7 天或 VIP）花元宝提出谏言，官职前三采纳（每国每天最多约 10 个，同类型同时只能有一个已通过的），
 * 国家成员支持（每天首次免费，之后花元宝），全国完成对应任务（强化 / 武将升级 / 培养 / 贸易 / 征收 / 通商 / 征战主线）后
 * 发起者与支持者领取银币或原魂与国家贡献。
 * 客户端 expostulationpop.js / countryworld.js 调用 index、authstr、adopt（id）、expostulation_list、put_expostulation（id）、
 * support（id）、refresh、get_reward（id）、get_expostulation_by_user。
 *
 * 同一玩家的提出 / 支持 / 刷新 / 领奖在玩家行锁里串行执行；采纳用条件更新（并发只成功一次）。
 */
final class ExpostulationHandler
{
    /** 谏言刷新价格 */
    private const int PRICE = 5;

    /** 提出谏言需要等级 */
    private const int LEVEL = 50;

    /** 输出的模版字段 */
    private const array TEMP_FIELDS = ['name', 'quality', 'value', 'put_value', 'sup_value', 'sup_price', 'type', 'get_type', 'put_contribute', 'sup_contribute'];

    /**
     * 只取我支持的谏言和我提出的谏言（进度最快的一个）: c=expostulation&m=get_expostulation_by_user
     */
    public static function get_expostulation_by_user(Context $ctx): array
    {
        $return = ['status' => 0, 'list' => []];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        $uid = (int)$member['uid'];
        if ((int)$member['country'] === 0) {
            // 未进入国家，无法参加谏言
            $return['status'] = -1;
            return $return;
        }
        $country = CountryHandler::memberInfo($uid, (int)$member['country']);
        if ($country === null || (int)$country['country'] === 0) {
            $return['status'] = -1;
            return $return;
        }
        // 取我的国家信息（返回国家等级，前端判断功能开放）
        $return['level'] = (int)(CountryHandler::countryInfo((int)$member['country'])['level'] ?? 0);
        // 获取谏言列表
        $getList = Expostulation::listByPass((int)$country['country'], 2);
        if ($getList === []) {
            // 暂时没有谏言
            $return['status'] = -2;
            return $return;
        }
        $isSupport = 0;
        $support = [];
        foreach ($getList as $val) {
            $support[] = $val['id'];
            if ($val['uid'] === $uid) {
                $isSupport = 1;
            }
        }
        // 取我的支持列表
        $supportList = Expostulation::supports($uid, $support);
        if ($supportList === [] && !$isSupport) {
            $return['status'] = -3;
            return $return;
        }
        // 得到我的支持（未领奖）或者提出的谏言列表
        $newlist = [];
        foreach ($getList as $val) {
            if ((isset($supportList[$val['id']]) && $supportList[$val['id']]['status'] !== 1)
                || (!isset($supportList[$val['id']]) && $val['uid'] === $uid)) {
                $newlist[$val['eid']] = $val;
            }
        }
        // 取模版信息
        $tempList = Expostulation::temps(array_keys($newlist));
        if ($tempList === []) {
            // 获取谏言模版信息失败
            $return['status'] = -4;
            return $return;
        }
        // 取出进度最快的一个任务
        $lastlist = [];
        foreach ($tempList as $val) {
            $lastlist[$val['id']] = (int)($val['value'] - $newlist[$val['id']]['plan']);
        }
        $key = array_keys($lastlist, min($lastlist))[0];
        $return['status'] = 1;
        foreach (['type', 'status', 'plan'] as $val) {
            $return['list'][$val] = $newlist[$key][$val];
            if ($tempList[$key]['value'] - $newlist[$key]['plan'] <= 0) {
                $return['status'] = 2;
            }
        }
        $return['list']['value'] = $tempList[$key]['value'];
        $return['list']['desc'] = Expostulation::description($newlist[$key]['type']);
        return $return;
    }

    /**
     * 初始化接口（已通过 / 已完成的谏言）: c=expostulation&m=index
     */
    public static function index(Context $ctx): array
    {
        $return = ['status' => 0, 'free_support' => 1, 'list' => []];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        $uid = (int)$member['uid'];
        $country = CountryHandler::memberInfo($uid, (int)$member['country']);
        if ($country === null || (int)$country['country'] === 0) {
            // 未进入国家，无法参加谏言
            $return['status'] = -1;
            return $return;
        }
        // 获取谏言列表
        $getList = Expostulation::listByPass((int)$country['country'], 2);
        if ($getList === []) {
            // 暂时没有谏言
            $return['status'] = -2;
            return $return;
        }
        $tempList = Expostulation::temps(array_column($getList, 'eid'));
        $supportList = Expostulation::supports($uid, array_column($getList, 'id'));
        if ($tempList === []) {
            // 获取谏言模版信息失败
            $return['status'] = -3;
            return $return;
        }
        $list = [];
        $statussort = $qualitysort = [];
        foreach ($getList as $key => $val) {
            if (isset($tempList[$val['eid']])) {
                $list[$key] = self::listItem($tempList[$val['eid']], $val, (int)$member['level']);
                $list[$key]['status'] = $val['status'];
                $list[$key]['plan'] = $val['plan'];
            }
            $newsort = 1;
            if (isset($supportList[$val['id']])) {
                // 已支持过
                $list[$key]['support'] = 1;
                $newsort = $val['status'] === 2 ? 3 : 2;
                if ($supportList[$val['id']]['status'] === 1) {
                    unset($list[$key]);
                }
            } elseif ($val['uid'] === $uid) {
                // 自己提出的
                $list[$key]['support'] = 2;
                $newsort = $val['status'] === 2 ? 3 : 2;
            } elseif ($val['status'] === 2) {
                unset($list[$key]);
            } else {
                $list[$key]['support'] = 0;
            }
            if (isset($list[$key])) {
                $list[$key]['sort'] = $newsort;
                $statussort[] = $newsort;
                $qualitysort[] = $tempList[$val['eid']]['quality'] ?? 0;
            }
        }
        // 先用当前状态排序（已完成，进行中，可支持），再用品级排序
        $list = array_values($list);
        array_multisort($statussort, SORT_DESC, $qualitysort, SORT_DESC, $list);
        if (date('ymd', (int)$country['support']) === date('ymd', time())) {
            $return['free_support'] = 0;
        }
        $return['status'] = 1;
        $return['list'] = $list;
        return $return;
    }

    /**
     * 领取任务奖励: c=expostulation&m=get_reward（id 谏言）
     */
    public static function get_reward(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $id = (int)$ctx->input('id', 0);
        if (self::member($uid) === null) {
            return ['status' => 0];
        }
        return Member::locked($uid, static function () use ($uid, $id): array {
            $return = ['status' => 0];
            $member = self::member($uid);
            $cid = (int)$member['country'];
            // 判断是否加入国家
            if ($cid === 0) {
                $return['status'] = -5;
                return $return;
            }
            $info = Expostulation::infoById($cid, $id);
            // 判断谏言是否存在
            if ($info === null) {
                $return['status'] = -1;
                return $return;
            }
            // 任务未完成，无法领取
            if ($info['status'] !== 2) {
                $return['status'] = -2;
                return $return;
            }
            // 查询该任务的模版信息
            $temp = Expostulation::temp($info['eid']);
            if ($temp === null) {
                // 旧版取不到模版时奖励为 0、照常领取；模版缺失属于配置错误，按谏言不存在处理
                $return['status'] = -1;
                return $return;
            }
            if ($uid === $info['uid']) {
                $value = $temp['put_value'] * (int)$member['level'];
                $getContribute = $temp['put_contribute'];
            } else {
                $value = $temp['sup_value'] * (int)$member['level'];
                $getContribute = $temp['sup_contribute'];
            }
            $country = CountryHandler::memberInfo($uid, $cid);
            if ($country === null) {
                // 旧版这里读不到国家成员信息时贡献按 0 继续；已不在国家里时同 -5
                $return['status'] = -5;
                return $return;
            }
            if ((int)$member['vip'] === 0 && time() - (int)$country['joindateline'] < 7 * 86400 && (int)$country['contribute'] + $getContribute > 100) {
                // 新加入国家7天内贡献不能超过100
                $return['status'] = -8;
                return $return;
            }
            // 查询这个任务我是否支持过
            $support = Expostulation::support($uid, $info['id']);
            if ($support !== null) {
                // 支持过，判断是否领取过奖励
                if ($support['status'] === 1) {
                    $return['status'] = -4;
                    return $return;
                }
            } elseif ($info['uid'] === $uid) {
                // 发起者添加一条记录
                Expostulation::addSupportLog($uid, $info['id']);
            } else {
                // 没支持过该谏言
                $return['status'] = -3;
                return $return;
            }
            // 修改我的领取状态（条件更新，并发只领一次）
            if (!Expostulation::updateGetStatus($uid, $info['id'])) {
                $return['status'] = -4;
                return $return;
            }
            // 添加银币或原魂
            if ($temp['get_type'] === 1) {
                Member::addCurrency($uid, silver: $value);
            } elseif ($temp['get_type'] === 2) {
                OwnSoul::addSoul($uid, $value, 0, 'expostulation', 'get_reward');
            }
            // 添加国家贡献、增加国家经验、记录贡献log
            CountryHandler::addContribute($member, $getContribute, 3, 'expostulation', 'get_reward');
            Expostulation::rewardMember($cid, $info['id'], $info['uid']);
            $return['status'] = 1;
            $return['get_type'] = $temp['get_type'];
            $return['get_value'] = $value;
            $return['get_contribute'] = $getContribute;
            $return['all_silver'] = (int)self::member($uid)['silver'];
            return $return;
        });
    }

    /**
     * 查看未审核的谏言列表: c=expostulation&m=authstr（status 2 为没有采纳权限）
     */
    public static function authstr(Context $ctx): array
    {
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        $country = CountryHandler::memberInfo((int)$member['uid'], (int)$member['country']);
        if ($country === null || (int)$country['country'] === 0) {
            // 未进入国家，无法参加谏言
            $return['status'] = -1;
            return $return;
        }
        $getList = Expostulation::listByPass((int)$country['country']);
        if ($getList === []) {
            // 暂时没有谏言
            $return['status'] = -2;
            return $return;
        }
        $tempList = Expostulation::temps(array_column($getList, 'eid'));
        if ($tempList === []) {
            // 获取谏言模版信息失败
            $return['status'] = -3;
            return $return;
        }
        $list = [];
        $qualitysort = [];
        foreach ($getList as $val) {
            if (isset($tempList[$val['eid']])) {
                $qualitysort[] = $tempList[$val['eid']]['quality'];
                $list[] = self::listItem($tempList[$val['eid']], $val, (int)$member['level']);
            }
        }
        // 按品级排序
        array_multisort($qualitysort, SORT_DESC, $list);
        // 判断职位是否能够采纳谏言
        $return['status'] = (int)$country['job'] > 3 ? 2 : 1;
        $return['list'] = $list;
        return $return;
    }

    /**
     * 采纳谏言: c=expostulation&m=adopt（id 谏言）
     */
    public static function adopt(Context $ctx): array
    {
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        $id = (int)$ctx->input('id', 0);
        // 取自己的国家信息
        $country = CountryHandler::memberInfo((int)$member['uid'], (int)$member['country']);
        if ($country === null || (int)$country['country'] === 0) {
            $return['status'] = -1;
            return $return;
        }
        $cid = (int)$country['country'];
        // 判断职位是否能够采纳谏言
        if ((int)$country['job'] > 3) {
            $return['status'] = -2;
            return $return;
        }
        // 根据ID查询谏言信息
        $info = Expostulation::infoById($cid, $id);
        if ($info === null) {
            $return['status'] = -3;
            return $return;
        }
        // 判断谏言是否已经被采纳过,或者被完成
        if ($info['status'] === 1) {
            $return['status'] = -4;
            return $return;
        }
        if ($info['status'] === 2) {
            $return['status'] = -5;
            return $return;
        }
        $now = time();
        $countryKey = 'country_adopt_key' . $cid;
        $passarr = Redis::get($countryKey);
        if (is_array($passarr)) {
            if (date('Ymd', (int)$passarr['dateline']) === date('Ymd', $now)) {
                // 每天采纳的谏言不能超过10个（旧版计数 > 10 才拦，实际可采纳 11 个）
                if ((int)$passarr['num'] > 10) {
                    $return['status'] = -9;
                    return $return;
                }
            } else {
                $passarr = ['num' => 0, 'dateline' => $now];
            }
        } else {
            $passarr = ['num' => 0, 'dateline' => $now];
        }
        // 判断谏言列表中是否有同种类型的谏言
        foreach (Expostulation::listByPass($cid, 1) as $val) {
            if ($val['type'] === $info['type']) {
                $return['status'] = -6;
                return $return;
            }
        }
        $passarr['num']++;
        Redis::set($countryKey, $passarr, 86401);
        // 修改谏言状态
        if (!Expostulation::updateStatus($id, 1, 0)) {
            // 修改失败
            $return['status'] = -7;
            return $return;
        }
        $return['status'] = 1;
        return $return;
    }

    /**
     * 提出谏言（列表展示）: c=expostulation&m=expostulation_list
     */
    public static function expostulation_list(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        if (self::member($uid) === null) {
            return ['status' => 0];
        }
        return Member::locked($uid, static function () use ($uid): array {
            $return = ['status' => 0];
            $member = self::member($uid);
            $country = CountryHandler::memberInfo($uid, (int)$member['country']);
            if ($country === null || (int)$country['country'] === 0) {
                $return['status'] = -1;
                return $return;
            }
            if ((string)$country['eid'] !== '') {
                // 按数据库的数据查询
                $temps = Expostulation::temps(array_map('intval', explode(',', (string)$country['eid'])));
                $list = ['status' => $temps === [] ? 0 : 1, 'list' => []];
                foreach (explode(',', (string)$country['eid']) as $key => $val) {
                    if (isset($temps[(int)$val])) {
                        $list['list'][$key] = $temps[(int)$val];
                    }
                }
            } else {
                // 根据个数刷新
                $list = Expostulation::listByRand($uid, (int)$member['level'], 4);
            }
            if ($list['status'] !== 1) {
                // 获取列表失败
                $return['status'] = -2;
                return $return;
            }
            $return['refresh'] = date('ymd', (int)$country['refresh']) === date('ymd', time()) ? 0 : 1;
            $return['status'] = 1;
            $return['list'] = self::tempItems($list['list'], (int)$member['level']);
            $return['price'] = self::PRICE;
            return $return;
        });
    }

    /**
     * 提出谏言: c=expostulation&m=put_expostulation（id 谏言模版，须在自己的谏言列表里）
     */
    public static function put_expostulation(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $id = (int)$ctx->input('id', 0);
        if (self::member($uid) === null) {
            return [];
        }
        return Member::locked($uid, static function () use ($uid, $id): array {
            $return = [];
            $member = self::member($uid);
            $now = time();
            // 判断该功能是否开启
            if ((int)$member['level'] < self::LEVEL) {
                $return['status'] = -7;
                return $return;
            }
            $country = CountryHandler::memberInfo($uid, (int)$member['country']);
            if ($country === null || (int)$country['country'] === 0) {
                $return['status'] = -1;
                return $return;
            }
            if ((int)$member['vip'] === 0 && $now - (int)$country['joindateline'] < 7 * 86400) {
                // 新加入国家7天内不能提出谏言
                $return['status'] = -8;
                return $return;
            }
            // 同时最多保留10个待审核谏言
            if (count(Expostulation::listByPass((int)$country['country'], 0)) >= 10) {
                $return['status'] = -9;
                return $return;
            }
            $eidArr = explode(',', (string)$country['eid']);
            // 判断是否是我的谏言列表里面的数据
            if (!in_array((string)$id, $eidArr, true)) {
                $return['status'] = -2;
                return $return;
            }
            // 取该条谏言的详细信息
            $info = Expostulation::temp($id);
            if ($info === null) {
                $return['status'] = -3;
                return $return;
            }
            // 判断金币
            if ((int)$member['gold'] < $info['put_price']) {
                $return['status'] = -4;
                return $return;
            }
            // 添加到用户谏言表
            if (!Expostulation::add($member, (int)$country['job'], $info, $now)) {
                $return['status'] = -6;
                return $return;
            }
            $site = 0;
            foreach ($eidArr as $key => $val) {
                if ((int)$val === $id) {
                    $site = $key;
                }
            }
            // 扣除金币，消费记录
            Member::deductCurrency($uid, gold: $info['put_price']);
            StatPay::record($uid, 'put_jianyan', $info['put_price']);
            // 刷新一条新的谏言替换提出的这条
            $newlist = Expostulation::listByRand($uid, (int)$member['level'], 1, $eidArr, $site);
            $return['status'] = 1;
            $return['all_gold'] = (int)self::member($uid)['gold'];
            $return['list'] = self::tempItems($newlist['list'], (int)$member['level']);
            return $return;
        });
    }

    /**
     * 支持谏言: c=expostulation&m=support（id 谏言；每天首次免费）
     */
    public static function support(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $id = (int)$ctx->input('id', 0);
        if (self::member($uid) === null) {
            return [];
        }
        return Member::locked($uid, static function () use ($uid, $id): array {
            $return = [];
            $member = self::member($uid);
            $now = time();
            $country = CountryHandler::memberInfo($uid, (int)$member['country']);
            if ($country === null || (int)$country['country'] === 0) {
                $return['status'] = -1;
                return $return;
            }
            // 取谏言信息
            $info = Expostulation::infoById((int)$country['country'], $id);
            if ($info === null) {
                $return['status'] = -2;
                return $return;
            }
            // 取奖励信息
            $temp = Expostulation::temp($info['eid']);
            if ($temp === null) {
                $return['status'] = -3;
                return $return;
            }
            // 是自己的谏言，无需支持
            if ($info['uid'] === $uid) {
                $return['status'] = -5;
                return $return;
            }
            // 判断是否支持过该谏言
            if (Expostulation::support($uid, $id) !== null) {
                $return['status'] = -3;
                return $return;
            }
            // 判断任务类型
            if ($temp['get_type'] === 2 && (int)$member['level'] < 51) {
                $return['status'] = -7;
                return $return;
            }
            if ($temp['type'] === 6 && (int)$member['level'] < 61) {
                $return['status'] = -4;
                return $return;
            }
            // 判断金币（当天已免费支持过则收费）
            $charge = date('ymd', (int)$country['support']) === date('ymd', $now);
            if ($charge && (int)$member['gold'] < $temp['sup_price']) {
                $return['status'] = -6;
                return $return;
            }
            if ($charge) {
                // 旧版付费点 sup_jianyan 不在统计列表里，不统计
                Member::deductCurrency($uid, gold: $temp['sup_price']);
            } else {
                Expostulation::updateSupTime($uid, $now);
            }
            // 添加数据
            if (Expostulation::addSupportLog($uid, $id)) {
                $return['status'] = 1;
                $return['all_gold'] = (int)self::member($uid)['gold'];
                $return['sub_gold'] = $temp['sup_price'];
            }
            return $return;
        });
    }

    /**
     * 刷新接口: c=expostulation&m=refresh（每天首次免费，之后 5 元宝）
     */
    public static function refresh(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        if (self::member($uid) === null) {
            return [];
        }
        return Member::locked($uid, static function () use ($uid): array {
            $return = [];
            $member = self::member($uid);
            $now = time();
            $country = CountryHandler::memberInfo($uid, (int)$member['country']);
            if ($country === null || (int)$country['country'] === 0) {
                $return['status'] = -1;
                return $return;
            }
            // 判断首次刷新是否免费
            $refresh = date('ymd', (int)$country['refresh']) === date('ymd', $now) ? 0 : 1;
            if (!$refresh && (int)$member['gold'] < self::PRICE) {
                $return['status'] = -2;
                return $return;
            }
            // 执行刷新
            $list = Expostulation::listByRand($uid, (int)$member['level'], 4);
            if ($list['status'] !== 1) {
                $return['status'] = -3;
                return $return;
            }
            if (!$refresh) {
                Member::deductCurrency($uid, gold: self::PRICE);
                // 消费记录
                StatPay::record($uid, 'refresh_jianyan', self::PRICE);
            }
            // 修改刷新时间
            Expostulation::updateRefreshDateline($uid, $now);
            $return['status'] = 1;
            $return['price'] = self::PRICE;
            $return['all_gold'] = (int)self::member($uid)['gold'];
            $return['list'] = self::tempItems($list['list'], (int)$member['level']);
            return $return;
        });
    }

    /**
     * 谏言列表项（index / authstr：模版字段（奖励 × 主公等级）+ 说明、谏言 id、发起者、职位名）
     *
     * @param array<string, mixed> $temp
     * @param array<string, mixed> $val
     * @return array<string, mixed>
     */
    private static function listItem(array $temp, array $val, int $level): array
    {
        $item = [];
        foreach (self::TEMP_FIELDS as $field) {
            $item[$field] = in_array($field, ['put_value', 'sup_value'], true) ? $temp[$field] * $level : $temp[$field];
        }
        $item['description'] = Expostulation::description($val['type']);
        $item['id'] = $val['id'];
        $item['uid'] = $val['uid'];
        $item['nickname'] = $val['nickname'];
        $item['gender'] = $val['gender'] === 0 ? 1 : $val['gender'];
        $item['job'] = CountryJob::ROWS[$val['job']]['job'] ?? null;
        return $item;
    }

    /**
     * 可提出的谏言模版（expostulation_list / put_expostulation / refresh：带说明，奖励 × 主公等级）
     *
     * @param array<int, array<string, mixed>> $list
     * @return list<array<string, mixed>>
     */
    private static function tempItems(array $list, int $level): array
    {
        $newlist = [];
        foreach ($list as $val) {
            $val['description'] = Expostulation::description($val['type']);
            $val['put_value'] = $val['put_value'] * $level;
            $val['sup_value'] = $val['sup_value'] * $level;
            $newlist[] = $val;
        }
        return $newlist;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function member(int $uid): ?array
    {
        return $uid > 0 ? DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first() : null;
    }
}
