<?php

declare(strict_types=1);

namespace Hanjiang\Handler\City;

use Hanjiang\Handler\General\TavernHandler;
use Hanjiang\MasterData\Equip as EquipTemplate;
use Hanjiang\MasterData\General as GeneralTemplate;
use Hanjiang\MasterData\Pub;
use Hanjiang\MasterData\Skill;
use Hanjiang\MasterData\Soul;
use Hanjiang\MasterData\Talent;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\Chat;
use Hanjiang\Model\Equipment;
use Hanjiang\Model\General;
use Hanjiang\Model\Member;
use Hanjiang\Model\MemberJade;
use Hanjiang\Model\OwnSoul;
use Hanjiang\Model\Vip;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * VIP 特权与 VIP 等级礼包（移植旧版 controllers/vip.php + models/vip_model.php，路径相对旧版 s1/application/）
 *
 * VIP 等级由充值累计金额决定（Model\Pay::complete → Model\Vip::pay，存 vip_log 并同步 member.vip）。VIP 规则取 Vip 表、
 * 等级礼包取 VipReward 表（get_vip_info 合并）；礼包须按等级依次领取，已领到的等级记在 vip_log.gift_num。
 *
 * 客户端调用：index、get_gift（vippop.js）、get_vip_reward（firstpaypop.js），全部移植。
 * 旧版 update_member_vip（后台批量重算 VIP 10）客户端不调用，不移植。
 * 文案取旧版 chinese_s 语言包（越南版分支不移植）。
 * 并发：get_gift 以 vip_log.gift_num 条件更新先占领取资格，同一级礼包并发领取只发一次。
 *
 * 依赖未移植：酒馆获得日志 tavern_model->add_pub_log(generalid, 1, 4)。
 */
final class VipHandler
{
    /** 将魂奖励的等级（$_soul_level） */
    private const int SOUL_LEVEL = 15;

    /** 已拥有的武将按酒馆价格折算宝石的折扣 */
    private const float REBATE = 0.8;

