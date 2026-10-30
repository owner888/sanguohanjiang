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
│   ├── Battle/              # Deterministic 9-grid combat engine
│   │   ├── BattleEngine.php # Core simulation logic (ported from battle_model.php)
│   │   ├── Hero.php         # Combat unit (legacy fields + temporary adapter for the current engine)
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
│   └── Http/                # HTTP Gateway & Router
│       ├── Router.php       # Pipeline runner & static asset server
│       └── Response.php     # Protocol response helper
├── tools/
│   └── gen_master_data.php  # Regenerates src/MasterData from the legacy mysqldump
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
   Upgrading an existing database instead: apply the files in `database/migrations/` that are newer than your schema, in filename order (each file header says what it does). `20261031_general_equip_legacy_cols.sql` assumes general/equipment rows are test data: it truncates both tables and re-issues every player the legacy starting general. `20261031_matrix_legacy.sql` converts the one-row-per-player `matrix` table to legacy per-formation rows, fills `member_field.matrixid` from the old row, rebuilds `general.goout` from the old `pos` / formation slots, then drops `general.pos` / `general.matrix_id`.

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
- `c=map&m=action`: Battle trigger & in-memory deterministic 9-grid combat report generation (player team from the legacy formation / equipment formulas, defenders from the legacy `Mission` → `Monster` tables); on victory the legacy `Mission` probabilistic drop (`getrate` / `gettype` / `getvalue`) is rolled and granted
- `c=map&m=get_newreward_index|get_newreward|get_newresult`: Campaign node milestone chests & chapter clear grand reward claims
- `c=general&m=index|get_info|equip|unequip`: Hero panel ported from the legacy `general.php` (deployed heroes only, legacy `info_field`, equipment display power with refine / enhancement-achievement bonuses, legacy HP formula, legacy equip validations and status codes)
- `c=matrix&m=index|get_info|update_matrix|use_matrix|levelup`: Formations ported from the legacy `matrix.php` (one row per owned formation, active formation in `member_field.matrixid`, only dispatched heroes can be placed, open slots / bonus / gold upgrade cost from MasterData `Matrix` / `MatrixLevel`)
- `c=pack&m=index|sale`: Bag ported from the legacy `pack.php` (unequipped items only, chip / scroll pricing, VIP 11 refund, daily sale limit for items enhanced to level 10+)
- `c=strengthen&m=index|strengthen_info|strengthen_start|clear_cd`: Blacksmith ported from the legacy `strengthen.php` (`EquipLevel` cost and success rate, VIP 5 critical +2 levels, writes level / powerup / sale price; legacy servers never applied an enhancement cooldown)
- `c=tavern&m=index`: General recruitment tavern & guest trade
- `c=practice&m=index|practice_start|practice_stop|go_leap|mop|get_levelup_price|levelup|get_unlock_price|unlock|turn`: Martial training grounds, rapid leaps & sweeps, 8-slot unlocks & reincarnation
- `c=activity&m=index`: Daily special activities overview, schedule windows, and sign-in/boss popo notifications
- `c=chat&m=read|send`: World and country real-time messaging
- `c=mainquest&m=index`: Main storyline quest milestones and rewards
- `c=shop&m=index`: Item mall (legacy `Shop` table: army tallies, equip 7, 9–15, 53–56) & stamina purchasing
- `c=arena&m=index|action|buy_pk_num|get_reward|clear_cd`: Military ranking arena & PVP duels, rank swaps, challenge purchases, and daily salaries
- `c=tower&m=index|action|sweep`: Trial Soul Tower climbing, boss encounters, floor sweeps, and soul rewards
- `c=pass_general&m=index|action|reset`: Six-Stage Pass General campaign, legendary warlord duels, and milestone chests
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
- `c=heaven&m=index|pk|mop_up`: Constellation stargazing, celestial guardian boss battles, and stargazing sweeps
- `c=worldboss&m=index|powerup|battle|reward`: Server-wide Dong Zhuo World Boss crusade, morale cheers, DPS rankings, and spoils chests
- `c=siege&m=index|battle_prepare|action|get_reward`: City siege warfare, territory conquest (Luoyang, Chang'an, Puyang), and daily fief tributes
- `c=gvg&m=index|betting|sync_info`: Guild vs Guild (GVG) cross-server tournament brackets, championship betting, and victory dividends
- `c=general_inherit&m=index|preview|go_inherit`: Martial inheritance, experience & cultivate stat transfers (Normal 80% & Flawless 100%)
- `c=blackmarket&m=index|buy|lock|refresh`: Western exotic black market, rare artifact locks, and shelf refreshes
- `c=first_recharge&m=index|get_reward|lottery|action`: First-recharge rebate tiers and the Fate Wheel, ported from the legacy `first_recharge.php` with `FirstRecharge` / `FirstRechargeLottery` data; state lives in `first_recharge_log` and `member_field.first_recharge`
- `c=overseastrade&m=index|goout|trade`: Maritime silk road, fleet sailings, exotic spices, and merchant profit claims
- `c=studfarm&m=index|action`: Bole stud farm, legendary steed appraisals, bloodline enhancements, and mount acquisitions
- `c=jadegamble&m=index|mining|gambling|harvest`: Rough jade ore mining, high-stakes stone cutting, and imperial emerald harvests
- `c=treasuremap&m=index|refresh|sale`: Tomb raiding compass geomancy, star-tier treasure maps, and royal mausoleum excavation
- `c=workshop&m=index|get_reward|levelup`: Official siege weaponry workshop, arrow manufacturing, and military tribute yields
- `c=sanctum&m=index|action|get_reward`: Four Divine Beast Sanctums (Azure Dragon, White Tiger, Vermilion Bird, Black Tortoise)
- `c=compete&m=index|action|get_reward`: Cross-server martial tournaments, tier promotions, and daily honor rewards
- `c=countrysacrifice&m=index|action`: National ancestral altar sacrifices, nationwide buffs, and reputation blessings
- `c=expostulation&m=index|adopt`: Court minister policy expostulations, imperial adoptions, and governance merits
- `c=banquet&m=index|create_team|action`: Heroic nocturnal banquets, feast gatherings, stamina refills, and banquet announcements
- `c=drink&m=index|go_drink`: Green plum wine hero deliberations, Dukang wine toasts, and soul fortunes
- `c=dice&m=index|shake_dice`: Fortunate god dice shaking, triple leopard jackpots, and silver/gold rewards
- `c=sacredtree&m=index|watering`: Money-growing sacred tree cultivation, canopy watering, and gold/silver harvests
- `c=flop&m=index|turnover`: Nine-grid card flips, card turnovers, and golden jackpot awards
- `c=challenge&m=index|action`: Hero trial arenas, master duels (Ma Chao, Zhao Yun, Guan Yu), and arena honors
- `c=elite&m=index|action|get_server`: Multi-hero elite campaigns, native WebSocket real-time teaming, and battle spoils
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
- `c=copies&m=index|pk|mop_up`: Multi-hero campaign copies (Ten Attendants, Dong Zhuo, Zhang Kai), boss duels, and mop sweeps
- `c=island&m=index|pk`: Penglai, Yingzhou, and Fangzhang treasure islands, beast conquests, and overseas spoils
- `c=herothrone&m=index|action`: Iron, Silver, and Imperial Hero Throne dominance tournaments
- `c=beatengod&m=index|beaten|get_box_reward`: Golden martial god body beatings, divine crystal drops, and trials chests
- `c=war&m=index|entry|point_shop`: Three Kingdoms Imperial Capital Cross-Server Holy War, enlistments, and military merit shop
- `c=countrymine&m=index|caikuang|rob|get_reward|give_up`: Nation border mines (Guan Du, Jian Ge, Chi Bi), convoy plunders, and military revenue harvests
- `c=countryboss&m=index|powerup|battle|reward`: National divine guardian beast altars (Golden Dragon, Kirin, Fire Phoenix), morale surges, and boss trials
- `c=hitegg&m=index|hit_egg`: Golden fortune egg smashings, hammer strikes, and silver/gold crit explosions
- `c=levelgift&m=index|get_reward`: Lord level-up milestone packages (Levels 5, 10, 20, 30, 40, 50) and growth funds
- `c=countryshop&m=index|buy`: National prestige tax shops, red artifact blueprints, and military provisions
- `c=country_taxes_shop&m=index|buy`: National taxes mall, treasury asset claims, and daily quota exchanges
- `c=equip_forge&m=index|fabrication|resolve`: Celestial gear forges, artifact fabrications, and gear scrap smelting
- `c=totallogin&m=index|action`: Continuous login festival milestones (Days 1 to 14) and carnival silver/gold bundles
- `c=epic&m=index|force`: Historical epic campaigns (Baima, Wuchao, Huarong Road) and heroic warlord duels
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
- [x] Military Arena PVP system (`arena`) with rank swapping, daily俸禄 salary claims, and deterministic mirror battle simulation
- [x] Main city infrastructure & imposition/levy system (`city`) with critical harvest multipliers and gold forced taxation
- [x] Native WebSocket full-duplex gateway with live world chat, player authentication, and server-wide marquee broadcast
- [x] Pure RFC 6455 opcode 0x9/0xA protocol-level ping/pong heartbeat standard aligned with `smart-book` (zero application payload overhead)
- [x] Directly consume native `OmniPHP\Http\Context` (modeled after Go Gin) across all Handlers and Middlewares without redundant subclassing
- [x] Directly consume native `OmniPHP\WebSocket\Context` and `Broadcaster` for full-duplex events, sessions, and headless unit testing
- [x] Eliminate redundant `src/Database/` wrappers; directly consume native `OmniPHP\Database\DB` and `OmniPHP\Cache\Redis`
- [x] Integrated `OmniPHP\ArchitectureGuard` to enforce clean architecture and prevent custom infrastructure re-wrapping
- [x] Client network layer with exponential backoff auto-reconnect, jitter, and zombie connection sweeping (`sweep`)
- [x] Complete port of legacy core modules: Trial Soul Tower (`tower`), Six-Stage Pass General campaign (`pass_general`), and dynamic storyline quests (`mainquest`)
- [x] Player-founded nation (guild) system (`country`), soul fate constellation empowerments (`soul`), and warlord marrow cleansing cultivation (`cultivate`)
- [x] Full alignment with live client daily loops: Continuous Check-in (`dailysign`), VIP tiers & gifts (`vip`), in-game mailbox (`message`), social friends (`friend`), and silver mine territory conquests (`mine`)
- [x] Hall-of-Fame end-game systems: Bronze Sparrow beauties (`beauty`), legendary steeds (`mount`), divine gear gem socketing (`gem`), celestial stargazing (`heaven`), and server-wide Dong Zhuo World Boss crusade (`worldboss`)
- [x] Legion territorial siege warfare (`siege`), GVG cross-server championship tournament betting (`gvg`)
- [x] Built-in golden flowing marquee banner UI renderer in `ws_client.js` with hardware-accelerated broadcast animations
- [x] Martial inheritance system (`general_inherit`), Western black market (`blackmarket`), first-recharge rebate & Fate Wheel (`first_recharge`), and maritime silk road (`overseastrade`)
- [x] High-tier diversion mechanics: Bole stud farm (`studfarm`), rough jade gambling (`jadegamble`), tomb raiding maps (`treasuremap`), and weaponry workshop (`workshop`)
- [x] Four Divine Beast Sanctums (`sanctum`), cross-server martial tournaments (`compete`), national altar sacrifices (`countrysacrifice`), and imperial court expostulations (`expostulation`)
- [x] Leisure & social festivity systems: Heroic banquets (`banquet`), plum wine deliberations (`drink`), celestial dice shaking (`dice`), and money sacred tree (`sacredtree`)
- [x] High-tier martial arenas & recruitment outlets: Card turnover prizes (`flop`), hero challenge arenas (`challenge`), elite campaigns (`elite`), and muster severance management (`muster`)
- [x] Merchant economy & talent mastery: Four Seas commerce (`business`), treasure bowl gambling (`gambling`), daily military bounties (`task`), and talent tree awakenings (`talent`)
- [x] Pinnacle tier ascensions: Quality ascension (`advanced`), gear affix refinement (`refine`), seven-star breakthrough (`star`), and War College technologies (`war_college`)
- [x] Chronicles & campaign expeditions: Fortune lottery (`lottery`), warlord expeditions (`generaltask`), heroes chronicle bonds (`general_book`), and campaign pacifications (`copies`)
- [x] Epic holy wars & overseas frontiers: Gold & Silver treasure islands (`island`), Hero Throne dominance (`herothrone`), martial god golden body trials (`beatengod`), and Imperial Capital Holy War (`war`)
- [x] Protocol & media fidelity: HTTP 206 Partial Content byte-range media streaming, Web Audio capture unlock, classic hero asset binding (`HERO_CONFIGS`), levelup exp bar normalization, and defeat zero-yield rule

- [x] Border battlegrounds & growth milestones: Nation border mines (`countrymine`), national beast altars (`countryboss`), golden egg smashings (`hitegg`), and level-up milestone packages (`levelgift`)
- [x] National trade & celestial forges: National prestige stores (`countryshop`), gear fabrications (`equip_forge`), continuous login festivals (`totallogin`), and historical epics (`epic`)
- [x] Operational giftings & achievement telemetry: Universal CDKeys (`activationcode`), military drills (`exercise`), lord combat overview (`information`), and brotherhood recruitment (`invitation`)
- [x] Lightweight domain thin models (`src/Model/`) with native `SoftDeletes`, automatic type casting, and encapsulated domain methods (`Member`, `General`, `Equipment`, `GeneralSoul`, `SiegeCity`)
- [x] High-concurrency debouncing & anti-cheat: Atomic request reentrancy protection via `ThrottleMiddleware`
- [x] PHPUnit Unit + Feature suites (123 tests, 6837 assertions)

---

## 📄 License

This project is licensed under the [MIT License](LICENSE).
