<?php

declare(strict_types=1);

namespace Hanjiang\Handler\City;
use Hanjiang\Handler\City\MemberHandler;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

final class ActivationCodeHandler
{
    /**
     * 官方预设通用激活码兑换字典
     *
     * @var array<string, array<string, mixed>>
     */
    public const array CODES = [
        'VIP666' => ['code' => 'VIP666', 'title' => '至尊VIP起航礼包', 'silver' => 66666, 'gold' => 100, 'act' => 50],
        'VIP888' => ['code' => 'VIP888', 'title' => '富贵吉祥开服礼包', 'silver' => 88888, 'gold' => 200, 'act' => 80],
        'SG888'  => ['code' => 'SG888',  'title' => '神将出山无双礼包', 'silver' => 100000, 'gold' => 300, 'act' => 100],
        'HJ666'  => ['code' => 'HJ666',  'title' => '悍将归来霸业礼包', 'silver' => 50000, 'gold' => 150, 'act' => 50],
    ];

    /**
     * 兑换码主界面: c=activationcode&m=index
     */
    public static function index(Context $ctx): array
    {
        return [
            'status' => 1,
            'title' => '至尊激活码·奉旨领赏',
            'desc' => '输入官方特权激活码即可即时兑换海量元宝银两军饷！',
            'message' => 'ok',
        ];
    }

    /**
     * 兑换礼包码: c=activationcode&m=get_reward
     */
    public static function get_reward(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $rawCode = strtoupper(trim((string)($ctx->input('code', $ctx->input('activation_code', '')))));

        if ($rawCode === '') {
            return ['status' => 0, 'message' => '请输入兑换激活码'];
        }

        if (!isset(self::CODES[$rawCode])) {
            return ['status' => -1, 'message' => '激活码不存在或已失效，请检查拼写'];
        }

        $usedKey = "code_used:{$uid}:{$rawCode}";
        if (Redis::get($usedKey)) {
            return ['status' => -2, 'message' => '该激活码您已经成功兑换过，不可重复领取'];
        }

        $cfg = self::CODES[$rawCode];
        $rewardSilver = (int)$cfg['silver'];
        $rewardGold = (int)$cfg['gold'];
        $rewardAct = (int)$cfg['act'];

        MemberHandler::addCurrency($uid, silver: $rewardSilver, gold: $rewardGold, act: $rewardAct);
        Redis::set($usedKey, '1', 86400 * 365);

        return [
            'status' => 1,
            'code' => $rawCode,
            'title' => $cfg['title'],
            'silver' => $rewardSilver,
            'gold' => $rewardGold,
            'act' => $rewardAct,
            'message' => "兑换成功！恭贺主公开启【{$cfg['title']}】，获得银两 {$rewardSilver}、元宝 {$rewardGold}、军令 {$rewardAct}！",
        ];
    }
}