    /** 旧版语言包（vip_lang.php / base_lang.php / general_lang.php） */
    private const array LANG = [
        'base_ordinary' => '普通',
        'base_enhance' => '加强',
        'base_advanced' => ' 高级',
        'base_platina' => '白金',
        'base_jewel' => '钻石',
        'base_extreme' => '至尊',
        'vip_white_signet' => '可在商店购买白色兵符',
        'vip_green_signet' => '可在商店购买绿色兵符',
        'vip_blue_signet' => '可在商店购买蓝色兵符',
        'vip_golden_signet' => '可在商店购买金色兵符',
        'vip_renwang_signet' => '可在商店购买人王印',
        'vip_emperor_signet' => '可在商店购买帝皇印',
        'vip_monarch_signet' => '可在商店购买君主印',
        'vip_gulong_signet' => '可在商店购买古龙印',
        'vip_tiandi_signet' => '可在商店购买天帝印',
        'vip_practice_site' => '训练位',
        'vip_practice_buy' => '使用元宝购买一个额外的武将训练位',
        'vip_workshop_site' => '采集位',
        'vip_workshop_buy' => '使用元宝购买一个额外的玉石采集位',
        'vip_practice_effect' => '永久获得%s武将训练效果',
        'vip_workshop_effect' => '永久获得%s玉石采集效果',
        'vip_cultivate' => '培养',
        'vip_inherit' => '继承',
        'vip_cultivate_open' => '开启%s培养功能',
        'vip_inherit_open' => '开启%s继承功能',
        'vip_strengthen' => '强化暴击:强化装备时有几率暴击，等级提升×2',
        'vip_leap' => '突飞暴击:突飞猛进时有几率暴击，获得经验×2',
        'vip_clear_cd' => '所有CD消失，全功能没有冷却时间',
        'vip_act_limit' => '军令上限增加到%s个',
        'vip_trade' => '强行贸易 每次贸易必出下一商人',
        'vip_insight' => '主公天赋顿悟增加100%成功选项。',
        'vip_insight_up' => '主公天赋升星暴击',
        'vip_team' => '至尊组队:团队副本中增加至尊组队功能。',
        'vip_not_open' => '神秘功能，暂未开启！',
        'vip_force' => '扫荡:可对关卡NPC使用扫荡功能。',
        'vip_herothrone' => '英雄王座挑战某关失败后，可花费元宝继续挑战',
        'vip_herothrone_times' => '每日可购买%s次英雄王座副本挑战次数',
        'vip_free_inherit' => '每隔30天可使用一次免费至尊继承功能',
        'vip_free_ordinary_inherit' => '每隔30天可使用一次免费普通继承功能',
        'vip_force_occupy' => '至尊攻城:君主可使用至尊攻城功能。',
        'vip_free_platina_inherit' => '每隔30天可使用一次免费白金继承功能',
        'vip_inherit_half_price' => '武将继承:全部武将继承永久5折',
        'vip_matrix_two_defense' => '开放顶级阵法-壁垒阵，增加双防御',
        'vip_matrix_two_attack' => '开放顶级阵法-冲锋阵，增加双攻击',
        'vip_matrix_supper' => '增加神级阵法-至尊阵，强化双攻击、双防御，同时阵法不再有位置限制，随意布置。',
        'vip_deprecition_seven' => '可以使用名将折旧功能，折旧返还30%的宝石。',
        'vip_deprecition_eight' => '可以使用名将折旧功能，折旧返还40%的宝石。',
        'vip_deprecition_nine' => '可以使用名将折旧功能，折旧返还50%的宝石。',
        'vip_deprecition_ten' => '可以使用名将折旧功能，折旧返还60%的宝石。',
        'vip_free_intensify_inherit' => '每隔30天可使用一次免费加强继承功能',
        'vip_advanced_inherit_fifteen_day' => '每隔15天可使用一次进阶传承功能。',
        'vip_advanced_inherit_seven_day' => '每隔7天可使用一次进阶传承功能。',
        'vip_give_first_stone' => '每日赠送10个盘龙源石',
        'vip_give_second_stone' => '每日赠送10个仙王玉石',
        'vip_guve_third_stone' => '每日赠送10个九窍灵石',
        'vip_epic_lock_times' => '天下史诗每日锁定次数+%s',
        'vip_epic_refresh_times' => '天下史诗每日刷新次数+%s',
        'vip_sale_item_price' => '装备出售返还100%银币',
        'vip_smatch_up' => '威震天下获得声望有概率2倍暴击',
        'vip_advanced_inherit_no_cd' => '进阶传承功能永久无CD',
        'vip_smatch_rate_up' => '威震天下获得声望大概率2倍暴击，小概率4倍暴击',
        'vip_oversea10' => '可购买海外贸易打劫次数5次',
        'vip_oversea11' => '可购买海外贸易打劫次数10次',
        'vip_oversea12' => '可购买海外贸易打劫次数15次',
        'vip_oversea13' => '可购买海外贸易打劫次数20次',
        'general_get' => '恭喜玩家【%s】获得%s星武将-%s',
    ];

    /** 培养 / 继承名称（init：_culture_name_arr） */
    private const array CULTURE_NAME = [1 => 'base_ordinary', 2 => 'base_enhance', 3 => 'base_platina', 4 => 'base_extreme'];

    /** 训练位 / 采集位名称（init：_practice_name_arr） */
    private const array PRACTICE_NAME = [
        1 => 'base_ordinary', 2 => 'base_enhance', 3 => 'base_advanced', 4 => 'base_platina', 5 => 'base_jewel', 6 => 'base_extreme',
    ];

