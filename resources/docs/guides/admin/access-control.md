# How to Manage Access Control (Roles & Permissions)

**Access Control** allows you to define who can do what in the admin panel. Create **Roles** (e.g., Manager, Cashier, Bartender), assign **Permissions** to each role, and then assign roles to staff users.

---

## Why Access Control Matters

Different staff need different levels of access:

| Role | Can Access | Cannot Access |
|------|-----------|---|
| **Super Admin** | Everything | Nothing — full system access |
| **Manager** | Orders, Staff, Menu, Reports | User Management, Roles, Branches |
| **Cashier** | Orders, View Reports | Device Settings, POS Management |
| **Bartender** | Orders (bar section only) | Most admin features |

Without access control, any logged-in user can see and change everything. **Roles & Permissions prevent accidents and enforce security.**

---

## How to Access Access Control

1. Open Woosoo Nexus at `https://woosoo.local`
2. Sign in with admin credentials
3. Click **Access Control** from the left menu (under Configuration)
4. Two sub-pages appear:
   - **Roles** — define role templates
   - **Permissions** — available permissions in the system

---

## Understanding Roles

### Pre-built Roles

Woosoo comes with default roles:

| Role | Purpose | Typical User |
|------|---------|---|
| **Super Admin** | Full system access | Owner or IT Admin |
| **Admin** | Manage most features except backups | General Manager |
| **Manager** | View and edit orders, staff, menu | Shift Manager |
| **Staff** | View orders only, can't edit | Server or Bartender |

---

## How to Create a Custom Role

### Step 1: Open Roles Page

1. Click **Access Control → Roles**
2. Existing roles appear in a list
3. Click **+ Create Role**

### Step 2: Name the Role

| Field | Example |
|-------|---------|
| **Role Name** | "Shift Supervisor" |
| **Description** | "Can manage orders and staff, but cannot modify menus or users" |
| **Display Color** | Choose a color badge (helps visually identify the role) |

### Step 3: Assign Permissions

A permission list appears with checkboxes:

| Permission | What It Allows |
|-----------|---|
| `view orders` | View all orders |
| `create orders` | Create orders manually |
| `update orders` | Edit/void orders |
| `view menus` | View menu items |
| `update menus` | Edit menu items and availability |
| `view users` | View user list |
| `create users` | Add new users |
| `update users` | Edit user roles and details |
| `view devices` | View registered devices |
| `create devices` | Register new tablets/relays |
| `view reports` | View reports |
| `access dashboard` | Access admin settings |
| `view permissions` | Manage roles & permissions (dangerous!) |

### Step 4: Save the Role

Click **Create Role**. The custom role now appears in the Roles list and can be assigned to users.

---

## How to Assign a Role to a User

### Via User Management

1. Go to **User Management**
2. Click on a user
3. In the user detail panel, find the **Role** dropdown
4. Select a role (e.g., "Manager", "Shift Supervisor")
5. Click **Save**

**Expected result:** User now has all permissions granted to that role.

---

## Understanding Permissions

### Permission Categories

| Category | Example Permissions |
|----------|---|
| **Orders** | view, create, edit, void, print |
| **Menu** | view, edit, publish |
| **Users** | view, create, edit, deactivate |
| **Devices** | view, register, edit, deactivate |
| **Reports** | view, export |
| **Settings** | view, edit (only for Super Admins) |

### Permission Naming Convention

Permissions follow a pattern:
- **`<feature>:<action>`**
  - `order:view` — can view orders
  - `menu:edit` — can edit menus
  - `user:create` — can add users

---

## How to Create Custom Permissions

**Advanced:** If your workflow requires new permissions (e.g., `report:export`):

1. Go to **Permissions** page (under Access Control)
2. Click **+ Add Permission**
3. Enter:
   - **Permission Name:** `report:export`
   - **Description:** "Can export reports to CSV/PDF"
4. Click **Create**
5. Now assign this permission to roles that should have it

---

## Common Access Control Scenarios

### Scenario: Create a "Bartender" Role

**Goal:** Bartenders can see orders for bar items, but can't edit menu or see POS data.

**Steps:**
1. Click **Access Control → Roles → + Create Role**
2. Name: "Bartender"
3. Description: "Can view and manage bar orders only"
4. Assign permissions:
   - ✅ `order:view`
   - ❌ `order:create` (orders come from tablet)
   - ❌ `menu:edit`
   - ❌ `user:edit`
5. Save

**Then:**
1. Go to **User Management**
2. Assign "Bartender" role to bar staff
3. They log in and see only Orders page (other pages hidden)

---

### Scenario: Restrict a Manager from Deleting Users

**Current problem:** Manager role has `user:edit` permission, which also allows them to delete users.

**Solution (if fine-grained permissions needed):**
1. Create a custom permission `user:delete`
2. Separate from `user:edit`
3. Remove `user:delete` from Manager role
4. Now managers can edit user names/emails, but cannot delete them

---

### Scenario: Audit Who Has Accessed What

**View:** Go to **Event Logs** to see all user actions.

**Columns:**
- User (who did it)
- Action (what they did)
- Resource (what they acted on)
- Timestamp (when)

**Filter:** Click **Filter by Role** to see all actions by users with a specific role.

---

## Best Practices

✅ **Follow the principle of least privilege** — give each role only the permissions it needs.

✅ **Name roles by job function** — "Bartender", "Cashier", "Shift Supervisor" are clearer than "Role_A", "Role_B".

✅ **Test new roles** — create a test user with the role and verify they can/can't access what you expect.

✅ **Review permissions quarterly** — ensure staff still have appropriate access (remove old staff, add new permissions as features change).

✅ **Document your roles** — in a shared note or training doc, list what each role can do.

---

## Troubleshooting

**Problem:** A user logs in and can't see the Orders page, even though they have `order:view` permission.

**Diagnosis:**
1. Check their assigned role: **User Management** → click user → see Role field
2. Check the role's permissions: **Access Control → Roles** → click role → verify `order:view` is checked
3. Have them log out completely and log back in (permissions cache in session)

**Solution:**
- Assign the correct role
- Re-check the permission checkbox
- Have user clear browser cache or use incognito mode to re-login

---

**Problem:** A new permission I created isn't showing in the Role edit page.

**Diagnosis:**
1. Go to **Permissions** page — verify the permission exists
2. Go back to **Roles** page and refresh the browser (may need cache clear)

**Solution:**
- Hard refresh: `Ctrl+Shift+R` (Windows) or `Cmd+Shift+R` (Mac)
- Clear browser cache for `https://woosoo.local`

---

## Next Steps

- [Add User Guide](add-user.md) — assign newly created roles to staff
- [Event Logs Guide](event-logs.md) — audit which user actions each role takes
- [Monitoring Guide](monitoring.md) — ensure all users stay connected and active
