# Woosoo Nexus Production Architecture

**Purpose:** Explain the complete deployment architecture for Windows production environments  
**Audience:** Sysadmins, DevOps engineers, IT staff  
**Date:** January 10, 2025

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│                     INTERNET / LOCAL NETWORK                  │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
                    ┌──────────────────┐
                    │  nginx Reverse   │
                    │     Proxy        │
                    │  (Port 443 HTTPS)│
                    └────────┬─────────┘
                             │
                ┌────────────┼────────────┐
                │            │            │
                ▼            ▼            ▼
        ┌────────────┐  ┌──────────┐  ┌──────────┐
        │ Admin App  │  │ Tablet   │  │ WebSocket│
        │  (Laravel) │  │   App    │  │ (Reverb) │
        │PHP-FPM 9000│  │(Nuxt)    │  │ 6001→443 │
        └────────────┘  └──────────┘  └──────────┘
                │            │            │
                └────────────┼────────────┘
                             │
                    ┌────────┴───────┐
                    │                │
                    ▼                ▼
            ┌──────────────┐  ┌──────────────┐
            │  MySQL       │  │  Krypton POS │
            │  App DB      │  │  (Read-Only) │
            └──────────────┘  └──────────────┘
```

---

## Service Stack

### 1. nginx Reverse Proxy (Port 443)

**Role:** Public-facing HTTPS endpoint, routes requests to backend services  
**Service Name:** woosoo-nginx  
**Binary:** C:\laragon\www\woosoo\bin\nginx\nginx.exe  
**Config:** C:\laragon\www\woosoo\configs\nginx.conf  
**Logs:** C:\laragon\www\woosoo\logs\nginx\  
**Startup:** Runs as Windows service via NSSM  
**Auto-restart:** Yes (every 1 second if crashes)

**Responsibilities:**
- Terminate HTTPS (SSL/TLS) with mkcert certificates
- Route /api/* → PHP-FPM (port 9000)
- Route / → Tablet PWA (Nuxt .output/public/)
- Proxy WebSocket upgrade to Reverb (port 6001)
- Serve static assets (CSS, JS, images)
- Handle client timeouts and connection limits

**Key Configuration:**
```
Server 1: admin.woosoo.local
- Root: /apps/woosoo-nexus/public/
- FastCGI upstream: 127.0.0.1:9000
- SSL cert: certs/admin.woosoo.local.pem

Server 2: app.woosoo.local
- Root: /apps/tablet-ordering-pwa/.output/public/
- Fallback: /index.html (SPA routing)
- SSL cert: certs/app.woosoo.local.pem

