<?php

// app/Events/ChatMessageSent.php
namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class ChatMessageSent implements ShouldBroadcast
{
    use SerializesModels;

    public function __construct(public ChatMessage $message) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('chat.'.$this->message->chat_id)];
    }

    public function broadcastWith(): array
    {
        return [
            'id'        => $this->message->id,
            'chat_id'   => $this->message->chat_id,
            'sender_id' => $this->message->sender_id,
            'message'   => $this->message->message,
            'is_read'   => (bool)$this->message->is_read,
            'created_at'=> $this->message->created_at?->toISOString(),
        ];
    }
}