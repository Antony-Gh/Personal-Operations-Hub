# Personal Operations Hub - Stop Services
Set-Location $PSScriptRoot

Write-Host "Stopping Personal Operations Hub background services..." -ForegroundColor Yellow

$ports = @(8000, 5173)
foreach ($port in $ports) {
    $connections = Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue
    if ($connections) {
        foreach ($conn in $connections) {
            $pidToStop = $conn.OwningProcess
            Write-Host "Terminating process on port $port (PID: $pidToStop)..." -ForegroundColor Cyan
            Stop-Process -Id $pidToStop -Force -ErrorAction SilentlyContinue
        }
    }
}

Write-Host "Services stopped cleanly." -ForegroundColor Green

