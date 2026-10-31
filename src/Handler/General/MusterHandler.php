<?php

declare(strict_types=1);

namespace Hanjiang\Handler\General;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\MasterData\Skill;
use Hanjiang\MasterData\Talent;
use Hanjiang\Model\OwnMatrix;
use Hanjiang\Model\OwnMount;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 点将台（移植旧版 controllers/muster.php：index / get_general_info / go_battle / go_rest）
 *
 * 出征名单为 general.goout；阵法站位只在 matrix 表（Model\OwnMatrix）。sale_general 仍是新版自定的遣散玩法。
 */
final class MusterHandler
{
    /**
     * 出征名单上限（general_model::go_battle：battle_count() >= 8 时出征队列已满）
     */
    public const int MAX_BATTLE = 8;

    /**
     * index 的 first 字段（旧版 first_field）
     */
    private const array INDEX_FIELD = [
        'id', 'quality', 'image', 'type', 'star', 'name', 'turn',
        'level', 'wuli', 'zhili', 'tili', 'zhiliup', 'tiliup', 'wuliup', 'skill', 'talent', 'goout',
    ];

    /**
     * get_general_info 的 info 字段（旧版 info_field）
     */
    private const array INFO_FIELD = [
        'id', 'image', 'quality', 'type', 'star', 'name', 'turn',
        'level', 'wuli', 'zhili', 'tili', 'zhiliup', 'tiliup', 'wuliup', 'skill', 'talent', 'goout',
    ];

    /**
     * 拥有将领列表: c=muster&m=index（旧版 controllers/muster.php:index）
     *
     * page / perpage（默认 1 / 9）分页，按 goout DESC, star DESC, level DESC 排序（同序按 id）；list 键从 1 开始，first 为本页第一个将领详情。
     * 状态码：-4 天赋获取错误；-3 技能获取错误或本页没有将领；-2 未知错误；2 将领不存在；1 成功。
     * VIP 7 级以上输出 sale_cd。
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $return = ['status' => 0, 'page' => 0, 'list' => [], 'first' => []];
        $getPage = (int)$ctx->input('page', 0);
        $getPerpage = (int)$ctx->input('perpage', 0);
        $page = $getPage > 0 ? $getPage : 1;
        $perpage = $getPerpage > 0 ? $getPerpage : 9;

        // general_model::get_own_list
        $totals = DB::table('general')->where('uid', $uid)->whereNull('deleted_at')->count();
        if ($totals === 0) {
            // 将领不存在
            $return['status'] = 2;
            return $return;
        }
        $pages = (int)ceil($totals / $perpage);
        $listPage = $page > $pages ? $pages : $page;
        $rows = DB::table('general')
            ->where('uid', $uid)
            ->whereNull('deleted_at')
            ->orderBy('goout', 'DESC')
            ->orderBy('star', 'DESC')
            ->orderBy('level', 'DESC')
            ->orderBy('id')
            ->limit($perpage)
            ->offset(($listPage - 1) * $perpage)
            ->get();
        if ($rows === []) {
            $return['status'] = -3;
            return $return;
        }

        $first = $rows[0];
        if ((int)$first['skillid'] === 0) {
            $return['status'] = -3;
            return $return;
        }
        if ((int)$first['talentid'] === 0) {
            $return['status'] = -4;
            return $return;
        }

        $list = [];
        foreach ($rows as $key => $value) {
            $list[$key + 1] = [
                'id' => (int)$value['id'],
                'quality' => (int)$value['quality'],
                'name' => (string)$value['name'],
                'image' => (int)$value['image'],
                'type' => (int)$value['type'],
                'goout' => (int)$value['goout'],
            ];
        }

        // 依赖未移植：获得武将气泡 member_field.ongeneral（进入点将台时清除）

        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first() ?? [];
        if ((int)($member['vip'] ?? 0) >= 7) {
            // 依赖未移植：武将折旧 CD member_field.general_sale（未存储，按 0 即 CD 已过）
            $return['sale_cd'] = 0;
        }

        $return['list'] = $list;
        $return['page'] = $page;
        $return['first'] = self::generalFields($first, self::INDEX_FIELD);
        $return['status'] = 1;
        return $return;
    }

    /**
     * 获取单个将领信息: c=muster&m=get_general_info（旧版 controllers/muster.php:get_general_info）
     *
     * 状态码：-4 天赋数据异常；-3 技能数据异常；-1 传参错误或将领不存在；1 成功。
     */
    public static function get_general_info(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $return = ['status' => 0, 'info' => []];
        $gid = max(0, (int)$ctx->input('gid', 0));

        $info = self::ownGeneral($uid, $gid);
        if ($info === null) {
            $return['status'] = -1;
            return $return;
        }
        if ((int)$info['skillid'] === 0) {
            $return['status'] = -3;
            return $return;
        }
        if ((int)$info['talentid'] === 0) {
            $return['status'] = -4;
            return $return;
        }

        $return['info'] = self::generalFields($info, self::INFO_FIELD);
        $return['status'] = 1;
        return $return;
    }

