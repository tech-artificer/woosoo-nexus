<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MenuController extends Controller
{
    public function index() 
    {
        return Inertia::render('Menus', [
            'title' => 'Menus',
            'description' => 'List of Menus',
        ]);
    }
}
