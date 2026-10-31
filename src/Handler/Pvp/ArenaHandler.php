<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Pvp;

use Hanjiang\Battle\BattleEngine;
use Hanjiang\Battle\TeamBuilder;
use Hanjiang\Handler\Activity\MessageHandler;
use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\General\TavernHandler;
use Hanjiang\MasterData\ArenaExchange;
use Hanjiang\MasterData\ArenaRankreward;
use Hanjiang\MasterData\Equip;
use Hanjiang\MasterData\EquipEssence;
use Hanjiang\MasterData\Gem;
use Hanjiang\MasterData\General as GeneralTemplate;
use Hanjiang\MasterData\MountTemp;
use Hanjiang\MasterData\Price;
use Hanjiang\MasterData\Skill;
use Hanjiang\MasterData\Soul;
use Hanjiang\MasterData\SoulType;
use Hanjiang\MasterData\Talent;
use Hanjiang\Model\Chat;
use Hanjiang\Model\Equipment;
use Hanjiang\Model\General;
use Hanjiang\Model\Member;
use Hanjiang\Model\MemberIntegral;
use Hanjiang\Model\MemberJade;
use Hanjiang\Model\MemberStone;
use Hanjiang\Model\OwnGem;
use Hanjiang\Model\OwnMount;
use Hanjiang\Model\OwnSoul;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 演武榜（移植旧版 controllers/arena.php + models/arena_model.php，路径相对旧版 s1/application/）
 *
 * 对手全部是玩家：主公升到 26 级（或 26 级以上首次打开演武榜）时以「当前最后一名 + 1」入榜，
 * 可挑战的对手按 get_member_rank 从排名表里取，旧版没有 NPC / 机器人守榜。
 *
 * 客户端调用：index、action、buy_pk_num、get_reward、get_reward_list、shop_index、shop_exchange、
 * send_to_chat（arena / 切磋 / 国家战报分享）；clear_cd 在 arenapop.js 中已注释，照旧版保留。
 */
final class ArenaHandler
{
    /** 竞技场开放等级 */
    public const int OPEN_LEVEL = 26;

    /** 每日更新挑战次数（旧版 arena_model->_times） */
    private const int DAILY_TIMES = 15;

    /** 首次入榜的挑战次数（旧版 get_info_by_id 首次插入写死 10） */
    private const int FIRST_TIMES = 10;

    /** 排行需要显示的人数 */
    private const int TOP_NUMBER = 5;

    /** get_member 每次查询的角色数量 */
    private const int MEMBER_NUMBER = 5;

    /** 竞技场对战 cd（旧版 $cd_time = '0'） */
    private const int CD_TIME = 0;

    /** 战报分享到聊天室的元宝花费 */
    private const int SHARE_PRICE = 2;

    private const string CD_KEY = 'arena_cd_';
    private const string REWARD_KEY = 'arena_reward_';
    private const string TOP_USER_KEY = 'arena_topuser';
    private const string ARENA_TOP_KEY = 'arena_top_30';
    private const string BATTLE_KEY = 'arena_battle_';
    private const string ACTION_LOCK = 'action_lock_';
    private const string EXCHANGE_LOCK = 'arena_exchange_';

    /** 旧版 zc_sanguo_arena 的字段（响应里只带这些，不带新版的三个时间字段） */
    private const array ARENA_COLUMNS = ['id', 'uid', 'username', 'win', 'rank', 'total_rank', 'times', 'dateline', 'get_time'];

    /** 演武信件类型（旧版 message_model->send_message type 4） */
    private const int MESSAGE_TYPE_ARENA = 4;

    private const string LANG_MESSAGE_ARENA_WIN = '在演武榜向您挑战，被您打的落花流水。';
    private const string LANG_MESSAGE_ARENA_LOSE = '在演武榜将您击败，排名跌落至第%s名';
    private const string LANG_LEVEL_ERROR = '等级未到，不能开放';
    private const string LANG_SHOP_LIST_ERROR = '积分商城数据表错误';
    private const string LANG_PARAM_ERROR = '传递参数错误';
    private const string LANG_FAST_ERROR = '积分兑换操作太快了';
    private const string LANG_EXCHNUM_ERROR = '兑换次数不足';
    private const string LANG_EXCHINT_ERROR = '兑换积分不足';
    private const string LANG_GENERAL_ERROR = '武将已招募';

    /** 装备精华奖励类型 → 精华模板 id（旧版 get_add_reward case 17 ~ 25） */
    private const array ESSENCE_ID = [17 => 1, 18 => 7, 19 => 13, 20 => 2, 21 => 8, 22 => 14, 23 => 3, 24 => 9, 25 => 15];

    /** 兑换发放物品的流水执行类与方法（旧版 c=arena&m=shop_exchange） */
    private const string LOG_CLASS = 'arena';
    private const string LOG_METHOD_EXCHANGE = 'shop_exchange';

    /** 将魂品质 → 最高等级（旧版 soul_model->get_levelup_rule） */
    private const array SOUL_LEVEL_RULE = [1 => 7, 2 => 9, 3 => 11, 4 => 13, 5 => 15, 6 => 20];

    /**
     * 初试化查看竞技场数据: c=arena&m=index
     *
     * status：-1 等级未到；1 今日已领奖（显示当前排名奖励，明日可领取）；2 显示昨日排名奖励（可领取）
     */
    public static function index(Context $ctx): array
    {
        $now = time();
        $return = [
            'status' => 0,
            'list' => [],
        ];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null || (int)$member['level'] < self::OPEN_LEVEL) {
            // 等级未到，不能开放
            $return['status'] = -1;
            return $return;
        }
        $uid = (int)$member['uid'];
        $log = self::getInfoById($member, $now);
        // 取 redis 中挑战 cd
        $cd = Redis::get(self::CD_KEY . $uid);
        // 用户信息
        $return['log'] = $log;
        // 排名前五的用户
        $return['top_list'] = self::topList();

        // 查询奖励（根据时间判断最后领取时间取排名信息）
        if (date('ymd', (int)$log['get_time']) === date('ymd', $now)) {
            // 显示当前排名奖励(明日可领取)
            $return['status'] = 1;
            $reward = self::getRankrewardByRank((int)$log['rank']);
        } else {
            // 显示昨日排名奖励(可领取)
            $return['status'] = 2;
            $reward = [];
            $getReward = Redis::get(self::REWARD_KEY . $uid);
            if (is_array($getReward)) {
                $reward['reward'] = $getReward;
            }
            if ($reward === [] || date('ymd', (int)$reward['reward']['dateline']) !== date('ymd', $now)) {
                $rank = self::getRankByTime($uid, $now) ?? (int)$log['rank'];
                $reward = self::getRankrewardByRank($rank);
                // 奖励存进 redis
                $data = $reward['reward'];
                $data['dateline'] = $now;
                Redis::set(self::REWARD_KEY . $uid, $data, 86400);
            }
        }

