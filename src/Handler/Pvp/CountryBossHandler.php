<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Pvp;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\General\TavernHandler;
use Hanjiang\MasterData\Equip;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\CountryBoss;
use Hanjiang\Model\Member;
use Hanjiang\Model\WorldBoss;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 国家 BOSS 蚩尤（移植旧版 controllers/countryboss.php，状态见 Model\CountryBoss）
 *
 * 每周五 20:30~20:45，国家（玩家军团）等级 40 级、主公 50 级开放；每个国家各打各的 BOSS（按 member.country 分键）。
 * 血量 20 亿 × 1.03^往周击杀次数，打死算胜利；按国家内伤害排名（前 50 名）发宝石 / 装备碎片（country_boss_reward），
 * 每 10 次击杀宝石 × 1.1，最后一击宝石 × 1.5。BOSS 数据 6 天后过期，下周五重新建档。旧版没有国家 BOSS 的计划任务。
 *
 * 客户端调用：index、battle、powerup、reward（countrybossworld.js / miscpop.js）。
 * 旧版的 clean（diy=1 清空全部国家 BOSS 键）与 power_up_pay（银币鼓舞价格，控制器内部用）客户端不调用，不对外暴露。
 */
final class CountryBossHandler
{
    /** 立即复活花费元宝 */
    private const int RELIVE_COST = 20;
    /** 复活间隔时间 */
    private const int RELIVE_TIME = 60;
    /** 元宝鼓舞花费 */
    private const int POWERUP_GOLD_COST = 20;

    /** 旧版语言包 base_friday / base_chip */
    private const string LANG_FRIDAY = '周五';
    private const string LANG_CHIP = '碎片';

    /**
     * 初始化接口: c=countryboss&m=index
     *
     * status
     *			8 国家等级
     *			7 未加入国家
     *			6 主公等级太低
     *			5 今日已参加过活动
     *			4 活动已结束
     *			3 活动未开启
     *			2 战斗准备
     *			1 领取奖励
     *
     * 旧版控制器的 _played 恒为 0（构造函数没有调用 check_played），5 不会出现；
     * 未加入国家时 member.country 为 0，旧版判断的是 < 0，实际返回 8（国家等级不够）。
     */
    public static function index(Context $ctx): array
    {
        $now = BossClock::now();
        $return = [
            'status' => 0,
            'countdown' => 0,		// 活动倒计时（单位：秒) status=2时有效
            'powerup' => 100,		// 攻击力加成百分比（单位 %）status=2时有效
            'bossinfo' => [],		// boss信息数组 status=2时有效
            'reward' => [],			// 奖励信息 status=1时有效
            'top' => [],			// top排行 status=1时有效
            'cd' => 0,				// 复活CD
            'upcost' => [],			// 鼓舞花费
            'cdcost' => 0,			// 复活花费
            'my' => [],				// 玩家伤害排名信息
        ];
        $member = self::member(MemberHandler::resolveUid($ctx));
        // 用户等级不够
        if ($member === null || (int)$member['level'] < CountryBoss::MEMBER_LEVEL) {
            $return['status'] = 6;
            $return['member_level'] = CountryBoss::MEMBER_LEVEL;
            return $return;
        }
        $uid = (int)$member['uid'];
        $country = (int)$member['country'];
        // 未加入国家
        if ($country < 0) {
            $return['status'] = 7;
            return $return;
        }
        // 国家等级不够
        if (!self::isOpen($country)) {
            $return['status'] = 8;
            $return['country_level'] = CountryBoss::COUNTRY_LEVEL;
            return $return;
        }
        $countdown = CountryBoss::countdown($now);
        $played = 0;

        if ($countdown === -1) {
            // 活动未开启：返回活动开启时间
            $return['status'] = 3;
            $return['starttime'] = self::LANG_FRIDAY . ' ' . substr(CountryBoss::START_TIME, 0, 2) . ':' . substr(CountryBoss::START_TIME, 2, 2);
        } elseif ($countdown === 0) {
            // 最后一击公告只发一次（final_horn）；依赖未移植：发送公告 chat_model->get_message_str(17, from 最后一击玩家) + send_message_to_country(…, 国家成员, 6, 1)
            CountryBoss::finalUid($country);
            self::rewardStatus($return, $country, $uid, $played, $now);
        } else {
            // boss信息
            $bossInfo = CountryBoss::bossInfo($country, $now);
            if ((int)$bossInfo['blood'] <= 0) {
                // 依赖未移植：最后一击聊天公告（同上，只发一次）
                CountryBoss::finalUid($country);
                // 活动未结束 boss已经死了
                self::rewardStatus($return, $country, $uid, $played, $now);
            } else {
                // 活动进行中 准备数据
                $return['status'] = 2;
                $return['countdown'] = $countdown;
                // 鼓舞
                $return['powerup'] = CountryBoss::powerUp($country, $uid);
                $bossInfo['blood'] = sprintf('%.2f', $bossInfo['blood'] * 100 / $bossInfo['allblood']);
                foreach (['name', 'blood', 'image'] as $value) {
                    $return['bossinfo'][$value] = $bossInfo[$value];
                }
                $delay = CountryBoss::checkDelay($country, $uid);
                $return['cd'] = 0;
                if ($delay > 0 && self::RELIVE_TIME > $delay) {
                    $return['cd'] = self::RELIVE_TIME - $delay + 1;
                }
                $return['cdcost'] = self::RELIVE_COST;
                $return['upcost']['gold'] = self::POWERUP_GOLD_COST;
                $return['upcost']['silver'] = self::powerUpPay($member);
                $return['my']['rank'] = CountryBoss::myRank($country, $uid);
                $return['my']['hit'] = CountryBoss::myHit($country, $uid);
            }
        }
        // 排行一直返回
        $return['top'] = WorldBoss::withMemberInfo(CountryBoss::topRank($country), CountryBoss::HIT_TOP_NUM);
        // 查询boss的击杀次数
        $info = CountryBoss::killedLog($country, $now);
        $return['level'] = $info['level'];
        $return['add'] = $info['add'] * 10 + 100;
        return $return;
    }

