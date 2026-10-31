# 悍将三国 (Hanjiang Sanguo) - 现代化重构版

> 基于 **PHP 8.4+** 与 **Workerman 5.x** 常驻内存架构重构的高性能三国策略卡牌游戏服务端。

[English](README.md) | [简体中文](README.zh-CN.md)

---

## 📖 项目简介

本项目是对经典三国九宫格策略卡牌手游/页游《悍将三国》服务端的现代化重构。

原版（2012~2015）服务端基于古老的 PHP 5.x + Apache + CodeIgniter 框架开发，采用传统的阻塞式 MySQL 查询与 HTTP 短轮询机制。虽然其九宫格战斗与策略养成玩法极其成熟，但多服拆分严重（`s1`、`war`、`battle`、`report`、`uc`），且单服承载力受限于 PHP-FPM 请求周期的生命周期开销。

本重构版本采用**常驻内存、全异步事件驱动**的架构模型，彻底消除了每次请求的框架初始化与解析损耗，单机吞吐与战斗推演性能提升数十倍，同时保持对原版前端协议的 100% 兼容。

---

## ⚡ 核心架构升级

| 特性维度 | 原版 2013 版 (PHP 5.x + FPM) | 现代化重构版 (PHP 8.4+ & Workerman 5) |
| :--- | :--- | :--- |
| **进程模型** | 请求结束即销毁进程，每次请求重复 bootstrap | **常驻内存进程池**，零启动开销 |
| **并发与 IO** | 同步阻塞式文件与数据库读写 | **事件驱动非阻塞 IO** (Revolt / EventLoop) |
| **战斗推演** | 依赖数据库读写与串行逻辑，单次约 10~50ms | **纯内存确定性推演**，单次 < 0.1ms |
| **状态同步** | 客户端每秒发送 HTTP 短轮询拉取聊天和战报 | 原生 **WebSocket 双工实时广播** |
| **媒体流式传输** | 原版静态直出，Safari/iOS 流媒体加载挂起 | 标准 **HTTP 206 Partial Content** 字节范围切片与全捕获音频唤醒 |
| **语言规范** | 无类型约束、数组 Notice 频繁的 PHP 5.3 | **严格类型 `declare(strict_types=1)`**、枚举、只读类、Match 表达式 |
| **部署维护** | 强依赖 Windows / WAMP / IIS 一键端 | 纯单命令 CLI / Docker 一键容器化部署 |

---

## 📂 项目结构

```
.
├── config/                  # 服务端配置目录（经 OmniPHP\Config 读取；环境变量 / .env 覆盖默认值）
│   ├── app.php              # HTTP / WebSocket 监听地址、Worker 进程数、客户端资源配置
│   ├── database.php         # MySQL 连接（`default` => mysql）
│   └── cache.php            # Redis 连接（键前缀 `hj:`）
├── public/                  # 游戏前端完整静态资源
│   ├── index.html           # 客户端横屏动态适配与入口
│   ├── tmpl.html            # 全局 UI 弹窗与场景模板
│   └── static/              # CSS、JS、音效及核心数值表
│       ├── js/data/ini.js   # 核心战斗数值表与武将属性 (442KB)
│       └── js/view/         # 前端视图引擎 (battleworld, cityworld, mapworld)
├── src/
│   ├── Battle/              # 九宫格战斗引擎（移植旧版 battle_model，随机数可注入）
│   │   ├── BattleEngine.php # 移植旧版 battle() / process / process_effect / process_hurt，输出旧版战报结构
│   │   ├── BattleRush.php   # 移植旧版三路团战 battle_rush（国战玩家对战、国战 / 多人副本怪物、怪物攻城）
│   │   ├── Position.php     # 九宫格战位 (1-9号位) 枚举
│   │   ├── LegacyStats.php  # 旧版武将/装备数值公式（纯函数，逐条注明旧版出处）
│   │   └── TeamBuilder.php  # 旧版组队（玩家 / 怪物队伍、team_return_arr）
│   ├── MasterData/          # 旧版配置表常量数组（脚本生成，勿手改）
│   ├── Middleware/          # 中间件流水线 (完全对齐 smart-book 架构)
│   │   ├── PublicAssetsMiddleware.php     # public/ 静态文件、动态 config.js、autoload.js 不缓存、音频 Range
│   │   ├── CorsMiddleware.php             # 跨域与 OPTIONS 预检处理
│   │   ├── GameProtocolMiddleware.php     # 将 c=xxx&m=yyy 智能分发至对应 Handler
│   │   └── ResponseEnvelopeMiddleware.php # 统一协议响应信封自动包装
│   ├── Handler/             # 领域驱动无状态动作处理器 (DDD 分层架构)
│   │   ├── Auth/            # 接入与账号认证 (Ios, User, Login, Register)
│   │   ├── City/            # 主城基础设施、征收与福利 (City, Member, DailySign, Vip)
│   │   ├── General/         # 武将、将魂、主公星魄与天赋、培养与酒馆 (General, Soul, Star, Talent, Tavern)
│   │   ├── Equip/           # 铁匠铺、神装铸造与宝石 (Pack, Strengthen, Refine, Gem, Forge)
│   │   ├── Campaign/        # 九宫格战役关卡、战位与试炼 (Map, Matrix, Tower, Copies, Sanctum)
│   │   ├── Pvp/             # 演武竞技、天下名城与国战 (Arena, Compete, Country, Siege, War)
│   │   └── Activity/        # 四海通商、博彩、夜宴与任务 (Chat, Shop, Trade, Dice, Task)
│   ├── Http/                # HTTP 协议层与网关
│   │   └── ExceptionHandler.php # 未捕获异常 → 500 + 旧版信封 {"status":-500,...}
│   ├── Support/
│   │   └── Connections.php  # 进程级 MySQL / Redis 初始化（onWorkerStart、命令行工具、PHPUnit）
│   └── WebSocket/           # WebSocket 网关（18101 端口）
│       ├── WebSocketHandler.php # 连接生命周期、心跳、鉴权
│       ├── ChatPush.php     # 聊天室实时推送（弹出玩家的旧版聊天队列）
│       └── EliteRoom.php    # 多人副本实时组队房间（按旧版 elitepop.js 协议）
├── tools/
│   ├── gen_master_data.php  # 从旧版 mysqldump 重新生成 src/MasterData
│   ├── pay_grant.php        # 开发者专用命令行：经充值核心手工到账（测试 VIP 用）
│   └── scan_legacy_gap.php  # 刷新 docs/LEGACY_GAP.md 中脚本生成的部分
├── bootstrap.php            # 所有入口第一行 require：OmniPHP Application::boot（路径、.env、时区、日志级别、CrashHandler）
├── routes.php               # OmniPHP Router：全局中间件管道 + 兜底路由
├── server.php               # Workerman 服务启动脚本（Fiber 事件循环、Kernel::handle）
├── runtime/                 # pid 与日志（git 忽略，启动时自动创建）
├── composer.json            # Composer 依赖与 PSR-4 自动加载
├── README.md                # 英文说明文档
└── README.zh-CN.md          # 中文说明文档
```

---

## 🚀 快速上手

### 环境要求

- **PHP >= 8.3**（推荐 PHP 8.4+）
- 扩展要求：`ext-pcntl`、`ext-posix`、`ext-json`、`ext-mbstring`、`ext-pdo_mysql`、`ext-redis`
- Composer 包管理器

### 安装与运行

1. 克隆代码仓库：
   ```bash
   git clone https://github.com/owner888/sanguohanjiang.git
   cd sanguohanjiang
   ```

2. 安装依赖：
   ```bash
   composer install
   ```

