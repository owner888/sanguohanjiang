<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Pvp;

use Hanjiang\Cross\GodServer;
use Hanjiang\Cross\Server;
use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\Chat;
use Hanjiang\Model\God;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 战神殿（移植旧版 s1 controllers/god.php + models/god_model.php；战斗服务器部分见 Cross\GodServer）
 *
 * 客户端 championsworld.js / palaceheroworld.js / championsrewardpop.js / miscpop.js / loginworld.js 调用 index、entry、
 * sync_battle_info、support、get_support、get_report_list、get_reward_list、get_reward、get_report_info、share_report、
 * palace、palace_report、get_reward_pre、loading。60 级以下多数接口不可用，80 级以下不能报名、同步、看历程。
 * 时间（每月 8 ~ 15 日报名、15 日 19 点开战）由战斗服务器按自然月计算，当前时间取 BossClock（生产恒为 time()）。
 *
 * 依赖未移植：战神殿奖励（battle 库 god_reward 在旧版数据里 0 行）——对战界面的胜利奖励 reward 为空、get_reward_list 回 -3、
 * get_reward 回 -2、get_reward_pre 的奖励与武将列表为空（战斗服务器也没有 get_basereward_list）；奖励里的装备 / 坐骑 / 武将
 * 信息补全与发放（get_temp_info / get_temp_by_id / get_info_by_id、add_item / add_mount / add_general）随奖励规则一起移植。
 * 进入游戏的战帝信息 loading 读本服 god_palace，旧版计划任务 setplace_info 向战斗服务器取 get_king 而 god 控制器里没有该方法，
 * 本表始终为空，king 恒为空数组（照旧版）。
 */
final class GodHandler
{
    /** 分享战报价格 */
    private const int SHARE_PRICE = 2;

    /** 奖励预览里显示的报名等级（s1 _entry_level，与战斗服务器的 175 不同，旧版如此） */
    private const int ENTRY_LEVEL = 100;

    /** s1 lang god_lang.php */
    private const array LANG_TURN = [1 => '第一场', 2 => '第二场', 3 => '第三场'];
    private const array LANG_RANK = [
        1 => '第一名', 2 => '第二名', 4 => '四强', 8 => '八强', 16 => '十六强', 32 => '三十二强', 64 => '海选半决赛', 128 => '海选精英赛',
        256 => '海选第五轮', 512 => '海选第四轮', 1024 => '海选第三轮', 2048 => '海选第二轮', 4096 => '海选第一轮', 8192 => '海选第一轮',
        16384 => '海选第一轮',
    ];
    private const array LANG_ROUND = [
        1 => '决赛', 2 => '半决赛', 4 => '四强赛', 8 => '八强赛', 16 => '十六强赛', 32 => '海选决赛', 64 => '海选半决赛', 128 => '海选第一轮',
        256 => '海选第二轮', 512 => '海选第三轮', 1024 => '海选第四轮', 2048 => '海选第五轮', 4096 => '海选第六轮', 8192 => '海选第七轮',
        16384 => '海选第八轮',
    ];
    private const array LANG_ROUND_NAME = [
        1 => '海选第一轮', 2 => '海选第二轮', 4 => '海选第三轮', 8 => '海选第四轮', 16 => '海选第五轮', 32 => '海选第六轮', 64 => '海选第七轮',
        128 => '海选第八轮',
    ];
    private const array LANG_CHAPTER = [
        1 => '一世战帝', 2 => '二世战帝', 3 => '三世战帝', 4 => '四世战帝', 5 => '五世战帝', 6 => '六世战帝', 7 => '七世战帝', 8 => '八世战帝',
        9 => '九世战帝', 10 => '十世战帝', 11 => '十一世战帝', 12 => '十二世战帝', 13 => '十三世战帝', 14 => '十四世战帝', 15 => '十五世战帝',
        16 => '十六世战帝', 17 => '十七世战帝', 18 => '十八世战帝', 19 => '十九世战帝', 20 => '二十世战帝', 21 => '廿一世战帝', 22 => '廿二世战帝',
        23 => '廿三世战帝', 24 => '廿四世战帝', 25 => '廿五世战帝', 26 => '廿六世战帝', 27 => '廿七世战帝', 28 => '廿八世战帝', 29 => '廿九世战帝',
        30 => '三十世战帝', 31 => '卅一世战帝', 32 => '卅二世战帝', 33 => '卅三世战帝', 34 => '卅四世战帝', 35 => '卅五世战帝', 36 => '卅六世战帝',
        37 => '卅七世战帝', 38 => '卅八世战帝', 39 => '卅九世战帝', 40 => '四十世战帝',
    ];
    private const array LANG_NAME = [
        1 => '无始大帝', 2 => '穹宇大帝', 3 => '极天大帝', 4 => '裂海大帝', 5 => '虚空大帝', 6 => '乱古大帝', 7 => '紫薇大帝',
        8 => '轮回大帝', 9 => '太衍大帝', 10 => '天羽大帝', 11 => '神武大帝', 12 => '四极大帝', 13 => '圣威大帝', 14 => '神魔大帝',
        15 => '太皇大帝', 16 => '圣祖大帝', 17 => '紫皇大帝', 18 => '四方大帝', 19 => '傲世大帝', 20 => '皇极大帝', 21 => '七曜大帝',
        22 => '天元大帝', 23 => '破元大帝', 24 => '圣天大帝', 25 => '武威大帝', 26 => '武烈大帝', 27 => '太初大帝', 28 => '玄通大帝',
        29 => '文谦大帝', 30 => '圣通大帝', 31 => '德象大帝', 32 => '亘古大帝', 33 => '睿圣大帝', 34 => '玄冲大帝', 35 => '乾坤大帝',
        36 => '文成大帝',
    ];
    private const string LANG_ERROR_OPEN = '不在预览奖励时间区间！';
    private const string LANG_CHAT_GOD_LOGIN = '%s【%s】上线了，万臣朝拜，天下慑服';

