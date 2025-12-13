<?php

namespace App\Http\Controllers\Admin\Device;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use App\Models\Device;
use App\Models\Krypton\Table;
use App\Models\DeviceRegistrationCode;
use App\Http\Requests\StoreDeviceRequest;
use App\Http\Requests\UpdateDeviceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function index()
    {
     $assignedTableIds = Device::active()->whereNotNull('table_id')->pluck('table_id');
        // Fetch tables from 3rd-party DB that are NOT assigned
        $unassignedTables = Table::whereNotIn('id', $assignedTableIds)->get();
        $devices = Device::active()->with('table', 'branch', 'registrationCode')->get(); 
        $registrationCodes = DeviceRegistrationCode::with(['device'])->get();

        inertia()->share('unassignedTables', $unassignedTables);

        // Device stats: total, registration codes, sparkline of devices created in last 7 days
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
            [ 'title' => 'Registration Codes', 'value' => $registrationCodes->count(), 'subtitle' => 'Available codes', 'variant' => 'accent' ],
        ];

        return Inertia::render('Devices/Index', [
            'title' => 'Device',
            'description' => 'List of Registered Devices',
            'devices' => $devices,
            'registrationCodes' => $registrationCodes,
            'stats' => $stats,
        ]);
    }

    /**
     * Show the form for creating a new device.
     */
    public function create()
    {
        $assignedTableIds = Device::active()->whereNotNull('table_id')->pluck('table_id');
        $unassignedTables = Table::whereNotIn('id', $assignedTableIds)->get();

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

        $device = Device::create([
            'name' => $data['name'],
            'ip_address' => $data['ip_address'],
            'port' => $data['port'] ?? null,
            'table_id' => $data['table_id'] ?? null,
            'is_active' => true,
        ]);

        return redirect()->route('devices.index')->with('success', 'Device created.');
    }

    /**
     * Show the form for editing the specified device.
     */
    public function edit(Device $device)
    {
        $assignedTableIds = Device::active()->whereNotNull('table_id')->pluck('table_id');
        $unassignedTables = Table::whereNotIn('id', $assignedTableIds)->get();

        return Inertia::render('Devices/Edit', [
            'title' => 'Edit Device',
            'description' => 'Edit device details',
            'device' => $device->load('table', 'branch', 'registrationCode'),
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
     * Generate registration codes (web action).
     */
    public function generateCodes(Request $request)
    {
        $count = (int) $request->input('count', 10);
        $count = max(1, min(1000, $count));

        $created = [];
        for ($i = 0; $i < $count; $i++) {
            // ensure uniqueness
            do {
                $code = Str::upper(Str::random(6));
            } while (DeviceRegistrationCode::where('code', $code)->exists());

            $created[] = DeviceRegistrationCode::create([
                'code' => $code,
            ]);
        }

        // If this was an AJAX / JSON request, return the created codes directly
        if ($request->wantsJson() || $request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json([
                'success' => true,
                'count' => $count,
                'created' => array_map(function($c) { return ['id' => $c->id, 'code' => $c->code, 'used_by_device_id' => $c->used_by_device_id ?? null, 'used_at' => $c->used_at ?? null]; }, $created)
            ], 201);
        }

        return redirect()->route('devices.index')->with('success', "Generated {$count} device codes.");
    }
}
