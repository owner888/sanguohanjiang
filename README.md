# Hanjiang Sanguo (悍将三国) - Modern Edition

> A high-performance Three Kingdoms tactical card RPG game server reconstructed with modern **PHP 8.4+** and **Workerman 5.x**.

[English](README.md) | [简体中文](README.zh-CN.md)

---

## 📖 Overview

This project is a modern, high-concurrency re-architecture of the classic strategy RPG web/mobile game *Hanjiang Sanguo* (悍将三国). 

The original 2012~2015 server was built on legacy PHP 5.x + Apache + CodeIgniter with synchronous blocking MySQL queries and HTTP short-polling. While the game logic and tactical nine-grid combat mechanics were solid, its runtime architecture suffered from severe performance bottlenecks and required complex multi-instance partitioning (`s1`, `war`, `battle`, `report`, `uc`).

This modern edition rebuilds the backend into a resident-memory, event-driven microservice system that dramatically outperforms the original legacy stack while maintaining complete backwards compatibility with original client protocols.

---

## ⚡ Key Architectural Upgrades

| Feature | Legacy 2013 Edition (PHP 5.x + FPM) | Modern Edition (PHP 8.4+ & Workerman 5) |
| :--- | :--- | :--- |
| **Execution Model** | Stateless short process, reloads code on every request | **Resident in-memory processes**, zero bootstrap overhead |
| **Concurrency / IO** | Synchronous blocking MySQL and file IO | **Event-driven non-blocking IO** (Revolt / EventLoop) |
| **Combat Simulation** | Database-dependent sequential execution (~10-50ms) | **Pure in-memory deterministic simulation** (< 0.1ms) |
| **State Sync** | Client HTTP short-polling every second | Native **WebSocket duplex real-time broadcasting** |
| **Media Streaming** | Full-payload response (fails on Safari / iOS WebKit) | Standard **HTTP 206 Partial Content** Byte-Range slices & Capture-phase unlock |
| **Language Standards** | Dynamic typeless PHP 5.3, procedural helpers | **`declare(strict_types=1)`**, Enums, Readonly classes, Match |
| **Deployment** | Heavy Windows / WAMP / IIS dependencies | Single-command CLI / Docker containerization |

---

## 📂 Project Structure

```
.
├── config/                  # Server configuration (read through OmniPHP\Config; env vars / .env override defaults)
│   ├── app.php              # HTTP / WebSocket listen addresses, worker count, client asset settings
│   ├── database.php         # MySQL connection (`default` => mysql)
│   └── cache.php            # Redis connection (key prefix `hj:`)
├── public/                  # Static game client resources
│   ├── index.html           # Viewport dynamic adaptation & bootstrap
│   ├── tmpl.html            # Global UI component templates
│   └── static/              # CSS, JS, audio, and game configurations
│       ├── js/data/ini.js   # Master combat formulas & hero attributes (442KB)
│       └── js/view/         # Client views (battleworld, cityworld, mapworld)
├── src/
│   ├── Battle/              # 9-grid combat engine (legacy battle_model port, injectable RNG)
│   │   ├── BattleEngine.php # Legacy battle() / process / process_effect / process_hurt port; outputs the legacy battle report
│   │   ├── BattleRush.php   # Legacy 3-lane team battle battle_rush (PvP country war, country war / elite campaign monsters, monster sieges)
│   │   ├── Position.php     # 9-grid tactical positions (1-9) enum
│   │   ├── LegacyStats.php  # Legacy general/equipment formulas (pure functions, each cites its legacy source)
│   │   └── TeamBuilder.php  # Legacy team building (player / monster teams, team_return_arr)
│   ├── MasterData/          # Legacy config tables as const arrays (generated, do not edit)
│   ├── Middleware/          # Middleware pipeline (aligned with smart-book)
│   │   ├── PublicAssetsMiddleware.php     # public/ static files, dynamic config.js, autoload.js no-cache, audio Range
│   │   ├── CorsMiddleware.php             # Cross-origin and OPTIONS preflight
│   │   ├── GameProtocolMiddleware.php     # Dispatches c=xxx&m=yyy to Handler actions
│   │   └── ResponseEnvelopeMiddleware.php # Standardizes JSON response envelopes
│   ├── Handler/             # Stateless domain action handlers (DDD Layout)
│   │   ├── Auth/            # Authentication & onboarding (Ios, User, Login, Register)
│   │   ├── City/            # City infrastructure, levy, VIP & gifts (City, Member, DailySign, Vip)
│   │   ├── General/         # Heroes, souls, lord star fate & talents, training & tavern (General, Soul, Star, Talent, Tavern)
│   │   ├── Equip/           # Blacksmith, refinement, gems & forge (Pack, Strengthen, Refine, Gem, Forge)
│   │   ├── Campaign/        # 9-grid campaign maps, tactics & towers (Map, Matrix, Tower, Copies, Sanctum)
│   │   ├── Pvp/             # Military rankings, nations & siege wars (Arena, Compete, Country, Siege, War)
│   │   └── Activity/        # Commerce, gambling, banquets & quests (Chat, Shop, Trade, Dice, Task)
│   ├── Http/
│   │   └── ExceptionHandler.php # Uncaught exception → 500 with the legacy envelope {"status":-500,...}
│   ├── Support/
│   │   └── Connections.php  # Per-process MySQL / Redis init (onWorkerStart, CLI tools, PHPUnit)
│   └── WebSocket/           # WebSocket gateway (port 18101)
│       ├── WebSocketHandler.php # Connection lifecycle, heartbeat, auth
│       ├── ChatPush.php     # Chat room real-time push (pops each player's legacy chat queue)
│       └── EliteRoom.php    # Elite campaign real-time team rooms (legacy elitepop.js protocol)
├── tools/
│   ├── gen_master_data.php  # Regenerates src/MasterData from the legacy mysqldump
│   ├── pay_grant.php        # Developer-only CLI: credits a recharge through the payment core (testing VIP)
│   └── scan_legacy_gap.php  # Refreshes the generated part of docs/LEGACY_GAP.md
├── bootstrap.php            # Required first by every entry point: OmniPHP Application::boot (paths, .env, timezone, log level, CrashHandler)
├── routes.php               # OmniPHP Router: global middleware pipeline + catch-all routes
├── server.php               # Workerman service entrypoint (Fiber event loop, Kernel::handle)
├── runtime/                 # pid + logs (git-ignored, created on boot)
├── composer.json            # Dependencies & PSR-4 autoloading
├── README.md                # English documentation
└── README.zh-CN.md          # Chinese documentation
```

---

## 🚀 Quick Start

### Prerequisites

- **PHP >= 8.3** (PHP 8.4+ recommended)
- PHP extensions: `ext-pcntl`, `ext-posix`, `ext-json`, `ext-mbstring`, `ext-pdo_mysql`, `ext-redis`
- Composer

### Installation

1. Clone repository:
   ```bash
   git clone https://github.com/owner888/sanguohanjiang.git
   cd sanguohanjiang
   ```

2. Install dependencies:
   ```bash
   composer install
   ```

