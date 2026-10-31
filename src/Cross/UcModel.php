<?php

declare(strict_types=1);

namespace Hanjiang\Cross;

use Hanjiang\Handler\Activity\MessageHandler;
use Hanjiang\MasterData\ChristmasgiftReward;
use Hanjiang\Model\BossClock;
use OmniPHP\Cache\Redis;
use OmniPHP\Config;
use OmniPHP\Database\DB;

/**
 * 用户中心 uc 的进程内实现（旧版 s1 models/uc_model.php 的调用面 + uc controllers 的对应方法）
 *
 * 旧版 s1 通过 uc_model->uc_get（curl 到 uc_url，带 serverid）调用 uc 服务器；单服部署下 uc 是本进程内的模块，
 * s1 的调用方直接调用这里的方法，返回值与 uc 控制器 api_return 的数组相同。表名前缀 zc_sanguo_uc_。
 * uc 的 model（herothrone_model / invitation_model / activationcode_model）与 report 服务器不在旧版代码包里，
 * 这里按控制器、s1 调用方、客户端与表结构还原，还原的部分在各方法注释里注明
 * 「按控制器与表结构还原（旧版 model 不在代码包里）」。
 */
final class UcModel
{
    /** uc invitation 控制器 $_max：一个邀请码最多邀请人数 */
    private const int INVITATION_MAX = 20;

    /** 邀请码字符（s1 change 把输入转成小写再校验，邀请码为小写字母与数字；推定） */
    private const string INVITATION_CHARS = 'abcdefghijklmnopqrstuvwxyz0123456789';

    /** 邀请码长度（s1 / uc invitation_code.code 为 varchar(8)；推定） */
    private const int INVITATION_LENGTH = 8;

    /** s1 lang invitation_completed_message */
    private const string LANG_INVITATION_COMPLETED = '%s<em>%s</em>接受了您的邀请并达到50级，您的邀请好友数量+1。';

    /** s1 lang message_system_title */
    private const string LANG_SYSTEM_TITLE = '系统通知';

    // ---------------------------------------------------------------------------------------------
    // 英雄王座跨服排行（uc controllers/herothrone.php）
    // ---------------------------------------------------------------------------------------------

    /**
     * uc_model->get_top_rank($num)：英雄王座排行前 $num 名（uc herothrone->get_top_rank）
     *
     * herothrone_model->get_list 按控制器与表结构还原（旧版 model 不在代码包里）：与本服同一服务器分组（uc_server.servergroup）
     * 的玩家按历史最高关卡降序、达到时间升序；每行 uid / nickname / serverid / groupname / stage。
     *
     * @return list<array{uid: int, nickname: string, serverid: int, groupname: string, stage: int}>
     */
    public static function getTopRank(int $num = 5, ?int $serverid = null): array
    {
        $serverid ??= Server::id();
        $server = Server::info($serverid);
        $servers = $server === null ? [$serverid] : array_map(
            static fn (array $r): int => (int)$r['id'],
            DB::table('uc_server')->select('id')->where('servergroup', (int)$server['servergroup'])->whereNull('deleted_at')->get()
        );
        $rows = DB::table('uc_herothrone')->select('uid', 'nickname', 'serverid', 'groupname', 'stage')
            ->whereIn('serverid', $servers)->whereNull('deleted_at')
            ->orderBy('stage', 'DESC')->orderBy('dateline')->orderBy('id')->limit(max(0, $num))->get();
        return array_map(static fn (array $r): array => [
            'uid' => (int)$r['uid'],
            'nickname' => (string)$r['nickname'],
            'serverid' => (int)$r['serverid'],
            'groupname' => (string)$r['groupname'],
            'stage' => (int)$r['stage'],
        ], $rows);
    }

