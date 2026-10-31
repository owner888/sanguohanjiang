<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\General\TavernHandler;
use Hanjiang\MasterData\ActsevenstarActionprice;
use Hanjiang\MasterData\ActsevenstarActionreward;
use Hanjiang\MasterData\ActsevenstarGeneraltemp;
use Hanjiang\MasterData\ActsevenstarRankreward;
use Hanjiang\MasterData\Gem;
use Hanjiang\MasterData\General as GeneralTemplate;
use Hanjiang\MasterData\MountTemp;
use Hanjiang\MasterData\Skill;
use Hanjiang\MasterData\Talent;
use Hanjiang\Model\ActivityClock;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\Equipment;
use Hanjiang\Model\FestLock;
use Hanjiang\Model\FestReward;
use Hanjiang\Model\FreeLeap;
use Hanjiang\Model\General;
use Hanjiang\Model\Member;
use Hanjiang\Model\MemberIntegral;
use Hanjiang\Model\MemberJade;
use Hanjiang\Model\MemberStone;
use Hanjiang\Model\OwnGem;
use Hanjiang\Model\OwnMaterials;
use Hanjiang\Model\OwnMount;
use Hanjiang\Model\PubLog;
use Hanjiang\Model\TraderLog;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 七星灯（移植旧版 controllers/lc/act_sevenstar.php + models/lc/actsevenstar_model.php，计划任务 cron_acttask.php:sevenstar_task）
 *
 * 活动时间与版本取 ActivityTime 的 sevenstar（旧版数据已结束）；活动结束后图标多挂一天，所以点灯截止在 endtime - 1 天。
 * 花紫宝石（MasterData ActsevenstarActionprice 每盏 get3）依次点亮 7 盏灯（每天第一次点一盏免费），或在第一盏之前一次点亮 7 盏；
 * 每盏灯按 ActsevenstarActionreward（lid 为灯号）加权抽一份奖励，点满 7 盏必得 1 个神将魂魄（member_integral.god_soul）并重置；
 * 神将魂魄在 ActsevenstarGeneraltemp 召唤神将。点灯总次数（times ≥ 7）前 20 名领 ActsevenstarRankreward。活动结束后 10 小时内
 * 计划任务把全服剩余的神将魂魄按每个 100 紫宝石兑换、给未领取的前 20 名发排行奖励（均发信件）。
 * 客户端 sevenstarpop.js（d=lc）调用 index、get_rank_list、call_godgeneral（id 武将模板 id）、action_light（id 1 / 7）、get_rank_reward。
 * 当前时间取 BossClock。
 *
 * 照旧版：call_godgeneral 返回的 god_soul 为 null（旧版变量未定义）；排行的 times 为点灯次数 ÷ 7（取整）；index 的武将信息
 * 包在 info.info 里（info 另有 status / own）；点满 7 盏时补的魂魄若追加到奖励列表末尾，列表里那一项显示为 2 个（旧版按引用遍历
 * 时追加的元素又被加了一次，实际只发 1 个）；排行奖励表里没有的名次计划任务照发一封空奖励的信件并标记已领。
 * 与旧版的差别：锁改为 Redis SET NX（10 秒，键名照旧版；旧版析构时无条件删锁，重复请求会删掉前一个请求的锁）；神将魂魄用条件扣减；
 * 排行奖励状态用条件更新；计划任务的魂魄先条件清零再发紫宝石。
 */
final class ActSevenstarHandler
{
    public const string ACTIVITY = 'sevenstar';

    /** 锁（键名 sevenstar_lk_ + 方法名 + uid，10 秒） */
    private const string LOCK_KEY = 'sevenstar_lk_';
    private const int LOCK_TIME = 10;
    /** 排名人数 */
    private const int RANK_LIMIT = 20;
    /** 计划任务：每个魂魄换的紫宝石 */
    private const int SOUL_JADE = 100;

