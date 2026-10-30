<?php

declare(strict_types=1);

namespace Hanjiang\Handler\General;

use Hanjiang\Handler\City\MemberHandler;

use Hanjiang\Model\General;
use Hanjiang\Model\Equipment;
use OmniPHP\Http\Context;
use OmniPHP\Database\DB;

final class GeneralHandler
{
    /**
     * 原版经典名将原型主数据 (精准对齐原版美术资产与战斗数值)
     *
     * @var array<string, array<string, mixed>>
     */
    public const array HERO_CONFIGS = [
        '诸葛亮' => [
            'name' => '诸葛亮',
            'image' => 76,  // 经典原版：八卦青芒、纶巾羽扇卧龙孔明
            'type' => 2,    // 谋士(法术策略)
            'quality' => 5, // 橙色神将
            'wuli' => 55,
            'zhili' => 100, // 智绝天下
            'tili' => 88,
            'talent' => ['id' => 4, 'name' => '智囊', 'blood' => 1, 'description' => '策略攻击防御综合提升15%'],
            'skill' => ['id' => 5, 'name' => '卧龙天火', 'description' => '借东风降下神火天降，对敌军全阵型进行法术覆盖'],
        ],
        '关羽' => [
            'name' => '关羽',
            'image' => 58,  // 经典原版：绿袍长髯公、卧蚕眉丹凤眼
            'type' => 1,    // 战法猛将
            'quality' => 5,
            'wuli' => 98,
            'zhili' => 82,
            'tili' => 95,
            'talent' => ['id' => 5, 'name' => '武帝', 'blood' => 1, 'description' => '受到直接物理伤害降低10%，反击伤害提升15%'],
            'skill' => ['id' => 4, 'name' => '春秋十字斩', 'description' => '神刀劈砍降下雷霆万钧，重创敌将并提升自身韧性'],
        ],
        '张飞' => [
            'name' => '张飞',
            'image' => 54,  // 经典原版：红巾黑须、怒目豹头张翼德
            'type' => 1,    // 物理猛将
            'quality' => 4,
            'wuli' => 99,
            'zhili' => 45,
            'tili' => 96,
            'talent' => ['id' => 2, 'name' => '勇士无双', 'blood' => 1, 'description' => '普通物理攻击伤害提升15%'],
            'skill' => ['id' => 2, 'name' => '万夫莫敌', 'description' => '咆哮怒吼横扫千军，对横排敌方造成160%群体震慑'],
        ],
        '赵云' => [
            'name' => '赵云',
            'image' => 25,  // 经典原版：常山赵子龙白银战盔红缨
            'type' => 1,    // 物理神将
            'quality' => 5,
            'wuli' => 96,
            'zhili' => 78,
            'tili' => 92,
            'talent' => ['id' => 3, 'name' => '一身是胆', 'blood' => 1, 'description' => '出手先攻速度提升25点，必定暴击'],
            'skill' => ['id' => 3, 'name' => '龙胆穿心', 'description' => '白马银枪直刺中路，必定暴击并破除敌军格挡'],
        ],
    ];

    /**
     * 解析武将正统头像 ID
     */
    public static function resolveHeroImage(string $name, int $gender = 1, int $fallback = 1): int
    {
        if (isset(self::HERO_CONFIGS[$name])) {
            return (int)self::HERO_CONFIGS[$name]['image'];
        }
        if ($fallback === 1) {
            return ($gender === 2) ? 2 : 1;
        }
        return $fallback;
    }

    /**
     * 计算单件装备提供的属性加成 (委托至 Equipment 领域模型)
     *
     * @param array<string, mixed> $equip
     * @return array{attack: int, defense: int, hp: int}
     */
    public static function calculateEquipBonus(array $equip): array
    {
        return (new Equipment($equip))->calculateBonus();
    }

    /**
     * 计算武将的综合战斗属性 (委托至 General 领域模型)
     *
     * @param array<string, mixed> $general
     * @param list<array<string, mixed>> $equippedItems
     * @return array<string, mixed>
     */
    public static function calculateGeneralStats(array $general, array $equippedItems = []): array
    {
        return (new General($general))->calculateStats($equippedItems);
    }

