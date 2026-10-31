<?php

declare(strict_types=1);

namespace Hanjiang\Handler\General;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\MasterData\Skill;
use Hanjiang\MasterData\Talent;
use Hanjiang\Model\Advanced;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\FreeLeap;
use Hanjiang\Model\Member;
use Hanjiang\Model\OwnSoul;
use Hanjiang\Model\TraderLog;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 名将进阶（移植旧版 controllers/advanced.php + models/advanced_model.php，路径相对旧版 s1/application/）
 *
 * 主公 100 级开放。每个将领有品级（1 ~ 7，存 advanced_log 并同步 general.quality）与四个被动技能（1 物攻 / 策攻、2 物防、3 策防、
 * 4 生命，0 ~ 25 级）：技能升级消耗突飞卡（member_field.freeleap），加成加到战斗的天赋系数上；技能都达到要求且将领等级够时可进阶，
 * 消耗黄 / 紫宝石（TraderLog）、原魂、声望，增加三围与血量。进阶传承把一个将领的进阶数据整体移到品级更低的将领身上，
 * 有 VIP 决定的 CD（member_field.advanced_time）。存储与配置见 Model\Advanced。
 * 加成计入组队（TeamBuilder）、推演（BattleEngine）与将领界面（GeneralHandler）。
 *
 * 客户端调用（advancedpop.js）：index、get_general_info、get_skill_info、level_up、start_advanced、advanced_inherit、
 * get_advanced_inherit_info，全部移植。
 * 将领界面的三围与血量（get_general_info）同 GeneralHandler::enrich（装备精炼 / 强化成就、精炼成就、兵符宝石、主公天赋、名将进阶、
 * 名将成就、美人、主公星魄、国家学院、坐骑、将魂）。技能升级消耗突飞卡走 FreeLeap::sub（记 leap_log）。
 * 并发：level_up / start_advanced / advanced_inherit 在 Member::locked 玩家行锁内执行。
 *
 * 依赖未移植：突飞卡的翻牌活动计数（tavern_model->sub_freeleap 的四方来朝 flop，旧版数据中已结束）。
 * 声望消耗由 Member::subReputation 记声望流水。
 */
final class AdvancedHandler
{
    /** 开启等级 */
    private const int OPEN_LEVEL = 100;

    /** 进阶最高等级（被动技能） */
    private const int AD_LEVEL = 25;

    /** 名将进阶最大序列ID 7（橙） */
    private const int AD_ID = 7;

    /** 进阶后的品级 => 需要的将领等级（upgrade_advance_need_level） */
    private const array NEED_LEVEL = [2 => 100, 3 => 120, 4 => 140, 5 => 160, 6 => 180, 7 => 200];

    /** 语言包（language/chinese_s/advanced_lang.php） */
    private const array LANG = [
        'advanced_wuli_attack' => '物攻天赋',
        'advanced_celue_attack' => '策攻天赋',
        'advanced_wuli_defense' => '物防天赋',
        'advanced_celue_defense' => '策防天赋',
        'advanced_shengming' => '生命天赋',
        'advanced_attack' => '攻击天赋',
    ];

    /** 出征将领列表输出的字段 */
    private const array LIST_FIELD = ['id', 'level', 'name', 'image', 'quality', 'type'];

