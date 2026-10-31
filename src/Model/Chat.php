<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;

/**
 * 聊天室（移植旧版 models/chat_model.php；屏蔽词取旧版 badword_model / banword_model）
 *
 * 存储同旧版全部放 Redis，键名、长度、过期时间同旧版（OmniPHP Redis 自动加 hj: 前缀）：
 * - chat_message_0：公共队列（世界、战报、系统），最多 30 条，24 小时过期；c=chat&m=index 返回它。
 * - chat_country_{国家id}：国家队列，最多 10 条，24 小时过期；index 时放在公共队列前面。
 * - chat_message_{uid}：每个在线玩家的私人队列。发到全服 / 国家 / 私聊的每条消息都追加到收件人的队列，
 *   过期时间与收件人的在线标记 chat_online_{uid} 同步（不在线的收件人队列立即删除，消息丢弃，同旧版）；
 *   c=chat&m=read 一次弹出全部并刷新在线标记（60 秒）。
 * - chat_lock_{uid}（3 秒发言间隔）、chat_recode_{uid}（上次发送内容，5 分钟内重复不发）。
 *
 * 与旧版的差别：
 * - 实时推送：玩家的 ws 连接鉴权后由 ws 进程代替轮询刷新在线标记，并记下所在 ws 进程 chat_ws_{uid}；
 *   往在线玩家私人队列追加消息后，把 uid 放进该进程的通知集合 chat_push_{进程号}，ws 进程弹出该玩家的队列
 *   （即旧版 read 的结果）推给客户端（{@see \Hanjiang\WebSocket\ChatPush}）。没有 ws 连接时客户端照旧轮询 read。
 * - 屏蔽玩家（ban_uid）旧版每对一个键 chat_ban__{uid}_{touid}、读时 KEYS 扫描（且 KEYS chat_ban__{uid}* 会把
 *   uid 前缀相同的其他玩家的屏蔽也算进来）；ws 推送每条消息都要过滤，改为每个玩家一个有序集合 chat_ban_{uid}
 *   （成员 touid、分值为到期时间），每条屏蔽仍是 24 小时。
 * - 发言间隔用 SET NX EX 原子判断（旧版 GET 后 SETEX，多进程并发可同时通过）。
 */
final class Chat
{
    /** 消息类型（_send_message 的 type）：0 公共 1 私信 2 喇叭 3 PK战报 4 过图战报 5 将魂战报 6 国家 7 系统 8 金银洞 9 跨服 10 战神殿 11 四象圣域 */
    public const int TYPE_PUBLIC = 0;
    public const int TYPE_PRIVATE = 1;
    public const int TYPE_HORN = 2;
    public const int TYPE_REPORT = 3;
    public const int TYPE_COUNTRY = 6;
    public const int TYPE_SYSTEM = 7;

    private const string LOCK_KEY = 'chat_lock_';
    private const string ONLINE_KEY = 'chat_online_';
    private const string PUBLIC_KEY = 'chat_message_0';
    private const string MESSAGE_KEY = 'chat_message_';
    private const string COUNTRY_KEY = 'chat_country_';
    private const string RECODE_KEY = 'chat_recode_';
    private const string BAN_KEY = 'chat_ban_';
    /** 实时推送：玩家 ws 连接所在进程号 / 各 ws 进程待推送的 uid 集合 */
    private const string WS_KEY = 'chat_ws_';
    private const string PUSH_KEY = 'chat_push_';
    /** 屏蔽词（badword_model：后台维护的 Redis 键，旧版库里没有数据）/ 聊天屏蔽字（banword_model->get_chat_ban） */
    private const string BADWORD_KEY = 'badword';
    private const string BANWORD_CHAT_KEY = 'banword_chat';

    /** 在线用户过期时间(s)，N 秒后无请求判定离线 */
    public const int ONLINE_EXPIRE = 60;
    /** 公共信息队列最大长度 */
    private const int PUBLIC_MAX_LEN = 30;
    /** 国家队列最大长度 */
    private const int COUNTRY_MAX_LEN = 10;
    /** 最短发送间隔(s) */
    private const int SEND_DELAY_TIME = 3;
    /** 上次发送信息保存时间 */
    private const int RECODE_TIME = 300;
    /** 屏蔽时间 */
    private const int BAN_TTL = 86400;
    /** 公共 / 国家队列过期时间 */
    private const int PUBLIC_TTL = 86400;

