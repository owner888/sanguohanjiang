<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use Hanjiang\MasterData\LevelFunc;
use Hanjiang\MasterData\Map as MapTemplate;
use Hanjiang\MasterData\Mission;
use Hanjiang\MasterData\MissionNewreward;
use Hanjiang\MasterData\MissionReward;
use Hanjiang\MasterData\Price;
use Hanjiang\MasterData\Scene;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;

/**
 * 主线推图（征战）进度与记录（移植旧版 models/map_model.php）
 *
 * 配置表（map / scene / mission / price / mission_reward / level_func / mission_newreward）取 MasterData；
 * 玩家数据：open_map（每人每势力一行：stage 已开启的最大场景、mission 该场景已通过的最大关卡、newresult 势力通关奖励
 * 领取状态）、mission_log（每人每关一行：首杀记录 + 今日攻击次数 / 强攻次数）、mission_report（首杀战报，id 即
 * mission_log.id）、mission_rewardlog（闯关竞速领奖）、map_action_log（闯关竞速参加人次）、mission_newreward_log
 * （关卡宝箱领取记录）。
 *
 * 旧版的「当前玩家」$this->member / $this->member_field 改为显式传 uid；member_field.newresult 按需从库里读。
 *
 * 与旧版的差别（多 Worker 安全，正常流程结果相同）：
 * - open_map 加唯一键 (uid, level)，开启势力用 INSERT IGNORE（旧版并发首次进入会插出两行同一势力）；
 * - mission_log 加唯一键 (uid, missionlevel, missionstage, missionid)，首杀记录用 INSERT IGNORE；
 * - mission_rewardlog 加唯一键 (uid, missionid)；
 * - 领奖锁 get_map_reward_{uid} 用 SET NX EX（旧版先 GET 再 SETEX）。
 */
final class OpenMap
{
    /** 默认在已通过的关卡下开启几关（$mission_num） */
    public const int MISSION_NUM = 1;

    /** 领取通关奖励的锁定 key（$lock_key） */
    private const string LOCK_KEY = 'get_map_reward_';

    /** open_map 的旧版字段 */
    private const array MAP_COLUMNS = ['id', 'uid', 'level', 'stage', 'mission', 'newresult', 'dateline'];

    /** mission_log 的旧版字段 */
    private const array LOG_COLUMNS = ['id', 'uid', 'nickname', 'missionlevel', 'missionstage', 'missionid', 'times', 'strom', 'dateline', 'level', 'disable', 'tree'];

    /**
     * 注册时初始化地图（add_map_log(1, $uid)：开启第一个势力）
     */
    public static function init(int $uid, ?int $now = null): void
    {
        self::insertLevel($uid, 1, $now ?? time());
    }

    /**
     * 势力列表（get_map_list），按势力 id 索引；status 开启状态，pass_status 通关奖励已领取
     *
     * @return array<int, array<string, mixed>>
     */
    public static function mapList(int $uid): array
    {
        $newresult = self::fieldNewresult($uid);
        // 开启的势力列表，取log记录
        $openMap = [];
        foreach (self::rows($uid) as $val) {
            // 得到记录中的势力id
            $openMap[$val['level']] = $val;
        }
        // 势力列表
        $list = [];
        foreach (MapTemplate::ROWS as $val) {
            $list[$val['id']] = $val;
            // 没有推图记录，默认开启第1个势力
            $list[$val['id']]['status'] = 0;
            if ($openMap === []) {
                // 没有记录，默认开启第一个势力
                $list[1]['status'] = 1;
            }
            if (isset($openMap[$val['id']])) {
                // 记录中有这个势力id，状态为开启
                $list[$val['id']]['status'] = 1;
            }
            // 判断上个势力是否开启
            if (isset($openMap[$val['premise']]) && ($openMap[$val['premise']]['newresult'] === 1 || $newresult === $val['premise'])) {
                $list[$val['id']]['status'] = 1;
            }
            // 势力通关状态
            $list[$val['id']]['pass_status'] = 0;
            if (isset($openMap[$val['id']]) && $openMap[$val['id']]['newresult'] === 1) {
                $list[$val['id']]['pass_status'] = 1;
            }
        }
        return $list;
    }

