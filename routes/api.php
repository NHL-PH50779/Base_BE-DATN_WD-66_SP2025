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
    BannerController
};

// ✅ Public Routes (Không cần đăng nhập)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/products/search', [ProductController::class, 'search']);
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);

Route::get('/brands', [BrandController::class, 'index']);
Route::get('/categories', [CategoryController::class, 'index']);

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

    // Đặt hàng (cho phép client checkout và xem đơn hàng của họ)
    Route::post('/orders/checkout', [OrderController::class, 'checkout']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
});

// ✅ Admin Routes
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    // Quản lý tài khoản người dùng
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
    Route::delete('/variants/{id}', [ProductVariantController::class, 'destroy']);

    // Thương hiệu, danh mục
    Route::apiResource('brands', BrandController::class);
    Route::apiResource('categories', CategoryController::class);

    // Thuộc tính sản phẩm
    Route::apiResource('attributes', AttributeController::class);
    Route::apiResource('attribute-values', AttributeValueController::class);

    // Tin tức
    Route::post('/news', [NewsController::class, 'store']);
    Route::put('/news/{id}', [NewsController::class, 'update']);
    Route::delete('/news/{id}', [NewsController::class, 'destroy']);

    // Quản lý banner
    Route::apiResource('banners', BannerController::class);
});



// ✅ Route fallback
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
