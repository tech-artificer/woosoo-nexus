---
status: canonical
last_reviewed: 2026-06-02
scope: ecosystem
---

# Woosoo process documentation

## 1. Purpose and scope

This document explains how Woosoo software work is planned, implemented, validated, released, maintained, and documented.

It applies to:

- `woosoo-nexus` - Laravel backend, admin UI, POS/Krypton integration, Reverb broadcasting, and print-event orchestration.
- `tablet-ordering-pwa` - Nuxt 3 customer/staff tablet ordering app.
- `woosoo-print-bridge` - Flutter Android print relay.
- `woosoo-platform` - deployment, orchestration, governance, Docker, scripts, and canonical cross-app contracts.

It does not replace source code, contracts, or per-app `.agents.md` rules. It tells contributors how to move work through the system safely.

## 2. Documentation ownership

| Area | Owner | Canonical source |
|---|---|---|
| Ecosystem rules and contracts | Platform maintainers | Root `AGENTS.md`, `docs/AI_CONTEXT.md`, `contracts/*.md` |
| Backend process and implementation notes | Nexus maintainers | `woosoo-nexus/docs/` |
| Tablet process and implementation notes | Tablet maintainers | `tablet-ordering-pwa/docs/` |
| Print relay process and implementation notes | Print Bridge maintainers | `woosoo-print-bridge/docs/` |
| Deployment process | Platform maintainers | Root `compose.yaml`, `docs/deployment/*`, `scripts/deployment/*` |
| Software documentation package | Technical writer plus maintainers | `woosoo-nexus/docs/software-development/` |

## 3. Canonical source rules

- Only documents with `status: canonical` frontmatter are source of truth.
- Archived documents are historical only.
- If a doc describes a command, route, event, config key, or state, verify it against the current repository before publishing.
- Generated DOCX files are deliverables; Markdown is the maintainable source.
- Do not document secrets. Use placeholders such as `replace_with_reverb_key`.
- Do not document invented order states, event names, payload fields, or deployment services.

## 4. Development lifecycle

Woosoo uses a strict lifecycle:

```text
intake -> case file -> triage -> plan -> implement -> validate -> review -> release -> handover
```

### 4.1 Intake

Use intake for raw logs, bug reports, feature requests, and stakeholder notes.

The intake record should capture:

- Reporter and date.
- Affected app or component.
- Observed behavior.
- Expected behavior.
- Screenshots, logs, or exact error text.
- Known environment, branch, and device context.
- Whether the issue affects restaurant operation.

### 4.2 Case file

Every non-trivial task gets a durable case file under `docs/cases/<task-slug>.md`.

The case file records:

- Task slug.
- Tier.
- Target app or docs scope.
- Current agent/phase.
- Findings.
- Proposed fix.
- Files changed.
- Verification evidence.
- Executioner verdict.

The case file is the resume point if the session is interrupted.

### 4.3 Triage

Triage decides the risk tier and scope.

| Tier | Use for | Required flow |
|---|---|---|
| Tier 1 | Trivial copy, typo, or one-line docs fix | Specialist -> Executioner |
| Tier 2 | Standard bug fix, docs package, UI change, endpoint change | Contrarian -> Specialist -> Verifier -> Executioner |
| Tier 3 | Auth, POS writes, order lifecycle, printing idempotency, deployment, race conditions | Deep Contrarian review -> Specialist -> Verifier -> Executioner |

Use the smallest scope that safely solves the problem. One task should modify one app unless the change is explicitly documentation-only or integration-approved.

### 4.4 Planning

Before implementation, the plan must identify:

- Goal and success criteria.
- Target app or documentation scope.
- Files or subsystems likely affected.
- Public interfaces or contracts affected.
- Required tests or checks.
- Rollback path.
- Known assumptions.

### 4.5 Implementation

Implementation rules:

- Preserve existing patterns.
- Keep changes tightly scoped.
- Avoid unrelated refactors.
- Do not change runtime behavior in documentation tasks.
- Do not weaken auth, CSRF, CORS, device auth, or channel guards.
- Do not add hardcoded LAN IPs to tablet or bridge code.
- Never expose secrets in logs, docs, or generated artifacts.

### 4.6 Validation

Validation must prove the intended behavior or documentation quality. For docs, validation means:

