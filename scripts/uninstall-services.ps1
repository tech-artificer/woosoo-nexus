# Woosoo Nexus - Uninstall Services
# Safely removes Windows services

$ErrorActionPreference = "Stop"

$BASE_DIR = "C:\laragon\www\woosoo"
$NSSM = "$BASE_DIR\bin\nssm\win64\nssm.exe"

Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "Woosoo Nexus Service Uninstallation" -ForegroundColor Cyan
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host ""

# Check Administrator
$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]"Administrator")
if (-not $isAdmin) {
    Write-Host "ERROR: This script requires Administrator privileges!" -ForegroundColor Red
    Write-Host "Right-click PowerShell and select 'Run as Administrator'" -ForegroundColor Yellow
    exit 1
}

if (-not (Test-Path $NSSM)) {
    Write-Host "ERROR: NSSM not found at $NSSM" -ForegroundColor Red
    exit 1
}

Write-Host "This will remove all Woosoo services." -ForegroundColor Yellow
$confirm = Read-Host "Continue? (yes/no)"
if ($confirm -ne "yes") {
    Write-Host "Cancelled." -ForegroundColor Gray
    exit 0
}

Write-Host ""
Write-Host "Stopping services..." -ForegroundColor Yellow
@("woosoo-reverb", "woosoo-php-fpm", "woosoo-nginx") | ForEach-Object {
    $svc = Get-Service -Name $_ -ErrorAction SilentlyContinue
    if ($svc) {
        Write-Host "  Stopping $_..." -ForegroundColor Gray
        Stop-Service -Name $_ -Force -ErrorAction SilentlyContinue
        Start-Sleep -Seconds 1
    }
}

Write-Host ""
Write-Host "Removing services..." -ForegroundColor Yellow
@("woosoo-reverb", "woosoo-php-fpm", "woosoo-nginx") | ForEach-Object {
    $svc = Get-Service -Name $_ -ErrorAction SilentlyContinue
    if ($svc) {
        Write-Host "  Removing $_..." -ForegroundColor Gray
        & $NSSM remove $_ confirm 2>$null
        Start-Sleep -Seconds 1
    }
}

Write-Host ""
Write-Host "[OK] Services uninstalled successfully!" -ForegroundColor Green
Write-Host ""
Write-Host "You can now reinstall with .\install-services.ps1" -ForegroundColor Gray
