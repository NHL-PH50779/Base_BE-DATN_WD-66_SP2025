<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OrderStatus;

class OrderStatusController extends Controller
{
    public function index()
    {
        return response()->json(OrderStatus::all());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50',
        ]);

        $orderStatus = OrderStatus::create($validated);

        return response()->json($orderStatus, 201);
    }

    public function show($id)
    {
        $orderStatus = OrderStatus::findOrFail($id);
        return response()->json($orderStatus);
    }

    public function update(Request $request, $id)
    {
        $orderStatus = OrderStatus::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:50',
        ]);

        $orderStatus->update($validated);

        return response()->json($orderStatus);
    }

    public function destroy($id)
{
    $orderStatus = OrderStatus::findOrFail($id);
    $orderStatus->delete();

    return response()->json([
        'message' => 'Trạng thái đơn hàng đã được xóa thành công.'
    ], 200);
}

}
