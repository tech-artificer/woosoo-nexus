<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\MenuResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Krypton\Menu;

class MenuController extends Controller
{
    public function index() 
    {
        $menus = Menu::orderBy('name')->get();

        return Inertia::render('Menus', [
            'title' => 'Menus',
            'description' => 'List of Menus',
            'menus' => MenuResource::collection($menus),
        ]);
    }

    public function edit(Menu $menu) 
    {
        return Inertia::render('menus/EditMenu', [
            'title' => 'Menu',
            'description' => 'Menu',
            'menu' => new MenuResource($menu),
        ]);
    }
}
