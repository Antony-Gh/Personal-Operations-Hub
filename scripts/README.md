# Personal Operations Hub — Scripts & Unified Controller Guide

Welcome to the central operational scripts repository for **Personal Operations Hub**. This directory houses all PowerShell, batch, and maintenance utilities that power local automation, service management, database integrity, backup replication, desktop companions, and mobile LAN access.

---

## 1. Quick Start: The Unified Controller

The recommended entry point for managing the entire application is the **Unified Controller** located in the project root:

```cmd
:: Double-click or run from command prompt (auto-elevates to Administrator):
controller.bat
```

```powershell
# Or run directly via PowerShell:
powershell -ExecutionPolicy Bypass -File .\controller.ps1
```

*(You can also launch it from any directory; it automatically detects and operates relative to the project root).*

### Features of the Unified Controller

- **Automatic Administrator Elevation**: Launching `controller.bat` automatically prompts for UAC elevation and starts an Administrator session so tasks requiring privileges (e.g. Windows Firewall configuration, Task Scheduler installation) execute without access errors.
- **Categorized interactive terminal dashboard**: 34 operational commands organized across 8 logical categories.
- **Detailed Operation Cards**: Every operation clearly displays its **Description**, **Prerequisites**, **Impact**, **Estimated Duration**, **Risk Level (Low/Medium/High)**, and **Confirmation Requirements**.
- **Safety Safeguards**: Confirmation required before destructive actions (database wipe/rollback/restore) with automatic pre-execution snapshots.
- **Safe Process Management**: Only terminates verified application processes tracked by PID; never kills unrelated processes.

---

## 2. Directory Structure & Script Inventory

All scripts are located in `scripts/`, except the root-level `controller.bat` and `controller.ps1`:

```text
Personal-Operations-Hub/
├── controller.bat                  # Batch launcher with UAC Administrator auto-elevation
├── controller.ps1                  # Central interactive terminal manager (root entry point)
└── scripts/
    ├── start.ps1                   # Interactive launcher (Laravel + Vite + Scheduler + Queue + Browser)
    ├── start.bat                   # Batch wrapper for interactive development launch
    ├── stop.ps1                    # Safe service stopper (stops all tracked processes and clears state)
    ├── stop.bat                    # Batch wrapper for stopping all services
    ├── start-background.ps1        # Headless background runner (HTTP + Scheduler + Queue)
    ├── stop-background.ps1         # Stops headless background services
    ├── install-task-scheduler.ps1  # Registers Windows Task Scheduler auto-start at user logon
    ├── uninstall-task-scheduler.ps1# Removes the Windows Task Scheduler background startup task
    ├── backup-database.ps1         # Verified SQLite database backup with authenticated AES-256 encryption
    ├── restore-database.ps1        # Interactive database restore with pre-restore snapshot and integrity check
    ├── run-diagnostics.ps1         # Full system health check (PHP, Node, Composer, SQLite, Ports, Permissions)
    ├── run-tests.ps1               # Automated test runner with compact execution timing and summary
    ├── clean-cache.ps1             # Clears framework caches and prunes stale runtime state
    ├── view-logs.ps1               # Log viewer for Laravel and background worker logs
    ├── enable-mobile-access.ps1    # Configures 0.0.0.0 server binding and Windows Firewall rule for LAN phone access
    ├── disable-mobile-access.ps1   # Reverts server to 127.0.0.1 localhost-only and disables firewall rule
    ├── tray-companion.ps1          # Native Windows System Tray Companion app with live badges and quick add
    ├── send-windows-toast.ps1      # Native WinRT / NotifyIcon desktop toast notification dispatcher
    ├── generate-pwa-icons.php      # Zero-dependency PHP GD standard PWA PNG icon generator
    └── README.md                   # This comprehensive documentation guide
```

---

## 3. Detailed Script Descriptions & Usage

### Application Startup & Shutdown

| Script | Purpose | Flags / Options | Risk |
| --- | --- | --- | :---: |
| `scripts/start.ps1` | Launches full development environment (HTTP on 8000, Vite compiler on 5173, Scheduler, and Queue worker with browser auto-open). | `-Status`, `-Backup`, `-Logs`, `-NoBrowser` | Low |
| `scripts/start.bat` | Double-clickable batch launcher for `scripts/start.ps1`. | None | Low |
| `scripts/stop.ps1` | Safely terminates all tracked application processes (`laravel.pid`, `vite.pid`, `scheduler.pid`, `queue.pid`, `background-pids.json`) and clears runtime state. | None | Low |
| `scripts/stop.bat` | Double-clickable batch stopper for `scripts/stop.ps1`. | None | Low |
| `scripts/start-background.ps1` | Starts headless background services without terminal windows. Logs to `storage/logs/background/`. | `-Status`, `-Port <int>`, `-BindHost <ip>` | Low |
| `scripts/stop-background.ps1` | Stops headless background services cleanly. | None | Low |

### Windows System Integration & Alarms

| Script | Purpose | Flags / Options | Risk |
| --- | --- | --- | :---: |
| `scripts/install-task-scheduler.ps1` | Registers a Windows scheduled task (`PersonalOperationsHub-Background`) to launch background workers silently at Windows user logon. | `-TaskName <name>`, `-RunNow` | Low |
| `scripts/uninstall-task-scheduler.ps1` | Unregisters the Windows Task Scheduler startup task and stops background workers. | `-TaskName <name>` | Low |
| `scripts/tray-companion.ps1` | Starts a native Windows System Tray application displaying real-time task and habit badges with a 1-click Quick Add prompt. | `-Port <int>`, `-BindHost <ip>` | Low |
| `scripts/send-windows-toast.ps1` | Headless, browserless Windows 10/11 Action Center toast notification dispatcher via WinRT (`Windows.UI.Notifications`). | `-Title <str>`, `-Message <str>`, `-Category <str>` | Low |