    /**
     * uc_model->update_top_rank($stage, $num)：上报历史最高关卡并返回最新排行（uc herothrone->update_top_rank）
     *
     * status：-1 参数不完整；-2 更新失败（关卡没有变化时 update 影响 0 行，旧版同样回 -2）；成功时直接返回排行列表。
     * herothrone_model 的 add_member_info / update_member_info 按控制器与表结构还原（旧版 model 不在代码包里）。
     *
     * @return array<int|string, mixed>
     */
    public static function updateTopRank(int $uid, string $nickname, int $stage, int $num = 5, ?int $serverid = null): array
    {
        $serverid ??= Server::id();
        // 判断参数是否完整
        if ($uid === 0 || $stage === 0 || $serverid === 0) {
            return ['status' => -1];
        }
        $now = BossClock::now();
        $exists = DB::table('uc_herothrone')->where('serverid', $serverid)->where('uid', $uid)->whereNull('deleted_at')->value('id');
        if ($exists === null) {
            // 查询服务器信息，没有自己的信息（添加一条信息）
            $server = Server::info($serverid);
            $group = $server === null ? null : Server::group((int)$server['servergroup']);
            $success = DB::execute(
                'INSERT IGNORE INTO `zc_sanguo_uc_herothrone` (`serverid`, `uid`, `nickname`, `groupname`, `stage`, `dateline`)
                 VALUES (:serverid, :uid, :nickname, :groupname, :stage, :now)',
                ['serverid' => $serverid, 'uid' => $uid, 'nickname' => $nickname, 'groupname' => (string)($group['name'] ?? ''), 'stage' => $stage, 'now' => $now]
            );
        } else {
            // 查询到信息（修改 stage）
            $success = DB::table('uc_herothrone')->where('id', (int)$exists)->whereNull('deleted_at')
                ->update(['stage' => $stage, 'dateline' => $now]);
        }
        // 判断是否更新成功
        if ($success !== 1) {
            return ['status' => -2];
        }
        // 用户排行更新成功（查询最新排行，并返回）
        return self::getTopRank($num, $serverid);
    }

    // ---------------------------------------------------------------------------------------------
    // 邀请码（uc controllers/invitation.php）
    // ---------------------------------------------------------------------------------------------

    /**
     * uc_model->create_invitation_code($uid)：取本服玩家的邀请码，没有就生成（uc invitation->create）
     *
     * invitation_model 的 get_code_info / create_code / check_code / insert_code 按控制器与表结构还原（旧版 model 不在代码包里）：
     * 8 位小写字母数字，与已有的码不重复（推定）。
     *
     * @return array{status: int, code: string}
     */
    public static function createInvitationCode(int $uid, ?int $serverid = null): array
    {
        $serverid ??= Server::id();
        $code = (string)(DB::table('uc_invitation_code')->where('serverid', $serverid)->where('uid', $uid)->whereNull('deleted_at')->value('code') ?? '');
        while ($code === '') {
            $code = self::randomCode();
            // 码已存在（status 不是 3）时重新生成；并发时唯一键冲突同样重来
            if (self::checkCode($code)['status'] !== 3) {
                $code = '';
                continue;
            }
            $inserted = DB::execute(
                'INSERT IGNORE INTO `zc_sanguo_uc_invitation_code` (`serverid`, `uid`, `code`, `number`, `dateline`) VALUES (:serverid, :uid, :code, 0, :now)',
                ['serverid' => $serverid, 'uid' => $uid, 'code' => $code, 'now' => BossClock::now()]
            );
            if ($inserted !== 1) {
                // 同一玩家并发生成：取已写入的码
                $code = (string)(DB::table('uc_invitation_code')->where('serverid', $serverid)->where('uid', $uid)->whereNull('deleted_at')->value('code') ?? '');
            }
        }
        return ['status' => 1, 'code' => $code];
    }

