<?php

declare(strict_types=1);

namespace Hanjiang\Handler\General;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Model\Member;
use Hanjiang\Model\MemberJade;
use Hanjiang\Model\OwnStar;
use Hanjiang\Model\TraderLog;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 主公星魄 / 命格（移植旧版 controllers/star.php + models/star_model.php，路径相对旧版 s1/application/）
 *
 * 旧版 star 是主公（玩家）的命格树，不是逐将升星：三页命格，每页 9 个可升到 5 级的命格（3 列）与 1 个三围命格（1 级）；
 * 升级消耗幽蓝 / 铭黄 / 紫阳星魄，星魄在炼化炉用玉石、酒馆宝石、元宝提炼。
 * 配置：命格 StarGrid，炼化炉 StarRefine。存储见 Model\OwnStar（own_star、star_tree、star_log、own_star_addition）。
 * 命格加成 own_star_addition 计入战斗（TeamBuilder / BattleEngine，主公 80 级起）与将领界面（GeneralHandler）。
 *
 * 客户端调用：index、level_up（lordsoulpop.js），refining_index、refining（soulrefinepop.js）。
 * 开放：主公 80 级起（_open_level）。
 *
 * 防并发同旧版：构造函数里的方法锁 sr_lk_{方法}{uid}（10 秒）——同一方法上一次请求未结束时 index / level_up / refining 返回 -1；
 * 旧版先读再写不是原子的，这里用 SET NX。refining_index 旧版不查锁，这里不加锁。
 * 另外 level_up / refining 的读改写在 Member::locked 玩家行锁内执行（两者的锁键不同，旧版可以并发改星魄余额）。
 *
 * 依赖未移植：元宝流水 gold_log（refining 的元宝消耗）。
 */
final class StarHandler
{
    /** 功能开启等级 */
    private const int OPEN_LEVEL = 80;

    /** 命格树最大等级 */
    private const int TREE_MAX_LEVEL = 5;

    /** 方法锁（旧版 _class_lock / _lock_time） */
    private const string LOCK_PREFIX = 'sr_lk_';
    private const int LOCK_TIME = 10;

    /** 旧版语言包 star_lang */
    private const array LANG = [
        'star_not_open' => '功能未开启！',
        'star_init_error' => '基础数据错误！',
        'tree_info_error' => '获取命格信息失败！',
        'tree_max_level' => '命格已经满级！',
        'operate_error' => '非法操作！',
        'jade_insufficient' => '玉石不足！',
        'blue_jade_insufficient' => '蓝宝石不足！',
        'yellow_jade_insufficient' => '黄宝石不足！',
        'purple_jade_insufficient' => '紫宝石不足！',
        'gold_insufficient' => '元宝不足！',
        'star_refine1' => '普普通通的炉子，可以提炼出幽蓝星魄',
        'star_refine2' => '炉身缠绕了九条龙，可以提炼出铭黄星魄',
        'star_refine3' => '太上老君的炼丹炉，可以提炼出紫阳星魄',
        'star_refine_name1' => '青铜炉',
        'star_refine_name2' => '盘龙炉',
        'star_refine_name3' => '八卦炉',
        'blue_star_insufficient' => '幽蓝星魄不足',
        'yellow_star_refine_name3' => '铭黄星魄不足',
        'purple_star_refine_name3' => '紫阳星魄不足',
        'open_level_tree' => '主公等级无法开启此命格',
        'first_level_tree' => '命格未开启',
        'next_info' => '(下一级+%s)',
    ];

