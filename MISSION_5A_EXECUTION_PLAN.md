# MISSION 5A: Backend P0 Critical Fixes
**Status:** 🔴 READY FOR EXECUTION  
**Lead Executor:** Chūya Nakahara  
**Lead Auditor:** Ranpo Edogawa  
**Technical Analyst:** Kunikida Doppo  
**Authorized By:** President Fukuzawa  
**Date:** 2026-01-26

---

## Mission Scope (CI1-CI5)

| ID | Description | Status | Risk |
|----|-------------|--------|------|
| **CI1** | Remove unreachable code in DeviceOrderApiController | ✅ COMPLETE | None |
| **CI2** | Add validation for ordered_menu_id (partial: min:0) | 🔴 PENDING | LOW |
| **CI3** | Clarify transaction exception logging | 🔴 PENDING | NONE |
| **CI4** | Set LOG_LEVEL=error in production .env | 🔴 PENDING | NONE |
| **CI5** | Update rate limits (registration: 10/min, orders: 100/min) | 🔴 PENDING | LOW |

**Timeline:** 2-3 hours (implementation) + 2 hours (soak test) = **4-5 hours total**

---

## Chūya's Execution Orders

### File Changes (EXACT SPECIFICATIONS)

#### 1. `app/Http/Requests/StoreDeviceOrderRequest.php`
**Location:** Line 79  
**Current:**
```php
'items.*.ordered_menu_id' => ['nullable', 'integer'],
```
**New:**
```php
'items.*.ordered_menu_id' => ['nullable', 'integer', 'min:1'],
```
**Reason:** Prevent negative AND zero IDs. Zero is invalid because `ordered_menu_id` must reference an actual menu item ID from `krypton_woosoo.menus`. (CI2 partial)

**Business Logic (President Clarification):**
- **Packages ARE menu items**: Classic Feast, Noble Selection, Royal Banquet (NOT a separate packages table)
- **For meat orders**: `ordered_menu_id` = package's menu_id (e.g., Classic Feast's ID from krypton_woosoo.menus)
- **For non-meat orders** (sides, drinks): `ordered_menu_id` = null (no package association)
- **PWA correctly sends**: `state.package.id` for meats, `null` for others
- **Validation must remain nullable**: Ala carte/non-meat orders don't have package association

---

#### 2. `.env`
**Location:** Line 19  
**Current:**
```dotenv
LOG_LEVEL=debug
```
**New:**
```dotenv
LOG_LEVEL=error
```
**Reason:** Suppress debug logs in production (CI4)

---

#### 3. `routes/api.php`
**Location:** Line 90  
**Current:**
```php
->middleware('throttle:5,1')
```
**New:**
```php
->middleware('throttle:10,1')
```
**Reason:** Device registration rate limit 5→10/min (CI5)

**Location:** Line 150  
**Current:**
```php
->middleware('throttle:60,1')
```
**New:**
```php
->middleware('throttle:100,1')
```
**Reason:** Order creation rate limit 60→100/min (CI5)

---

#### 4. `app/Services/Krypton/OrderService.php`
**Location:** Lines 156-158  
**Current:**
```php
// CRITICAL: POS writes have already succeeded and are NOT rolled back.
// Local transaction failure leaves POS order orphaned.
\Illuminate\Support\Facades\Log::error('Order creation local transaction failed after POS success', [
```
**New:**
```php
// CRITICAL: Database transaction failure after order creation.
// This should be rare but indicates data integrity issue requiring manual intervention.
\Illuminate\Support\Facades\Log::error('Order creation transaction failed', [
```
**Reason:** Remove misleading "POS writes" reference (CI3 clarification)

---

### Required Tests (NEW)

Create these 3 test files:

#### Test 1: `tests/Feature/OrderedMenuIdValidationTest.php`
```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;

class OrderedMenuIdValidationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_rejects_negative_ordered_menu_id()
    {
        $device = Device::factory()->create(['table_id' => 1]);
        
        $response = $this->actingAs($device, 'device')->postJson('/api/devices/create-order', [
            'guest_count' => 2,
            'subtotal' => 100,
            'tax' => 10,
            'discount' => 0,
            'total_amount' => 110,
            'items' => [
                [
                    'menu_id' => 1,
                    'name' => 'Test Item',
                    'quantity' => 1,
                    'price' => 100,
                    'subtotal' => 100,
                    'ordered_menu_id' => -1, // Invalid negative ID
                ]
            ]
        ]);
        
        $response->assertStatus(422)
                 ->assertJsonValidationErrors('items.0.ordered_menu_id');
    }

    /** @test */
    public function it_accepts_null_ordered_menu_id()
    {
        $device = Device::factory()->create(['table_id' => 1]);
        
        $response = $this->actingAs($device, 'device')->postJson('/api/devices/create-order', [
            'guest_count' => 2,
            'subtotal' => 100,
            'tax' => 10,
            'discount' => 0,
            'total_amount' => 110,
            'items' => [
                [
                    'menu_id' => 1,
                    'name' => 'Test Item',
                    'quantity' => 1,
                    'price' => 100,
                    'subtotal' => 100,
                    'ordered_menu_id' => null, // Valid null (ala carte)
                ]
            ]
        ]);
        
        $response->assertStatus(201);
    }
}
```

