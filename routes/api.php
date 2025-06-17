<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ReturnRequestController;
use App\Http\Controllers\Api\RefundController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OrderStatusController;
use App\Http\Controllers\Api\PaymentStatusController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\NewsCommentController;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// 📦 Route API cho trả hàng và hoàn tiền
Route::apiResource('return_requests', ReturnRequestController::class);
Route::apiResource('refunds', RefundController::class);

// Các route cho quản lý đơn hàng
Route::apiResource('orders', OrderController::class);
Route::apiResource('order-statuses', OrderStatusController::class);
Route::apiResource('payment-statuses', PaymentStatusController::class);

//Các route cho bình luận
Route::apiResource('comments', CommentController::class);
Route::apiResource('news-comments', NewsCommentController::class);

