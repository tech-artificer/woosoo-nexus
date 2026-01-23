# Restart nginx only (for quick nginx.conf changes)
# Run as Administrator
# 
# Note: Use restart-for-lan.ps1 if you need to restart ALL services

$ErrorActionPreference = "Stop"

Write-Host "Restarting nginx..." -ForegroundColor Cyan

# Check Administrator privileges
$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]"Administrator")
if (-not $isAdmin) {
    Write-Host "[ERROR] This script requires Administrator privileges!" -ForegroundColor Red
    Write-Host ""
    Write-Host "Right-click PowerShell and select 'Run as Administrator'" -ForegroundColor Yellow
    exit 1
}

try {
    # Stop service
    Stop-Service woosoo-nginx -Force -ErrorAction SilentlyContinue
    Start-Sleep -Seconds 2
    
    # Kill any remaining processes
    Get-Process nginx -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue
    Start-Sleep -Seconds 1
    
    # Start service
    Start-Service woosoo-nginx
    Start-Sleep -Seconds 2
    
    $nginxStatus = (Get-Service woosoo-nginx).Status
    if ($nginxStatus -eq "Running") {
        Write-Host "[OK] nginx restarted successfully" -ForegroundColor Green
    } else {
        Write-Host "[ERROR] nginx failed to start (Status: $nginxStatus)" -ForegroundColor Red
        exit 1
    }
} catch {
    Write-Host "[ERROR] Error restarting nginx: $_" -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "Access URLs:" -ForegroundColor Yellow
Write-Host "  Tablet PWA: http://192.168.100.85:3000/" -ForegroundColor Cyan
Write-Host "  Admin Panel: http://192.168.100.85:8000/" -ForegroundColor Cyan
Write-Host ""
