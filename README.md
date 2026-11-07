# Personal Operations Hub

A centralized, local-first personal productivity and operations platform built for a university student, software engineer, and team leader.

Rather than a generic to-do list or cloud-dependent SaaS clone, **Personal Operations Hub** is designed around low cognitive load, intentional daily planning, domain customizability, local Windows background alarms & reminders, and sustainable, recovery-friendly routines.

---

## Tech Stack (100% Local Windows Stack)

- **Backend**: Laravel 13 (PHP 8.4)
- **Frontend**: Vue 3.4 (Composition API) + TypeScript 5.6 + Inertia.js v2
- **Styling & Icons**: Tailwind CSS 3 + `lucide-vue-next`
- **Database & Queue**: SQLite (`database/database.sqlite` + Database Queue & Notifications)
- **Background Runtime**: Laravel Scheduler (`schedule:work`) + Database Queue (`queue:work`) + Windows PowerShell 5.1 / Task Scheduler scripts
- **Testing**: Pest PHP 3 & PHPUnit 11 (**79 automated tests, 410 assertions, 100% pass rate**)
- **Asset Bundler & PWA**: Vite 8 + Local Web App Manifest & Service Worker

---

## Core Features

1. **Personalized Dashboard & Automated Briefings**
   - **Today's Overview**: Tasks due today, overdue items, completed tasks, upcoming 7-day deadlines, Focus minutes logged today, and queued local reminders.
   - **Morning Briefing & Weekly Digest (`/briefings`)**: Scheduled or on-demand briefings summarizing priorities, deadlines, active project progress, focus sessions, and habit streaks.
   - **Habits & Spiritual Life Widget**: Quick one-click check-ins with live streak badges.
   - **Phone Usage Widget**: Mindful leisure screen-time tracking against daily targets.

2. **Local Alarms, Reminders & Notification Center**
   - **Task Alarms**: Presets (`At due time`, `15m`, `30m`, `1h`, `1d`, `Custom datetime`), multiple reminder offsets per task, and automatic overdue alerts.
   - **Project & Milestone Countdowns**: Configurable target countdowns (`7`, `3`, `1`, `0` days before deadline), overdue alerts, and milestone completion notifications.
   - **In-App Notification Center**: Top-bar drawer with unread counter, category filters, **Snooze** (`15m`, `30m`, `1h`, `Tomorrow 09:00`), **Dismiss**, and one-click **Complete Task**.
   - **Native Desktop Banners & Offline Audio**: Browser `Notification` API integration plus Web Audio API synthesized chimes (`Gentle Chime`, `Warm Bell`, `Soft Ping`, `Digital Alarm`) with volume control—zero external audio files or internet required.
   - **Quiet Hours**: Suppresses standard alerts overnight while optionally allowing critical high-priority overrides.

3. **Focus / Pomodoro Timer & Actual Duration Logging**
   - Persistent top-bar **Mini Focus Timer** (`Focus`, `Short Break`, `Long Break`) backed by server timestamps (`focus_sessions`) so countdowns stay accurate across tab switches and reloads.
   - Automatically logs earned focus minutes directly into linked `Task.actual_duration` without double counting.

4. **Comprehensive Task Management & Kanban Board**
   - Switch seamlessly between **List View** and **5-Column Interactive Kanban Board** (`Not Started`, `In Progress`, `Waiting`, `Deferred`, `Completed`) with drag-and-drop and keyboard/menu status controls.
   - Semantic priority sorting (`High -> Medium -> Low`), checklists, tags, estimated vs. logged duration, and bulk operations.
   - **Complete Recurring Task Engine**: Supports `daily`, `weekdays` (Mon–Fri), `weekly`, `monthly` (safe month-end handling), and custom `N`-day/week/month intervals with end dates or max occurrence limits, inheriting tags, checklists, and reminder presets.

5. **Global Command Palette (`Ctrl+K` / `Cmd+K`)**
   - Instant keyboard-driven search across tasks, projects, milestones, habits, navigation routes, and quick actions (start focus timer, open quick add, jump to briefings).

