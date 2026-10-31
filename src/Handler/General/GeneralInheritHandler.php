<?php

declare(strict_types=1);

namespace Hanjiang\Handler\General;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\MasterData\GeneralInherit;
use Hanjiang\Model\General;
use Hanjiang\Model\MainQuestTask;
use Hanjiang\Model\Member;
use Hanjiang\Model\Practice;
use Hanjiang\Model\StatPay;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 武将继承（移植旧版 controllers/general_inherit.php + general_model 的继承部分）
 *
 * 继承规则取 MasterData GeneralInherit（普通 / 加强 / 白金 / 至尊：经验 %、属性 %、价格系数）。VIP 0 只能普通、1 ~ 3 到加强、
 * 4 ~ 7 到白金、8 起至尊。元宝 = round(max(1, 武将1总经验 ÷ (主公等级 × 562.5)) × 价格系数)，VIP 7 起半价。
 * 免费继承：VIP 2 ~ 3 普通、4 ~ 5 加强、6 ~ 7 白金、8 起至尊，每 30 天一次（member_field.free_inherit）；VIP 10 起赠送一次至尊
 * （member_field.inherit_dateline 为 0 时可用）；活动赠送的次数在 inherit_times（inherit_1 ~ 4）。
 * 继承后武将1 重置为 1 级 0 转、培养属性清零；武将2 得到 总经验 × 经验% + 自身经验（可自动转生，不超过主公等级），
 * 培养属性取 max(武将1 × 属性%, 武将2)。记 inherit_log。
 * 客户端 inheritpop.js 调用 index、get_inherit_info（gid）、preview / go_inherit（gid1、gid2、type、confirm）。
 *
 * 依赖未移植：老兵召回活动的免费至尊继承（recall，ActivityTime 中 2016-12-31 已结束；actveteran_model 未移植，按活动未开启处理）。
 * 旧版 index 的首次打开记录 edifice_model->add_edifice_log(4) 只写日志，不移植。
 */
final class GeneralInheritHandler
{
    /** 继承cd时间（30天） */
    private const int INHERIT_DATE = 2592000;

    /** 开启等级 */
    private const int OPEN_LEVEL = 26;

    /**
     * 初始化接口: c=general_inherit&m=index
     */
    public static function index(Context $ctx): array
    {
        /**
         * -1 获取继承规则失败
         *  0 失败
         *  1 成功
         *  2 没有出征武将
         *  3 主公等级不足
         */
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        $uid = (int)$member['uid'];
        if ((int)$member['level'] < self::OPEN_LEVEL) {
            // 等级不足
            $return['status'] = 3;
            return $return;
        }
        // [将领model]获取出征武将列表
        $list = General::battleList($uid);
        if ($list === []) {
            // 没有出征武将
            $return['status'] = 2;
            return $return;
        }
        $listArr = [];
        foreach ($list as $key => $value) {
            $listArr[$key] = [
                'id' => (int)$value['id'],
                'name' => (string)$value['name'],
                'quality' => (int)$value['quality'],
                'image' => (int)$value['image'],
                'level' => (int)$value['level'],
                'turn' => (int)$value['turn'],
            ];
        }
        $rule = self::vipRule((int)$member['vip']);
        $ruleArr = [];
        foreach ($rule as $key => $value) {
            $ruleArr[$key + 1] = [
                'id' => $value['id'],
                'name' => $value['name'],
                'gold' => 0,
                'exp' => $value['exp'],
                'attr' => $value['attr'],
            ];
        }
        $field = self::field($uid);
        $now = time();
        $endTime = max(0, (int)$field['free_inherit'] + self::INHERIT_DATE - $now);
        // 使用赠送的免费至尊继承
        $freeSite = 0;
        if ((int)$member['vip'] >= 10 && (int)$field['inherit_dateline'] === 0) {
            $freeSite = 4;
        }
        // 查询免费继承次数
        $inheritTime = self::inheritTimes($uid);
        if ($freeSite > 0) {
            $inheritTime['inherit_' . $freeSite] += 1;
        }
        unset($inheritTime['uid']);
        $return['status'] = 1;
        $return['list'] = $listArr;
        $return['rule'] = $ruleArr;
        $return['endtime'] = $endTime;
        $return['free_inherit'] = $inheritTime;
        $return['free_site'] = $freeSite;
        return $return;
    }

