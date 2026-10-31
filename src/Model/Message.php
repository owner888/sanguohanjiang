<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;

/**
 * 信件（移植旧版 models/message_model.php）
 *
 * 存储同旧版：
 * - pm：信件（气泡信件与邮件），type 1 系统通知、2 vip 充值通知、3 夺矿、4 演武榜 / 切磋 / 群雄逐鹿、5 玩家互动、6 旧 GM 信件、
 *   7 国家通知、8 跨服远征、9 海外贸易、10 国家信件、11 国家矿区；status 0 正常 / 1 已回复 / 2 发信方删除 / 3 收信方删除；
 *   附件 gold / silver / act / barcard，giftstatus 1 为已领取（或没有附件）；report 1 为战报信件。
 * - pm_report：信件战报（id 即信件 id），report 为战斗过程 JSON。
 * - gm：新 GM 信箱（玩家反馈，status 4 忽略不回复；GM 回复 reply / reply_uid / reply_dateline，玩家评分 grade）。
 * - member.newpm（1 有新气泡信件）/ member.newmail（1 有新邮件，2 GM 已回复，3 两者都有）。
 *
 * 删除同旧版分两步：双方都删了的、已读的信件删掉，其余标记本方删除；旧版物理删除，新版软删除（deleted_at）。
 * 信件内容列宽 255：旧版库非严格模式截断写入，这里同样截到 255 字。
 */
final class Message
{
    /** 信件每页显示条数 */
    public const int PERPAGE = 6;
    /** 普通信件（邮件）类型 */
    public const array MAIL_TYPES = [5, 6, 10];
    /** 气泡信件类型 */
    public const array PM_TYPES = [1, 2, 3, 4, 7, 8, 9, 11];

    /** pm 旧版字段（gm 表见 GM_FIELDS） */
    private const array PM_FIELDS = [
        'id', 'fromuid', 'fromname', 'title', 'touid', 'toname', 'type', 'message', 'isread', 'status', 'reply',
        'gold', 'silver', 'act', 'barcard', 'giftstatus', 'report', 'channel', 'dateline',
    ];
    private const array GM_FIELDS = [
        'id', 'fromuid', 'fromname', 'title', 'type', 'touid', 'toname', 'message', 'isread', 'status', 'reply',
        'reply_uid', 'reply_dateline', 'channel', 'grade', 'grade_dateline', 'dateline',
    ];
    private const array STRING_FIELDS = ['fromname', 'title', 'toname', 'message', 'reply'];

