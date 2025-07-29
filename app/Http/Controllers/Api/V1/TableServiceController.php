<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Krypton\Table;
use App\Models\Krypton\ServiceType;
use App\Events\TableService;

class TableServiceController extends Controller
{
    public function store(Request $request, Table $table)
    {
        $request->validate([
            'id' => ['required'],
        ]);

        $serviceType = ServiceType::find($request->id);

        if(!$serviceType) {
            return response()->json([
                'success' => false,
                'message' => 'Service type not found'
            ], 404);
        }

        broadcast(new TableService($table, $serviceType))->toOthers();
    }
}
