<?php

declare(strict_types=1);

namespace Hanjiang\WebSocket;

use Hanjiang\Handler\Activity\EliteHandler;
use Hanjiang\Handler\Pvp\CountryHandler;
use Hanjiang\MasterData\EliteCity;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Logger;
use OmniPHP\WebSocket\Context;
use Workerman\Connection\TcpConnection;
use Workerman\Timer;

/**
 * 多人副本实时组队房间（还原旧版 node / socket.io 实时服务器，旧版代码包里没有这个服务器）
 *
 * 协议全部按客户端 public/static/js/view/elitepop.js 还原：客户端经 socket.io.min.js 适配层发
 * {action: 事件名, data: 参数}（参数可能是标量，如 startfight 发 1、eliteindex 发 ''），这里只读 data；
 * 下行帧为 {type: 事件名, data: 载荷}，适配层把 data 原样交给 socket.on 回调，所以 loginstatus / *_error 的载荷是整数。
 *
 * 队伍状态全部在 Redis（hj: 前缀）：
 * - elite:team:{teamid}       队伍（军团、队长、类型、成员及位置）
 * - elite:teams:{fid}         军团下的队伍 id 列表（按创建顺序）
 * - elite:player:{uid}        玩家当前所在队伍 id
 * - elite:online:{uid}        玩家的实时会话 {sid, worker, fid}（fid 为正在查看的军团）
 * - elite:viewers:{fid}       正在查看该军团的玩家 uid => 首杀标记（teamlist 只推给这些人）
 * - elite:sessions:{worker}   该 ws 进程登录过的 uid（进程重启时清理残留队伍）
 * - elite:mailbox:{worker}    推给其它 ws 进程上玩家的消息（ws 进程数 > 1 时由定时器投递）
 * 所有改动队伍 / 观众的操作都在全局锁 elite:lock 内完成，多个 ws 进程也不会并发改坏队伍。
 */
final class EliteRoom
{
    /** elitepop.js 发送的事件（login 之外都要求已登录） */
    public const array EVENTS = [
        'login', 'eliteindex', 'teamlist', 'createTeam', 'jointeam', 'exitteam', 'fireteam',
        'firemember', 'changeorder', 'teaminvited', 'startfight',
    ];

    /** 队伍人数上限（elitepop.js 的队伍列表显示「人数 x/3」、队伍详情固定 3 个位置） */
    private const int TEAM_MAX = 3;

    /** 开战最少人数（旧版 action：队员去重后只有 1 人时不能开战） */
    private const int TEAM_MIN = 2;

    /** 各事件失败时回给客户端的事件名 */
    private const array ERROR_EVENT = [
        'eliteindex' => 'eliteindex_error',
        'teamlist' => 'teamlist_error',
        'createTeam' => 'createteam_error',
        'jointeam' => 'jointeam_error',
        'exitteam' => 'exitteam_error',
        'fireteam' => 'fireteam_error',
        'firemember' => 'firemember_error',
        'changeorder' => 'changeorder_error',
        'startfight' => 'startfight_error',
    ];

    /**
     * 服务端内部错误（加锁超时 / 异常）的错误码：旧版客户端没有对应提示，按各 *_error 的 default 分支处理
     * （startfight_error 另用 -5「进入战斗失败，请稍后重试」）
     */
    private const int CODE_INTERNAL = -9;

    /** Redis 键存活秒数（每次写入刷新；多人副本每段开放 2 小时） */
    private const int TTL = 21600;

    private const string LOCK_KEY = 'elite:lock';
    private const int LOCK_TTL = 10;
    private const float LOCK_WAIT = 5.0;

    /** 每次定时投递最多处理的跨进程消息数 */
    private const int MAILBOX_BATCH = 500;

    private static int $workerId = 0;

    /** @var array<int, array{sid: string, send: \Closure(string): void, conn: ?TcpConnection}> 本进程的会话 */
    private static array $local = [];

    private static bool $inLock = false;
    private static int $seq = 0;
    private static ?int $mailboxTimer = null;

    /** @var (\Closure(): int)|null 测试用时间源，生产代码不设置 */
    private static ?\Closure $clock = null;

    /**
     * ws 进程启动：清理本进程上次运行残留的会话与队伍；多进程时启动跨进程投递定时器
     */
    public static function init(int $workerId, int $workerCount): void
    {
        self::$workerId = $workerId;
        self::$local = [];

        self::locked(static function () use ($workerId): void {
            $uids = Redis::get('elite:sessions:' . $workerId);
            foreach (is_array($uids) ? $uids : [] as $uid) {
                $online = Redis::get('elite:online:' . (int)$uid);
                if (is_array($online) && (int)$online['worker'] === $workerId) {
                    self::leave((int)$uid);
                }
            }
            Redis::del('elite:sessions:' . $workerId, 'elite:mailbox:' . $workerId);
        });

        if (self::$mailboxTimer !== null) {
            Timer::del(self::$mailboxTimer);
            self::$mailboxTimer = null;
        }
        if ($workerCount > 1) {
            self::$mailboxTimer = Timer::add(0.1, static function (): void {
                self::drainMailbox();
            });
        }
    }

