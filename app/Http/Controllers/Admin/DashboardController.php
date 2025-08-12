<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Krypton\Table;
use App\Repositories\Krypton\TableRepository;
use App\Models\Device;

class DashboardController extends Controller
{
    protected $tableRepository;
    public function __construct(TableRepository $tableRepository)
    {
        $this->tableRepository = $tableRepository;
    }

    /**
     * Dashboard for admin
     *
     * @param \Illuminate\Http\Request $request
     * @return \Inertia\Response
     */
    public function index()
    {   
        $tableOrders = $this->tableRepository->getActiveTableOrders();


        foreach ($tableOrders as $tableOrder) {
            $device = Device::where('table_id', $tableOrder->table_id)->first();

            if( $device ) {
                $tableOrder->device = $device->load(['table']);
            }

            
        }

        return Inertia::render('Dashboard', [
            'title' => 'Dashboard',
            'description' => 'Analytics',
            'tableOrders' => $tableOrders,
        ]);
    }
}