3. Import database schema:
   ```bash
   mysql -uroot -proot -e "CREATE DATABASE IF NOT EXISTS zc_sanguo;"
   mysql -uroot -proot zc_sanguo < database/schema.sql
   ```
   Upgrading an existing database instead: apply the files in `database/migrations/` that are newer than your schema, in filename order (each file header says what it does). `20261031_01_general_equip_legacy_cols.sql` assumes general/equipment rows are test data: it truncates both tables and re-issues every player the legacy starting general. `20261031_03_matrix_legacy.sql` converts the one-row-per-player `matrix` table to legacy per-formation rows, fills `member_field.matrixid` from the old row, rebuilds `general.goout` from the old `pos` / formation slots, then drops `general.pos` / `general.matrix_id`. `20261031_04_arena_legacy.sql` assumes arena data is test-only: it rebuilds the arena tables (old ranks are not kept), adds the points table, and resets every player's `member_field.rank` to 0; players re-enter the ladder the legacy way the next time they open the arena. `20261031_05_tower_legacy.sql` assumes Soul Star Road (tower) progress is test-only (it lived only in Redis `tower_progress:{uid}`): it creates the legacy tower tables (`member_activity`, `soul_open_map`, `soul_log`, `soul_report`) and adds `member_field.soultimes`; old progress is not converted and every player starts from mission 1. `20261031_06_copies_legacy.sql` assumes pacification copies (copies) state is test-only (only a daily attempt counter in Redis `copies_times:{uid}`): it (re)creates the legacy copies tables (`copies_beat`, `copies_beat_log`, `copies_report`) and the forge material tables (`own_materials`, `own_materials_log`); nothing is converted and each player gets fresh legacy rows the first time they open a copy. `20261031_07_elite_legacy.sql` assumes elite campaign state is test-only (only a daily attempt counter in Redis `elite_times:{uid}`): it (re)creates `elite_log` / `elite_mission_log` and adds the `elite*` columns to `member_activity`; nothing is converted, existing activity rows start with 0 elite attempts until the next daily reset (an optional `UPDATE` at the end of the file grants 10 right away). `20261101_batch1.sql` assumes sparring (challenge), World Epics (epic) and Pass General (pass_general) state is test-only (it lived only in Redis `challenge_master:{uid}`, `epic_cleared:{uid}`, `pass_general_stage:{uid}`): it (re)creates `epic_log` and the `passgeneral*` tables and updates the `member_integral` table comment; nothing is converted, and those Redis keys are no longer read. `20261101_batch3.sql` assumes Contest of Heroes (compete), country mines (countrymine) and siege state is test-only (Redis `compete_score:{uid}`, `compete_times:{uid}`, `user_country_mine:{uid}`, `siege_reward:{uid}` and the invented `zc_sanguo_siege` table): it drops `zc_sanguo_siege`, creates the legacy `compete`, `compete_action_log`, `country_mine`, `country_fight_notice`, `country_city`, `country_city_report` and `country_siege_log` tables and adds `member_field.oncountrymine`; nothing is converted. `20261101_items_storage.sql` adds the legacy item storages: `member.souluse` / `soulpack` / `jade` / `gem` / `gemuse` / `refinestone` / `colorstone` / `bloodstone` (placed right after `soul`; existing players get the legacy starting `soulpack` of 4) and the `trader_log`, `own_gem`, `own_mount`, `own_soul` tables plus their log tables (`tavernjade_log`, `jade_log`, `stone_log`, `gem_log`, `itemchip_log`, `talent_scroll_log`, `mountchip_log`, `mount_info_log`, `debris_log`); equipment chips, talent scrolls and equipment essence stay in `equipment` as in legacy `own_equip`. It assumes the Redis item keys are test-only and cannot be read from SQL, so nothing is converted; `user_trader:{uid}`, `user_gems:{uid}`, `equip_gems:{uid}:{eid}` and `user_mounts:{uid}` are no longer read or written and can be deleted. `20261101_growth1.sql` ports souls (soul), lord star fate (star) and lord talents (talent) from legacy: it drops the invented `zc_sanguo_general_soul` table and creates the legacy `own_general_soul` (per-general soul slots), `own_soul_mergelog`, `usesoul_log` (soul essence log), `own_star`, `star_tree`, `star_log`, `own_star_addition` and `own_hero_talent` tables. It assumes the old slot levels and the Redis keys `general_star:{uid}:{gid}` / `general_talents:{uid}:{gid}` (invented per-general star / talent features) are test-only: nothing is converted, those keys are no longer read and can be deleted. `20261101_growth2.sql` ports the Bronze Sparrow Terrace beauties (beauty) from legacy: it creates the legacy `beauty_own_log`, `beauty_own_chip`, `beauty_info`, `beauty_loot_list`, `beauty_loot_log`, `beauty_active_log`, `beauty_buyloot_log`, `beauty_buyrose_log` and `beauty_speical_reward_log` tables (table names spelled as in legacy); roses / diamonds stay in `member_integral`. It assumes the Redis key `user_beauties:{uid}` (invented beauty data) is test-only: nothing is converted, the key is no longer read or written and can be deleted. `20261101_growth3.sql` ports general advancement (advanced), equipment refinement (refine) and the heroes chronicle / chronicle achievements (general_book) from legacy: it creates the legacy `advanced_log`, `advanced_inherit_log`, `refine_master_log`, `refine_log`, `refine_inherit_log`, `own_achievement`, `own_addition` and `own_achievement_log` tables and adds `member_field.advanced_time` (that `ALTER TABLE` runs once). It assumes the invented quality ascension, the Redis refine affixes `equip_refine:{uid}:{eid}` and the invented chronicle bonds `general_book:{uid}` are test-only: nothing is converted, those keys are no longer read and can be deleted; refine levels are the existing `equipment.hammer`. `20261102_pay.sql` adds the legacy recharge / VIP storage: `pay_order` (the legacy per-channel success-order tables such as `alipay_order` / `91pay_order` / `pppay_order` share one structure and are merged, with a unique order id), `pay_log`, `vip_log`, `recharge_list_log`, `newyear_vipreward_log`, `jump_rebate` / `jump_rebate_order`, plus `member.rebate_firstpay`, `member_field.rebate_dateline` / `rebate_version` and `member_activity.vipwage_dateline` (those `ALTER TABLE`s run once); the `member.vip` default becomes the legacy 0. It assumes the invented VIP state is test-only: nothing is converted, existing `member.vip` values are kept, and the Redis keys `vip_salary:{uid}`, `vip_gifts:{uid}`, `vipreward_claimed:{uid}` and `vipwage_date:{uid}` are no longer read or written and can be deleted. `20261102_q_daily1.sql` (named to sort after `20261102_pay.sql`, whose columns it builds on) adds the legacy letter, task and log storage: `pm`, `pm_report`, `gm`, `task_log`, `main_quest_log`, `main_quest_reward`, `gold_log`, `silver_log`, `act_log`, `reputation_log`, `stat_pay`, plus `member.newpm` / `newmail` and `member_activity.task_number` (those `ALTER TABLE`s run once). It assumes the invented letters / daily tasks / main quests are test-only: nothing is converted, and the Redis keys `user_messages:{uid}`, `user_daily_tasks:{uid}:{Ymd}` and `mainquest:task_id:{uid}` are no longer read or written and can be deleted. `20261103_map.sql` ports the legacy campaign map storage: creates `open_map`, `mission_log`, `mission_report`, `mission_rewardlog`, `map_action_log` and `pub_log`, adds `member.missionworld` / `firstworld` and `member_field.start_time` (these `ALTER TABLE`s run once), and drops the custom `zc_sanguo_map` progress table and the fake `zc_sanguo_mission_newreward` chest table. Existing progress is converted from what was actually won: one `open_map` row per faction with `zc_sanguo_map` rows (highest scene, its highest won mission), first-kill `mission_log` rows for missions 1..N of every recorded scene; scenes / factions the custom flow only pushed the player into are not kept (e.g. after winning 1-1-5 the player continues at 1-1-6); every player gets at least the faction 1 row; the stored map position is pulled back when it is past the converted progress; old chest claims of the fake chests are soft-deleted; Redis `user_shili_claimed:{uid}:{id}` is no longer read. `20261103_q_daily2.sql` (sorted after `20261103_map.sql`) adds the legacy tables for drink / banquet / expostulation / generaltask / war college / cultivate / inherit / leap logs, adds `member_field.baijintimes` / `zhizuntimes` / `ongeneral` / `free_inherit` / `general_sale` / `inherit_dateline` and `member_activity.drinktime` / `generaltasktimes`, changes the `member_field.freeleap` default to 0, and backfills missing `practice` slots 1..`practiceall`.

