<?php

namespace App\Http\Controllers\API;

use App\Events\ChatMessageSent;
use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\ChatMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ChatAdminController extends Controller
{
    // ADMIN: Danh sách chat (mới nhất trước)
    public function listForAdmin(Request $request)
    {
        $user = $request->user();
        if (!in_array($user->role, ['admin','super_admin'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $chats = Cache::remember('admin_chats_' . $user->id, 300, function () {
            return Chat::with(['user:id,name,email', 'admin:id,name'])
                ->withCount('messages')
                ->latest('updated_at')
                ->limit(50)
                ->get();
        });

        return response()->json(['data' => $chats]);
    }

    // ADMIN: Gán mình vào chat (claim)
    public function claim(Request $request, Chat $chat)
    {
        $user = $request->user();
        if (!in_array($user->role, ['admin','super_admin'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $chat->update(['admin_id' => $user->id]);

        return response()->json([
            'data' => $chat->load('user:id,name', 'admin:id,name')
        ]);
    }

    // ADMIN: Gửi tin nhắn
    public function send(Request $request, Chat $chat)
    {
        $request->validate(['message' => 'required|string|max:3000']);

        $user = $request->user();
        if ($chat->admin_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $msg = $chat->messages()->create([
            'sender_id' => $user->id,
            'message'   => $request->message,
        ]);

        // Clear cache khi có tin nhắn mới
        Cache::forget('chat_messages_' . $chat->id);
        Cache::forget('admin_chats_' . $user->id);

        // Broadcast real-time
        broadcast(new \App\Events\ChatMessageSent($msg->load('sender'), $chat->id));

        return response()->json(['data' => $msg], 201);
    }

    // ADMIN: Lấy danh sách tin nhắn trong 1 chat
    public function messages(Request $request, Chat $chat)
    {
        $user = $request->user();
        if (!in_array($user->role, ['admin','super_admin'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $messages = Cache::remember('chat_messages_' . $chat->id, 120, function () use ($chat) {
            return $chat->messages()->with('sender:id,name')->orderBy('id')->limit(100)->get();
        });

        return response()->json(['data' => $messages]);
    }

    // ADMIN: Đánh dấu đã đọc
    public function markRead(Request $request, Chat $chat)
    {
        $user = $request->user();
        if (!in_array($user->role, ['admin','super_admin'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        ChatMessage::where('chat_id', $chat->id)
            ->where('sender_id', '!=', $user->id)
            ->update(['is_read' => true]);

        return response()->json(['message' => 'ok']);
    }
}