    /**
     * 原版经典武将升级经验公式 (80 * 等级^2)
     */
    public static function getLevelUpExp(int $level): int
    {
        return General::getLevelUpExp($level);
    }

    /**
     * 将领列表: c=general&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->first();
        $nickname = $member['nickname'] ?? '天命主公';

        // 查询已有武将
        $generals = DB::table('general')->where('uid', $uid)->whereNull('deleted_at')->get();
        if (empty($generals)) {
            // 初始化初始主公将领
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

        // 查询该玩家所有已穿戴装备 (过滤软删除)
        $allEquips = DB::table('equipment')->where('uid', $uid)->whereNull('deleted_at')->get();
        if (empty($allEquips)) {
            // 初始化新手入门装备放入背包 (generalid=0)
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
            $allEquips = DB::table('equipment')->where('uid', $uid)->whereNull('deleted_at')->get();
        }
        $equipsByGeneral = [];
        foreach ($allEquips as $eq) {
            $gid = (int)$eq['generalid'];
            if ($gid > 0) {
                $equipsByGeneral[$gid][] = $eq;
            }
        }

        $list = [];
        $idx = 1;
        foreach ($generals as $g) {
            $gid = (int)$g['id'];
            $equipped = $equipsByGeneral[$gid] ?? [];
            $list[(string)$idx] = self::formatHeroData($g, $equipped, $uid);
            $idx++;
        }

        $availableEquipments = [];
        $eqIdx = 1;
        foreach ($allEquips as $eq) {
            $bonus = self::calculateEquipBonus($eq);
            $item = array_merge($eq, [
                'id' => (int)$eq['id'],
                'equipid' => (int)$eq['equipid'],
                'etype' => (int)$eq['type'],
                'name' => $eq['name'],
                'type' => (int)$eq['type'],
                'level' => (int)$eq['level'],
                'quality' => (int)$eq['quality'],
                'gid' => (int)$eq['generalid'],
                'needlevel' => 1,
                'available' => 1,
                'hammer' => 0,
                'hammer_quality' => 0,
                'powerup' => $bonus['attack'] ?: ($bonus['defense'] ?: $bonus['hp']),
                'bonus' => $bonus,
            ]);
            $availableEquipments[(string)$eqIdx] = $item;
            $eqIdx++;
        }

        return [
            'status' => 1,
            'list' => $list,
            'equipments' => $availableEquipments,
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
        $general = DB::table('general')->where('uid', $uid)->where('id', $gid)->whereNull('deleted_at')->first();

        if ($general === null) {
            // 若指定 gid 不存在，回退到该用户拥有的首位武将
            $general = DB::table('general')->where('uid', $uid)->whereNull('deleted_at')->first();
        }

        if ($general === null) {
            return [
                'status' => -1,
                'message' => '武将不存在',
            ];
        }

        $realGid = (int)$general['id'];
        $allEquips = DB::table('equipment')->where('uid', $uid)->whereNull('deleted_at')->get();
        $equipped = [];
        $unequipped = [];
        $eqIdx = 1;
        foreach ($allEquips as $eq) {
            if ((int)$eq['generalid'] === $realGid) {
                $equipped[] = $eq;
            } elseif ((int)$eq['generalid'] === 0) {
                $bonus = self::calculateEquipBonus($eq);
                $unequipped[(string)$eqIdx] = array_merge($eq, [
                    'id' => (int)$eq['id'],
                    'equipid' => (int)$eq['equipid'],
                    'etype' => (int)$eq['type'],
                    'name' => $eq['name'],
                    'type' => (int)$eq['type'],
                    'level' => (int)$eq['level'],
                    'quality' => (int)$eq['quality'],
                    'gid' => 0,
                    'needlevel' => 1,
                    'available' => 1,
                    'hammer' => 0,
                    'hammer_quality' => 0,
                    'powerup' => $bonus['attack'] ?: ($bonus['defense'] ?: $bonus['hp']),
                    'bonus' => $bonus,
                ]);
                $eqIdx++;
            }
        }

        $heroInfo = self::formatHeroData($general, $equipped, $uid);

        return [
            'status' => 1,
            'info' => $heroInfo,
            'equipments' => $unequipped,
        ];
    }

    /**
     * 格式化武将完整协议数据 (包含 heropop.js 依赖的 talent, skill, eid1~eid4 等)
     *
     * @param array<string, mixed> $g
     * @param list<array<string, mixed>> $equipped
     * @return array<string, mixed>
     */
    public static function formatHeroData(array $g, array $equipped, int $uid): array
    {
        $gid = (int)$g['id'];
        $name = (string)($g['name'] ?? '');
        $cfg = self::HERO_CONFIGS[$name] ?? null;

        // 查询玩家性别以决定主公头像 (女2/男1)
        $memberGender = 1;
        $member = DB::table('member')->where('uid', $uid)->first();
        if ($member !== null) {
            $memberGender = (int)($member['gender'] ?? 1);
        }

        $image = (int)($g['image'] ?? 0);
        if ($image <= 0) {
            $image = self::resolveHeroImage($name, $memberGender, (int)($g['generalid'] ?? 1));
        }

        $wuli = (int)($g['wuli'] ?? $cfg['wuli'] ?? 85);
        $zhili = (int)($g['zhili'] ?? $cfg['zhili'] ?? 70);
        $tili = (int)($g['tili'] ?? $cfg['tili'] ?? 90);
        $quality = (int)($g['quality'] ?? $cfg['quality'] ?? 3);
        $type = (int)($g['type'] ?? $cfg['type'] ?? 1);

        $gWithAttributes = array_merge($g, [
            'wuli' => $wuli,
            'zhili' => $zhili,
            'tili' => $tili,
        ]);
        $stats = self::calculateGeneralStats($gWithAttributes, $equipped);
        $level = $stats['level'];

        $equipSlot = [];
        $eids = [1 => '0', 2 => '0', 3 => '0', 4 => '0'];

        foreach ($equipped as $eq) {
            $eType = (int)$eq['type'];
            $bonus = self::calculateEquipBonus($eq);
            $powerup = $bonus['attack'] ?: ($bonus['defense'] ?: $bonus['hp']);
            $eqItem = [
                'id' => (int)$eq['id'],
                'equipid' => (int)$eq['equipid'],
                'name' => $eq['name'],
                'type' => $eType,
                'level' => (int)$eq['level'],
                'quality' => (int)$eq['quality'],
                'needlevel' => 1,
                'available' => 1,
                'newquality' => 0,
                'powerup' => $powerup,
                'hammer' => 0,
                'hammer_quality' => 0,
                'bonus' => $bonus,
            ];
            $equipSlot[$eType] = $eqItem;
            if ($eType >= 1 && $eType <= 4) {
                $eids[$eType] = $eqItem;
            }
        }

        return [
            'id' => $gid,
            'uid' => $uid,
            'generalid' => (int)($g['generalid'] ?? 1),
            'name' => $name,
            'level' => $level,
            'exp' => (int)$g['exp'],
            'levelupexp' => self::getLevelUpExp($level),
            'image' => $image,
            'quality' => $quality,
            'type' => $type,
            'turn' => (int)($g['turn'] ?? 0),
            'isturn' => ($level >= 100) ? 1 : 0,
            'star' => (int)($g['star'] ?? 1),
            'achID' => 0,
            'achList' => [],
            'pos' => (int)$g['pos'],
            'goout' => (int)$g['pos'] > 0 ? 1 : 0,
            'wuli' => $wuli,
            'wuliup' => (int)($g['wuliup'] ?? 0),
            'zhili' => $zhili,
            'zhiliup' => (int)($g['zhiliup'] ?? 0),
            'tili' => $tili,
            'tiliup' => (int)($g['tiliup'] ?? 0),
            'hp' => $stats['hp'],
            'allblood' => $stats['allblood'],
            'attack' => $stats['attack'],
            'defense' => $stats['defense'],
            'speed' => $stats['speed'],
            'skillid' => (int)($g['skillid'] ?? 1),
            'talentid' => (int)($g['talentid'] ?? 1),
            'talent' => self::getGeneralTalent($g),
            'skill' => self::getGeneralSkill($g),
            'eid1' => $eids[1],
            'eid2' => $eids[2],
            'eid3' => $eids[3],
            'eid4' => $eids[4],
            'mid0' => 0,
            'mid1' => 0,
            'mid2' => 0,
            'mid3' => 0,
            'mid4' => 0,
            'equip' => $equipSlot,
            'stats' => $stats,
        ];
    }