    /**
     * 场景列表（get_scene_list）
     *
     * status：-1 势力未开启；-2 势力没有场景；1 成功（list 按场景 id 索引，next_status 下个势力是否开启）
     *
     * @return array<string, mixed>
     */
    public static function sceneList(int $uid, int $level, int $now): array
    {
        $return = [];
        $mapLog = self::openMapLog($uid, $level, 0, $now);
        // 检查传进来的势力有没有开启。
        if ($mapLog['status'] === -1) {
            $return['status'] = -1;
            return $return;
        }
        $scenes = array_filter(Scene::ROWS, static fn (array $row): bool => $row['level'] === $level);
        // 检查场景是否存在
        if ($scenes === []) {
            $return['status'] = -2;
            return $return;
        }
        // 遍历设置开启状态
        $list = [];
        foreach ($scenes as $val) {
            $list[$val['id']] = $val;
            $list[$val['id']]['status'] = 0;
            $list[$val['id']]['openstatus'] = 0;
            // 能开启最大场景id
            if ($val['stage'] <= $mapLog['info']['stage']) {
                $list[$val['id']]['openstatus'] = 1;
            }
            // 通过的场景
            if ($mapLog['info']['newresult'] === 1) {
                $list[$val['id']]['status'] = 1;
            } elseif ($val['stage'] <= $mapLog['info']['stage'] - 1) {
                $list[$val['id']]['status'] = 1;
            }
        }
        // 查询下个势力是否开启
        $mapLog = self::openMapLog($uid, $level + 1, 0, $now);
        $return['next_status'] = 1;
        if ($mapLog['status'] === -1 || $level === 14) {
            $return['next_status'] = 0;
        }
        // 返回列表
        $return['status'] = 1;
        $return['list'] = $list;
        return $return;
    }

    /**
     * 关卡列表（get_mission_list）
     *
     * status：-1 势力未开启；-2 场景未开启；-3 场景没有关卡；1 成功（list 按关卡 id 索引，next_status 下个场景是否开启）
     *
     * @return array<string, mixed>
     */
    public static function missionList(int $uid, int $level, int $stage, int $now): array
    {
        $return = [];
        // 如果传了势力id和场景id的话，就用传进来的信息查询
        $mapLog = self::openMapLog($uid, $level, $stage, $now);
        // 验证势力未开启
        if ($mapLog['status'] === -1) {
            $return['status'] = -1;
            return $return;
        }
        // 验证场景未开启
        if ($mapLog['status'] === -2) {
            $return['status'] = -2;
            return $return;
        }
        $return['next_status'] = 0;
        if ($stage + 1 <= $mapLog['info']['stage']) {
            $return['next_status'] = 1;
        }
        $result = self::missions($level, $stage);
        // 没有找到关卡数据
        if ($result === []) {
            $return['status'] = -3;
            return $return;
        }
        // 默认开启几关
        $cursor = $mapLog['info']['mission'] + self::MISSION_NUM;
        $field = ['id', 'missionlevel', 'missionstage', 'missionid', 'level', 'type', 'generalid', 'x', 'y', 'background', 'maxtimes'];
        $list = [];
        foreach ($result as $val) {
            $info = [];
            foreach ($field as $fieldVal) {
                $info[$fieldVal] = $val[$fieldVal];
            }
            $info['name'] = '';
            if (in_array($val['gettype'], [1, 2, 5, 6], true) || $val['type'] === 3 || $val['type'] === 4) {
                $info['name'] = $val['name'];
            }
            $list[$val['id']] = $info;
            $list[$val['id']]['status'] = 0;
            $list[$val['id']]['openstatus'] = 0;
            // 开启的场景大于当前场景，说明场景内的关卡全部通过
            if ($stage < $mapLog['info']['stage']) {
                $list[$val['id']]['openstatus'] = 1;
                $list[$val['id']]['status'] = 1;
            } else {
                // 开启的关卡,关卡地图没有前置条件
                if ($val['missionid'] <= $cursor) {
                    $list[$val['id']]['openstatus'] = 1;
                }
                // 通过的关卡
                if ($val['missionid'] <= $mapLog['info']['mission']) {
                    $list[$val['id']]['status'] = 1;
                }
            }
        }
        $return['status'] = 1;
        $return['list'] = $list;
        return $return;
    }

