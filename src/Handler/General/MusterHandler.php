<?php

declare(strict_types=1);

namespace Hanjiang\Handler\General;

use Hanjiang\Handler\City\MemberHandler;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 点将台大营名册、出征派遣与闲置武将遣散处理器 (DDD General Domain)
 */
final class MusterHandler
{
    /**
     * 点将台主界面: c=muster&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $generals = DB::table('general')->where('uid', $uid)->whereNull('deleted_at')->get();

        if (empty($generals)) {
            // 初始化初始主公将领
            $member = DB::table('member')->where('uid', $uid)->first();
            $nickname = $member['nickname'] ?? '天命主公';
            DB::table('general')->insert([
                'uid' => $uid,
                'generalid' => 1,
                'name' => $nickname,
                'level' => 1,
                'exp' => 0,
                'pos' => 2,
                'matrix_id' => 1,
                'status' => 1,
            ]);
            $generals = DB::table('general')->where('uid', $uid)->whereNull('deleted_at')->get();
        }

        // 排序规则：出征武将优先，等级从高到低
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

        $list = [];
        $idx = 1;
        foreach ($generals as $g) {
            $gid = (int)$g['id'];
            $pos = (int)($g['pos'] ?? 0);
            $level = max(1, (int)($g['level'] ?? 1));

            $list[(string)$idx] = [
                'id' => $gid,
                'quality' => (int)($g['quality'] ?? 3),
                'name' => (string)$g['name'],
                'image' => (int)($g['image'] ?? $g['generalid'] ?? 1),
                'type' => (int)($g['type'] ?? 1),
                'goout' => ($pos > 0) ? 1 : 0,
                'k' => $idx,
                'level' => $level,
                'pos' => $pos,
                'severance_pay' => $level * 3000,
            ];
            $idx++;
        }

        $firstGeneral = !empty($generals) ? self::formatMusterGeneral($generals[0]) : [];

        return [
            'status' => 1,
            'page' => 1,
            'list' => $list,
            'first' => $firstGeneral,
            'sale_cd' => 0,
            'total' => count($list),
            'message' => 'ok',
        ];
    }

    /**
     * 获取单个将领详情: c=muster&m=get_general_info
     */
    public static function get_general_info(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $gid = (int)($ctx->input('gid', 0));

        if ($gid <= 0) {
            return ['status' => -1, 'message' => '参数错误'];
        }

        $general = DB::table('general')
            ->where('uid', $uid)
            ->where('id', $gid)
            ->whereNull('deleted_at')
            ->first();

        if ($general === null) {
            return ['status' => -2, 'message' => '将领不存在'];
        }

        return [
            'status' => 1,
            'info' => self::formatMusterGeneral($general),
            'message' => 'ok',
        ];
    }

    /**
     * 武将出征派遣: c=muster&m=go_battle
     */
    public static function go_battle(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $gid = (int)($ctx->input('gid', 0));

        if ($gid <= 0) {
            return ['status' => -1, 'message' => '参数错误'];
        }

        $general = DB::table('general')
            ->where('uid', $uid)
            ->where('id', $gid)
            ->whereNull('deleted_at')
            ->first();

        if ($general === null) {
            return ['status' => -1, 'message' => '将领不存在'];
        }

        if ((int)$general['pos'] > 0) {
            return ['status' => 2, 'message' => '已在出征队列中'];
        }

        // 查询当前上阵战位
        $activeGenerals = DB::table('general')
            ->where('uid', $uid)
            ->where('pos', '>', 0)
            ->whereNull('deleted_at')
            ->get();

        if (count($activeGenerals) >= 5) {
            return ['status' => 3, 'message' => '出征人数已满'];
        }

        $usedPositions = [];
        foreach ($activeGenerals as $ag) {
            $usedPositions[(int)$ag['pos']] = true;
        }

        // 寻找 1~9 中首个空闲阵位
        $newPos = 2; // 默认前中位置
        for ($p = 1; $p <= 9; $p++) {
            if (!isset($usedPositions[$p])) {
                $newPos = $p;
                break;
            }
        }

        DB::table('general')->where('id', $gid)->update([
            'pos' => $newPos,
        ]);

        Redis::del('player:' . $uid);

        return [
            'status' => 1,
            'gid' => $gid,
            'pos' => $newPos,
            'message' => '出征成功',
        ];
    }

    /**
     * 武将下阵休息: c=muster&m=go_rest
     */
    public static function go_rest(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $gid = (int)($ctx->input('gid', 0));

        if ($gid <= 0) {
            return ['status' => -1, 'tip' => 0, 'message' => '参数错误'];
        }

        $general = DB::table('general')
            ->where('uid', $uid)
            ->where('id', $gid)
            ->whereNull('deleted_at')
            ->first();

        if ($general === null) {
            return ['status' => -3, 'tip' => 0, 'message' => '将领不存在'];
        }

        if ((int)$general['pos'] <= 0) {
            return ['status' => 3, 'tip' => 0, 'message' => '将领已在休息中'];
        }

        // 统计当前出征人数
        $battleCount = DB::table('general')
            ->where('uid', $uid)
            ->where('pos', '>', 0)
            ->whereNull('deleted_at')
            ->count();

        if ($battleCount <= 1) {
            return ['status' => 4, 'tip' => 0, 'message' => '至少需保留一名武将出征'];
        }

        DB::table('general')->where('id', $gid)->update([
            'pos' => 0,
        ]);

        Redis::del('player:' . $uid);

        return [
            'status' => 1,
            'tip' => 0,
            'gid' => $gid,
            'message' => '将领已下阵休息',
        ];
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

        if ((int)$general['pos'] > 0) {
            return ['status' => 0, 'message' => '该武将正在阵法出征中，请先在阵法中撤下阵位'];
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
     * 格式化点将台武将详细数据对象 (完全匹配 musterpop.js buildRight 结构)
     *
     * @param array<string, mixed> $g
     * @return array<string, mixed>
     */
    private static function formatMusterGeneral(array $g): array
    {
        $pos = (int)($g['pos'] ?? 0);
        $level = max(1, (int)($g['level'] ?? 1));

        return [
            'id' => (int)$g['id'],
            'quality' => (int)($g['quality'] ?? 3),
            'image' => (int)($g['image'] ?? $g['generalid'] ?? 1),
            'type' => (int)($g['type'] ?? 1),
            'star' => (int)($g['star'] ?? 1),
            'name' => (string)$g['name'],
            'turn' => (int)($g['turn'] ?? 0),
            'level' => $level,
            'wuli' => 85,
            'zhili' => 70,
            'tili' => 90,
            'wuliup' => (int)($g['wuliup'] ?? 0),
            'zhiliup' => (int)($g['zhiliup'] ?? 0),
            'tiliup' => (int)($g['tiliup'] ?? 0),
            'skill' => GeneralHandler::getGeneralSkill($g),
            'talent' => GeneralHandler::getGeneralTalent($g),
            'goout' => ($pos > 0) ? 1 : 0,
        ];
    }
}
