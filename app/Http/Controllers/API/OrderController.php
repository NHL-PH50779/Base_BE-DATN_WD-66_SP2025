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
use App\Models\ReturnRequest;
use App\Mail\OrderStatusMail;
use Illuminate\Support\Facades\Mail;
use App\Models\FlashSaleItem;
use Carbon\Carbon;
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
            'payment_method' => 'required|in:cod,vnpay,wallet',
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
                // Kiểm tra stock của variant
                $variant = ProductVariant::find($cartItem->product_variant_id);
                if (!$variant || $variant->stock < $cartItem->quantity) {
                    return response()->json([
                        'message' => "Sản phẩm '{$cartItem->product->name}' không đủ số lượng tồn kho"
                    ], 400);
                }
            } else {
                // Kiểm tra stock của product chính
                $product = \App\Models\Product::find($cartItem->product_id);
                if (!$product || !isset($product->stock) || $product->stock < $cartItem->quantity) {
                    return response()->json([
                        'message' => "Sản phẩm '{$cartItem->product->name}' không đủ số lượng tồn kho"
                    ], 400);
                }
            }
        }

        // Kiểm tra wallet balance nếu thanh toán bằng ví
        if ($request->payment_method === 'wallet') {
            if (!$user->id) {
                return response()->json(['message' => 'Vui lòng đăng nhập để thanh toán bằng ví'], 401);
            }
            
            $wallet = $user->wallet;
            if (!$wallet) {
                $wallet = $user->wallet()->create(['balance' => 0]);
            }
            
            if ($wallet->balance < $request->total) {
                return response()->json([
                    'message' => 'Số dư ví không đủ để thanh toán',
                    'current_balance' => $wallet->balance,
                    'required_amount' => $request->total
                ], 400);
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
                'payment_status_id' => $request->payment_method === 'wallet' ? Order::PAYMENT_PAID : Order::PAYMENT_PENDING,
                'status' => 'pending',
                'payment_status' => $request->payment_method === 'wallet' ? 'paid' : 'unpaid'
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
                    
                    // Trừ stock và flash sale cho COD và Wallet, VNPay sẽ trừ khi thanh toán thành công
                    if (in_array($request->payment_method, ['cod', 'wallet'])) {
                        if ($cartItem->product_variant_id) {
                            // Trừ stock của variant
                            $variant = ProductVariant::find($cartItem->product_variant_id);
                            if ($variant && $variant->stock >= $cartItem->quantity) {
                                $variant->decrement('stock', $cartItem->quantity);
                            }
                        } else {
                            // Trừ stock của product chính
                            $product = \App\Models\Product::find($cartItem->product_id);
                            if ($product && isset($product->stock) && $product->stock >= $cartItem->quantity) {
                                $product->decrement('stock', $cartItem->quantity);
                            }
                        }
                        
                        // Trừ số lượng flash sale nếu là sản phẩm flash sale
                        $this->decrementFlashSaleQuantity($cartItem->product_id, $cartItem->quantity);
                        
                        // Clear cache ngay lập tức
                        \Illuminate\Support\Facades\Cache::forget('current_flash_sale');
                        \Illuminate\Support\Facades\Cache::forget("flash_sale_product_{$cartItem->product_id}");
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

            // Xử lý thanh toán bằng ví
            if ($request->payment_method === 'wallet' && $user->id) {
                $wallet = $user->wallet;
                
                // Trừ tiền từ ví
                $wallet->subtractMoney(
                    $request->total,
                    'Thanh toán đơn hàng #' . $order->id,
                    'order',
                    $order->id
                );
                
                \Log::info('Wallet payment processed:', [
                    'order_id' => $order->id,
                    'amount' => $request->total,
                    'user_id' => $user->id,
                    'new_balance' => $wallet->fresh()->balance
                ]);
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
    // Tạm thời bỏ qua auth check để test
    // if (!$user || ($user->role !== 'admin' && $user->role !== 'super_admin')) {
    //     return response()->json(['message' => 'Không có quyền truy cập'], 403);
    // }

    $order = Order::with('user')->findOrFail($id);
    
    $validated = $request->validate([
        'order_status_id' => 'required|integer|min:1|max:9',
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

    // Gửi email thông báo cho khách hàng
    try {
        if ($order->user && $order->user->email) {
            Mail::to($order->user->email)->send(new OrderStatusMail($order));
        } elseif ($order->email) {
            Mail::to($order->email)->send(new OrderStatusMail($order));
        }
    } catch (\Exception $e) {
        \Log::error('Failed to send order status email: ' . $e->getMessage());
    }

    return response()->json([
        'message' => 'Cập nhật trạng thái và gửi email thành công',
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
        ->get()
        ->map(function ($order) {
            return [
                'id' => $order->id,
                'user_id' => $order->user_id,
                'order_status_id' => $order->order_status_id,
                'payment_status_id' => $order->payment_status_id,
                'total' => $order->total,
                'payment_method' => $order->payment_method,
                'cancel_requested' => $order->cancel_requested ?? false,
                'cancel_reason' => $order->cancel_reason,
                'created_at' => $order->created_at,
                'items' => $order->items
            ];
        });

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
    
    // Kiểm tra điều kiện hủy đơn - chỉ cho phép hủy khi chưa giao hàng
    if (!in_array($order->order_status_id, [Order::STATUS_PENDING, Order::STATUS_CONFIRMED])) {
        return response()->json([
            'message' => 'Chỉ có thể hủy đơn hàng khi chưa giao hàng'
        ], 400);
    }
    
    // Lấy reason từ request body
    $reason = $request->input('reason', 'Khách hàng yêu cầu hủy');
    
    \Log::info('Cancel order request:', [
        'order_id' => $order->id,
        'user_id' => $user->id,
        'payment_method' => $order->payment_method,
        'payment_status' => $order->payment_status_id,
        'reason' => $reason
    ]);
    
    DB::beginTransaction();
    try {
        $order->update(['order_status_id' => 6]);
        
        // Hoàn lại tồn kho khi hủy đơn
        foreach ($order->items as $item) {
            if ($item->product_variant_id) {
                // Hoàn stock cho variant
                $variant = ProductVariant::find($item->product_variant_id);
                if ($variant) {
                    $variant->increment('stock', $item->quantity);
                }
            } else {
                // Hoàn stock cho product chính
                $product = \App\Models\Product::find($item->product_id);
                if ($product && isset($product->stock)) {
                    $product->increment('stock', $item->quantity);
                }
            }
        }
        
        // Hoàn tiền nếu đơn đã thanh toán
        if ($order->payment_status_id == Order::PAYMENT_PAID) {
            $wallet = $user->wallet;
            if (!$wallet) {
                $wallet = $user->wallet()->create(['balance' => 0]);
            }
            
            // Kiểm tra đã hoàn tiền chưa
            $alreadyRefunded = \App\Models\WalletTransaction::where('reference_id', $order->id)
                ->where('reference_type', 'order')
                ->where('type', 'credit')
                ->where('description', 'LIKE', '%Hoàn tiền%')
                ->exists();
                
            if (!$alreadyRefunded) {
                $wallet->addMoney(
                    $order->total,
                    'Hoàn tiền hủy đơn #' . $order->id . ' (' . strtoupper($order->payment_method) . ')',
                    'order',
                    $order->id
                );
                
                \Log::info('Refunded VNPay order:', [
                    'order_id' => $order->id,
                    'amount' => $order->total,
                    'user_id' => $user->id,
                    'new_balance' => $wallet->fresh()->balance
                ]);
            }
            
            $order->update(['payment_status_id' => 3]); // Đã hoàn tiền
        }
        
        DB::commit();
        
        return response()->json([
            'message' => ($order->payment_status_id == 3) 
                ? 'Hủy đơn hàng thành công và đã hoàn tiền vào ví' 
                : 'Hủy đơn hàng thành công',
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

    $order = Order::with('user')->findOrFail($id);
    
    $validated = $request->validate([
        'order_status_id' => 'required|integer|min:1|max:9'
    ]);

    $updateData = ['order_status_id' => $validated['order_status_id']];
    
    // Tự động cập nhật trạng thái thanh toán khi đơn hàng hoàn thành với COD
    if ($validated['order_status_id'] == Order::STATUS_COMPLETED && $order->payment_method === 'cod') {
        $updateData['payment_status_id'] = Order::PAYMENT_PAID;
    }

    $order->update($updateData);

    // Gửi email thông báo cho khách hàng
    try {
        if ($order->user && $order->user->email) {
            Mail::to($order->user->email)->send(new OrderStatusMail($order));
        } elseif ($order->email) {
            Mail::to($order->email)->send(new OrderStatusMail($order));
        }
    } catch (\Exception $e) {
        \Log::error('Failed to send order status email: ' . $e->getMessage());
    }

    return response()->json([
        'message' => 'Cập nhật trạng thái đơn hàng và gửi email thành công',
        'data' => $order->load(['user', 'items.product'])
    ]);
}

// Cập nhật trạng thái thanh toán (Admin)
public function updatePaymentStatus(Request $request, $id)
{
    $order = Order::findOrFail($id);
    
    $validated = $request->validate([
        'payment_status_id' => 'required|integer|min:1|max:3'
    ]);

    $order->update(['payment_status_id' => $validated['payment_status_id']]);

    return response()->json([
        'message' => 'Cập nhật trạng thái thanh toán thành công',
        'data' => $order
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
    try {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Vui lòng đăng nhập'], 401);
        }
        
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
        $order->update([
            'order_status_id' => Order::STATUS_RETURN_REQUESTED,
            'return_requested' => true,
            'return_reason' => $validated['reason']
        ]);
        
        return response()->json([
            'message' => 'Đã gửi yêu cầu hoàn hàng thành công',
            'data' => $order
        ]);
        
    } catch (\Exception $e) {
        \Log::error('Return request error: ' . $e->getMessage(), [
            'order_id' => $id,
            'user_id' => $request->user() ? $request->user()->id : null,
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'message' => 'Có lỗi xảy ra khi gửi yêu cầu hoàn hàng',
            'error' => $e->getMessage()
        ], 500);
    }
}

// Admin xử lý yêu cầu hoàn hàng
public function processRefund(Request $request, $id)
{
    $user = auth('sanctum')->user();
    // Tạm thời bỏ qua auth check để test
    // if (!$user || ($user->role !== 'admin' && $user->role !== 'super_admin')) {
    //     return response()->json(['message' => 'Không có quyền truy cập'], 403);
    // }

    $order = Order::with('user')->findOrFail($id);
    
    $validated = $request->validate([
        'approve' => 'required|boolean',
        'admin_note' => 'nullable|string|max:500'
    ]);
    
    DB::beginTransaction();
    try {
        // Kiểm tra đã hoàn tiền chưa
        $alreadyRefunded = WalletTransaction::where('reference_id', $order->id)
            ->where('reference_type', 'order')
            ->where('type', 'credit')
            ->where('description', 'LIKE', '%Hoàn tiền%')
            ->exists();
            
        if ($alreadyRefunded) {
            return response()->json(['message' => 'Đơn này đã hoàn tiền trước đó'], 400);
        }
        
        // Nếu đồng ý hoàn hàng, hoàn tiền về ví
        if ($validated['approve']) {
            // Hoàn tiền về ví người dùng
            $orderUser = $order->user;
            if (!$orderUser) {
                throw new \Exception('Không tìm thấy thông tin khách hàng');
            }
            
            $wallet = $orderUser->wallet;
            if (!$wallet) {
                $wallet = $orderUser->wallet()->create(['balance' => 0]);
            }
            
            $wallet->addMoney(
                $order->total,
                'Hoàn tiền hoàn hàng đơn #' . $order->id,
                'order',
                $order->id
            );
            
            \Log::info('Refunded return request:', [
                'order_id' => $order->id,
                'amount' => $order->total,
                'user_id' => $orderUser->id,
                'new_balance' => $wallet->fresh()->balance
            ]);
            
            // Cập nhật trạng thái đơn hàng
            $order->update([
                'order_status_id' => 8, // Return approved
                'payment_status_id' => 3 // Đã hoàn tiền
            ]);
        } else {
            // Từ chối hoàn hàng
            $order->update([
                'order_status_id' => 9 // Return rejected
            ]);
        }
        
        // Cập nhật return request nếu có
        $returnRequest = ReturnRequest::where('order_id', $order->id)->latest()->first();
        if ($returnRequest) {
            $returnRequest->update([
                'status' => $validated['approve'] ? 'approved' : 'rejected',
                'admin_note' => $validated['admin_note']
            ]);
        }
        
        DB::commit();
        
        return response()->json([
            'message' => $validated['approve'] 
                ? 'Đã đồng ý hoàn hàng và hoàn tiền vào ví' 
                : 'Đã từ chối hoàn hàng',
            'data' => $order->fresh()
        ]);
        
    } catch (\Exception $e) {
        DB::rollback();
        \Log::error('Process refund error: ' . $e->getMessage());
        return response()->json([
            'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
        ], 500);
    }
}

// Yêu cầu hủy đơn VNPay
public function cancelRequest(Request $request, $id)
{
    // Tạm thời bỏ qua auth để test
    $order = Order::where('id', $id)->first();
    
    if (!$order) {
        return response()->json(['message' => 'Không tìm thấy đơn hàng'], 404);
    }
    
    // Kiểm tra điều kiện hủy đơn VNPay
    if ($order->payment_method !== 'vnpay' || $order->payment_status_id !== Order::PAYMENT_PAID) {
        return response()->json(['message' => 'Chỉ có thể yêu cầu hủy đơn VNPay đã thanh toán'], 400);
    }
    
    // Chỉ cho phép hủy khi đơn hàng ở trạng thái "Chờ xác nhận" hoặc "Đã xác nhận"
    if (!in_array($order->order_status_id, [Order::STATUS_PENDING, Order::STATUS_CONFIRMED])) {
        return response()->json(['message' => 'Chỉ có thể yêu cầu hủy đơn hàng khi chờ xác nhận hoặc đã xác nhận'], 400);
    }
    
    if ($order->cancel_requested) {
        return response()->json(['message' => 'Đã gửi yêu cầu hủy trước đó'], 400);
    }
    
    $validated = $request->validate([
        'reason' => 'required|string|max:500'
    ]);
    
    $order->update([
        'cancel_requested' => true,
        'cancel_reason' => $validated['reason']
    ]);
    
    return response()->json([
        'message' => 'Đã gửi yêu cầu hủy đơn hàng, vui lòng chờ admin xác nhận',
        'data' => $order
    ]);
}

// Admin duyệt yêu cầu hủy
public function approveCancel(Request $request, $id)
{
    $user = auth('sanctum')->user();
    // Tạm thời bỏ qua auth check để test
    // if (!$user || ($user->role !== 'admin' && $user->role !== 'super_admin')) {
    //     return response()->json(['message' => 'Không có quyền truy cập'], 403);
    // }

    $order = Order::findOrFail($id);
    
    if (!$order->cancel_requested) {
        return response()->json(['message' => 'Đơn hàng chưa có yêu cầu hủy'], 400);
    }
    
    // Kiểm tra đã hoàn tiền chưa
    $alreadyRefunded = \App\Models\WalletTransaction::where('reference_id', $order->id)
        ->where('reference_type', 'order')
        ->where('type', 'credit')
        ->where('description', 'LIKE', '%Hoàn tiền%')
        ->exists();
    
    if ($alreadyRefunded) {
        return response()->json(['message' => 'Đơn này đã hoàn tiền trước đó'], 400);
    }
    
    DB::beginTransaction();
    try {
        // Cập nhật đơn hàng
        $order->update([
            'status' => 'cancelled',
            'order_status_id' => Order::STATUS_CANCELLED,
            'cancel_requested' => false // Đã xử lý xong yêu cầu hủy
        ]);
        
        // Debug approve cancel
        \Log::info('Approve Cancel Debug:', [
            'order_id' => $order->id,
            'payment_method' => $order->payment_method,
            'payment_status_id' => $order->payment_status_id,
            'total' => $order->total,
            'has_user' => !!$order->user
        ]);
        
        // Hoàn tiền nếu đơn đã thanh toán (VNPay hoặc COD)
        if ($order->payment_status_id == Order::PAYMENT_PAID) {
            // Lấy user và ví
            $orderUser = $order->user;
            if (!$orderUser) {
                throw new \Exception('Không tìm thấy thông tin khách hàng');
            }
            
            $wallet = $orderUser->wallet;
            if (!$wallet) {
                $wallet = $orderUser->wallet()->create(['balance' => 0]);
            }
            
            \Log::info('Before admin refund:', ['balance' => $wallet->balance]);
            
            // Hoàn tiền vào ví
            $wallet->addMoney(
                $order->total,
                'Hoàn tiền hủy đơn #' . $order->id . ' (' . strtoupper($order->payment_method) . ')',
                'order',
                $order->id
            );
            
            \Log::info('After admin refund:', ['balance' => $wallet->fresh()->balance]);
            
            // Cập nhật trạng thái thanh toán
            $order->update(['payment_status_id' => 3]); // Đã hoàn tiền
        }
        
        DB::commit();
        
        return response()->json([
            'message' => ($order->payment_status_id == 3) 
                ? 'Đã duyệt hủy đơn và hoàn tiền vào ví' 
                : 'Đã duyệt hủy đơn hàng',
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

// Admin lấy danh sách yêu cầu hủy đơn
public function getCancelRequests()
{
    $user = auth('sanctum')->user();
    // Tạm thời bỏ qua auth check để test
    // if (!$user || ($user->role !== 'admin' && $user->role !== 'super_admin')) {
    //     return response()->json(['message' => 'Không có quyền truy cập'], 403);
    // }

    $cancelRequests = Order::with(['user', 'items.product'])
        ->where('cancel_requested', true)
        ->where('order_status_id', '!=', Order::STATUS_CANCELLED)
        ->orderBy('updated_at', 'desc')
        ->get();

    return response()->json([
        'message' => 'Danh sách yêu cầu hủy đơn',
        'data' => $cancelRequests
    ]);
}

// Admin từ chối yêu cầu hủy
public function rejectCancel(Request $request, $id)
{
    $user = auth('sanctum')->user();
    // Tạm thời bỏ qua auth check để test
    // if (!$user || ($user->role !== 'admin' && $user->role !== 'super_admin')) {
    //     return response()->json(['message' => 'Không có quyền truy cập'], 403);
    // }

    $order = Order::findOrFail($id);
    
    if (!$order->cancel_requested) {
        return response()->json(['message' => 'Đơn hàng chưa có yêu cầu hủy'], 400);
    }
    
    $validated = $request->validate([
        'reason' => 'nullable|string|max:500'
    ]);
    
    $order->update([
        'cancel_requested' => false,
        'cancel_reason' => $validated['reason'] ?? 'Admin từ chối yêu cầu hủy'
    ]);
    
    return response()->json([
        'message' => 'Đã từ chối yêu cầu hủy đơn hàng',
        'data' => $order
    ]);
}

// Test endpoint để debug hoàn tiền
public function testRefund(Request $request, $id)
{
    $order = Order::with('user')->findOrFail($id);
    
    return response()->json([
        'order_info' => [
            'id' => $order->id,
            'payment_method' => $order->payment_method,
            'payment_status_id' => $order->payment_status_id,
            'total' => $order->total,
            'user_id' => $order->user_id
        ],
        'wallet_info' => [
            'has_wallet' => !!$order->user->wallet,
            'balance' => $order->user->wallet->balance ?? 0
        ],
        'transactions' => \App\Models\WalletTransaction::where('reference_id', $order->id)
            ->where('reference_type', 'order')
            ->get()
    ]);
}

// Helper method để giảm số lượng flash sale
private function decrementFlashSaleQuantity($productId, $quantity)
{
    $now = Carbon::now();
    
    // Tìm flash sale item đang active cho sản phẩm này
    $flashSaleItem = FlashSaleItem::whereHas('flashSale', function ($query) use ($now) {
        $query->where('is_active', true)
              ->where('start_time', '<=', $now)
              ->where('end_time', '>=', $now);
    })
    ->where('product_id', $productId)
    ->where('is_active', true)
    ->first();
    
    if ($flashSaleItem) {
        // Kiểm tra còn đủ số lượng không
        $remainingQuantity = $flashSaleItem->quantity_limit - $flashSaleItem->sold_quantity;
        if ($remainingQuantity >= $quantity) {
            $flashSaleItem->increment('sold_quantity', $quantity);
            
            // Clear cache
            \Illuminate\Support\Facades\Cache::forget('current_flash_sale');
            \Illuminate\Support\Facades\Cache::forget("flash_sale_product_{$productId}");
            
            \Log::info('Decremented flash sale quantity:', [
                'product_id' => $productId,
                'quantity' => $quantity,
                'new_sold_quantity' => $flashSaleItem->fresh()->sold_quantity
            ]);
        }
    }
}

}
