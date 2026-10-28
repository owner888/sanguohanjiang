<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Pvp;

use Hanjiang\Handler\City\MemberHandler;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use OmniPHP\WebSocket\Broadcaster;

final class CountryHandler
{
    public const array COUNTRIES = [
        1 => ['id' => 1, 'name' => '魏国', 'leader' => '曹操', 'capital' => '洛阳', 'buff' => '全军物理防御 +5%'],
        2 => ['id' => 2, 'name' => '蜀国', 'leader' => '刘备', 'capital' => '成都', 'buff' => '全军普通攻击 +5%'],
        3 => ['id' => 3, 'name' => '吴国', 'leader' => '孙权', 'capital' => '建业', 'buff' => '全军最大生命 +5%'],
    ];

    /**
     * 国家系统主界面: c=country&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->first();
        $myCountry = (int)($member['country'] ?? 0);

        // 统计三国国力
        $stats = [];
        foreach (self::COUNTRIES as $cid => $c) {
            $memberCount = DB::table('member')->where('country', $cid)->count();
            $power = 10000 + $memberCount * 2500;
            $stats[$cid] = array_merge($c, [
                'members' => $memberCount,
                'power' => $power,
            ]);
        }

        $now = time();
        $salaryClaimed = false;
        $lastSalary = Redis::get('country_salary:' . $uid);
        if ($lastSalary !== null && date('Ymd', (int)$lastSalary) === date('Ymd', $now)) {
            $salaryClaimed = true;
        }

        return [
            'status' => 1,
            'my_country' => $myCountry,
            'country_name' => self::COUNTRIES[$myCountry]['name'] ?? '在野游侠',
            'official' => ($myCountry > 0) ? '镇军将军' : '平民',
            'salary_claimed' => $salaryClaimed ? 1 : 0,
            'countries' => $stats,
            'message' => 'ok',
        ];
    }

    /**
     * 选择国家加入阵营: c=country&m=select_country
     */
    public static function select_country(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->first();
        $curCountry = (int)($member['country'] ?? 0);

        if ($curCountry > 0) {
            return [
                'status' => 0,
                'message' => '您已效力于【' . self::COUNTRIES[$curCountry]['name'] . '】，不可叛国',
            ];
        }

        // country: 1魏, 2蜀, 3吴, 0听天由命(随机)
        $choice = (int)($ctx->input('country', $ctx->input('cid', 0)));
        $isRandom = false;

        if ($choice <= 0 || !isset(self::COUNTRIES[$choice])) {
            $choice = mt_rand(1, 3);
            $isRandom = true;
        }

        $bonusGold = $isRandom ? 100 : 0;
        $bonusSilver = $isRandom ? 10000 : 0;

        $newGold = (int)($member['gold'] ?? 0) + $bonusGold;
        $newSilver = (int)($member['silver'] ?? 0) + $bonusSilver;

        DB::table('member')->where('uid', $uid)->update([
            'country' => $choice,
            'gold' => $newGold,
            'silver' => $newSilver,
        ]);

        Redis::del('player:' . $uid);

        $countryName = self::COUNTRIES[$choice]['name'];
        $nickname = $member['nickname'] ?? ('主公' . $uid);

        // 全服通报加入阵营
        Broadcaster::broadcast('marquee', [
            'content' => "天下大势！主公【{$nickname}】" . ($isRandom ? '顺应天命' : '宣誓') . "加入【{$countryName}】，共谋中原！",
            'type' => 'system_broadcast',
        ]);

        return [
            'status' => 1,
            'country' => $choice,
            'country_name' => $countryName,
            'is_random' => $isRandom ? 1 : 0,
            'bonus_gold' => $bonusGold,
            'bonus_silver' => $bonusSilver,
            'gold' => $newGold,
            'silver' => $newSilver,
            'message' => "成功加入【{$countryName}】！" . ($isRandom ? "获得天命奖励：{$bonusGold} 元宝、{$bonusSilver} 银两！" : ''),
        ];
    }

