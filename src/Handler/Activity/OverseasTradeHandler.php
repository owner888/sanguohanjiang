<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\Pvp\ArenaHandler;
use Hanjiang\Handler\Pvp\CountryHandler;
use Hanjiang\MasterData\OverseasTradeShop;
use Hanjiang\Model\MainQuestTask;
use Hanjiang\Model\Member;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 海外贸易（移植旧版 controllers/overseastrade.php + models/overseastrade_model.php）
 *
 * 客户端（overseastradepop.js / miscpop.js）调用：index(p) / item_list / buy_item(id) / get_list_by_country(p) / goout /
 * trade / join_team(id, site, page, place) / rob(id) / refresh(p) / get_trade_log(p) / get_report_info(id) / buy。
 * 旧版 check_team 客户端不调用，不开放。加入国家、主公 51 级开放（没有国家 2，等级不足 3）。
 *
 * 玩法：每天 5 次贸易、5 次打劫（overseas_trade_info，隔天第一次读取时重置）。先买一船货（MasterData OverseasTradeShop：
 * 银币价格 × 主公等级 / 元宝，need_vip），再在本国的组队页（5 页、每页若干位置）建队或加入别人的队伍：两人凑齐自动出发，
 * 也可以花 5 元宝单人出发。航行 15 分钟后回城（下次打开 index / refresh 时结算）得 货物 get × 主公等级 银币，完成主线任务 14。
 * 航行中的船队可被其他国家的玩家打劫：按旧版 battle_pk 依次挑战船上的玩家（等级低的先打，ArenaHandler::battlePk），
 * 全胜才算打劫成功：打劫方得收益较高一方的一半，船上每人只能拿一半收益，并给船上的人发信件（type 9）、记国家战报
 * （country_fight_notice type 1）。打劫记录与战报见 overseas_trade_log / overseas_trade_report。
 * VIP 10 以上每天可买 5 / 10 / 15 / 20 次打劫（overseas_trade_rob），价格 50 元宝起每次 + 10，最多 100（overseas_trade_buy_log）。
 * 退出 / 被踢出国家时清理未出发的队伍（clearTeam，CountryHandler 调用）。
 *
 * 与旧版的差别（多 Worker 安全）：
 * - 旧版航行中的船队另存 Redis（oversease_trade_team_{id}，出发时的快照）并用 KEYS 扫描列船队，新版直接查
 *   overseas_trade_team（status 1 且未满 15 分钟，按 id 排序）；
 * - 接口在 Member::locked（玩家行锁）里执行，打劫再锁住被打劫的队伍行（同一船队串行），旧版的 Redis 打劫锁 / 购买锁（-11 / -3）
 *   不再需要；建队锁住国家行（同一位置不会建出两支队伍）；加入队伍以「位置为空、未出发」条件更新（旧版不校验，后到的会覆盖）；
 * - 回城结算以「队伍 id 未变」条件清空自己的队伍状态，结算成功才发银币（旧版并发会重复结算）；
 * - join_team 的 site 只能是 1 / 2，其他值回 -8（旧版会拼出不存在的列，SQL 报错）；队伍退出改为软删除。
 * 依赖未移植：春节送福活动计数（actnewyear_model->add_task_num 18，节日活动）。
 */
final class OverseasTradeHandler
{
    /** 海外贸易开启等级 */
    private const int LEVEL = 51;
    /** 贸易需要时长 */
    private const int TIME = 900;
    /** 船队每页显示几条 */
    private const int LIMIT = 3;
    /** 单人贸易价格 */
    private const int PRICE = 5;
    /** 贸易组队总页数 */
    private const int ALL_PAGE = 5;
    /** 购买打劫次数价格下限 / 上限 */
    private const int MIN_GOLD = 50;
    private const int MAX_GOLD = 100;
    /** 每日贸易次数 / 打劫次数（overseastrade_model） */
    private const int TIMES = 5;
    private const int ROBTIMES = 5;
    /** 贸易记录每页条数 */
    private const int LOG_LIMIT = 5;
    /** VIP 10 ~ 13 每天可购买的打劫次数 */
    private const array ROB_TIMES = [10 => 5, 11 => 10, 12 => 15, 13 => 20];
    /** 信件类型：海外贸易 */
    private const int MESSAGE_TYPE = 9;
    /** 国家战报类型：海外贸易 */
    private const int FIGHT_NOTICE_TYPE = 1;

    private const array INFO_FIELDS = ['uid', 'times', 'robtimes', 're_robtimes', 'team', 'commodity', 'dateline'];
    private const array TEAM_FIELDS = [
        'id', 'country', 'country_name', 'member1', 'member1_name', 'member1_gender', 'member1_level', 'member1_commodity',
        'member2', 'member2_name', 'member2_gender', 'member2_level', 'member2_commodity', 'status', 'page', 'site',
        'startdateline', 'dateline',
    ];
    private const array LOG_FIELDS = [
        'id', 'type', 'team_id', 'member1', 'member1_name', 'member1_win', 'member1_get', 'member1_report', 'member1_tree',
        'member2', 'member2_name', 'member2_win', 'member2_get', 'member2_report', 'member2_tree', 'attack_uid',
        'attack_nickname', 'win', 'get', 'dateline',
    ];

    /** 语言包（overseastrade_lang / message_lang） */
    private const array LANG = [
        'overseatrade_buy_error' => '获取可购买次数失败',
        'overseatrade_buy_limit' => '达到更高的VIP等级可以购买更多的打劫次数',
        'overseatrade_gold_limit' => '元宝不足',
        'overseatrade_vip_limit' => '你需要达到VIP10才能进行购买',
        'message_overseas_att_lose' => '意图打劫您和%s的船只，被你们合力击败了！',
        'message_overseas_att_win' => '打劫了您的船只,您的收益减半,获得银币%s',
        'message_overseas_defense_lost' => '打劫了您和%s的船只,您的收益减半,获得银币%s',
        'message_overseas_defense_win' => '意图打劫，被您击败了！',
    ];

