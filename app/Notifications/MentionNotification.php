<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MentionNotification extends Notification
{
    use Queueable;

    public function __construct(
        public User $actor,
        public string $postExcerpt,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'mention',
            'actor_id' => $this->actor->id,
            'actor_name' => $this->actor->name,
            'actor_handle' => '@'.strtolower(str_replace(' ', '', $this->actor->name)),
            'actor_avatar' => $this->actor->avatar_url,
            'message' => 'mentioned you',
            'post_excerpt' => $this->postExcerpt,
        ];
    }
}
