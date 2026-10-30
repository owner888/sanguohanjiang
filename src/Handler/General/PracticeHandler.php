<?php

declare(strict_types=1);

namespace Hanjiang\Handler\General;

use Hanjiang\Handler\City\MemberHandler;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 校场演武与武将挂机训练/突飞猛进处理器 (DDD General Domain)
 */
final class PracticeHandler
{
    /**
     * 训练位强化等级字典
     */
    public const array LEVEL_INFO = [
        1 => ['name' => '普通', 'result' => 100, 'price' => 0],
        2 => ['name' => '加强', 'result' => 150, 'price' => 50],
        3 => ['name' => '白金', 'result' => 200, 'price' => 100],
        4 => ['name' => '极品', 'result' => 250, 'price' => 200],
        5 => ['name' => '至尊', 'result' => 300, 'price' => 300],
        6 => ['name' => '神级', 'result' => 400, 'price' => 600],
    ];

    /**
     * 训练位解锁价格与 VIP 限制字典 (序号 3~8)
     */
    public const array SQUENCE_PRICES = [
        3 => ['gold' => 50, 'vip' => 0],
        4 => ['gold' => 100, 'vip' => 1],
        5 => ['gold' => 500, 'vip' => 2],
        6 => ['gold' => 1000, 'vip' => 4],
        7 => ['gold' => 2000, 'vip' => 6],
        8 => ['gold' => 5000, 'vip' => 7],
    ];

