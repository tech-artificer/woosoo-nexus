# Woosoo Nexus — Integrated Restaurant POS System

**Version:** 1.0  
**Last Updated:** January 4, 2026

Woosoo Nexus is a comprehensive restaurant management system consisting of three integrated products:

1. **Admin Panel** (Laravel + Inertia.js + Vue 3) — Web-based management interface
2. **Tablet Ordering PWA** (Nuxt 3) — Customer-facing kiosk ordering system
3. **Relay Printer App** (Flutter) — Mobile thermal printer relay device

---

## 🚀 Quick Start

### Prerequisites
- PHP 8.2+
- Node.js 18+
- MySQL 8.0+
- Composer 2.x
- Flutter 3.9.2+ (for relay device only)

### Installation (PowerShell)

```powershell
# Clone repository
git clone <repository-url> woosoo-nexus
cd woosoo-nexus

# Install dependencies & setup
composer install
npm ci
cp .env.example .env
php artisan key:generate
php artisan migrate --seed

# Start all services (HTTP, Queue, Vite, Reverb)
composer dev
```

**Verify setup:**
- HTTP: http://127.0.0.1:8000
- Vite HMR: http://127.0.0.1:5173
- Reverb WebSocket: Port 6001

### Individual Product Setup

#### Tablet Ordering PWA
```powershell
cd tablet-ordering-pwa
npm install
npm run dev
```

#### Relay Printer App
```powershell
cd relay-device
flutter pub get
flutter run -d <device>
```

---

## 📁 Repository Structure

```
woosoo-nexus/
├── app/                       # Laravel application (Admin Panel backend)
├── resources/js/              # Vue 3 + Inertia.js frontend
├── tablet-ordering-pwa/       # Nuxt 3 PWA (customer kiosk)
├── relay-device/              # Flutter printer relay app
├── print-service/             # Node.js print service (Express, port 9100)
├── docs/                      # Technical documentation
├── .github/                   # GitHub workflows & copilot instructions
└── tests/                     # Feature & unit tests
```

---

## 🏗️ Architecture Overview

### Tech Stack
- **Backend:** Laravel 12, MySQL 8.0, Reverb (WebSocket)
- **Admin Frontend:** Vue 3, TypeScript, Inertia.js, Tailwind v4, shadcn-vue
- **Tablet PWA:** Nuxt 3, Pinia, Axios, PWA with offline support
- **Relay Device:** Flutter, Riverpod, Blue Thermal Printer, WebSocket

### Real-time Communication
- **Reverb** (port 6001) — WebSocket server for broadcasting events
- **Channels:** `device.{deviceId}`, `admin.orders`, `admin.print`, `admin.service-requests`
- **Frontend Listeners:** Laravel Echo (Vue) + Echo client (Nuxt)

### Database Architecture
- **`mysql` connection** (default) — App data (users, devices, menus, orders, branches, roles)
- **`pos` connection** — Read-only Krypton legacy POS (`krypton_woosoo` DB)
- **Test:** SQLite in-memory (configured in `phpunit.xml`)

---

## 📚 Documentation

### For Developers
- **[AI Agent Instructions](.github/copilot-instructions.md)** — Comprehensive guide for AI coding assistants (572 lines)
- **[Documentation Index](DOCUMENTATION_INDEX.md)** — Master navigation for all docs
- **[API Reference](docs/API_MAP.md)** — Complete API endpoint documentation
- **[Testing Guide](TESTING_VALIDATION_GUIDE.md)** — 15 test procedures
- **[Implementation Checklist](IMPLEMENTATION_CHECKLIST.md)** — Remaining tasks (6 items)

### Product-Specific Documentation
- **Admin Panel:** [.github/copilot-instructions.md](.github/copilot-instructions.md)
- **Tablet PWA:** [tablet-ordering-pwa/README.md](tablet-ordering-pwa/README.md) + [tablet-ordering-pwa/.github/copilot-instructions.md](tablet-ordering-pwa/.github/copilot-instructions.md)
- **Relay Device:** [relay-device/README.md](relay-device/README.md) + [relay-device/QUICK_START.md](relay-device/QUICK_START.md)

### Integration Guides
- **[Printer Integration](docs/printer_readme.md)** — Device registration, API endpoints, WebSocket events
- **[Admin Manual](docs/admin_manual.md)** — Staff operations guide
- **[Order Restrictions](README_ORDER_RESTRICTIONS.md)** — Business rules implementation

