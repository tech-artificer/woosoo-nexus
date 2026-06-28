---
status: draft-pending-team-review
last_reviewed: 2026-06-28
scope: woosoo-nexus, tablet-ordering-pwa, krypton-pos
---

# Spec: Nexus Package Builder + Tablet Category System

> **Status: draft-pending-team-review** — Two unresolved product decisions prevent canonical
> status: (1) `TabletApiController`'s docblock labels these endpoints "legacy," so confirm
> whether this API surface will be replaced before treating this spec as long-term authoritative.
> (2) The category-menu endpoint does not yet use the admin-curated pivot — see §3 Known Gap.
> All other content reflects the **currently shipped architecture** as of 2026-06-28 and is
> grounded in cited source files.

---

## 1. Purpose and Scope

This document specifies the architecture, data model, API contract, and administrative
functions for two related subsystems in `woosoo-nexus`:

- **Package Builder** — admin-managed dining packages (Set Meal A/B/C style), each defining
  which Krypton POS meat modifiers a guest may select and how many, plus display metadata.
- **Tablet Category System** — admin-managed category list (Sides, Dessert, Beverage, etc.)
  for the Tablet Ordering PWA, including per-category Krypton POS menu curation.

These subsystems share a data-ownership boundary: **Nexus owns structure and curation;
Krypton POS owns pricing, tax, and calculation authority.**

Cross-app contracts governed by `woosoo-platform/docs/CONTRACTS.md` take precedence over
any claim in this document when they conflict.

---

## 2. Package Builder: Data Model and Components

### 2.1 Package entity (`packages` table)

A `Package` is a named dining set (e.g. "Set Meal A") that the tablet presents to the guest
at order time.

| Column | Type | Notes |
|---|---|---|
| `id` | PK | Local Nexus identity |
| `krypton_menu_id` | FK → `krypton_woosoo.menus.id` (nullable) | Anchor POS menu for base-price read-through. Nullable when a package has no 1:1 POS menu mapping. |
| `name` | string | Display name; auto-derived from POS `menus.name` on create/update |
| `description` | text (nullable) | Admin-entered text |
| `base_price` | decimal(10,2) | **Display/cache value only** — see §4. Sourced from `krypton_woosoo.menus.price` at save time. |
| `min_meat` | tinyint | Minimum meat selections the guest must make |
| `max_meat` | tinyint | Maximum meat selections allowed |
| `banner_media_id` | FK → media (nullable) | Optional banner image |
| `is_active` | boolean | Controls visibility on tablet |
| `is_most_popular` | boolean | At most one row is true — enforced at the app layer (see §8) |
| `sort_order` | integer | Tablet display order |

**Side/dessert/beverage columns do not exist.** Migration
`2026_06_14_000001_packages_meats_only_most_popular.php` explicitly removed
`min_side/max_side`, `min_dessert/max_dessert`, `min_beverage/max_beverage` with the comment:
> "A package now configures meats only; banchan/sides/desserts/beverages are global (served
> via Tablet Categories), so the per-package non-meat limits are removed."

Non-meat items are not package components — they live entirely in the Tablet Category system
(§3).

### 2.2 Allowed Menus (`package_allowed_menus` table)

Each row represents one Krypton POS menu item that is part of a package's selectable meat
list.

| Column | Type | Notes |
|---|---|---|
| `id` | PK | |
| `package_id` | FK → `packages.id` | Cascade delete |
| `krypton_menu_id` | int | References `krypton_woosoo.menus.id` (no DB-level FK across connections) |
| `menu_type` | string(20) | Currently always `"meat"` in practice (default) |
| `meat_category_code` | string(50) nullable | Admin-entered code (`P`, `B`, or `C`) used by the PWA to group items — see §2.3 |
| `extra_price` | decimal(10,2) | **Display/cache value only** — see §4 |
| `quantity_limit` | tinyint | Max quantity selectable for this item |
| `is_required` | boolean | |
| `is_default` | boolean | Pre-selected for the guest |
| `is_active` | boolean | Admin can soft-disable individual items |
| `sort_order` | int | |

The index `pam_package_type_idx` on `(package_id, menu_type)` supports efficient
per-type filtering.

**Generic modifier groups were built, then removed.** A `package_modifiers` table existed;
`2026_06_13_203513_consolidate_packages_schema.php` dropped it and introduced the flat
`package_allowed_menus` table. There is no per-group name, per-group min/exact/max rules
entity, or "Included Menu Items" scoped to a package. The only selection rule on a package
today is the global `min_meat`/`max_meat` pair.

### 2.3 Meat grouping: PORK / BEEF / CHICKEN

