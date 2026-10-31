<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Pvp;

use Hanjiang\Handler\Activity\MessageHandler;
use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Model\Member;
use Hanjiang\Model\MemberIntegral;
use Hanjiang\Model\WarCollege;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 国家学院（军事学院；移植旧版 controllers/war_college.php，存储与加成见 Model\WarCollege）
 *
 * 国家 30 级开启。君主用国家税金研究技能（11 类，每类 0 ~ 20 级，所需国家等级、前置技能与税金见 MasterData WarCollegeSkill），
 * 税金不足时按 1 税金 = 15 元宝补足；研究后给全体成员发系统信件。成员加入国家满 3 天后可用 1 个将军令（member_integral.general）
 * 开启 24 小时学院 buff，buff 期间战斗与武将界面获得学院加成。
 * 客户端 countrycollegepop.js 调用 index、level_up（skill_id）、use_buff。
 *
 * 不移植：level_up 的 platform = android 原生客户端文本（只按 H5 文本拼信件）。
 */
final class WarCollegeHandler
{
    /** 元宝补足金（1 税金 = 15 元宝） */
    private const int GOLD = 15;

    /** 技能最高等级 */
    private const int MAX_LEVEL = 20;

    /** 加入国家三天生效 */
    private const int SKILL_OPEN = 259200;

    /** 使用时需要消耗的将军令 */
    private const int GENERAL = 1;

    /** buff有效时间 */
    private const int BUFFTIME = 86400;

    private const int MESSAGE_TYPE_SYSTEM = 1;

    private const string LANG_MESSAGE_SYSTEM_TITLE = '系统通知';

    /** 旧版语言包 warcollege_lang.php */
    private const array LANG = [
        'skill_info_error' => '获取技能列表失败',
        'country_info_error' => '获取国家信息失败',
        'type_error' => '参数错误',
        'fast_error' => '操作太快了',
        'uid_error' => '抱歉，只有君主才能能进技能研究',
        'pre_skill_error' => '前置技能尚未解锁',
        'gold_error' => '元宝不足',
        'country_error' => '国家税金不足,',
        'country_cost_info' => '君主%s花费了%s元宝补足税金,',
        'country_level_up_succ' => '成功升级<font color="#FF0000" >%s</font>到LV%s。',
        'country_name' => '国家学院 ',
        'country_level_up' => ' 技能已升级到LV%s。',
        'skill_level_error' => '该技能已经满级',
        'country_level_lv' => 'LV',
        'country_level_error' => '国家30级开启',
        'level_error' => '国家等级不够',
        'cd_weidao' => 'cd时间未到 不能购买',
        'general_error' => '将军令不足',
    ];

    /**
     * 主界面: c=war_college&m=index
     */
    public static function index(Context $ctx): array
    {
        return self::indexInfo(MemberHandler::resolveUid($ctx));
    }

    /**
     * 使用 buff: c=war_college&m=use_buff（消耗 1 将军令，buff 顺延 24 小时）
     */
    public static function use_buff(Context $ctx): array
    {
        /**
         * -2 获取国家信息失败
         * -5 cd时间未到 不能购买
         *  1 使用成功
         *  2 未加入国家
         *  3 将军令不足
         */
        $uid = MemberHandler::resolveUid($ctx);
        $member = self::member($uid);
        if ($member === null) {
            return ['status' => 0];
        }
        return Member::locked($uid, static function () use ($uid, $member): array {
            $return = ['status' => 1];
            $now = time();
            // 得到将军令
            $general = (int)MemberIntegral::get($uid)['general'];
            $return['general'] = $general;
            // 获取国家id
            $info = CountryHandler::memberCountry($uid);
            if ($info === null) {
                $return['status'] = -2;
                $return['msg'] = self::LANG['country_info_error'];
                return $return;
            }
            if ($now - (int)$info['joindateline'] < self::SKILL_OPEN) {
                $return['status'] = -5;
                $return['msg'] = self::LANG['cd_weidao'];
                return $return;
            }
            // 未加入国家时退出
            if ((int)$member['country'] === 0) {
                $return['status'] = 2;
                $return['msg'] = self::LANG['country_info_error'];
                return $return;
            }
            // 将军令不足
            if (self::GENERAL > $general) {
                $return['status'] = 3;
                $return['msg'] = self::LANG['general_error'];
                return $return;
            }
            // 添加buff时间
            $return = WarCollege::useBuff($uid, self::BUFFTIME, $now);
            // 消耗将军令
            $changed = MemberIntegral::change($uid, self::GENERAL, 'general', 2, 'war_college', 'use_buff', $now);
            $return['general'] = $changed['general'] ?? null;
            return $return;
        });
    }

