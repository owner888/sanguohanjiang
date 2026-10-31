<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Cross\UcModel;
use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\MasterData\General as GeneralTemplate;
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
 * 限量神将（移植旧版 s1 controllers/limitedshop.php；商品与全服限量由 uc controllers/limitedshop.php 管理，单服部署下在本进程内：
 * Cross\UcModel::limitedItemList / limitedItem / limitedBuy）
 *
 * 活动时间取 ActivityTime 的 limitedshop（旧版数据 starttime = endtime = 0，从未开放）。商品表 uc_limitedshop（旧版 uc 的
 * limitedshop_model 与表不在旧版代码包和数据里，按控制器与客户端字段还原，没有商品数据：开放活动前需由运营录入，否则 index 回 -2）。
 * 每人每件商品买一次；扣宝石（type 类型、now 现价）与声望（now_reputation），酒馆日志获取方式 7、添加武将、7 星以上发小喇叭、
 * 记活动人次 tavern_action_log。客户端 limitedgodpop.js 调用 index、buy_general（id 商品 id）。当前时间取 BossClock。
 *
 * 照旧版：uc 售出后扣宝石失败不退回限量份数。与旧版的差别：购买在玩家行锁里执行（宝石 / 声望用条件更新扣减）。
 */
final class LimitedshopHandler
{
    public const string ACTIVITY = 'limitedshop';

    /** general_lang.php general_get */
    private const string LANG_GENERAL_GET = '恭喜玩家【%s】获得%s星武将-%s';

    /**
     * 首页: c=limitedshop&m=index
     *
     * -4 获取宝石信息失败；-3 获取将领信息失败；-2 获取商品信息失败；-1 活动未开启；0 失败；1 成功
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
        $item = UcModel::limitedItemList();
        if ($item['status'] !== 1 || empty($item['list'])) {
            $return['status'] = -2;
            return $return;
        }
        $gidArr = array_column($item['list'], 'generalid');
        $generalArr = [];
        foreach (array_unique($gidArr) as $gid) {
            $general = GeneralTemplate::ROWS[$gid] ?? null;
            if ($general === null) {
                continue;
            }
            $skill = Skill::ROWS[$general['skillid']] ?? [];
            $talent = Talent::ROWS[$general['talentid']] ?? [];
            $general['skill'] = ['name' => $skill['name'] ?? null, 'description' => $skill['description'] ?? null];
            $general['talent'] = ['name' => $talent['name'] ?? null, 'description' => $talent['description'] ?? null];
            foreach (['type', 'name', 'image', 'star', 'wuli', 'zhili', 'tili', 'skill', 'talent'] as $v) {
                $generalArr[$gid][$v] = $general[$v];
            }
        }
        // 获取将领信息失败（get_info_by_id 按 id 去重，与商品个数不同时旧版也回 -3）
        if (count($generalArr) !== count($gidArr)) {
            $return['status'] = -3;
            return $return;
        }
        $list = [];
        foreach ($item['list'] as $key => $value) {
            $value['isown'] = General::ownsTemplate($uid, $value['generalid']) ? 1 : 0;
            $value['num'] -= $value['sale'];
            $value['info'] = $generalArr[$value['generalid']];
            $list[$key] = $value;
        }
        // 获取剩余宝石信息
        $jade = TraderLog::get($uid);
        $return['cd'] = (int)$act['endtime'] - $now;
        $return['status'] = 1;
        $return['list'] = $list;
        $return['jade'] = [1 => $jade['get1'] ?? 0, 2 => $jade['get2'] ?? 0, 3 => $jade['get3'] ?? 0];
        $return['reputation'] = self::reputation($uid);
        return $return;
    }

    /**
     * 购买: c=limitedshop&m=buy_general（id 商品 id）
     *
     * -6 添加将领失败；-5 添加招募记录失败；-4 扣宝石失败；-3 购买失败；-2 获取商品信息失败；-1 活动未开启；0 失败；1 成功；
     * 2 已拥有该武将；3 宝石不足；4 已售完；5 声望不足
     */
    public static function buy_general(Context $ctx): array
    {
        $return = ['status' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $act = ActivityClock::open(self::ACTIVITY, $now);
        if ($act === null || $uid <= 0) {
            $return['status'] = -1;
            return $return;
        }
        $cd = (int)$act['endtime'] - $now;
        $id = (int)$ctx->input('id', 0);
        $item = UcModel::limitedItem($id);
        if ($item['status'] !== 1) {
            $return['status'] = -2;
            return $return;
        }
        $generalid = $item['info']['generalid'];
        $type = $item['info']['type'];
        $price = $item['info']['now'];
        $reputation = $item['info']['now_reputation'];
        return Member::locked($uid, static function () use ($uid, $id, $generalid, $type, $price, $reputation, $cd, $now, $return): array {
            // 是否拥有该武将
            if (General::ownsTemplate($uid, $generalid)) {
                $return['status'] = 2;
                return $return;
            }
            // 宝石是否足够
            if (TraderLog::jade($uid, $type) < $price) {
                $return['status'] = 3;
                return $return;
            }
            if (self::reputation($uid) < $reputation) {
                // 声望不足
                $return['status'] = 5;
                return $return;
            }
            $result = UcModel::limitedBuy($id, $uid);
            if ($result['status'] !== 1) {
                // 已售完 / 购买失败
                $return['status'] = $result['status'] === 2 ? 4 : -3;
                return $return;
            }
            // 购买武将
            $sub = TraderLog::subJade($uid, $type, $price, 'limitedshop', 'buy_general', $now);
            if ($sub['status'] !== 1) {
                $return['status'] = $sub['status'] === -1 ? 3 : -4;
                return $return;
            }
            // 扣除声望
            Member::subReputation($uid, $reputation);
            if (PubLog::add($uid, $generalid, 1, 7, $now) !== 1) {
                // 添加招募记录失败
                $return['status'] = -5;
                return $return;
            }
            // [将领模型]添加将领(generalid)
            $info = General::addGeneral($uid, $generalid);
            if ($info === null) {
                $return['status'] = -6;
                return $return;
            }
            $star = Chat::starWord((int)$info['star']);
            $sender = Chat::sender($uid);
            if ($star !== null) {
                Chat::sendToHorn(sprintf(self::LANG_GENERAL_GET, (string)($sender['nickname'] ?? ''), $star, $info['name']), $sender);
            }
            unset($info['status']);
            // 记录活动人次
            DB::table('tavern_action_log')->insert([
                'uid' => $uid,
                'name' => Member::logText((string)$info['name'], 10),
                'level' => (int)(DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('level') ?? 0),
                'dateline' => $now,
            ]);
            Redis::del('player:' . $uid);
            $return['status'] = 1;
            $return['cd'] = $cd;
            $return['jade'] = [$type => $sub['jade'] ?? $sub['number']];
            $return['reputation'] = self::reputation($uid);
            $return['num'] = $result['num'];
            $return['general'] = $info;
            return $return;
        });
    }

    private static function reputation(int $uid): int
    {
        return (int)(DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('reputation') ?? 0);
    }
}
