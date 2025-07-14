<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Inertia\Inertia;
use App\Models\Device;

class DeviceController extends Controller
{
    public function index()
    {
        $devices = Device::active()->with('table', 'branch')->get();
        
        return Inertia::render('Devices', [
            'title' => 'Device',
            'description' => 'List of Registered Devices',
            'devices' => $devices
        ]);
    }
}
