<?php

use Illuminate\Support\Facades\Route;
use App\Models\News;
use App\Http\Controllers\VNPayController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test-news', function () {
    try {
        $news = News::create([
            'title' => 'Test News',
            'description' => 'Test Description',
            'content' => 'Test Content',
            'thumbnail' => '',
            'is_active' => true,
            'published_at' => now()
        ]);
        
        return response()->json(['success' => true, 'data' => $news]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()]);
    }
});

Route::get('/test-list', function () {
    try {
        $news = News::all();
        return response()->json(['success' => true, 'data' => $news]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()]);
    }
});

// VNPay routes
Route::get('/vnpay/return', [VNPayController::class, 'vnpayReturn']);
Route::get('/vnpay/test', function () {
    return view('vnpay.test');
});

// Order status routes
Route::get('/orders/{id}', [\App\Http\Controllers\OrderStatusController::class, 'show']);