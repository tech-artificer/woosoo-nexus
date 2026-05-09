# How to Log In

## Access the Dashboard

1. Open your web browser (Chrome or Edge recommended).
2. In the address bar, type: **`https://woosoo.local`** and press Enter.
   - If the page doesn't load, try: **`http://192.168.100.42`**
3. You will be redirected to the login page at `/login`.

---

## Sign In

1. Enter your **Email address** in the first field.
2. Enter your **Password** in the second field.
3. (Optional) Check **Remember me** to stay signed in across sessions.
4. Click **Sign In**.

**Expected result:** You are redirected to the Dashboard (`/dashboard`) showing today's sales summary, order stats, and charts.

---

## If Login Fails

| Problem | What to Do |
|---------|-----------|
| "These credentials do not match our records" | Double-check email and password. Passwords are case-sensitive. |
| "Your account has been suspended" | Contact your Super Admin to reactivate the account. |
| Page shows a security warning | Your browser does not trust the server certificate yet — see the Requirements guide for how to install the CA cert. |
| Page does not load at all | Confirm your device is on the same WiFi network as the Raspberry Pi. |
| Forgot password | Click **"Forgot your password?"** on the login page and follow the email reset instructions. |

---

## Logging Out

1. Click your name or avatar in the top-right corner.
2. Select **Log Out** from the dropdown.
3. You are returned to the login page.

> **Security note:** Always log out when leaving a shared computer. Sessions expire automatically after 2 hours of inactivity.

---

## First-Time Login (New Account)

If this is your first login:
1. You should receive your credentials via email from your Super Admin.
2. Log in using the provided temporary password.
3. Navigate to **Settings → Password** (top-right menu) to set a new password immediately.