    /**
     * 旧版 controllers/chat.php 内置的中文聊天屏蔽字（banword_chat 未设置时使用；serverid 6000~9000 韩文、
     * 9000~10000 越南文两套随对应地区服，新版单一中文服不移植）
     */
    public const array CHAT_BAN_WORDS = [
        '毛泽东','周恩来','孙中山','刘少奇','邓小平','宋庆龄',
        '彭德怀','刘伯承','罗荣桓','聂荣臻','叶剑英','习近平',
        '李克强','张德江','俞正声','刘云山','王岐山','张高丽',
        '王沪宁','刘延东','刘奇葆','许其亮','孙春兰','孙政才',
        '李建国','李源潮','张春贤','范长龙','孟建柱','赵乐际',
        '胡春华','栗战书','郭金龙','赵洪祝','杜青林','常万全',
        '房峰辉','赵克石','张又侠','吴胜利','马晓天','魏凤和',
        '江泽民','朱镕基','胡锦涛','温家宝','吴邦国','李长春',
        '贺国强','周永康','李肇星','杨洁篪','董必武','杨尚昆',
        '李先念','华国锋','张国焘','邓颖超','赵紫阳','王立军',
        '邓子恢','乌兰夫','薄熙来','薄一波','共产党','悍将三国',
        '张阳','马凯','韩正','杨晶','汪洋','鲁迅','陈云','李鹏',
        '朱德','林彪','贺龙','陈毅','粟裕','贴吧','淘宝','交易',
        '元=','加q','先货','元等','家q','圓=','扣','先貨','送神将',
        '元宝=','50790278','要加','锬','元寶=','赔','咨询',
        '元寶=','沅','聯繫','秋秋','738868060','周瑜项羽','q谈',
        '谈','要q','萬','餸','要茄','运营','出售','优惠','qq',
        '远寳','要m','官方','黑卡','管理','客服','充值','活动','联系',
        '加群','gm','vip','admin','4282887','1606790114','24906045',
        'taobao','玩家群','加q','822725227','70890995','15867604357',
        '付款','386057364','183404546','64345075','300員','568002721',
        '先货','聯繫','送周瑜','5萬','加買家','1099367752','員寶',
        '圓寶','+周瑜','家q','5万','加周瑜','扣扣','737628003','談',
        '778585942','50000','300','335618211','200922179','五万',
        '三百','(周瑜)','七三七','五六八','三八六','一八三','六四三',
        '七七五','三三五','九二二','五七三','四五四','三四五','二七二',
        '1224620089','扣','送神将','百元','先貨','送', '直充', '付款',
        '.0.','.1.','.2.','.3.','.4.','.5.','.6.','.7.','.8.','.9.', 'oo',
        '高效','代唰','快速','810305026','现成50级','kmp','搜索','大量刷',
        '掏保','385870149','千澫','碘伟','资讯','员寶','佰圓','先获','窾',
        '232797981','扣','抠','圆寶','佰圆','铜钱','请加','售','赠v','代刷',
        'q', '萭', '佰園', '筘', '珈','小号','ｑ','Ｑ','q','Q','钓鱼岛','尖阁列岛',
    ];

    /** 旧版 chat.php 判断屏蔽字 / 重复发言前去掉的符号（原样照搬，含 '\|' 等带反斜杠的写法） */
    public const array STRIP_CHARS = [
        '！', '@', '#', '￥', '%', '…', '&', '*', '（', '）', '-', '—',
        '+', '!', '$', '^', '*', '(', ')', '_', '+', ',', '.', '/',
        ';', '\'', '[', ']', '\\', '，', '。', ' ', '；', '’', '【',
        '】', '、', '《', '》', '？', '：', '“', '｛', '｝', '\|', '\<',
        '\>', '?', '\:', '\ "', '{', '}', 'amp;', '`', '~', ' ', '≈', '≡',
    ];

