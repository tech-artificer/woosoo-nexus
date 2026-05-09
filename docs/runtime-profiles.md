# Woosoo Nexus Runtime Profiles

## Canonical Local Path

Use PHP on the Windows host and MySQL through Docker on `127.0.0.1:3306`.

1. Start MySQL with the local compose override:
   ```powershell
   docker compose -f compose.yaml -f compose.local.yaml up -d mysql
   ```
2. Create a local profile from the template:
   ```powershell
   Copy-Item .env.local.example .env.local
   ```
3. Fill the placeholders in `.env.local`.
4. Apply the profile:
   ```powershell
   powershell -ExecutionPolicy Bypass -File scripts/env/apply-env-profile.ps1 -Profile local -AllowOverride -AutoRun
   ```
5. Verify resolved config:
   ```powershell
   php artisan config:show database.connections.mysql.host
   php artisan config:show cache.default
   php artisan config:show queue.default
   php artisan config:show session.driver
   php artisan config:show pulse.enabled
   ```

Expected local values:

- `database.connections.mysql.host`: `127.0.0.1`
- `cache.default`: `file`
- `queue.default`: `sync`
- `session.driver`: `file`
- `pulse.enabled`: `false`

## Docker Path

Use Docker service DNS only from inside containers. Do not use `compose.local.yaml` for production or Pi runtime.

1. Create a Docker profile from the template:
   ```powershell
   Copy-Item .env.docker.example .env.docker
   ```
2. Fill the placeholders in `.env.docker`.
3. Apply the profile:
   ```powershell
   powershell -ExecutionPolicy Bypass -File scripts/env/apply-env-profile.ps1 -Profile docker -AllowOverride
   ```
4. Start the stack:
   ```powershell
   docker compose -f compose.yaml up -d
   ```
5. Refresh Laravel config inside the app container:
   ```powershell
   docker compose exec app php artisan optimize:clear
   docker compose exec app php artisan config:clear
   ```
6. Verify container-resolved config:
   ```powershell
   docker compose exec app php artisan config:show database.connections.mysql.host
   docker compose exec app php artisan config:show database.redis.default.host
   docker compose exec app php artisan config:show queue.default
   ```

Expected Docker values:

- `database.connections.mysql.host`: `mysql`
- `database.redis.default.host`: `redis`
- `queue.default`: `redis`

## Profile Script Contract

`scripts/env/apply-env-profile.ps1` accepts:

- `-Profile local|docker`
- `-DryRun`
- `-AutoRun`
- `-AllowOverride`
- `-ForceReplace`

Exit codes:

- `0`: success
- `1`: invalid input or missing profile
- `2`: duplicate keys in source profile
- `3`: duplicate keys in target `.env` or unsafe overwrite without `-AllowOverride`
- `4`: write or post-apply hygiene failure

The script writes `.env` with a generated header and fails before writing when it finds duplicate env keys. This explicitly prevents regressions like duplicated `QUEUE_CONNECTION`.

`-ForceReplace` is an advanced recovery mode only. When used, the script now rebuilds `.env` from `.env.example` and then applies profile overrides, so non-profile keys are not silently dropped.

## Cleanup Workflow

Malformed absolute-path artifact directories can appear after local Windows path handling goes wrong. Always dry-run first:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/env/cleanup-generated-artifacts.ps1
```

If the listed directories are only generated logs or compiled views, delete them:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/env/cleanup-generated-artifacts.ps1 -Apply
```
