<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\Printer;

class PosPrintController extends Controller
{
    public function printNetworkReceipt(Sale $sale) // Pass your sale data or other relevant info
    {
        // --- Configuration for your network printer ---
        $PRINTER_IP = "192.168.1.100"; // IMPORTANT: Replace with your actual network printer's IP address
        $PRINTER_PORT = 9100; // Standard port for most thermal network printers

        try {
            $connector = new NetworkPrintConnector(PRINTER_IP, PRINTER_PORT);
            $printer = new Printer($connector);

            // --- Generate your receipt content (same as local example) ---
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->text("--- YOUR STORE ---\n");
            $printer->text("Network Receipt # " . $sale->id . "\n");
            $printer->feed(1);
            $printer->setJustification(Printer::JUSTIFY_LEFT);

            foreach ($sale->items as $item) {
                $printer->text(sprintf("%-25s %7.2f\n", $item['name'], $item['price']));
            }

            $printer->text("--------------------------------\n");
            $printer->text(sprintf("Total: %25.2f\n", $sale->total));
            $printer->feed(2);
            $printer->text("Thank you for your business!\n");
            $printer->cut();

            $printer->close();

            return back()->with('success', 'Receipt printed successfully to network printer!');

        } catch (\Exception $e) {
            \Log::error('Network print error: ' . $e->getMessage());
            return back()->withErrors(['print' => 'Failed to print receipt to network printer: ' . $e->getMessage()]);
        }
    }
}