    /**
     * 按类型分页查询信件（get_message_by_type）
     *
     * $t：0 / 1 私人信件（收件箱另含国家信件 10）、2 旧 GM 信件、4 国家信箱、5 新 GM 信箱、其他为系统和 vip；
     * $from 1 发件箱（GM 信箱一律取发件箱）。排序：未读在前，时间倒序。国家信箱的发件箱一次群发只列一条（旧版 GROUP BY dateline）。
     *
     * @return array{status: int, list: list<array<string, mixed>>}
     */
    public static function byType(int $uid, int $t = 0, int $from = 0, int $page = 1, int $perpage = 0): array
    {
        $perpage = $perpage > 0 ? $perpage : self::PERPAGE;
        $table = 'pm';
        if ($t === 1 || $t === 0) {
            // 私人信件
            $type = [5, 1];
        } elseif ($t === 2) {
            // 旧GM提交信件
            $type = [6];
        } elseif ($t === 4) {
            // 国家邮箱
            $type = [10];
        } elseif ($t === 5) {
            // 新GM信箱
            $type = [6];
            $table = 'gm';
        } else {
            // 系统和vip
            $type = [1, 2];
        }

        $query = DB::table($table)->select(...self::fields($table))->whereNull('deleted_at');
        if ($from === 1 || $t === 2 || $t === 5) {
            // 发件箱(进入GM信箱直接取发件箱数据)
            $query->where('fromuid', $uid)->where('status', '!=', 2);
            $column = 'fromuid';
            $statusOp = 2;
        } else {
            // 收件箱
            $query->where('touid', $uid)->where('status', '!=', 3);
            $column = 'touid';
            $statusOp = 3;
            // 查看收件箱时，普通信箱新增国家信件
            if ($t === 1 || $t === 0) {
                $type[] = 10;
            }
        }
        $query->whereIn('type', $type);
        if ($t === 4 && $from === 1) {
            // GROUP BY dateline：同一次群发取一行（旧版取到哪行由库决定，这里取 id 最小的一行）
            $ids = DB::query(
                'SELECT MIN(`id`) AS `id` FROM `zc_sanguo_' . $table . '` WHERE `' . $column . '` = :uid AND `status` != :st
                 AND `type` IN (' . implode(',', $type) . ') AND `deleted_at` IS NULL GROUP BY `dateline`',
                ['uid' => $uid, 'st' => $statusOp]
            );
            $query->whereIn('id', $ids === [] ? [0] : array_map(static fn (array $r): int => (int)$r['id'], $ids));
        }
        // 偏移量
        $offset = ($page - 1 < 0 ? 0 : $page - 1) * $perpage;
        $rows = $query->orderBy('isread', 'ASC')->orderBy('dateline', 'DESC')->orderBy('id', 'DESC')
            ->limit($perpage)->offset($offset)->get();
        if ($rows === []) {
            return ['status' => 0, 'list' => []];
        }
        return ['status' => 1, 'list' => array_map(static fn (array $row): array => self::row($row), $rows)];
    }

    /**
     * 取用户信件（get_pm_buy_num）
     *
     * - $id > 0：取单封（$f 1 按发件人、否则按收件人是自己），未读的标为已读；再按 $type 更新 member 的新信件提示：
     *   $type 2（邮件）/ 3（新 GM 信箱）把 newmail 清 0（GM 有已回复未读的保留 2），其余类型没有未读气泡信件时 newpm 清 0；
     * - $id 为 0：$type 1 取未读的气泡信件，返回 [信件类型 => [id…]]。
     *
     * 与旧版的差别：$type 3 读的是 gm 表，旧版却按同一个 id 去改 pm 表的已读状态（改到别人的信件）；新版改读到的那张表。
     *
     * @param array{uid: int, newmail: int} $member
     * @return array{status: int, list: array<int|string, mixed>}
     */
    public static function pmBuyNum(array $member, int $type = 0, int $id = 0, int $f = 0): array
    {
        $uid = $member['uid'];
        $table = $type === 3 ? 'gm' : 'pm';
        $query = DB::table($table)->select(...self::fields($table))->where($f === 1 ? 'fromuid' : 'touid', $uid)->whereNull('deleted_at');
        if ($id > 0) {
            // 按信件id查询
            $query->where('id', $id);
        } elseif ($type === 1) {
            // 查询未读的信件
            $query->where('isread', 0)->whereIn('type', self::PM_TYPES);
        }
        $rows = $query->orderBy('id')->get();
        if ($rows === []) {
            return ['status' => 0, 'list' => []];
        }
        if ($id <= 0) {
            // 多条返回
            $list = [];
            foreach ($rows as $val) {
                $list[(int)$val['type']][] = (int)$val['id'];
            }
            return ['status' => 1, 'list' => $list];
        }

        // 单条返回
        $list = self::row($rows[0]);
        if ($list['isread'] === 0) {
            // 修改查看状态
            DB::table($table)->where('id', $id)->whereNull('deleted_at')->update(['isread' => 1]);
        }
        // 修改member信件状态 如果这是最后一条信件，把状态改成0
        if ($type === 2 || $type === 3) {
            $types = self::MAIL_TYPES;
            $data = ['newmail' => 1];
        } else {
            $types = self::PM_TYPES;
            $data = ['newpm' => 0];
        }
        // 如果是查看邮件，并且状态为都有信件，查询未读信件个数
        if (($type === 2 || $type === 3) && $member['newmail'] === 3) {
            // 当前信件没有未读的了，就查询另外一种有没有未读的
            $other = DB::table($type === 2 ? 'pm' : 'gm')->where('fromuid', $uid)->where('status', 1)->where('isread', 0)
                ->whereNull('deleted_at')->value('id');
            if ($other !== null) {
                $data['newmail'] = 2;
            }
        }
        // 查询邮件和信件的未读数量
        $num = DB::table('pm')->whereIn('type', $types)->where('touid', $uid)->where('isread', 0)->whereNull('deleted_at')->count();
        if ($num === 0 || $type === 2 || $type === 3) {
            if (($type === 2 || $type === 3) && $data['newmail'] !== 2) {
                $data['newmail'] = 0;
            }
            DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->update($data);
            Redis::del('player:' . $uid);
        }
        return ['status' => 1, 'list' => $list];
    }

