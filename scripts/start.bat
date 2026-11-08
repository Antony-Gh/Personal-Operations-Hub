@echo off
setlocal EnableDelayedExpansion

title Personal Operations Hub - Launcher

:: Ensure running in the project root directory
cd /d "%~dp0.."

where powershell >nul 2>&1
if %ERRORLEVEL% equ 0 (
    powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0start.ps1"
) else (
    echo [ERROR] PowerShell is required to run Personal Operations Hub.
    pause
    exit /b 1
)
