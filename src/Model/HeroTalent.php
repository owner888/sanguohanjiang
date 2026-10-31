<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use Hanjiang\MasterData\HeroTalentInsight;
use Hanjiang\MasterData\HeroTalentStar;
use OmniPHP\Database\DB;

/**
 * 主公天赋（移植旧版 models/talent_model.php）
 *
 * 存储同旧版 own_hero_talent：每个玩家 5 行（uid + type 唯一），type 1 物攻 / 2 物防 / 3 策攻 / 4 策防 / 5 生命，
 * star 为星级（0 ~ 300），quality 为品级（1 白 ~ 7 橙），power 为加成值（get_talent_info）。
 * 配置：升星 HeroTalentStar、顿悟 HeroTalentInsight（星级 20 / 40 / 60 / 80 / 100 / 200 的下一星）。
 * 加成计入战斗（TeamBuilder，主公 91 级起）与将领界面的血量（GeneralHandler，生命天赋）。
 *
 * 随机数同旧版用 mt_rand（_roll）。
 */
final class HeroTalent
{
    /** 旧版 own_hero_talent 的字段 */
    private const array FIELDS = ['uid', 'type', 'quality', 'star', 'power'];

    /** 天赋成长（get_talent_info 的 $list：类型 => [品级 => 每星成长]） */
    private const array GROWTH = [
        1 => [1 => 20, 2 => 30, 3 => 50, 4 => 80, 5 => 120, 6 => 170, 7 => 230],
        2 => [1 => 20, 2 => 30, 3 => 50, 4 => 80, 5 => 120, 6 => 170, 7 => 230],
        3 => [1 => 20, 2 => 30, 3 => 50, 4 => 80, 5 => 120, 6 => 170, 7 => 230],
        4 => [1 => 20, 2 => 30, 3 => 50, 4 => 80, 5 => 120, 6 => 170, 7 => 230],
        5 => [1 => 60, 2 => 90, 3 => 150, 4 => 240, 5 => 360, 6 => 510, 7 => 570],
    ];

    /** 初始值（get_talent_info 的 $base；旧版注释写的是品级，实际按类型取：生命 300，其余 100） */
    private const array BASE = [1 => 100, 2 => 100, 3 => 100, 4 => 100, 5 => 300];

    /** 满星 */
    public const int MAX_STAR = 300;

    /**
     * 天赋加成值（移植 get_talent_info：初始 + 星级 × 成长）
     */
    public static function talentInfo(int $star, int $type, int $quality): int
    {
        return (int)((self::BASE[$type] ?? 0) + $star * (self::GROWTH[$type][$quality] ?? 0));
    }

    /**
     * 主公天赋列表（移植 get_talent_list；没有记录时建 5 行白色 0 星）
     *
     * 旧版不传 uid 且主公不足 91 级时返回空，由调用方（控制器 / 战斗）先判断等级。
     *
     * @return list<array<string, int>>
     */
    public static function list(int $uid): array
    {
        $rows = DB::table('own_hero_talent')->select(...self::FIELDS)->where('uid', $uid)->whereNull('deleted_at')->orderBy('type')->get();
        if ($rows !== []) {
            return array_map(static fn (array $row): array => array_map('intval', $row), $rows);
        }
        return self::addList($uid);
    }

    /**
     * 按类型取主公天赋（移植 get_talent_by_type）；没有返回空数组
     *
     * @return array<string, int>
     */
    public static function byType(int $uid, int $type): array
    {
        $row = DB::table('own_hero_talent')->select(...self::FIELDS)->where('uid', $uid)->where('type', $type)->whereNull('deleted_at')->first();
        return $row === null ? [] : array_map('intval', $row);
    }

    /**
     * 更新主公天赋（移植 update_talent）
     *
     * @param array<string, int> $data
     */
    public static function update(int $uid, int $type, array $data): void
    {
        DB::table('own_hero_talent')->where('uid', $uid)->where('type', $type)->whereNull('deleted_at')->update($data);
    }

