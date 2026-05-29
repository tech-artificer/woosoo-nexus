# Server-Authoritative Order Transaction Implementation Plan

**Date:** 2026-05-09  
**Priority:** P0 - Top Priority  
**Status:** Ready for Implementation  
**Repos:** `woosoo-nexus`, `tablet-ordering-pwa`

---

## Problem Statement

Current system has order/refill blockers due to:
- Tablet calculates totals (untrusted authority)
- Tablet decides refillability (untrusted authority)
- POS `ordered_menu_id` uses menu_id instead of actual POS row IDs
- Refill lacks idempotency (risk of duplicate POS rows)
- POS success + local failure = split-brain state

---

## Solution Architecture

**Core Principle:** Tablet sends intent only. Server is single source of truth.

### Tablet Payload (New)

**Initial Order:**
```json
{
  "guest_count": 3,
  "package_id": 46,
  "items": [
    { "menu_id": 10, "quantity": 2 },
    { "menu_id": 13, "quantity": 2 }
  ]
}
```

**Refill:**
```json
{
  "items": [
    { "menu_id": 10, "quantity": 2 },
    { "menu_id": 13, "quantity": 1 }
  ]
}
```

**Note:** Order ID comes from URL path (`/api/device/orders/{orderId}/refills`), not payload.

### Server Responsibilities

- Package validity
- Guest count rules
- Item validity
- Package-allowed item validation
- Pricing & tax calculation
- POS `ordered_menus` rows
- Local mirror rows
- Print events
- Reverb broadcasts
- Idempotency enforcement
- Recovery handling

---

## Phase 1: Backend (`woosoo-nexus`)

### 1.1 Database Migrations

#### `order_quotes` Table
```php
Schema::create('order_quotes', function (Blueprint $table) {
    $table->id();
    $table->uuid('quote_uuid')->unique();
    $table->foreignId('device_id')->constrained();
    $table->foreignId('table_id')->constrained('tables');
    $table->string('session_id');
    $table->integer('guest_count');
    $table->foreignId('package_menu_id')->constrained('menus');
    $table->json('intent_payload');      // Original request
    $table->json('quote_payload');       // Calculated lines
    $table->decimal('subtotal', 10, 2);
    $table->decimal('tax', 10, 2);
    $table->decimal('discount', 10, 2)->default(0);
    $table->decimal('total', 10, 2);
    $table->timestamp('expires_at');
    $table->enum('status', ['active', 'committed', 'expired', 'cancelled'])->default('active');
    $table->foreignId('committed_order_id')->nullable()->constrained('device_orders');
    $table->timestamps();
    
    $table->index(['device_id', 'status']);
    $table->index(['quote_uuid']);
    $table->index(['expires_at']);
});
```

#### `order_transactions` Table
```php
Schema::create('order_transactions', function (Blueprint $table) {
    $table->id();
    $table->enum('type', ['initial', 'refill']);
    $table->string('idempotency_key', 64)->index();
    $table->string('payload_hash', 64);     // SHA256 of normalized payload
    $table->foreignId('device_id')->constrained();
    $table->string('session_id');
    $table->foreignId('device_order_id')->nullable()->constrained('device_orders');
    $table->bigInteger('pos_order_id')->nullable();        // Krypton orders.id
    $table->bigInteger('pos_order_check_id')->nullable();  // Krypton order_checks.id
    $table->foreignId('quote_id')->nullable()->constrained('order_quotes');
    $table->json('request_payload');    // What was requested
    $table->json('order_plan');          // Canonical plan built
    $table->json('pos_result')->nullable(); // POS response
    $table->enum('status', [
        'pending', 'planned', 'pos_written', 'local_written', 
        'events_dispatched', 'completed', 'recovery_required', 'failed'
    ])->default('pending');
    $table->unsignedInteger('recovery_attempt_count')->default(0);
    $table->unsignedInteger('max_recovery_attempts')->default(8);
    $table->timestamp('next_recovery_at')->nullable();
    $table->timestamp('last_recovery_attempt_at')->nullable();
    $table->string('recovery_locked_by', 64)->nullable();
    $table->timestamp('recovery_lock_expires_at')->nullable();
    $table->string('last_recovery_error_code', 64)->nullable();
    $table->text('last_recovery_error_message')->nullable();
    $table->string('terminal_failure_reason', 64)->nullable();
    $table->unsignedInteger('state_version')->default(0);
    $table->text('error_message')->nullable();
    $table->timestamps();
    
    $table->unique(['idempotency_key', 'device_id']);
    $table->index(['device_order_id', 'type']);
    $table->index(['status', 'created_at']);
    $table->index(['next_recovery_at', 'status']);
});
```

