<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\MasterData\General as GeneralTemplate;
use Hanjiang\MasterData\PubOnSale;
use Hanjiang\MasterData\Skill;
use Hanjiang\MasterData\Talent;
use Hanjiang\Model\ActivityClock;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\Chat;
use Hanjiang\Model\General;
use Hanjiang\Model\Member;
use Hanjiang\Model\PubLog;
use Hanjiang\Model\TraderLog;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 限时打折神将（移植旧版 controllers/general_onsale.php + tavern_model 的 get_on_sale / get_price_onsale / pay_onsale）
 *
 * 活动时间与版本取 ActivityTime 的 general_onsale（旧版数据 starttime = endtime = 0，从未开放）。打折武将取 MasterData PubOnSale
 * 中 version 等于活动版本的行（paytype 宝石类型、price 价格）；购买扣宝石、记酒馆日志（获取方式 3）、添加武将，7 星以上发小喇叭。
 * 客户端 godwillpop.js 调用 index、buy（generalid）。当前时间取 BossClock。
 *
 * 与旧版的差别：购买在玩家行锁里执行；已拥有该武将时先回 2 不扣宝石（旧版先扣宝石，酒馆日志发现已拥有才回 2，宝石不退）。
 */
final class GeneralOnsaleHandler
{
    public const string ACTIVITY = 'general_onsale';

    /** general_lang.php general_get */
    private const string LANG_GENERAL_GET = '恭喜玩家【%s】获得%s星武将-%s';

    /**
     * 初始化接口: c=general_onsale&m=index
     *
     * -4 获取活动版本失败；-3 获取打折信息失败；-2 获取宝石信息失败；-1 活动未开启；0 失败；1 成功
     */
    public static function index(Context $ctx): array
    {
        $return = ['status' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $act = ActivityClock::open(self::ACTIVITY, $now);
        if ($act === null || $uid <= 0) {
            $return['status'] = -1;
            return $return;
        }
        $version = (int)$act['version'];
        if ($version < 1) {
            $return['status'] = -4;
            return $return;
        }
        // [酒馆模型]获得翡翠数量
        $jade = TraderLog::get($uid);
        $list = array_values(array_filter(PubOnSale::ROWS, static fn (array $row): bool => $row['version'] === $version));
        if ($list === []) {
            $return['status'] = -3;
            return $return;
        }
        // 获取酒馆招募记录：取得拥有将领id数组
        $ownIdList = array_map('intval', array_column(
            DB::table('pub_log')->select('generalid')->where('uid', $uid)->where('own', 1)->whereNull('deleted_at')->get(),
            'generalid'
        ));
        $gidArr = [];
        foreach ($list as $key => $value) {
            // 标记是否已拥有该将领
            $list[$key]['isown'] = in_array($value['generalid'], $ownIdList, true) ? 1 : 0;
            $gidArr[] = $value['generalid'];
        }
        $generalArr = self::generalInfo($gidArr);
        foreach ($list as $key => $value) {
            if (isset($generalArr[$value['generalid']])) {
                $list[$key]['info'] = $generalArr[$value['generalid']];
            }
        }
        $return['status'] = 1;
        $return['version'] = $version;
        $return['jade'] = [1 => $jade['get1'] ?? 0, 2 => $jade['get2'] ?? 0, 3 => $jade['get3'] ?? 0];
        $return['list'] = $list;
        $return['cd'] = (int)$act['endtime'] - $now;
        return $return;
    }

    /**
     * 购买: c=general_onsale&m=buy（generalid 将领模板 id）
     *
     * -6 获取活动版本失败；-5 添加将领失败；-4 添加日志失败；-3 扣翡翠失败；-2 获取花费失败；-1 活动未开启；0 失败；1 成功；
     * 2 将领已招募；3 宝石不足
     */
    public static function buy(Context $ctx): array
    {
        $return = ['status' => 0, 'price' => 0, 'type' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        $generalid = (int)$ctx->input('generalid', 0);
        $now = BossClock::now();
        $act = ActivityClock::open(self::ACTIVITY, $now);
        if ($act === null || $uid <= 0) {
            $return['status'] = -1;
            return $return;
        }
        $version = (int)$act['version'];
        if ($version < 1) {
            $return['status'] = -6;
            return $return;
        }
        return Member::locked($uid, static function () use ($uid, $generalid, $version, $now, $return): array {
            // [酒馆模型]支付翡翠（pay_onsale）：获得花费
            $cash = PubOnSale::ROWS[$generalid] ?? null;
            if ($cash === null || $cash['version'] !== $version) {
                $return['status'] = -2;
                return $return;
            }
            // 武将已招募（见类注释：先判断，不扣宝石）
            if ((PubLog::byGeneral($uid, $generalid)['own'] ?? 0) === 1) {
                $return['status'] = 2;
                return $return;
            }
            // 支付翡翠
            $result = TraderLog::subJade($uid, $cash['paytype'], $cash['price'], 'general_onsale', 'buy', $now);
            if ($result['status'] !== 1) {
                $return['status'] = $result['status'] === -1 ? 3 : -3;
                return $return;
            }
            // 添加酒馆日志(武将id, 拥有状态, 获取方式)
            $add = PubLog::add($uid, $generalid, 1, 3, $now);
            if ($add !== 1) {
                $return['status'] = $add === 2 ? 2 : -4;
                return $return;
            }
            // [将领模型]添加将领(generalid)
            $info = General::addGeneral($uid, $generalid);
            if ($info === null) {
                $return['status'] = -5;
                return $return;
            }
            unset($info['status']);
            $star = Chat::starWord((int)$info['star']);
            if ($star !== null) {
                $sender = Chat::sender($uid);
                Chat::sendToHorn(sprintf(self::LANG_GENERAL_GET, (string)($sender['nickname'] ?? ''), $star, $info['name']), $sender);
            }
            Redis::del('player:' . $uid);
            $return['price'] = $cash['price'];
            $return['type'] = $cash['paytype'];
            $return['jade'] = $result['jade'] ?? $result['number'];
            $return['general'] = $info;
            $return['status'] = 1;
            return $return;
        });
    }

    /**
     * 将领模板展示信息（general_only / general_onsale 的 index：get_info_by_id + get_ext_by_id 技能 / 天赋的 id、name、description，
     * 输出 image / name / type / wuli / zhili / tili / star / skill / talent）：generalid => 信息
     *
     * @param list<int> $gidArr
     * @return array<int, array<string, mixed>>
     */
    public static function generalInfo(array $gidArr): array
    {
        $generalArr = [];
        foreach ($gidArr as $gid) {
            $general = GeneralTemplate::ROWS[$gid] ?? null;
            if ($general === null) {
                continue;
            }
            $skill = Skill::ROWS[$general['skillid']] ?? null;
            $talent = Talent::ROWS[$general['talentid']] ?? null;
            $general['skill'] = [];
            $general['talent'] = [];
            foreach (['id', 'name', 'description'] as $v) {
                $general['skill'][$v] = $skill[$v] ?? '';
                $general['talent'][$v] = $talent[$v] ?? '';
            }
            foreach (['image', 'name', 'type', 'wuli', 'zhili', 'tili', 'star', 'skill', 'talent'] as $v) {
                $generalArr[$gid][$v] = $general[$v];
            }
        }
        return $generalArr;
    }
}
