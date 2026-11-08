param(
    [switch]$Optimize,
    [switch]$Force
)

# Personal Operations Hub - Cache Cleaning & Maintenance Utility (PowerShell 5.1 Compatible)
$ErrorActionPreference = "Stop"

$ProjectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $ProjectRoot

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "    Personal Operations Hub - Cache & Maintenance Cleanup  " -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host ""

Write-Host "1. Clearing Laravel Application & Framework Caches..." -ForegroundColor Yellow
& php artisan cache:clear --no-interaction
& php artisan config:clear --no-interaction
& php artisan route:clear --no-interaction
& php artisan view:clear --no-interaction

Write-Host "`n2. Cleaning Stale Runtime State..." -ForegroundColor Yellow
$runDir = Join-Path $ProjectRoot ".run"
$cleanedPids = 0

if (Test-Path $runDir) {
    $pidFiles = Get-ChildItem -Path $runDir -Filter "*.pid" -ErrorAction SilentlyContinue
    foreach ($pf in $pidFiles) {
        $raw = (Get-Content $pf.FullName -ErrorAction SilentlyContinue | Out-String).Trim()
        $alive = $false
        if ($raw -match '^\d+$') {
            $p = Get-Process -Id ([int]$raw) -ErrorAction SilentlyContinue
            if ($p) { $alive = $true }
        }
        if (-not $alive) {
            Remove-Item $pf.FullName -Force -ErrorAction SilentlyContinue
            $cleanedPids++
        }
    }

    $bgJson = Join-Path $runDir "background-pids.json"
    if (Test-Path $bgJson) {
        try {
            $parsed = Get-Content $bgJson -Raw -ErrorAction Stop | ConvertFrom-Json
            $anyAlive = $false
            foreach ($prop in @("server", "scheduler", "queue")) {
                if ($parsed.$prop) {
                    $p = Get-Process -Id ([int]$parsed.$prop) -ErrorAction SilentlyContinue
                    if ($p) { $anyAlive = $true }
                }
            }
            if (-not $anyAlive) {
                Remove-Item $bgJson -Force -ErrorAction SilentlyContinue
            }
        } catch {
            Remove-Item $bgJson -Force -ErrorAction SilentlyContinue
        }
    }
}
Write-Host "  Cleared $cleanedPids stale PID entry/entries from .run\" -ForegroundColor Green

# Remove Vite hot reload file if compiler is inactive
$hotFile = Join-Path $ProjectRoot "public\hot"
if (Test-Path $hotFile) {
    $viteListening = Get-NetTCPConnection -LocalPort 5173 -State Listen -ErrorAction SilentlyContinue
    if (-not $viteListening) {
        Remove-Item $hotFile -Force -ErrorAction SilentlyContinue
        Write-Host "  Removed inactive public\hot Vite file." -ForegroundColor Green
    }
}

if ($Optimize) {
    Write-Host "`n3. Optimizing Configuration & Route Cache..." -ForegroundColor Yellow
    & php artisan optimize --no-interaction
}

Write-Host ""
Write-Host "[SUCCESS] All caches cleared and maintenance operations finished." -ForegroundColor Green

