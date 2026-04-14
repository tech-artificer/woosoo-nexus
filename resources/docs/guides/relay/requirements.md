# Printer Relay System Requirements

## Hardware Requirements
- Android device (phone or tablet) running Android 8.0+
- Bluetooth 4.0+ support
- Minimum 2GB RAM
- Device must remain powered on and charged

## Printer Compatibility
- ESC/POS thermal printers (58mm or 80mm)
- Bluetooth-enabled printers
- Tested brands: Epson, Star Micronics, Bixolon

## Network Requirements
- Stable WiFi connection
- Minimum 2 Mbps upload/download speed
- WebSocket support for real-time job receiving
- Access to admin server URL

## Installation Steps
1. Download the Relay app APK
2. Enable "Install from Unknown Sources" in Android settings
3. Install the APK file
4. Grant required permissions:
   - Bluetooth
   - Storage (for print job caching)
   - Network access
5. Open app and enter device token from admin panel

## Power Management
- Disable battery optimization for the relay app
- Keep device plugged into power source
- Use "Stay Awake While Charging" developer option