#### `device_order_items` Schema Update
```php
Schema::table('device_order_items', function (Blueprint $table) {
    $table->bigInteger('pos_ordered_menu_id')->nullable()->after('id');
    $table->bigInteger('parent_pos_ordered_menu_id')->nullable()->after('pos_ordered_menu_id');
    $table->enum('line_role', ['package', 'included_item', 'refill', 'paid_extra'])
          ->default('included_item')
          ->after('parent_pos_ordered_menu_id');
    $table->boolean('is_refill')->default(false)->after('line_role');
    
    $table->index(['pos_ordered_menu_id']);
    $table->index(['parent_pos_ordered_menu_id']);
    $table->index(['line_role']);
});
```

### 1.2 Service Layer

#### `OrderTransactionService` (Main Entry)
```php
class OrderTransactionService
{
    public function createQuote(Device $device, InitialOrderIntent $intent): OrderQuote;
    public function commitInitialOrder(Device $device, string $quoteId, string $idempotencyKey): DeviceOrder;
    public function submitRefill(Device $device, RefillIntent $intent, string $idempotencyKey): DeviceOrder;
    public function recoverTransaction(string $idempotencyKey): ?DeviceOrder;
}
```

#### `OrderPlanner` (Builds Canonical Plan)
```php
class OrderPlanner
{
    public function buildInitialPlan(InitialOrderIntent $intent): OrderPlan;
    public function buildRefillPlan(RefillIntent $intent, DeviceOrder $order): OrderPlan;
}

// OrderPlan structure:
// - type: initial|refill
// - device_id
// - table_id
// - guest_count
// - package_id
// - lines: OrderLinePlan[]
// - totals: Totals

// OrderLinePlan:
// - menu_id
// - quantity
// - line_role: package|included_item|refill|paid_extra
// - unit_price
// - tax
// - subtotal
// - total
// - parent_client_key (nullable, for parent linkage)
```

#### `OrderPricingService`
```php
class OrderPricingService
{
    public function calculateQuote(OrderPlan $plan): QuotePayload;
    public function applyPackagePricing(OrderPlan $plan): void;
    public function calculateTax(decimal $subtotal): decimal;
}
```

#### `PosOrderWriter`
```php
class PosOrderWriter
{
    public function writeInitialOrder(OrderPlan $plan, OrderTransaction $transaction): PosWriteResult;
    public function writeRefill(OrderPlan $plan, DeviceOrder $order, OrderTransaction $transaction): PosWriteResult;
    
    // Must capture and return actual POS ordered_menus.id values
    // Never use package menu ID as parent ordered_menu_id
}
```

#### `DeviceOrderMirror`
```php
class DeviceOrderMirror
{
    public function mirrorInitialOrder(DeviceOrder $order, OrderPlan $plan, PosWriteResult $posResult): void;
    public function mirrorRefill(DeviceOrder $order, OrderPlan $plan, PosWriteResult $posResult): void;
    public function updateFromPosResult(DeviceOrder $order, PosWriteResult $posResult): void;
}
```

### 1.3 API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/device/menu-contract` | Returns packages + items with rules |
| POST | `/api/device/orders/quote` | Get quote for initial order |
| POST | `/api/device/orders/initial` | Commit initial order with quote_id |
| POST | `/api/device/orders/{orderId}/refills` | Submit refill |
| GET | `/api/device/orders/active` | Get current active order |

#### Unified Response Schemas

