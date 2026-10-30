<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Pvp;

use Hanjiang\Handler\Activity\MessageHandler;
use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\General\TavernHandler;
use Hanjiang\MasterData\ActivityTime;
use Hanjiang\MasterData\CompeteReward;
use Hanjiang\MasterData\Equip;
use Hanjiang\MasterData\MountTemp;
use Hanjiang\MasterData\NewcompeteReward;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\Equipment;
use Hanjiang\Model\Member;
use Hanjiang\Model\PassGeneral;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 群雄逐鹿 / 决战（移植旧版 controllers/compete.php + models/compete_model.php，路径相对旧版 s1/application/）
 *
 * 限时活动（activity_time 名为 compete 的一行，MasterData ActivityTime）：活动期间玩家按排名互相挑战（对手全是玩家），
 * 活动结束前一天（endtime - 86400）停止挑战，之后可领取排名奖励（前 200 名，CompeteReward；活动 type 1「决战」用
 * NewcompeteReward）；活动结束后 10 小时内由 CompeteCron 自动补领。ActivityTime 里 compete 的 endtime 为
 * 2016-12-30，生产环境所有接口返回 -1（活动未开启），同旧版。
 *
 * 玩家主公升到 26 级（MapHandler）或首次打开时以「当前最后一名 + 1」入榜，挑战次数初始 100，旧版不做每日重置。
 *
 * 客户端调用（zhulupop.js / miscpop.js）：index、get_reward_list、get_reward、buy_pk_num、action、send_to_chat。
 *
 * 依赖未移植：
 * - 神树活动（activity_model->activity_status('sacredtree') / sacredtree_model->get_tree_by_uid），top_list 的 tree 恒为 0；
 * - 奖励 6 玉石（member.jade）、8 坐骑（mount_model->add_mount / own_mount）只回报不发放（CompeteReward 每档都有坐骑）；
 * - message_model->record_pk_report 信件战报（pm_report，供信件回放）；
 * - 聊天室战报分享（send_to_chat，见该方法）；元宝流水。
 */
final class CompeteHandler
{
    /** 活动名（activity_time.name） */
    private const string ACTIVITY = 'compete';

    /** 竞技场开放等级 */
    public const int OPEN_LEVEL = 26;

    /** 购买次数价格 */
    private const int PRICE = 2;

    /** 排行需要显示的人数 */
    private const int TOP_NUMBER = 3;

    /** get_member 每次查询的角色数量 */
    private const int MEMBER_NUMBER = 5;

    /** 首次入榜的挑战次数（旧版 get_info_by_id 首次插入写死 100） */
    private const int FIRST_TIMES = 100;

    /** 有奖励的最大排名 */
    private const int REWARD_RANK = 200;

    /** 奖励档位起始名次（旧版 get_reward_list 按奖励表行序对应） */
    private const array REWARD_RANKS = [1, 2, 3, 4, 11, 21, 31, 51, 76, 101];

    /** 战报分享到聊天室的元宝花费 */
    private const int SHARE_PRICE = 2;

    private const string TOP_USER_KEY = 'compete_topuser';
    private const string BATTLE_KEY = 'compete_battle_';

    /** 旧版 zc_sanguo_compete 的字段 */
    private const array COMPETE_COLUMNS = ['uid', 'nickname', 'rank', 'times', 'get_rank', 'dateline'];

    /** 群雄逐鹿信件（旧版 send_message type 12，入库前强制改成 4 以显示查看战报按钮；发件人为挑战者本人） */
    private const int MESSAGE_TYPE = 4;

    private const string LANG_MESSAGE_COMPETE_WIN = '在群雄逐鹿向您挑战，被您打的落花流水。';
    private const string LANG_MESSAGE_COMPETE_LOSE = '在群雄逐鹿将您击败，排名跌落至第%s名。';

