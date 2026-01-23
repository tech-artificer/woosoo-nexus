<?php

namespace App\Services\Reports;

use App\Http\Requests\ReportQueryRequest;
use Illuminate\Support\Facades\DB;

class DailySalesReport
{
    public function getData(ReportQueryRequest $request): array
    {
        $startDate = $request->input('start_date') ?? now()->subDays(30)->toDateString();
        $endDate = $request->input('end_date') ?? now()->toDateString();

        $data = DB::table('device_orders')
            ->selectRaw('
                DATE(created_at) as date,
                COUNT(*) as transaction_count,
                SUM(total) as total_sales,
                ROUND(AVG(total), 2) as avg_order_value,
                SUM(guest_count) as total_guests
            ')
            ->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate])
            ->whereIn('status', ['COMPLETED', 'CONFIRMED'])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderByDesc('date')
            ->get()
            ->map(fn($row) => [
                'date' => $row->date,
                'transaction_count' => (int) $row->transaction_count,
                'total_sales' => (float) $row->total_sales,
                'avg_order_value' => (float) $row->avg_order_value,
                'total_guests' => (int) $row->total_guests,
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