    /**
     * 首页: c=star&m=index
     *
     * list 为 [页 => [页内序号 1~10 => 命格]]：已升级的命格显示下一级的消耗（满级没有消耗），未升级的显示 1 级；
     * addition 为命格累计加成，def_cost 为最后升级的命格，all_star1~3 为三种星魄。首次进入时建 own_star 与 1 号命格。
     */
    public static function index(Context $ctx): array
    {
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        $uid = (int)$member['uid'];
        if (!self::isOpen($member) || !self::lock('index', $uid)) {
            // 功能未开启
            return self::fail($return, -1, 'star_not_open');
        }
        try {
            // 获取命格基础数据
            $gridList = OwnStar::grid();
            $treeList = OwnStar::tree($uid);
            if ($treeList === []) {
                $treeList = OwnStar::initTree($uid, 1, 0);
            }
            if ($treeList === []) {
                // 未知错误
                return self::fail($return, -2, 'star_init_error');
            }
            // 获取用户星魄信息
            $starInfo = OwnStar::get($uid);
            if ($starInfo === []) {
                $starInfo = OwnStar::init($uid, time());
            }
            if ($starInfo === []) {
                // 初始化信息失败
                return self::fail($return, -2, 'star_init_error');
            }

            $grid = [];
            $soure = [];
            foreach ($gridList as $key => $val) {
                $id = (int)$val['id'] % 10;
                if ($id === 0) {
                    $id = 10;
                }
                $page = (int)$val['page'];
                $maxLevel = (int)$val['line'] === 4 ? 1 : self::TREE_MAX_LEVEL;
                $cost = ['type1' => (int)$val['type1'], 'value1' => (int)$val['value1'], 'value2' => (int)$val['value2'], 'value3' => (int)$val['value3']];
                if (isset($treeList[$val['id']]) && $treeList[$val['id']]['level'] > 0) {
                    $level = $treeList[$val['id']]['level'];
                    if ($level + 1 === (int)$val['level'] || $level === $maxLevel) {
                        $desc = (string)$val['desc'];
                        if ($level < $maxLevel) {
                            $descValue = explode('+', $desc);
                            $desc = $gridList[$key - 1]['desc'] . sprintf(self::LANG['next_info'], end($descValue));
                        }
                        $grid[$page][$id] = [
                            'id' => (int)$val['id'],
                            'name' => (string)$val['name'],
                            'desc' => $desc,
                            'level' => $level,
                            'max_level' => $maxLevel,
                            'line' => (int)$val['line'],
                            'prepos_level' => (int)$val['prepos_level'],
                            'open_status' => 1,
                            // 旧版消耗只带 type1 / value1 / value2 / value3（type1 写了两次）；满级没有消耗
                            'cost' => $level === $maxLevel ? [] : $cost,
                        ];
                        // 用户最后升级的星魄id
                        if ((int)$val['id'] === $starInfo['lastlevel']) {
                            $soure = $grid[$page][$id];
                        }
                    }
                } elseif ((int)$val['level'] === 1) {
                    $openStatus = 0;
                    if (in_array((int)$val['id'], [1, 2, 3], true)) {
                        // 默认每列第一个开启
                        $openStatus = 1;
                    } else {
                        for ($ii = 1; $ii < 4; $ii++) {
                            $prepos = (int)$val['prepos' . $ii];
                            if ($prepos > 0 && isset($treeList[$prepos]) && $treeList[$prepos]['level'] === (int)$val['prepos' . $ii . $ii]) {
                                $openStatus = 1;
                                break;
                            }
                        }
                    }
                    if ((int)$val['line'] === 4) {
                        $desc = (string)$val['desc'];
                    } else {
                        $exArr = explode('+', (string)$val['desc']);
                        $desc = $exArr[0] . '+0' . sprintf(self::LANG['next_info'], end($exArr));
                    }
                    $grid[$page][$id] = [
                        'id' => (int)$val['id'],
                        'name' => (string)$val['name'],
                        'desc' => $desc,
                        'level' => 0,
                        'max_level' => $maxLevel,
                        'line' => (int)$val['line'],
                        'prepos_level' => (int)$val['prepos_level'],
                        'open_status' => $openStatus,
                        'cost' => $cost,
                    ];
                    // 用户最后升级的星魄id
                    if ((int)$val['id'] === $starInfo['lastlevel']) {
                        $soure = $grid[$page][$id];
                    }
                }
            }

            $return['status'] = 1;
            $return['all_star1'] = $starInfo['star1'];
            $return['all_star2'] = $starInfo['star2'];
            $return['all_star3'] = $starInfo['star3'];
            // 获取星魄加成信息
            $return['addition'] = OwnStar::addition($uid);
            // 默认选中消耗
            $return['def_cost'] = $soure;
            // 命格列表
            $return['list'] = $grid;
            return $return;
        } finally {
            self::unlock('index', $uid);
        }
    }