    /**
     * 测试用：替换开放时间判断用的时间源（null 恢复 time()）
     */
    public static function useClock(?\Closure $clock): void
    {
        self::$clock = $clock;
    }

    public static function handles(string $action): bool
    {
        return in_array($action, self::EVENTS, true);
    }

    public static function dispatch(Context $ctx): void
    {
        $action = $ctx->action();
        $arg = $ctx->input('data');
        $uid = 0;
        try {
            if ($action === 'login') {
                self::login($ctx, is_array($arg) ? $arg : []);
                return;
            }

            $uid = self::uidOf($ctx);
            if ($uid <= 0) {
                // 客户端 loginstatus 0：您已经失去登录状态，请重新进入多人副本
                self::reply($ctx, 'loginstatus', 0);
                return;
            }

            $arg = is_array($arg) ? $arg : [];
            match ($action) {
                'eliteindex' => self::eliteIndex($uid),
                'teamlist' => self::teamList($uid, $arg),
                'createTeam' => self::createTeam($uid, $arg),
                'jointeam' => self::joinTeam($uid, $arg),
                'exitteam' => self::exitTeam($uid, $arg),
                'fireteam' => self::fireTeam($uid, $arg),
                'firemember' => self::fireMember($uid, $arg),
                'changeorder' => self::changeOrder($uid, $arg),
                'teaminvited' => self::teamInvited($uid, $arg),
                'startfight' => self::startFight($uid),
                default => null,
            };
        } catch (\Throwable $e) {
            Logger::error('[EliteRoom] ' . $action . ' 失败: ' . $e->getMessage(), ['uid' => $uid]);
            if ($action === 'login') {
                self::reply($ctx, 'loginstatus', -2);
            } elseif (isset(self::ERROR_EVENT[$action]) && $uid > 0) {
                self::push($uid, self::ERROR_EVENT[$action], $action === 'startfight' ? -5 : self::CODE_INTERNAL);
            }
        }
    }

    /**
     * 连接断开（含心跳超时被 sweep 关闭）：只处理仍是当前会话的连接
     */
    public static function onClose(TcpConnection $connection): void
    {
        $uid = (int)($connection->eliteUid ?? 0);
        $sid = (string)($connection->eliteSid ?? '');
        if ($uid > 0 && $sid !== '') {
            self::disconnect($uid, $sid);
        }
    }

    /**
     * 会话结束：离开军团观众列表与队伍（队长离开即解散，elitepop.js 队长关闭界面前会提示「退出」确认）
     */
    public static function disconnect(int $uid, ?string $sid = null): void
    {
        $sid ??= self::$local[$uid]['sid'] ?? null;
        if ($sid === null) {
            return;
        }
        if ((self::$local[$uid]['sid'] ?? null) === $sid) {
            unset(self::$local[$uid]);
        }
        self::locked(static function () use ($uid, $sid): void {
            $online = Redis::get('elite:online:' . $uid);
            if (is_array($online) && $online['sid'] === $sid) {
                self::leave($uid);
            }
        });
    }

    /**
     * 跨进程投递：取出发给本进程会话的消息
     */
    public static function drainMailbox(): void
    {
        for ($i = 0; $i < self::MAILBOX_BATCH; $i++) {
            $raw = Redis::lPop('elite:mailbox:' . self::$workerId);
            if (!is_string($raw)) {
                return;
            }
            $msg = json_decode($raw, true);
            if (!is_array($msg)) {
                continue;
            }
            $session = self::$local[(int)$msg['uid']] ?? null;
            if ($session !== null && $session['sid'] === $msg['sid']) {
                ($session['send'])((string)$msg['frame']);
            }
        }
    }

    // ---------------------------------------------------------------
    // 事件
    // ---------------------------------------------------------------

