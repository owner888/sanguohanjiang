<?php

declare(strict_types=1);

namespace Hanjiang\Handler;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

final class TalentHandler
{
    public const array TALENTS = [
        1 => ['id' => 1, 'name' => '破军猛攻天赋', 'type' => 'attack', 'val' => 50, 'desc' => '提升武将普通物理攻击力'],
        2 => ['id' => 2, 'name' => '金刚铁壁天赋', 'type' => 'defense', 'val' => 35, 'desc' => '提升武将物理与策略防御力'],
        3 => ['id' => 3, 'name' => '百战嗜血天赋', 'type' => 'hp', 'val' => 300, 'desc' => '大幅提升武将生命上限'],
        4 => ['id' => 4, 'name' => '无双会心天赋', 'type' => 'crit', 'val' => 2, 'desc' => '提升武将暴击概率'],
    ];

    /**
     * 获取武将天赋数据
     *
     * @return array<int, int> talent_id => level
     */
    public static function getGeneralTalents(int $uid, int $gid): array
    {
        $cached = Redis::getJson("general_talents:{$uid}:{$gid}");
        if (is_array($cached)) {
            return $cached;
        }

        $init = [
            1 => 1,
            2 => 0,
            3 => 0,
            4 => 0,
        ];
        Redis::setJson("general_talents:{$uid}:{$gid}", $init, 86400 * 30);
        return $init;
    }

    /**
     * 天赋主界面: c=talent&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $gid = (int)($ctx->input('gid', 1));

        $general = DB::table('general')->where('uid', $uid)->where('id', $gid)->whereNull('deleted_at')->first();
        if ($general === null) {
            $first = DB::table('general')->where('uid', $uid)->whereNull('deleted_at')->first();
            $gid = (int)($first['id'] ?? 1);
            $general = $first;
        }

        $talents = self::getGeneralTalents($uid, $gid);

        $list = [];
        foreach (self::TALENTS as $id => $t) {
            $lvl = (int)($talents[$id] ?? 0);
            $list[$id] = array_merge($t, [
                'level' => $lvl,
                'cost_silver' => max(5000, $lvl * 5000),
                'effect' => ($lvl * $t['val']) . ($t['type'] === 'crit' ? '%' : '点'),
            ]);
        }

        return [
            'status' => 1,
            'gid' => $gid,
            'general_name' => $general['name'] ?? '武将',
            'list' => array_values($list),
            'message' => 'ok',
        ];
    }

    /**
     * 升级武将天赋: c=talent&m=levelup
     */
    public static function levelup(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $gid = (int)($ctx->input('gid', 1));
        $talentId = (int)($ctx->input('id', $ctx->input('talent_id', 1)));

        if (!isset(self::TALENTS[$talentId])) {
            return ['status' => 0, 'message' => '天赋类型不存在'];
        }

        $general = DB::table('general')->where('uid', $uid)->where('id', $gid)->whereNull('deleted_at')->first();
        if ($general === null) {
            return ['status' => -1, 'message' => '武将不存在'];
        }

        $talents = self::getGeneralTalents($uid, $gid);
        $curLvl = (int)($talents[$talentId] ?? 0);
        $costSilver = max(5000, $curLvl * 5000);

        if (!MemberHandler::deductCurrency($uid, silver: $costSilver)) {
            return ['status' => -2, 'message' => "银两不足，参悟天赋需要 {$costSilver} 银两"];
        }

        $newLvl = $curLvl + 1;
        $talents[$talentId] = $newLvl;
        Redis::setJson("general_talents:{$uid}:{$gid}", $talents, 86400 * 30);

        $cfg = self::TALENTS[$talentId];

        return [
            'status' => 1,
            'gid' => $gid,
            'talent_id' => $talentId,
            'talent_name' => $cfg['name'],
            'level' => $newLvl,
            'message' => "顿悟神力！【{$general['name']}】之【{$cfg['name']}】成功突破至 {$newLvl} 级！",
        ];
    }
}
