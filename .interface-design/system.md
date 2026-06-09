---
status: canonical
last_reviewed: 2026-06-08
scope: woosoo-nexus
---

# Woosoo KDS — Interface Design System

Dense, warm, wall-distance kitchen command surface for Samsung Galaxy Tab A11+ landscape kiosk.

## Intent

Kitchen staff read tickets from 3–6 feet. Hero data (table number, elapsed timer) must dominate; chips, labels, and item rows sit clearly below without feeling shouty.

## Depth strategy

Borders-only — subtle `--kds-bg*` surface steps, light dividers. Card drop shadows on tickets only; item rows are flat.

## Spacing

8px base grid: 8 / 14 / 16 / 20px rhythm. Touch targets ≥ 44×44px.

## Typography — families

| Token | Stack | Role |
|-------|-------|------|
| `--kds-font-d` | Raleway | Display / brand only |
| `--kds-font-s` | Kanit | UI sans — heroes, labels, body, CTAs |
| `--kds-font-m` | JetBrains Mono | Timers, metrics, quantities |

## Typography — weights

| Token | Value | Use |
|-------|-------|-----|
| `--kds-weight-display` | 700 | Brand name, empty-state headline |
| `--kds-weight-hero` | 700 | Table number, elapsed timer (Kanit) |
| `--kds-weight-cta` | 700 | Mark Ready / Mark Served buttons |
| `--kds-weight-label` | 600 | Uppercase micro-labels, chips, badges, status pills |
| `--kds-weight-body` | 500 | Item names, empty-state subcopy, viewport default |
| `--kds-weight-data` | 600 | Metric values, clock time, qty (mono + tabular-nums) |
| `--kds-weight-caption` | 500 | Issued time, date sublabel (mono) |

### Element mapping

| Element | Font | Weight token | Selector |
|---------|------|--------------|----------|
| Brand name | Raleway | display | `.kds-brand-name` |
| Brand sub (KITCHEN - LIVE) | Kanit (inherit) | label | `.kds-brand-sub` |
| Queue metric value | Mono | data | `.kds-metric strong` |
| Queue metric label | Kanit | label | `.kds-metric span` |
| Online / Rush pill | Kanit | label | `.kds-online`, `.kds-rush` |
| Clock time | Mono | data | `.kds-clock strong` |
| Clock date | Mono | caption | `.kds-clock span` |
| Filter chip label | Kanit | label | `.kds-filter-chip` |
| Filter chip count | Mono | data | `.kds-filter-chip strong` |
| Density toggle | Kanit | label | `.kds-density-toggle` |
| Section label (TABLE, ELAPSED, ITEMS) | Kanit | label | `.kds-ticket-pane span`, `.kds-items-head` |
| Table # / timer hero | Kanit | hero | `.kds-ticket-pane strong` |
| Issued subline | Mono | caption | `.kds-ticket-pane small` |
| Type / status badges | Kanit | label | `.kds-pill`, `.kds-status-badge` |
| Item row text | Kanit | body | `.kds-item-row` |
| Item qty | Mono | data | `.kds-item-qty` |
| Card CTA | Kanit | cta | `.kds-card-action` |
| Empty headline | Raleway | display | `.kds-empty strong` |
| Empty subcopy | Kanit | body | `.kds-empty span` |

## Color semantics

Stage = left border only (`--kds-new`, `--kds-preparing`, `--kds-ready`, …). Urgency = timer color + optional overdue border glow. No per-table color coding.

## Motion

`prefers-reduced-motion: reduce` suppresses transitions globally on the KDS viewport.

## Touch

`touch-action: manipulation` on viewport. `env(safe-area-inset-*)` padding on full-bleed shell.
