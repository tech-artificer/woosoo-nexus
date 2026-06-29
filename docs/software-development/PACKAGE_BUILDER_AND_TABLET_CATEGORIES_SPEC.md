---
status: draft-pending-team-review
last_reviewed: 2026-06-29
scope: woosoo-nexus, tablet-ordering-pwa, krypton-pos
---

# Spec: Nexus Package Builder + Tablet Category System

> **Status: draft-pending-team-review** — One unresolved product decision prevents canonical
> status: `TabletApiController`'s docblock labels these endpoints "legacy," so confirm whether
> this API surface will be replaced before treating this spec as long-term authoritative.
> The category-menu pivot gap and the `meat_category_code` derivation gap described in earlier
> drafts of this document were **fixed in `e75f617` and `d96fd8a`** (2026-06-28) — see the
> corrected §2.3 and §3.3 below. All other content reflects the **currently shipped
> architecture** as of 2026-06-29 and is grounded in cited source files.

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
| `meat_category_code` | string(50) nullable | Code (`P`, `B`, or `C`) used by the PWA to group items — auto-derived from `receipt_name` if not explicitly supplied; see §2.3 |
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

This rule is implemented client-side in `tablet-ordering-pwa/utils/packageModifierGroups.ts`
(`classifyPackageModifier` function), which first checks `receipt_name` prefix, then falls back
to `groupName`/`name` keyword matching. Note that this client-side classifier is **not** the
function actually wired into the live UI (`PackageCard.vue`, `packageSelection.vue` use
`groupAllowedMenusByCategoryCode`/`displayMeatGroupLabel`, which read `meat_category_code`
directly) — it is exercised only by its own unit test. This PWA-side dead-code gap is unchanged
by the server-side fix below and remains a named follow-up (see §11).

**Fixed (2026-06-28, `d96fd8a`):** `package_allowed_menus.meat_category_code` was previously a
free-text field that the admin UI never actually populated (`SyncEntry` never sent it), so it
was always stored `null` — causing the PWA's `groupAllowedMenusByCategoryCode` to silently drop
every meat item, since it only recognizes `P`/`B`/`C`. `PackageController::replaceAllowedMenus()`
now fetches `receipt_name` alongside the POS validation query and derives the code from the
first character (`P`→`PORK`, `B`→`BEEF`, `C`→`CHICKEN`) via a new `deriveMeatCategoryCode()`
helper whenever the request payload does not supply an explicit code — an explicit value in the
payload still wins, preserving backwards compatibility. `StorePackageRequest`/
`UpdatePackageRequest` continue to validate `allowed_menus.*.meat_category_code` as `nullable`,
since the derivation now backfills the null case server-side.

**Residual gap — existing rows are not backfilled.** The fix only applies at the next
`replaceAllowedMenus()` write (i.e. admin Packages → Manage Meats → Save). Rows created before
2026-06-28 retain whatever `meat_category_code` value (including `null`) they already had until
an admin re-syncs that package's meat list. There is no migration or backfill command for
existing rows — this is called out explicitly in the fixing commit's own message.

### 2.4 `ModifierDescription` entity

`App\Models\ModifierDescription` provides global, package-independent text descriptions for
modifier menu items, keyed by `krypton_menu_id`. This model is shipped and schema-backed but
has no dedicated admin controller or UI surface in the current build. It is available for a
future admin "modifier descriptions" management page.

---

## 3. Tablet Category System: Data Model and Resolution Behaviour

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

### 3.3 `categoryMenus` resolution — fixed for admin-curated categories, `meats` remains a permanent special case

**Fixed (2026-06-28, `e75f617`):** `GET /api/v2/tablet/categories/{slug}/menus`
(`TabletApiController::categoryMenus`) previously bypassed `tablet_category_menu` entirely in
favour of a hardcoded Krypton POS group-ID map for four fixed slugs. It now resolves menus
differently depending on the slug and whether any admin-curated categories exist:

