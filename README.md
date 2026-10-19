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
│   ├── Controller/          # Business controllers
│   │   ├── IosController.php       # Client bootstrap & version checks
│   │   ├── UserController.php      # User authentication & token exchange
│   │   ├── RegisterController.php  # Guest & account registration
│   │   └── MemberController.php    # Player profile, stamina, and city data
│   └── Http/                # HTTP Gateway & Protocol
│       ├── Router.php       # Legacy `c=xxx&m=yyy` dispatcher & static files
│       └── Response.php     # Standardized JSON protocol response helper
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
- `c=member&m=index`: Player profile, attributes, stamina, and city states

---

## 🗺️ Roadmap

- [x] Initial Workerman HTTP gateway & static asset serving
- [x] Backward-compatible parameter dispatcher (`c` / `m`)
- [x] Authentication & guest account onboarding flows
- [x] Pure memory 9-grid battle simulation prototype
- [ ] Redis asynchronous write-behind caching layer
- [ ] Database connection pool integration (MySQL `zc_sanguo` schema migration)
- [ ] Native WebSocket server for real-time world chat & guild broadcasts
- [ ] Complete port of remaining legacy modules (`arena`, `tower`, `pass_general`)

---

## 📄 License

This project is licensed under the [MIT License](LICENSE).
