# Quick Test & Validation Guide

## 🧪 Testing the Stability Implementation

### Prerequisites
```powershell
cd c:\laragon\www\woosoo-nexus

# Start all services (HTTP, Queue, Vite, Reverb)
composer dev
```

This starts:
- HTTP server: http://127.0.0.1:8000
- Vite HMR: http://127.0.0.1:5173
- Reverb WebSocket: ws://127.0.0.1:6001
- Queue listener: Processes jobs in background

---

## ✅ Network Gate Enforcement Tests

### Test 1: Welcome Page Blocks When Offline
1. Open admin app: http://127.0.0.1:8000
2. Disable WiFi or disable network (Dev Tools → Network → Offline)
3. Click "START" button
4. **Expected:** Error dialog "Network connection required to process orders."
5. **Result:** ✅ PASS if error appears

### Test 2: Welcome Page Blocks When Reverb Disconnected
1. Open admin app: http://127.0.0.1:8000
2. Kill Reverb process: `php artisan reverb:start` (stop via terminal)
3. Click "START" button
4. **Expected:** Error dialog "Broadcasting service connection required."
5. **Result:** ✅ PASS if error appears

### Test 3: Welcome Page Allows When Both Connected
1. Open admin app: http://127.0.0.1:8000
2. Ensure network is online AND Reverb is running
3. Click "START" button
4. **Expected:** Redirect to /order/start (next page loads)
5. **Result:** ✅ PASS if progression works

---

## 🏥 Health Endpoint Tests

### Test 4: Health Check Returns 200 (All Systems OK)
```powershell
Invoke-WebRequest -Uri "http://127.0.0.1:8000/api/health" -Method Get | ConvertTo-Json
```
**Expected Response:**
```json
{
  "status": "ok",
  "mysql": "ok",
  "pos_db": "ok",
  "queue_depth": 0,
  "uptime_seconds": 245
}
```
**Result:** ✅ PASS if status=ok

### Test 5: Health Check Returns 503 (MySQL Down)
1. Stop MySQL service
2. Run health check:
```powershell
Invoke-WebRequest -Uri "http://127.0.0.1:8000/api/health" -Method Get
```
**Expected:** Status 503, message includes "mysql failed"
**Result:** ✅ PASS if 503 returned

---

## 📡 Event Replay Tests

### Test 6: Broadcast Event Recording
```bash
cd c:\laragon\www\woosoo-nexus
php artisan tinker

# Record a test event
>>> event(new App\Events\PrintOrder(['orderId' => 123]));

# Verify it was recorded
>>> DB::table('broadcast_events')->latest()->first();
```
**Expected:** Event appears in broadcast_events table within 1 second
**Result:** ✅ PASS if event recorded

### Test 7: Event Replay API
```bash
# Get events since 1 hour ago
curl "http://127.0.0.1:8000/api/events/missing?since=2026-01-03T12:00:00Z&channel=admin.print"
```
**Expected:** JSON array with PrintOrder events (if any exist)
**Result:** ✅ PASS if events array returned

---

## 📊 Monitoring Tests

### Test 8: Metrics Endpoint
```bash
curl "http://127.0.0.1:8000/api/monitoring/metrics"
```
**Expected Response:**
```json
{
  "queue_depth": 5,
  "broadcast_event_rate": 23,
  "mysql_healthy": true,
  "pos_healthy": true,
  "memory_usage_mb": 145
}
```
**Result:** ✅ PASS if metrics returned

### Test 9: Liveness Probe
```bash
curl "http://127.0.0.1:8000/api/monitoring/live"
```
**Expected:** 200 OK, "alive"
**Result:** ✅ PASS if 200 returned

### Test 10: Readiness Probe
```bash
curl "http://127.0.0.1:8000/api/monitoring/ready"
```
**Expected:** 200 OK, "ready" (if dependencies healthy)
**Result:** ✅ PASS if 200 returned

---

## 🧬 Flutter WebSocket Tests

### Test 11: Flutter WebSocket Connection
1. Build and run Flutter app:
```bash
cd relay-device
flutter run -d windows
```

2. Check console logs for:
```
WebSocketListenerService: Connecting to ws://127.0.0.1:6001...
WebSocketListenerService: Connected successfully
```

3. **Expected:** Connection succeeds and stays connected
4. **Result:** ✅ PASS if connected shown in logs

### Test 12: Flutter WebSocket Reconnection
1. App running (from Test 11)
2. Stop Reverb process
3. Watch Flutter logs for:
```
WebSocketListenerService: Connection lost, reconnecting in 1000ms...
WebSocketListenerService: Reconnection attempt 1 of 10
WebSocketListenerService: Connected successfully
```
4. Restart Reverb: `php artisan reverb:start`
5. **Expected:** App reconnects within 10 seconds
6. **Result:** ✅ PASS if reconnection succeeds

### Test 13: Print Queue Persistence
1. Send a print job while app running
2. Force kill Flutter app (no graceful shutdown)
3. Reopen app
4. Check for:
```
QueueStorageService: Resuming 1 pending jobs from storage
```
5. **Expected:** Pending job survives app restart
6. **Result:** ✅ PASS if queue persisted