    /**
     * 命格升级: c=star&m=level_up（starid 命格id）
     */
    public static function level_up(Context $ctx): array
    {
        /**
         * -9 升级失败
         * -6 星魄不足
         * -5 主公等级无法开启此命格
         * -4 命格已满级 / 前置命格未满足
         * -3 获取命格信息失败
         * -2 基础数据错误
         * -1 功能未开启
         *  0 失败 / 缺少参数
         *  1 成功
         *  3 三围命格已满级
         */
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        $uid = (int)$member['uid'];
        if (!self::isOpen($member) || !self::lock('level_up', $uid)) {
            return self::fail($return, -1, 'star_not_open');
        }
        $starid = (int)$ctx->input('starid', 0);
        try {
            if ($starid <= 0) {
                // 缺少必要参数
                return $return;
            }
            return Member::locked($uid, static fn (): array => self::levelUp($uid, (int)$member['level'], $starid));
        } finally {
            self::unlock('level_up', $uid);
        }
    }

    /**
     * 提炼首页: c=star&m=refining_index
     */
    public static function refining_index(Context $ctx): array
    {
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        $uid = (int)$member['uid'];
        if (!self::isOpen($member)) {
            // 功能未开启
            return self::fail($return, -1, 'star_not_open');
        }
        $refine = OwnStar::refine();
        if ($refine === []) {
            // 基础数据错误
            return self::fail($return, -2, 'star_not_open');
        }

        $refineList = [];
        foreach ($refine as $val) {
            $rid = (int)$val['id'];
            $refineList[$rid]['id'] = $rid;
            // 旧版语言包取不到时为 FALSE（JSON false）
            $refineList[$rid]['desc'] = self::LANG['star_refine' . $rid] ?? false;
            $refineList[$rid]['name'] = self::LANG['star_refine_name' . $rid] ?? false;
            $refineList[$rid]['refine_type' . $val['refine_type']] = [
                'type1' => (int)$val['type1'],
                'value1' => (int)$val['value1'],
                'type2' => (int)$val['type2'],
                'value2' => (int)$val['value2'],
                'type3' => (int)$val['type3'],
                'value3' => (int)$val['value3'],
                'gettype' => (int)$val['gettype'],
                'getvalue' => (int)$val['getvalue'],
            ];
        }
        // 查询玩家酒馆宝石记录（tavern_model->get_info：没有记录时三种宝石为 0）
        $jade = TraderLog::get($uid) ?? ['get1' => 0, 'get2' => 0, 'get3' => 0];
        // 获取星魄信息
        $starInfo = OwnStar::get($uid);
        if ($starInfo === []) {
            // 初始化信息失败
            return self::fail($return, -2, 'star_init_error');
        }
        $return['refine'] = $refineList;
        $return['jade1'] = $jade['get1'];
        $return['jade2'] = $jade['get2'];
        $return['jade3'] = $jade['get3'];
        $return['jade'] = (int)$member['jade'];
        $return['star1'] = $starInfo['star1'];
        $return['star2'] = $starInfo['star2'];
        $return['star3'] = $starInfo['star3'];
        $return['lastsite'] = $starInfo['lastsite'];
        $return['status'] = 1;
        return $return;
    }

