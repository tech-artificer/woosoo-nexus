<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Enums\OrderStatus;
use App\Enums\ItemStatus;

class StatusParityTest extends TestCase
{
    public function test_order_status_values_match_expected()
    {
        $expected = [
            'pending','confirmed','in_progress','ready','served','completed','cancelled','voided','archived'
        ];
        $actual = array_map(fn($e) => $e->value, OrderStatus::cases());
        sort($expected);
        sort($actual);
        $this->assertSame($expected, $actual);
    }

    public function test_item_status_values_match_expected()
    {
        $expected = [
            'pending','preparing','ready','served','cancelled','voided','returned'
        ];
        $actual = array_map(fn($e) => $e->value, ItemStatus::cases());
        sort($expected);
        sort($actual);
        $this->assertSame($expected, $actual);
    }
}
