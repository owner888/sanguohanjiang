<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Database\SoftDeletes;
use OmniPHP\Model;

/**
 * 首充返利日志（旧版 zc_sanguo_first_recharge_log，每个玩家一行）
 *
 * gold 为首次充值的元宝数，status 为已领取的首充档位 id（0 未领取），lottery 为天命转盘是否已抽（0/1）。
 */
class FirstRechargeLog extends Model
{
    use SoftDeletes;

    protected string $table = 'first_recharge_log';
    protected string $primaryKey = 'id';

    protected array $casts = [
        'id' => 'int',
        'uid' => 'int',
        'gold' => 'int',
        'status' => 'int',
        'lottery' => 'int',
        'dateline' => 'int',
    ];

    /**
     * 首次充值时写入首充日志（移植旧版 models/vip_model.php:first_recharge）
     *
     * 只记第一次：gold = 充值金额 * 10；写入成功后 member_field.first_recharge 置为 2（充值 >= 6 时，
     * 客户端据此弹出领奖），否则置 0。已有日志时不做任何事。
     * 由充值核心 Model\Pay::complete → Model\Vip::pay 在玩家行锁事务里调用（旧版 controllers/pay.php 各渠道回调 → vip_model->pay）。
     *
     * @param int $addMoney 充值金额（旧版参数名 $add_gold，实为人民币元）
     * @return bool 是否写入了新日志
     */
    public static function recordRecharge(int $uid, int $addMoney): bool
    {
        $exists = static::query()->where('uid', $uid)->whereNull('deleted_at')->first();
        if ($exists !== null) {
            return false;
        }

        static::query()->insert([
            'uid' => $uid,
            'gold' => $addMoney * 10,
            'dateline' => time(),
        ]);

        // 旧版此处用 $this->member['uid']（充值回调里即当前玩家）更新 first_recharge / first_recharge_an
        // （first_recharge_an 为安卓客户端用的同一标识，新版 member_field 无此字段，主城接口恒回 0）
        DB::table('member_field')->where('uid', $uid)->update(['first_recharge' => $addMoney >= 6 ? 2 : 0]);
        Redis::del('player:' . $uid);
        return true;
    }
}
