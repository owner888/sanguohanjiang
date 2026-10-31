<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\General\GeneralHandler;
use Hanjiang\Handler\General\SoulHandler;
use Hanjiang\Handler\Pvp\CountryHandler;
use Hanjiang\Model\Beauty;
use Hanjiang\Model\HeroTalent;
use Hanjiang\Model\OwnGem;
use Hanjiang\Model\OwnMount;
use Hanjiang\Model\OwnSoul;
use Hanjiang\Model\OwnStar;
use Hanjiang\Model\WarCollege;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 玩家个人资料、武将出征展示与成就总览 (DDD Activity Domain)
 */
final class InformationHandler
{
    /** 星魄最大等级（旧版 $_tree_max_level） */
    private const int TREE_MAX_LEVEL = 5;
    /** 旧版语言包 star_lang.php star_init_error */
    private const string LANG_STAR_INIT_ERROR = '基础数据错误！';
    /** 旧版语言包 warcollege_lang.php type_error */
    private const string LANG_TYPE_ERROR = '参数错误';

    /**
     * 玩家个人资料与将领全览: c=information&m=index
     */
    public static function index(Context $ctx): array
    {
        $myUid = MemberHandler::resolveUid($ctx);
        $targetUid = (int)$ctx->input('uid', $myUid);
        if ($targetUid <= 0) {
            $targetUid = $myUid;
        }

        $member = DB::table('member')->where('uid', $targetUid)->whereNull('deleted_at')->first();
        if ($member === null) {
            return [
                'status' => -1,
                'message' => '获取用户信息失败',
            ];
        }

        $field = DB::table('member_field')->where('uid', $targetUid)->whereNull('deleted_at')->first();
        // 旧版 information.php：演武榜排名取 member_field.rank（未入榜为 0）
        $rank = (int)($field['rank'] ?? 0);

        $countryId = (int)($member['country'] ?? 0);
        $countryName = DB::table('country')->where('id', $countryId)->whereNull('deleted_at')->value('name') ?? '';

        $player = [
            'nickname' => (string)($member['nickname'] ?? ('主公' . $targetUid)),
            'vip' => (int)($member['vip'] ?? 1),
            'level' => max(1, (int)($member['level'] ?? 1)),
            'rank' => $rank,
            'country' => $countryName,
            'country_level' => ($countryId > 0) ? 1 : 0,
        ];

        // 查询玩家所有武将 (过滤软删除)
        // 查看他人信息时不再替对方补武将（旧版无此行为，初始武将只在注册时发放）
        $generals = DB::table('general')->where('uid', $targetUid)->whereNull('deleted_at')->get();

        // 排序规则：出征武将优先，等级降序
        usort($generals, static function (array $a, array $b): int {
            $aOut = (int)$a['goout'];
            $bOut = (int)$b['goout'];
            if ($aOut !== $bOut) {
                return $bOut <=> $aOut;
            }
            $aLevel = (int)($a['level'] ?? 1);
            $bLevel = (int)($b['level'] ?? 1);
            if ($aLevel !== $bLevel) {
                return $bLevel <=> $aLevel;
            }
            return ((int)($a['id'] ?? 0)) <=> ((int)($b['id'] ?? 0));
        });

        $list = [];
        $idx = 1;
        foreach ($generals as $g) {
            if ($idx > 8) {
                break;
            }
            // 武将展示字段按旧版 general get_info（info_field + hp）
            $list[(string)$idx] = GeneralHandler::legacyInfo($targetUid, $g) ?? [];
            $idx++;
        }

        $isFriend = ($myUid === $targetUid) ? 1 : 0;

        return [
            'status' => 1,
            'player' => $player,
            'list' => $list,
            'friend' => $isFriend,
            'tree' => 0,
            // 向下兼容保留原字段
            'uid' => $targetUid,
            'nickname' => $player['nickname'],
            'level' => $player['level'],
            'country' => $countryId,
            'country_name' => $countryName,
            'vip' => $player['vip'],
            'general_count' => count($generals),
            'message' => 'ok',
        ];
    }

