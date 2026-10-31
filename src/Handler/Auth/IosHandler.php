<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Auth;

use Hanjiang\Handler\City\MemberHandler;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

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
     * 苹果内购收据验证: c=ios&m=verify（旧版 controllers/ios.php:verify）
     *
     * 待定渠道（渠道待开发者确定）：旧版把收据发到苹果 verifyReceipt 校验、按商品到账并记各充值活动。接入时验签通过后调用
     * Model\Pay::complete。这里只移植与渠道无关的参数检查：没有提交 receipt 回 -1；其余一律按校验失败回 0（不到账）。
     */
    public static function verify(Context $ctx): array
    {
        $return = ['status' => 0];
        if ((string)$ctx->input('receipt', '') === '') {
            // 没有提交 receipt
            $return['status'] = -1;
            return $return;
        }
        // 待定渠道：苹果收据校验（iospayapi->verifyReceipt）
        return $return;
    }

    /**
     * 保存苹果推送 devicetoken: c=ios&m=savetoken（旧版 controllers/ios.php:savetoken）
     *
     * 保存到 member_field.devicetoken（member_model->update_devicetoken：有修改为 1，否则 0）；旧版封禁设备名单里的 token
     * 直接输出纯文本 403（客户端按登录失效处理）。没有登录（ios 是登录前可调用的控制器）时回 0。
     * 依赖未移植：用 devicetoken 发推送的计划任务（iospushapi，矿区可收获等）。
     */
    public static function savetoken(Context $ctx): array|string
    {
        $return = ['status' => 0];
        $deviceToken = (string)$ctx->input('devicetoken', '');
        if (in_array($deviceToken, self::BANNED_TOKENS, true)) {
            // 封禁设备
            return '403';
        }
        $uid = MemberHandler::resolveUid($ctx);
        if ($deviceToken !== '' && $uid > 0) {
            $affected = DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')
                ->update(['devicetoken' => mb_substr($deviceToken, 0, 70)]);
            $return['status'] = $affected > 0 ? 1 : 0;
        }
        return $return;
    }

    /** 旧版 savetoken 的封禁设备名单 */
    private const array BANNED_TOKENS = [
        'dcb35cb64ebc8d414d9ef4dc186645c92602aa1c0b227cab8526c93b1293582c',
        '67c9184947e654403d0aac8e5978ddfb84fa768b81315fde3864d4f713d297c8',
        'ca98ff594d2a51877f58bdabd93113f828bdd0a7ac0f56962f67d9a815848214',
        '1da0b3146c30b11afae33940cd58e896c8d678cbc46d9245cbd900ef29f4961d',
        '38a1dbb493d4dc748371078e42e8ba342aa983d68a819d4182f53efcfebb99ef',
        '490f77c8f395433e5d372952b87e3d5328b55710325efd0ba458892bdb55027a',
        '954992194dc84b3b535c214554823e1e881aa796c4b3a4ec2ccabd351376c999',
        '6cf9e2219cce74e2102eb52347e75c54e8f04e37061b2a5f122b3d3e5adda534',
        '72ae90f876aaf47d8b614527544e3332b8277cb8813eab63f1834653b5c7f282',
        '2f94672cd0f777a805ac6a642a1657a9fa33f5b40afa68da692b89a3153e2f5b',
        '3ff389be1d6ecbb0b39c5a16cf0a3e5005a3881dbe40a781c7f2146c38cc6f7c',
        '30f0eb1441423dbd053272497ca679e58e2a7cdf46908a756b87cf3481eb18a9',
    ];
}
