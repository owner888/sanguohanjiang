<?php

declare(strict_types=1);

namespace Hanjiang\Handler\City;

use Hanjiang\Handler\General\TavernHandler;
use Hanjiang\MasterData\Activationcode;
use Hanjiang\MasterData\ActivationcodeReward;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\FreeLeap;
use Hanjiang\Model\Member;
use Hanjiang\Model\OwnSoul;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 激活码（移植旧版 controllers/activationcode.php + models/activationcode_model.php）
 *
 * 四位激活码取旧版激活码表 MasterData Activationcode（code → 奖励类型 type，dateline 起 30 天内有效），每个玩家每个码只能用一次
 * （activationcode_log）；奖励取 MasterData ActivationcodeReward（按 type，gettype 1 元宝、2 银币、3 军令、4 突飞卡、5 蓝宝石、
 * 6 黄宝石、7 紫宝石、8 原魂）。每个玩家 24 小时内最多尝试 50 次（Redis activation_max_{uid}）。
 * 客户端 settingspop.js 调用 index、get_reward（code）。index 照旧版恒回 open 0（旧版「为了支持多种类型的激活码」注释掉了查询）。
 *
 * 依赖未移植：8 ~ 13 位的激活码由 uc 服务器校验（uc_model->use_activation_code，uc 的 activationcode 不在旧版代码包里）。
 * uc 没有返回时旧版会写一条 type 0 的使用记录再因奖励类型为空 SQL 出错；这里格式正确的长码一律回 -3（激活码不存在），不写记录。
 * 当前时间取 BossClock（生产恒为 time()，测试可固定时间验证有效期）。
 * 与旧版的差别：处理锁改为 Redis SET NX（旧版 get + setex）。
 */
final class ActivationCodeHandler
{
    /** 24 小时最多尝试次数 */
    private const int MAX_TIMES = 50;

    /** 四位激活码的有效期（旧版 _dateline，30 天） */
    private const int EXPIRE = 2592000;

    private const string MAX_TIME_KEY = 'activation_max_';
    private const string LOCK_KEY = 'activation_lock_';
    private const int LOCK_TTL = 300;

    private const array LANG = [
        'code_error1' => '已经使用过激活码',
        'code_error2' => '激活码格式不正确',
        'code_error3' => '激活码不存在',
        'code_error6' => '请求正在处理，请稍后再试',
        'code_error7' => '尝试次数过多，请稍后再试',
        'code_error8' => '没有激活码可用',
        'code_error9' => '激活码已过期',
        'chat_activationcode_get' => '兑换码使用成功，恭喜您获得：%s 祝您游戏愉快。',
        'chat_activationcode_iphone' => 'iPhone 5s 一台，请主动联系客服进行兑换，',
        'chat_activationcode_note' => 'Samsung Note3 一台，请主动联系客服进行兑换，',
        'chat_activationcode_ps4' => 'Sony PS4 一台，请主动联系客服进行兑换，',
    ];

    /** 奖励类型的名称（base_lang） */
    private const array REWARD_NAME = [1 => '元宝', 2 => '银币', 3 => '军令', 4 => '初级突飞卡', 5 => '蓝宝石', 6 => '黄宝石', 7 => '紫宝石', 8 => '原魂'];

    /**
     * 检查用户是否已经使用激活码: c=activationcode&m=index
     */
    public static function index(Context $ctx): array
    {
        // 为了支持多种类型的激活码，旧版不再查询使用记录，open 恒为 0
        return ['status' => 1, 'open' => 0];
    }

