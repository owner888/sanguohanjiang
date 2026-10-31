<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Pvp;

use Hanjiang\Battle\BattleRush;
use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\MasterData\CountryCityEffect;
use Hanjiang\MasterData\CountryJob;
use Hanjiang\MasterData\CountryMission;
use Hanjiang\MasterData\Mission;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\Chat;
use Hanjiang\Model\Member;
use Hanjiang\Model\MemberJade;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 国战 / 都城（移植旧版 controllers/siege.php + models/siege_model.php + models/countryfight_model.php 的国战日志，
 * 以及 cron.php:siege_battle，路径相对旧版 s1/application/；clear_warstatus 在 SiegeCron）
 *
 * 国家 = 玩家军团（member.country = zc_sanguo_country.id）。13 座都城（MasterData CountryCityEffect：开战时间 time、
 * 占领效果 type / value、NPC 势力 team）按服务器主线最远进度开放；每座城的 NPC 守将为 MasterData CountryMission
 * （city 对应都城，怪物为 country_monster）。占领状态存 zc_sanguo_country_city（占领国 cid、挑战国 challengercid、
 * 至尊攻城锁 lock），国家表的 city / city_status 记录本国请战 / 占领的城（1 已占领，2 已请战，3 有挑战国家）。
 *
 * 流程：君主 / 前三官职周一 ~ 周五 18:00 前「占领」（请战）一座城；周六各城在 time 前 1 小时开放报名（join_battle，
 * 开战前 3 分钟截止），国战计划任务（SiegeCron，每分钟）在开战前 3 分钟用团战（BattleRush）算出整场战斗，
 * 战报存 zc_sanguo_country_city_report 与 Redis；玩家进入战场（action）播放战报，之后领取奖励（get_reward，
 * 按国家等级俸禄，占领 / 守城成功 3 倍）。占领国全体成员享受都城效果（getCityEffect：1 征收次数、2 通商次数、
 * 3 玉石作坊、4 将魂星路、5 四象圣域、6 铜雀台、7 通天塔）。
 *
 * 客户端调用（countrymapworld.js / countrybattleworld.js / countrywarnoticepop.js / miscpop.js）：index、capital_info、
 * occupy、battle_prepare、join_battle、action、get_reward、get_report、siege_notice。get_report_info 客户端不调用，不开放。
 * 主城国战提示（member index 的 country_war 等字段）由 countryWar() 提供。
 *
 * 聊天室：至尊攻城小喇叭、国战胜负全服系统公告、双方国家聊天室战报（get_message_str 18 ~ 21，type 6 系统）。
 *
 * 依赖未移植：
 * - 通商免费次数 member_activity.freebusiness（都城效果 type 2）；
 * - 元宝流水。
 */
final class SiegeHandler
{
    /** 至尊攻城价格 */
    private const int PRICE = 1000;

    /** 国战开始前星期几，不能占领 */
    private const int END_DAY = 5;
    /** 国战周几开启 */
    private const int START_DAY = 6;
    /** 国战开始时间（时） */
    private const int START_HOURS = 18;
    /** 国战结束时间（时） */
    private const int END_HOURS = 21;
    /** 国战开始前几秒停止报名 */
    private const int MINUTE = 180;

    /** 至尊攻城所需 vip 等级 */
    private const int FORCE_VIP = 7;

    /** 国战保存战斗列表的 key（siege_{城池}_attack / _defense / _battle） */
    private const string KEYS = 'siege_';
    /** 杀敌榜 key（siege_enemy_{国家}） */
    private const string ENEMY = 'siege_enemy_';
    /** 国战战斗锁（siege_lock_{城池}，值为加锁时间 + 40 秒） */
    private const string LOCK = 'siege_lock_';
    /** 占领（请战）互斥锁：同一时间只处理一个占领请求（旧版无锁，多 Worker 并发会互相覆盖城池状态） */
    private const string OCCUPY_LOCK = 'siege_occupy_lock';
    /** 报名列表互斥锁（siege_join_lock_{城池}：报名列表是整串 JSON，读改写需互斥） */
    private const string JOIN_LOCK = 'siege_join_lock_';

    /** 国家信件类型（旧版 send_message type 7） */
    private const int MESSAGE_TYPE_COUNTRY = 7;

    /** 平民官职（报名时以 uid 为键，避免被顶掉） */
    private const int JOB_COMMONER = 51;

    /** 旧版 zc_sanguo_country_city 的字段 */
    private const array CITY_COLUMNS = ['maplevel', 'challengercid', 'challengername', 'challengerlevel', 'challengerdateline', 'cid', 'name', 'level', 'dateline', 'lock'];

    /** 旧版 zc_sanguo_country_siege_log 的字段 */
    private const array SIEGE_LOG_COLUMNS = ['id', 'city', 'city_name', 'report', 'country', 'country_name', 'attack_country', 'attack_country_name', 'zhizun', 'win_type', 'dateline'];

    private const string LANG_SIEGE_LEVY = '全国成员免费征收次数';
    private const string LANG_SIEGE_BUSINESS = '全国成员免费通商次数';
    private const string LANG_SIEGE_WORKSHOP = '全国成员玉石采集产量';
    private const string LANG_SIEGE_SOUL = '全国成员原魂每日领取%s';
    private const string LANG_SIEGE_GEM = '全国宝石碎片每日领取%s';
    private const string LANG_SIEGE_BEAUTY = '全国铜雀台每日互动次数+%s';
    private const string LANG_SIEGE_TOWER = '全国通天塔每日领取%s';
    private const string LANG_SIEGE_DONT = '无';
    private const string LANG_SIEGE_OCCUPY_LOSE = '【%s】Lv%s对%s发起了攻占。国家等级低于当前挑战方【%s】，发起攻占失败。请提升你的国家等级！';
    private const string LANG_SIEGE_OCCUPY_CHANGE = '【%s】Lv%s对%s发起了攻占。国家等级超过【%s】,获得攻城资格！';
    private const string LANG_SIEGE_OCCUPY_CH_MSG = '【%s】Lv%s对%s发起了攻占。国家等级更高，自动变为进攻方，取消【%s】Lv%s的攻城资格。';
    private const string LANG_SIEGE_OCCUPY_WIN = '【%s】成功对%s发起了攻占,请各位做好作战准备，请在周六%s准备应战！';
    private const string LANG_SIEGE_ARMY_GROUP = '军团';
    private const string LANG_SIEGE_CHANGE_OWN_CITY = '您当前已经拥有城池【%s】，确认攻打【%s】放弃【%s】吗？（一个国家只能攻打或拥有一座城池）';
    private const string LANG_SIEGE_CHANGE_OCCPY_CITY = '您当前已经申请打城池【%s】，确认攻打【%s】放弃【%s】吗？（一个国家只能攻打或拥有一座城池）';
    private const string LANG_SIEGE_START_TIME = '国战报名将于周六%s开启,请准时参加';
    private const string LANG_SIEGE_ONT_OCCUPY = '%s 已被【%s】发起至尊攻城，普通攻占无法取代占领资格！';
    /** 国战胜负全服公告（siege_lang.php：siege_battle_win / siege_battle_lose） */
    private const string LANG_SIEGE_BATTLE_WIN = '【%s】击败了【%s】 ，成功占领【%s】。';
    private const string LANG_SIEGE_BATTLE_LOSE = '【%s】击败了前来攻城的【%s】，%s防守成功。';
    private const string LANG_SIEGE_FORCE_ATTACK_WIN = '【%s】君主【%s】对【%s】发起至尊攻城成功，国家【%s】无法弃城，双方将决一死战！';
    private const string LANG_SIEGE_FORCE_ATTACK_CHALLENGER_WIN = '【%s】君主【%s】对【%s】发起至尊挑战，战胜另一挑战者【%s】的君主【%s】，获得挑战资格。';
    private const string LANG_SIEGE_FORCE_OCCUPY_NEED_VIP = 'vip等级达到7级才可使用至尊攻城！';
    private const string LANG_BASE_SATURDAY = '周六';
    private const string LANG_BASE_HOUR = '时';
    private const string LANG_BASE_MINUTE = '分';

    /**
     * 初始化接口（都城列表）: c=siege&m=index
     *
     * status：-1 没有加入国家；3 叛国 / 退国 7 天内（cd 剩余秒数）；1 成功
     */
    public static function index(Context $ctx): array
    {
        $now = BossClock::now();
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        // 判断有没有进入国家
        if ($member === null || (int)$member['country'] === 0) {
            $return['status'] = -1;
            return $return;
        }
        $myCountryId = (int)$member['country'];

        // 叛国、退国后7天内无法进入
        $myCountry = CountryHandler::memberCountry((int)$member['uid']);
        if ($myCountry !== null && ((int)$myCountry['leavedateline'] + 7 * 86400) - $now > 0) {
            $return['status'] = 3;
            $return['cd'] = (int)$myCountry['leavedateline'] + 7 * 86400 - $now;
            return $return;
        }

        // 获取都城列表
        $list = self::capitalList();
        // 按城池id查询城池的占领者
        $info = self::castellansByIds(array_column($list, 'id'));
        foreach ($list as $key => $val) {
            if (isset($list[$key + 1])) {
                $list[$key]['size'] = $list[$key + 1]['type'] !== $val['type'] ? 1 : 0;
            } else {
                $list[$key]['size'] = 1;
            }
            $city = $info[$val['id']] ?? null;
            if ($city !== null && $city['cid'] > 0) {
                $list[$key]['occupy'] = 1;
                $list[$key]['occupy_name'] = mb_substr($city['name'], 0, 1);
            } elseif ($city !== null && $city['challengercid'] === $myCountryId) {
                $list[$key]['occupy'] = 2;
                $list[$key]['occupy_name'] = '';
            } else {
                $list[$key]['occupy'] = 0;
                $list[$key]['occupy_name'] = '';
            }
        }
        $return['status'] = 1;
        $return['list'] = $list;
        return $return;
    }

    /**
     * 获取单个城的信息: c=siege&m=capital_info（id 都城 id）
     *
     * status：-1 没有加入国家；-2 都城不存在；1 可占领；2 本国已请战该城 / 国战进行中（按钮变灰）
     */
    public static function capital_info(Context $ctx): array
    {
        $now = BossClock::now();
        $id = (int)$ctx->input('id', 0);
        $return = [];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null || (int)$member['country'] === 0) {
            $return['status'] = -1;
            return $return;
        }
        $myCountryId = (int)$member['country'];
        // 查询这个都城的相关信息
        $info = self::capital($id);
        if ($info === null) {
            $return['status'] = -2;
            return $return;
        }
        // 判断该都城的开启状态
        $info['open'] = $info['id'] <= self::maxMap() ? 1 : 0;

