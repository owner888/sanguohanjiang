<?php

declare(strict_types=1);

namespace Hanjiang\Handler\General;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Model\DailyTask;
use Hanjiang\Model\FreeLeap;
use Hanjiang\Model\General;
use Hanjiang\Model\MainQuestTask;
use Hanjiang\Model\Member;
use Hanjiang\Model\Practice;
use Hanjiang\Model\StatPay;
use Hanjiang\Model\Vip;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 校场（移植旧版 controllers/practice.php，训练位 / 突飞规则见 Model\Practice，突飞卡见 Model\FreeLeap）
 *
 * 客户端 practicepop.js / practiceleappop.js 调用 index、go_leap（gid）、mop（gid、times）、turn（gid）、get_unlock_price（squence）、
 * unlock、practice_start（pid、gid、type）、get_levelup_price（pid）、levelup（pid）、practice_stop（pid）。
 * 同一玩家的突飞 / 训练 / 解锁 / 升级在玩家行锁里串行执行。
 *
 * 旧版 index 的首次打开记录 edifice_model->add_edifice_log(1) 只写日志、不影响返回，不移植。
 */
final class PracticeHandler
{
    /** 突飞扫荡次数（2014/03/24 起固定） */
    private const array MOP = [5, 10, 50, 100];

    /**
     * 初始化: c=practice&m=index
     */
    public static function index(Context $ctx): array
    {
        /**
         * 接口返回值
         * -4 获取突飞规则失败
         * -3 获取扩展信息失败
         * -1 获取训练列表失败
         *  0 失败
         *  1 成功
         *  2 没有出征将领
         */
        $return = ['status' => 0, 'list' => [], 'rule' => [], 'place' => [], 'leap' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        $uid = (int)$member['uid'];
        // 更新所有训练将领信息
        Practice::updatePractice($uid);
        // [将领模型]出征将领列表,按等级排序
        $battle = General::battleList($uid);
        if ($battle === []) {
            // 没有出征将领
            $return['status'] = 2;
            return $return;
        }
        $battleArr = [];
        $imagearr = [];
        $qualityarr = [];
        foreach ($battle as $key => $value) {
            $imagearr[(int)$value['id']] = (int)$value['image'];
            $qualityarr[(int)$value['id']] = (int)$value['quality'];
            // 设置转生系数
            $isturn = 0;
            $ratio = 1;
            if (General::turnRule((int)$value['turn']) === (int)$value['level']) {
                $isturn = 1;
                // 下转系数
                $ratio = General::nextTurnRatio((int)$value['turn']);
            }
            $battleArr[$key] = [
                'id' => (int)$value['id'],
                'name' => (string)$value['name'],
                'quality' => (int)$value['quality'],
                'level' => (int)$value['level'],
                'exp' => (int)$value['exp'],
                'levelupexp' => (int)$value['levelupexp'],
                'image' => (int)$value['image'],
                'type' => (int)$value['type'],
                'turn' => (int)$value['turn'],
                'isturn' => $isturn,
                'ratio' => $ratio,
            ];
        }

        // [训练模型]获得训练列表
        $practice = Practice::practiceList($uid);
        if ($practice['status'] !== 1) {
            // 获取训练列表失败 -1
            $return['status'] = -1;
            return $return;
        }
        $now = time();
        $gidarr = [];
        $practiceArr = [];
        foreach ($practice['list'] as $key => $value) {
            // 训练中的将领（旧版空闲训练位也记 gid 0）
            $gidarr[$value['gid']] = $value['id'];
            $practiceArr[$key] = [
                'id' => $value['id'],
                'gid' => $value['gid'],
                'image' => $value['gid'] !== 0 ? ($imagearr[$value['gid']] ?? null) : 0,
                'squence' => $value['squence'],
                'level' => $value['level'],
                'type' => $value['type'],
                'quality' => $value['gid'] !== 0 ? ($qualityarr[$value['gid']] ?? null) : 0,
                'cd' => $value['starttime'] !== 0 ? $value['endtime'] - $now : 0,
            ];
        }
        foreach ($battleArr as $key => $value) {
            $battleArr[$key]['pid'] = 0;
            $battleArr[$key]['ispractice'] = 0;
            if (isset($gidarr[$value['id']])) {
                $battleArr[$key]['pid'] = $gidarr[$value['id']];
                $battleArr[$key]['ispractice'] = 1;
            }
        }

        // 获取本日突飞次数
        $field = self::field($uid);
        if ($field === null) {
            // 获取扩展信息失败 -3
            $return['status'] = -3;
            return $return;
        }
        $leap = null;
        if ((int)$field['freeleap'] === 0) {
            $leap = Practice::leapRule((int)$field['leaptimes'] + 1, 0, (int)$member['level'], (int)$member['vip']);
        }

        $return['mop'] = self::MOP;
        $return['list'] = $battleArr;
        $return['place'] = $practiceArr;
        $return['rule'] = Practice::typeList((int)$member['level']);
        $return['freetimes'] = (int)$field['freeleap'];
        $return['leap'] = $leap !== null ? (int)$leap['gold'] : 0;
        $return['status'] = 1;
        return $return;
    }

    /**
     * 突飞: c=practice&m=go_leap（gid）
     */
    public static function go_leap(Context $ctx): array
    {
        /**
         * -8 获取将领信息失败
         * -6 获取规则失败
         * -5 突飞次数获取失败
         * -4 加经验失败
         * -3 参数错误
         * -2 扣除金币失败
         * -1 获取规则失败
         *  0 失败
         *  1 成功
         *  2 金币不足
         *  3 等级已达主公等级上限
         *  4 训练才能突飞
         */
        $uid = MemberHandler::resolveUid($ctx);
        $gid = max(0, (int)$ctx->input('gid', 0));
        if ($gid === 0) {
            // 参数错误 -3
            return ['status' => -3, 'info' => []];
        }
        if (self::member($uid) === null) {
            return ['status' => 0, 'info' => []];
        }
        $now = time();
        $result = Member::locked($uid, static function () use ($uid, $gid, $now): array {
            $return = ['status' => 0, 'info' => []];
            $member = self::member($uid);
            // [训练模型]将领是否在训练中
            if (Practice::checkPractice($uid, $gid) === 0) {
                // 训练才能突飞 4
                $return['status'] = 4;
                return $return;
            }
            // 获取本日突飞次数
            $field = self::field($uid);
            if ($field === null) {
                $return['status'] = -5;
                return $return;
            }
            // 当次突飞次数
            $leaptimes = (int)$field['leaptimes'] + 1;
            $general = General::own($uid, $gid);
            if ($general === null) {
                // 获取将领信息失败 返回-8
                $return['status'] = -8;
                return $return;
            }
            if ((int)$general['level'] === (int)$member['level'] && (int)$general['exp'] === (int)$general['levelupexp']) {
                $return['status'] = 3;
                return $return;
            }
            $free = (int)$field['freeleap'] > 0 ? 1 : 0;
            // 新手引导突飞免费5次,武将为周仓,模板id为2
            $guide = null;
            if ((int)$general['generalid'] === 2) {
                $guide = Practice::guideInfo($uid);
                if ($guide['leaptimes'] < 5) {
                    $free = 1;
                }
            }
            // 获得突飞规则
            $rule = Practice::leapRule($leaptimes, (int)$general['level'], (int)$member['level'], (int)$member['vip']);
            $hit = $rule['hit'] ? 1 : 0;
            // 取得金币
            $gold = $rule['gold'];
            if ($free) {
                $next = $leaptimes;
                if ((int)$field['freeleap'] > 0) {
                    // 突飞卡突飞
                    FreeLeap::sub($uid, 1, $now);
                    // 最后一张突飞卡
                    if ((int)$field['freeleap'] <= 1) {
                        $free = 0;
                    }
                }
                if ($guide !== null) {
                    // 新手引导突飞,记录次数
                    $guidetimes = $guide['leaptimes'] + 1;
                    Practice::addGuideLeaptimes($uid, $guidetimes);
                    // 最后一次免费突飞
                    if ($guidetimes > 4) {
                        $free = 0;
                    }
                }
            } else {
                // 下次突飞次数
                $next = $leaptimes + 1;
                // 扣除金币
                if (!Member::deductCurrency($uid, gold: $gold)) {
                    // 金币不足 2
                    $return['status'] = 2;
                    return $return;
                }
                if (Practice::updateLeaptimes($uid, $leaptimes) !== 1) {
                    $return['status'] = -5;
                    return $return;
                }
            }

            // 取得经验，加经验并判断是否升级
            $exp = $rule['exp'];
            $info = General::addExp($uid, $gid, (int)$exp);
            if ($info['status'] !== 1) {
                if ($info['status'] === 2) {
                    // 不在出征队列中 2
                    $return['status'] = 2;
                } elseif ($info['status'] === 3) {
                    // 等级已达主公等级上限 3
                    $return['status'] = 3;
                } else {
                    // 加经验失败 -4
                    $return['status'] = -4;
                    $return['error'] = $info;
                }
                return $return;
            }

            // 获得突飞规则,取下次突飞花费
            $nextrule = null;
            if ($free === 0) {
                $nextrule = Practice::leapRule($next, 0, (int)$member['level'], (int)$member['vip']);
            }
            $infoArr = [];
            foreach (['gid', 'level', 'addexp', 'exp', 'levelupexp', 'isturn'] as $value) {
                $infoArr[$value] = $info['info'][$value];
            }
            if ($infoArr['isturn'] === 1) {
                $infoArr['ratio'] = General::nextTurnRatio((int)$general['turn']);
            }
            $after = self::member($uid);
            $return['status'] = 1;
            $return['info'] = $infoArr;
            $return['info']['gold'] = (int)$after['gold'];
            $return['info']['next'] = $nextrule !== null ? $nextrule['gold'] : 0;
            $return['info']['freetimes'] = (int)$field['freeleap'] > 0 ? (int)$field['freeleap'] - 1 : 0;
            $return['info']['hit'] = $hit;

            // 消费统计记录（旧版免费突飞也按本次规则的价格记）
            StatPay::record($uid, 'tufei', $gold);
            // 日常任务(1是突飞对应的类型)
            if ((int)$member['level'] >= DailyTask::OPEN_LEVEL) {
                DailyTask::action($uid, DailyTask::TYPE_LEAP, 1, $now);
            }
            Practice::addLeapLog($uid, $gid, $exp, $hit, $general, $return['info'], $now);
            return $return;
        });
        return $result;
    }

    /**
     * 突飞扫荡: c=practice&m=mop（gid、times）
     */
    public static function mop(Context $ctx): array
    {
        /**
         * -8 增加经验失败
         * -7 扣除突飞次数失败
         * -6 获取规则失败
         * -5 计算经验失败
         * -4 突飞暴击率错误
         * -3 获取将领信息失败
         * -2 该武将不在训练位上
         *  0 失败
         *  1 成功
         *  2 突飞次数不足
         *  3 等级已达主公等级上限
         *  4 转生等级上限
         */
        $uid = MemberHandler::resolveUid($ctx);
        $times = (int)$ctx->input('times', 0);
        $gid = (int)$ctx->input('gid', 0);
        if (self::member($uid) === null) {
            return ['status' => 0];
        }
        $now = time();
        return Member::locked($uid, static function () use ($uid, $times, $gid, $now): array {
            $return = ['status' => 0];
            $member = self::member($uid);
            $field = self::field($uid);
            $freeleap = (int)($field['freeleap'] ?? 0);
            if ($times > $freeleap) {
                // 突飞次数不足
                $return['status'] = 2;
                return $return;
            }
            // [训练模型]将领是否在训练中
            if (Practice::checkPractice($uid, $gid) === 0) {
                // 该武将不在训练位上
                $return['status'] = -2;
                return $return;
            }
            $general = General::own($uid, $gid);
            if ($general === null) {
                // 获取将领信息失败
                $return['status'] = -3;
                return $return;
            }
            if ((int)$general['level'] === (int)$member['level'] && (int)$general['exp'] === (int)$general['levelupexp']) {
                // 等级已达主公等级上限
                $return['status'] = 3;
                return $return;
            }
            $glevel = (int)$general['level'];
            // 计算突飞获得经验
            $crit = Practice::leapCrit($glevel);
            if ($crit < 0) {
                // 突飞暴击率错误
                $return['status'] = -4;
                return $return;
            }
            $mop = Practice::mopExp($times, $crit, (int)$member['level']);
            if ($mop === []) {
                // 计算经验失败
                $return['status'] = -5;
                return $return;
            }
            // vip 5 时有暴击
            if ((int)$member['vip'] < 5) {
                $mop['ext_exp'] = 0;
            }
            $addExp = $mop['exp'] + $mop['ext_exp'] + (int)$general['exp'];
            // 计算武将突飞后等级
            $result = General::inheritLevelup((int)$member['level'], (int)$general['turn'], $glevel, $addExp, false);
            if (($result['status'] ?? 0) !== 1) {
                if (($result['status'] ?? 0) === 2) {
                    // 等级已达主公等级上限
                    $return['status'] = 3;
                } elseif (($result['status'] ?? 0) === 3) {
                    // 转生等级上限
                    $return['status'] = 4;
                } else {
                    $return['status'] = -6;
                }
                return $return;
            }
            // 扣除突飞次数
            $sub = FreeLeap::sub($uid, $times, $now);
            if ($sub !== 1) {
                $return['status'] = $sub === -1 ? 2 : -7;
                return $return;
            }
            // 增加经验
            $add = General::addExp($uid, $gid, (int)($mop['exp'] + $mop['ext_exp']));
            if ($add['status'] !== 1) {
                $return['status'] = -8;
                return $return;
            }
            // 日常任务(1是突飞对应的类型)
            if ((int)$member['level'] >= DailyTask::OPEN_LEVEL) {
                DailyTask::action($uid, DailyTask::TYPE_LEAP, $times, $now);
            }
            $return['status'] = 1;
            $return['general'] = [
                'level' => $add['info']['level'],
                'exp' => $add['info']['exp'],
                'levelupexp' => $add['info']['levelupexp'],
            ];
            $return['exp'] = $mop['exp'];
            $return['ext_exp'] = $mop['ext_exp'];
            $return['freeleap'] = $freeleap - $times;
            // 2014/03/21:突飞扫荡bug修复（突飞卡用完时返回下次突飞的元宝）
            if ($return['freeleap'] === 0) {
                $after = self::field($uid);
                $nextrule = Practice::leapRule((int)($after['leaptimes'] ?? 0) + 1, 0, (int)$member['level'], (int)$member['vip']);
                $return['next'] = $nextrule['gold'];
            }
            return $return;
        });
    }

    /**
     * 转生: c=practice&m=turn（gid）
     */
    public static function turn(Context $ctx): array
    {
        /**
         * -3 转生失败
         * -2 获取将领失败
         * -1 参数错误
         *  0 失败
         *  1 成功
         *  2 等级已达200上限
         *  3 等级不足
         */
        $return = ['status' => 0, 'info' => []];
        $uid = MemberHandler::resolveUid($ctx);
        $gid = max(0, (int)$ctx->input('gid', 0));
        if ($gid === 0) {
            // 参数错误 -1
            $return['status'] = -1;
            return $return;
        }
        $general = General::own($uid, $gid);
        if ($general === null) {
            // 获取将领失败 -2
            $return['status'] = -2;
            return $return;
        }
        // [将领模型]转生（general_model->turn）
        $turn = (int)$general['turn'];
        if ($turn >= 70) {
            // 转生上限
            $return['status'] = 2;
            return $return;
        }
        if ((int)$general['level'] !== General::turnRule($turn)) {
            // 等级不足 3
            $return['status'] = 3;
            return $return;
        }
        $data = ['exp' => 0, 'level' => 1, 'levelupexp' => General::levelupExp(1), 'turn' => $turn + 1];
        $affected = DB::table('general')->where('uid', $uid)->where('id', $gid)->where('level', $general['level'])->where('turn', $turn)
            ->whereNull('deleted_at')->update($data);
        if ($affected <= 0) {
            // 更新失败（并发转生只成功一次）
            $return['status'] = -3;
            return $return;
        }
        $return['status'] = 1;
        $return['info'] = $data;
        return $return;
    }

    /**
     * 获得解锁价格: c=practice&m=get_unlock_price（squence 3 ~ 8）
     */
    public static function get_unlock_price(Context $ctx): array
    {
        /**
         * -1 位置值不合法
         *  0 失败
         *  1 成功
         */
        $return = ['status' => 0, 'gold' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        $squence = (int)$ctx->input('squence', 0);
        if ($squence < 3 || $squence > 8) {
            // 位置值不合法 -1
            $return['status'] = -1;
            return $return;
        }
        $field = self::field($uid);
        if ($squence <= (int)($field['practiceall'] ?? 0)) {
            // 安卓版本bug后端屏蔽之
            return $return;
        }
        $price = Practice::SQUENCE_PRICE[$squence];
        $return['gold'] = $price['gold'];
        if (isset($price['vip'])) {
            $return['vip'] = $price['vip'];
        }
        $return['status'] = 1;
        return $return;
    }

    /**
     * 解锁训练位: c=practice&m=unlock
     */
    public static function unlock(Context $ctx): array
    {
        /**
         * -5 获取训练位信息失败
         * -4 添加训练位失败
         * -3 扣除金币失败
         * -1 获得扩展信息失败
         *  0 失败
         *  1 成功
         *  2 金币不足
         *  3 vip等级不足
         */
        $uid = MemberHandler::resolveUid($ctx);
        if (self::member($uid) === null) {
            return ['status' => 0, 'info' => []];
        }
        return Member::locked($uid, static function () use ($uid): array {
            $return = ['status' => 0, 'info' => []];
            // vip_model->practicenum（VIP 0 为 3 个）
            $num = self::vipRule($uid, 'practicenum', 3);
            $field = self::field($uid);
            if ($field === null) {
                // 获得扩展信息失败 -1
                $return['status'] = -1;
                return $return;
            }
            // 要解锁的训练位
            $squence = (int)$field['practiceall'] + 1;
            if ($squence > $num) {
                // vip等级不足返回3
                $return['status'] = 3;
                return $return;
            }
            $price = Practice::SQUENCE_PRICE[$squence] ?? null;
            if ($price === null) {
                // 旧版 VIP 训练位最多 8 个，取不到价格时扣 0 元宝并插出第 9 个训练位；这里按添加训练位失败处理
                $return['status'] = -4;
                return $return;
            }
            $gold = $price['gold'];
            if (!Member::deductCurrency($uid, gold: $gold)) {
                // 元宝不足
                $return['status'] = 2;
                return $return;
            }
            // [训练模型]添加训练位
            $info = Practice::addPractice($uid, $squence);
            if ($info['status'] !== 1) {
                // 添加训练位失败 -4
                $return['status'] = -4;
                return $return;
            }
            // [训练模型]获取训练位信息
            $practice = Practice::own($uid, $info['id']);
            if ($practice === null) {
                // 获取训练位信息失败 -5
                $return['status'] = -5;
                return $return;
            }
            $return['status'] = 1;
            $return['info'] = $practice;
            $return['info']['gold'] = (int)self::member($uid)['gold'];
            // 消费统计记录
            StatPay::record($uid, 'buy_xunlianwei', $gold);
            return $return;
        });
    }

    /**
     * 开始训练: c=practice&m=practice_start（pid、gid、type）
     */
    public static function practice_start(Context $ctx): array
    {
        /**
         * -7 获取扩展信息失败
         * -6 时间记录失败
         * -5 金币扣除失败
         * -4 银币扣除失败
         * -3 获得训练费用失败
         * -2 获取拥有训练信息失败
         * -1 获得将领信息失败
         *  0 失败
         *  1 成功
         *  2 将领未出征
         *  3 训练位已被占用
         *  4 银币不足
         *  5 金币不足
         *  6 将领正在训练中
         *  7 将领已满级
         */
        $uid = MemberHandler::resolveUid($ctx);
        $pid = (int)$ctx->input('pid', 0);
        $gid = (int)$ctx->input('gid', 0);
        $type = (int)$ctx->input('type', 0);
        if (self::member($uid) === null) {
            return ['status' => 0, 'info' => []];
        }
        $now = time();
        Practice::updatePractice($uid, $now);
        return Member::locked($uid, static function () use ($uid, $pid, $gid, $type, $now): array {
            $return = ['status' => 0, 'info' => []];
            $member = self::member($uid);
            // [用户模型]获取扩展信息(uid)
            $field = self::field($uid);
            if ($field === null) {
                // 获取扩展信息失败 -7
                $return['status'] = -7;
                return $return;
            }
            // [将领模型]获得将领信息
            $general = General::own($uid, $gid);
            if ($general === null) {
                // 获得将领信息失败 -1
                $return['status'] = -1;
                return $return;
            }
            if ((int)$general['goout'] !== 1) {
                // 将领未出证 2
                $return['status'] = 2;
                return $return;
            }
            // 将领已满级
            if ((int)$general['level'] === (int)$member['level'] && (int)$general['exp'] === (int)$general['levelupexp']) {
                $return['status'] = 7;
                return $return;
            }
            if ((int)$general['level'] > (int)$member['level']) {
                $return['status'] = 7;
                return $return;
            }
            // [训练模型]检查将领唯一性
            if (Practice::checkPractice($uid, $gid) !== 0) {
                // 将领正在训练中 6
                $return['status'] = 6;
                return $return;
            }
            // [训练模型]获得拥有训练位信息(pid)
            $own = Practice::own($uid, $pid);
            if ($own === null) {
                // 获得拥有训练信息失败 -2
                $return['status'] = -2;
                return $return;
            }
            if ($own['gid'] !== 0) {
                // 训练位被占用 3
                $return['status'] = 3;
                return $return;
            }
            // [训练模型]获得训练规则
            $info = Practice::typeList((int)$member['level'])[$type] ?? null;
            if ($info === null) {
                // 获得训练费用失败 -3
                $return['status'] = -3;
                return $return;
            }
            if ($info['paytype'] === 1 && !Member::deductCurrency($uid, silver: $info['cost'])) {
                // 银币不足 4
                $return['status'] = 4;
                return $return;
            }
            if ($info['paytype'] === 2 && !Member::deductCurrency($uid, gold: $info['cost'])) {
                // 金币不足 5
                $return['status'] = 5;
                return $return;
            }
            // [训练模型]设置训练时间
            $result = Practice::updateTime($uid, [
                'pid' => $pid,
                'gid' => $gid,
                'type' => $type,
                'hour' => $info['hour'],
                'practicenow' => (int)$field['practicenow'],
                'practicetime' => (int)$field['practicetime'],
            ], $now);
            if ($result['status'] !== 1) {
                // 记录开始时间失败 -6
                $return['status'] = -6;
                return $return;
            }
            if ((int)$field['practiceall'] - (int)$field['practicenow'] > 1) {
                $time = 0;
            } else {
                $time = max(0, (int)$field['practicetime'] - $now);
            }
            $after = self::member($uid);
            $return['status'] = 1;
            $return['info'] = [
                'gid' => $result['info']['gid'],
                'cd' => $result['info']['starttime'] !== 0 ? $result['info']['endtime'] - $result['info']['starttime'] : 0,
                'type' => $result['info']['type'],
                'cost' => $info['cost'],
                'paytype' => $info['paytype'],
                'id' => $own['id'],
                'squence' => $own['squence'],
                'level' => $own['level'],
                'practicetime' => $time,
                'gold' => (int)$after['gold'],
                'silver' => (int)$after['silver'],
            ];
            if ($info['paytype'] === 2) {
                // 消费统计记录
                StatPay::record($uid, 'xunlian', $info['cost']);
            }
            return $return;
        });
    }

    /**
     * 训练位升级: c=practice&m=levelup（pid）
     */
    public static function levelup(Context $ctx): array
    {
        /**
         * -4 升级训练位失败
         * -2 获取规则失败
         * -1 参数错误
         *  0 失败
         *  1 成功
         *  2 训练位已满级
         *  3 vip等级不足
         *  4 金币不足
         */
        $uid = MemberHandler::resolveUid($ctx);
        $pid = (int)$ctx->input('pid', 0);
        if ($pid === 0) {
            // 参数错误 -1
            return ['status' => -1, 'info' => []];
        }
        if (self::member($uid) === null) {
            return ['status' => 0, 'info' => []];
        }
        return Member::locked($uid, static function () use ($uid, $pid): array {
            $return = ['status' => 0, 'info' => []];
            $maxLevel = self::vipRule($uid, 'practicelevel', 1);
            // [训练模型]训练位升级规则(训练位id)
            $rule = Practice::levelupRule($uid, $pid, $maxLevel);
            if ($rule['status'] !== 1) {
                $return['status'] = match ($rule['status']) {
                    // 训练位等级已满
                    2 => 2,
                    // vip等级不足 返回3
                    3 => 3,
                    // 获取规则失败 -2
                    default => -2,
                };
                return $return;
            }
            // 升级价格
            $gold = $rule['info']['price'];
            if (!Member::deductCurrency($uid, gold: $gold)) {
                // 金币不足 4
                $return['status'] = 4;
                return $return;
            }
            // 升级训练位
            if (Practice::levelup($uid, $pid, $rule['info']['level']) !== 1) {
                $return['status'] = -4;
                return $return;
            }
            $return['info'] = [
                'id' => $pid,
                'name' => $rule['info']['name'],
                'level' => $rule['info']['level'],
                'price' => $rule['info']['price'],
                'gold' => (int)self::member($uid)['gold'],
            ];
            $return['status'] = 1;
            // 主线任务 5任意阵法升到X级（旧版此处传训练位等级）
            MainQuestTask::action($uid, 5, $rule['info']['level']);
            // 消费统计记录
            StatPay::record($uid, 'up_xunlianwei', $gold);
            return $return;
        });
    }

    /**
     * 获得升级价格: c=practice&m=get_levelup_price（pid）
     */
    public static function get_levelup_price(Context $ctx): array
    {
        /**
         * -2 获得等级信息失败
         * -1 获得训练位信息失败
         *  0 失败
         *  1 成功
         *  2 vip等级不足
         */
        $return = [];
        $uid = MemberHandler::resolveUid($ctx);
        $pid = (int)$ctx->input('pid', 0);
        $own = Practice::own($uid, $pid);
        $maxLevel = self::vipRule($uid, 'practicelevel', 1);
        if ($own === null) {
            // 获得拥有训练位信息失败 -1
            $return['status'] = -1;
            return $return;
        }
        $level = $own['level'] + 1;
        if ($level > $maxLevel) {
            // vip等级不足 2
            $return['status'] = 2;
            return $return;
        }
        $price = Practice::LEVEL_INFO[$level] ?? null;
        if ($price === null) {
            // 获得等级信息失败 -2
            $return['status'] = -2;
            return $return;
        }
        $return['status'] = 1;
        $return['info'] = [
            'level' => $level,
            'gold' => $price['price'],
            'result' => $price['result'],
        ];
        return $return;
    }

    /**
     * 终止训练: c=practice&m=practice_stop（pid；旧版不结算这次训练的经验）
     */
    public static function practice_stop(Context $ctx): array
    {
        /**
         * -2 获取扩展信息失败
         * -1 终止训练失败
         *  0 失败
         *  1 成功
         */
        $uid = MemberHandler::resolveUid($ctx);
        $pid = (int)$ctx->input('pid', 0);
        if (self::member($uid) === null) {
            return ['status' => 0];
        }
        $status = Member::locked($uid, static function () use ($uid, $pid): int {
            $field = self::field($uid);
            if ($field === null) {
                // 获取扩展信息失败 -2
                return -2;
            }
            if (Practice::practiceStop($uid, $pid, (int)$field['practicetime'], (int)$field['practicenow']) !== 1) {
                // 终止训练失败 -1
                return -1;
            }
            return 1;
        });
        return ['status' => $status];
    }

    /**
     * VIP 规则值（vip_model->practicenum / practicelevel：VIP 0 或没有 vip_log 时取 $default，否则取 Vip 表）
     */
    private static function vipRule(int $uid, string $field, int $default): int
    {
        $log = Vip::log($uid);
        if ($log === null || $log['level'] === 0) {
            return $default;
        }
        return (int)(Vip::info()[$log['level']][$field] ?? $default);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function member(int $uid): ?array
    {
        return $uid > 0 ? DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first() : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function field(int $uid): ?array
    {
        return DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->first();
    }
}
