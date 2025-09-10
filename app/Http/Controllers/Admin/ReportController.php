<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(string $type, ReportQueryRequest $request, ReportRegistry $registry)
    {
        $service = $registry->resolve($type);
        [$rows, $meta] = $service->list($request);

        return response()->json([
        'data' => $rows,
        'meta' => $meta,
        ]);
    }
}
