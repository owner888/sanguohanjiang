<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Campaign;

use Hanjiang\Handler\City\MemberHandler;

use Hanjiang\Battle\BattleEngine;
use Hanjiang\Battle\Hero;
use Hanjiang\Battle\Position;
use Hanjiang\Battle\TeamBuilder;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

final class CopiesHandler
{
    public const array COPIES = [
        1 => ['id' => 1, 'name' => '平乱·十常侍之乱', 'hp' => 2000, 'attack' => 300, 'defense' => 120, 'silver' => 12000, 'drop' => '初级锻造石 x3'],
        2 => ['id' => 2, 'name' => '平乱·董卓乱政大营', 'hp' => 3500, 'attack' => 400, 'defense' => 160, 'silver' => 22000, 'drop' => '中级锻造石 x2'],
        3 => ['id' => 3, 'name' => '平乱·张闿叛军据点', 'hp' => 5000, 'attack' => 500, 'defense' => 200, 'silver' => 35000, 'drop' => '高级锻造石 x1'],
    ];

    /**
     * 平乱副本主界面: c=copies&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $times = (int)(Redis::get('copies_times:' . $uid) ?? 5);

        return [
            'status' => 1,
            'left_times' => $times,
            'list' => array_values(self::COPIES),
            'message' => 'ok',
        ];
    }

    /**
     * 发起平乱对决: c=copies&m=pk
     */
    public static function pk(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $copyId = (int)($ctx->input('id', $ctx->input('copy_id', 1)));
        $times = (int)(Redis::get('copies_times:' . $uid) ?? 5);

        if ($times <= 0) {
            return ['status' => -1, 'message' => '今日平乱副本挑战次数已用尽'];
        }

        if (!isset(self::COPIES[$copyId])) {
            return ['status' => 0, 'message' => '平乱战役不存在'];
        }

        $cfg = self::COPIES[$copyId];
        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();

        // 1. 我方队伍（旧版新副本走 battle_model->battle_copies：init_member_team_info，PVE）
        $left = TeamBuilder::forMember($uid, pve: true);
        if ($left['status'] === -1 || $left['status'] === -3) {
            // 旧版 battle_model 返回 -1（没有阵法）/ -3（武将信息错误）；本玩法尚未按旧版控制器移植，直接回传该状态
            return ['status' => $left['status'], 'message' => '出战阵容信息错误'];
        }
        // -2（阵法里没有武将）旧版按空队伍继续战斗，空队伍直接判负
        $leftHeroes = array_values(TeamBuilder::heroes($left['team'], $left['matrix_type'], $left['matrix_coe']));

        // 2. 叛军叛将
        // 依赖未移植：旧版新副本守军取 copies_info 的 position1~9 → copies_info（TeamBuilder::MONSTER_COPIES），本玩法的关卡 id 与守军数值是新版自定的，对不上旧版关卡
        $rightHeroes = [
            Hero::unported(1500 + $cfg['id'], $cfg['name'], Position::FrontMiddle, $cfg['hp'], $cfg['attack'], $cfg['defense']),
        ];

        // 3. 推演
        $engine = new BattleEngine($leftHeroes, $rightHeroes);
        $simResult = $engine->simulate();
        $isWin = $simResult['win'];

        Redis::set('copies_times:' . $uid, (string)($times - 1), 86400);

        if (!$isWin) {
            return [
                'status' => 1,
                'win' => -1,
                'rounds' => $simResult['rounds'],
                'message' => "平乱受挫，叛军军势甚锐，请整军备武再行出征！",
            ];
        }

        $rewardSilver = (int)$cfg['silver'];
        MemberHandler::addCurrency($uid, silver: $rewardSilver);

        return [
            'status' => 1,
            'win' => 1,
            'copy_name' => $cfg['name'],
            'silver' => $rewardSilver,
            'drop' => $cfg['drop'],
            'rounds' => $simResult['rounds'],
            'message' => "平乱大捷！扫灭【{$cfg['name']}】，缴获军饷 {$rewardSilver}、珍稀战利品【{$cfg['drop']}】！",
        ];
    }

    /**
     * 一键平乱扫荡: c=copies&m=mop_up
     */
    public static function mop_up(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $times = (int)(Redis::get('copies_times:' . $uid) ?? 5);

        if ($times <= 0) {
            return ['status' => 0, 'message' => '今日扫荡次数已用尽'];
        }

        $rewardSilver = 15000;
        MemberHandler::addCurrency($uid, silver: $rewardSilver);
        Redis::set('copies_times:' . $uid, (string)($times - 1), 86400);

        return [
            'status' => 1,
            'silver' => $rewardSilver,
            'message' => "一键平乱扫荡圆满！斩获军功银饷 {$rewardSilver}！",
        ];
    }
}
