<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Campaign;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\General\GeneralHandler;
use Hanjiang\MasterData\Matrix;
use Hanjiang\Model\MainQuestTask;
use Hanjiang\Model\Member;
use Hanjiang\Model\OwnMatrix;
use Hanjiang\Model\StatPay;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 阵法（移植旧版 controllers/matrix.php：index / get_info / update_matrix / use_matrix / levelup）
 *
 * 存储与规则见 Model\OwnMatrix（旧版 matrix_model）：每个阵法一行、启用的阵法记在 member_field.matrixid，
 * 只有出征名单（general.goout = 1）里的将领能上阵。
 */
final class MatrixHandler
{
    /**
     * 初始化: c=matrix&m=index
     *
     * 状态码：-2 获取阵法模板失败；-1 获取拥有阵法失败；1 成功。
     * 顺带按主公等级 / VIP 等级补齐已达开启条件的阵法（add_matrix，等级 0）。
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $return = ['status' => 0, 'matrix' => [], 'general' => []];

        // 出征武将列表
        $list = GeneralHandler::battleList($uid);
        // 启用的阵法 id
        $openid = OwnMatrix::activeId($uid);
        // 拥有阵法列表
        $own = OwnMatrix::ownList($uid);
        if ($own === []) {
            // 获取拥有阵法失败 -1
            $return['status'] = -1;
            return $return;
        }
        $result = [];
        foreach ($own as $value) {
            $result[$value['mid']] = $value;
        }
        // 启用阵法的等级（旧版启用的阵法不在拥有列表里时取到 null，按 0）
        $level = $result[$openid]['level'] ?? 0;
        $next = $level + 1;
        $rule = OwnMatrix::rule($openid, $level);
        if ($rule === null) {
            // 获取阵法模板失败 -2
            $return['status'] = -2;
            return $return;
        }
        $position = $rule['info'];

        $gidarr = [];
        for ($i = 1; $i < 10; $i++) {
            if (($result[$openid]['position' . $i] ?? 0) !== 0) {
                // 取上阵将领
                $gidarr[] = $result[$openid]['position' . $i];
            }
            if ($position['position' . $i] === -1) {
                $result[$openid]['position' . $i] = -1;
            }
        }

        // 启用的阵法输出整行（不含 uid）与加成，其余只输出 mid
        $data = [];
        foreach ($result as $key => $value) {
            if ($key === $openid) {
                $data[$key] = $value;
                $data[$key]['addition'] = $rule['addition'];
                $data[$key]['addition']['next'] = (int)(OwnMatrix::levelupPrice($next) * $rule['info']['ratio']);
            } else {
                $data[$key] = ['mid' => $value['mid']];
            }
            unset($data[$key]['uid']);
        }

        $ownArr = [];
        foreach ($data as $key => $value) {
            $data[$key]['open'] = ($value['mid'] ?? null) === $openid ? 1 : 0;
            $ownArr[] = $value['mid'] ?? null;
        }

        $generalList = [];
        foreach ($list as $key => $value) {
            $generalList[$key] = [
                'id' => $value['id'],
                'goout' => in_array($value['id'], $gidarr, true) ? 1 : 0,
                'quality' => $value['quality'],
                'name' => $value['name'],
                'image' => $value['image'],
                'level' => $value['level'],
            ];
        }

        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first() ?? [];
        // 拥有阵法个数不足 11 个时，检查主公等级 / VIP 是否能开启新阵法
        if (count($own) < OwnMatrix::MAX_MID) {
            $open = OwnMatrix::openTemplates((int)($member['level'] ?? 0), (int)($member['vip'] ?? 0));
            $addArr = array_diff(array_keys($open), $ownArr);
            foreach ($addArr as $value) {
                OwnMatrix::add($uid, $value);
                $data[$value] = ['mid' => $value, 'open' => 0];
            }
        }

        // 排序：VIP 阵法在前（按模板 opentype DESC, openlevel DESC），其余按 $data 顺序
        $sort = 0;
        foreach (OwnMatrix::templateList() as $value) {
            $id = (int)$value['id'];
            if (isset($data[$id])) {
                if ((int)$value['opentype'] === 2) {
                    $data[$id]['vip'] = (int)$value['openlevel'];
                    $data[$id]['sort'] = ++$sort;
                } else {
                    $data[$id]['vip'] = 0;
                }
            }
        }
        foreach ($data as $key => $value) {
            if (($value['vip'] ?? 0) === 0) {
                $value['sort'] = ++$sort;
                $data[$key] = $value;
            }
        }

        $return['general'] = $generalList;
        $return['matrix'] = $data;
        $return['status'] = 1;
        return $return;
    }

    /**
     * 获得阵法信息: c=matrix&m=get_info
     *
     * mid 为 0 时取启用的阵法。状态码：-2 获得阵法规则失败；-1 获得拥有阵法失败；1 成功（旧版注释里的 -3 没有使用）。
     */
    public static function get_info(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $return = ['status' => 0, 'matrix' => []];
        $mid = (int)$ctx->input('mid', 0);
        if ($mid === 0) {
            $mid = OwnMatrix::activeId($uid);
        }

        $info = OwnMatrix::own($uid, $mid);
        if ($info === null) {
            // 获取拥有阵法失败 -1
            $return['status'] = -1;
            return $return;
        }

        $rule = OwnMatrix::rule($info['mid'], $info['level']);
        if ($rule === null) {
            // 获取阵法规则失败
            $return['status'] = -2;
            return $return;
        }

        for ($i = 1; $i < 10; $i++) {
            if ($rule['info']['position' . $i] === -1) {
                $info['position' . $i] = -1;
            }
        }
        $info['vip'] = $rule['info']['opentype'] === 2 ? $rule['info']['openlevel'] : 0;

        $price = OwnMatrix::levelupPrice($info['level'] + 1);
        $return['matrix'] = $info;
        $return['matrix']['addition'] = $rule['addition'];
        $return['matrix']['addition']['next'] = (int)($price * $rule['info']['ratio']);
        $return['status'] = 1;
        return $return;
    }

