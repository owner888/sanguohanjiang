<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use Hanjiang\MasterData\LeapCrit;
use Hanjiang\MasterData\Price;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;

/**
 * 校场训练位（移植旧版 models/practice_model.php，以及 controllers/practice.php / map.php / general_inherit.php 共用的
 * update_practice + _update_status）
 *
 * 训练位存 practice（旧版 own_practice，gid 列新版叫 generalid）：每人按 squence 一行，level 1 ~ 6（普通 ~ 至尊，经验 100% ~ 400%），
 * 训练中的将领 generalid、类型 type、starttime / endtime。member_field.practiceall 为已开启的训练位数，practicenow 为训练中的个数，
 * practicetime 为训练中最早的结束时间（主城倒计时）。
 *
 * 训练经验：主公等级 × 小时 × 112.5 × 训练位倍率，向上取整；每次结算（打开校场、开始训练、主公升级、查看继承）把上次结算到现在的
 * 经验加给将领（General::addExp），到期的训练位清空。终止训练（practice_stop）不结算经验（旧版同）。
 *
 * 与旧版的差别：update_practice 在玩家行锁里执行（并发结算不会重复加经验）；训练位唯一键 (uid, squence)。
 */
final class Practice
{
    /** 训练位等级（get_level_by_id：名称取 base_lang.php，result 为经验倍率 %，price 为升到该级的元宝） */
    public const array LEVEL_INFO = [
        1 => ['name' => '普通', 'result' => '100', 'price' => 0],
        2 => ['name' => '加强', 'result' => '150', 'price' => 50],
        3 => ['name' => ' 高级', 'result' => '200', 'price' => 100],
        4 => ['name' => '白金', 'result' => '250', 'price' => 200],
        5 => ['name' => '钻石', 'result' => '300', 'price' => 300],
        6 => ['name' => '至尊', 'result' => '400', 'price' => 600],
    ];

    /** 训练位解锁价格（get_squence_price：位置 => 元宝 / VIP） */
    public const array SQUENCE_PRICE = [
        3 => ['gold' => 50],
        4 => ['gold' => 100, 'vip' => 1],
        5 => ['gold' => 500, 'vip' => 2],
        6 => ['gold' => 1000, 'vip' => 4],
        7 => ['gold' => 2000, 'vip' => 6],
        8 => ['gold' => 5000, 'vip' => 7],
    ];

    /**
     * 新账号的 1、2 号训练位（init_practice）
     */
    public static function init(int $uid): void
    {
        foreach ([1, 2] as $squence) {
            DB::execute(
                'INSERT IGNORE INTO `zc_sanguo_practice` (`uid`, `squence`, `level`, `type`) VALUES (:uid, :squence, 1, 1)',
                ['uid' => $uid, 'squence' => $squence]
            );
        }
        DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->update(['practiceall' => 2]);
    }

    /**
     * 训练位列表（get_practice_list：$type 1 只取训练中的；键为 squence，带训练位等级的 name / result / price）
     *
     * @return array{status: int, list: array<int, array<string, mixed>>} status -1 没有训练位
     */
    public static function practiceList(int $uid, int $type = 0): array
    {
        $query = DB::table('practice')->where('uid', $uid)->whereNull('deleted_at');
        if ($type === 1) {
            $query->where('generalid', '>', 0);
        }
        $rows = $query->orderBy('id')->get();
        if ($rows === []) {
            // 训练位数据异常
            return ['status' => -1, 'list' => []];
        }
        $result = [];
        foreach ($rows as $row) {
            $value = self::row($row);
            $level = self::LEVEL_INFO[$value['level']] ?? null;
            if ($level !== null) {
                $value['name'] = $level['name'];
                $value['result'] = $level['result'];
                $value['price'] = $level['price'];
            }
            $result[$value['squence']] = $value;
        }
        return ['status' => 1, 'list' => $result];
    }

    /**
     * 训练位（get_own_by_id 单个）；没有为 null
     *
     * @return array<string, int>|null
     */
    public static function own(int $uid, int $pid): ?array
    {
        $row = DB::table('practice')->where('uid', $uid)->where('id', $pid)->whereNull('deleted_at')->first();
        return $row === null ? null : self::row($row);
    }

    /**
     * 将领所在的训练位 id（check_practice）；不在训练为 0
     */
    public static function checkPractice(int $uid, int $gid): int
    {
        return (int)(DB::table('practice')->where('uid', $uid)->where('generalid', $gid)->whereNull('deleted_at')->value('id') ?? 0);
    }

