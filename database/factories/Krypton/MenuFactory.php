<?php

namespace Database\Factories\Krypton;

use App\Models\Krypton\Menu;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Menu>
 */
class MenuFactory extends Factory
{
    protected $model = Menu::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'receipt_name' => $this->faker->words(2, true),
            'price' => $this->faker->randomFloat(2, 10, 500),
            'menu_group_id' => null,
        ];
    }
}