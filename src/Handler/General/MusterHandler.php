<?php

declare(strict_types=1);

namespace Hanjiang\Handler\General;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\MasterData\BookAchievement;
use Hanjiang\MasterData\Pub;
use Hanjiang\MasterData\Skill;
use Hanjiang\MasterData\Talent;
use Hanjiang\Model\GeneralBook;
use Hanjiang\Model\Member;
use Hanjiang\Model\OwnMatrix;
use Hanjiang\Model\OwnMount;
use Hanjiang\Model\OwnSoul;
use Hanjiang\Model\Practice;
use Hanjiang\Model\PubLog;
use Hanjiang\Model\TraderLog;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 点将台（移植旧版 controllers/muster.php：index / get_general_info / go_battle / go_rest / sale_general）
 *
 * 出征名单为 general.goout；阵法站位只在 matrix 表（Model\OwnMatrix）。
 * 武将折旧（sale_general）：VIP 7 起、7 天 CD（member_field.general_sale），按名将榜（Pub tab 2）售价 × VIP 系数返还宝石；
 * 折旧的将领禁用（旧版 own_general.disable，新版软删除），酒馆招募记录改为未招募，名将谱加成重算。
 */
final class MusterHandler
{
    /**
     * 出征名单上限（general_model::go_battle：battle_count() >= 8 时出征队列已满）
     */
    public const int MAX_BATTLE = 8;

    /** 武将折旧 CD（7 天） */
    private const int SALE_CD = 86400 * 7;

    /** 武将折旧系数（VIP 7 ~ 10，10 以上按 10） */
    private const array SALE_RULE = [7 => 0.3, 8 => 0.4, 9 => 0.5, 10 => 0.6];

    /** 旧版语言包 olderror_lang.php muster_error* */
    private const array LANG = [
        1 => 'VIP等级不够！',
        2 => 'CD时间未到，无法折旧',
        3 => '还未拥有该武将',
        4 => '已达成名将成就的武将无法折旧',
        5 => '仅名将榜武将可以折旧',
        6 => '获取售价失败',
        7 => '无法终止训练',
        8 => '武将下阵失败',
        9 => '禁用武将失败',
        10 => '更新cd时间失败',
        11 => '增加宝石失败',
        12 => '获取页码失败',
        13 => '战神殿获得武将不能折旧',
        14 => '绝版神将获得武将不能折旧',
        15 => '累计登陆获得武将不能折旧',
        16 => '充值送豪礼获得武将不能折旧',
    ];

    /**
     * index 的 first 字段（旧版 first_field）
     */
    private const array INDEX_FIELD = [
        'id', 'quality', 'image', 'type', 'star', 'name', 'turn',
        'level', 'wuli', 'zhili', 'tili', 'zhiliup', 'tiliup', 'wuliup', 'skill', 'talent', 'goout',
    ];

    /**
     * get_general_info 的 info 字段（旧版 info_field）
     */
    private const array INFO_FIELD = [
        'id', 'image', 'quality', 'type', 'star', 'name', 'turn',
        'level', 'wuli', 'zhili', 'tili', 'zhiliup', 'tiliup', 'wuliup', 'skill', 'talent', 'goout',
    ];

