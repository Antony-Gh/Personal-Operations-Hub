# Windows Background Runner & Scheduler Guide

To deliver task reminders, project deadline countdowns, recurring task generation, morning briefings, weekly digests, and automated SQLite backups **without keeping terminal windows open**, Personal Operations Hub includes a dedicated headless PowerShell runner and Windows Task Scheduler integration.

---

## 1. Architecture Overview

In background mode (`scripts\start-background.ps1`), three hidden PHP processes run locally and log to `storage\logs\background\`:

1. **HTTP Server (`php artisan serve --host=127.0.0.1 --port=8000`)** — Serves the compiled application (`npm run build`) at `http://127.0.0.1:8000`.
2. **Laravel Scheduler (`php artisan schedule:work`)** — Evaluates `routes/console.php` every minute:
   - `ops:process-reminders` (every minute, `withoutOverlapping`)
   - `ops:process-recurring` (every minute, `withoutOverlapping`)
   - `ops:send-briefings` (every minute, `withoutOverlapping`)
   - `ops:backup-database --if-needed` (hourly check for daily verified backup)
   - `ops:maintenance` (daily at `03:00` — prunes stale cancelled/dismissed reminders and failed jobs)
3. **Database Queue Worker (`php artisan queue:work --sleep=3 --tries=3 --timeout=60`)** — Processes queued jobs from SQLite's `jobs` table.

Process IDs are tracked in `.run\background-pids.json` (plus `.run\laravel.pid`, `.run\scheduler.pid`, `.run\queue.pid`) and verified by process name (`php` / `php-cgi` / `cmd`) before stopping.

---

## 2. Manual Background Start / Status / Stop

Run from the project root in PowerShell:

```powershell
# Start all 3 headless background workers (builds frontend automatically if public\build\manifest.json is missing)
powershell -ExecutionPolicy Bypass -File .\scripts\start-background.ps1

# Check live PID status of HTTP Server, Scheduler, and Queue Worker
powershell -ExecutionPolicy Bypass -File .\scripts\start-background.ps1 -Status

# Stop all headless background workers cleanly
powershell -ExecutionPolicy Bypass -File .\scripts\stop-background.ps1
```

### Optional LAN Binding (For Local Wi-Fi Phone / Tablet Access)
To make the web app and `.ics` calendar feed reachable from other devices on your home Wi-Fi:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\start-background.ps1 -BindHost 0.0.0.0 -Port 8000
```

---

## 3. Auto-Start at Windows Login (Task Scheduler)

Register a per-user Windows Scheduled Task (`PersonalOperationsHub-Background`) so the hub starts silently whenever you sign into Windows—no Administrator elevation required:

```powershell
# Register auto-start task at Windows logon (and start immediately)
powershell -ExecutionPolicy Bypass -File .\scripts\install-task-scheduler.ps1

# Remove auto-start task (and optionally stop running workers)
powershell -ExecutionPolicy Bypass -File .\scripts\uninstall-task-scheduler.ps1 -StopRunning
```

You can inspect the task anytime in Windows **Task Scheduler** (`taskschd.msc`) under `PersonalOperationsHub-Background`, or via PowerShell:

```powershell
Get-ScheduledTask -TaskName "PersonalOperationsHub-Background"
```

---

## 4. Manual Artisan Operational Commands

You can invoke any scheduled task manually for testing or diagnostics:

```powershell
php artisan ops:process-reminders
php artisan ops:process-recurring
php artisan ops:send-briefings
php artisan ops:backup-database --trigger=manual
php artisan ops:maintenance
php artisan schedule:list
```

---

## 5. Logs & Troubleshooting

Background worker logs are written to `storage\logs\background\`:

- `server.out.log` / `server.err.log`
- `scheduler.out.log` / `scheduler.err.log`
- `queue.out.log` / `queue.err.log`

View recent logs quickly with:

```powershell
powershell -ExecutionPolicy Bypass -File .\start.ps1 -Logs
```

### Important Local Windows Limitations
- **Sleep / Hibernation / Shutdown**: While Windows is asleep, hibernating, or powered off, local processes are suspended by the OS. As soon as Windows wakes up (or when you log back in), `schedule:work` automatically catches up on any due reminders, overdue checks, briefings, and backups that passed while the machine was asleep.

