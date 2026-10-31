<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\General\GeneralHandler;
use Hanjiang\MasterData\MountTemp;
use Hanjiang\Model\Member;
use Hanjiang\Model\OwnMount;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 坐骑（移植旧版 controllers/mount.php + models/mount_model.php）
 *
 * 客户端（public/static/js/view/mountpop.js）调用的 7 个方法：index / get_info_by_id / get_level_info / levelup /
 * fit_on / take_down / get_mount_by_page。
 *
 * 存储见 Model\OwnMount：own_mount 每匹一行（gid 为佩戴的将领），将领身上的坐骑记在 general.mid0（主战）~ mid4（备战），
 * 战斗加成由 Battle\TeamBuilder 按 mid0 ~ mid4 读取。主公 120 级开启（未开启返回 status 2）。
 *
 * 界面数据：坐骑字段取 id / name / mid / gid / gender / quality / level / attack / wufang / cefang / hp / chip
 * （升级界面多 toexp），另加 is_full（等级 >= 20）、special_type / special_value（模板 type 与 value 都大于 0 时，
 * value + 模板 level × 坐骑等级）、type（经验马 101 / 102 为 1，其余 0），列表里的碎片行加 full_num「chip/10」；
 * 列表按 type 降序（经验马在前，其余保持查询顺序）。将领加成：mid0 全额、mid1 ~ mid4 各 ceil(× 0.3)。
 *
 * 与旧版的差别：
 * - fit_on 的 site 小于 0 时返回 -1（旧版会拼出 mid-1 列的 UPDATE 报错）；
 * - 修改数据的方法在 Member::locked（玩家行锁）里执行；
 * - 数值字段输出为整数（旧版 MySQL 驱动输出字符串）；JSON 里 is_full / special_* / full_num / type 的键顺序统一。
 */
final class MountHandler
{
    /** 开启等级（旧版 $_level） */
    public const int OPEN_LEVEL = 120;
    /** 经验马模板 id（type 1，不能升级） */
    public const array EXP_MOUNTS = [101, 102];
    /** 坐骑特殊类型：一个将领不能同时佩戴两匹同种特殊类型的坐骑（旧版 $_mstype） */
    public const array SPECIAL_TYPES = [1, 2];

    /** 界面字段（旧版 $field） */
    private const array FIELD = ['id', 'name', 'mid', 'gid', 'gender', 'quality', 'level', 'attack', 'wufang', 'cefang', 'hp', 'chip'];
    /** 升级界面字段（多 toexp） */
    private const array FIELD_EXP = ['id', 'name', 'mid', 'gid', 'gender', 'quality', 'level', 'attack', 'wufang', 'cefang', 'hp', 'toexp', 'chip'];