    /**
     * 拥有将领列表: c=muster&m=index（旧版 controllers/muster.php:index）
     *
     * page / perpage（默认 1 / 9）分页，按 goout DESC, star DESC, level DESC 排序（同序按 id）；list 键从 1 开始，first 为本页第一个将领详情。
     * 状态码：-4 天赋获取错误；-3 技能获取错误或本页没有将领；-2 未知错误；2 将领不存在；1 成功。
     * VIP 7 级以上输出 sale_cd。
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $return = ['status' => 0, 'page' => 0, 'list' => [], 'first' => []];
        $getPage = (int)$ctx->input('page', 0);
        $getPerpage = (int)$ctx->input('perpage', 0);
        $page = $getPage > 0 ? $getPage : 1;
        $perpage = $getPerpage > 0 ? $getPerpage : 9;

        // general_model::get_own_list
        $totals = DB::table('general')->where('uid', $uid)->whereNull('deleted_at')->count();
        if ($totals === 0) {
            // 将领不存在
            $return['status'] = 2;
            return $return;
        }
        $pages = (int)ceil($totals / $perpage);
        $listPage = $page > $pages ? $pages : $page;
        $rows = DB::table('general')
            ->where('uid', $uid)
            ->whereNull('deleted_at')
            ->orderBy('goout', 'DESC')
            ->orderBy('star', 'DESC')
            ->orderBy('level', 'DESC')
            ->orderBy('id')
            ->limit($perpage)
            ->offset(($listPage - 1) * $perpage)
            ->get();
        if ($rows === []) {
            $return['status'] = -3;
            return $return;
        }

        $first = $rows[0];
        if ((int)$first['skillid'] === 0) {
            $return['status'] = -3;
            return $return;
        }
        if ((int)$first['talentid'] === 0) {
            $return['status'] = -4;
            return $return;
        }

        $list = [];
        foreach ($rows as $key => $value) {
            $list[$key + 1] = [
                'id' => (int)$value['id'],
                'quality' => (int)$value['quality'],
                'name' => (string)$value['name'],
                'image' => (int)$value['image'],
                'type' => (int)$value['type'],
                'goout' => (int)$value['goout'],
            ];
        }

        // 如果进来的时候有气泡通知，就把他隐藏
        $field = DB::table('member_field')->select('ongeneral', 'general_sale')->where('uid', $uid)->whereNull('deleted_at')->first() ?? [];
        if ((int)($field['ongeneral'] ?? 0) === 1) {
            DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->update(['ongeneral' => 0]);
        }
        $cd = 0;
        if ((int)($field['general_sale'] ?? 0) > 0) {
            $cd = max(0, self::SALE_CD - (time() - (int)$field['general_sale']));
        }
        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first() ?? [];
        if ((int)($member['vip'] ?? 0) >= 7) {
            $return['sale_cd'] = $cd;
        }

        $return['list'] = $list;
        $return['page'] = $page;
        $return['first'] = self::generalFields($first, self::INDEX_FIELD);
        $return['status'] = 1;
        return $return;
    }

    /**
     * 获取单个将领信息: c=muster&m=get_general_info（旧版 controllers/muster.php:get_general_info）
     *
     * 状态码：-4 天赋数据异常；-3 技能数据异常；-1 传参错误或将领不存在；1 成功。
     */
    public static function get_general_info(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $return = ['status' => 0, 'info' => []];
        $gid = max(0, (int)$ctx->input('gid', 0));

        $info = self::ownGeneral($uid, $gid);
        if ($info === null) {
            $return['status'] = -1;
            return $return;
        }
        if ((int)$info['skillid'] === 0) {
            $return['status'] = -3;
            return $return;
        }
        if ((int)$info['talentid'] === 0) {
            $return['status'] = -4;
            return $return;
        }

        $return['info'] = self::generalFields($info, self::INFO_FIELD);
        $return['status'] = 1;
        return $return;
    }

    /**
     * 出征: c=muster&m=go_battle（旧版 controllers/muster.php:go_battle + general_model::go_battle）
     *
     * 出征名单上限 8 人（battle_count >= 8 先于其他检查）。只改出征名单 goout，不改阵法站位（旧版同）。
     * 状态码：-1 将领不存在；1 出征成功；2 已在出征队列；3 出征人数已满。
     */
    public static function go_battle(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $return = ['status' => 0];
        $gid = (int)$ctx->input('gid', 0);

        // check_battle：-1 不存在，1 休息中，2 出征中
        $general = self::ownGeneral($uid, $gid);
        $result = $general === null ? -1 : ((int)$general['goout'] === 1 ? 2 : 1);
        if (self::battleCount($uid) >= self::MAX_BATTLE) {
            // 出征队列已满
            $return['status'] = 3;
            return $return;
        }
        if ($result !== 1) {
            $return['status'] = $result;
            return $return;
        }

        $affected = DB::table('general')->where('uid', $uid)->where('id', $gid)->whereNull('deleted_at')->update(['goout' => 1]);
        $return['status'] = $affected > 0 ? 1 : 2;
        Redis::del('player:' . $uid);
        return $return;
    }

