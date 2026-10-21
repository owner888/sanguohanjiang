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
│   │   ├── Hero.php         # Combat entity model
│   │   └── Position.php     # 9-grid tactical positions (1-9) enum
│   ├── Middleware/          # Middleware pipeline (aligned with smart-book)
│   │   ├── MiddlewareInterface.php
│   │   ├── CorsMiddleware.php             # Cross-origin and OPTIONS preflight
│   │   ├── GameProtocolMiddleware.php     # Dispatches c=xxx&m=yyy to Handler actions
│   │   └── ResponseEnvelopeMiddleware.php # Standardizes JSON response envelopes
│   ├── Handler/             # Stateless action handlers (no class state leaks)
│   │   ├── IosHandler.php        # Client bootstrap & version checks
│   │   ├── UserHandler.php       # User authentication & token exchange
│   │   ├── RegisterHandler.php   # Guest & account registration
│   │   └── MemberHandler.php     # Player profile, stamina, and city data
│   └── Http/                # HTTP Gateway & Router
│       ├── Router.php       # Pipeline runner & static asset server
│       └── Response.php     # Protocol response helper
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

3. Start server:
   ```bash
   # Debug mode (foreground)
   php server.php start

   # Daemon mode (background)
   php server.php start -d
   ```

4. Play the game:
   Open your browser and navigate to:
   ```
   http://127.0.0.1:18100/
   ```

---

## 🎮 Supported Protocol & APIs

The HTTP gateway seamlessly bridges original client requests mapped through `?c={controller}&m={method}`:

- `c=ios&m=update_world_common`: Client environment handshake & resource validation
- `c=register&m=guest`: Instant trial / guest account initialization
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
- `c=map&m=action`: Battle trigger & in-memory deterministic 9-grid combat report generation
- `c=general&m=index`: Player hero inventory, equipment, and attribute calculations
- `c=matrix&m=index`: 9-grid tactical battle formation & hero slot updates
- `c=pack&m=index`: Bag items, consumables, and gear management
- `c=strengthen&m=index`: Equipment blacksmith enhancement & rate checking
- `c=tavern&m=index`: General recruitment tavern & guest trade
- `c=practice&m=index`: Martial training grounds, slots, and rapid exp leaps
- `c=chat&m=read|send`: World and country real-time messaging
- `c=mainquest&m=index`: Main storyline quest milestones and rewards
- `c=shop&m=index`: Item mall & stamina purchasing
- `c=arena&m=index`: Military ranking arena & PVP challenges
- `c=tower&m=index|action|sweep`: Trial Soul Tower climbing, boss encounters, floor sweeps, and soul rewards
- `c=pass_general&m=index|action|reset`: Six-Stage Pass General campaign, legendary warlord duels, and milestone chests
- `c=soul&m=index|upgrade`: Soul fate constellation slots, soul point upgrades, and permanent hero battle stat empowerment
- `c=country&m=index|select_country|donate|salary`: Three Kingdoms nations (Wei, Shu, Wu), allegiance selection, military donations, and daily salaries
- `c=cultivate&m=index|roll|save`: Warlord marrow cultivation & attribute cleansing (Strength, Intellect, Vitality)
- `c=dailysign&m=index|sign`: 30-Day monthly continuous check-in, tiered silver/gold rewards, and anti-duplicate validations
- `c=vip&m=index|get_vip_reward|get_gift`: VIP tier privileges, daily VIP salaries, and promotion milestone gift packages
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
- `c=first_recharge&m=index|get_reward`: First-recharge celebration, god-tier Zhao Yun, silver spear, and VIP perks
- `c=overseastrade&m=index|goout|trade`: Maritime silk road, fleet sailings, exotic spices, and merchant profit claims
- `c=studfarm&m=index|action`: Bole stud farm, legendary steed appraisals, bloodline enhancements, and mount acquisitions
- `c=jadegamble&m=index|mining|gambling|harvest`: Rough jade ore mining, high-stakes stone cutting, and imperial emerald harvests
- `c=treasuremap&m=index|refresh|sale`: Tomb raiding compass geomancy, star-tier treasure maps, and royal mausoleum excavation
- `c=workshop&m=index|get_reward|levelup`: Official siege weaponry workshop, arrow manufacturing, and military tribute yields

---

## 🧪 Automated Testing

All core API endpoints and combat simulation loops are covered by PHPUnit integration tests:

```bash
./vendor/bin/phpunit
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
- [x] Blacksmith enhancement system (`strengthen`) with silver cost scaling & success rate
- [x] Tavern warlord recruitment (`tavern`) with persistent silver deductions & anti-duplicate checks
- [x] Martial practice grounds (`practice`) with idle time exp accumulation & rapid leap leveling
- [x] Full combat integration linking dynamic hero stats and equipment bonuses into 9-grid simulation
- [x] Tactical 9-grid formation lineup management (`matrix`), hero slot changes, anti-duplicate validation, and matrix upgrades
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
- [x] Three Kingdoms nation faction system (`country`), soul fate constellation empowerments (`soul`), and warlord marrow cleansing cultivation (`cultivate`)
- [x] Full alignment with live client daily loops: Continuous Check-in (`dailysign`), VIP tiers & gifts (`vip`), in-game mailbox (`message`), social friends (`friend`), and silver mine territory conquests (`mine`)
- [x] Hall-of-Fame end-game systems: Bronze Sparrow beauties (`beauty`), legendary steeds (`mount`), divine gear gem socketing (`gem`), celestial stargazing (`heaven`), and server-wide Dong Zhuo World Boss crusade (`worldboss`)
- [x] Legion territorial siege warfare (`siege`), GVG cross-server championship tournament betting (`gvg`), and 16-chapter campaign static master repository (`GameMasterData`)
- [x] Built-in golden flowing marquee banner UI renderer in `ws_client.js` with hardware-accelerated broadcast animations
- [x] Martial inheritance system (`general_inherit`), Western black market (`blackmarket`), first-recharge celebration (`first_recharge`), and maritime silk road (`overseastrade`)
- [x] High-tier diversion mechanics: Bole stud farm (`studfarm`), rough jade gambling (`jadegamble`), tomb raiding maps (`treasuremap`), and weaponry workshop (`workshop`)
- [x] PHPUnit end-to-end integration test suite (49/49 100% passing, 735 assertions)

---

## 📄 License

This project is licensed under the [MIT License](LICENSE).