    /**
     * 初始界面: c=advanced&m=index
     *
     * 出征将领列表与第一个将领的进阶信息、资源、进阶传承 CD。
     */
    public static function index(Context $ctx): array
    {
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        // 判断功能是否开启
        if (!self::isOpen($member)) {
            $return['status'] = 2;
            return $return;
        }
        $uid = (int)$member['uid'];
        $now = BossClock::now();

        // 获取出征将领列表
        $list = GeneralHandler::battleList($uid);
        if ($list === []) {
            $return['status'] = -1;
            return $return;
        }

        // 获得第一个将领的信息（-1 将领信息、-2 进阶技能、-3 小技能升级信息、-4 进阶信息、-5 装备信息）
        $result = self::generalInfo($uid, $list[1]);
        if ($result['status'] !== 1) {
            $return['status'] = $result['status'];
            return $return;
        }

        // 获取剩余宝石（旧版 tavern_model->get_info 总是成功，-6 不会出现）
        $jade = TraderLog::get($uid);

        // 成功返回
        $return['status'] = 1;
        $return['list'] = self::generalList($list);
        $return['level_up'] = $result['level_up'];
        $return['quality_info'] = $result['quality_info'];
        $return['skill_info'] = $result['skill_info'];
        $return['general'] = $result['general'];
        $return['cd'] = max(0, Advanced::dateline($uid) + self::inheritDays((int)$member['vip']) * 86400 - $now);
        $return['resource'] = [
            'freeleap' => self::freeleap($uid),
            'soul' => (int)$member['soul'],
            'jade2' => $jade['get2'] ?? 0,
            'jade3' => $jade['get3'] ?? 0,
        ];
        if ((int)$member['level'] >= 140) {
            $return['resource']['reputation'] = (int)$member['reputation'];
        }
        return $return;
    }

    /**
     * 查询单个技能信息: c=advanced&m=get_skill_info（gid 将领，t 技能类型 1 ~ 4）
     */
    public static function get_skill_info(Context $ctx): array
    {
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        $gid = (int)$ctx->input('gid', 0);
        $type = (int)$ctx->input('t', 0);

        // 判断功能是否开启
        if (!self::isOpen($member)) {
            $return['status'] = 2;
            return $return;
        }
        $uid = (int)$member['uid'];

        // 判断参数
        if (!$gid || !in_array($type, [1, 2, 3, 4], true)) {
            $return['status'] = -1;
            return $return;
        }

        // 查询将领信息
        $general = self::ownGeneral($uid, $gid);
        if ($general === null) {
            $return['status'] = -1;
            return $return;
        }

        // 查询将领的进阶技能信息
        $advanced = Advanced::generalSkill($gid, $uid);
        $level = $advanced['skill' . $type . '_level'];

        $skill = [
            'level' => $level,
            'name' => $advanced['skill' . $type . '_name'],
            'value' => self::percent($advanced['skill' . $type . '_value']),
            'add_name' => self::addName($type, $general['type']),
            // 被动技能品级
            'quality' => Advanced::skillQuality($level, self::AD_ID),
            'is_full' => 0,
            'type' => $type,
        ];

        // 查询技能的升级信息
        if ($level >= self::AD_LEVEL) {
            $skill['next_value'] = '0%';
            $skill['next_use'] = 0;
            $skill['next_level'] = 0;
            $skill['is_full'] = 1;
        } else {
            $skillInfo = Advanced::skillInfo($type, $level + 1);
            if ($skillInfo === null) {
                $return['status'] = -3;
                return $return;
            }
            $skill['next_value'] = self::percent($skillInfo['value']);
            $skill['next_use'] = $skillInfo['use'];
            $skill['next_level'] = $level + 1;
        }

        // 成功返回
        $return['status'] = 1;
        $return['info'] = $skill;
        return $return;
    }

    /**
     * 获取单个将领的信息: c=advanced&m=get_general_info（gid 将领）
     */
    public static function get_general_info(Context $ctx): array
    {
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        // 判断功能是否开启
        if (!self::isOpen($member)) {
            $return['status'] = 2;
            return $return;
        }
        $uid = (int)$member['uid'];
        $general = self::ownGeneral($uid, (int)$ctx->input('gid', 0));
        if ($general === null) {
            $return['status'] = -1;
            return $return;
        }
        return self::generalInfo($uid, $general);
    }

    /**
     * 升级技能: c=advanced&m=level_up（gid 将领，t 技能类型 1 ~ 4）
     */
    public static function level_up(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $gid = (int)$ctx->input('gid', 0);
        $type = (int)$ctx->input('t', 0);
        return Member::locked($uid, static fn (): array => self::doLevelUp($uid, $gid, $type));
    }

