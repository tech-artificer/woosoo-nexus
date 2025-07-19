<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Inertia\Inertia;
use App\Models\Device;
use App\Models\Krypton\Table;


class DeviceController extends Controller
{
    public function index()
    {

        $assignedTableIds = Device::active()->whereNotNull('table_id')->pluck('table_id');
        // Fetch tables from 3rd-party DB that are NOT assigned
        $unassignedTables = Table::whereNotIn('id', $assignedTableIds)->get();
        $devices = Device::active()->with('table', 'branch')->get(); 
        
        return Inertia::render('Devices', [
            'title' => 'Device',
            'description' => 'List of Registered Devices',
            'devices' => $devices,
            'unassignedTables' => $unassignedTables
        ]);
    }

    public function edit(Request $request, Device $device) {

        $request->validate([
            'table_id' => ['required'],
        ]);

        return Inertia::render('Devices/Edit', [
            'device' => $device,
            'table_id' => $request->table_id
        ]);

    }

    public function assignTable(Request $request, Device $device)
    {
        $device->table_id = $request->table_id;
        $device->save();

        return back(); // Don't redirect to 'devices' to avoid full reload
    }
}
