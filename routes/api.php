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
    CartController // Thêm CartController
};

// ✅ Route công khai (không cần đăng nhập)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/products/search', [ProductController::class, 'search']);
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);

Route::get('/brands', [BrandController::class, 'index']);
Route::get('/categories', [CategoryController::class, 'index']);

Route::get('/news', [NewsController::class, 'index']);
Route::get('/news/{id}', [NewsController::class, 'show']);

// ✅ Route cần xác thực (auth:sanctum)
Route::middleware('auth:sanctum')->group(function () {
    // Thông tin user đăng nhập
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Giỏ hàng
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart', [CartController::class, 'store']);
    Route::put('/cart/{id}', [CartController::class, 'update']);
    Route::delete('/cart/{id}', [CartController::class, 'destroy']);

    // Quản lý sản phẩm (yêu cầu đăng nhập)
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);
    Route::get('/products/trashed', [ProductController::class, 'trashed']);
    Route::put('/products/restore/{id}', [ProductController::class, 'restore']);
    Route::put('/products/toggle-active/{id}', [ProductController::class, 'toggleActive']);

    // Quản lý biến thể sản phẩm
    Route::get('/products/{productId}/variants', [ProductVariantController::class, 'index']);
    Route::post('/products/{productId}/variants', [ProductVariantController::class, 'store']);
    Route::delete('/variants/{id}', [ProductVariantController::class, 'destroy']);

    // Quản lý yêu cầu hoàn hàng và hoàn tiền
    Route::apiResource('return_requests', ReturnRequestController::class);
    Route::apiResource('refunds', RefundController::class);

    // ✅ Route chỉ dành cho admin
    Route::middleware('admin')->group(function () {
        // Quản lý tài khoản người dùng
        Route::get('/admin/users', function () {
            return response()->json(['users' => \App\Models\User::all()]);
        });

        // Quản lý thương hiệu và danh mục
        Route::post('/brands', [BrandController::class, 'store']);
        Route::post('/categories', [CategoryController::class, 'store']);

        // Quản lý thuộc tính sản phẩm
        Route::apiResource('attributes', AttributeController::class);
        Route::apiResource('attribute-values', AttributeValueController::class);

        // Quản lý tin tức
        Route::post('/news', [NewsController::class, 'store']);
        Route::put('/news/{id}', [NewsController::class, 'update']);
        Route::delete('/news/{id}', [NewsController::class, 'destroy']);
    });

    // Route kiểm tra token + quyền
    Route::get('/protected', function () {
        return response()->json([
            'message' => 'Đây là route được bảo vệ',
            'user_role' => auth()->user()->role
        ]);
    });
});