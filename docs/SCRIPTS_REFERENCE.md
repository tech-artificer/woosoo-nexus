# Scripts Reference - Quick Lookup

Use this for quick script discovery. Full documentation in **[../scripts/README.md](../scripts/README.md)**.

---

## Setup Phase (First Time - Run in Order)

1. `.\scripts\setup-prerequisites.ps1` — Install OpenSSL, mkcert
2. `.\scripts\setup-firewall.ps1` — Open Windows Firewall ports
3. `.\scripts\setup-php-config.ps1` — Configure PHP extensions
4. `.\scripts\setup-local-domains.ps1` — Add hosts file entries
5. `.\scripts\services-setup.ps1 -Mode install` — Install Windows services
6. `.\scripts\services-manager.ps1 -Action start` — Start all services

**Verify:** `.\scripts\check-services.ps1` (all should show [OK])

---

## Daily Operations

| Task | Command |
|------|---------|
| Check health | `.\scripts\check-services.ps1` |
| Restart all services | `.\scripts\services-manager.ps1 -Action restart` |
| Restart nginx only | `.\scripts\restart-nginx.ps1` |
| Stop all services | `.\scripts\services-manager.ps1 -Action stop` |

---

## Script Consolidation (v2.0)

Simplified from 12 → 9 scripts:

**Merged scripts:**
- `services-setup.ps1` — Install/uninstall (replaces: install-services.ps1, uninstall-services.ps1)
- `services-manager.ps1` — Start/stop/restart (replaces: start-production.ps1, stop-production.ps1, restart-for-lan.ps1)

**Command reference:**
- Old: `.\start-production.ps1` → New: `.\services-manager.ps1 -Action start`
- Old: `.\restart-for-lan.ps1` → New: `.\services-manager.ps1 -Action restart`
- Old: `.\install-services.ps1` → New: `.\services-setup.ps1 -Mode install`

---

## Emergency

- **Services crashed?** Run `.\scripts\services-manager.ps1 -Action restart`
- **Nginx config invalid?** Check `logs/nginx/error.log`, fix, then run `.\scripts\restart-nginx.ps1`
- **Port conflict?** Run `netstat -ano | findstr :80` to find process
- **Complete reset?** Run `.\scripts\services-setup.ps1 -Mode uninstall`, then restart from Setup Phase

---

## Troubleshooting

See **[SITE_NOT_REACHED_FIX.md](SITE_NOT_REACHED_FIX.md)** for detailed error diagnosis.

---

## Archived Scripts

Debug and test scripts moved to **vault/woosoo-nexus-archived/** for historical reference:
- `check_order_*.php` — Legacy order debugging
- `debug_*.php` — Component debugging
- `test_*.php` — Manual testing
- `verify_live.php` — Environment verification

These are **NOT** part of active operations.

---

## Notes

- **Always run as Administrator** (right-click PowerShell)
- **Verify after changes** (run `check-services.ps1`)
- **Check logs on errors** (`logs/nginx/`, `logs/php/`, `logs/reverb/`)
- **Full reference:** [../scripts/README.md](../scripts/README.md)
