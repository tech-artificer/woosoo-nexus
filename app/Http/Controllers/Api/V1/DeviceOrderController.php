<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\StoreDeviceOrderRequest;

use App\Actions\Order\CreateOrder;


use App\Models\Krypton\Order;
use App\Models\Krypton\OrderCheck;
use App\Models\Krypton\OrderedMenu;
use App\Models\Krypton\TableOrder;
use App\Models\Krypton\TableLink;
use App\Models\Krypton\Table;

class DeviceOrderController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(StoreDeviceOrderRequest $request)
    {   
        return CreateOrder::run($request, $request->validated());

        // $tableId = $request->user()->table_id;

        // $table = Table::find($tableId);

        // return $table->tableOrders()->get();
    }
}
