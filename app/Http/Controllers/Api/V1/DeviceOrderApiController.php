<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\StoreDeviceOrderRequest;
use App\Http\Resources\DeviceOrderResource;
use App\Models\DeviceOrder;
use App\Events\Order\OrderCreated;
use App\Models\Device;
use App\Services\Krypton\OrderService;
use App\Services\BroadcastService;
use Illuminate\Support\Facades\Auth;
use App\Enums\OrderStatus;
// use App\Jobs\PrinterOrderJob;

use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;

/**
 * Handle incoming order requests from devices.
 */
class DeviceOrderApiController extends Controller
{
    /**
     * Handle the incoming order request from a specific device.
     *
     * @param  StoreDeviceOrderRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function __invoke(StoreDeviceOrderRequest $request)
    {   
        // Validate the incoming request
        $validatedData = $request->validated();
        // Initialize errors array
        $errors = [];
        // Get the device from the incoming request
        $device = $request->user();

        if( $device && $device->table_id) {

            $canOrder = $device->orders()->whereIn('status', [OrderStatus::PENDING, OrderStatus::CONFIRMED])->latest()->first();
           
            if(  $canOrder ) {
                 return response()->json([
                    'success' => true,
                    'message' => 'Order already in progress',
                    'order' => new DeviceOrderResource($canOrder)
                ], 201);
            }
                
            $order = app(OrderService::class)->processOrder($device, $validatedData);

            OrderCreated::dispatch($order);
            $this->printKitchen($order->order_id);
            // app(BroadcastService::class)->dispatchBroadcastJob(new OrderCreated($order));
            // PrinterOrderJob::dispatch($order, 'cashier')->onQueue('cashier');
  

            return response()->json([
                'success' => true,
                'order' => new DeviceOrderResource($order),
            ], 201);

           
            $errors[] = 'There is already an order in progress for this device.';  
        }else{
            $errors[] = 'The device is not assigned to a table. Please assign the device to a table and try again.';
        }
        return response()->json([
            'success' => false,
            'message' => 'Order processing failed.',
            'errors' => $errors,
        ], 500);
    
    }

    public function printKitchen($orderId)
    {

        $order = DeviceOrder::where('order_id', $orderId)->first();

        if (!$order) {
            return response()->json(['error' => 'Order not found.'], 404);
        }

        // Prepare data for printing
        // You can customize this part based on your order structure
        $items = $order->items;
        $tableName = $order->table->name ?? 'Unknown Table';
        $date = $order->created_at->toDateString() ?? now()->toDateString();
        $time = $order->created_at->format('h:i A') ?? now()->format('h:i A');
        $guestCount = $order->guest_count ?? 0;
        $orderId = $order->order_id ?? 'Unknown ID';
        $package = $order->items[0]['name'] ?? 'Unknown Package';

        // Specify your printer name here (as configured in Windows)
        $printerName = "WoosooPrinter"; 
        // Create a connector to the Windows printer
        $connector = new WindowsPrintConnector($printerName);
        // Create a printer instance
        $printer = new Printer($connector);
        // Start printing
        $printer->text("\n");
        $printer->text(str_repeat('=', 30) . PHP_EOL);
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text("    DINE IN    \n");

        $printer->setJustification(Printer::JUSTIFY_LEFT);

        $count = strlen($date) + strlen($time) + 4; // 4 spaces for padding
        $printer->text("{$date} ");
        $printer->text(str_repeat(' ', 32 - $count) . $time . PHP_EOL);

        $printer->text(str_repeat('=', 30) . PHP_EOL);
        $printer->text("Package: {$package}" . PHP_EOL);
        $printer->text("Table: {$tableName}" . PHP_EOL);
        $printer->text("Guests: {$guestCount}" . PHP_EOL);
        $printer->text(str_repeat('-', 30) . PHP_EOL);
       
        foreach ($items as $key => $item) {

            if( $key == 0  ) continue;

            $name = $item['name'];
            $quantity = $item['quantity'];
            $printer->text("$quantity {$name}\n");
        }

        $printer->text("\n");
   
        $printer->text("****************************\n");
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text("Order #: {$orderId}\n");
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->text("****************************\n");
        $printer->text("\n\n\n");
        
        try {
            // Cut the receipt (if the printer supports it)
            // Some printers may not support this feature
            $printer->cut();
        } catch (\Exception $e) {
            // Handle printers that do not support cutting
            // Optionally log the error: \Log::warning('Printer cut error: ' . $e->getMessage());
        }

        $printer->close();
      
        return response()->json(['data' => $order ]);

       
    }
}