The tablet PWA classifies meat modifiers into three display groups (PORK, BEEF, CHICKEN).
The **canonical classification rule**, confirmed by the project owner, is the `receipt_name`
prefix convention on `krypton_woosoo.menus` rows:

| Prefix | Group |
|---|---|
| `P` (e.g. `P1`, `P2`) | PORK |
| `B` (e.g. `B1`, `B2`) | BEEF |
| `C` (e.g. `C1`, `C2`) | CHICKEN |

This is currently implemented client-side only in
`tablet-ordering-pwa/utils/packageModifierGroups.ts` (`classifyPackageModifier` function),
which first checks `receipt_name` prefix, then falls back to `groupName`/`name` keyword
matching.

**Known gap — `meat_category_code` drift:** `package_allowed_menus.meat_category_code` is a
free-text field entered by the admin in `StorePackageRequest`/`UpdatePackageRequest`
(`app/Http/Controllers/Admin/PackageController.php`). It is not derived from `receipt_name`.
The PWA's `groupAllowedMenusByCategoryCode` function reads this field directly (v2 API path).
If an admin enters a code that does not match the `receipt_name` prefix for the same POS menu,
the two sources diverge. The `receipt_name` prefix is authoritative; `meat_category_code`
should agree with it. No server-side validation enforces this today.

### 2.4 `ModifierDescription` entity

`App\Models\ModifierDescription` provides global, package-independent text descriptions for
modifier menu items, keyed by `krypton_menu_id`. This model is shipped and schema-backed but
has no dedicated admin controller or UI surface in the current build. It is available for a
future admin "modifier descriptions" management page.

---

## 3. Tablet Category System: Data Model and Known Gap

### 3.1 Category entity (`tablet_categories` table)

| Column | Type | Notes |
|---|---|---|
| `id` | PK | |
| `name` | string | User-facing tab label |
| `slug` | string unique | Auto-generated from name; collision-safe (appends `-2`, `-3`, etc.) |
| `icon` | string nullable | Icon identifier |
| `color` | string nullable | Accent color |
| `sort_order` | integer | Tablet display order |
| `is_active` | boolean | Default true |

Slug `"meats"` is permanently reserved: `TabletApiController` treats it as a POS-group-ID
bypass (see §3.3) and excludes it from `GET /categories` DB results and from the
`hasActiveDbCategories()` gate.

### 3.2 Category-menu pivot (`tablet_category_menu` table)

| Column | Type | Notes |
|---|---|---|
| `id` | PK | |
| `tablet_category_id` | FK → `tablet_categories.id` | |
| `krypton_menu_id` | int | References `krypton_woosoo.menus.id` |
| `sort_order` | integer | Per-category item order |
| `is_featured` | boolean | Optional featured flag |

The admin UI (`TabletCategoryController`) uses this pivot to let operators manually select
which Krypton POS menus appear in each category and in what order.

### 3.3 Known gap — `categoryMenus` does not use the pivot

`GET /api/v2/tablet/categories/{slug}/menus` (`TabletApiController::categoryMenus`) does
**not** read from `tablet_category_menu`. Instead it uses a hardcoded Krypton POS group-ID
map for a fixed set of four slugs:

| Slug | Resolution |
|---|---|
| `meats` | `menuRepository->getMenusByGroupId(34)` (POS group "Meat Order") |
| `sides` | `menuRepository->getMenusByGroupId(29)` |
| `beverage` | `menuRepository->getMenusByGroupId(30)` (mapped via `beverage → drinks`) |
| `dessert` | `menuRepository->getMenusByCourse('dessert')` (mapped via `dessert → desserts`) |

Any other slug that reaches this endpoint returns 404 (when DB categories exist) or passes
through to `resolveLegacyCategoryMenus()` which also only handles the four slugs above.

**Product-decision context:** The CHANGELOG entry "Strict tablet contract — no legacy
fallback, fixed POS category mapping" predates the admin-curation build. At the time it was a
deliberate design decision; subsequent work added the admin pivot. The user's stated current
intent is that "administrators manually select the items per menu category," which the pivot
model supports for metadata but the endpoint does not yet honour for content delivery. This is
a **product decision to revisit**, not just a bug. Until the endpoint is wired to the pivot,
the admin-curated menu selection has no effect on what the tablet receives.

**Fallback behaviour (no DB categories):** When no active non-meats rows exist in
`tablet_categories`, `categories()` returns a three-item hardcoded list
(`sides`/`dessert`/`beverage`) and `categoryMenus` uses `resolveLegacyCategoryMenus`. This
is a bootstrap fallback only.

---

## 4. Data Ownership and Pricing Authority

