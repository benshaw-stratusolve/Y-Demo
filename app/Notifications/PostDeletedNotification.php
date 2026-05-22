<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class PostDeletedNotification extends Notification
{
    use Queueable;

    public function __construct(public string $postExcerpt) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'post_deleted',
            'message' => 'Your post was deleted.',
            'post_excerpt' => Str::limit($this->postExcerpt, 80),
        ];
    }
}