    /**
     * 更新阵法: c=matrix&m=update_matrix（list 为 9 个站位的将领 id，逗号分隔）
     *
     * 状态码：-4 将领不唯一；-3 获取出征信息失败；-2 更新阵法失败（阵法 id 错误、上阵超过 5 人、未拥有该阵法、
     * 站位没有变化）；-1 提交数据不完整；1 成功；3 将领未出征。tip = 1 表示提交的阵法上没有将领（仍照常保存）。
     */
    public static function update_matrix(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $return = ['status' => 0, 'tip' => 0];
        $list = urldecode((string)$ctx->input('list', ''));
        $mid = (int)$ctx->input('mid', 0);

        // 旧版 PHP 5 把站位字符串按数字比较 / 写库，这里统一按 intval
        $list = array_map(static fn (string $v): int => (int)$v, explode(',', $list));
        if (count($list) !== 9) {
            // -1 提交数据不完整
            $return['status'] = -1;
            return $return;
        }
        $nogeneral = 1;
        foreach ($list as $value) {
            if ($value > 0) {
                $nogeneral = 0;
            }
        }
        if ($nogeneral) {
            // 阵法上没有将领
            $return['tip'] = 1;
        }
        $repeat = 0;
        for ($i = 0; $i < 9; $i++) {
            if ($list[$i] > 0) {
                for ($j = 0; $j < 9; $j++) {
                    if ($i !== $j && $list[$i] === $list[$j]) {
                        $repeat = 1;
                    }
                }
            }
        }
        if ($repeat === 1) {
            // -4 将领不唯一
            $return['status'] = -4;
            return $return;
        }

        $glist = [];
        $matrix = [];
        for ($i = 0, $j = 1; $i < 9; $i++, $j++) {
            if ($list[$i] > 0) {
                // 上阵将领列表
                $glist[] = $list[$i];
            }
            $matrix['position' . $j] = $list[$i];
        }

        // 出征将领
        $battle = GeneralHandler::battleList($uid);
        if ($battle === []) {
            // 获取出征信息失败 -3
            $return['status'] = -3;
            return $return;
        }
        $gidarr = array_column($battle, 'id');
        if (count(array_intersect($glist, $gidarr)) !== count($glist)) {
            // 将领未出征 3
            $return['status'] = 3;
            return $return;
        }

        if (self::saveMatrix($uid, $mid, $matrix) !== 1) {
            // 更新阵法失败 -2（旧版 matrix_model::update_matrix 不会返回 2，所以控制器的「人员未变更 2」走不到）
            $return['status'] = -2;
            return $return;
        }

        // 主线任务 6同时上阵5个将领
        if (count($glist) === 5) {
            MainQuestTask::action($uid, 6, 1);
        }

        Redis::del('player:' . $uid);
        $return['status'] = 1;
        return $return;
    }

