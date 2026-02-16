# On-Prem DNS and HTTPS Setup (Windows)

One URL, one cert, zero router changes, and no IPs in code for Laravel 12 (Admin UI + API) and Reverb (WSS), plus Nuxt/Capacitor tablets.

## Outcome
- Single domain everywhere: https://admin.panel for Admin UI, API, and Reverb.
- No router/DNS changes; clients use a hosts entry installed by a script.
- HTTPS via mkcert; trusted on server, Windows clients, and Android tablets.
- One-click server install and one-click client install; zero manual IP configuration.

## Architecture
```
Clients (Windows / Tablets)
  -> hosts: admin.panel -> server IP
  -> mkcert root CA trusted
  -> Browser / PWA / Capacitor
          |
          v
Nginx (Windows) -- HTTPS reverse proxy
   -> Laravel 12 (127.0.0.1:8000)
   -> Reverb (127.0.0.1:8080, WSS)
```

## Deployment Package Layout
```
AdminPanel-Deployment/
 server/
   install-server.ps1
   nginx/admin.panel.conf
   laravel/.env.template
   mkcert/mkcert.exe
   README-server.md
 client/
   install-client.ps1
   mkcert/mkcert.exe
   README-client.md
 README.md
```

## Part A  Server Setup (Laravel 12)
### Step 1  Prerequisites (once)
Install dependencies (PHP 8.2+):
```powershell
choco install nginx php composer nodejs mkcert nssm -y
php -v
composer -V
```

### Step 2  Project prep
From server/laravel:
```powershell
composer install
npm install
npm run build
```

### Step 3  .env.template
Create server/laravel/.env.template:
```env
APP_NAME=AdminPanel
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://admin.panel

LOG_CHANNEL=stack

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=admin
DB_USERNAME=root
DB_PASSWORD=

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SANCTUM_STATEFUL_DOMAINS=admin.panel

BROADCAST_CONNECTION=reverb

REVERB_APP_ID=local
REVERB_APP_KEY=local
REVERB_APP_SECRET=local
REVERB_HOST=admin.panel
REVERB_PORT=443
REVERB_SCHEME=https
```

### Step 4  mkcert (server)
```powershell
mkcert -install
mkdir C:\AdminPanelCerts
mkcert admin.panel `
  -cert-file C:\AdminPanelCerts\admin.panel.pem `
  -key-file  C:\AdminPanelCerts\admin.panel-key.pem
```

### Step 5  Nginx (unified URL)
server/nginx/admin.panel.conf:
```nginx
server {
    listen 443 ssl;
    server_name admin.panel;

    ssl_certificate     C:/AdminPanelCerts/admin.panel.pem;
    ssl_certificate_key C:/AdminPanelCerts/admin.panel-key.pem;

    client_max_body_size 50M;

    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-Proto https;
        proxy_set_header X-Forwarded-For $remote_addr;
    }

    location /reverb {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_set_header Host $host;
    }
}

server {
    listen 80;
    server_name admin.panel;
    return 301 https://$host$request_uri;
}
```

### Step 6  Run Laravel and Reverb
```powershell
php artisan key:generate
php artisan migrate --force
php artisan serve --host=127.0.0.1 --port=8000
php artisan reverb:start --host=127.0.0.1 --port=8080
```
Later: wrap both commands as Windows services with NSSM for auto-start.

### Step 7  install-server.ps1 (automation checklist)
- Installs Nginx and mkcert.
- Generates certificates.
- Copies the Nginx config.
- Prepares .env from the template.
- Reloads Nginx.

## Part B  Client Setup (no router access)
- Script scans LAN for the server on port 443, adds admin.panel to the hosts file, installs mkcert root CA, trusts HTTPS, and opens the browser.
- Expected hosts entry example: 192.168.x.x admin.panel.
- Requirements: Windows, one-time admin rights, modern browser.

## Part C  Frontend and Reverb config
Example Reverb client (JS):
```ts
import Echo from 'laravel-echo'

window.Echo = new Echo({
  broadcaster: 'reverb',
  host: 'admin.panel',
  scheme: 'https',
  port: 443,
})
```

## Part D  Verification checklist
- https://admin.panel loads with no HTTPS warnings.
- API responds over the same domain.
- Reverb connects over WSS and receives broadcasts.
- No IPs present in code or configs.

## Part E  Rationale
- Matches patterns used by POS, hospital, and factory systems.
- Works offline for the UI shell; easy to move servers later.
- Cloud-like UX while fully on-prem and router-free.

## Nuxt and Capacitor Roadmap (on-prem safe mode)
### Nuxt config (single domain, no env switching)
```ts
// nuxt.config.ts
export default defineNuxtConfig({
  app: { baseURL: '/' },
  runtimeConfig: {
    public: {
      apiBase: 'https://admin.panel/api',
      reverbHost: 'admin.panel',
      reverbScheme: 'https',
      reverbPort: 443,
    },
  },
})
```

API usage:
```ts
const config = useRuntimeConfig()
await $fetch(`${config.public.apiBase}/orders`)
```

### Reverb (Nuxt side)
```ts
import Echo from 'laravel-echo'

const config = useRuntimeConfig()

export const echo = new Echo({
  broadcaster: 'reverb',
  host: config.public.reverbHost,
  scheme: config.public.reverbScheme,
  port: config.public.reverbPort,
  secure: true,
  transports: ['websocket'],
})
```

