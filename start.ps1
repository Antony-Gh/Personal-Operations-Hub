# Personal Operations Hub - PowerShell Launcher
$ErrorActionPreference = "Stop"

Set-Location $PSScriptRoot

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
    php artisan key:generate
}

if (-not (Test-Path "database")) {
    New-Item -ItemType Directory -Path "database" -Force | Out-Null
}

if (-not (Test-Path "database\database.sqlite")) {
    Write-Host "      Initializing SQLite database file..." -ForegroundColor Cyan
    New-Item -ItemType File -Path "database\database.sqlite" -Force | Out-Null
} else {
    # Create safety backup of existing database before migration
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
php artisan migrate --force

# 4. Spawning Services with PID Tracking
Write-Host "[4/6] Spawning background services with PID tracking..." -ForegroundColor Yellow

$runDir = Join-Path $PSScriptRoot ".run"
if (-not (Test-Path $runDir)) {
    New-Item -ItemType Directory -Path $runDir -Force | Out-Null
}

# Clean up any stale PID files from previous crashes/reboots
$trackedPidFiles = @("laravel.pid", "vite.pid")
foreach ($file in $trackedPidFiles) {
    $filePath = Join-Path $runDir $file
    if (Test-Path $filePath) {
        $pRaw = (Get-Content $filePath -ErrorAction SilentlyContinue | Out-String).Trim()
        $proc = if ($pRaw -match '^\d+$') { Get-Process -Id [int]$pRaw -ErrorAction SilentlyContinue } else { $null }
        if (-not $proc) {
            Remove-Item $filePath -Force -ErrorAction SilentlyContinue
        }
    }
}

$appUrl = "http://127.0.0.1:8000"
$viteUrl = "http://127.0.0.1:5173"

# Check if port 8000 is listening
$port8000InUse = Get-NetTCPConnection -LocalPort 8000 -State Listen -ErrorAction SilentlyContinue
if ($port8000InUse) {
    Write-Host "      Port 8000 already has a service listening." -ForegroundColor Yellow
} else {
    Write-Host "      Starting Laravel backend (port 8000)..." -ForegroundColor Cyan
    $laravelProc = Start-Process -FilePath "php" -ArgumentList "artisan serve --host=127.0.0.1 --port=8000" -WorkingDirectory $PSScriptRoot -PassThru
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
    $viteProc = Start-Process -FilePath $npmCmd -ArgumentList "run dev" -WorkingDirectory $PSScriptRoot -PassThru
    $viteProc.Id | Out-File -FilePath "$runDir\vite.pid" -Encoding ascii
    Write-Host "      Vite started (PID: $($viteProc.Id))." -ForegroundColor Green
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
            # Check Vite client endpoint
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
    Write-Host "      [NOTE] Server startup timed out waiting for all checks; opening browser anyway." -ForegroundColor Yellow
}

# 6. Launch Browser
Write-Host "[6/6] Launching browser..." -ForegroundColor Yellow
Write-Host ""
Write-Host "===================================================" -ForegroundColor Green
Write-Host "  Personal Operations Hub is LIVE at:" -ForegroundColor Green
Write-Host "  $appUrl" -ForegroundColor White -BackgroundColor DarkBlue
Write-Host "===================================================" -ForegroundColor Green
Write-Host ""

Start-Process $appUrl

Write-Host "Services are running with tracked PIDs in .run\" -ForegroundColor Gray
Write-Host "To cleanly stop services, run .\stop.ps1 or stop.bat" -ForegroundColor Gray
