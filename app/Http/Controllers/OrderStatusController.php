<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class OrderStatusController extends Controller
{
    public function show($orderId)
    {
        $order = Order::with(['items.product', 'user'])->find($orderId);
        
        if (!$order) {
            return view('orders.not-found');
        }
        
        return redirect(env('FRONTEND_URL', 'http://localhost:3000') . '/orders/' . $orderId);
    }
}