    /**
     * 休息: c=muster&m=go_rest（旧版 controllers/muster.php:go_rest）
     *
     * 依次：出征名单只剩 1 人时拒绝（4）；训练中的将领需 confrim=1 才终止训练（否则 2）；
     * 从所有阵法站位上撤下（撤下后启用的阵法没有其他将领时 tip = 1，旧版照样撤下）；卸下全部装备与坐骑；goout = 0。
     * 状态码：-5 下阵失败；-4 训练终止失败；-3 获取将领信息失败；-2 卸下装备失败；-1 休息失败；1 成功；2 将领正在训练；3 将领已休息；4 最后一个将领。
     */
    public static function go_rest(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $return = ['status' => 0, 'tip' => 0];
        $gid = (int)$ctx->input('gid', 0);
        $confrim = (int)$ctx->input('confrim', 0);
        if (self::battleCount($uid) === 1) {
            // 最后一个将领 4
            $return['status'] = 4;
            return $return;
        }

        // 检查将领是否训练中
        $unique = Practice::checkPractice($uid, $gid);
        $field = DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->first() ?? [];
        if ($unique !== 0) {
            if ($confrim) {
                $stop = Practice::practiceStop($uid, $unique, (int)($field['practicetime'] ?? 0), (int)($field['practicenow'] ?? 0));
                if ($stop !== 1) {
                    // 训练终止失败 -4
                    $return['status'] = -4;
                    return $return;
                }
            } else {
                // 将领正在训练中 2
                $return['status'] = 2;
                return $return;
            }
        }

        $own = self::ownGeneral($uid, $gid);
        if ($own === null) {
            // 获取将领信息失败 -3
            $return['status'] = -3;
            return $return;
        }
        if ((int)$own['goout'] === 0) {
            // 将领已休息 3
            $return['status'] = 3;
            return $return;
        }

        // 下阵（旧版没有任何阵法行时 get_matrix_by_gid 返回 ['status' => -1]，随后 unset 报致命错误；这里按无需下阵处理）
        $info = OwnMatrix::byGid($uid, $gid);
        if ($info !== []) {
            $matrixid = OwnMatrix::activeId($uid);
            if ($matrixid !== 0) {
                // 将领在启用的阵法上时，检查撤下后阵法上是否还有其他将领
                $mid = null;
                foreach ($info as $value) {
                    if ($value['mid'] === $matrixid) {
                        $mid = $matrixid;
                    }
                }
                if ($mid !== null) {
                    $matrix = OwnMatrix::own($uid, $mid);
                    $last = 1;
                    for ($i = 1; $i < 10; $i++) {
                        if (($matrix['position' . $i] ?? 0) > 0 && $matrix['position' . $i] !== $gid) {
                            $last = 0;
                        }
                    }
                    if ($last === 1) {
                        $return['tip'] = 1;
                    }
                }
            }

            foreach ($info as $key => $value) {
                unset($value['uid']);
                for ($i = 1; $i < 10; $i++) {
                    if (isset($value['position' . $i])) {
                        $value['position' . $i] = 0;
                    }
                }
                $info[$key] = $value;
            }
            if (OwnMatrix::goRest($uid, $info) !== 1) {
                // 下阵失败 -5
                $return['status'] = -5;
                return $return;
            }
        }

        // 卸下所有装备（equipment_model::unequip_all；旧版 own_general.eid1~eid4 新版由 equipment.generalid 派生）
        $equipped = DB::table('equipment')->where('uid', $uid)->where('generalid', $gid)->whereNull('deleted_at')->count();
        if ($equipped > 0
            && DB::table('equipment')->where('uid', $uid)->where('generalid', $gid)->whereNull('deleted_at')->update(['generalid' => 0]) === 0) {
            // 卸下装备失败 -2
            $return['status'] = -2;
            return $return;
        }

        // 卸下所有坐骑（mount_model::take_down_all：将领 mid0~mid4 清零，own_mount.gid 清零）
        OwnMount::takeDownAll($uid, $gid);

        // 休息（general_model::go_rest）
        $affected = DB::table('general')->where('uid', $uid)->where('id', $gid)->whereNull('deleted_at')->update(['goout' => 0]);
        Redis::del('player:' . $uid);
        if ($affected <= 0) {
            // 休息失败 -1
            $return['status'] = -1;
            return $return;
        }
        $return['status'] = 1;
        return $return;
    }

