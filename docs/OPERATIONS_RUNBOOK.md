# Personal Operations Hub — Operations Runbook

This runbook outlines standard operating procedures, daemon management, logging, routine maintenance, and troubleshooting across Windows, Linux, and Docker environments.

---

## 1. Environment Architecture & Entrypoints

Personal Operations Hub provides three primary operating methods, all sharing the same Laravel 13.39 (PHP 8.4) backend and SQLite WAL database:

| Environment | Primary Entrypoint | Description |
| :--- | :--- | :--- |
| **Windows Native** | `controller.bat` or `controller.ps1` | Central menu with 34 operations, process tracking via `.run/`, desktop notifications. |
| **Linux Native / WSL** | `./poh` or `controller.sh` | POSIX Bash controller with service management, background daemons, and systemd units. |
| **Docker Multi-Container** | `docker compose up -d` | Four isolated services (`app`, `queue`, `scheduler`, `caddy`) with persistent storage volumes. |

---

## 2. Service Management Lifecycle

### Windows Native (PowerShell)
```powershell
# Interactive menu:
.\controller.bat

# Headless background start (Server + Scheduler + Queue):
powershell -ExecutionPolicy Bypass -File .\scripts\start-background.ps1

# Service status check:
powershell -ExecutionPolicy Bypass -File .\scripts\start-background.ps1 -Status

# Safe service stop:
powershell -ExecutionPolicy Bypass -File .\scripts\stop.ps1
```

### Linux Native & macOS (Bash)
```bash
# Unified dispatcher (auto-detects environment):
./poh start
./poh status
./poh stop
./poh restart

# Or via controller.sh:
./controller.sh start
./controller.sh status
./controller.sh stop
```

### Docker Multi-Container
```bash
# Start all services in the background:
docker compose up -d

# Check health and status of all containers:
docker compose ps

# View live container logs:
docker compose logs -f

# Stop all services:
docker compose down
```

---

## 3. Background Daemons & Scheduling

Personal Operations Hub relies on two background daemons for real-time operation:

1. **Queue Worker (`php artisan queue:work --sleep=3 --tries=3 --timeout=60`)**:
   - Processes background notification dispatches, email triggers, and resource-heavy calculations.
   - Database queue driver persists jobs across server restarts in the `jobs` table.
2. **Task Scheduler (`php artisan schedule:work` / `schedule:run`)**:
   - Executes periodic tasks defined in `routes/console.php`:
     - Every minute: `ops:process-reminders` (overdue alerts, task reminders).
     - Every minute: `ops:process-recurring` (spawns recurring tasks for due periods).
     - Every minute: `ops:send-briefings` (morning briefings, evening digests).
     - Hourly: `ops:backup-database --if-needed` (automated 24h backup cycle).
     - Daily at 03:00: `ops:maintenance` (prunes read notifications, closed sessions, and expired backups).
   - **Concurrency Safety:** All scheduled tasks enforce `->withoutOverlapping()` to prevent deadlocks and duplicate job executions.

---

## 4. Logging & Monitoring

### Log File Locations
- **Application Log:** `storage/logs/laravel.log` (Rotated daily when `LOG_CHANNEL=daily`).
- **Windows Background Logs:** `storage/logs/background/server.log`, `scheduler.log`, `queue.log`.
- **Docker Container Logs:** Accessible via `docker compose logs [service]`.

### Viewing Recent Logs
- **Windows:** `powershell -ExecutionPolicy Bypass -File .\scripts\view-logs.ps1 -Lines 50`
- **Linux:** `./poh logs` or `./controller.sh logs`
- **Docker:** `docker compose logs --tail=50 -f app`

### Health Check Endpoint
- **URL:** `http://localhost:8000/up` (or `https://operations.your-domain.com/up`)
- **Status:** Returns `HTTP 200 OK` when the application runtime and database are reachable.
- **Monitoring Tools:** Compatible with Uptime Kuma, Healthchecks.io, or AWS/Cloudflare health checks.

---

## 5. Routine Maintenance Procedures

### 1. Manual Application Maintenance
Run the unified maintenance pipeline to prune expired records, stale notifications, and orphaned focus sessions:
```bash
php artisan ops:maintenance
```

### 2. Cache Invalidation & Optimization
When updating configuration, routes, or environment variables:
```bash
# Clear all caches:
php artisan cache:clear && php artisan config:clear && php artisan route:clear && php artisan view:clear

# Warm caches for production:
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

### 3. SQLite Database Compaction & Vacuuming
Periodically compact the SQLite database file to reclaim unused space:
```bash
php artisan db:wipe --help # (Do not run wipe in production)
# Vacuum active database:
php -r "(new SQLite3('database/database.sqlite'))->exec('VACUUM;');"
```

---

## 6. Troubleshooting Common Operational Issues

### 1. Port 8000 or 5173 Already in Use
- **Symptoms:** `Failed to listen on 127.0.0.1:8000 (reason: Address already in use)`.
- **Windows Resolution:**
  ```powershell
  # Identify PID occupying the port:
  Get-NetTCPConnection -LocalPort 8000 -ErrorAction SilentlyContinue | Select-Object OwningProcess
  # Run clean stop:
  powershell -ExecutionPolicy Bypass -File .\scripts\stop.ps1
  ```
- **Linux Resolution:**
  ```bash
  fuser -k 8000/tcp 5173/tcp
  ./controller.sh stop
  ```

### 2. SQLite Database File Locking (`SQLITE_BUSY` / `database is locked`)
- **Root Cause:** A long-running write transaction or stale unclosed connection.
- **Resolution:**
  1. Verify WAL configuration in `config/database.php` (`busy_timeout = 5000`, `synchronous = NORMAL`).
  2. Restart background workers:
     - Windows: `powershell -ExecutionPolicy Bypass -File .\controller.ps1 -Option 5`
     - Linux: `./controller.sh restart`
     - Docker: `docker compose restart app queue scheduler`
  3. Clean up stale `-wal` or `-shm` sidecars only after stopping services:
     `rm -f database/database.sqlite-wal database/database.sqlite-shm`

### 3. Stale PID Files in `.run/`
- **Symptoms:** Controller reports services as running, but server is unreachable.
- **Resolution:**
  - Delete stale PID tracking files: `rm -rf .run/*`
  - Run fresh start: `./controller.sh start` or `controller.bat`
