# Personal Operations Hub - Comprehensive System Diagnostics (PowerShell 5.1 Compatible)
$ErrorActionPreference = "Continue"

$ProjectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $ProjectRoot

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "    Personal Operations Hub - System Diagnostics          " -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host ""

$issuesFound = 0
$warningsFound = 0

function Report-Status {
    param(
        [string]$Component,
        [string]$Status,
        [string]$Details = "",
        [string]$Type = "OK"
    )
    $color = "Green"
    $tag = "[OK]     "
    if ($Type -eq "WARN") {
        $color = "Yellow"
        $tag = "[WARN]   "
        $script:warningsFound++
    } elseif ($Type -eq "ERROR") {
        $color = "Red"
        $tag = "[FAIL]   "
        $script:issuesFound++
    }

    $detailStr = if ($Details) { " - $Details" } else { "" }
    Write-Host ("  {0} {1}: {2}{3}" -f $tag, $Component, $Status, $detailStr) -ForegroundColor $color
}

# 1. System & CLI Prerequisites
Write-Host "1. CLI Prerequisites & Runtimes:" -ForegroundColor White
$php = Get-Command "php" -ErrorAction SilentlyContinue
if ($php) {
    $phpVer = (& php -r "echo PHP_VERSION;")
    if ([System.Version]::Parse($phpVer).Major -ge 8) {
        Report-Status -Component "PHP Runtime" -Status "Installed" -Details "v$phpVer"
    } else {
        Report-Status -Component "PHP Runtime" -Status "Outdated" -Details "v$phpVer (PHP 8.2+ recommended)" -Type "WARN"
    }
} else {
    Report-Status -Component "PHP Runtime" -Status "Missing" -Details "Not in PATH" -Type "ERROR"
}

$composer = Get-Command "composer" -ErrorAction SilentlyContinue
if ($composer) {
    Report-Status -Component "Composer" -Status "Installed" -Details "Available in PATH"
} else {
    Report-Status -Component "Composer" -Status "Missing" -Details "Not in PATH" -Type "ERROR"
}

$node = Get-Command "node" -ErrorAction SilentlyContinue
if ($node) {
    $nodeVer = (& node -v)
    Report-Status -Component "Node.js" -Status "Installed" -Details "$nodeVer"
} else {
    Report-Status -Component "Node.js" -Status "Missing" -Details "Not in PATH" -Type "ERROR"
}

$npm = Get-Command "npm" -ErrorAction SilentlyContinue
if ($npm) {
    Report-Status -Component "npm" -Status "Installed" -Details "Available in PATH"
} else {
    Report-Status -Component "npm" -Status "Missing" -Details "Not in PATH" -Type "ERROR"
}

# 2. Environment Configuration
Write-Host "`n2. Environment & Configuration:" -ForegroundColor White
$envPath = Join-Path $ProjectRoot ".env"
if (Test-Path $envPath) {
    $envContent = Get-Content $envPath -Raw -ErrorAction SilentlyContinue
    $hasKey = $envContent -match 'APP_KEY=base64:.+'
    if ($hasKey) {
        Report-Status -Component ".env File" -Status "Configured" -Details "APP_KEY is set"
    } else {
        Report-Status -Component ".env File" -Status "Incomplete" -Details "APP_KEY missing or empty (run: php artisan key:generate)" -Type "ERROR"
    }

    $dbConn = if ($envContent -match 'DB_CONNECTION=(\w+)') { $Matches[1] } else { "sqlite" }
    Report-Status -Component "Database Driver" -Status "Detected" -Details "$dbConn"
} else {
    Report-Status -Component ".env File" -Status "Missing" -Details "Copy from .env.example" -Type "ERROR"
}

# 3. Database Health & Integrity
Write-Host "`n3. Database & Storage:" -ForegroundColor White
$sqlitePath = Join-Path $ProjectRoot "database\database.sqlite"
if (Test-Path $sqlitePath) {
    $sizeKb = [math]::Round((Get-Item $sqlitePath).Length / 1KB, 2)
    $integrity = (& php -r "echo (new SQLite3('database/database.sqlite'))->querySingle('PRAGMA integrity_check;');")
    if ($integrity -eq 'ok') {
        Report-Status -Component "SQLite Database" -Status "Healthy" -Details "$sizeKb KB (PRAGMA integrity_check: ok)"
    } else {
        Report-Status -Component "SQLite Database" -Status "Corruption Alert" -Details "PRAGMA integrity_check returned '$integrity'" -Type "ERROR"
    }
} else {
    Report-Status -Component "SQLite Database" -Status "Missing" -Details "database\database.sqlite not found" -Type "ERROR"
}

# Backups directory
$backupDir = Join-Path $ProjectRoot "storage\app\backups"
if (-not (Test-Path $backupDir)) {
    New-Item -ItemType Directory -Path $backupDir -Force | Out-Null
}
$backupCount = (Get-ChildItem -Path $backupDir -Filter "backup-*.sqlite*" -ErrorAction SilentlyContinue).Count
Report-Status -Component "Backup Archive" -Status "Ready" -Details "$backupCount backup snapshot(s) found"

