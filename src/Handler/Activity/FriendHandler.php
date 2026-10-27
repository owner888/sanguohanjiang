<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 好友社交、高朋满座与切磋互动处理器 (DDD Activity Domain)
 */
final class FriendHandler
{
    public const array COUNTRIES = [
        0 => '在野',
        1 => '魏国',
        2 => '蜀国',
        3 => '吴国',
    ];

    /**
     * 好友列表: c=friend&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $page = (int)($ctx->input('p', 1));
        $limit = (int)($ctx->input('l', 4));

        $key = 'user_friends:' . $uid;
        $friendUids = Redis::getJson($key);

        if (!is_array($friendUids)) {
            $friendUids = [10001, 10002]; // 初始推荐系统好友
            Redis::setJson($key, $friendUids, 86400 * 30);
        }

        $list = [];
        foreach ($friendUids as $fUid) {
            $m = DB::table('member')->where('uid', $fUid)->first();
            $countryId = (int)($m['country'] ?? 1);
            $countryName = self::COUNTRIES[$countryId] ?? '魏国';
            $nick = (string)($m['nickname'] ?? ('盟友' . $fUid));
            $level = (int)($m['level'] ?? 20);

            $list[] = [
                'fuid' => $fUid,
                'uid' => $fUid,
                'nickname' => $nick,
                'name' => $nick,
                'level' => $level,
                'countryid' => $countryId,
                'country' => $countryName,
                'job' => '偏将军',
                'online' => 1,
                'tree' => 0,
            ];
        }

        $totalCount = count($list);
        $allPage = max(1, (int)ceil($totalCount / max(1, $limit)));

        return [
            'status' => 1,
            'now_number' => $totalCount,
            'all_number' => 100,
            'online' => $totalCount,
            'all_page' => $allPage,
            'page' => $page,
            'list' => $list,
            'total' => $totalCount,
            'message' => 'ok',
        ];
    }

    /**
     * 添加好友: c=friend&m=add_friend
     */
    public static function add_friend(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $targetUid = (int)($ctx->input('f', $ctx->input('target_uid', $ctx->input('fuid', 0))));

        if ($targetUid <= 0 || $targetUid === $uid) {
            return ['status' => 0, 'message' => '不能添加自己或无效的玩家为好友'];
        }

        $key = 'user_friends:' . $uid;
        $friendUids = Redis::getJson($key) ?: [];

        if (in_array($targetUid, $friendUids, true)) {
            return ['status' => 0, 'message' => '对方已经在您的高朋好友列表中'];
        }

        $friendUids[] = $targetUid;
        Redis::setJson($key, $friendUids, 86400 * 30);

        return [
            'status' => 1,
            'target_uid' => $targetUid,
            'message' => '成功结交并添加为三国好友！',
        ];
    }

    /**
     * 删除好友: c=friend&m=del_friend
     */
    public static function del_friend(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $targetUid = (int)($ctx->input('f', $ctx->input('target_uid', $ctx->input('fuid', 0))));

        $key = 'user_friends:' . $uid;
        $friendUids = Redis::getJson($key) ?: [];

        $friendUids = array_values(array_filter($friendUids, fn($id) => (int)$id !== $targetUid));
        Redis::setJson($key, $friendUids, 86400 * 30);

        return [
            'status' => 1,
            'message' => '好友已解除结交关系',
        ];
    }
}
