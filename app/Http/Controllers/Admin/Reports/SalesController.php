<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\DeviceOrder;
use Carbon\Carbon;
use App\Repositories\Krypton\ReportRepository;

class SalesController extends Controller
{

    public function index()
    {
        // $service = $registry->resolve($type);
        // [$rows, $meta] = $service->list($request);
        $year = Carbon::now()->year;
        $sales = ReportRepository::getMonthlySales($year);
        return Inertia::render('reports/sales/Index', [
            'title' => 'Sales Report',
            'description' => 'View and analyze sales data',
            'data' => $sales,
            'filters' => [],
        ]);

        // return response()->json([
        // 'data' => $rows,
        // 'meta' => $meta,
        // ]);
    }

    // public function index(Request $request)
    // {
    //     // $request->validate([
    //     //    'report_type' => ['required'],
    //     //    'start_date' => ['required'],
    //     //    'end_date' => ['required']
    //     // ]);
    //     $date = Carbon::now()->format('Y-m-d');
    //     $month = Carbon::now()->subMonth(2)->month;
    //     $year = Carbon::now()->year;
    //     $sales = ReportRepository::getMonthlySales($year);
    //     // $sales = ReportRepository::getTopMenusMonth($month, $year);
        
    //     // $sales = DeviceOrder::whereMonth('created_at', Carbon::now()->month)->get()->sum('total');
    //     return Inertia::render('reports/sales/Index', [
    //         'title' => 'Sales Report',
    //         'description' => 'View and analyze sales data',
    //         'range' => $date,
    //         'sales' => $sales,
    //     ]);
    // }
}