    /**
     * 初始界面: c=mount&m=index
     *
     * 状态码：2 功能未开启；-1 没有出征将领；1 成功。
     * general 为出征将领（按转生、等级降序，键从 1 起）；ginfo 为第一个将领的 mid0 ~ mid4 坐骑与加成；
     * mlist 为第一页未装配的坐骑；minfo 为第一个将领身上的第一匹坐骑（没有则取 mlist 第一匹）。
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $return = ['status' => 0];

        // 判断功能是否开启
        if (!self::isOpen($uid)) {
            $return['status'] = 2;
            return $return;
        }

        // 获取出征武将列表
        $result = GeneralHandler::battleList($uid);
        if ($result === []) {
            $return['status'] = -1;
            return $return;
        }

        // 得到该列表中的所有坐骑id（第一个武将的坐骑）
        $general = [];
        $firstGeneral = [];
        foreach ($result as $key => $val) {
            foreach (['id', 'name', 'level', 'image', 'type', 'turn', 'quality'] as $field) {
                $general[$key][$field] = $val[$field];
            }
            if ($firstGeneral === []) {
                $firstGeneral = $val;
            }
        }

        $minfo = [];
        $info = self::generalMounts($uid, $firstGeneral, 0, $minfo);

        // 得到第1页未装配的坐骑
        $result = OwnMount::mountByPage($uid, 1);
        $newlist = self::mountList($result['list'], self::FIELD);
        if ($minfo === [] && $result['list'] !== []) {
            $minfo = self::decorate($result['list'][0], self::FIELD);
        }

        // 成功返回
        $return['status'] = 1;
        $return['page'] = $result['page'];
        $return['allpage'] = $result['allpage'];
        $return['minfo'] = $minfo;
        $return['mlist'] = $newlist;
        $return['general'] = $general;
        $return['ginfo'] = $info;
        return $return;
    }

    /**
     * 查询单个将领坐骑信息: c=mount&m=get_info_by_id&gid=将领id&mid=坐骑id&page=页码
     *
     * 状态码：2 功能未开启；-1 将领不存在；1 成功。
     * minfo 为将领身上第一匹坐骑（mid 不为 0 时取身上那一匹）；都没有时取第 page 页的第一匹未装配坐骑（整行）。
     * mid 大于 0 时才返回 mlist（第 page 页未装配坐骑，页码超出取最后一页）与其 allpage，否则 mlist 为空、allpage 1。
     */
    public static function get_info_by_id(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $return = ['status' => 0];

        // 判断功能是否开启
        if (!self::isOpen($uid)) {
            $return['status'] = 2;
            return $return;
        }

        $gid = (int)$ctx->input('gid', 0);
        $fid = (int)$ctx->input('mid', 0);
        $page = (int)$ctx->input('page', 0);

        // 查询将领是否存在
        $general = GeneralHandler::ownLegacyList($uid, $gid)[0] ?? null;
        if ($gid <= 0 || $general === null) {
            $return['status'] = -1;
            return $return;
        }

        $minfo = [];
        $info = self::generalMounts($uid, $general, $fid, $minfo);

        // 如果坐骑信息为空，查询本页的第一个坐骑信息
        if ($minfo === []) {
            $row = OwnMount::infoByPage($uid, $page);
            if ($row !== []) {
                $minfo = self::decorate($row, array_keys($row));
            }
        }

        // 未装配的坐骑
        $newlist = [];
        $allpage = 1;
        if ($fid > 0) {
            $result = OwnMount::mountByPage($uid, $page, 0, 1);
            $newlist = self::mountList($result['list'], self::FIELD);
            $allpage = $result['allpage'];
        }

        // 成功返回
        $return['status'] = 1;
        $return['info'] = $info;
        $return['mlist'] = $newlist;
        $return['page'] = $page;
        $return['allpage'] = $allpage;
        $return['minfo'] = $minfo;
        return $return;
    }

    /**
     * 坐骑升级界面: c=mount&m=get_level_info&id=坐骑id
     *
     * 状态码：2 功能未开启；-1 坐骑不存在；-6 经验马不能升级；1 成功。
     * info 为该坐骑整行；list 为第一页未装配的其他坐骑（带 toexp，可作为升级材料）。
     */
    public static function get_level_info(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $return = ['status' => 0];

        // 判断功能是否开启
        if (!self::isOpen($uid)) {
            $return['status'] = 2;
            return $return;
        }

        // 要升级的坐骑id
        $id = (int)$ctx->input('id', 0);

        // 获得要升级的这个坐骑信息
        $row = OwnMount::infoById($uid, $id);
        if ($row === []) {
            $return['status'] = -1;
            return $return;
        }
        $row = OwnMount::legacyRow($row);
        $info = self::decorate($row, array_keys($row));

        // 经验马不能升级
        if (in_array($info['mid'], self::EXP_MOUNTS, true)) {
            $return['status'] = -6;
            return $return;
        }

        // 得到第1页未装配的坐骑
        $result = OwnMount::mountByPage($uid, 1, $id);

        // 成功返回
        $return['status'] = 1;
        $return['list'] = self::mountList($result['list'], self::FIELD_EXP);
        $return['page'] = 1;
        $return['allpage'] = $result['allpage'];
        $return['info'] = $info;
        return $return;
    }

