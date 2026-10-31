<?php

declare(strict_types=1);

namespace Hanjiang\Handler\General;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\MasterData\GeneralCulture;
use Hanjiang\Model\DailyTask;
use Hanjiang\Model\Expostulation;
use Hanjiang\Model\General;
use Hanjiang\Model\MainQuestTask;
use Hanjiang\Model\Member;
use Hanjiang\Model\StatPay;
use Hanjiang\Model\Vip;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 武将培养（洗属性；移植旧版 controllers/cultivate.php + general_model 的 cultivate_rule / get_rule_by_id / cultivate /
 * get_roll_by_attr / update_times / update_addition）
 *
 * 培养规则取 MasterData GeneralCulture：1 普通（银币 = 基数 × 主公等级）、2 加强（固定元宝）、3 白金 / 4 至尊（元宝 = 基数 +
 * 递增 × 今日次数，member_field.baijintimes / zhizuntimes，每日计划任务归零）。可用模式由 VIP 决定（vip_model->culturelevel：
 * VIP 3 以下为 2）。每次培养把三项培养属性按权重各 -6 ~ +6（低于基础加成时只加不减，上限为将领等级 + 20），结果先存 Redis
 * cultivate_{uid}_{gid}（24 小时），保存时写入将领并记 cultivate_log，放弃时删除。
 * 客户端 fosterpop.js 调用 index、roll（gid、mode）、save（gid）、giveup（gid）。
 *
 * 培养在玩家行锁里执行（白金 / 至尊的递增价格与扣费不会被并发绕过）。
 */
final class CultivateHandler
{
    private const string ATTR_KEY = 'cultivate_';

    private const int CACHE_TTL = 86400;

    /**
     * 初始化接口: c=cultivate&m=index
     */
    public static function index(Context $ctx): array
    {
        /**
         * 定义接口返回
         * -1 规则表数据异常
         *  0 失败
         *  1 成功
         *  2 没有出征将领
         */
        $return = ['status' => 0, 'rule' => [], 'list' => []];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        $uid = (int)$member['uid'];
        // [将领模型]获得出征将领列表(uid)
        $list = General::battleList($uid);
        // vip限制
        $maxCultivate = self::cultureLevel($uid);
        if ($list === []) {
            $return['status'] = 2;
            return $return;
        }
        // [将领模型]获取培养规则：只输出 id、name 与不为 0 的 silver / gold
        $field = self::field($uid);
        $rule = [];
        foreach (array_values(GeneralCulture::ROWS) as $key => $value) {
            $value = self::cost($value, (int)$member['level'], $field);
            if ($value['id'] <= $maxCultivate) {
                $rule[$key + 1] = ['id' => $value['id'], 'name' => $value['name']];
                if ($value['silver'] !== 0) {
                    $rule[$key + 1]['silver'] = $value['silver'];
                }
                if ($value['gold'] !== 0) {
                    $rule[$key + 1]['gold'] = $value['gold'];
                }
            }
        }
        $return['rule'] = $rule;
        $listArr = [];
        foreach ($list as $key => $value) {
            foreach (['id', 'name', 'quality', 'level', 'image', 'type', 'wuli', 'zhili', 'tili', 'wuliup', 'zhiliup', 'tiliup', 'star', 'goout'] as $v) {
                $listArr[$key][$v] = $v === 'name' ? (string)$value[$v] : (int)$value[$v];
            }
            // 检查redis中是否有此将领的信息
            $listArr[$key]['cultivate'] = self::cache($uid, (int)$value['id']) ?? [];
        }
        $return['list'] = $listArr;
        $return['status'] = 1;
        return $return;
    }

