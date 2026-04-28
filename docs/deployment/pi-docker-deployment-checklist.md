# Woosoo Raspberry Pi Docker Deployment Checklist

This is the single-file deployment checklist for running Woosoo on a Raspberry Pi 5 using Docker Compose.

It covers folder layout, required files, setup commands, Docker commands, verification, health checks, backups, and troubleshooting.

---

## 1. Target Architecture

```txt
Ordering Tablets
  → https://woosoo.local:4443
  → Tablet Ordering PWA

Admin / Staff Browser
  → https://woosoo.local
  → Woosoo Nexus admin/API

Print Bridge Tablet
  → https://woosoo.local
  → Fetches/listens for print jobs
  → Sends receipt to Bluetooth thermal printer

Raspberry Pi 5
  → Docker Compose stack
  → Nginx, Laravel, MySQL, Redis, Reverb, Queue, Scheduler, Tablet PWA
```

Access map:

```txt
Admin/API:     https://woosoo.local
Tablet PWA:    https://woosoo.local:4443
Print bridge:  https://woosoo.local
Reverb/WSS:    wss://woosoo.local/app
```

---

## 2. Required Folder Layout

The Pi should use this layout:

```txt
/opt/woosoo/
├── woosoo-nexus/
│   ├── compose.yaml
│   ├── Dockerfile
│   ├── .env
│   ├── docker/
│   │   ├── nginx/
│   │   │   └── default.conf
│   │   ├── certs/
│   │   │   ├── fullchain.pem
│   │   │   └── privkey.pem
│   │   └── php/
│   │       ├── local.ini
│   │       └── www.conf
│   └── scripts/
│       └── deployment/
│           ├── apply-woosoo-config.sh
│           ├── woosoo-health.sh
│           └── woosoo-backup.sh
│
└── tablet-ordering-pwa/
    ├── Dockerfile
    ├── package.json
    ├── package-lock.json
    ├── nuxt.config.ts
    └── app source files
```

Important:

```txt
woosoo-nexus/compose.yaml builds tablet-pwa from ../tablet-ordering-pwa
```

So both repos must be siblings under `/opt/woosoo`.

---

## 3. Required Files Checklist

### woosoo-nexus

```txt
[ ] compose.yaml
[ ] Dockerfile
[ ] .env.example
[ ] docker/nginx/default.conf
[ ] docker/certs/README.md
[ ] docker/certs/generate-dev-certs.sh
[ ] docker/certs/fullchain.pem       # generated locally, do not commit
[ ] docker/certs/privkey.pem         # generated locally, do not commit
[ ] docker/php/local.ini
[ ] docker/php/www.conf
[ ] docker/docker-entrypoint.sh
[ ] scripts/deployment/apply-woosoo-config.sh
[ ] scripts/deployment/woosoo-health.sh
[ ] scripts/deployment/woosoo-backup.sh
```

### tablet-ordering-pwa

```txt
[ ] Dockerfile
[ ] .dockerignore
[ ] package.json
[ ] package-lock.json
[ ] nuxt.config.ts
[ ] public/
[ ] app/pages/components/stores source files
```

---

## 4. Network Plan

Example values:

```txt
Router/gateway:       192.168.100.1
Raspberry Pi server:  192.168.100.10
POS host:             192.168.100.20
Tablet DNS 1:         192.168.100.10
Tablet DNS 2:         blank or 192.168.100.10
```

Rules:

```txt
Tablets keep DHCP for IP assignment.
Tablets manually use the Pi IP as DNS.
Do not use public DNS like 8.8.8.8 as tablet DNS 2.
All normal client access should use woosoo.local, not the raw Pi IP.
```

---

## 5. Base Raspberry Pi Setup

Update OS:

```bash
sudo apt update
sudo apt full-upgrade -y
sudo reboot
```

After reboot:

```bash
sudo apt install -y git curl ca-certificates dnsutils nano unzip
sudo timedatectl set-timezone Asia/Manila
```

Install Docker:

```bash
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker $USER
sudo systemctl enable docker
sudo systemctl start docker
sudo reboot
```

Verify Docker:

```bash
docker --version
docker compose version
```

---

## 6. Clone Repositories

```bash
sudo mkdir -p /opt/woosoo
sudo chown -R $USER:$USER /opt/woosoo
cd /opt/woosoo

git clone https://github.com/tech-artificer/woosoo-nexus.git
git clone https://github.com/tech-artificer/tablet-ordering-pwa.git
```

Check out staging branches:

```bash
cd /opt/woosoo/woosoo-nexus
git checkout staging
git pull origin staging

cd /opt/woosoo/tablet-ordering-pwa
git checkout staging
git pull origin staging
```

