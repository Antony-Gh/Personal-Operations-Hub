param(
    [switch]$Status,
    [switch]$NoBuildCheck,
    [int]$Port = 8000,
    [string]$BindHost = "127.0.0.1"
)

# Personal Operations Hub - Local Windows Background Runner (PowerShell 5.1 Compatible)
$ErrorActionPreference = "Stop"

$ProjectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $ProjectRoot

$RunDir = Join-Path $ProjectRoot ".run"
$LogDir = Join-Path $ProjectRoot "storage\logs\background"
$PidJsonPath = Join-Path $RunDir "background-pids.json"
$bindConfigFile = Join-Path $RunDir "bind-host.txt"

# If BindHost was not explicitly specified via command line, check persistent config
if (-not $PSBoundParameters.ContainsKey('BindHost')) {
    if (Test-Path $bindConfigFile) {
        $savedHost = (Get-Content $bindConfigFile -ErrorAction SilentlyContinue | Out-String).Trim()
        if ($savedHost) {
            $BindHost = $savedHost
        }
    } elseif ($env:POH_BIND_HOST) {
        $BindHost = $env:POH_BIND_HOST
    }
}

if (-not (Test-Path $RunDir)) {
    New-Item -ItemType Directory -Path $RunDir -Force | Out-Null
}
if (-not (Test-Path $LogDir)) {
    New-Item -ItemType Directory -Path $LogDir -Force | Out-Null
}

function Get-AlivePhpProcess {
    param([int]$ProcessId)
    if ($ProcessId -le 0) {
        return $null
    }
    $proc = Get-Process -Id $ProcessId -ErrorAction SilentlyContinue
    if ($proc -and ($proc.ProcessName -match '^(php|php-cgi|cmd)$')) {
        return $proc
    }
    return $null
}

function Read-PidState {
    $state = @{
        server    = 0
        scheduler = 0
        queue     = 0
    }

    if (Test-Path $PidJsonPath) {
        try {
            $raw = Get-Content $PidJsonPath -Raw -ErrorAction Stop | ConvertFrom-Json
            if ($raw.server) { $state.server = [int]$raw.server }
            if ($raw.scheduler) { $state.scheduler = [int]$raw.scheduler }
            if ($raw.queue) { $state.queue = [int]$raw.queue }
        } catch {
            # Ignore malformed PID JSON
        }
    }

    $legacyMap = @{
        server    = "laravel.pid"
        scheduler = "scheduler.pid"
        queue     = "queue.pid"
    }
    foreach ($k in $legacyMap.Keys) {
        if ($state[$k] -le 0) {
            $f = Join-Path $RunDir $legacyMap[$k]
            if (Test-Path $f) {
                $txt = (Get-Content $f -ErrorAction SilentlyContinue | Out-String).Trim()
                if ($txt -match '^\d+$') {
                    $state[$k] = [int]$txt
                }
            }
        }
    }

    return $state
}

$currentState = Read-PidState

if ($Status) {
    Write-Host "==========================================================" -ForegroundColor Cyan
    Write-Host "  Personal Operations Hub - Background Worker Status" -ForegroundColor Cyan
    Write-Host "==========================================================" -ForegroundColor Cyan

    $services = @(
        @{ Key = "server"; Label = "HTTP Server (http://${BindHost}:${Port})" },
        @{ Key = "scheduler"; Label = "Laravel Scheduler (schedule:work)" },
        @{ Key = "queue"; Label = "Database Queue Worker (queue:work)" }
    )

    foreach ($svc in $services) {
        $pidVal = [int]$currentState[$svc.Key]
        $alive = Get-AlivePhpProcess -ProcessId $pidVal
        if ($alive) {
            Write-Host ("  [RUNNING] {0} - PID {1}" -f $svc.Label, $alive.Id) -ForegroundColor Green
        } else {
            Write-Host ("  [STOPPED] {0}" -f $svc.Label) -ForegroundColor Yellow
        }
    }

    Write-Host ""
    Write-Host "  Logs directory: $LogDir" -ForegroundColor Gray
    exit 0
}

$phpCmd = Get-Command "php" -ErrorAction SilentlyContinue
if (-not $phpCmd) {
    Write-Host "[ERROR] PHP executable not found in PATH." -ForegroundColor Red
    exit 1
}
$phpExe = $phpCmd.Source

if (-not (Test-Path (Join-Path $ProjectRoot ".env"))) {
    Copy-Item (Join-Path $ProjectRoot ".env.example") (Join-Path $ProjectRoot ".env")
    & $phpExe artisan key:generate --no-interaction | Out-Null
}

$dbPath = Join-Path $ProjectRoot "database\database.sqlite"
if (-not (Test-Path $dbPath)) {
    New-Item -ItemType File -Path $dbPath -Force | Out-Null
}

# Run non-destructive migrations
& $phpExe artisan migrate --force --no-interaction | Out-Null

# Ensure production frontend bundle exists for headless mode
$manifestPath = Join-Path $ProjectRoot "public\build\manifest.json"
if ((-not $NoBuildCheck) -and (-not (Test-Path $manifestPath))) {
    $npmCmd = Get-Command "npm.cmd" -ErrorAction SilentlyContinue
    if ($npmCmd) {
        Write-Host "Building frontend production bundle (public\build\manifest.json missing)..." -ForegroundColor Cyan
        & $npmCmd.Source run build
    }
}

