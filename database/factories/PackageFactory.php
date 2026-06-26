<?php

namespace Database\Factories;

use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
{
    public function definition(): array
    {
        static $menuId = 46;

        return [
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->optional()->sentence(),
            'krypton_menu_id' => $menuId++,
            'base_price' => $this->faker->randomFloat(2, 99, 999),
            'min_meat' => 1,
            'max_meat' => 5,
            'is_active' => true,
            'is_most_popular' => false,
            'sort_order' => $this->faker->numberBetween(0, 10),
        ];
    }
}
