---
status: canonical
last_reviewed: 2026-05-22
scope: ecosystem
---

# CASE: deployment-docs-krypton-pc-ip-change

## Run State
- task_slug: deployment-docs-krypton-pc-ip-change
- tier: 3
- branch: staging
- status: IN_PROGRESS
- last_completed_agent: specialist:dazai-docs
- next_agent: verifier
- active_runner: codex
- interrupted: false
- interrupt_reason: none
- updated: 2026-05-22 00:00

## Handoff
- Phase in progress: verifier
- Done so far: Contrarian review and docs specialist implementation in progress for a docs-only restaurant operations and handover manual.
- Exact next action: Build the DOCX, render it to PNG pages, inspect layout, and verify the restaurant-only IP contract.
- Working-tree state (list edited files explicitly; cross-check with `git status`): new docs-only files under `docs/cases/`, `docs/deployment/`, and `docs/deployment/assets/`.
- Risks / do-not-redo: Do not edit app code, `.env`, Docker runtime behavior, API contracts, or order logic. Do not include non-restaurant network IPs in the final manual.

## Tier
3 - production deployment and restaurant handover documentation. Documentation only, but the content is operationally high impact.

## Branch
staging

## Problem

The restaurant needs a complete operator-ready handover manual for Woosoo deployment and day-to-day operations. The manual must include restaurant-only network values, app usage, features, specifications, workflows, deployment/redeployment, logs, troubleshooting, rollback, generated diagrams, screenshot placeholders, and acceptance checks.

## Contrarian Review

1. Correct app or platform scope? Yes. This is platform documentation and handover scope, not app code.
2. Does this already exist? Partially. `docs/deployment/production-docker.md` is canonical for platform-root deployment, but it is not a complete restaurant handover manual.
3. Scope exactly as described? Yes. The request is broad but documentation-only.
4. What breaks if wrong? Operators may configure the wrong POS IP/port, use stale docs, or lose recovery steps during deployment.
5. Simpler path? A single complete DOCX plus maintainable Markdown source is simpler than scattering updates across several docs.
6. Touches contract/auth/state/payment/print? No code or contract changes, but it documents production deployment and POS/print flows. Tier 3 for operational impact.
7. Split required? No. Single docs-only deliverable. No app code changes.

Decision: proceed as docs specialist with strict no-app-code scope.

## Investigation

Authoritative sources:
- `docs/deployment/production-docker.md` for platform-root Docker deployment.
- `scripts/deployment/switch-network.sh` for restaurant values: `PUBLIC_HOST=192.168.1.31`, `DB_POS_HOST=192.168.1.32`, `DB_POS_PORT=2121`, `DB_POS_DATABASE=krypton_woosoo`.
- `contracts/tablet-api.contract.md` for intent-only tablet payload.
- `contracts/pos-db.contract.md` for POS authority and production POS IP.
- `contracts/printer-relay.contract.md` for heartbeat and reserve/ack/failed print lifecycle.
- 2026-05-14 app audits for feature and workflow summaries.

## Root Cause

The existing deployment documentation is correct for platform-root orchestration, but not packaged as a complete restaurant operator handover. Older examples and transition notes can confuse operators unless the final handover document provides a single restaurant-only path.

## Proposed Fix

Create:
- A maintainable Markdown source manual.
- Generated diagram assets with exact restaurant labels.
- A polished DOCX handover manual with screenshot placeholders and acceptance checklists.

## Files Changed

To be completed after implementation.

## Verification

To be completed by Verifier.

## Executioner Verdict

Pending.

## Remaining Risks

- Screenshots are placeholders until the operator supplies the real images.
- The DOCX is an operator guide; live Pi deployment still requires on-device validation.
