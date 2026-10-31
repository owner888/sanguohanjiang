<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\MasterData\Stone;
use Hanjiang\MasterData\StoneRule;
use Hanjiang\Model\Chat;
use Hanjiang\Model\Member;
use Hanjiang\Model\MemberJade;
use Hanjiang\Model\StatPay;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 赌石（移植旧版 controllers/gambling.php + models/gambling_model.php）
 *
 * 客户端（slyzpop.js）调用：index / buy(id) / cut(id) / sell(id)。主公 91 级开启。三种原石取 MasterData StoneRule
 * （1 盘龙石银币 × 主公等级、2 仙王石 / 3 九窍灵石元宝），每种最多拥有一块（own_stone）。切石免费：第 N 刀按 cut_rate_N
 * 切出 N+1 品的珍稀宝石（MasterData Stone 按 rate 抽），否则按 broken_rate_N 切碎（第 4 刀不出宝石必碎），其余切小（估值
 * 变 value_N）。卖出得玉石（member.jade）：珍稀宝石按 Stone.value，原石按当前估值。
 * VIP 每日赠送免费原石：member_field.stone1~3_times（每日计划任务按 VIP 发 10 次，升 VIP 时 + 10，见 Member::dailyCron /
 * Vip::pay）；买 / 切碎 / 卖出时有免费次数就直接再拿一块。返回的 num / stoneN_times 在还有免费次数时把手上这块也算上（旧版如此）。
 * 切出 4 品以上宝石发小喇叭（gambling_accomplish）。
 *
 * 与旧版的差别：buy / cut / sell 在 Member::locked（玩家行锁）里执行；own_stone 软删除，(uid, 有效位置) 唯一。
 * 依赖未移植：春节送福活动计数（actnewyear_model->add_task_num 26，节日活动）。
 */
final class GamblingHandler
{
    /** 功能开启需要等级（旧版 $_need_level） */
    private const int NEED_LEVEL = 91;
    /** 小喇叭（gambling_lang） */
    private const string LANG_ACCOMPLISH = '恭喜%s赌石切出珍稀宝石【%s】 石行估价 %s 玉石';

    /**
     * 初始化接口: c=gambling&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = self::member($uid);
        $returnList = [];
        if ($member === null) {
            // 功能未开启
            return ['status' => -1, 'list' => $returnList];
        }
        $field = self::field($uid);
        // 取已拥有的宝石 及 状态
        $ownList = self::ownList($uid);
        foreach (StoneRule::ROWS as $rule) {
            $value = [];
            foreach (['id', 'name', 'buytype', 'price', 'description', 'trait', 'value_0', 'value_1', 'value_2', 'value_3'] as $f) {
                $value[$f] = $rule[$f];
            }
            $value['stoneid'] = $value['cut'] = 0;
            if ($value['buytype'] === 2) {
                // 银币购买
                $value['price'] = $value['price'] * (int)$member['level'];
            }
            $value['num'] = 0;
            foreach ($ownList as $v) {
                if ($value['id'] === $v['position']) {
                    $value['num'] = 1;
                    $value['cut'] = $v['cut'];
                    $value['value'] = $value['value_' . $v['cut']];
                    $value['price'] = $value['value'] = 0;
                    if ($v['stoneid']) {
                        $value['stoneid'] = $v['stoneid'];
                        $stone = Stone::ROWS[$v['stoneid']] ?? null;
                        $value['name'] = $stone['name'] ?? null;
                        $value['level'] = $stone['level'] ?? null;
                    }
                }
            }
            // VIP赠送的免费次数
            if ($field['stone' . $value['id'] . '_times'] > 0) {
                $value['num'] += $field['stone' . $value['id'] . '_times'];
            } else {
                $value['num'] = 0;
            }
            for ($i = 0; $i <= 3; $i++) {
                unset($value['value_' . $i]);
            }
            $id = $value['id'];
            unset($value['id']);
            $returnList[$id] = $value;
        }
        return [
            'status' => 1,
            'list' => $returnList,
            'jade' => MemberJade::get($uid),
        ];
    }

    /**
     * 购买: c=gambling&m=buy&id=1|2|3
     *
     * 3 银币不足；2 元宝不足；1 购买成功；-1 位置id不正确；-2 功能未开启
     */
    public static function buy(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $id = self::idInput($ctx);
        return Member::locked($uid, static function () use ($uid, $id): array {
            $return = ['status' => 0, 'info' => []];
            $member = self::member($uid);
            if ($member === null) {
                // 功能未开启
                $return['status'] = -2;
                return $return;
            }
            if ($id === 0) {
                // 没有选择要购买的宝石
                $return['status'] = -1;
                return $return;
            }
            $field = self::field($uid);
            // 检查是否已拥有该宝石
            $own = self::ownList($uid)[$id] ?? null;
            $cut = $stoneId = 0;
            if ($own === null) {
                // 获得宝石升级规则
                $rule = StoneRule::ROWS[$id];
                // 判断是否有VIP赠送的免费次数
                if ($field['stone' . $id . '_times'] > 0) {
                    // 扣除一次次数
                    if (!self::subFreeTimes($uid, $id)) {
                        $return['status'] = -2;
                        return $return;
                    }
                    $field['stone' . $id . '_times'] -= 1;
                } elseif ($rule['buytype'] === 1) {
                    // 没有该种宝石，购买（旧版先记付费点统计再判断扣款结果）
                    StatPay::record($uid, 'buystone', $rule['price']);
                    if (!Member::deductCurrency($uid, gold: $rule['price'])) {
                        // 扣元宝失败
                        $return['status'] = 2;
                        return $return;
                    }
                } elseif (!Member::deductCurrency($uid, silver: $rule['price'] * (int)$member['level'])) {
                    // 扣银币失败
                    $return['status'] = 3;
                    return $return;
                }
                // 插入到拥有宝石数据表
                self::addOwnStone($uid, $id);
                // 组织返回值
                $stoneValue = $rule['value_0'];
            } elseif ($own['stoneid']) {
                // 已经拥有该宝石：稀有宝石
                $stoneValue = Stone::ROWS[$own['stoneid']]['value'] ?? null;
                $stoneId = $own['stoneid'];
            } else {
                $cut = $own['cut'];
                $stoneValue = StoneRule::ROWS[$id]['value_' . $cut];
            }
            // 这里要把已经拥有的石头数量加上
            if ($field['stone' . $id . '_times'] > 0) {
                $field['stone' . $id . '_times'] += 1;
            }
            $after = DB::table('member')->select('gold', 'silver')->where('uid', $uid)->first();
            $return['status'] = 1;
            $return['info'] = ['cut' => $cut, 'value' => $stoneValue, 'stoneid' => $stoneId];
            $return['gold'] = (int)$after['gold'];
            $return['silver'] = (int)$after['silver'];
            $return['stone1_times'] = $field['stone1_times'];
            $return['stone2_times'] = $field['stone2_times'];
            $return['stone3_times'] = $field['stone3_times'];
            return $return;
        });
    }

