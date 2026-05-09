# How to Manage Orders

The Orders page is the operational heart of the admin dashboard. It shows incoming orders in real-time and lets you complete, print, and void them.

---

## Navigating to Orders

Click **Orders** in the left sidebar.

The page has two tabs:

| Tab | What it shows |
|-----|--------------|
| **Live Orders** | Active orders — pending, processing, and recently completed |
| **History** | Searchable archive of all past orders with filters |

---

## Live Orders Tab

Orders appear automatically as guests submit them from the tablets — no refresh needed. A sound ping plays when a new order arrives.

Each order card shows:
- **Table number** and **order number**
- **Guest count**
- **Number of items**
- **Status** (Pending, Processing, Completed, Voided)
- **Time received**

---

## Viewing Order Details

1. Click on any order card or row to open the **Order Detail Sheet** (slides in from the right).
2. The sheet shows:
   - Full item list with quantities, modifiers, and notes
   - Package information (if applicable)
   - Order status history with timestamps
   - Printer status (printed / not printed)
   - Service request history for this table

---

## Completing an Order

When food has been served and the session is done:

1. Open the order detail sheet.
2. Click **Mark as Completed**.
3. Confirm the action.

**Expected result:** The order moves to the **History** tab. The table is freed for the next session.

---

## Printing an Order

Orders are normally printed automatically when submitted. If a receipt needs to be reprinted:

1. Open the order detail sheet.
2. Click **Print**.
3. The print job is sent to the registered relay device for that branch.
4. The relay device forwards the job to the connected Bluetooth printer.

> If no relay is connected, the print job goes into the queue. Check **Monitoring** to see queue status.

---

## Voiding an Order

Use this to cancel an order due to input error, guest cancellation, or other operational reason:

1. Open the order detail sheet.
2. Click **Void Order**.
3. Enter an optional reason for the void.
4. Click **Confirm Void**.

**Expected result:** The order is marked **Voided** and excluded from revenue reports. The void is logged in Event Logs for audit purposes.

> **Note:** Voiding cannot be undone. If the guest wants to reorder, they must submit a new order from the tablet.

---

## Searching & Filtering History

1. Click the **History** tab.
2. Use the filters at the top:
   - **Search** by order number or table number
   - **Date range** picker
   - **Status** filter (Completed, Voided)
   - **Branch** filter (Super Admin / Admin only)
3. Results update instantly.

---

## Service Requests (Linked Feature)

While monitoring orders, also check the **Service Requests** page. Guests can call for attention from their tablet — this is separate from ordering and appears in its own section.

---

## Monitoring & System Health

If orders are not appearing or prints are failing, go to the **Monitoring** page (sidebar). It shows:
- Unprinted orders
- Failed print events
- Queue pending/failed jobs
- Orphaned (stuck) orders
- Database health status

---

## Troubleshooting Orders

| Problem | Fix |
|---------|-----|
| New order didn't appear | Check WebSocket connection. Refresh the page. If orders still don't appear, check Reverb status on the Pi. |
| Print button has no effect | Verify a relay device is registered and connected. Check Monitoring → Print Queue. |
| Order stuck in "Pending" | Check if the POS (Krypton) database connection is healthy under Configuration → POS Connection. |
| Cannot void an order | Only Admins and above can void. Check your role permissions. |