# 4. Directory Write Permissions
Write-Host "`n4. Filesystem Permissions:" -ForegroundColor White
$dirsToCheck = @("storage", "storage\logs", "storage\framework", "bootstrap\cache", ".run")
foreach ($dir in $dirsToCheck) {
    $target = Join-Path $ProjectRoot $dir
    if (-not (Test-Path $target)) {
        New-Item -ItemType Directory -Path $target -Force | Out-Null
    }
    $testFile = Join-Path $target ("_perm_test_" + [Guid]::NewGuid().ToString() + ".tmp")
    try {
        Set-Content -Path $testFile -Value "test" -ErrorAction Stop
        Remove-Item -Path $testFile -Force -ErrorAction SilentlyContinue
        Report-Status -Component "Writable: $dir" -Status "Pass"
    } catch {
        Report-Status -Component "Writable: $dir" -Status "Denied" -Details "Write permission error: $($_.Exception.Message)" -Type "ERROR"
    }
}

# 5. Network Ports & Tracked Processes
Write-Host "`n5. Ports & Services:" -ForegroundColor White
$port8000 = Get-NetTCPConnection -LocalPort 8000 -State Listen -ErrorAction SilentlyContinue | Select-Object -First 1
if ($port8000) {
    $p = Get-Process -Id ([int]$port8000.OwningProcess) -ErrorAction SilentlyContinue
    $pName = if ($p) { "$($p.ProcessName) (PID: $($p.Id))" } else { "PID: $($port8000.OwningProcess)" }
    Report-Status -Component "Port 8000 (HTTP)" -Status "Listening" -Details "Bound by $pName"
} else {
    Report-Status -Component "Port 8000 (HTTP)" -Status "Idle" -Details "Not currently running" -Type "WARN"
}

$port5173 = Get-NetTCPConnection -LocalPort 5173 -State Listen -ErrorAction SilentlyContinue | Select-Object -First 1
if ($port5173) {
    $p = Get-Process -Id ([int]$port5173.OwningProcess) -ErrorAction SilentlyContinue
    $pName = if ($p) { "$($p.ProcessName) (PID: $($p.Id))" } else { "PID: $($port5173.OwningProcess)" }
    Report-Status -Component "Port 5173 (Vite)" -Status "Listening" -Details "Bound by $pName"
} else {
    Report-Status -Component "Port 5173 (Vite)" -Status "Idle" -Details "Dev compiler not running"
}

# 6. Windows Firewall & Mobile LAN
Write-Host "`n6. Local Network & Firewall:" -ForegroundColor White
if ($env:OS -like "*Windows*" -or [System.Environment]::OSVersion.Platform -like "*Win*") {
    $rule = Get-NetFirewallRule -DisplayName "Personal Operations Hub (Port 8000)" -ErrorAction SilentlyContinue
    if ($rule -and $rule.Enabled) {
        Report-Status -Component "Firewall Port 8000" -Status "Allowed" -Details "Incoming LAN connections enabled"
    } else {
        Report-Status -Component "Firewall Port 8000" -Status "Disabled/Missing" -Details "Run enable-mobile-access.ps1 for phone access" -Type "WARN"
    }
}

# 7. Recent Log Errors
Write-Host "`n7. Error Logs Check:" -ForegroundColor White
$larLog = Join-Path $ProjectRoot "storage\logs\laravel.log"
if (Test-Path $larLog) {
    $errorsInLog = (Select-String -Path $larLog -Pattern "\[\d{4}-\d{2}-\d{2}.+\] \w+\.ERROR:" -ErrorAction SilentlyContinue)
    $errCount = if ($errorsInLog) { $errorsInLog.Count } else { 0 }
    if ($errCount -gt 0) {
        Report-Status -Component "Laravel Error Log" -Status "Errors Logged" -Details "$errCount error entry/entries in storage\logs\laravel.log" -Type "WARN"
    } else {
        Report-Status -Component "Laravel Error Log" -Status "Clean" -Details "No critical errors recorded"
    }
} else {
    Report-Status -Component "Laravel Error Log" -Status "Clean" -Details "No log file generated yet"
}

Write-Host ""
Write-Host "==========================================================" -ForegroundColor Cyan
if ($issuesFound -eq 0 -and $warningsFound -eq 0) {
    Write-Host "  DIAGNOSTICS SUMMARY: ALL SYSTEMS PERFECT (0 errors, 0 warnings)" -ForegroundColor Green
} elseif ($issuesFound -eq 0) {
    Write-Host "  DIAGNOSTICS SUMMARY: HEALTHY WITH $warningsFound ADVISORY NOTICE(S)" -ForegroundColor Yellow
} else {
    Write-Host "  DIAGNOSTICS SUMMARY: $issuesFound ERROR(S), $warningsFound WARNING(S) FOUND" -ForegroundColor Red
}
Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host ""
