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
    echo Please install PHP 8.2 or newer (recommended PHP 8.4) and add it to your PATH.
    echo Download: https://windows.php.net/download/
    pause
    exit /b 1
)

where composer >nul 2>&1
if %ERRORLEVEL% neq 0 (
    echo [ERROR] Composer is not found in your PATH.
    echo Please install Composer: https://getcomposer.org/download/
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
    echo Please ensure npm is installed alongside Node.js.
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

if not exist "database\database.sqlite" (
    echo       Initializing SQLite database file...
    type nul > "database\database.sqlite"
)

if not exist "vendor\autoload.php" (
    echo [ERROR] PHP dependencies are missing (vendor directory not found).
    echo Please run: composer install
    pause
    exit /b 1
)

if not exist "node_modules" (
    echo [ERROR] Node dependencies are missing (node_modules directory not found).
    echo Please run: npm install
    pause
    exit /b 1
)

:: 4. Run safe, non-destructive migrations
echo [3/6] Running safe database migrations...
call php artisan migrate --force
if %ERRORLEVEL% neq 0 (
    echo [WARNING] Database migration check returned an issue. Continuing...
)

:: 5. Check ports and launch servers
echo [4/6] Checking server ports and launching services...

set APP_URL=http://127.0.0.1:8000

:: Check if port 8000 is already active
netstat -ano | findstr /R /C:":8000 .*LISTENING" >nul 2>&1
if %ERRORLEVEL% equ 0 (
    echo       Laravel server already running on port 8000.
) else (
    echo       Starting Laravel backend server (port 8000)...
    start "Personal Operations Hub - Laravel Backend" cmd /c "php artisan serve --host=127.0.0.1 --port=8000"
)

:: Check if Vite (port 5173) is already active
netstat -ano | findstr /R /C:":5173 .*LISTENING" >nul 2>&1
if %ERRORLEVEL% equ 0 (
    echo       Vite dev server already running on port 5173.
) else (
    echo       Starting Vite frontend server (port 5173)...
    start "Personal Operations Hub - Vite Frontend" cmd /c "npm run dev"
)

:: 6. Wait for application readiness
echo [5/6] Waiting for application to be ready...

set /a attempts=0
:WAIT_LOOP
set /a attempts+=1
if %attempts% gtr 30 (
    echo [WARNING] Server took longer than expected to respond.
    echo Opening browser anyway at %APP_URL%...
    goto OPEN_BROWSER
)

powershell -NoProfile -Command "try { $r = Invoke-WebRequest -Uri '%APP_URL%' -UseBasicParsing -TimeoutSec 2; if ($r.StatusCode -lt 500) { exit 0 } else { exit 1 } } catch { exit 1 }" >nul 2>&1
if %ERRORLEVEL% neq 0 (
    timeout /t 1 /nobreak >nul
    goto WAIT_LOOP
)

:OPEN_BROWSER
echo [6/6] Application is ready!
echo.
echo ===================================================
echo   Access your workspace at: %APP_URL%
echo ===================================================
echo.
echo Opening default web browser...
start "" "%APP_URL%"

echo.
echo Services are running in separate windows.
echo To stop services, you may run stop.bat or close their respective terminal windows.
echo.
pause