    /**
     * login {uid, token, serverid} → loginstatus
     * 1 成功；-1 缺少参数；0 登录状态无效（token 不对或与 uid 不符）；-2 取不到玩家信息；-3 不在开放时间（客户端走 default「无法进入多人副本」）
     *
     * @param array<string, mixed> $arg
     */
    private static function login(Context $ctx, array $arg): void
    {
        $token = is_scalar($arg['token'] ?? null) ? trim((string)$arg['token']) : '';
        if ($token === '') {
            self::reply($ctx, 'loginstatus', -1);
            return;
        }
        // 与 HTTP 同一套 token（MemberHandler::resolveUid：只认 token，不回退到客户端给的 uid）
        $cached = Redis::get('token:' . $token);
        $uid = $cached !== null ? (int)$cached : 0;
        if ($uid <= 0 || (isset($arg['uid']) && is_scalar($arg['uid']) && (int)$arg['uid'] !== $uid)) {
            self::reply($ctx, 'loginstatus', 0);
            return;
        }
        if (!EliteHandler::isOpen(self::now())) {
            self::reply($ctx, 'loginstatus', -3);
            return;
        }
        if (self::member($uid) === null) {
            self::reply($ctx, 'loginstatus', -2);
            return;
        }

        $conn = $ctx->connection();
        $sid = self::$workerId . ':' . ($conn !== null ? 'c' . $conn->id : 'm' . spl_object_id($ctx)) . ':' . (++self::$seq);
        $old = self::$local[$uid] ?? null;

        self::locked(static function () use ($uid, $sid, $ctx, $conn): void {
            // 同一玩家重复进入：旧会话作废（旧队伍按离开处理，客户端重新进入时界面是空的）
            if (Redis::get('elite:online:' . $uid) !== null) {
                self::leave($uid);
            }
            self::$local[$uid] = [
                'sid' => $sid,
                'send' => $conn !== null
                    ? static function (string $frame) use ($conn): void {
                        $conn->send($frame);
                    }
                    : static function (string $frame) use ($ctx): void {
                        $ctx->sentMessages[] = $frame;
                    },
                'conn' => $conn,
            ];
            if ($conn !== null) {
                $conn->eliteUid = $uid;
                $conn->eliteSid = $sid;
            }
            Redis::set('elite:online:' . $uid, ['sid' => $sid, 'worker' => self::$workerId, 'fid' => 0], self::TTL);
            $sessions = Redis::get('elite:sessions:' . self::$workerId);
            $sessions = is_array($sessions) ? $sessions : [];
            if (!in_array($uid, $sessions, true)) {
                $sessions[] = $uid;
            }
            Redis::set('elite:sessions:' . self::$workerId, $sessions, self::TTL);
        });

        if ($old !== null && $old['conn'] !== null && $old['conn'] !== $conn) {
            $old['conn']->eliteUid = 0;
            $old['conn']->close();
        }
        self::push($uid, 'loginstatus', 1);
    }

    /**
     * eliteindex → eliteindex {list: [{fid, name, open, firstkill}], number, supper_group, countryname}
     * 失败 eliteindex_error（get_member_info 的 status）
     */
    private static function eliteIndex(int $uid): void
    {
        $info = EliteHandler::memberInfo($uid);
        if ($info['status'] !== 1) {
            self::push($uid, 'eliteindex_error', $info['status']);
            return;
        }
        $list = [];
        foreach ($info['list'] as $fid => $row) {
            $list[] = [
                'fid' => $fid,
                'name' => EliteCity::ROWS[$fid]['name'] ?? '',
                'open' => $row['open'],
                'firstkill' => $row['firstkill'],
            ];
        }
        usort($list, static fn (array $a, array $b): int => $a['fid'] <=> $b['fid']);
        self::push($uid, 'eliteindex', [
            'list' => $list,
            'number' => $info['number'],
            'supper_group' => $info['supper_group'],
            'countryname' => $info['countryname'],
        ]);
    }

    /**
     * teamlist {id} → teamlist {军团奖励行..., id, firstkill, list}，此后该军团队伍变动都推给他
     * teamlist_error：-1 军团不存在，-2 军团未开启
     *
     * @param array<string, mixed> $arg
     */
    private static function teamList(int $uid, array $arg): void
    {
        $fid = self::intArg($arg, 'id');
        if (!isset(EliteCity::ROWS[$fid])) {
            self::push($uid, 'teamlist_error', -1);
            return;
        }
        if (!EliteHandler::cityOpen($uid, $fid)) {
            self::push($uid, 'teamlist_error', -2);
            return;
        }
        $firstKill = EliteHandler::firstKill($uid, $fid);
        self::locked(static function () use ($uid, $fid, $firstKill): void {
            self::watch($uid, $fid, $firstKill);
            self::push($uid, 'teamlist', self::teamListPayload($fid, $firstKill, self::teamSummaries($fid)));
        });
    }