    /** 旧版语言包（language/chinese_s/chat_lang.php、base_lang.php 中 get_message_str 用到的条目） */
    public const array LANG = [
        'chat_ranked' => '第%s名',
        'chat_arena_beat' => '%s 击败了 %s 升至演武榜 %s ',
        'chat_challenge' => '%s 在与 %s 比武中 %s ',
        'chat_beat' => '%s 击败了 %s',
        'chat_tower_beat' => '%s 在将魂星路击败了 %s',
        'chat_lottery' => '恭喜 %s 在幸运大转盘抽到了 %s',
        'chat_lottery_general' => '恭喜 %s 在幸运大转盘获得武将 %s',
        'chat_springshop_general' => '恭喜 %s 在武将商城获得武将 %s',
        'chat_springlottery_general' => '恭喜 %s 在大转盘中获得武将 %s',
        'chat_island_beat' => '%s 在金银洞击败了 %s',
        'chat_country_sacrifice' => '%s 完成了 %s ，加快国家发展，真是功不可没。',
        'chat_get_general' => '恭喜 %s 成功招募到武将 %s',
        'chat_elite' => '%s准备攻打%s，天下英雄们速来协助',
        'chat_boss' => '%s 将“刑天”最后击杀，奖励额外增加%s',
        'chat_business' => '玩家 %s 达成 %s 幸运通商，获得银币%s 。',
        'chat_show_report' => '查看战报',
        'chat_blackmarket' => '%s鸿运当头，在黑市中以【%s折】购买了%s',
        'chat_worldarena' => '%s 击败了 %s 升至演武榜 %s ',
        'chat_world_pk' => '%s 在与 %s 比武中 %s ',
        'chat_country_boss' => '%s 将“蚩尤”最后击杀，奖励额外增加%s',
        'chat_siege_defense_win' => '%s击败了%s，成功守住【%s】',
        'chat_siege_defense_lose' => '%s被%s击败了，【%s】被攻占',
        'chat_siege_attack_win' => '%s击败了%s，攻占【%s】',
        'chat_siege_attack_lose' => '%s被%s击败了，【%s】攻占失败。',
        'chat_hit_egg_general' => '%s 运气真好，在砸金蛋活动中砸到了武将-%s',
        'chat_god_report_not_final' => '%s 战胜 %s，成功晋级战神殿%s %s',
        'chat_god_report_final' => '%s 在战神殿决赛中战胜 %s，荣登战帝宝座，万民膜拜 %s',
        'chat_sanctum_beat' => '%s 在四象圣域击败了 %s',
        'chat_countrymine' => '%s 在国家矿区抢夺 %s 的矿中 %s ',
        'chat_mine' => '%s 在矿区抢夺 %s 的矿中 %s ',
        'chat_mount_quality' => '<em class="q%s">%s</em>',
        'chat_mount_get' => '恭喜【%s】在西域马场选中名马-%s，真乃当世雄主',
        'chat_springmount_get' => '恭喜【%s】在大转盘抽中名马-%s，真乃当世雄主',
        'chat_springequipment_get' => '恭喜【%s】在大转盘抽中至尊神装-%s',
        'chat_send_need_level' => '您必须达到等级%s级才能在世界频道发言！',
        'chat_first_recharge_get' => '恭喜【%s】在首充双倍返利中获得装备-%s',
        'chat_first_lottery_get' => '恭喜【%s】在天命转盘抽中装备-%s',
        'chat_first_lottery_general' => '恭喜【%s】在天命转盘获得%s星武将-%s',
        'chat_heaven_beat' => '%s 在通天塔击败了 %s',
        'chat_war_report_not_final' => '%s 战胜 %s，成功晋级武斗会%s %s',
        'chat_war_report_final' => '%s 在武斗会决赛中战胜 %s，荣登战帝宝座，万民膜拜 %s',
        'chat_treasure_get' => '恭喜【%s】在寻宝活动中获得装备-%s',
        'chat_treasure_general' => '恭喜【%s】在寻宝活动中获得%s星灵将-%s',
        'chat_treasure_mount_get' => '恭喜【%s】在寻宝活动中获得名马-%s',
        'chat_treasure_hanziexch_get' => '恭喜【%s】在汉字兑换中获得【%s】等奖',
        'chat_veteran_general_get' => '恭喜【%s】在老兵召回活动中获得%s星武将-%s',
        'chat_kemari_get' => '恭喜【%s】在蹴鞠大赛中获得%s星武将-%s',
        'chat_kemari_pr_get' => '恭喜【%s】在蹴鞠大赛中获得%s星神将-%s',
        'chat_passgeneral_get_quality' => '恭喜【%s】在过关斩将中获得装备-%s',
        'chat_passgeneral_get' => '恭喜【%s】在过关斩将中获得%s星武将-%s',
        'chat_passgeneral_pr_get' => '恭喜【%s】在过关斩将中获得%s星神将-%s',
        'chat_arena_general_get' => '恭喜玩家【%s】在封神榜积分商城兑换中获得%s星武将-%s',
        'chat_thirty_general_get' => '恭喜【%s】在充值送豪礼活动中获得%s星武将-%s',
        'chat_beauty_beat_win2' => '%s 妄图抢劫%s 的美人，被狠狠教训了一顿',
        'chat_beauty_beat_win1' => '%s 在铜雀台中大展雄风，战胜了%s',
        'chat_beauty_beat_win0' => '%s 和 %s 在铜雀台中战成 %s',
        'chat_copies_beat' => '%s击败了%s',
        // base_lang.php
        'base_gold' => '元宝',
        'base_silver' => '银币',
        'base_act' => '军令',
        'base_jade' => '玉石',
        'base_star' => '星',
        'base_seven' => '七',
        'base_eight' => '八',
        'base_nine' => '九',
        'base_ten' => '十',
        // arena_lang.php（各 send_to_chat 的 win 文字）
        'arena_win' => '胜利',
        'arena_lose' => '失败',
        'arena_deuce' => '打成平局',
    ];

    /** 消息类型 => 战报类型（get_message_str 的 $argu_id_type，对应前端 buildTag 的解析） */
    private const array REPORT_ID_TYPE = [
        2 => 2, 3 => 2, 4 => 3, 5 => 4, 8 => 11, 15 => 13, 16 => 13, 18 => 14, 19 => 14, 20 => 14, 21 => 14,
        23 => 15, 24 => 15, 25 => 16, 26 => 2, 27 => 2, 38 => 17, 41 => 18, 36 => 19, 37 => 19,
        49 => 49, 50 => 49, 51 => 49, 52 => 49, 53 => 53,
    ];

