<?php

namespace App\Testing\Fakes\Krypton;

use App\Repositories\Krypton\TerminalRepository;
use Illuminate\Support\Collection;

class FakeTerminalRepository extends TerminalRepository
{
    public function findById(int $id)
    {
        return null;
    }

    public function getByBranch(int $branchId): Collection
    {
        return collect([]);
    }
}
