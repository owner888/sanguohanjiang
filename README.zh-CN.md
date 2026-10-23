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
| **语言规范** | 无类型约束、数组 Notice 频繁的 PHP 5.3 | **严格类型 `declare(strict_types=1)`**、枚举、只读类、Match 表达式 |
| **部署维护** | 强依赖 Windows / WAMP / IIS 一键端 | 纯单命令 CLI / Docker 一键容器化部署 |

---

## 📂 项目结构

```
.
├── config/                  # 服务端配置目录
│   └── app.php              # HTTP 监听端口、Worker 进程数、静态资源路径
├── public/                  # 游戏前端完整静态资源
│   ├── index.html           # 客户端横屏动态适配与入口
│   ├── tmpl.html            # 全局 UI 弹窗与场景模板
│   └── static/              # CSS、JS、音效及核心数值表
│       ├── js/data/ini.js   # 核心战斗数值表与武将属性 (442KB)
│       └── js/view/         # 前端视图引擎 (battleworld, cityworld, mapworld)
├── src/
│   ├── Battle/              # 确定性九宫格战斗引擎
│   │   ├── BattleEngine.php # 核心战斗推演逻辑 (源自 battle_model.php 重构)
│   │   ├── Hero.php         # 战斗英雄实体模型
│   │   └── Position.php     # 九宫格战位 (1-9号位) 枚举
│   ├── Middleware/          # 中间件流水线 (完全对齐 smart-book 架构)
│   │   ├── MiddlewareInterface.php
│   │   ├── CorsMiddleware.php             # 跨域与 OPTIONS 预检处理
│   │   ├── GameProtocolMiddleware.php     # 将 c=xxx&m=yyy 智能分发至对应 Handler
│   │   └── ResponseEnvelopeMiddleware.php # 统一协议响应信封自动包装
│   ├── Handler/             # 领域驱动无状态动作处理器 (DDD 分层架构)
│   │   ├── Auth/            # 接入与账号认证 (Ios, User, Login, Register)
│   │   ├── City/            # 主城基础设施、征收与福利 (City, Member, DailySign, Vip)
│   │   ├── General/         # 武将招募、将魂突破与洗髓 (General, Star, Soul, Tavern)
│   │   ├── Equip/           # 铁匠铺、神装铸造与宝石 (Pack, Strengthen, Refine, Gem, Forge)
│   │   ├── Campaign/        # 九宫格战役关卡、战位与试炼 (Map, Matrix, Tower, Copies, Sanctum)
│   │   ├── Pvp/             # 演武竞技、天下名城与国战 (Arena, Compete, Country, Siege, War)
│   │   └── Activity/        # 四海通商、博彩、夜宴与任务 (Chat, Shop, Trade, Dice, Task)
│   └── Http/                # HTTP 协议层与网关
│       ├── Router.php       # 流水线驱动器及静态文件服务器
│       └── Response.php     # 统一协议响应助手
├── server.php               # Workerman 服务启动脚本
├── composer.json            # Composer 依赖与 PSR-4 自动加载
├── README.md                # 英文说明文档
└── README.zh-CN.md          # 中文说明文档
```

---

## 🚀 快速上手

### 环境要求

- **PHP >= 8.3**（推荐 PHP 8.4+）
- 扩展要求：`ext-pcntl`、`ext-posix`、`ext-json`、`ext-mbstring`
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

6. 打开游戏：
   在浏览器中访问：
   ```
   http://127.0.0.1:18100/
   ```

---

## 🎮 当前支持的协议与接口

网关无缝支持原版客户端通过 `?c={controller}&m={method}` 发起的各项业务请求：