    /**
     * 发信人（旧版 chat_model 里的 $this->member / $this->member_field）：uid、昵称、国家、演武榜排名
     *
     * 计划任务里没有发信人（旧版 $this->member = array()）时传 null：消息 uid 为 0、昵称为空、不做重复判断。
     *
     * @return array{uid: int, nickname: string, country: int, rank: int}|null
     */
    public static function sender(int $uid): ?array
    {
        if ($uid <= 0) {
            return null;
        }
        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        if ($member === null) {
            return null;
        }
        $rank = DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->value('rank');
        return [
            'uid' => $uid,
            'nickname' => (string)$member['nickname'],
            'country' => (int)$member['country'],
            'rank' => (int)($rank ?? 0),
        ];
    }

    /**
     * 更新用户在线信息（_update_online_arr）
     */
    public static function updateOnline(int $uid, string $nickname): void
    {
        Redis::set(self::ONLINE_KEY . $uid, ['nickname' => $nickname], self::ONLINE_EXPIRE);
    }

    /**
     * 获得在线用户数（get_online_number）
     */
    public static function onlineNumber(): int
    {
        return count(self::onlineList());
    }

    /**
     * 获得在线用户 uid 列表（get_online_list）
     *
     * @return list<int>
     */
    public static function onlineList(): array
    {
        $prefix = self::prefix() . self::ONLINE_KEY;
        $return = [];
        foreach (Redis::keys(self::ONLINE_KEY . '*') as $key) {
            $return[] = (int)substr((string)$key, strlen($prefix));
        }
        return $return;
    }

    /**
     * 发送公共消息（send_message_to_all）：写公共队列，并追加到每个在线用户的私人队列
     *
     * 有发信人且不是喇叭时，与该发信人 5 分钟内上次发送的内容相同则不发，否则记录本次内容。
     *
     * @param array{uid: int, nickname: string, country: int, rank: int}|null $sender
     */
    public static function sendToAll(string $message, int $type = 0, int|string $id = 0, int $tree = 0, ?array $sender = null): void
    {
        // 判断消息是否重复
        if ($type !== self::TYPE_HORN && $sender !== null) {
            // 获取上次发送的语言
            if ($message == self::getLastSendMessage($sender['uid'])) {
                return;
            }
            // 记录本次发送的信息
            self::recodeSendMessage($sender['uid'], $message);
        }

        self::sendMessage($message, 0, $type, $id, 0, 0, $tree, $sender);
        // 维护公共队列长度
        for ($i = (int)Redis::lLen(self::PUBLIC_KEY); $i > self::PUBLIC_MAX_LEN; $i--) {
            Redis::lPop(self::PUBLIC_KEY);
        }
        // 循环插入在线用户的私人消息队列
        $pushed = [];
        foreach (self::onlineList() as $uid) {
            if (self::sendMessage($message, $uid, $type, $id, 0, 0, $tree, $sender)) {
                $pushed[] = $uid;
            }
        }
        self::notify($pushed);
    }

    /**
     * 单独发信（send_message_to_uid）：默认是私信；type = 0 时为禁言 / 屏蔽后仅自己可见
     *
     * @param array{uid: int, nickname: string, country: int, rank: int}|null $sender
     */
    public static function sendToUid(string $message, int $uid, int $type = self::TYPE_PRIVATE, int $tree = 0, ?array $sender = null): void
    {
        if (self::sendMessage($message, $uid, $type, 0, 0, 0, $tree, $sender)) {
            self::notify([$uid]);
        }
    }

    /**
     * 国家信息（send_message_to_country）：写国家队列，并追加到每个国家成员的私人队列
     *
     * 国家队列取发信人的国家（旧版 isset($this->member['country'])），没有发信人时取 $country。
     *
     * @param list<int> $uidArr
     * @param array{uid: int, nickname: string, country: int, rank: int}|null $sender
     */
    public static function sendToCountry(string $message, array $uidArr, int $type = self::TYPE_COUNTRY, int $isSystem = 0, int $country = 0, int $tree = 0, ?array $sender = null): void
    {
        if ($sender !== null) {
            $country = $sender['country'];
        }
        // 向国家队列发信息
        self::sendMessage($message, 0, $type, 0, $isSystem, $country, $tree, $sender);
        $key = self::COUNTRY_KEY . $country;
        // 维护国家队列长度
        for ($i = (int)Redis::lLen($key); $i > self::COUNTRY_MAX_LEN; $i--) {
            Redis::lPop($key);
        }
        $pushed = [];
        foreach ($uidArr as $uid) {
            // 向每个国家成员的私人队列发信息
            if (self::sendMessage($message, (int)$uid, $type, 0, $isSystem, $country, $tree, $sender)) {
                $pushed[] = (int)$uid;
            }
        }
        self::notify($pushed);
    }