    /**
     * uc_model->check_invitation_code($uid, $nickname, $code)：被邀请者输入别人的邀请码（uc invitation->check）
     *
     * status：1 邀请成功；2 邀请码失效（已邀请满 20 人，或自己已经被邀请过）；3 邀请码错误（不存在，或是自己的码）。
     * 成功时 uc 回调邀请者所在服的 c=invitation&m=completed（累计人数并发信件），单服下直接执行（invitationCompleted）。
     * 并发安全：邀请记录 (serverid, uid) 唯一，只有一个插入成功的请求累计人数。
     *
     * @return array{status: int}
     */
    public static function checkInvitationCode(int $uid, string $nickname, string $code, ?int $serverid = null): array
    {
        $serverid ??= Server::id();
        $result = self::checkCode($code);
        if ($result['status'] === 2) {
            // 邀请码失效
            return ['status' => 2];
        }
        if ($result['status'] !== 1) {
            // 邀请码错误
            return ['status' => 3];
        }
        $fromServerid = (int)$result['result']['serverid'];
        $fromUid = (int)$result['result']['uid'];
        if ($fromServerid === $serverid && $fromUid === $uid) {
            // 自己邀请自己
            return ['status' => 3];
        }
        $now = BossClock::now();
        return Server::transaction(static function () use ($serverid, $uid, $nickname, $fromServerid, $fromUid, $now): array {
            $inserted = DB::execute(
                'INSERT IGNORE INTO `zc_sanguo_uc_invitation_log` (`serverid`, `uid`, `from_serverid`, `from_uid`, `dateline`)
                 VALUES (:serverid, :uid, :from_serverid, :from_uid, :now)',
                ['serverid' => $serverid, 'uid' => $uid, 'from_serverid' => $fromServerid, 'from_uid' => $fromUid, 'now' => $now]
            );
            if ($inserted !== 1) {
                // 已经被邀请过：邀请码失效
                return ['status' => 2];
            }
            // 邀请成功
            DB::execute(
                'UPDATE `zc_sanguo_uc_invitation_code` SET `number` = `number` + 1 WHERE `serverid` = :serverid AND `uid` = :uid AND `deleted_at` IS NULL',
                ['serverid' => $fromServerid, 'uid' => $fromUid]
            );
            $number = (int)DB::table('uc_invitation_code')->where('serverid', $fromServerid)->where('uid', $fromUid)->whereNull('deleted_at')->value('number');
            // 回调邀请者所在服（合服后取新服）的 completed；单服部署只有本服
            self::invitationCompleted($fromUid, $number, $serverid, $nickname);
            return ['status' => 1];
        });
    }

    /**
     * 成功邀请其他人（旧版 s1 c=invitation&m=completed，由 uc 调用）：本服 invitation_code.number 更新为 uc 的人数，给邀请者发信件
     */
    private static function invitationCompleted(int $uid, int $number, int $toServerid, string $toNickname): void
    {
        if ($number === 0) {
            $number = (int)(DB::table('invitation_code')->where('uid', $uid)->whereNull('deleted_at')->value('number') ?? 0) + 1;
        }
        DB::table('invitation_code')->where('uid', $uid)->whereNull('deleted_at')->update(['number' => $number]);
        // 给邀请者发信
        $message = sprintf(self::LANG_INVITATION_COMPLETED, Server::serverName($toServerid), $toNickname);
        MessageHandler::sendSystemMessage([$uid], 1, self::LANG_SYSTEM_TITLE, $message);
    }

    /**
     * invitation_model->check_code($code, $max)：status 1 可用（带 result 行）、2 已邀请满、3 不存在（按控制器还原）
     *
     * @return array{status: int, result?: array<string, mixed>}
     */
    private static function checkCode(string $code): array
    {
        if ($code === '') {
            return ['status' => 3];
        }
        $row = DB::table('uc_invitation_code')->select('serverid', 'uid', 'number')->where('code', $code)->whereNull('deleted_at')->first();
        if ($row === null) {
            return ['status' => 3];
        }
        if ((int)$row['number'] >= self::INVITATION_MAX) {
            return ['status' => 2, 'result' => $row];
        }
        return ['status' => 1, 'result' => $row];
    }

    private static function randomCode(): string
    {
        $code = '';
        $max = strlen(self::INVITATION_CHARS) - 1;
        for ($i = 0; $i < self::INVITATION_LENGTH; $i++) {
            $code .= self::INVITATION_CHARS[mt_rand(0, $max)];
        }
        return $code;
    }