- Commands exist.
- Paths exist.
- Events and endpoints match source.
- Changelog entries are verified or marked pending.
- Generated DOCX artifacts exist.
- Render/visual QA is attempted for DOCX deliverables.

For code, validation means running the relevant app test, lint, type-check, build, and platform pre-merge gates.

### 4.7 Review

Review checks for:

- Contract drift.
- Security regressions.
- Race conditions.
- State-machine violations.
- Duplicate print or ACK hazards.
- User-facing raw technical errors.
- Documentation truth drift.
- Scope creep.

### 4.8 Release

Release work must include:

- Source branch and target branch.
- Release notes.
- Migration/deployment steps.
- Smoke checks.
- Rollback plan.
- Known risks.

### 4.9 Handover

The handover records:

- What changed.
- Why it changed.
- Files changed.
- Tests/checks run.
- Output or exact blocker.
- Remaining risks.
- Rollback path.

## 5. Repository and component process

### 5.1 Platform repository

The platform repository coordinates deployment and governance. It owns:

- Root `compose.yaml`.
- Docker/Nginx/cert assets.
- Deployment scripts.
- Cross-app contracts.
- Agent/case workflow.
- Platform documentation index.

Do not put app implementation code in platform-only tasks.

### 5.2 Nexus repository

Nexus owns backend truth:

- Device auth.
- Tablet APIs.
- POS/Krypton integration.
- Order state.
- Session lifecycle.
- Reverb event publication.
- Print-event records.
- Admin UI.
- Monitoring and reporting.

Nexus changes must preserve the rule that the backend owns pricing, modifiers, totals, POS mapping, and order state.

### 5.3 Tablet PWA repository

The Tablet PWA owns customer/staff interaction:

- Welcome and registration flow.
- Guest/package/menu/order review flow.
- In-session refill and service request flow.
- Reverb status handling.
- Local recovery UX.

The tablet sends intent only. It must not send pricing, tax, modifiers, totals, POS mapping, or order state.

### 5.4 Print Bridge repository

The Print Bridge owns last-mile printing:

- WebSocket/polling intake.
- Local durable queue.
- Bluetooth printer connection.
- Reserve/print/ack/fail lifecycle.
- Dead-letter handling.
- Operator visibility.

Bridge changes must preserve duplicate-print prevention.

## 6. Branching, commits, and review

Use branch names that identify scope:

```text
agent/<task-slug>
fix/<app>/<short-name>
feature/<app>/<short-name>
docs/<short-name>
```

Commit messages should be conventional and scoped:

```text
docs(nexus): add software documentation package
fix(tablet): prevent duplicate token refresh calls
fix(print-bridge): recover stale reserved print jobs
```

Review requirements:

- Stage only intended files.
- Do not use `git add .` for mixed worktrees.
- Quote raw test output when claiming a test result.
- Do not treat partial test slices as full-suite proof.
- Include rollback instructions.

## 7. Requirements and change control

Requirements changes must be traceable.

For each requirement, record:

- Requirement ID or short name.
- User role.
- Business reason.
- Functional behavior.
- Non-functional constraints.
- Acceptance checks.
- Source or approval note.

Change-control rules:

- Contract changes require docs-first updates.
- Auth/session/POS/print changes are high risk.
- New order states require changes to `OrderStatus` and `contracts/order-state.contract.md`.
- New tablet payload fields require changes to `contracts/tablet-api.contract.md`.
- New print relay behavior requires changes to `contracts/printer-relay.contract.md` and source verification.

## 8. Testing and quality assurance

### 8.1 Standard gates

Run the platform pre-merge check from the platform root when possible:

```powershell
.\scripts\pre-merge-check.ps1 -App woosoo-nexus
.\scripts\pre-merge-check.ps1 -App tablet-ordering-pwa
.\scripts\pre-merge-check.ps1 -App woosoo-print-bridge
```

For app-specific development, also run the app's native commands:

| App | Common checks |
|---|---|
| Nexus | `composer test`, `npm run build`, route/config checks |
| Tablet PWA | `npm run typecheck`, `npm run lint`, `npm run test`, `npm run build`, `npm run generate` |
| Print Bridge | `flutter analyze`, `flutter test` |

### 8.2 Documentation truth audit

Before publishing docs:

