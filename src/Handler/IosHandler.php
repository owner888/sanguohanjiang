<?php

declare(strict_types=1);

namespace Hanjiang\Handler;

use Hanjiang\Context;

final class IosHandler
{
    /**
     * 游戏启动握手与资源校验: c=ios&m=update_world_common
     */
    public static function update_world_common(Context $ctx): array
    {
        $clientVersion = (int)($ctx->input('client_version', 20150112001));

        return [
            'status' => 1,
            'version' => $clientVersion,
            'versionname' => '1.0.0',
            'updateinfo' => '',
            'time' => (string)time(),
            'filelist' => [],
            'alljs_list' => [],
            'cached_list' => [],
        ];
    }

    /**
     * Token 校验
     */
    public static function verify(Context $ctx): array
    {
        return [
            'status' => 1,
            'verified' => true,
        ];
    }

    /**
     * 注册推送 Token
     */
    public static function savetoken(Context $ctx): array
    {
        return [
            'status' => 1,
        ];
    }
}
