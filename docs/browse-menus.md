# Browse Menu API Reference

**File:** `app/Http/Controllers/Api/V1/BrowseMenuApiController.php`

Base URL used in examples: `http://localhost:8000`

---

## Controller Overview

Purpose: read-only API endpoints returning menus, modifier groups and modifiers for device and client apps. Uses `MenuRepository`, stored procedures, and two resource transformers: `MenuResource` and `MenuModifierResource`.

Key files:
- Controller: `app/Http/Controllers/Api/V1/BrowseMenuApiController.php`
- Resources: `app/Http/Resources/MenuResource.php`, `app/Http/Resources/MenuModifierResource.php`
- Routes: `routes/api.php`

---

## Resource Schemas (exact keys)

### MenuResource (object)
- `id` : integer
- `group` : string|null
- `category` : string|null
- `course` : string|null
- `name` : string
- `kitchen_name` : string|null
- `receipt_name` : string|null
- `price` : string (formatted with two decimals, e.g. `"12.50"`)
- `is_taxable` : boolean|null
- `is_available` : boolean|null
- `is_discountable` : boolean|null
- `img_url` : string (URL; falls back to placeholder asset)
- `tax` : object|null (present only when `tax` relation is loaded; shape from `TaxResource`)
- `tax_amount` : number|null
- `modifiers` : array of `MenuModifierResource` objects (empty array if none)

Notes:
- `price` is a formatted string (clients must parse to numeric for math).
- `modifiers` is always transformed by `MenuModifierResource::collection(...)` in the resource; controllers can attach raw model collections and the resource will transform them.

### MenuModifierResource (object)
- `id` : integer
- `menu_group_id` : integer|null
- `group` : string|null
- `name` : string
- `category` : string|null
- `kitchen_name` : string|null
- `receipt_name` : string|null
- `price` : string (formatted, two decimals)
- `description` : string|null
- `is_available` : boolean|null
- `is_modifier` : boolean|null
- `is_modifier_only` : boolean|null
- `img_url` : string (URL; placeholder fallback)

---

## Route Mapping (from `routes/api.php`)

- `GET /menus` → `BrowseMenuApiController@getMenus`
- `GET /menus/with-modifiers` → `BrowseMenuApiController@getMenusWithModifiers`
- `GET /menus/modifier-groups` → `BrowseMenuApiController@getAllModifierGroups`
- `GET /menus/modifiers` → `BrowseMenuApiController@getMenuModifiers`
- `GET /menus/modifier-groups/{id}/modifiers` → `BrowseMenuApiController@getMenuModifiersByGroup` (route exists; handler missing in controller file)
- `GET /menus/course` → `BrowseMenuApiController@getMenusByCourse`
- `GET /menus/group` → `BrowseMenuApiController@getMenusByGroup`
- `GET /menus/category` → `BrowseMenuApiController@getMenusByCategory`
- `GET /menus/bundle` → `MenuBundleController` (separate controller)

---

## Endpoint Details, parameters and responses

Each endpoint includes a short description, validation, and representative response using the exact resource keys. Use `http://localhost:8000` for cURL examples.

### GET /menus
- Controller: `getMenus(Request $request)`
- Query params:
  - `menu_id` (optional, integer)
- Behavior: calls `MenuRepository::getMenus()`, eager-loads `modifiers` and `image`, returns `MenuResource::collection(...)`.
- Response (200): array of `MenuResource` objects.

Example cURL:
```bash
curl -X GET "http://localhost:8000/api/menus"
```

Example item (MenuResource):
```json
{
  "id": 123,
  "group": "Mains",
  "category": "Beef",
  "course": "Main",
  "name": "Grilled Steak",
  "kitchen_name": "Steak",
  "receipt_name": "STK",
  "price": "25.00",
  "is_taxable": true,
  "is_available": true,
  "is_discountable": false,
  "img_url": "http://localhost:8000/images/menu-placeholder/1.jpg",
  "tax": null,
  "tax_amount": 2.5,
  "modifiers": []
}
```