### Database, Backups & Maintenance

| Script | Purpose | Flags / Options | Risk |
| --- | --- | --- | :---: |
| `scripts/backup-database.ps1` | Triggers a verified database backup with authenticated AES-256 encryption, PBKDF2 key derivation, and secondary mirror replication. | `-Trigger <manual\|scheduled>` | Low |
| `scripts/restore-database.ps1` | Interactive database restore utility. Creates a pre-restore safety snapshot (`database.sqlite.pre-restore.<timestamp>.bak`) before restoring. | `-BackupFile <path>`, `-Force` | High |
| `scripts/clean-cache.ps1` | Clears configuration, route, view, and application cache, and cleans stale orphaned PID files in `.run/`. | `-Optimize`, `-Force` | Low |

### Diagnostics & Testing

| Script | Purpose | Flags / Options | Risk |
| --- | --- | --- | :---: |
| `scripts/run-diagnostics.ps1` | Comprehensive system audit: verifies PHP 8.2+, Node.js, Composer, npm, `.env`, SQLite `PRAGMA integrity_check`, ports 8000/5173, and filesystem write permissions. | None | Low |
| `scripts/run-tests.ps1` | Runs Pest / PHPUnit test suite with execution timing and failure summaries. | `-Filter <str>`, `-Detailed` | Low |
| `scripts/view-logs.ps1` | Inspects recent tail output of `laravel.log` and background worker logs (`scheduler.out.log`, `queue.out.log`, `server.out.log`). | `-Lines <int>`, `-ErrorsOnly` | Low |

### Mobile LAN & PWA Support

| Script | Purpose | Flags / Options | Risk |
| --- | --- | --- | :---: |
| `scripts/enable-mobile-access.ps1` | Auto-detects local Wi-Fi IPv4, adds a Windows Firewall rule for port 8000, binds the server to `0.0.0.0`, and prints a console QR code. | `-Port <int>`, `-NoRestart` | Low |
| `scripts/disable-mobile-access.ps1` | Reverts the server to `127.0.0.1` localhost-only and disables the firewall rule. | `-Port <int>`, `-NoRestart` | Low |
| `scripts/generate-pwa-icons.php` | Synthesizes standard 192x192, 512x512, and maskable PNG icons for mobile PWA compliance using PHP GD. | None | Low |

---

## 4. Backup & Restore Procedures

### Automated Daily Backups

The Laravel Scheduler automatically generates encrypted daily backups during background execution (stored in `storage/app/backups/`).

### Manual Backup

To create an on-demand verified backup:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\backup-database.ps1
# Or via controller option 15
```

### Safe Database Restoration

To restore from a backup:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\restore-database.ps1
# Or via controller option 16
```

1. It lists all available backups in `storage/app/backups/`.
2. It asks for your selection number.
3. **Safety Guarantee**: Before modifying the active database, it copies `database/database.sqlite` to `database/database.sqlite.pre-restore.<timestamp>.bak`.
4. If the selected backup is encrypted (`.enc`), it decrypts it using your configured passphrase.
5. It runs `PRAGMA integrity_check` on the restored database to verify SQLite health.

---

## 5. Troubleshooting & FAQ

### Port 8000 or 5173 Already Bound

If another process is holding port 8000 or 5173:

```powershell
# Run the controller diagnostics:
powershell -ExecutionPolicy Bypass -File .\scripts\run-diagnostics.ps1
```

The diagnostics script identifies the exact process ID and process name bound to the port. If it is an orphaned Personal Operations Hub process, run:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\stop.ps1
```

### Phone Cannot Connect Over Wi-Fi

1. Ensure your phone is connected to the same Wi-Fi network as your PC (not cellular data).
2. Run `scripts/enable-mobile-access.ps1` from an elevated PowerShell prompt to ensure the Windows Firewall inbound rule for port 8000 was added.
3. View your scannable QR code: `php artisan ops:mobile`.

### Clearing Caches After Code Updates

If frontend or backend changes aren't appearing:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\clean-cache.ps1 -Optimize
npm run build
```

---

## 6. How to Add a New Script and Register in the Controller

1. **Create the Script**:
   Place your new PowerShell script inside `scripts/my-script.ps1`. Always resolve the project root using:

   ```powershell
   $ProjectRoot = Split-Path -Parent $PSScriptRoot
   Set-Location $ProjectRoot
   ```

2. **Register in `controller.ps1`**:
   Open `controller.ps1` and append a new operation to `$Operations`:

   ```powershell
   [PSCustomObject]@{
       Id                   = 35
       Category             = "Project Information & Utilities"
       Name                 = "My New Utility Task"
       Description          = "What the new utility does."
       Requirements         = "Prerequisites (e.g. PHP 8.2+)"
       Impact               = "What files or state it modifies"
       Duration             = "1 - 2 seconds"
       Risk                 = "Low" # Low / Medium / High
       RequiresConfirmation = $false
       Action               = {
           param($root)
           & powershell.exe -NoProfile -ExecutionPolicy Bypass -File (Join-Path $root "scripts\my-script.ps1")
       }
   }
   ```

3. **Document in `scripts/README.md`**: Add an entry under the appropriate category table above.