    // ---------------------------------------------------------------------------------------------
    // 8 ~ 13 位激活码（uc controllers/activationcode.php）
    // ---------------------------------------------------------------------------------------------

    /**
     * uc_model->use_activation_code($code)：使用长激活码（uc activationcode->use_activation_code）
     *
     * status：-5 已使用过同类型的激活码；-4 参数不完整；-3 激活码使用失败；-2 激活码已使用；-1 激活码不存在（或类型不存在）；
     * 1 成功（code 为激活码行，s1 按 code.type 取本服 activationcode_reward 的奖励）。
     * activationcode_model 按控制器与表结构还原（旧版 model 不在代码包里）：激活码表 uc_activationcode（use_uid > 0 已使用）、
     * 类型表 uc_activationcode_type（use_num > 0 时同一玩家同类型只能用一个）；add_use_log 以 use_uid = 0 为条件更新，
     * 并发时只有一个请求成功。激活码与类型由运营后台生成（create_activation_code / add_active_type，后台接口不移植）。
     *
     * @return array{status: int, code?: array<string, mixed>}
     */
    public static function useActivationCode(int $uid, string $code, ?int $serverid = null): array
    {
        $serverid ??= Server::id();
        $code = htmlspecialchars($code);
        if ($serverid === 0 || $uid === 0 || $code === '') {
            return ['status' => -4];
        }
        // 获取激活码信息
        $info = DB::table('uc_activationcode')->select('id', 'code', 'type', 'channel', 'use_serverid', 'use_uid', 'use_time', 'dateline')
            ->where('code', $code)->whereNull('deleted_at')->first();
        if ($info === null) {
            // 激活码不存在
            return ['status' => -1];
        }
        if ((int)$info['use_uid'] > 0) {
            // 激活码已使用
            return ['status' => -2];
        }
        // 根据激活码类型查询限制使用次数
        $type = DB::table('uc_activationcode_type')->select('type', 'use_num')->where('type', (int)$info['type'])->whereNull('deleted_at')->first();
        if ($type === null) {
            return ['status' => -1];
        }
        if ((int)$type['use_num'] > 0) {
            // 查询用户是否使用过同类型的激活码
            $used = DB::table('uc_activationcode')->where('use_serverid', $serverid)->where('use_uid', $uid)
                ->where('type', (int)$info['type'])->whereNull('deleted_at')->value('id');
            if ($used !== null) {
                return ['status' => -5];
            }
        }
        // 使用激活码
        $now = BossClock::now();
        $status = DB::table('uc_activationcode')->where('id', (int)$info['id'])->where('use_uid', 0)->whereNull('deleted_at')
            ->update(['use_serverid' => $serverid, 'use_uid' => $uid, 'use_time' => $now]);
        if ($status !== 1) {
            return ['status' => -3];
        }
        return ['status' => 1, 'code' => [
            'id' => (int)$info['id'],
            'code' => (string)$info['code'],
            'type' => (int)$info['type'],
            'channel' => (int)$info['channel'],
            'use_serverid' => (int)$info['use_serverid'],
            'use_uid' => (int)$info['use_uid'],
            'use_time' => (int)$info['use_time'],
            'dateline' => (int)$info['dateline'],
        ]];
    }

    // ---------------------------------------------------------------------------------------------
    // 分享战报（旧版 report 服务器 c=report&m=save，transfer_model->save_report）
    // ---------------------------------------------------------------------------------------------

