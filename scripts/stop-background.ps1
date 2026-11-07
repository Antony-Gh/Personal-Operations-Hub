# Personal Operations Hub - Stop Local Background Services (PowerShell 5.1 Compatible)
$ErrorActionPreference = "Stop"

$ProjectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $ProjectRoot

$RunDir = Join-Path $ProjectRoot ".run"
$PidJsonPath = Join-Path $RunDir "background-pids.json"

$pidsToStop = @()

if (Test-Path $PidJsonPath) {
    try {
        $parsed = Get-Content $PidJsonPath -Raw -ErrorAction Stop | ConvertFrom-Json
        foreach ($prop in @("server", "scheduler", "queue")) {
            if ($parsed.$prop -and ([int]$parsed.$prop -gt 0)) {
                $pidsToStop += [int]$parsed.$prop
            }
        }
    } catch {
        # Ignore parse issues
    }
    Remove-Item $PidJsonPath -Force -ErrorAction SilentlyContinue
}

foreach ($pidFile in @("laravel.pid", "scheduler.pid", "queue.pid")) {
    $fullPath = Join-Path $RunDir $pidFile
    if (Test-Path $fullPath) {
        $raw = (Get-Content $fullPath -ErrorAction SilentlyContinue | Out-String).Trim()
        if ($raw -match '^\d+$') {
            $pidsToStop += [int]$raw
        }
        Remove-Item $fullPath -Force -ErrorAction SilentlyContinue
    }
}

$uniquePids = $pidsToStop | Select-Object -Unique
$stoppedCount = 0

foreach ($pidNum in $uniquePids) {
    if ($pidNum -le 0) { continue }
    $proc = Get-Process -Id $pidNum -ErrorAction SilentlyContinue
    if ($proc -and ($proc.ProcessName -match '^(php|php-cgi|cmd)$')) {
        Write-Host "Stopping background PHP worker (PID: $pidNum, Name: $($proc.ProcessName))..." -ForegroundColor Yellow
        cmd.exe /c "taskkill /F /T /PID $pidNum >nul 2>&1"
        $stoppedCount++
    }
}

if (Test-Path $RunDir) {
    $remaining = Get-ChildItem $RunDir -ErrorAction SilentlyContinue
    if (-not $remaining) {
        Remove-Item $RunDir -Force -ErrorAction SilentlyContinue
    }
}

Write-Host "Stopped $stoppedCount tracked background process(es)." -ForegroundColor Green

