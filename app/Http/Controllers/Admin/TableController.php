<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Krypton\Table;


class TableController extends Controller
{
    // public function index() 
    // {
    //     $tables = Table::with(['tableOrders', 'device'])->get();

    //     return Inertia::render('Tables', [
    //         'title' => 'Tables',
    //         'description' => 'List of Tables',
    //         'tables' => $tables,
    //     ]);
    // }
}