    /**
     * 洗属性: c=cultivate&m=roll（gid、mode 1 ~ 4）
     */
    public static function roll(Context $ctx): array
    {
        /**
         * 接口返回定义
         * -8 redis缓存失败
         * -7 获取下次花费失败
         * -6 用户扩展表异常
         * -5 未知错误
         * -4 规则丢失
         * -3 花费计算失败
         * -2 将领不存在
         * -1 模式不存在
         *  0 失败
         *  1 成功
         *  2 将领不在出征列表中
         *  3 银币不足
         *  4 金币不足
         *  5 vip等级不足
         */
        $uid = MemberHandler::resolveUid($ctx);
        $gid = (int)$ctx->input('gid', 0);
        $mode = (int)$ctx->input('mode', 0);
        if (self::member($uid) === null) {
            return ['status' => 0, 'info' => []];
        }
        $now = time();
        return Member::locked($uid, static function () use ($uid, $gid, $mode, $now): array {
            $return = ['status' => 0, 'info' => []];
            $member = self::member($uid);
            if ($mode > self::cultureLevel($uid)) {
                // vip等级不足 5
                $return['status'] = 5;
                return $return;
            }
            $row = GeneralCulture::ROWS[$mode] ?? null;
            if ($row === null) {
                // 模式不存在
                $return['status'] = -1;
                return $return;
            }
            $field = self::field($uid);
            $rule = self::cost($row, (int)$member['level'], $field);
            if ($mode === 1 && (int)$member['silver'] < $rule['silver']) {
                // 银币不足
                $return['status'] = 3;
                return $return;
            }
            if ($mode > 1 && (int)$member['gold'] < $rule['gold']) {
                // 金币不足
                $return['status'] = 4;
                return $return;
            }
            // [将领模型]获得培养加成（general_model->cultivate）
            $general = General::own($uid, $gid);
            if ($general === null) {
                // 将领不存在
                $return['status'] = -2;
                return $return;
            }
            if ((int)$general['goout'] !== 1) {
                // 将领未出征
                $return['status'] = 2;
                return $return;
            }
            $level = (int)$general['level'];
            $info = [
                'gid' => $gid,
                'wuliup' => self::rollAttr($rule, (int)$general['wuliup'], $level),
                'zhiliup' => self::rollAttr($rule, (int)$general['zhiliup'], $level),
                'tiliup' => self::rollAttr($rule, (int)$general['tiliup'], $level),
            ];
            // 花费：金币花费优先（规则里 silver / gold 都为 0 时为花费计算失败）
            $silver = $gold = 0;
            if ($rule['gold'] > 0) {
                $gold = $rule['gold'];
            } elseif ($rule['silver'] > 0) {
                $silver = $rule['silver'];
            } else {
                $return['status'] = -3;
                return $return;
            }
            // 扣除银币花费
            if ($silver > 0 && !Member::deductCurrency($uid, silver: $silver)) {
                // 银币不足
                $return['status'] = 3;
                return $return;
            }
            // 扣除金币花费
            if ($gold > 0) {
                if (!Member::deductCurrency($uid, gold: $gold)) {
                    // 金币不足
                    $return['status'] = 4;
                    return $return;
                }
                // 消费统计记录
                StatPay::record($uid, 'xishuxing', $gold);
            }
            // 按照培养模式更新member_field的白金/至尊字段,每次+1
            if ($mode === 3 || $mode === 4) {
                $col = $mode === 3 ? 'baijintimes' : 'zhizuntimes';
                if (DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->update([$col => (int)$field[$col] + 1]) <= 0) {
                    $return['status'] = -6;
                    return $return;
                }
                $field[$col] = (int)$field[$col] + 1;
            }
            // 获得下次花费
            $next = self::cost($row, (int)$member['level'], $field);
            $next = $mode === 1 ? $next['silver'] : $next['gold'];
            // 保存到redis中
            $info['type'] = $mode;
            if (!Redis::set(self::ATTR_KEY . $uid . '_' . $gid, $info, self::CACHE_TTL)) {
                // redis缓存失败 -8
                $return['status'] = -8;
                return $return;
            }
            $after = self::member($uid);
            $return['info'] = $info;
            $return['info']['gold'] = (int)$after['gold'];
            $return['info']['silver'] = (int)$after['silver'];
            $return['next'] = $next;
            $return['status'] = 1;

            // 日常任务(7是属性培养对应的类型)
            if ((int)$member['level'] >= DailyTask::OPEN_LEVEL) {
                DailyTask::action($uid, DailyTask::TYPE_CULTIVATE, 1, $now);
            }
            // 帮派任务(谏言：3是属性培养对应的类型)
            if ((int)$member['country'] > 0) {
                Expostulation::action((int)$member['country'], 3);
            }
            return $return;
        });
    }

    /**
     * 放弃: c=cultivate&m=giveup（gid）
     */
    public static function giveup(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $gid = (int)$ctx->input('gid', 0);
        Redis::del(self::ATTR_KEY . $uid . '_' . $gid);
        return ['status' => 1];
    }

    /**
     * 保存: c=cultivate&m=save（gid）
     */
    public static function save(Context $ctx): array
    {
        /**
         * 接口返回值
         * -4 获取缓存数据失败
         * -3 未知错误
         * -2 更新失败
         * -1 获取将领信息失败
         *  0 失败
         *  1 成功
         */
        $uid = MemberHandler::resolveUid($ctx);
        $gid = (int)$ctx->input('gid', 0);
        if (self::member($uid) === null) {
            return ['status' => 0];
        }
        $now = time();
        return Member::locked($uid, static function () use ($uid, $gid, $now): array {
            $return = ['status' => 0];
            // 检查redis中是否有此将领的信息
            $info = self::cache($uid, $gid);
            if ($info === null) {
                // 获取缓存失败 -4
                $return['status'] = -4;
                return $return;
            }
            // 更新将领附加属性（update_addition；旧版取缓存里的 gid）
            if ($info !== []) {
                $result = self::updateAddition($uid, $info, $now);
                if ($result !== 1 && $result !== 2) {
                    $return['status'] = $result === -1 ? -1 : ($result === -2 ? -2 : -3);
                    return $return;
                }
            }
            // 清除redis
            Redis::del(self::ATTR_KEY . $uid . '_' . $gid);
            // 主线任务 4任意武将全属性（武智体）洗到≥X点
            if ($info !== []) {
                MainQuestTask::action($uid, 4, min((int)$info['wuliup'], (int)$info['zhiliup'], (int)$info['tiliup']));
            }
            Redis::del('player:' . $uid);
            $return['info'] = $info;
            $return['status'] = 1;
            return $return;
        });
    }