3. 导入标准数据库结构：
   ```bash
   mysql -uroot -proot -e "CREATE DATABASE IF NOT EXISTS zc_sanguo;"
   mysql -uroot -proot zc_sanguo < database/schema.sql
   ```
   已有数据库改为升级：按文件名顺序执行 `database/migrations/` 中比现有结构新的文件（每个文件开头写明作用）。`20261031_01_general_equip_legacy_cols.sql` 假定武将/装备表只有测试数据：会清空这两张表，并给每个玩家重新发放旧版初始武将。`20261031_03_matrix_legacy.sql` 把每个玩家一行的 `matrix` 表转成旧版每个阵法一行，按原阵法行写入 `member_field.matrixid`，按原 `pos` / 阵法站位重建 `general.goout`，然后删除 `general.pos` / `general.matrix_id`。`20261031_04_arena_legacy.sql` 假定演武榜只有测试数据：重建演武榜相关表（不保留旧排名）、新增积分表，并把所有玩家 `member_field.rank` 归 0，玩家下次打开演武榜时按旧版重新入榜。`20261031_05_tower_legacy.sql` 假定将魂星路（tower）进度只有测试数据（原先只存 Redis `tower_progress:{uid}`）：新建旧版星路表（`member_activity`、`soul_open_map`、`soul_log`、`soul_report`）并给 `member_field` 加 `soultimes`；旧进度不转换，所有玩家从第 1 关重新开始。`20261031_06_copies_legacy.sql` 假定平乱副本（copies）只有测试数据（原先只有 Redis 里的今日次数 `copies_times:{uid}`）：（重新）创建旧版副本表（`copies_beat`、`copies_beat_log`、`copies_report`）与锻造材料表（`own_materials`、`own_materials_log`）；不转换任何数据，玩家首次打开某个副本时按旧版建档。`20261031_07_elite_legacy.sql` 假定多人副本（elite）只有测试数据（原先只有 Redis 里的今日次数 `elite_times:{uid}`）：（重新）创建 `elite_log` / `elite_mission_log`，并给 `member_activity` 加 `elite*` 字段；不转换任何数据，已有活动记录的玩家多人副本次数为 0，到下次每日重置才恢复（文件末尾的可选 `UPDATE` 可立即给 10 次）。`20261101_batch1.sql` 假定切磋（challenge）、天下史诗（epic）、过关斩将（pass_general）只有测试数据（原先只存 Redis `challenge_master:{uid}`、`epic_cleared:{uid}`、`pass_general_stage:{uid}`）：（重新）创建 `epic_log` 与 `passgeneral*` 各表，并更新 `member_integral` 表注释；不转换任何数据，上述 Redis 键不再读取。`20261101_batch3.sql` 假定群雄逐鹿（compete）、国家矿脉（countrymine）、国战（siege）只有测试数据（原先只存 Redis `compete_score:{uid}`、`compete_times:{uid}`、`user_country_mine:{uid}`、`siege_reward:{uid}` 与新版自定的 `zc_sanguo_siege` 表）：删除 `zc_sanguo_siege`，新建旧版 `compete`、`compete_action_log`、`country_mine`、`country_fight_notice`、`country_city`、`country_city_report`、`country_siege_log` 表并给 `member_field` 加 `oncountrymine`；不转换任何数据。`20261101_items_storage.sql` 补齐旧版物品存储：`member` 新增 `souluse` / `soulpack` / `jade` / `gem` / `gemuse` / `refinestone` / `colorstone` / `bloodstone`（紧跟 `soul`；已有玩家的 `soulpack` 按旧版注册值补为 4），新建 `trader_log`、`own_gem`、`own_mount`、`own_soul` 及各流水表（`tavernjade_log`、`jade_log`、`stone_log`、`gem_log`、`itemchip_log`、`talent_scroll_log`、`mountchip_log`、`mount_info_log`、`debris_log`）；装备碎片、天赋卷轴、装备精华同旧版 `own_equip` 仍存 `equipment`。假定 Redis 里的物品数据只有测试数据（SQL 也读不到 Redis），不转换任何数据；`user_trader:{uid}`、`user_gems:{uid}`、`equip_gems:{uid}:{eid}`、`user_mounts:{uid}` 不再读写，可自行删除。`20261101_growth1.sql` 按旧版移植将魂（soul）、主公星魄 / 命格（star）、主公天赋（talent）：删除新版自定的 `zc_sanguo_general_soul`，新建旧版 `own_general_soul`（将领将魂槽）、`own_soul_mergelog`、`usesoul_log`（原魂流水）、`own_star`、`star_tree`、`star_log`、`own_star_addition`、`own_hero_talent`。假定原孔位等级与 Redis `general_star:{uid}:{gid}` / `general_talents:{uid}:{gid}`（新版自定的逐将升星 / 天赋）只有测试数据：不转换任何数据，这些键不再读取，可自行删除。`20261101_growth2.sql` 按旧版移植铜雀台美人（beauty）：新建旧版 `beauty_own_log`、`beauty_own_chip`、`beauty_info`、`beauty_loot_list`、`beauty_loot_log`、`beauty_active_log`、`beauty_buyloot_log`、`beauty_buyrose_log`、`beauty_speical_reward_log`（表名拼写同旧版），玫瑰花 / 钻石沿用 `member_integral`。假定 Redis `user_beauties:{uid}`（新版自定的美人数据）只有测试数据：不转换任何数据，该键不再读写，可自行删除。`20261101_growth3.sql` 按旧版移植名将进阶（advanced）、装备精炼（refine）、名将谱 / 名将成就（general_book）：新建旧版 `advanced_log`、`advanced_inherit_log`、`refine_master_log`、`refine_log`、`refine_inherit_log`、`own_achievement`、`own_addition`、`own_achievement_log`，并给 `member_field` 加 `advanced_time`（这句 `ALTER TABLE` 只能执行一次）。假定新版自定的品质飞升、Redis 精炼词条 `equip_refine:{uid}:{eid}` 与名将谱羁绊 `general_book:{uid}` 只有测试数据：不转换任何数据，这些键不再读取，可自行删除；精炼等级沿用已有的 `equipment.hammer`。`20261102_pay.sql` 补齐旧版充值 / VIP 存储：新建 `pay_order`（旧版各渠道结构相同的成功订单表 `alipay_order` / `91pay_order` / `pppay_order` 等合并为一张，订单号唯一）、`pay_log`、`vip_log`、`recharge_list_log`、`newyear_vipreward_log`、`jump_rebate` / `jump_rebate_order`，并新增 `member.rebate_firstpay`、`member_field.rebate_dateline` / `rebate_version`、`member_activity.vipwage_dateline`（这几句 `ALTER TABLE` 只能执行一次）；`member.vip` 默认值改为旧版的 0。假定新版自定的 VIP 状态只有测试数据：不转换任何数据，已有的 `member.vip` 保留，Redis `vip_salary:{uid}`、`vip_gifts:{uid}`、`vipreward_claimed:{uid}`、`vipwage_date:{uid}` 不再读写，可自行删除。 `20261102_q_daily1.sql`（文件名带 `q_` 使其排在 `20261102_pay.sql` 之后，它用到该文件新增的列）新增旧版信件、任务与流水存储：`pm`、`pm_report`、`gm`、`task_log`、`main_quest_log`、`main_quest_reward`、`gold_log`、`silver_log`、`act_log`、`reputation_log`、`stat_pay`，并新增 `member.newpm` / `newmail`、`member_activity.task_number`（这几句 `ALTER TABLE` 只能执行一次）。假定新版自定的信件 / 日常任务 / 主线任务只有测试数据：不转换任何数据，Redis 键 `user_messages:{uid}`、`user_daily_tasks:{uid}:{Ymd}`、`mainquest:task_id:{uid}` 不再读写，可自行删除。 `20261103_map.sql` 按旧版移植主线推图存储：新建 `open_map`、`mission_log`、`mission_report`、`mission_rewardlog`、`map_action_log`、`pub_log`，新增 `member.missionworld` / `firstworld` 与 `member_field.start_time`（这几句 `ALTER TABLE` 只能执行一次），删除新版自定的进度表 `zc_sanguo_map` 与假数据宝箱表 `zc_sanguo_mission_newreward`。已有进度按真正打赢过的关卡转换：`zc_sanguo_map` 有记录的每个势力一行 `open_map`（取最大场景及该场景打赢的最大关卡），每条记录的 1 ~ N 关补首杀 `mission_log`；新版自定流程只是把玩家推过去、并没打过的场景 / 势力不保留（例如打赢 1-1-5 后从 1-1-6 继续）；每个玩家至少有势力 1 的记录；停留位置超过转换后进度的拉回；自定宝箱的领取记录软删除；Redis `user_shili_claimed:{uid}:{id}` 不再读取。`20261103_q_daily2.sql`（排在 `20261103_map.sql` 之后）新增煮酒 / 宴会 / 谏言 / 武将任务 / 国家学院 / 培养 / 传承 / 突飞日志等旧版表，新增 `member_field.baijintimes` / `zhizuntimes` / `ongeneral` / `free_inherit` / `general_sale` / `inherit_dateline` 与 `member_activity.drinktime` / `generaltasktimes`，`member_field.freeleap` 默认值改为 0，并补齐缺失的 `practice` 训练位 1..`practiceall`。