| Case | Resolution |
|---|---|
| Slug is `meats` | **Always** `menuRepository->getMenusByGroupId(34)` (POS group "Meat Order") — see below, this is permanent by design, not a residual gap. |
| Slug is not `meats`, and at least one active non-meats row exists in `tablet_categories` (`hasActiveDbCategories()`) | Resolved from the `tablet_category_menu` pivot: `TabletCategory::menuPivots` ordered by `sort_order`, mapped to live Krypton `menus` rows. Unknown/inactive slugs return 404. Empty pivot returns `200` with `data: []`. |
| Slug is not `meats`, and **no** active non-meats `tablet_categories` rows exist at all | Falls through to `resolveLegacyCategoryMenus()` — the original hardcoded map (`sides`→POS group 29, `beverage`→drinks group 30, `dessert`→course `'dessert'`). This is a **bootstrap fallback only**, active before any admin has created a tablet category. |

So the admin-curated pivot **does** now drive tablet content for any admin-managed category
(sides, beverage, dessert, or a custom category an admin creates), as long as at least one such
category exists in the database. The previous "Known gap" language describing all four slugs as
permanently hardcoded is **no longer accurate** and is corrected here.

**Permanent design decision — `meats` is not pivot-driven.** Unlike the other three legacy
slugs, `meats` is excluded from `hasActiveDbCategories()` and from `GET /categories` DB results
(`TabletApiController::MEATS_SLUG`), and `categoryMenus` always resolves it via the hardcoded
POS group 34 regardless of any `tablet_categories` row with that slug. The code comment on the
`MEATS_SLUG` constant states the rationale: "meats tab is PWA-injected; catalog via POS group."
This is intentional, not an oversight — do not treat it as the same class of gap as the
now-fixed sides/beverage/dessert case.

**Results caching:** both the pivot-driven and legacy-fallback paths are now cached under
`tablet.category_menus.v2.{slug}` (300s TTL; see §5.1), busted by
`TabletApiController::forgetCategoriesCache()` on any category or pivot mutation.

**Residual gap — `is_featured` still unused by the API.** The pivot's `is_featured` column
(§3.2) is settable in the admin UI (`toggleFeatured()`) but `categoryMenus()`'s response does
not surface it — the endpoint returns the resolved `Menu`/`MenuResource` rows only, with no
featured flag merged in. A tablet client cannot currently distinguish a featured item from a
regular one via this endpoint.

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

Returns menus for a category slug. **Resolves from the admin-curated `tablet_category_menu`
pivot when an active admin category with that slug exists** (fixed 2026-06-28, `e75f617` —
see §3.3); falls back to the legacy hardcoded POS group-ID map only as a bootstrap path before
any admin category exists. Passes through `MenuResource` which includes: `id`, `name`,
`receipt_name`, `price`, `is_taxable`, `is_modifier`, `img_url`, and related resource fields.

The `meats` slug always resolves via POS group 34 regardless of DB category state — this is a
permanent special case, not a fallback (§3.3).

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
rows) — this curation now drives the tablet's `categoryMenus` response directly (§3.3). The
`is_featured` flag on pivot rows is set in the admin but is still not surfaced by the tablet
endpoint's response shape (§3.3 residual gap).

### 8.3 `ModifierDescription` (shipped model, no admin surface yet)

`App\Models\ModifierDescription` stores global, package-independent display descriptions for
modifier menu items, keyed by `krypton_menu_id`. The model and its table are present in the
schema but no admin controller or UI has been built for it. It is available for a future
"modifier descriptions" admin page without schema changes.

---

## 9. Known Gaps (Formal Record)

The following gaps are on record as of 2026-06-29. G1 and G2 below were fixed on 2026-06-28
(`e75f617`, `d96fd8a`) after an earlier draft of this document had recorded them as open —
retained here with corrected status so the fix is on record, not silently dropped.

