# Session redirect overlay and service-requests 500 — postmortem

Date: 2026-04-24
Project: `woosoo-nexus`

## What happened

Two separate issues were observed while navigating the admin app:

1. Clicking some sidebar links produced a 500 error.
2. After login, a page-expired / redirect-style dialog appeared, and the redirected page seemed to render inside that overlay.

These looked related at first, but the investigation showed they are different failure modes that can happen together.

## Confirmed root cause for the sidebar 500

The service-requests admin route (`/service-requests`) was failing in `App\Http\Controllers\Admin\ServiceRequestController@index` because it queried a `status` column that does not exist in the live `service_requests` table.

Evidence:
- Laravel log showed `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'status' in 'where clause'`
- The controller applied `where('status', ...)` and `active()` scopes unconditionally
- The model and frontend expected richer service-request fields than the live schema actually had
- The migration history showed the original `service_requests` table was created with only `id` and timestamps, then only a few foreign-key-style columns were added later

### Fix applied

- Made `ServiceRequestController@index` schema-aware so it only filters by columns that actually exist.
- Added a backfill migration to create the missing columns expected by the app:
  - `status`
  - `priority`
  - `description`
  - `acknowledged_at`
  - `acknowledged_by`
  - `completed_by`
  - `assigned_device_id`
- Expanded `ServiceRequest` and `ServiceRequestResource` to expose the fields the UI already expects.
- Added a regression test to confirm the service requests page opens successfully for an admin.

### Verification

- `php artisan test --filter=ServiceRequestAdminTest` passed.

## Likely cause of the login redirect overlay

The login/session behavior is strongly suggestive of an environment/origin mismatch rather than a Vue dialog bug.

Evidence:
- Browser logs showed requests originating from `https://127.0.0.1` while assets were being loaded from `https://192.168.100.7`
- That mismatch can break cookies, CSRF, and Inertia asset/session continuity in subtle ways
- The app’s origin is driven by `PUBLIC_HOST` / `APP_URL` in `App\Support\PublicOrigin`
- `resources/views/app.blade.php` injects `asset('')` into `asset-base-url`, so a host mismatch affects the frontend runtime too
- `config/session.php` is configured to rely on environment values, so a wrong or missing session setup can make redirects feel like the page has expired

### What to check if this returns

- Ensure the browser uses the same host consistently for login and admin pages
- Verify `APP_URL`, `PUBLIC_HOST`, `SESSION_DOMAIN`, and `SESSION_SECURE_COOKIE`
- Confirm the session driver is set correctly in the live environment
- Clear browser cookies if the app host changed during development

## Why this was confusing

The redirect overlay made the failure look like a front-end modal problem. In reality:
- the 500 came from backend data/schema mismatch
- the dialog-like behavior came from session/origin state drift and redirect handling

So the frontend was mostly reporting the symptom, not the cause.

## Avoidance checklist

When a sidebar navigation or Inertia page looks like it opened "inside a dialog":

1. Check the browser console for host/origin mismatches.
2. Check the Laravel log for SQL exceptions first.
3. Confirm the route exists with `route:list`.
4. Verify the controller does not assume columns that the live DB does not have.
5. Compare the model, resource, and migration history together; do not trust only one of them.
6. Add a regression test for the exact page that failed.

## Practical lesson

When a page works directly by URL but fails through navigation, do not assume the router is wrong. The route may be fine; the page can still 500 because the controller is loading data that the live schema cannot satisfy.

In this case, the real fix was schema alignment plus a safer controller, not axios or sidebar navigation.
