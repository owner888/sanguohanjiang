<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 摸金校尉古墓寻宝、罗盘分金升星与藏宝图组队探秘处理器 (DDD Activity Domain)
 */
final class TreasuremapHandler
{
    public const array MAP_LEVELS = [
        1 => ['star' => 1, 'name' => '残破羊皮古卷', 'cost_refresh' => 3000, 'sale_price' => 12000, 'levelup_price' => 5, 'reward_silver' => 8000, 'reward_gold' => 0],
        2 => ['star' => 2, 'name' => '西域古墓星象图', 'cost_refresh' => 5000, 'sale_price' => 30000, 'levelup_price' => 10, 'reward_silver' => 15000, 'reward_gold' => 10],
        3 => ['star' => 3, 'name' => '南蛮巫王宝藏密卷', 'cost_refresh' => 8000, 'sale_price' => 80000, 'levelup_price' => 20, 'reward_silver' => 30000, 'reward_gold' => 20],
        4 => ['star' => 4, 'name' => '始皇地宫九龙分金图', 'cost_refresh' => 15000, 'sale_price' => 200000, 'levelup_price' => 50, 'reward_silver' => 60000, 'reward_gold' => 50],
        5 => ['star' => 5, 'name' => '传国玉玺龙脉藏宝图', 'cost_refresh' => 30000, 'sale_price' => 500000, 'levelup_price' => 0, 'reward_silver' => 120000, 'reward_gold' => 100],
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
     * 获取玩家藏宝图当前碎片部位 (1: 上, 2: 中, 3: 下)
     */
    public static function getUserMapPart(int $uid): int
    {
        $cached = Redis::get('user_treasure_part:' . $uid);
        return ($cached !== null && is_numeric($cached)) ? max(1, min(3, (int)$cached)) : 1;
    }

    /**
     * 藏宝图主界面: c=treasuremap&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        $star = self::getUserMap($uid);
        $part = self::getUserMapPart($uid);
        $cfg = self::MAP_LEVELS[$star] ?? self::MAP_LEVELS[1];

        // 查询玩家当前组队状态 (来自 Redis 缓存)
        $teamData = Redis::getJson("treasure_team:{$uid}");
        $teamStatus = (!empty($teamData)) ? 1 : 0;

        // 全服寻宝公开队伍列表
        $allTeams = Redis::getJson('treasure_public_teams') ?: [];
        $teamList = array_values($allTeams);

        // 玩家当前持有的藏宝图碎片对象 (补齐 levelup_price, sale_price, ismax)
        $myMap = [
            'id' => 1,
            'name' => $cfg['name'],
            'type' => $part,
            'quality' => $star,
            'sale_price' => (int)$cfg['sale_price'],
            'levelup_price' => ($star >= 5) ? 0 : (int)$cfg['levelup_price'],
            'ismax' => ($star >= 5) ? 1 : 0,
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
        $part = self::getUserMapPart($uid);
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
                    'type' => $part,
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
            'list' => array_values($publicTeams),
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
            'list' => array_values($publicTeams),
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

        $publicTeams = Redis::getJson('treasure_public_teams') ?: [];
        if (!empty($team)) {
            $tid = (int)($team['id'] ?? $uid);
            unset($publicTeams[$tid]);
            Redis::setJson('treasure_public_teams', $publicTeams, 86400);
        }

        Redis::del("treasure_team:{$uid}");

        return [
            'status' => 1,
            'list' => array_values($publicTeams),
            'message' => '已成功退出/解散队伍',
        ];
    }

    /**
     * 升级藏宝图品级: c=treasuremap&m=levelup
     */
    public static function levelup(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $curStar = self::getUserMap($uid);
        $curPart = self::getUserMapPart($uid);

        if ($curStar >= 5) {
            return ['status' => 2, 'message' => '藏宝图已达最高品质！'];
        }

        $cfg = self::MAP_LEVELS[$curStar] ?? self::MAP_LEVELS[1];
        $costGold = (int)$cfg['levelup_price'];

        if (!MemberHandler::deductCurrency($uid, gold: $costGold)) {
            return ['status' => 3, 'message' => "元宝不足，升级品级需要 {$costGold} 元宝"];
        }

        $newStar = min(5, $curStar + 1);
        Redis::set('user_treasure_map:' . $uid, (string)$newStar, 86400 * 7);

        $newCfg = self::MAP_LEVELS[$newStar];
        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();

        $mapObj = [
            'id' => 1,
            'name' => $newCfg['name'],
            'type' => $curPart,
            'quality' => $newStar,
            'sale_price' => (int)$newCfg['sale_price'],
            'levelup_price' => ($newStar >= 5) ? 0 : (int)$newCfg['levelup_price'],
            'ismax' => ($newStar >= 5) ? 1 : 0,
        ];

        return [
            'status' => 1,
            'map' => $mapObj,
            'gold' => (int)($member['gold'] ?? 0),
            'star' => $newStar,
            'message' => "升级成功！藏宝图升至 {$newStar} 阶【{$newCfg['name']}】！",
        ];
    }

    /**
     * 寻龙分金刷新宝图部位: c=treasuremap&m=refresh
     */
    public static function refresh(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $curStar = self::getUserMap($uid);
        $curPart = self::getUserMapPart($uid);

        $costGold = 2; // 老客户端刷新价格固定为 2 元宝
        if (!MemberHandler::deductCurrency($uid, gold: $costGold)) {
            return ['status' => 2, 'message' => "元宝不足，刷新部件需要 {$costGold} 元宝"];
        }

        // 随机切换至其他部件 (1 上, 2 中, 3 下)
        $candidates = array_values(array_diff([1, 2, 3], [$curPart]));
        $newPart = $candidates[array_rand($candidates)];
        Redis::set('user_treasure_part:' . $uid, (string)$newPart, 86400 * 7);

        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        $cfg = self::MAP_LEVELS[$curStar] ?? self::MAP_LEVELS[1];

        return [
            'status' => 1,
            'type' => $newPart,
            'gold' => (int)($member['gold'] ?? 0),
            'star' => $curStar,
            'map_name' => $cfg['name'],
            'upgraded' => 1,
            'message' => '部件刷新成功！',
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

        $salePrice = (int)$cfg['sale_price'];

        // 增加卖出收益银两
        MemberHandler::addCurrency($uid, silver: $salePrice);

        // 重置回 1 星
        Redis::set('user_treasure_map:' . $uid, '1', 86400 * 7);
        Redis::del('player:' . $uid);

        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();

        return [
            'status' => 1,
            'map' => [], // 卖出后变为空数组 []，让前端正确展示无图状态
            'num' => 0,
            'add_silver' => $salePrice,
            'silver' => (int)($member['silver'] ?? 0),
            'gold' => (int)($member['gold'] ?? 0),
            'reward_silver' => $cfg['reward_silver'],
            'reward_gold' => $cfg['reward_gold'],
            'message' => "成功卖出藏宝图，获得 {$salePrice} 银两！",
        ];
    }
}
