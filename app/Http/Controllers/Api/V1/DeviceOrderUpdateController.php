<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Enums\OrderStatus;
use Illuminate\Validation\Rules\Enum;

class DeviceOrderUpdateController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        $request->validate([
            'order_id' => ['required', 'integer'],
            'status' => ['required', new Enum(OrderStatus::class)],
        ]);

        return $request->all();
    }
}
