<?php

declare(strict_types=1);

namespace Hanjiang\Handler;

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

        // 写入用户表
        $uid = DB::table('user')->insert([
            'username' => $username,
            'password' => sha1($password),
            'channel' => $channel,
            'is_guest' => 1,
            'mac' => $mac,
            'created_at' => $now,
        ]);

        // 初始化玩家数据
        self::initMemberData($uid, $username, $channel);

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
            'created_at' => $now,
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

    private static function initMemberData(int $uid, string $username, int $channel): void
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
            'created_at' => $now,
            'updated_at' => $now,
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
            'rank' => 1,
            'newresult' => 0,
            'gift' => 0,
            'race' => 0,
            'race_dateline' => 0,
        ]);

        // 初始化主公将领
        $genId = DB::table('general')->insert([
            'uid' => $uid,
            'generalid' => 1,
            'name' => '新主公' . $uid,
            'level' => 1,
            'exp' => 0,
            'pos' => 2,
            'matrix_id' => 1,
            'status' => 1,
            'created_at' => $now,
        ]);

        // 初始化新手初始装备 (青铜剑, 青铜甲)
        DB::table('equipment')->insert([
            'uid' => $uid,
            'equipid' => 101,
            'name' => '青铜剑',
            'type' => 1, // 武器
            'quality' => 1,
            'level' => 1,
            'generalid' => 0,
            'number' => 1,
            'saleprice' => 50,
            'created_at' => $now,
        ]);

        DB::table('equipment')->insert([
            'uid' => $uid,
            'equipid' => 201,
            'name' => '青铜甲',
            'type' => 2, // 铠甲
            'quality' => 1,
            'level' => 1,
            'generalid' => 0,
            'number' => 1,
            'saleprice' => 50,
            'created_at' => $now,
        ]);

        // 初始化默认阵法 (鱼鳞阵, 主公放置在 2 号位)
        DB::table('matrix')->insert([
            'uid' => $uid,
            'mid' => 1,
            'level' => 1,
            'position1' => 0,
            'position2' => $genId,
            'position3' => 0,
            'position4' => 0,
            'position5' => 0,
            'position6' => 0,
            'position7' => 0,
            'position8' => 0,
            'position9' => 0,
        ]);
    }
}
