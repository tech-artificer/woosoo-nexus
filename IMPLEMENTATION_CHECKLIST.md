# 🔧 Implementation Checklist for Remaining Tasks

Quick reference for completing the 6 remaining stability tasks.

---

## Task 1: Token Refresh Timer (30 min)

**File:** `tablet-ordering-pwa/stores/device.ts`

### Step 1: Add JWT decode dependency
```bash
cd tablet-ordering-pwa
npm install jwt-decode
```

### Step 2: Add token refresh logic to device store
```typescript
import { jwtDecode } from 'jwt-decode';

export const setupTokenRefreshTimer = (token: string) => {
  try {
    const decoded: any = jwtDecode(token);
    const expiresAt = decoded.exp * 1000; // Convert to milliseconds
    
    // Refresh 5 minutes before expiration
    const refreshAt = expiresAt - (5 * 60 * 1000);
    const now = Date.now();
    const delayMs = Math.max(0, refreshAt - now);
    
    console.log(`Token refresh scheduled in ${delayMs}ms`);
    
    setTimeout(() => {
      console.log('Refreshing token...');
      refreshDeviceToken(); // Your refresh API call
      setupTokenRefreshTimer(device.token); // Reschedule
    }, delayMs);
  } catch (error) {
    console.error('Token setup failed:', error);
  }
}

// Call from useDeviceStore initialization
onMounted(() => {
  if (deviceStore.token) {
    setupTokenRefreshTimer(deviceStore.token);
  }
});
```

### Verification
- [ ] Token refreshes 5 minutes before expiration
- [ ] No 401 errors during normal operation
- [ ] New token persists to store and localStorage

---

## Task 2: Nuxt API Retry Logic (45 min)

**File:** `tablet-ordering-pwa/plugins/api.client.ts`

### Step 1: Import Notification
```typescript
import { ElNotification } from 'element-plus';
```

### Step 2: Add response interceptor with retry
```typescript
// Add after api instance creation
api.interceptors.response.use(
  response => response,
  async (error) => {
    const config = error.config as any;
    
    // Don't retry on client errors (4xx)
    if (error.response?.status >= 400 && error.response?.status < 500) {
      if (error.response?.status === 401) {
        // Token expired, redirect to login
        useRouter().push('/');
      }
      throw error;
    }

    // Initialize retry counter
    if (!config._retry) {
      config._retry = 0;
    }

    config._retry++;
    
    // Max 3 retries
    if (config._retry > 3) {
      ElNotification({
        type: 'error',
        title: 'Service Unavailable',
        message: 'API is temporarily unavailable. Please try again.',
        duration: 5000
      });
      throw error;
    }

    // Exponential backoff: 1s, 2s, 4s
    const delay = 1000 * Math.pow(2, config._retry - 1);
    console.log(`Retrying request (attempt ${config._retry}/3) in ${delay}ms`);
    
    // Wait before retrying
    await new Promise(resolve => setTimeout(resolve, delay));
    
    // Retry the request
    return api(config);
  }
);
```

### Verification
- [ ] 5xx errors retry automatically (max 3 times)
- [ ] Timeout errors retry automatically
- [ ] Exponential delays: 1s, 2s, 4s
- [ ] User sees notification on final failure
- [ ] No infinite retry loops

---

## Task 3: Flutter API Retry Logic (45 min)

**File:** `relay-device/lib/services/api_service.dart`

### Step 1: Add retry method to ApiService
```dart
class ApiService {
  static const int _maxRetries = 3;
  static const int _baseDelayMs = 1000;

  // Generic retry wrapper
  Future<T> _retryWithBackoff<T>(
    Future<T> Function() request, {
    String operationName = 'Request',
  }) async {
    int attempts = 0;
    
    while (true) {
      try {
        return await request();
      } on SocketException catch (e) {
        attempts++;
        _handleRetryableError(
          error: e,
          operationName: operationName,
          attempts: attempts,
          maxAttempts: _maxRetries,
        );
        
        if (attempts >= _maxRetries) rethrow;
        
        final delayMs = _baseDelayMs * (1 << (attempts - 1)); // 1s, 2s, 4s
        await Future.delayed(Duration(milliseconds: delayMs));
      } on TimeoutException catch (e) {
        attempts++;
        _handleRetryableError(
          error: e,
          operationName: operationName,
          attempts: attempts,
          maxAttempts: _maxRetries,
        );
        
        if (attempts >= _maxRetries) rethrow;
        
        final delayMs = _baseDelayMs * (1 << (attempts - 1));
        await Future.delayed(Duration(milliseconds: delayMs));
      }
    }
  }

  void _handleRetryableError({
    required dynamic error,
    required String operationName,
    required int attempts,
    required int maxAttempts,
  }) {
    final delayMs = _baseDelayMs * (1 << (attempts - 1));
    print('$operationName failed (attempt $attempts/$maxAttempts): $error');
    print('Retrying in ${delayMs}ms...');
  }

  // Update existing methods to use retry wrapper
  Future<List<PrintJob>> getPendingPrintJobs() async {
    return _retryWithBackoff(
      () => _getPendingPrintJobsImpl(),
      operationName: 'Get print jobs',
    );
  }

  Future<void> sendPrintJobConfirmation(String jobId) async {
    return _retryWithBackoff(
      () => _sendPrintJobConfirmationImpl(jobId),
      operationName: 'Send print confirmation',
    );
  }
}
```

