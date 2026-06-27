# Woosoo Nexus — Packages & Tablet Menu Categories: Gap Analysis & Implementation Spec

> **Status:** Approved spec · **Audience:** ranpo-backend + chuya-frontend specialists
> (execute the ordered plan in §d sequentially after approval).
> **Date:** 2026-06-21

## Context

Two features depend on menu data that lives in the **krypton-woosoo** POS database, reached
from Nexus over a secondary DB connection: **Packages** (Nexus-native dining packages whose
meat slots reference krypton menus) and **Tablet Menu Categories** (admin groupings of
krypton menus surfaced to the tablet PWA). This spec audits backend + HQ Console frontend
for both, identifies gaps, and gives an ordered implementation plan.

**Two corrections to the original brief, established by the audit:**
1. **The Krypton connection is named `pos`** (`config/database.php` → `DB_POS_DATABASE`,
   default `krypton_woosoo`), used by `App\Models\Krypton\*` (`$connection = 'pos'`),
   `MenuRepository` (stored-proc calls on `pos`), and `TabletCategoryController` raw queries.
   A second connection literally named **`krypton_woosoo`** also exists but is only a
   *test-safe sqlite alias* (`config/database.php:97-104`). Code should standardize on `pos`.
2. **There is no Nuxt app.** The "HQ Console frontend" is the **Inertia + Vue 3** pages in
   *this* repo (`resources/js/pages/`). Audited accordingly.

All menu FKs correctly store **`krypton_menu_id` as an integer** (krypton-woosoo `menus.id`) —
no name/slug references found. The `pos` connection is read-only in practice (only `SELECT` /
`CALL get_*` procedures); no writes proposed to it.

---

## (a) Findings — Backend

### Dual-DB wiring — **OK**
- `pos` connection + `App\Models\Krypton\Menu` (`$connection='pos'`, table `menus`). Reads only.
- `MenuRepository` (`app/Repositories/Krypton/MenuRepository.php`) wraps `CALL get_*` procs on `pos`.
- Cross-connection image join handled via `Menu::attachUploadedImages()` (MenuImage on `mysql`).

### Packages

| Artifact | Path | Status |
|---|---|---|
| `packages` table (consolidated) | `…203513_consolidate_packages_schema.php`, `…000001_packages_meats_only_most_popular.php` | **complete** |
| `package_allowed_menus` table | `…203513_consolidate_packages_schema.php:27` | **complete** |
| `Package` model | `app/Models/Package.php` | complete — but **dead `krypton_menu_id`** in `$fillable`/`$casts` (never set; legacy) |
| `PackageAllowedMenu` model | `app/Models/PackageAllowedMenu.php` | complete |
| `PackageController` (admin) | index/store/update/destroy/syncAllowedMenus/setMostPopular | **complete** |
| `StorePackageRequest` / `UpdatePackageRequest` | `app/Http/Requests/Admin/` | complete — but **no referential check** that `krypton_menu_id` exists in `pos.menus` |
| `PackageResource` | `app/Http/Resources/PackageResource.php` | present |
| Tablet API: `packages`, `packageDetails` | `TabletApiController.php:60,232` | works off the **new `packages` table** (docblock saying "IDs 46/47/48" is stale) |
| Cache + realtime | `PACKAGES_CACHE_KEY` busted + `PackageUpdated` broadcast on every admin mutation | **complete** |
| Legacy set-meal path | `Menu::getComputedModifiersAttribute` / `getPackagesWithModifiers` (hardcoded IDs 46/47/48, P/B/C codes) | **superseded / likely dead** — verify consumers |

### Tablet Menu Categories

