<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ReturnRequestController;
use App\Http\Controllers\Api\RefundController;
use App\Http\Controllers\Api\ProductController;

use App\Http\Controllers\Api\ProductVariantController;
use App\Http\Controllers\Api\AttributeController;
use App\Http\Controllers\Api\AttributeValueController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\CategoryController;
use Illuminate\Http\Request;
// Lấy thông tin user đăng nhập
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Return requests & Refunds
Route::apiResource('return_requests', ReturnRequestController::class);
Route::apiResource('refunds', RefundController::class);

// Product routes
Route::get('products/search', [ProductController::class, 'search']);
Route::apiResource('products', ProductController::class);
Route::get('/products/trashed', [ProductController::class, 'trashed']);
Route::put('/products/restore/{id}', [ProductController::class, 'restore']);
Route::put('/products/toggle-active/{id}', [ProductController::class, 'toggleActive']);

// Product variants
Route::get('products/{productId}/variants', [ProductVariantController::class, 'index']);
Route::post('products/{productId}/variants', [ProductVariantController::class, 'store']);
Route::delete('variants/{id}', [ProductVariantController::class, 'destroy']);

// Attribute routes
Route::apiResource('attributes', AttributeController::class);
Route::apiResource('attribute-values', AttributeValueController::class);
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

    
});

// Các routes cho sản phẩm không cần xác thực 

// ql tintuc
Route::apiResource('news', \App\Http\Controllers\Api\NewsController::class);

// Brand & Category
Route::apiResource('brands', BrandController::class)->only(['index', 'store']);
Route::apiResource('categories', CategoryController::class)->only(['index', 'store']);