# Woosoo Nexus Service Management Scripts

All scripts must be run as **Administrator** (right-click PowerShell → "Run as Administrator").

---

## Quick Start (9 Operational Scripts - Consolidated)

**First-time setup (6 steps):**
```powershell
cd scripts/
.\setup-prerequisites.ps1                     # Install OpenSSL, mkcert
.\setup-firewall.ps1                          # Open Windows Firewall ports
.\setup-php-config.ps1                        # Configure PHP settings  
.\setup-local-domains.ps1 -ServerIP 127.0.0.1  # Add hosts file entries
.\services-setup.ps1 -Mode install            # Install Windows services
.\services-manager.ps1 -Action start          # Start all services
```

**Daily operations (3 main tasks):**
```powershell
.\check-services.ps1                          # Health check (all services)
.\services-manager.ps1 -Action restart        # Restart all (nginx → PHP-FPM → Reverb)
.\restart-nginx.ps1                           # Restart nginx only (after config changes)
```

---

## Network Configuration

**Current setup (port-based routing):**
- **Tablet PWA:** `http://192.168.100.85:3000/` (static files via nginx)
- **Admin Panel:** `http://192.168.100.85:8000/` (Laravel via nginx → PHP-FPM)
- **WebSocket:** Port 6001 (Reverb)

**Access from:**
- Server: `http://localhost:3000/` or `http://192.168.100.85:3000/`
- Tablets (same WiFi): `http://192.168.100.85:3000/`

---

## Script Reference

### restart-nginx.ps1
**Restart nginx web server only** (quick restart for nginx.conf changes).

**When to use:**
- After editing `configs/nginx.conf`
- After regenerating SSL certificates
- When nginx stops responding

**Ports affected:** 3000 (PWA), 8000 (Admin)

**Does NOT restart:** PHP-FPM, Reverb, Queue workers

---

### restart-for-lan.ps1
**Restart ALL Woosoo services** (nginx, PHP-FPM, Reverb).

**When to use:**
- After major configuration changes (`.env`, PHP settings)
- When admin panel returns 502 errors (PHP-FPM issue)
- When WebSocket connections fail (Reverb issue)
- First-time LAN deployment

**Restarts:**
- nginx (web server)
- PHP-FPM (PHP processor)
- Reverb (WebSocket server)

**Tests:** Verifies all services are running and accessible

---

### install-services.ps1
Registers three Windows services using NSSM (Non-Sucking Service Manager):
- **woosoo-nginx** - Reverse proxy on HTTPS (port 443)
- **woosoo-php-fpm** - FastCGI handler (port 9000)
- **woosoo-reverb** - WebSocket server (port 6001)

**Usage:**
```powershell
# Run as Administrator
cd C:\laragon\www\woosoo\scripts
.\install-services.ps1
```

**Requirements:**
- Administrator access
- All binaries present in bin/ folder
- Certificates in certs/ folder
- nginx.conf in configs/ folder

**What it does:**
1. Validates all required files exist
2. Registers each service with NSSM
3. Configures auto-start and auto-restart
4. Sets correct working directories
5. Configures logging to logs/ folder

**Expected output:**
```
Registering NSSM services for Woosoo Nexus...
✓ woosoo-nginx registered successfully
✓ woosoo-php-fpm registered successfully
✓ woosoo-reverb registered successfully
========================================
Services installed successfully!
```

### start-production.ps1
Starts all three services in correct order with health checks.

**Usage:**
```powershell
cd C:\laragon\www\woosoo\scripts
.\start-production.ps1
```

**What it does:**
1. Starts nginx (must be first - listens on 443)
2. Starts PHP-FPM (must be second - needs nginx to proxy to it)
3. Starts Reverb (can start last - independent)
4. Waits 10 seconds for services to stabilize
5. Tests https://admin.woosoo.local endpoint
6. Reports success or failure

**Expected output:**
```
[1/3] Starting woosoo-nginx...
[2/3] Starting woosoo-php-fpm...
[3/3] Starting woosoo-reverb...
Waiting for services to stabilize (10 seconds)...
Testing https://admin.woosoo.local...
✓ Endpoint responding!
✓ Services started successfully!
```

### stop-production.ps1
Stops all three services in reverse order (graceful shutdown).

**Usage:**
```powershell
cd C:\laragon\www\woosoo\scripts
.\stop-production.ps1
```