    /**
     * 获取继承信息: c=general_inherit&m=get_inherit_info（gid 被继承的武将）
     */
    public static function get_inherit_info(Context $ctx): array
    {
        /**
         * -2 获取将领信息失败
         * -1 获取继承规则失败
         *  0 失败
         *  1 成功
         *  2 将领未出征
         *  3 主公等级不足
         */
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        $uid = (int)$member['uid'];
        $gid = (int)$ctx->input('gid', 0);
        $vip = (int)$member['vip'];
        if ((int)$member['level'] < self::OPEN_LEVEL) {
            // 主公等级不足
            $return['status'] = 3;
            return $return;
        }
        $now = time();
        $field = self::field($uid);
        // 获取继承cd
        $inheritCd = max(0, (int)$field['free_inherit'] + self::INHERIT_DATE - $now);
        $rule = self::vipRule($vip);
        if ($vip >= 2 && $vip < 4 && $inheritCd === 0) {
            // 每隔30天可使用一次免费普通继承
            $rule[0]['price'] = 0;
        } elseif ($vip >= 4 && $vip < 6 && $inheritCd === 0) {
            // 每隔30天可使用一次免费加强继承
            $rule[1]['price'] = 0;
        } elseif ($vip >= 6 && $vip < 8 && $inheritCd === 0) {
            // 每隔30天可使用一次免费白金继承
            $rule[2]['price'] = 0;
        } elseif ($vip >= 8 && $inheritCd === 0) {
            // 每30天可使用一次免费至尊继承
            $rule[3]['price'] = 0;
        }
        // 使用赠送的免费至尊继承
        if ($vip >= 10 && (int)$field['inherit_dateline'] === 0) {
            $rule[3]['price'] = 0;
        }
        // 使用活动赠送的免费继承
        $inheritTime = self::inheritTimes($uid);
        for ($i = 1; $i <= 4; $i++) {
            if ($inheritTime['inherit_' . $i] > 0) {
                $rule[$i - 1]['price'] = 0;
            }
        }
        // 更新训练位
        Practice::updatePractice($uid, $now);
        // [将领model]拥有将领id(gid)
        $general = General::own($uid, $gid);
        if ($general === null) {
            // 获取将领信息失败
            $return['status'] = -2;
            return $return;
        }
        if ((int)$general['goout'] !== 1) {
            // 将领未出征
            $return['status'] = 2;
            return $return;
        }
        // 获取总经验值
        $allexp = General::totalExp((int)$general['turn'], (int)$general['level'], (int)$general['exp']);
        // 金币花费=round(当前武将经验总值/当前主公等级突飞经验，0)*突飞价格系数
        $gold = $allexp / ((int)$member['level'] * 562.5);
        $gold = $gold > 1 ? $gold : 1;
        $ruleArr = [];
        ksort($rule);
        foreach ($rule as $key => $value) {
            if (!isset($value['id'])) {
                // 旧版低 VIP 时免费价格写进了不存在的规则位（只有 price），遇到即停止
                break;
            }
            $ruleArr[$key + 1] = [
                'id' => $value['id'],
                'name' => $value['name'],
                // VIP7以上半价
                'gold' => self::legacyNumber($vip >= 7 ? round($gold * $value['price'] / 2, 0) : round($gold * $value['price'], 0)),
                'exp' => $value['exp'],
                'attr' => $value['attr'],
                'allexp' => (int)($allexp * $value['exp'] / 100),
            ];
        }
        $return['status'] = 1;
        $return['rule'] = $ruleArr;
        return $return;
    }

