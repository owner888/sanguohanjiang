<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use Hanjiang\MasterData\Equip;
use Hanjiang\MasterData\EquipEssence;
use Hanjiang\MasterData\General as GeneralTemplate;
use Hanjiang\MasterData\MountLevel;
use Hanjiang\MasterData\MountTemp;
use Hanjiang\MasterData\Pub;
use Hanjiang\MasterData\Skill;
use Hanjiang\MasterData\Talent;

/**
 * 节日活动（七夕 act_tanabata、国家庆典 act_celebration、万圣节 act_halloween）共用的奖励编号与展示信息
 *
 * 旧版这几个控制器各自复制了一份 get_reward（奖励类型 1 元宝 2 银币 3 黄宝石 4 紫宝石 5 原魂 6 玉石 7 声望 8 装备
 * 9 宝石碎片 10 突飞卡 11 蓝宝石 12 武将 13 军令 14 精炼石 15 五彩石 16 上古血石 17 ~ 25 装备精华 26 坐骑 27 玫瑰 28 钻石），
 * 计划任务 cron_acttask 里再复制一份带信件文字的（tanabata_reward / celebration_reward，country.php 的 celebration_reward 同）。
 * 各控制器之间的差别（元宝是否掉进银币分支、坐骑属性是否只对有特殊属性的坐骑加成等）由调用方处理，这里只放相同的部分。
 */
final class FestReward
{
    /** 装备精华类型 17 ~ 25 对应的 equip_essence.id（旧版直接用奖励表里的 id，这里只用于判断） */
    public const array ITEM_TYPES = [8, 12, 26, 17, 18, 19, 20, 21, 22, 23, 24, 25];

    /** cron_lang.php（cron_soul 在语言包里定义了两次，后一次「将魂」生效） */
    public const array LANG_CRON = [
        'cron_check' => '请查收!',
        'cron_get_reward' => '获得奖励：',
        1 => '元宝',
        2 => '银币',
        3 => '黄宝石',
        4 => '紫宝石',
        5 => '将魂',
        6 => '玉石',
        7 => '声望',
        9 => '宝石碎片',
        10 => '突飞卡',
        11 => '蓝宝石',
        13 => '军令',
        14 => '精炼石',
        15 => '五彩石',
        16 => '上古血石',
        27 => '玫瑰花',
        28 => '钻石',
        'cron_equip' => '装备-',
        'cron_mount' => '坐骑-',
        'cron_change_purple' => '武将兑换紫宝石',
        'cron_general' => '武将-',
        'cron_good' => '精华',
    ];

    /**
     * 装备、武将、坐骑、装备精华：奖励表的 num 是 id、value 是数量（in_array($type, array('8', '12', '26')) || 17 ~ 25）
     */
    public static function isItemType(int $type): bool
    {
        return in_array($type, [8, 12, 26], true) || ($type >= 17 && $type <= 25);
    }

    /**
     * 装备模板（equipment_model->get_temp_info 的 info）；没有为 null
     *
     * @return array<string, mixed>|null
     */
    public static function equipInfo(int $id): ?array
    {
        return Equip::ROWS[$id] ?? null;
    }

    /**
     * 武将模板 + 天赋 / 技能的 name、description（general_model->get_info_by_id + get_ext_by_id）
     *
     * @return array<string, mixed>
     */
    public static function generalInfo(int $gid): array
    {
        $info = GeneralTemplate::ROWS[$gid] ?? [];
        $talent = Talent::ROWS[(int)($info['talentid'] ?? 0)] ?? [];
        $skill = Skill::ROWS[(int)($info['skillid'] ?? 0)] ?? [];
        $info['talent'] = ['name' => $talent['name'] ?? null, 'description' => $talent['description'] ?? null];
        $info['skill'] = ['name' => $skill['name'] ?? null, 'description' => $skill['description'] ?? null];
        return $info;
    }

    /**
     * 装备精华模板（equipment_model->get_eqip_essence($id)[$id]）；没有为 null
     *
     * @return array<string, mixed>|null
     */
    public static function essenceInfo(int $id): ?array
    {
        return EquipEssence::ROWS[$id] ?? null;
    }

