@echo off
setlocal EnableDelayedExpansion

title Personal Operations Hub - Unified Controller

:: 1. Ensure working directory is set to project root
cd /d "%~dp0"

:: 2. Check for Administrative privileges and elevate if needed
net session >nul 2>&1
if %ERRORLEVEL% neq 0 (
    echo ==============================================================================
    echo   Requesting Administrator privileges for Personal Operations Hub Controller...
    echo ==============================================================================
    powershell -NoProfile -ExecutionPolicy Bypass -Command "try { Start-Process powershell.exe -ArgumentList @('-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', '%~dp0controller.ps1', '%*') -Verb RunAs -WorkingDirectory '%~dp0' -ErrorAction Stop } catch { exit 99 }"
    if !ERRORLEVEL! equ 0 exit /b 0
    echo [NOTE] UAC elevation unavailable in this session. Continuing in current shell...
    echo.
)

:: 3. Execute Unified Controller
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0controller.ps1" %*

if %ERRORLEVEL% neq 0 (
    echo.
    echo Controller exited with code %ERRORLEVEL%.
    pause
)
