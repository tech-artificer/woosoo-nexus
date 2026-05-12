# Krypton Woosoo POS Specifications (Cleaned)

This document is a cleaned, organized specification derived from prior analysis of `krypton_woosoo.sql`.  
It removes chat transcript noise and keeps only actionable technical content.

---

## 1. Scope

This spec covers:

1. Session lifecycle
2. Order creation flow
3. Package-modifier behavior
4. Pricing behavior
5. Reporting and audit/logging

Constraint from requirements: **do not include sides in package-modifier logic**.

---

## 2. Confirmed Package-Modifier Mapping

| Package | Menu ID | Base Price | Modifier Pattern | Modifier IDs (named, menu_group_id=34) |
|---|---:|---:|---|---|
| Classic Feast | 46 | 449.00 | P1-P5 | 114, 115, 116, 117, 118 |
| Noble Selection | 47 | 499.00 | P1-P5, B1-B3 | 114-118, 124, 125, 126 |
| Royal Banquet | 48 | 549.00 | P1-P10, B1-B9, C1-C2 | 114-123, 124-132, 133, 134 |

### Modifier ID Reference (menu_group_id = 34)

| receipt_name | menu ID | kitchen_name |
|---|---:|---|
| P1 | 114 | Plain Samgyupsal |
| P2 | 115 | Kajun Bulmat Samgyupsal |
| P3 | 116 | Yangyeom Samgyupsal |
| P4 | 117 | Citrus Burst Pepper Samgyupsal |
| P5 | 118 | Hyangcho Samgyupsal |
| P6 | 119 | Korean Chili Pepper Samgyupsal |
| P7 | 120 | Spicy Sesame Samgyupsal |
| P8 | 121 | Secret Spice Samgyupsal |
| P9 | 122 | Golden Mushroom Roll |
| P10 | 123 | Moksal |
| B1 | 124 | Woosamgyup |
| B2 | 125 | Beef Bulgogi |
| B3 | 126 | Asian Gochu Woosamgyup |
| B4 | 127 | Citrus Burst Woosamgyup |
| B5 | 128 | Hyangcho Woosamgyup |
| B6 | 129 | Korean Chili Pepper Beef |
| B7 | 130 | Spicy Sesame Woosamgyup |
| B8 | 131 | Secret Spice Woosamgyup |
| B9 | 132 | Golden Mushroom Beef Roll |
| C1 | 133 | Dak Galbi |
| C2 | 134 | Bulgogi Korean Chicken |

### Notes

- `Royal Banquet` is the most expensive package and includes all meats.
- **B10 does not exist in Krypton** — removed from all package definitions.
- For complete item naming, use **`kitchen_name`** (not symbol fields).
- Modifier resolution uses `menu_group_id = 34` (named tablet menus), not `is_modifier_only = true` (old stub IDs 49–68).

---

## 3. Naming Columns (Important)

In `menus` and `ordered_menus`, these columns serve different purposes:

| Column | Typical Meaning |
|---|---|
| `name` | Symbol or display shorthand (e.g., P1, B2) |
| `receipt_name` | Receipt-facing shorthand/symbol |
| `kitchen_name` | Full descriptive item name (authoritative complete name) |

For complete modifier names, prefer:

1. `menus.kitchen_name` (master definition)
2. `ordered_menus.kitchen_name` (transaction snapshot copy)

---

## 4. Core Tables for Ordering Transactions

### 4.1 Session and Staff Context

- `sessions`
  - Session header (`id`, open/close timestamps, `is_open`)
- `employee_logs`
  - Staff logins/logouts and `session_id` linkage
- `terminals`
  - POS device metadata

### 4.2 Order Lifecycle

- `orders`
  - Order header, linked to session and terminal
- `order_checks`
  - Check/payment boundary for each order
- `ordered_menus` (**critical table**)
  - Package and modifier line items
  - **Parent package row**: `ordered_menu_id IS NULL`
  - **Child modifier rows**: `ordered_menu_id = <parent ordered_menus.id>`

### 4.3 Catalog/Definitions

- `menus`
  - Contains both package records and modifier records
- `menu_modifiers`, `modifier_groups`
  - Modifier grouping/classification metadata

---

## 5. Confirmed Stored Procedures (Relevant)

### 5.1 Session

- `create_session()`
  - Creates/sets active session context via `_set_session()`
  - Updates `employee_logs.session_id` where needed

### 5.2 Audit/Logs

- `add_audit_trails(...)`
  - Inserts audit trail records
- `get_audit_trail_report(...)`
  - Retrieves audit records (reporting)
- `create_employee_log(...)`
  - Creates employee login/session log entries

### 5.3 Session Reporting Summaries

- `add_session_meal_period_summary(...)`
- `add_session_others_summary(...)`
- `add_session_payments_summary(...)`
- `add_session_revenue_summary(...)`
- `add_session_sales_category_summary(...)`
- `add_session_terminal_summary(...)`

---

## 6. Pricing Behavior

Observed behavior from transaction patterns:

