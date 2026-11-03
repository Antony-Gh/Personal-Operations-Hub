# Personal Operations Hub - Stop Services
$ErrorActionPreference = "Stop"

Set-Location $PSScriptRoot

Write-Host "===================================================" -ForegroundColor Cyan
Write-Host "    Personal Operations Hub - Stop Services" -ForegroundColor Cyan
Write-Host "===================================================" -ForegroundColor Cyan
Write-Host ""

$runDir = Join-Path $PSScriptRoot ".run"
$stoppedPids = 0

# 1. Stop Tracked Laravel Service
$laravelPidFile = Join-Path $runDir "laravel.pid"
if (Test-Path $laravelPidFile) {
    $pidRaw = (Get-Content $laravelPidFile -ErrorAction SilentlyContinue | Out-String).Trim()
    if ($pidRaw -match '^\d+$') {
        $pidNum = [int]$pidRaw
        Write-Host "Terminating tracked Laravel backend (PID: $pidNum)..." -ForegroundColor Yellow
        cmd.exe /c "taskkill /F /T /PID $pidNum >nul 2>&1"
        $stoppedPids++
    }
    Remove-Item $laravelPidFile -Force -ErrorAction SilentlyContinue
}

# 2. Stop Tracked Vite Service
$vitePidFile = Join-Path $runDir "vite.pid"
if (Test-Path $vitePidFile) {
    $pidRaw = (Get-Content $vitePidFile -ErrorAction SilentlyContinue | Out-String).Trim()
    if ($pidRaw -match '^\d+$') {
        $pidNum = [int]$pidRaw
        Write-Host "Terminating tracked Vite compiler (PID: $pidNum and children)..." -ForegroundColor Yellow
        cmd.exe /c "taskkill /F /T /PID $pidNum >nul 2>&1"
        $stoppedPids++
    }
    Remove-Item $vitePidFile -Force -ErrorAction SilentlyContinue
}

if ($stoppedPids -gt 0) {
    Write-Host "Tracked services terminated cleanly." -ForegroundColor Green
} else {
    Write-Host "No tracked PID files found in .run\" -ForegroundColor Yellow
    Write-Host "Checking if default development ports (8000, 5173) are still bound by orphaned processes..." -ForegroundColor Gray

    $orphanedPorts = @(8000, 5173)
    foreach ($port in $orphanedPorts) {
        $conns = Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue
        if ($conns) {
            foreach ($conn in $conns) {
                $p = $conn.OwningProcess
                Write-Host "Terminating orphaned process on port $port (PID: $p)..." -ForegroundColor Yellow
                cmd.exe /c "taskkill /F /T /PID $p >nul 2>&1"
            }
        }
    }
    Write-Host "Port cleanup complete." -ForegroundColor Green
}

# Clean empty .run dir if empty
if (Test-Path $runDir) {
    $remaining = Get-ChildItem $runDir
    if (-not $remaining) {
        Remove-Item $runDir -Force -ErrorAction SilentlyContinue
    }
}