    /**
     * 武将进阶: c=advanced&m=start_advanced（gid 将领）
     */
    public static function start_advanced(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $gid = (int)$ctx->input('gid', 0);
        return Member::locked($uid, static fn (): array => self::doStartAdvanced($uid, $gid));
    }

    /**
     * 进阶传承: c=advanced&m=advanced_inherit（gid1 被传承的将领，gid2 受益将领）
     *
     * gid1 的进阶数据（品级、技能、属性）整体写到 gid2 上，gid1 清成品级 1、技能 0 级；
     * CD：VIP 11 为 15 天、VIP 12 为 7 天、VIP 13 以上 0 天，其余 30 天。
     */
    public static function advanced_inherit(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $gid1 = (int)$ctx->input('gid1', 0);
        $gid2 = (int)$ctx->input('gid2', 0);
        return Member::locked($uid, static fn (): array => self::doInherit($uid, $gid1, $gid2, BossClock::now()));
    }

    /**
     * 获取可传承的数据: c=advanced&m=get_advanced_inherit_info（gid 将领）
     *
     * advanced 为该将领的进阶行，另加 skill1 ~ 4_addname，skill1 ~ 4_value 改为百分比文字。
     */
    public static function get_advanced_inherit_info(Context $ctx): array
    {
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        // 判断功能是否开启
        if (!self::isOpen($member)) {
            $return['status'] = 2;
            return $return;
        }
        $uid = (int)$member['uid'];
        $gid = (int)$ctx->input('gid', 0);

        // 判断参数
        if (!$gid) {
            $return['status'] = -1;
            return $return;
        }

        // 查询将领信息
        $general = self::ownGeneral($uid, $gid);
        if ($general === null) {
            $return['status'] = -2;
            return $return;
        }

        // 获取将领的被动技能信息
        $info = Advanced::generalSkill($gid, $uid);
        for ($i = 1; $i <= 4; $i++) {
            $addName = '';
            if ($i === 1 && $general['type'] <= 2) {
                $addName = self::LANG['advanced_attack'];
            } elseif ($i === 2) {
                $addName = self::LANG['advanced_wuli_defense'];
            } elseif ($i === 3) {
                $addName = self::LANG['advanced_celue_defense'];
            } elseif ($i === 4) {
                $addName = self::LANG['advanced_shengming'];
            }
            $info['skill' . $i . '_addname'] = $addName;
            $info['skill' . $i . '_value'] = self::percent($info['skill' . $i . '_value']);
        }

        // 成功返回
        $return['status'] = 1;
        $return['advanced'] = $info;
        return $return;
    }

