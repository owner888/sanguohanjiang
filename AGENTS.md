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
   - Cross-cutting logic (CORS, protocol normalization, authentication, rate-limiting, and response envelope formatting) must reside in Middlewares implementing `OmniPHP\Http\Middleware`, registered globally in `routes.php` via `OmniPHP\Http\Router::middleware()`, not inside business Handlers.
   - Pipeline order (outermost first): `PublicAssets → Cors → ResponseEnvelope → Auth → Throttle → DailyCron → GameProtocol → GameProtocolMiddleware::execute`. `server.php` uses `OmniPHP\Http\Kernel::handle` as `onMessage`; tests call the same Router through `tests/Support/Gateway.php`. Never reintroduce a private router.
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

7. **Database Table Timestamps & Soft Deletes Law (三必备时间字段铁律)**:
   - **All database tables must have three columns**:
     - `created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` (auto-filled by MySQL on INSERT)
     - `updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` (auto-updated by MySQL on UPDATE)
     - `deleted_at DATETIME NULL DEFAULT NULL` (soft delete timestamp, NULL when active)
   - **Strictly forbidden**: Never manually pass `'created_at'` or `'updated_at'` in application insert/update queries. MySQL handles them natively.
   - **Required**: Business queries must filter active records with `->whereNull('deleted_at')`. Deletion must be soft delete: `update(['deleted_at' => date('Y-m-d H:i:s')])`.

8. **Direct Use of OmniPHP Framework Facades (严禁私造基础设施包装)**:
   - Directly consume `OmniPHP\Http\Context`, `OmniPHP\WebSocket\Context`, `OmniPHP\Database\DB`, and `OmniPHP\Cache\Redis`.
   - Never write redundant standalone wrappers (`src/Context.php`, `src/Database/DB.php`). If domain extension is needed, legitimately extend the base class.

9. **Worker Process Isolation & Zero Master fd Sharing Law (长连接子进程 Fork 隔离铁律)**:
   - **Strictly forbidden**: Never open MySQL / Redis connections (`Hanjiang\Support\Connections::init()`, `DatabaseBootstrap::initWorker()`, `DB::init()`, `Redis::init()`) in the Master process global scope before `Worker::runAll()`.
   - **Reason**: `pcntl_fork()` duplicates open socket file descriptors. Sharing an fd across workers leads to concurrent packet collision, `Packets out of order` MySQL crashes, and severe race corruption.
   - **Required**: call `Hanjiang\Support\Connections::init()` (= `DatabaseBootstrap::initWorker()` + PDO options from `config/database.php` + `Redis::init()` with `config/cache.php` `redis`) inside each worker's `$worker->onWorkerStart`. CLI tools, `tests/Support/*_worker.php` and `tests/bootstrap.php` call it once after requiring `bootstrap.php`.
   - **Only exception**: on `start` / `restart`, `server.php` runs `Hanjiang\Support\Migrations::migrateOnStart()` in the master before `Worker::runAll()`; it opens its own MySQL connection, runs pending migrations and always closes it (`DB::close()` in `finally`) before any fork. Nothing else may touch MySQL / Redis in the master.

10. **Fiber Event Loop & Request-Scoped State (Fiber 事件循环与请求级状态铁律)**:
   - Workers run on Workerman's Fiber driver (`Application::workerman()`, Revolt): every connection event and timer callback runs in its own Fiber.
   - **Strictly forbidden**: request-scoped data in static properties. Use `Context $ctx`, or Fiber-local storage `Workerman\Coroutine\Context` when a value must be reachable statically (see `Model\FlowLog::route()`; crons and `EliteRoom` set their own route).
   - **Invariant**: PDO and phpredis are synchronous, so a request never suspends half-way. `Member::locked()` / `DB::transaction()` on the single per-process PDO and the `EliteRoom::$inLock` reentrancy flag rely on this. Introducing anything that suspends a Fiber mid-request (async MySQL / Redis clients, `Timer::sleep`) requires per-request connections and Fiber-local lock flags first. `usleep` waits (Siege / EliteRoom Redis locks) block the whole worker, exactly as before.
   - Process-wide statics that are fine: reflection / handler-map caches (`GameProtocolMiddleware::$actionCache`, `HandlerRegistry`), WebSocket connection registries and timer ids (`ChatPush`, `EliteRoom`, `WebSocketHandler`), test-only clocks (`BossClock`, `EliteRoom::useClock`).