Return to backend repo:

```bash
cd /opt/woosoo/woosoo-nexus
```

---

## 7. Make Deployment Scripts Executable

```bash
cd /opt/woosoo/woosoo-nexus
chmod +x scripts/deployment/apply-woosoo-config.sh
chmod +x scripts/deployment/woosoo-health.sh
chmod +x scripts/deployment/woosoo-backup.sh
```

---

## 8. Create Woosoo Server Config

Create config directory:

```bash
sudo mkdir -p /etc/woosoo
```

Copy example config:

```bash
sudo cp docs/deployment/examples/woosoo.env.example /etc/woosoo/woosoo.env
```

Edit values:

```bash
sudo nano /etc/woosoo/woosoo.env
```

Important values:

```bash
WOOSOO_HOST="woosoo.local"
WOOSOO_SERVER_IP="192.168.100.10"
WOOSOO_GATEWAY="192.168.100.1"
WOOSOO_CIDR="24"
WOOSOO_NEXUS_PATH="/opt/woosoo/woosoo-nexus"
WOOSOO_DOCKER_COMPOSE="docker compose -f compose.yaml"
WOOSOO_POS_HOST="192.168.100.20"
WOOSOO_POS_PORT="3308"
```

Secure config file:

```bash
sudo chown root:root /etc/woosoo/woosoo.env
sudo chmod 600 /etc/woosoo/woosoo.env
```

---

## 9. Create Laravel `.env`

```bash
cd /opt/woosoo/woosoo-nexus
cp .env.example .env
nano .env
```

Minimum important values:

```env
PUBLIC_SCHEME=https
PUBLIC_HOST=woosoo.local
PUBLIC_HTTP_PORT=80
PUBLIC_HTTPS_PORT=443
TABLET_HTTPS_PORT=4443
APP_ENV=production
APP_DEBUG=false
APP_URL=https://woosoo.local
APP_TIMEZONE=Asia/Manila

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=woosoo
DB_USERNAME=woosoo
DB_PASSWORD=change_this_password
DB_ROOT_PASSWORD=change_this_root_password

DB_POS_HOST=192.168.100.20
DB_POS_PORT=3308
DB_POS_DATABASE=krypton_woosoo
DB_POS_USERNAME=krypton_readonly
DB_POS_PASSWORD=

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
REDIS_HOST=redis

BROADCAST_DRIVER=reverb
REVERB_APP_ID=woosoo
REVERB_APP_KEY=change_this_reverb_key
REVERB_APP_SECRET=change_this_reverb_secret
REVERB_HOST=0.0.0.0
REVERB_PUBLIC_HOST=woosoo.local
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY=change_this_reverb_key
VITE_REVERB_HOST=woosoo.local
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https

SESSION_DOMAIN=woosoo.local
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
SANCTUM_STATEFUL_DOMAINS=woosoo.local,woosoo.local:443,woosoo.local:80,woosoo.local:4443
CORS_ALLOWED_ORIGINS=https://woosoo.local,http://woosoo.local,https://woosoo.local:4443
```

Important:

```txt
Tablet PWA runs on :4443.
Laravel API runs on :443.
Because browser origins include the port, :4443 must be included in both CORS_ALLOWED_ORIGINS and SANCTUM_STATEFUL_DOMAINS.
```

---

## 10. Generate TLS Certificates

The Nginx config expects:

```txt
woosoo-nexus/docker/certs/fullchain.pem
woosoo-nexus/docker/certs/privkey.pem
```

Generate dev/self-signed certs:

```bash
cd /opt/woosoo/woosoo-nexus/docker/certs
chmod +x generate-dev-certs.sh
./generate-dev-certs.sh 192.168.100.10
cd /opt/woosoo/woosoo-nexus
```

Verify files:

```bash
ls -l docker/certs/fullchain.pem docker/certs/privkey.pem
```

Install/trust the cert on tablets if Android/browser warns.

---

## 11. Apply Host Configuration

Run from `woosoo-nexus`:

```bash
cd /opt/woosoo/woosoo-nexus
sudo bash scripts/deployment/apply-woosoo-config.sh
```

This configures:

```txt
static Pi IP through NetworkManager
dnsmasq local DNS
/etc/hosts fallback
Laravel .env values
certificate directory check
Docker stack startup/cache refresh
```

SSH warning:

```txt
If running over SSH and changing the active IP, the script refuses unless FORCE_APPLY_STATIC_IP=true is set.
Prefer doing static IP changes from the Pi console.
```

---

## 12. Build and Start Docker Stack

