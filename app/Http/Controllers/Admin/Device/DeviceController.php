<?php

namespace App\Http\Controllers\Admin\Device;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use App\Models\Device;
use App\Models\Krypton\Table;
use App\Http\Requests\StoreDeviceRequest;
use App\Http\Requests\UpdateDeviceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Models\Branch;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DeviceController extends Controller
{
    public function index()
    {
        $assignedTableIds = Device::active()->whereNotNull('table_id')->pluck('table_id');
        // Fetch tables from 3rd-party DB that are NOT assigned
        try {
            $unassignedTables = Table::whereNotIn('id', $assignedTableIds)->get();
        } catch (\Illuminate\Database\QueryException $e) {
            session()->flash('warning', 'Table data is unavailable — POS system is currently offline.');
            $unassignedTables = collect([]);
        }
        $devices = Device::active()->with('table', 'branch')->get();
        $securityReadyCount = $devices->whereNotNull('security_code_generated_at')->count();

        inertia()->share('unassignedTables', $unassignedTables);

        // Device stats: total, security-ready count, sparkline of devices created in last 7 days
        $today = \Carbon\Carbon::today();
        $start = $today->copy()->subDays(6)->startOfDay();

        $daily = Device::where('created_at', '>=', $start)
            ->selectRaw("DATE(created_at) as date, COUNT(*) as cnt")
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
            [ 'title' => 'Total Devices', 'value' => $devices->count(), 'subtitle' => 'Registered devices', 'variant' => 'primary', 'sparkline' => $spark ],
            [ 'title' => 'Security Ready', 'value' => $securityReadyCount, 'subtitle' => 'Devices with security code', 'variant' => 'accent' ],
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
        } catch (\Illuminate\Database\QueryException $e) {
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

        $plainSecurityCode = trim((string) ($data['security_code'] ?? ''));

        if ($plainSecurityCode === '') {
            $plainSecurityCode = $this->generateUniqueSecurityCode();
        }

        \Log::info('Security code generated', ['code_length' => strlen($plainSecurityCode)]);

        // Check for existing device with this security code (Batch 2: Uniqueness Enforcement)
        if ($this->securityCodeExists($plainSecurityCode)) {
            \Log::warning('Security code already exists, rejecting device creation');
            return back()
                ->withInput()
                ->withErrors([
                    'security_code' => 'This security code is already assigned to another device.'
                ]);
        }

        \Log::info('About to create device with payload', [
            'name' => $data['name'],
            'branch_id' => $branchId,
            'ip_address' => $data['ip_address'] ?? null,
            'port' => $data['port'] ?? null,
            'table_id' => $data['table_id'] ?? null,
        ]);

        try {
            $device = Device::create([
                'name' => $data['name'],
                'branch_id' => $branchId,
                'ip_address' => $data['ip_address'] ?? null,
                'port' => $data['port'] ?? null,
                'table_id' => $data['table_id'] ?? null,
                'security_code' => Hash::make($plainSecurityCode),
                'security_code_generated_at' => now(),
                'is_active' => true,
            ]);

            \Log::info('Device created successfully', ['device_id' => $device->id]);
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
        return Device::query()
            ->whereNotNull('security_code')
            ->get(['id', 'security_code'])
            ->contains(fn (Device $device) => Hash::check($plainSecurityCode, (string) $device->security_code));
    }

    private function generateUniqueSecurityCode(int $maxAttempts = 20): string
    {
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $candidate = (string) random_int(100000, 999999);

            if (! $this->securityCodeExists($candidate)) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Unable to generate a unique security code. Please try again.');
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
        } catch (\Illuminate\Database\QueryException $e) {
            $unassignedTables = collect([]);
        }

        return Inertia::render('Devices/Edit', [
            'title' => 'Edit Device',
            'description' => 'Edit device details',
            'device' => $device->load('table', 'branch'),
            'unassignedTables' => $unassignedTables,
        ]);
    }

    public function update(UpdateDeviceRequest $request, Device $device) {

        $data = $request->validated();

        $device->update([
            'name' => $data['name'],
            'ip_address' => $data['ip_address'],
            'port' => $data['port'] ?? null,
            'table_id' => $data['table_id'] ?? null,
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

    public function destroy(Device $device) {

        $device->delete();
        return redirect()
            ->route('devices.index')
            ->with('success', 'Device trashed.');
    }

    public function restore(Request $request, int $id){

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
     * Download the mkcert CA certificate for Flutter app SSL/TLS validation.
     * 
     * Industry standard: Serve CA certificates with application/x-x509-ca-cert MIME type.
     * This is the standard Android expects for certificate installation.
     * This certificate allows Flutter apps to validate HTTPS connections against the self-signed mkcert CA.
     * Android installation: Settings → Security → Install from SD card.
     */
    public function downloadCertificate()
    {
        // Prefer DER (better Android install UX); fall back to PEM if missing
        $derPath = storage_path('app/public/certificates/woosoo-ca.der');
        $pemPath = storage_path('app/public/certificates/CAROOT.pem');

        if (file_exists($derPath)) {
            return response()->download($derPath, 'woosoo-ca.crt', [
                'Content-Type' => 'application/x-x509-ca-cert',
            ]);
        }

        if (file_exists($pemPath)) {
            return response()->download($pemPath, 'woosoo-ca.crt', [
                'Content-Type' => 'application/x-x509-ca-cert',
            ]);
        }

        return redirect()
            ->route('devices.index')
            ->with('error', 'CA certificate not found. Contact system administrator.');
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

}
