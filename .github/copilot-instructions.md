<!-- Repository-specific Copilot instructions for woosoo-nexus -->
# Woosoo Nexus — Integrated Restaurant POS + Admin Panel + Relay Printer App

**Purpose:** Help AI agents become productive in woosoo-nexus monorepo. Focus on discoverable patterns, runnable commands, and concrete examples across three integrated products.

---

## Quick Start

**All-in-one setup (PowerShell — recommended for full-stack work):**

```powershell
composer install; npm ci; cp .env.example .env; php artisan key:generate; php artisan migrate --seed; composer dev
```

**Individual commands if setting up in stages:**

```powershell
composer install          # PHP dependencies
npm ci                    # Frontend dependencies (exact versions)
cp .env.example .env      # Create .env from template
php artisan key:generate  # Generate APP_KEY
php artisan migrate --seed  # Create DB schema + seed data
composer dev              # Start all: HTTP server (8000), queue, Vite HMR (5173), Reverb (6001)
```

**Verify setup worked:**
- HTTP: Visit `http://127.0.0.1:8000` (should show login or admin dashboard)
- Vite HMR: Check `http://127.0.0.1:5173` responds with Vite dev page
- Queue listener: `composer dev` logs show queue processing
- Reverb: See `php artisan reverb:start` output in terminal

---

## Architecture Overview: Three Integrated Products

### 1. **Admin Panel** (Laravel + Inertia.js + Vue 3 + TypeScript)
- **What:** Web UI for restaurant staff (managers, operators) to manage orders, menus, users, devices, branches, and view reports
- **Tech:** Laravel 12 HTTP server (port 8000) + Inertia (no separate API) + Vue 3 + TypeScript + Vite HMR (port 5173)
- **Routes:** `routes/web.php` (Inertia pages)
- **Controllers:** `app/Http/Controllers/Admin/*` — thin controllers returning `Inertia::render()`
- **State:** Reactive (component-local with `useForm()` helpers), no global Vue store needed for most cases
- **Real-time updates:** Broadcast events (e.g., PrintOrder, OrderUpdate) via Laravel Echo / Reverb (port 6001)

### 2. **Tablet Ordering PWA** (`tablet-ordering-pwa/` — Nuxt 3)
- **What:** Kiosk/SPA app for customers to place orders on in-store tablets (landscape layout)
- **Tech:** Nuxt 3 (SSR disabled), Pinia state, Axios API client, Tailwind, PWA support
- **Runs:** `npm run dev` from `tablet-ordering-pwa/` folder (expands to `nuxi dev --host 0.0.0.0`)
- **API calls:** All via `useApi()` composable (injects device auth token)
- **State:** Pinia stores with persist plugin (e.g., `menu-store`, `device`)
- **Offline:** PWA service worker caches menus and order history; workbox rules in `nuxt.config.ts`

### 3. **Relay Printer App** (`relay-device/` — Flutter)
- **What:** Native mobile/tablet app that listens for print events (WebSocket + fallback polling) and prints to Bluetooth thermal printers
- **Tech:** Flutter 3.9.2+, Dart, Riverpod state management, Sembast local DB
- **Platforms:** Android, iOS, Windows, Linux, web (see `pubspec.yaml`)
- **Key features:** WebSocket listener, ESC/POS printer control, durable offline queue, device registration
- **Run:** `flutter pub get; flutter run -d <device>` from `relay-device/`

**Database Architecture:**
- `mysql` connection (default): App data (users, devices, menus, orders, branches, roles) — Laravel migrations in `database/migrations/*`
- `pos` connection: Read-only Krypton legacy POS system (`krypton_woosoo` DB) — Models in `app/Models/Krypton/*` with `protected $connection = 'pos'`
- Test: SQLite `:memory:` (configured in `phpunit.xml`)

## POS Transaction Safety & Order Contracts (CRITICAL)

**⚠️ POS-First Principle: Krypton database is the authoritative source of truth.** All writes to `pos` (krypton_woosoo) are permanent and NOT rolled back by local transaction failures. Order creation and refills must follow this contract strictly.