    /**
     * 坐骑展示信息（mount_model->get_temp_by_id + 特殊属性 special_type / special_value + 0 级属性加成，level 0）
     *
     * $specialOnly 为 true 时（国家庆典、万圣节、圣诞团购）只对有特殊属性的坐骑按 0 级系数加成属性，false 时（七夕）全部加成。
     *
     * @return array<string, mixed>
     */
    public static function mountInfo(int $id, bool $specialOnly): array
    {
        $mount = MountTemp::ROWS[$id] ?? [];
        $special = (int)($mount['type'] ?? 0) > 0 && (int)($mount['value'] ?? 0) > 0;
        // 坐骑特殊属性
        if ($special) {
            $mount['special_type'] = $mount['type'];
            $mount['special_value'] = $mount['value'];
        }
        // 查询等级加成和升级经验信息
        $level = MountLevel::ROWS[0] ?? null;
        if ($level !== null && $mount !== [] && (!$specialOnly || $special)) {
            $coefficient = (float)$level['coefficient'];
            $mount['attack'] = $mount['attack'] + (int)($mount['attack_up'] * $coefficient);
            $mount['wufang'] = $mount['wufang'] + (int)($mount['wufang_up'] * $coefficient);
            $mount['cefang'] = $mount['cefang'] + (int)($mount['cefang_up'] * $coefficient);
            $mount['hp'] = $mount['hp'] + (int)($mount['hp_up'] * $coefficient);
        }
        // 安卓使用此作为坐骑等级，默认为 0
        $mount['level'] = 0;
        return $mount;
    }

    /**
     * 已拥有武将折算的紫宝石（tavern_model->get_price_by_id 的 price × 0.8）；酒馆没有该武将为 null
     */
    public static function generalJade(int $gid): ?float
    {
        $pub = Pub::ROWS[$gid] ?? null;
        return $pub === null ? null : (int)$pub['price'] * 0.8;
    }

    /**
     * 发武将（get_own_by_id 已拥有 → 按酒馆价格 × 0.8 发紫宝石；否则 add_general + 酒馆日志 add_pub_log(gid, 1, 11)）
     *
     * @return array{owned: bool, jade: float|null, general: array<string, mixed>|null}
     */
    public static function grantGeneral(int $uid, int $gid, string $class, string $method, int $now): array
    {
        if (General::ownsTemplate($uid, $gid)) {
            $jade = self::generalJade($gid);
            if ($jade !== null) {
                TraderLog::addJade($uid, 3, (int)$jade, class: $class, method: $method, now: $now);
            }
            return ['owned' => true, 'jade' => $jade, 'general' => null];
        }
        $general = General::addGeneral($uid, $gid);
        if ($general !== null) {
            PubLog::add($uid, $gid, 1, 11, $now);
        }
        return ['owned' => false, 'jade' => null, 'general' => $general];
    }