11. **Database Changes Only Through Idempotent Code Migrations (数据库修改必须通过代码 · 迁移幂等铁律)**:
   - **Strictly forbidden**: never change a deployed database by hand (`ALTER TABLE`, adding / dropping columns or tables in a MySQL shell on the server). Never edit `vendor/` to work around the migration runner.
   - **Required**: every schema change is 100% code: a PHP migration in `database/migrations/` (`php tools/make-migration.php <name>`; `return new class extends Migration { up() / down() }`), committed with the code. Restarting `server.php` (`start` / `restart`) runs pending migrations automatically in the master (`Hanjiang\Support\Migrations::migrateOnStart()` → OmniPHP `MigrationRunner`, filename order, recorded in the unprefixed `migrations` table). A failed migration aborts the start.
   - **Idempotent, always**: `database/schema.sql` stays the complete current schema, and on an empty database the first migration (`0000_00_00_000000_import_base_schema`) imports it, so every later migration must be a no-op when its change already exists: guard with `Schema::hasTable()` / `Schema::hasColumn()` / `Schema::hasIndex()` before each step, and make `down()` safe to run twice as well.
   - **Same change in `database/schema.sql`**: write each migration's change into `schema.sql` in the same commit (same DDL text, so fresh and upgraded databases are identical; `tests/Feature/MigrationTest.php` compares a fresh migration run with `schema.sql`). New tables follow Iron Rule 7.
   - Migration code uses full table names (`zc_sanguo_xxx`): the migration connection has no table prefix. Prefer raw DDL copied from `schema.sql` over `Blueprint` (it cannot express table charset / comments).
   - `database/migrations/sql/*.sql` are the old hand-written migrations, kept only for upgrading installs older than the automatic migrations by hand; never add new `.sql` files there.

---

## 2. Directory Layout & Standards