4. 启动服务：
   ```bash
   # 调试模式（前台运行，Ctrl+C 停止）
   composer server:start

   # 守护进程模式（后台常驻）
   composer server:daemon

   # 重载代码 / 查看状态 / 停止服务
   composer server:reload
   composer server:status
   composer server:stop
   ```

5. 执行全量自动化测试套件：
   ```bash
   composer test
   ```

   开发者手工充值（尚未接入支付渠道）：`php tools/pay_grant.php <uid> <productId>` 按商品 `productId`（`AlipayProduct`，不带参数运行可列出全部商品）给玩家 `uid` 到账，走的是渠道回调将来使用的同一个充值核心（`Hanjiang\Model\Pay::complete`：元宝、VIP 累计、首充状态、信件、`pay_order` / `pay_log`），订单号以 `manual_` 开头。只是命令行脚本，任何 HTTP 路由都调不到它。

   游戏内测试充值：以 `PAY_TEST=1` 启动服务器（`config/app.php` 的 `pay_test`），设置弹窗会出现「测试充值」按钮，列出商品；每个商品调用 `c=pay&m=test_grant&id=<productId>`，给当前登录玩家走与 `pay_grant.php` 相同的核心到账（`Pay::grantManual`，渠道 `manual`）。`c=pay&m=test_info` 返回 `{enabled, list}`，客户端仅在开启时显示按钮。`PAY_TEST` 不为 `1`（默认）时 `test_grant` 返回 status `0`，什么也不做。**生产环境绝不能开启 `PAY_TEST`**——任何玩家都能免费刷元宝。

   **配置**：`config/*.php` 里每一项都先读环境变量；本地覆盖写在项目根目录的 `.env`（每行 `KEY=VALUE`，git 忽略，由 `bootstrap.php` 加载；真实环境变量优先于 `.env`）。常用键：`HTTP_LISTEN`（默认 `http://0.0.0.0:18100`）、`HTTP_WORKERS`（4）、`WS_LISTEN`（`websocket://0.0.0.0:18101`）、`WS_PUBLIC_PORT`、`DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD`、`REDIS_HOST` / `REDIS_PORT` / `REDIS_PASSWORD` / `REDIS_DB`、`APP_TIMEZONE`（默认 `Asia/Shanghai`）、`LOG_LEVEL`（`info`）、`PAY_TEST`。改 `.env` 或 `routes.php` 必须 `restart`，`reload` 不会重读。

   **运行时文件与日志**（都在 `runtime/` 下）：`hanjiang.pid`；`logs/hanjiang.log`（Workerman master：启动 / 停止 / 子进程退出）；`logs/hanjiang_stdout.log`（守护模式的标准输出：每个请求的访问日志、PHP warning / fatal、未捕获异常）；`logs/{级别}_YYYYMMDD.log`（OmniPHP Logger，如未处理的 HTTP 异常进 `error_*.log`、协议降级进 `warning_*.log`；保留 `LOG_KEEP_DAYS` 天，默认 5）。

   **事件循环**：Worker 跑在 Workerman 的 Fiber 驱动上（Revolt，`revolt/event-loop`），每个连接事件、定时器回调都在独立 Fiber 里执行。MySQL（PDO）与 Redis（phpredis）都是同步调用，请求不会中途挂起，一个进程同一时刻仍只处理一个请求；请求级状态放在 `Context` 或 Fiber 本地存储（`Workerman\Coroutine\Context`，`FlowLog` 在用），绝不放静态属性。数据库迁移不会自动执行：OmniPHP 的 MigrationRunner 只认它自己的 PHP 迁移类，`database/migrations/` 下的 `.sql` 按上文手动执行。

6. 打开游戏：
   在浏览器中访问：
   ```
   http://127.0.0.1:18100/
   ```

---

## 🎮 当前支持的协议与接口

网关无缝支持原版客户端通过 `?c={controller}&m={method}` 发起的各项业务请求：

