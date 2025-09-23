<?php

namespace App\Repositories\Krypton;

use Illuminate\Support\Facades\DB;

class ReportRepository
{
    protected $connection = 'pos';

    public static function getItemSalesRevenue($startDate, $endDate)
    {
        try {
            return DB::connection('pos')->select('CALL get_item_sales_revenue(?,?)', [$startDate, $endDate]);
        } catch (\Exception $e) {
            \Log::error('Procedure call failed: ' . $e->getMessage());
            throw new \Exception('Something Went Wrong.');
        }
    }

    public static function getWeeklySalesReport($startDate, $endDate)
    {
        try {
            return DB::connection('pos')->select('CALL get_weekly_sales_report(?,?)', [$startDate, $endDate]);
        } catch (\Exception $e) {
            \Log::error('Procedure call failed: ' . $e->getMessage());
            throw new \Exception('Something Went Wrong.');
        }
    }
    public static function getSalesByMenu($menuId, $startDate, $endDate)
    {
        try {
            return DB::connection('pos')->select('CALL get_sales_by_menu(?,?,?)', [$menuId, $startDate, $endDate]);
        } catch (\Exception $e) {
            \Log::error('Procedure call failed: ' . $e->getMessage());
            throw new \Exception('Something Went Wrong.');
        }
    }

    public static function getDailySummary($month, $year)
    {
        try {
            return DB::connection('pos')->select('CALL get_daily_summary(?,?)', [$month, $year]);
        } catch (\Exception $e) {
            \Log::error('Procedure call failed: ' . $e->getMessage());
            throw new \Exception('Something Went Wrong.');
        }
    }

    public static function getDailySummaryReport($month, $year)
    {
        try {
            return DB::connection('pos')->select('CALL get_daily_summary_report(?,?)', [$month, $year]);
        } catch (\Exception $e) {
            \Log::error('Procedure call failed: ' . $e->getMessage());
            throw new \Exception('Something Went Wrong.');
        }
    }

    public static function getMonthlySales($year)
    {
        try {
            return DB::connection('pos')->select('CALL get_monthly_sales(?)', [$year]);
        } catch (\Exception $e) {
            \Log::error('Procedure call failed: ' . $e->getMessage());
            throw new \Exception('Something Went Wrong.');
        }
    }

    public static function getTopMenusDaily($date)
    {
        try {
            return DB::connection('pos')->select('CALL get_top_menus_daily(?)', [$date]);
        } catch (\Exception $e) {
            \Log::error('Procedure call failed: ' . $e->getMessage());
            throw new \Exception('Something Went Wrong.');
        }
    }

    public static function getTopMenusMonth($month, $year)
    {
        try {
            return DB::connection('pos')->select('CALL get_top_menus_month(?,?)', [$month, $year]);
        } catch (\Exception $e) {
            \Log::error('Procedure call failed: ' . $e->getMessage());
            throw new \Exception('Something Went Wrong.');
        }
    }

}