    /**
     * 启用阵法: c=matrix&m=use_matrix
     *
     * 状态码：-2 未知错误（未拥有该阵法）；-1 启用阵法失败（含已是启用的阵法）；1 成功；2 参数错误；3 没有上阵将领。
     */
    public static function use_matrix(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $return = ['status' => 0];
        $mid = max(0, (int)$ctx->input('mid', 0));
        if ($mid === 0) {
            // 参数错误 2
            $return['status'] = 2;
            return $return;
        }

        // 检查阵法是否有将领（check_matrix）
        $own = OwnMatrix::own($uid, $mid);
        if ($own === null) {
            // 未知错误
            $return['status'] = -2;
            return $return;
        }
        $hasGeneral = false;
        for ($i = 1; $i < 10; $i++) {
            if ($own['position' . $i] !== 0) {
                $hasGeneral = true;
            }
        }
        if (!$hasGeneral) {
            // 未设置将领 3
            $return['status'] = 3;
            return $return;
        }

        $affected = DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->update(['matrixid' => $mid]);
        if ($affected <= 0) {
            // 启用阵法失败 -1
            $return['status'] = -1;
            return $return;
        }

        Redis::del('player:' . $uid);
        $return['status'] = 1;
        return $return;
    }

    /**
     * 阵法升级: c=matrix&m=levelup
     *
     * 花费元宝 intval(MatrixLevel[新等级].price × 模板 ratio)；阵法等级上限 = 主公等级 / 5（至少 1、最高 100）。
     * 状态码：-3 扣除元宝失败；-2 阵法升级失败；-1 获取阵法信息失败；1 成功；2 等级已满；3 主公等级不足（附 info.matrix_level / master_level）；4 元宝不足。
     */
    public static function levelup(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $return = [];
        $mid = (int)$ctx->input('mid', 0);
        $matrix = OwnMatrix::own($uid, $mid);
        if ($matrix === null) {
            $return['status'] = -1;
            return $return;
        }
        $level = $matrix['level'] + 1;

        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first() ?? [];
        $levelup = self::levelupMatrix($uid, $mid, $level, (int)($member['level'] ?? 0), (int)($member['gold'] ?? 0));
        if ($levelup['status'] !== 1) {
            if ($levelup['status'] === 2) {
                // 阵法等级已满
                $return['status'] = 2;
            } elseif ($levelup['status'] === 3) {
                $return['info']['matrix_level'] = $level;
                $return['info']['master_level'] = OwnMatrix::levelupRule($level);
                // 主公等级不足
                $return['status'] = 3;
            } elseif ($levelup['status'] === 4) {
                // 元宝不足
                $return['status'] = 4;
            } else {
                // 升级阵法失败
                $return['status'] = -2;
            }
            return $return;
        }
        if ($levelup['info']['gold'] === 0) {
            $return['status'] = -3;
            return $return;
        }
        if (!Member::deductCurrency($uid, gold: $levelup['info']['gold'])) {
            // 扣除元宝失败（旧版同样先升级、后扣元宝）
            $return['status'] = -3;
            return $return;
        }

        // 主线任务 5任意阵法升到X级
        MainQuestTask::action($uid, 5, $level);
        // 消费统计记录
        StatPay::record($uid, 'matrixlevelup', $levelup['info']['gold']);

        Redis::del('player:' . $uid);
        $levelup['info']['gold'] = (int)(DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('gold') ?? 0);

        $return['status'] = 1;
        $return['info'] = $levelup['info'];
        return $return;
    }

