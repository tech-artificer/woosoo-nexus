# How to Connect a Bluetooth Printer

After installing Woosoo Print Bridge and registering the device, the next step is to pair the Android device with the thermal printer and connect it in the app.

---

## Before You Start

- The thermal printer must be powered on.
- The Android device must have Bluetooth enabled.
- The Print Bridge app must already be installed and registered (showing "Server: Connected" on the Status screen).

---

## Step 1 — Power On and Enter Pairing Mode

1. Turn on the thermal printer (power switch or button).
2. The printer should automatically enter pairing mode on first power-on.
3. If not, **press and hold the Feed button** until the LED blinks rapidly (rapid blinking = pairing mode active).
4. The printer may also print a self-test receipt showing "PAIRING MODE" — this is normal.

---

## Step 2 — Pair the Printer in Android Bluetooth Settings

1. On the Android device, go to **Settings → Bluetooth**.
2. Make sure Bluetooth is **ON**.
3. Tap **Pair New Device** (or "Scan" / "Search").
4. Wait for the printer to appear in the **Available Devices** list (usually named something like `RPP02N`, `Xprinter-40`, `BT Printer`, or the brand name).
5. Tap the printer name.
6. If prompted for a PIN, enter **`0000`** or **`1234`** (most thermal printers use one of these).
7. Wait for **"Paired"** status to appear next to the printer name.

> The printer is now paired at the Android OS level. You still need to select it inside the Print Bridge app.

---

## Step 3 — Select the Printer in the App

1. Open the **Woosoo Print Bridge** app.
2. Navigate to the **Settings** screen (bottom navigation).
3. Find the **Printer** section.
4. Tap **Scan / Select Printer**.
5. A list of paired Bluetooth devices appears.
6. Tap your printer's name.
7. Tap **Connect**.

**Expected result:** The Status screen updates to show:
- 🟢 **Server: Connected**
- 🟢 **Printer: Connected**

---

## Step 4 — Send a Test Print

1. Navigate to the **Tools** screen in the app.
2. Tap **Test Print**.
3. The printer should print a test receipt containing:
   - Date and time
   - Device name
   - "Woosoo Print Bridge — Test Print OK"

If the test receipt prints successfully, the relay is fully operational.

---

## Step 5 — Verify in Admin Dashboard

1. Open the admin dashboard at `https://woosoo.local`.
2. Go to **Devices** in the sidebar.
3. Find the relay device — it should show a green **Active** status and a recent "Last Seen" timestamp.

---

## Troubleshooting Printer Connection

| Problem | Fix |
|---------|-----|
| Printer doesn't appear during Android Bluetooth scan | Turn the printer off and back on. Put it back in pairing mode (hold Feed button). Make sure you're within 5 meters. |
| PIN prompt — what PIN to use | Try `0000`, then `1234`. Check the printer's quick-start guide if neither works. |
| Printer appears as "Paired" in Android but the app can't connect | Open Android Bluetooth settings, tap the printer name → **Forget** → Re-pair it, then try again in the app. |
| Test print shows garbled characters | The printer may use a different baud rate or paper width setting. Contact your system admin to verify ESC/POS configuration. |
| Print quality is faint or streaky | Replace the thermal paper roll. Clean the print head with a dry lint-free cloth. |
| App shows printer as Connected but jobs don't print | Check the **Queue** screen for stuck jobs. Tap the job → **Retry**. If dead letters accumulate, see the Troubleshooting guide. |
| Connection drops after a few minutes | Disable Android battery optimization for the Print Bridge app (see Installation guide). Keep the printer plugged in and close to the device. |