<?php

namespace App\Services;

use App\Models\Order;
use Exception;

class ThermalPrinterService
{
    private string $printerIP;
    private int $printerPort;
    
    public function __construct()
    {
        $this->printerIP = config('printing.thermal_printer_ip', '192.168.1.100');
        $this->printerPort = config('printing.thermal_printer_port', 9100);
    }
    
    public function printOrder(Order $order): bool
    {
        try {
            $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            
            if (!$socket) {
                throw new Exception('Cannot create socket');
            }
            
            $connection = socket_connect($socket, $this->printerIP, $this->printerPort);
            
            if (!$connection) {
                throw new Exception('Cannot connect to printer');
            }
            
            $escposData = $this->generateESCPOSData($order);
            socket_write($socket, $escposData);
            socket_close($socket);
            
            \Log::info("Order #{$order->id} sent to thermal printer successfully");
            return true;
            
        } catch (Exception $e) {
            \Log::error("Thermal printer error: " . $e->getMessage());
            return false;
        }
    }
    
    private function generateESCPOSData(Order $order): string
    {
        $esc = "\x1B";
        $gs = "\x1D";
        
        $data = '';
        
        // Initialize printer
        $data .= $esc . "@";
        
        // Set character size and alignment
        $data .= $esc . "!" . "\x00"; // Normal size
        $data .= $esc . "a" . "\x01"; // Center align
        
        // Business name (larger font)
        $data .= $esc . "!" . "\x30"; // Double height and width
        $data .= config('app.business_name', 'RESTAURANT NAME') . "\n";
        
        // Reset font size and left align
        $data .= $esc . "!" . "\x00";
        $data .= $esc . "a" . "\x00";
        
        // Order details
        $data .= str_repeat("-", 32) . "\n";
        $data .= "Order #: " . $order->id . "\n";
        $data .= "Date: " . $order->created_at->format('Y-m-d H:i:s') . "\n";
        $data .= "Customer: " . ($order->customer_name ?? 'Walk-in') . "\n";
        $data .= str_repeat("-", 32) . "\n";
        
        // Items
        foreach ($order->items as $item) {
            $name = substr($item->name, 0, 20);
            $qty = $item->pivot->quantity;
            $price = number_format($item->pivot->price * $qty, 2);
            
            $data .= sprintf("%-20s %2dx %8s\n", $name, $qty, '$' . $price);
            
            // Add modifiers/notes if any
            if (!empty($item->pivot->notes)) {
                $data .= "  Note: " . substr($item->pivot->notes, 0, 28) . "\n";
            }
        }
        
        $data .= str_repeat("-", 32) . "\n";
        
        // Totals
        $subtotal = $order->subtotal;
        $tax = $order->tax_amount;
        $total = $order->total_amount;
        
        $data .= sprintf("%-20s %11s\n", "Subtotal:", '$' . number_format($subtotal, 2));
        $data .= sprintf("%-20s %11s\n", "Tax:", '$' . number_format($tax, 2));
        $data .= $esc . "!" . "\x20"; // Double width for total
        $data .= sprintf("%-20s %11s\n", "TOTAL:", '$' . number_format($total, 2));
        $data .= $esc . "!" . "\x00"; // Reset font
        
        $data .= str_repeat("-", 32) . "\n";
        
        // Footer
        $data .= $esc . "a" . "\x01"; // Center align
        $data .= "Thank you for your order!\n";
        $data .= config('app.business_phone', '') . "\n";
        
        // Feed and cut
        $data .= "\n\n\n";
        $data .= $gs . "V" . "\x00"; // Full cut
        
        return $data;
    }
}