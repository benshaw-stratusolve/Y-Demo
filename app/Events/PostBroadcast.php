<?php

namespace App\Events;

use App\Models\Post;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PostBroadcast implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Post $post,
        public User $follower,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.'.$this->follower->id)];
    }

    public function broadcastAs(): string
    {
        return 'PostBroadcast';
    }

    public function broadcastWith(): array
    {
        $this->post->loadCount(['likes', 'replies']);
        $this->post->load('user');

        return [
            'post' => [
                'id' => $this->post->id,
                'body' => $this->post->body,
                'image_url' => $this->post->image_url,
                'likes_count' => $this->post->likes_count,
                'replies_count' => $this->post->replies_count,
                'repost_of_id' => $this->post->repost_of_id,
                'parent_post_id' => $this->post->parent_post_id,
                'liked_by_user' => false,
                'reposted_by_user' => false,
                'created_at' => $this->post->created_at->diffForHumans(),
                'user' => [
                    'id' => $this->post->user->id,
                    'name' => $this->post->user->name,
                    'username' => $this->post->user->username,
                    'avatar_url' => $this->post->user->avatar_url,
                ],
            ],
        ];
    }
}
