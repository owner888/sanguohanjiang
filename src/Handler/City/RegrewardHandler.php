<?php

declare(strict_types=1);

namespace Hanjiang\Handler\City;

use Hanjiang\Model\Member;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 试玩账号绑定奖励（移植旧版 controllers/regreward.php:get_reward）
 *
 * 客户端（regrewardpop.js）在试玩账号绑定正式帐号（c=register&m=binding）后调用 get_reward 领 50 元宝；主城 member.index 的
 * guest 为 1 时显示入口。member.guest：1 试玩注册、尚未领奖，2 已领奖（update_guest_status），0 正式注册。
 * 旧版用 uc 服务器 check_member_bind 判断是否已绑定（用户名或密码为空即未绑定）；新版单服账号在本服 user 表，
 * 以 user.is_guest = 1 为未绑定。index（客户端不调用）不移植。
 * 与旧版的差别：领奖状态以条件更新（guest = 1 → 2）抢占，并发也只发一次（旧版先读后改，改成功才发，结果相同）。
 */
final class RegrewardHandler
{
    /** 绑定帐号添加的元宝 */
    private const int GOLD = 50;
    /** 旧版语言包 regreward_not_reward / regreward_not_bind */
    private const string LANG_NOT_REWARD = '没有可领取的注册礼包奖励，无法进入。';
    private const string LANG_NOT_BIND = '帐号未绑定，无法领取';

    /**
     * 领取绑定奖励: c=regreward&m=get_reward
     */
    public static function get_reward(Context $ctx): array
    {
        $return = ['status' => 0, 'msg' => ''];
        $uid = MemberHandler::resolveUid($ctx);
        $guest = (int)(DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('guest') ?? 0);
        if ($guest !== 1) {
            // 没有可领取的奖励
            $return['status'] = -1;
            $return['msg'] = self::LANG_NOT_REWARD;
            return $return;
        }
        // 判断是否绑定（uc check_member_bind：仍是试玩账号即未绑定）
        if ((int)(DB::table('user')->where('uid', $uid)->whereNull('deleted_at')->value('is_guest') ?? 0) === 1) {
            $return['status'] = -2;
            $return['msg'] = self::LANG_NOT_BIND;
            return $return;
        }
        // 修改用户绑定状态，成功才加元宝
        $affected = DB::table('member')->where('uid', $uid)->where('guest', 1)->whereNull('deleted_at')->update(['guest' => 2]);
        if ($affected > 0) {
            Member::addCurrency($uid, gold: self::GOLD);
        }
        $return['status'] = 1;
        $return['gold'] = self::GOLD;
        $return['all_gold'] = (int)DB::table('member')->where('uid', $uid)->value('gold');
        return $return;
    }
}
