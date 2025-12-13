Admin User Manual — Woosoo Nexus

Purpose
- A concise, user-facing guide for administrators using the web admin app.
- Place: `docs/admin_manual.md` (rendered in repo, suitable for PDF export).

Overview
- The admin app provides CRUD for branches, devices, menus, orders, users, roles, and system controls (Reverb, queues).
- URL: the admin app runs on the same host as the API (e.g., `http://localhost/` in local dev). Access via browser and sign in with your admin account.

Access & prerequisites
- You must have an admin user with appropriate permissions (`spatie/laravel-permission` roles). If locked out, contact site owner or follow the `docs/ROLES_IMPLEMENTATION_COMPLETE.md` procedures.
- Recommended: use Chrome/Edge and ensure cookies are enabled for authentication.

Common Tasks (step-by-step)

- Sign in
  1. Open the admin URL (e.g., `http://localhost/`).
 2. Click "Sign in" and enter your admin credentials.

- Viewing orders
  - Navigate: left menu → Orders.
  - Use filters (status, branch, date) at top to narrow results.
  - Click an order to see details, print actions, and order items.

- Managing devices (pre-provision static IPs)
  - Navigate: Devices → Create.
  - Fill fields: `name`, `branch`, `table` (optional), `ip_address` (enter static IP).
  - Validation: `ip_address` must be unique. If the system rejects the IP, choose another or check existing devices.
  - Save: click `Create` to persist.

- Generating a device token (for pre-provisioning printers or static devices)
  - Navigate: Devices → Edit → open the device record.
  - Click "Generate Token" (Admin only).
  - The app will return a personal access token valid for 1 year labeled `admin-issued`.
  - Copy & store this token securely — treat as a secret. Use it as `Authorization: Bearer <token>` on the device.
  - To revoke: use the device's token management section (or delete the token in UI/DB).

- Assigning a device to a table
  - Open the device and click `Assign table` (or `devices/{device}/assign-table`).
  - Select the table and save.

- Printer temporary workaround (visible change)
  - Note: printer endpoints were temporarily made guest-accessible to assist the print team. This is temporary and marked in docs.
  - Location: `docs/printer_manual.md` describes usage and risks.

Security best practices for admins
- Treat device tokens like passwords — store securely and rotate if compromised.
- Use IP whitelisting or shared secrets for printers where possible.
- Audit: check application logs `storage/logs/laravel.log` for suspicious calls to printer endpoints or device token creation events.

Troubleshooting
- Login issues: clear cookies, use an incognito window, or check `APP_URL` in `.env`.
- Token issues: verify token expiry and that the device's token is active in `personal_access_tokens` table.
- Printer failures: check network connectivity between printer and app host; see `docs/printer_manual.md` for smoke-test commands.

Export to PDF (recommended for handouts)
- Option A — Pandoc (if installed):

```powershell
# from repo root (PowerShell)
pandoc docs/admin_manual.md -o docs/admin_manual.pdf
```

- Option B — VS Code extension: use "Markdown PDF" or print-to-PDF from the editor.

Where to place the manual
- `docs/admin_manual.md` (created). For visibility, add a link to `docs/api.md` or your internal docs index:
  - e.g. add a line in `docs/api.md` under Admin docs: `- Admin manual: docs/admin_manual.md`

Next steps I can take
- Add screenshots or annotated images for the most common flows (requires screenshots uploaded to `docs/images/`).
- Generate a PDF and attach to repository (if you want me to create it here, confirm and I will attempt to create a PDF using `pandoc` if available on the host).
- Implement a temporary IP-whitelist middleware for printer endpoints and update `routes/api.php` to use it.

File: docs/admin_manual.md
