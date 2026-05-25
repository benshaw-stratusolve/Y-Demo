<?php

namespace App\Notifications;

use App\Concerns\BroadcastsNotification;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class LikeNotification extends Notification implements ShouldBroadcast, ShouldQueue
{
    use Queueable, BroadcastsNotification;

    public function __construct(
        public User $actor,
        public string $postExcerpt,
    ) {}

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'like',
            'actor_id' => $this->actor->id,
            'actor_name' => $this->actor->name,
            'actor_handle' => '@'.$this->actor->username,
            'actor_avatar' => $this->actor->avatar_url,
            'message' => 'liked your post',
            'post_excerpt' => $this->postExcerpt,
        ];
    }
}