| Domain | Owned by | Notes |
|---|---|---|
| Pricing, tax, totals, calculation | **Krypton POS** (`krypton_woosoo` DB) | `krypton_woosoo.menus.id` is the authoritative price source. Anything calculated at POS/order time is Krypton's responsibility. |
| Package composition (which menus, min/max meat) | **Nexus** (`packages`, `package_allowed_menus`) | Structural curation only. |
| Package display metadata (name, description, image, sort order, active/most-popular) | **Nexus** | |
| Tablet Category curation (title, item selection, active, sort order) | **Nexus** (`tablet_categories`, `tablet_category_menu`) | |
| Order state, session lifecycle | **Nexus** (`orders`, `device_orders`) | See CONTRACTS §1 |

`packages.base_price` and `package_allowed_menus.extra_price` are **cached/display values**
sourced from Krypton at save time. They are used for presentation in the admin panel and
fallback display on the tablet. They are **not** the system of record for actual charged
amounts, which remain Krypton's responsibility at order/POS time.

`packages.krypton_menu_id` being nullable means a package-as-bundle does not always map 1:1
to a single Krypton menu row. Pricing authority still defers to Krypton regardless.

---

## 5. Synchronization and Cache Invalidation

### 5.1 Cache keys

`TabletApiController` manages three cache key families:

| Key | TTL | Busted by |
|---|---|---|
| `tablet.packages.v2` | 300 s | `PackageUpdated` event (dispatched on package create/update/delete/syncAllowedMenus) |
| `tablet.categories.v2` | 300 s | `TabletApiController::forgetCategoriesCache()` called from `TabletCategoryController` on any category mutation |
| `tablet.category_menus.v2.{slug}` | 300 s | Same — per-slug key forgotten on category or menu pivot change |

The `PackageUpdated` event (`App\Events\Menu\PackageUpdated`) is broadcast over Reverb to
connected tablets so they refetch package data without polling. Category changes do not
currently broadcast a Reverb event — tablets pick up changes on next page load or cache expiry.

### 5.2 POS connectivity

All Krypton POS lookups inside `TabletApiController` are wrapped in try/catch. If the `pos`
DB connection is unavailable:
- `packages`: returns `[]` (empty) for POS menu enrichment, not 500.
- `categoryMenus` (meats/sides/drinks/desserts): returns empty collection — the controller
  calls `Menu::hydrate([])` in the catch block; `getMenusByGroupId` also does so in test
  mode.
- A log entry is written at `warning` level.

---

## 6. API: Endpoints and Response Shapes

Base path: `/api/v2/tablet/`. All endpoints require device Bearer token authentication
(`sanctum` middleware, device token issued at device registration).

### 6.1 `GET /api/v2/tablet/packages`

Returns all active packages with their allowed menus (meat items).

**Response `data[]`:**
```json
{
  "id": 1,
  "krypton_menu_id": 46,
  "name": "Set Meal A",
  "description": "...",
  "base_price": "799.00",
  "min_meat": 1,
  "max_meat": 3,
  "is_active": true,
  "is_most_popular": true,
  "sort_order": 0,
  "allowed_menus": [
    {
      "id": 10,
      "krypton_menu_id": 101,
      "menu_name": "P1 Samgyupsal",
      "menu_type": "meat",
      "meat_category_code": "P",
      "extra_price": 0.0,
      "quantity_limit": 1,
      "is_required": false,
      "is_default": false,
      "is_active": true,
      "sort_order": 0
    }
  ]
}
```

### 6.2 `GET /api/v2/tablet/packages/{id}`

Returns full package detail for a single package ID (active only), plus `allowed_menus`
grouped by `menu_type` key.

**Response `data.allowed_menus`:**
```json
{
  "meat": [...],
  "side": [...],
  "dessert": [...],
  "drinks": [...]
}
```

(Non-meat types are empty arrays in the current build — meats-only model per §2.)

### 6.3 `GET /api/v2/tablet/categories`

Returns the admin-curated category list (DB path) or three-item fallback
(`sides`/`dessert`/`beverage`) if no active non-meats rows exist. The `meats` slug is always
excluded; the PWA injects the Meats tab independently.

**Response `data[]`:**
```json
{
  "id": 1,
  "name": "Sides",
  "slug": "sides",
  "icon": null,
  "color": null,
  "menu_count": 8
}
```

### 6.4 `GET /api/v2/tablet/categories/{slug}/menus`

Returns menus for a category slug. **Currently uses hardcoded POS group-ID resolution (§3.3),
not the admin-curated pivot.** Passes through `MenuResource` which includes: `id`, `name`,
`receipt_name`, `price`, `is_taxable`, `is_modifier`, `img_url`, and related resource fields.

