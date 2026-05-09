## Executive Summary
- [ ] Documents are ready for implementation
- [x] Documents need revision before implementation
- [x] Critical blockers identified

The docs are strong and actionable overall, but there are contract-level inconsistencies (endpoints/payloads/idempotency model) that will cause divergent backend/frontend implementation if not resolved first.

## Critical Issues (Must Fix)
| # | Issue | Location | Recommended Fix |
|---|-------|----------|-----------------|
| 1 | **Order API contract conflicts across docs**: implementation plan defines quote/commit/refill endpoints (`/api/device/orders/quote`, `/initial`, `/{order}/refills`), while long-term requirements list `/api/devices/create-order` and `/api/order/{orderId}/refill` as required tablet endpoints. | `docs/IMPLEMENTATION_PLAN_SERVER_AUTHORITATIVE_ORDER_TRANSACTION_2026-05-09.md:215-223`, `docs/LONG_TERM_REQUIREMENTS_2026-05-09.md:163-183`, `:865-879` | Pick one canonical v1 endpoint set and mark the other as explicit compatibility/deprecation path with dates. |
| 2 | **Refill request schema is inconsistent**: implementation plan top-level payload includes `order_id`, but refill API example omits it (path-only); long-term section 8.3 also omits it. | `docs/IMPLEMENTATION_PLAN_SERVER_AUTHORITATIVE_ORDER_TRANSACTION_2026-05-09.md:40-47`, `:291-303`, `docs/LONG_TERM_REQUIREMENTS_2026-05-09.md:473-481` | Standardize refill contract to one shape (path-only or path+body), document it once, and add fixture examples for both request and response. |
| 3 | **Idempotency persistence model conflicts**: implementation plan uses `order_transactions` with `idempotency_key + payload_hash`, while long-term requirements define separate `idempotency_records` schema. | `docs/IMPLEMENTATION_PLAN_SERVER_AUTHORITATIVE_ORDER_TRANSACTION_2026-05-09.md:97-123`, `docs/LONG_TERM_REQUIREMENTS_2026-05-09.md:416-431` | Decide single source of idempotency persistence (embedded in transaction table vs dedicated records table), then align both docs. |
| 4 | **Recovery lifecycle not fully specified** despite `recovery_required` status: no owner/trigger policy, retry budget, loop prevention, or terminal recovery states. | `docs/IMPLEMENTATION_PLAN_SERVER_AUTHORITATIVE_ORDER_TRANSACTION_2026-05-09.md:113-116`, `:151`, `:390-391`, `:757-765` | Add explicit recovery state machine: trigger actor (job/manual), max retries, backoff, dead-letter status, and observability fields. |

## Warnings (Should Fix)
| # | Issue | Location | Recommended Fix |
|---|-------|----------|-----------------|
| 1 | Commit/refill **response/error envelopes are underspecified** (only quote response is fully modeled; 409 example exists but no unified error schema). | `docs/IMPLEMENTATION_PLAN_SERVER_AUTHORITATIVE_ORDER_TRANSACTION_2026-05-09.md:225-303`, `docs/LONG_TERM_REQUIREMENTS_2026-05-09.md:406-414`, `:434-444` | Add canonical success/error schemas per endpoint (`code`, `retryable`, `request_hash`, `transaction_id`, `order`). |
| 2 | **Idempotency key generation/storage details need hardening**: uses `crypto.randomUUID()` and localStorage patterns without browser fallback and device-scope reset semantics. | `docs/IMPLEMENTATION_PLAN_SERVER_AUTHORITATIVE_ORDER_TRANSACTION_2026-05-09.md:589-620`, `:763`, `docs/LONG_TERM_REQUIREMENTS_2026-05-09.md:392-405`, `:546-549` | Specify fallback strategy, scope key by device/session, and clear policy on re-registration/table reassignment/session end. |
| 3 | **Concurrent quote policy is missing** (multiple active quotes per device/session, replacement behavior, cleanup policy). | `docs/IMPLEMENTATION_PLAN_SERVER_AUTHORITATIVE_ORDER_TRANSACTION_2026-05-09.md:70-95`, `:245`, `:751-752` | Define uniqueness strategy (`device_id+session_id+active`), new quote invalidation behavior, and scheduled cleanup of expired/abandoned quotes. |
| 4 | **Menu contract cache/invalidation strategy not defined** (TTL, refresh triggers, stale handling). | `docs/IMPLEMENTATION_PLAN_SERVER_AUTHORITATIVE_ORDER_TRANSACTION_2026-05-09.md:305-332`, `docs/LONG_TERM_REQUIREMENTS_2026-05-09.md:163`, `:870-879` | Specify fetch cadence, ETag/versioning, invalidation event, and stale fallback rules. |
| 5 | **Flow phase machine is defined but transitions are incomplete** (no explicit transition map/guards; risk of unreachable or contradictory states). | `docs/LONG_TERM_REQUIREMENTS_2026-05-09.md:577-610`, `:629-636`, `:907-912` | Add transition matrix (`from -> to`, trigger, guard, rollback behavior). |
| 6 | **Forbidden mixing enforceability is partly procedural** (code review/PR checks not all machine-enforced). | `docs/LONG_TERM_REQUIREMENTS_2026-05-09.md:36-45` | Add concrete CI checks (commit path allowlists per repo, branch policy, PR label gate). |

