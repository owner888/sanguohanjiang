<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\General\GeneralHandler;
use Hanjiang\Handler\Pvp\CountryHandler;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 玩家个人资料、武将出征展示与成就总览 (DDD Activity Domain)
 */
final class InformationHandler
{
    /**
     * 玩家个人资料与将领全览: c=information&m=index
     */
    public static function index(Context $ctx): array
    {
        $myUid = MemberHandler::resolveUid($ctx);
        $targetUid = (int)$ctx->input('uid', $myUid);
        if ($targetUid <= 0) {
            $targetUid = $myUid;
        }

        $member = DB::table('member')->where('uid', $targetUid)->whereNull('deleted_at')->first();
        if ($member === null) {
            return [
                'status' => -1,
                'message' => '获取用户信息失败',
            ];
        }

        $field = DB::table('member_field')->where('uid', $targetUid)->whereNull('deleted_at')->first();
        $rank = (int)($field['rank'] ?? 0);
        if ($rank <= 0) {
            $arena = DB::table('arena')->where('uid', $targetUid)->first();
            $rank = (int)($arena['rank'] ?? 1);
        }

        $countryId = (int)($member['country'] ?? 0);
        $countryName = CountryHandler::COUNTRIES[$countryId]['name'] ?? '';

        $player = [
            'nickname' => (string)($member['nickname'] ?? ('主公' . $targetUid)),
            'vip' => (int)($member['vip'] ?? 1),
            'level' => max(1, (int)($member['level'] ?? 1)),
            'rank' => $rank,
            'country' => $countryName,
            'country_level' => ($countryId > 0) ? 1 : 0,
        ];

        // 查询玩家所有武将 (过滤软删除)
        $generals = DB::table('general')->where('uid', $targetUid)->whereNull('deleted_at')->get();
        if (empty($generals)) {
            // 初始化初始武将
            DB::table('general')->insert([
                'uid' => $targetUid,
                'generalid' => 1,
                'name' => $player['nickname'],
                'level' => 1,
                'exp' => 0,
                'pos' => 2,
                'matrix_id' => 1,
                'status' => 1,
            ]);
            $generals = DB::table('general')->where('uid', $targetUid)->whereNull('deleted_at')->get();
        }

        // 排序规则：出征武将优先，等级降序
        usort($generals, static function (array $a, array $b): int {
            $aOut = ((int)($a['pos'] ?? 0)) > 0 ? 1 : 0;
            $bOut = ((int)($b['pos'] ?? 0)) > 0 ? 1 : 0;
            if ($aOut !== $bOut) {
                return $bOut <=> $aOut;
            }
            $aLevel = (int)($a['level'] ?? 1);
            $bLevel = (int)($b['level'] ?? 1);
            if ($aLevel !== $bLevel) {
                return $bLevel <=> $aLevel;
            }
            return ((int)($a['id'] ?? 0)) <=> ((int)($b['id'] ?? 0));
        });

        // 查所有装备
        $allEquips = DB::table('equipment')->where('uid', $targetUid)->whereNull('deleted_at')->get();
        $equipsByGeneral = [];
        foreach ($allEquips as $eq) {
            $gid = (int)$eq['generalid'];
            if ($gid > 0) {
                $equipsByGeneral[$gid][] = $eq;
            }
        }

        $list = [];
        $idx = 1;
        $totalPower = 0;
        foreach ($generals as $g) {
            $gid = (int)$g['id'];
            $equipped = $equipsByGeneral[$gid] ?? [];
            $heroData = GeneralHandler::formatHeroData($g, $equipped, $targetUid);
            $totalPower += (int)($heroData['attack'] * 2 + $heroData['defense'] + $heroData['hp'] * 0.2);

            if ($idx <= 8) {
                $list[(string)$idx] = $heroData;
                $idx++;
            }
        }

        $isFriend = ($myUid === $targetUid) ? 1 : 0;

        return [
            'status' => 1,
            'player' => $player,
            'list' => $list,
            'friend' => $isFriend,
            'tree' => 0,
            // 向下兼容保留原字段
            'uid' => $targetUid,
            'nickname' => $player['nickname'],
            'level' => $player['level'],
            'country' => $countryId,
            'country_name' => $countryName,
            'vip' => $player['vip'],
            'total_power' => max(5000, $totalPower),
            'general_count' => count($generals),
            'message' => 'ok',
        ];
    }

    /**
     * 个人战斗属性总览 (委托至 index 动作)
     */
    public static function get_info(Context $ctx): array
    {
        return self::index($ctx);
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
