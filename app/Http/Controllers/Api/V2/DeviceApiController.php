<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Device;
use App\Models\DeviceHeartbeat;
use App\Services\LocalBranchResolver;
use App\Http\Requests\Api\StoreDeviceApiRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class DeviceApiController extends Controller
{
    public function __construct(private LocalBranchResolver $branchResolver)
    {
    }

    /** Minutes of inactivity before a device is considered offline. */
    private const ONLINE_WINDOW_MINUTES = 5;

    // -------------------------------------------------------------------------
    // Collection endpoints
    // -------------------------------------------------------------------------

    /**
     * GET /api/v2/devices
     * Paginated device list with optional status/type/branch_id filters.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Device::class);

        $query = Device::query()->with('branch');

        if ($request->filled('status')) {
            if ($request->input('status') === 'online') {
                $query->where('last_seen_at', '>=', now()->subMinutes(self::ONLINE_WINDOW_MINUTES));
            } else {
                $query->where(function ($q) {
                    $q->whereNull('last_seen_at')
                      ->orWhere('last_seen_at', '<', now()->subMinutes(self::ONLINE_WINDOW_MINUTES));
                });
            }
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->integer('branch_id'));
        }

        $devices = $query->orderByDesc('created_at')->paginate(20);
        $devices->getCollection()->transform(fn ($d) => $this->formatDevice($d));

        return response()->json([
            'data' => $devices->items(),
            'meta' => [
                'total'        => $devices->total(),
                'per_page'     => $devices->perPage(),
                'current_page' => $devices->currentPage(),
                'last_page'    => $devices->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/v2/devices/metadata
     * Returns active branches for device registration forms.
     */
    public function metadata(): JsonResponse
    {
        $this->authorize('viewAny', Device::class);

        $resolved = $this->branchResolver->resolve();

        // On-prem default: exactly one local branch. Fallback to full list for
        // backward compatibility in dev/test environments with multi-branch data.
        $branches = $resolved
            ? collect([$resolved->only(['id', 'name'])])
            : Branch::query()->select('id', 'name')->orderBy('name')->get();

        return response()->json(['branches' => $branches]);
    }

    /**
     * GET /api/v2/devices/statistics
     * Per-type and aggregate device counts.
     */
    public function statistics(): JsonResponse
    {
        $this->authorize('viewAny', Device::class);

        $onlineThreshold = now()->subMinutes(self::ONLINE_WINDOW_MINUTES);
        $total   = Device::count();
        $active  = Device::where('is_active', true)->count();
        $online  = Device::where('last_seen_at', '>=', $onlineThreshold)->count();

        $byType = [];
        foreach (['tablet', 'relay_printer', 'print_bridge', 'direct_printer'] as $type) {
            $base = Device::where('type', $type);
            $byType[$type] = [
                'total'  => (clone $base)->count(),
                'online' => (clone $base)->where('last_seen_at', '>=', $onlineThreshold)->count(),
            ];
        }

        return response()->json([
            'total'   => $total,
            'active'  => $active,
            'online'  => $online,
            'offline' => $total - $online,
            'by_type' => $byType,
        ]);
    }

    /**
     * GET /api/v2/devices/by-status
     * Returns all devices split into online / offline arrays.
     */
    public function byStatus(): JsonResponse
    {
        $this->authorize('viewAny', Device::class);

        $threshold = now()->subMinutes(self::ONLINE_WINDOW_MINUTES);

        $online  = Device::where('last_seen_at', '>=', $threshold)->with('branch')->get();
        $offline = Device::where(function ($q) use ($threshold) {
            $q->whereNull('last_seen_at')
              ->orWhere('last_seen_at', '<', $threshold);
        })->with('branch')->get();

        return response()->json([
            'online'  => $online->map(fn ($d) => $this->formatDevice($d)),
            'offline' => $offline->map(fn ($d) => $this->formatDevice($d)),
        ]);
    }

    // -------------------------------------------------------------------------
    // Single resource endpoints
    // -------------------------------------------------------------------------

    /**
     * POST /api/v2/devices
     * Register a new device. Returns the device + one-time plain security code.
     */
    public function store(StoreDeviceApiRequest $request): JsonResponse
    {
        $data   = $request->validated();
        $branch = Branch::find($data['branch_id'] ?? null)
            ?? $this->branchResolver->resolve()
            ?? Branch::query()->orderBy('id')->first();

        if (! $branch) {
            return response()->json(['message' => 'No branch found.'], 422);
        }

        $plain  = $data['security_code'];
        $device = Device::create([
            'name'                       => $data['name'],
            'type'                       => $data['type'],
            'branch_id'                  => $branch->id,
            'ip_address'                 => $data['ip_address'] ?? null,
            'security_code'              => Hash::make($plain),
            'security_code_generated_at' => now(),
            'is_active'                  => true,
        ]);

        return response()->json([
            'device'        => $this->formatDevice($device),
            'security_code' => $plain,   // one-time flash — not stored in plain text after this
        ], 201);
    }

    /**
     * GET /api/v2/devices/{device}
     * Show a single device with branch + latest heartbeat.
     */
    public function show(Device $device): JsonResponse
    {
        $this->authorize('view', $device);

        $device->load(['branch', 'latestHeartbeat']);
        return response()->json($this->formatDevice($device, withHeartbeat: true));
    }

    /**
     * GET /api/v2/devices/{device}/heartbeats
     * Rolling telemetry for a device. Capped at 500 records, max 30 days.
     */
    public function heartbeats(Request $request, Device $device): JsonResponse
    {
        $this->authorize('view', $device);

        $days  = min((int) $request->input('days', 1), 30);
        $limit = min((int) $request->input('limit', 100), 500);

        $records = DeviceHeartbeat::where('device_id', $device->id)
            ->where('recorded_at', '>=', now()->subDays($days))
            ->orderByDesc('recorded_at')
            ->limit($limit)
            ->get();

        return response()->json(['data' => $records]);
    }

    /**
     * GET /api/v2/devices/{device}/health
     * Online status + latest heartbeat snapshot.
     */
    public function health(Device $device): JsonResponse
    {
        $this->authorize('view', $device);

        $hb     = $device->latestHeartbeat;
        $online = $device->last_seen_at
            && $device->last_seen_at->gte(now()->subMinutes(self::ONLINE_WINDOW_MINUTES));

        return response()->json([
            'online'           => $online,
            'last_seen_at'     => $device->last_seen_at?->toIso8601String(),
            'latest_heartbeat' => $hb ? [
                'recorded_at'     => $hb->recorded_at->toIso8601String(),
                'battery_level'   => $hb->battery_level,
                'storage_percent' => $hb->storage_percent,
                'wifi_signal_dbm' => $hb->wifi_signal_dbm,
                'ping_ms'         => $hb->ping_ms,
            ] : null,
        ]);
    }

    /**
     * POST /api/v2/devices/{device}/security-code
     * Regenerate a device's security code. Returns plain code once.
     */
    public function regenerateSecurityCode(Device $device): JsonResponse
    {
        $this->authorize('update', $device);

        $plain = (string) random_int(100000, 999999);
        $device->update([
            'security_code'              => Hash::make($plain),
            'security_code_generated_at' => now(),
        ]);

        return response()->json(['security_code' => $plain]);
    }

    /**
     * POST /api/v2/devices/{device}/status
     * Toggle active/inactive.
     */
    public function toggleStatus(Request $request, Device $device): JsonResponse
    {
        $this->authorize('update', $device);

        $request->validate(['is_active' => ['required', 'boolean']]);
        $device->update(['is_active' => $request->boolean('is_active')]);

        return response()->json(['is_active' => $device->is_active]);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function formatDevice(Device $device, bool $withHeartbeat = false): array
    {
        $online = $device->last_seen_at
            && $device->last_seen_at->gte(now()->subMinutes(self::ONLINE_WINDOW_MINUTES));

        $out = [
            'id'           => $device->id,
            'device_uuid'  => $device->device_uuid,
            'name'         => $device->name,
            'type'         => $device->type,
            'is_active'    => $device->is_active,
            'online'       => $online,
            'last_seen_at' => $device->last_seen_at?->toIso8601String(),
            'ip_address'   => $device->ip_address,
            'branch'       => $device->relationLoaded('branch') ? [
                'id'   => $device->branch?->id,
                'name' => $device->branch?->name,
            ] : null,
        ];

        if ($withHeartbeat && $device->relationLoaded('latestHeartbeat')) {
            $hb  = $device->latestHeartbeat;
            $out['latest_heartbeat'] = $hb ? [
                'recorded_at'     => $hb->recorded_at->toIso8601String(),
                'battery_level'   => $hb->battery_level,
                'storage_percent' => $hb->storage_percent,
                'wifi_signal_dbm' => $hb->wifi_signal_dbm,
                'ping_ms'         => $hb->ping_ms,
            ] : null;
        }

        return $out;
    }
}