    /** actsevenstar_lang.php + cron_lang.php */
    private const array LANG = [
        'cron_sevenstar_msg' => '亲爱的玩家!七星灯活动已结束,您剩余的%s个神将魂魄,已经自动兑换成%s个请注意查收!',
        'cron_sevenstar_msg2' => '亲爱的玩家!七星灯活动已结束,您未领取的排名奖励,已经成功自动兑换，奖励为%s请注意查收!',
        'sevenstar_common_1' => '活动未开启',
        'sevenstar_common_2' => '操作太快',
        'sevenstar_common_3' => '参数错误',
        'sevenstar_common_4' => '活动已结束',
        'sevenstar_index_1' => '获取神将魂魄信息失败',
        'sevenstar_index_2' => '获取紫宝石信息失败',
        'sevenstar_index_3' => '获取玩家活动信息失败',
        'sevenstar_index_4' => '获取出售神将模板信息失败',
        'sevenstar_index_5' => '获取武将模板信息失败',
        'sevenstar_index_6' => '获取花费信息失败',
        'sevenstar_rank_2' => '获取排行奖励信息失败',
        'sevenstar_call_1' => '获取神将信息失败',
        'sevenstar_call_2' => '神将魂魄不足',
        'sevenstar_call_3' => '已经拥有该武将',
        'sevenstar_call_4' => '扣除资源失败',
        'sevenstar_call_5' => '添加武将失败',
        'sevenstar_action_1' => '获取花费信息失败',
        'sevenstar_action_2' => '紫宝石不足',
        'sevenstar_get_1' => '还没有到领奖的时间',
        'sevenstar_get_2' => '获取玩家活动信息失败',
        'sevenstar_get_3' => '没有排行',
        'sevenstar_get_4' => '已经领取过奖励',
        'sevenstar_get_5' => '获取排行奖励信息失败',
        'sevenstar_get_6' => '领奖失败',
        'message_system_title' => '系统通知',
    ];

    /** 计划任务发奖的信件文字（cron_lang.php） */
    private const array LANG_CRON = [
        1 => '元宝', 2 => '银币', 3 => '黄宝石', 4 => '紫宝石', 5 => '将魂', 6 => '玉石', 7 => '声望', 8 => '装备-', 9 => '宝石碎片',
        10 => '突飞卡', 11 => '蓝宝石', 13 => '军令', 14 => '精炼石', 15 => '五彩石', 16 => '上古血石', 26 => '坐骑-', 27 => '玫瑰花',
        28 => '钻石', 32 => '宝石-', 'cron_change_purple' => '武将兑换紫宝石', 'cron_general' => '武将-', 'cron_good' => '精华',
    ];

