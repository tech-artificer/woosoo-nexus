# Restart Woosoo Services to Apply LAN Configuration
# Run as Administrator

$ErrorActionPreference = "Stop"

Write-Host ""
Write-Host "=====================================" -ForegroundColor Cyan
Write-Host "  Woosoo LAN Configuration Restart  " -ForegroundColor Cyan
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

Write-Host "Restarting services to apply LAN configuration..." -ForegroundColor Yellow
Write-Host ""

# Restart nginx (critical for new configuration)
Write-Host "[1/3] Restarting nginx..." -ForegroundColor Yellow
try {
    Restart-Service woosoo-nginx -ErrorAction Stop
    Start-Sleep -Seconds 2
    $nginxStatus = (Get-Service woosoo-nginx).Status
    if ($nginxStatus -eq "Running") {
        Write-Host "  [OK] nginx restarted successfully" -ForegroundColor Green
    } else {
        Write-Host "  [WARN] nginx status: $nginxStatus" -ForegroundColor Yellow
    }
} catch {
    Write-Host "  [ERROR] Failed to restart nginx: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}

Write-Host ""

# Restart PHP-FPM (fixes admin panel PHP processing)
Write-Host "[2/3] Restarting PHP-FPM..." -ForegroundColor Yellow
try {
    Restart-Service woosoo-php-fpm -ErrorAction Stop
    Start-Sleep -Seconds 1
    $phpStatus = (Get-Service woosoo-php-fpm).Status
    if ($phpStatus -eq "Running") {
        Write-Host "  [OK] PHP-FPM restarted successfully" -ForegroundColor Green
    } else {
        Write-Host "  [WARN] PHP-FPM status: $phpStatus" -ForegroundColor Yellow
    }
} catch {
    Write-Host "  [ERROR] Failed to restart PHP-FPM: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host ""

# Restart Reverb (WebSocket server)
Write-Host "[3/3] Restarting Reverb..." -ForegroundColor Yellow
try {
    Restart-Service woosoo-reverb -ErrorAction Stop
    Start-Sleep -Seconds 1
    $reverbStatus = (Get-Service woosoo-reverb).Status
    if ($reverbStatus -eq "Running") {
        Write-Host "  [OK] Reverb restarted successfully" -ForegroundColor Green
    } else {
        Write-Host "  [WARN] Reverb status: $reverbStatus" -ForegroundColor Yellow
    }
} catch {
    Write-Host "  [ERROR] Failed to restart Reverb: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host ""
Write-Host "=====================================" -ForegroundColor Green
Write-Host "  Services Restarted Successfully   " -ForegroundColor Green
Write-Host "=====================================" -ForegroundColor Green
Write-Host ""

# Verify deployment
Write-Host "Service Status:" -ForegroundColor Cyan
Get-Service woosoo-* | Select-Object Name, Status, StartType | Format-Table -AutoSize

Write-Host ""
Write-Host "Testing LAN Access..." -ForegroundColor Cyan

# Test local IP access
Write-Host "  Testing https://192.168.100.85 ..." -ForegroundColor Gray
try {
    # Bypass certificate validation for self-signed cert
    [System.Net.ServicePointManager]::ServerCertificateValidationCallback = {$true}
    $response = Invoke-WebRequest -Uri "https://192.168.100.85" -UseBasicParsing -TimeoutSec 10 -ErrorAction Stop
    Write-Host "  [OK] Server responding (HTTP $($response.StatusCode))" -ForegroundColor Green
} catch {
    Write-Host "  [WARN] Could not test endpoint: $($_.Exception.Message)" -ForegroundColor Yellow
    Write-Host "  [INFO] This may be normal if TLS/PowerShell version mismatch" -ForegroundColor Gray
}

Write-Host ""
Write-Host "Next Steps:" -ForegroundColor Yellow
Write-Host "  1. From another device on LAN, open browser" -ForegroundColor Gray
Write-Host "  2. Navigate to: https://192.168.100.85" -ForegroundColor Gray
Write-Host "  3. Accept certificate warning (or install root CA)" -ForegroundColor Gray
Write-Host "  4. Verify admin panel loads" -ForegroundColor Gray
Write-Host ""
Write-Host "Firewall Status:" -ForegroundColor Cyan
Get-NetFirewallRule -DisplayName "Woosoo*" -ErrorAction SilentlyContinue | Select-Object DisplayName, Enabled, Direction, Action | Format-Table -AutoSize

Write-Host ""
Write-Host "For detailed instructions, see:" -ForegroundColor Yellow
Write-Host "  C:\laragon\www\woosoo\LAN_DEPLOYMENT_COMPLETE.md" -ForegroundColor Gray
Write-Host ""
