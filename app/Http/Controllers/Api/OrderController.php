<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    // GET /api/orders : lấy tất cả đơn hàng kèm theo items, trạng thái, thanh toán
    public function index()
    {
        $orders = Order::with(['items', 'status', 'paymentStatus'])->get();

        return response()->json($orders);
    }
    public function show($id)
{
    $order = Order::with(['items', 'status', 'paymentStatus'])->findOrFail($id);
    return response()->json($order);
}

    // POST /api/orders : tạo đơn hàng mới
    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'order_status_id' => 'required|exists:order_statuses,id',
            'payment_status_id' => 'required|exists:payment_statuses,id',
            'items' => 'required|array|min:1',
            'items.*.variant_id' => 'required|exists:product_variants,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
        ]);

        $total = collect($request->items)->reduce(function ($carry, $item) {
            return $carry + ($item['quantity'] * $item['price']);
        }, 0);

        $order = DB::transaction(function () use ($request, $total) {
            $order = Order::create([
                'user_id' => $request->user_id,
                'order_status_id' => $request->order_status_id,
                'payment_status_id' => $request->payment_status_id,
                'total' => $total,
            ]);

            foreach ($request->items as $item) {
                $order->items()->create([
                    'variant_id' => $item['variant_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                ]);
            }

            return $order;
        });

        return response()->json([
            'message' => 'Đơn hàng đã được tạo thành công.',
            'order' => $order->load('items')
        ], 201);
    }
    public function update(Request $request, $id)
{
    $order = Order::findOrFail($id);

    $request->validate([
        'user_id' => 'sometimes|exists:users,id',
        'order_status_id' => 'sometimes|exists:order_statuses,id',
        'payment_status_id' => 'sometimes|exists:payment_statuses,id',
        'items' => 'sometimes|array|min:1',
        'items.*.variant_id' => 'required_with:items|exists:product_variants,id',
        'items.*.quantity' => 'required_with:items|integer|min:1',
        'items.*.price' => 'required_with:items|numeric|min:0',
    ]);

    // Nếu có cập nhật các trường này thì cập nhật:
    if ($request->has('user_id')) {
        $order->user_id = $request->user_id;
    }
    if ($request->has('order_status_id')) {
        $order->order_status_id = $request->order_status_id;
    }
    if ($request->has('payment_status_id')) {
        $order->payment_status_id = $request->payment_status_id;
    }

    // Cập nhật items nếu có
    if ($request->has('items')) {
        // Xóa hết items cũ
        $order->items()->delete();

        // Thêm items mới
        $total = 0;
        foreach ($request->items as $item) {
            $order->items()->create([
                'variant_id' => $item['variant_id'],
                'quantity' => $item['quantity'],
                'price' => $item['price'],
            ]);
            $total += $item['quantity'] * $item['price'];
        }

        $order->total = $total;
    }

    $order->save();

    return response()->json($order->load('items'));
}

    // DELETE /api/orders/{id} : xóa đơn hàng
    public function destroy($id)
    {
        try {
            $order = Order::findOrFail($id);
            $order->delete();

            return response()->json([
                'message' => 'Đơn hàng đã được xóa thành công.'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Xóa đơn hàng thất bại: ' . $e->getMessage()
            ], 500);
        }
    }
}
