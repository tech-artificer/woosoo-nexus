<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    /**
     * Dashboard for admin
     *
     * @param \Illuminate\Http\Request $request
     * @return \Inertia\Response
     */
    public function index()
    {   

        return Inertia::render('Dashboard', [
            'title' => 'Dashboard',
            'description' => 'Analytics',
        ]);
    }
}
