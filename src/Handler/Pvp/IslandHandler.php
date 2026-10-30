<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Pvp;

use Hanjiang\Battle\BattleEngine;
use Hanjiang\Battle\TeamBuilder;
use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\MasterData\Island;
use Hanjiang\Model\Member;
use Hanjiang\Model\MemberActivity;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 金银洞 / 金银岛（移植旧版 controllers/island.php + models/island_model.php，路径相对旧版 s1/application/）
 *
 * 配置：关卡 Island（主公等级取整到 10 级为一组，30~190 级每组 5 关；position1~9 → 怪物 IslandMonster，
 * battle_model->battle_island 即 battle_mission type 1；silver 必得、gold 按 probability% 掉落）。
 * 玩家存储：member_activity（islandid 已开启的关卡、islandtimes 今日挑战次数，旧版 daily_cron 归零；islanddate 进度更新时间，
 * 跨天进入 index 时回到本等级组第一关）、island_log（首胜记录，即攻略列表）、island_report（首胜战报）。
 * 每次挑战消耗军令「今日挑战次数 + 1」，胜利才累加今日挑战次数。
 *
 * 客户端调用（treasureworld.js / miscpop.js）：index、get_mission、pk、get_report_list、get_report_info、send_to_chat。
 * 旧版公开方法即这 6 个。
 *
 * 依赖未移植：
 * - 聊天室战报广播 chat_model->get_message_str(8, …) / send_message_to_all（send_to_chat 暂返回 0，不扣元宝）；
 * - 日常任务 task_model->task_action(9)（主公 41 级以上挑战金银洞）；
 * - member_model 的流水与附带计数：sub_act 的 act_log、翻牌活动 flopact；add_gold / add_silver 的流水。
 * 战斗 CD 照旧版读 member_field.attackstatus / attackdateline（cd_model->get_attack）；旧版 vip_model->cd_status() 恒为 1，
 * 战后不写 CD、info.cd 恒为 0。
 */
final class IslandHandler
{
    /** 开放等级：主公等级不足 36 返回 -1 */
    private const int OPEN_LEVEL = 36;

    /** 旧版 cd_model->get_attack：状态 0（未锁）时 CD 超过 1800 秒不能战斗 */
    private const int MAX_CD = 1800;

    /** 旧版 zc_sanguo_member_activity 中本玩法读写的字段 */
    private const array ACTIVITY_COLUMNS = ['uid', 'islandid', 'islandtimes', 'islanddate'];

    /** 旧版 zc_sanguo_island_log 的字段 */
    private const array LOG_COLUMNS = ['id', 'islandid', 'uid', 'nickname', 'level', 'dateline', 'tree'];

