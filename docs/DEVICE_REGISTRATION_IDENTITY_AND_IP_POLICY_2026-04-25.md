# Device Registration Identity and IP Policy (2026-04-25)

**Status:** Implemented — tests and env docs pending  
**Scope:** `woosoo-nexus` + `tablet-ordering-pwa`  
**Trigger:** Device registration/settings intermittently fail with `Device not found` while backend
observes `172.18.x.x` (Docker bridge) instead of tablet LAN IP.

---

## Problem Summary

In Docker deployments, `$request->ip()` can return the nginx container address (e.g. `172.18.0.3`)
instead of the tablet's LAN address (e.g. `192.168.100.7`) if proxies are not trusted. This breaks
IP-based device lookup because the stored `ip_address` on the device record is the tablet's real
LAN IP, not the Docker bridge address.

**Current stack status:** This problem does NOT currently occur because:
- `TrustProxies` middleware is set to `$proxies = '*'` (trusts all proxies)
- nginx sets `HTTP_X_FORWARDED_FOR $proxy_add_x_forwarded_for` on all FastCGI requests
- Laravel therefore resolves the real tablet IP from the forwarded header

If `TrustProxies` were misconfigured or an additional proxy hop stripped `X-Forwarded-For`,
the problem would reappear. The client-supplied IP trust gate (`DEVICE_ALLOW_CLIENT_SUPPLIED_IP`)
exists as a documented escape hatch for that scenario.

Affected flows (when `TrustProxies` is broken):
- `GET /api/devices/login` — IP-only reconnect after token expiry
- `GET /api/device/lookup-by-ip` — print bridge bootstrap

Registration via security code is **never affected** — it identifies devices by code, not IP.

---

## Product Contract (Authoritative)

1. **Primary identity is `security_code` at first registration, then Sanctum bearer token.**
2. **IP address is mutable metadata, not durable identity.**
3. **Router/network changes must not require device re-provisioning.**

This contract applies to registration, authentication, and settings recovery flows.

---

## What Is Implemented

### Security-code-first registration (`POST /api/devices/register`)

- Device matched by iterating all active devices and calling `Hash::check(input, stored_hash)`
- IP plays no role in matching — code is the sole identity gate
- On success: code consumed (`security_code = null`), IP stored as metadata only
- Rate-limited to 10 requests/minute (brute-force protection)

### IP-only reconnect (`GET /api/devices/login`)

Used after token expiry when the tablet has no valid token but its IP is stable.

- Looks up `Device` by `ip_address = $resolvedIp AND is_active = true`
- **Blocked for unclaimed devices**: if `security_code IS NOT NULL`, returns 403 — device must
  register with code first
- IP resolution uses `shouldTrustClientSuppliedIp()` — see below

### Client-supplied IP trust gate

**File:** `app/Http/Controllers/Api/V1/Auth/DeviceAuthApiController.php`

When a tablet sends its local LAN IP in the request body (`ip_address` field), the backend can
optionally trust it instead of `$request->ip()`. Decision logic:

```
shouldTrustClientSuppliedIp($clientSupplied, $requestIp):

1. If $clientSupplied is not a private RFC-1918 IP → reject (never trust public IPs)
2. If DEVICE_ALLOW_CLIENT_SUPPLIED_IP = false (default) → reject
3. If DEVICE_ALLOWED_PRIVATE_SUBNETS is set:
     → trust only if $clientSupplied falls within one of the listed CIDRs
4. Fallback (no subnets configured):
     → trust only if $requestIp is also private AND same /24 as $clientSupplied
```

**This gate is what resolves the Docker bridge IP problem.** When enabled, the tablet's WebRTC-detected
LAN IP (`192.168.100.7`) is used instead of the nginx container IP (`172.18.0.3`).

### Frontend IP detection

**File:** `tablet-ordering-pwa/utils/getLocalIp.ts`

