<?php

namespace App\Events;

use App\Models\Post;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PostInteractionUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Post $post) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.'.$this->post->user_id)];
    }

    public function broadcastAs(): string
    {
        return 'PostInteractionUpdated';
    }

    public function broadcastWith(): array
    {
        $this->post->loadCount(['likes', 'replies']);

        return [
            'post_id' => $this->post->id,
            'likes_count' => $this->post->likes_count,
            'replies_count' => $this->post->replies_count,
        ];
    }
}