    /**
     * 校场主界面初始化: c=practice&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        if ($member === null) {
            return [
                'status' => 0,
                'message' => '玩家数据不存在',
            ];
        }

        $memberLevel = max(1, (int)$member['level']);
        $field = self::getOrCreateMemberField($uid);
        $practiceAll = max(2, min(8, (int)($field['practiceall'] ?? 2)));

        // 1. 初始化并同步开启的训练位
        self::ensurePracticeSlots($uid, $practiceAll);

        // 2. 结算训练中的经验进度
        self::updatePracticesProgress($uid, $memberLevel);

        // 3. 读取并装配武将出征列表 (按出征优先级与等级排序)
        $generals = DB::table('general')
            ->where('uid', $uid)
            ->whereNull('deleted_at')
            ->get();

        if (empty($generals)) {
            return [
                'status' => 2,
                'message' => '没有出征将领',
            ];
        }

        // 优先出征武将 (goout = 1)，按等级降序、转生降序排序
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

        // 获取训练位数据
        $practiceSlots = DB::table('practice')
            ->where('uid', $uid)
            ->whereNull('deleted_at')
            ->get();

        $activeSlotMap = []; // gid => slot
        $slotBySquence = []; // squence => slot
        foreach ($practiceSlots as $slot) {
            $seq = (int)$slot['squence'];
            $slotBySquence[$seq] = $slot;
            $gid = (int)$slot['generalid'];
            if ($gid > 0) {
                $activeSlotMap[$gid] = $slot;
            }
        }

        // 4. 构建出征武将字典 (1-based: 1..8)
        $heroList = [];
        $heroIdx = 1;
        $generalMap = [];
        foreach ($generals as $g) {
            $gid = (int)$g['id'];
            $generalMap[$gid] = $g;

            if ($heroIdx <= 8) {
                $isPracticing = isset($activeSlotMap[$gid]);
                $pid = $isPracticing ? (int)$activeSlotMap[$gid]['id'] : 0;
                $gLevel = max(1, (int)$g['level']);
                $gTurn = (int)($g['turn'] ?? 0);
                $isTurn = ($gLevel >= 100) ? 1 : 0;

                $heroList[(string)$heroIdx] = [
                    'id' => $gid,
                    'name' => (string)$g['name'],
                    'quality' => (int)($g['quality'] ?? 3),
                    'level' => $gLevel,
                    'exp' => (int)$g['exp'],
                    'levelupexp' => GeneralHandler::getLevelUpExp($gLevel),
                    'image' => (int)($g['image'] ?? $g['generalid'] ?? 1),
                    'type' => (int)($g['type'] ?? 1),
                    'turn' => $gTurn,
                    'isturn' => $isTurn,
                    'ratio' => 1,
                    'pid' => $pid,
                    'ispractice' => $isPracticing ? 1 : 0,
                ];
                $heroIdx++;
            }
        }

        // 5. 构建训练位字典 (1-based: 1..8)
        $now = time();
        $placeList = [];
        for ($i = 1; $i <= $practiceAll; $i++) {
            $slot = $slotBySquence[$i] ?? null;
            if ($slot === null) {
                continue;
            }

            $gid = (int)$slot['generalid'];
            $general = $gid > 0 ? ($generalMap[$gid] ?? null) : null;
            $endTime = (int)($slot['endtime'] ?? 0);
            $cd = ($gid > 0 && $endTime > $now) ? ($endTime - $now) : 0;

            $placeList[(string)$i] = [
                'id' => (int)$slot['id'],
                'gid' => $gid,
                'image' => $general ? (int)($general['image'] ?? $general['generalid'] ?? 1) : 0,
                'squence' => $i,
                'level' => (int)$slot['level'],
                'type' => (int)$slot['type'],
                'quality' => $general ? (int)($general['quality'] ?? 3) : 0,
                'cd' => $cd,
            ];
        }

        // 6. 训练规则 (1-based: 1..4)
        $ruleList = [
            '1' => ['paytype' => 1, 'hour' => 8, 'cost' => 100 * $memberLevel],
            '2' => ['paytype' => 1, 'hour' => 24, 'cost' => 255 * $memberLevel],
            '3' => ['paytype' => 2, 'hour' => 24, 'cost' => 2],
            '4' => ['paytype' => 2, 'hour' => 72, 'cost' => 20],
        ];

        // 7. 突飞花费与免费次数
        $freeTimes = (int)($field['freeleap'] ?? 10);
        $leapTimes = (int)($field['leaptimes'] ?? 0);
        $leapCost = ($freeTimes > 0) ? 0 : min(50, 2 + $leapTimes * 2);

        return [
            'status' => 1,
            'maxnum' => 8,
            'opennum' => $practiceAll,
            'list' => $heroList,
            'place' => $placeList,
            'rule' => $ruleList,
            'freetimes' => $freeTimes,
            'leap' => $leapCost,
            'mop' => [5, 10, 50, 100],
            'message' => 'ok',
        ];
    }

    /**
     * 开始训练: c=practice&m=practice_start
     */
    public static function practice_start(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $pid = (int)($ctx->input('pid', 1));
        $gid = (int)($ctx->input('gid', 0));
        $type = (int)($ctx->input('type', 1));

        if ($gid <= 0) {
            return ['status' => 0, 'message' => '请选择需要训练的武将'];
        }

        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        if ($member === null) {
            return ['status' => 0, 'message' => '玩家不存在'];
        }

        $general = DB::table('general')->where('uid', $uid)->where('id', $gid)->whereNull('deleted_at')->first();
        if ($general === null) {
            return ['status' => -1, 'message' => '武将不存在'];
        }

        $memberLevel = max(1, (int)$member['level']);
        $generalLevel = (int)$general['level'];

        if ($generalLevel >= $memberLevel && (int)$general['exp'] >= GeneralHandler::getLevelUpExp($generalLevel)) {
            return ['status' => 7, 'message' => '武将等级已达主公等级上限'];
        }

        // 检查武将是否已在训练中
        $isTraining = DB::table('practice')
            ->where('uid', $uid)
            ->where('generalid', $gid)
            ->whereNull('deleted_at')
            ->first();
        if ($isTraining !== null) {
            return ['status' => 6, 'message' => '该武将已在训练中'];
        }

        // 查找指定训练位 (支持通过 id 或 squence 定位)
        $slot = DB::table('practice')
            ->where('uid', $uid)
            ->where('id', $pid)
            ->whereNull('deleted_at')
            ->first();

        if ($slot === null) {
            $slot = DB::table('practice')
                ->where('uid', $uid)
                ->where('squence', $pid)
                ->whereNull('deleted_at')
                ->first();
        }

        if ($slot === null) {
            return ['status' => -2, 'message' => '未找到对应的训练位'];
        }

        if ((int)$slot['generalid'] > 0) {
            return ['status' => 3, 'message' => '该训练位已被占用'];
        }

        // 计算训练时长与扣费规则
        $rule = self::getPracticeRule($type, $memberLevel);
        if ($rule['paytype'] === 1) {
            if ((int)$member['silver'] < $rule['cost']) {
                return ['status' => 4, 'message' => '银两不足'];
            }
            $remainSilver = (int)$member['silver'] - $rule['cost'];
            $remainGold = (int)$member['gold'];
            DB::table('member')->where('uid', $uid)->update(['silver' => $remainSilver]);
        } else {
            if ((int)$member['gold'] < $rule['cost']) {
                return ['status' => 5, 'message' => '元宝不足'];
            }
            $remainGold = (int)$member['gold'] - $rule['cost'];
            $remainSilver = (int)$member['silver'];
            DB::table('member')->where('uid', $uid)->update(['gold' => $remainGold]);
        }

        $now = time();
        $durationSeconds = $rule['hour'] * 3600;
        $endTime = $now + $durationSeconds;

        DB::table('practice')->where('id', (int)$slot['id'])->update([
            'generalid' => $gid,
            'type' => $type,
            'starttime' => $now,
            'endtime' => $endTime,
        ]);

        // 更新 member_field 的活跃训练位计数
        $practicingCount = DB::table('practice')
            ->where('uid', $uid)
            ->where('generalid', '>', 0)
            ->whereNull('deleted_at')
            ->count();

        DB::table('member_field')->where('uid', $uid)->update([
            'practicenow' => $practicingCount,
            'practicetime' => $endTime,
        ]);

        Redis::del('player:' . $uid);

        $practiceAll = max(2, (int)($field['practiceall'] ?? 2));
        $emptySlots = $practiceAll - $practicingCount;
        $practiceTimeCd = ($emptySlots > 0) ? 0 : $durationSeconds;

        return [
            'status' => 1,
            'info' => [
                'id' => (int)$slot['id'],
                'squence' => (int)$slot['squence'],
                'level' => (int)$slot['level'],
                'gid' => $gid,
                'type' => $type,
                'cost' => $rule['cost'],
                'paytype' => $rule['paytype'],
                'cd' => $durationSeconds,
                'practicetime' => $practiceTimeCd,
                'gold' => $remainGold,
                'silver' => $remainSilver,
            ],
            'message' => '训练开始',
        ];
    }