    /**
     * 发送喇叭（send_message_to_horn）：只进在线用户的私人队列（不进公共队列），客户端用 xGame.horn 滚动
     *
     * @param array{uid: int, nickname: string, country: int, rank: int}|null $sender
     */
    public static function sendToHorn(string $message, ?array $sender = null): void
    {
        self::sendToAll($message, self::TYPE_HORN, 0, 0, $sender);
    }

    /**
     * 发送战报（send_message_to_report）
     *
     * @param array{uid: int, nickname: string, country: int, rank: int}|null $sender
     */
    public static function sendToReport(string $message, int|string $id, ?array $sender = null): void
    {
        self::sendToAll($message, self::TYPE_REPORT, $id, 0, $sender);
    }

    /**
     * 检查屏蔽词（check_badword）：后台屏蔽词替换成 ***
     */
    public static function checkBadword(string $message): string
    {
        $words = Redis::get(self::BADWORD_KEY);
        if (!is_array($words) || $words === []) {
            return $message;
        }
        return str_replace(array_map(strval(...), $words), '***', $message);
    }

    /**
     * 聊天屏蔽字（banword_model->get_chat_ban，为空时用旧版内置的中文屏蔽字）
     *
     * @return list<string>
     */
    public static function chatBanWords(): array
    {
        $words = Redis::get(self::BANWORD_CHAT_KEY);
        if (is_array($words) && $words !== []) {
            return array_values(array_map(strval(...), $words));
        }
        return self::CHAT_BAN_WORDS;
    }

    /**
     * 读取消息（read_message）：弹出私人队列里的全部消息，并刷新在线状态
     *
     * @return list<array<string, mixed>>
     */
    public static function readMessage(int $uid, string $nickname): array
    {
        $key = self::MESSAGE_KEY . $uid;
        $return = [];
        while (true) {
            $raw = Redis::lPop($key);
            $message = is_string($raw) ? json_decode($raw, true) : null;
            if (empty($message)) {
                break;
            }
            $return[] = $message;
        }
        // 读取消息时保持在线
        self::updateOnline($uid, $nickname);
        return $return;
    }

    /**
     * 读取公共消息（read_public_message）
     *
     * @return list<array<string, mixed>>
     */
    public static function readPublicMessage(): array
    {
        return self::range(self::PUBLIC_KEY);
    }

    /**
     * 读取国家公共消息（read_country）
     *
     * @return list<array<string, mixed>>
     */
    public static function readCountry(int $country): array
    {
        if ($country === 0) {
            return [];
        }
        return self::range(self::COUNTRY_KEY . $country);
    }

    /**
     * 检查发信间隔（check_delay）：发送太快返回 true，否则开始 3 秒间隔并返回 false
     */
    public static function checkDelay(int $uid): bool
    {
        $redis = Redis::instance();
        if ($redis === null) {
            return false;
        }
        return $redis->set(self::LOCK_KEY . $uid, '1', ['nx', 'ex' => self::SEND_DELAY_TIME]) !== true;
    }

    /**
     * 修改公共聊天信息（pop_public_message）：去掉公共队列头 / 尾一条
     */
    public static function popPublicMessage(int $head = 1): void
    {
        if ($head) {
            Redis::lPop(self::PUBLIC_KEY);
        } else {
            Redis::rPop(self::PUBLIC_KEY);
        }
    }

    /**
     * 记录本次发送的信息（recode_send_message）
     */
    public static function recodeSendMessage(int $uid, string $message): void
    {
        Redis::set(self::RECODE_KEY . $uid, $message, self::RECODE_TIME);
    }

    /**
     * 获取上次发送信息（get_last_send_message）
     */
    public static function getLastSendMessage(int $uid): ?string
    {
        $message = Redis::get(self::RECODE_KEY . $uid);
        return $message === null ? null : (string)$message;
    }

