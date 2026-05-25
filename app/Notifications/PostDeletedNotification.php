<?php

namespace App\Notifications;

use App\Concerns\BroadcastsNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class PostDeletedNotification extends Notification implements ShouldBroadcast
{
    use Queueable, BroadcastsNotification;

    public function __construct(
        public string $postExcerpt,
        public ?string $reason = null,
        public bool $selfDeleted = false,
    ) {}

    public function toArray(object $notifiable): array
    {
        if ($this->selfDeleted) {
            return [
                'type' => 'post_self_deleted',
                'message' => 'You deleted a post.',
                'post_excerpt' => Str::limit($this->postExcerpt, 80),
            ];
        }

        return [
            'type' => 'post_deleted',
            'message' => 'Your post was removed by an admin.',
            'post_excerpt' => Str::limit($this->postExcerpt, 80),
            'reason' => $this->reason,
        ];
    }
}
