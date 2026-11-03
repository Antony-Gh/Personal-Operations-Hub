@echo off
setlocal EnableDelayedExpansion

title Personal Operations Hub - Stopper

echo ===================================================
echo     Stopping Personal Operations Hub Services
echo ===================================================
echo.

:: Stop process on port 8000
for /f "tokens=5" %%a in ('netstat -aon ^| findstr /R /C:":8000 .*LISTENING"') do (
    echo Terminating process on port 8000 (PID: %%a)...
    taskkill /F /PID %%a >nul 2>&1
)

:: Stop process on port 5173
for /f "tokens=5" %%a in ('netstat -aon ^| findstr /R /C:":5173 .*LISTENING"') do (
    echo Terminating process on port 5173 (PID: %%a)...
    taskkill /F /PID %%a >nul 2>&1
)

echo.
echo All services stopped cleanly.
timeout /t 2 /nobreak >nul

