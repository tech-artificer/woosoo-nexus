<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TerminalSessionController extends Controller
{
    public function index()
    {
        return Inertia::render('TerminalSession', [
            'title' => 'Terminal Session',
            'description' => 'Terminal Session',
        ]);
    }
}