4. Start server:
   ```bash
   # Debug mode (foreground)
   composer server:start

   # Daemon mode (background)
   composer server:daemon

   # Hot reload / status / stop
   composer server:reload
   composer server:status
   composer server:stop
   ```

5. Run test suite:
   ```bash
   composer test
   ```

   Developer recharge (no payment channel is wired yet): `php tools/pay_grant.php <uid> <productId>` credits product `productId` (`AlipayProduct`, run without arguments to list them) to player `uid` through the same payment core a channel callback will use (`Hanjiang\Model\Pay::complete`: gold, VIP progress, first-recharge state, mail, `pay_order` / `pay_log`) with an order id prefixed `manual_`. It is a CLI script only; no HTTP route reaches it.

   In-game test recharge: start the server with `PAY_TEST=1` (config `pay_test` in `config/app.php`) and the settings popup shows a "测试充值" button listing the products; each one calls `c=pay&m=test_grant&id=<productId>`, which credits the logged-in player through the same core as `pay_grant.php` (`Pay::grantManual`, channel `manual`). `c=pay&m=test_info` returns `{enabled, list}` so the client only shows the button when enabled. When `PAY_TEST` is not `1` (the default), `test_grant` returns status `0` and does nothing. **Never enable `PAY_TEST` in production** — it lets any player credit gold for free.

   **Configuration**: every setting in `config/*.php` reads an environment variable first; put local overrides in a `.env` file in the project root (`KEY=VALUE` per line, git-ignored, loaded by `bootstrap.php`; real environment variables win over `.env`). Common keys: `HTTP_LISTEN` (default `http://0.0.0.0:18100`), `HTTP_WORKERS` (4), `WS_LISTEN` (`websocket://0.0.0.0:18101`), `WS_PUBLIC_PORT`, `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD`, `REDIS_HOST` / `REDIS_PORT` / `REDIS_PASSWORD` / `REDIS_DB`, `APP_TIMEZONE` (default `Asia/Shanghai`), `LOG_LEVEL` (`info`), `PAY_TEST`. Changing `.env` or `routes.php` needs `restart`; `reload` does not re-read them.

   **Runtime files and logs** (all under `runtime/`): `hanjiang.pid`; `logs/hanjiang.log` (Workerman master: start / stop / worker exits); `logs/hanjiang_stdout.log` (daemon mode stdout: per-request access log lines, PHP warnings / fatals, uncaught exceptions); `logs/{level}_YYYYMMDD.log` (OmniPHP Logger, e.g. `error_*.log` for unhandled HTTP exceptions, `warning_*.log` for protocol fallbacks; kept `LOG_KEEP_DAYS`, default 5).

   **Event loop**: workers run on Workerman's Fiber driver (Revolt, `revolt/event-loop`); every connection event and timer callback runs in its own Fiber. MySQL (PDO) and Redis (phpredis) calls are synchronous, so a request never suspends half-way and one process still serves one request at a time; request-scoped state lives in `Context` or in Fiber-local storage (`Workerman\Coroutine\Context`, used by `FlowLog`), never in static properties. Database migrations are not run automatically: OmniPHP's MigrationRunner only handles its own PHP migration classes, the `.sql` files in `database/migrations/` are applied by hand as described above.

6. Play the game:
   Open your browser and navigate to:
   ```
   http://127.0.0.1:18100/
   ```

---

## 🎮 Supported Protocol & APIs

The HTTP gateway seamlessly bridges original client requests mapped through `?c={controller}&m={method}`:

