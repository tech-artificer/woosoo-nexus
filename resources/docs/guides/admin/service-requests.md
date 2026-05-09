# How to Manage Service Requests

**Service Requests** are in-app calls for help that guests submit while using the tablet. The admin dashboard shows all active requests and their status, allowing staff to respond quickly.

---

## What Are Service Requests?

Guests use the **Call Staff** button on the tablet to:
- Ask a question (e.g., "What's in this dish?")
- Report a problem (e.g., "We haven't received our order")
- Request a refill or check

These requests appear in the admin panel so staff can respond immediately.

---

## How to Access Service Requests

1. Open Woosoo Nexus at `https://woosoo.local`
2. Sign in with admin credentials
3. Click **Service Requests** from the left menu
4. The requests list appears with filters and status indicators

---

## Understanding the Service Requests List

### Request Card Layout

Each request shows:

| Element | Meaning |
|---------|---------|
| **Table/Guest Number** | Table ID or session identifier |
| **Device Name** | Which tablet initiated the request (e.g., "Tablet 1", "Kiosk A") |
| **Request Type** | Category — "Question", "Problem", or "Refill" |
| **Status Badge** | **New** (unread), **In Progress** (acknowledged), **Resolved** (completed) |
| **Timestamp** | When the request was sent |
| **Action Buttons** | Acknowledge, Resolve, or Mark Complete |

---

## How to Respond to a Request

### Step 1: View Pending Requests

Filter by **Status = "New"** to see unacknowledged requests.

### Step 2: Acknowledge the Request

1. Click the request card
2. A detail panel opens showing:
   - Full request text / type
   - Table/session info
   - Device location
3. Click **Acknowledge** to mark it as "In Progress"
   - Woosoo sends a notification to the guest's tablet: "Staff received your request"

### Step 3: Respond in Person or In-App

- **In Person:** Go to the table/device and assist the guest
- **In App:** Some requests (e.g., menu questions) can be answered by sending a message back to the device

### Step 4: Mark as Resolved

Once the guest is helped:

1. Return to the request in the admin panel
2. Click **Resolve** or **Mark Complete**
3. Request moves to "Resolved" status
4. Tablet guest sees confirmation: "Your request has been handled"

---

## Request Types & How to Handle Them

### Type: Question

**Guest asks:** "What's in the Chicken Parmesan?"

**How to handle:**
1. Acknowledge immediately
2. Answer in person (describe the dish) or send a brief text response if supported
3. Mark as Resolved

**Example responses:**
- "Chicken breast, marinara, mozzarella, served with pasta"
- "Yes, it's gluten-free"
- "We have nut-free versions available"

---

### Type: Problem

**Guest reports:** "We haven't received our order yet" or "This drink is too cold"

**How to handle:**
1. Acknowledge immediately
2. In Person:
   - Check the kitchen queue in Woosoo (Orders page) to verify order status
   - If order is printing → explain ~5 min wait
   - If order is complete → check if it got lost, remake if needed
   - If order failed → show guest and offer alternative
3. Mark as Resolved once issue is fixed

---

### Type: Refill

**Guest requests:** "More water, please" or "Another beer"

**How to handle:**
1. Acknowledge (staff knows they're coming)
2. Go to the table with the item
3. Mark as Resolved
4. Tablet guest sees: "Your refill is on the way" → then "Refill delivered"

---

## Service Request Dashboard Insights

### Quick Stats

- **Pending Requests:** How many are "New" and need attention
- **Average Response Time:** How long from request to acknowledgment
- **Resolved Today:** Count of completed requests
- **Busiest Device:** Which tablet gets most requests (helps identify high-traffic areas)

---

## Common Scenarios

### Scenario: A request is marked "New" but staff already helped the guest

**Solution:**
1. Open the request
2. Click **Resolve** to close it
3. Request moves to history (no longer shows as pending)

---

### Scenario: Guest's tablet is not responding (request sent but no acknowledgment received)

**Problem:** Network lag or device disconnected before acknowledging.

**Solution:**
1. Go in person and help the guest anyway
2. Manually mark the request as Resolved in the admin panel
3. Tablet will sync when reconnected

---

### Scenario: Too many "Problem" requests coming from one table

**Diagnosis:**
1. Check the **Orders** page to see if orders from that table are failing
2. Check if there's a consistent issue (e.g., all orders printing as blank, or kitchen is very slow)
3. Go assist the table in person

**Solution:**
- If kitchen delay → communicate expected time
- If order failed → remake from scratch
- If device issue → restart the tablet if needed

---

## Best Practices

✅ **Acknowledge within 30 seconds** — guests expect fast response.

✅ **Respond in person** — Service Requests usually need human help, not just notifications.

✅ **Log resolution time** — track average response time to identify staffing gaps.

✅ **Use during peak hours** — this feature shines when staff can't monitor every table visually.

✅ **Monitor problematic devices** — if one tablet generates many "Problem" requests, it may need diagnosis.

---

## Troubleshooting

**Problem:** Service Requests page shows no requests even though guests have called for staff.

**Diagnosis:**
1. Check if tablet devices are registered in **Woosoo Nexus → Devices**
2. Check if WebSocket connection is healthy (**Monitoring** page should show Reverb as green)
3. Check tablet app — ensure it's the latest version

**Solution:**
- Restart the tablet app
- Restart Reverb service: `sudo supervisorctl restart laravel-reverb`

---

## Next Steps

- [Manage Orders](manage-orders.md) — respond to service requests by checking order status
- [Monitoring Guide](monitoring.md) — ensure tablets stay connected
- [Event Logs](event-logs.md) — audit all service request activities
