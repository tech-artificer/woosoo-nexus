<?php

namespace App\Testing\Fakes\Krypton;

use Illuminate\Support\Collection;
use App\Repositories\Krypton\TableRepository as RealTableRepository;

class FakeTableRepository extends RealTableRepository
{
    /**
     * Return an empty collection for active table orders in tests.
     */
    public function getActiveTableOrders(): Collection
    {
        return collect([]);
    }

    public function getActiveTableOrdersByTableGroup($tableGroupId): Collection
    {
        return collect([]);
    }

    public static function getActiveTableOrderByTable($tableId)
    {
        return null;
    }
}