    private const string GOD_LOGIN_DELAY_KEY = 'god_login_delay';

    /**
     * 战神殿入口: c=god&m=index
     */
    public static function index(Context $ctx): array
    {
        /**
         * -3 等级太低
         * -1 获取战神殿信息失败
         *  0 失败
         *  1 成功
         */
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return ['status' => 0];
        }
        if (self::levelLimit($member['level']) === 2) {
            return ['status' => -3];
        }
        $uid = $member['uid'];
        $uc = GodServer::index($uid, Server::id());
        if ($uc['status'] !== 1) {
            return ['status' => -1];
        }
        if ($uc['stage'] === 3) {
            $info = $uc['info'];
            $round = $info['round'];
            // 赛制：32 强以前一局定胜负，之后三局两胜
            $info['mode'] = $round >= 32 ? 1 : 2;
            $info['play']['left']['server'] = Server::serverName($info['play']['left']['serverid']);
            $info['play']['right']['server'] = Server::serverName($info['play']['right']['serverid']);
            $info['play']['left']['first'] = $info['play']['right']['first'] = 0;
            $maxRound = $info['max_round'];
            if ($info['play']['left']['win'] === 2 || $info['play']['right']['win'] === 2) {
                // 第几轮（已决出胜负：显示下一轮）
                $info['type'] = self::LANG_ROUND[intdiv($round, 2)] ?? '';
            } else {
                if ($round <= 64) {
                    $info['type'] = self::LANG_ROUND[$round] ?? '';
                } else {
                    $info['type'] = $round > 0 ? (self::LANG_ROUND_NAME[intdiv($maxRound, $round)] ?? '') : '';
                }
                if ($info['play']['right']['support'] > $info['play']['left']['support']) {
                    $info['play']['right']['first'] = 1;
                } else {
                    $info['play']['left']['first'] = 1;
                }
                if ($info['stage'] === 2) {
                    $info['play']['right']['first'] = $info['play']['left']['first'];
                    $info['play']['left']['first'] = 1 - $info['play']['left']['first'];
                } elseif ($info['stage'] === 3 && $info['play']['left']['win'] + $info['play']['right']['win'] < 2) {
                    // 异常情况处理
                    $info['play']['left']['win'] = 2;
                }
            }
            // 依赖未移植：胜利奖励里的装备 / 坐骑 / 武将信息补全（奖励规则为空，reward 恒为空数组）
            $uc['info'] = $info;
        } elseif ($uc['stage'] === 4) {
            foreach ($uc['info']['list'] as $key => $value) {
                $uc['info']['list'][$key]['round'] = self::LANG_RANK[$value['round']] ?? '';
            }
            God::setEndStage();
        } elseif ($uc['stage'] === 2) {
            if (($uc['info']['is_entry'] ?? false) && !God::isEntry($uid)) {
                God::addEntryKey($uid);
            }
        }
        return [
            'status' => 1,
            'stage' => $uc['stage'],
            // 战帝堂是否开启
            'palace' => $uc['palace'],
            'cd' => $uc['cd'],
            'info' => $uc['info'],
        ];
    }

    /**
     * 报名: c=god&m=entry
     */
    public static function entry(Context $ctx): array
    {
        /**
         * -6 等级太低（80 级以下）
         * -5 已报名
         * -4 获取报名条件失败
         * -3 输入参数有误
         * -2 报名结束
         * -1 报名未开启
         *  0 失败
         *  1 成功
         *  2 等级不足
         */
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return ['status' => 0];
        }
        if (self::levelLimit($member['level']) >= 1) {
            return ['status' => -6];
        }
        $return = ['status' => 0];
        $limitLevel = God::entryLevel();
        if ($limitLevel === 0) {
            $return['status'] = -4;
            return $return;
        }
        if ($member['level'] < $limitLevel) {
            $return['status'] = 2;
            return $return;
        }
        $uc = GodServer::entry(Server::id(), $member['uid'], $member['nickname'], $member['gender']);
        $return['status'] = match ($uc['status']) {
            // 已报名
            -4 => -5,
            -3 => -3,
            -2 => -2,
            -1 => -1,
            1 => 1,
            default => 0,
        };
        if ($return['status'] === 1) {
            // 添加报名缓存
            God::addEntryKey($member['uid']);
            $return['list'] = $uc['list'];
        }
        return $return;
    }

    /**
     * 同步玩家战斗信息: c=god&m=sync_battle_info
     */
    public static function sync_battle_info(Context $ctx): array
    {
        /**
         * -6 等级太低
         * -5 保存信息失败
         * -4 获取信息失败
         * -3 报名未结束
         * -2 同步过于频繁
         * -1 未报名
         *  1 成功
         */
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return ['status' => 0];
        }
        if (self::levelLimit($member['level']) >= 1) {
            return ['status' => -6];
        }
        $uid = $member['uid'];
        if (God::isSync($uid)) {
            return ['status' => -2];
        }
        $sync = GodServer::syncBattleInfo($uid, Server::id());
        if ($sync['status'] === 1) {
            God::sync($uid, BossClock::now());
        }
        return ['status' => $sync['status'], 'info' => $sync['info']];
    }

    /**
     * 支持战斗中的一方: c=god&m=support&side=
     */
    public static function support(Context $ctx): array
    {
        /**
         * -2 等级太低
         * -1 支持失败
         *  0 失败
         *  1 成功
         */
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return ['status' => 0];
        }
        if (self::levelLimit($member['level']) === 2) {
            return ['status' => -2];
        }
        $support = GodServer::support((int)$ctx->input('side', 0), Server::id(), $member['uid']);
        return ['status' => $support['status'] === 1 ? 1 : -1];
    }

    /**
     * 获得支持数: c=god&m=get_support&id=
     */
    public static function get_support(Context $ctx): array
    {
        /**
         * -2 等级太低
         * -1 获取失败
         *  1 成功
         */
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return ['status' => 0, 'left' => 0, 'right' => 0];
        }
        if (self::levelLimit($member['level']) === 2) {
            return ['status' => -2];
        }
        $id = (int)$ctx->input('id', 0);
        $cache = God::supportCache($id);
        if ($cache === []) {
            $result = GodServer::getSupport($id);
            if ($result['status'] !== 1) {
                return ['status' => -1];
            }
            God::setSupportCache($id, $result['support']);
            return ['status' => 1, 'left' => $result['support']['left'], 'right' => $result['support']['right']];
        }
        return ['status' => 1, 'left' => (int)$cache['left'], 'right' => (int)$cache['right']];
    }

    /**
     * 历程: c=god&m=get_report_list&uid=&serverid=
     */
    public static function get_report_list(Context $ctx): array
    {
        /**
         * -4 等级太低
         * -3 获取历程失败
         *  0 失败
         *  1 成功
         */
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return ['status' => 0];
        }
        if (self::levelLimit($member['level']) >= 1) {
            return ['status' => -4];
        }
        $uid = (int)$ctx->input('uid', 0);
        $serverid = $uid === $member['uid'] ? Server::id() : (int)$ctx->input('serverid', 0);
        $list = GodServer::reportList($serverid, $uid);
        if ($list['status'] !== 1) {
            return ['status' => -3];
        }
        $info = $list['info'];
        $info['rank'] = $info['round'] > 32 ? '' : (self::LANG_RANK[$info['round']] ?? '');
        $info['server'] = Server::serverName($info['serverid']);
        $listArr = [];
        foreach ($list['list'] as $value) {
            $value['round'] = self::LANG_ROUND[$value['round']] ?? '';
            $value['turn'] = self::LANG_TURN[$value['stage']] ?? '';
            $listArr[] = $value;
        }
        return ['status' => 1, 'info' => $info, 'list' => $listArr];
    }

    /**
     * 奖励列表: c=god&m=get_reward_list&type=
     *
     * 旧版等级太低时输出 -5 后没有 return，接着往下执行又输出一次；这里输出 -5 即返回。
     */
    public static function get_reward_list(Context $ctx): array
    {
        /**
         * -5 等级太低
         * -3 获取奖励信息失败
         * -2 活动未结束
         * -1 获取信息失败
         *  0 失败
         *  1 成功
         */
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return ['status' => 0];
        }
        if (self::levelLimit($member['level']) === 2) {
            return ['status' => -5];
        }
        $type = (int)$ctx->input('type', 0);
        $type = $type > 0 ? $type : 1;
        $uc = GodServer::index($member['uid'], Server::id());
        if ($uc['status'] !== 1) {
            return ['status' => -1];
        }
        if ($uc['stage'] !== 4) {
            // 活动未结束
            return ['status' => -2];
        }
        $reward = GodServer::rewardList(Server::id(), $member['uid'], $type);
        if ($reward['status'] !== 1) {
            // 获取奖励信息失败
            return ['status' => -3];
        }
        // 依赖未移植：奖励列表的名称、已领取修复与装备 / 坐骑 / 武将信息补全（奖励规则为空，战斗服务器恒回 -1）
        return ['status' => -3];
    }

    /**
     * 领取奖励: c=god&m=get_reward&type=&id=
     */
    public static function get_reward(Context $ctx): array
    {
        /**
         * -5 等级太低
         * -4 领取装备失败
         * -3 添加领取记录失败
         * -2 获取奖励信息失败
         * -1 已领取
         *  0 失败
         *  1 成功
         */
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return ['status' => 0];
        }
        if (self::levelLimit($member['level']) === 2) {
            return ['status' => -5];
        }
        $type = (int)$ctx->input('type', 0);
        $id = (int)$ctx->input('id', 0);
        if (God::rewardLog($member['uid'], $id, $type, BossClock::now()) !== []) {
            return ['status' => -1];
        }
        $reward = GodServer::reward(Server::id(), $member['uid'], $type, $id);
        if ($reward['status'] !== 1) {
            // 获取奖励信息失败
            return ['status' => -2];
        }
        // 依赖未移植：领取记录与奖励发放（奖励规则为空，战斗服务器恒回 -1）
        return ['status' => -2];
    }

    /**
     * 战报信息: c=god&m=get_report_info&id=&stage=（旧版不判断等级）
     */
    public static function get_report_info(Context $ctx): array
    {
        /**
         * -1 获取战报失败
         *  1 成功
         */
        $id = (int)$ctx->input('id', 0);
        $stage = (int)$ctx->input('stage', 0);
        $return = self::reportInfo($id, $stage);
        $return['share'] = ['id' => $id . '_' . $stage, 'type' => 'god'];
        return $return;
    }

    /**
     * 一局的战报回放（_get_report_info，report 控制器 _god_report 相同）：Redis 缓存 15 天
     *
     * @return array<string, mixed>
     */
    public static function reportInfo(int $id, int $stage): array
    {
        $info = God::reportCache($id, $stage);
        if ($info === []) {
            $result = GodServer::reportInfo($id, $stage);
            if ($result['status'] !== 1) {
                return ['status' => -1];
            }
            $info = $result['report'];
            God::cacheReport($id, $stage, $info);
        }
        $return = ['status' => 1];
        $return['info']['win'] = $info['report']['assess'] ?? null;
        $return['battle'] = $info['report'];
        $return['battle']['left_role'] = ['name' => $info['nickname'], 'level' => $info['userlevel']];
        $return['battle']['right_role'] = ['name' => $info['name'], 'level' => $info['level']];
        return $return;
    }

    /**
     * 分享战报到聊天室: c=god&m=share_report&id=&stage=
     *
     * 照旧版：先扣 2 元宝再判断——5 分钟内同一局分享过的不再广播（照样扣元宝、回 1）、战报取不到回 -2（同样已扣元宝）；
     * 晋级赛（up 1）与决赛（up 2）才有聊天内容，海选 / 决赛圈未分出胜负的局没有内容回 2（已扣元宝）。
     */
    public static function share_report(Context $ctx): array
    {
        /**
         * -2 元宝不足 / 获取战报失败
         *  1 成功
         *  2 生成内容失败
         */
        $uid = MemberHandler::resolveUid($ctx);
        if (self::member($uid) === null) {
            return ['status' => 0];
        }
        $id = (int)$ctx->input('id', 0);
        $stage = (int)$ctx->input('stage', 0);
        // 判断元宝是否足够，扣除元宝
        $allGold = Chat::chargeShare($uid, self::SHARE_PRICE);
        if ($allGold === null) {
            return ['status' => -2];
        }
        // 检查是否被分享过
        if (God::shareCache($id, $stage)) {
            return ['status' => 1, 'all_gold' => $allGold];
        }
        // 查询比赛信息
        $info = self::reportInfo($id, $stage);
        if ($info['status'] !== 1) {
            return ['status' => -2];
        }
        if ($info['info']['win'] > 0) {
            $godLeft = $info['battle']['left_role']['name'];
            $godRight = $info['battle']['right_role']['name'];
        } else {
            $godLeft = $info['battle']['right_role']['name'];
            $godRight = $info['battle']['left_role']['name'];
        }
        $toround = intdiv((int)($info['battle']['round'] ?? 0), 2);
        $messageInfo = [
            'id' => $id . '|' . $stage,
            'from' => ['nickname' => $godLeft, 'uid' => 0],
            'to' => ['nickname' => $godRight, 'uid' => 0],
            'god_round' => $toround ? (self::LANG_ROUND[$toround] ?? '') : '',
        ];
        $sender = Chat::sender($uid);
        $message = match ((int)($info['battle']['up'] ?? 0)) {
            // 晋级赛
            1 => Chat::getMessageStr(23, $messageInfo, $sender),
            // 决赛
            2 => Chat::getMessageStr(24, $messageInfo, $sender),
            default => '',
        };
        if ($message !== '') {
            $status = 1;
            Chat::sendToAll($message, 10, 0, 0, $sender);
        } else {
            // 生成内容失败
            $status = 2;
        }
        God::setShareCache($id, $stage);
        return ['status' => $status, 'all_gold' => $allGold];
    }

    /**
     * 战帝堂: c=god&m=palace&page=
     */
    public static function palace(Context $ctx): array
    {
        /**
         * -2 等级太低
         * -1 没有战帝
         *  1 成功
         */
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return ['status' => 0, 'list' => []];
        }
        if (self::levelLimit($member['level']) === 2) {
            return ['status' => -2];
        }
        $page = (int)$ctx->input('page', 0);
        $result = GodServer::palaceList($page > 0 ? $page : 1);
        if ($result['status'] !== 1) {
            return ['status' => -1, 'list' => []];
        }
        $list = $result['list'];
        foreach ($list as $key => $value) {
            $list[$key]['server'] = Server::serverName($value['serverid']);
            foreach ($value['report_list'] as $k => $v) {
                $list[$key]['report_list'][$k]['title'] = (self::LANG_ROUND[$v['round']] ?? '') . (self::LANG_TURN[$v['stage']] ?? '');
            }
            $list[$key]['title'] = self::LANG_CHAPTER[$value['chapter']] ?? '';
            $list[$key]['name'] = self::LANG_NAME[$value['chapter']] ?? '';
        }
        return ['status' => 1, 'list' => $list];
    }

    /**
     * 战帝战报: c=god&m=palace_report&chapter=&round=&stage=
     */
    public static function palace_report(Context $ctx): array
    {
        /**
         * -3 等级太低
         * -2 获取战报失败
         * -1 参数不正确
         *  1 成功
         */
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return ['status' => 0];
        }
        if (self::levelLimit($member['level']) === 2) {
            return ['status' => -3];
        }
        $chapter = (int)$ctx->input('chapter', 0);
        $round = (int)$ctx->input('round', 0);
        $stage = (int)$ctx->input('stage', 0);
        if (!$chapter || !$round || !$stage) {
            return ['status' => -1];
        }
        $result = GodServer::palaceReport($chapter, $round, $stage);
        if ($result['status'] !== 1) {
            return ['status' => -2];
        }
        $info = $result['info'];
        $return = ['status' => 1];
        $return['info']['win'] = $info['report']['assess'] ?? null;
        $return['battle'] = $info['report'];
        $return['battle']['left_role'] = ['name' => $info['nickname'], 'level' => $info['userlevel']];
        $return['battle']['right_role'] = ['name' => $info['name'], 'level' => $info['level']];
        return $return;
    }

    /**
     * 战神殿奖励预览: c=god&m=get_reward_pre（每月 1 日到 15 日 18 点、60 级以上）
     */
    public static function get_reward_pre(Context $ctx): array
    {
        /**
         * -2 基础奖励信息错误
         * -1 不在预览奖励时间区间
         *  0 请求失败
         *  1 请求成功
         */
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return ['status' => 0];
        }
        $open = God::open(BossClock::now(), $member['level']);
        if (!$open['open']) {
            return ['status' => -1, 'msg' => self::LANG_ERROR_OPEN];
        }
        // 依赖未移植：基础奖励列表（战斗服务器没有 get_basereward_list，god_reward 也是 0 行）：武将列表、奖励列表为空
        return [
            'status' => 1,
            // 报名倒计时
            'entry_cd' => $open['entry_cd'],
            'general_list' => [],
            'reward_list' => [],
            'entry_level' => self::ENTRY_LEVEL,
        ];
    }

    /**
     * 进入游戏 loading 的战帝信息: c=god&m=loading
     */
    public static function loading(Context $ctx): array
    {
        return ['status' => 1, 'king' => God::placeInfo(BossClock::now())];
    }

    // ---------------------------------------------------------------------------------------------

    /**
     * 战帝上线公告（chat 控制器 index：每月 15 日以外，当前战帝本人打开聊天时；chat_model->god_login 5 分钟一次）
     *
     * 当前战帝取 Redis god_info，没有时向战斗服务器取 get_god_info 并缓存。公告发全服喇叭（type 2）与系统消息（type 7）。
     * 旧版另通知战斗服务器 god_login 向其他服发公告（transfer_model->god_login → uc_worldarena->send_god_login），单服部署没有其他服。
     */
    public static function loginNotice(int $uid, int $now): void
    {
        if ((int)date('d', $now) === 15) {
            return;
        }
        $god = God::getGod();
        if (!$god['uid']) {
            $result = GodServer::godInfo();
            if ($result['status'] === 1) {
                $god = $result['god'];
                God::cacheGodInfo($god, $now);
            }
        }
        if (!$god['uid'] || $uid !== (int)$god['uid'] || (int)$god['serverid'] !== Server::id()) {
            return;
        }
        // 发送全服公告（发送间隔 5 分钟）
        $redis = Redis::instance();
        if ($redis === null || !(bool)$redis->set(self::GOD_LOGIN_DELAY_KEY, '1', ['nx', 'ex' => 300])) {
            return;
        }
        $sender = Chat::sender($uid);
        $message = sprintf(self::LANG_CHAT_GOD_LOGIN, $god['title'], $god['nickname']);
        Chat::sendToAll($message, Chat::TYPE_HORN, 0, 0, $sender);
        Chat::sendToAll('{9, ' . $message . '}', Chat::TYPE_SYSTEM, 0, 0, $sender);
    }

    /**
     * 主城的战神殿报名状态（member 控制器 _god_entry_status）：0 不可报名，1 可报名，2 开战
     *
     * 8 日 0 点到 15 日 18:00 未报名为 1；15 日 19:00 ~ 21:00 比赛没结束为 2（旧版按 date('jHi') 的数值比较，照搬）。
     */
    public static function entryStatus(int $uid, int $level, int $now): int
    {
        $dayHour = (int)date('jHi', $now);
        if ($dayHour < 80000 || $dayHour >= 160000) {
            return 0;
        }
        if ($level < God::entryLevel()) {
            return 0;
        }
        if ($dayHour < 151800) {
            return God::isEntry($uid) ? 0 : 1;
        }
        if ($dayHour >= 151900 && $dayHour <= 152100 && !God::endStage()) {
            return 2;
        }
        return 0;
    }

    /**
     * 等级限制（构造函数 level_limit）：60 级以下 2，80 级以下 1，否则 0
     */
    private static function levelLimit(int $level): int
    {
        if ($level < 60) {
            return 2;
        }
        return $level < 80 ? 1 : 0;
    }

    /**
     * @return array{uid: int, nickname: string, level: int, gender: int}|null
     */
    private static function member(int $uid): ?array
    {
        if ($uid <= 0) {
            return null;
        }
        $row = DB::table('member')->select('uid', 'nickname', 'level', 'gender')->where('uid', $uid)->whereNull('deleted_at')->first();
        return $row === null ? null : ['uid' => (int)$row['uid'], 'nickname' => (string)$row['nickname'], 'level' => (int)$row['level'], 'gender' => (int)$row['gender']];
    }
}