    /**
     * 发放没有展示信息的资源（各 get_reward 里相同的分支：2 ~ 7、9 ~ 11、13 ~ 16、27、28；元宝 1 由调用方处理）
     *
     * 军令 13 旧版是 add_act(1, $reward_info['value'])，由调用方传入要加的数量。
     *
     * @return bool 是否是这里处理的类型
     */
    public static function grantBasic(int $uid, int $type, int $num, string $class, string $method, int $now): bool
    {
        switch ($type) {
            case 2: // 银币
                Member::addCurrency($uid, silver: $num);
                return true;
            case 3: // 黄宝石
                TraderLog::addJade($uid, 2, $num, class: $class, method: $method, now: $now);
                return true;
            case 4: // 紫宝石
                TraderLog::addJade($uid, 3, $num, class: $class, method: $method, now: $now);
                return true;
            case 5: // 原魂
                Member::addCurrency($uid, soul: $num);
                return true;
            case 6: // 玉石
                MemberJade::add($uid, $num, $class, $method, $now);
                return true;
            case 7: // 声望
                Member::addReputation($uid, $num);
                return true;
            case 9: // 宝石碎片
                OwnGem::addChip($uid, $num, $class, $method, $now);
                return true;
            case 10: // 突飞卡
                FreeLeap::add($uid, $num, $now);
                return true;
            case 11: // 蓝宝石
                TraderLog::addJade($uid, 1, $num, class: $class, method: $method, now: $now);
                return true;
            case 13: // 军令（add_act(1, n)：不受军令上限限制）
                Member::addCurrency($uid, act: $num);
                return true;
            case 14: // 精炼石
                MemberStone::add($uid, $num, MemberStone::REFINE, $class, $method, $now);
                return true;
            case 15: // 五彩石
                MemberStone::add($uid, $num, MemberStone::COLOR, $class, $method, $now);
                return true;
            case 16: // 上古血石
                MemberStone::add($uid, $num, MemberStone::BLOOD, $class, $method, $now);
                return true;
            case 27: // 玫瑰花
                MemberIntegral::change($uid, $num, 'beauty_rose', 1, $class, $method, $now);
                return true;
            case 28: // 钻石
                MemberIntegral::change($uid, $num, 'beauty_diamond', 1, $class, $method, $now);
                return true;
        }
        return false;
    }

    /**
     * 计划任务发奖并返回信件里的奖励描述（cron_acttask 的 tanabata_reward / celebration_reward，country.php 的 celebration_reward）
     *
     * $info：type、num、value（装备 / 武将 / 坐骑 / 精华 num 为 id、value 为数量）。$beauty 为 false 时（七夕）不处理玫瑰 / 钻石。
     * 照旧版：元宝 1 不掉进银币分支；军令 13 加的是 value、信件里写的是 num；装备模板不存在时仍拼「装备-」。
     *
     * @param array{type: int, num?: int, value?: int} $info
     */
    public static function cronGrant(int $uid, array $info, bool $beauty, string $class, string $method, int $now): string
    {
        $type = (int)$info['type'];
        $num = (int)($info['num'] ?? 0);
        $id = 0;
        // 装备、武将、坐骑、装备精华单独处理
        if (self::isItemType($type)) {
            $id = $num;
            $num = (int)($info['value'] ?? 0);
        }
        $lang = self::LANG_CRON;
        switch ($type) {
            case 1: // 元宝
                Member::addCurrency($uid, gold: $num);
                return $lang[1] . $num . ';';
            case 8: // 装备
                $item = self::equipInfo($id);
                if ($item !== null) {
                    Equipment::addFromTemplate($uid, $id, $num);
                }
                return $lang['cron_equip'] . ($item['name'] ?? '') . ';';
            case 12: // 武将
                $result = self::grantGeneral($uid, $id, $class, $method, $now);
                if ($result['owned']) {
                    return $result['jade'] !== null ? $lang['cron_change_purple'] . $result['jade'] . ';' : '';
                }
                return $result['general'] !== null ? $lang['cron_general'] . $result['general']['name'] . ';' : '';
            case 13: // 军令
                Member::addCurrency($uid, act: (int)($info['value'] ?? 0));
                return $lang[13] . $num . ';';
            case 17: case 18: case 19: case 20: case 21: case 22: case 23: case 24: case 25: // 装备精华
                $ess = self::essenceInfo($id);
                if ($ess === null) {
                    return '';
                }
                Equipment::addTowerItem($uid, $id, $num);
                return $ess['name'] . $lang['cron_good'] . ';';
            case 26: // 坐骑
                OwnMount::addMount($uid, $id, 0, $num, $class, $method, $now);
                return $lang['cron_mount'] . (MountTemp::ROWS[$id]['name'] ?? '') . ';';
            case 27:
            case 28:
                if (!$beauty) {
                    return '';
                }
                break;
        }
        if (self::grantBasic($uid, $type, $num, $class, $method, $now)) {
            return ($lang[$type] ?? '') . $num . ';';
        }
        return '';
    }
}
