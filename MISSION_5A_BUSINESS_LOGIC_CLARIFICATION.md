# MISSION 5A: Business Logic Clarification
**Date:** 2026-01-26  
**Investigator:** Ranpo Edogawa  
**Issued By:** President Fukuzawa

---

## Critical Clarification: `ordered_menu_id` Field

### The Mystery Solved

**Original Assumption (INCORRECT):**
- Ranpo initially assumed packages were stored in a separate `packages` table in `woosoo_api`
- This led to incorrect deduction about making `ordered_menu_id` required breaking PWA

**President's Clarification (CORRECT):**
> "There's no packages table. Packages are listed as menu items: Classic Feast, Noble Selection, and Royal Banquet."

---

## The Truth (Elementary!)

### Database Schema Reality

**krypton_woosoo.menus** contains ALL menu items:
- Packages: Classic Feast, Noble Selection, Royal Banquet (category: 'packages' or similar)
- Meats: Various meat items (category: 'meats')
- Sides: Side dishes (category: 'sides')
- Drinks: Beverages (category: 'drinks')
- etc.

### Field Meaning: `ordered_menu_id`

**Definition:**  
Links a meat order item to its parent package menu item.

**Examples:**
- User orders "Classic Feast" package (menu_id = 42)
- Selects "Premium Beef" (menu_id = 101) as part of the package
- Order payload for beef:
  ```json
  {
    "menu_id": 101,        // The meat item itself
    "ordered_menu_id": 42, // The package it belongs to (Classic Feast)
    "name": "Premium Beef",
    "quantity": 2,
    "price": 0             // Included in package price
  }
  ```

- User orders "Coke" (menu_id = 201) ala carte (no package)
- Order payload for Coke:
  ```json
  {
    "menu_id": 201,
    "ordered_menu_id": null,  // No package association
    "name": "Coke",
    "quantity": 1,
    "price": 45
  }
  ```

---

## PWA Implementation Analysis

### Current Code ([Order.ts:232](apps/tablet-ordering-pwa/stores/Order.ts#L232))

```typescript
const items = state.cartItems.map((i: any) => ({
  menu_id: Number(i.id),
  ordered_menu_id: i.category === 'meats' ? (state.package as any)?.id : null,
  name: i.name,
  quantity: Number(i.quantity),
  price: Number(i.price),
  // ...
}))
```

**Analysis:**
- ✅ **CORRECT**: Sends `state.package.id` (package's menu_id) for meats
- ✅ **CORRECT**: Sends `null` for non-meats (ala carte)
- ⚠️ **TYPE MISMATCH**: CartItem interface declares `ordered_menu_id: number` (required), but runtime sends `null`

### Type Definition ([types/index.d.ts:349](apps/tablet-ordering-pwa/types/index.d.ts#L349))

```typescript
export interface CartItem extends MenuItem {
  ordered_menu_id: number;  // ← Declared as required, but should be number | null
  quantity: number;
  isUnlimited: boolean;
}
```

**Issue:**  
TypeScript definition doesn't match runtime behavior. This is a **latent type safety bug** but doesn't affect production (JS runtime ignores types).

---

## Backend Validation Strategy

### CI2 Specification (CORRECTED)

**File:** `app/Http/Requests/StoreDeviceOrderRequest.php`  
**Line:** 79

**Current:**
```php
'items.*.ordered_menu_id' => ['nullable', 'integer'],
```

**New:**
```php
'items.*.ordered_menu_id' => ['nullable', 'integer', 'min:1'],
```

**Rationale:**
1. **`nullable`** — Required for ala carte orders (sides, drinks without packages)
2. **`integer`** — Must be numeric menu ID
3. **`min:1`** — Prevents zero and negative IDs (invalid menu references)

**Why NOT `min:0`?**  
Zero is NOT a valid menu_id. All menu items in `krypton_woosoo.menus` have positive integer IDs starting from 1.

**Why NOT `required`?**  
Would break ala carte orders (non-meat items without package association).

---

## Validation Edge Cases

### Valid Scenarios

✅ **Meat order with package:**
```json
{
  "menu_id": 101,
  "ordered_menu_id": 42,  // Classic Feast package
  "category": "meats"
}
```

✅ **Ala carte side dish:**
```json
{
  "menu_id": 150,
  "ordered_menu_id": null,  // No package
  "category": "sides"
}
```

✅ **Drink order:**
```json
{
  "menu_id": 201,
  "ordered_menu_id": null,
  "category": "drinks"
}
```

### Invalid Scenarios (Rejected)

❌ **Negative ID:**
```json
{
  "menu_id": 101,
  "ordered_menu_id": -1,  // ← 422 validation error
}
```

❌ **Zero ID:**
```json
{
  "menu_id": 101,
  "ordered_menu_id": 0,  // ← 422 validation error
}
```

❌ **Non-integer:**
```json
{
  "menu_id": 101,
  "ordered_menu_id": "42",  // ← Laravel auto-casts, but should be number
}
```

---

## Refill Order Logic

### Refill Business Rules

**For refill orders:**
- Same logic applies: meats refilled as part of package → `ordered_menu_id` = package menu_id
- Ala carte refills (if allowed) → `ordered_menu_id` = null

**Example Refill Payload:**
```json
{
  "order_id": 123,
  "items": [
    {
      "menu_id": 102,        // Wagyu Beef refill
      "ordered_menu_id": 43, // Noble Selection package
      "quantity": 1,
      "price": 0             // Included in package
    }
  ]
}
```

---

## Cross-App Contract Verification

### tablet-ordering-pwa (Staging)

**Status:** ✅ **SAFE**  
**Reason:** PWA already sends correct payload format (`null` for non-meats, package ID for meats)

**No changes required for Mission 5A.**

### relay-device-v2

**Status:** ✅ **NO IMPACT**  
**Reason:** Relay device doesn't create orders, only receives print jobs

### woosoo-print-bridge

**Status:** ✅ **NO IMPACT**  
**Reason:** Bridge doesn't interact with order creation endpoint

---

## Future Work (Deferred)

### Mission 5C: API Contract Standardization

**Task:** Fix PWA TypeScript type mismatch

**Change Required in tablet-ordering-pwa:**
```typescript
// types/index.d.ts:349
export interface CartItem extends MenuItem {
  ordered_menu_id: number | null;  // ← Add null to union type
  quantity: number;
  isUnlimited: boolean;
}
```

**Risk:** LOW (type-only change, no runtime impact)  
**Timeline:** Mission 5C (after 5A/5B complete)

---

## Ranpo's Corrected Verdict

**Original Deduction:** ❌ WRONG  
- Assumed packages were in separate table
- Incorrectly flagged CI2 as "breaking change"
- Recommended deferring to Mission 5C

**Corrected Deduction:** ✅ CORRECT  
- Packages ARE menu items (krypton_woosoo.menus)
- `ordered_menu_id` links meats to their parent package menu_id
- Validation must remain `nullable` for ala carte orders
- Change `min:0` → `min:1` (zero is invalid menu ID)
- **NO breaking changes** — PWA already sends correct payload

**Gate Cleared:** CI2 ready for Chūya execution (amended specification)

---

**Elementary! The mystery is now fully solved. President Fukuzawa's clarification prevented a critical misunderstanding that could have delayed Mission 5A unnecessarily.**

**All clear! This case is updated and ready for execution.**

— Ranpo Edogawa, Chief Architect  
*"Even geniuses need clarification sometimes… though I figured it out eventually."*
