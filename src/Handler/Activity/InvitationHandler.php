<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\General\TavernHandler;
use Hanjiang\MasterData\InvitationReward;
use Hanjiang\Model\Member;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 邀请好友（移植旧版 controllers/invitation.php + models/invitation_model.php）
 *
 * 50 级开放。邀请码与已邀请人数存 invitation_code，邀请奖励在 MasterData InvitationReward（type 1 黄宝石、2 紫宝石、3 元宝），
 * 领取记 invitation_reward_log。被邀请者输入别人的邀请码得 100 元宝 + 50 万银币（只能一次）；输错 24 小时内最多 50 次
 * （Redis invitation_error_{uid}，旧版键名）。被邀请过且奖励全部领完时关闭入口（member_field.close_invitation = 1，每日活动不显示）。
 * 客户端 invitationpop.js / miscpop.js 调用 index、get_reward（id）、change（code）。
 *
 * 依赖未移植：邀请码由 uc 服务器生成与校验（uc_model->create_invitation_code / check_invitation_code），邀请成功由 uc 回调
 * completed 累计人数（uc 的 invitation 不在旧版代码包里）。没有 uc 时：index 生成不了邀请码——旧版会插入一条空邀请码，
 * 这里不插，回 status 0；change 照旧版按 uc 没有返回处理（status 0，记一次错误）；已邀请人数恒为 0。
 * 不移植：completed（uc 回调）、clear_change_limit（运维接口，不校验身份）。
 */
final class InvitationHandler
{
    /** 功能等级限制 */
    private const int LEVEL_LIMIT = 50;

    /** 24小时最大输错次数 */
    private const int MAX_ERROR_TIMES = 50;

    /** 被邀请者兑换奖励 */
    private const array CHANGE_REWARD = ['gold' => 100, 'silver' => 500000];

    private const string ERROR_KEY = 'invitation_error_';
    private const int ERROR_EXPIRE = 86400;

