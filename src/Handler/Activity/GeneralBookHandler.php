<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\MasterData\ActivityTime;
use Hanjiang\MasterData\General as GeneralTemplate;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\GeneralBook;
use Hanjiang\Model\Member;
use Hanjiang\Model\TraderLog;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 名将谱 / 名将成就（移植旧版 controllers/general_book.php + models/general_model.php 的图鉴与成就部分，路径相对旧版 s1/application/）
 *
 * 主公等级大于 80 开放。图鉴（Book）里的将领获得时自带单将加成（general.addtype / addvalue）；集齐成就（BookAchievement）
 * 所需的将领即达成成就（品级 1），可花蓝 / 黄 / 紫宝石（TraderLog）升到 6 品。单将加成与成就加成合计存 own_addition，
 * 计入组队（TeamBuilder）、推演（BattleEngine 暴击率 / 闪避 / 识破）与将领 / 名将进阶界面（主公 81 级起）。存储见 Model\GeneralBook。
 * 加成在打开名将谱（每天第一次）与获得武将（主公 80 级以上）时重算。
 * 周年庆活动 ActivityTime year_happy 期间成就升级花费八折（活动 2016-12-31 已结束）。
 *
 * 客户端调用（heromanualpop.js）：index、get_book_list、get_achievement_list、levelup，全部移植。
 * 旧版 clear_book_count（运维刷新图鉴总数缓存，直接 echo）客户端不调用，不移植。
 * 并发：levelup 在 Member::locked 玩家行锁内执行。
 *
 * 依赖未移植：首次打开记录 edifice_model->add_edifice_log(7)、计划任务 cron::update_addition（导入数据后全服重算，运维用）、
 * 武将折旧 muster sale_general 后的重算（随 sale_general 一起移植）。
 */
final class GeneralBookHandler
{
    /** 开放等级：主公等级大于 80 */
    private const int OPEN_LEVEL = 80;

    /** 百分比加成类型（闪避、暴击、识破、命中、精准、抗暴击） */
    private const array PERCENTUM_TYPE = [9, 10, 11, 13, 14, 15];

    /** 加成里按百分比显示的字段 */
    private const array PERCENTUM_FIELD = ['dodge', 'crit', 'penetrate', 'mingzhong', 'jingzhun', 'kcrit'];

    /** 周年庆活动打八折 */
    private const float HAPPY_FREE = 0.8;

    /**
     * 初始化接口: c=general_book&m=index（perpage 每页个数）
     *
     * 当天第一次打开时重算名将谱加成；返回第一页图鉴与当前加成。
     */
    public static function index(Context $ctx): array
    {
        /**
         * -4 获取成就信息失败
         * -3 获取图鉴列表失败
         * -2 获取将领模板失败
         * -1 等级不足
         *  0 失败
         *  1 成功
         */
        $return = ['status' => 1];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return ['status' => 0];
        }
        $perpage = (int)$ctx->input('perpage', 0);

        if (!self::isOpen($member)) {
            // 等级不足
            $return['status'] = -1;
            return $return;
        }
        $uid = (int)$member['uid'];
        $now = BossClock::now();

        if (!GeneralBook::bookFlag($uid)) {
            // 更新加成信息表
            GeneralBook::refresh($uid);
            GeneralBook::setBookFlag($uid);
        }

        // 图鉴列表
        $book = self::bookList($uid, 1, $perpage);
        if ($book['status'] !== 1) {
            // -2 获取将领模板失败、-1 获取图鉴列表失败
            $return['status'] = $book['status'] === -2 ? -2 : -3;
            return $return;
        }

        // 加成列表（旧版取整行 own_addition 后输出大于 0 的字段，uid 也在其中）
        $return['addition'] = self::additionText(GeneralBook::ownAddition($uid));

        // 依赖未移植：首次打开操作记录 edifice_model->add_edifice_log(7)

