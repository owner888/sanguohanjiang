<?php

declare(strict_types=1);

namespace Hanjiang\Handler;

use Hanjiang\Context;

final class MemberHandler
{
    /** @var array<int, array<string, mixed>> 纯内存玩家状态缓存 */
    private static array $players = [];

    /**
     * 玩家主信息接口: c=member&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = (int)($ctx->input('uid', 10001));
        if ($uid <= 0) {
            $uid = 10001;
        }

        if (!isset(self::$players[$uid])) {
            self::$players[$uid] = [
                'uid' => $uid,
                'username' => 'dev_player',
                'nickname' => '天命主公',
                'sex' => 1,
                'gender' => 0, // 0表示进入选角创角流程，选定后变为 1 或 2
                'roleid' => 1,
                'country' => 1, // 1魏 2蜀 3吴
                'Level' => 1,
                'level' => 1,
                'silver' => 88888,   // 银两
                'gold' => 9999,      // 元宝
                'reputation' => 100, // 声望
                'act' => 100,        // 军令/体力
                'maxact' => 100,
                'vip' => 1,
                'exp' => 0,
                'maxexp' => 100,
                'bagnum' => 20,
                'guide' => 0,
                'guidestep' => 0,
                'guidestop' => 0,
                'missionsite' => 1,
                'missionlevel' => 1,
                'missionstage' => 1,
                'mainquest' => [],
                'attackcd' => [
                    'status' => 0,
                    'cd' => 0,
                ],
                'levy' => [
                    'times' => 10,
                    'cd' => 0,
                    'status' => 0,
                ],
                'practice' => [
                    'timers' => 0,
                ],
                'rank' => 1,
                'newresult' => 0,
                'gift' => 0,
                'race' => 0,
                'race_dateline' => 0,
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
            ];
        }

        $player = self::$players[$uid];
        $player['status'] = 1;
        $player['timestamp'] = time();

        return $player;
    }

    /**
     * 选角/创角: c=member&m=select_role
     */
    public static function select_role(Context $ctx): array
    {
        $uid = (int)($ctx->input('uid', 10001));
        if ($uid <= 0) {
            $uid = 10001;
        }

        $sex = (int)($ctx->input('sex', 1));
        $nickname = trim((string)($ctx->input('name', $ctx->input('nickname', '天命主公'))));

        if (!isset(self::$players[$uid])) {
            self::index($ctx);
        }

        self::$players[$uid]['gender'] = $sex > 0 ? $sex : 1;
        self::$players[$uid]['sex'] = $sex > 0 ? $sex : 1;
        self::$players[$uid]['nickname'] = $nickname;

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
        $uid = (int)($ctx->input('uid', 10001));
        $step = self::$players[$uid]['guidestep'] ?? 0;
        $stop = self::$players[$uid]['guidestop'] ?? 0;

        return [
            'status' => 1,
            'step' => $step,
            'stop' => $stop,
        ];
    }

    /**
     * 更新新手引导步数: c=member&m=updateguidestep
     */
    public static function updateguidestep(Context $ctx): array
    {
        $uid = (int)($ctx->input('uid', 10001));
        $step = (int)($ctx->input('step', 0));
        if (isset(self::$players[$uid])) {
            self::$players[$uid]['guidestep'] = $step;
        }

        return [
            'status' => 1,
            'step' => $step,
        ];
    }
}
