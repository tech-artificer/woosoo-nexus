<?php

namespace App\Http\Controllers\Api\v1\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SalesApiController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {

        // $request->validate([
        //     'month' => ['nullable', 'date_format:m'],
        //     'year' => ['nullable', 'date_format:Y'],
        //     'startDate' => ['nullable', 'date'],
        //     'endDate' => ['nullable', 'date'],
        // ]);

        return response()->json([
            'request' => $request->all(),
        ]);
    }
}