        $happy = self::happy($now);
        $return['book'] = $book['list'];
        $return['page'] = $book['page'];
        $return['perpage'] = $book['perpage'];
        $return['pages'] = $book['pages'];
        $return['count'] = $book['count'];
        $return['_happy_open'] = $happy['open'];
        $return['_happy_cd'] = $happy['cd'];
        $return['status'] = 1;
        return $return;
    }

    /**
     * 图鉴列表: c=general_book&m=get_book_list（page 页码，perpage 每页个数）
     */
    public static function get_book_list(Context $ctx): array
    {
        /**
         * -3 获取图鉴列表失败
         * -2 获取将领模板失败
         * -1 等级不足
         *  0 失败
         *  1 成功
         */
        $return = ['status' => 1];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return ['status' => 0];
        }
        $page = (int)$ctx->input('page', 0);
        $perpage = (int)$ctx->input('perpage', 0);

        if (!self::isOpen($member)) {
            // 等级不足
            $return['status'] = -1;
            return $return;
        }

        $book = self::bookList((int)$member['uid'], $page, $perpage);
        if ($book['status'] !== 1) {
            $return['status'] = $book['status'] === -2 ? -2 : -3;
            return $return;
        }

        $return['book'] = $book['list'];
        $return['page'] = $book['page'];
        $return['perpage'] = $book['perpage'];
        $return['pages'] = $book['pages'];
        $return['count'] = $book['count'];
        $return['status'] = 1;
        return $return;
    }

    /**
     * 成就列表: c=general_book&m=get_achievement_list
     *
     * 全部成就（已拥有的按品级、未拥有的按已有将领数排在前），每个成就带当前品级属性、各品级预览、升级花费与所需将领。
     */
    public static function get_achievement_list(Context $ctx): array
    {
        /**
         * -5 获取宝石数量失败
         * -4 获取成就加成失败
         * -3 获取成就列表失败
         * -2 获取将领模板失败
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
        $happyOpen = self::happy(BossClock::now())['open'];

        // 获取剩余宝石（旧版 tavern_model->get_info 总是成功，-5 不会出现）
        $jade = TraderLog::get($uid);

        // 将领模板（星级 >= 1）
        $infoArr = [];
        foreach (GeneralTemplate::ROWS as $key => $value) {
            if ($value['star'] >= 1) {
                $infoArr[$key] = ['id' => $value['id'], 'name' => $value['name'], 'image' => $value['image']];
            }
        }
        if ($infoArr === []) {
            $return['status'] = -2;
            return $return;
        }

        // 拥有将领
        $general = GeneralBook::ownByStar($uid);
        // 获取拥有成就列表
        $own = GeneralBook::ownAchievementList($uid);

        // 获取成就列表
        $list = GeneralBook::achievementList();
        if ($list === []) {
            $return['status'] = -3;
            return $return;
        }

        // 获取成就加成列表（百分比类型的值带「%」）
        $addition = array_map(self::percentText(...), GeneralBook::achievementAdditionList());
        if ($addition === []) {
            $return['status'] = -4;
            return $return;
        }
        // 按成就 id 分组（保留在整张表里的下标：升级信息的 now 取下标前一行）
        $additionById = [];
        foreach ($addition as $key => $val) {
            $additionById[$val['id']][$key] = $val;
        }

        $field1 = ['quality', 'addtype1', 'addtype2', 'addtype3', 'addvalue1', 'addvalue2', 'addvalue3'];
        $field2 = ['quality', 'addtype1', 'addtype2', 'addtype3', 'addvalue1', 'addvalue2', 'addvalue3', 'paytype', 'number'];
        $result = [];
        foreach ($list as $value) {
            // 成就状态、成就品级、成就是否满级、成就升级信息、预览信息
            $value['complete'] = 0;
            $value['quality'] = 1;
            $value['ismax'] = 0;
            $value['levelup'] = [];
            $value['preview'] = [];
            // 是否拥有该成就
            if (isset($own[$value['id']])) {
                $value['complete'] = 1;
                $value['quality'] = $own[$value['id']]['quality'];
                $value['ismax'] = $value['quality'] === 6 ? 1 : 0;
            }

            $i = 0;
            // 合并加成信息
            foreach ($additionById[$value['id']] ?? [] as $key => $val) {
                if ($val['quality'] === $value['quality']) {
                    foreach ($field1 as $v) {
                        $value['addition'][$v] = $val[$v];
                    }
                }
                if ($value['complete'] === 1 && $val['quality'] === $value['quality'] + 1 && $value['quality'] < 6) {
                    // 升级信息
                    foreach ($field2 as $v) {
                        $value['levelup']['now'][$v] = $addition[$key - 1][$v];
                        $value['levelup']['next'][$v] = $val[$v];
                        if ($v === 'number' && $happyOpen > 0) {
                            $value['levelup']['now'][$v] = $addition[$key - 1][$v] * self::HAPPY_FREE;
                            $value['levelup']['next'][$v] = $val[$v] * self::HAPPY_FREE;
                        }
                    }
                }
                foreach ($field1 as $v) {
                    $value['preview'][$i][$v] = $val[$v];
                }
                $i++;
                $value['paytype'] = $val['paytype'];
            }

            for ($i = 1; $i < 6; $i++) {
                if ($value['gid' . $i] > 0) {
                    $gid = $value['gid' . $i];
                    $value['gid' . $i] = $infoArr[$gid] ?? [];
                    // 是否拥有该武将
                    $value['gid' . $i]['isown'] = isset($general[$gid]) ? 1 : 0;
                }
            }
            $result[] = $value;
        }

        // 对成就排序（已完成的按品级排最上，未完成的按已拥有的将领数；同值保持原顺序）
        $keys1value = [];
        $keysvalue = [];
        foreach ($result as $k => $v) {
            if ($v['complete'] === 1) {
                $keys1value[$k] = $v['quality'];
            } else {
                $keysvalue[$k] = 0;
                for ($i = 1; $i <= 5; $i++) {
                    if (is_array($v['gid' . $i]) && $v['gid' . $i]['isown'] === 1) {
                        $keysvalue[$k]++;
                    }
                }
            }
        }
        arsort($keys1value);
        arsort($keysvalue);
        $newArray = [];
        foreach (array_keys($keys1value) as $k) {
            $newArray[] = $result[$k];
        }
        foreach (array_keys($keysvalue) as $k) {
            $newArray[] = $result[$k];
        }

        $return['status'] = 1;
        $return['achievement'] = $newArray;
        $return['jade'] = [1 => $jade['get1'] ?? 0, 2 => $jade['get2'] ?? 0, 3 => $jade['get3'] ?? 0];
        return $return;
    }

    /**
     * 成就升级: c=general_book&m=levelup（id 成就）
     *
     * 按当前品级的 paytype / number 扣宝石（1 蓝、2 黄、3 紫），品级 +1，并按全部拥有的成就重算名将谱加成。
     */
    public static function levelup(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $id = (int)$ctx->input('id', 0);
        return Member::locked($uid, static fn (): array => self::doLevelup($uid, $id, BossClock::now()));
    }

    /**
     * levelup 的主体（玩家行锁内）
     *
     * @return array<string, mixed>
     */
    private static function doLevelup(int $uid, int $id, int $now): array
    {
        /**
         * -8 获取成就加成信息失败
         * -7 升级失败
         * -6 扣除宝石失败
         * -5 宝石不足
         * -4 获取升级花费失败
         * -3 获取成就加成失败
         * -2 获取拥有成就失败
         * -1 等级不足
         *  0 失败
         *  1 成功
         *  2 已满级
         */
        $return = ['status' => 0];
        $member = self::member($uid);
        if ($member === null) {
            return $return;
        }
        if (!self::isOpen($member)) {
            // 等级不足
            $return['status'] = -1;
            return $return;
        }

        $own = GeneralBook::ownAchievement($uid, $id);
        if ($own === null) {
            // 获取拥有成就信息失败
            $return['status'] = -2;
            return $return;
        }
        if ($own['quality'] === 6) {
            // 已满级
            $return['status'] = 2;
            return $return;
        }

        $info = array_map(self::percentText(...), GeneralBook::achievementAdditionInfo($id));
        if ($info === []) {
            // 获取成就加成信息失败
            $return['status'] = -3;
            return $return;
        }

        $levelup = [];
        $next = [];
        // 当前等级 / 目标等级
        $qualityBefore = $own['quality'];
        $quality = $own['quality'] + 1;
        $happyOpen = self::happy($now)['open'];

        $paytype = $num = $ismax = 0;
        foreach ($info as $value) {
            if ($value['quality'] === $own['quality']) {
                $paytype = $value['paytype'];
                $num = $value['number'];
            }
            if ($value['quality'] === $quality) {
                // 升级属性
                $levelup = $value;
                if ($happyOpen > 0) {
                    $levelup['number'] = $value['number'] * self::HAPPY_FREE;
                }
            }
            if ($quality === 6) {
                $ismax = 1;
            } elseif ($value['quality'] === $quality + 1) {
                // 取下一级升级信息
                $next = $value;
            }
        }

        if ($paytype === 0 || $num === 0) {
            // 升级花费获取失败
            $return['status'] = -4;
            return $return;
        }

        if ($happyOpen > 0) {
            // 旧版按 number × 0.8 扣宝石（宝石数为整数，按 intval 取整）
            $num = (int)($num * self::HAPPY_FREE);
        }
        // 扣除宝石
        $result = TraderLog::subJade($uid, $paytype, $num, 'general_book', 'levelup', $now);
        if ($result['status'] !== 1) {
            // -1 宝石不足，其他为扣除宝石失败
            $return['status'] = $result['status'] === -1 ? -5 : -6;
            return $return;
        }
        $return['jade'][$paytype] = $result['number'];

        // 更新拥有成就信息
        if (GeneralBook::updateOwnAchievement($uid, $id, $quality) !== 1) {
            // 升级失败
            $return['status'] = -7;
            return $return;
        }

        // 全部拥有的成就当前品级的属性（不论是否仍达成）
        $ownList = GeneralBook::ownAchievementList($uid);
        $additionInfo = GeneralBook::achievementAdditionInfo(array_keys($ownList));
        if ($additionInfo === []) {
            $return['status'] = -8;
            return $return;
        }
        $achievement = [];
        foreach ($ownList as $value) {
            foreach ($additionInfo as $val) {
                if ($val['id'] === $value['bid'] && $val['quality'] === $value['quality']) {
                    $value += $val;
                }
            }
            $achievement[] = GeneralBook::achieveFields($value);
        }

        // 更新加成信息表
        $addition = GeneralBook::achievementAddition(GeneralBook::ownByStar($uid), $achievement);
        GeneralBook::saveAddition($uid, $addition);
        $return['addition'] = self::additionText($addition);

        GeneralBook::addAchievementLog($uid, $id, $qualityBefore, $quality, $paytype, $num, $now);

        $return['status'] = 1;
        $return['ismax'] = $ismax;
        $return['levelup'] = $levelup;
        $return['next'] = $next;
        return $return;
    }

    /**
     * 获取图鉴列表（移植 _get_book_list：每个图鉴带将领模板的 id / name / image 与是否拥有）
     *
     * @return array<string, mixed> status 1 成功，-1 获取图鉴列表失败，-2 获取将领模板失败
     */
    private static function bookList(int $uid, int $page, int $perpage): array
    {
        $return = ['status' => 0];
        $own = GeneralBook::ownByStar($uid);
        $book = GeneralBook::bookList($page, $perpage);
        if ($book['status'] !== 1) {
            $return['status'] = -1;
            return $return;
        }

        $list = [];
        foreach ($book['list'] as $value) {
            $tpl = GeneralTemplate::ROWS[$value['id']] ?? null;
            if ($tpl === null) {
                // 获取将领模板失败
                $return['status'] = -2;
                return $return;
            }
            $value['isown'] = isset($own[$value['id']]) ? 1 : 0;
            $value['image'] = $tpl['image'];
            $value['info'] = ['id' => $tpl['id'], 'name' => $tpl['name'], 'image' => $tpl['image']];
            if (in_array($value['addtype'], self::PERCENTUM_TYPE, true)) {
                // 闪避、暴击、识破
                $value['addvalue'] .= '%';
            }
            $list[] = $value;
        }

        $return['status'] = 1;
        $return['list'] = $list;
        $return['perpage'] = $book['perpage'];
        $return['page'] = $book['page'];
        $return['pages'] = $book['pages'];
        $return['count'] = $book['count'];
        return $return;
    }

    /**
     * 成就属性行的百分比类型、大于 0 的值带「%」
     *
     * @param array<string, mixed> $value
     * @return array<string, mixed>
     */
    private static function percentText(array $value): array
    {
        for ($i = 1; $i < 4; $i++) {
            if ($value['addvalue' . $i] > 0 && in_array($value['addtype' . $i], self::PERCENTUM_TYPE, true)) {
                $value['addvalue' . $i] .= '%';
            }
        }
        return $value;
    }

    /**
     * 加成的输出：大于 0 的字段，闪避 / 暴击 / 识破 / 命中 / 精准 / 抗暴击带「%」
     *
     * @param array<string, int> $addition
     * @return array<string, int|string>
     */
    private static function additionText(array $addition): array
    {
        $return = [];
        foreach ($addition as $key => $value) {
            if ($value > 0) {
                $return[$key] = in_array($key, self::PERCENTUM_FIELD, true) ? $value . '%' : $value;
            }
        }
        return $return;
    }

    /**
     * 周年庆活动（构造函数：activity_model->activity_status('year_happy')；cd 为距结束的秒数）
     *
     * @return array{open: int, cd: int}
     */
    private static function happy(int $now): array
    {
        foreach (ActivityTime::ROWS as $row) {
            if ($row['name'] === 'year_happy' && $row['starttime'] <= $now && $row['endtime'] >= $now) {
                return ['open' => 1, 'cd' => $row['endtime'] - $now];
            }
        }
        return ['open' => 0, 'cd' => 0];
    }

    /**
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