## Gaps / Missing Items
| # | Gap | Impact | Recommendation |
|---|-----|--------|----------------|
| 1 | Migration reversibility (`down`) not specified for new tables/columns. | Risky rollback during staged rollout. | Add explicit rollback plan per migration (drop order, nullable backfills, data preservation notes). |
| 2 | JSON column limits/shape constraints for `intent_payload`, `quote_payload`, `order_plan`, `pos_result` not specified. | Oversized payload risk and inconsistent schema persistence. | Define max size, JSON schema validation, and truncation/rejection behavior. |
| 3 | Phase 0 rollback plan not explicit. | Recovery ambiguity if CI fix introduces deployment regression. | Add “Phase 0 rollback” section with revert commits, config fallback, and verification checklist. |
| 4 | No explicit retention policy for `order_quotes` / `order_transactions`/idempotency records. | Unbounded growth and performance degradation. | Define archival/deletion windows and scheduled cleanup jobs. |
| 5 | Missing explicit observability thresholds (alerts/SLOs) for quote latency, commit latency, recovery rate. | Hard to operationalize success criteria. | Add SLO + alert thresholds and dashboard owner. |

## Questions for Clarification
1. Which endpoint family is canonical for v1 (`/api/device/orders/*` vs `/api/devices/create-order` + `/api/order/{id}/refill`)?
2. Should refill carry `order_id` in request body, or only in path?
3. Is idempotency persisted in `order_transactions`, `idempotency_records`, or both?
4. What actor owns `recovery_required` processing (queue worker, scheduled job, admin action)?
5. Are multiple active quotes allowed per device/session, and if yes, how is commit race handled?
6. What is the required cache policy for `GET /api/device/menu-contract`?

## Positive Findings
- Strong authority model: server-side validation/pricing/persistence is consistently emphasized.
- Good non-negotiable rule set and risk framing.
- Phase 0 is concrete and immediately executable.
- Test checklists are broad and mostly actionable.
- Security terminology (`security_code` vs bearer token) is explicit and clear.

## Implementation Readiness
| Phase | Ready? | Blockers |
|-------|--------|----------|
| 0 | [x] | None in requirements detail; scope is concrete. |
| 1 | [ ] | Endpoint/payload/idempotency model inconsistencies must be resolved first. |
| 2 | [ ] | Depends on canonical API contract decision and Phase 1 stabilization. |

## Deferred Polish Items (Address Later)

These are non-blocking consistency cleanups that should be queued after the current review/implementation cycle.

| # | Deferred polish item | Evidence | Suggested follow-up |
|---|----------------------|----------|---------------------|
| 1 | Clarify session endpoint semantics (`/api/session/latest` alias vs actual latest-session endpoint). | `docs/LONG_TERM_REQUIREMENTS_2026-05-09.md:172`, `routes/api.php:208`, `routes/api.php:255-257` | In the API table, label `/api/session/latest` as a compatibility alias to current session and explicitly document `/api/devices/latest-session` as latest known session. |
| 2 | Replace generic "Persisted idempotency table" wording with canonical `order_transactions` phrasing. | `docs/LONG_TERM_REQUIREMENTS_2026-05-09.md:431`, `docs/LONG_TERM_REQUIREMENTS_2026-05-09.md:599` | Change the checklist text to "Persisted idempotency in `order_transactions`". |
| 3 | Add explicit legacy endpoint deprecation cutoff (phase/date + removal condition). | `docs/LONG_TERM_REQUIREMENTS_2026-05-09.md:191` | Add a concrete deprecation window and merge gate for removing aliases. |
| 4 | Reconcile referenced contract/standards docs with actual repo files. | `docs/LONG_TERM_REQUIREMENTS_2026-05-09.md:154`, `docs/LONG_TERM_REQUIREMENTS_2026-05-09.md:831`, `docs/LONG_TERM_REQUIREMENTS_2026-05-09.md:837` | Create missing files (`docs/contracts/tablet-api.v1.yaml`, `docs/standards/idempotency.md`) or mark them as future-phase deliverables with owner/phase. |