    /**
     * 关卡详细信息（get_mission_by_id）；$status 为 1 时不判断关卡是否开启
     *
     * status：-1 没有地图记录；-2 场景未开启；-3 关卡未开启；-4 没有找到关卡；1 成功（info 为 Mission 行）
     *
     * @return array{status: int, info?: array<string, mixed>}
     */
    public static function missionById(int $uid, int $level, int $stage, int $id, int $status = 0, ?int $now = null): array
    {
        $mapLog = self::openMapLog($uid, $level, $stage, $now ?? time());
        // 没有地图记录
        if ($mapLog['status'] === -1) {
            return ['status' => -1];
        }
        // 场景未开启
        if ($mapLog['status'] === -2) {
            return ['status' => -2];
        }
        // 验证关卡是否开启
        $corsor = $mapLog['info']['mission'] + self::MISSION_NUM;
        if ($status === 0 && $stage === $mapLog['info']['stage'] && $id > $corsor) {
            return ['status' => -3];
        }
        $row = self::mission($level, $stage, $id);
        if ($row === null) {
            // 没有找到关卡
            return ['status' => -4];
        }
        return ['status' => 1, 'info' => $row];
    }

    /**
     * 场景中关卡总数（get_mission_num：场景下最大的 missionid，没有关卡为 -1）
     */
    public static function missionNum(int $level, int $stage): int
    {
        $max = -1;
        foreach (self::missions($level, $stage) as $row) {
            $max = max($max, $row['missionid']);
        }
        return $max;
    }

    /**
     * 取开启记录（get_open_map_log）
     *
     * 旧版 SQL 是 level >= $level 取第一行（没有 ORDER BY，按插入顺序；势力总是从小到大开启，即 level 最小的一行），
     * 这里按 level、id 排序取第一行。没有记录时：前置势力通关（open_map.newresult 为 1，或 member_field.newresult
     * 为前置势力）则开启该势力并返回 stage 1、mission 0；否则 -1。传了场景且场景大于记录的 stage 返回 -2。
     *
     * @return array{status: int, info?: array{id?: int, uid: int, level: int, stage: int, mission: int, newresult: int, dateline: int}}
     */
    public static function openMapLog(int $uid, int $level, int $stage = 0, ?int $now = null): array
    {
        $now ??= time();
        $result = null;
        foreach (self::rows($uid) as $row) {
            if ($row['level'] >= $level) {
                $result = $row;
                break;
            }
        }
        // 没有找到该关卡log,判断当前势力的前置势力是否通关,通关则添加记录
        if ($result === null) {
            // 查询当前关卡的前置势力
            $map = MapTemplate::ROWS[$level] ?? null;
            if ($map === null) {
                return ['status' => -1];
            }
            // 判断前置势力是否通关
            $premise = self::openMapLog($uid, $map['premise'], 0, $now);
            if ($premise['status'] === 1 && ($premise['info']['newresult'] === 1 || self::fieldNewresult($uid) === $map['premise'])) {
                // 如果通关，添加记录
                $result = [
                    'uid' => $uid,
                    'level' => $level,
                    'stage' => 1,
                    'mission' => 0,
                    'newresult' => 0,
                    'dateline' => $now,
                ];
                self::addMapLog($uid, [$level], $now);
            } else {
                // 前置势力未通关，直接返回
                return ['status' => -1];
            }
        }
        // 传了场景ID，验证是否开启
        if ($stage > $result['stage']) {
            return ['status' => -2];
        }
        // 找到log，返回记录
        return ['status' => 1, 'info' => $result];
    }

    /**
     * 根据势力和场景id，得到下一个能开启的场景（get_open_scene）
     *
     * status：-1 场景不存在；-2 没有可开启的场景；1 成功（info 为 Scene 行）
     *
     * @return array{status: int, info?: array<string, mixed>}
     */
    public static function openScene(int $level, int $stage): array
    {
        // 查询场景是否存在,并取到场景id
        $scene = self::sceneById($level, $stage);
        if ($scene === []) {
            return ['status' => -1];
        }
        // 根据场景id取可开启的场景信息
        foreach (Scene::ROWS as $row) {
            if ($row['opencondition'] === $scene['id']) {
                return ['status' => 1, 'info' => $row];
            }
        }
        // 没有可开启的关卡
        return ['status' => -2];
    }

