<?php

use Illuminate\Support\Facades\Route;
use App\Models\Product;

Route::get('/', function () {
    return view('welcome');
});

// Test route for trashed products
Route::get('/test-trashed', function () {
    $products = Product::onlyTrashed()->with(['brand', 'category'])->get();
    return response()->json([
        'message' => 'Test trashed products',
        'count' => $products->count(),
        'data' => $products
    ]);
});