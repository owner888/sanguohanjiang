<?php

declare(strict_types=1);

namespace Hanjiang\WebSocket;

use Hanjiang\Database\DB;
use OmniPHP\WebSocket\Context as BaseWsContext;

/**
 * 游戏业务层全双工上下文
 *
 * 继承自 OmniPHP 框架官方 WebSocket Context，并扩展游戏专属的玩家数据快捷读取。
 */
class WsContext extends BaseWsContext
{
    /**
     * 获取当前登录玩家的数据库档案
     *
     * @return array<string, mixed>|null
     */
    public function member(): ?array
    {
        $uid = $this->uid();
        if ($uid === null || $uid <= 0) {
            return null;
        }

        return DB::table('member')->where('uid', $uid)->first();
    }
}
