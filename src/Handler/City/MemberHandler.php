<?php

declare(strict_types=1);

namespace Hanjiang\Handler\City;

use Hanjiang\Handler\Activity\HiteggHandler;
use Hanjiang\Handler\General\TavernHandler;
use Hanjiang\Handler\Pvp\SiegeHandler;
use Hanjiang\MasterData\LevelGift;
use Hanjiang\MasterData\ActivityTime;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\DailyTask;
use Hanjiang\Model\Edifice;
use Hanjiang\Model\General;
use Hanjiang\Model\Logined;
use Hanjiang\Model\MainQuestTask;
use Hanjiang\Model\Member;
use Hanjiang\Model\OpenMap;
use Hanjiang\Model\OwnMatrix;
use Hanjiang\Model\WorldBoss;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

final class MemberHandler
{
    /**
     * 根据请求 token 解析当前登录 uid（对齐旧版 token_model->check_token）
     *
     * 只认登录时签发并写入 Redis 的 token；无 token / token 失效返回 0，
     * 不接受请求里的 uid 参数，也不从 token 字面解析 uid（二者都可伪造）。
     */
    public static function resolveUid(Context $ctx): int
    {
        $token = trim((string)$ctx->input('token', ''));
        if ($token === '') {
            return 0;
        }
        $uid = Redis::get('token:' . $token);
        return $uid !== null && (int)$uid > 0 ? (int)$uid : 0;
    }

