# Session Validation Implementation

## Overview

This document describes the comprehensive session validation system implemented to enforce mandatory `session_id` validation across the woosoo-nexus application. This ensures that all orders and print operations are properly associated with active Krypton POS sessions, preventing orphaned records and maintaining data integrity.

## Key Components

### 1. SessionNotFoundException Exception

**File**: `app/Exceptions/SessionNotFoundException.php`

A custom exception class that is thrown when:
- No active terminal session is found in the Krypton POS database
- An order creation attempt is made without a valid session
- Print events are requested for orders with invalid sessions

**Usage**:
```php
throw new SessionNotFoundException('No active terminal session found for table X');
```

The exception returns a 409 Conflict HTTP status code with a clear error message to the client.

### 2. KryptonContextService Validation

**File**: `app/Services/KryptonContextService.php`

**Key Changes**:
- Method `getData()` now throws `SessionNotFoundException` instead of returning empty array
- Enforces that all callers must have a valid session
- Removed fallback behavior that masked missing sessions

**Before**:
```php
public function getData(): array
{
    if ($sessionId === null) {
        return [];  // Silent failure
    }
    // ... rest of logic
}
```

**After**:
```php
public function getData(): array
{
    if ($sessionId === null) {
        throw new SessionNotFoundException('No active terminal session found...');
    }
    // ... rest of logic
}
```

### 3. OrderService Validation

**File**: `app/Services/OrderService.php`

**Key Changes**:
- Added `assertActiveSession()` method to validate session before order creation
- All order creation methods (`createOrder`, `createRefill`) now validate session first
- Throws `SessionNotFoundException` if session is invalid or inactive

**New Method**:
```php
private function assertActiveSession(int $sessionId): void
{
    $session = TerminalSession::on('pos')
        ->where('terminal_session_id', $sessionId)
        ->first();

    if (!$session) {
        throw new SessionNotFoundException(
            "Invalid terminal session ID: {$sessionId}"
        );
    }

    if ($session->closed_at !== null) {
        throw new SessionNotFoundException(
            "Terminal session {$sessionId} is closed (closed_at: {$session->closed_at})"
        );
    }
}
```

### 4. CreateOrder Action Validation

**File**: `app/Actions/Order/CreateOrder.php`

**Key Changes**:
- Added explicit validation for `terminal_session_id` parameter
- Throws `InvalidArgumentException` if session ID is missing from request

**Validation Code**:
```php
public function handle(array $attr): DeviceOrder
{
    if (!isset($attr['terminal_session_id']) || $attr['terminal_session_id'] === null) {
        throw new InvalidArgumentException('terminal_session_id is required');
    }
    
    // Continue with order creation...
}
```

### 5. Controller Updates

**Files Updated**:
- `app/Http/Controllers/Admin/OrderController.php`
- `app/Http/Controllers/Api/V1/DeviceOrderApiController.php`

**Key Changes**:
- Removed hardcoded fallback values like `$sessionId ?? 1`
- Now relies on upstream validation in services
- Properly propagates `SessionNotFoundException` to clients
- Returns 409 Conflict status with clear error messages

**Before** (OrderController):
```php
$kryptonData = $kryptContext->getData();
$employeeLogId = $kryptonData['employee_log_id'] ?? 1;  // Fallback!
$sessionId = $kryptonData['terminal_session_id'] ?? 1;   // Fallback!
```

**After** (OrderController):
```php
$kryptonData = $kryptContext->getData();  // Throws if no session
$employeeLogId = $kryptonData['employee_log_id'];
$sessionId = $kryptonData['terminal_session_id'];
```

### 6. Test Helper

**File**: `tests/TestCase.php`

**Helper Method**:
```php
protected function createTestSession(array $attributes = []): int
{
    DB::connection('pos')->table('terminal_sessions')->insert(array_merge([
        'terminal_session_id' => DB::connection('pos')->table('terminal_sessions')
            ->max('terminal_session_id') + 1,
        'terminal_session_business_day_id' => 1,
        'terminal_id' => 1,
        'employee_log_id' => 1,
        'opened_at' => now(),
        'closed_at' => null,
        'status' => 'active',
    ], $attributes));

    return DB::connection('pos')->table('terminal_sessions')
        ->max('terminal_session_id');
}
```

This helper is used throughout all test files to create valid sessions before testing order operations.

## Test Coverage

### Updated Test Files

All test files that create orders or device orders have been updated to use `createTestSession()`:

1. **tests/Feature/Api/OrderApiTest.php**
   - `test_device_can_filter_orders_and_receive_meta_counts()`
   - `test_update_status_endpoint()`
   - `test_bulk_status_endpoint()`