    /**
     * 国家军资捐献: c=country&m=donate
     */
    public static function donate(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->first();
        $myCountry = (int)($member['country'] ?? 0);

        if ($myCountry <= 0) {
            return [
                'status' => 0,
                'message' => '请先选择加入魏、蜀、吴任意国家',
            ];
        }

        $type = (int)($ctx->input('type', 1)); // 1 银两捐献(10000), 2 元宝捐献(50)
        $curSilver = (int)($member['silver'] ?? 0);
        $curGold = (int)($member['gold'] ?? 0);

        if ($type === 1) {
            $cost = 10000;
            if ($curSilver < $cost) {
                return ['status' => -1, 'message' => "银两不足，捐献需要 {$cost} 银两"];
            }
            $rewardRep = 50;
            $newSilver = $curSilver - $cost;
            $newGold = $curGold;
        } else {
            $cost = 50;
            if ($curGold < $cost) {
                return ['status' => -2, 'message' => "元宝不足，捐献需要 {$cost} 元宝"];
            }
            $rewardRep = 200;
            $newSilver = $curSilver;
            $newGold = $curGold - $cost;
        }

        $newRep = (int)($member['reputation'] ?? 0) + $rewardRep;

        DB::table('member')->where('uid', $uid)->update([
            'silver' => $newSilver,
            'gold' => $newGold,
            'reputation' => $newRep,
        ]);

        Redis::del('player:' . $uid);

        return [
            'status' => 1,
            'add_reputation' => $rewardRep,
            'reputation' => $newRep,
            'silver' => $newSilver,
            'gold' => $newGold,
            'message' => "国家捐献成功！为【" . self::COUNTRIES[$myCountry]['name'] . "】建立丰功伟绩，获得声望 {$rewardRep}！",
        ];
    }

    /**
     * 领取每日国家俸禄: c=country&m=salary
     */
    public static function salary(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->first();
        $myCountry = (int)($member['country'] ?? 0);

        if ($myCountry <= 0) {
            return [
                'status' => 0,
                'message' => '未加入国家，无法领取国家俸禄',
            ];
        }

        $now = time();
        $lastSalary = Redis::get('country_salary:' . $uid);
        if ($lastSalary !== null && date('Ymd', (int)$lastSalary) === date('Ymd', $now)) {
            return [
                'status' => 0,
                'message' => '今日国家俸禄已领取，明日再来',
            ];
        }

        $level = max(1, (int)($member['level'] ?? 1));
        $rewardSilver = 3000 + $level * 500;
        $rewardRep = 50;

        $newSilver = (int)($member['silver'] ?? 0) + $rewardSilver;
        $newRep = (int)($member['reputation'] ?? 0) + $rewardRep;

        DB::table('member')->where('uid', $uid)->update([
            'silver' => $newSilver,
            'reputation' => $newRep,
        ]);

        Redis::set('country_salary:' . $uid, (string)$now, 86400 * 2);
        Redis::del('player:' . $uid);

        return [
            'status' => 1,
            'silver' => $rewardSilver,
            'reputation' => $rewardRep,
            'message' => "成功领取今日国家俸禄，获得银两 {$rewardSilver}、声望 {$rewardRep}！",
        ];
    }

    /**
     * 国家大营府库 / 俸禄与捐献仓储面板: c=country&m=storage
     */
    public static function storage(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        $myCountry = (int)($member['country'] ?? 0);

        if ($myCountry <= 0) {
            return [
                'status' => -1,
                'message' => '未加入国家，无法进入国家大营',
            ];
        }

        $now = time();
        $lastSalary = Redis::get('country_salary:' . $uid);
        $get = ($lastSalary !== null && date('Ymd', (int)$lastSalary) === date('Ymd', $now)) ? 1 : 0;

        $level = max(1, (int)($member['level'] ?? 1));
        $countryLevel = 5;

        $countryData = [
            'level' => $countryLevel,
            'exp' => 5000,
            'levelupexp' => 10000,
        ];

        $salaryData = [
            'silver' => 3000 + $level * 500,
            'act' => 15,
            'soul' => 100,
            'jade' => 10,
        ];

        $infoData = [
            'allcontribute' => (int)(Redis::get("user_country_contribute:{$uid}") ?? 2000),
            'level' => $countryLevel,
            'job' => 1,
            'rank' => 1,
            'job_name' => '大将军',
        ];

        $donateData = [
            'silver' => 10000,
            'gold' => 50,
            'contribute' => 100,
        ];

        $members = DB::table('member')
            ->where('country', $myCountry)
            ->whereNull('deleted_at')
            ->orderBy('level', 'desc')
            ->limit(10)
            ->get();

        $memberList = [];
        $rank = 1;
        foreach ($members as $m) {
            $memberList[] = [
                'uid' => (int)$m['uid'],
                'nickname' => (string)($m['nickname'] ?? ('主公' . $m['uid'])),
                'level' => (int)($m['level'] ?? 1),
                'job' => 1,
                'job_name' => ($rank === 1) ? '大将军' : '车骑将军',
                'rank' => $rank,
                'contribute' => max(100, 3000 - $rank * 200),
            ];
            $rank++;
        }

        return [
            'status' => 1,
            'country' => $countryData,
            'salary' => $salaryData,
            'info' => $infoData,
            'donate' => $donateData,
            'list' => $memberList,
            'get' => $get,
            'islock' => 0,
            'message' => 'ok',
        ];
    }

