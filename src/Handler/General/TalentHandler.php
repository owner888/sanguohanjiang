<?php

declare(strict_types=1);

namespace Hanjiang\Handler\General;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\MasterData\ActivityTime;
use Hanjiang\Model\Equipment;
use Hanjiang\Model\HeroTalent;
use Hanjiang\Model\Member;
use Hanjiang\Model\MemberJade;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 主公天赋（移植旧版 controllers/talent.php + models/talent_model.php，路径相对旧版 s1/application/）
 *
 * 旧版天赋是主公（玩家）的 5 项天赋（物攻 / 物防 / 策攻 / 策防 / 生命），不是逐将天赋：每项 0 ~ 300 星，
 * 升星消耗玉石与天赋卷轴（Equipment 的 talentScroll，按天赋类型与目标品级），可花元宝必成；
 * 星级 20 / 40 / 60 / 80 / 100 / 200 的下一星为顿悟（失败掉星，可花元宝保护或必成）。存储见 Model\HeroTalent（own_hero_talent）。
 * 加成计入战斗（TeamBuilder，主公 91 级起）与将领界面血量（GeneralHandler）。
 *
 * 客户端调用（talentpop.js）：index、levelup、get_info。旧版 get_talent_list（按 uid 查他人天赋）前端不调用，不移植。
 * 开放：主公等级大于 90。双倍活动 ActivityTime double_talent（升星 / 顿悟成功率翻倍；配置的开始 / 结束时间为 0，不会开启）。
 * 并发：levelup 在 Member::locked 玩家行锁内执行。
 *
 * 依赖未移植：元宝流水 gold_log。
 */
final class TalentHandler
{
    /** 开放等级：主公等级大于 90 */
    private const int OPEN_LEVEL = 90;

    /**
     * 初始化: c=talent&m=index
     *
     * list 为 5 项天赋（第一次进入时建档），默认取第一项的升级信息与对应卷轴。
     */
    public static function index(Context $ctx): array
    {
        /**
         * -3 获取规则失败
         * -2 获取天赋失败
         * -1 等级不足
         *  0 失败
         *  1 成功
         */
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        if (!self::isOpen($member)) {
            // 等级不足
            $return['status'] = -1;
            return $return;
        }
        $uid = (int)$member['uid'];

        // 获得主公天赋列表
        $list = HeroTalent::list($uid);
        if ($list === []) {
            $return['status'] = -2;
            return $return;
        }
        // 缺省取第一个天赋升级信息
        $star = $list[0]['star'];
        $type = $list[0]['type'];
        $rule = self::getRule($star, $type, (int)$member['vip']);
        if ($rule === []) {
            // 获取规则失败
            $return['status'] = -3;
            return $return;
        }

        $scrollArr = [];
        if ($rule['ismax'] < 1) {
            $scroll = Equipment::talentScroll($uid, $type, $rule['starup']['quality']);
            $scrollArr['type'] = $type;
            $scrollArr['quality'] = $rule['starup']['quality'];
            $scrollArr['num'] = $scroll === null ? 0 : (int)$scroll['number'];
            $return['mode'] = $rule['mode'];
            $return['starup'] = $rule['starup'];
        }

        // 主公天赋
        $return['list'] = $list;
        $return['ismax'] = $rule['ismax'];
        $return['scroll'] = $scrollArr;
        // 剩余玉石
        $return['jade'] = (int)$member['jade'];
        $return['status'] = 1;
        return $return;
    }

    /**
     * 升星 / 顿悟: c=talent&m=levelup（type 天赋类型 1~5，success 元宝必成，protect 顿悟元宝保护）
     *
     * 失败（failed 1）同样扣玉石、卷轴与元宝（同旧版）。
     */
    public static function levelup(Context $ctx): array
    {
        /**
         * -7 获取升级信息失败
         * -6 扣卷轴失败
         * -5 扣玉石失败
         * -4 扣金币失败
         * -3 获取规则失败
         * -2 获取天赋失败
         * -1 等级不足
         *  0 失败
         *  1 成功
         *  2 满级
         *  3 玉石不足
         *  4 卷轴不足
         *  5 vip等级不足
         *  6 元宝不足
         */
        $uid = MemberHandler::resolveUid($ctx);
        $type = (int)$ctx->input('type', 0);
        $success = (int)$ctx->input('success', 0);
        $protect = (int)$ctx->input('protect', 0);
        return Member::locked($uid, static fn (): array => self::doLevelup($uid, $type, $success, $protect, time()));
    }