    /**
     * 提炼星魄: c=star&m=refining（id 炼化炉 1~3，refine_type 提炼类型 1 普通 / 2 高级 / 3 至尊）
     */
    public static function refining(Context $ctx): array
    {
        /**
         * -8 元宝不足
         * -7 紫宝石不足
         * -6 黄宝石不足
         * -5 蓝宝石不足
         * -4 玉石不足
         * -3 获取规则失败 / 获取星魄信息失败
         * -2 缺少参数
         * -1 功能未开启
         *  0 失败
         *  1 提炼成功
         */
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        $uid = (int)$member['uid'];
        if (!self::isOpen($member) || !self::lock('refining', $uid)) {
            return self::fail($return, -1, 'star_not_open');
        }
        $id = (int)$ctx->input('id', 0);
        $refineType = (int)$ctx->input('refine_type', 0);
        try {
            if ($refineType <= 0 || $id <= 0) {
                // 缺少必要参数
                return self::fail($return, -2, 'operate_error');
            }
            return Member::locked($uid, static fn (): array => self::refine($uid, $id, $refineType));
        } finally {
            self::unlock('refining', $uid);
        }
    }

    /**
     * level_up 的主体（玩家行锁内）
     *
     * @return array<string, mixed>
     */
    private static function levelUp(int $uid, int $memberLevel, int $starid): array
    {
        $return = ['status' => 0];
        $now = time();
        // 获取用户当前命格信息
        $tree = OwnStar::tree($uid, [$starid]);
        if ($tree === []) {
            // 旧版先插入命格行，再在下面取不到命格配置时返回 -3（留下不存在的命格行）；这里配置里没有该命格时直接 -3
            if (OwnStar::grid(0, [$starid], [1]) === []) {
                return self::fail($return, -3, 'tree_info_error');
            }
            $tree = OwnStar::initTree($uid, $starid, 0);
        }
        if ($tree === []) {
            // 基础数据错误
            return self::fail($return, -2, 'star_init_error');
        }
        $tree = reset($tree);
        $starid = $tree['starid'];
        if ($tree['level'] + 1 > self::TREE_MAX_LEVEL) {
            // 命格树满级
            return self::fail($return, -4, 'tree_max_level');
        }
        $levelUp = $tree['level'] + 1;
        $poList = OwnStar::grid(0, [$starid], [$levelUp]);
        if ($poList === []) {
            // 获取用户命格信息错误
            return self::fail($return, -3, 'tree_info_error');
        }
        $poInfo = $poList[0];

        // 判断前置命格（任一前置命格处于任一要求等级即可；没有前置命格时取全部命格，非空）
        $staridArr = $levelArr = [];
        for ($ii = 1; $ii < 4; $ii++) {
            if ((int)$poInfo['prepos' . $ii] > 0) {
                $staridArr[] = (int)$poInfo['prepos' . $ii];
                $levelArr[] = (int)$poInfo['prepos' . $ii . $ii];
            }
        }
        if (OwnStar::tree($uid, $staridArr, $levelArr) === []) {
            // 前置命格没有满级----非法操作
            return self::fail($return, -4, 'first_level_tree');
        }
        // 三围命格只能升级一次
        if ((int)$poInfo['line'] === 4 && $tree['level'] + 1 > 1) {
            return self::fail($return, 3, 'tree_max_level');
        }
        // 判断主公等级是否可以开启此命格
        if ((int)$poInfo['prepos_level'] > $memberLevel) {
            return self::fail($return, -5, 'open_level_tree');
        }

        // 获取用户星魄信息
        $starSoure = OwnStar::get($uid);
        if ($starSoure === []) {
            // 获取用户星魄信息失败
            return self::fail($return, -2, 'star_init_error');
        }
        // 判断星魄足够否
        if ((int)$poInfo['value1'] > $starSoure['star1']) {
            return self::fail($return, -6, 'blue_star_insufficient');
        }
        if ((int)$poInfo['value2'] > $starSoure['star2']) {
            return self::fail($return, -6, 'yellow_star_refine_name3');
        }
        if ((int)$poInfo['value3'] > $starSoure['star3']) {
            return self::fail($return, -6, 'purple_star_refine_name3');
        }
        $subType = $subNum = [];
        for ($ii = 1; $ii < 4; $ii++) {
            if ((int)$poInfo['value' . $ii] > 0) {
                $subType[$ii] = (int)$poInfo['type' . $ii];
                $blance = $starSoure['star' . $ii] - (int)$poInfo['value' . $ii];
                $subNum[$ii] = $blance;
                $starSoure['star' . $ii] = $blance;
            }
        }
        foreach ($subType as $k1 => $v1) {
            OwnStar::log($uid, $v1, 2, (int)$poInfo['value' . $k1], $subNum[$k1], 'star', 'level_up', $now, $poInfo);
        }
        // 扣除资源
        if (OwnStar::update($uid, $subType, $subNum, 0, $starid) !== 1) {
            // 操作失败
            return $return;
        }
        // 升级
        if (OwnStar::updateTree($uid, $starid, $levelUp, self::TREE_MAX_LEVEL) !== 1) {
            $return['status'] = -9;
            return $return;
        }

        if ((int)$poInfo['line'] === 4 && $levelUp === 1) {
            // 三围命格
            $nextList = OwnStar::grid((int)$poInfo['page'], [$starid], [1]);
        } else {
            // 查询下一级消耗
            $nextList = OwnStar::grid(0, [$starid], [min($levelUp + 1, self::TREE_MAX_LEVEL)]);
        }

        $tmpl = [];
        $openList = OwnStar::openList($starid, $levelUp);
        if ($openList !== []) {
            $i = 1;
            if (count($openList) === 1) {
                $tmpl['id2'] = 0;
                $tmpl['id3'] = 0;
            }
            foreach ($openList as $val) {
                if ($val['line'] === 4) {
                    // 三围命格已开启过时不提示
                    if (OwnStar::tree($uid, [$val['id']], [1]) !== []) {
                        $tmpl = [];
                        break;
                    }
                }
                $tmpl['id' . $i] = $val['id'];
                $i++;
            }
        }

        $nextInfo = $nextList[0];
        $maxLevel = (int)$nextInfo['line'] === 4 ? 1 : self::TREE_MAX_LEVEL;
        if ((int)$poInfo['level'] < $maxLevel && (int)$poInfo['line'] !== 4) {
            $addvalue = explode('+', (string)$nextInfo['desc']);
            $desc = $poInfo['desc'] . sprintf(self::LANG['next_info'], end($addvalue));
            $cost = [
                'type1' => (int)$nextInfo['type1'],
                'value1' => (int)$nextInfo['value1'],
                'type2' => (int)$nextInfo['type2'],
                'value2' => (int)$nextInfo['value2'],
                'type3' => (int)$nextInfo['type3'],
                'value3' => (int)$nextInfo['value3'],
            ];
        } else {
            $desc = (string)$nextInfo['desc'];
            $cost = [];
        }
        $return['status'] = 1;
        $return['next_cost'] = [
            'id' => (int)$nextInfo['id'],
            'name' => (string)$nextInfo['name'],
            'desc' => $desc,
            'level' => (int)$nextInfo['level'],
            'max_level' => $maxLevel,
            'line' => (int)$nextInfo['line'],
            'prepos_level' => (int)$nextInfo['prepos_level'],
            'open_status' => 1,
            'cost' => $cost,
        ];
        $return['open_star_list'] = $tmpl;
        $return['star1'] = $starSoure['star1'];
        $return['star2'] = $starSoure['star2'];
        $return['star3'] = $starSoure['star3'];

        // 处理星魄加成（get_star_addition 没有记录时先建）
        OwnStar::addition($uid);
        OwnStar::addAddition($uid, (int)$poList[0]['addtype'], (int)$poList[0]['addvalue']);
        return $return;
    }

