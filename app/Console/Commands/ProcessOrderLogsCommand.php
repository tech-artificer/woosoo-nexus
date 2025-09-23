<?php

namespace App\Console\Commands;

use Illuminate\Console\Scheduling\Attributes\AsScheduled;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Enums\OrderStatus;
use App\Jobs\ProcessOrderLogs;


class ProcessOrderLogsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:process-logs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch the job to process order update logs.';

    /**
     * Execute the console command.
     */
    public function handle() : void
    {
        try {
            $this->info(' ProcessOrderLogs job: starting.. .');
            dispatch(new ProcessOrderLogs());
            Log::info('🕵️ Dispatched ProcessOrderLogs job at: ' . now());
        } catch (\Throwable $e) {
            Log::error('❌ orders:process-logs failed: ' . $e->getMessage());
            $this->error('Command failed: ' . $e->getMessage());
        }
    }
}
