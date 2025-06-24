<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DeviceRegistrationCode;

class GenerateDeviceCodes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'devices:generate-codes {count=20}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate one-time registration codes for devices';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $count = (int) $this->argument('count');
        $this->info("Generating {$count} registration codes...");

        $generated = 0;

        while ($generated < $count) {
            $code = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);

            // Ensure uniqueness
            if (!DeviceRegistrationCode::where('code', $code)->exists()) {
                DeviceRegistrationCode::create(['code' => $code]);
                $this->line("✔ Code generated: {$code}");
                $generated++;
            }
        }

        $this->info("✅ {$generated} codes generated successfully.");
    }
}
