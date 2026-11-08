param(
    [switch]$Status,
    [switch]$Backup,
    [switch]$Logs,
    [switch]$NoBrowser
)

# Personal Operations Hub - Interactive Development Launcher (PowerShell 5.1 Compatible)
$ErrorActionPreference = "Stop"

$ProjectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $ProjectRoot

$runDir = Join-Path $ProjectRoot ".run"
$bgLogDir = Join-Path $ProjectRoot "storage\logs\background"

if ($Status) {
    $bgScript = Join-Path $PSScriptRoot "start-background.ps1"
    if (Test-Path $bgScript) {
        & powershell -NoProfile -ExecutionPolicy Bypass -File $bgScript -Status
    }
    $vitePidFile = Join-Path $runDir "vite.pid"
    $viteAlive = $false
    if (Test-Path $vitePidFile) {
        $vRaw = (Get-Content $vitePidFile -ErrorAction SilentlyContinue | Out-String).Trim()
        if ($vRaw -match '^\d+$') {
            $vProc = Get-Process -Id ([int]$vRaw) -ErrorAction SilentlyContinue
            if ($vProc -and ($vProc.ProcessName -match '^(node|cmd|npm)$')) {
                Write-Host "  [RUNNING] Vite Dev Compiler (http://127.0.0.1:5173) - PID $($vProc.Id)" -ForegroundColor Green
                $viteAlive = $true
            }
        }
    }
    if (-not $viteAlive) {
        Write-Host "  [STOPPED] Vite Dev Compiler (http://127.0.0.1:5173)" -ForegroundColor Yellow
    }
    exit 0
}

if ($Backup) {
    Write-Host "Creating verified SQLite database backup..." -ForegroundColor Cyan
    & php artisan ops:backup-database --trigger=manual --no-interaction
    exit $LASTEXITCODE
}

if ($Logs) {
    Write-Host "===================================================" -ForegroundColor Cyan
    Write-Host "    Personal Operations Hub - Recent Logs" -ForegroundColor Cyan
    Write-Host "===================================================" -ForegroundColor Cyan
    $laravelLog = Join-Path $ProjectRoot "storage\logs\laravel.log"
    if (Test-Path $laravelLog) {
        Write-Host "`n--- storage\logs\laravel.log (last 25 lines) ---" -ForegroundColor Yellow
        Get-Content $laravelLog -Tail 25
    } else {
        Write-Host "`nNo storage\logs\laravel.log file yet." -ForegroundColor Gray
    }
    if (Test-Path $bgLogDir) {
        $logFiles = Get-ChildItem -Path $bgLogDir -Filter "*.log" -ErrorAction SilentlyContinue
        foreach ($lf in $logFiles) {
            if ($lf.Length -gt 0) {
                Write-Host "`n--- $($lf.Name) (last 15 lines) ---" -ForegroundColor Yellow
                Get-Content $lf.FullName -Tail 15
            }
        }
    }
    exit 0
}

Write-Host "===================================================" -ForegroundColor Cyan
Write-Host "    Personal Operations Hub - System Launcher" -ForegroundColor Cyan
Write-Host "===================================================" -ForegroundColor Cyan
Write-Host ""

# 1. Check Prerequisites
Write-Host "[1/6] Checking system prerequisites..." -ForegroundColor Yellow

$tools = @("php", "composer", "node", "npm")
foreach ($tool in $tools) {
    if (-not (Get-Command $tool -ErrorAction SilentlyContinue)) {
        Write-Host "[ERROR] $tool is not found in your PATH." -ForegroundColor Red
        Write-Host "Please ensure PHP 8.2+, Composer, Node.js 18+, and npm are installed."
        exit 1
    }
}
Write-Host "      All CLI prerequisites detected." -ForegroundColor Green

# 2. Check Environment & Dependencies
Write-Host "[2/6] Verifying project environment and dependencies..." -ForegroundColor Yellow

if (-not (Test-Path ".env")) {
    Write-Host "      .env file not found. Creating from .env.example..." -ForegroundColor Cyan
    Copy-Item ".env.example" ".env"
    php artisan key:generate --no-interaction
}

if (-not (Test-Path "database")) {
    New-Item -ItemType Directory -Path "database" -Force | Out-Null
}

if (-not (Test-Path "database\database.sqlite")) {
    Write-Host "      Initializing SQLite database file..." -ForegroundColor Cyan
    New-Item -ItemType File -Path "database\database.sqlite" -Force | Out-Null
} else {
    Copy-Item "database\database.sqlite" "database\database.sqlite.bak" -Force
    Write-Host "      Safety backup created at database\database.sqlite.bak" -ForegroundColor Gray
}