    /**
     * 切割: c=gambling&m=cut&id=1|2|3
     *
     * -1 位置id不正确；-2 没有该种宝石；-3 珍稀宝石无法切割 / 功能未开启；1 切出珍稀宝石；2 切碎了；3 切小了
     */
    public static function cut(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $id = self::idInput($ctx);
        return Member::locked($uid, static function () use ($uid, $id): array {
            $return = ['status' => 0, 'info' => []];
            $now = time();
            $member = self::member($uid);
            if ($member === null) {
                // 功能未开启
                $return['status'] = -3;
                return $return;
            }
            if ($id === 0) {
                $return['status'] = -1;
                return $return;
            }
            // 检查是否已拥有该宝石
            $own = self::ownList($uid)[$id] ?? null;
            if ($own === null) {
                // 没有该宝石
                $return['status'] = -2;
                return $return;
            }
            if ($own['stoneid']) {
                // 宝石不是原石，无法切割
                $return['status'] = -3;
                return $return;
            }
            $field = self::field($uid);
            $rule = StoneRule::ROWS[$id];
            // 元宝购买 / 银币购买
            $price = $rule['buytype'] === 1 ? $rule['price'] : $rule['price'] * (int)$member['level'];

            $result = self::cutStone($uid, $id, $own['cut'], $now);
            foreach (['cut', 'value', 'stoneid', 'name', 'level'] as $value) {
                $return['info'][$value] = $result['info'][$value];
            }
            switch ($result['status']) {
                case 1:
                    // 切出珍惜宝石
                    $return['status'] = 1;
                    break;
                case 2:
                    // 切碎了
                    $return['status'] = 2;
                    // 如果还有免费次数，直接进入下次切石界面
                    if ($field['stone' . $id . '_times'] > 0 && self::subFreeTimes($uid, $id)) {
                        $field['stone' . $id . '_times'] -= 1;
                        // 插入到拥有宝石数据表
                        self::addOwnStone($uid, $id);
                        $return['info']['value'] = $rule['value_0'];
                        // 旧版这里把 stoneid 写成原石位置 id
                        $return['info']['stoneid'] = $id;
                        $return['info']['name'] = $rule['name'];
                        // 这里要把已经拥有的石头数量加上
                        $field['stone' . $id . '_times'] += 1;
                    }
                    // 依赖未移植：春节送福活动计数 actnewyear_model->add_task_num(version, 26)
                    break;
                case 3:
                    // 切小了：把当前正在切的宝石算上
                    $return['status'] = 3;
                    if ($field['stone' . $id . '_times'] > 0) {
                        $field['stone' . $id . '_times'] += 1;
                    }
                    break;
            }
            $return['gold'] = (int)DB::table('member')->where('uid', $uid)->value('gold');
            $return['price'] = $price;
            $return['num'] = $field['stone' . $id . '_times'];
            $return['buytype'] = $rule['buytype'];

            if ($return['status'] === 1 && $return['info']['level'] >= 4) {
                // 切出高级宝石发喇叭
                $sender = Chat::sender($uid);
                Chat::sendToHorn(sprintf(self::LANG_ACCOMPLISH, $member['nickname'], $return['info']['name'], $return['info']['value']), $sender);
            }
            return $return;
        });
    }

