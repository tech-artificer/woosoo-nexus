<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\ServiceRequest;
use App\Models\TableService;
use App\Enums\ServiceRequestStatus;
use App\Http\Resources\ServiceRequestResource;
use Carbon\Carbon;

class ServiceRequestController extends Controller
{
    /**
     * Display service requests management page
     */
    public function index(Request $request)
    {
        $query = ServiceRequest::with([
            'tableService', 
            'deviceOrder.table', 
            'deviceOrder.device',
            'assignedDevice',
            'acknowledgedBy',
            'completedBy'
        ]);

        // Apply filters
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('priority') && $request->priority !== 'all') {
            $query->where('priority', $request->priority);
        }

        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        
        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        // Default to showing active requests
        if (!$request->has('show_all') || !$request->boolean('show_all')) {
            $query->active();
        }

        $query->orderBy('created_at', 'desc');

        $serviceRequests = $query->paginate($request->get('per_page', 20));

        // Get statistics
        $stats = [
            'total_pending' => ServiceRequest::pending()->count(),
            'total_active' => ServiceRequest::active()->count(),
            'total_today' => ServiceRequest::whereDate('created_at', today())->count(),
            'avg_response_time' => ServiceRequest::whereNotNull('acknowledged_at')
                ->whereDate('created_at', today())
                ->get()
                ->avg(function ($request) {
                    return $request->created_at->diffInMinutes($request->acknowledged_at);
                }),
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