    /**
     * 可开启的势力列表，排除已开启的势力（open_map_list）
     *
     * status：-1 没有前置势力为 $level 的势力；1 成功（info 按势力 id 索引）
     *
     * @return array{status: int, info?: array<int, array<string, mixed>>}
     */
    public static function openMapList(int $uid, int $level): array
    {
        // 查询前置势力等于传进来的id的势力信息
        $list = [];
        foreach (MapTemplate::ROWS as $val) {
            if ($val['premise'] === $level) {
                $list[$val['id']] = $val;
            }
        }
        if ($list === []) {
            // 没有找到可开启的势力列表
            return ['status' => -1];
        }
        // 取用户已开启的势力列表，把已开启的势力从可开启的势力列表中删除
        foreach (self::rows($uid) as $val) {
            unset($list[$val['level']]);
        }
        return ['status' => 1, 'info' => $list];
    }

    /**
     * 开启势力（add_map_log 传势力 id 数组）：status 1 有势力要开启，-1 没有
     *
     * @param list<int> $levelarr
     * @return array{status: int}
     */
    public static function addMapLog(int $uid, array $levelarr, ?int $now = null): array
    {
        $return = ['status' => -1];
        // 传需要开启的势力id，记录到开启记录表中
        foreach ($levelarr as $val) {
            self::insertLevel($uid, (int)$val, $now ?? time());
            $return['status'] = 1;
        }
        return $return;
    }

    /**
     * 修改开启记录（open_log）：$id 为 0 时开启新场景（stage = $stage、mission = 0），否则记通过关卡（mission = $id）
     *
     * 多 Worker 安全：只往前推进（新场景大于记录的 stage / 关卡大于记录的 mission），旧版无此条件，正常流程结果相同。
     *
     * @return array{status: int} 1 有修改，0 没有
     */
    public static function openLog(int $uid, int $level, int $stage, int $id, int $now): array
    {
        $query = DB::table('open_map')->where('uid', $uid)->where('level', $level)->whereNull('deleted_at');
        if ($id === 0) {
            // 修改场景
            $affected = $query->where('stage', '<', $stage)->update(['stage' => $stage, 'mission' => 0, 'dateline' => $now]);
        } else {
            // 修改关卡
            $affected = $query->where('mission', '<', $id)->update(['mission' => $id, 'dateline' => $now]);
        }
        return ['status' => $affected > 0 ? 1 : 0];
    }

    /**
     * 该关卡今日攻击次数（get_attack_times）
     *
     * status：-1 没有记录（首杀）；-2 记录不是今天的（次数全为 0）；1 今天的记录（times 普通攻击次数、strom 强攻次数）
     *
     * @return array{status: int, times?: int, strom?: int}
     */
    public static function attackTimes(int $uid, int $level, int $stage, int $id, int $now): array
    {
        $row = self::missionLog($uid, $level, $stage, $id);
        if ($row === null) {
            // 没有记录,攻击次数为0，并且首杀
            return ['status' => -1];
        }
        if (date('ymd', $row['dateline']) === date('ymd', $now)) {
            // 有记录，返回普通攻击次数和强攻次数,非首杀
            return ['status' => 1, 'times' => $row['times'], 'strom' => $row['strom']];
        }
        // 不是今天的记录，次数全为0，非首杀
        return ['status' => -2];
    }

    /**
     * 强攻花费元宝（gold_spent：当前强攻次数 + 1 对应的 Price.storm，50 次及以上 50）
     */
    public static function goldSpent(int $storm): int
    {
        $storm += 1;
        // 根据当前攻次数取对应的强攻价格
        if ($storm >= 50) {
            // 次数大于等于50，价格全是50金币
            return 50;
        }
        return (int)(Price::ROWS[$storm]['storm'] ?? 0);
    }

    /**
     * 连续刷图需要的元宝（get_price_by_times：从当前强攻次数 $start 起再强攻 $times 次）
     */
    public static function priceByTimes(int $start, int $times = 1): int
    {
        $sub = 0;
        for ($i = 0; $i < $times; $i++) {
            $start = $start + 1 > 50 ? 50 : $start + 1;
            $sub += (int)(Price::ROWS[$start]['storm'] ?? 0);
        }
        return $sub;
    }

