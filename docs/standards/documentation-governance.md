# Documentation Governance Standard

Status: Active

## Core rule

Documentation must describe current operational reality.

## Production authority

- Production Docker authority is `compose.yaml` in `woosoo-nexus`.
- Production deployment commands must be run from `E:\Projects\woosoo-nexus`.

## Ownership boundaries

- `woosoo-nexus`: orchestration, deployment, networking, nginx, Redis, MySQL, Reverb.
- `tablet-ordering-pwa`: frontend source code.
- `woosoo-print-bridge`: bridge implementation; no independent production orchestration authority.

## Canonical doc structure

- Active docs: `docs/`
- Archive only: `docs/archive/`
- Navigation root: `docs/INDEX.md`

## Archive policy

Historical, deprecated, migration, audit, and experimental docs must be moved to `docs/archive/*` and carry the standard archived header.

## README policy

- One canonical root `README.md` for repository authority and entrypoints.
- Subdirectory READMEs are allowed only for local technical scope and must not define competing production authority.

## Forbidden patterns

- Multiple active production deployment flows.
- Duplicate canonical guidance in separate files.
- Temporary debugging notes presented as operational standards.