    /**
     * 删除信件（del_message）
     *
     * $f 0 我是收件方（删发件方已删的、已读的；其余标为收件方删除 3），1 我是发件方（删收件方已删的、已读的；其余标为发件方删除 2）。
     * $t 0 / 1 私人信件（收件箱除 GM 外、发件箱除 GM 与国家信件外的所有类型）、2 GM 信件、3 国家信件、4 新 GM 信件（gm 表）。
     * $id 为 0 时整箱删除。
     *
     * @return array{status: int}
     */
    public static function del(int $uid, int $id = 0, int $t = 0, int $f = 0, ?int $now = null): array
    {
        $return = ['status' => 0];
        if ($f === 0) {
            $from = 'touid';
            $delStatus = 2;
            $upStatus = 3;
        } else {
            $from = 'fromuid';
            $delStatus = 3;
            $upStatus = 2;
        }
        $table = $t === 4 ? 'gm' : 'pm';
        $scope = static function () use ($table, $from, $uid, $t, $f, $id) {
            $query = DB::table($table)->where($from, $uid)->whereNull('deleted_at');
            if ($t === 1 || $t === 0) {
                // 私人信件
                $query->whereNotIn('type', $f === 0 ? [6] : [6, 10]);
            } elseif ($t === 2 || $t === 4) {
                // GM信件
                $query->where('type', 6);
            } elseif ($t === 3) {
                // 国家信件
                $query->where('type', 10);
            }
            if ($id > 0) {
                $query->where('id', $id);
            }
            return $query;
        };
        // 删除（软删除）
        $deleted = $scope()->where('status', $delStatus)->where('isread', 1)
            ->update(['deleted_at' => date('Y-m-d H:i:s', $now ?? time())]);
        if ($deleted > 0) {
            $return['status'] = 1;
        }
        // 把没删的修改状态
        if ($scope()->update(['status' => $upStatus]) > 0) {
            $return['status'] = 1;
        }
        return $return;
    }