| # | Gap | Location | Impact | Status |
|---|---|---|---|---|
| G1 | `categoryMenus` endpoint bypassed `tablet_category_menu` pivot; used hardcoded POS group-ID map for all four legacy slugs | `TabletApiController::categoryMenus` | Admin curation of per-category menus had no effect on what the tablet received | **Fixed `e75f617`** — pivot now drives `sides`/`beverage`/`dessert`/custom slugs when an active admin category exists; hardcoded map survives only as a bootstrap fallback. `meats` remains permanently hardcoded by design (§3.3), not a residual gap. |
| G2 | `meat_category_code` (admin-entered) could drift from `receipt_name` prefix (authoritative); admin UI never actually populated the field | `package_allowed_menus.meat_category_code`, `PackageController::replaceAllowedMenus` | PWA's `groupAllowedMenusByCategoryCode` silently dropped meat items with no/invalid code | **Fixed `d96fd8a`** — auto-derived from `receipt_name` prefix at write time when not explicitly supplied. Residual: rows written before 2026-06-28 are not backfilled; require a manual re-sync per package. |
| G3 | Stale `krypton_menu_id` references produce silent fallback display text, no admin warning | `TabletApiController::packages` (`"Menu #{id}"`), `TabletApiController::categoryMenus` (item silently dropped) | Admin has no visibility into orphaned POS references | Open |
| G4 | `TabletApiController` docblock calls endpoints "legacy" — no team decision on replacement | `TabletApiController` class docblock | Spec may become obsolete if API surface is retired | Open |
| G5 | Category mutations do not broadcast a Reverb event | `TabletCategoryController` | Tablets pick up category changes only on next page load or cache TTL expiry (max 5 min lag) | Open |
| G6 | `tablet_category_menu.is_featured` is admin-settable but not surfaced in `categoryMenus()`'s response shape | `TabletApiController::categoryMenus` | Tablet cannot distinguish a featured item via this endpoint | Open |
| G7 | PWA's `receipt_name`-prefix classifier (`classifyPackageModifier` et al., `packageModifierGroups.ts`) is dead code — the live UI path (`groupAllowedMenusByCategoryCode`) reads `meat_category_code` instead | `tablet-ordering-pwa/utils/packageModifierGroups.ts`, `PackageCard.vue`, `packageSelection.vue` | The user-confirmed-canonical classification rule is unused in production; G2's fix improves `meat_category_code` accuracy but does not make the PWA consume `receipt_name` directly | Open — PWA edit, out of scope for this nexus-only pass |

---

## 10. Edge Cases

- **Only `meats` active in `tablet_categories`:** `hasActiveDbCategories()` excludes the
  `meats` slug, so a meats-only DB configuration correctly falls through to the legacy
  fallback in `categories()` and `categoryMenus()` for the remaining slugs (shipped 2026-06-28
  as part of `e75f617`; see `TabletApiController::hasActiveDbCategories`).
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

---

## Appendix A: Tablet PWA ↔ Nexus API Alignment Audit

Cross-checked every PWA call site against `woosoo-nexus/routes/api.php` and the controller
each route resolves to, reading controller source directly rather than inferring shapes.

### Matched table: PWA call → route → controller → response shape

