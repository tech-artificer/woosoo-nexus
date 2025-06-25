<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Inertia\Inertia;

class DeviceController extends Controller
{
    public function index()
    {
        return Inertia::render('Devices', [
            'title' => 'Device',
            'description' => 'List of Registered Devices',
        ]);
    }
}
