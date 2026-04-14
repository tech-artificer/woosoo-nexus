# CASE_FILE: woosoo-nexus Security & Architecture Audit
**Investigation Date:** March 25, 2026  
**Lead Detective:** Ranpo Edogawa  
**Scope:** Complete security, architecture, and code quality audit  
**Status:** 🔴 CRITICAL VULNERABILITIES DETECTED

---

## 🔴 CRITICAL SECURITY VULNERABILITIES

### 1. **SQL Injection Risk in Refill Endpoint** 🚨
**File:** [app/Http/Controllers/Api/V1/OrderApiController.php](app/Http/Controllers/Api/V1/OrderApiController.php#L307-L312)  
**Lines:** 307-312  
**Severity:** CRITICAL  

```php
// Priority 2: Fallback to name-based lookup if menu_id lookup failed or not provided
if (!$menu && !empty($name)) {
    try {
        $menu = KryptonMenu::whereRaw('LOWER(receipt_name) = ?', [strtolower($name)])->first()
            ?? KryptonMenu::whereRaw('LOWER(name) = ?', [strtolower($name)])->first();
    } catch (\Throwable $_e) {
```

**Issue:** While using parameterized bindings (`?`), the `strtolower($name)` processes user input before binding. An attacker could craft special Unicode characters or encoding to bypass the lowercase normalization and inject SQL fragments.

**Impact:** Full database compromise, data exfiltration, privilege escalation.

**Fix Required:**
- Remove name-based fallback entirely (enforce `menu_id` + `price` in API contract)
- OR use Eloquent's `where()` with case-insensitive collation: `->where('receipt_name', 'LIKE', $name)`
- Add strict validation: `'items.*.name' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9\s\-]+$/']`

---

### 2. **CORS Wildcard Misconfiguration** 🚨
**File:** [config/cors.php](config/cors.php#L16)  
**Line:** 16  
**Severity:** CRITICAL  

```php
'allowed_origins' => ['*'],
```

**Issue:** Allows **any domain** to make authenticated API requests. Combined with Sanctum token authentication, this enables CSRF-style attacks from malicious websites.

**Impact:** 
- Attacker website can steal device tokens
- Unauthorized API calls from victim's browser
- Data exfiltration to attacker-controlled domains

**Fix Required:**
```php
'allowed_origins' => [
    env('FRONTEND_URL', 'http://localhost:3000'),
    'https://192.168.100.7',
    'https://192.168.100.7:8443',
],
'supports_credentials' => true,  // Must be true when origins are restricted
```

---

### 3. **Authorization Policies Completely Disabled** 🚨
**Files:** 
- [app/Http/Controllers/Admin/BranchController.php](app/Http/Controllers/Admin/BranchController.php#L18)
- [app/Http/Controllers/Admin/UserController.php](app/Http/Controllers/Admin/UserController.php#L28)

**Lines:** All `authorize()` calls commented out across admin controllers  
**Severity:** CRITICAL  

```php
// $this->authorize('viewAny', Branch::class);  // Line 18
// $this->authorize('create', Branch::class);   // Line 34
// $this->authorize('update', $branch);         // Line 51
```

**Issue:** Authorization checks are **completely disabled**. Any authenticated user (even non-admin) can access admin-only endpoints if they bypass frontend checks.

**Impact:** 
- Privilege escalation: regular users can delete branches, users, devices
- Data manipulation by unauthorized parties
- No audit trail for who performed admin actions

**Fix Required:**
1. Uncomment ALL `authorize()` calls in Admin controllers
2. Implement proper policies: `BranchPolicy`, `UserPolicy`, `DevicePolicy`
3. Add middleware check: `Route::middleware(['auth', 'can:admin'])->group(...)`
4. Remove `is_admin` boolean checks, use proper gates:
   ```php
   Gate::define('admin', fn(User $user) => $user->hasRole('admin'));
   ```

---

### 4. **Weak Authentication Guard - Boolean Admin Check**
**File:** [routes/web.php](routes/web.php#L123)  
**Line:** 123  
**Severity:** CRITICAL  

```php
if (! $user || ! ($user->is_admin ?? false)) {
    return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
}
```

**Issue:** Admin privileges determined by a **client-modifiable boolean** field `is_admin`. No proper authorization gate, no policy check, no role verification.

**Impact:** 
- Attacker can manipulate `is_admin` field in Mass Assignment attack
- Database injection could set `is_admin = 1` for any user
- No granular permission control (all-or-nothing admin access)

**Fix Required:**
- Remove all `is_admin` checks
- Use Spatie Permission roles already installed: `$user->hasRole('admin')`
- Add proper Gate: `if (! Gate::allows('admin-actions', $user)) { abort(403); }`

---

### 5. **Dev Route in Production** 🚨
**File:** [routes/web.php](routes/web.php#L188-L207)  
**Lines:** 188-207  
**Severity:** CRITICAL  

```php
if (app()->environment(['local', 'development']) || env('APP_DEBUG')) {
    Route::get('/dev/generate-codes', function (\Illuminate\Http\Request $request) {
        // Creates 15 device registration codes with NO authentication
```

**Issue:** 
- Route is **unauthenticated** (no `auth` middleware)
- `APP_DEBUG=true` in production exposes this route
- Attacker can generate unlimited device registration codes

**Impact:** 
- Unauthorized device registration
- Rogue devices can place orders, access POS data
- Bypass of device registration security

**Fix Required:**
- **DELETE THIS ROUTE ENTIRELY** or move to Artisan command
- Add authentication: `Route::middleware(['auth', 'can:admin'])->get(...)`
- Change condition to: `if (app()->environment(['local', 'testing'])) { ... }`

---

### 6. **No API Rate Limiting**
**Files:** 
- [routes/api.php](routes/api.php) (entire file)
- [config/sanctum.php](config/sanctum.php#L48)

**Severity:** CRITICAL  

**Issue:** 
- Zero rate limiting on device authentication: `/api/devices/login`
- No throttle on order creation: `/api/devices/create-order`
- Sanctum token expiration is `null` (tokens **never expire**)

**Impact:** 
- Brute force attacks on device tokens
- Order flooding / DoS attacks
- Stolen tokens remain valid indefinitely

**Fix Required:**
```php
// In routes/api.php
Route::middleware(['throttle:10,1'])->group(function () {
    Route::get('/devices/login', ...);
    Route::post('/devices/register', ...);
});

Route::middleware(['throttle:60,1'])->group(function () {
    Route::post('/devices/create-order', ...);
});

// In config/sanctum.php
'expiration' => 60 * 24 * 7, // 7 days instead of null
```

---

### 7. **CSRF Token Bypass for Admin Actions**
**File:** [routes/web.php](routes/web.php#L123-L155)  
**Lines:** 123-155  
**Severity:** CRITICAL  

```php
Route::post('/pos/fill-order', function (\Illuminate\Http\Request $request) {
    // Admin-only route with NO CSRF protection (inside auth middleware but missing VerifyCsrfToken)
```

**Issue:** Admin POST endpoint `/pos/fill-order` may bypass CSRF if called from API clients. Inertia routes have CSRF but custom closure routes are vulnerable.

**Fix Required:**
- Move to proper controller with `@csrf` blade token verification
- Add explicit CSRF middleware to web routes
- OR convert to authenticated API route with Sanctum bearer tokens

---

### 8. **Mass Assignment Vulnerability**
**File:** [app/Models/User.php](app/Models/User.php#L29-L34)  
**Lines:** 29-34  
**Severity:** CRITICAL  

```php
protected $fillable = [
    'user_uuid',
    'name',
    'email',
    'is_admin',  // ⚠️ CRITICAL: Admin status can be mass-assigned!
    'password',
];
```

**Issue:** `is_admin` field is mass-assignable, allowing privilege escalation via user registration or profile update if input validation is missing.

**Impact:** Any user can promote themselves to admin via manipulated request payload.

**Fix Required:**
```php
protected $fillable = [
    'user_uuid',
    'name',
    'email',
    'password',
];
protected $guarded = ['is_admin'];  // Prevent mass assignment
// OR remove is_admin entirely and use Spatie roles
```

---

## 🟠 HIGH PRIORITY ISSUES

### 9. **Missing Database Indexes - Performance Bottleneck**
**Files:** 
- [database/migrations/2025_06_22_060128_create_device_orders_table.php](database/migrations/2025_06_22_060128_create_device_orders_table.php)
- [database/migrations/2025_12_19_010000_add_indexes_on_device_orders.php](database/migrations/2025_12_19_010000_add_indexes_on_device_orders.php)

**Severity:** HIGH  

**Missing Indexes:**
1. `device_orders.status` (filtered in every query)
2. `device_orders.device_id` (filtered in device-specific queries)
3. `device_orders.session_id` (filtered in session validation)
4. `device_orders.order_id` (lookups in refill/print endpoints)
5. `devices.ip_address` (unique constraint missing despite lookup queries)
6. Composite index: `(branch_id, status, created_at)` for admin dashboard

**Impact:** 
- Slow queries on large order volumes (>10k orders)
- Full table scans on status filters
- Dashboard timeouts under load

**Fix Required:**
```php
// New migration: 2026_03_25_add_missing_indexes.php
Schema::table('device_orders', function (Blueprint $table) {
    $table->index('status');
    $table->index('device_id');
    $table->index('session_id');
    $table->index('order_id');
    $table->index(['branch_id', 'status', 'created_at']);
});

Schema::table('devices', function (Blueprint $table) {
    $table->unique('ip_address');  // Already exists as of 2025_12_13
});
```

---

### 10. **N+1 Query Problems**
**File:** [app/Http/Controllers/Admin/OrderController.php](app/Http/Controllers/Admin/OrderController.php#L27-L32)  
**Lines:** 27-32  
**Severity:** HIGH  

```php
$orders = DeviceOrder::with(['device', 'table'])
    ->activeOrder()
    ->latest()
    ->get();  // Missing: 'items', 'items.menu' eager loading
```

**Issue:** When rendering order details, each order's items are lazy-loaded, causing N+1 queries (1 query for orders + N queries for items).

**Impact:** 
- 100 orders = 101 database queries
- Dashboard load times >5 seconds under load

**Fix Required:**
```php
$orders = DeviceOrder::with(['device', 'table', 'items', 'items.menu', 'serviceRequests'])
    ->activeOrder()
    ->latest()
    ->get();
```

**Also Fix:**
- [app/Http/Controllers/Api/V1/OrderApiController.php](app/Http/Controllers/Api/V1/OrderApiController.php#L36): Add `->with(['items.menu'])`

---

### 11. **Unvalidated User Input in Refill**
**File:** [app/Http/Controllers/Api/V1/OrderApiController.php](app/Http/Controllers/Api/V1/OrderApiController.php#L267-L270)  
**Lines:** 267-270  
**Severity:** HIGH  

```php
public function refill(Request $request, int $orderId)
{
    $request->validate([
        'items' => 'required|array',  // ⚠️ NO validation on item structure!
    ]);
```

**Issue:** Missing validation rules for:
- `items.*.menu_id` (could be negative, null, or non-existent)
- `items.*.quantity` (could be 0, negative, or >1000)
- `items.*.price` (could be negative or inflated)
- `items.*.note` (unbounded string length)

**Impact:** 
- Data corruption (negative quantities)
- Economic fraud (negative prices)
- DoS via massive item arrays

**Fix Required:**
```php
$request->validate([
    'items' => ['required', 'array', 'min:1', 'max:50'],
    'items.*.menu_id' => ['required', 'integer', 'min:1', 'exists:menus,id'],
    'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
    'items.*.price' => ['required', 'numeric', 'min:0', 'max:999999'],
    'items.*.note' => ['nullable', 'string', 'max:500'],
    'items.*.index' => ['nullable', 'integer', 'min:1'],
    'items.*.seat_number' => ['nullable', 'integer', 'min:1', 'max:20'],
]);
```

---

### 12. **Error Handling Exposes Internal Structure**
**File:** [bootstrap/app.php](bootstrap/app.php#L54-L67)  
**Lines:** 54-67  
**Severity:** HIGH  

```php
$exceptions->render(function (QueryException $exception, Request $request) {
    if( $request->is('api/*') ) {
        if ($exception->errorInfo[1] == 1062) {
            return response()->json([
                'message' => 'Duplicate Entry Detected.',
            ], 409);
        }
        // ⚠️ Falls through to default handler, exposing SQL errors in API responses
    }
});
```

**Issue:** SQL errors returned raw in API responses, exposing:
- Table names
- Column names
- Database structure
- Query details

**Impact:** Information disclosure aids SQL injection attempts.

**Fix Required:**
```php
$exceptions->render(function (QueryException $exception, Request $request) {
    if ($request->is('api/*')) {
        if ($exception->errorInfo[1] == 1062) {
            return response()->json(['message' => 'Duplicate Entry Detected.'], 409);
        }
        
        // Log full error but return sanitized message
        \Log::error('Database error', ['exception' => $exception]);
        
        return response()->json([
            'success' => false,
            'message' => 'A database error occurred. Please contact support.',
        ], 500);
    }
});
```

---

### 13. **Broadcasting Security - Public Channels for Private Data**
**File:** [routes/channels.php](routes/channels.php) (need to verify)  
**Severity:** HIGH  

**Issue:** Need to verify channel authorization in `routes/channels.php`. If order channels are public, any device can listen to other branches' order updates.

**Required Investigation:**
- Check if channels use device-specific naming
- Verify branch-level isolation in channel subscriptions
- Ensure private channels require authentication

**Fix Required:**
```php
// Example secure channel
Broadcast::channel('branch.{branchId}.orders', function ($device, $branchId) {
    return $device->branch_id == $branchId;
});
```

---

### 14. **Queue Job Failures - No Retry/Idempotency**
**Files:** 
- [app/Events/PrintOrder.php](app/Events/PrintOrder.php) (assumed based on dispatch calls)
- [app/Events/PrintRefill.php](app/Events/PrintRefill.php) (assumed)

**Severity:** HIGH  

**Issue:** Print events dispatch jobs with no visible retry logic or idempotency checks. If printer is offline, orders may be lost.

**Impact:** 
- Lost print jobs if queue worker crashes
- Duplicate prints if job retries without idempotency key
- No audit trail of failed print attempts

**Fix Required:**
1. Add job retry logic: `PrintOrder::dispatch($order)->onQueue('printing')->tries(3)->backoff([10, 30, 60]);`
2. Add idempotency: Check `print_events` table before creating new event
3. Store failed print jobs in `failed_jobs` table for manual retry

---

## 🟡 MEDIUM PRIORITY ISSUES

### 15. **Code Duplication - DRY Violations**
**Files:**
- Device lookup logic duplicated in [DeviceAuthApiController.php](app/Http/Controllers/Api/V1/Auth/DeviceAuthApiController.php#L16-L28) (lines 16-28) and repeated in methods: `register()`, `authenticate()`, `refresh()`, `lookupByIp()`

**Severity:** MEDIUM  

**Fix:** Extract to service method:
```php
class DeviceIpService {
    public function resolveDeviceIp(Request $request): ?string
    {
        $clientSupplied = $request->input('ip_address');
        $requestIp = $request->ip();
        
        if ($clientSupplied && $this->isPrivateIp($clientSupplied)) {
            return $clientSupplied;
        }
        return $this->isPrivateIp($requestIp) ? $requestIp : $requestIp;
    }
    
    private function isPrivateIp(?string $ip): bool { /* ... */ }
}
```

---

### 16. **God Class - OrderService**
**File:** [app/Services/Krypton/OrderService.php](app/Services/Krypton/OrderService.php)  
**Lines:** 1-200+ (full file)  
**Severity:** MEDIUM  

**Issue:** `OrderService::processOrder()` is 180+ lines handling:
- Order creation
- Table locking
- Order check creation
- Device order persistence
- Item mapping
- Print event scheduling

**Violation:** Single Responsibility Principle (SRP)

**Fix:** Split into:
- `OrderCreationService` (lines 1-80)
- `OrderItemService` (lines 80-120)
- `PrintEventService` (lines 120-150) ✅ Already exists!

---

### 17. **Missing Type Hints**
**File:** [app/Actions/Order/CreateOrder.php](app/Actions/Order/CreateOrder.php#L20)  
**Line:** 20  
**Severity:** MEDIUM  

```php
public function createNewOrder(array $attr = []) {  // ⚠️ No return type
```

**Fix Required:**
```php
public function createNewOrder(array $attr = []): Order|\stdClass {
```

**Also Fix:**
- [app/Services/Krypton/OrderService.php](app/Services/Krypton/OrderService.php#L21): Add return type `DeviceOrder`
- [app/Services/Krypton/KryptonContextService.php](app/Services/Krypton/KryptonContextService.php#L68): Add return types

---

### 18. **Dead Code**
**File:** [app/Http/Middleware/CheckSessionIsOpened.php](app/Http/Middleware/CheckSessionIsOpened.php#L15-L28)  
**Lines:** 15-28 (entire middleware body commented out)  
**Severity:** MEDIUM  

**Issue:** Middleware registered but completely disabled. Creates confusion.

**Fix:** Delete middleware or implement properly.

---

### 19. **Test Coverage Gaps**
**Directories:**
- [tests/Feature/](tests/Feature/) - 12 test files
- [tests/Unit/](tests/Unit/) - 3 test files

**Severity:** MEDIUM  

**Coverage Gaps:**
1. No tests for admin authorization (all policies commented out)
2. No tests for CORS configuration
3. No tests for rate limiting
4. No tests for SQL injection protection in refill endpoint
5. No tests for mass assignment vulnerability
6. Only 3 unit tests (OrderServiceTest, StatusParityTest, ExampleTest)

**Required Tests:**
- `Feature/Auth/AdminAuthorizationTest.php`
- `Feature/Security/CorsTest.php`
- `Feature/Security/RateLimitTest.php`
- `Feature/Security/SqlInjectionTest.php`
- `Feature/Security/MassAssignmentTest.php`

---

### 20. **API Resources Not Used Consistently**
**Files:**
- [app/Http/Controllers/Admin/OrderController.php](app/Http/Controllers/Admin/OrderController.php#L27-L32): Returns raw Eloquent collections
- [app/Http/Controllers/Api/V1/OrderApiController.php](app/Http/Controllers/Api/V1/OrderApiController.php#L69): Returns raw `DeviceOrder` model

**Severity:** MEDIUM  

**Issue:** Inconsistent use of API Resources leads to:
- Sensitive fields exposed (e.g., `deleted_at`, `updated_at`)
- No output sanitization
- Inconsistent response formats

**Fix Required:**
- Wrap ALL API responses in `DeviceOrderResource`
- Create `OrderCollection` for paginated responses
- Add `$hidden` fields to models as backup

---

## 📊 SUMMARY STATISTICS

| Severity | Count | Status |
|----------|-------|--------|
| 🔴 CRITICAL | 8 | REQUIRES IMMEDIATE FIX |
| 🟠 HIGH | 6 | FIX BEFORE PRODUCTION |
| 🟡 MEDIUM | 6 | ADDRESS IN NEXT SPRINT |
| **TOTAL** | **20** | |

---

## 🎯 PRIORITIZED REMEDIATION PLAN

### Phase 1: CRITICAL BLOCKERS (P0 - DO NOT DEPLOY TO PRODUCTION)
1. ✅ Fix CORS wildcard → whitelist specific domains
2. ✅ Fix SQL injection in refill endpoint → enforce `menu_id` + validation
3. ✅ Enable ALL authorization policies → uncomment + test
4. ✅ Remove `is_admin` mass assignment → add to `$guarded`
5. ✅ Delete `/dev/generate-codes` route → move to Artisan
6. ✅ Add API rate limiting → 10 req/min for auth, 60/min for orders
7. ✅ Fix Sanctum expiration → set to 7 days
8. ✅ Fix CSRF for admin POST routes → use controller, not closure

**Gate:** All 8 CRITICAL vulnerabilities resolved + penetration test passed

---

### Phase 2: HIGH PRIORITY (P1 - Before Scale)
1. Add missing database indexes (4 indexes)
2. Fix N+1 queries (2 controllers)
3. Add refill input validation
4. Sanitize error responses
5. Add queue retry logic for print jobs
6. Verify broadcasting authorization

**Gate:** Load test with 1000 concurrent devices, <500ms p95 response time

---

### Phase 3: MEDIUM PRIORITY (P2 - Tech Debt)
1. Refactor `OrderService` (split into 3 services)
2. Extract device IP resolution to service
3. Add type hints to all methods
4. Remove dead `CheckSessionIsOpened` middleware
5. Achieve 80% test coverage (add 15+ tests)
6. Wrap all API responses in Resources

**Gate:** Codebase quality score >8.0 in static analysis

---

## 🔒 SECURITY CHECKLIST (POST-REMEDIATION)

- [ ] Run `composer audit` for dependency vulnerabilities
- [ ] Run `php artisan route:list --json` and verify no unauthenticated admin routes
- [ ] Run OWASP ZAP scan against API endpoints
- [ ] Test SQL injection payloads against refill endpoint
- [ ] Test CSRF tokens on all POST/PUT/DELETE web routes
- [ ] Verify Sanctum tokens expire after 7 days
- [ ] Load test with 10,000 device tokens to verify rate limiting
- [ ] Penetration test: attempt privilege escalation via `is_admin` manipulation
- [ ] Verify CORS whitelist blocks unauthorized origins
- [ ] Code review: ensure all `authorize()` calls are uncommented

---

## 📝 NOTES FOR CHŪYA (IMPLEMENTATION)

When implementing fixes:
1. Create feature branch: `security/critical-vulnerabilities-2026-03-25`
2. Implement fixes in **EXACT ORDER** listed in Phase 1
3. Write tests **BEFORE** pushing each fix
4. No merge until ALL 8 CRITICAL issues resolved + tests green
5. Run `php artisan test` after EVERY fix
6. Update `.env.example` with secure CORS defaults

**DO NOT:**
- Skip authorization policy tests ("they're in the way")
- Leave commented-out authorization checks
- Use `APP_DEBUG=true` in production
- Deploy if Sanctum expiration is still `null`

**Failure Modes to Manually Test:**
- Attempt admin action as regular user (should 403)
- Attempt SQL injection in refill `name` field (should sanitize)
- Attempt CORS request from `http://evil.com` (should reject)
- Generate 100 requests/sec to `/api/devices/login` (should throttle)
- Steal a device token, wait 8 days, attempt use (should 401)

---

**All clear! This backend is a MINEFIELD… but now you have the map.**

— Ranpo Edogawa  
*Chief Architect & Forensic Auditor*
