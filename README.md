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
├── config/                  # Server configuration
│   └── app.php              # HTTP ports, worker count, public path
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
│   │   ├── MiddlewareInterface.php
│   │   ├── CorsMiddleware.php             # Cross-origin and OPTIONS preflight
│   │   ├── GameProtocolMiddleware.php     # Dispatches c=xxx&m=yyy to Handler actions
│   │   └── ResponseEnvelopeMiddleware.php # Standardizes JSON response envelopes
│   ├── Handler/             # Stateless domain action handlers (DDD Layout)
│   │   ├── Auth/            # Authentication & onboarding (Ios, User, Login, Register)
│   │   ├── City/            # City infrastructure, levy, VIP & gifts (City, Member, DailySign, Vip)
│   │   ├── General/         # Heroes, talents, ascension, training & tavern (General, Star, Soul, Tavern)
│   │   ├── Equip/           # Blacksmith, refinement, gems & forge (Pack, Strengthen, Refine, Gem, Forge)
│   │   ├── Campaign/        # 9-grid campaign maps, tactics & towers (Map, Matrix, Tower, Copies, Sanctum)
│   │   ├── Pvp/             # Military rankings, nations & siege wars (Arena, Compete, Country, Siege, War)
│   │   └── Activity/        # Commerce, gambling, banquets & quests (Chat, Shop, Trade, Dice, Task)
│   ├── Http/                # HTTP Gateway & Router
│   │   ├── Router.php       # Pipeline runner & static asset server
│   │   └── Response.php     # Protocol response helper
│   └── WebSocket/           # WebSocket gateway (port 18101)
│       ├── WebSocketHandler.php # Connection lifecycle, heartbeat, auth, world chat, marquee
│       └── EliteRoom.php    # Elite campaign real-time team rooms (legacy elitepop.js protocol)
├── tools/
│   ├── gen_master_data.php  # Regenerates src/MasterData from the legacy mysqldump
│   └── scan_legacy_gap.php  # Refreshes the generated part of docs/LEGACY_GAP.md
├── server.php               # Workerman service entrypoint
├── composer.json            # Dependencies & PSR-4 autoloading
├── README.md                # English documentation
└── README.zh-CN.md          # Chinese documentation
```

---

## 🚀 Quick Start

### Prerequisites

- **PHP >= 8.3** (PHP 8.4+ recommended)
- PHP extensions: `ext-pcntl`, `ext-posix`, `ext-json`, `ext-mbstring`
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
   Upgrading an existing database instead: apply the files in `database/migrations/` that are newer than your schema, in filename order (each file header says what it does). `20261031_01_general_equip_legacy_cols.sql` assumes general/equipment rows are test data: it truncates both tables and re-issues every player the legacy starting general. `20261031_03_matrix_legacy.sql` converts the one-row-per-player `matrix` table to legacy per-formation rows, fills `member_field.matrixid` from the old row, rebuilds `general.goout` from the old `pos` / formation slots, then drops `general.pos` / `general.matrix_id`. `20261031_04_arena_legacy.sql` assumes arena data is test-only: it rebuilds the arena tables (old ranks are not kept), adds the points table, and resets every player's `member_field.rank` to 0; players re-enter the ladder the legacy way the next time they open the arena. `20261031_05_tower_legacy.sql` assumes Soul Star Road (tower) progress is test-only (it lived only in Redis `tower_progress:{uid}`): it creates the legacy tower tables (`member_activity`, `soul_open_map`, `soul_log`, `soul_report`) and adds `member_field.soultimes`; old progress is not converted and every player starts from mission 1. `20261031_06_copies_legacy.sql` assumes pacification copies (copies) state is test-only (only a daily attempt counter in Redis `copies_times:{uid}`): it (re)creates the legacy copies tables (`copies_beat`, `copies_beat_log`, `copies_report`) and the forge material tables (`own_materials`, `own_materials_log`); nothing is converted and each player gets fresh legacy rows the first time they open a copy. `20261031_07_elite_legacy.sql` assumes elite campaign state is test-only (only a daily attempt counter in Redis `elite_times:{uid}`): it (re)creates `elite_log` / `elite_mission_log` and adds the `elite*` columns to `member_activity`; nothing is converted, existing activity rows start with 0 elite attempts until the next daily reset (an optional `UPDATE` at the end of the file grants 10 right away). `20261101_batch1.sql` assumes sparring (challenge), World Epics (epic) and Pass General (pass_general) state is test-only (it lived only in Redis `challenge_master:{uid}`, `epic_cleared:{uid}`, `pass_general_stage:{uid}`): it (re)creates `epic_log` and the `passgeneral*` tables and updates the `member_integral` table comment; nothing is converted, and those Redis keys are no longer read. `20261101_batch3.sql` assumes Contest of Heroes (compete), country mines (countrymine) and siege state is test-only (Redis `compete_score:{uid}`, `compete_times:{uid}`, `user_country_mine:{uid}`, `siege_reward:{uid}` and the invented `zc_sanguo_siege` table): it drops `zc_sanguo_siege`, creates the legacy `compete`, `compete_action_log`, `country_mine`, `country_fight_notice`, `country_city`, `country_city_report` and `country_siege_log` tables and adds `member_field.oncountrymine`; nothing is converted.

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
- `c=city&m=index`: Main city building levels, levy limits, and cooling states
- `c=map&m=index`: World map chapter states and unlock status
- `c=map&m=get_scene_list`: Campaign scene list (e.g., Daxing Mountain)
- `c=map&m=get_mission_list`: Enemy nodes and warlord defense lineups
- `c=map&m=mission`: Pre-battle enemy intelligence and lineup preparation dialog
- `c=map&m=action`: Battle trigger & in-memory 9-grid combat (legacy `battle_model` engine and report format: `process` / `assess` / `leftbuff` / `rightbuff`, plus `left_role` / `right_role`; `info.win` is the legacy `assess`; player team from the legacy formation / equipment formulas, defenders from the legacy `Mission` → `Monster` tables); on victory the legacy `Mission` probabilistic drop (`getrate` / `gettype` / `getvalue`) is rolled and granted
- `c=map&m=get_newreward_index|get_newreward|get_newresult`: Campaign node milestone chests & chapter clear grand reward claims
- `c=general&m=index|get_info|equip|unequip`: Hero panel ported from the legacy `general.php` (deployed heroes only, legacy `info_field`, equipment display power with refine / enhancement-achievement bonuses, legacy HP formula, legacy equip validations and status codes)
- `c=matrix&m=index|get_info|update_matrix|use_matrix|levelup`: Formations ported from the legacy `matrix.php` (one row per owned formation, active formation in `member_field.matrixid`, only dispatched heroes can be placed, open slots / bonus / gold upgrade cost from MasterData `Matrix` / `MatrixLevel`)
- `c=pack&m=index|sale`: Bag ported from the legacy `pack.php` (unequipped items only, chip / scroll pricing, VIP 11 refund, daily sale limit for items enhanced to level 10+)
- `c=strengthen&m=index|strengthen_info|strengthen_start|clear_cd`: Blacksmith ported from the legacy `strengthen.php` (`EquipLevel` cost and success rate, VIP 5 critical +2 levels, writes level / powerup / sale price; legacy servers never applied an enhancement cooldown)
- `c=tavern&m=index`: General recruitment tavern & guest trade
- `c=practice&m=index|practice_start|practice_stop|go_leap|mop|get_levelup_price|levelup|get_unlock_price|unlock|turn`: Martial training grounds, rapid leaps & sweeps, 8-slot unlocks & reincarnation
- `c=activity&m=index`: Daily special activities overview, schedule windows, and sign-in popo notifications; the world boss entry follows legacy `worldboss_init` (12:00–12:15, 20:00–20:15, popo when a reward is unclaimed)
- `c=chat&m=read|send`: World and country real-time messaging
- `c=mainquest&m=index`: Main storyline quest milestones and rewards
- `c=shop&m=index`: Item mall (legacy `Shop` table: army tallies, equip 7, 9–15, 53–56) & stamina purchasing
- `c=arena&m=index|action|buy_pk_num|get_reward|get_reward_list|clear_cd|send_to_chat|shop_index|shop_exchange`: Military ranking arena (legacy port): players join at lord level 26 as last rank + 1, challenge higher-ranked players and swap ranks on victory (legacy battle report), 15 daily challenges plus gold-bought extras, daily arena points by yesterday's rank, and the points shop from `ArenaExchange`
- `c=tower&m=get_scene_list|get_mission_list|get_mission_info|get_mopup_price|mop_up|pk|get_report_list|get_report_info|get_price|buy|reward_info|get_reward|send_to_chat`: Soul Star Road (legacy port, lord level 51+): 7 star fields and 175 missions from `SoulScene` / `SoulMission`, defenders from `SoulMonster` with legacy battle reports, first-kill soul rewards and strategy reports, gold-priced sweeps, 10 daily free attempts plus bought extras, and the daily soul reward with gold multipliers from `SoulReward`
- `c=pass_general&m=index|action|achievement_reward|action_achievement_reward|reward_info|action_reward|lottery_index|action_lottery`: Pass General limited-time event (legacy port, no battle): dribble past 11 generals on 4 layers by `PassgeneralInfo` pass rates (3 free tries a day, then gold), weighted rewards from `PassgeneralReward`, layer chests and the super prize from `PassgeneralBoxReward`, clear-count achievements, lottery-ticket exchange (`ActkemariExchangeLottery`); daily reset and end-of-event settlement in `PassGeneralCron`. The event window in `ActivityTime` ended on 2016-12-31, so every call currently returns -1
- `c=soul&m=index|upgrade`: Soul fate constellation slots, soul point upgrades, and permanent hero battle stat empowerment
- `c=country&m=create|get_rank|get_member_list|apply|del_apply|get_apply_list|get_audit_list|ignore_all|audit|publish_notice|betray|make_way_info|make_way|kick_out_info|kick_out|get_usurp_info|usurp|storage|donate|get_salary|search|get_country_info|learn_index|learn|fight_notice`: Player-founded nations (guilds; `member.country` is the guild id): founding, rankings, applications & audits, notices, abdication, kicks, promotion/usurpation duels, treasury donations, daily salaries, and the National Preceptor training
- `c=cultivate&m=index|roll|save`: Warlord marrow cultivation & attribute cleansing (Strength, Intellect, Vitality)
- `c=dailysign&m=index|sign`: 30-Day monthly continuous check-in, tiered silver/gold rewards, and anti-duplicate validations
- `c=logined&m=index|get_reward`: 1-15 Day consecutive login rewards, daily progression, and streak resets
- `c=vip&m=index|get_vip_reward|get_gift`: VIP tier privileges, daily VIP salaries, and promotion milestone gift packages
- `c=vipwage&m=index|get_vip_wage`: VIP daily salary distribution, tiered silver/free leaps/souls claim and status checks
- `c=vipreward&m=index|get_reward`: VIP repay celebration rewards, milestone promotion packages, and claim validations
- `c=message&m=index|get_message|send_message|del_message`: Mailbox system, secret pigeon correspondence, and battle reports
- `c=friend&m=index|add_friend|del_friend`: Social brotherhood system, friend additions, duplicate blocks, and relations
- `c=mine&m=index|caikuang|zhanling|get_silver|give_up`: Silver mine occupations, idle yield accumulation, and resource harvests
- `c=beauty&m=index|give_flower|gift`: Bronze Sparrow harem beauties (Diaochan, Qiao sisters, Zhenji), intimacy, and army aura buffs
- `c=mount&m=index|fit_on|take_down|levelup`: Legendary steeds (Red Hare, Dilu, Jueying), steed riding, and mount stat training
- `c=gem&m=index|adron|pick_off|merge_gem`: Divine gear gem socketing, unsocketing, and 4-to-1 gem synthesis fusion
- `c=heaven&m=index|get_mopup_price|mop_up|pk|get_report_list|get_report_info|buy|reward_info|get_reward|send_to_chat|get_price`: Tower of Heaven (legacy port, lord level 160+): 200 floors from `HeavenMission` with defenders from `HeavenMonster` and legacy battle reports, first-kill rewards and strategy reports, 10 daily free attempts plus 20-gold purchases, 5 / 10 / 50 / 100 sweeps, and the daily reward with 2 / 4 / 8× gold tiers. Refine / colour / blood stones and equipment essences are computed and reported but not granted yet (legacy storage not ported)
- `c=worldboss&m=index|powerup|battle|reward`: World boss Xingtian (legacy port, lord level 46+): two sessions a day, 12:00–12:15 and 20:00–20:15, one per player per day; the boss is `WorldBoss` row 1, its health bar drains with the remaining time, and fights use legacy `battle_worldboss` (with battle reports); 60 s revive cooldown (10 gold to revive now), silver (level × 500) / gold (10) cheers of +10% each up to 200%; after the session, arena points by personal total-damage rank (`WorldBossIntreward`, last hit × 1.5). State lives in Redis (legacy key names, atomic accumulation); unclaimed rewards are granted and all keys cleared by the daily 00:30 job (`Hanjiang\Cron\WorldBossCron`, legacy `cron_acttask::worldboss`). `openboss` / `openbossreward` in `c=member&m=index` follow the legacy game
- `c=siege&m=index|capital_info|occupy|battle_prepare|join_battle|action|get_reward|get_report|siege_notice`: Country war over capitals (legacy port; a country is a player guild): the 13 capitals of `CountryCityEffect` open with the server's farthest campaign progress; a guild's king or top-3 officials claim one capital before Friday 18:00 (the king may pay 1000 gold for a "supreme siege" duel against the holder's king, VIP 7+), members sign up on Saturday in the hour before the capital's start time, and `Hanjiang\Cron\SiegeCron` resolves each contested capital with the legacy 3-lane team battle (`BattleRush`, player or `CountryMission` NPC armies) 3 minutes before it starts; players replay the battle report, claim country-level rewards (×3 for the winner) and the holder guild's members enjoy the capital effect (levy / tower / sanctum / heaven bonuses). Participation flags are cleared every Saturday 00:00. `country_war` and related fields in `c=member&m=index` follow the legacy game
- `c=gvg&m=index|betting|sync_info`: Guild vs Guild (GVG) cross-server tournament brackets, championship betting, and victory dividends
- `c=general_inherit&m=index|preview|go_inherit`: Martial inheritance, experience & cultivate stat transfers (Normal 80% & Flawless 100%)
- `c=blackmarket&m=index|buy|lock|refresh`: Western exotic black market, rare artifact locks, and shelf refreshes
- `c=first_recharge&m=index|get_reward|lottery|action`: First-recharge rebate tiers and the Fate Wheel, ported from the legacy `first_recharge.php` with `FirstRecharge` / `FirstRechargeLottery` data; state lives in `first_recharge_log` and `member_field.first_recharge`
- `c=overseastrade&m=index|goout|trade`: Maritime silk road, fleet sailings, exotic spices, and merchant profit claims
- `c=studfarm&m=index|action`: Bole stud farm, legendary steed appraisals, bloodline enhancements, and mount acquisitions
- `c=jadegamble&m=index|mining|gambling|harvest`: Rough jade ore mining, high-stakes stone cutting, and imperial emerald harvests
- `c=treasuremap&m=index|refresh|sale`: Tomb raiding compass geomancy, star-tier treasure maps, and royal mausoleum excavation
- `c=workshop&m=index|get_reward|levelup`: Official siege weaponry workshop, arrow manufacturing, and military tribute yields
- `c=sanctum&m=index|select_map|get_mission_info|action|buy_times|get_mission_report_list|get_report_info|send_to_chat|get_reward`: Four Symbols Sanctum (legacy port, lord level 140+): 5 sanctums × 5 floors × 10 missions from `SanctumMission` with defenders from `SanctumMonster` and legacy battle reports, first-clear strategy reports, 10 daily attempts plus 20-gold purchases, gold-priced sweeps, and the daily reward with `SanctumReward` multipliers. Gem chips and gem drops are computed and reported but not granted yet (legacy storage not ported)
- `c=compete&m=index|get_reward_list|get_reward|buy_pk_num|action|send_to_chat`: Contest of Heroes (legacy port): a time-limited player ladder (activity `compete` in `ActivityTime`, which ended in 2016, so every call reports "not open" like the legacy server); players join at lord level 26 as last rank + 1 with 100 challenges, beat higher-ranked players to swap ranks (legacy battle report, mail to the loser), buy extra challenges for 2 gold, and claim `CompeteReward` / `NewcompeteReward` rank rewards (top 200) after challenges close; unclaimed rewards are granted by `Hanjiang\Cron\CompeteCron` within 10 hours after the activity ends. Mounts and jade in the rewards are reported but not granted yet (legacy storage not ported)
- `c=countrysacrifice&m=index|action`: National ancestral altar sacrifices, nationwide buffs, and reputation blessings
- `c=expostulation&m=index|adopt`: Court minister policy expostulations, imperial adoptions, and governance merits
- `c=banquet&m=index|create_team|action`: Heroic nocturnal banquets, feast gatherings, stamina refills, and banquet announcements
- `c=drink&m=index|go_drink`: Green plum wine hero deliberations, Dukang wine toasts, and soul fortunes
- `c=dice&m=index|shake_dice`: Fortunate god dice shaking, triple leopard jackpots, and silver/gold rewards
- `c=sacredtree&m=index|watering`: Money-growing sacred tree cultivation, canopy watering, and gold/silver harvests
- `c=flop&m=index|turnover`: Nine-grid card flips, card turnovers, and golden jackpot awards
- `c=challenge&m=action`: Sparring (legacy port): a free PK against any player with the arena battle engine (`battle_pk`), returns the legacy battle report and mails the opponent
- `c=elite&m=index|get_member_info|join_team|get_country_info|get_server|action|buy_number|sendinvite`: Elite team campaigns (legacy port): 83 legions from `EliteCity` (open once the player has cleared the legion's last main-campaign mission), defenders from `EliteMission` → `EliteMonster` fought as a legacy 3-lane team battle (`BattleRush`, legacy `battle_rush`), first-kill / repeat experience and item rewards, VIP 8 "supreme team" bonus for gold, 10 daily attempts plus gold-bought extras (`Price.elite`), open 12:00–14:00 and 19:00–21:00. In the legacy game `index` / `get_member_info` / `join_team` / `get_country_info` / `action` are called by a separate real-time team server that is not in the legacy package; that server is rebuilt as `EliteRoom` on the WebSocket gateway (below), and `action` is internal-only: the HTTP route returns `{status: 0, info: []}` and battles start only from the WebSocket `startfight`
- Elite real-time teams (WebSocket, `Hanjiang\WebSocket\EliteRoom`): the legacy client `elitepop.js` connects through the `socket.io.min.js` adapter (frames `{action, data}` up, `{type, data}` down, an app-level `ping` every 20 s) to the address from `c=elite&m=get_server` (`socket_server_url`; empty → `HJ_CONFIG.wsUrl`). Events: `login` (same Redis token as HTTP; `loginstatus` 1 / 0 / -1 / -2 / -3), `eliteindex`, `teamlist` (pushed only to players viewing that legion), `createTeam`, `jointeam` (max 3, country teams same-country only), `exitteam`, `fireteam`, `firemember`, `changeorder`, `teaminvited`, `startfight` (`readyfight` then the `EliteHandler::fight` result as `enterfight` to every member). Team state lives in Redis under one global lock, so several WebSocket workers can share it; a closed or idle connection leaves its team (a leader leaving dissolves it). See `docs/LEGACY_GAP.md` for the parts the client does not reveal
- `c=muster&m=index|get_general_info|go_battle|go_rest|sale_general`: Muster platform; `index` / `get_general_info` / `go_battle` / `go_rest` ported from the legacy `muster.php` (dispatch roster `general.goout`, at most 8 heroes; resting removes the hero from every formation, stops training on `confrim`, unequips all items and mounts, keeps at least one hero dispatched); `sale_general` is still the new-version severance
- `c=business&m=index|go_business|buy|refresh`: Four Seas commerce, merchant fleet caravans (Luoyang, Chengdu, Jianye), and trade profits
- `c=gambling&m=index|buy|cut|sell`: Treasure bowl gambling, rough stone cuts, and pawnshop silver liquidations
- `c=task&m=index|accept_task|get_reward|refresh`: Daily military bounty boards, 5-star quest refreshes, and reward claims
- `c=talent&m=index|levelup`: Warlord talent awakening (Fierce Attack, Iron Wall, Bloodthirst, Critical Hit) and stat surges
- `c=advanced&m=index|start_advanced`: Warlord quality tier ascension (Green -> Blue -> Purple -> Orange -> Dark Gold Divine)
- `c=refine&m=index|refinning`: Blacksmith gear refinement affixes (Armor Piercing, Tenacity, Critical Strike)
- `c=star&m=index|level_up`: Seven-star constellation warlord breakthrough and attribute surges
- `c=war_college&m=index|level_up`: National War College branch tech research (Infantry, Cavalry, Archers, Strategists)
- `c=lottery&m=index|action`: Celestial fortune lottery wheel, sector spins, and jackpot draws
- `c=generaltask&m=index|action|get_report`: Warlord expeditions, scout patrols, and exploration spoils
- `c=general_book&m=index|levelup`: Heroes of the Three Kingdoms chronicle, bond activation, and army stat boosts
- `c=copies&m=index|get_monster_list|get_monster_info|pk|get_mopup_price|mop_up|buy_times|get_report_list|get_report_info|send_to_chat` (client sends `d=newequip`): Pacification copies (legacy port, lord level 110+): 3 copies × 3 difficulties × 10 monsters from `CopiesInfo` (defenders: the monster row's position1~9 → `CopiesInfo` rows) with legacy battle reports, first-kill / drop rewards from `CopiesReward` (reputation, equipment, forge materials from `MaterialsTemp` stored in `own_materials`), first-kill strategy reports, 5 free attempts per monster per day plus VIP-limited gold purchases (`CopiesVipBuyTimes` / `CopiesBuyTimesGold`), and 1 / 5 / 10 sweeps
- `c=island&m=index|get_mission|pk|get_report_list|get_report_info|send_to_chat`: Gold & Silver Island (legacy port, lord level 36+): 5 guardians per 10-level bracket from `Island` with defenders from `IslandMonster` and legacy battle reports, command cost rising with each daily win, guaranteed silver plus a gold drop chance, and first-win strategy reports; progress returns to the bracket start each day
- `c=herothrone&m=index|go_back|start|action|buy_times|end_battle`: Hero Throne (legacy port, lord level 101+): a 300-floor run from `HerothroneMission` with defenders from `HerothroneMonster` and legacy battle reports, floor rewards and chests every 10 floors, 2 free runs a day plus VIP-limited 1000-gold purchases, and VIP 10+ gold continues after a loss. The cross-server ranking (uc server) is not ported, so the ranking list is empty
- `c=beatengod&m=index|beaten|get_box_reward`: Golden martial god body beatings, divine crystal drops, and trials chests
- `c=war&m=index|entry|point_shop`: Three Kingdoms Imperial Capital Cross-Server Holy War, enlistments, and military merit shop
- `c=countrymine&m=index|get_countrymine_info|caikuang|rob|get_reward|give_up|send_to_chat`: Country jade mines (legacy port, lord level 91+ and in a guild): 10 pages of mines typed by `CountryMineRatio`, 3 occupations and 3 robberies a day, 15-minute occupations, robbing miners of other guilds with a legacy PvP battle report (mail plus a guild fight-notice entry), and random `CountryMineEvent` events (purple gems, gold, jade multipliers). Jade is computed and reported but not granted yet (`member.jade` not ported)
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
- `c=information&m=index|get_info|get_own_achievement`: Lord combat rating overview, warlord statistics, and achievement badges
- `c=invitation&m=index|change|get_reward`: Brotherhood recruitment codes, recruit dividend ingots, and referral milestone gifts

---

## 🧪 Automated Testing

All core API endpoints and combat simulation loops are covered by PHPUnit integration tests (`tests/Feature`); legacy formulas in `LegacyStats` and team building in `TeamBuilder` are checked against real legacy database rows (`tests/Unit`):

```bash
./vendor/bin/phpunit                      # all suites
./vendor/bin/phpunit --testsuite Unit     # formulas and team building (TeamBuilder needs MySQL, no Redis)
node tests/js/socket_io_adapter.test.js   # socket.io.min.js adapter (frame shape, heartbeat, address)
```

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
- [x] Tavern warlord recruitment (`tavern`) with persistent silver deductions & anti-duplicate checks
- [x] Martial practice grounds (`practice`) with idle time exp accumulation & rapid leap leveling
- [x] Full combat integration linking dynamic hero stats and equipment bonuses into 9-grid simulation
- [x] Legacy formation storage (`matrix`): per-formation rows, active formation switching, anti-duplicate validation, and gold upgrades; battles use the active formation
- [x] Multi-hero cooperative combat simulation with live battle report replay and positioning strategy
- [x] Military Arena (`arena`) ported from legacy: player rank challenges and swaps, legacy battle reports, daily rank points and the points shop
- [x] Main city infrastructure & imposition/levy system (`city`) with critical harvest multipliers and gold forced taxation
- [x] Native WebSocket full-duplex gateway with live world chat, player authentication, and server-wide marquee broadcast
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
- [x] Player-founded nation (guild) system (`country`), soul fate constellation empowerments (`soul`), and warlord marrow cleansing cultivation (`cultivate`)
- [x] Full alignment with live client daily loops: Continuous Check-in (`dailysign`), VIP tiers & gifts (`vip`), in-game mailbox (`message`), social friends (`friend`), and silver mine territory conquests (`mine`)
- [x] Hall-of-Fame end-game systems: Bronze Sparrow beauties (`beauty`), legendary steeds (`mount`), divine gear gem socketing (`gem`), the Tower of Heaven (`heaven`), and world boss Xingtian (`worldboss`)
- [x] Country war over capitals (`siege`, legacy port), GVG cross-server championship tournament betting (`gvg`)
- [x] Built-in golden flowing marquee banner UI renderer in `ws_client.js` with hardware-accelerated broadcast animations
- [x] Martial inheritance system (`general_inherit`), Western black market (`blackmarket`), first-recharge rebate & Fate Wheel (`first_recharge`), and maritime silk road (`overseastrade`)
- [x] High-tier diversion mechanics: Bole stud farm (`studfarm`), rough jade gambling (`jadegamble`), tomb raiding maps (`treasuremap`), and weaponry workshop (`workshop`)
- [x] Four Symbols Sanctum (`sanctum`), the Contest of Heroes ladder (`compete`, legacy port), national altar sacrifices (`countrysacrifice`), and imperial court expostulations (`expostulation`)
- [x] Leisure & social festivity systems: Heroic banquets (`banquet`), plum wine deliberations (`drink`), celestial dice shaking (`dice`), and money sacred tree (`sacredtree`)
- [x] High-tier martial arenas & recruitment outlets: Card turnover prizes (`flop`), player sparring (`challenge`, legacy port), and muster severance management (`muster`)
- [x] Merchant economy & talent mastery: Four Seas commerce (`business`), treasure bowl gambling (`gambling`), daily military bounties (`task`), and talent tree awakenings (`talent`)
- [x] Pinnacle tier ascensions: Quality ascension (`advanced`), gear affix refinement (`refine`), seven-star breakthrough (`star`), and War College technologies (`war_college`)
- [x] Chronicles & campaign expeditions: Fortune lottery (`lottery`), warlord expeditions (`generaltask`), and heroes chronicle bonds (`general_book`)
- [x] Epic holy wars & overseas frontiers: Gold & Silver Island (`island`), Hero Throne (`herothrone`), martial god golden body trials (`beatengod`), and Imperial Capital Holy War (`war`)
- [x] Protocol & media fidelity: HTTP 206 Partial Content byte-range media streaming, Web Audio capture unlock, classic hero asset binding (`HERO_CONFIGS`), levelup exp bar normalization, and defeat zero-yield rule

- [x] Border battlegrounds & growth milestones: country jade mines (`countrymine`, legacy port), country boss Chiyou (`countryboss`), golden egg smashings (`hitegg`), and level-up milestone packages (`levelgift`)
- [x] National trade & celestial forges: National prestige stores (`countryshop`), gear fabrications (`equip_forge`), continuous login festivals (`totallogin`), and the World Epics puzzle (`epic`, legacy port)
- [x] Operational giftings & achievement telemetry: Universal CDKeys (`activationcode`), military drills (`exercise`), lord combat overview (`information`), and brotherhood recruitment (`invitation`)
- [x] Lightweight domain thin models (`src/Model/`) with native `SoftDeletes`, automatic type casting, and encapsulated domain methods (`Member`, `General`, `Equipment`, `GeneralSoul`)
- [x] High-concurrency debouncing & anti-cheat: Atomic request reentrancy protection via `ThrottleMiddleware`
- [x] PHPUnit Unit + Feature suites (123 tests, 6837 assertions)

---

## 📄 License

This project is licensed under the [MIT License](LICENSE).
