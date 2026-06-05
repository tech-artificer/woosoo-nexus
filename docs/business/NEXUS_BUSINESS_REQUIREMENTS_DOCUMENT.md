# Woosoo Nexus — Business Requirements & System Specification

**Document Type:** Business Requirements Document (BRD) / System Specification  
**Classification:** Client-Facing / Executive Review  
**Platform:** Woosoo Nexus — Integrated Restaurant Operations Platform  
**Customer-Facing Product:** Grillpad Tablet Ordering  
**Prepared:** May 2026  
**Version:** 1.0 — Based on Verified Implemented Features

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [System Architecture Overview](#2-system-architecture-overview)
3. [Business Modules & Capabilities](#3-business-modules--capabilities)
4. [Customer Experience Workflow](#4-customer-experience-workflow)
5. [Operational Workflows](#5-operational-workflows)
6. [System Integrations](#6-system-integrations)
7. [Technical Infrastructure & Deployment](#7-technical-infrastructure--deployment)
8. [Delivered Scope — Feature Status](#8-delivered-scope--feature-status)

---

## 1. Executive Summary

### Platform Purpose

Woosoo Nexus is a purpose-built restaurant operations platform designed for Korean BBQ dining concepts. It delivers a complete, end-to-end digital ordering experience: guests place their own orders from table-mounted tablets, orders are routed in real time to kitchen printers, and restaurant operators monitor all activity through a live administrative dashboard — without disrupting or replacing the existing Point-of-Sale (POS) infrastructure.

### Business Value

| Value Driver | Description |
|---|---|
| **Reduced Front-of-House Labour** | Guests self-order via tablet, reducing the need for servers to take orders manually at each table |
| **Faster Kitchen Communication** | Orders are printed directly to kitchen thermal printers in real time — no manual relay or paper pads |
| **Higher Table Turn Efficiency** | Automated session lifecycle handles open, refill, and close events with minimal staff intervention |
| **Operational Transparency** | Administrators and managers see order status, printer health, and device activity on a live dashboard |
| **POS Continuity** | Nexus operates alongside the existing Krypton POS system without requiring POS migration or replacement |

### Stakeholders

| Role | System Interaction |
|---|---|
| **Restaurant Guests** | Table-mounted tablets for self-service ordering and refill requests |
| **Kitchen Staff** | Receive printed order tickets and refill tickets via kitchen thermal printers |
| **Front-of-House Staff** | Monitor service requests; acknowledge and fulfill guest assistance calls |
| **Restaurant Managers** | Access the admin dashboard for order oversight, menu management, and reporting |
| **System Administrators** | Manage devices, users, roles, and system configuration |
| **Owner / Operations** | Access reporting, analytics, and multi-branch performance data |

---

## 2. System Architecture Overview

### High-Level Architecture

Woosoo Nexus is a three-tier, on-premises system that operates entirely within the restaurant's local area network (LAN). No internet connectivity is required for the guest ordering flow once the system is deployed.

```
┌─────────────────────────────────────────────────────────────────┐
│                       RESTAURANT LAN                            │
│                                                                 │
│  ┌──────────────────┐         ┌───────────────────────────┐     │
│  │  Grillpad        │  HTTPS  │  Woosoo Nexus             │     │
│  │  Tablet PWA      │────────▶│  Laravel API + Admin      │     │
│  │  (Nuxt 3)        │         │  (PHP 8.2 / Laravel 12)   │     │
│  │                  │         └──────────┬────────────────┘     │
│  │  [Per Table]     │                   │                       │
│  └──────────────────┘         ┌─────────▼──────────────┐        │
│                               │  MySQL Database         │        │
│  ┌──────────────────┐         │  ├─ Nexus App DB        │        │
│  │  Admin Dashboard │         │  └─ Krypton POS DB      │        │
│  │  (Vue 3 / Inertia│◀────────│      (read-only)        │        │
│  │   — same server) │         └─────────────────────────┘        │
│  └──────────────────┘                   │                        │
│                               ┌─────────▼──────────────┐        │
│                               │  Reverb WebSocket       │        │
│                               │  (Real-Time Events)     │        │
│                               └─────────┬──────────────┘        │
│                                         │                        │
│  ┌──────────────────────────────────────▼──────────────────┐     │
│  │  Woosoo Print Bridge (Flutter Android)                  │     │
│  │  ├─ Listens to Reverb for print events                  │     │
│  │  ├─ Manages local print job queue (Sembast DB)          │     │
│  │  └─ Drives Bluetooth Thermal Printers (58mm)            │     │
│  └──────────────────────────────────────────────────────────┘     │
└─────────────────────────────────────────────────────────────────┘
```

### Component Summary

| Component | Technology | Role |
|---|---|---|
| **Woosoo Nexus API** | Laravel 12 / PHP 8.2 | Core backend: REST API, business logic, database, queue, broadcasting |
| **Admin Dashboard** | Vue 3 + Inertia.js | Operator-facing web UI for orders, menus, devices, reporting |
| **Grillpad Tablet PWA** | Nuxt 3 + TypeScript | Customer-facing Progressive Web App on table-mounted tablets |
| **Woosoo Print Bridge** | Flutter (Android) | Local print relay: receives events, manages queue, drives printers |
| **Reverb WebSocket Server** | Laravel Reverb | Real-time event bus for all system components |
| **MySQL Database** | MySQL 8.0 | Persistent data: Nexus app data + read-only Krypton POS mirror |
| **Redis** | Redis 7 | Cache, session, and asynchronous job queue |

---

## 3. Business Modules & Capabilities

### 3.1 Grillpad Tablet Self-Ordering

The Grillpad tablet application is the primary customer touchpoint. It is installed as a Progressive Web App (PWA) on table-mounted tablets, presenting a full-screen, touch-optimized ordering interface.

**Capabilities:**
- Branded welcome screen with table identification badge
- Guest count selection (party size 2–20)
- Dining package selection with price preview and meat-cut browsing
- Full menu browsing across categories: Meats, Sides, Desserts, Drinks
- Cart management with running price breakdown, tax calculation, and grand total
- Order submission with idempotency protection (prevents duplicate orders on retry)
- Real-time order status monitoring during the dining session
- Unlimited refill requests for eligible items (meats and sides)
- In-app service requests (call waiter, request assistance)
- Automatic session reset on order completion, ready for the next table

**Business Value:** Guests experience a seamless self-service ordering flow that reduces server workload and eliminates order transcription errors.

---

### 3.2 Order Management & Lifecycle

The order management system tracks every order from creation through completion, with a complete audit trail.

**Order Lifecycle States:**

```
PENDING → CONFIRMED → IN_PROGRESS → READY → SERVED → COMPLETED
        ↘           ↘            ↘       ↘         ↘
         CANCELLED   VOIDED       VOIDED   VOIDED    VOIDED
```

Terminal states (no further transitions): `COMPLETED`, `CANCELLED`, `VOIDED`, `ARCHIVED`.

**Capabilities:**
- Create orders from tablet devices via authenticated API
- Bulk order operations (complete, void, update status)
- Individual order detail view with line items and modifiers
- Manual order reprint from the admin dashboard
- Order status broadcast in real time to all connected clients
- Order refill request tracking (each refill round is separately recorded)
- Full order history and audit log (who changed what, and when)
- Synchronization with POS order data (read-only, for reference)

---

### 3.3 Menu Management & Curation

Menu data originates from the Krypton POS system and is extended with tablet-specific curation tools in Nexus.

**Capabilities:**
- Browse the full Krypton POS menu structure (categories, courses, groups, items, modifiers)
- Toggle availability of individual menu items for tablet ordering
- Define and manage Tablet Categories — curated groupings displayed in the Grillpad app
- Assign POS menu items to tablet categories
- Create and manage Dining Packages (e.g., "Classic BBQ", "Premium Combo") with price, description, and included items
- Configure per-package modifier groups (e.g., which meat cuts are included)
- Manage menu item images (uploaded and served from the Nexus storage layer)

**Business Value:** Operators can quickly adjust what is visible and orderable on tablets without touching the POS system — ideal for daily specials, seasonal items, or inventory-based availability changes.

---

### 3.4 Kitchen Print Relay System

The Woosoo Print Bridge is a dedicated Flutter Android application installed on a tablet near each kitchen printer. It operates autonomously and continuously.

**Capabilities:**
- Receives print jobs in real time via WebSocket (Laravel Reverb)
- Falls back to HTTP polling (every 30 seconds) if WebSocket is unavailable
- Manages a local persistent print queue (survives app restarts and device reboots)
- Prevents duplicate printing in multi-bridge deployments via server-side job reservation (atomic 409 conflict detection)
- Supports print types: order tickets, refill tickets
- Drives 58mm Bluetooth thermal printers (bonded via Android Bluetooth settings)
- Performs pre-print printer health checks (ESC/POS DLE EOT protocol)
- Reports print acknowledgment back to Nexus for audit trail
- Automatically retries failed print jobs with exponential backoff
- Maintains a dead-letter queue for jobs that fail beyond retry limits, with operator recovery tools
- Provides an 8-screen operator UI: Status, Queue, Dead-Letter, Metrics, Logs, Orders, Tools, Settings
- Sends heartbeat to Nexus every 30 seconds with printer and connection status

**Business Value:** Kitchen teams receive printed order tickets immediately upon order submission — with guaranteed delivery tracking, retry logic, and operator-visible failure recovery — eliminating silent print failures.

---

### 3.5 Device & Tablet Management

Every tablet running the Grillpad app is a registered device in the Nexus system.

**Capabilities:**
- Device registration via one-time security code and IP-based auto-discovery
- Token-based authentication (Laravel Sanctum) with automatic token refresh
- Table assignment — link a specific tablet to a specific restaurant table
- Device heartbeat monitoring — detect online/offline status in real time
- Device security code rotation
- Admin dashboard view of all registered devices with status indicators
- APK distribution for debug and release builds (for Android sideloading)
- SSL certificate download for local HTTPS setup on Pi-based deployments

---

### 3.6 Service Request System

Guests can request staff assistance directly from the tablet at any point during their dining session.

**Capabilities:**
- In-app floating action button (FAB) triggers a service request modal
- Service request types: table assistance, water refill, clean table, and others
- Requests broadcast in real time to the admin dashboard
- Staff can acknowledge and mark requests as resolved
- Full service request history visible in the admin panel

---

### 3.7 Multi-Branch Operations

Nexus supports multiple restaurant branches within a single deployment.

**Capabilities:**
- Branch entity with individual settings (theme, notification endpoints, API configuration)
- Users assigned to specific branches
- Orders and devices scoped to their respective branch
- Branch-level menu and device filtering in the admin dashboard
- Branch-resolved session context for API requests

---

### 3.8 Role-Based Access Control (RBAC)

Nexus implements granular, role-based access control for all administrative users.

**Capabilities:**
- Role creation and management (built on Spatie Laravel Permission)
- Fine-grained permission assignment per role
- Predefined admin role with full system access
- Custom role creation for restricted operator access
- User-to-role assignment from the admin dashboard
- Accessibility management dashboard for bulk permission updates

---

### 3.9 Admin Dashboard & Reporting

The admin dashboard provides real-time operational visibility and historical reporting.

**Real-Time Capabilities:**
- Live order feed with status indicators
- Device health panel (online/offline per tablet)
- Printer relay status and heartbeat display
- Service request queue
- System queue depth monitoring (Redis job queue)
- Broadcasting connection status

**Reporting Capabilities:**
- Daily sales reports
- Menu item performance analytics
- Hourly sales breakdown and trend charts
- Guest count analytics
- Print event audit trail
- Order status snapshots
- Discount and tax breakdowns

**Audit & Compliance:**
- Admin action audit log (who performed what operation and when)
- Order status change history (full before/after log)
- Broadcast event log
- Print history per order

---

### 3.10 Real-Time Infrastructure

All system components communicate through a shared real-time event bus powered by Laravel Reverb (WebSocket server).

**Broadcast Events:**

| Event | Trigger | Subscribers |
|---|---|---|
| `OrderCreated` | Tablet submits order | Admin dashboard |
| `OrderCompleted` | Order marked complete | Admin dashboard, Tablet |
| `OrderVoided` | Order voided | Admin dashboard, Tablet |
| `PrintOrder` | Order routed to printer | Print Bridge |
| `PrintRefill` | Refill routed to printer | Print Bridge |
| `ServiceRequest` | Guest requests assistance | Admin dashboard |
| `SessionReset` | Tablet session invalidated | Tablet |
| `MenuUpdated` | Menu availability changed | Tablet |
| `AppControlEvent` | Force refresh, feature toggle | All clients |

---

## 4. Customer Experience Workflow

The following is the verified end-to-end customer ordering flow, as implemented in the Grillpad tablet application.

### Step 1 — Welcome & Session Start
The tablet displays the Woosoo/Grillpad welcome screen with the assigned table number. Guests tap **"Begin the Feast"** to start. The app authenticates the device and verifies the table assignment with the Nexus backend.

### Step 2 — Guest Count Selection
A large, touch-friendly counter allows guests to set their party size. The selected count drives package pricing throughout the session.

### Step 3 — Dining Package Selection
Guests browse available dining packages (e.g., Classic BBQ, Premium, Wagyu). Each package card displays the name, description, and price per guest. Guests can tap "View Meats" to browse all included unlimited meat cuts in a full-screen gallery with images and categories (Beef, Pork, Seafood). Once a package is selected, guests proceed to the menu.

### Step 4 — Menu Browsing & Cart Building
The menu is organized across four tabs: **Meats**, **Sides**, **Desserts**, and **Drinks**. Guests add or remove items using touch controls. A floating cart summary shows the running total. The cart sidebar displays:
- Selected package and price breakdown
- All added items with quantities
- Subtotal, tax, and grand total

### Step 5 — Order Review & Submission
Guests review their complete order before submission. The app validates all items against current availability. Upon confirmation, the order is submitted to the Nexus API. If an item has become unavailable since loading, the app automatically refreshes the menu and removes the affected item before resubmitting. Duplicate order protection (idempotency key) prevents double submissions on network retry.

### Step 6 — In-Session Monitoring
After submission, the tablet transitions to the **In-Session** screen. Guests can see:
- All submitted items from the current order
- Package and pricing summary
- An active session indicator with elapsed time
- A prominent **"Order Refills"** button

### Step 7 — Refill Ordering
Guests tap "Order Refills" to re-enter the menu in refill mode. Only Meats and Sides are available (items eligible for unlimited refill). The refill is submitted as a separate order round. The in-session screen updates to show both the original order and refill items, labelled by round (Initial Order, Refill #1, Refill #2, etc.).

### Step 8 — Session Completion
When kitchen staff marks the order as complete in the admin dashboard, the tablet automatically detects the status change (via WebSocket broadcast) and transitions to the **Session Ended** screen. After a brief hold, the tablet auto-resets to the welcome screen, ready for the next guests.

---

## 5. Operational Workflows

### 5.1 Device Onboarding
1. Administrator creates a device record and generates a security code in the Nexus dashboard.
2. Staff physically installs the tablet at the table and opens the Grillpad PWA.
3. Staff taps the settings gear icon and enters the one-time security code.
4. The device registers with Nexus, receives an auth token, and is assigned to its table.
5. The device is now active and visible in the admin device panel.

### 5.2 Print Bridge Setup
1. A tablet designated as a print relay is registered in Nexus as a printer device.
2. The Woosoo Print Bridge app is installed on this tablet.
3. The operator opens the Settings screen, enters the API URL, and uses the security code to register.
4. The operator pairs a Bluetooth thermal printer via Android Bluetooth settings.
5. The operator selects the paired printer in the Print Bridge settings screen.
6. The bridge connects to Reverb and begins listening for print events.
7. A test print can be triggered from the Print Bridge Tools screen to verify the full pipeline.

### 5.3 Order Monitoring (Admin Dashboard)
- The admin dashboard displays all active orders in real time.
- Orders arrive automatically as tablets submit them.
- Staff can view order items, status, and associated table.
- Bulk complete or void operations are available for end-of-session clearance.
- Manual reprint can be triggered per order if a kitchen ticket was lost or damaged.

### 5.4 Menu Availability Management
- Administrators navigate to the Menu section of the dashboard.
- Individual menu items can be toggled available/unavailable without modifying the POS.
- Tablet categories can be reordered and re-curated at any time.
- Changes take effect immediately; a `MenuUpdated` broadcast refreshes all active tablets.

### 5.5 Service Request Handling
- Service requests appear on the admin dashboard in real time.
- Staff acknowledge the request by clicking "Acknowledge" on the dashboard.
- The request is marked resolved once the staff action is complete.
- A full history of service requests is available for review.

---

## 6. System Integrations

### 6.1 Krypton POS System (Read-Only)

| Attribute | Detail |
|---|---|
| **Integration Type** | Read-only secondary database connection |
| **Purpose** | Source of truth for menu data, table layout, order history, and revenue reporting |
| **Access Model** | Nexus connects to the Krypton database as a read-only consumer; no writes are made to POS data |
| **Data Consumed** | Menu items, categories, groups, modifiers; Tables, table groups; Orders, order checks; Employees; Revenue, taxes |
| **Benefit** | Nexus does not require manual menu re-entry; it reads the live POS menu structure directly |

### 6.2 Laravel Reverb (Real-Time WebSocket)

| Attribute | Detail |
|---|---|
| **Technology** | Laravel Reverb — open-source WebSocket server bundled with the Nexus deployment |
| **Protocol** | Pusher-compatible WebSocket protocol |
| **Clients** | Grillpad Tablet PWA, Woosoo Print Bridge, Admin Dashboard |
| **Transport Security** | TLS (WSS) with self-signed certificate support for local LAN deployments |
| **Fallback** | All clients implement HTTP polling fallback when WebSocket is unavailable |

### 6.3 Woosoo Print Bridge (Bluetooth Thermal Printing)

| Attribute | Detail |
|---|---|
| **Integration Model** | The Print Bridge is a first-party microservice, tightly integrated with Nexus via REST API and Reverb events |
| **Print Job Protocol** | Event received via WebSocket → reserved on Nexus API → printed locally → acknowledged via API |
| **Printer Protocol** | ESC/POS (58mm thermal) via Bluetooth |
| **Collision Prevention** | Server-side atomic reservation prevents duplicate printing when multiple Print Bridge devices are running |
| **Reliability** | Dead-letter queue, ACK backlog management, exponential backoff retry, and heartbeat monitoring |

### 6.4 Authentication (Laravel Sanctum)

Nexus uses Laravel Sanctum for API token authentication across all device types:
- Tablet devices receive a persistent API token at registration
- Print Bridge devices receive a token at setup
- Admin users authenticate via web session (cookie-based)
- Token refresh is handled automatically by the Grillpad PWA with a 60-second buffer before expiry

### 6.5 Access Control (Spatie Laravel Permission)

Role-based access control is implemented using the Spatie Laravel Permission package, providing:
- Granular permission definitions per resource and action
- Role composition (assign multiple permissions to a role)
- User-to-role assignment
- Middleware enforcement on all admin routes

---

## 7. Technical Infrastructure & Deployment

### 7.1 Deployment Model

Woosoo Nexus is deployed **on-premises** at each restaurant location using Docker Compose. The entire platform runs as a self-contained multi-service stack within the restaurant's local network.

**Docker Service Stack:**

| Service | Technology | Purpose | Resource Limit |
|---|---|---|---|
| `nginx` | nginx 1.25 | Reverse proxy, TLS termination | — |
| `app` | PHP 8.2 (Laravel) | Application server (PHP-FPM) | 768 MB RAM |
| `queue` | Laravel Queue Worker | Asynchronous job processing | — |
| `scheduler` | Laravel Scheduler | Cron tasks (heartbeat checks, log cleanup) | — |
| `reverb` | Laravel Reverb | WebSocket broadcast server | — |
| `tablet-pwa` | Nuxt 3 (served static) | Grillpad PWA (HTTPS on port 4443) | — |
| `mysql` | MySQL 8.0 | Primary application database | 640 MB RAM |
| `redis` | Redis 7 | Cache, session, job queue | 400 MB RAM |

**Ports Exposed:**
- `80` — HTTP (redirects to HTTPS)
- `443` — HTTPS (admin dashboard + API)
- `4443` — HTTPS (Grillpad Tablet PWA)

### 7.2 PWA Capabilities (Grillpad)

| Capability | Status |
|---|---|
| Installable (add to home screen) | Supported — iOS and Android |
| Fullscreen display mode | Enabled — no browser chrome |
| Landscape orientation lock | Enforced — optimized for tablet form factor |
| Service worker precaching | Enabled — app shell, menus, and images cached |
| Offline app shell | Supported — app loads offline; ordering requires network |
| Auto-update notification | Enabled — "New version available" banner with refresh prompt |
| Wake lock (screen always on) | Implemented — screen stays active during ordering sessions |
| Idle detection & auto-reset | Implemented — resets to welcome screen after inactivity |

### 7.3 Technology Stack

**Backend:**
- Laravel 12 (PHP 8.2)
- Inertia.js + Vue 3 (admin dashboard)
- Laravel Reverb (WebSocket server)
- Laravel Sanctum (API token authentication)
- Laravel Horizon (job queue monitoring)
- Spatie Laravel Permission (RBAC)

**Tablet Application:**
- Nuxt 3 + Vue 3 + TypeScript
- Pinia (state management with persistence)
- Tailwind CSS
- Laravel Echo + Pusher JS (Reverb WebSocket client)
- Workbox (PWA service worker)
- Axios (HTTP client)
- Zod (runtime validation)

**Print Bridge:**
- Flutter (Android)
- Sembast (local persistent database for print queue)
- Custom Blue Thermal Printer package (Bluetooth ESC/POS)
- SharedPreferences (configuration persistence)

**Infrastructure:**
- Docker Compose (multi-service orchestration)
- MySQL 8.0, Redis 7, nginx 1.25
- Raspberry Pi 5 (reference on-premises hardware target)

### 7.4 Security Model

| Layer | Mechanism |
|---|---|
| **Transport** | TLS (HTTPS/WSS) for all client-server communication; self-signed cert support for LAN deployments |
| **Device Authentication** | Laravel Sanctum API tokens — per-device, rotatable, with automatic refresh |
| **Admin Authentication** | Session-based login with CSRF protection |
| **API Authorization** | Middleware-enforced RBAC (Spatie Permission) on all admin routes |
| **Device Registration** | One-time security code required for initial device registration |
| **Print Bridge** | Bearer token authentication on all API calls; server-side reservation prevents unauthorized print job claiming |
| **Network** | All services communicate over an internal Docker bridge network; no external service-to-service exposure |

### 7.5 System Health Monitoring

| Mechanism | Description |
|---|---|
| **Health API** | `GET /api/health` returns DB, Redis, queue, and broadcasting status |
| **Docker Healthchecks** | Built-in healthchecks for all Docker services |
| **Device Heartbeat** | Tablets report heartbeat to Nexus; admin dashboard shows online/offline status |
| **Print Bridge Heartbeat** | Print Bridge sends status to Nexus every 30 seconds (printer state, connection, queue depth) |
| **Laravel Horizon** | Real-time job queue monitoring dashboard |
| **Stale Event Recovery** | Scheduled jobs detect and retry unacknowledged print events automatically |

---

## 8. Delivered Scope — Feature Status

### Fully Operational

| Feature Area | Status |
|---|---|
| Grillpad tablet ordering — full 7-step customer flow | Operational |
| Dining package selection with meat-cut browsing | Operational |
| Menu browsing (Meats, Sides, Desserts, Drinks) | Operational |
| Cart management with tax calculation and grand total | Operational |
| Order submission with idempotency protection | Operational |
| Unlimited refill ordering (meats and sides) | Operational |
| Real-time order status monitoring (in-session screen) | Operational |
| Automatic session end on order completion | Operational |
| Kitchen print relay (WebSocket + polling fallback) | Operational |
| Print job queue with dead-letter and retry logic | Operational |
| Multi-bridge collision prevention (server-side reservation) | Operational |
| Print acknowledgment and audit trail | Operational |
| Admin dashboard — live order feed | Operational |
| Admin dashboard — device health monitoring | Operational |
| Admin dashboard — print relay status | Operational |
| Menu availability management from admin | Operational |
| Tablet category and package curation | Operational |
| Device registration and table assignment | Operational |
| Role-based access control (RBAC) | Operational |
| Multi-branch operations | Operational |
| Sales reporting and menu performance analytics | Operational |
| Service requests (guest-to-staff assistance) | Operational |
| Krypton POS read-only integration | Operational |
| Full on-premises Docker Compose deployment | Operational |

### Partially Implemented

| Feature Area | Notes |
|---|---|
| POS order write-back | Krypton POS integration is read-only; Nexus orders are not written back to the POS system |
| Guest bill request | UI button exists on the tablet; functionality is fulfilled by staff via POS, not automated |
| Notification delivery (email/SMS) | Infrastructure present; no active notification dispatch implemented |

### Explicitly Out of Scope (Current Version)

| Feature | Status |
|---|---|
| Payment processing on tablet | Payment is handled entirely at the POS; no payment UI on tablets |
| Inventory management | No stock tracking or deduction logic |
| Loyalty / customer accounts | No guest account or rewards system |
| Delivery / takeout order types | Dine-in only; no order type differentiation |
| Network or USB printer support | Print Bridge supports Bluetooth thermal printers only |
| Multiple printers per Print Bridge device | Single printer per device |
| Multi-language support | English only |
| Labor / shift scheduling | POS employee data is read-only; no labor management |

---

*This document reflects features verified through direct code analysis of the `woosoo-nexus`, `tablet-ordering-pwa`, and `woosoo-print-bridge` repositories as of May 2026. All described capabilities are implemented and operational unless explicitly noted otherwise.*