    /**
     * 终止训练: c=practice&m=practice_stop
     */
    public static function practice_stop(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $pid = (int)($ctx->input('pid', 1));

        $slot = DB::table('practice')
            ->where('uid', $uid)
            ->where('id', $pid)
            ->whereNull('deleted_at')
            ->first();

        if ($slot === null) {
            $slot = DB::table('practice')
                ->where('uid', $uid)
                ->where('squence', $pid)
                ->whereNull('deleted_at')
                ->first();
        }

        if ($slot === null || (int)$slot['generalid'] <= 0) {
            return ['status' => 1, 'message' => '训练已终止'];
        }

        $gid = (int)$slot['generalid'];
        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        $memberLevel = max(1, (int)($member['level'] ?? 1));

        // 结算并赋予已积累的经验
        $earnedExp = self::settleSlotExp($uid, $slot, $memberLevel);

        // 重置训练位
        DB::table('practice')->where('id', (int)$slot['id'])->update([
            'generalid' => 0,
            'starttime' => 0,
            'endtime' => 0,
            'type' => 0,
        ]);

        $practicingCount = DB::table('practice')
            ->where('uid', $uid)
            ->where('generalid', '>', 0)
            ->whereNull('deleted_at')
            ->count();

        DB::table('member_field')->where('uid', $uid)->update([
            'practicenow' => $practicingCount,
        ]);

        Redis::del('player:' . $uid);

        return [
            'status' => 1,
            'earned_exp' => max(1, $earnedExp),
            'message' => '训练已终止',
        ];
    }

    /**
     * 将领所在的训练位（移植旧版 practice_model::check_practice）
     *
     * @return int 训练位 id，0 为不在训练
     */
    public static function checkPractice(int $uid, int $gid): int
    {
        return (int)(DB::table('practice')
            ->where('uid', $uid)
            ->where('generalid', $gid)
            ->whereNull('deleted_at')
            ->value('id') ?? 0);
    }

