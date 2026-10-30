<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Campaign;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\General\GeneralHandler;

use OmniPHP\Http\Context;
use OmniPHP\Database\DB;
use OmniPHP\Cache\Redis;

final class MatrixHandler
{
    /**
     * 经典阵法配置字典 (1~8为经典八卦阵法，9~11为VIP尊享高级阵法)
     *
     * @var array<int, array<string, mixed>>
     */
    public const array MATRIX_CONFIGS = [
        1 => ['id' => 1, 'name' => '鱼鳞阵', 'type' => 1, 'vip' => 0, 'desc' => '全军普通攻击力提升', 'base_rate' => 5, 'growth_rate' => 1],
        2 => ['id' => 2, 'name' => '锋矢阵', 'type' => 2, 'vip' => 0, 'desc' => '全军战法攻击力提升', 'base_rate' => 5, 'growth_rate' => 1],
        3 => ['id' => 3, 'name' => '鹤翼阵', 'type' => 3, 'vip' => 0, 'desc' => '全军普通防御力提升', 'base_rate' => 5, 'growth_rate' => 1],
        4 => ['id' => 4, 'name' => '偃月阵', 'type' => 4, 'vip' => 0, 'desc' => '全军暴击几率提升', 'base_rate' => 3, 'growth_rate' => 0.5],
        5 => ['id' => 5, 'name' => '方圆阵', 'type' => 5, 'vip' => 0, 'desc' => '全军策略攻击力提升', 'base_rate' => 5, 'growth_rate' => 1],
        6 => ['id' => 6, 'name' => '雁形阵', 'type' => 6, 'vip' => 0, 'desc' => '全军策略防御力提升', 'base_rate' => 5, 'growth_rate' => 1],
        7 => ['id' => 7, 'name' => '长蛇阵', 'type' => 7, 'vip' => 0, 'desc' => '全军闪避几率提升', 'base_rate' => 3, 'growth_rate' => 0.5],
        8 => ['id' => 8, 'name' => '乱剑阵', 'type' => 8, 'vip' => 0, 'desc' => '全军命中几率提升', 'base_rate' => 3, 'growth_rate' => 0.5],
        9 => ['id' => 9, 'name' => '壁垒阵', 'type' => 3, 'vip' => 7, 'desc' => '全军双防御力提升', 'base_rate' => 10, 'growth_rate' => 2],
        10 => ['id' => 10, 'name' => '冲锋阵', 'type' => 1, 'vip' => 10, 'desc' => '全军双攻击力提升', 'base_rate' => 15, 'growth_rate' => 2],
        11 => ['id' => 11, 'name' => '至尊阵', 'type' => 1, 'vip' => 13, 'desc' => '全军双攻双防全面极致提升', 'base_rate' => 20, 'growth_rate' => 3],
    ];

    /**
     * 获取或初始化用户的阵法数据
     *
     * @return array<string, mixed>
     */
    public static function getOrInitMatrix(int $uid): array
    {
        $matrix = DB::table('matrix')->where('uid', $uid)->first();
        if ($matrix !== null) {
            return $matrix;
        }

        // 默认将主公武将放置在 2 号位 (前排中)
        $mainGeneral = DB::table('general')->where('uid', $uid)->first();
        $mainGid = (int)($mainGeneral['id'] ?? 1);

        DB::table('matrix')->insert([
            'uid' => $uid,
            'mid' => 1,
            'level' => 1,
            'position1' => 0,
            'position2' => $mainGid,
            'position3' => 0,
            'position4' => 0,
            'position5' => 0,
            'position6' => 0,
            'position7' => 0,
            'position8' => 0,
            'position9' => 0,
        ]);

        if ($mainGeneral !== null) {
            DB::table('general')->where('id', $mainGid)->update(['pos' => 2]);
        }

        return DB::table('matrix')->where('uid', $uid)->first() ?? [];
    }

