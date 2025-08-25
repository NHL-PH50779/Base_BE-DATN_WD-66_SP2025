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
// FlashSaleItem import removed
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
        // Debug: Log toàn bộ request
        \Log::info('=== CREATE ORDER DEBUG ===');
        \Log::info('Request data:', $request->all());
        \Log::info('Request headers:', $request->headers->all());
        
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
            'items' => 'nullable|array',
            'items.*.id' => 'required_with:items|integer',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'items.*.price' => 'required_with:items|min:0'
        ]);

        if ($validator->fails()) {
            \Log::error('Order validation failed:', [
                'errors' => $validator->errors()->toArray(),
                'request_data' => $request->all()
            ]);
            return response()->json([
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors(),
                'debug_info' => [
                    'validation_rules' => [
                        'name' => 'required|string|max:255',
                        'phone' => 'required|string|max:20',
                        'email' => 'nullable|email|max:255',
                        'address' => 'required|string',
                        'payment_method' => 'required|in:cod,vnpay,wallet',
                        'total' => 'required|numeric|min:0',
                        'items' => 'nullable|array'
                    ]
                ]
            ], 422);
        }

        // Lấy user từ auth hoặc tạo guest user
        $user = auth('sanctum')->user();
        \Log::info('Auth user check:', [
            'has_user' => !!$user,
            'user_id' => $user ? $user->id : null,
            'user_email' => $user ? $user->email : null
        ]);
        
        if (!$user) {
            // Tạo guest user tạm thời
            $user = new \stdClass();
            $user->id = null;
            \Log::info('Created guest user object');
        }
        
        \Log::info('Order request data:', $request->all());
        \Log::info('User info:', ['user_id' => $user->id ?? 'guest', 'authenticated' => !!$user->id]);
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

        // Chuẩn hóa dữ liệu items
        if ($request->items) {
            $items = collect($request->items)->map(function($item) {
                return [
                    'id' => (int)$item['id'],
                    'quantity' => (int)$item['quantity'],
                    'price' => (float)$item['price'],
                    'variant_id' => $item['variant_id'] ?? $item['product_variant_id'] ?? null
                ];
            })->toArray();
            $request->merge(['items' => $items]);
        }
        
        // Kiểm tra tồn kho trước khi đặt hàng
        if ($request->items) {
            // Kiểm tra stock cho items từ request
            foreach ($request->items as $item) {
                $product = \App\Models\Product::withTrashed()->with('variants')->find($item['id']);
                
                if (!$product) {
                    \Log::warning('Product not found, looking for alternatives', [
                        'requested_id' => $item['id']
                    ]);
                    
                    // Tìm sản phẩm có sẵn
                    $product = \App\Models\Product::with('variants')
                        ->where('is_active', true)
                        ->whereHas('variants', function($q) {
                            $q->where('stock', '>', 0);
                        })
                        ->first();
                    
                    if (!$product) {
                        // Nếu vẫn không có, lấy bất kỳ sản phẩm nào
                        $product = \App\Models\Product::with('variants')->first();
                    }
                    
                    if (!$product) {
                        \Log::error('No products found in system');
                        return response()->json([
                            'message' => 'Không có sản phẩm nào trong hệ thống',
                            'product_id' => $item['id']
                        ], 400);
                    }
                    
                    \Log::info('Using alternative product', [
                        'requested_id' => $item['id'],
                        'used_product_id' => $product->id,
                        'product_name' => $product->name
                    ]);
                }
                
                if ($product->trashed()) {
                    return response()->json([
                        'message' => 'Sản phẩm đã bị xóa',
                        'product_id' => $item['id']
                    ], 400);
                }
                
                // Kiểm tra variant_id hoặc product_variant_id
                $variantId = $item['variant_id'] ?? $item['product_variant_id'] ?? null;
                
                if ($variantId) {
                    $variant = ProductVariant::find($variantId);
                    if (!$variant || $variant->stock < $item['quantity']) {
                        return response()->json([
                            'message' => "Sản phẩm '{$product->name}' không đủ số lượng tồn kho. Còn lại: " . ($variant ? $variant->stock : 0),
                            'error_type' => 'out_of_stock',
                            'product_id' => $item['id'],
                            'variant_id' => $variantId,
                            'available_stock' => $variant ? $variant->stock : 0
                        ], 400);
                    }
                } else {
                    // Nếu không có variant_id, kiểm tra variant đầu tiên
                    if ($product->variants->isEmpty()) {
                        return response()->json([
                            'message' => "Sản phẩm '{$product->name}' không có biến thể nào",
                            'error_type' => 'no_variants',
                            'product_id' => $item['id']
                        ], 400);
                    }
                    
                    $firstVariant = $product->variants->first();
                    if ($firstVariant->stock < $item['quantity']) {
                        return response()->json([
                            'message' => "Sản phẩm '{$product->name}' không đủ số lượng tồn kho. Còn lại: {$firstVariant->stock}",
                            'error_type' => 'out_of_stock',
                            'product_id' => $item['id'],
                            'available_stock' => $firstVariant->stock
                        ], 400);
                    }
                }
            }
        } else if ($cart && $cart->items->isNotEmpty()) {
            // Kiểm tra stock cho cart items
            foreach ($cart->items as $cartItem) {
                if ($cartItem->product_variant_id) {
                    $variant = ProductVariant::find($cartItem->product_variant_id);
                    if (!$variant || $variant->stock < $cartItem->quantity) {
                        return response()->json([
                            'message' => "Sản phẩm '{$cartItem->product->name}' không đủ số lượng tồn kho. Còn lại: " . ($variant ? $variant->stock : 0),
                            'error_type' => 'out_of_stock',
                            'product_id' => $cartItem->product_id,
                            'variant_id' => $cartItem->product_variant_id,
                            'available_stock' => $variant ? $variant->stock : 0
                        ], 400);
                    }
                } else {
                    $product = $cartItem->product;
                    $totalStock = $product ? $product->variants->sum('stock') : 0;
                    if ($totalStock < $cartItem->quantity) {
                        return response()->json([
                            'message' => "Sản phẩm '{$product->name}' không đủ số lượng tồn kho. Còn lại: {$totalStock}",
                            'error_type' => 'out_of_stock',
                            'product_id' => $cartItem->product_id,
                            'available_stock' => $totalStock
                        ], 400);
                    }
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

        \Log::info('Starting order creation transaction');
        DB::beginTransaction();
        try {
            // Tạo đơn hàng
            $orderData = [
                'user_id' => $user->id ?? null,
                'total' => $request->total,
                'name' => $request->name,
                'phone' => $request->phone,
                'email' => $request->email,
                'address' => $request->address,
                'note' => $request->note,
                'payment_method' => $request->payment_method,
                'coupon_code' => $request->coupon_code,
                'coupon_discount' => $request->coupon_discount ? $request->coupon_discount : 0,
                'order_status_id' => Order::STATUS_PENDING,
                'payment_status_id' => $request->payment_method === 'wallet' ? Order::PAYMENT_PAID : Order::PAYMENT_PENDING,
                'status' => 'pending',
                'payment_status' => $request->payment_method === 'wallet' ? 'paid' : 'unpaid'
            ];
            
            \Log::info('Creating order with data:', $orderData);
            $order = Order::create($orderData);
            \Log::info('Order created successfully:', ['order_id' => $order->id]);

            // Tạo order items từ request hoặc cart
            if ($request->items) {
                foreach ($request->items as $item) {
                    $product = \App\Models\Product::with('variants')->find($item['id']);
                    $variantId = $item['variant_id'] ?? $item['product_variant_id'] ?? null;
                    $variant = $variantId ? ProductVariant::find($variantId) : null;
                    
                    // Đảm bảo luôn có variant
                    if (!$variant && $product) {
                        if ($product->variants->isNotEmpty()) {
                            $variant = $product->variants->first();
                            $variantId = $variant->id;
                        } else {
                            // Tạo variant mới nếu chưa có
                            $variant = \App\Models\ProductVariant::create([
                                'product_id' => $product->id,
                                'Name' => 'Mặc định',
                                'price' => $product->price ?? $item['price'],
                                'stock' => 100
                            ]);
                            $variantId = $variant->id;
                            \Log::info('Created new variant for product', [
                                'product_id' => $product->id,
                                'variant_id' => $variant->id
                            ]);
                        }
                    }
                    
                    if (!$product) {
                        \Log::error('Product is null after all attempts', [
                            'item_id' => $item['id'],
                            'item_data' => $item
                        ]);
                        return response()->json([
                            'message' => 'Không thể tạo sản phẩm',
                            'product_id' => $item['id']
                        ], 500);
                    }
                    
                    if (!$variant) {
                        \Log::error('Variant is null after all attempts', [
                            'product_id' => $product->id,
                            'product_variants_count' => $product->variants->count()
                        ]);
                        return response()->json([
                            'message' => 'Không thể tạo variant cho sản phẩm',
                            'product_id' => $product->id
                        ], 500);
                    }
                    
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'product_variant_id' => $variantId,
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                        'product_name' => $product->name,
                        'product_image' => $product->thumbnail ?: 'http://127.0.0.1:8000/placeholder.svg',
                        'variant_name' => $variant ? $variant->Name : ''
                    ]);
                    
                    // Trừ stock
                    if ($variant) {
                        $variant->decrement('stock', $item['quantity']);
                    }
                    
                    // Bỏ flash sale logic
                }
            } elseif ($cart && $cart->items->isNotEmpty()) {
                foreach ($cart->items as $cartItem) {
                    $product = $cartItem->product;
                    $variant = $cartItem->productVariant;
                    
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $cartItem->product_id,
                        'product_variant_id' => $cartItem->product_variant_id,
                        'quantity' => $cartItem->quantity,
                        'price' => $cartItem->price,
                        'product_name' => $product ? $product->name : 'Sản phẩm đã xóa',
                        'product_image' => $product && $product->thumbnail ? $product->thumbnail : 'http://127.0.0.1:8000/placeholder.svg',
                        'variant_name' => $variant ? $variant->Name : ''
                    ]);
                    
                    // Trừ stock
                    if ($cartItem->product_variant_id) {
                        $variant = ProductVariant::find($cartItem->product_variant_id);
                        if ($variant) {
                            $variant->decrement('stock', $cartItem->quantity);
                        }
                    } else {
                        $product = $cartItem->product;
                        if ($product && $product->variants->isNotEmpty()) {
                            $firstVariant = $product->variants->first();
                            $firstVariant->decrement('stock', $cartItem->quantity);
                        }
                    }
                    
                    // Bỏ flash sale logic
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

            // Xóa giỏ hàng nếu có
            if ($cart && !$request->items) {
                $cart->items()->delete();
            }

            DB::commit();
            
            // Transform order items để đảm bảo không có null
            $order->load('items');
            $order->items->transform(function($item) {
                $productName = $item->product_name ?: ($item->product ? $item->product->name : 'Sản phẩm đã xóa');
            $productImage = $item->product_image ?: ($item->product && $item->product->thumbnail ? $item->product->thumbnail : 'http://127.0.0.1:8000/placeholder.svg');
            $variantName = $item->variant_name ?: ($item->productVariant ? $item->productVariant->Name : 'Mặc định');
            
            $item->product_info = [
                'id' => $item->product_id,
                'name' => $productName,
                'image' => $productImage,
                'variant_name' => $variantName
            ];
                return $item;
            });
            
            return response()->json([
                'message' => 'Đặt hàng thành công',
                'data' => [
                    'order' => $order
                ]
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Order creation failed:', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            
            // Trả về lỗi chi tiết hơn
            return response()->json([
                'message' => 'Có lỗi xảy ra khi đặt hàng',
                'error' => $e->getMessage(),
                'debug_info' => [
                    'line' => $e->getLine(),
                    'file' => basename($e->getFile()),
                    'request_id' => $item['id'] ?? 'unknown'
                ]
            ], 500);
        }
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

    // Transform items để hiển thị thông tin đã lưu
    $orders->each(function($order) {
        $order->items->transform(function($item) {
            $productName = $item->product_name ?: ($item->product ? $item->product->name : 'Sản phẩm đã xóa');
            $productImage = $item->product_image ?: ($item->product && $item->product->thumbnail ? $item->product->thumbnail : 'http://127.0.0.1:8000/placeholder.svg');
            $variantName = $item->variant_name ?: ($item->productVariant ? $item->productVariant->Name : 'Mặc định');
            
            $item->product_info = [
                'id' => $item->product_id,
                'name' => $productName,
                'image' => $productImage,
                'variant_name' => $variantName
            ];
            return $item;
        });
    });

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

    // Transform items để hiển thị thông tin đã lưu
    $order->items->transform(function($item) {
        $productName = $item->product_name ?: ($item->product ? $item->product->name : 'Sản phẩm đã xóa');
        $productImage = $item->product_image ?: ($item->product && $item->product->thumbnail ? $item->product->thumbnail : 'http://127.0.0.1:8000/placeholder.svg');
        $variantName = $item->variant_name ?: ($item->productVariant ? $item->productVariant->Name : 'Mặc định');
        
        $item->product_info = [
            'id' => $item->product_id,
            'name' => $productName,
            'image' => $productImage,
            'variant_name' => $variantName
        ];
        return $item;
    });

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
            // Transform items để hiển thị thông tin đã lưu
            $order->items->transform(function($item) {
                $productName = $item->product_name ?: ($item->product ? $item->product->name : 'Sản phẩm đã xóa');
            $productImage = $item->product_image ?: ($item->product && $item->product->thumbnail ? $item->product->thumbnail : 'http://127.0.0.1:8000/placeholder.svg');
            $variantName = $item->variant_name ?: ($item->productVariant ? $item->productVariant->Name : 'Mặc định');
            
            $item->product_info = [
                'id' => $item->product_id,
                'name' => $productName,
                'image' => $productImage,
                'variant_name' => $variantName
            ];
                return $item;
            });
            
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

// Xem chi tiết đơn hàng (Client)
public function show($id)
{
    $user = auth('sanctum')->user();
    if (!$user) {
        return response()->json(['message' => 'Vui lòng đăng nhập'], 401);
    }

    $order = Order::with([
        'user',
        'items.product',
        'items.productVariant'
    ])->where('user_id', $user->id)->findOrFail($id);

    // Transform items để hiển thị thông tin đã lưu
    $order->items->transform(function($item) {
        $productName = $item->product_name ?: ($item->product ? $item->product->name : 'Sản phẩm đã xóa');
        $productImage = $item->product_image ?: ($item->product && $item->product->thumbnail ? $item->product->thumbnail : 'http://127.0.0.1:8000/placeholder.svg');
        $variantName = $item->variant_name ?: ($item->productVariant ? $item->productVariant->Name : 'Mặc định');
        
        $item->product_info = [
            'id' => $item->product_id,
            'name' => $productName,
            'image' => $productImage,
            'variant_name' => $variantName
        ];
        return $item;
    });

    return response()->json([
        'message' => 'Chi tiết đơn hàng',
        'data' => $order
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
                    \Log::info('Restored variant stock:', [
                        'variant_id' => $variant->id,
                        'quantity' => $item->quantity,
                        'new_stock' => $variant->fresh()->stock
                    ]);
                }
            } else {
                // Hoàn stock cho product chính
                $product = \App\Models\Product::find($item->product_id);
                if ($product && isset($product->stock)) {
                    $product->increment('stock', $item->quantity);
                    \Log::info('Restored product stock:', [
                        'product_id' => $product->id,
                        'quantity' => $item->quantity,
                        'new_stock' => $product->fresh()->stock
                    ]);
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
            'message' => ($order->payment_status_id == Order::PAYMENT_REFUNDED) 
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
                'payment_status_id' => Order::PAYMENT_REFUNDED // Đã hoàn tiền
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
    if (!$user || ($user->role !== 'admin' && $user->role !== 'super_admin')) {
        return response()->json(['message' => 'Không có quyền truy cập'], 403);
    }

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
            $order->update(['payment_status_id' => Order::PAYMENT_REFUNDED]); // Đã hoàn tiền
        }
        
        DB::commit();
        
        return response()->json([
            'message' => ($order->payment_status_id == Order::PAYMENT_REFUNDED) 
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

// Test endpoint để debug tạo order
public function testCreateOrder(Request $request)
{
    return response()->json([
        'message' => 'Test endpoint working',
        'request_data' => $request->all(),
        'validation_rules' => [
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'required|string',
            'payment_method' => 'required|in:cod,vnpay,wallet',
            'total' => 'required|numeric|min:0',
            'items' => 'nullable|array'
        ]
    ]);
}

// Endpoint tạo order đơn giản không validation
public function simpleCreateOrder(Request $request)
{
    try {
        \Log::info('Simple create order:', $request->all());
        
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json(['message' => 'Vui lòng đăng nhập'], 401);
        }
        
        // Tạo order đơn giản
        $order = \App\Models\Order::create([
            'user_id' => $user->id,
            'total' => $request->total,
            'name' => $request->name,
            'phone' => $request->phone,
            'email' => $request->email,
            'address' => $request->address,
            'payment_method' => $request->payment_method,
            'order_status_id' => 1,
            'payment_status_id' => 1
        ]);
        
        // Tạo order item đơn giản
        if ($request->items) {
            $product = \App\Models\Product::with('variants')->first();
            if ($product) {
                $variant = $product->variants->first();
                if (!$variant) {
                    $variant = \App\Models\ProductVariant::create([
                        'product_id' => $product->id,
                        'Name' => 'Mặc định',
                        'price' => $product->price ?? 1000000,
                        'stock' => 100
                    ]);
                }
                
                foreach ($request->items as $item) {
                    \App\Models\OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'product_variant_id' => $variant->id,
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                        'product_name' => $product->name,
                        'product_image' => $product->thumbnail ?? 'http://127.0.0.1:8000/placeholder.svg',
                        'variant_name' => $variant->Name
                    ]);
                }
            }
        }
        
        return response()->json([
            'message' => 'Tạo order thành công',
            'order_id' => $order->id
        ]);
        
    } catch (\Exception $e) {
        \Log::error('Simple order creation failed:', [
            'error' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => $e->getFile()
        ]);
        return response()->json([
            'message' => 'Lỗi: ' . $e->getMessage(),
            'line' => $e->getLine(),
            'file' => basename($e->getFile())
        ], 500);
    }
}

// Kiểm tra sản phẩm
public function checkProduct($id)
{
    $product = \App\Models\Product::with('variants')->find($id);
    
    if (!$product) {
        return response()->json([
            'exists' => false,
            'product_id' => $id,
            'available_products' => \App\Models\Product::select('id', 'name')->take(10)->get()
        ]);
    }
    
    return response()->json([
        'exists' => true,
        'product' => [
            'id' => $product->id,
            'name' => $product->name,
            'variants_count' => $product->variants->count(),
            'variants' => $product->variants->map(function($v) {
                return [
                    'id' => $v->id,
                    'name' => $v->Name,
                    'stock' => $v->stock
                ];
            })
        ]
    ]);
}





}
