<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\Pvp\CountryHandler;
use Hanjiang\Model\Banquet;
use Hanjiang\Model\Member;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 国宴（移植旧版 controllers/banquet.php，存储见 Model\Banquet）
 *
 * 国家 20 级、主公 50 级开启。玩家建队（小型 / 大型 / 国宴，人数与元宝见 MasterData CountryBanquet），国家成员加入；
 * 小型宴会人满时最后加入的人直接开宴（status 4），其余由队长开宴（action，type 1 霸王宴多花 10 元宝、不要求满员）。
 * 开宴时队长领银币与国家贡献（大型 / 国宴 200%，君主开国宴 300%），队员状态改为已完成，下次打开国宴时领奖励（index）。
 * 每人每天 3 次。客户端 countrybanquetpop.js 调用 index（type 1 刷新）、create_team（id）、join_team（id）、kickout_team（uid）、
 * dismiss_team、getout_team、action（type）。
 *
 * 同一国家的组队操作在国家行锁里串行执行（Banquet::locked）。
 * 依赖未移植：国家计划任务标志位 country_model->set_flog（消费方 cron_country 计划任务未移植）；
 * 春节送福活动计数 actnewyear_model->add_task_num(version, 22)（节日活动，旧版数据中已结束）。
 */
final class BanquetHandler
{
    /** 霸王宴价格 */
    private const int PRICE = 10;

    /** 主公开启等级 */
    private const int LEVEL = 50;

    /** 国家开启等级 */
    private const int COUNTRY_LEVEL = 20;

    /**
     * 获取国宴信息: c=banquet&m=index（type 1 为刷新，不返回 price / banquet）
     */
    public static function index(Context $ctx): array
    {
        $type = (int)$ctx->input('type', 0);
        return self::run($ctx, ['status' => 0, 'team' => [], 'list' => [], 'banquet' => []], static function (array $member, array $return) use ($type): array {
            $uid = (int)$member['uid'];
            $country = (int)$member['country'];
            $now = time();
            // 获取用户数据
            $memberInfo = Banquet::info($uid, $now);
            unset($memberInfo['dateline']);
            // 宴会列表
            $banquet = Banquet::banquet((int)$member['level']);
            if ($memberInfo['status'] === 1) {
                // 取我的队伍信息
                $return['team'] = Banquet::teamInfo($country, $memberInfo['team']);
                // 判断队伍是否正常
                $isTeam = false;
                foreach ($return['team']['list'] ?? [] as $val) {
                    if ((int)$val['uid'] === $uid) {
                        $isTeam = true;
                    }
                }
                if ($return['team'] === [] || !$isTeam) {
                    // 如果队伍为空，或者自己没有在队伍里面，把状态改成空闲
                    $memberInfo['status'] = 2;
                    Banquet::updateTeam([$uid]);
                } else {
                    $return['team']['now_number'] = count($return['team']['list']);
                }
            } elseif ($memberInfo['status'] === 3) {
                // 查询我的国家信息
                if (CountryHandler::memberCountry($uid) === null) {
                    $return['status'] = -2;
                    return $return;
                }
                $addSilver = $banquet[$memberInfo['type'] - 1]['silver'];
                $addContribute = $banquet[$memberInfo['type'] - 1]['contribute'];
                // 如果状态是已完成，就获取奖励，修改状态
                Member::addCurrency($uid, silver: $addSilver);
                // 添加国家贡献、增加国家经验、记录贡献log
                CountryHandler::addContribute($member, $addContribute, 4, 'banquet', 'index');
                $return['add_silver'] = $addSilver;
                $return['add_contribute'] = $addContribute;
                $return['all_silver'] = (int)self::member($uid)['silver'];
                // 修改我的状态，减少次数
                Banquet::updateTeam([$uid]);
                Banquet::subTimes($uid, $memberInfo['times'] - 1);
                $memberInfo['times'] -= 1;
            } elseif ($memberInfo['status'] === 2) {
                // 把状态改成空闲
                Banquet::updateTeam([$uid]);
            }
            $return['status'] = 1;
            $return['info'] = $memberInfo;
            if ($type !== 1) {
                $return['price'] = self::PRICE;
                $return['banquet'] = $banquet;
            }
            $return['list'] = self::group($country);
            return $return;
        });
    }

