# Local Windows Setup Guide

Personal Operations Hub is designed to run **100% locally on Windows 10/11** using SQLite, Laravel's local HTTP server, Laravel Scheduler, Database Queue worker, and Vite—with zero cloud, Docker, Redis, or external paid service dependencies.

---

## 1. Prerequisites

Ensure the following tools are installed and available in your Windows `PATH`:

- **PHP 8.2+** (PHP 8.4 recommended) with extensions: `pdo_sqlite`, `sqlite3`, `mbstring`, `openssl`, `fileinfo`, `intl`, `curl`
- **Composer 2.x**
- **Node.js 18+** and **npm**
- **Windows PowerShell 5.1+** (pre-installed on Windows 10/11)

Verify your environment in PowerShell:

```powershell
php -v
composer -V
node -v
npm -v
```

---

## 2. First-Time Installation

From the repository root (`e:\Projects\Personal-Operations-Hub`):

```powershell
# 1. Install PHP and frontend dependencies
composer install
npm install

# 2. Create .env if not already present
if (-not (Test-Path .env)) {
    Copy-Item .env.example .env
    php artisan key:generate
}

# 3. Ensure SQLite database exists and run safe migrations
if (-not (Test-Path database\database.sqlite)) {
    New-Item -ItemType File -Path database\database.sqlite -Force | Out-Null
}
php artisan migrate --force

# 4. Build production frontend assets (used when running in headless background mode)
npm run build
```

> **Preserving Existing Data**: Never run `php artisan migrate:fresh` against an existing `database\database.sqlite` file unless you intentionally want to wipe all local data. Always use `php artisan migrate`.

---

## 3. Operations Management (`controller.ps1` or `scripts/start.ps1`)

For unified management of all operations, run the central controller:

```powershell
powershell -ExecutionPolicy Bypass -File .\controller.ps1
```

For direct interactive daily launch with live frontend hot-reloading plus background alarms, reminders, and queue processing:

### Option A — PowerShell Launcher (with Helper Flags)

```powershell
# Launch HTTP server, Vite, Scheduler, Queue worker, and open browser
powershell -ExecutionPolicy Bypass -File .\scripts\start.ps1

# Launch without opening a new browser tab
powershell -ExecutionPolicy Bypass -File .\scripts\start.ps1 -NoBrowser

# Check live running status of all local workers
powershell -ExecutionPolicy Bypass -File .\scripts\start.ps1 -Status

# Trigger an immediate verified SQLite backup from the terminal
powershell -ExecutionPolicy Bypass -File .\scripts\start.ps1 -Backup

# Tail recent application and background worker logs
powershell -ExecutionPolicy Bypass -File .\scripts\start.ps1 -Logs
```

### Option B — Batch Launcher
Double-click `scripts\start.bat` in Windows Explorer, or run:

```cmd
scripts\start.bat
```

### Clean Shutdown
Stop all tracked processes started by Personal Operations Hub (without affecting unrelated PHP or Node processes on your PC):

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\stop.ps1
```
*(Or double-click `scripts\stop.bat`.)*

---

## 4. Installing as a Local Desktop App (PWA)

Personal Operations Hub ships with a local Web App Manifest (`public/site.webmanifest`) and Service Worker (`public/sw.js`):

1. Open `http://127.0.0.1:8000` in **Microsoft Edge** or **Google Chrome**.
2. Click the **Install App** icon in the address bar (or **Menu -> Apps -> Install Personal Operations Hub**).
3. Pin the installed app to your Windows Taskbar or Start Menu for standalone window access and native Windows notification banners.

---

## 5. Global Keyboard Shortcuts

| Shortcut | Action |
| :--- | :--- |
| `Ctrl + K` / `Cmd + K` | Open Global Command Palette (search tasks, projects, milestones, habits, and quick actions) |
| `Ctrl + N` | Open Quick Add Task modal |
| `Esc` | Close active modal, Command Palette, or Notification Center drawer |

