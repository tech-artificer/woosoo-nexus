Purpose: Help AI coding assistants become productive in woosoo-nexus. Focus on discoverable patterns, runnable commands, and concrete examples.

## Architecture Overview

**Tech Stack**
- Backend: Laravel 12 (PHP 8.2+), Sanctum auth, Scramble API docs
- Frontend: Inertia.js + Vue 3 + TypeScript, Vite bundler, shadcn-vue components
- Database: Dual MySQL connections - `mysql` (Laravel app data) and `pos` (legacy Krypton POS system)
- Real-time: Laravel Reverb (WebSockets) + Pusher for broadcasting
- Testing: Pest (PHPUnit wrapper) with in-memory SQLite

**Process Boundaries**
- PHP process: HTTP requests, queues, scheduled tasks, domain logic
- Node process: `print-service/index.js` (Express server on port 9100) - handles print jobs independently
- Background workers: scheduler, queue, Reverb (installed as Windows services, see deployment notes below)

**Multi-Database Architecture**
- `config/database.php` defines two MySQL connections:
  - `mysql` (default): app data (users, devices, branches, etc.)
  - `pos`: legacy Krypton POS database (`krypton_woosoo`) - read-only integration
- Models in `app/Models/Krypton/*` use `protected $connection = 'pos'` to query POS data
- Actions call POS stored procedures: e.g., `Order::fromQuery('CALL create_order(...)', $params)`

## Key Files & Directories

**Configuration & Tooling**
- `composer.json` — scripts: `dev` (serve+queue+vite), `dev:ssr`, `test`
- `package.json` — scripts: `dev`, `build`, `build:ssr` (SSR support via Inertia)
- `phpunit.xml` — test env (SQLite :memory:, `QUEUE_CONNECTION=sync`)
- `vite.config.ts` — alias `@` to `resources/js`, Tailwind v4, Vue plugin
- `tsconfig.json` — paths alias `@/*` to `resources/js/*`
- `components.json` — shadcn-vue config (Tailwind v4, lucide icons)

**Domain Structure**
- `app/Actions/*` — lorisleiva/laravel-actions pattern (discrete, testable commands organized by domain: Device/, Order/, Pos/, Table/)
- `app/Services/*` — business logic services (Krypton/, Reports/, BroadcastService, etc.)
- `app/Repositories/Krypton/*` — data access for Krypton POS integration
- `app/Jobs/*` — queued background jobs (BroadcastEventJob, ProcessOrderLogs, OrderCheckUpdate)
- `app/Events/*` — broadcastable events (PrintOrder, PrintRefill, Order/*, ServiceRequest/*)
- `app/Models/Krypton/*` — POS database models (Order, Menu, Terminal, etc.) with `connection = 'pos'`

**Routes & Controllers**
- `routes/web.php` — admin web routes (Inertia pages: dashboard, orders, menus, users, devices, branches, roles)
- `routes/api.php` — device API (v1, Sanctum auth), POS integration endpoints
- `routes/channels.php` — broadcast channel authorization (device.{id}, admin.orders, admin.print, etc.)
- `app/Http/Controllers/Admin/*` — web controllers using Inertia::render()
- `app/Http/Controllers/Api/V1/*` — API controllers returning JSON

**Frontend**
- `resources/js/app.ts` — Inertia + Vue setup, Axios config (CSRF), Laravel Echo init (Reverb)
- `resources/js/pages/*` — Inertia page components (Dashboard, Orders, Menus, Users, Devices, Branches, Roles, etc.)
- `resources/js/components/ui/*` — shadcn-vue components (via `npx shadcn-vue@latest add`)
- `resources/js/composables/*` — Vue composables (useToast, useAppearance, useInitials, useReportColumns)
- `resources/js/layouts/*` — app layouts
- `resources/js/types/*` — TypeScript types


## Developer Workflows

**Setup (Windows PowerShell)**
```powershell
composer install
npm ci
cp .env.example .env
php artisan key:generate
php artisan migrate
```

**Local Development**
```powershell
# All-in-one: serve + queue + vite (recommended)
composer dev

# SSR mode (includes pail logs + Inertia SSR)
composer dev:ssr

# Individual processes
php artisan serve              # HTTP server (localhost:8000)
php artisan queue:listen --tries=1
npm run dev                    # Vite HMR (port 5173)
php artisan reverb:start       # WebSockets (port 6001)
node print-service/index.js    # Print service (port 9100)
```

**Testing**
```powershell
composer test                  # config:clear + artisan test
./vendor/bin/pest              # Direct Pest invocation
./vendor/bin/pest --filter=OrderServiceTest
```
- Feature tests in `tests/Feature/*` (uses RefreshDatabase)
- Unit tests in `tests/Unit/*`
- Test environment: SQLite :memory:, sync queue (see `phpunit.xml`)

**Frontend**
```powershell
npm run dev           # Vite dev server with HMR
npm run build         # Production build
npm run build:ssr     # Build + SSR bundle
npm run format        # Prettier
npm run lint          # ESLint
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
- **Note**: `lp` command is Linux-specific; Windows deployment uses different print handling

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

## Integration Points & External Dependencies

**Laravel Packages**
- `dedoc/scramble` — API documentation generator (auto-generates from controllers/routes)
- `spatie/laravel-permission` — roles & permissions (used in `app/Models/User.php`, `routes/web.php`)
- `tightenco/ziggy` — Laravel routes in JavaScript (available via `route()` helper in Vue)
- `laravel/reverb` — WebSocket server (alternative to Pusher)
- `laravel/pulse` — application monitoring (disabled in tests)
- `lorisleiva/laravel-actions` — action pattern library

**Frontend Libraries**
- `@inertiajs/vue3` — SPA-like experience without building API
- `@tanstack/vue-table` — data table components
- `@vueuse/core` — Vue composition utilities
- `reka-ui` — headless UI components (shadcn-vue foundation)
- `vee-validate` + `zod` — form validation (not heavily used)
- `vue-sonner` — toast notifications
- `date-fns` — date manipulation

**Database Connections**
- `mysql` (default) — Laravel app database (`woosoo_api`)
- `pos` — Krypton POS database (`krypton_woosoo`) - **read-only integration**
- Test: `sqlite` (in-memory, see `phpunit.xml`)

**Ports & Services**
- `8000` — Laravel HTTP server (`php artisan serve`)
- `5173` — Vite dev server (HMR)
- `6001` — Reverb WebSocket server
- `9100` — Print service (Node.js Express)

## Troubleshooting & Tips

**Common Issues**
- **CSRF token errors**: Ensure `<meta name="csrf-token">` in layout, Axios configured in `app.ts`
- **Echo/WebSocket fails**: Check Reverb is running, VITE_REVERB_* env vars set, port 6001 accessible
- **POS queries fail**: Verify `pos` connection in `.env` (DB_POS_*), check stored procedure signatures
- **Tests fail**: Run `php artisan config:clear` before tests, check `phpunit.xml` env vars
- **Vite build errors**: Clear cache (`rm -rf node_modules/.vite`), reinstall deps (`npm ci`)

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
```

**Performance**
- Enable opcache in production (PHP 8.2+)
- Run `npm run build` for optimized frontend bundle
- Queue heavy tasks (use `dispatch()` instead of synchronous execution)
- Use `php artisan optimize` for config/route caching (production)

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
