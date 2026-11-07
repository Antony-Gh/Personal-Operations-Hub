param(
    [string]$TaskName = "PersonalOperationsHub-Background",
    [switch]$RunNow
)

# Personal Operations Hub - Windows Task Scheduler Auto-Start Installer (PowerShell 5.1 Compatible)
$ErrorActionPreference = "Stop"

$ProjectRoot = Split-Path -Parent $PSScriptRoot
$StartScript = Join-Path $PSScriptRoot "start-background.ps1"

if (-not (Test-Path $StartScript)) {
    Write-Host "[ERROR] Could not locate $StartScript" -ForegroundColor Red
    exit 1
}

$psExe = Join-Path $env:SystemRoot "System32\WindowsPowerShell\v1.0\powershell.exe"
if (-not (Test-Path $psExe)) {
    $psExe = "powershell.exe"
}

$arguments = "-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$StartScript`""
$currentUser = [System.Security.Principal.WindowsIdentity]::GetCurrent().Name

Write-Host "Registering Windows Task Scheduler logon task '$TaskName' for $currentUser..." -ForegroundColor Cyan

$registered = $false

try {
    $action = New-ScheduledTaskAction -Execute $psExe -Argument $arguments -WorkingDirectory $ProjectRoot
    $trigger = New-ScheduledTaskTrigger -AtLogOn -User $currentUser
    $settings = New-ScheduledTaskSettingsSet `
        -AllowStartIfOnBatteries `
        -DontStopIfGoingOnBatteries `
        -StartWhenAvailable `
        -MultipleInstances IgnoreNew `
        -ExecutionTimeLimit ([TimeSpan]::Zero)

    Register-ScheduledTask `
        -TaskName $TaskName `
        -Action $action `
        -Trigger $trigger `
        -Settings $settings `
        -Description "Starts Personal Operations Hub local HTTP server, Laravel scheduler, and database queue worker in the background at Windows logon." `
        -Force | Out-Null

    $registered = $true
} catch {
    Write-Host "Register-ScheduledTask required elevation or failed ($($_.Exception.Message)); trying schtasks.exe fallback..." -ForegroundColor Yellow
    $trValue = "`"$psExe`" $arguments"
    & schtasks.exe /Create /TN $TaskName /TR $trValue /SC ONLOGON /F | Out-Null
    if ($LASTEXITCODE -eq 0) {
        $registered = $true
    }
}

if (-not $registered) {
    Write-Host "[ERROR] Unable to register scheduled task '$TaskName'. Try running PowerShell as Administrator or using the Startup folder shortcut documented in docs\BACKGROUND_SCHEDULER.md." -ForegroundColor Red
    exit 1
}

Write-Host "Task '$TaskName' registered cleanly to launch at Windows sign-in." -ForegroundColor Green

if ($RunNow) {
    Write-Host "Starting background workers right now..." -ForegroundColor Cyan
    & $psExe -NoProfile -ExecutionPolicy Bypass -File $StartScript
}

