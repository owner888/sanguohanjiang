<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Pvp;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\General\GeneralHandler;

use Hanjiang\Battle\BattleEngine;
use Hanjiang\Battle\Hero;
use Hanjiang\Battle\Position;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use OmniPHP\WebSocket\Broadcaster;

final class CompeteHandler
{
    /**
     * 比武大会对手预设
     */
    public const array OPPONENTS = [
        1 => ['id' => 1, 'name' => '【辽东霸主】公孙瓒', 'score' => 2800, 'hp' => 2500, 'attack' => 380, 'defense' => 150],
        2 => ['id' => 2, 'name' => '【江东小霸王】孙策', 'score' => 2500, 'hp' => 2200, 'attack' => 350, 'defense' => 140],
        3 => ['id' => 3, 'name' => '【河北名将】高览', 'score' => 2200, 'hp' => 1900, 'attack' => 320, 'defense' => 130],
    ];

    /**
     * 比武大会主界面: c=compete&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $score = (int)(Redis::get('compete_score:' . $uid) ?: 1000);
        $times = (int)(Redis::get('compete_times:' . $uid) ?? 10);

        return [
            'status' => 1,
            'tournament' => '群雄逐鹿·跨服跨国巅峰论剑',
            'my_score' => $score,
            'rank_tier' => match (true) {
                $score >= 2500 => '无双王者',
                $score >= 2000 => '至尊星耀',
                $score >= 1500 => '永恒钻石',
                default        => '荣耀黄金',
            },
            'left_times' => $times,
            'opponents' => array_values(self::OPPONENTS),
            'message' => 'ok',
        ];
    }

    /**
     * 发起比武单挑: c=compete&m=action
     */
    public static function action(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $oppId = (int)($ctx->input('id', $ctx->input('target_id', 1)));
        $times = (int)(Redis::get('compete_times:' . $uid) ?? 10);

        if ($times <= 0) {
            return ['status' => -1, 'message' => '今日比武次数已用尽'];
        }

        if (!isset(self::OPPONENTS[$oppId])) {
            return ['status' => 0, 'message' => '比武对手不存在'];
        }

        $cfg = self::OPPONENTS[$oppId];
        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();

        // 1. 构建我方出战英雄
        $generals = DB::table('general')->where('uid', $uid)->where('pos', '>', 0)->whereNull('deleted_at')->get();
        if (empty($generals)) {
            $generals = DB::table('general')->where('uid', $uid)->whereNull('deleted_at')->get();
        }

        $leftHeroes = [];
        foreach ($generals as $g) {
            $gid = (int)$g['id'];
            $posInt = (int)$g['pos'] > 0 ? (int)$g['pos'] : 2;
            $pos = Position::tryFrom($posInt) ?? Position::FrontMiddle;
            $equipped = DB::table('equipment')->where('uid', $uid)->where('generalid', $gid)->whereNull('deleted_at')->get();
            $stats = GeneralHandler::calculateGeneralStats($g, $equipped);
            $leftHeroes[] = new Hero($gid, $g['name'], $pos, $stats['hp'], $stats['hp'], $stats['attack'], $stats['defense'], $stats['speed']);
        }
        if (empty($leftHeroes)) {
            $leftHeroes[] = new Hero(1, '天命主公', Position::FrontMiddle, 2000, 2000, 380, 150, 150);
        }

        // 2. 对手
        $rightHeroes = [
            new Hero(4000 + $cfg['id'], $cfg['name'], Position::FrontMiddle, $cfg['hp'], $cfg['hp'], $cfg['attack'], $cfg['defense'], 130),
        ];

        // 3. 推演
        $engine = new BattleEngine($leftHeroes, $rightHeroes);
        $simResult = $engine->simulate();
        $isWin = $simResult['win'];

        // 扣除挑战次数
        Redis::set('compete_times:' . $uid, (string)($times - 1), 86400);

        if (!$isWin) {
            return [
                'status' => 1,
                'win' => -1,
                'rounds' => $simResult['rounds'],
                'message' => "比武单挑惜败于【{$cfg['name']}】，请重整旗鼓再来挑战！",
            ];
        }

        // 战胜：增加比武积分与声望银两
        $oldScore = (int)(Redis::get('compete_score:' . $uid) ?: 1000);
        $newScore = $oldScore + 50;
        Redis::set('compete_score:' . $uid, (string)$newScore, 86400 * 30);

        $rewardSilver = 5000;
        DB::table('member')->where('uid', $uid)->update([
            'silver' => (int)($member['silver'] ?? 0) + $rewardSilver,
            'reputation' => (int)($member['reputation'] ?? 0) + 30,
        ]);
        Redis::del('player:' . $uid);

        return [
            'status' => 1,
            'win' => 1,
            'add_score' => 50,
            'current_score' => $newScore,
            'silver' => $rewardSilver,
            'rounds' => $simResult['rounds'],
            'message' => "旗开得胜！力斩对手【{$cfg['name']}】，比武积分 +50，获得胜者银两 {$rewardSilver}！",
        ];
    }

    /**
     * 领取比武段位每日俸禄: c=compete&m=get_reward
     */
    public static function get_reward(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $score = (int)(Redis::get('compete_score:' . $uid) ?: 1000);
        $rewardSilver = (int)round($score * 5);

        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        DB::table('member')->where('uid', $uid)->update([
            'silver' => (int)($member['silver'] ?? 0) + $rewardSilver,
        ]);
        Redis::del('player:' . $uid);

        return [
            'status' => 1,
            'silver' => $rewardSilver,
            'message' => "成功领取群雄逐鹿段位俸禄，赏赐银两 {$rewardSilver}！",
        ];
    }
}
