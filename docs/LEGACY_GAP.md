# 旧版接口缺口清单

对比基线：前端 `public/static/js` 中写死的 `c`/`m` 调用（含单行对象、多行对象、`?c=…&m=…` URL、`x.c = …; x.m = …` 赋值四种写法）vs 新版 `src/Handler` 的 `public static` 方法 vs 旧版 CodeIgniter `wwwroot/{s1,uc,battle,war}/application/controllers/` 下全部控制器（含 `newequip/`、`lc/` 等子目录）。完全动态拼接的调用未覆盖。

- 前端调用：667；已实现：644；缺失：23（旧版有 17，旧版也无 6）
- 「旧版位置」为相对旧服务端 `HJServer/wwwroot/` 的路径；「—」表示四个旧服务里都没有对应方法（多为渠道 SDK 登录），需按前端字段反推。
- 标记 🆕 的控制器在新版中整个 Handler 都不存在。
- 本节由 `tools/scan_legacy_gap.php` 生成，勿手改；其余章节手工维护。

## 缺失接口（按控制器，缺口多的在前）

### login（6）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=login&m=binding_91` | `s1/application/controllers/login.php:423` | `static/js/view/binding91pop.js:140` |
| `c=login&m=user91` | `s1/application/controllers/login.php:58` | `static/js/view/loginworld.js:507` |
| `c=login&m=userdj` | `s1/application/controllers/login.php:122` | `static/js/view/loginworld.js:531` |
| `c=login&m=userdjn` | `s1/application/controllers/login.php:136` | `static/js/view/loginworld.js:543` |
| `c=login&m=userkuaiyong` | `s1/application/controllers/login.php:110` | `static/js/view/loginworld.js:568` |
| `c=login&m=userpp` | `s1/application/controllers/login.php:71` | `static/js/view/loginworld.js:519` |

### pay（5）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=pay&m=create_91_order` | `s1/application/controllers/pay.php:1241` | `static/js/view/miscpop.js:5081` |
| `c=pay&m=create_kuaiyong_order` | `s1/application/controllers/pay.php:1746` | `static/js/view/miscpop.js:5153` |
| `c=pay&m=create_pp_order` | `s1/application/controllers/pay.php:1388` | `static/js/view/miscpop.js:5117` |
| `c=pay&m=get_pay_info` | `s1/application/controllers/pay.php:512` | `static/js/view/miscpop.js:5042` |
| `c=pay&m=szf_order` | `s1/application/controllers/pay.php:835` | `static/js/view/miscpop.js:5335` |

### ios（2）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=ios&m=buylist` | `s1/application/controllers/ios.php:212` | `static/js/view/iospaypop.js:98`<br>`static/js/view/rebatepop.js:95` |
| `c=ios&m=prepay` | `s1/application/controllers/ios.php:246` | `static/js/view/miscpop.js:4286` |

### generaltask（1）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=generaltask&m=send_to_chat` | — | `static/js/view/miscpop.js:4628` |

### iostrack 🆕（1）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=iostrack&m=trackall` | `uc/application/controllers/iostrack.php:293` | `static/js/core/gamestart.js:75` |

### paydj 🆕（1）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=paydj&m=create_djpay_order` | `s1/application/controllers/paydj.php:152` | `static/js/view/miscpop.js:5176` |

### paydjn 🆕（1）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=paydjn&m=create_djpay_order` | `s1/application/controllers/paydjn.php:152` | `static/js/view/miscpop.js:5203` |

### report（1）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=report&m=send` | — | `static/js/core/base.js:2686` |

### user91 🆕（1）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=user91&m=login_ios` | — | `static/js/view/loginworld.js:855` |

### userdj 🆕（1）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=userdj&m=login_ios` | — | `static/js/view/loginworld.js:883` |

### userdjn 🆕（1）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=userdjn&m=login` | `uc/application/controllers/userdjn.php:17` | `static/js/view/loginworld.js:905` |

### userkuaiyong 🆕（1）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=userkuaiyong&m=login` | — | `static/js/view/loginworld.js:940` |

### weibocallback 🆕（1）

| 接口 | 旧版位置 | 前端调用 |
|---|---|---|
| `c=weibocallback&m=index` | — | `static/js/view/miscpop.js:6260` |

## 已实现但为写死 / 空壳的接口

方法存在，但不读写 DB / Redis，也不调用其他 Handler，返回值是固定内容。