    /**
     * 初始化: c=island&m=index
     */
    public static function index(Context $ctx): array
    {
        /**
         * 返回值
         * -3 获取任务列表失败
         * -2 开启副本失败
         * -1 等级不足30
         *  0 失败
         *  1 成功
         */
        $return = ['status' => 0, 'list' => []];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        // 玩家等级
        $level = (int)$member['level'];
        $uid = (int)$member['uid'];
        $dateline = time();
        if ($level < self::OPEN_LEVEL) {
            // 等级不足36 返回-1
            $return['status'] = -1;
            return $return;
        }
        // [用户模型]获取玩家活动信息
        $field = self::getActivityInfo($uid);
        if ($field === null) {
            // 旧版不判断空（get_activity_info 没有记录时建档），建档失败时 islandid 取不到按 0 处理后更新失败返回 -2
            $return['status'] = -2;
            return $return;
        }

        if ($field['islandid'] > 0) {
            // 已开启的任务id
            $islandid = $field['islandid'];
        } else {
            // 开启金银洞副本
            $islandid = self::openMission($level);
            if (self::updateActivityInfo($uid, ['islandid' => $islandid, 'islanddate' => $dateline]) !== 1) {
                // 开启副本失败
                $return['status'] = -2;
                return $return;
            }
        }

        // [island模型]获取island任务信息(id)
        $info = Island::ROWS[$islandid] ?? null;
        // [金银岛模型]获取玩家等级对应的副本等级
        $missionLevel = self::getMissionLevel($level);
        // 当前怪物等级 < 副本等级：开启本等级组第一关
        if ($info !== null && $info['level'] < $missionLevel) {
            $islandid = self::openMission($level);
            self::updateActivityInfo($uid, ['islandid' => $islandid, 'islanddate' => $dateline]);
        }

        // 跨天：回到本等级组第一关
        if ((int)date('Ymd', $field['islanddate']) < (int)date('Ymd', $dateline)) {
            $islandid = self::openMission($level);
            self::updateActivityInfo($uid, ['islandid' => $islandid, 'islanddate' => $dateline]);
        }

        // [island模型]获取island任务列表(等级)
        $island = self::getMissionList($missionLevel);
        if ($island === []) {
            // 获取任务列表失败返回 -3
            $return['status'] = -3;
            return $return;
        }

        // 根据islandid取当前已击杀怪物,kill:1已击杀;0未击杀
        $list = [];
        foreach ($island as $key => $value) {
            $list[$key] = [
                'id' => $value['id'],
                'name' => $value['name'],
                'x' => $value['x'],
                'y' => $value['y'],
                'background' => $value['background'],
                'killed' => $value['id'] < $islandid ? 1 : 0,
                'openstatus' => $value['id'] <= $islandid ? 1 : 0,
            ];
        }

        $return['status'] = 1;
        $return['list'] = $list;
        return $return;
    }

    /**
     * 获取任务信息: c=island&m=get_mission（id 任务id）
     */
    public static function get_mission(Context $ctx): array
    {
        /**
         *  -2 获取任务信息失败
         *  -1 获取玩家活动信息失败
         *   0 失败
         *   1 成功
         *   2 任务未开启
         *   4 等级不符合
         */
        $return = ['status' => 0, 'info' => []];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        $uid = (int)$member['uid'];
        $id = (int)$ctx->input('id', 0);
        // [用户模型]获取玩家活动信息
        $activity = self::getActivityInfo($uid);
        if ($activity === null) {
            // 获取玩家活动信息失败 返回 -1
            $return['status'] = -1;
            return $return;
        }

        // 当日挑战次数
        $act = $activity['islandtimes'] + 1;

        // [island模型]获取任务信息(id) 获取失败返回 -2
        $mission = Island::ROWS[$id] ?? null;
        if ($mission === null) {
            $return['status'] = -2;
            return $return;
        }

        // [island模型]获取任务等级
        $level = self::getMissionLevel((int)$member['level']);
        if ($mission['level'] !== $level) {
            // 等级不符合 返回4；开启本等级组第一关（旧版取不到时 islandid 为 null，存成 0）
            $return['status'] = 4;
            self::updateActivityInfo($uid, ['islandid' => self::getOpenMission($level), 'islanddate' => time()]);
            return $return;
        }

        // 字段过滤
        $info = [];
        foreach (['id', 'monsterlevel', 'name', 'image', 'silver', 'gold'] as $v) {
            $info[$v] = $mission[$v];
        }
        $info['act'] = $act;
        $return['status'] = 1;
        $return['info'] = $info;
        return $return;
    }