1. Verify routes in `routes/*.php`.
2. Verify event names in event classes and client listeners.
3. Verify environment keys in `.env.example` files and deployment templates.
4. Verify service names in `compose.yaml`.
5. Verify commands exist.
6. Verify changelog entries against `git log`.
7. Mark unverifiable claims as pending verification.

### 8.3 DOCX quality gate

For generated DOCX deliverables:

1. Build DOCX from Markdown.
2. Render to page images with the Documents renderer when LibreOffice/`soffice` is available.
3. Inspect every page image for clipped text, broken tables, overlap, missing glyphs, and bad page breaks.
4. If rendering is unavailable, record the exact blocker and run structural checks.

## 9. Release and deployment process

### 9.1 Pre-release

Before release:

- Confirm target branches.
- Confirm no unrelated dirty files.
- Confirm migrations and environment changes.
- Confirm rollback path.
- Confirm release notes.
- Confirm smoke checks.

### 9.2 Deployment

The platform root is the Docker Compose authority. Compose operations should run from the platform repository root.

Typical production-style checks:

```bash
docker compose --env-file ./woosoo-nexus/.env -f compose.yaml ps
docker compose --env-file ./woosoo-nexus/.env -f compose.yaml logs --tail=100 app nginx reverb
curl -k https://<PUBLIC_HOST>
curl -k https://<PUBLIC_HOST>:4443/build-info.json
```

### 9.3 Post-release smoke checks

After deployment:

1. Open Nexus admin.
2. Open Tablet PWA.
3. Confirm POS host is reachable.
4. Place a test order.
5. Confirm the order appears in Nexus.
6. Confirm POS/Krypton receives the order.
7. Confirm Print Bridge receives and prints the receipt.
8. Confirm print ACK or print audit reflects completion.

### 9.4 Rollback

Rollback instructions must name:

- Branch/tag/commit to return to.
- Docker services to restart or rebuild.
- Migration rollback status, if any.
- Manual recovery steps for in-flight sessions or print jobs.
- Evidence required before service resumes.

## 10. Incident and maintenance process

### 10.1 Reverb/WebSocket incident

1. Check Reverb container/service status.
2. Check client runtime config.
3. Check `/broadcasting/auth` or device auth failures.
4. Check browser or Flutter logs.
5. Confirm fallback polling status for tablet/bridge where applicable.

### 10.2 POS/Krypton incident

1. Confirm the POS PC is powered and reachable.
2. Confirm static IP and port.
3. Check Nexus logs for POS connection failures.
4. Do not add compensating POS deletes.
5. Reconcile toward POS authority after recovery.

### 10.3 Tablet incident

1. Confirm device registration and token validity.
2. Confirm API base URL and Reverb runtime config.
3. Confirm session/order state from Nexus.
4. Use `/sw-reset` only for stuck service-worker/client-shell issues.

### 10.4 Print incident

1. Confirm Print Bridge heartbeat.
2. Confirm printer connection and permissions.
3. Check local queue, ACK backlog, and dead-letter screens.
4. Confirm server print event reserve/ack/fail endpoints.
5. Avoid duplicate physical prints unless an operator explicitly chooses manual reprint.

## 11. Changelog and release-note process

The user-provided changelog is useful intake. Treat it as a starting point, not automatic truth.

For each changelog entry:

1. Verify the commit subject in local `git log` or GitHub.
2. Use the commit hash in the release-note entry.
3. Summarize only what the commit subject and nearby source evidence support.
4. If a claim is plausible but not verified, label it `pending verification`.
5. Separate release notes by audience:
   - Developer notes: contracts, code paths, tests, migrations.
   - Operator notes: deployment, monitoring, daily operation.
   - Stakeholder notes: delivered capabilities and scope changes.

## 12. Documentation revision log

| Date | Version | Author | Summary | Verification basis |
|---|---|---|---|---|
| 2026-06-02 | 1.0 | Codex | Created process, product, and user documentation package with DOCX generation workflow. | Live source inspection, local `git log`, contract docs, generated DOCX check. |

## 13. Revision history and changelog basis

This document was created from:

- Current canonical contracts.
- Current route and event source.
- Current app manifests.
- Current platform compose/deployment docs.
- Local commit subjects from `woosoo-nexus`, `tablet-ordering-pwa`, and `woosoo-print-bridge`.

Any future release-note update must repeat the verification step rather than copying this section forward unchanged.
