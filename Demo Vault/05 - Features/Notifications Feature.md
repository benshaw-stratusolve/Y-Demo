# Notifications Feature

> The Notifications page — how database notifications are fetched, enriched, displayed, and kept live with WebSockets.

---

## Concept Explained

The Notifications page reads from Laravel's `notifications` table, enriches each row with live actor data (name, avatar, follow status), then renders them in Svelte. Because `toArray()` only stores an `actor_id` (not the actor's name/avatar — those change), the controller re-fetches actor data fresh on every load. New notifications are pushed in real-time via Laravel Reverb and prepended to the list without a page refresh.

---

## How it's Used in Y

File: `app/Http/Controllers/NotificationsController.php`

### Load and mark read

When you open the Notifications page, all unread notifications are immediately marked read:

```php
$user->unreadNotifications()->update(['read_at' => now()]);
```

This is a bulk update — one query marks all unread rows.

### Actor enrichment

Notifications store `actor_id` (user who triggered the event). The controller fetches all actors in one query, not per-notification:

```php
$actorIds = $rawNotifications->pluck('data.actor_id')->filter()->unique();
$actors = User::whereIn('id', $actorIds)
    ->get(['id', 'name', 'username', 'avatar'])
    ->keyBy('id');
```

Each notification is then mapped, injecting `actor_name` and `actor_avatar` from the in-memory collection — zero N+1.

### Real-time delivery via Reverb

Polling has been replaced with WebSocket push. When a notification is created server-side, the `BroadcastsNotification` trait broadcasts a `.NotificationSent` event to the user's private channel. The frontend prepends it instantly:

```ts
// resources/js/pages/Notifications.svelte
$effect(() => {
    const incoming = realtimeStore.incomingNotifications;
    if (incoming.length > 0) {
        untrack(() => {
            allNotifications = [...realtimeStore.consumeIncomingNotifications(), ...allNotifications];
        });
    }
});
```

The component holds notifications in `allNotifications` (local `$state`) rather than reading directly from the Inertia prop. This lets real-time notifications be prepended without a server round-trip.

### Bell badge reset

Visiting `/notifications` resets the live unread increment in the realtime store:

```ts
$effect(() => {
    untrack(() => realtimeStore.resetUnreadIncrement());
});
```

### Follow-back status

For `follow` type notifications, the page also shows whether you're already following the actor back:

```php
$alreadyFollowing = $user->follows()
    ->whereIn('following_id', $followActorIds)
    ->pluck('following_id')
    ->flip(); // hash-map for O(1) lookup

'is_following_actor' => $alreadyFollowing->has($data['actor_id']),
```

---

## Key Code Snippet

```php
$notifications = $rawNotifications->map(function ($n) use ($actors, $alreadyFollowing) {
    $data = $n->data;
    if (isset($data['actor_id']) && $actors->has($data['actor_id'])) {
        $actor = $actors->get($data['actor_id']);
        $data['actor_name']   = $actor->name;
        $data['actor_avatar'] = $actor->avatar_url;
    }
    return [
        'id'               => $n->id,
        'type'             => $data['type'] ?? 'unknown',
        'data'             => $data,
        'read'             => $n->read_at !== null,
        'created_at'       => $n->created_at->diffForHumans(),
        'is_following_actor' => isset($data['actor_id'])
                               && $alreadyFollowing->has($data['actor_id']),
    ];
});
```

---

## Why This Approach

Storing only `actor_id` in notification data (not the actor's name/avatar) is intentional — if a user changes their avatar, old notifications automatically show their current avatar because it's fetched fresh. The bulk actor fetch avoids N+1 queries. WebSocket push via Reverb delivers notifications instantly and eliminates the 30-second polling delay.

---

## Related Notes

- [[Laravel Notifications]]
- [[Laravel Reverb (WebSockets)]]
- [[Real-Time Store (realtime.svelte.ts)]]
- [[Shared Data (Inertia Middleware)]]
- [[Prefetching + Polling]]