**What it does:**
1. Stops Reverb (doesn't depend on others)
2. Stops PHP-FPM (must stop before nginx)
3. Stops nginx (last to stop listening)
4. Confirms all stopped

**Expected output:**
```
[1/3] Stopping woosoo-reverb...
[2/3] Stopping woosoo-php-fpm...
[3/3] Stopping woosoo-nginx...
✓ Services stopped successfully!
```

### check-services.ps1
Performs comprehensive health check of all three services.

**Usage:**
```powershell
cd C:\laragon\www\woosoo\scripts
.\check-services.ps1
```

**What it checks:**
1. Service status (Running/Stopped)
2. Port availability (listening on 443, 9000, 6001)
3. HTTPS endpoints responding
4. API health status

**Expected output:**
```
1. Service Status:
   ✓ woosoo-nginx : Running
   ✓ woosoo-php-fpm : Running
   ✓ woosoo-reverb : Running

2. Port Connectivity:
   ✓ Open : nginx (HTTPS) on 127.0.0.1:443
   ✓ Open : PHP-FPM (FastCGI) on 127.0.0.1:9000
   ✓ Open : Reverb (WebSocket) on 127.0.0.1:6001

3. HTTPS Endpoints:
   ✓ Admin Panel : 200
   ✓ Tablet App : 200
   ✓ API Health : 200
```

## Typical Workflow

### First-Time Installation
```powershell
# 1. Verify prerequisites (MySQL running, certs present)
Test-NetConnection -ComputerName 127.0.0.1 -Port 3306

# 2. Install services
.\install-services.ps1

# 3. Start services
.\start-production.ps1

# 4. Verify everything works
.\check-services.ps1

# 5. Open browser and test
# https://admin.woosoo.local
```

### Daily Operations
```powershell
# Morning: start
.\start-production.ps1

# During day: check health
.\check-services.ps1

# Evening: stop
.\stop-production.ps1
```

### Emergency Restart
```powershell
# Quick restart all services
.\stop-production.ps1
Start-Sleep -Seconds 2
.\start-production.ps1
.\check-services.ps1
```

## Manual Service Management

If scripts don't work, you can manage services manually:

```powershell
# Check status
Get-Service woosoo-*

# Start individual service
Start-Service -Name woosoo-nginx

# Stop individual service
Stop-Service -Name woosoo-nginx

# Restart individual service
Restart-Service -Name woosoo-nginx

# View service details
Get-Service woosoo-nginx | Select-Object *

# View NSSM log for service
Get-Content "C:\laragon\www\woosoo\logs\nginx\stderr.txt" -Tail 20
```

## Troubleshooting

### Services won't start
1. Check Administrator access: `([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole] "Administrator")`
2. Check ports are free: `netstat -ano | Select-String ":443|:9000|:6001"`
3. Check logs: `Get-Content "C:\laragon\www\project-woosoo\logs\nginx\error.log" -Tail 30`

### HTTPS not working
1. Check certificates exist: `ls C:\laragon\www\project-woosoo\certs\`
2. Check nginx config: `"C:\laragon\www\project-woosoo\bin\nginx\nginx.exe" -t`
3. Check hosts file: Add `127.0.0.1 admin.woosoo.local` to `C:\Windows\System32\drivers\etc\hosts`

### Services keep crashing
1. Check error logs: `Get-Content "C:\laragon\www\project-woosoo\logs\php\error.log" -Tail 30`
2. Check MySQL connection: `Test-NetConnection -ComputerName 127.0.0.1 -Port 3306`
3. Check disk space: `Get-Volume C`

## Security Notes

These scripts are designed for **local network deployment only**. For internet-facing deployments:

1. **Use proper certificates** - Replace mkcert certificates with valid SSL certificates from Let's Encrypt or commercial CA
2. **Configure firewall** - Only allow necessary inbound connections
3. **Set strong passwords** - Change default admin credentials immediately
4. **Enable logging** - Configure log aggregation and monitoring
5. **Update regularly** - Keep Laravel, PHP, and dependencies current

See main [PRODUCTION_DEPLOYMENT_GUIDE.md](../docs/PRODUCTION_DEPLOYMENT_GUIDE.md) for complete deployment instructions.

## Dependencies

- **NSSM 2.24+** - Service manager (included in bin/nssm/)
- **PHP 8.3+** - CLI executable (included in bin/php/)
- **nginx 1.24+** - Web server (included in bin/nginx/)
- **Laravel 12** - Application framework
- **MySQL 8.0+** - Database
- **Windows Server 2019+** - Operating system

## Logs Location

All service logs are written to:
```
C:\laragon\www\woosoo\logs\
├── nginx/
│   ├── access.log
│   └── error.log
├── php/
│   ├── stdout.log
│   └── stderr.log
└── reverb/
    ├── stdout.log
    └── stderr.log
```

Application logs:
```
C:\laragon\www\woosoo\apps\woosoo-nexus\storage\logs\laravel.log
```

## Getting Help

1. **Check the guide:** [PRODUCTION_DEPLOYMENT_GUIDE.md](../docs/PRODUCTION_DEPLOYMENT_GUIDE.md)
2. **View logs:** `Get-Content "C:\laragon\www\project-woosoo\logs\*\*.log" -Tail 50`
3. **Manual check:** `.\check-services.ps1`
4. **Reinstall:** Remove services with `nssm remove` and run `.\install-services.ps1` again

---

**Last Updated:** January 10, 2025
**Version:** 1.0