- `c=ios&m=update_world_common`：客户端启动环境握手与模块清单验证
- `c=register&m=guest`：游客一键试玩账号创建与初始区服下发
- `c=register&m=reg`：正式账号注册
- `c=user&m=login`：账号验证与角色登录
- `c=login&m=guest`：单服试玩令牌签发
- `c=login&m=user`：单服正式账号验证与令牌签发
- `c=member&m=index`：玩家主数据、体力军令、背包银两及主城状态
- `c=member&m=select_role`：男女主公创角选角色与昵称绑定
- `c=member&m=get_guidestep`：新手剧情与引导步数同步
- `c=city&m=index`：主城建筑等级、征收次数与强征冷却
- `c=map&m=index`：世界地图大章节开启状态
- `c=map&m=get_scene_list`：战役势力关卡场景列表（如：大兴山）
- `c=map&m=get_mission_list`：据点敌人阵容与关卡敌将数据
- `c=map&m=mission`：战前敌将情报与布阵确认弹窗
- `c=map&m=action`：发起推图战斗与纯内存九宫格战报下发
- `c=map&m=get_newreward_index|get_newreward|get_newresult`：战役势力据点关卡宝箱、整势力通关大礼奖励领取与防重校验
- `c=general&m=index`：玩家武将列表、穿戴装备与属性计算
- `c=matrix&m=index`：九宫格战位阵法管理与上阵站位调换
- `c=pack&m=index`：背包道具、消耗品与装备仓储
- `c=strengthen&m=index`：铁匠铺装备强化升级与成功率计算
- `c=tavern&m=index`：酒馆将领招募与会客商人
- `c=practice&m=index|practice_start|practice_stop|go_leap|mop|get_levelup_price|levelup|get_unlock_price|unlock|turn`：校场武将挂机演武训练、突飞猛进与扫荡、8大训练位进阶解锁及武将转生
- `c=activity&m=index`：每日精彩活动列表总览、开放时段区间判定与签到/Boss红点气泡
- `c=chat&m=read|send`：世界与国家频道实时消息流
- `c=mainquest&m=index`：主线任务进度与里程碑领奖
- `c=shop&m=index`：商城道具与军令体力购买
- `c=arena&m=index`：演武榜竞技场挑战与排名争夺
- `c=tower&m=index|action|sweep`：将魂星路试炼塔爬塔闯关、守关神兽决战、一键扫荡与将魂结算
- `c=pass_general&m=index|action|reset`：经典过关斩将单挑六大名将（华雄/颜良/文丑/庞德/典韦/吕布）与通关宝箱开启
- `c=soul&m=index|upgrade`：将魂星盘八大命格孔位激活、试炼塔将魂消耗升级与攻防血量永久加成
- `c=country&m=index|select_country|donate|salary`：魏蜀吴三国势力格局、天命随机选国封赏、国家军资捐献与每日国家俸禄
- `c=cultivate&m=index|roll|save`：武将洗髓培养系统，武力/智力/体力永久洗练与属性注入强化
- `c=dailysign&m=index|sign`：30天连续月度签到、阶梯式海量银两/元宝/军令封赏与防重复签到校验
- `c=logined&m=index|get_reward`：1~15天连续登录阶梯大礼包、登录天数递增与断签自动重置
- `c=vip&m=index|get_vip_reward|get_gift`：VIP 尊享等级特权、每日 VIP 专属俸禄与段位晋升豪华大礼包
- `c=vipwage&m=index|get_vip_wage`：VIP 专属每日工资发放、等级加成银两/突飞卡/军令将魂领取与已领状态校验
- `c=message&m=index|get_message|send_message|del_message`：系统信箱与邮件流、飞鸽传书玩家私信交互与战报记录
- `c=friend&m=index|add_friend|del_friend`：高朋满座好友社交系统、结交盟友与防重好友管理
- `c=mine&m=index|caikuang|zhanling|get_silver|give_up`：富饶银矿与赤金矿脉占领、离线挂机采矿收益累积与定时收缴提取
- `c=beauty&m=index|give_flower|gift`：铜雀台绝色美人后宫宠幸（貂蝉/大乔/小乔/甄姬/孙尚香）、亲密度提升与全军属性光环
- `c=mount&m=index|fit_on|take_down|levelup`：三国神品名驹坐骑（赤兔/的卢/绝影/爪黄飞电）、战马乘骑与进阶驯化
- `c=gem&m=index|adron|pick_off|merge_gem`：神装宝石孔位镶嵌、宝石安全拆卸与4合1宝石熔炼进阶升级
- `c=heaven&m=index|pk|mop_up`：天命观星二十八星宿点亮、星盘守护神决战与一键观星扫荡
- `c=worldboss&m=index|powerup|battle|reward`：全服世界 BOSS 极·魔王董卓集结讨伐战、士气鼓舞伤害加成、全服伤害榜与功勋宝箱领奖
- `c=siege&m=index|battle_prepare|action|get_reward`：攻城掠地天下名城占领（洛阳/长安/建业/成都/襄阳/濮阳）、城池决战与每日封邑岁贡分红
- `c=gvg&m=index|betting|sync_info`：军团跨服 GVG 巅峰争霸赛对阵树、冠军押注竞猜与胜利分红结算
- `c=general_inherit&m=index|preview|go_inherit`：武将功法传承、经验与洗髓三围属性无损转移（普通传承 80% 与至尊完璧 100% 传承）
- `c=blackmarket&m=index|buy|lock|refresh`：西域神秘黑市商人、稀世奇珍锁定保底与货架刷新
- `c=first_recharge&m=index|get_reward`：开服首充狂欢至尊大礼包、神将赵云、龙胆亮银枪与 VIP 直升
- `c=overseastrade&m=index|goout|trade`：海上丝绸之路远洋商贸、商队启航与满载珍宝凯旋利润结算
- `c=studfarm&m=index|action`：伯乐相马场、纯血神驹鉴赏寻觅、战马血统注入与战力跃升
- `c=jadegamble&m=index|mining|gambling|harvest`：翡翠原石赌石坊、选石开采、一刀切石与满色帝王绿玉石折现
- `c=treasuremap&m=index|refresh|sale`：摸金校尉古墓寻宝、罗盘寻龙分金升星与皇室地宫秘宝发掘
- `c=workshop&m=index|get_reward|levelup`：官府军械工坊流水线、精铁破甲箭生产、流水线扩建与军工收益折现银两
- `c=sanctum&m=index|action|get_reward`：四象圣域神尊决战（青龙/白虎/朱雀/玄武）、九宫格推演与终极大成神礼
- `c=compete&m=index|action|get_reward`：跨服群雄逐鹿比武大会、排位段位积分与每日论剑俸禄
- `c=countrysacrifice&m=index|action`：国家宗庙九鼎太牢祭天大典、全国内阁攻击防御光环与声望庇护
- `c=expostulation&m=index|adopt`：群臣治国诤言进谏（富国策/精兵策/安民策）、御笔朱批与治国功绩政绩折现银两
- `c=banquet&m=index|create_team|action`：群英夜宴大摆筵席（主公家宴/诸侯豪宴/万寿盛宴）、宾主尽欢与巨额军令体力赠予
- `c=drink&m=index|go_drink`：青梅煮酒论英雄、杜康千年佳酿畅饮对饮与将魂银两祥瑞
- `c=dice&m=index|shake_dice`：逍遥神骰行大运、至尊豹子大顺子摇骰与金银暴击瓜分
- `c=sacredtree&m=index|watering`：聚宝通天摇钱神树、灵泉灌溉浇水成长与金叶玉果元宝银饷掉落收割
- `c=flop&m=index|turnover`：九宫翻牌夺宝、金边卡牌翻转揭秘与至尊元宝大奖
- `c=challenge&m=index|action`：英雄试炼擂台巅峰单挑、挑战守擂名将（马超/赵云/关羽）夺取擂主荣耀
- `c=elite&m=index|action`：精英关卡战役（精英大兴山/宛城/虎牢关）、九宫格推演与必掉珍稀装备图纸
- `c=muster&m=index|get_general_info|go_battle|go_rest|sale_general`：点将台大营名册、出征派遣、下阵休息、将领详情与闲置武将退役遣散（软删除管理）
- `c=business&m=index|go_business|buy|refresh`：四海通商行商经商（洛阳/成都/建业/襄阳）、商市行情采购与暴利返航折现
- `c=gambling&m=index|buy|cut|sell`：金银宝盆博彩、原石抛光雕琢与聚宝盆估价翻倍典当
- `c=task&m=index|accept_task|get_reward|refresh`：每日军务悬赏令看板、接取平定剿匪、交付领赏与揭榜刷新
- `c=talent&m=index|levelup`：武将天赋神力树觉醒（破军猛攻/金刚铁壁/百战嗜血/无双会心）与战力暴涨翻倍
- `c=advanced&m=index|start_advanced`：武将品质品阶飞升（绿将/蓝将/紫将/橙将/暗金天命神将突破）
- `c=refine&m=index|refinning`：铁匠铺神装精炼淬火、附加破甲抗暴闪避卓越词条属性
- `c=star&m=index|level_up`：武将七星连珠星级突破、星宿命格觉醒与战力倍增
- `c=war_college&m=index|level_up`：三国内阁国家战争学院兵种科技研发（步兵/骑兵/弓手/策士科技全军被动光环）
- `c=lottery&m=index|action`：天赐幸运大转盘、五行扇区指针抽奖与至尊百两元宝大奖
- `c=generaltask&m=index|action|get_report`：武将派遣远征试炼、古栈道南蛮巡狩与凯旋战利品将魂上缴
- `c=general_book&m=index|levelup`：三国英雄名将谱图鉴、点亮桃园结义/五虎上将/五子良将/东吴都督羁绊
- `c=copies&m=index|pk|mop_up`：平乱战役副本（十常侍之乱/董卓乱政/张闿叛军据点）、九宫格推演与一键平乱扫荡
- `c=island&m=index|pk`：东海金银岛秘境探险（蓬莱仙境/瀛洲金岛/方丈古境）、护岛神兽对决与海外龙脉珍宝
- `c=herothrone&m=index|action`：英雄王座巅峰争霸（青铜铁血/白银霸烈/至尊第一王座）、九宫格攻擂推演与霸主加冕
- `c=beatengod&m=index|beaten|get_box_reward`：真武战神金光试炼、敲击金身赐福掉落与战神宝箱领奖
- `c=war&m=index|entry|point_shop`：魏蜀吴皇城跨服封神国战大厅、出征报名参战与军功功勋商城兑换
- `c=countrymine&m=index|caikuang|rob|get_reward|give_up`：三国国家边境矿山开采、敌国矿车掠夺对决与国库收益收缴
- `c=countryboss&m=index|powerup|battle|reward`：魏蜀吴国家守护神兽祭坛（真金神龙/踏云麒麟/九羽火凤）、全军鼓舞与神兽试炼宝箱
- `c=hitegg&m=index|hit_egg`：金蛋纳福大暴击（吉祥金蛋/富贵银蛋/至尊彩蛋）、挥锤破蛋与元宝爆出
- `c=levelgift&m=index|get_reward`：主公等级成长里程碑礼包（5/10/20/30/40/50级）与成长基金一键领奖
- `c=countryshop&m=index|buy`：魏蜀吴国家特产商城、官营国税军资与红色绝品神装图纸兑换
- `c=equip_forge&m=index|fabrication|resolve`：天工神兵铸造工坊、神装开炉淬火打造与闲置装备熔炼提取精铁
- `c=totallogin&m=index|action`：全服开服累登狂欢大礼（1~14天全梯度封赏）、豪华银票元宝礼包一键申领
- `c=epic&m=index|force`：三国历史天命战纪史诗战役（白马之战/火烧乌巢/华容道劫曹）、九宫格推演决战领赏
- `c=activationcode&m=index|get_reward`：全服至尊特权激活码/CDKey兑换系统（VIP666/VIP888/SG888）、海量元宝银两秒级兑付与防重领取
- `c=exercise&m=index|open_gift`：全军整军经武大演习、整军礼包拆开与行军令军饷犒赏
- `c=information&m=index|get_info|get_own_achievement`：主公全军总战力透视总览、名将总汇统计与个人成就勋章荣耀
- `c=invitation&m=index|change|get_reward`：结义金兰邀请招募、好友绑定双向获赠元宝与邀请人头里程碑达标大礼

