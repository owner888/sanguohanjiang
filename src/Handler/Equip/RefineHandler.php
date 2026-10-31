<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Equip;

use Hanjiang\Battle\LegacyStats;
use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\General\GeneralHandler;
use Hanjiang\MasterData\ActivityTime;
use Hanjiang\MasterData\Equip;
use Hanjiang\MasterData\Skill;
use Hanjiang\MasterData\Talent;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\Member;
use Hanjiang\Model\MemberJade;
use Hanjiang\Model\MemberStone;
use Hanjiang\Model\Refine;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 装备精炼（移植旧版 controllers/refine.php + models/refine_model.php，路径相对旧版 s1/application/）
 *
 * 主公 160 级开放（精炼成就界面 achieve_index / get_achieve_info 旧版不判断等级）。装备精炼等级为 equipment.hammer（0 ~ 99），
 * 每级消耗玉石（MemberJade）与精炼石 / 五彩石 / 上古血玉（MemberStone），成功率取当前精炼大师（RefineMaster），
 * add_type 1 的大师成功时有几率连升 2 级（逢 8、9 级且 69 级以下的晋升前不连升）。大师每天 04:00 / 12:00 / 20:00 自动刷新，
 * 也可花 100 元宝刷新（不会刷到当前大师）。双倍活动 ActivityTime double_refine 期间大师固定为 12 欧冶子（100% 成功；配置时间为 0，不会开启）。
 * 精炼继承把一件装备的精炼等级整体移到精炼等级更低的装备上。存储见 Model\Refine。
 * 精炼加成（refine_promote.percent × 强化值）与精炼成就（将领 4 件装备都精炼时按最低等级加武力 / 智力）计入
 * 将领界面（GeneralHandler / Refine::generalRefineAdd）与组队（TeamBuilder::additionRefine）。
 *
 * 客户端调用（equiprefinepop.js / refineinheritpop.js / refineachievepop.js）：index、item_index、item_selected、master_refresh、
 * refinning、inherit、preview、achieve_index、get_achieve_info，全部移植。
 * 并发：精炼 / 继承 / 刷新大师各有防刷锁（Refine::lock，同旧版 30 秒），资源扣除为原子条件扣除。
 * 与旧版的差别：旧版按装备 id 查装备时不限本人（equipment_model->get_info），可以精炼 / 继承别人的装备；新版只认本人的装备，
 * 别人的装备按「装备不存在」返回。
 *
 * 依赖未移植：元宝流水 gold_log、日常任务 5（元宝消费）与翻牌活动计数（member_model->sub_gold）。
 */
final class RefineHandler
{
    /** 刷新大师需要元宝 */
    private const int PRICE = 100;

    /** 开启此功能的等级数 */
    private const int OPEN_LEVEL = 160;

    /** 装备精炼最高等级 */
    private const int REFINE_MAX_LEVEL = 99;

    /** 双倍奖励活动期间的大师（欧冶子，成功率 100%） */
    private const int DOUBLE_MASTER = 12;

    /** 语言包（language/chinese_s/heaven_lang.php 的 refine_error*） */
    private const array LANG = [
        'refine_error1' => '抱歉，您的等级不足！',
        'refine_error2' => '获取大师信息失败',
        'refine_error3' => '获取装备列表失败',
        'refine_error4' => '传递参数错误',
        'refine_error5' => '获取装备信息失败',
        'refine_error6' => '元宝不足',
        'refine_error9' => '装备信息不存在',
        'refine_error11' => '获取装备精炼等级信息失败',
        'refine_error12' => '玉石不足',
        'refine_error13' => '精炼石不足',
        'refine_error14' => '五彩石不足',
        'refine_error15' => '上古血玉不足',
        'refine_error17' => '精炼石减少失败',
        'refine_error18' => '五彩石减少失败',
        'refine_error19' => '上古血玉减少失败',
        'refine_error20' => '继承装备精炼等级须大于被继承装备',
        'refine_error21' => '装备未精炼，无法继承',
        'refine_error22' => '获取出征列表失败',
        'refine_error23' => '精炼成就信息错误',
        'refine_error24' => '获取出征将领列表失败',
        'refine_error25' => '获取将领信息失败',
        'refine_error26' => '请稍后再试',
    ];

