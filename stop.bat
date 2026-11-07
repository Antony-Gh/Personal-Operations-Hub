@echo off
setlocal EnableDelayedExpansion

title Personal Operations Hub - Stopper

echo ===================================================
echo     Stopping Personal Operations Hub Services
echo ===================================================
echo.

where powershell >nul 2>&1
if %ERRORLEVEL% equ 0 (
    powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0stop.ps1"
) else (
    set RUN_DIR=%~dp0.run
    for %%F in (laravel.pid scheduler.pid queue.pid vite.pid) do (
        if exist "!RUN_DIR!\%%F" (
            set /p PROC_PID=<"!RUN_DIR!\%%F"
            if defined PROC_PID taskkill /F /T /PID !PROC_PID! >nul 2>&1
            del "!RUN_DIR!\%%F" >nul 2>&1
            set PROC_PID=
        )
    )
    if exist "!RUN_DIR!\background-pids.json" del "!RUN_DIR!\background-pids.json" >nul 2>&1
    if exist "!RUN_DIR%" rmdir "!RUN_DIR%" >nul 2>&1
    echo Services terminated.
)

timeout /t 2 /nobreak >nul