    /**
     * 卖出: c=gambling&m=sell&id=1|2|3
     *
     * -1 位置id不正确；-2 没有该种宝石；-3 功能未开启
     */
    public static function sell(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $id = self::idInput($ctx);
        return Member::locked($uid, static function () use ($uid, $id): array {
            $now = time();
            $return = ['status' => 0, 'value' => 0, 'jade' => MemberJade::get($uid)];
            $member = self::member($uid);
            if ($member === null) {
                // 功能未开启
                $return['status'] = -3;
                return $return;
            }
            if ($id === 0) {
                $return['status'] = -1;
                return $return;
            }
            // 检查是否已拥有该宝石
            $own = self::ownList($uid)[$id] ?? null;
            if ($own === null) {
                // 没有该宝石
                $return['status'] = -2;
                return $return;
            }
            $field = self::field($uid);
            $rule = StoneRule::ROWS[$id];
            $price = $rule['buytype'] === 2 ? $rule['price'] * (int)$member['level'] : $rule['price'];
            // 珍惜宝石按宝石价格，原石按当前估值
            $value = $own['stoneid'] ? (int)(Stone::ROWS[$own['stoneid']]['value'] ?? 0) : $rule['value_' . $own['cut']];
            $info = ['cut' => 0, 'value' => 0, 'stoneid' => 0, 'name' => '', 'level' => 0];

            if (self::deleteOwnStone($uid, $id, $now) !== 1) {
                $return['status'] = -2;
                return $return;
            }
            MemberJade::add($uid, $value, 'gambling', 'sell', $now);

            // 如果还有免费次数，直接进入下次切石界面
            if ($field['stone' . $id . '_times'] > 0 && self::subFreeTimes($uid, $id)) {
                $field['stone' . $id . '_times'] -= 1;
                // 插入到拥有宝石数据表
                self::addOwnStone($uid, $id);
                $info['value'] = $rule['value_0'];
                $info['stoneid'] = $id;
                $info['name'] = $rule['name'];
                // 这里要把已经拥有的石头数量加上
                $field['stone' . $id . '_times'] += 1;
            }
            // 依赖未移植：春节送福活动计数 actnewyear_model->add_task_num(version, 26)

            $return['status'] = 1;
            $return['value'] = $value;
            $return['info'] = $info;
            $return['num'] = $field['stone' . $id . '_times'];
            $return['jade'] = MemberJade::get($uid);
            $return['price'] = $price;
            $return['buytype'] = $rule['buytype'];
            $return['silver'] = (int)DB::table('member')->where('uid', $uid)->value('silver');
            return $return;
        });
    }

    /**
     * 切石（gambling_model->cut）
     *
     * @return array{status: int, info: array<string, mixed>}
     */
    private static function cutStone(int $uid, int $position, int $cut, int $now): array
    {
        $rule = StoneRule::ROWS[$position];
        $rand = mt_rand(1, 100);
        $stoneId = $stoneValue = $stoneLevel = 0;
        $stoneName = '';
        $cut += 1;
        if ($rand <= $rule['cut_rate_' . $cut]) {
            // 切出宝石
            $status = 1;
            $stone = self::selectStoneByLevel($cut + 1);
            self::updateOwnStone($uid, $position, ['stoneid' => (int)($stone['id'] ?? 0), 'cut' => 0]);
            $stoneId = $stone['id'] ?? null;
            $stoneValue = $stone['value'] ?? null;
            $stoneName = $stone['name'] ?? null;
            $stoneLevel = $stone['level'] ?? null;
            $cut = 0;
        } elseif ($cut === 4 || $rand - $rule['cut_rate_' . $cut] <= $rule['broken_rate_' . $cut]) {
            // 切碎了
            $status = 2;
            self::deleteOwnStone($uid, $position, $now);
            $cut = 0;
        } else {
            // 切小了
            $status = 3;
            self::updateOwnStone($uid, $position, ['stoneid' => 0, 'cut' => $cut]);
            $stoneValue = $rule['value_' . $cut];
        }
        return [
            'status' => $status,
            'info' => [
                'cut' => $cut,
                'bugtype' => $rule['buytype'],
                'price' => 0,
                'value' => $stoneValue,
                'stoneid' => $stoneId,
                'name' => $stoneName,
                'level' => $stoneLevel,
            ],
        ];
    }

