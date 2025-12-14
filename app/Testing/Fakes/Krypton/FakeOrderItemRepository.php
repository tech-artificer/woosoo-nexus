<?php

namespace App\Testing\Fakes\Krypton;

use App\Repositories\Krypton\OrderItemRepository;
use Illuminate\Support\Collection;

class FakeOrderItemRepository extends OrderItemRepository
{
    public function findByOrderId(int $orderId): Collection
    {
        return collect([]);
    }
}