    /**
     * 升级: c=mount&m=levelup&id=坐骑id&list=被吃的坐骑id（逗号分隔）
     *
     * 状态码：2 功能未开启；-1 list 为空；-7 list 里有未满 10 片的碎片行；-2 list 里有不存在或已佩戴的坐骑；
     * -3 要升级的坐骑不存在；-4 已满级；-6 经验马不能升级；-5 删除材料坐骑失败；1 成功。
     * 材料坐骑的 toexp 之和加到该坐骑（按 MountLevel 逐级升级，最高 20 级），材料坐骑删除并在 mount_info_log 记 type 2，
     * 再写 mount_exp_log。info 为升级后的数据，mlist 为第一页未装配的其他坐骑。
     */
    public static function levelup(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $id = (int)$ctx->input('id', 0);
        // 要搭配升级的坐骑id 用逗号分隔
        $list = (string)$ctx->input('list', '');

        return Member::locked($uid, static function () use ($uid, $id, $list): array {
            $return = ['status' => 0];

            // 判断功能是否开启
            if (!self::isOpen($uid)) {
                $return['status'] = 2;
                return $return;
            }

            $midArr = array_map('intval', explode(',', $list));
            if ($midArr === []) {
                $return['status'] = -1;
                return $return;
            }

            // 查询坐骑信息，把已装配的坐骑筛选出来
            $mlist = OwnMount::infoById($uid, $midArr);
            foreach ($mlist as $key => $val) {
                if ((int)$val['gid'] > 0) {
                    unset($mlist[$key]);
                }
                // 碎片不能升级
                if ((int)$val['chip'] > 0 && (int)$val['chip'] < OwnMount::CHIP_FULL) {
                    $return['status'] = -7;
                    return $return;
                }
            }

            if (count($mlist) !== count($midArr)) {
                $return['status'] = -2;
                return $return;
            }

            // 获得要升级的这个坐骑信息
            $info = OwnMount::infoById($uid, $id);
            if ($info === []) {
                $return['status'] = -3;
                return $return;
            }
            $info = OwnMount::legacyRow($info);

            // 判断该坐骑是否满级
            if ($info['level'] >= OwnMount::MAX_LEVEL) {
                $return['status'] = -4;
                return $return;
            }
            // 判断经验马不能升级
            if (in_array($info['mid'], self::EXP_MOUNTS, true)) {
                $return['status'] = -6;
                return $return;
            }

            // 获得能增加的经验
            $addExp = 0;
            foreach ($mlist as $val) {
                $addExp += (int)$val['toexp'];
            }

            // 把这几个搭配升级的坐骑删除
            if (!OwnMount::disable($uid, $midArr, 'mount', 'levelup')) {
                $return['status'] = -5;
                return $return;
            }

            // 添加经验
            $addInfo = OwnMount::addExp($uid, $id, $addExp);
            $addInfo['id'] = $info['id'];
            $addInfo['name'] = $info['name'];
            $addInfo['mid'] = $info['mid'];
            $addInfo['gender'] = $info['gender'];
            $addInfo['quality'] = $info['quality'];
            if (!isset($addInfo['attack'])) {
                // 没有升级，使用坐骑本身的属性
                $addInfo['attack'] = $info['attack'];
                $addInfo['wufang'] = $info['wufang'];
                $addInfo['cefang'] = $info['cefang'];
                $addInfo['hp'] = $info['hp'];
            }
            $addInfo = self::decorate($addInfo, array_keys($addInfo));

            // 得到第1页未装配的坐骑
            $result = OwnMount::mountByPage($uid, 1, $id);
            $minfo = $result['list'] !== [] ? self::decorate($result['list'][0], self::FIELD_EXP) : [];

            // 添加log
            OwnMount::addExpLog($uid, $id, implode(',', explode(',', $list)), $addExp, (int)$addInfo['exp'], (int)$addInfo['level']);

            // 成功返回
            $return['status'] = 1;
            $return['info'] = $addInfo;
            $return['mlist'] = self::mountList($result['list'], self::FIELD_EXP);
            $return['page'] = 1;
            $return['minfo'] = $minfo;
            $return['allpage'] = $result['allpage'];
            return $return;
        });
    }

