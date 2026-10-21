<?php

declare(strict_types=1);

namespace Hanjiang\Handler;

use OmniPHP\Http\Context;
use OmniPHP\Database\DB;
use OmniPHP\Cache\Redis;

final class PracticeHandler
{
    /**
     * 校场训练主界面: c=practice&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $practices = DB::table('practice')->where('uid', $uid)->get();

        if (empty($practices)) {
            // 初始化两个默认开启的训练位
            DB::table('practice')->insert([
                'uid' => $uid,
                'squence' => 1,
                'generalid' => 0,
                'level' => 1,
                'type' => 1,
                'starttime' => 0,
                'endtime' => 0,
            ]);

            DB::table('practice')->insert([
                'uid' => $uid,
                'squence' => 2,
                'generalid' => 0,
                'level' => 1,
                'type' => 1,
                'starttime' => 0,
                'endtime' => 0,
            ]);

            $practices = DB::table('practice')->where('uid', $uid)->get();
        }

        // 查询将领字典
        $generals = DB::table('general')->where('uid', $uid)->get();
        $generalMap = [];
        foreach ($generals as $g) {
            $generalMap[(int)$g['id']] = $g;
        }

        $now = time();
        $list = [];
        $idx = 1;
        foreach ($practices as $p) {
            $gid = (int)$p['generalid'];
            $startTime = (int)($p['starttime'] ?? 0);
            $general = $generalMap[$gid] ?? null;

            $elapsed = ($gid > 0 && $startTime > 0) ? max(0, $now - $startTime) : 0;
            $accumulatedExp = $elapsed * 2; // 每秒 2 点挂机经验

            $list[$idx] = [
                'id' => (int)$p['id'],
                'squence' => (int)$p['squence'],
                'generalid' => $gid,
                'generalname' => $general['name'] ?? '',
                'generallevel' => (int)($general['level'] ?? 0),
                'level' => (int)$p['level'],
                'type' => (int)$p['type'],
                'starttime' => $startTime,
                'elapsed' => $elapsed,
                'accumulated_exp' => $accumulatedExp,
            ];
            $idx++;
        }

        return [
            'status' => 1,
            'maxnum' => 6,
            'opennum' => 2,
            'list' => $list,
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
            return [
                'status' => 0,
                'message' => '请选择需要训练的武将',
            ];
        }

        // 验证武将
        $general = DB::table('general')->where('uid', $uid)->where('id', $gid)->first();
        if ($general === null) {
            return [
                'status' => -1,
                'message' => '武将不存在',
            ];
        }

        // 检查该武将是否已在训练中
        $training = DB::table('practice')
            ->where('uid', $uid)
            ->where('generalid', $gid)
            ->first();

        if ($training !== null) {
            return [
                'status' => 6,
                'message' => '该武将已在校场训练中',
            ];
        }

        // 检查训练位
        $slot = DB::table('practice')
            ->where('uid', $uid)
            ->where('squence', $pid)
            ->first();

        if ($slot === null) {
            // 插入训练位
            DB::table('practice')->insert([
                'uid' => $uid,
                'squence' => $pid,
                'generalid' => $gid,
                'level' => 1,
                'type' => $type,
                'starttime' => time(),
                'endtime' => time() + 28800,
            ]);
        } else {
            if ((int)$slot['generalid'] > 0) {
                return [
                    'status' => 3,
                    'message' => '该训练位已被其他武将占用',
                ];
            }

            DB::table('practice')->where('id', (int)$slot['id'])->update([
                'generalid' => $gid,
                'type' => $type,
                'starttime' => time(),
                'endtime' => time() + 28800,
            ]);
        }

        return [
            'status' => 1,
            'message' => '训练开始',
            'pid' => $pid,
            'gid' => $gid,
            'generalname' => $general['name'],
        ];
    }

    /**
     * 停止训练并结算经验: c=practice&m=practice_stop
     */
    public static function practice_stop(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $pid = (int)($ctx->input('pid', 1));

        $slot = DB::table('practice')
            ->where('uid', $uid)
            ->where('squence', $pid)
            ->first();

        if ($slot === null || (int)$slot['generalid'] <= 0) {
            return [
                'status' => 0,
                'message' => '该训练位未在训练中',
            ];
        }

        $gid = (int)$slot['generalid'];
        $general = DB::table('general')->where('uid', $uid)->where('id', $gid)->first();
        if ($general === null) {
            // 重置训练位
            DB::table('practice')->where('id', (int)$slot['id'])->update([
                'generalid' => 0,
                'starttime' => 0,
            ]);
            return [
                'status' => -1,
                'message' => '武将数据异常，训练位已重置',
            ];
        }

        $startTime = max(1, (int)$slot['starttime']);
        $elapsed = max(1, time() - $startTime);
        $earnedExp = $elapsed * 2; // 每秒 2 点经验

        $curExp = (int)$general['exp'] + $earnedExp;
        $curLevel = (int)$general['level'];

        // 升级逻辑
        while ($curExp >= $curLevel * 100) {
            $curExp -= $curLevel * 100;
            $curLevel++;
        }

        // 更新武将等级与经验
        DB::table('general')->where('id', $gid)->update([
            'level' => $curLevel,
            'exp' => $curExp,
        ]);

        // 重置训练位
        DB::table('practice')->where('id', (int)$slot['id'])->update([
            'generalid' => 0,
            'starttime' => 0,
        ]);

        return [
            'status' => 1,
            'message' => '训练已停止并完成经验结算',
            'pid' => $pid,
            'gid' => $gid,
            'generalname' => $general['name'],
            'earned_exp' => $earnedExp,
            'elapsed' => $elapsed,
            'level' => $curLevel,
            'exp' => $curExp,
        ];
    }

    /**
     * 突飞猛进: c=practice&m=go_leap
     */
    public static function go_leap(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $gid = (int)($ctx->input('gid', 1));
        $num = max(1, (int)($ctx->input('num', 1)));

        $general = DB::table('general')->where('uid', $uid)->where('id', $gid)->first();
        if ($general === null) {
            return [
                'status' => -1,
                'message' => '武将不存在',
            ];
        }

        $member = DB::table('member')->where('uid', $uid)->first();
        $costSilver = 100 * $num;

        if ((int)$member['silver'] < $costSilver) {
            return [
                'status' => 3,
                'message' => '银两不足，突飞猛进需要 ' . $costSilver . ' 银两',
            ];
        }

        $remainSilver = (int)$member['silver'] - $costSilver;
        DB::table('member')->where('uid', $uid)->update(['silver' => $remainSilver]);

        $addExp = 500 * $num;
        $curExp = (int)$general['exp'] + $addExp;
        $curLevel = (int)$general['level'];

        while ($curExp >= $curLevel * 100) {
            $curExp -= $curLevel * 100;
            $curLevel++;
        }

        DB::table('general')->where('id', $gid)->update([
            'level' => $curLevel,
            'exp' => $curExp,
        ]);

        Redis::del('player:' . $uid);

        return [
            'status' => 1,
            'add_exp' => $addExp,
            'level' => $curLevel,
            'exp' => $curExp,
            'cost_silver' => $costSilver,
            'silver' => $remainSilver,
            'message' => "突飞猛进成功，获得 {$addExp} 点武将经验",
        ];
    }
}