    /**
     * level_up 的主体（玩家行锁内）
     *
     * @return array<string, mixed>
     */
    private static function doLevelUp(int $uid, int $gid, int $type): array
    {
        /**
         * -8 当前品级不能再升级该技能 / 获取下一级升级信息失败
         * -7 升级失败
         * -6 突飞卡不足
         * -5 获取升级信息失败
         * -4 技能已满级
         * -3 获取进阶信息失败
         * -2 获取将领信息失败
         * -1 参数错误
         *  1 成功
         *  2 功能未开启
         */
        $return = ['status' => 0];
        $member = self::member($uid);
        if ($member === null) {
            return $return;
        }
        // 判断功能是否开启
        if (!self::isOpen($member)) {
            $return['status'] = 2;
            return $return;
        }

        // 判断参数
        if (!$gid || !in_array($type, [1, 2, 3, 4], true)) {
            $return['status'] = -1;
            return $return;
        }

        // 获取将领信息
        $general = self::ownGeneral($uid, $gid);
        if ($general === null) {
            $return['status'] = -2;
            return $return;
        }

        // 查询将领的进阶技能信息
        $advanced = Advanced::generalSkill($gid, $uid);
        $level = $advanced['skill' . $type . '_level'];

        // 判断是否满级
        if ($level >= self::AD_LEVEL) {
            $return['status'] = -4;
            return $return;
        }

        // 判断当前品级是否可以升级（18、19 级按每 4 级一品算，其余每 3 级一品）
        $k = 3;
        if ($level >= 18 && $level < 20) {
            $k = 4;
        }
        if (min((int)ceil(($level + 1) / $k), self::AD_ID) > $advanced['quality']) {
            $return['status'] = -8;
            return $return;
        }

        // 获取下一级升级和消耗信息
        $skillInfo = Advanced::skillInfo($type, $level + 1);
        if ($skillInfo === null) {
            $return['status'] = -5;
            return $return;
        }

        // 判断资源是否足够，扣除突飞卡（条件扣除，不足时不扣）
        $freeleap = self::subFreeleap($uid, $skillInfo['use']);
        if ($freeleap === null) {
            $return['status'] = -6;
            return $return;
        }

        // 执行升级
        if (Advanced::updateSkill($uid, $gid, $skillInfo['type'], $skillInfo['level'], (float)$skillInfo['value']) !== 1) {
            $return['status'] = -7;
            return $return;
        }

        // 升级后技能信息
        $skill = [
            'level' => $skillInfo['level'],
            'name' => $skillInfo['name'],
            'value' => self::percent($skillInfo['value']),
            'type' => $skillInfo['type'],
            // 被动技能品级
            'quality' => Advanced::skillQuality($skillInfo['level'], self::AD_ID),
            'is_full' => 0,
            'add_name' => self::addName($type, $general['type']),
        ];

        // 同步信息
        $advanced['skill' . $skill['type'] . '_level'] = $skill['level'];

        // 查询下一级的升级信息
        if ($skillInfo['level'] >= self::AD_LEVEL) {
            $skill['next_value'] = '0%';
            $skill['next_use'] = 0;
            $skill['next_level'] = 0;
            $skill['is_full'] = 1;
        } else {
            $next = Advanced::skillInfo($type, $skillInfo['level'] + 1);
            if ($next === null) {
                $return['status'] = -8;
                return $return;
            }
            $skill['next_value'] = self::percent($next['value']);
            $skill['next_use'] = $next['use'];
            $skill['next_level'] = $next['level'];
        }

        if ($skillInfo['type'] === 4) {
            // 如果升级的技能是增加血量的，特殊处理（重新计算将领信息）
            $result = self::generalInfo($uid, $general);
            $return['level_up'] = $result['level_up'] ?? null;
            $return['general'] = $result['general'] ?? null;
        } else {
            // 获取进阶按钮状态
            $return['level_up'] = 0;
            $return['general'] = [];
            if ($advanced['quality'] < self::AD_ID) {
                $info = Advanced::advancedInfo($advanced['quality'] + 1);
                // 判断将领等级、被动技能等级是否满足进阶条件
                if ($info !== null && $general['level'] >= self::NEED_LEVEL[$advanced['quality'] + 1]) {
                    $status = 1;
                    for ($i = 1; $i <= 4; $i++) {
                        if ($advanced['skill' . $i . '_level'] < $info['skill_level']) {
                            $status = 0;
                        }
                    }
                    if ($status === 1) {
                        $return['level_up'] = 1;
                    }
                }
            }
        }

        // 成功返回
        $return['status'] = 1;
        $return['skill'] = $skill;
        $return['freeleap'] = $freeleap;
        return $return;
    }