    /**
     * 装上坐骑: c=mount&m=fit_on&gid=将领id&mid=坐骑id&site=位置（0 主战，1 ~ 4 备战）&page=页码
     *
     * 状态码：2 功能未开启；-1 参数不完整（gid / mid 为 0 或 site 不在 0 ~ 4）；-2 将领不存在；-3 坐骑不存在；
     * -8 将领已佩戴同种特殊类型（1、2）的坐骑；-4 该位置原有的坐骑不存在；-6 坐骑已被其他将领佩戴；-7 碎片不能佩戴；1 成功。
     * 写 general.mid{site}：该位置原有坐骑时，若新坐骑原本没被佩戴则把原坐骑卸下（gid 清零），若新坐骑原本在该将领的
     * 另一个位置则两个位置互换；新坐骑原来所在位置清零。再把新坐骑的 gid 设为该将领。
     */
    public static function fit_on(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $gid = (int)$ctx->input('gid', 0);
        $mid = (int)$ctx->input('mid', 0);
        $site = (int)$ctx->input('site', 0);
        $page = (int)$ctx->input('page', 0);

        return Member::locked($uid, static function () use ($uid, $gid, $mid, $site, $page): array {
            $return = ['status' => 0];

            // 判断功能是否开启
            if (!self::isOpen($uid)) {
                $return['status'] = 2;
                return $return;
            }

            // 判断参数是否完整
            if (!$gid || !$mid || $site > 4 || $site < 0) {
                $return['status'] = -1;
                return $return;
            }

            // 查询将领是否存在
            $general = GeneralHandler::ownLegacyList($uid, $gid)[0] ?? null;
            if ($general === null) {
                $return['status'] = -2;
                return $return;
            }

            // 查询坐骑信息
            $midarr = [$mid];
            $siteMid = $general['mid' . $site];
            if ($siteMid > 0) {
                $midarr[] = $siteMid;
            }
            $list = OwnMount::infoById($uid, $midarr);

            // 获取要穿戴的坐骑信息失败
            if (!isset($list[$mid])) {
                $return['status'] = -3;
                return $return;
            }

            // 判断坐骑特殊类型 一个武将不能同时装配拥有同种特殊类型的坐骑（type:1,2）
            $typeList = [];
            $nowMtype = self::templateType((int)$list[$mid]['mid']);
            if (in_array($nowMtype, self::SPECIAL_TYPES, true)) {
                $siteType = isset($list[$siteMid]) ? self::templateType((int)$list[$siteMid]['mid']) : null;
                if ($siteMid === 0 || $siteType !== $nowMtype) {
                    for ($i = 0; $i <= 4; $i++) {
                        if ($general['mid' . $i] > 0) {
                            $typeList[] = $general['mid' . $i];
                        }
                    }
                }
            }
            if ($typeList !== []) {
                foreach (OwnMount::infoById($uid, $typeList) as $minfo) {
                    if ($nowMtype === self::templateType((int)$minfo['mid']) && !in_array($mid, $typeList, true)) {
                        $return['status'] = -8;
                        return $return;
                    }
                }
            }

            // 要穿戴的位置有坐骑，但是坐骑信息不存在
            if ($siteMid > 0 && !isset($list[$siteMid])) {
                $return['status'] = -4;
                return $return;
            }

            // 该坐骑已被其他将领穿戴
            $mountGid = (int)$list[$mid]['gid'];
            if ($mountGid > 0 && $mountGid !== $gid) {
                $return['status'] = -6;
                return $return;
            }

            $data = ['mid' . $site => $mid];

            // 如果这个坐骑之前在别的位置，那么，直接把这个位置重置为0
            if ($mountGid > 0) {
                for ($i = 0; $i <= 4; $i++) {
                    if ($general['mid' . $i] === $mid) {
                        $data['mid' . $i] = 0;
                    }
                }
            }

            if ((int)$list[$mid]['chip'] > 0) {
                // 不能装备
                $return['status'] = -7;
                return $return;
            }
            // 如果那个位置有坐骑  就替换位置
            if ($siteMid > 0) {
                if ($mountGid === 0) {
                    OwnMount::adorn($uid, $siteMid, 0);
                } else {
                    $nowSite = 0;
                    for ($i = 0; $i <= 4; $i++) {
                        if ($general['mid' . $i] === $mid) {
                            $nowSite = $i;
                        }
                    }
                    $data['mid' . $nowSite] = $siteMid;
                }
            }

            foreach ($data as $key => $val) {
                $general[$key] = $val;
            }
            OwnMount::updateGeneralMount($uid, $gid, $data);

            // 带上指定的坐骑
            OwnMount::adorn($uid, $mid, $gid);

            // 查询最新的战斗数据
            $minfo = [];
            $info = self::generalMounts($uid, $general, $mid, $minfo, true);

            // 未装配的坐骑
            $result = OwnMount::mountByPage($uid, $page, 0, 1);

            // 成功返回
            $return['status'] = 1;
            $return['info'] = $info;
            $return['mlist'] = self::mountList($result['list'], self::FIELD);
            $return['page'] = $result['page'];
            $return['minfo'] = $minfo;
            $return['allpage'] = $result['allpage'];
            return $return;
        });
    }