    /**
     * 首杀记录（add_mission_log），返回记录 id（已有记录为 0）
     *
     * @param array<string, mixed> $member 当前玩家（uid / nickname / level）
     */
    public static function addMissionLog(array $member, int $level, int $stage, int $id, int $tree, int $now): int
    {
        $inserted = DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_mission_log`
                (`uid`, `nickname`, `missionlevel`, `missionstage`, `missionid`, `times`, `dateline`, `level`, `tree`)
             VALUES (:uid, :nickname, :missionlevel, :missionstage, :missionid, 1, :dateline, :level, :tree)',
            [
                'uid' => (int)$member['uid'],
                'nickname' => (string)$member['nickname'],
                'missionlevel' => $level,
                'missionstage' => $stage,
                'missionid' => $id,
                'dateline' => $now,
                'level' => (int)$member['level'],
                'tree' => $tree,
            ]
        );
        if ($inserted === 0) {
            return 0;
        }
        return (int)DB::query('SELECT LAST_INSERT_ID() AS `id`')[0]['id'];
    }

    /**
     * 首杀战报（add_mission_report：report 存 base64(gzcompress(战报 json))，id 即 mission_log.id）
     *
     * @param array<string, mixed> $member  当前玩家（nickname / level）
     * @param array<string, mixed> $mission Mission 行
     */
    public static function addMissionReport(int $id, string $report, array $member, array $mission): void
    {
        DB::table('mission_report')->insert([
            'id' => $id,
            'report' => $report,
            'nickname' => (string)$member['nickname'],
            'userlevel' => (int)$member['level'],
            'name' => (string)$mission['name'],
            'level' => (int)$mission['level'],
            'background' => (int)$mission['background'],
        ]);
    }

    /**
     * 关卡最近 7 条首杀记录（get_mission_report：全服 mission_log 按 id 倒序）
     *
     * status：-1 没有战报；1 成功（list 按记录 id 索引：id / uid / level / nickname / tree；uid_arr 为昵称为空的 uid）
     *
     * @return array{status: int, list: array<int, array<string, mixed>>, uid_arr?: list<int>}
     */
    public static function missionReport(int $level, int $stage, int $id): array
    {
        $return = ['status' => 0, 'list' => []];
        $logArr = $uidArr = [];
        $rows = DB::table('mission_log')->select('id', 'uid', 'level', 'nickname', 'tree')
            ->where('missionlevel', $level)->where('missionstage', $stage)->where('missionid', $id)->whereNull('deleted_at')
            ->orderBy('id', 'DESC')->limit(7)->get();
        foreach ($rows as $value) {
            $value = ['id' => (int)$value['id'], 'uid' => (int)$value['uid'], 'level' => (int)$value['level'], 'nickname' => (string)$value['nickname'], 'tree' => (int)$value['tree']];
            $logArr[$value['id']] = $value;
            if ($value['nickname'] === '') {
                $uidArr[] = $value['uid'];
            }
        }
        if ($logArr === []) {
            // 没有战报
            $return['status'] = -1;
            return $return;
        }
        $return['status'] = 1;
        $return['list'] = $logArr;
        $return['uid_arr'] = $uidArr;
        return $return;
    }

    /**
     * 按 id 取单条战报（get_mission_report_by_id：report 先按 json 解，不是数组再按 base64 + gzuncompress 解）
     *
     * status：-1 没有指定的战报；1 成功（report 为战报，info 为 level / name / nickname / userlevel / background）
     *
     * @return array{status: int, report: array<string, mixed>, info?: array<string, mixed>}
     */
    public static function missionReportById(int $id): array
    {
        $return = ['status' => 0, 'report' => []];
        $row = DB::table('mission_report')->where('id', $id)->whereNull('deleted_at')->first();
        if ($row !== null) {
            $report = json_decode((string)$row['report'], true);
            if (!is_array($report)) {
                $raw = base64_decode((string)$row['report'], true);
                $raw = $raw === false ? false : gzuncompress($raw);
                $report = $raw === false ? null : json_decode($raw, true);
            }
            $return['report'] = is_array($report) ? $report : [];
            $return['info'] = [
                'level' => (int)$row['level'],
                'name' => (string)$row['name'],
                'nickname' => (string)$row['nickname'],
                'userlevel' => (int)$row['userlevel'],
                'background' => (int)$row['background'],
            ];
        }
        // 没有找到指定战报为 -1
        $return['status'] = $return['report'] !== [] ? 1 : -1;
        return $return;
    }

    /**
     * 修改关卡攻击记录（update_mission_log：times / strom / dateline）
     *
     * @param array<string, int> $data
     */
    public static function updateMissionLog(int $uid, int $level, int $stage, int $id, array $data): void
    {
        DB::table('mission_log')->where('uid', $uid)->where('missionlevel', $level)->where('missionstage', $stage)
            ->where('missionid', $id)->whereNull('deleted_at')->update($data);
    }

    /**
     * 势力通关奖励已领取（update_newresult：open_map.newresult = 1）
     */
    public static function updateNewresult(int $uid, int $level): void
    {
        DB::table('open_map')->where('uid', $uid)->where('level', $level)->whereNull('deleted_at')->update(['newresult' => 1]);
    }

    /**
     * 闯关竞速奖励（get_reward_list：$id 为关卡 id 时取那一行，否则全部）
     *
     * @return array{status: int, list: array<int|string, mixed>}
     */
    public static function rewardList(int $id = 0): array
    {
        $return = ['status' => 0, 'list' => []];
        if ($id > 0) {
            foreach (MissionReward::ROWS as $row) {
                if ($row['missionid'] === $id) {
                    return ['status' => 1, 'list' => $row];
                }
            }
            return $return;
        }
        $rows = array_values(MissionReward::ROWS);
        if ($rows !== []) {
            $return = ['status' => 1, 'list' => $rows];
        }
        return $return;
    }

    /**
     * 已通过的最大关卡 id（get_mission_max）
     *
     * 取 level 最大的开启记录，mission 为 0 时按 1，再按 get_mission_by_id 查关卡 id；查不到（关卡未开启 / 不存在）
     * 时取 Mission 表最后一关（missionlevel、missionstage、missionid 最大的一行），表空时 397。没有记录 status 0、id 0。
     * 旧版 get_mission_by_id 用的是当前请求的玩家（$this->member）的开启记录，$memberUid 即当前玩家（默认同 $uid）。
     *
     * @return array{status: int, id: int}
     */
    public static function missionMax(int $uid, int $memberUid = 0): array
    {
        $return = ['status' => 0, 'id' => 0];
        $rows = self::rows($uid);
        if ($rows === []) {
            return $return;
        }
        // 查询攻打的最大势力信息
        usort($rows, static fn (array $a, array $b): int => $b['level'] <=> $a['level'] ?: $a['id'] <=> $b['id']);
        $result = $rows[0];
        if ($result['mission'] === 0) {
            $result['mission'] = 1;
        }
        // 查询最大势力下的关卡信息
        $info = self::missionById($memberUid > 0 ? $memberUid : $uid, $result['level'], $result['stage'], $result['mission']);
        if ($info['status'] === 1) {
            return ['status' => 1, 'id' => $info['info']['id']];
        }
        // 如果没有查询到关卡信息，就取最大的关卡信息
        $last = null;
        foreach (Mission::ROWS as $row) {
            if ($last === null || [$row['missionlevel'], $row['missionstage'], $row['missionid']] > [$last['missionlevel'], $last['missionstage'], $last['missionid']]) {
                $last = $row;
            }
        }
        // 没查询到关卡信息，取默认最大的关卡信息
        return ['status' => 1, 'id' => $last['id'] ?? 397];
    }

    /**
     * 该关卡是否击败过（get_mission_status：mission_log 有该关卡的记录为 1）
     */
    public static function missionStatus(int $uid, int $id): int
    {
        $mission = Mission::ROWS[$id] ?? null;
        if ($mission === null) {
            return 0;
        }
        return self::missionLog($uid, $mission['missionlevel'], $mission['missionstage'], $mission['missionid']) === null ? 0 : 1;
    }

    /**
     * 闯关竞速领奖记录（get_reward_log：$id 为关卡 id 时只查那一关，missionid 为该关卡 id；否则为已领取的关卡 id 列表）
     *
     * @return array{status: int, missionid: int|list<int>}
     */
    public static function rewardLog(int $uid, int $id = 0): array
    {
        $query = DB::table('mission_rewardlog')->select('missionid')->where('uid', $uid)->whereNull('deleted_at');
        if ($id > 0) {
            $row = $query->where('missionid', $id)->first();
            return $row === null ? ['status' => 0, 'missionid' => []] : ['status' => 1, 'missionid' => (int)$row['missionid']];
        }
        $ids = array_map(static fn (array $row): int => (int)$row['missionid'], $query->orderBy('id')->get());
        return $ids === [] ? ['status' => 0, 'missionid' => []] : ['status' => 1, 'missionid' => $ids];
    }

    /**
     * 闯关竞速领奖记录（add_reward_log）；已领过返回 false（唯一键，多 Worker 安全）
     */
    public static function addRewardLog(int $uid, int $id, int $now): bool
    {
        return DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_mission_rewardlog` (`missionid`, `uid`, `dateline`) VALUES (:missionid, :uid, :now)',
            ['missionid' => $id, 'uid' => $uid, 'now' => $now]
        ) > 0;
    }

    /**
     * 全服通过的最大势力（get_max_map，siege 都城开放用）
     *
     * 取全服 open_map 里 level、stage、mission 最大的一行，该势力最后一关已通过时为该势力，否则减 1；没有记录为 0。
     * 「已通过」的判断旧版拿势力 id 与最后一关的场景号比较（原样保留）。
     */
    public static function maxMap(): int
    {
        $maxMap = DB::table('open_map')->select('level', 'stage', 'mission')->whereNull('deleted_at')
            ->orderBy('level', 'DESC')->orderBy('stage', 'DESC')->orderBy('mission', 'DESC')->first();
        if ($maxMap === null) {
            return 0;
        }
        $level = (int)$maxMap['level'];
        // 取最大的一个势力通过了没有
        $maxMission = null;
        foreach (Mission::ROWS as $row) {
            if ($row['missionlevel'] === $level
                && ($maxMission === null || [$row['missionstage'], $row['missionid']] > [$maxMission['missionstage'], $maxMission['missionid']])) {
                $maxMission = $row;
            }
        }
        if ($maxMission === null) {
            return $level - 1;
        }
        if ($level === $maxMission['missionlevel'] && $level === $maxMission['missionstage'] && (int)$maxMap['mission'] === $maxMission['missionid']) {
            return $level;
        }
        return $level - 1;
    }

    /**
     * 关卡信息（get_mission_info：传数组时按关卡 id 索引）
     *
     * @param int|list<int> $id
     * @param list<string>  $field
     * @return array<int|string, mixed>
     */
    public static function missionInfo(int|array $id, array $field): array
    {
        $pick = static function (array $row) use ($field): array {
            $info = [];
            foreach ($field as $key) {
                $info[$key] = $row[$key];
            }
            return $info;
        };
        if (!is_array($id)) {
            return isset(Mission::ROWS[$id]) ? $pick(Mission::ROWS[$id]) : [];
        }
        $return = [];
        foreach ($id as $val) {
            if (isset(Mission::ROWS[(int)$val])) {
                $return[(int)$val] = $pick(Mission::ROWS[(int)$val]);
            }
        }
        return $return;
    }

    /**
     * 场景信息（get_scene_by_id）
     *
     * @return array<string, mixed>
     */
    public static function sceneById(int $level, int $stage): array
    {
        foreach (Scene::ROWS as $row) {
            if ($row['level'] === $level && $row['stage'] === $stage) {
                return $row;
            }
        }
        return [];
    }

    /**
     * 等级升级功能提示（get_levelup_func：openLevel 当前等级开启的功能，nextLevel 10 级内下一个开启的功能）
     *
     * @return array{openLevel?: array<string, mixed>, nextLevel?: array<string, mixed>}
     */
    public static function levelupFunc(int $level): array
    {
        $return = [];
        foreach (LevelFunc::ROWS as $val) {
            // 开启功能
            if ($level === $val['level']) {
                $return['openLevel'] = $val;
            } else {
                $minL = $level + 10;
                // 下一等级开启功能信息
                if ($minL >= $val['level'] && $level <= $val['level']) {
                    $return['nextLevel'] = $val;
                    break;
                }
            }
        }
        return $return;
    }

    /**
     * 领奖加锁（get_lock + add_lock，600 秒）：拿到锁返回 true
     */
    public static function lock(int $uid): bool
    {
        $redis = Redis::instance();
        return $redis !== null && (bool)$redis->set(self::LOCK_KEY . $uid, '1', ['nx', 'ex' => 600]);
    }

    /**
     * 领奖解锁（del_lock）
     */
    public static function unlock(int $uid): void
    {
        Redis::del(self::LOCK_KEY . $uid);
    }

    /**
     * 闯关竞速参加人次（add_action_log）
     */
    public static function addActionLog(int $uid, int $level, int $now): void
    {
        DB::table('map_action_log')->insert(['uid' => $uid, 'level' => $level, 'dateline' => $now]);
    }

    /**
     * 关卡宝箱奖励（get_mission_newreward：$id 为宝箱 id 时取那一行，否则取势力 $levelid 的全部；都为 0 时全部）
     *
     * @return array<int|string, mixed>
     */
    public static function missionNewreward(int $id = 0, int $levelid = 0): array
    {
        if ($id > 0) {
            return MissionNewreward::ROWS[$id] ?? [];
        }
        $return = [];
        foreach (MissionNewreward::ROWS as $row) {
            if ($levelid <= 0 || $row['levelid'] === $levelid) {
                $return[] = $row;
            }
        }
        return $return;
    }

    /**
     * 关卡宝箱领取记录（get_newrewardlog_info）
     *
     * @return array<string, mixed>
     */
    public static function newrewardlogInfo(int $uid, int $levelid, int $missid): array
    {
        return DB::table('mission_newreward_log')->select('id', 'uid', 'levelid', 'missid', 'dateline')
            ->where('uid', $uid)->where('levelid', $levelid)->where('missid', $missid)->whereNull('deleted_at')->first() ?? [];
    }

    /**
     * 关卡宝箱领取记录列表（get_newrewardlog_list：按「势力ID-关卡ID」索引）
     *
     * @return array<string, array<string, mixed>>
     */
    public static function newrewardlogList(int $uid): array
    {
        $return = [];
        $rows = DB::table('mission_newreward_log')->select('id', 'uid', 'levelid', 'missid', 'dateline')
            ->where('uid', $uid)->whereNull('deleted_at')->orderBy('id')->get();
        foreach ($rows as $val) {
            $return[$val['levelid'] . '-' . $val['missid']] = $val;
        }
        return $return;
    }

    /**
     * 记录领取关卡宝箱（add_newrewardlog）：1 成功，0 已领过（唯一键，多 Worker 安全）
     */
    public static function addNewrewardlog(int $uid, int $levelid, int $missid, int $now): int
    {
        return DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_mission_newreward_log` (`uid`, `levelid`, `missid`, `dateline`) VALUES (:uid, :levelid, :missid, :now)',
            ['uid' => $uid, 'levelid' => $levelid, 'missid' => $missid, 'now' => $now]
        ) > 0 ? 1 : 0;
    }

    /**
     * 场景下的关卡（Mission 行），按 missionid 排序
     *
     * @return list<array<string, mixed>>
     */
    public static function missions(int $level, int $stage): array
    {
        $rows = array_values(array_filter(
            Mission::ROWS,
            static fn (array $row): bool => $row['missionlevel'] === $level && $row['missionstage'] === $stage
        ));
        usort($rows, static fn (array $a, array $b): int => $a['missionid'] <=> $b['missionid']);
        return $rows;
    }

    /**
     * 关卡行（missionlevel / missionstage / missionid）
     *
     * @return array<string, mixed>|null
     */
    public static function mission(int $level, int $stage, int $id): ?array
    {
        foreach (Mission::ROWS as $row) {
            if ($row['missionlevel'] === $level && $row['missionstage'] === $stage && $row['missionid'] === $id) {
                return $row;
            }
        }
        return null;
    }

    /**
     * 玩家的开启记录，按 level、id 排序
     *
     * @return list<array{id: int, uid: int, level: int, stage: int, mission: int, newresult: int, dateline: int}>
     */
    public static function rows(int $uid): array
    {
        $rows = DB::table('open_map')->select(...self::MAP_COLUMNS)->where('uid', $uid)->whereNull('deleted_at')
            ->orderBy('level')->orderBy('id')->get();
        return array_map(static fn (array $row): array => array_map('intval', $row), $rows);
    }

    /**
     * 玩家某关的攻击记录
     *
     * @return array<string, int|string>|null
     */
    private static function missionLog(int $uid, int $level, int $stage, int $id): ?array
    {
        $row = DB::table('mission_log')->select(...self::LOG_COLUMNS)->where('uid', $uid)->where('missionlevel', $level)
            ->where('missionstage', $stage)->where('missionid', $id)->whereNull('deleted_at')->first();
        if ($row === null) {
            return null;
        }
        foreach ($row as $key => $value) {
            $row[$key] = $key === 'nickname' ? (string)$value : (int)$value;
        }
        return $row;
    }

    /**
     * member_field.newresult（通关的势力id，可领通关奖励）
     */
    private static function fieldNewresult(int $uid): int
    {
        return (int)(DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->value('newresult') ?? 0);
    }

    private static function insertLevel(int $uid, int $level, int $now): void
    {
        DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_open_map` (`uid`, `level`, `stage`, `mission`, `newresult`, `dateline`)
             VALUES (:uid, :level, 1, 0, 0, :now)',
            ['uid' => $uid, 'level' => $level, 'now' => $now]
        );
    }
}