### Verification
- [ ] Socket exceptions retry automatically
- [ ] Timeout exceptions retry automatically
- [ ] Exponential backoff applied (1s, 2s, 4s)
- [ ] Max 3 retries per request
- [ ] Device retries without user intervention
- [ ] Print jobs eventually succeed or fail with clear error

---

## Task 4: Monitoring Thresholds (30 min)

**File:** `app/Services/MonitoringService.php`

### Step 1: Make thresholds configurable
```php
// config/monitoring.php (create new file)
return [
    'queue' => [
        'depth_alert_threshold' => env('MONITORING_QUEUE_THRESHOLD', 100),
    ],
    'broadcast' => [
        'event_rate_threshold' => env('MONITORING_EVENT_RATE', 500), // per 5 min
        'slow_event_threshold_ms' => env('MONITORING_SLOW_EVENT_MS', 100),
    ],
    'database' => [
        'slow_query_ms' => env('MONITORING_SLOW_QUERY_MS', 1000),
    ],
    'alerts' => [
        'enabled' => env('MONITORING_ALERTS_ENABLED', true),
        'channels' => explode(',', env('MONITORING_ALERT_CHANNELS', 'log')),
    ]
];
```

### Step 2: Update .env with thresholds
```env
# Monitoring (app/Services/MonitoringService.php)
MONITORING_QUEUE_THRESHOLD=100
MONITORING_EVENT_RATE=500
MONITORING_SLOW_EVENT_MS=100
MONITORING_ALERTS_ENABLED=true
MONITORING_ALERT_CHANNELS=log,slack,email
```

### Step 3: Add webhook integration
```php
// app/Services/AlertingService.php (create new file)
class AlertingService {
    public static function sendAlert(string $level, string $message, array $details = []): void {
        $channels = config('monitoring.alerts.channels');
        
        foreach ($channels as $channel) {
            match ($channel) {
                'log' => Log::channel('alerts')->{$level}($message, $details),
                'slack' => self::sendSlackAlert($level, $message, $details),
                'email' => self::sendEmailAlert($level, $message, $details),
                'pagerduty' => self::sendPagerDutyAlert($level, $message, $details),
                default => null,
            };
        }
    }

    private static function sendSlackAlert(string $level, string $message, array $details): void {
        $webhook = env('SLACK_ALERT_WEBHOOK');
        if (!$webhook) return;
        
        Http::post($webhook, [
            'text' => "[$level] $message",
            'attachments' => [
                [
                    'color' => $level === 'error' ? 'danger' : 'warning',
                    'fields' => collect($details)->map(fn($v, $k) => [
                        'title' => $k,
                        'value' => (string) $v,
                        'short' => true,
                    ])->values()->all(),
                ]
            ]
        ]);
    }

    private static function sendEmailAlert(string $level, string $message, array $details): void {
        $emails = explode(',', env('ALERT_EMAIL_RECIPIENTS', ''));
        foreach ($emails as $email) {
            Mail::send('emails.alert', compact('level', 'message', 'details'), fn($m) => 
                $m->to(trim($email))->subject("Alert: $message")
            );
        }
    }

    private static function sendPagerDutyAlert(string $level, string $message, array $details): void {
        Http::post('https://events.pagerduty.com/v2/enqueue', [
            'routing_key' => env('PAGERDUTY_INTEGRATION_KEY'),
            'event_action' => $level === 'error' ? 'trigger' : 'trigger',
            'payload' => [
                'summary' => $message,
                'severity' => $level === 'error' ? 'critical' : 'warning',
                'source' => config('app.name'),
                'custom_details' => $details,
            ]
        ]);
    }
}
```

### Step 4: Update .env for alert channels
```env
# Slack alerts
SLACK_ALERT_WEBHOOK=https://hooks.slack.com/services/YOUR/WEBHOOK/URL

# Email alerts
ALERT_EMAIL_RECIPIENTS=ops@company.com,devops@company.com

# PagerDuty
PAGERDUTY_INTEGRATION_KEY=your_integration_key_here
```