    /**
     * 首页: c=act_sevenstar&m=index（d=lc）
     *
     * -1 活动未开启；-2 ~ -7 获取信息失败；1 成功
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $act = self::init($now);
        if ($act === null || $act['open'] !== 1 || $uid <= 0) {
            return ['status' => -1, 'msg' => self::LANG['sevenstar_common_1']];
        }
        // 获取玩家积分信息（神将魂魄）、宝石信息
        $godSoul = (int)MemberIntegral::get($uid)['god_soul'];
        $get3 = TraderLog::jade($uid, 3);
        // 获取个人活动信息
        $info = self::userInfo($uid, $act['version'], $now);
        if ($info === null) {
            return ['status' => -4, 'msg' => self::LANG['sevenstar_index_3']];
        }
        $free = self::addFree($info, $now);
        // 获取出售武将信息
        $list = array_values(ActsevenstarGeneraltemp::ROWS);
        if ($list === []) {
            return ['status' => -5, 'msg' => self::LANG['sevenstar_index_4']];
        }
        foreach ($list as $key => $val) {
            $template = GeneralTemplate::ROWS[$val['gid']] ?? null;
            if ($template === null) {
                return ['status' => -6, 'msg' => self::LANG['sevenstar_index_5']];
            }
            $general = ['status' => 1, 'info' => $template];
            // 查询将领是否已经拥有
            $general['own'] = General::ownsTemplate($uid, $val['gid']) ? 1 : 0;
            $skill = Skill::ROWS[$template['skillid']] ?? null;
            $talent = Talent::ROWS[$template['talentid']] ?? null;
            foreach (['id', 'name', 'description'] as $v) {
                if ($skill !== null) {
                    $general['info']['skill'][$v] = $skill[$v];
                } else {
                    // 照旧版写到了外层
                    $general['skill'][$v] = '';
                }
                $general['info']['talent'][$v] = $talent[$v] ?? '';
            }
            unset($general['info']['talentid'], $general['info']['skillid']);
            $list[$key]['info'] = $general;
            unset($list[$key]['gid']);
        }
        // 获取点灯花费
        $price = array_values(ActsevenstarActionprice::ROWS);
        if ($price === []) {
            return ['status' => -7, 'msg' => self::LANG['sevenstar_index_6']];
        }
        $one = (int)($price[$info['light_site']]['get3'] ?? 0);
        $all = array_sum(array_column($price, 'get3'));
        return [
            'seven' => $info['light_site'] === 0 ? 1 : 0,
            'status' => 1,
            'god_soul' => $godSoul,
            'get3' => $get3,
            'site' => $info['light_site'],
            'list' => $list,
            'one' => $free > 0 ? 0 : $one,
            'all' => $all,
            'date' => $act['open_start'] . '-' . $act['open_end'],
        ];
    }

    /**
     * 获取排行（前二十名）: c=act_sevenstar&m=get_rank_list（d=lc）
     *
     * -1 活动未开启；-3 获取排行奖励信息失败；1 成功。isget：0 没有排行奖励，1 可领取，2 已领取
     */
    public static function get_rank_list(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $act = self::init($now);
        if ($act === null || $act['open'] !== 1) {
            return ['status' => -1, 'msg' => self::LANG['sevenstar_common_1']];
        }
        $rank = self::rankList($act['version']);
        $own = [];
        $myRank = 0;
        $myInfo = [];
        if ($rank !== []) {
            // 查询自己有没有在排行信息里面
            foreach ($rank as $key => $val) {
                $rank[$key]['rank'] = $key + 1;
                if ($val['uid'] === $uid) {
                    $myRank = $key + 1;
                    $myInfo = $val;
                }
                $rank[$key]['times'] = intdiv($val['times'], 7);
                unset($rank[$key]['level'], $rank[$key]['light_site'], $rank[$key]['version'], $rank[$key]['dateline'],
                    $rank[$key]['is_get'], $rank[$key]['get_dateline']);
            }
            // 获取排行奖励信息
            $reward = ActsevenstarRankreward::ROWS;
            if ($reward === []) {
                return ['status' => -3, 'msg' => self::LANG['sevenstar_rank_2']];
            }
            // 组装奖励
            foreach ($rank as $key => $val) {
                $row = $reward[$val['rank']] ?? null;
                if ($row === null) {
                    continue;
                }
                for ($i = 1; $i <= 2; $i++) {
                    $rank[$key]['type' . $i] = $row['type' . $i];
                    if ($row['type' . $i] === 26) {
                        $rank[$key]['value' . $i] = MountTemp::ROWS[$row['value' . $i]] ?? [];
                    } elseif ($row['type' . $i] === 32) {
                        // 21级宝石（前端要求改字段名）
                        $tmpl = Gem::ROWS[$row['value' . $i]] ?? [];
                        $rank[$key]['value' . $i] = [
                            'gem_id' => $tmpl['id'] ?? null,
                            'gem_name' => $tmpl['name'] ?? null,
                            'gem_type' => $tmpl['type'] ?? null,
                            'gem_power' => $tmpl['level21'] ?? null,
                            'gem_quality' => 6,
                            'gem_level' => 21,
                        ];
                    } else {
                        $rank[$key]['value' . $i] = $row['value' . $i];
                    }
                    $rank[$key]['num' . $i] = $row['num' . $i];
                }
                if ($val['uid'] === $uid) {
                    $own = $rank[$key];
                }
            }
        }
        $isget = 0;
        if ($act['cd'] <= 0) {
            if ($myRank > 0 && $myInfo['is_get'] > 0) {
                $isget = 2;
            } elseif ($myRank > 0) {
                $isget = 1;
            }
        }
        return ['status' => 1, 'list' => $rank, 'own_reward' => $own, 'isget' => $isget, 'cd' => $act['cd']];
    }

    /**
     * 召唤神将: c=act_sevenstar&m=call_godgeneral（d=lc，id 武将模板 id）
     *
     * -1 活动未开启；-2 请求太快；-3 参数错误；-4 获取神将信息失败；-5 神将魂魄不足；-6 已经拥有该武将；-7 扣除资源失败；
     * -8 添加武将失败；1 成功
     */
    public static function call_godgeneral(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $lockKey = self::LOCK_KEY . 'call_godgeneral' . $uid;
        $locked = !FestLock::acquire($lockKey, self::LOCK_TIME);
        try {
            $act = self::init($now);
            if ($act === null || $act['open'] !== 1 || $uid <= 0) {
                return ['status' => -1, 'msg' => self::LANG['sevenstar_common_1']];
            }
            if ($locked) {
                // 请求太快，提示未知错误
                return ['status' => -2, 'msg' => self::LANG['sevenstar_common_2']];
            }
            $gid = (int)$ctx->input('id', 0);
            if ($gid === 0) {
                return ['status' => -3, 'msg' => self::LANG['sevenstar_common_3']];
            }
            $info = null;
            foreach (ActsevenstarGeneraltemp::ROWS as $row) {
                if ($row['gid'] === $gid) {
                    $info = $row;
                    break;
                }
            }
            if ($info === null) {
                return ['status' => -4, 'msg' => self::LANG['sevenstar_call_1']];
            }
            if ((int)MemberIntegral::get($uid)['god_soul'] < $info['price']) {
                return ['status' => -5, 'msg' => self::LANG['sevenstar_call_2']];
            }
            if (General::ownsTemplate($uid, $gid)) {
                return ['status' => -6, 'msg' => self::LANG['sevenstar_call_3']];
            }
            // 扣除资源（条件扣减）
            if (MemberIntegral::change($uid, $info['price'], 'god_soul', 2, 'act_sevenstar', 'call_godgeneral', $now) === []) {
                return ['status' => -7, 'msg' => self::LANG['sevenstar_call_4']];
            }
            // 加武将
            $general = General::addGeneral($uid, $gid);
            if ($general === null) {
                return ['status' => -8, 'msg' => self::LANG['sevenstar_call_5']];
            }
            PubLog::add($uid, $gid, 1, 104, $now);
            unset($general['status']);
            return ['status' => 1, 'god_soul' => null, 'info' => $general];
        } finally {
            if (!$locked) {
                FestLock::release($lockKey);
            }
        }
    }

