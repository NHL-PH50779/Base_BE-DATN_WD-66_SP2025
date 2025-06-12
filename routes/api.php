<?php

use Illuminate\Support\Facades\Route;
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

// Brand & Category
Route::apiResource('brands', BrandController::class)->only(['index', 'store']);
Route::apiResource('categories', CategoryController::class)->only(['index', 'store']);