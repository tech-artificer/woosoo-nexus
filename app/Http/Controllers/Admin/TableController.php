<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;


class TableController extends Controller
{
     public function index() 
    {
        return Inertia::render('Tables', [
            'title' => 'Tables',
            'description' => 'List of Tables',
        ]);
    }
}