---

### GET /menus/with-modifiers
- Controller: `getMenusWithModifiers()`
- Query params: none
- Behavior: uses a hard-coded mapping for parent menu ids (46,47,48) and filters modifiers by `receipt_name`. Attaches a `modifiers` collection to the parent menu model then returns `MenuResource::collection(...)` (which transforms modifiers via `MenuModifierResource`).
- Response (200): array of `MenuResource` objects (each with `modifiers` filled).
- On exception: returns `{ "error": "Failed to retrieve object-based grouped modifiers." }` with HTTP 500.

Example cURL:
```bash
curl -X GET "http://localhost:8000/api/menus/with-modifiers"
```

Example single menu with attached modifiers (simplified):
```json
{
  "id": 46,
  "group": "Packages",
  "category": "Main",
  "course": "Main",
  "name": "Family Meal",
  "kitchen_name": "Kitchen A",
  "receipt_name": "FM",
  "price": "45.00",
  "is_taxable": false,
  "is_available": true,
  "is_discountable": true,
  "img_url": "http://localhost:8000/images/menu-placeholder/1.jpg",
  "tax": null,
  "tax_amount": 0,
  "modifiers": [
    {
      "id": 456,
      "menu_group_id": 10,
      "group": "PORK",
      "name": "Bacon",
      "category": "Toppings",
      "kitchen_name": "Bacon",
      "receipt_name": "B",
      "price": "1.50",
      "description": "Crispy bacon bits",
      "is_available": true,
      "is_modifier": true,
      "is_modifier_only": false,
      "img_url": "http://localhost:8000/images/menu-placeholder/1.jpg"
    }
  ]
}
```

---

### GET /menus/modifier-groups
- Controller: `getAllModifierGroups(Request $request)`
- Query params:
  - `modifiers` (optional, boolean)
- Behavior: returns `MenuRepository::getAllModifierGroups()` raw output. The repository returns an array/collection of modifier-group records with the following fields (example output produced from the local DB):

- Exact output fields for each modifier-group record:
  - `id` : integer (group identifier)
  - `name` : string (group name, e.g. "BEEF", "PORK")
  - `is_available` : boolean
  - `index` : integer
  - `display_in_pos` : integer (flag)
  - `menu_group_id` : integer (id of the related menu group)
  - `menu_group_name` : string (name of the related menu group)
  - `menu_tax_type_id` : integer|null
  - `menu_category_id` : integer|null
  - `menu_course_type_id` : integer|null
  - `is_discountable` : boolean
  - `is_taxable` : boolean

- Response (200): array of objects with the fields above.

Example cURL:
```bash
curl -X GET "http://localhost:8000/api/menus/modifier-groups?modifiers=1"
```

Example response (excerpt):
```json
[
  {
    "id": 2,
    "name": "BEEF",
    "is_available": true,
    "index": 2,
    "display_in_pos": 1,
    "menu_group_id": 36,
    "menu_group_name": "BEEF",
    "menu_tax_type_id": 1,
    "menu_category_id": 1,
    "menu_course_type_id": null,
    "is_discountable": true,
    "is_taxable": true
  },
  {
    "id": 3,
    "name": "CHICKEN",
    "is_available": true,
    "index": 3,
    "display_in_pos": 1,
    "menu_group_id": 37,
    "menu_group_name": "CHICKEN",
    "menu_tax_type_id": 1,
    "menu_category_id": 1,
    "menu_course_type_id": null,
    "is_discountable": true,
    "is_taxable": true
  }
]
```

---

### GET /menus/modifiers
- Controller: `getMenuModifiers()`
- Query params: none
- Behavior: runs stored procedure `CALL get_menu_modifiers()` via `Menu::fromQuery()`, groups results by `group.name` (falls back to `group` string or `'Other'`), then maps each group to `MenuModifierResource::collection()`.
- Response (200): object mapping `groupName` → array of `MenuModifierResource` objects.