WebSocket Upgrade:
- Path: /laravel-echo-server/*
- Proxy to: 127.0.0.1:6001 (Reverb)
- Timeout: 90s (persistent connections)
```

---

### 2. PHP-FPM FastCGI Handler (Port 9000)

**Role:** Execute PHP code, handle HTTP requests from nginx  
**Service Name:** woosoo-php-fpm  
**Binary:** C:\laragon\www\woosoo\bin\php\php-cgi.exe  
**Binding:** 127.0.0.1:9000 (FastCGI protocol)  
**Working Dir:** C:\laragon\www\woosoo\apps\woosoo-nexus\  
**Logs:** C:\laragon\www\woosoo\logs\php\  
**Startup:** Runs as Windows service via NSSM with 2 processes  
**Auto-restart:** Yes (every 1 second if crashes)

**Responsibilities:**
- Execute Laravel application code
- Handle web requests via FastCGI
- Connect to MySQL (default connection)
- Connect to Krypton POS database (pos connection, read-only)
- Queue jobs (QUEUE_CONNECTION=sync, execute inline)
- Run scheduled tasks (scheduler still active)
- Broadcast events to WebSocket (Reverb)

**Key Environment Variables:**
```env
APP_URL=https://admin.woosoo.local
APP_ENV=production
APP_DEBUG=false

# Database
DB_HOST=127.0.0.1
DB_DATABASE=woosoo_api
DB_USERNAME=root
DB_PASSWORD=*****

# POS Integration (read-only)
DB_POS_HOST=127.0.0.1
DB_POS_DATABASE=krypton_woosoo
DB_POS_USERNAME=pos_user
DB_POS_PASSWORD=*****

# Queue (no worker needed)
QUEUE_CONNECTION=sync

# Reverb WebSocket
REVERB_HOST=admin.woosoo.local
REVERB_PORT=443
REVERB_SCHEME=https
```

**Request Lifecycle:**
1. nginx receives HTTPS request (port 443)
2. nginx converts to FastCGI protocol
3. Sends to php-cgi.exe on port 9000
4. PHP executes Laravel request
5. Laravel fetches data from MySQL
6. May call POS stored procedures (read-only)
7. May broadcast events to Reverb
8. Returns JSON/HTML response
9. nginx sends response to client

---

### 3. Laravel Reverb WebSocket Server (Port 6001)

**Role:** Real-time WebSocket server for admin panel and tablet app  
**Service Name:** woosoo-reverb  
**Binary:** C:\laragon\www\woosoo\bin\php\php.exe  
**Command:** php artisan reverb:start --port=6001  
**Working Dir:** C:\laragon\www\woosoo\apps\woosoo-nexus\  
**Logs:** C:\laragon\www\woosoo\logs\reverb\  
**Startup:** Runs as Windows service via NSSM  
**Auto-restart:** Yes (every 1 second if crashes)

**Responsibilities:**
- Accept WebSocket connections from browsers and apps
- Broadcast events (PrintOrder, OrderUpdate, ServiceRequest, etc.)
- Maintain persistent connections to multiple clients
- Route messages to subscribed channels
- Handle connection authentication (token validation)

**Channels:**
```
device.{deviceId}     - Tablet-specific notifications
orders.{orderId}      - Order status updates
admin.orders          - Admin order notifications
admin.print           - Print job events
admin.service-requests - Service request alerts
```

**Client Connections:**
- Admin Panel: Via Laravel Echo (JavaScript, resources/js/app.ts)
- Tablet App: Via Laravel Echo (Vue/Nuxt, tablet-ordering-pwa/plugins/echo.client.ts)
- Relay Device: Via Dart WebSocket library (relay-device/lib/services/websocket_service.dart)

**Proxy Configuration (nginx):**
```nginx
location /laravel-echo-server {
    proxy_pass http://127.0.0.1:6001;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_read_timeout 90;
}
```

---

## Data Flow: Order to Print

### 1. Tablet Creates Order

```
Tablet App (HTTPS)
  → POST /api/v1/device-orders
  → nginx (port 443)
  → PHP-FPM (port 9000)
  → Laravel OrderController::store()
    - Calls POS create_order() stored proc
    - Creates device_orders local record
    - Commits transaction
    - Fires PrintOrder event
  → BroadcastEventJob queued (sync = executes immediately)
  → Event broadcast to WebSocket
  → Reverb broadcasts to admin.print channel
  ↓
Admin Panel (listening on WebSocket)
  → Receives PrintOrder event
  → Displays "New Order" notification
  → Shows order details
  ↓
Relay Device (listening on WebSocket)
  → Receives PrintOrder event
  → Fetches order details via API
  → Prints to thermal printer
```

### 2. POS Updates Order Status

```
Krypton POS (external system)
  → Payment processed
  → Triggers after_payment_update (database trigger)
  → Updates device_orders.status = 'completed'
  ↓
Tablet App (polling every 5 seconds)
  → GET /api/v1/device/orders/{id}
  → nginx → PHP-FPM
  → Returns latest status
  → Displays "Order Complete" to customer
  ↓
Admin Panel
  → Polls for order updates via API
  → Shows order as completed
  → Updates real-time via WebSocket (if subscribed)
```

---

## Deployment Model

### On-Premise, Local Network Only

This deployment is designed for:
- **Single location:** One physical server
- **Internal use:** Local network (intranet)
- **No internet:** Can be air-gapped or firewalled
- **Self-signed HTTPS:** Certificates valid only for local domains
- **24/7 operation:** Auto-restart services, designed for stability

**Not designed for:**
- Multi-location deployments (would need VPN + load balancing)
- Public internet (self-signed certs not trusted by browsers)
- Horizontal scaling (single server, no clustering)
- High-volume ordering (no queued jobs, sync execution only)

### Service Dependencies

```
Startup Order:
1. nginx (must start first - listens on 443)
2. PHP-FPM (must start second - serves content)
3. Reverb (can start last - independent process)

Shutdown Order (reverse):
1. Reverb (stop first - no new connections)
2. PHP-FPM (stop second - allow active requests to finish)
3. nginx (stop last - close client connections)
```

---

## Critical Architectural Decisions

### 1. QUEUE_CONNECTION=sync

**Decision:** Jobs execute inline instead of queuing  
**Reason:** Single server, no background worker needed  
**Impact:** 
- Faster (no queue latency)
- Simpler (no queue service to manage)
- Risk: Long jobs block request (mitigated by short jobs only)

**Jobs using this:**
- BroadcastEventJob (publish to Reverb) - milliseconds
- All others removed (ProcessOrderLogs, OrderCheckUpdate) - were dead code

### 2. POS Trigger for Status Sync

**Decision:** Database trigger updates device_orders.status  
**Reason:** Authoritative source of truth is Krypton POS  
**Impact:**
- No polling of POS needed
- Status sync is immediate and atomic
- Tablet polls app API (simple HTTP polling)

**How it works:**
```
POS payment_processed
  → Fire trigger: after_payment_update
  → Execute: UPDATE device_orders SET status='completed' WHERE ...
  → Tablet detects change via polling
  → App broadcasts to WebSocket (optional, for admin)
```

### 3. mkcert for HTTPS (Dev Certs)

**Decision:** Self-signed certificates, not public CA  
**Reason:** Local network only, no external validation needed  
**Impact:**
- Browsers show "certificate not trusted" warning (expected)
- Faster to set up (no Let's Encrypt renewal)
- Free (no commercial cert purchase)

**For production internet use:** Replace with Let's Encrypt (requires DNS validation)

### 4. nginx + PHP-FPM Instead of artisan serve

**Decision:** Production architecture instead of dev server  
**Reason:** 
- artisan serve is for development only (single-process, slow)
- nginx + PHP-FPM is production-ready (concurrent requests, pooling)
- NSSM auto-restart makes it reliable for 24/7

**Benefits:**
- Can handle 10+ concurrent users
- Auto-recovery on crash
- Proper HTTPS termination
- Can scale connections via PHP process pool

---

## Maintenance & Monitoring

### Daily Operations

**Morning startup:**
```powershell
# Automated via Windows startup (services configured for auto-start)
# Or manual:
.\start-production.ps1
```

**Health check:**
```powershell
.\check-services.ps1
# Should show all ✓
```

**Evening shutdown:**
```powershell
# Automated via shutdown script (if scheduled)
# Or manual:
.\stop-production.ps1
```

### Monitoring

**Service status:**
```powershell
Get-Service woosoo-*
```

**Resource usage:**
```powershell
Get-Process php-cgi, nginx | Format-Table Name, CPU, Memory
```

**Logs:**
```powershell
# Last 30 errors
Get-Content "C:\laragon\www\woosoo\apps\woosoo-nexus\storage\logs\laravel.log" -Tail 30
```

### Updates

**Code changes:**
```powershell
# 1. Pull code / edit files
cd C:\laragon\www\woosoo\apps\woosoo-nexus
git pull

# 2. Install dependencies
composer install --no-dev

# 3. Run migrations
php artisan migrate --force

# 4. Clear caches
php artisan config:clear

# 5. Rebuild frontend (if needed)
npm run build

# 6. Services auto-reload (no restart needed)
# PHP-FPM processes new code on next request
```

---

## Troubleshooting Strategy

### Service Won't Start

1. **Check service status:** `Get-Service woosoo-nginx`
2. **Check logs:** `Get-Content "C:\laragon\www\woosoo\logs\nginx\error.log" -Tail 30`
3. **Check binary:** `& "C:\laragon\www\woosoo\bin\nginx\nginx.exe" -t`
4. **Check port:** `netstat -ano | Select-String ":443.*LISTENING"`
5. **Fix & restart:** `Restart-Service woosoo-nginx`

### HTTPS Not Working

1. **Check certificate files exist:** `ls C:\laragon\www\woosoo\certs\`
2. **Check nginx can read them:** `Get-Content "C:\laragon\www\woosoo\certs\admin.woosoo.local.pem" | Measure-Object -Line`
3. **Check hosts file:** Add `127.0.0.1 admin.woosoo.local` to C:\Windows\System32\drivers\etc\hosts
4. **Verify nginx config:** `& "C:\laragon\www\woosoo\bin\nginx\nginx.exe" -t` (should say OK)
5. **Test connection:** `Invoke-WebRequest https://admin.woosoo.local -SkipCertificateCheck`

### API Returning Errors

1. **Check PHP-FPM running:** `Get-Service woosoo-php-fpm` (should be Running)
2. **Check Laravel logs:** `Get-Content "C:\laragon\www\woosoo\apps\woosoo-nexus\storage\logs\laravel.log" -Tail 50`
3. **Check MySQL:** `Test-NetConnection 127.0.0.1 -Port 3306` (should succeed)
4. **Check .env:** `cat C:\laragon\www\woosoo\apps\woosoo-nexus\.env | Select-String DB_`
5. **Restart PHP:** `Restart-Service woosoo-php-fpm`

### WebSocket Not Connected

1. **Check Reverb running:** `Get-Service woosoo-reverb` (should be Running)
2. **Check port 6001:** `netstat -ano | Select-String ":6001.*LISTENING"`
3. **Check firewall:** `New-NetFirewallRule -DisplayName "WebSocket" -Direction Inbound -LocalPort 6001 -Protocol TCP -Action Allow`
4. **Check .env:** REVERB_HOST, REVERB_PORT, REVERB_SCHEME should match
5. **Check logs:** `Get-Content "C:\laragon\www\woosoo\logs\reverb\stderr.txt" -Tail 30`
6. **Restart Reverb:** `Restart-Service woosoo-reverb`

---

## Performance Tuning

### PHP Process Pool

**Default:** 2 processes (configured in install-services.ps1)  
**To increase:** Edit NSSM configuration
```powershell
nssm edit woosoo-php-fpm
# Change: -n 4  (for 4 processes)
# Supports higher concurrency but uses more RAM
```

### nginx Worker Processes

**Default:** auto (uses 1 per CPU core)  
**Configuration:** Edit configs/nginx.conf
```nginx
worker_processes auto;  # or set to specific number
```

### Connection Limits

**nginx client body:** 50MB (for file uploads)  
**nginx keepalive:** 30s (client timeout)  
**Reverb ping:** Every 30s (WebSocket keepalive)  
**PHP execution:** 30s default timeout

---

## Disaster Recovery

### Service Crash Recovery

**Automatic:** Services configured with auto-restart (1 second delay)  
**Manual:** Run `.\start-production.ps1`

### Database Corruption

**Backup:** Implement MySQL backup schedule
```powershell
# Daily backup script (run via Task Scheduler)
mysqldump -u root -p<password> woosoo_api > backup_$(Get-Date -Format "yyyyMMdd").sql
```

### Complete Reset

```powershell
# Stop services
.\stop-production.ps1

# Remove services
nssm remove woosoo-nginx confirm
nssm remove woosoo-php-fpm confirm
nssm remove woosoo-reverb confirm

# Reinstall
.\install-services.ps1

# Reset database
cd C:\laragon\www\woosoo\apps\woosoo-nexus
php artisan migrate:refresh --seed

# Start
.\start-production.ps1
```

---

## Security Considerations

### Current Security

✓ HTTPS encryption (443)  
✓ Local network only (no internet exposure)  
✓ Database credentials in .env (not in code)  
✓ Laravel CSRF protection enabled  
✓ Rate limiting can be enabled  
✓ SQL injection protected (Eloquent ORM)  

### Hardening for Internet

⚠ If exposing to internet, add:
- Valid SSL certificate (Let's Encrypt)
- Firewall rules (allow only 443)
- Password authentication (OAuth2)
- Database user permissions (read-only for POS)
- Backup strategy
- Log aggregation & monitoring
- DDoS protection

---

## Appendix: File Locations

```
C:\laragon\www\woosoo\
├── apps/
│   ├── woosoo-nexus/                     # Admin panel (Laravel)
│   │   ├── .env                          # Configuration
│   │   ├── public/build/                 # Vite assets
│   │   ├── storage/logs/laravel.log      # Application logs
│   │   └── scripts/                      # Deployment scripts
│   ├── tablet-ordering-pwa/              # Tablet app (Nuxt)
│   │   ├── .env                          # Configuration
│   │   └── .output/public/               # Built static files
│   └── relay-device/                     # Printer app (Flutter)
├── bin/
│   ├── nginx/                            # Web server binary
│   ├── php/                              # PHP-FPM binary
│   └── nssm/                             # Service manager
├── certs/                                # SSL certificates
│   ├── admin.woosoo.local.pem            # Certificate file
│   └── admin.woosoo.local-key.pem        # Private key
├── configs/
│   └── nginx.conf                        # nginx configuration
├── logs/
│   ├── nginx/                            # Web server logs
│   ├── php/                              # PHP-FPM logs
│   └── reverb/                           # WebSocket server logs
└── scripts/
    ├── install-services.ps1              # Service installation
    ├── start-production.ps1              # Service startup
    ├── stop-production.ps1               # Service shutdown
    └── check-services.ps1                # Health check
```

---

**Document Version:** 1.0  
**Last Updated:** January 10, 2025  
**Status:** Production Ready
