# Woosoo Nexus - PHP Configuration Setup
# Dynamically configures php.ini extension_dir based on actual installation path

$ErrorActionPreference = "Stop"

$BASE_DIR = "C:\laragon\www\woosoo"
$PHP_DIR = "$BASE_DIR\bin\php"
$PHP_INI = "$PHP_DIR\php.ini"
$EXTENSION_DIR = "$PHP_DIR\ext"

Write-Host "Configuring PHP..." -ForegroundColor Cyan

# Validate paths exist
if (-not (Test-Path $PHP_INI)) {
    Write-Host "ERROR: php.ini not found at $PHP_INI" -ForegroundColor Red
    exit 1
}

if (-not (Test-Path $EXTENSION_DIR)) {
    Write-Host "ERROR: PHP ext directory not found at $EXTENSION_DIR" -ForegroundColor Red
    exit 1
}

Write-Host "  [OK] PHP paths validated" -ForegroundColor Green

# Read php.ini
$ini_content = Get-Content $PHP_INI -Raw

# Check current extension_dir
if ($ini_content -match 'extension_dir\s*=\s*"([^"]*)"') {
    $current = $matches[1]
    if ($current -eq $EXTENSION_DIR) {
        Write-Host "  [OK] extension_dir already correct: $current" -ForegroundColor Green
        exit 0
    }
}

# Update extension_dir safely with proper escaping
$escaped_dir = $EXTENSION_DIR -replace '\\', '\\'
$ini_content = $ini_content -replace 'extension_dir\s*=\s*"[^"]*"', "extension_dir = `"$escaped_dir`""

# Write back with UTF-8 encoding
[System.IO.File]::WriteAllText($PHP_INI, $ini_content, [System.Text.Encoding]::UTF8)

Write-Host "  [OK] PHP extension_dir updated: $EXTENSION_DIR" -ForegroundColor Green
Write-Host ""
