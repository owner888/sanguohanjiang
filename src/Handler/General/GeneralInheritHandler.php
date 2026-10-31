<?php

declare(strict_types=1);

namespace Hanjiang\Handler\General;

use Hanjiang\Handler\City\MemberHandler;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 武将经验传承、洗髓资质转移与神将继承处理器 (DDD General Domain)
 */
final class GeneralInheritHandler
{
    /**
     * 4 阶继承模式规则
     */
    public const array INHERIT_RULES = [
        1 => ['id' => 1, 'name' => '普通继承', 'gold' => 0, 'exp' => 70, 'attr' => 70, 'rate' => 0.70],
        2 => ['id' => 2, 'name' => '加强继承', 'gold' => 50, 'exp' => 85, 'attr' => 85, 'rate' => 0.85],
        3 => ['id' => 3, 'name' => '白金继承', 'gold' => 100, 'exp' => 95, 'attr' => 95, 'rate' => 0.95],
        4 => ['id' => 4, 'name' => '至尊继承', 'gold' => 200, 'exp' => 100, 'attr' => 100, 'rate' => 1.00],
    ];

    /**
     * 传承主界面: c=general_inherit&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $generals = DB::table('general')->where('uid', $uid)->whereNull('deleted_at')->get();

        $list = [];
        $idx = 1;
        foreach ($generals as $g) {
            $gid = (int)$g['id'];
            $image = (string)($g['image'] ?? 'hero_1');
            if (empty($image)) {
                $image = 'hero_' . (int)$g['generalid'];
            }
            $list[$idx] = [
                'id' => $gid,
                'name' => (string)$g['name'],
                'quality' => (int)($g['quality'] ?? 1),
                'image' => $image,
                'level' => (int)$g['level'],
                'turn' => (int)($g['turn'] ?? 0),
                // 向下兼容保留原字段
                'exp' => (int)$g['exp'],
                'wuliup' => (int)($g['wuliup'] ?? 0),
                'zhiliup' => (int)($g['zhiliup'] ?? 0),
                'tiliup' => (int)($g['tiliup'] ?? 0),
            ];
            $idx++;
        }

        $ruleArr = [];
        foreach (self::INHERIT_RULES as $k => $r) {
            $ruleArr[$k] = [
                'id' => (int)$r['id'],
                'name' => (string)$r['name'],
                'gold' => (int)$r['gold'],
                'exp' => (int)$r['exp'],
                'attr' => (int)$r['attr'],
            ];
        }

        return [
            'status' => 1,
            'list' => $list,
            'rule' => $ruleArr,
            'endtime' => 0,
            'free_inherit' => [
                'inherit_1' => 1,
                'inherit_2' => 0,
                'inherit_3' => 0,
                'inherit_4' => 0,
            ],
            'free_site' => 0,
            // 向下兼容保留 modes 字典
            'modes' => [
                1 => ['type' => 1, 'name' => '普通传承', 'rate' => 0.8, 'cost_silver' => 10000, 'cost_gold' => 0, 'desc' => '保留 80% 经验与洗髓属性'],
                2 => ['type' => 2, 'name' => '至尊完璧传承', 'rate' => 1.0, 'cost_silver' => 0, 'cost_gold' => 50, 'desc' => '100% 完美无损保留全部经验与洗髓属性'],
            ],
            'message' => 'ok',
        ];
    }

    /**
     * 获取指定武将的继承规则与可传承经验: c=general_inherit&m=get_inherit_info
     */
    public static function get_inherit_info(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $gid = (int)($ctx->input('gid', 0));

        $fromGen = DB::table('general')->where('uid', $uid)->where('id', $gid)->whereNull('deleted_at')->first();
        if ($fromGen === null) {
            $fromGen = DB::table('general')->where('uid', $uid)->whereNull('deleted_at')->first();
        }

        $allExp = (int)($fromGen['exp'] ?? 0);

        $ruleArr = [];
        foreach (self::INHERIT_RULES as $k => $r) {
            $ruleArr[$k] = [
                'id' => (int)$r['id'],
                'name' => (string)$r['name'],
                'gold' => (int)$r['gold'],
                'exp' => (int)$r['exp'],
                'attr' => (int)$r['attr'],
                'allexp' => (int)round($allExp * (float)$r['rate']),
            ];
        }

        return [
            'status' => 1,
            'rule' => $ruleArr,
            'message' => 'ok',
        ];
    }

