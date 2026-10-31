<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\MasterData\GeneralOnlySale;
use Hanjiang\Model\ActivityClock;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\Chat;
use Hanjiang\Model\FestLock;
use Hanjiang\Model\General;
use Hanjiang\Model\Member;
use Hanjiang\Model\PubLog;
use Hanjiang\Model\TraderLog;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 绝版神将（移植旧版 controllers/general_only.php + models/generalonly_model.php（操作锁）+ tavern_model->get_on_only_sale）
 *
 * 活动时间与类型取 ActivityTime 的 general_only（旧版数据 starttime = endtime = 0，从未开放）。神将取 MasterData GeneralOnlySale
 * （generalid、price1 紫宝石、price2 声望）；购买扣紫宝石与声望、记酒馆日志（获取方式 101）、添加武将，7 星以上发小喇叭。
 * 客户端 outprintgodpop.js 调用 index、buy（generalid）。当前时间取 BossClock。
 *
 * 照旧版：generalid 不合法回 -1（语言包 generalid_error 旧版没有定义，msg 为 null）；酒馆日志在添加武将之前记录。
 * 与旧版的差别：购买锁改为 Redis SET NX；扣紫宝石与声望在玩家行锁里用条件更新，余额不足回 3 / 4 不扣。
 */
final class GeneralOnlyHandler
{
    public const string ACTIVITY = 'general_only';

    /** 购买锁（generalonly_model 的 key 后接 uid） */
    private const string LOCK_KEY = 'general_id';

    /** generalonly_lang.php + general_lang.php */
    private const array LANG = [
        'active_not_open' => '活动未开启！',
        'general_info_error' => '获取武将信息失败！',
        'jade_is_not' => '紫宝石不足！',
        'reputation_is_not' => '声望不足！',
        'general_is_get' => '已经招募该武将！',
        'add_general_error' => '添加将领失败！',
        'general_fast_error' => '购买操作太快了',
        'general_get' => '恭喜玩家【%s】获得%s星武将-%s',
    ];

    /**
     * 初始化接口: c=general_only&m=index
     *
     * -2 获取信息失败；-1 活动未开启；0 失败；1 成功。isget：2 已拥有，1 宝石与声望够，0 不够
     */
    public static function index(Context $ctx): array
    {
        $return = ['status' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $act = ActivityClock::open(self::ACTIVITY, $now);
        if ($act === null || $uid <= 0) {
            $return['status'] = -1;
            $return['msg'] = self::LANG['active_not_open'];
            return $return;
        }
        $list = array_values(GeneralOnlySale::ROWS);
        if ($list === []) {
            $return['status'] = -2;
            $return['msg'] = self::LANG['general_info_error'];
            return $return;
        }
        $price1 = TraderLog::jade($uid, 3);
        $reputation = self::reputation($uid);
        $gidArr = [];
        foreach ($list as $key => $value) {
            if (General::ownsTemplate($uid, $value['generalid'])) {
                $list[$key]['isget'] = 2;
            } else {
                $list[$key]['isget'] = $price1 >= $value['price1'] && $reputation >= $value['price2'] ? 1 : 0;
            }
            $gidArr[] = $value['generalid'];
        }
        // [将领模型]获得将领模板信息
        $generalArr = GeneralOnsaleHandler::generalInfo($gidArr);
        foreach ($list as $key => $value) {
            if (isset($generalArr[$value['generalid']])) {
                $list[$key]['info'] = $generalArr[$value['generalid']];
            }
        }
        $return['status'] = 1;
        $return['reputation'] = $reputation;
        $return['jade'] = $price1;
        $return['list'] = $list;
        $return['type'] = (int)$act['type'];
        $return['cd'] = (int)$act['endtime'] - $now;
        return $return;
    }

    /**
     * 购买: c=general_only&m=buy（generalid 将领模板 id）
     *
     * -5 操作太快了；-4 参数错误；-3 添加将领失败；-2 奖励信息失败；-1 活动未开启 / 参数错误；0 失败；1 成功；2 将领已招募；
     * 3 紫宝石不足；4 声望不足
     */
    public static function buy(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $lockKey = self::LOCK_KEY . $uid;
        if ($uid <= 0 || !FestLock::acquire($lockKey)) {
            return ['status' => -5, 'msg' => self::LANG['general_fast_error']];
        }
        try {
            $act = ActivityClock::open(self::ACTIVITY, $now);
            if ($act === null) {
                return ['status' => -1, 'msg' => self::LANG['active_not_open']];
            }
            $generalid = (int)$ctx->input('generalid', 0);
            if ($generalid <= 0) {
                // 参数错误（旧版语言包没有 generalid_error）
                return ['status' => -1, 'msg' => null];
            }
            // 获取奖励信息
            $list = null;
            foreach (GeneralOnlySale::ROWS as $row) {
                if ($row['generalid'] === $generalid) {
                    $list = $row;
                    break;
                }
            }
            if ($list === null) {
                return ['status' => -2, 'msg' => self::LANG['general_info_error']];
            }
            return Member::locked($uid, static function () use ($uid, $generalid, $list, $act, $now): array {
                // 判断是否已经招募该武将
                if (General::ownsTemplate($uid, $generalid)) {
                    return ['status' => 2, 'msg' => self::LANG['general_is_get']];
                }
                // 紫宝石不足 / 声望不足
                if (TraderLog::jade($uid, 3) < $list['price1']) {
                    return ['status' => 3, 'msg' => self::LANG['jade_is_not']];
                }
                if (self::reputation($uid) < $list['price2']) {
                    return ['status' => 4, 'msg' => self::LANG['reputation_is_not']];
                }
                // 减紫宝石、声望
                if ($list['price1'] > 0 && TraderLog::subJade($uid, 3, $list['price1'], 'general_only', 'buy', $now)['status'] !== 1) {
                    return ['status' => 3, 'msg' => self::LANG['jade_is_not']];
                }
                if ($list['price2'] > 0 && Member::subReputation($uid, $list['price2']) === null) {
                    // 行锁内已确认声望足够，走不到这里
                    return ['status' => 4, 'msg' => self::LANG['reputation_is_not']];
                }
                // [将领模型]添加将领(generalid)；酒馆日志在前（照旧版）
                PubLog::add($uid, $generalid, 1, 101, $now);
                $info = General::addGeneral($uid, $generalid);
                if ($info === null) {
                    return ['status' => -3, 'msg' => self::LANG['add_general_error']];
                }
                unset($info['status']);
                $star = Chat::starWord((int)$info['star']);
                if ($star !== null) {
                    $sender = Chat::sender($uid);
                    Chat::sendToHorn(sprintf(self::LANG['general_get'], (string)($sender['nickname'] ?? ''), $star, $info['name']), $sender);
                }
                Redis::del('player:' . $uid);
                return [
                    'status' => 1,
                    'general' => $info,
                    'jade' => TraderLog::jade($uid, 3),
                    'type' => (int)$act['type'],
                    'reputation' => self::reputation($uid),
                ];
            });
        } finally {
            FestLock::release($lockKey);
        }
    }

    private static function reputation(int $uid): int
    {
        return (int)(DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('reputation') ?? 0);
    }
}
