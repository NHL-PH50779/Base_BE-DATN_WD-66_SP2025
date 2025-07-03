<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\{
    WithdrawRequestController,
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
    DashboardController
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

// ✅ Client Routes (Yêu cầu đăng nhập)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Giỏ hàng
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart', [CartController::class, 'store']);
    Route::put('/cart/{id}', [CartController::class, 'update']);
    Route::delete('/cart/{id}', [CartController::class, 'destroy']);

    // Yêu cầu hoàn hàng và hoàn tiền
    Route::apiResource('return_requests', ReturnRequestController::class);
    Route::apiResource('refunds', RefundController::class);
    // Route cho admin duyệt yêu cầu hoàn hàng
    Route::post('return_requests/{id}/approve', [ReturnRequestController::class, 'approve']);
    Route::post('refunds/{id}/approve', [RefundController::class, 'approve']);
    Route::post('return_requests/{id}/reject', [ReturnRequestController::class, 'reject']);
    
    Route::apiResource('withdraw_requests', WithdrawRequestController::class);
    Route::post('withdraw_requests/{id}/approve', [WithdrawRequestController::class, 'approve']);
    Route::post('withdraw_requests/{id}/reject', [WithdrawRequestController::class, 'reject']);

    // Đặt hàng (cho phép client checkout và xem đơn hàng của họ)
    Route::post('/orders/checkout', [OrderController::class, 'checkout']);
    Route::get('/orders', [OrderController::class, 'myOrders']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
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

    // Quản lý giỏ hàng (Admin)
    Route::get('/admin/carts', [CartController::class, 'adminIndex']);
    Route::get('/admin/carts/{userId}', [CartController::class, 'adminShow']);
});



// ✅ Route fallback
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
