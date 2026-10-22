<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Pvp;

use Hanjiang\Handler\City\MemberHandler;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use OmniPHP\WebSocket\Broadcaster;

final class CountryHandler
{
    public const array COUNTRIES = [
        1 => ['id' => 1, 'name' => '魏国', 'leader' => '曹操', 'capital' => '洛阳', 'buff' => '全军物理防御 +5%'],
        2 => ['id' => 2, 'name' => '蜀国', 'leader' => '刘备', 'capital' => '成都', 'buff' => '全军普通攻击 +5%'],
        3 => ['id' => 3, 'name' => '吴国', 'leader' => '孙权', 'capital' => '建业', 'buff' => '全军最大生命 +5%'],
    ];

    /**
     * 国家系统主界面: c=country&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->first();
        $myCountry = (int)($member['country'] ?? 0);

        // 统计三国国力
        $stats = [];
        foreach (self::COUNTRIES as $cid => $c) {
            $memberCount = DB::table('member')->where('country', $cid)->count();
            $power = 10000 + $memberCount * 2500;
            $stats[$cid] = array_merge($c, [
                'members' => $memberCount,
                'power' => $power,
            ]);
        }

        $now = time();
        $salaryClaimed = false;
        $lastSalary = Redis::get('country_salary:' . $uid);
        if ($lastSalary !== null && date('Ymd', (int)$lastSalary) === date('Ymd', $now)) {
            $salaryClaimed = true;
        }

        return [
            'status' => 1,
            'my_country' => $myCountry,
            'country_name' => self::COUNTRIES[$myCountry]['name'] ?? '在野游侠',
            'official' => ($myCountry > 0) ? '镇军将军' : '平民',
            'salary_claimed' => $salaryClaimed ? 1 : 0,
            'countries' => $stats,
            'message' => 'ok',
        ];
    }

    /**
     * 选择国家加入阵营: c=country&m=select_country
     */
    public static function select_country(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->first();
        $curCountry = (int)($member['country'] ?? 0);

        if ($curCountry > 0) {
            return [
                'status' => 0,
                'message' => '您已效力于【' . self::COUNTRIES[$curCountry]['name'] . '】，不可叛国',
            ];
        }

        // country: 1魏, 2蜀, 3吴, 0听天由命(随机)
        $choice = (int)($ctx->input('country', $ctx->input('cid', 0)));
        $isRandom = false;

        if ($choice <= 0 || !isset(self::COUNTRIES[$choice])) {
            $choice = mt_rand(1, 3);
            $isRandom = true;
        }

        $bonusGold = $isRandom ? 100 : 0;
        $bonusSilver = $isRandom ? 10000 : 0;

        $newGold = (int)($member['gold'] ?? 0) + $bonusGold;
        $newSilver = (int)($member['silver'] ?? 0) + $bonusSilver;

        DB::table('member')->where('uid', $uid)->update([
            'country' => $choice,
            'gold' => $newGold,
            'silver' => $newSilver,
        ]);

        Redis::del('player:' . $uid);

        $countryName = self::COUNTRIES[$choice]['name'];
        $nickname = $member['nickname'] ?? ('主公' . $uid);

        // 全服通报加入阵营
        Broadcaster::broadcast('marquee', [
            'content' => "天下大势！主公【{$nickname}】" . ($isRandom ? '顺应天命' : '宣誓') . "加入【{$countryName}】，共谋中原！",
            'type' => 'system_broadcast',
        ]);

        return [
            'status' => 1,
            'country' => $choice,
            'country_name' => $countryName,
            'is_random' => $isRandom ? 1 : 0,
            'bonus_gold' => $bonusGold,
            'bonus_silver' => $bonusSilver,
            'gold' => $newGold,
            'silver' => $newSilver,
            'message' => "成功加入【{$countryName}】！" . ($isRandom ? "获得天命奖励：{$bonusGold} 元宝、{$bonusSilver} 银两！" : ''),
        ];
    }

    /**
     * 国家军资捐献: c=country&m=donate
     */
    public static function donate(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->first();
        $myCountry = (int)($member['country'] ?? 0);

        if ($myCountry <= 0) {
            return [
                'status' => 0,
                'message' => '请先选择加入魏、蜀、吴任意国家',
            ];
        }

        $type = (int)($ctx->input('type', 1)); // 1 银两捐献(10000), 2 元宝捐献(50)
        $curSilver = (int)($member['silver'] ?? 0);
        $curGold = (int)($member['gold'] ?? 0);

        if ($type === 1) {
            $cost = 10000;
            if ($curSilver < $cost) {
                return ['status' => -1, 'message' => "银两不足，捐献需要 {$cost} 银两"];
            }
            $rewardRep = 50;
            $newSilver = $curSilver - $cost;
            $newGold = $curGold;
        } else {
            $cost = 50;
            if ($curGold < $cost) {
                return ['status' => -2, 'message' => "元宝不足，捐献需要 {$cost} 元宝"];
            }
            $rewardRep = 200;
            $newSilver = $curSilver;
            $newGold = $curGold - $cost;
        }

        $newRep = (int)($member['reputation'] ?? 0) + $rewardRep;

        DB::table('member')->where('uid', $uid)->update([
            'silver' => $newSilver,
            'gold' => $newGold,
            'reputation' => $newRep,
        ]);

        Redis::del('player:' . $uid);

        return [
            'status' => 1,
            'add_reputation' => $rewardRep,
            'reputation' => $newRep,
            'silver' => $newSilver,
            'gold' => $newGold,
            'message' => "国家捐献成功！为【" . self::COUNTRIES[$myCountry]['name'] . "】建立丰功伟绩，获得声望 {$rewardRep}！",
        ];
    }

    /**
     * 领取每日国家俸禄: c=country&m=salary
     */
    public static function salary(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->first();
        $myCountry = (int)($member['country'] ?? 0);

        if ($myCountry <= 0) {
            return [
                'status' => 0,
                'message' => '未加入国家，无法领取国家俸禄',
            ];
        }

        $now = time();
        $lastSalary = Redis::get('country_salary:' . $uid);
        if ($lastSalary !== null && date('Ymd', (int)$lastSalary) === date('Ymd', $now)) {
            return [
                'status' => 0,
                'message' => '今日国家俸禄已领取，明日再来',
            ];
        }

        $level = max(1, (int)($member['level'] ?? 1));
        $rewardSilver = 3000 + $level * 500;
        $rewardRep = 50;

        $newSilver = (int)($member['silver'] ?? 0) + $rewardSilver;
        $newRep = (int)($member['reputation'] ?? 0) + $rewardRep;

        DB::table('member')->where('uid', $uid)->update([
            'silver' => $newSilver,
            'reputation' => $newRep,
        ]);

        Redis::set('country_salary:' . $uid, (string)$now, 86400 * 2);
        Redis::del('player:' . $uid);

        return [
            'status' => 1,
            'silver' => $rewardSilver,
            'reputation' => $rewardRep,
            'message' => "成功领取今日国家俸禄，获得银两 {$rewardSilver}、声望 {$rewardRep}！",
        ];
    }
}