    /**
     * 点灯: c=act_sevenstar&m=action_light（d=lc，id 1 点一盏 / 7 一次点亮七盏）
     *
     * -1 活动已结束；-2 活动未开启 / 请求太快 / 获取神将魂魄失败；-3 获取紫宝石失败；-4 / -5 参数错误；-6 获取花费信息失败；
     * -7 紫宝石不足；1 成功（reset 1 表示点满七盏已重置）
     */
    public static function action_light(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $lockKey = self::LOCK_KEY . 'action_light' . $uid;
        $locked = !FestLock::acquire($lockKey, self::LOCK_TIME);
        try {
            $act = self::init($now);
            // 判断活动是否结束
            if ($act === null || $act['cd'] <= 0) {
                return ['status' => -1, 'msg' => self::LANG['sevenstar_common_4']];
            }
            if ($act['open'] !== 1 || $uid <= 0) {
                return ['status' => -2, 'msg' => self::LANG['sevenstar_common_1']];
            }
            if ($locked) {
                return ['status' => -2, 'msg' => self::LANG['sevenstar_common_2']];
            }
            $num = (int)$ctx->input('id', 0);
            if (!in_array($num, [1, 7], true)) {
                return ['status' => -4, 'msg' => self::LANG['sevenstar_common_3']];
            }
            return Member::locked($uid, static fn (): array => self::doLight($uid, $act['version'], $num, $now));
        } finally {
            if (!$locked) {
                FestLock::release($lockKey);
            }
        }
    }

