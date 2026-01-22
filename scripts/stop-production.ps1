# Woosoo Nexus - Stop Services

Write-Host "Stopping Woosoo services..." -ForegroundColor Cyan
Write-Host ""

Write-Host "[1/3] Stopping woosoo-reverb..." -ForegroundColor Yellow
Stop-Service -Name woosoo-reverb -Force
Start-Sleep -Seconds 1

Write-Host "[2/3] Stopping woosoo-php-fpm..." -ForegroundColor Yellow
Stop-Service -Name woosoo-php-fpm -Force
Start-Sleep -Seconds 1

Write-Host "[3/3] Stopping woosoo-nginx..." -ForegroundColor Yellow
Stop-Service -Name woosoo-nginx -Force

Write-Host ""
Write-Host "✓ Services stopped successfully!" -ForegroundColor Green
