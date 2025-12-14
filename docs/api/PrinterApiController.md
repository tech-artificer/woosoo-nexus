Printer API - PrintEvent Endpoints

Summary of new endpoints added for durable print events:

- GET /api/printer/unprinted-events — Fetch unacknowledged print events (for printers/relays)
- POST /api/printer/print-events/{id}/ack — Acknowledge a print event as printed
- POST /api/printer/print-events/{id}/failed — Report a failure for a print event
- POST /api/printer/heartbeat — Printer heartbeat (existing)

These endpoints are exposed inside the `auth:device` route group and are intended for device-authenticated clients. They are additive and do not change existing order transactional semantics.