    /**
     * createTeam {id, type, topteam} → createteamdone {teamid} + getteamdone + 该军团 teamlist
     * createteam_error：-1 已在队伍里，-2 国家队伍但没有国家，-3 军团不存在 / 未开启
     *
     * @param array<string, mixed> $arg
     */
    private static function createTeam(int $uid, array $arg): void
    {
        $fid = self::intArg($arg, 'id');
        $type = self::intArg($arg, 'type') === 2 ? 2 : 1;
        $topteam = self::boolArg($arg, 'topteam') ? 1 : 0;

        self::locked(static function () use ($uid, $fid, $type, $topteam): void {
            if (self::playerTeamId($uid) > 0) {
                self::push($uid, 'createteam_error', -1);
                return;
            }
            if (!EliteHandler::cityOpen($uid, $fid)) {
                self::push($uid, 'createteam_error', -3);
                return;
            }
            $member = self::member($uid);
            if ($member === null) {
                self::push($uid, 'createteam_error', -3);
                return;
            }
            $countryId = 0;
            $countryName = '';
            if ($type === 2) {
                $countryId = (int)$member['country'];
                $country = $countryId > 0 ? CountryHandler::countryInfo($countryId) : null;
                if ($country === null) {
                    self::push($uid, 'createteam_error', -2);
                    return;
                }
                $countryName = (string)$country['name'];
            }

            $teamId = (int)Redis::incr('elite:teamid');
            $team = [
                'teamid' => $teamId,
                'fid' => $fid,
                // 旧版队伍名规则未知（实时服务器缺失），按队长昵称
                'name' => (string)$member['nickname'],
                'leader' => $uid,
                'topteam' => $topteam,
                'type' => $type,
                'countryid' => $countryId,
                'countryname' => $countryName,
                'invited' => 0,
                'members' => [self::memberEntry($member, 1)],
            ];
            self::saveTeam($team);
            $ids = self::teamIds($fid);
            $ids[] = $teamId;
            Redis::set('elite:teams:' . $fid, $ids, self::TTL);
            Redis::set('elite:player:' . $uid, $teamId, self::TTL);
            self::watch($uid, $fid, EliteHandler::firstKill($uid, $fid));

            self::push($uid, 'createteamdone', ['teamid' => $teamId]);
            self::pushTeamDetail($team);
            self::broadcastTeamList($fid);
        });
    }

    /**
     * jointeam {id, teamid} → jointeamdone {teamid} + 全队 getteamdone + 该军团 teamlist
     * jointeam_error：0 已在队伍里，-1 军团不存在 / 未开启，-2 队伍已解散，-3 队伍已满，-4 国家队伍但没有国家，-5 国家不符
     *
     * @param array<string, mixed> $arg
     */
    private static function joinTeam(int $uid, array $arg): void
    {
        $fid = self::intArg($arg, 'id');
        $teamId = self::intArg($arg, 'teamid');

        self::locked(static function () use ($uid, $fid, $teamId): void {
            if (self::playerTeamId($uid) > 0) {
                self::push($uid, 'jointeam_error', 0);
                return;
            }
            if (!EliteHandler::cityOpen($uid, $fid)) {
                self::push($uid, 'jointeam_error', -1);
                return;
            }
            $team = self::loadTeam($teamId);
            if ($team === null || $team['fid'] !== $fid) {
                self::push($uid, 'jointeam_error', -2);
                return;
            }
            if (count($team['members']) >= self::TEAM_MAX) {
                self::push($uid, 'jointeam_error', -3);
                return;
            }
            $member = self::member($uid);
            if ($member === null) {
                self::push($uid, 'jointeam_error', -2);
                return;
            }
            if ($team['countryid'] > 0) {
                if ((int)$member['country'] <= 0) {
                    self::push($uid, 'jointeam_error', -4);
                    return;
                }
                // 旧版 elite.php join_team：玩家国家与队伍国家是否一致
                if ((int)$member['country'] !== $team['countryid']) {
                    self::push($uid, 'jointeam_error', -5);
                    return;
                }
            }

            $used = array_column($team['members'], 'order');
            $order = 1;
            while (in_array($order, $used, true)) {
                $order++;
            }
            $team['members'][] = self::memberEntry($member, $order);
            self::saveTeam($team);
            Redis::set('elite:player:' . $uid, $teamId, self::TTL);
            self::watch($uid, $fid, EliteHandler::firstKill($uid, $fid));

            self::push($uid, 'jointeamdone', ['teamid' => $teamId]);
            self::pushTeamDetail($team);
            self::broadcastTeamList($fid);
        });
    }

    /**
     * exitteam {id, teamid} → exitteamdone {teamid} + 其余队员 getteamdone + 该军团 teamlist
     * exitteam_error：0 不在该队伍，-1 军团不存在，-2 队伍已解散，-3 队长不能退出（只能解散）
     *
     * @param array<string, mixed> $arg
     */
    private static function exitTeam(int $uid, array $arg): void
    {
        self::locked(static function () use ($uid, $arg): void {
            $team = self::teamOf($uid, $arg, 'exitteam_error');
            if ($team === null) {
                return;
            }
            if ($team['leader'] === $uid) {
                self::push($uid, 'exitteam_error', -3);
                return;
            }
            $team = self::removeMember($team, $uid);
            self::push($uid, 'exitteamdone', ['teamid' => $team['teamid']]);
            self::pushTeamDetail($team);
            self::broadcastTeamList($team['fid']);
        });
    }