    /**
     * 战斗: c=island&m=pk（id 任务id）
     */
    public static function pk(Context $ctx): array
    {
        /**
         * -7 加银币失败
         * -6 加金币失败
         * -5 更新玩家活动信息失败
         * -4 获取战斗信息失败
         * -3 获取任务信息失败
         * -2 获取玩家活动信息失败
         * -1 战斗cd
         *  0 失败
         *  1 成功
         *  2 任务未开启
         *  3 等级不符合
         *  4 军令不足
         * （旧版注释未列出：-8 扣军令失败）
         */
        $now = time();
        $return = ['status' => 0, 'info' => []];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        $uid = (int)$member['uid'];
        $id = (int)$ctx->input('id', 0);

        // 取战斗cd信息
        $cd = self::getAttack($uid, $now);
        if ($cd['status'] === 1 && $cd['cd'] > 0) {
            $return['status'] = -1;
            return $return;
        }
        if ($cd['status'] === 0 && $cd['cd'] > self::MAX_CD) {
            $return['status'] = -1;
            return $return;
        }

        // 同一玩家并发挑战：在事务里锁住活动记录行，挑战次数 / 进度读写串行
        return DB::transaction(static function () use ($return, $member, $uid, $id, $now): array {
            // [用户模型]获取玩家活动信息
            $activity = self::lockActivity($uid);
            if ($activity === null) {
                // 获取玩家活动信息失败 返回 -2
                $return['status'] = -2;
                return $return;
            }
            // 已开启的任务id；检查是否开启
            $islandid = $activity['islandid'];
            if ($islandid < $id) {
                // 任务未开启 返回2
                $return['status'] = 2;
                return $return;
            }
            // 当日挑战次数
            $act = $activity['islandtimes'] + 1;

            // [island模型]获取任务信息(id)
            $mission = Island::ROWS[$id] ?? null;
            if ($mission === null) {
                // 获取任务信息失败 返回-3
                $return['status'] = -3;
                return $return;
            }
            // 奖励的金币,银币
            $gold = $mission['gold'];
            $silver = $mission['silver'];
            // [island模型]获取任务等级
            if ($mission['level'] !== self::getMissionLevel((int)$member['level'])) {
                // 等级不符合 返回3
                $return['status'] = 3;
                return $return;
            }
            // [战斗模型]战斗(任务信息：position1~9)
            $battle = self::battleIsland($uid, $mission);
            if ($battle['status'] !== 1) {
                // 获取战斗信息失败 返回 -4
                $return['status'] = -4;
                return $return;
            }

            if ($act > (int)$member['act']) {
                // 军令不足
                $return['status'] = 4;
                return $return;
            }

            // 扣军令
            if (!Member::deductCurrency($uid, act: $act)) {
                $return['status'] = -8;
                return $return;
            }
            $reportId = 0;
            // 胜利
            if ($battle['info']['assess'] > 0) {
                // 挑战次数+1；最大任务 id 等于当次任务 id 时开启下个任务
                $info = [];
                if ($activity['islandid'] === $mission['id']) {
                    $info['islandid'] = $activity['islandid'] + 1;
                }
                $info['islandtimes'] = $activity['islandtimes'] + 1;
                $info['islanddate'] = $now;
                if (self::updateActivityInfo($uid, $info) !== 1) {
                    // 更新玩家活动信息失败 返回 -5
                    $return['status'] = -5;
                    return $return;
                }
                // 加奖励：元宝按概率掉落
                if ($gold > 0) {
                    if (self::rollGold($mission['probability']) === 1) {
                        // 加金币失败（-6）：Member::addCurrency 不返回结果
                        Member::addCurrency($uid, gold: $gold);
                    } else {
                        $gold = 0;
                    }
                }
                if ($silver > 0) {
                    // 加银币失败（-7）：Member::addCurrency 不返回结果
                    Member::addCurrency($uid, silver: $silver);
                }
                // 获取战斗记录：首胜记录战报
                if (self::getMissionLog($uid, $mission['id']) === null) {
                    // 用户神树信息
                    $treeId = (int)$battle['info']['leftbuff']['tree'];
                    $reportId = self::addMissionLog($member, $mission['id'], $treeId, $now);
                    // 战斗记录不存在则添加
                    if (!self::checkReport($reportId)) {
                        self::addMissionReport($reportId, $mission, $member, $battle['info']);
                    }
                }
            }
            $win = $battle['info']['assess'] > 0;
            $return['info']['id'] = $mission['id'];
            $return['info']['background'] = $mission['pkbackground'];
            $return['status'] = 1;
            $return['info']['report'] = $reportId;
            $return['info']['act'] = self::memberValue($uid, 'act');
            $return['info']['win'] = $battle['info']['assess'];
            $return['info']['reward_gold'] = $win ? $gold : 0;
            $return['info']['reward_silver'] = $win ? $silver : 0;
            $return['info']['gold'] = self::memberValue($uid, 'gold');
            $return['info']['silver'] = self::memberValue($uid, 'silver');
            // 增加战斗cd,得到当前cd：旧版 vip_model->cd_status() 恒为 1（免 CD），不写 CD
            $return['info']['cd'] = 0;

            $return['battle'] = $battle['info'];
            $return['battle']['left_role'] = [
                'name' => (string)$member['nickname'],
                'level' => (int)$member['level'],
            ];
            $return['battle']['right_role'] = [
                'name' => $mission['name'],
                'level' => $mission['level'],
            ];
            // 依赖未移植：日常任务 task_model->task_action(9)（主公 41 级以上，9 是金银洞对应的类型）
            return $return;
        });
    }

