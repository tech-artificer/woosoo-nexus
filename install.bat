@echo off
echo =======================================
echo   Laravel App Service Installer
echo =======================================
echo.

:: Run the PowerShell installer with elevated permissions
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0install.ps1"

pause