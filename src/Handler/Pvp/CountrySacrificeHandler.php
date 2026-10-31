<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Pvp;

use Hanjiang\Handler\City\MemberHandler;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

final class CountrySacrificeHandler
{
    public const array SACRIFICES = [
        1 => ['id' => 1, 'name' => '白马素酒平祭', 'cost_silver' => 5000, 'cost_gold' => 0, 'rep' => 50, 'blessing' => '全军生命 +100'],
        2 => ['id' => 2, 'name' => '少牢三牲太祭', 'cost_silver' => 20000, 'cost_gold' => 0, 'rep' => 150, 'blessing' => '全军物攻 +30'],
        3 => ['id' => 3, 'name' => '太牢九鼎天祭', 'cost_silver' => 0, 'cost_gold' => 50, 'rep' => 500, 'blessing' => '全军攻击/防御/生命 +5%'],
    ];

    /**
     * 宗庙祭祀主界面: c=countrysacrifice&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        $country = (int)($member['country'] ?? 0);

        $now = time();
        $claimed = false;
        $lastDate = Redis::get('country_sacrifice_date:' . $uid);
        if ($lastDate !== null && date('Ymd', (int)$lastDate) === date('Ymd', $now)) {
            $claimed = true;
        }

        return [
            'status' => 1,
            'my_country' => $country,
            'country_name' => DB::table('country')->where('id', $country)->whereNull('deleted_at')->value('name') ?? '在野游侠',
            'sacrificed_today' => $claimed ? 1 : 0,
            'list' => array_values(self::SACRIFICES),
            'message' => 'ok',
        ];
    }

    /**
     * 执行宗庙祭天祈福: c=countrysacrifice&m=action
     */
    public static function action(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $sacId = (int)($ctx->input('id', $ctx->input('type', 1)));

        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        $country = (int)($member['country'] ?? 0);

        if ($country <= 0) {
            return ['status' => 0, 'message' => '请先加入魏、蜀、吴任意国家方可参加国家祭天大典'];
        }

        if (!isset(self::SACRIFICES[$sacId])) {
            return ['status' => 0, 'message' => '祭祀规格不存在'];
        }

        $cfg = self::SACRIFICES[$sacId];
        $costSilver = (int)$cfg['cost_silver'];
        $costGold = (int)$cfg['cost_gold'];

        if (!MemberHandler::deductCurrency($uid, silver: $costSilver, gold: $costGold)) {
            return [
                'status' => -1,
                'message' => ($costGold > 0) ? "元宝不足，太牢天祭需要 {$costGold} 元宝" : "银两不足，祭祀需要 {$costSilver} 银两",
            ];
        }

        // 获得声望
        $addRep = (int)$cfg['rep'];
        MemberHandler::addCurrency($uid, reputation: $addRep);

        Redis::set('country_sacrifice_date:' . $uid, (string)time(), 86400 * 2);

        $countryName = DB::table('country')->where('id', $country)->whereNull('deleted_at')->value('name');

        // 依赖未移植：旧版祭祀完成时 chat_model->get_message_str(9, sacrificename) + send_message_to_country(…, 国家成员, 6, 1)
        // 国家聊天室公告（本玩法为新版自定，随旧版 countrysacrifice 移植）

        return [
            'status' => 1,
            'type' => $sacId,
            'add_reputation' => $addRep,
            'blessing' => $cfg['blessing'],
            'message' => "祭天大典圆满！为【{$countryName}】宗庙祈福，获得声望 {$addRep}！{$cfg['blessing']}！",
        ];
    }
}
