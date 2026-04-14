# CASE FILE: Woosoo Nexus (The Hub)
**Status:** Core Backend / Integration Hub
**Primary Stack:** PHP (Laravel) & Node.js

## 🎯 Goal
The central brain of the Woosoo ecosystem. It coordinates device registrations, processes print events, and manages the primary business logic.

## 🧩 Subjects
- **Print Orchestration:** Receiving orders and routing them to the correct `relay-device`.
- **API Surface:** Serving the `tablet-ordering-pwa` via REST/WebSockets.
- **Data Flow:** Managing the state of orders from "Placed" (PWA) to "Printed" (Relay).
- **Automation:** Cron jobs for reporting and device heartbeat monitoring.

## 📜 Coding Standards
- Laravel best practices (Service Classes, Eloquent).
- Node.js for high-concurrency WebSocket event handling.
- Strict API versioning to avoid breaking the PWA or Relay apps.