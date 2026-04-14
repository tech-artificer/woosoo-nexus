<?php

namespace App\Services;

use App\Models\Branch;
use RuntimeException;

class LocalBranchResolver
{
    public function resolve(): ?Branch
    {
        if (Branch::query()->count() === 1) {
            return Branch::query()->first();
        }

        return null;
    }

    public function requireId(): int
    {
        $branch = $this->resolve();

        if ($branch !== null) {
            return (int) $branch->getKey();
        }

        throw new RuntimeException(
            'Local install must have exactly one branch record in the branches table.'
        );
    }
}