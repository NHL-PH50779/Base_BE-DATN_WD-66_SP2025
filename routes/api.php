<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\{
    AuthController,
    ProductController,
    ProductVariantController,
    AttributeController,
    AttributeValueController,
    BrandController,
    CategoryController,
    ReturnRequestController,
    RefundController,
    NewsController,
    CartController,
    OrderController,
    BannerController,
    UploadController,
    UserController,
    DashboardController,
    NotificationController,
    CommentController,
    VoucherController,
    WishlistController,
    FlashSaleController,
    FlashSalePurchaseController,
    PaymentController,
    WithdrawRequestController
};

// ✅ Public Routes (Không cần đăng nhập)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/send-otp', [AuthController::class, 'sendOtp']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

Route::get('/products/search', [ProductController::class, 'search']);
Route::get('/products', [ProductController::class, 'index'])->middleware('cache.headers:public;max_age=300');
Route::get('/products/{id}', [ProductController::class, 'show']);

Route::get('/brands', [BrandController::class, 'index'])->middleware('cache.headers:public;max_age=600');
Route::get('/brands/{id}', [BrandController::class, 'show']);
Route::get('/categories', [CategoryController::class, 'index'])->middleware('cache.headers:public;max_age=600');
Route::get('/categories/{id}', [CategoryController::class, 'show']);
Route::get('/products-by-brand/{id}', [ProductController::class, 'getByBrand']);
Route::get('/products-by-category/{id}', [ProductController::class, 'getByCategory']);

Route::get('/attributes', [AttributeController::class, 'index']);
Route::get('/attribute-values', [AttributeValueController::class, 'index']);

Route::get('/news', [NewsController::class, 'index']);
Route::get('/news/{id}', [NewsController::class, 'show']);

Route::get('/comments', [CommentController::class, 'index']);
Route::get('/products/{id}/rating-stats', [CommentController::class, 'getProductRatingStats']);
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::get('/categories', [CategoryController::class, 'index']);

// Flash Sale - public routes
Route::prefix('flash-sale')->group(function () {
    Route::get('/current', [FlashSaleController::class, 'current']);
    Route::get('/upcoming', [FlashSaleController::class, 'upcoming']);
    Route::post('/check-product', [FlashSaleController::class, 'checkProduct']);
    Route::get('/{id}/stats', [FlashSaleController::class, 'stats']);
});

Route::post('/vouchers/validate', [VoucherController::class, 'validateVoucher']);
Route::get('/vouchers/available', [VoucherController::class, 'getAvailableVouchers']);

// Guest checkout - public
Route::post('/orders', [OrderController::class, 'createOrder']);

// VNPay routes - public
Route::post('/vnpay/create-payment', [\App\Http\Controllers\API\VNPayController::class, 'createPayment']);
Route::get('/vnpay/return', [\App\Http\Controllers\API\VNPayController::class, 'vnpayReturn']);
Route::get('/vnpay/ipn', [\App\Http\Controllers\API\VNPayController::class, 'vnpayIPN']);