if (-not (Test-Path "vendor\autoload.php")) {
    Write-Host "[ERROR] PHP dependencies are missing. Run: composer install" -ForegroundColor Red
    exit 1
}

if (-not (Test-Path "node_modules")) {
    Write-Host "[ERROR] Node dependencies are missing. Run: npm install" -ForegroundColor Red
    exit 1
}

# 3. Safe Non-Destructive Migrations
Write-Host "[3/6] Running safe database migrations..." -ForegroundColor Yellow
php artisan migrate --force --no-interaction

# 4. Spawning Services with PID Tracking
Write-Host "[4/6] Spawning services (HTTP, Vite, Scheduler & Queue) with PID tracking..." -ForegroundColor Yellow

if (-not (Test-Path $runDir)) {
    New-Item -ItemType Directory -Path $runDir -Force | Out-Null
}
if (-not (Test-Path $bgLogDir)) {
    New-Item -ItemType Directory -Path $bgLogDir -Force | Out-Null
}

# Clean up any stale PID files from previous crashes/reboots
$trackedPidFiles = @("laravel.pid", "vite.pid", "scheduler.pid", "queue.pid")
foreach ($file in $trackedPidFiles) {
    $filePath = Join-Path $runDir $file
    if (Test-Path $filePath) {
        $pRaw = (Get-Content $filePath -ErrorAction SilentlyContinue | Out-String).Trim()
        $proc = if ($pRaw -match '^\d+$') { Get-Process -Id ([int]$pRaw) -ErrorAction SilentlyContinue } else { $null }
        if (-not $proc) {
            Remove-Item $filePath -Force -ErrorAction SilentlyContinue
        }
    }
}

$appUrl = "http://127.0.0.1:8000"
$viteUrl = "http://127.0.0.1:5173"
$phpExe = (Get-Command "php").Source

# Check if port 8000 is listening
$port8000InUse = Get-NetTCPConnection -LocalPort 8000 -State Listen -ErrorAction SilentlyContinue
if ($port8000InUse) {
    Write-Host "      Port 8000 already has a service listening." -ForegroundColor Yellow
} else {
    Write-Host "      Starting Laravel backend (port 8000)..." -ForegroundColor Cyan
    $laravelProc = Start-Process -FilePath $phpExe -ArgumentList "artisan serve --host=127.0.0.1 --port=8000 --no-interaction" -WorkingDirectory $ProjectRoot -PassThru
    $laravelProc.Id | Out-File -FilePath "$runDir\laravel.pid" -Encoding ascii
    Write-Host "      Laravel started (PID: $($laravelProc.Id))." -ForegroundColor Green
}

# Check if port 5173 is listening
$port5173InUse = Get-NetTCPConnection -LocalPort 5173 -State Listen -ErrorAction SilentlyContinue
if ($port5173InUse) {
    Write-Host "      Port 5173 already has a service listening." -ForegroundColor Yellow
} else {
    Write-Host "      Starting Vite dev compiler (port 5173)..." -ForegroundColor Cyan
    $npmCmd = "npm"
    $foundNpm = Get-Command "npm.cmd" -ErrorAction SilentlyContinue
    if ($foundNpm) {
        $npmCmd = $foundNpm.Source
    }
    $viteProc = Start-Process -FilePath $npmCmd -ArgumentList "run dev" -WorkingDirectory $ProjectRoot -PassThru
    $viteProc.Id | Out-File -FilePath "$runDir\vite.pid" -Encoding ascii
    Write-Host "      Vite started (PID: $($viteProc.Id))." -ForegroundColor Green
}

# Start Laravel Scheduler (schedule:work) if not already running
$schedRunning = $false
$schedPidFile = Join-Path $runDir "scheduler.pid"
if (Test-Path $schedPidFile) {
    $sRaw = (Get-Content $schedPidFile -ErrorAction SilentlyContinue | Out-String).Trim()
    if ($sRaw -match '^\d+$') {
        $sProc = Get-Process -Id ([int]$sRaw) -ErrorAction SilentlyContinue
        if ($sProc -and ($sProc.ProcessName -match '^(php|php-cgi|cmd)$')) {
            $schedRunning = $true
            Write-Host "      Laravel Scheduler already running (PID: $($sProc.Id))." -ForegroundColor Gray
        }
    }
}
if (-not $schedRunning) {
    $schOut = Join-Path $bgLogDir "scheduler.out.log"
    $schErr = Join-Path $bgLogDir "scheduler.err.log"
    $schedProc = Start-Process -FilePath $phpExe `
        -ArgumentList "artisan schedule:work --no-interaction" `
        -WorkingDirectory $ProjectRoot `
        -WindowStyle Hidden `
        -RedirectStandardOutput $schOut `
        -RedirectStandardError $schErr `
        -PassThru
    $schedProc.Id | Out-File -FilePath $schedPidFile -Encoding ascii
    Write-Host "      Laravel Scheduler started (PID: $($schedProc.Id))." -ForegroundColor Green
}

