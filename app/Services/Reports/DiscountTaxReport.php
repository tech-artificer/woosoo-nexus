<?php

namespace App\Services\Reports;

use App\Http\Requests\ReportQueryRequest;
use Illuminate\Support\Facades\DB;

class DiscountTaxReport
{
    public function getData(ReportQueryRequest $request): array
    {
        $startDate = $request->input('start_date') ?? now()->subDays(30)->toDateString();
        $endDate = $request->input('end_date') ?? now()->toDateString();

        $data = DB::table('device_orders')
            ->selectRaw('
                DATE(created_at) as date,
                COUNT(*) as order_count,
                SUM(discount) as total_discount,
                ROUND(AVG(discount), 2) as avg_discount,
                SUM(tax) as total_tax,
                ROUND(AVG(tax), 2) as avg_tax,
                SUM(total) as total_sales,
                ROUND((SUM(discount) / SUM(total) * 100), 2) as discount_percentage,
                ROUND((SUM(tax) / SUM(total) * 100), 2) as tax_percentage
            ')
            ->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate])
            ->whereIn('status', ['COMPLETED', 'CONFIRMED'])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderByDesc('date')
            ->get()
            ->map(fn($row) => [
                'date' => $row->date,
                'order_count' => (int) $row->order_count,
                'total_discount' => (float) $row->total_discount,
                'avg_discount' => (float) $row->avg_discount,
                'total_tax' => (float) $row->total_tax,
                'avg_tax' => (float) $row->avg_tax,
                'total_sales' => (float) $row->total_sales,
                'discount_percentage' => (float) $row->discount_percentage,
                'tax_percentage' => (float) $row->tax_percentage,
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