#### Test 2: `tests/Feature/RateLimitEnforcementTest.php`
```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RateLimitEnforcementTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_enforces_order_creation_rate_limit()
    {
        $device = Device::factory()->create(['table_id' => 1]);
        
        $payload = [
            'guest_count' => 2,
            'subtotal' => 100,
            'tax' => 10,
            'discount' => 0,
            'total_amount' => 110,
            'items' => [
                ['menu_id' => 1, 'name' => 'Item', 'quantity' => 1, 'price' => 100, 'subtotal' => 100]
            ]
        ];

        // Make 100 requests (should all succeed)
        for ($i = 0; $i < 100; $i++) {
            $response = $this->actingAs($device, 'device')
                             ->postJson('/api/devices/create-order', $payload);
            
            // Most will fail due to "existing order" logic, but none should be rate-limited
            $this->assertNotEquals(429, $response->status());
        }

        // 101st request should be rate-limited
        $response = $this->actingAs($device, 'device')
                         ->postJson('/api/devices/create-order', $payload);
        
        $response->assertStatus(429)
                 ->assertHeader('Retry-After');
    }
}
```

#### Test 3: `tests/Unit/LogLevelTest.php`
```php
<?php

namespace Tests\Unit;

use Tests\TestCase;

class LogLevelTest extends TestCase
{
    /** @test */
    public function production_env_uses_error_log_level()
    {
        $this->assertEquals('error', config('logging.level'));
    }
}
```

---

### Execution Checklist

**Step 1: Make Changes**
- [ ] Update StoreDeviceOrderRequest.php line 79
- [ ] Update .env line 19
- [ ] Update routes/api.php lines 90 and 150
- [ ] Update OrderService.php lines 156-158
- [ ] Create 3 new test files

**Step 2: Run Tests**
```bash
php artisan test --filter=Order
php artisan test tests/Feature/OrderedMenuIdValidationTest.php
php artisan test tests/Feature/RateLimitEnforcementTest.php
php artisan test tests/Unit/LogLevelTest.php
```
**Acceptance:** All tests MUST pass

**Step 3: Commit & Push**
```bash
git add .
git commit -m "fix(mission-5a): CI2-CI5 P0 fixes (validation, rate limits, logging)

- CI2: Add min:0 validation for ordered_menu_id (prevent negative IDs)
- CI3: Clarify transaction exception logging (remove misleading POS reference)
- CI4: Set LOG_LEVEL=error in production .env
- CI5: Update rate limits (registration 10/min, orders 100/min)
- Tests: Add validation, rate limit, and log level tests"

git push origin staging
```

**Step 4: Report Kill Count**
Format:
```
Kill Count: X/Y tests passing
- Existing tests: A/B
- New tests: 3/3
- Total: X/Y
```

---

## DO / DON'T Constraints

### ✅ DO:
1. Make **EXACTLY** the 4 file changes specified above
2. Run all order-related tests before committing
3. Create 3 new test files with exact specifications
4. Use commit message format provided
5. Push to `staging` branch ONLY
6. Report Kill Count in specified format

### ❌ DON'T:
1. **DO NOT** make `ordered_menu_id` fully required (breaks PWA)
2. **DO NOT** modify DB transaction structure in OrderService (already correct)
3. **DO NOT** touch any other files (scope creep = instant rejection)
4. **DO NOT** merge to main without Gate 1 clearance
5. **DO NOT** deploy to production without Ranpo re-audit
6. **DO NOT** add TODOs or placeholders

---

## Gate 1: Soak Test (2 Hours)

**Kunikida's Verification Tasks:**

### Automated Checks:
```bash
# Test 1: Create 100 orders
for i in {1..100}; do
  curl -X POST http://staging-api-url/api/devices/create-order \
    -H "Authorization: Bearer $DEVICE_TOKEN" \
    -H "Content-Type: application/json" \
    -d '{...order payload...}'
done

# Test 2: Trigger rate limit
for i in {1..101}; do
  curl -X POST http://staging-api-url/api/devices/create-order \
    -H "Authorization: Bearer $DEVICE_TOKEN" \
    -w "\nStatus: %{http_code}\n"
done
# Expected: First 100 succeed/409, 101st returns 429

# Test 3: Verify log level
tail -f storage/logs/laravel.log
# Expected: No DEBUG entries, only ERROR/INFO
```

