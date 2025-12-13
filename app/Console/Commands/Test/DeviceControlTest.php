<?php

namespace App\Console\Commands\Test;

use Illuminate\Console\Command;
use App\Services\BroadcastService;
use App\Events\AppControlEvent;

class DeviceControlTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:device-control {deviceId} {action}';
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
        $deviceId = 1;
         $deviceId = $this->argument('deviceId');
         $action = $this->argument('action');
         app(BroadcastService::class)->dispatchBroadcastJob(new AppControlEvent($deviceId, $action, [
            'success' => true,
            'message' => 'Device Control'
        ]));

         $this->info('Device Notification event broadcasted successfully!');
    }
}
