# Woosoo Print Bridge — Requirements & Overview

Woosoo Print Bridge is the Android app that connects a Bluetooth thermal printer to the Woosoo ordering system. It receives print jobs from the Nexus server over a WebSocket connection (Laravel Reverb) and sends them to the paired printer via Bluetooth.

---

## How It Works

```
Woosoo Nexus Server
        │
   WebSocket (Reverb)
        │
  Print Bridge App (Android)
        │
   Bluetooth
        │
  Thermal Printer (ESC/POS)
```

When a guest submits an order on the tablet, the Nexus server sends a print job over WebSocket. The Print Bridge app receives it and immediately sends it to the paired Bluetooth printer.

---

## Hardware Requirements

| Requirement | Specification |
|-------------|---------------|
| **Device** | Android phone or tablet |
| **Android Version** | 8.0 (Oreo) or higher |
| **Bluetooth** | Bluetooth 4.0 or higher |
| **RAM** | 2 GB minimum, 3 GB recommended |
| **Storage** | 100 MB free for app + print cache |
| **Power** | Must remain plugged in during service — do not run on battery |
| **Placement** | Keep within 10 meters of the printer |

---

## Printer Requirements

| Requirement | Specification |
|-------------|---------------|
| **Interface** | Bluetooth (not USB-only printers) |
| **Print type** | Thermal (ESC/POS command set) |
| **Paper width** | 58 mm or 80 mm |
| **Tested brands** | Epson, Star Micronics, Bixolon, Xprinter |
| **Power** | Must have its own power adapter — do not run on battery |

> Any ESC/POS compliant Bluetooth printer should work. If unsure, check the printer manual for "ESC/POS" and "Bluetooth" support.

---

## Network Requirements

- Android device must be on the **same WiFi network** as the Raspberry Pi server.
- Must be able to reach `https://woosoo.local` (same CA cert setup as tablets applies).
- The WebSocket connection to the Reverb server must not be blocked by a firewall or router.

---

## App Screens Overview

| Screen | Purpose |
|--------|---------|
| **Status** | Live connection indicator — server and printer health |
| **Queue** | Pending print jobs waiting to be processed |
| **Metrics** | Performance stats — jobs per hour, success rate, latency |
| **Tools** | Manual actions — test print, reconnect, purge queue |
| **Settings** | Server URL, WebSocket key, Printer ID, Registration Code |
| **Logs** | Application event log for debugging |
| **Orders History** | List of all print jobs (completed) |
| **Dead Letter** | Failed print jobs that could not be processed |

---

## Power Management (Important)

The Print Bridge app must run continuously during service. Android's battery optimization can kill background apps — disable it:

1. Go to **Android Settings → Apps → Woosoo Print Bridge**.
2. Tap **Battery → Unrestricted** (or "Don't optimize").
3. Enable **Settings → Display → Stay Awake** (if available in developer options).
4. Plug the device into a charger permanently.