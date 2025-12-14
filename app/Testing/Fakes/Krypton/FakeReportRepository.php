<?php

namespace App\Testing\Fakes\Krypton;

use App\Repositories\Krypton\ReportRepository;

class FakeReportRepository extends ReportRepository
{
    public static function getItemSalesRevenue($startDate, $endDate)
    {
        return [];
    }

    public static function getWeeklySalesReport($startDate, $endDate)
    {
        return [];
    }

    public static function getSalesByMenu($menuId, $startDate, $endDate)
    {
        return [];
    }

    public static function getDailySummary($month, $year)
    {
        return [];
    }

    public static function getDailySummaryReport($month, $year)
    {
        return [];
    }

    public static function getMonthlySales($year)
    {
        return [];
    }

    public static function getTopMenusDaily($date)
    {
        return [];
    }

    public static function getTopMenusMonth($month, $year)
    {
        return [];
    }
}