    /**
     * fireteam {id, teamid} → 队长 fireteamdone {teamid}，其余队员 teambeenfire {teamid}，该军团 teamlist
     * fireteam_error：0 不在该队伍，-1 军团不存在，-2 队伍已解散，-3 只有队长才可以解散
     *
     * @param array<string, mixed> $arg
     */
    private static function fireTeam(int $uid, array $arg): void
    {
        self::locked(static function () use ($uid, $arg): void {
            $team = self::teamOf($uid, $arg, 'fireteam_error');
            if ($team === null) {
                return;
            }
            if ($team['leader'] !== $uid) {
                self::push($uid, 'fireteam_error', -3);
                return;
            }
            self::dissolve($team, true);
            self::push($uid, 'fireteamdone', ['teamid' => $team['teamid']]);
            self::broadcastTeamList($team['fid']);
        });
    }

    /**
     * firemember {id, teamid, uid} → 队长 firememberdone {uid}，被踢者 beenfire {teamid}，其余队员 getteamdone，该军团 teamlist
     * firemember_error：0 不在该队伍，-1 军团不存在，-2 队伍信息不存在，-3 不是队长，-4 对方已不在队伍里
     *
     * @param array<string, mixed> $arg
     */
    private static function fireMember(int $uid, array $arg): void
    {
        $target = self::intArg($arg, 'uid');
        self::locked(static function () use ($uid, $arg, $target): void {
            $team = self::teamOf($uid, $arg, 'firemember_error');
            if ($team === null) {
                return;
            }
            if ($team['leader'] !== $uid) {
                self::push($uid, 'firemember_error', -3);
                return;
            }
            if ($target === $uid || !in_array($target, array_column($team['members'], 'uid'), true)) {
                self::push($uid, 'firemember_error', -4);
                return;
            }
            $team = self::removeMember($team, $target);
            self::push($target, 'beenfire', ['teamid' => $team['teamid']]);
            self::push($uid, 'firememberdone', ['uid' => $target]);
            self::pushTeamDetail($team);
            self::broadcastTeamList($team['fid']);
        });
    }

    /**
     * changeorder {id, teamid, from, to} → 全队 getteamdone（from 位置的人换到 to，to 位置原来的人换到 from）
     * changeorder_error：0 不在该队伍，-1 军团不存在，-2 队伍已解散，-3 只有队长才可以调整位置
     *
     * @param array<string, mixed> $arg
     */
    private static function changeOrder(int $uid, array $arg): void
    {
        $from = self::intArg($arg, 'from');
        $to = self::intArg($arg, 'to');
        self::locked(static function () use ($uid, $arg, $from, $to): void {
            $team = self::teamOf($uid, $arg, 'changeorder_error');
            if ($team === null) {
                return;
            }
            if ($team['leader'] !== $uid) {
                self::push($uid, 'changeorder_error', -3);
                return;
            }
            $orders = array_column($team['members'], 'order');
            if ($from !== $to && $from >= 1 && $from <= self::TEAM_MAX && $to >= 1 && $to <= self::TEAM_MAX && in_array($from, $orders, true)) {
                foreach ($team['members'] as $i => $m) {
                    if ($m['order'] === $from) {
                        $team['members'][$i]['order'] = $to;
                    } elseif ($m['order'] === $to) {
                        $team['members'][$i]['order'] = $from;
                    }
                }
                self::saveTeam($team);
            }
            self::pushTeamDetail($team);
        });
    }

    /**
     * teaminvited {id, teamid}：队长已在聊天室发出邀请（c=elite&m=sendinvite 成功后），队伍标记 invited，全队 getteamdone
     * （客户端没有 teaminvited 的失败事件，不符合条件时不回）
     *
     * @param array<string, mixed> $arg
     */
    private static function teamInvited(int $uid, array $arg): void
    {
        self::locked(static function () use ($uid, $arg): void {
            $team = self::loadTeam(self::intArg($arg, 'teamid'));
            if ($team === null || $team['fid'] !== self::intArg($arg, 'id') || $team['leader'] !== $uid) {
                return;
            }
            $team['invited'] = 1;
            self::saveTeam($team);
            self::pushTeamDetail($team);
        });
    }