    /**
     * 获取攻略列表: c=island&m=get_report_list（id 关卡id）
     */
    public static function get_report_list(Context $ctx): array
    {
        /**
         * -1 获取攻略列表失败
         *  0 失败
         *  1 成功
         *  2 攻略不存在
         */
        $return = ['status' => 0, 'list' => []];
        $islandid = (int)$ctx->input('id', 0);
        $report = self::getLogById($islandid);
        if ($report === []) {
            // 攻略不存在 返回2
            $return['status'] = 2;
            return $return;
        }
        $return['status'] = 1;
        $return['list'] = $report;
        return $return;
    }

    /**
     * 获取玩家攻略: c=island&m=get_report_info（id 攻略id）
     */
    public static function get_report_info(Context $ctx): array
    {
        /**
         * -1 获取攻略列表失败
         *  0 失败
         *  1 成功
         *  2 攻略不存在
         */
        $return = ['status' => 0, 'battle' => [], 'info' => []];
        $id = (int)$ctx->input('id', 0);
        if ($id <= 0) {
            // island_model->_get_report：id 不合法返回 -2，控制器按获取失败返回 -1
            $return['status'] = -1;
            return $return;
        }
        $result = self::getReportById($id);
        if ($result === null) {
            // 攻略不存在 返回2
            $return['status'] = 2;
            return $return;
        }
        $return['status'] = 1;
        $return['battle'] = $result['report'];
        $return['battle']['left_role'] = [
            'name' => $result['nickname'],
            'level' => $result['userlevel'],
        ];
        $return['battle']['right_role'] = [
            'name' => $result['name'],
            'level' => $result['level'],
        ];
        $return['info']['win'] = $result['report']['assess'] ?? null;
        return $return;
    }

    /**
     * 发送战报到聊天室: c=island&m=send_to_chat（id 关卡id，report 战报id）
     *
     * status：-1 参数不完整；-2 元宝不足 / 关卡不存在；-3 扣元宝失败；1 成功
     */
    public static function send_to_chat(Context $ctx): array
    {
        // 战报id
        $reportId = (int)$ctx->input('report', 0);
        $id = (int)$ctx->input('id', 0);
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($id === 0 || $reportId === 0 || $member === null) {
            return ['status' => -1];
        }
        // 查询关卡名字
        if (!isset(Island::ROWS[$id])) {
            return ['status' => -2];
        }
        // 依赖未移植：chat_model->get_message_str(8, general / id) + send_message_to_all(…, 8, report) 聊天室战报广播。
        // 旧版在这里先校验元宝(-2)、扣 2 元宝再广播；聊天室未移植前广播发不出去，
        // 开发者要求暂不扣元宝，返回 0（失败），客户端提示「暂时无法分享战报」。聊天室移植后恢复旧版流程。
        return ['status' => 0];
    }

