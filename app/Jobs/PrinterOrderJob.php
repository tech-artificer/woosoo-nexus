<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\DeviceOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;

class PrinterOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $order;
    public $printerName;

    public $tries = 3;     // Retry up to 3 times if fails
    public $backoff = 5;   // Retry after 5 seconds

    /**
     * Create a new job instance.
     */
    public function __construct(DeviceOrder $order, $printerName)
    {
        $this->order = $order;
        $this->printerName = $printerName;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        // $order = Order::with('items')->find($this->orderId);
        // if (!$order) {
        //     return;
        // }

        $config = config("printers.{$this->printerName}");
        if (!$config) {
            \Log::error("Printer config not found: {$this->printerName}");
            return;
        }

        try {
            // Pick connector type
            switch ($config['type']) {
                case 'network':
                    $connector = new NetworkPrintConnector($config['ip'], $config['port']);
                    break;

                case 'windows-com':
                    $connector = new FilePrintConnector($config['com']);
                    break;

                default:
                    throw new \Exception("Unsupported printer type: {$config['type']}");
            }

            $printer = new Printer($connector);

            // ---- Example ticket format ----
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->text("=== ORDER #{$this->order->id} ===\n");
            $printer->setJustification(Printer::JUSTIFY_LEFT);

            foreach ($this->order->items as $item) {
                $printer->text("{$item->qty}x {$item->name}\n");
            }

            $printer->feed(2);
            $printer->cut();
            $printer->close();

            \Log::info("Order {$this->order->id} printed on {$this->printerName}");
        } catch (\Exception $e) {
            \Log::error("Failed to print order {$this->order} on {$this->printerName}: " . $e->getMessage());
            $this->release(10); // retry in 10s
        }
    }
}
