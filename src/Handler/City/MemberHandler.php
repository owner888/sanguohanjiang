<?php

declare(strict_types=1);

namespace Hanjiang\Handler\City;

use Hanjiang\Handler\Pvp\SiegeHandler;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\General;
use Hanjiang\Model\OwnMatrix;
use Hanjiang\Model\Member;
use Hanjiang\Model\WorldBoss;
use OmniPHP\Http\Context;
use OmniPHP\Database\DB;
use OmniPHP\Cache\Redis;

final class MemberHandler
{
    /**
     * 根据请求 token 解析当前登录 uid（对齐旧版 token_model->check_token）
     *
     * 只认登录时签发并写入 Redis 的 token；无 token / token 失效返回 0，
     * 不接受请求里的 uid 参数，也不从 token 字面解析 uid（二者都可伪造）。
     */
    public static function resolveUid(Context $ctx): int
    {
        $token = trim((string)$ctx->input('token', ''));
        if ($token === '') {
            return 0;
        }
        $uid = Redis::get('token:' . $token);
        return $uid !== null && (int)$uid > 0 ? (int)$uid : 0;
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
            // 世界 BOSS 开启 / 领奖、国战、国家矿可收获标识随时间变化，不走缓存
            return array_merge($cached, self::liveInfo($uid, (int)($cached['level'] ?? 0), (int)($cached['country'] ?? 0)));
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
                'country' => 0,
                'level' => 1,
                'exp' => 0,
                'maxexp' => 80,
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
                'missionsite' => 2,
                'missionlevel' => 1,
                'missionstage' => 1,
                'missionid' => 1,
            ]);
            $member = DB::table('member')->where('uid', $uid)->first();
        }

        $now = time();
        $level = max(1, (int)$member['level']);
        $vip = max(0, (int)$member['vip']);
        $levelUpExp = (int)($member['maxexp'] ?? 0) > 0 ? (int)$member['maxexp'] : Member::getLevelUpExp($level);
        $actLimit = Member::getActLimit($vip);

        $data = [
            'status' => 1,
            'uid' => $uid,
            'username' => $member['username'],
            'nickname' => $member['nickname'],
            'sex' => (int)$member['sex'],
            'gender' => (int)$member['gender'],
            'roleid' => (int)$member['roleid'],
            'country' => (int)$member['country'],
            'Level' => $level,
            'level' => $level,
            'silver' => (int)$member['silver'],
            'gold' => (int)$member['gold'],
            'reputation' => (int)$member['reputation'],
            'act' => (int)$member['act'],
            'act_limit' => $actLimit,
            'maxact' => $actLimit,
            'vip' => $vip,
            'exp' => (int)$member['exp'],
            'levelupexp' => $levelUpExp,
            'maxexp' => $levelUpExp,
            'bagnum' => (int)$member['bagnum'],
            'guide' => (int)$member['guide'],
            'guidestep' => (int)$member['guidestep'],
            'guidestop' => (int)$member['guidestop'],
            'missionsite' => (int)$member['missionsite'],
            'missionlevel' => (int)$member['missionlevel'],
            'missionstage' => (int)$member['missionstage'],
            'missionid' => (int)$member['missionid'],
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
                'timers' => (function() use ($field, $uid): int {
                    $practiceAll = max(2, (int)($field['practiceall'] ?? 2));
                    $practicingCount = DB::table('practice')->where('uid', $uid)->where('generalid', '>', 0)->whereNull('deleted_at')->count();
                    if ($practiceAll > $practicingCount) {
                        return 0; // 尚有空闲训练位，显示“可训练”，不倒计时
                    }
                    return max(0, (int)($field['practicetime'] ?? 0) - time()); // 剩余倒计时秒数
                })(),
            ],
            'rank' => (int)($field['rank'] ?? 0),
            'newresult' => (int)($field['newresult'] ?? 0),
            'gift' => (int)($field['gift'] ?? 0),
            'race' => (int)($field['race'] ?? 0),
            'race_dateline' => (int)($field['race_dateline'] ?? 0),
            // 首充图标（旧版 member.php：1 显示未领奖 / 未抽转盘，2 充值后弹领奖，0 不显示；强制引导期间有图标时为 3）
            'first_recharge' => (int)$member['guidestop'] === 0 && (int)($field['first_recharge'] ?? 1) > 0
                ? 3
                : (int)($field['first_recharge'] ?? 1),
            'first_recharge_an' => 0,
            'new_role_reward' => 0,
            'opennewyearboss' => 0,
            // 世界 BOSS 开启状态（旧版 member.php:worldboss_init）
            'openboss' => 0,
            'openbossreward' => 0,
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

        return array_merge($data, self::liveInfo($uid, $level, (int)$member['country']));
    }

    /**
     * 随时间变化的主城标识（不进玩家缓存）：世界 BOSS（worldboss_init）、国战提示（get_country_war）、
     * 国家矿可收获 oncountrymine（member_field.oncountrymine 到期）
     *
     * @return array<string, mixed>
     */
    private static function liveInfo(int $uid, int $level, int $country): array
    {
        $now = BossClock::now();
        $onCountryMine = (int)DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->value('oncountrymine');
        return array_merge(
            WorldBoss::memberInit($uid, $level, $now),
            SiegeHandler::countryWar(['uid' => $uid, 'country' => $country], $now),
            // 判断国家矿是否可收获
            ['oncountrymine' => $onCountryMine > 0 && $now >= $onCountryMine ? 1 : 0],
        );
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
     * 重置当前账号进度至 1 级初始新手状态: c=member&m=reset_account
     */
    public static function reset_account(Context $ctx): array
    {
        $uid = self::resolveUid($ctx);

        if ($uid > 0) {
            // 1. 重置 member 基础字段
            DB::table('member')->where('uid', $uid)->update([
                'level' => 1,
                'exp' => 0,
                'maxexp' => 80,
                'silver' => 88888,
                'gold' => 9999,
                'reputation' => 100,
                'act' => 100,
                'maxact' => 100,
                'missionsite' => 2,
                'missionlevel' => 1,
                'missionstage' => 1,
                'missionid' => 1,
                'guide' => 0,
                'guidestep' => 0,
                'guidestop' => 0,
            ]);

            // 2. 清空并重建关联动态数据
            DB::table('map')->where('uid', $uid)->delete();
            DB::table('equipment')->where('uid', $uid)->delete();
            DB::table('general')->where('uid', $uid)->delete();
            DB::table('matrix')->where('uid', $uid)->delete();

            // 3. 重新发放旧版初始武将（init_general），不送初始装备
            $genId = General::initGeneral($uid);

            // 旧版 init_matrix(1, uid, 2, gid)：初始武将放在 1 号阵法 2 号位，并启用 1 号阵法
            OwnMatrix::init($uid, OwnMatrix::INITIAL_MID, OwnMatrix::INITIAL_POSITION, $genId);
            DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->update(['matrixid' => OwnMatrix::INITIAL_MID]);

            // 4. 清空 Redis 缓存
            Redis::del('player:' . $uid);
            Redis::del('mainquest:task_id:' . $uid);
            Redis::del("chat_last_read:{$uid}");
        }

        return [
            'status' => 1,
            'message' => '账号重置成功，已重回1级初始新手状态',
            'msg' => '账号重置成功，已重回1级初始新手状态',
        ];
    }

    /**
     * 原子安全扣除玩家货币/资源 (委托至 Member 领域模型)
     */
    public static function deductCurrency(int $uid, int $silver = 0, int $gold = 0, int $act = 0): bool
    {
        return Member::deductCurrency($uid, $silver, $gold, $act);
    }

    /**
     * 原子增加玩家货币/资源 (委托至 Member 领域模型)
     */
    public static function addCurrency(int $uid, int $silver = 0, int $gold = 0, int $act = 0, int $reputation = 0, int $soul = 0): void
    {
        Member::addCurrency($uid, $silver, $gold, $act, $reputation, $soul);
    }
}