    /**
     * 发信件（send_message 的存储部分；信件内容由调用方按旧版 get_message 的模板拼好）
     *
     * $info：touid（单个或列表）、type、message，可选 fromuid / fromname（缺省按类型：1 / 2 / 7 / 8 为系统 1000 + 系统名称，
     * 其余为 $sender）、toname / title / status（5 / 6 / 10）、gold / silver / act / barcard（1 系统通知的附件）、report（1 战报）、
     * new（6 GM 信件写新 GM 信箱 gm 表）、ismail（按邮件提示）。
     * 收件人的提示：邮件（5 / 6 / 10 或 ismail）newmail = 1，其余 newpm = 1；5 / 6 / 10 的 status 非 0（禁言玩家的信）不提示。
     *
     * @param array<string, mixed> $info
     * @param array{uid: int, nickname: string} $sender 当前玩家（旧版 $this->member）
     * @return int 插入的第一封信件 id（旧版 insert_batch 后的 insert_id）
     */
    public static function send(array $info, array $sender, ?int $now = null): int
    {
        $type = (int)$info['type'];
        $common = [];
        // 是否修改收件人新信件提示
        $updateStatus = 1;
        if ($type === 1) {
            $giftstatus = 0;
            foreach (['gold', 'silver', 'act', 'barcard'] as $field) {
                if ((int)($info[$field] ?? 0) > 0) {
                    $common[$field] = (int)$info[$field];
                    $giftstatus = 1;
                }
            }
            if (!$giftstatus) {
                $common['giftstatus'] = 1;
            }
        } elseif (in_array($type, self::MAIL_TYPES, true)) {
            $common['toname'] = (string)($info['toname'] ?? '');
            $common['status'] = (int)($info['status'] ?? 0);
            if (isset($info['title'])) {
                $common['title'] = mb_substr((string)$info['title'], 0, 50);
            }
            if ($common['status']) {
                $updateStatus = 0;
            }
        } elseif ($type === 8 && isset($info['title'])) {
            // 跨服远征：战报id
            $common['title'] = mb_substr((string)$info['title'], 0, 50);
        }
        if (!empty($info['report'])) {
            $common['report'] = 1;
        }
        $common['message'] = mb_substr((string)$info['message'], 0, 255);
        $common['type'] = $type;
        $common['dateline'] = $now ?? time();

        $table = 'pm';
        // 信件特殊处理(GM信件自动标为已读)
        if ($type === 6) {
            $common['isread'] = 1;
            // 依赖未移植：member.channel（登录渠道），新版没有，记 0
            $common['channel'] = 0;
            if ((int)($info['new'] ?? 0) === 1) {
                $table = 'gm';
            }
        }
        // 发件人(1,2,7,8 是系统类信件，发件人名字取系统定义的)
        if (isset($info['fromuid'])) {
            $common['fromuid'] = (int)$info['fromuid'];
            $common['fromname'] = (string)($info['fromname'] ?? '');
        } else {
            $common['fromuid'] = $sender['uid'];
            $common['fromname'] = $sender['nickname'];
        }

        $touids = is_array($info['touid']) ? $info['touid'] : [$info['touid']];
        // 修改用户收信状态用(5, 6, 10 代表是邮件：更新邮件状态)
        $memberInfo = in_array($type, self::MAIL_TYPES, true) || (int)($info['ismail'] ?? 0) === 1 ? ['newmail' => 1] : ['newpm' => 1];

        $firstId = 0;
        foreach ($touids as $touid) {
            $id = (int)DB::table($table)->insertGetId($common + ['touid' => (int)$touid]);
            if ($firstId === 0) {
                $firstId = $id;
            }
        }
        // 修改用户信件状态
        if ($updateStatus && $touids !== []) {
            DB::table('member')->whereIn('uid', array_map('intval', $touids))->whereNull('deleted_at')->update($memberInfo);
            foreach ($touids as $touid) {
                Redis::del('player:' . (int)$touid);
            }
        }
        return $firstId;
    }

