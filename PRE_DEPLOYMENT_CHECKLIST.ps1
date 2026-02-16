# ============================================
# WOOSOO NEXUS PRE-DEPLOYMENT CHECKLIST
# ============================================

Write-Host "Starting Pre-Deployment Checklist..." -ForegroundColor Cyan
Write-Host ""

# 1. Administrator Check
Write-Host "1. Administrator Access" -ForegroundColor Yellow
$isAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole] "Administrator")
Write-Host "   $(if ($isAdmin) { '✓' } else { '✗' }) Administrator: $(if ($isAdmin) { 'YES' } else { 'NO (re-run PowerShell as Administrator)' })" -ForegroundColor $(if ($isAdmin) { 'Green' } else { 'Red' })
Write-Host ""

# 2. Directory Structure
Write-Host "2. Directory Structure" -ForegroundColor Yellow
$dirs = @(
    "C:\laragon\www\woosoo\apps\woosoo-nexus",
    "C:\laragon\www\woosoo\apps\tablet-ordering-pwa",
    "C:\laragon\www\woosoo\certs",
    "C:\laragon\www\woosoo\configs",
    "C:\laragon\www\woosoo\logs",
    "C:\laragon\www\woosoo\bin\nginx",
    "C:\laragon\www\woosoo\bin\php",
    "C:\laragon\www\woosoo\bin\nssm"
)
$all_exist = $true
foreach ($dir in $dirs) {
    $exists = Test-Path $dir
    Write-Host "   $(if ($exists) { '✓' } else { '✗' }) $(Split-Path $dir -Leaf)" -ForegroundColor $(if ($exists) { 'Green' } else { 'Red' })
    if (-not $exists) { $all_exist = $false }
}
Write-Host ""

# 3. Certificates
Write-Host "3. SSL Certificates" -ForegroundColor Yellow
$certs = @(
    "C:\laragon\www\woosoo\certs\admin.woosoo.local.pem",
    "C:\laragon\www\woosoo\certs\admin.woosoo.local-key.pem",
    "C:\laragon\www\woosoo\certs\app.woosoo.local.pem",
    "C:\laragon\www\woosoo\certs\app.woosoo.local-key.pem"
)
$certs_ok = $true
foreach ($cert in $certs) {
    $exists = Test-Path $cert
    $name = Split-Path $cert -Leaf
    Write-Host "   $(if ($exists) { '✓' } else { '✗' }) $name" -ForegroundColor $(if ($exists) { 'Green' } else { 'Red' })
    if (-not $exists) { $certs_ok = $false }
}
Write-Host ""

# 4. Binaries
Write-Host "4. Required Binaries" -ForegroundColor Yellow
$binaries = @(
    "C:\laragon\www\woosoo\bin\nginx\nginx.exe",
    "C:\laragon\www\woosoo\bin\php\php-cgi.exe",
    "C:\laragon\www\woosoo\bin\nssm\win64\nssm.exe"
)
$bins_ok = $true
foreach ($binary in $binaries) {
    $exists = Test-Path $binary
    $name = Split-Path $binary -Leaf
    Write-Host "   $(if ($exists) { '✓' } else { '✗' }) $name" -ForegroundColor $(if ($exists) { 'Green' } else { 'Red' })
    if (-not $exists) { $bins_ok = $false }
}
Write-Host ""

# 5. Configuration Files
Write-Host "5. Configuration Files" -ForegroundColor Yellow
$configs = @(
    "C:\laragon\www\woosoo\configs\nginx.conf",
    "C:\laragon\www\woosoo\apps\woosoo-nexus\.env",
    "C:\laragon\www\woosoo\apps\tablet-ordering-pwa\.env"
)
$configs_ok = $true
foreach ($config in $configs) {
    $exists = Test-Path $config
    $name = Split-Path $config -Leaf
    Write-Host "   $(if ($exists) { '✓' } else { '✗' }) $name" -ForegroundColor $(if ($exists) { 'Green' } else { 'Red' })
    if (-not $exists) { $configs_ok = $false }
}
Write-Host ""

# 6. Build Artifacts
Write-Host "6. Build Artifacts (npm run build)" -ForegroundColor Yellow
$builds = @(
    @{ Name = "Admin Panel"; Path = "C:\laragon\www\woosoo\apps\woosoo-nexus\public\build" },
    @{ Name = "Tablet PWA"; Path = "C:\laragon\www\woosoo\apps\tablet-ordering-pwa\.output\public" }
)
$builds_ok = $true
foreach ($build in $builds) {
    $exists = Test-Path $build.Path
    Write-Host "   $(if ($exists) { '✓' } else { '✗' }) $($build.Name)" -ForegroundColor $(if ($exists) { 'Green' } else { 'Red' })
    if (-not $exists) { $builds_ok = $false }
}
Write-Host ""

