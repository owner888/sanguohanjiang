<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use Hanjiang\Handler\Activity\MessageHandler;
use Hanjiang\MasterData\Vip as VipTemplate;
use Hanjiang\MasterData\VipReward;
use OmniPHP\Database\DB;

/**
 * VIP 记录与充值记 VIP（移植旧版 models/vip_model.php：get_vip_log / get_vip_info / pay / clear_cd /
 * update_gift_log / add_epic_times / first_recharge / recharge_list / get_recharge_list）
 *
 * 存储同旧版：vip_log（每人一行：累计充值人民币 money、VIP 等级 level、已领 VIP 礼包等级 gift_num、首充状态 type）、
 * recharge_list_log（每个充值档次首次充值记录）、pay_log（充值成功订单流水）；VIP 等级同时写 member.vip，
 * 其他玩法的 VIP 特权都读 member.vip。
 *
 * VIP 等级门槛取 Vip 表的 toplimit（人民币，旧版数据 VIP1~9 为 1~9 元，VIP10 起 5000 元）。
 * 只供 Model\Pay::complete 在玩家行锁的事务里调用。
 */
final class Vip
{
    /** 首充档次（元宝 => 档次，旧版 $pay_list） */
    public const array PAY_LIST = [
        60 => 1,
        300 => 2,
        680 => 3,
        1980 => 4,
        3280 => 5,
        6480 => 6,
    ];

    /** 信件类型 2：vip 充值通知（message_model->send_message，发件人取 get_title(2)） */
    public const int MESSAGE_TYPE_VIP = 2;

    /** message_lang.php / base_lang.php */
    private const string LANG_VIP_TITLE = 'vip通知';
    private const string LANG_PAY_MONEY = '恭喜您成功充值';
    private const string LANG_PAY_REBATE = '返利附赠';
    private const string LANG_PAY_FIRST = '另赠送您';
    private const string LANG_PAY_LEVEL_UP = 'VIP等级提升到了【VIP%s】';
    private const string LANG_HAPPLY_GAME = '祝您游戏愉快！';
    private const string LANG_GOLD = '元宝';

    /**
     * 取用户 vip_log（get_vip_log）；没有记录为 null
     *
     * @return array{uid: int, money: int, level: int, dateline: int, gift_num: int, type: int}|null
     */
    public static function log(int $uid): ?array
    {
        $row = DB::table('vip_log')->select('uid', 'money', 'level', 'dateline', 'gift_num', 'type')
            ->where('uid', $uid)->whereNull('deleted_at')->first();
        return $row === null ? null : array_map('intval', $row);
    }

    /**
     * VIP 规则 + VIP 等级奖励（get_vip_info：vip 表与 vip_reward 表按 vip_level 合并），按 vip_level 索引
     *
     * @return array<int, array<string, int>>
     */
    public static function info(): array
    {
        $list = [];
        foreach (VipTemplate::ROWS as $level => $row) {
            $list[$level] = array_merge($row, VipReward::ROWS[$level] ?? []);
        }
        return $list;
    }

    /**
     * 累计充值金额对应的 VIP 等级（pay：toplimit 不为 0 且累计金额 >= toplimit 的最高一级）
     */
    public static function rank(int $money): int
    {
        $rank = 0;
        $series = self::info();
        $vipNum = count($series);
        for ($i = 1; $i <= $vipNum; $i++) {
            if ($series[$i]['toplimit'] && $money >= $series[$i]['toplimit']) {
                $rank = $i;
            }
        }
        return $rank;
    }

