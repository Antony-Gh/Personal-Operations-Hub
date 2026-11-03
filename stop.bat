@echo off
setlocal EnableDelayedExpansion

title Personal Operations Hub - Stopper

echo ===================================================
echo     Stopping Personal Operations Hub Services
echo ===================================================
echo.

set RUN_DIR=%~dp0.run
set STOPPED=0

:: Check for Laravel PID file
if exist "%RUN_DIR%\laravel.pid" (
    set /p LARAVEL_PID=<"%RUN_DIR%\laravel.pid"
    if defined LARAVEL_PID (
        echo Terminating tracked Laravel backend (PID: !LARAVEL_PID!)...
        taskkill /F /T /PID !LARAVEL_PID! >nul 2>&1
        set /a STOPPED+=1
    )
    del "%RUN_DIR%\laravel.pid" >nul 2>&1
)

:: Check for Vite PID file
if exist "%RUN_DIR%\vite.pid" (
    set /p VITE_PID=<"%RUN_DIR%\vite.pid"
    if defined VITE_PID (
        echo Terminating tracked Vite compiler (PID: !VITE_PID!)...
        taskkill /F /T /PID !VITE_PID! >nul 2>&1
        set /a STOPPED+=1
    )
    del "%RUN_DIR%\vite.pid" >nul 2>&1
)

if %STOPPED% gtr 0 (
    echo.
    echo Tracked services terminated cleanly.
) else (
    echo No tracked PID files found in .run\.
    echo Checking for orphaned processes on ports 8000 and 5173...

    for /f "tokens=5" %%a in ('netstat -aon ^| findstr /R /C:":8000 .*LISTENING"') do (
        echo Terminating orphaned process on port 8000 (PID: %%a)...
        taskkill /F /T /PID %%a >nul 2>&1
    )

    for /f "tokens=5" %%a in ('netstat -aon ^| findstr /R /C:":5173 .*LISTENING"') do (
        echo Terminating orphaned process on port 5173 (PID: %%a)...
        taskkill /F /T /PID %%a >nul 2>&1
    )
    echo Orphaned process cleanup complete.
)

if exist "%RUN_DIR%" (
    rmdir "%RUN_DIR%" >nul 2>&1
)

echo.
timeout /t 2 /nobreak >nul
