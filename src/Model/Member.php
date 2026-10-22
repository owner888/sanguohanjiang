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
     * 根据 UID 快速查找主公记录 (自动过滤软删除)
     */
    public static function findByUid(int $uid): ?static
    {
        $row = static::query()->where('uid', $uid)->first();
        return $row ? static::fromArray($row) : null;
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
}
