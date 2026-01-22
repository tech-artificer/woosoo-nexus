# Woosoo Nexus - Setup Prerequisites
# Ensures all required files exist before service installation

$ErrorActionPreference = "Stop"

$BASE_DIR = "C:\laragon\www\woosoo"
$APPS_DIR = "$BASE_DIR\apps"
$CONFIGS_DIR = "$BASE_DIR\configs"
$CERTS_DIR = "$BASE_DIR\certs"
$NGINX_DIR = "$BASE_DIR\bin\nginx"
$MKCERT_DIR = "$BASE_DIR\bin\mkcert"
$PWA_DIR = "$APPS_DIR\tablet-ordering-pwa"

Write-Host "Setting up prerequisites..." -ForegroundColor Cyan
Write-Host ""

# 1. Build tablet-ordering-pwa
Write-Host "[1/3] Building tablet-ordering-pwa..." -ForegroundColor Yellow

if (-not (Test-Path "$PWA_DIR\package.json")) {
    Write-Host "  ERROR: tablet-ordering-pwa package.json not found at $PWA_DIR" -ForegroundColor Red
    exit 1
}

Write-Host "  Installing dependencies..." -ForegroundColor Gray
Push-Location $PWA_DIR
npm ci --silent --progress=false 2>&1 | Out-Null
if ($LASTEXITCODE -ne 0) {
    Write-Host "  ERROR: npm ci failed" -ForegroundColor Red
    Pop-Location
    exit 1
}

Write-Host "  Building production bundle..." -ForegroundColor Gray
npm run generate 2>&1 | Out-Null
if ($LASTEXITCODE -ne 0) {
    Write-Host "  ERROR: npm run generate failed" -ForegroundColor Red
    Pop-Location
    exit 1
}
Pop-Location

# Validate static build artifacts (generated via npm run generate)
if ((Test-Path "$PWA_DIR\.output\public\index.html") -and (Test-Path "$PWA_DIR\.output\public\_nuxt")) {
    Write-Host "  [OK] tablet-ordering-pwa built successfully" -ForegroundColor Green
} else {
    Write-Host "  ERROR: Static files not generated in .output/public" -ForegroundColor Red
    exit 1
}

Write-Host "[2/3] Copying Nginx configuration files..." -ForegroundColor Yellow

# Ensure configs directory exists
New-Item -ItemType Directory -Path $CONFIGS_DIR -Force | Out-Null

# Copy mime.types
$mime_source = "$NGINX_DIR\conf\mime.types"
$mime_dest = "$CONFIGS_DIR\mime.types"

if (-not (Test-Path $mime_source)) {
    Write-Host "  ERROR: Source mime.types not found at $mime_source" -ForegroundColor Red
    exit 1
}

Copy-Item $mime_source $mime_dest -Force
Write-Host "  [OK] mime.types copied" -ForegroundColor Green

# Copy fastcgi_params
$fastcgi_source = "$NGINX_DIR\conf\fastcgi_params"
$fastcgi_dest = "$CONFIGS_DIR\fastcgi_params"

if (-not (Test-Path $fastcgi_source)) {
    Write-Host "  ERROR: Source fastcgi_params not found at $fastcgi_source" -ForegroundColor Red
    exit 1
}

Copy-Item $fastcgi_source $fastcgi_dest -Force
Write-Host "  [OK] fastcgi_params copied" -ForegroundColor Green

# 3. Generate SSL certificates
Write-Host ""
Write-Host "[3/3] Generating SSL certificates..." -ForegroundColor Yellow

# Find mkcert executable (could be versioned: mkcert-v*.exe or mkcert.exe)
$mkcert_exe = Get-ChildItem "$MKCERT_DIR\mkcert*.exe" -ErrorAction SilentlyContinue | Select-Object -First 1 -ExpandProperty FullName
if (-not $mkcert_exe) {
    Write-Host "  ERROR: mkcert executable not found in $MKCERT_DIR" -ForegroundColor Red
    exit 1
}
Write-Host "  Using: $(Split-Path $mkcert_exe -Leaf)" -ForegroundColor Gray

# Create certs directory
if (-not (Test-Path $CERTS_DIR)) {
    New-Item -ItemType Directory -Path $CERTS_DIR -Force | Out-Null
}

# Check if certificates already exist
$admin_cert = "$CERTS_DIR\admin.woosoo.local.pem"
$admin_key = "$CERTS_DIR\admin.woosoo.local-key.pem"
$app_cert = "$CERTS_DIR\app.woosoo.local.pem"
$app_key = "$CERTS_DIR\app.woosoo.local-key.pem"

if ((Test-Path $admin_cert) -and (Test-Path $admin_key) -and (Test-Path $app_cert) -and (Test-Path $app_key)) {
    Write-Host "  [OK] SSL certificates already exist" -ForegroundColor Green
} else {
    Write-Host "  Generating certificates with mkcert..." -ForegroundColor Gray
    
    Push-Location $MKCERT_DIR
    
    # Run mkcert with proper argument handling
    $output = & cmd /c "$mkcert_exe admin.woosoo.local app.woosoo.local"
    
    # Move and rename generated certificates (mkcert uses +1 for multiple domains)
    Get-ChildItem -Path . -Filter "*woosoo.local*.pem" -ErrorAction SilentlyContinue | ForEach-Object {
        $newName = $_.Name -replace "\+1", ""
        Move-Item $_.FullName -Destination "$CERTS_DIR\$newName" -Force
    }
    
    Pop-Location
    
    if ((Test-Path $admin_cert) -and (Test-Path $admin_key) -and (Test-Path $app_cert) -and (Test-Path $app_key)) {
        Write-Host "  [OK] SSL certificates generated and moved to certs directory" -ForegroundColor Green
    } else {
        Write-Host "  WARNING: Certificates were generated but not all files found in expected locations" -ForegroundColor Yellow
        Write-Host "  Checking what was created..." -ForegroundColor Gray
        Get-ChildItem "$CERTS_DIR\*.pem" | ForEach-Object { Write-Host "    Found: $($_.Name)" -ForegroundColor Gray }
    }
}

Write-Host ""
Write-Host "[OK] All prerequisites ready!" -ForegroundColor Green
Write-Host ""
