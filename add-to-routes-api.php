<?php

// THÊM VÀO CUỐI FILE routes/api.php

use App\Http\Controllers\API\ChatController;
use App\Http\Controllers\API\ChatUserController;
use App\Http\Controllers\API\ChatAdminController;

// Chat routes
Route::middleware('auth:sanctum')->group(function () {
    // AI Chatbot
    Route::post('/chat', [ChatController::class, 'chat']);
    
    // User chat with admin
    Route::post('/chat/start', [ChatUserController::class, 'start']);
    Route::get('/chat/{chat}/messages', [ChatUserController::class, 'messages']);
    Route::post('/chat/{chat}/send', [ChatUserController::class, 'send']);
    Route::post('/chat/{chat}/read', [ChatUserController::class, 'markRead']);
    
    // Admin chat management
    Route::get('/admin/chats', [ChatAdminController::class, 'listForAdmin']);
    Route::post('/admin/chats/{chat}/claim', [ChatAdminController::class, 'claim']);
    Route::post('/admin/chats/{chat}/send', [ChatAdminController::class, 'send']);
    Route::get('/admin/chats/{chat}/messages', [ChatAdminController::class, 'messages']);
    Route::post('/admin/chats/{chat}/read', [ChatAdminController::class, 'markRead']);
});