**Success Response (All endpoints):**
```json
{
  "success": true,
  "data": {
    // Endpoint-specific data
  },
  "request_hash": "sha256_of_normalized_payload",
  "transaction_id": "ordt_abc123",
  "timestamp": "2026-05-10T12:00:00+08:00"
}
```

**Error Response (All endpoints):**
```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_FAILED",
    "message": "Package not available for selected guest count",
    "details": {
      "field": "package_id",
      "value": 46,
      "constraint": "max_guests: 10"
    }
  },
  "request_hash": "sha256_of_normalized_payload",
  "retryable": false,
  "timestamp": "2026-05-10T12:00:00+08:00"
}
```

**Idempotency Conflict Response:**
```json
{
  "success": false,
  "error": {
    "code": "IDEMPOTENCY_CONFLICT",
    "message": "Idempotency key already used with different payload",
    "details": {
      "original_request_hash": "sha256_original",
      "current_request_hash": "sha256_current",
      "original_transaction_id": "ordt_original123"
    }
  },
  "request_hash": "sha256_of_normalized_payload",
  "retryable": false,
  "timestamp": "2026-05-10T12:00:00+08:00"
}
```

**Idempotent Replay Response:**
```json
{
  "success": true,
  "data": {
    // Cached original response data
  },
  "request_hash": "sha256_of_normalized_payload",
  "transaction_id": "ordt_cached123",
  "idempotent_replay": true,
  "timestamp": "2026-05-10T12:00:00+08:00"
}
```

#### Request/Response Examples

**Quote Request:**
```json
{
  "guest_count": 3,
  "package_id": 46,
  "items": [
    { "menu_id": 10, "quantity": 2 },
    { "menu_id": 13, "quantity": 2 }
  ]
}
```

**Quote Response:**
```json
{
  "success": true,
  "quote": {
    "quote_id": "ordq_a1b2c3d4",
    "expires_at": "2026-05-09T21:15:00+08:00",
    "guest_count": 3,
    "package_id": 46,
    "lines": [
      {
        "menu_id": 46,
        "name": "Classic Feast",
        "quantity": 3,
        "unit_price": 399,
        "line_role": "package",
        "subtotal": 1197,
        "tax": 119.70,
        "total": 1316.70
      },
      {
        "menu_id": 10,
        "name": "Beef",
        "quantity": 2,
        "unit_price": 0,
        "line_role": "included_item",
        "subtotal": 0,
        "tax": 0,
        "total": 0
      }
    ],
    "totals": {
      "subtotal": 1197,
      "tax": 119.70,
      "discount": 0,
      "total": 1316.70
    }
  }
}
```

**Initial Commit Request:**
```http
POST /api/device/orders/initial
X-Idempotency-Key: idem_abc123xyz
Content-Type: application/json

{
  "quote_id": "ordq_a1b2c3d4"
}
```

**Refill Request:**
```http
POST /api/device/orders/19583/refills
X-Idempotency-Key: idem_def456uvw
Content-Type: application/json

{
  "items": [
    { "menu_id": 10, "quantity": 2 },
    { "menu_id": 13, "quantity": 1 }
  ]
}
```

### 1.4 Menu Contract Response

```json
{
  "version": "menu-contract.v1.2026-05-09",
  "etag": "\"menu-contract-v1-20260509\"",
  "packages": [
    {
      "menu_id": 46,
      "name": "Classic Feast",
      "price": 399,
      "min_guests": 2,
      "max_guests": 20,
      "allowed_item_ids": [10, 13, 16, 20, 25],
      "included_item_pricing": "zero",
      "is_active": true
    }
  ],
  "items": [
    {
      "menu_id": 10,
      "name": "Beef",
      "is_selectable_for_package": true,
      "is_refillable": true,
      "max_quantity_per_request": 5,
      "category": "meat"
    }
  ]
}
```

**Caching and invalidation requirements (must implement):**
- Server returns `ETag` and contract `version` for `/api/device/menu-contract`
- Client sends conditional request (`If-None-Match`) and uses `304 Not Modified`
- Client cache key is version-scoped, not timestamp-only
- `session.reset` broadcast must force menu-contract invalidation before next order flow

