<?php

namespace App\Services\Reports;

use App\Http\Requests\ReportQueryRequest;
use Illuminate\Support\Facades\DB;

class MenuItemSalesReport
{
    public function getData(ReportQueryRequest $request): array
    {
        $startDate = $request->input('start_date') ?? now()->subDays(30)->toDateString();
        $endDate = $request->input('end_date') ?? now()->toDateString();
        $sortBy = $request->input('sort_by', 'total_revenue');
        $sortDir = $request->input('sort_dir', 'desc');

        $query = DB::table('device_order_items')
            ->join('device_orders', 'device_order_items.order_id', '=', 'device_orders.id')
            ->selectRaw('
                device_order_items.menu_id,
                COALESCE(menus.name, "Unknown Menu Item") as menu_name,
                COUNT(*) as quantity_sold,
                SUM(device_order_items.total) as total_revenue,
                ROUND(AVG(device_order_items.price), 2) as avg_price,
                ROW_NUMBER() OVER (PARTITION BY device_order_items.order_id ORDER BY device_order_items.id) as item_position
            ')
            ->leftJoin('menus', 'device_order_items.menu_id', '=', 'menus.id')
            ->whereBetween(DB::raw('DATE(device_orders.created_at)'), [$startDate, $endDate])
            ->whereIn('device_orders.status', ['COMPLETED', 'CONFIRMED'])
            ->groupBy('device_order_items.menu_id', 'menus.name');

        // Raw query with position flag to identify packages (first item in order)
        $data = DB::select("
            SELECT 
                doi.menu_id,
                COALESCE(m.name, 'Unknown Menu Item') as menu_name,
                COUNT(*) as quantity_sold,
                SUM(doi.total) as total_revenue,
                ROUND(AVG(doi.price), 2) as avg_price,
                SUM(CASE WHEN roi.item_num = 1 THEN 1 ELSE 0 END) as package_count
            FROM device_order_items doi
            INNER JOIN device_orders do ON doi.order_id = do.id
            LEFT JOIN menus m ON doi.menu_id = m.id
            LEFT JOIN (
                SELECT order_id, menu_id, ROW_NUMBER() OVER (PARTITION BY order_id ORDER BY id) as item_num
                FROM device_order_items
            ) roi ON doi.id = roi.id
            WHERE DATE(do.created_at) BETWEEN ? AND ?
                AND do.status IN ('COMPLETED', 'CONFIRMED')
            GROUP BY doi.menu_id, m.name
            ORDER BY {$this->getSortField($sortBy)} {$sortDir}
        ", [$startDate, $endDate]);

        $mapped = collect($data)->map(fn($row) => [
            'menu_id' => $row->menu_id,
            'menu_name' => $row->menu_name,
            'quantity_sold' => (int) $row->quantity_sold,
            'total_revenue' => (float) $row->total_revenue,
            'avg_price' => (float) $row->avg_price,
            'package_count' => (int) $row->package_count,
            'is_package_best_seller' => (int) $row->package_count > 0,
        ])->toArray();

        $meta = [
            'total_rows' => count($mapped),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'note' => 'Package count shows how many orders had this item as the first item (package)',
        ];

        return [$mapped, $meta];
    }

    private function getSortField(string $field): string
    {
        return match($field) {
            'quantity_sold' => 'quantity_sold',
            'total_revenue' => 'total_revenue',
            'avg_price' => 'avg_price',
            'package_count' => 'package_count',
            default => 'total_revenue',
        };
    }
}
