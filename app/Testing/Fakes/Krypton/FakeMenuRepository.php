<?php

namespace App\Testing\Fakes\Krypton;

use App\Models\Krypton\Menu;
use App\Repositories\Krypton\MenuRepository as RealMenuRepository;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class FakeMenuRepository extends RealMenuRepository
{
    public function getMenus(): EloquentCollection
    {
        return Menu::hydrate([]);
    }

    public static function getMenuById(int $id): ?Menu
    {
        return null;
    }
}
