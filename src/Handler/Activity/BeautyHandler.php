<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\Pvp\ArenaHandler;
use Hanjiang\Handler\Pvp\CountryHandler;
use Hanjiang\MasterData\ActivityTime;
use Hanjiang\MasterData\Equip;
use Hanjiang\Model\Beauty;
use Hanjiang\Model\Chat;
use Hanjiang\Model\Equipment;
use Hanjiang\Model\Member;
use Hanjiang\Model\MemberIntegral;
use Hanjiang\Model\MemberJade;
use Hanjiang\Model\MemberStone;
use Hanjiang\Model\Message;
use Hanjiang\Model\OwnGem;
use Hanjiang\Model\OwnSoul;
use Hanjiang\Model\PassGeneral;
use Hanjiang\Model\TraderLog;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 铜雀台（移植旧版 controllers/beauty.php + models/beauty_model.php，路径相对旧版 s1/application/）
 *
 * 主公 60 级开放；三位美人可同时激活（60 / 90 / 130 级）。美人靠集齐 6 个碎片升级（一小时后完成）、每 20 级进阶，
 * 激活中的美人送花后 24 小时内技能生效：战斗属性加成（Beauty::addition，计入 TeamBuilder / BattleEngine / 将领界面）
 * 与资源产出（特殊技能，action_special_reward 领取）。碎片靠互动 / 转化获得，或在掠夺中从其他玩家处抢（battle_pk）。
 * 存储与配置见 Model\Beauty；玫瑰花 / 钻石为 member_integral.beauty_rose / beauty_diamond（Model\MemberIntegral）。
 *
 * 客户端调用（beautypop.js / miscpop.js / chat 战报）：index、get_beauty_info、get_say_info、wakeup_beauty、rest_beauty、peace、
 * loot_index、loot_reflush、loot_action、buy_loot_times、send_to_chat、report_detail、advance_index、advance_action、
 * level_up、level_up_status、cancel_levelup、active_index、active_action、gift、give_flower、buy_rose、
 * action_special_reward、conversion_chip。旧版其余 public 方法是内部函数，不对外。
 *
 * 防并发同旧版：各接口的防刷锁 {锁名}{uid}（30 秒，Beauty::lock，SET NX），掠夺双方的战斗锁 _lott_key{uid}（500 秒）。
 * 旧版 level_up 碎片不足、loot_reflush 取不到对手时不解锁（等 30 秒过期），照旧。
 * 跨请求的状态迁移（升级完成、技能到期自动领取）用带旧值条件的 UPDATE，只有抢到的请求发放 / 扣除。
 *
 * 掠夺信件与信件战报（pm / pm_report，report_detail 回放）见 Model\Message；元宝 / 银币 / 声望流水由 Member 的增减记。
 *
 * 依赖未移植：首次打开操作记录 edifice_model->add_edifice_log(11)；春节送福活动 actnewyear_model->add_task_num（掠夺 16、亲近 17）。
 */
final class BeautyHandler
{
    /** 系统开启等级 */
    private const int OPEN_LEVEL = 60;
    /** 第一 / 二 / 三个美人激活需要等级 */
    private const int FIRST_LEV = 60;
    private const int SECOND_LEV = 90;
    private const int THREE_LEV = 130;
    /** 免战的花费 */
    private const int BATTLE_GOLD = 50;
    /** 技能生效时间 */
    private const int EFFECT_TIME = 86400;
    /** 技能最高等级 */
    private const int SKILL_MAXLEV = 80;
    /** 美人技能生效消耗玫瑰花数量 */
    private const int ROSE_NUM = 5;
    /** 宠幸 vip 限制 */
    private const int LOVE_VIP = 5;
    /** 互动消费：问候银币 / 亲近元宝 / 宠幸元宝 */
    private const array SPEND = ['greet' => 100000, 'close' => 100, 'love' => 500];
    /** 互动增加的好感度 */
    private const array HAPPY = [1 => 8, 2 => 8, 3 => 8];
    /** 赠礼类型和对应消耗及提升的好感度 */
    private const array GIFT_ARR = [
        0 => ['type' => 1, 'gold' => 500, 'ratio' => 50],
        1 => ['type' => 2, 'diamond' => 10, 'ratio' => 50],
        2 => ['type' => 3, 'diamond' => 18, 'ratio' => 100],
    ];
    /** 购买玫瑰花消耗元宝 */
    private const int BUY_ROSE = 20;
    /** 购买抢夺次数花费 */
    private const int VIP_PRICE = 50;
    /** 刷新对手冷却时间 */
    private const int FLUSH_TIME = 1;
    /** 全服分享战报的元宝 */
    private const int SHARE_PRICE = 2;

    /** 防刷锁（旧版 _xxx_lock，键为 锁名 + uid） */
    private const string REFLUSH_LOCK = '_reflush_lock';
    private const string BUY_LOOT_TIMES_LOCK = '_buy_loot_times_lock';
    private const string ADVANCE_ACTION_LOCK = '_advance_action_lock';
    private const string LEVEL_UP_LOCK = '_level_up_lock';
    private const string ACTIVE_ACTION_LOCK = '_active_action_lock';
    private const string GIFT_LOCK = '_gift_lock';
    private const string GIVE_LOCK = '_give_lock';
    private const string BUY_ROSE_LOCK = '_buy_rose_lock';
    private const string SPECIAL_REWARD_LOCK = '_action_special_reward_lock';
    private const string WAKEUP_LOCK = '_wakeup_beauty_lock';
    private const string REST_LOCK = '_rest_beauty_lock';
    private const string PEACE_LOCK = '_peace_lock';
    private const string CONVERSION_LOCK = '_action_conversion_chip_lock';

    /** 信件类型：1 系统通知、13 铜雀台掠夺（发件人为抢夺者） */
    private const int MESSAGE_TYPE_SYSTEM = 1;
    private const int MESSAGE_TYPE_LOOT = 13;
    private const string LANG_MESSAGE_SYSTEM_TITLE = '系统通知';

    /** 流水的执行类 */
    private const string LOG_CLASS = 'beauty';

    /** 旧版语言包 beauty_lang.php（chinese_s） */
    private const array LANG = [
        'beauty_error1' => '等级不足',
        'beauty_error2' => '获取美人模板表失败',
        'beauty_error3' => '获取美人技能失败',
        'beauty_error5' => '参数错误',
        'beauty_error6' => '激活美人已达上限',
        'beauty_error7' => '元宝不足',
        'beauty_error9' => '休息失败',
        'beauty_error11' => '获取美人碎片失败',
        'beauty_error12' => '获取美人失败',
        'beauty_error13' => '获取美人进阶表失败',
        'beauty_error14' => '获取互动信息失败',
        'beauty_error15' => 'VIP等级不足',
        'beauty_error16' => '获取互动奖励信息失败',
        'beauty_error17' => '银币不足',
        'beauty_error18' => '获取抢夺列表失败',
        'beauty_error19' => '美人id参数失败',
        'beauty_error20' => '碎片id参数失败',
        'beauty_error21' => '用户id参数失败',
        'beauty_error22' => '获取美人信息失败',
        'beauty_error24' => '已经拥有该碎片',
        'beauty_error25' => '正在战斗中',
        'beauty_error26' => '对方等级不足！',
        'beauty_error28' => '对方碎片不足',
        'beauty_error29' => '抢夺宝箱数据表失败',
        'beauty_error30' => '获取用户抢夺信息失败',
        'beauty_error31' => '免费抢夺次数不足',
        'beauty_error32' => '剩余购买抢夺次数不足',
        'beauty_error33' => '购买免战次数不足',
        'beauty_error34' => '玫瑰花不足',
        'beauty_error35' => '对方正在免战中',
        'beauty_loot_is_error' => '您的【%s】已经被【%s】抢夺,升级失败。',
        'bauty_loot_level_up_info' => '您的',
        'beauty_loot_level_up_is_error' => '【%s】被【%s】抢夺，',
        'beauty_level_up_error' => '升级失败。',
        'beauty_chip_isnot' => '集齐六种碎片方可开始升级',
        'beauty_flush_time_isnot' => '刷新对手冷却中',
        'beauty_yellow_error' => '黄宝石不足',
        'beauty_purple_error' => '紫宝石不足',
        'beauty_soul_error' => '原魂不足',
        'beauty_reputation_error' => '声望不足',
        'beauty_rose_error' => '玫瑰花不足',
        'beauty_diamond_error' => '钻石不足',
        'beauty_gold_error' => '金币不足',
        'share_info_error' => '获取用户信息失败',
        'beauty_chip_loot' => '【%s】碎片被抢，升级失败！',
        'beauty_love_is_enough' => '美人好感度已满',
        'diamond_error' => '钻石不足',
        'fast_error' => '操作太快了！',
        'beauty_say_error' => '美人私语信息失败',
        'beauty_not_leveup_error' => '美人不在升级状态',
        'beauty_greetnum_error' => '问候次数不足',
        'beauty_greettime_error' => '问候时间冷却中',
        'beauty_active_error' => '美人未激活',
        'beauty_skill_error' => '获取美人技能信息失败',
        'beauty_is_open' => '美人已经激活',
        'beauty_isnot_open' => '美人正在休息中',
        'beauty_skill_isnot' => '技能未生效',
        'beauty_skill_reward_isnot' => '当前没有可领取的奖励',
        'beauty_add_skill' => '解锁技能：',
        'skill_end_time_finish' => '美人%s的的技能已经失效，',
        'skill_end_time_reward' => '期间您一共获得',
        'skill_end_type13' => '银币',
        'skill_end_type14' => '蓝宝石',
        'skill_end_type15' => '玉石',
        'skill_end_type16' => '原魂',
        'skill_end_type17' => '宝石碎片',
        'skill_end_type18' => '声望',
        'skill_end_type19' => '精炼石',
        'skill_check' => '请您笑纳',
        'beauty_isup' => '升级过程中不能进行操作',
        'beauty_not_advance' => '美人未在进阶中',
        'beauty_chip_error' => '美人碎片不足',
        'beauty_deuce' => '打成平局',
        'beauty_level_is_not_up' => '此美人不可升级',
        'beauty_level_is_not_advance' => '此美人不可进阶',
        'beauty_is_level_up' => '此美人正在升级中',
        // chat_lang.php
        'chat_beauty_get_quality' => '恭喜【%s】在铜雀台系统中获得装备-%s',
    ];

    /** 掠夺宝箱奖励名称（beauty_loot_{type}） */
    private const array LOOT_NAME = [
        1 => '元宝', 2 => '银币 ', 3 => '黄宝石', 4 => '紫宝石', 5 => '原魂', 6 => '玉石', 7 => '声望', 8 => '装备', 9 => '宝石碎片',
        10 => '突飞卡', 11 => '坐骑', 12 => '蓝宝石', 13 => '武将', 14 => '军令', 15 => '精炼石', 16 => '五彩石', 17 => '上古血玉',
        21 => '武器精华（白）', 22 => '防具精华（白）', 23 => '饰品精华（白）', 24 => '武器精华（绿）', 25 => '防具精华（绿）',
        26 => '饰品精华（绿）', 27 => '武器精华（蓝）', 28 => '防具精华（蓝）', 29 => '饰品精华（蓝）', 30 => '美人碎片', 31 => '玫瑰花 ', 32 => '钻石',
    ];

    // ---------------------------------------------------------------------------------------------
    // 主页 / 美人信息
    // ---------------------------------------------------------------------------------------------

