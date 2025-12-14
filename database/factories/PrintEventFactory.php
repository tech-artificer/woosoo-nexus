<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\PrintEvent;

class PrintEventFactory extends Factory
{
    protected $model = PrintEvent::class;

    public function definition()
    {
        return [
            'device_order_id' => null,
            'printer_id' => null,
            'event_type' => $this->faker->randomElement(['INITIAL', 'REFILL']),
            'meta' => ['note' => $this->faker->sentence()],
            'is_acknowledged' => false,
            'attempts' => 0,
        ];
    }
}
