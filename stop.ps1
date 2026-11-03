# Personal Operations Hub - Safe Service Stopper
$ErrorActionPreference = "Stop"

Set-Location $PSScriptRoot

Write-Host "===================================================" -ForegroundColor Cyan
Write-Host "    Personal Operations Hub - Stop Services" -ForegroundColor Cyan
Write-Host "===================================================" -ForegroundColor Cyan
Write-Host ""

$runDir = Join-Path $PSScriptRoot ".run"
$stoppedPids = 0

# 1. Stop Tracked Laravel Service with Identity Verification
$laravelPidFile = Join-Path $runDir "laravel.pid"
if (Test-Path $laravelPidFile) {
    $pidRaw = (Get-Content $laravelPidFile -ErrorAction SilentlyContinue | Out-String).Trim()
    if ($pidRaw -match '^\d+$') {
        $pidNum = [int]$pidRaw
        $proc = Get-Process -Id $pidNum -ErrorAction SilentlyContinue
        if ($proc -and ($proc.ProcessName -match '^(php|cmd)$')) {
            Write-Host "Terminating tracked Laravel backend (PID: $pidNum, Process: $($proc.ProcessName))..." -ForegroundColor Yellow
            cmd.exe /c "taskkill /F /T /PID $pidNum >nul 2>&1"
            $stoppedPids++
        } else {
            Write-Host "Cleared stale Laravel PID file (PID $pidNum is not active or not PHP)." -ForegroundColor Gray
        }
    }
    Remove-Item $laravelPidFile -Force -ErrorAction SilentlyContinue
}

# 2. Stop Tracked Vite Service with Identity Verification
$vitePidFile = Join-Path $runDir "vite.pid"
if (Test-Path $vitePidFile) {
    $pidRaw = (Get-Content $vitePidFile -ErrorAction SilentlyContinue | Out-String).Trim()
    if ($pidRaw -match '^\d+$') {
        $pidNum = [int]$pidRaw
        $proc = Get-Process -Id $pidNum -ErrorAction SilentlyContinue
        if ($proc -and ($proc.ProcessName -match '^(node|cmd|npm)$')) {
            Write-Host "Terminating tracked Vite compiler (PID: $pidNum, Process: $($proc.ProcessName))..." -ForegroundColor Yellow
            cmd.exe /c "taskkill /F /T /PID $pidNum >nul 2>&1"
            $stoppedPids++
        } else {
            Write-Host "Cleared stale Vite PID file (PID $pidNum is not active or not Node/NPM)." -ForegroundColor Gray
        }
    }
    Remove-Item $vitePidFile -Force -ErrorAction SilentlyContinue
}

if ($stoppedPids -gt 0) {
    Write-Host "Tracked services terminated cleanly." -ForegroundColor Green
} else {
    Write-Host "No active tracked services were found in .run\" -ForegroundColor Yellow
    Write-Host "Checking if orphaned PHP (port 8000) or Node (port 5173) are listening..." -ForegroundColor Gray

    # Only terminate if the listening process is verified to be php or node
    $portMap = @{ 8000 = 'php'; 5173 = 'node' }
    foreach ($entry in $portMap.GetEnumerator()) {
        $port = $entry.Key
        $expectedProcess = $entry.Value
        $conns = Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue
        if ($conns) {
            foreach ($conn in $conns) {
                $p = $conn.OwningProcess
                $proc = Get-Process -Id $p -ErrorAction SilentlyContinue
                if ($proc -and ($proc.ProcessName -match "^$expectedProcess")) {
                    Write-Host "Terminating orphaned $expectedProcess on port $port (PID: $p)..." -ForegroundColor Yellow
                    cmd.exe /c "taskkill /F /T /PID $p >nul 2>&1"
                } else {
                    Write-Host "Port $port is used by '$($proc.ProcessName)' (PID: $p) - skipping to protect unrelated software." -ForegroundColor Yellow
                }
            }
        }
    }
}

# Clean empty .run dir if empty
if (Test-Path $runDir) {
    $remaining = Get-ChildItem $runDir
    if (-not $remaining) {
        Remove-Item $runDir -Force -ErrorAction SilentlyContinue
    }
}

Write-Host "Shutdown procedure complete." -ForegroundColor Green