    /**
     * 战斗: c=countryboss&m=battle
     *
     * status	5 活动结束
     *			4 boss已经死了
     *			3 金币不足
     *			2 等待复活CD
     *			1 成功进入战斗
     */
    public static function battle(Context $ctx): array
    {
        $now = BossClock::now();
        $return = ['status' => 0, 'info' => [], 'battle' => []];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null || CountryBoss::countdown($now) <= 0) {
            // 活动结束
            $return['status'] = 5;
            return $return;
        }
        $uid = (int)$member['uid'];
        $country = (int)$member['country'];
        $relive = (int)$ctx->input('now', 0);

        // 获取boss信息
        $bossInfo = CountryBoss::bossInfo($country, $now);
        if ((int)$bossInfo['blood'] <= 0) {
            // boss已经死了
            $return['status'] = 4;
            return $return;
        }
        if (CountryBoss::isFinalHorn($country)) {
            $return['status'] = 4;
            return $return;
        }
        // 检查战斗锁 + 战斗处理锁（1 秒）
        if (!CountryBoss::battleLock($uid)) {
            $return['status'] = 2;
            $return['info']['cd'] = 61;
            return $return;
        }

        if ($relive !== 0) {
            // 立即复活：有 CD 时才扣元宝
            $delay = CountryBoss::checkDelay($country, $uid);
            if ($delay > 0 && $delay < self::RELIVE_TIME) {
                if (!Member::deductCurrency($uid, gold: self::RELIVE_COST)) {
                    // 金币不足
                    $return['status'] = 3;
                    return $return;
                }
                // 依赖未移植：元宝流水 gold_log（member_model->sub_gold）、消费统计 stat_model->countryboss_relive
            }
            $return['info']['all_gold'] = self::gold($uid);
        } else {
            // 正常进入战斗：检查cd
            $delay = CountryBoss::checkDelay($country, $uid);
            if ($delay > 0 && $delay < self::RELIVE_TIME) {
                // 等待CD进入战斗
                $return['status'] = 2;
                $return['info']['cd'] = self::RELIVE_TIME - $delay + 1;
                return $return;
            }
        }

        /* 正常战斗处理 */
        $return['status'] = 1;
        // 战斗攻击加成系数
        $attackCoe = CountryBoss::powerUp($country, $uid) / 100;
        // 战斗（battle_model->battle_worldboss($boss_info, $attack_coe, 0, 1)：_pve = 1，_boss_battle = 2）
        $info = WorldBoss::battle($uid, $bossInfo, $attackCoe, 2);

