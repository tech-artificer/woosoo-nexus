<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\ServiceRequest;
use App\Models\TableService;
use App\Http\Resources\ServiceRequestResource;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class ServiceRequestController extends Controller
{
    /**
     * Display service requests management page
     */
    public function index(Request $request)
    {
        $hasStatusColumn = Schema::hasColumn('service_requests', 'status');
        $hasPriorityColumn = Schema::hasColumn('service_requests', 'priority');
        $hasAcknowledgedAtColumn = Schema::hasColumn('service_requests', 'acknowledged_at');

        $query = ServiceRequest::with([
            'tableService', 
            'deviceOrder.table', 
            'deviceOrder.device',
            'assignedDevice',
            'acknowledgedBy',
            'completedBy'
        ]);

        // Apply filters
        if ($hasStatusColumn && $request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($hasPriorityColumn && $request->has('priority') && $request->priority !== 'all') {
            $query->where('priority', $request->priority);
        }

        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        
        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        // Default to showing active requests
        if ($hasStatusColumn && (!$request->has('show_all') || !$request->boolean('show_all'))) {
            $query->active();
        }

        $query->orderBy('created_at', 'desc');

        $serviceRequests = $query->paginate($request->get('per_page', 20));

        // Get statistics
        $stats = [
            'total_pending' => $hasStatusColumn ? ServiceRequest::pending()->count() : ServiceRequest::count(),
            'total_active' => $hasStatusColumn ? ServiceRequest::active()->count() : ServiceRequest::count(),
            'total_today' => ServiceRequest::whereDate('created_at', today())->count(),
            'avg_response_time' => $hasAcknowledgedAtColumn
                ? ServiceRequest::whereNotNull('acknowledged_at')
                    ->whereDate('created_at', today())
                    ->get()
                    ->avg(function ($request) {
                        return $request->created_at->diffInMinutes($request->acknowledged_at);
                    })
                : 0,
        ];

        $tableServices = TableService::all();

        return Inertia::render('ServiceRequests/Index', [
            'title' => 'Service Requests',
            'description' => 'Manage customer service requests',
            'serviceRequests' => ServiceRequestResource::collection($serviceRequests->items()),
            'pagination' => [
                'current_page' => $serviceRequests->currentPage(),
                'last_page' => $serviceRequests->lastPage(),
                'per_page' => $serviceRequests->perPage(),
                'total' => $serviceRequests->total(),
            ],
            'stats' => $stats,
            'tableServices' => $tableServices,
            'filters' => [
                'status' => $request->status ?? 'active',
                'priority' => $request->priority ?? 'all',
                'from_date' => $request->from_date,
                'to_date' => $request->to_date,
                'show_all' => $request->boolean('show_all', false),
            ],
        ]);
    }
}