    /**
     * @param array<string, mixed> $g
     * @return array{id: int, name: string, blood: int, description: string}
     */
    public static function getGeneralTalent(array $g): array
    {
        $talents = [
            1 => ['id' => 1, 'name' => '坚强', 'blood' => 1, 'description' => '受到直接物理伤害降低5%'],
            2 => ['id' => 2, 'name' => '勇猛', 'blood' => 1, 'description' => '普通物理攻击伤害提升10%'],
            3 => ['id' => 3, 'name' => '神速', 'blood' => 1, 'description' => '出手先攻速度提升15点'],
            4 => ['id' => 4, 'name' => '智囊', 'blood' => 1, 'description' => '策略攻击防御综合提升10%'],
            5 => ['id' => 5, 'name' => '破军', 'blood' => 1, 'description' => '无双暴击概率提升10%'],
            6 => ['id' => 6, 'name' => '百战', 'blood' => 1, 'description' => '气血生命值上限提升15%'],
        ];

        $name = (string)($g['name'] ?? '');
        $id = match ($name) {
            '张飞' => 2,
            '赵云' => 3,
            '关羽' => 5,
            '诸葛亮' => 4,
            default => (int)($g['talentid'] ?? 1),
        };

        return $talents[$id] ?? $talents[1];
    }

