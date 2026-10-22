<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Equip;

use Hanjiang\Handler\City\MemberHandler;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

final class GemHandler
{
    public const array GEM_TYPES = [
        1 => ['type' => 1, 'name' => '赤炎红宝石', 'attr' => 'attack', 'val_per_lvl' => 30],
        2 => ['type' => 2, 'name' => '沧海蓝宝石', 'attr' => 'defense', 'val_per_lvl' => 20],
        3 => ['type' => 3, 'name' => '碧涛绿宝石', 'attr' => 'hp', 'val_per_lvl' => 150],
        4 => ['type' => 4, 'name' => '雷霆黄宝石', 'attr' => 'attack', 'val_per_lvl' => 25],
    ];

    /**
     * 获取玩家背包拥有的宝石
     *
     * @return array<int, array{id: int, type: int, level: int, count: int}>
     */
    public static function getUserGems(int $uid): array
    {
        $cached = Redis::getJson('user_gems:' . $uid);
        if (is_array($cached)) {
            return $cached;
        }

        $init = [
            1 => ['id' => 1, 'type' => 1, 'level' => 1, 'count' => 6], // 6颗 1级红宝石
            2 => ['id' => 2, 'type' => 2, 'level' => 1, 'count' => 4], // 4颗 1级蓝宝石
            3 => ['id' => 3, 'type' => 3, 'level' => 1, 'count' => 5], // 5颗 1级绿宝石
        ];
        Redis::setJson('user_gems:' . $uid, $init, 86400 * 30);
        return $init;
    }

    /**
     * 宝石主界面: c=gem&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $gems = self::getUserGems($uid);

        $list = [];
        foreach ($gems as $id => $g) {
            $cfg = self::GEM_TYPES[$g['type']] ?? self::GEM_TYPES[1];
            $list[$id] = [
                'id' => (int)$g['id'],
                'type' => (int)$g['type'],
                'name' => $g['level'] . '级' . $cfg['name'],
                'level' => (int)$g['level'],
                'count' => (int)$g['count'],
                'bonus' => $cfg['val_per_lvl'] * $g['level'],
            ];
        }

        return [
            'status' => 1,
            'list' => $list,
            'message' => 'ok',
        ];
    }

    /**
     * 镶嵌宝石: c=gem&m=adron
     */
    public static function adron(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $gemId = (int)($ctx->input('gem_id', $ctx->input('id', 1)));
        $equipId = (int)($ctx->input('equip_id', $ctx->input('eid', 1)));

        $equip = DB::table('equipment')->where('uid', $uid)->where('id', $equipId)->first();
        if ($equip === null) {
            return ['status' => -1, 'message' => '装备不存在'];
        }

        $gems = self::getUserGems($uid);
        if (!isset($gems[$gemId]) || (int)$gems[$gemId]['count'] <= 0) {
            return ['status' => -2, 'message' => '宝石库存不足'];
        }

        // 扣减一颗宝石
        $gems[$gemId]['count'] = (int)$gems[$gemId]['count'] - 1;
        Redis::setJson('user_gems:' . $uid, $gems, 86400 * 30);

        // 记录已镶嵌信息
        $socketKey = "equip_gems:{$uid}:{$equipId}";
        $sockets = Redis::getJson($socketKey) ?: [];
        $sockets[] = [
            'type' => $gems[$gemId]['type'],
            'level' => $gems[$gemId]['level'],
        ];
        Redis::setJson($socketKey, $sockets, 86400 * 30);

        $cfg = self::GEM_TYPES[$gems[$gemId]['type']];

        return [
            'status' => 1,
            'equip_id' => $equipId,
            'gem_name' => $gems[$gemId]['level'] . '级' . $cfg['name'],
            'message' => "成功将【{$gems[$gemId]['level']}级{$cfg['name']}】镶嵌至【{$equip['name']}】！",
        ];
    }

    /**
     * 拆卸宝石: c=gem&m=pick_off
     */
    public static function pick_off(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $equipId = (int)($ctx->input('equip_id', $ctx->input('eid', 1)));

        $socketKey = "equip_gems:{$uid}:{$equipId}";
        $sockets = Redis::getJson($socketKey);

        if (!is_array($sockets) || empty($sockets)) {
            return ['status' => 0, 'message' => '该装备上未镶嵌任何宝石'];
        }

        // 拆卸一颗宝石回背包
        $removed = array_pop($sockets);
        Redis::setJson($socketKey, $sockets, 86400 * 30);

        $gems = self::getUserGems($uid);
        $found = false;
        foreach ($gems as $k => $g) {
            if ($g['type'] === $removed['type'] && $g['level'] === $removed['level']) {
                $gems[$k]['count'] = (int)$gems[$k]['count'] + 1;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $newId = count($gems) + 1;
            $gems[$newId] = [
                'id' => $newId,
                'type' => $removed['type'],
                'level' => $removed['level'],
                'count' => 1,
            ];
        }
        Redis::setJson('user_gems:' . $uid, $gems, 86400 * 30);

        return [
            'status' => 1,
            'message' => '宝石已安全拆卸并收纳回背包',
        ];
    }

    /**
     * 4合1宝石合成: c=gem&m=merge_gem
     */
    public static function merge_gem(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $gemId = (int)($ctx->input('gem_id', $ctx->input('id', 1)));

        $gems = self::getUserGems($uid);
        if (!isset($gems[$gemId]) || (int)$gems[$gemId]['count'] < 4) {
            return ['status' => -1, 'message' => '合成需要至少 4 颗同类型同等级宝石'];
        }

        $type = (int)$gems[$gemId]['type'];
        $curLevel = (int)$gems[$gemId]['level'];
        $newLevel = $curLevel + 1;

        // 扣除 4 颗旧宝石
        $gems[$gemId]['count'] = (int)$gems[$gemId]['count'] - 4;

        // 增加 1 颗高阶宝石
        $found = false;
        foreach ($gems as $k => $g) {
            if ($g['type'] === $type && $g['level'] === $newLevel) {
                $gems[$k]['count'] = (int)$gems[$k]['count'] + 1;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $newId = count($gems) + 1;
            $gems[$newId] = [
                'id' => $newId,
                'type' => $type,
                'level' => $newLevel,
                'count' => 1,
            ];
        }

        Redis::setJson('user_gems:' . $uid, $gems, 86400 * 30);

        $cfg = self::GEM_TYPES[$type];

        return [
            'status' => 1,
            'new_gem' => "{$newLevel}级{$cfg['name']}",
            'level' => $newLevel,
            'message' => "神工熔炼！成功将 4 颗 {$curLevel} 级宝石融合成 1 颗【{$newLevel}级{$cfg['name']}】！",
        ];
    }
}