    /**
     * 创建一个队伍: c=banquet&m=create_team（id 宴会类型 1 ~ 3）
     */
    public static function create_team(Context $ctx): array
    {
        $id = (int)$ctx->input('id', 0);
        return self::run($ctx, ['status' => 0], static function (array $member, array $return) use ($id): array {
            $uid = (int)$member['uid'];
            // 判断参数是否正常
            if (!in_array($id, [1, 2, 3], true)) {
                $return['status'] = -1;
                return $return;
            }
            // 取我的信息，判断自己是否是空闲状态
            $memberInfo = Banquet::memberInfo($uid) ?? ['status' => 0, 'times' => 0, 'team' => 0];
            if ($memberInfo['status'] === 1 || $memberInfo['status'] === 3) {
                $return['status'] = -2;
                return $return;
            }
            // 判断我的剩余次数（旧版没有记录时次数按 0）
            if ($memberInfo['times'] === 0) {
                $return['status'] = -6;
                return $return;
            }
            // 取该类型的宴会信息
            $banquet = Banquet::banquet((int)$member['level'], $id);
            if ($banquet === []) {
                $return['status'] = -4;
                return $return;
            }
            if (self::contributeLimited($member, $banquet)) {
                // 新加入国家7天内贡献不能超过100
                $return['status'] = -8;
                return $return;
            }
            // 判断金币是否足够
            if ((int)$member['gold'] < $banquet['sub_gold']) {
                $return['status'] = -5;
                return $return;
            }
            // 创建队伍
            $team = Banquet::createTeam($member, $banquet);
            $return['status'] = 1;
            $return['team'] = $team;
            $return['team']['now_number'] = count($team['list']);
            $return['list'] = self::group((int)$member['country']);
            return $return;
        });
    }

    /**
     * 踢出队伍: c=banquet&m=kickout_team（uid 被踢的玩家，队长才能踢）
     */
    public static function kickout_team(Context $ctx): array
    {
        $kick = (int)$ctx->input('uid', 0);
        return self::run($ctx, [], static function (array $member, array $return) use ($kick): array {
            $country = (int)$member['country'];
            $memberInfo = Banquet::memberInfo((int)$member['uid']);
            $team = self::captainTeam($member, $memberInfo, $return);
            if ($team === null) {
                return $return;
            }
            // 删除该用户
            $del = Banquet::gotoutTeam($country, $memberInfo['team'], $kick);
            if ($del['status'] === -1) {
                // 队伍已解散
                $return['status'] = -3;
            } elseif ($del['status'] === -2) {
                // 对方未加入该队伍
                $return['status'] = -5;
            } elseif ($del['status'] === 1) {
                $return['status'] = 1;
                $return['team'] = $del['team'];
                $return['team']['now_number'] = count($del['team']['list']);
            }
            return $return;
        });
    }

    /**
     * 解散队伍: c=banquet&m=dismiss_team（队长）
     */
    public static function dismiss_team(Context $ctx): array
    {
        return self::run($ctx, [], static function (array $member, array $return): array {
            $country = (int)$member['country'];
            $memberInfo = Banquet::memberInfo((int)$member['uid']);
            if (self::captainTeam($member, $memberInfo, $return) === null) {
                return $return;
            }
            // 执行解散
            Banquet::dismissTeam($country, $memberInfo['team'], (int)$member['uid']);
            $return['status'] = 1;
            $return['list'] = self::group($country);
            return $return;
        });
    }

    /**
     * 离开队伍: c=banquet&m=getout_team
     */
    public static function getout_team(Context $ctx): array
    {
        return self::run($ctx, [], static function (array $member, array $return): array {
            $country = (int)$member['country'];
            // 取自己的信息
            $memberInfo = Banquet::memberInfo((int)$member['uid']);
            if ($memberInfo === null) {
                $return['status'] = -1;
                return $return;
            }
            // 判断自己是否加入队伍
            if ($memberInfo['status'] !== 1) {
                $return['status'] = -2;
                return $return;
            }
            // 执行退队
            Banquet::gotoutTeam($country, $memberInfo['team'], (int)$member['uid']);
            // 退出成功
            $return['status'] = 1;
            $return['list'] = self::group($country);
            return $return;
        });
    }