| Artifact | Path | Status |
|---|---|---|
| `tablet_categories` + `tablet_category_menu` pivot | `…000002_create_tablet_categories_table.php` | **complete** (FK, unique `(category,menu)`, indexes) |
| `TabletCategory` / `TabletCategoryMenu` models | `app/Models/` | complete (slug auto-gen) |
| `TabletCategoryController` (admin) | index/store/update/destroy/syncMenus/attachMenus/detachMenu/toggleFeatured/updateMenuOrder/reorder | **complete** (inline validation, no FormRequests) |
| Tablet API: `categories` | `TabletApiController.php:187` | returns DB categories, hardcoded fallback — OK |
| Tablet API: `categoryMenus({slug})` | `TabletApiController.php:319` | **BROKEN CONTRACT** — see G1 |
| Realtime/cache bust on category change | — | **none** (unlike Packages) |

---

## (b) Findings — HQ Console (Inertia/Vue) frontend

### `resources/js/pages/Packages/Index.vue` — **near-complete**
- Full CRUD (create/edit dialog), most-popular toggle, delete confirm, amber styling.
- "Manage Meats" dialog = the krypton menu picker (grouped Pork/Beef/Chicken via `receipt_name`,
  search, qty per meat). Calls `packages.sync-menus`.
- **Gap:** picker only sets `quantity_limit` + membership. `extra_price`, `is_required`,
  `is_default`, `meat_category_code` are hardcoded defaults in `SyncEntry` though the
  schema + API + FormRequests all support them (G3).

### `resources/js/pages/tablet-categories/IndexTabletCategories.vue` — **near-complete**
- Category CRUD, reorder (`tablet-categories.reorder`), attach dialog (krypton picker grouped
  Category→Group, select-all, `receipt_name` badges), detach, featured toggle — all wired.
- **Gaps:** `is_featured` is toggled/stored but **never surfaced by any tablet API** (G2);
  `tablet-categories.sync-menus` route+method exist but the UI uses attach/detach instead (G8, dead).

No missing create/edit forms; both krypton menu pickers exist.

---

## (c) Prioritised gap list

| ID | Pri | Gap | Where |
|---|---|---|---|
| **G1** | **P0** | `categoryMenus($slug)` **ignores the `tablet_category_menu` pivot** — it only resolves a hardcoded `meats\|sides\|drinks\|desserts` → fixed POS group-ID map and returns **422 for any admin-created slug**. The admin "attach menus to category" feature has **zero effect on the tablet**. | `TabletApiController.php:319-358` |
| **G2** | P1 | `is_featured` stored + admin-toggle wired, but no API emits it → featured is end-to-end dead. | `TabletApiController` `categories`/`categoryMenus` |
| **G3** | P1 | Packages meat picker can't set `extra_price` / `is_required` / `is_default` / `meat_category_code` (schema + API support them). | `Packages/Index.vue` (`SyncEntry`) |
| **G4** | P1 | No validation that `krypton_menu_id` exists in `pos.menus` on attach/sync/store → orphan refs storable. | `StorePackageRequest`, `UpdatePackageRequest`, `TabletCategoryController` |
| **G5** | P2 | Legacy set-meal code (IDs 46/47/48) appears superseded by native packages — confirm no consumers, then remove. | `Menu::getComputedModifiersAttribute`, `getPackagesWithModifiers` |
| **G6** | P2 | Dead `krypton_menu_id` column on `packages` (nullable, never written). | `Package.php`, schema |
| **G7** | P2 | `packageDetails` always returns empty `side`/`dessert`/`drinks` buckets (packages are meats-only). | `TabletApiController.php:292-297` |
| **G8** | P2 | `tablet-categories.sync-menus` route+method unused by UI. | `routes/web.php:104`, `TabletCategoryController::syncMenus` |
| **G9** | P2 | No realtime/cache invalidation on tablet-category mutations (Packages broadcast `PackageUpdated`; categories broadcast nothing). | `TabletCategoryController` |
| **G10** | P2 | Connection-name duality (`pos` vs `krypton_woosoo` alias) risks model/test drift; categories controller uses inline validation vs Packages' FormRequests. | `config/database.php`, controllers |

---

