# Print Events Contract and Routing Alignment Plan

## Mission
Create a definitive 1-page contract for relay device implementation, resolving mismatches between backend and relay expectations.

## Strategic Decisions (Dazai)
Decision 1: Acknowledgment endpoints match existing backend.
- POST /api/printer/print-events/{id}/ack
- POST /api/printer/print-events/{id}/failed

Decision 2: Polling endpoint hybrid (belt-and-suspenders).
- Keep existing: GET /api/printer/unprinted-events
- Add alias: GET /api/print-events/unprinted
- Relay accepts both response keys: "events" or "print_events".

## Critical Findings (Confirmed)
1) Endpoint path mismatch
- Backend: /api/printer/unprinted-events
- Relay expects: /api/print-events/unprinted
- Resolution: add alias route.

2) Response schema mismatch
- Backend returns "events"
- Relay expects "print_events"
- Resolution: relay accepts both keys.

3) Schema field mismatch
- Backend filters with is_acknowledged
- Contract previously used status
- Resolution: contract uses is_acknowledged.

4) Watermark comparison mismatch
- Backend uses created_at > since (exclusive)
- Contract previously used >=
- Resolution: contract documents exclusive behavior.

5) Payload field mismatch
- Backend broadcast lacks top-level print_event_id and device_id
- Relay requires flat fields
- Resolution: update broadcast payloads.

6) Relay watermark timing bug
- Relay sets _since to now after every poll
- Resolution: update only on non-empty response.

## Contract Specification

### WebSocket broadcasting
- Channel: admin.print
- Event: order.printed
- Required fields: print_event_id, device_id, order_id, session_id, print_type, refill_number, tablename, created_at, payload

### HTTP polling (hybrid)
- Primary: GET /api/printer/unprinted-events
- Alias: GET /api/print-events/unprinted
- Response keys accepted: "events" or "print_events"
- Filter logic:
```
WHERE is_acknowledged = false
  AND (since IS NULL OR created_at > since)
ORDER BY created_at ASC
LIMIT 200
```

### Acknowledgments
- POST /api/printer/print-events/{id}/ack
- POST /api/printer/print-events/{id}/failed

### Heartbeat
- POST /api/printer/heartbeat

## Implementation Changes Required

### Backend (woosoo-nexus)
- Add alias route for /api/print-events/unprinted to getUnprintedEvents().
- Update broadcast events to include top-level fields.

### Relay device (relay-device-v2)
- Update polling watermark logic to only advance on non-empty response.
- Update API parsing to accept "events" or "print_events".
- Update ack endpoints to /api/printer/print-events/{id}/ack and /failed.

## Documentation Updates
- apps/woosoo-nexus/docs/print-events-contract.md updated to hybrid rules.

## Verification Checklist
- WS event arrives with print_event_id/device_id.
- Polling returns events and relay parses keys correctly.
- Relay prints once and acks via /api/printer/print-events/{id}/ack.
- Heartbeat visible in admin.
- Polling fallback works when WS is down.

## Success Criteria
- Backend and relay use compatible polling paths.
- Response schema matches relay expectations.
- All required fields present in payloads.
- Watermark prevents missed events.
- Dedup works via print_event_id.
- End-to-end test passes.
