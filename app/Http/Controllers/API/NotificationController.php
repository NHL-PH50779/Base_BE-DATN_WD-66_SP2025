<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index()
    {
        $user = auth('sanctum')->user();
        
        if (!$user) {
            return response()->json(['notifications' => []]);
        }
        
        $notifications = Notification::whereNull('user_id')
            ->orWhere('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['notifications' => $notifications]);
    }

    public function markAsRead($id)
    {
        $notification = Notification::findOrFail($id);
        $notification->update(['is_read' => true]);

        return response()->json(['message' => 'Đã đánh dấu đã đọc']);
    }

    public function markAllAsRead()
    {
        $user = auth('sanctum')->user();
        
        if (!$user) {
            return response()->json(['message' => 'Chưa đăng nhập'], 401);
        }
        
        Notification::whereNull('user_id')
            ->orWhere('user_id', $user->id)
            ->update(['is_read' => true]);

        return response()->json(['message' => 'Đã đánh dấu tất cả đã đọc']);
    }

    public function getUnreadCount()
    {
        $user = auth('sanctum')->user();
        
        if (!$user) {
            return response()->json(['count' => 0]);
        }
        
        $count = Notification::where('is_read', false)
            ->where(function($query) use ($user) {
                $query->whereNull('user_id')
                      ->orWhere('user_id', $user->id);
            })
            ->count();

        return response()->json(['count' => $count]);
    }
}