<?php
// routes/api.php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ReturnRequestController;
use App\Http\Controllers\Api\RefundController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\NewsController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/


// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    
    // Routes chỉ dành cho admin
    Route::middleware('admin')->group(function () {
        Route::get('/admin/users', function () {
            return response()->json(['users' => \App\Models\User::all()]);
        });
    });
  
    Route::get('/protected', function () {
        return response()->json([
            'message' => 'Đây là route được bảo vệ',
            'user_role' => auth()->user()->role
        ]);
    });

  
    Route::apiResource('return_requests', ReturnRequestController::class);
    Route::apiResource('refunds', RefundController::class);

    // Khôi phục sản phẩm đã xóa
    Route::put('/products/restore/{id}', [ProductController::class, 'restore']);

    // Bật/tắt trạng thái hiển thị (is_active)
    Route::put('/products/toggle-active/{id}', [ProductController::class, 'toggleActive']);

    // Thêm biến thể sản phẩm
    Route::post('/products/{productId}/variants', [ProductController::class, 'addVariant']);
});

// Các routes cho sản phẩm không cần xác thực 
Route::get('products/search', [ProductController::class, 'search']);
Route::get('/products/trashed', [ProductController::class, 'trashed']);
Route::apiResource('products', ProductController::class);
// ql tintuc
Route::apiResource('news', \App\Http\Controllers\Api\NewsController::class);

