# API Contract Resolution - Server Authoritative Order Transaction

**Date:** 2026-05-10  
**Purpose:** Resolve critical contract conflicts identified in review  
**Status:** Ready for Implementation  

---

## Critical Issue 1: Endpoint Family Conflicts

### Conflict Summary
- **Implementation Plan**: Uses `/api/device/orders/*` family
- **Long-term Requirements**: Lists both `/api/device/orders/*` and legacy endpoints `/api/devices/create-order`, `/api/order/{orderId}/refill`

### Resolution Decision

**Canonical v1 Endpoint Family:** `/api/device/orders/*`

**Rationale:**
- Consistent with device-scoped namespace
- Matches existing device authentication patterns
- Clear resource hierarchy (device → orders → specific operations)

### Endpoint Standardization

| Operation | Canonical Endpoint | Legacy Alias | Deprecation Plan |
|-----------|-------------------|--------------|------------------|
| Initial Order Quote | `POST /api/device/orders/quote` | None | N/A |
| Initial Order Commit | `POST /api/device/orders/initial` | `POST /api/devices/create-order` | Deprecate after v1.1 stable |
| Refill Submit | `POST /api/device/orders/{orderId}/refills` | `POST /api/order/{orderId}/refill` | Deprecate after v1.1 stable |
| Active Order | `GET /api/device/orders/active` | None | N/A |
| Order Lookup | `GET /api/device/orders/{orderId}` | `GET /api/device-order/by-order-id/{orderId}` | Deprecate after v1.1 stable |

---

## Critical Issue 2: Refill Request Schema

### Conflict Summary
- **Implementation Plan**: Shows refill with `order_id` in top-level payload
- **Long-term Requirements**: Path-only refill (order ID from URL)

### Resolution Decision

**Refill Schema:** Path-only order identification

**Final Refill Request:**
```http
POST /api/device/orders/{orderId}/refills
X-Idempotency-Key: idem_def456uvw
Content-Type: application/json

{
  "items": [
    { "menu_id": 10, "quantity": 2 },
    { "menu_id": 13, "quantity": 1 }
  ]
}
```

**Rationale:**
- RESTful resource hierarchy (orders/{id}/refills)
- Eliminates payload/URL redundancy
- Matches HTTP semantics for nested resources
- Simpler validation (order must exist in path)

---

## Critical Issue 3: Idempotency Persistence Model

### Conflict Summary
- **Implementation Plan**: Idempotency in `order_transactions` table
- **Long-term Requirements**: Separate `idempotency_records` table mentioned

### Resolution Decision

**Single Source of Truth:** `order_transactions` table

**Rationale:**
- Simpler data model (no joins needed)
- Transaction context preserved with idempotency
- Atomic operations on single table
- Easier recovery and debugging
- Matches implementation plan's detailed schema

**Final Schema:**
```sql
order_transactions
- id
- type (initial|refill)
- idempotency_key (indexed, unique per device)
- payload_hash
- device_id
- session_id
- request_payload
- order_plan
- pos_result
- status (pending|planned|pos_written|local_written|events_dispatched|completed|recovery_required|failed)
- recovery_attempt_count
- max_recovery_attempts
- next_recovery_at
- recovery_locked_by
- recovery_lock_expires_at
- last_recovery_error_code
- last_recovery_error_message
- terminal_failure_reason
- state_version
- created_at
- updated_at
```

---

## Critical Issue 4: Recovery Lifecycle Specification

### Missing Components Identified
- No owner/trigger policy
- No retry budget specification
- No loop prevention details
- No terminal recovery states

### Complete Recovery Specification

#### Trigger Ownership
```php
// Three recovery trigger sources:
1. Immediate synchronous recovery (after POS success + local failure)
2. Scheduler sweep (every minute for recovery_required rows)
3. Manual admin action (force_recovery endpoint)
```

#### Retry Policy
```php
'max_recovery_attempts' => 8,
'backoff_strategy' => 'min(2^attempt * 15s, 15m) + jitter(0-20%)',
'retry_conditions' => [
    'network_timeout',
    'database_constraint_violation',
    'reverb_broadcast_failure',
    'print_dispatch_failure'
],
'terminal_conditions' => [
    'recovery_exhausted',
    'pos_order_not_found',
    'session_terminated',
    'device_unregistered'
]
```

#### Loop Prevention Guards
```php
// Single active recovery lock
'recovery_locked_by' => 'worker_id',
'recovery_lock_expires_at' => 'now() + 5 minutes',

// State versioning for compare-and-set
'state_version' => 'monotonic_integer',

// POS write guard
if ($transaction->pos_order_id !== null) {
    // Never retry POS write, only local mirror/events
    $this->recoverLocalStateOnly($transaction);
}

// Scheduler respects backoff
if ($transaction->next_recovery_at > now()) {
    continue; // Skip until retry time
}
```

#### Recovery State Machine
```php
// Allowed transitions only:
pending -> planned -> pos_written -> local_written -> events_dispatched -> completed
pos_written -> recovery_required
recovery_required -> recovering -> local_written -> events_dispatched -> completed
recovering -> recovery_required (retryable failure)
recovering -> failed (terminal)
```

---

## Implementation Priority

### Phase 0 (Immediate - Today)
1. ✅ Adopt `/api/device/orders/*` as canonical
2. ✅ Use path-only refill schema
3. ✅ Use `order_transactions` for idempotency
4. ✅ Implement complete recovery spec above

### Phase 1 (This Week)
1. Update implementation plan with resolved contracts
2. Add unified error/success schemas
3. Harden idempotency key generation
4. Define quote concurrency policy

### Phase 2 (Next Week)
1. Add menu contract caching strategy
2. Complete flow state machine
3. Add comprehensive test coverage

---

## Updated Non-Negotiable Rules

```
[ ] Tablet NEVER sends official totals
[ ] Tablet NEVER decides package legality
[ ] Tablet NEVER decides refill legality
[ ] Server quote is ONLY customer-facing total authority
[ ] Server commit is ONLY persistence authority
[ ] Every order mutation requires idempotency key
[ ] Idempotency persisted in order_transactions table only
[ ] Recovery has explicit owner, retry budget, and terminal states
[ ] Refill order ID comes from URL path, not payload
[ ] Canonical endpoint family is /api/device/orders/*
[ ] Events fire ONLY after local canonical state commits
[ ] Recovery uses compare-and-set with state_version
```

---

**Document Owner:** Backend Team Lead  
**Status:** Resolutions Complete - Ready for Implementation  
**Next Action:** Update implementation plan with these resolutions