// ✅ Client Routes - Bật auth
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Upload
    Route::post('/upload', [UploadController::class, 'uploadImage']);

    // Giỏ hàng
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart', [CartController::class, 'store']);
    Route::put('/cart/{id}', [CartController::class, 'update']);
    Route::delete('/cart/{id}', [CartController::class, 'destroy']);

    // Profile
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::put('/change-password', [AuthController::class, 'changePassword']);

    // Thông báo
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'getUnreadCount']);
    Route::put('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::put('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);

    // Comments
    Route::post('/comments', [CommentController::class, 'store']);

    // Wishlist
    Route::get('/wishlist', [WishlistController::class, 'index']);
    Route::post('/wishlist/toggle', [WishlistController::class, 'toggle']);
    Route::post('/wishlist/check', [WishlistController::class, 'check']);
    Route::delete('/wishlist/{id}', [WishlistController::class, 'destroy']);

    // Flash Sale - moved to public
    // Route::prefix('flash-sale')->group(function () {
    //     Route::get('/current', [FlashSaleController::class, 'current']);
    //     Route::get('/upcoming', [FlashSaleController::class, 'upcoming']);
    //     Route::post('/check-product', [FlashSaleController::class, 'checkProduct']);
    //     Route::get('/{id}/stats', [FlashSaleController::class, 'stats']);
    //     Route::post('/purchase', [FlashSalePurchaseController::class, 'purchase']);
    //     Route::post('/validate-price', [FlashSalePurchaseController::class, 'validateFlashPrice']);
    // });

    // Đặt hàng
    Route::post('/orders/checkout', [OrderController::class, 'checkout']);
    Route::get('/my-orders', [OrderController::class, 'myOrders']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::put('/orders/{id}/cancel', [OrderController::class, 'cancelOrder']);
    Route::put('/orders/{id}/complete', [OrderController::class, 'confirmComplete']);
    Route::post('/orders/{id}/refund-request', [OrderController::class, 'requestRefund']);

    // VNPay - sử dụng VNPayController thống nhất
    Route::post('/orders/{id}/cancel-request', [OrderController::class, 'cancelRequest']);
    
    // Ví tiền
    Route::get('/wallet', [\App\Http\Controllers\API\WalletController::class, 'getWallet']);
    Route::get('/wallet/transactions', [\App\Http\Controllers\API\WalletController::class, 'getTransactions']);
    Route::post('/wallet/deposit', [\App\Http\Controllers\API\WalletController::class, 'deposit']);
    Route::post('/wallet/withdraw', [\App\Http\Controllers\API\WalletController::class, 'withdraw']);

    // Return requests và Withdraw requests
    Route::apiResource('return-requests', ReturnRequestController::class);
    Route::post('return-requests/{id}/approve', [ReturnRequestController::class, 'approve']);
    Route::post('return-requests/{id}/reject', [ReturnRequestController::class, 'reject']);
    
    Route::apiResource('withdraw-requests', WithdrawRequestController::class);
    Route::post('withdraw-requests/{id}/approve', [WithdrawRequestController::class, 'approve']);
    Route::post('withdraw-requests/{id}/reject', [WithdrawRequestController::class, 'reject']);
});

// ✅ Admin Routes
Route::get('/test', function () {
    return response()->json(['message' => 'API working', 'time' => now()]);
});

// Test routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/test/vnpay-refund/{orderId}', [\App\Http\Controllers\API\TestController::class, 'testVNPayRefund']);
    Route::get('/test/withdraw-request', [\App\Http\Controllers\API\TestController::class, 'testWithdrawRequest']);
});

// Test withdraw endpoint (no auth)
Route::get('/test/withdraw-table', function() {
    try {
        $count = \App\Models\WithdrawRequest::count();
        return response()->json([
            'message' => 'Withdraw requests table exists',
            'count' => $count,
            'table_exists' => true
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Error accessing withdraw requests table',
            'error' => $e->getMessage(),
            'table_exists' => false
        ], 500);
    }
});