    /**
     * 玩家主信息接口: c=member&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = self::resolveUid($ctx);

        // 执行连续登录处理（今天还没更新过时；连续登录天数任务 20）
        self::updateContinuousLoginDay($uid, time());
        // 累计登陆处理（活动开启时修改累计登陆次数）
        TotalLoginHandler::updateDay($uid, BossClock::now());

        // 1. 优先尝试从 Redis 缓存读取
        $cacheKey = 'player:' . $uid;
        $cached = Redis::getJson($cacheKey);
        if (is_array($cached)) {
            $cached['timestamp'] = time();
            // 世界 BOSS 开启 / 领奖、国战、国家矿可收获标识随时间变化，不走缓存
            return array_merge($cached, self::liveInfo($uid, (int)($cached['level'] ?? 0), (int)($cached['country'] ?? 0)));
        }

        // 2. 从 MySQL 数据库读取
        $member = DB::table('member')->where('uid', $uid)->first();
        $field = DB::table('member_field')->where('uid', $uid)->first() ?? [];

        if ($member === null) {
            // 如果不存在则初始化一份
            $now = time();
            $username = 'player_' . $uid;
            DB::table('member')->insert([
                'uid' => $uid,
                'username' => $username,
                'nickname' => '新主公' . $uid,
                'sex' => 1,
                'gender' => 0,
                'roleid' => 1,
                'country' => 0,
                'level' => 1,
                'exp' => 0,
                'maxexp' => 80,
                'silver' => 88888,
                'gold' => 9999,
                'reputation' => 100,
                // 旧版 member_model->insert_member：将魂包裹 4 格
                'soulpack' => 4,
                'act' => 100,
                'maxact' => 100,
                'vip' => 0,
                'bagnum' => 20,
                'guide' => 0,
                'guidestep' => 0,
                'guidestop' => 0,
                'missionsite' => 2,
                'missionlevel' => 1,
                'missionstage' => 1,
                'missionid' => 1,
            ]);
            // 初始化地图（同注册：开启第一个势力）
            OpenMap::init($uid);
            $member = DB::table('member')->where('uid', $uid)->first();
        }

        $now = time();
        $level = max(1, (int)$member['level']);
        $vip = max(0, (int)$member['vip']);
        $levelUpExp = (int)($member['maxexp'] ?? 0) > 0 ? (int)$member['maxexp'] : Member::getLevelUpExp($level);
        $actLimit = Member::getActLimit($vip);

        $data = [
            'status' => 1,
            'uid' => $uid,
            'username' => $member['username'],
            'nickname' => $member['nickname'],
            'sex' => (int)$member['sex'],
            'gender' => (int)$member['gender'],
            'roleid' => (int)$member['roleid'],
            'country' => (int)$member['country'],
            'Level' => $level,
            'level' => $level,
            'silver' => (int)$member['silver'],
            'gold' => (int)$member['gold'],
            'reputation' => (int)$member['reputation'],
            'act' => (int)$member['act'],
            'act_limit' => $actLimit,
            'maxact' => $actLimit,
            'vip' => $vip,
            'exp' => (int)$member['exp'],
            'levelupexp' => $levelUpExp,
            'maxexp' => $levelUpExp,
            'bagnum' => (int)$member['bagnum'],
            'guide' => (int)$member['guide'],
            'guidestep' => (int)$member['guidestep'],
            'guidestop' => (int)$member['guidestop'],
            'missionsite' => (int)$member['missionsite'],
            'missionlevel' => (int)$member['missionlevel'],
            'missionstage' => (int)$member['missionstage'],
            'missionid' => (int)$member['missionid'],
            'attackcd' => [
                'status' => (int)($field['attackstatus'] ?? 0),
                'cd' => 0,
            ],
            'practice' => [
                'timers' => (function() use ($field, $uid): int {
                    $practiceAll = max(2, (int)($field['practiceall'] ?? 2));
                    $practicingCount = DB::table('practice')->where('uid', $uid)->where('generalid', '>', 0)->whereNull('deleted_at')->count();
                    if ($practiceAll > $practicingCount) {
                        return 0; // 尚有空闲训练位，显示“可训练”，不倒计时
                    }
                    return max(0, (int)($field['practicetime'] ?? 0) - time()); // 剩余倒计时秒数
                })(),
            ],
            'rank' => (int)($field['rank'] ?? 0),
            'newresult' => (int)($field['newresult'] ?? 0),
            'race' => (int)($field['race'] ?? 0),
            'race_dateline' => (int)($field['race_dateline'] ?? 0),
            // 首充图标（旧版 member.php：1 显示未领奖 / 未抽转盘，2 充值后弹领奖，0 不显示；强制引导期间有图标时为 3）
            'first_recharge' => (int)$member['guidestop'] === 0 && (int)($field['first_recharge'] ?? 1) > 0
                ? 3
                : (int)($field['first_recharge'] ?? 1),
            'first_recharge_an' => 0,
            'new_role_reward' => 0,
            'opennewyearboss' => 0,
            // 世界 BOSS 开启状态（旧版 member.php:worldboss_init）
            'openboss' => 0,
            'openbossreward' => 0,
            'appreview' => 0,
            'serverhold' => '',
            'onmine' => 0,
            'oncountrymine' => 0,
            'onworkshop' => 0,
            'god_reward' => 0,
            'country_war' => 0,
            'is_saturday' => 0,
            'country_date' => '',
            'enemy_name' => '',
            'country_lock' => 0,
            'leave_cd' => 0,
            'country_job' => 0,
            'trends_id' => 0,
            'notice_id' => 0,
            'timestamp' => $now,
        ];

        // 存入 Redis 缓存，TTL 1小时
        Redis::setJson($cacheKey, $data, 3600);

        return array_merge($data, self::liveInfo($uid, $level, (int)$member['country']));
    }

    /**
     * 随时间变化的主城标识（不进玩家缓存）：世界 BOSS（worldboss_init）、国战提示（get_country_war）、
     * 银矿可收获 onmine（占矿满 4 小时）、国家矿可收获 oncountrymine（member_field.oncountrymine 到期）、玉石作坊可收获 onworkshop、点将台新武将气泡 ongeneral、新信件提示 newpm / newmail、日常任务 daily_task、
     * 主线任务 mainquest
     *
     * @return array<string, mixed>
     */
    private static function liveInfo(int $uid, int $level, int $country): array
    {
        $now = BossClock::now();
        $base = DB::table('member')->select('jointime', 'gift', 'guest')->where('uid', $uid)->whereNull('deleted_at')->first() ?? [];
        $jointime = (int)($base['jointime'] ?? 0);
        $fieldFlags = DB::table('member_field')
            ->select('oncountrymine', 'onmine', 'onworkshop', 'ongeneral', 'levytimes', 'levystatus', 'levelgift', 'rebate_dateline')
            ->where('uid', $uid)->whereNull('deleted_at')->first() ?? [];
        $onCountryMine = (int)($fieldFlags['oncountrymine'] ?? 0);
        $notice = DB::table('member')->select('newpm', 'newmail')->where('uid', $uid)->whereNull('deleted_at')->first() ?? [];
        $time = time();
        // 主线任务（member.php get_task_list；初始化锁被占等情形为空列表）
        $mainquest = MainQuestTask::taskList($uid, $time);
        // 征收倒计时（vip_model->cd_status() 恒为 1：剩余 cd 一律按 0，状态随之为 0）
        $levyCd = 0;
        // 等级礼包（levelgift_model->opentime 为 -1，任何开服时间都显示图标）：member_field.levelgift 1 显示、2 可领取；16 级以下不提醒
        $levelgift = (int)($fieldFlags['levelgift'] ?? 0);
        // 连续登陆活动的提醒（_logined：当天缓存的礼包状态，有可领取的或今天还没领过奖时显示）
        $loginedPopo = Logined::popo($uid, Logined::cache($uid, $time), $time);
        return array_merge(
            self::activityFlags($uid, $level, (int)($fieldFlags['rebate_dateline'] ?? 0), $now),
            [
                // 征收信息（状态，次数，时间）
                'levy' => [
                    'times' => (int)($fieldFlags['levytimes'] ?? 0),
                    'cd' => $levyCd,
                    'status' => $levyCd === 0 ? 0 : (int)($fieldFlags['levystatus'] ?? 0),
                ],
                'levelgift' => $levelgift >= 1 ? 1 : 0,
                'levelgift_popo' => $levelgift === 2 && $level >= 16 ? 1 : 0,
                'logined_popo' => $loginedPopo,
                'activity_popo' => $loginedPopo,
                // 主城建筑开启状态（edifice_model->get_open_status）
                'edifice' => Edifice::openStatus($uid, $level),
            ],
            WorldBoss::memberInit($uid, $level, $now),
            SiegeHandler::countryWar(['uid' => $uid, 'country' => $country], $now),
            // 判断矿区是否可收获（占矿满 4 小时）
            ['onmine' => (int)($fieldFlags['onmine'] ?? 0) > 0 && $time - (int)$fieldFlags['onmine'] >= 60 * 60 * 4 ? 1 : 0],
            // 判断国家矿是否可收获
            ['oncountrymine' => $onCountryMine > 0 && $now >= $onCountryMine ? 1 : 0],
            // 判断玉石作坊是否可收获（最早装满的采集位时间）
            ['onworkshop' => (int)($fieldFlags['onworkshop'] ?? 0) > 0 && $time >= (int)$fieldFlags['onworkshop'] ? 1 : 0],
            // 判断点将台是否有新武将（获得武将时置 1，进入点将台时清 0）
            ['ongeneral' => (int)($fieldFlags['ongeneral'] ?? 0) ? 1 : 0],
            [
                // 老版主线礼包（_get_gift_info：25 级以下按已领个数取下一个，通关条件看 mission_log）
                'gift' => self::giftInfo($uid, $level, (int)($base['gift'] ?? 0)),
                // 试玩用户（1 显示绑定送元宝入口，regreward）
                'guest' => (int)($base['guest'] ?? 0),
                // 砸蛋活动（有可免费砸的蛋时为 1）
                'hitegg' => HiteggHandler::init($uid, $jointime, $time),
            ],
            // 新服礼包活动
            NewgiftHandler::memberInfo($uid, $jointime, $time),
            [
                // 新信件提示（旧版 member.newpm / newmail）
                'newpm' => (int)($notice['newpm'] ?? 0),
                'newmail' => (int)($notice['newmail'] ?? 0),
                // 日常任务（41 级以上：task_model->get_action_task_info）
                'daily_task' => $level >= DailyTask::OPEN_LEVEL ? DailyTask::actionTaskInfo($uid, $time) : ['status' => 0],
                'mainquest' => $mainquest['status'] === 1 ? $mainquest['list'] : [],
            ],
        );
    }

