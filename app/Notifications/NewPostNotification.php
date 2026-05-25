<?php

namespace App\Notifications;

use App\Concerns\BroadcastsNotification;
use App\Models\Post;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class NewPostNotification extends Notification implements ShouldBroadcast, ShouldQueue
{
    use BroadcastsNotification, Queueable;

    public function __construct(
        public User $actor,
        public Post $post,
    ) {}

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new_post',
            'actor_id' => $this->actor->id,
            'actor_name' => $this->actor->name,
            'actor_handle' => '@'.$this->actor->username,
            'actor_avatar' => $this->actor->avatar_url,
            'message' => 'posted',
            'post_id' => $this->post->id,
            'post_excerpt' => $this->post->body ? Str::limit($this->post->body, 60) : null,
        ];
    }
}
