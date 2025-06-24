<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\OrderItem;
class OrderController extends Controller
{
    public function checkout(Request $request)
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json(['message' => 'Vui lòng đăng nhập'], 401);
        }
        
        $cart = Cart::with('items')->where('user_id', $user->id)->first();

        if (!$cart || $cart->items->isEmpty()) {
            return response()->json(['message' => 'Giỏ hàng trống'], 400);
        }

        DB::beginTransaction();

try {
    $total = 0;

    foreach ($cart->items as $item) {
        if ($item->product_variant_id) {
            $variant = ProductVariant::findOrFail($item->product_variant_id);
            if ($variant->stock < $item->quantity) {
                return response()->json(['message' => 'Sản phẩm không đủ tồn kho'], 400);
            }
            $variant->stock -= $item->quantity;
            $variant->save();
        }

        $total += $item->price * $item->quantity;
    }

    // ✅ Tạo đơn hàng
    $order = Order::create([
        'user_id' => $user->id,
        'order_status_id' => 1, // chờ xác nhận
        'payment_status_id' => 1, // chưa thanh toán
        'total' => $total,
    ]);

    // ✅ Tạo các mục đơn hàng
    foreach ($cart->items as $item) {
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $item->product_id,
            'product_variant_id' => $item->product_variant_id,
            'quantity' => $item->quantity,
            'price' => $item->price
        ]);
    }

    // ✅ Xoá giỏ hàng
    $cart->items()->delete();
    // Nếu bạn muốn xoá luôn bản ghi giỏ hàng: $cart->delete();

    DB::commit();

    return response()->json([
        'message' => 'Đơn hàng đã được tạo thành công',
        'data' => $order
    ], 201);

} catch (\Exception $e) {
    DB::rollBack();
    return response()->json(['message' => 'Đặt hàng thất bại', 'error' => $e->getMessage()], 500);
}
    }

  public function show($id)
{
    $order = Order::with([
        'items.product',
        'items.productVariant'
    ])->find($id);

    if (!$order) {
        return response()->json([
            'message' => 'Không tìm thấy đơn hàng'
        ], 404);
    }

    // ✅ Kiểm tra quyền: chỉ cho phép xem đơn của chính mình
    if ($order->user_id !== auth()->id()) {
        return response()->json([
            'message' => 'Bạn không có quyền truy cập đơn hàng này'
        ], 403);
    }

    return response()->json([
        'message' => 'Chi tiết đơn hàng',
        'data' => [
            'order' => $order,
            'items' => $order->items,
            'total' => $order->total
        ]
    ]);
}

// ===== ADMIN METHODS =====

// Xem tất cả đơn hàng (Admin)
public function index()
{
    $user = auth('sanctum')->user();
    
    // Debug thông tin user
    \Log::info('Admin Orders - User info:', [
        'user' => $user ? $user->toArray() : null,
        'role' => $user ? $user->role : null,
        'is_admin' => $user && $user->role === 'admin'
    ]);
    
    if (!$user) {
        return response()->json(['message' => 'Vui lòng đăng nhập'], 401);
    }
    
    if ($user->role !== 'admin') {
        return response()->json(['message' => 'Không có quyền admin'], 403);
    }

    $orders = Order::with(['user', 'items.product', 'items.productVariant'])
        ->orderBy('created_at', 'desc')
        ->get();

    return response()->json([
        'message' => 'Danh sách đơn hàng',
        'data' => $orders
    ]);
}

// Cập nhật trạng thái đơn hàng (Admin)
public function updateStatus(Request $request, $id)
{
    $user = auth('sanctum')->user();
    if (!$user || $user->role !== 'admin') {
        return response()->json(['message' => 'Không có quyền truy cập'], 403);
    }

    $order = Order::findOrFail($id);
    
    $validated = $request->validate([
        'order_status_id' => 'required|integer|min:1|max:5',
        'payment_status_id' => 'required|integer|min:1|max:3'
    ]);

    $order->update($validated);

    return response()->json([
        'message' => 'Cập nhật trạng thái thành công',
        'data' => $order->load(['user', 'items.product'])
    ]);
}

// Xem chi tiết đơn hàng (Admin)
public function adminShow($id)
{
    $user = auth('sanctum')->user();
    if (!$user || $user->role !== 'admin') {
        return response()->json(['message' => 'Không có quyền truy cập'], 403);
    }

    $order = Order::with([
        'user',
        'items.product',
        'items.productVariant'
    ])->findOrFail($id);

    return response()->json([
        'message' => 'Chi tiết đơn hàng',
        'data' => $order
    ]);
}

// Lấy danh sách đơn hàng của user hiện tại
public function myOrders()
{
    $user = auth('sanctum')->user();
    if (!$user) {
        return response()->json(['message' => 'Vui lòng đăng nhập'], 401);
    }

    $orders = Order::with(['items.product', 'items.productVariant'])
        ->where('user_id', $user->id)
        ->orderBy('created_at', 'desc')
        ->get();

    return response()->json([
        'message' => 'Danh sách đơn hàng của bạn',
        'data' => $orders
    ]);
}


}