    /**
     * start_advanced 的主体（玩家行锁内）
     *
     * @return array<string, mixed>
     */
    private static function doStartAdvanced(int $uid, int $gid): array
    {
        /**
         * -11 声望不足
         * -10 原魂不足
         * -9 紫宝石不足
         * -8 黄宝石不足
         * -6 进阶条件不满足（将领等级 / 被动技能等级）
         * -5 获取进阶信息失败
         * -4 已达最高品级
         * -3 获取进阶技能信息失败
         * -2 获取将领信息失败
         * -1 参数错误
         *  1 成功
         *  2 功能未开启
         */
        $return = ['status' => 0];
        $member = self::member($uid);
        if ($member === null) {
            return $return;
        }
        // 判断功能是否开启
        if (!self::isOpen($member)) {
            $return['status'] = 2;
            return $return;
        }

        // 判断参数
        if (!$gid) {
            $return['status'] = -1;
            return $return;
        }

        // 获取将领信息
        $general = self::ownGeneral($uid, $gid);
        if ($general === null) {
            $return['status'] = -2;
            return $return;
        }

        // 判断武将进阶等级是否已满
        if ($general['quality'] >= self::AD_ID) {
            $return['status'] = -4;
            return $return;
        }

        // 查询将领的进阶技能信息
        $advanced = Advanced::generalSkill($gid, $uid);
        if ($advanced['quality'] >= self::AD_ID) {
            $return['status'] = -4;
            return $return;
        }

        // 获取进阶信息
        $qualityInfo = Advanced::advancedInfo($advanced['quality'] + 1);
        if ($qualityInfo === null) {
            $return['status'] = -5;
            return $return;
        }

        // 判断进阶条件是否满足
        if ($general['level'] < self::NEED_LEVEL[$advanced['quality'] + 1]) {
            $return['status'] = -6;
            return $return;
        }
        for ($i = 1; $i <= 4; $i++) {
            if ($advanced['skill' . $i . '_level'] < $qualityInfo['skill_level']) {
                $return['status'] = -6;
                return $return;
            }
        }

        // 获取宝石（旧版 tavern_model->get_info 总是成功，-7 不会出现）
        $jade = TraderLog::get($uid) ?? ['get2' => 0, 'get3' => 0];
        $jade2 = $jade['get2'];
        $jade3 = $jade['get3'];

        // 判断资源是否足够（1 黄宝石、2 紫宝石、3 原魂、4 声望）
        for ($i = 1; $i <= 4; $i++) {
            $useType = $qualityInfo['use_type' . $i];
            $useValue = $qualityInfo['use_value' . $i];
            if ($useType === 1 && $jade2 < $useValue) {
                $return['status'] = -8;
                return $return;
            } elseif ($useType === 2 && $jade3 < $useValue) {
                $return['status'] = -9;
                return $return;
            } elseif ($useType === 3 && (int)$member['soul'] < $useValue) {
                $return['status'] = -10;
                return $return;
            } elseif ($useType === 4 && (int)$member['reputation'] < $useValue) {
                $return['status'] = -11;
                return $return;
            }
        }

        // 扣除资源（已在玩家行锁内校验过数量，下面的条件扣除不会失败）
        $soul = (int)$member['soul'];
        for ($i = 1; $i <= 4; $i++) {
            $useType = $qualityInfo['use_type' . $i];
            $useValue = $qualityInfo['use_value' . $i];
            if ($useType === 1) {
                // 黄宝石
                TraderLog::subJade($uid, 2, $useValue, 'advanced', 'start_advanced');
                $jade2 -= $useValue;
            } elseif ($useType === 2) {
                // 紫宝石
                TraderLog::subJade($uid, 3, $useValue, 'advanced', 'start_advanced');
                $jade3 -= $useValue;
            } elseif ($useType === 3) {
                // 原魂
                OwnSoul::subSoul($uid, $useValue, 'advanced', 'start_advanced');
                $soul -= $useValue;
            } elseif ($useType === 4 && $useValue > 0) {
                // 声望
                Member::subReputation($uid, $useValue);
            }
        }

        // 开始进阶
        Advanced::updateAdvanced($uid, $gid, [
            'quality' => $qualityInfo['id'],
            'wuli' => $advanced['wuli'] + $qualityInfo['wuli'],
            'zhili' => $advanced['zhili'] + $qualityInfo['zhili'],
            'tili' => $advanced['tili'] + $qualityInfo['tili'],
            'hp' => $advanced['hp'] + $qualityInfo['hp'],
        ]);
        $result = self::generalInfo($uid, $general);
        $result['general']['quality'] = $qualityInfo['id'];

        // 成功返回
        $return['status'] = 1;
        $return['level_up'] = $result['level_up'] ?? null;
        $return['quality_info'] = $result['quality_info'] ?? null;
        $return['skill_info'] = $result['skill_info'] ?? null;
        $return['general'] = $result['general'];
        $return['resource'] = [
            'freeleap' => self::freeleap($uid),
            'soul' => $soul,
            'jade2' => $jade2,
            'jade3' => $jade3,
        ];
        if ((int)$member['level'] >= 140) {
            // 旧版 sub_reputation 不回写 $this->member['reputation']，这里同样返回扣除前的声望
            $return['resource']['reputation'] = (int)$member['reputation'];
        }
        return $return;
    }

