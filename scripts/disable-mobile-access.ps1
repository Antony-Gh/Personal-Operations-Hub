param(
    [int]$Port = 8000,
    [switch]$NoRestart
)

# Personal Operations Hub - Disable Mobile LAN Access (Revert to Localhost 127.0.0.1)
$ErrorActionPreference = "Stop"

$ProjectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $ProjectRoot

$RunDir = Join-Path $ProjectRoot ".run"
$bindFile = Join-Path $RunDir "bind-host.txt"

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "  Personal Operations Hub - Revert to Localhost Only      " -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host ""

# 1. Configure Persistent Bind Host to 127.0.0.1
if (Test-Path $bindFile) {
    Set-Content -Path $bindFile -Value "127.0.0.1" -Force
}
Write-Host "  [OK] Configured background runner to bind on localhost only (127.0.0.1)." -ForegroundColor Green

# 2. Disable or remove Windows Firewall Rule
$ruleName = "Personal Operations Hub (Port $Port)"
try {
    $existing = Get-NetFirewallRule -DisplayName $ruleName -ErrorAction SilentlyContinue
    if ($existing) {
        Disable-NetFirewallRule -DisplayName $ruleName | Out-Null
        Write-Host "  [OK] Disabled Windows Firewall rule '$ruleName'." -ForegroundColor Green
    }
} catch {
    # Ignore if non-elevated
}

# 3. Restart Background Server
if (-not $NoRestart) {
    Write-Host "  Restarting background services to listen on 127.0.0.1..." -ForegroundColor Cyan
    $stopScript = Join-Path $PSScriptRoot "stop-background.ps1"
    $startScript = Join-Path $PSScriptRoot "start-background.ps1"

    if (Test-Path $stopScript) {
        & powershell.exe -NoProfile -ExecutionPolicy Bypass -File $stopScript | Out-Null
    }
    Start-Sleep -Seconds 1
    if (Test-Path $startScript) {
        & powershell.exe -NoProfile -ExecutionPolicy Bypass -File $startScript -BindHost "127.0.0.1" -Port $Port | Out-Null
    }
    Write-Host "  [OK] Background services running bound to 127.0.0.1." -ForegroundColor Green
}

Write-Host ""
Write-Host "  Localhost isolation restored. The application is only accessible from this computer." -ForegroundColor Green