    /**
     * 写入阵法站位（移植 matrix_model::update_matrix）
     *
     * @param array<string, int> $matrix position1~9 => 将领 id
     * @return int 1 成功；-8 上阵超过 5 人；-7 将领不唯一；-6 阵法 id 错误；-3 获取阵法规则失败；-2 获取拥有阵法失败；-1 更新失败（含站位没有变化）
     */
    private static function saveMatrix(int $uid, int $mid, array $matrix): int
    {
        if ($mid < 1 || $mid > OwnMatrix::MAX_MID) {
            return -6;
        }

        $repeat = 0;
        $count = 0;
        for ($i = 1; $i < 10; $i++) {
            if ($matrix['position' . $i] > 0) {
                $count++;
                for ($j = 1; $j < 10; $j++) {
                    if ($i !== $j && $matrix['position' . $i] === $matrix['position' . $j]) {
                        $repeat = 1;
                    }
                }
            }
        }
        if ($repeat === 1) {
            return -7;
        }
        if ($count > 5) {
            return -8;
        }

        $own = OwnMatrix::own($uid, $mid);
        if ($own === null) {
            return -2;
        }
        $rule = OwnMatrix::rule($mid, $own['level']);
        if ($rule === null) {
            return -3;
        }

        for ($i = 1; $i < 10; $i++) {
            if ($rule['info']['position' . $i] === -1) {
                $matrix['position' . $i] = 0;
            }
            // 客户端把关闭的位置记成 -1 / -2；旧版列为 unsigned，非严格模式的 MySQL 存成 0
            if ($matrix['position' . $i] < 0) {
                $matrix['position' . $i] = 0;
            }
        }

        $affected = DB::table('matrix')->where('uid', $uid)->where('mid', $mid)->whereNull('deleted_at')->update($matrix);
        return $affected > 0 ? 1 : -1;
    }

    /**
     * 升级阵法（移植 matrix_model::levelup）：先写入新等级，扣元宝由调用方完成（旧版同）
     *
     * @return array{status: int, info?: array<string, int>}
     *   status：-2 获取阵法模板失败；-1 获取升级价格失败；0 失败；1 成功；2 阵法满级；3 达到主公等级上限；4 元宝不足
     */
    private static function levelupMatrix(int $uid, int $mid, int $level, int $memberLevel, int $memberGold): array
    {
        $return = ['status' => 0];

        $maxLevel = OwnMatrix::maxLevel($memberLevel);
        if ($level === 101) {
            $return['status'] = 2;
            return $return;
        } elseif ($level > $maxLevel) {
            $return['status'] = 3;
            return $return;
        }

        $tpl = Matrix::ROWS[$mid] ?? null;
        if ($tpl === null) {
            $return['status'] = -2;
            return $return;
        }

        $price = OwnMatrix::levelupPrice($level);
        if ($price === 0) {
            $return['status'] = -1;
            return $return;
        }
        $return['info']['gold'] = (int)($price * (int)$tpl['ratio']);

        if ($return['info']['gold'] > $memberGold) {
            $return['status'] = 4;
            return $return;
        }

        $affected = DB::table('matrix')->where('uid', $uid)->where('mid', $mid)->whereNull('deleted_at')->update(['level' => $level]);
        if ($affected > 0) {
            $next = $level + 1;
            // 旧版 isset($matrix['openlevel' . $level])：模板只有 openlevel3~5，所以只有升到 3 / 4 / 5 级时返回新开放的位置
            if ((int)$tpl['opentype'] === 1 && isset($tpl['openlevel' . $level])) {
                $position = (int)$tpl['position' . $level];
            } else {
                $position = 0;
            }
            $return['info']['level'] = $level;
            $return['info']['type'] = (int)$tpl['addtype'];
            $return['info']['position'] = $position;
            $return['info']['percent'] = OwnMatrix::addition($level, (int)$tpl['addition']);
            $return['info']['next'] = (int)(OwnMatrix::levelupPrice($next) * (int)$tpl['ratio']);
            $return['status'] = 1;
        }
        return $return;
    }
}