- `c=ios&m=update_world_common`：客户端启动环境握手与模块清单验证
- `c=register&m=guest`：游客一键试玩账号创建与初始区服下发（按旧版：初始武将为模板 1 廖化，放在 1 号阵法 2 号位，不送初始装备）
- `c=register&m=reg`：正式账号注册
- `c=user&m=login`：账号验证与角色登录
- `c=login&m=guest`：单服试玩令牌签发
- `c=login&m=user`：单服正式账号验证与令牌签发
- `c=member&m=index`：玩家主数据、体力军令、背包银两及主城状态
- `c=member&m=select_role`：男女主公创角选角色与昵称绑定
- `c=member&m=get_guidestep`：新手剧情与引导步数同步
- `c=member&m=updatemissionsite`：新手引导记录推图位置（`missionsite`：1 世界、2 势力、3 场景；按旧版移植）
- `c=city&m=index`：主城建筑等级、征收次数与强征冷却
- `c=map&m=index|get_scene_list|get_mission_list|mission|action|clear_cd|report|report_detail|get_reward_list|get_mission_reward|send_to_chat|get_newreward_index|get_newreward|get_newresult`：主线推图 / 征战（按旧版 `controllers/map.php` + `models/map_model.php` 移植，`Hanjiang\Model\OpenMap`）：势力 / 场景 / 关卡取 `Map` / `Scene` / `Mission`；进度存 `open_map`（每势力一行：最大场景、该场景已通过的最大关卡、通关奖励领取状态），首杀与今日攻击次数存 `mission_log`，首杀战报存 `mission_report`。弹窗（`mission`、`get_mission_list`）与战斗（`action`）读同一行 `Mission`，守将名称 / 等级 / 首杀经验必然一致；守军为 `Mission` 站位 → `Monster`。`action` 消耗 `times` 个军令（1 次，或 VIP 3 起 5 / 10 / 50 / 100 次扫荡，首杀不能扫荡），当日免费次数（`maxtimes`）用完后按 `Price.storm` 收强攻元宝，首杀 / 普通经验（主公经验走 `Member::addExp`：旧版 80 × 等级² 升级、每级 +2 军令），按 `getrate` 掉落、按 `recruitrate`‰ 招募 `generalid`，打通场景 / 势力时开启下一场景 / 势力并把势力通关奖励记到 `member_field.newresult`（`get_newresult` 领取）。势力 1 场景 1 的首杀返回旧版新手引导预制战报（`Battle\NewbieBattleReport`）。关卡宝箱（`MissionNewreward`、`mission_newreward_log`）、注册 3 天内的闯关竞速（`MissionReward`、`mission_rewardlog`）、首杀攻略 / 回放与 2 元宝的聊天室分享均按旧版
- `c=general&m=index|get_info|equip|unequip`：将领界面，按旧版 `general.php` 移植（只列出征将领、旧版 `info_field`、装备显示能力含精炼 / 强化成就加成、旧版血量公式、旧版装配校验与状态码）
- `c=matrix&m=index|get_info|update_matrix|use_matrix|levelup`：阵法，按旧版 `matrix.php` 移植（每个拥有的阵法一行，启用的阵法记在 `member_field.matrixid`，只有出征将领能上阵，开放位置 / 加成 / 元宝升级价格取 MasterData `Matrix` / `MatrixLevel`）
- `c=pack&m=index|sale`：背包，按旧版 `pack.php` 移植（只列未穿戴、碎片 / 卷轴售价、VIP 11 强化返还、强化 10 级以上装备每日限售）
- `c=strengthen&m=index|strengthen_info|strengthen_start|clear_cd`：铁匠铺，按旧版 `strengthen.php` 移植（`EquipLevel` 花费与成功率、VIP 5 暴击 +2 级、写回等级 / 强化能力 / 售价；旧版强化从不累加冷却）
- `c=tavern&m=index|trade|trade_batch|get_list|buy`：酒馆，按旧版 `tavern.php` + `tavern_model` 移植（MasterData `Pub` / `Trader`；招募写 `pub_log`）
- `c=practice&m=index|practice_start|practice_stop|go_leap|mop|get_levelup_price|levelup|get_unlock_price|unlock|turn`：校场，按旧版 `practice.php` 逐行移植（`practice` 训练位表，`practice_stop` 结算已训练经验，`get_unlock_price` 用旧版固定内容，免费 / 白金 / 至尊突飞写 `leap_log` / `general_leap_log`；主公升级时重新结算训练位）
- `c=activity&m=index`：每日精彩活动列表总览、开放时段区间判定与签到红点气泡；世界 BOSS 项按旧版 `worldboss_init`（12:00–12:15、20:00–20:15，有未领奖励时亮红点）
- `c=chat&m=index|send|read|getuid|ban_uid`：聊天室（按旧版移植，`Hanjiang\Model\Chat`）：世界 / 国家 / 私聊频道，存储同旧版 Redis 队列（公共队列 30 条、国家队列 10 条、每个在线玩家一个私人队列），主公 50 级可发言、200 字节、3 秒间隔，含屏蔽字或重复发言只有自己可见，`member.status` / `statustime` 禁言，屏蔽某人 24 小时。系统公告、小喇叭与战报分享（`send_to_chat`，2 元宝）用旧版 `get_message_str` 模板。`GameWS` 鉴权后由 WebSocket 进程弹出该玩家的队列，以 `chat_message`（载荷同 `read`）推送，客户端不再轮询；HTTP 进程经 Redis（`chat_ws_{uid}` / `chat_push_{进程号}`）通知，HTTP 与 WebSocket 进程数任意
- `c=mainquest&m=index|get_task_reward`：主线任务（按旧版移植，配置取 `MainQuest`，`Hanjiang\Model\MainQuestTask`）：每条任务线在 `main_quest_log` 一行（从没有前置的任务开出 8 条线，「连续登录」线每天检查一次），进度由已移植的玩法推送（强化、武将、阵法、演武榜、多人副本、世界 BOSS、日常任务、将魂、将魂星路、征战首杀等），奖励（元宝 / 银币 / 军令 / 宝石 / 突飞卡 / 原魂 / 声望）在 `main_quest_reward` 记一次，之后换成该线的下一个任务。`c=member&m=index` 的 `mainquest` 是同一份列表
- `c=shop&m=index`：商城道具（旧版 `Shop` 表：兵符，装备 7、9–15、53–56）与军令体力购买
- `c=arena&m=index|action|buy_pk_num|get_reward|get_reward_list|clear_cd|send_to_chat|shop_index|shop_exchange`：演武榜（按旧版移植）：主公 26 级以「最后一名 + 1」入榜，挑战名次在前的玩家、胜则互换名次（带旧版战报），每日 15 次挑战与元宝购买次数，按昨日名次领演武积分，积分商城取 `ArenaExchange`
- `c=tower&m=get_scene_list|get_mission_list|get_mission_info|get_mopup_price|mop_up|pk|get_report_list|get_report_info|get_price|buy|reward_info|get_reward|send_to_chat`：将魂星路（按旧版移植，主公 51 级开放）：`SoulScene` / `SoulMission` 的 7 个星域、175 个关卡，守军取 `SoulMonster` 并带旧版战报，首杀原魂与攻略战报，元宝扫荡，每日 10 次免费挑战与元宝购买次数，以及按 `SoulReward` 元宝加倍的每日原魂奖励
- `c=pass_general&m=index|action|achievement_reward|action_achievement_reward|reward_info|action_reward|lottery_index|action_lottery`：过关斩将限时活动（按旧版移植，没有战斗）：按 `PassgeneralInfo` 的过人概率突破 4 层 11 名守将（每日 3 次免费，其余扣元宝），按 `PassgeneralReward` 加权发奖，逐层宝箱与超级大奖取 `PassgeneralBoxReward`，通关次数成就，兑奖券兑换（`ActkemariExchangeLottery`）；每日重置与活动结束结算见 `PassGeneralCron`。`ActivityTime` 里的活动已于 2016-12-31 结束，目前一律返回 -1
- `c=soul&m=index|get_soul_info|get_general_info|levelup|convert|equip|unequip|get_unlock_price|unlock|get_pack_info|merge_index|merge_action`：将魂（按旧版移植，主公 51 级开放）：将魂装进将领的 8 个槽位（按转生次数解锁，同类型只能装一件），花原魂升级、转换回原魂，元宝解锁包裹格，两个满级紫色将魂合成 16 级红色将魂。已装将魂在战斗中（转生 > 0 的将领）加士气 / 武力 / 智力 / 生命% / 闪避 / 识破 / 暴击率 / 暴击伤害 / 命中 / 精准 / 抗暴 / 抗晕，在将领界面加武力 / 智力 / 生命%
- `c=country&m=create|get_rank|get_member_list|apply|del_apply|get_apply_list|get_audit_list|ignore_all|audit|publish_notice|betray|make_way_info|make_way|kick_out_info|kick_out|get_usurp_info|usurp|storage|donate|get_salary|search|get_country_info|learn_index|learn|fight_notice`：玩家自建国家（军团，`member.country` 即国家 id）：建国、国家排行、申请与审核、国家公告、禅让、踢人、升官/篡位挑战、国库捐献、每日俸禄与国师授艺
- `c=cultivate&m=index|roll|save|giveup`：培养，按旧版 `cultivate.php` 移植（MasterData `GeneralCulture`；待保存结果缓存在旧版 key `cultivate_{uid}_{gid}`，写 `cultivate_log`）
- `c=dailysign&m=index|sign`：30天连续月度签到、阶梯式海量银两/元宝/军令封赏与防重复签到校验
- `c=logined&m=index|get_reward`：1~15天连续登录阶梯大礼包、登录天数递增与断签自动重置
- `c=pay&m=get_product_list`：充值商品（按旧版移植，`AlipayProduct`；返利活动 `rebate` 期间且玩家本期未充过值时取返利商品；某元宝档次首次购买前带「赠送 X 元宝(限购1次)」说明）。到账为旧版与渠道无关的核心 `Hanjiang\Model\Pay::complete(uid, productId, orderId, channel, amount)`：同一订单号只到账一次、同一玩家串行执行；发元宝（含返利 / 节日返利），按累计人民币（`Vip.toplimit`）提升 VIP，记首充与各档次首充（之后首次买到新档次再送该档元宝），VIP 3 清 CD、VIP 11~13 加天下史诗次数，发 VIP 信件，写 `pay_order` / `pay_log` / 跳楼返利订单。渠道下单与回调（`get_pay_info`、`create_91_order`、`create_pp_order`、`create_kuaiyong_order`、`szf_order` 等）待开发者确定渠道后接入
- `c=vip&m=index|get_gift|get_vip_reward`：VIP（按旧版移植）：VIP 等级与进度取 `vip_log`，各级特权说明与等级礼包（`Vip` + `VipReward`），礼包须按等级依次领取（装备、武将——VIP 10 以下已拥有时按酒馆价格 8 折折算宝石——、银币、宝石、玉石、将魂、突飞卡、声望）；`get_vip_reward` 同旧版恒回 0
- `c=vipwage&m=index|get_vip_wage`：VIP 工资（按旧版移植，`VipWage`，银币 × 主公等级，`double_vip` 活动期间翻倍），按自然日每天一次（`member_activity.vipwage_dateline`）
- `c=vipreward&m=index|get_reward`：VIP 回馈活动（按旧版移植，`NewyearVipreward`；活动 `viprepay` 2016-12-31 已结束，接口回 -1），每期每档领一次（`newyear_vipreward_log`）
- `c=rebate&m=index|get_reward`：大熊跳楼返利（按旧版移植；旧版数据中 `jump_rebate` 活动未排期）：活动期间累计充值满 300 元宝后，30 天内每天领 100 元宝
- `c=message&m=index|get_message|send_message|del_message|open_gift|update_read_status|report_detail|get_notice|get_new_trends|update_trends_status`：信件（按旧版移植，`Hanjiang\Model\Message`）：系统 / VIP / 演武 / 国家通知为气泡信件，玩家 / GM / 国家信件为邮件，存 `pm`（新信件提示 `member.newpm` / `newmail`，`c=member&m=index` 返回）；附件（元宝 / 银币 / 军令 / 突飞卡）只能领一次；收发双方分别删除；君主群发国家信件（发给别国花 100 元宝）；演武榜、切磋、铜雀台掠夺、群雄逐鹿、国家矿区、国家篡位的 PK 战报存 `pm_report` 供回放。公告与每日动态旧版来自跨服 uc 服务，这里为空
- `c=gmmail&m=index|get_message|send_message|del_message|grade`：新 GM 信箱（按旧版移植，`gm` 表）：玩家向 GM 反馈、给 GM 回复评分（GM 回复是后台功能，不移植）
- `c=friend&m=index|add_friend|del_friend`：高朋满座好友社交系统、结交盟友与防重好友管理
- `c=mine&m=index|caikuang|zhanling|get_silver|give_up`：富饶银矿与赤金矿脉占领、离线挂机采矿收益累积与定时收缴提取
- `c=beauty&m=index|get_beauty_info|get_say_info|wakeup_beauty|rest_beauty|peace|loot_index|loot_reflush|loot_action|buy_loot_times|send_to_chat|report_detail|advance_index|advance_action|level_up|level_up_status|cancel_levelup|active_index|active_action|gift|give_flower|buy_rose|action_special_reward|conversion_chip`：铜雀台美人（按旧版移植，主公 60 级开放）：`BeautyTemp` 七位美人，最多同时激活三位（60 / 90 / 130 级）；集齐六个碎片（`beauty_own_chip`）开始一小时的升级，每 20 级花黄 / 紫宝石、原魂、声望、玫瑰花、钻石进阶（`BeautyAdvanced`）；问候 / 亲近 / 宠幸每天各 5 次，提升好感度并抽 `BeautyPamper` 奖励；送玫瑰花（`member_integral.beauty_rose`）让技能生效 24 小时：战斗属性（`Beauty::addition`，计入组队、推演的闪避 / 识破 / 暴击伤害与将领界面）与资源产出（`action_special_reward` 领取）。碎片可以在掠夺中通过 `battle_pk` 从其他玩家手里抢（每天 10 次免费另可购买，50 元宝一小时免战，`BeautyLootBox` 宝箱奖励）
- `c=mount&m=index|get_info_by_id|get_level_info|levelup|fit_on|take_down|get_mount_by_page`：坐骑（按旧版移植，主公 120 级开放）：坐骑与坐骑碎片存 `own_mount`；将领带主战坐骑 `mid0` 与备战坐骑 `mid1`~`mid4`（主战全额、备战 ceil(× 0.3)，战斗组队读取）；同一将领不能同时带两匹同种特殊类型（1 / 2）的坐骑；升级吃掉其他未装配坐骑（其 `toexp`），按 `MountLevel` 升到 20 级（`mount_exp_log`，被吃坐骑记 `mount_info_log`）；经验马 101 / 102 不能升级
- `c=gem&m=index|get_gem_by_id|adron|pick_off|level_up|gem_turn|get_gem_info|get_gem_by_page|mergegem_index|get_20gem_by_page|merge_gem`：宝石（按旧版移植，主公 140 级开放）：宝石存 `own_gem`，宝石碎片 `member.gem`，格子占用 `member.gemuse`；按装备类型镶到阵上将领装备的 1~8 号位，升级花碎片（`GemLevelup` × 类型系数），宝石转碎片，两颗同类型 20 级宝石合成一颗 21 级宝石（`own_gem_mergelog`）
- `c=heaven&m=index|get_mopup_price|mop_up|pk|get_report_list|get_report_info|buy|reward_info|get_reward|send_to_chat|get_price`：通天塔（按旧版移植，主公 160 级开放）：`HeavenMission` 的 200 层，守军取 `HeavenMonster` 并带旧版战报，首杀奖励与攻略战报，每日 10 次免费挑战与 20 元宝购买次数，5 / 10 / 50 / 100 次扫荡，以及 2 / 4 / 8 倍元宝档位的每日奖励。精炼石 / 五彩石 / 上古血玉与装备精华照旧版发进 `MemberStone` / `Equipment`
- `c=worldboss&m=index|powerup|battle|reward`：世界 BOSS 刑天（按旧版移植，主公 46 级开放）：每天 12:00–12:15、20:00–20:15 两场、每人每天只能参加一场；BOSS 取 `WorldBoss` 行 1，血条按剩余时间减少，打法走旧版 `battle_worldboss`（带战报）；60 秒复活 CD（10 元宝立即复活），银币（等级 × 500）/ 元宝（10）鼓舞每次 +10%、上限 200%；结束后按个人累计伤害排名领演武榜积分（`WorldBossIntreward`，最后一击 × 1.5）。状态存 Redis（旧版键名，原子累计），未领的奖励由每日 00:30 的计划任务（`Hanjiang\Cron\WorldBossCron`，旧版 `cron_acttask::worldboss`）代领并清空。主城 `c=member&m=index` 的 `openboss` / `openbossreward` 同旧版
- `c=siege&m=index|capital_info|occupy|battle_prepare|join_battle|action|get_reward|get_report|siege_notice`：国战 / 都城（按旧版移植；国家即玩家军团）：`CountryCityEffect` 的 13 座都城按全服主线最远进度开放；君主与前三官职在周五 18:00 前请战一座城（君主可花 1000 元宝、VIP 7 以上发起「至尊攻城」，与占领国君主单挑），成员在周六该城开战前 1 小时内报名，`Hanjiang\Cron\SiegeCron` 在开战前 3 分钟用旧版三路团战（`BattleRush`，玩家或 `CountryMission` NPC 军团）结算每座有争夺的城；玩家回放战报、按国家等级领奖（胜方 3 倍），占领国全体成员享受都城效果（征收 / 将魂星路 / 四象圣域 / 通天塔加成）。每周六 00:00 清除参战状态。`c=member&m=index` 的 `country_war` 等字段按旧版计算
- `c=gvg&m=index|betting|sync_info`：军团跨服 GVG 巅峰争霸赛对阵树、冠军押注竞猜与胜利分红结算
- `c=general_inherit&m=index|get_inherit_info|preview|go_inherit`：武将传承，按旧版 `general_inherit.php` 移植（MasterData `GeneralInherit`；免费次数 `member_field.free_inherit`，`inherit_times` / `inherit_log`）
- `c=blackmarket&m=index|buy|lock|refresh`：西域神秘黑市商人、稀世奇珍锁定保底与货架刷新
- `c=first_recharge&m=index|get_reward|lottery|action`：首充返利档位与天命转盘，按旧版 `first_recharge.php` 与 `FirstRecharge` / `FirstRechargeLottery` 数据移植；玩家状态存 `first_recharge_log`（首次充值时由充值核心写入）与 `member_field.first_recharge`
- `c=overseastrade&m=index|goout|trade`：海上丝绸之路远洋商贸、商队启航与满载珍宝凯旋利润结算
- `c=studfarm&m=index|action`：西域马场（按旧版移植，主公 120 级开放）：`MountRatio` 三档，花酒馆黄 / 紫宝石（`trader_log`）；每档首次必得该档最高品级（记 `mount_log`），之后按权重抽品级，再在该品级的 `MountTemp` 模板（id 小于 100）里等概率抽
- `c=jadegamble&m=index|mining|gambling|harvest`：翡翠原石赌石坊、选石开采、一刀切石与满色帝王绿玉石折现
- `c=treasuremap&m=index|refresh|sale`：摸金校尉古墓寻宝、罗盘寻龙分金升星与皇室地宫秘宝发掘
- `c=workshop&m=index|get_reward|levelup`：官府军械工坊流水线、精铁破甲箭生产、流水线扩建与军工收益折现银两
- `c=sanctum&m=index|select_map|get_mission_info|action|buy_times|get_mission_report_list|get_report_info|send_to_chat|get_reward`：四象圣域（按旧版移植，主公 140 级开放）：`SanctumMission` 的 5 个圣域 × 5 层 × 10 关，守军取 `SanctumMonster` 并带旧版战报，首通攻略战报，每日 10 次挑战与 20 元宝购买次数，元宝扫荡，以及按 `SanctumReward` 倍率的每日奖励。宝石碎片与宝石掉落照旧版发进 `OwnGem`
- `c=compete&m=index|get_reward_list|get_reward|buy_pk_num|action|send_to_chat`：群雄逐鹿（按旧版移植）：限时玩家排名活动（`ActivityTime` 的 `compete`，2016 年已结束，各接口同旧版返回「活动未开启」）；主公 26 级以「最后一名 + 1」入榜、挑战次数 100，挑战名次在前的玩家、胜则互换名次（旧版战报，给对方发信），2 元宝购买次数，挑战结束后按名次领取 `CompeteReward` / `NewcompeteReward` 奖励（前 200 名），未领取的由 `Hanjiang\Cron\CompeteCron` 在活动结束后 10 小时内自动补领。奖励里的坐骑与玉石照旧版发进 `OwnMount` / `MemberJade`
- `c=countrysacrifice&m=index|action`：国家宗庙九鼎太牢祭天大典、全国内阁攻击防御光环与声望庇护
- `c=expostulation&m=index|authstr|adopt|expostulation_list|put_expostulation|support|refresh|get_reward|get_expostulation_by_user`：帮派谏言，按旧版 `expostulation.php` 移植（MasterData `ExpostulationTemp`，`expostulation` / `expostulation_support` 表；任务计数由强化、地图、武将经验累加）
- `c=banquet&m=index|create_team|join_team|kickout_team|dismiss_team|getout_team|action`：国家宴会，按旧版 `banquet.php` 移植（MasterData `CountryBanquet`，队伍存 Redis `banquet_team_{country}_{caption}`，写 `country_banquet_log`）
- `c=drink&m=index|go_drink`：煮酒，按旧版 `drink.php` 移植（MasterData `Drink`，每日次数 `member_activity.drinktime`，写 `drink_log`）
- `c=dice&m=index|shake_dice`：逍遥神骰行大运、至尊豹子大顺子摇骰与金银暴击瓜分
- `c=sacredtree&m=index|watering`：聚宝通天摇钱神树、灵泉灌溉浇水成长与金叶玉果元宝银饷掉落收割
- `c=flop&m=index|turnover`：九宫翻牌夺宝、金边卡牌翻转揭秘与至尊元宝大奖
- `c=challenge&m=action`：切磋（按旧版移植）：与任意玩家免费 PK（演武榜同一套 `battle_pk`），返回旧版战报并给对方发信
- `c=elite&m=index|get_member_info|join_team|get_country_info|get_server|action|buy_number|sendinvite`：多人副本 / 团队副本（按旧版移植）：`EliteCity` 的 83 个军团（主线打通该军团最后一关后开启），守军取 `EliteMission` → `EliteMonster`，按旧版三路团战（`BattleRush`，即旧版 `battle_rush`）结算，首杀 / 再打的经验与道具奖励，VIP 8 元宝「至尊组队」加成，每日 10 次挑战与元宝购买次数（`Price.elite`），12:00–14:00、19:00–21:00 开放。旧版 `index` / `get_member_info` / `join_team` / `get_country_info` / `action` 由独立的实时组队服务器调用，该服务器不在旧版代码包里；新版在 WebSocket 网关上按客户端协议重建为 `EliteRoom`（见下条），`action` 只供内部调用：HTTP 返回 `{status: 0, info: []}`，开战只走 WebSocket 的 `startfight`
- 多人副本实时组队（WebSocket，`Hanjiang\WebSocket\EliteRoom`）：旧版客户端 `elitepop.js` 经 `socket.io.min.js` 适配层（上行 `{action, data}`、下行 `{type, data}`，每 20 秒发一次应用层 `ping`）连 `c=elite&m=get_server` 返回的地址（`socket_server_url`，留空则用 `HJ_CONFIG.wsUrl`）。事件：`login`（与 HTTP 同一套 Redis token；`loginstatus` 1 / 0 / -1 / -2 / -3）、`eliteindex`、`teamlist`（只推给正在查看该军团的玩家）、`createTeam`、`jointeam`（最多 3 人，国家队伍只限同国）、`exitteam`、`fireteam`、`firemember`、`changeorder`、`teaminvited`、`startfight`（先给全队 `readyfight`，再把 `EliteHandler::fight` 的结果作为 `enterfight` 推给每个队员）。队伍状态存 Redis，改动都在同一把全局锁内，多个 WebSocket 进程可共享；连接关闭或心跳超时即离开队伍（队长离开则解散）。客户端看不出旧版做法的部分见 `docs/LEGACY_GAP.md`
- `c=muster&m=index|get_general_info|go_battle|go_rest|sale_general`：点将台；`index` / `get_general_info` / `go_battle` / `go_rest` 按旧版 `muster.php` 移植（出征名单 `general.goout`，最多 8 人；休息时从所有阵法撤下、`confrim` 确认后终止训练、卸下全部装备与坐骑、至少保留一名出征将领）；`sale_general` 按旧版移植（武将折旧：名将榜武将按 `Pub` 售价 × VIP 比例返还，图鉴成就 / 战神殿 / 活动所得武将不可折旧；终止训练、下阵、卸下装备、将魂与坐骑，写 `member_field.general_sale` 冷却并重算 `general_book` 加成）；`index` 返回新武将气泡 `ongeneral`
- `c=business&m=index|go_business|buy|refresh`：四海通商行商经商（洛阳/成都/建业/襄阳）、商市行情采购与暴利返航折现
- `c=gambling&m=index|buy|cut|sell`：金银宝盆博彩、原石抛光雕琢与聚宝盆估价翻倍典当
- `c=task&m=index|accept_task|give_up|refresh|get_reward`：日常任务（按旧版移植，主公 41 级起，配置取 `Task`，`Hanjiang\Model\DailyTask`）：`task_log` 三个任务位，同时只能进行一个任务，进度存 Redis `task_plan_{ymd}_{uid}`，由已移植的玩法计数（元宝消费、演武榜、强化、世界 BOSS、天下史诗、金银洞、国家矿区、征战）；每天 6 次（`member_activity.task_number`，每日计划任务重置），每天第一次刷新免费、之后 5 元宝；奖励为银币 × 主公等级，另有装备碎片 / 紫宝石 / 玉石 / 声望
- `c=talent&m=index|levelup|get_info`：主公天赋（按旧版移植，主公 91 级开放）：物攻 / 物防 / 策攻 / 策防 / 生命五项天赋，最高 300 星，消耗玉石与天赋卷轴，可花元宝必成；星级 20 / 40 / 60 / 80 / 100 / 200 的下一星为顿悟，失败会掉星；加成作用于战斗中的全部将领与将领界面血量
- `c=advanced&m=index|get_general_info|get_skill_info|level_up|start_advanced|advanced_inherit|get_advanced_inherit_info`：名将进阶（按旧版移植，主公 100 级开放）：每个将领四个被动技能（物攻 / 物防 / 策防 / 生命天赋系数，最高 25 级），消耗突飞卡升级；技能与将领等级达标后花黄 / 紫宝石、原魂、声望进阶（品级 1 → 7，`AdvancedInfo`），增加三围与血量；进阶数据可传承给品级更低的将领（CD 按 VIP）。加成计入组队、推演的天赋系数 / 血量与将领界面
- `c=refine&m=index|item_index|item_selected|master_refresh|refinning|inherit|preview|achieve_index|get_achieve_info`：装备精炼（按旧版移植，主公 160 级开放）：精炼等级 0 → 99（`equipment.hammer`，`RefinePromote`），消耗玉石与精炼石 / 五彩石 / 上古血玉，成功率取当前精炼大师（`RefineMaster`，每天 04:00 / 12:00 / 20:00 免费刷新，也可花 100 元宝刷新）；精炼等级可在装备间继承；将领 4 件装备都精炼时达成精炼成就。精炼加成计入组队与将领界面
- `c=star&m=index|level_up|refining_index|refining`：主公星魄 / 命格（按旧版移植，主公 80 级开放）：`StarGrid` 的三页命格（最高 5 级，受前置命格与主公等级限制），消耗三种星魄；星魄在 `StarRefine` 的炼化炉用玉石、酒馆宝石、元宝提炼；累计加成作用于战斗中的全部将领（三围、血量、士气、攻防、命中、暴击率、暴击伤害、闪避、识破、抗晕、抗暴）与将领界面
- `c=war_college&m=index|level_up|use_buff`：国家学院，按旧版 `war_college.php` 移植（MasterData `WarCollegeSkill`，`war_college_*` / `warcollege_use` 表；技能加成作用于战斗、武将信息与 `get_country_info`）
- `c=lottery&m=index|action`：天赐幸运大转盘、五行扇区指针抽奖与至尊百两元宝大奖
- `c=generaltask&m=index|get_list|action|get_report_list|get_report|buy_number`：武将任务，按旧版 `generaltask.php` 移植（MasterData `GeneraltaskMission` / `GeneraltaskMonster`，每日次数 `member_activity.generaltasktimes` 重置为 10，`generaltask_*` 表）
- `c=general_book&m=index|get_book_list|get_achievement_list|levelup`：名将谱（按旧版移植，主公 81 级开放）：图鉴里的将领（`Book`）自带单将加成，集齐成就（`BookAchievement`）所需将领即达成，花酒馆宝石升到 6 品（`BookAddition`）；合计加成（`own_addition`）计入组队、推演的暴击率 / 闪避 / 识破与将领界面
- `c=copies&m=index|get_monster_list|get_monster_info|pk|get_mopup_price|mop_up|buy_times|get_report_list|get_report_info|send_to_chat`（客户端带 `d=newequip`）：平乱副本（按旧版移植，主公 110 级开放）：`CopiesInfo` 的 3 个副本 × 3 个难度 × 10 只怪（守军：怪物行的 position1~9 → `CopiesInfo` 行）并带旧版战报，`CopiesReward` 的首杀 / 掉落奖励（声望、装备、`MaterialsTemp` 锻造材料，存入 `own_materials`），首杀攻略战报，每只怪每天 5 次免费攻打与按 VIP 限次的元宝购买（`CopiesVipBuyTimes` / `CopiesBuyTimesGold`），1 / 5 / 10 次扫荡
- `c=island&m=index|get_mission|pk|get_report_list|get_report_info|send_to_chat`：金银岛（按旧版移植，主公 36 级开放）：`Island` 按主公等级每 10 级一组 5 名守护者，守军取 `IslandMonster` 并带旧版战报，每日胜场越多军令消耗越高，必得银两、按概率掉元宝，首胜攻略战报；每天进入时回到本等级组第一关
- `c=herothrone&m=index|go_back|start|action|buy_times|end_battle`：英雄王座（按旧版移植，主公 101 级开放）：`HerothroneMission` 的 300 层连闯，守军取 `HerothroneMonster` 并带旧版战报，过关奖励与每 10 层宝箱，每日 2 次免费挑战与按 VIP 限次的 1000 元宝购买，VIP 10 起战败后可花元宝继续。跨服排行（uc 服务器）未移植，排行列表为空
- `c=beatengod&m=index|beaten|get_box_reward`：真武战神金光试炼、敲击金身赐福掉落与战神宝箱领奖
- `c=war&m=index|entry|point_shop`：魏蜀吴皇城跨服封神国战大厅、出征报名参战与军功功勋商城兑换
- `c=countrymine&m=index|get_countrymine_info|caikuang|rob|get_reward|give_up|send_to_chat`：国家矿脉（按旧版移植，主公 91 级且加入国家）：10 页矿区，矿类型取 `CountryMineRatio`，每日 3 次开采、3 次抢夺，每次占领 15 分钟，抢夺其他国家玩家的矿（旧版 PVP 战报、信件与国家战报记录），收获 / 抢夺随机触发 `CountryMineEvent` 事件（紫宝石、元宝、玉石倍数）。玉石照旧版发进 `member.jade`（`MemberJade`）
- `c=countryboss&m=index|powerup|battle|reward`：国家 BOSS 蚩尤（按旧版移植）：每周五 20:30–20:45，国家（玩家军团）40 级、主公 50 级开放，每个国家各打各的 BOSS；BOSS 取 `WorldBoss` 行 2，血量 20 亿 × 1.03^往周击杀次数（击杀记录 `country_boss_log`），血量原子扣减，打死算胜利；60 秒复活 CD（20 元宝），银币（等级 × 1000）/ 元宝（20）鼓舞；按国家内伤害前 50 名领 `CountryBossReward` 的宝石（每 10 次击杀 × 1.1，最后一击 × 1.5）与装备碎片
- `c=hitegg&m=index|hit_egg`：金蛋纳福大暴击（吉祥金蛋/富贵银蛋/至尊彩蛋）、挥锤破蛋与元宝爆出
- `c=levelgift&m=index|get_reward`：主公等级成长里程碑礼包（5/10/20/30/40/50级）与成长基金一键领奖
- `c=countryshop&m=index|buy`：魏蜀吴国家特产商城、官营国税军资与红色绝品神装图纸兑换
- `c=country_taxes_shop&m=index|buy`：国家税金集市商城、税金资产核算与银两突飞卡军令限额兑换
- `c=equip_forge&m=index|fabrication|resolve`：天工神兵铸造工坊、神装开炉淬火打造与闲置装备熔炼提取精铁
- `c=totallogin&m=index|action`：全服开服累登狂欢大礼（1~14天全梯度封赏）、豪华银票元宝礼包一键申领
- `c=epic&m=index|select_epic|refresh|force|buy_lock_times|buy_refresh_times`：天下史诗拼图（按旧版移植，没有战斗，主公 150 级开放）：选择史诗后按 `EpicChip` 权重刷新 5 个位置（可锁定），拼成得声望（`Epic.reward`）或花元宝强行领取；每日刷新 / 锁定次数按 VIP 重置，可用元宝购买
- `c=activationcode&m=index|get_reward`：全服至尊特权激活码/CDKey兑换系统（VIP666/VIP888/SG888）、海量元宝银两秒级兑付与防重领取
- `c=exercise&m=index|open_gift`：全军整军经武大演习、整军礼包拆开与行军令军饷犒赏
- `c=information&m=index|get_info|get_own_achievement|get_all_beauty_info|get_own_soul|get_talent_list|get_general_gem|get_general_mount|get_country_info|get_star_info`：查看他人资料；美人、将魂、主公天赋、宝石、坐骑、国家学院、星魄按旧版移植（国家学院未按旧版移植前技能列表为空）；`index` / `get_info` / `get_own_achievement` 仍是新版自定的展示
- 货币流水（旧版 `member_model` 的 `_gold_log` / `_silver_log` / `_act_log` / `_reputation_log` / `_soul_log`，`Hanjiang\Model\FlowLog`）：经 `Member::deductCurrency` / `addCurrency` / `addReputation` / `subReputation` 的元宝 / 银币 / 军令 / 声望 / 原魂增减都记 `gold_log` / `silver_log` / `act_log` / `reputation_log` / `usesoul_log`，class / method 为当前请求的 `c` / `m`（计划任务记旧版计划任务名）；花元宝同时计日常任务 5。旧版 `stat_model` 的付费点统计记 `stat_pay`（`Hanjiang\Model\StatPay`）
- `c=invitation&m=index|change|get_reward`：结义金兰邀请招募、好友绑定双向获赠元宝与邀请人头里程碑达标大礼

