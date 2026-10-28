<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use OmniPHP\WebSocket\Broadcaster;

/**
 * 铜雀台绝色佳人、红颜技能与后宫互动处理器 (DDD Activity Domain)
 */
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
        $beautyId = (int)($ctx->input('beauty_id', $ctx->input('bid', 1)));
        if (!isset(self::BEAUTIES[$beautyId])) {
            $beautyId = 1;
        }

        $list = [];
        $activeList = [];
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

            $activeList[] = [
                'beauty_id' => $id,
                'name' => (string)$b['name'],
                'active' => ($id === 1) ? 1 : 0,
                'quality' => 3,
            ];
        }

        $chips = [];
        for ($c = 1; $c <= 6; $c++) {
            $chips[] = [
                'chip_id' => $c,
                'chip_name' => "绝色碎片{$c}",
                'chip' => 0,
                'own' => 0,
            ];
        }

        $specialSkill = [
            'id' => 13,
            'skill_name' => '天香红颜',
            'num' => 50000,
            'allnum' => 50000,
            'lock' => 1,
            'lock_msg' => 1,
        ];

        $curUserB = $userBeauties[$beautyId] ?? ['level' => 1, 'intimacy' => 0];
        $curLvl = (int)$curUserB['level'];

        $beautyInfo = [
            'info' => [
                'beauty_id' => $beautyId,
                'beauty_quality' => 3,
                'beauty_level' => $curLvl,
                'beauty_active' => 1,
                'skill_cd' => 0,
                'is_active' => 1,
                'battle_cd' => 0,
                'up_status' => 1,
                'sub_rose' => 5,
                'max_num' => 3,
                'buy_rose' => 20,
                'up_cd' => 0,
                'up_succ' => 0,
                'conversion_status' => 0,
            ],
            'active' => $activeList,
            'battle' => [
                'gold' => 50,
                'times' => 0,
            ],
            'gift_info' => [
                0 => ['type' => 1, 'gold' => 500, 'ratio' => 50],
                1 => ['type' => 2, 'diamond' => 10, 'ratio' => 50],
                2 => ['type' => 3, 'diamond' => 18, 'ratio' => 100],
            ],
            'skill' => [
                [
                    'id' => 1,
                    'name' => '倾国绝色',
                    'content' => '全军普通攻击力提升 50 点',
                    'value' => 50,
                    'lock' => 1,
                    'next_value' => 60,
                ],
                [
                    'id' => 2,
                    'name' => '闭月天姿',
                    'content' => '全军策攻提升 40 点',
                    'value' => 40,
                    'lock' => 0,
                    'next_value' => 50,
                ],
            ],
            'chip' => $chips,
            'happy' => [
                'quality' => 1,
                'ratio' => 20,
                'skill' => 0,
                'rose' => 100,
                'diamond' => 50,
            ],
            'special_skill' => $specialSkill,
        ];

        return [
            'status' => 1,
            'beauty_info' => $beautyInfo,
            'special_skill' => $specialSkill,
            'say' => '主公，妾身这厢有礼了。',
            // 向下兼容保留原字段
            'list' => $list,
            'total_beauties' => count($list),
            'message' => 'ok',
        ];
    }

    /**
     * 获取指定美人信息: c=beauty&m=get_beauty_info
     */
    public static function get_beauty_info(Context $ctx): array
    {
        return self::index($ctx);
    }

    /**
     * 美人休息: c=beauty&m=rest_beauty
     */
    public static function rest_beauty(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->first();

        $specialSkill = [
            'id' => 13,
            'skill_name' => '天香红颜',
            'num' => 50000,
            'allnum' => 50000,
            'lock' => 1,
            'lock_msg' => 1,
        ];

        return [
            'status' => 1,
            'wakeup_num' => 0,
            'say' => '妾身暂且歇息，主公得闲常来看看妾身。',
            'special_skill' => $specialSkill,
            'all_gold' => (int)($member['gold'] ?? 0),
            'all_silver' => (int)($member['silver'] ?? 0),
            'message' => '美人已安置歇息',
        ];
    }

    /**
     * 激活美人: c=beauty&m=wakeup_beauty
     */
    public static function wakeup_beauty(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->first();

        $specialSkill = [
            'id' => 13,
            'skill_name' => '天香红颜',
            'num' => 50000,
            'allnum' => 50000,
            'lock' => 1,
            'lock_msg' => 1,
        ];

        return [
            'status' => 1,
            'wakeup_num' => 1,
            'say' => '主公唤妾身何事？妾身自当全力以赴！',
            'special_skill' => $specialSkill,
            'all_gold' => (int)($member['gold'] ?? 0),
            'all_silver' => (int)($member['silver'] ?? 0),
            'message' => '美人已随驾侍奉',
        ];
    }

    /**
     * 领取特殊技能奖励: c=beauty&m=action_special_reward
     */
    public static function action_special_reward(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $rewardSilver = 50000;
        MemberHandler::addCurrency($uid, silver: $rewardSilver);

        $member = DB::table('member')->where('uid', $uid)->first();

        $specialSkill = [
            'id' => 13,
            'skill_name' => '天香红颜',
            'num' => 0,
            'allnum' => 50000,
            'lock' => 1,
            'lock_msg' => 1,
        ];

        return [
            'status' => 1,
            'silver' => $rewardSilver,
            'all_silver' => (int)($member['silver'] ?? 0),
            'special_skill' => $specialSkill,
            'message' => "成功领取美人红颜恩赐：银两 {$rewardSilver}！",
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

        $specialSkill = [
            'id' => 13,
            'skill_name' => '天香红颜',
            'num' => 50000,
            'allnum' => 50000,
            'lock' => 1,
            'lock_msg' => 1,
        ];

        return [
            'status' => 1,
            'bid' => $bid,
            'name' => $beautyName,
            'level' => $curLvl,
            'intimacy' => $newIntimacy,
            'leveled_up' => $leveledUp ? 1 : 0,
            'silver' => $newSilver,
            'rose' => 100,
            'skill_cd' => 86400,
            'say' => "得蒙主公垂青赠妾身鲜花，妾身喜不自胜！",
            'special_skill' => $specialSkill,
            'message' => "成功向【{$beautyName}】赠送一束娇艳玫瑰！亲密度 +20！" . ($leveledUp ? " 绝色美人等级突破至 {$curLvl} 级！" : ''),
        ];
    }

    /**
     * 赏赐珠宝 (消耗元宝): c=beauty&m=gift
     */
    public static function gift(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $bid = (int)($ctx->input('id', $ctx->input('bid', $ctx->input('beauty_id', 1))));

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

        $newGold = $curGold - $costGold;
        DB::table('member')->where('uid', $uid)->update(['gold' => $newGold]);
        Redis::del('player:' . $uid);

        $beautyName = self::BEAUTIES[$bid]['name'];
        if ($leveledUp) {
            $pName = $member['nickname'] ?? ('主公' . $uid);
            Broadcaster::broadcast('marquee', [
                'content' => "国色天香！主公【{$pName}】赏赐珠宝博得佳人【{$beautyName}】倾城一笑，等级突破至 {$curLvl} 级！",
                'type' => 'system_broadcast',
            ]);
        }

        $specialSkill = [
            'id' => 13,
            'skill_name' => '天香红颜',
            'num' => 50000,
            'allnum' => 50000,
            'lock' => 1,
            'lock_msg' => 1,
        ];

        $happy = [
            'quality' => min(5, max(1, (int)ceil($newIntimacy / 20))),
            'ratio' => min(100, $newIntimacy),
            'skill' => min(100, (int)floor($newIntimacy / 5)),
            'rose' => 100,
            'diamond' => 50,
        ];

        return [
            'status' => 1,
            'happy' => $happy,
            'diamond_num' => 50,
            'gold' => $newGold,
            'all_gold' => $newGold,
            'say' => "谢主公厚赠！妾身定当竭力侍奉主公！",
            'special_skill' => $specialSkill,
            // 向下兼容保留原字段
            'bid' => $bid,
            'name' => $beautyName,
            'level' => $curLvl,
            'intimacy' => $newIntimacy,
            'leveled_up' => $leveledUp ? 1 : 0,
            'message' => "珠宝赏赐成功！【{$beautyName}】芳心暗许，亲密度暴增 100 点！",
        ];
    }
}
