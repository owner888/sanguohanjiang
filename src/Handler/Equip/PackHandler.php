<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Equip;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\General\GeneralHandler;

use OmniPHP\Http\Context;
use OmniPHP\Database\DB;
use OmniPHP\Cache\Redis;

final class PackHandler
{
    /**
     * 背包列表: c=pack&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        $items = DB::table('equipment')->where('uid', $uid)->whereNull('deleted_at')->get();

        if (empty($items)) {
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
            ]);

            $items = DB::table('equipment')->where('uid', $uid)->whereNull('deleted_at')->get();
        }

        // 排序规则：未穿戴装备优先，品阶降序，等级降序
        usort($items, static function (array $a, array $b): int {
            $aEquipped = ((int)($a['generalid'] ?? 0) > 0) ? 1 : 0;
            $bEquipped = ((int)($b['generalid'] ?? 0) > 0) ? 1 : 0;
            if ($aEquipped !== $bEquipped) {
                return $aEquipped <=> $bEquipped; // 0 (未穿戴) 优先排前
            }
            $aQuality = (int)($a['quality'] ?? 1);
            $bQuality = (int)($b['quality'] ?? 1);
            if ($aQuality !== $bQuality) {
                return $bQuality <=> $aQuality;
            }
            return ((int)($b['level'] ?? 1)) <=> ((int)($a['level'] ?? 1));
        });

        $list = [];
        foreach ($items as $it) {
            $bonus = GeneralHandler::calculateEquipBonus($it);
            $powerup = $bonus['attack'] ?: ($bonus['defense'] ?: $bonus['hp']);
            $list[] = [
                'id' => (int)$it['id'],
                'equipid' => (int)$it['equipid'],
                'name' => $it['name'],
                'type' => (int)$it['type'],
                'level' => (int)$it['level'],
                'quality' => (int)$it['quality'],
                'needlevel' => 1,
                'powerup' => $powerup,
                'description' => "品质 {$it['quality']} 阶装备，增强战力 {$powerup}",
                'saleprice' => (int)$it['saleprice'],
                'number' => (int)$it['number'],
                'full_num' => (int)$it['number'],
                'gid' => (int)$it['generalid'],
                'is_equipped' => (int)$it['generalid'] > 0 ? 1 : 0,
                'bonus' => $bonus,
            ];
        }

        return [
            'status' => 1,
            'maxnum' => (int)($member['bagnum'] ?? 20),
            'list' => array_values($list),
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

        $item = DB::table('equipment')
            ->where('uid', $uid)
            ->where('id', $eid)
            ->whereNull('deleted_at')
            ->first();

        if ($item === null) {
            return [
                'status' => -1,
                'message' => '物品不存在',
            ];
        }

        if ((int)$item['generalid'] > 0) {
            return [
                'status' => -2,
                'message' => '已穿戴装备无法出售，请先在将领面板卸下',
            ];
        }

        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        $salePrice = max(50, (int)$item['saleprice']) * max(1, (int)$item['level']);
        $newSilver = (int)($member['silver'] ?? 0) + $salePrice;

        // 增加玩家银两并软删除装备 (deleted_at 记录删除时间)
        DB::table('member')->where('uid', $uid)->update(['silver' => $newSilver]);
        DB::table('equipment')->where('id', $eid)->update(['deleted_at' => date('Y-m-d H:i:s')]);

        Redis::del('player:' . $uid);

        return [
            'status' => 1,
            'message' => '出售成功',
            'sale_price' => $salePrice,
            'silver' => $salePrice,
            'all_silver' => $newSilver,
        ];
    }
}