The `meats` slug always resolves via POS group 34 regardless of DB category state.

### 6.5 `GET /api/v2/tablet/meat-categories`

Returns the three fixed meat groups used by the PWA to render the meat-selection UI:

```json
[
  {"id": 1, "name": "PORK",    "slug": "pork",    "prefix": "P"},
  {"id": 2, "name": "BEEF",    "slug": "beef",    "prefix": "B"},
  {"id": 3, "name": "CHICKEN", "slug": "chicken", "prefix": "C"}
]
```

### 6.6 Tablet request contract (CONTRACTS §3)

The tablet sends **intent only** when submitting an order:
```json
{
  "guest_count": 4,
  "package_id": 1,
  "items": [
    {"menu_id": 101, "quantity": 1}
  ]
}
```

No pricing, modifier-group tree, or nested options are sent from the tablet. Any API shape
that implies nested modifier groups/options in the request is not supported and violates the
tablet's hard rule (`tablet-ordering-pwa/CLAUDE.md`: "Sends intent only — never pricing, tax,
modifiers, totals, POS mapping, or state").

---

## 7. Error Handling and Fallback Behaviour

| Scenario | Behaviour |
|---|---|
| Unknown slug to `categoryMenus` when DB categories exist | 404 |
| Unknown slug to `categoryMenus` when no DB categories exist and not in legacy map | 404 |
| Active category with no pivot rows | 200 with `data: []` |
| POS DB unavailable during `categoryMenus` | 200 with `data: []` + warning log |
| `krypton_menu_id` in pivot not found in Krypton `menus` table | Item silently dropped from response (filtered by `->filter()`) |
| `krypton_menu_id` in `package_allowed_menus` not found in Krypton | Display fallback `"Menu #{id}"` used in admin panel; item included in API with `menu_name: "Menu #101"` |
| No active packages | Empty array `[]` |
| Package requested by ID that is inactive | 404 |

**Known gap — silent POS reference failures:** When a `package_allowed_menus.krypton_menu_id`
does not resolve to a row in `krypton_woosoo.menus`, the tablet receives `"Menu #{id}"` as
the display name with no admin-facing warning. There is no admin alert or orphan-reference
report for stale `krypton_menu_id` values.

---

## 8. Administrative Functions

All admin routes require Sanctum session authentication and appropriate role/permission gates.

### 8.1 Package management (`Admin\PackageController`)

| Action | Route | Description |
|---|---|---|
| List | `GET /admin/packages` | Index with enriched POS menu names and `allowed_menus` |
| Create | `POST /admin/packages` | Requires `krypton_menu_id` from POS package-anchor scope |
| Update | `PUT /admin/packages/{id}` | Full update including `allowed_menus` replacement |
| Delete | `DELETE /admin/packages/{id}` | Cascades to `package_allowed_menus` |
| Sync meats | `POST /admin/packages/{id}/sync-allowed-menus` | Replace meat list only |
| Set most popular | `POST /admin/packages/{id}/set-most-popular` | Enforces single-row invariant |

**`is_most_popular` invariant:** At most one package may have `is_most_popular = true`.
MySQL does not support partial unique indexes, so this invariant is enforced at the
application layer by `PackageController::makeOnlyMostPopular()`, which runs inside a DB
transaction and sets all other rows to `false` before setting the target row to `true`.
Setting `is_most_popular` via `store()`, `update()`, or `setMostPopular()` all go through
this helper.

**POS menu scopes used in the admin:** `Menu::packageAnchors()` scopes for package-level POS
menus; `Menu::meatModifiers()` scopes for meat modifier items. These drive the admin's
package/meat picker dropdowns.

### 8.2 Tablet Category management (`Admin\TabletCategoryController`)

| Action | Description |
|---|---|
| List | Index with live POS menu name enrichment from `tablet_category_menu` pivots |
| Create | Create category with optional slug (auto-generated if omitted); slug collision-safe |
| Update | Update metadata and pivot replacements (add/remove Krypton menu IDs per category) |
| Delete | Removes category and its pivot rows |

The admin UI allows operators to: assign a user-facing category name, choose sort order and
active state, and manually select Krypton POS menus per category (managing `tablet_category_menu`
rows). The `is_featured` flag on pivot rows is set in the admin but not yet consumed by the
tablet endpoint (which uses the hardcoded POS-group-ID path — §3.3).

### 8.3 `ModifierDescription` (shipped model, no admin surface yet)