```bash
cd /opt/woosoo/woosoo-nexus
docker compose -f compose.yaml up -d --build
```

Watch logs:

```bash
docker compose -f compose.yaml logs -f
```

Check containers:

```bash
docker compose -f compose.yaml ps
```

Expected services:

```txt
nginx
app
queue
scheduler
reverb
tablet-pwa
mysql
redis
```

---

## 13. First-Run Laravel Commands

Install/update PHP dependencies inside app container if needed:

```bash
docker compose -f compose.yaml exec app composer install --no-dev --optimize-autoloader
```

Generate app key only on first install:

```bash
docker compose -f compose.yaml exec app php artisan key:generate
```

Do not run `key:generate` again on an existing production DB unless intentionally rotating `APP_KEY`.

Run migrations and optimize:

```bash
docker compose -f compose.yaml exec app php artisan migrate --force
docker compose -f compose.yaml exec app php artisan storage:link || true
docker compose -f compose.yaml exec app php artisan config:clear
docker compose -f compose.yaml exec app php artisan cache:clear
docker compose -f compose.yaml exec app php artisan route:clear
docker compose -f compose.yaml exec app php artisan view:clear
docker compose -f compose.yaml exec app php artisan config:cache
docker compose -f compose.yaml exec app php artisan route:cache
docker compose -f compose.yaml exec app php artisan view:cache
```

---

## 14. Tablet Setup

For each ordering tablet:

```txt
Wi-Fi IP assignment: DHCP
DNS 1: Raspberry Pi IP, e.g. 192.168.100.10
DNS 2: blank or same as DNS 1
Browser URL: https://woosoo.local:4443
```

Then install/add the PWA to home screen if needed.

---

## 15. Print Bridge Setup

The print bridge tablet should:

```txt
Pair with the Bluetooth thermal printer.
Use backend/server URL: https://woosoo.local
Fetch/listen for print jobs from the backend.
Print via Bluetooth.
Mark orders as printed through the backend API.
```

Important:

```txt
The Raspberry Pi does not pair directly with the Bluetooth printer.
The print bridge tablet is the Bluetooth relay device.
```

---

## 16. Verification Commands

### Verify files

```bash
cd /opt/woosoo/woosoo-nexus

ls -l compose.yaml
ls -l Dockerfile
ls -l docker/nginx/default.conf
ls -l docker/php/local.ini
ls -l docker/php/www.conf
ls -l docker/docker-entrypoint.sh
ls -l docker/certs/fullchain.pem docker/certs/privkey.pem

cd /opt/woosoo/tablet-ordering-pwa
ls -l Dockerfile
ls -l package.json
ls -l package-lock.json
ls -l nuxt.config.ts
```

### Verify DNS

```bash
dig woosoo.local @127.0.0.1 +short
```

Expected:

```txt
192.168.100.10
```

### Verify Docker

```bash
cd /opt/woosoo/woosoo-nexus
docker compose -f compose.yaml ps
```

### Verify ports

```bash
sudo ss -lntup | grep -E ':(53|80|443|4443)\b'
```

### Verify HTTPS

```bash
curl -k -I https://woosoo.local
curl -k -I https://woosoo.local:4443
```

### Verify Laravel API from Pi

```bash
curl -k https://woosoo.local/api
```

The response depends on available routes, but it should not fail due to DNS/TLS/connectivity.

### Verify CORS/Sanctum values

```bash
grep '^CORS_ALLOWED_ORIGINS=' /opt/woosoo/woosoo-nexus/.env
grep '^SANCTUM_STATEFUL_DOMAINS=' /opt/woosoo/woosoo-nexus/.env
```

Must include:

```txt
https://woosoo.local:4443
woosoo.local:4443
```

### Verify logs

```bash
docker compose -f compose.yaml logs nginx --tail=100
docker compose -f compose.yaml logs app --tail=100
docker compose -f compose.yaml logs tablet-pwa --tail=100
docker compose -f compose.yaml logs reverb --tail=100
```

---

## 17. Browser Smoke Test

From tablet or desktop browser:

```txt
https://woosoo.local           → admin/API host loads
https://woosoo.local:4443      → tablet PWA loads
```

Open DevTools on the tablet PWA:

```txt
Network tab: API calls to https://woosoo.local/api should not show CORS errors.
Console tab: WebSocket should not show failed /app connection errors.
```

---

## 18. Health Check

Run:

```bash
cd /opt/woosoo/woosoo-nexus
sudo bash scripts/deployment/woosoo-health.sh
```

Checks include:

