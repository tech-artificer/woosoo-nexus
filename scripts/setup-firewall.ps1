# Setup Windows Firewall Rules for Woosoo LAN Access
# Run as Administrator
# 
# Opens ports for:
# - 443 (HTTPS) for nginx web server
# - 6001 (WebSocket) for Reverb real-time connections

$ErrorActionPreference = "Stop"

Write-Host ""
Write-Host "=====================================" -ForegroundColor Cyan
Write-Host "  Woosoo Firewall Configuration     " -ForegroundColor Cyan
Write-Host "=====================================" -ForegroundColor Cyan
Write-Host ""

# Check Administrator privileges
$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]"Administrator")
if (-not $isAdmin) {
    Write-Host "[ERROR] This script requires Administrator privileges!" -ForegroundColor Red
    Write-Host ""
    Write-Host "Right-click PowerShell and select 'Run as Administrator'" -ForegroundColor Yellow
    exit 1
}

Write-Host "Adding firewall rules for LAN access..." -ForegroundColor Yellow
Write-Host ""

# Port 443 - HTTPS (nginx)
Write-Host "[1/2] Configuring HTTPS (port 443)..." -ForegroundColor Yellow
try {
    $existingRule = Get-NetFirewallRule -DisplayName "Woosoo HTTPS (nginx)" -ErrorAction SilentlyContinue
    if ($existingRule) {
        Write-Host "  [INFO] Rule already exists, removing old rule..." -ForegroundColor Gray
        Remove-NetFirewallRule -DisplayName "Woosoo HTTPS (nginx)" -ErrorAction Stop
    }
    
    New-NetFirewallRule `
        -DisplayName "Woosoo HTTPS (nginx)" `
        -Description "Allow HTTPS access to Woosoo POS system from LAN" `
        -Direction Inbound `
        -LocalPort 443 `
        -Protocol TCP `
        -Action Allow `
        -Profile Private,Domain `
        -Enabled True `
        -ErrorAction Stop | Out-Null
    
    Write-Host "  [OK] HTTPS rule added successfully" -ForegroundColor Green
} catch {
    Write-Host "  [ERROR] Failed to add HTTPS rule: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}

Write-Host ""

# Port 6001 - WebSocket (Reverb)
Write-Host "[2/2] Configuring WebSocket (port 6001)..." -ForegroundColor Yellow
try {
    $existingRule = Get-NetFirewallRule -DisplayName "Woosoo WebSocket (Reverb)" -ErrorAction SilentlyContinue
    if ($existingRule) {
        Write-Host "  [INFO] Rule already exists, removing old rule..." -ForegroundColor Gray
        Remove-NetFirewallRule -DisplayName "Woosoo WebSocket (Reverb)" -ErrorAction Stop
    }
    
    New-NetFirewallRule `
        -DisplayName "Woosoo WebSocket (Reverb)" `
        -Description "Allow WebSocket connections to Woosoo Reverb server from LAN" `
        -Direction Inbound `
        -LocalPort 6001 `
        -Protocol TCP `
        -Action Allow `
        -Profile Private,Domain `
        -Enabled True `
        -ErrorAction Stop | Out-Null
    
    Write-Host "  [OK] WebSocket rule added successfully" -ForegroundColor Green
} catch {
    Write-Host "  [ERROR] Failed to add WebSocket rule: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "=====================================" -ForegroundColor Green
Write-Host "  Firewall Configuration Complete   " -ForegroundColor Green
Write-Host "=====================================" -ForegroundColor Green
Write-Host ""
Write-Host "Active Woosoo firewall rules:" -ForegroundColor Cyan
Get-NetFirewallRule -DisplayName "Woosoo*" | Select-Object DisplayName, Enabled, Direction, Action | Format-Table -AutoSize
Write-Host ""
Write-Host "LAN devices can now access:" -ForegroundColor Yellow
Write-Host "  - HTTPS (port 443): Admin & Tablet PWA" -ForegroundColor Gray
Write-Host "  - WebSocket (port 6001): Real-time updates" -ForegroundColor Gray
Write-Host ""
