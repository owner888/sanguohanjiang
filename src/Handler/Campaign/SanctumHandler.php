<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Campaign;

use Hanjiang\Handler\City\MemberHandler;

use Hanjiang\Battle\BattleEngine;
use Hanjiang\Battle\Position;
use Hanjiang\Battle\TeamBuilder;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use OmniPHP\WebSocket\Broadcaster;

final class SanctumHandler
{
    public const array REALMS = [
        1 => ['id' => 1, 'name' => '青龙东方圣域', 'boss' => '青龙守护神尊', 'hp' => 2000, 'attack' => 280, 'defense' => 120, 'reward_silver' => 15000, 'reward_gold' => 30],
        2 => ['id' => 2, 'name' => '白虎西方圣域', 'boss' => '白虎破军狂尊', 'hp' => 3000, 'attack' => 350, 'defense' => 150, 'reward_silver' => 25000, 'reward_gold' => 50],
        3 => ['id' => 3, 'name' => '朱雀南方圣域', 'boss' => '朱雀焚天炎尊', 'hp' => 4500, 'attack' => 420, 'defense' => 180, 'reward_silver' => 40000, 'reward_gold' => 70],
        4 => ['id' => 4, 'name' => '玄武北方圣域', 'boss' => '玄武极寒冥尊', 'hp' => 6000, 'attack' => 500, 'defense' => 220, 'reward_silver' => 60000, 'reward_gold' => 100],
    ];

    /**
     * 四象圣域主界面: c=sanctum&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $cleared = (int)(Redis::get('sanctum_cleared:' . $uid) ?: 0);

        $list = [];
        foreach (self::REALMS as $id => $r) {
            $list[$id] = array_merge($r, [
                'status' => ($id <= $cleared) ? 2 : (($id === $cleared + 1) ? 1 : 0),
            ]);
        }

        return [
            'status' => 1,
            'cleared_realms' => $cleared,
            'list' => $list,
            'message' => 'ok',
        ];
    }

    /**
     * 发起圣域神尊决战: c=sanctum&m=action
     */
    public static function action(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $realmId = (int)($ctx->input('id', $ctx->input('realm_id', 1)));

        if (!isset(self::REALMS[$realmId])) {
            return ['status' => 0, 'message' => '圣域不存在'];
        }

        $cfg = self::REALMS[$realmId];
        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();

        // 1. 我方队伍（旧版 sanctum 控制器走 battle_model->battle_sanctum：init_member_team_info，PVE）
        $left = TeamBuilder::forMember($uid, pve: true);
        if ($left['status'] === -1 || $left['status'] === -3) {
            // 旧版 battle_model 返回 -1（没有阵法）/ -3（武将信息错误）；本玩法尚未按旧版控制器移植，直接回传该状态
            return ['status' => $left['status'], 'message' => '出战阵容信息错误'];
        }
        // -2（阵法里没有武将）旧版按空队伍继续战斗，空队伍直接判负

        // 2. 圣域神尊
        // 依赖未移植：旧版四象圣域守军取 sanctum_mission 的 position1~9 → sanctum_monster（TeamBuilder::MONSTER_SANCTUM），本玩法的关卡 id 与守军数值是新版自定的，对不上旧版关卡
        // 依赖未移植：守军数值仍为新版自定
        $rightTeam = [Position::FrontMiddle->value => TeamBuilder::unportedUnit(5000 + $cfg['id'], $cfg['boss'], (int)$cfg['hp'], (int)$cfg['attack'], (int)$cfg['defense'])];

        // 3. 推演
        $engine = new BattleEngine($left['matrix_type'], $left['matrix_coe']);
        $battle = $engine->battle($left['team'], $rightTeam);
        $isWin = $battle['assess'] >= 1;

        if (!$isWin) {
            return [
                'status' => 1,
                'win' => -1,
                'realm_id' => $realmId,
                'message' => "挑战【{$cfg['boss']}】失败，圣尊神威浩瀚，请强化阵型再战！",
            ];
        }

        // 4. 战胜：记录通关进度并给奖
        $cleared = (int)(Redis::get('sanctum_cleared:' . $uid) ?: 0);
        if ($realmId > $cleared) {
            Redis::set('sanctum_cleared:' . $uid, (string)$realmId, 86400 * 30);
        }

        $rewardSilver = (int)$cfg['reward_silver'];
        $rewardGold = (int)$cfg['reward_gold'];

        $newSilver = (int)($member['silver'] ?? 0) + $rewardSilver;
        $newGold = (int)($member['gold'] ?? 0) + $rewardGold;

        DB::table('member')->where('uid', $uid)->update([
            'silver' => $newSilver,
            'gold' => $newGold,
        ]);
        Redis::del('player:' . $uid);

        $nickname = $member['nickname'] ?? ('主公' . $uid);
        Broadcaster::broadcast('marquee', [
            'content' => "圣域破晓！主公【{$nickname}】力克四象圣尊【{$cfg['boss']}】，扬我国威！",
            'type' => 'system_broadcast',
        ]);

        return [
            'status' => 1,
            'win' => 1,
            'realm_id' => $realmId,
            'boss_name' => $cfg['boss'],
            'silver' => $rewardSilver,
            'gold' => $rewardGold,
            'message' => "大捷！成功突破【{$cfg['name']}】，击溃【{$cfg['boss']}】！获得银两 {$rewardSilver}、元宝 {$rewardGold}！",
        ];
    }

    /**
     * 领取圣域通关总大奖: c=sanctum&m=get_reward
     */
    public static function get_reward(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $cleared = (int)(Redis::get('sanctum_cleared:' . $uid) ?: 0);

        if ($cleared < 4) {
            return ['status' => 0, 'message' => "当前已突破 {$cleared}/4 大圣域，需通关全部四象圣域方可申领终极神礼！"];
        }

        return [
            'status' => 1,
            'message' => '四象圣域圆满大成，上古圣兽精魄已悉数归位！',
        ];
    }
}
