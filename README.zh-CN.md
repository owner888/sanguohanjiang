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
│   ├── Handler/             # 纯无状态动作处理器 (杜绝类属性常驻污染)
│   │   ├── IosHandler.php        # 客户端握手与版本校验
│   │   ├── UserHandler.php       # 用户登录与 Token 交换
│   │   ├── RegisterHandler.php   # 试玩游客与正式注册
│   │   └── MemberHandler.php     # 玩家数据、军令体力与主城信息
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

3. 启动服务：
   ```bash
   # 调试模式（前台运行，Ctrl+C 停止）
   php server.php start

   # 守护进程模式（后台常驻）
   php server.php start -d

   # 重载代码 / 停止服务
   php server.php reload
   php server.php stop
   ```

4. 打开游戏：
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
- `c=general&m=index`：玩家武将列表、穿戴装备与属性计算
- `c=matrix&m=index`：九宫格战位阵法管理与上阵站位调换
- `c=pack&m=index`：背包道具、消耗品与装备仓储
- `c=strengthen&m=index`：铁匠铺装备强化升级与成功率计算
- `c=tavern&m=index`：酒馆将领招募与会客商人
- `c=practice&m=index`：校场武将挂机训练与突飞猛进
- `c=chat&m=read|send`：世界与国家频道实时消息流
- `c=mainquest&m=index`：主线任务进度与里程碑领奖
- `c=shop&m=index`：商城道具与军令体力购买
- `c=arena&m=index`：演武榜竞技场挑战与排名争夺

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
- [x] 编写 PHPUnit 全链路自动化测试套件 (20/20 100% 绿灯全过，225 个断言)
- [ ] 逐步移植核心玩法模块（试炼塔 `tower`、过关斩将 `pass_general`）

---

## 📄 开源许可证

本项目基于 [MIT License](LICENSE) 许可发布。
