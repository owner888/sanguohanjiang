<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use Hanjiang\MasterData\Flop as FlopRule;
use Hanjiang\MasterData\FlopReward;
use OmniPHP\Database\DB;

/**
 * 四方来朝（翻牌）活动（移植旧版 models/flop_model.php 与 member_model / tavern_model 的翻牌计数）
 *
 * 活动开启（ActivityTime 的 flop）期间，元宝（sub_gold）、军令（sub_act）、突飞卡（sub_freeleap）的消耗分别累计到
 * member_activity.flopgold / flopact / flopleap，按 MasterData Flop 第「已换算次数 + 1」行（28 次以后都取第 28 行）的价格
 * 换算成翻牌次数 floptimes（get_times）；三种累计与已换算次数每日计划任务归零，剩余翻牌次数不归零。
 * 翻牌奖励按 MasterData FlopReward 的出现概率 rate 抽类型，再按 multiply 权重抽倍数（get_reward_info）。
 *
 * 当前时间取 BossClock（生产恒为 time()，只有 PHPUnit 可固定时间测试活动期间）。
 */
final class Flop
{
    public const string ACTIVITY = 'flop';

    /** 消耗类型 → MasterData Flop 的价格列 */
    public const string SPEND_GOLD = 'gold';
    public const string SPEND_ACT = 'act';
    public const string SPEND_LEAP = 'leap';

    /** 规则表最后一行（28 次以后都按这一行） */
    private const int MAX_INDEX = 28;

    /**
     * 活动信息（activity_model->get_info_by_name）
     *
     * @return array{id: int, name: string, starttime: int, endtime: int, version: int, type: int}|null
     */
    public static function activity(): ?array
    {
        return ActivityClock::byName(self::ACTIVITY);
    }

    /**
     * 活动是否开启（activity_status：starttime <= 当前 <= endtime）
     */
    public static function isOpen(int $now): bool
    {
        $info = self::activity();
        return $info !== null && $info['starttime'] <= $now && $info['endtime'] >= $now;
    }

    /**
     * 规则表行号（_get_info / get_times：已换算次数 0 → 1，>= 28 → 28，其余 + 1）
     */
    public static function ruleIndex(int $times): int
    {
        if ($times === 0) {
            return 1;
        }
        return $times >= self::MAX_INDEX ? self::MAX_INDEX : $times + 1;
    }

    /**
     * 消耗累计换算翻牌次数（get_times）
     *
     * @return array{times: int, num: int} times 新换算的次数，num 换算后剩余的累计
     */
    public static function getTimes(int $times, string $type, int $num): array
    {
        $i = 0;
        while ($num > 0) {
            $price = (int)FlopRule::ROWS[self::ruleIndex($times)][$type];
            if ($price > 0 && $num >= $price) {
                $i++;
                $times++;
                $num -= $price;
            } else {
                break;
            }
        }
        return ['times' => $i, 'num' => $num];
    }

    /**
     * 消耗计入翻牌（sub_gold / sub_act / sub_freeleap 的 _flop 分支）：活动未开启不处理；
     * 没有活动记录时按旧版 get_activity_info 建档。在事务里对活动行加锁读改写（多 Worker 安全）。
     */
    public static function addSpend(int $uid, string $type, int $num): void
    {
        if ($num <= 0 || !self::isOpen(BossClock::now())) {
            return;
        }
        MemberActivity::ensure($uid);
        $run = static function () use ($uid, $type, $num): void {
            $row = DB::query(
                "SELECT `flop{$type}` AS `spend`, `flop{$type}times` AS `times`, `floptimes` FROM `zc_sanguo_member_activity`
                 WHERE `uid` = :uid AND `deleted_at` IS NULL FOR UPDATE",
                ['uid' => $uid]
            )[0] ?? null;
            if ($row === null) {
                return;
            }
            $info = self::getTimes((int)$row['times'], $type, $num + (int)$row['spend']);
            MemberActivity::update($uid, [
                "flop{$type}times" => (int)$row['times'] + $info['times'],
                'floptimes' => (int)$row['floptimes'] + $info['times'],
                "flop{$type}" => $info['num'],
            ]);
        };
        DB::inTransaction() ? $run() : DB::transaction($run);
    }

    /**
     * 按权重抽一个下标（_roll：从 0 数到 count - 1，跳过没有的下标）
     *
     * 旧版倍数权重的下标是 1 / 2 / 4 / 8，循环只走到 count - 1 = 3，所以只可能抽到 1 或 2 倍（随机数落在
     * 1、2 倍权重之外时也取 2），照旧版保留。
     *
     * @param array<int, int> $rate
     */
    public static function roll(array $rate): int
    {
        $return = 0;
        $rand = mt_rand(1, (int)array_sum($rate));
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
     * 奖励（get_reward_info）：$id 为 0 时按出现概率抽类型，否则取第 $id 种；银币（id 2）数量乘主公等级
     *
     * @return array{name: string, type: int, num: int, multiply: int}
     */
    public static function rewardInfo(int $level, int $id = 0): array
    {
        $rule = array_values(FlopReward::ROWS);
        if ($id > 0) {
            $key = $id - 1;
        } else {
            $rate = [];
            foreach ($rule as $k => $value) {
                // 取出现概率
                $rate[$k] = (int)$value['rate'];
            }
            // 获得出现的类型
            $key = self::roll($rate);
        }
        // 取翻倍概率
        $multiplyArr = [];
        for ($i = 1; $i < 9; $i++) {
            if (isset($rule[$key]['multiply' . $i])) {
                $multiplyArr[$i] = (int)$rule[$key]['multiply' . $i];
            }
        }
        // 获得倍数
        $multiply = self::roll($multiplyArr);
        $num = (int)$rule[$key]['num'];
        if ((int)$rule[$key]['id'] === 2) {
            $num *= $level;
        }
        return [
            'name' => (string)$rule[$key]['name'],
            'type' => (int)$rule[$key]['id'],
            'num' => $num,
            'multiply' => $multiply,
        ];
    }
}
