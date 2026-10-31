<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\MasterData\MountRatio;
use Hanjiang\MasterData\MountTemp;
use Hanjiang\Model\Chat;
use Hanjiang\Model\Member;
use Hanjiang\Model\OwnMount;
use Hanjiang\Model\TraderLog;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 西域马场（移植旧版 controllers/studfarm.php + models/mount_model.php 的马场部分）
 *
 * 客户端（public/static/js/view/studfarmpop.js）调用：index / action(id, new = 1)。主公 120 级开启（未开启返回 status 2）。
 *
 * 三档马场取 MasterData MountRatio：sub_type 1 花黄宝石（trader_log.get2）、2 花紫宝石（get3），价格 price，
 * rate1 ~ rate7 为各品级权重。每档第一次选马必得该档最高品级（十里挑一 4 金、百里挑一 5 紫、万里挑一 6 红），
 * 记在 mount_log.first{id}；之后按权重抽品级。坐骑在该品级的 MountTemp 模板里等概率抽（id >= 100 的抽不到）。
 *
 * 选中 5 品以上坐骑时发聊天室系统消息（get_message_str 28）与小喇叭（chat_mount_get）。
 * 与旧版的差别：选马在 Member::locked（玩家行锁）里执行；数值字段输出为整数。
 */
final class StudfarmHandler
{
    /** 开启等级（旧版 $_level） */
    public const int OPEN_LEVEL = 120;
    /** 每档马场首次选马必得的品级 */
    public const array FIRST_QUALITY = [1 => 4, 2 => 5, 3 => 6];