    /**
     * advanced_inherit 的主体（玩家行锁内）
     *
     * @return array<string, mixed>
     */
    private static function doInherit(int $uid, int $gid1, int $gid2, int $now): array
    {
        /**
         * -6 被传承的将领品级不高于受益将领
         * -5 没有可传承的数据
         * -4 获取进阶信息失败
         * -3 获取将领信息失败
         * -2 传承 CD 中
         * -1 参数错误 / 没有出征将领
         *  1 成功
         *  2 功能未开启
         */
        $return = ['status' => 0];
        $member = self::member($uid);
        if ($member === null) {
            return $return;
        }
        // 判断功能是否开启
        if (!self::isOpen($member)) {
            $return['status'] = 2;
            return $return;
        }

        // 判断参数
        if (!$gid1 || !$gid2) {
            $return['status'] = -1;
            return $return;
        }

        $day = self::inheritDays((int)$member['vip']);
        // 判断cd
        if (Advanced::dateline($uid) + $day * 86400 > $now) {
            $return['status'] = -2;
            return $return;
        }

        // 查询将领信息（两个将领都要是自己的；同一个将领只查到一个，同样失败）
        $generals = [];
        foreach (GeneralHandler::ownLegacyList($uid, [$gid1, $gid2]) as $row) {
            $generals[$row['id']] = $row;
        }
        if (count($generals) < 2) {
            $return['status'] = -3;
            return $return;
        }

        // 获取将领的进阶信息
        $gid1Info = Advanced::generalSkill($gid1, $uid);
        $gid2Info = Advanced::generalSkill($gid2, $uid);

        // 判断是否有数据可传承
        if ($gid1Info['quality'] === 1) {
            $advancedStatus = 0;
            for ($i = 1; $i <= 4; $i++) {
                if ($gid1Info['skill' . $i . '_level'] > 0) {
                    $advancedStatus = 1;
                }
            }
            if ($advancedStatus === 0) {
                $return['status'] = -5;
                return $return;
            }
        }

        // 判断被传承的武将能力是否大过受益武将
        if ($gid1Info['quality'] <= $gid2Info['quality']) {
            $return['status'] = -6;
            return $return;
        }

        // 修改cd信息
        Advanced::updateDateline($uid, $now);
        // 修改受益武将进阶信息
        Advanced::updateAll($gid1Info, $gid2, $uid);
        // 修改被传承的武将进阶信息
        Advanced::updateAll([], $gid1, $uid);

        // 返回被传承的武将进阶信息
        $result = self::generalInfo($uid, $generals[$gid1]);

        // 获取出征将领列表
        $list = GeneralHandler::battleList($uid);
        if ($list === []) {
            $return['status'] = -1;
            return $return;
        }

        // 添加进阶传承记录
        Advanced::addInheritLog($uid, $gid1Info, $gid2Info, $now);

        // 成功返回
        $return['status'] = 1;
        $return['cd'] = $day * 86400;
        $return['general'] = $result['general'] ?? [];
        $return['general']['quality'] = 1;
        $return['quality_info'] = $result['quality_info'] ?? null;
        $return['skill_info'] = $result['skill_info'] ?? null;
        $return['level_up'] = $result['level_up'] ?? null;
        $return['list'] = self::generalList($list);
        return $return;
    }