---

## 🧪 自动化测试套件

所有 16 个核心业务接口与战斗推演闭环均由 PHPUnit 自动化集成测试全量覆盖（`tests/Feature`）；`LegacyStats` 中的旧版公式与 `TeamBuilder` 组队用旧版库真实行做金标准校验（`tests/Unit`）：

```bash
./vendor/bin/phpunit                      # 全部测试
./vendor/bin/phpunit --testsuite Unit     # 公式与组队（TeamBuilder 需要 MySQL，不需要 Redis）
./vendor/bin/phpunit tests/Feature/ItemStorageTest.php  # 物品存储模型（并发用例会起 tests/Support/storage_worker.php 子进程）
node tests/js/socket_io_adapter.test.js   # socket.io.min.js 适配层（帧格式、心跳、连接地址）
```

PHPUnit 经 `tests/bootstrap.php` 启动：与服务端同一份 `bootstrap.php` 和 `routes.php`，再为测试进程建立一条 MySQL / Redis 连接，所以 Feature 测试的请求走的是线上同一条中间件管道（`OmniPHP\Http\Router::dispatch`，经 `tests/Support/Gateway.php`）。`tests/Feature/HttpGatewayTest.php` 从 `OmniPHP\Http\Kernel::handle` 端到端驱动：静态文件及缓存头、动态 `config.js`、接口入口、CORS 预检、`403` 纯文本响应与 `-500` 异常信封。

