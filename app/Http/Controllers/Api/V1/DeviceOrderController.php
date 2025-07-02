<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\StoreDeviceOrderRequest;
use App\Services\Krypton\OrderService;

use App\Models\Krypton\TerminalSession;

use App\Enums\OrderStatus;
use App\Events\Order\OrderCreated;
use App\Models\DeviceOrder;

class DeviceOrderController extends Controller
{

    protected $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    /**
     * Handle the incoming order request from specific device.
     *
     * @param  StoreDeviceOrderRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function __invoke(StoreDeviceOrderRequest $request)
    {   
        // Check for active terminal session
        $terminalSession = TerminalSession::current()->latest('created_on')->first() ?? false;

        if( !$terminalSession ) {
            return response()->json([
                'message' => 'Terminal session not found'
                ], 404);
        }

        // Proceed to create order
        # Validate Request
        $validated = $request->validated();
        
        $device = $request->user();

        $order = $this->orderService->create($device, $terminalSession, $validated);
        $order->load(['orderedMenus']);

        $deviceOrder = $device->orders()->create([
            'order_id' => $order->id,
            'table_id' => $device->table_id,
            'terminal_session_id' => $order->terminal_session_id,
            'items' => $order->orderedMenus->toJson(),
            'meta' => json_encode([
                'total_amount' => $validated['total_amount'],
                'guest_count' => $validated['guest_count'],
                'note' => $validated['note'],
            ]), 
        ]);

        $deviceOrder->update(['status' => OrderStatus::CONFIRMED]);

        $device->table()->update(['is_locked' => 1]);

        $order->load(['orderCheck', 'orderedMenus']);
        $order->deviceOrder = $deviceOrder->load('device', 'table');

        broadcast(new OrderCreated($deviceOrder));
    
        return $deviceOrder;

    }
}
