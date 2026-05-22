<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class CommentDeletedNotification extends Notification
{
    use Queueable;

    public function __construct(public string $commentExcerpt) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'comment_deleted',
            'message' => 'Your comment was deleted.',
            'post_excerpt' => Str::limit($this->commentExcerpt, 80),
        ];
    }
}
