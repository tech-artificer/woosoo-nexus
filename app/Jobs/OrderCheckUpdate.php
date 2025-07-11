<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Krypton\OrderCheck;
use App\Models\Krypton\Order;
use App\Models\UpdateLogs;
use Carbon\Carbon;

class OrderCheckUpdate implements ShouldQueue
{
    use Dispatchable, Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // $terminalSession = TerminalSession::current()->latest('created_on')->first() ?? false;
        // $logs = UpdateLogs::whereDate('updated_at', Carbon::today())->get();
    }
}
