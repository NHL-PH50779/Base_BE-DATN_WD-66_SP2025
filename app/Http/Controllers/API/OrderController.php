<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\OrderItem;
use App\Models\Wallet;
use App\Models\WalletTransaction;
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
        'order_status_id' => Order::STATUS_PENDING,
        'payment_status_id' => Order::PAYMENT_PENDING,
        'status' => 'pending',
        'payment_status' => 'unpaid',
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
            'payment_method' => 'required|in:cod,vnpay',
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

        // Lấy user từ auth hoặc tạo guest user
        $user = auth('sanctum')->user();
        if (!$user) {
            // Tạo guest user tạm thời
            $user = new \stdClass();
            $user->id = null;
        }
        \Log::info('Order request data:', $request->all());
        $cart = null;
        if ($user->id) {
            $cart = Cart::with('items')->where('user_id', $user->id)->first();
        }
        
        // Nếu không có items trong request và không có cart, cho phép tạo đơn hàng trống
        // (sẽ được xử lý bởi frontend)
        if (!$request->items && (!$cart || $cart->items->isEmpty())) {
            // Cho phép tạo đơn hàng trống cho guest checkout
            \Log::info('Creating empty order for guest checkout');
        }
        
        // Nếu có items trong request nhưng không có cart, tạo đơn hàng từ items
        if ($request->items && (!$cart || $cart->items->isEmpty())) {
            \Log::info('Creating order from request items without cart');
        }

        // Kiểm tra tồn kho trước khi đặt hàng
        $cartItems = collect();
        if ($request->items) {
            // Nếu có items trong request, sử dụng items đó
            if ($cart) {
                $cartItems = $cart->items->whereIn('id', collect($request->items)->pluck('id'));
            }
        } else if ($cart) {
            // Nếu không có items trong request, sử dụng toàn bộ cart
            $cartItems = $cart->items;
        }
            
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
                'user_id' => $user->id ?? null,
                'total' => $request->total,
                'name' => $request->name,
                'phone' => $request->phone,
                'email' => $request->email,
                'address' => $request->address,
                'note' => $request->note,
                'payment_method' => $request->payment_method,
                'coupon_code' => $request->coupon_code,
                'coupon_discount' => $request->coupon_discount ?? 0,
                'order_status_id' => Order::STATUS_PENDING,
                'payment_status_id' => Order::PAYMENT_PENDING,
                'status' => 'pending',
                'payment_status' => 'unpaid'
            ]);

            // Tạo order items và trừ tồn kho
            // Tạo order items từ cart hoặc từ request items
            if ($cartItems->isNotEmpty()) {
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
            } else if ($request->items) {
                // Nếu không có cart items nhưng có items trong request
                foreach ($request->items as $item) {
                    // Tạo order item trực tiếp từ request data
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $item['id'], // Sử dụng id từ request làm product_id
                        'product_variant_id' => null, // Có thể null nếu không có variant
                        'quantity' => $item['quantity'],
                        'price' => $item['price']
                    ]);
                }
            }

            // Xóa các items đã đặt hàng khỏi giỏ hàng
            if ($cart) {
                if ($request->items) {
                    $cart->items()->whereIn('id', collect($request->items)->pluck('id'))->delete();
                } else {
                    $cart->items()->delete();
                }
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
    
    // Tạm thời bỏ qua auth check để test
    // if (!$user) {
    //     return response()->json(['message' => 'Vui lòng đăng nhập'], 401);
    // }
    
    // if ($user->role !== 'admin' && $user->role !== 'super_admin') {
    //     return response()->json(['message' => 'Không có quyền admin'], 403);
    // }

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
    
    if ($order->order_status_id !== 1) {
        return response()->json([
            'message' => 'Chỉ có thể hủy đơn hàng khi chưa được xác nhận'
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
    // Tạm thời bỏ qua auth check để test
    // if (!$user || ($user->role !== 'admin' && $user->role !== 'super_admin')) {
    //     return response()->json(['message' => 'Không có quyền truy cập'], 403);
    // }

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
        $updateData = ['order_status_id' => Order::STATUS_COMPLETED];
        
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
    
    if ($order->order_status_id !== Order::STATUS_DELIVERED) {
        return response()->json(['message' => 'Chỉ có thể xác nhận đơn hàng đã giao'], 400);
    }
    
    $updateData = ['order_status_id' => Order::STATUS_COMPLETED];
    
    // Tự động cập nhật trạng thái thanh toán cho COD
    if($order->payment_method === 'cod' && $order->payment_status_id == Order::PAYMENT_PENDING) {
        $updateData['payment_status_id'] = Order::PAYMENT_PAID;
    }
    
    $order->update($updateData);
    
    return response()->json([
        'message' => 'Xác nhận nhận hàng thành công',
        'data' => $order->load(['items.product'])
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
    
    if (!in_array($order->order_status_id, [Order::STATUS_DELIVERED, Order::STATUS_COMPLETED])) {
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

// Yêu cầu hủy đơn VNPay
public function cancelRequest(Request $request, $id)
{
    $user = $request->user();
    $order = Order::where('id', $id)->where('user_id', $user->id)->first();
    
    if (!$order) {
        return response()->json(['message' => 'Không tìm thấy đơn hàng'], 404);
    }
    
    if (!$order->is_vnpay || $order->status !== 'success') {
        return response()->json(['message' => 'Chỉ có thể yêu cầu hủy đơn VNPay đã thanh toán'], 400);
    }
    
    if ($order->cancel_requested) {
        return response()->json(['message' => 'Đã gửi yêu cầu hủy trước đó'], 400);
    }
    
    $order->update(['cancel_requested' => true]);
    
    return response()->json([
        'message' => 'Đã gửi yêu cầu hủy đơn hàng',
        'data' => $order
    ]);
}

// Admin duyệt yêu cầu hủy
public function approveCancel(Request $request, $id)
{
    $user = auth('sanctum')->user();
    if (!$user || ($user->role !== 'admin' && $user->role !== 'super_admin')) {
        return response()->json(['message' => 'Không có quyền truy cập'], 403);
    }

    $order = Order::findOrFail($id);
    
    if (!$order->cancel_requested) {
        return response()->json(['message' => 'Đơn hàng chưa có yêu cầu hủy'], 400);
    }
    
    DB::beginTransaction();
    try {
        // Cập nhật đơn hàng
        $order->update([
            'status' => 'cancelled',
            'order_status_id' => Order::STATUS_CANCELLED,
            'cancelled_at' => now()
        ]);
        
        // Kiểm tra và tạo ví nếu chưa có
        $wallet = Wallet::firstOrCreate(
            ['user_id' => $order->user_id],
            ['balance' => 0]
        );
        
        // Cộng tiền vào ví
        $wallet->increment('balance', $order->total);
        
        // Ghi lịch sử giao dịch
        WalletTransaction::create([
            'user_id' => $order->user_id,
            'type' => 'refund',
            'amount' => $order->total,
            'description' => 'Hoàn tiền đơn hàng #' . $order->id,
            'order_id' => $order->id
        ]);
        
        DB::commit();
        
        return response()->json([
            'message' => 'Đã duyệt hủy đơn và hoàn tiền vào ví',
            'data' => $order
        ]);
        
    } catch (\Exception $e) {
        DB::rollback();
        return response()->json([
            'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
        ], 500);
    }
}

// Lấy thông tin ví
public function getWallet(Request $request)
{
    $user = $request->user();
    $wallet = Wallet::firstOrCreate(
        ['user_id' => $user->id],
        ['balance' => 0]
    );
    
    return response()->json([
        'message' => 'Thông tin ví',
        'data' => [
            'balance' => $wallet->balance,
            'transactions' => $wallet->transactions()->latest()->take(10)->get()
        ]
    ]);
}

}
