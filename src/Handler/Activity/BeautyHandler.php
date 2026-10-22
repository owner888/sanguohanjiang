<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use OmniPHP\WebSocket\Broadcaster;

final class BeautyHandler
{
    /**
     * 绝色美人图鉴配置
     *
     * @var array<int, array<string, mixed>>
     */
    public const array BEAUTIES = [
        1 => ['id' => 1, 'name' => '貂蝉', 'title' => '闭月绝色', 'buff_desc' => '全军普通攻击 +5%', 'atk_bonus' => 50, 'hp_bonus' => 300],
        2 => ['id' => 2, 'name' => '大乔', 'title' => '沉鱼天香', 'buff_desc' => '全军物理防御 +5%', 'atk_bonus' => 0, 'hp_bonus' => 500],
        3 => ['id' => 3, 'name' => '小乔', 'title' => '落雁倾城', 'buff_desc' => '全军策略攻击 +5%', 'atk_bonus' => 40, 'hp_bonus' => 400],
        4 => ['id' => 4, 'name' => '甄姬', 'title' => '洛水仙子', 'buff_desc' => '全军策略防御 +5%', 'atk_bonus' => 0, 'hp_bonus' => 600],
        5 => ['id' => 5, 'name' => '孙尚香', 'title' => '弓腰枭姬', 'buff_desc' => '全军暴击概率 +3%', 'atk_bonus' => 60, 'hp_bonus' => 200],
    ];

    /**
     * 获取玩家美人后宫数据
     *
     * @return array<int, array{level: int, intimacy: int}>
     */
    public static function getBeautyData(int $uid): array
    {
        $cached = Redis::getJson('user_beauties:' . $uid);
        if (is_array($cached)) {
            return $cached;
        }

        $init = [
            1 => ['level' => 1, 'intimacy' => 20],
            2 => ['level' => 1, 'intimacy' => 0],
            3 => ['level' => 1, 'intimacy' => 0],
            4 => ['level' => 1, 'intimacy' => 0],
            5 => ['level' => 1, 'intimacy' => 0],
        ];
        Redis::setJson('user_beauties:' . $uid, $init, 86400 * 30);
        return $init;
    }

    /**
     * 美人主界面: c=beauty&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $userBeauties = self::getBeautyData($uid);

        $list = [];
        foreach (self::BEAUTIES as $id => $b) {
            $userB = $userBeauties[$id] ?? ['level' => 1, 'intimacy' => 0];
            $lvl = (int)$userB['level'];
            $intimacy = (int)$userB['intimacy'];

            $list[$id] = array_merge($b, [
                'level' => $lvl,
                'intimacy' => $intimacy,
                'need_intimacy' => $lvl * 100,
                'total_atk_bonus' => $b['atk_bonus'] * $lvl,
                'total_hp_bonus' => $b['hp_bonus'] * $lvl,
            ]);
        }

        return [
            'status' => 1,
            'list' => $list,
            'total_beauties' => count($list),
            'message' => 'ok',
        ];
    }

    /**
     * 赠送鲜花 (消耗银两): c=beauty&m=give_flower
     */
    public static function give_flower(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $bid = (int)($ctx->input('id', $ctx->input('bid', 1)));

        if (!isset(self::BEAUTIES[$bid])) {
            return ['status' => 0, 'message' => '无效的美人编号'];
        }

        $member = DB::table('member')->where('uid', $uid)->first();
        $costSilver = 5000;
        $curSilver = (int)($member['silver'] ?? 0);

        if ($curSilver < $costSilver) {
            return ['status' => -1, 'message' => "银两不足，赠送鲜花需要 {$costSilver} 银两"];
        }

        $userBeauties = self::getBeautyData($uid);
        $userB = $userBeauties[$bid] ?? ['level' => 1, 'intimacy' => 0];

        $newIntimacy = (int)$userB['intimacy'] + 20;
        $curLvl = (int)$userB['level'];

        // 亲密度达到升级要求自动晋阶
        $leveledUp = false;
        if ($newIntimacy >= $curLvl * 100) {
            $newIntimacy -= $curLvl * 100;
            $curLvl++;
            $leveledUp = true;
        }

        $userBeauties[$bid] = [
            'level' => $curLvl,
            'intimacy' => $newIntimacy,
        ];
        Redis::setJson('user_beauties:' . $uid, $userBeauties, 86400 * 30);

        $newSilver = $curSilver - $costSilver;
        DB::table('member')->where('uid', $uid)->update(['silver' => $newSilver]);
        Redis::del('player:' . $uid);

        $beautyName = self::BEAUTIES[$bid]['name'];
        if ($leveledUp) {
            $pName = $member['nickname'] ?? ('主公' . $uid);
            Broadcaster::broadcast('marquee', [
                'content' => "铜雀生辉！主公【{$pName}】与绝色佳人【{$beautyName}】情深意浓，晋阶至 {$curLvl} 重天，全军属性暴增！",
                'type' => 'system_broadcast',
            ]);
        }

        return [
            'status' => 1,
            'bid' => $bid,
            'name' => $beautyName,
            'level' => $curLvl,
            'intimacy' => $newIntimacy,
            'leveled_up' => $leveledUp ? 1 : 0,
            'silver' => $newSilver,
            'message' => "成功向【{$beautyName}】赠送一束娇艳玫瑰！亲密度 +20！" . ($leveledUp ? " 绝色美人等级突破至 {$curLvl} 级！" : ''),
        ];
    }

    /**
     * 赏赐珠宝 (消耗元宝): c=beauty&m=gift
     */
    public static function gift(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $bid = (int)($ctx->input('id', $ctx->input('bid', 1)));

        if (!isset(self::BEAUTIES[$bid])) {
            return ['status' => 0, 'message' => '无效的美人编号'];
        }

        $member = DB::table('member')->where('uid', $uid)->first();
        $costGold = 20;
        $curGold = (int)($member['gold'] ?? 0);

        if ($curGold < $costGold) {
            return ['status' => -2, 'message' => "元宝不足，赏赐珠宝需要 {$costGold} 元宝"];
        }

        $userBeauties = self::getBeautyData($uid);
        $userB = $userBeauties[$bid] ?? ['level' => 1, 'intimacy' => 0];

        $newIntimacy = (int)$userB['intimacy'] + 100;
        $curLvl = (int)$userB['level'];

        $leveledUp = false;
        while ($newIntimacy >= $curLvl * 100) {
            $newIntimacy -= $curLvl * 100;
            $curLvl++;
            $leveledUp = true;
        }

        $userBeauties[$bid] = [
            'level' => $curLvl,
            'intimacy' => $newIntimacy,
        ];
        Redis::setJson('user_beauties:' . $uid, $userBeauties, 86400 * 30);

        $newGold = $curGold - $costGold;
        DB::table('member')->where('uid', $uid)->update(['gold' => $newGold]);
        Redis::del('player:' . $uid);

        $beautyName = self::BEAUTIES[$bid]['name'];

        return [
            'status' => 1,
            'bid' => $bid,
            'name' => $beautyName,
            'level' => $curLvl,
            'intimacy' => $newIntimacy,
            'leveled_up' => $leveledUp ? 1 : 0,
            'gold' => $newGold,
            'message' => "赐予【{$beautyName}】夜明珠美玉！亲密度 +100！" . ($leveledUp ? " 绝色美人等级突破至 {$curLvl} 级！" : ''),
        ];
    }
}
