param(
    [int]$Lines = 25,
    [switch]$ErrorsOnly
)

# Personal Operations Hub - Log Viewer Utility (PowerShell 5.1 Compatible)
$ErrorActionPreference = "Continue"

$ProjectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $ProjectRoot

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "    Personal Operations Hub - Recent Log Output           " -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host ""

$laravelLog = Join-Path $ProjectRoot "storage\logs\laravel.log"
$bgLogDir = Join-Path $ProjectRoot "storage\logs\background"

if (Test-Path $laravelLog) {
    Write-Host "--- storage\logs\laravel.log (Last $Lines lines) ---" -ForegroundColor Yellow
    if ($ErrorsOnly) {
        Get-Content $laravelLog | Select-String -Pattern "ERROR" | Select-Object -Last $Lines
    } else {
        Get-Content $laravelLog -Tail $Lines
    }
    Write-Host ""
} else {
    Write-Host "No storage\logs\laravel.log found." -ForegroundColor Gray
}

if (Test-Path $bgLogDir) {
    $logFiles = Get-ChildItem -Path $bgLogDir -Filter "*.log" -ErrorAction SilentlyContinue
    foreach ($lf in $logFiles) {
        if ($lf.Length -gt 0) {
            $isErr = $lf.Name.Contains("err")
            $color = if ($isErr) { "Red" } else { "Cyan" }
            Write-Host ("--- {0} ({1} bytes, last {2} lines) ---" -f $lf.Name, $lf.Length, $Lines) -ForegroundColor $color
            Get-Content $lf.FullName -Tail $Lines
            Write-Host ""
        }
    }
} else {
    Write-Host "No storage\logs\background\ folder found." -ForegroundColor Gray
}