    /**
     * 运营活动开启状态（member.php index：活动表每一行输出 {name} 与 {name}_type，在开始结束时间内为 1）
     *
     * 照旧版的特例：返利 rebate 首充过（member.rebate_firstpay = 1）为 0；战神殿 god 50 级以下为 0；跳楼返利 jump_rebate
     * 达到返利标准后 30 天内为 1；寻宝 acttreasure 50 级以下为 0；春节送福 act_newyear 带版本号 act_newyear_version。
     * 依赖未移植（节日 / 跨服活动，旧版数据中都已结束）：貔貅聚财 actenrichment 结束前 7 天的未领奖判断、春节 boss 开启状态
     * opennewyearboss、寻宝 / 清明 / 转盘抽奖的 VIP 同步与 acttreasure_popo；旧版循环后写死的战神殿 god = 1 与群服争霸 gvg（跨服未移植，不显示）。
     *
     * @return array<string, int>
     */
    private static function activityFlags(int $uid, int $level, int $rebateDateline, int $now): array
    {
        $return = [];
        $rebateFirstpay = (int)(DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('rebate_firstpay') ?? 0);
        foreach (ActivityTime::ROWS as $val) {
            $name = $val['name'];
            $return[$name . '_type'] = (int)$val['type'];
            $return[$name] = $val['starttime'] <= $now && $now <= $val['endtime'] ? 1 : 0;
            // 春节送福活动
            if ($name === 'act_newyear') {
                $return[$name . '_version'] = (int)$val['version'];
            }
            if ($name === 'rebate' && $rebateFirstpay === 1) {
                $return[$name] = 0;
            } elseif ($name === 'god' && $level < 50) {
                $return[$name] = 0;
            }
            // 跳楼返利活动，如奖励未完全领取，30天才消失
            if ($name === 'jump_rebate' && $now < strtotime(date('y-m-d', $rebateDateline)) + 86400 * 30) {
                $return[$name] = 1;
            }
            // 寻宝活动
            if ($name === 'acttreasure' && $level < 50) {
                $return[$name] = 0;
            }
        }
        return $return;
    }