---

## 🔄 Exponential Backoff Tests (After Implementation)

### Test 14: Nuxt API Retry on 5xx Error
1. Force API to return 500 error (temporarily modify controller)
2. Make API call from PWA
3. Check browser console for retries with delays
4. **Expected:** Request retried 3 times with 1s, 2s, 4s delays
5. **Result:** ✅ PASS after token refresh implementation

### Test 15: Flutter API Retry on Timeout
1. App running
2. Disable network briefly
3. Try to send print job
4. Watch Flutter logs for:
```
ApiService: Request failed, retrying in 1000ms (attempt 1/3)
ApiService: Request failed, retrying in 2000ms (attempt 2/3)
ApiService: Request succeeded on attempt 3
```
5. **Expected:** Retries succeed after network restored
6. **Result:** ✅ PASS after retry logic implementation

---

## 🎯 Full Test Suite

### Run All Laravel Tests
```bash
cd c:\laragon\www\woosoo-nexus
composer test
```
**Expected:** All tests pass
**Result:** ✅ PASS if no failures

### Run Flutter Tests
```bash
cd relay-device
flutter test
```
**Expected:** All tests pass
**Result:** ✅ PASS if no failures

### Run Nuxt Tests (if configured)
```bash
cd tablet-ordering-pwa
npm run test
```
**Expected:** All tests pass
**Result:** ✅ PASS if no failures

---

## 🚀 Deployment Validation Checklist

- [ ] Health endpoint responds: `GET /api/health` → 200
- [ ] Monitoring metrics available: `GET /api/monitoring/metrics` → JSON
- [ ] Event replay works: `GET /api/events/missing?...` → events array
- [ ] Welcome page gate blocks offline: Error shown when disconnected
- [ ] Welcome page gate blocks Reverb disconnect: Error shown when WebSocket fails
- [ ] Welcome page allows progression: Works when both online + Reverb connected
- [ ] Flutter WebSocket connects: Logs show "Connected successfully"
- [ ] Flutter WebSocket reconnects: Exponential backoff working (1s, 2s, 4s...)
- [ ] Print queue survives restart: Jobs persist across app restarts
- [ ] All tests pass: composer test, flutter test, npm run test
- [ ] Monitoring thresholds configured: Alert system integrated
- [ ] Manual integration tasks completed: Token refresh, API retry logic

---

## 📋 Test Results Template

Copy and fill in:

```
Date: ____________________
Tester: ____________________
Environment: ☐ Local ☐ Staging ☐ Production

Test Results:
- [ ] Test 1: Welcome Page Offline Block
- [ ] Test 2: Welcome Page Reverb Block
- [ ] Test 3: Welcome Page Allow When Connected
- [ ] Test 4: Health Check 200
- [ ] Test 5: Health Check 503
- [ ] Test 6: Event Recording
- [ ] Test 7: Event Replay API
- [ ] Test 8: Metrics Endpoint
- [ ] Test 9: Liveness Probe
- [ ] Test 10: Readiness Probe
- [ ] Test 11: Flutter WebSocket Connect
- [ ] Test 12: Flutter WebSocket Reconnect
- [ ] Test 13: Print Queue Persistence
- [ ] Test 14: Nuxt API Retry (after implementation)
- [ ] Test 15: Flutter API Retry (after implementation)

Overall Result: ☐ PASS ☐ FAIL

Issues Found:
_________________________________
_________________________________
_________________________________

Sign-off: ____________________
```

---

## 🐛 Troubleshooting

### Reverb Not Connecting
```bash
# Check if Reverb is running
php artisan reverb:start --verbose

# Check if port 6001 is in use
netstat -ano | findstr :6001

# Kill process using port if needed
taskkill /PID <PID> /F
```

### Health Endpoint Returns 503
```bash
# Check MySQL connection
php artisan tinker
>>> DB::connection('mysql')->select('SELECT 1')

# Check POS connection
>>> DB::connection('pos')->select('SELECT 1')

# Check queue jobs count
>>> DB::table('jobs')->count()
```

### Broadcast Events Not Recording
```bash
# Check if listener is registered
php artisan tinker
>>> Event::listen('*', function() { echo 'Listener working'; });

# Verify AppServiceProvider boot method
grep -n "registerBroadcastEventListener" app/Providers/AppServiceProvider.php

# Check broadcast_events table exists
>>> DB::table('broadcast_events')->count()
```

### Flutter WebSocket Not Connecting
```bash
# Check logs
flutter logs

# Verify Reverb running and accessible
curl ws://127.0.0.1:6001/health

# Check device network connectivity
adb shell ping 127.0.0.1
```

---

## 📞 Contact Support

If tests fail:
1. Check troubleshooting section above
2. Review implementation files:
   - Backend: `/app/Http/Controllers/Api/*`
   - Frontend: `/tablet-ordering-pwa/pages/index.vue`
   - Mobile: `/relay-device/lib/services/*`
3. Check logs: `php artisan pail` or `flutter logs`
4. Re-run migrations: `php artisan migrate --step`
5. Clear cache: `php artisan cache:clear config:clear`

