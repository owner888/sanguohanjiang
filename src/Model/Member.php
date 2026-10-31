<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use Hanjiang\Handler\Pvp\ArenaHandler;
use Hanjiang\Handler\Pvp\CompeteHandler;
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
        // 每日重置用户状态：今日突飞次数 leaptimes、白金 / 至尊培养次数 baijintimes / zhizuntimes、
        // 将魂星路今日购买次数 soultimes 归零
        $affected = DB::execute(
            'UPDATE `zc_sanguo_member_field` SET `dateline` = :now, `leaptimes` = 0, `baijintimes` = 0, `zhizuntimes` = 0, `soultimes` = 0
             WHERE `uid` = :uid AND `deleted_at` IS NULL AND `dateline` < :today',
            ['now' => $now, 'uid' => $uid, 'today' => strtotime(date('Y-m-d', $now))]
        );
        if ($affected === 0) {
            return;
        }

        // 依赖未移植：member_field 每日计数（stone1~3_times / treasure_free，赌石 / 寻宝，新版 member_field 尚无这些字段）

        // update_activity_info：金银岛挑战次数归零、将魂星路免费次数重置为 10、多人副本次数重置为 10、
        // 神将之门挑战次数重置为 10、通天塔免费次数重置为 10、日常任务次数重置为 6（没有记录时不插入，同旧版）
        MemberActivity::update($uid, [
            'islandtimes' => 0,
            'freetower' => 10,
            'elitetimes' => 10,
            'generaltasktimes' => 10,
            'freeheaven' => 10,
            'task_number' => DailyTask::DAILY_NUMBER,
        ]);
        // 依赖未移植：update_activity_info 的其余字段（sacrifice1~3 / freebusiness /
        // freemooncake / flop*，新版 member_activity 尚无这些字段）
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
     * 扣除玩家货币（移植旧版 member_model->sub_silver / sub_gold / sub_act，几种一起扣时全部够才扣）
     *
     * 条件 UPDATE（余额 >= 数量）防并发超扣；扣成功后在同一事务里按扣后余额记流水（FlowLog）：
     * - 元宝：gold_log（type 2）；主公 41 级以上时日常任务 5（元宝消费）task_action(5, $goldType)
     *   （旧版 $type：1 正常消耗，百次祭祖传 100）；
     * - 银币：silver_log（type 2）——旧版 sub_silver 只给 VIP 玩家记流水，VIP 0 不记，照搬；
     * - 军令：act_log（type 2）。
     * 依赖未移植：sub_gold / sub_act 的翻牌活动计数（flop，member_activity.flop*，随翻牌活动移植；旧版数据中 flop 已于
     * 2016-12-31 结束）；sub_gold 国庆节流水 national_day_gold_log（节日活动）。
     */
    public static function deductCurrency(int $uid, int $silver = 0, int $gold = 0, int $act = 0, int $goldType = 1): bool
    {
        if ($silver < 0 || $gold < 0 || $act < 0) {
            return false;
        }

        if ($silver === 0 && $gold === 0 && $act === 0) {
            return true;
        }

        $run = static function () use ($uid, $silver, $gold, $act): ?array {
            $affected = DB::execute(
                'UPDATE `zc_sanguo_member`
                 SET `silver` = `silver` - :sub_silver, `gold` = `gold` - :sub_gold, `act` = `act` - :sub_act
                 WHERE `uid` = :uid AND `silver` >= :req_silver AND `gold` >= :req_gold AND `act` >= :req_act
                   AND `deleted_at` IS NULL',
                [
                    'sub_silver' => $silver,
                    'sub_gold' => $gold,
                    'sub_act' => $act,
                    'uid' => $uid,
                    'req_silver' => $silver,
                    'req_gold' => $gold,
                    'req_act' => $act,
                ]
            );
            if ($affected === 0) {
                return null;
            }
            $row = self::balances($uid);
            $now = time();
            if ($gold > 0) {
                FlowLog::write('gold_log', $uid, FlowLog::TYPE_SUB, $gold, $row['gold'], $row, $now);
            }
            if ($silver > 0 && $row['vip'] > 0) {
                FlowLog::write('silver_log', $uid, FlowLog::TYPE_SUB, $silver, $row['silver'], $row, $now);
            }
            if ($act > 0) {
                FlowLog::write('act_log', $uid, FlowLog::TYPE_SUB, $act, $row['act'], $row, $now);
            }
            return $row;
        };
        $row = DB::inTransaction() ? $run() : DB::transaction($run);
        if ($row === null) {
            return false;
        }
        Redis::del('player:' . $uid);

        // 日常任务（5 是元宝消费对应的类型）
        if ($gold > 0 && $row['level'] >= DailyTask::OPEN_LEVEL) {
            DailyTask::action($uid, DailyTask::TYPE_GOLD, $goldType);
        }
        return true;
    }

    /**
     * 增加玩家货币 / 资源（移植旧版 add_silver / add_gold / add_act(1, n) / add_reputation / add_soul）
     *
     * 单条 UPDATE 原子增加；数量大于 0 的每一种在同一事务里按增加后的余额记一条流水（type 1）：
     * silver_log / gold_log / act_log / reputation_log / usesoul_log。
     */
    public static function addCurrency(
        int $uid,
        int $silver = 0,
        int $gold = 0,
        int $act = 0,
        int $reputation = 0,
        int $soul = 0
    ): void {
        $add = [
            'silver' => max(0, $silver),
            'gold' => max(0, $gold),
            'act' => max(0, $act),
            'reputation' => max(0, $reputation),
            'soul' => max(0, $soul),
        ];
        if (array_sum($add) === 0) {
            return;
        }
        $run = static function () use ($uid, $add): void {
            $affected = DB::execute(
                'UPDATE `zc_sanguo_member`
                 SET `silver` = `silver` + :add_silver, `gold` = `gold` + :add_gold, `act` = `act` + :add_act,
                     `reputation` = `reputation` + :add_rep, `soul` = `soul` + :add_soul
                 WHERE `uid` = :uid AND `deleted_at` IS NULL',
                [
                    'add_silver' => $add['silver'],
                    'add_gold' => $add['gold'],
                    'add_act' => $add['act'],
                    'add_rep' => $add['reputation'],
                    'add_soul' => $add['soul'],
                    'uid' => $uid,
                ]
            );
            if ($affected === 0) {
                return;
            }
            $row = self::balances($uid);
            $now = time();
            $tables = ['silver' => 'silver_log', 'gold' => 'gold_log', 'act' => 'act_log', 'reputation' => 'reputation_log', 'soul' => 'usesoul_log'];
            foreach ($tables as $column => $table) {
                if ($add[$column] > 0) {
                    FlowLog::write($table, $uid, FlowLog::TYPE_ADD, $add[$column], $row[$column], $row, $now);
                }
            }
        };
        DB::inTransaction() ? $run() : DB::transaction($run);

        Redis::del('player:' . $uid);
    }

    /**
     * 主公加经验（移植 member_model->add_exp + check_levelup）
     *
     * 升级所需经验 80 × 等级²（getLevelUpExp），够了就升一级、扣掉这一级的经验，直到不够为止（不封顶）；
     * 每升一级加 2 军令（check_levelup 里 add_act(1) 默认 2 个，记 act_log type 1，流水等级为升级前的等级）。
     * 升级时写 level 与 maxexp（旧版 levelupexp 列），不论升级都写 exp。升到 26 级（最终等级正好是 26）时进入演武榜
     * 与群雄逐鹿（arena_model / compete_model->get_info_by_id）。
     * 依赖未移植：等级礼包标识 level_gift（levelgift_model，新版 c=levelgift 是自定玩法）；
     * 旧版砸蛋 hitegg_model->get_hitegg_info 的条件是「开启等级 1 >= 主公等级」，升级后主公至少 2 级，永不成立，不移植。
     *
     * 在玩家行锁里读写（多 Worker 安全）。
     *
     * @return array{status: int, levelup: int, exp?: int, level?: int, levelupexp?: int} status 1 成功，-1 玩家不存在
     */
    public static function addExp(int $uid, int $exp, ?int $now = null): array
    {
        $now ??= time();
        $result = self::locked($uid, static function () use ($uid, $exp, $now): ?array {
            $row = DB::query(
                'SELECT `uid`, `nickname`, `level`, `exp`, `act`, `vip` FROM `zc_sanguo_member` WHERE `uid` = :uid AND `deleted_at` IS NULL',
                ['uid' => $uid]
            )[0] ?? null;
            if ($row === null) {
                return null;
            }
            $oldLevel = (int)$row['level'];
            // 验证升级
            $level = $oldLevel;
            $left = (int)$row['exp'] + $exp;
            $levelupexp = self::getLevelUpExp($level);
            $ups = 0;
            while ($left >= $levelupexp) {
                $left -= $levelupexp;
                $level++;
                $ups++;
                $levelupexp = self::getLevelUpExp($level);
            }
            $data = ['exp' => $left];
            if ($level > $oldLevel) {
                $data['level'] = $level;
                $data['maxexp'] = $levelupexp;
                // 升级加军令（每级 2 个）
                $data['act'] = (int)$row['act'] + 2 * $ups;
            }
            DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->update($data);
            $act = (int)$row['act'];
            for ($i = 0; $i < $ups; $i++) {
                $act += 2;
                FlowLog::write('act_log', $uid, FlowLog::TYPE_ADD, 2, $act, ['level' => $oldLevel, 'vip' => (int)$row['vip']], $now);
            }
            return $row + ['new_level' => $level, 'new_exp' => $left, 'levelupexp' => $levelupexp];
        });
        if ($result === null) {
            return ['status' => -1, 'levelup' => 0];
        }
        Redis::del('player:' . $uid);
        $levelup = $result['new_level'] > (int)$result['level'] ? 1 : 0;
        // 升级到26级，进入演武榜、群雄逐鹿
        if ($levelup === 1 && $result['new_level'] === ArenaHandler::OPEN_LEVEL) {
            $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
            if ($member !== null) {
                ArenaHandler::getInfoById($member, $now);
                CompeteHandler::getInfoById($member, $now);
            }
        }
        return [
            'status' => 1,
            'levelup' => $levelup,
            'exp' => $result['new_exp'],
            'level' => $result['new_level'],
            'levelupexp' => $result['levelupexp'],
        ];
    }

    /**
     * 增加声望（移植 add_reputation：写库成功时记 reputation_log type 1）
     *
     * @return int 增加后的声望；玩家不存在返回 -1
     */
    public static function addReputation(int $uid, int $reputation): int
    {
        if ($reputation <= 0) {
            $info = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('reputation');
            return $info === null ? -1 : (int)$info;
        }
        $balance = self::incrementColumn($uid, 'reputation', $reputation);
        if ($balance === null) {
            return -1;
        }
        FlowLog::write('reputation_log', $uid, FlowLog::TYPE_ADD, $reputation, $balance, self::logInfo($uid) ?? ['level' => 0, 'vip' => 0]);
        return $balance;
    }

    /**
     * 消耗声望（移植 sub_reputation：数量为 0 直接成功；不足不扣；写库成功时记 reputation_log type 2）
     *
     * @return int|null 扣除后的声望；声望不足或玩家不存在返回 null
     */
    public static function subReputation(int $uid, int $reputation): ?int
    {
        if ($reputation <= 0) {
            $info = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('reputation');
            return $info === null ? null : (int)$info;
        }
        $balance = self::decrementColumn($uid, 'reputation', $reputation);
        if ($balance === null) {
            return null;
        }
        FlowLog::write('reputation_log', $uid, FlowLog::TYPE_SUB, $reputation, $balance, self::logInfo($uid) ?? ['level' => 0, 'vip' => 0]);
        return $balance;
    }

    /**
     * 写库后的余额与等级 / VIP（在写库的事务里加锁读，读到的就是本次写入后的值）
     *
     * @return array{silver: int, gold: int, act: int, reputation: int, soul: int, level: int, vip: int}
     */
    private static function balances(int $uid): array
    {
        $row = DB::query(
            'SELECT `silver`, `gold`, `act`, `reputation`, `soul`, `level`, `vip` FROM `zc_sanguo_member` WHERE `uid` = :uid FOR UPDATE',
            ['uid' => $uid]
        )[0];
        return array_map('intval', $row);
    }

    /**
     * 以玩家行锁串行化同一玩家的物品读改写（多 Worker 安全）
     *
     * 在事务里先对 member 行加 FOR UPDATE，再执行 $callback；调用方已在事务中时沿用调用方的事务
     * （行锁持有到调用方提交），否则自开事务。用于没有唯一键可做 upsert 的多行物品存储
     * （装备碎片 / 天赋卷轴 / 装备精华、坐骑碎片、酒馆宝石首行）。
     *
     * Fiber 事件循环下的不变式：事务开在本进程唯一的一条 PDO 连接上，PDO / phpredis 都是同步调用，
     * $callback 执行期间当前 Fiber 不会挂起，同进程的其他请求插不进这个事务。若以后引入会挂起 Fiber 的
     * 异步 IO（协程 MySQL 客户端、Timer::sleep 等），必须改成每个请求独占连接（连接池），否则别的请求的 SQL 会落进本事务。
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
            'jade', 'gem', 'gemuse', 'refinestone', 'colorstone', 'bloodstone', 'soul', 'souluse', 'soulpack', 'reputation' => $column,
            default => throw new \InvalidArgumentException("member 计数列不支持：{$column}"),
        };
    }
}