    /**
     * 初始化页面: c=refine&m=index
     *
     * 过了 04:00 / 12:00 / 20:00 且大师在此之前刷新的，先按时段免费刷新大师（master_refresh(1)）。
     */
    public static function index(Context $ctx): array
    {
        /**
         * -2 获取大师信息失败
         * -1 您的等级不足
         * 0 请求失败
         * 1 请求成功
         */
        $return = ['status' => 0, 'cd' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        // 限制开放等级
        if (!self::isOpen($member)) {
            return self::fail($return, -1, 'refine_error1');
        }
        $uid = (int)$member['uid'];
        $now = BossClock::now();
        $refresh = self::refreshTimes($now);

        // 查询本玩家的大师信息；过了刷新时间点且大师在此之前刷新的，按时段刷新
        $id = Refine::masterLog($uid, $now);
        foreach ($refresh as $time) {
            if ($id['dateline'] < $time && $now > $time) {
                self::masterRefresh($uid, 1, 0, $now);
                $id = Refine::masterLog($uid, $now);
            }
        }

        // 双倍奖励活动期间，精炼100%成功
        if (self::isDouble($now)) {
            $id['masterid'] = self::DOUBLE_MASTER;
        }

        // 查询本玩家大师的信息
        $result = Refine::masterInfo($id['masterid']);
        if ($result === null) {
            return self::fail($return, -2, 'refine_error2');
        }

        // 计算大师倒计时
        $return['cd'] = self::masterCd($now, $refresh);
        $return['master'] = $result;
        $return['master']['current'] = self::masterRate($result);

        $return['status'] = 1;
        $return['resource']['jade'] = (int)$member['jade'];
        $return['resource']['refinestone'] = (int)$member['refinestone'];
        $return['resource']['colorstone'] = (int)$member['colorstone'];
        $return['resource']['bloodstone'] = (int)$member['bloodstone'];
        $return['price'] = self::PRICE;
        return $return;
    }

    /**
     * 选择装备: c=refine&m=item_index
     *
     * 本人的装备（不含碎片，类型 1 ~ 5），按穿戴将领、类型、品质、精炼等级、强化等级、装配等级排序。
     */
    public static function item_index(Context $ctx): array
    {
        /**
         * -2 获取装备列表失败
         * -1 您的等级不足
         * 0 请求失败
         * 1 请求成功
         */
        $return = ['status' => 0, 'list' => [], 'info' => []];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        // 限制开放等级
        if (!self::isOpen($member)) {
            return self::fail($return, -1, 'refine_error1');
        }
        $uid = (int)$member['uid'];

        // 获得拥有装备列表（get_own_list(needlevel 1, equiptype 1 ~ 5)：gid DESC, type ASC, quality DESC, hammer DESC, level DESC, needlevel ASC）
        $rows = DB::table('equipment')
            ->where('uid', $uid)
            ->where('chip', 0)
            ->whereIn('type', [1, 2, 3, 4, 5])
            ->whereNull('deleted_at')
            ->orderBy('generalid', 'DESC')
            ->orderBy('type', 'ASC')
            ->orderBy('quality', 'DESC')
            ->orderBy('hammer', 'DESC')
            ->orderBy('level', 'DESC')
            ->orderBy('needlevel', 'ASC')
            ->orderBy('id', 'ASC')
            ->get();
        if ($rows === []) {
            return self::fail($return, -2, 'refine_error3');
        }

        $list = [];
        $gidarr = [];
        foreach ($rows as $row) {
            $list[] = [
                'id' => (int)$row['id'],
                'gid' => (int)$row['generalid'],
                'type' => (int)$row['type'],
                'level' => (int)$row['level'],
                'equipid' => (int)$row['equipid'],
                'quality' => (int)$row['quality'],
                'hammer' => (int)$row['hammer'],
                'name' => (string)$row['name'],
            ];
            if ((int)$row['generalid'] > 0) {
                $gidarr[] = (int)$row['generalid'];
            }
        }

        // 获得该装备对应的武将名称（旧版校验将领该位置的装备是这件，不是则清掉装备的 gid；新版位置由装备派生，总是一致）
        if ($gidarr !== []) {
            $general = [];
            foreach (GeneralHandler::ownLegacyList($uid, array_values(array_unique($gidarr))) as $row) {
                $general[$row['id']] = $row;
            }
            foreach ($list as $key => $val) {
                if (isset($general[$val['gid']])) {
                    $list[$key]['generalname'] = $general[$val['gid']]['name'];
                }
            }
        }

        // 查找锤子品级
        foreach ($list as $key => $val) {
            $list[$key]['hammer_quality'] = (int)(Refine::promote($val['hammer'])['type'] ?? 0);
        }

        // 返回数据
        $return['status'] = 1;
        $return['list'] = $list;
        return $return;
    }

    /**
     * 获取装备信息: c=refine&m=item_selected（id 装备）
     *
     * 当前攻击 = 初始 + 强化 + 精炼 + 强化成就；同时给出下一级的精炼加成与消耗。
     */
    public static function item_selected(Context $ctx): array
    {
        /**
         * -3 查找的装备不存在
         * -2 请输入正确的装备id
         * -1 您的等级不足
         * 0 请求失败
         * 1 请求成功
         */
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        // 限制开放等级
        if (!self::isOpen($member)) {
            return self::fail($return, -1, 'refine_error1');
        }
        $id = (int)$ctx->input('id', 0);
        if ($id <= 0) {
            return self::fail($return, -2, 'refine_error4');
        }
        $list = self::itemSelected((int)$member['uid'], $id);
        if ($list === null) {
            return self::fail($return, -3, 'refine_error5');
        }
        $return['list'] = $list;
        $return['status'] = 1;
        return $return;
    }

    /**
     * 刷新大师: c=refine&m=master_refresh（zero 1 为倒计时到 0 的免费刷新，0 为花 100 元宝刷新）
     */
    public static function master_refresh(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        if ($uid <= 0) {
            return ['status' => 0, 'refresh' => 1];
        }
        return self::masterRefresh($uid, 0, $ctx->input('zero', 0), BossClock::now());
    }

    /**
     * 精炼: c=refine&m=refinning（id 装备）
     *
     * 失败（status 4）同样消耗材料。成功时 hit 1 为暴击连升 2 级，list 同 item_selected。
     */
    public static function refinning(Context $ctx): array
    {
        /**
         * -14 上古血石减少错误
         * -13 五彩石减少错误
         * -12 精炼石减少错误
         * -11 玉石减少错误
         * -10 大师基础信息错误
         * -9 上古血石不足
         * -8 五彩石石不足
         * -7 精炼石不足
         * -6 玉石不足
         * -5 装备精炼等级信息不存在
         * -4 玩家装备不存在
         * -3 装备不存在
         * -2 传递参数错误
         * -1 您的等级不足
         * 0 请求失败
         * 1 请求成功
         * 3 玩家大师信息错误
         * 4 精炼失败
         * 5 精炼操作不要太频繁
         */
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        $uid = (int)$member['uid'];
        $now = BossClock::now();

        // 防刷接口
        if (!Refine::lock(Refine::REFINE_LOCK, $uid)) {
            return self::fail($return, 5, 'refine_error26');
        }

        // 限制开放等级（旧版这里不解锁，锁到 30 秒后过期）
        if (!self::isOpen($member)) {
            return self::fail($return, -1, 'refine_error1');
        }

        // 参数接收
        $id = (int)$ctx->input('id', 0);
        if (!$id) {
            Refine::unlock(Refine::REFINE_LOCK, $uid);
            return self::fail($return, -2, 'refine_error4');
        }

        // 查看玩家是否拥有这个装备
        $ownEquip = Refine::ownEquip($uid, $id);
        if ($ownEquip === null) {
            Refine::unlock(Refine::REFINE_LOCK, $uid);
            return self::fail($return, -4, 'refine_error9');
        }

        // 查看装备信息是否存在
        if (!isset(Equip::ROWS[$ownEquip['equipid']])) {
            Refine::unlock(Refine::REFINE_LOCK, $uid);
            return self::fail($return, -3, 'refine_error5');
        }

        // 查询精炼信息是否存在（根据装备精炼等级：下一级，满级时按满级）
        $nowLevel = $ownEquip['hammer'];
        $eqRefineLev = $nowLevel === 0 ? 1 : min($nowLevel + 1, self::REFINE_MAX_LEVEL);
        $refineInfo = Refine::promote($eqRefineLev);
        if ($refineInfo === null) {
            Refine::unlock(Refine::REFINE_LOCK, $uid);
            return self::fail($return, -5, 'refine_error11');
        }

        // 判断消耗材料数量是否足够（玉石、精炼石、五彩石、上古血石）
        $checks = [
            ['jade', -6, 'refine_error12'],
            ['refinestone', -7, 'refine_error13'],
            ['colorstone', -8, 'refine_error14'],
            ['bloodstone', -9, 'refine_error15'],
        ];
        foreach ($checks as [$field, $status, $lang]) {
            if ((int)$member[$field] < $refineInfo[$field]) {
                Refine::unlock(Refine::REFINE_LOCK, $uid);
                return self::fail($return, $status, $lang);
            }
        }

        // 查看玩家是否拥有大师信息（没有时按旧版建档，状态 3 不会出现）
        $masterInfo = Refine::masterLog($uid, $now);

        // 双倍奖励活动期间，精炼100%成功
        if (self::isDouble($now)) {
            $masterInfo['masterid'] = self::DOUBLE_MASTER;
        }

        // 判断大师基础信息是否存在
        $masterBaseInfo = Refine::masterInfo($masterInfo['masterid']);
        if ($masterBaseInfo === null) {
            Refine::unlock(Refine::REFINE_LOCK, $uid);
            return self::fail($return, -10, 'refine_error2');
        }

        // 返回数据
        $return['status'] = 1;
        // 是否暴击升级
        $hit = 0;

        // 玉石减少（条件扣除，并发下不足时失败）
        $resource = ['jade' => (int)$member['jade']];
        if ($refineInfo['jade'] > 0) {
            $jade = MemberJade::sub($uid, $refineInfo['jade'], 'refine', 'refinning', $now);
            if ($jade['status'] !== 1) {
                Refine::unlock(Refine::REFINE_LOCK, $uid);
                return self::fail($return, -11, 'refine_error1');
            }
            $resource['jade'] = $jade['jade'];
        }
        $return['resource']['jade'] = $resource['jade'];

        // 精炼石 / 五彩石 / 上古血石减少
        $stones = [
            ['refinestone', MemberStone::REFINE, -12, 'refine_error17'],
            ['colorstone', MemberStone::COLOR, -13, 'refine_error18'],
            ['bloodstone', MemberStone::BLOOD, -14, 'refine_error19'],
        ];
        foreach ($stones as [$field, $type, $status, $lang]) {
            $balance = (int)$member[$field];
            if ($refineInfo[$field] > 0) {
                $stone = MemberStone::sub($uid, $refineInfo[$field], $type, 'refine', 'refinning', $now);
                if ($stone['status'] !== 1) {
                    Refine::unlock(Refine::REFINE_LOCK, $uid);
                    return self::fail($return, $status, $lang);
                }
                $balance = $stone['stone'];
            }
            $return['resource'][$field] = $balance;
        }

        // 精炼等级成功与否操作：获取大师成功率
        $masterSuccRate = self::masterRate($masterBaseInfo);
        $upLevel = 1;

        // 概率计算
        $rands = mt_rand(1, 100);
        if ($rands > $masterSuccRate) {
            // 精炼失败
            $return['status'] = 4;
            $newLevel = $nowLevel;
        } else {
            // 精炼成功；暴击限制（逢 8、9 级且 69 级以下不连升）
            $baoLimit = self::nowMode($nowLevel, 1);
            if ($masterBaseInfo['add_type'] === 1 && $baoLimit !== 1) {
                // 是否是暴击 暴击概率计算
                $baoRand = mt_rand(1, 100);
                if ($baoRand <= $masterBaseInfo['add_value']) {
                    // 暴击连升2级
                    $upLevel = 2;
                    $hit = 1;
                }
            }
            $newLevel = min($nowLevel + $upLevel, self::REFINE_MAX_LEVEL);
            // 更新玩家装备精炼等级
            Refine::setHammer($uid, $id, $newLevel);
        }

        // 增加精炼日志表
        Refine::addRefineLog($uid, $id, $masterBaseInfo['id'], $nowLevel, $newLevel, $now);

        // 解锁
        Refine::unlock(Refine::REFINE_LOCK, $uid);

        // 返回数据
        $return['hit'] = $hit;
        if ($return['status'] === 1) {
            $return['list'] = self::itemSelected($uid, $id);
        }
        return $return;
    }

    /**
     * 精炼继承: c=refine&m=inherit（id1 被继承装备，id2 继承装备）
     *
     * id1 的精炼等级写到 id2 上，id1 清 0；left / right 为两件装备继承后的精炼等级与锤子品级。
     */
    public static function inherit(Context $ctx): array
    {
        /**
         * -5 装备未精炼，不能继承
         * -4 被继承装备的精炼等级不高于继承装备
         * -3 装备信息错误
         * -2 您输入的装备有误
         * -1 您的等级不足
         * 0 失败
         * 1 成功
         * 2 继承操作太频繁
         */
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        $uid = (int)$member['uid'];

        // 防刷接口
        if (!Refine::lock(Refine::INHERIT_LOCK, $uid)) {
            return self::fail($return, 2, 'refine_error26');
        }

        // 限制开放等级
        if (!self::isOpen($member)) {
            Refine::unlock(Refine::INHERIT_LOCK, $uid);
            return self::fail($return, -1, 'refine_error1');
        }

        $id1 = (int)$ctx->input('id1', 0);
        $id2 = (int)$ctx->input('id2', 0);
        if (!$id1 || !$id2) {
            Refine::unlock(Refine::INHERIT_LOCK, $uid);
            return self::fail($return, -2, 'refine_error4');
        }

        // 通过装备id查找精炼等级（旧版只要查到一件就继续，另一件取不到时按精炼 0 级继续，会把 id1 清 0 而 id2 不变；
        // 新版两件都要是本人的装备，否则按装备信息错误返回）
        $result = Refine::ownEquip($uid, [$id1, $id2]);
        if (!isset($result[$id1], $result[$id2])) {
            Refine::unlock(Refine::INHERIT_LOCK, $uid);
            return self::fail($return, -3, 'refine_error5');
        }
        $hammer1 = $result[$id1]['hammer'];
        if ($hammer1 === 0) {
            // 装备未精炼，不能继承
            Refine::unlock(Refine::INHERIT_LOCK, $uid);
            return self::fail($return, -5, 'refine_error21');
        }
        if ($hammer1 <= $result[$id2]['hammer']) {
            Refine::unlock(Refine::INHERIT_LOCK, $uid);
            return self::fail($return, -4, 'refine_error20');
        }

        // 预览效果（旧版这里调用 preview()，其输出被下面的返回覆盖）
        $info = Refine::promote($hammer1);
        // 左边
        $return['left']['hammer'] = 0;
        $return['left']['hammer_quality'] = 0;
        // 右边
        $return['right']['hammer'] = $hammer1;
        $return['right']['hammer_quality'] = (int)($info['type'] ?? 0);

        // 更新继承后的信息
        Refine::setHammer($uid, $id1, 0);
        Refine::setHammer($uid, $id2, $hammer1);

        // 添加继承日志
        Refine::addInheritLog($uid, $id1, $id2, BossClock::now());

        // 解锁
        Refine::unlock(Refine::INHERIT_LOCK, $uid);

        // 成功
        $return['status'] = 1;
        return $return;
    }

    /**
     * 继承预览: c=refine&m=preview（id1 被继承装备，id2 继承装备）
     */
    public static function preview(Context $ctx): array
    {
        $return = [];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return ['status' => 0];
        }
        // 限制开放等级
        if (!self::isOpen($member)) {
            return self::fail($return, -1, 'refine_error1');
        }
        $uid = (int)$member['uid'];
        $id1 = (int)$ctx->input('id1', 0);
        $id2 = (int)$ctx->input('id2', 0);
        if (!$id1 || !$id2) {
            return self::fail($return, -2, 'refine_error1');
        }
        // 通过装备id查找精炼等级（同 inherit：两件都要是本人的装备）
        $result = Refine::ownEquip($uid, [$id1, $id2]);
        if (!isset($result[$id1], $result[$id2])) {
            return self::fail($return, -3, 'refine_error4');
        }
        $equip1 = $result[$id1];
        $equip2 = $result[$id2];
        if ($equip1['hammer'] === 0) {
            // 装备未精炼，不能继承
            return self::fail($return, -5, 'refine_error21');
        }
        if ($equip1['hammer'] <= $equip2['hammer']) {
            return self::fail($return, -4, 'refine_error20');
        }
        $info = Refine::promote($equip1['hammer']);
        // 高继承覆盖低继承
        $hammer2 = $equip1['hammer'];
        // 左边
        $return['left']['refine'] = $equip1['powerup'];
        // 锤子等级 / 锤子品级
        $return['left']['level'] = 0;
        $return['left']['type'] = 0;
        $return['left']['powerups'] = $equip1['powerup'] - $equip1['power'];
        $return['left']['all'] = $equip1['powerup'];
        $return['left']['power'] = $equip1['power'];
        $return['left']['hammer_quality'] = 0;
        // 右边
        $percent = Refine::promote($hammer2);
        $return['right']['refine'] = $equip2['powerup'];
        if ($percent !== null) {
            $return['right']['refine'] = self::number($equip2['powerup'] * ($percent['percent'] / 100));
            $return['right']['type'] = $percent['type'];
        }
        $return['right']['level'] = $hammer2;
        $return['right']['powerups'] = $equip2['powerup'] - $equip2['power'];
        $return['right']['all'] = self::number($equip2['powerup'] + $return['right']['refine']);
        $return['right']['power'] = $equip2['power'];
        $return['right']['hammer_quality'] = (int)($info['type'] ?? 0);

        // 成功
        $return['status'] = 1;
        return $return;
    }