    /**
     * 技能升级(研究): c=war_college&m=level_up（skill_id 技能类型，君主）
     */
    public static function level_up(Context $ctx): array
    {
        /**
         * -9 国家等级不足
         * -8 该技能已经满级
         * -5 前置技能尚未解锁 / 元宝不足
         * -4 没有权限操作 / 技能类型错误
         * -3 参数错误
         * -2 国家未满 30 级 / 获取国家技能失败
         * -1 获取国家信息失败
         * 2 操作太快了
         * 1 成功
         * 0 失败
         */
        $return = ['status' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        $member = self::member($uid);
        if ($member === null) {
            return $return;
        }
        // [国家model]获取国家信息
        $country = CountryHandler::countryInfo((int)$member['country']);
        if ($country === null) {
            $return['status'] = -1;
            $return['msg'] = self::LANG['country_info_error'];
            return $return;
        }
        if ((int)$country['level'] < WarCollege::OPEN_LEVEL) {
            $return['status'] = -2;
            $return['msg'] = self::LANG['country_level_error'];
            return $return;
        }
        $countryId = (int)$country['id'];
        // 防止刷接口（加锁 30 秒）
        if (!WarCollege::lock($countryId)) {
            $return['status'] = 2;
            $return['msg'] = self::LANG['fast_error'];
            return $return;
        }
        try {
            $result = self::levelUp($uid, $member, $country, (int)$ctx->input('skill_id', 0));
        } finally {
            WarCollege::unlock($countryId);
        }
        if ($result['status'] !== 1) {
            return $result;
        }
        $return = self::indexInfo($uid);
        $return['gold'] = (int)self::member($uid)['gold'];
        return $return;
    }

    /**
     * 研究技能（level_up 加锁后的部分）
     *
     * @param array<string, mixed> $member
     * @param array<string, mixed> $country
     * @return array<string, mixed>
     */
    private static function levelUp(int $uid, array $member, array $country, int $skillId): array
    {
        $return = ['status' => 0];
        $countryId = (int)$country['id'];
        $now = time();
        // 获取技能id
        if ($skillId <= 0) {
            $return['status'] = -3;
            $return['msg'] = self::LANG['type_error'];
            return $return;
        }
        if ($uid !== (int)$country['uid']) {
            $return['status'] = -4;
            $return['msg'] = self::LANG['uid_error'];
            return $return;
        }
        if (!in_array($skillId, array_column(WarCollege::skillList(), 'skill_type'), true)) {
            $return['status'] = -4;
            $return['msg'] = self::LANG['type_error'];
            return $return;
        }
        // 获取国家技能信息
        $skillInfo = WarCollege::countrySkillInfo($countryId, $skillId);
        if ($skillInfo === []) {
            $return['status'] = -2;
            $return['msg'] = self::LANG['country_info_error'];
            return $return;
        }
        if ($skillInfo['skill_level'] + 1 > self::MAX_LEVEL) {
            $return['status'] = -8;
            $return['msg'] = self::LANG['skill_level_error'];
            return $return;
        }
        // 升级到下一级所需等级
        $next = WarCollege::skillInfo($skillInfo['skill_type'], $skillInfo['skill_level'] + 1);
        if ((int)$country['level'] < (int)($next['level'] ?? 0)) {
            $return['status'] = -9;
            $return['msg'] = self::LANG['level_error'];
            return $return;
        }
        // 升级到下一级的前置技能信息判断
        for ($i = 1; $i <= 2; $i++) {
            if (($next['pre_skill_type' . $i] ?? 0) > 0) {
                $pre = WarCollege::countrySkillInfo($countryId, $next['pre_skill_type' . $i]);
                if ($pre === [] || $pre['skill_level'] < $next['pre_skill_type' . $i . '_level']) {
                    $return['status'] = -5;
                    $return['msg'] = self::LANG['pre_skill_error'];
                    return $return;
                }
            }
        }
        // 升级到下一级所需的税金
        $cost = (int)($next['cost'] ?? 0);
        // 获取国家税金
        $countryInfo = WarCollege::countryGoldInfo($country, $now);
        // [国家model]获取成员列表
        $touids = array_map(static fn (array $val): int => (int)$val['uid'], CountryHandler::memberList($countryId, 2));
        $newLevel = $skillInfo['skill_level'] + 1;
        if ($countryInfo['country_gold'] < $cost) {
            // 还差多少税金，元宝补足
            // （税金有一位小数，元宝按 MySQL 写整数列时的四舍五入取整）
            $needGold = (int)round(($cost - $countryInfo['country_gold']) * self::GOLD);
            if (!Member::deductCurrency($uid, gold: $needGold)) {
                $return['status'] = -5;
                $return['msg'] = self::LANG['gold_error'];
                return $return;
            }
            // 减税金（扣光）
            WarCollege::updateCountryGold($country, $countryInfo['country_gold'], 3, $now);
            // 国家税金不足，君主XXX花费了XXX元宝补足税金，成功升级XXX到LVX。
            $message = self::LANG['country_error']
                . sprintf(self::LANG['country_cost_info'], (string)$member['nickname'], $needGold)
                . sprintf(self::LANG['country_level_up_succ'], $skillInfo['skill_name'], $newLevel);
            $data = [
                'country_id' => $countryId,
                'skill_type' => $skillInfo['skill_type'],
                'skill_level' => $skillInfo['skill_level'],
                'country_gold' => (int)$countryInfo['country_gold'],
                'gold' => $needGold,
                'dateline' => $now,
            ];
        } else {
            WarCollege::updateCountryGold($country, $cost, 3, $now);
            // 国家军事学院 养生术 已升级到LV2。
            $message = self::LANG['country_name'] . "<font color='#FF0000' >" . $skillInfo['skill_name'] . '</font>'
                . sprintf(self::LANG['country_level_up'], $newLevel);
            $data = [
                'country_id' => $countryId,
                'skill_type' => $skillInfo['skill_type'],
                'skill_level' => $skillInfo['skill_level'],
                'country_gold' => $cost,
                'gold' => 0,
                'dateline' => $now,
            ];
        }
        if ($touids !== []) {
            MessageHandler::sendSystemMessage($touids, self::MESSAGE_TYPE_SYSTEM, self::LANG_MESSAGE_SYSTEM_TITLE, $message);
        }
        WarCollege::addLevelUpLog($data);
        // 根据skill_id country_id 升级技能
        WarCollege::skillLevelAdd($countryId, $skillId, $now);
        return ['status' => 1];
    }

    /**
     * 主界面数据（index：level_up 成功后也返回这份数据）
     *
     * @return array<string, mixed>
     */
    private static function indexInfo(int $uid): array
    {
        /*
         * -3 初始化用户数据失败
         * -2 获取国家信息失败
         * -1 获取技能列表失败
         * 1成功
         * 0失败
         */
        $return = ['status' => 0];
        $now = time();
        // 获取国家id
        $info = CountryHandler::memberCountry($uid);
        if ($info === null) {
            $return['status'] = -2;
            $return['msg'] = self::LANG['country_info_error'];
            return $return;
        }
        // [国家model]获取国家信息
        $country = CountryHandler::countryInfo((int)$info['country']);
        if ($country === null) {
            $return['status'] = -2;
            $return['msg'] = self::LANG['country_info_error'];
            return $return;
        }
        $countryLevel = (int)$country['level'];
        if ($countryLevel < WarCollege::OPEN_LEVEL) {
            $return['status'] = -2;
            $return['msg'] = self::LANG['country_level_error'];
            return $return;
        }
        $countryId = (int)$country['id'];
        $isKing = $uid === (int)$country['uid'];
        // 国家税金
        $countryGold = (int)WarCollege::countryGoldInfo($country, $now)['country_gold'];
        $returnList = [];
        foreach (WarCollege::skillList() as $key => $val) {
            $item = [
                'skill_id' => (int)$val['skill_type'],
                'img_id' => (int)$val['img_id'],
                'desc' => '',
                'cost' => 0,
                'open' => $countryLevel >= $val['level'] ? 1 : 0,
            ];
            $skillInfo = WarCollege::countrySkillInfo($countryId, $val['skill_type']);
            if ($skillInfo === []) {
                $return['status'] = -2;
                $return['msg'] = self::LANG['country_info_error'];
                return $return;
            }
            // 当前技能信息 / 下一级技能信息
            $current = WarCollege::skillInfo($skillInfo['skill_type'], $skillInfo['skill_level']);
            $next = WarCollege::skillInfo($skillInfo['skill_type'], $skillInfo['skill_level'] + 1);
            $item['skill_name'] = $skillInfo['skill_name'];
            $item['skill_level'] = $skillInfo['skill_level'];
            $item['current_name'] = $current['desc'] ?? null;
            if ($next !== []) {
                $item['msg'] = (int)$next['level'];
                $item['next_name'] = $next['desc'];
            }
            $item['is_cost'] = $item['is_full_level'] = $item['level_is_open'] = 0;
            $item['country_gold'] = $countryGold;
            // 研究按钮的状态
            if ($isKing) {
                $item['is_active'] = $item['skill_level'] < self::MAX_LEVEL && isset($next['level']) && $countryLevel >= $next['level'] ? 1 : 0;
            } else {
                $item['is_active'] = 2;
            }
            if ($item['skill_level'] >= self::MAX_LEVEL) {
                $item['is_full_level'] = 1;
            }
            // 前置按钮的状态 1未开启 0 已开启
            $preSkill = [];
            for ($i = 1; $i <= 2; $i++) {
                if (($current['pre_skill_type' . $i] ?? 0) > 0) {
                    $preInfo = WarCollege::skillInfo($current['pre_skill_type' . $i]);
                    $preSkill[$i - 1]['pre_skill_id'] = (int)($preInfo['img_id'] ?? 0);
                    $preSkill[$i - 1]['pre_skill_name'] = ($preInfo['skill_name'] ?? '') . self::LANG['country_level_lv'] . $current['pre_skill_type' . $i . '_level'] . ' ';
                    $skillArray = WarCollege::countrySkillInfo($countryId, $current['pre_skill_type' . $i]);
                    if ($skillArray === [] || $skillArray['skill_level'] < $current['pre_skill_type' . $i . '_level']) {
                        $preSkill[$i - 1]['pre_skill'] = 1;
                        if ($item['is_active'] !== 2) {
                            $item['is_active'] = 0;
                        }
                    } else {
                        $preSkill[$i - 1]['pre_skill'] = 0;
                    }
                }
            }
            if ($preSkill !== []) {
                $item['pre_skill_info'] = $preSkill;
            }
            // 升级到下一级所需的税金
            if ($next !== []) {
                $item['cost'] = (int)$next['cost'];
            }
            if ($item['country_gold'] < $item['cost']) {
                $item['is_cost'] = 1;
            }
            // 点击研究时弹出的对话框类型：1确认研究 0弹出需要用元宝补充资金对话框
            $item['confirm_type'] = $item['country_gold'] >= $item['cost'] ? 1 : 0;
            if ($item['confirm_type'] <= 0) {
                $item['need_country_gold'] = $item['cost'] - $item['country_gold'];
                $item['need_gold'] = $item['need_country_gold'] * self::GOLD;
            }
            $returnList[$key] = $item;
        }
        // 获取学院加成信息：闪避率 / 识破率 / 命中 / 精准带 %（旧版按列表下标 + 1 取加成名称）
        $skillBuff = ['addition' => []];
        foreach (WarCollege::countrySkillInfo($countryId) as $key => $value) {
            $name = WarCollege::TYPE[$key + 1] ?? null;
            if ($name !== null) {
                $skillBuff['addition'][$name] = in_array($value['skill_type'], [8, 9, 11, 10], true)
                    ? $value['skill_value'] . '%'
                    : (int)$value['skill_value'];
            }
        }
        $return['skill_list'] = $returnList;
        $return['skill_buff'] = $skillBuff;
        $isOpen = $cd = 0;
        if ($now - (int)$info['joindateline'] >= self::SKILL_OPEN) {
            $isOpen = 1;
        } else {
            $cd = self::SKILL_OPEN - ($now - (int)$info['joindateline']);
        }
        // 得到将军令
        $return['general'] = (int)MemberIntegral::get($uid)['general'];
        $return['need_general'] = self::GENERAL;
        // 获取玩家buffcd
        $return['buffcd'] = WarCollege::buffcd($uid, $now);
        $return['is_open'] = $isOpen;
        $return['cd'] = $cd;
        $return['status'] = 1;
        return $return;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function member(int $uid): ?array
    {
        return $uid > 0 ? DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first() : null;
    }
}