    /**
     * 训练类型（get_type_list：1 / 2 银币 8 / 24 小时，3 / 4 元宝 24 / 72 小时）
     *
     * @return array<int, array{paytype: int, hour: int, cost: int}>
     */
    public static function typeList(int $level): array
    {
        return [
            1 => ['paytype' => 1, 'hour' => 8, 'cost' => 100 * $level],
            2 => ['paytype' => 1, 'hour' => 24, 'cost' => 255 * $level],
            3 => ['paytype' => 2, 'hour' => 24, 'cost' => 2],
            4 => ['paytype' => 2, 'hour' => 72, 'cost' => 20],
        ];
    }

    /**
     * 突飞规则（get_leap_rule：第 $times 次突飞的元宝（price.leap，表外 50）；将领等级 > 0 且 VIP 5 以上时按突飞暴击率翻倍经验；
     * 经验 = 主公等级 × 562.5 × 倍数）
     *
     * @return array{status: int, gold: int, exp: float, hit: int}
     */
    public static function leapRule(int $times, int $glevel, int $level, int $vip): array
    {
        $gold = isset(Price::ROWS[$times]) ? (int)Price::ROWS[$times]['leap'] : 50;
        $addition = 1;
        if ($glevel > 0 && $vip > 4) {
            $crit = self::leapCrit($glevel);
            $rule = [1 => 100 - $crit, 2 => $crit];
            $roll = mt_rand(1, 100);
            $cursor = 0;
            for ($i = 1; $i <= 2; $i++) {
                $cursor += $rule[$i];
                $addition = $i;
                if ($cursor >= $roll) {
                    break;
                }
            }
        }
        return [
            'status' => 1,
            'gold' => $gold,
            'exp' => $level * 562.5 * $addition,
            'hit' => $addition === 2 && $vip >= 5 ? 1 : 0,
        ];
    }

    /**
     * 突飞暴击率（get_leap_crit：MasterData LeapCrit，等级上限 200；没有为 -1）
     */
    public static function leapCrit(int $level): int
    {
        $level = min($level, 200);
        return isset(LeapCrit::ROWS[$level]) ? (int)LeapCrit::ROWS[$level]['probability'] : -1;
    }

    /**
     * 突飞扫荡经验（get_mop_exp：暴击率在 ±2 内随机）；参数不合法为空数组
     *
     * @return array{exp: float, ext_exp: float}|array{}
     */
    public static function mopExp(int $times, int $crit, int $level): array
    {
        if ($times < 1 || $times > 100 || $crit < 1 || $crit > 100) {
            return [];
        }
        $min = $crit - 2 > 0 ? $crit - 2 : 1;
        $crit = mt_rand($min, $crit + 2);
        $exp = $level * 562.5 * $times;
        return ['exp' => floor($exp), 'ext_exp' => floor($exp * $crit / 100)];
    }

    /**
     * 更新今日突飞次数（update_leaptimes）
     *
     * @return int 1 成功；-1 失败
     */
    public static function updateLeaptimes(int $uid, int $times): int
    {
        return DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->update(['leaptimes' => $times]) > 0 ? 1 : -1;
    }

