<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Equip;

use Hanjiang\Handler\City\MemberHandler;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

final class RefineHandler
{
    /**
     * 装备精炼主界面: c=refine&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $eid = (int)($ctx->input('id', $ctx->input('eid', 1)));

        $equip = DB::table('equipment')->where('uid', $uid)->where('id', $eid)->whereNull('deleted_at')->first();
        if ($equip === null) {
            $first = DB::table('equipment')->where('uid', $uid)->whereNull('deleted_at')->first();
            $eid = (int)($first['id'] ?? 1);
            $equip = $first;
        }

        $affix = Redis::getJson("equip_refine:{$uid}:{$eid}") ?: [
            'level' => 1,
            'stat_name' => '暴击破甲',
            'stat_value' => 20,
        ];

        return [
            'status' => 1,
            'eid' => $eid,
            'equip_name' => $equip['name'] ?? '装备',
            'current_refine' => $affix,
            'refine_cost' => 5000,
            'message' => 'ok',
        ];
    }

    /**
     * 执行精炼淬火: c=refine&m=refinning
     */
    public static function refinning(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $eid = (int)($ctx->input('id', $ctx->input('eid', 1)));

        $equip = DB::table('equipment')->where('uid', $uid)->where('id', $eid)->whereNull('deleted_at')->first();
        if ($equip === null) {
            return ['status' => -1, 'message' => '装备不存在'];
        }

        $costSilver = 5000;
        if (!MemberHandler::deductCurrency($uid, silver: $costSilver)) {
            return ['status' => -2, 'message' => "银两不足，精炼装备需要 {$costSilver} 银两"];
        }

        $affixList = [
            ['name' => '绝世破甲', 'val' => 25],
            ['name' => '不动金刚抗暴', 'val' => 20],
            ['name' => '百步穿杨精准', 'val' => 15],
            ['name' => '真武吸血灵光', 'val' => 18],
        ];

        $rand = $affixList[array_rand($affixList)];

        $newAffix = [
            'level' => mt_rand(1, 5),
            'stat_name' => $rand['name'],
            'stat_value' => $rand['val'],
            'time' => time(),
        ];
        Redis::setJson("equip_refine:{$uid}:{$eid}", $newAffix, 86400 * 30);

        return [
            'status' => 1,
            'eid' => $eid,
            'refine' => $newAffix,
            'message' => "精炼淬火成功！为【{$equip['name']}】附加【{$rand['name']} +{$rand['val']}】词条神力！",
        ];
    }
}
