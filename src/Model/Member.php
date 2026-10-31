<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Database\SoftDeletes;
use OmniPHP\Model;

/**
 * 主公玩家数据模型 (轻量薄模型，对标 Laravel / OmniPHP Model)
 *
 * 内聚玩家货币原子扣减/增加、等级经验流转与会话缓存清理。
 */
class Member extends Model
{
    use SoftDeletes;

    protected string $table = 'member';
    protected string $primaryKey = 'uid';

    protected array $casts = [
        'uid' => 'int',
        'sex' => 'int',
        'gender' => 'int',
        'roleid' => 'int',
        'country' => 'int',
        'level' => 'int',
        'exp' => 'int',
        'maxexp' => 'int',
        'silver' => 'int',
        'gold' => 'int',
        'reputation' => 'int',
        'soul' => 'int',
        'souluse' => 'int',
        'soulpack' => 'int',
        'jade' => 'int',
        'gem' => 'int',
        'gemuse' => 'int',
        'refinestone' => 'int',
        'colorstone' => 'int',
        'bloodstone' => 'int',
        'act' => 'int',
        'maxact' => 'int',
        'vip' => 'int',
        'bagnum' => 'int',
        'guide' => 'int',
        'guidestep' => 'int',
        'guidestop' => 'int',
        'missionsite' => 'int',
        'missionlevel' => 'int',
        'missionstage' => 'int',
        'missionid' => 'int',
    ];

    /**
     * 原版经典主公/武将升级经验公式 (80 * 等级^2)
     */
    public static function getLevelUpExp(int $level): int
    {
        return max(80, 80 * $level * $level);
    }

    /**
     * 原版 VIP 军令上限公式 (VIP0:50, VIP1:60, VIP2:70, VIP3:80, VIP4:90, VIP5+:100)
     */
    public static function getActLimit(int $vip): int
    {
        $limits = [
            0 => 50,
            1 => 60,
            2 => 70,
            3 => 80,
            4 => 90,
            5 => 100,
        ];
        return $limits[min(5, max(0, $vip))];
    }

    /**
     * 根据 UID 快速查找主公记录 (自动过滤软删除)
     */
    public static function findByUid(int $uid): ?static
    {
        $row = static::query()->where('uid', $uid)->first();
        return $row ? static::fromArray($row) : null;
    }

    /**
     * 每日计划任务：玩家当天首次请求时执行一次（移植自旧版 member_model->daily_cron）
     *
     * 以 member_field.dateline 原子抢占当日执行权，多 Worker 并发首请求只会执行一次。
     * member_field 不存在时不执行（旧版该行注册即建）。
     */
    public static function dailyCron(int $uid, int $now): void
    {
        // 每日重置用户状态：soultimes 将魂星路今日购买次数归零
        $affected = DB::execute(
            'UPDATE `zc_sanguo_member_field` SET `dateline` = :now, `soultimes` = 0
             WHERE `uid` = :uid AND `deleted_at` IS NULL AND `dateline` < :today',
            ['now' => $now, 'uid' => $uid, 'today' => strtotime(date('Y-m-d', $now))]
        );
        if ($affected === 0) {
            return;
        }

        // 依赖未移植：member_field 每日计数归零（leaptimes / baijintimes / zhizuntimes /
        // stone1~3_times / treasure_free，新版 member_field 尚无这些字段）

        // update_activity_info：金银岛挑战次数归零、将魂星路免费次数重置为 10、多人副本次数重置为 10、
        // 通天塔免费次数重置为 10（没有记录时不插入，同旧版）
        MemberActivity::update($uid, ['islandtimes' => 0, 'freetower' => 10, 'elitetimes' => 10, 'freeheaven' => 10]);
        // 依赖未移植：update_activity_info 的其余字段（sacrifice1~3 / freebusiness /
        // freemooncake / task_number / flop* / generaltasktimes，新版 member_activity 尚无这些字段）
        // 依赖未移植：lottery_model->update_day、logined_model->update_day 连续登录计数
        // 依赖未移植：member.dateline / version / appversion、insert_member_log 每日日志

        // 清空昨日贡献次数/学艺次数（member_model->clear_donate）
        DB::table('member_country')->where('uid', $uid)->whereNull('deleted_at')->update([
            'goldtimes' => 0,
            'silvertimes' => 0,
            'learntimes' => 0,
        ]);
    }

    /**
     * 原子安全扣除玩家货币/资源 (带余额防超扣安全防御)
     *
     * 底层采用行级原子锁 WHERE silver >= :req_silver，彻底杜绝并发超卖与重放刷钱
     */
    public static function deductCurrency(int $uid, int $silver = 0, int $gold = 0, int $act = 0): bool
    {
        if ($silver < 0 || $gold < 0 || $act < 0) {
            return false;
        }

        if ($silver === 0 && $gold === 0 && $act === 0) {
            return true;
        }

        $sql = "UPDATE `zc_sanguo_member` 
                SET `silver` = `silver` - :sub_silver,
                    `gold` = `gold` - :sub_gold,
                    `act` = `act` - :sub_act
                WHERE `uid` = :uid 
                  AND `silver` >= :req_silver 
                  AND `gold` >= :req_gold 
                  AND `act` >= :req_act
                  AND `deleted_at` IS NULL";

        $affected = DB::execute($sql, [
            'sub_silver' => $silver,
            'sub_gold' => $gold,
            'sub_act' => $act,
            'uid' => $uid,
            'req_silver' => $silver,
            'req_gold' => $gold,
            'req_act' => $act,
        ]);

        if ($affected > 0) {
            Redis::del('player:' . $uid);
            return true;
        }

        return false;
    }