### 1.5 Validation Rules

**Initial Order:**
- [ ] Authenticated device required
- [ ] Device must have assigned table
- [ ] No existing active order for device/session
- [ ] Active POS session required
- [ ] Guest count within package min/max
- [ ] Package must exist and be active
- [ ] Each item menu_id must exist
- [ ] Each item must be allowed for package
- [ ] Duplicate menu_ids normalized to quantity
- [ ] Quote must exist and be active
- [ ] Quote must not be expired
- [ ] Quote must belong to requesting device
- [ ] Idempotency key must be unique or match same payload

**Refill:**
- [ ] Authenticated device required
- [ ] Order must exist and belong to device/branch
- [ ] Order must not be completed/voided/cancelled
- [ ] Session must match if provided
- [ ] Each item must exist in Krypton
- [ ] Each item must be refillable (server-side rule)
- [ ] Duplicate menu_ids normalized to quantity
- [ ] Idempotency key required

### 1.6 Idempotency and Conflict Policy (Required)

- Every write endpoint requires `X-Idempotency-Key`:
  - `POST /api/device/orders/initial`
  - `POST /api/device/orders/{orderId}/refills`
- Server persists idempotency in `order_transactions` (single source of truth)
- Server computes `payload_hash` from normalized request payload
- Same key + same hash = replay cached canonical response (`X-Idempotent-Replay: true`)
- Same key + different hash = `409 Conflict` with explicit idempotency conflict code
- Key scope: `(device_id, session_id, route family)` with unique guard on `device_id + idempotency_key`
- TTL policy:
  - Initial commit idempotency: 24 hours
  - Refill idempotency: session lifetime or 2 hours, whichever is shorter
- Refill retries for the same submit attempt must reuse the same key until terminal success/failure

#### Idempotency Key Generation (Hardened)

**Client-side Generation:**
```typescript
// utils/idempotency.ts
export function generateIdempotencyKey(): string {
  // Primary: Crypto API with fallback
  if (typeof crypto !== 'undefined' && crypto.randomUUID) {
    return `woosoo_${crypto.randomUUID()}`
  }
  
  // Fallback 1: timestamp + random
  if (typeof Date.now === 'function' && typeof Math.random === 'function') {
    const timestamp = Date.now().toString(36)
    const random = Math.random().toString(36).slice(2, 15)
    return `woosoo_${timestamp}_${random}`
  }
  
  // Fallback 2: simple counter (last resort)
  const counter = (parseInt(localStorage.getItem('woosoo_idem_counter') || '0') + 1).toString(36)
  localStorage.setItem('woosoo_idem_counter', counter)
  return `woosoo_fallback_${counter}`
}
```

**Key Scope and Storage:**
```typescript
// Device-scoped keys prevent cross-device collisions
const keyScope = `${deviceId}_${sessionId}_${routeFamily}`

// Persisted in localStorage with session cleanup
localStorage.setItem(`woosoo_idem_${keyScope}`, generatedKey)

// Clear policy:
// - On successful transaction completion
// - On session reset
// - On device re-registration
// - After TTL expiration
```

### 1.7 Concurrent Quote Policy (Required)

#### Quote Uniqueness Strategy
- **Uniqueness constraint**: `(device_id, status = 'active')`
- **Max active quotes**: 1 per device at any time
- **New quote invalidation**: Creating new quote automatically cancels previous active quotes

#### Quote Lifecycle
```sql
-- New quote creation
1. UPDATE order_quotes 
   SET status = 'cancelled' 
   WHERE device_id = ? AND status = 'active';
   
2. INSERT INTO order_quotes (
   device_id, quote_uuid, status, intent_payload, 
   expires_at, created_at
) VALUES (?, ?, 'active', ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), NOW());
```

