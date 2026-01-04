<?php

namespace App\Services\Reports;

use App\Http\Requests\ReportQueryRequest;
use Illuminate\Support\Facades\DB;

class HourlySalesReport
{
    public function getData(ReportQueryRequest $request): array
    {
        $startDate = $request->input('start_date') ?? now()->subDays(7)->toDateString();
        $endDate = $request->input('end_date') ?? now()->toDateString();

        $data = DB::select("
            SELECT 
                HOUR(do.created_at) as hour,
                CONCAT(LPAD(HOUR(do.created_at), 2, '0'), ':00') as hour_label,
                COUNT(*) as transaction_count,
                SUM(do.total) as total_sales,
                ROUND(AVG(do.total), 2) as avg_order_value
            FROM device_orders do
            WHERE DATE(do.created_at) BETWEEN ? AND ?
                AND do.status IN ('COMPLETED', 'CONFIRMED')
            GROUP BY HOUR(do.created_at)
            ORDER BY hour ASC
        ", [$startDate, $endDate]);

        $mapped = collect($data)->map(fn($row) => [
            'hour' => (int) $row->hour,
            'hour_label' => $row->hour_label,
            'transaction_count' => (int) $row->transaction_count,
            'total_sales' => (float) $row->total_sales,
            'avg_order_value' => (float) $row->avg_order_value,
        ])->toArray();

        $meta = [
            'total_rows' => count($mapped),
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];

        return [$mapped, $meta];
    }
}