    /**
     * 点灯（在玩家行锁事务里执行）
     *
     * @return array<string, mixed>
     */
    private static function doLight(int $uid, int $version, int $num, int $now): array
    {
        $info = self::userInfo($uid, $version, $now);
        if ($info === null || ($num === 7 && $info['light_site'] > 0)) {
            return ['status' => -5, 'msg' => self::LANG['sevenstar_common_3']];
        }
        // 当前灯位置
        $site = $info['light_site'];
        $price = array_values(ActsevenstarActionprice::ROWS);
        if ($price === []) {
            return ['status' => -6, 'msg' => self::LANG['sevenstar_action_1']];
        }
        // 获取所需花费的宝石数量
        $type = 0;
        if ($num === 1) {
            $jade = (int)($price[$site]['get3'] ?? 0);
            $site++;
        } else {
            $jade = array_sum(array_column($price, 'get3'));
            // 点亮全部
            $type = 1;
            $site = 7;
        }
        // 判断是否为免费
        if (self::addFree($info, $now) > 0 && $num !== 7) {
            DB::table('actsevenstar')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')->update(['freetime' => $now]);
        } elseif (TraderLog::subJade($uid, 3, $jade, 'act_sevenstar', 'action_light', $now)['status'] !== 1) {
            // 扣宝石失败
            return ['status' => -7, 'msg' => self::LANG['sevenstar_action_2']];
        }
        // 获取奖励信息（全部点亮取 lid <= 7，否则 lid = 当前灯）
        $newreward = [];
        foreach (ActsevenstarActionreward::ROWS as $val) {
            if ($type > 0 ? $val['lid'] <= $site : $val['lid'] === $site) {
                $newreward[$val['lid']][] = $val;
            }
        }
        // 组装奖励
        $addReward = $addReward1 = [];
        foreach ($newreward as $val) {
            $minRate = 999999999;
            $sumRate = 0;
            foreach ($val as $v) {
                $sumRate += $v['rate'] * 100;
                $minRate = min($minRate, $v['rate']);
            }
            $rnum = mt_rand($minRate, $sumRate);
            $cur = 0;
            foreach ($val as $v) {
                $cur += $v['rate'] * 100;
                if ($rnum <= $cur) {
                    if (isset($addReward[$v['type']])) {
                        $addReward[$v['type']]['num'] += $v['num'];
                    } elseif (isset($addReward1[$v['type']][$v['value']])) {
                        $addReward1[$v['type']][$v['value']]['num'] += $v['num'];
                    } elseif ($v['value'] === 0) {
                        $addReward[$v['type']] = $v;
                    } else {
                        $addReward1[$v['type']][$v['value']] = $v;
                    }
                    break;
                }
            }
        }
        $return = [];
        // 普通资源
        foreach ($addReward as $val) {
            self::addReward($uid, $val['type'], $val['num'], 0, $now);
            unset($val['lid'], $val['rate']);
            $return['reward'][] = $val;
        }
        // 装备、锻造材料特殊
        foreach ($addReward1 as $val) {
            foreach ($val as $v) {
                self::addReward($uid, $v['type'], $v['num'], $v['value'], $now);
                unset($v['lid'], $v['rate']);
                $return['reward'][] = $v;
            }
        }
        // 更改用户位置
        $return['reset'] = 0;
        if ($site === 7) {
            $site = 0;
            $return['reset'] = 1;
            // 必定加1个神将魂魄
            self::addReward($uid, 30, 1, 0, $now);
            if (isset($return['reward'])) {
                $found = false;
                foreach ($return['reward'] as $ke => $va) {
                    if ($va['type'] === 30) {
                        $return['reward'][$ke]['num']++;
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    // 照旧版：按引用遍历时追加的这一项又被加 1，显示为 2
                    $return['reward'][] = ['id' => 97, 'type' => 30, 'value' => 0, 'num' => 2];
                }
            }
        }
        DB::table('actsevenstar')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')
            ->update(['light_site' => $site, 'times' => $info['times'] + $num, 'dateline' => $now]);
        $return['seven'] = $site === 0 ? 1 : 0;
        $return['status'] = 1;
        $return['site'] = $site;
        $return['one'] = (int)($price[$site]['get3'] ?? 0);
        $return['all'] = array_sum(array_column($price, 'get3'));
        $return['get3'] = TraderLog::jade($uid, 3);
        $return['god_soul'] = (int)MemberIntegral::get($uid)['god_soul'];
        return $return;
    }

    /**
     * 领取排行奖励: c=act_sevenstar&m=get_rank_reward（d=lc）
     *
     * -1 活动未开启；-2 还没有到领奖的时间 / 请求太快；-4 获取玩家活动信息失败；-5 没有排行；-6 已经领取过奖励；
     * -7 获取排行奖励信息失败；-8 领奖失败；1 成功
     */
    public static function get_rank_reward(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $lockKey = self::LOCK_KEY . 'get_rank_reward' . $uid;
        $locked = !FestLock::acquire($lockKey, self::LOCK_TIME);
        try {
            $act = self::init($now);
            if ($act === null || $act['open'] !== 1 || $uid <= 0) {
                return ['status' => -1, 'msg' => self::LANG['sevenstar_common_1']];
            }
            // 判断是否到领奖的时候了
            if ($act['end'] - $now > 0) {
                return ['status' => -2, 'msg' => self::LANG['sevenstar_get_1']];
            }
            if ($locked) {
                return ['status' => -2, 'msg' => self::LANG['sevenstar_common_2']];
            }
            $memberInfo = self::userInfo($uid, $act['version'], $now);
            if ($memberInfo === null) {
                return ['status' => -4, 'msg' => self::LANG['sevenstar_get_2']];
            }
            $myrank = 0;
            foreach (self::rankList($act['version']) as $i => $val) {
                if ($val['uid'] === $uid) {
                    $myrank = $i + 1;
                }
            }
            if ($myrank === 0) {
                return ['status' => -5, 'msg' => self::LANG['sevenstar_get_3']];
            }
            if ($memberInfo['is_get'] > 0) {
                return ['status' => -6, 'msg' => self::LANG['sevenstar_get_4']];
            }
            $reward = ActsevenstarRankreward::ROWS[$myrank] ?? null;
            if ($reward === null) {
                return ['status' => -7, 'msg' => self::LANG['sevenstar_get_5']];
            }
            // 修改领奖状态（条件更新）
            if (!self::markRankReward($uid, $act['version'], $now)) {
                return ['status' => -8, 'msg' => self::LANG['sevenstar_get_6']];
            }
            // 添加奖励
            for ($i = 1; $i <= 2; $i++) {
                $type = $reward['type' . $i];
                if ($type === 26 || $type === 32) {
                    self::addReward($uid, $type, $reward['num' . $i], $reward['value' . $i], $now);
                    if ($type === 32) {
                        // 21级宝石（前端要求改字段名）
                        $tmpl = Gem::ROWS[$reward['value' . $i]] ?? [];
                        $reward['value' . $i] = [
                            'id' => $tmpl['id'] ?? null,
                            'name' => $tmpl['name'] ?? null,
                            'type' => $tmpl['type'] ?? null,
                            'power' => $tmpl['level21'] ?? null,
                            'quality' => 6,
                            'level' => 21,
                        ];
                    }
                } else {
                    self::addReward($uid, $type, $reward['num' . $i], 0, $now);
                }
            }
            return ['status' => 1, 'reward' => $reward];
        } finally {
            if (!$locked) {
                FestLock::release($lockKey);
            }
        }
    }

    /**
     * 计划任务：七星灯（cron_acttask.php:sevenstar_task）——魂魄自动兑换紫宝石、发排名奖励
     *
     * @return array<string, int>
     */
    public static function cronTask(int $now): array
    {
        return [
            '_send_sevenstar_reward' => self::cronSoulReward($now),
            '_send_sevenstar_rank_reward' => self::cronRankReward($now),
        ];
    }

    /**
     * 魂魄转紫宝石（_send_sevenstar_reward：活动结束后 10 小时内，全服 god_soul > 0 的玩家每个魂魄 100 紫宝石，清零并发信件）
     *
     * @return int -1 无活动；-2 活动未结束；-3 超过 10 小时；1 已执行
     */
    public static function cronSoulReward(int $now): int
    {
        $window = self::cronWindow($now);
        if ($window < 0) {
            return $window;
        }
        foreach (DB::table('member_integral')->select('uid', 'god_soul')->where('god_soul', '>', 0)->whereNull('deleted_at')->get() as $val) {
            $uid = (int)$val['uid'];
            $godSoul = (int)$val['god_soul'];
            // 将魂魄清零（条件清零成功才兑换）
            $cleared = DB::table('member_integral')->where('uid', $uid)->where('god_soul', $godSoul)->whereNull('deleted_at')->update(['god_soul' => 0]);
            if ($cleared <= 0) {
                continue;
            }
            $msg = FestReward::cronGrant($uid, ['type' => 4, 'num' => $godSoul * self::SOUL_JADE], true, 'cron_acttask', 'sevenstar_task', $now);
            MessageHandler::sendSystemMessage([$uid], 1, self::LANG['message_system_title'], sprintf(self::LANG['cron_sevenstar_msg'], $godSoul, $msg));
        }
        return 1;
    }

    /**
     * 发排名奖励（_send_sevenstar_rank_reward：活动结束后 10 小时内，前 20 名中未领取的发奖、发信件并标记已领）
     *
     * @return int -1 无活动；-2 活动未结束；-3 超过 10 小时；1 已执行
     */
    public static function cronRankReward(int $now): int
    {
        $version = self::cronWindow($now);
        if ($version < 0) {
            return $version;
        }
        foreach (self::rankList($version) as $i => $val) {
            if ($val['is_get'] > 0) {
                continue;
            }
            // 修改领取奖励状态（条件更新，成功才发）
            if (!self::markRankReward($val['uid'], $version, $now)) {
                continue;
            }
            $row = ActsevenstarRankreward::ROWS[$i + 1] ?? null;
            $msg = '';
            for ($j = 1; $j <= 2 && $row !== null; $j++) {
                $msg .= self::cronReward($val['uid'], $row['type' . $j], $row['num' . $j], $row['value' . $j], $now);
            }
            MessageHandler::sendSystemMessage([$val['uid']], 1, self::LANG['message_system_title'], sprintf(self::LANG['cron_sevenstar_msg2'], $msg));
        }
        return 1;
    }

    /**
     * 活动信息（_init）：活动行开启时返回版本、开放标识（starttime <= 当前 < endtime）、点灯截止（endtime - 1 天）与倒计时；否则 null
     *
     * @return array{version: int, open: int, end: int, cd: int, open_start: string, open_end: string}|null
     */
    private static function init(int $now): ?array
    {
        $info = ActivityClock::open(self::ACTIVITY, $now);
        if ($info === null) {
            return null;
        }
        $start = (int)$info['starttime'];
        $endtime = (int)$info['endtime'];
        $end = $endtime - 86400;
        return [
            'version' => (int)$info['version'],
            'open' => $now >= $start && $now < $endtime ? 1 : 0,
            'end' => $end,
            'cd' => max(0, $end - $now),
            'open_start' => date('m.d', $start),
            'open_end' => date('m.d', $end),
        ];
    }

    /**
     * 计划任务的发放窗口：活动结束后 10 小时内返回版本，否则状态码
     */
    private static function cronWindow(int $now): int
    {
        $activity = ActivityClock::byName(self::ACTIVITY);
        if ($activity === null) {
            return -1;
        }
        if ((int)$activity['endtime'] > $now) {
            return -2;
        }
        if ((int)$activity['endtime'] + 36000 < $now) {
            return -3;
        }
        return (int)$activity['version'];
    }

    /**
     * 是否今天第一次点灯（_add_free：freetime 不是今天为 1）
     *
     * @param array<string, int|string> $info
     */
    private static function addFree(array $info, int $now): int
    {
        return date('Ymd', (int)$info['freetime']) !== date('Ymd', $now) ? 1 : 0;
    }

    /**
     * 用户活动信息（get_user_info：没有时建档）
     *
     * @return array<string, int|string>|null
     */
    private static function userInfo(int $uid, int $version, int $now): ?array
    {
        $row = self::row($uid, $version);
        if ($row !== null) {
            return $row;
        }
        $member = DB::table('member')->select('nickname', 'level')->where('uid', $uid)->whereNull('deleted_at')->first();
        if ($member === null) {
            return null;
        }
        DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_actsevenstar` (`uid`, `nickname`, `level`, `light_site`, `times`, `freetime`, `version`, `dateline`, `is_get`, `get_dateline`)
             VALUES (:uid, :nickname, :level, 0, 0, 0, :version, 0, 0, 0)',
            ['uid' => $uid, 'nickname' => Member::logText((string)$member['nickname'], 50), 'level' => (int)$member['level'], 'version' => $version]
        );
        return self::row($uid, $version);
    }

    /** actsevenstar 的旧版列 */
    private const array COLUMNS = ['id', 'uid', 'nickname', 'level', 'light_site', 'times', 'freetime', 'version', 'dateline', 'is_get', 'get_dateline'];

    /**
     * @return array<string, int|string>|null
     */
    private static function row(int $uid, int $version): ?array
    {
        $row = DB::table('actsevenstar')->select(...self::COLUMNS)->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')->first();
        return $row === null ? null : self::cast($row);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, int|string>
     */
    private static function cast(array $row): array
    {
        foreach ($row as $key => $value) {
            $row[$key] = $key === 'nickname' ? (string)$value : (int)$value;
        }
        return $row;
    }

    /**
     * 排名前二十（get_rank_list：times >= 7，次数降序、id 升序）
     *
     * @return list<array<string, int|string>>
     */
    private static function rankList(int $version): array
    {
        $rows = DB::table('actsevenstar')->select(...self::COLUMNS)->where('version', $version)->where('times', '>=', 7)->whereNull('deleted_at')
            ->orderBy('times', 'DESC')->orderBy('id', 'ASC')->limit(self::RANK_LIMIT)->get();
        return array_map(self::cast(...), $rows);
    }

    /**
     * 更改排行奖励领取状态（update_rank_reward：is_get 0 → 1）
     */
    private static function markRankReward(int $uid, int $version, int $now): bool
    {
        return DB::table('actsevenstar')->where('uid', $uid)->where('version', $version)->where('is_get', 0)->whereNull('deleted_at')
            ->update(['is_get' => 1, 'get_dateline' => $now]) > 0;
    }

    /**
     * 组织奖励（_add_reward；装备 8 / 坐骑 26 / 材料 29 / 宝石 32 的 $id 为模板 id、$num 为数量）
     */
    private static function addReward(int $uid, int $type, int $num, int $id, int $now): void
    {
        $class = 'act_sevenstar';
        $method = 'action_light';
        switch ($type) {
            case 1: // 元宝
                Member::addCurrency($uid, gold: $num);
                break;
            case 2: // 银币
                Member::addCurrency($uid, silver: $num);
                break;
            case 3: // 黄宝石
                TavernHandler::addJade($uid, 2, $num, $class, $method);
                break;
            case 4: // 紫宝石
                TavernHandler::addJade($uid, 3, $num, $class, $method);
                break;
            case 5: // 原魂
                Member::addCurrency($uid, soul: $num);
                break;
            case 6: // 玉石
                MemberJade::add($uid, $num, $class, $method, $now);
                break;
            case 7: // 声望
                Member::addReputation($uid, $num);
                break;
            case 8: // 装备
                if (FestReward::equipInfo($id) !== null) {
                    Equipment::addFromTemplate($uid, $id, $num);
                }
                break;
            case 9: // 宝石碎片
                OwnGem::addChip($uid, $num, $class, $method, $now);
                break;
            case 10: // 突飞卡
                FreeLeap::add($uid, $num, $now);
                break;
            case 11: // 蓝宝石
                TavernHandler::addJade($uid, 1, $num, $class, $method);
                break;
            case 13: // 军令
                Member::addCurrency($uid, act: $num);
                break;
            case 14: // 精炼石
            case 15: // 五彩石
            case 16: // 上古血石
                MemberStone::add($uid, $num, $type - 13, $class, $method, $now);
                break;
            case 26: // 坐骑
                OwnMount::addMount($uid, $id, 0, $num, $class, $method, $now);
                break;
            case 27: // 玫瑰
                MemberIntegral::change($uid, $num, 'beauty_rose', 1, $class, $method, $now);
                break;
            case 28: // 钻石
                MemberIntegral::change($uid, $num, 'beauty_diamond', 1, $class, $method, $now);
                break;
            case 29: // 装备打造材料（materials_change 的碎片参数旧版传 0）
                $info = OwnMaterials::template($id);
                if ($info !== null) {
                    OwnMaterials::change($uid, $id, $info['quality'], $info['name'], 0, $num, 1, $now);
                }
                break;
            case 30: // 神将魂魄
                MemberIntegral::change($uid, $num, 'god_soul', 1, $class, $method, $now);
                break;
            case 32: // 加宝石（21 级）
                OwnGem::addGem($uid, $id, 21, $num, $now);
                break;
        }
    }

    /**
     * 计划任务发奖并返回信件文字（_add_sevenstar_reward；坐骑 26 / 宝石 32 的 $id 为模板 id）
     */
    private static function cronReward(int $uid, int $type, int $num, int $id, int $now): string
    {
        $l = self::LANG_CRON;
        $class = 'cron_acttask';
        $method = 'sevenstar_task';
        switch ($type) {
            case 1:
            case 2:
            case 5:
            case 7:
            case 13:
                match ($type) {
                    1 => Member::addCurrency($uid, gold: $num),
                    2 => Member::addCurrency($uid, silver: $num),
                    5 => Member::addCurrency($uid, soul: $num),
                    7 => Member::addReputation($uid, $num),
                    13 => Member::addCurrency($uid, act: $id),
                };
                return $l[$type] . $num . ';';
            case 3:
            case 4:
            case 11:
                TavernHandler::addJade($uid, [3 => 2, 4 => 3, 11 => 1][$type], $num, $class, $method);
                return $l[$type] . $num . ';';
            case 6:
                MemberJade::add($uid, $num, $class, $method, $now);
                return $l[6] . $num . ';';
            case 8:
                $item = FestReward::equipInfo($id);
                if ($item !== null) {
                    Equipment::addFromTemplate($uid, $id, $num);
                }
                return $l[8] . ($item['name'] ?? '') . ';';
            case 9:
                OwnGem::addChip($uid, $num, $class, $method, $now);
                return $l[9] . $num . ';';
            case 10:
                FreeLeap::add($uid, $num, $now);
                return $l[10] . $num . ';';
            case 12:
                $result = FestReward::grantGeneral($uid, $id, $class, $method, $now);
                if ($result['owned']) {
                    return $result['jade'] !== null ? $l['cron_change_purple'] . $result['jade'] . ';' : '';
                }
                return $result['general'] !== null ? $l['cron_general'] . $result['general']['name'] . ';' : '';
            case 14:
            case 15:
            case 16:
                MemberStone::add($uid, $num, $type - 13, $class, $method, $now);
                return $l[$type] . $num . ';';
            case 17: case 18: case 19: case 20: case 21: case 22: case 23: case 24: case 25:
                $ess = FestReward::essenceInfo($id);
                if ($ess === null) {
                    return '';
                }
                Equipment::addTowerItem($uid, $id, $num);
                return $ess['name'] . $l['cron_good'] . ';';
            case 26:
                OwnMount::addMount($uid, $id, 0, $num, $class, $method, $now);
                return $l[26] . (MountTemp::ROWS[$id]['name'] ?? '') . $num . '个;';
            case 27:
                MemberIntegral::change($uid, $num, 'beauty_rose', 1, $class, $method, $now);
                return $l[27] . $num . ';';
            case 28:
                MemberIntegral::change($uid, $num, 'beauty_diamond', 1, $class, $method, $now);
                return $l[28] . $num . ';';
            case 32:
                OwnGem::addGem($uid, $id, 21, $num, $now);
                return $l[32] . '21级' . (Gem::ROWS[$id]['name'] ?? '') . $num . '个;';
        }
        return '';
    }
}
