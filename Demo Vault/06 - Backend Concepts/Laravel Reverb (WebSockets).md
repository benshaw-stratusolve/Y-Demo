# Laravel Reverb (WebSockets)

> How Y uses Laravel Reverb to push real-time updates to connected browsers without polling.

---

## Concept Explained

**Laravel Reverb** is a first-party WebSocket server that runs alongside your Laravel app. Browsers connect to it and subscribe to named channels. When the server fires a broadcast event, Reverb pushes a JSON message to every subscriber on that channel — instantly, with no polling.

The frontend uses **Laravel Echo** (a JS library) to manage the WebSocket connection and listen for events. Echo uses `pusher-js` as its transport layer but points it at Reverb instead of Pusher's servers.

---

## Architecture in Y

Every logged-in user gets a single **private channel**:

```
private-user.{id}
```

Five event types flow through that channel:

| Event | Fired when | Frontend effect |
|---|---|---|
| `.PostBroadcast` | A followed user creates a post | Post prepended to Dashboard feed |
| `.PostInteractionUpdated` | A post is liked or replied to | Like/reply count updates in place |
| `.NotificationSent` | Any social notification is created | Bell badge increments; notification prepended to list |
| `.MessageSent` | Someone sends you a DM | Message appended to active thread instantly |
| `.UserTyping` | Someone is typing in a DM thread | Animated typing indicator appears |

---

## Backend Setup

### Channel authorization — `routes/channels.php`

Before a browser can subscribe to a private channel, the server must authorize it. This happens automatically via a POST to `/broadcasting/auth`:

```php
Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
```

Users can only subscribe to their own channel.

### Broadcast events

```php
// app/Events/PostBroadcast.php
class PostBroadcast implements ShouldBroadcast
{
    public int $likesCount;
    public int $repliesCount;
    public array $postUser;

    public function __construct(public Post $post, public User $follower)
    {
        // Counts loaded in constructor so SerializesModels re-fetch
        // doesn't trigger extra queries per follower in the queue worker.
        $post->loadCount(['likes', 'replies']);
        $post->load('user');
        $this->likesCount   = $post->likes_count;
        $this->repliesCount = $post->replies_count;
        $this->postUser     = [...]; // id, name, username, avatar_url
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.'.$this->follower->id)];
    }

    public function broadcastAs(): string { return 'PostBroadcast'; }
}
```

```php
// app/Events/PostInteractionUpdated.php
class PostInteractionUpdated implements ShouldBroadcast
{
    public function __construct(public Post $post) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.'.$this->post->user_id)];
    }

    public function broadcastAs(): string { return 'PostInteractionUpdated'; }

    public function broadcastWith(): array
    {
        $this->post->loadCount(['likes', 'replies']);
        return [
            'post_id'       => $this->post->id,
            'likes_count'   => $this->post->likes_count,
            'replies_count' => $this->post->replies_count,
        ];
    }
}
```

### Firing events from PostController

```php
// store() — notify every follower
$user->followerUsers()->each(function (User $follower) use ($post) {
    broadcast(new PostBroadcast($post, $follower));
});

// like() and reply()
$post->loadCount(['likes', 'replies']);
broadcast(new PostInteractionUpdated($post));
```

### BroadcastsNotification trait

Rather than duplicating broadcast boilerplate across five notification classes, a shared trait handles it:

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

All social notification classes (`LikeNotification`, `FollowNotification`, `ReplyNotification`, `PostDeletedNotification`, `ProfanityStrikeNotification`) implement `ShouldBroadcast, ShouldQueue` and use this trait.

---

## Frontend Setup

### `resources/js/lib/echo.ts`

Creates the single Echo instance used app-wide. Guarded against SSR (Node.js has no `window`):

```ts
const isBrowser = typeof window !== 'undefined';

if (isBrowser) {
    (window as any).Pusher = Pusher;
}

export const echo: Echo = isBrowser
    ? new Echo({
          broadcaster: 'reverb',
          key: import.meta.env.VITE_REVERB_APP_KEY,
          wsHost: import.meta.env.VITE_REVERB_HOST ?? 'localhost',
          wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
          forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
          enabledTransports: ['ws', 'wss'],
      })
    : (null as unknown as Echo);
```

### `resources/js/app.ts` — subscription wiring

After Inertia boots, the app subscribes to the current user's channel and re-subscribes on navigation (handles login/logout during the SPA session):

```ts
router.on('navigate', (event) => {
    syncRealtimeUser(event.detail.page.props);
});
```

---

## Running Reverb

```bash
php artisan reverb:start          # WebSocket server on port 8080
composer run dev                  # starts server + queue worker + Vite + logs
```

Reverb must be running for real-time delivery. If it's down, the app still works — broadcasts are queued and silently dropped.

---

## Key Design Decisions

**Single channel per user** — simpler than per-post or per-conversation channels. All event types share one subscription.

**Queued broadcasts** (`QUEUE_CONNECTION=database`) — HTTP requests return immediately. If Reverb is down, the queue job fails silently rather than returning a 500 to the user.

**Counts pre-loaded in constructor** — `PostBroadcast` loads counts in `__construct()`, not `broadcastWith()`, because `SerializesModels` re-fetches the `Post` from the DB when the queue worker deserializes the job. Loading in the constructor means the counts are stored as plain integers and survive serialization without extra queries.

---

## Related Notes

- [[Real-Time Store (realtime.svelte.ts)]]
- [[Notifications Feature]]
- [[Direct Messages (DMs)]]
- [[Laravel Notifications]]
- [[Jobs + Queues (ProcessPostImage)]]
- [[Events + Listeners]]