        // 依赖未移植：edifice_model->add_edifice_log(3) 首次打开操作记录

        // 奖励领取倒计时
        $return['end_time'] = strtotime(date('y-m-d', $now)) + 86400 - $now;
        $return['reward'] = $reward['reward'];
        // 根据用户等级查询出几个排名相邻角色
        $return['list'] = self::getMemberRank((int)$log['rank']);
        $return['log']['cd'] = max(0, (int)(is_array($cd) ? $cd['dateline'] : 0) - $now);
        // 演武榜积分
        $return['log']['act_arena_int'] = self::nowArenaInt($uid);
        return $return;
    }

    /**
     * 查询奖励列表: c=arena&m=get_reward_list
     *
     * status：-1 奖励表为空；1 成功
     */
    public static function get_reward_list(Context $ctx): array
    {
        $list = array_values(ArenaRankreward::ROWS);
        if ($list === []) {
            return ['status' => -1];
        }
        return ['status' => 1, 'list' => $list];
    }

    /**
     * 领取奖励: c=arena&m=get_reward
     *
     * status：-1 没有可领取的奖励；1 成功
     */
    public static function get_reward(Context $ctx): array
    {
        $now = time();
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return ['status' => -1];
        }
        $uid = (int)$member['uid'];
        // 取 redis 中的奖励信息（index 显示昨日排名奖励时写入）
        $reward = Redis::get(self::REWARD_KEY . $uid);
        if (!is_array($reward) || date('ymd', (int)$reward['dateline']) !== date('ymd', $now)) {
            // 没有可领取的奖励
            return ['status' => -1];
        }
        // 修改最后领取奖励时间，删除 redis 奖励记录
        DB::table('arena')->where('uid', $uid)->whereNull('deleted_at')->update(['get_time' => $now]);
        Redis::del(self::REWARD_KEY . $uid);

        // 更新玩家演武榜积分
        $memint = self::changeMemint($uid, (int)$reward['intnum'], 'act_arena_int', 1, 'get_reward', $now);

        $return = ['status' => 1];
        $return['reward'] = $reward;
        $return['all_silver'] = (int)$member['silver'];
        // 奖励领取倒计时
        $return['end_time'] = strtotime(date('y-m-d', $now)) + 86400 - $now;
        // 查询最新奖励
        $log = self::getInfoById($member, $now);
        $return['new_reward'] = self::getRankrewardByRank((int)$log['rank'])['reward'];
        $return['act_arena_int'] = $memint['act_arena_int'] ?? null;
        return $return;
    }

    /**
     * 购买挑战次数: c=arena&m=buy_pk_num（t = 1 购买，否则只查价格）
     *
     * status：-1 元宝不足；1 成功
     */
    public static function buy_pk_num(Context $ctx): array
    {
        $now = time();
        $type = (int)$ctx->input('t', 0);
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return ['status' => -1];
        }
        $uid = (int)$member['uid'];
        // 查询今日购买次数
        $number = self::getTimes($uid, $now);
        // 得到购买需要的价格
        $price = self::actPrice($number['times'] + 1);
        $return = ['status' => 1, 'price' => $price];
        if ($type === 1) {
            // 判断金币是否足够
            if ((int)$member['gold'] < $price) {
                $return['status'] = -1;
                return $return;
            }
            // 扣除金币（依赖未移植：gold_log 元宝流水）
            if (!Member::deductCurrency($uid, gold: $price)) {
                $return['status'] = -1;
                return $return;
            }

            // 修改次数，添加购买记录
            $times = $number['status'] === 0
                ? self::addPayLog($uid, $now)
                : self::addPayLog($uid, $now, $number['times'], $number['total_times']);

            // 取下一次购买花费
            $return['price'] = self::actPrice($number['times'] + 2);
            // 依赖未移植：stat_model->yanwubang_times 消费统计
            // 返回剩余金币和次数
            $return['gold'] = (int)$member['gold'] - $price;
            $return['times'] = $times;
        }
        return $return;
    }

    /**
     * 清除 cd: c=arena&m=clear_cd（t = 1 清除，否则只查价格）
     *
     * 旧版 cd_time 为 0 且 vip_model->cd_status() 恒为 1，挑战从不写 cd，这里照旧版恒返回 -1。
     * status：-2 元宝不足；-1 cd 不需要清除；1 成功
     */
    public static function clear_cd(Context $ctx): array
    {
        $now = time();
        $type = (int)$ctx->input('t', 0);
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return ['status' => -1];
        }
        $uid = (int)$member['uid'];
        $cd = Redis::get(self::CD_KEY . $uid);
        if (!is_array($cd) || ((int)$cd['dateline'] - $now) <= 0) {
            // cd 不需要清除
            return ['status' => -1];
        }
        // 取价格
        $time = max(0, (int)$cd['dateline'] - $now);
        $price = (int)ceil($time / 60);
        $return = ['status' => 1, 'price' => $price];
        if ($type === 1) {
            // 判断金币数量
            if ((int)$member['gold'] < $price || !Member::deductCurrency($uid, gold: $price)) {
                $return['status'] = -2;
                return $return;
            }
            Redis::del(self::CD_KEY . $uid);
            // 依赖未移植：stat_model->yanwubang_cd 消费统计
            $return['gold'] = (int)$member['gold'] - $price;
        }
        return $return;
    }

    /**
     * 挑战: c=arena&m=action（t = 对方 uid）
     *
     * status：-1 挑战次数不足；-2 双方之一正在被挑战（action 锁）/ cd 未清除；-3 对方不在演武榜或排名比我低；
     * -4 不在可挑战名单；-5 对方或自己正在 pk 中；1 成功（info 为结算，battle 为战报）
     */
    public static function action(Context $ctx): array
    {
        $now = time();
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return ['status' => -3];
        }
        $uid = (int)$member['uid'];
        // 对方的id
        $touid = (int)$ctx->input('t', 0);
        // 在打别人的时候，把自己和对手都锁起来
        $touidLock = (int)(Redis::get(self::ACTION_LOCK . $touid) ?? 0);
        $myLock = (int)(Redis::get(self::ACTION_LOCK . $uid) ?? 0);
        if ($touidLock > 0 || $myLock > 0) {
            return ['status' => -2];
        }
        Redis::set(self::ACTION_LOCK . $touid, 1, 30);
        Redis::set(self::ACTION_LOCK . $uid, 1, 30);
        try {
            return self::doAction($member, $touid, $now);
        } finally {
            // 旧版 __destruct：对方 uid > 0 时请求结束清除双方的 action 锁
            if ($touid > 0) {
                Redis::del(self::ACTION_LOCK . $uid, self::ACTION_LOCK . $touid);
            }
        }
    }

    /**
     * 发送聊天室战报: c=arena&m=send_to_chat（touid 对方 uid，report 信件 id，win 战斗评级）
     *
     * status：-1 参数不完整；-2 元宝不足；-3 对方不存在；1 成功
     */
    public static function send_to_chat(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $touid = (int)$ctx->input('touid', 0);
        $report = (int)$ctx->input('report', 0);
        $win = (int)$ctx->input('win', 0);
        $member = self::member($uid);
        // 判断参数是否完整
        if ($touid === 0 || $report === 0 || $member === null) {
            return ['status' => -1];
        }
        // 判断金币是否足够
        if ((int)$member['gold'] < self::SHARE_PRICE) {
            return ['status' => -2];
        }
        // 取用户信息
        $toUser = self::member($touid);
        if ($toUser === null) {
            return ['status' => -3];
        }
        // 扣除金币
        $allGold = Chat::chargeShare($uid, self::SHARE_PRICE);
        if ($allGold === null) {
            return ['status' => -2];
        }
        $sender = Chat::sender($uid);
        $message = Chat::getMessageStr(3, [
            'to' => ['uid' => $touid, 'nickname' => $toUser['nickname']],
            'id' => $report,
            'win' => Chat::winWord($win),
        ], $sender);
        Chat::sendToAll($message, Chat::TYPE_REPORT, $report, 0, $sender);

        return ['status' => 1, 'all_gold' => $allGold];
    }

    /**
     * 积分商城: c=arena&m=shop_index
     */
    public static function shop_index(Context $ctx): array
    {
        /**
         * -2 积分商城数据表错误
         * -1 等级未到，不能开放
         *  0 失败
         *  1 成功
         */
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null || (int)$member['level'] < self::OPEN_LEVEL) {
            return ['status' => -1, 'msg' => self::LANG_LEVEL_ERROR];
        }
        return self::shopList((int)$member['uid'], time());
    }

    /**
     * 积分商城积分兑换: c=arena&m=shop_exchange（id = ArenaExchange.id）
     */
    public static function shop_exchange(Context $ctx): array
    {
        /**
         * -3 积分商城数据表错误
         * -2 传递参数错误
         * -1 等级未到，不能开放
         *  0 失败
         *  1 成功
         *  2 积分兑换操作太快了
         *  3 兑换次数不足
         *  4 武将已招募
         */
        $now = time();
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return ['status' => -1, 'msg' => self::LANG_LEVEL_ERROR];
        }
        $uid = (int)$member['uid'];
        // 防刷接口
        $lockKey = self::EXCHANGE_LOCK . $uid;
        if (Redis::get($lockKey) !== null) {
            return ['status' => 2, 'msg' => self::LANG_FAST_ERROR];
        }
        // 加锁
        Redis::set($lockKey, 1, 30);
        try {
            return self::exchange($member, (int)$ctx->input('id', 0), $now);
        } finally {
            // 解锁
            Redis::del($lockKey);
        }
    }

    /**
     * 根据 uid 查询用户竞技场信息（移植 arena_model->get_info_by_id，只用于当前玩家本人）
     *
     * 跨天时把剩余次数重置为 15；首次进入以「最后一名 + 1」入榜（次数 10、领奖时间为当前时间），同步 member_field.rank。
     * 旧版另在 member_model 升到 26 级时调用（新版 MapHandler 主公升级处调用）。
     *
     * @param array<string, mixed> $member
     * @return array<string, mixed>
     */
    public static function getInfoById(array $member, int $now): array
    {
        $uid = (int)$member['uid'];
        $result = self::getMemberInfo($uid);
        if ($result !== null) {
            if (date('ymd', (int)$result['dateline']) !== date('ymd', $now)) {
                // 更新剩余次数
                DB::table('arena')->where('uid', $uid)->whereNull('deleted_at')->update([
                    'times' => self::DAILY_TIMES,
                    'dateline' => $now,
                ]);
                $result['times'] = self::DAILY_TIMES;
                $result['dateline'] = $now;
            }
            return $result;
        }

        // 首次进入，添加数据 取出最后一名的排行
        $last = DB::table('arena')->select('rank')->whereNull('deleted_at')->orderBy('rank', 'DESC')->limit(1)->first();
        $rank = $last !== null ? (int)$last['rank'] + 1 : 1;
        $data = [
            'uid' => $uid,
            'username' => (string)$member['nickname'],
            'rank' => $rank,
            'times' => self::FIRST_TIMES,
            'total_rank' => $rank,
            'dateline' => $now,
            'get_time' => $now,
        ];
        DB::table('arena')->insert($data);
        // 修改 member_field 中排名信息
        self::updateRank($uid, $rank);
        return $data;
    }

    /**
     * @param array<string, mixed> $member
     * @return array<string, mixed>
     */
    private static function doAction(array $member, int $touid, int $now): array
    {
        $uid = (int)$member['uid'];
        // 取用户信息
        $log = self::getInfoById($member, $now);
        // 判断挑战次数
        if ((int)$log['times'] <= 0) {
            return ['status' => -1];
        }
        // 取挑战 cd，判断挑战 cd 是否清除
        $cd = Redis::get(self::CD_KEY . $uid);
        if (is_array($cd) && (int)$cd['dateline'] - $now > 0) {
            return ['status' => -2];
        }

        $allow = self::getMemberRank((int)$log['rank']) ?? [];
        $uidArr = array_map(static fn(array $value): int => (int)$value['uid'], $allow);
        if (!in_array($touid, $uidArr, true)) {
            // 不可挑战
            return ['status' => -4];
        }

        // 查询该用户是否正在 pk 中
        if (Redis::get(self::BATTLE_KEY . $touid) !== null || Redis::get(self::BATTLE_KEY . $uid) !== null) {
            return ['status' => -5];
        }
        // 添加战斗锁
        Redis::set(self::BATTLE_KEY . $touid, 1, 500);
        Redis::set(self::BATTLE_KEY . $uid, 1, 500);

        // 查询对方信息
        $toLog = self::getMemberInfo($touid);
        // 判断攻击对象的排名是否比我靠前，否者不能开战（同名次即自己，旧版照常开战）
        if ($toLog === null || (int)$toLog['rank'] > (int)$log['rank']) {
            // 错误退出，删除战斗的key
            self::unLock($uid, $touid);
            return ['status' => -3];
        }
        $myRank = (int)$log['rank'];
        $toRank = (int)$toLog['rank'];

        // 调用算法
        $battle = self::battlePk($uid, $touid);
        $assess = $battle['info']['assess'] ?? null;
        // 判断胜负返回
        $rank = $myRank;
        $pmid = null;
        $info = [];
        // 新奖励
        $info['reward_status'] = 0;
        $info['reward'] = [];
        // 历史最高排名
        $info['total_rank'] = (int)$log['total_rank'];
        if ($assess !== null && $assess >= 1) {
            // 胜利处理，（排名，战报）
            $info['rank'] = 0;
            if ($toRank < $myRank) {
                // 修改我的排名，连胜次数，历史最高排名
                self::updateRank($uid, $toRank);
                self::updateMemberRank($uid, $toRank, (int)$log['total_rank'], (int)$log['win']);
                // 修改对方的排名
                self::updateRank($touid, $myRank);
                self::updateMemberRank($touid, $myRank);
                // 发信件
                $pmid = self::sendArenaMessage($member, $touid, sprintf(self::LANG_MESSAGE_ARENA_LOSE, $myRank));
                $info['rank'] = $rank = $toRank;
                // 旧版如此：取的是历史最高排名 + 1
                $info['win_times'] = (int)$log['total_rank'] + 1;
                // 历史最高排名
                if ((int)$log['total_rank'] > $toRank) {
                    $info['total_rank'] = $toRank;
                }
                // 如果奖励已经领取了，根据当前排名查询奖励
                if (date('ymd', (int)$log['get_time']) === date('ymd', $now)) {
                    $info['reward_status'] = 1;
                }
                $info['reward'] = self::getRankrewardByRank($toRank);
                // 修改排行榜
                if ($toRank <= self::TOP_NUMBER || $myRank <= self::TOP_NUMBER) {
                    self::refreshTopUser();
                }
                // 依赖未移植：mainquest_model->task_action(9, get_arena_rank_up(提升名次)) 主线任务「演武榜提升 X 名」
            }
            // 记录 log
            self::addLog($uid, 1, 1, $touid, $toRank, $now);
            self::addLog($uid, 2, 2, $touid, $myRank, $now);
            // 发送战报
            if ($toRank === 1) {
                self::sendPkReport((string)$toLog['username'], $toRank, (int)$pmid, $touid, $uid);
            }
        } else {
            // 失败操作，连胜改成 0
            self::updateMemberRank($uid);
            // 发信件
            $pmid = self::sendArenaMessage($member, $touid, self::LANG_MESSAGE_ARENA_WIN);
            $info['rank'] = $rank;
            $info['win_times'] = 0;
        }

        // 依赖未移植：日常任务 task_model->task_action(10)（主公 41 级以上）
        // 依赖未移植：主线任务 mainquest_model->task_action(8, 1)（征战一次演武榜）

        // 消耗次数
        $times = (int)$log['times'] - 1;
        DB::table('arena')->where('uid', $uid)->whereNull('deleted_at')->update([
            'times' => $times,
            'dateline' => $now,
        ]);
        $info['times'] = $times;
        // 旧版 vip_model->cd_status() 恒为 1（cd 免费），不写 cd
        $return = ['status' => 1];
        // 查询对方职业
        $toMember = self::member($touid);
        $info['gender'] = $toMember !== null ? (int)$toMember['gender'] : null;
        // 根据用户等级查询出几个排名相邻角色
        $info['list'] = self::getMemberRank($rank);
        $info['cd'] = 0;
        $info['win'] = $assess;
        $return['battle'] = $battle['info'] ?? [];
        $return['battle']['left_role'] = [
            'name' => (string)$member['nickname'],
            'level' => (int)$member['level'],
            'gender' => (int)$member['gender'],
        ];
        $return['battle']['right_role'] = [
            'name' => $toMember !== null ? (string)$toMember['nickname'] : null,
            'level' => $toMember !== null ? (int)$toMember['level'] : null,
            'gender' => $toMember !== null ? (int)$toMember['gender'] : null,
        ];

        // 依赖未移植：message_model->record_pk_report 信件战报（from / fromlevel / to / tolevel / report 落库供信件回放）

        // pk 完成，删除战斗的 key
        self::unLock($uid, $touid);

        // 排名前五的用户
        $info['top_list'] = self::topList();
        $info['report'] = $pmid;
        $info['act_arena_int'] = self::nowArenaInt($uid);
        $return['info'] = $info;
        $return['share'] = [
            'id' => $pmid,
            'type' => 'arena',
        ];
        return $return;
    }

    /**
     * PK 战斗（移植 battle_model->battle_pk）
     *
     * 旧版先按左侧 check_user_team 设 status，再被右侧的结果覆盖：只有右侧（被挑战方）-1（没有阵法）/ -3（武将信息错误）
     * 时不战斗（arena 控制器不检查 status，按失败结算、battle 只有双方信息）；任一侧 -2（阵法里没有武将）或左侧 -1 / -3
     * 都按空队伍继续战斗（空队伍直接判负）。
     * 切磋（ChallengeHandler）同样调用本方法。
     *
     * @return array{status: int, info?: array<string, mixed>}
     */
    public static function battlePk(int $uid, int $touid): array
    {
        $left = TeamBuilder::forMember($uid);
        $right = TeamBuilder::forMember($touid, defense: true, self: $touid === $uid);
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
     * 按排名取可挑战的 5 个名次（移植 arena 控制器 get_member_rank）
     *
     * @return array<int, array<string, mixed>>|null 键从 1 开始；一个都查不到时为 null（旧版未定义变量）
     */
    private static function getMemberRank(int $rank): ?array
    {
        // 获取排名配置
        $arr = [
            1 => [1, 2, 3, 4, 5],
            100 => [1, 2, 4, 8, 16],
            1000 => [5, 20, 45, 80, 125],
        ];

        $newarr = 0;
        $newrank = [];
        if ($rank <= 5) {
            $newrank = range(1, 5);
        } else {
            foreach (array_keys($arr) as $key) {
                if ($rank > $key) {
                    $newarr = $key;
                }
            }

            $array = $arr[$newarr];
            $newkey = 1;
            $moreRank = $rank;

            // 循环取出用户排名
            foreach ($array as $step) {
                // 下个人的排名
                $rank = $moreRank - $step;
                foreach (array_keys($arr) as $key) {
                    if ($rank > $key) {
                        $newkey = $key;
                    }
                }
                // 如果下一个人的阶段和现在的阶段不同，则跳出本次循环，给 array 赋新的阶段值，在下一个循环中继续取
                if ($rank < 1) {
                    break;
                }
                $newrank[] = $rank;
                if ($newkey < $newarr) {
                    $array = $arr[$newkey];
                    $newarr = $newkey;
                    $moreRank = $rank;
                    break;
                }
            }
            if (count($newrank) < 5 && $rank > 1) {
                foreach ($array as $step) {
                    $rank = $moreRank - $step;
                    $newrank[] = $rank;
                }
            }
        }

        $randarr = array_slice($newrank, 0, 5);
        $list = self::getInfoByRank($randarr);
        if ($list === []) {
            return null;
        }
        $uidArr = array_map(static fn(array $val): int => (int)$val['uid'], $list);
        // 查询角色用户名和等级
        $memberList = [];
        foreach (DB::table('member')->select('uid', 'gender', 'level')->whereIn('uid', $uidArr)->whereNull('deleted_at')->get() as $row) {
            $memberList[(int)$row['uid']] = $row;
        }
        $return = [];
        foreach ($list as $key => $val) {
            $row = $val;
            $m = $memberList[(int)$val['uid']] ?? null;
            $row['gender'] = $m !== null ? (int)$m['gender'] : null;
            $row['level'] = $m !== null ? (int)$m['level'] : null;
            unset($row['get_time'], $row['dateline'], $row['total_rank'], $row['times'], $row['win']);
            $return[$key] = $row;
        }
        return $return;
    }

    /**
     * 按名次查询用户信息（移植 arena_model->get_info_by_rank）
     *
     * status 照旧版恒为 0：旧版拿单个名次与名次数组比较（int >= array 永远不成立）。
     *
     * @param list<int> $ranks
     * @return array<int, array<string, mixed>> 键从 1 开始
     */
    private static function getInfoByRank(array $ranks): array
    {
        if ($ranks === []) {
            return [];
        }
        $rows = DB::table('arena')->select(...self::ARENA_COLUMNS)
            ->whereIn('rank', $ranks)->whereNull('deleted_at')->orderBy('rank', 'ASC')->get();
        $list = [];
        $key = 1;
        foreach ($rows as $val) {
            $row = self::castArena($val);
            $row['status'] = 0;
            $list[$key++] = $row;
        }
        return $list;
    }

    /**
     * 根据名次查询出几个排名相邻角色（移植 arena_model->get_member）
     *
     * @return array<int, array<string, mixed>> 键从 1 开始，名次从小到大
     */
    private static function getMember(int $rank, int $type = 0): array
    {
        $smallRank = $rank <= 5 ? 6 : $rank;
        $smallRank = min($smallRank, 100000);
        if ($type === 1) {
            $smallRank = $rank;
        }
        $rows = DB::table('arena')->select(...self::ARENA_COLUMNS)
            ->where('rank', '<', $smallRank)->whereNull('deleted_at')
            ->orderBy('rank', 'DESC')->limit(self::MEMBER_NUMBER)->get();
        $list = [];
        $key = 1;
        foreach (array_reverse($rows) as $val) {
            $row = self::castArena($val);
            $row['status'] = (int)$val['rank'] >= $rank ? 1 : 0;
            $list[$key++] = $row;
        }
        return $list;
    }

    /**
     * 取前 5 名（移植 arena_model->get_top_user，缓存 1 天；不足 5 人时每次查库），每项附神树等级 tree
     *
     * @return array<int, array<string, mixed>>|null 键从 1 开始；排行榜为空时为 null（旧版未定义变量）
     */
    private static function topList(): ?array
    {
        $top = Redis::get(self::TOP_USER_KEY);
        if (!is_array($top) || count($top) < self::TOP_NUMBER) {
            $list = self::refreshTopUser();
        } else {
            $list = $top;
        }
        if ($list === null) {
            return null;
        }
        foreach ($list as $key => $val) {
            $list[$key]['tree'] = 0;
        }
        // 依赖未移植：神树活动（activity_model->activity_status('sacredtree') / sacredtree_model->get_tree_by_uid），tree 恒为 0
        return $list;
    }

    /**
     * 查库重建前 5 名缓存（旧版 get_top_user / action 里的 get_member(6, 1)）
     *
     * @return array<int, array{uid: int, username: string}>|null
     */
    private static function refreshTopUser(): ?array
    {
        $list = null;
        foreach (self::getMember(self::TOP_NUMBER + 1, 1) as $key => $val) {
            $list[$key] = ['uid' => $val['uid'], 'username' => $val['username']];
        }
        Redis::set(self::TOP_USER_KEY, $list, 86400);
        return $list;
    }

    /**
     * 根据排名取奖励（移植 arena_model->get_rankreward_by_rank：取名次门槛不大于排名的最后一档）
     *
     * @return array{status: int, reward?: array<string, mixed>}
     */
    private static function getRankrewardByRank(int $rank): array
    {
        $rows = ArenaRankreward::ROWS;
        if ($rows === []) {
            return ['status' => 0];
        }
        $reward = [];
        foreach ($rows as $val) {
            if ($rank >= $val['rank']) {
                $reward = $val;
            }
            unset($reward['id'], $reward['rank']);
        }
        return ['status' => 1, 'reward' => $reward];
    }

    /**
     * 取昨日挑战排名（移植 arena_model->get_rank_by_time：今日 0 点前最后一条挑战记录的名次）
     */
    private static function getRankByTime(int $uid, int $now): ?int
    {
        $row = DB::table('arena_log')->select('rank')->where('uid', $uid)
            ->where('dateline', '<', strtotime(date('y-m-d', $now)))->whereNull('deleted_at')
            ->orderBy('dateline', 'DESC')->limit(1)->first();
        return $row !== null ? (int)$row['rank'] : null;
    }

    /**
     * 添加挑战记录（移植 arena_model->add_log：type 1 攻击方记自己，2 防守方记对方）
     */
    private static function addLog(int $uid, int $type, int $win, int $touid, int $rank, int $now): void
    {
        DB::table('arena_log')->insert([
            'uid' => $type === 1 ? $uid : $touid,
            'type' => $type,
            'win' => $win,
            'rank' => $rank,
            'userid' => $type === 1 ? $touid : $uid,
            'dateline' => $now,
        ]);
    }

    /**
     * 修改用户排名（移植 arena_model->update_member_rank）
     *
     * 连胜 win 照旧版：原连胜大于 1 才加 1，否则清 0（旧版首胜不计，连胜实际一直为 0）。
     */
    private static function updateMemberRank(int $uid, int $rank = 0, int $totalRank = 0, int $win = 0): void
    {
        $data = [];
        if ($rank > 0) {
            $data['rank'] = $rank;
        }
        // 历史最高排名
        if ($rank > 0 && $rank < $totalRank) {
            $data['total_rank'] = $rank;
        }
        // 连胜次数
        $data['win'] = $win > 1 ? $win + 1 : 0;
        $affected = DB::table('arena')->where('uid', $uid)->whereNull('deleted_at')->update($data);
        if ($affected > 0) {
            // 清除演武缓存（arena_model->clear_arena_top）
            Redis::del(self::ARENA_TOP_KEY);
        }
    }

    /**
     * 修改 member_field 中 rank 信息（移植 arena_model->update_rank）
     */
    private static function updateRank(int $uid, int $rank): void
    {
        DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->update(['rank' => $rank]);
        Redis::del('player:' . $uid);
    }

    /**
     * 删除双方战斗锁（移植 arena_model->un_lock）
     */
    private static function unLock(int $uid, int $touid): void
    {
        Redis::del(self::BATTLE_KEY . $touid, self::BATTLE_KEY . $uid);
    }

    /**
     * 演武信件（旧版 message_model->send_message type 4：发件人为挑战者本人）
     *
     * @param array<string, mixed> $member
     */
    private static function sendArenaMessage(array $member, int $touid, string $text): int
    {
        $message = '<em uid="' . (int)$member['uid'] . '">' . $member['nickname'] . '</em>' . $text;
        return MessageHandler::sendSystemMessage([$touid], self::MESSAGE_TYPE_ARENA, (string)$member['nickname'], $message, 0, (int)$member['uid']);
    }

    /**
     * 查询今日购买次数（移植 arena_model->get_times，不是今天买的 times 为 0）
     *
     * @return array{status: int, times: int, total_times: int}
     */
    private static function getTimes(int $uid, int $now): array
    {
        $return = ['status' => 0, 'times' => 0, 'total_times' => 0];
        $row = DB::table('arena_paylog')->where('uid', $uid)->whereNull('deleted_at')->first();
        if ($row !== null) {
            $return['status'] = 1;
            if (date('ymd', (int)$row['dateline']) === date('ymd', $now)) {
                $return['times'] = (int)$row['times'];
            }
            $return['total_times'] = (int)$row['total_times'];
        }
        return $return;
    }

    /**
     * 增加购买记录并给挑战次数 + 1（移植 arena_model->add_pay_log）
     *
     * @return int 购买后的挑战次数（旧版写入失败时为 0）
     */
    private static function addPayLog(int $uid, int $now, int $times = 0, int $totalTimes = 0): int
    {
        if ($times === 0 && $totalTimes === 0) {
            $affected = DB::table('arena_paylog')->insert([
                'uid' => $uid,
                'times' => 1,
                'total_times' => 1,
                'dateline' => $now,
            ]) !== false ? 1 : 0;
        } else {
            $affected = DB::table('arena_paylog')->where('uid', $uid)->whereNull('deleted_at')->update([
                'times' => $times + 1,
                'total_times' => $totalTimes + 1,
                'dateline' => $now,
            ]);
        }
        if ($affected <= 0) {
            return 0;
        }
        // 查询用户记录（不做跨天重置）
        $log = self::getMemberInfo($uid);
        $newTimes = (int)($log['times'] ?? 0) + 1;
        DB::table('arena')->where('uid', $uid)->whereNull('deleted_at')->update(['times' => $newTimes]);
        return $newTimes;
    }

    /**
     * 第 n 次购买的价格（移植 shop_model->get_act_price：50 次起固定 50 元宝）
     */
    private static function actPrice(int $num): int
    {
        if ($num >= 50) {
            return 50;
        }
        return (int)(Price::ROWS[$num]['act'] ?? 0);
    }

    /**
     * 商城列表与兑换状态（移植 shop_index($type)）
     *
     * @return array<string, mixed>
     */
    private static function shopList(int $uid, int $now): array
    {
        // 获取商城商品兑换信息
        $shopList = array_values(ArenaExchange::ROWS);
        if ($shopList === []) {
            return ['status' => -2, 'msg' => self::LANG_SHOP_LIST_ERROR];
        }
        // 每日刷新 / 限量的已兑换次数
        $numInfo1 = self::getExchlogNum($uid, 1, $now);
        $numInfo2 = self::getExchlogNum($uid, 2, $now);
        // 获取当前玩家积分
        $nowInt = self::nowArenaInt($uid);
        // 处理积分兑换状态
        foreach ($shopList as &$val) {
            $val['is_exch'] = 0; // 不可兑换
            $val['exch_num'] = 0; // 剩余兑换次数
            $exchtimes = 0;
            if ($val['type'] === 1) {
                $exchtimes = $numInfo1[$val['id']] ?? 0;
            } elseif ($val['type'] === 2) {
                $exchtimes = $numInfo2[$val['id']] ?? 0;
            }
            if (($val['type'] === 1 || $val['type'] === 2) && $val['num'] > $exchtimes) {
                if ($nowInt >= $val['use_int']) {
                    $val['is_exch'] = 1;
                }
                $val['exch_num'] = $val['num'] - $exchtimes;
            }
            // 奖励信息处理
            if (in_array($val['re_type'], [8, 12, 26, 27, 28], true) || ($val['re_type'] >= 17 && $val['re_type'] <= 25)) {
                $info = self::addReward($uid, $val, false);
                $val['info'] = $info['info'] ?? null;
            }
        }
        unset($val);
        return [
            'status' => 1,
            'list' => $shopList,
            'act_arena_int' => $nowInt,
        ];
    }

    /**
     * @param array<string, mixed> $member
     * @return array<string, mixed>
     */
    private static function exchange(array $member, int $id, int $now): array
    {
        $uid = (int)$member['uid'];
        // 验证玩家等级
        if ((int)$member['level'] < self::OPEN_LEVEL) {
            return ['status' => -1, 'msg' => self::LANG_LEVEL_ERROR];
        }
        // 参数接收
        if ($id <= 0) {
            return ['status' => -2, 'msg' => self::LANG_PARAM_ERROR];
        }
        // 获取商城商品兑换信息
        $shopList = ArenaExchange::ROWS[$id] ?? null;
        if ($shopList === null) {
            return ['status' => -3, 'msg' => self::LANG_SHOP_LIST_ERROR];
        }
        // 获取产品剩余兑换次数（为 0 时，无限兑换）
        if ($shopList['num'] > 0) {
            $exchNum = self::getExchlogNum($uid, $shopList['type'], $now, $id);
            if (isset($exchNum[$id]) && $shopList['num'] <= $exchNum[$id]) {
                return ['status' => 3, 'msg' => self::LANG_EXCHNUM_ERROR];
            }
        }
        // 获取当前玩家积分
        $nowInt = self::nowArenaInt($uid);
        // 验证积分是否足够
        if ($nowInt < $shopList['use_int']) {
            return ['status' => 3, 'msg' => self::LANG_EXCHINT_ERROR];
        }
        // 如果兑换武将已经存在，提示已经招募
        if ($shopList['re_type'] === 12 && self::ownsGeneral($uid, $shopList['re_value1'])) {
            return ['status' => 4, 'msg' => self::LANG_GENERAL_ERROR];
        }
        // 扣除积分
        $lastInt = $nowInt - $shopList['use_int'];
        // 更新商品剩余兑换次数
        DB::table('arena_exchange_log')->insert([
            'uid' => $uid,
            'pid' => $id,
            'ptype' => $shopList['type'],
            'dateline' => $now,
        ]);
        // 更新积分信息
        DB::table('member_integral')->where('uid', $uid)->whereNull('deleted_at')->update(['act_arena_int' => $lastInt]);
        // 添加积分变化日志
        self::addMemintLog($uid, 2, $shopList['use_int'], $lastInt, 'act_arena_int', 'shop_exchange', $now);
        // 发送兑换奖励
        self::addReward($uid, $shopList, true);
        // 获取商城产品列表和当前积分信息
        $shopInfo = self::shopList($uid, $now);
        // 奖励信息处理
        $reward = [];
        foreach ($shopInfo['list'] as $value) {
            if ($value['id'] === $id) {
                $reward = $value;
            }
        }
        return [
            'status' => 1,
            'list' => $shopInfo['list'],
            'reward' => $reward,
            'act_arena_int' => $shopInfo['act_arena_int'],
        ];
    }

    /**
     * 奖励信息 / 发放奖励（移植 get_add_reward）
     *
     * @param array<string, mixed> $reward ArenaExchange 行
     * @param bool $isCheck false 查看信息；true 发放奖励
     * @return array<string, mixed>
     */
    private static function addReward(int $uid, array $reward, bool $isCheck): array
    {
        $return = [];
        $type = (int)$reward['re_type'];
        $num = (int)$reward['re_value1'];
        $id = 0;
        $level = 0;
        if (in_array($type, [8, 12, 26], true)) { // 装备、武将、坐骑
            $id = $num;
        } elseif ($type >= 17 && $type <= 25) { // 装备精华
            $num = (int)$reward['re_value2'];
        } elseif ($type === 27 || $type === 28) { // 将魂、宝石
            $id = $num;
            $level = (int)$reward['re_value2'];
        }
        switch ($type) {
            case 1: // 元宝
                if ($isCheck) {
                    Member::addCurrency($uid, gold: $num);
                }
                break;
            case 2: // 银币
                if ($isCheck) {
                    Member::addCurrency($uid, silver: $num);
                }
                break;
            case 3: // 黄宝石
            case 4: // 紫宝石
            case 11: // 蓝宝石
                if ($isCheck) {
                    TavernHandler::addJade($uid, match ($type) {
                        3 => 2,
                        4 => 3,
                        default => 1,
                    }, $num, self::LOG_CLASS, self::LOG_METHOD_EXCHANGE);
                }
                break;
            case 6: // 玉石
                if ($isCheck) {
                    MemberJade::add($uid, $num, self::LOG_CLASS, self::LOG_METHOD_EXCHANGE);
                }
                break;
            case 9: // 宝石碎片
                if ($isCheck) {
                    OwnGem::addChip($uid, $num, self::LOG_CLASS, self::LOG_METHOD_EXCHANGE);
                }
                break;
            case 14: // 精炼石
            case 15: // 五彩石
            case 16: // 上古血石
                if ($isCheck) {
                    MemberStone::add($uid, $num, $type - 13, self::LOG_CLASS, self::LOG_METHOD_EXCHANGE);
                }
                break;
            case 5: // 原魂
                if ($isCheck) {
                    Member::addCurrency($uid, soul: $num);
                }
                break;
            case 7: // 声望
                if ($isCheck) {
                    Member::addCurrency($uid, reputation: $num);
                }
                break;
            case 8: // 装备
                $item = Equip::ROWS[$id] ?? null;
                if ($isCheck && $item !== null) {
                    Equipment::addFromTemplate($uid, $id);
                }
                $return['info'] = [
                    'id' => $item['id'] ?? null,
                    'image' => $item['id'] ?? null,
                    'name' => $item['name'] ?? null,
                    'quality' => $item['quality'] ?? null,
                    'type' => $item['type'] ?? null,
                    'needlevel' => $item['needlevel'] ?? null,
                    'power' => $item['power'] ?? null,
                ];
                break;
            case 10: // 突飞卡
                if ($isCheck) {
                    DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->increment('freeleap', $num);
                }
                break;
            case 12: // 武将
                $tpl = GeneralTemplate::ROWS[$id] ?? null;
                if (!$isCheck) {
                    // 查询武将信息（旧版先写 is_exist = 0 又被模板整行覆盖：未拥有时没有 is_exist 字段）
                    $return['info'] = $tpl ?? [];
                    $return['info']['skill'] = $tpl !== null ? (Skill::ROWS[$tpl['skillid']] ?? []) : [];
                    $return['info']['talent'] = $tpl !== null ? (Talent::ROWS[$tpl['talentid']] ?? []) : [];
                    if (self::ownsGeneral($uid, $id)) {
                        $return['info']['is_exist'] = 1;
                    }
                } elseif ($tpl !== null && General::createFromTemplate($uid, $id) !== null) {
                    // 已拥有时旧版折算紫宝石（tavern_model->get_price_by_id），shop_exchange 已先返回 4，这里不会走到
                    $return['info'] = [
                        'status' => 1,
                        'type' => $tpl['type'],
                        'generalid' => $tpl['id'],
                        'name' => $tpl['name'],
                        'image' => $tpl['image'],
                        'star' => $tpl['star'],
                        'wuli' => $tpl['wuli'],
                        'zhili' => $tpl['zhili'],
                        'tili' => $tpl['tili'],
                        'skill' => Skill::ROWS[$tpl['skillid']] ?? [],
                        'talent' => Talent::ROWS[$tpl['talentid']] ?? [],
                    ];
                    // 依赖未移植：tavern_model->add_pub_log(gid, 1, 11) 酒馆获得日志
                }
                break;
            case 13: // 军令
                if ($isCheck) {
                    Member::addCurrency($uid, act: $num);
                }
                break;
            case 17: case 18: case 19: case 20: case 21: case 22: case 23: case 24: case 25: // 装备精华
                if ($isCheck && isset(EquipEssence::ROWS[self::ESSENCE_ID[$type]])) {
                    Equipment::addTowerItem($uid, self::ESSENCE_ID[$type], $num);
                }
                $return['info'] = EquipEssence::ROWS[self::ESSENCE_ID[$type]] ?? [];
                $return['info']['type'] = $type;
                $return['info']['num'] = $num;
                break;
            case 26: // 坐骑
                if ($isCheck) {
                    // 添加坐骑
                    OwnMount::addMount($uid, $id, class: self::LOG_CLASS, method: self::LOG_METHOD_EXCHANGE);
                }
                if (isset(MountTemp::ROWS[$id])) {
                    $return['info'] = MountTemp::ROWS[$id];
                }
                break;
            case 27: // 将魂
                $soul = Soul::ROWS[$id] ?? null;
                if ($isCheck) {
                    OwnSoul::addHeroSoul($uid, $id, $level);
                }
                if ($soul !== null) {
                    $return['info'] = $soul;
                    $return['info']['image'] = $soul['type'] . $soul['quality'];
                    $return['info']['level'] = $level;
                    $return['info']['power'] = self::soulAddition($level, $soul['type'], $soul['quality']);
                }
                break;
            case 28: // 宝石
                $gemInfo = Gem::ROWS[$id] ?? null;
                if ($isCheck && OwnGem::addGem($uid, $id, $level, 1)['status'] === 1) {
                    // 修改宝石格子占用数
                    OwnGem::addGemuse($uid, 1);
                }
                if ($gemInfo !== null) {
                    $return['info'] = [
                        'gem' => $id,
                        'gem_name' => $gemInfo['name'],
                        'gem_type' => $gemInfo['type'],
                        'gem_power' => $gemInfo['level' . $level] ?? null,
                        'gem_level' => $level,
                        'gem_num' => 1,
                        'gem_quality' => $level > 0 ? (int)ceil($level / 4) : 1,
                    ];
                }
                break;
        }
        return $return;
    }

    /**
     * 将魂等级属性（移植 soul_model->get_levelup_rule 的 addition；超过品质上限时旧版没有该字段）
     */
    private static function soulAddition(int $level, int $type, int $quality): int|string|null
    {
        if ($level > (self::SOUL_LEVEL_RULE[$quality] ?? 0)) {
            return null;
        }
        $row = SoulType::ROWS[$type] ?? null;
        if ($row === null) {
            return null;
        }
        $addition = (int)($row['lv' . $level] ?? 0);
        return $type > 3 ? $addition . '%' : $addition;
    }

    private static function ownsGeneral(int $uid, int $generalid): bool
    {
        return DB::table('general')->where('uid', $uid)->where('generalid', $generalid)->whereNull('deleted_at')->first() !== null;
    }

    /**
     * 积分商品兑换次数（移植 arena_model->get_exchlog_num：ptype 1 只算今天，2 不限时间）
     *
     * @return array<int, int> 商品 id => 已兑换次数
     */
    private static function getExchlogNum(int $uid, int $ptype, int $now, int $pid = 0): array
    {
        $query = DB::table('arena_exchange_log')->select('pid')->where('uid', $uid)->where('ptype', $ptype)->whereNull('deleted_at');
        if ($pid > 0) {
            $query->where('pid', $pid);
        }
        if ($ptype <= 1) {
            $query->where('dateline', '>=', strtotime(date('Y-m-d', $now)));
        }
        $return = [];
        foreach ($query->get() as $val) {
            $return[(int)$val['pid']] = ($return[(int)$val['pid']] ?? 0) + 1;
        }
        return $return;
    }

    /**
     * 当前演武榜积分（旧版 get_memint_info 后取 act_arena_int，不大于 0 时为 0）
     */
    private static function nowArenaInt(int $uid): int
    {
        return max(0, (int)self::getMemintInfo($uid)['act_arena_int']);
    }

    /**
     * 玩家积分信息（移植 member_model->get_memint_info：没有时插入默认行；Model\MemberIntegral）
     *
     * @return array<string, mixed>
     */
    private static function getMemintInfo(int $uid): array
    {
        return MemberIntegral::get($uid);
    }

    /**
     * 积分变化（移植 member_model->change_memint：type 1 增加、2 消耗，写入成功才记日志；Model\MemberIntegral）
     *
     * @return array<string, int> 变化后的字段；没有写入时为空
     */
    private static function changeMemint(int $uid, int $num, string $arg, int $type, string $method, int $now): array
    {
        return MemberIntegral::change($uid, $num, $arg, $type, self::LOG_CLASS, $method, $now);
    }

    /**
     * 积分变化记录（移植 member_model->add_memint_log）
     */
    private static function addMemintLog(int $uid, int $type, int $intnum, int $balance, string $argument, string $method, int $now): void
    {
        DB::table('integral_log')->insert([
            'uid' => $uid,
            'type' => $type,
            'intnum' => $intnum,
            'balance' => $balance,
            'class' => 'arena',
            'method' => $method,
            'argument' => $argument,
            'dateline' => $now,
        ]);
    }

    /**
     * 按 uid 查询演武榜记录（移植 arena_model->get_member_info）
     *
     * @return array<string, mixed>|null
     */
    private static function getMemberInfo(int $uid): ?array
    {
        $row = DB::table('arena')->select(...self::ARENA_COLUMNS)->where('uid', $uid)->whereNull('deleted_at')->first();
        return $row !== null ? self::castArena($row) : null;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function castArena(array $row): array
    {
        foreach ($row as $key => $value) {
            $row[$key] = $key === 'username' ? (string)$value : (int)$value;
        }
        return $row;
    }

    /**
     * 发送聊天室战报（_send_pk_report）：「击败了 xx 升至演武榜第 1 名」，type 3 带信件战报 id
     */
    private static function sendPkReport(string $to, int $rank, int $id, int $touid, int $uid): void
    {
        $sender = Chat::sender($uid);
        $message = Chat::getMessageStr(2, [
            'to' => ['uid' => $touid, 'nickname' => $to],
            'rank' => $rank,
            'id' => $id,
        ], $sender);
        Chat::sendToReport($message, $id, $sender);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function member(int $uid): ?array
    {
        if ($uid <= 0) {
            return null;
        }
        return DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
    }
}
