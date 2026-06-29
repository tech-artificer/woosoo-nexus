---
status: canonical
last_reviewed: 2026-06-15
scope: woosoo-nexus
---

# WebSocket Events Contract

Authoritative registry of all broadcast event names, channels, and payload shapes.
The `BroadcastEvent` enum is the source of truth for event names; every class, channel, and payload shape listed here is mirrored by the implementation in `app/Events/Order/` and `app/Events/Kds/`.

## Events

### `order.created` — Order Created

| Property | Value |
|---|---|
| **Class** | `App\Events\Order\OrderCreated` |
| **BroadcastEvent** | `BroadcastEvent::OrderCreated` |
| **broadcastAs()** | `order.created` |
| **Channels** | `admin.orders`, `orders.{order_id}`, `device.{device_id}` |

**Payload shape:**
```json
{
  "order": OrderBroadcastPayload
}
```

Fired when a new order is created and enters `pending` or `confirmed` state.

---

### `order.updated` — Order Status Updated

| Property | Value |
|---|---|
| **Class** | `App\Events\Order\OrderStatusUpdated` |
| **BroadcastEvent** | `BroadcastEvent::OrderUpdated` |
| **broadcastAs()** | `order.updated` |
| **Channels** | `orders.{order_id}`, `device.{device_id}`, `admin.orders` |

**Payload shape:**
```json
{
  "order": OrderBroadcastPayload
}
```

Fired when an order's status changes (e.g., `confirmed` → `in_progress`, `in_progress` → `ready`). Includes state transition on KDS advance or recall.

---

### `order.completed` — Order Completed

| Property | Value |
|---|---|
| **Class** | `App\Events\Order\OrderCompleted` |
| **BroadcastEvent** | `BroadcastEvent::OrderCompleted` |
| **broadcastAs()** | `order.completed` |
| **Channels** | `orders.{order_id}`, `admin.orders` |

**Payload shape:**
```json
{
  "order": OrderBroadcastPayload
}
```

Fired when an order reaches terminal state `completed` (bill paid at POS). Order is removed from KDS board and hidden from active views.

---

### `order.voided` — Order Voided

| Property | Value |
|---|---|
| **Class** | `App\Events\Order\OrderVoided` |
| **BroadcastEvent** | `BroadcastEvent::OrderVoided` |
| **broadcastAs()** | `order.voided` |
| **Channels** | `orders.{order_id}`, `admin.orders` |

**Payload shape:**
```json
{
  "order": OrderBroadcastPayload
}
```

Fired when an order is voided (cancelled by POS). Order is terminal; KDS cannot recall a voided order (must create a new ticket instead).

---

### `order.cancelled` — Order Cancelled

| Property | Value |
|---|---|
| **Class** | `App\Events\Order\OrderCancelled` |
| **BroadcastEvent** | `BroadcastEvent::OrderCancelled` |
| **broadcastAs()** | `order.cancelled` |
| **Channels** | `orders.{order_id}`, `admin.orders` |

**Payload shape:**
```json
{
  "order": OrderBroadcastPayload
}
```

Fired when an order is cancelled before fulfillment. Order is removed from active views.

---

### `order.details.updated` — Order Details Updated

| Property | Value |
|---|---|
| **Class** | `App\Events\Order\OrderDetailsUpdated` |
| **BroadcastEvent** | `BroadcastEvent::OrderDetailsUpdated` |
| **broadcastAs()** | `order.details.updated` |
| **Channels** | `orders.{order_id}`, `admin.orders` |

**Payload shape:**
```json
{
  "order": OrderBroadcastPayload
}
```

Fired when order details change (totals, item list, guest count) without status transition. Distinct from `order.updated` (status changes). Sourced from POS order-detail outbox. Tablet redraws totals and items as eventually-consistent; KDS does not subscribe to this event (KDS state is controlled by kitchen status, not POS details).

---

### `item.toggled` — Item Done Status Toggled

| Property | Value |
|---|---|
| **Class** | `App\Events\Kds\ItemToggled` |
| **BroadcastEvent** | `BroadcastEvent::ItemToggled` |
| **broadcastAs()** | `item.toggled` |
| **Channels** | `admin.orders`, `orders.{order_id}`, `device.{device_id}` (if present) |

**Payload shape (flat, not wrapped):**
```json
{
  "item_id": 1,
  "order_id": 2,
  "done": true,
  "done_at": "2026-06-15T14:30:45Z"
}
```

Fired when a kitchen staff member marks an item done or undone. Only `item_id` is required; `order_id`, `done`, and `done_at` must all be present.

---

## OrderBroadcastPayload Key Set

The `OrderBroadcastPayload::make(DeviceOrder $order): array` method produces the canonical payload shape for all order events (except `item.toggled`, which is flat).

**Top-level keys:**

