<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TableService;

class TableServiceApiController extends Controller
{
     /**
     * Returns a list of all services
     * 
     * @example 
     * @return array
     * 
     */
      public function index(Request $request)
      {
            $services = TableService::all();
            return response()->json($services);
      }
}
