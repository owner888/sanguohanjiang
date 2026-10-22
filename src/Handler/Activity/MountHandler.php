<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use OmniPHP\WebSocket\Broadcaster;

final class MountHandler
{
    /**
     * 三国名驹图鉴
     *
     * @var array<int, array<string, mixed>>
     */
    public const array MOUNT_CONFIGS = [
        1 => ['id' => 1, 'name' => '神品·赤兔马', 'quality' => 5, 'hp' => 1200, 'speed' => 60, 'attack' => 100],
        2 => ['id' => 2, 'name' => '绝世·的卢马', 'quality' => 4, 'hp' => 800, 'speed' => 50, 'attack' => 60],
        3 => ['id' => 3, 'name' => '灵骏·绝影', 'quality' => 4, 'hp' => 700, 'speed' => 45, 'attack' => 50],
        4 => ['id' => 4, 'name' => '神骏·爪黄飞电', 'quality' => 3, 'hp' => 500, 'speed' => 35, 'attack' => 40],
    ];

    /**
     * 获取玩家战马列表
     *
     * @return array<int, array{id: int, level: int, gid: int}>
     */
    public static function getUserMounts(int $uid): array
    {
        $cached = Redis::getJson('user_mounts:' . $uid);
        if (is_array($cached)) {
            return $cached;
        }

        $init = [
            1 => ['id' => 1, 'level' => 1, 'gid' => 0],
            2 => ['id' => 2, 'level' => 1, 'gid' => 0],
        ];
        Redis::setJson('user_mounts:' . $uid, $init, 86400 * 30);
        return $init;
    }

    /**
     * 战马主界面: c=mount&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $userMounts = self::getUserMounts($uid);

        $generals = DB::table('general')->where('uid', $uid)->get();
        $generalMap = [];
        foreach ($generals as $g) {
            $generalMap[(int)$g['id']] = $g['name'];
        }

        $list = [];
        foreach ($userMounts as $id => $m) {
            $cfg = self::MOUNT_CONFIGS[$id] ?? self::MOUNT_CONFIGS[1];
            $lvl = (int)$m['level'];
            $gid = (int)$m['gid'];

            $list[$id] = array_merge($cfg, [
                'level' => $lvl,
                'gid' => $gid,
                'general_name' => $generalMap[$gid] ?? '',
                'total_hp' => $cfg['hp'] + ($lvl - 1) * 200,
                'total_speed' => $cfg['speed'] + ($lvl - 1) * 10,
                'total_attack' => $cfg['attack'] + ($lvl - 1) * 20,
                'upgrade_cost' => $lvl * 3000,
            ]);
        }

        return [
            'status' => 1,
            'list' => $list,
            'total_mounts' => count($list),
            'message' => 'ok',
        ];
    }

    /**
     * 乘骑战马: c=mount&m=fit_on
     */
    public static function fit_on(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $mountId = (int)($ctx->input('id', $ctx->input('mount_id', 1)));
        $gid = (int)($ctx->input('gid', 1));

        $general = DB::table('general')->where('uid', $uid)->where('id', $gid)->first();
        if ($general === null) {
            return ['status' => -1, 'message' => '武将不存在'];
        }

        $userMounts = self::getUserMounts($uid);
        if (!isset($userMounts[$mountId])) {
            return ['status' => -2, 'message' => '您尚未驯化该名驹'];
        }

        // 若当前武将已有坐骑，自动替换卸下
        foreach ($userMounts as $k => $um) {
            if ((int)$um['gid'] === $gid) {
                $userMounts[$k]['gid'] = 0;
            }
        }

        $userMounts[$mountId]['gid'] = $gid;
        Redis::setJson('user_mounts:' . $uid, $userMounts, 86400 * 30);

        $cfg = self::MOUNT_CONFIGS[$mountId];

        return [
            'status' => 1,
            'mount_id' => $mountId,
            'mount_name' => $cfg['name'],
            'gid' => $gid,
            'general_name' => $general['name'],
            'message' => "【{$general['name']}】成功乘骑名驹【{$cfg['name']}】，战力大幅跃升！",
        ];
    }

    /**
     * 卸下战马: c=mount&m=take_down
     */
    public static function take_down(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $mountId = (int)($ctx->input('id', $ctx->input('mount_id', 1)));

        $userMounts = self::getUserMounts($uid);
        if (isset($userMounts[$mountId])) {
            $userMounts[$mountId]['gid'] = 0;
            Redis::setJson('user_mounts:' . $uid, $userMounts, 86400 * 30);
        }

        return [
            'status' => 1,
            'mount_id' => $mountId,
            'message' => '战马已安全牵回马厩',
        ];
    }

    /**
     * 驯化升级战马: c=mount&m=levelup
     */
    public static function levelup(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $mountId = (int)($ctx->input('id', $ctx->input('mount_id', 1)));

        $userMounts = self::getUserMounts($uid);
        if (!isset($userMounts[$mountId])) {
            return ['status' => 0, 'message' => '名驹不存在'];
        }

        $curLevel = (int)$userMounts[$mountId]['level'];
        $costSilver = $curLevel * 3000;

        $member = DB::table('member')->where('uid', $uid)->first();
        $curSilver = (int)($member['silver'] ?? 0);

        if ($curSilver < $costSilver) {
            return ['status' => -1, 'message' => "银两不足，驯化名驹需要 {$costSilver} 银两"];
        }

        $newLevel = $curLevel + 1;
        $userMounts[$mountId]['level'] = $newLevel;
        Redis::setJson('user_mounts:' . $uid, $userMounts, 86400 * 30);

        $newSilver = $curSilver - $costSilver;
        DB::table('member')->where('uid', $uid)->update(['silver' => $newSilver]);
        Redis::del('player:' . $uid);

        $cfg = self::MOUNT_CONFIGS[$mountId];

        return [
            'status' => 1,
            'mount_id' => $mountId,
            'mount_name' => $cfg['name'],
            'level' => $newLevel,
            'silver' => $newSilver,
            'message' => "名驹【{$cfg['name']}】驯化进阶成功！提升至 {$newLevel} 阶！",
        ];
    }
}