#### Cleanup Policy
```php
// Scheduled cleanup every 5 minutes
class QuoteCleanupJob extends Job
{
    public function handle()
    {
        // Cancel expired quotes
        OrderQuote::where('expires_at', '<', now())
            ->where('status', 'active')
            ->update(['status' => 'expired']);
            
        // Delete old quotes (older than 24 hours)
        OrderQuote::where('created_at', '<', now()->subHours(24))
            ->whereIn('status', ['expired', 'cancelled', 'committed'])
            ->delete();
    }
}
```

#### Client-side Quote Management
```typescript
// Auto-invalidate on cart changes
watch([selectedPackage, guestCount, cartItems], () => {
  if (currentQuote.value && !isQuoteStale.value) {
    markQuoteStale()
  }
})

// Quote refresh policy
const refreshQuote = async () => {
  if (isQuoting.value) return
  
  clearCurrentQuote()
  await quoteInitialOrder()
}
```

### 1.7 Recovery State Machine (Required)

**Trigger ownership:**
- Immediate synchronous path after detecting `pos_written` succeeded but local mirror/event dispatch failed
- Scheduler sweep every minute for rows in `recovery_required` or stale `recovering`
- Manual admin action (`force_recovery`) for operations

**Retry policy:**
- Max attempts: `8`
- Backoff: `min(2^attempt * 15s, 15m)` + jitter (`0-20%`)
- Attempts exhausted => terminal `failed` with `terminal_failure_reason = recovery_exhausted`

**Allowed transitions only:**
- `pending -> planned -> pos_written -> local_written -> events_dispatched -> completed`
- `pos_written -> recovery_required`
- `recovery_required -> recovering -> local_written`
- `recovering -> recovery_required` (retryable failure, attempts remaining)
- `recovering -> failed` (non-retryable or retries exhausted)

**Loop guards:**
- Monotonic state transitions only (reject invalid backward transitions)
- Single active recovery lock (`recovery_locked_by`, `recovery_lock_expires_at`)
- Compare-and-set using `state_version`
- If POS IDs already exist, recovery must replay local/event work only (never POS rewrite)
- Scheduler honors `next_recovery_at` to prevent hot retry loops

**Required observability metrics:**
- `recovery_required_total`
- `recovery_attempt_total`
- `recovery_success_total`
- `recovery_failed_total`
- `recovery_dead_letter_total`
- `recovery_duration_seconds`
- `recovery_stuck_count`

### 1.8 Backend Tests Required

Create test files:
- `tests/Feature/Ordering/InitialOrderQuoteTest.php`
- `tests/Feature/Ordering/InitialOrderCommitTest.php`
- `tests/Feature/Ordering/RefillOrderTransactionTest.php`
- `tests/Feature/Ordering/OrderTransactionIdempotencyTest.php`
- `tests/Feature/Ordering/OrderTransactionRecoveryTest.php`
- `tests/Unit/Services/Ordering/OrderPlannerTest.php`
- `tests/Unit/Services/Ordering/OrderPricingServiceTest.php`

Test scenarios:
- Quote returns correct server totals
- Quote rejects invalid package
- Quote rejects disallowed items
- Quote normalizes duplicate items (A,A → A qty:2)
- Commit requires valid quote
- Commit rejects expired quote
- Commit rejects reused quote
- Initial commit writes package row + included rows
- Included items are zero-priced
- POS row IDs captured correctly
- POS row IDs stored in local mirror
- Refill rejects terminal order
- Refill rejects non-refillable item
- Refill normalizes duplicates
- Refill idempotency prevents duplicate POS rows
- Same key + same payload = replay/resume
- Same key + different payload = 409 Conflict
- POS success + local failure → recovery_required status
- Recovery resumes local mirror without re-writing POS
- Print/Reverb events fire only after local commit

---

## Phase 2: Frontend (`tablet-ordering-pwa`)

### 2.1 Type Definitions