    /**
     * 加入队伍: c=banquet&m=join_team（id 队伍 id）
     */
    public static function join_team(Context $ctx): array
    {
        $id = (int)$ctx->input('id', 0);
        return self::run($ctx, ['status' => 0], static function (array $member, array $return) use ($id): array {
            $uid = (int)$member['uid'];
            $country = (int)$member['country'];
            // 取自己的信息
            $memberInfo = Banquet::memberInfo($uid);
            if ($memberInfo === null) {
                $return['status'] = -1;
                return $return;
            }
            // 判断自己是否是空闲状态
            if ($memberInfo['status'] === 1 || $memberInfo['status'] === 3) {
                $return['status'] = -2;
                return $return;
            }
            // 取该队伍信息
            $team = Banquet::teamInfo($country, $id);
            if ($team === []) {
                $return['status'] = -3;
                return $return;
            }
            // 取该队伍的宴会信息
            $banquet = Banquet::banquet((int)$member['level'], (int)$team['type']);
            if ($banquet === []) {
                $return['status'] = -4;
                return $return;
            }
            if (self::contributeLimited($member, $banquet)) {
                // 新加入国家7天内贡献不能超过100
                $return['status'] = -8;
                return $return;
            }
            // 判断该队伍人数
            if (count($team['list']) >= $banquet['number']) {
                $return['status'] = -5;
                return $return;
            }
            // 判断我的剩余次数
            if ($memberInfo['times'] === 0) {
                $return['status'] = -6;
                return $return;
            }
            // 自己加入宴会，人数刚满，并且是小型宴会，就直接开始宴会
            if (count($team['list']) + 1 === $banquet['number'] && (int)$team['type'] === 1) {
                // 查询我的国家信息
                if (CountryHandler::memberCountry($uid) === null) {
                    $return['status'] = -7;
                    return $return;
                }
                // 添加奖励
                Member::addCurrency($uid, silver: $banquet['silver']);
                // 添加国家贡献、增加国家经验、记录贡献log
                CountryHandler::addContribute($member, $banquet['contribute'], 4, 'banquet', 'join_team');
                // 删除队伍，修改自己的队伍状态，减少次数
                Banquet::delTeam($country, $id, $uid);
                Banquet::updateTeam([$uid]);
                Banquet::subTimes($uid, $memberInfo['times'] - 1);
                // 依赖未移植：更新计划任务标志位 country_model->set_flog
                $return['status'] = 4;
                $return['times'] = $memberInfo['times'] - 1;
                $return['silver'] = $banquet['silver'];
                $return['all_silver'] = (int)self::member($uid)['silver'];
                $return['contribute'] = $banquet['contribute'];
                $return['list'] = self::group($country);
            } else {
                // 执行加入队伍操作
                $newTeam = Banquet::joinTeam($member, $id);
                if ($newTeam !== []) {
                    // 加入成功
                    $return['status'] = 1;
                    $return['team'] = $newTeam;
                    $return['team']['now_number'] = count($newTeam['list']);
                    $return['list'] = self::group($country);
                }
            }
            return $return;
        });
    }

    /**
     * 开始宴会: c=banquet&m=action（type 1 霸王宴：多花 10 元宝，不要求满员；队长）
     */
    public static function action(Context $ctx): array
    {
        $type = (int)$ctx->input('type', 0);
        return self::run($ctx, [], static function (array $member, array $return) use ($type): array {
            $uid = (int)$member['uid'];
            $country = (int)$member['country'];
            $memberInfo = Banquet::memberInfo($uid);
            $team = self::captainTeam($member, $memberInfo, $return);
            if ($team === null) {
                return $return;
            }
            // 取队伍的宴会信息
            $banquet = Banquet::banquet((int)$member['level'], $memberInfo['type']);
            if ($banquet === []) {
                $return['status'] = -5;
                return $return;
            }
            // 判断金币是否足够
            $subGold = $type === 1 ? $banquet['sub_gold'] + self::PRICE : $banquet['sub_gold'];
            if ((int)$member['gold'] < $subGold) {
                $return['status'] = -6;
                return $return;
            }
            // 判断队伍人数是否足够
            if ($type !== 1 && count($team['list']) < $banquet['number']) {
                $return['status'] = -7;
                return $return;
            }
            // 查询我的国家信息
            $country2 = CountryHandler::memberCountry($uid);
            if ($country2 === null) {
                $return['status'] = -8;
                return $return;
            }
            // 判断该宴会类型（大型宴会 和 国宴 奖励 200%, 君主 + 国宴 奖励 300%）
            if ($banquet['id'] > 1) {
                if ((int)$country2['job'] === 1 && $banquet['id'] === 3) {
                    $addition = 300;
                    $addSilver = $banquet['silver'] * 3;
                    $addContribute = $banquet['contribute'] * 3;
                } else {
                    $addition = 200;
                    $addSilver = $banquet['silver'] * 2;
                    $addContribute = $banquet['contribute'] * 2;
                }
            } else {
                $addition = 0;
                $addSilver = $banquet['silver'];
                $addContribute = $banquet['contribute'];
            }
            // 扣除金币
            if ($banquet['sub_gold'] > 0) {
                Member::deductCurrency($uid, gold: $banquet['sub_gold']);
            }
            // 霸王宴扣金币
            if ($type === 1) {
                Member::deductCurrency($uid, gold: self::PRICE);
            }
            // 减少次数
            Banquet::subTimes($uid, $memberInfo['times'] - 1);
            // 队长添加奖励
            Member::addCurrency($uid, silver: $addSilver);
            // 添加国家贡献、增加国家经验、记录贡献log
            CountryHandler::addContribute($member, $addContribute, 4, 'banquet', 'action');
            // 删除队伍，修改队长的队伍状态
            Banquet::delTeam($country, $memberInfo['team'], $uid);
            Banquet::updateTeam([$uid]);
            // 依赖未移植：更新计划任务标志位 country_model->set_flog；春节送福活动计数 add_task_num(version, 22)
            $after = self::member($uid);
            $return['status'] = 1;
            $return['add_silver'] = $addSilver;
            $return['all_silver'] = (int)$after['silver'];
            $return['add_contribute'] = $addContribute;
            $return['sub_gold'] = $banquet['sub_gold'];
            $return['all_gold'] = (int)$after['gold'];
            $return['addition'] = $addition;
            $return['list'] = self::group($country);
            $return['times'] = $memberInfo['times'] - 1;
            return $return;
        });
    }

