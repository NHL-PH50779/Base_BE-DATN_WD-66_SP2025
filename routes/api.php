<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ReturnRequestController;
use App\Http\Controllers\Api\RefundController;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// 📦 Route API cho trả hàng và hoàn tiền
Route::apiResource('return_requests', ReturnRequestController::class);
Route::apiResource('refunds', RefundController::class);
