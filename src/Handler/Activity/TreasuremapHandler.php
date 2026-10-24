<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use OmniPHP\WebSocket\Broadcaster;

/**
 * 摸金校尉古墓寻宝、罗盘分金升星与藏宝图组队探秘处理器 (DDD Activity Domain)
 */
final class TreasuremapHandler
{
    public const array MAP_LEVELS = [
        1 => ['star' => 1, 'name' => '残破羊皮古卷', 'cost_refresh' => 3000, 'reward_silver' => 8000, 'reward_gold' => 0],
        2 => ['star' => 2, 'name' => '西域古墓星象图', 'cost_refresh' => 5000, 'reward_silver' => 15000, 'reward_gold' => 10],
        3 => ['star' => 3, 'name' => '南蛮巫王宝藏密卷', 'cost_refresh' => 8000, 'reward_silver' => 30000, 'reward_gold' => 20],
        4 => ['star' => 4, 'name' => '始皇地宫九龙分金图', 'cost_refresh' => 15000, 'reward_silver' => 60000, 'reward_gold' => 50],
        5 => ['star' => 5, 'name' => '传国玉玺龙脉藏宝图', 'cost_refresh' => 30000, 'reward_silver' => 120000, 'reward_gold' => 100],
    ];

    /**
     * 获取玩家藏宝图当前星级
     */
    public static function getUserMap(int $uid): int
    {
        $cached = Redis::get('user_treasure_map:' . $uid);
        return ($cached !== null && is_numeric($cached)) ? max(1, min(5, (int)$cached)) : 1;
    }

    /**
     * 藏宝图主界面: c=treasuremap&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        $star = self::getUserMap($uid);
        $cfg = self::MAP_LEVELS[$star] ?? self::MAP_LEVELS[1];

        // 查询玩家当前组队状态 (来自 Redis 缓存)
        $teamData = Redis::getJson("treasure_team:{$uid}");
        $teamStatus = (!empty($teamData)) ? 1 : 0;

        // 全服寻宝公开队伍列表
        $allTeams = Redis::getJson('treasure_public_teams') ?: [];
        $teamList = array_values($allTeams);

        // 玩家当前持有的藏宝图碎片对象
        $myMap = [
            'id' => 1,
            'name' => $cfg['name'],
            'type' => 1, // 1: 上部, 2: 中部, 3: 下部
            'quality' => $star,
        ];

        return [
            'status' => 1,
            'team_status' => $teamStatus,
            'list' => $teamList,
            'map' => $myMap,
            'team' => $teamData ?: [],
            'refresh_price' => 2,
            'num' => 1,
            'add_silver' => 0,
            'silver' => (int)($member['silver'] ?? 0),
            // 向下兼容保留原字段
            'star' => $star,
            'map_name' => $cfg['name'],
            'reward_silver' => $cfg['reward_silver'],
            'reward_gold' => $cfg['reward_gold'],
            'refresh_cost' => $cfg['cost_refresh'],
            'message' => 'ok',
        ];
    }

    /**
     * 创建寻宝队伍: c=treasuremap&m=create_team
     */
    public static function create_team(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        $star = self::getUserMap($uid);
        $cfg = self::MAP_LEVELS[$star] ?? self::MAP_LEVELS[1];
        $nickname = (string)($member['nickname'] ?? ('主公' . $uid));

        $teamId = $uid;
        $team = [
            'id' => $teamId,
            'quality' => $star,
            'list' => [
                [
                    'uid' => $uid,
                    'nickname' => $nickname,
                    'name' => $cfg['name'],
                    'type' => 1,
                ],
            ],
        ];

        Redis::setJson("treasure_team:{$uid}", $team, 86400);

        // 登记到公开队伍池
        $publicTeams = Redis::getJson('treasure_public_teams') ?: [];
        $publicTeams[$teamId] = $team;
        Redis::setJson('treasure_public_teams', $publicTeams, 86400);

        return [
            'status' => 1,
            'team' => $team,
            'message' => '摸金队伍创建成功！',
        ];
    }

    /**
     * 加入寻宝队伍: c=treasuremap&m=join_team
     */
    public static function join_team(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $tid = (int)($ctx->input('tid', $ctx->input('id', 0)));

        if ($tid <= 0) {
            return ['status' => 0, 'message' => '目标队伍不存在'];
        }

        $publicTeams = Redis::getJson('treasure_public_teams') ?: [];
        if (!isset($publicTeams[$tid])) {
            return ['status' => -1, 'message' => '该队伍已出发或已解散'];
        }

        $team = $publicTeams[$tid];
        if (count($team['list']) >= 3) {
            return ['status' => 0, 'message' => '该队伍人数已满员'];
        }

        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        $nickname = (string)($member['nickname'] ?? ('主公' . $uid));
        $star = self::getUserMap($uid);
        $cfg = self::MAP_LEVELS[$star] ?? self::MAP_LEVELS[1];
        $partType = count($team['list']) + 1;

        $team['list'][] = [
            'uid' => $uid,
            'nickname' => $nickname,
            'name' => $cfg['name'],
            'type' => $partType,
        ];

        $publicTeams[$tid] = $team;
        Redis::setJson('treasure_public_teams', $publicTeams, 86400);
        Redis::setJson("treasure_team:{$uid}", $team, 86400);

        return [
            'status' => 1,
            'team' => $team,
            'message' => '成功加入摸金队伍！',
        ];
    }

