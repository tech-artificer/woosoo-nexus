<?php

namespace App\Http\Controllers\Api\V1\Krypton;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\Krypton\OrderRepository;
use App\Http\Resources\OrderResource;

class OrderController extends Controller
{
  
    /**
     * Return a list of all orders with the corresponding device data.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $orders = OrderRepository::getAllOrdersWithDeviceData();
        return OrderResource::collection($orders);
    }
    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
