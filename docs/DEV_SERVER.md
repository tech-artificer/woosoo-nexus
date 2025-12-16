# Development Server (Vite) — Run & Debug

This document explains how to run the frontend dev server locally and how to use the provided `dev-monitor` script that supervises the server and ensures the dev-runner health-check succeeds.

Basic steps

1. Install dependencies

```bash
npm ci
```

2. Start the monitored dev server

```bash
npm run dev:monitor
```

This will start `npm run dev` in the background, wait up to 120 seconds for the server to respond at `VITE_DEV_SERVER_URL` (defaults to `http://localhost:3000`), and tail `vite.log` in the foreground. If the dev server process exits unexpectedly, the monitor will attempt up to 3 restarts with a short backoff.

Logs

- The dev server stdout/stderr is written to `vite.log` in the project root. Use `tail -f vite.log` to follow logs.

Override default port

By default the project sets the dev server to port `3000` to match the dev-runner health-check. To override the port when launching manually:

```bash
VITE_DEV_PORT=5173 npm run dev
# or set the env for the monitor
VITE_DEV_PORT=5173 npm run dev:monitor
```

CI notes

- The CI workflow `tests.yml` includes optional support for starting the frontend dev server and waiting for health using `npx wait-on`. See the `START_FRONTEND` environment variable in the workflow to enable this behavior.

Revert

To revert these dev-only changes, restore the previous `package.json` dev script and remove `bin/dev-monitor.sh`, `docs/DEV_SERVER.md`, and workflow edits. These changes are intentionally limited to development and CI helpers — production configs are unchanged.
