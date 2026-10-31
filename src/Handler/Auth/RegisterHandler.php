<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Auth;

use Hanjiang\Model\General;
use Hanjiang\Model\OpenMap;
use Hanjiang\Model\OwnMatrix;
use Hanjiang\Model\Practice;
use Hanjiang\Handler\City\MemberHandler;
use OmniPHP\Cache\Redis;
use OmniPHP\Http\Context;
use OmniPHP\Database\DB;

final class RegisterHandler
{
    /**
     * 游客快速试玩账号创建: c=register&m=guest
     */
    public static function guest(Context $ctx): array
    {
        $serverBasePort = 18100;
        $host = $ctx->host();
        $hostname = explode(':', $host)[0];
        $channel = (int)($ctx->input('channel', 106));
        $mac = (string)($ctx->input('macaddr', $ctx->input('mac', '')));

        $now = time();
        $username = 'guest_' . bin2hex(random_bytes(4));
        $password = bin2hex(random_bytes(6));
        // 游客登录密钥：明文只返回这一次，库里只存 sha256
        $guestKey = bin2hex(random_bytes(16));

        // 写入用户表
        $uid = DB::table('user')->insert([
            'username' => $username,
            'password' => sha1($password),
            'channel' => $channel,
            'is_guest' => 1,
            'mac' => $mac,
            'guest_key' => hash('sha256', $guestKey),
        ]);

        // 初始化玩家数据（试玩用户 member.guest = 1：绑定正式帐号后可领注册奖励 regreward）
        self::initMemberData($uid, $username, $channel, 1);

        $serverList = [
            [
                'id' => 1,
                'name' => '1区-桃园结义',
                'addr' => "http://{$hostname}:{$serverBasePort}/index.php",
                'servergroup' => 1,
                'newserver' => 0,
                'status' => 1,
                'message' => '',
                'selected' => 1,
            ],
        ];

        return [
            'status' => 1,
            'uid' => $uid,
            'guestkey' => $guestKey,
            'message' => '试玩账号创建成功',
            'serverlist' => $serverList,
        ];
    }

    /**
     * 正式用户注册: c=register&m=reg
     */
    public static function reg(Context $ctx): array
    {
        $username = trim((string)$ctx->input('u', ''));
        $password = trim((string)$ctx->input('p', ''));
        $channel = (int)($ctx->input('channel', 106));
        $mac = (string)($ctx->input('macaddr', $ctx->input('mac', '')));

        if ($username === '' || $password === '') {
            return [
                'status' => -4,
                'message' => '用户名或密码不能为空',
            ];
        }

        // 检查用户名是否已存在
        $exist = DB::table('user')->where('username', $username)->first();
        if ($exist !== null) {
            return [
                'status' => -1,
                'message' => '用户名已存在，请换一个',
            ];
        }

        $now = time();
        $uid = DB::table('user')->insert([
            'username' => $username,
            'password' => sha1($password),
            'channel' => $channel,
            'is_guest' => 0,
            'mac' => $mac,
        ]);

        self::initMemberData($uid, $username, $channel);

        $serverBasePort = 18100;
        $host = $ctx->host();
        $hostname = explode(':', $host)[0];

        $serverList = [
            [
                'id' => 1,
                'name' => '1区-桃园结义',
                'addr' => "http://{$hostname}:{$serverBasePort}/index.php",
                'servergroup' => 1,
                'newserver' => 0,
                'status' => 1,
                'message' => '',
                'selected' => 1,
            ],
        ];

        return [
            'status' => 1,
            'uid' => $uid,
            'message' => '注册成功',
            'serverlist' => $serverList,
        ];
    }

    /**
     * 试玩帐号绑定正式帐号: c=register&m=binding（原 uc 服 controllers/register.php:binding）
     *
     * 参数 u / p（用户名 / 密码）、uid。状态码同旧版：1 绑定成功，-1 uid 与设备不匹配，-2 已绑定过，-3 用户名格式不正确，
     * -4 密码格式不正确，-5 用户名已存在，0 未知错误；返回 message、uid 与服务器列表。
     * 旧版 uc 的 register_model 不在旧版代码包里：用户名 / 密码格式按客户端 helper.checkUserName / checkPassWord 的规则
     * （字母数字，4 ~ 16 / 6 ~ 16 位）；旧版按设备 mac 认定是本人的试玩帐号，新版试玩帐号凭登录 token 认定（开发者要求
     * 的游客密钥体系下 mac 不可信）：token 对应的 uid 与参数 uid 不一致时回 -1。绑定后 user.is_guest = 0、游客密钥作废。
     * 依赖未移植：uc 的 fetch_member_info（记录手机号 mobile、设备、渠道、版本，新版 user 表没有这些列）。
     */
    public static function binding(Context $ctx): array
    {
        $username = (string)$ctx->input('u', '');
        $password = (string)$ctx->input('p', '');
        $uid = (int)$ctx->input('uid', 0);
        if (!preg_match('/^[A-Za-z0-9]{4,16}$/', $username)) {
            [$status, $message] = [-3, '用户名格式不正确'];
        } elseif (!preg_match('/^[A-Za-z0-9]{6,16}$/', $password)) {
            [$status, $message] = [-4, '密码格式不正确'];
        } elseif (DB::table('user')->where('username', $username)->value('uid') !== null) {
            [$status, $message] = [-5, '用户名已存在'];
        } else {
            $status = self::guestBinding($ctx, $uid, $username, $password);
            $message = match ($status) {
                1 => '用户绑定成功',
                -1 => 'uid与mac不匹配',
                -2 => '用户已绑定过',
                default => '未知错误',
            };
        }
        return [
            'status' => $status,
            'message' => $message,
            'uid' => $uid,
            'serverlist' => self::serverList($ctx),
        ];
    }

