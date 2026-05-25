# Laravel Reverb Integration — Design Spec

**Date:** 2026-05-25  
**Project:** Y (Twitter clone)  
**Stack:** Laravel 13 + Inertia + Svelte, running locally via Laravel Herd

---

## Overview

Replace the existing 30-second Inertia polling on the Dashboard and Notifications pages with real-time WebSocket broadcasting via Laravel Reverb. Each authenticated user subscribes to a single private channel. The Reverb server runs locally alongside Herd.

**In scope:**
- Real-time notification badge and notification list updates
- Auto-insert new posts at the top of the Dashboard feed
- Live like/reply count updates on visible posts

**Out of scope:**
- Production deployment (nginx proxy, supervisor, etc.)
- Typing indicators or presence channels
- Direct messages

---

## Architecture

Every authenticated user subscribes to one private channel: `private-user.{id}`.

Laravel broadcasts typed events onto that channel from the backend. The Svelte frontend listens via Laravel Echo (using `pusher-js` as the WebSocket transport, configured to point at Reverb instead of Pusher).

Initial page data continues to load via Inertia on navigation. Reverb only handles incremental real-time updates after the page has loaded.

---

## Backend

### Package

Install `laravel/reverb` via Composer. Run the Reverb install artisan command to publish config and set environment variables.

### Broadcasting Config

`.env` additions:
```
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=
REVERB_APP_KEY=
REVERB_APP_SECRET=
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
```

`config/broadcasting.php` already exists in Laravel 13 — just set the default driver to `reverb`.

### Channel Authorization

`routes/channels.php`:
```php
Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
```

### Broadcast Events

#### 1. Notifications — via existing Notification classes

Add `ShouldBroadcast` to each existing notification class. Each broadcasts on `PrivateChannel('user.' . $this->notifiable->id)` and returns a payload matching the shape already used in `NotificationsController`:

```php
public function broadcastOn(): array {
    return [new PrivateChannel('user.' . $this->notifiable->id)];
}

public function broadcastAs(): string {
    return 'NotificationSent';
}

public function broadcastWith(): array {
    return [
        'notification' => [...], // same shape as NotificationsController response
        'unread_count' => $this->notifiable->unreadNotifications()->count(),
    ];
}
```

All 10 existing notification classes get this treatment.

#### 2. New Post — `PostBroadcast` event

Fired in `PostController::store()` after a post is saved. Broadcasts to each follower of the author.

```php
// Fired once per follower
broadcast(new PostBroadcast($post, $follower))->toOthers();
```

Payload:
```json
{
  "post": {
    "id": 123,
    "body": "...",
    "author": { "id": 1, "name": "...", "username": "...", "avatar_url": "..." },
    "likes_count": 0,
    "replies_count": 0,
    "created_at": "..."
  }
}
```

#### 3. Interaction Count Update — `PostInteractionUpdated` event

Fired in `PostController::like()` and `PostController::reply()` (or wherever likes/replies are toggled). Broadcasts to the post author's channel and to all followers currently viewing the post.

Broadcasts to `private-user.{post->user_id}`:
```json
{
  "post_id": 123,
  "likes_count": 5,
  "replies_count": 2
}
```

> **Note:** For simplicity, interaction updates only broadcast to the post author's channel. Other users see counts update on next page load or navigation. This can be extended later with a `post.{id}` public channel if needed.

---

## Frontend

### Echo Setup — `resources/js/lib/echo.ts`

New file. Initialises a single `Echo` instance configured for Reverb:

```ts
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

export const echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST ?? 'localhost',
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
    forceTLS: false,
    enabledTransports: ['ws'],
});
```

`vite.config.ts` env vars (already sourced from `.env` by Vite):
```
VITE_REVERB_APP_KEY=
VITE_REVERB_HOST=localhost
VITE_REVERB_PORT=8080
```

### Realtime Store — `resources/js/lib/realtime.svelte.ts`

Holds the active channel subscription. Exposes `subscribeToUser(userId)` and `unsubscribeFromUser()`.

On `NotificationSent`: appends to the existing `notifications` store and increments `unread_count`.  
On `PostBroadcast`: emits a Svelte custom event / updates a `newPosts` store that Dashboard listens to.  
On `PostInteractionUpdated`: updates counts in a shared `postCounts` store keyed by post id.

### Layout Integration — `DashboardSettingsLayout.svelte`

Call `subscribeToUser(auth.user.id)` on mount. Call `unsubscribeFromUser()` on destroy. This is the shared layout, so the subscription is active across all pages without duplicating setup.

### Dashboard — `pages/Dashboard.svelte`

- Remove `usePoll(30000, { only: ['posts'] })`
- Subscribe to the `newPosts` store; prepend arriving posts to the local `posts.data` array
- Subscribe to the `postCounts` store; update `likes_count` / `replies_count` on matching posts in-place

### Notifications — `pages/Notifications.svelte`

- Remove `usePoll(30000, { only: ['notifications', 'unread_count'] })`
- Notification badge and list are already driven by the `notifications` store — no additional changes needed once the store is updated by the realtime handler

---

## What Is Removed

| File | Change |
|---|---|
| `Dashboard.svelte` | Remove `usePoll(30000, { only: ['posts'] })` |
| `Notifications.svelte` | Remove `usePoll(30000, { only: ['notifications', 'unread_count'] })` |

---

## Local Dev

Run Reverb alongside `php artisan serve` (or Herd):

```bash
php artisan reverb:start
```

No additional process manager needed for local dev.