    /**
     * 预览: c=general_inherit&m=preview（gid1 被继承、gid2 继承、type 规则）
     */
    public static function preview(Context $ctx): array
    {
        /**
         * -4 vip等级不足
         * -3 获取将领信息失败
         * -2 获取继承规则失败
         * -1 将领id重复
         *  0 失败
         *  1 成功
         *  2 将领未出征
         *  3 主公等级不足
         */
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        $uid = (int)$member['uid'];
        $gid1 = (int)$ctx->input('gid1', 0);
        $gid2 = (int)$ctx->input('gid2', 0);
        $type = (int)$ctx->input('type', 0);
        if ((int)$member['level'] < self::OPEN_LEVEL) {
            // 等级不足
            $return['status'] = 3;
            return $return;
        }
        if ($gid1 === $gid2) {
            // 将领id重复
            $return['status'] = -1;
            return $return;
        }
        if (self::vipError((int)$member['vip'], $type)) {
            // vip等级不足
            $return['status'] = -4;
            return $return;
        }
        // [将领model]获取继承规则
        $rule = GeneralInherit::ROWS[$type] ?? null;
        if ($rule === null) {
            // 获取继承规则失败
            $return['status'] = -2;
            return $return;
        }
        // 旧版这里把免费价格写进 $rule[0] ~ $rule[3]（单条规则没有这些下标），不影响 $rule['price']，预览的元宝不打折也不免费
        $generals = self::twoGenerals($uid, $gid1, $gid2);
        if ($generals === null) {
            // 获取将领信息失败
            $return['status'] = -3;
            return $return;
        }
        $fields = ['id', 'type', 'name', 'generalid', 'image', 'level', 'exp', 'levelupexp', 'wuli', 'zhili', 'tili', 'wuliup', 'zhiliup', 'tiliup', 'turn'];
        $generalArr = [];
        foreach ($generals as $key => $value) {
            foreach ($fields as $v) {
                $generalArr[$key][$v] = $v === 'name' ? (string)$value[$v] : (int)$value[$v];
            }
        }
        if ((int)$generals[$gid1]['goout'] !== 1 || (int)$generals[$gid2]['goout'] !== 1) {
            // 将领未出征
            $return['status'] = 2;
            return $return;
        }
        $allexp1 = General::totalExp($generalArr[$gid1]['turn'], $generalArr[$gid1]['level'], $generalArr[$gid1]['exp']);
        // 金币花费=round(当前武将经验总值/当前主公等级突飞经验，0)*突飞价格系数
        $gold = round($allexp1 / ((int)$member['level'] * 562.5) * $rule['price'], 0);
        $return['disable'] = (int)$member['gold'] < $gold ? 1 : 0;
        $allexp2 = round($allexp1 * $rule['exp'] / 100 + $generalArr[$gid2]['exp'], 0);
        // [将领model]继承
        $result = General::inheritLevelup((int)$member['level'], $generalArr[$gid2]['turn'], $generalArr[$gid2]['level'], $allexp2);
        $return['before'] = [$generalArr[$gid1], $generalArr[$gid2]];
        $general1 = array_merge($generalArr[$gid1], self::RESET);
        $general2 = array_merge($generalArr[$gid2], [
            'level' => $result['level'],
            'levelupexp' => $result['levelupexp'],
            'exp' => self::legacyNumber($result['exp']),
            'turn' => $result['turn'] ?? 0,
            'wuliup' => self::attr($generalArr[$gid1]['wuliup'], $generalArr[$gid2]['wuliup'], $rule['attr']),
            'zhiliup' => self::attr($generalArr[$gid1]['zhiliup'], $generalArr[$gid2]['zhiliup'], $rule['attr']),
            'tiliup' => self::attr($generalArr[$gid1]['tiliup'], $generalArr[$gid2]['tiliup'], $rule['attr']),
        ]);
        $return['after'] = [$general1, $general2];
        $return['status'] = 1;
        return $return;
    }