    /**
     * 记录 pk 战报（record_pk_report：$info from / fromlevel / to / tolevel / report）
     *
     * @param array<string, mixed> $info
     */
    public static function recordPkReport(int $id, array $info): void
    {
        if ($id <= 0) {
            return;
        }
        DB::table('pm_report')->insert([
            'id' => $id,
            'nickname' => mb_substr((string)$info['from'], 0, 50),
            'userlevel' => (int)$info['fromlevel'],
            'name' => mb_substr((string)$info['to'], 0, 50),
            'level' => (int)$info['tolevel'],
            'report' => json_encode($info['report'], JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * 按 id 查询信件战报（get_pk_report_by_id）
     *
     * @return array{status: int, report: array<string, mixed>, info?: array<string, mixed>}
     */
    public static function pkReportById(int $id): array
    {
        $return = ['status' => 0, 'report' => []];
        $row = DB::table('pm_report')->select('id', 'report', 'nickname', 'userlevel', 'name', 'level', 'background')
            ->where('id', $id)->whereNull('deleted_at')->first();
        if ($row !== null) {
            $report = json_decode((string)$row['report'], true);
            $return['report'] = is_array($report) ? $report : [];
            foreach (['level', 'name', 'nickname', 'userlevel', 'background'] as $value) {
                $return['info'][$value] = in_array($value, ['name', 'nickname'], true) ? (string)$row[$value] : (int)$row[$value];
            }
        }
        // 没有找到指定战报为 -1
        $return['status'] = $return['report'] !== [] ? 1 : -1;
        return $return;
    }

    /**
     * 修改信件中的礼包领取状态（update_gift_status）：1 成功，0 没有修改（已领取过）
     */
    public static function updateGiftStatus(int $uid, int $id): int
    {
        return DB::table('pm')->where('id', $id)->where('touid', $uid)->where('giftstatus', 0)->whereNull('deleted_at')
            ->update(['giftstatus' => 1]) > 0 ? 1 : 0;
    }

    /**
     * 某类信件全部标为已读（update_read_status）；没有未读的气泡信件时 newpm 清 0
     */
    public static function updateReadStatus(int $uid, int $type): void
    {
        DB::table('pm')->where('type', $type)->where('isread', 0)->where('touid', $uid)->whereNull('deleted_at')->update(['isread' => 1]);
        $unread = DB::table('pm')->where('touid', $uid)->whereIn('type', self::PM_TYPES)->where('isread', 0)->whereNull('deleted_at')->value('id');
        if ($unread === null) {
            DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->update(['newpm' => 0]);
            Redis::del('player:' . $uid);
        }
    }

    /**
     * 新 GM 信箱按 id 查信件（get_message_by_id）
     *
     * @return array<string, mixed>
     */
    public static function gmById(int $id): array
    {
        $row = DB::table('gm')->select(...self::GM_FIELDS)->where('id', $id)->whereNull('deleted_at')->first();
        return $row === null ? [] : self::row($row);
    }

    /**
     * 评分（update_message_grade）：1 成功，0 没有修改
     */
    public static function updateGrade(int $id, int $grade, int $now): int
    {
        return DB::table('gm')->where('id', $id)->where('grade', 0)->whereNull('deleted_at')
            ->update(['grade' => $grade, 'grade_dateline' => $now]) > 0 ? 1 : 0;
    }

    /**
     * 字符串截取（控制器 g_substr：按 UTF-8 字节、中文算 2 个宽度，超出时去掉末尾再加 ...）
     */
    public static function substr(string $str, int $len = 20, bool $dot = true): string
    {
        $i = 0;
        $l = 0;
        $c = 0;
        $a = [];
        while ($l < $len) {
            $t = substr($str, $i, 1);
            if ($t !== '' && ord($t) >= 224) {
                $c = 3;
                $t = substr($str, $i, $c);
                $l += 2;
            } elseif ($t !== '' && ord($t) >= 192) {
                $c = 2;
                $t = substr($str, $i, $c);
                $l += 2;
            } else {
                $c = 1;
                $l++;
            }
            $i += $c;
            if ($l > $len) {
                break;
            }
            $a[] = $t;
        }
        $re = implode('', $a);
        // 旧版 substr 越界返回 FALSE（PHP 8 返回空串）：后面还有内容才截断
        if (substr($str, $i, 1) !== '') {
            array_pop($a);
            if ($c === 1) {
                array_pop($a);
            }
            $re = implode('', $a);
            if ($dot) {
                $re .= '...';
            }
        }
        return $re;
    }

    /**
     * @return list<string>
     */
    private static function fields(string $table): array
    {
        return $table === 'gm' ? self::GM_FIELDS : self::PM_FIELDS;
    }

    /**
     * 库里的行：字符串字段保持字符串，其余转整数
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function row(array $row): array
    {
        foreach ($row as $key => $value) {
            $row[$key] = in_array($key, self::STRING_FIELDS, true) ? (string)$value : (int)$value;
        }
        return $row;
    }
}