    /**
     * 出征: c=muster&m=go_battle（旧版 controllers/muster.php:go_battle + general_model::go_battle）
     *
     * 出征名单上限 8 人（battle_count >= 8 先于其他检查）。只改出征名单 goout，不改阵法站位（旧版同）。
     * 状态码：-1 将领不存在；1 出征成功；2 已在出征队列；3 出征人数已满。
     */
    public static function go_battle(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $return = ['status' => 0];
        $gid = (int)$ctx->input('gid', 0);

        // check_battle：-1 不存在，1 休息中，2 出征中
        $general = self::ownGeneral($uid, $gid);
        $result = $general === null ? -1 : ((int)$general['goout'] === 1 ? 2 : 1);
        if (self::battleCount($uid) >= self::MAX_BATTLE) {
            // 出征队列已满
            $return['status'] = 3;
            return $return;
        }
        if ($result !== 1) {
            $return['status'] = $result;
            return $return;
        }

        $affected = DB::table('general')->where('uid', $uid)->where('id', $gid)->whereNull('deleted_at')->update(['goout' => 1]);
        $return['status'] = $affected > 0 ? 1 : 2;
        Redis::del('player:' . $uid);
        return $return;
    }

    /**
     * 休息: c=muster&m=go_rest（旧版 controllers/muster.php:go_rest）
     *
     * 依次：出征名单只剩 1 人时拒绝（4）；训练中的将领需 confrim=1 才终止训练（否则 2）；
     * 从所有阵法站位上撤下（撤下后启用的阵法没有其他将领时 tip = 1，旧版照样撤下）；卸下全部装备与坐骑；goout = 0。
     * 状态码：-5 下阵失败；-4 训练终止失败；-3 获取将领信息失败；-2 卸下装备失败；-1 休息失败；1 成功；2 将领正在训练；3 将领已休息；4 最后一个将领。
     */
    public static function go_rest(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $return = ['status' => 0, 'tip' => 0];
        $gid = (int)$ctx->input('gid', 0);
        $confrim = (int)$ctx->input('confrim', 0);
        if (self::battleCount($uid) === 1) {
            // 最后一个将领 4
            $return['status'] = 4;
            return $return;
        }

        // 检查将领是否训练中
        $unique = PracticeHandler::checkPractice($uid, $gid);
        $field = DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->first() ?? [];
        if ($unique !== 0) {
            if ($confrim) {
                $stop = PracticeHandler::legacyStop($uid, $unique, (int)($field['practicetime'] ?? 0), (int)($field['practicenow'] ?? 0));
                if ($stop !== 1) {
                    // 训练终止失败 -4
                    $return['status'] = -4;
                    return $return;
                }
            } else {
                // 将领正在训练中 2
                $return['status'] = 2;
                return $return;
            }
        }

        $own = self::ownGeneral($uid, $gid);
        if ($own === null) {
            // 获取将领信息失败 -3
            $return['status'] = -3;
            return $return;
        }
        if ((int)$own['goout'] === 0) {
            // 将领已休息 3
            $return['status'] = 3;
            return $return;
        }

        // 下阵（旧版没有任何阵法行时 get_matrix_by_gid 返回 ['status' => -1]，随后 unset 报致命错误；这里按无需下阵处理）
        $info = OwnMatrix::byGid($uid, $gid);
        if ($info !== []) {
            $matrixid = OwnMatrix::activeId($uid);
            if ($matrixid !== 0) {
                // 将领在启用的阵法上时，检查撤下后阵法上是否还有其他将领
                $mid = null;
                foreach ($info as $value) {
                    if ($value['mid'] === $matrixid) {
                        $mid = $matrixid;
                    }
                }
                if ($mid !== null) {
                    $matrix = OwnMatrix::own($uid, $mid);
                    $last = 1;
                    for ($i = 1; $i < 10; $i++) {
                        if (($matrix['position' . $i] ?? 0) > 0 && $matrix['position' . $i] !== $gid) {
                            $last = 0;
                        }
                    }
                    if ($last === 1) {
                        $return['tip'] = 1;
                    }
                }
            }

            foreach ($info as $key => $value) {
                unset($value['uid']);
                for ($i = 1; $i < 10; $i++) {
                    if (isset($value['position' . $i])) {
                        $value['position' . $i] = 0;
                    }
                }
                $info[$key] = $value;
            }
            if (OwnMatrix::goRest($uid, $info) !== 1) {
                // 下阵失败 -5
                $return['status'] = -5;
                return $return;
            }
        }

        // 卸下所有装备（equipment_model::unequip_all；旧版 own_general.eid1~eid4 新版由 equipment.generalid 派生）
        $equipped = DB::table('equipment')->where('uid', $uid)->where('generalid', $gid)->whereNull('deleted_at')->count();
        if ($equipped > 0
            && DB::table('equipment')->where('uid', $uid)->where('generalid', $gid)->whereNull('deleted_at')->update(['generalid' => 0]) === 0) {
            // 卸下装备失败 -2
            $return['status'] = -2;
            return $return;
        }

        // 卸下所有坐骑（mount_model::take_down_all：将领 mid0~mid4 清零，own_mount.gid 清零）
        OwnMount::takeDownAll($uid, $gid);

        // 休息（general_model::go_rest）
        $affected = DB::table('general')->where('uid', $uid)->where('id', $gid)->whereNull('deleted_at')->update(['goout' => 0]);
        Redis::del('player:' . $uid);
        if ($affected <= 0) {
            // 休息失败 -1
            $return['status'] = -1;
            return $return;
        }
        $return['status'] = 1;
        return $return;
    }