    /**
     * 获取国家成员列表: c=country&m=get_member_list
     */
    public static function get_member_list(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        $myCountry = max(1, (int)($member['country'] ?? 1));
        $countryName = self::COUNTRIES[$myCountry]['name'] ?? '魏国';
        $nickname = (string)($member['nickname'] ?? ('主公' . $uid));
        $level = (int)($member['level'] ?? 1);

        $country = [
            'id' => $myCountry,
            'name' => $countryName,
            'level' => 5,
            'member' => 18,
            'exp' => 25000,
            'levelupexp' => 50000,
            'notice' => '修明法度，征讨不臣！愿与诸君同心协力，共定中原！',
        ];

        $info = [
            'rank' => 1,
            'job' => 1,
            'make_way' => 0,
            'usurp' => 0,
            'closeday' => 0,
        ];

        $list = [
            [
                'uid' => $uid,
                'nickname' => $nickname,
                'level' => $level,
                'country' => $myCountry,
                'job' => 1,
                'job_name' => '君主',
                'contribute' => 200,
                'allcontribute' => 1500,
                'dateline' => 60,
            ],
            [
                'uid' => 8888,
                'nickname' => '常山赵子龙',
                'level' => 60,
                'country' => $myCountry,
                'job' => 2,
                'job_name' => '丞相',
                'contribute' => 300,
                'allcontribute' => 3200,
                'dateline' => 120,
            ],
            [
                'uid' => 8889,
                'nickname' => '关云长',
                'level' => 62,
                'country' => $myCountry,
                'job' => 3,
                'job_name' => '大将军',
                'contribute' => 500,
                'allcontribute' => 5000,
                'dateline' => 300,
            ],
            [
                'uid' => 8890,
                'nickname' => '燕人张翼德',
                'level' => 58,
                'country' => $myCountry,
                'job' => 4,
                'job_name' => '偏将军',
                'contribute' => 150,
                'allcontribute' => 1200,
                'dateline' => 600,
            ],
        ];

        return [
            'status' => 1,
            'country' => $country,
            'info' => $info,
            'list' => $list,
            'audit' => 0,
            'message' => 'ok',
        ];
    }

    /**
     * 获取国家排行: c=country&m=get_rank
     */
    public static function get_rank(Context $ctx): array
    {
        $page = (int)($ctx->input('page', $ctx->input('p', 1)));
        $rankList = [
            [
                'id' => 1,
                'rank' => 1,
                'name' => '魏国',
                'level' => 5,
                'member' => 18,
                'uid' => 8888,
                'nickname' => '曹操',
                'notice' => '对酒当歌，人生几何！何以解忧，唯有杜康！',
            ],
            [
                'id' => 2,
                'rank' => 2,
                'name' => '蜀国',
                'level' => 5,
                'member' => 16,
                'uid' => 8889,
                'nickname' => '刘备',
                'notice' => '勿以恶小而为之，勿以善小而不为！',
            ],
            [
                'id' => 3,
                'rank' => 3,
                'name' => '吴国',
                'level' => 4,
                'member' => 15,
                'uid' => 8890,
                'nickname' => '孙权',
                'notice' => '江东子弟多才俊，卷土重来未可知！',
            ],
        ];

        return [
            'status' => 1,
            'list' => $rankList,
            'country' => $rankList[0],
            'page' => $page,
            'perpage' => 10,
            'pages' => 1,
            'message' => 'ok',
        ];
    }
}