    /**
     * @param array<string, mixed> $g
     * @return array{id: int, name: string, description: string}
     */
    public static function getGeneralSkill(array $g): array
    {
        $skills = [
            1 => ['id' => 1, 'name' => '会心一击', 'description' => '积攒怒气释放强力一击，造成150%战法伤害'],
            2 => ['id' => 2, 'name' => '万夫莫敌', 'description' => '咆哮怒吼横扫千军，对横排敌方造成160%群体震慑'],
            3 => ['id' => 3, 'name' => '龙胆穿心', 'description' => '白马银枪直刺中路，必定暴击并破除敌军格挡'],
            4 => ['id' => 4, 'name' => '青龙偃月', 'description' => '神刀劈砍降下雷霆万钧，重创敌将并提升自身韧性'],
            5 => ['id' => 5, 'name' => '卧龙天火', 'description' => '借东风降下神火天降，对敌军全阵型进行法术覆盖'],
        ];

        $name = (string)($g['name'] ?? '');
        $id = match ($name) {
            '张飞' => 2,
            '赵云' => 3,
            '关羽' => 4,
            '诸葛亮' => 5,
            default => (int)($g['skillid'] ?? 1),
        };

        return $skills[$id] ?? $skills[1];
    }

    /**
     * 穿装备: c=general&m=equip
     */
    public static function equip(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $gid = (int)$ctx->input('gid', 0);
        $eid = (int)$ctx->input('eid', 0);

        if ($gid <= 0 || $eid <= 0) {
            return [
                'status' => 0,
                'message' => '参数错误',
            ];
        }

        $general = DB::table('general')->where('uid', $uid)->where('id', $gid)->first();
        if ($general === null) {
            return [
                'status' => -1,
                'message' => '武将不存在',
            ];
        }

        $equip = DB::table('equipment')->where('uid', $uid)->where('id', $eid)->first();
        if ($equip === null) {
            return [
                'status' => -4,
                'message' => '装备不存在',
            ];
        }

        // 检查武将该槽位是否已有穿戴中的同类型装备，有则自动卸下
        $equipType = (int)$equip['type'];
        $alreadyEquipped = DB::table('equipment')
            ->where('uid', $uid)
            ->where('generalid', $gid)
            ->where('type', $equipType)
            ->get();

        foreach ($alreadyEquipped as $old) {
            DB::table('equipment')->where('id', (int)$old['id'])->update(['generalid' => 0]);
        }

        // 装配新装备
        DB::table('equipment')->where('id', $eid)->update(['generalid' => $gid]);

        // 获取最新穿戴属性与背包装备
        $allEquips = DB::table('equipment')->where('uid', $uid)->whereNull('deleted_at')->get();
        $equipped = [];
        $unequipped = [];
        $eqIdx = 1;
        foreach ($allEquips as $item) {
            if ((int)$item['generalid'] === $gid) {
                $equipped[] = $item;
            } elseif ((int)$item['generalid'] === 0) {
                $bonus = self::calculateEquipBonus($item);
                $unequipped[(string)$eqIdx] = array_merge($item, [
                    'id' => (int)$item['id'],
                    'equipid' => (int)$item['equipid'],
                    'etype' => (int)$item['type'],
                    'name' => $item['name'],
                    'type' => (int)$item['type'],
                    'level' => (int)$item['level'],
                    'quality' => (int)$item['quality'],
                    'gid' => 0,
                    'needlevel' => 1,
                    'available' => 1,
                    'hammer' => 0,
                    'hammer_quality' => 0,
                    'powerup' => $bonus['attack'] ?: ($bonus['defense'] ?: $bonus['hp']),
                    'bonus' => $bonus,
                ]);
                $eqIdx++;
            }
        }

        $stats = self::calculateGeneralStats($general, $equipped);
        $heroData = self::formatHeroData($general, $equipped, $uid);

        return [
            'status' => 1,
            'message' => '穿戴成功',
            'gid' => $gid,
            'eid' => $eid,
            'stats' => $stats,
            'info' => $heroData,
            'equipments' => $unequipped,
        ];
    }

