# Personal Operations Hub

A centralized, local-first personal productivity and operations platform built for a university student, software engineer, and team leader.

Rather than a generic to-do list or cloud-dependent SaaS clone, **Personal Operations Hub** is designed around low cognitive load, intentional daily planning, domain customizability, background reminders, robust SQLite concurrency, and cross-platform operational resilience.

---

## Architecture & Technology Stack

- **Backend**: Laravel 13.39.0 (PHP 8.4)
- **Frontend**: Vue 3.5 (Composition API) + TypeScript + Inertia.js v2
- **Styling & UI**: Tailwind CSS 3 + Lucide Icons
- **Database & Concurrency**: SQLite with Write-Ahead Logging (WAL mode), `busy_timeout = 5000ms`, `synchronous = NORMAL`
- **Reverse Proxy**: Caddy (Automated Let's Encrypt / ZeroSSL TLS) or Nginx
- **Containerization**: Multi-service Docker Compose (`app`, `queue`, `scheduler`, `caddy`)
- **Testing**: Pest PHP & PHPUnit (**132 automated tests, 892 assertions, 100% pass rate**)
- **Asset Bundler**: Vite 8 with Production Asset Optimization

---

## Platform Quickstart Matrix

Choose the execution method that matches your environment:

### Option 1: Native Windows Host (Recommended for Local Dev)

Double-click `controller.bat` or run via PowerShell:

```powershell
# Interactive Unified Controller (34 operations covering servers, backups, and diagnostics):
.\controller.bat

# Or launch background services directly:
powershell -ExecutionPolicy Bypass -File .\scripts\start.ps1

# Check status:
powershell -ExecutionPolicy Bypass -File .\scripts\start.ps1 -Status

# Stop all services:
powershell -ExecutionPolicy Bypass -File .\scripts\stop.ps1
```

### Option 2: Native Linux Host, macOS, or WSL

Use the unified `./poh` CLI dispatcher or `controller.sh`:

```bash
# Initial bootstrapping:
./poh setup

# Start background services (HTTP, Scheduler, Queue):
./poh start

# Check service health and PID status:
./poh status

# Safe shutdown:
./poh stop

# Run automated test suite:
./poh test
```

### Option 3: Docker Compose (Recommended for Production Self-Hosting)

Spin up the complete 4-container stack with automated Let's Encrypt SSL:

```bash
# 1. Copy production environment configuration:
cp .env.production.example .env

# 2. Configure your domain and APP_KEY in .env

# 3. Launch all containers:
docker compose up -d

# 4. Check container health:
docker compose ps
```

---

## Core Operations & CLI Commands

Personal Operations Hub provides dedicated Artisan commands for system management:

| Command | Purpose |
| :--- | :--- |
| `php artisan ops:status` | Displays live summary of tasks due today, habits, unread alerts, and backup health. |
| `php artisan ops:backup-database` | Creates verified, atomic SQLite snapshot with SHA-256 metadata sidecar. |
| `php artisan ops:restore-database` | Safely restores database with pre-restore safety snapshots and automatic rollback. |
| `php artisan ops:decrypt-backup` | Decrypts authenticated AES-256 backup archives (`.enc`). |
| `php artisan ops:quick-add` | Captures tasks quickly using natural shorthand (`!high`, `@tomorrow`, `~45m`, `#Project`). |
| `php artisan ops:mobile` | Displays local LAN IP addresses and scannable terminal QR codes. |
| `php artisan ops:maintenance` | Prunes expired notifications, stale reminders, and old backups. |

---

## Backup & Disaster Recovery Architecture

Personal Operations Hub features an automated, enterprise-grade disaster recovery pipeline:

- **Automated Snapshots:** Hourly checks and 24-hour retention cycles.
- **Pre-Restore Safety Net:** `ops:restore-database` creates a verified pre-restore snapshot before modifying active data.
- **Corrupted State Protection:** If a restore attempt encounters an invalid file or fails post-restore integrity checking, the active database is **automatically rolled back** to the pre-restore snapshot.
- **Authenticated Encryption:** AES-256-CBC encryption with PBKDF2-SHA256 (100,000 rounds) and HMAC-SHA256 authentication (`OPSHUBENC1` header).
- **Explicit Migration Control:** Does not silently upgrade schemas upon restore; migrations require explicit `--migrate` approval.

---

## Documentation Directory

For detailed operations, recovery, and production architecture guides, refer to:

- 📖 **[Operations Runbook](docs/OPERATIONS_RUNBOOK.md)**: Day-to-day operations, logging, background workers, and troubleshooting.
- 🛡️ **[Disaster Recovery Runbook](docs/DISASTER_RECOVERY_RUNBOOK.md)**: Bare-metal restore procedures, emergency decryption, and rollback drills.
- 🚀 **[Production Deployment Guide](docs/PRODUCTION_DEPLOYMENT_GUIDE.md)**: Detailed deployment walkthroughs for Docker Compose + Caddy, Linux Systemd, and Cloud PaaS.
- 🔒 **[Background Scheduler](docs/BACKGROUND_SCHEDULER.md)**: Scheduler configuration and recurring task engine.
- 📅 **[Calendar Integration](docs/CALENDAR_INTEGRATION.md)**: RFC 5545 `.ics` export and private token feed architecture.
