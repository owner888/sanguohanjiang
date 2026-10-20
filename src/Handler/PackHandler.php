<?php

declare(strict_types=1);

namespace Hanjiang\Handler;

use Hanjiang\Context;
use Hanjiang\Database\DB;
use Hanjiang\Database\Redis;

final class PackHandler
{
    /**
     * 背包列表: c=pack&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->first();
        $items = DB::table('equipment')->where('uid', $uid)->get();

        if (empty($items)) {
            $now = time();
            DB::table('equipment')->insert([
                'uid' => $uid,
                'equipid' => 101,
                'name' => '青铜剑',
                'type' => 1,
                'quality' => 1,
                'level' => 1,
                'generalid' => 0,
                'number' => 1,
                'saleprice' => 50,
                'created_at' => $now,
            ]);

            DB::table('equipment')->insert([
                'uid' => $uid,
                'equipid' => 201,
                'name' => '青铜甲',
                'type' => 2,
                'quality' => 1,
                'level' => 1,
                'generalid' => 0,
                'number' => 1,
                'saleprice' => 50,
                'created_at' => $now,
            ]);

            $items = DB::table('equipment')->where('uid', $uid)->get();
        }

        $list = [];
        $idx = 1;
        foreach ($items as $it) {
            $bonus = GeneralHandler::calculateEquipBonus($it);
            $list[$idx] = [
                'id' => (int)$it['id'],
                'equipid' => (int)$it['equipid'],
                'name' => $it['name'],
                'type' => (int)$it['type'],
                'level' => (int)$it['level'],
                'quality' => (int)$it['quality'],
                'saleprice' => (int)$it['saleprice'],
                'number' => (int)$it['number'],
                'gid' => (int)$it['generalid'],
                'is_equipped' => (int)$it['generalid'] > 0 ? 1 : 0,
                'bonus' => $bonus,
            ];
            $idx++;
        }

        return [
            'status' => 1,
            'maxnum' => (int)($member['bagnum'] ?? 20),
            'list' => $list,
            'message' => 'ok',
        ];
    }

    /**
     * 使用物品: c=pack&m=use_item
     */
    public static function use_item(Context $ctx): array
    {
        return [
            'status' => 1,
            'message' => '使用成功',
        ];
    }

    /**
     * 出售物品: c=pack&m=sale
     */
    public static function sale(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $eid = (int)($ctx->input('id', $ctx->input('eid', 0)));

        if ($eid <= 0) {
            return [
                'status' => 0,
                'message' => '请选择需要出售的装备',
            ];
        }

        $item = DB::table('equipment')->where('uid', $uid)->where('id', $eid)->first();
        if ($item === null) {
            return [
                'status' => -1,
                'message' => '物品不存在',
            ];
        }

        if ((int)$item['generalid'] > 0) {
            return [
                'status' => -2,
                'message' => '已穿戴装备无法出售，请先卸下',
            ];
        }

        $member = DB::table('member')->where('uid', $uid)->first();
        $salePrice = max(10, (int)$item['saleprice']) * max(1, (int)$item['level']);
        $newSilver = (int)$member['silver'] + $salePrice;

        // 增加玩家银两并删除装备
        DB::table('member')->where('uid', $uid)->update(['silver' => $newSilver]);
        DB::table('equipment')->where('id', $eid)->delete();

        Redis::del('player:' . $uid);

        return [
            'status' => 1,
            'message' => '出售成功',
            'sale_price' => $salePrice,
            'silver' => $newSilver,
        ];
    }
}