**Order Creation Flow** ([app/Services/Krypton/OrderService.php](app/Services/Krypton/OrderService.php#L79), [app/Http/Controllers/Api/V1/DeviceOrderApiController.php](app/Http/Controllers/Api/V1/DeviceOrderApiController.php))
1. Tablet device calls `/api/v1/device-orders` → `OrderService::processOrder()`
2. Inside `DB::transaction` (default/app connection):
   - `CreateOrder::run()` → calls POS `create_order()` stored proc on `pos` connection (NOT rolled back)
   - `CreateTableOrder::run()` → calls POS `create_table_order()` stored proc (NOT rolled back)
   - `CreateOrderCheck::run()` → calls POS `create_order_check()` stored proc (NOT rolled back)
   - `CreateOrderedMenu::run()` → calls POS `create_ordered_menu()` stored proc per item (NOT rolled back)
   - Inserts local `device_order` + `device_order_items` rows (rolls back if error)
3. On local success: `DB::afterCommit()` triggers print event + broadcasts to printers/admin
4. **If local fails after POS succeeds:** POS data persists, local rolls back. Return error; POS is source of truth.

**Refill Flow** ([app/Http/Controllers/Api/V1/OrderApiController.php#L154](app/Http/Controllers/Api/V1/OrderApiController.php#L154), [app/Actions/Order/CreateOrderedMenu.php](app/Actions/Order/CreateOrderedMenu.php#L17))
- Append-only meats/sides items to an existing order
- **Validation in [RefillOrderRequest](app/Http/Requests/RefillOrderRequest.php):** confirms items are refillable (category check against Krypton menus)
- **Execution:**
  1. Per-item: `CreateOrderedMenu::run()` → POS `create_ordered_menu()` stored proc (NOT rolled back)
  2. Inside local transaction: insert `device_order_items` rows with up to 3 retries on transient failure
  3. On local success: `DB::afterCommit()` triggers print event + broadcast
  4. **If local fails after all 3 retries:** POS ordered_menus exist, local inserts fail; return error (POS is source of truth)

**Never:**
- Reorder POS stored proc calls or change connection from `pos` to `mysql`
- Add `DB::transaction` wrappers around individual POS calls (breaks atomicity semantics)
- Attempt to roll back POS writes on local failure
- Alter refill validation rules without explicit sign-off from ops/product

**Do:**
- Keep POS calls in current order: order → table_order → order_check → ordered_menus
- Wrap only local `device_order_items` inserts in a default transaction
- Use `DB::afterCommit()` for print/broadcast so notifications fire only after local persistence
- Log refill attempts (POS success + local success/failure) for drift detection if extended later

**Process Boundaries:**
- PHP process: HTTP requests, domain logic, queues, scheduled tasks, WebSocket server (Reverb)
- Node process: `print-service/index.js` (Express, port 9100) — independent print job handler
- Flutter process: Relay device — autonomous listener/printer, syncs with backend via API + WebSocket

**Real-time Communication:**
- Reverb (port 6001): WebSocket server for broadcasting events (admin, device channels)
- Channels: `device.{deviceId}`, `admin.orders`, `admin.print`, `admin.service-requests`, etc.
- Frontend listeners: Laravel Echo in Vue (`resources/js/app.ts`) and Nuxt PWA (`tablet-ordering-pwa/plugins/echo.client.ts`)
- Mobile: Relay device listens via Dart HTTP client (WebSocket library)

---

## Key Files & Directories

**Configuration & Tooling**
- `composer.json` — scripts: `dev` (serve+queue+vite+reverb), `dev:ssr`, `test`
- `package.json` — scripts: `dev` (Vite HMR), `build`, `build:ssr`, `format`, `lint`
- `phpunit.xml` — test env (SQLite :memory:, `QUEUE_CONNECTION=sync`)
- `vite.config.ts` — alias `@` to `resources/js`, Tailwind v4, Vue plugin
- `tsconfig.json` — paths alias `@/*` to `resources/js/*`
- `components.json` — shadcn-vue config (Tailwind v4, lucide icons)
- `.env.example` → `.env` — must be set for local dev (DB_*, REVERB_*, VITE_REVERB_*, etc.)

**Domain Structure (Admin/Backend)**
- `app/Actions/*` — lorisleiva/laravel-actions pattern (discrete, testable commands: Device/, Order/, Pos/, Table/)
- `app/Services/*` — business logic (Dashboard, Broadcast, Krypton/, Report, etc.)
- `app/Repositories/Krypton/*` — data access for POS integration
- `app/Jobs/*` — queued background jobs (BroadcastEventJob, ProcessOrderLogs, OrderCheckUpdate)
- `app/Events/*` — broadcastable events (PrintOrder, PrintRefill, Order/*, ServiceRequest/*)
- `app/Models/Krypton/*` — POS database models (Order, Menu, Terminal, etc.) with `connection = 'pos'`

**Routes & Controllers (Admin)**
- `routes/web.php` — admin web routes (Inertia pages: Dashboard, Orders, Menus, Users, Devices, Branches, Roles)
- `routes/api.php` — device API (v1, Sanctum auth), POS integration endpoints
- `routes/channels.php` — broadcast channel authorization (device.{id}, admin.orders, admin.print, etc.)
- `app/Http/Controllers/Admin/*` — web controllers using `Inertia::render()`
- `app/Http/Controllers/Api/V1/*` — API controllers returning JSON (device auth, order updates, confirmations)

**Frontend (Admin Panel)**
- `resources/js/app.ts` — Inertia + Vue setup, Axios config (CSRF), Laravel Echo init (Reverb)
- `resources/js/pages/*` — Inertia page components (Dashboard, Orders, Menus, Users, Devices, Branches, Roles)
- `resources/js/components/ui/*` — shadcn-vue components (Button, Dialog, Table, etc.)
- `resources/js/composables/*` — Vue composables (useToast, useAppearance, useInitials, useReportColumns)
- `resources/js/layouts/*` — app layouts (default, admin navigation)

**Tablet Ordering PWA**
- `tablet-ordering-pwa/nuxt.config.ts` — Nuxt 3 config, PWA settings, runtime env vars
- `tablet-ordering-pwa/stores/*.ts` — Pinia stores (menu, device, cart, etc.)
- `tablet-ordering-pwa/plugins/api.client.ts` — Axios setup with device auth header
- `tablet-ordering-pwa/plugins/echo.client.ts` — Laravel Echo for real-time order updates
- `tablet-ordering-pwa/composables/useApi.ts` — API wrapper composable
- `tablet-ordering-pwa/pages/*` — Nuxt pages (kiosk mode, landing, menu browsing)
- `tablet-ordering-pwa/public/manifest.json` — PWA manifest for offline support

**Relay Printer App (Flutter)**
- `relay-device/pubspec.yaml` — dependencies (flutter_riverpod, blue_thermal_printer, web_socket_channel, sembast)
- `relay-device/lib/main.dart` — app entry, Riverpod setup, theme
- `relay-device/lib/providers/*.dart` — Riverpod state providers (app_state, printer_connection, print_queue, device_config)
- `relay-device/lib/services/*.dart` — business logic (WebSocket listener, printer control, device API client, queue persistence)
- `relay-device/lib/models/*.dart` — data structures (PrintJob, DeviceConfig, ConnectionState)
- `relay-device/lib/screens/*` — UI pages (home, settings, connection status)
- `relay-device/android/`, `ios/`, `windows/`, `linux/` — platform-specific configurations

**Testing**
- `tests/Feature/*` — Feature tests using Pest (integration-like, RefreshDatabase)
- `tests/Unit/*` — Unit tests (services, repositories)
- `database/factories/*` — test data factories
- `database/seeders/*` — seed scripts for seeding test/demo data

**Print Service (Node.js)**
- `print-service/index.js` — Express server (port 9100) that receives print jobs and executes OS print commands
- Receives POST `/print` requests with ESC/POS data
- Configurable for Linux `lp` command or Windows print handling


## Developer Workflows

**Setup (Windows PowerShell)**
```powershell
# Main app (Laravel + Vue)
cd c:\laragon\www\woosoo-nexus
composer install
npm ci
cp .env.example .env
php artisan key:generate
php artisan migrate --seed

# OR—one-liner:
composer install; npm ci; cp .env.example .env; php artisan key:generate; php artisan migrate --seed

# Start all services
composer dev

# Tablet PWA
cd tablet-ordering-pwa
npm install
npm run dev

# Relay device (Flutter)
cd relay-device
flutter pub get
flutter run -d <device>  # e.g., -d Windows, -d android-physical
```

**Recommended .env variables (examples)**
```env
# App
APP_NAME=Woosoo
APP_ENV=local
APP_KEY=
APP_URL=http://127.0.0.1:8000

# MySQL (app)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=woosoo_api
DB_USERNAME=root
DB_PASSWORD=

# Krypton POS (read-only)
DB_POS_CONNECTION=pos
DB_POS_HOST=127.0.0.1
DB_POS_PORT=3306
DB_POS_DATABASE=krypton_woosoo
DB_POS_USERNAME=pos_user
DB_POS_PASSWORD=pos_pass

# Reverb / WebSockets
VITE_REVERB_HOST=127.0.0.1
VITE_REVERB_PORT=6001

# Tablet PWA
MAIN_API_URL=http://127.0.0.1:8000
NUXT_PUBLIC_ECHO_PUSHER_KEY=local
```

**Local Development (Main App)**
```powershell
# All-in-one: serve + queue + vite + reverb
composer dev

# Individual processes
php artisan serve              # HTTP server (localhost:8000)
php artisan queue:listen --tries=1
npm run dev                    # Vite HMR (port 5173)
php artisan reverb:start       # WebSockets (port 6001)
node print-service/index.js    # Print service (port 9100, optional for local)
```

**Tablet PWA Development**
```powershell
cd tablet-ordering-pwa
npm run dev              # Vite dev server (port 3000 or see package.json)
npm run build            # Production build
npm run preview          # Preview built version
```

**Relay Device Development**
```powershell
cd relay-device

# Windows Desktop
flutter run -d windows

# Android Physical Device (via USB)
flutter run -d <device-id>

# Android Emulator
flutter emulators --launch <emulator-id>
flutter run

# Watch mode (auto-reload on changes)
flutter run --debug
```

**Canonical ports & scripts**

- **Laravel HTTP server (local):** `8000` (`php artisan serve`)
- **Vite dev server (HMR):** `5173` (check `package.json` for override)
- **Tablet PWA dev:** `3000` or `5173` (check `tablet-ordering-pwa/package.json`)
- **Reverb (WebSockets):** `6001` (default)
- **Print service:** `9100` (Node.js Express)

**Testing**
```powershell
# Main app (PHP/Pest)
composer test              # config:clear + artisan test
./vendor/bin/pest          # Direct Pest invocation
./vendor/bin/pest --filter=OrderServiceTest

# Relay device (Flutter)
cd relay-device
flutter test               # Runs unit tests under test/
flutter test --coverage    # Generate coverage report
```

**Production Deployment (Windows)**
- Windows services expected (installed via NSSM, see ops documentation):
  - `woosoo-scheduler` → `php artisan schedule:work`
  - `woosoo-reverb` → `php artisan reverb:start`
  - `woosoo-queue` → `php artisan queue:work`
  - `woosoo-printer` → `node print-service/index.js`

## Project Conventions & Patterns

**Actions Pattern** (lorisleiva/laravel-actions)
- Discrete, single-purpose server operations in `app/Actions/*`
- Example: `CreateOrder::handle(array $attr)` — calls POS stored procedure `create_order()`
- Not widely used in controllers (mostly manual controller methods), but present in domain logic
- Recommended for new features requiring testable, reusable logic

**Service/Repository Pattern**
- Business logic in `app/Services/*` (e.g., `DashboardService`, `BroadcastService`, `Krypton/*`)
- Data access in `app/Repositories/Krypton/*`
- Controllers stay thin, delegate to services

**Real-time Broadcasting**
- Events in `app/Events/*` implement `ShouldBroadcast`
- Channels defined in `routes/channels.php`:
  - `device.{deviceId}` — device-specific updates
  - `orders.{orderId}` — order updates
  - `admin.orders` — admin order notifications
  - `admin.print` — print job events (PrintOrder, PrintRefill)
  - `admin.service-requests` — service request notifications
- Frontend: Laravel Echo configured in `resources/js/app.ts` (Reverb/Pusher)
- Listen pattern: `window.Echo.channel('admin.print').listen('PrintOrder', (e) => {...})`

**Printing System**
- Server-side: `mike42/escpos-php` library (ESC/POS thermal printers)
- Service: `print-service/index.js` (Express) — receives POST /print, writes to `/tmp/printjob.txt`, executes `lp` command (Linux printer utility)
- Events: `PrintOrder`, `PrintRefill` broadcast on `admin.print` channel
- **Note**: `lp` command is Linux-specific; Windows deployment uses different print handling.
- **Windows development tip**: On Windows run the `print-service` under WSL with CUPS installed or stub printing locally. A simple local stub is to set an env var `PRINT_CMD=echo` and adjust `print-service/index.js` to use `process.env.PRINT_CMD || 'lp'` for the print command, or configure the service to write jobs to a folder for inspection. See `docs/printer_manual.md` for deployment guidance.

**Database Patterns**
- Migrations: `database/migrations/*` (Laravel app schema only)
- Seeders: `database/seeders/*`
- Krypton POS integration: stored procedure calls via `Model::fromQuery('CALL proc(?)', [$params])`
- Example: `Order::fromQuery('CALL create_order(...)', $params)->first()`

**Frontend Patterns**
- Inertia pages: `resources/js/pages/*` (organized by feature: dashboard/, orders/, menus/, users/, etc.)
- Shared components: `resources/js/components/*` (organized by feature + ui/)
- UI library: shadcn-vue (Tailwind v4 + Reka UI + lucide icons)
- Add components: `npx shadcn-vue@latest add <component>`
- Composables: `resources/js/composables/*` (reusable Vue logic)
- TypeScript: path alias `@/*` → `resources/js/*` (vite.config.ts + tsconfig.json)
- Forms: typically use Inertia form helpers, manual validation (some vee-validate usage)

**Testing Patterns**
- Pest syntax: `it('does something', function() { ... })`
- Feature tests use `RefreshDatabase` trait (configured in `tests/Pest.php`)
- Test environment auto-configured in `phpunit.xml` (SQLite, sync queue, no Pulse/Telescope)
- Example: `tests/Feature/DeviceTableTest.php`, `tests/Unit/OrderServiceTest.php`

**Flutter/Dart Patterns** (Relay Device)
- State management: Riverpod providers in `lib/providers/*.dart`
- Service layer: Business logic in `lib/services/*.dart` (WebSocket listener, printer control, device API)
- Models: Data structures in `lib/models/*.dart`
- Persistence: Sembast for durable queue, SharedPreferences for simple config
- Testing: Unit tests in `test/` (run with `flutter test`)
- Hot reload: Supported in debug mode for rapid iteration
- Platform channels: Android/iOS permissions handled via `permission_handler` package

---

## Common Tasks & Examples

**Adding a New Inertia Page**
1. Create page component: `resources/js/pages/MyFeature/Index.vue`
2. Add route: `routes/web.php` → `Route::get('/my-feature', [MyController::class, 'index'])`
3. Controller: `return Inertia::render('MyFeature/Index', ['data' => $data])`

**Adding a New API Endpoint**
1. Route: `routes/api.php` → `Route::get('/api/v1/my-endpoint', [MyApiController::class, 'index'])`
2. Controller: `app/Http/Controllers/Api/V1/MyApiController.php`
3. Response: `return response()->json(['success' => true, 'data' => $data])`
4. Auth: use `auth:sanctum` middleware for device/user auth

**Adding a New Broadcast Event**
1. Create event: `app/Events/MyEvent.php` implements `ShouldBroadcast`
2. Define channel: `routes/channels.php` → `Broadcast::channel('my-channel', ...)`
3. Dispatch: `MyEvent::dispatch($data)` in controller/job
4. Frontend: `window.Echo.channel('my-channel').listen('MyEvent', (e) => {...})`

**Querying Krypton POS Data**
```php
// Model setup (app/Models/Krypton/MyModel.php)
protected $connection = 'pos';
protected $table = 'my_table';
public $timestamps = false;

// Query
use App\Models\Krypton\Order;
$order = Order::find($id);

// Stored procedure
$result = Order::fromQuery('CALL my_procedure(?, ?)', [$param1, $param2])->first();
```

**Adding shadcn-vue Component**
```powershell
npx shadcn-vue@latest add button
npx shadcn-vue@latest add dialog
```
- Components installed to `resources/js/components/ui/*`
- Import: `import { Button } from '@/components/ui/button'`

**Adding a Riverpod Provider (Flutter)**
```dart
// lib/providers/my_provider.dart
import 'package:flutter_riverpod/flutter_riverpod.dart';

final myDataProvider = FutureProvider<List<String>>((ref) async {
  // Fetch data from API or local DB
  return ['item1', 'item2'];
});

// Usage in widget
Consumer(
  builder: (context, ref, child) {
    final data = ref.watch(myDataProvider);
    return data.when(
      data: (items) => ListView(children: items.map((e) => Text(e)).toList()),
      loading: () => const CircularProgressIndicator(),
      error: (err, stack) => Text('Error: $err'),
    );
  },
)
```

**Adding a Nuxt PWA API Call (Tablet)**
```ts
// Use the useApi composable
const api = useApi()
const response = await api.get('/api/v1/menus')

// Or configure in stores
// tablet-ordering-pwa/stores/menu.ts
export const useMenuStore = defineStore('menu', () => {
  const items = ref([])
  const loadMenus = async () => {
    const api = useApi()
    const { data } = await api.get('/api/v1/menus')
    items.value = data
  }
  return { items, loadMenus }
})
```

---

## Integration Points & External Dependencies

**Laravel Packages**
- `dedoc/scramble` — API documentation generator (auto-generates from controllers/routes)
- `spatie/laravel-permission` — roles & permissions (used in `app/Models/User.php`, `routes/web.php`)
- `tightenco/ziggy` — Laravel routes in JavaScript (available via `route()` helper in Vue)
- `laravel/reverb` — WebSocket server (alternative to Pusher)
- `laravel/pulse` — application monitoring (disabled in tests)
- `lorisleiva/laravel-actions` — action pattern library

**Frontend Libraries (Admin Panel)**
- `@inertiajs/vue3` — SPA-like experience without building API
- `@tanstack/vue-table` — data table components
- `@vueuse/core` — Vue composition utilities
- `reka-ui` — headless UI components (shadcn-vue foundation)
- `vee-validate` + `zod` — form validation (not heavily used)
- `vue-sonner` — toast notifications
- `date-fns` — date manipulation

**Nuxt PWA Libraries (Tablet)**
- `@nuxt/image` — optimized image loading
- `@vite-pwa/nuxt` — PWA plugin (service worker, manifest)
- `pinia` & `@pinia/nuxt` — state management with persist plugin
- `axios` — HTTP client
- `laravel-echo` — WebSocket client for real-time updates

**Flutter/Dart Packages (Relay Device)**
- `flutter_riverpod` — state management & dependency injection
- `blue_thermal_printer` — Bluetooth thermal printer control (ESC/POS)
- `web_socket_channel` — WebSocket client
- `esc_pos_utils` — ESC/POS formatting utilities
- `sembast` — embedded NoSQL DB for durable queue persistence
- `shared_preferences` — simple key-value storage
- `permission_handler` — Bluetooth & location permissions (Android/iOS)
- `wakelock_plus` — prevent device sleep during printing

**Database Connections**
- `mysql` (default) — Laravel app database (`woosoo_api`)
- `pos` — Krypton POS database (`krypton_woosoo`) - **read-only integration**
- Test: `sqlite` (in-memory, see `phpunit.xml`)

**Ports & Services**
- `8000` — Laravel HTTP server (`php artisan serve`)
- `5173` — Vite dev server (HMR, main app)
- `3000`–`5173` — Tablet PWA dev (check script)
- `6001` — Reverb WebSocket server
- `9100` — Print service (Node.js Express)

## Troubleshooting & Tips

**Common Issues**
- **CSRF token errors**: Ensure `<meta name="csrf-token">` in layout, Axios configured in `app.ts`
- **Echo/WebSocket fails**: Check Reverb is running, VITE_REVERB_* env vars set, port 6001 accessible
- **POS queries fail**: Verify `pos` connection in `.env` (DB_POS_*), check stored procedure signatures
- **Tests fail**: Run `php artisan config:clear` before tests, check `phpunit.xml` env vars
- **Vite build errors**: Clear cache (`rm -rf node_modules/.vite`), reinstall deps (`npm ci`)
  If you see errors related to `esbuild` native binaries (common on CI runners), run `npm run rebuild:esbuild --if-present` during CI `postinstall` or manually locally to rebuild the native binary.
- **Flutter build issues**: Run `flutter clean` and `flutter pub get` before rebuilding; check platform-specific requirements (Android SDK, Xcode, etc.)
- **Bluetooth permissions (Android)**: Ensure manifest permissions declared; runtime requests shown via `permission_handler` package
- **WebSocket connection in relay device**: Check device network connectivity; fallback polling should activate if WebSocket fails

**Debugging**
```powershell
# Logs
php artisan pail                        # Real-time log viewer (Laravel 11+)
tail -f storage/logs/laravel.log       # Traditional logs

# Queue jobs
php artisan queue:work --verbose       # Watch queue processing
php artisan queue:failed               # List failed jobs
php artisan queue:retry all            # Retry failed jobs

# Database
php artisan tinker                     # REPL
php artisan db:show                    # Database info
php artisan db:table users             # Inspect table

# Cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Flutter
flutter run -d <device> --verbose      # Verbose output
flutter logs                           # Stream device logs
flutter analyze                        # Code analysis (Dart)
```

**Performance**
- Enable opcache in production (PHP 8.2+)
- Run `npm run build` for optimized frontend bundle
- Queue heavy tasks (use `dispatch()` instead of synchronous execution)
- Use `php artisan optimize` for config/route caching (production)
- Flutter: Use release mode for performance testing (`flutter run --release`)

## Code Quality Guidelines

**Laravel Best Practices**
- Thin controllers, fat services/actions
- Use resource classes for API responses (e.g., `BranchResource`)
- Type-hint dependencies (constructor injection)
- Use form requests for validation (e.g., `StoreUserRequest`)
- Prefer Eloquent over query builder where possible

**Vue/TypeScript Best Practices**
- Use composition API (`<script setup lang="ts">`)
- Define prop types explicitly
- Extract reusable logic to composables
- Use Inertia `useForm` for forms with validation
- Prefer `ref()` over `reactive()` for primitives

**Testing**
- Write feature tests for user-facing workflows
- Write unit tests for complex business logic (services, repositories)
- Use factories for test data (`database/factories/*`)
- Mock external services (POS stored procedures, APIs)
- Keep tests fast (in-memory DB, sync queue)

**Git Workflow**
- Branch: `main` (production), `staging` (pre-production), feature branches
- Current branch: `staging` (from repo context)
- Commit messages: conventional commits (feat:, fix:, docs:, etc.)

## Reference Files

**Examples to Study**
- Action: `app/Actions/Order/CreateOrder.php` (stored procedure call pattern)
- Service: `app/Services/DashboardService.php` (business logic)
- Repository: `app/Repositories/Krypton/*` (data access)
- Event: `app/Events/PrintOrder.php` (broadcast pattern)
- Controller (web): `app/Http/Controllers/Admin/BranchController.php` (Inertia CRUD)
- Controller (API): `app/Http/Controllers/Api/V1/DeviceApiController.php` (JSON API)
- Page: `resources/js/pages/Branches/IndexBranches.vue` (Inertia page)
- Composable: `resources/js/composables/useToast.ts` (reusable logic)
- Test: `tests/Feature/DeviceTableTest.php` (Pest feature test)

**Documentation**
- `docs/API_MAP.md` — API endpoint reference (request/response shapes)
- `docs/BRANCH_CRUD_IMPLEMENTATION.md` — example CRUD implementation
- `docs/ROLES_IMPLEMENTATION_COMPLETE.md` — roles/permissions guide
- `routes/web.php`, `routes/api.php` — route definitions
- `routes/channels.php` — broadcast channel authorization

---

**Need more detail?** Ask about:
- Specific event names/payloads
- Action implementation examples
- Windows service setup/deployment
- POS integration patterns
- Frontend component structure
- Testing strategies