    /**
     * refining 的主体（玩家行锁内）
     *
     * @return array<string, mixed>
     */
    private static function refine(int $uid, int $id, int $refineType): array
    {
        $return = ['status' => 0];
        $now = time();
        // 获取提取规则
        $refineInfo = OwnStar::refine($id, $refineType)[0] ?? null;
        if ($refineInfo === null) {
            // 获取规则失败
            return self::fail($return, -3, 'star_init_error');
        }
        $member = self::member($uid) ?? [];
        // 用户蓝黄紫宝石信息
        $jade = TraderLog::get($uid) ?? ['get1' => 0, 'get2' => 0, 'get3' => 0];
        $checks = [
            1 => [(int)$member['jade'], -4, 'jade_insufficient'],
            2 => [$jade['get1'], -5, 'blue_jade_insufficient'],
            3 => [$jade['get2'], -6, 'yellow_jade_insufficient'],
            4 => [$jade['get3'], -7, 'purple_jade_insufficient'],
            5 => [(int)$member['gold'], -8, 'gold_insufficient'],
        ];
        for ($ii = 1; $ii < 4; $ii++) {
            $sType = (int)$refineInfo['type' . $ii];
            $sVal = (int)$refineInfo['value' . $ii];
            if (isset($checks[$sType]) && $checks[$sType][0] < $sVal) {
                return self::fail($return, $checks[$sType][1], $checks[$sType][2]);
            }
        }

        for ($i = 1; $i < 4; $i++) {
            $sType = (int)$refineInfo['type' . $i];
            $sVal = (int)$refineInfo['value' . $i];
            if ($sType === 1) {
                if (MemberJade::sub($uid, $sVal, 'star', 'refining', $now)['status'] !== 1) {
                    // 未知错误
                    return $return;
                }
            } elseif ($sType >= 2 && $sType <= 4) {
                $jade['get' . ($sType - 1)] -= $sVal;
                if (TraderLog::subJade($uid, $sType - 1, $sVal, 'star', 'refining', $now)['status'] !== 1) {
                    return $return;
                }
            } elseif ($sType === 5) {
                // 依赖未移植：元宝流水 gold_log
                if (!Member::deductCurrency($uid, gold: $sVal)) {
                    return $return;
                }
            }
        }

        $starInfo = OwnStar::get($uid);
        if ($starInfo === []) {
            // 未知错误（旧版此时宝石 / 元宝已扣）
            return self::fail($return, -3, 'star_init_error');
        }
        $gettype = (int)$refineInfo['gettype'];
        $getvalue = (int)$refineInfo['getvalue'];
        $blance = $starInfo['star' . $gettype] + $getvalue;
        // 添加log
        OwnStar::log($uid, $gettype, 1, $getvalue, $blance, 'star', 'refining', $now);
        // 添加星魄
        if (OwnStar::update($uid, [$gettype], [$blance], $id) !== 1) {
            // 添加星魄失败
            return $return;
        }
        $member = self::member($uid) ?? [];
        $return['star'] = $blance;
        $return['gettype'] = $gettype;
        $return['getstar'] = $getvalue;
        $return['jade1'] = $jade['get1'];
        $return['jade2'] = $jade['get2'];
        $return['jade3'] = $jade['get3'];
        $return['jade'] = (int)$member['jade'];
        $return['gold'] = (int)$member['gold'];
        // 提炼成功
        $return['status'] = 1;
        return $return;
    }

    /**
     * 方法锁（旧版构造函数：sr_lk_{方法}{uid}，存在时视为上一次请求未结束）
     */
    private static function lock(string $method, int $uid): bool
    {
        $redis = Redis::instance();
        return $redis !== null && (bool)$redis->set(self::LOCK_PREFIX . $method . $uid, (string)$uid, ['nx', 'ex' => self::LOCK_TIME]);
    }

    private static function unlock(string $method, int $uid): void
    {
        Redis::del(self::LOCK_PREFIX . $method . $uid);
    }

    /**
     * 失败返回（带旧版语言包 msg）
     *
     * @param array<string, mixed> $return
     * @return array<string, mixed>
     */
    private static function fail(array $return, int $status, string $msg): array
    {
        $return['status'] = $status;
        $return['msg'] = self::LANG[$msg];
        return $return;
    }

    /**
     * 主公 80 级起开放
     *
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