- `c=ios&m=update_world_common`: Client environment handshake & resource validation
- `c=register&m=guest`: Instant trial / guest account initialization (legacy start: general template 1 廖化 in formation 1 slot 2, no starter equipment)
- `c=register&m=reg`: User registration
- `c=user&m=login`: Account authentication & server selection
- `c=login&m=guest`: Server-side trial session token issuance
- `c=login&m=user`: Server-side member authentication & token issuance
- `c=member&m=index`: Player profile, attributes, stamina, and city states
- `c=member&m=select_role`: Character creation and custom nickname binding
- `c=member&m=get_guidestep`: Tutorial step synchronization
- `c=member&m=updatemissionsite`: Store the map position used by the tutorial (`missionsite`: 1 world, 2 faction, 3 scene; legacy port)
- `c=city&m=index`: Main city building levels, levy limits, and cooling states
- `c=map&m=index|get_scene_list|get_mission_list|mission|action|clear_cd|report|report_detail|get_reward_list|get_mission_reward|send_to_chat|get_newreward_index|get_newreward|get_newresult`: Main campaign (legacy port of `controllers/map.php` + `models/map_model.php`, `Hanjiang\Model\OpenMap`): factions / scenes / missions from `Map` / `Scene` / `Mission`; progress in `open_map` (one row per faction: highest scene, highest cleared mission, clear-reward flag), first kills and daily attack counts in `mission_log`, first-kill replays in `mission_report`. The popup (`mission`, `get_mission_list`) and the battle (`action`) both read the same `Mission` row, so the defender name / level / first-kill exp always match; defenders are `Mission` positions → `Monster`. `action` costs `times` command tokens (1, or 5 / 10 / 50 / 100 sweeps for VIP 3+, not on first kills), charges `Price.storm` gold once the daily free attacks (`maxtimes`) are used up, grants first-kill or normal exp (lord exp via `Member::addExp`, legacy `80 × level²` level-up, +2 tokens per level), drops by `getrate`, recruits `generalid` by `recruitrate`‰, opens the next scene / faction and records `member_field.newresult` for the faction clear reward (`get_newresult`). First kills of faction 1 scene 1 return the legacy scripted tutorial reports (`Battle\NewbieBattleReport`). Mission chests (`MissionNewreward`, `mission_newreward_log`), 3-day race rewards (`MissionReward`, `mission_rewardlog`), first-kill strategy list / replay and the 2-gold chat share follow the legacy code
- `c=general&m=index|get_info|equip|unequip`: Hero panel ported from the legacy `general.php` (deployed heroes only, legacy `info_field`, equipment display power with refine / enhancement-achievement bonuses, legacy HP formula, legacy equip validations and status codes)
- `c=matrix&m=index|get_info|update_matrix|use_matrix|levelup`: Formations ported from the legacy `matrix.php` (one row per owned formation, active formation in `member_field.matrixid`, only dispatched heroes can be placed, open slots / bonus / gold upgrade cost from MasterData `Matrix` / `MatrixLevel`)
- `c=pack&m=index|sale`: Bag ported from the legacy `pack.php` (unequipped items only, chip / scroll pricing, VIP 11 refund, daily sale limit for items enhanced to level 10+)
- `c=strengthen&m=index|strengthen_info|strengthen_start|clear_cd`: Blacksmith ported from the legacy `strengthen.php` (`EquipLevel` cost and success rate, VIP 5 critical +2 levels, writes level / powerup / sale price; legacy servers never applied an enhancement cooldown)
- `c=tavern&m=index|trade|trade_batch|get_list|buy`: Tavern, ported from the legacy `tavern.php` + `tavern_model` (MasterData `Pub` / `Trader`; recruiting writes `pub_log`)
- `c=practice&m=index|practice_start|practice_stop|go_leap|mop|get_levelup_price|levelup|get_unlock_price|unlock|turn`: Training grounds, ported literally from the legacy `practice.php` (`practice` slot table, `practice_stop` settles the trained exp, `get_unlock_price` uses the legacy fixed content, free / platinum / supreme leaps with `leap_log` / `general_leap_log`; a player level-up re-settles the training slots)
- `c=activity&m=index`: Daily special activities overview, schedule windows, and sign-in popo notifications; the world boss entry follows legacy `worldboss_init` (12:00–12:15, 20:00–20:15, popo when a reward is unclaimed)
- `c=chat&m=index|send|read|getuid|ban_uid`: Chat room (legacy port, `Hanjiang\Model\Chat`): world / country / private channels with the legacy Redis queues (public queue 30, country queue 10, a per-player queue for every online player), lord level 50 to speak, 200 bytes, 3-second interval, banned words and repeats visible only to the sender, mute via `member.status` / `statustime`, 24-hour per-player blocking. System messages, horns and battle-report shares (`send_to_chat`, 2 gold) use the legacy `get_message_str` templates. Once `GameWS` authenticates, the WebSocket worker pops the player's queue and pushes it as `chat_message` (same payload as `read`) instead of the client polling; HTTP workers notify it through Redis (`chat_ws_{uid}` / `chat_push_{worker}`), so any number of HTTP and WebSocket workers work together
- `c=mainquest&m=index|get_task_reward`: Main quests (legacy port, `MainQuest` data, `Hanjiang\Model\MainQuestTask`): one row per quest chain in `main_quest_log` (8 chains start from the quests without a prerequisite; the "login streak" chain is re-checked once a day), progress pushed by the ported modes (strengthen, generals, matrix, arena, elite, world boss, daily tasks, souls, tower, campaign first kills…), rewards (gold / silver / orders / gems / free leaps / soul / reputation) recorded once in `main_quest_reward`, then the next quest of the chain. The same list is `mainquest` in `c=member&m=index`
- `c=shop&m=index`: Item mall (legacy `Shop` table: army tallies, equip 7, 9–15, 53–56) & stamina purchasing
- `c=arena&m=index|action|buy_pk_num|get_reward|get_reward_list|clear_cd|send_to_chat|shop_index|shop_exchange`: Military ranking arena (legacy port): players join at lord level 26 as last rank + 1, challenge higher-ranked players and swap ranks on victory (legacy battle report), 15 daily challenges plus gold-bought extras, daily arena points by yesterday's rank, and the points shop from `ArenaExchange`
- `c=tower&m=get_scene_list|get_mission_list|get_mission_info|get_mopup_price|mop_up|pk|get_report_list|get_report_info|get_price|buy|reward_info|get_reward|send_to_chat`: Soul Star Road (legacy port, lord level 51+): 7 star fields and 175 missions from `SoulScene` / `SoulMission`, defenders from `SoulMonster` with legacy battle reports, first-kill soul rewards and strategy reports, gold-priced sweeps, 10 daily free attempts plus bought extras, and the daily soul reward with gold multipliers from `SoulReward`
- `c=pass_general&m=index|action|achievement_reward|action_achievement_reward|reward_info|action_reward|lottery_index|action_lottery`: Pass General limited-time event (legacy port, no battle): dribble past 11 generals on 4 layers by `PassgeneralInfo` pass rates (3 free tries a day, then gold), weighted rewards from `PassgeneralReward`, layer chests and the super prize from `PassgeneralBoxReward`, clear-count achievements, lottery-ticket exchange (`ActkemariExchangeLottery`); daily reset and end-of-event settlement in `PassGeneralCron`. The event window in `ActivityTime` ended on 2016-12-31, so every call currently returns -1
- `c=soul&m=index|get_soul_info|get_general_info|levelup|convert|equip|unequip|get_unlock_price|unlock|get_pack_info|merge_index|merge_action`: Souls (legacy port, lord level 51+): equip souls into a general's 8 slots (unlocked by rebirth count, one soul per type), level them up with soul essence, convert them back into essence, unlock bag slots with gold, and merge two max-level purple souls into a level-16 red soul. Equipped souls add morale / might / intellect / HP% / dodge / insight / crit / crit damage / hit / precision / anti-crit / anti-stun in battle (generals with rebirth > 0) and might / intellect / HP% on the general panel
- `c=country&m=create|get_rank|get_member_list|apply|del_apply|get_apply_list|get_audit_list|ignore_all|audit|publish_notice|betray|make_way_info|make_way|kick_out_info|kick_out|get_usurp_info|usurp|storage|donate|get_salary|search|get_country_info|learn_index|learn|fight_notice`: Player-founded nations (guilds; `member.country` is the guild id): founding, rankings, applications & audits, notices, abdication, kicks, promotion/usurpation duels, treasury donations, daily salaries, and the National Preceptor training
- `c=cultivate&m=index|roll|save|giveup`: Cultivation, ported from the legacy `cultivate.php` (MasterData `GeneralCulture`; the pending roll is cached under the legacy `cultivate_{uid}_{gid}` key, `cultivate_log`)
- `c=dailysign&m=index|sign`: 30-Day monthly continuous check-in, tiered silver/gold rewards, and anti-duplicate validations
- `c=logined&m=index|get_reward`: 1-15 Day consecutive login rewards, daily progression, and streak resets
- `c=pay&m=get_product_list`: Recharge products (legacy port, `AlipayProduct`; rebate products while the `rebate` activity runs and the player has not recharged during it; per-tier "bonus, once" note until that gold tier is first bought). Crediting is the channel-independent legacy core `Hanjiang\Model\Pay::complete(uid, productId, orderId, channel, amount)`: idempotent per order id and serialized per player, it credits gold (plus rebate / festival bonus), raises the VIP level from the cumulative RMB (`Vip.toplimit`), records the first recharge and per-tier first purchases (later new tiers get the tier's gold again), clears cooldowns at VIP 3, adds Epic counts at VIP 11–13, sends the VIP mail and writes `pay_order` / `pay_log` / jump-rebate orders. Channel order creation and callbacks (`get_pay_info`, `create_91_order`, `create_pp_order`, `create_kuaiyong_order`, `szf_order`, …) wait for the developer's channel decision
- `c=vip&m=index|get_gift|get_vip_reward`: VIP (legacy port): VIP level and progress from `vip_log`, per-level privilege text and level gift contents (`Vip` + `VipReward`), gifts claimed in level order (equipment, generals — owned ones below VIP 10 turn into gems at 80% of the tavern price —, silver, gems, jade, souls, free leaps, reputation); `get_vip_reward` returns 0 as in legacy
- `c=vipwage&m=index|get_vip_wage`: VIP daily wage (legacy port, `VipWage`, silver × lord level, doubled during `double_vip`), once per calendar day via `member_activity.vipwage_dateline`
- `c=vipreward&m=index|get_reward`: VIP repay activity (legacy port, `NewyearVipreward`, activity `viprepay` ended 2016-12-31 so it returns -1), one claim per tier per activity version in `newyear_vipreward_log`
- `c=rebate&m=index|get_reward`: Big Bear jump rebate (legacy port; activity `jump_rebate` is not scheduled in the legacy data): once a player's recharges during the activity reach 300 gold, 100 gold a day for 30 days
- `c=message&m=index|get_message|send_message|del_message|open_gift|update_read_status|report_detail|get_notice|get_new_trends|update_trends_status`: Letters (legacy port, `Hanjiang\Model\Message`): system / VIP / arena / country notices as bubble letters and player / GM / country letters as mail in `pm` (new-letter flags `member.newpm` / `newmail`, shown in `c=member&m=index`), attachments (gold / silver / orders / free leaps) claimed once, two-sided deletion, country letters from the king (100 gold to another guild), and PvP battle replays stored in `pm_report` by arena, sparring, beauty raids, contest of heroes, country mines and country usurpation. Announcements and daily trends come from the cross-server uc service in legacy and are empty here
- `c=gmmail&m=index|get_message|send_message|del_message|grade`: New GM mailbox (legacy port, `gm` table): player feedback to GM and rating GM replies (GM replies are a back-office feature and are not ported)
- `c=friend&m=index|add_friend|del_friend`: Social brotherhood system, friend additions, duplicate blocks, and relations
- `c=mine&m=index|caikuang|zhanling|get_silver|give_up`: Silver mine occupations, idle yield accumulation, and resource harvests
- `c=beauty&m=index|get_beauty_info|get_say_info|wakeup_beauty|rest_beauty|peace|loot_index|loot_reflush|loot_action|buy_loot_times|send_to_chat|report_detail|advance_index|advance_action|level_up|level_up_status|cancel_levelup|active_index|active_action|gift|give_flower|buy_rose|action_special_reward|conversion_chip`: Bronze Sparrow Terrace beauties (legacy port, lord level 60+): seven beauties from `BeautyTemp`, up to three active at once (levels 60 / 90 / 130); six chips per beauty (`beauty_own_chip`) start a one-hour level-up, every 20 levels an advance paid in yellow / purple gems, soul, reputation, roses and diamonds (`BeautyAdvanced`); greet / court / favour interactions (5 a day) raise favour and draw `BeautyPamper` rewards; roses (`member_integral.beauty_rose`) activate skills for 24 hours: battle stats (`Beauty::addition`, counted in team building, the battle engine's dodge / insight / crit damage and the general panel) and resource output collected through `action_special_reward`. Chips can be looted from other players in a `battle_pk` fight (10 free tries a day plus purchases, one-hour 50-gold peace shields, `BeautyLootBox` chest rewards)
- `c=mount&m=index|get_info_by_id|get_level_info|levelup|fit_on|take_down|get_mount_by_page`: Mounts (legacy port, lord level 120+): mounts and mount chips in `own_mount`; each general carries a main mount `mid0` and backup mounts `mid1`~`mid4` (main at full stats, backups at ceil(× 0.3), read by battle teams); no two mounts of the same special type 1 / 2 on one general; levelling feeds other unequipped mounts (their `toexp`) through `MountLevel` up to level 20 (`mount_exp_log`, eaten mounts in `mount_info_log`); experience horses 101 / 102 cannot level
- `c=gem&m=index|get_gem_by_id|adron|pick_off|level_up|gem_turn|get_gem_info|get_gem_by_page|mergegem_index|get_20gem_by_page|merge_gem`: Gems (legacy port, lord level 140+): gems in `own_gem`, gem chips `member.gem`, bag usage `member.gemuse`; socketing into slots 1~8 of the equipment of formation generals by equipment type, level-ups paid in chips (`GemLevelup` × type ratio), gem-to-chip conversion, and merging two same-type level-20 gems into a level-21 gem (`own_gem_mergelog`)
- `c=heaven&m=index|get_mopup_price|mop_up|pk|get_report_list|get_report_info|buy|reward_info|get_reward|send_to_chat|get_price`: Tower of Heaven (legacy port, lord level 160+): 200 floors from `HeavenMission` with defenders from `HeavenMonster` and legacy battle reports, first-kill rewards and strategy reports, 10 daily free attempts plus 20-gold purchases, 5 / 10 / 50 / 100 sweeps, and the daily reward with 2 / 4 / 8× gold tiers. Refine / colour / blood stones and equipment essences are granted into `MemberStone` / `Equipment` as legacy does
- `c=worldboss&m=index|powerup|battle|reward`: World boss Xingtian (legacy port, lord level 46+): two sessions a day, 12:00–12:15 and 20:00–20:15, one per player per day; the boss is `WorldBoss` row 1, its health bar drains with the remaining time, and fights use legacy `battle_worldboss` (with battle reports); 60 s revive cooldown (10 gold to revive now), silver (level × 500) / gold (10) cheers of +10% each up to 200%; after the session, arena points by personal total-damage rank (`WorldBossIntreward`, last hit × 1.5). State lives in Redis (legacy key names, atomic accumulation); unclaimed rewards are granted and all keys cleared by the daily 00:30 job (`Hanjiang\Cron\WorldBossCron`, legacy `cron_acttask::worldboss`). `openboss` / `openbossreward` in `c=member&m=index` follow the legacy game
- `c=siege&m=index|capital_info|occupy|battle_prepare|join_battle|action|get_reward|get_report|siege_notice`: Country war over capitals (legacy port; a country is a player guild): the 13 capitals of `CountryCityEffect` open with the server's farthest campaign progress; a guild's king or top-3 officials claim one capital before Friday 18:00 (the king may pay 1000 gold for a "supreme siege" duel against the holder's king, VIP 7+), members sign up on Saturday in the hour before the capital's start time, and `Hanjiang\Cron\SiegeCron` resolves each contested capital with the legacy 3-lane team battle (`BattleRush`, player or `CountryMission` NPC armies) 3 minutes before it starts; players replay the battle report, claim country-level rewards (×3 for the winner) and the holder guild's members enjoy the capital effect (levy / tower / sanctum / heaven bonuses). Participation flags are cleared every Saturday 00:00. `country_war` and related fields in `c=member&m=index` follow the legacy game
- `c=gvg&m=index|betting|sync_info`: Guild vs Guild (GVG) cross-server tournament brackets, championship betting, and victory dividends
- `c=general_inherit&m=index|get_inherit_info|preview|go_inherit`: Hero inheritance, ported from the legacy `general_inherit.php` (MasterData `GeneralInherit`; free inherits `member_field.free_inherit`, `inherit_times` / `inherit_log`)
- `c=blackmarket&m=index|buy|lock|refresh`: Western exotic black market, rare artifact locks, and shelf refreshes
- `c=first_recharge&m=index|get_reward|lottery|action`: First-recharge rebate tiers and the Fate Wheel, ported from the legacy `first_recharge.php` with `FirstRecharge` / `FirstRechargeLottery` data; state lives in `first_recharge_log` (written by the payment core on the first recharge) and `member_field.first_recharge`
- `c=overseastrade&m=index|goout|trade`: Maritime silk road, fleet sailings, exotic spices, and merchant profit claims
- `c=studfarm&m=index|action`: Western stud farm (legacy port, lord level 120+): three tiers from `MountRatio` paid in yellow / purple tavern gems (`trader_log`); the first draw of each tier yields its top quality (recorded in `mount_log`), later draws roll the tier weights, then a uniform `MountTemp` template of that quality (ids below 100)
- `c=jadegamble&m=index|mining|gambling|harvest`: Rough jade ore mining, high-stakes stone cutting, and imperial emerald harvests
- `c=treasuremap&m=index|refresh|sale`: Tomb raiding compass geomancy, star-tier treasure maps, and royal mausoleum excavation
- `c=workshop&m=index|get_reward|levelup`: Official siege weaponry workshop, arrow manufacturing, and military tribute yields
- `c=sanctum&m=index|select_map|get_mission_info|action|buy_times|get_mission_report_list|get_report_info|send_to_chat|get_reward`: Four Symbols Sanctum (legacy port, lord level 140+): 5 sanctums × 5 floors × 10 missions from `SanctumMission` with defenders from `SanctumMonster` and legacy battle reports, first-clear strategy reports, 10 daily attempts plus 20-gold purchases, gold-priced sweeps, and the daily reward with `SanctumReward` multipliers. Gem chips and gem drops are granted into `OwnGem` as legacy does
- `c=compete&m=index|get_reward_list|get_reward|buy_pk_num|action|send_to_chat`: Contest of Heroes (legacy port): a time-limited player ladder (activity `compete` in `ActivityTime`, which ended in 2016, so every call reports "not open" like the legacy server); players join at lord level 26 as last rank + 1 with 100 challenges, beat higher-ranked players to swap ranks (legacy battle report, mail to the loser), buy extra challenges for 2 gold, and claim `CompeteReward` / `NewcompeteReward` rank rewards (top 200) after challenges close; unclaimed rewards are granted by `Hanjiang\Cron\CompeteCron` within 10 hours after the activity ends. Mounts and jade in the rewards are granted into `OwnMount` / `MemberJade` as legacy does
- `c=countrysacrifice&m=index|action`: National ancestral altar sacrifices, nationwide buffs, and reputation blessings
- `c=expostulation&m=index|authstr|adopt|expostulation_list|put_expostulation|support|refresh|get_reward|get_expostulation_by_user`: Guild expostulation, ported from the legacy `expostulation.php` (MasterData `ExpostulationTemp`, `expostulation` / `expostulation_support` tables; task counters are fed by strengthen, map and general exp)
- `c=banquet&m=index|create_team|join_team|kickout_team|dismiss_team|getout_team|action`: Country banquet, ported from the legacy `banquet.php` (MasterData `CountryBanquet`, teams in Redis `banquet_team_{country}_{caption}`, `country_banquet_log`)
- `c=drink&m=index|go_drink`: Drinking, ported from the legacy `drink.php` (MasterData `Drink`, daily count `member_activity.drinktime`, `drink_log`)
- `c=dice&m=index|shake_dice`: Fortunate god dice shaking, triple leopard jackpots, and silver/gold rewards
- `c=sacredtree&m=index|watering`: Money-growing sacred tree cultivation, canopy watering, and gold/silver harvests
- `c=flop&m=index|turnover`: Nine-grid card flips, card turnovers, and golden jackpot awards
- `c=challenge&m=action`: Sparring (legacy port): a free PK against any player with the arena battle engine (`battle_pk`), returns the legacy battle report and mails the opponent
- `c=elite&m=index|get_member_info|join_team|get_country_info|get_server|action|buy_number|sendinvite`: Elite team campaigns (legacy port): 83 legions from `EliteCity` (open once the player has cleared the legion's last main-campaign mission), defenders from `EliteMission` → `EliteMonster` fought as a legacy 3-lane team battle (`BattleRush`, legacy `battle_rush`), first-kill / repeat experience and item rewards, VIP 8 "supreme team" bonus for gold, 10 daily attempts plus gold-bought extras (`Price.elite`), open 12:00–14:00 and 19:00–21:00. In the legacy game `index` / `get_member_info` / `join_team` / `get_country_info` / `action` are called by a separate real-time team server that is not in the legacy package; that server is rebuilt as `EliteRoom` on the WebSocket gateway (below), and `action` is internal-only: the HTTP route returns `{status: 0, info: []}` and battles start only from the WebSocket `startfight`
- Elite real-time teams (WebSocket, `Hanjiang\WebSocket\EliteRoom`): the legacy client `elitepop.js` connects through the `socket.io.min.js` adapter (frames `{action, data}` up, `{type, data}` down, an app-level `ping` every 20 s) to the address from `c=elite&m=get_server` (`socket_server_url`; empty → `HJ_CONFIG.wsUrl`). Events: `login` (same Redis token as HTTP; `loginstatus` 1 / 0 / -1 / -2 / -3), `eliteindex`, `teamlist` (pushed only to players viewing that legion), `createTeam`, `jointeam` (max 3, country teams same-country only), `exitteam`, `fireteam`, `firemember`, `changeorder`, `teaminvited`, `startfight` (`readyfight` then the `EliteHandler::fight` result as `enterfight` to every member). Team state lives in Redis under one global lock, so several WebSocket workers can share it; a closed or idle connection leaves its team (a leader leaving dissolves it). See `docs/LEGACY_GAP.md` for the parts the client does not reveal
- `c=muster&m=index|get_general_info|go_battle|go_rest|sale_general`: Muster platform; `index` / `get_general_info` / `go_battle` / `go_rest` ported from the legacy `muster.php` (dispatch roster `general.goout`, at most 8 heroes; resting removes the hero from every formation, stops training on `confrim`, unequips all items and mounts, keeps at least one hero dispatched); `sale_general` ported from the legacy version (hero depreciation: sells a 名将榜 hero for the `Pub` sale price times the VIP ratio, refused for book-achievement / 战神殿 / event heroes; stops training, takes the hero off the formation, unequips equipment, souls and mounts, sets the `member_field.general_sale` cooldown and recomputes the `general_book` bonus); `index` reports the `ongeneral` new-hero bubble
- `c=business&m=index|go_business|buy|refresh`: Four Seas commerce, merchant fleet caravans (Luoyang, Chengdu, Jianye), and trade profits
- `c=gambling&m=index|buy|cut|sell`: Treasure bowl gambling, rough stone cuts, and pawnshop silver liquidations
- `c=task&m=index|accept_task|give_up|refresh|get_reward`: Daily tasks (legacy port, lord level 41+, `Task` data, `Hanjiang\Model\DailyTask`): three task slots in `task_log`, one task in progress at a time with progress in Redis `task_plan_{ymd}_{uid}`, counted by the ported modes (gold spending, arena, strengthen, world boss, epic, island, country mines, campaign), 6 completions a day (`member_activity.task_number`, reset by the daily cron), first refresh of the day free then 5 gold, rewards of silver × lord level plus equipment chips / purple gems / jade / reputation
- `c=talent&m=index|levelup|get_info`: Lord talents (legacy port, lord level 91+): five talents (physical / strategy attack and defense, HP) up to 300 stars with jade and talent scrolls, gold-guaranteed upgrades, and insight breakthroughs at stars 20 / 40 / 60 / 80 / 100 / 200 that can drop stars on failure; the bonuses apply to every general in battle and HP on the general panel
- `c=advanced&m=index|get_general_info|get_skill_info|level_up|start_advanced|advanced_inherit|get_advanced_inherit_info`: General advancement (legacy port, lord level 100+): four passive skills per general (attack / physical defense / strategy defense / HP talent coefficients, up to level 25) levelled with free-leap cards; when all skills and the general level qualify, the general advances (quality 1 -> 7, `AdvancedInfo`) for yellow / purple gems, soul and reputation, gaining stats and HP; advancement can be inherited by a lower-quality general on a VIP-dependent cooldown. The bonuses count in team building, the battle engine's talent coefficients / HP and the general panel
- `c=refine&m=index|item_index|item_selected|master_refresh|refinning|inherit|preview|achieve_index|get_achieve_info`: Equipment refinement (legacy port, lord level 160+): refine levels 0 -> 99 (`equipment.hammer`, `RefinePromote`) paid with jade and refine / colour / blood stones, success rate from the current refine master (`RefineMaster`, refreshed free at 04:00 / 12:00 / 20:00 or for 100 gold), refine-level inheritance between items, and refine achievements when all four equipped items are refined; the refine bonus counts in team building and the general panel
- `c=star&m=index|level_up|refining_index|refining`: Lord star fate (legacy port, lord level 80+): three pages of fate nodes from `StarGrid` (up to level 5, gated by prerequisite nodes and lord level) paid with three kinds of star essence refined in the furnaces of `StarRefine` from jade, tavern gems and gold; the accumulated bonuses apply to every general in battle (stats, HP, morale, attack / defense, hit, crit, crit damage, dodge, insight, anti-stun, anti-crit) and on the general panel
- `c=war_college&m=index|level_up|use_buff`: Country war college, ported from the legacy `war_college.php` (MasterData `WarCollegeSkill`, `war_college_*` / `warcollege_use` tables; the skill bonuses apply in battle, hero info and `get_country_info`)
- `c=lottery&m=index|action`: Celestial fortune lottery wheel, sector spins, and jackpot draws
- `c=generaltask&m=index|get_list|action|get_report_list|get_report|buy_number`: Hero tasks, ported from the legacy `generaltask.php` (MasterData `GeneraltaskMission` / `GeneraltaskMonster`, daily count `member_activity.generaltasktimes` reset to 10, `generaltask_*` tables)
- `c=general_book&m=index|get_book_list|get_achievement_list|levelup`: Heroes chronicle (legacy port, lord level 81+): chronicle heroes (`Book`) carry a single-hero bonus, collecting the heroes of an achievement (`BookAchievement`) unlocks it and tavern gems upgrade it to quality 6 (`BookAddition`); the summed bonuses (`own_addition`) count in team building, the battle engine's crit / dodge / insight and the general panel
- `c=copies&m=index|get_monster_list|get_monster_info|pk|get_mopup_price|mop_up|buy_times|get_report_list|get_report_info|send_to_chat` (client sends `d=newequip`): Pacification copies (legacy port, lord level 110+): 3 copies × 3 difficulties × 10 monsters from `CopiesInfo` (defenders: the monster row's position1~9 → `CopiesInfo` rows) with legacy battle reports, first-kill / drop rewards from `CopiesReward` (reputation, equipment, forge materials from `MaterialsTemp` stored in `own_materials`), first-kill strategy reports, 5 free attempts per monster per day plus VIP-limited gold purchases (`CopiesVipBuyTimes` / `CopiesBuyTimesGold`), and 1 / 5 / 10 sweeps
- `c=island&m=index|get_mission|pk|get_report_list|get_report_info|send_to_chat`: Gold & Silver Island (legacy port, lord level 36+): 5 guardians per 10-level bracket from `Island` with defenders from `IslandMonster` and legacy battle reports, command cost rising with each daily win, guaranteed silver plus a gold drop chance, and first-win strategy reports; progress returns to the bracket start each day
- `c=herothrone&m=index|go_back|start|action|buy_times|end_battle`: Hero Throne (legacy port, lord level 101+): a 300-floor run from `HerothroneMission` with defenders from `HerothroneMonster` and legacy battle reports, floor rewards and chests every 10 floors, 2 free runs a day plus VIP-limited 1000-gold purchases, and VIP 10+ gold continues after a loss. The cross-server ranking (uc server) is not ported, so the ranking list is empty
- `c=beatengod&m=index|beaten|get_box_reward`: Golden martial god body beatings, divine crystal drops, and trials chests
- `c=war&m=index|entry|point_shop`: Three Kingdoms Imperial Capital Cross-Server Holy War, enlistments, and military merit shop
- `c=countrymine&m=index|get_countrymine_info|caikuang|rob|get_reward|give_up|send_to_chat`: Country jade mines (legacy port, lord level 91+ and in a guild): 10 pages of mines typed by `CountryMineRatio`, 3 occupations and 3 robberies a day, 15-minute occupations, robbing miners of other guilds with a legacy PvP battle report (mail plus a guild fight-notice entry), and random `CountryMineEvent` events (purple gems, gold, jade multipliers). Jade is granted into `member.jade` (`MemberJade`) as legacy does
- `c=countryboss&m=index|powerup|battle|reward`: Country boss Chiyou (legacy port): Fridays 20:30–20:45 for country (player guild) level 40+ and lord level 50+, one boss per country; the boss is `WorldBoss` row 2 with 2 billion × 1.03^(past weekly kills) health (kill log `country_boss_log`), health is decremented atomically and killing it counts as a win; 60 s revive cooldown (20 gold), silver (level × 1000) / gold (20) cheers; the top 50 damage dealers in the country get `CountryBossReward` gems (× 1.1 per 10 kills, last hit × 1.5) and equipment shards
- `c=hitegg&m=index|hit_egg`: Golden fortune egg smashings, hammer strikes, and silver/gold crit explosions
- `c=levelgift&m=index|get_reward`: Lord level-up milestone packages (Levels 5, 10, 20, 30, 40, 50) and growth funds
- `c=countryshop&m=index|buy`: National prestige tax shops, red artifact blueprints, and military provisions
- `c=country_taxes_shop&m=index|buy`: National taxes mall, treasury asset claims, and daily quota exchanges
- `c=equip_forge&m=index|fabrication|resolve`: Celestial gear forges, artifact fabrications, and gear scrap smelting
- `c=totallogin&m=index|action`: Continuous login festival milestones (Days 1 to 14) and carnival silver/gold bundles
- `c=epic&m=index|select_epic|refresh|force|buy_lock_times|buy_refresh_times`: World Epics puzzle (legacy port, no battle, lord level 150): pick an epic, refresh 5 slots by `EpicChip` weights with locks, complete it for reputation (`Epic.reward`) or claim it with gold; VIP-based daily refresh / lock counts, extra counts bought with gold
- `c=activationcode&m=index|get_reward`: Universal CDKey / redemption code claims (VIP666, VIP888, SG888) and ingot/silver windfalls
- `c=exercise&m=index|open_gift`: Imperial military exercise drills, gift box openings, and deployment orders
- `c=information&m=index|get_info|get_own_achievement|get_all_beauty_info|get_own_soul|get_talent_list|get_general_gem|get_general_mount|get_country_info|get_star_info`: Another player's profile; the beauty, soul, lord talent, gem, mount, country college and star fate views are legacy ports (the country college skill list stays empty until the war college is ported); `index` / `get_info` / `get_own_achievement` are still the new server's own views
- Currency logs (legacy `member_model` `_gold_log` / `_silver_log` / `_act_log` / `_reputation_log` / `_soul_log`, `Hanjiang\Model\FlowLog`): every gold / silver / order / reputation / soul change made through `Member::deductCurrency` / `addCurrency` / `addReputation` / `subReputation` writes `gold_log` / `silver_log` / `act_log` / `reputation_log` / `usesoul_log` with the requesting `c` / `m` (crons record their legacy cron names); spending gold also counts daily task 5. Paid-point statistics of the legacy `stat_model` go to `stat_pay` (`Hanjiang\Model\StatPay`)
- `c=invitation&m=index|change|get_reward`: Brotherhood recruitment codes, recruit dividend ingots, and referral milestone gifts

---

## 🧪 Automated Testing

All core API endpoints and combat simulation loops are covered by PHPUnit integration tests (`tests/Feature`); legacy formulas in `LegacyStats` and team building in `TeamBuilder` are checked against real legacy database rows (`tests/Unit`):

```bash
./vendor/bin/phpunit                      # all suites
./vendor/bin/phpunit --testsuite Unit     # formulas and team building (TeamBuilder needs MySQL, no Redis)
./vendor/bin/phpunit tests/Feature/ItemStorageTest.php  # item storage models (spawns tests/Support/storage_worker.php processes for concurrency)
node tests/js/socket_io_adapter.test.js   # socket.io.min.js adapter (frame shape, heartbeat, address)
```

PHPUnit boots through `tests/bootstrap.php`: the same `bootstrap.php` and `routes.php` as the server, then one MySQL / Redis connection for the test process, so Feature tests run requests through the production middleware pipeline (`OmniPHP\Http\Router::dispatch`, via `tests/Support/Gateway.php`). `tests/Feature/HttpGatewayTest.php` drives `OmniPHP\Http\Kernel::handle` end to end: static files and their cache headers, the dynamic `config.js`, API entry points, CORS preflight, the `403` text response and the `-500` exception envelope.

Elite real-time end-to-end smoke (starts the real server with the open-hours check pinned inside the window; test-only entry point):

```bash
php tests/e2e/elite_smoke_server.php start -d
node tests/e2e/elite_smoke.mjs            # two guests: create → join → startfight, both receive enterfight
php tests/e2e/elite_smoke_server.php stop
```

---

## 🗺️ Roadmap

- [x] Initial Workerman HTTP gateway & static asset serving
- [x] Backward-compatible parameter dispatcher (`c` / `m`)
- [x] Authentication & guest account onboarding flows
- [x] Pure memory 9-grid battle simulation prototype
- [x] Desktop mouse-to-touch seamless bridge (unified PC & Mobile gameplay)
- [x] Role creation and prologue story tutorial loop
- [x] Redis high-speed caching layer (`token`, `player`, `session`)
- [x] MySQL schema migration & persistent storage integration (`zc_sanguo`)
- [x] Main city infrastructure & campaign chapter dispatch (`city`, `map`)
- [x] End-to-end combat loop with deterministic battle report playback
- [x] All 16 core game systems migrated to stateless Handlers
- [x] Equipment equip/unequip system with real-time dynamic attribute calculation
- [x] Blacksmith enhancement system (`strengthen`) with legacy `EquipLevel` cost, success rate, and VIP critical enhancement
- [x] Tavern (`tavern`) ported from the legacy version, recruiting writes `pub_log`
- [x] Training grounds (`practice`) ported from the legacy version, including `practice_stop` settlement and the level-up slot hook
- [x] Full combat integration linking dynamic hero stats and equipment bonuses into 9-grid simulation
- [x] Legacy formation storage (`matrix`): per-formation rows, active formation switching, anti-duplicate validation, and gold upgrades; battles use the active formation
- [x] Multi-hero cooperative combat simulation with live battle report replay and positioning strategy
- [x] Military Arena (`arena`) ported from legacy: player rank challenges and swaps, legacy battle reports, daily rank points and the points shop
- [x] Main city infrastructure & imposition/levy system (`city`) with critical harvest multipliers and gold forced taxation
- [x] Native WebSocket full-duplex gateway with player authentication and real-time chat room push (legacy chat queues delivered as they arrive)
- [x] Pure RFC 6455 opcode 0x9/0xA protocol-level ping/pong heartbeat standard aligned with `smart-book` (zero application payload overhead)
- [x] Directly consume native `OmniPHP\Http\Context` (modeled after Go Gin) across all Handlers and Middlewares without redundant subclassing
- [x] Directly consume native `OmniPHP\WebSocket\Context` and `Broadcaster` for full-duplex events, sessions, and headless unit testing
- [x] Eliminate redundant `src/Database/` wrappers; directly consume native `OmniPHP\Database\DB` and `OmniPHP\Cache\Redis`
- [x] Integrated `OmniPHP\ArchitectureGuard` to enforce clean architecture and prevent custom infrastructure re-wrapping
- [x] Client network layer with exponential backoff auto-reconnect, jitter, and zombie connection sweeping (`sweep`)
- [x] Soul Star Road (`tower`) ported from legacy: legacy missions and defenders, battle reports, first kills, sweeps, attempt purchases and the daily soul reward
- [x] Pacification copies (`copies`) ported from legacy: legacy monsters, battle reports, first-kill and drop rewards, forge material storage (`own_materials`), sweeps and attempt purchases
- [x] Elite team campaigns (`elite`) ported from legacy: legions, legacy team battle (`battle_rush`) against `EliteMonster`, first-kill and drop rewards, supreme teams, attempt purchases and the daily reset, plus the real-time team rooms rebuilt from the `elitepop.js` protocol
- [x] Pass General event (`pass_general`, legacy port) and dynamic storyline quests (`mainquest`)
- [x] Player-founded nation (guild) system (`country`), souls (`soul`, legacy port), and warlord marrow cleansing cultivation (`cultivate`)
- [x] Full alignment with live client daily loops: Continuous Check-in (`dailysign`), VIP tiers & gifts (`vip`), in-game mailbox (`message`), social friends (`friend`), and silver mine territory conquests (`mine`)
- [x] Hall-of-Fame end-game systems: Bronze Sparrow beauties (`beauty`), legendary steeds (`mount`), divine gear gem socketing (`gem`), the Tower of Heaven (`heaven`), and world boss Xingtian (`worldboss`)
- [x] Country war over capitals (`siege`, legacy port), GVG cross-server championship tournament betting (`gvg`)
- [x] Chat room (`chat`) ported from legacy: world / country / private channels, horns, system announcements and battle-report sharing with the legacy message templates
- [x] Martial inheritance system (`general_inherit`), Western black market (`blackmarket`), first-recharge rebate & Fate Wheel (`first_recharge`), and maritime silk road (`overseastrade`)
- [x] High-tier diversion mechanics: Bole stud farm (`studfarm`), rough jade gambling (`jadegamble`), tomb raiding maps (`treasuremap`), and weaponry workshop (`workshop`)
- [x] Four Symbols Sanctum (`sanctum`), the Contest of Heroes ladder (`compete`, legacy port), national altar sacrifices (`countrysacrifice`), and imperial court expostulations (`expostulation`)
- [x] Leisure & social festivity systems: Heroic banquets (`banquet`), plum wine deliberations (`drink`), celestial dice shaking (`dice`), and money sacred tree (`sacredtree`)
- [x] High-tier martial arenas & recruitment outlets: Card turnover prizes (`flop`), player sparring (`challenge`, legacy port), and muster severance management (`muster`)
- [x] Merchant economy & talent mastery: Four Seas commerce (`business`), treasure bowl gambling (`gambling`), daily military bounties (`task`), and lord talents (`talent`, legacy port)
- [x] Pinnacle tier ascensions: General advancement (`advanced`, legacy port), equipment refinement (`refine`, legacy port), lord star fate (`star`, legacy port), and War College technologies (`war_college`)
- [x] Chronicles & campaign expeditions: Fortune lottery (`lottery`), warlord expeditions (`generaltask`), and the heroes chronicle (`general_book`, legacy port)
- [x] Epic holy wars & overseas frontiers: Gold & Silver Island (`island`), Hero Throne (`herothrone`), martial god golden body trials (`beatengod`), and Imperial Capital Holy War (`war`)
- [x] Protocol & media fidelity: HTTP 206 Partial Content byte-range media streaming, Web Audio capture unlock, classic hero asset binding (`HERO_CONFIGS`), levelup exp bar normalization, and defeat zero-yield rule

- [x] Border battlegrounds & growth milestones: country jade mines (`countrymine`, legacy port), country boss Chiyou (`countryboss`), golden egg smashings (`hitegg`), and level-up milestone packages (`levelgift`)
- [x] National trade & celestial forges: National prestige stores (`countryshop`), gear fabrications (`equip_forge`), continuous login festivals (`totallogin`), and the World Epics puzzle (`epic`, legacy port)
- [x] Operational giftings & achievement telemetry: Universal CDKeys (`activationcode`), military drills (`exercise`), lord combat overview (`information`), and brotherhood recruitment (`invitation`)
- [x] Legacy player item storage models (`src/Model/`): jade `MemberJade`, tavern gems `TraderLog`, refine / color / blood stones `MemberStone`, gems and gem chips `OwnGem`, souls and the soul bag `OwnSoul`, mounts and mount chips `OwnMount`, artifact chips `CountryDebris`, and equipment chips / talent scrolls / equipment essence on `Equipment`, each writing its legacy log table; decrements are conditional atomic updates, multi-row storages are serialized by a per-player row lock (`Member::locked`). Every ported reward site (pass general, elite, country mines, contest, country boss, salary, siege, Soul Star Road, copies, Tower of Heaven, Hero Throne, sanctum, campaign drops, arena exchange) grants into these storages as legacy does, and battle teams / the general panel include gem power and mount stats
- [x] Lightweight domain thin models (`src/Model/`) with native `SoftDeletes`, automatic type casting, and encapsulated domain methods (`Member`, `General`, `Equipment`)
- [x] High-concurrency debouncing & anti-cheat: Atomic request reentrancy protection via `ThrottleMiddleware`
- [x] PHPUnit Unit + Feature suites (213 tests, 11289 assertions)

---

## 📄 License

This project is licensed under the [MIT License](LICENSE).