    /** 商店可购买的兵符 / 印（init：_shop_ame_arr，按 VIP 等级） */
    private const array SHOP_NAME = [
        0 => 'vip_white_signet', 1 => 'vip_green_signet', 2 => 'vip_blue_signet', 3 => 'vip_golden_signet',
        4 => 'vip_renwang_signet', 5 => 'vip_monarch_signet', 6 => 'vip_emperor_signet', 7 => 'vip_gulong_signet',
        8 => 'vip_tiandi_signet',
    ];

    /**
     * VIP 页面初始信息: c=vip&m=index
     *
     * level 为当前 VIP 等级，exp 为累计充值元宝（人民币 × 10），top_exp 为下一级所需元宝；list 按 VIP 等级列出特权说明
     * （left）、礼包内容（right）与礼包状态（status：0 不可领取、1 可领取、2 已领取）。
     */
    public static function index(Context $ctx): array
    {
        /**
         * -1 没有 vip 信息
         *  1 成功
         */
        $return = [];
        $uid = MemberHandler::resolveUid($ctx);
        // 取 vip 规则信息
        $list = Vip::info();
        if ($list === []) {
            // 没有 vip 信息
            return ['status' => -1];
        }
        // 取自己的 vip 记录（没有记录时按 0）
        $log = Vip::log($uid) ?? ['level' => 0, 'money' => 0, 'gift_num' => 0];
        $return['level'] = $log['level'];
        $return['exp'] = $log['money'] * 10;
        $level = $log['level'] + 1;
        $return['top_exp'] = 0;
        if (isset($list[$level])) {
            $return['top_exp'] = $list[$level]['toplimit'] * 10;
        }
        $return['diff'] = $return['top_exp'] - $return['exp'];
        $return['next_level'] = $level;
        if ($return['diff'] < 0) {
            $return['diff'] = 0;
            $return['next_level'] = -1;
            $return['top_exp'] = $return['exp'];
        }

        // 得到升级到下一级会奖励的东西
        $reward = [];
        $lastinfo = [];
        foreach ($list as $val) {
            $rewardList = self::rewardInfo($uid, $val, $log['gift_num'], $lastinfo);
            $rewardList['status'] = 0;
            if ($log['level'] >= $val['vip_level'] && $val['vip_level'] <= $log['gift_num'] + 1) {
                // 可领取的
                $rewardList['status'] = 1;
            }
            if ($log['gift_num'] >= $val['vip_level']) {
                // 领过的
                $rewardList['status'] = 2;
            }
            $lastinfo = $val;
            $reward[$val['vip_level']] = $rewardList;
        }

        $return['list'] = $reward;
        $return['status'] = 1;
        return $return;
    }

