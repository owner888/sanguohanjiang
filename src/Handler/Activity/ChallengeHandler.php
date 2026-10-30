<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\Pvp\ArenaHandler;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 切磋（移植旧版 controllers/challenge.php，路径相对旧版 s1/application/）
 *
 * 向任意玩家发起一场 PK（battle_model->battle_pk，与演武榜同一套组队 / 推演），不消耗、不奖励，
 * 给对方发一封演武类信件（type 4）。旧版控制器不加载 challenge_model（challenge_log 不写）。
 *
 * 客户端调用：action（miscpop.js battleByUid）。
 *
 * 依赖未移植：
 * - 信件战报 message_model->record_pk_report（pm_report，供信件回放）；信件存 Redis user_messages，info.report 为其 id；
 * - 春节送福活动 actnewyear_model（_init 读活动状态；action 里 add_task_num(version, 15) 计数）。
 */
final class ChallengeHandler
{
    /** 演武类信件（旧版 message_model->send_message type 4，发件人为发起方本人） */
    private const int MESSAGE_TYPE = 4;

    /** 旧版语言包 message_challenge_lose / win / deuce（对方视角） */
    private const string LANG_MESSAGE_CHALLENGE_LOSE = '跟您切磋了一把，你惨遭失败。';
    private const string LANG_MESSAGE_CHALLENGE_WIN = '跟您切磋了一把，你大获全胜。';
    private const string LANG_MESSAGE_CHALLENGE_DEUCE = '跟您切磋了一把，双方实力相当，本次平局。';

    /**
     * 挑战: c=challenge&m=action（uid 对方 uid）
     *
     * status：-1 没传对方 uid 或是自己；-2 对方不存在；1 成功
     */
    public static function action(Context $ctx): array
    {
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return ['status' => 0];
        }
        $uid = (int)$member['uid'];
        // 对方的id
        $touid = (int)$ctx->input('uid', 0);

        if ($touid === 0 || $touid === $uid) {
            return ['status' => -1];
        }
        // 查询对方信息
        $toMember = self::member($touid);
        if ($toMember === null) {
            return ['status' => -2];
        }

        // 调用算法（右侧 -1 没有阵法 / -3 武将信息错误时旧版不战斗，info 为空，控制器不检查照常返回）
        $battle = ArenaHandler::battlePk($uid, $touid);
        $assess = $battle['info']['assess'] ?? null;

        // 发信件（旧版 win >= 1 对方输、== 0 平局，其余对方赢；没有战斗时 win 为 null，按 == 0 平局）
        if ($assess !== null && $assess >= 1) {
            $text = self::LANG_MESSAGE_CHALLENGE_LOSE;
        } elseif ($assess === null || $assess === 0) {
            $text = self::LANG_MESSAGE_CHALLENGE_DEUCE;
        } else {
            $text = self::LANG_MESSAGE_CHALLENGE_WIN;
        }
        $message = '<em uid="' . $uid . '">' . $member['nickname'] . '</em>' . $text;
        $pmid = MessageHandler::sendSystemMessage([$touid], self::MESSAGE_TYPE, (string)$member['nickname'], $message, 0, $uid);

        // 返回数据
        $return = ['status' => 1];
        $return['info']['win'] = $assess;
        $return['battle'] = $battle['info'] ?? [];

        // 查询对方职业
        $return['info']['gender'] = (int)$toMember['gender'];

        $return['battle']['left_role'] = [
            'name' => (string)$member['nickname'],
            'level' => (int)$member['level'],
            'gender' => (int)$member['gender'],
        ];
        $return['battle']['right_role'] = [
            'name' => (string)$toMember['nickname'],
            'level' => (int)$toMember['level'],
            'gender' => (int)$toMember['gender'],
        ];

        // 依赖未移植：message_model->record_pk_report 信件战报（from / fromlevel / to / tolevel / report 落库供信件回放）
        $return['info']['report'] = $pmid;
        $return['share'] = [
            'id' => $pmid,
            'type' => 'challenge',
        ];

        // 依赖未移植：春节送福活动开启时 actnewyear_model->add_task_num(version, 15) 日常任务计数

        return $return;
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