        // 记录伤害
        $hit = $blood = 0;
        foreach ($info['process'] ?? [] as $key => $value) {
            if ($value['launch'] === 'left') {
                foreach ($value['hurt'] as $v) {
                    if (isset($v['self']) && $v['self'] == 0) {
                        $hit += $v['blood'];
                        $blood += $v['blood'];
                    }
                }
                $percent = sprintf('%.2f', $blood * 100 / $bossInfo['allblood']);
                if ($percent < 0.01 && $blood >= 10000) {
                    $info['process'][$key]['hurt'][0]['percent'] = 0.01;
                    $blood = 0;
                }
            }
        }
        if ($info !== null) {
            foreach ($info['rightteam'] as $key => $value) {
                $info['rightteam'][$key]['allblood'] = $bossInfo['allblood'];
                $info['rightteam'][$key]['blood'] = $bossInfo['blood'];
                $info['rightteam'][$key]['herotype'] = 0;
            }
            foreach ($info['rightresult'] as $key => $value) {
                $info['rightresult'][$key]['allblood'] = $bossInfo['allblood'];
                $info['rightresult'][$key]['herotype'] = 0;
            }
        }
        CountryBoss::updateMyHit($country, $uid, $hit);
        $return['battle'] = $info;
        // 返回战斗头信息（谁攻击谁）
        $return['battle']['left_role'] = [
            'name' => $member['nickname'],
            'level' => $member['level'],
            'gender' => $member['gender'],
        ];
        $return['battle']['right_role'] = [
            'name' => $bossInfo['name'],
            'level' => $bossInfo['level'],
            'type' => 1,
        ];
        $return['info']['win'] = $return['battle']['assess'] ?? null;