    /**
     * 处理连续登录天数，并完成连续登录天数任务（member.php _update_continuous_login_day）
     *
     * 昨天更新过为连续登录（天数 + 1），否则重置为 1；以旧的更新时间条件更新，同一天只改一次。成功后主线任务 20 记连续登录天数。
     */
    public static function updateContinuousLoginDay(int $uid, int $now): void
    {
        $field = DB::table('member_field')->select('login_day', 'login_dateline')->where('uid', $uid)->whereNull('deleted_at')->first();
        if ($field === null) {
            return;
        }
        $dateline = (int)$field['login_dateline'];
        if (date('ymd', $dateline) === date('ymd', $now)) {
            // 登录天数已修改过
            return;
        }
        // 连续登录 / 非连续登录（天数重置）
        $day = date('ymd', $dateline) === date('ymd', $now - 86400) ? (int)$field['login_day'] + 1 : 1;
        $status = DB::table('member_field')->where('uid', $uid)->where('login_dateline', $dateline)->whereNull('deleted_at')
            ->update(['login_day' => $day, 'login_dateline' => $now]);
        if ($status > 0) {
            // 调用完成任务的方法
            MainQuestTask::action($uid, 20, $day);
        }
    }

    /**
     * 选角/创角: c=member&m=select_role
     */
    public static function select_role(Context $ctx): array
    {
        $uid = self::resolveUid($ctx);
        $sex = (int)($ctx->input('sex', 1));
        $nickname = trim((string)($ctx->input('name', $ctx->input('nickname', '天命主公'))));

        // 更新 MySQL 数据库
        DB::table('member')->where('uid', $uid)->update([
            'gender' => $sex > 0 ? $sex : 1,
            'sex' => $sex > 0 ? $sex : 1,
            'nickname' => $nickname,
        ]);

        // 删除旧 Redis 缓存，强制下次刷新
        Redis::del('player:' . $uid);

        return [
            'status' => 1,
            'message' => '角色创建成功',
            'roleid' => 1,
            'nickname' => $nickname,
            'Level' => 1,
        ];
    }

