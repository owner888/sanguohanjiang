<?php

declare(strict_types=1);

namespace Hanjiang\Handler\City;

use OmniPHP\Http\Context;
use OmniPHP\Database\DB;
use OmniPHP\Cache\Redis;

final class MemberHandler
{
    /**
     * 根据请求上下文解析当前真实 uid
     */
    public static function resolveUid(Context $ctx): int
    {
        $token = trim((string)$ctx->input('token', ''));
        if ($token !== '') {
            $cachedUid = Redis::get('token:' . $token);
            if ($cachedUid !== null && (int)$cachedUid > 0) {
                return (int)$cachedUid;
            }
        }

        $inputUid = (int)$ctx->input('uid', 0);
        if ($inputUid > 0) {
            return $inputUid;
        }

        // 回退到最新活跃用户
        $latest = DB::table('member')->orderBy('uid', 'DESC')->first();
        return $latest !== null ? (int)$latest['uid'] : 10001;
    }

    /**
     * 玩家主信息接口: c=member&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = self::resolveUid($ctx);

        // 1. 优先尝试从 Redis 缓存读取
        $cacheKey = 'player:' . $uid;
        $cached = Redis::getJson($cacheKey);
        if (is_array($cached)) {
            $cached['timestamp'] = time();
            return $cached;
        }

        // 2. 从 MySQL 数据库读取
        $member = DB::table('member')->where('uid', $uid)->first();
        $field = DB::table('member_field')->where('uid', $uid)->first() ?? [];

        if ($member === null) {
            // 如果不存在则初始化一份
            $now = time();
            $username = 'player_' . $uid;
            DB::table('member')->insert([
                'uid' => $uid,
                'username' => $username,
                'nickname' => '新主公' . $uid,
                'sex' => 1,
                'gender' => 0,
                'roleid' => 1,
                'country' => 1,
                'level' => 1,
                'exp' => 0,
                'maxexp' => 100,
                'silver' => 88888,
                'gold' => 9999,
                'reputation' => 100,
                'act' => 100,
                'maxact' => 100,
                'vip' => 1,
                'bagnum' => 20,
                'guide' => 0,
                'guidestep' => 0,
                'guidestop' => 0,
                'missionsite' => 1,
                'missionlevel' => 1,
                'missionstage' => 1,
                'missionid' => 1,
            ]);
            $member = DB::table('member')->where('uid', $uid)->first();
        }

        $now = time();
        $data = [
            'status' => 1,
            'uid' => $uid,
            'username' => $member['username'],
            'nickname' => $member['nickname'],
            'sex' => (int)$member['sex'],
            'gender' => (int)$member['gender'],
            'roleid' => (int)$member['roleid'],
            'country' => (int)$member['country'],
            'Level' => (int)$member['level'],
            'level' => (int)$member['level'],
            'silver' => (int)$member['silver'],
            'gold' => (int)$member['gold'],
            'reputation' => (int)$member['reputation'],
            'act' => (int)$member['act'],
            'maxact' => (int)$member['maxact'],
            'vip' => (int)$member['vip'],
            'exp' => (int)$member['exp'],
            'maxexp' => (int)$member['maxexp'],
            'bagnum' => (int)$member['bagnum'],
            'guide' => (int)$member['guide'],
            'guidestep' => (int)$member['guidestep'],
            'guidestop' => (int)$member['guidestop'],
            'missionsite' => (int)$member['missionsite'],
            'missionlevel' => (int)$member['missionlevel'],
            'missionstage' => (int)$member['missionstage'],
            'mainquest' => [],
            'attackcd' => [
                'status' => (int)($field['attackstatus'] ?? 0),
                'cd' => 0,
            ],
            'levy' => [
                'times' => (int)($field['levytimes'] ?? 50),
                'cd' => 0,
                'status' => 0,
            ],
            'practice' => [
                'timers' => (int)($field['practicetime'] ?? 0),
            ],
            'rank' => (int)($field['rank'] ?? 1),
            'newresult' => (int)($field['newresult'] ?? 0),
            'gift' => (int)($field['gift'] ?? 0),
            'race' => (int)($field['race'] ?? 0),
            'race_dateline' => (int)($field['race_dateline'] ?? 0),
            'new_role_reward' => 0,
            'opennewyearboss' => 0,
            'daily_task' => [
                'status' => 0,
                'number' => 0,
                'plan' => 0,
                'value' => 0,
                'type' => 0,
            ],
            'appreview' => 0,
            'serverhold' => '',
            'onmine' => 0,
            'oncountrymine' => 0,
            'onworkshop' => 0,
            'god_reward' => 0,
            'country_war' => 0,
            'is_saturday' => 0,
            'country_date' => '',
            'enemy_name' => '',
            'country_lock' => 0,
            'leave_cd' => 0,
            'country_job' => 0,
            'trends_id' => 0,
            'notice_id' => 0,
            'hitegg' => 0,
            'timestamp' => $now,
        ];

        // 存入 Redis 缓存，TTL 1小时
        Redis::setJson($cacheKey, $data, 3600);

        return $data;
    }

    /**
     * 选角/创角: c=member&m=select_role
     */
    public static function select_role(Context $ctx): array
    {
        $uid = self::resolveUid($ctx);
        $sex = (int)($ctx->input('sex', 1));
        $nickname = trim((string)($ctx->input('name', $ctx->input('nickname', '天命主公'))));

        // 更新 MySQL 数据库
        DB::table('member')->where('uid', $uid)->update([
            'gender' => $sex > 0 ? $sex : 1,
            'sex' => $sex > 0 ? $sex : 1,
            'nickname' => $nickname,
        ]);

        // 删除旧 Redis 缓存，强制下次刷新
        Redis::del('player:' . $uid);

        return [
            'status' => 1,
            'message' => '角色创建成功',
            'roleid' => 1,
            'nickname' => $nickname,
            'Level' => 1,
        ];
    }

    /**
     * 获取新手引导步数: c=member&m=get_guidestep
     */
    public static function get_guidestep(Context $ctx): array
    {
        $uid = self::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->first();

        return [
            'status' => 1,
            'step' => (int)($member['guidestep'] ?? 0),
            'stop' => (int)($member['guidestop'] ?? 0),
        ];
    }

    /**
     * 更新新手引导步数: c=member&m=updateguidestep
     */
    public static function updateguidestep(Context $ctx): array
    {
        $uid = self::resolveUid($ctx);
        $step = (int)($ctx->input('step', 0));

        DB::table('member')->where('uid', $uid)->update([
            'guidestep' => $step,
        ]);

        // 同步清理 Redis 缓存
        Redis::del('player:' . $uid);

        return [
            'status' => 1,
            'step' => $step,
        ];
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
                  AND `act` >= :req_act";

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
    public static function addCurrency(int $uid, int $silver = 0, int $gold = 0, int $act = 0, int $reputation = 0, int $soul = 0): void
    {
        $sql = "UPDATE `zc_sanguo_member` 
                SET `silver` = `silver` + :add_silver,
                    `gold` = `gold` + :add_gold,
                    `act` = `act` + :add_act,
                    `reputation` = `reputation` + :add_rep,
                    `soul` = `soul` + :add_soul
                WHERE `uid` = :uid";

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