    /**
     * 原子增加玩家货币/资源
     */
    public static function addCurrency(
        int $uid,
        int $silver = 0,
        int $gold = 0,
        int $act = 0,
        int $reputation = 0,
        int $soul = 0
    ): void {
        $sql = "UPDATE `zc_sanguo_member` 
                SET `silver` = `silver` + :add_silver,
                    `gold` = `gold` + :add_gold,
                    `act` = `act` + :add_act,
                    `reputation` = `reputation` + :add_rep,
                    `soul` = `soul` + :add_soul
                WHERE `uid` = :uid
                  AND `deleted_at` IS NULL";

        DB::execute($sql, [
            'add_silver' => max(0, $silver),
            'add_gold' => max(0, $gold),
            'add_act' => max(0, $act),
            'add_rep' => max(0, $reputation),
            'add_soul' => max(0, $soul),
            'uid' => $uid,
        ]);

        Redis::del('player:' . $uid);
    }

    /**
     * 以玩家行锁串行化同一玩家的物品读改写（多 Worker 安全）
     *
     * 在事务里先对 member 行加 FOR UPDATE，再执行 $callback；调用方已在事务中时沿用调用方的事务
     * （行锁持有到调用方提交），否则自开事务。用于没有唯一键可做 upsert 的多行物品存储
     * （装备碎片 / 天赋卷轴 / 装备精华、坐骑碎片、酒馆宝石首行）。
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public static function locked(int $uid, callable $callback): mixed
    {
        $run = static function () use ($uid, $callback): mixed {
            DB::query('SELECT `uid` FROM `zc_sanguo_member` WHERE `uid` = :uid FOR UPDATE', ['uid' => $uid]);
            return $callback();
        };
        return DB::inTransaction() ? $run() : DB::transaction($run);
    }

    /**
     * 流水表需要的玩家等级与 VIP（旧版各 _xxx_log 记 $this->member 的 level / vip）；玩家不存在返回 null
     *
     * @return array{level: int, vip: int}|null
     */
    public static function logInfo(int $uid): ?array
    {
        $row = DB::table('member')->select('level', 'vip')->where('uid', $uid)->whereNull('deleted_at')->first();
        return $row === null ? null : ['level' => (int)$row['level'], 'vip' => (int)$row['vip']];
    }

    /**
     * member 上的计数列原子增加，返回增加后的值；玩家不存在返回 null
     *
     * 用 LAST_INSERT_ID(expr) 在同一条 UPDATE 里取回新值，余额与并发写入无竞争。$column 只接受本类调用方的列名常量。
     */
    public static function incrementColumn(int $uid, string $column, int $num): ?int
    {
        $col = self::counterColumn($column);
        $affected = DB::execute(
            "UPDATE `zc_sanguo_member` SET `{$col}` = LAST_INSERT_ID(`{$col}` + :num) WHERE `uid` = :uid AND `deleted_at` IS NULL",
            ['num' => $num, 'uid' => $uid]
        );
        if ($affected === 0) {
            return null;
        }
        Redis::del('player:' . $uid);
        return (int)DB::query('SELECT LAST_INSERT_ID() AS `v`')[0]['v'];
    }

    /**
     * member 上的计数列原子减少（余额不足不扣），返回减少后的值；余额不足或玩家不存在返回 null
     *
     * 条件 UPDATE（列 >= 数量）防并发超扣。
     */
    public static function decrementColumn(int $uid, string $column, int $num): ?int
    {
        $col = self::counterColumn($column);
        $affected = DB::execute(
            "UPDATE `zc_sanguo_member` SET `{$col}` = LAST_INSERT_ID(`{$col}` - :num)
             WHERE `uid` = :uid AND `deleted_at` IS NULL AND `{$col}` >= :need",
            ['num' => $num, 'uid' => $uid, 'need' => $num]
        );
        if ($affected === 0) {
            return null;
        }
        Redis::del('player:' . $uid);
        return (int)DB::query('SELECT LAST_INSERT_ID() AS `v`')[0]['v'];
    }

    /**
     * member 上的计数列原子减少，不足时减到 0（旧版无下限检查的减法写进无符号列，MySQL 非严格模式截到 0），返回减少后的值；
     * 玩家不存在返回 null
     */
    public static function decrementColumnFloor(int $uid, string $column, int $num): ?int
    {
        $col = self::counterColumn($column);
        $affected = DB::execute(
            "UPDATE `zc_sanguo_member` SET `{$col}` = LAST_INSERT_ID(IF(`{$col}` > :num, `{$col}` - :num2, 0))
             WHERE `uid` = :uid AND `deleted_at` IS NULL",
            ['num' => $num, 'num2' => $num, 'uid' => $uid]
        );
        if ($affected === 0 && self::logInfo($uid) === null) {
            return null;
        }
        Redis::del('player:' . $uid);
        return $affected === 0
            ? (int)DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value($col)
            : (int)DB::query('SELECT LAST_INSERT_ID() AS `v`')[0]['v'];
    }

    /**
     * 流水表的 class / method 列值：旧版列宽较窄（多为 VARCHAR(20)），旧版库非严格模式写入时截断，这里照样截到列宽
     */
    public static function logText(string $value, int $length): string
    {
        return mb_substr($value, 0, $length);
    }

    private static function counterColumn(string $column): string
    {
        return match ($column) {
            'jade', 'gem', 'gemuse', 'refinestone', 'colorstone', 'bloodstone', 'souluse', 'soulpack' => $column,
            default => throw new \InvalidArgumentException("member 计数列不支持：{$column}"),
        };
    }
}
