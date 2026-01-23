# Woosoo Nexus - Reset Deployment
# Complete clean reinstall (stop, remove, clean, reinstall)

$ErrorActionPreference = "Stop"

$BASE_DIR = "C:\laragon\www\woosoo"
$NSSM = "$BASE_DIR\bin\nssm\win64\nssm.exe"
$LOGS_DIR = "$BASE_DIR\logs"

Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "Woosoo Nexus Complete Reset" -ForegroundColor Cyan
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "WARNING: This will:" -ForegroundColor Yellow
Write-Host "  1. Stop all services" -ForegroundColor Yellow
Write-Host "  2. Remove all services" -ForegroundColor Yellow
Write-Host "  3. Clear all logs" -ForegroundColor Yellow
Write-Host "  4. Reinstall from scratch" -ForegroundColor Yellow
Write-Host ""

$confirm = Read-Host "Continue? (yes/no)"
if ($confirm -ne "yes") {
    Write-Host "Cancelled." -ForegroundColor Gray
    exit 0
}

# Check Administrator
$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]"Administrator")
if (-not $isAdmin) {
    Write-Host "ERROR: This script requires Administrator privileges!" -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "[1/5] Stopping services..." -ForegroundColor Cyan
@("woosoo-reverb", "woosoo-php-fpm", "woosoo-nginx") | ForEach-Object {
    $svc = Get-Service -Name $_ -ErrorAction SilentlyContinue
    if ($svc) {
        Stop-Service -Name $_ -Force -ErrorAction SilentlyContinue
        Start-Sleep -Seconds 1
    }
}
Write-Host "  [OK] Services stopped" -ForegroundColor Green

Write-Host ""
Write-Host "[2/5] Removing services..." -ForegroundColor Cyan
@("woosoo-reverb", "woosoo-php-fpm", "woosoo-nginx") | ForEach-Object {
    $svc = Get-Service -Name $_ -ErrorAction SilentlyContinue
    if ($svc) {
        & $NSSM remove $_ confirm 2>$null
        Start-Sleep -Seconds 1
    }
}
Write-Host "  [OK] Services removed" -ForegroundColor Green

Write-Host ""
Write-Host "[3/5] Clearing logs..." -ForegroundColor Cyan
@("$LOGS_DIR\nginx", "$LOGS_DIR\php", "$LOGS_DIR\reverb") | ForEach-Object {
    if (Test-Path $_) {
        Get-ChildItem $_ -File | Remove-Item -Force -ErrorAction SilentlyContinue
    }
}
Write-Host "  [OK] Logs cleared" -ForegroundColor Green

Write-Host ""
Write-Host "[4/5] Setting up prerequisites..." -ForegroundColor Cyan
& "$PSScriptRoot\setup-prerequisites.ps1"

Write-Host "[5/5] Running fresh installation..." -ForegroundColor Cyan
& "$PSScriptRoot\install-services.ps1"

Write-Host ""
Write-Host "[OK] Complete reset finished!" -ForegroundColor Green
Write-Host ""
Write-Host "Services are installed. Run .\start-production.ps1 to start them." -ForegroundColor Gray
