<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ReturnRequestController;
use App\Http\Controllers\Api\RefundController;
use App\Http\Controllers\Api\ProductController;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


Route::apiResource('return_requests', ReturnRequestController::class);
Route::apiResource('refunds', RefundController::class);


Route::get('products/search', [ProductController::class, 'search']);
Route::apiResource('products', ProductController::class);

// Xem sản phẩm đã xóa mềm
Route::get('/products/trashed', [ProductController::class, 'trashed']);
 // Lấy cả sản phẩm đã xóa mềm

// Khôi phục sản phẩm đã xóa
Route::put('/products/restore/{id}', [ProductController::class, 'restore']);

// Bật/tắt trạng thái hiển thị (is_active)
Route::put('/products/toggle-active/{id}', [ProductController::class, 'toggleActive']);

// Thêm biến thể sản phẩm

Route::post('/products/{productId}/variants', [ProductController::class, 'addVariant']);