## (d) Ordered implementation plan

Each step is scoped to one file/concern, executable sequentially.

1. **[P0 · G1] Make the pivot authoritative in `categoryMenus`.** Rewrite
   `TabletApiController::categoryMenus($slug)`: resolve `TabletCategory::where('slug',$slug)->first()`;
   if found, read its `menuPivots` (ordered `is_featured desc, sort_order`), collect
   `krypton_menu_id`s, fetch via `Menu::whereIn('id',$ids)` on `pos`, `attachUploadedImages`,
   return `MenuResource::collection` preserving pivot order + a `is_featured` flag. Keep the
   hardcoded `meats|sides|drinks|desserts` map **only** as a fallback when the slug has no DB
   category (or drop it if the four are migrated to real categories). *File:* `TabletApiController.php`.
2. **[P1 · G2] Surface `is_featured`** in the step-1 payload (already covered if you add the flag
   + featured-first ordering). Confirm `categories()` returns enough for the PWA. *File:* `TabletApiController.php`.
3. **[P1 · G4] Cross-DB existence rule.** Add a `KryptonMenuExists` validation rule
   (`Menu::where('id',$value)->exists()` on `pos`, tolerant when POS offline) and apply to
   `allowed_menus.*.krypton_menu_id` (Store/Update requests) and `menu_ids.*`
   (`TabletCategoryController::attachMenus`/`syncMenus`). *Files:* new Rule, the two requests, controller.
4. **[P1 · G3] Expand the Packages meat picker** to edit `extra_price`, `is_required`,
   `is_default` (and optionally `meat_category_code`) per selected meat; send them in the
   existing `packages.sync-menus` payload (already validated). *File:* `Packages/Index.vue`.
5. **[P2 · G9] Broadcast on category change.** Bust a categories cache + broadcast a
   `TabletCategoriesUpdated` event from `store/update/destroy/attachMenus/detachMenu/syncMenus/toggleFeatured/updateMenuOrder/reorder`, mirroring `PackageController::broadcastPackageUpdated()`. *File:* `TabletCategoryController.php` (+ new event).
6. **[P2 · G7] Trim `packageDetails`** to a meats-only shape (drop empty side/dessert/drinks
   buckets) or document them as reserved. *File:* `TabletApiController.php`.
7. **[P2 · G6] Drop dead `krypton_menu_id`** from `Package` `$fillable`/`$casts`; add a migration
   to drop the column once confirmed unused. *Files:* `Package.php`, new migration.
8. **[P2 · G8] Remove unused `sync-menus`** category route + method (or wire it). *Files:* `routes/web.php`, `TabletCategoryController.php`.
9. **[P2 · G5] Audit + retire legacy set-meal code** (46/47/48). Grep consumers of
   `getComputedModifiersAttribute`/`getPackagesWithModifiers`/`computed_modifiers`; remove if dead. *Files:* `Menu.php` + callers.
10. **[P2 · G10] Housekeeping.** Document the `pos` vs `krypton_woosoo` connection roles in
    `config/database.php`; optionally convert `TabletCategoryController` inline validation to FormRequests for parity.

### Verification per step
- **Backend:** `php artisan test --compact` for affected feature tests (API contract tests for
  `tablet/categories` + `categoryMenus` must assert pivot-driven results — add one for G1).
  `vendor/bin/pint --dirty` after PHP edits.
- **Frontend:** `npm run build` + `npm run typecheck` + `npm run lint:check` after `.vue` edits.
- **End-to-end (G1):** seed a tablet category + attach 2 krypton menus → `GET /api/v2/tablet/categories`
  then `GET /api/v2/tablet/categories/{slug}/menus` returns exactly those menus in pivot order
  (verified on the WSL dev DB).

### Out of scope (unchanged)
Tablet PWA rendering, print bridge/Flutter, krypton-woosoo itself (read-only reference),
any feature outside Packages and Tablet Menu Categories.
