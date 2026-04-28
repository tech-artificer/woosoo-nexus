Complete Pi Operations Reference
SSH Access

# Always connect via hostname (works regardless of IP)
ssh woosoo@woosoo-server.local
Moving to a New Network
Step 1 — Connect to new WiFi


sudo nmcli dev wifi list
sudo nmcli dev wifi connect "NETWORK_SSID" password "WIFI_PASSWORD"
Step 2 — Find assigned IP


hostname -I
Step 3 — Set static IP


sudo nmcli con mod "netplan-wlan0-blk 12 lt 31_2.4G" \
  ipv4.addresses 192.168.1.32/24 \
  ipv4.gateway 192.168.1.1 \
  ipv4.dns 127.0.0.1 \
  ipv4.method manual
sudo nmcli con up "netplan-wlan0-blk 12 lt 31_2.4G"
SSH drops here. Reconnect via ssh woosoo@woosoo-server.local

Step 4 — Update dnsmasq


sudo nano /etc/dnsmasq.conf
Update these 3 lines:


address=/woosoo.local/<NEW_PI_IP>
address=/krypton.local/<NEW_KRYPTON_IP>
listen-address=127.0.0.1,<NEW_PI_IP>

sudo systemctl restart dnsmasq
ping -c 1 woosoo.local
ping -c 1 krypton.local
Step 5 — Update each tablet's WiFi DNS
Settings → WiFi → long-press network → Modify → Advanced → IP settings: Static → DNS 1: <NEW_PI_IP>

Changing Krypton DB Credentials

sudo nano /etc/woosoo/.env
Update the DB_POS_* lines:


DB_POS_HOST=krypton.local
DB_POS_PORT=3308
DB_POS_DATABASE=krypton_woosoo
DB_POS_USERNAME=woosoo_pos
DB_POS_PASSWORD=<new_password>
Save: Ctrl+O → Ctrl+X

Re-cache and test:


sudo -u www-data php /srv/woosoo/nexus/artisan config:cache
sudo -u www-data php /srv/woosoo/nexus/artisan tinker
>>> DB::connection('pos')->select('SELECT 1');
Full Redeploy (after code push)

# 1. Pull code
sudo git config --global --add safe.directory /srv/woosoo/nexus
sudo git -C /srv/woosoo/nexus pull origin staging

# 2. PHP dependencies
sudo -u www-data composer install --no-dev --optimize-autoloader --working-dir=/srv/woosoo/nexus

# 3. Database
sudo -u www-data php /srv/woosoo/nexus/artisan migrate --force

# 4. Frontend assets
sudo chown -R woosoo:woosoo /srv/woosoo/nexus/node_modules /srv/woosoo/nexus/public/build 2>/dev/null || true
cd /srv/woosoo/nexus && npm ci
sudo chmod 644 /etc/woosoo/.env && npm run build && sudo chmod 640 /etc/woosoo/.env
sudo chown -R www-data:www-data /srv/woosoo/nexus/public/build /srv/woosoo/nexus/node_modules

# 5. Caches
sudo -u www-data php /srv/woosoo/nexus/artisan config:cache
sudo -u www-data php /srv/woosoo/nexus/artisan route:cache
sudo -u www-data php /srv/woosoo/nexus/artisan view:cache

# 6. Restart workers
sudo supervisorctl restart all
PWA Redeploy (after PWA code push)

cd /tmp
git clone https://github.com/tech-artificer/tablet-ordering-pwa.git pwa-build
cd /tmp/pwa-build
git checkout staging
npm ci

REVERB_KEY=$(sudo grep '^REVERB_APP_KEY=' /etc/woosoo/.env | cut -d= -f2)

NODE_ENV=production \
NUXT_PUBLIC_API_BASE_URL=/api \
NUXT_PUBLIC_REVERB_APP_KEY=$REVERB_KEY \
NUXT_PUBLIC_REVERB_HOST= \
NUXT_PUBLIC_REVERB_PORT=443 \
NUXT_PUBLIC_REVERB_SCHEME=https \
NUXT_PUBLIC_REVERB_PATH=/app \
npx --yes nuxi generate

sudo rm -rf /srv/woosoo/pwa/*
sudo cp -r /tmp/pwa-build/.output/public/* /srv/woosoo/pwa/
sudo chown -R www-data:www-data /srv/woosoo/pwa/
cd ~ && rm -rf /tmp/pwa-build
Supervisor (Workers)

sudo supervisorctl status           # check all 4 workers
sudo supervisorctl restart all      # restart after code pull
sudo supervisorctl restart laravel-reverb  # restart one worker

tail -f /var/log/woosoo/reverb.log
tail -f /var/log/woosoo/queue-error.log
nginx

sudo nginx -t                        # test config syntax
sudo systemctl reload nginx          # apply config changes
sudo tail -f /var/log/nginx/access.log
sudo tail -20 /var/log/nginx/error.log
Laravel Artisan

# Migrations & seeders
sudo -u www-data php /srv/woosoo/nexus/artisan migrate --force
sudo -u www-data php /srv/woosoo/nexus/artisan db:seed --force

# Rebuild caches (after every code pull or .env change)
sudo -u www-data php /srv/woosoo/nexus/artisan config:cache
sudo -u www-data php /srv/woosoo/nexus/artisan route:cache
sudo -u www-data php /srv/woosoo/nexus/artisan view:cache

# Clear caches (troubleshooting)
sudo -u www-data php /srv/woosoo/nexus/artisan config:clear
sudo -u www-data php /srv/woosoo/nexus/artisan route:clear
sudo -u www-data php /srv/woosoo/nexus/artisan view:clear

# Storage symlink
sudo -u www-data php /srv/woosoo/nexus/artisan storage:link
Verification

curl -sk https://woosoo.local/up -o /dev/null -w "%{http_code}\n"   # → 200
curl -sk https://woosoo.local/ -o /dev/null -w "%{http_code}\n"     # → 200
curl -sk https://woosoo.local/api/config
sudo supervisorctl status
sudo tail -30 /srv/woosoo/nexus/storage/logs/laravel.log
What Never Changes Between Networks
Item	Reason
TLS certificate	Domain-based (woosoo.local), not IP
.env APP_KEY / REVERB_APP_KEY	Fixed — never rotate unless security incident
nginx config	No IPs hardcoded
PWA rebuild	Uses browser hostname automatically
Krypton .env credentials	Only change if password/port changes
