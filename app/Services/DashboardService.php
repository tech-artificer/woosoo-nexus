<?php

namespace App\Services;
use App\Models\DeviceOrder;
use App\Enums\OrderStatus;
use Carbon\Carbon;

class DashboardService
{
    
    public function totalSales($startDate = null, $endDate = null) {
        $query = DeviceOrder::query();
        
        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        } else {
            $query->whereDate('created_at', Carbon::now());
        }

        $totalSales = $query->whereIn('status', [
            OrderStatus::COMPLETED,
            OrderStatus::CONFIRMED
        ])->sum('total');

        return number_format((float) $totalSales, 2, '.', ','); 
    }

    public function monthlySales($month = null, $year = null) {
        $query = DeviceOrder::query();
        
        if ($month && $year) {
            $query->whereMonth('created_at', $month)
                  ->whereYear('created_at', $year);
        } else {
            $query->whereMonth('created_at', Carbon::now()->month)
                  ->whereYear('created_at', Carbon::now()->year);
        }

        $sales = $query->whereIn('status', [
            OrderStatus::COMPLETED,
            OrderStatus::CONFIRMED
        ])->sum('total');

        return number_format((float) $sales, 2, '.', ','); 
    }

    public function getTotalOrders($startDate = null, $endDate = null) {
        $query = DeviceOrder::query();
        
        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        } else {
            $query->whereDate('created_at', Carbon::now());
        }

        return $query->whereIn('status', [
            OrderStatus::COMPLETED,
            OrderStatus::CONFIRMED
        ])->count();
    }

    public function getTotalGuests($startDate = null, $endDate = null) {
        $query = DeviceOrder::query();
        
        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        } else {
            $query->whereDate('created_at', Carbon::now());
        }

        return $query->sum('guest_count') ?? 0;
    }

    /**
     * Get top selling menu items for today (or a date range).
     */
    public function getTopItems(int $limit = 5, $startDate = null, $endDate = null): array
    {
        $query = DeviceOrder::query();

        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        } else {
            $query->whereDate('created_at', Carbon::today());
        }

        $orders = $query->whereIn('status', [
            OrderStatus::COMPLETED,
            OrderStatus::CONFIRMED,
        ])->with('items')->get();

        $totals = [];
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $name = $item->name ?? $item->menu_name ?? 'Unknown';
                $qty  = (int) ($item->quantity ?? 1);
                $price = (float) ($item->price ?? $item->unit_price ?? 0);
                if (!isset($totals[$name])) {
                    $totals[$name] = ['name' => $name, 'qty' => 0, 'revenue' => 0.0];
                }
                $totals[$name]['qty'] += $qty;
                $totals[$name]['revenue'] += $price * $qty;
            }
        }

        usort($totals, fn($a, $b) => $b['qty'] <=> $a['qty']);

        return array_slice(array_values($totals), 0, $limit);
    }

    public function getSalesData($days = 7) {
        $today = Carbon::today();
        $start = $today->copy()->subDays($days - 1)->startOfDay();

        $daily = DeviceOrder::where('created_at', '>=', $start)
            ->whereIn('status', [
                OrderStatus::COMPLETED,
                OrderStatus::CONFIRMED
            ])
            ->selectRaw("DATE(created_at) as date, SUM(total) as total, COUNT(*) as count")
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $chartData = [];
        for ($i = 0; $i < $days; $i++) {
            $d = $start->copy()->addDays($i);
            $dateStr = $d->toDateString();
            $data = $daily->get($dateStr);
            
            $chartData[] = [
                'date' => $d->format('M d'),
                'sales' => $data ? (float) $data->total : 0,
                'orders' => $data ? (int) $data->count : 0,
            ];
        }

        return $chartData;
    }
}
