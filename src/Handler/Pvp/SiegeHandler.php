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

final class SiegeHandler
{
    /**
     * 攻城掠地天下名城列表: c=siege&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $cities = DB::table('siege')->whereNull('deleted_at')->get();

        $now = time();
        $salaryClaimed = false;
        $lastClaim = Redis::get('siege_reward:' . $uid);
        if ($lastClaim !== null && date('Ymd', (int)$lastClaim) === date('Ymd', $now)) {
            $salaryClaimed = true;
        }

        $myCities = [];
        $list = [];
        foreach ($cities as $c) {
            $cid = (int)$c['city_id'];
            $occUid = (int)$c['occupier_uid'];
            $isMine = ($occUid === $uid && $uid > 0);

            if ($isMine) {
                $myCities[] = $c['name'];
            }

            $list[$cid] = [
                'id' => $cid,
                'name' => $c['name'],
                'occupier_name' => $c['occupier_name'],
                'occupier_uid' => $occUid,
                'is_mine' => $isMine ? 1 : 0,
                'daily_silver' => (int)$c['daily_silver'],
                'daily_gold' => (int)$c['daily_gold'],
                'defense_level' => (int)$c['defense_level'],
            ];
        }

        return [
            'status' => 1,
            'list' => $list,
            'my_cities_count' => count($myCities),
            'my_cities' => $myCities,
            'reward_claimed' => $salaryClaimed ? 1 : 0,
            'message' => 'ok',
        ];
    }

    /**
     * 发起攻城掠地战斗: c=siege&m=action
     */
    public static function action(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $cityId = (int)($ctx->input('city_id', $ctx->input('id', 1)));
        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();

        $city = DB::table('siege')->where('city_id', $cityId)->whereNull('deleted_at')->first();
        if ($city === null) {
            return ['status' => 0, 'message' => '城池不存在'];
        }

        if ((int)$city['occupier_uid'] === $uid && $uid > 0) {
            return ['status' => 0, 'message' => '该城池已在您的统治麾下，无需攻打'];
        }

        // 1. 构建我方攻城先锋部队
        $generals = DB::table('general')->where('uid', $uid)->where('pos', '>', 0)->whereNull('deleted_at')->get();
        if (empty($generals)) {
            $generals = DB::table('general')->where('uid', $uid)->whereNull('deleted_at')->get();
        }

        $matrix = DB::table('matrix')->where('uid', $uid)->whereNull('deleted_at')->first();
        $matrixMid = (int)($matrix['mid'] ?? 1);
        $matrixLevel = max(1, (int)($matrix['level'] ?? 1));
        $matrixAddition = 1.0 + 0.05 + ($matrixLevel - 1) * 0.01;

        $leftHeroes = [];
        foreach ($generals as $g) {
            $gid = (int)$g['id'];
            $posInt = (int)$g['pos'] > 0 ? (int)$g['pos'] : 2;
            $pos = Position::tryFrom($posInt) ?? Position::FrontMiddle;

            $equipped = DB::table('equipment')->where('uid', $uid)->where('generalid', $gid)->whereNull('deleted_at')->get();
            $stats = GeneralHandler::calculateGeneralStats($g, $equipped);

            $finalAtk = ($matrixMid === 1) ? (int)round($stats['attack'] * $matrixAddition) : $stats['attack'];
            $finalDef = ($matrixMid === 3) ? (int)round($stats['defense'] * $matrixAddition) : $stats['defense'];

            $heroName = $g['name'] ?: ($member['nickname'] ?? '天命主公');
            $leftHeroes[] = new Hero($gid, $heroName, $pos, $stats['hp'], $stats['hp'], $finalAtk, $finalDef, $stats['speed']);
        }
        if (empty($leftHeroes)) {
            $leftHeroes[] = new Hero(1, '天命主公', Position::FrontMiddle, 2000, 2000, 380, 150, 150);
        }

        // 2. 构建守城军团守军
        $defLvl = (int)$city['defense_level'];
        $enemyHp = 1200 + $defLvl * 400;
        $enemyAtk = 220 + $defLvl * 40;
        $enemyDef = 90 + $defLvl * 20;

        $rightHeroes = [
            new Hero(6000 + $cityId, $city['occupier_name'], Position::FrontMiddle, $enemyHp, $enemyHp, $enemyAtk, $enemyDef, 120),
        ];

        // 3. 确定性推演
        $engine = new BattleEngine($leftHeroes, $rightHeroes);
        $simResult = $engine->simulate();
        $isWin = $simResult['win'];

        if (!$isWin) {
            return [
                'status' => 1,
                'win' => -1,
                'city_id' => $cityId,
                'city_name' => $city['name'],
                'message' => "攻打【{$city['name']}】受挫，守城军团防御森严，请壮大兵力后再行攻城！",
            ];
        }

        // 4. 攻克占领城池
        $nickname = $member['nickname'] ?? ('主公' . $uid);
        DB::table('siege')->where('city_id', $cityId)->update([
            'occupier_name' => '【' . $nickname . '】军团',
            'occupier_uid' => $uid,
        ]);

        // 攻破名城全服跑马灯大喇叭通报
        Broadcaster::broadcast('marquee', [
            'content' => "气吞山河！主公【{$nickname}】率军浴血奋战，成功攻克天下名城【{$city['name']}】，受万人朝拜！",
            'type' => 'system_broadcast',
        ]);

        return [
            'status' => 1,
            'win' => 1,
            'city_id' => $cityId,
            'city_name' => $city['name'],
            'new_occupier' => $nickname,
            'rounds' => $simResult['rounds'],
            'message' => "破城大捷！成功击溃守军，【{$city['name']}】正式归入主公麾下统治！",
        ];
    }

    /**
     * 领取城池每日岁贡分红: c=siege&m=get_reward
     */
    public static function get_reward(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $now = time();

        $lastClaim = Redis::get('siege_reward:' . $uid);
        if ($lastClaim !== null && date('Ymd', (int)$lastClaim) === date('Ymd', $now)) {
            return ['status' => 0, 'message' => '今日城池岁贡分红已领取，请明日再来'];
        }

        $myCities = DB::table('siege')->where('occupier_uid', $uid)->whereNull('deleted_at')->get();
        if (empty($myCities)) {
            return ['status' => 0, 'message' => '您当前未占领任何城池，请先攻城占领'];
        }

        $totalSilver = 0;
        $totalGold = 0;
        foreach ($myCities as $c) {
            $totalSilver += (int)$c['daily_silver'];
            $totalGold += (int)$c['daily_gold'];
        }

        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        DB::table('member')->where('uid', $uid)->update([
            'silver' => (int)($member['silver'] ?? 0) + $totalSilver,
            'gold' => (int)($member['gold'] ?? 0) + $totalGold,
        ]);

        Redis::set('siege_reward:' . $uid, (string)$now, 86400 * 2);
        Redis::del('player:' . $uid);

        return [
            'status' => 1,
            'city_count' => count($myCities),
            'silver' => $totalSilver,
            'gold' => $totalGold,
            'message' => "成功收缴 " . count($myCities) . " 座封邑城池岁贡，获得银两 {$totalSilver}、元宝 {$totalGold}！",
        ];
    }
}
