<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = Notification::whereNull('user_id')
            ->orWhere('user_id', auth()->id())
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
        Notification::whereNull('user_id')
            ->orWhere('user_id', auth()->id())
            ->update(['is_read' => true]);

        return response()->json(['message' => 'Đã đánh dấu tất cả đã đọc']);
    }

    public function getUnreadCount()
    {
        $count = Notification::where('is_read', false)
            ->where(function($query) {
                $query->whereNull('user_id')
                      ->orWhere('user_id', auth()->id());
            })
            ->count();

        return response()->json(['count' => $count]);
    }

    // Admin methods
    public function adminIndex()
    {
        $notifications = Notification::orderBy('created_at', 'desc')->get();
        return response()->json(['data' => $notifications]);
    }

    public function adminGetUnreadCount()
    {
        $count = Notification::where('is_read', false)->count();
        return response()->json(['count' => $count]);
    }
}