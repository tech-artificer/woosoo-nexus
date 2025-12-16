Summary of changes
------------------

This PR makes development-only improvements to ensure the dev-runner health-check reliably detects the frontend dev server and provides a lightweight monitor that supervises and restarts the server during local development and CI runs.

Files changed / added
- `package.json` — set `dev` to run Vite on port 3000 and add `dev:monitor` script
- `vite.config.ts` — configure `server.host` and `server.port` from `VITE_DEV_PORT || 3000`
- `.env.example` — add `APP_URL` and `VITE_DEV_SERVER_URL` pointing to `http://localhost:3000`
- `bin/dev-monitor.sh` — new monitor script to start, wait for, and supervise Vite dev server
- `.github/workflows/tests.yml` — add `APP_URL` / `VITE_DEV_SERVER_URL` envs; optional `START_FRONTEND` logic to start the dev server and `npx wait-on` with 120s timeout; always dump `vite.log` to help debugging
- `docs/DEV_SERVER.md` — usage and debug instructions
- `PR_BODY.md` — this PR body / QA checklist

Rationale
---------

- The existing dev-runner health-check expects the frontend dev server to be reachable on `localhost:3000`, but Vite defaults to `5173`. Aligning the dev script and Vite config with port `3000` makes the health-check deterministic.
- Adding a small `dev-monitor` script makes it easier to run a supervised dev server locally and enables CI to wait for readiness reliably using `npx wait-on`.
- Adding `VITE_DEV_SERVER_URL` centralizes the health-check URL so both CI and local tooling use the same endpoint.

Manual QA checklist (maintainer)
--------------------------------

Run these steps locally to verify:

1. Install frontend deps

```bash
npm ci
```

2. Start the monitored dev server (recommended)

```bash
npm run dev:monitor
```

3. In another terminal, verify the health endpoint

```bash
curl -v http://localhost:3000/
# or
npx wait-on http://localhost:3000 --timeout 5000 && echo 'ok'
```

4. If the dev server fails to start, inspect `vite.log` in the project root:

```bash
tail -n 200 vite.log
```

Override default port

To run on the previous default port (5173) or another port:

```bash
VITE_DEV_PORT=5173 npm run dev
# or for the monitor
VITE_DEV_PORT=5173 npm run dev:monitor
```

How to run the monitor in CI
---------------------------

The `tests.yml` workflow contains optional steps to start and wait for the frontend dev server. Set the `START_FRONTEND` environment variable to `true` in the workflow or in a workflow dispatch to enable starting the frontend and waiting on `VITE_DEV_SERVER_URL` (default `http://localhost:3000`). The workflow will always print `vite.log` to help debugging failures.

How to revert
-------------

All changes are dev-only. To revert:

```bash
git checkout main
git revert <this-branch-commit-range>
# or manually restore files:
# - reset package.json dev script
# - remove bin/dev-monitor.sh
# - revert .github workflow edits
```

Notes
-----

- Keep monitoring the health-check in CI for a few runs and report back; the monitor is intentionally simple and conservative.
- Device testing will still be required to validate the end-to-end flow — keep the monitor running until the user reports stability.