    /**
     * 初始界面: c=studfarm&m=index
     *
     * 状态码：2 功能未开启；1 成功。list 为 MountRatio 三档（权重大于 0 的显示为 1），jade1 ~ jade3 为蓝 / 黄 / 紫宝石。
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $return = ['status' => 0];

        // 判断功能是否开启
        if (!self::isOpen($uid)) {
            $return['status'] = 2;
            return $return;
        }

        // 查询马场列表
        $list = [];
        foreach (MountRatio::ROWS as $val) {
            for ($i = 1; $i <= 7; $i++) {
                if ($val['rate' . $i] > 0) {
                    $val['rate' . $i] = 1;
                }
            }
            $list[] = $val;
        }

        // 获得用户剩余宝石
        $jade = TraderLog::get($uid);

        $return['status'] = 1;
        $return['list'] = $list;
        $return['jade1'] = $jade['get1'] ?? 0;
        $return['jade2'] = $jade['get2'] ?? 0;
        $return['jade3'] = $jade['get3'] ?? 0;
        return $return;
    }

    /**
     * 选马: c=studfarm&m=action&id=马场id&new=1
     *
     * 状态码：2 功能未开启；-1 马场不存在；-3 new 不为 1，或首次选马记录写入失败（此时宝石已扣）；
     * -2 宝石不足或消耗类型不对；-7 该品级没有坐骑模板；-4 坐骑模板不存在；-5 坐骑等级表缺 0 级；-6 添加失败；1 成功。
     * first 为 1 表示本次是该档首次选马；info 为新坐骑数据。
     */
    public static function action(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $id = (int)$ctx->input('id', 0);
        $new = (int)$ctx->input('new', 0);

        return Member::locked($uid, static function () use ($uid, $id, $new): array {
            $return = ['status' => 0];
            $now = time();

            // 判断功能是否开启
            if (!self::isOpen($uid)) {
                $return['status'] = 2;
                return $return;
            }

            // 获取该类型的价格
            $info = MountRatio::ROWS[$id] ?? null;
            if ($info === null) {
                $return['status'] = -1;
                return $return;
            }

            if ($new !== 1) {
                $return['status'] = -3;
                return $return;
            }

            // 判断资源是否足够并扣除（1 黄宝石、2 紫宝石）
            $jadeType = match ($info['sub_type']) {
                1 => 2,
                2 => 3,
                default => 0,
            };
            if ($jadeType === 0 || TraderLog::subJade($uid, $jadeType, $info['price'], 'studfarm', 'action', $now)['status'] !== 1) {
                $return['status'] = -2;
                return $return;
            }

            // 获得首次选马记录
            $status = self::firstStatus($uid, $id);
            $first = $status ? 0 : 1;

            // 获得本次能获得那个品级的坐骑
            $quality = 0;
            if ($status) {
                // 走正常概率
                $allRate = $nowRate = 0;
                for ($i = 1; $i <= 7; $i++) {
                    if ($info['rate' . $i] > 0) {
                        $allRate += $info['rate' . $i];
                    }
                }
                $rands = mt_rand(1, $allRate);
                for ($i = 1; $i <= 7; $i++) {
                    if ($info['rate' . $i] > 0) {
                        $nowRate += $info['rate' . $i];
                        if ($rands <= $nowRate) {
                            $quality = $i;
                            break;
                        }
                    }
                }
            } else {
                // 获得最高品级的坐骑
                $quality = self::FIRST_QUALITY[$id] ?? 0;

                // 添加首次选马记录
                $affected = DB::table('mount_log')->where('uid', $uid)->whereNull('deleted_at')->update(['first' . $id => $now]);
                if ($affected <= 0) {
                    $return['status'] = -3;
                    return $return;
                }
            }

            // 按品级获得坐骑列表
            $list = array_values(array_filter(MountTemp::ROWS, static fn (array $v): bool => $v['quality'] === $quality));
            if ($list === []) {
                $return['status'] = -7;
                return $return;
            }

            // 坐骑id大于100的无法在马场中抽中（旧版 unset 后不重排下标，再按 mt_rand(1, 剩余个数) - 1 取下标）
            foreach ($list as $key => $val) {
                if ($val['id'] >= 100) {
                    unset($list[$key]);
                }
            }

            $rands = mt_rand(1, count($list));
            $result = OwnMount::addMount($uid, (int)($list[$rands - 1]['id'] ?? 0), 0, 1, 'studfarm', 'action', $now);
            if ($result['status'] === -1) {
                // 获取坐骑模版信息失败
                $return['status'] = -4;
                return $return;
            } elseif ($result['status'] === -2) {
                // 获取坐骑升级信息失败
                $return['status'] = -5;
                return $return;
            } elseif ($result['status'] !== 1) {
                // 添加失败
                $return['status'] = -6;
                return $return;
            }

            // 如果获得的坐骑品级大于5  发送广播到聊天室和小喇叭
            if ($quality >= 5) {
                $sender = Chat::sender($uid);
                $message = Chat::getMessageStr(28, ['rebate' => $result['info']['name'], 'mount' => $quality], $sender);
                Chat::sendToAll($message, Chat::TYPE_SYSTEM, 0, 0, $sender);
                $qualityStr = Chat::quality($quality, (string)$result['info']['name']);
                Chat::sendToHorn(sprintf(Chat::LANG['chat_mount_get'], $sender['nickname'] ?? '', $qualityStr), $sender);
            }

            $jade = TraderLog::get($uid);
            // 成功返回
            $return['status'] = 1;
            $return['first'] = $first;
            $return['info'] = $result['info'];
            $return['jade1'] = $jade['get1'] ?? 0;
            $return['jade2'] = $jade['get2'] ?? 0;
            $return['jade3'] = $jade['get3'] ?? 0;
            $return['all_gold'] = (int)(DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('gold') ?? 0);
            return $return;
        });
    }

    /**
     * 该档是否已选过马（移植 get_first_status：没有 mount_log 记录时插入一行全 0 并返回 0）
     */
    private static function firstStatus(int $uid, int $id): int
    {
        $row = DB::table('mount_log')->where('uid', $uid)->whereNull('deleted_at')->first();
        if ($row === null) {
            DB::table('mount_log')->insert(['uid' => $uid, 'first1' => 0, 'first2' => 0, 'first3' => 0]);
            return 0;
        }
        return (int)($row['first' . $id] ?? 0) > 0 ? 1 : 0;
    }

    /**
     * 功能是否开启（主公等级不低于 120）
     */
    private static function isOpen(int $uid): bool
    {
        return (int)(DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('level') ?? 0) >= self::OPEN_LEVEL;
    }
}
