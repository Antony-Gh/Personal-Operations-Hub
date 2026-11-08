# ==============================================================================
# Personal Operations Hub - Unified Central Controller (controller.ps1)
# Compatible with Windows PowerShell 5.1 and PowerShell 7+
# ==============================================================================
param(
    [int]$Option = 0,
    [switch]$Help,
    [switch]$List,
    [string]$Category = "",
    [switch]$NonInteractive
)

$ErrorActionPreference = "Stop"

# Ensure all operations run relative to project root
$ProjectRoot = $PSScriptRoot
Set-Location $ProjectRoot

# UI Color Palette & Formatting Helpers
function Show-Header {
    param([string]$Subtitle = "")
    Clear-Host
    Write-Host "==============================================================================" -ForegroundColor Cyan
    Write-Host "                PERSONAL OPERATIONS HUB - UNIFIED CONTROLLER                 " -ForegroundColor Cyan
    Write-Host "              Local Windows Architecture & Operations Engine                 " -ForegroundColor DarkCyan
    Write-Host "==============================================================================" -ForegroundColor Cyan
    if ($Subtitle) {
        Write-Host "  >> $Subtitle" -ForegroundColor Yellow
        Write-Host "------------------------------------------------------------------------------" -ForegroundColor DarkGray
    }
    Write-Host ""
}

function Show-Separator {
    Write-Host "------------------------------------------------------------------------------" -ForegroundColor DarkGray
}

function Format-Risk {
    param([string]$Risk)
    switch ($Risk) {
        "High"   { return @{ Text = "HIGH"; Color = "Red" } }
        "Medium" { return @{ Text = "MEDIUM"; Color = "Yellow" } }
        default  { return @{ Text = "LOW"; Color = "Green" } }
    }
}

