# Woosoo Canonical Documentation Index

This is the authoritative navigation root for active documentation.

## Deployment

- [`docs/deployment/production-docker.md`](deployment/production-docker.md) — canonical production Docker deployment flow
- [`docs/deployment/restaurant-operations-handover-manual.md`](deployment/restaurant-operations-handover-manual.md) — restaurant setup, daily operations, and handover guide
- [`docs/deployment/tablet-update-contract.md`](deployment/tablet-update-contract.md) — tablet deployment boundary contract

## Architecture

- [`docs/architecture/ARCHITECTURE.md`](architecture/ARCHITECTURE.md) — platform architecture and runtime topology

## Operations

- [`docs/operations/scripts-reference.md`](operations/scripts-reference.md) — operational script entrypoints and execution context

## Standards

- [`docs/standards/documentation-governance.md`](standards/documentation-governance.md) — documentation authority and lifecycle rules
- [`docs/standards/documentation-pr-checklist.md`](standards/documentation-pr-checklist.md) — merge gate for documentation changes
- [`docs/standards/access-and-integration-rules.md`](standards/access-and-integration-rules.md) — access and integration rules

## API and Contracts

- [`docs/API_MAP.md`](API_MAP.md) — full API surface map
- [`docs/print-events-contract.md`](print-events-contract.md) — print event contract (current)
- [`docs/print-events-contract-plan.md`](print-events-contract-plan.md) — print event contract evolution plan
- [`docs/printer_readme.md`](printer_readme.md) — printer app integration guide (device auth, endpoints, WebSocket)
- [`docs/api/`](api/) — per-endpoint API documentation

## In-App Guides

In-app user guides are served from `resources/docs/guides/` and displayed within the
Woosoo Nexus admin panel under **Manual**. They are organised by audience:

- `resources/docs/guides/admin/` — admin panel guides *(guides pending)*
- `resources/docs/guides/tablet/` — tablet PWA guides *(guides pending)*
- `resources/docs/guides/relay/` — print relay guides *(guides pending)*

## Archive

- [`docs/archive/`](archive/) — historical, migration, audit, and deprecated material (non-canonical)
- [`docs/printer_manual.md`](printer_manual.md) — **archived** temporary printer workaround (superseded by `printer_readme.md`)
