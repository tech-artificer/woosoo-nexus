## v0.1.0 - 2025-12-15

### Added
- PrintEvent API: event-driven print flow for printer apps (unprinted-events, ack, failed).
- New printer endpoints: `/api/printer/unprinted-events`, `/api/printer/print-events/{id}/ack`, `/api/printer/print-events/{id}/failed`.
- Backwards-compatible printing endpoints under `/api/orders/*` (idempotent mark-printed, bulk, heartbeat).
- `PrintEvent` model, `PrintEventService`, resources, tests, and docs.

### Tests
- Added comprehensive tests for printer flow and edge cases (ack idempotency, fail/attempts, validation).

### Docs
- Updated `docs/printer_app.md` and `docs/api.md` with PrintEvent API details and examples.

### Notes
- Merged from branch `feat/print-events` (PR #8). Full test suite passed locally: 50 tests, 143 assertions.