多人副本实时组队端到端冒烟（启动真实服务，开放时间判断固定在开放时段内；只用于测试的入口）：

```bash
php tests/e2e/elite_smoke_server.php start -d
node tests/e2e/elite_smoke.mjs            # 两个游客：创建 → 加入 → 开战，双方都收到 enterfight
php tests/e2e/elite_smoke_server.php stop
```

---

## 🗺️ 迭代路线图 (Roadmap)

- [x] Workerman HTTP 网关搭建与静态文件服务器就绪
- [x] 兼容原版客户端参数分发器 (`c` / `m`)
- [x] 游客体验与账号登录流程打通
- [x] 纯内存确定性九宫格战斗推演原型开发
- [x] 桌面鼠标到触控事件全局自适应桥接（PC/移动端全端无感畅玩）
- [x] 选角创角与开场新手剧情完整串联闭环
- [x] 接入本地 Redis 高速缓存层 (`token`, `player`, `session`)
- [x] 接入本地 MySQL 数据库持久化存储与表结构迁移 (`zc_sanguo`)
- [x] 主城系统与征战关卡全线贯通 (`city`, `map`)
- [x] 战报回放与纯内存推图战斗全闭环
- [x] 16 个核心游戏玩法全面迁移为无状态 Handler 架构
- [x] 装备穿戴与卸下系统，支持全属性（攻击/防御/生命）实时动态加成计算
- [x] 铁匠铺装备强化系统 (`strengthen`)，按旧版 `EquipLevel` 花费、成功率与 VIP 强化暴击
- [x] 酒馆 (`tavern`) 按旧版移植，招募写 `pub_log`
- [x] 校场 (`practice`) 按旧版移植，含 `practice_stop` 结算与主公升级训练位钩子
- [x] 深度联动纯内存九宫格战斗推演，上阵武将与装备强化数值无缝作用于战场战力
- [x] 旧版阵法存储 (`matrix`)：每个阵法一行、启用阵法切换、防重上阵拦截、元宝升级；战斗读启用的阵法
- [x] 多武将同屏协同出征作战，真实九宫格站位多目标推演与战报回放
- [x] 演武榜 (`arena`) 按旧版移植：玩家名次挑战与互换、旧版战报、每日排名积分与积分商城
- [x] 主城征收与强征系统 (`city`)，支持丰收暴击银两倍数与元宝强征加倍收益
- [x] 原生 WebSocket 全双工长连接网关，支持鉴权与聊天室实时推送（旧版聊天队列到达即推）
- [x] 完全对齐 `smart-book` 标准的纯粹 RFC 6455 协议级心跳机制（0x9/0xA 控制帧 ping/pong，杜绝应用层冗余载荷开销）
- [x] 全面废除冗余的业务中间继承层，全站业务 Handler 与中间件直接原生消费 `OmniPHP\Http\Context`（完全对齐 Go Gin 标准）
- [x] 直接使用框架原生 `OmniPHP\WebSocket\Context` 与 `Broadcaster` 接管全双工事件载荷、用户会话与纯内存极速测试
- [x] 彻底删除业务端自建的 `src/Database/`，全站直连框架官方高可靠 `OmniPHP\Database\DB` 与 `OmniPHP\Cache\Redis`
- [x] 框架级引入 `OmniPHP\ArchitectureGuard` 架构铁律守卫，杜绝新老项目重复造轮子与私造基础设施
- [x] 前端网络层指数退避断线重连（Exponential Backoff with Jitter）与服务端超时僵尸连接自动回收扫描（`sweep`）
- [x] 将魂星路 (`tower`) 按旧版移植：旧版关卡与守军、战报、首杀、扫荡、购买次数与每日原魂奖励
- [x] 平乱副本 (`copies`) 按旧版移植：旧版怪物、战报、首杀与掉落奖励、锻造材料存储（`own_materials`）、扫荡与购买次数
- [x] 多人副本 (`elite`) 按旧版移植：军团、旧版团战（`battle_rush`）对 `EliteMonster`、首杀与掉落奖励、至尊组队、购买次数与每日重置，以及按 `elitepop.js` 协议重建的实时组队房间
- [x] 过关斩将活动（`pass_general`，按旧版移植）、主线任务动态里程碑 (`mainquest`)
- [x] 原版经典深度养成与宏观格局重现：玩家自建国家（军团）系统 (`country`)、将魂 (`soul`，按旧版移植)、武将三围永久洗髓培养 (`cultivate`)
- [x] 全面打通前端全量高频日常交互系统：每日签到福利 (`dailysign`)、VIP 特权俸禄与晋级礼包 (`vip`)、系统信箱与飞鸽传书 (`message`)、好友社交系统 (`friend`)、银矿占领挂机采矿 (`mine`)
- [x] 原版殿堂级中后期核心系统全线打通：铜雀台绝色美人 (`beauty`)、名驹坐骑系统 (`mount`)、神装宝石镶嵌与合成 (`gem`)、通天塔 (`heaven`)、世界 BOSS 刑天 (`worldboss`)
- [x] 国战都城争夺 (`siege`，按旧版移植)、跨服 GVG 巅峰争霸赛与押注分红 (`gvg`)
- [x] 聊天室 (`chat`) 按旧版移植：世界 / 国家 / 私聊频道、小喇叭、系统公告与战报分享，消息模板同旧版
- [x] 经典保值与开服生态全线贯通：武将经验属性传承 (`general_inherit`)、神秘黑市珍宝 (`blackmarket`)、首充返利与天命转盘 (`first_recharge`)、海上丝绸之路远洋贸易 (`overseastrade`)
- [x] 原版高阶衍生趣味玩法全线攻克：伯乐相马场 (`studfarm`)、原石翡翠赌石坊 (`jadegamble`)、古墓寻龙藏宝图 (`treasuremap`)、官府军械工坊 (`workshop`)
- [x] 战神殿与朝堂宏图系统打通：四象圣域 (`sanctum`)、群雄逐鹿排名活动 (`compete`，按旧版移植)、国家宗庙祭天 (`countrysacrifice`)、群臣治国诤言 (`expostulation`)
- [x] 休闲社交与交互狂欢系统全面贯通：群英夜宴大摆豪门宴席 (`banquet`)、青梅煮酒论英雄 (`drink`)、逍遥神骰行大运 (`dice`)、聚宝摇钱神树浇水摇金 (`sacredtree`)
- [x] 巅峰试炼与点将全线闭环：九宫金边翻牌夺宝 (`flop`)、玩家切磋 (`challenge`，按旧版移植)、点将台退役遣散 (`muster`)
- [x] 商业经贸与天赋神力体系贯通：四海通商行商经商 (`business`)、金银宝盆博彩 (`gambling`)、每日军务悬赏令 (`task`)、主公天赋 (`talent`，按旧版移植)
- [x] 终极养成进阶体系全线贯通：名将进阶 (`advanced`，按旧版移植)、装备精炼 (`refine`，按旧版移植)、主公星魄 / 命格 (`star`，按旧版移植)、国家战争学院科技 (`war_college`)
- [x] 英雄图谱与战役平乱全线就绪：幸运大转盘 (`lottery`)、武将远征试炼 (`generaltask`)、名将谱 (`general_book`，按旧版移植)
- [x] 跨服争霸与海外秘境全线贯通：金银岛 (`island`)、英雄王座 (`herothrone`)、真武战神金身试炼 (`beatengod`)、皇城跨服封神国战 (`war`)
- [x] 协议一致性与媒体高保真：标准 HTTP 206 Partial Content 字节切片音频流、Web Audio 捕获级全兼容唤醒、名将原型资产严格对齐 (`HERO_CONFIGS`)、主公经验条分母归一化与战败零收益铁律

