<?php

declare(strict_types=1);

namespace Hanjiang\Handler;

use Hanjiang\Context;
use Hanjiang\Database\DB;

final class GeneralHandler
{
    /**
     * 将领列表: c=general&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->first();
        $nickname = $member['nickname'] ?? '天命主公';

        // 查询已有武将
        $generals = DB::table('general')->where('uid', $uid)->get();
        if (empty($generals)) {
            // 初始化初始主公将领
            $genId = DB::table('general')->insert([
                'uid' => $uid,
                'generalid' => 1,
                'name' => $nickname,
                'level' => 1,
                'exp' => 0,
                'pos' => 2,
                'matrix_id' => 1,
                'status' => 1,
                'created_at' => time(),
            ]);
            $generals = DB::table('general')->where('uid', $uid)->get();
        }

        $list = [];
        $idx = 1;
        foreach ($generals as $g) {
            $list[$idx] = [
                'id' => (int)$g['id'],
                'uid' => $uid,
                'generalid' => (int)$g['generalid'],
                'name' => $g['name'],
                'level' => (int)$g['level'],
                'exp' => (int)$g['exp'],
                'levelupexp' => 100,
                'wuli' => 85,
                'zhili' => 70,
                'tili' => 90,
                'hp' => 1500,
                'allblood' => 1500,
                'skillid' => 1,
                'talentid' => 1,
                'pos' => (int)$g['pos'],
                'goout' => 1,
                'equip' => [],
            ];
            $idx++;
        }

        return [
            'status' => 1,
            'list' => $list,
            'message' => 'ok',
        ];
    }

    /**
     * 将领详情: c=general&m=get_info
     */
    public static function get_info(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $gid = (int)($ctx->input('gid', 1));
        $general = DB::table('general')->where('uid', $uid)->where('id', $gid)->first();

        return [
            'status' => 1,
            'info' => [
                'id' => $gid,
                'name' => $general['name'] ?? '主将',
                'level' => (int)($general['level'] ?? 1),
                'hp' => 1500,
                'attack' => 320,
                'defense' => 120,
                'speed' => 150,
            ],
        ];
    }

    /**
     * 穿装备: c=general&m=equip
     */
    public static function equip(Context $ctx): array
    {
        return [
            'status' => 1,
            'message' => '穿戴成功',
        ];
    }

    /**
     * 卸装备: c=general&m=unequip
     */
    public static function unequip(Context $ctx): array
    {
        return [
            'status' => 1,
            'message' => '卸下成功',
        ];
    }
}