    /**
     * 充值记 VIP（移植 vip_model->pay）
     *
     * 须在 Model\Pay::complete 的玩家行锁事务里调用。充值信件（message_model->send_message type 2）由调用方在提交后发送，
     * 内容见返回的 mail。
     *
     * @param int $addMoney 充值金额（人民币）
     * @param int $type 1 表示不计首充
     * @param int $addGold 充值附赠元宝（返利 / 节日返利，只用于信件）
     * @return array{status: int, type: int, rank: int, mail: string}
     */
    public static function pay(int $uid, int $addMoney, int $type, int $addGold, string $orderid, string $channel, int $now): array
    {
        $return = ['status' => 0, 'type' => 0, 'rank' => 0, 'mail' => ''];
        $firstPay = 0;
        $money = $addMoney;
        // 取用户 vip_log；用户总金额
        $log = self::log($uid);
        if ($log !== null) {
            $money += $log['money'];
        }
        // vip 系列信息
        $rank = self::rank($money);
        $viplevel = 0;
        // 充值列表首充数据记录
        $msgInfo = self::rechargeList($uid, $addMoney, $now);
        // 首充系统 - 首充返利
        FirstRechargeLog::recordRecharge($uid, $addMoney);

        // 修改 vip 记录
        $status = 0;
        if ($log === null) {
            // 添加
            $firstPay = 1;
            $viplevel = $rank;
            $data = [
                'uid' => $uid,
                'money' => $money,
                'level' => $rank,
                'dateline' => $now,
                'type' => 1,
            ];
            $return['type'] = 1;
            if ($type === 1) {
                $data['type'] = 0;
                $return['type'] = 0;
            }
            if (DB::table('vip_log')->insert($data) !== false) {
                $status = 1;
            }
            // 更新赠送继承cd（旧版直接记当前时间，VIP 10 赠送的至尊继承随即不可用，照旧版）
            if ($rank >= 10) {
                DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->update(['inherit_dateline' => $now]);
            }
            // 清空功能 cd
            if ($rank >= 3) {
                self::clearCd($uid);
            }
        } else {
            // 充值前 vip 等级
            $lastVip = $log['level'];
            if ($log['level'] !== $rank) {
                $viplevel = $rank;
            }
            $data = [
                'level' => $rank,
                'money' => $money,
                'dateline' => $now,
            ];
            if ($log['type'] < 1 && $type !== 1) {
                $data['type'] = 1;
            }
            if (DB::table('vip_log')->where('uid', $uid)->whereNull('deleted_at')->update($data) > 0) {
                $status = 1;
            }
            // 判断是否要更新继承cd：跨过 VIP 4 / 6 / 8 时重置免费继承
            if ($lastVip !== $rank && (($lastVip < 4 && $rank >= 4) || ($lastVip < 6 && $rank >= 6) || ($lastVip < 8 && $rank >= 8))) {
                DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->update(['free_inherit' => 0]);
            }
            // 判断是否更新赠送继承cd（同上，跨过 VIP 10 时记当前时间）
            if ($lastVip !== $rank && $lastVip <= 9 && $rank >= 10) {
                DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->update(['inherit_dateline' => $now]);
            }
            // 判断是否清空 cd
            if ($lastVip !== $rank && $lastVip < 3 && $rank >= 3) {
                self::clearCd($uid);
            }
            // 判断充值后是否有 vip 奖励发放：天下史诗每日刷新 / 锁定次数
            if ($lastVip !== $rank && $rank >= 11) {
                if ($rank === 11) {
                    self::addEpicTimes($uid, 15, $now);
                } elseif ($rank === 12) {
                    self::addEpicTimes($uid, 30, $now);
                } elseif ($rank === 13) {
                    self::addEpicTimes($uid, 45, $now);
                }
            }
            // 依赖未移植：vip 赌石免费切石次数（member_field.stone1~3_times，赌石玩法未移植）
        }

        // 判断是否成功
        if ($status > 0) {
            $return['status'] = 1;
            $return['rank'] = $rank;
            DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->update(['vip' => $rank]);

            // 发送信件（get_message type 2）
            $mail = self::LANG_PAY_MONEY . ($addMoney * 10) . self::LANG_GOLD . ',';
            if ($addGold > 0) {
                $mail .= self::LANG_PAY_REBATE . $addGold . self::LANG_GOLD . ',';
            }
            if ($msgInfo['status'] === 2) {
                $mail .= self::LANG_PAY_FIRST . $msgInfo['other_gold'] . self::LANG_GOLD . ',';
            }
            if ($viplevel > 0) {
                $mail .= sprintf(self::LANG_PAY_LEVEL_UP, $viplevel) . ',';
            }
            $return['mail'] = $mail . self::LANG_HAPPLY_GAME;

            // 记录成功订单（旧版只在充值玩家即当前请求玩家时记；新版充值入口都按订单玩家记）
            $member = DB::table('member')->select('level', 'created_at')->where('uid', $uid)->whereNull('deleted_at')->first();
            $regchannel = (int)(DB::table('user')->where('uid', $uid)->value('channel') ?? 0);
            DB::table('pay_log')->insert([
                'uid' => $uid,
                'level' => (int)($member['level'] ?? 0),
                'money' => $addMoney,
                'vip' => $rank,
                'dateline' => $now,
                'first' => $firstPay,
                'channel' => $regchannel,
                'regchannel' => $regchannel,
                'regtime' => $member === null ? 0 : (int)strtotime((string)$member['created_at']),
                'paytype' => Member::logText($channel, 255),
                'orderid' => $orderid,
                'class' => 'pay',
                'method' => 'complete',
                'argument' => '',
            ]);
        }

        return $return;
    }

    /**
     * 发充值信件（pay 末尾的 message_model->send_message type 2；在充值事务提交后调用）
     */
    public static function sendPayMail(int $uid, string $mail): void
    {
        if ($mail !== '') {
            MessageHandler::sendSystemMessage([$uid], self::MESSAGE_TYPE_VIP, self::LANG_VIP_TITLE, $mail);
        }
    }