```typescript
// types/order.ts
export type InitialOrderIntent = {
  guest_count: number
  package_id: number
  items: Array<{
    menu_id: number
    quantity: number
  }>
}

export type RefillIntent = {
  items: Array<{
    menu_id: number
    quantity: number
  }>
}

export type OrderQuote = {
  quote_id: string
  expires_at: string
  guest_count: number
  package_id: number
  lines: QuoteLine[]
  totals: QuoteTotals
}

export type QuoteLine = {
  menu_id: number
  name: string
  quantity: number
  unit_price: number
  line_role: 'package' | 'included_item' | 'refill' | 'paid_extra'
  subtotal: number
  tax: number
  total: number
}

export type QuoteTotals = {
  subtotal: number
  tax: number
  discount: number
  total: number
}

export type MenuContract = {
  packages: PackageContract[]
  items: ItemContract[]
}

export type PackageContract = {
  menu_id: number
  name: string
  price: number
  min_guests: number
  max_guests: number
  allowed_item_ids: number[]
  included_item_pricing: 'zero' | 'proportional'
  is_active: boolean
}

export type ItemContract = {
  menu_id: number
  name: string
  is_selectable_for_package: boolean
  is_refillable: boolean
  max_quantity_per_request: number
  category: string
}
```

### 2.2 API Endpoints (Constants)

```typescript
// config/api.ts
export const API_ENDPOINTS = {
  MENU_CONTRACT: '/api/device/menu-contract',
  ORDER_QUOTE: '/api/device/orders/quote',
  ORDER_INITIAL: '/api/device/orders/initial',
  ORDER_REFILL: (orderId: number | string) => `/api/device/orders/${orderId}/refills`,
  ACTIVE_ORDER: '/api/device/orders/active',
} as const
```

### 2.3 Store Updates (Order Store)

```typescript
// stores/Order.ts additions
export const useOrderStore = defineStore('order', () => {
  // Existing state...
  
  // New quote state
  const currentQuote = ref<OrderQuote | null>(null)
  const isQuoting = ref(false)
  const quoteError = ref<string | null>(null)
  const isQuoteStale = ref(false)
  
  // Idempotency keys (persisted to localStorage)
  const initialCommitIdempotencyKeys = ref<Record<string, string>>({}) // quote_id -> key
  const refillIdempotencyKeys = ref<Record<string, string>>({}) // order_id:payload_hash -> key
  
  // Actions
  const buildInitialOrderIntent = (): InitialOrderIntent
  const quoteInitialOrder = async (): Promise<boolean>
  const commitInitialOrder = async (): Promise<DeviceOrder | null>
  const clearQuote = () => void
  const markQuoteStale = () => void
  
  const buildRefillIntent = (): RefillIntent
  const submitRefill = async (): Promise<DeviceOrder | null>
  
  const getOrCreateIdempotencyKey = (type: 'initial' | 'refill', identifier: string): string
  const clearIdempotencyKey = (type: 'initial' | 'refill', identifier: string): void
  
  // Watchers
  watch([selectedPackage, guestCount, cartItems], () => {
    if (currentQuote.value) markQuoteStale()
  })
  
  return {
    // ... existing exports
    currentQuote,
    isQuoting,
    quoteError,
    isQuoteStale,
    buildInitialOrderIntent,
    quoteInitialOrder,
    commitInitialOrder,
    clearQuote,
    buildRefillIntent,
    submitRefill,
  }
})
```

### 2.4 Intent Builders

```typescript
// composables/useOrderIntent.ts
export function useOrderIntent() {
  const buildInitialOrderIntent = (
    guestCount: number,
    packageId: number,
    selectedItems: SelectedItem[]
  ): InitialOrderIntent => {
    // Normalize duplicates: merge same menu_id into quantity
    const merged = selectedItems.reduce((acc, item) => {
      const existing = acc.find(i => i.menu_id === item.menu_id)
      if (existing) {
        existing.quantity += item.quantity || 1
      } else {
        acc.push({ menu_id: item.menu_id, quantity: item.quantity || 1 })
      }
      return acc
    }, [] as Array<{ menu_id: number; quantity: number }>)
    
    return {
      guest_count: guestCount,
      package_id: packageId,
      items: merged,
    }
  }
  
  const buildRefillIntent = (
    refillItems: SelectedItem[]
  ): RefillIntent => {
    // Same normalization logic
    const merged = refillItems.reduce((acc, item) => {
      const existing = acc.find(i => i.menu_id === item.menu_id)
      if (existing) {
        existing.quantity += item.quantity || 1
      } else {
        acc.push({ menu_id: item.menu_id, quantity: item.quantity || 1 })
      }
      return acc
    }, [] as Array<{ menu_id: number; quantity: number }>)
    
    return {
      items: merged,
    }
  }
  
  return { buildInitialOrderIntent, buildRefillIntent }
}
```

