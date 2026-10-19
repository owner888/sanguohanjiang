<?php

declare(strict_types=1);

namespace Hanjiang\Controller;

use Hanjiang\Http\Response;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response as WorkermanResponse;

final class MemberController
{
    /**
     * 玩家主信息接口: c=member&m=index
     */
    public function index(Request $request): WorkermanResponse
    {
        $now = time();

        // 基础玩家数据模型（与 zc_sanguo_menber 对应）
        $data = [
            'status' => 1,
            'uid' => 10001,
            'username' => 'dev_player',
            'nickname' => '天命主公',
            'sex' => 1,
            'roleid' => 1,
            'country' => 1, // 1魏 2蜀 3吴
            'Level' => 1,
            'silver' => 88888,   // 银两
            'gold' => 9999,      // 元宝
            'reputation' => 100, // 声望
            'act' => 100,        // 军令/体力
            'maxact' => 100,
            'vip' => 1,
            'exp' => 0,
            'maxexp' => 100,
            'bagnum' => 20,
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
            'timestamp' => $now,
        ];

        return Response::json($data);
    }

    /**
     * 创角/选角接口: c=member&m=select_role
     */
    public function select_role(Request $request): WorkermanResponse
    {
        $roleId = (int)($request->get('roleid') ?? 1);
        $nickname = trim((string)($request->get('nickname') ?? '主公'));

        return Response::success([
            'roleid' => $roleId,
            'nickname' => $nickname,
            'Level' => 1,
        ], '角色创建成功');
    }
}
