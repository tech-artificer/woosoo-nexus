# Print Service Relay

Standalone Node.js microservice that relays print events from Reverb WebSocket to the Woosoo Nexus print event API.

---

## Purpose

Acts as a bridge between:
- **Relay Device** (Flutter app on kitchen printer) — sends print events via WebSocket
- **Woosoo Nexus API** — receives ack confirmations and logs print history

Maintains a queue and retry logic independent of the main Laravel server.

---

## Architecture

```
Relay Device (Flutter)
    ↓ WebSocket (Reverb channel)
Print Service (index.js)
    ↓ HTTP POST
Woosoo Nexus API (/api/printer/print-events/{id}/ack)
    ↓ Response
Relay Device (update state)
```

---

## Installation

```bash
cd print-service/
npm install
```

---

## Running

```bash
# Development
node index.js

# Production (with process manager)
pm2 start index.js --name "woosoo-print-service"
```

---

## Configuration

Loads from `../../config/base-config.json`:
- **REVERB_PORT** (default 6001) — WebSocket listen port
- **API_BASE_URL** (default http://localhost:8000) — Woosoo Nexus API
- **API_TIMEOUT** (default 30s) — HTTP request timeout
- **QUEUE_RETRY** (default [1,2,4]s) — Backoff on API failure

---

## API Contract

**Receives (via WebSocket):**
```json
{
  "type": "print_event",
  "payload": {
    "print_event_id": 42,
    "device_id": 5,
    "device_uuid": "abc-123-def",
    "printer_id": "AA:BB:CC:DD:EE:FF",
    "printer_name": "Kitchen Printer 1",
    "status": "printed",
    "timestamp": "2026-01-23T19:45:00Z"
  }
}
```

**Sends (to API):**
```bash
POST /api/printer/print-events/{print_event_id}/ack
Content-Type: application/json
Authorization: Bearer {device_token}

{
  "printer_id": "AA:BB:CC:DD:EE:FF",
  "printer_name": "Kitchen Printer 1",
  "app_version": "1.0.0",
  "status": "printed"
}
```

---

## Error Handling

- **Timeout (>30s):** Retry with backoff [1,2,4]s, max 3 attempts
- **4xx error (validation):** Log and discard (no retry)
- **5xx error (server):** Retry with backoff
- **Network error:** Queue and retry on next connection

All errors logged to `../../logs/print-service.log`

---

## Monitoring

```bash
# Check if service is running
netstat -ano | findstr :6001

# View logs
tail -f ../../logs/print-service.log

# Test WebSocket connection
npm test
```

---

## Relationship to Woosoo Nexus

- Reverb server (Laravel WebSocket module) handles client authentication
- Print Service consumes events from authenticated channels
- API calls include device token (from initial device registration)
- Audit trail: Acknowledged events stored in `print_events.acknowledged_by_device_id` (B2 implementation)

---

## See Also

- [../../docs/printer_manual.md](../../docs/printer_manual.md) — Hardware integration
- [../../docs/printer_app.md](../../docs/printer_app.md) — Relay device integration
- [../../docs/print-events-contract.md](../../docs/print-events-contract.md) — Full ACK contract