    /**
     * 终止训练（移植旧版 practice_model::practice_stop，供 c=muster&m=go_rest 使用）
     *
     * 清空训练位；训练位结束时间等于 member_field.practicetime 时改为其余训练中位置的最早结束时间（没有则 0），
     * practicenow > 0 时减 1。旧版这里算出的经验只放在返回值里、不发给将领（go_rest 也不发），这里同样不发。
     * c=practice&m=practice_stop 仍是新版实现，与本方法无关。
     *
     * @return int 1 成功；-5 更新扩展表失败；-4 获取训练位等级失败；-3 没有武将；-2 获取训练信息失败；-1 更新训练位失败
     */
    public static function legacyStop(int $uid, int $pid, int $practicetime, int $practicenow): int
    {
        $info = DB::table('practice')->where('uid', $uid)->where('id', $pid)->whereNull('deleted_at')->first();
        if ($info === null) {
            return -2;
        }
        if ((int)$info['generalid'] === 0) {
            return -3;
        }
        $end = (int)$info['endtime'];
        if (!isset(self::LEVEL_INFO[(int)$info['level']])) {
            return -4;
        }

        $affected = DB::table('practice')->where('uid', $uid)->where('id', $pid)->whereNull('deleted_at')->update([
            'type' => 0,
            'generalid' => 0,
            'starttime' => 0,
            'endtime' => 0,
        ]);
        if ($affected <= 0) {
            return -1;
        }

        $result = [];
        if ($end === $practicetime) {
            $endarr = array_map(
                static fn (array $row): int => (int)$row['endtime'],
                DB::table('practice')->where('uid', $uid)->where('generalid', '>', 0)->whereNull('deleted_at')->get(),
            );
            $result['practicetime'] = $endarr === [] ? 0 : min($endarr);
        }
        if ($practicenow > 0) {
            $result['practicenow'] = $practicenow - 1;
        }
        // 旧版 $result 为空时 CI 不执行 UPDATE，affected_rows 仍是上一条训练位更新的结果（> 0），视为成功
        if ($result !== []
            && DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->update($result) === 0) {
            return -5;
        }

        Redis::del('player:' . $uid);
        return 1;
    }

    /**
     * 单次突飞猛进: c=practice&m=go_leap
     */
    public static function go_leap(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $gid = (int)($ctx->input('gid', 0));

        if ($gid <= 0) {
            return ['status' => -3, 'message' => '参数错误'];
        }

        $slot = DB::table('practice')
            ->where('uid', $uid)
            ->where('generalid', $gid)
            ->whereNull('deleted_at')
            ->first();

        $general = DB::table('general')->where('uid', $uid)->where('id', $gid)->whereNull('deleted_at')->first();
        if ($general === null) {
            return ['status' => -8, 'message' => '获取将领信息失败'];
        }

        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        if ($member === null) {
            return ['status' => 0, 'message' => '玩家数据异常'];
        }

        $memberLevel = max(1, (int)$member['level']);
        $gLevel = (int)$general['level'];
        $gExp = (int)$general['exp'];
        $nextExp = GeneralHandler::getLevelUpExp($gLevel);

        if ($gLevel >= $memberLevel && $gExp >= $nextExp) {
            return ['status' => 3, 'message' => '等级已达主公等级上限'];
        }

        $field = self::getOrCreateMemberField($uid);
        $freeLeap = (int)($field['freeleap'] ?? 0);
        $leapTimes = (int)($field['leaptimes'] ?? 0);
        $remainGold = (int)$member['gold'];

        if ($freeLeap > 0) {
            // 消耗突飞卡
            $remainFreeLeap = $freeLeap - 1;
            DB::table('member_field')->where('uid', $uid)->update(['freeleap' => $remainFreeLeap]);
        } else {
            // 消耗金币
            $costGold = min(50, 2 + $leapTimes * 2);
            if ($remainGold < $costGold) {
                return ['status' => 2, 'message' => '金币不足'];
            }
            $remainGold -= $costGold;
            $leapTimes++;
            DB::table('member')->where('uid', $uid)->update(['gold' => $remainGold]);
            DB::table('member_field')->where('uid', $uid)->update(['leaptimes' => $leapTimes]);
            $remainFreeLeap = 0;
        }

        // 计算突飞经验 (主公等级 * 562.5，VIP 5+ 概率暴击双倍)
        $vip = (int)($member['vip'] ?? 1);
        $isCrit = ($vip >= 5 && mt_rand(1, 100) <= 25);
        $addition = $isCrit ? 2 : 1;
        $addExp = max(500, (int)ceil($memberLevel * 562.5 * $addition));

        // 加经验并升级
        $curExp = $gExp + $addExp;
        $curLevel = $gLevel;
        while ($curLevel < $memberLevel && $curExp >= GeneralHandler::getLevelUpExp($curLevel)) {
            $curExp -= GeneralHandler::getLevelUpExp($curLevel);
            $curLevel++;
        }

        // 到达主公等级上限时截断溢出经验
        if ($curLevel >= $memberLevel) {
            $curLevel = $memberLevel;
            $curExp = min($curExp, GeneralHandler::getLevelUpExp($curLevel));
        }

        DB::table('general')->where('id', $gid)->update([
            'level' => $curLevel,
            'exp' => $curExp,
        ]);

        $nextCost = ($remainFreeLeap > 0) ? 0 : min(50, 2 + $leapTimes * 2);
        Redis::del('player:' . $uid);

        return [
            'status' => 1,
            'add_exp' => $addExp,
            'level' => $curLevel,
            'exp' => $curExp,
            'info' => [
                'gid' => $gid,
                'level' => $curLevel,
                'exp' => $curExp,
                'levelupexp' => GeneralHandler::getLevelUpExp($curLevel),
                'addexp' => $addExp,
                'isturn' => ($curLevel >= 100) ? 1 : 0,
                'ratio' => 1,
                'gold' => $remainGold,
                'next' => $nextCost,
                'freetimes' => $remainFreeLeap,
                'hit' => $isCrit ? 1 : 0,
            ],
            'message' => '突飞成功',
        ];
    }

