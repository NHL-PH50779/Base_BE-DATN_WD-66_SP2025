<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\{
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
    WishlistController
};

// ✅ Public Routes (Không cần đăng nhập)
Route::middleware('cors')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// Upload route (cần đăng nhập)
Route::middleware('auth:sanctum')->post('/upload', [UploadController::class, 'uploadImage']);

Route::get('/products/search', [ProductController::class, 'search']);
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);

Route::get('/brands', [BrandController::class, 'index']);
Route::get('/categories', [CategoryController::class, 'index']);

// Attributes - public routes
Route::get('/attributes', [AttributeController::class, 'index']);
Route::get('/attribute-values', [AttributeValueController::class, 'index']);

Route::get('/news', [NewsController::class, 'index']);
Route::get('/news/{id}', [NewsController::class, 'show']);

// Comments - public routes
Route::get('/comments', [CommentController::class, 'index']);
Route::get('/products/{id}/rating-stats', [CommentController::class, 'getProductRatingStats']);

// Voucher validation - public
Route::post('/vouchers/validate', [VoucherController::class, 'validateVoucher']);
Route::get('/vouchers/available', [VoucherController::class, 'getAvailableVouchers']);

// ✅ Client Routes (Yêu cầu đăng nhập)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Giỏ hàng
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart', [CartController::class, 'store']);
    Route::put('/cart/{id}', [CartController::class, 'update']);
    Route::delete('/cart/{id}', [CartController::class, 'destroy']);

    // Cập nhật thông tin cá nhân
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::put('/change-password', [AuthController::class, 'changePassword']);

    // Yêu cầu hoàn hàng và hoàn tiền
    Route::apiResource('return_requests', ReturnRequestController::class);
    Route::apiResource('refunds', RefundController::class);

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

    // Đặt hàng (cho phép client checkout và xem đơn hàng của họ)
    Route::post('/orders/checkout', [OrderController::class, 'checkout']);
    Route::post('/orders', [OrderController::class, 'createOrder']);
    Route::get('/my-orders', [OrderController::class, 'myOrders']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::put('/orders/{id}/cancel', [OrderController::class, 'cancelOrder']);
});

// ✅ Admin Routes
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    // Quản lý tài khoản người dùng
    Route::get('/users', function () {
        return response()->json(['users' => \App\Models\User::all()]);
    });
    Route::get('/admin/users', function () {
        return response()->json(['users' => \App\Models\User::all()]);
    });

    // Quản lý sản phẩm
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);
    Route::get('/products/trashed', [ProductController::class, 'trashed']);
    Route::put('/products/restore/{id}', [ProductController::class, 'restore']);
    Route::delete('/products/force-delete/{id}', [ProductController::class, 'forceDelete']);
    Route::put('/products/toggle-active/{id}', [ProductController::class, 'toggleActive']);

    // Biến thể sản phẩm
    Route::get('/products/{productId}/variants', [ProductVariantController::class, 'index']);
    Route::post('/products/{productId}/variants', [ProductVariantController::class, 'store']);
    Route::put('/variants/{id}', [ProductVariantController::class, 'update']);
    Route::delete('/variants/{id}', [ProductVariantController::class, 'destroy']);

    // Thương hiệu, danh mục - CRUD operations
    Route::post('/brands', [BrandController::class, 'store']);
    Route::put('/brands/{id}', [BrandController::class, 'update']);
    Route::delete('/brands/{id}', [BrandController::class, 'destroy']);
    
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{id}', [CategoryController::class, 'update']);
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

    // Thuộc tính sản phẩm
    Route::apiResource('attributes', AttributeController::class);
    Route::apiResource('attribute-values', AttributeValueController::class);

    // Tin tức
    Route::post('/news', [NewsController::class, 'store']);
    Route::put('/news/{id}', [NewsController::class, 'update']);
    Route::delete('/news/{id}', [NewsController::class, 'destroy']);

    // Quản lý banner
    Route::apiResource('banners', BannerController::class);

    // Dashboard stats
    Route::get('/dashboard/stats', [DashboardController::class, 'getStats']);

    // Quản lý người dùng (Admin)
    Route::apiResource('admin/users', UserController::class);
    Route::get('/users', [UserController::class, 'index']); // Backup route

    // Quản lý đơn hàng (Admin)
    Route::get('/admin/orders', [OrderController::class, 'index']);
    Route::get('/orders', [OrderController::class, 'index']); // Backup route
    Route::get('/admin/orders/{id}', [OrderController::class, 'adminShow']);
    Route::put('/admin/orders/{id}/status', [OrderController::class, 'updateStatus']);
    Route::put('/orders/{id}/status', [OrderController::class, 'updateStatus']); // Backup route
    Route::put('/admin/orders/{id}/order-status', [OrderController::class, 'updateOrderStatus']); // Chỉ cập nhật trạng thái đơn hàng

    // Quản lý yêu cầu hoàn hàng (Admin)
    Route::get('/admin/return-requests', [ReturnRequestController::class, 'index']);
    Route::put('/admin/return-requests/{id}', [ReturnRequestController::class, 'update']);

    // Thông báo admin
    Route::get('/admin/notifications', [NotificationController::class, 'index']);
    Route::put('/admin/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::put('/admin/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
    Route::get('/admin/notifications/unread-count', [NotificationController::class, 'getUnreadCount']);

    // Quản lý giỏ hàng (Admin)
    Route::get('/admin/carts', [CartController::class, 'adminIndex']);
    Route::get('/admin/carts/{userId}', [CartController::class, 'adminShow']);

    // Quản lý bình luận (Admin)
    Route::get('/admin/comments', [CommentController::class, 'adminIndex']);
    Route::put('/admin/comments/{id}/status', [CommentController::class, 'updateStatus']);
    Route::delete('/admin/comments/{id}', [CommentController::class, 'destroy']);

    // Quản lý voucher (Admin)
    Route::apiResource('vouchers', VoucherController::class);
});



// ✅ Route fallback
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
