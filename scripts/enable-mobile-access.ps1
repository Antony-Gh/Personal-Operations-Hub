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

# 3. Prepare Frontend Production Bundle & Clear Vite Hot File
# Mobile devices connect via LAN IP and cannot reach localhost Vite dev server (port 5173).
# Removing public\hot ensures Laravel renders compiled production assets directly over port 8000.
$hotFile = Join-Path $ProjectRoot "public\hot"
if (Test-Path $hotFile) {
    Remove-Item $hotFile -Force -ErrorAction SilentlyContinue
    Write-Host "  [OK] Cleaned up Vite hot reload file (mobile devices will load compiled assets)." -ForegroundColor Green
}

$vitePidFile = Join-Path $RunDir "vite.pid"
if (Test-Path $vitePidFile) {
    $vRaw = (Get-Content $vitePidFile -ErrorAction SilentlyContinue | Out-String).Trim()
    if ($vRaw -match '^\d+$') {
        $vProc = Get-Process -Id ([int]$vRaw) -ErrorAction SilentlyContinue
        if ($vProc -and ($vProc.ProcessName -match '^(node|cmd|npm)$')) {
            Stop-Process -Id ([int]$vRaw) -Force -ErrorAction SilentlyContinue
            Write-Host "  [OK] Stopped local Vite dev compiler to prioritize compiled production assets." -ForegroundColor Green
        }
    }
    Remove-Item $vitePidFile -Force -ErrorAction SilentlyContinue
}

$manifestPath = Join-Path $ProjectRoot "public\build\manifest.json"
if (-not (Test-Path $manifestPath)) {
    Write-Host "  Building production frontend bundle for mobile access (npm run build)..." -ForegroundColor Cyan
    $npmCmd = Get-Command "npm.cmd" -ErrorAction SilentlyContinue
    if ($npmCmd) {
        & $npmCmd.Source run build
    }
}

# 4. Restart Background Server if requested
if (-not $NoRestart) {
    Write-Host "  Restarting background services to listen on 0.0.0.0..." -ForegroundColor Cyan
    $stopScript = Join-Path $PSScriptRoot "stop-background.ps1"
    $startScript = Join-Path $PSScriptRoot "start-background.ps1"

    if (Test-Path $stopScript) {
        & $stopScript
    }
    Start-Sleep -Seconds 1
    if (Test-Path $startScript) {
        & $startScript -BindHost "0.0.0.0" -Port $Port
    }
    Write-Host "  [OK] Background services running bound to 0.0.0.0." -ForegroundColor Green
}

Write-Host ""

# 5. Display Connection URLs and QR Code
$phpCmd = Get-Command "php" -ErrorAction SilentlyContinue
$phpExe = if ($phpCmd) { $phpCmd.Source } else { "php" }

try {
    & $phpExe artisan ops:mobile --port=$Port
} catch {
    Write-Host "  Mobile configuration complete! Run 'php artisan ops:mobile' to view your QR code." -ForegroundColor Green
}

