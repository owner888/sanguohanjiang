<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\General\GeneralHandler;
use Hanjiang\Model\Beauty;
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
        // 旧版 information.php：演武榜排名取 member_field.rank（未入榜为 0）
        $rank = (int)($field['rank'] ?? 0);

        $countryId = (int)($member['country'] ?? 0);
        $countryName = DB::table('country')->where('id', $countryId)->whereNull('deleted_at')->value('name') ?? '';

        $player = [
            'nickname' => (string)($member['nickname'] ?? ('主公' . $targetUid)),
            'vip' => (int)($member['vip'] ?? 1),
            'level' => max(1, (int)($member['level'] ?? 1)),
            'rank' => $rank,
            'country' => $countryName,
            'country_level' => ($countryId > 0) ? 1 : 0,
        ];

        // 查询玩家所有武将 (过滤软删除)
        // 查看他人信息时不再替对方补武将（旧版无此行为，初始武将只在注册时发放）
        $generals = DB::table('general')->where('uid', $targetUid)->whereNull('deleted_at')->get();

        // 排序规则：出征武将优先，等级降序
        usort($generals, static function (array $a, array $b): int {
            $aOut = (int)$a['goout'];
            $bOut = (int)$b['goout'];
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

        $list = [];
        $idx = 1;
        foreach ($generals as $g) {
            if ($idx > 8) {
                break;
            }
            // 武将展示字段按旧版 general get_info（info_field + hp）
            $list[(string)$idx] = GeneralHandler::legacyInfo($targetUid, $g) ?? [];
            $idx++;
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

    /**
     * 他人的铜雀台美人（移植旧版 controllers/information.php get_all_beauty_info）: c=information&m=get_all_beauty_info&uid=
     *
     * 对方 60 级以上时列出激活中的美人与已解锁技能（技能等级同 beauty index）；不到 60 级 list 为空。
     * status：-1 参数错误；1 成功
     */
    public static function get_all_beauty_info(Context $ctx): array
    {
        $uid = (int)$ctx->input('uid', 0);
        if ($uid <= 0) {
            // 缺少参数
            return ['status' => -1, 'msg' => '参数错误'];
        }
        $level = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('level');
        $newReturn = [];
        if ($level !== null && (int)$level >= 60) {
            foreach (Beauty::ownAll($uid) as $val) {
                if ($val['open_status'] !== 1) {
                    continue;
                }
                $beautyLevel = $val['beauty_level'];
                $skillIds = [$val['skill1'], $val['skill2'], $val['skill3']];
                $lock = Beauty::upStatus($beautyLevel)['lock'];
                $levelArray = match ($lock) {
                    1 => [$beautyLevel, 1, 1],
                    2 => [$beautyLevel, $beautyLevel - 20, 1],
                    3 => [$beautyLevel, $beautyLevel - 20, $beautyLevel - 60],
                    default => [],
                };
                // 显示已经解锁的技能
                $skillInfo = [];
                foreach ($skillIds as $key => $value) {
                    if ($lock >= $key + 1) {
                        $skill = Beauty::skillInfo($val['beauty_id'], $value, $levelArray[$key]);
                        $skillInfo[$key] = [
                            'skill_name' => $skill['skill_name'] ?? null,
                            'skill_content' => $skill['skill_content'] ?? null,
                            'skill_value' => $skill['skill_value'] ?? null,
                        ];
                    }
                }
                $newReturn[] = [
                    'beauty_id' => $val['beauty_id'],
                    'beauty_name' => $val['beauty_name'],
                    'beauty_level' => $beautyLevel,
                    'skill_info' => $skillInfo,
                ];
            }
        }
        return ['status' => 1, 'list' => $newReturn];
    }
}
