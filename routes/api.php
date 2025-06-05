<?php
// routes/api.php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

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
    
    // Routes cho cả client và admin
    Route::get('/protected', function () {
        return response()->json([
            'message' => 'Đây là route được bảo vệ',
            'user_role' => auth()->user()->role
        ]);
    });
});