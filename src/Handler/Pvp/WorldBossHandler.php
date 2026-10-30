<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Pvp;

use Hanjiang\Handler\City\MemberHandler;

use Hanjiang\Battle\BattleEngine;
use Hanjiang\Battle\Position;
use Hanjiang\Battle\TeamBuilder;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use OmniPHP\WebSocket\Broadcaster;

final class WorldBossHandler
{
    public const int TOTAL_HP = 500000;

    /**
     * 世界 BOSS 主界面: c=worldboss&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $bossHp = (int)(Redis::get('worldboss_hp') ?: self::TOTAL_HP);
        $myBuff = (int)(Redis::get('worldboss_buff:' . $uid) ?: 0);
        $myDamage = (int)(Redis::get('worldboss_dmg:' . $uid) ?: 0);

        return [
            'status' => 1,
            'boss_name' => '极·魔王董卓',
            'total_hp' => self::TOTAL_HP,
            'current_hp' => max(0, $bossHp),
            'hp_percent' => round(max(0, $bossHp) / self::TOTAL_HP * 100, 1),
            'is_alive' => ($bossHp > 0) ? 1 : 0,
            'my_buff_percent' => $myBuff,
            'my_damage' => $myDamage,
            'top_ranks' => [
                ['rank' => 1, 'name' => '常山赵子龙', 'damage' => 125000],
                ['rank' => 2, 'name' => '诸葛孔明', 'damage' => 98000],
                ['rank' => 3, 'name' => '关云长', 'damage' => 86000],
            ],
            'message' => 'ok',
        ];
    }

    /**
     * 鼓舞士气: c=worldboss&m=powerup
     */
    public static function powerup(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->first();
        $costSilver = 5000;

        if ((int)($member['silver'] ?? 0) < $costSilver) {
            return ['status' => -1, 'message' => "银两不足，鼓舞士气需要 {$costSilver} 银两"];
        }

        $myBuff = (int)(Redis::get('worldboss_buff:' . $uid) ?: 0);
        if ($myBuff >= 100) {
            return ['status' => 0, 'message' => '士气鼓舞已达 100% 满额状态'];
        }

        $newBuff = min(100, $myBuff + 20);
        Redis::set('worldboss_buff:' . $uid, (string)$newBuff, 86400);

        $newSilver = (int)$member['silver'] - $costSilver;
        DB::table('member')->where('uid', $uid)->update(['silver' => $newSilver]);
        Redis::del('player:' . $uid);

        return [
            'status' => 1,
            'buff_percent' => $newBuff,
            'silver' => $newSilver,
            'message' => "战意昂扬！士气鼓舞成功，全军伤害提升至 +{$newBuff}%！",
        ];
    }

    /**
     * 发起世界 BOSS 讨伐战: c=worldboss&m=battle
     */
    public static function battle(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->first();
        $bossHp = (int)(Redis::get('worldboss_hp') ?: self::TOTAL_HP);

        if ($bossHp <= 0) {
            return ['status' => 0, 'message' => '魔王董卓已被击败，请等待明日刷新！'];
        }

        $myBuff = (int)(Redis::get('worldboss_buff:' . $uid) ?: 0);
        $buffMultiplier = 1.0 + ($myBuff / 100);

        // 1. 我方队伍（旧版 worldboss 控制器走 battle_model->battle_worldboss：init_member_team_info，PVE）
        $left = TeamBuilder::forMember($uid, pve: true);
        if ($left['status'] === -1 || $left['status'] === -3) {
            // 旧版 battle_model 返回 -1（没有阵法）/ -3（武将信息错误）；本玩法尚未按旧版控制器移植，直接回传该状态
            return ['status' => $left['status'], 'message' => '出战阵容信息错误'];
        }
        // -2（阵法里没有武将）旧版按空队伍继续战斗，空队伍直接判负

        // 2. 世界 BOSS 董卓
        // 依赖未移植：旧版世界 BOSS 信息取自 world_boss 配置（battle_worldboss 的 $bossinfo），本玩法的数值是新版自定的；站位按旧版 5 号位
        // 依赖未移植：守军数值仍为新版自定
        $rightTeam = [Position::MidMiddle->value => TeamBuilder::unportedUnit(9999, '极·魔王董卓', 50000, 450, 200)];

        // 3. 推演（旧版 battle_worldboss：_boss_battle = 1，鼓舞系数 _attack_coe 乘到双方伤害上；本玩法的鼓舞系数是新版自定的）
        $engine = new BattleEngine($left['matrix_type'], $left['matrix_coe'], attackCoe: $buffMultiplier, bossBattle: 1);
        $battle = $engine->battle($left['team'], $rightTeam);

        // 本次对 BOSS 造成的伤害（旧版 worldboss 控制器：左方出手记录里 self = 0 的 hurt.blood 之和）
        $damageDealt = 0;
        foreach ($battle['process'] as $value) {
            if ($value['launch'] === 'left') {
                foreach ($value['hurt'] as $v) {
                    if (isset($v['self']) && $v['self'] === 0) {
                        $damageDealt += $v['blood'];
                    }
                }
            }
        }
        $damageDealt = max(500, $damageDealt);

        $newBossHp = max(0, $bossHp - $damageDealt);
        Redis::set('worldboss_hp', (string)$newBossHp, 86400);

        $oldDmg = (int)(Redis::get('worldboss_dmg:' . $uid) ?: 0);
        $newDmg = $oldDmg + $damageDealt;
        Redis::set('worldboss_dmg:' . $uid, (string)$newDmg, 86400);

        // 奖励银两
        $rewardSilver = (int)round($damageDealt * 0.5);
        DB::table('member')->where('uid', $uid)->update([
            'silver' => (int)($member['silver'] ?? 0) + $rewardSilver,
        ]);
        Redis::del('player:' . $uid);

        if ($newBossHp <= 0) {
            $pName = $member['nickname'] ?? ('主公' . $uid);
            Broadcaster::broadcast('marquee', [
                'content' => "天地同庆！魔王董卓已被各路诸侯全歼！最终致命一击由主公【{$pName}】达成！",
                'type' => 'system_broadcast',
            ]);
        }

        return [
            'status' => 1,
            'damage_dealt' => $damageDealt,
            'boss_remain_hp' => $newBossHp,
            'total_damage' => $newDmg,
            'reward_silver' => $rewardSilver,
            'message' => "讨伐激战结束！对魔王董卓造成了 {$damageDealt} 点巨额伤害！获得功勋银两 {$rewardSilver}！",
        ];
    }

    /**
     * 领取世界 BOSS 宝箱: c=worldboss&m=reward
     */
    public static function reward(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $myDmg = (int)(Redis::get('worldboss_dmg:' . $uid) ?: 0);

        if ($myDmg <= 0) {
            return ['status' => 0, 'message' => '未参与世界 BOSS 讨伐战，无战利品可领'];
        }

        $rewardSilver = 10000;
        $rewardGold = 50;

        $member = DB::table('member')->where('uid', $uid)->first();
        DB::table('member')->where('uid', $uid)->update([
            'silver' => (int)($member['silver'] ?? 0) + $rewardSilver,
            'gold' => (int)($member['gold'] ?? 0) + $rewardGold,
        ]);
        Redis::del('player:' . $uid);

        return [
            'status' => 1,
            'silver' => $rewardSilver,
            'gold' => $rewardGold,
            'message' => "成功领取世界 BOSS 功勋宝箱！获得银两 {$rewardSilver}、元宝 {$rewardGold}！",
        ];
    }
}