    /**
     * 绑定（uc register_model->guest_binding）：1 成功，-1 不是本人的帐号，-2 已绑定过，0 未知错误
     */
    private static function guestBinding(Context $ctx, int $uid, string $username, string $password): int
    {
        if ($uid <= 0 || MemberHandler::resolveUid($ctx) !== $uid) {
            return -1;
        }
        $user = DB::table('user')->select('is_guest')->where('uid', $uid)->whereNull('deleted_at')->first();
        if ($user === null) {
            return -1;
        }
        if ((int)$user['is_guest'] !== 1) {
            return -2;
        }
        try {
            $affected = DB::table('user')->where('uid', $uid)->where('is_guest', 1)->whereNull('deleted_at')->update([
                'username' => $username,
                'password' => sha1($password),
                'is_guest' => 0,
                'guest_key' => '',
            ]);
        } catch (\PDOException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            // 用户名唯一键冲突（并发注册了同名帐号）
            return 0;
        }
        if ($affected === 0) {
            return -2;
        }
        DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->update(['username' => $username]);
        Redis::del('player:' . $uid);
        return 1;
    }

    /**
     * 服务器列表（同 guest / reg）
     *
     * @return list<array<string, mixed>>
     */
    private static function serverList(Context $ctx): array
    {
        $hostname = explode(':', $ctx->host())[0];
        return [
            [
                'id' => 1,
                'name' => '1区-桃园结义',
                'addr' => "http://{$hostname}:18100/index.php",
                'servergroup' => 1,
                'newserver' => 0,
                'status' => 1,
                'message' => '',
                'selected' => 1,
            ],
        ];
    }

    private static function initMemberData(int $uid, string $username, int $channel, int $guest = 0): void
    {
        $now = time();

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
            // 旧版 member_model->insert_member：将魂包裹 4 格
            'soulpack' => 4,
            'act' => 100,
            'maxact' => 100,
            'vip' => 0,
            'bagnum' => 20,
            'guide' => 0,
            'guidestep' => 0,
            'guidestop' => 0,
            'missionsite' => 2, // 初始置于黄巾势力地图，确保引导第1步指引.guanqia1顺畅定位
            'missionlevel' => 1,
            'missionstage' => 1,
            'missionid' => 1,
            // 旧版 member_model->insert_member：加入游戏时间、征收计划任务执行时间（注册当天 7 点前后都不再发放征收次数）
            'jointime' => $now,
            'crontime' => $now,
            // 是否是试玩用户（旧版 member.guest）
            'guest' => $guest,
        ]);

        DB::table('member_field')->insert([
            'uid' => $uid,
            'attackstatus' => 0,
            'attackdateline' => 0,
            'levytimes' => 50,
            'levydateline' => 0,
            'practiceall' => 2,
            'practicenow' => 0,
            'practicetime' => 0,
            // 旧版注册不写演武榜排名（默认 0），升到 26 级入榜时才写
            'rank' => 0,
            'newresult' => 0,
            'race' => 0,
            'race_dateline' => 0,
            // 旧版 member_model 注册：默认启用阵法 1
            'matrixid' => OwnMatrix::INITIAL_MID,
            // 旧版 member_model->insert_member：首次进入游戏时间（闯关竞速从此时起 3 天）
            'start_time' => $now,
            // 旧版 member_model->insert_member：连续登录天数更新时间（注册当天不计连续登录）
            'login_dateline' => $now,
        ]);

        // 初始化训练位信息：旧版 controllers/login.php 注册时 practice_model->init_practice(uid)（1、2 号训练位）
        Practice::init($uid);

        // 初始武将：旧版 controllers/login.php 注册时 init_general(1, uid)，不送初始装备
        $genId = General::initGeneral($uid);

        // 初始化默认阵法：旧版 init_matrix(1, uid, 2, gid)，初始武将放在 1 号阵法 2 号位
        OwnMatrix::init($uid, OwnMatrix::INITIAL_MID, OwnMatrix::INITIAL_POSITION, $genId);

        // 初始化地图：旧版 controllers/login.php 注册时 map_model->add_map_log(1, uid)，开启第一个势力
        OpenMap::init($uid, $now);
    }
}
