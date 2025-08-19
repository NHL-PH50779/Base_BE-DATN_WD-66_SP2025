<?php

namespace App\Http\Controllers\API;

use App\Events\ChatMessageSent;
use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\ChatMessage;
use Illuminate\Http\Request;

class ChatUserController extends Controller
{
    // USER: Tạo (hoặc lấy) một cuộc chat của chính mình
    public function start(Request $request)
    {
        $chat = Chat::firstOrCreate(['user_id' => $request->user()->id]);
        return response()->json(['data' => $chat->load('admin')]);
    }

    // USER: Gửi tin nhắn
    public function send(Request $request, Chat $chat)
    {
        $request->validate(['message' => 'required|string|max:3000']);
        $user = $request->user();

        if ($chat->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $msg = $chat->messages()->create([
            'sender_id' => $user->id,
            'message'   => $request->message,
        ]);

        broadcast(new ChatMessageSent($msg))->toOthers();

        return response()->json(['data' => $msg->load('sender')], 201);
    }

    // USER: Lấy tin nhắn
    public function messages(Request $request, Chat $chat)
    {
        $user = $request->user();
        if ($chat->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $messages = $chat->messages()->with('sender:id,name')->orderBy('id')->get();
        return response()->json(['data' => $messages]);
    }

    // USER: Đánh dấu đã đọc
    public function markRead(Request $request, Chat $chat)
    {
        $user = $request->user();
        if ($chat->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        ChatMessage::where('chat_id', $chat->id)
            ->where('sender_id', '!=', $user->id)
            ->update(['is_read' => true]);

        return response()->json(['message' => 'ok']);
    }
}
