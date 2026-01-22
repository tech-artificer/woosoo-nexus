# Setup local domain resolution for Woosoo
# Run this as Administrator to add entries to the hosts file

param(
    [Parameter(Mandatory=$false)]
    [string]$ServerIP = "127.0.0.1"
)

$hostsFile = "C:\Windows\System32\drivers\etc\hosts"

Write-Host "Woosoo Local Domain Setup" -ForegroundColor Cyan
Write-Host "========================" -ForegroundColor Cyan
Write-Host ""

# Auto-detect server IP if not explicitly localhost
if ($ServerIP -eq "127.0.0.1") {
    $networkIP = (Get-NetIPAddress -AddressFamily IPv4 | Where-Object {$_.IPAddress -notlike "127.*" -and $_.IPAddress -notlike "169.254.*"} | Select-Object -First 1).IPAddress
    if ($networkIP) {
        Write-Host "[INFO] Detected network IP: $networkIP" -ForegroundColor Cyan
        Write-Host "[INFO] Using 127.0.0.1 for local-only access" -ForegroundColor Gray
        Write-Host "[INFO] To enable network access, run: .\setup-local-domains.ps1 -ServerIP $networkIP" -ForegroundColor Yellow
    }
}

Write-Host "Server IP: $ServerIP" -ForegroundColor Yellow
Write-Host ""

# Check if running as admin
$isAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole] "Administrator")

if (-not $isAdmin) {
    Write-Host "[ERROR] This script requires Administrator privileges!" -ForegroundColor Red
    Write-Host ""
    Write-Host "Please right-click PowerShell and select 'Run as Administrator'" -ForegroundColor Yellow
    exit 1
}

Write-Host "Adding local domain entries to hosts file..." -ForegroundColor Yellow
Write-Host ""

# Read current hosts content
$content = Get-Content $hostsFile -Encoding UTF8

# Check for existing entries
$hasAdmin = $content -match "admin\.woosoo\.local"
$hasApp = $content -match "app\.woosoo\.local"

if ($hasAdmin -and $hasApp) {
    Write-Host "[OK] Both entries already exist in hosts file" -ForegroundColor Green
    Write-Host ""
    Write-Host "Current entries:" -ForegroundColor Cyan
    $content | Select-String "woosoo.local"
} else {
    # Add entries
    if (-not $hasAdmin) {
        Write-Host "[+] Adding admin.woosoo.local -> $ServerIP" -ForegroundColor Green
        Add-Content $hostsFile "`n$ServerIP       admin.woosoo.local" -Encoding UTF8
    }
    
    if (-not $hasApp) {
        Write-Host "[+] Adding app.woosoo.local -> $ServerIP" -ForegroundColor Green
        Add-Content $hostsFile "`n$ServerIP       app.woosoo.local" -Encoding UTF8
    }
    
    Write-Host ""
    Write-Host "[OK] Entries added successfully" -ForegroundColor Green
}

Write-Host ""
Write-Host "Testing DNS resolution..." -ForegroundColor Cyan

# Flush DNS cache
ipconfig /flushdns | Out-Null
Write-Host "[OK] DNS cache flushed" -ForegroundColor Green

# Test resolution
Write-Host ""
$adminResolves = Test-Connection -ComputerName "admin.woosoo.local" -Count 1 -Quiet -ErrorAction SilentlyContinue
$appResolves = Test-Connection -ComputerName "app.woosoo.local" -Count 1 -Quiet -ErrorAction SilentlyContinue

if ($adminResolves) {
    Write-Host "[OK] admin.woosoo.local resolves" -ForegroundColor Green
} else {
    Write-Host "[WARN] admin.woosoo.local may not resolve yet" -ForegroundColor Yellow
}

if ($appResolves) {
    Write-Host "[OK] app.woosoo.local resolves" -ForegroundColor Green
} else {
    Write-Host "[WARN] app.woosoo.local may not resolve yet" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "Setup complete!" -ForegroundColor Green
Write-Host ""
Write-Host "You can now access:" -ForegroundColor Cyan
Write-Host "  - https://admin.woosoo.local (Admin Panel)" -ForegroundColor Gray
Write-Host "  - https://app.woosoo.local (Tablet PWA)" -ForegroundColor Gray
Write-Host ""
Write-Host "Browser Security Notes:" -ForegroundColor Yellow
Write-Host "  - Certificates are self-signed" -ForegroundColor Gray
Write-Host "  - Your browser will show 'Not Secure' warning" -ForegroundColor Gray
Write-Host "  - This is normal for local development" -ForegroundColor Gray
Write-Host "  - Click 'Advanced' and 'Proceed' to continue" -ForegroundColor Gray