# Operations Registry
$Operations = @(
    # Category 1: Application Startup & Shutdown
    [PSCustomObject]@{
        Id                   = 1
        Category             = "Application Startup & Shutdown"
        Name                 = "Start Interactive Mode (Dev Server + Frontend + Workers)"
        Description          = "Launches HTTP server (8000), Vite dev server (5173), Scheduler, and Queue worker with PID tracking."
        Requirements         = "PHP 8.2+, Node.js 18+, Composer, npm"
        Impact               = "Binds ports 8000 & 5173; spawns 4 background processes; opens default browser"
        Duration             = "3 - 8 seconds"
        Risk                 = "Low"
        RequiresConfirmation = $false
        Action               = {
            param($root)
            & powershell.exe -NoProfile -ExecutionPolicy Bypass -File (Join-Path $root "scripts\start.ps1")
        }
    },
    [PSCustomObject]@{
        Id                   = 2
        Category             = "Application Startup & Shutdown"
        Name                 = "Start Headless Background Mode (Server + Scheduler + Queue)"
        Description          = "Runs HTTP server, Laravel scheduler, and queue worker silently in background without terminal windows."
        Requirements         = "PHP 8.2+, Composer"
        Impact               = "Binds port 8000; writes PIDs to .run\background-pids.json; logs to storage\logs\background\"
        Duration             = "1 - 3 seconds"
        Risk                 = "Low"
        RequiresConfirmation = $false
        Action               = {
            param($root)
            & powershell.exe -NoProfile -ExecutionPolicy Bypass -File (Join-Path $root "scripts\start-background.ps1")
        }
    },
    [PSCustomObject]@{
        Id                   = 3
        Category             = "Application Startup & Shutdown"
        Name                 = "Check Service Health & Process Status"
        Description          = "Inspects active HTTP, Vite, Scheduler, and Queue workers; checks listening ports and PID status."
        Requirements         = "None"
        Impact               = "Read-only process status check"
        Duration             = "< 1 second"
        Risk                 = "Low"
        RequiresConfirmation = $false
        Action               = {
            param($root)
            & powershell.exe -NoProfile -ExecutionPolicy Bypass -File (Join-Path $root "scripts\start.ps1") -Status
        }
    },
    [PSCustomObject]@{
        Id                   = 4
        Category             = "Application Startup & Shutdown"
        Name                 = "Stop All Running Services Safely"
        Description          = "Terminates all tracked Laravel HTTP, Vite, Scheduler, and Queue processes; clears .run state."
        Requirements         = "None"
        Impact               = "Terminates tracked processes; clears .run\ PIDs and public\hot"
        Duration             = "1 - 3 seconds"
        Risk                 = "Low"
        RequiresConfirmation = $false
        Action               = {
            param($root)
            & powershell.exe -NoProfile -ExecutionPolicy Bypass -File (Join-Path $root "scripts\stop.ps1")
        }
    },
    [PSCustomObject]@{
        Id                   = 5
        Category             = "Application Startup & Shutdown"
        Name                 = "Restart All Services (Clean Cycle)"
        Description          = "Stops all active services, verifies process termination, and restarts background services."
        Requirements         = "PHP 8.2+, Composer"
        Impact               = "Terminates and respawns backend workers"
        Duration             = "3 - 5 seconds"
        Risk                 = "Low"
        RequiresConfirmation = $false
        Action               = {
            param($root)
            Write-Host "Stopping existing services..." -ForegroundColor Cyan
            & powershell.exe -NoProfile -ExecutionPolicy Bypass -File (Join-Path $root "scripts\stop.ps1") | Out-Null
            Start-Sleep -Seconds 1
            Write-Host "Restarting background runner..." -ForegroundColor Cyan
            & powershell.exe -NoProfile -ExecutionPolicy Bypass -File (Join-Path $root "scripts\start-background.ps1")
        }
    },

    # Category 2: Development Servers & Processes
    [PSCustomObject]@{
        Id                   = 6
        Category             = "Development Servers & Processes"
        Name                 = "Start Vite Dev Compiler (npm run dev)"
        Description          = "Runs the Vite frontend development compiler in interactive mode on port 5173."
        Requirements         = "Node.js 18+, npm"
        Impact               = "Binds port 5173; creates public\hot file for hot-module reloading"
        Duration             = "Indefinite (Interactive)"
        Risk                 = "Low"
        RequiresConfirmation = $false
        Action               = {
            param($root)
            & npm run dev
        }
    },
    [PSCustomObject]@{
        Id                   = 7
        Category             = "Development Servers & Processes"
        Name                 = "Start Laravel HTTP Server Only (artisan serve)"
        Description          = "Runs the Laravel PHP built-in server interactively on port 8000."
        Requirements         = "PHP 8.2+"
        Impact               = "Binds port 8000"
        Duration             = "Indefinite (Interactive)"
        Risk                 = "Low"
        RequiresConfirmation = $false
        Action               = {
            param($root)
            & php artisan serve --host=127.0.0.1 --port=8000
        }
    },
    [PSCustomObject]@{
        Id                   = 8
        Category             = "Development Servers & Processes"
        Name                 = "Run Laravel Scheduler Interactively (schedule:work)"
        Description          = "Runs Laravel's scheduled task loop in the foreground so you can observe cron triggers."
        Requirements         = "PHP 8.2+"
        Impact               = "Executes due reminders, briefings, and backups on 1-minute schedule"
        Duration             = "Indefinite (Interactive)"
        Risk                 = "Low"
        RequiresConfirmation = $false
        Action               = {
            param($root)
            & php artisan schedule:work
        }
    },
    [PSCustomObject]@{
        Id                   = 9
        Category             = "Development Servers & Processes"
        Name                 = "Run Database Queue Worker Interactively (queue:work)"
        Description          = "Processes queued jobs in the foreground for real-time monitoring and debugging."
        Requirements         = "PHP 8.2+"
        Impact               = "Consumes database queue jobs"
        Duration             = "Indefinite (Interactive)"
        Risk                 = "Low"
        RequiresConfirmation = $false
        Action               = {
            param($root)
            & php artisan queue:work --sleep=3 --tries=3 --timeout=60
        }
    },
    [PSCustomObject]@{
        Id                   = 10
        Category             = "Development Servers & Processes"
        Name                 = "Build Production Frontend Assets (npm run build)"
        Description          = "Runs TypeScript type checking (vue-tsc) and compiles optimized production assets with Vite."
        Requirements         = "Node.js 18+, npm"
        Impact               = "Overwrites public\build\ manifest and bundles; removes public\hot"
        Duration             = "4 - 8 seconds"
        Risk                 = "Low"
        RequiresConfirmation = $false
        Action               = {
            param($root)
            & npm run build
        }
    },

    # Category 3: Database & Migrations
    [PSCustomObject]@{
        Id                   = 11
        Category             = "Database & Migrations"
        Name                 = "Run Pending Migrations (migrate --force)"
        Description          = "Executes any unrun database migrations safely in a non-destructive manner."
        Requirements         = "PHP 8.2+, SQLite database file"
        Impact               = "Modifies SQLite schema (adds tables/columns); preserves existing records"
        Duration             = "1 - 3 seconds"
        Risk                 = "Low"
        RequiresConfirmation = $false
        Action               = {
            param($root)
            & php artisan migrate --force --no-interaction
        }
    },
    [PSCustomObject]@{
        Id                   = 12
        Category             = "Database & Migrations"
        Name                 = "Check Migration Status (migrate:status)"
        Description          = "Displays a detailed table showing all migrations and whether they have been run."
        Requirements         = "PHP 8.2+, SQLite database"
        Impact               = "Read-only inspection"
        Duration             = "< 1 second"
        Risk                 = "Low"
        RequiresConfirmation = $false
        Action               = {
            param($root)
            & php artisan migrate:status
        }
    },
    [PSCustomObject]@{
        Id                   = 13
        Category             = "Database & Migrations"
        Name                 = "Rollback Last Migration Batch (migrate:rollback)"
        Description          = "Reverts the most recent batch of database migrations. Takes a safety backup before proceeding."
        Requirements         = "PHP 8.2+, SQLite database"
        Impact               = "Reverts schema changes from last migration batch; may drop tables/columns"
        Duration             = "1 - 3 seconds"
        Risk                 = "Medium"
        RequiresConfirmation = $true
        Action               = {
            param($root)
            Write-Host "Creating safety database snapshot before rollback..." -ForegroundColor Cyan
            & powershell.exe -NoProfile -ExecutionPolicy Bypass -File (Join-Path $root "scripts\backup-database.ps1") -Trigger pre_rollback
            Write-Host "`nRolling back last migration batch..." -ForegroundColor Yellow
            & php artisan migrate:rollback --force --no-interaction
        }
    },
    [PSCustomObject]@{
        Id                   = 14
        Category             = "Database & Migrations"
        Name                 = "Wipe & Fresh Migration (migrate:fresh --seed)"
        Description          = "Drops ALL database tables, re-runs all migrations, and runs seeders. AUTOMATIC BACKUP CREATED FIRST."
        Requirements         = "PHP 8.2+, SQLite database"
        Impact               = "DESTRUCTIVE: Deletes all existing database data (safeguarded by pre-wipe backup)"
        Duration             = "2 - 5 seconds"
        Risk                 = "High"
        RequiresConfirmation = $true
        Action               = {
            param($root)
            Write-Host "Creating mandatory safety database snapshot before fresh wipe..." -ForegroundColor Cyan
            & powershell.exe -NoProfile -ExecutionPolicy Bypass -File (Join-Path $root "scripts\backup-database.ps1") -Trigger pre_fresh_wipe
            Write-Host "`nWiping database and running fresh migrations..." -ForegroundColor Red
            & php artisan migrate:fresh --seed --force --no-interaction
        }
    },

    # Category 4: Backup & Restore
    [PSCustomObject]@{
        Id                   = 15
        Category             = "Backup & Restore"
        Name                 = "Create Verified Database Backup"
        Description          = "Creates an authenticated AES-256 encrypted SQLite backup with secondary mirror replication."
        Requirements         = "PHP 8.2+, SQLite database"
        Impact               = "Creates new .sqlite or .enc archive in storage\app\backups\ and mirror folder"
        Duration             = "1 - 2 seconds"
        Risk                 = "Low"
        RequiresConfirmation = $false
        Action               = {
            param($root)
            & powershell.exe -NoProfile -ExecutionPolicy Bypass -File (Join-Path $root "scripts\backup-database.ps1") -Trigger manual
        }
    },
    [PSCustomObject]@{
        Id                   = 16
        Category             = "Backup & Restore"
        Name                 = "Restore Database from Backup Archive"
        Description          = "Selects a backup archive, takes a pre-restore safety snapshot, and restores SQLite data safely."
        Requirements         = "PHP 8.2+, existing backup archives"
        Impact               = "Replaces database\database.sqlite with selected backup data (safety snapshot created first)"
        Duration             = "2 - 5 seconds"
        Risk                 = "High"
        RequiresConfirmation = $true
        Action               = {
            param($root)
            & powershell.exe -NoProfile -ExecutionPolicy Bypass -File (Join-Path $root "scripts\restore-database.ps1")
        }
    },
    [PSCustomObject]@{
        Id                   = 17
        Category             = "Backup & Restore"
        Name                 = "Decrypt Encrypted Backup Archive (ops:decrypt-backup)"
        Description          = "Prompts for an encrypted backup file (.enc) and passphrase, decrypting it to a standard .sqlite database."
        Requirements         = "PHP 8.2+, encrypted backup file"
        Impact               = "Outputs unencrypted SQLite file to specified destination"
        Duration             = "1 - 3 seconds"
        Risk                 = "Low"
        RequiresConfirmation = $false
        Action               = {
            param($root)
            $backupDir = Join-Path $root "storage\app\backups"
            $encFiles = Get-ChildItem -Path $backupDir -Filter "*.enc" -ErrorAction SilentlyContinue
            if (-not $encFiles -or $encFiles.Count -eq 0) {
                Write-Host "No encrypted (.enc) backup files found in $backupDir" -ForegroundColor Yellow
                return
            }
            Write-Host "Available Encrypted Backups:" -ForegroundColor Cyan
            for ($i = 0; $i -lt $encFiles.Count; $i++) {
                Write-Host ("  [{0}] {1} ({2:N2} KB)" -f ($i + 1), $encFiles[$i].Name, ($encFiles[$i].Length / 1KB))
            }
            $sel = Read-Host "`nEnter selection number"
            if ($sel -match '^\d+$' -and [int]$sel -ge 1 -and [int]$sel -le $encFiles.Count) {
                $targetFile = $encFiles[[int]$sel - 1].FullName
                $defaultOut = Join-Path $backupDir ("decrypted-" + $encFiles[[int]$sel - 1].BaseName)
                $outPath = Read-Host "Enter output path [Default: $defaultOut]"
                if (-not $outPath) { $outPath = $defaultOut }
                & php artisan ops:decrypt-backup "$targetFile" "$outPath"
            } else {
                Write-Host "Invalid selection." -ForegroundColor Red
            }
        }
    },

    # Category 5: Diagnostics & Health Checks
    [PSCustomObject]@{
        Id                   = 18
        Category             = "Diagnostics & Health Checks"
        Name                 = "Run Complete System Diagnostics"
        Description          = "Audits runtimes (PHP, Node, Composer), .env, database integrity, permissions, ports, and logs."
        Requirements         = "None"
        Impact               = "Read-only system audit and health score report"
        Duration             = "1 - 3 seconds"
        Risk                 = "Low"
        RequiresConfirmation = $false
        Action               = {
            param($root)
            & powershell.exe -NoProfile -ExecutionPolicy Bypass -File (Join-Path $root "scripts\run-diagnostics.ps1")
        }
    },
    [PSCustomObject]@{
        Id                   = 19
        Category             = "Diagnostics & Health Checks"
        Name                 = "Run Automated Test Suite (Pest / Feature Tests)"
        Description          = "Executes all unit and feature tests with compact timing and failure summaries."
        Requirements         = "PHP 8.2+, SQLite database, Composer"
        Impact               = "Runs test suite against test database"
        Duration             = "10 - 25 seconds"
        Risk                 = "Low"
        RequiresConfirmation = $false
        Action               = {
            param($root)
            & powershell.exe -NoProfile -ExecutionPolicy Bypass -File (Join-Path $root "scripts\run-tests.ps1")
        }
    },
    [PSCustomObject]@{
        Id                   = 20
        Category             = "Diagnostics & Health Checks"
        Name                 = "Probe Live Server Endpoints (Integration Probe)"
        Description          = "Spawns a temporary test server and verifies HTTP 200 responses for /login and security rules."
        Requirements         = "PHP 8.2+"
        Impact               = "Temporary server probe; read-only verification"
        Duration             = "3 - 6 seconds"
        Risk                 = "Low"
        RequiresConfirmation = $false
        Action               = {
            param($root)
            $probeScript = Join-Path $root "tests\test_live_server.ps1"
            if (Test-Path $probeScript) {
                & powershell.exe -NoProfile -ExecutionPolicy Bypass -File $probeScript
            } else {
                Write-Host "Probe script not found at $probeScript" -ForegroundColor Red
            }
        }
    },
    [PSCustomObject]@{
        Id                   = 21
        Category             = "Diagnostics & Health Checks"
        Name                 = "View Recent Application & Worker Logs"
        Description          = "Displays recent tail lines of laravel.log, scheduler logs, queue logs, and server logs."
        Requirements         = "None"
        Impact               = "Read-only log viewer"
        Duration             = "< 1 second"
        Risk                 = "Low"
        RequiresConfirmation = $false
        Action               = {
            param($root)
            & powershell.exe -NoProfile -ExecutionPolicy Bypass -File (Join-Path $root "scripts\view-logs.ps1") -Lines 25
        }
    },

    # Category 6: Maintenance & Cleanup
    [PSCustomObject]@{
        Id                   = 22
        Category             = "Maintenance & Cleanup"
        Name                 = "Clear Application & Framework Caches"
        Description          = "Clears compiled configuration, routes, Blade views, and application cache."
        Requirements         = "PHP 8.2+"
        Impact               = "Clears framework cache files; prunes stale .run\ PIDs"
        Duration             = "1 - 2 seconds"
        Risk                 = "Low"
        RequiresConfirmation = $false
        Action               = {
            param($root)
            & powershell.exe -NoProfile -ExecutionPolicy Bypass -File (Join-Path $root "scripts\clean-cache.ps1")
        }
    },
    [PSCustomObject]@{
        Id                   = 23
        Category             = "Maintenance & Cleanup"
        Name                 = "Optimize Configuration & Route Caching"
        Description          = "Caches Laravel configurations and routes for faster response times."
        Requirements         = "PHP 8.2+"
        Impact               = "Generates cached bootstrap files in bootstrap\cache\"
        Duration             = "1 - 2 seconds"
        Risk                 = "Low"
        RequiresConfirmation = $false
        Action               = {
            param($root)
            & powershell.exe -NoProfile -ExecutionPolicy Bypass -File (Join-Path $root "scripts\clean-cache.ps1") -Optimize
        }
    },
    [PSCustomObject]@{
        Id                   = 24
        Category             = "Maintenance & Cleanup"
        Name                 = "Prune Expired Models & Stale Records (model:prune)"
        Description          = "Deletes expired notifications, stale logs, and records scheduled for pruning."
        Requirements         = "PHP 8.2+, SQLite database"
        Impact               = "Prunes expired records from database tables"
        Duration             = "1 - 2 seconds"
        Risk                 = "Medium"
        RequiresConfirmation = $true
        Action               = {
            param($root)
            & php artisan model:prune
        }
    },

    # Category 7: Environment & Configuration
    [PSCustomObject]@{
        Id                   = 25
        Category             = "Environment & Configuration"
        Name                 = "Enable Mobile LAN Access (0.0.0.0 Bind + Firewall Rule)"
        Description          = "Allows mobile phones on local Wi-Fi to connect; opens port 8000 in Windows Firewall."
        Requirements         = "Administrator permission (for Windows Firewall)"
        Impact               = "Adds firewall rule; binds server to 0.0.0.0; restarts background services"
        Duration             = "2 - 4 seconds"
        Risk                 = "Low"
        RequiresConfirmation = $false
        Action               = {
            param($root)
            & powershell.exe -NoProfile -ExecutionPolicy Bypass -File (Join-Path $root "scripts\enable-mobile-access.ps1")
        }
    },
    [PSCustomObject]@{
        Id                   = 26
        Category             = "Environment & Configuration"
        Name                 = "Disable Mobile LAN Access (Revert to 127.0.0.1)"
        Description          = "Reverts server binding to localhost (127.0.0.1) only and disables the firewall rule."
        Requirements         = "None"
        Impact               = "Blocks external LAN connections; isolates server to this machine"
        Duration             = "1 - 2 seconds"
        Risk                 = "Low"
        RequiresConfirmation = $false
        Action               = {
            param($root)
            & powershell.exe -NoProfile -ExecutionPolicy Bypass -File (Join-Path $root "scripts\disable-mobile-access.ps1")
        }
    },
    [PSCustomObject]@{
        Id                   = 27
        Category             = "Environment & Configuration"
        Name                 = "Display Mobile URLs & Instant Terminal QR Code"
        Description          = "Detects local Wi-Fi IP address and displays a scannable QR code right in your console."
        Requirements         = "PHP 8.2+"
        Impact               = "Read-only network display"
        Duration             = "< 1 second"
        Risk                 = "Low"
        RequiresConfirmation = $false
        Action               = {
            param($root)
            & php artisan ops:mobile
        }
    },
    [PSCustomObject]@{
        Id                   = 28
        Category             = "Environment & Configuration"
        Name                 = "Install Task Scheduler Logon Auto-Start"
        Description          = "Registers a Windows Task Scheduler task to automatically launch background services when you log in."
        Requirements         = "Windows PowerShell 5.1+"
        Impact               = "Registers scheduled task 'PersonalOperationsHub-Background'"
        Duration             = "1 - 2 seconds"
        Risk                 = "Low"
        RequiresConfirmation = $false
        Action               = {
            param($root)
            & powershell.exe -NoProfile -ExecutionPolicy Bypass -File (Join-Path $root "scripts\install-task-scheduler.ps1") -RunNow
        }
    },
    [PSCustomObject]@{
        Id                   = 29
        Category             = "Environment & Configuration"
        Name                 = "Remove Task Scheduler Logon Auto-Start"
        Description          = "Unregisters the Windows Task Scheduler background startup task."
        Requirements         = "Windows PowerShell 5.1+"
        Impact               = "Removes scheduled task 'PersonalOperationsHub-Background'"
        Duration             = "< 1 second"
        Risk                 = "Low"
        RequiresConfirmation = $false
        Action               = {
            param($root)
            & powershell.exe -NoProfile -ExecutionPolicy Bypass -File (Join-Path $root "scripts\uninstall-task-scheduler.ps1")
        }
    },
    [PSCustomObject]@{
        Id                   = 30
        Category             = "Environment & Configuration"
        Name                 = "Launch Windows System Tray Companion"
        Description          = "Starts the native Windows system tray companion app with live operational badge counts and quick capture."
        Requirements         = "Windows PowerShell 5.1+, Windows.Forms"
        Impact               = "Spawns background tray app; displays task/habit badges in Windows taskbar"
        Duration             = "1 - 2 seconds"
        Risk                 = "Low"
        RequiresConfirmation = $false
        Action               = {
            param($root)
            Start-Process powershell.exe -ArgumentList "-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$root\scripts\tray-companion.ps1`""
            Write-Host "Windows System Tray Companion started! Look for the indigo badge in your taskbar." -ForegroundColor Green
        }
    },
    [PSCustomObject]@{
        Id                   = 31
        Category             = "Environment & Configuration"
        Name                 = "Send Test Windows Toast Notification"
        Description          = "Dispatches a native Windows 10/11 Action Center toast notification using WinRT."
        Requirements         = "Windows 10/11"
        Impact               = "Triggers Windows Action Center popup toast"
        Duration             = "< 1 second"
        Risk                 = "Low"
        RequiresConfirmation = $false
        Action               = {
            param($root)
            & powershell.exe -NoProfile -ExecutionPolicy Bypass -File (Join-Path $root "scripts\send-windows-toast.ps1") `
                -Title "Personal Operations Hub" `
                -Message "Unified Controller test toast notification succeeded! Your desktop alert pipeline is healthy." `
                -Category "System Diagnostics"
            Write-Host "Dispatched test toast to Windows Action Center." -ForegroundColor Green
        }
    },

    # Category 8: Project Information & Utilities
    [PSCustomObject]@{
        Id                   = 32
        Category             = "Project Information & Utilities"
        Name                 = "Show Operational Summary (ops:status CLI)"
        Description          = "Prints a live summary of tasks due today, active habits, unread alerts, and backup health."
        Requirements         = "PHP 8.2+, SQLite database"
        Impact               = "Read-only operational query"
        Duration             = "< 1 second"
        Risk                 = "Low"
        RequiresConfirmation = $false
        Action               = {
            param($root)
            & php artisan ops:status
        }
    },
    [PSCustomObject]@{
        Id                   = 33
        Category             = "Project Information & Utilities"
        Name                 = "Quick Add Task via Shorthand CLI (ops:quick-add)"
        Description          = "Captures tasks quickly using natural language tokens (e.g. !high, @tomorrow, ~45m, #Project)."
        Requirements         = "PHP 8.2+"
        Impact               = "Creates new task in database"
        Duration             = "Interactive"
        Risk                 = "Low"
        RequiresConfirmation = $false
        Action               = {
            param($root)
            Write-Host "Examples of shorthand syntax:" -ForegroundColor Gray
            Write-Host "  Review quarterly budget !high @tomorrow ~45m #Finance" -ForegroundColor Cyan
            Write-Host "  Call contractor !urgent @friday #Home +Renovations" -ForegroundColor Cyan
            Write-Host ""
            $inputTask = Read-Host "Enter task description with shorthand tokens"
            if ($inputTask) {
                & php artisan ops:quick-add "$inputTask"
            } else {
                Write-Host "No input provided." -ForegroundColor Gray
            }
        }
    },
    [PSCustomObject]@{
        Id                   = 34
        Category             = "Project Information & Utilities"
        Name                 = "Re-generate Standard PWA Icons (PNG)"
        Description          = "Synthesizes pixel-perfect 192x192, 512x512, and maskable PNG icons using PHP GD."
        Requirements         = "PHP 8.2+ with GD extension"
        Impact               = "Overwrites public\icon-192.png, public\icon-512.png, public\icon-maskable-512.png"
        Duration             = "< 1 second"
        Risk                 = "Low"
        RequiresConfirmation = $false
        Action               = {
            param($root)
            & php (Join-Path $root "scripts\generate-pwa-icons.php")
        }
    }
)

function Show-HelpScreen {
    Show-Header "Help & Controller Documentation"
    Write-Host "OVERVIEW:" -ForegroundColor Yellow
    Write-Host "  The Unified Controller (controller.ps1) is the central orchestration tool"
    Write-Host "  for Personal Operations Hub. It manages development servers, background"
    Write-Host "  daemons, database migrations, backups, mobile LAN access, and health checks."
    Write-Host ""
    Write-Host "HOW TO USE:" -ForegroundColor Yellow
    Write-Host "  - Enter any operation number (1 - $($Operations.Count)) to run that task."
    Write-Host "  - Type 'C' followed by a category number (e.g. 'C1', 'C3') to filter the menu."
    Write-Host "  - Type 'ALL' to reset category filters and display all operations."
    Write-Host "  - Type 'H' to view this help documentation at any time."
    Write-Host "  - Type 'Q' to exit safely."
    Write-Host ""
    Write-Host "SAFETY & CONFIRMATION SAFEGUARDS:" -ForegroundColor Yellow
    Write-Host "  - Operations marked HIGH or MEDIUM risk require explicit confirmation."
    Write-Host "  - Database rollbacks and wipes create automatic pre-execution snapshots."
    Write-Host "  - Process termination only targets verified POH processes tracked by PID."
    Write-Host ""
    Write-Host "KEY FILE LOCATIONS:" -ForegroundColor Yellow
    Write-Host "  - Project Root:     $ProjectRoot"
    Write-Host "  - Modular Scripts:  $ProjectRoot\scripts\"
    Write-Host "  - Database Backups: $ProjectRoot\storage\app\backups\"
    Write-Host "  - Background Logs:  $ProjectRoot\storage\logs\background\"
    Write-Host "  - Runtime PIDs:     $ProjectRoot\.run\"
    Write-Host ""
    Write-Host "Press Enter to return to the main menu..." -ForegroundColor Cyan
    Read-Host | Out-Null
}

function Show-OperationCard {
    param($Op)
    $riskInfo = Format-Risk $Op.Risk

    Write-Host "==============================================================================" -ForegroundColor DarkCyan
    Write-Host (" OPERATION [{0}]: {1}" -f $Op.Id, $Op.Name) -ForegroundColor White -BackgroundColor DarkBlue
    Write-Host "==============================================================================" -ForegroundColor DarkCyan
    Write-Host " Description:  " -NoNewline -ForegroundColor Gray
    Write-Host $Op.Description -ForegroundColor White
    Write-Host " Category:     " -NoNewline -ForegroundColor Gray
    Write-Host $Op.Category -ForegroundColor Cyan
    Write-Host " Requirements: " -NoNewline -ForegroundColor Gray
    Write-Host $Op.Requirements -ForegroundColor White
    Write-Host " Impact:       " -NoNewline -ForegroundColor Gray
    Write-Host $Op.Impact -ForegroundColor Yellow
    Write-Host " Est Duration: " -NoNewline -ForegroundColor Gray
    Write-Host $Op.Duration -ForegroundColor White
    Write-Host " Risk Level:   " -NoNewline -ForegroundColor Gray
    Write-Host $riskInfo.Text -ForegroundColor $riskInfo.Color
    Write-Host " Confirmation: " -NoNewline -ForegroundColor Gray
    $reqConfText = if ($Op.RequiresConfirmation) { "REQUIRED (Confirm before execution)" } else { "Not Required" }
    $reqConfColor = if ($Op.RequiresConfirmation) { "Yellow" } else { "Green" }
    Write-Host $reqConfText -ForegroundColor $reqConfColor
    Write-Host "==============================================================================" -ForegroundColor DarkCyan
    Write-Host ""
}

function Execute-Operation {
    param($Op)
    Show-Header "Executing: $($Op.Name)"
    Show-OperationCard $Op

    if ($Op.RequiresConfirmation) {
        Write-Host "[CONFIRMATION REQUIRED]" -ForegroundColor Yellow
        Write-Host "This operation may modify data or terminate active services." -ForegroundColor Gray
        $response = Read-Host "Type 'YES' to proceed (or press Enter to cancel)"
        if ($response -ne "YES") {
            Write-Host "`nOperation cancelled by user." -ForegroundColor Gray
            Write-Host "Press Enter to return to the menu..." -ForegroundColor Cyan
            Read-Host | Out-Null
            return
        }
    }

    Write-Host "Executing operation..." -ForegroundColor Cyan
    Show-Separator

    $sw = [System.Diagnostics.Stopwatch]::StartNew()
    $exitCode = 0

    try {
        & $Op.Action $ProjectRoot
        $exitCode = if ($LASTEXITCODE -ne $null) { $LASTEXITCODE } else { 0 }
    } catch {
        Write-Host "`n[ERROR] An exception occurred while executing the operation:" -ForegroundColor Red
        Write-Host $_.Exception.Message -ForegroundColor Red
        $exitCode = 1
    } finally {
        $sw.Stop()
    }

    Show-Separator
    if ($exitCode -eq 0) {
        Write-Host ("[SUCCESS] Operation finished cleanly in {0:N2}s." -f $sw.Elapsed.TotalSeconds) -ForegroundColor Green
    } else {
        Write-Host ("[WARNING] Operation completed with non-zero exit code: {0} ({1:N2}s)" -f $exitCode, $sw.Elapsed.TotalSeconds) -ForegroundColor Red
    }

    if (-not $NonInteractive) {
        Write-Host ""
        Write-Host "Press Enter to return to the main menu..." -ForegroundColor Cyan
        Read-Host | Out-Null
    }
}

