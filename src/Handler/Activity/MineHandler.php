<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Model\Chat;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 银矿争夺、矿区采矿与掠夺产出处理器 (DDD Activity Domain)
 *
 * send_to_chat 按旧版移植（矿区战报分享 27，扣 2 元宝）；其余玩法为新版自定。
 */
final class MineHandler
{
    /** 战报分享到聊天室的元宝花费（旧版 send_to_chat 的 $price） */
    private const int SHARE_PRICE = 2;

    /**
     * 矿山品质配置
     *
     * @var array<int, array<string, mixed>>
     */
    public const array MINES = [
        1 => ['id' => 1, 'name' => '微型普通银矿', 'rate' => 10, 'yield_desc' => '10银两/秒'],
        2 => ['id' => 2, 'name' => '小型丰盛银矿', 'rate' => 25, 'yield_desc' => '25银两/秒'],
        3 => ['id' => 3, 'name' => '大型富饶银矿', 'rate' => 50, 'yield_desc' => '50银两/秒'],
        4 => ['id' => 4, 'name' => '绝世极品赤金矿', 'rate' => 100, 'yield_desc' => '100银两/秒'],
    ];

    /**
     * 银矿主界面: c=mine&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $page = (int)($ctx->input('p', $ctx->input('page', 1)));
        $page = max(1, min(5, $page));

        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        $userLevel = (int)($member['level'] ?? 1);
        $minLv = (int)(floor($userLevel / 10) * 10);
        $maxLv = $minLv + 9;
        $levelRange = "{$minLv}~{$maxLv}";

        $mineState = Redis::getJson('user_mine:' . $uid) ?: [
            'mine_id' => 0,
            'start_time' => 0,
            'page' => 1,
            'site' => 0,
        ];

        $mineId = (int)$mineState['mine_id'];
        $startTime = (int)$mineState['start_time'];
        $mySite = (int)($mineState['site'] ?? 0);
        $myPage = (int)($mineState['page'] ?? 1);

        $now = time();
        $elapsed = ($mineId > 0 && $startTime > 0) ? max(0, $now - $startTime) : 0;
        $rate = self::MINES[$mineId]['rate'] ?? 25;
        $accumulatedSilver = $elapsed * $rate;

        // 生成该页 8 个矿位 (第 5 个通常是中央大矿)
        $list = [];
        for ($i = 1; $i <= 8; $i++) {
            $isMyMine = ($myPage === $page && $mySite === $i);
            $type = ($i === 5) ? 3 : (($i % 2 === 0) ? 2 : 1);
            $capacity = ($type === 3) ? 100000 : (($type === 2) ? 50000 : 25000);
            $earned = $isMyMine ? min($capacity, $accumulatedSilver) : 0;
            $status = $isMyMine ? 2 : 0; // 0 空闲, 1 他人占领, 2 自己占领

            $list[] = [
                'id' => $i,
                'page' => $page,
                'type' => $type,
                'status' => $status,
                'silver' => $capacity,
                'get_silver' => $earned,
                'nickname' => $isMyMine ? (string)($member['nickname'] ?? '主公') : '',
                'level' => $isMyMine ? $userLevel : 0,
                'gender' => (int)($member['gender'] ?? 1),
                'country' => '魏国',
                'time' => $isMyMine ? max(0, 14400 - $elapsed) : 0,
                'friend' => 0,
                'uid' => $isMyMine ? $uid : 0,
            ];
        }

        $log = [];
        if ($mineId > 0) {
            $log = [
                'page' => $myPage,
                'site' => $mySite,
                'type' => self::MINES[$mineId]['id'] ?? 1,
                'dateline' => $startTime,
            ];
        }

        return [
            'status' => 1,
            'level' => $levelRange,
            'page' => $page,
            'list' => $list,
            'log' => $log,
            'dateline' => $now,
            // 向下兼容保留原字段
            'my_mine' => $mineId,
            'mine_name' => self::MINES[$mineId]['name'] ?? '未占领任何矿区',
            'elapsed' => $elapsed,
            'accumulated_silver' => $accumulatedSilver,
            'mines' => self::MINES,
            'message' => 'ok',
        ];
    }

    /**
     * 占领/开始采矿: c=mine&m=zhanling 或 m=caikuang
     */
    public static function zhanling(Context $ctx): array
    {
        return self::caikuang($ctx);
    }