    /**
     * 武将折旧: c=muster&m=sale_general（gid、confirm；confirm 为 0 时只返回折旧系数与可得宝石）
     */
    public static function sale_general(Context $ctx): array
    {
        /**
         * -13 战神殿 / 绝版 / 累计登录 / 充值送豪礼获得的武将不能折旧
         * -12 获取页码失败
         * -11 增加宝石失败
         * -10 更新cd时间失败
         * -9 禁用武将失败
         * -8 下阵失败
         * -7 无法终止训练
         * -6 获取售价失败
         * -5 只有名将榜武将才能折旧
         * -4 已达成名将成就的武将无法折旧
         * -3 未拥有该武将
         * -2 cd时间未过
         * -1 vip等级不足
         *  0 失败
         *  1 成功
         */
        $uid = MemberHandler::resolveUid($ctx);
        $gid = (int)$ctx->input('gid', 0);
        $confirm = (int)$ctx->input('confirm', 0);
        if (DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('uid') === null) {
            return ['status' => 0, 'tip' => 0];
        }
        return Member::locked($uid, static function () use ($uid, $gid, $confirm): array {
            $return = ['status' => 0, 'tip' => 0];
            $fail = static function (int $status, int $msg) use (&$return): array {
                $return['status'] = $status;
                $return['msg'] = self::LANG[$msg];
                return $return;
            };
            $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
            $field = DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->first() ?? [];
            $vip = (int)$member['vip'];
            $now = time();
            if ($vip < 7) {
                // vip等级不足
                return $fail(-1, 1);
            }
            if ($now - (int)($field['general_sale'] ?? 0) <= self::SALE_CD) {
                // cd中
                return $fail(-2, 2);
            }
            $ratio = $vip > 10 ? self::SALE_RULE[10] : self::SALE_RULE[$vip];
            $general = self::ownGeneral($uid, $gid);
            if ($general === null) {
                // 将领不存在
                return $fail(-3, 3);
            }
            // 获取拥有成就列表：已达成成就的将领无法出售
            foreach (array_keys(GeneralBook::ownAchievementList($uid)) as $bid) {
                $ach = BookAchievement::ROWS[$bid] ?? null;
                for ($i = 1; $ach !== null && $i <= 5; $i++) {
                    if ($ach['gid' . $i] === (int)$general['generalid']) {
                        return $fail(-4, 4);
                    }
                }
            }
            // 不在名将榜中无法出售（tavern_model->get_sale_price：Pub tab 2）
            $sale = Pub::ROWS[(int)$general['generalid']] ?? null;
            if ($sale === null || $sale['tab'] !== 2) {
                return $fail(-5, 5);
            }
            // 在战神殿 / 绝版神将 / 累计登录 / 充值送豪礼获得的武将不能折旧（pub_log.type 100 ~ 103）
            $god = PubLog::byGeneral($uid, (int)$general['generalid']);
            if ($god !== []) {
                $msg = match ($god['type']) {
                    100 => 13,
                    101 => 14,
                    102 => 15,
                    103 => 16,
                    default => 0,
                };
                if ($msg > 0) {
                    return $fail(-13, $msg);
                }
            }
            $paytype = $sale['paytype'];
            $price = (int)($sale['price'] * $ratio);
            if (!in_array($paytype, [1, 2, 3], true) || $price <= 0) {
                // 获取价格失败
                return $fail(-6, 6);
            }
            if ($confirm === 0) {
                $return['status'] = 1;
                $return['rule']['vip'] = $vip;
                $return['rule']['ratio'] = ($ratio * 100) . '%';
                $return['reward']['type'] = $paytype;
                $return['reward']['num'] = $price;
                return $return;
            }

            // [训练模型]检查将领是否训练中，终止训练
            $practice = Practice::checkPractice($uid, $gid);
            if ($practice !== 0
                && Practice::practiceStop($uid, $practice, (int)($field['practicetime'] ?? 0), (int)($field['practicenow'] ?? 0)) !== 1) {
                // 训练终止失败
                return $fail(-7, 7);
            }
            if ((int)$general['goout'] === 1) {
                // 下阵
                $down = self::downMatrix($uid, $gid);
                if ($down < 0) {
                    return $fail(-8, 8);
                }
                $return['tip'] = $down;
                DB::table('general')->where('uid', $uid)->where('id', $gid)->whereNull('deleted_at')->update(['goout' => 0]);
            }
            // 卸下所有装备
            DB::table('equipment')->where('uid', $uid)->where('generalid', $gid)->whereNull('deleted_at')->update(['generalid' => 0]);
            // 卸下所有将魂，按未装备的将魂个数校正包裹占用
            if (OwnSoul::unequipAll($uid, $gid) === 1) {
                $num = OwnSoul::unequipCount($uid) - (int)$member['souluse'];
                if ($num > 0) {
                    OwnSoul::addSouluse($uid, $num);
                }
                if ($num < 0) {
                    OwnSoul::subSouluse($uid, -$num);
                }
            }
            // 卸下所有坐骑
            OwnMount::takeDownAll($uid, $gid);
            // 禁用武将（旧版 disable = 时间戳，新版软删除）
            $disable = DB::table('general')->where('uid', $uid)->where('id', $gid)->whereNull('deleted_at')
                ->update(['deleted_at' => date('Y-m-d H:i:s', $now)]);
            if ($disable <= 0) {
                return $fail(-9, 9);
            }
            // 清除招募信息（update_pub_log：own = 0）
            DB::table('pub_log')->where('uid', $uid)->where('generalid', (int)$general['generalid'])->whereNull('deleted_at')->update(['own' => 0]);
            // 更新cd时间
            if (DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->update(['general_sale' => $now]) <= 0) {
                return $fail(-10, 10);
            }
            // 更新图鉴属性加成
            GeneralBook::refresh($uid);
            $addJade = TraderLog::addJade($uid, $paytype, $price, class: 'muster', method: 'sale_general', now: $now);
            if ($addJade['status'] !== 1) {
                return $fail(-11, 11);
            }
            Redis::del('player:' . $uid);
            $list = self::saleGeneralList($uid, 1, 999);
            if ($list['status'] !== 1) {
                return $fail(-12, 12);
            }
            $return['status'] = 1;
            $return['reward']['type'] = $paytype;
            $return['reward']['num'] = $price;
            $return['sale_cd'] = self::SALE_CD;
            $return['list'] = $list['list'];
            $return['first'] = $list['first'];
            $return['page'] = $list['page'];
            return $return;
        });
    }