        if ($return['info']['win'] >= 1) {
            // 记录击杀log
            CountryBoss::addKilledLog($country, $now);
        }
        return $return;
    }

    /**
     * 鼓舞: c=countryboss&m=powerup
     *
     * status	1 鼓舞成功
     *			2 金币不足
     *			3 银币不足
     *			4 鼓舞达到上限
     */
    public static function powerup(Context $ctx): array
    {
        $return = ['status' => 0, 'powerup' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        $uid = (int)$member['uid'];
        $country = (int)$member['country'];
        $powerup = CountryBoss::powerUp($country, $uid);
        if ($powerup >= CountryBoss::POWER_UP_MAX) {
            $return['status'] = 4;
            $return['powerup'] = $powerup;
            return $return;
        }
        $gold = (bool)$ctx->input('gold', 0);
        $silver = 0;
        $goldCost = 0;
        if ($gold) {
            $goldCost = self::POWERUP_GOLD_COST;
            if (!Member::deductCurrency($uid, gold: $goldCost)) {
                // 金币不足
                $return['status'] = 2;
                $goldCost = 0;
            }
            // 依赖未移植：元宝流水 gold_log、消费统计 stat_model->countryboss_powerup（旧版扣费失败也记）
            $return['all_gold'] = self::gold($uid);
        } else {
            $silver = self::powerUpPay($member);
            if (!Member::deductCurrency($uid, silver: $silver)) {
                // 银币不足
                $return['status'] = 3;
                $silver = 0;
            }
            $return['all_silver'] = (int)DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('silver');
        }
        if ($return['status'] === 0) {
            $newPowerup = CountryBoss::incrPowerUp($country, $uid);
            if ($newPowerup === null) {
                // 并发鼓舞已到上限：退回本次花费
                Member::addCurrency($uid, silver: $silver, gold: $goldCost);
                unset($return['all_gold'], $return['all_silver']);
                $return['status'] = 4;
                $return['powerup'] = CountryBoss::powerUp($country, $uid);
                return $return;
            }
            // 鼓舞成功
            $return['status'] = 1;
            $return['powerup'] = $newPowerup;
        }
        return $return;
    }

    /**
     * 领取奖励: c=countryboss&m=reward
     *
     * status状态：
     * 1 领取成功
     * 2 已领取，或是未参加
     * -1 活动未结束（boss 未死）
     */
    public static function reward(Context $ctx): array
    {
        $now = BossClock::now();
        $return = [
            'status' => 0,
            'reward' => [
                'jadetype' => 0,
                'jadenum' => 0,
                'itemid' => 0,
                'itemnum' => 0,
                'quality' => 0,
                'itemname' => '',
                'lasthit' => 0,
                'hit' => 0,
                'win' => 0,
            ],
        ];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            $return['status'] = 2;
            return $return;
        }
        $uid = (int)$member['uid'];
        $country = (int)$member['country'];
        // 获取boss信息
        $win = 0;
        $bossInfo = CountryBoss::bossInfo($country, $now);
        if ((int)$bossInfo['blood'] <= 0) {
            // boss死了就是胜利
            $win = 1;
            $return['reward']['win'] = $win;
        } elseif (CountryBoss::countdown($now) > 0) {
            // 判断活动是否结束
            $return['status'] = -1;
            return $return;
        }
        // 获取奖励信息
        $reward = CountryBoss::reward($country, $uid, $win, $now);
        // 我的伤害、我的排名（删除伤害记录之前取）
        $myHit = CountryBoss::myHit($country, $uid);
        $myRank = CountryBoss::myRank($country, $uid);
        // 删除伤害记录，防止重复领取
        if ($reward['status'] === 1 && CountryBoss::delMyHit($country, $uid, $now)) {
            // 添加道具碎片
            if ($reward['itemid'] > 0 && $reward['itemnum'] > 0) {
                $item = Equip::ROWS[$reward['itemid']] ?? null;
                if ($item !== null) {
                    // 道具存在
                    $return['reward']['itemid'] = (int)$item['id'];
                    $return['reward']['quality'] = (int)$item['quality'];
                    $return['reward']['itemnum'] = $reward['itemnum'];
                    $return['reward']['itemname'] = $item['name'] . self::LANG_CHIP;
                    // 依赖未移植：添加碎片 equipment_model->add_item($item, 1, itemnum)（Equipment 碎片分支、itemchip_log）
                }
            }
            // 添加宝石
            if ($reward['jadetype'] > 0 && $reward['jadenum'] > 0) {
                $return['reward']['jadetype'] = $reward['jadetype'];
                $return['reward']['jadenum'] = $reward['jadenum'];
                $return['reward']['jadename'] = $reward['jadetype'] === 2 ? '金宝石' : '紫宝石';
                TavernHandler::addJade($uid, $reward['jadetype'], $reward['jadenum']);
            }
            // 最后一击
            $return['reward']['lasthit'] = $reward['lasthit'];
            // 我的伤害
            $return['reward']['hit'] = $myHit;
            // 我的排名
            $return['reward']['rank'] = $myRank;
            // 领取成功
            $return['status'] = 1;
        } else {
            // 没有参与活动 或 已经领取奖励
            $return['status'] = 2;
        }
        return $return;
    }

    /**
     * index 的 status 1 / 4 / 5：有伤害记录可领奖（win -1 失败、1 boss 已死），否则已参加 / 已结束
     *
     * @param array<string, mixed> $return
     */
    private static function rewardStatus(array &$return, int $country, int $uid, int $played, int $now): void
    {
        if (CountryBoss::myHit($country, $uid) === 0) {
            // 已参加过活动 / 活动已结束
            $return['status'] = $played === 1 ? 5 : 4;
            return;
        }
        // 参加活动未领取奖励
        $return['status'] = 1;
        $return['win'] = -1;
        if ((int)CountryBoss::bossInfo($country, $now)['blood'] <= 0) {
            // boss死了就是胜利
            $return['win'] = 1;
        }
    }

    /**
     * 国家等级 40 级开放国家 BOSS（控制器构造函数 _open：未加入国家为 0）
     */
    private static function isOpen(int $country): bool
    {
        if ($country <= 0) {
            return false;
        }
        $info = CountryHandler::countryInfo($country);
        return $info !== null && (int)$info['level'] >= CountryBoss::COUNTRY_LEVEL;
    }

    /**
     * 银币鼓舞花费（power_up_pay）
     *
     * @param array<string, mixed> $member
     */
    private static function powerUpPay(array $member): int
    {
        return (int)$member['level'] * 500 * 2;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function member(int $uid): ?array
    {
        if ($uid <= 0) {
            return null;
        }
        return DB::table('member')->select('uid', 'nickname', 'level', 'gender', 'gold', 'silver', 'country')
            ->where('uid', $uid)->whereNull('deleted_at')->first();
    }

    private static function gold(int $uid): int
    {
        return (int)DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('gold');
    }
}