    /**
     * transfer_model->save_report($type, $reportid, $report)：保存分享战报，返回分享链接
     *
     * report 服务器不在旧版代码包里，按 s1 调用与客户端还原（旧版 model 不在代码包里）：同一服务器同一战报只存一份
     * （uc_report），link 为 config app.report_url + 分享编号。没有配置 report_url（没有展示页）时战报照样保存，但按旧版
     * 连不上 report 服务器处理（status 0，s1 回 -2）。
     *
     * @param array<string, mixed> $report
     * @return array{status: int, link?: string}
     */
    public static function saveReport(string $type, string $reportid, array $report): array
    {
        $serverid = Server::id();
        DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_uc_report` (`serverid`, `type`, `reportid`, `report`, `dateline`) VALUES (:serverid, :type, :reportid, :report, :now)',
            ['serverid' => $serverid, 'type' => $type, 'reportid' => $reportid, 'report' => (string)json_encode($report, JSON_UNESCAPED_UNICODE), 'now' => BossClock::now()]
        );
        $id = (int)(DB::table('uc_report')->where('serverid', $serverid)->where('type', $type)->where('reportid', $reportid)->whereNull('deleted_at')->value('id') ?? 0);
        $url = (string)Config::get('app.report_url', '');
        if ($id === 0 || $url === '') {
            return ['status' => 0];
        }
        return ['status' => 1, 'link' => $url . $id];
    }

    // ---------------------------------------------------------------------------------------------
    // 圣诞礼包全服限售数量（uc controllers/christmasgift.php）
    // ---------------------------------------------------------------------------------------------

    /** 商品购买次数记录的键前缀（s1 christmasgift_model->_buy_times，键为 前缀 + ymd + _ + 礼包 id） */
    public const string CHRISTMASGIFT_BUY_TIMES = 'christmasgift_buytimes_';

    /**
     * uc_model->get_buy_num($id)：今天该礼包的全服购买数量（uc christmasgift->get_buy_num）
     *
     * uc 的 christmasgift_model 按 s1 christmasgift_model 的 get_buy_times 还原（旧版 model 不在代码包里）：
     * Redis 键 christmasgift_buytimes_{ymd}_{id}，24 小时过期。
     */
    public static function christmasgiftBuyNum(int $id, ?int $now = null): int
    {
        return (int)(Redis::get(self::christmasgiftKey($id, $now ?? BossClock::now())) ?? 0);
    }

    /**
     * uc_model->get_all_buy_num()：今天全部礼包的全服购买数量（uc christmasgift->get_all_buy_num），键为完整 Redis 键名
     *
     * 按 s1 的读取方式还原（旧版 model 不在代码包里）：s1 用「christmasgift_buytimes_{ymd}_{id}」取值，这里按礼包表的 id 逐个读取，
     * 只返回有记录的。
     *
     * @return array<string, int>
     */
    public static function christmasgiftAllBuyNum(?int $now = null): array
    {
        $now ??= BossClock::now();
        $return = [];
        foreach (ChristmasgiftReward::ROWS as $row) {
            $key = self::christmasgiftKey((int)$row['id'], $now);
            $value = Redis::get($key);
            if ($value !== null) {
                $return[$key] = (int)$value;
            }
        }
        return $return;
    }

    /**
     * uc_model->add_buy_num($id)：购买数量加 1，返回全部礼包的购买数量（uc christmasgift->add_buy_num）
     *
     * 与旧版的差别：旧版先读后写（get_buy_num + 1 再 setex），这里 INCR 原子加 1；返回值另带本次加完的数量 num，
     * 供调用方判断是否超卖。
     *
     * @return array{all: array<string, int>, num: int}
     */
    public static function christmasgiftAddBuyNum(int $id, ?int $now = null): array
    {
        $now ??= BossClock::now();
        $key = self::christmasgiftKey($id, $now);
        $num = (int)Redis::incr($key);
        Redis::expire($key, 86400);
        return ['all' => self::christmasgiftAllBuyNum($now), 'num' => $num];
    }

    /**
     * 退回一份购买数量（新版新增：s1 先占数量后付款失败时退回，旧版没有）
     */
    public static function christmasgiftReleaseBuyNum(int $id, ?int $now = null): void
    {
        Redis::decr(self::christmasgiftKey($id, $now ?? BossClock::now()));
    }

    private static function christmasgiftKey(int $id, int $now): string
    {
        return self::CHRISTMASGIFT_BUY_TIMES . date('ymd', $now) . '_' . $id;
    }

    // ---------------------------------------------------------------------------------------------
    // 限量神将（uc controllers/limitedshop.php）
    // ---------------------------------------------------------------------------------------------

    /**
     * uc_model->get_general_onsale：限量神将商品列表（uc limitedshop->get_item_list）
     *
     * limitedshop_model 按控制器、s1 调用方与客户端字段还原（旧版 model 与表不在代码包和数据里）：uc_limitedshop 全表（全服共用），
     * 每行 id / generalid / type / cost / reputation / now / now_reputation / num / sale。没有商品时 status -1。
     *
     * @return array{status: int, list?: list<array<string, int>>}
     */
    public static function limitedItemList(): array
    {
        $rows = DB::table('uc_limitedshop')->select(...self::LIMITED_COLUMNS)->whereNull('deleted_at')->orderBy('id')->get();
        if ($rows === []) {
            return ['status' => -1];
        }
        return ['status' => 1, 'list' => array_map(static fn (array $r): array => array_map('intval', $r), $rows)];
    }

    /**
     * uc_model->get_limited_item($id)：单个商品（uc limitedshop->get_item_info）；没有时 status -1
     *
     * @return array{status: int, info?: array<string, int>}
     */
    public static function limitedItem(int $id): array
    {
        $row = DB::table('uc_limitedshop')->select(...self::LIMITED_COLUMNS)->where('id', $id)->whereNull('deleted_at')->first();
        return $row === null ? ['status' => -1] : ['status' => 1, 'info' => array_map('intval', $row)];
    }

    /**
     * uc_model->buy_item($id)：购买一份（uc limitedshop->buy）
     *
     * status：1 成功（num 为剩余数量）；2 已售完；-1 商品不存在；-2 售出失败；-3 添加记录失败；-4 已购买过。
     * 按控制器与表结构还原：每个玩家每件商品只能买一次（售出记录 uid + serverid + pid）；售出与记录在同一事务里、商品行加锁，
     * 并发不会超卖或重复购买。
     *
     * @return array{status: int, num?: int}
     */
    public static function limitedBuy(int $id, int $uid, ?int $serverid = null): array
    {
        $serverid ??= Server::id();
        $now = BossClock::now();
        return Server::transaction(static function () use ($id, $uid, $serverid, $now): array {
            $item = DB::query(
                'SELECT `num`, `sale` FROM `zc_sanguo_uc_limitedshop` WHERE `id` = :id AND `deleted_at` IS NULL FOR UPDATE',
                ['id' => $id]
            )[0] ?? null;
            $log = DB::table('uc_limitedshop_sale_log')->where('uid', $uid)->where('serverid', $serverid)->where('pid', $id)
                ->whereNull('deleted_at')->value('id');
            if ($log !== null) {
                return ['status' => -4];
            }
            if ($item === null) {
                // 商品不存在
                return ['status' => -1];
            }
            if ((int)$item['sale'] >= (int)$item['num']) {
                // 已售完
                return ['status' => 2];
            }
            $sold = DB::execute(
                'UPDATE `zc_sanguo_uc_limitedshop` SET `sale` = `sale` + 1 WHERE `id` = :id AND `sale` < `num` AND `deleted_at` IS NULL',
                ['id' => $id]
            );
            if ($sold <= 0) {
                // 售出失败
                return ['status' => -2];
            }
            $added = DB::execute(
                'INSERT IGNORE INTO `zc_sanguo_uc_limitedshop_sale_log` (`uid`, `serverid`, `pid`, `dateline`) VALUES (:uid, :serverid, :pid, :now)',
                ['uid' => $uid, 'serverid' => $serverid, 'pid' => $id, 'now' => $now]
            );
            if ($added <= 0) {
                DB::execute('UPDATE `zc_sanguo_uc_limitedshop` SET `sale` = `sale` - 1 WHERE `id` = :id', ['id' => $id]);
                return ['status' => -3];
            }
            return ['status' => 1, 'num' => (int)$item['num'] - (int)$item['sale'] - 1];
        });
    }

    /** uc_limitedshop 的列 */
    private const array LIMITED_COLUMNS = ['id', 'generalid', 'type', 'cost', 'reputation', 'now', 'now_reputation', 'num', 'sale'];
}