    /**
     * 铜雀台主页面: c=beauty&m=index
     *
     * status：-1 等级不足；-2 获取美人模板表失败；-3 获取美人技能失败；1 成功
     */
    public static function index(Context $ctx): array
    {
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return ['status' => 0];
        }
        // 依赖未移植：首次打开操作记录 edifice_model->add_edifice_log(11)
        return self::indexInfo($member, 0, time());
    }

    /**
     * 铜雀台美人详情: c=beauty&m=get_beauty_info&beauty_id=
     *
     * status：-1 等级不足；-2 参数错误；其余同 index
     */
    public static function get_beauty_info(Context $ctx): array
    {
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null || !self::isOpen($member)) {
            // 等级不足
            return self::fail($return, -1, 'beauty_error1');
        }
        $beautyId = (int)$ctx->input('beauty_id', 0);
        if ($beautyId <= 0) {
            // 参数错误
            return self::fail($return, -2, 'beauty_error5');
        }
        return self::indexInfo($member, $beautyId, time());
    }

    /**
     * 美人私语: c=beauty&m=get_say_info&beauty_id=（随机取一句类型 1 的对话）
     *
     * status：-1 参数错误；-2 美人私语信息错误；1 成功
     */
    public static function get_say_info(Context $ctx): array
    {
        $beautyId = (int)$ctx->input('beauty_id', 0);
        return self::sayInfo($beautyId, 1);
    }

    // ---------------------------------------------------------------------------------------------
    // 激活 / 休息 / 免战
    // ---------------------------------------------------------------------------------------------

    /**
     * 美人激活: c=beauty&m=wakeup_beauty&beauty_id=
     *
     * status：-9 操作太快了；-8 当前美人已经激活；-7 获取美人技能信息失败；-4 激活美人已达上限 / 升级中；
     * -3 获取美人信息失败；-2 美人id错误；-1 等级不足；1 成功（say、all_gold、wakeup_num）
     */
    public static function wakeup_beauty(Context $ctx): array
    {
        $return = [];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null || !self::isOpen($member)) {
            // 等级不足
            return self::fail($return, -1, 'beauty_error1');
        }
        $uid = (int)$member['uid'];
        $now = time();
        // 防止刷接口
        if (!Beauty::lock(self::WAKEUP_LOCK, $uid)) {
            return self::fail($return, -9, 'fast_error');
        }
        $beautyId = (int)$ctx->input('beauty_id', 0);
        if ($beautyId <= 0) {
            Beauty::unlock(self::WAKEUP_LOCK, $uid);
            return self::fail($return, -2, 'beauty_error5');
        }
        // 查看美人信息是否存在
        $info = Beauty::template($beautyId);
        if ($info === []) {
            Beauty::unlock(self::WAKEUP_LOCK, $uid);
            return self::fail($return, -3, 'beauty_error2');
        }
        // 查看当前美人状态
        $beautyInfo = Beauty::own($uid, $beautyId);
        if ($beautyInfo !== [] && $beautyInfo['open_status'] === 1) {
            Beauty::unlock(self::WAKEUP_LOCK, $uid);
            // 当前美人已经激活
            return self::fail($return, -8, 'beauty_is_open');
        }
        // 查看美人是否是在升级中
        if ($beautyInfo !== [] && ($beautyInfo['up_status'] === 1 || $beautyInfo['up_status'] === 2)) {
            Beauty::unlock(self::WAKEUP_LOCK, $uid);
            return self::fail($return, -4, 'beauty_isup');
        }
        // 查看玩家现在激活的美人数量
        $num = Beauty::onlineCount($uid);
        if ($num >= self::maxNum($member)) {
            Beauty::unlock(self::WAKEUP_LOCK, $uid);
            // 不能再激活美人
            return self::fail($return, -4, 'beauty_error6');
        }
        // 根据已激活美人数量进行等级判断（第一个 60 级、第二个 90 级、第三个 130 级）
        $needLevel = match ($num) {
            2 => self::THREE_LEV,
            1 => self::SECOND_LEV,
            default => self::FIRST_LEV,
        };
        if ((int)$member['level'] < $needLevel) {
            Beauty::unlock(self::WAKEUP_LOCK, $uid);
            return self::fail($return, -1, 'beauty_error1');
        }
        if ($beautyInfo !== []) {
            // 更新成激活状态
            Beauty::updateActive($uid, $beautyId, 1);
        } else {
            // 添加美人并建碎片
            Beauty::addOwn($uid, $beautyId, $info, $now);
            Beauty::addChipInfo($uid, $beautyId, $now);
        }
        Beauty::unlock(self::WAKEUP_LOCK, $uid);
        // 美人私语
        $return['say'] = self::sayInfo($beautyId, 2)['say'] ?? null;
        $return['status'] = 1;
        $return['all_gold'] = (int)$member['gold'];
        $return['wakeup_num'] = $num + 1;
        return $return;
    }

    /**
     * 美人休息: c=beauty&m=rest_beauty&beauty_id=（先自动领取未领取的特殊技能产出）
     *
     * status：-6 操作太快了；-5 修改美人状态失败；-4 美人正在休息中 / 升级中；-3 没有此美人；-2 美人id错误；-1 等级不足；1 成功
     */
    public static function rest_beauty(Context $ctx): array
    {
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null || !self::isOpen($member)) {
            return self::fail($return, -1, 'beauty_error1');
        }
        $uid = (int)$member['uid'];
        $now = time();
        if (!Beauty::lock(self::REST_LOCK, $uid)) {
            return self::fail($return, -6, 'fast_error');
        }
        $beautyId = (int)$ctx->input('beauty_id', 0);
        if ($beautyId <= 0) {
            Beauty::unlock(self::REST_LOCK, $uid);
            return self::fail($return, -2, 'beauty_error5');
        }
        if (Beauty::template($beautyId) === []) {
            Beauty::unlock(self::REST_LOCK, $uid);
            return self::fail($return, -2, 'beauty_error2');
        }
        // 玩家是否拥有此美人
        $result = Beauty::own($uid, $beautyId);
        if ($result === []) {
            Beauty::unlock(self::REST_LOCK, $uid);
            return self::fail($return, -3, 'beauty_error21');
        }
        if ($result['up_status'] === 1 || $result['up_status'] === 2) {
            Beauty::unlock(self::REST_LOCK, $uid);
            return self::fail($return, -4, 'beauty_isup');
        }
        if ($result['open_status'] === 0) {
            Beauty::unlock(self::REST_LOCK, $uid);
            // 美人正在休息中
            return self::fail($return, -4, 'beauty_isnot_open');
        }
        // 自动领取未领取的特殊技能数量
        self::actionSpecialReward($member, $beautyId, $now);
        // 更新成休息状态
        if (Beauty::updateActive($uid, $beautyId, 0)['status'] === 0) {
            Beauty::unlock(self::REST_LOCK, $uid);
            return self::fail($return, -5, 'beauty_error9');
        }
        Beauty::unlock(self::REST_LOCK, $uid);
        $after = self::member($uid) ?? $member;
        $return['wakeup_num'] = Beauty::onlineCount($uid);
        $return['say'] = self::sayInfo($beautyId, 3)['say'] ?? null;
        $return['special_skill'] = self::specialSkillById($uid, $beautyId, $now);
        $return['all_gold'] = (int)$after['gold'];
        $return['all_silver'] = (int)$after['silver'];
        $return['status'] = 1;
        return $return;
    }

    /**
     * 免战: c=beauty&m=peace（花 50 元宝免战一小时，每天 5 次；未过期时累加）
     *
     * status：-5 操作太快了；-3 购买免战次数不足；-2 元宝不足；-1 等级不足；1 成功（cd_time、times、all_gold）
     */
    public static function peace(Context $ctx): array
    {
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        $uid = (int)$member['uid'];
        $now = time();
        if (!Beauty::lock(self::PEACE_LOCK, $uid)) {
            return self::fail($return, -5, 'fast_error');
        }
        if (!self::isOpen($member)) {
            Beauty::unlock(self::PEACE_LOCK, $uid);
            return self::fail($return, -1, 'beauty_error1');
        }
        // 免战信息（跨天时库里重置为 5 次，本次仍按读到的旧值判断，旧版如此）
        $info = Beauty::userInfo($uid, $now);
        if ($info !== [] && $info['times'] <= 0) {
            Beauty::unlock(self::PEACE_LOCK, $uid);
            return self::fail($return, -3, 'beauty_error33');
        }
        // 判断元宝是否足够并扣除
        if ((int)$member['gold'] < self::BATTLE_GOLD || !Member::deductCurrency($uid, gold: self::BATTLE_GOLD)) {
            Beauty::unlock(self::PEACE_LOCK, $uid);
            return self::fail($return, -2, 'beauty_error7');
        }
        // 更新免战信息
        $result = Beauty::peace($uid, (int)$member['vip'], $now);
        $times = $result !== [] ? $result['times'] : Beauty::BATTLE_NUM - 1;
        // 免战剩余时间（秒）
        $cdTime = ($result['cd_time'] ?? 0) - $now;
        Beauty::unlock(self::PEACE_LOCK, $uid);
        $return['status'] = 1;
        $return['cd_time'] = $cdTime > 0 ? $cdTime : 0;
        $return['times'] = $times;
        $return['all_gold'] = (int)(self::member($uid)['gold'] ?? 0);
        return $return;
    }

    // ---------------------------------------------------------------------------------------------
    // 掠夺
    // ---------------------------------------------------------------------------------------------

    /**
     * 抢夺主页面: c=beauty&m=loot_index&beauty_id=&chip_id=
     *
     * 旧版的开放判断写成 _open == 2，永远不成立，这里同样不判断等级。
     * status：-3 获取抢夺列表失败；-4 美人id参数错误；-5 碎片id参数错误；-7 获取美人模板失败；-8 已经拥有该碎片；1 成功
     */
    public static function loot_index(Context $ctx): array
    {
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        $uid = (int)$member['uid'];
        $now = time();
        $beautyId = (int)$ctx->input('beauty_id', 0);
        if ($beautyId <= 0) {
            return self::fail($return, -4, 'beauty_error5');
        }
        if (Beauty::template($beautyId) === []) {
            return self::fail($return, -7, 'beauty_error2');
        }
        $chipId = (int)$ctx->input('chip_id', 0);
        if ($chipId <= 0) {
            return self::fail($return, -5, 'beauty_error5');
        }
        // 当前碎片不用抢夺
        $userChip = Beauty::chip($uid, $beautyId, $chipId);
        if ($userChip !== [] && $userChip['chip'] > 0) {
            return self::fail($return, -8, 'beauty_error24');
        }

        $saved = Beauty::lootList($uid, $beautyId, $chipId);
        if ($saved !== []) {
            $newArray = json_decode((string)$saved['list'], true);
            $newArray = is_array($newArray) ? $newArray : [];
            foreach ($newArray as &$nv) {
                if (empty($nv['country'])) {
                    $nv['country'] = '';
                }
            }
            unset($nv);
        } else {
            // 获取抢夺列表
            $newArray = self::getLootList($member, $beautyId, $chipId, $now);
            if ($newArray === []) {
                return self::fail($return, -3, 'beauty_error18');
            }
            Beauty::addLootList($uid, $beautyId, $chipId, $newArray, $now);
        }

        // 抢夺记录
        $lootArray = self::lootLogArray($uid);

        // 零点初始化免费次数（双倍奖励活动期间免费抢夺次数翻倍）
        Beauty::updateLootTimes($uid, self::isDouble($now), (int)$member['vip'], $now);
        $userInfo = Beauty::userInfo($uid, $now);
        if ($userInfo === []) {
            Beauty::initUserInfo($uid, (int)$member['vip'], $now);
            $userInfo = Beauty::userInfo($uid, $now);
        }

        $return['status'] = 1;
        $return['vip_times'] = $userInfo['vip_times'];
        $return['free_times'] = $userInfo['free_times'];
        $return['loot_list'] = $newArray;
        $return['loot_log'] = $lootArray;
        // 旧版直接相减，冷却已过时为负数
        $return['cd'] = $userInfo['reflush_dateline'] - $now;
        $return['price'] = self::VIP_PRICE;
        return $return;
    }

    /**
     * 刷新对手: c=beauty&m=loot_reflush&beauty_id=&chip_id=（冷却 1 秒）
     *
     * status：-7 操作太快了；-4 刷新对手冷却中；-3 获取美人模板失败 / 获取抢夺列表失败；-2 参数错误；1 成功（loot_list、cd）
     */
    public static function loot_reflush(Context $ctx): array
    {
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return ['status' => 0];
        }
        return self::reflush($member, (int)$ctx->input('beauty_id', 0), (int)$ctx->input('chip_id', 0), time());
    }

    /**
     * 抢夺: c=beauty&m=loot_action&beauty_id=&chip_id=&t=被挑战uid
     *
     * 与对方 battle_pk；胜利后按双方等级差的概率抢到碎片（对方碎片 - 1，对方正在升级且该碎片抢光时升级失败并发信），
     * 不论胜负都按宝箱权重得一份奖励、消耗一次免费次数、取消自己的免战，并给对方发一封掠夺信件（类型 13）。
     *
     * status：-14 抢夺宝箱奖励错误；-13 对方碎片不足；-11 对方等级不足；-10 免费抢夺次数不足；-9 正在战斗中 / 获取用户抢夺信息失败；
     * -8 已经拥有该碎片；-7 对方正在免战中；-6 此美人正在升级中；-5 用户id参数错误；-4 碎片id参数错误；-3 美人id参数错误；
     * -2 美人模板为空；-1 等级不足；1 成功
     */
    public static function loot_action(Context $ctx): array
    {
        $return = [];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null || !self::isOpen($member)) {
            return self::fail($return, -1, 'beauty_error1');
        }
        $uid = (int)$member['uid'];
        $now = time();

        $beautyId = (int)$ctx->input('beauty_id', 0);
        $beautyList = Beauty::templates();
        if ($beautyList === []) {
            return self::fail($return, -2, 'beauty_error2');
        }
        if (!in_array($beautyId, array_column($beautyList, 'id'), true)) {
            return self::fail($return, -3, 'beauty_error19');
        }
        $chipId = (int)$ctx->input('chip_id', 0);
        if ($chipId <= 0) {
            return self::fail($return, -4, 'beauty_error20');
        }
        $touid = (int)$ctx->input('t', 0);
        if ($touid <= 0) {
            return self::fail($return, -5, 'beauty_error21');
        }

        // 判断双方是否正在战斗中，并加战斗锁
        if (!Beauty::lottLock($uid, $touid)) {
            return self::fail($return, -9, 'beauty_error25');
        }

        // 对方等级判断
        $target = self::member($touid);
        if ($target === null || (int)$target['level'] < self::OPEN_LEVEL) {
            Beauty::unLottLock($uid, $touid);
            return self::fail($return, -11, 'beauty_error26');
        }
        $info2 = Beauty::own($touid, $beautyId);
        // 对方正在免战中
        $toInfo = Beauty::userInfo($touid, $now);
        if ($toInfo !== [] && $toInfo['cd_time'] >= $now) {
            Beauty::unLottLock($uid, $touid);
            return self::fail($return, -7, 'beauty_error35');
        }
        // 对方升级到时间的，先完成升级
        if ($info2 !== [] && $info2['up_dateline'] > 0 && $info2['up_dateline'] - $now <= 0) {
            self::finishLevelUp($touid, $beautyId, Beauty::chips($touid, $beautyId), 2, $info2['up_dateline'], $now);
        }
        // 对方碎片数量判断
        $chipInfo2 = Beauty::chip($touid, $beautyId, $chipId);
        if ($chipInfo2 === [] || $chipInfo2['chip'] <= 0) {
            Beauty::unLottLock($uid, $touid);
            return self::fail($return, -13, 'beauty_error28');
        }
        // 自己的美人正在升级中
        $info1 = Beauty::own($uid, $beautyId);
        if ($info1 !== [] && $info1['up_status'] > 0) {
            Beauty::unLottLock($uid, $touid);
            return self::fail($return, -6, 'beauty_is_level_up');
        }
        // 已经拥有该碎片
        $myChip = Beauty::chip($uid, $beautyId, $chipId);
        if ($myChip !== [] && $myChip['chip'] > 0) {
            Beauty::unLottLock($uid, $touid);
            return self::fail($return, -8, 'beauty_error24');
        }
        // 抢夺次数判断
        $info = Beauty::userInfo($uid, $now);
        if ($info === []) {
            Beauty::unLottLock($uid, $touid);
            return self::fail($return, -9, 'beauty_error30');
        }
        if ($info['free_times'] <= 0) {
            Beauty::unLottLock($uid, $touid);
            return self::fail($return, -10, 'beauty_error31');
        }
        // 取消免战
        Beauty::updateUserInfo($uid, ['cd_time' => 0]);

        // 宝箱奖励（剔除已拥有的武将奖励）
        $lootReward = Beauty::lootReward();
        if ($lootReward === []) {
            Beauty::unLottLock($uid, $touid);
            return self::fail($return, -14, 'beauty_error29');
        }
        $typeArr = [];
        $rateTotal = 0;
        foreach ($lootReward as $val) {
            if ($val['type'] === 13 && self::ownsGeneral($uid, (int)$val['value'])) {
                continue;
            }
            $typeArr[] = $val;
            $rateTotal += $val['rate'];
        }
        // 随机获取奖励（旧版 rand_arr 有极小概率一个都抽不中，此时没有奖励）
        $lastReward = self::randArr($typeArr, $rateTotal, 1)[0] ?? null;

        // 开始战斗
        $battle = ArenaHandler::battlePk($uid, $touid);
        $assess = $battle['info']['assess'] ?? null;
        $chipStatus = 0;
        $battleStatus = 0;
        if ($assess !== null && $assess >= 1) {
            $battleStatus = 1;
            // 胜利处理 判断读取成功率
            $randNum = mt_rand(0, 100);
            $rateInfo = self::rateItem((int)$member['level'], (int)$target['level']);
            // 被挑战方减碎片（条件 UPDATE：并发下对方的碎片已被别人抢走 / 用掉时这次抢不到，旧版会凭空多出一片）
            if ($randNum <= $rateInfo['rate'] && Beauty::takeChip($touid, $beautyId, $chipId)) {
                // 获得碎片
                $chipStatus = 1;
                // 挑战方加碎片
                if (Beauty::chip($uid, $beautyId, $chipId) === []) {
                    Beauty::addChipInfo($uid, $beautyId, $now);
                }
                Beauty::updateChipNum($uid, $beautyId, $chipId, 1, $now);
                $newNum = $chipInfo2['chip'] - 1;
                if ($newNum <= 0 && $info2 !== [] && ($info2['up_status'] === 1 || $info2['up_status'] === 2)) {
                    // 对方正在升级，碎片抢光：升级失败并发信
                    $message = sprintf(self::LANG['beauty_loot_is_error'], $chipInfo2['chip_name'], $member['nickname']);
                    MessageHandler::sendSystemMessage([$touid], self::MESSAGE_TYPE_SYSTEM, self::LANG_MESSAGE_SYSTEM_TITLE, $message);
                    Beauty::updateLevelStatus($touid, $beautyId, 0, $now);
                }
            }
        }

        // 刷新对手（只取副作用：刷新时间、保存的对手列表；返回值旧版被后面的输出覆盖）
        if ($chipStatus === 1) {
            self::reflush($member, $beautyId, $chipId, $now);
        }

        // 发信件（类型 13，发件人为抢夺者）
        $pmid = MessageHandler::sendSystemMessage([$touid], self::MESSAGE_TYPE_LOOT, (string)$member['nickname'], '', 0, $uid);

        // 发送宝箱奖励
        $rewardAdd = self::addLootReward($member, $lastReward, $now);

        // 添加抢夺log
        Beauty::addLootLog([
            'uid' => $uid,
            'nickname' => (string)$member['nickname'],
            'to_uid' => $touid,
            'to_name' => (string)$target['nickname'],
            'chip_id' => $chipId,
            'beauty_id' => $beautyId,
            'reward_id' => (int)($lastReward['id'] ?? 0),
            'chip_status' => $chipStatus,
            'battle_status' => $battleStatus,
            'report_id' => $pmid,
            'dateline' => $now,
        ]);
        // 消耗一次免费次数
        Beauty::useFreeTimes($uid);
        $info = Beauty::userInfo($uid, $now);
        $newTimes = (int)($info['free_times'] ?? 0);

        $return['info'] = [
            'gender' => (int)$target['gender'],
            'win' => $assess,
            'touid' => $touid,
            'report' => $pmid,
        ];
        $return['battle'] = $battle['info'] ?? [];
        $return['battle']['left_role'] = [
            'name' => (string)$member['nickname'],
            'level' => (int)$member['level'],
            'gender' => (int)$member['gender'],
        ];
        $return['battle']['right_role'] = [
            'name' => (string)$target['nickname'],
            'level' => (int)$target['level'],
            'gender' => (int)$target['gender'],
        ];
        $return['share'] = [
            'id' => $pmid,
            'type' => 'beauty',
        ];
        // 信件战报
        Message::recordPkReport($pmid, [
            'from' => (string)$member['nickname'],
            'fromlevel' => (int)$member['level'],
            'to' => (string)$target['nickname'],
            'tolevel' => (int)$target['level'],
            'report' => $battle['info'] ?? null,
        ]);

        $beautyChip = self::chipList($uid, $beautyId);
        $lootArray = self::lootLogArray($uid);
        $chipName = Beauty::chipInfo($beautyId, $chipId)['chip_name'] ?? null;
        // 依赖未移植：春节送福活动 actnewyear_model->add_task_num(version, 16)

        Beauty::unLottLock($uid, $touid);
        $after = self::member($uid) ?? $member;
        $return['status'] = 1;
        $return['b_info'] = [
            'chip_status' => $chipStatus,
            'chip_id' => $chipId,
            'beauty_id' => $beautyId,
            'chip_name' => $chipName,
        ];
        $return['free_times'] = $newTimes;
        $return['vip_times'] = $info['vip_times'] ?? null;
        $return['reward_info'] = $rewardAdd['info'];
        $return['all_gold'] = (int)$after['gold'];
        $return['all_silver'] = (int)$after['silver'];
        $return['chip'] = $beautyChip;
        $return['loot_log'] = $lootArray;
        return $return;
    }

    /**
     * 购买抢夺次数: c=beauty&m=buy_loot_times（50 元宝一次，每天 10 次）
     *
     * status：-3 操作太快了；-2 剩余购买次数不足；-1 元宝不足；1 成功（gold、free_times、vip_times）
     */
    public static function buy_loot_times(Context $ctx): array
    {
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        $uid = (int)$member['uid'];
        $now = time();
        if (!Beauty::lock(self::BUY_LOOT_TIMES_LOCK, $uid)) {
            return self::fail($return, -3, 'fast_error');
        }
        if ((int)$member['gold'] < self::VIP_PRICE) {
            Beauty::unlock(self::BUY_LOOT_TIMES_LOCK, $uid);
            return self::fail($return, -1, 'beauty_error7');
        }
        $info = Beauty::userInfo($uid, $now);
        if (($info['vip_times'] ?? 0) <= 0) {
            Beauty::unlock(self::BUY_LOOT_TIMES_LOCK, $uid);
            return self::fail($return, -2, 'beauty_error32');
        }
        $status = Member::locked($uid, static function () use ($uid): int {
            // 扣除元宝
            if (!Member::deductCurrency($uid, gold: self::VIP_PRICE)) {
                return -1;
            }
            // 增加免费次数
            if (!Beauty::buyLootTimes($uid)) {
                throw new \RuntimeException('beauty buy_loot_times: vip_times changed');
            }
            return 1;
        });
        if ($status === -1) {
            Beauty::unlock(self::BUY_LOOT_TIMES_LOCK, $uid);
            return self::fail($return, -1, 'beauty_error7');
        }
        // 购买挑战次数log
        Beauty::addBuyLootLog([
            'uid' => $uid,
            'vip' => (int)$member['vip'],
            'free_times' => $info['free_times'] + 1,
            'price' => self::VIP_PRICE,
            'dateline' => $now,
        ]);
        Beauty::unlock(self::BUY_LOOT_TIMES_LOCK, $uid);
        $return['status'] = 1;
        $return['gold'] = (int)(self::member($uid)['gold'] ?? 0);
        $return['free_times'] = $info['free_times'] + 1;
        $return['vip_times'] = $info['vip_times'] - 1;
        return $return;
    }

    /**
     * 分享铜雀台战报: c=beauty&m=send_to_chat（touid 对方 uid，report 战报 id，win 战斗评级；扣 2 元宝）
     *
     * status：-1 参数错误；-2 金币不足；-3 获取用户信息失败；1 成功（all_gold）
     */
    public static function send_to_chat(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $touid = (int)$ctx->input('touid', 0);
        $report = (int)$ctx->input('report', 0);
        $win = (int)$ctx->input('win', 0);
        $member = self::member($uid);
        // 判断参数是否完整
        if ($touid === 0 || $report === 0 || $member === null) {
            return ['status' => -1, 'msg' => self::LANG['beauty_error5']];
        }
        // 判断金币是否足够
        if ((int)$member['gold'] < self::SHARE_PRICE) {
            return ['status' => -2, 'msg' => self::LANG['beauty_gold_error']];
        }
        // 取用户信息
        $toUser = self::member($touid);
        if ($toUser === null) {
            return ['status' => -3, 'msg' => self::LANG['share_info_error']];
        }
        // 扣除金币
        $allGold = Chat::chargeShare($uid, self::SHARE_PRICE);
        if ($allGold === null) {
            return ['status' => -2, 'msg' => self::LANG['beauty_gold_error']];
        }
        $sender = Chat::sender($uid);
        $info = [
            'to' => ['uid' => $touid, 'nickname' => $toUser['nickname']],
            'id' => $report,
        ];
        if ($win >= 1) {
            $info['win'] = 1;
            $message = Chat::getMessageStr(49, $info, $sender);
        } elseif ($win === 0) {
            $info['win'] = self::LANG['beauty_deuce'];
            $message = Chat::getMessageStr(51, $info, $sender);
        } else {
            $info['win'] = 2;
            $message = Chat::getMessageStr(50, $info, $sender);
        }
        // 发送战报
        Chat::sendToAll($message, 49, $report, 0, $sender);
        return ['status' => 1, 'all_gold' => $allGold];
    }

    /**
     * 查看掠夺战报: c=beauty&m=report_detail&id=（聊天室 / 信件里的铜雀台战报，同 c=message&m=report_detail）
     *
     * status：-1 没有指定的战报；1 成功
     */
    public static function report_detail(Context $ctx): array
    {
        return MessageHandler::reportDetail((int)$ctx->input('id', 0));
    }

    // ---------------------------------------------------------------------------------------------
    // 进阶 / 升级
    // ---------------------------------------------------------------------------------------------

    /**
     * 进阶首页: c=beauty&m=advance_index&beauty_id=（旧版不判断等级）
     *
     * status：-7 美女技能信息错误；-6 / -3 美女进阶信息错误；-4 获取美人信息失败；1 成功（add_info、info 资源、list 消耗）
     */
    public static function advance_index(Context $ctx): array
    {
        $return = [];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return ['status' => 0];
        }
        $uid = (int)$member['uid'];
        $beautyId = (int)$ctx->input('beauty_id', 0);

        $advanceList = Beauty::advancedInfo();
        if ($advanceList === []) {
            return self::fail($return, -3, 'beauty_error13');
        }
        $beautyInfo = Beauty::own($uid, $beautyId);
        if ($beautyInfo === []) {
            return self::fail($return, -4, 'beauty_error12');
        }
        $beautyLevel = $beautyInfo['beauty_level'];
        if (!isset($advanceList[$beautyLevel])) {
            return self::fail($return, -6, 'beauty_error13');
        }
        $skillIds = [$beautyInfo['skill1'], $beautyInfo['skill2'], $beautyInfo['skill3']];
        $levelArray = [];
        if ($beautyLevel >= 0 && $beautyLevel <= 20) {
            $levelArray = [$beautyLevel, 1, 1];
        } elseif ($beautyLevel >= 21 && $beautyLevel <= 40) {
            $levelArray = [$beautyLevel, $beautyLevel - 20, 0];
        } elseif ($beautyLevel >= 41 && $beautyLevel <= 60) {
            $levelArray = [$beautyLevel, $beautyLevel - 20, 1];
        }
        $before = [];
        foreach ($skillIds as $key => $val) {
            if (($levelArray[$key] ?? 0) > 0) {
                $before[] = Beauty::skillInfo($beautyId, $val, $levelArray[$key]);
            }
        }
        if ($before === []) {
            return self::fail($return, -7, 'beauty_error3');
        }
        $after = [];
        foreach ($skillIds as $key => $val) {
            $after[] = Beauty::skillInfo($beautyId, $val, ($levelArray[$key] ?? 0) + 1);
        }

        $return['add_info'] = [];
        foreach ($before as $key => $val) {
            $tmp = [
                'name' => $val['skill_content'] ?? null,
                // 带「%」的属性按数字部分相减
                'value' => (int)($after[$key]['skill_value'] ?? 0) - (int)($val['skill_value'] ?? 0),
            ];
            if (($val['skill_level'] ?? 0) === 1) {
                $tmp = ['name' => self::LANG['beauty_add_skill'] . $val['skill_name'], 'value' => 0];
                $return['add_info'][] = $tmp;
                break;
            }
            $return['add_info'][] = $tmp;
        }
        $return['info'] = self::advanceResource($member);
        $return['list'] = $advanceList[$beautyLevel];
        $return['status'] = 1;
        return $return;
    }

    /**
     * 进阶: c=beauty&m=advance_action&beauty_id=（20 / 40 / 60 级，消耗黄 / 紫宝石、原魂、声望、玫瑰花、钻石，等级与品级 + 1，美人转为休息）
     *
     * status：-8 操作太快了；-7 美女技能信息错误；-6 / -3 美女进阶信息错误；-5 此美人不可进阶；-4 获取美人信息失败；-2 参数错误；
     * -1 等级不足；1 成功（同 index 的美人信息，另带 wakeup_num、all_gold、all_silver）；
     * 2 黄宝石不足；3 紫宝石不足；4 原魂不足；5 声望不足；6 玫瑰花不足；7 钻石不足
     */
    public static function advance_action(Context $ctx): array
    {
        $return = [];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return ['status' => 0];
        }
        $uid = (int)$member['uid'];
        $now = time();
        if (!Beauty::lock(self::ADVANCE_ACTION_LOCK, $uid)) {
            return self::fail($return, -8, 'fast_error');
        }
        $beautyId = (int)$ctx->input('beauty_id', 0);
        // 获取当前美人进价或升级状态（没有该美人时旧版按 0 级算，可进阶）
        $result = Beauty::own($uid, $beautyId);
        if (Beauty::upStatus((int)($result['beauty_level'] ?? 0))['up_status'] !== 2) {
            Beauty::unlock(self::ADVANCE_ACTION_LOCK, $uid);
            return self::fail($return, -5, 'beauty_level_is_not_advance');
        }
        // 获取进阶条件判断信息。旧版 advance_return 失败时只输出错误、返回空，进阶照常执行且不扣资源
        // （例如 80 级没有进阶配置会升到 81 级），这里直接返回 advance_return 的失败状态
        $advanceInfo = self::advanceReturn($member, $beautyId);
        if ($advanceInfo['status'] < 0) {
            Beauty::unlock(self::ADVANCE_ACTION_LOCK, $uid);
            return $advanceInfo;
        }
        $baseInfo = $advanceInfo['info'];
        $want = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0];
        for ($i = 1; $i <= 6; $i++) {
            $type = (int)$advanceInfo['list']['type' . $i];
            if (isset($want[$type])) {
                $want[$type] = (int)$advanceInfo['list']['value' . $i];
            }
        }
        // 验证资源是否足够
        $errors = [1 => [2, 'beauty_yellow_error'], 2 => [3, 'beauty_purple_error'], 3 => [4, 'beauty_soul_error'],
            4 => [5, 'beauty_reputation_error'], 5 => [6, 'beauty_rose_error'], 6 => [7, 'beauty_diamond_error']];
        foreach ($errors as $type => [$status, $msg]) {
            if ($want[$type] > $baseInfo['value' . $type]) {
                Beauty::unlock(self::ADVANCE_ACTION_LOCK, $uid);
                return self::fail($return, $status, $msg);
            }
        }

        // 领取特殊技能奖励
        self::actionSpecialReward($member, $beautyId, $now);
        // 更新美人的激活状态为休息状态
        Beauty::updateActive($uid, $beautyId, 0);

        // 扣除资源（玩家行锁内逐项条件扣除；并发下某项不足时停在该项，返回对应的不足状态）
        $failed = Member::locked($uid, static function () use ($uid, $want, $now): int {
            if ($want[1] > 0 && TraderLog::subJade($uid, 2, $want[1], self::LOG_CLASS, 'advance_action', $now)['status'] !== 1) {
                return 1;
            }
            if ($want[2] > 0 && TraderLog::subJade($uid, 3, $want[2], self::LOG_CLASS, 'advance_action', $now)['status'] !== 1) {
                return 2;
            }
            if ($want[3] > 0 && OwnSoul::subSoul($uid, $want[3], self::LOG_CLASS, 'advance_action', $now)['status'] !== 1) {
                return 3;
            }
            if ($want[4] > 0 && Member::subReputation($uid, $want[4]) === null) {
                return 4;
            }
            if ($want[5] > 0 && MemberIntegral::change($uid, $want[5], 'beauty_rose', 2, self::LOG_CLASS, 'advance_action', $now) === []) {
                return 5;
            }
            if ($want[6] > 0 && MemberIntegral::change($uid, $want[6], 'beauty_diamond', 2, self::LOG_CLASS, 'advance_action', $now) === []) {
                return 6;
            }
            return 0;
        });
        if ($failed > 0) {
            Beauty::unlock(self::ADVANCE_ACTION_LOCK, $uid);
            return self::fail($return, $errors[$failed][0], $errors[$failed][1]);
        }

        // 等级 + 1、品级 + 1
        Beauty::updateLevel($uid, $beautyId);
        Beauty::updateQuality($uid, $beautyId);

        // 返回美人数据
        $return = self::indexInfo($member, $beautyId, $now);
        $num = Beauty::onlineCount($uid);
        Beauty::unlock(self::ADVANCE_ACTION_LOCK, $uid);
        $after = self::member($uid) ?? $member;
        $return['status'] = 1;
        $return['wakeup_num'] = $num;
        $return['all_gold'] = (int)$after['gold'];
        $return['all_silver'] = (int)$after['silver'];
        return $return;
    }

    /**
     * 美人升级: c=beauty&m=level_up&beauty_id=&type=（type 1 玩家点击，2 客户端倒计时结束后刷新）
     *
     * 集齐 6 个碎片后开始升级（美人转为休息），一小时后每个碎片消耗 1 个、等级 + 1；期间碎片被抢光则升级失败。
     * status：-8 操作太快了；-5 此美人不可升级；-4 美人碎片不足（chip，碎片被抢时给自己发信）；-3 获取美人碎片失败；
     * -2 参数错误；-1 等级不足；1 成功（cd 倒计时，-1 为已完成并带 index 的美人信息）
     */
    public static function level_up(Context $ctx): array
    {
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        $uid = (int)$member['uid'];
        $now = time();
        if (!Beauty::lock(self::LEVEL_UP_LOCK, $uid)) {
            return self::fail($return, -8, 'fast_error');
        }
        if (!self::isOpen($member)) {
            Beauty::unlock(self::LEVEL_UP_LOCK, $uid);
            return self::fail($return, -1, 'beauty_error1');
        }
        $beautyId = (int)$ctx->input('beauty_id', 0);
        if ($beautyId <= 0) {
            Beauty::unlock(self::LEVEL_UP_LOCK, $uid);
            return self::fail($return, -2, 'beauty_error5');
        }
        $type = (int)$ctx->input('type', 0);
        $result = Beauty::own($uid, $beautyId);
        // 当前美人是否该升级（没有该美人时按 0 级，是进阶不是升级）
        if (Beauty::upStatus((int)($result['beauty_level'] ?? 0))['up_status'] !== 1) {
            Beauty::unlock(self::LEVEL_UP_LOCK, $uid);
            return self::fail($return, -5, 'beauty_level_is_not_up');
        }
        // 判断此玩家是否拥有此美人的所有碎片
        $chipInfo = Beauty::chips($uid, $beautyId);
        if ($chipInfo === []) {
            Beauty::unlock(self::LEVEL_UP_LOCK, $uid);
            return self::fail($return, -3, 'beauty_error11');
        }
        $str = '';
        $chipArr = [];
        foreach ($chipInfo as $val) {
            if ($val['chip'] === 0) {
                $str .= $val['chip_name'] . ' ';
                $chipArr[] = $val['chip_id'];
            }
        }
        if ($chipArr !== []) {
            // 碎片不足（旧版此分支不解锁，防刷锁 30 秒后过期）
            $return['status'] = -4;
            $return['msg'] = $type === 2 ? sprintf(self::LANG['beauty_chip_loot'], $str) : self::LANG['beauty_chip_isnot'];
            // 重置美人升级时间信息
            Beauty::updateLevelStatus($uid, $beautyId, 0, $now);
            $return['chip'] = self::chipList($uid, $beautyId);
            // 获取玩家美人碎片被抢信息，给自己发信
            $lootInfo = Beauty::lootChipInfo($uid, $beautyId, $chipArr);
            if ($lootInfo !== []) {
                $message = self::LANG['bauty_loot_level_up_info'];
                foreach ($lootInfo as $val) {
                    $name = Beauty::chipInfo($val['beauty_id'], $val['chip_id'])['chip_name'] ?? null;
                    $message .= sprintf(self::LANG['beauty_loot_level_up_is_error'], $name, $val['nickname']);
                }
                $message .= self::LANG['beauty_level_up_error'];
                MessageHandler::sendSystemMessage([$uid], self::MESSAGE_TYPE_SYSTEM, self::LANG_MESSAGE_SYSTEM_TITLE, $message);
            }
            return $return;
        }

        if ($result['up_status'] === 0) {
            // 领取特殊奖励
            self::actionSpecialReward($member, $beautyId, $now);
            // 更新为休息状态
            Beauty::updateActive($uid, $beautyId, 0);
            // 开始升级
            $status = Beauty::updateLevelStatus($uid, $beautyId, 1, $now);
            $cd = ($status['up_dateline'] ?? 0) - $now;
            $return['cd'] = $cd > 0 ? $cd : 0;
            $return['wakeup_num'] = Beauty::onlineCount($uid);
        } else {
            $cd = $result['up_dateline'] - $now;
            $return['cd'] = $cd > 0 ? $cd : 0;
            if ($return['cd'] <= 0) {
                // 倒计时完后每个碎片消耗一个并升级
                self::finishLevelUp($uid, $beautyId, $chipInfo, 0, $result['up_dateline'], $now);
                $return = self::indexInfo($member, $beautyId, $now);
                // -1 不显示倒计时
                $return['cd'] = -1;
            }
        }
        Beauty::unlock(self::LEVEL_UP_LOCK, $uid);
        $after = self::member($uid) ?? $member;
        $return['status'] = 1;
        if ($type !== 2) {
            $return['special_skill'] = self::specialSkillById($uid, $beautyId, $now);
        }
        $return['all_gold'] = (int)$after['gold'];
        $return['all_silver'] = (int)$after['silver'];
        return $return;
    }

    /**
     * 升级动画已播放: c=beauty&m=level_up_status&beauty_id=（is_play 记 1，返回 index 的美人信息；旧版不判断等级，status 总是 1）
     *
     * status：-2 获取美人信息失败；-1 参数错误；1 成功
     */
    public static function level_up_status(Context $ctx): array
    {
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        $uid = (int)$member['uid'];
        $beautyId = (int)$ctx->input('beauty_id', 0);
        if ($beautyId <= 0) {
            return self::fail($return, -1, 'beauty_error5');
        }
        if (Beauty::own($uid, $beautyId) === []) {
            return self::fail($return, -2, 'beauty_error22');
        }
        Beauty::updateOwn($uid, $beautyId, ['is_play' => 1]);
        $return = self::indexInfo($member, $beautyId, time());
        $return['status'] = 1;
        return $return;
    }

    /**
     * 取消升级: c=beauty&m=cancel_levelup&beauty_id=
     *
     * status：-4 美人不在升级状态；-3 获取美人信息失败；-2 参数错误；-1 等级不足；1 成功
     */
    public static function cancel_levelup(Context $ctx): array
    {
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null || !self::isOpen($member)) {
            return self::fail($return, -1, 'beauty_error1');
        }
        $uid = (int)$member['uid'];
        $beautyId = (int)$ctx->input('beauty_id', 0);
        if ($beautyId <= 0) {
            return self::fail($return, -2, 'beauty_error5');
        }
        $beautyInfo = Beauty::own($uid, $beautyId);
        if ($beautyInfo === []) {
            return self::fail($return, -3, 'beauty_error22');
        }
        if ($beautyInfo['up_status'] === 0) {
            return self::fail($return, -4, 'beauty_not_leveup_error');
        }
        Beauty::updateLevelStatus($uid, $beautyId, 0, time());
        $return['status'] = 1;
        return $return;
    }

    // ---------------------------------------------------------------------------------------------
    // 互动 / 赠礼 / 送花
    // ---------------------------------------------------------------------------------------------

    /**
     * 互动主页面: c=beauty&m=active_index
     *
     * status：-2 获取互动信息失败；-1 等级不足；1 成功（三种互动剩余次数、花费、宠幸 vip 限制）
     */
    public static function active_index(Context $ctx): array
    {
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null || !self::isOpen($member)) {
            return self::fail($return, -1, 'beauty_error1');
        }
        $result = Beauty::activeTimes($member, time());
        // 旧版算出问候冷却后又直接置 0
        $return = $result;
        $return['g_dateline'] = 0;
        // 宠幸按钮：vip 5 以上可点
        $return['three'] = (int)$member['vip'] < self::LOVE_VIP ? 0 : 1;
        $return['spend_greet'] = self::SPEND['greet'];
        $return['spend_close'] = self::SPEND['close'];
        $return['spend_love'] = self::SPEND['love'];
        $return['love_vip'] = self::LOVE_VIP;
        $return['status'] = 1;
        return $return;
    }

    /**
     * 互动: c=beauty&m=active_action&beauty_id=&type=（1 问候 10 万银币、2 亲近 100 元宝、3 宠幸 500 元宝（vip 5））
     *
     * 每种每天 5 次；好感度 + 8，并按互动奖励表三个奖池各抽一份（宠幸的碎片固定为该美人的碎片）。
     * status：-10 操作太快了；-9 宠幸次数不足；-8 亲近次数不足；-7 问候冷却中；-6 问候次数不足；-5 元宝不足；
     * -4 银币不足 / 升级中；-3 美人信息错误；-2 参数错误；-1 等级 / VIP 等级不足；1 成功
     */
    public static function active_action(Context $ctx): array
    {
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        $uid = (int)$member['uid'];
        $now = time();
        if (!Beauty::lock(self::ACTIVE_ACTION_LOCK, $uid)) {
            return self::fail($return, -10, 'fast_error');
        }
        if (!self::isOpen($member)) {
            Beauty::unlock(self::ACTIVE_ACTION_LOCK, $uid);
            return self::fail($return, -1, 'beauty_error1');
        }
        // 初始化互动次数
        Beauty::activeTimes($member, $now);
        // 1 问候 2 亲近 3 宠幸 其余非法
        $type = (int)$ctx->input('type', 0);
        $bid = (int)$ctx->input('beauty_id', 0);
        if ($type < 1 || $type > 3 || $bid < 1) {
            Beauty::unlock(self::ACTIVE_ACTION_LOCK, $uid);
            return self::fail($return, -2, 'beauty_error5');
        }
        if (Beauty::template($bid) === []) {
            Beauty::unlock(self::ACTIVE_ACTION_LOCK, $uid);
            return self::fail($return, -3, 'beauty_error2');
        }
        $beautyInfo = Beauty::own($uid, $bid);
        if ($beautyInfo !== [] && ($beautyInfo['up_status'] === 1 || $beautyInfo['up_status'] === 2)) {
            Beauty::unlock(self::ACTIVE_ACTION_LOCK, $uid);
            return self::fail($return, -4, 'beauty_isup');
        }
        if ($type === 3 && (int)$member['vip'] < self::LOVE_VIP) {
            Beauty::unlock(self::ACTIVE_ACTION_LOCK, $uid);
            return self::fail($return, -1, 'beauty_error15');
        }

        $activeLog = Beauty::activeTimes($member, $now);
        // 次数、冷却、花费判断（花费原子扣除）
        [$field, $countStatus, $countMsg, $sayType] = match ($type) {
            1 => ['greeting', -6, 'beauty_greetnum_error', 5],
            2 => ['close', -8, 'beauty_error16', 6],
            default => ['love', -9, 'beauty_error16', 4],
        };
        if ($activeLog[$field] <= 0) {
            Beauty::unlock(self::ACTIVE_ACTION_LOCK, $uid);
            return self::fail($return, $countStatus, $countMsg);
        }
        $lastnum = $activeLog[$field] - 1;
        if ($type === 1 && $activeLog['g_dateline'] > $now) {
            Beauty::unlock(self::ACTIVE_ACTION_LOCK, $uid);
            // 问候次数冷却中
            return self::fail($return, -7, 'beauty_greettime_error');
        }
        if ($type === 1) {
            if ((int)$member['silver'] < self::SPEND['greet'] || !Member::deductCurrency($uid, silver: self::SPEND['greet'])) {
                Beauty::unlock(self::ACTIVE_ACTION_LOCK, $uid);
                return self::fail($return, -4, 'beauty_error17');
            }
        } else {
            $gold = $type === 2 ? self::SPEND['close'] : self::SPEND['love'];
            if ((int)$member['gold'] < $gold || !Member::deductCurrency($uid, gold: $gold)) {
                Beauty::unlock(self::ACTIVE_ACTION_LOCK, $uid);
                return self::fail($return, -5, 'beauty_error7');
            }
        }
        // 美人私语
        $return['say'] = self::sayInfo($bid, $sayType)['say'] ?? null;

        // 减少次数
        Beauty::useActive($uid, $type, $now);
        // 增加好感度
        Beauty::updateHappy($uid, $bid, self::HAPPY[$type], $now);
        // 根据 type 算出奖励
        $result = self::getActiveRate($type);
        if ($type === 3) {
            $result = self::fixPamperChip($result, $bid);
        }
        $reward = self::rewardList($result, (int)$member['level'], $uid, true);
        // 添加奖励
        self::addReward($member, $result, $now, 'active_action');
        $happy = self::happyInfo($uid, $bid);
        // 依赖未移植：春节送福活动 actnewyear_model->add_task_num(version, 17)（亲近）

        Beauty::unlock(self::ACTIVE_ACTION_LOCK, $uid);
        $after = self::member($uid) ?? $member;
        $memint = MemberIntegral::get($uid);
        $return['special_skill'] = self::specialSkillById($uid, $bid, $now);
        $return['status'] = 1;
        $return['reward'] = $reward;
        $return['lastnum'] = $lastnum;
        $return['cd_time'] = $type === 1 ? Beauty::GREET_TIME : 0;
        $return['happy'] = $happy;
        $return['happy']['rose'] = (int)$memint['beauty_rose'];
        $return['diamond'] = (int)$memint['beauty_diamond'];
        $return['gold'] = (int)$after['gold'];
        $return['silver'] = (int)$after['silver'];
        return $return;
    }

    /**
     * 赠礼: c=beauty&m=gift&beauty_id=&type=（1 花 500 元宝 +50、2 花 10 钻石 +50、3 花 18 钻石 +100 好感度；未拥有的美人先建记录）
     *
     * status：-6 钻石不足；-5 操作太快了 / 好感度已满；-4 升级中；-3 元宝不足；-2 参数错误；-1 等级不足；1 成功
     */
    public static function gift(Context $ctx): array
    {
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        $uid = (int)$member['uid'];
        $now = time();
        if (!Beauty::lock(self::GIFT_LOCK, $uid)) {
            return self::fail($return, -5, 'fast_error');
        }
        if (!self::isOpen($member)) {
            Beauty::unlock(self::GIFT_LOCK, $uid);
            return self::fail($return, -1, 'beauty_error1');
        }
        $beautyId = (int)$ctx->input('beauty_id', 0);
        $type = (int)$ctx->input('type', 0);
        // 美人不存在时旧版会建出一条空名字的记录，这里按参数错误
        if ($beautyId <= 0 || !in_array($type, [1, 2, 3], true) || Beauty::template($beautyId) === []) {
            Beauty::unlock(self::GIFT_LOCK, $uid);
            return self::fail($return, -2, 'beauty_error5');
        }
        if (Beauty::own($uid, $beautyId) === []) {
            // 初始化美人信息
            Beauty::initOwn($uid, $beautyId, $now);
        }
        $result = Beauty::own($uid, $beautyId);
        if ($result['up_status'] === 1 || $result['up_status'] === 2) {
            Beauty::unlock(self::GIFT_LOCK, $uid);
            return self::fail($return, -4, 'beauty_isup');
        }
        $lastTime = $now - $result['ratio_time'];
        $lastTime = $lastTime > 0 ? $lastTime : 0;
        // 当前好感度的值
        $ratio = $result['ratio'] - (int)ceil($lastTime / 3600);
        if ($ratio >= 100) {
            Beauty::unlock(self::GIFT_LOCK, $uid);
            return self::fail($return, -5, 'beauty_love_is_enough');
        }

        $arrInfo = self::GIFT_ARR[$type - 1];
        $actDiamond = 0;
        $diamondNum = 0;
        if ($type === 1) {
            if ((int)$member['gold'] < $arrInfo['gold'] || !Member::deductCurrency($uid, gold: $arrInfo['gold'])) {
                Beauty::unlock(self::GIFT_LOCK, $uid);
                return self::fail($return, -3, 'beauty_error7');
            }
        } else {
            $diamondNum = (int)MemberIntegral::get($uid)['beauty_diamond'];
            $actDiamond = $arrInfo['diamond'];
            if ($diamondNum < $actDiamond
                || MemberIntegral::change($uid, $actDiamond, 'beauty_diamond', 2, self::LOG_CLASS, 'gift', $now) === []) {
                Beauty::unlock(self::GIFT_LOCK, $uid);
                return self::fail($return, -6, 'diamond_error');
            }
        }
        // 更新美人好感度值
        Beauty::updateHappy($uid, $beautyId, $arrInfo['ratio'], $now);
        $happy = self::happyInfo($uid, $beautyId);
        $happy['rose'] = (int)MemberIntegral::get($uid)['beauty_rose'];

        Beauty::unlock(self::GIFT_LOCK, $uid);
        $return['gold'] = (int)(self::member($uid)['gold'] ?? 0);
        if ($type !== 1) {
            $return['diamond_num'] = $diamondNum - $actDiamond;
        }
        $return['special_skill'] = self::specialSkillById($uid, $beautyId, $now);
        $return['say'] = self::sayInfo($beautyId, 8)['say'] ?? null;
        $return['status'] = 1;
        $return['happy'] = $happy;
        return $return;
    }

    /**
     * 送花: c=beauty&m=give_flower&beauty_id=（激活中的美人花 5 朵玫瑰，技能生效时间延长 24 小时）
     *
     * status：-3 操作太快了；-2 参数错误；-1 等级不足；1 成功（skill_cd、rose）；2 玫瑰花不足；3 美人信息错误；4 美人未激活 / 升级中
     */
    public static function give_flower(Context $ctx): array
    {
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        $uid = (int)$member['uid'];
        $now = time();
        if (!Beauty::lock(self::GIVE_LOCK, $uid)) {
            return self::fail($return, -3, 'fast_error');
        }
        if (!self::isOpen($member)) {
            Beauty::unlock(self::GIVE_LOCK, $uid);
            return self::fail($return, -1, 'beauty_error1');
        }
        $bid = (int)$ctx->input('beauty_id', 0);
        if ($bid <= 0) {
            Beauty::unlock(self::GIVE_LOCK, $uid);
            return self::fail($return, -2, 'beauty_error5');
        }
        $beautyInfo = Beauty::own($uid, $bid);
        if ($beautyInfo === []) {
            Beauty::unlock(self::GIVE_LOCK, $uid);
            return self::fail($return, 3, 'beauty_active_error');
        }
        if ($beautyInfo['open_status'] !== 1) {
            Beauty::unlock(self::GIVE_LOCK, $uid);
            return self::fail($return, 4, 'beauty_active_error');
        }
        if ($beautyInfo['up_status'] === 1 || $beautyInfo['up_status'] === 2) {
            Beauty::unlock(self::GIVE_LOCK, $uid);
            return self::fail($return, 4, 'beauty_isup');
        }
        // 玫瑰花数量是否足够并扣除
        $roseChange = (int)MemberIntegral::get($uid)['beauty_rose'] < self::ROSE_NUM
            ? []
            : MemberIntegral::change($uid, self::ROSE_NUM, 'beauty_rose', 2, self::LOG_CLASS, 'give_flower', $now);
        if ($roseChange === []) {
            Beauty::unlock(self::GIVE_LOCK, $uid);
            return self::fail($return, 2, 'beauty_error34');
        }
        if ($beautyInfo['effect_time'] <= 0) {
            // 本次激活后首次送花：wakeup_num + 1
            Beauty::updateWakeupInfo($uid, $bid, 1, $now);
        }
        // 更新最终生效时间
        $effectChange = Beauty::extendEffect($uid, $bid, self::EFFECT_TIME, $now);
        $lastEffectTime = ($effectChange['effect_end_time'] ?? 0) - $now;
        Beauty::unlock(self::GIVE_LOCK, $uid);
        $return['say'] = self::sayInfo($bid, 7)['say'] ?? null;
        $return['status'] = 1;
        $return['skill_cd'] = $lastEffectTime > 0 ? $lastEffectTime : 0;
        $return['rose'] = $roseChange['beauty_rose'];
        return $return;
    }

    /**
     * 购买玫瑰花: c=beauty&m=buy_rose（20 元宝一朵）
     *
     * status：-2 操作太快了；-1 元宝不足；1 成功（gold、rose）
     */
    public static function buy_rose(Context $ctx): array
    {
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        $uid = (int)$member['uid'];
        $now = time();
        if (!Beauty::lock(self::BUY_ROSE_LOCK, $uid)) {
            return self::fail($return, -2, 'fast_error');
        }
        if ((int)$member['gold'] < self::BUY_ROSE || !Member::deductCurrency($uid, gold: self::BUY_ROSE)) {
            Beauty::unlock(self::BUY_ROSE_LOCK, $uid);
            return self::fail($return, -1, 'beauty_error7');
        }
        $roseNum = (int)MemberIntegral::get($uid)['beauty_rose'];
        MemberIntegral::change($uid, 1, 'beauty_rose', 1, self::LOG_CLASS, 'buy_rose', $now);
        Beauty::addBuyRoseLog(['uid' => $uid, 'price' => self::BUY_ROSE, 'dateline' => $now]);
        Beauty::unlock(self::BUY_ROSE_LOCK, $uid);
        $return['status'] = 1;
        $return['gold'] = (int)(self::member($uid)['gold'] ?? 0);
        $return['rose'] = $roseNum + 1;
        return $return;
    }

    /**
     * 领取特殊技能奖励: c=beauty&m=action_special_reward&beauty_id=
     *
     * status：-6 操作太快了；-5 当前没有可领取的奖励；-4 不在技能生效时间内 / 升级中；-3 美人未激活；-2 参数错误；-1 等级不足；
     * 1 成功（num、all_gold、all_silver）
     */
    public static function action_special_reward(Context $ctx): array
    {
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return ['status' => 0];
        }
        return self::actionSpecialReward($member, (int)$ctx->input('beauty_id', 0), time());
    }

    /**
     * 碎片转化: c=beauty&m=conversion_chip&beauty_id=（进阶等级时 6 个碎片各消耗 1 个，好感度 + 8，按宠幸奖励表抽奖）
     *
     * status：-6 操作太快了；-5 碎片不足；-4 未在进阶状态；-3 美人未激活；-2 参数错误；-1 等级不足；1 成功
     */
    public static function conversion_chip(Context $ctx): array
    {
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        $uid = (int)$member['uid'];
        $now = time();
        if (!Beauty::lock(self::CONVERSION_LOCK, $uid)) {
            return self::fail($return, -6, 'fast_error');
        }
        if (!self::isOpen($member)) {
            Beauty::unlock(self::CONVERSION_LOCK, $uid);
            return self::fail($return, -1, 'beauty_error1');
        }
        $bid = (int)$ctx->input('beauty_id', 0);
        if ($bid <= 0) {
            Beauty::unlock(self::CONVERSION_LOCK, $uid);
            return self::fail($return, -2, 'beauty_error5');
        }
        $beautyInfo = Beauty::own($uid, $bid);
        if ($beautyInfo === []) {
            Beauty::unlock(self::CONVERSION_LOCK, $uid);
            return self::fail($return, -3, 'beauty_active_error');
        }
        if (Beauty::upStatus($beautyInfo['beauty_level'])['up_status'] !== 2) {
            Beauty::unlock(self::CONVERSION_LOCK, $uid);
            return self::fail($return, -4, 'beauty_not_advance');
        }
        $chipInfo = Beauty::chips($uid, $bid);
        $enough = $chipInfo !== [];
        for ($i = 0; $i <= 5 && $enough; $i++) {
            if (($chipInfo[$i]['chip'] ?? 0) <= 0) {
                $enough = false;
            }
        }
        if (!$enough) {
            Beauty::unlock(self::CONVERSION_LOCK, $uid);
            return self::fail($return, -5, 'beauty_chip_error');
        }
        // 减碎片
        Beauty::subAllChips($uid, $bid);
        // 增加好感度
        Beauty::updateHappy($uid, $bid, self::HAPPY[3], $now);
        // 按宠幸奖励表抽奖（旧版这里的奖励展示不把银币乘以主公等级，发放时照乘）
        $result = self::fixPamperChip(self::getActiveRate(3), $bid);
        $reward = self::rewardList($result, (int)$member['level'], $uid, false);
        self::addReward($member, $result, $now, 'conversion_chip');
        $happy = self::happyInfo($uid, $bid);

        Beauty::unlock(self::CONVERSION_LOCK, $uid);
        $after = self::member($uid) ?? $member;
        $memint = MemberIntegral::get($uid);
        $chips = Beauty::chips($uid, $bid);
        foreach ($chips as &$val) {
            $val['own'] = $val['chip'] > 0 ? 1 : 0;
        }
        unset($val);
        $return['special_skill'] = self::specialSkillById($uid, $bid, $now);
        $return['status'] = 1;
        $return['reward'] = $reward;
        $return['happy'] = $happy;
        $return['happy']['rose'] = (int)$memint['beauty_rose'];
        $return['diamond'] = (int)$memint['beauty_diamond'];
        $return['chip'] = $chips;
        $return['gold'] = (int)$after['gold'];
        $return['silver'] = (int)$after['silver'];
        return $return;
    }

    // ---------------------------------------------------------------------------------------------
    // 内部：主页 / 特殊技能
    // ---------------------------------------------------------------------------------------------

    /**
     * 旧版 index($bid)：$bid 为 0 时取激活中 id 最小的美人（都没激活取第一个美人），否则取 $bid
     *
     * 进入时顺带完成到时间的升级、跨天清空激活次数、到期的特殊技能自动发放（get_special_skill）。
     *
     * @param array<string, mixed> $member
     * @return array<string, mixed>
     */
    private static function indexInfo(array $member, int $bid, int $now): array
    {
        $return = ['status' => 0];
        if (!self::isOpen($member)) {
            return self::fail($return, -1, 'beauty_error1');
        }
        $uid = (int)$member['uid'];
        $skillCd = 0;
        $beautyActive = 0;
        $beautyQuality = 1;
        $beautyLevel = 1;
        $skill = [];

        // 获取所有美人信息
        $beautyList = Beauty::templates();
        if ($beautyList === []) {
            return self::fail($return, -2, 'beauty_error2');
        }
        // 用户激活中的美人
        $activeIds = [];
        foreach (Beauty::ownAll($uid) as $val) {
            if ($val['open_status'] === 1) {
                $activeIds[$val['beauty_id']] = $val;
            }
        }
        // 美人激活状态处理
        $newBeautyInfo = [];
        $baseBeauty = [];
        $allBeauty = [];
        foreach ($beautyList as $key => $val) {
            $baseBeauty[$val['id']] = $val;
            $baseBeauty[$val['id']]['level'] = 1;
            $allBeauty[$key] = ['beauty_id' => $val['id'], 'name' => $val['name']];
            if (isset($activeIds[$val['id']])) {
                $allBeauty[$key]['active'] = 1;
                $allBeauty[$key]['quality'] = $activeIds[$val['id']]['quality'];
                $baseBeauty[$val['id']]['info'] = $activeIds[$val['id']];
            } else {
                $allBeauty[$key]['active'] = 0;
                $allBeauty[$key]['quality'] = 1;
                $own = Beauty::own($uid, $val['id']);
                if ($own !== []) {
                    $allBeauty[$key]['quality'] = $own['quality'];
                }
            }
            if ($bid > 0 && $bid === $val['id']) {
                $newBeautyInfo = $val;
            }
        }
        $return['beauty_info']['active'] = $allBeauty;

        // 显示默认美人信息
        if ($activeIds !== []) {
            if ($bid > 0) {
                $beautyId = $bid;
                $beautyInfo = $activeIds[$bid] ?? $newBeautyInfo;
            } else {
                $ids = array_keys($activeIds);
                sort($ids);
                $beautyId = $ids[0];
                $beautyInfo = $activeIds[$beautyId];
            }
        } elseif ($bid > 0) {
            $beautyId = $bid;
            $beautyInfo = $newBeautyInfo;
        } else {
            $beautyInfo = $beautyList[0];
            $beautyId = $beautyInfo['id'];
        }
        if ($beautyInfo === []) {
            // 美人 id 不存在：旧版拼出不完整的 SQL 报错，这里按获取美人模板表失败
            return self::fail($return, -2, 'beauty_error2');
        }
        $skillIds = [(int)$beautyInfo['skill1'], (int)$beautyInfo['skill2'], (int)$beautyInfo['skill3']];

        $beautyInfo = Beauty::own($uid, $beautyId);
        // 升级成功处理
        $upCd = 0;
        $upSucc = 0;
        if ($beautyInfo !== []) {
            $chipInfo = Beauty::chips($uid, $beautyId);
            if ($beautyInfo['up_dateline'] > 0) {
                $upCd = $beautyInfo['up_dateline'] - $now;
                if ($upCd <= 0) {
                    $upSucc = 1;
                    $upCd = 0;
                    // 碎片各减 1、等级 + 1、清除升级状态
                    self::finishLevelUp($uid, $beautyId, $chipInfo, 0, $beautyInfo['up_dateline'], $now);
                }
            }
            // 激活时间不是今天：当天激活次数与时间清 0
            if ($beautyInfo['wakeup_dateline'] > 0 && date('ymd', $beautyInfo['wakeup_dateline']) !== date('ymd', $now)) {
                Beauty::updateWakeupInfo($uid, $beautyId, 0, $now);
            }
            // 别人抢夺时替本人完成的升级（is_play 2）：播放升级动画
            if ($beautyInfo['is_play'] === 2) {
                $upSucc = 1;
                $upCd = 0;
            }
        }

        // 重新获取美人信息
        $beautyInfo = Beauty::own($uid, $beautyId);
        if ($beautyInfo !== []) {
            $beautyQuality = $beautyInfo['quality'];
            $beautyLevel = $beautyInfo['beauty_level'];
            $beautyActive = $beautyInfo['open_status'];
            $skillCd = $beautyInfo['effect_end_time'] - $now;
            $skillCd = $skillCd > 0 ? $skillCd : 0;
        }
        // 美人好感度信息
        if ($beautyInfo === []) {
            $beautyHappy = ['beauty_id' => $beautyId, 'ratio' => 0, 'skill' => 0, 'quality' => 0];
        } else {
            $beautyHappy = [
                'quality' => Beauty::happyRatio($beautyInfo['ratio'])['quality'] ?? null,
                'ratio' => $beautyInfo['ratio'],
            ];
            // 好感度每小时衰减 1
            if ($beautyInfo['ratio'] > 0 && $beautyInfo['ratio_time'] > 0) {
                $ratio = $beautyInfo['ratio'] - (int)ceil(($now - $beautyInfo['ratio_time']) / 3600) + 1;
                $beautyHappy['ratio'] = $ratio <= 0 ? 0 : $ratio;
                $beautyHappy['quality'] = Beauty::happyRatio($beautyHappy['ratio'])['quality'] ?? null;
            }
        }
        $beautyHappy['skill'] = Beauty::happyRatio($beautyHappy['ratio'])['skill'] ?? null;

        // 进阶或升级状态（80 级满级为 3），进阶时可转化碎片
        $upStatusInfo = Beauty::upStatus($beautyLevel);
        $upStatus = $beautyLevel >= self::SKILL_MAXLEV ? 3 : $upStatusInfo['up_status'];
        $conversion = $upStatus === 2 ? 1 : 0;
        $levelArray = match ($upStatusInfo['lock']) {
            1 => [$beautyLevel, 1, 1],
            2 => [$beautyLevel, $beautyLevel - 20, 1],
            3 => [$beautyLevel, $beautyLevel - 20, $beautyLevel - 60],
            default => [],
        };
        if ($levelArray === []) {
            // 超过 80 级：旧版拼出不完整的 SQL 报错，这里按获取美人技能失败
            return self::fail($return, -3, 'beauty_error3');
        }

        // 美人技能
        foreach ($skillIds as $key => $val) {
            $info = Beauty::skillInfo($beautyId, $val, $levelArray[$key]);
            $skill[$key] = [
                'id' => $info['skill_property'] ?? null,
                'name' => $info['skill_name'] ?? null,
                'content' => $info['skill_content'] ?? null,
                'value' => $info['skill_value'] ?? null,
            ];
            if ($upStatusInfo['lock'] >= $key + 1) {
                $skill[$key]['lock'] = 1;
            } else {
                $skill[$key]['lock'] = 0;
                // 开启条件
                if ($key === 1) {
                    $skill[$key]['lock_msg'] = 21;
                } elseif ($key === 2) {
                    $skill[$key]['lock_msg'] = 61;
                }
            }
        }

        // 碎片
        $beautyChip = self::chipList($uid, $beautyId);

        // 下一级技能
        foreach ($skillIds as $key => $val) {
            $skill[$key]['next_value'] = $beautyLevel >= self::SKILL_MAXLEV
                ? 0
                : (Beauty::skillInfo($beautyId, $val, $levelArray[$key] + 1)['skill_value'] ?? null);
        }

        // 免战次数
        $battleInfo = Beauty::userInfo($uid, $now);
        $battleTimes = $battleInfo !== [] ? $battleInfo['times'] : Beauty::BATTLE_NUM;
        $battleDateline = $battleInfo !== [] ? $battleInfo['cd_time'] : 0;

        // 玫瑰花、钻石数量
        $memint = MemberIntegral::get($uid);
        $beautyHappy['rose'] = (int)$memint['beauty_rose'];
        $beautyHappy['diamond'] = (int)$memint['beauty_diamond'];

        if ($beautyInfo !== []) {
            // 旧版先写一次 info（随后整体覆盖），只影响键的位置
            $return['beauty_info']['info'] = [];
        }
        $return['beauty_info']['special_skill'] = self::specialSkill($member, $baseBeauty[$beautyId], $now);
        $return['status'] = 1;
        $return['beauty_info']['skill'] = $skill;
        $return['beauty_info']['chip'] = $beautyChip;
        $return['beauty_info']['happy'] = $beautyHappy;
        $return['beauty_info']['battle'] = ['gold' => self::BATTLE_GOLD, 'times' => $battleTimes];
        $return['beauty_info']['gift_info'] = self::GIFT_ARR;
        $battleCd = $battleDateline > $now ? $battleDateline - $now : 0;
        $return['beauty_info']['info'] = [
            'beauty_id' => $beautyId,
            'beauty_quality' => $beautyQuality,
            'beauty_level' => $beautyLevel,
            'beauty_active' => $beautyActive,
            'skill_cd' => $skillCd,
            'is_active' => 1,
            'battle_cd' => $battleCd,
            'up_status' => $upStatus,
            'sub_rose' => self::ROSE_NUM,
            'max_num' => self::maxNum($member),
            'buy_rose' => self::BUY_ROSE,
            'up_cd' => $upCd,
            'up_succ' => $upSucc,
            'conversion_status' => $conversion,
        ];
        // 美人说话
        $return['say'] = self::sayInfo($beautyId, 1)['say'] ?? null;
        return $return;
    }

    /**
     * 特殊技能信息（get_special_skill：index 用）
     *
     * 技能已到期且有效时，自动按到期时的好感度发放剩余产出、发系统信件并清空生效时间（只有清空成功的请求发放）；
     * 生效中时返回当前可领取数量 num（好感度取进入 index 时激活中的记录）。
     *
     * @param array<string, mixed> $member
     * @param array<string, mixed> $baseBeauty 美人模板（激活中时带 info 为拥有记录）
     * @return array<string, mixed>
     */
    private static function specialSkill(array $member, array $baseBeauty, int $now): array
    {
        $uid = (int)$member['uid'];
        $bid = (int)$baseBeauty['id'];
        [$return, $openId, $openLevel] = self::specialSkillId($baseBeauty);
        $beautyInfo = Beauty::own($uid, $bid);
        $return['level'] = self::specialSkillLevel((int)($beautyInfo['beauty_level'] ?? 0), $openId);
        $skillInfo = Beauty::skill($bid, $return['id'], $return['level']);
        $return['num'] = 0;
        $return['allnum'] = 0;
        $return['lock'] = 0;
        $return['lock_msg'] = $openLevel;
        if ($beautyInfo !== [] && $beautyInfo['beauty_level'] >= (int)$openLevel) {
            $return['lock'] = 1;
        }
        if ($skillInfo === []) {
            return $return;
        }
        $return['skill_name'] = $skillInfo[0]['skill_name'];
        $bInfo = $beautyInfo;
        if ($bInfo === []) {
            return $return;
        }
        // 技能生效时间已过：自动领取未领取的产出
        if ($bInfo['effect_end_time'] !== 0 && $bInfo['effect_end_time'] < $now && $return['lock'] === 1) {
            // 清空生效时间（带旧值条件，并发时只有一个请求发放）
            $taken = DB::table('beauty_own_log')->where('uid', $uid)->where('beauty_id', $bid)
                ->where('effect_end_time', $bInfo['effect_end_time'])->whereNull('deleted_at')
                ->update(['effect_time' => 0, 'effect_end_time' => 0]) > 0;
            if ($taken) {
                $allNum = $skillInfo[0]['skill_value'];
                $rewardLog = Beauty::specialRewardLog($uid, $bid, $bInfo['wakeup_num']);
                // 技能结束时的好感度
                $ratio = 0;
                if ($bInfo['ratio'] > 0 && $bInfo['ratio_time'] > 0) {
                    $ratio = $bInfo['ratio'] - (int)ceil(($bInfo['effect_end_time'] - $bInfo['ratio_time']) / 3600) + 1;
                    if ($ratio <= 0) {
                        $ratio = 0;
                    }
                }
                $skill = Beauty::happyRatio($ratio)['skill'] ?? 0;
                if ($rewardLog !== []) {
                    $num = (int)ceil(($allNum / 86400) * ($bInfo['effect_end_time'] - $rewardLog['dateline']) * ($skill / 100));
                } else {
                    $num = (int)ceil($allNum * ($skill / 100));
                }
                self::addSpecialReward($uid, $return['id'], $num, 'index', $now);
                Beauty::addSpecialRewardLog([
                    'uid' => $uid,
                    'beauty_id' => $bid,
                    'num' => $num,
                    'type' => $return['id'],
                    'wakeup_num' => $bInfo['wakeup_num'],
                    'dateline' => $now,
                ]);
                $message = sprintf(self::LANG['skill_end_time_finish'], $bInfo['beauty_name'])
                    . self::LANG['skill_end_time_reward']
                    . (self::LANG['skill_end_type' . $return['id']] ?? '')
                    . $num . ', '
                    . self::LANG['skill_check'];
                MessageHandler::sendSystemMessage([$uid], self::MESSAGE_TYPE_SYSTEM, self::LANG_MESSAGE_SYSTEM_TITLE, $message);
            }
        }
        // 当前技能生效中：可领取数量
        if ($bInfo['open_status'] === 1 && $bInfo['effect_time'] <= $now && $bInfo['effect_end_time'] >= $now
            && $bInfo['beauty_level'] >= (int)$openLevel) {
            $ratio = 0;
            $info = $baseBeauty['info'] ?? [];
            if (($info['ratio'] ?? 0) > 0 && ($info['ratio_time'] ?? 0) > 0) {
                $ratio = self::clampRatio($info['ratio'] - (int)ceil(($now - $info['ratio_time']) / 3600) + 1);
            }
            $return['num'] = self::specialNum($uid, $bid, $bInfo, (int)$skillInfo[0]['skill_value'], $ratio, $now);
        }
        return $return;
    }

    /**
     * 特殊技能信息（get_special_skill_by_id：按拥有记录，不自动发放）
     *
     * @return array<string, mixed>
     */
    private static function specialSkillById(int $uid, int $bid, int $now): array
    {
        $base = Beauty::own($uid, $bid);
        [$return, $openId, $openLevel] = self::specialSkillId($base);
        $return['level'] = self::specialSkillLevel((int)($base['beauty_level'] ?? 0), $openId);
        if ($base === []) {
            // 没有拥有记录：旧版拼出不完整的 SQL 报错（各调用方此时都已建好记录，走不到）
            $return['num'] = $return['allnum'] = $return['lock'] = 0;
            $return['lock_msg'] = $openLevel;
            return $return;
        }
        $skillInfo = Beauty::skill($bid, $return['id'], $return['level']);
        $return['num'] = 0;
        $return['allnum'] = 0;
        $return['lock'] = 0;
        $return['lock_msg'] = $openLevel;
        if ($base['beauty_level'] >= (int)$openLevel) {
            $return['lock'] = 1;
        }
        if ($skillInfo !== []) {
            $return['skill_name'] = $skillInfo[0]['skill_name'];
            $bInfo = Beauty::own($uid, $bid);
            if ($bInfo !== [] && $bInfo['open_status'] === 1 && $bInfo['effect_time'] <= $now && $bInfo['effect_end_time'] >= $now
                && $bInfo['beauty_level'] >= (int)$openLevel) {
                $ratio = 0;
                if ($bInfo['ratio'] > 0 && $bInfo['ratio_time'] > 0) {
                    $ratio = self::clampRatio($bInfo['ratio'] - (int)ceil(($now - $bInfo['ratio_time']) / 3600) + 1);
                }
                $return['num'] = self::specialNum($uid, $bid, $bInfo, (int)$skillInfo[0]['skill_value'], $ratio, $now);
            }
        }
        return $return;
    }

    /**
     * 特殊技能 id（第一个属性 >= 13 的技能）、所在技能位与开启等级（1 / 21 / 61；没有特殊技能为 null）
     *
     * @param array<string, mixed> $base
     * @return array{0: array{id: int}, 1: int, 2: int|null}
     */
    private static function specialSkillId(array $base): array
    {
        $return = ['id' => 0];
        $openId = 0;
        for ($i = 1; $i <= 3; $i++) {
            if (isset($base['skill' . $i]) && (int)$base['skill' . $i] >= 13) {
                $return['id'] = (int)$base['skill' . $i];
                $openId = $i;
                break;
            }
        }
        $openLevel = match ($openId) {
            1 => 1,
            2 => 21,
            3 => 61,
            default => null,
        };
        return [$return, $openId, $openLevel];
    }

    /**
     * 特殊技能等级：美人等级 > 1 时，技能位 1 取美人等级、2 取等级 - 20、3 取等级 - 40（旧版如此）；不大于 0 按 1
     */
    private static function specialSkillLevel(int $beautyLevel, int $openId): int
    {
        $level = 1;
        if ($beautyLevel > 1) {
            $level = match ($openId) {
                1 => $beautyLevel,
                2 => $beautyLevel - 20,
                3 => $beautyLevel - 40,
                default => 1,
            };
        }
        return $level <= 0 ? 1 : $level;
    }

    /**
     * 生效中可领取的产出：24 小时总量 × 距上次领取（或生效起点）的秒数 / 86400 × 好感度技能效果，向下取整
     *
     * @param array<string, mixed> $bInfo
     */
    private static function specialNum(int $uid, int $bid, array $bInfo, int $num, int $ratio, int $now): int
    {
        $rewardLog = Beauty::specialRewardLog($uid, $bid, $bInfo['wakeup_num']);
        $upTimes = $rewardLog !== [] ? $rewardLog['dateline'] : $bInfo['effect_time'];
        $skill = Beauty::happyRatio($ratio)['skill'] ?? 0;
        return (int)floor(($num / 86400) * ($now - $upTimes) * ($skill / 100));
    }

    private static function clampRatio(int $ratio): int
    {
        if ($ratio <= 0) {
            return 0;
        }
        return $ratio >= 100 ? 100 : $ratio;
    }

    /**
     * 领取特殊技能奖励（旧版 action_special_reward($bid)：rest_beauty / advance_action / level_up 内部也调用，只取副作用）
     *
     * @param array<string, mixed> $member
     * @return array<string, mixed>
     */
    private static function actionSpecialReward(array $member, int $bid, int $now): array
    {
        $return = ['status' => 0];
        $uid = (int)$member['uid'];
        if (!Beauty::lock(self::SPECIAL_REWARD_LOCK, $uid)) {
            return self::fail($return, -6, 'fast_error');
        }
        if (!self::isOpen($member)) {
            Beauty::unlock(self::SPECIAL_REWARD_LOCK, $uid);
            return self::fail($return, -1, 'beauty_error1');
        }
        if ($bid <= 0) {
            Beauty::unlock(self::SPECIAL_REWARD_LOCK, $uid);
            return self::fail($return, -2, 'beauty_error5');
        }
        $beautyInfo = Beauty::own($uid, $bid);
        if ($beautyInfo === []) {
            Beauty::unlock(self::SPECIAL_REWARD_LOCK, $uid);
            return self::fail($return, -3, 'beauty_active_error');
        }
        if ($beautyInfo['up_status'] === 1 || $beautyInfo['up_status'] === 2) {
            Beauty::unlock(self::SPECIAL_REWARD_LOCK, $uid);
            return self::fail($return, -4, 'beauty_isup');
        }
        if ($beautyInfo['open_status'] !== 1) {
            Beauty::unlock(self::SPECIAL_REWARD_LOCK, $uid);
            return self::fail($return, -3, 'beauty_active_error');
        }
        if ($beautyInfo['effect_time'] >= $now || $beautyInfo['effect_end_time'] <= $now
            || $beautyInfo['effect_time'] === 0 || $beautyInfo['effect_end_time'] === 0) {
            Beauty::unlock(self::SPECIAL_REWARD_LOCK, $uid);
            return self::fail($return, -4, 'beauty_skill_isnot');
        }
        $type = self::specialSkillId($beautyInfo)[0]['id'];
        // 可领取的奖励
        $totalNum = self::specialSkillById($uid, $bid, $now)['num'];
        if ($totalNum <= 0) {
            Beauty::unlock(self::SPECIAL_REWARD_LOCK, $uid);
            return self::fail($return, -5, 'beauty_skill_reward_isnot');
        }
        Beauty::addSpecialRewardLog([
            'uid' => $uid,
            'beauty_id' => $bid,
            'num' => $totalNum,
            'type' => $type,
            'wakeup_num' => $beautyInfo['wakeup_num'],
            'dateline' => $now,
        ]);
        self::addSpecialReward($uid, $type, $totalNum, 'action_special_reward', $now);
        Beauty::unlock(self::SPECIAL_REWARD_LOCK, $uid);
        $after = self::member($uid) ?? $member;
        $return['status'] = 1;
        $return['num'] = $totalNum;
        $return['all_gold'] = (int)$after['gold'];
        $return['all_silver'] = (int)$after['silver'];
        return $return;
    }

    /**
     * 特殊技能产出（add_special_reward：13 银币 14 蓝宝石 15 玉石 16 原魂 17 宝石碎片 18 声望 19 精炼石）
     */
    private static function addSpecialReward(int $uid, int $type, int $num, string $method, int $now): void
    {
        if ($num <= 0) {
            return;
        }
        match ($type) {
            13 => Member::addCurrency($uid, silver: $num),
            14 => TraderLog::addJade($uid, 1, $num, class: self::LOG_CLASS, method: $method, now: $now),
            15 => MemberJade::add($uid, $num, self::LOG_CLASS, $method, $now),
            16 => OwnSoul::addSoul($uid, $num, 0, self::LOG_CLASS, $method, $now),
            17 => OwnGem::addChip($uid, $num, self::LOG_CLASS, $method, $now),
            18 => Member::addCurrency($uid, reputation: $num),
            19 => MemberStone::add($uid, $num, MemberStone::REFINE, self::LOG_CLASS, $method, $now),
            default => null,
        };
    }

    // ---------------------------------------------------------------------------------------------
    // 内部：升级 / 进阶
    // ---------------------------------------------------------------------------------------------

    /**
     * 完成升级：碎片各减 1、等级 + 1、清除升级状态（$type 0 本人完成；2 别人抢夺 / 刷新对手时替对方完成，标记播放动画）
     *
     * 先用读到的 up_dateline 做条件清除升级状态，只有清除成功的请求扣碎片、升级（旧版顺序相反，并发时会重复升级）。
     *
     * @param list<array<string, mixed>> $chipInfo
     */
    private static function finishLevelUp(int $uid, int $bid, array $chipInfo, int $type, int $upDateline, int $now): bool
    {
        $affected = DB::table('beauty_own_log')->where('uid', $uid)->where('beauty_id', $bid)->where('up_dateline', $upDateline)
            ->whereNull('deleted_at')->update(['up_status' => 0, 'up_dateline' => 0, 'is_play' => $type === 2 ? 2 : 0]);
        if ($affected <= 0) {
            return false;
        }
        foreach ($chipInfo as $val) {
            Beauty::updateChipNum($uid, $bid, $val['chip_id'], -1, $now);
        }
        Beauty::updateLevel($uid, $bid);
        return true;
    }

    /**
     * 进阶条件判断（旧版 advance_return；成功时 status 为 0，带 info 资源与 list 消耗）
     *
     * @param array<string, mixed> $member
     * @return array<string, mixed>
     */
    private static function advanceReturn(array $member, int $bid): array
    {
        $return = ['status' => 0];
        if (!self::isOpen($member)) {
            return self::fail($return, -1, 'beauty_error1');
        }
        if ($bid <= 0) {
            return self::fail($return, -2, 'beauty_error5');
        }
        $advanceList = Beauty::advancedInfo();
        if ($advanceList === []) {
            return self::fail($return, -3, 'beauty_error13');
        }
        $beautyInfo = Beauty::own((int)$member['uid'], $bid);
        if ($beautyInfo === []) {
            return self::fail($return, -4, 'beauty_error12');
        }
        $beautyLevel = $beautyInfo['beauty_level'];
        if (!isset($advanceList[$beautyLevel])) {
            return self::fail($return, -6, 'beauty_error13');
        }
        if (Beauty::skillByLevel($bid, [$beautyLevel, $beautyLevel + 1]) === []) {
            return self::fail($return, -7, 'beauty_error3');
        }
        $return['info'] = self::advanceResource($member);
        $return['list'] = $advanceList[$beautyLevel];
        return $return;
    }

    /**
     * 当前资源（进阶界面 info：1 黄宝石 2 紫宝石 3 原魂 4 声望 5 玫瑰花 6 钻石）
     *
     * @param array<string, mixed> $member
     * @return array<string, int>
     */
    private static function advanceResource(array $member): array
    {
        $uid = (int)$member['uid'];
        $jade = TraderLog::get($uid);
        $memint = MemberIntegral::get($uid);
        return [
            'type1' => 1, 'value1' => $jade['get2'] ?? 0,
            'type2' => 2, 'value2' => $jade['get3'] ?? 0,
            'type3' => 3, 'value3' => (int)$member['soul'],
            'type4' => 4, 'value4' => (int)$member['reputation'],
            'type5' => 5, 'value5' => (int)$memint['beauty_rose'],
            'type6' => 6, 'value6' => (int)$memint['beauty_diamond'],
        ];
    }

    // ---------------------------------------------------------------------------------------------
    // 内部：掠夺
    // ---------------------------------------------------------------------------------------------

    /**
     * 刷新对手（旧版 loot_reflush($beauty_id, $chip_id)；loot_action 抢到碎片后也调用）
     *
     * @param array<string, mixed> $member
     * @return array<string, mixed>
     */
    private static function reflush(array $member, int $beautyId, int $chipId, int $now): array
    {
        $return = ['status' => 0];
        $uid = (int)$member['uid'];
        if (!Beauty::lock(self::REFLUSH_LOCK, $uid)) {
            return self::fail($return, -7, 'fast_error');
        }
        if ($beautyId <= 0) {
            Beauty::unlock(self::REFLUSH_LOCK, $uid);
            return self::fail($return, -2, 'beauty_error5');
        }
        if (Beauty::template($beautyId) === []) {
            Beauty::unlock(self::REFLUSH_LOCK, $uid);
            return self::fail($return, -3, 'beauty_error2');
        }
        if ($chipId <= 0) {
            Beauty::unlock(self::REFLUSH_LOCK, $uid);
            return self::fail($return, -2, 'beauty_error5');
        }
        $userInfo = Beauty::userInfo($uid, $now);
        if (($userInfo['reflush_dateline'] ?? 0) > 0 && $userInfo['reflush_dateline'] - $now > 0) {
            Beauty::unlock(self::REFLUSH_LOCK, $uid);
            return self::fail($return, -4, 'beauty_flush_time_isnot');
        }
        // 更新刷新时间
        Beauty::updateUserInfo($uid, ['reflush_dateline' => $now + self::FLUSH_TIME]);
        $list = self::getLootList($member, $beautyId, $chipId, $now);
        if ($list === []) {
            // 旧版此处不解锁（30 秒后过期）
            return self::fail($return, -3, 'beauty_error18');
        }
        Beauty::updateLootList($uid, $beautyId, $chipId, $list, $now);
        Beauty::unlock(self::REFLUSH_LOCK, $uid);
        $return['status'] = 1;
        $return['loot_list'] = $list;
        $return['cd'] = self::FLUSH_TIME;
        return $return;
    }

    /**
     * 抢夺对手列表（旧版 get_loot_list）：持有该碎片的前 500 名其他玩家，剔除碎片已为 0、免战中、60 级以下的，
     * 按等级排序后分成三段，每段随机取一人。顺带替到时间的对手完成升级（is_play 2）。
     *
     * 旧版完成升级时循环变量是引用，会把该对手在列表里的那一行换成其最后一个碎片的行（chip_id 随之变掉），照旧。
     *
     * @param array<string, mixed> $member
     * @return list<array<string, mixed>> 取不到为空数组（旧版 -3 获取抢夺列表失败）
     */
    private static function getLootList(array $member, int $beautyId, int $chipId, int $now): array
    {
        $list = Beauty::lootCandidates($beautyId, $chipId, 500, (int)$member['uid']);
        foreach ($list as $key => $row) {
            $touid = $row['uid'];
            $info2 = Beauty::own($touid, $beautyId);
            if ($info2 !== [] && $info2['up_dateline'] > 0 && $info2['up_dateline'] - $now <= 0) {
                $chipInfo = Beauty::chips($touid, $beautyId);
                if (self::finishLevelUp($touid, $beautyId, $chipInfo, 2, $info2['up_dateline'], $now) && $chipInfo !== []) {
                    $list[$key] = $chipInfo[count($chipInfo) - 1];
                }
            }
            // 判断当前碎片是否足够，不够的话删除当前用户
            $chip = Beauty::chip($touid, $beautyId, $chipId);
            if ($chip !== [] && $chip['chip'] <= 0) {
                unset($list[$key]);
            }
        }
        $levels = [];
        foreach ($list as $key => $row) {
            $level = DB::table('member')->where('uid', $row['uid'])->whereNull('deleted_at')->value('level');
            // 剔除正在免战中的用户与 60 级以下的用户
            if (Beauty::inPeace($row['uid'], $now) || $level === null || (int)$level < self::OPEN_LEVEL) {
                unset($list[$key]);
                continue;
            }
            $levels[$key] = (int)$level;
        }
        if ($list === []) {
            return [];
        }
        $newList = [];
        foreach ($list as $key => $row) {
            $row['level'] = $levels[$key];
            $newList[] = $row;
        }
        // 根据 level 排序
        usort($newList, static fn (array $a, array $b): int => $a['level'] <=> $b['level']);
        // 分成三段
        $totalCount = count($newList);
        $total1 = $totalCount / 3;
        $total2 = $totalCount / 3 * 2;
        $slices = [
            array_slice($newList, 0, (int)$total1),
            array_slice($newList, (int)$total1, (int)($total2 - $total1)),
            array_slice($newList, (int)$total2),
        ];
        $newArray = [];
        foreach ($slices as $slice) {
            if ($slice !== []) {
                $newArray[] = $slice[array_rand($slice)];
            }
        }
        $returnArray = [];
        foreach ($newArray as $key => $val) {
            // 返回用户的等级 名称 国家 概率值
            $info = DB::table('member')->select('level', 'nickname', 'country', 'gender')->where('uid', $val['uid'])->whereNull('deleted_at')->first() ?? [];
            $country = (int)($info['country'] ?? 0);
            $returnArray[$key]['country'] = $country > 0 ? (CountryHandler::countryInfo($country)['name'] ?? null) : '';
            $returnArray[$key]['rate_info'] = self::rateItem((int)$member['level'], (int)($info['level'] ?? 0))['info'];
            $returnArray[$key]['level'] = (int)($info['level'] ?? 0);
            $returnArray[$key]['gender'] = (int)($info['gender'] ?? 0);
            $returnArray[$key]['nickname'] = (string)($info['nickname'] ?? '');
            $returnArray[$key]['chip_id'] = $val['chip_id'];
            $returnArray[$key]['beauty_id'] = $val['beauty_id'];
            $returnArray[$key]['uid'] = $val['uid'];
        }
        return $returnArray;
    }

    /**
     * 抢夺成功概率（rate_item：被挑战方比挑战方高 21 级以上 90%、11 ~ 20 级 70%、0 ~ 10 级 60%、低 1 ~ 15 级 40%、
     * 低 16 ~ 30 级 25%，其余 15%；info 为客户端显示的档位 6 ~ 1）
     *
     * @return array{rate: int, info: int}
     */
    private static function rateItem(int $level1, int $level2): array
    {
        $res = $level2 - $level1;
        if ($res >= 21) {
            return ['rate' => 90, 'info' => 6];
        } elseif ($res >= 11 && $res <= 20) {
            return ['rate' => 70, 'info' => 5];
        } elseif ($res >= 0 && $res <= 10) {
            return ['rate' => 60, 'info' => 4];
        } elseif ($res >= -15 && $res <= -1) {
            return ['rate' => 40, 'info' => 3];
        } elseif ($res >= -30 && $res <= -16) {
            return ['rate' => 25, 'info' => 2];
        }
        return ['rate' => 15, 'info' => 1];
    }

    /**
     * 奖品随机（rand_arr：按 rate × 10 划区间，每次 mt_rand(1, 总权重 × 10)，落在 (下界, 上界] 内的取中；
     * 至多试 count 次，取够 $num 个为止。区间下界是开的，落在下界上的那次不中）
     *
     * @param list<array<string, mixed>> $arr
     * @return list<array<string, mixed>>
     */
    private static function randArr(array $arr, int|float $totalNum, int $num): array
    {
        $return = [];
        // 重置数组顺序（旧版 sort 二维数组：各行字段相同，按 id 排序）
        usort($arr, static fn (array $a, array $b): int => $a['id'] <=> $b['id']);
        $k = 0;
        foreach ($arr as &$value) {
            $value['rate_in'] = [$k + 1, (int)round($value['rate'] * 10) + $k];
            $k += (int)round($value['rate'] * 10);
        }
        unset($value);
        $max = (int)round($totalNum * 10);
        if ($max < 1) {
            return [];
        }
        $count = count($arr);
        for ($i = 1; $i <= $count; $i++) {
            $randNum = mt_rand(1, $max);
            foreach ($arr as $val) {
                if ($randNum > $val['rate_in'][0] && $randNum <= $val['rate_in'][1]) {
                    if (count($return) >= $num) {
                        break;
                    }
                    unset($val['rate_in']);
                    $return[] = $val;
                }
            }
        }
        return $return;
    }

    /**
     * 发放掠夺宝箱奖励（add_loot_reward；银币为 主公等级 × value）
     *
     * @param array<string, mixed> $member
     * @param array<string, mixed>|null $rewardInfo
     * @return array{info: array<string, mixed>}
     */
    private static function addLootReward(array $member, ?array $rewardInfo, int $now): array
    {
        $uid = (int)$member['uid'];
        $type = $rewardInfo['type'] ?? null;
        $id = $rewardInfo['value1'] ?? null;
        $num = $rewardInfo['value'] ?? null;
        if ($type === 2) {
            $num = (int)$member['level'] * $rewardInfo['value'];
        }
        $return = ['info' => ['type' => $type, 'value' => $num, 'value1' => $id, 'name' => self::LOOT_NAME[$type] ?? null]];
        if ($type === null) {
            return $return;
        }
        $num = (int)$num;
        $id = (int)$id;
        $method = 'loot_action';
        switch ($type) {
            case 1:
                Member::addCurrency($uid, gold: $num);
                break;
            case 2:
                Member::addCurrency($uid, silver: $num);
                break;
            case 3:
                TraderLog::addJade($uid, 2, $num, class: self::LOG_CLASS, method: $method, now: $now);
                break;
            case 4:
                TraderLog::addJade($uid, 3, $num, class: self::LOG_CLASS, method: $method, now: $now);
                break;
            case 5:
                OwnSoul::addSoul($uid, $num, 0, self::LOG_CLASS, $method, $now);
                break;
            case 6:
                MemberJade::add($uid, $num, self::LOG_CLASS, $method, $now);
                break;
            case 7:
                Member::addCurrency($uid, reputation: $num);
                break;
            case 8:
                // 装备（紫装以上发小喇叭）
                $item = Equip::ROWS[$id] ?? null;
                if ($item !== null) {
                    Equipment::addFromTemplate($uid, $id);
                    if ((int)$item['quality'] >= 5) {
                        $name = Chat::quality((int)$item['quality'], (string)$item['name']);
                        Chat::sendToHorn(sprintf(self::LANG['chat_beauty_get_quality'], $member['nickname'], $name), Chat::sender($uid));
                    }
                    $return['info']['info'] = [
                        'id' => (int)$item['id'],
                        'image' => (int)$item['id'],
                        'name' => $item['name'],
                        'quality' => (int)$item['quality'],
                        'type' => 8,
                        'value' => 1,
                    ];
                }
                break;
            case 9:
                OwnGem::addChip($uid, $num, self::LOG_CLASS, $method, $now);
                break;
            case 10:
                PassGeneral::addFreeleap($uid, $num);
                break;
            case 15:
                MemberStone::add($uid, $num, MemberStone::REFINE, self::LOG_CLASS, $method, $now);
                break;
            case 16:
                MemberStone::add($uid, $num, MemberStone::COLOR, self::LOG_CLASS, $method, $now);
                break;
            case 17:
                MemberStone::add($uid, $num, MemberStone::BLOOD, self::LOG_CLASS, $method, $now);
                break;
            case 30:
                // 美人碎片：value 为美人 id、value1 为碎片 id
                Beauty::updateChipNum($uid, $num, $id, 1, $now);
                $return['info']['info'] = [
                    'beauty_id' => $num,
                    'name' => Beauty::chipInfo($num, $id)['chip_name'] ?? null,
                    'chip_id' => $id,
                ];
                break;
            case 31:
                MemberIntegral::change($uid, $num, 'beauty_rose', 1, self::LOG_CLASS, $method, $now);
                break;
            case 32:
                MemberIntegral::change($uid, $num, 'beauty_diamond', 1, self::LOG_CLASS, $method, $now);
                break;
        }
        return $return;
    }

    /**
     * 是否已拥有某模板的武将（宝箱剔除武将奖励：general_model->get_own_by_id($generalid, $uid, 1)）
     */
    private static function ownsGeneral(int $uid, int $generalid): bool
    {
        return DB::table('general')->where('uid', $uid)->where('generalid', $generalid)->whereNull('deleted_at')->exists();
    }

    /**
     * 抢夺记录（自己被抢的那条 battle_status 改为 2，对手显示抢夺者）
     *
     * @return list<array<string, mixed>>
     */
    private static function lootLogArray(int $uid): array
    {
        $lootArray = [];
        foreach (Beauty::lootLog($uid) as $key => $val) {
            $lootArray[$key] = [
                'battle_status' => $val['battle_status'],
                'to_name' => $val['to_name'],
                'to_uid' => $val['to_uid'],
            ];
            if ($val['to_uid'] === $uid) {
                $lootArray[$key]['battle_status'] = 2;
                $lootArray[$key]['to_name'] = $val['nickname'];
                $lootArray[$key]['to_uid'] = $val['uid'];
            }
            $lootArray[$key]['beauty_id'] = $val['beauty_id'];
            $lootArray[$key]['chip_name'] = Beauty::chipInfo($val['beauty_id'], $val['chip_id'])['chip_name'] ?? null;
            $lootArray[$key]['chip_id'] = $val['chip_id'];
            $lootArray[$key]['report_id'] = $val['report_id'];
            $lootArray[$key]['dateline'] = date('Y-m-d H:i:s', $val['dateline']);
        }
        return $lootArray;
    }

    // ---------------------------------------------------------------------------------------------
    // 内部：互动奖励
    // ---------------------------------------------------------------------------------------------

    /**
     * 互动奖励（get_active_rate：三个奖池按 getrate 权重各抽一份）
     *
     * @return list<array<string, mixed>>
     */
    private static function getActiveRate(int $type): array
    {
        $pools = [1 => [], 2 => [], 3 => []];
        $rates = [1 => 0, 2 => 0, 3 => 0];
        foreach (Beauty::activeInfo($type) as $val) {
            if (isset($pools[$val['pool']])) {
                // 防止小数点出现
                $rates[$val['pool']] += (int)round($val['getrate'] * 1000);
                $pools[$val['pool']][] = $val;
            }
        }
        $return = [];
        foreach ([1, 2, 3] as $pool) {
            if ($rates[$pool] <= 0) {
                continue;
            }
            $rand = mt_rand(1, $rates[$pool]);
            $cursor = 0;
            foreach ($pools[$pool] as $val) {
                $cursor += (int)round($val['getrate'] * 1000);
                if ($rand <= $cursor) {
                    $return[] = $val;
                    break;
                }
            }
        }
        return $return;
    }

    /**
     * 宠幸 / 碎片转化：碎片奖励固定为当前美人的碎片
     *
     * @param list<array<string, mixed>> $result
     * @return list<array<string, mixed>>
     */
    private static function fixPamperChip(array $result, int $bid): array
    {
        foreach ($result as &$val) {
            if ($val['type'] === 30) {
                $val['num'] = $bid;
                $val['name'] = Beauty::chipInfo($bid, $val['value'])['chip_name'] ?? null;
            }
        }
        unset($val);
        return $result;
    }

    /**
     * 奖励展示：碎片显示获得后的数量；$silverByLevel 时银币显示为 主公等级 × num
     *
     * @param list<array<string, mixed>> $result
     * @return list<array<string, mixed>>
     */
    private static function rewardList(array $result, int $level, int $uid, bool $silverByLevel): array
    {
        $reward = [];
        foreach ($result as $key => $val) {
            $reward[$key] = ['type' => $val['type'], 'num' => $val['num']];
            if ($val['type'] === 30) {
                $reward[$key]['name'] = $val['name'];
                $reward[$key]['beauty_id'] = $val['num'];
                $reward[$key]['chip_id'] = $val['value'];
                $log = Beauty::chip($uid, $val['num'], $val['value']);
                $reward[$key]['num'] = $log !== [] ? $log['chip'] + 1 : 1;
            }
            if ($silverByLevel && $val['type'] === 2) {
                $reward[$key]['num'] = $level * $val['num'];
            }
        }
        return $reward;
    }

    /**
     * 发放互动奖励（add_reward：2 银币（主公等级 × num）3 黄宝石 4 紫宝石 5 原魂 6 玉石 30 美人碎片 31 玫瑰花 32 钻石）
     *
     * @param array<string, mixed> $member
     * @param list<array<string, mixed>> $reward
     */
    private static function addReward(array $member, array $reward, int $now, string $method): void
    {
        $uid = (int)$member['uid'];
        foreach ($reward as $val) {
            $num = (int)$val['num'];
            switch ($val['type']) {
                case 2:
                    Member::addCurrency($uid, silver: (int)$member['level'] * $num);
                    break;
                case 3:
                    TraderLog::addJade($uid, 2, $num, class: self::LOG_CLASS, method: $method, now: $now);
                    break;
                case 4:
                    TraderLog::addJade($uid, 3, $num, class: self::LOG_CLASS, method: $method, now: $now);
                    break;
                case 5:
                    OwnSoul::addSoul($uid, $num, 0, self::LOG_CLASS, $method, $now);
                    break;
                case 6:
                    MemberJade::add($uid, $num, self::LOG_CLASS, $method, $now);
                    break;
                case 30:
                    // num 为美人 id、value 为碎片 id
                    Beauty::updateChipNum($uid, $num, (int)$val['value'], 1, $now);
                    break;
                case 31:
                    MemberIntegral::change($uid, $num, 'beauty_rose', 1, self::LOG_CLASS, $method, $now);
                    break;
                case 32:
                    MemberIntegral::change($uid, $num, 'beauty_diamond', 1, self::LOG_CLASS, $method, $now);
                    break;
            }
        }
    }

    // ---------------------------------------------------------------------------------------------
    // 内部：通用
    // ---------------------------------------------------------------------------------------------

    /**
     * 美人私语（旧版 get_say_info($bid, $btype)：随机一句该类型的对话）
     *
     * 类型：1 主页 2 激活 3 休息 4 宠幸 5 问候 6 亲近 7 送花 8 赠礼
     *
     * @return array<string, mixed>
     */
    private static function sayInfo(int $beautyId, int $type): array
    {
        $return = ['status' => 0];
        if ($beautyId <= 0) {
            return self::fail($return, -1, 'beauty_error5');
        }
        $sayList = Beauty::dialogue($beautyId, $type);
        if ($sayList === []) {
            return self::fail($return, -2, 'beauty_say_error');
        }
        $return['say'] = $sayList[mt_rand(0, count($sayList) - 1)]['content'];
        $return['status'] = 1;
        return $return;
    }

    /**
     * 美人碎片（有记录时带 own；没有时取碎片模板，chip 与 own 为 0）
     *
     * @return list<array<string, mixed>>
     */
    private static function chipList(int $uid, int $beautyId): array
    {
        $chips = Beauty::chips($uid, $beautyId);
        if ($chips !== []) {
            foreach ($chips as &$val) {
                $val['own'] = $val['chip'] > 0 ? 1 : 0;
            }
            unset($val);
            return $chips;
        }
        $chips = Beauty::chipInfo($beautyId);
        foreach ($chips as &$val) {
            $val['own'] = 0;
            $val['chip'] = 0;
        }
        unset($val);
        return $chips;
    }

    /**
     * 好感度信息（互动 / 赠礼 / 转化后：按库里的好感度取标识，不做衰减）
     *
     * @return array<string, mixed>
     */
    private static function happyInfo(int $uid, int $bid): array
    {
        $own = Beauty::own($uid, $bid);
        if ($own === []) {
            return ['beauty_id' => $bid, 'ratio' => 0, 'skill' => 0, 'quality' => 0];
        }
        $ratio = Beauty::happyRatio($own['ratio']);
        return ['quality' => $ratio['quality'] ?? null, 'skill' => $ratio['skill'] ?? null, 'ratio' => $own['ratio']];
    }

    /**
     * 激活位置上限：90 级 2 个，130 级 3 个，其余 1 个
     *
     * @param array<string, mixed> $member
     */
    private static function maxNum(array $member): int
    {
        $level = (int)$member['level'];
        if ($level >= 90 && $level < 130) {
            return 2;
        }
        return $level >= 130 ? 3 : 1;
    }

    /**
     * 双倍奖励活动（_initdouble：activity_model->activity_status('double_beauty')）
     */
    private static function isDouble(int $now): bool
    {
        foreach (ActivityTime::ROWS as $row) {
            if ($row['name'] === 'double_beauty') {
                return $row['starttime'] <= $now && $row['endtime'] >= $now;
            }
        }
        return false;
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

    /**
     * @param array<string, mixed> $return
     * @return array<string, mixed>
     */
    private static function fail(array $return, int $status, string $msg): array
    {
        $return['status'] = $status;
        $return['msg'] = self::LANG[$msg] ?? $msg;
        return $return;
    }
}