# 7. Scripts
Write-Host "7. Deployment Scripts" -ForegroundColor Yellow
$scripts = @(
    "C:\laragon\www\woosoo\apps\woosoo-nexus\scripts\install-services.ps1",
    "C:\laragon\www\woosoo\apps\woosoo-nexus\scripts\start-production.ps1",
    "C:\laragon\www\woosoo\apps\woosoo-nexus\scripts\stop-production.ps1",
    "C:\laragon\www\woosoo\apps\woosoo-nexus\scripts\check-services.ps1"
)
$scripts_ok = $true
foreach ($script in $scripts) {
    $exists = Test-Path $script
    $name = Split-Path $script -Leaf
    Write-Host "   $(if ($exists) { '✓' } else { '✗' }) $name" -ForegroundColor $(if ($exists) { 'Green' } else { 'Red' })
    if (-not $exists) { $scripts_ok = $false }
}
Write-Host ""

# 8. MySQL
Write-Host "8. MySQL Database" -ForegroundColor Yellow
$mysql_test = Test-NetConnection -ComputerName 127.0.0.1 -Port 3306 -WarningAction SilentlyContinue
$mysql_ok = $mysql_test.TcpTestSucceeded
Write-Host "   $(if ($mysql_ok) { '✓' } else { '✗' }) MySQL Server on 127.0.0.1:3306 $(if ($mysql_ok) { '(Running)' } else { '(NOT RUNNING - Start MySQL first!)' })" -ForegroundColor $(if ($mysql_ok) { 'Green' } else { 'Red' })
Write-Host ""

# 9. Port Availability
Write-Host "9. Port Availability" -ForegroundColor Yellow
$ports = @(
    @{ Name = "HTTPS"; Port = 443 },
    @{ Name = "FastCGI"; Port = 9000 },
    @{ Name = "WebSocket"; Port = 6001 }
)
$ports_ok = $true
foreach ($port in $ports) {
    $test = netstat -ano 2>$null | Select-String ":$($port.Port)" | Select-String "LISTENING"
    $available = -not $test
    Write-Host "   $(if ($available) { '✓' } else { '⚠' }) Port $($port.Port) ($($port.Name)) $(if ($available) { '(Free)' } else { '(In use)' })" -ForegroundColor $(if ($available) { 'Green' } else { 'Yellow' })
}
Write-Host ""

# 10. Hosts File
Write-Host "10. Windows Hosts File" -ForegroundColor Yellow
$hostsPath = "C:\Windows\System32\drivers\etc\hosts"
$hostsContent = Get-Content $hostsPath
$hasAdmin = $hostsContent -match "admin\.woosoo\.local"
$hasApp = $hostsContent -match "app\.woosoo\.local"
Write-Host "   $(if ($hasAdmin) { '✓' } else { '⚠' }) admin.woosoo.local entry" -ForegroundColor $(if ($hasAdmin) { 'Green' } else { 'Yellow' })
Write-Host "   $(if ($hasApp) { '✓' } else { '⚠' }) app.woosoo.local entry" -ForegroundColor $(if ($hasApp) { 'Green' } else { 'Yellow' })

if (-not $hasAdmin -or -not $hasApp) {
    Write-Host "   Add to hosts file: 127.0.0.1 admin.woosoo.local app.woosoo.local" -ForegroundColor Gray
}
Write-Host ""

# Summary
Write-Host "========================================" -ForegroundColor Cyan
if ($isAdmin -and $all_exist -and $certs_ok -and $bins_ok -and $configs_ok -and $builds_ok -and $scripts_ok -and $mysql_ok) {
    Write-Host "✓ ALL CHECKS PASSED - READY TO DEPLOY!" -ForegroundColor Green
    Write-Host "Next: cd scripts; .\install-services.ps1" -ForegroundColor Cyan
} else {
    Write-Host "⚠ SOME CHECKS FAILED - SEE ABOVE" -ForegroundColor Yellow
    if (-not $isAdmin) { Write-Host "• Run PowerShell as Administrator" -ForegroundColor Yellow }
    if (-not $all_exist) { Write-Host "• Some directories missing" -ForegroundColor Yellow }
    if (-not $certs_ok) { Write-Host "• Certificates missing" -ForegroundColor Yellow }
    if (-not $bins_ok) { Write-Host "• Some binaries missing" -ForegroundColor Yellow }
    if (-not $configs_ok) { Write-Host "• Configuration files missing" -ForegroundColor Yellow }
    if (-not $builds_ok) { Write-Host "• Build artifacts missing" -ForegroundColor Yellow }
    if (-not $scripts_ok) { Write-Host "• Deployment scripts missing" -ForegroundColor Yellow }
    if (-not $mysql_ok) { Write-Host "• MySQL not running" -ForegroundColor Yellow }
}
Write-Host "========================================" -ForegroundColor Cyan