    /**
     * 根据消息类型和参数获得消息内容（get_message_str）
     *
     * 参数按传入顺序处理（mount 用到此前 rebate 设的 $argu_report）；没有 from 时取发信人。
     * type 1 预留、2 演武榜、3 切磋、4 任务战报、5 将魂战报、6 / 7 幸运大转盘元宝 / 武将、8 金银洞、9 国家祭祀、
     * 10 过图招武将、11 多人副本邀请、12 世界 BOSS 最后一击、13 通商、14 黑市、15 跨服前三、16 跨服远征、
     * 17 国家 BOSS 最后一击、18 ~ 21 国战、22 砸金蛋、23 / 24 战神殿、25 四象圣域、26 国家矿区、27 普通矿区、
     * 28 西域马场、29 ~ 32 春节、33 ~ 35 首充、36 / 37 武斗会、38 通天塔、39 ~ 42 踏青寻宝、43 / 44 蹴鞠、
     * 45 / 46 过关斩将、47 积分商城、48 充值送豪奖、49 ~ 51 铜雀台、52 老兵召回、53 新副本；其他类型返回空串。
     *
     * @param array<string, mixed> $argu
     * @param array{uid: int, nickname: string, country: int, rank: int}|null $sender
     */
    public static function getMessageStr(int $type, array $argu = [], ?array $sender = null): string
    {
        $from = $to = $report = $other = '';
        // 战报类型 对应前端解析
        $idType = isset($argu['id']) ? (self::REPORT_ID_TYPE[$type] ?? 0) : 0;
        $l = self::LANG;
        foreach ($argu as $key => $value) {
            switch ((string)$key) {
                case 'from':
                    $from = '{1,' . $value['nickname'] . ' ,' . $value['uid'] . '}';
                    break;
                case 'to':
                    $to = '{1,' . $value['nickname'] . ' ,' . $value['uid'] . '}';
                    break;
                case 'id':
                    $report = '{' . $idType . ',[' . $l['chat_show_report'] . '],' . $value . '}';
                    break;
                case 'gold':
                    $other = '{5,' . $value . $l['base_gold'] . '}';
                    break;
                case 'silver':
                    $other = '{6,' . $value . $l['base_silver'] . '}';
                    break;
                case 'jade':
                    $other = '{7,' . $value . $l['base_jade'] . '}';
                    break;
                case 'act':
                    $other = '{8,' . $value . $l['base_act'] . '}';
                    break;
                case 'general':
                case 'springmount':
                case 'springeuipment':
                case 'god_round':
                case 'siege3':
                    $other = '{9,' . $value . '}';
                    break;
                case 'rank':
                    $other = '{10,' . sprintf($l['chat_ranked'], $value) . '}';
                    break;
                case 'elite':
                    $report = '{12,' . $value . '}';
                    break;
                case 'star':
                    $other = '{9,' . $value . $l['base_star'] . '}';
                    break;
                case 'mount':
                    $other = sprintf($l['chat_mount_quality'], $value, $report);
                    break;
                case 'rebate':
                case 'star_general':
                    $report = (string)$value;
                    break;
                case 'worldarena':
                case 'siege2':
                    $to = '{9,' . $value . '}';
                    break;
                case 'siege1':
                    $from = '{9,' . $value . '}';
                    break;
                case 'god_report':
                    $report = '{15,' . $value . '}';
                    break;
                default:
                    $other = (string)$value;
                    break;
            }
        }
        if ($from === '') {
            $from = '{1,' . ($sender['nickname'] ?? '') . ' ,' . ($sender['uid'] ?? '') . '}';
        }

        return match ($type) {
            2 => sprintf($l['chat_arena_beat'], $from, $to, $other) . ' ' . $report,
            3 => sprintf($l['chat_challenge'], $from, $to, $other) . ' ' . $report,
            4 => sprintf($l['chat_beat'], $from, $other) . ' ' . $report,
            5 => sprintf($l['chat_tower_beat'], $from, $other) . ' ' . $report,
            6 => sprintf($l['chat_lottery'], $from, $other),
            7 => sprintf($l['chat_lottery_general'], $from, $other),
            8 => sprintf($l['chat_island_beat'], $from, $other) . ' ' . $report,
            9 => sprintf($l['chat_country_sacrifice'], $from, $other),
            10 => sprintf($l['chat_get_general'], $from, $other),
            11 => sprintf($l['chat_elite'], $from, $other) . ' ' . $report,
            12 => sprintf($l['chat_boss'], $from, '50%'),
            13 => sprintf($l['chat_business'], $from, $other, $other),
            14 => sprintf($l['chat_blackmarket'], $from, $report, $other),
            // 跨服前三
            15 => sprintf($l['chat_worldarena'], $from, $to, $other) . ' ' . $report,
            // 跨服战报
            16 => sprintf($l['chat_world_pk'], $from, $to, $other) . ' ' . $report,
            17 => sprintf($l['chat_country_boss'], $from, '50%'),
            18 => sprintf($l['chat_siege_defense_win'], $from, $to, $other) . $report,
            19 => sprintf($l['chat_siege_defense_lose'], $from, $to, $other) . $report,
            20 => sprintf($l['chat_siege_attack_win'], $from, $to, $other) . $report,
            21 => sprintf($l['chat_siege_attack_lose'], $from, $to, $other) . $report,
            22 => sprintf($l['chat_hit_egg_general'], $from, $other),
            23 => sprintf($l['chat_god_report_not_final'], $from, $to, $other, $report),
            24 => sprintf($l['chat_god_report_final'], $from, $to, $report),
            25 => sprintf($l['chat_sanctum_beat'], $from, $other) . ' ' . $report,
            26 => sprintf($l['chat_countrymine'], $from, $to, $other) . ' ' . $report,
            27 => sprintf($l['chat_mine'], $from, $to, $other) . ' ' . $report,
            28 => sprintf($l['chat_mount_get'], $from, $other),
            // 春节活动
            29 => sprintf($l['chat_springshop_general'], $from, $other),
            30 => sprintf($l['chat_springlottery_general'], $from, $other),
            31 => sprintf($l['chat_springmount_get'], $from, $other),
            32 => sprintf($l['chat_springequipment_get'], $from, $other),
            // 首充活动
            33 => sprintf($l['chat_first_recharge_get'], $from, $other),
            34 => sprintf($l['chat_first_lottery_get'], $from, $other),
            35 => sprintf($l['chat_first_lottery_general'], $from, $report, $other),
            // 武斗会
            36 => sprintf($l['chat_war_report_not_final'], $from, $to, $other, $report),
            37 => sprintf($l['chat_war_report_final'], $from, $to, $report),
            // 通天塔
            38 => sprintf($l['chat_heaven_beat'], $from, $other) . ' ' . $report,
            // 踏青寻宝
            39 => sprintf($l['chat_treasure_get'], $from, $other),
            40 => sprintf($l['chat_treasure_general'], $from, $report, $other),
            41 => sprintf($l['chat_treasure_mount_get'], $from, $report, $other),
            42 => sprintf($l['chat_treasure_hanziexch_get'], $from, $other),
            // 蹴鞠大赛（获得武将和神将）
            43 => sprintf($l['chat_kemari_get'], $from, $report, $other),
            44 => sprintf($l['chat_kemari_pr_get'], $from, $report, $other),
            // 过关斩将（获得武将和神将）
            45 => sprintf($l['chat_passgeneral_get'], $from, $report, $other),
            46 => sprintf($l['chat_passgeneral_pr_get'], $from, $report, $other),
            // 积分商城
            47 => sprintf($l['chat_arena_general_get'], $from, $report, $other),
            // 充值送豪奖
            48 => sprintf($l['chat_thirty_general_get'], $from, $report, $other),
            // 铜雀台 成功 / 失败 / 平局
            49 => sprintf($l['chat_beauty_beat_win1'], $from, $to, $other) . ' ' . $report,
            50 => sprintf($l['chat_beauty_beat_win2'], $from, $to, $other) . ' ' . $report,
            51 => sprintf($l['chat_beauty_beat_win0'], $from, $to, $other) . ' ' . $report,
            // 老兵召回
            52 => sprintf($l['chat_veteran_general_get'], $from, $report, $other),
            // 新副本
            53 => sprintf($l['chat_copies_beat'], $from, $other) . ' ' . $report,
            default => '',
        };
    }