    /**
     * 继承: c=general_inherit&m=go_inherit（gid1、gid2、type、confirm；confirm 为 0 时只返回 max）
     */
    public static function go_inherit(Context $ctx): array
    {
        /**
         * -7 消耗免费继承次数失败
         * -6 更新将领2信息失败
         * -5 vip等级不足
         * -4 更新将领信息失败
         * -3 获取将领信息失败
         * -2 获取继承规则失败
         * -1 将领id重复
         *  0 失败
         *  1 成功
         *  2 将领未出证
         *  3 元宝不足
         *  4 主公等级不足
         *  5 将领等级不足
         */
        $uid = MemberHandler::resolveUid($ctx);
        $gid1 = (int)$ctx->input('gid1', 0);
        $gid2 = (int)$ctx->input('gid2', 0);
        $type = (int)$ctx->input('type', 0);
        $confirm = (int)$ctx->input('confirm', 0);
        if (self::member($uid) === null) {
            return ['status' => 0];
        }
        $now = time();
        return Member::locked($uid, static function () use ($uid, $gid1, $gid2, $type, $confirm, $now): array {
            $return = ['status' => 0];
            $member = self::member($uid);
            $vip = (int)$member['vip'];
            if ((int)$member['level'] < self::OPEN_LEVEL) {
                // 等级不足
                $return['status'] = 4;
                return $return;
            }
            if ($gid1 === $gid2) {
                // 将领id重复
                $return['status'] = -1;
                return $return;
            }
            if (self::vipError($vip, $type)) {
                // vip等级不足
                $return['status'] = -5;
                return $return;
            }
            // [将领model]获取继承规则
            $rule = GeneralInherit::ROWS[$type] ?? null;
            if ($rule === null) {
                // 获取继承规则失败
                $return['status'] = -2;
                return $return;
            }
            $field = self::field($uid);
            // 判断是否可使用赠送的免费至尊继承
            $useFree = 0;
            $freeSite = 0;
            if ($vip >= 10 && (int)$field['inherit_dateline'] === 0) {
                $useFree = 1;
                $freeSite = 4;
            }
            // 获取继承cd
            $inheritCd = max(0, (int)$field['free_inherit'] + self::INHERIT_DATE - $now);
            $cdLeft = static fn (): int => max(0, (int)$field['free_inherit'] + self::INHERIT_DATE - $now);
            // 查询免费继承剩余次数
            $endTime = 0;
            $freeUpdate = null;
            $inheritTimes = self::inheritTimes($uid);
            if (($inheritTimes['inherit_' . $type] ?? 0) > 0) {
                // 使用免费次数
                $rule['price'] = 0;
                if ($confirm !== 0) {
                    // 消耗用户免费继承次数
                    if (!self::subInheritTime($uid, $type)) {
                        $return['status'] = -7;
                        return $return;
                    }
                    $inheritTimes['inherit_' . $type] -= 1;
                    // 获取倒计时
                    $endTime = $cdLeft();
                }
            } elseif (($type === 1 && $vip >= 2 && $vip < 4 && $inheritCd === 0)
                || ($type === 2 && $vip >= 4 && $vip < 6 && $inheritCd === 0)
                || ($type === 3 && $vip >= 6 && $vip < 8 && $inheritCd === 0)) {
                // 每30天可使用一次免费普通 / 加强 / 白金继承
                $rule['price'] = 0;
                if ($confirm !== 0) {
                    // 修改用户使用免费继承时间
                    $freeUpdate = ['free_inherit' => $now];
                    $endTime = self::INHERIT_DATE;
                }
            } elseif ($type === 4 && $useFree === 1) {
                // 使用免费赠送的至尊继承
                $rule['price'] = 0;
                if ($confirm !== 0) {
                    $freeSite = 0;
                    // 修改用户使用赠送继承时间
                    $freeUpdate = ['inherit_dateline' => $now];
                    $endTime = $cdLeft();
                }
            } elseif ($type === 4 && $vip >= 8 && $inheritCd === 0) {
                // 每30天可使用一次免费至尊继承
                $rule['price'] = 0;
                if ($confirm !== 0) {
                    $freeUpdate = ['free_inherit' => $now];
                    $endTime = self::INHERIT_DATE;
                }
            } elseif ($vip >= 2) {
                // 没有使用免费继承也要获取倒计时
                $endTime = $cdLeft();
            }
            if ($freeUpdate !== null) {
                DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->update($freeUpdate);
            }

            // [将领model]拥有将领id(gid)
            $generals = self::twoGenerals($uid, $gid1, $gid2);
            if ($generals === null) {
                // 获取将领信息失败
                $return['status'] = -3;
                return $return;
            }
            $g1 = $generals[$gid1];
            $g2 = $generals[$gid2];
            if ((int)$g1['goout'] !== 1 || (int)$g2['goout'] !== 1) {
                // 将领未出征
                $return['status'] = 2;
                return $return;
            }
            if ((int)$g1['level'] < 2 && (int)$g1['turn'] === 0) {
                // 将领等级不足
                $return['status'] = 5;
                return $return;
            }
            // 获取总经验值
            $allexp = General::totalExp((int)$g1['turn'], (int)$g1['level'], (int)$g1['exp']);
            // 金币花费=round(当前武将经验总值/当前主公等级突飞经验，0)*突飞价格系数（VIP7继承半价）
            $gold = $allexp / ((int)$member['level'] * 562.5);
            $gold = $gold > 1 ? $gold : 1;
            $gold = (int)($vip >= 7 ? round($gold * $rule['price'] / 2, 0) : round($gold * $rule['price'], 0));
            if ((int)$member['gold'] < $gold) {
                // 元宝不足
                $return['status'] = 3;
                return $return;
            }
            $allexp = round($allexp * $rule['exp'] / 100 + (int)$g2['exp'], 0);
            // [将领model]继承
            $result = General::inheritLevelup((int)$member['level'], (int)$g2['turn'], (int)$g2['level'], $allexp);
            if ($confirm === 0) {
                $return['max'] = ($result['status'] ?? 0) === 2 ? 1 : 0;
                $return['status'] = 1;
                return $return;
            }
            // 更新将领信息
            $general1 = self::RESET;
            if (DB::table('general')->where('uid', $uid)->where('id', $gid1)->whereNull('deleted_at')->update($general1) <= 0) {
                // 更新将领信息失败
                $return['status'] = -4;
                return $return;
            }
            $general2 = [
                'level' => $result['level'],
                'levelupexp' => $result['levelupexp'],
                'exp' => (int)$result['exp'],
                'turn' => $result['turn'] ?? 0,
                'wuliup' => self::attr((int)$g1['wuliup'], (int)$g2['wuliup'], $rule['attr']),
                'zhiliup' => self::attr((int)$g1['zhiliup'], (int)$g2['zhiliup'], $rule['attr']),
                'tiliup' => self::attr((int)$g1['tiliup'], (int)$g2['tiliup'], $rule['attr']),
            ];
            if (DB::table('general')->where('uid', $uid)->where('id', $gid2)->whereNull('deleted_at')->update($general2) <= 0) {
                // 更新将领信息失败
                $return['status'] = -6;
                return $return;
            }
            // 删除武将培养信息
            Redis::del('cultivate_' . $uid . '_' . $gid1);
            if (!Member::deductCurrency($uid, gold: $gold)) {
                // 扣除金币失败（旧版此时将领已经更新，照旧版返回 -5）
                $return['status'] = -5;
                return $return;
            }
            // 消费统计记录
            StatPay::record($uid, 'general_inherit', $gold);
            $return['status'] = 1;
            $return['gold'] = (int)self::member($uid)['gold'];
            $return['info'] = [
                $gid1 => ['level' => $general1['level'], 'turn' => $general1['turn']],
                $gid2 => ['level' => $general2['level'], 'turn' => $general2['turn']],
            ];
            self::addInheritLog($member, $type, $gold, (int)$allexp, $g1, $general1, $g2, $general2, $now);
            $return['endtime'] = $endTime;
            $return['free_site'] = $freeSite;
            $return['free_inherit'] = $inheritTimes;
            // 主线任务 3任意武将升级到x级
            MainQuestTask::action($uid, 3, $general2['level']);
            // 主线任务 4任意武将全属性洗到x点
            MainQuestTask::action($uid, 4, min($general2['wuliup'], $general2['zhiliup'], $general2['tiliup']));
            Redis::del('player:' . $uid);
            return $return;
        });
    }

