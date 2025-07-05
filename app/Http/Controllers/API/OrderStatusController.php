<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;

class OrderStatusController extends Controller
{
    public function show($orderId)
    {
        $order = Order::with(['items.product', 'user'])->find($orderId);
        
        if (!$order) {
            return response()->json(['error' => 'Đơn hàng không tồn tại'], 404);
        }
        
        return response()->json([
            'success' => true,
            'order' => $order
        ]);
    }
}