Example cURL:
```bash
curl -X GET "http://localhost:8000/api/menus/modifiers"
```

Example response (grouped modifiers):
```json
{
  "PORK": [
    {
      "id": 456,
      "menu_group_id": 10,
      "group": "PORK",
      "name": "Bacon",
      "category": "Toppings",
      "kitchen_name": "Bacon",
      "receipt_name": "B",
      "price": "1.50",
      "description": "Crispy bacon bits",
      "is_available": true,
      "is_modifier": true,
      "is_modifier_only": false,
      "img_url": "http://localhost:8000/images/menu-placeholder/1.jpg"
    }
  ],
  "BEEF": [ /* ... */ ]
}
```

---

### GET /menus/modifier-groups/{id}/modifiers
- Controller: `getMenuModifiersByGroup(int $id)`
- Path param: `id` (integer)
- Behavior: returns `MenuModifierResource::collection(...)` for modifiers belonging to the requested modifier-group id. The controller prefers `MenuRepository::getMenuModifiersByGroup($id)` when available and falls back to `Menu::where('menu_group_id', $id)->get()`.
- Response (200): array of `MenuModifierResource` objects.

---

### GET /menus/course
- Controller: `getMenusByCourse(Request $request)`
- Query params: `course` (required, string)
- Behavior: runs `CALL get_menus_by_course(?)`, plucks ids, returns `MenuResource::collection(...)` for those ids.
- Example cURL:
```bash
curl -G "http://localhost:8000/api/menus/course" --data-urlencode "course=starter"
```

---

### GET /menus/category
- Controller: `getMenusByCategory(Request $request)`
- Query params: `category` (required, string)
- Behavior: runs `CALL get_menus_by_category(?)`, plucks ids, returns `MenuResource::collection(...)`.

Example cURL:
```bash
curl -G "http://localhost:8000/api/menus/category" --data-urlencode "category=beverage"
```

---

### GET /menus/group
- Controller: `getMenusByGroup(Request $request)`
- Query params: `group` (required, string)
- Behavior: uses `MenuRepository::getMenusByGroup($group)` to fetch ids then returns `MenuResource::collection(...)`.

Example cURL:
```bash
curl -G "http://localhost:8000/api/menus/group" --data-urlencode "group=Sides"
```

---

## Implementation notes & gotchas
- `price` values are formatted strings (two decimals). Clients should parse them to numbers for math operations.
- `getMenuModifiers` returns an object (map) keyed by group name → array of modifier resources; not a flat array.
- Stored-procedure call strings are used inconsistently in the controller (`CALL get_menu_modifiers()` vs `CALL get_menu_modifiers`). Standardize to `CALL get_menu_modifiers()`.
- `getMenuModifiersByGroup` route exists but the handler is missing in the controller — implement or remove the route to avoid runtime errors.
- `getAllModifierGroups` returns repository raw output; consider wrapping repository output in a Resource for consistent API shape.
- Error handling is inconsistent across methods (some methods `throw` after logging, others return JSON error objects). Prefer returning JSON error objects with appropriate HTTP status codes for API stability.

---

## Recommended quick actions
- Implement `getMenuModifiersByGroup($id)` in `BrowseMenuApiController.php` or remove the route.
- Standardize stored-proc calls and ensure all DB calls are safe and parameterized.
- Wrap repository outputs with Resources where appropriate to avoid leaking internal fields.
- Normalize error responses (JSON with status codes) across controller methods.

---

## Example usage (curl)
Fetch menus:
```bash
curl -X GET "http://localhost:8000/api/menus"
```
Fetch grouped modifiers:
```bash
curl -X GET "http://localhost:8000/api/menus/modifiers"
```

---

## Where to go next
- I can generate an OpenAPI (YAML) fragment using the exact resource keys if you want a machine-readable spec.
- I can implement the missing `getMenuModifiersByGroup` handler.
- I can open and document `MenuRepository::getAllModifierGroups()` if you want the exact repository output shape included.

---

*Document generated automatically from controller and resource code.*