目前没有。

## 已实现但待按旧版重写

接口能用，但玩法、数值或存储不是旧版的；当前战斗不读这些数据。

下表里「活动时间取 `ActivityTime`（… 已结束 / 未开启）」的运营活动，开发环境可用开发者开关 `ACTIVITY_OPEN`（`Model\ActivityClock`，见 README）临时打开；已移植的节日活动（春节系列、第二批：圣诞团购 / 圣诞礼包 / 万圣节 / 中秋 / 国庆 / 七夕 / 国家庆典 / 周年庆 / 清明，与第三批：踏青寻宝 / 我爱世界杯 / 蹴鞠大赛 / 七星灯 / 貔貅聚财 / 招募战友领工资 / 老兵召回 / 充值送豪礼 / 绝版神将 / 限时打折神将 / 限量神将）在 `all` 之内，其余节日活动、未移植的活动与不读活动表的跨服玩法不在 `all` 之内（群服争霸 `gvg` 的奖励计划任务读活动表，在 `all` 之内）。

| 接口 / 位置 | 现状 |
|---|---|
| `c=advanced`（`AdvancedHandler`、`Model\Advanced`） | 计划任务 `cron::update_advanced_log`（改配置后同步技能加成，运维用）与后台恢复进阶传承（`recover_inherit`）不移植 |
| 货币流水（`Model\FlowLog`，`Member::deductCurrency` / `addCurrency` / `addReputation` / `subReputation`） | 新版自定玩法里直接改 `member` 货币列的写法（征战掉落等）不记流水，随各玩法按旧版重写 |
| `c=message`（`MessageHandler`、`Model\Message`） | 依赖未移植：公告 `get_notice`、每日动态 `get_new_trends` / `update_trends_status` 的内容来自 uc 服务器 `inform` 接口（uc 的 `inform_model` 不在旧版代码包里），按 uc 没有返回数据处理；主城的 `notice_id` / `trends_id` 恒为 0；GM 信件的 `channel`（`member.channel` 未移植，记 0）。不移植：`platform` = android / wp8 的原生客户端文本转换 |
| `c=gmmail`（`GmmailHandler`） | GM 回复（`message_model->reply`，后台功能）不移植：没有后台时 `reply_uid` 恒为 0，`grade` 一律 -3 |
| `c=information`（`InformationHandler`） | `index` / `get_info` / `get_own_achievement` 待按旧版重写（仍是新版自定的展示；旧版 `index` / `get_info` 的国家学院三围 `_get_war_college_info` 随之接入 `WarCollege::applyInfo`） |
| `c=general_book`（`GeneralBookHandler`、`Model\GeneralBook`） | 计划任务 `cron::update_addition`（导入数据后全服重算，运维用）不移植 |
| `c=chat`（`ChatHandler`、`Model\Chat`、`WebSocket\ChatPush`） | 按旧版移植，GameWS 鉴权后改为 ws 推送。依赖未移植：`member.status` 3 / 4 封号的全局 403（只在发言时认禁言 1 / 2 与到期清除）、后台 / 计划任务喇叭（`horn` 表，`cron::date_horn` / `cron_horn` / `crontab_horn` / `send_horn`，运维功能）与计划任务 `cron::game_process` 的定时系统提示。不移植：`platform` = android / wp8 的原生客户端格式转换、`send_system_chat`（旧版不校验身份）、`clear_ban_key`（运维口令接口）、`illegal_chat` 记录（旧版调用已注释）。与旧版的差别：屏蔽字与重复发言按还原后的原文判断（旧版对 htmlspecialchars 后的内容判断，`&quot;` 含屏蔽字 q，带双引号的消息都被当成违规）；屏蔽玩家改为每人一个有序集合 `chat_ban_{uid}`（旧版每对一个键、`KEYS chat_ban__{uid}*` 会把 uid 前缀相同的玩家算进来）；`read` 过滤后重排下标（旧版 `unset` 留洞，JSON 变成对象） |
| 聊天室广播（其余玩法） | 依赖未移植：`generaltask&m=send_to_chat`（旧版没有） |
| 多人副本实时组队（`EliteRoom`） | 旧版实时服务器不在旧版代码包里，协议按 `elitepop.js` 还原，以下是客户端看不出旧版做法、按新版自定的部分：队伍名取队长昵称；不在开放时间登录回 `loginstatus` -3、服务端内部错误（加锁超时 / 异常）回各 `*_error` -9（客户端都落到 default 提示）；队伍人数：开发者决定：最多 3 人、最少 2 人（客户端语言键名为 `only_3_begin_battle`，旧版实时服务器的要求不可考）；创建 / 加入队伍不在服务端校验次数、VIP、元宝（客户端先校验，开战时 `fight` 校验）；`login` 的 `serverid` 不参与分组（新版单服）；`userin` / `userlogout` / `testmsg` 不发（客户端只打印日志）。`c=elite&m=action` 只供 `startfight` 内部调用，HTTP 返回 `{status: 0, info: []}`（旧版可由任何持 token 的调用方直接开战） |
| `c=map`（`MapHandler`、`Model\OpenMap`） | 依赖未移植：维护计划任务 `cron::clear_mission_report`（只保留每关最近 7 条首杀战报，旧版代码包里没有执行时间） |
| `c=worldboss`（`WorldBossHandler`） | 计划任务 `cron_acttask::worldboss` 的执行时间旧版代码包里没有，按键过期时间推定为每日 00:30（`WorldBossCron::RUN_AT`） |
| `c=pass_general`（`PassGeneralHandler`、`PassGeneralCron`） | 活动时间取 `ActivityTime`（2016-12-31 00:00 已结束，接口一律 -1） |
| `c=island`（`IslandHandler`） | 依赖未移植：维护计划任务 `cron::clear_island_report`（只保留每关最近 7 条战报，旧版代码包里没有执行时间） |
| `c=compete`（`CompeteHandler`、`CompeteCron`） | 活动时间取 `ActivityTime`（compete 2016-12-31 00:00 已结束，接口一律 -1）。计划任务 `cron::get_compete_reward` 的执行时间旧版代码包里没有，按每小时执行（只在活动结束后 10 小时内发放） |
| `c=siege`（`SiegeHandler`、`SiegeCron`） | 计划任务 `cron::siege_battle` / `clear_warstatus` 的执行时间旧版代码包里没有，按旧版逻辑推定为每分钟 / 每周六 00:00。都城效果已全部接入：征收次数（1）与通商次数（2）在 `getCityEffect` 内加次数，玉石作坊（3）、将魂星路（4）、四象圣域（5）、铜雀台互动次数（6）、通天塔（7）由各玩法按 value 加成 |
| 支付渠道（`c=pay` 下单 / 回调） | 待定渠道（渠道待开发者确定）：客户端调用的 `get_pay_info`（支付宝下单签名）、`create_91_order`、`create_pp_order`、`create_kuaiyong_order`、`szf_order`，以及旧版只由渠道服务器回调的 `confirm_alipay_order` / `confirm_91_order` / `confirm_pp_order` / `szf_confirm` / `googlepay_order` / `googlepay_confirm` / `elex_*` / `*_sf_*` / `*_kuaiyong_order`、网站充值 `create_prepay_order` / `complete_alipay_order` / `index`，与 `paydj` / `paydjn` / `pay360n` / `payaz` / `paydk` / `payhw` / `paytstore*` / `payuc` / `payxiaomi` / `payyidongyun` / `payyolo` 各渠道控制器、`c=ios&m=buylist` / `prepay` / `verify`（苹果内购；`verify` 只移植了没有收据回 -1，其余回 0 不到账）。接入时验签通过后调用 `Model\Pay::complete`；开发者测试用 `tools/pay_grant.php` |
| `c=pay`（`PayHandler`、`Model\Pay`、`Model\Vip`） | 依赖未移植：首充 `member_field.first_recharge_an`（安卓客户端标识，主城恒回 0） |
| `c=banquet`（`BanquetHandler`、`Model\Banquet`） | 依赖未移植：国宴开宴后的国家计划任务标志位 `country_model->set_flog`（消费方 `cron_country` 未移植） |
| `c=war_college`（`WarCollegeHandler`、`Model\WarCollege`） | 不移植：`level_up` 信件的 `platform` = android 原生客户端文本。MasterData `WarCollegeSkillInfo` / `WarCollegeGoldInfo` 是旧服各国的运行数据（不是配置），不读；新服各国的学院技能与税金从国家第一次打开学院时建档 |
| `c=member&m=index`（`MemberHandler`） | 运营活动图标按 `ActivityTime` 输出 `{name}` / `{name}_type` |
| 整军演武 / 四方来朝计划任务（`ExerciseCron`、`FlopCron`） | 旧版代码包里没有执行时间：`cron::clear_exercise` 按每日 07:00、`sale_gift` 与 `push_flop_reward` 按每小时执行（只在活动结束后 3 天 / 10 小时内发放），`clear_flop_times` 在发放窗口结束后执行 |
| 经济 / 交易玩法（`c=shop`、`c=countryshop`、`c=country_taxes_shop`、`c=countrysacrifice`、`c=sacrifice`、`c=workshop`、`c=mine`、`c=business`、`c=blackmarket`、`c=gambling`、`c=jadegamble`、`c=overseastrade`） | 依赖未移植：国家祭祀后的国家计划任务标志位 `country_model->set_flog`（消费方 `cron_country` 未移植）；矿区可收获的 iOS 推送计划任务 `cron::push_mine_list` / `mine`（设备 token 与推送服务）。每日通商任务表旧版由后台 `business_model->builder` 生成（旧版数据只到 2017-10-11），新版由 `Cron\BusinessCron` 按旧版 builder 补齐今天起 3 天。不移植：`c=overseastrade&m=check_team`（客户端不调用）、`c=business&m=clear`（运维口令接口） |
| `c=lottery`（`LotteryHandler`） | 依赖未移植：注册 120 秒内抽奖按刷接口封号时的封号记录 `stat_opear_user`（后台统计表；`member.status` = 4 已写，但全局封号 403 未移植，见 `c=chat` 行） |
| `c=treasuremap`（`TreasuremapHandler`、`TreasuremapCron`） | 计划任务 `cron::sale_map` 的执行时间旧版代码包里没有，按注释「跟征收一起更新」定为每日 07:00 |
| `c=beatengod`（`BeatengodHandler`、`BeatengodCron`） | 计划任务 `cron::get_beatengod_reward` / `get_beatengod_box_reward` 的执行时间旧版代码包里没有，按每小时执行（只在活动结束后 10 小时内发放）。活动时间取 `ActivityTime`（2016-03-20 已结束，接口一律 -1） |
| `c=newgift`（`NewgiftHandler`、`NewgiftCron`） | 计划任务 `cron::get_newgift_reward` 的执行时间旧版代码包里没有，按每日 00:10 执行。旧版 `newgift_reward` 数值很大（每天 188888 元宝等），照旧版数据，上线前请开发者确认 |
| `c=dice`（`DiceHandler`） | 不移植：计划任务 `cron::send_dice_reward`（修复领奖状态，旧版开头即 `exit`） |
| 节日活动第二批（`c=act_tanabata` / `act_celebration` / `act_moon` / `act_national_day` / `act_halloween` / `christmasbuy` / `christmasgift` / `anniversary` / `chingming`，`Cron\FestivalCron`） | 活动结束后的补发计划任务（`cron_acttask::acttanabata_reward` / `actcelebration_reward` / `clear_moon` / `get_nationalday` / `get_helloween_reward` / `get_christmasbuy`、`cron::get_chingming_reward` / `get_chingming_achievement_reward` / `push_pay_reward`）的执行时间旧版代码包里没有，按每 10 分钟执行（只在各自的结束后窗口内发放，万圣节窗口 1 小时）。依赖未移植：万圣节聊天发言送糖果（客户端 `chatpop.js` 在 `player.info.halloween` 为 1 时读发言返回的 `candy`；旧版 model 只有 `add_send_message_log` / `get_send_message_log`（按小时记 `halloween_sendmessage_candy_log`），旧版代码包里没有调用方，聊天控制器也不调用，每次送几个、每小时几次不可考，不臆造；聊天室已移植，规则确定后在 `ChatHandler` 发言成功处接入）；中秋好友 uid 的安卓合服转换 `exchange_uid`（`server_merge` 合服配置未移植）。不移植：圣诞签到 `c=christmas`（`sign_list` / `sign`）、圣诞充值返利领奖 `c=christmaspay`（`pay_reward` / `get_reward`）、中秋吃月饼 `c=mooncake`（客户端没有调用，只有主城图标；圣诞充值的记账 `add_christmas_log` 已在 `Model\Pay`） |
| 节日活动第三批（`c=act_treasure` / `worldcup` / `act_kemari` / `act_sevenstar` / `act_enrichment` / `act_paychecks` / `act_veteran` / `act_thirty` / `general_only` / `general_onsale` / `limitedshop`，`Cron\Fest3Cron`） | 计划任务执行时间旧版代码包里没有（推定）：`cron_acttask::treasure` / `new_pay_logined`、`cron_kemari::index` 每日 00:00，发工资统计 `action_paychecks` 每 30 分钟，`action_paychecks_reward` / `get_actenrichment_reward` / `sevenstar_task` 每 10 分钟（只在各自的结束后窗口内发放）。依赖未移植：招募战友领工资统计其他服的被邀请者（旧版 `uc_model->get_user_data_by_cron` / `get_codedata` 不在旧版代码包里，按 `uc_invitation_log` 还原，只统计本服，多服部署才需要）；限量神将的商品（uc 的 `limitedshop` model 与表不在旧版代码包和数据里，表按控制器还原，没有商品时 `index` 回 -2，由运维写入 `uc_limitedshop`）；寻宝计划任务奖励类型 18（旧版 `get_treasure_reward` 的分支在寻宝奖励表里没有对应数据）。不移植：`c=attack_japan`（客户端不调用） |
| `c=report&m=save`（`ReportHandler`） | 依赖未移植：分享页（旧版 report 服务器的展示页不在旧版代码包里：没有配置 `REPORT_URL` 时战报照样存进 `uc_report`，但回 -2）。与旧版的差别：战神殿战报的分享编号保留「场次_第几局」（旧版 intval 后同一场三局撞号）。不移植：`c=report&m=send`（客户端发往外部错误收集地址，不是游戏服接口） |
| `c=register&m=binding`、`c=password&m=set_pwd`、`c=user&m=get_info_by_temptoken`（原 uc 服） | uc 的 `register_model` / `password_model` / `user_model` 不在旧版代码包里，按控制器的状态码与客户端规则还原：只操作登录 token 对应的本人帐号。依赖未移植：官网生成 temptoken（`create_temptoken`，新版会话 token 即 temptoken） |
| 跨服部署（`src/Cross`：`Server`、`UcModel`、`WorldArenaServer`、`GodServer`、`WarServer`、`GvgServer`） | 按单服部署，旧版 uc / battle 服务器在本进程内，表加前缀 `zc_sanguo_uc_` / `zc_sanguo_battle_`，按 `serverid`（config `app.server_id`）分区。uc / battle 的 model 不在旧版代码包里，按控制器与表结构还原（代码注释注明「按控制器与表结构还原」）。依赖未移植（多服部署才需要）：其他服玩家的队伍（跨服演武 `battle_world_pk`、战神殿同步其他服玩家）、其他服的演武榜前三 / 神树、跨服信件投递、战帝上线通知其他服（`god_login`）、武斗会同步其他服报名玩家、群服争霸其他服的演武榜前 30 与队伍同步（`fetch_member` / `sync_info`）；多服部署需要各服共用 uc / battle 的表（共用库或拆成独立服务），见 README |
| `c=worldarena`（`WorldarenaHandler`、`Model\WorldArena`、`Cross\WorldArenaServer`、`WorldArenaCron`） | 计划任务执行时间旧版代码包里没有（推定）：本服前三 `cron::cache_top_rank` 与跨服排行重置 `battle cron::reset_rank_list` 每日 00:01，神树同步 `reset_member_tree` 每 10 分钟（0:10 前不执行）。还原时推定的部分：排行榜每页 10 人、商城记录取最近 10 条、支持数存 Redis 哈希 `worldarena_support` 并在每日重置时清空、每日重置时退出前三的删除、剩下的重新排名、新进前三排在后面、排行榜为空时按前三初始化。与旧版的差别：`send_to_chat` 先扣元宝再广播（扣不到回 2） |
| `c=god`（`GodHandler`、`Model\God`、`Cross\GodServer`、`GodCron`） | 依赖未移植：奖励（battle 库 `god_reward` 在旧版数据里 0 行；对战界面奖励为空，`get_reward_list` 回 -3、`get_reward` 回 -2、`get_reward_pre` 列表为空；奖励物品信息补全与发放随奖励规则移植）；`loading` 的战帝信息（旧版计划任务 `setplace_info` 取战斗服务器 `get_king`，该方法不存在，本服 `god_palace` 始终为空）。计划任务 `check_pk` 推定每分钟执行。还原时推定的部分：第一轮按报名人数取场数（不小于人数一半的 2 的幂）并随机配对、之后相邻两场的胜者配对；观战玩家每轮随机关注一场；队伍快照取 `TeamBuilder::forMember`（防守方）；战报列表的 `side` 为对手所在的一方；战神榜为 32 强；战帝堂一次返回全部（客户端只请求第 1 页）。照旧版：4 人以下的比赛 19 点即结束（结束时间缓存为 0） |
| `c=war`（`WarHandler`、`Model\War`、`Cross\WarServer`、`WarCron`） | 旧版 war 服务器的 `war` 控制器与 model 都不在旧版代码包里（`wwwroot/war` 只有 uc 控制器的副本），服务器端按 s1 调用方、客户端与 battle 库表结构还原，推定的部分：每月 19 日 0 点 ~ 26 日 18 点报名（member.php 的报名状态时段）、报名等级 80；26 日起每天 19 点开打，第 1 ~ 3 天（预赛 / 初赛 / 复赛）每天 5 轮、每轮 10 分钟一局定胜负，按当天胜场配对、单数轮空算胜，当天按胜场 / 积分 / 报名先后排名，前 128 / 64 / 32 名晋级；第 4 天（决赛）32 强淘汰赛、每轮三局两胜（每局 10 分钟），同战神殿；月初 5 天内仍显示上月的结果；每场积分取客户端 `tmpl.html` 的积分说明（battle 库 `war_reward` 0 行），胜者与支持胜方的玩家直接记入积分商城积分 `war_member_info`；支持在开打前 5 分钟截止（决赛圈只能在第一局）；显示的支持数用战神殿的公式；斗士堂为各期四强、武神榜为本期 32 强；`get_reward_list` / `get_reward` 没有物品奖励（回 -3 / -2）；计划任务每分钟执行。不移植：`palace` / `palace_report`（客户端不调用）、`cron_backwar`（2014 年的一次性数据回滚脚本，不是计划任务）。与旧版的差别：积分购买锁改为 SET NX（旧版先读后写，可能并发拿到）、`point_buy` 的 `id` 为 0 时回 -1（旧版取到整张表后按 0 积分发空宝箱） |
| `c=gvg`（`GvgHandler`、`Model\Gvg`、`Cross\GvgServer`、`GvgCron`） | battle 的 `gvg_model` / `user_model` / `server_model` 不在旧版代码包里，按控制器与表结构还原：对阵（参赛服务器随机排开，第一轮场数为不小于服务器数一半的 2 的幂，右方轮空）、关注（有比赛关注自己那场，否则随机）、客户端赛程图的 7 个键（缺的场次补空场）、参赛玩家按演武排名升序、比赛结果排序用的服务器最高等级、团战按同步的队伍快照打。计划任务执行时间旧版代码包里没有（推定）：`run_pk` 每分钟（只在旧版注释的 15 日 5 点 ~ 18 日 22 点内）、`bet_reward` / `push_member_reward` 每 5 分钟。照旧版：MasterData `GvgReward` 的旧版数据整行错了一列，按旧版代码读出来参赛奖励与全服奖励为空、押注奖励 2 元宝，晋级信件照发（奖励为空）；奖励计划任务要求活动表 `gvg` 开启（旧版数据从未开启，开发环境用 `ACTIVITY_OPEN`）；单服部署下本服只有自己参赛（开服次月起），每期一场轮空即冠军、没有团战战报。与旧版的差别：`cron_gvg` 发装备奖励时旧版把 uid 传到了 `add_item` 的 `$type` 位置，这里按本意发给该玩家。不移植：`clear` / `clear_report` / `test_swap_report`（运维与调试接口） |
| 春节送福（`Model\ActNewyear`） | 客户端没有春节送福界面（`public/static/js` 里没有 `c=act_newyear` 的调用）：控制器 `index` / `get_task_reward` / `buy_num` / `open_item` 不暴露，翻牌日志表 `act_newyear_flop_log` 未建；各玩法的任务计数与主城每日刷新已按旧版接入 |
| `c=newyearboss`（`NewyearbossHandler`、`Model\NewyearBoss`、`NewyearbossCron`） | 计划任务 `cron::get_newyearboss_reward` 的执行时间旧版代码包里没有，按每小时执行（伤害 / 鼓舞 / 闪避三种排行各一次，只在活动结束后 10 小时内补发）。活动时间取 `ActivityTime`（2016-12-31 00:00 已结束，接口一律 -1） |
| `c=springlottery`（`SpringlotteryHandler`） | 不移植：`cron::get_springlottery_reward`（旧版注释为手动发放，带忽略结束时间的参数，运维接口）。旧版 `copies_model->get_materials_info_by_arr` 不在旧版代码包里，按调用方还原。活动时间取 `ActivityTime`（starttime = endtime = 0，从未开放） |
| 年夜饭（`c=newyeardinner`） | 未移植：客户端没有年夜饭界面（`newyeardinnerpop.js` 不在客户端里，`miscpop.js` / `cd.js` 只有提示与倒计时），控制器 `index` / `get_member_list` / `get_menu_list` / `order_dishes` / `start` / `get_reward` 与手动补发 `cron::get_dinner_reward` 不暴露；`member_field.newyeardinner` 随之不建（见「已建表的缺失字段」）。主城 `dinner_get` 照旧版写死的 2013 年时段输出 |
| `c=ios&m=savetoken` | 依赖未移植：用 `member_field.devicetoken` 推送的计划任务（`iospushapi`，矿区可收获等） |
| 资源缺失：武将美术（`public/static/img/hero`、`img/avatar`） | 本地只有 image 1–114、201–212 的武将形象，旧版其余形象从已失效的 CDN `img2.hanjiangsanguo.com` 加载。神 / 魔将领已回退到原型将领的本地形象（`xGame.imgFallback`，`tools/gen_img_fallback.php` 生成，共 31 个，如神张飞 163→54、神关羽 157→58）；其中 116、154、156、160、342、348、418、452、453、461、473 的原型战斗图尺寸不同（301×210 / 260×210），站立图能显示，攻击动画仍按神将的 315×253 帧格（`hero_ini`）切图，可能错位。以下 222 个 image id 既没有本地美术、也找不到原型将领（MasterData 各表引用到的，`php tools/gen_img_fallback.php --missing`），客户端只显示名字 / 血条、头像为空，需要开发者提供美术：115、117、151–153、161–162、164、301–318、320、324–341、346、349–361、367–388、390–394、396–415、417、420–423、425–430、433–439、443、445、448、450–451、455–459、462–465、467–470、472、474–534、536–551、554、557 |

