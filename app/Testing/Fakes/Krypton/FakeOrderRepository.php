<?php

namespace App\Testing\Fakes\Krypton;

use Illuminate\Support\Collection;
use App\Repositories\Krypton\OrderRepository as RealOrderRepository;

class FakeOrderRepository extends RealOrderRepository
{
    public static function getAllOrdersWithDeviceData($currentSessions): Collection
    {
        return collect([]);
    }

    public static function getOpenOrdersWithTables(): Collection
    {
        return collect([]);
    }

    public static function getOpenOrdersByTable(int $tableId): Collection
    {
        return collect([]);
    }

    public function getOpenOrdersForSession($sessionId): Collection
    {
        return collect([]);
    }
}
