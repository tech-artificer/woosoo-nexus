# How to Troubleshoot Printing Issues

## Problem: Relay App Shows "Offline"

### Steps to Fix
1. Check WiFi connection on Android device
2. Verify server URL is correct in settings
3. Restart WiFi on the device
4. Tap **Reconnect** in the app
5. If still offline, restart the app completely

## Problem: Printer Not Printing

### Steps to Fix
1. Check printer power (LED should be on)
2. Check Bluetooth connection (green indicator in app)
3. Verify paper is loaded correctly
4. Check if printer is in error state (flashing red LED)
5. Tap **Test Print** to isolate issue
6. If test fails, reconnect Bluetooth:
   - Tap **Disconnect Printer**
   - Wait 5 seconds
   - Tap **Scan for Printers**
   - Select and connect your printer

## Problem: Print Quality Issues

### Steps to Fix
- **Faint printing:** Replace thermal paper or clean printer head
- **Partial printing:** Check paper alignment and width setting
- **Garbled text:** Printer model may be incompatible, check compatibility list

## Problem: Delayed Printing

### Steps to Fix
1. Check WiFi signal strength (should be 3+ bars)
2. Move device closer to WiFi router
3. Clear print job cache: Settings > Clear Cache
4. Restart the relay app

## Problem: App Crashes or Freezes

### Steps to Fix
1. Force stop the app: Android Settings > Apps > Relay > Force Stop
2. Clear app cache (not data): Settings > Storage > Clear Cache
3. Restart the Android device
4. If persistent, reinstall the app

## When to Contact Support
- Error codes appear repeatedly
- Printer not detected after multiple pairing attempts
- App crashes on launch
- Orders received but not printing (check server-side issues)

## Emergency Workaround
If relay is completely non-functional:
1. Log into admin dashboard
2. Navigate to affected order
3. Use **Print to Browser** option
4. Print using kitchen computer printer as fallback