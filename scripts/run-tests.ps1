param(
    [string]$Filter = "",
    [switch]$Detailed
)

# Personal Operations Hub - Test Runner Utility (PowerShell 5.1 Compatible)
$ErrorActionPreference = "Stop"

$ProjectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $ProjectRoot

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "    Personal Operations Hub - Automated Test Suite        " -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host ""

$sw = [System.Diagnostics.Stopwatch]::StartNew()

$argsList = @("artisan", "test")
if (-not $Detailed) {
    $argsList += "--compact"
}
if ($Filter) {
    $argsList += "--filter=$Filter"
}

Write-Host "Executing test runner..." -ForegroundColor Yellow
& php @argsList
$exitCode = $LASTEXITCODE

$sw.Stop()

Write-Host ""
if ($exitCode -eq 0) {
    Write-Host ("[SUCCESS] All tests passed cleanly in {0:N2}s!" -f $sw.Elapsed.TotalSeconds) -ForegroundColor Green
} else {
    Write-Host ("[FAILURE] Tests encountered failures (exit code: $exitCode, duration: {0:N2}s)" -f $sw.Elapsed.TotalSeconds) -ForegroundColor Red
}

exit $exitCode
