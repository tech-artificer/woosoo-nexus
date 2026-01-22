# Woosoo Nexus Production Deployment Guide

Complete step-by-step guide to deploy Woosoo Nexus as Windows services.

## Prerequisites

- Windows Server or Windows 10/11
- Administrator privileges
- Git (for cloning repository)
- Completed builds: `npm run build` in both woosoo-nexus and tablet-ordering-pwa
- SSL certificates generated with mkcert

## Quick Start

```powershell
# Navigate to scripts directory
cd C:\laragon\www\woosoo\apps\woosoo-nexus\scripts

# Set execution policy for current session
Set-ExecutionPolicy -ExecutionPolicy Bypass -Scope Process -Force

# Install services (requires Administrator)
.\install-services.ps1

# Start all services
.\start-production.ps1

# Verify health
.\check-services.ps1
```

## Detailed Setup

### 1. Environment Configuration

Copy and configure environment file:

```powershell
cd C:\laragon\www\woosoo\apps\woosoo-nexus
cp .env.example .env
php artisan key:generate
```

**Required `.env` settings:**

```env
APP_NAME=Woosoo
APP_ENV=production
APP_URL=https://admin.woosoo.local
APP_DEBUG=false

BROADCAST_CONNECTION=reverb
QUEUE_CONNECTION=sync

REVERB_HOST=admin.woosoo.local
REVERB_PORT=6001
REVERB_SCHEME=https

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=woosoo_api
DB_USERNAME=root
DB_PASSWORD=your_password
```

### 2. Database Setup

```powershell
php artisan migrate --force
php artisan db:seed --force
```

### 3. Build Assets

```powershell
# Main app
cd C:\laragon\www\woosoo\apps\woosoo-nexus
npm ci
npm run build

# Tablet PWA
cd C:\laragon\www\woosoo\apps\tablet-ordering-pwa
npm ci
npm run build
```

### 4. SSL Certificates

Generate SSL certificates for local development:

```powershell
cd C:\laragon\www\woosoo\bin\mkcert
mkcert -install
mkcert admin.woosoo.local app.woosoo.local

# Move certificates to certs folder
move admin.woosoo.local*.pem ..\..\certs\
move app.woosoo.local*.pem ..\..\certs\
```

### 5. Configure Hosts File

Add to `C:\Windows\System32\drivers\etc\hosts`:

```
127.0.0.1 admin.woosoo.local
127.0.0.1 app.woosoo.local
```

### 6. Install Services

Open PowerShell as Administrator:

```powershell
cd C:\laragon\www\woosoo\apps\woosoo-nexus\scripts
Set-ExecutionPolicy -ExecutionPolicy Bypass -Scope Process -Force
.\install-services.ps1
```

This installs three Windows services:
- `woosoo-nginx` — nginx reverse proxy (port 443)
- `woosoo-php-fpm` — PHP FastCGI (port 9000)
- `woosoo-reverb` — WebSocket server (port 6001)

### 7. Start Services

```powershell
.\start-production.ps1
```

Wait 15-20 seconds for services to stabilize.

### 8. Verify Deployment

```powershell
.\check-services.ps1
```

Expected output:
```
[OK] woosoo-nginx : Running
[OK] woosoo-php-fpm : Running
[OK] woosoo-reverb : Running
[OK] nginx (HTTPS) on 127.0.0.1:443
[OK] PHP-FPM (FastCGI) on 127.0.0.1:9000
[OK] Reverb (WebSocket) on 127.0.0.1:6001
[OK] Admin Panel : 200
[OK] Tablet App : 200
```

### 9. Access Application

- **Admin Panel:** https://admin.woosoo.local
- **Tablet PWA:** https://app.woosoo.local

## Service Management

### Start Services

```powershell
cd C:\laragon\www\woosoo\apps\woosoo-nexus\scripts
.\start-production.ps1
```

### Stop Services