    /**
     * 突飞扫荡: c=practice&m=mop
     */
    public static function mop(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $gid = (int)($ctx->input('gid', 0));
        $times = (int)($ctx->input('times', 5));

        if ($gid <= 0 || $times <= 0) {
            return ['status' => -1, 'message' => '参数错误'];
        }

        $slot = DB::table('practice')
            ->where('uid', $uid)
            ->where('generalid', $gid)
            ->whereNull('deleted_at')
            ->first();

        if ($slot === null) {
            return ['status' => -2, 'message' => '该武将不在训练位上'];
        }

        $general = DB::table('general')->where('uid', $uid)->where('id', $gid)->whereNull('deleted_at')->first();
        if ($general === null) {
            return ['status' => -3, 'message' => '获取将领信息失败'];
        }

        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        $memberLevel = max(1, (int)($member['level'] ?? 1));

        $field = self::getOrCreateMemberField($uid);
        $freeLeap = (int)($field['freeleap'] ?? 0);

        if ($times > $freeLeap) {
            return ['status' => 2, 'message' => '突飞次数不足'];
        }

        $gLevel = (int)$general['level'];
        $gExp = (int)$general['exp'];
        if ($gLevel >= $memberLevel && $gExp >= GeneralHandler::getLevelUpExp($gLevel)) {
            return ['status' => 3, 'message' => '等级已达主公等级上限'];
        }

        // 扣除突飞卡
        $remainFreeLeap = $freeLeap - $times;
        DB::table('member_field')->where('uid', $uid)->update(['freeleap' => $remainFreeLeap]);

        $baseExp = (int)floor($memberLevel * 562.5 * $times);
        $vip = (int)($member['vip'] ?? 1);
        $critRate = ($vip >= 5) ? mt_rand(10, 20) : 0;
        $extExp = (int)floor($baseExp * $critRate / 100);
        $totalAddExp = $baseExp + $extExp;

        $curExp = $gExp + $totalAddExp;
        $curLevel = $gLevel;
        while ($curLevel < $memberLevel && $curExp >= GeneralHandler::getLevelUpExp($curLevel)) {
            $curExp -= GeneralHandler::getLevelUpExp($curLevel);
            $curLevel++;
        }

        if ($curLevel >= $memberLevel) {
            $curLevel = $memberLevel;
            $curExp = min($curExp, GeneralHandler::getLevelUpExp($curLevel));
        }

        DB::table('general')->where('id', $gid)->update([
            'level' => $curLevel,
            'exp' => $curExp,
        ]);

        $leapTimes = (int)($field['leaptimes'] ?? 0);
        $nextCost = ($remainFreeLeap > 0) ? 0 : min(50, 2 + $leapTimes * 2);

        Redis::del('player:' . $uid);

        return [
            'status' => 1,
            'general' => [
                'level' => $curLevel,
                'exp' => $curExp,
                'levelupexp' => GeneralHandler::getLevelUpExp($curLevel),
            ],
            'exp' => $baseExp,
            'ext_exp' => $extExp,
            'freeleap' => $remainFreeLeap,
            'next' => $nextCost,
        ];
    }

