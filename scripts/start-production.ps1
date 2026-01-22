# Woosoo Nexus - Start Services

Write-Host "Starting Woosoo services..." -ForegroundColor Cyan
Write-Host ""

# Ensure PHP config is correct
Write-Host "Verifying PHP configuration..." -ForegroundColor Gray
& "$PSScriptRoot\setup-php-config.ps1"

Write-Host "[1/3] Starting woosoo-nginx..." -ForegroundColor Yellow
Start-Service -Name woosoo-nginx
Start-Sleep -Seconds 2

Write-Host "[2/3] Starting woosoo-php-fpm..." -ForegroundColor Yellow
Start-Service -Name woosoo-php-fpm
Start-Sleep -Seconds 2

Write-Host "[3/3] Starting woosoo-reverb..." -ForegroundColor Yellow
Start-Service -Name woosoo-reverb
Start-Sleep -Seconds 3

Write-Host ""
Write-Host "Waiting for services to stabilize..." -ForegroundColor Gray
Start-Sleep -Seconds 10

Write-Host ""
Write-Host "Testing endpoint..." -ForegroundColor Yellow
try {
    $response = Invoke-WebRequest -Uri "https://admin.woosoo.local" -SkipCertificateCheck -TimeoutSec 5 -ErrorAction Stop
    Write-Host "[OK] https://admin.woosoo.local responding ($($response.StatusCode))" -ForegroundColor Green
} catch {
    Write-Host "[WARN] Endpoint test failed (services may still be starting)" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "[OK] Services started successfully!" -ForegroundColor Green
Write-Host ""
Write-Host "Run .\check-services.ps1 to verify full health" -ForegroundColor Gray