    /**
     * 领取 VIP 等级礼包: c=vip&m=get_gift（l VIP 等级）
     *
     * 奖励类型 get_type：1 装备、2 武将（VIP 10 以下已拥有时按酒馆价格 8 折折算宝石）、3 银币、4 黄宝石、5 紫宝石、
     * 6 蓝宝石、7 玉石、8 将魂（15 级）、9 突飞卡、10 声望。
     */
    public static function get_gift(Context $ctx): array
    {
        /**
         * -4 添加武将失败（礼包记为已领取）
         * -3 礼包已领取
         * -2 VIP 等级不足 / 须先领取前一级礼包
         * -1 没有 vip 记录
         *  1 成功
         */
        $return = [];
        $uid = MemberHandler::resolveUid($ctx);
        $level = (int)$ctx->input('l', 0);
        $now = BossClock::now();
        // 得到用户 vip 记录；没有 vip 记录就没有可领取的礼包
        $log = Vip::log($uid);
        if ($log === null) {
            return ['status' => -1];
        }
        // 用户当前 vip 等级小于传进来的等级，不能领取
        if ($log['level'] < $level) {
            return ['status' => -2];
        }
        // 传进来的 VIP 等级奖励不可领取（须按等级依次领取）
        if ($level > $log['gift_num'] + 1) {
            return ['status' => -2];
        }
        // 传进来的等级小于等于礼包领取等级，礼包已经领取过了
        if ($level <= $log['gift_num']) {
            return ['status' => -3];
        }
        // 礼包领取记录（旧版发完奖励后写；这里先占领取资格，并发领取同一级时只有一个成功）
        if (!Vip::claimGift($uid, $level, $now)) {
            return ['status' => -3];
        }

        // 处理礼包领取
        $info = Vip::info()[$level];
        for ($i = 1; $i <= 4; $i++) {
            $getType = (int)$info['get_type' . $i];
            $getValue = (int)$info['get_value' . $i];
            if ($getType === 1) {
                // 奖励道具
                $item = EquipTemplate::ROWS[$getValue] ?? null;
                if ($item !== null) {
                    Equipment::addFromTemplate($uid, $getValue);
                }
                $return['equip'][] = [
                    'itemid' => $item['id'] ?? null,
                    'itemname' => $item['name'] ?? null,
                    'itemtype' => $item['type'] ?? null,
                    'itemquality' => $item['quality'] ?? null,
                ];
            } elseif ($getType === 2) {
                // 奖励将领：已拥有且 VIP 10 以下时奖励对应宝石
                if (self::ownsGeneral($uid, $getValue) && (int)$info['vip_level'] < 10) {
                    // 已获取的时候去酒馆查询该武将售价，奖励宝石
                    $price = Pub::ROWS[$getValue] ?? null;
                    $jadenum = $price === null ? 0 : (int)ceil($price['price'] * self::REBATE);
                    if ($price !== null) {
                        TavernHandler::addJade($uid, (int)$price['paytype'], $jadenum, 'vip', 'get_gift');
                    }
                    $return['jadetype'] = $price['paytype'] ?? null;
                    $return['jadenum'] = $jadenum;
                } else {
                    if (General::createFromTemplate($uid, $getValue) === null) {
                        // 添加失败（模板不存在或 VIP 10 以上已拥有）：礼包已记为领取，直接返回
                        $return['status'] = -4;
                        return $return;
                    }
                    $tpl = GeneralTemplate::ROWS[$getValue];
                    $return['general'] = [
                        'type' => $tpl['type'],
                        'generalid' => $tpl['id'],
                        'name' => $tpl['name'],
                        'image' => $tpl['image'],
                        'star' => $tpl['star'],
                        'wuli' => $tpl['wuli'],
                        'zhili' => $tpl['zhili'],
                        'tili' => $tpl['tili'],
                        'skill' => Skill::ROWS[(int)$tpl['skillid']] ?? [],
                        'talent' => Talent::ROWS[(int)$tpl['talentid']] ?? [],
                    ];
                    // 依赖未移植：酒馆获得日志 tavern_model->add_pub_log(generalid, 1, 4)
                    // 发送广播：7 星以上武将小喇叭
                    $star = Chat::starWord((int)$tpl['star']);
                    if ($star !== null) {
                        $sender = Chat::sender($uid);
                        Chat::sendToHorn(sprintf(self::LANG['general_get'], $sender['nickname'] ?? '', $star, $tpl['name']), $sender);
                    }
                }
            } elseif ($getType === 3) {
                // 奖励银币
                Member::addCurrency($uid, silver: $getValue);
                $return['silver'] = $getValue;
                $return['all_silver'] = (int)DB::table('member')->where('uid', $uid)->value('silver');
            } elseif (in_array($getType, [4, 5, 6], true)) {
                // 奖励宝石：4 黄、5 紫、6 蓝
                $jadeType = match ($getType) {
                    4 => 2,
                    5 => 3,
                    default => 1,
                };
                TavernHandler::addJade($uid, $jadeType, $getValue, 'vip', 'get_gift');
                $return['jadetype'] = $jadeType;
                $return['jadenum'] = $getValue;
            } elseif ($getType === 7) {
                // 奖励玉石
                MemberJade::add($uid, $getValue, 'vip', 'get_gift');
                $return['stone'] = $getValue;
            } elseif ($getType === 8) {
                // 奖励将魂
                $soulInfo = OwnSoul::addHeroSoul($uid, $getValue, self::SOUL_LEVEL);
                $return['soul'] = $getValue;
                $return['soul_name'] = $soulInfo['name'] ?? null;
                $return['soul_type'] = $soulInfo['type'] ?? null;
                $return['soul_quality'] = $soulInfo['quality'] ?? null;
            } elseif ($getType === 9) {
                // 突飞卡
                DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->increment('freeleap', $getValue);
                $return['freeleap'] = $getValue;
            } elseif ($getType === 10) {
                // 声望
                Member::addCurrency($uid, reputation: $getValue);
                $return['reputation'] = $getValue;
            }
        }
        Redis::del('player:' . $uid);

        $return['status'] = 1;
        return $return;
    }

