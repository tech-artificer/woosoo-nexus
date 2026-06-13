<?php

namespace Database\Factories;

use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DeviceFactory extends Factory
{
    protected $model = Device::class;

    public function definition()
    {
        return [
            'name' => $this->faker->unique()->word().'-tablet',
            'branch_id' => 1, // Default test branch
            'table_id' => null,
            'is_active' => true,
            'status' => 'online',
            'app_version' => '1.0.0',
            // unique() state resets per factory instance; safe at test scale
            // (IPv4 space is ~4B; Faker retries up to 10,000 times before throwing OverflowException).
            'ip_address' => $this->faker->unique()->ipv4(),
            'last_ip_address' => null,
            'last_seen_at' => now(),
            // Set explicitly so Device::factory() satisfies the NOT NULL device_uuid
            // even when model events are faked (Event::fake suppresses Device::booted()'s
            // `creating` hook, which would otherwise generate it).
            'device_uuid' => (string) Str::uuid(),
        ];
    }

    /**
     * Indicate that the device is inactive.
     */
    public function inactive()
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'status' => 'offline',
        ]);
    }

    /**
     * Indicate that the device is assigned to a table.
     */
    public function withTable(int $tableId)
    {
        return $this->state(fn (array $attributes) => [
            'table_id' => $tableId,
        ]);
    }

    /**
     * Indicate that the device belongs to a specific branch.
     */
    public function forBranch(int $branchId)
    {
        return $this->state(fn (array $attributes) => [
            'branch_id' => $branchId,
        ]);
    }
}