    /**
     * 解散或退出寻宝队伍: c=treasuremap&m=exit_team
     */
    public static function exit_team(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $team = Redis::getJson("treasure_team:{$uid}");

        if (!empty($team)) {
            $tid = (int)($team['id'] ?? $uid);
            $publicTeams = Redis::getJson('treasure_public_teams') ?: [];
            unset($publicTeams[$tid]);
            Redis::setJson('treasure_public_teams', $publicTeams, 86400);
        }

        Redis::del("treasure_team:{$uid}");

        return [
            'status' => 1,
            'message' => '已成功退出/解散队伍',
        ];
    }

    /**
     * 升级藏宝图: c=treasuremap&m=levelup
     */
    public static function levelup(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $curStar = self::getUserMap($uid);

        if ($curStar >= 5) {
            return ['status' => 0, 'message' => '藏宝图已达最高 5 星！'];
        }

        $costGold = 10;
        if (!MemberHandler::deductCurrency($uid, gold: $costGold)) {
            return ['status' => -1, 'message' => '元宝不足，升级需要 10 元宝'];
        }

        $newStar = $curStar + 1;
        Redis::set('user_treasure_map:' . $uid, (string)$newStar, 86400 * 7);

        return [
            'status' => 1,
            'star' => $newStar,
            'message' => "升级成功！藏宝图升至 {$newStar} 星！",
        ];
    }

    /**
     * 寻龙分金刷新宝图星级: c=treasuremap&m=refresh
     */
    public static function refresh(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $curStar = self::getUserMap($uid);

        if ($curStar >= 5) {
            return ['status' => 0, 'message' => '藏宝图已达至高 5 星绝世龙脉！'];
        }

        $cfg = self::MAP_LEVELS[$curStar];
        $costSilver = (int)$cfg['cost_refresh'];

        if (!MemberHandler::deductCurrency($uid, silver: $costSilver)) {
            return ['status' => -1, 'message' => "银两不足，勘探寻宝需要 {$costSilver} 银两"];
        }

        // 成功升级星级 (70% 概率升星，30% 保留)
        $up = (mt_rand(1, 100) <= 70);
        $newStar = $up ? $curStar + 1 : $curStar;

        Redis::set('user_treasure_map:' . $uid, (string)$newStar, 86400 * 7);
        $newCfg = self::MAP_LEVELS[$newStar];

        if ($newStar >= 5) {
            $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
            $pName = $member['nickname'] ?? ('主公' . $uid);
            Broadcaster::broadcast('marquee', [
                'content' => "龙脉出世！主公【{$pName}】寻龙分金，成功探出 5 星绝世宝图【{$newCfg['name']}】！",
                'type' => 'system_broadcast',
            ]);
        }

        return [
            'status' => 1,
            'star' => $newStar,
            'map_name' => $newCfg['name'],
            'upgraded' => $up ? 1 : 0,
            'message' => $up ? "分金定穴大成！藏宝图升至 {$newStar} 星【{$newCfg['name']}】！" : '勘探暂未精进，宝图星级保持不变',
        ];
    }

    /**
     * 发掘挖宝 / 出售宝图: c=treasuremap&m=sale
     */
    public static function sale(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $star = self::getUserMap($uid);
        $cfg = self::MAP_LEVELS[$star] ?? self::MAP_LEVELS[1];

        $rewardSilver = (int)$cfg['reward_silver'];
        $rewardGold = (int)$cfg['reward_gold'];

        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        $newSilver = (int)($member['silver'] ?? 0) + $rewardSilver;
        $newGold = (int)($member['gold'] ?? 0) + $rewardGold;

        DB::table('member')->where('uid', $uid)->update([
            'silver' => $newSilver,
            'gold' => $newGold,
        ]);

        // 重置回 1 星
        Redis::set('user_treasure_map:' . $uid, '1', 86400 * 7);
        Redis::del('player:' . $uid);

        return [
            'status' => 1,
            'silver' => $rewardSilver,
            'gold' => $rewardGold,
            'all_silver' => $newSilver,
            'all_gold' => $newGold,
            'message' => "古宝出土！发掘收获银两 {$rewardSilver}" . ($rewardGold > 0 ? "、元宝 {$rewardGold}" : '') . "！",
        ];
    }
}
