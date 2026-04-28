# Woosoo Deployment Access and Integration Rules

This document defines the production access rules for the on-premise Raspberry Pi deployment.

The goal is to keep the system simple, predictable, and easy to support in a restaurant LAN environment.

---

## 1. Canonical Hostname

All client devices should use the local hostname:

```txt
woosoo.local
```

The Raspberry Pi resolves this name locally through `dnsmasq`:

```txt
woosoo.local → Raspberry Pi static LAN IP
```

Example:

```txt
woosoo.local → 192.168.100.10
```

Client devices should not be configured to use the raw Pi IP as the normal application URL.

---

## 2. Client Access Map

```txt
Admin panel:        https://woosoo.local
Laravel API:        https://woosoo.local/api
Tablet ordering:    https://woosoo.local/tablet
Reverb/WebSocket:   wss://woosoo.local/app
Print bridge API:   https://woosoo.local
```

---

## 3. Tablet Ordering PWA Rule

Ordering tablets access the Nuxt tablet ordering PWA through:

```txt
https://woosoo.local/tablet
```

The tablet PWA should call the backend API through:

```txt
https://woosoo.local/api
```

The tablet PWA should connect to realtime events through:

```txt
wss://woosoo.local/app
```

Ordering tablets must not talk directly to:

```txt
POS database
POS API
Bluetooth printer
print bridge tablet
MySQL/MariaDB
Redis
internal Reverb port
```

The tablet PWA is a client only.

---

## 4. Admin Panel Rule

The Laravel admin panel remains on:

```txt
https://woosoo.local
```

Laravel routes such as admin dashboards, API docs, settings pages, and backend management pages remain owned by `woosoo-nexus`.

The `/tablet` prefix is reserved for the tablet ordering PWA and should not be used by Laravel routes.

Nginx should intercept `/tablet` before Laravel receives the request.

Recommended Nginx routing:

```nginx
location = /tablet {
    return 301 /tablet/;
}

location ^~ /tablet/ {
    alias /var/www/html/public/tablet/;
    try_files $uri $uri/ /tablet/index.html;
}

location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

---

## 5. Print Bridge Rule

The Bluetooth thermal printer is not connected to the Raspberry Pi.

The printer is paired only with the print bridge tablet / relay device.

```txt
woosoo-nexus on Raspberry Pi
  https://woosoo.local
        ↑
        │ API / WebSocket
        │
Print Bridge Tablet / Relay Device
  woosoo-print-bridge app
        ↓
        │ Bluetooth
        ↓
Bluetooth Thermal Printer
```

The print bridge tablet should:

1. Connect to `https://woosoo.local`.
2. Authenticate or register as a device.
3. Listen for print events through Reverb/WebSocket.
4. Fallback-fetch unprinted orders through the backend API if WebSocket events are missed.
5. Format the receipt locally.
6. Send the receipt data to the Bluetooth thermal printer.
7. Call the backend to mark the order as printed.
8. Send heartbeat/status to the backend.

The backend manages order data and print state.

The print bridge tablet manages Bluetooth pairing, printer connection, receipt formatting, and actual printing.

---

## 6. Print Flow

```txt
Ordering Tablet
  → https://woosoo.local/tablet
  → creates order

Raspberry Pi / woosoo-nexus
  → saves order
  → broadcasts print event

Print Bridge Tablet
  → receives or fetches order from https://woosoo.local
  → prints via Bluetooth thermal printer
  → marks order printed
```

Fallback flow:

```txt
If WebSocket is missed or disconnected:
  print bridge calls backend unprinted-orders endpoint
  print bridge prints pending orders
  print bridge confirms printed status
```

---

## 7. POS Integration Rule

Only `woosoo-nexus` should communicate with the POS / third-party application.

```txt
Tablet PWA        → woosoo-nexus only
Print bridge      → woosoo-nexus only
woosoo-nexus      → POS database/API
```

Client devices must not talk directly to the POS system.

This keeps POS credentials, database access, and sync logic centralized in the backend.

---

## 8. DNS Rule for Tablets

Because router access may not be available, tablets should keep DHCP for their IP address but manually use the Pi as DNS.

Example tablet Wi-Fi settings:

```txt
IP assignment: DHCP
DNS 1:         192.168.100.10
DNS 2:         blank or 192.168.100.10
```

Never set public DNS such as `8.8.8.8` as tablet DNS 2. Some devices may use DNS 2 instead of the Pi and fail to resolve `woosoo.local`.

---

## 9. Forbidden Shortcuts

Do not introduce these shortcuts:

```txt
Tablet → POS directly
Tablet → printer directly
Tablet → MySQL directly
Print bridge → POS directly
Printer → Raspberry Pi Bluetooth directly
Client devices → raw Docker service ports
Client devices → Raspberry Pi IP as the normal app URL
```

All application traffic should pass through `https://woosoo.local` unless explicitly documented otherwise.

---

## 10. Operational Summary

```txt
Tablets use:        https://woosoo.local/tablet
Admin uses:         https://woosoo.local
Print bridge uses:  https://woosoo.local
Printer uses:       Bluetooth from print bridge tablet
POS is accessed by: woosoo-nexus only
DNS is served by:   Raspberry Pi dnsmasq
Production storage: M.2/NVMe SSD
Deployment method:  Docker Compose
```