---

## 🧪 自动化测试套件

所有 16 个核心业务接口与战斗推演闭环均由 PHPUnit 自动化集成测试全量覆盖：

```bash
./vendor/bin/phpunit
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
- [x] 铁匠铺装备强化系统 (`strengthen`)，支持梯度银两消耗与成功率机制
- [x] 酒馆真实银两招募武将系统 (`tavern`)，名将池持久化入库与防重招募校验
- [x] 校场武将挂机训练系统 (`practice`)，挂机时长经验自动结算与突飞猛进即时升级
- [x] 深度联动纯内存九宫格战斗推演，上阵武将与装备强化数值无缝作用于战场战力
- [x] 九宫格战位阵法布阵系统 (`matrix`)，支持换位、防重上阵拦截、八大阵法升级与切换
- [x] 多武将同屏协同出征作战，真实九宫格站位多目标推演与战报回放
- [x] 演武榜竞技场真实 PVP 系统 (`arena`)，支持天梯名次互换、每日俸禄领取与纯内存镜像对决推演
- [x] 主城征收与强征系统 (`city`)，支持丰收暴击银两倍数与元宝强征加倍收益
- [x] 原生 WebSocket 全双工长连接网关，支持鉴权、实时世界聊天与全服跑马灯大喇叭广播
- [x] 完全对齐 `smart-book` 标准的纯粹 RFC 6455 协议级心跳机制（0x9/0xA 控制帧 ping/pong，杜绝应用层冗余载荷开销）
- [x] 全面废除冗余的业务中间继承层，全站业务 Handler 与中间件直接原生消费 `OmniPHP\Http\Context`（完全对齐 Go Gin 标准）
- [x] 直接使用框架原生 `OmniPHP\WebSocket\Context` 与 `Broadcaster` 接管全双工事件载荷、用户会话与纯内存极速测试
- [x] 彻底删除业务端自建的 `src/Database/`，全站直连框架官方高可靠 `OmniPHP\Database\DB` 与 `OmniPHP\Cache\Redis`
- [x] 框架级引入 `OmniPHP\ArchitectureGuard` 架构铁律守卫，杜绝新老项目重复造轮子与私造基础设施
- [x] 前端网络层指数退避断线重连（Exponential Backoff with Jitter）与服务端超时僵尸连接自动回收扫描（`sweep`）
- [x] 全面移植并贯通原版核心进阶玩法：试炼塔爬塔闯关与一键扫荡 (`tower`)、过关斩将斩杀六大名将 (`pass_general`)、主线任务动态里程碑 (`mainquest`)
- [x] 原版经典深度养成与宏观格局重现：三国国家阵营格局 (`country`)、将魂星盘命格突破 (`soul`)、武将三围永久洗髓培养 (`cultivate`)
- [x] 全面打通前端全量高频日常交互系统：每日签到福利 (`dailysign`)、VIP 特权俸禄与晋级礼包 (`vip`)、系统信箱与飞鸽传书 (`message`)、好友社交系统 (`friend`)、银矿占领挂机采矿 (`mine`)
- [x] 原版殿堂级中后期核心系统全线打通：铜雀台绝色美人 (`beauty`)、名驹坐骑系统 (`mount`)、神装宝石镶嵌与合成 (`gem`)、天命观星突破 (`heaven`)、全服世界 BOSS 董卓战 (`worldboss`)
- [x] 军团攻城掠地城池战 (`siege`)、跨服 GVG 巅峰争霸赛与押注分红 (`gvg`)、十六历史战役全景数值大字典 (`GameMasterData`)
- [x] 前端网络层内置全服金色流光跑马灯大喇叭横幅渲染引擎（硬件加速流动字幕动效）
- [x] 经典保值与开服生态全线贯通：武将经验属性传承 (`general_inherit`)、神秘黑市珍宝 (`blackmarket`)、首充至尊神礼 (`first_recharge`)、海上丝绸之路远洋贸易 (`overseastrade`)
- [x] 原版高阶衍生趣味玩法全线攻克：伯乐相马场 (`studfarm`)、原石翡翠赌石坊 (`jadegamble`)、古墓寻龙藏宝图 (`treasuremap`)、官府军械工坊 (`workshop`)
- [x] 战神殿与朝堂宏图系统打通：四象圣域神尊决战 (`sanctum`)、跨服群雄论剑 (`compete`)、国家宗庙祭天 (`countrysacrifice`)、群臣治国诤言 (`expostulation`)
- [x] 休闲社交与交互狂欢系统全面贯通：群英夜宴大摆豪门宴席 (`banquet`)、青梅煮酒论英雄 (`drink`)、逍遥神骰行大运 (`dice`)、聚宝摇钱神树浇水摇金 (`sacredtree`)
- [x] 巅峰试炼与点将全线闭环：九宫金边翻牌夺宝 (`flop`)、英雄试炼擂台决战 (`challenge`)、精英高难副本战役 (`elite`)、点将台退役遣散 (`muster`)
- [x] 商业经贸与天赋神力体系贯通：四海通商行商经商 (`business`)、金银宝盆博彩 (`gambling`)、每日军务悬赏令 (`task`)、武将天赋神力树 (`talent`)
- [x] 终极养成进阶体系全线贯通：神将品质飞升 (`advanced`)、装备精炼词条 (`refine`)、武将七星突破 (`star`)、国家战争学院科技 (`war_college`)
- [x] 英雄图谱与战役平乱全线就绪：幸运大转盘 (`lottery`)、武将远征试炼 (`generaltask`)、名将谱羁绊点亮 (`general_book`)、平乱战役副本 (`copies`)
- [x] 跨服争霸与海外秘境全线贯通：东海金银岛秘境 (`island`)、英雄王座巅峰争霸 (`herothrone`)、真武战神金身试炼 (`beatengod`)、皇城跨服封神国战 (`war`)
- [x] 边境阵营战场与等级里程碑：国家边境矿山争夺 (`countrymine`)、国家神兽守护神祭坛 (`countryboss`)、金蛋纳福大暴击 (`hitegg`)、主公等级成长基金礼包 (`levelgift`)
- [x] 官营特产与神装天铸体系全线贯通：国家特产商城 (`countryshop`)、天工神装铸造熔炼 (`equip_forge`)、开服十四日累登狂欢 (`totallogin`)、天命战纪史诗副本 (`epic`)
- [x] 运营激活与战力成就基石就绪：至尊CDKey激活码兑换 (`activationcode`)、整军经武演武礼包 (`exercise`)、主公全军总战力透视 (`information`)、结义邀请拉新分红 (`invitation`)
- [x] 核心业务轻量薄模型 (`src/Model/`) 落地：引入 `SoftDeletes` 自动软删除过滤、强类型属性转换与内聚领域方法（`Member`, `General`, `Equipment`, `GeneralSoul`, `SiegeCity`）
- [x] 高并发防连击与防重入机制：基于 Redis 的核心资产操作原子防刷中间件 `ThrottleMiddleware`
- [x] 编写 PHPUnit 全链路自动化测试套件 (91/91 100% 绿灯全过，1202 个断言)

---

## 📄 开源许可证

本项目基于 [MIT License](LICENSE) 许可发布。