    /**
     * 初试化查看竞技场数据: c=compete&m=index
     *
     * status：-1 活动未开启；2 等级不足；-2 用户信息错误；-3 奖励信息错误；1 成功
     */
    public static function index(Context $ctx): array
    {
        $now = BossClock::now();
        $return = [
            'status' => 0,
            'list' => [],
        ];
        $activity = self::activity($now);
        if ($activity === null) {
            // 活动未开启
            $return['status'] = -1;
            return $return;
        }
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null || (int)$member['level'] < self::OPEN_LEVEL) {
            // 等级不足
            $return['status'] = 2;
            return $return;
        }

        // 用户信息
        $log = self::getInfoById($member, $now);
        if ($log === []) {
            $return['status'] = -2;
            return $return;
        }

        // 获取奖励信息
        $reward = self::getRewardByRank((int)$log['rank'], $activity['type']);
        if ((int)$log['rank'] <= self::REWARD_RANK && $reward === []) {
            $return['status'] = -3;
            return $return;
        }
        // 查询奖励详细信息
        $reward = self::rewardDetail($reward);

        // 排名前三的用户
        $topList = self::topList();
        // 根据用户等级查询出几个排名相邻角色
        $list = self::getMemberRank((int)$log['rank']);

        // 如果活动已结束，判断奖励是否已经领取
        $isGet = 0;
        if ($activity['cd'] <= 0 && (int)($log['get_rank'] ?? 0) > 0) {
            $isGet = 1;
        }

