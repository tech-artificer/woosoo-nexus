# How to Manage Menu Availability

Menu availability lets you mark items as sold out instantly. When an item is toggled off, it grays out on all tablets immediately — guests cannot add it to their cart. Toggling it back on restores it instantly.

---

## Toggling a Single Item

1. Click **Menus** in the left sidebar.
2. Find the item you want to update. Use the **Search** bar or browse by category.
3. Locate the **Availability** toggle switch on the item's row.
4. Click the toggle:
   - **Green (On)** = Available — guests can order this item
   - **Gray (Off)** = Sold Out — item is visible but cannot be added to cart

**Expected result:** The change propagates to all connected tablets in real-time via WebSocket. No tablet refresh is needed.

---

## Menu Stats Cards

At the top of the Menus page, four stats cards show:

| Card | What it Shows |
|------|--------------|
| **Total Items** | Total number of menu items in the system |
| **Available** | Items currently available for ordering |
| **Unavailable** | Items currently marked as sold out |
| **Categories** | Number of distinct menu categories |

These numbers update whenever you toggle an item.

---

## Understanding the Menu List

Each menu item row displays:
- Item name and category
- Price
- Krypton Menu ID (the reference ID from the POS system)
- Availability toggle
- Last updated timestamp

---

## Bulk Availability (Opening / Closing Routine)

To quickly set all items available at opening or all items unavailable at closing, contact your Super Admin — bulk operations are available through the system but require appropriate permissions.

---

## Packages vs. Menu Items

Note that **Packages** (e.g., eat-all-you-can packages) are managed separately under the **Packages** page. Availability of individual menu items does not affect package availability — packages have their own active/inactive toggle.

---

## Tablet Categories

The **Tablet Categories** section (under the Configuration area) controls which menu categories appear on the tablet and in what order. This is separate from menu item availability:

| Setting | Where |
|---------|-------|
| Mark item sold out | Menus → toggle switch |
| Change what categories tablets show | Tablet Categories page |
| Activate/deactivate a package | Packages page → Active toggle |

---

## Troubleshooting

| Problem | Fix |
|---------|-----|
| Toggle change not showing on tablets | Confirm tablets are online and connected to the same network. Check WebSocket status in Monitoring. |
| Item still orderable after toggling off | Hard-refresh the tablet browser (hold power + volume or clear cache in PWA settings). |
| Cannot find the item | Use the search bar at the top of the Menus page. Items from Krypton POS sync automatically. |