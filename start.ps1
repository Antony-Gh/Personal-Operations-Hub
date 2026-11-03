# Personal Operations Hub - PowerShell Launcher
$ErrorActionPreference = "Stop"

Set-Location $PSScriptRoot

Write-Host "===================================================" -ForegroundColor Cyan
Write-Host "    Personal Operations Hub - PowerShell Launcher" -ForegroundColor Cyan
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

if (-not (Test-Path "database\database.sqlite")) {
    Write-Host "      Initializing SQLite database file..." -ForegroundColor Cyan
    New-Item -ItemType File -Path "database\database.sqlite" -Force | Out-Null
}

if (-not (Test-Path "vendor\autoload.php")) {
    Write-Host "[ERROR] PHP dependencies are missing. Run: composer install" -ForegroundColor Red
    exit 1
}

if (-not (Test-Path "node_modules")) {
    Write-Host "[ERROR] Node dependencies are missing. Run: npm install" -ForegroundColor Red
    exit 1
}

# 3. Safe Migrations
Write-Host "[3/6] Running safe database migrations..." -ForegroundColor Yellow
php artisan migrate --force

# 4. Check Ports and Start Services
Write-Host "[4/6] Checking server ports and launching services..." -ForegroundColor Yellow
$appUrl = "http://127.0.0.1:8000"

$port8000Active = Get-NetTCPConnection -LocalPort 8000 -State Listen -ErrorAction SilentlyContinue
if ($port8000Active) {
    Write-Host "      Laravel backend already running on port 8000." -ForegroundColor Green
} else {
    Write-Host "      Starting Laravel backend server..." -ForegroundColor Cyan
    Start-Process -FilePath "php" -ArgumentList "artisan", "serve", "--host=127.0.0.1", "--port=8000" -WindowStyle Minimized
}

$port5173Active = Get-NetTCPConnection -LocalPort 5173 -State Listen -ErrorAction SilentlyContinue
if ($port5173Active) {
    Write-Host "      Vite dev server already running on port 5173." -ForegroundColor Green
} else {
    Write-Host "      Starting Vite frontend server..." -ForegroundColor Cyan
    Start-Process -FilePath "cmd.exe" -ArgumentList "/c", "npm", "run", "dev" -WindowStyle Minimized
}

# 5. Wait for Readiness
Write-Host "[5/6] Waiting for application to respond..." -ForegroundColor Yellow
$ready = $false
$attempts = 0

while (-not $ready -and $attempts -lt 25) {
    Start-Sleep -Seconds 1
    $attempts++
    try {
        $response = Invoke-WebRequest -Uri $appUrl -UseBasicParsing -TimeoutSec 2 -ErrorAction SilentlyContinue
        if ($response.StatusCode -lt 500) {
            $ready = $true
        }
    } catch {
        # Retry until server is listening
    }
}

# 6. Open Browser
Write-Host "[6/6] Application is ready!" -ForegroundColor Green
Write-Host ""
Write-Host "===================================================" -ForegroundColor Cyan
Write-Host "  URL: $appUrl" -ForegroundColor White
Write-Host "===================================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Opening default web browser..." -ForegroundColor Green
Start-Process $appUrl

Write-Host "Services are running." -ForegroundColor Cyan
Write-Host "Run .\stop.ps1 or stop.bat when you wish to terminate the background processes." -ForegroundColor Gray