```txt
expected Pi IP
dnsmasq
woosoo.local DNS
ports 53/80/443/4443
admin HTTPS
tablet PWA HTTPS
Reverb route
Docker containers
disk
memory
temperature
recent dnsmasq logs
```

---

## 19. Backup

Manual backup:

```bash
cd /opt/woosoo/woosoo-nexus
sudo bash scripts/deployment/woosoo-backup.sh
```

Backups are stored under:

```txt
/opt/woosoo/backups/db
```

Cron example:

```cron
0 3 * * * /bin/bash /opt/woosoo/woosoo-nexus/scripts/deployment/woosoo-backup.sh >> /var/log/woosoo-backup.log 2>&1
```

Retention is controlled by:

```bash
WOOSOO_BACKUP_RETENTION_DAYS="14"
```

---

## 20. Reboot Survival Test

```bash
sudo reboot
```

After reboot:

```bash
cd /opt/woosoo/woosoo-nexus
sudo bash scripts/deployment/woosoo-health.sh
docker compose -f compose.yaml ps
```

Pass criteria:

```txt
Pi has expected IP
dnsmasq is active
woosoo.local resolves
Docker containers are running
https://woosoo.local responds
https://woosoo.local:4443 responds
Reverb route does not return 502
Disk has free space
Temperature is safe
```

---

## 21. Common Troubleshooting

### Tablet cannot open `woosoo.local`

Check tablet DNS:

```txt
DNS 1 must be Raspberry Pi IP.
DNS 2 must be blank or same Raspberry Pi IP.
```

Check Pi DNS:

```bash
dig woosoo.local @127.0.0.1 +short
sudo systemctl status dnsmasq --no-pager
```

### Tablet PWA loads but API fails

Check `.env`:

```bash
grep '^CORS_ALLOWED_ORIGINS=' .env
grep '^SANCTUM_STATEFUL_DOMAINS=' .env
```

Must include `:4443`.

Then clear/cache config:

```bash
docker compose -f compose.yaml exec app php artisan config:clear
docker compose -f compose.yaml exec app php artisan cache:clear
docker compose -f compose.yaml exec app php artisan config:cache
```

### Nginx fails to start

Check certs:

```bash
ls -l docker/certs/fullchain.pem docker/certs/privkey.pem
```

Check logs:

```bash
docker compose -f compose.yaml logs nginx --tail=100
```

### Backend image build fails

Check required Docker build files:

```bash
ls -l docker/php/www.conf
ls -l docker/docker-entrypoint.sh
ls -l docker/php/local.ini
```

### Tablet PWA image build fails

Check sibling repo and files:

```bash
ls -l /opt/woosoo/tablet-ordering-pwa/Dockerfile
ls -l /opt/woosoo/tablet-ordering-pwa/package-lock.json
```

### WebSocket/Reverb fails

Check logs:

```bash
docker compose -f compose.yaml logs reverb --tail=100
docker compose -f compose.yaml logs nginx --tail=100
```

Check `.env`:

```bash
grep '^REVERB_' .env
grep '^VITE_REVERB_' .env
```

### Orders save but do not print

Check:

```txt
Print bridge server URL is https://woosoo.local
Print bridge tablet is paired to Bluetooth printer
Print bridge can fetch/listen for orders
Backend marks printed status after successful print
```

---

## 22. Update Procedure

```bash
cd /opt/woosoo/woosoo-nexus
git checkout staging
git pull origin staging

cd /opt/woosoo/tablet-ordering-pwa
git checkout staging
git pull origin staging

cd /opt/woosoo/woosoo-nexus
docker compose -f compose.yaml up -d --build
docker compose -f compose.yaml exec app php artisan migrate --force
docker compose -f compose.yaml exec app php artisan config:cache
docker compose -f compose.yaml ps
sudo bash scripts/deployment/woosoo-health.sh
```

---

## 23. Final Go-Live Checklist

```txt
[ ] Pi boots from M.2/NVMe SSD
[ ] Pi has stable static IP
[ ] dnsmasq resolves woosoo.local
[ ] Tablet DNS points to Pi
[ ] TLS certs exist and Nginx starts
[ ] Admin loads at https://woosoo.local
[ ] Tablet PWA loads at https://woosoo.local:4443
[ ] Tablet PWA API calls have no CORS errors
[ ] Reverb/WebSocket connects
[ ] Laravel migrations completed
[ ] Queue worker running
[ ] Scheduler running
[ ] POS host configured
[ ] Print bridge uses https://woosoo.local
[ ] Bluetooth printer paired to print bridge tablet
[ ] Test order prints successfully
[ ] Backup script runs successfully
[ ] Health script passes
[ ] Reboot survival test passes
```