    /**
     * 添加训练位（add_practice）
     *
     * @return array{status: int, id?: int} status 1 成功；-2 插入失败；-3 更新扩展表失败
     */
    public static function addPractice(int $uid, int $squence): array
    {
        $affected = DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_practice` (`uid`, `squence`, `level`, `type`) VALUES (:uid, :squence, 1, 0)',
            ['uid' => $uid, 'squence' => $squence]
        );
        if ($affected === 0) {
            // 插入失败 -2
            return ['status' => -2];
        }
        $id = (int)DB::query('SELECT LAST_INSERT_ID() AS `v`')[0]['v'];
        if (DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->update(['practiceall' => $squence]) <= 0) {
            // 更新扩展表失败 -3
            return ['status' => -3, 'id' => $id];
        }
        Redis::del('player:' . $uid);
        return ['status' => 1, 'id' => $id];
    }

    /**
     * 开始训练（update_time）
     *
     * @param array{pid: int, gid: int, type: int, hour: int, practicenow: int, practicetime: int} $practice
     * @return array{status: int, info: array<string, int>} status 1 成功；-2 更新训练位失败；-3 更新扩展表失败
     */
    public static function updateTime(int $uid, array $practice, int $now): array
    {
        $end = $now + $practice['hour'] * 3600;
        $data = ['generalid' => $practice['gid'], 'type' => $practice['type'], 'starttime' => $now, 'endtime' => $end];
        $affected = DB::table('practice')->where('id', $practice['pid'])->where('uid', $uid)->whereNull('deleted_at')->update($data);
        if ($affected <= 0) {
            return ['status' => -2, 'info' => []];
        }
        $info = ['practicenow' => $practice['practicenow'] + 1];
        if ($practice['practicetime'] === 0 || $practice['practicetime'] > $end) {
            $info['practicetime'] = $end;
        }
        if (DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->update($info) <= 0) {
            return ['status' => -3, 'info' => []];
        }
        Redis::del('player:' . $uid);
        return ['status' => 1, 'info' => ['gid' => $practice['gid'], 'type' => $practice['type'], 'starttime' => $now, 'endtime' => $end]];
    }

    /**
     * 结算全部训练中的训练位（控制器 update_practice + _update_status，在玩家行锁里执行）
     *
     * @return int 1 成功；2 没有将领在训练；-1 获取扩展信息失败；-2 获取训练列表失败
     */
    public static function updatePractice(int $uid, ?int $now = null): int
    {
        $now ??= time();
        return Member::locked($uid, static function () use ($uid, $now): int {
            $field = DB::table('member_field')->select('practicenow')->where('uid', $uid)->whereNull('deleted_at')->first();
            if ($field === null) {
                // 获取扩展信息失败 -1
                return -1;
            }
            $num = (int)$field['practicenow'];
            if ($num === 0) {
                // 没有将领在训练 2
                return 2;
            }
            // 获得正在使用训练位列表
            $list = self::practiceList($uid, 1);
            if ($list['status'] !== 1) {
                // 获取训练列表失败 -2
                return -2;
            }
            $level = (int)DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('level');
            $end = 0;
            $endtime = [];
            foreach ($list['list'] as $value) {
                // 更新训练位
                $info = self::updateStatus($uid, $value, $level, $now);
                if ($info['status'] === 1) {
                    // [将领模型]加经验($gid, $exp)
                    General::addExp($uid, $info['gid'], (int)$info['exp']);
                    $end += $info['end'];
                    if ($info['end'] === 0) {
                        $endtime[] = $value['endtime'];
                    }
                }
            }
            // 更新member_field（update_practice）
            if ($end > 0) {
                DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->update([
                    'practicenow' => $num - $end,
                    'practicetime' => $endtime !== [] ? min($endtime) : 0,
                ]);
                Redis::del('player:' . $uid);
            }
            return 1;
        });
    }

    /**
     * 更新训练状态（update_status：到期的清空训练位，未到期的把开始时间推到现在）
     *
     * @param array<string, mixed> $info 训练位
     * @return array{status: int, gid?: int, exp?: float, end?: int} status 1 成功；-1 更新失败；-3 训练位等级不对
     */
    public static function updateStatus(int $uid, array $info, int $memberLevel, int $now): array
    {
        $end = 0;
        if ($now >= (int)$info['endtime']) {
            $end = 1;
            // 当前时间大于等于结束时间
            $seconds = (int)$info['endtime'] - (int)$info['starttime'];
        } else {
            // 当前时间小于结束时间
            $seconds = $now - (int)$info['starttime'];
        }
        // 获取训练位等级
        $level = self::LEVEL_INFO[(int)$info['level']] ?? null;
        if ($level === null) {
            return ['status' => -3];
        }
        $addition = (int)$level['result'] / 100;
        // 主公等级 * 2250 / 24 = 主公等级 * 112.5 = 1小时经验
        $exp = ceil($memberLevel * ($seconds / 3600) * 112.5 * $addition);
        $data = $end ? ['type' => 0, 'generalid' => 0, 'starttime' => 0, 'endtime' => 0] : ['starttime' => $now];
        $affected = DB::table('practice')->where('uid', $uid)->where('id', (int)$info['id'])->whereNull('deleted_at')->update($data);
        if ($affected <= 0) {
            return ['status' => -1];
        }
        return ['status' => 1, 'gid' => (int)$info['gid'], 'exp' => $exp, 'end' => $end];
    }

    /**
     * 训练位升级规则（get_levelup_rule）
     *
     * @return array{status: int, info: array<string, mixed>} status 1 成功；-1 没有该训练位；2 训练位已满级；3 VIP 等级不足
     */
    public static function levelupRule(int $uid, int $pid, int $maxLevel): array
    {
        $own = self::own($uid, $pid);
        if ($own === null) {
            return ['status' => -1, 'info' => []];
        }
        // 下一级
        $level = $own['level'] + 1;
        if ($level > $maxLevel) {
            // vip等级不足 3
            return ['status' => 3, 'info' => []];
        }
        $result = self::LEVEL_INFO[$level] ?? null;
        if ($result === null) {
            // 训练位已满级 2
            return ['status' => 2, 'info' => []];
        }
        return ['status' => 1, 'info' => ['id' => $level] + $result + ['level' => $level]];
    }

    /**
     * 升级训练位（levelup）
     */
    public static function levelup(int $uid, int $pid, int $level): int
    {
        return DB::table('practice')->where('id', $pid)->where('uid', $uid)->whereNull('deleted_at')->update(['level' => $level]) > 0 ? 1 : 0;
    }

    /**
     * 终止训练（practice_stop：清空训练位；结束时间等于 member_field.practicetime 时改为其余训练中位置的最早结束时间，
     * practicenow > 0 时减 1。旧版算出的经验只放在返回值里，调用方都不发给将领，这里不算）
     *
     * @return int 1 成功；-5 更新扩展表失败；-4 训练位等级不对；-3 没有武将；-2 没有该训练位；-1 更新训练位失败
     */
    public static function practiceStop(int $uid, int $pid, int $practicetime, int $practicenow): int
    {
        $info = self::own($uid, $pid);
        if ($info === null) {
            return -2;
        }
        if ($info['gid'] === 0) {
            return -3;
        }
        $end = $info['endtime'];
        if (!isset(self::LEVEL_INFO[$info['level']])) {
            return -4;
        }
        $affected = DB::table('practice')->where('uid', $uid)->where('id', $pid)->whereNull('deleted_at')->update([
            'type' => 0,
            'generalid' => 0,
            'starttime' => 0,
            'endtime' => 0,
        ]);
        if ($affected <= 0) {
            return -1;
        }
        $result = [];
        if ($end === $practicetime) {
            // 取得训练中训练位信息
            $list = self::practiceList($uid, 1);
            $result['practicetime'] = $list['status'] === 1 ? min(array_column($list['list'], 'endtime')) : 0;
        }
        if ($practicenow > 0) {
            $result['practicenow'] = $practicenow - 1;
        }
        // 旧版 $result 为空时 CI 不执行 UPDATE，affected_rows 仍是上一条训练位更新的结果（> 0），视为成功
        if ($result !== [] && DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->update($result) === 0) {
            return -5;
        }
        Redis::del('player:' . $uid);
        return 1;
    }

    /**
     * 新手引导记录（guide_model->get_guide_info：没有时建档）
     *
     * @return array{leaptimes: int, clearcd: int}
     */
    public static function guideInfo(int $uid): array
    {
        DB::execute('INSERT IGNORE INTO `zc_sanguo_guide_log` (`uid`, `leaptimes`, `clearcd`) VALUES (:uid, 0, 0)', ['uid' => $uid]);
        $row = DB::table('guide_log')->select('leaptimes', 'clearcd')->where('uid', $uid)->whereNull('deleted_at')->first();
        return ['leaptimes' => (int)($row['leaptimes'] ?? 0), 'clearcd' => (int)($row['clearcd'] ?? 0)];
    }

    /**
     * 记录新手引导突飞次数（guide_model->add_leaptimes）
     */
    public static function addGuideLeaptimes(int $uid, int $times): void
    {
        DB::table('guide_log')->where('uid', $uid)->whereNull('deleted_at')->update(['leaptimes' => $times]);
    }

    /**
     * 武将突飞记录（general_model->add_leap_log）
     *
     * @param array<string, mixed> $before 突飞前的将领
     * @param array<string, mixed> $after 突飞后（level / exp / levelupexp）
     */
    public static function addLeapLog(int $uid, int $gid, int|float $exp, int $hit, array $before, array $after, int $now): void
    {
        $info = Member::logInfo($uid) ?? ['level' => 0, 'vip' => 0];
        DB::table('general_leap_log')->insert([
            'uid' => $uid,
            'vip' => $info['vip'],
            'level' => $info['level'],
            'gid' => $gid,
            'hit' => $hit,
            'addexp' => (int)$exp,
            'turn' => (int)$before['turn'],
            'glevel1' => (int)$before['level'],
            'gexp1' => (int)$before['exp'],
            'glevelupexp1' => (int)$before['levelupexp'],
            'glevel2' => (int)$after['level'],
            'gexp2' => (int)$after['exp'],
            'glevelupexp2' => (int)$after['levelupexp'],
            'dateline' => $now,
        ]);
    }

    /**
     * 训练位行：旧版字段名（generalid → gid）
     *
     * @param array<string, mixed> $row
     * @return array<string, int>
     */
    private static function row(array $row): array
    {
        return [
            'id' => (int)$row['id'],
            'uid' => (int)$row['uid'],
            'squence' => (int)$row['squence'],
            'level' => (int)$row['level'],
            'type' => (int)$row['type'],
            'gid' => (int)$row['generalid'],
            'starttime' => (int)$row['starttime'],
            'endtime' => (int)$row['endtime'],
        ];
    }
}