- [x] 边境阵营战场与等级里程碑：国家矿脉 (`countrymine`，按旧版移植)、国家 BOSS 蚩尤 (`countryboss`)、金蛋纳福大暴击 (`hitegg`)、主公等级成长基金礼包 (`levelgift`)
- [x] 官营特产与神装天铸体系全线贯通：国家特产商城 (`countryshop`)、天工神装铸造熔炼 (`equip_forge`)、开服十四日累登狂欢 (`totallogin`)、天下史诗拼图 (`epic`，按旧版移植)
- [x] 运营激活与战力成就基石就绪：至尊CDKey激活码兑换 (`activationcode`)、整军经武演武礼包 (`exercise`)、主公全军总战力透视 (`information`)、结义邀请拉新分红 (`invitation`)
- [x] 旧版玩家物品存储模型（`src/Model/`）：玉石 `MemberJade`、酒馆宝石 `TraderLog`、精炼石 / 五彩石 / 上古血玉 `MemberStone`、宝石与宝石碎片 `OwnGem`、将魂与将魂包裹 `OwnSoul`、坐骑与坐骑碎片 `OwnMount`、神器碎片 `CountryDebris`，以及 `Equipment` 上的装备碎片 / 天赋卷轴 / 装备精华，均写旧版流水表；扣减为条件原子 UPDATE，多行存储以玩家行锁（`Member::locked`）串行化。已移植玩法的奖励（过关斩将、多人副本、国家矿脉、群雄逐鹿、国家 BOSS、俸禄、国战、将魂星路、平乱副本、通天塔、英雄王座、四象圣域、征战掉落、演武榜兑换）照旧版发进这些存储，战斗组队与将领界面计入宝石与坐骑属性
- [x] 核心业务轻量薄模型 (`src/Model/`) 落地：引入 `SoftDeletes` 自动软删除过滤、强类型属性转换与内聚领域方法（`Member`, `General`, `Equipment`）
- [x] 高并发防连击与防重入机制：基于 Redis 的核心资产操作原子防刷中间件 `ThrottleMiddleware`
- [x] PHPUnit Unit + Feature 测试套件（213 个测试，11289 个断言）

---

## 📄 开源许可证

本项目基于 [MIT License](LICENSE) 许可发布。
