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
    if exist "%RUN_DIR%\laravel.pid" (
        set /p LARAVEL_PID=<"%RUN_DIR%\laravel.pid"
        if defined LARAVEL_PID taskkill /F /T /PID !LARAVEL_PID! >nul 2>&1
        del "%RUN_DIR%\laravel.pid" >nul 2>&1
    )
    if exist "%RUN_DIR%\vite.pid" (
        set /p VITE_PID=<"%RUN_DIR%\vite.pid"
        if defined VITE_PID taskkill /F /T /PID !VITE_PID! >nul 2>&1
        del "%RUN_DIR%\vite.pid" >nul 2>&1
    )
    if exist "%RUN_DIR%" rmdir "%RUN_DIR%" >nul 2>&1
    echo Services terminated.
)

timeout /t 2 /nobreak >nul