    /**
     * 初试界面: c=overseastrade&m=index&p=船队页
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $page = (int)$ctx->input('p', 0);
        $page = $page > 0 ? $page : 1;
        return Member::locked($uid, static function () use ($uid, $page): array {
            /**
             * 2 未加入国家，功能未开启
             * 3 等级不够
             *-1 获取用户信息失败
             *-2 获取队伍信息失败
             *-4 获取货物信息失败
             */
            $return = ['status' => 0, 'get' => 0, 'trade' => 0, 'endtime' => 0, 'btn_status' => 0, 'item_name' => '', 'team' => []];
            $now = time();
            $member = self::member($uid);
            $gate = self::gate($member);
            if ($gate !== 0) {
                $return['status'] = $gate;
                return $return;
            }
            // 获取用户信息
            $info = self::memberInfo($uid, $now);
            $commodity = self::commodityList($member);
            // 判断是否能够发起贸易或则是否能够购买货物
            if ($info['commodity'] > 0) {
                $return['btn_status'] = 1;
                if (!isset($commodity[$info['commodity']])) {
                    $return['status'] = -4;
                    return $return;
                }
                $return['item_name'] = $commodity[$info['commodity']]['name'];
            } else {
                $return['btn_status'] = 0;
            }
            // 如果是已经组队了，贸易按钮就要灰掉
            $teamid = 0;
            if ($info['team'] > 0) {
                $return['btn_status'] = 2;
                $teamid = $info['team'];
                // 取自己的队伍信息
                $team = self::teamInfo($teamid);
                if ($team === null) {
                    $return['status'] = -2;
                    return $return;
                }
                // 开始贸易了
                if ($team['status'] === 1) {
                    $return['btn_status'] = 3;
                }
                // 判断自己的贸易是否结束
                if (($team['status'] === 1 && $now - $team['startdateline'] >= self::TIME) || $team['status'] === 2) {
                    $site = self::siteOf($team, $uid);
                    if ($site === 0) {
                        // 队伍信息异常：修改用户队伍状态
                        self::updateMemberInfo($uid, ['team' => 0], $now);
                        $return['btn_status'] = 1;
                    } else {
                        $settle = self::settle($uid, $member, $info, $team, $commodity, $now);
                        if ($settle !== null) {
                            $return['trade'] = $settle['trade'];
                            $return['get'] = $settle['get'];
                        }
                        $return['btn_status'] = 0;
                        $teamid = 0;
                    }
                } else {
                    // 贸易倒计时（旧版对未出发的队伍也按开始时间 0 计算）
                    $return['trade'] = 1;
                    $return['endtime'] = self::TIME - ($now - $team['startdateline'] + 2);
                }
            }
            // 如果船队没有贸易，并且没有货物购买次数和贸易次数
            if ($return['btn_status'] !== 3 && $info['times'] === 0 && $info['robtimes'] === 0) {
                $return['btn_status'] = 4;
            }
            // 初始化打劫次数（只有VIP10以上才更新打劫次数）
            if ((int)$member['vip'] >= 10) {
                $rob = self::robTime($uid, (int)$member['vip'], $now);
                $return['rob']['times'] = $rob['times'] ?? null;
            } else {
                $return['rob']['times'] = 0;
            }
            // 获取此时应该价格
            $return['rob']['price'] = self::buyPrice($uid, $now);
            // 取船队
            $return['status'] = 1;
            $return['team'] = self::getTeam($member, $teamid, $page, $now);
            $return['all_silver'] = (int)DB::table('member')->where('uid', $uid)->value('silver');
            $return['info'] = $info;
            $return['info']['robtimes'] = $info['robtimes'] + $info['re_robtimes'];
            return $return;
        });
    }

    /**
     * 购买货物界面: c=overseastrade&m=item_list
     */
    public static function item_list(Context $ctx): array
    {
        /**
         * 2未加入国家 功能未开启
         *-1获取用户信息失败
         *-2已经购买了货物，快去贸易吧
         *-3获取货物列表失败
         */
        $return = ['status' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        $member = self::member($uid);
        $gate = self::gate($member);
        if ($gate !== 0) {
            $return['status'] = $gate;
            return $return;
        }
        $info = Member::locked($uid, static fn(): array => self::memberInfo($uid, time()));
        // 判断用户是否已经购买了货物
        if ($info['commodity'] > 0) {
            $return['status'] = -2;
            return $return;
        }
        // 获取所有货物列表
        $list = array_values(self::commodityList($member));
        if ($list === []) {
            $return['status'] = -3;
            return $return;
        }
        foreach ($list as $key => $val) {
            $list[$key]['get'] = $val['get'] * (int)$member['level'];
        }
        $return['status'] = 1;
        $return['list'] = $list;
        return $return;
    }

    /**
     * 购买货物: c=overseastrade&m=buy_item&id=货物id
     */
    public static function buy_item(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $id = (int)$ctx->input('id', 0);
        return Member::locked($uid, static function () use ($uid, $id): array {
            /**
             * 2 未加入国家
             * 3 等级不足
             *-1 获取用户信息失败
             *-2 已经购买了一船货
             *-3 获取货物信息失败
             *-4 vip等级不足，无法购买
             *-5 银币不足
             *-6 元宝不足
             *-7 贸易次数不足
             */
            $return = ['status' => 0];
            $now = time();
            $member = self::member($uid);
            $gate = self::gate($member);
            if ($gate !== 0) {
                $return['status'] = $gate;
                return $return;
            }
            $info = self::memberInfo($uid, $now);
            // 判断用户是否已经购买了货物
            if ($info['commodity'] > 0) {
                $return['status'] = -2;
                return $return;
            }
            // 判断是否有贸易次数
            if ($info['times'] === 0) {
                $return['status'] = -7;
                return $return;
            }
            // 获取货物信息
            $item = $id ? (self::commodityList($member)[$id] ?? null) : null;
            if ($item === null) {
                $return['status'] = -3;
                return $return;
            }
            // 判断vip等级
            if ((int)$member['vip'] < $item['need_vip']) {
                $return['status'] = -4;
                return $return;
            }
            // 判断购买货币是否足够并扣除
            if ($item['buy_type'] === 1 && !Member::deductCurrency($uid, silver: $item['buy_value'])) {
                // 银币不足
                $return['status'] = -5;
                return $return;
            }
            if ($item['buy_type'] === 2 && !Member::deductCurrency($uid, gold: $item['buy_value'])) {
                // 元宝不足
                $return['status'] = -6;
                return $return;
            }
            // 执行购买
            self::updateMemberInfo($uid, ['commodity' => $id], $now);
            $after = DB::table('member')->select('gold', 'silver')->where('uid', $uid)->first();
            $return['status'] = 1;
            $return['all_gold'] = (int)$after['gold'];
            $return['all_silver'] = (int)$after['silver'];
            return $return;
        });
    }

    /**
     * 获取国家队伍列表（等待组队的队伍）: c=overseastrade&m=get_list_by_country&p=页
     */
    public static function get_list_by_country(Context $ctx): array
    {
        /**
         * 2 未加入国家
         * 3 等级不足
         *-1 获取用户信息失败
         */
        $return = ['status' => 0, 'teamid' => 0, 'trade' => 0, 'price' => self::PRICE, 'list' => []];
        $uid = MemberHandler::resolveUid($ctx);
        $page = (int)$ctx->input('p', 0);
        $member = self::member($uid);
        $gate = self::gate($member);
        if ($gate !== 0) {
            $return['status'] = $gate;
            return $return;
        }
        $info = Member::locked($uid, static fn(): array => self::memberInfo($uid, time()));
        // 取用户的队伍信息
        $team = $info['team'] > 0 ? self::teamInfo($info['team']) : null;
        if ($team !== null) {
            if (!$page && $team['status'] === 0) {
                $page = $team['page'];
            }
            if ($team['status'] === 1) {
                // 开始贸易了
                $return['trade'] = 1;
            }
        }
        // 返回队伍id
        $return['teamid'] = $info['team'];
        // 分页限制
        $page = $page > 0 ? $page : 1;
        $page = min($page, self::ALL_PAGE);
        // 获取未组队成功的队伍
        $newlist = [];
        foreach (DB::table('overseas_trade_team')->select(...self::TEAM_FIELDS)->where('country', (int)$member['country'])
            ->where('status', 0)->where('page', $page)->whereNull('deleted_at')->orderBy('id')->get() as $val) {
            $val = self::castTeam($val);
            $newlist[$val['site']] = $val;
        }
        $return['status'] = 1;
        $return['list'] = $newlist;
        $return['allpage'] = self::ALL_PAGE;
        $return['page'] = $page;
        return $return;
    }

    /**
     * 退出队伍: c=overseastrade&m=goout
     */
    public static function goout(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        return Member::locked($uid, static function () use ($uid): array {
            /**
             * 2 未加入国家
             * 3 等级不足
             *-1 获取用户信息失败
             *-2 未加入队伍
             *-3 获取队伍信息失败
             *-4 贸易已开始
             *-5 退出失败
             */
            $return = ['status' => 0];
            $now = time();
            $gate = self::gate(self::member($uid));
            if ($gate !== 0) {
                $return['status'] = $gate;
                return $return;
            }
            $info = self::memberInfo($uid, $now);
            // 判断是否加入了队伍
            if ($info['team'] === 0) {
                $return['status'] = -2;
                return $return;
            }
            // 查询该队伍信息
            $team = self::teamInfo($info['team']);
            if ($team === null) {
                $return['status'] = -3;
                return $return;
            }
            // 贸易已开始，无法执行退出
            if ($team['status'] > 0) {
                $return['status'] = -4;
                return $return;
            }
            // 执行退出
            if (self::deleteTeam($info['team'], $now) !== 1) {
                $return['status'] = -5;
                return $return;
            }
            // 修改用户队伍状态
            self::updateMemberInfo($uid, ['team' => 0], $now);
            $return['status'] = 1;
            return $return;
        });
    }

    /**
     * 单人贸易: c=overseastrade&m=trade
     */
    public static function trade(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        return Member::locked($uid, static function () use ($uid): array {
            /**
             * 2 未加入国家
             * 3 等级不足
             *-1 获取用户信息失败
             *-2 还未加入一个队伍
             *-3 获取队伍信息失败
             *-4 队伍已开始贸易
             *-5 元宝不足
             */
            $return = ['status' => 0, 'times' => 0, 'team_id' => 0, 'endtime' => 0];
            $now = time();
            $member = self::member($uid);
            $gate = self::gate($member);
            if ($gate !== 0) {
                $return['status'] = $gate;
                return $return;
            }
            $info = self::memberInfo($uid, $now);
            // 判断自己是否加入了队伍
            if ($info['team'] === 0) {
                $return['status'] = -2;
                return $return;
            }
            // 获取队伍信息
            $team = self::teamInfo($info['team']);
            if ($team === null) {
                $return['status'] = -3;
                return $return;
            }
            // 判断队伍是否已经开始贸易
            if ($team['status'] > 0) {
                $return['status'] = -4;
                return $return;
            }
            // 扣除元宝
            if (!Member::deductCurrency($uid, gold: self::PRICE)) {
                $return['status'] = -5;
                return $return;
            }
            // 队伍信息修改为开始贸易（以未出发为条件，同一队伍不会出发两次）
            if (DB::table('overseas_trade_team')->where('id', $info['team'])->where('status', 0)->whereNull('deleted_at')
                ->update(['status' => 1, 'startdateline' => $now]) === 0) {
                $return['status'] = -4;
                return $return;
            }
            // 扣除自己的贸易次数
            self::subTimes($uid, $now, true);
            // 添加贸易记录
            self::addTradeLog([
                'type' => 1,
                'member1' => $team['member1'],
                'member1_name' => $team['member1_name'],
                'member2' => $team['member2'],
                'member2_name' => $team['member2_name'],
                'dateline' => $now,
            ]);
            // 依赖未移植：春节送福活动计数 actnewyear_model->add_task_num(version, 18)
            $return['status'] = 1;
            $return['team_id'] = $info['team'];
            $return['times'] = $info['times'] - 1;
            $return['all_gold'] = (int)DB::table('member')->where('uid', $uid)->value('gold');
            $return['endtime'] = self::TIME;
            return $return;
        });
    }

    /**
     * 加入 / 创建队伍: c=overseastrade&m=join_team&id=队伍id（0 为创建）&site=要加入的位置 1|2&page=第几页&place=第几个队伍
     */
    public static function join_team(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $id = (int)$ctx->input('id', 0);
        $site = (int)$ctx->input('site', 0);
        $page = (int)$ctx->input('page', 0);
        $place = (int)$ctx->input('place', 0);
        return Member::locked($uid, static function () use ($uid, $id, $site, $page, $place): array {
            /**
             * 1 加入成功
             * 2 未加入国家
             * 3 等级不足
             *-1 获取用户信息失败
             *-2 还未购买货物
             *-3 次数不足，无法加入队伍
             *-4 已加入一个队伍
             *-5 获取队伍信息失败
             *-6 要加入的位置已经有人了
             *-7 该队伍不是自己国家的队伍
             *-8 参数丢失
             *-9 该位置无法加入
             *-10队伍已经开始贸易了
             */
            $return = ['status' => 0, 'commodity' => 0, 'team_id' => 0, 'times' => 0, 'endtime' => 0, 'price' => self::PRICE];
            $now = time();
            $member = self::member($uid);
            // 判断功能是否开启
            if ($member === null || (int)$member['country'] === 0) {
                $return['status'] = 2;
                return $return;
            }
            // 判断参数是否丢失（位置只能是 1 / 2，旧版不校验）
            if (!$page || $page > self::ALL_PAGE || !$place || !in_array($site, [1, 2], true)) {
                $return['status'] = -8;
                return $return;
            }
            // 判断等级是否足够
            if ((int)$member['level'] < self::LEVEL) {
                $return['status'] = 3;
                return $return;
            }
            $info = self::memberInfo($uid, $now);
            // 判断自己是否购买了货物
            if ($info['commodity'] === 0) {
                $return['status'] = -2;
                return $return;
            }
            $return['commodity'] = $info['commodity'];
            // 判断次数是否足够
            if ($info['times'] === 0) {
                $return['status'] = -3;
                return $return;
            }
            // 判断是否加入了队伍
            if ($info['team'] > 0) {
                $return['status'] = -4;
                return $return;
            }
            $status = 0;
            if ($id > 0) {
                // 查询该队伍信息
                $team = self::teamInfo($id);
                if ($team === null) {
                    $return['status'] = -5;
                    return $return;
                }
                // 判断该队伍是否已经开始贸易了
                if ($team['status'] > 0) {
                    $return['status'] = -10;
                    return $return;
                }
                // 判断要加入的位置是否已经有人了
                if ($team['member' . $site] > 0) {
                    $return['status'] = -6;
                    return $return;
                }
                // 判断该队伍是否是本国队伍
                if ($team['country'] !== (int)$member['country']) {
                    $return['status'] = -7;
                    return $return;
                }
                // 执行加入（join_team：写入自己的位置并出发；以位置为空、未出发为条件）
                $status = DB::table('overseas_trade_team')->where('id', $id)->where('member' . $site, 0)->where('status', 0)->whereNull('deleted_at')
                    ->update([
                        'member' . $site => $uid,
                        'member' . $site . '_name' => (string)$member['nickname'],
                        'member' . $site . '_gender' => (int)$member['gender'],
                        'member' . $site . '_level' => (int)$member['level'],
                        'member' . $site . '_commodity' => $info['commodity'],
                        'status' => 1,
                        'startdateline' => $now,
                    ]) > 0 ? 1 : 0;
                if ($status === 0) {
                    $return['status'] = -6;
                    return $return;
                }
                self::updateMemberInfo($uid, ['team' => $id], $now);
                // 队友位置
                $teammate = $site === 1 ? 2 : 1;
                // 开始贸易
                if ($team['member' . $teammate] > 0) {
                    $team['member' . $site] = $uid;
                    $team['member' . $site . '_name'] = (string)$member['nickname'];
                    // 扣除自己与队友的贸易次数
                    self::subTimes($uid, $now, true);
                    self::memberInfo($team['member' . $teammate], $now);
                    self::subTimes($team['member' . $teammate], $now, false);
                    // 添加贸易记录
                    self::addTradeLog([
                        'type' => 1,
                        'member1' => $team['member1'],
                        'member1_name' => $team['member1_name'],
                        'member2' => $team['member2'],
                        'member2_name' => $team['member2_name'],
                        'dateline' => $now,
                    ]);
                    $return['price'] = 0;
                    $return['times'] = $info['times'] - 1;
                    $return['endtime'] = self::TIME;
                    // 依赖未移植：春节送福活动计数 actnewyear_model->add_task_num(version, 18)（两名队员）
                }
                $return['team_id'] = $id;
            } else {
                // 同一国家的建队串行（锁国家行），再查询该位置是否已经有别的队伍
                DB::query('SELECT `id` FROM `zc_sanguo_country` WHERE `id` = :id FOR UPDATE', ['id' => (int)$member['country']]);
                if (DB::table('overseas_trade_team')->where('country', (int)$member['country'])->where('page', $page)->where('site', $place)
                    ->where('status', 0)->whereNull('deleted_at')->exists()) {
                    $return['status'] = -9;
                    return $return;
                }
                // 查询国家名称，创建队伍
                $country = CountryHandler::countryInfo((int)$member['country']);
                $status = (int)DB::table('overseas_trade_team')->insertGetId([
                    'member' . $site => $uid,
                    'member' . $site . '_commodity' => $info['commodity'],
                    'member' . $site . '_gender' => (int)$member['gender'],
                    'member' . $site . '_level' => (int)$member['level'],
                    'member' . $site . '_name' => (string)$member['nickname'],
                    'dateline' => $now,
                    'country' => (int)$member['country'],
                    'country_name' => (string)($country['name'] ?? ''),
                    'site' => $place,
                    'page' => $page,
                ]);
                if ($status > 0) {
                    // 修改用户信息中的队伍状态
                    self::updateMemberInfo($uid, ['team' => $status], $now);
                }
                $return['team_id'] = $status;
            }
            // 判断是否加入成功
            if ($status > 0) {
                $return['status'] = 1;
            }
            return $return;
        });
    }

    /**
     * 打劫: c=overseastrade&m=rob&id=队伍id
     */
    public static function rob(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $id = (int)$ctx->input('id', 0);
        return Member::locked($uid, static function () use ($uid, $id): array {
            /**
             * 2 未加入国家
             * 3 等级不足
             *-1 获取用户信息失败
             *-2 打劫次数不足
             *-3 获取队伍信息失败
             *-4 该队伍没有贸易
             *-5 队伍已贸易结束
             *-6 队伍信息异常
             *-7 获取货物信息失败
             *-8 该队伍已被打劫过
             *-9 该队伍是自己的队伍，无法打劫
             *-10 自己已加入队伍，无法打劫
             */
            $return = ['status' => 0];
            $now = time();
            $member = self::member($uid);
            $gate = self::gate($member);
            if ($gate !== 0) {
                $return['status'] = $gate;
                return $return;
            }
            $info = self::memberInfo($uid, $now);
            // 判断是否有打劫次数
            if ($info['robtimes'] === 0 && $info['re_robtimes'] === 0) {
                $return['status'] = -2;
                return $return;
            }
            // 获取队伍信息（锁住队伍行，同一船队的打劫串行）
            DB::query('SELECT `id` FROM `zc_sanguo_overseas_trade_team` WHERE `id` = :id FOR UPDATE', ['id' => $id]);
            $team = self::teamInfo($id);
            if ($team === null) {
                $return['status'] = -3;
                return $return;
            }
            // 判断自己是否有队伍
            if ($info['team'] > 0) {
                $return['status'] = -10;
                return $return;
            }
            // 判断要抢的是不是自己的队伍
            if ($info['team'] === $id) {
                $return['status'] = -9;
                return $return;
            }
            // 判断该队伍是否开始了贸易
            if ($team['status'] === 0) {
                $return['status'] = -4;
                return $return;
            }
            // 判断该队伍是否被打劫过
            if ($team['status'] === 2) {
                $return['status'] = -8;
                return $return;
            }
            // 判断该队伍是否已经完成贸易
            if ($now - $team['startdateline'] > self::TIME) {
                $return['status'] = -5;
                return $return;
            }
            // 取成员等级
            $uids = [];
            for ($i = 1; $i <= 2; $i++) {
                if ($team['member' . $i] > 0) {
                    $uids[] = $team['member' . $i];
                }
            }
            if ($uids === []) {
                $return['status'] = -6;
                return $return;
            }
            $user = [];
            foreach (DB::table('member')->select('uid', 'level', 'nickname')->whereIn('uid', $uids)->whereNull('deleted_at')->get() as $row) {
                $user[(int)$row['uid']] = ['uid' => (int)$row['uid'], 'level' => (int)$row['level'], 'nickname' => (string)$row['nickname']];
            }
            // 按等级排序（等级低的先打）
            $newUser = array_values($user);
            usort($newUser, static fn(array $a, array $b): int => $a['level'] <=> $b['level']);
            // 扣除挑战次数：先消耗系统给的次数，后消耗购买的次数
            $column = $info['robtimes'] === 0 ? 're_robtimes' : 'robtimes';
            DB::execute(
                "UPDATE `zc_sanguo_overseas_trade_info` SET `{$column}` = `{$column}` - 1, `dateline` = :now WHERE `uid` = :uid AND `{$column}` > 0 AND `deleted_at` IS NULL",
                ['now' => $now, 'uid' => $uid]
            );

            // 开始打劫
            $lose = 0;
            $report = [];
            foreach ($newUser as $val) {
                if ($lose === 0) {
                    $battle = ArenaHandler::battlePk($uid, $val['uid']);
                    if (($battle['info']['assess'] ?? 0) < 1) {
                        $lose = 1;
                    }
                    // 存战报
                    $report[$val['uid']]['win'] = $lose;
                    $report[$val['uid']]['id'] = self::recordPkReport([
                        'from' => (string)$member['nickname'],
                        'fromlevel' => (int)$member['level'],
                        'to' => $val['nickname'],
                        'tolevel' => $val['level'],
                        'report' => $battle['info'] ?? null,
                    ]);
                }
            }
            $win = $lose === 1 ? 0 : 1;

            $robGet = 0;
            if ($win === 1) {
                // 打劫成功：打劫收益较高的一方（等级高的，等级相同时货物好的）
                if ($team['member1_level'] > $team['member2_level']) {
                    $mySite = 1;
                } elseif ($team['member1_level'] === $team['member2_level'] && $team['member1_commodity'] > $team['member2_commodity']) {
                    $mySite = 1;
                } else {
                    $mySite = 2;
                }
                $commodity = self::commodityList($member);
                // 把船队改成被打劫
                DB::table('overseas_trade_team')->where('id', $id)->whereNull('deleted_at')->update(['status' => 2]);
                // 打劫方获得银币
                $robGet = (int)ceil(($commodity[$team['member' . $mySite . '_commodity']]['get'] ?? 0) * ($user[$team['member' . $mySite]]['level'] ?? 0) / 2);
                Member::addCurrency($uid, silver: $robGet);
                // 得到船队人员被劫后能获得多少
                for ($i = 1; $i <= 2; $i++) {
                    if ($team['member' . $i] > 0) {
                        $team['member' . $i . '_get'] = (int)ceil(($commodity[$team['member' . $i . '_commodity']]['get'] ?? 0) * ($user[$team['member' . $i]]['level'] ?? 0) / 2);
                        if (isset($user[$team['member' . $i]])) {
                            $user[$team['member' . $i]]['get'] = $team['member' . $i . '_get'];
                        }
                    } else {
                        $team['member' . $i . '_get'] = 0;
                    }
                }
            }

            // 发送气泡通知（信件 type 9）
            foreach ($user as $key => $val) {
                $friend = null;
                $get = 0;
                foreach ($user as $smallVal) {
                    if ($key !== $smallVal['uid']) {
                        $friend = $smallVal;
                    } else {
                        $get = $smallVal['get'] ?? 0;
                    }
                }
                if ($win === 1) {
                    $text = count($user) === 2 && $friend !== null
                        ? sprintf(self::LANG['message_overseas_defense_lost'], self::em($friend['uid'], $friend['nickname']), $get)
                        : sprintf(self::LANG['message_overseas_att_win'], $val['get'] ?? 0);
                } else {
                    $text = count($user) === 2 && $friend !== null
                        ? sprintf(self::LANG['message_overseas_att_lose'], self::em($friend['uid'], $friend['nickname']))
                        : self::LANG['message_overseas_defense_win'];
                }
                MessageHandler::sendSystemMessage([$val['uid']], self::MESSAGE_TYPE, (string)$member['nickname'], self::em($uid, (string)$member['nickname']) . $text, 0, $uid);
            }

            // 存贸易记录
            $data = [
                'attack_uid' => $uid,
                'attack_nickname' => (string)$member['nickname'],
                'get' => $robGet,
                'win' => $win,
                'type' => 2,
            ];
            for ($i = 1; $i <= 2; $i++) {
                $data['member' . $i] = $team['member' . $i];
                $data['member' . $i . '_name'] = $team['member' . $i . '_name'];
                $data['member' . $i . '_get'] = $team['member' . $i . '_get'] ?? 0;
                if (isset($report[$team['member' . $i]])) {
                    $data['member' . $i . '_win'] = $report[$team['member' . $i]]['win'] > 0 ? 0 : 1;
                    $data['member' . $i . '_report'] = $report[$team['member' . $i]]['id'];
                }
            }
            $data['dateline'] = $now;
            self::addTradeLog($data);
            // 记录国家战报 - 海外贸易
            if ($win === 1) {
                self::addFightNoticeLog($member, $data, $win, $now);
            }
            $return['status'] = 1;
            $return['win'] = $win;
            $return['get'] = $robGet;
            $return['all_silver'] = (int)DB::table('member')->where('uid', $uid)->value('silver');
            $return['robtimes'] = $info['robtimes'] + $info['re_robtimes'] - 1;
            return $return;
        });
    }

    /**
     * 获取队伍列表（并结算自己回城的船队）: c=overseastrade&m=refresh&p=页
     */
    public static function refresh(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $page = (int)$ctx->input('p', 0);
        return Member::locked($uid, static function () use ($uid, $page): array {
            /**
             * 2 未加入国家
             * 3 等级不足
             *-1 获取用户信息失败
             *-4 获取货物信息失败
             */
            $return = ['status' => 0, 'trade' => 0, 'endtime' => 0, 'team_id' => 0, 'get' => 0];
            $now = time();
            $member = self::member($uid);
            $gate = self::gate($member);
            if ($gate !== 0) {
                $return['status'] = $gate;
                return $return;
            }
            $info = self::memberInfo($uid, $now);
            // 判断是否组队
            if ($info['team'] > 0) {
                $team = self::teamInfo($info['team']);
                $return['team_id'] = $info['team'];
                if ($team !== null) {
                    if (($team['status'] === 1 && $now - $team['startdateline'] >= self::TIME) || $team['status'] === 2) {
                        $site = self::siteOf($team, $uid);
                        $commodity = self::commodityList($member);
                        if (!isset($commodity[$info['commodity']])) {
                            $return['status'] = -4;
                            return $return;
                        }
                        // 判断自己队伍信息是否正常
                        if ($site !== 0) {
                            $settle = self::settle($uid, $member, $info, $team, $commodity, $now);
                            if ($settle !== null) {
                                $return['trade'] = $settle['trade'];
                                $return['get'] = $settle['get'];
                            }
                        }
                    } elseif ($team['status'] === 1) {
                        // 正在贸易
                        $return['trade'] = 1;
                        $return['endtime'] = self::TIME - ($now - $team['startdateline']);
                    }
                }
            }
            // 队伍信息（旧版用结算前读到的队伍 id）
            $return['status'] = 1;
            $return['team'] = self::getTeam($member, $info['team'], $page, $now);
            return $return;
        });
    }

    /**
     * 获取贸易记录: c=overseastrade&m=get_trade_log&p=页
     */
    public static function get_trade_log(Context $ctx): array
    {
        $return = ['status' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        $page = (int)$ctx->input('p', 0);
        $page = $page > 0 ? $page : 1;
        $list = DB::table('overseas_trade_log')->select(...self::LOG_FIELDS)
            ->whereRaw('(`member1` = ? OR `member2` = ? OR `attack_uid` = ?)', [$uid, $uid, $uid])->whereNull('deleted_at')
            ->orderBy('dateline', 'DESC')->orderBy('id', 'DESC')->limit(self::LOG_LIMIT)->offset(($page - 1) * self::LOG_LIMIT)->get();
        // 用来保存组装后的记录
        $newList = [];
        foreach ($list as $key => $val) {
            $val = array_map(static fn(mixed $v): mixed => is_numeric($v) ? (int)$v : $v, $val);
            $message = $val;
            $message['dateline'] = date('Y-n-d H:i', $val['dateline']);
            if ($val['type'] === 1) {
                // 单人贸易 1 / 多人贸易 2 / 贸易完成 7
                $message['type'] = $val['member1'] === 0 || $val['member2'] === 0 ? 1 : 2;
                if ($val['member1_get'] !== 0 || $val['member2_get'] !== 0) {
                    $message['type'] = 7;
                }
            } elseif ($val['type'] === 2) {
                if ($val['attack_uid'] === $uid) {
                    // 抢夺方：抢夺成功 3 / 抢夺失败 4
                    $message['type'] = $val['win'] === 1 ? 3 : 4;
                } else {
                    // 防守方：防守成功 5 / 防守失败 6
                    $message['type'] = $val['win'] === 0 ? 5 : 6;
                }
            }
            $newList[$key] = $message;
        }
        $return['status'] = 1;
        $return['list'] = $newList;
        if ($newList === [] && $page > 1) {
            $page -= 1;
        }
        $return['page'] = $page;
        return $return;
    }

    /**
     * 查看战报信息: c=overseastrade&m=get_report_info&id=战报id
     */
    public static function get_report_info(Context $ctx): array
    {
        $return = ['status' => 0];
        $id = (int)$ctx->input('id', 0);
        $row = DB::table('overseas_trade_report')->select('report', 'nickname', 'userlevel', 'name', 'level', 'background')
            ->where('id', $id)->whereNull('deleted_at')->first();
        if ($row === null) {
            $return['status'] = -1;
            return $return;
        }
        $report = json_decode((string)$row['report'], true);
        $return['status'] = 1;
        $return['battle'] = is_array($report) ? $report : [];
        $return['info']['background'] = (int)$row['background'];
        // 组装攻击方 / 防守方人员信息
        $return['battle']['left_role'] = ['name' => (string)$row['nickname'], 'level' => (int)$row['userlevel']];
        $return['battle']['right_role'] = ['name' => (string)$row['name'], 'level' => (int)$row['level']];
        // 胜利状态
        $return['info']['win'] = is_array($report) ? ($report['assess'] ?? null) : null;
        return $return;
    }

    /**
     * 购买打劫次数: c=overseastrade&m=buy
     */
    public static function buy(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        return Member::locked($uid, static function () use ($uid): array {
            $return = [];
            $now = time();
            $member = self::member($uid);
            if ($member === null || (int)$member['vip'] < 10) {
                // -6vip不足
                $return['status'] = -6;
                $return['msg'] = self::LANG['overseatrade_vip_limit'];
                return $return;
            }
            // 获取今天可购买的次数
            $all = self::robTime($uid, (int)$member['vip'], $now);
            if ($all === []) {
                // -1获取可购买次数失败
                $return['status'] = -1;
                $return['msg'] = self::LANG['overseatrade_buy_error'];
                return $return;
            }
            if ((int)($all['times'] ?? 0) === 0) {
                // -2没有可购买的次数
                $return['status'] = -2;
                $return['msg'] = self::LANG['overseatrade_buy_limit'];
                return $return;
            }
            // 价格：今天第一次 50，之后每次 + 10，最多 100（旧版先记购买记录再判断元宝，元宝不足时价格也会涨）
            $gold = self::buyPrice($uid, $now);
            DB::table('overseas_trade_buy_log')->insert(['uid' => $uid, 'gold' => $gold, 'dateline' => $now]);
            // 扣元宝
            if (!Member::deductCurrency($uid, gold: $gold)) {
                // -4元宝不足
                $return['status'] = -4;
                $return['msg'] = self::LANG['overseatrade_gold_limit'];
                return $return;
            }
            $info = self::memberInfo($uid, $now);
            // 扣次数、加次数
            DB::table('overseas_trade_rob')->where('uid', $uid)->whereNull('deleted_at')
                ->update(['times' => max(0, (int)$all['times'] - 1), 'dateline' => $now]);
            self::updateMemberInfo($uid, ['re_robtimes' => $info['re_robtimes'] + 1], $now);
            // 返还前端下一次的数据
            $gold = $gold >= self::MAX_GOLD ? self::MAX_GOLD : $gold + 10;
            $return['status'] = 1;
            $return['gold'] = $gold;
            $return['all_gold'] = (int)DB::table('member')->where('uid', $uid)->value('gold');
            $return['times'] = (int)$all['times'] - 1;
            $return['robtimes'] = $info['re_robtimes'] + $info['robtimes'] + 1;
            return $return;
        });
    }

    /**
     * 清除玩家未出发的队伍（overseastrade_model->clear_team：退出 / 被踢出国家时由 CountryHandler 调用）
     *
     * @return int 1 已清理；2 没有组队；4 正在贸易；5 退出失败
     */
    public static function clearTeam(int $uid): int
    {
        $now = time();
        $info = self::memberInfo($uid, $now);
        // 判断是否组队
        if ($info['team'] === 0) {
            return 2;
        }
        // 取自己的队伍信息
        $team = self::teamInfo($info['team']);
        if ($team === null) {
            // 用户队伍清理
            self::updateMemberInfo($uid, ['team' => 0], $now, false);
            return 1;
        }
        // 判断是否正在贸易
        if ($team['status'] !== 0) {
            return 4;
        }
        // 执行退出
        if (self::deleteTeam($info['team'], $now) !== 1) {
            return 5;
        }
        self::updateMemberInfo($uid, ['team' => 0], $now, false);
        return 1;
    }

    /**
     * 回城结算（index / refresh 共用）：被劫收益减半（trade 3），否则补记贸易完成记录（trade 2）；
     * 以队伍 id 未变为条件清空自己的队伍与货物，清空成功才发银币、计主线任务 14
     *
     * @param array<string, mixed> $member
     * @param array<string, int> $info
     * @param array<string, mixed> $team
     * @param array<int, array<string, mixed>> $commodity
     * @return array{trade: int, get: int}|null 并发已结算时为 null
     */
    private static function settle(int $uid, array $member, array $info, array $team, array $commodity, int $now): ?array
    {
        // 完整收益
        $get = (int)(($commodity[$info['commodity']]['get'] ?? 0) * (int)$member['level']);
        if ($team['status'] === 2) {
            // 被劫，收益减半
            $get = (int)ceil($get / 2);
            $trade = 3;
        } else {
            // 先获取该记录是否已经存在，没有就添加记录
            $exists = DB::table('overseas_trade_log')->where('team_id', $team['id'])->where('type', 1)
                ->whereRaw('(`member1_get` != 0 OR `member2_get` != 0)')->whereNull('deleted_at')->exists();
            if (!$exists) {
                $data = [
                    'type' => 1,
                    'team_id' => $team['id'],
                    'member1' => $team['member1'],
                    'member1_name' => $team['member1_name'],
                    'member2' => $team['member2'],
                    'member2_name' => $team['member2_name'],
                    'dateline' => $now,
                ];
                if ($team['member1'] > 0) {
                    $data['member1_get'] = $team['member1_level'] * (int)($commodity[$team['member1_commodity']]['get'] ?? 0);
                }
                if ($team['member2'] > 0) {
                    $data['member2_get'] = $team['member2_level'] * (int)($commodity[$team['member2_commodity']]['get'] ?? 0);
                }
                self::addTradeLog($data);
            }
            $trade = 2;
        }
        // 清空自己的组队状态
        $cleared = DB::table('overseas_trade_info')->where('uid', $uid)->where('team', $info['team'])->whereNull('deleted_at')
            ->update(['team' => 0, 'commodity' => 0, 'dateline' => $now]);
        if ($cleared === 0) {
            return null;
        }
        // 添加银币
        Member::addCurrency($uid, silver: $get);
        // 主线任务 14完成一次海外贸易（无论是否被劫-有获得那一刻就完成）
        MainQuestTask::action($uid, 14, 1);
        return ['trade' => $trade, 'get' => $get];
    }

    /**
     * 获取所有正在贸易中的队伍（get_team：按出发后 5 / 10 / 15 分钟分三栏，每栏每页 3 条，自己的船队排在所在栏最前）
     *
     * @param array<string, mixed> $member
     * @return array{allpage: int, list: list<array<string, mixed>>}
     */
    private static function getTeam(array $member, int $teamid, int $page, int $now): array
    {
        $list = $newlist = ['five' => [], 'ten' => [], 'fifteen' => []];
        $commodity = self::commodityList($member);
        $rows = DB::table('overseas_trade_team')->select(...self::TEAM_FIELDS)->where('status', 1)
            ->where('startdateline', '>', $now - self::TIME)->whereNull('deleted_at')->orderBy('id')->get();
        foreach ($rows as $teamInfo) {
            $teamInfo = self::castTeam($teamInfo);
            $teamInfo['endtime'] = self::TIME - ($now - $teamInfo['startdateline']);
            if ($teamInfo['member1_level'] > $teamInfo['member2_level']) {
                $site = 1;
            } elseif ($teamInfo['member1_level'] === $teamInfo['member2_level'] && $teamInfo['member1_commodity'] > $teamInfo['member2_commodity']) {
                $site = 1;
            } else {
                $site = 2;
            }
            // 取抢夺可获得银币
            $teamInfo['get'] = isset($commodity[$teamInfo['member' . $site . '_commodity']])
                ? (int)ceil($teamInfo['member' . $site . '_level'] * $commodity[$teamInfo['member' . $site . '_commodity']]['get'] / 2)
                : 0;
            $elapsed = $now - $teamInfo['startdateline'];
            $key = $elapsed < 300 ? 'five' : ($elapsed < 600 ? 'ten' : 'fifteen');
            if ($teamInfo['id'] === $teamid) {
                // 这是自己的队伍，要放在第一页
                array_unshift($list[$key], $teamInfo);
            } else {
                $list[$key][] = $teamInfo;
            }
        }
        // 分页
        $num = 0;
        $i = 0;
        foreach ($list as $key => $val) {
            $num = max($num, count($val));
            $offect = ($page - 1) * self::LIMIT;
            if ($val !== []) {
                $slice = array_slice($val, $offect, self::LIMIT);
                usort($slice, static fn(array $a, array $b): int => $a['endtime'] <=> $b['endtime']);
                $j = 1;
                foreach ($slice as $smallKey => $smallVal) {
                    $smallVal['site'] = $i * self::LIMIT + $j;
                    $newlist[$key][$smallKey] = $smallVal;
                    $j += 1;
                }
            }
            $i += 1;
        }
        $allPage = (int)ceil($num / self::LIMIT);
        return [
            'allpage' => $allPage > 0 ? $allPage : 1,
            'list' => array_merge($newlist['five'], $newlist['ten'], $newlist['fifteen']),
        ];
    }

    /**
     * 国家 / 等级判断：没有国家 2，不足 51 级 3，通过 0
     *
     * @param array<string, mixed>|null $member
     */
    private static function gate(?array $member): int
    {
        if ($member === null || (int)$member['country'] === 0) {
            return 2;
        }
        if ((int)$member['level'] < self::LEVEL) {
            return 3;
        }
        return 0;
    }

    /**
     * 玩家贸易数据（get_member_info：没有时建档；隔天把贸易 / 打劫次数重置为 5）
     *
     * @return array{uid: int, times: int, robtimes: int, re_robtimes: int, team: int, commodity: int, dateline: int}
     */
    private static function memberInfo(int $uid, int $now): array
    {
        $row = DB::table('overseas_trade_info')->select(...self::INFO_FIELDS)->where('uid', $uid)->whereNull('deleted_at')->first();
        if ($row === null) {
            $data = ['uid' => $uid, 'times' => self::TIMES, 'robtimes' => self::ROBTIMES, 're_robtimes' => 0, 'commodity' => 0, 'team' => 0, 'dateline' => $now];
            DB::execute(
                'INSERT IGNORE INTO `zc_sanguo_overseas_trade_info` (`uid`, `times`, `robtimes`, `re_robtimes`, `commodity`, `team`, `dateline`)
                 VALUES (:uid, :times, :robtimes, 0, 0, 0, :now)',
                ['uid' => $uid, 'times' => self::TIMES, 'robtimes' => self::ROBTIMES, 'now' => $now]
            );
            return $data;
        }
        $data = array_map('intval', $row);
        // 更新每日贸易次数
        if (date('ymd', $now) !== date('ymd', $data['dateline'])) {
            $affected = DB::table('overseas_trade_info')->where('uid', $uid)->where('dateline', $data['dateline'])->whereNull('deleted_at')
                ->update(['times' => self::TIMES, 'robtimes' => self::ROBTIMES, 'dateline' => $now]);
            if ($affected > 0) {
                $data['times'] = self::TIMES;
                $data['robtimes'] = self::ROBTIMES;
            }
        }
        return $data;
    }

    /**
     * 修改玩家贸易数据（update_member_info：改自己的数据时同时记最后活动时间）
     *
     * @param array<string, int> $data
     */
    private static function updateMemberInfo(int $uid, array $data, int $now, bool $self = true): void
    {
        if ($self) {
            $data['dateline'] = $now;
        }
        DB::table('overseas_trade_info')->where('uid', $uid)->whereNull('deleted_at')->update($data);
    }

    /**
     * 扣一次贸易次数（旧版写回读到的次数 - 1，新版条件减 1）
     */
    private static function subTimes(int $uid, int $now, bool $self): void
    {
        DB::execute(
            'UPDATE `zc_sanguo_overseas_trade_info` SET `times` = `times` - 1' . ($self ? ', `dateline` = :now' : '') . '
             WHERE `uid` = :uid AND `times` > 0 AND `deleted_at` IS NULL',
            $self ? ['now' => $now, 'uid' => $uid] : ['uid' => $uid]
        );
    }

    /**
     * 货物列表（get_item_list：银币价格 × 主公等级，open 为 VIP 是否够），按 id 索引
     *
     * @param array<string, mixed> $member
     * @return array<int, array<string, mixed>>
     */
    private static function commodityList(array $member): array
    {
        $list = [];
        foreach (OverseasTradeShop::ROWS as $val) {
            if ($val['buy_type'] === 1) {
                $val['buy_value'] = (int)$member['level'] * $val['buy_value'];
            }
            $val['open'] = (int)$member['vip'] >= $val['need_vip'] ? 1 : 0;
            $list[$val['id']] = $val;
        }
        return $list;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function teamInfo(int $id): ?array
    {
        $row = DB::table('overseas_trade_team')->select(...self::TEAM_FIELDS)->where('id', $id)->whereNull('deleted_at')->first();
        return $row === null ? null : self::castTeam($row);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function castTeam(array $row): array
    {
        foreach ($row as $key => $value) {
            if (!in_array($key, ['country_name', 'member1_name', 'member2_name'], true)) {
                $row[$key] = (int)$value;
            }
        }
        return $row;
    }

    /**
     * 自己在队伍里的位置（1 / 2），不在队伍里为 0
     *
     * @param array<string, mixed> $team
     */
    private static function siteOf(array $team, int $uid): int
    {
        $site = 0;
        for ($i = 1; $i <= 2; $i++) {
            if ($team['member' . $i] === $uid) {
                $site = $i;
            }
        }
        return $site;
    }

    /**
     * 退出队伍（goout：旧版删除整行，新版软删除）
     */
    private static function deleteTeam(int $id, int $now): int
    {
        DB::table('overseas_trade_team')->where('id', $id)->whereNull('deleted_at')->update(['deleted_at' => date('Y-m-d H:i:s', $now)]);
        return 1;
    }

    /**
     * 可购买的打劫次数（get_rob_time：没有时建档，隔天按 VIP 10 ~ 13 重置为 5 / 10 / 15 / 20；VIP 不在 10 ~ 13 时没有 times）
     *
     * @return array<string, int>
     */
    private static function robTime(int $uid, int $vip, int $now): array
    {
        $row = DB::table('overseas_trade_rob')->select('uid', 'times', 'dateline')->where('uid', $uid)->whereNull('deleted_at')->first();
        $data = ['dateline' => $now];
        if (isset(self::ROB_TIMES[$vip])) {
            $data['times'] = self::ROB_TIMES[$vip];
        }
        if ($row === null) {
            // 初始化用户信息
            DB::execute(
                'INSERT IGNORE INTO `zc_sanguo_overseas_trade_rob` (`uid`, `times`, `dateline`) VALUES (:uid, :times, :now)',
                ['uid' => $uid, 'times' => $data['times'] ?? 0, 'now' => $now]
            );
            return ['uid' => $uid] + $data;
        }
        $row = array_map('intval', $row);
        if (date('ymd', $now) !== date('ymd', $row['dateline'])) {
            DB::table('overseas_trade_rob')->where('uid', $uid)->whereNull('deleted_at')->update($data);
            return $data;
        }
        return $row;
    }

    /**
     * 购买打劫次数的当前价格（今天最近一次购买 + 10，最多 100；今天没买过为 50）
     */
    private static function buyPrice(int $uid, int $now): int
    {
        $last = DB::table('overseas_trade_buy_log')->select('gold')->where('uid', $uid)
            ->where('dateline', '>=', strtotime(date('Ymd', $now)))->whereNull('deleted_at')
            ->orderBy('dateline', 'DESC')->orderBy('id', 'DESC')->first();
        if ($last === null) {
            return self::MIN_GOLD;
        }
        return (int)$last['gold'] >= self::MAX_GOLD ? self::MAX_GOLD : (int)$last['gold'] + 10;
    }

    /**
     * 记录打劫战报（record_pk_report）
     *
     * @param array<string, mixed> $info
     */
    private static function recordPkReport(array $info): int
    {
        return (int)DB::table('overseas_trade_report')->insertGetId([
            'nickname' => (string)$info['from'],
            'userlevel' => (int)$info['fromlevel'],
            'name' => (string)$info['to'],
            'level' => (int)$info['tolevel'],
            'report' => json_encode($info['report'], JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * 添加贸易记录（add_trade_log）
     *
     * @param array<string, mixed> $data
     */
    private static function addTradeLog(array $data): void
    {
        DB::table('overseas_trade_log')->insert($data);
    }

    /**
     * 记录国家战报 - 海外贸易（countryfight_model->add_fight_notice_log(船员, $win, 1)：被劫国家取成员 1，没有时取成员 2）
     *
     * @param array<string, mixed> $member 打劫者
     * @param array<string, mixed> $touid member1 / member1_name / member2 / member2_name
     */
    private static function addFightNoticeLog(array $member, array $touid, int $win, int $now): void
    {
        $toUid = (int)($touid['member1'] ?: $touid['member2']);
        $toMemberCountry = DB::table('member_country')->where('uid', $toUid)->whereNull('deleted_at')->first();
        $toCountry = $toMemberCountry !== null ? CountryHandler::countryInfo((int)$toMemberCountry['country']) : null;
        $myMemberCountry = DB::table('member_country')->where('uid', (int)$member['uid'])->whereNull('deleted_at')->first();
        $myCountry = $myMemberCountry !== null ? CountryHandler::countryInfo((int)$myMemberCountry['country']) : null;
        DB::table('country_fight_notice')->insert([
            'type' => self::FIGHT_NOTICE_TYPE,
            'attack_uid' => (int)$member['uid'],
            'attack_nickname' => (string)$member['nickname'],
            'attack_country' => (int)$member['country'],
            'attack_country_name' => (string)($myCountry['name'] ?? ''),
            'win' => $win,
            'dateline' => $now,
            'member_country' => (int)($toCountry['id'] ?? 0),
            'member_country_name' => (string)($toCountry['name'] ?? ''),
            'member1' => (int)$touid['member1'],
            'member1_name' => (string)$touid['member1_name'],
            'member2' => (int)$touid['member2'],
            'member2_name' => (string)$touid['member2_name'],
        ]);
    }

    private static function em(int $uid, string $nickname): string
    {
        return '<em uid="' . $uid . '">' . $nickname . '</em>';
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function member(int $uid): ?array
    {
        return DB::table('member')->select('uid', 'nickname', 'gender', 'level', 'vip', 'gold', 'silver', 'country')
            ->where('uid', $uid)->whereNull('deleted_at')->first();
    }
}
