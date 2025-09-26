<?php

namespace App\Http\Controllers\Admin\Device;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Inertia\Inertia;
use App\Models\Device;
use App\Models\Krypton\Table;
use App\Models\DeviceRegistrationCode;

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
        
        return Inertia::render('Devices/Index', [
            'title' => 'Device',
            'description' => 'List of Registered Devices',
            'devices' => $devices,
            'registrationCodes' => $registrationCodes
        ]);
    }

    public function update(Request $request, Device $device) {

        $request->validate([
            'name' => ['required', 'string'],
            'ip_address' => ['required'],
            'port' => ['nullable'],
            'table_id' => ['required'],
        ]);

        $device->update([
            'name' => $request->name,
            'ip_address' => $request->ip_address,
            'port' => $request->port,
            'table_id' => $request->table_id,
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
}