### 2.5 Idempotency Key Management

```typescript
// utils/idempotency.ts
const IDEMPOTENCY_KEY_PREFIX = 'woosoo_idem_'

export function generateIdempotencyKey(): string {
  const randomUuid = typeof crypto.randomUUID === 'function'
    ? crypto.randomUUID()
    : `${Date.now()}-${Math.random().toString(16).slice(2)}`

  return `${IDEMPOTENCY_KEY_PREFIX}${randomUuid}`
}

export function getInitialCommitKey(quoteId: string): string | null {
  return localStorage.getItem(`woosoo_initial_commit_idem_key:${quoteId}`)
}

export function setInitialCommitKey(quoteId: string, key: string): void {
  localStorage.setItem(`woosoo_initial_commit_idem_key:${quoteId}`, key)
}

export function clearInitialCommitKey(quoteId: string): void {
  localStorage.removeItem(`woosoo_initial_commit_idem_key:${quoteId}`)
}

export function getRefillKey(orderId: number, payloadHash: string): string | null {
  return localStorage.getItem(`woosoo_refill_idem_key:${orderId}:${payloadHash}`)
}

export function setRefillKey(orderId: number, payloadHash: string, key: string): void {
  localStorage.setItem(`woosoo_refill_idem_key:${orderId}:${payloadHash}`, key)
}

export function clearRefillKey(orderId: number, payloadHash: string): void {
  localStorage.removeItem(`woosoo_refill_idem_key:${orderId}:${payloadHash}`)
}

// Payload hash for comparison (simple JSON-stable hash)
export function hashPayload(payload: object): string {
  const sorted = JSON.stringify(payload, Object.keys(payload).sort())
  // Use SHA-256 in production implementation for server/client parity.
  let hash = 0
  for (let i = 0; i < sorted.length; i++) {
    const char = sorted.charCodeAt(i)
    hash = ((hash << 5) - hash) + char
    hash = hash & hash
  }
  return Math.abs(hash).toString(36)
}
```

### 2.6 Review Screen Updates

Display server quote totals:

```vue
<!-- pages/order/review.vue -->
<template>
  <div v-if="currentQuote" class="quote-review">
    <h2>Order Summary</h2>
    
    <div v-for="line in currentQuote.lines" :key="line.menu_id" class="line-item">
      <span>{{ line.name }} x {{ line.quantity }}</span>
      <span>{{ formatPrice(line.total) }}</span>
    </div>
    
    <div class="totals">
      <div>Subtotal: {{ formatPrice(currentQuote.totals.subtotal) }}</div>
      <div>Tax: {{ formatPrice(currentQuote.totals.tax) }}</div>
      <div class="total">Total: {{ formatPrice(currentQuote.totals.total) }}</div>
    </div>
    
    <div v-if="isQuoteStale" class="stale-warning">
      Cart changed - refresh quote to see updated total
      <button @click="refreshQuote">Refresh</button>
    </div>
    
    <p class="disclaimer">
      Estimated total. Final amount may change only if staff applies 
      discounts, voids, or POS adjustments.
    </p>
    
    <button 
      :disabled="isQuoteStale || isCommitting" 
      @click="confirmOrder"
    >
      {{ isCommitting ? 'Processing...' : 'Confirm Order' }}
    </button>
  </div>
</template>
```

### 2.7 Frontend Tests Required

Create test files:
- `tests/unit/composables/useOrderIntent.spec.ts`
- `tests/unit/stores/OrderQuote.spec.ts`
- `tests/unit/utils/idempotency.spec.ts`
- `tests/e2e/initial-order-quote-flow.spec.ts`
- `tests/e2e/refill-idempotency.spec.ts`