6. **Interactive Calendar & `.ics` Integration**
   - Monthly grid & agenda views aggregating calendar commitments, tasks with due dates/times, project deadlines, and milestones.
   - **RFC 5545 `.ics` Export & Private Token Feed**: One-click `.ics` download plus a revocable, SHA-256 hashed private subscription URL (`/calendar/feed/{token}.ics`) with `VALARM` triggers for Thunderbird, Outlook, or LAN devices.

7. **Habits, Streaks & Consistency Heatmap**
   - Tracks **Current Streak**, **Best Streak**, **Weekly/Monthly Consistency %**, flexible weekly targets (e.g., 5 days/week without weekend streak loss), clickable 14-day history tiles, and a GitHub-style **Monthly Activity Heatmap** with 6-month momentum trends.

8. **Timezone-Accurate Engine**
   - Full IANA timezone support (`Settings -> Localization & Timezone`) with one-click browser timezone detection and automatic reminder resynchronization.

9. **Automated Verified SQLite Backups & Safe Restore**
   - Daily automated SQLite snapshots (`VACUUM INTO` + `PRAGMA integrity_check` + SHA-256 metadata sidecar) stored in `storage/app/backups/` with configurable retention pruning.
   - Safe in-app restoration requiring explicit `RESTORE` confirmation and automatically capturing a `pre_restore` snapshot before restoring.

---

## Quick Start (Windows 10/11)

### 1. Install Dependencies & Initialize

```powershell
composer install
npm install
if (-not (Test-Path .env)) { Copy-Item .env.example .env; php artisan key:generate }
php artisan migrate --force
npm run build
```

### 2. Launch Interactive Mode (HTTP + Vite + Scheduler + Queue)

```powershell
powershell -ExecutionPolicy Bypass -File .\start.ps1
```
*(Or double-click `start.bat`. Stop cleanly anytime with `.\stop.ps1` or `stop.bat`.)*

Useful launcher flags:
```powershell
powershell -ExecutionPolicy Bypass -File .\start.ps1 -Status
powershell -ExecutionPolicy Bypass -File .\start.ps1 -Backup
powershell -ExecutionPolicy Bypass -File .\start.ps1 -Logs
```

### 3. Headless Windows Background Mode & Auto-Start at Login

Run Personal Operations Hub silently in the background without open terminal windows:

```powershell
# Start headless HTTP server + Scheduler + Queue worker
powershell -ExecutionPolicy Bypass -File .\scripts\start-background.ps1

# Check live PID status
powershell -ExecutionPolicy Bypass -File .\scripts\start-background.ps1 -Status

# Stop headless workers
powershell -ExecutionPolicy Bypass -File .\scripts\stop-background.ps1

# Optional: Auto-start at Windows login via Task Scheduler
powershell -ExecutionPolicy Bypass -File .\scripts\install-task-scheduler.ps1
```

---

## Local Documentation Guides

- [Local Windows Setup & PWA Guide](docs/LOCAL_SETUP.md)
- [Background Runner & Windows Task Scheduler Guide](docs/BACKGROUND_SCHEDULER.md)
- [Alarms, Reminders & Notification System Guide](docs/NOTIFICATIONS.md)
- [Calendar `.ics` Export & Local Subscription Guide](docs/CALENDAR_INTEGRATION.md)
- [Automated SQLite Backups & Safe Restore Guide](docs/BACKUPS.md)

---

## Default Seeded Credentials (Fresh Seeded Install)

- **Email**: `user@operations-hub.local`
- **Password**: `password`

*(Registration is disabled by default via `ALLOW_REGISTRATION=false` in `.env`.)*

---

## Automated Testing & Code Quality

```powershell
# Format PHP code with Laravel Pint
vendor\bin\pint

# Run the Pest test suite (79 tests, 410 assertions)
php artisan test --compact

# Type-check Vue/TypeScript and compile production bundle
npm run build
```

---

## License

Open-source personal software under the [MIT License](LICENSE).