    /**
     * 获取新手引导步数: c=member&m=get_guidestep
     */
    public static function get_guidestep(Context $ctx): array
    {
        $uid = self::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->first();

        return [
            'status' => 1,
            'step' => (int)($member['guidestep'] ?? 0),
            'stop' => (int)($member['guidestop'] ?? 0),
        ];
    }

    /**
     * 更新新手引导步数: c=member&m=updateguidestep
     */
    public static function updateguidestep(Context $ctx): array
    {
        $uid = self::resolveUid($ctx);
        $step = (int)($ctx->input('step', 0));

        DB::table('member')->where('uid', $uid)->update([
            'guidestep' => $step,
        ]);

        // 同步清理 Redis 缓存
        Redis::del('player:' . $uid);

        return [
            'status' => 1,
            'step' => $step,
        ];
    }

    /**
     * 更新推图位置: c=member&m=updatemissionsite（site：1 世界、2 势力、3 关卡；新手引导调用）
     *
     * 移植旧版 controllers/member.php:updatemissionsite（update_member(['missionsite' => site])），恒返回 status 1。
     */
    public static function updatemissionsite(Context $ctx): array
    {
        $uid = self::resolveUid($ctx);
        $site = $ctx->input('site');
        DB::table('member')->where('uid', $uid)->whereNull('deleted_at')
            ->update(['missionsite' => is_scalar($site) ? (int)$site : 0]);
        Redis::del('player:' . $uid);
        return ['status' => 1];
    }

    /**
     * 重置当前账号进度至 1 级初始新手状态: c=member&m=reset_account
     */
    public static function reset_account(Context $ctx): array
    {
        $uid = self::resolveUid($ctx);

        if ($uid > 0) {
            // 1. 重置 member 基础字段
            DB::table('member')->where('uid', $uid)->update([
                'level' => 1,
                'exp' => 0,
                'maxexp' => 80,
                'silver' => 88888,
                'gold' => 9999,
                'reputation' => 100,
                'act' => 100,
                'maxact' => 100,
                'missionsite' => 2,
                'missionlevel' => 1,
                'missionstage' => 1,
                'missionid' => 1,
                'guide' => 0,
                'guidestep' => 0,
                'guidestop' => 0,
            ]);

            // 2. 清空并重建关联动态数据
            DB::table('open_map')->where('uid', $uid)->delete();
            DB::table('mission_log')->where('uid', $uid)->delete();
            DB::table('equipment')->where('uid', $uid)->delete();
            DB::table('general')->where('uid', $uid)->delete();
            DB::table('matrix')->where('uid', $uid)->delete();

            // 3. 重新发放旧版初始武将（init_general），不送初始装备
            $genId = General::initGeneral($uid);

            // 旧版 init_matrix(1, uid, 2, gid)：初始武将放在 1 号阵法 2 号位，并启用 1 号阵法
            OwnMatrix::init($uid, OwnMatrix::INITIAL_MID, OwnMatrix::INITIAL_POSITION, $genId);
            // 重新开启第一个势力（同注册 add_map_log）
            OpenMap::init($uid);
            DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->update(['matrixid' => OwnMatrix::INITIAL_MID]);

            // 4. 清空 Redis 缓存
            Redis::del('player:' . $uid);
            Redis::del("chat_last_read:{$uid}");
        }

        return [
            'status' => 1,
            'message' => '账号重置成功，已重回1级初始新手状态',
            'msg' => '账号重置成功，已重回1级初始新手状态',
        ];
    }