## 战斗

组队（`src/Battle/TeamBuilder.php`：`init_member_team_info` / `init_monster_team_info` / `team_return_arr`）与推演（`src/Battle/BattleEngine.php`：`battle` / `process` / `process_attack` / `process_effect` / `process_position` / `process_hurt` / `_battle_assess`）已按旧版移植。团战 `battle_rush`（`src/Battle/BattleRush.php`）已移植 PVP（type 0）、PVE（type 1 国战怪物 / 2 多人副本）与 EVP（type 3 怪物攻城）。

| 位置 | 现状 |
|---|---|
| 组队加成（`TeamBuilder::forMember`） | 依赖未移植，按 0 计：锻造套装 |
| 推演加成（`BattleEngine`） | 依赖未移植，旧版对应分支不移植：锻造套装（`suitbuff` 吸血 / 回气 / 偷气 / 清气 / 反伤，最终增伤 / 免伤、暴击伤害、血量伤害倍增、抗晕、血量%；坐骑模板的血量% / 抗晕 / 免伤 / 吸血已计入） |
| 技能 effect 30 / 31、攻击类型 16 | 旧版 `battle_model.php` 没有对应分支，按旧版照搬：655 袖里乾坤（type 16）没有受击位，656 飞花溅玉、668 地狱人间、669 逆命王轮只按 `ratio` 计伤害；技能描述里的偷气 / 眩晕 / 吸血 / 伤害上限等在这份旧版源码中没有实现 |
| 守军 | 只有 `c=map&m=action` 按旧版取 `Mission` → `Monster`、`c=tower&m=pk` 按旧版取 `SoulMission` → `SoulMonster`、`c=copies&m=pk` 按旧版取 `CopiesInfo`（怪物行的 position1~9 → `CopiesInfo` 的行）、`c=elite&m=action` 按旧版团战取 `EliteMission` → `EliteMonster`、`c=generaltask&m=action` 按旧版 `battle_general` 取 `GeneraltaskMission` → `GeneraltaskMonster`（玩家只上一名武将），`c=sanctum&m=action` / `c=heaven&m=pk` / `c=herothrone&m=action` / `c=island&m=pk` 按旧版 `battle_mission` 取 `SanctumMission` → `SanctumMonster` / `HeavenMission` → `HeavenMonster` / `HerothroneMission` → `HerothroneMonster` / `Island` → `IslandMonster`。`c=worldboss&m=battle` / `c=countryboss&m=battle` 按旧版 `battle_worldboss` 取 `WorldBoss` 行 1 / 2 + 写死数值（`TeamBuilder::forBoss`）。`c=compete&m=action`、`c=countrymine&m=rob`、`c=siege&m=occupy`（至尊攻城）按旧版 `battle_pk` 打玩家，国战按旧版团战取玩家或 `CountryMission` → `CountryMonster` |