    /**
     * 精炼成就页面: c=refine&m=achieve_index
     *
     * 出征将领列表与第一个将领的精炼成就信息（旧版不判断开放等级）。
     */
    public static function achieve_index(Context $ctx): array
    {
        /**
         * -4 获取将领信息失败
         * -3 获取出征将领列表失败
         * -2 精炼成就信息错误
         * -1 获取出征列表失败
         * 0 请求失败
         * 1 请求成功
         */
        $return = ['status' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        if (self::member($uid) === null) {
            return $return;
        }

        // 获取出征将领列表（旧版传 get_battle_list(2)，被校验改回 1：按转生、等级降序）
        $list = GeneralHandler::battleList($uid);
        if ($list === []) {
            return self::fail($return, -1, 'refine_error22');
        }

        // 获得第一个将领的信息
        $result = self::achieveInfo($uid, $list[1]);
        if ($result['status'] === -1) {
            return self::fail($return, -2, 'refine_error23');
        } elseif ($result['status'] === -2) {
            return self::fail($return, -3, 'refine_error24');
        } elseif ($result['status'] === -3) {
            return self::fail($return, -4, 'refine_error25');
        }

        // 把将领列表中的多余字段筛选出去
        $generallist = [];
        foreach ($list as $val) {
            $row = [];
            foreach (['id', 'level', 'name', 'image', 'quality', 'type'] as $field) {
                $row[$field] = $val[$field];
            }
            $generallist[] = $row;
        }

        // 返回数据
        $return['status'] = 1;
        $return['list'] = $generallist;
        $return['general'] = $result['general'];
        $return['achList'] = $result['achList'];
        return $return;
    }

    /**
     * 单个将领的精炼成就: c=refine&m=get_achieve_info（gid 将领；旧版不判断开放等级）
     */
    public static function get_achieve_info(Context $ctx): array
    {
        /**
         * -3 获取将领信息失败
         * -2 获取出征将领列表失败
         * -1 精炼成就信息错误
         * 0 请求失败
         * 1 请求成功
         */
        $uid = MemberHandler::resolveUid($ctx);
        if (self::member($uid) === null) {
            return ['status' => 0];
        }
        $gid = (int)$ctx->input('gid', 0);
        $general = $gid > 0 ? (GeneralHandler::ownLegacyList($uid, $gid)[0] ?? null) : null;
        return self::achieveInfo($uid, $general, true);
    }

    /**
     * 刷新大师（移植 master_refresh($type)：$type 1 为 index 按时段的免费刷新，0 为客户端请求）
     *
     * @return array<string, mixed>
     */
    private static function masterRefresh(int $uid, int $type, mixed $zero, int $now): array
    {
        /**
         * -3 元宝不足
         * -2 传递参数错误
         * -1 您的等级不足
         * 0 请求失败
         * 1 请求成功
         * 2 大师刷新太频繁
         */
        $return = ['status' => 0, 'refresh' => 1];

        // 防刷接口
        if (!Refine::lock(Refine::REFRESH_LOCK, $uid)) {
            return self::fail($return, 2, 'refine_error26');
        }
        $member = self::member($uid);

        // 限制开放等级
        if ($member === null || !self::isOpen($member)) {
            Refine::unlock(Refine::REFRESH_LOCK, $uid);
            return self::fail($return, -1, 'refine_error1');
        }

        $id = 0;
        if ($type === 0) {
            // 前端标识（1 为倒计时到 0），防止刷接口（只有 1 和 0）
            $id = (int)$zero;
            if (!($id === 1 || $id === 0)) {
                Refine::unlock(Refine::REFRESH_LOCK, $uid);
                return self::fail($return, -2, 'refine_error4');
            }
        }

        $refresh = self::refreshTimes($now);
        $gold = (int)$member['gold'];
        // 不在刷新时间点（精确到秒）的客户端刷新，zero 为 0 时花元宝
        if (!in_array($now, $refresh, true) && $type !== 1 && $id === 0) {
            if ($gold < self::PRICE || !Member::deductCurrency($uid, gold: self::PRICE)) {
                Refine::unlock(Refine::REFRESH_LOCK, $uid);
                return self::fail($return, -3, 'refine_error6');
            }
            $gold -= self::PRICE;
            $return['refresh'] = 0;
        }

        // 查看本玩家所拥有的大师
        $masterid = Refine::masterLog($uid, $now)['masterid'];
        // 获取精炼大师信息
        $list = Refine::masterList();

        if (self::isDouble($now)) {
            // 双倍奖励活动期间，精炼100%成功
            $getinfo = $list[self::DOUBLE_MASTER - 1];
        } else {
            // 不能刷新到当前的大师（旧版按下标 = 大师 id - 1 剔除）
            $rate = 0;
            foreach ($list as $key => $val) {
                $rate += $val['get_rate'] * 100;
                if ($key === $masterid - 1) {
                    unset($list[$key]);
                    $rate -= $val['get_rate'] * 100;
                }
            }
            $getinfo = Refine::pick($list, $rate);
        }

        $return['list'] = $getinfo;
        $return['list']['current'] = self::masterRate($getinfo);
        // 计算大师倒计时
        $return['cd'] = self::masterCd($now, $refresh);

        // 更新刷新时间和大师名称
        Refine::updateMasterLog($uid, $getinfo['id'], $now);

        // 解锁
        Refine::unlock(Refine::REFRESH_LOCK, $uid);

        // 返回数据
        $return['status'] = 1;
        $return['all_gold'] = $gold;
        return $return;
    }

    /**
     * 装备精炼信息（移植 item_selected($oid) 的 list；装备不是本人的或不存在返回 null）
     *
     * @return array<string, mixed>|null
     */
    private static function itemSelected(int $uid, int $id): ?array
    {
        // 根据装备id查找信息
        $info = Refine::ownEquip($uid, $id);
        if ($info === null) {
            return null;
        }
        $hammerInfo = Refine::promote($info['hammer']);
        $list = $info;

        if ($info['hammer'] === 0) {
            $hammer = 0;
            $nextHammer = 1;
        } elseif ($info['hammer'] >= self::REFINE_MAX_LEVEL) {
            $hammer = $nextHammer = self::REFINE_MAX_LEVEL;
        } else {
            $hammer = $info['hammer'];
            $nextHammer = $hammer + 1;
        }
        // 通过当前精炼等级查找百分比
        $percent = Refine::promote($hammer);
        $percent1 = Refine::promote($nextHammer) ?? ['jade' => 0, 'refinestone' => 0, 'colorstone' => 0, 'bloodstone' => 0, 'percent' => 0];

        // 获取装备强化成就信息（最高的强化成就对应本装备类型的加成百分比）
        $strengAdd = 0;
        $strengAch = LegacyStats::topStrengAchieve($info['aid']);
        if ($strengAch !== null) {
            $strengAdd = $strengAch['add_per' . $info['type']] ?? 0;
        }

        // 精炼攻击 = 强化的数值 × 精炼等级对应的百分比
        $list['refine'] = 0;
        $list['cost_next'] = [
            'jade' => $percent1['jade'],
            'refinestone' => $percent1['refinestone'],
            'colorstone' => $percent1['colorstone'],
            'bloodstone' => $percent1['bloodstone'],
        ];
        $list['percent'] = 0;
        $list['percent_next'] = $percent1['percent'];
        $list['refine_next'] = 0;
        $list['strength_ach'] = 0;
        $list['strength_ach_next'] = 0;
        // 强化攻击 = powerup - power
        $powerups = $info['powerup'] - $info['power'];
        $list['powerups'] = $powerups;
        if ($percent !== null) {
            $list['refine'] = (int)round($powerups * ($percent['percent'] / 100));
            // 当前属性值
            $list['percent'] = $percent['percent'];
            $list['refine_next'] = (int)round($powerups * ($percent1['percent'] / 100) - $powerups * ($percent['percent'] / 100));
        } else {
            $list['refine_next'] = (int)round($powerups * ($percent1['percent'] / 100));
        }

        // 强化成就加成值
        if ($strengAdd > 0) {
            $allpower = $list['refine'] + $info['power'] + $powerups;
            $list['strength_ach'] = (int)round($allpower * $strengAdd / 100);
            $allpowers = $powerups * ($percent1['percent'] / 100) + $info['power'] + $powerups;
            $list['strength_ach_next'] = (int)round(($allpowers * $strengAdd - $allpower * $strengAdd) / 100);
        }

        // 当前攻击力 = 初始 + 强化 + 精炼 + 强化成就
        $list['all'] = $info['powerup'] + $list['refine'] + $list['strength_ach'];

        // 0 是普通精炼，1 是晋升
        $list['now_mode'] = self::nowMode($info['hammer']);
        // 是否满级 1 是满级
        $list['ismax'] = $info['hammer'] >= self::REFINE_MAX_LEVEL ? 1 : 0;
        $list['hammer_quality'] = (int)($hammerInfo['type'] ?? 0);
        return $list;
    }

    /**
     * 单个将领的精炼成就（移植 get_achieve_info($info, $returntype)）
     *
     * 将领 4 件装备都精炼过时，按最低精炼等级达到的精炼成就（refine_achieve.level）给出 achid 与累计的 achInfo。
     *
     * @param array<string, mixed>|null $general 旧版 own_general 形状的行；null 为查不到将领
     * @return array<string, mixed>
     */
    private static function achieveInfo(int $uid, ?array $general, bool $byGid = false): array
    {
        $return = ['status' => 0];

        // 查询精炼成就表是否有信息
        $achieveList = Refine::achieveList();
        if ($achieveList === []) {
            return self::fail($return, -1, 'refine_error23');
        }

        // 获取出征将领列表
        if (GeneralHandler::battleList($uid) === []) {
            return self::fail($return, -2, 'refine_error22');
        }

        // 查询将领信息
        if ($general === null) {
            return self::fail($return, -3, $byGid ? 'refine_error25' : 'refine_error24');
        }

        // 获得将领技能 / 天赋名称（取不到时为 null）
        $general['skill'] = Skill::ROWS[$general['skillid']]['name'] ?? null;
        $general['talent'] = Talent::ROWS[$general['talentid']]['name'] ?? null;

        // 查看武将身上已穿戴的装备
        $eidArr = [];
        $elist = [];
        for ($i = 1; $i < 5; $i++) {
            $elist['eid' . $i] = $general['eid' . $i];
            if ($general['eid' . $i] > 0) {
                $eidArr[] = $general['eid' . $i];
            }
        }

        // 根据装备ID查看精炼等级信息
        $ache = [];
        $acheInfo = [];
        $achid = 0;
        if ($eidArr !== []) {
            $ownEquip = Refine::ownEquip($uid, $eidArr);
            if ($ownEquip !== []) {
                foreach ($ownEquip as $value) {
                    $ache[] = $value['hammer'];
                }

                // 精炼等级信息
                $refineArr = [];
                foreach (Refine::promoteList($ache) as $refine) {
                    $refineArr[$refine['level']] = $refine['type'];
                }

                // 装备字段
                $equipArr = [];
                foreach ($ownEquip as $key => $value) {
                    $value['hammer_quality'] = ($refineArr[$value['hammer']] ?? 0) > 0 ? $refineArr[$value['hammer']] : 0;
                    foreach (['id', 'equipid', 'name', 'type', 'quality', 'needlevel', 'power', 'powerup', 'level', 'saleprice', 'hammer', 'hammer_quality'] as $v) {
                        $equipArr[$key][$v] = $value[$v];
                    }
                }
                for ($i = 1; $i < 5; $i++) {
                    if ($general['eid' . $i] !== 0) {
                        // 旧版装备取不到时 repair_equipment 清掉将领该位置；新版位置由装备派生，取不到时按 0
                        $general['eid' . $i] = $equipArr[$elist['eid' . $i]] ?? 0;
                    }
                }
            }
            if (!in_array(0, $ache, true) && count($ache) >= 4) {
                // 排序等级
                sort($ache);
                foreach ($achieveList as $value) {
                    if ($ache[0] >= $value['level']) {
                        $achid = $value['id'];
                        $acheInfo['id'] = $value['id'];
                        $acheInfo['level'] = $value['level'];
                        for ($k = 1; $k <= 4; $k++) {
                            $acheInfo['add_type' . $k] = $value['add_type' . $k];
                            $acheInfo['add_type_val' . $k] = ($acheInfo['add_type_val' . $k] ?? 0) + $value['add_type_val' . $k];
                        }
                    }
                }
            }
        }
        $general['achid'] = $achid;
        $general['achInfo'] = $acheInfo;

        // 将领信息返回字段筛选
        $newgeneral = [];
        foreach (['id', 'uid', 'quality', 'name', 'level', 'image', 'type', 'wuli', 'zhili', 'tili', 'wuliup', 'zhiliup', 'tiliup',
            'star', 'turn', 'goout', 'skill', 'talent', 'eid1', 'eid2', 'eid3', 'eid4', 'mid0', 'mid1', 'mid2', 'mid3', 'mid4',
            'achid', 'achInfo'] as $val) {
            $newgeneral[$val] = $general[$val];
        }

        // 成功返回
        $return['status'] = 1;
        $return['general'] = $newgeneral;
        $return['achList'] = $achieveList;
        return $return;
    }

    /**
     * 大师当前成功率：add_type 2 的大师 = 精炼成功率 + 特殊属性成功率，否则为精炼成功率
     *
     * @param array<string, mixed> $master
     */
    private static function masterRate(array $master): int
    {
        return $master['add_type'] === 2 ? $master['succ_rate'] + $master['add_value'] : $master['succ_rate'];
    }

    /**
     * 当天的三个大师刷新时间点（04:00 / 12:00 / 20:00）
     *
     * @return list<int>
     */
    private static function refreshTimes(int $now): array
    {
        $day = date('Ymd', $now);
        return [
            (int)strtotime($day . ' 04:00:00'),
            (int)strtotime($day . ' 12:00:00'),
            (int)strtotime($day . ' 20:00:00'),
        ];
    }

    /**
     * 大师倒计时：到下一个刷新时间点；20:00 之后按 20:00 + 8 小时（次日 04:00）算
     *
     * @param list<int> $refresh
     */
    private static function masterCd(int $now, array $refresh): int
    {
        if ($now < $refresh[0]) {
            return $refresh[0] - $now;
        } elseif ($now < $refresh[1]) {
            return $refresh[1] - $now;
        } elseif ($now >= $refresh[2]) {
            // 比较特殊一点，需要加上8个小时
            return $refresh[2] + 28800 - $now;
        }
        return $refresh[2] - $now;
    }

    /**
     * 当前装备是否是晋升状态（get_now_mode）：精炼等级个位为 9（$type 1 时 8、9）且低于 69 级为 1
     */
    private static function nowMode(int $modeLevel, int $type = 0): int
    {
        $limit = $type === 1 ? [8, 9] : [9];
        return in_array($modeLevel % 10, $limit, true) && $modeLevel < 69 ? 1 : 0;
    }

    /**
     * 浮点结果按旧版（PHP 5）json_encode 输出：按 precision 14 位有效数字（133.20000000000002 输出 133.2），整数值输出为整数
     */
    private static function number(int|float $value): int|float
    {
        if (is_int($value)) {
            return $value;
        }
        $value = (float)(string)$value;
        return floor($value) === $value ? (int)$value : $value;
    }

    /**
     * 双倍奖励活动（构造函数 _initdouble：activity_model->activity_status('double_refine')）
     */
    private static function isDouble(int $now): bool
    {
        foreach (ActivityTime::ROWS as $row) {
            if ($row['name'] === 'double_refine') {
                return $row['starttime'] <= $now && $row['endtime'] >= $now;
            }
        }
        return false;
    }

    /**
     * @param array<string, mixed> $return
     * @return array<string, mixed>
     */
    private static function fail(array $return, int $status, string $lang): array
    {
        $return['status'] = $status;
        $return['msg'] = self::LANG[$lang];
        return $return;
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