    /**
     * 开启老版主线礼包: c=member&m=open_gift（旧版 controllers/member.php:open_gift）
     *
     * 礼包取 MasterData LevelGift（level_gift 表），member.gift 为已领取个数；下一个礼包（id = gift + 1）的条件类型 1 为
     * 击败 cond_value 关卡（OpenMap::missionStatus）。奖励 type：1 元宝、2 银币、3 军令、4 ~ 6 蓝 / 金 / 紫宝石。
     * next 为再下一个礼包（带 open）。状态码：-5 添加宝石失败，-4 修改用户信息失败，-3 未知类型，-2 礼包不存在，
     * -1 获取礼包信息失败，0 失败，1 成功。
     * 与旧版的差别：在 Member::locked（玩家行锁）里执行，以 gift 条件更新防并发重复领取；货币用原子加（旧版按读到的余额写回）。
     */
    public static function open_gift(Context $ctx): array
    {
        $uid = self::resolveUid($ctx);
        return Member::locked($uid, static function () use ($uid): array {
            $return = ['status' => 0, 'next' => 0];
            $member = DB::table('member')->select('gift')->where('uid', $uid)->whereNull('deleted_at')->first();
            if ($member === null) {
                $return['status'] = -1;
                return $return;
            }
            $giftNum = (int)$member['gift'] + 1;
            $gift = [];
            $next = 0;
            foreach (LevelGift::ROWS as $value) {
                if ($giftNum === $value['id'] && $value['cond_type'] === 1 && OpenMap::missionStatus($uid, $value['cond_value']) === 1) {
                    $gift = $value;
                }
                if ($giftNum + 1 === $value['id']) {
                    $next = $value;
                    $next['open'] = 0;
                    if ($value['cond_type'] === 1) {
                        $next['open'] = OpenMap::missionStatus($uid, $value['cond_value']);
                    }
                }
            }
            if ($gift === []) {
                // 礼包不存在
                $return['status'] = -2;
                return $return;
            }
            if (!in_array($gift['type'], [1, 2, 3, 4, 5, 6], true)) {
                // 未知类型
                $return['status'] = -3;
                return $return;
            }
            $affected = DB::table('member')->where('uid', $uid)->where('gift', $giftNum - 1)->whereNull('deleted_at')->update(['gift' => $gift['id']]);
            if ($affected === 0) {
                // 获取奖励失败
                $return['status'] = -4;
                return $return;
            }
            Redis::del('player:' . $uid);
            switch ($gift['type']) {
                case 1:
                    Member::addCurrency($uid, gold: $gift['num']);
                    $return['all_gold'] = (int)DB::table('member')->where('uid', $uid)->value('gold');
                    break;
                case 2:
                    Member::addCurrency($uid, silver: $gift['num']);
                    $return['all_silver'] = (int)DB::table('member')->where('uid', $uid)->value('silver');
                    break;
                case 3:
                    Member::addCurrency($uid, act: $gift['num']);
                    $return['all_act'] = (int)DB::table('member')->where('uid', $uid)->value('act');
                    break;
                default:
                    // 蓝 / 金 / 紫翡翠（宝石）
                    TavernHandler::addJade($uid, $gift['type'] - 3, $gift['num'], 'member', 'open_gift');
                    break;
            }
            $return['next'] = $next;
            $return['status'] = 1;
            return $return;
        });
    }

    /**
     * 主城的老版主线礼包（_get_gift_info：25 级以上返回空礼包；否则 id = 已领个数 + 1 的礼包，条件类型 1 带 open；没有为 0）
     *
     * @return array<string, mixed>|int
     */
    private static function giftInfo(int $uid, int $level, int $gift): array|int
    {
        if ($level >= 25) {
            return ['id' => 0, 'title' => '', 'open' => 0, 'description' => '', 'cond_type' => 0, 'cond_value' => 0, 'type' => 0, 'num' => 0];
        }
        $result = LevelGift::ROWS[$gift + 1] ?? null;
        if ($result === null) {
            return 0;
        }
        if ($result['cond_type'] === 1) {
            $result['open'] = OpenMap::missionStatus($uid, $result['cond_value']);
        }
        return $result;
    }

    /**
     * 原子安全扣除玩家货币/资源 (委托至 Member 领域模型)
     */
    public static function deductCurrency(int $uid, int $silver = 0, int $gold = 0, int $act = 0, int $goldType = 1): bool
    {
        return Member::deductCurrency($uid, $silver, $gold, $act, $goldType);
    }

    /**
     * 原子增加玩家货币/资源 (委托至 Member 领域模型)
     */
    public static function addCurrency(int $uid, int $silver = 0, int $gold = 0, int $act = 0, int $reputation = 0, int $soul = 0): void
    {
        Member::addCurrency($uid, $silver, $gold, $act, $reputation, $soul);
    }
}