    /**
     * 公共前置：功能开启（国家 20 级，status 2）、主公 50 级（status 3），然后在国家行锁里执行
     *
     * @param array<string, mixed> $return
     * @param callable(array<string, mixed>, array<string, mixed>): array<string, mixed> $callback
     * @return array<string, mixed>
     */
    private static function run(Context $ctx, array $return, callable $callback): array
    {
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        $country = (int)$member['country'];
        // 判断是否开启
        $info = $country > 0 ? CountryHandler::countryInfo($country) : null;
        if ($info === null || (int)$info['level'] < self::COUNTRY_LEVEL) {
            $return['status'] = 2;
            return $return;
        }
        // 判断自己等级是否足够
        if ((int)$member['level'] < self::LEVEL) {
            $return['status'] = 3;
            return $return;
        }
        $result = Banquet::locked($country, static fn (): array => $callback($member, $return));
        Redis::del('player:' . (int)$member['uid']);
        return $result;
    }

    /**
     * 队长操作的前置检查（kickout / dismiss / action）：有记录（-1）、已加入队伍（-2）、队伍存在（-3）、是队长（-4）
     *
     * @param array<string, mixed> $member
     * @param array<string, int>|null $memberInfo
     * @param array<string, mixed> $return
     * @return array<string, mixed>|null 队伍；检查不通过为 null（$return['status'] 已设）
     */
    private static function captainTeam(array $member, ?array $memberInfo, array &$return): ?array
    {
        if ($memberInfo === null) {
            $return['status'] = -1;
            return null;
        }
        if ($memberInfo['status'] !== 1) {
            $return['status'] = -2;
            return null;
        }
        $team = Banquet::teamInfo((int)$member['country'], $memberInfo['team']);
        if ($team === []) {
            // 队伍已解散
            $return['status'] = -3;
            return null;
        }
        if ((int)$member['uid'] !== (int)$team['caption']) {
            $return['status'] = -4;
            return null;
        }
        return $team;
    }

    /**
     * 新加入国家 7 天内（VIP 0）贡献不能超过 100；不在国家也按超限（status -8）
     *
     * @param array<string, mixed> $member
     * @param array<string, mixed> $banquet
     */
    private static function contributeLimited(array $member, array $banquet): bool
    {
        $mc = CountryHandler::memberCountry((int)$member['uid']);
        return $mc === null || ((int)$member['vip'] === 0 && time() - (int)$mc['joindateline'] < 7 * 86400
            && (int)$mc['contribute'] + $banquet['contribute'] > 100);
    }

    /**
     * 获取队伍列表（get_group：去掉成员列表，带当前人数）
     *
     * @return list<array<string, mixed>>
     */
    private static function group(int $country): array
    {
        $group = Banquet::teamList($country);
        foreach ($group as $key => $val) {
            $group[$key]['now_number'] = count($val['list']);
            unset($group[$key]['list']);
        }
        return $group;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function member(int $uid): ?array
    {
        return $uid > 0 ? DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first() : null;
    }
}
