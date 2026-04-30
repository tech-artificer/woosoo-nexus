<?php

namespace App\Http\Controllers\Admin\Device;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeviceRequest;
use App\Http\Requests\UpdateDeviceRequest;
use App\Models\Branch;
use App\Models\Device;
use App\Models\Krypton\Table;
use App\Services\CertificatePathResolver;
use App\Support\DeviceSecurityCode;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use RuntimeException;

class DeviceController extends Controller
{
    public function __construct(
        private readonly CertificatePathResolver $certificatePathResolver,
    ) {}

    public function index()
    {
        $assignedTableIds = Device::active()->whereNotNull('table_id')->pluck('table_id');
        // Fetch tables from 3rd-party DB that are NOT assigned
        try {
            $unassignedTables = Table::whereNotIn('id', $assignedTableIds)->get();
        } catch (QueryException $e) {
            session()->flash('warning', 'Table data is unavailable — POS system is currently offline.');
            $unassignedTables = collect([]);
        }
        // Include soft-deleted rows so admins can reactivate previously deactivated
        // devices directly from the Devices page.
        $devices = Device::withTrashed()
            ->with('table', 'branch')
            ->get()
            ->each(fn (Device $device) => $device->makeVisible('deleted_at'));
        $securityReadyCount = $devices->whereNotNull('security_code_generated_at')->count();

        inertia()->share('unassignedTables', $unassignedTables);

        // Device stats: total, security-ready count, sparkline of devices created in last 7 days
        $today = Carbon::today();
        $start = $today->copy()->subDays(6)->startOfDay();

        $daily = Device::where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as cnt')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('cnt', 'date')
            ->toArray();

        $spark = [];
        for ($i = 0; $i < 7; $i++) {
            $d = $start->copy()->addDays($i)->toDateString();
            $spark[] = isset($daily[$d]) ? (int) $daily[$d] : 0;
        }

        $stats = [
            ['title' => 'Total Devices', 'value' => $devices->count(), 'subtitle' => 'Registered devices', 'variant' => 'primary', 'sparkline' => $spark],
            ['title' => 'Security Ready', 'value' => $securityReadyCount, 'subtitle' => 'Devices with security code', 'variant' => 'accent'],
        ];

        return Inertia::render('Devices/Index', [
            'title' => 'Device',
            'description' => 'List of Registered Devices',
            'devices' => $devices,
            'stats' => $stats,
        ]);
    }

    /**
     * Show the form for creating a new device.
     */
    public function create()
    {
        $assignedTableIds = Device::active()->whereNotNull('table_id')->pluck('table_id');
        try {
            $unassignedTables = Table::whereNotIn('id', $assignedTableIds)->get();
        } catch (QueryException $e) {
            $unassignedTables = collect([]);
        }

        return Inertia::render('Devices/Create', [
            'title' => 'Create Device',
            'description' => 'Register a new device',
            'unassignedTables' => $unassignedTables,
        ]);
    }

