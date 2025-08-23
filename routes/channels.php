<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('chat.{chatId}', function ($user, $chatId) {
    $chat = \App\Models\Chat::find($chatId);
    return $chat && ($chat->user_id === $user->id || $chat->admin_id === $user->id || in_array($user->role, ['admin', 'super_admin']));
});