    /**
     * 阵法主界面: c=matrix&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $matrix = self::getOrInitMatrix($uid);
        $nowMid = (int)($matrix['mid'] ?? 1);
        $matrixLevel = max(1, (int)($matrix['level'] ?? 1));

        // 当前九宫格战位站位 (1~9号位)
        $positions = [];
        $onBattleGids = [];
        for ($i = 1; $i <= 9; $i++) {
            $gid = (int)($matrix['position' . $i] ?? 0);
            $positions[$i] = $gid;
            if ($gid > 0) {
                $onBattleGids[] = $gid;
            }
        }

        // 获取用户所有武将并标注其出战状态 (包含 image, quality 等展示字段)
        $member = DB::table('member')->where('uid', $uid)->first();
        $memberGender = (int)($member['gender'] ?? 1);

        $generals = DB::table('general')->where('uid', $uid)->whereNull('deleted_at')->get();
        $generalList = [];
        $gIdx = 1;
        foreach ($generals as $g) {
            $gid = (int)$g['id'];
            $pos = (int)($g['pos'] ?? 0);
            $name = (string)$g['name'];
            $cfg = GeneralHandler::HERO_CONFIGS[$name] ?? null;

            $image = (int)($g['image'] ?? 0);
            if ($image <= 0) {
                $image = GeneralHandler::resolveHeroImage($name, $memberGender, (int)($g['generalid'] ?? 1));
            }

            $generalList[(string)$gIdx] = [
                'id' => $gid,
                'generalid' => (int)($g['generalid'] ?? 1),
                'name' => $name,
                'quality' => (int)($g['quality'] ?? $cfg['quality'] ?? 3),
                'image' => $image,
                'level' => (int)($g['level'] ?? 1),
                'pos' => $pos,
                'goout' => ($pos > 0 && in_array($gid, $onBattleGids, true)) ? 1 : 0,
            ];
            $gIdx++;
        }

        $list = [];
        foreach (self::MATRIX_CONFIGS as $mid => $cfg) {
            $percent = $cfg['base_rate'] + ($matrixLevel - 1) * $cfg['growth_rate'];
            $additionObj = [
                'type' => (int)$cfg['type'],
                'percent' => $percent,
                'next' => $matrixLevel * 1000,
            ];

            $item = [
                'id' => $mid,
                'mid' => $mid,
                'sort' => $mid,
                'name' => $cfg['name'],
                'level' => $matrixLevel,
                'type' => (int)$cfg['type'],
                'vip' => (int)$cfg['vip'],
                'desc' => $cfg['desc'],
                'addition' => $additionObj,
                'open' => ($mid === $nowMid) ? 1 : 0,
                'position' => $positions,
                'position1' => $positions[1] ?? 0,
                'position2' => $positions[2] ?? 0,
                'position3' => $positions[3] ?? 0,
                'position4' => $positions[4] ?? 0,
                'position5' => $positions[5] ?? 0,
                'position6' => $positions[6] ?? 0,
                'position7' => $positions[7] ?? 0,
                'position8' => $positions[8] ?? 0,
                'position9' => $positions[9] ?? 0,
            ];

            $list[$mid] = $item;
        }

        return [
            'status' => 1,
            'now_mid' => $nowMid,
            'list' => $list,
            'matrix' => $list,
            'generals' => $generalList,
            'general' => $generalList,
            'message' => 'ok',
        ];
    }

    /**
     * 调整阵法站位: c=matrix&m=update_matrix
     */
    public static function update_matrix(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $matrix = self::getOrInitMatrix($uid);
        $mid = (int)($ctx->input('mid', $matrix['mid'] ?? 1));

        // 收集该玩家名下所有合法武将 ID
        $myGenerals = DB::table('general')->where('uid', $uid)->get();
        $validGidMap = [];
        foreach ($myGenerals as $g) {
            $validGidMap[(int)$g['id']] = $g;
        }

        $newPositions = [];
        $rawList = (string)$ctx->input('list', '');

        if ($rawList !== '') {
            // 模式 A: 逗号分隔的 9 个战位数值 (如 "0,10,0,1,0,0,0,0,0")
            $parts = explode(',', $rawList);
            if (count($parts) !== 9) {
                return [
                    'status' => -1,
                    'message' => '阵法战位数据不完整，需传 9 个战位',
                ];
            }

            for ($i = 1; $i <= 9; $i++) {
                $newPositions[$i] = (int)trim($parts[$i - 1]);
            }
        } else {
            // 模式 B: 指定单个位置与武将 id
            $pos = (int)($ctx->input('position', $ctx->input('pos', 2)));
            $gid = (int)($ctx->input('gid', 0));

            if ($pos < 1 || $pos > 9) {
                return [
                    'status' => 0,
                    'message' => '战位编号必须在 1 到 9 之间',
                ];
            }

            for ($i = 1; $i <= 9; $i++) {
                $newPositions[$i] = (int)($matrix['position' . $i] ?? 0);
            }

            // 若要上阵的 gid 在其他战位出现过，先将其从旧战位移除
            if ($gid > 0) {
                for ($i = 1; $i <= 9; $i++) {
                    if ($newPositions[$i] === $gid) {
                        $newPositions[$i] = 0;
                    }
                }
            }

            $newPositions[$pos] = $gid;
        }

        // 校验上阵将领合法性与唯一性
        $activeGids = [];
        foreach ($newPositions as $pos => $gid) {
            if ($gid <= 0) {
                continue;
            }

            if (!isset($validGidMap[$gid])) {
                return [
                    'status' => -3,
                    'message' => "战位 {$pos} 上的武将不存在或不属于该玩家",
                ];
            }

            if (isset($activeGids[$gid])) {
                return [
                    'status' => -4,
                    'message' => '将领不唯一，同一武将不可同时出现在多个战位',
                ];
            }

            $activeGids[$gid] = $pos;
        }

        if (empty($activeGids)) {
            return [
                'status' => 0,
                'message' => '阵法中必须至少保留一名出战武将',
            ];
        }

        if (count($activeGids) > 5) {
            return [
                'status' => -2,
                'message' => '上阵人数超过上限（最多上阵 5 人）',
            ];
        }

        // 1. 更新 matrix 表中的战位配置
        $matrixUpdate = ['mid' => $mid];
        for ($i = 1; $i <= 9; $i++) {
            $matrixUpdate['position' . $i] = $newPositions[$i];
        }
        DB::table('matrix')->where('uid', $uid)->update($matrixUpdate);

        // 2. 同步更新 general 表中武将的 pos 战位与出征状态
        foreach ($myGenerals as $g) {
            $gid = (int)$g['id'];
            $targetPos = $activeGids[$gid] ?? 0;
            if ((int)$g['pos'] !== $targetPos) {
                DB::table('general')->where('id', $gid)->update([
                    'pos' => $targetPos,
                    'matrix_id' => $mid,
                ]);
            }
        }

        Redis::del('player:' . $uid);

        return [
            'status' => 1,
            'message' => '布阵成功',
            'mid' => $mid,
            'positions' => $newPositions,
            'active_count' => count($activeGids),
        ];
    }

