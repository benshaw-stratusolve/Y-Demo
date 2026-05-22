# Laravel Notifications

> Laravel's notification system — how Y sends in-app database notifications and security-sensitive emails.

---

## Concept Explained

A Laravel `Notification` class defines *what* to send and *how* to send it. The `via()` method returns an array of channels (`'database'`, `'mail'`). `toArray()` defines the database payload. `toMail()` defines the email format. Notifications are sent by calling `$user->notify(new SomeNotification(...))`.

---

## How it's Used in Y

### Notification classes

| Class | Channels | Trigger |
|---|---|---|
| `WelcomeNotification` | database | Registration |
| `PostCreatedNotification` | database | Post published |
| `LikeNotification` | database | Someone likes your post |
| `ReplyNotification` | database | Someone replies to your post |
| `FollowNotification` | database | Someone follows you |
| `ProfanityStrikeNotification` | database | Strike or ban issued |
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

Notifications that involve actor User objects implement `ShouldQueue`:

```php
class LikeNotification extends Notification implements ShouldQueue
{
    use Queueable;
    // ...
}
```

This means `$post->user->notify(new LikeNotification(...))` returns immediately and the notification is stored in the background. `WelcomeNotification` does not queue — it's a database-only notification with no external call.

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

Storing `actor_id` instead of `actor_name` keeps notification data accurate over time — names and avatars can change. The dual channel (`mail` + `database`) for password updates means the user gets both an immediate in-app notification and an email they can check from any device.

---

## Related Notes

- [[Notifications (Data Model)]]
- [[Notifications Feature]]
- [[Events + Listeners]]
- [[Jobs + Queues (ProcessPostImage)]]
