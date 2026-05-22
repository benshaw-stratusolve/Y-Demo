<?php

namespace App\Notifications;

use App\Models\Post;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class CommentCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(public Post $comment, public Post $parentPost) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'comment_created',
            'message' => 'Your comment has been posted.',
            'post_id' => $this->parentPost->id,
            'post_excerpt' => Str::limit($this->comment->body, 80),
        ];
    }
}
