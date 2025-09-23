<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class ServiceMonitorController extends Controller
{
     public function status()
    {
        return response()->json([
            'reverb' => $this->isProcessRunning('reverb'),
            'deviceCodes' => 'Completed', // This could be a DB flag or config
            'paymentTrigger' => 'Completed', // Could also be dynamic
            'scheduler' => $this->isProcessRunning('schedule:work'),
        ]);
    }

    public function run(Request $request)
    {
        $service = $request->service;
        $commands = [
            'reverb' => 'app:start-reverb',
            // 'deviceCodes' => 'devices:generate-codes',
            // 'paymentTrigger' => 'pos:setup-payment-trigger',
            'scheduler' => 'schedule:work',
        ];

        if (!isset($commands[$service])) {
            return response()->json(['error' => 'Unknown service'], 400);
        }

        Artisan::call($commands[$service]);
        return response()->json(['success' => true]);
    }

    private function isProcessRunning($keyword)
    {
        $output = shell_exec("ps aux | grep '{$keyword}' | grep -v grep");
        return $output;
        // return $output ? 'running' : 'stopped';
    }
}
