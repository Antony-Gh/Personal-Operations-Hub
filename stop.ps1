# Personal Operations Hub - Safe Service Stopper (PowerShell 5.1 Compatible)
$ErrorActionPreference = "Stop"

Set-Location $PSScriptRoot

Write-Host "===================================================" -ForegroundColor Cyan
Write-Host "    Personal Operations Hub - Stop Services" -ForegroundColor Cyan
Write-Host "===================================================" -ForegroundColor Cyan
Write-Host ""

$runDir = Join-Path $PSScriptRoot ".run"
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
    @{ File = "vite.pid"; Label = "Vite compiler"; Pattern = '^(node|cmd|npm)$' }
)

foreach ($spec in $pidSpecs) {
    $pidFile = Join-Path $runDir $spec.File
    if (Test-Path $pidFile) {
        $pidRaw = (Get-Content $pidFile -ErrorAction SilentlyContinue | Out-String).Trim()
        if ($pidRaw -match '^\d+$') {
            $stoppedPids += Stop-VerifiedProcess -PidNumber ([int]$pidRaw) -Label $spec.Label -AllowedPattern $spec.Pattern
        }
        Remove-Item $pidFile -Force -ErrorAction SilentlyContinue
    }
}

if ($stoppedPids -gt 0) {
    Write-Host "Tracked services terminated cleanly ($stoppedPids process tree(s))." -ForegroundColor Green
} else {
    Write-Host "No active tracked services were found in .run\" -ForegroundColor Yellow
    Write-Host "Checking if orphaned PHP (port 8000) or Node (port 5173) are listening..." -ForegroundColor Gray

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
                } elseif ($proc) {
                    Write-Host "Port $port is used by '$($proc.ProcessName)' (PID: $p) - skipping to protect unrelated software." -ForegroundColor Yellow
                }
            }
        }
    }
}

# Clean empty .run dir if empty
if (Test-Path $runDir) {
    $remaining = Get-ChildItem $runDir -ErrorAction SilentlyContinue
    if (-not $remaining) {
        Remove-Item $runDir -Force -ErrorAction SilentlyContinue
    }
}

Write-Host "Shutdown procedure complete." -ForegroundColor Green