### Manual Regression Tests:
- [ ] Create order with meats (`ordered_menu_id` > 0) → 201 success
- [ ] Create order with sides/drinks (`ordered_menu_id` = null) → 201 success
- [ ] Attempt duplicate order → 409 conflict
- [ ] Trigger rate limit (101st request in 1 min) → 429 with Retry-After header
- [ ] Send negative `ordered_menu_id` → 422 validation error

### Success Criteria:
- ✅ 100+ orders created successfully
- ✅ Zero 500 errors
- ✅ Rate limit enforced correctly (429 after 100/min)
- ✅ Negative IDs rejected (422 validation error)
- ✅ `.env` LOG_LEVEL confirmed as `error` (no debug logs)
- ✅ No breaking changes to tablet-ordering-pwa API contract

### Rollback Trigger:
- ❌ Any 500 error → immediate rollback
- ❌ Rate limit false positives (429 before 100 requests) → rollback
- ❌ Valid orders rejected (false 422 errors) → rollback

---

## Ranpo's Re-Audit Checklist

**Code Review:**
- [ ] Exactly 4 files changed (no scope creep)
- [ ] Changes match specifications character-for-character
- [ ] No additional "improvements" or "refactoring"
- [ ] Commit message references Mission 5A and CI2-CI5

**Test Review:**
- [ ] 3 new tests created with exact specifications
- [ ] All existing tests still pass (no regressions)
- [ ] Kill Count reported accurately

**Security Audit:**
- [ ] No new attack vectors introduced
- [ ] Rate limiting protects against DoS
- [ ] Validation prevents negative ID injection

**Contract Compatibility:**
- [ ] PWA can still send `ordered_menu_id: null` (ala carte orders)
- [ ] Relay device unaffected (doesn't create orders)
- [ ] No breaking changes to response format

**Sign-Off:**
- [ ] All Gate 1 criteria met
- [ ] No critical flaws detected
- [ ] Ready for Mission 5B authorization

---

## Cross-App Impact Summary

**tablet-ordering-pwa (staging):**
- ✅ **SAFE** — `ordered_menu_id` nullable preserved
- ⚠️ **WATCH** — If PWA sends negative IDs, will now be rejected (unlikely edge case)
- 📝 **DEFER** — Full "required" validation to Mission 5C after PWA payload update

**relay-device-v2:**
- ✅ **NO IMPACT** — Relay device doesn't create orders

**woosoo-print-bridge:**
- ✅ **NO IMPACT** — Bridge doesn't interact with order creation endpoint

---

## Deferred to Mission 5C (API Contract Phase)

The following **CANNOT** be done in Mission 5A without breaking PWA:
- ❌ Making `ordered_menu_id` fully required
- ❌ Removing nullable option
- ❌ Changing error response format to standardized `{error: {code, message, details}}`

**Reason:** PWA must update payload first, then backend can tighten validation.

**Coordination Plan (Mission 5C):**
1. PWA updates `Order.ts` to send `ordered_menu_id: 0` instead of `null` for non-meats
2. Deploy PWA to staging
3. Backend changes `nullable` → `required` validation
4. Deploy backend to staging
5. Contract tests verify compatibility
6. Production deployment (coordinated cutover)

---

## Timeline

**Total:** 4-5 hours (Chūya 2-3h + Kunikida 2h)

| Phase | Duration | Owner | Deliverable |
|-------|----------|-------|-------------|
| **Implementation** | 2-3h | Chūya | 4 file changes + 3 tests committed to staging |
| **Test Execution** | 30m | Chūya | Kill Count report (18+/18+ passing) |
| **Soak Test** | 2h | Kunikida | 100+ orders, rate limit verified, zero 500s |
| **Re-Audit** | 1h | Ranpo | Code review, security check, sign-off |
| **TOTAL** | 5.5h | Team | Gate 1 cleared, Mission 5B authorized |

---

## Success Metrics

**Code Quality:**
- ✅ 4 files changed (exact scope)
- ✅ 0 TODOs or placeholders
- ✅ 0 scope creep

**Test Coverage:**
- ✅ 18+ existing tests passing
- ✅ 3 new tests passing
- ✅ 100% CI2/CI4/CI5 coverage

**Production Readiness:**
- ✅ 100+ staging orders successful
- ✅ 0 500 errors
- ✅ Rate limit enforcement verified
- ✅ PWA contract compatibility confirmed

**Gate 1 Authorization:**
- ✅ Ranpo sign-off obtained
- ✅ Kunikida verification complete
- ✅ President Fukuzawa approval for Mission 5B

---

**All clear! This mission is ready for execution… unless Chūya wants to mess it up with "creative improvements." Stick to the plan, executioner.**

— Ranpo Edogawa, Chief Architect
