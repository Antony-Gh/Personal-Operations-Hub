param(
    [string]$Trigger = "manual"
)

# Personal Operations Hub - Verified Database Backup Utility (PowerShell 5.1 Compatible)
$ErrorActionPreference = "Stop"

$ProjectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $ProjectRoot

Write-Host "===================================================" -ForegroundColor Cyan
Write-Host "    Personal Operations Hub - Database Backup" -ForegroundColor Cyan
Write-Host "===================================================" -ForegroundColor Cyan
Write-Host ""

$phpCmd = Get-Command "php" -ErrorAction SilentlyContinue
if (-not $phpCmd) {
    Write-Host "[ERROR] PHP was not found in PATH." -ForegroundColor Red
    exit 1
}

$dbPath = Join-Path $ProjectRoot "database\database.sqlite"
if (-not (Test-Path $dbPath)) {
    Write-Host "[ERROR] SQLite database file not found at $dbPath" -ForegroundColor Red
    exit 1
}

$dbSize = (Get-Item $dbPath).Length
Write-Host "Active SQLite Database: $dbPath ($([math]::Round($dbSize / 1KB, 2)) KB)" -ForegroundColor Gray
Write-Host "Executing verified backup service with encryption and secondary mirror support..." -ForegroundColor Yellow

try {
    & php artisan ops:backup-database --trigger=$Trigger --no-interaction
    $exitCode = $LASTEXITCODE

    if ($exitCode -eq 0) {
        Write-Host ""
        Write-Host "[SUCCESS] Database backup completed and verified." -ForegroundColor Green
    } else {
        Write-Host ""
        Write-Host "[ERROR] Database backup failed with exit code $exitCode." -ForegroundColor Red
    }

    exit $exitCode
} catch {
    Write-Host "[EXCEPTION] $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}