    /**
     * startfight → 全队 readyfight，调用 EliteHandler::fight（旧版实时服务器调用 c=elite&m=action），
     * 返回值原样作为 enterfight 推给全队；开战成功后队伍清除。
     * startfight_error：0 不在队伍，-1 队伍已解散，-2 不是队长，-3 人数不够，
     * fight 的 -6 元宝不足 → -4，-7 不到 VIP8 → -7，-8 10 秒内重复开战 → -5（同时给队员 readyfight_error 收起等待）
     */
    private static function startFight(int $uid): void
    {
        self::locked(static function () use ($uid): void {
            $teamId = self::playerTeamId($uid);
            if ($teamId <= 0) {
                self::push($uid, 'startfight_error', 0);
                return;
            }
            $team = self::loadTeam($teamId);
            if ($team === null) {
                Redis::del('elite:player:' . $uid);
                self::push($uid, 'startfight_error', -1);
                return;
            }
            if ($team['leader'] !== $uid) {
                self::push($uid, 'startfight_error', -2);
                return;
            }
            if (count($team['members']) < self::TEAM_MIN) {
                self::push($uid, 'startfight_error', -3);
                return;
            }

            $members = $team['members'];
            usort($members, static fn (array $a, array $b): int => $a['order'] <=> $b['order']);
            $uids = array_column($members, 'uid');
            foreach ($uids as $memberUid) {
                self::push($memberUid, 'readyfight', ['teamid' => $teamId]);
            }

            $result = null;
            try {
                $result = EliteHandler::fight($uid, $team['fid'], $teamId, $team['topteam'], $uid, implode(',', $uids));
                $mapped = match ($result['status']) {
                    -6 => -4,
                    -7 => -7,
                    -8 => -5,
                    default => null,
                };
            } catch (\Throwable $e) {
                Logger::error('[EliteRoom] startfight 战斗失败: ' . $e->getMessage(), ['teamid' => $teamId]);
                $mapped = -5;
            }
            if ($mapped !== null) {
                foreach ($uids as $memberUid) {
                    if ($memberUid !== $uid) {
                        self::push($memberUid, 'readyfight_error', $mapped);
                    }
                }
                self::push($uid, 'startfight_error', $mapped);
                return;
            }

            foreach ($uids as $memberUid) {
                self::push($memberUid, 'enterfight', $result);
            }
            if ($result['status'] === 1) {
                self::dissolve($team, false);
                // 打完首杀标记会变（客户端回到组队界面时会重新发 teamlist）
                self::broadcastTeamList($team['fid']);
            }
        });
    }

    // ---------------------------------------------------------------
    // 队伍状态
    // ---------------------------------------------------------------

    /**
     * 退出 / 解散 / 踢人 / 调位置的公共校验：返回队伍；不满足时回 {event} 错误码并返回 null
     *
     * @param array<string, mixed> $arg
     * @return array<string, mixed>|null
     */
    private static function teamOf(int $uid, array $arg, string $event): ?array
    {
        $fid = self::intArg($arg, 'id');
        if (!isset(EliteCity::ROWS[$fid])) {
            self::push($uid, $event, -1);
            return null;
        }
        $team = self::loadTeam(self::intArg($arg, 'teamid'));
        if ($team === null) {
            self::push($uid, $event, -2);
            return null;
        }
        if ($team['fid'] !== $fid) {
            self::push($uid, $event, -1);
            return null;
        }
        if (!in_array($uid, array_column($team['members'], 'uid'), true)) {
            self::push($uid, $event, 0);
            return null;
        }
        return $team;
    }

    /**
     * 玩家离开（断线 / 重复登录 / 进程重启清理）：队长离开解散队伍，队员离开从队伍移除
     */
    private static function leave(int $uid): void
    {
        $online = Redis::get('elite:online:' . $uid);
        $teamId = self::playerTeamId($uid);
        $team = $teamId > 0 ? self::loadTeam($teamId) : null;
        Redis::del('elite:online:' . $uid, 'elite:player:' . $uid);

        $fids = [];
        if (is_array($online) && (int)$online['fid'] > 0) {
            self::unwatch($uid, (int)$online['fid']);
            $fids[] = (int)$online['fid'];
        }
        if ($team !== null && in_array($uid, array_column($team['members'], 'uid'), true)) {
            if ($team['leader'] === $uid) {
                self::dissolve($team, true);
            } else {
                self::pushTeamDetail(self::removeMember($team, $uid));
            }
            $fids[] = $team['fid'];
        }
        foreach (array_unique($fids) as $fid) {
            self::broadcastTeamList($fid);
        }
    }

    /**
     * @param array<string, mixed> $team
     * @return array<string, mixed>
     */
    private static function removeMember(array $team, int $uid): array
    {
        $team['members'] = array_values(array_filter($team['members'], static fn (array $m): bool => $m['uid'] !== $uid));
        self::saveTeam($team);
        Redis::del('elite:player:' . $uid);
        return $team;
    }

