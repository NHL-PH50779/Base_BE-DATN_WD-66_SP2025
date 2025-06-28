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

    public function createOrder(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'required|string',
            'note' => 'nullable|string',
            'payment_method' => 'required|in:cod,bank_transfer,credit_card',
            'total' => 'required|numeric|min:0',
            'coupon_code' => 'nullable|string',
            'coupon_discount' => 'nullable|numeric|min:0',
            'items' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $cart = Cart::where('user_id', $user->id)->first();
        if (!$cart || $cart->items->isEmpty()) {
            return response()->json([
                'message' => 'Giỏ hàng trống'
            ], 400);
        }

        // Kiểm tra tồn kho trước khi đặt hàng
        $cartItems = $request->items ? 
            $cart->items->whereIn('id', collect($request->items)->pluck('id')) : 
            $cart->items;
            
        foreach ($cartItems as $cartItem) {
            if ($cartItem->product_variant_id) {
                $variant = ProductVariant::find($cartItem->product_variant_id);
                if (!$variant || $variant->stock < $cartItem->quantity) {
                    return response()->json([
                        'message' => "Sản phẩm '{$cartItem->product->name}' không đủ số lượng tồn kho"
                    ], 400);
                }
            }
        }

        DB::beginTransaction();
        try {
            // Tạo đơn hàng
            $order = Order::create([
                'user_id' => $user->id,
                'order_status_id' => 1,
                'payment_status_id' => 1,
                'total' => $request->total,
                'name' => $request->name,
                'phone' => $request->phone,
                'email' => $request->email,
                'address' => $request->address,
                'note' => $request->note,
                'payment_method' => $request->payment_method,
                'coupon_code' => $request->coupon_code,
                'coupon_discount' => $request->coupon_discount ?? 0
            ]);

            // Tạo order items và trừ tồn kho
            foreach ($cartItems as $cartItem) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $cartItem->product_id,
                    'product_variant_id' => $cartItem->product_variant_id,
                    'quantity' => $cartItem->quantity,
                    'price' => $cartItem->price
                ]);
                
                // Trừ tồn kho
                if ($cartItem->product_variant_id) {
                    $variant = ProductVariant::find($cartItem->product_variant_id);
                    if ($variant) {
                        $variant->decrement('stock', $cartItem->quantity);
                    }
                }
            }

            // Xóa các items đã đặt hàng khỏi giỏ hàng
            if ($request->items) {
                $cart->items()->whereIn('id', collect($request->items)->pluck('id'))->delete();
            } else {
                $cart->items()->delete();
            }

            DB::commit();
            
            return response()->json([
                'message' => 'Đặt hàng thành công',
                'data' => $order->load(['items.product', 'items.productVariant'])
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => 'Có lỗi xảy ra khi đặt hàng: ' . $e->getMessage()
            ], 500);
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
    
    if ($user->role !== 'admin' && $user->role !== 'super_admin') {
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
    if (!$user || ($user->role !== 'admin' && $user->role !== 'super_admin')) {
        return response()->json(['message' => 'Không có quyền truy cập'], 403);
    }

    $order = Order::findOrFail($id);
    
    $validated = $request->validate([
        'order_status_id' => 'required|integer|min:1|max:6',
        'payment_status_id' => 'nullable|integer|min:1|max:3'
    ]);

    // Tự động cập nhật trạng thái thanh toán khi đơn hàng hoàn thành
    if ($validated['order_status_id'] == Order::STATUS_COMPLETED) {
        // Nếu phương thức thanh toán là COD, tự động đánh dấu đã thanh toán
        if ($order->payment_method === 'cod') {
            $validated['payment_status_id'] = 2; // Đã thanh toán
        }
    }
    
    // Nếu không có payment_status_id trong request, giữ nguyên giá trị cũ
    if (!isset($validated['payment_status_id'])) {
        unset($validated['payment_status_id']);
    }

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
    if (!$user || ($user->role !== 'admin' && $user->role !== 'super_admin')) {
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

public function cancelOrder(Request $request, $id)
{
    $user = $request->user();
    $order = Order::where('id', $id)->where('user_id', $user->id)->first();
    
    if (!$order) {
        return response()->json([
            'message' => 'Không tìm thấy đơn hàng'
        ], 404);
    }
    
    if (!in_array($order->order_status_id, [1, 2])) {
        return response()->json([
            'message' => 'Không thể hủy đơn hàng ở trạng thái này'
        ], 400);
    }
    
    DB::beginTransaction();
    try {
        $order->update(['order_status_id' => 6]);
        
        foreach ($order->items as $item) {
            if ($item->product_variant_id) {
                $variant = ProductVariant::find($item->product_variant_id);
                if ($variant) {
                    $variant->increment('stock', $item->quantity);
                }
            }
        }
        
        DB::commit();
        
        return response()->json([
            'message' => 'Hủy đơn hàng thành công',
            'data' => $order
        ]);
        
    } catch (\Exception $e) {
        DB::rollback();
        return response()->json([
            'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
        ], 500);
    }
}

// Cập nhật chỉ trạng thái đơn hàng (Admin)
public function updateOrderStatus(Request $request, $id)
{
    $user = auth('sanctum')->user();
    if (!$user || ($user->role !== 'admin' && $user->role !== 'super_admin')) {
        return response()->json(['message' => 'Không có quyền truy cập'], 403);
    }

    $order = Order::findOrFail($id);
    
    $validated = $request->validate([
        'order_status_id' => 'required|integer|min:1|max:6'
    ]);

    $updateData = ['order_status_id' => $validated['order_status_id']];
    
    // Tự động cập nhật trạng thái thanh toán khi đơn hàng hoàn thành với COD
    if ($validated['order_status_id'] == Order::STATUS_COMPLETED && $order->payment_method === 'cod') {
        $updateData['payment_status_id'] = Order::PAYMENT_PAID;
    }

    $order->update($updateData);

    return response()->json([
        'message' => 'Cập nhật trạng thái đơn hàng thành công',
        'data' => $order->load(['user', 'items.product'])
    ]);
}

}