| PWA call site | Method + URL | Route name | Controller·method | Response shape (200/201) | Status |
|---|---|---|---|---|---|
| `Device.ts:406` register | `POST /api/devices/register` | `api.devices.register` | `DeviceAuthApiController::register` | `{success, token, device, table, expires_at, ip_used, broadcasting}` | ✅ aligned |
| `Device.ts:298` authenticate | `GET\|POST /api/devices/login` | `api.devices.login` | `DeviceAuthApiController::authenticate` | same shape as register | ✅ aligned |
| `Device.ts:269` refresh | `POST /api/devices/refresh` | `api.devices.refresh` | `DeviceAuthApiController::refresh` | same shape, **omits `ip_used`** | ✅ aligned (field is optional-read in `applyAuthPayload`) |
| `Device.ts` logout | `POST /api/devices/logout` | `api.devices.logout` | `DeviceAuthApiController::logout` | `{message}` only — **no `success` key** | ⚠️ inconsistent shape, but no caller reads `.success` on logout |
| print-bridge | `GET /api/device/lookup-by-ip` | `api.device.lookup-by-ip` | `DeviceAuthApiController::lookupByIp` | `{found, device:{device_id,auth_token,printer_name,bluetooth_address}, ip_used, broadcasting}` | ✅ aligned (not called by PWA itself) |
| `settings.vue:375` verifyToken | `GET /api/token/verify` | inline closure, `api.php:140-172` | n/a (closure, duplicates dead `DeviceAuthApiController::verifyToken()`) | `{valid, message?, device?, created_at?, expires_at?}` — **no `success` key, ever** | ❌ **misaligned** — see Finding A below |
| `settings.vue:509` update | `PUT /api/devices/{id}` | `api.devices.update` | `DeviceApiController::update` | bare `DeviceResource` (no envelope) | ✅ aligned (resource auto-wraps) |
| `settings.vue:281,296` table | `GET\|POST /api/device/table` | `device.table` | `DeviceApiController::getTableByIp` | `{success, device, table, ip_used}` via `ApiResponse::success` | ✅ aligned |
| `Order.ts:506` submitOrder | `POST /api/devices/create-order` | `api.devices.create.order` | `DeviceOrderApiController::__invoke` | 201 `{success, order: DeviceOrderResource}`; 409 `{success:false, message, order}`; 422/503/500 `{success:false, message, code?}` | ✅ aligned |
| `Order.ts:685` / `usePollingFallback.ts:51` status check | `GET /api/device-order/by-order-id/{orderId}` | (unnamed) | `OrderApiController::showByExternalId` | `{success, order: DeviceOrderResource}` | ✅ aligned |
| `Order.ts:729` submitRefill | `POST /api/order/{orderId}/refill` | `api.order.refill` | `OrderApiController::refill` | 200 `{success, order: DeviceOrderResource, created}`; errors `{success:false, message, error?, code?}` | ✅ aligned — payload sends only `{menu_id, quantity}` per item; controller's `name`-based fallback lookup is dead code |
| (not called by PWA) | `POST /api/order/{orderId}/printed` | `api.order.printed` | `OrderApiController::markPrinted` | `{success, message?, data?}` | n/a — print-bridge only |
| (not called by PWA) | `GET /api/order/{orderId}/print` | (unnamed) | `OrderApiController::print` | `{order:{...}, tablename, items}` — **no `success` envelope at all** | ⚠️ print-bridge only, third distinct envelope style in one controller |
| `useDeviceAuth.ts` (unused?) | n/a | n/a | `DeviceAuthApiController::verifyToken()` | same `{valid,...}` shape as the closure | ❌ dead controller method — route never points here (closure at `api.php:140` wins) |
| `packages()` callers (PWA package browsing) | `GET /api/v2/tablet/packages` | `api.v2.tablet.packages` | `TabletApiController::packages` | `{success, message, data:[Package...]}` | ⚠️ structurally aligned, data-incomplete — see §2/§9 (Package Builder field gap vs. `MenuResource`) |
| `categoryMenus()` callers | `GET /api/v2/tablet/categories/{slug}/menus` | `api.v2.tablet.category.menus` | `TabletApiController::categoryMenus` | `{success, message, data: MenuResource[]}` | ✅ shape aligned; **as of `e75f617` (2026-06-28) content is pivot-driven for admin-curated categories** (§3.3) — corrects this audit's original note, which described the pre-fix bypass |

### Finding A (code-confirmed): `settings.vue:380` checks a field that can never exist

`settings.vue:379-385`:
```js
// Check if backend returned success: false (authentication failed)
if (response.data?.success === false) {
    tokenStatus.value = "invalid"
    tokenMessage.value = response.data?.debug?.message || "Authentication failed on server"
```
The backend's `/token/verify` (the inline closure at `routes/api.php:140-172`, which actually
serves this route — `DeviceAuthApiController::verifyToken()` is dead code, never wired to a
route) only ever returns `{valid: bool, ...}`. It has no `success` key in any code path.
Failure cases return HTTP 400/401, which axios throws on and which are caught by the separate
`catch` block (lines 393-403), not this branch. So `response.data?.success === false` is never
true on a 200 response — unreachable dead code, not an active bug, but a real naming-convention
drift (`success` vs `valid`).

### Other shape inconsistencies inside `DeviceAuthApiController` (not PWA-caused)
- `logout()` returns `{message}` only — breaks the `{success, ...}` convention every sibling
  method follows.
- `refresh()` omits `ip_used`, present in `register()`/`authenticate()`.
- `OrderApiController::print()` (print-bridge endpoint) returns no `success`/`message`
  envelope at all — a third, distinct convention inside one app.
- `DeviceAuthApiController::verifyToken()` (the class method) is unreferenced — the route table
  points at an inline closure with the same shape instead.

### Net assessment