    /**
     * 遣散闲置武将退役: c=muster&m=sale_general
     */
    public static function sale_general(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $gid = (int)($ctx->input('id', $ctx->input('gid', 0)));

        $general = DB::table('general')
            ->where('uid', $uid)
            ->where('id', $gid)
            ->whereNull('deleted_at')
            ->first();

        if ($general === null) {
            return ['status' => -1, 'message' => '武将不存在'];
        }

        if ((int)$general['generalid'] === 1) {
            return ['status' => 0, 'message' => '主公将领不可遣散退役'];
        }

        if ((int)$general['goout'] === 1) {
            return ['status' => 0, 'message' => '该武将正在出征中，请先休息'];
        }

        $level = max(1, (int)$general['level']);
        $severancePay = $level * 3000;

        // 软删除武将 (遵循 deleted_at 规范)
        DB::table('general')->where('id', $gid)->update([
            'deleted_at' => date('Y-m-d H:i:s'),
        ]);

        MemberHandler::addCurrency($uid, silver: $severancePay);
        Redis::del('player:' . $uid);

        return [
            'status' => 1,
            'gid' => $gid,
            'name' => $general['name'],
            'severance_pay' => $severancePay,
            'message' => "【{$general['name']}】功成身退，卸甲归田！奉送退役安家银两 {$severancePay}！",
        ];
    }

    /**
     * 出征人数（general_model::battle_count）
     */
    private static function battleCount(int $uid): int
    {
        return DB::table('general')->where('uid', $uid)->where('goout', 1)->whereNull('deleted_at')->count();
    }

    /**
     * 拥有将领（general_model::get_own_by_id 单个 id）
     *
     * @return array<string, mixed>|null
     */
    private static function ownGeneral(int $uid, int $gid): ?array
    {
        return DB::table('general')->where('uid', $uid)->where('id', $gid)->whereNull('deleted_at')->first();
    }

    /**
     * 按旧版字段表输出将领详情（skill / talent 为技能、天赋子数组）
     *
     * @param array<string, mixed> $g
     * @param list<string> $fields
     * @return array<string, mixed>
     */
    private static function generalFields(array $g, array $fields): array
    {
        $return = [];
        foreach ($fields as $field) {
            $return[$field] = match ($field) {
                'name' => (string)$g['name'],
                'skill' => self::musterSkill((int)$g['skillid']),
                'talent' => self::musterTalent((int)$g['talentid']),
                default => (int)$g[$field],
            };
        }
        return $return;
    }

    /**
     * 技能：旧版 controllers/muster.php 取 id / name / type / description，技能不存在时各项为空串
     *
     * @return array<string, mixed>
     */
    private static function musterSkill(int $skillid): array
    {
        $skill = Skill::ROWS[$skillid] ?? null;
        $return = [];
        foreach (['id', 'name', 'type', 'description'] as $field) {
            $return[$field] = $skill !== null ? $skill[$field] : '';
        }
        return $return;
    }

    /**
     * 天赋：旧版 controllers/muster.php 取 id / name / description，天赋不存在时各项为空串
     *
     * @return array<string, mixed>
     */
    private static function musterTalent(int $talentid): array
    {
        $talent = Talent::ROWS[$talentid] ?? null;
        $return = [];
        foreach (['id', 'name', 'description'] as $field) {
            $return[$field] = $talent !== null ? $talent[$field] : '';
        }
        return $return;
    }
}
