<?php

declare(strict_types=1);

namespace Hanjiang\Handler;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use OmniPHP\WebSocket\Broadcaster;

final class GeneralInheritHandler
{
    /**
     * 传承主界面: c=general_inherit&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $generals = DB::table('general')->where('uid', $uid)->whereNull('deleted_at')->get();

        $list = [];
        foreach ($generals as $g) {
            $gid = (int)$g['id'];
            $list[$gid] = [
                'id' => $gid,
                'name' => $g['name'],
                'level' => (int)$g['level'],
                'exp' => (int)$g['exp'],
                'wuliup' => (int)($g['wuliup'] ?? 0),
                'zhiliup' => (int)($g['zhiliup'] ?? 0),
                'tiliup' => (int)($g['tiliup'] ?? 0),
            ];
        }

        return [
            'status' => 1,
            'list' => $list,
            'modes' => [
                1 => ['type' => 1, 'name' => '普通传承', 'rate' => 0.8, 'cost_silver' => 10000, 'cost_gold' => 0, 'desc' => '保留 80% 经验与洗髓属性'],
                2 => ['type' => 2, 'name' => '至尊完璧传承', 'rate' => 1.0, 'cost_silver' => 0, 'cost_gold' => 50, 'desc' => '100% 完美无损保留全部经验与洗髓属性'],
            ],
            'message' => 'ok',
        ];
    }

    /**
     * 传承属性预览: c=general_inherit&m=preview
     */
    public static function preview(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $fromGid = (int)$ctx->input('from_gid', 0);
        $toGid = (int)$ctx->input('to_gid', 0);
        $type = (int)$ctx->input('type', 1);

        if ($fromGid <= 0 || $toGid <= 0 || $fromGid === $toGid) {
            return ['status' => 0, 'message' => '请选择两个不同的武将进行传承'];
        }

        $fromGen = DB::table('general')->where('uid', $uid)->where('id', $fromGid)->whereNull('deleted_at')->first();
        $toGen = DB::table('general')->where('uid', $uid)->where('id', $toGid)->whereNull('deleted_at')->first();

        if ($fromGen === null || $toGen === null) {
            return ['status' => -1, 'message' => '指定的武将不存在'];
        }

        $rate = ($type === 2) ? 1.0 : 0.8;

        $targetExp = (int)round((int)$fromGen['exp'] * $rate);
        $targetWuli = (int)round((int)($fromGen['wuliup'] ?? 0) * $rate);
        $targetZhili = (int)round((int)($fromGen['zhiliup'] ?? 0) * $rate);
        $targetTili = (int)round((int)($fromGen['tiliup'] ?? 0) * $rate);

        // 推算继承后新等级
        $simLvl = 1;
        $simExp = $targetExp;
        while ($simExp >= GeneralHandler::getLevelUpExp($simLvl)) {
            $simExp -= GeneralHandler::getLevelUpExp($simLvl);
            $simLvl++;
        }

        return [
            'status' => 1,
            'from_name' => $fromGen['name'],
            'to_name' => $toGen['name'],
            'inherit_rate' => $rate,
            'preview_level' => $simLvl,
            'preview_exp' => $simExp,
            'preview_wuliup' => $targetWuli,
            'preview_zhiliup' => $targetZhili,
            'preview_tiliup' => $targetTili,
            'message' => 'ok',
        ];
    }

    /**
     * 执行武将传承: c=general_inherit&m=go_inherit
     */
    public static function go_inherit(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $fromGid = (int)$ctx->input('from_gid', 0);
        $toGid = (int)$ctx->input('to_gid', 0);
        $type = (int)$ctx->input('type', 1);

        if ($fromGid <= 0 || $toGid <= 0 || $fromGid === $toGid) {
            return ['status' => 0, 'message' => '请选择两个不同的武将进行传承'];
        }

        $fromGen = DB::table('general')->where('uid', $uid)->where('id', $fromGid)->whereNull('deleted_at')->first();
        $toGen = DB::table('general')->where('uid', $uid)->where('id', $toGid)->whereNull('deleted_at')->first();

        if ($fromGen === null || $toGen === null) {
            return ['status' => -1, 'message' => '指定的武将不存在'];
        }

        $costSilver = ($type === 2) ? 0 : 10000;
        $costGold = ($type === 2) ? 50 : 0;

        // 原子安全扣减货币
        if (!MemberHandler::deductCurrency($uid, silver: $costSilver, gold: $costGold)) {
            return [
                'status' => -2,
                'message' => ($type === 2) ? "元宝不足，至尊传承需要 {$costGold} 元宝" : "银两不足，传承需要 {$costSilver} 银两",
            ];
        }

        $rate = ($type === 2) ? 1.0 : 0.8;

        $targetExp = (int)round((int)$fromGen['exp'] * $rate);
        $targetWuli = (int)round((int)($fromGen['wuliup'] ?? 0) * $rate);
        $targetZhili = (int)round((int)($fromGen['zhiliup'] ?? 0) * $rate);
        $targetTili = (int)round((int)($fromGen['tiliup'] ?? 0) * $rate);

        // 重新计算继承后的新等级
        $newLvl = 1;
        $remainExp = $targetExp;
        while ($remainExp >= GeneralHandler::getLevelUpExp($newLvl)) {
            $remainExp -= GeneralHandler::getLevelUpExp($newLvl);
            $newLvl++;
        }

        // 1. 传功武将重置回 1 级初始状态
        DB::table('general')->where('id', $fromGid)->update([
            'level' => 1,
            'exp' => 0,
            'wuliup' => 0,
            'zhiliup' => 0,
            'tiliup' => 0,
        ]);

        // 2. 承功武将注入毕生修为与洗髓属性
        DB::table('general')->where('id', $toGid)->update([
            'level' => $newLvl,
            'exp' => $remainExp,
            'wuliup' => $targetWuli,
            'zhiliup' => $targetZhili,
            'tiliup' => $targetTili,
        ]);

        Redis::del('player:' . $uid);

        // 全服大喇叭
        if ($newLvl >= 5 || $type === 2) {
            $member = DB::table('member')->where('uid', $uid)->first();
            $pName = $member['nickname'] ?? ('主公' . $uid);
            Broadcaster::broadcast('marquee', [
                'content' => "功盖三分国！主公【{$pName}】施展乾坤大挪移，将【{$fromGen['name']}】之毕生修为完美传承给【{$toGen['name']}】！",
                'type' => 'system_broadcast',
            ]);
        }

        return [
            'status' => 1,
            'from_gid' => $fromGid,
            'to_gid' => $toGid,
            'to_name' => $toGen['name'],
            'new_level' => $newLvl,
            'new_exp' => $remainExp,
            'wuliup' => $targetWuli,
            'zhiliup' => $targetZhili,
            'tiliup' => $targetTili,
            'message' => "功法传承大圆满！【{$toGen['name']}】瞬间继承功力，飞升至 {$newLvl} 级！",
        ];
    }
}
