<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ReverbStart extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:start-reverb';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start the Reverb service';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Reverb service...');

        $phpBinary = PHP_BINARY; // Gets the PHP executable path dynamically
        $command = "{$phpBinary} artisan reverb:start > /dev/null 2>&1 &";

        shell_exec($command);

        $this->info('Reverb service started successfully.');
    }
}