### PWA config (matters for Capacitor too)
```ts
// nuxt.config.ts
export default defineNuxtConfig({
  modules: ['@vite-pwa/nuxt'],
  pwa: {
    registerType: 'autoUpdate',
    manifest: {
      name: 'Admin Panel',
      short_name: 'Admin',
      display: 'fullscreen',
      theme_color: '#000000',
      background_color: '#000000',
      start_url: '/',
    },
    workbox: {
      navigateFallback: '/',
      runtimeCaching: [
        {
          urlPattern: /^https:\/\/admin\.panel\/api\/.*$/,
          handler: 'NetworkFirst',
        },
      ],
    },
  },
})
```
Result: offline shell, cached UI, live API when available, and seamless updates.

### Capacitor prep (Android)
```bash
npm install @capacitor/core @capacitor/cli
npx cap init admin.panel com.company.adminpanel
```

capacitor.config.ts:
```ts
import { CapacitorConfig } from '@capacitor/cli'

const config: CapacitorConfig = {
  appId: 'com.company.adminpanel',
  appName: 'Admin Panel',
  webDir: '.output/public',
  bundledWebRuntime: false,
  server: {
    url: 'https://admin.panel',
    cleartext: false,
  },
}

export default config
```
This forces the Android app to load remote content from admin.panel, enabling remote updates without rebuilding the APK.

### Build flow (Nuxt to Android)
```bash
npm run build
npx cap add android
npx cap sync android
npx cap open android
```

### Android HTTPS trust
- Preferred: install the mkcert root CA on the tablet as a user-trusted CA.
- Fallback network security config (requires trusted cert):
```xml
<!-- android/app/src/main/res/xml/network_security_config.xml -->
<network-security-config>
  <domain-config cleartextTrafficPermitted="false">
    <domain includeSubdomains="true">admin.panel</domain>
  </domain-config>
</network-security-config>
```
Add to AndroidManifest.xml:
```xml
android:networkSecurityConfig="@xml/network_security_config"
```

### Offline and update strategy
- Nuxt is served by Laravel; clients always load from https://admin.panel.
- Deploy a new Nuxt build; clients refresh to update. No store, no reinstall.

### Optional tablet hardening
- Enable kiosk mode, lock orientation, disable system UI, auto-launch on boot.

### Tablet stack go/no-go checklist
- https://admin.panel opens in browser.
- PWA installs; Capacitor app loads the remote URL.
- HTTPS trusted; Reverb connects over WSS.
- Offline shell works; updates propagate without APK rebuild.

## Master TODO  On-Prem Admin Panel and Tablets
- Phase 0: Decide domain (admin.panel), server machine, LAN subnet; confirm client admin rights and CA install.
- Phase 1: Base server setup (Chocolatey, PHP/Node), install deps, build Laravel, run php artisan serve on 8000.
- Phase 2: mkcert root install and cert generation to C:\AdminPanelCerts.
- Phase 3: Nginx reverse proxy for / to 8000 and /reverb to 8080; HTTP to HTTPS redirect; firewall 443 (80 optional).
- Phase 4: Start Reverb on 8080; target wss://admin.panel/reverb.
- Phase 5: Convert Laravel and Reverb commands to Windows services (NSSM) and set auto-start.
- Phase 6: Client installer scans LAN for 443, writes hosts entry, installs mkcert CA, trusts cert, opens https://admin.panel (one-time admin rights).
- Phase 7: Nuxt runtime config uses only admin.panel (no IPs, no env switching); API and Reverb URLs come from runtimeConfig.
- Phase 8: PWA module with offline shell and network-first API caching; fullscreen display.
- Phase 9: Capacitor init, remote server config, add Android, build/sync/open in Android Studio.
- Phase 10: Android HTTPS trust via mkcert CA (preferred) or network security config.
- Phase 11: Update strategy  deploy Nuxt build to Laravel; clients refresh to update (no store, no reinstall).
- Phase 12: Optional hardening  kiosk mode, device ID registration, heartbeat, auto-launch.

## Advanced Device Management and Monitoring
### Phase 1  Device registration and authentication
- Generate per-device codes; store in devices table (id, device_code, ip_address, mac_address, registered_at, status).
- On startup the tablet checks registration, authenticates with device code, and stores session locally.
- Block unregistered devices; allow admin override for replacements.

### Phase 2  Auto-enrollment and table assignment
- On first boot, tablet sends device code plus device info; server validates and assigns table ID.
- Tablet only allows orders when a table is assigned; admin can reassign remotely.

### Phase 3  Single order session lifecycle
- Tablet requests a new session on open; server returns session_id and timestamp.
- Session stays active during ordering; server broadcasts session_closed and tablet resets when done.

### Phase 4  Heartbeat monitoring
- Tablet posts heartbeat every few seconds with device code, session id, and status; server updates last_seen.
- Admin dashboard shows online/offline state and active session; alert when stale.

### Phase 5  Remote updates
- Server deploys new Nuxt build; optional version number in DB.
- Tablet compares version, refreshes WebView if newer; show updating modal if desired.

### Phase 6  Admin dashboard enhancements
- List devices with registration status, table assignment, last heartbeat, and active orders.
- Allow remote session close/cancel and manual Nuxt reload trigger.

### Phase 7  Security hardening
- Enforce HTTPS (https://admin.panel) everywhere; validate device token on each API call and WSS connection.
- Rate-limit heartbeat API; auto-logout inactive sessions; optionally encrypt sensitive device data.

### Phase 8  Optional extras
- Kiosk mode, orientation lock, disable back button, auto-launch on boot.
- Server-side device logs and session event logs; rollback by re-registering device or rolling back Nuxt build.

## Result
- Cloud-style UX fully on-prem, single trusted domain, no IPs in code, zero ongoing client configuration.
- Remote updates for tablets without rebuilding APKs; offline-capable UI with secure WSS.