        $info['str'] = self::effectStr($info['type'], $info['value']);
        unset($info['type'], $info['value']);

        $info['castellanid'] = 0;
        $info['castellanname'] = $info['team'];

        // 查询该都城等级最大的关卡守将
        $mission = self::maxMissionByCity($id);
        $info['generalname'] = $mission !== null ? $mission['name'] : self::LANG_SIEGE_DONT;

        // 查询该城的占领者和挑战者
        $cityInfo = self::castellan($id);
        if ($cityInfo !== null && $cityInfo['cid'] > 0) {
            $info['castellanid'] = $cityInfo['cid'];
            $info['castellanname'] = $cityInfo['name'];
        }
        if ($cityInfo !== null && $cityInfo['challengercid'] > 0) {
            $info['challengercid'] = $cityInfo['challengercid'];
            $info['challengername'] = $cityInfo['challengername'];
        } else {
            // 没有挑战者
            $info['challengercid'] = 0;
            $info['challengername'] = $info['team'];
        }

        $return['status'] = 1;

        // 国家有没有已经请战，有则按钮变灰
        $myCity = self::castellanByCountry($myCountryId);
        if ($myCity !== null && $myCity['maplevel'] === $id) {
            $return['status'] = 2;
        }

        // 查询自己国家已参与的国战城池信息（君主可对有占领者 / 挑战者的城发起至尊攻城）
        $info['status'] = 0;
        if ($myCity === null || ($myCity['lock'] !== 1 && $myCity['maplevel'] !== $id)) {
            $myCountry = CountryHandler::memberCountry((int)$member['uid']);
            if ($cityInfo !== null && (int)($myCountry['job'] ?? 0) === 1 && ($cityInfo['cid'] > 0 || $cityInfo['challengercid'] > 0)) {
                $info['status'] = 1;
            }
        }

        // 至尊攻城状态
        $info['force'] = $cityInfo !== null && $cityInfo['lock'] === 1 ? 1 : 0;

        // 国战时间内，按钮变灰
        if (self::isWarTime($now)) {
            $return['status'] = 2;
        }
        // 周六，时，分
        $time = (string)$info['time'];
        $info['time'] = self::LANG_BASE_SATURDAY . substr($time, 0, 2) . ':' . substr($time, 2, 2) . self::LANG_BASE_MINUTE;

