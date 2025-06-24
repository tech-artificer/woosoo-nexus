<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;


use App\Actions\Order\CreateOrder;

class DeviceOrderController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {   
        return CreateOrder::run();
        // $request->user()->orders()->get();
    }
}
