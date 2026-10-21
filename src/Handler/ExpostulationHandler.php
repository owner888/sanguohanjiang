<?php

declare(strict_types=1);

namespace Hanjiang\Handler;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

final class ExpostulationHandler
{
    public const array PETITIONS = [
        1 => ['id' => 1, 'official' => '御史中丞', 'title' => '屯田兴农疏', 'content' => '请令各郡军士屯田积谷，以足军食。', 'reward_silver' => 8000, 'merit' => 20],
        2 => ['id' => 2, 'name' => '兵部尚书', 'title' => '严明行伍表', 'content' => '请申明军令，赏罚分明，以振军威。', 'reward_silver' => 12000, 'merit' => 30],
        3 => ['id' => 3, 'official' => '尚书令', 'title' => '轻徭薄赋论', 'content' => '请宽恤万民，减免商税，以收民心。', 'reward_silver' => 15000, 'merit' => 50],
    ];

    /**
     * 谏言折子主界面: c=expostulation&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $merit = (int)(Redis::get('expostulation_merit:' . $uid) ?: 0);
        $adopted = Redis::getJson('expostulation_adopted:' . $uid) ?: [];

        $list = [];
        foreach (self::PETITIONS as $id => $p) {
            $list[$id] = array_merge($p, [
                'adopted' => in_array($id, $adopted, true) ? 1 : 0,
            ]);
        }

        return [
            'status' => 1,
            'my_merit' => $merit,
            'list' => array_values($list),
            'message' => 'ok',
        ];
    }

    /**
     * 批阅采纳奏折: c=expostulation&m=adopt
     */
    public static function adopt(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $pid = (int)($ctx->input('id', $ctx->input('petition_id', 1)));

        if (!isset(self::PETITIONS[$pid])) {
            return ['status' => 0, 'message' => '奏折不存在'];
        }

        $adopted = Redis::getJson('expostulation_adopted:' . $uid) ?: [];
        if (in_array($pid, $adopted, true)) {
            return ['status' => 0, 'message' => '该谏言奏折已被主公准奏采纳'];
        }

        $p = self::PETITIONS[$pid];
        $rewardSilver = (int)$p['reward_silver'];
        $addMerit = (int)$p['merit'];

        MemberHandler::addCurrency($uid, silver: $rewardSilver, reputation: $addMerit);

        $curMerit = (int)(Redis::get('expostulation_merit:' . $uid) ?: 0);
        Redis::set('expostulation_merit:' . $uid, (string)($curMerit + $addMerit), 86400 * 30);

        $adopted[] = $pid;
        Redis::setJson('expostulation_adopted:' . $uid, $adopted, 86400 * 30);

        return [
            'status' => 1,
            'id' => $pid,
            'title' => $p['title'],
            'silver' => $rewardSilver,
            'merit' => $curMerit + $addMerit,
            'message' => "主公御笔朱批：准奏！推行【{$p['title']}】，获得国库银两 {$rewardSilver}、治国功绩 {$addMerit}！",
        ];
    }
}