    /**
     * 获取训练位升级价格: c=practice&m=get_levelup_price
     */
    public static function get_levelup_price(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $pid = (int)($ctx->input('pid', 1));

        $slot = DB::table('practice')
            ->where('uid', $uid)
            ->where('id', $pid)
            ->whereNull('deleted_at')
            ->first();

        if ($slot === null) {
            $slot = DB::table('practice')
                ->where('uid', $uid)
                ->where('squence', $pid)
                ->whereNull('deleted_at')
                ->first();
        }

        if ($slot === null) {
            return ['status' => -1, 'message' => '获得拥有训练位信息失败'];
        }

        $nextLevel = (int)$slot['level'] + 1;
        if ($nextLevel > 6) {
            return ['status' => 2, 'message' => '训练位已达最高等级'];
        }

        $info = self::LEVEL_INFO[$nextLevel] ?? null;
        if ($info === null) {
            return ['status' => -2, 'message' => '获取升级规则失败'];
        }

        return [
            'status' => 1,
            'info' => [
                'level' => $nextLevel,
                'gold' => $info['price'],
                'result' => $info['result'],
            ],
        ];
    }

    /**
     * 升级训练位: c=practice&m=levelup
     */
    public static function levelup(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $pid = (int)($ctx->input('pid', 1));

        $slot = DB::table('practice')
            ->where('uid', $uid)
            ->where('id', $pid)
            ->whereNull('deleted_at')
            ->first();

        if ($slot === null) {
            $slot = DB::table('practice')
                ->where('uid', $uid)
                ->where('squence', $pid)
                ->whereNull('deleted_at')
                ->first();
        }

        if ($slot === null) {
            return ['status' => -1, 'message' => '训练位不存在'];
        }

        $nextLevel = (int)$slot['level'] + 1;
        if ($nextLevel > 6) {
            return ['status' => 2, 'message' => '训练位已满级'];
        }

        $rule = self::LEVEL_INFO[$nextLevel] ?? null;
        if ($rule === null) {
            return ['status' => -2, 'message' => '规则不存在'];
        }

        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        $costGold = $rule['price'];
        if ((int)($member['gold'] ?? 0) < $costGold) {
            return ['status' => 4, 'message' => '金币不足'];
        }

        $remainGold = (int)$member['gold'] - $costGold;
        DB::table('member')->where('uid', $uid)->update(['gold' => $remainGold]);
        DB::table('practice')->where('id', (int)$slot['id'])->update(['level' => $nextLevel]);

        Redis::del('player:' . $uid);

        return [
            'status' => 1,
            'info' => [
                'id' => (int)$slot['id'],
                'level' => $nextLevel,
                'price' => $costGold,
                'name' => $rule['name'],
                'gold' => $remainGold,
            ],
            'message' => '升级成功',
        ];
    }

    /**
     * 获取解锁训练位价格: c=practice&m=get_unlock_price
     */
    public static function get_unlock_price(Context $ctx): array
    {
        $squence = (int)($ctx->input('squence', 3));
        $price = self::SQUENCE_PRICES[$squence] ?? null;

        if ($price === null) {
            return ['status' => -1, 'message' => '训练位序号不合法'];
        }

        return [
            'status' => 1,
            'gold' => $price['gold'],
            'vip' => $price['vip'],
        ];
    }

