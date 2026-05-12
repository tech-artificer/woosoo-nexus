<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\PrinterApiController;

/**
 * PrintEvent API routes
 *
 * NOTE: These endpoints are gated behind the 'print_events.enabled' middleware.
 * When NEXUS_PRINT_EVENTS_ENABLED=false (MVP default), these endpoints return
 * 503 Service Unavailable. woosoo-print-bridge is the active print execution
 * path for the current MVP.
 *
 * Enable these routes only for future printer expansion work by setting
 * NEXUS_PRINT_EVENTS_ENABLED=true in your .env file.
 */
Route::middleware(['print_events.enabled'])->group(function () {
    // Printer API endpoints (device-authenticated for branch isolation)
    // These endpoints are for printer relay devices that authenticate via device tokens
    Route::get('/printer/unprinted-events', [PrinterApiController::class, 'getUnprintedEvents']);
    Route::get('/printer/unprinted-orders', [PrinterApiController::class, 'getUnprintedOrders']);
    Route::post('/printer/print-events/{id}/reserve', [PrinterApiController::class, 'reservePrintEvent']);
    Route::post('/printer/print-events/{id}/ack', [PrinterApiController::class, 'ackPrintEvent']);
    Route::post('/printer/print-events/{id}/failed', [PrinterApiController::class, 'failPrintEvent']);
    Route::post('/printer/heartbeat', [PrinterApiController::class, 'heartbeat']);

    // Alias route for print-events endpoint (legacy clients)
    Route::get('/print-events/unprinted', [PrinterApiController::class, 'getUnprintedEvents']);

    // Backwards-compatibility: legacy clients use plural '/orders' paths
    Route::post('/orders/{orderId}/printed', [PrinterApiController::class, 'markPrinted']);
    Route::post('/orders/printed/bulk', [PrinterApiController::class, 'markPrintedBulk']);
});
