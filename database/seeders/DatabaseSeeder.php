<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use App\Models\User;
use App\Models\Branch;
use App\Models\TableService;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Branch::firstOrCreate([
            'name' => 'SM Butuan',
            'location' => 'Butuan City, Agusan del Norte, Philippines'
        ]);

        $services = ['Cleaning', 'Billing', 'Call Support', 'Service Water'];

        foreach ($services as $name) {
            TableService::firstOrCreate(['name' => $name]);
        }

        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'admin',
                'password' => bcrypt('password'),
                'is_admin' => true,
            ]
        );

    }
}