1. Package row carries package price.
2. Modifier rows are typically inserted with `price = 0.0000`.
3. Total is primarily package-based (plus any other non-modifier line items, if used outside this scope).

Within this spec scope (no sides in package logic), package calculation is:

```text
order_total = sum(package_row.price * package_row.quantity)
```

---

## 7. End-to-End Flow (Operational)

### Phase 1: Session Start

1. Create employee log (`create_employee_log`)
2. Create/open session (`create_session`)
3. Associate terminal/staff context

### Phase 2: Order Creation

1. Insert `orders` header
2. Insert `order_checks` header
3. Insert package row in `ordered_menus` (`ordered_menu_id = NULL`)
4. Insert modifier rows in `ordered_menus` (`ordered_menu_id = parent_id`)

### Phase 3: Payment and Closing

1. Record payment/settlement
2. Mark check settled
3. Close session when applicable

### Phase 4: Reporting and Audit

1. Run `add_session_*_summary` procedures
2. Retrieve operational reports and audit logs

---

## 8. Minimal SQL Pattern Reference

### 8.1 Package parent + modifiers child linkage

```sql
-- Parent package
INSERT INTO ordered_menus (
    order_id, menu_id, ordered_menu_id, order_check_id, quantity, price, kitchen_name
)
VALUES (
    @orderId, @packageMenuId, NULL, @orderCheckId, @qty, @packagePrice, @packageKitchenName
);

SET @parentOrderedMenuId = LAST_INSERT_ID();

-- Child modifiers
INSERT INTO ordered_menus (
    order_id, menu_id, ordered_menu_id, order_check_id, quantity, price, kitchen_name
)
VALUES
(@orderId, @modifierId1, @parentOrderedMenuId, @orderCheckId, 1.0000, 0.0000, @modifierKitchenName1),
(@orderId, @modifierId2, @parentOrderedMenuId, @orderCheckId, 1.0000, 0.0000, @modifierKitchenName2);
```

### 8.2 Session summary generation

```sql
CALL add_session_meal_period_summary(...);
CALL add_session_payments_summary(...);
CALL add_session_revenue_summary(...);
CALL add_session_sales_category_summary(...);
CALL add_session_terminal_summary(...);
```

---

## 9. Final Implementation Rules

1. Treat `kitchen_name` as the complete canonical label.
2. Keep package-modifier linkage via `ordered_menu_id`.
3. Use package mapping exactly as defined (46, 47, 48 rules above).
4. Do not embed side-item rules inside package-modifier logic.
5. Use existing session/reporting/audit procedures for consistency.

---

## 10. Woosoo-Nexus Implementation Audit (Stored Procedures)

This section reviews the **actual woosoo-nexus code usage** of Krypton stored procedures and whether implementation behavior is correct for the POS-first contract.

### 10.1 Order Creation Path (Primary)

| Step | Stored Procedure | Code Location | How It Is Used | Status |
|---|---|---|---|---|
| Create order header | `create_order(...)` | `app/Actions/Order/CreateOrder.php:121` | Called with positional params and returns created POS order row. | ✅ Proper |
| Link order to table | `create_table_order(...)` | `app/Actions/Order/CreateTableOrder.php:51` | Called after order creation; looked up again via `TableOrder` query. | ⚠️ Needs fix (error handling) |
| Create order check | `create_order_check(...)` | `app/Actions/Order/CreateOrderCheck.php:81` | Called after table order; used to populate POS check totals. | ✅ Proper |
| Insert ordered line | `create_ordered_menu(...)` | `app/Actions/Order/CreateOrderedMenu.php:239` | Called per expanded line (package/modifier/refill item). | ✅ Proper |
| Resolve menu price level | `get_menu_price_levels_by_menu(?)` | `app/Actions/Order/CreateOrderedMenu.php:272` | Used to derive `price_level_id` for ordered menu insert. | ⚠️ Needs fix (return type + fallback) |

Execution sequence in service is correct and consistent with expected POS flow:

`create_order` → `create_table_order` → `create_order_check` → `create_ordered_menu`  
(see `app/Services/Krypton/OrderService.php:104-149`)

### 10.2 Session/Reporting/Operational Calls

| Area | Stored Procedure(s) | Location | Status |
|---|---|---|---|
| Session lookup | `get_latest_session()` | `app/Repositories/Krypton/SessionRepository.php:20`, `app/Models/Krypton/Session.php:31` | ✅ Proper |
| Table state | `get_active_table_orders*`, `check_table_status` | `app/Repositories/Krypton/TableRepository.php:19,33,48`, `app/Models/Krypton/Table.php:71` | ✅ Proper |
| Payments | `create_check_payment(...)` | `app/Http/Controllers/Admin/PosController.php:381` | ✅ Proper (with transactional caution) |
| Reporting | `get_item_sales_revenue`, `get_weekly_sales_report`, etc. | `app/Repositories/Krypton/ReportRepository.php` | ✅ Proper |
| Menus/Modifiers | `get_menus*`, `get_menu_modifiers*` | `app/Repositories/Krypton/MenuRepository.php` | ✅ Proper |
| Employee logs | `get_employee_logs_for_session(?)` | `app/Repositories/Krypton/EmployeeRepository.php:38` | ⚠️ Needs fix (binding argument shape) |

