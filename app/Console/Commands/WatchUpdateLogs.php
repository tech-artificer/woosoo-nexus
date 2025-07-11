<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\Krypton\Order;
use App\Models\OrderUpdateLog;
use App\Models\DeviceOrder;
use Carbon\Carbon;
use App\Events\Order\OrderCompleted;
use App\Enums\OrderStatus;

class WatchUpdateLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:watch-update-logs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
      
    }
}