# Remove Vite hot file when binding to 0.0.0.0 (so mobile clients load compiled assets over port 8000)
# or when the local Vite dev compiler is not listening
$hotFile = Join-Path $ProjectRoot "public\hot"
if (Test-Path $hotFile) {
    if ($BindHost -eq "0.0.0.0") {
        Remove-Item $hotFile -Force -ErrorAction SilentlyContinue
        Write-Host "Removed public\hot file for 0.0.0.0 bind so mobile clients load compiled assets." -ForegroundColor Gray
    } else {
        $viteListening = Get-NetTCPConnection -LocalPort 5173 -State Listen -ErrorAction SilentlyContinue
        if (-not $viteListening) {
            Remove-Item $hotFile -Force -ErrorAction SilentlyContinue
        }
    }
}

# 1. Start HTTP Server if not alive
$serverProc = Get-AlivePhpProcess -ProcessId $currentState.server
if (-not $serverProc) {
    $existingPortConn = Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($existingPortConn) {
        $portOwner = Get-AlivePhpProcess -ProcessId ([int]$existingPortConn.OwningProcess)
        if ($portOwner) {
            $currentState.server = $portOwner.Id
            Write-Host "Adopted existing PHP server on port $Port (PID: $($portOwner.Id))." -ForegroundColor Green
        } else {
            Write-Host "[WARNING] Port $Port is already bound by another process (PID: $($existingPortConn.OwningProcess))." -ForegroundColor Yellow
        }
    } else {
        $srvOut = Join-Path $LogDir "server.out.log"
        $srvErr = Join-Path $LogDir "server.err.log"
        $pubDir = Join-Path $ProjectRoot "public"
        $serverScript = Join-Path $ProjectRoot "vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php"
        if (-not (Test-Path $serverScript)) {
            $serverScript = Join-Path $ProjectRoot "server.php"
        }
        $startedSrv = Start-Process -FilePath $phpExe `
            -ArgumentList "-S ${BindHost}:${Port} `"$serverScript`"" `
            -WorkingDirectory $pubDir `
            -WindowStyle Hidden `
            -RedirectStandardOutput $srvOut `
            -RedirectStandardError $srvErr `
            -PassThru
        $currentState.server = $startedSrv.Id
        Write-Host "Started Laravel HTTP Server on http://${BindHost}:${Port} (PID: $($startedSrv.Id))." -ForegroundColor Green
    }
} else {
    Write-Host "Laravel HTTP Server already running (PID: $($serverProc.Id))." -ForegroundColor Gray
}

# 2. Start Laravel Scheduler (schedule:work)
$schedProc = Get-AlivePhpProcess -ProcessId $currentState.scheduler
if (-not $schedProc) {
    $schOut = Join-Path $LogDir "scheduler.out.log"
    $schErr = Join-Path $LogDir "scheduler.err.log"
    $startedSch = Start-Process -FilePath $phpExe `
        -ArgumentList "artisan schedule:work --no-interaction" `
        -WorkingDirectory $ProjectRoot `
        -WindowStyle Hidden `
        -RedirectStandardOutput $schOut `
        -RedirectStandardError $schErr `
        -PassThru
    $currentState.scheduler = $startedSch.Id
    Write-Host "Started Laravel Scheduler worker (PID: $($startedSch.Id))." -ForegroundColor Green
} else {
    Write-Host "Laravel Scheduler already running (PID: $($schedProc.Id))." -ForegroundColor Gray
}

# 3. Start Database Queue Worker (queue:work)
$queueProc = Get-AlivePhpProcess -ProcessId $currentState.queue
if (-not $queueProc) {
    $qOut = Join-Path $LogDir "queue.out.log"
    $qErr = Join-Path $LogDir "queue.err.log"
    $startedQueue = Start-Process -FilePath $phpExe `
        -ArgumentList "artisan queue:work --sleep=3 --tries=3 --timeout=60 --no-interaction" `
        -WorkingDirectory $ProjectRoot `
        -WindowStyle Hidden `
        -RedirectStandardOutput $qOut `
        -RedirectStandardError $qErr `
        -PassThru
    $currentState.queue = $startedQueue.Id
    Write-Host "Started Laravel Queue worker (PID: $($startedQueue.Id))." -ForegroundColor Green
} else {
    Write-Host "Laravel Queue worker already running (PID: $($queueProc.Id))." -ForegroundColor Gray
}

# Persist PID tracking files
$payload = [ordered]@{
    updated_at = (Get-Date).ToString("o")
    host       = $BindHost
    port       = $Port
    server     = $currentState.server
    scheduler  = $currentState.scheduler
    queue      = $currentState.queue
}
$payload | ConvertTo-Json | Out-File -FilePath $PidJsonPath -Encoding ascii

if ($currentState.server -gt 0) {
    $currentState.server | Out-File -FilePath (Join-Path $RunDir "laravel.pid") -Encoding ascii
}
if ($currentState.scheduler -gt 0) {
    $currentState.scheduler | Out-File -FilePath (Join-Path $RunDir "scheduler.pid") -Encoding ascii
}
if ($currentState.queue -gt 0) {
    $currentState.queue | Out-File -FilePath (Join-Path $RunDir "queue.pid") -Encoding ascii
}

Write-Host "Background services active. Check status anytime with: powershell -File .\scripts\start-background.ps1 -Status" -ForegroundColor Cyan