    /** 被继承武将重置后的数值 */
    private const array RESET = [
        'level' => 1,
        'levelupexp' => 80,
        'exp' => 0,
        'wuliup' => 0,
        'zhiliup' => 0,
        'tiliup' => 0,
        'turn' => 0,
    ];

    /**
     * 按 VIP 可用的继承规则（VIP 0 只有普通，1 ~ 3 到加强，4 ~ 7 到白金，8 起全部）
     *
     * @return array<int, array<string, mixed>>
     */
    private static function vipRule(int $vip): array
    {
        $rule = array_values(GeneralInherit::ROWS);
        if ($vip < 1) {
            return [$rule[0]];
        }
        if ($vip < 4) {
            return [$rule[0], $rule[1]];
        }
        if ($vip < 8) {
            return [$rule[0], $rule[1], $rule[2]];
        }
        return $rule;
    }

    private static function vipError(int $vip, int $type): bool
    {
        return ($vip < 1 && $type > 1) || ($vip >= 1 && $vip < 4 && $type > 2) || ($vip >= 4 && $vip < 8 && $type > 3);
    }

    /**
     * 培养属性继承（_get_attr：max(武将1 × 属性%, 武将2)）
     */
    private static function attr(int $attr1, int $attr2, int $ratio): int
    {
        $attr1 = $attr1 * $ratio / 100;
        return (int)($attr1 > $attr2 ? $attr1 : $attr2);
    }

