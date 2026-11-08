param(
    [string]$BackupFile,
    [switch]$Force
)

# Personal Operations Hub - Verified Database Restore Utility (PowerShell 5.1 Compatible)
$ErrorActionPreference = "Stop"

$ProjectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $ProjectRoot

Write-Host "===================================================" -ForegroundColor Cyan
Write-Host "    Personal Operations Hub - Database Restore" -ForegroundColor Cyan
Write-Host "===================================================" -ForegroundColor Cyan
Write-Host ""

$backupDir = Join-Path $ProjectRoot "storage\app\backups"
$dbPath = Join-Path $ProjectRoot "database\database.sqlite"

if (-not (Test-Path $backupDir)) {
    Write-Host "[ERROR] Backup directory not found at $backupDir" -ForegroundColor Red
    exit 1
}

$backups = Get-ChildItem -Path $backupDir -Filter "backup-*.sqlite*" -ErrorAction SilentlyContinue |
    Sort-Object LastWriteTime -Descending

if (-not $backups -or $backups.Count -eq 0) {
    Write-Host "[WARNING] No database backups found in $backupDir" -ForegroundColor Yellow
    exit 0
}

$selectedFile = $null

if ($BackupFile) {
    if (Test-Path $BackupFile) {
        $selectedFile = Get-Item $BackupFile
    } else {
        $targetInDir = Join-Path $backupDir $BackupFile
        if (Test-Path $targetInDir) {
            $selectedFile = Get-Item $targetInDir
        } else {
            Write-Host "[ERROR] Specified backup file does not exist: $BackupFile" -ForegroundColor Red
            exit 1
        }
    }
} else {
    Write-Host "Available Backups in storage\app\backups\:" -ForegroundColor Yellow
    Write-Host ""
    for ($i = 0; $i -lt $backups.Count; $i++) {
        $b = $backups[$i]
        $sizeKb = [math]::Round($b.Length / 1KB, 2)
        $encTag = if ($b.Name.EndsWith(".enc")) { " [ENCRYPTED]" } else { " [RAW SQLITE]" }
        Write-Host ("  [{0}] {1} ({2} KB) - {3}{4}" -f ($i + 1), $b.Name, $sizeKb, $b.LastWriteTime.ToString("yyyy-MM-dd HH:mm:ss"), $encTag)
    }
    Write-Host ""
    $choice = Read-Host "Select backup number to restore (or press Enter to cancel)"
    if (-not ($choice -match '^\d+$')) {
        Write-Host "Restore cancelled." -ForegroundColor Gray
        exit 0
    }
    $index = [int]$choice - 1
    if ($index -lt 0 -or $index -ge $backups.Count) {
        Write-Host "Invalid selection. Restore cancelled." -ForegroundColor Red
        exit 1
    }
    $selectedFile = $backups[$index]
}

Write-Host ""
Write-Host "Selected Backup: $($selectedFile.Name)" -ForegroundColor Cyan
Write-Host "Target Database: $dbPath" -ForegroundColor Cyan
Write-Host ""

if (-not $Force) {
    Write-Host "[WARNING] Restoring a database replaces all current operational data with this backup!" -ForegroundColor Yellow
    $confirm = Read-Host "Are you absolutely sure you want to proceed? (Type 'YES' to confirm)"
    if ($confirm -ne 'YES') {
        Write-Host "Restore cancelled by user." -ForegroundColor Gray
        exit 0
    }
}

# 1. Safety Snapshot of Current Database
if (Test-Path $dbPath) {
    $timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
    $snapshotPath = Join-Path $ProjectRoot "database\database.sqlite.pre-restore.$timestamp.bak"
    Copy-Item -Path $dbPath -Destination $snapshotPath -Force
    Write-Host "Safety pre-restore snapshot created: $snapshotPath" -ForegroundColor Green
}

# 2. Stop running services temporarily to avoid SQLite file locking
$stopScript = Join-Path $PSScriptRoot "stop.ps1"
if (Test-Path $stopScript) {
    & $stopScript | Out-Null
}

# 3. Handle Encrypted vs Raw SQLite restore
if ($selectedFile.Extension -eq ".enc") {
    Write-Host "Backup is encrypted. Decrypting..." -ForegroundColor Yellow
    $tempDecrypted = Join-Path $backupDir ("temp-restore-" + [Guid]::NewGuid().ToString() + ".sqlite")

    try {
        & php artisan ops:decrypt-backup "$($selectedFile.FullName)" "$tempDecrypted"
        if ($LASTEXITCODE -ne 0 -or (-not (Test-Path $tempDecrypted))) {
            throw "Decryption failed or invalid passphrase."
        }

        Copy-Item -Path $tempDecrypted -Destination $dbPath -Force
        Remove-Item -Path $tempDecrypted -Force -ErrorAction SilentlyContinue
    } catch {
        Write-Host "[ERROR] Failed to decrypt and restore backup: $($_.Exception.Message)" -ForegroundColor Red
        if (Test-Path $tempDecrypted) {
            Remove-Item -Path $tempDecrypted -Force -ErrorAction SilentlyContinue
        }
        exit 1
    }
} else {
    Copy-Item -Path $selectedFile.FullName -Destination $dbPath -Force
}

# 4. Verify Restored SQLite Integrity
$checkResult = (& php -r "echo (new SQLite3('database/database.sqlite'))->querySingle('PRAGMA integrity_check;');")
if ($checkResult -eq 'ok') {
    Write-Host ""
    Write-Host "[SUCCESS] Database restored successfully and verified with PRAGMA integrity_check (ok)!" -ForegroundColor Green
    Write-Host "Restored from: $($selectedFile.Name)" -ForegroundColor Gray
} else {
    Write-Host ""
    Write-Host "[WARNING] Integrity check reported: $checkResult" -ForegroundColor Yellow
}