    /**
     * 取下坐骑: c=mount&m=take_down&mid=坐骑id&page=页码
     *
     * 状态码：2 功能未开启；-1 坐骑不存在；1 成功。
     * 坐骑已被佩戴时把将领身上它所在的 mid{site} 清零（旧版找不到时 site 为 0，清的是 mid0），再把坐骑 gid 清零。
     * 坐骑原本没被佩戴时 info 的 mid0 ~ mid4 为 0，id / type / name 为 null（旧版未定义变量）。
     */
    public static function take_down(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $mid = (int)$ctx->input('mid', 0);
        $page = (int)$ctx->input('page', 0);

        return Member::locked($uid, static function () use ($uid, $mid, $page): array {
            $return = ['status' => 0];

            // 判断功能是否开启
            if (!self::isOpen($uid)) {
                $return['status'] = 2;
                return $return;
            }

            // 查询该坐骑信息
            $info = OwnMount::infoById($uid, $mid);
            if ($info === []) {
                $return['status'] = -1;
                return $return;
            }

            $general = [];
            if ((int)$info['gid'] > 0) {
                // 查询将领信息
                $general = GeneralHandler::ownLegacyList($uid, (int)$info['gid'])[0] ?? [];
                $site = 0;
                for ($i = 0; $i <= 4; $i++) {
                    if (($general['mid' . $i] ?? 0) === $mid) {
                        $site = $i;
                    }
                }
                $data = ['mid' . $site => 0];
                foreach ($data as $key => $val) {
                    $general[$key] = $val;
                }
                OwnMount::updateGeneralMount($uid, (int)$info['gid'], $data);
            }

            // 执行卸下操作
            OwnMount::adorn($uid, $mid);

            // 查询最新的战斗数据
            $minfo = [];
            $info = self::generalMounts($uid, $general, 0, $minfo, true);

            // 未装配的坐骑
            $result = OwnMount::mountByPage($uid, $page, 0, 1);
            $minfo = $result['list'] !== [] ? self::decorate($result['list'][0], self::FIELD) : [];

            // 成功返回
            $return['status'] = 1;
            $return['info'] = $info;
            $return['mlist'] = self::mountList($result['list'], self::FIELD);
            $return['page'] = $result['page'];
            $return['minfo'] = $minfo;
            $return['allpage'] = $result['allpage'];
            return $return;
        });
    }

    /**
     * 分页查询未装配的坐骑: c=mount&m=get_mount_by_page&page=页码&mid=要排除的坐骑id（坐骑升级界面用）
     *
     * 状态码：2 功能未开启；1 成功。info 为本页第一匹（带 toexp）。
     */
    public static function get_mount_by_page(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $return = ['status' => 0, 'list' => []];

        // 判断功能是否开启
        if (!self::isOpen($uid)) {
            $return['status'] = 2;
            return $return;
        }

        $page = (int)$ctx->input('page', 0);
        $mid = (int)$ctx->input('mid', 0);
        $page = $page > 0 ? $page : 1;

        // 查询未装配的坐骑列表
        $list = OwnMount::mountByPage($uid, $page, $mid);

        // 成功返回
        $return['status'] = 1;
        $return['page'] = $page;
        $return['info'] = $list['list'] !== [] ? self::decorate($list['list'][0], self::FIELD_EXP) : [];
        $return['list'] = self::mountList($list['list'], self::FIELD_EXP);
        $return['allpage'] = $list['allpage'];
        return $return;
    }

