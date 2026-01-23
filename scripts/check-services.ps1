# Woosoo Nexus - Health Check

Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "Woosoo Services Health Check" -ForegroundColor Cyan
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host ""

# Service Status
Write-Host "1. Service Status:" -ForegroundColor Yellow
$services = @("woosoo-nginx", "woosoo-php-fpm", "woosoo-reverb")
foreach ($svc in $services) {
    $service = Get-Service -Name $svc -ErrorAction SilentlyContinue
    if ($service) {
        $icon = if ($service.Status -eq "Running") { "[OK]" } else { "[FAIL]" }
        $color = if ($service.Status -eq "Running") { "Green" } else { "Red" }
        Write-Host "   $icon $svc : $($service.Status)" -ForegroundColor $color
    } else {
        Write-Host "   [FAIL] $svc : NOT INSTALLED" -ForegroundColor Red
    }
}

Write-Host ""
Write-Host "2. Port Connectivity:" -ForegroundColor Yellow

$ports = @(
    @{ Name = "nginx (HTTPS)"; Host = "127.0.0.1"; Port = 443 },
    @{ Name = "PHP-FPM (FastCGI)"; Host = "127.0.0.1"; Port = 9000 },
    @{ Name = "Reverb (WebSocket)"; Host = "127.0.0.1"; Port = 6001 }
)

foreach ($port in $ports) {
    $tcp = Test-NetConnection -ComputerName $port.Host -Port $port.Port -WarningAction SilentlyContinue
    $icon = if ($tcp.TcpTestSucceeded) { "[OK]" } else { "[FAIL]" }
    $color = if ($tcp.TcpTestSucceeded) { "Green" } else { "Red" }
    Write-Host "   $icon $($port.Name) on $($port.Host):$($port.Port)" -ForegroundColor $color
}

Write-Host ""
Write-Host "3. HTTPS Endpoints:" -ForegroundColor Yellow

$endpoints = @(
    @{ Name = "Admin Panel"; URL = "https://admin.woosoo.local" },
    @{ Name = "Tablet App"; URL = "https://app.woosoo.local" }
)

foreach ($endpoint in $endpoints) {
    try {
        $response = Invoke-WebRequest -Uri $endpoint.URL -SkipCertificateCheck -TimeoutSec 3 -ErrorAction Stop
        Write-Host "   [OK] $($endpoint.Name) : $($response.StatusCode)" -ForegroundColor Green
    } catch {
        Write-Host "   [FAIL] $($endpoint.Name) : Cannot connect" -ForegroundColor Red
    }
}

Write-Host ""
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "Check complete" -ForegroundColor Gray
Write-Host "Logs: C:\laragon\www\woosoo\logs\" -ForegroundColor Gray
Write-Host "==========================================" -ForegroundColor Cyan
