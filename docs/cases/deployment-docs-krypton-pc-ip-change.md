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
- last_completed_agent: verifier
- next_agent: executioner
- active_runner: codex
- interrupted: false
- interrupt_reason: none
- updated: 2026-05-25

## Handoff
- Phase in progress: executioner
- Done so far: Verifier rebuilt the DOCX handover manual, reviewed Nexus and Tablet PWA navigation against current app surfaces, added the navigation guide, regenerated diagrams, confirmed the builder's restaurant-value checks passed, and visually inspected the generated diagram assets.
- Exact next action: Executioner should review the docs-only diff, confirm the render limitation is acceptable or provide LibreOffice for full DOCX page render QA, then approve or return to verifier.
- Working-tree state (list edited files explicitly; cross-check with `git status`): docs-only changes under `docs/deployment/` plus this case file.
- Risks / do-not-redo: Do not edit app code, `.env`, Docker runtime behavior, API contracts, or order logic. Do not include non-restaurant network values in the final manual.

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

- `docs/deployment/restaurant-operations-handover-manual.md` — added verified Woosoo Nexus and Tablet Ordering PWA navigation guidance.
- `docs/deployment/build_restaurant_handover_manual.py` — added the same navigation section to the DOCX builder, added required structural check phrases, and fixed deployment diagram label wrapping.
- `docs/deployment/woosoo-restaurant-operations-handover-manual.docx` — generated final DOCX artifact from the builder.
- `docs/deployment/assets/restaurant-network-topology.png` — generated diagram asset.
- `docs/deployment/assets/app-responsibility-map.png` — generated diagram asset.
- `docs/deployment/assets/order-flow.png` — generated diagram asset.
- `docs/deployment/assets/deployment-workflow.png` — generated diagram asset after wrapping fix.
- `docs/cases/deployment-docs-krypton-pc-ip-change.md` — verifier checkpoint and evidence update.

## Verification

Verifier completed the following checks:

- PASS — reviewed current Nexus navigation against `resources/js/components/AppSidebar.vue`; the manual now documents Main, Analytics, and Configuration navigation groups and the relevant pages for Dashboard, Orders, POS, Menus, Packages, User Management, Devices, Service Requests, Reports, Branches, Access Control, Accessibility, Event Logs, Reverb Service, Monitoring, and Manual.
- PASS — reviewed current Tablet PWA route and flow surfaces against `tablet-ordering-pwa/pages/*` and `tablet-ordering-pwa/docs/API_TRACE_REFERENCE.md`; the manual now documents `/`, `/settings`, `/order/start`, `/order/packageSelection`, `/menu`, `/order/review`, `/order/in-session`, `/order/session-ended`, and `/sw-reset`, including the current `Begin the Feast` entry action.
- PASS — ran DOCX builder:

```powershell
C:\Users\Pc1\.cache\codex-runtimes\codex-primary-runtime\dependencies\python\python.exe docs\deployment\build_restaurant_handover_manual.py
```

Output:

```text
Wrote E:\Projects\woosoo-platform\woosoo-nexus\docs\deployment\woosoo-restaurant-operations-handover-manual.docx
Wrote network: E:\Projects\woosoo-platform\woosoo-nexus\docs\deployment\assets\restaurant-network-topology.png
Wrote responsibility: E:\Projects\woosoo-platform\woosoo-nexus\docs\deployment\assets\app-responsibility-map.png
Wrote order: E:\Projects\woosoo-platform\woosoo-nexus\docs\deployment\assets\order-flow.png
Wrote deployment: E:\Projects\woosoo-platform\woosoo-nexus\docs\deployment\assets\deployment-workflow.png
```

- PASS — builder's built-in `verify_docx_text()` completed without raising after confirming required restaurant values/phrases and rejecting forbidden old sample IPs.
- PASS — confirmed DOCX file exists:

```powershell
Test-Path docs\deployment\woosoo-restaurant-operations-handover-manual.docx
```

Output:

```text
True
```

- PASS — generated diagram assets exist and are non-empty:

```text
app-responsibility-map.png       88203
deployment-workflow.png          54813
order-flow.png                   60471
restaurant-network-topology.png  83037
```

- PASS — visually inspected the four generated diagram PNGs. Initial `deployment-workflow.png` had overflowing script labels; verifier patched the builder, regenerated assets, and re-inspected the fixed diagram with no visible overlap.
- PARTIAL — attempted DOCX render-to-PNG QA with the Documents renderer:

```powershell
C:\Users\Pc1\.cache\codex-runtimes\codex-primary-runtime\dependencies\python\python.exe C:\Users\Pc1\.codex\plugins\cache\openai-primary-runtime\documents\26.520.11634\skills\documents\render_docx.py E:\Projects\woosoo-platform\woosoo-nexus\docs\deployment\woosoo-restaurant-operations-handover-manual.docx --output_dir E:\Projects\woosoo-platform\woosoo-nexus\docs\deployment\_rendered_manual_pages --emit_pdf
```

Result:

```text
FileNotFoundError: [WinError 2] The system cannot find the file specified
```

Follow-up:

```powershell
where.exe soffice
```

Output:

```text
INFO: Could not find files for the given pattern(s).
```

Conclusion: LibreOffice/`soffice` is not available to the renderer, so full DOCX page-image QA could not be completed in this environment. The manual has passed builder-level DOCX checks, source checks, generated asset checks, and diagram visual checks.

## Executioner Verdict

Pending.

## Remaining Risks

- Screenshots are placeholders until the operator supplies the real images.
- The DOCX is an operator guide; live Pi deployment still requires on-device validation.