    /**
     * 金银岛战斗（移植 battle_model->battle_island → battle_mission($position, 1)）
     *
     * 旧版 check_user_team：玩家 -1（没有阵法）/ -3（武将信息错误）时不战斗、status 非 1（pk 返回 -4）；
     * -2（阵法里没有武将）按空队伍继续战斗（空队伍直接判 -3 大败）。
     *
     * @param array<string, mixed> $mission Island 行
     * @return array{status: int, info: array<string, mixed>}
     */
    private static function battleIsland(int $uid, array $mission): array
    {
        $left = TeamBuilder::forMember($uid, pve: true);
        if ($left['status'] === -1 || $left['status'] === -3) {
            return ['status' => $left['status'], 'info' => []];
        }
        $right = TeamBuilder::forMonsters($mission, TeamBuilder::MONSTER_ISLAND);
        $engine = new BattleEngine($left['matrix_type'], $left['matrix_coe']);
        return [
            'status' => 1,
            'info' => $engine->battle($left['team'], $right['team']),
        ];
    }

    /**
     * 元宝掉落掷点（移植 island_model->roll_gold）：roll% 返回 1，否则 0
     */
    private static function rollGold(int $roll): int
    {
        $return = 0;
        $rule = [0 => 100 - $roll, 1 => $roll];
        $rand = mt_rand(1, 100);
        $cursor = 0;
        for ($i = 0; $i <= 1; $i++) {
            $cursor += $rule[$i];
            $return = $i;
            if ($cursor >= $rand) {
                break;
            }
        }
        return $return;
    }

    /**
     * 任务等级（移植 island_model->get_mission_level：主公等级取整到 10 级，最高 190）
     */
    private static function getMissionLevel(int $level): int
    {
        return min(190, intdiv($level, 10) * 10);
    }

    /**
     * 开启等级对应的任务（移植 island_model->open_mission：30 级组为 1，其余取该等级组第一关）
     */
    private static function openMission(int $level): int
    {
        $fbLevel = self::getMissionLevel($level);
        if ($fbLevel === 30) {
            return 1;
        }
        return self::getOpenMission($fbLevel);
    }

    /**
     * 等级组第一关的 id（移植 island_model->get_open_mission：ORDER BY id LIMIT 1），没有时 0
     */
    private static function getOpenMission(int $level): int
    {
        foreach (self::getMissionList($level) as $row) {
            return $row['id'];
        }
        return 0;
    }

    /**
     * 等级组的任务列表（移植 island_model->get_mission_list）
     *
     * @return list<array<string, mixed>>
     */
    private static function getMissionList(int $level): array
    {
        $return = [];
        foreach (Island::ROWS as $row) {
            if ($row['level'] === $level) {
                $return[] = $row;
            }
        }
        return $return;
    }

    /**
     * 战斗 CD（移植 cd_model->get_attack：member_field.attackstatus / attackdateline）
     *
     * @return array{status: int, cd: int}
     */
    private static function getAttack(int $uid, int $now): array
    {
        $row = DB::table('member_field')->select('attackstatus', 'attackdateline')->where('uid', $uid)->whereNull('deleted_at')->first();
        if ($row === null) {
            return ['status' => 0, 'cd' => 0];
        }
        return ['status' => (int)$row['attackstatus'], 'cd' => max(0, (int)$row['attackdateline'] - $now)];
    }

    /**
     * 玩家活动信息（移植 member_model->get_activity_info：没有记录时按旧版初始值建档）
     *
     * @return array{uid: int, islandid: int, islandtimes: int, islanddate: int}|null
     */
    private static function getActivityInfo(int $uid): ?array
    {
        MemberActivity::ensure($uid);
        $row = DB::table('member_activity')->select(...self::ACTIVITY_COLUMNS)->where('uid', $uid)->whereNull('deleted_at')->first();
        return $row === null ? null : array_map('intval', $row);
    }

    /**
     * 玩家活动信息并加行锁（同 getActivityInfo，在事务里使用）
     *
     * @return array{uid: int, islandid: int, islandtimes: int, islanddate: int}|null
     */
    private static function lockActivity(int $uid): ?array
    {
        MemberActivity::ensure($uid);
        $rows = DB::query(
            'SELECT `' . implode('`, `', self::ACTIVITY_COLUMNS) . '` FROM `zc_sanguo_member_activity` WHERE `uid` = :uid AND `deleted_at` IS NULL FOR UPDATE',
            ['uid' => $uid]
        );
        return $rows === [] ? null : array_map('intval', $rows[0]);
    }