```powershell
.\stop-production.ps1
```

### Check Health

```powershell
.\check-services.ps1
```

### View Logs

```powershell
# nginx logs
Get-Content C:\laragon\www\woosoo\logs\nginx\error.log -Tail 50
Get-Content C:\laragon\www\woosoo\logs\nginx\access.log -Tail 50

# PHP logs
Get-Content C:\laragon\www\woosoo\logs\php\stderr.log -Tail 50

# Reverb logs
Get-Content C:\laragon\www\woosoo\logs\reverb\stdout.log -Tail 50
```

### Manual Service Control

```powershell
# Windows Services Manager
services.msc

# PowerShell commands
Get-Service woosoo-*
Start-Service woosoo-nginx
Stop-Service woosoo-nginx
Restart-Service woosoo-nginx
```

## Troubleshooting

### Service Won't Start

1. Check logs in `C:\laragon\www\woosoo\logs\`
2. Verify ports aren't in use:
   ```powershell
   netstat -ano | findstr ":443"
   netstat -ano | findstr ":9000"
   netstat -ano | findstr ":6001"
   ```
3. Check binary paths exist:
   ```powershell
   Test-Path C:\laragon\www\woosoo\bin\nginx\nginx.exe
   Test-Path C:\laragon\www\woosoo\bin\php\php-cgi.exe
   Test-Path C:\laragon\www\woosoo\bin\php\php.exe
   ```

### HTTPS Certificate Errors

Reinstall mkcert root CA:

```powershell
cd C:\laragon\www\woosoo\bin\mkcert
mkcert -install
```

### Can't Access Admin Panel

1. Verify hosts file has `admin.woosoo.local` entry
2. Check nginx is running: `Get-Service woosoo-nginx`
3. Test HTTPS endpoint: `Invoke-WebRequest https://admin.woosoo.local -SkipCertificateCheck`
4. Check nginx error log

### WebSocket Connection Fails

1. Verify Reverb is running: `Get-Service woosoo-reverb`
2. Check Reverb logs: `C:\laragon\www\woosoo\logs\reverb\stdout.log`
3. Verify `.env` has correct Reverb settings
4. Test WebSocket endpoint: `Test-NetConnection 127.0.0.1 -Port 6001`

## Uninstalling Services

```powershell
# Stop all services
.\stop-production.ps1

# Remove services
$NSSM = "C:\laragon\www\woosoo\bin\nssm\win64\nssm.exe"
& $NSSM remove woosoo-nginx confirm
& $NSSM remove woosoo-php-fpm confirm
& $NSSM remove woosoo-reverb confirm
```

## Production Checklist

- [ ] `.env` configured with production values
- [ ] `APP_DEBUG=false`
- [ ] Database migrated and seeded
- [ ] Assets built (`npm run build`)
- [ ] SSL certificates generated
- [ ] Hosts file configured
- [ ] Services installed and running
- [ ] Health check passes
- [ ] Logs directory has correct permissions
- [ ] Firewall rules configured (if needed)

## Directory Structure

```
C:\laragon\www\woosoo\
├── apps\
│   ├── woosoo-nexus\          # Laravel admin panel
│   └── tablet-ordering-pwa\   # Nuxt 3 PWA
├── bin\
│   ├── nginx\                 # nginx 1.24
│   ├── php\                   # PHP 8.3 Thread-Safe
│   ├── nssm\                  # Windows service manager
│   └── mkcert\                # SSL certificate generator
├── certs\                     # SSL certificates
├── configs\
│   └── nginx.conf             # nginx configuration
└── logs\
    ├── nginx\                 # nginx logs
    ├── php\                   # PHP-FPM logs
    └── reverb\                # Reverb logs
```

## Support

For issues or questions:
- Check logs in `C:\laragon\www\woosoo\logs\`
- Review `.env` configuration
- Verify all prerequisites are met
- Consult `README.md` in repository root
