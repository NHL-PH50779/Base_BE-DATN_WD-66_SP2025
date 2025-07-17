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
    PaymentController
};

// ✅ Public Routes (Không cần đăng nhập)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
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
    Route::post('/orders', [OrderController::class, 'createOrder']);
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

    // Return requests
    Route::apiResource('return_requests', ReturnRequestController::class);
    Route::apiResource('refunds', RefundController::class);
});

// ✅ Admin Routes
Route::get('/test', function () {
    return response()->json(['message' => 'API working', 'time' => now()]);
});

// Route::middleware(['auth:api', 'admin'])->prefix('admin')->group(function () {
Route::prefix('admin')->group(function () {
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
    
    // Return Requests & Refunds
    Route::apiResource('return-requests', ReturnRequestController::class);
    Route::apiResource('refunds', RefundController::class);
    
    // Cancel Requests
    Route::get('/cancel-requests', [OrderController::class, 'getCancelRequests']);
    Route::post('/orders/{id}/reject-cancel', [OrderController::class, 'rejectCancel']);
});