<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ProfanityStrikeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $message,
        public bool $isBan = false,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->isBan ? 'ban' : 'profanity_strike',
            'message' => $this->message,
        ];
    }
}
