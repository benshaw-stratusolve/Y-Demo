# Laravel Notifications

> Laravel's notification system — how Y sends in-app database notifications, emails, and real-time WebSocket pushes.

---

## Concept Explained

A Laravel `Notification` class defines *what* to send and *how* to send it. The `via()` method returns an array of channels (`'database'`, `'mail'`, `'broadcast'`). `toArray()` defines the database payload. `toMail()` defines the email format. `toBroadcast()` defines the WebSocket payload. Notifications are sent by calling `$user->notify(new SomeNotification(...))`.

---

## How it's Used in Y

### Notification classes

| Class | Channels | Trigger |
|---|---|---|
| `WelcomeNotification` | database | Registration |
| `PostCreatedNotification` | database | Post published |
| `LikeNotification` | database + broadcast | Someone likes your post |
| `ReplyNotification` | database + broadcast | Someone replies to your post |
| `FollowNotification` | database + broadcast | Someone follows you |
| `PostDeletedNotification` | database + broadcast | Post removed (admin or self) |
| `ProfanityStrikeNotification` | database + broadcast | Strike or ban issued |
| `PasswordUpdateNotification` | mail + database | Password changed |

### Database channel — `toArray()`

```php
// app/Notifications/LikeNotification.php
public function toArray(object $notifiable): array
{
    return [
        'type'         => 'like',
        'actor_id'     => $this->actor->id,    // stored as ID, enriched on read
        'message'      => 'liked your post',
        'post_excerpt' => $this->postExcerpt,
    ];
}
```

The `type` key is the value the frontend reads (`notif.type`). `actor_id` is deliberately stored instead of `actor_name` — so if the actor changes their name, old notifications show the updated name.

### Broadcast channel — `BroadcastsNotification` trait

Rather than duplicating broadcast logic across five classes, a shared trait handles it. All social notification classes use `use BroadcastsNotification` which provides `via()`, `broadcastOn()`, `broadcastAs()`, and `toBroadcast()`:

```php
// app/Concerns/BroadcastsNotification.php
trait BroadcastsNotification
{
    public function via(object $notifiable): array
    {
        $this->notifiable = $notifiable;
        return ['database', 'broadcast'];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.'.$this->notifiable->id)];
    }

    public function broadcastAs(): string { return 'NotificationSent'; }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        $data = $this->toArray($notifiable);
        return new BroadcastMessage([
            'id'         => $this->id,
            'type'       => $data['type'] ?? 'unknown',
            'data'       => $data,
            'read'       => false,
            'created_at' => 'just now',
        ]);
    }
}
```

All five social notifications implement `ShouldBroadcast, ShouldQueue` and `use Queueable, BroadcastsNotification`.

### Mail channel — `toMail()`

```php
// app/Notifications/PasswordUpdateNotification.php
public function toMail(object $notifiable): MailMessage
{
    return (new MailMessage)
        ->subject('Your password was updated')
        ->markdown('mail.password-updated', [
            'user' => $notifiable,
            'url'  => config('app.url'),
        ]);
}
```

Uses a custom Markdown mail template at `resources/views/mail/password-updated.blade.php`.

### Queued notifications

Social notifications implement `ShouldQueue` so they're processed by the queue worker, not in the HTTP request:

```php
class LikeNotification extends Notification implements ShouldBroadcast, ShouldQueue
{
    use Queueable, BroadcastsNotification;
    // ...
}
```

`QUEUE_CONNECTION=database` ensures the HTTP request returns immediately; the queue worker handles delivery and WebSocket push asynchronously.

### The `Notifiable` trait

`User` uses `HasFactory, Notifiable` — the `Notifiable` trait provides:
- `$user->notify()` — sends a notification
- `$user->notifications()` — relationship to all notifications
- `$user->unreadNotifications()` — scope for `read_at IS NULL`

---

## Key Code Snippet

```php
// Sending a notification is always one line:
$post->user->notify(new LikeNotification($user, Str::limit($post->body, 50)));
$user->notify(new PasswordUpdateNotification);
```

---

## Why This Approach

Storing `actor_id` instead of `actor_name` keeps notification data accurate over time — names and avatars can change. The dual channel (`mail` + `database`) for password updates means the user gets both an immediate in-app notification and an email they can check from any device. The `BroadcastsNotification` trait avoids repeating 20+ lines of broadcast boilerplate across five classes.

---

## Related Notes

- [[Laravel Reverb (WebSockets)]]
- [[Notifications Feature]]
- [[Real-Time Store (realtime.svelte.ts)]]
- [[Notifications (Data Model)]]
- [[Events + Listeners]]
- [[Jobs + Queues (ProcessPostImage)]]
