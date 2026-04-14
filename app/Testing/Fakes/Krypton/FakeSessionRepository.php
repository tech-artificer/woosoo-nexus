<?php

namespace App\Testing\Fakes\Krypton;

use App\Repositories\Krypton\SessionRepository as RealSessionRepository;

class FakeSessionRepository extends RealSessionRepository
{
    public static function getLatestSession(): mixed
    {
        return null;
    }
}
