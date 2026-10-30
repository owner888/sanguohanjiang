<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Pvp;

use Hanjiang\Handler\City\MemberHandler;

use Hanjiang\Battle\BattleEngine;
use Hanjiang\Battle\Hero;
use Hanjiang\Battle\Position;
use Hanjiang\Battle\TeamBuilder;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use OmniPHP\WebSocket\Broadcaster;

final class CountryBossHandler
{
    public const int TOTAL_HP = 300000;

    /**
     * 国家神兽主界面: c=countryboss&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        $country = (int)($member['country'] ?? 1);

        $bossName = match ($country) {
            1 => '魏国·九天真金神龙',
            2 => '蜀国·踏云踏风真麒麟',
            default => '吴国·焚世九羽火凤',
        };

        $hp = (int)(Redis::get("countryboss_hp:{$country}") ?: self::TOTAL_HP);
        $myBuff = (int)(Redis::get("countryboss_buff:{$uid}") ?: 0);
        $myDmg = (int)(Redis::get("countryboss_dmg:{$uid}") ?: 0);

        return [
            'status' => 1,
            'boss_name' => $bossName,
            'country' => $country,
            'total_hp' => self::TOTAL_HP,
            'current_hp' => max(0, $hp),
            'my_buff' => $myBuff,
            'my_damage' => $myDmg,
            'is_alive' => ($hp > 0) ? 1 : 0,
            'message' => 'ok',
        ];
    }

    /**
     * 鼓舞全军: c=countryboss&m=powerup
     */
    public static function powerup(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $costSilver = 3000;

        if (!MemberHandler::deductCurrency($uid, silver: $costSilver)) {
            return ['status' => -1, 'message' => "银两不足，鼓舞军威需要 {$costSilver} 银两"];
        }

        $myBuff = (int)(Redis::get("countryboss_buff:{$uid}") ?: 0);
        $newBuff = min(100, $myBuff + 20);
        Redis::set("countryboss_buff:{$uid}", (string)$newBuff, 86400);

        return [
            'status' => 1,
            'buff' => $newBuff,
            'message' => "全军军威大振！伤害加成提升至 +{$newBuff}%！",
        ];
    }

    /**
     * 发起神兽挑战推演: c=countryboss&m=battle
     */
    public static function battle(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        $country = (int)($member['country'] ?? 1);

        $bossHp = (int)(Redis::get("countryboss_hp:{$country}") ?: self::TOTAL_HP);
        if ($bossHp <= 0) {
            return ['status' => 0, 'message' => '本国守护神兽今日试炼已降服！'];
        }

        $myBuff = (int)(Redis::get("countryboss_buff:{$uid}") ?: 0);
        $buffMul = 1.0 + ($myBuff / 100);

        // 1. 我方队伍（旧版 countryboss 控制器走 battle_model->battle_worldboss：init_member_team_info，PVE）
        $left = TeamBuilder::forMember($uid, pve: true);
        if ($left['status'] === -1 || $left['status'] === -3) {
            // 旧版 battle_model 返回 -1（没有阵法）/ -3（武将信息错误）；本玩法尚未按旧版控制器移植，直接回传该状态
            return ['status' => $left['status'], 'message' => '出战阵容信息错误'];
        }
        // -2（阵法里没有武将）旧版按空队伍继续战斗，空队伍直接判负
        // 临时适配（P5 删除）：旧版鼓舞系数 _attack_coe 在 process_hurt 里乘到伤害上，当前引擎没有该入口，暂乘到攻击上
        foreach ($left['team'] as $pos => $unit) {
            $left['team'][$pos]['attack'] = (int)round($unit['attack'] * $buffMul);
        }
        $leftHeroes = array_values(TeamBuilder::heroes($left['team'], $left['matrix_type'], $left['matrix_coe']));

        // 2. 国家守护神兽
        // 依赖未移植：旧版国家 BOSS 信息取自 countryboss 配置（battle_worldboss 的 $bossinfo），本玩法的数值是新版自定的；站位按旧版 5 号位
        $rightHeroes = [
            Hero::unported(8800 + $country, '护国圣兽真身', Position::MidMiddle, 30000, 400, 180),
        ];

        // 3. 推演
        $engine = new BattleEngine($leftHeroes, $rightHeroes, maxRounds: 8);
        $simResult = $engine->simulate();

        $dmg = 0;
        foreach ($simResult['logs'] as $log) {
            if ($log['side'] === 'left') {
                $dmg += (int)$log['damage'];
            }
        }
        $dmg = max(500, $dmg);

        $newHp = max(0, $bossHp - $dmg);
        Redis::set("countryboss_hp:{$country}", (string)$newHp, 86400);

        $oldDmg = (int)(Redis::get("countryboss_dmg:{$uid}") ?: 0);
        Redis::set("countryboss_dmg:{$uid}", (string)($oldDmg + $dmg), 86400);

        $rewardSilver = (int)round($dmg * 0.4);
        MemberHandler::addCurrency($uid, silver: $rewardSilver);

        return [
            'status' => 1,
            'damage' => $dmg,
            'boss_remain_hp' => $newHp,
            'silver' => $rewardSilver,
            'rounds' => $simResult['rounds'],
            'message' => "试炼对决结束！对护国神兽造成 {$dmg} 点伤害，荣获军功银饷 {$rewardSilver}！",
        ];
    }

    /**
     * 领取国家神兽宝箱: c=countryboss&m=reward
     */
    public static function reward(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $dmg = (int)(Redis::get("countryboss_dmg:{$uid}") ?: 0);

        if ($dmg <= 0) {
            return ['status' => 0, 'message' => '未参与护国神兽试炼，无战利品可领'];
        }

        MemberHandler::addCurrency($uid, silver: 20000, gold: 30);

        return [
            'status' => 1,
            'silver' => 20000,
            'gold' => 30,
            'message' => '成功领取护国神兽抚赏宝箱！获得银两 20000、元宝 30！',
        ];
    }
}
