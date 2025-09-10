<?php

namespace App\Services;
use App\Models\DeviceOrder;
use Carbon\Carbon;
use Illuminate\Support\Number;
class DashboardService
{
    
    public function totalSales() {

        $totalSales = DeviceOrder::whereDate('created_at', Carbon::now())->get()->sum('total');


        return Number::format($totalSales, 2); 
    }

    public function monthlySales() {

        $sales = DeviceOrder::whereMonth('created_at', Carbon::now()->month)->get()->sum('total');


        return Number::format($sales, 2); 
    }

}