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

    public int $likesCount;
    public int $repliesCount;
    public array $postUser;

    public function __construct(
        public Post $post,
        public User $follower,
    ) {
        $post->loadCount(['likes', 'replies']);
        $post->load('user');
        $this->likesCount = $post->likes_count;
        $this->repliesCount = $post->replies_count;
        $this->postUser = [
            'id' => $post->user->id,
            'name' => $post->user->name,
            'username' => $post->user->username,
            'avatar_url' => $post->user->avatar_url,
        ];
    }

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
        return [
            'post' => [
                'id' => $this->post->id,
                'body' => $this->post->body,
                'image_url' => $this->post->image_url,
                'likes_count' => $this->likesCount,
                'replies_count' => $this->repliesCount,
                'repost_of_id' => $this->post->repost_of_id,
                'parent_post_id' => $this->post->parent_post_id,
                'liked_by_user' => false,
                'reposted_by_user' => false,
                'created_at' => $this->post->created_at->diffForHumans(),
                'user' => $this->postUser,
            ],
        ];
    }
}
