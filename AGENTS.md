# AGENTS.md — sanguohanjiang (悍将三国 Modern Edition)

A high-performance Three Kingdoms tactical card RPG game server reconstructed with modern **PHP 8.4+** and **Workerman 5.x**, aligned with the architectural standards of `smart-book`.

## 1. Core Architectural Laws (Iron Rules)

1. **Handler + Middleware Pattern Over Legacy Controllers**:
   - **Strictly forbidden**: Never use stateful CI-style Controllers with class instance properties (`$this->member`, `$this->data`). In resident-memory Workerman processes, instance properties cause cross-request state pollution and session leaks.
   - **Required**: All HTTP endpoints must be implemented as stateless **Handlers** with static action signatures:
     ```php
     public static function action(Context $ctx): array|Response
     ```
   - Request-scoped data (authenticated player, session token, timing) lives exclusively within `Context $ctx` and is garbage-collected automatically upon request completion.

2. **Middleware Pipeline for Cross-Cutting Concerns**:
   - Cross-cutting logic (CORS, protocol normalization, authentication, rate-limiting, and response envelope formatting) must reside in PSR-15-style Middlewares, not inside business Handlers.
   - Handlers focus purely on deterministic game domain logic.

3. **No Developer Identity in Code or Commits**:
   - The developer is always referred to as "the developer". Never hardcode real usernames, paths, or tenant identities in comments, test fixtures, documentation, or code. Use generic paths (`/home/dev/...`) and anonymous author descriptors.

4. **Bilingual Documentation Sync**:
   - `README.md` (English) and `README.zh-CN.md` (Chinese) are two mirrors of one document. Every user-visible change, architectural update, or feature addition must be applied to both files in the same commit.

5. **PHP 8.3/8.4+ Strict Type Discipline**:
   - Every PHP file starts with `declare(strict_types=1);`.
   - Prefer `readonly class`, `match` expressions, typed class constants, first-class callables, and backed `enum`s.
   - Zero `@` error suppression. Exceptions must be caught and re-thrown or handled cleanly through `ExceptionHandler`.

6. **100% Backwards-Compatible Client Protocol**:
   - The server must seamlessly drive the legacy H5/Cordova client by routing `index.php?c={controller}&m={method}` (both GET and POST) to `App\Handlers\{Controller}Handler::{method}()`.
   - Outgoing response envelopes must strictly preserve the expected legacy structure (`status`, `message`, `member`, `serverlist`, etc.).

---

## 2. Directory Layout & Standards

```
config/                  # Global application & database configurations
public/                  # Static assets & game client (index.html, static/js, css)
src/
├── Battle/              # Pure memory deterministic combat simulator
│   ├── BattleEngine.php # Core simulation ported & typed from battle_model.php
│   ├── Hero.php         # Immutable/state-controlled combat entity
│   └── Position.php     # 9-grid tactical position enum (1-9)
├── Context.php          # Request context (attributes, input, query, headers, auth)
├── Middleware/          # Middleware pipeline
│   ├── MiddlewareInterface.php
│   ├── CorsMiddleware.php
│   ├── GameProtocolMiddleware.php
│   └── ResponseEnvelopeMiddleware.php
├── Handler/             # Stateless action handlers
│   ├── IosHandler.php        # Handshake & asset version verification
│   ├── RegisterHandler.php   # Guest & member registration
│   ├── UserHandler.php       # Authentication & server list selection
│   ├── LoginHandler.php      # Token issuance & session binding
│   ├── MemberHandler.php     # Player profile, stamina, and city building state
│   ├── CityHandler.php       # Main city buildings, levy, and imposition
│   ├── MapHandler.php        # World map, campaign scenes, and battle trigger
│   ├── GeneralHandler.php    # Hero recruitment, inventory, and stats
│   ├── MatrixHandler.php     # 9-grid formation lineup management
│   ├── PackHandler.php       # Bag items, consumables, and gear
│   ├── StrengthenHandler.php # Equipment upgrade & refinement
│   ├── TavernHandler.php     # General recruitment tavern
│   ├── PracticeHandler.php   # Martial training field & leap exp
│   ├── ChatHandler.php       # World/country real-time chat
│   ├── MainquestHandler.php  # Main storyline quests & milestones
│   ├── ShopHandler.php       # Mall, stamina pack purchases
│   └── ArenaHandler.php      # PVP arena & military rankings
├── Database/            # Persistent storage & caching
│   ├── DB.php                # PDO fluid query builder with auto-reconnect
│   └── Redis.php             # High-speed memory caching proxy
├── Enum/                # Strong-typed domain enums
│   ├── Faction.php           # 16 Three Kingdoms historical factions
│   ├── FactionStatus.php     # Locked / Active / Cleared states
│   └── BattleAssess.php      # Combat victory grading (-1, 1, 2, 3)
└── Http/                # Workerman HTTP pipeline & router
    ├── Router.php
    └── Response.php
server.php               # Workerman entrypoint
tests/                   # PHPUnit end-to-end integration test suites
└── Feature/GameApiTest.php
```

---

## 3. Infrastructure & Legacy References

1. **Local Native Services (Never use Docker for DB/Cache)**:
   - **MySQL 8/9**: `127.0.0.1:3306`, user: `root`, password: `root`, database: `zc_sanguo`
   - **Redis**: `127.0.0.1:6379`, no password, prefix: `hj:`
   - Both managed via macOS native services (`brew services list`).

2. **Legacy Codebase Reference**:
   - Location: `../sanguohanjiang1/悍将三国文件/`
   - `HJServer/wwwroot/s1/application/controllers/`: Original PHP 5.x controllers for gameplay logic.
   - `HJServer/wwwroot/s1/application/models/`: Database query & formula references.
   - `HJServer/wwwroot/battle/application/models/battle_model.php`: Reference for 9-grid combat mechanics.

3. **Service Management**:
   ```bash
   php server.php start -d   # Start background daemon
   php server.php restart -d # Hot reload / restart
   php server.php stop       # Stop daemon
   ```

4. **Agent Workflow Baseline**:
   - **Step 1**: Always run `./vendor/bin/phpunit` first. Ensure all existing tests pass in ~40ms before touching code.
   - **Step 2**: Add tests to `tests/Feature/GameApiTest.php` for any new endpoints or bug fixes.
   - **Step 3**: Implement stateless Handlers adhering to strict typing (`declare(strict_types=1)`).
   - **Step 4**: Verify all tests pass in < 50ms, update both `README.md` and `README.zh-CN.md`, and commit.
