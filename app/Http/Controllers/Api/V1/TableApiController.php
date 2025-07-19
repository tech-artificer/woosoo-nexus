<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;


use App\Models\Krypton\TableOrder;

class TableApiController extends Controller
{
    public function index()
    {   

        $orders = TableOrder::activeTableOrders();

        return response()->json(['table_orders' => $orders ], 200);
    }
}