    /**
     * 按照品级获得珍惜宝石（_select_stone_by_level：rand(0, 100) 依次减各宝石 rate）
     *
     * @return array<string, mixed>
     */
    private static function selectStoneByLevel(int $level): array
    {
        $rand = mt_rand(0, 100);
        foreach (Stone::ROWS as $value) {
            if ($value['level'] !== $level) {
                continue;
            }
            if ($rand <= $value['rate']) {
                return $value;
            }
            $rand -= $value['rate'];
        }
        return [];
    }

    /**
     * 拥有的石头，按位置索引（get_own_stone_list）
     *
     * @return array<int, array{position: int, stoneid: int, cut: int}>
     */
    private static function ownList(int $uid): array
    {
        $list = [];
        foreach (DB::table('own_stone')->select('position', 'stoneid', 'cut')->where('uid', $uid)->whereNull('deleted_at')->get() as $row) {
            $row = array_map('intval', $row);
            $list[$row['position']] = $row;
        }
        return $list;
    }

    private static function addOwnStone(int $uid, int $position): void
    {
        DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_own_stone` (`uid`, `position`, `stoneid`, `cut`) VALUES (:uid, :position, 0, 0)',
            ['uid' => $uid, 'position' => $position]
        );
    }

    /**
     * @param array{stoneid: int, cut: int} $data
     */
    private static function updateOwnStone(int $uid, int $position, array $data): void
    {
        DB::table('own_stone')->where('uid', $uid)->where('position', $position)->whereNull('deleted_at')->update($data);
    }

    /**
     * 删除已拥有的宝石（旧版整行删除，新版软删除）
     */
    private static function deleteOwnStone(int $uid, int $position, int $now): int
    {
        $affected = DB::table('own_stone')->where('uid', $uid)->where('position', $position)->whereNull('deleted_at')
            ->update(['deleted_at' => date('Y-m-d H:i:s', $now)]);
        return $affected > 0 ? 1 : 0;
    }

    /**
     * 扣一次免费切石次数（member_model->update_free_times：旧版写回读到的次数 - 1，新版条件减 1）
     */
    private static function subFreeTimes(int $uid, int $id): bool
    {
        $column = 'stone' . $id . '_times';
        return DB::execute(
            "UPDATE `zc_sanguo_member_field` SET `{$column}` = `{$column}` - 1 WHERE `uid` = :uid AND `{$column}` > 0 AND `deleted_at` IS NULL",
            ['uid' => $uid]
        ) > 0;
    }

    /**
     * @return array{stone1_times: int, stone2_times: int, stone3_times: int}
     */
    private static function field(int $uid): array
    {
        $row = DB::table('member_field')->select('stone1_times', 'stone2_times', 'stone3_times')->where('uid', $uid)->whereNull('deleted_at')->first();
        return ['stone1_times' => (int)($row['stone1_times'] ?? 0), 'stone2_times' => (int)($row['stone2_times'] ?? 0), 'stone3_times' => (int)($row['stone3_times'] ?? 0)];
    }

    /**
     * 原石位置参数（旧版 in_array($id, array(1, 2, 3))），不合法为 0
     */
    private static function idInput(Context $ctx): int
    {
        $id = $ctx->input('id', 0);
        return is_numeric($id) && in_array((float)$id, [1.0, 2.0, 3.0], true) ? (int)$id : 0;
    }

    /**
     * 当前玩家；主公不足 91 级（功能未开启）为 null
     *
     * @return array<string, mixed>|null
     */
    private static function member(int $uid): ?array
    {
        $row = DB::table('member')->select('uid', 'nickname', 'level', 'gold', 'silver')->where('uid', $uid)->whereNull('deleted_at')->first();
        return $row === null || (int)$row['level'] < self::NEED_LEVEL ? null : $row;
    }
}
