@echo off
setlocal EnableDelayedExpansion

title Personal Operations Hub - Launcher

:: 1. Ensure running from the project root directory
cd /d "%~dp0"

echo ===================================================
echo     Personal Operations Hub - System Launcher
echo ===================================================
echo.

:: 2. Check Prerequisites
echo [1/6] Checking system prerequisites...

where php >nul 2>&1
if %ERRORLEVEL% neq 0 (
    echo [ERROR] PHP is not found in your PATH.
    echo Please install PHP 8.2 or newer and add it to your PATH.
    pause
    exit /b 1
)

where composer >nul 2>&1
if %ERRORLEVEL% neq 0 (
    echo [ERROR] Composer is not found in your PATH.
    echo Please install Composer: https://getcomposer.org/
    pause
    exit /b 1
)

where node >nul 2>&1
if %ERRORLEVEL% neq 0 (
    echo [ERROR] Node.js is not found in your PATH.
    echo Please install Node.js 18 or newer: https://nodejs.org/
    pause
    exit /b 1
)

where npm >nul 2>&1
if %ERRORLEVEL% neq 0 (
    echo [ERROR] npm is not found in your PATH.
    pause
    exit /b 1
)

echo       All CLI prerequisites detected.

:: 3. Check dependencies & files
echo [2/6] Verifying project environment and dependencies...

if not exist ".env" (
    echo       .env file not found. Creating from .env.example...
    copy .env.example .env >nul
    call php artisan key:generate
)

if not exist "database" mkdir database

if not exist "database\database.sqlite" (
    echo       Initializing SQLite database file...
    type nul > "database\database.sqlite"
) else (
    echo       Creating database safety backup...
    copy /y "database\database.sqlite" "database\database.sqlite.bak" >nul
)

if not exist "vendor\autoload.php" (
    echo [ERROR] PHP dependencies are missing. Run: composer install
    pause
    exit /b 1
)

if not exist "node_modules" (
    echo [ERROR] Node dependencies are missing. Run: npm install
    pause
    exit /b 1
)

:: 4. Run safe, non-destructive migrations
echo [3/6] Running safe database migrations...
call php artisan migrate --force

:: 5. Create .run folder and spawn services with PID tracking
echo [4/6] Spawning background services with PID tracking...
if not exist ".run" mkdir ".run"

set APP_URL=http://127.0.0.1:8000
set VITE_URL=http://127.0.0.1:5173

:: Clean up stale PID files if processes are no longer running
powershell -NoProfile -Command ^
    "foreach ($f in @('.run\laravel.pid', '.run\vite.pid')) { " ^
    "  if (Test-Path $f) { " ^
    "    $raw = (Get-Content $f -ErrorAction SilentlyContinue | Out-String).Trim(); " ^
    "    if ($raw -match '^\d+$') { " ^
    "      $proc = Get-Process -Id ([int]$raw) -ErrorAction SilentlyContinue; " ^
    "      if (-not $proc) { Remove-Item $f -Force -ErrorAction SilentlyContinue } " ^
    "    } else { Remove-Item $f -Force -ErrorAction SilentlyContinue } " ^
    "  } " ^
    "}"

powershell -NoProfile -Command ^
    "$p8000 = Get-NetTCPConnection -LocalPort 8000 -State Listen -ErrorAction SilentlyContinue; " ^
    "if (-not $p8000) { " ^
    "  $lp = Start-Process -FilePath 'php' -ArgumentList 'artisan serve --host=127.0.0.1 --port=8000' -WorkingDirectory '%~dp0' -PassThru; " ^
    "  $lp.Id | Out-File -FilePath '.run\laravel.pid' -Encoding ascii; " ^
    "  Write-Host '      Laravel backend started (PID: ' $lp.Id ')' " ^
    "} else { Write-Host '      Port 8000 already bound.' }"

powershell -NoProfile -Command ^
    "$p5173 = Get-NetTCPConnection -LocalPort 5173 -State Listen -ErrorAction SilentlyContinue; " ^
    "if (-not $p5173) { " ^
    "  $npm = 'npm'; " ^
    "  $cmd = Get-Command 'npm.cmd' -ErrorAction SilentlyContinue; " ^
    "  if ($cmd) { $npm = $cmd.Source }; " ^
    "  $vp = Start-Process -FilePath $npm -ArgumentList 'run dev' -WorkingDirectory '%~dp0' -PassThru; " ^
    "  $vp.Id | Out-File -FilePath '.run\vite.pid' -Encoding ascii; " ^
    "  Write-Host '      Vite frontend started (PID: ' $vp.Id ')' " ^
    "} else { Write-Host '      Port 5173 already bound.' }"

:: 6. Wait for application AND Vite readiness
echo [5/6] Waiting for application and Vite frontend to be fully ready...

set /a attempts=0
:WAIT_LOOP
set /a attempts+=1
if %attempts% gtr 35 (
    echo [WARNING] Server startup took longer than usual.
    echo Opening browser at %APP_URL%...
    goto OPEN_BROWSER
)

powershell -NoProfile -Command ^
    "try { " ^
    "  $r1 = Invoke-WebRequest -Uri '%APP_URL%' -UseBasicParsing -TimeoutSec 2; " ^
    "  $r2 = Invoke-WebRequest -Uri '%VITE_URL%/@vite/client' -UseBasicParsing -TimeoutSec 2; " ^
    "  if ($r1.StatusCode -lt 500 -and $r2.StatusCode -eq 200) { exit 0 } else { exit 1 } " ^
    "} catch { exit 1 }" >nul 2>&1

if %ERRORLEVEL% neq 0 (
    timeout /t 1 /nobreak >nul
    goto WAIT_LOOP
)

:OPEN_BROWSER
echo [6/6] Both Laravel and Vite are ready!
echo.
echo ===================================================
echo   Access your workspace at: %APP_URL%
echo ===================================================
echo.
echo Opening default web browser...
start "" "%APP_URL%"

echo.
echo Services are running with tracked PIDs in .run\
echo To stop services cleanly, run stop.bat or powershell -File .\stop.ps1
echo.
pause