# Start Database Queue Worker (queue:work) if not already running
$queueRunning = $false
$queuePidFile = Join-Path $runDir "queue.pid"
if (Test-Path $queuePidFile) {
    $qRaw = (Get-Content $queuePidFile -ErrorAction SilentlyContinue | Out-String).Trim()
    if ($qRaw -match '^\d+$') {
        $qProc = Get-Process -Id ([int]$qRaw) -ErrorAction SilentlyContinue
        if ($qProc -and ($qProc.ProcessName -match '^(php|php-cgi|cmd)$')) {
            $queueRunning = $true
            Write-Host "      Database Queue Worker already running (PID: $($qProc.Id))." -ForegroundColor Gray
        }
    }
}
if (-not $queueRunning) {
    $qOut = Join-Path $bgLogDir "queue.out.log"
    $qErr = Join-Path $bgLogDir "queue.err.log"
    $queueProc = Start-Process -FilePath $phpExe `
        -ArgumentList "artisan queue:work --sleep=3 --tries=3 --timeout=60 --no-interaction" `
        -WorkingDirectory $ProjectRoot `
        -WindowStyle Hidden `
        -RedirectStandardOutput $qOut `
        -RedirectStandardError $qErr `
        -PassThru
    $queueProc.Id | Out-File -FilePath $queuePidFile -Encoding ascii
    Write-Host "      Database Queue Worker started (PID: $($queueProc.Id))." -ForegroundColor Green
}

# 5. Wait for Both Laravel AND Vite Readiness
Write-Host "[5/6] Waiting for application and Vite frontend to be fully ready..." -ForegroundColor Yellow

$attempts = 0
$maxAttempts = 35
$laravelReady = $false
$viteReady = $false

while ($attempts -lt $maxAttempts -and (-not $laravelReady -or -not $viteReady)) {
    $attempts++

    if (-not $laravelReady) {
        try {
            $resp = Invoke-WebRequest -Uri $appUrl -UseBasicParsing -TimeoutSec 2 -ErrorAction Stop
            if ($resp.StatusCode -lt 500) {
                $laravelReady = $true
            }
        } catch {
            # Still warming up
        }
    }

    if (-not $viteReady) {
        try {
            $respVite = Invoke-WebRequest -Uri "$viteUrl/@vite/client" -UseBasicParsing -TimeoutSec 2 -ErrorAction Stop
            if ($respVite.StatusCode -eq 200) {
                $viteReady = $true
            }
        } catch {
            # Still compiling/starting
        }
    }

    if (-not $laravelReady -or -not $viteReady) {
        Start-Sleep -Seconds 1
    }
}

if ($laravelReady -and $viteReady) {
    Write-Host "      Laravel backend is ready (HTTP 200/302)." -ForegroundColor Green
    Write-Host "      Vite frontend compiler is ready (@vite/client active)." -ForegroundColor Green
} else {
    Write-Host "      [NOTE] Server startup timed out waiting for all checks; continuing." -ForegroundColor Yellow
}

# 6. Launch Browser
Write-Host "[6/6] Ready!" -ForegroundColor Yellow
Write-Host ""
Write-Host "===================================================" -ForegroundColor Green
Write-Host "  Personal Operations Hub is LIVE at:" -ForegroundColor Green
Write-Host "  $appUrl" -ForegroundColor White -BackgroundColor DarkBlue
Write-Host "===================================================" -ForegroundColor Green
Write-Host ""

if (-not $NoBrowser) {
    Start-Process $appUrl
}

Write-Host "Services (HTTP, Vite, Scheduler, Queue) are running with tracked PIDs in .run\" -ForegroundColor Gray
Write-Host "Helper flags: .\scripts\start.ps1 -Status | .\scripts\start.ps1 -Backup | .\scripts\start.ps1 -Logs" -ForegroundColor Gray
Write-Host "To cleanly stop services, run .\scripts\stop.ps1 or .\scripts\stop.bat" -ForegroundColor Gray