```
bootstrap.php            # Required first by every entry point: OmniPHP\Application::boot() (paths, .env, timezone Asia/Shanghai unless APP_TIMEZONE, LOG_LEVEL, CrashHandler, ArchitectureGuard)
routes.php               # OmniPHP Router: exception handler, global middleware pipeline, catch-all routes
runtime/                 # hanjiang.pid + logs/ (git-ignored)
config/                  # app.php (listen / workers / assets), database.php (MySQL, default => mysql), cache.php (Redis, prefix hj:)
public/                  # Static assets & game client (index.html, static/js, css)
src/
├── Battle/              # Pure memory deterministic combat simulator
│   ├── BattleEngine.php # Core simulation ported & typed from battle_model.php
│   ├── LegacyStats.php  # Legacy stat formulas (general / equipment / hp)
│   ├── TeamBuilder.php  # Legacy team building (init_member/monster_team_info)
│   └── Position.php     # 9-grid tactical position enum (1-9)
├── Middleware/          # Middleware pipeline (implements OmniPHP\Http\Middleware)
│   ├── PublicAssetsMiddleware.php   # public/ files, dynamic config.js, autoload.js no-cache, audio Range
│   ├── CorsMiddleware.php
│   ├── ResponseEnvelopeMiddleware.php
│   ├── AuthMiddleware.php
│   ├── ThrottleMiddleware.php
│   ├── DailyCronMiddleware.php
│   └── GameProtocolMiddleware.php   # c/m → Handler; execute() is the route endpoint
├── Handler/             # Stateless domain action handlers (DDD Layout)
│   ├── Auth/            # Authentication & onboarding (Ios, User, Login, Register)
│   ├── City/            # City infrastructure, levy, VIP & gifts (City, Member, DailySign, Vip)
│   ├── General/         # Heroes, talents, ascension, training & tavern (General, Star, Soul, Tavern)
│   ├── Equip/           # Blacksmith, refinement, gems & forge (Pack, Strengthen, Refine, Gem, Forge)
│   ├── Campaign/        # 9-grid campaign maps, tactics & towers (Map, Matrix, Tower, Copies, Sanctum)
│   ├── Pvp/             # Military rankings, nations & siege wars (Arena, Compete, Country, Siege, War)
│   └── Activity/        # Commerce, gambling, banquets & quests (Chat, Shop, Trade, Dice, Task)
├── Enum/                # Strong-typed domain enums
│   ├── Faction.php           # 16 Three Kingdoms historical factions
│   ├── FactionStatus.php     # Locked / Active / Cleared states
│   └── BattleAssess.php      # Combat victory grading (-1, 1, 2, 3)
├── Http/
│   └── ExceptionHandler.php # extends OmniPHP ExceptionHandler; 500 + {"status":-500,"message":"系统异常: ..."}
└── Support/
    ├── Connections.php  # per-process MySQL / Redis init (Iron Rule 9)
    └── Migrations.php   # migration connection (no table prefix) + MigrationRunner wrapper + migrations table DDL (Iron Rule 11)
database/
├── schema.sql           # Always the complete, current schema (fresh installs import it through the first migration)
└── migrations/          # *.php migrations, run in filename order at server start; sql/ = old hand-written .sql history (not run)
tools/migrate.php        # migrate | status | rollback [N] | reset --force | refresh --force
tools/make-migration.php # Generates an idempotent migration template
server.php               # Workerman entrypoint: Application::workerman('hanjiang') + Kernel::handle
tests/                   # PHPUnit (bootstrap: tests/bootstrap.php)
├── Feature/GameApiTest.php
├── Feature/HttpGatewayTest.php  # HTTP gateway contract via Kernel::handle
└── Support/Gateway.php          # Router::dispatch for Context::mock() requests
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
   php server.php restart -d # Restart (required after changing routes.php or .env; reload does not re-read them)
   php server.php stop       # Stop daemon
   ```
   - Config: env vars > `.env` (project root, git-ignored, loaded by `bootstrap.php`) > defaults in `config/*.php`. Alternate ports: `HTTP_LISTEN`, `WS_LISTEN`, `WS_PUBLIC_PORT`.
   - Logs: `runtime/logs/hanjiang.log` (Workerman master), `runtime/logs/hanjiang_stdout.log` (daemon stdout: access log, PHP warnings / fatals), `runtime/logs/{level}_YYYYMMDD.log` (OmniPHP Logger). Read these first when something breaks.
   - Migrations run automatically on `start` / `restart` (Iron Rule 11); `php tools/migrate.php status` shows what ran. Do not call `DatabaseBootstrap::init()` directly (it ignores our PDO options, mixes prefixed / unprefixed names for the `migrations` table and swallows failures; see `src/Support/Migrations.php`, `docs/TRAPS.md` §9).
   - `revolt/event-loop` is required by the Fiber driver; without it workers crash-loop with `Class "Revolt\EventLoop" not found`.

4. **Agent Workflow Baseline**:
   - **Step 1**: Always run `./vendor/bin/phpunit` first. Ensure all existing tests pass in ~40ms before touching code.
   - **Step 2**: Add tests to `tests/Feature/GameApiTest.php` for any new endpoints or bug fixes.
   - **Step 3**: Implement stateless Handlers adhering to strict typing (`declare(strict_types=1)`).
   - **Step 4**: Verify all tests pass in < 50ms, update both `README.md` and `README.zh-CN.md`, and commit.