    /**
     * 按星级取下一星的规则（移植 get_rule）
     *
     * 星级 1 ~ 100 中 20 的倍数与 100 以上 100 的倍数下一星为顿悟（mode 1，HeroTalentInsight），其余为升星（mode 2，HeroTalentStar）；
     * rule 另带目标星级的品级 quality 与加成 power。
     *
     * @return array{status: int, rule?: array<string, int>, mode?: int} status 1 成功，2 满级，-1 取不到规则
     */
    public static function rule(int $star, int $type): array
    {
        $return = ['status' => 0];
        if ($star >= self::MAX_STAR) {
            // 满级
            $return['status'] = 2;
            return $return;
        }
        if (($star > 0 && $star <= 100 && $star % 20 === 0) || ($star > 100 && $star % 100 === 0)) {
            // 顿悟
            $star += 1;
            $rule = HeroTalentInsight::ROWS[$star] ?? [];
            $return['mode'] = 1;
        } else {
            // 升星
            $star += 1;
            $rule = HeroTalentStar::ROWS[$star] ?? [];
            $return['mode'] = 2;
        }
        if ($rule === []) {
            // 获取升星或顿悟规则失败
            $return['status'] = -1;
            return $return;
        }
        $quality = match (true) {
            $star < 21 => 1,   // 白色
            $star < 41 => 2,   // 绿色
            $star < 61 => 3,   // 蓝色
            $star < 81 => 4,   // 金色
            $star < 101 => 5,  // 紫色
            $star < 201 => 6,  // 红色
            default => 7,      // 橙色
        };
        $rule = array_map('intval', $rule);
        $rule['quality'] = $quality;
        $rule['power'] = self::talentInfo($star, $type, $quality);
        $return['rule'] = $rule;
        // 旧版 rule 在 mode 之前赋值
        $return = ['status' => 1, 'rule' => $return['rule'], 'mode' => $return['mode']];
        return $return;
    }

    /**
     * 升星（移植 starup）
     *
     * $success > 0 为元宝必成（元宝不足返回 2）；否则按成功率 rate 掷骰，失败星级不变（info 为原星级与目标品级）。
     * 成功后 VIP 9 以上按 crit 暴击多升 1 星（目标星级为 20 的倍数且不超过 100 时不暴击）。
     *
     * @param array<string, int> $rule
     * @return array{status: int, gold: int, info?: array<string, int>, hit?: int}
     */
    public static function starup(array $rule, int $success, int $gold, int $vip): array
    {
        $return = ['status' => 0, 'gold' => 0];
        if ($success > 0) {
            $price = $rule['success'];
            if ($price > $gold) {
                // 元宝不足
                $return['status'] = 2;
                return $return;
            }
            $return['gold'] = $price;
        } else {
            $result = self::roll([0 => 100 - $rule['rate'], 1 => $rule['rate']]);
            if ($result < 1) {
                // 升星失败
                $return['info'] = ['star' => $rule['star'] - 1, 'quality' => $rule['quality']];
                $return['status'] = -1;
                return $return;
            }
        }

        if ($vip > 8) {
            $result = self::roll([0 => 100 - $rule['crit'], 1 => $rule['crit']]);
            if ($result > 0) {
                if ($rule['star'] % 20 === 0 && $rule['star'] <= 100) {
                    $return['info']['star'] = $rule['star'];
                    $return['hit'] = 0;
                } else {
                    $return['info']['star'] = $rule['star'] + 1;
                    $return['hit'] = 1;
                }
            } else {
                $return['info']['star'] = $rule['star'];
                $return['hit'] = 0;
            }
        } else {
            $return['info']['star'] = $rule['star'];
            $return['hit'] = 0;
        }
        $return['info']['quality'] = $rule['quality'];
        $return['status'] = 1;
        return $return;
    }