    /**
     * 武将折旧后的将领列表（get_general_list：list 不带 quality；first 不带 quality）
     *
     * @return array{status: int, list?: array<int, array<string, mixed>>, page?: int, first?: array<string, mixed>}
     */
    private static function saleGeneralList(int $uid, int $page, int $perpage): array
    {
        $totals = DB::table('general')->where('uid', $uid)->whereNull('deleted_at')->count();
        if ($totals === 0) {
            // 将领不存在
            return ['status' => -2];
        }
        $listPage = min($page, (int)ceil($totals / $perpage));
        $rows = DB::table('general')->where('uid', $uid)->whereNull('deleted_at')
            ->orderBy('goout', 'DESC')->orderBy('star', 'DESC')->orderBy('level', 'DESC')->orderBy('id')
            ->limit($perpage)->offset(($listPage - 1) * $perpage)->get();
        $first = $rows[0];
        if (!isset(Skill::ROWS[(int)$first['skillid']])) {
            return ['status' => -5];
        }
        if (!isset(Talent::ROWS[(int)$first['talentid']])) {
            return ['status' => -6];
        }
        $list = [];
        foreach ($rows as $key => $value) {
            $list[$key + 1] = [
                'id' => (int)$value['id'],
                'name' => (string)$value['name'],
                'image' => (int)$value['image'],
                'type' => (int)$value['type'],
                'goout' => (int)$value['goout'],
            ];
        }
        return [
            'status' => 1,
            'list' => $list,
            'page' => $page,
            'first' => self::generalFields($first, array_values(array_diff(self::INDEX_FIELD, ['quality']))),
        ];
    }