| Key | Type | Description |
|---|---|---|
| `id` | `int` | Device order ID (app-DB primary key) |
| `order_id` | `string` | POS order ID (external reference) |
| `order_number` | `int\|string` | Display number for POS receipt |
| `device_id` | `int` | Kiosk/device ID |
| `table_id` | `int\|null` | Dining table ID (null if delivery/pickup) |
| `branch_id` | `int` | Branch/location ID |
| `session_id` | `int` | POS daily session ID |
| `status` | `OrderStatus` | Order status enum case (e.g., `confirmed`, `in_progress`, `ready`) |
| `kds_state` | `string` | KDS-facing state: `new`, `preparing`, `served`, `voided` |
| `kds_type` | `string` | `initial` or `refill` (based on `is_refill` flags) |
| `is_printed` | `bool` | Whether ticket has been printed |
| `printed_at` | `string\|null` | ISO-8601 timestamp of print event |
| `printed_by` | `string\|null` | User/system name that printed |
| `subtotal` | `float\|null` | Order subtotal (ex. tax) |
| `tax` | `float\|null` | Tax amount |
| `discount` | `float\|null` | Discount amount |
| `total` | `float\|null` | Order total (incl. tax, after discount) |
| `guest_count` | `int\|null` | Number of guests |
| `created_at` | `string` | ISO-8601 timestamp of order creation |
| `updated_at` | `string` | ISO-8601 timestamp of last modification |
| `device` | `object\|null` | Device info |
| `table` | `object\|null` | Table info |
| `items` | `array` | Array of item objects |
| `recalled` | `int` | Number of times order has been recalled (KDS) |
| `void_reason` | `string\|null` | Reason captured when an order is voided from the KDS (null otherwise) |
| `serviceRequests` | `array` | Array of service requests |

**Device object (nested):**

| Key | Type |
|---|---|
| `id` | `int` |
| `name` | `string` |

**Table object (nested):**

| Key | Type |
|---|---|
| `id` | `int` |
| `name` | `string` |

**Item object (array element):**

| Key | Type | Description |
|---|---|---|
| `id` | `int` | Device order item ID |
| `name` | `string` | Item name (from menu receipt name, fallback to item name) |
| `quantity` | `int` | Ordered quantity |
| `price` | `float\|null` | Unit price |
| `subtotal` | `float\|null` | Line subtotal (qty × price) |
| `is_refill` | `bool` | Whether this is a refill request |
| `done` | `bool` | Whether kitchen marked it complete |
| `done_at` | `string\|null` | ISO-8601 timestamp when marked done |
| `notes` | `string\|null` | Special instructions/allergen notes |
| `type` | `string\|null` | Item type tag (e.g., beverage, food) |

---

## Channel Routing Summary

| Channel | Listeners | Events |
|---|---|---|
| `admin.orders` | KDS board, HQ console, monitoring dashboards | All 7 events |
| `orders.{order_id}` | Tablet (per-order subscription), legacy POS integrations | `order.created`, `order.updated`, `order.details.updated`, `order.completed`, `order.voided`, `order.cancelled`, `item.toggled` |
| `device.{device_id}` | Device-specific feeds (legacy) | `order.created`, `order.updated`, `item.toggled` |

**KDS board subscribes to:** `admin.orders` only.

---

## Implementation Notes

### POS-Down Resilience

`OrderBroadcastPayload::make()` wraps POS-backed relation loads (`device.table`, `table`, `items.menu`) in a try/catch. If POS is unreachable:
- `table` object resolves to `null`
- `device.table` resolves to `null`
- Item `name` falls back to the stored item name (not menu receipt name)

This ensures a broadcast never fails if POS is temporarily down.

### Payload Uniqueness

- Order events (`order.created`, `order.updated`, etc.) wrap the payload as `{ "order": OrderBroadcastPayload }`.
- `item.toggled` sends a flat payload with no wrapper.
- Frontend must unwrap order events before processing (see `useKdsEcho.ts` `handleOrderEvent`).

### State Mapping

KDS state (`kds_state`) is derived from backend `OrderStatus`:

| Backend Status | `kds_state` |
|---|---|
| `pending`, `confirmed` | `new` |
| `in_progress`, `ready` | `preparing` |
| `served` | `served` |
| `voided` | `voided` |
| `completed`, `cancelled`, `archived` | (not broadcast; order hidden) |

---

## References

- `app/Broadcasting/BroadcastEvent.php` — Source of truth for event names (enum cases)
- `app/Broadcasting/OrderBroadcaster.php` — Intent-based broadcast boundary
- `app/Events/Order/` — Event class implementations
- `app/Events/Kds/ItemToggled.php` — Item toggle event
- `app/Helpers/OrderBroadcastPayload.php` — Canonical payload builder
- `contracts/order-state.contract.md` — Order status state machine
