<?php

namespace App\Notifications;

use App\Models\Post;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class PostCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(public Post $post) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'post_created',
            'message' => 'Your post was published!',
            'post_excerpt' => Str::limit(strip_tags($this->post->body ?? ''), 80),
            'post_id' => $this->post->id,
        ];
    }
}