    /**
     * 当次花费（cultivate_rule / get_rule_by_id：1 普通银币 × 主公等级；3 白金 / 4 至尊元宝 + 递增 × 今日次数）
     *
     * @param array<string, mixed> $row GeneralCulture 行
     * @param array<string, mixed> $field member_field
     * @return array<string, mixed>
     */
    private static function cost(array $row, int $level, array $field): array
    {
        switch ($row['id']) {
            case 1:
                $row['silver'] = $row['silver'] * $level;
                break;
            case 3:
                $row['gold'] = $row['gold'] + $row['addition'] * max(0, (int)($field['baijintimes'] ?? 0));
                break;
            case 4:
                $row['gold'] = $row['gold'] + $row['addition'] * max(0, (int)($field['zhizuntimes'] ?? 0));
                break;
        }
        return $row;
    }

    /**
     * 洗属性结果（get_roll_by_attr）
     *
     * @param array<string, mixed> $rule
     */
    private static function rollAttr(array $rule, int $attrup, int $level): int
    {
        // 设置洗属性上限
        $attrmax = $level + 20;
        // 如果加成小于等于基础值，只加不减
        $attrMin = $attrup <= $rule['baseaddition'] ? 1 : -6;
        $weight = static fn (int $i): int => $i <= 0 ? (int)$rule['attrsub' . abs($i)] : (int)$rule['attradd' . $i];
        $randMax = 0;
        for ($i = $attrMin; $i <= 6; $i++) {
            $randMax += $weight($i);
        }
        $rand = mt_rand(1, max(1, $randMax));
        $cursor = 0;
        $up = 6;
        for ($i = $attrMin; $i <= 6; $i++) {
            $cursor += $weight($i);
            $up = $i;
            if ($cursor >= $rand) {
                break;
            }
        }
        return min($attrup + $up, $attrmax);
    }

    /**
     * 更新将领附加属性（update_addition）
     *
     * @param array<string, mixed> $addition 缓存的培养结果
     * @return int 1 成功；2 属性不变；-1 获取将领失败；-2 更新失败
     */
    private static function updateAddition(int $uid, array $addition, int $now): int
    {
        $gid = (int)$addition['gid'];
        $general = General::own($uid, $gid);
        if ($general === null) {
            return -1;
        }
        $data = [];
        foreach (['wuliup', 'zhiliup', 'tiliup'] as $key) {
            if ((int)$general[$key] !== (int)$addition[$key]) {
                $data[$key] = (int)$addition[$key];
            }
        }
        if ($data === []) {
            // 属性不变 2
            return 2;
        }
        if (DB::table('general')->where('uid', $uid)->where('id', $gid)->whereNull('deleted_at')->update($data) <= 0) {
            // 更新失败 -2
            return -2;
        }
        DB::table('cultivate_log')->insert([
            'uid' => $uid,
            'gid' => $gid,
            'level' => (int)$general['level'],
            'type' => (int)($addition['type'] ?? 0),
            'wuliup1' => (int)$general['wuliup'],
            'zhiliup1' => (int)$general['zhiliup'],
            'tiliup1' => (int)$general['tiliup'],
            'wuliup2' => (int)$addition['wuliup'],
            'zhiliup2' => (int)$addition['zhiliup'],
            'tiliup2' => (int)$addition['tiliup'],
            'dateline' => $now,
        ]);
        return 1;
    }

    /**
     * 可用的培养模式（vip_model->culturelevel：没有 vip_log 或 VIP 3 以下为 2）
     */
    private static function cultureLevel(int $uid): int
    {
        $log = Vip::log($uid);
        if ($log === null || $log['level'] < 3) {
            return 2;
        }
        return (int)(Vip::info()[$log['level']]['culturelevel'] ?? 2);
    }

    /**
     * 缓存的培养结果；没有为 null
     *
     * @return array<string, mixed>|null
     */
    private static function cache(int $uid, int $gid): ?array
    {
        $cache = Redis::get(self::ATTR_KEY . $uid . '_' . $gid);
        return is_array($cache) ? $cache : null;
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
        return DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->first() ?? ['baijintimes' => 0, 'zhizuntimes' => 0];
    }
}