    /**
     * 将领 mid0 ~ mid4 的坐骑与加成（旧版各方法里同一段：mid0 全额、mid1 ~ mid4 各 ceil(× 0.3)）
     *
     * $minfo 传入空数组：$fit 为 false 时取第一匹（$fid 不为 0 时取 id 为 $fid 的那匹），$fit 为 true 时取 id 为 $fid 的那匹。
     * 将领为空数组时（take_down 的坐骑原本没被佩戴）mid0 ~ mid4 为 0，id / type / name 为 null。
     *
     * @param array<string, mixed> $general 旧版 own_general 行（GeneralHandler::battleList / ownLegacyList）
     * @param array<string, mixed> $minfo
     * @return array<string, mixed>
     */
    private static function generalMounts(int $uid, array $general, int $fid, array &$minfo, bool $fit = false): array
    {
        $mid = [];
        for ($i = 0; $i <= 4; $i++) {
            $mid[$i] = (int)($general['mid' . $i] ?? 0);
        }
        $ids = array_values(array_filter($mid, static fn (int $v): bool => $v > 0));
        $mlist = $ids === [] ? [] : OwnMount::infoById($uid, $ids);

        $info = [];
        $attack = $wufang = $cefang = $hp = 0;
        for ($i = 0; $i <= 4; $i++) {
            if ($mid[$i] > 0 && isset($mlist[$mid[$i]])) {
                $row = OwnMount::legacyRow($mlist[$mid[$i]]);
                $info['mid' . $i] = self::decorate($row, self::FIELD);
                if ($i === 0) {
                    $attack += $row['attack'];
                    $wufang += $row['wufang'];
                    $cefang += $row['cefang'];
                    $hp += $row['hp'];
                } else {
                    $attack += (int)ceil($row['attack'] * 0.3);
                    $wufang += (int)ceil($row['wufang'] * 0.3);
                    $cefang += (int)ceil($row['cefang'] * 0.3);
                    $hp += (int)ceil($row['hp'] * 0.3);
                }
                if ($fit ? $mid[$i] === $fid : ($minfo === [] && ($fid === 0 || $fid === $mid[$i]))) {
                    $minfo = $info['mid' . $i];
                }
            } else {
                $info['mid' . $i] = 0;
            }
        }
        $info['id'] = $general['id'] ?? null;
        $info['type'] = $general['type'] ?? null;
        $info['name'] = $general['name'] ?? null;
        $info['attack'] = $attack;
        $info['wufang'] = $wufang;
        $info['cefang'] = $cefang;
        $info['hp'] = $hp;
        return $info;
    }

    /**
     * 未装配坐骑列表（碎片行加 full_num），按 type 降序（经验马在前，其余保持原顺序）
     *
     * @param list<array<string, mixed>> $rows OwnMount::legacyRow 行
     * @param list<string> $fields
     * @return list<array<string, mixed>>
     */
    private static function mountList(array $rows, array $fields): array
    {
        $list = [];
        foreach ($rows as $row) {
            $item = self::decorate($row, $fields);
            if ($row['chip'] > 0) {
                $item['full_num'] = $row['chip'] . '/' . OwnMount::CHIP_FULL;
            }
            $list[] = $item;
        }
        // 排序（旧版 _array_sort 按 type arsort；PHP 8 排序稳定，同 type 保持原顺序）
        usort($list, static fn (array $a, array $b): int => $b['type'] <=> $a['type']);
        return $list;
    }

    /**
     * 取字段并加 is_full / special_type / special_value / type
     *
     * @param array<string, mixed> $row
     * @param list<string> $fields
     * @return array<string, mixed>
     */
    private static function decorate(array $row, array $fields): array
    {
        $item = [];
        foreach ($fields as $field) {
            $item[$field] = $row[$field] ?? null;
        }
        $level = (int)($row['level'] ?? 0);
        $item['is_full'] = $level >= OwnMount::MAX_LEVEL ? 1 : 0;

        // 坐骑特殊属性
        $temp = MountTemp::ROWS[(int)($row['mid'] ?? 0)] ?? null;
        if ($temp !== null && $temp['type'] > 0 && $temp['value'] > 0) {
            $item['special_type'] = $temp['type'];
            $item['special_value'] = $temp['value'] + ($temp['level'] * $level);
        }

        // 返回坐骑的type（经验坐骑 1，普通坐骑 0）
        $item['type'] = in_array((int)($row['mid'] ?? 0), self::EXP_MOUNTS, true) ? 1 : 0;
        return $item;
    }

    /**
     * 坐骑模板的特殊类型（MountTemp.type）；模板不存在为 null
     */
    private static function templateType(int $mid): ?int
    {
        return MountTemp::ROWS[$mid]['type'] ?? null;
    }

    /**
     * 功能是否开启（主公等级不低于 120）
     */
    private static function isOpen(int $uid): bool
    {
        return (int)(DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('level') ?? 0) >= self::OPEN_LEVEL;
    }
}