## 数据结构缺口

旧版 `zc_sanguo` 库 632 张表（另有 `zc_sanguo_uc` 14 张、`zc_sanguo_battle` 37 张）；新版 `database/schema.sql` 314 张（含迁移记录表 `migrations` 与进程内 uc / battle 的 `zc_sanguo_uc_` / `zc_sanguo_battle_` 表），其余玩家状态约 90 类存于 Redis JSON 键。字段名取自旧版 `.frm` 文件。

### 已建表的缺失字段

| 新表 ← 旧表 | 旧版有、新版无的字段 |
|---|---|
| `member` ← `member` | `status`、`statustime`、`dateline`、`actdateline`、`levelupexp`、`ndgift`、`channel`、`regchannel`、`version`、`appversion`、`bind91`、`bind360` |
| `general` ← `own_general` | `disable`（`eid1`~`eid4` 不存储，由 `equipment.generalid` + 旧版 `get_item_site` 派生；其余旧字段已补，获得时从模板复制） |
| `equipment` ← `own_equip` | `glevel`、`disable`、`dateline`（`gid` 即 `generalid`；其余旧字段已补，同一武将同一装配位置由 `uk_general_site` 约束） |
| `member_field` ← `member_field` | `shopdateline`（旧版代码不读写）、`vip_reward`、`trends_times`、`newyeardinner`、`first_recharge_an`、`macaddr` |
| `member_activity` ← `member_activity` | 只有将魂星路字段（`towerlevel`、`towerstage`、`towermission`、`freetower`、`towertimes`、`souldate`）、多人副本字段（`elitetimes`、`elitebuytimes`、`elitebuydateline`、`elitesurplustimes`）、金银岛字段（`islandid`、`islandtimes`、`islanddate`）、四象圣域 `sanctumdate`、通天塔字段（`heavenstage`、`heavenmission`、`heavenlevel`、`heaventimes`、`freeheaven`、`heavendate`）、VIP 工资 `vipwage_dateline`、日常任务 `task_number`、每日对酒 `drinktime`、神将之门 `generaltasktimes`、整军演武 `exercise_gift` / `exercise_level`、四方来朝 `flop*`、天神祭祀 `sacrifice1`~`sacrifice3`、每日通商 `freebusiness` / `businesstimes` 、神矿 `jadetype` / `jadenum` / `jadeturn`、藏宝图奖励 `mapreward`、威震天下 `smashtime` 与中秋吃月饼 `freemooncake` / `mooncaketimes`（吃月饼 `c=mooncake` 客户端没有界面，只建档与每日重置）；缺 `towersite`、`treasuremap` |
| `practice` ← `own_practice` | 字段改名 `gid` → `generalid` |

部分字段可能已转存 Redis，补字段前先确认是否已有替代存储，避免两处真相。

### 新版无对应存储的旧实体

| 领域 | 旧表 |
|---|---|
| 公告 | `notice`（旧版公告取自 uc 服务器 `inform`，本服 `notice` 表只有后台读写） |

### 存储风险

大量玩家资产仅存于 Redis（背包状态等）。若 Redis 未开启 AOF / RDB 持久化，重启即丢档。

## 建议补齐顺序

0. 旧版配置表已生成到 `src/MasterData/`（`tools/gen_master_data.php`），补玩法时数值一律取自这里，不再手写配置。
1. 战斗：各玩法改用旧版怪物表（`TeamBuilder::forMonsters`）与旧版控制器的响应结构（`res.battle` / `res.info`）。
2. 军团体系的外围依赖：税金等（代码中 `依赖未移植` 注释标出）。
3. 其余缺口大的玩法（见「缺失接口」）。
4. 节日活动。
