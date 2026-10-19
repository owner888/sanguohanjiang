<?php

declare(strict_types=1);

namespace Hanjiang\Controller;

use Hanjiang\Http\Response;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response as WorkermanResponse;

final class IosController
{
    /**
     * 游戏启动首个模块检查接口: c=ios&m=update_world_common
     */
    public function update_world_common(Request $request): WorkermanResponse
    {
        $clientVersion = (int)($request->get('client_version') ?? 20150112001);

        return Response::json([
            'status' => 1,
            'version' => $clientVersion,
            'versionname' => '1.0.0',
            'updateinfo' => '',
            'time' => (string)time(),
            'filelist' => [],
            'alljs_list' => [],
            'cached_list' => [],
        ]);
    }

    /**
     * 凭证校验
     */
    public function verify(Request $request): WorkermanResponse
    {
        return Response::success(['verified' => true]);
    }

    /**
     * 保存推送 Token
     */
    public function savetoken(Request $request): WorkermanResponse
    {
        return Response::success();
    }
}
