---
status: canonical
last_reviewed: 2026-05-17
scope: woosoo-nexus
---

# AGENTS.md — woosoo-nexus (per-app entrypoint)

This is a **pointer**, not a duplicate. It scopes agent work that happens inside
`woosoo-nexus/`; the full operating system lives in the root `../AGENTS.md`.

1. **Operating system:** follow the root `../AGENTS.md` — the Lite 4-agent sequence
   (Contrarian → Specialist → Verifier → Executioner) and triage tiers apply here.
2. **This app's hard rules are in `.agents.md`** (same directory). That file is the single
   source of truth for backend scope rules — do not duplicate it here.
3. **Specialist for this app:** `ranpo-backend`. Scope is `woosoo-nexus/**` only; touching
   another app is `SPLIT_REQUIRED`.
4. **Contracts:** `../contracts/order-state.contract.md`, `tablet-api`, `pos-db`,
   `auth-session`, `printer-relay`.
5. Order state machine: the `OrderStatus` enum; terminal = `completed | cancelled | voided | archived`. See `../contracts/order-state.contract.md`. Do not invent states.
6. Backend owns truth. Customer-facing errors must be client-safe. Never read/commit secrets.
7. **Resume:** before any task, check `../docs/cases/<task-slug>.md`. If `IN_PROGRESS`/`BLOCKED`,
   do not restart — follow `../docs/RESUME_PROTOCOL.md`, adopt the `next_agent` role, and
   checkpoint to the case file before handing off.