    /**
     * 领取首充奖励: c=vip&m=get_vip_reward（t = 1 领取）
     *
     * 旧版方法开头直接返回 status 0（首充奖励改由 c=first_recharge 发放，后面的旧逻辑不再执行），这里同样只返回 0。
     */
    public static function get_vip_reward(Context $ctx): array
    {
        return ['status' => 0];
    }

    /**
     * 某一 VIP 等级的特权说明与礼包内容（get_reward）
     *
     * @param array<string, int> $info vip 规则 + 礼包
     * @param int $opennum 已领礼包的等级
     * @param array<string, int> $lastinfo 上一 VIP 等级的规则
     * @return array<string, mixed>
     */
    private static function rewardInfo(int $uid, array $info, int $opennum, array $lastinfo): array
    {
        $num = 1;
        $message = '';
        $l = self::LANG;
        $last = static fn (string $key): int => (int)($lastinfo[$key] ?? 0);

        if ($info['practicenum'] && $info['practicenum'] > $last('practicenum')) {
            $message .= $num . '.' . $l['vip_practice_site'] . $info['practicenum'] . ':' . $l['vip_practice_buy'] . '<br />';
            $num++;
        }
        if ($info['workshopnum'] && $info['workshopnum'] > $last('workshopnum')) {
            $message .= $num . '.' . $l['vip_workshop_site'] . $info['workshopnum'] . ':' . $l['vip_workshop_buy'] . '<br />';
            $num++;
        }
        if ($info['practicelevel'] && $info['practicelevel'] > $last('practicelevel')) {
            $name = self::name(self::PRACTICE_NAME, $info['practicelevel']);
            $percent = ($info['practicelevel'] + 1) * 50;
            if ($percent === 350) {
                $percent = 400;
            }
            $message .= $num . '.' . $name . $l['vip_practice_site'] . ':' . sprintf($l['vip_practice_effect'], $percent . '%') . '<br />';
            $num++;
        }
        if ($info['workshoplevel'] && $info['workshoplevel'] > $last('workshoplevel')) {
            $name = self::name(self::PRACTICE_NAME, $info['workshoplevel']);
            $percent = ($info['workshoplevel'] + 1) * 50;
            if ($percent === 350) {
                $percent = 400;
            }
            $message .= $num . '.' . $name . $l['vip_workshop_site'] . ':' . sprintf($l['vip_workshop_effect'], $percent . '%') . '<br />';
            $num++;
        }
        if ($info['culturelevel'] && $info['culturelevel'] > $last('culturelevel')) {
            $name = self::name(self::CULTURE_NAME, $info['culturelevel']);
            $message .= $num . '.' . $name . $l['vip_cultivate'] . ':' . sprintf($l['vip_cultivate_open'], $name) . '<br />';
            $num++;
        }
        if ($info['inherit'] && $info['inherit'] > $last('inherit')) {
            $name = self::name(self::CULTURE_NAME, $info['inherit']);
            $message .= $num . '.' . $name . $l['vip_inherit'] . ':' . sprintf($l['vip_inherit_open'], $name) . '<br />';
            $num++;
        }
        foreach (['strengthen' => 'vip_strengthen', 'leap' => 'vip_leap', 'clear_cd' => 'vip_clear_cd'] as $field => $key) {
            if ($info[$field] && $info[$field] > $last($field)) {
                $message .= $num . '.' . $l[$key] . '<br />';
                $num++;
            }
        }
        if ($info['act_limit'] && $info['act_limit'] > $last('act_limit')) {
            $message .= $num . '.' . sprintf($l['vip_act_limit'], $info['act_limit']) . '<br />';
            $num++;
        }
        foreach (['trade' => 'vip_trade', 'insight' => 'vip_insight', 'insightup' => 'vip_insight_up'] as $field => $key) {
            if ($info[$field] && $info[$field] > $last($field)) {
                $message .= $num . '.' . $l[$key] . '<br />';
                $num++;
            }
        }
        $shopName = self::name(self::SHOP_NAME, $info['vip_level']);
        if ($shopName !== '') {
            $message .= $num . '.' . $shopName . '<br />';
            $num++;
        }
        // 各 VIP 等级的专属特权（旧版按等级逐条写死的说明，顺序照旧版）
        $lines = match ($info['vip_level']) {
            2 => [$l['vip_free_ordinary_inherit'], $l['vip_give_first_stone']],
            4 => [$l['vip_free_intensify_inherit']],
            6 => [$l['vip_free_platina_inherit'], $l['vip_give_second_stone']],
            7 => [$l['vip_force_occupy'], $l['vip_inherit_half_price'], $l['vip_matrix_two_defense'], $l['vip_deprecition_seven']],
            8 => [$l['vip_team'], $l['vip_deprecition_eight'], $l['vip_free_inherit']],
            3 => [$l['vip_force']],
            9 => [sprintf($l['vip_herothrone_times'], 1), $l['vip_deprecition_nine']],
            10 => [
                sprintf($l['vip_herothrone_times'], 3), $l['vip_herothrone'], $l['vip_matrix_two_attack'],
                $l['vip_deprecition_ten'], $l['vip_guve_third_stone'], $l['vip_oversea10'],
            ],
            11 => [
                sprintf($l['vip_herothrone_times'], 5), $l['vip_advanced_inherit_fifteen_day'],
                sprintf($l['vip_epic_refresh_times'], 15), sprintf($l['vip_epic_lock_times'], 15),
                $l['vip_sale_item_price'], $l['vip_oversea11'],
            ],
            12 => [
                sprintf($l['vip_herothrone_times'], 10), sprintf($l['vip_epic_refresh_times'], 30),
                sprintf($l['vip_epic_lock_times'], 30), $l['vip_advanced_inherit_seven_day'], $l['vip_smatch_up'],
                $l['vip_oversea12'],
            ],
            13 => [
                $l['vip_matrix_supper'], sprintf($l['vip_epic_refresh_times'], 45), sprintf($l['vip_epic_lock_times'], 45),
                $l['vip_advanced_inherit_no_cd'], $l['vip_smatch_rate_up'], $l['vip_oversea13'],
            ],
            default => [],
        };
        foreach ($lines as $line) {
            $message .= $num . '.' . $line . '<br />';
            $num++;
        }
        if ($message === '') {
            $message .= $num . '.' . $l['vip_not_open'] . '<br />';
        }
        $return = ['left' => $message];

        for ($i = 1; $i <= 4; $i++) {
            $getType = (int)$info['get_type' . $i];
            $getValue = (int)$info['get_value' . $i];
            $item = [];
            if ($getType === 1) {
                // 装备
                $tpl = EquipTemplate::ROWS[$getValue] ?? [];
                $item = [
                    'gettype' => 3,
                    'id' => $tpl['id'] ?? null,
                    'type' => $tpl['type'] ?? null,
                    'name' => $tpl['name'] ?? null,
                    'power' => $tpl['power'] ?? null,
                    'quality' => $tpl['quality'] ?? null,
                    'needlevel' => $tpl['needlevel'] ?? null,
                ];
            } elseif ($getType === 2) {
                // 将领
                $general = GeneralTemplate::ROWS[$getValue] ?? [];
                // 尚未领取该礼包、已拥有该将领且 VIP 10 以下：显示折算的宝石
                if ($info['vip_level'] > $opennum && self::ownsGeneral($uid, $getValue) && $info['vip_level'] < 10) {
                    $price = Pub::ROWS[$getValue] ?? null;
                    if ($price !== null) {
                        $item = [
                            'gettype' => 2,
                            'jadetype' => $price['paytype'],
                            'jadenum' => (int)ceil($price['price'] * self::REBATE),
                            'generalname' => $general['name'] ?? null,
                        ];
                    }
                } else {
                    $talent = Talent::ROWS[(int)($general['talentid'] ?? 0)] ?? [];
                    $skill = Skill::ROWS[(int)($general['skillid'] ?? 0)] ?? [];
                    $item = [
                        'gettype' => 4,
                        'name' => $general['name'] ?? null,
                        'image' => $general['image'] ?? null,
                        'type' => $general['type'] ?? null,
                        'wuli' => $general['wuli'] ?? null,
                        'zhili' => $general['zhili'] ?? null,
                        'tili' => $general['tili'] ?? null,
                        'star' => $general['star'] ?? null,
                        'talent' => ['name' => $talent['name'] ?? null, 'description' => $talent['description'] ?? null],
                        'skill' => ['name' => $skill['name'] ?? null, 'description' => $skill['description'] ?? null],
                    ];
                }
            } elseif ($getType === 3) {
                // 银币
                $item = ['gettype' => 1, 'getvalue' => $getValue];
            } elseif (in_array($getType, [4, 5, 6], true)) {
                // 宝石：4 黄、5 紫、6 蓝
                $item = [
                    'gettype' => 2,
                    'jadetype' => match ($getType) {
                        4 => 2,
                        5 => 3,
                        default => 1,
                    },
                    'jadenum' => $getValue,
                    'generalname' => '',
                ];
            } elseif ($getType === 7) {
                // 玉石
                $item = ['gettype' => 5, 'getvalue' => $getValue];
            } elseif ($getType === 8) {
                // 将魂
                $soul = Soul::ROWS[$getValue] ?? null;
                if ($soul !== null) {
                    // 获取将魂增加属性
                    $rule = OwnSoul::levelupRule(self::SOUL_LEVEL, (int)$soul['type'], (int)$soul['quality']);
                    $item = [
                        'gettype' => 6,
                        'soul_name' => $soul['name'],
                        'soul_type' => $soul['type'],
                        'soul_level' => self::SOUL_LEVEL,
                        'soul_quality' => $soul['quality'],
                        'soul_addition' => $rule['info']['addition'] ?? null,
                    ];
                }
            } elseif ($getType === 9) {
                // 突飞卡
                $item = ['gettype' => 7, 'getvalue' => $getValue];
            } elseif ($getType === 10) {
                // 声望
                $item = ['gettype' => 8, 'getvalue' => $getValue];
            }

            if ($item !== []) {
                $return['right'][] = $item;
            }
        }

        return $return;
    }

    /**
     * 名称表取名（_get_practice_name / _get_culture_name / _get_shop_name：取不到为空串）
     *
     * @param array<int, string> $names 等级 => 语言键
     */
    private static function name(array $names, int $level): string
    {
        return isset($names[$level]) ? self::LANG[$names[$level]] : '';
    }

    /**
     * 是否已拥有该模板的武将（general_model->get_own_by_id($generalid, 0, 1)）
     */
    private static function ownsGeneral(int $uid, int $generalid): bool
    {
        return DB::table('general')->where('uid', $uid)->where('generalid', $generalid)->whereNull('deleted_at')->value('id') !== null;
    }
}