    /**
     * 解散队伍；notify 时给队长以外的队员推 teambeenfire
     *
     * @param array<string, mixed> $team
     */
    private static function dissolve(array $team, bool $notify): void
    {
        Redis::del('elite:team:' . $team['teamid']);
        Redis::set('elite:teams:' . $team['fid'], array_values(array_diff(self::teamIds($team['fid']), [$team['teamid']])), self::TTL);
        foreach ($team['members'] as $m) {
            if ((int)Redis::get('elite:player:' . $m['uid']) === $team['teamid']) {
                Redis::del('elite:player:' . $m['uid']);
            }
            if ($notify && $m['uid'] !== $team['leader']) {
                self::push($m['uid'], 'teambeenfire', ['teamid' => $team['teamid']]);
            }
        }
    }

    /**
     * getteamdone 推给全队：{teamid, fid, name, leader, topteam, countryid, countryname, invited, num, list: [{uid, nickname, level, order, leader}]}
     *
     * @param array<string, mixed> $team
     */
    private static function pushTeamDetail(array $team): void
    {
        $list = [];
        foreach ($team['members'] as $m) {
            $list[] = $m + ['leader' => $m['uid'] === $team['leader']];
        }
        $detail = [
            'teamid' => $team['teamid'],
            'fid' => $team['fid'],
            'name' => $team['name'],
            'leader' => $team['leader'],
            'topteam' => $team['topteam'],
            'countryid' => $team['countryid'],
            'countryname' => $team['countryname'],
            'invited' => $team['invited'],
            'num' => count($list),
            'list' => $list,
        ];
        foreach ($team['members'] as $m) {
            self::push($m['uid'], 'getteamdone', $detail);
        }
    }

    /**
     * 军团队伍列表推给正在查看该军团的玩家（elitepop.js：后端最好只给在当前军团的人发）
     */
    private static function broadcastTeamList(int $fid): void
    {
        $summaries = self::teamSummaries($fid);
        foreach (self::viewers($fid) as $viewer => $firstKill) {
            self::push((int)$viewer, 'teamlist', self::teamListPayload($fid, (int)$firstKill, $summaries));
        }
    }

    /**
     * @param list<array<string, mixed>> $summaries
     * @return array<string, mixed>
     */
    private static function teamListPayload(int $fid, int $firstKill, array $summaries): array
    {
        static $rows = null;
        if ($rows === null) {
            $rows = array_column(EliteHandler::rewardList(), null, 'id');
        }
        return ($rows[$fid] ?? []) + ['id' => $fid, 'firstkill' => $firstKill, 'list' => $summaries];
    }

    /**
     * 队伍列表项：{teamid, name, num, countryid, countryname, topteam}
     *
     * @return list<array<string, mixed>>
     */
    private static function teamSummaries(int $fid): array
    {
        $list = [];
        foreach (self::teamIds($fid) as $teamId) {
            $team = self::loadTeam($teamId);
            if ($team === null) {
                continue;
            }
            $list[] = [
                'teamid' => $team['teamid'],
                'name' => $team['name'],
                'num' => count($team['members']),
                'countryid' => $team['countryid'],
                'countryname' => $team['countryname'],
                'topteam' => $team['topteam'],
            ];
        }
        return $list;
    }

    private static function watch(int $uid, int $fid, int $firstKill): void
    {
        $online = Redis::get('elite:online:' . $uid);
        if (!is_array($online)) {
            return;
        }
        if ((int)$online['fid'] > 0 && (int)$online['fid'] !== $fid) {
            self::unwatch($uid, (int)$online['fid']);
        }
        $viewers = self::viewers($fid);
        $viewers[$uid] = $firstKill;
        Redis::set('elite:viewers:' . $fid, $viewers, self::TTL);
        $online['fid'] = $fid;
        Redis::set('elite:online:' . $uid, $online, self::TTL);
    }

    private static function unwatch(int $uid, int $fid): void
    {
        $viewers = self::viewers($fid);
        unset($viewers[$uid]);
        Redis::set('elite:viewers:' . $fid, $viewers, self::TTL);
    }

    /**
     * @return array<int, int> uid => firstkill
     */
    private static function viewers(int $fid): array
    {
        $viewers = Redis::get('elite:viewers:' . $fid);
        $return = [];
        foreach (is_array($viewers) ? $viewers : [] as $uid => $firstKill) {
            $return[(int)$uid] = (int)$firstKill;
        }
        return $return;
    }

    /**
     * @return list<int>
     */
    private static function teamIds(int $fid): array
    {
        $ids = Redis::get('elite:teams:' . $fid);
        return is_array($ids) ? array_map('intval', $ids) : [];
    }