Uses WebRTC `RTCPeerConnection` to extract the tablet's LAN IP from ICE candidates.
Prefers private RFC-1918 ranges. Sent as `ip_address` in all auth and registration payloads.

If WebRTC fails or is unavailable (SSR), falls back to `window.location.hostname`. If the device
already has a `last_ip_address` from a previous session, that is used instead of re-detecting.

---

## Environment Variables

These two variables control the client-IP trust gate. **Neither is set by default** — the feature
is opt-in.

### `DEVICE_ALLOW_CLIENT_SUPPLIED_IP`

| Value | Behaviour |
|---|---|
| `false` (default) | Backend always uses `$request->ip()`. In Docker, this is the nginx container IP. IP-based reconnect will fail unless the device's stored `ip_address` happens to match the container IP (it won't). |
| `true` | Backend will consider the client-supplied `ip_address` field. Subject to subnet validation below. |

**Set this to `true` in Docker/proxy deployments** where `$request->ip()` is not the tablet's real IP.

### `DEVICE_ALLOWED_PRIVATE_SUBNETS`

Only evaluated when `DEVICE_ALLOW_CLIENT_SUPPLIED_IP=true`.

| Value | Behaviour |
|---|---|
| *(empty, default)* | Falls back to same-/24 heuristic: trusts client IP only if request IP is also private and in the same /24 subnet. Safe for simple flat networks. |
| `192.168.100.0/24` | Trusts any client-supplied IP within that CIDR. |
| `192.168.100.0/24,10.0.1.0/24` | Comma-separated list of trusted CIDRs. |

**Recommended for Docker deployments:**
```
DEVICE_ALLOW_CLIENT_SUPPLIED_IP=true
DEVICE_ALLOWED_PRIVATE_SUBNETS=192.168.100.0/24
```
Replace the CIDR with your actual tablet LAN subnet.

---

## Expired Token + IP Change Edge Case

The contract holds for token-valid devices (token in localStorage → `POST /api/devices/refresh`
works regardless of IP). However:

> If a tablet's **token expires** AND its **IP has changed**, `GET /api/devices/login` will return 404
> (no device at new IP). The tablet cannot reconnect without admin action.

Admin resolution: generate a new security code for the device in the dashboard and have the tablet
re-register.

This is an accepted trade-off: the IP-based fallback is a convenience path, not a guaranteed
recovery mechanism. The primary recovery path is always security-code re-registration.

---

## Implementation Status

| Item | Status |
|---|---|
| Security-code-first registration | ✅ Done |
| Code consumed on registration (`security_code = null`) | ✅ Done |
| IP-only auth blocked for unclaimed devices (403) | ✅ Done |
| Client-IP trust gate (`shouldTrustClientSuppliedIp`) | ✅ Done |
| Frontend WebRTC IP detection | ✅ Done |
| Frontend: IP sent in all auth/register payloads | ✅ Done |
| Settings page: shows detected IP vs server IP | ✅ Done |
| Token expiry normalized to 30 days across all paths | ✅ Done |
| `DEVICE_ALLOW_CLIENT_SUPPLIED_IP` in `.env.example` | ✅ Done |
| Feature tests: trusted client-IP + Docker source IP fallback | ⚠️ Partial — `DeviceAuthLookupByIpTest` covers untrusted case only |
| Feature tests: register + reconnect after IP change | ❌ Not written |
| Verification: end-to-end for changed-IP scenario | ❌ Not verified |

---

## 100% Success Gate

Complete when all are true:

1. Registration succeeds for pre-created devices via `security_code` regardless of LAN IP or Docker
   bridge IP. ✅
2. IP-based reconnect works in Docker when `DEVICE_ALLOW_CLIENT_SUPPLIED_IP=true` and subnet is
   configured. ⚠️ (Implemented; not integration-tested)
3. No `Device not found` failures caused solely by Docker bridge IP substitution in normal LAN
   operation. ⚠️ (Requires env config — documented above)
4. Tests and documentation updated. ⚠️ (Docs done; tests partial)
