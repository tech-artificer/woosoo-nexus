<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Device;
use App\Models\Branch;
use Illuminate\Support\Str;

class DeviceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $branch = Branch::first();
        
        if (!$branch) {
            $this->command->error('No branch found. Please run DatabaseSeeder first to create a branch.');
            return;
        }

        // Create 15 tablet devices for ordering
        $this->command->info('Creating 15 tablet devices...');
        
        for ($i = 1; $i <= 15; $i++) {
            $deviceName = sprintf('Tablet-%02d', $i);
            $ipAddress = sprintf('192.168.100.%d', 100 + $i); // IPs: 192.168.100.101 - 192.168.100.115
            
            Device::firstOrCreate(
                ['name' => $deviceName],
                [
                    'device_uuid' => (string) Str::uuid(),
                    'branch_id' => $branch->id,
                    'ip_address' => $ipAddress,
                    'port' => '3001', // PWA port for legacy stack
                    'is_active' => true,
                    'app_version' => '1.0.0',
                    'table_id' => ($i <= 10) ? (string) $i : null, // Assign first 10 to tables 1-10
                    'last_seen_at' => now(),
                ]
            );
            
            $this->command->info("  ✓ Created: {$deviceName} ({$ipAddress})");
        }

        // Create 1 relay/print bridge device
        $this->command->info('Creating print bridge relay device...');
        
        Device::firstOrCreate(
            ['name' => 'Print-Bridge-01'],
            [
                'device_uuid' => (string) Str::uuid(),
                'branch_id' => $branch->id,
                'ip_address' => '192.168.100.200', // Dedicated IP for print bridge
                'port' => '8080', // Different port for relay service
                'is_active' => true,
                'app_version' => '1.0.0',
                'table_id' => null, // Print bridge is not assigned to any table
                'last_seen_at' => now(),
            ]
        );
        
        $this->command->info('  ✓ Created: Print-Bridge-01 (192.168.100.200)');
        
        $this->command->info('Device seeding completed!');
        $this->command->info('Total devices: 16 (15 tablets + 1 print bridge)');
    }
}