    /**
     * 获取天赋信息: c=talent&m=get_info（type 天赋类型 1~5）
     */
    public static function get_info(Context $ctx): array
    {
        /**
         * -3 获取规则失败
         * -2 获取天赋信息失败
         * -1 等级不足
         *  0 失败
         *  1 成功
         */
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        $type = (int)$ctx->input('type', 0);
        if (!self::isOpen($member)) {
            // 等级不足
            $return['status'] = -1;
            return $return;
        }
        $uid = (int)$member['uid'];

        $info = HeroTalent::byType($uid, $type);
        if ($info === []) {
            // 获取天赋信息失败
            $return['status'] = -2;
            return $return;
        }
        // 取天赋升级信息
        $infoArr = self::getRule($info['star'], $type, (int)$member['vip']);
        if ($infoArr === []) {
            // 获取规则失败
            $return['status'] = -3;
            return $return;
        }

        $scrollArr = [];
        if ($infoArr['ismax'] < 1) {
            $scroll = Equipment::talentScroll($uid, $type, $infoArr['starup']['quality']);
            $scrollArr['type'] = $type;
            $scrollArr['quality'] = $infoArr['starup']['quality'];
            $scrollArr['num'] = $scroll === null ? 0 : (int)$scroll['number'];
            $return['mode'] = $infoArr['mode'];
            $return['starup'] = $infoArr['starup'];
        }

        $return['status'] = 1;
        $return['ismax'] = $infoArr['ismax'];
        $return['scroll'] = $scrollArr;
        $return['info'] = $info;
        return $return;
    }

    /**
     * levelup 的主体（玩家行锁内）
     *
     * @return array<string, mixed>
     */
    private static function doLevelup(int $uid, int $type, int $success, int $protect, int $now): array
    {
        $return = ['status' => 0, 'info' => [], 'failed' => 0];
        $member = self::member($uid);
        if ($member === null) {
            return $return;
        }
        if (!self::isOpen($member)) {
            // 等级不足
            $return['status'] = -1;
            return $return;
        }
        // 获取主公天赋信息
        $info = HeroTalent::byType($uid, $type);
        if ($info === []) {
            // 获取天赋信息失败
            $return['status'] = -2;
            return $return;
        }
        // 取天赋升级信息
        $rule = HeroTalent::rule($info['star'], $type);
        if ($rule['status'] !== 1) {
            // 2 满级，其余为获取规则失败
            $return['status'] = $rule['status'] === 2 ? 2 : -3;
            return $return;
        }

        // 消耗玉石
        $jade = $rule['rule']['jade'];
        if ((int)$member['jade'] < $jade) {
            // 玉石不足
            $return['status'] = 3;
            return $return;
        }
        // 消耗卷轴：根据类型、品级获取卷轴
        $quality = $rule['rule']['quality'];
        $scroll = $rule['rule']['part'];
        $ownScroll = Equipment::talentScroll($uid, $type, $quality);
        if ($ownScroll === null || (int)$ownScroll['number'] < $scroll) {
            // 卷轴不足
            $return['status'] = 4;
            return $return;
        }

        $gold = (int)$member['gold'];
        $vip = (int)$member['vip'];
        // 双倍奖励活动期间，升星成功率翻倍
        if (self::isDouble($now)) {
            $rule['rule']['rate'] *= 2;
        }
        if ($rule['mode'] === 1) {
            // 顿悟
            $insight = HeroTalent::insight($rule['rule'], $protect, $success, $gold, $vip);
            if ($insight['status'] === 1) {
                $result = $insight['info'];
            } elseif ($insight['status'] === -1) {
                // 顿悟失败
                $result = $insight['info'];
                $return['failed'] = 1;
            } elseif ($insight['status'] === 2) {
                // vip等级不足
                $return['status'] = 5;
                return $return;
            } else {
                // 元宝不足
                $return['status'] = 6;
                return $return;
            }
            $cost = $insight['gold'];
        } else {
            // 升星
            $starup = HeroTalent::starup($rule['rule'], $success, $gold, $vip);
            if ($starup['status'] === 1) {
                $result = $starup['info'];
                $return['hit'] = $starup['hit'];
            } elseif ($starup['status'] === -1) {
                // 升星失败
                $result = $starup['info'];
                $return['failed'] = 1;
            } else {
                // 元宝不足
                $return['status'] = 6;
                return $return;
            }
            $cost = $starup['gold'];
        }

        // 星级品级是否变化
        $result['power'] = HeroTalent::talentInfo($result['star'], $type, $result['quality']);
        if ($result['star'] !== $info['star'] || $result['quality'] !== $info['quality']) {
            HeroTalent::update($uid, $type, $result);
        }
        // 扣金币（依赖未移植：元宝流水 gold_log）
        if (!Member::deductCurrency($uid, gold: $cost)) {
            $return['status'] = -4;
            return $return;
        }
        // 扣玉石
        if (MemberJade::sub($uid, $jade, 'talent', 'levelup', $now)['status'] !== 1) {
            $return['status'] = -5;
            return $return;
        }
        // 扣卷轴
        if (Equipment::subTalentScroll($uid, $type, $quality, $scroll, 'talent', 'levelup', $now)['status'] !== 1) {
            $return['status'] = -6;
            return $return;
        }

        if ($result['star'] < HeroTalent::MAX_STAR) {
            $next = self::getRule($result['star'], $type, $vip);
            if ($next === []) {
                // 获取规则失败
                $return['status'] = -7;
                return $return;
            }
            if (($next['starup']['quality'] ?? 0) > $quality) {
                $quality = $next['starup']['quality'];
            }
            $return['mode'] = $next['mode'] ?? null;
            $return['ismax'] = $next['ismax'];
            if ($next['ismax'] < 1) {
                $return['starup'] = $next['starup'];
            }
        } else {
            $return['ismax'] = 1;
        }

        // 根据类型、品级获取卷轴
        $ownScroll = Equipment::talentScroll($uid, $type, $quality);
        $member = self::member($uid) ?? [];
        $return['status'] = 1;
        $return['info'] = $result;
        $return['info']['type'] = $type;
        $return['scroll'] = [
            'type' => $type,
            'quality' => $ownScroll === null ? $quality : (int)$ownScroll['quality'],
            'num' => $ownScroll === null ? 0 : (int)$ownScroll['number'],
        ];
        $return['jade'] = (int)$member['jade'];
        $return['gold'] = (int)$member['gold'];
        return $return;
    }