    /**
     * 两个拥有将领（get_own_by_id 数组：两个都在才算成功）
     *
     * @return array<int, array<string, mixed>>|null
     */
    private static function twoGenerals(int $uid, int $gid1, int $gid2): ?array
    {
        $rows = DB::table('general')->where('uid', $uid)->whereIn('id', [$gid1, $gid2])->whereNull('deleted_at')->get();
        if (count($rows) !== 2) {
            return null;
        }
        $return = [];
        foreach ($rows as $row) {
            $return[(int)$row['id']] = $row;
        }
        return $return;
    }

    /**
     * 免费继承次数（get_inherit_times：没有记录时建档）
     *
     * @return array{inherit_1: int, inherit_2: int, inherit_3: int, inherit_4: int, uid: int}
     */
    private static function inheritTimes(int $uid): array
    {
        DB::execute('INSERT IGNORE INTO `zc_sanguo_inherit_times` (`uid`) VALUES (:uid)', ['uid' => $uid]);
        $row = DB::table('inherit_times')->select('inherit_1', 'inherit_2', 'inherit_3', 'inherit_4', 'uid')
            ->where('uid', $uid)->whereNull('deleted_at')->first() ?? [];
        return [
            'inherit_1' => (int)($row['inherit_1'] ?? 0),
            'inherit_2' => (int)($row['inherit_2'] ?? 0),
            'inherit_3' => (int)($row['inherit_3'] ?? 0),
            'inherit_4' => (int)($row['inherit_4'] ?? 0),
            'uid' => $uid,
        ];
    }

    /**
     * 消耗免费继承次数（sub_inherit_time）
     */
    private static function subInheritTime(int $uid, int $type): bool
    {
        $col = 'inherit_' . $type;
        return DB::execute(
            "UPDATE `zc_sanguo_inherit_times` SET `{$col}` = `{$col}` - 1 WHERE `uid` = :uid AND `{$col}` > 0 AND `deleted_at` IS NULL",
            ['uid' => $uid]
        ) > 0;
    }

    /**
     * 武将继承记录（add_inherit_log）
     *
     * @param array<string, mixed> $member
     * @param array<string, mixed> $g1Before
     * @param array<string, mixed> $g1After
     * @param array<string, mixed> $g2Before
     * @param array<string, mixed> $g2After
     */
    private static function addInheritLog(array $member, int $type, int $gold, int $addexp, array $g1Before, array $g1After, array $g2Before, array $g2After, int $now): void
    {
        $data = [
            'uid' => (int)$member['uid'],
            'level' => (int)$member['level'],
            'vip' => (int)$member['vip'],
            'dateline' => $now,
            'type' => $type,
            'cost' => $gold,
            'addexp' => $addexp,
            'gid1' => (int)$g1Before['id'],
            'gid2' => (int)$g2Before['id'],
        ];
        foreach (['g1' => [$g1Before, $g1After], 'g2' => [$g2Before, $g2After]] as $prefix => [$before, $after]) {
            foreach (['turn', 'level', 'exp', 'levelupexp', 'wuliup', 'zhiliup', 'tiliup'] as $key) {
                $data[$prefix . '_' . $key . '1'] = (int)$before[$key];
                $data[$prefix . '_' . $key . '2'] = (int)$after[$key];
            }
        }
        DB::table('inherit_log')->insert($data);
    }

    /**
     * 旧版 PHP 的 round() 返回浮点，json_encode 输出时整数值不带小数点
     */
    private static function legacyNumber(int|float $value): int|float
    {
        return is_float($value) && $value === floor($value) ? (int)$value : $value;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function member(int $uid): ?array
    {
        return $uid > 0 ? DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first() : null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function field(int $uid): array
    {
        return DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->first() ?? ['free_inherit' => 0, 'inherit_dateline' => 0];
    }
}
