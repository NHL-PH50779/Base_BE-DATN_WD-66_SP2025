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
use App\Models\ReturnRequest;
use App\Models\Wallet;
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
            'payment_method' => 'required|in:cod,bank_transfer,credit_card,vnpay',
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
        
        // Kiểm tra items từ request hoặc giỏ hàng
        if ($request->has('items') && !empty($request->items)) {
            // Sử dụng items từ request
            $orderItems = $request->items;
        } else {
            // Lấy từ giỏ hàng
            $cart = Cart::with('items.product')->where('user_id', $user->id)->first();
            if (!$cart || $cart->items->isEmpty()) {
                return response()->json([
                    'message' => 'Giỏ hàng trống và không có sản phẩm nào được chọn'
                ], 400);
            }
            $orderItems = $cart->items;
        }

        // Kiểm tra tồn kho
        foreach ($orderItems as $item) {
            $productVariantId = is_array($item) ? ($item['product_variant_id'] ?? null) : $item->product_variant_id;
            $quantity = is_array($item) ? $item['quantity'] : $item->quantity;
            
            if ($productVariantId) {
                $variant = ProductVariant::find($productVariantId);
                if (!$variant || $variant->stock < $quantity) {
                    return response()->json([
                        'message' => "Sản phẩm không đủ số lượng tồn kho"
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
            foreach ($orderItems as $item) {
                $productId = is_array($item) ? $item['product_id'] : $item->product_id;
                $productVariantId = is_array($item) ? ($item['product_variant_id'] ?? null) : $item->product_variant_id;
                $quantity = is_array($item) ? $item['quantity'] : $item->quantity;
                $price = is_array($item) ? $item['price'] : $item->price;
                
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $productId,
                    'product_variant_id' => $productVariantId,
                    'quantity' => $quantity,
                    'price' => $price
                ]);
                
                // Trừ tồn kho
                if ($productVariantId) {
                    $variant = ProductVariant::find($productVariantId);
                    if ($variant) {
                        $variant->decrement('stock', $quantity);
                    }
                }
            }

            // Xóa giỏ hàng nếu có
            if (isset($cart)) {
                $cart->items()->delete();
            }

            DB::commit();
            
            return response()->json([
                'message' => 'Đặt hàng thành công',
                'data' => [
                    'order' => $order->load(['items.product', 'items.productVariant'])
                ]
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
    if ($validated['order_status_id'] == Order::STATUS_DELIVERED) {
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
    
    // Cho phép hủy khi: Chờ xác nhận (1) hoặc Đã xác nhận (2)
    if (!in_array($order->order_status_id, [1, 2])) {
        return response()->json([
            'message' => 'Chỉ có thể hủy đơn hàng khi chưa giao hoặc đang chuẩn bị'
        ], 400);
    }
    
    DB::beginTransaction();
    try {
        // Cập nhật trạng thái đơn hàng thành đã hủy
        $updateData = ['order_status_id' => 6];
        
        // Nếu đơn hàng VNPay đã thanh toán, hoàn tiền về ví
        if ($order->payment_method === 'vnpay' && $order->payment_status === 'paid') {
            $updateData['payment_status_id'] = 3; // Đã hoàn tiền
            $updateData['payment_status'] = 'refunded';
            
            // Hoàn tiền về ví người dùng
            $wallet = $user->getOrCreateWallet();
            $wallet->credit(
                $order->total,
                "Hoàn tiền đơn hàng #{$order->id}",
                'Order',
                $order->id
            );
        }
        
        $order->update($updateData);
        
        // Hoàn lại tồn kho
        foreach ($order->items as $item) {
            if ($item->product_variant_id) {
                $variant = ProductVariant::find($item->product_variant_id);
                if ($variant) {
                    $variant->increment('stock', $item->quantity);
                }
            }
        }
        
        DB::commit();
        
        $message = 'Hủy đơn hàng thành công';
        if ($order->payment_method === 'vnpay' && $order->payment_status === 'refunded') {
            $message .= '. Tiền đã được hoàn về ví của bạn.';
        }
        
        return response()->json([
            'message' => $message,
            'data' => $order->fresh()
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
    if ($validated['order_status_id'] == Order::STATUS_DELIVERED && $order->payment_method === 'cod') {
        $updateData['payment_status_id'] = Order::PAYMENT_PAID;
    }

    $order->update($updateData);

    return response()->json([
        'message' => 'Cập nhật trạng thái đơn hàng thành công',
        'data' => $order->load(['user', 'items.product'])
    ]);
}

// Tự động hoàn thành đơn hàng sau 3 ngày
public function autoComplete()
{
    $user = auth('sanctum')->user();
    if (!$user || ($user->role !== 'admin' && $user->role !== 'super_admin')) {
        return response()->json(['message' => 'Không có quyền truy cập'], 403);
    }

    $threeDaysAgo = now()->subDays(3);
    $orders = Order::where('order_status_id', Order::STATUS_DELIVERED)
        ->where('updated_at', '<=', $threeDaysAgo)
        ->get();

    $completedCount = 0;
    foreach($orders as $order) {
        $updateData = ['order_status_id' => Order::STATUS_DELIVERED];
        
        // Tự động cập nhật trạng thái thanh toán cho COD
        if($order->payment_method === 'cod' && $order->payment_status_id == Order::PAYMENT_PENDING) {
            $updateData['payment_status_id'] = Order::PAYMENT_PAID;
        }
        
        $order->update($updateData);
        $completedCount++;
    }

    return response()->json([
        'message' => "Đã tự động hoàn thành {$completedCount} đơn hàng",
        'completed_orders' => $completedCount
    ]);
}

// Client xác nhận hoàn thành đơn hàng
public function confirmComplete(Request $request, $id)
{
    $user = $request->user();
    $order = Order::where('id', $id)->where('user_id', $user->id)->first();
    
    if (!$order) {
        return response()->json(['message' => 'Không tìm thấy đơn hàng'], 404);
    }
    
    // Cho phép xác nhận khi đơn hàng đang giao (4) hoặc đã giao (5)
    if (!in_array($order->order_status_id, [Order::STATUS_SHIPPING, Order::STATUS_DELIVERED])) {
        return response()->json([
            'message' => 'Chỉ có thể xác nhận đơn hàng khi đang giao hoặc đã giao. Trạng thái hiện tại: ' . $order->getStatusTextAttribute(),
            'current_status' => $order->order_status_id
        ], 400);
    }
    
    // Cập nhật trạng thái đơn hàng thành đã giao và xử lý thanh toán
    $updateData = ['order_status_id' => Order::STATUS_DELIVERED];
    
    // Tự động cập nhật trạng thái thanh toán cho COD khi xác nhận nhận hàng
    if($order->payment_method === 'cod' && $order->payment_status_id == Order::PAYMENT_PENDING) {
        $updateData['payment_status_id'] = Order::PAYMENT_PAID;
    }
    
    // Cập nhật trạng thái
    $order->update($updateData);
    
    return response()->json([
        'message' => 'Xác nhận nhận hàng thành công',
        'data' => $order->fresh()->load(['items.product'])
    ]);
}

// Yêu cầu hoàn hàng
public function requestRefund(Request $request, $id)
{
    $user = $request->user();
    $order = Order::where('id', $id)->where('user_id', $user->id)->first();
    
    if (!$order) {
        return response()->json(['message' => 'Không tìm thấy đơn hàng'], 404);
    }
    
    if (!in_array($order->order_status_id, [Order::STATUS_DELIVERED])) {
        return response()->json(['message' => 'Chỉ có thể yêu cầu hoàn hàng cho đơn đã giao hoặc hoàn thành'], 400);
    }
    
    $validated = $request->validate([
        'reason' => 'required|string|max:500'
    ]);
    
    // Tạo yêu cầu hoàn hàng
    ReturnRequest::create([
        'order_id' => $order->id,
        'user_id' => $user->id,
        'reason' => $validated['reason'],
        'status' => 'pending'
    ]);
    
    // Cập nhật trạng thái đơn hàng
    $order->update(['order_status_id' => 7]); // Yêu cầu hoàn hàng
    
    return response()->json([
        'message' => 'Đã gửi yêu cầu hoàn hàng thành công',
        'data' => $order
    ]);
}

// Admin xử lý yêu cầu hoàn hàng
public function processRefund(Request $request, $id)
{
    $user = auth('sanctum')->user();
    if (!$user || ($user->role !== 'admin' && $user->role !== 'super_admin')) {
        return response()->json(['message' => 'Không có quyền truy cập'], 403);
    }

    $order = Order::findOrFail($id);
    
    if ($order->order_status_id !== 7) {
        return response()->json(['message' => 'Đơn hàng không ở trạng thái yêu cầu hoàn hàng'], 400);
    }
    
    $validated = $request->validate([
        'approve' => 'required|boolean',
        'admin_note' => 'nullable|string|max:500'
    ]);
    
    $newStatusId = $validated['approve'] ? 8 : 9; // 8: Đồng ý, 9: Từ chối
    $updateData = ['order_status_id' => $newStatusId];
    
    // Nếu đồng ý hoàn hàng, cập nhật trạng thái thanh toán
    if ($validated['approve']) {
        $updateData['payment_status_id'] = 3; // Đã hoàn tiền
    }
    
    $order->update($updateData);
    
    // Cập nhật return request
    $returnRequest = ReturnRequest::where('order_id', $order->id)->latest()->first();
    if ($returnRequest) {
        $returnRequest->update([
            'status' => $validated['approve'] ? 'approved' : 'rejected',
            'admin_note' => $validated['admin_note']
        ]);
    }
    
    return response()->json([
        'message' => $validated['approve'] ? 'Đã đồng ý hoàn hàng' : 'Đã từ chối hoàn hàng',
        'data' => $order->load(['returnRequests'])
    ]);
}

public function updatePaymentStatus(Request $request, $id)
{
    $order = Order::find($id);
    
    if (!$order) {
        return response()->json(['message' => 'Không tìm thấy đơn hàng'], 404);
    }
    
    $user = $request->user();
    if ($order->user_id !== $user->id && !in_array($user->role, ['admin', 'super_admin'])) {
        return response()->json(['message' => 'Không có quyền truy cập'], 403);
    }
    
    $validated = $request->validate([
        'payment_status' => 'required|in:paid,failed,refunded',
        'order_status' => 'nullable|in:confirmed,cancelled'
    ]);
    
    $updateData = [];
    
    if ($validated['payment_status'] === 'paid') {
        $updateData['payment_status_id'] = 2;
        $updateData['payment_status'] = 'paid';
        $updateData['paid_at'] = now();
        
        if ($request->order_status === 'confirmed') {
            $updateData['order_status_id'] = 2;
        }
    } elseif ($validated['payment_status'] === 'failed') {
        $updateData['payment_status_id'] = 1;
        $updateData['payment_status'] = 'failed';
        
        if ($request->order_status === 'cancelled') {
            $updateData['order_status_id'] = 6;
        }
    }
    
    $order->update($updateData);
    
    return response()->json([
        'message' => 'Cập nhật trạng thái thành công',
        'data' => $order->fresh()
    ]);
}

}
