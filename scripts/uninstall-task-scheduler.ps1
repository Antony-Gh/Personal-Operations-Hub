param(
    [string]$TaskName = "PersonalOperationsHub-Background"
)

# Personal Operations Hub - Windows Task Scheduler Uninstaller (PowerShell 5.1 Compatible)
$ErrorActionPreference = "Stop"

$StopScript = Join-Path $PSScriptRoot "stop-background.ps1"

if (Test-Path $StopScript) {
    & $StopScript
}

Write-Host "Removing Windows Task Scheduler task '$TaskName'..." -ForegroundColor Cyan

$existing = Get-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue
if ($existing) {
    try {
        Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false -ErrorAction Stop
        Write-Host "Scheduled task '$TaskName' removed." -ForegroundColor Green
    } catch {
        & schtasks.exe /Delete /TN $TaskName /F | Out-Null
        Write-Host "Scheduled task '$TaskName' removed via schtasks.exe." -ForegroundColor Green
    }
} else {
    & schtasks.exe /Query /TN $TaskName >$null 2>&1
    if ($LASTEXITCODE -eq 0) {
        & schtasks.exe /Delete /TN $TaskName /F | Out-Null
        Write-Host "Scheduled task '$TaskName' removed." -ForegroundColor Green
    } else {
        Write-Host "Scheduled task '$TaskName' was not registered." -ForegroundColor Gray
    }
}

