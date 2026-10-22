<?php

declare(strict_types=1);

namespace Hanjiang\Handler;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

final class InformationHandler
{
    /**
     * 个人成就功勋总览: c=information&m=index 或 m=get_info
     */
    public static function index(Context $ctx): array
    {
        return self::get_info($ctx);
    }

    public static function get_info(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();

        $generals = DB::table('general')->where('uid', $uid)->whereNull('deleted_at')->get();
        $totalPower = 0;
        foreach ($generals as $g) {
            $equipped = DB::table('equipment')->where('uid', $uid)->where('generalid', (int)$g['id'])->whereNull('deleted_at')->get();
            $stats = GeneralHandler::calculateGeneralStats($g, $equipped);
            $totalPower += (int)($stats['attack'] * 2 + $stats['defense'] + $stats['hp'] * 0.2);
        }

        return [
            'status' => 1,
            'uid' => $uid,
            'nickname' => $member['nickname'] ?? ('主公' . $uid),
            'level' => (int)($member['level'] ?? 1),
            'country' => (int)($member['country'] ?? 0),
            'country_name' => CountryHandler::COUNTRIES[(int)($member['country'] ?? 0)]['name'] ?? '在野游侠',
            'vip' => (int)($member['vip'] ?? 1),
            'total_power' => max(5000, $totalPower),
            'general_count' => count($generals),
            'message' => 'ok',
        ];
    }

    /**
     * 获取个人成就列表: c=information&m=get_own_achievement
     */
    public static function get_own_achievement(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);

        $achievements = [
            1 => ['id' => 1, 'name' => '初出茅庐', 'desc' => '通关大兴山第一个据点', 'achieved' => 1],
            2 => ['id' => 2, 'name' => '神将麾下', 'desc' => '招募第一位历史名将', 'achieved' => 1],
            3 => ['id' => 3, 'name' => '百炼成钢', 'desc' => '强化一件装备至 2 级', 'achieved' => 1],
            4 => ['id' => 4, 'name' => '威震中原', 'desc' => '等级达到 10 级', 'achieved' => 0],
        ];

        return [
            'status' => 1,
            'list' => array_values($achievements),
            'total_score' => 300,
            'message' => 'ok',
        ];
    }
}