    /**
     * 获取阵法信息: c=matrix&m=get_info
     */
    public static function get_info(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $mid = (int)($ctx->input('mid', 1));
        $matrix = self::getOrInitMatrix($uid);
        $level = max(1, (int)($matrix['level'] ?? 1));

        $cfg = self::MATRIX_CONFIGS[$mid] ?? self::MATRIX_CONFIGS[1];
        $addition = 1.0 + $cfg['base_rate'] + ($level - 1) * $cfg['growth_rate'];

        return [
            'status' => 1,
            'info' => [
                'mid' => $mid,
                'name' => $cfg['name'],
                'level' => $level,
                'effect' => "{$cfg['desc']} " . round($addition * 100 - 100, 1) . '%',
                'addition' => round($addition, 4),
            ],
        ];
    }

    /**
     * 启用指定阵法: c=matrix&m=use_matrix
     */
    public static function use_matrix(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $mid = (int)($ctx->input('mid', 1));

        if (!isset(self::MATRIX_CONFIGS[$mid])) {
            return [
                'status' => 0,
                'message' => '无效的阵法编号',
            ];
        }

        self::getOrInitMatrix($uid);
        DB::table('matrix')->where('uid', $uid)->update([
            'mid' => $mid,
        ]);

        // 同步更新已出战武将的 matrix_id
        DB::table('general')->where('uid', $uid)->where('pos', '>', 0)->update([
            'matrix_id' => $mid,
        ]);

        Redis::del('player:' . $uid);

        return [
            'status' => 1,
            'now_mid' => $mid,
            'message' => '阵法切换成功',
        ];
    }

    /**
     * 升级阵法: c=matrix&m=levelup
     */
    public static function levelup(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $matrix = self::getOrInitMatrix($uid);
        $curLevel = max(1, (int)($matrix['level'] ?? 1));
        $costSilver = $curLevel * 500;

        $member = DB::table('member')->where('uid', $uid)->first();
        if ((int)$member['silver'] < $costSilver) {
            return [
                'status' => 3,
                'message' => '银两不足，阵法升级需要 ' . $costSilver . ' 银两',
            ];
        }

        $remainSilver = (int)$member['silver'] - $costSilver;
        $newLevel = $curLevel + 1;

        DB::table('member')->where('uid', $uid)->update(['silver' => $remainSilver]);
        DB::table('matrix')->where('uid', $uid)->update(['level' => $newLevel]);

        Redis::del('player:' . $uid);

        $mid = (int)($ctx->input('mid', $matrix['mid'] ?? 1));
        $cfg = self::MATRIX_CONFIGS[$mid] ?? self::MATRIX_CONFIGS[1];
        $percent = $cfg['base_rate'] + ($newLevel - 1) * $cfg['growth_rate'];
        $nextCost = ($newLevel + 1) * 500;

        return [
            'status' => 1,
            'message' => '阵法升级成功',
            'level' => $newLevel,
            'cost_silver' => $costSilver,
            'silver' => $remainSilver,
            'info' => [
                'level' => $newLevel,
                'type' => (int)$cfg['type'],
                'percent' => round($percent * 100, 1),
                'next' => $nextCost,
                'position' => 0,
                'gold' => (int)($member['gold'] ?? 0),
            ],
        ];
    }
}
