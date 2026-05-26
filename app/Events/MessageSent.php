<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Message $message,
        public int $recipientId,
        public bool $silenced = false,
    ) {
        $message->load('sender');
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.'.$this->recipientId)];
    }

    public function broadcastAs(): string
    {
        return 'MessageSent';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => [
                'id' => $this->message->id,
                'conversation_id' => $this->message->conversation_id,
                'body' => $this->message->body,
                'image_url' => $this->message->image_url,
                'sender_id' => $this->message->sender_id,
                'sender' => [
                    'id' => $this->message->sender->id,
                    'name' => $this->message->sender->name,
                    'username' => $this->message->sender->username,
                    'avatar_url' => $this->message->sender->avatar_url,
                ],
                'created_at' => $this->message->created_at->diffForHumans(),
                'is_mine' => false,
                'silenced' => $this->silenced,
            ],
        ];
    }
}