    /**
     * 输入激活码: c=activationcode&m=get_reward（code）
     */
    public static function get_reward(Context $ctx): array
    {
        /**
         * -9 激活码已过期
         * -8 没有激活码可用
         * -7 尝试次数过多，请稍后再试
         * -6 请求正在处理，请稍后再试
         * -3 激活码不存在
         * -2 激活码格式不正确
         * -1 已使用过激活码
         *  1 成功
         */
        $return = ['status' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        if ($uid <= 0) {
            return $return;
        }
        // 激活码
        $code = htmlspecialchars((string)$ctx->input('code', ''));
        // 判断上次请求是否结束
        $redis = Redis::instance();
        if ($redis === null || !(bool)$redis->set(self::LOCK_KEY . $uid, '1', ['nx', 'ex' => self::LOCK_TTL])) {
            $return['status'] = -6;
            $return['message'] = self::LANG['code_error6'];
            return $return;
        }
        try {
            return self::reward($uid, $code, $return);
        } finally {
            // 解锁
            $redis->del(self::LOCK_KEY . $uid);
        }
    }

    /**
     * @param array<string, mixed> $return
     * @return array<string, mixed>
     */
    private static function reward(int $uid, string $code, array $return): array
    {
        $now = BossClock::now();
        // 判断今日尝试次数
        $times = (int)(Redis::get(self::MAX_TIME_KEY . $uid) ?? 0);
        if ($times >= self::MAX_TIMES) {
            $return['status'] = -7;
            $return['message'] = self::LANG['code_error7'];
            return $return;
        }
        // 添加尝试次数
        Redis::set(self::MAX_TIME_KEY . $uid, $times + 1, 86400);

        if (strlen($code) !== 4) {
            // 判断激活码格式是否正确
            if (preg_match('/^[\w~!@#$%^&*]{8,13}$/i', $code) !== 1) {
                $return['status'] = -2;
                $return['message'] = self::LANG['code_error2'];
                return $return;
            }
            // 依赖未移植：uc 服务器校验长激活码（uc_model->use_activation_code），按激活码不存在处理
            $return['status'] = -3;
            $return['message'] = self::LANG['code_error3'];
            return $return;
        }
        // 找出数据库所存在的激活码
        $info = null;
        foreach (Activationcode::ROWS as $row) {
            if ((string)$row['code'] === $code) {
                $info = $row;
                break;
            }
        }
        if ($info === null) {
            // 没有激活码可用
            $return['status'] = -8;
            $return['message'] = self::LANG['code_error8'];
            return $return;
        }
        if ($info['dateline'] < $now - self::EXPIRE) {
            // 激活码失效
            $return['status'] = -9;
            $return['message'] = self::LANG['code_error9'];
            return $return;
        }
        // 查询用户是否已经使用过激活码
        if (DB::table('activationcode_log')->where('uid', $uid)->where('code', $code)->whereNull('deleted_at')->value('id') !== null) {
            $return['status'] = -1;
            $return['message'] = self::LANG['code_error1'];
            return $return;
        }
        $rewardType = (int)$info['type'];
        // 查询奖励列表（先查奖励：取不到时旧版已写使用记录，这里不写）
        $reward = null;
        foreach (ActivationcodeReward::ROWS as $row) {
            if ($row['type'] === $rewardType) {
                $reward = $row;
                break;
            }
        }
        if ($reward === null) {
            $return['status'] = -3;
            $return['message'] = self::LANG['code_error3'];
            return $return;
        }
        // 记录使用
        DB::table('activationcode_log')->insert(['uid' => $uid, 'code' => $code, 'type' => $rewardType, 'dateline' => $now]);
        unset($reward['description']);

        // 添加奖励
        $rewardStr = '';
        for ($i = 1; $i <= 4; $i++) {
            $type = (int)$reward['gettype' . $i];
            $value = (int)$reward['getvalue' . $i];
            if ($type <= 0 || $value <= 0) {
                continue;
            }
            $rewardStr .= $value;
            switch ($type) {
                case 1: Member::addCurrency($uid, gold: $value); break;
                case 2: Member::addCurrency($uid, silver: $value); break;
                case 3: Member::addCurrency($uid, act: $value); break;
                case 4: FreeLeap::add($uid, $value, $now); break;
                case 5: TavernHandler::addJade($uid, 1, $value); break;
                case 6: TavernHandler::addJade($uid, 2, $value); break;
                case 7: TavernHandler::addJade($uid, 3, $value); break;
                case 8: OwnSoul::addSoul($uid, $value, now: $now); break;
            }
            if (isset(self::REWARD_NAME[$type])) {
                $rewardStr .= self::REWARD_NAME[$type] . '，';
            }
        }
        $rewardStr .= match ($rewardType) {
            21 => self::LANG['chat_activationcode_iphone'],
            22 => self::LANG['chat_activationcode_note'],
            23 => self::LANG['chat_activationcode_ps4'],
            default => '',
        };

        $member = DB::table('member')->select('gold', 'silver', 'act')->where('uid', $uid)->first();
        $return['reward'] = $reward;
        $return['msg'] = sprintf(self::LANG['chat_activationcode_get'], $rewardStr);
        $return['all_gold'] = (int)$member['gold'];
        $return['all_silver'] = (int)$member['silver'];
        $return['all_act'] = (int)$member['act'];
        $return['status'] = 1;
        return $return;
    }
}
