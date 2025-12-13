<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\DeviceOrder;
use Illuminate\Validation\ValidationException; // Import for validation errors
use App\Http\Resources\DeviceOrderResource;
// use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;

use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\Printer;
use Illuminate\Support\Facades\Http;
use Exception;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\PrintConnectors\RawbtPrintConnector;
use Mike42\Escpos\CapabilityProfile;

class PrintController extends Controller
{
    public function print()
    {
        // Implement your printing logic here
        return response()->json(['message' => 'Print function called']);
    }

    public function printKitchen(Request $request)
    {

        $validated = $request->validate([
            'order_id' => ['required', 'integer'],
        ]);

        $order = DeviceOrder::where('order_id', $validated['order_id'])->first();

        if (!$order) {
            return response()->json(['error' => 'Order not found.'], 404);
        }

        // Prepare data for printing
        // You can customize this part based on your order structure
        $items = $order->items()->with('menu')->orderBy('index')->get();
        $tableName = $order->table->name ?? 'Unknown Table';
        $date = $order->created_at->toDateString() ?? now()->toDateString();
        $time = $order->created_at->format('h:i A') ?? now()->format('h:i A');
        $guestCount = $order->guest_count ?? 0;
        $orderId = $order->order_id ?? 'Unknown ID';
        $packageItem = $items->first();
        $package = $packageItem?->menu?->receipt_name ?? $packageItem?->menu?->name ?? 'Unknown Package';

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
       
        foreach ($items->slice(1) as $item) {
            $name = $item->menu?->receipt_name ?? $item->menu?->name ?? null;
            $quantity = $item->quantity;
            if (! $name) continue;
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

    public function printOrder($orderId)
    {   
        // $serverUrl = 'http://192.168.100.133:9100';
        // $response = Http::timeout(5)->get($serverUrl);
            
        //     return [
        //         'success' => true,
        //         'status' => $response->status(),
        //         'body' => $response->body()
        //     ];
        // $response = Http::timeout(5)->get('http://192.168.100.133:9100' . '/status');
        //  return $response->successful();

        $profile = CapabilityProfile::load("POS-5890");

    /* Fill in your own connector here */
         $connector = new RawbtPrintConnector();

        $order = DeviceOrder::where('order_id', $orderId)->first();

        if (!$order) {
            return response()->json(['error' => 'Order not found.'], 404);
        }

        // Prepare data for printing
        // You can customize this part based on your order structure
        $items = $order->items()->with('menu')->orderBy('index')->get();
        $tableName = $order->table->name ?? 'Unknown Table';
        $date = $order->created_at->toDateString() ?? now()->toDateString();
        $time = $order->created_at->format('h:i A') ?? now()->format('h:i A');
        $guestCount = $order->guest_count ?? 0;
        $orderId = $order->order_id ?? 'Unknown ID';
        $packageItem = $items->first();
        $package = $packageItem?->menu?->receipt_name ?? $packageItem?->menu?->name ?? 'Unknown Package';

        // Specify your printer name here (as configured in Windows)
        $printerName = "lan_printer"; 
        // Create a connector to the Windows printer
        // $connector =  new Printer($connector, $profile);

        // $connector = new NetworkPrintConnector('192.168.100.133', '9100');
        // Create a printer instance
        $printer = new Printer($connector, $profile);
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
       
        foreach ($items->slice(1) as $item) {
            $name = $item->menu?->receipt_name ?? $item->menu?->name ?? null;
            $quantity = $item->quantity;
            if (! $name) continue;
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

        // return response()->json(['message' => "Print order function called for order ID: $orderId"]);
    }

    public function printReceipt($receiptId)
    {
        // Implement your receipt printing logic here
        return response()->json(['message' => "Print receipt function called for receipt ID: $receiptId"]);
    }
}