### User Guides
Located in `resources/docs/guides/`:
- **Admin:** login, add-user, manage-orders, register-device, menu-availability, overview
- **Tablet:** navigation, place-order, requirements, troubleshooting
- **Relay:** install, connect-printer, check-status, requirements

---

## 🔧 Development Workflow

### Running Services Individually
```powershell
# HTTP Server (port 8000)
php artisan serve

# Queue Worker
php artisan queue:listen --tries=1

# Vite Dev Server (port 5173)
npm run dev

# Reverb WebSocket (port 6001)
php artisan reverb:start

# Print Service (port 9100)
node print-service/index.js
```

### Testing
```powershell
# Main app (PHP/Pest)
composer test
./vendor/bin/pest --filter=OrderServiceTest

# Tablet PWA (Vitest)
cd tablet-ordering-pwa
npm run test

# Relay Device (Flutter)
cd relay-device
flutter test
```

### Code Quality
```powershell
# PHP formatting
./vendor/bin/pint

# JavaScript/TypeScript formatting
npm run format

# Linting
npm run lint
```

---

## 🚀 Deployment

### Production Services (Windows)
Windows services managed via NSSM:
- `woosoo-scheduler` → `php artisan schedule:work`
- `woosoo-reverb` → `php artisan reverb:start`
- `woosoo-queue` → `php artisan queue:work`
- `woosoo-printer` → `node print-service/index.js`

### Build Commands
```powershell
# Admin Panel
npm run build
php artisan optimize

# Tablet PWA
cd tablet-ordering-pwa
npm run build

# Relay Device
cd relay-device
flutter build apk --release
flutter build windows --release
```

---

## 🔐 Environment Configuration

Key environment variables (`.env`):

```env
# Application
APP_NAME=Woosoo
APP_URL=http://127.0.0.1:8000

# Database (App)
DB_CONNECTION=mysql
DB_DATABASE=woosoo_api

# Database (POS - Read-only)
DB_POS_CONNECTION=pos
DB_POS_DATABASE=krypton_woosoo

# Reverb WebSocket
VITE_REVERB_HOST=127.0.0.1
VITE_REVERB_PORT=6001

# Tablet PWA
MAIN_API_URL=http://127.0.0.1:8000
```

---

## 📊 Current Status

**Implementation Completeness:** 80% feature-complete, 6 small tasks remaining (3-4 hours)

### ✅ Completed Features
- Order restrictions & validation
- Stability infrastructure (7/7 deliverables)
- Roles & permissions CRUD
- Branch management
- Real-time broadcasting (Reverb)
- Event replay API
- Health monitoring
- Durable print queue
- PIN authentication

### ⏳ Pending Tasks
See [IMPLEMENTATION_CHECKLIST.md](IMPLEMENTATION_CHECKLIST.md) for:
1. Token refresh timer (Tablet PWA)
2. API retry logic (Tablet PWA)
3. API retry logic (Flutter)
4. Monitoring thresholds
5. Event recording verification
6. Test suite validation

---

## 🐛 Troubleshooting

### Common Issues

**CSRF Token Errors**
- Ensure `<meta name="csrf-token">` in layout
- Check Axios configuration in `resources/js/app.ts`

**WebSocket Connection Fails**
- Verify Reverb is running: `php artisan reverb:start`
- Check `VITE_REVERB_*` env vars
- Ensure port 6001 is accessible

**POS Queries Fail**
- Verify `DB_POS_*` connection in `.env`
- Check stored procedure signatures in Krypton DB

**Tests Fail**
- Run `php artisan config:clear` before tests
- Check `phpunit.xml` environment variables

**Vite Build Errors**
- Clear cache: `rm -rf node_modules/.vite`
- Reinstall: `npm ci`

---

## 📞 Support & Resources

- **Issue Tracker:** [GitHub Issues](../../issues)
- **Changelog:** [CHANGELOG.md](CHANGELOG.md)
- **Visual Summary:** [VISUAL_SUMMARY.md](VISUAL_SUMMARY.md)
- **Stability Overview:** [README_STABILITY.md](README_STABILITY.md)

---

## 📝 License

Proprietary — Woosoo Restaurant Management System

---

**Note for AI Coding Agents:** This repository includes comprehensive AI agent instructions. Please read [.github/copilot-instructions.md](.github/copilot-instructions.md) for detailed patterns, conventions, and examples before making changes.