    /**
     * 单个将领的进阶信息（移植 get_general_info($info, 1)：返回 status / level_up / quality_info / skill_info / general）
     *
     * status：1 成功，-5 获取将领装备信息失败（旧版 -2 / -3 / -4 来自取不到的配置，MasterData 齐全时不会出现）。
     *
     * @param array<string, mixed> $general 旧版 own_general 形状的行（GeneralHandler::battleList / ownLegacyList）
     * @return array<string, mixed>
     */
    private static function generalInfo(int $uid, array $general): array
    {
        $return = ['status' => 0];

        // 获得将领的进阶技能信息
        $advanced = Advanced::generalSkill($general['id'], $uid);
        $level1 = $advanced['skill1_level'];

        // 获取增加的属性名称
        $addName = '';
        if ($general['type'] === 1) {
            $addName = self::LANG['advanced_wuli_attack'];
        } elseif ($general['type'] === 2) {
            $addName = self::LANG['advanced_celue_attack'];
        }

        $skillInfo = [
            'level' => $level1,
            'name' => $advanced['skill1_name'],
            'value' => self::percent($advanced['skill1_value']),
            'add_name' => $addName,
            // 被动技能品级
            'quality' => Advanced::skillQuality($level1, self::AD_ID),
            'is_full' => 0,
            'type' => 1,
        ];

        // 查询技能的升级信息
        if ($level1 >= self::AD_LEVEL) {
            $skillInfo['next_value'] = '0%';
            $skillInfo['next_use'] = 0;
            $skillInfo['next_level'] = 0;
            $skillInfo['is_full'] = 1;
        } else {
            $next = Advanced::skillInfo(1, $level1 + 1);
            if ($next === null) {
                $return['status'] = -3;
                return $return;
            }
            $skillInfo['next_value'] = self::percent($next['value']);
            $skillInfo['next_use'] = $next['use'];
            $skillInfo['next_level'] = $level1 + 1;
        }

        // 获取进阶信息（已满品级：当前品级的信息，level_up 2；否则下一品级的信息，满足条件 level_up 1）
        if ($advanced['quality'] >= self::AD_ID) {
            $levelUp = 2;
            $qualityInfo = Advanced::advancedInfo($advanced['quality']);
            if ($qualityInfo === null) {
                $return['status'] = -4;
                return $return;
            }
            $qualityInfo['general_level'] = self::NEED_LEVEL[$advanced['quality']];
        } else {
            $qualityInfo = Advanced::advancedInfo($advanced['quality'] + 1);
            if ($qualityInfo === null) {
                $return['status'] = -4;
                return $return;
            }
            $qualityInfo['general_level'] = self::NEED_LEVEL[$advanced['quality'] + 1];
            // 判断进阶条件
            $levelUp = 1;
            if ($general['level'] < $qualityInfo['general_level']) {
                $levelUp = 0;
            }
            if ($levelUp === 1) {
                for ($i = 1; $i <= 4; $i++) {
                    if ($advanced['skill' . $i . '_level'] < $qualityInfo['skill_level']) {
                        $levelUp = 0;
                    }
                }
            }
        }

        // 武将装备精炼 / 强化成就 / 精炼成就、兵符宝石、主公天赋、名将进阶、名将成就、美人、星魄、坐骑、将魂（同将领界面）
        $enriched = GeneralHandler::enrich($uid, $general);
        if ($enriched === null) {
            // 装备不存在
            $return['status'] = -5;
            return $return;
        }
        // 将领技能 / 天赋只输出名称（get_ext_by_id(...)['name']；取不到时为 null）
        $enriched['skill'] = Skill::ROWS[$general['skillid']]['name'] ?? null;
        $enriched['talent'] = Talent::ROWS[$general['talentid']]['name'] ?? null;

        // 将领信息返回字段筛选
        $newgeneral = [];
        foreach (['id', 'name', 'level', 'generalid', 'image', 'type', 'wuli', 'zhili', 'tili', 'wuliup', 'zhiliup', 'tiliup',
            'star', 'turn', 'quality', 'skill', 'talent', 'hp'] as $val) {
            $newgeneral[$val] = $enriched[$val];
        }
        // 名将进阶被动技能合并（这里的品级旧版只按 ceil(等级 / 3) 算）
        for ($i = 1; $i <= 4; $i++) {
            $level = $advanced['skill' . $i . '_level'];
            $newgeneral['skill' . $i . '_level'] = $level;
            $newgeneral['skill' . $i . '_name'] = $advanced['skill' . $i . '_name'];
            $newgeneral['skill' . $i . '_quality'] = $level > 0 ? (int)min(ceil($level / 3), self::AD_ID) : 1;
        }

        // 成功返回
        $return['status'] = 1;
        $return['level_up'] = $levelUp;
        $return['quality_info'] = $qualityInfo;
        $return['skill_info'] = $skillInfo;
        $return['general'] = $newgeneral;
        return $return;
    }

