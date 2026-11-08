# Personal Operations Hub - Safe Service Stopper (PowerShell 5.1 Compatible)
$ErrorActionPreference = "Stop"

$ProjectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $ProjectRoot

Write-Host "===================================================" -ForegroundColor Cyan
Write-Host "    Personal Operations Hub - Stop Services" -ForegroundColor Cyan
Write-Host "===================================================" -ForegroundColor Cyan
Write-Host ""

$runDir = Join-Path $ProjectRoot ".run"
$stoppedPids = 0
$seenPids = @{}

function Stop-VerifiedProcess {
    param(
        [int]$PidNumber,
        [string]$Label,
        [string]$AllowedPattern
    )
    if ($PidNumber -le 0 -or $seenPids.ContainsKey($PidNumber)) {
        return 0
    }
    $seenPids[$PidNumber] = $true
    $proc = Get-Process -Id $PidNumber -ErrorAction SilentlyContinue
    if ($proc -and ($proc.ProcessName -match $AllowedPattern)) {
        Write-Host "Terminating tracked $Label (PID: $PidNumber, Process: $($proc.ProcessName))..." -ForegroundColor Yellow
        cmd.exe /c "taskkill /F /T /PID $PidNumber >nul 2>&1"
        return 1
    } else {
        Write-Host "Cleared stale $Label PID entry (PID $PidNumber is not active or does not match expected runtime)." -ForegroundColor Gray
        return 0
    }
}

# 1. Stop Background JSON-tracked PIDs if present
$bgJson = Join-Path $runDir "background-pids.json"
if (Test-Path $bgJson) {
    try {
        $parsed = Get-Content $bgJson -Raw -ErrorAction Stop | ConvertFrom-Json
        if ($parsed.server) {
            $stoppedPids += Stop-VerifiedProcess -PidNumber ([int]$parsed.server) -Label "Laravel HTTP server" -AllowedPattern '^(php|php-cgi|cmd)$'
        }
        if ($parsed.scheduler) {
            $stoppedPids += Stop-VerifiedProcess -PidNumber ([int]$parsed.scheduler) -Label "Laravel Scheduler" -AllowedPattern '^(php|php-cgi|cmd)$'
        }
        if ($parsed.queue) {
            $stoppedPids += Stop-VerifiedProcess -PidNumber ([int]$parsed.queue) -Label "Database Queue Worker" -AllowedPattern '^(php|php-cgi|cmd)$'
        }
    } catch {
        # Ignore malformed JSON
    }
    Remove-Item $bgJson -Force -ErrorAction SilentlyContinue
}

# 2. Stop Individual PID files (laravel.pid, scheduler.pid, queue.pid, vite.pid)
$pidSpecs = @(
    @{ File = "laravel.pid"; Label = "Laravel backend"; Pattern = '^(php|php-cgi|cmd)$' },
    @{ File = "scheduler.pid"; Label = "Laravel Scheduler"; Pattern = '^(php|php-cgi|cmd)$' },
    @{ File = "queue.pid"; Label = "Database Queue Worker"; Pattern = '^(php|php-cgi|cmd)$' },
    @{ File = "vite.pid"; Label = "Vite frontend compiler"; Pattern = '^(node|cmd|npm)$' }
)

foreach ($spec in $pidSpecs) {
    $fPath = Join-Path $runDir $spec.File
    if (Test-Path $fPath) {
        $raw = (Get-Content $fPath -ErrorAction SilentlyContinue | Out-String).Trim()
        if ($raw -match '^\d+$') {
            $stoppedPids += Stop-VerifiedProcess -PidNumber ([int]$raw) -Label $spec.Label -AllowedPattern $spec.Pattern
        }
        Remove-Item $fPath -Force -ErrorAction SilentlyContinue
    }
}

# 3. Clean up Vite hot reload flag file so Laravel defaults to built assets on next run
$hotFile = Join-Path $ProjectRoot "public\hot"
if (Test-Path $hotFile) {
    Remove-Item $hotFile -Force -ErrorAction SilentlyContinue
}

# 4. Remove empty .run folder if no files remain
if (Test-Path $runDir) {
    $remaining = Get-ChildItem -Path $runDir -Force -ErrorAction SilentlyContinue
    if (-not $remaining -or $remaining.Count -eq 0) {
        Remove-Item $runDir -Force -Recurse -ErrorAction SilentlyContinue
    }
}

Write-Host ""
if ($stoppedPids -gt 0) {
    Write-Host "[OK] Stopped $stoppedPids tracked process(es). Working directory clean." -ForegroundColor Green
} else {
    Write-Host "[OK] No active tracked processes were running. Stale state cleared." -ForegroundColor Green
}
