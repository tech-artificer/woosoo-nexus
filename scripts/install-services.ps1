# Woosoo Nexus - Install Windows Services
# Run as Administrator

$ErrorActionPreference = "Stop"

Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "Woosoo Nexus Service Installation" -ForegroundColor Cyan
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host ""

# Check Administrator
$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]"Administrator")
if (-not $isAdmin) {
    Write-Host "ERROR: This script requires Administrator privileges!" -ForegroundColor Red
    Write-Host "Right-click PowerShell and select 'Run as Administrator'" -ForegroundColor Yellow
    exit 1
}

# Paths
$BASE_DIR = "C:\laragon\www\woosoo"
$NSSM = "$BASE_DIR\bin\nssm\win64\nssm.exe"
$NGINX_EXE = "$BASE_DIR\bin\nginx\nginx.exe"
$PHP_CGI = "$BASE_DIR\bin\php\php-cgi.exe"
$PHP_EXE = "$BASE_DIR\bin\php\php.exe"
$APP_DIR = "$BASE_DIR\apps\woosoo-nexus"
$LOGS_DIR = "$BASE_DIR\logs"

# Validate paths
Write-Host "Validating paths..." -ForegroundColor Yellow
$required = @(
    @{Path=$NSSM; Name="NSSM"},
    @{Path=$NGINX_EXE; Name="nginx"},
    @{Path=$PHP_CGI; Name="PHP-CGI"},
    @{Path=$PHP_EXE; Name="PHP"},
    @{Path=$APP_DIR; Name="App Directory"}
)

$allExist = $true
foreach ($item in $required) {
    if (Test-Path $item.Path) {
        Write-Host "  [OK] $($item.Name)" -ForegroundColor Green
    } else {
        Write-Host "  [FAIL] $($item.Name) not found at $($item.Path)" -ForegroundColor Red
        $allExist = $false
    }
}

if (-not $allExist) {
    Write-Host "`nERROR: Some required files are missing!" -ForegroundColor Red
    exit 1
}

Write-Host "`nAll required files found!" -ForegroundColor Green
Write-Host ""

# Setup prerequisites (mime.types, SSL certificates)
Write-Host "Setting up prerequisites..." -ForegroundColor Yellow
& "$PSScriptRoot\setup-prerequisites.ps1"
if ($LASTEXITCODE -ne 0) {
    Write-Host "ERROR: Prerequisites setup failed!" -ForegroundColor Red
    exit 1
}

# Configure PHP
Write-Host "Setting up PHP configuration..." -ForegroundColor Yellow
& "$PSScriptRoot\setup-php-config.ps1"

# Create log directories
Write-Host "Creating log directories..." -ForegroundColor Yellow
@("$LOGS_DIR\nginx", "$LOGS_DIR\php", "$LOGS_DIR\reverb") | ForEach-Object {
    if (-not (Test-Path $_)) {
        New-Item -ItemType Directory -Path $_ -Force | Out-Null
    }
}
Write-Host "  [OK] Log directories ready" -ForegroundColor Green
Write-Host ""

# Install Services
Write-Host "Installing Windows Services..." -ForegroundColor Yellow
Write-Host ""

# Function to install or update service
function Install-Service {
    param([string]$ServiceName, [string[]]$ServiceArgs)
    
    $existing = Get-Service -Name $ServiceName -ErrorAction SilentlyContinue
    if ($existing) {
        Write-Host "  [INFO] Service $ServiceName already exists, updating configuration..." -ForegroundColor Gray
        & $NSSM set $ServiceName Start SERVICE_AUTO_START 2>$null
    } else {
        Write-Host "  [INFO] Creating new service $ServiceName..." -ForegroundColor Gray
        & $NSSM install $ServiceName @ServiceArgs 2>$null
    }
}

# 1. nginx
Write-Host "[1/3] Installing woosoo-nginx..." -ForegroundColor Cyan
Install-Service "woosoo-nginx" @("$NGINX_EXE", "-c", "$BASE_DIR\configs\nginx.conf")
& $NSSM set woosoo-nginx AppDirectory "$BASE_DIR\bin\nginx"
& $NSSM set woosoo-nginx DisplayName "Woosoo Nexus - nginx"
& $NSSM set woosoo-nginx Description "nginx reverse proxy for Woosoo Nexus"
& $NSSM set woosoo-nginx Start SERVICE_AUTO_START
& $NSSM set woosoo-nginx AppStdout "$LOGS_DIR\nginx\stdout.log"
& $NSSM set woosoo-nginx AppStderr "$LOGS_DIR\nginx\stderr.log"
& $NSSM set woosoo-nginx AppRotateFiles 1
& $NSSM set woosoo-nginx AppRotateBytes 10485760
Write-Host "  [OK] woosoo-nginx configured" -ForegroundColor Green
Write-Host ""

# 2. PHP-FPM
Write-Host "[2/3] Installing woosoo-php-fpm..." -ForegroundColor Cyan
Install-Service "woosoo-php-fpm" @("$PHP_CGI", "-b", "127.0.0.1:9000")
& $NSSM set woosoo-php-fpm AppDirectory "$APP_DIR"
& $NSSM set woosoo-php-fpm DisplayName "Woosoo Nexus - PHP-FPM"
& $NSSM set woosoo-php-fpm Description "PHP FastCGI Process Manager"
& $NSSM set woosoo-php-fpm Start SERVICE_AUTO_START
& $NSSM set woosoo-php-fpm AppStdout "$LOGS_DIR\php\stdout.log"
& $NSSM set woosoo-php-fpm AppStderr "$LOGS_DIR\php\stderr.log"
& $NSSM set woosoo-php-fpm AppRotateFiles 1
& $NSSM set woosoo-php-fpm AppRotateBytes 10485760
Write-Host "  [OK] woosoo-php-fpm configured" -ForegroundColor Green
Write-Host ""

# 3. Reverb
Write-Host "[3/3] Installing woosoo-reverb..." -ForegroundColor Cyan
Install-Service "woosoo-reverb" @("$PHP_EXE", "artisan", "reverb:start", "--port=6001")
& $NSSM set woosoo-reverb AppDirectory "$APP_DIR"
& $NSSM set woosoo-reverb DisplayName "Woosoo Nexus - Laravel Reverb"
& $NSSM set woosoo-reverb Description "Laravel Reverb WebSocket Server"
& $NSSM set woosoo-reverb Start SERVICE_AUTO_START
& $NSSM set woosoo-reverb AppStdout "$LOGS_DIR\reverb\stdout.log"
& $NSSM set woosoo-reverb AppStderr "$LOGS_DIR\reverb\stderr.log"
& $NSSM set woosoo-reverb AppRotateFiles 1
& $NSSM set woosoo-reverb AppRotateBytes 10485760
Write-Host "  [OK] woosoo-reverb configured" -ForegroundColor Green
Write-Host ""

Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "[OK] Installation Complete!" -ForegroundColor Green
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Services installed:" -ForegroundColor Yellow
Write-Host "  • woosoo-nginx (nginx reverse proxy)" -ForegroundColor Gray
Write-Host "  • woosoo-php-fpm (PHP FastCGI)" -ForegroundColor Gray
Write-Host "  • woosoo-reverb (WebSocket server)" -ForegroundColor Gray
Write-Host ""
Write-Host "Next steps:" -ForegroundColor Yellow
Write-Host "  1. .\start-production.ps1  (start all services)" -ForegroundColor Gray
Write-Host "  2. .\check-services.ps1    (verify health)" -ForegroundColor Gray
Write-Host ""