The vast majority of PWA↔Nexus API calls are correctly wired — every device-auth,
order-creation, refill, and status-polling call site matches its backend response shape
field-for-field, including edge cases (409 resumable order, 503 no-POS-session, terminal-status
rejection). The concrete, demonstrable misalignments are narrower than a blanket "misaligned
data" framing would suggest:
- **Finding A** (`settings.vue:380` checking `.success` on a `/token/verify` response that only
  ever has `.valid`) — cosmetic/dead-code, not user-facing breakage.
- **Convention drift inside the backend itself** (`logout()`'s missing `success` key,
  `print()`'s missing envelope entirely, dead `verifyToken()` controller method) — not
  PWA-caused.
- **The remaining higher-impact gap is the Package Builder data-completeness gap** (§2/§9):
  `packages()`/`packageDetails()` return a thin shape missing most `MenuResource` fields
  (`img_url`, `price`, `tax`, `receipt_name`, etc.) that `categoryMenus()` already returns via
  the same resource. The `categoryMenus()` pivot-bypass gap that this audit originally flagged
  alongside it has since been fixed (`e75f617`) and is corrected above.

This audit is read-only — no code changes made in this pass.

---

## Appendix B: End-to-End Order Transaction Sweep (registration → completion)

A full-cycle sweep of every feature, workflow, and API call from device registration through
order completion — what data is required at each step, every broadcast/event, and the exact
money calculation formula. Every claim below is sourced from a directly-read file.

### 1. Registration → Auth → Table Assignment
- `POST /api/devices/register` → `DeviceAuthApiController::register` — DB transaction, claims
  a `security_code`, issues a 30-day Sanctum token, resolves `branch_id`/table. Returns
  `{success, token, device, table, expires_at, ip_used, broadcasting}` (`broadcasting` is
  `BroadcastConfig::clientPayload()`).
- `Device` model: `device_uuid` is immutable after creation — `booted()` hooks assign it once
  on `creating` and throw if a later `updating` call tries to change it. `deleting` cascades
  token deletion.
- `GET/POST /api/devices/login` → `authenticate()` — IP-based device lookup, rejects devices
  that still carry an unclaimed `security_code`.
- `GET /api/device/table` / `POST /api/device/table` → `DeviceApiController::getTableByIp` →
  `{success, device, table, ip_used}`.
- `BroadcastConfig::clientPayload()`: returns `{driver:'reverb', key, host, port, scheme,
  auth_endpoint:'/broadcasting/auth'}`; explicit code comment "NEVER include: secret, app_id
  (server-only values)"; returns `[]` if no Reverb key configured.

### 2. Session (POS-shared, not per-device)
- `KryptonSession` (`pos` DB connection, table `sessions`): open = `date_time_closed IS NULL`.
  **One session is shared across every tablet in a branch** — device isolation happens at the
  order level (`device_id` on `DeviceOrder`), not at the session level.
- `session.pos` middleware (`CheckSessionIsOpened`) gates order-sensitive routes only; returns
  HTTP 503 if `KryptonContextService::getData()['session_id']` is empty or a
  `SessionNotFoundException` is thrown. Not applied to login/health/config/token/heartbeat/
  print-bridge/session routes.
- `GET /api/session/latest` → `SessionApiController::current`. `SessionApiController::forceEnd()`
  cross-checks open orders against `DB::connection('pos')->table('orders')` before allowing
  finalization (blocks unless `force=true` and POS genuinely shows orders still open), then
  dispatches `OrderCompleted`/`OrderVoided`/`OrderStatusUpdated` per affected order before
  `doSessionReset()`.
- `KryptonContextService::getData()` sources `session_id`/`employee_log_id`/`tax_set_id`/
  `price_level_id` from POS tables (`sessions`, `employee_logs`, `revenues`,
  `terminal_sessions`) — never from Nexus's own tables.

### 3. Menu / Package Browsing
- `GET /api/v2/tablet/packages`, `GET /api/v2/tablet/packages/{id}`, `GET
  /api/v2/tablet/categories`, `GET /api/v2/tablet/categories/{slug}/menus`, `GET
  /api/v2/tablet/meat-categories` — all via `TabletApiController`, all wrapped in
  `ApiResponse::success()`'s `{success, message, data}` envelope. Exact shapes are documented
  in §6 above; the package-field-completeness gap is documented in §9 (G-series) rather than
  repeated here.

### 4. Order Creation (with full calculation trace)
- `POST /api/devices/create-order` → `DeviceOrderApiController::__invoke`. Tablet sends
  intent-only: `{guest_count, package_id, items:[{menu_id, quantity}]}` (CLAUDE.md hard rule).
  `expandIntentPayload()` is the sole translator from this intent shape into the internal
  package+modifier structure the backend persists.
- Idempotency: client sends `X-Idempotency-Key` (persisted client-side in `sessionStorage` as
  `woosoo_order_idem_key`). Server: `Cache::add()` creates a 30-second in-flight lock, plus a
  24-hour response-replay cache — a retried request with the same key returns the original
  response rather than double-creating an order.
- Guards: rejects if the device already has a PENDING/CONFIRMED order (409, includes the
  existing `order` in the body so the PWA can resume); requires an open POS session
  (`session.pos` middleware, 503 `SESSION_NOT_FOUND` otherwise).
- Persistence: `OrderService::processOrder()` and `CreateOrderedMenu::handle()` confirm the
  package+modifier expansion (top-level package item + nested modifier rows from
  `items[].modifiers[]`) and call the POS stored procedure `CALL create_ordered_menu(...)`
  (stubbed in test environments).
- **Calculation formula** (identical at every money-touching call site):
  - `itemTotal = round(unitPrice * quantity, 2)`
  - `taxAmount = round(itemTotal * taxRate, 2)`, `taxRate = config('api.krypton.tax_rate', 0.10)`
  - `subTotal = itemTotal + taxAmount`
  - Order-level `subtotal`/`tax` = sums of the per-item values; `total = subtotal + tax`
  - `unitPrice` is always read from `KryptonMenu::find($menuId)->price` — POS-authoritative,
    never client-supplied.
- **Server-side totals enforcement**: if the client submits its own `subtotal`/`tax`/
  `total_amount`, the server always overwrites them with its own calculation. A mismatch only
  triggers `Log::warning('Client totals drift detected...')` — the request is **not** rejected.
- `DeviceOrder` model: `$guarded = ['items','meta']` blocks the legacy JSON columns from
  mass-assignment; `setStatusAttribute()` enforces `OrderStatus::canTransitionTo()` on existing
  models (throws on an invalid transition) but skips validation during `creating`; `boot()`
  auto-resolves `branch_id` and generates `order_uuid`/`order_number`
  (`'ORD-{YYYYMMDD}-{UUID last 6 chars}'`).
- `OrderStatus` enum: 9 values — `pending, confirmed, in_progress, ready, served`
  (non-terminal, all must be covered by tablet active-order recovery per CLAUDE.md) plus
  `completed, cancelled, voided, archived` (terminal). **`archived` is defined and checked
  against in scopes/comparisons but never actually assigned by any live code path** (confirmed
  via exhaustive grep). The frontend's narrower terminal-status checks are therefore currently
  harmless — a latent inconsistency against `Order.ts`'s `TERMINAL_ORDER_STATUSES` constant
  (which does include `archived`), not an active bug.
- Success: 201 `{success, order: DeviceOrderResource}`, broadcasts `OrderCreated`.

### 5. Refill
- `POST /api/order/{orderId}/refill`, body `{items:[{menu_id, quantity}], client_submission_id}`.
- `OrderApiController::refill` runs a durable 5-state machine — `PROCESSING → POS_CREATED →
  MIRRORED → PRINT_EVENT_CREATED → COMPLETED` — via `DurableRefillGuard`, replay-safe
  (dispatches `PrintRefill`/`OrderStatusUpdated` only on first submission of a given
  `client_submission_id`). Rejects terminal-status orders with 409 `ORDER_NOT_ACTIVE`.
- Same `unitPrice`/tax/subtotal formula as order creation, sourced the same way.
- `DeviceOrderItems` model: pricing fields (`price`,`subtotal`,`tax`,`discount`,`total`) all
  `decimal:4`; `status` cast to `ItemStatus` enum.

### 6. Service Requests — confirmed data-integrity gap
- `POST` to the service-request endpoint → `ServiceRequestApiController::store()` — validates
  via `StoreServiceRequest`, checks device ownership of the order (403 on mismatch), creates
  inside `DB::transaction`, broadcasts `ServiceRequestNotification` inside
  `DB::afterCommit(fn () => broadcast(new ServiceRequestNotification($serviceRequest))->toOthers())`.
  All other CRUD methods on this controller are empty stubs.
- `StoreServiceRequest::rules()`:
  ```php
  return [
      'table_service_id' => ['required', 'integer'],
      'order_id' => ['required', 'exists:device_orders,order_id', 'integer'],
  ];
  ```
  `table_service_id` has **no `exists:table_services,id` check.** `menu.vue`'s
  `getServiceTypeId()` hardcodes a client-side map (`{clean:1, water:2, billing:3, support:4}`)
  and is never cross-checked against real `table_services` rows, despite
  `TableServiceApiController::index()` existing and able to supply them. A wrong client-side
  guess would silently create a service request with a mismatched/nonexistent service type —
  no validation error surfaced anywhere.

### 7. Broadcast Channels — full cross-check
- `routes/channels.php`: exactly 5 registered channels — `device.{deviceId}`,
  `orders.{orderId}`, `service-requests.{orderId}`, `admin.orders`, `admin.service-requests`.
  Each has a closure-based authorization check (device-ownership query, or `$user->is_admin`
  for the two admin channels).
- **Confirmed dispatched somewhere in `app/`**: `OrderCreated`, `OrderStatusUpdated`,
  `OrderCompleted`, `OrderCancelled`, `OrderVoided`, `PaymentCompleted`, `PrintOrder`,
  `PrintRefill`, `SessionReset`, `AppControlEvent`, `PackageUpdated` (from
  `PackageController::broadcastPackageUpdated()`), `ServiceRequestNotification` (via
  `broadcast(new X())->toOthers()`, not `::dispatch()`).
  **No dispatch call site found anywhere in `app/`** (dead/defined-but-unused):
  `Kds\ItemToggled`, `Menu\MenuUpdated`, `Menu\PackageUpdated` (distinct from the dispatched
  `Order\...`-namespace `PackageUpdated` — two different classes share the tail name),
  `Order\OrderDetailsUpdated`.
- `Order\OrderDetailsUpdated` is broadcast-defined **and** frontend-listened
  (`useBroadcasts.ts` subscribes to `.order.details.updated`) but has zero backend dispatch
  sites — a dead listener on the frontend, not a missing-listener bug.
- `NEX-CASE-013`: a code comment in `OrderStatusUpdated::broadcastOn()` documents a
  previously-fixed bug where the event only broadcast on `device.{device_id}`, silently
  dropping status updates for tablets subscribed only to `orders.{orderId}`. Now broadcasts on
  both channels — historical evidence of this exact channel-mismatch bug class recurring.

### 8. KDS State Machine
- `KdsController::advance()` auto-promotes `pending→confirmed`, then `confirmed→in_progress`,
  and treats `in_progress→ready→served` as one atomic action gated on every item having
  `done=true`. `recall()` allows `served→in_progress` only, capped at `MAX_RECALLS=5`. `void()`
  allows any non-terminal status → `voided`, with a best-effort (non-blocking) POS void call.
  All transitions wrapped in `DB::transaction()` + `lockForUpdate()` to prevent
  concurrent-write races; broadcasts dispatched via `app(OrderBroadcaster::class)` after
  relations are refreshed/reloaded.

### 9. Money / Calculation — single source of truth
- Identical formula at every money-touching call site: `itemTotal = round(unitPrice*qty,2)` →
  `taxAmount = round(itemTotal*taxRate,2)` → `subTotal = itemTotal+taxAmount` → order totals
  are sums of item-level values. `unitPrice` is always `KryptonMenu::find($menuId)->price` —
  POS is the only source for price; Nexus never originates a price used in a real calculation.
  Client-submitted totals are logged-but-overridden, never trusted, never rejected outright.

### Net findings carried forward
1. `archived` `OrderStatus` is dead-but-defined — a known latent gap, not a bug to fix in this
   task.
2. Service-request `table_service_id` has no FK-style validation against `table_services` —
   confirmed, real, currently silent-failure-only.
3. `Order\OrderDetailsUpdated` is a dead broadcast (defined + frontend-subscribed, never
   dispatched).
4. `ServiceRequestNotification` IS dispatched (via `broadcast(new X())->toOthers()`), correcting
   any grep-based check that searches only for `::dispatch()` syntax.

This sweep is read-only — no code changes made in this pass.
