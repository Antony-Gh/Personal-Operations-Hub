param(
    [string]$BackupFile = "",
    [switch]$Force,
    [switch]$Migrate,
    [string]$Passphrase = ""
)

# Personal Operations Hub - Verified Database Restore Utility (PowerShell 5.1 Compatible)
$ErrorActionPreference = "Stop"

$ProjectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $ProjectRoot

Write-Host "===================================================" -ForegroundColor Cyan
Write-Host "    Personal Operations Hub - Database Restore" -ForegroundColor Cyan
Write-Host "===================================================" -ForegroundColor Cyan
Write-Host ""

$phpCmd = Get-Command "php" -ErrorAction SilentlyContinue
if (-not $phpCmd) {
    Write-Host "[ERROR] PHP was not found in PATH." -ForegroundColor Red
    exit 1
}

$cmdArgs = @("artisan", "ops:restore-database")

if ($BackupFile) {
    $cmdArgs += $BackupFile
}

if ($Force) {
    $cmdArgs += "--force"
}

if ($Migrate) {
    $cmdArgs += "--migrate"
}

if ($Passphrase) {
    $cmdArgs += "--passphrase=$Passphrase"
}

try {
    & php @cmdArgs
    exit $LASTEXITCODE
} catch {
    Write-Host "[EXCEPTION] $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}
