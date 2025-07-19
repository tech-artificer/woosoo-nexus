<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Enums\OrderStatus;
use Illuminate\Validation\Rules\Enum;
use App\Models\Krypton\TerminalSession;
use App\Events\Order\OrderStatusUpdated;
use App\Services\Krypton\OrderService;
use App\Models\DeviceOrder;


class DeviceOrderUpdateApiController extends Controller
{

    protected $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        // $request->validate([
        //     /**
        //      * Order ID
        //      * 
        //      * @var integer
        //      * @required
        //      * 
        //     */
        //     'order_id' => ['required', 'integer'],
        //     /**
        //      * Transaction Number
        //      * 
        //      * @var integer
        //      * @required
        //      * 
        //     */
        //     'transaction_no' => ['required', 'integer'],
        //     /**
        //      * Order Status 
        //      *  
        //      * @var string
        //      * @required
        //      * @see \App\Enums\OrderStatus
        //      * 
        //     */
        //     'status' => ['required', new Enum(OrderStatus::class)],
        // ]);

        // // Check for active terminal session
        // $terminalSession = TerminalSession::current()->latest('created_on')->first() ?? false;

        // $user = $request->user();
        
        // // Fetch Device Order
        // $deviceOrder = $user->orders()->where([
        //     'order_id' => $request->order_id, 
        //     'terminal_session_id' => $terminalSession->id
        // ])->first();
        
        // // Update Order
        // $result = $this->orderService->update($deviceOrder, $request->validated());
        
        
        // if(  !$result ) {
        //     return response()->json([
        //         'message' => 'Order not found'
        //         ], 404);
        // }
        
        // return response()->json([
        //    'success' => true,
        //    'message' => 'Order updated successfully',

        // ]);
        // $order = $deviceOrder->refresh();
        // $order->update(['status' => OrderStatus::from($request->status)]);

        // if( $order->status == OrderStatus::COMPLETED ) {
            
        // }

        // broadcast(new OrderStatusUpdated($order));

        // if( !$order ) {
        //     return response()->json([
        //         'message' => 'Order not found'
        //         ], 404);
        // }

        return $order;
    }
}