    /**
     * 升级信息（移植控制器 _get_rule）
     *
     * 满级返回 [ismax 1]；否则 mode、ismax 0 与 starup（顿悟：star / power / jade / part / quality / protect，VIP 6 起加 success；
     * 升星：star / power / jade / part / quality / success；另带 type）。取不到规则返回空数组。
     *
     * @return array<string, mixed>
     */
    private static function getRule(int $star, int $type, int $vip): array
    {
        $return = [];
        $rule = HeroTalent::rule($star, $type);
        if ($rule['status'] === 2) {
            // 满级
            $return['ismax'] = 1;
        } elseif ($rule['status'] === 1) {
            $return['mode'] = $rule['mode'];
            $return['ismax'] = 0;
            if ($rule['mode'] === 1) {
                $field = $vip > 5
                    ? ['star', 'power', 'jade', 'part', 'quality', 'protect', 'success']
                    : ['star', 'power', 'jade', 'part', 'quality', 'protect'];
            } else {
                $field = ['star', 'power', 'jade', 'part', 'quality', 'success'];
            }
            foreach ($field as $value) {
                $return['starup'][$value] = $rule['rule'][$value];
            }
            $return['starup']['type'] = $type;
        }
        return $return;
    }

    /**
     * 双倍活动（构造函数 _initdouble：activity_model->activity_status('double_talent')）
     */
    private static function isDouble(int $now): bool
    {
        foreach (ActivityTime::ROWS as $row) {
            if ($row['name'] === 'double_talent') {
                return $row['starttime'] <= $now && $row['endtime'] >= $now;
            }
        }
        return false;
    }

    /**
     * 主公等级大于 90 开放
     *
     * @param array<string, mixed> $member
     */
    private static function isOpen(array $member): bool
    {
        return (int)$member['level'] > self::OPEN_LEVEL;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function member(int $uid): ?array
    {
        if ($uid <= 0) {
            return null;
        }
        return DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
    }
}