    /**
     * 传承属性预览: c=general_inherit&m=preview
     */
    public static function preview(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $fromGid = (int)($ctx->input('gid1', $ctx->input('from_gid', 0)));
        $toGid = (int)($ctx->input('gid2', $ctx->input('to_gid', 0)));
        $type = (int)($ctx->input('t', $ctx->input('type', 1)));

        if ($fromGid <= 0 || $toGid <= 0 || $fromGid === $toGid) {
            return ['status' => -1, 'message' => '请选择两个不同的武将进行传承'];
        }

        $fromGen = DB::table('general')->where('uid', $uid)->where('id', $fromGid)->whereNull('deleted_at')->first();
        $toGen = DB::table('general')->where('uid', $uid)->where('id', $toGid)->whereNull('deleted_at')->first();

        if ($fromGen === null || $toGen === null) {
            return ['status' => -3, 'message' => '指定的武将不存在'];
        }

        $cfg = self::INHERIT_RULES[$type] ?? self::INHERIT_RULES[1];
        $rate = (float)$cfg['rate'];

        $fromExp = (int)$fromGen['exp'];
        $toExp = (int)$toGen['exp'];
        $transferExp = (int)round($fromExp * $rate);
        $targetExp = $toExp + $transferExp;

        $targetWuli = (int)round((int)($fromGen['wuliup'] ?? 0) * $rate);
        $targetZhili = (int)round((int)($fromGen['zhiliup'] ?? 0) * $rate);
        $targetTili = (int)round((int)($fromGen['tiliup'] ?? 0) * $rate);

        // 推算继承后新等级
        $simLvl = (int)$toGen['level'];
        $simExp = $targetExp;
        while ($simExp >= GeneralHandler::getLevelUpExp($simLvl) && $simLvl < 120) {
            $simExp -= GeneralHandler::getLevelUpExp($simLvl);
            $simLvl++;
        }

        return [
            'status' => 1,
            'from_name' => (string)$fromGen['name'],
            'to_name' => (string)$toGen['name'],
            'inherit_rate' => $rate,
            'preview_level' => $simLvl,
            'preview_exp' => $simExp,
            'preview_wuliup' => $targetWuli,
            'preview_zhiliup' => $targetZhili,
            'preview_tiliup' => $targetTili,
            'info' => [
                'from_name' => (string)$fromGen['name'],
                'to_name' => (string)$toGen['name'],
                'level' => $simLvl,
                'exp' => $simExp,
                'wuliup' => $targetWuli,
                'zhiliup' => $targetZhili,
                'tiliup' => $targetTili,
            ],
            'message' => 'ok',
        ];
    }

    /**
     * 执行武将传承: c=general_inherit&m=go_inherit
     */
    public static function go_inherit(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $fromGid = (int)($ctx->input('gid1', $ctx->input('from_gid', 0)));
        $toGid = (int)($ctx->input('gid2', $ctx->input('to_gid', 0)));
        $type = (int)($ctx->input('t', $ctx->input('type', 1)));

        if ($fromGid <= 0 || $toGid <= 0 || $fromGid === $toGid) {
            return ['status' => -1, 'message' => '请选择两个不同的武将进行传承'];
        }

        $fromGen = DB::table('general')->where('uid', $uid)->where('id', $fromGid)->whereNull('deleted_at')->first();
        $toGen = DB::table('general')->where('uid', $uid)->where('id', $toGid)->whereNull('deleted_at')->first();

        if ($fromGen === null || $toGen === null) {
            return ['status' => -3, 'message' => '指定的武将不存在'];
        }

        $cfg = self::INHERIT_RULES[$type] ?? self::INHERIT_RULES[1];
        $rate = (float)$cfg['rate'];
        $costGold = (int)$cfg['gold'];

        if ($costGold > 0) {
            $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
            if ((int)($member['gold'] ?? 0) < $costGold) {
                return ['status' => 3, 'message' => '元宝不足'];
            }
            MemberHandler::deductCurrency($uid, gold: $costGold);
        }

        $fromExp = (int)$fromGen['exp'];
        $transferExp = (int)round($fromExp * $rate);
        $newToExp = (int)$toGen['exp'] + $transferExp;

        $newWuli = (int)round((int)($fromGen['wuliup'] ?? 0) * $rate);
        $newZhili = (int)round((int)($fromGen['zhiliup'] ?? 0) * $rate);
        $newTili = (int)round((int)($fromGen['tiliup'] ?? 0) * $rate);

        $newLevel = (int)$toGen['level'];
        $leftExp = $newToExp;
        while ($leftExp >= GeneralHandler::getLevelUpExp($newLevel) && $newLevel < 120) {
            $leftExp -= GeneralHandler::getLevelUpExp($newLevel);
            $newLevel++;
        }

        // 更新传承武将与被传承武将数据
        DB::table('general')->where('id', $toGid)->update([
            'level' => $newLevel,
            'exp' => $leftExp,
            'wuliup' => max((int)($toGen['wuliup'] ?? 0), $newWuli),
            'zhiliup' => max((int)($toGen['zhiliup'] ?? 0), $newZhili),
            'tiliup' => max((int)($toGen['tiliup'] ?? 0), $newTili),
        ]);

        // 原武将经验清零，洗髓清零
        DB::table('general')->where('id', $fromGid)->update([
            'level' => 1,
            'exp' => 0,
            'wuliup' => 0,
            'zhiliup' => 0,
            'tiliup' => 0,
        ]);

        Redis::del('player:' . $uid);

        return [
            'status' => 1,
            'from_gid' => $fromGid,
            'to_gid' => $toGid,
            'new_level' => $newLevel,
            'new_exp' => $leftExp,
            'message' => "恭喜！大将【{$toGen['name']}】成功继承【{$fromGen['name']}】之神力，战力大幅飞跃！",
        ];
    }
}