2. **tests/Feature/Admin/OrderAdminTest.php**
   - `test_admin_can_view_orders_page()`
   - `test_admin_orders_are_properly_filtered_by_branch()`
   - `test_admin_orders_are_properly_filtered_by_date_range()`
   - `test_admin_date_presets_filter_range()`

3. **tests/Feature/PrinterPrintEventsTest.php**
   - All 14 test functions updated with session creation

4. **tests/Feature/PrinterApiTest.php**
   - `test_mark_printed_endpoint()`
   - `test_mark_printed_bulk_partial_success()`

5. **tests/Feature/OrderRefillTest.php**
   - `test_refill_endpoint_persists_items_and_returns_created()`

6. **tests/Feature/OrderCreateAndRefillTest.php**
   - `test_manual_order_then_refill_creates_similar_device_order_structure()`

7. **tests/Feature/DeviceCreateOrderConflictTest.php**
   - `test_device_cannot_create_order_when_existing_pending_or_confirmed_exists()`

8. **tests/Feature/SessionOrderValidationTest.php**
   - New test file specifically for session validation scenarios

### Test Results

```
Tests:    62 passed (183 assertions)
Duration: 7.16s
```

All tests pass successfully with the new validation system in place.

## Error Handling

### Client Response Format

When a session validation error occurs, clients receive:

**Status Code**: `409 Conflict`

**Response Body**:
```json
{
    "success": false,
    "message": "No active terminal session found for table 5"
}
```

Or for closed sessions:
```json
{
    "success": false,
    "message": "Terminal session 123 is closed (closed_at: 2025-01-14 16:30:00)"
}
```

### Error Flow

1. **Order Creation Request** → Device/Admin Controller
2. **Session Lookup** → KryptonContextService
3. **Validation** → OrderService.assertActiveSession()
4. **If Invalid** → SessionNotFoundException thrown
5. **HTTP Response** → 409 Conflict with error message

## Benefits

### 1. Data Integrity
- Prevents orphaned orders without valid sessions
- Ensures all operations are tied to proper business day context
- Maintains referential integrity with POS system

### 2. Clear Error Messages
- Clients receive explicit feedback about session issues
- Easier debugging and troubleshooting
- Better user experience with actionable error information

### 3. Fail-Fast Behavior
- Errors caught early in the request lifecycle
- No partial state changes
- Prevents cascading failures

### 4. Consistent Validation
- Single source of truth for session validation logic
- All paths (API, admin, actions) use same validation
- Reduces code duplication

## Migration Considerations

### Existing Orders

Existing orders in the database with `session_id = 1` are left unchanged. This implementation only affects:
- New order creation
- Order refills
- Print event generation

### Backward Compatibility

The changes are backward compatible for:
- Existing valid orders (no changes)
- Read operations (no validation needed)
- Status updates (operate on existing orders)

## Future Enhancements

1. **Session Auto-Detection**
   - Automatically determine active session from table/device context
   - Reduce manual session_id passing

2. **Session Cleanup**
   - Automated closing of abandoned sessions
   - Warning alerts for long-running sessions

3. **Session Metrics**
   - Track session duration
   - Monitor session creation/closure patterns
   - Alert on session-related issues

## API Documentation

### POST /api/v1/orders (Create Order)

**Required Field**:
- `terminal_session_id` (integer): Must be an active, non-closed session

**Error Responses**:
- `409 Conflict`: No active session found or session is closed
- `422 Unprocessable Entity`: Missing terminal_session_id parameter

### POST /api/orders/refill (Refill Order)

**Session Handling**:
- Uses `terminal_session_id` from existing device order
- Validates session is still active before allowing refill

**Error Responses**:
- `409 Conflict`: Session is closed or invalid
- `404 Not Found`: Original order not found

## Testing Strategy

### Unit Tests
- KryptonContextService throws exception correctly
- OrderService.assertActiveSession validates properly
- CreateOrder action rejects missing session_id

### Feature Tests
- Order creation fails without valid session
- Refill operations check session validity
- Print events respect session status
- Admin operations work with valid sessions

### Integration Tests
- Full request/response cycle with session validation
- Multiple concurrent requests with different sessions
- Session closure impact on ongoing operations

## Conclusion

The session validation implementation provides a robust, fail-fast approach to ensuring data integrity across the woosoo-nexus application. By enforcing mandatory session validation at multiple layers (exception, service, action, controller), the system prevents invalid operations and provides clear feedback to clients when issues occur.

All 62 tests pass, demonstrating that the implementation maintains existing functionality while adding critical validation logic.
