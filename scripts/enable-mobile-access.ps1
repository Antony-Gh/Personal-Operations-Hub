param(
    [int]$Port = 8000,
    [switch]$NoRestart
)

# Personal Operations Hub - Enable Local Mobile Access (PowerShell 5.1 Compatible)
$ErrorActionPreference = "Stop"

$ProjectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $ProjectRoot

$RunDir = Join-Path $ProjectRoot ".run"
if (-not (Test-Path $RunDir)) {
    New-Item -ItemType Directory -Path $RunDir -Force | Out-Null
}

$bindFile = Join-Path $RunDir "bind-host.txt"

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "  Personal Operations Hub - Enable Local Mobile Access    " -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host ""

# 1. Configure Persistent Bind Host to 0.0.0.0
Set-Content -Path $bindFile -Value "0.0.0.0" -Force
Write-Host "  [OK] Configured background runner to bind on all network interfaces (0.0.0.0)." -ForegroundColor Green

# 2. Add or verify Windows Firewall Rule for Port 8000
$ruleName = "Personal Operations Hub (Port $Port)"
$ruleAdded = $false

try {
    $existing = Get-NetFirewallRule -DisplayName $ruleName -ErrorAction SilentlyContinue
    if ($existing) {
        if (-not $existing.Enabled) {
            Enable-NetFirewallRule -DisplayName $ruleName | Out-Null
        }
        Write-Host "  [OK] Windows Firewall rule '$ruleName' is active." -ForegroundColor Green
        $ruleAdded = $true
    } else {
        New-NetFirewallRule -DisplayName $ruleName `
            -Direction Inbound `
            -LocalPort $Port `
            -Protocol TCP `
            -Action Allow `
            -Description "Allows mobile devices on local Wi-Fi to access Personal Operations Hub on port $Port." | Out-Null
        Write-Host "  [OK] Added Windows Firewall inbound rule for port $Port." -ForegroundColor Green
        $ruleAdded = $true
    }
} catch {
    # If New-NetFirewallRule fails due to elevation, try netsh or print instructions
    try {
        $netshOut = netsh advfirewall firewall add rule name="$ruleName" dir=in action=allow protocol=TCP localport=$Port 2>&1
        if ($LASTEXITCODE -eq 0) {
            Write-Host "  [OK] Added Windows Firewall inbound rule via netsh." -ForegroundColor Green
            $ruleAdded = $true
        } else {
            Write-Host "  [WARNING] Could not automatically add firewall rule (requires Administrator privileges)." -ForegroundColor Yellow
            Write-Host "    To allow access from your phone, open an elevated (Admin) PowerShell and run:" -ForegroundColor Gray
            Write-Host "    New-NetFirewallRule -DisplayName '$ruleName' -Direction Inbound -LocalPort $Port -Protocol TCP -Action Allow" -ForegroundColor Cyan
        }
    } catch {
        Write-Host "  [WARNING] Firewall configuration skipped." -ForegroundColor Yellow
    }
}

Write-Host ""

# 3. Restart Background Server if requested
if (-not $NoRestart) {
    Write-Host "  Restarting background services to listen on 0.0.0.0..." -ForegroundColor Cyan
    $stopScript = Join-Path $PSScriptRoot "stop-background.ps1"
    $startScript = Join-Path $PSScriptRoot "start-background.ps1"

    if (Test-Path $stopScript) {
        & powershell.exe -NoProfile -ExecutionPolicy Bypass -File $stopScript | Out-Null
    }
    Start-Sleep -Seconds 1
    if (Test-Path $startScript) {
        & powershell.exe -NoProfile -ExecutionPolicy Bypass -File $startScript -BindHost "0.0.0.0" -Port $Port | Out-Null
    }
    Write-Host "  [OK] Background services running bound to 0.0.0.0." -ForegroundColor Green
}

Write-Host ""

# 4. Display Connection URLs and QR Code
$phpExe = Join-Path $ProjectRoot "vendor\..\..\php.exe" # check path or fallback to php
if (-not (Test-Path $phpExe)) {
    $phpExe = "php"
}

try {
    & $phpExe artisan ops:mobile --port=$Port
} catch {
    Write-Host "  Mobile configuration complete! Run 'php artisan ops:mobile' to view your QR code." -ForegroundColor Green
}

