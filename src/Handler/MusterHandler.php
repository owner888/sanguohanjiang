<?php

declare(strict_types=1);

namespace Hanjiang\Handler;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

final class MusterHandler
{
    /**
     * 点将台主界面: c=muster&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $generals = DB::table('general')->where('uid', $uid)->whereNull('deleted_at')->get();

        $list = [];
        foreach ($generals as $g) {
            $gid = (int)$g['id'];
            $pos = (int)$g['pos'];
            $level = (int)$g['level'];
            $list[] = [
                'id' => $gid,
                'name' => $g['name'],
                'level' => $level,
                'pos' => $pos,
                'goout' => ($pos > 0) ? 1 : 0,
                'severance_pay' => $level * 3000,
            ];
        }

        return [
            'status' => 1,
            'list' => $list,
            'total' => count($list),
            'message' => 'ok',
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

        return [
            'status' => 1,
            'gid' => $gid,
            'name' => $general['name'],
            'severance_pay' => $severancePay,
            'message' => "【{$general['name']}】功成身退，卸甲归田！奉送退役安家银两 {$severancePay}！",
        ];
    }
}