    /**
     * 卸装备: c=general&m=unequip
     */
    public static function unequip(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $gid = (int)$ctx->input('gid', 0);
        $eid = (int)$ctx->input('eid', 0);

        if ($gid <= 0 || $eid <= 0) {
            return [
                'status' => 0,
                'message' => '参数错误',
            ];
        }

        $equip = DB::table('equipment')->where('uid', $uid)->where('id', $eid)->first();
        if ($equip === null) {
            return [
                'status' => -1,
                'message' => '装备不存在',
            ];
        }

        if ((int)$equip['generalid'] !== $gid) {
            return [
                'status' => -3,
                'message' => '该装备未穿戴在指定武将身上',
            ];
        }

        // 卸下装备
        DB::table('equipment')->where('id', $eid)->update(['generalid' => 0]);

        $general = DB::table('general')->where('uid', $uid)->where('id', $gid)->first();
        $allEquips = DB::table('equipment')->where('uid', $uid)->whereNull('deleted_at')->get();
        $equipped = [];
        $unequipped = [];
        $eqIdx = 1;
        foreach ($allEquips as $item) {
            if ((int)$item['generalid'] === $gid) {
                $equipped[] = $item;
            } elseif ((int)$item['generalid'] === 0) {
                $bonus = self::calculateEquipBonus($item);
                $unequipped[(string)$eqIdx] = array_merge($item, [
                    'id' => (int)$item['id'],
                    'equipid' => (int)$item['equipid'],
                    'etype' => (int)$item['type'],
                    'name' => $item['name'],
                    'type' => (int)$item['type'],
                    'level' => (int)$item['level'],
                    'quality' => (int)$item['quality'],
                    'gid' => 0,
                    'needlevel' => 1,
                    'available' => 1,
                    'hammer' => 0,
                    'hammer_quality' => 0,
                    'powerup' => $bonus['attack'] ?: ($bonus['defense'] ?: $bonus['hp']),
                    'bonus' => $bonus,
                ]);
                $eqIdx++;
            }
        }

        $stats = $general ? self::calculateGeneralStats($general, $equipped) : [];
        $heroData = $general ? self::formatHeroData($general, $equipped, $uid) : [];

        return [
            'status' => 1,
            'message' => '卸下成功',
            'gid' => $gid,
            'eid' => $eid,
            'stats' => $stats,
            'info' => $heroData,
            'equipments' => $unequipped,
        ];
    }
}