    /**
     * 邀请页面接口: c=invitation&m=index
     */
    public static function index(Context $ctx): array
    {
        $return = ['status' => 0, 'code' => '', 'number' => 0, 'list' => []];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null || (int)$member['level'] < self::LEVEL_LIMIT) {
            $return['status'] = -1;
            return $return;
        }
        $uid = (int)$member['uid'];
        $codeInfo = self::codeInfo($uid);
        if ($codeInfo === null) {
            // 依赖未移植：没有邀请码时由 uc 生成（create_code_info），无法生成
            return $return;
        }
        // 处理奖励
        $rewardList = array_values(InvitationReward::ROWS);
        $myReward = self::myRewardIds($uid);
        // 已领取奖励数
        $completed = 0;
        foreach ($rewardList as $key => $value) {
            // 完成状态
            $rewardList[$key]['completed'] = 0;
            if ($codeInfo['number'] >= (int)$value['count']) {
                // 已完成未领取
                $rewardList[$key]['completed'] = 2;
            }
            if (in_array((int)$value['id'], $myReward, true)) {
                // 已领取
                $rewardList[$key]['completed'] = 1;
                $completed++;
            }
            $rewardList[$key]['reward_type'] = match ((int)$value['type']) {
                1 => 'get2',
                2 => 'get3',
                3 => 'gold',
                default => null,
            };
            if ($rewardList[$key]['reward_type'] === null) {
                unset($rewardList[$key]['reward_type']);
            }
        }
        if ($codeInfo['status'] === 0) {
            // 可以被邀请
            $return['status'] = 1;
        } else {
            // 已经被邀请
            $return['status'] = 2;
            if ($completed === count($rewardList)) {
                // 所有奖励已领取 关闭邀请
                DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->update(['close_invitation' => 1]);
            }
        }
        $return['code'] = $codeInfo['code'];
        $return['number'] = $codeInfo['number'];
        $return['list'] = $rewardList;
        return $return;
    }

    /**
     * 领取奖励: c=invitation&m=get_reward（id）
     */
    public static function get_reward(Context $ctx): array
    {
        /**
         * -1 等级不足
         *  1 领取成功
         *  2 条件未达成
         *  3 已领取过奖励
         *  4 奖励信息不存在
         */
        $return = ['status' => 0, 'all_gold' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null || (int)$member['level'] < self::LEVEL_LIMIT) {
            $return['status'] = -1;
            return $return;
        }
        $uid = (int)$member['uid'];
        $id = (int)$ctx->input('id', 0);
        // 获得邀请信息
        $codeInfo = self::codeInfo($uid);
        // 获得奖励信息
        $rewardInfo = InvitationReward::ROWS[$id] ?? null;
        if ($rewardInfo === null) {
            // 奖励信息不存在
            $status = 4;
        } elseif (($codeInfo['number'] ?? 0) >= (int)$rewardInfo['count'] && $codeInfo !== null) {
            // 达成条件：领取记录主键 (uid, rewardid)，并发只有一个插入成功
            $inserted = DB::execute(
                'INSERT IGNORE INTO `zc_sanguo_invitation_reward_log` (`uid`, `rewardid`, `dateline`) VALUES (:uid, :id, :now)',
                ['uid' => $uid, 'id' => $id, 'now' => time()]
            );
            if ($inserted > 0) {
                if ((int)$rewardInfo['type'] === 3) {
                    // 奖励元宝
                    Member::addCurrency($uid, gold: (int)$rewardInfo['number']);
                    $return['all_gold'] = (int)DB::table('member')->where('uid', $uid)->value('gold');
                } elseif ((int)$rewardInfo['type'] === 1) {
                    // 奖励黄宝石
                    TavernHandler::addJade($uid, 2, (int)$rewardInfo['number']);
                } elseif ((int)$rewardInfo['type'] === 2) {
                    // 奖励紫宝石
                    TavernHandler::addJade($uid, 3, (int)$rewardInfo['number']);
                }
                // 领取成功
                $status = 1;
            } else {
                // 已获取过奖励
                $status = 3;
            }
        } else {
            // 条件未达成
            $status = 2;
        }
        $return['status'] = $status;
        return $return;
    }

    /**
     * 兑换邀请奖励: c=invitation&m=change（code 别人的邀请码）
     */
    public static function change(Context $ctx): array
    {
        /**
         * -1 等级不足
         *  1 成功
         *  2 邀请码已失效
         *  3 邀请码错误
         *  4 已领取过奖励
         *  5 输入错误次数太多
         */
        $return = ['status' => 0, 'all_silver' => 0, 'all_gold' => 0, 'reward' => []];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null || (int)$member['level'] < self::LEVEL_LIMIT) {
            $return['status'] = -1;
            return $return;
        }
        $uid = (int)$member['uid'];
        $status = 0;
        $times = (int)(Redis::get(self::ERROR_KEY . $uid) ?? 0);
        $code = strtolower(trim((string)$ctx->input('code', '')));
        if ($code === '' || $code === '0') {
            // 邀请码错误
            $status = 3;
        } elseif ($times >= self::MAX_ERROR_TIMES) {
            // 输入错误次数太多
            $status = 5;
        } else {
            $codeInfo = self::codeInfo($uid);
            if ($codeInfo === null || $codeInfo['status'] === 0) {
                // 依赖未移植：uc 校验邀请码（check_invitation_code），按 uc 没有返回处理（status 0）
                // 记录连续错误次数
                Redis::set(self::ERROR_KEY . $uid, $times + 1, self::ERROR_EXPIRE);
            } else {
                // 已经获得过奖励
                $status = 4;
            }
        }
        $return['status'] = $status;
        $return['reward'] = self::CHANGE_REWARD;
        return $return;
    }

    /**
     * 邀请码信息（get_code_info）；没有为 null
     *
     * @return array{code: string, number: int, status: int}|null
     */
    private static function codeInfo(int $uid): ?array
    {
        $row = DB::table('invitation_code')->select('code', 'number', 'status')->where('uid', $uid)->whereNull('deleted_at')->first();
        return $row === null ? null : ['code' => (string)$row['code'], 'number' => (int)$row['number'], 'status' => (int)$row['status']];
    }

    /**
     * @return list<int>
     */
    private static function myRewardIds(int $uid): array
    {
        $rows = DB::table('invitation_reward_log')->select('rewardid')->where('uid', $uid)->whereNull('deleted_at')->get();
        return array_map(static fn (array $r): int => (int)$r['rewardid'], $rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function member(int $uid): ?array
    {
        return $uid > 0 ? DB::table('member')->select('uid', 'level')->where('uid', $uid)->whereNull('deleted_at')->first() : null;
    }
}