Test scenarios:
- Intent builder merges duplicate menu_ids
- Intent builder omits totals/prices/is_package/modifiers
- Quote flow marks quote stale after cart changes
- Commit requires quote_id
- Commit persists idempotency key until success
- Refill intent builder merges duplicates
- Refill idempotency key survives network failure
- Endpoint constants used for order APIs
- Review screen renders server quote totals
- Stale quote prevents submission

---

## Phase 3: Integration & Migration

### 3.1 Rollout Strategy

| Phase | Duration | Activities |
|-------|----------|------------|
| 1 | Days 1-3 | Backend: Migrations, services, endpoints, tests |
| 2 | Days 4-6 | Frontend: Intent builders, quote flow, tests |
| 3 | Day 7 | Integration testing, feature flag enablement |
| 4 | Week 2+ | Monitor, deprecate old endpoints |

### 3.2 Feature Flag

Add `useServerAuthoritativeOrders` feature flag:
- Default: `false` in production
- Enable for staging testing
- Emergency rollback capability

### 3.3 Monitoring

Track these metrics:
- `order_transactions` status distribution (watch for `recovery_required`)
- Quote-to-commit conversion time
- Refill duplicate detection rate
- POS write success rate
- Local mirror success rate

### 3.4 Old Endpoint Deprecation

After 2 weeks stable:
1. Add usage logging to old endpoints
2. Set deprecation headers
3. Remove after zero usage confirmed

---

## Non-Negotiable Rules

```
[ ] Tablet NEVER sends official totals
[ ] Tablet NEVER decides package legality
[ ] Tablet NEVER decides refill legality
[ ] Server quote is ONLY customer-facing total authority
[ ] Server commit is ONLY persistence authority
[ ] Every order mutation requires idempotency key
[ ] Every POS write recorded in order_transactions
[ ] Events fire ONLY after local canonical state commits
[ ] Tablet replaces state from server response (not merges)
[ ] POS ordered_menus IDs stored explicitly (not overloaded)
[ ] Duplicate items normalized BEFORE server submit
[ ] Quote expires after 5-10 minutes
[ ] Stale quote blocks submission
```

---

## Risk Mitigation

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| POS row ID capture failure | Low | High | Test stored proc return values; fallback query |
| Quote expiration UX friction | Medium | Medium | Add refresh button; extend TTL for large parties |
| Idempotency key collision | Low | High | Use crypto.randomUUID() with device prefix |
| Recovery logic bugs | Medium | High | Extensive test coverage; manual recovery admin tool |
| Menu price change mid-quote | Low | Medium | Short TTL; real-time quote invalidation webhooks |

---

## Success Criteria

- [ ] All backend tests pass
- [ ] All frontend tests pass
- [ ] Integration tests pass (end-to-end order flows)
- [ ] No duplicate POS rows in 1000 test transactions
- [ ] Recovery scenario works (POS success + local failure → resume)
- [ ] Staging deployment stable for 48 hours
- [ ] Performance: Quote < 200ms, Commit < 500ms

---

## Appendix: POS Integration Notes

### Capturing Inserted Row IDs

For MySQL stored procedures:
```sql
-- After INSERT, capture LAST_INSERT_ID()
SET @package_row_id = LAST_INSERT_ID();

-- Return to application
SELECT @package_row_id as package_ordered_menu_id;
```

Or use `DB::getPdo()->lastInsertId()` after each insert if using Eloquent/Query Builder.

### Parent Linkage Decision

**If Krypton needs parent-child reporting:**
- Set `parent_pos_ordered_menu_id` = package row's `ordered_menus.id`

**If Krypton prints flat rows only:**
- Leave `parent_pos_ordered_menu_id` null
- All rows go directly to `ordered_menus`

**Never:** Use `package_menu_id` (the `menus.id`) as `ordered_menu_id`.

---

**Document Owner:** Backend/Frontend Team Lead  
**Next Review:** After Phase 1 completion  
**Approved For Implementation:** ✅