    /**
     * 更新活动记录（移植 member_model->update_activity_info，写入成功返回 1）
     *
     * @param array<string, int> $data
     */
    private static function updateActivityInfo(int $uid, array $data): int
    {
        return DB::table('member_activity')->where('uid', $uid)->whereNull('deleted_at')->update($data) > 0 ? 1 : 0;
    }

    /**
     * 玩家某关的首胜记录（移植 island_model->get_mission_log）
     *
     * @return array<string, mixed>|null
     */
    private static function getMissionLog(int $uid, int $islandid): ?array
    {
        $row = DB::table('island_log')->select(...self::LOG_COLUMNS)
            ->where('uid', $uid)->where('islandid', $islandid)->whereNull('deleted_at')->first();
        return $row === null ? null : self::castLog($row);
    }

    /**
     * 关卡攻略列表：最近 7 条首胜记录（移植 island_model->get_log_by_id：ORDER BY id DESC LIMIT 7）
     *
     * @return list<array<string, mixed>>
     */
    private static function getLogById(int $islandid): array
    {
        $rows = DB::table('island_log')->select(...self::LOG_COLUMNS)
            ->where('islandid', $islandid)->whereNull('deleted_at')->orderBy('id', 'DESC')->limit(7)->get();
        return array_map(self::castLog(...), $rows);
    }

    /**
     * 添加首胜记录（移植 island_model->add_mission_log），返回记录 id（即战报 id）
     *
     * @param array<string, mixed> $member
     */
    private static function addMissionLog(array $member, int $islandid, int $tree, int $now): int
    {
        return (int)DB::table('island_log')->insertGetId([
            'islandid' => $islandid,
            'uid' => (int)$member['uid'],
            'nickname' => (string)$member['nickname'],
            'level' => (int)$member['level'],
            'dateline' => $now,
            'tree' => $tree,
        ]);
    }

    /**
     * 战报是否已存在（移植 island_model->check_report）
     */
    private static function checkReport(int $id): bool
    {
        return DB::table('island_report')->where('id', $id)->whereNull('deleted_at')->exists();
    }

    /**
     * 添加战报（移植 island_model->add_mission_report：report 列存战报 json，不压缩；level 为 monsterlevel）
     *
     * @param array<string, mixed> $mission
     * @param array<string, mixed> $member
     * @param array<string, mixed> $report
     */
    private static function addMissionReport(int $id, array $mission, array $member, array $report): void
    {
        DB::table('island_report')->insert([
            'id' => $id,
            'nickname' => (string)$member['nickname'],
            'userlevel' => (int)$member['level'],
            'name' => $mission['name'],
            'level' => $mission['monsterlevel'],
            'report' => (string)json_encode($report),
        ]);
    }

    /**
     * 按 id 取战报（移植 island_model->get_mission_report_by_id），不存在或战报为空时 null
     *
     * @return array{level: int, name: string, nickname: string, userlevel: int, report: array<string, mixed>}|null
     */
    private static function getReportById(int $id): ?array
    {
        $row = DB::table('island_report')->select('level', 'name', 'nickname', 'userlevel', 'report')
            ->where('id', $id)->whereNull('deleted_at')->first();
        if ($row === null) {
            return null;
        }
        $report = json_decode((string)$row['report'], true);
        if (!is_array($report) || $report === []) {
            // 没有找到指定战报
            return null;
        }
        return [
            'level' => (int)$row['level'],
            'name' => (string)$row['name'],
            'nickname' => (string)$row['nickname'],
            'userlevel' => (int)$row['userlevel'],
            'report' => $report,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function castLog(array $row): array
    {
        foreach ($row as $key => $value) {
            $row[$key] = $key === 'nickname' ? (string)$value : (int)$value;
        }
        return $row;
    }

    private static function memberValue(int $uid, string $column): int
    {
        return (int)(DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value($column) ?? 0);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function member(int $uid): ?array
    {
        if ($uid <= 0) {
            return null;
        }
        return DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
    }
}