### Verification
- [ ] Alerts configured per environment
- [ ] Thresholds customizable via .env
- [ ] Alert channels working (log, Slack, email, PagerDuty)
- [ ] High-priority alerts trigger immediately
- [ ] Threshold tests: Manually trigger alert scenarios

---

## Task 5: Verify Event Recording (15 min)

**File:** None (testing only)

### Step 1: Start development server
```bash
composer dev
```

### Step 2: Send test event
```bash
php artisan tinker

# Option 1: Dispatch a real event
>>> event(new App\Events\PrintOrder(['orderId' => 123, 'deviceId' => 456]));

# Option 2: Manually record event
>>> App\Models\BroadcastEvent::record('admin.print', 'PrintOrder', ['orderId' => 123]);
```

### Step 3: Verify recording
```bash
# Check it was recorded
>>> DB::table('broadcast_events')->latest()->first();

# Should see output like:
# {
#   "id": 1,
#   "channel": "admin.print",
#   "event": "PrintOrder",
#   "payload": "{\"orderId\":123,...}",
#   "created_at": "2026-01-03 14:30:45",
#   "updated_at": "2026-01-03 14:30:45"
# }
```

### Verification
- [ ] Event recorded to database within 1 second
- [ ] All fields populated correctly
- [ ] Multiple events recordable
- [ ] Old events purged after 24 hours (test with artisan command)

---

## Task 6: Run Full Test Suite (30 min)

### Step 1: Clear cache and run Laravel tests
```bash
cd c:\laragon\www\woosoo-nexus

# Clear cache
php artisan cache:clear config:clear

# Run tests
composer test
```
✅ **Expected:** All tests pass

### Step 2: Run Flutter tests (optional if configured)
```bash
cd relay-device
flutter test
```
✅ **Expected:** All tests pass

### Step 3: Run Nuxt tests (if configured)
```bash
cd tablet-ordering-pwa
npm run test 2>/dev/null || echo "Tests not configured"
```
✅ **Expected:** All tests pass (or no tests configured)

### Step 4: Manual smoke tests
```bash
# Health check
curl http://127.0.0.1:8000/api/health

# Monitoring metrics
curl http://127.0.0.1:8000/api/monitoring/metrics

# Routes
php artisan route:list --path=api | grep -E "(health|monitoring|events)"
```

### Verification
- [ ] All Laravel tests passing
- [ ] All Flutter tests passing
- [ ] All Nuxt tests passing
- [ ] Health endpoint responds 200
- [ ] Monitoring endpoints return JSON
- [ ] All routes registered

---

## Implementation Timeline

```
Day 1 (3-4 hours):
  Task 1: Token Refresh ........... 30 min ✅
  Task 2: Nuxt API Retry ......... 45 min ✅
  Task 3: Flutter API Retry ...... 45 min ✅
  Task 4: Monitoring Thresholds .. 30 min ✅

Day 2 (1 hour):
  Task 5: Verify Event Recording . 15 min ✅
  Task 6: Full Test Suite ........ 30 min ✅
  
Total: 4-5 hours implementation time
```

---

## Success Criteria

All tasks complete when:
- [ ] All 6 tasks implemented
- [ ] All tests passing (composer test, flutter test)
- [ ] Health endpoint returns 200
- [ ] Monitoring endpoints return JSON
- [ ] Event recording verified in database
- [ ] Token refresh working (check logs)
- [ ] API retry logic working (test with error injection)
- [ ] Alert channels configured and tested

---

## Troubleshooting

### Task 1: Token not refreshing
- Check if jwt-decode installed: `npm list jwt-decode`
- Verify token is in store: `console.log(deviceStore.token)`
- Check browser console for errors

### Task 2: API retry not working
- Check browser dev tools Network tab (should see retries)
- Verify 5xx error returns status 500+
- Check axios interceptor registered

### Task 3: Flutter retry not working
- Check Flutter logs: `flutter logs`
- Verify network is actually failing (use airplane mode)
- Check implementation uses _retryWithBackoff wrapper

### Task 4: Alerts not sending
- Verify webhooks configured in .env
- Check app logs for alert sends
- Test webhook manually with curl

### Task 5: Events not recording
- Verify listener registered: grep "registerBroadcastEventListener" app/Providers/AppServiceProvider.php
- Check if event is broadcastable: implements ShouldBroadcast
- Verify broadcast_events table exists

### Task 6: Tests failing
- Run `php artisan cache:clear` first
- Check test output for specific errors
- Run single failing test: `./vendor/bin/pest --filter=TestName`

---

## Notes

- All tasks are independent except they should be done in order (for debugging)
- Tasks 1-4 can be done in parallel if multiple developers available
- Tasks 5-6 validate all previous tasks
- Rollback: Just comment out changes if issues found (nothing is breaking)

Good luck! 🚀