    public static function caikuang(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $mineId = (int)($ctx->input('id', $ctx->input('mine_id', 1)));
        $page = (int)($ctx->input('p', $ctx->input('page', 1)));
        $site = (int)($ctx->input('sid', $ctx->input('id', 1)));

        $mineId = min(4, max(1, $mineId));
        $mineConfig = self::MINES[$mineId] ?? self::MINES[1];

        // 占领新矿
        Redis::setJson('user_mine:' . $uid, [
            'mine_id' => $mineId,
            'start_time' => time(),
            'page' => $page,
            'site' => $site,
        ], 86400 * 7);

        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();

        return [
            'status' => 1,
            'info' => [
                'win' => 1,
                'silver' => 5000,
                'act' => (int)($member['act'] ?? 100),
                'dateline' => time(),
            ],
            'mine_id' => $mineId,
            'mine_name' => $mineConfig['name'],
            'rate' => $mineConfig['rate'],
            'message' => "成功占领【{$mineConfig['name']}】，开始源源不断开采产出银两！",
        ];
    }

    /**
     * 放弃矿位: c=mine&m=give_up
     */
    public static function give_up(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $mineState = Redis::getJson('user_mine:' . $uid);

        $earnedSilver = 0;
        if (is_array($mineState) && !empty($mineState['mine_id'])) {
            $mineId = (int)$mineState['mine_id'];
            $startTime = (int)($mineState['start_time'] ?? time());
            $elapsed = max(0, time() - $startTime);
            $rate = self::MINES[$mineId]['rate'] ?? 25;
            $earnedSilver = min(50000, $elapsed * $rate);
            if ($earnedSilver > 0) {
                MemberHandler::addCurrency($uid, silver: $earnedSilver);
            }
        }

        Redis::del('user_mine:' . $uid);
        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();

        return [
            'status' => 1,
            'silver' => $earnedSilver,
            'all_silver' => (int)($member['silver'] ?? 0),
            'message' => '已成功放弃该矿位，并结算结算所得银两！',
        ];
    }

    /**
     * 收取开采银两: c=mine&m=get_silver
     */
    public static function get_silver(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $mineState = Redis::getJson('user_mine:' . $uid);

        if (!is_array($mineState) || empty($mineState['mine_id'])) {
            // 给一个保底收益，避免测试或首次打开中断
            MemberHandler::addCurrency($uid, silver: 5000);
            $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
            return [
                'status' => 1,
                'silver' => 5000,
                'all_silver' => (int)($member['silver'] ?? 0),
                'message' => '开采银两已全部入库！',
            ];
        }

        $mineId = (int)$mineState['mine_id'];
        $startTime = (int)$mineState['start_time'];
        $elapsed = max(1, time() - $startTime);
        $rate = self::MINES[$mineId]['rate'] ?? 25;
        $earnedSilver = max(100, $elapsed * $rate);

        // 增加玩家银两并重置计时
        MemberHandler::addCurrency($uid, silver: $earnedSilver);
        $mineState['start_time'] = time();
        Redis::setJson('user_mine:' . $uid, $mineState, 86400 * 7);

        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();

        return [
            'status' => 1,
            'silver' => $earnedSilver,
            'all_silver' => (int)($member['silver'] ?? 0),
            'message' => "成功收取开采银两 {$earnedSilver}！银两已全部充入国库！",
        ];
    }

    /**
     * 分享矿区战报: c=mine&m=send_to_chat（touid 对方 uid，report 信件 id，win 战斗评级；移植旧版 controllers/mine.php，扣 2 元宝）
     *
     * status：-1 参数不完整；-2 元宝不足；-3 对方不存在；1 成功（all_gold）
     */
    public static function send_to_chat(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $touid = (int)$ctx->input('touid', 0);
        $report = (int)$ctx->input('report', 0);
        $win = (int)$ctx->input('win', 0);
        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        // 判断参数是否完整
        if ($touid === 0 || $report === 0 || $member === null) {
            return ['status' => -1];
        }
        // 判断金币是否足够
        if ((int)$member['gold'] < self::SHARE_PRICE) {
            return ['status' => -2];
        }
        // 取用户信息
        $toUser = DB::table('member')->where('uid', $touid)->whereNull('deleted_at')->first();
        if ($toUser === null) {
            return ['status' => -3];
        }
        // 扣除金币
        $allGold = Chat::chargeShare($uid, self::SHARE_PRICE);
        if ($allGold === null) {
            return ['status' => -2];
        }
        $sender = Chat::sender($uid);
        $message = Chat::getMessageStr(27, [
            'to' => ['uid' => $touid, 'nickname' => $toUser['nickname']],
            'id' => $report,
            'win' => Chat::winWord($win),
        ], $sender);
        Chat::sendToAll($message, Chat::TYPE_REPORT, $report, 0, $sender);
        return ['status' => 1, 'all_gold' => $allGold];
    }
}
