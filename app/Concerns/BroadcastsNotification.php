<?php

namespace App\Concerns;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Notifications\Messages\BroadcastMessage;

trait BroadcastsNotification
{
    protected object $notifiable;

    public function via(object $notifiable): array
    {
        $this->notifiable = $notifiable;

        return ['database', 'broadcast'];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.'.$this->notifiable->id)];
    }

    public function broadcastAs(): string
    {
        return 'NotificationSent';
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        $data = $this->toArray($notifiable);

        return new BroadcastMessage([
            'id' => $this->id,
            'type' => $data['type'] ?? 'unknown',
            'data' => $data,
            'read' => false,
            'created_at' => 'just now',
            'is_following_actor' => false,
        ]);
    }
}
