<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Auth;

use Hanjiang\Model\General;
use Hanjiang\Model\OpenMap;
use Hanjiang\Model\OwnMatrix;
use Hanjiang\Model\Practice;
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
            'gift' => 0,
            'race' => 0,
            'race_dateline' => 0,
            // 旧版 member_model 注册：默认启用阵法 1
            'matrixid' => OwnMatrix::INITIAL_MID,
            // 旧版 member_model->insert_member：首次进入游戏时间（闯关竞速从此时起 3 天）
            'start_time' => $now,
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