    /**
     * Store a newly created device in storage.
     */
    public function store(StoreDeviceRequest $request): RedirectResponse
    {
        $data = $request->validated();

        \Log::info('Device store requested', ['data' => $data]);

        $branchId = $this->resolveBranchIdForDeviceCreate($request);

        \Log::info('Branch resolved', ['branch_id' => $branchId]);

        if ($branchId === null) {
            \Log::warning('Branch ID is null, rejecting device creation');

            return back()
                ->withInput()
                ->withErrors([
                    'branch' => 'Cannot create device: no branch context is available. Assign the user to a branch or keep exactly one branch in this install.',
                ]);
        }

        $ipAddress = $data['ip_address'] ?? null;
        $requestedSecurityCode = trim((string) ($data['security_code'] ?? ''));
        $isGeneratedCode = $requestedSecurityCode === '';
        $result = null;

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $plainSecurityCode = $isGeneratedCode
                ? $this->generateUniqueSecurityCode()
                : $requestedSecurityCode;

            \Log::info('Security code generated', ['code_length' => strlen($plainSecurityCode)]);
            \Log::info('About to create device with payload', [
                'name' => $data['name'],
                'branch_id' => $branchId,
                'ip_address' => $data['ip_address'] ?? null,
                'port' => $data['port'] ?? null,
                'table_id' => $data['table_id'] ?? null,
            ]);

            try {
                $result = DB::transaction(function () use ($data, $branchId, $ipAddress, $plainSecurityCode): array {
                    if (DeviceSecurityCode::isAssigned($plainSecurityCode)) {
                        throw new RuntimeException('security_code_assigned');
                    }

                    $deviceWithIp = Device::withTrashed()
                        ->where('ip_address', $ipAddress)
                        ->lockForUpdate()
                        ->first();

                    $nameConflict = Device::withTrashed()
                        ->where('name', $data['name'])
                        ->when($deviceWithIp, fn ($query) => $query->whereKeyNot($deviceWithIp->id))
                        ->lockForUpdate()
                        ->first();

                    if ($nameConflict) {
                        throw new RuntimeException('name_assigned');
                    }

                    $attributes = array_merge([
                        'name' => $data['name'],
                        'branch_id' => $branchId,
                        'ip_address' => $ipAddress,
                        'port' => $data['port'] ?? null,
                        'table_id' => $data['table_id'] ?? null,
                        'type' => $data['type'] ?? null,
                        'is_active' => true,
                    ], DeviceSecurityCode::attributesFor($plainSecurityCode));

                    if ($deviceWithIp) {
                        if (! $deviceWithIp->trashed()) {
                            throw new RuntimeException('ip_address_assigned');
                        }

                        $deviceWithIp->restore();
                        $deviceWithIp->update($attributes);

                        return ['device' => $deviceWithIp, 'restored' => true];
                    }

                    $device = Device::create($attributes);

                    return ['device' => $device, 'restored' => false];
                }, 3);

                $device = $result['device'];
                \Log::info('Device stored successfully', ['device_id' => $device->id, 'restored' => $result['restored']]);

                break;
            } catch (RuntimeException $e) {
                if ($e->getMessage() === 'security_code_assigned') {
                    \Log::warning('Security code already exists, rejecting device creation');

                    if ($isGeneratedCode) {
                        continue;
                    }

                    return back()
                        ->withInput()
                        ->withErrors([
                            'security_code' => 'This security code is already assigned to another device.',
                        ]);
                }

                if ($e->getMessage() === 'ip_address_assigned') {
                    return back()
                        ->withInput()
                        ->withErrors([
                            'ip_address' => 'This IP is already assigned to an active device.',
                        ]);
                }

                if ($e->getMessage() === 'name_assigned') {
                    return back()
                        ->withInput()
                        ->withErrors([
                            'name' => 'This device name is already assigned.',
                        ]);
                }

                throw $e;
            } catch (QueryException $e) {
                if ($this->isUniqueConstraintViolation($e)) {
                    if ($isGeneratedCode) {
                        continue;
                    }

                    return back()
                        ->withInput()
                        ->withErrors([
                            'ip_address' => 'A device with this IP, name, or security code already exists.',
                        ]);
                }

                throw $e;
            }
        }

        if ($result === null) {
            return back()
                ->withInput()
                ->withErrors([
                    'security_code' => 'Unable to generate a unique security code. Please try again.',
                ]);
        }