// Debug wallet endpoint
Route::get('/debug/wallet/{email}', function($email) {
    try {
        $user = \App\Models\User::where('email', $email)->first();
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }
        
        $wallet = $user->wallet;
        if (!$wallet) {
            $wallet = $user->wallet()->create(['balance' => 0]);
        }
        
        return response()->json([
            'user' => $user->only(['id', 'name', 'email']),
            'wallet' => [
                'balance' => $wallet->balance,
                'formatted_balance' => number_format($wallet->balance, 0, ',', '.') . ' VND'
            ],
            'transactions_count' => $wallet->transactions()->count()
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});

// Test mail endpoint
Route::get('/test-mail', function () {
    try {
        $otp = rand(100000, 999999);
        \Mail::to('test@example.com')->send(new \App\Mail\OtpMail($otp));
        return response()->json([
            'message' => 'Test mail sent successfully',
            'otp' => $otp,
            'mail_config' => [
                'mailer' => config('mail.default'),
                'host' => config('mail.mailers.smtp.host'),
                'port' => config('mail.mailers.smtp.port'),
                'username' => config('mail.mailers.smtp.username'),
                'from' => config('mail.from')
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Mail test failed',
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});

// Debug OTP flow
Route::post('/debug-otp', function (\Illuminate\Http\Request $request) {
    $email = $request->input('email', 'test@example.com');
    
    try {
        // Kiểm tra user tồn tại
        $existingUser = \App\Models\User::where('email', $email)->first();
        
        $result = [
            'email' => $email,
            'user_exists' => !!$existingUser,
            'mail_config' => [
                'mailer' => config('mail.default'),
                'host' => config('mail.mailers.smtp.host'),
                'username' => config('mail.mailers.smtp.username'),
                'from' => config('mail.from')
            ]
        ];
        
        if ($existingUser) {
            $result['message'] = 'Email đã tồn tại - không thể gửi OTP';
            return response()->json($result, 422);
        }
        
        // Tạo OTP
        $otp = rand(100000, 999999);
        \App\Models\UserOtp::updateOrCreate(
            ['email' => $email],
            ['otp' => $otp, 'expires_at' => now()->addMinutes(5)]
        );
        
        // Gửi mail
        \Mail::to($email)->send(new \App\Mail\OtpMail($otp));
        
        $result['otp'] = $otp;
        $result['message'] = 'OTP sent successfully';
        
        return response()->json($result);
        
    } catch (\Exception $e) {
        return response()->json([
            'email' => $email,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});

Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
    // Users
    Route::get('/users', function () {
        return response()->json(['users' => \App\Models\User::all()]);
    });
    Route::apiResource('users', UserController::class);

    // Products
    Route::get('/products', [ProductController::class, 'index']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);
    Route::get('/products/trashed', [ProductController::class, 'trashed']);
    Route::put('/products/restore/{id}', [ProductController::class, 'restore']);
    Route::delete('/products/force-delete/{id}', [ProductController::class, 'forceDelete']);
    Route::put('/products/toggle-active/{id}', [ProductController::class, 'toggleActive']);

    // Product Variants
    Route::get('/products/{productId}/variants', [ProductVariantController::class, 'index']);
    Route::post('/products/{productId}/variants', [ProductVariantController::class, 'store']);
    Route::put('/variants/{id}', [ProductVariantController::class, 'update']);
    Route::delete('/variants/{id}', [ProductVariantController::class, 'destroy']);

    // Brands & Categories
    Route::get('/brands/{id}', [BrandController::class, 'show']);
    Route::post('/brands', [BrandController::class, 'store']);
    Route::put('/brands/{id}', [BrandController::class, 'update']);
    Route::delete('/brands/{id}', [BrandController::class, 'destroy']);
    
    Route::get('/categories/{id}', [CategoryController::class, 'show']);
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{id}', [CategoryController::class, 'update']);
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

    // Attributes
    Route::apiResource('attributes', AttributeController::class);
    Route::apiResource('attribute-values', AttributeValueController::class);

    // News
    Route::post('/news', [NewsController::class, 'store']);
    Route::put('/news/{id}', [NewsController::class, 'update']);
    Route::delete('/news/{id}', [NewsController::class, 'destroy']);

    // Banners
    Route::apiResource('banners', BannerController::class);

    // Dashboard
    Route::get('/dashboard/stats', [DashboardController::class, 'getStats']);
    Route::get('/stats', [DashboardController::class, 'getStats']);

    // Orders
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{id}', [OrderController::class, 'adminShow']);
    Route::put('/orders/{id}/status', [OrderController::class, 'updateStatus']);
    Route::put('/orders/{id}/order-status', [OrderController::class, 'updateOrderStatus']);
    Route::put('/orders/{id}/payment-status', [OrderController::class, 'updatePaymentStatus']);
    Route::post('/orders/{id}/approve-cancel', [OrderController::class, 'approveCancel']);
    
    // Notifications
    Route::get('/notifications', [NotificationController::class, 'adminIndex']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'adminGetUnreadCount']);
    Route::put('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::put('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
    
    // Comments
    Route::get('/comments', [CommentController::class, 'adminIndex']);
    Route::get('/comments/{id}', [CommentController::class, 'show']);
    Route::put('/comments/{id}/approve', [CommentController::class, 'approve']);
    Route::put('/comments/{id}/status', [CommentController::class, 'updateStatus']);
    Route::delete('/comments/{id}', [CommentController::class, 'destroy']);

    // Flash Sales
    Route::prefix('flash-sales')->group(function () {
        Route::get('/', [FlashSaleController::class, 'adminIndex']);
        Route::post('/', [FlashSaleController::class, 'adminStore']);
        Route::get('/{id}', [FlashSaleController::class, 'show']);
        Route::put('/{id}', [FlashSaleController::class, 'adminUpdate']);
        Route::delete('/{id}', [FlashSaleController::class, 'adminDestroy']);
    });
    
    // Return Requests & Withdraw Requests (Admin)
    Route::get('/return-requests', [ReturnRequestController::class, 'index']);
    Route::get('/return-requests/{id}', [ReturnRequestController::class, 'show']);
    Route::post('/return-requests/{id}/approve', [ReturnRequestController::class, 'approve']);
    Route::post('/return-requests/{id}/reject', [ReturnRequestController::class, 'reject']);
    
    Route::get('/withdraw-requests', [WithdrawRequestController::class, 'index']);
    Route::get('/withdraw-requests/{id}', [WithdrawRequestController::class, 'show']);
    Route::post('/withdraw-requests/{id}/approve', [WithdrawRequestController::class, 'approve']);
    Route::post('/withdraw-requests/{id}/reject', [WithdrawRequestController::class, 'reject']);
    
    // Cancel Requests
    Route::get('/cancel-requests', [OrderController::class, 'getCancelRequests']);
    Route::post('/orders/{id}/reject-cancel', [OrderController::class, 'rejectCancel']);
    
    // Process Refund
    Route::post('/orders/{id}/process-refund', [OrderController::class, 'processRefund']);
    
    // Vouchers
    Route::apiResource('vouchers', VoucherController::class);
    
    // Test endpoint
    Route::get('/orders/{id}/test-refund', [OrderController::class, 'testRefund']);
    Route::get('/test-auth', function() {
        $user = auth('sanctum')->user();
        return response()->json([
            'authenticated' => !!$user,
            'user' => $user ? $user->only(['id', 'name', 'email']) : null,
            'wallet_balance' => $user && $user->wallet ? $user->wallet->balance : 'No wallet'
        ]);
    });
    
    Route::post('/create-test-order', function() {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        
        $order = App\Models\Order::create([
            'user_id' => $user->id,
            'total' => 150000,
            'payment_method' => 'vnpay',
            'payment_status_id' => 2,
            'order_status_id' => 1,
            'name' => $user->name,
            'phone' => $user->phone ?? '0123456789',
            'address' => $user->address ?? 'Test Address'
        ]);
        
        return response()->json([
            'message' => 'Tạo đơn hàng test thành công',
            'order' => $order
        ]);
    });
});

// Chat routes
use App\Http\Controllers\API\ChatController;
use App\Http\Controllers\API\ChatUserController;
use App\Http\Controllers\API\ChatAdminController;

Route::middleware('auth:sanctum')->group(function () {
    // AI Chatbot
    Route::post('/chat', [ChatController::class, 'chat']);
    
    // User chat with admin
    Route::post('/chat/start', function() {
        try {
            $user = auth('sanctum')->user();
            
            // Tìm hoặc tạo chat
            $chat = DB::table('chats')->where('user_id', $user->id)->first();
            
            if (!$chat) {
                $chatId = DB::table('chats')->insertGetId([
                    'user_id' => $user->id,
                    'admin_id' => null,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                
                $chat = (object)[
                    'id' => $chatId,
                    'user_id' => $user->id,
                    'admin_id' => null
                ];
            }
            
            return response()->json([
                'data' => [
                    'id' => $chat->id,
                    'user_id' => $chat->user_id,
                    'admin_id' => $chat->admin_id,
                    'admin' => $chat->admin_id ? ['id' => $chat->admin_id] : null
                ]
            ]);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    });
    Route::get('/chat/{chat}/messages', [ChatUserController::class, 'messages']);
    Route::post('/chat/{chat}/send', function($chat) {
        try {
            $user = auth('sanctum')->user();
            $message = request('message');
            
            // Kiểm tra chat có tồn tại không
            $chatExists = DB::table('chats')->where('id', $chat)->where('user_id', $user->id)->exists();
            if (!$chatExists) {
                return response()->json(['error' => 'Chat not found'], 404);
            }
            
            // Thêm tin nhắn mới
            $msgId = DB::table('chat_messages')->insertGetId([
                'chat_id' => $chat,
                'sender_id' => $user->id,
                'message' => $message,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            // Cập nhật thời gian của chat
            DB::table('chats')->where('id', $chat)->update(['updated_at' => now()]);
            
            return response()->json([
                'data' => [
                    'id' => $msgId,
                    'sender_id' => $user->id,
                    'message' => $message,
                    'created_at' => now()->toISOString(),
                    'sender' => ['id' => $user->id, 'name' => $user->name]
                ]
            ], 201);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    });
    Route::post('/chat/{chat}/read', [ChatUserController::class, 'markRead']);
    
    // Admin chat management - Simple endpoints
    Route::get('/admin/chats', function() {
        try {
            $chats = DB::table('chats')
                ->join('users', 'chats.user_id', '=', 'users.id')
                ->leftJoin('users as admins', 'chats.admin_id', '=', 'admins.id')
                ->leftJoin(DB::raw('(SELECT chat_id, COUNT(*) as messages_count FROM chat_messages GROUP BY chat_id) as msg_counts'), 'chats.id', '=', 'msg_counts.chat_id')
                ->select(
                    'chats.id',
                    'chats.user_id', 
                    'chats.admin_id',
                    'chats.updated_at',
                    'users.name as user_name',
                    'users.email as user_email',
                    'admins.name as admin_name',
                    DB::raw('COALESCE(msg_counts.messages_count, 0) as messages_count')
                )
                ->orderBy('chats.updated_at', 'desc')
                ->get();
                
            $formatted = $chats->map(function($chat) {
                return [
                    'id' => $chat->id,
                    'user' => [
                        'id' => $chat->user_id,
                        'name' => $chat->user_name,
                        'email' => $chat->user_email
                    ],
                    'admin' => $chat->admin_id ? [
                        'id' => $chat->admin_id,
                        'name' => $chat->admin_name
                    ] : null,
                    'messages_count' => (int)$chat->messages_count,
                    'updated_at' => $chat->updated_at
                ];
            });
            
            return response()->json(['data' => $formatted]);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    });
    
    Route::post('/admin/chats/{id}/claim', function($id) {
        try {
            $user = auth('sanctum')->user();
            DB::table('chats')->where('id', $id)->update(['admin_id' => $user->id]);
            
            $chat = DB::table('chats')
                ->join('users', 'chats.user_id', '=', 'users.id')
                ->leftJoin('users as admins', 'chats.admin_id', '=', 'admins.id')
                ->where('chats.id', $id)
                ->select(
                    'chats.id',
                    'chats.user_id',
                    'chats.admin_id', 
                    'users.name as user_name',
                    'admins.name as admin_name'
                )
                ->first();
                
            return response()->json([
                'data' => [
                    'id' => $chat->id,
                    'user' => ['id' => $chat->user_id, 'name' => $chat->user_name],
                    'admin' => ['id' => $chat->admin_id, 'name' => $chat->admin_name]
                ]
            ]);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    });
    
    Route::get('/admin/chats/{id}/messages', function($id) {
        try {
            $messages = DB::table('chat_messages')
                ->join('users', 'chat_messages.sender_id', '=', 'users.id')
                ->where('chat_messages.chat_id', $id)
                ->select(
                    'chat_messages.id',
                    'chat_messages.sender_id',
                    'chat_messages.message',
                    'chat_messages.created_at',
                    'users.name as sender_name'
                )
                ->orderBy('chat_messages.id')
                ->get();
                
            $formatted = $messages->map(function($msg) {
                return [
                    'id' => $msg->id,
                    'sender_id' => $msg->sender_id,
                    'message' => $msg->message,
                    'created_at' => $msg->created_at,
                    'sender' => [
                        'id' => $msg->sender_id,
                        'name' => $msg->sender_name
                    ]
                ];
            });
            
            return response()->json(['data' => $formatted]);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    });
    
    Route::post('/admin/chats/{id}/send', function($id) {
        try {
            $user = auth('sanctum')->user();
            $message = request('message');
            
            // Thêm tin nhắn mới
            $msgId = DB::table('chat_messages')->insertGetId([
                'chat_id' => $id,
                'sender_id' => $user->id,
                'message' => $message,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            // Cập nhật thời gian của chat
            DB::table('chats')->where('id', $id)->update(['updated_at' => now()]);
            
            return response()->json([
                'data' => [
                    'id' => $msgId,
                    'sender_id' => $user->id,
                    'message' => $message,
                    'created_at' => now()->toISOString(),
                    'sender' => ['id' => $user->id, 'name' => $user->name]
                ]
            ], 201);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    });
    
    Route::post('/admin/chats/{id}/read', function($id) {
        return response()->json(['message' => 'ok']);
    });
});