    /**
     * 增加的属性名称（get_skill_info / level_up：技能 1 按将领职业分物攻 / 策攻）
     */
    private static function addName(int $type, int $generalType): string
    {
        if ($type === 1 && $generalType === 1) {
            return self::LANG['advanced_wuli_attack'];
        } elseif ($type === 1 && $generalType === 2) {
            return self::LANG['advanced_celue_attack'];
        } elseif ($type === 2) {
            return self::LANG['advanced_wuli_defense'];
        } elseif ($type === 3) {
            return self::LANG['advanced_celue_defense'];
        } elseif ($type === 4) {
            return self::LANG['advanced_shengming'];
        }
        return '';
    }

    /**
     * 技能加成的百分比文字（旧版 $value * 100 .'%'，浮点按 precision 14 转字符串）
     */
    private static function percent(int|float $value): string
    {
        return $value * 100 . '%';
    }

    /**
     * 进阶传承 CD 天数：VIP 11 为 15 天、VIP 12 为 7 天、VIP 13 以上 0 天，其余 30 天
     */
    private static function inheritDays(int $vip): int
    {
        return match (true) {
            $vip >= 13 => 0,
            $vip >= 12 => 7,
            $vip >= 11 => 15,
            default => 30,
        };
    }

    /**
     * 把将领列表中的多余字段筛选出去
     *
     * @param array<int, array<string, mixed>> $list
     * @return list<array<string, mixed>>
     */
    private static function generalList(array $list): array
    {
        $generallist = [];
        foreach ($list as $val) {
            $row = [];
            foreach (self::LIST_FIELD as $field) {
                $row[$field] = $val[$field];
            }
            $generallist[] = $row;
        }
        return $generallist;
    }

    /**
     * 本人的将领（旧版 general_model->get_own_by_id(单个)）；不存在返回 null
     *
     * @return array<string, mixed>|null
     */
    private static function ownGeneral(int $uid, int $gid): ?array
    {
        if ($gid <= 0) {
            return null;
        }
        return GeneralHandler::ownLegacyList($uid, $gid)[0] ?? null;
    }

    /**
     * 剩余突飞卡（member_field.freeleap）
     */
    private static function freeleap(int $uid): int
    {
        return (int)(DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->value('freeleap') ?? 0);
    }

    /**
     * 扣除突飞卡（tavern_model->sub_freeleap：条件扣除，不足返回 null，成功返回剩余数量）
     */
    private static function subFreeleap(int $uid, int $num): ?int
    {
        // tavern_model->sub_freeleap（记突飞卡流水 leap_log）
        if (FreeLeap::sub($uid, $num) !== 1) {
            return null;
        }
        // 主城信息缓存里有 member_field
        Redis::del('player:' . $uid);
        return self::freeleap($uid);
    }

    /**
     * @param array<string, mixed> $member
     */
    private static function isOpen(array $member): bool
    {
        return (int)$member['level'] >= self::OPEN_LEVEL;
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