    /**
     * 顿悟（移植 insight）
     *
     * $success > 0 为元宝必成（VIP 6 以下返回 2，元宝不足 3）；$protect > 0 为元宝保护（失败不掉星，只掉品级显示）；
     * 否则失败时按 sub1 ~ sub5 掉 1 ~ 4 星（旧版 _roll 只遍历到下标 4，sub5 的概率落到掉 4 星）。
     *
     * @param array<string, int> $rule
     * @return array{status: int, gold: int, info?: array<string, int>}
     */
    public static function insight(array $rule, int $protect, int $success, int $gold, int $vip): array
    {
        $return = ['status' => 0, 'gold' => 0];
        if ($success > 0) {
            // 是否vip6
            if ($vip < 6) {
                // vip等级不足
                $return['status'] = 2;
                return $return;
            }
            $price = $rule['success'];
            if ($price > $gold) {
                // 元宝不足
                $return['status'] = 3;
                return $return;
            }
            $return['gold'] = $price;
        } elseif ($protect > 0) {
            // 保护不降星
            $price = $rule['protect'];
            if ($price > $gold) {
                // 元宝不足
                $return['status'] = 3;
                return $return;
            }
            $return['gold'] = $price;
            $result = self::roll([0 => 100 - $rule['rate'], 1 => $rule['rate']]);
            if ($result < 1) {
                // 顿悟失败
                $return['info'] = ['star' => $rule['star'] - 1, 'quality' => $rule['quality'] - 1];
                $return['status'] = -1;
                return $return;
            }
        } else {
            $result = self::roll([0 => 100 - $rule['rate'], 1 => $rule['rate']]);
            if ($result < 1) {
                $rate2 = [];
                for ($i = 1; $i < 6; $i++) {
                    $rate2[$i] = $rule['sub' . $i];
                }
                $result = self::roll($rate2);
                $return['info'] = ['star' => $rule['star'] - $result, 'quality' => $rule['quality'] - 1];
                // 顿悟失败
                $return['status'] = -1;
                return $return;
            }
        }
        $return['info'] = ['star' => $rule['star'], 'quality' => $rule['quality']];
        $return['status'] = 1;
        return $return;
    }

    /**
     * 按权重掷骰（移植 _roll：mt_rand(1, 权重和)，按下标 0 ~ count - 1 累加，取到的最后一个下标；没有任何下标时为 0）
     *
     * @param array<int, int> $rate
     */
    private static function roll(array $rate): int
    {
        $return = 0;
        $rand = mt_rand(1, max(1, array_sum($rate)));
        $cursor = 0;
        for ($i = 0, $size = count($rate); $i < $size; $i++) {
            if (isset($rate[$i])) {
                $cursor += $rate[$i];
                $return = $i;
                if ($cursor >= $rand) {
                    break;
                }
            }
        }
        return $return;
    }

    /**
     * 建 5 行白色 0 星的天赋（移植 add_talent_list）
     *
     * @return list<array<string, int>>
     */
    private static function addList(int $uid): array
    {
        $data = [];
        for ($i = 1; $i < 6; $i++) {
            $data[] = ['uid' => $uid, 'type' => $i, 'quality' => 1, 'star' => 0, 'power' => self::talentInfo(0, $i, 1)];
        }
        DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_own_hero_talent` (`uid`, `type`, `quality`, `star`, `power`) VALUES '
            . '(:u1, 1, 1, 0, :p1), (:u2, 2, 1, 0, :p2), (:u3, 3, 1, 0, :p3), (:u4, 4, 1, 0, :p4), (:u5, 5, 1, 0, :p5)',
            [
                'u1' => $uid, 'u2' => $uid, 'u3' => $uid, 'u4' => $uid, 'u5' => $uid,
                'p1' => $data[0]['power'], 'p2' => $data[1]['power'], 'p3' => $data[2]['power'], 'p4' => $data[3]['power'], 'p5' => $data[4]['power'],
            ]
        );
        return $data;
    }
}
