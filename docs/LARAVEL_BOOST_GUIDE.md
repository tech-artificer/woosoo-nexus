---
status: canonical
last_reviewed: 2026-06-05
scope: woosoo-nexus
---

# Laravel Boost — Day-to-Day Usage

[Laravel Boost](https://laravel.com/docs/boost) gives AI coding agents
Laravel-specific, version-pinned context and live tooling for this codebase. This
guide explains how to use it day-to-day and the guardrails to be aware of. It is
**additive** to the agent operating model — the always-on rules still live in the
root `../AGENTS.md` and per-app `.agents.md`; Boost just makes agents more
accurate inside those rules.

## What was installed

| File | Purpose |
|------|---------|
| `CLAUDE.md` | Version-pinned AI guidelines (Laravel 12, Inertia/Vue, Pulse, Reverb, Sanctum, Pest, Pint, PHP 8.2). Auto-loaded by Claude Code. |
| `.mcp.json` | Registers the Boost MCP server (`php artisan boost:mcp`) for Claude Code. |
| `boost.json` | Boost feature/agent config (guidelines + MCP, Claude Code). |
| `config/boost.php` | Published config — master switch, browser logs, Tinker toggle. |
| `composer.json` | `laravel/boost` (require-dev). |

`CLAUDE.md` and `config/boost.php` are managed/refreshable by Boost — prefer
`php artisan boost:update` over hand-editing `CLAUDE.md`.

## Prerequisite: local environment only

Boost is **inert unless `APP_ENV=local`** (or `APP_DEBUG=true`). It never runs in
production/CI by design. Confirm it is active:

```bash
php artisan list | grep boost      # should list boost:install, boost:mcp, boost:update, ...
```

In an editor that supports MCP (e.g. Claude Code), the `laravel-boost` server
loads automatically from `.mcp.json`.

## The MCP tools and when to use them

| Tool | Use it for |
|------|-----------|
| `search-docs` | Version-matched docs for installed packages. Pull these instead of guessing or pasting large docs into context. |
| `application-info` | Installed PHP/Laravel/package versions. Good first call in a new session. |
| `database-schema` | Real table/column structure — including the read-only `pos`/`krypton_woosoo` connection — before writing queries or migrations. |
| `database-query` | Run **read-only** SQL (SELECT/SHOW/EXPLAIN/DESCRIBE only; writes are rejected). |
| `database-connections` | List configured DB connections. |
| `last-error` / `read-log-entries` | Inspect recent application errors/logs. |
| `browser-logs` | Read browser console errors/exceptions (Inertia/Vue SPA). |
| `get-absolute-url` | Resolve correct scheme/host/port for project URLs. |
| `tinker` | Execute PHP in the app context (see safety note below). |

### `search-docs` and web sessions

`search-docs` calls `boost.laravel.com`. On a **local** machine it works out of
the box. In **Claude Code on the web**, the environment's network policy must
allowlist `boost.laravel.com` (the other tools run locally and need no network).

## The Tinker tool — power and safety

The `tinker` MCP tool lets an agent execute **arbitrary PHP** in the live
application (the equivalent of `php artisan tinker`) — useful for verifying an
`OrderStatus` transition, a `lorisleiva/laravel-actions` action, a Spatie
permission, or an Eloquent relationship against real data.

`config/boost.php` reads `env('BOOST_TINKER_TOOL_ENABLED', false)` — so the
config default is **off** as a safe fallback. The local env templates
(`.env.example`, `.env.local.example`) intentionally opt in by setting
`BOOST_TINKER_TOOL_ENABLED=true`; the Docker/production template sets it `false`.

Safety:

- It runs **local-only** (Boost's environment gate) — never in production.
- The primary `woosoo` connection uses a privileged DB user, so treat it like a
  live tinker shell: **don't run destructive code**. The `pos` connection is
  read-only at the DB-user level.
- To opt out, set `BOOST_TINKER_TOOL_ENABLED=false` in your `.env` (or unset it —
  it defaults to off in `config/boost.php`).

## Maintenance

```bash
php artisan boost:update     # refresh guidelines/skills to the latest guidance
php artisan boost:install    # re-run to add more agents/editors or change features
```

## Relationship to governance

`CLAUDE.md` (Boost coding conventions) and `AGENTS.md` / `.agents.md` (the agent
operating model and hard rules) are complementary and both loaded — Boost does
not replace or modify the governance docs.