    /**
     * 将领从所有阵法站位上撤下（go_rest / sale_general 共用；matrix_model->get_matrix_by_gid + go_rest）
     *
     * @return int 1 撤下后启用的阵法没有其他将领（tip）；0 正常；-1 下阵失败
     */
    private static function downMatrix(int $uid, int $gid): int
    {
        $tip = 0;
        $info = OwnMatrix::byGid($uid, $gid);
        if ($info === []) {
            return $tip;
        }
        $matrixid = OwnMatrix::activeId($uid);
        if ($matrixid !== 0) {
            // 如果阵法已启用并且武将在阵法上,检查阵法上有几人
            $mid = null;
            foreach ($info as $value) {
                if ($value['mid'] === $matrixid) {
                    $mid = $matrixid;
                }
            }
            if ($mid !== null) {
                $matrix = OwnMatrix::own($uid, $mid);
                $last = 1;
                for ($i = 1; $i < 10; $i++) {
                    if (($matrix['position' . $i] ?? 0) > 0 && $matrix['position' . $i] !== $gid) {
                        $last = 0;
                    }
                }
                $tip = $last;
            }
        }
        foreach ($info as $key => $value) {
            unset($value['uid']);
            for ($i = 1; $i < 10; $i++) {
                if (isset($value['position' . $i])) {
                    $value['position' . $i] = 0;
                }
            }
            $info[$key] = $value;
        }
        return OwnMatrix::goRest($uid, $info) === 1 ? $tip : -1;
    }

    /**
     * 出征人数（general_model::battle_count）
     */
    private static function battleCount(int $uid): int
    {
        return DB::table('general')->where('uid', $uid)->where('goout', 1)->whereNull('deleted_at')->count();
    }

    /**
     * 拥有将领（general_model::get_own_by_id 单个 id）
     *
     * @return array<string, mixed>|null
     */
    private static function ownGeneral(int $uid, int $gid): ?array
    {
        return DB::table('general')->where('uid', $uid)->where('id', $gid)->whereNull('deleted_at')->first();
    }

    /**
     * 按旧版字段表输出将领详情（skill / talent 为技能、天赋子数组）
     *
     * @param array<string, mixed> $g
     * @param list<string> $fields
     * @return array<string, mixed>
     */
    private static function generalFields(array $g, array $fields): array
    {
        $return = [];
        foreach ($fields as $field) {
            $return[$field] = match ($field) {
                'name' => (string)$g['name'],
                'skill' => self::musterSkill((int)$g['skillid']),
                'talent' => self::musterTalent((int)$g['talentid']),
                default => (int)$g[$field],
            };
        }
        return $return;
    }

    /**
     * 技能：旧版 controllers/muster.php 取 id / name / type / description，技能不存在时各项为空串
     *
     * @return array<string, mixed>
     */
    private static function musterSkill(int $skillid): array
    {
        $skill = Skill::ROWS[$skillid] ?? null;
        $return = [];
        foreach (['id', 'name', 'type', 'description'] as $field) {
            $return[$field] = $skill !== null ? $skill[$field] : '';
        }
        return $return;
    }

    /**
     * 天赋：旧版 controllers/muster.php 取 id / name / description，天赋不存在时各项为空串
     *
     * @return array<string, mixed>
     */
    private static function musterTalent(int $talentid): array
    {
        $talent = Talent::ROWS[$talentid] ?? null;
        $return = [];
        foreach (['id', 'name', 'description'] as $field) {
            $return[$field] = $talent !== null ? $talent[$field] : '';
        }
        return $return;
    }
}