    private static function playerTeamId(int $uid): int
    {
        return (int)(Redis::get('elite:player:' . $uid) ?? 0);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function loadTeam(int $teamId): ?array
    {
        if ($teamId <= 0) {
            return null;
        }
        $team = Redis::get('elite:team:' . $teamId);
        return is_array($team) ? $team : null;
    }

    /**
     * @param array<string, mixed> $team
     */
    private static function saveTeam(array $team): void
    {
        Redis::set('elite:team:' . $team['teamid'], $team, self::TTL);
    }

    /**
     * @param array<string, mixed> $member
     * @return array{uid: int, nickname: string, level: int, order: int}
     */
    private static function memberEntry(array $member, int $order): array
    {
        return [
            'uid' => (int)$member['uid'],
            'nickname' => (string)$member['nickname'],
            'level' => (int)$member['level'],
            'order' => $order,
        ];
    }

    // ---------------------------------------------------------------
    // 会话与收发
    // ---------------------------------------------------------------

    /**
     * 当前连接已登录的 uid（真实连接读连接上的 eliteUid；测试 Mock 读 Context 的 uid，且须已 login）。
     * 会话被同一玩家的新登录顶掉后返回 0。
     */
    private static function uidOf(Context $ctx): int
    {
        $conn = $ctx->connection();
        if ($conn !== null) {
            $uid = (int)($conn->eliteUid ?? 0);
            $sid = (string)($conn->eliteSid ?? '');
        } else {
            $uid = (int)($ctx->uid() ?? 0);
            $sid = (string)(self::$local[$uid]['sid'] ?? '');
        }
        if ($uid <= 0 || $sid === '') {
            return 0;
        }
        $online = Redis::get('elite:online:' . $uid);
        return is_array($online) && $online['sid'] === $sid ? $uid : 0;
    }

    /**
     * 推给某个玩家的当前会话（本进程直接发；在其它 ws 进程上时放进该进程的 mailbox）
     */
    private static function push(int $uid, string $type, mixed $data): void
    {
        $frame = self::frame($type, $data);
        $session = self::$local[$uid] ?? null;
        if ($session !== null) {
            ($session['send'])($frame);
            return;
        }
        $online = Redis::get('elite:online:' . $uid);
        if (is_array($online) && (int)$online['worker'] !== self::$workerId) {
            Redis::rPush('elite:mailbox:' . (int)$online['worker'], (string)json_encode([
                'uid' => $uid,
                'sid' => $online['sid'],
                'frame' => $frame,
            ], JSON_UNESCAPED_UNICODE));
        }
    }

    private static function reply(Context $ctx, string $type, mixed $data): void
    {
        $frame = self::frame($type, $data);
        $conn = $ctx->connection();
        if ($conn !== null) {
            $conn->send($frame);
        } else {
            $ctx->sentMessages[] = $frame;
        }
    }

    /**
     * 与 OmniPHP Context::emit 相同的下行帧，但 data 可以是标量（客户端的 loginstatus / *_error 回调直接比较整数）
     */
    private static function frame(string $type, mixed $data): string
    {
        return (string)json_encode([
            'type' => $type,
            'data' => $data,
            'timestamp' => time(),
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * 在全局锁内执行（多个 ws 进程共享 Redis 队伍状态；锁内再次调用直接执行）
     *
     * @template T
     * @param callable(): T $fn
     * @return T
     */
    private static function locked(callable $fn): mixed
    {
        if (self::$inLock) {
            return $fn();
        }
        $redis = Redis::instance();
        if ($redis === null) {
            throw new \RuntimeException('Redis 未连接');
        }
        $token = self::$workerId . ':' . getmypid() . ':' . (++self::$seq);
        $deadline = microtime(true) + self::LOCK_WAIT;
        while (!$redis->set(self::LOCK_KEY, $token, ['nx', 'ex' => self::LOCK_TTL])) {
            if (microtime(true) > $deadline) {
                throw new \RuntimeException('获取多人副本锁超时');
            }
            usleep(2000);
        }
        self::$inLock = true;
        try {
            return $fn();
        } finally {
            self::$inLock = false;
            if ($redis->get(self::LOCK_KEY) === $token) {
                $redis->del(self::LOCK_KEY);
            }
        }
    }

    private static function now(): int
    {
        return self::$clock !== null ? (self::$clock)() : time();
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function member(int $uid): ?array
    {
        return DB::table('member')->select('uid', 'nickname', 'level', 'country', 'vip', 'gold')
            ->where('uid', $uid)->whereNull('deleted_at')->first();
    }

    /**
     * @param array<string, mixed> $arg
     */
    private static function intArg(array $arg, string $key): int
    {
        $value = $arg[$key] ?? null;
        return is_scalar($value) ? (int)$value : 0;
    }

    /**
     * 客户端 createTeam 的 topteam 是布尔值（也兼容 1 / '1' / 'true'）
     *
     * @param array<string, mixed> $arg
     */
    private static function boolArg(array $arg, string $key): bool
    {
        $value = $arg[$key] ?? false;
        return $value === true || $value === 1 || $value === '1' || $value === 'true';
    }
}
