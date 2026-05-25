<?php

namespace App\Notifications;

use App\Concerns\BroadcastsNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ProfanityStrikeNotification extends Notification implements ShouldBroadcast, ShouldQueue
{
    use Queueable, BroadcastsNotification;

    public function __construct(
        public string $message,
        public bool $isBan = false,
    ) {}

    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->isBan ? 'ban' : 'profanity_strike',
            'message' => $this->message,
        ];
    }
}