    /**
     * 解锁新训练位: c=practice&m=unlock
     */
    public static function unlock(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        $field = self::getOrCreateMemberField($uid);

        $currentAll = max(2, (int)($field['practiceall'] ?? 2));
        $nextSquence = $currentAll + 1;

        if ($nextSquence > 8) {
            return ['status' => 0, 'message' => '训练位已达上限'];
        }

        $priceInfo = self::SQUENCE_PRICES[$nextSquence] ?? null;
        if ($priceInfo === null) {
            return ['status' => -2, 'message' => '价格配置异常'];
        }

        $costGold = (int)$priceInfo['gold'];
        if ((int)($member['gold'] ?? 0) < $costGold) {
            return ['status' => 2, 'message' => '元宝不足'];
        }

        $remainGold = (int)$member['gold'] - $costGold;
        DB::table('member')->where('uid', $uid)->update(['gold' => $remainGold]);
        DB::table('member_field')->where('uid', $uid)->update(['practiceall' => $nextSquence]);

        // 插入或激活训练位记录
        $existing = DB::table('practice')
            ->where('uid', $uid)
            ->where('squence', $nextSquence)
            ->first();

        if ($existing !== null) {
            DB::table('practice')->where('id', (int)$existing['id'])->update([
                'deleted_at' => null,
                'level' => 1,
                'generalid' => 0,
                'type' => 1,
                'starttime' => 0,
                'endtime' => 0,
            ]);
            $slotId = (int)$existing['id'];
        } else {
            DB::table('practice')->insert([
                'uid' => $uid,
                'squence' => $nextSquence,
                'generalid' => 0,
                'level' => 1,
                'type' => 1,
                'starttime' => 0,
                'endtime' => 0,
            ]);
            $newSlot = DB::table('practice')
                ->where('uid', $uid)
                ->where('squence', $nextSquence)
                ->first();
            $slotId = (int)($newSlot['id'] ?? $nextSquence);
        }

        Redis::del('player:' . $uid);

        return [
            'status' => 1,
            'info' => [
                'id' => $slotId,
                'squence' => $nextSquence,
                'level' => 1,
                'gold' => $remainGold,
            ],
            'message' => '解锁成功',
        ];
    }

    /**
     * 武将转生: c=practice&m=turn
     */
    public static function turn(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $gid = (int)($ctx->input('gid', 0));

        if ($gid <= 0) {
            return ['status' => -1, 'message' => '参数错误'];
        }

        $general = DB::table('general')->where('uid', $uid)->where('id', $gid)->whereNull('deleted_at')->first();
        if ($general === null) {
            return ['status' => -2, 'message' => '武将不存在'];
        }

        $level = (int)$general['level'];
        if ($level < 100) {
            return ['status' => 3, 'message' => '等级不足100级，暂不可转生'];
        }

        $newTurn = (int)($general['turn'] ?? 0) + 1;
        DB::table('general')->where('id', $gid)->update([
            'level' => 1,
            'exp' => 0,
            'turn' => $newTurn,
        ]);

        Redis::del('player:' . $uid);

        return [
            'status' => 1,
            'info' => [
                'id' => $gid,
                'level' => 1,
                'exp' => 0,
                'turn' => $newTurn,
            ],
            'message' => '转生成功',
        ];
    }

    /**
     * 保证训练位记录数量满足 practiceAll
     */
    private static function ensurePracticeSlots(int $uid, int $practiceAll): void
    {
        for ($i = 1; $i <= $practiceAll; $i++) {
            $slot = DB::table('practice')
                ->where('uid', $uid)
                ->where('squence', $i)
                ->first();

            if ($slot === null) {
                DB::table('practice')->insert([
                    'uid' => $uid,
                    'squence' => $i,
                    'generalid' => 0,
                    'level' => 1,
                    'type' => 1,
                    'starttime' => 0,
                    'endtime' => 0,
                ]);
            } elseif ($slot['deleted_at'] !== null) {
                DB::table('practice')->where('id', (int)$slot['id'])->update([
                    'deleted_at' => null,
                ]);
            }
        }
    }

