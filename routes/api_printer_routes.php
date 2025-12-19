<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\PrinterApiController;

// Printer API endpoints (device-authenticated for branch isolation)
// These endpoints are for printer relay devices that authenticate via device tokens
Route::get('/printer/unprinted-events', [PrinterApiController::class, 'getUnprintedEvents']);
Route::post('/printer/print-events/{id}/ack', [PrinterApiController::class, 'ackPrintEvent']);
Route::post('/printer/print-events/{id}/failed', [PrinterApiController::class, 'failPrintEvent']);
Route::post('/printer/heartbeat', [PrinterApiController::class, 'heartbeat']);

// Backwards-compatibility: legacy clients use plural '/orders' paths
Route::post('/orders/{orderId}/printed', [PrinterApiController::class, 'markPrinted']);
Route::post('/orders/printed/bulk', [PrinterApiController::class, 'markPrintedBulk']);