    /**
     * 个人战斗属性总览 (委托至 index 动作)
     */
    public static function get_info(Context $ctx): array
    {
        return self::index($ctx);
    }

    /**
     * 获取个人成就列表: c=information&m=get_own_achievement
     */
    public static function get_own_achievement(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);

        $achievements = [
            1 => ['id' => 1, 'name' => '初出茅庐', 'desc' => '通关大兴山第一个据点', 'achieved' => 1],
            2 => ['id' => 2, 'name' => '神将麾下', 'desc' => '招募第一位历史名将', 'achieved' => 1],
            3 => ['id' => 3, 'name' => '百炼成钢', 'desc' => '强化一件装备至 2 级', 'achieved' => 1],
            4 => ['id' => 4, 'name' => '威震中原', 'desc' => '等级达到 10 级', 'achieved' => 0],
        ];

        return [
            'status' => 1,
            'list' => array_values($achievements),
            'total_score' => 300,
            'message' => 'ok',
        ];
    }

    /**
     * 他人的铜雀台美人（移植旧版 controllers/information.php get_all_beauty_info）: c=information&m=get_all_beauty_info&uid=
     *
     * 对方 60 级以上时列出激活中的美人与已解锁技能（技能等级同 beauty index）；不到 60 级 list 为空。
     * status：-1 参数错误；1 成功
     */
    public static function get_all_beauty_info(Context $ctx): array
    {
        $uid = (int)$ctx->input('uid', 0);
        if ($uid <= 0) {
            // 缺少参数
            return ['status' => -1, 'msg' => '参数错误'];
        }
        $level = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('level');
        $newReturn = [];
        if ($level !== null && (int)$level >= 60) {
            foreach (Beauty::ownAll($uid) as $val) {
                if ($val['open_status'] !== 1) {
                    continue;
                }
                $beautyLevel = $val['beauty_level'];
                $skillIds = [$val['skill1'], $val['skill2'], $val['skill3']];
                $lock = Beauty::upStatus($beautyLevel)['lock'];
                $levelArray = match ($lock) {
                    1 => [$beautyLevel, 1, 1],
                    2 => [$beautyLevel, $beautyLevel - 20, 1],
                    3 => [$beautyLevel, $beautyLevel - 20, $beautyLevel - 60],
                    default => [],
                };
                // 显示已经解锁的技能
                $skillInfo = [];
                foreach ($skillIds as $key => $value) {
                    if ($lock >= $key + 1) {
                        $skill = Beauty::skillInfo($val['beauty_id'], $value, $levelArray[$key]);
                        $skillInfo[$key] = [
                            'skill_name' => $skill['skill_name'] ?? null,
                            'skill_content' => $skill['skill_content'] ?? null,
                            'skill_value' => $skill['skill_value'] ?? null,
                        ];
                    }
                }
                $newReturn[] = [
                    'beauty_id' => $val['beauty_id'],
                    'beauty_name' => $val['beauty_name'],
                    'beauty_level' => $beautyLevel,
                    'skill_info' => $skillInfo,
                ];
            }
        }
        return ['status' => 1, 'list' => $newReturn];
    }

    /**
     * 他人将领的将魂: c=information&m=get_own_soul（uid 玩家，gid 将领）
     *
     * 对方 50 级以上时取将领的将魂槽（没有时建档）与已装备的将魂；site1~site8 同 c=soul（islock 0 已解锁 / 1 未解锁带
     * unlock_level / 2 未解锁但装着将魂）。
     */
    public static function get_own_soul(Context $ctx): array
    {
        /**
         * -5 添加将领将魂槽失败
         * -2 获取将领信息失败
         * -1 获取用户信息失败
         *  0 失败
         *  1 成功
         */
        $return = ['status' => 0, 'general' => []];
        $gid = (int)$ctx->input('gid', 0);
        $uid = (int)$ctx->input('uid', 0);

        $level = $uid > 0 ? DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('level') : null;
        if ($level === null) {
            // 获取用户信息失败
            $return['status'] = -1;
            return $return;
        }
        $general = GeneralHandler::ownLegacyList($uid, $gid)[0] ?? null;
        if ($general === null) {
            // 获取将领信息失败
            $return['status'] = -2;
            return $return;
        }
        $generalArr = [];
        foreach (['id', 'name', 'quality', 'level', 'image', 'type', 'star', 'turn'] as $value) {
            $generalArr[$value] = $general[$value];
        }

        $equip = [];
        $site = [];
        if ((int)$level > 50) {
            // 获取将领拥有的将魂（没有将魂槽时建档）
            $site = SoulHandler::generalSites($general['uid'], $general['id']);
            if ($site === null) {
                // 添加将领失败
                $return['status'] = -5;
                return $return;
            }
            $equip = SoulHandler::equippedSouls($site);
        } else {
            for ($i = 1; $i < 9; $i++) {
                $site['site' . $i] = 0;
            }
        }
        // 获取将领已解锁槽位
        $unlockSite = OwnSoul::unlockSite($general['turn']);
        foreach (SoulHandler::siteInfo($site, $unlockSite, $equip) as $i => $info) {
            $generalArr['site' . $i] = $info;
        }
        $return['status'] = 1;
        $return['general'] = $generalArr;
        return $return;
    }

    /**
     * 他人的主公天赋: c=information&m=get_talent_list（uid）
     *
     * 旧版对方没有天赋记录时按 5 项白色 0 星建档（不判断对方等级），同 HeroTalent::list；对方不存在时旧版也会给这个 uid
     * 建档，这里回 -2 获取列表失败。
     */
    public static function get_talent_list(Context $ctx): array
    {
        $return = [];
        $uid = (int)$ctx->input('uid', 0);
        if ($uid <= 0) {
            // uid非法
            $return['status'] = -1;
            return $return;
        }
        $exists = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('uid') !== null;
        // 获得主公天赋列表
        $list = $exists ? HeroTalent::list($uid) : [];
        if ($list === []) {
            // 获取列表失败
            $return['status'] = -2;
            return $return;
        }
        $return['status'] = 1;
        $return['talent'] = $list;
        return $return;
    }

    /**
     * 他人将领装备上的宝石: c=information&m=get_general_gem（uid，gid）
     *
     * info：eid1~eid4 为装备（id / equipid / name / type / quality / level / powerup，空位为 []），有宝石的装备带 gem
     * （1~8 号位，空位为 0）。
     */
    public static function get_general_gem(Context $ctx): array
    {
        /**
         * -3 获取装备信息失败
         * -2 获取将领信息失败
         * -1 参数错误
         *  1 成功
         *  2 将领没有装备
         */
        $return = ['status' => 0];
        $uid = (int)$ctx->input('uid', 0);
        $gid = (int)$ctx->input('gid', 0);
        // 参数错误
        if (!$gid || !$uid) {
            $return['status'] = -1;
            return $return;
        }
        // 查询将领已装配的道具
        $info = GeneralHandler::ownLegacyList($uid, $gid)[0] ?? null;
        if ($info === null) {
            $return['status'] = -2;
            return $return;
        }
        // 道具id
        $eidArr = [];
        for ($i = 1; $i <= 4; $i++) {
            if ($info['eid' . $i] > 0) {
                $eidArr[] = $info['eid' . $i];
            }
        }
        // 判断列表是否为空
        if ($eidArr === []) {
            $return['status'] = 2;
            return $return;
        }
        // 得到道具的详细信息
        $eidList = [];
        foreach (DB::table('equipment')->where('uid', $uid)->whereIn('id', $eidArr)->whereNull('deleted_at')->get() as $row) {
            $eidList[(int)$row['id']] = $row;
        }
        if ($eidList === []) {
            $return['status'] = -3;
            return $return;
        }
        // 按道具获得宝石列表
        $newList = [];
        foreach (OwnGem::byEid($uid, $eidArr) as $val) {
            $newList[(int)$val['eid']][(int)$val['site']] = [
                'id' => (int)$val['id'],
                'name' => (string)$val['name'],
                'type' => (int)$val['type'],
                'quality' => (int)$val['quality'],
                'level' => (int)$val['level'],
                'power' => (int)$val['power'],
            ];
        }
        foreach ($newList as $key => $val) {
            for ($i = 1; $i <= 8; $i++) {
                if (!isset($val[$i])) {
                    $newList[$key][$i] = 0;
                }
            }
        }
        // 组装信息
        $equip = [];
        for ($i = 1; $i <= 4; $i++) {
            $eid = $info['eid' . $i];
            if (isset($eidList[$eid])) {
                $row = $eidList[$eid];
                $equip['eid' . $i] = [
                    'id' => (int)$row['id'],
                    'equipid' => (int)$row['equipid'],
                    'name' => (string)$row['name'],
                    'type' => (int)$row['type'],
                    'quality' => (int)$row['quality'],
                    'level' => (int)$row['level'],
                    'powerup' => (int)$row['powerup'],
                ];
            } else {
                $equip['eid' . $i] = [];
            }
            if (isset($newList[$eid]) && $equip['eid' . $i] !== []) {
                $equip['eid' . $i]['gem'] = $newList[$eid];
            }
        }
        $return['status'] = 1;
        $return['info'] = $equip;
        return $return;
    }

    /**
     * 他人将领的坐骑: c=information&m=get_general_mount（uid，gid）
     *
     * general 为将领（旧版 own_general 字段），mid0~mid4 有坐骑时换成坐骑信息；attack / wufang / cefang / hp 为坐骑加成合计
     * （主坐骑 mid0 全额，副坐骑 mid1~4 各 30% 向上取整）。
     */
    public static function get_general_mount(Context $ctx): array
    {
        /**
         * -2 获取将领信息失败
         * -1 参数错误
         *  1 成功
         */
        $return = ['status' => 0];
        $gid = (int)$ctx->input('gid', 0);
        $uid = (int)$ctx->input('uid', 0);
        if (!$gid || !$uid) {
            $return['status'] = -1;
            return $return;
        }
        // 查询这个将领的信息
        $general = GeneralHandler::ownLegacyList($uid, $gid)[0] ?? null;
        if ($general === null) {
            $return['status'] = -2;
            return $return;
        }
        // 获得要查询的坐骑列表
        $midarr = [];
        for ($i = 0; $i <= 4; $i++) {
            if ($general['mid' . $i] > 0) {
                $midarr[$i] = $general['mid' . $i];
            }
        }
        $list = $midarr === [] ? [] : OwnMount::infoById($uid, array_values($midarr));
        $attack = $wufang = $cefang = $hp = 0;
        for ($i = 0; $i <= 4; $i++) {
            if ($general['mid' . $i] > 0 && !empty($list[$midarr[$i]])) {
                $row = OwnMount::legacyRow($list[$midarr[$i]]);
                $mount = [];
                foreach (['id', 'name', 'mid', 'quality', 'level', 'attack', 'wufang', 'cefang', 'hp'] as $val) {
                    $mount[$val] = $row[$val];
                }
                $general['mid' . $i] = $mount;
                if ($i === 0) {
                    $attack += $mount['attack'];
                    $wufang += $mount['wufang'];
                    $cefang += $mount['cefang'];
                    $hp += $mount['hp'];
                } else {
                    $attack += (int)ceil($mount['attack'] * 0.3);
                    $wufang += (int)ceil($mount['wufang'] * 0.3);
                    $cefang += (int)ceil($mount['cefang'] * 0.3);
                    $hp += (int)ceil($mount['hp'] * 0.3);
                }
            }
        }
        $general['attack'] = $attack;
        $general['wufang'] = $wufang;
        $general['cefang'] = $cefang;
        $general['hp'] = $hp;
        $return['status'] = 1;
        $return['general'] = $general;
        return $return;
    }

    /**
     * 他人的国家学院: c=information&m=get_country_info（uid）
     *
     * 对方有国家时：skill_list 为国家学院各技能（img_id / skill_id / skill_level），skill_buffer.addition 为各项加成（闪避 / 识破 /
     * 命中 / 精准带 %）。旧版判断写成「国家存在 || 国家 30 级」，未满 30 级的国家同样列出（并建档国家技能），照旧版。
     * 加入国家满 3 天 is_open 为 1，否则 cd 为剩余秒数。
     * 没有国家时全为空 / 0。
     */
    public static function get_country_info(Context $ctx): array
    {
        /**
         * -1 参数错误
         *  1 成功
         */
        $return = ['status' => 0];
        $uid = (int)$ctx->input('uid', 0);
        if ($uid <= 0) {
            $return['status'] = -1;
            // 缺少参数
            $return['msg'] = self::LANG_TYPE_ERROR;
            return $return;
        }
        // 如果玩家没有国家信息 或者国家学院没开启返回空值
        $returnInfo = $skillBuff = [];
        $isOpen = $cd = 0;
        // 获取国家信息
        $country = CountryHandler::memberCountry($uid);
        if ($country !== null && (int)$country['country'] > 0) {
            if (CountryHandler::countryInfo((int)$country['country']) !== null) {
                // 获取技能列表
                $skillInfo = WarCollege::countrySkillInfo((int)$country['country']);
                foreach ($skillInfo as $key => $val) {
                    $returnInfo[$key] = ['img_id' => $val['img_id'], 'skill_id' => $val['skill_type'], 'skill_level' => $val['skill_level']];
                }
                foreach ($skillInfo as $key => $value) {
                    // 闪避率 识破率 命中 精准带 %（旧版按列表下标 + 1 取加成名称）
                    $name = WarCollege::TYPE[$key + 1] ?? null;
                    if ($name !== null) {
                        $skillBuff['addition'][$name] = in_array($value['skill_type'], [8, 9, 11, 10], true)
                            ? $value['skill_value'] . '%'
                            : (int)$value['skill_value'];
                    }
                }
            }
            $skillOpen = 259200;
            $now = time();
            if ($now - (int)$country['joindateline'] >= $skillOpen) {
                $isOpen = 1;
            } else {
                $cd = $skillOpen - ($now - (int)$country['joindateline']);
            }
        }
        $return['status'] = 1;
        $return['skill_list'] = $returnInfo;
        $return['skill_buffer'] = $skillBuff;
        $return['is_open'] = $isOpen;
        $return['cd'] = $cd;
        return $return;
    }

    /**
     * 他人的星魄: c=information&m=get_star_info（uid）
     *
     * list：[页][1~10 号位] 命格（id / name / level / max_level / line / open_status）；已点亮的命格取玩家等级，未点亮的
     * 只列 1 级命格（level 0、open_status 0）。第 4 列命格最高 1 级，其余 5 级。
     */
    public static function get_star_info(Context $ctx): array
    {
        $return = ['status' => 0, 'list' => []];
        $uid = (int)$ctx->input('uid', 0);
        if ($uid <= 0) {
            $return['status'] = -1;
            // 缺少参数
            $return['msg'] = self::LANG_STAR_INIT_ERROR;
            return $return;
        }
        // 获取用户的所有命格信息
        $treeList = OwnStar::tree($uid);
        $gridList = OwnStar::grid();
        if ($gridList === []) {
            $return['status'] = -1;
            $return['msg'] = self::LANG_STAR_INIT_ERROR;
            return $return;
        }
        $grid = [];
        foreach ($gridList as $val) {
            $id = (int)$val['id'] % 10;
            if ($id === 0) {
                $id = 10;
            }
            $page = (int)$val['page'];
            $maxLevel = (int)$val['line'] === 4 ? 1 : self::TREE_MAX_LEVEL;
            if (isset($treeList[(int)$val['id']])) {
                $grid[$page][$id] = [
                    'id' => (int)$val['id'],
                    'name' => (string)$val['name'],
                    'level' => $treeList[(int)$val['id']]['level'],
                    'max_level' => $maxLevel,
                    'line' => (int)$val['line'],
                    'open_status' => 1,
                ];
            } elseif ((int)$val['level'] === 1) {
                $grid[$page][$id] = [
                    'id' => (int)$val['id'],
                    'name' => (string)$val['name'],
                    'level' => 0,
                    'max_level' => $maxLevel,
                    'line' => (int)$val['line'],
                    'open_status' => 0,
                ];
            }
        }
        $return['status'] = 1;
        $return['list'] = $grid;
        return $return;
    }
}