    /**
     * 结算挂机训练位的经验积累
     */
    private static function updatePracticesProgress(int $uid, int $memberLevel): void
    {
        $slots = DB::table('practice')
            ->where('uid', $uid)
            ->where('generalid', '>', 0)
            ->whereNull('deleted_at')
            ->get();

        if (empty($slots)) {
            return;
        }

        $now = time();
        $endedCount = 0;
        $minEndTime = 0;

        foreach ($slots as $slot) {
            $gid = (int)$slot['generalid'];
            $startTime = (int)$slot['starttime'];
            $endTime = (int)$slot['endtime'];

            if ($startTime <= 0) {
                continue;
            }

            self::settleSlotExp($uid, $slot, $memberLevel);

            if ($now >= $endTime) {
                // 训练已到期，恢复空闲状态
                DB::table('practice')->where('id', (int)$slot['id'])->update([
                    'generalid' => 0,
                    'starttime' => 0,
                    'endtime' => 0,
                    'type' => 0,
                ]);
                $endedCount++;
            } else {
                // 训练进行中，推进结算起点
                DB::table('practice')->where('id', (int)$slot['id'])->update([
                    'starttime' => $now,
                ]);
                $minEndTime = ($minEndTime === 0) ? $endTime : min($minEndTime, $endTime);
            }
        }

        if ($endedCount > 0) {
            $activeCount = DB::table('practice')
                ->where('uid', $uid)
                ->where('generalid', '>', 0)
                ->whereNull('deleted_at')
                ->count();

            DB::table('member_field')->where('uid', $uid)->update([
                'practicenow' => $activeCount,
                'practicetime' => $minEndTime,
            ]);
        }
    }

    /**
     * 结算单个训练位到当前时间的经验
     *
     * @param array<string, mixed> $slot
     */
    private static function settleSlotExp(int $uid, array $slot, int $memberLevel): int
    {
        $gid = (int)$slot['generalid'];
        $startTime = (int)$slot['starttime'];
        $endTime = (int)$slot['endtime'];
        $now = time();

        if ($gid <= 0 || $startTime <= 0) {
            return 0;
        }

        $general = DB::table('general')->where('uid', $uid)->where('id', $gid)->whereNull('deleted_at')->first();
        if ($general === null) {
            return 0;
        }

        $seconds = max(0, min($now, $endTime) - $startTime);
        if ($seconds <= 0) {
            return 0;
        }

        $slotLevel = max(1, min(6, (int)$slot['level']));
        $ratio = match ($slotLevel) {
            1 => 1.0,
            2 => 1.5,
            3 => 2.0,
            4 => 2.5,
            5 => 3.0,
            6 => 4.0,
            default => 1.0,
        };

        // 经验公式: 主公等级 * (秒数/3600) * 112.5 * 强化倍率
        $earnedExp = (int)ceil($memberLevel * ($seconds / 3600.0) * 112.5 * $ratio);
        if ($earnedExp <= 0) {
            return 0;
        }

        $curExp = (int)$general['exp'] + $earnedExp;
        $curLevel = (int)$general['level'];

        while ($curLevel < $memberLevel && $curExp >= GeneralHandler::getLevelUpExp($curLevel)) {
            $curExp -= GeneralHandler::getLevelUpExp($curLevel);
            $curLevel++;
        }

        if ($curLevel >= $memberLevel) {
            $curLevel = $memberLevel;
            $curExp = min($curExp, GeneralHandler::getLevelUpExp($curLevel));
        }

        DB::table('general')->where('id', $gid)->update([
            'level' => $curLevel,
            'exp' => $curExp,
        ]);

        return $earnedExp;
    }

    /**
     * 根据训练类型获取时长与扣费配置
     *
     * @return array{paytype: int, hour: int, cost: int}
     */
    private static function getPracticeRule(int $type, int $memberLevel): array
    {
        return match ($type) {
            2 => ['paytype' => 1, 'hour' => 24, 'cost' => 255 * $memberLevel],
            3 => ['paytype' => 2, 'hour' => 24, 'cost' => 2],
            4 => ['paytype' => 2, 'hour' => 72, 'cost' => 20],
            default => ['paytype' => 1, 'hour' => 8, 'cost' => 100 * $memberLevel],
        };
    }

    /**
     * 获取或初始化玩家扩展字段记录
     *
     * @return array<string, mixed>
     */
    private static function getOrCreateMemberField(int $uid): array
    {
        $field = DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->first();
        if ($field === null) {
            DB::table('member_field')->insert([
                'uid' => $uid,
                'practiceall' => 2,
                'practicenow' => 0,
                'practicetime' => 0,
                'freeleap' => 10,
                'leaptimes' => 0,
            ]);
            $field = DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->first() ?? [];
        }
        return $field;
    }
}
