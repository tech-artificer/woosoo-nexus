<?php

namespace App\Services\Reports;

use App\Http\Requests\ReportQueryRequest;
use Illuminate\Support\Facades\DB;

class GuestCountReport
{
    public function getData(ReportQueryRequest $request): array
    {
        $startDate = $request->input('start_date') ?? now()->subDays(30)->toDateString();
        $endDate = $request->input('end_date') ?? now()->toDateString();

        $data = DB::table('device_orders')
            ->selectRaw('
                DATE(created_at) as date,
                SUM(guest_count) as total_guests,
                COUNT(*) as order_count,
                ROUND(AVG(guest_count), 2) as avg_guests_per_order
            ')
            ->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate])
            ->whereIn('status', ['COMPLETED', 'CONFIRMED'])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderByDesc('date')
            ->get()
            ->map(fn($row) => [
                'date' => $row->date,
                'total_guests' => (int) $row->total_guests,
                'order_count' => (int) $row->order_count,
                'avg_guests_per_order' => (float) $row->avg_guests_per_order,
            ])
            ->toArray();

        $meta = [
            'total_rows' => count($data),
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];

        return [$data, $meta];
    }
}