    /**
     * 武将星级的中文数字（各玩法 7 星以上武将广播的 base_seven ~ base_ten）；7 星以下返回 null
     */
    public static function starWord(int $star): ?string
    {
        return match ($star) {
            7 => self::LANG['base_seven'],
            8 => self::LANG['base_eight'],
            9 => self::LANG['base_nine'],
            10 => self::LANG['base_ten'],
            default => null,
        };
    }

    /**
     * 品级颜色包装（chat_mount_quality：<em class="q品级">名称</em>）
     */
    public static function quality(int|string $quality, string $name): string
    {
        return sprintf(self::LANG['chat_mount_quality'], $quality, $name);
    }

    /**
     * 分享战报的胜负文字（arena / compete / mine / countrymine 的 send_to_chat）
     */
    public static function winWord(int $win): string
    {
        if ($win >= 1) {
            return self::LANG['arena_win'];
        } elseif ($win === 0) {
            return self::LANG['arena_deuce'];
        }
        return self::LANG['arena_lose'];
    }

    /**
     * 分享战报扣元宝（各 send_to_chat 的 member_model->sub_gold($price)），返回扣后的元宝（all_gold）；元宝不够返回 null
     */
    public static function chargeShare(int $uid, int $price): ?int
    {
        if (!Member::deductCurrency($uid, gold: $price)) {
            return null;
        }
        return (int)(DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('gold') ?? 0);
    }

    /**
     * 取我屏蔽的 uid 列表（get_ban_keys）
     *
     * @return list<int>
     */
    public static function banKeys(int $uid): array
    {
        $list = Redis::zRangeByScore(self::BAN_KEY . $uid, '(' . time(), '+inf');
        return array_map(intval(...), is_array($list) ? $list : []);
    }

    /**
     * 是否已屏蔽某人（get_ban_key）
     */
    public static function banKey(int $touid, int $uid): bool
    {
        $expire = Redis::zScore(self::BAN_KEY . $uid, (string)$touid);
        return $expire !== false && $expire !== null && (int)$expire > time();
    }

    /**
     * 屏蔽某人 24 小时（add_ban_uid）
     */
    public static function addBanUid(int $touid, int $uid): void
    {
        $key = self::BAN_KEY . $uid;
        $now = time();
        Redis::zRemRangeByScore($key, '-inf', (string)$now);
        Redis::zAdd($key, $now + self::BAN_TTL, (string)$touid);
        Redis::expire($key, self::BAN_TTL);
    }