# CLI Direct Actions
if ($Help) {
    Show-HelpScreen
    exit 0
}

if ($List) {
    Show-Header "All Available Operations"
    foreach ($op in $Operations) {
        $riskInfo = Format-Risk $op.Risk
        Write-Host ("[{0,2}] {1,-55} [{2}] {3}" -f $op.Id, $op.Name, $riskInfo.Text, $op.Category) -ForegroundColor Cyan
    }
    Write-Host ""
    exit 0
}

if ($Option -gt 0) {
    $targetOp = $Operations | Where-Object { $_.Id -eq $Option }
    if ($targetOp) {
        Execute-Operation $targetOp
        exit 0
    } else {
        Write-Host "[ERROR] Operation ID $Option not found. Valid IDs: 1 to $($Operations.Count)." -ForegroundColor Red
        exit 1
    }
}

# Main Interactive Loop
$ActiveFilter = if ($Category) { $Category } else { $null }

while ($true) {
    $headerSub = if ($ActiveFilter) { "Filtered by: $ActiveFilter (Type 'ALL' to reset)" } else { "Main Operations Menu" }
    Show-Header $headerSub

    $grouped = $Operations | Where-Object { -not $ActiveFilter -or $_.Category -like "*$ActiveFilter*" } | Group-Object Category

    if ($grouped.Count -eq 0) {
        Write-Host "No operations found matching filter '$ActiveFilter'." -ForegroundColor Yellow
        Write-Host "Resetting filter..."
        $ActiveFilter = $null
        Start-Sleep -Seconds 1
        continue
    }

    foreach ($group in $grouped) {
        Write-Host ("  === {0} ===" -f $group.Name.ToUpper()) -ForegroundColor DarkCyan
        foreach ($op in $group.Group) {
            $riskBadge = switch ($op.Risk) {
                "High"   { "[HIGH]" }
                "Medium" { "[MED] " }
                default  { "      " }
            }
            $riskColor = switch ($op.Risk) {
                "High"   { "Red" }
                "Medium" { "Yellow" }
                default  { "DarkGray" }
            }

            Write-Host ("  [{0,2}] " -f $op.Id) -NoNewline -ForegroundColor White
            Write-Host ("{0} " -f $riskBadge) -NoNewline -ForegroundColor $riskColor
            Write-Host ("{0}" -f $op.Name) -ForegroundColor Cyan
        }
        Write-Host ""
    }

    Show-Separator
    Write-Host "Navigation Shortcuts:" -ForegroundColor Gray
    Write-Host "  [1 - $($Operations.Count)] Select Operation   |   [H] Help & Docs   |   [C1-C8] Filter Category   |   [Q] Quit" -ForegroundColor DarkGray
    Write-Host ""

    $rawInput = Read-Host "Enter your selection"
    if ($rawInput -eq $null) {
        break
    }
    $selection = $rawInput.Trim()
    if (-not $selection) {
        continue
    }

    if ($selection -match '^(q|quit|exit)$') {
        Show-Header "Exiting Controller"
        Write-Host "Thank you for using Personal Operations Hub Unified Controller." -ForegroundColor Green
        Write-Host "Working directory: $ProjectRoot" -ForegroundColor Gray
        Write-Host "Have a productive day!`n" -ForegroundColor Green
        break
    }

    if ($selection -match '^(h|help|\?)$') {
        Show-HelpScreen
        continue
    }

    if ($selection -match '^c([1-8])$') {
        $catNum = $Matches[1]
        $catMap = @{
            "1" = "Startup"
            "2" = "Development"
            "3" = "Database"
            "4" = "Backup"
            "5" = "Diagnostics"
            "6" = "Maintenance"
            "7" = "Environment"
            "8" = "Project"
        }
        $ActiveFilter = $catMap[$catNum]
        continue
    }

    if ($selection -match '^(all|clear|reset)$') {
        $ActiveFilter = $null
        continue
    }

    if ($selection -match '^\d+$') {
        $opId = [int]$selection
        $chosenOp = $Operations | Where-Object { $_.Id -eq $opId }
        if ($chosenOp) {
            Execute-Operation $chosenOp
        } else {
            Write-Host "`nInvalid selection: No operation with number $opId. Press Enter to continue..." -ForegroundColor Red
            Read-Host | Out-Null
        }
        continue
    }

    Write-Host "`nUnrecognized command: '$selection'. Press Enter to continue..." -ForegroundColor Yellow
    Read-Host | Out-Null
}
