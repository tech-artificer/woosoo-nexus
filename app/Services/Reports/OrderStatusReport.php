<?php

namespace App\Services\Reports;

use App\Http\Requests\ReportQueryRequest;
use App\Models\DeviceOrder;

class OrderStatusReport
{
    public function getData(ReportQueryRequest $request): array
    {
        $startDate = $request->input('start_date') ?? now()->subDays(30)->toDateString();
        $endDate = $request->input('end_date') ?? now()->toDateString();

        // Use Eloquent model with date range filtering
        $data = DeviceOrder::query()
            ->selectRaw('
                status,
                COUNT(*) as order_count,
                SUM(total) as total_revenue,
                ROUND(AVG(total), 2) as avg_order_value,
                SUM(guest_count) as total_guests
            ')
            ->whereBetween('created_at', [
                now()->parse($startDate)->startOfDay(),
                now()->parse($endDate)->endOfDay(),
            ])
            ->groupBy('status')
            ->orderByDesc('order_count')
            ->get()
            ->map(fn($row) => [
                'status' => $row->status,
                'order_count' => (int) $row->order_count,
                'total_revenue' => (float) $row->total_revenue,
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