    /**
     * 去掉屏蔽的人发的消息（index / read / ban_uid 的过滤）
     *
     * @param list<array<string, mixed>> $list
     * @return list<array<string, mixed>>
     */
    public static function filterBanned(array $list, int $uid): array
    {
        if ($list === []) {
            return $list;
        }
        $banned = self::banKeys($uid);
        if ($banned === []) {
            return $list;
        }
        return array_values(array_filter($list, static fn(array $m): bool => !in_array((int)($m['uid'] ?? 0), $banned, true)));
    }

    /**
     * ws 进程登记玩家连接：刷新在线标记并记下所在 ws 进程（推送用）
     */
    public static function markWs(int $uid, string $nickname, int $workerId): void
    {
        self::updateOnline($uid, $nickname);
        Redis::set(self::WS_KEY . $uid, $workerId, self::ONLINE_EXPIRE);
    }

    /**
     * ws 连接断开：去掉 ws 进程标记（之后的消息留在私人队列，客户端改回轮询 read 取）
     */
    public static function unmarkWs(int $uid, int $workerId): void
    {
        $current = Redis::get(self::WS_KEY . $uid);
        if ($current !== null && (int)$current === $workerId) {
            Redis::del(self::WS_KEY . $uid);
        }
    }

    /**
     * ws 进程取出待推送的 uid（各进程一个集合，多进程互不影响）
     *
     * @return list<int>
     */
    public static function popPushUids(int $workerId, int $count = 1000): array
    {
        $uids = Redis::sPop(self::PUSH_KEY . $workerId, $count);
        return is_array($uids) ? array_map(intval(...), $uids) : [];
    }

    /**
     * ws 进程启动：清掉本进程上次运行残留的推送通知
     */
    public static function resetPush(int $workerId): void
    {
        Redis::del(self::PUSH_KEY . $workerId);
    }

    /**
     * 发送单条消息（_send_message）；写进在线玩家私人队列时返回 true
     *
     * @param array{uid: int, nickname: string, country: int, rank: int}|null $sender
     */
    private static function sendMessage(string $message, int $uid, int $type, int|string $id, int $isSystem, int $country, int $tree, ?array $sender): bool
    {
        if ($uid === 0 && $type === self::TYPE_HORN) {
            return false;
        }
        if ($type === self::TYPE_COUNTRY && $uid === 0) {
            // 国家公共信息
            $key = self::COUNTRY_KEY . ($sender !== null ? $sender['country'] : $country);
        } else {
            $key = self::MESSAGE_KEY . $uid;
        }
        $rank = 0;
        if ($sender !== null && $sender['rank'] <= 3) {
            $rank = $sender['rank'];
        }
        $data = [
            'uid' => $sender['uid'] ?? 0,
            'type' => $type,
            'time' => date('H:i:s'),
            'nickname' => $sender['nickname'] ?? '',
            'message' => $message,
            'id' => $id,
            'rank' => $rank,
            'tree' => $tree,
            'system' => $isSystem,
        ];
        Redis::rPush($key, (string)json_encode($data, JSON_UNESCAPED_UNICODE));
        if ($uid !== 0) {
            // 消息队列生存时间与在线状态生存时间同步：在线状态过期，消息队列即过期（不在线时 TTL 为负，队列立即删除）
            $ttl = (int)Redis::ttl(self::ONLINE_KEY . $uid);
            Redis::expire($key, $ttl);
            return $ttl > 0;
        }
        // 公共消息
        Redis::expire($key, self::PUBLIC_TTL);
        return false;
    }

    /**
     * 通知收件人所在的 ws 进程推送（没有 ws 连接的玩家不通知，消息留在私人队列等 read）
     *
     * @param list<int> $uids
     */
    private static function notify(array $uids): void
    {
        if ($uids === []) {
            return;
        }
        $workers = Redis::mGet(array_map(static fn(int $uid): string => self::WS_KEY . $uid, $uids));
        $byWorker = [];
        foreach ($uids as $i => $uid) {
            $raw = is_array($workers) ? ($workers[$i] ?? false) : false;
            if (is_string($raw)) {
                $byWorker[(int)json_decode($raw)][] = $uid;
            }
        }
        foreach ($byWorker as $workerId => $list) {
            Redis::sAdd(self::PUSH_KEY . $workerId, ...$list);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function range(string $key): array
    {
        $list = Redis::lRange($key, 0, -1);
        $return = [];
        foreach (is_array($list) ? $list : [] as $value) {
            $decoded = json_decode((string)$value, true);
            if (is_array($decoded)) {
                $return[] = $decoded;
            }
        }
        return $return;
    }

    private static function prefix(): string
    {
        $redis = Redis::instance();
        return $redis === null ? '' : (string)$redis->getOption(\Redis::OPT_PREFIX);
    }
}
