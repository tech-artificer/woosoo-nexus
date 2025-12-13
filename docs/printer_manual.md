Printer API Temporary User Manual

Purpose
- Explain the temporary change allowing the printer app to call the printer API endpoints as guest.
- Provide usage examples, security considerations, and rollback instructions for the print and ops teams.

Location
- This file is stored at `docs/printer_manual.md` in the repository. Link from the main docs index if desired.

What changed (temporary)
- The following API routes were moved from the `auth:device` group to a guest-accessible group so the Flutter printer app can reach them without device tokens:
  - POST /api/orders/{orderId}/printed
  - GET  /api/orders/unprinted
  - POST /api/orders/printed/bulk
  - POST /api/printer/heartbeat
- No URIs or controller handlers were modified — only route middleware changed.

Quick usage (examples)
- curl (Linux/macOS):

  # Fetch unprinted orders (replace host/port)
  curl "http://localhost/api/orders/unprinted"

  # Mark multiple orders printed
  curl -X POST "http://localhost/api/orders/printed/bulk" \
    -H "Content-Type: application/json" \
    -d '[1001,1002]'

  # Mark single order printed
  curl -X POST "http://localhost/api/orders/1001/printed" \
    -H "Content-Type: application/json" \
    -d '{}'

  # Heartbeat
  curl -X POST "http://localhost/api/printer/heartbeat" \
    -H "Content-Type: application/json" \
    -d '{"session_id":123}'

- PowerShell (Windows):

  Invoke-WebRequest -Uri http://localhost/api/orders/unprinted -UseBasicParsing
  Invoke-WebRequest -Method POST -Uri http://localhost/api/orders/printed/bulk -ContentType 'application/json' -Body '[1001,1002]'
  Invoke-WebRequest -Method POST -Uri http://localhost/api/orders/1001/printed -ContentType 'application/json' -Body '{}'
  Invoke-WebRequest -Method POST -Uri http://localhost/api/printer/heartbeat -ContentType 'application/json' -Body '{"session_id":123}'

Security notes (important)
- These endpoints are now accessible without authentication. That increases risk of accidental or malicious calls that can mark orders as printed.
- Recommended temporary mitigations (pick one):
  1. IP whitelist middleware: only allow known printer IPs/subnets.
  2. Shared-secret / HMAC header: require a secret header the printers include and verify server-side.
  3. Rate limiting: throttle requests per IP to reduce abuse risk.
- Long-term: revert to `auth:device` and ensure printer app authenticates using device tokens (preferred).

Operational guidance
- Verify that the printers can reach the app host and that any local firewall allows outbound traffic to the app port.
- Run quick smoke tests (from the host or printer network) using the curl/PowerShell snippets above.
- Monitor logs (`storage/logs/laravel.log`) for unusual activity after change.
- If you need to restrict access quickly, I can implement an IP whitelist middleware and apply it to these routes.

Rollback plan
- Revert the single commit that moved middleware (or restore the prior `auth:device` group in `routes/api.php`).
- Re-deploy and verify printers authenticate with tokens (if available).

Contact & next steps
- If you want me to implement a mitigation (IP whitelist or HMAC) before opening a PR, say which and I will implement it and add tests.
- If you want this manual added to the documentation index, I can update `docs/api.md` or the docs index accordingly.

---
File: docs/printer_manual.md
Created: temporary manual for printer team
