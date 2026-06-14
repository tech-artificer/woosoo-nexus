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
        return [
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->optional()->sentence(),
            'base_price' => $this->faker->randomFloat(2, 99, 999),
            'min_meat' => 1,
            'max_meat' => 3,
            'min_side' => 0,
            'max_side' => 5,
            'min_dessert' => 0,
            'max_dessert' => 2,
            'min_beverage' => 0,
            'max_beverage' => 2,
            'is_active' => true,
            'sort_order' => $this->faker->numberBetween(0, 10),
        ];
    }
}