        try {
            if ($result['restored']) {
                return redirect()
                    ->route('devices.index')
                    ->with('success', 'Deactivated device reactivated.')
                    ->with('security_code_reveal', $plainSecurityCode);
            }
        } catch (RuntimeException $e) {
            \Log::error('RuntimeException caught during device creation', ['message' => $e->getMessage()]);

            if (str_contains($e->getMessage(), 'Local install must have exactly one branch record')) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'branch' => 'Cannot create device: no branch context is available. Assign the user to a branch or keep exactly one branch in this install.',
                    ]);
            }

            throw $e;
        }

        \Log::info('About to redirect to devices.index');

        return redirect()
            ->route('devices.index')
            ->with('success', 'Device created.')
            ->with('security_code_reveal', $plainSecurityCode);
    }

    private function securityCodeExists(string $plainSecurityCode): bool
    {
        return DeviceSecurityCode::isAssigned($plainSecurityCode);
    }

    private function generateUniqueSecurityCode(int $maxAttempts = 20): string
    {
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $candidate = (string) random_int(100000, 999999);

            if (! $this->securityCodeExists($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('Unable to generate a unique security code. Please try again.');
    }

    private function resolveBranchIdForDeviceCreate(Request $request): ?int
    {
        $user = $request->user();

        if ($user && method_exists($user, 'branches')) {
            try {
                $userBranchId = $user->branches()->select('branches.id')->value('branches.id');

                if ($userBranchId !== null) {
                    return (int) $userBranchId;
                }
            } catch (\Throwable $e) {
                // Fallback to install-level branch resolution below.
            }
        }

        $branchCount = Branch::query()->count();

        if ($branchCount === 1) {
            return (int) Branch::query()->value('id');
        }

        return null;
    }

    private function isUniqueConstraintViolation(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? '');
        $driverCode = (string) ($e->errorInfo[1] ?? '');

        return $sqlState === '23000' || $driverCode === '1062' || $driverCode === '19';
    }

    /**
     * Show the form for editing the specified device.
     */
    public function edit(Device $device)
    {
        // Get all assigned table IDs EXCEPT the one currently assigned to this device
        // so the device's own table appears in the dropdown
        $assignedTableIds = Device::active()
            ->whereNotNull('table_id')
            ->where('id', '!=', $device->id)
            ->pluck('table_id');

        try {
            $unassignedTables = Table::whereNotIn('id', $assignedTableIds)->get();
        } catch (QueryException $e) {
            $unassignedTables = collect([]);
        }

        return Inertia::render('Devices/Edit', [
            'title' => 'Edit Device',
            'description' => 'Edit device details',
            'device' => $device->load('table', 'branch'),
            'unassignedTables' => $unassignedTables,
        ]);
    }

    public function update(UpdateDeviceRequest $request, Device $device)
    {

        $data = $request->validated();

        $ipAddress = $data['ip_address'];
        $conflictingTrashedDevice = Device::onlyTrashed()
            ->where('ip_address', $ipAddress)
            ->where('id', '!=', $device->id)
            ->first();

        if ($conflictingTrashedDevice) {
            return back()
                ->withInput()
                ->withErrors([
                    'ip_address' => 'This IP belongs to a deactivated device. Reactivate that device instead of reassigning the IP.',
                ]);
        }

        $device->update([
            'name' => $data['name'],
            'ip_address' => $ipAddress,
            'port' => $data['port'] ?? null,
            'table_id' => $data['table_id'] ?? null,
            'type' => $data['type'] ?? null,
        ]);

        return redirect()
            ->route('devices.index')
            ->with('success', 'Device updated.');
        // return Inertia::render('Devices/Edit', [
        //     'device' => $device,
        //     'table_id' => $request->table_id
        // ]);

    }

    // public function assignTable(Request $request, Device $device)
    // {
    //     $request->validate([
    //         'table_id' => ['required'],
    //     ]);

    //     $table = Table::find($request->table_id);

    //     if( !$table ) {
    //         return back()->with(['error' => 'Table not found']);
    //     }

    //     $device->table_id = $request->table_id;
    //     $device->save();

    //     return to_route('devices')->with(['success' => 'Device assigned successfully']);
    // }

    public function destroy(Device $device)
    {

        $device->delete();

        return redirect()
            ->route('devices.index')
            ->with('success', 'Device trashed.');
    }

    public function restore(Request $request, int $id)
    {

        $device = Device::withTrashed()->findOrFail($id);
        $device->restore();

        return redirect()
            ->route('devices.index')
            ->with('success', 'Device restored.');
    }

    /**
     * Download the printer APK (release or debug) from storage.
     */
    public function downloadApk(string $channel = 'release')
    {
        $channel = $channel === 'debug' ? 'debug' : 'release';
        $filename = $channel === 'release' ? 'app-release.apk' : 'app-debug.apk';
        $path = storage_path("app/public/printer-app/{$channel}/{$filename}");

        if (! file_exists($path)) {
            return redirect()
                ->route('devices.index')
                ->with('error', "{$filename} not found. Upload it to storage/app/public/printer-app/{$channel}/.");
        }

        return response()->download($path, "printer-app-{$channel}.apk", [
            'Content-Type' => 'application/vnd.android.package-archive',
        ]);
    }

    /**
     * Download the self-signed CA/server certificate so devices can trust the local HTTPS stack.
     *
     * Served with application/x-x509-ca-cert so Android/iOS prompt the user to install it.
     * The endpoint is intentionally accessible over plain HTTP (nginx exception) so devices
     * can bootstrap trust before they have a valid HTTPS connection to this server.
     *
     * Android installation: Settings → Security → Install from storage.
     * iOS: tap .crt → Settings → General → VPN & Device Management → Install.
     */
    public function downloadCertificate()
    {
        $path = $this->certificatePathResolver->resolveCertificatePath();

        if ($path !== null) {
            return response()->download($path, 'woosoo-ca.crt', [
                'Content-Type' => 'application/x-x509-ca-cert',
            ]);
        }

        return response('CA certificate not found. Contact system administrator.', 404)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    /**
     * Generate a personal access token for the device via admin UI.
     * Returns JSON with the plain token when requested via AJAX, otherwise
     * redirects back and flashes the token in the session (display once).
     */
    public function createToken(Request $request, Device $device)
    {
        $user = $request->user();
        if (! $user || ! ($user->is_admin ?? false)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        // Note: we do not automatically revoke existing tokens here to avoid
        // accidental service disruption. Admins may revoke tokens manually if needed.

        // Re-resolve the device to ensure we have a persisted model instance
        $device = Device::find($device->id);
        if (! $device) {
            return response()->json(['success' => false, 'message' => 'Device not found'], 404);
        }

        $expiresAt = now()->addDays(365); // admin-issued tokens expire in 1 year by default
        // Use default '*' abilities to ensure the token can be used for typical device auth flows.
        $personalToken = $device->createToken(name: 'admin-issued', abilities: ['*'], expiresAt: $expiresAt);
        $plain = $personalToken->plainTextToken;

        if ($request->wantsJson() || $request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json(['success' => true, 'token' => $plain, 'expires_at' => $expiresAt->toDateTimeString()]);
        }

        return redirect()->route('devices.index')->with('device_token', $plain)->with('success', 'Device token created.');
    }

    /**
     * Regenerate a device's registration (security) code via admin UI.
     * Returns the plain code once as JSON. Always AJAX-only.
     */
    public function regenerateSecurityCode(Request $request, Device $device)
    {
        $user = $request->user();
        if (! $user || ! ($user->is_admin ?? false)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $device = Device::find($device->id);
        if (! $device) {
            return response()->json(['success' => false, 'message' => 'Device not found'], 404);
        }

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $plain = (string) random_int(100000, 999999);

            try {
                DB::transaction(function () use ($device, $plain): void {
                    if (DeviceSecurityCode::isAssigned($plain, (int) $device->id)) {
                        throw new RuntimeException('security_code_assigned');
                    }

                    $lockedDevice = Device::whereKey($device->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $lockedDevice->tokens()->delete();
                    $lockedDevice->update(DeviceSecurityCode::attributesFor($plain));
                }, 3);

                return response()->json(['security_code' => $plain]);
            } catch (RuntimeException $e) {
                if ($e->getMessage() === 'security_code_assigned') {
                    continue;
                }
                throw $e;
            }
        }

        return response()->json(['message' => 'Unable to generate a unique security code.'], 409);
    }
}