`App\Models\ModifierDescription` stores global, package-independent display descriptions for
modifier menu items, keyed by `krypton_menu_id`. The model and its table are present in the
schema but no admin controller or UI has been built for it. It is available for a future
"modifier descriptions" admin page without schema changes.

---

## 9. Known Gaps (Formal Record)

The following gaps are on record as of 2026-06-28. Each is a product decision, not an
unplanned defect.

| # | Gap | Location | Impact |
|---|---|---|---|
| G1 | `categoryMenus` endpoint bypasses `tablet_category_menu` pivot; uses hardcoded POS group-ID map | `TabletApiController::categoryMenus`, `resolveLegacyCategoryMenus` | Admin curation of per-category menus has no effect on what the tablet receives |
| G2 | `meat_category_code` (admin-entered) can drift from `receipt_name` prefix (authoritative) | `package_allowed_menus.meat_category_code`, `StorePackageRequest`, `UpdatePackageRequest` | PWA's `groupAllowedMenusByCategoryCode` may disagree with `classifyPackageModifier` heuristic |
| G3 | Stale `krypton_menu_id` references produce silent fallback display text, no admin warning | `TabletApiController::packages` (`"Menu #{id}"`), `TabletApiController::categoryMenus` (item silently dropped) | Admin has no visibility into orphaned POS references |
| G4 | `TabletApiController` docblock calls endpoints "legacy" — no team decision on replacement | `TabletApiController` class docblock | Spec may become obsolete if API surface is retired |
| G5 | Category mutations do not broadcast a Reverb event | `TabletCategoryController` | Tablets pick up category changes only on next page load or cache TTL expiry (max 5 min lag) |

---

## 10. Edge Cases

- **Only `meats` active in `tablet_categories`:** `hasActiveDbCategories()` excludes the
  `meats` slug, so a meats-only DB configuration correctly falls through to the legacy
  fallback in `categories()` and `categoryMenus()`. (Fixed 2026-06-28; see
  `TabletApiController::hasActiveDbCategories`.)
- **`alacarte` slug:** Previously present in the hardcoded fallback but removed because
  `resolveLegacyCategoryMenus` has no branch for it. Client requests for `/alacarte/menus`
  return 404 in both DB and legacy paths.
- **Concurrent `is_most_popular` updates:** `makeOnlyMostPopular` runs inside a DB
  transaction but does not use a pessimistic lock. A race condition under concurrent requests
  could momentarily set two rows to `true`; the next write clears the duplicate. Acceptable
  at current load.
- **Nullable `krypton_menu_id` on `packages`:** A package without a POS anchor resolves
  `base_price` from `packages.base_price` directly (`resolvePackageBasePrice`). The display
  fallback is valid; pricing authority still defers to Krypton at order time.
- **Cache poisoning after rollback:** If a DB transaction rolls back after a
  `forgetCategoriesCache()` call, the cache is invalidated but the DB change did not commit.
  The next request re-populates the cache from the reverted DB state (no permanent
  inconsistency, one extra DB round-trip).

---

## 11. Cross-Repo Documentation Gaps (Follow-up Required)

The following staleness was identified in a cross-doc sweep on 2026-06-28. No edits were made
to `tablet-ordering-pwa` in this pass (one-app-per-commit rule). These are formal named
follow-up items.

| File | Staleness |
|---|---|
| `tablet-ordering-pwa/docs/DATA_MODEL.md` | Documents `Package = { id, name, price, img_url, accent, color, is_popular, tax, tax_amount, modifiers: Modifier[] }` — does not match real `Package` interface in `types/index.d.ts` (no `accent`/`color`/`is_popular`/`tax`/`tax_amount`/`modifiers`; real shape has `base_price`, `min_meat`/`max_meat`, `allowed_menus[]`). Also lists `tabletCategories[]` under "Removed (dead code) — unused," which is incorrect: Tablet Categories are a live backend feature. |
| `tablet-ordering-pwa/types/index.d.ts` | Still declares `min_side/max_side`, `min_dessert/max_dessert`, `min_beverage/max_beverage` on the `Package` interface — columns dropped in `2026_06_14_000001_packages_meats_only_most_popular.php`. |
| `woosoo-nexus/CHANGELOG.md` | Documents `woosoo:sync-package-modifiers` Artisan command as "the sole package modifier update path," but no such command exists in `app/Console/Commands/` or `routes/console.php`. Orphaned reference, likely removed during `package_modifiers → package_allowed_menus` consolidation. |

**Recommended follow-up task:** Update `tablet-ordering-pwa/docs/DATA_MODEL.md` and
`tablet-ordering-pwa/types/index.d.ts` to reflect the v2 Package shape, and remove the
orphaned changelog command reference.