        $return['info'] = $info;
        $return['price'] = self::PRICE;
        return $return;
    }

    /**
     * 占领（请战）: c=siege&m=occupy（id 都城 id；sure 第一次请求返回 3 时传 1 确认放弃原城；force 1 至尊攻城）
     *
     * status：-1 没有加入国家；-2 官职不够；-3 国战前 24 小时到国战结束不能占领；-9 国战期间；-4 本国已请战 / 占领该城；
     * -12 本国城池被至尊攻城锁定无法放弃；-10 本国请战的城已锁定；-5 都城不存在；-6 都城未开放；-11 至尊攻城需君主；
     * -8 msg 提示（vip 不足 / 已被至尊攻城 / 国家等级不够）；-13 元宝不足；-7 本国已是挑战者；3 msg 确认放弃原城；
     * 0 操作繁忙；1 成功（至尊攻城带 info.win 与 battle 战报，战败时只返回战报）
     */
    public static function occupy(Context $ctx): array
    {
        $member = self::member(MemberHandler::resolveUid($ctx));
        $redis = Redis::instance();
        if ($redis === null || !$redis->set(self::OCCUPY_LOCK, '1', ['nx', 'ex' => 30])) {
            return ['status' => 0];
        }
        try {
            return self::doOccupy(
                $member,
                (int)$ctx->input('id', 0),
                (int)$ctx->input('sure', 0),
                (int)$ctx->input('force', 0),
                BossClock::now()
            );
        } finally {
            Redis::del(self::OCCUPY_LOCK);
        }
    }

    /**
     * 进入国战等待页面: c=siege&m=battle_prepare
     *
     * status：-1 没有加入国家；3 叛国 / 退国 7 天内；2 直接进入战斗（上次国战结果 / 已开战）；-3 国家没有国战；
     * -4 城池信息错误；-2 今天不是国战日；-5 msg 报名尚未开始；1 成功（双方报名列表、开战倒计时 dateline）
     */
    public static function battle_prepare(Context $ctx): array
    {
        $now = BossClock::now();
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        $country = $member !== null ? CountryHandler::memberInfo((int)$member['uid'], (int)$member['country']) : null;
        if ($country === null || (int)$country['country'] === 0) {
            $return['status'] = -1;
            return $return;
        }
        $myCountryId = (int)$member['country'];

        // 叛国、退国后7天内无法进入
        if ((int)$country['leavedateline'] + 7 * 86400 - $now > 0) {
            $return['status'] = 3;
            $return['cd'] = (int)$country['leavedateline'] + 7 * 86400 - $now;
            return $return;
        }

        // 有占领城或者有挑战城
        $info = self::castellanByCountry((int)$country['country']);
        // 如果参加过战斗，但是查询出战斗已经进行过了，就不在等待界面显示
        if ($info === null && (int)$country['warstatus'] === 1) {
            $return['status'] = 2;
            return $return;
        }
        // 国家没有战斗
        if ($info === null) {
            $return['status'] = -3;
            return $return;
        }
        // 国家有新的国战的时候，判断自己上次战斗是否结束（并且不在开启国战时间的时候，直接进入上次国战）
        $week = (int)date('w', $now);
        $hour = (int)date('H', $now);
        if ((int)$country['warstatus'] === 1
            && !($week === self::START_DAY && $hour >= self::START_HOURS - 1 && $hour <= self::END_HOURS)) {
            $return['status'] = 2;
            return $return;
        }

        // 查询战斗什么时候开始
        $effect = self::capital($info['maplevel']);
        if ($effect === null) {
            $return['status'] = -4;
            return $return;
        }
        // 判断现在是否开启了国战
        if ($week !== self::START_DAY) {
            $return['status'] = -2;
            return $return;
        }
        // 取国战开始前1个小时的时间
        $time = self::battleTime($effect, $now);
        $startTime = $time - 3600;
        if ($now < $startTime) {
            $return['status'] = -5;
            $return['msg'] = sprintf(self::LANG_SIEGE_START_TIME, date('H:i', $startTime));
            return $return;
        }

        // 城池进攻和防守方队伍列表的key前缀
        $keys = self::KEYS . $info['maplevel'];

        // 攻击方列表
        if ($info['challengercid'] === 0) {
            $isNpc = 1;
            $attackArr = self::npcArmy($info['maplevel']);
            $newAttackInfo = self::npcList($info['maplevel']);
        } else {
            $isNpc = 0;
            $attackArr = CountryHandler::countryInfo($info['challengercid']);
            $newAttackInfo = self::sortedList(Redis::get($keys . '_attack'));
        }
        // 防守方列表
        if ($info['cid'] === 0) {
            $isNpc = 1;
            $defenseArr = self::npcArmy($info['maplevel']);
            $newDefenseInfo = self::npcList($info['maplevel']);
        } else {
            $isNpc = 0;
            $defenseArr = CountryHandler::countryInfo($info['cid']);
            $newDefenseInfo = self::sortedList(Redis::get($keys . '_defense'));
        }

        // 得到自己有没有在战斗列表里面
        $list = [];
        if ($info['challengercid'] === $myCountryId) {
            $return['type'] = 1;
            $list = $newAttackInfo;
        } elseif ($info['cid'] === $myCountryId) {
            $return['type'] = 2;
            $list = $newDefenseInfo;
        }
        $return['in_battle'] = 0;
        foreach ($list as $val) {
            if ((int)$val['uid'] === (int)$member['uid']) {
                $return['in_battle'] = 1;
            }
        }

        $return['status'] = 1;
        $return['city_name'] = $effect['name'];
        $return['attack'] = $newAttackInfo;
        $return['attack_name'] = $attackArr['name'] ?? null;
        $return['attack_level'] = isset($attackArr['level']) ? (int)$attackArr['level'] : null;
        $return['attack_num'] = count($newAttackInfo);
        $return['defense'] = $newDefenseInfo;
        $return['defense_name'] = $defenseArr['name'] ?? null;
        $return['defense_level'] = isset($defenseArr['level']) ? (int)$defenseArr['level'] : null;
        $return['defense_num'] = count($newDefenseInfo);
        // 开战倒计时
        $return['dateline'] = $time - $now < 0 ? 0 : $time - $now + 10;
        if ($return['dateline'] === 0) {
            $return['status'] = 2;
        }
        // 防守方是否是NPC的标识
        $return['npc'] = $isNpc;
        return $return;
    }

    /**
     * 加入攻击队伍（报名）: c=siege&m=join_battle
     *
     * status：-1 没有加入国家；3 叛国 / 退国 7 天内；-2 今天不是国战日；-3 国家没有国战；-4 城池信息错误；
     * -5 报名已截止（开战前 3 分钟）；-6 已经报名；0 操作繁忙；1 成功（type 1 防守方、2 攻击方，双方报名列表）
     */
    public static function join_battle(Context $ctx): array
    {
        $now = BossClock::now();
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        $country = $member !== null ? CountryHandler::memberInfo((int)$member['uid'], (int)$member['country']) : null;
        if ($country === null || (int)$country['country'] === 0) {
            $return['status'] = -1;
            return $return;
        }
        $uid = (int)$member['uid'];
        $myCountryId = (int)$member['country'];
        // 叛国、退国后7天内无法进入
        if ((int)$country['leavedateline'] > $now - 86400 * 7) {
            $return['status'] = 3;
            return $return;
        }
        // 判断现在是否开启了国战
        if ((int)date('w', $now) !== self::START_DAY) {
            $return['status'] = -2;
            return $return;
        }
        $info = self::castellanByCountry((int)$country['country']);
        if ($info === null) {
            $return['status'] = -3;
            return $return;
        }
        // 判断是否报名结束
        $effect = self::capital($info['maplevel']);
        if ($effect === null) {
            $return['status'] = -4;
            return $return;
        }
        // 判断报名截至时间（开战前3分钟停止报名）
        if ((int)date('Hi', $now + self::MINUTE) > $effect['time']) {
            $return['status'] = -5;
            return $return;
        }

        $attackKeys = self::KEYS . $info['maplevel'] . '_attack';
        $defenseKeys = self::KEYS . $info['maplevel'] . '_defense';
        // 判断我是攻击方还是防守方(取列表)
        if ($info['cid'] === $myCountryId) {
            // 防守方
            $return['type'] = 1;
            $keys = $defenseKeys;
        } else {
            // 攻击方
            $return['type'] = 2;
            $keys = $attackKeys;
        }

        // 报名列表是整串 JSON，读改写期间互斥
        if (!self::waitLock(self::JOIN_LOCK . $info['maplevel'], 10, 5)) {
            return $return;
        }
        try {
            $attackList = Redis::get($attackKeys);
            $defenseList = Redis::get($defenseKeys);
            $attackList = is_array($attackList) ? $attackList : [];
            $defenseList = is_array($defenseList) ? $defenseList : [];
            $list = $return['type'] === 1 ? $defenseList : $attackList;
            foreach ($list as $val) {
                if ((int)$val['uid'] === $uid) {
                    $return['status'] = -6;
                    return $return;
                }
            }
            // 插入数据（如果官职是平民，键用uid代替，避免平民玩家报名后被顶掉）
            $teamKey = (int)$country['job'] === self::JOB_COMMONER ? $uid : (int)$country['job'];
            $list[$teamKey] = [
                'uid' => $uid,
                'nickname' => (string)$member['nickname'],
                'level' => (int)$member['level'],
                'job' => (int)$country['job'],
            ];
            Redis::set($keys, $list, 86400);
        } finally {
            Redis::del(self::JOIN_LOCK . $info['maplevel']);
        }
        if ($return['type'] === 1) {
            $defenseList = $list;
        } else {
            $attackList = $list;
        }
        $newDefenseList = self::sortedList($defenseList);
        $newAttackList = self::sortedList($attackList);
        // 修改国战参加状态
        self::updateWarStatus($uid, $info['maplevel'], 1);
        $return['status'] = 1;
        $return['attack'] = $newAttackList;
        $return['defense'] = $newDefenseList;
        $return['attack_num'] = count($newAttackList);
        $return['defense_num'] = count($newDefenseList);
        $return['attack_level'] = $info['challengerlevel'];
        $return['defense_level'] = $info['level'];
        return $return;
    }

    /**
     * 进入战斗（播放国战战报）: c=siege&m=action
     *
     * 国战期间没有战报时（计划任务还没算）由本次请求按旧版算出整场战斗；其余时间读本城最近一次战报（6 天内）。
     * status：-1 没有加入国家；-3 没有参加国战 / 国家没有国战；-4 城池信息错误；-5 还没开战；-2 没有战报；
     * 1 成功（info.win 1 本国胜 2 本国负，battle 团战战报）
     */
    public static function action(Context $ctx): array
    {
        $now = BossClock::now();
        $return = [];
        $member = self::member(MemberHandler::resolveUid($ctx));
        $country = $member !== null ? CountryHandler::memberInfo((int)$member['uid'], (int)$member['country']) : null;
        if ($country === null || (int)$country['country'] === 0) {
            $return['status'] = -1;
            return $return;
        }
        $myCountryId = (int)$member['country'];
        // 判断自己有没有参加国战
        if ((int)$country['warstatus'] !== 1) {
            $return['status'] = -3;
            return $return;
        }
        $city = (int)$country['city'];
        // 取城池信息
        $effect = self::capital($city);
        if ($effect === null) {
            $return['status'] = -4;
            return $return;
        }
        // 取自己的国家信息
        $myCountry = CountryHandler::countryInfo($myCountryId);
        if ((int)date('w', $now) === self::START_DAY && (int)($myCountry['city_status'] ?? 0) > 0) {
            // 判断战斗是否能开启
            if ((int)date('Hi', $now) < $effect['time']) {
                $return['status'] = -5;
                return $return;
            }
        }

        $result = self::cityBattle($city, $effect, (int)$country['country'], $now, (int)$member['uid']);
        if (isset($result['status'])) {
            return $result;
        }
        $data = $result['data'];
        $battle = $data['battle'];
        $reportId = $result['report_id'];

        // 判断我是攻击方还是防守方
        if ((int)$data['cid'] === $myCountryId) {
            // 防守方胜利 1，失败 2
            $return['info']['win'] = (int)$battle['win'] === 2 ? 1 : 2;
        } elseif ((int)$data['challengercid'] === $myCountryId) {
            // 攻击方胜利 1，失败 2
            $return['info']['win'] = (int)$battle['win'] === 1 ? 1 : 2;
        } else {
            $return['info']['win'] = 2;
        }

        $attack = array_values($data['attack']);
        $defense = array_values($data['defense']);
        $return['status'] = 1;
        $return['battle'] = $battle;
        $return['attack'] = $attack;
        $return['defense'] = $defense;
        $return['city_name'] = $effect['name'];
        $return['attack_num'] = count($data['attack']);
        $return['attack_name'] = $data['attack_name'];
        $return['attack_level'] = $data['attack_level'];
        $return['defense_num'] = count($data['defense']);
        $return['defense_name'] = $data['defense_name'];
        $return['defense_level'] = $data['defense_level'];
        $return['npc'] = $data['npc'];
        if ($reportId !== null) {
            $return['share'] = [
                'id' => $reportId,
                'type' => 'siege',
            ];
        }
        return $return;
    }

    /**
     * 领取国战奖励: c=siege&m=get_reward（type 1 领取，否则查看奖励与本国杀敌榜前 5）
     *
     * status：-1 没有加入国家；-2 没有可领取的奖励；1 成功（rule 奖励：按国家等级俸禄，占领 / 守城成功 3 倍）
     */
    public static function get_reward(Context $ctx): array
    {
        $type = (int)$ctx->input('type', 0);
        $return = [];
        $member = self::member(MemberHandler::resolveUid($ctx));
        $country = $member !== null ? CountryHandler::memberInfo((int)$member['uid'], (int)$member['country']) : null;
        if ($country === null || (int)$country['country'] === 0) {
            $return['status'] = -1;
            return $return;
        }
        $uid = (int)$member['uid'];
        $level = (int)$member['level'];
        // 判断奖励是否已被领取
        if ((int)$country['warstatus'] === 0) {
            $return['status'] = -2;
            return $return;
        }
        // 判断是胜利的奖励还是失败的奖励（查询国家信息，通过占领的国家判断）
        $myCountry = CountryHandler::countryInfo((int)$member['country']);
        $rule = CountryHandler::levelupRule((int)($myCountry['level'] ?? 0)) ?? [];
        // 根据我的官职 取官职信息，俸禄要计算官职系数
        $ratio = (float)(CountryJob::ROWS[(int)$country['job']]['ratio'] ?? 0.0);

        // 一天俸禄
        $rule['silver'] = (int)round($level * (int)($rule['silver'] ?? 0) * $ratio);
        $rule['jade'] = (int)round($level * (int)($rule['jade'] ?? 0) * $ratio);
        $rule['act'] = (int)round((int)($rule['act'] ?? 0) * $ratio);
        $rule['soul'] = (int)round((int)($rule['soul'] ?? 0) * $ratio);
        unset($rule['exp']);
        if ($level < 91) {
            $rule['jade'] = 0;
        }
        if ($level < 51) {
            $rule['soul'] = 0;
        }
        $cityStatus = (int)($myCountry['city_status'] ?? 0);
        if ($cityStatus === 1 || $cityStatus === 3) {
            // 胜利的奖励（3天的俸禄）
            $rule['silver'] *= 3;
            $rule['act'] *= 3;
            $rule['soul'] *= 3;
            $rule['jade'] *= 3;
        }

        if ($type === 1) {
            // 修改领取状态（只改仍为已参战的行：多 Worker 并发领取只有一个成功）
            $affected = DB::table('member_country')->where('uid', $uid)->where('warstatus', 1)->whereNull('deleted_at')
                ->update(['city' => 0, 'warstatus' => 0]);
            if ($affected <= 0) {
                $return['status'] = -2;
                return $return;
            }
            Member::addCurrency($uid, silver: max(0, $rule['silver']), act: max(0, $rule['act']), soul: max(0, $rule['soul']));
            if ($rule['jade'] > 0) {
                MemberJade::add($uid, $rule['jade'], 'siege', 'get_reward');
            }
            $after = self::member($uid) ?? $member;
            $return['all_silver'] = (int)$after['silver'];
            $return['all_act'] = (int)$after['act'];
        } else {
            // 返回杀敌榜
            $newEnemyList = [];
            $enemy = Redis::get(self::ENEMY . (int)$member['country']);
            if (is_array($enemy) && $enemy !== []) {
                $names = [];
                foreach (DB::table('member')->select('uid', 'nickname')->whereIn('uid', array_map('intval', array_keys($enemy)))->whereNull('deleted_at')->get() as $row) {
                    $names[(int)$row['uid']] = (string)$row['nickname'];
                }
                foreach ($enemy as $key => $val) {
                    $list = [];
                    if (isset($names[(int)$key])) {
                        $list['nickname'] = $names[(int)$key];
                        $list['num'] = $val;
                        $list['uid'] = (int)$key;
                    }
                    $newEnemyList[] = $list;
                }
            }
            $return['list'] = array_slice($newEnemyList, 0, 5);
        }

        $return['status'] = 1;
        $return['rule'] = $rule;
        return $return;
    }

    /**
     * 聊天室 / 国家战报中查看国战战报: c=siege&m=get_report（id 城池 id，读 Redis 中本城战报）
     *
     * status：-1 战报不存在；1 成功
     */
    public static function get_report(Context $ctx): array
    {
        $return = ['status' => 0];
        $city = (int)$ctx->input('id', 0);
        $report = Redis::get(self::KEYS . $city . '_battle');
        if (!is_array($report)) {
            $return['status'] = -1;
            return $return;
        }
        // 双方人员列表重排
        $attack = array_values($report['attack'] ?? []);
        $defense = array_values($report['defense'] ?? []);
        $capital = self::capital($city);

        $return['status'] = 1;
        $return['battle'] = $report['battle'] ?? null;
        $return['attack'] = $attack;
        $return['defense'] = $defense;
        $return['city_name'] = $capital !== null ? $capital['name'] : '';
        $return['attack_num'] = count($attack);
        $return['attack_name'] = $report['attack_name'] ?? null;
        $return['attack_level'] = $report['attack_level'] ?? null;
        $return['defense_num'] = count($defense);
        $return['defense_name'] = $report['defense_name'] ?? null;
        $return['defense_level'] = $report['defense_level'] ?? null;
        $return['npc'] = $report['npc'] ?? null;
        return $return;
    }

    /**
     * 国家战报 - 国战日志: c=siege&m=siege_notice（上周的国战；周六 21:00 以后为本周）
     *
     * status：2 等级不足；1 成功
     */
    public static function siege_notice(Context $ctx): array
    {
        $now = BossClock::now();
        $return = ['status' => 0, 'log' => []];
        $member = self::member(MemberHandler::resolveUid($ctx));
        // 判断开启等级
        if ($member === null || (int)$member['level'] < 26) {
            $return['status'] = 2;
            return $return;
        }
        // 获取战报日志（countryfight_model->get_siege_notice_list：旧版按 MySQL YEARWEEK 周日起算的周，这里按同样的周界在 PHP 里算）
        $weekStart = (int)strtotime(date('Y-m-d', $now)) - (int)date('w', $now) * 86400;
        if (!((int)date('w', $now) === 6 && (int)date('Hi', $now) >= 2100)) {
            $weekStart -= 7 * 86400;
        }
        $rows = DB::table('country_siege_log')->select(...self::SIEGE_LOG_COLUMNS)
            ->where('dateline', '>=', $weekStart)->where('dateline', '<', $weekStart + 7 * 86400)
            ->whereNull('deleted_at')->orderBy('dateline', 'DESC')->get();
        $return['status'] = 1;
        $return['log'] = array_map(static function (array $row): array {
            foreach ($row as $key => $value) {
                $row[$key] = in_array($key, ['city_name', 'country_name', 'attack_country_name'], true) ? (string)$value : (int)$value;
            }
            return $row;
        }, $rows);
        return $return;
    }

    /**
     * 国战计划任务（旧版 cron.php:siege_battle，每分钟）：周六国战期间，在某城开战前 3 分钟起 10 分钟内算出该城的整场战斗
     *
     * @return int 1 本次算出了战斗；0 没有需要计算的城
     */
    public static function cronBattle(int $now): int
    {
        // 不在国战期间，不能继续下面的操作
        if (!self::isWarTime($now)) {
            return 0;
        }
        foreach (self::capitalList() as $effect) {
            // 国战提前3分种开始计算
            $startTime = self::battleTime($effect, $now) - 180;
            if ($now >= $startTime && $now < $startTime + 600) {
                // 查询该城池是否有战斗
                $info = self::castellan($effect['id']);
                if ($info === null || ($info['challengercid'] === 0 && $info['cid'] === 0)) {
                    // 没有国战
                    return 0;
                }
                $result = self::cityBattle($effect['id'], $effect, 0, $now, 0, $info);
                return isset($result['computed']) ? 1 : 0;
            }
        }
        return 0;
    }

    /**
     * 获得国家都城加成（移植 siege_model->get_city_effect）
     *
     * 不传 $country / $targetUid 时查当前玩家本人：没加入国家 -1；当天加入国家 -5；没有国家记录 -6。
     * 国家没有占领都城 -2；都城不存在 -3。效果 1（征收）每天 7 点后首次调用给成员加 value 次免费征收（times 为加后次数），
     * 2（通商）每天首次调用加通商次数；3 ~ 7 只返回数值，由调用方按 value 加成。
     *
     * @param array<string, mixed> $member 当前玩家（member 行）
     * @return array{status: int, times: int, type?: int, value?: int}
     */
    public static function getCityEffect(array $member, int $now, int $country = 0, int $uid = 0): array
    {
        $return = ['status' => 0, 'times' => 0];
        // 如果没有传国家和用户id，就是查自己的信息
        if ($uid === 0 || $country === 0) {
            if ((int)$member['country'] === 0) {
                $return['status'] = -1;
                return $return;
            }
            // 获取自己的国家信息（一天加入国家，无法获得任何国家奖励）
            $mine = CountryHandler::memberCountry((int)$member['uid']);
            if ($mine === null) {
                $return['status'] = -6;
                return $return;
            }
            if (date('ymd', $now) === date('ymd', (int)$mine['joindateline'])) {
                $return['status'] = -5;
                return $return;
            }
        }
        // 如果参数为空，就赋值为自己的信息
        $uid = $uid > 0 ? $uid : (int)$member['uid'];
        $country = $country > 0 ? $country : (int)$member['country'];

        // 判断国家有没有都城
        $row = DB::table('country_city')->select(...self::CITY_COLUMNS)->where('cid', $country)->whereNull('deleted_at')->first();
        if ($row === null) {
            $return['status'] = -2;
            return $return;
        }
        // 查询这个都城的占领效果
        $capital = self::capital((int)$row['maplevel']);
        if ($capital === null) {
            $return['status'] = -3;
            return $return;
        }

        if (!in_array($capital['type'], [3, 4, 5], true)) {
            // 添加次数，查询今日有没有更新过
            $result = DB::table('member_country')->select('effect', 'effect_type')->where('country', $country)->where('uid', $uid)
                ->whereNull('deleted_at')->first();
            $effect = (int)($result['effect'] ?? 0);
            $effectType = (int)($result['effect_type'] ?? 0);
            if ($capital['type'] === 1 && $result !== null) {
                // 今天未领取过通商奖励，且到了 7 点征收时间
                $cronSec = 3600 * 7;
                if (!(date('Ymd', $now) === date('Ymd', $effect) && $effectType === 2)
                    && date('Ymd', $now - $cronSec) !== date('Ymd', $effect - $cronSec)) {
                    // 先以更新时间抢占（多 Worker 并发只加一次），再增加征收次数
                    $affected = DB::table('member_country')->where('country', $country)->where('uid', $uid)
                        ->where('effect', $effect)->where('effect_type', $effectType)->whereNull('deleted_at')
                        ->update(['effect' => $now, 'effect_type' => $capital['type']]);
                    if ($affected > 0) {
                        DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->increment('levytimes', $capital['value']);
                        Redis::del('player:' . $uid);
                        $return['times'] = (int)DB::table('member_field')->where('uid', $uid)->value('levytimes');
                    }
                }
            } elseif ($capital['type'] === 2 && date('Ymd', $now) !== date('Ymd', $effect)) {
                // 依赖未移植：member_activity.freebusiness 通商免费次数（新版 member_activity 无此字段），不加次数、不记更新时间
            }
        }
        $return['status'] = 1;
        $return['type'] = $capital['type'];
        $return['value'] = $capital['value'];
        return $return;
    }

    /**
     * 按国家 id 清理国战状态（siege_model->clear_battle_status，国家解散时调用）：
     * 是挑战者则清挑战者（有占领者时占领国状态改回已占领），是占领者则清占领者
     */
    public static function clearBattleStatus(int $countryId): int
    {
        $city = self::castellanByCountry($countryId);
        if ($city === null) {
            return 0;
        }
        if ($city['challengercid'] === $countryId) {
            // 挑战者
            if ($city['cid'] > 0) {
                // 有占领者(把占领者国家的状态改为已占领)
                self::updateCityStatus($city['cid'], $city['maplevel'], 1);
            }
            $data = ['challengercid' => 0, 'challengername' => '', 'challengerlevel' => 0, 'challengerdateline' => 0];
        } else {
            $data = ['cid' => 0, 'name' => '', 'level' => 0, 'dateline' => 0];
        }
        $affected = DB::table('country_city')->where('maplevel', $city['maplevel'])->whereNull('deleted_at')->update($data);
        return $affected > 0 ? 1 : 0;
    }

    /**
     * 离开国家，国战报名状态清理（siege_model->clear_siege_status）
     */
    public static function clearSiegeStatus(int $uid): int
    {
        if ($uid <= 0) {
            return 0;
        }
        DB::table('member_country')->where('uid', $uid)->whereNull('deleted_at')->update(['city' => 0, 'warstatus' => 0]);
        return 1;
    }

    /**
     * 主城国战提示（移植旧版 member.php:get_country_war，member index 每次请求现算，不走玩家缓存）
     *
     * country_war：0 无；1 可报名 / 已报名；2 国战马上开始；4 可直接进战场（上次奖励未领）。
     *
     * @param array{uid: int|string, country: int|string} $member
     * @return array{country_war: int, is_saturday: int, country_lock: int, leave_cd: int, country_date: string, enemy_name: string}
     */
    public static function countryWar(array $member, int $now): array
    {
        $return = [
            'country_war' => 0,
            'is_saturday' => 0,
            'country_lock' => 0,
            'leave_cd' => 0,
            'country_date' => '',
            'enemy_name' => '',
        ];
        // 未进国家，没有国战状态
        if ((int)$member['country'] <= 0) {
            return $return;
        }
        // 查询国家是否有国战
        $myCountry = CountryHandler::countryInfo((int)$member['country']);
        $cityStatus = (int)($myCountry['city_status'] ?? 0);
        // 查询自己国战参加状态
        $country = CountryHandler::memberCountry((int)$member['uid']);
        $warstatus = (int)($country['warstatus'] ?? 0);

        // 离开国家时间小于7天（无法进入国战）
        if ($country !== null && ((int)$country['leavedateline'] + 7 * 86400) - $now > 0) {
            $return['leave_cd'] = (int)$country['leavedateline'] + 7 * 86400 - $now;
        }

        // 国家没有国战，但是国家参战状态为真（上次奖励未领，可直接进国战）
        if ($cityStatus <= 1 && $warstatus === 1) {
            $return['country_war'] = 4;
            return $return;
        }

        if ($cityStatus > 0) {
            // 国家有国战，取国战开始时间
            $effect = self::capital((int)$myCountry['city']);
            $timeStr = (string)($effect['time'] ?? '');
            $return['country_date'] = ((int)substr($timeStr, 0, 2) - 1) . ':' . substr($timeStr, 2);
            // 判断国战时间，分配国战状态（0没有及时报名，2国战快开始，is_saturday可报名）
            if ((int)date('w', $now) === 6 && (int)date('H', $now) >= 17 && (int)date('H', $now) <= 21) {
                // 查询国战开始时间（在国战停止报名后，查询有没有参加国战）
                if ($effect !== null && (int)date('Hi', $now + 180) >= $effect['time']) {
                    $return['country_war'] = $warstatus !== 1 ? 0 : 1;
                } else {
                    $return['country_war'] = 1;
                }
                if ($effect !== null) {
                    $time = self::battleTime($effect, $now);
                    // 开始报名时间
                    $startTime = $time - 3600;
                    $endTime = $time - 180;
                    // 国战开始pk前3分钟，提示快开始
                    if ($now >= $endTime && $now <= $time) {
                        $return['country_war'] = 2;
                    }
                    // 国战报名期间提示可报名
                    if ($now >= $startTime && $now <= $time) {
                        $return['is_saturday'] = 1;
                    }
                }
            } elseif ($warstatus === 1) {
                // 上次国战未结束，可进战斗界面
                $return['country_war'] = 4;
            }

            // 在国战状态为等待开启时，获取敌方名称
            if ($return['is_saturday'] !== 1) {
                $info = self::castellan((int)$myCountry['city']);
                $return['country_lock'] = (int)($info['lock'] ?? 0);
                if ($cityStatus === 2) {
                    if ($info !== null && $info['cid'] !== 0) {
                        $enemy = CountryHandler::countryInfo($info['cid']);
                        $return['enemy_name'] = (string)($enemy['name'] ?? '');
                    }
                } elseif ($cityStatus === 3) {
                    // 查询挑战者名称
                    $enemy = CountryHandler::countryInfo((int)($info['challengercid'] ?? 0));
                    $return['enemy_name'] = (string)($enemy['name'] ?? '');
                }
                // 敌方名称为空，查询npc名称
                if ($return['enemy_name'] === '') {
                    $mission = self::maxMissionByCity((int)($info['maplevel'] ?? 0));
                    $return['enemy_name'] = ($mission['name'] ?? '') . self::LANG_SIEGE_ARMY_GROUP;
                }
            }
        }
        return $return;
    }

    /**
     * @param array<string, mixed>|null $member
     * @return array<string, mixed>
     */
    private static function doOccupy(?array $member, int $id, int $sure, int $force, int $now): array
    {
        $return = [];
        // 查询我的国家信息
        $country = $member !== null ? CountryHandler::memberInfo((int)$member['uid'], (int)$member['country']) : null;
        if ($country === null || (int)$country['country'] === 0) {
            // 未进入国家，无法占领
            $return['status'] = -1;
            return $return;
        }
        $myCountryId = (int)$member['country'];
        if ((int)$country['job'] > 3) {
            // 职位不够，不能占领
            $return['status'] = -2;
            return $return;
        }
        $week = (int)date('w', $now);
        $hour = (int)date('H', $now);
        // 判断是否是在国战期间(国战24小时前到国战结束才能继续占领)
        if (($week === self::END_DAY && $hour >= self::START_HOURS) || ($week === self::START_DAY && $hour <= self::END_HOURS)) {
            $return['status'] = -3;
            return $return;
        }
        // 国战时间内，按钮变灰
        if (self::isWarTime($now)) {
            $return['status'] = -9;
            return $return;
        }
        // 先查询自己的国家有没有已经请战，没有才执行下面的
        $myCity = self::castellanByCountry($myCountryId);
        if ($myCity !== null) {
            // 判断该城是否已经占领或申请占领该国家
            if ($myCity['maplevel'] === $id) {
                $return['status'] = -4;
                return $return;
            }
            // 如果城池已被锁定，无法放弃占领，或攻打其他城池
            if ($myCity['lock'] > 0) {
                $return['status'] = $myCity['cid'] === $myCountryId ? -12 : -10;
                return $return;
            }
            // 查询该城的名称
            $myCity['city_name'] = self::capital($myCity['maplevel'])['name'] ?? null;
        }
        // 我的国家信息
        $myCountry = CountryHandler::countryInfo((int)$country['country']) ?? [];
        // 查询这个都城的相关信息
        $info = self::capital($id);
        if ($info === null) {
            $return['status'] = -5;
            return $return;
        }
        // 判断该都城的开启状态
        if ($info['id'] > self::maxMap()) {
            $return['status'] = -6;
            return $return;
        }
        // 判断是否是至尊占领
        if ($force > 0) {
            // 判断职位是否足够
            if ((int)$country['job'] !== 1) {
                $return['status'] = -11;
                return $return;
            }
            // 判断vip等级是否足够
            if ((int)$member['vip'] < self::FORCE_VIP) {
                $return['status'] = -8;
                $return['msg'] = self::LANG_SIEGE_FORCE_OCCUPY_NEED_VIP;
                return $return;
            }
        }
        if ($force > 0 && (int)$member['gold'] < self::PRICE) {
            $return['status'] = -13;
            return $return;
        }

        // 查询该城的占领者和挑战者
        $cityInfo = self::castellan($id);
        $old = null;
        $oldKingInfo = null;
        if ($cityInfo !== null && $cityInfo['challengercid'] === $myCountryId) {
            // 占领者已经是自己
            $return['status'] = -7;
            return $return;
        } elseif ($cityInfo !== null && $cityInfo['challengercid'] > 0) {
            // 历史挑战者（取这个国家的信息，判断等级）
            $old = CountryHandler::countryInfo($cityInfo['challengercid']) ?? [];
            if ($cityInfo['lock'] === 1 && $force === 0) {
                // 【xx】已被【xx】发起至尊攻城，普通攻占无法取代占领资格！
                $return['msg'] = sprintf(self::LANG_SIEGE_ONT_OCCUPY, $info['name'], $old['name'] ?? '');
                $return['status'] = -8;
                return $return;
            }
            if ($force === 0 && (int)($myCountry['level'] ?? 0) <= (int)($old['level'] ?? 0)) {
                $return['msg'] = sprintf(self::LANG_SIEGE_OCCUPY_LOSE, $myCountry['name'] ?? '', $myCountry['level'] ?? '', $info['name'], $old['name'] ?? '');
                $return['status'] = -8;
                return $return;
            }
            if ((($myCity !== null && $sure === 1) || $myCity === null) && $force > 0) {
                // 如果至尊占领，挑战历史挑战者的国家君主
                $fight = self::forceBattle($member, $cityInfo['challengercid'], $return);
                if ($fight === null) {
                    return $return;
                }
                $oldKingInfo = $fight;
            }
            // 放弃之前城池，占领新的城池
            $abandon = self::abandonCity($myCity, $myCountryId, $info, $sure);
            if ($abandon !== null) {
                return $abandon;
            }
            // 把历史挑战国家的占领状态去掉
            self::updateCityStatus((int)($old['id'] ?? $cityInfo['challengercid']));
            // 把占领者的国家状态改成有挑战者
            self::updateCityStatus($cityInfo['cid'], $id, 3);
            // 修改挑战者信息
            self::updateCastellan($id, $myCountry, $now);
            $return['msg'] = sprintf(self::LANG_SIEGE_OCCUPY_CHANGE, $myCountry['name'] ?? '', $myCountry['level'] ?? '', $info['name'], $old['name'] ?? '');
            // 占领资格被替换，发信通知该国家成员
            $oldUids = self::memberUids($cityInfo['challengercid']);
            if ($oldUids !== []) {
                $message = sprintf(self::LANG_SIEGE_OCCUPY_CH_MSG, $myCountry['name'] ?? '', $myCountry['level'] ?? '', $info['name'], $old['name'] ?? '', $old['level'] ?? '');
                CountryHandler::sendMessage($oldUids, $message);
            }
        } elseif ($cityInfo !== null && $cityInfo['challengercid'] === 0) {
            // 如果至尊占领，挑战占领国的君主
            if ((($myCity !== null && $sure === 1) || $myCity === null) && $force > 0 && $cityInfo['cid'] > 0) {
                $fight = self::forceBattle($member, $cityInfo['cid'], $return);
                if ($fight === null) {
                    return $return;
                }
                $oldKingInfo = $fight;
            }
            // 放弃之前城池，占领新的城池
            $abandon = self::abandonCity($myCity, $myCountryId, $info, $sure);
            if ($abandon !== null) {
                return $abandon;
            }
            // 挑战占领者
            self::updateCastellan($id, $myCountry, $now);
            // 把占领者的国家状态改成有挑战者
            self::updateCityStatus($cityInfo['cid'], $id, 3);
        } else {
            // 放弃之前城池，占领新的城池
            $abandon = self::abandonCity($myCity, $myCountryId, $info, $sure);
            if ($abandon !== null) {
                return $abandon;
            }
            // 挑战npc
            DB::table('country_city')->insert([
                'maplevel' => $id,
                'challengercid' => (int)($myCountry['id'] ?? 0),
                'challengername' => (string)($myCountry['name'] ?? ''),
                'challengerdateline' => $now,
            ]);
        }

        // 挑战成功，给国家成员发信通知
        $myUids = self::memberUids($myCountryId);
        if ($myUids !== []) {
            $time = (string)$info['time'];
            $dateline = substr($time, 0, 2) . self::LANG_BASE_HOUR . substr($time, 2) . self::LANG_BASE_MINUTE;
            CountryHandler::sendMessage($myUids, sprintf(self::LANG_SIEGE_OCCUPY_WIN, $myCountry['name'] ?? '', $info['name'], $dateline));
        }

        // 如果是至尊占领（把城池锁定）
        if ($force > 0) {
            // 获取城池防守方名称
            $defenseName = $cityInfo['name'] ?? '';
            if ($cityInfo === null || $cityInfo['cid'] === 0) {
                // 查询该城的防守方
                $defenseName = self::maxMissionByCity($id)['name'] ?? null;
            }
            $message = sprintf(self::LANG_SIEGE_FORCE_ATTACK_WIN, $myCountry['name'] ?? '', $member['nickname'], $info['name'], $defenseName);
            $sender = Chat::sender((int)$member['uid']);
            Chat::sendToHorn($message, $sender);
            $uids = [];
            if ($cityInfo !== null && $cityInfo['cid'] !== 0) {
                $uids = self::memberUids($cityInfo['cid']);
            }
            $uids = array_merge($uids, self::memberUids($myCountryId));
            if ($cityInfo !== null && $cityInfo['challengercid'] > 0) {
                // 【xx】君主【xx】对【xx】发起至尊挑战，战胜另一挑战者【xx】的君主【xx】，获得挑战资格。
                $message2 = sprintf(self::LANG_SIEGE_FORCE_ATTACK_CHALLENGER_WIN, $myCountry['name'] ?? '', $member['nickname'], $info['name'], $cityInfo['challengername'], $oldKingInfo['nickname'] ?? null);
                Chat::sendToHorn($message2, $sender);
                $challengerUids = self::memberUids($cityInfo['challengercid']);
                if ($challengerUids !== []) {
                    $uids = array_merge($uids, $challengerUids);
                    // 国家聊天室（旧版国家队列取发起人的国家）
                    Chat::sendToCountry($message2, $uids, Chat::TYPE_COUNTRY, 1, 0, 0, $sender);
                    CountryHandler::sendMessage($uids, $message2);
                }
            }
            // 国家聊天室
            Chat::sendToCountry($message, $uids, Chat::TYPE_COUNTRY, 1, 0, 0, $sender);
            if ($uids !== []) {
                CountryHandler::sendMessage($uids, $message);
            }
            self::lockCity($id, 1);
        } else {
            self::lockCity($id, 0);
        }
        // 修改国家都城占领状态，这里改为已请战
        self::updateCityStatus($myCountryId, $id, 2);
        $return['status'] = 1;
        return $return;
    }

    /**
     * 至尊攻城：扣元宝后与对方国家君主（官职最高者）单挑（battle_pk）
     *
     * 战败时把 status 1 与战报写进 $return 并返回 null（调用方直接返回）；战胜返回对方君主信息。
     *
     * @param array<string, mixed> $member
     * @param array<string, mixed> $return
     * @return array<string, mixed>|null
     */
    private static function forceBattle(array $member, int $country, array &$return): ?array
    {
        $uid = (int)$member['uid'];
        $oldKing = CountryHandler::topJob($country);
        $kingUid = (int)($oldKing['uid'] ?? 0);
        // 获取敌方用户信息
        $oldKingInfo = $kingUid > 0 ? self::member($kingUid) : null;
        // 扣除元宝（依赖未移植：gold_log 元宝流水）
        if (!Member::deductCurrency($uid, gold: self::PRICE)) {
            $return['status'] = -13;
            return null;
        }
        $battle = ArenaHandler::battlePk($uid, $kingUid);
        $assess = $battle['info']['assess'] ?? null;
        $return['info']['win'] = $assess;
        $return['battle'] = $battle['info'] ?? [];
        $return['battle']['left_role'] = [
            'name' => (string)$member['nickname'],
            'level' => (int)$member['level'],
            'gender' => (int)$member['gender'],
        ];
        $return['battle']['right_role'] = [
            'name' => $oldKingInfo !== null ? (string)$oldKingInfo['nickname'] : null,
            'level' => $oldKingInfo !== null ? (int)$oldKingInfo['level'] : null,
            'gender' => $oldKingInfo !== null ? (int)$oldKingInfo['gender'] : null,
        ];
        if ($assess === null || $assess <= 0) {
            $return['status'] = 1;
            return null;
        }
        return $oldKingInfo ?? [];
    }

    /**
     * 放弃之前城池，占领新的城池（occupy 三个分支共用）：未确认时返回 status 3 与提示，确认后清除原城状态
     *
     * @param array<string, mixed>|null $myCity
     * @param array<string, mixed> $info 新城（CountryCityEffect 行）
     * @return array<string, mixed>|null 需要确认时的返回值
     */
    private static function abandonCity(?array $myCity, int $myCountryId, array $info, int $sure): ?array
    {
        if ($myCity === null) {
            return null;
        }
        if ($myCity['cid'] === $myCountryId) {
            if ($sure !== 1) {
                return [
                    'status' => 3,
                    'msg' => sprintf(self::LANG_SIEGE_CHANGE_OWN_CITY, $myCity['city_name'], $info['name'], $myCity['city_name']),
                ];
            }
            // 把之前城池的占领者去掉
            self::clearOccupant($myCity['maplevel']);
        } elseif ($myCity['challengercid'] === $myCountryId) {
            if ($sure !== 1) {
                return [
                    'status' => 3,
                    'msg' => sprintf(self::LANG_SIEGE_CHANGE_OCCPY_CITY, $myCity['city_name'], $info['name'], $myCity['city_name']),
                ];
            }
            // 把之前申请占领城池的占领国家状态去掉
            self::updateCityStatus($myCity['cid'], $myCity['maplevel'], 1);
            // 把之前申请占领的城池挑战者去掉
            self::updateCastellan($myCity['maplevel'], null, 0);
        }
        return null;
    }

    /**
     * 取城池的国战战斗（action 与计划任务共用）：Redis 战报 → 数据库 6 天内的战报 → 国战期间现算
     *
     * $selfUid 为发起请求的玩家（计划任务为 0）：现算时团战里的 6 星战斗特效只对本人生效；玩家请求现算且攻方胜利时，
     * 攻方全体成员自动领取都城效果（get_city_effect），计划任务不领（旧版同）。
     *
     * @param array<string, mixed> $effect 城池（CountryCityEffect 行）
     * @param array<string, mixed>|null $cronInfo 计划任务已查到的城池占领记录
     * @return array{data: array<string, mixed>, report_id: int|null, computed?: bool}|array{status: int}
     */
    private static function cityBattle(int $city, array $effect, int $myCountry, int $now, int $selfUid, ?array $cronInfo = null): array
    {
        $isCron = $cronInfo !== null;
        $battleKeys = self::KEYS . $city . '_battle';
        $report = null;
        // 取战斗的锁：已有锁时等另一个请求算完（旧版每 2 秒取一次战报，直到取到或锁到期）
        $lockKey = self::LOCK . $city;
        $redis = Redis::instance();
        $locked = false;
        $lockValue = $now + 40;
        if ($isCron || self::isWarTime($now)) {
            // 加锁（只在国战期间调用战斗才加锁）
            $locked = $redis !== null && (bool)$redis->set($lockKey, json_encode($lockValue), ['nx', 'ex' => $isCron ? 86400 : 18000]);
        }
        if (!$locked) {
            $lock = Redis::get($lockKey);
            if ($lock !== null) {
                // 旧版 sleep(2) 轮询；这里 0.2 秒一次，等到锁记录的「加锁时间 + 40 秒」为止（按本次请求时间加已等待的时间算）
                $started = microtime(true);
                while (true) {
                    $report = Redis::get($battleKeys);
                    if (is_array($report) || (int)$lock - ($now + (int)(microtime(true) - $started)) <= 0) {
                        break;
                    }
                    usleep(200000);
                }
            }
        }
        $report = is_array($report) ? $report : null;

        if ($isCron) {
            if ($report !== null) {
                // 缓存有战报，则不需要再次调用算法
                return ['data' => $report, 'report_id' => null];
            }
            // 查询数据库有没有战报
            $reportInfo = self::getReport($city);
            if ($reportInfo !== null && $reportInfo['dateline'] > $now - 6 * 86400) {
                return ['data' => [], 'report_id' => $reportInfo['id']];
            }
            return self::computeBattle($city, $effect, $cronInfo, $now, 0) + ['computed' => true];
        }

        $warNow = (int)date('w', $now) === self::START_DAY && (int)date('H', $now) >= self::START_HOURS && (int)date('H', $now) < self::END_HOURS;
        // 没有国战战报，或者不在国战期间
        if ($report !== null || !$warNow) {
            if ($report !== null) {
                // 取到了战报，查询战报ID
                $temp = self::getReport($city);
                return ['data' => $report, 'report_id' => $temp['id'] ?? null];
            }
            // 按城池id最后一条战报
            $reportInfo = self::getReport($city);
            if ($reportInfo === null || $reportInfo['dateline'] < $now - 6 * 86400) {
                // 不是上次战斗的战报
                return ['status' => -2];
            }
            return ['data' => self::cacheReport($city, $reportInfo, 43200), 'report_id' => $reportInfo['id']];
        }
        // 不在国战期间不让取战报
        if (!self::isWarTime($now)) {
            return ['status' => -2];
        }
        // 获取国战是否已经打过（按城池id最后一条战报，战报丢失的情况用到）
        $reportInfo = self::getReport($city);
        if ($reportInfo !== null && $reportInfo['dateline'] > $now - 6 * 86400) {
            // 找到了战报，不进行战斗，直接使用战报
            return ['data' => self::cacheReport($city, $reportInfo, 43200), 'report_id' => $reportInfo['id']];
        }
        // 用玩家的国家id查询都城占领记录
        $info = self::castellanByCountry($myCountry);
        if ($info === null) {
            return ['status' => -3];
        }
        return self::computeBattle($city, $effect, $info, $now, $selfUid);
    }

    /**
     * 读出数据库战报并缓存到 Redis（杀敌榜与本城战报）
     *
     * @param array{id: int, city: int, report: string, dateline: int} $reportInfo
     * @return array<string, mixed>
     */
    private static function cacheReport(int $city, array $reportInfo, int $ttl): array
    {
        $report = json_decode($reportInfo['report'], true);
        $report = is_array($report) ? $report : [];
        $battle = $report['battle'] ?? [];
        $data = [
            'attack' => $report['attack'] ?? [],
            'attack_name' => $report['attack_name'] ?? null,
            'attack_level' => $report['attack_level'] ?? null,
            'defense' => $report['defense'] ?? [],
            'defense_name' => $report['defense_name'] ?? null,
            'defense_level' => $report['defense_level'] ?? null,
            'cid' => $report['cid'] ?? 0,
            'challengercid' => $report['challengercid'] ?? 0,
            'npc' => $report['npc'] ?? null,
            'battle' => $battle,
            'att_type' => $report['att_type'] ?? 0,
        ];
        // 把杀敌榜存储到redis
        if ((int)$data['challengercid'] !== 0) {
            Redis::set(self::ENEMY . (int)$data['challengercid'], $battle['kill_rank']['left'] ?? null, $ttl);
        }
        if ((int)$data['cid'] !== 0) {
            Redis::set(self::ENEMY . (int)$data['cid'], $battle['kill_rank']['right'] ?? null, $ttl);
        }
        Redis::set(self::KEYS . $city . '_battle', $data, $ttl);
        return $data;
    }

    /**
     * 现算一座城的国战（团战 battle_rush）并结算城池归属
     *
     * 攻方：挑战国报名列表（没有挑战国时为 NPC 守将）；守方：占领国报名列表（没有占领国时为 NPC 守将）。
     * 团战类型：0 玩家对玩家；1 玩家攻 NPC 城；3 NPC 攻玩家（怪物攻城）。
     *
     * @param array<string, mixed> $effect
     * @param array<string, mixed> $info 城池占领记录
     * @return array{data: array<string, mixed>, report_id: int}
     */
    private static function computeBattle(int $city, array $effect, array $info, int $now, int $selfUid): array
    {
        $attackKeys = self::KEYS . $city . '_attack';
        $defenseKeys = self::KEYS . $city . '_defense';
        $npcAttackList = $npcDefenseList = [];
        // 攻击方
        if ($info['challengercid'] === 0) {
            // 系统NPC
            $attType = 1;
            $attackArr = self::npcArmy($info['maplevel']);
            $attackList = [];
            foreach (self::missionsByCity($city) as $key => $val) {
                $npcAttackList[$val['id']] = $val;
                $attackList[$key] = ['uid' => $val['id'], 'nickname' => $val['name'], 'level' => $val['level']];
            }
        } else {
            // 查询攻击方国家名称和等级
            $attType = 0;
            $attackArr = CountryHandler::countryInfo($info['challengercid']);
            $attackList = Redis::get($attackKeys);
            $attackList = is_array($attackList) ? $attackList : [];
        }
        // 防守方
        if ($info['cid'] === 0) {
            // 系统NPC
            $type = 1;
            $defenseArr = self::npcArmy($info['maplevel']);
            $defenseList = [];
            foreach (self::missionsByCity($city) as $key => $val) {
                $npcDefenseList[$val['id']] = $val;
                $defenseList[$key] = ['uid' => $val['id'], 'nickname' => $val['name'], 'level' => $val['level']];
            }
        } else {
            // 查询防守方国家名称和等级
            $type = 0;
            $defenseArr = CountryHandler::countryInfo($info['cid']);
            $defenseList = Redis::get($defenseKeys);
            $defenseList = is_array($defenseList) ? $defenseList : [];
        }

        // 处理攻击人员 / 防御人员（报名列表按官职键倒序出场）
        $newAttackList = $newDefenseList = [];
        if ($attType === 0 && $attackList !== []) {
            krsort($attackList);
            foreach ($attackList as $val) {
                $newAttackList[] = (int)$val['uid'];
            }
        } elseif ($attType === 1) {
            $newAttackList = $npcAttackList;
        }
        if ($type === 0 && $defenseList !== []) {
            krsort($defenseList);
            foreach ($defenseList as $val) {
                $newDefenseList[] = (int)$val['uid'];
            }
        } elseif ($type === 1) {
            $newDefenseList = $npcDefenseList;
        }
        // 怪物攻城，战斗类型为3
        if ($type === 0 && $attType === 1) {
            $type = 3;
        }
        $battle = (new BattleRush($selfUid))->run($newAttackList, $newDefenseList, $type);
        // 把杀敌榜存储到redis(攻击方 / 防守方)
        if ($info['challengercid'] !== 0) {
            Redis::set(self::ENEMY . $info['challengercid'], $battle['kill_rank']['left'], 86400 * 5);
        }
        if ($info['cid'] !== 0) {
            Redis::set(self::ENEMY . $info['cid'], $battle['kill_rank']['right'], 86400 * 5);
        }
        // 把战斗过程和双方列表保存到缓存
        $data = [
            'attack_level' => $attackArr['level'] ?? null,
            'attack_name' => $attackArr['name'] ?? null,
            'defense_level' => $defenseArr['level'] ?? null,
            'defense_name' => $defenseArr['name'] ?? null,
            'attack' => $attackList,
            'defense' => $defenseList,
            'cid' => $info['cid'],
            'challengercid' => $info['challengercid'],
            'battle' => $battle,
            'npc' => $type,
            'att_type' => $attType,
        ];
        Redis::set(self::KEYS . $city . '_battle', $data, 86400 * 5);
        $reportId = (int)DB::table('country_city_report')->insert([
            'city' => $city,
            'report' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'dateline' => $now,
        ]);
        // 清空攻击方和防守方的redis列表
        Redis::del($attackKeys, $defenseKeys);
        // 清理城池锁定状态
        self::lockCity($city, 0);
        // 玩家请求里现算时以该玩家为发信人；计划任务里没有发信人（旧版 cron 把 $this->member 置空）
        $sender = $selfUid > 0 ? Chat::sender($selfUid) : null;
        if ($battle['win'] === 1) {
            //【哈哈肥猪】击败了【张角军团】 ，成功占领【濮阳城】。
            Chat::sendToAll(sprintf(self::LANG_SIEGE_BATTLE_WIN, $data['attack_name'], $data['defense_name'], $effect['name']), Chat::TYPE_SYSTEM, 0, 0, $sender);
            // 攻击方胜利（修改城池占领者）
            self::updateOccupant($city, $now);
            if ($info['challengercid'] !== 0 && $selfUid > 0) {
                // 自动领取国战加成奖励（只有玩家请求里现算时）
                foreach (CountryHandler::memberList($info['challengercid'], 1, 0) as $val) {
                    self::getCityEffect([], $now, (int)$val['country'], (int)$val['uid']);
                }
            }
            // 把战报发送到国家聊天室（攻方 20、守方 19）
            self::sendSiegeReport(20, $info['challengercid'], $data['attack_name'], $data['defense_name'], $effect, $sender);
            self::sendSiegeReport(19, $info['cid'], $data['defense_name'], $data['attack_name'], $effect, $sender);
            self::addFightSiegeNotice($data, $effect, $reportId, 1, $info['lock'], $now);
        } else {
            //【张角军团】击败了前来攻城的【哈哈肥猪队】，濮阳城防守成功。
            Chat::sendToAll(sprintf(self::LANG_SIEGE_BATTLE_LOSE, $data['defense_name'], $data['attack_name'], $effect['name']), Chat::TYPE_SYSTEM, 0, 0, $sender);
            // 失败，修改挑战者为空
            self::updateCastellan($city, null, 0);
            // 把挑战者的国家都城状态和占领都城都改成0
            self::updateCityStatus($info['challengercid']);
            if ($info['cid'] > 0) {
                // 把占领者的国家状态改成已占领
                self::updateCityStatus($info['cid'], $city, 1);
            }
            // 把战报发送到国家聊天室（攻方 21、守方 18）
            self::sendSiegeReport(21, $info['challengercid'], $data['attack_name'], $data['defense_name'], $effect, $sender);
            self::sendSiegeReport(18, $info['cid'], $data['defense_name'], $data['attack_name'], $effect, $sender);
            self::addFightSiegeNotice($data, $effect, $reportId, 2, $info['lock'], $now);
        }
        return ['data' => $data, 'report_id' => $reportId];
    }

    /**
     * 国战战报发到一方国家聊天室（get_message_str 18 ~ 21 + send_message_to_country(…, 6, 1)）
     *
     * 旧版玩家请求里国家队列取发起请求的玩家的国家（两方战报都进他的国家队列）；计划任务 cron.php:siege_battle 的调用
     * send_message_to_country($message, $uid, 6, 1, 0, 国家id) 把国家id 错位传进了 tree 参数，国家队列为 chat_country_0、
     * 消息的 tree 为国家id。两种情况照旧版，成员的私人队列都正确。
     *
     * @param array<string, mixed> $effect 城池（id、name）
     * @param array{uid: int, nickname: string, country: int, rank: int}|null $sender
     */
    private static function sendSiegeReport(int $type, int $country, mixed $siege1, mixed $siege2, array $effect, ?array $sender): void
    {
        if ($country === 0) {
            return;
        }
        $uids = self::memberUids($country);
        if ($uids === []) {
            return;
        }
        $message = Chat::getMessageStr($type, [
            'siege1' => $siege1,
            'siege2' => $siege2,
            'siege3' => $effect['name'],
            'id' => $effect['id'],
        ], $sender);
        if ($sender !== null) {
            Chat::sendToCountry($message, $uids, Chat::TYPE_COUNTRY, 1, 0, 0, $sender);
        } else {
            Chat::sendToCountry($message, $uids, Chat::TYPE_COUNTRY, 1, 0, $country);
        }
    }

    /**
     * 记录国战日志（countryfight_model->add_fight_siege_notice）
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $city
     */
    private static function addFightSiegeNotice(array $data, array $city, int $reportId, int $win, int $zhizun, int $now): void
    {
        DB::table('country_siege_log')->insert([
            'city' => $city['id'],
            'city_name' => $city['name'],
            'report' => $reportId,
            'zhizun' => $zhizun,
            'dateline' => $now,
            'win_type' => $win === 1 ? 1 : 0,
            'attack_country' => (int)$data['challengercid'],
            'attack_country_name' => (string)($data['attack_name'] ?? ''),
            'country' => (int)$data['cid'],
            'country_name' => (string)($data['defense_name'] ?? ''),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function member(int $uid): ?array
    {
        return DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
    }

    /**
     * 周六 18 ~ 21 点（国战期间）
     */
    private static function isWarTime(int $now): bool
    {
        $hour = (int)date('H', $now);
        return (int)date('w', $now) === self::START_DAY && $hour >= self::START_HOURS && $hour <= self::END_HOURS;
    }

    /**
     * 城池当天的开战时间戳（CountryCityEffect.time 为 HHMM）
     *
     * @param array<string, mixed> $effect
     */
    private static function battleTime(array $effect, int $now): int
    {
        $time = sprintf('%04d', (int)$effect['time']);
        return (int)strtotime(date('Y-m-d ', $now) . substr($time, 0, 2) . ':' . substr($time, 2) . ':0');
    }

    /**
     * 占领效果说明（capital_info 的 str）
     */
    private static function effectStr(int $type, int $value): string
    {
        return match ($type) {
            1 => self::LANG_SIEGE_LEVY . '+' . $value,
            2 => self::LANG_SIEGE_BUSINESS . '+' . $value,
            3 => self::LANG_SIEGE_WORKSHOP . ($value + 100) . '%',
            4 => sprintf(self::LANG_SIEGE_SOUL, $value + 100) . '%',
            5 => sprintf(self::LANG_SIEGE_GEM, $value + 100) . '%',
            6 => sprintf(self::LANG_SIEGE_BEAUTY, $value),
            7 => sprintf(self::LANG_SIEGE_TOWER, $value + 100) . '%',
            default => '',
        };
    }

    /**
     * 都城列表（siege_model->get_capital_list：CountryCityEffect 全部行，附开启状态 open）
     *
     * @return list<array<string, mixed>>
     */
    private static function capitalList(): array
    {
        $openLevel = self::maxMap();
        $list = [];
        foreach (CountryCityEffect::ROWS as $row) {
            $row['open'] = $row['id'] <= $openLevel ? 1 : 0;
            $list[] = $row;
        }
        return $list;
    }

    /**
     * 查询单个城（siege_model->get_capital_by_id）
     *
     * @return array{id: int, name: string, time: int, type: int, value: int, team: string}|null
     */
    private static function capital(int $id): ?array
    {
        return CountryCityEffect::ROWS[$id] ?? null;
    }

    /**
     * 服务器主线最远开放的势力（map_model->get_max_map），都城 id 不大于它时开放
     *
     * 旧版取全服 open_map（每人每势力一行）里 level、stage、mission 最大的一行，该势力最后一关已通过时为该势力，否则减 1；
     * 「已通过」的判断旧版拿势力 id 与最后一关的场景号比较（原样保留）。依赖未移植：open_map，
     * 新版征战进度在 zc_sanguo_map（每人每势力每场景一行，mission 为该场景已通关据点），这里按全服 map 取代。
     */
    private static function maxMap(): int
    {
        $maxMap = DB::table('map')->select('level', 'stage', 'mission')->whereNull('deleted_at')
            ->orderBy('level', 'DESC')->orderBy('stage', 'DESC')->orderBy('mission', 'DESC')->first();
        if ($maxMap === null) {
            return 0;
        }
        $level = (int)$maxMap['level'];
        // 取最大的一个势力的最后一关
        $maxMission = null;
        foreach (Mission::ROWS as $row) {
            if ($row['missionlevel'] === $level
                && ($maxMission === null || [$row['missionstage'], $row['missionid']] > [$maxMission['missionstage'], $maxMission['missionid']])) {
                $maxMission = $row;
            }
        }
        if ($maxMission === null) {
            return $level - 1;
        }
        if ($level === $maxMission['missionlevel'] && $level === $maxMission['missionstage'] && (int)$maxMap['mission'] === $maxMission['missionid']) {
            return $level;
        }
        return $level - 1;
    }

    /**
     * 一个城的最大守将（siege_model->get_maxmission_by_city：CountryMission 里该城 id 最大的一行）
     *
     * 旧版把结果缓存在 Redis country_city_general_{城池}，配置已在 MasterData，不再缓存。
     *
     * @return array<string, mixed>|null
     */
    private static function maxMissionByCity(int $id): ?array
    {
        $max = null;
        foreach (CountryMission::ROWS as $row) {
            if ($row['city'] === $id && ($max === null || $row['id'] > $max['id'])) {
                $max = $row;
            }
        }
        return $max;
    }

    /**
     * 都城的 NPC 守将列表（siege_model->get_mission_by_id，按 id 升序）
     *
     * @return list<array<string, mixed>>
     */
    private static function missionsByCity(int $id): array
    {
        $list = array_values(array_filter(CountryMission::ROWS, static fn(array $row): bool => $row['city'] === $id));
        usort($list, static fn(array $a, array $b): int => $a['id'] <=> $b['id']);
        return $list;
    }

    /**
     * NPC 势力名称（最大守将名 + 军团），等级 0
     *
     * @return array{name: string, level: int}
     */
    private static function npcArmy(int $city): array
    {
        return ['name' => (self::maxMissionByCity($city)['name'] ?? '') . self::LANG_SIEGE_ARMY_GROUP, 'level' => 0];
    }

    /**
     * NPC 守将名单（battle_prepare 显示用：uid 为关卡 id）
     *
     * @return list<array{uid: int, nickname: string, level: int}>
     */
    private static function npcList(int $city): array
    {
        $list = [];
        foreach (self::missionsByCity($city) as $val) {
            $list[] = ['uid' => $val['id'], 'nickname' => $val['name'], 'level' => $val['level']];
        }
        return $list;
    }

    /**
     * 报名列表按键（官职 / 平民 uid）倒序排列后去掉键
     *
     * @return list<array<string, mixed>>
     */
    private static function sortedList(mixed $list): array
    {
        if (!is_array($list)) {
            return [];
        }
        krsort($list);
        return array_values($list);
    }

    /**
     * 国家成员 uid（country_model->get_member_list）
     *
     * @return list<int>
     */
    private static function memberUids(int $country): array
    {
        if ($country <= 0) {
            return [];
        }
        return array_map(static fn(array $row): int => (int)$row['uid'], CountryHandler::memberList($country, 1, 0));
    }

    /**
     * 按城池 id 查询城池的占领者和挑战者（get_castellan_by_id($id)）
     *
     * @return array<string, int|string>|null
     */
    private static function castellan(int $maplevel): ?array
    {
        $row = DB::table('country_city')->select(...self::CITY_COLUMNS)->where('maplevel', $maplevel)->whereNull('deleted_at')->first();
        return $row === null ? null : self::castCity($row);
    }

    /**
     * 按城池 id 数组查询（get_castellan_by_id(array)），按 maplevel 索引
     *
     * @param list<int> $ids
     * @return array<int, array<string, int|string>>
     */
    private static function castellansByIds(array $ids): array
    {
        $return = [];
        if ($ids === []) {
            return $return;
        }
        foreach (DB::table('country_city')->select(...self::CITY_COLUMNS)->whereIn('maplevel', $ids)->whereNull('deleted_at')->get() as $row) {
            $row = self::castCity($row);
            $return[$row['maplevel']] = $row;
        }
        return $return;
    }

    /**
     * 本国占领或申请占领的城（get_castellan_by_id($country, 4)：cid = 国家 OR challengercid = 国家，取第一行）
     *
     * @return array<string, int|string>|null
     */
    private static function castellanByCountry(int $country): ?array
    {
        $rows = DB::query(
            'SELECT `' . implode('`, `', self::CITY_COLUMNS) . '` FROM `zc_sanguo_country_city`
             WHERE (`cid` = :cid OR `challengercid` = :challenger) AND `deleted_at` IS NULL LIMIT 1',
            ['cid' => $country, 'challenger' => $country]
        );
        return $rows === [] ? null : self::castCity($rows[0]);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, int|string>
     */
    private static function castCity(array $row): array
    {
        foreach ($row as $key => $value) {
            $row[$key] = in_array($key, ['challengername', 'name'], true) ? (string)$value : (int)$value;
        }
        return $row;
    }

    /**
     * 修改挑战者信息（siege_model->update_castellan：不传国家时清空挑战者）
     *
     * @param array<string, mixed>|null $info 挑战国（country 行）
     */
    private static function updateCastellan(int $level, ?array $info, int $now): void
    {
        if ($info === null) {
            $data = ['challengercid' => 0, 'challengername' => '', 'challengerlevel' => 0, 'challengerdateline' => 0];
        } else {
            $data = [
                'challengercid' => (int)($info['id'] ?? 0),
                'challengername' => (string)($info['name'] ?? ''),
                'challengerlevel' => (int)($info['level'] ?? 0),
                'challengerdateline' => $now,
            ];
        }
        DB::table('country_city')->where('maplevel', $level)->whereNull('deleted_at')->update($data);
    }

    /**
     * 占领者资料清空（siege_model->clear_occupant），原占领国的都城状态清 0
     */
    private static function clearOccupant(int $level): void
    {
        $result = self::castellan($level);
        if ($result === null) {
            return;
        }
        DB::table('country_city')->where('maplevel', $level)->whereNull('deleted_at')->update(['cid' => 0, 'name' => '', 'level' => 0]);
        if ($result['cid'] !== 0) {
            self::updateCityStatus($result['cid']);
        }
    }

    /**
     * 占领成功，把挑战者资料挪到占领者（siege_model->update_occupant）
     */
    private static function updateOccupant(int $level, int $now): void
    {
        $result = self::castellan($level);
        if ($result === null) {
            return;
        }
        DB::table('country_city')->where('maplevel', $level)->whereNull('deleted_at')->update([
            'cid' => $result['challengercid'],
            'name' => $result['challengername'],
            'level' => $result['challengerlevel'],
            'dateline' => $now,
            'challengercid' => 0,
            'challengername' => '',
            'challengerlevel' => 0,
            'challengerdateline' => 0,
        ]);
        // 把都城状态修改了
        if ($result['cid'] !== 0) {
            self::updateCityStatus($result['cid']);
        }
        self::updateCityStatus($result['challengercid'], $result['maplevel'], 1);
    }

    /**
     * 修改国家都城状态（siege_model->update_city_status）
     */
    private static function updateCityStatus(int $cid, int $city = 0, int $status = 0): void
    {
        DB::table('country')->where('id', $cid)->whereNull('deleted_at')->update(['city' => $city, 'city_status' => $status]);
    }

    /**
     * 修改玩家参加国战的状态（siege_model->update_war_status）
     */
    private static function updateWarStatus(int $uid, int $city = 0, int $status = 0): void
    {
        DB::table('member_country')->where('uid', $uid)->whereNull('deleted_at')->update(['city' => $city, 'warstatus' => $status]);
    }

    /**
     * 至尊攻城锁定城池（siege_model->lock_city）
     */
    private static function lockCity(int $city, int $status): void
    {
        DB::table('country_city')->where('maplevel', $city)->whereNull('deleted_at')->update(['lock' => $status]);
    }

    /**
     * 按城池 id 获取最近的国战战报（siege_model->get_report）
     *
     * @return array{id: int, city: int, report: string, dateline: int}|null
     */
    private static function getReport(int $city): ?array
    {
        $row = DB::table('country_city_report')->select('id', 'city', 'report', 'dateline')->where('city', $city)
            ->whereNull('deleted_at')->orderBy('dateline', 'DESC')->orderBy('id', 'DESC')->limit(1)->first();
        if ($row === null) {
            return null;
        }
        return ['id' => (int)$row['id'], 'city' => (int)$row['city'], 'report' => (string)$row['report'], 'dateline' => (int)$row['dateline']];
    }

    /**
     * 加 Redis 锁，已被占用时最多等 $wait 秒
     */
    private static function waitLock(string $key, int $ttl, int $wait): bool
    {
        $redis = Redis::instance();
        if ($redis === null) {
            return false;
        }
        $deadline = microtime(true) + $wait;
        do {
            if ($redis->set($key, '1', ['nx', 'ex' => $ttl])) {
                return true;
            }
            usleep(20000);
        } while (microtime(true) < $deadline);
        return false;
    }
}
