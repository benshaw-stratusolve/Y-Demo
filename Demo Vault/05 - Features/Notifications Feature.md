# Notifications Feature

> The Notifications page — how database notifications are fetched, enriched, displayed, and kept fresh with polling.

---

## Concept Explained

The Notifications page reads from Laravel's `notifications` table, enriches each row with live actor data (name, avatar, follow status), then renders them in Svelte. Because `toArray()` only stores an `actor_id` (not the actor's name/avatar — those change), the controller re-fetches actor data fresh on every load. Inertia's `usePoll` keeps the list current without WebSockets.

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

### Frontend polling

```ts
// resources/js/pages/Notifications.svelte
import { page, router, usePoll } from '@inertiajs/svelte';

usePoll(30000, { only: ['notifications', 'unread_count'] });
```

Every 30 seconds, Inertia fires a partial reload that re-runs only the notification fetching logic. When the tab is in the background, Inertia throttles this to ~90% reduction automatically.

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

Storing only `actor_id` in notification data (not the actor's name/avatar) is intentional — if a user changes their avatar, old notifications automatically show their current avatar because it's fetched fresh. The bulk actor fetch avoids N+1 queries. Polling at 30s is a pragmatic choice — the correct long-term solution is WebSockets (Laravel Echo + Reverb), but polling requires zero extra infrastructure.

---

## Related Notes

- [[Laravel Notifications]]
- [[Notifications (Data Model)]]
- [[Shared Data (Inertia Middleware)]]
- [[Prefetching + Polling]]
