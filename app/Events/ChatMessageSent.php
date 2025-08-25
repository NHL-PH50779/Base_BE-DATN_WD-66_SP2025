<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $chat_id;

    public function __construct($message, $chat_id)
    {
        $this->message = $message;
        $this->chat_id = $chat_id;
    }

    public function broadcastOn()
    {
        return new Channel('chat');
    }

    public function broadcastAs()
    {
        return 'message-sent';
    }

    public function broadcastWith()
    {
        return [
            'message' => $this->message,
            'chat_id' => $this->chat_id
        ];
    }
}