---

## 11. Improper / Risky Implementations Found

### Issue A — POS rollback deletes violate POS-first contract (**Critical**)

- **Location:** `app/Services/Krypton/OrderService.php:162-168`
- **Current behavior:** On exception, code deletes POS rows (`ordered_menus`, `order_checks`, `table_orders`, `orders`) by `createdOrderId`.
- **Why improper:** POS-first contract requires treating POS writes as authoritative and non-rolled-back side effects. Manual compensating deletes can destroy valid POS transactions and create drift with real terminal activity.

**Correction:**
1. Remove direct POS delete rollback block.
2. Keep failure as `local failed after POS write`.
3. Persist drift/recovery metadata (order id, failure stage, payload hash) for reconciliation worker.
4. Return explicit error to caller while preserving POS record integrity.

### Issue B — Action returns HTTP response object instead of throwing (**Critical**)

- **Location:** `app/Actions/Order/CreateTableOrder.php:59-61`
- **Current behavior:** Catches exception and returns `response()->json(...)` from an Action class.
- **Why improper:** Action should throw domain exception; returning a Response object can be treated as success by service flow and mask partial failures.

**Correction:**
1. Replace `return response()->json(...)` with `throw $e` (or throw typed domain exception).
2. Let controller/service layer convert exception to HTTP response.

### Issue C — `ordered_menu_id` local mirror semantics inconsistent between initial order and refill (**High**)

- **Locations:**  
  - Initial: `app/Actions/Order/CreateOrderedMenu.php:108-113` (stores package/menu id)  
  - Refill: `app/Http/Controllers/Api/V1/OrderApiController.php:367` (stores POS ordered_menus.id)
- **Current behavior:** Same local column holds different meanings depending on flow.
- **Why improper:** Breaks deterministic interpretation for reporting, joins, and client behavior.

**Correction (choose one and enforce globally):**
1. Introduce explicit columns: `pos_ordered_menu_id` and `parent_pos_ordered_menu_id` (recommended), keep `ordered_menu_id` for logical package linkage.
2. Backfill existing rows and update resources/queries.
3. Document one canonical meaning per column.

### Issue D — Employee repository stored-proc binding passed as scalar (**High**)

- **Location:** `app/Repositories/Krypton/EmployeeRepository.php:38`
- **Current behavior:** `select('CALL get_employee_logs_for_session(?)', $sessionId)` passes scalar, not bindings array.
- **Why improper:** Binding signature expects array; may fail at runtime depending on PHP/Laravel strictness.

**Correction:**
```php
DB::connection($this->connection)->select(
    'CALL get_employee_logs_for_session(?)',
    [$sessionId]
);
```

### Issue E — Price level resolver returns model/object and silently falls back to `1` (**Medium**)

- **Location:** `app/Actions/Order/CreateOrderedMenu.php:272-277`
- **Current behavior:** `first() ?? 1` may return object (not scalar id), and errors fall back to `1` silently.
- **Why improper:** Can produce wrong `price_level_id` and hide POS read failures.

**Correction:**
1. Extract explicit scalar field from SP result (`price_level_id` / expected column).
2. Validate numeric result before use.
3. On production failure, throw; keep fallback only for test mode.

### Issue F — Package indicator check includes menu id `49` as package (**Medium**)

- **Location:** `app/Actions/Order/CreateOrderedMenu.php:77`
- **Current behavior:** `in_array($menuId, [46, 47, 48, 49])`
- **Why improper:** Confirmed package ids are 46/47/48 only. ID 49 is modifier/meat and should not be treated as package indicator.

**Correction:**
```php
in_array($menuId, [46, 47, 48], true)
```

### Issue G — Stale internal doc reference in code (**Low**)

- **Location:** `app/Services/Krypton/OrderService.php:39-40`
- **Current behavior:** References `docs/DATABASE_SYNC.md` (file not present).
- **Why improper:** Maintainers cannot verify contract source from code comment.

**Correction:**
1. Update comment to an existing canonical document path.
2. Keep one authoritative source for dual-write/POS-first behavior.

---

## 12. Immediate Fix Plan (Code-Level)

1. **P0:** Remove POS delete rollback in `OrderService` and replace with recovery logging/state.
2. **P0:** Make `CreateTableOrder` throw exceptions (no HTTP response objects in actions).
3. **P1:** Normalize local line-item identifiers (`ordered_menu_id` semantics), ideally with dedicated POS id columns.
4. **P1:** Fix scalar binding bug in `EmployeeRepository::getEmployeeLogsForSession`.
5. **P1:** Harden `getMenuPriceLevel` return typing/error behavior.
6. **P2:** Fix package id set (`46,47,48` only) and refresh comments/docs.