    /**
     * 记录礼包领取记录（update_gift_log）
     *
     * 多 Worker 安全：只在 gift_num 仍为 $level - 1 时写入（同一级礼包并发领取只有一个成功），成功返回 true。
     */
    public static function claimGift(int $uid, int $level, int $now): bool
    {
        return DB::execute(
            'UPDATE `zc_sanguo_vip_log` SET `gift_num` = :level, `dateline` = :now
             WHERE `uid` = :uid AND `gift_num` = :prev AND `level` >= :need AND `deleted_at` IS NULL',
            ['level' => $level, 'now' => $now, 'uid' => $uid, 'prev' => $level - 1, 'need' => $level]
        ) > 0;
    }

    /**
     * 获取充值列表信息（get_recharge_list）：档次 => 记录
     *
     * @return array<int, array<string, int>>
     */
    public static function rechargeLog(int $uid): array
    {
        $return = [];
        $rows = DB::table('recharge_list_log')->select('uid', 'site', 'money', 'dateline')
            ->where('uid', $uid)->whereNull('deleted_at')->get();
        foreach ($rows as $val) {
            $return[(int)$val['site']] = array_map('intval', $val);
        }
        return $return;
    }

    /**
     * 记录充值列表（recharge_list）
     *
     * 按充值元宝取达到的最高档次；该档次第一次充值时记一条，若玩家已有首充日志，再赠送该档次的元宝（首充返利提醒）。
     * 唯一键 uid + site：同档次只记一次。
     *
     * @return array{status: int, other_gold: int} status 1 记录成功，2 记录成功并赠送了 other_gold 元宝
     */
    private static function rechargeList(int $uid, int $addMoney, int $now): array
    {
        $return = ['status' => 0, 'other_gold' => 0];
        // 充值元宝
        $addGold = $addMoney * 10;
        // 获取充值档次
        $goldNum = $site = 0;
        foreach (self::PAY_LIST as $gold => $sval) {
            if ($addGold >= $gold) {
                $goldNum = $gold;
                $site = $sval;
            }
        }
        if ($site > 0) {
            $inserted = DB::execute(
                'INSERT IGNORE INTO `zc_sanguo_recharge_list_log` (`uid`, `site`, `money`, `dateline`) VALUES (:uid, :site, :money, :now)',
                ['uid' => $uid, 'site' => $site, 'money' => $addMoney, 'now' => $now]
            );
            if ($inserted > 0) {
                // 记录充值记录成功
                $return['status'] = 1;
                // 查询首充系统是否已经存在首充
                $first = DB::table('first_recharge_log')->where('uid', $uid)->whereNull('deleted_at')->value('id');
                if ($first !== null && $goldNum > 0) {
                    Member::addCurrency($uid, gold: $goldNum);
                    // 首充返利提醒
                    $return['status'] = 2;
                    $return['other_gold'] = $goldNum;
                }
            }
        }
        return $return;
    }

    /**
     * 清空 cd（clear_cd：member_field 的攻击 / 征收 cd）
     *
     * 依赖未移植：强征 cd（qiangdateline / qiangstatus）与征收状态 levystatus，新版 member_field 尚无这些字段。
     */
    private static function clearCd(int $uid): void
    {
        DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->update([
            'levydateline' => 0,
            'attackdateline' => 0,
            'attackstatus' => 0,
        ]);
    }

    /**
     * 增加 VIP 奖励的天下史诗次数（add_epic_times：主公未满 150 级不处理；当天未刷新过时按每日初始次数 10 / 5 加）
     */
    private static function addEpicTimes(int $uid, int $times, int $now): void
    {
        $level = (int)(DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('level') ?? 0);
        if ($level < 150) {
            // 天下史诗功能未开放，不做处理
            return;
        }
        $rows = DB::query(
            'SELECT `refreshtimes`, `locktimes`, `dateline` FROM `zc_sanguo_epic_log` WHERE `uid` = :uid AND `deleted_at` IS NULL FOR UPDATE',
            ['uid' => $uid]
        );
        if ($rows !== []) {
            $result = $rows[0];
            // 判断每日次数是否更新
            if (date('ymd', $now) !== date('ymd', (int)$result['dateline'])) {
                $refreshtimes = $times + 10;
                $locktimes = $times + 5;
            } else {
                $refreshtimes = $times + (int)$result['refreshtimes'];
                $locktimes = $times + (int)$result['locktimes'];
            }
            DB::table('epic_log')->where('uid', $uid)->whereNull('deleted_at')->update([
                'refreshtimes' => $refreshtimes,
                'locktimes' => $locktimes,
                'dateline' => $now,
            ]);
            return;
        }
        // 首次今日添加用户数据
        DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_epic_log` (`uid`, `refreshtimes`, `locktimes`, `dateline`) VALUES (:uid, :r, :l, :now)',
            ['uid' => $uid, 'r' => $times + 10, 'l' => $times + 5, 'now' => $now]
        );
    }
}
