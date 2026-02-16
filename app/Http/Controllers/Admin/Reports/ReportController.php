<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportQueryRequest;
use App\Services\Reports\DailySalesReport;
use App\Services\Reports\MenuItemSalesReport;
use App\Services\Reports\HourlySalesReport;
use App\Services\Reports\GuestCountReport;
use App\Services\Reports\PrintJobAuditReport;
use App\Services\Reports\OrderStatusReport;
use App\Services\Reports\DiscountTaxReport;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function dailySales(ReportQueryRequest $request)
    {
        $service = new DailySalesReport();
        [$data, $meta] = $service->getData($request);

        return Inertia::render('reports/DailySales', [
            'title' => 'Daily Sales Summary',
            'data' => $data,
            'meta' => $meta,
            'startDate' => $request->input('start_date'),
            'endDate' => $request->input('end_date'),
        ]);
    }

    public function menuItems(ReportQueryRequest $request)
    {
        $service = new MenuItemSalesReport();
        [$data, $meta] = $service->getData($request);

        return Inertia::render('reports/MenuItems', [
            'title' => 'Menu Item Sales & Package Best Sellers',
            'data' => $data,
            'meta' => $meta,
            'startDate' => $request->input('start_date'),
            'endDate' => $request->input('end_date'),
        ]);
    }

    public function hourlySales(ReportQueryRequest $request)
    {
        $service = new HourlySalesReport();
        [$data, $meta] = $service->getData($request);

        return Inertia::render('reports/HourlySales', [
            'title' => 'Hourly Sales (Peak Hours)',
            'data' => $data,
            'meta' => $meta,
            'startDate' => $request->input('start_date'),
            'endDate' => $request->input('end_date'),
        ]);
    }

    public function guestCount(ReportQueryRequest $request)
    {
        $service = new GuestCountReport();
        [$data, $meta] = $service->getData($request);

        return Inertia::render('reports/GuestCount', [
            'title' => 'Guest Count Trends',
            'data' => $data,
            'meta' => $meta,
            'startDate' => $request->input('start_date'),
            'endDate' => $request->input('end_date'),
        ]);
    }

    public function printAudit(ReportQueryRequest $request)
    {
        $service = new PrintJobAuditReport();
        [$data, $meta] = $service->getData($request);

        return Inertia::render('reports/PrintAudit', [
            'title' => 'Print Job Audit Log',
            'data' => $data,
            'meta' => $meta,
            'startDate' => $request->input('start_date'),
            'endDate' => $request->input('end_date'),
        ]);
    }

    public function orderStatus(ReportQueryRequest $request)
    {
        $service = new OrderStatusReport();
        [$data, $meta] = $service->getData($request);

        return Inertia::render('reports/OrderStatus', [
            'title' => 'Order Status Distribution',
            'data' => $data,
            'meta' => $meta,
            'startDate' => $request->input('start_date'),
            'endDate' => $request->input('end_date'),
        ]);
    }

    public function discountTax(ReportQueryRequest $request)
    {
        $service = new DiscountTaxReport();
        [$data, $meta] = $service->getData($request);

        return Inertia::render('reports/DiscountTax', [
            'title' => 'Discount & Tax Analysis',
            'data' => $data,
            'meta' => $meta,
            'startDate' => $request->input('start_date'),
            'endDate' => $request->input('end_date'),
        ]);
    }
}