        $return['status'] = 1;
        $return['cd'] = $activity['cd'];
        $return['reward'] = $reward;
        $return['is_get'] = $isGet;
        $return['log'] = $log;
        $return['top_list'] = $topList;
        $return['list'] = $list;
        $return['type'] = $activity['type'];
        return $return;
    }

    /**
     * 领取奖励: c=compete&m=get_reward
     *
     * status：-1 活动未开启；2 等级不足；-2 活动未结束；-3 用户信息错误；-4 排名超过 200；-5 已经领取过；
     * -6 奖励信息错误；-7 修改领取状态失败；1 成功
     */
    public static function get_reward(Context $ctx): array
    {
        $now = BossClock::now();
        $return = ['status' => 0];
        $activity = self::activity($now);
        if ($activity === null) {
            $return['status'] = -1;
            return $return;
        }
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null || (int)$member['level'] < self::OPEN_LEVEL) {
            $return['status'] = 2;
            return $return;
        }
        // 活动未结束，无法领取奖励
        if ($activity['cd'] > 0) {
            $return['status'] = -2;
            return $return;
        }
        $uid = (int)$member['uid'];
        $log = self::getInfoById($member, $now);
        if ($log === []) {
            $return['status'] = -3;
            return $return;
        }
        // 排名超过200 没有奖励
        if ((int)$log['rank'] > self::REWARD_RANK) {
            $return['status'] = -4;
            return $return;
        }
        // 判断是否已经领取过奖励
        if ((int)($log['get_rank'] ?? 0) > 0) {
            $return['status'] = -5;
            return $return;
        }
        // 根据排名获取奖励
        $reward = self::getRewardByRank((int)$log['rank'], $activity['type']);
        if ($reward === []) {
            $return['status'] = -6;
            return $return;
        }
        // 修改领取状态（只改 get_rank 仍为 0 的行：多 Worker 并发领取只有一个成功）
        if (self::rewardLog((int)$log['rank'], $uid) !== 1) {
            $return['status'] = -7;
            return $return;
        }

        // 添加奖励
        $reward = self::grantReward($uid, $reward);

        $after = self::member($uid) ?? $member;
        $return['status'] = 1;
        $return['reward'] = $reward;
        $return['all_silver'] = (int)$after['silver'];
        $return['all_gold'] = (int)$after['gold'];
        $return['all_act'] = (int)$after['act'];
        return $return;
    }

    /**
     * 查询奖励列表: c=compete&m=get_reward_list
     *
     * status：-1 活动未开启；2 等级不足；-2 奖励表为空；-3 装备信息错误；-4 坐骑信息错误；1 成功
     */
    public static function get_reward_list(Context $ctx): array
    {
        $now = BossClock::now();
        $return = ['status' => 0];
        $activity = self::activity($now);
        if ($activity === null) {
            $return['status'] = -1;
            return $return;
        }
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null || (int)$member['level'] < self::OPEN_LEVEL) {
            $return['status'] = 2;
            return $return;
        }
        $list = self::getRewardList($activity['type']);
        if ($list === []) {
            $return['status'] = -2;
            return $return;
        }
        $equipIds = $mountIds = [];
        foreach ($list as $val) {
            for ($i = 1; $i <= 4; $i++) {
                if ($val['type' . $i] === 7) {
                    $equipIds[] = $val['value' . $i];
                }
                if ($val['type' . $i] === 8) {
                    $mountIds[] = $val['value' . $i];
                }
            }
        }
        // 查询道具信息（一个都查不到时失败）
        $equip = [];
        foreach ($equipIds as $id) {
            if (isset(Equip::ROWS[$id])) {
                $equip[$id] = Equip::ROWS[$id];
            }
        }
        if ($equipIds !== [] && $equip === []) {
            $return['status'] = -3;
            return $return;
        }
        // 查询坐骑信息
        $mount = [];
        foreach ($mountIds as $id) {
            if (isset(MountTemp::ROWS[$id])) {
                $mount[$id] = MountTemp::ROWS[$id];
            }
        }
        if ($mountIds !== [] && $mount === []) {
            $return['status'] = -4;
            return $return;
        }
        foreach ($list as $key => $val) {
            for ($i = 1; $i <= 4; $i++) {
                if ($val['type' . $i] === 7 && isset($equip[$val['value' . $i]])) {
                    $list[$key]['value' . $i] = $equip[$val['value' . $i]];
                }
                if ($val['type' . $i] === 8 && isset($mount[$val['value' . $i]])) {
                    $list[$key]['value' . $i] = $mount[$val['value' . $i]];
                }
            }
        }
        $return['status'] = 1;
        $return['list'] = $list;
        return $return;
    }

    /**
     * 购买挑战次数: c=compete&m=buy_pk_num（t = 1 购买，否则只查价格）
     *
     * status：-1 活动未开启；2 等级不足；-2 挑战已结束（活动倒计时为 0）；-3 用户信息错误；-4 元宝不足；1 成功
     */
    public static function buy_pk_num(Context $ctx): array
    {
        $now = BossClock::now();
        $return = ['status' => 0];
        $activity = self::activity($now);
        if ($activity === null) {
            $return['status'] = -1;
            return $return;
        }
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null || (int)$member['level'] < self::OPEN_LEVEL) {
            $return['status'] = 2;
            return $return;
        }
        if ($activity['cd'] <= 0) {
            $return['status'] = -2;
            return $return;
        }
        $type = (int)$ctx->input('t', 0);
        $uid = (int)$member['uid'];
        $log = self::getInfoById($member, $now);
        if ($log === []) {
            $return['status'] = -3;
            return $return;
        }
        // 得到购买需要的价格
        $return['status'] = 1;
        $return['price'] = self::PRICE;
        if ($type === 1) {
            // 判断金币是否足够，扣除金币（依赖未移植：gold_log 元宝流水）
            if ((int)$member['gold'] < self::PRICE || !Member::deductCurrency($uid, gold: self::PRICE)) {
                $return['status'] = -4;
                return $return;
            }
            // 增加次数（原子 + 1，旧版写回读到的次数 + 1）
            DB::table('compete')->where('uid', $uid)->whereNull('deleted_at')->increment('times', 1);
            // 取下一次购买花费
            $return['price'] = self::PRICE;
            // 返回剩余金币和次数
            $return['gold'] = (int)$member['gold'] - self::PRICE;
            $return['times'] = (int)$log['times'] + 1;
        }
        return $return;
    }

    /**
     * 挑战: c=compete&m=action（t = 对方 uid）
     *
     * status：-1 活动未开启；2 等级不足；-2 挑战已结束；-3 挑战次数不足；-4 不在可挑战名单；-5 对方或自己正在 pk 中；
     * -6 对方不在榜上；-7 对方排名比我低；1 成功（info 为结算，battle 为战报）
     */
    public static function action(Context $ctx): array
    {
        $now = BossClock::now();
        // 对方的id
        $touid = (int)$ctx->input('t', 0);
        $return = [];
        $activity = self::activity($now);
        if ($activity === null) {
            $return['status'] = -1;
            return $return;
        }
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null || (int)$member['level'] < self::OPEN_LEVEL) {
            $return['status'] = 2;
            return $return;
        }
        if ($activity['cd'] <= 0) {
            $return['status'] = -2;
            return $return;
        }
        $uid = (int)$member['uid'];
        // 取用户信息
        $log = self::getInfoById($member, $now);
        // 判断挑战次数
        if ((int)$log['times'] <= 0) {
            $return['status'] = -3;
            return $return;
        }
        $allow = self::getMemberRank((int)$log['rank']) ?? [];
        $uidArr = array_map(static fn(array $value): int => (int)$value['uid'], $allow);
        if (!in_array($touid, $uidArr, true)) {
            // 不可挑战（可挑战名单里包含自己，旧版如此）
            $return['status'] = -4;
            return $return;
        }
        // 查询该用户是否正在pk中，添加战斗锁（原子加锁，旧版先查后加）
        if (!self::addLock($uid, $touid)) {
            $return['status'] = -5;
            return $return;
        }
        try {
            return self::doAction($member, $log, $touid, $now);
        } finally {
            // pk 完成 / 错误退出，删除战斗的key
            Redis::del(self::BATTLE_KEY . $touid, self::BATTLE_KEY . $uid);
        }
    }

    /**
     * 分享战报: c=compete&m=send_to_chat（touid 对方 uid，report 信件 id，win 战斗评级）
     *
     * status：-1 参数不完整；-2 元宝不足；-3 对方不存在；1 成功
     */
    public static function send_to_chat(Context $ctx): array
    {
        $touid = (int)$ctx->input('touid', 0);
        $report = (int)$ctx->input('report', 0);
        $member = self::member(MemberHandler::resolveUid($ctx));
        // 判断参数是否完整
        if ($touid === 0 || $report === 0 || $member === null) {
            return ['status' => -1];
        }
        // 取用户信息
        if (self::member($touid) === null) {
            return ['status' => -3];
        }
        // 依赖未移植：chat_model->get_message_str(3, to / id / win) + send_message_to_all(…, 3, report) 聊天室战报广播。
        // 旧版在这里先校验元宝(-2)、扣 SHARE_PRICE 再广播；聊天室未移植前广播发不出去，
        // 开发者要求暂不扣元宝，返回 0（失败），客户端提示「暂时无法分享战报」。聊天室移植后恢复旧版流程（花费 SHARE_PRICE）。
        return ['status' => 0];
    }

    /**
     * 活动结束后自动补领排名奖励（旧版 cron.php:get_compete_reward，由 CompeteCron 调用）
     *
     * 旧版：活动未结束 -2；结束超过 10 小时 -3；没有可发放的用户 -4。补发信件在旧版里已注释，不发信。
     *
     * @return int 1 发放完成；其余同旧版状态
     */
    public static function settleRewards(int $now): int
    {
        // 查询活动信息（get_activity_by_start：不论是否在活动期）
        $activity = self::activityRow();
        if ($activity === null) {
            return -1;
        }
        // 判断活动是否结束
        if ($activity['endtime'] > $now) {
            return -2;
        }
        // 判断是否可发放奖励（活动已结束，并且在活动结束后 10 个小时内）
        if ($activity['endtime'] + 36000 < $now) {
            return -3;
        }
        $rows = DB::table('compete')->select(...self::COMPETE_COLUMNS)->where('rank', '<=', self::REWARD_RANK)
            ->where('get_rank', 0)->whereNull('deleted_at')->get();
        if ($rows === []) {
            // 没有可发放的用户
            return -4;
        }
        $type = $activity['type'] === 1 ? 1 : 0;
        foreach ($rows as $val) {
            $uid = (int)$val['uid'];
            $reward = self::getRewardByRank((int)$val['rank'], $type);
            if ($reward === []) {
                continue;
            }
            // 修改领取状态（与玩家手动领取互斥）
            if (self::rewardLog((int)$val['rank'], $uid) === 1) {
                self::grantReward($uid, $reward);
                // 旧版 message_model->send_message 补发信件已注释，不发信
            }
        }
        return 1;
    }

    /**
     * 玩家入榜（移植 compete_model->get_info_by_id，只用于当前玩家本人）
     *
     * 首次进入以「最后一名 + 1」入榜，挑战次数 100。旧版另在 member_model 升到 26 级时调用（MapHandler）。
     * 名次在一条 INSERT … SELECT 里计算，uid 主键保证并发首次请求只插入一行。
     *
     * @param array<string, mixed> $member
     * @return array<string, mixed> 首次插入时为插入的数据（旧版不含 get_rank）
     */
    public static function getInfoById(array $member, int $now): array
    {
        $uid = (int)$member['uid'];
        $result = self::getMemberInfo($uid);
        if ($result !== null) {
            return $result;
        }
        // 首次进入，添加数据 取出最后一名的排行
        $affected = DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_compete` (`uid`, `nickname`, `rank`, `times`, `dateline`)
             SELECT :uid, :nickname, COALESCE(MAX(`rank`), 0) + 1, :times, :now FROM `zc_sanguo_compete` WHERE `deleted_at` IS NULL',
            ['uid' => $uid, 'nickname' => (string)$member['nickname'], 'times' => self::FIRST_TIMES, 'now' => $now]
        );
        $row = self::getMemberInfo($uid);
        if ($affected > 0 && $row !== null) {
            // 旧版返回插入的数据（没有 get_rank 字段）
            unset($row['get_rank']);
        }
        return $row ?? [];
    }

    /**
     * @param array<string, mixed> $member
     * @param array<string, mixed> $log
     * @return array<string, mixed>
     */
    private static function doAction(array $member, array $log, int $touid, int $now): array
    {
        $uid = (int)$member['uid'];
        $return = [];
        // 查询对方信息
        $toLog = self::getMemberInfo($touid);
        if ($toLog === null) {
            $return['status'] = -6;
            return $return;
        }
        // 判断攻击对象的排名是否比我靠前，否者不能开战
        if ((int)$toLog['rank'] > (int)$log['rank']) {
            $return['status'] = -7;
            return $return;
        }
        $myRank = (int)$log['rank'];
        $toRank = (int)$toLog['rank'];

        // 调用算法
        $battle = ArenaHandler::battlePk($uid, $touid);
        $assess = $battle['info']['assess'] ?? null;

        // 判断胜负返回
        $rank = $myRank;
        $pmid = null;
        $info = [];
        // 新奖励
        $info['reward_status'] = 0;
        $info['reward'] = [];
        if ($assess !== null && $assess >= 1) {
            // 胜利处理，（排名，战报）
            $info['rank'] = 0;
            if ($toRank < $myRank) {
                // 修改我的排名、对方的排名
                self::updateMemberRank($uid, $toRank);
                self::updateMemberRank($touid, $myRank);
                // 发信件
                $pmid = self::sendMessage($member, $touid, sprintf(self::LANG_MESSAGE_COMPETE_LOSE, $myRank));
                $info['rank'] = $rank = $toRank;
                // 根据当前排名查询奖励（旧版这里不带活动类型，按 compete_reward 取）
                if ($toRank <= self::REWARD_RANK) {
                    $info['reward_status'] = 1;
                    $info['reward'] = self::rewardDetail(self::getRewardByRank($toRank));
                }
                // 修改排行榜
                if ($toRank <= self::TOP_NUMBER || $myRank <= self::TOP_NUMBER) {
                    self::refreshTopUser();
                }
            }
        } else {
            // 发信件
            $pmid = self::sendMessage($member, $touid, self::LANG_MESSAGE_COMPETE_WIN);
            $info['rank'] = $rank;
        }

        // 消耗次数
        $times = (int)$log['times'] - 1;
        DB::table('compete')->where('uid', $uid)->whereNull('deleted_at')->update([
            'times' => $times,
            'dateline' => $now,
        ]);
        $info['times'] = $times;
        $return['status'] = 1;
        // 查询对方职业
        $toMember = self::member($touid);
        $info['gender'] = $toMember !== null ? (int)$toMember['gender'] : null;
        // 根据用户等级查询出几个排名相邻角色
        $info['list'] = self::getMemberRank($rank);
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

        // 排名前三的用户
        $info['top_list'] = self::topList();
        $info['report'] = $pmid;
        $return['info'] = $info;
        $return['share'] = [
            'id' => $pmid,
            'type' => 'compete',
        ];

        // 记录活动人次
        DB::table('compete_action_log')->insert([
            'uid' => $uid,
            'level' => (int)$member['level'],
            'dateline' => $now,
        ]);
        return $return;
    }

    /**
     * 活动开启信息（构造函数：activity_status('compete')）
     *
     * @return array{type: int, cd: int}|null 未开启为 null；cd 为距挑战结束（endtime - 86400）的秒数
     */
    private static function activity(int $now): ?array
    {
        $info = self::activityRow();
        if ($info === null || $info['starttime'] > $now || $info['endtime'] < $now) {
            return null;
        }
        $end = $info['endtime'] - 86400;
        return [
            'type' => (int)$info['type'],
            'cd' => max(0, $end - $now),
        ];
    }

    /**
     * @return array{id: int, name: string, starttime: int, endtime: int, version: int, type: int}|null
     */
    private static function activityRow(): ?array
    {
        foreach (ActivityTime::ROWS as $row) {
            if ($row['name'] === self::ACTIVITY) {
                return $row;
            }
        }
        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function member(int $uid): ?array
    {
        return DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
    }

    /**
     * 按用户查询信息（compete_model->get_member_info）
     *
     * @return array<string, int|string>|null
     */
    private static function getMemberInfo(int $uid): ?array
    {
        $row = DB::table('compete')->select(...self::COMPETE_COLUMNS)->where('uid', $uid)->whereNull('deleted_at')->first();
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
     * 修改用户排名（compete_model->update_member_rank）
     */
    private static function updateMemberRank(int $uid, int $rank): void
    {
        if ($rank > 0) {
            DB::table('compete')->where('uid', $uid)->whereNull('deleted_at')->update(['rank' => $rank]);
        }
    }

    /**
     * 奖励领取记录（compete_model->reward_log）：get_rank 仍为 0 时写入领奖名次
     */
    private static function rewardLog(int $rank, int $uid): int
    {
        $affected = DB::table('compete')->where('uid', $uid)->where('get_rank', 0)->whereNull('deleted_at')
            ->update(['get_rank' => $rank]);
        return $affected > 0 ? 1 : 0;
    }

    /**
     * 添加战斗锁（compete_model->get_lock / add_lock，500 秒）：双方都没被锁时才加锁成功
     */
    private static function addLock(int $uid, int $touid): bool
    {
        $redis = Redis::instance();
        if ($redis === null) {
            return false;
        }
        if (!$redis->set(self::BATTLE_KEY . $touid, '1', ['nx', 'ex' => 500])) {
            return false;
        }
        if ($touid !== $uid && !$redis->set(self::BATTLE_KEY . $uid, '1', ['nx', 'ex' => 500])) {
            Redis::del(self::BATTLE_KEY . $touid);
            return false;
        }
        return true;
    }

    /**
     * 群雄逐鹿信件（发件人为挑战者本人）
     *
     * @param array<string, mixed> $member
     */
    private static function sendMessage(array $member, int $touid, string $text): int
    {
        $message = '<em uid="' . (int)$member['uid'] . '">' . $member['nickname'] . '</em>' . $text;
        return MessageHandler::sendSystemMessage([$touid], self::MESSAGE_TYPE, (string)$member['nickname'], $message, 0, (int)$member['uid']);
    }

    /**
     * 按排名取可挑战的名次（移植 compete 控制器 get_member_rank：最多 4 个更靠前的名次 + 自己）
     *
     * @return array<int, array<string, mixed>>|null 键从 1 开始；一个都查不到时为 null（旧版未定义变量）
     */
    private static function getMemberRank(int $rank): ?array
    {
        // 获取排名配置
        $arr = [
            1 => [1, 2, 3, 4],
            100 => [1, 2, 4, 8],
            1000 => [5, 20, 45, 80],
        ];

        $newarr = 0;
        $newrank = [];
        $oldRank = $rank;
        if ($rank <= 5) {
            $newrank = range(1, 5);
            $randarr = $newrank;
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
            if (count($newrank) < 4 && $rank > 1) {
                foreach ($array as $step) {
                    $rank = $moreRank - $step;
                    $newrank[] = $rank;
                }
            }

            $randarr = array_slice($newrank, 0, 4);
        }
        $randarr[] = $oldRank;

        // 按名次查询用户信息（compete_model->get_info_by_rank）
        $rows = DB::table('compete')->select(...self::COMPETE_COLUMNS)
            ->whereIn('rank', $randarr)->whereNull('deleted_at')->orderBy('rank', 'ASC')->get();
        if ($rows === []) {
            return null;
        }
        $uidArr = array_map(static fn(array $val): int => (int)$val['uid'], $rows);
        // 查询角色用户名和等级
        $memberList = [];
        foreach (DB::table('member')->select('uid', 'gender', 'level')->whereIn('uid', $uidArr)->whereNull('deleted_at')->get() as $row) {
            $memberList[(int)$row['uid']] = $row;
        }
        $return = [];
        $key = 1;
        foreach ($rows as $val) {
            $row = self::cast($val);
            $row['status'] = $row['rank'] >= $oldRank ? 0 : 1;
            $m = $memberList[$row['uid']] ?? null;
            $row['gender'] = $m !== null ? (int)$m['gender'] : null;
            $row['level'] = $m !== null ? (int)$m['level'] : null;
            unset($row['dateline'], $row['times']);
            $return[$key++] = $row;
        }
        return $return;
    }

    /**
     * 取前 3 名（移植 compete_model->get_top_user，缓存 1 天；不足 3 人时每次查库），每项附神树等级 tree
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
     * 查库重建前 3 名缓存（compete_model->get_member(4, 1)：名次小于 4 的最多 5 人，名次从小到大）
     *
     * @return array<int, array{uid: int, nickname: string}>|null
     */
    private static function refreshTopUser(): ?array
    {
        $rows = DB::table('compete')->select('uid', 'nickname', 'rank')
            ->where('rank', '<', self::TOP_NUMBER + 1)->whereNull('deleted_at')
            ->orderBy('rank', 'DESC')->limit(self::MEMBER_NUMBER)->get();
        $list = null;
        $key = 1;
        foreach (array_reverse($rows) as $val) {
            $list[$key++] = ['uid' => (int)$val['uid'], 'nickname' => (string)$val['nickname']];
        }
        Redis::set(self::TOP_USER_KEY, $list, 86400);
        return $list;
    }

    /**
     * 取排名奖励列表（compete_model->get_reward_list：type 1 决战用 newcompete_reward）
     *
     * @return list<array<string, mixed>> 每行附档位起始名次 rank 与名次区间 rank_str
     */
    private static function getRewardList(int $type = 0): array
    {
        $rows = array_values($type === 1 ? NewcompeteReward::ROWS : CompeteReward::ROWS);
        $return = [];
        foreach ($rows as $key => $val) {
            $start = self::REWARD_RANKS[$key] ?? null;
            $val['rank'] = $start;
            if (isset(self::REWARD_RANKS[$key + 1])) {
                if (self::REWARD_RANKS[$key + 1] - $start > 1) {
                    $val['rank_str'] = $start . '~' . self::REWARD_RANKS[$key + 1];
                } else {
                    $val['rank_str'] = $start;
                }
            } else {
                $val['rank_str'] = $start . '~' . '200';
            }
            $return[$key] = $val;
        }
        return $return;
    }

    /**
     * 根据排名取奖励（compete_model->get_reward_by_rank：取起始名次不大于排名的最后一档，去掉 id / rank）
     *
     * @return array<string, mixed> 排名超过 200 为空
     */
    private static function getRewardByRank(int $rank, int $type = 0): array
    {
        if ($rank > self::REWARD_RANK) {
            return [];
        }
        $reward = [];
        foreach (self::getRewardList($type) as $val) {
            if ($rank >= $val['rank']) {
                $reward = $val;
            }
            unset($reward['id'], $reward['rank']);
        }
        return $reward;
    }

    /**
     * 查询奖励详细信息：7 装备换成装备模板、8 坐骑换成坐骑模板（查不到为 null）
     *
     * @param array<string, mixed> $reward
     * @return array<string, mixed>
     */
    private static function rewardDetail(array $reward): array
    {
        if ($reward === []) {
            return $reward;
        }
        for ($i = 1; $i <= 4; $i++) {
            switch ($reward['type' . $i]) {
                case 7:
                    $reward['value' . $i] = Equip::ROWS[$reward['value' . $i]] ?? [];
                    break;
                case 8:
                    $reward['value' . $i] = MountTemp::ROWS[$reward['value' . $i]] ?? [];
                    break;
            }
        }
        return $reward;
    }

    /**
     * 发放排名奖励（get_reward / cron get_compete_reward 的发放部分）
     *
     * 类型：1 元宝、2 银币、3 紫宝石、4 突飞卡、5 原魂、6 玉石、7 装备（1 件）、8 坐骑（1 匹）
     *
     * @param array<string, mixed> $reward
     * @return array<string, mixed> 7 装备 / 8 坐骑的 value 换成模板信息
     */
    private static function grantReward(int $uid, array $reward): array
    {
        for ($i = 1; $i <= 4; $i++) {
            $value = $reward['value' . $i];
            switch ($reward['type' . $i]) {
                case 1:
                    Member::addCurrency($uid, gold: (int)$value);
                    break;
                case 2:
                    Member::addCurrency($uid, silver: (int)$value);
                    break;
                case 3:
                    TavernHandler::addJade($uid, 3, (int)$value);
                    break;
                case 4:
                    PassGeneral::addFreeleap($uid, (int)$value);
                    break;
                case 5:
                    Member::addCurrency($uid, soul: (int)$value);
                    break;
                case 6:
                    // 依赖未移植：member_model->add_jade 玉石（新服 member 表无 jade 字段）
                    break;
                case 7:
                    if (isset(Equip::ROWS[$value])) {
                        // 添加道具
                        Equipment::addFromTemplate($uid, (int)$value);
                        $reward['value' . $i] = Equip::ROWS[$value];
                    }
                    break;
                case 8:
                    // 依赖未移植：mount_model->add_mount 坐骑（own_mount），只回报坐骑模板不发放
                    $reward['value' . $i] = MountTemp::ROWS[$value] ?? [];
                    break;
            }
        }
        return $reward;
    }
}
