# Laravel Reverb Integration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace 30-second Inertia polling with real-time WebSocket broadcasting for notifications, dashboard feed, and post interaction counts.

**Architecture:** A single private channel `private-user.{id}` per user carries all real-time events. The backend fires broadcast events from `PostController` and adds `ShouldBroadcast` to social notification classes via a shared trait. The Svelte frontend subscribes once on app boot via Laravel Echo, updating reactive stores that Dashboard, Notifications, and the layout bell badge read from.

**Tech Stack:** Laravel Reverb (WebSocket server), `laravel-echo` + `pusher-js` (frontend transport), Svelte 5 reactive modules (`.svelte.ts` stores), Pest (tests)

---

## File Map

**New backend files:**
- `app/Concerns/BroadcastsNotification.php` — trait: adds `via()`, `broadcastOn()`, `broadcastAs()`, `toBroadcast()` to notification classes
- `app/Events/PostBroadcast.php` — broadcast event fired per follower when a post is created
- `app/Events/PostInteractionUpdated.php` — broadcast event fired when a post is liked or replied to
- `routes/channels.php` — private channel authorization

**New frontend files:**
- `resources/js/lib/echo.ts` — creates and exports the single Laravel Echo instance
- `resources/js/lib/realtime.svelte.ts` — Svelte 5 reactive store: holds `newPosts`, `postCounts`, `liveUnreadIncrement`, `incomingNotifications`

**Modified backend files:**
- `bootstrap/app.php` — add `->withBroadcasting()` to register `/broadcasting/auth` route
- `app/Notifications/LikeNotification.php` — add `BroadcastsNotification` trait, remove `via()`
- `app/Notifications/FollowNotification.php` — same
- `app/Notifications/ReplyNotification.php` — same
- `app/Notifications/PostDeletedNotification.php` — same
- `app/Notifications/ProfanityStrikeNotification.php` — same
- `app/Http/Controllers/PostController.php` — fire `PostBroadcast` in `store()`, fire `PostInteractionUpdated` in `like()` and `reply()`

**Modified frontend files:**
- `resources/js/app.ts` — subscribe to user's private channel after Inertia boots
- `resources/js/pages/Dashboard.svelte` — remove `usePoll`, add realtime feed + counts + bell badge
- `resources/js/pages/Notifications.svelte` — remove `usePoll`, prepend live notifications
- `resources/js/layouts/settings/DashboardSettingsLayout.svelte` — update bell badge with live increment

**New test files:**
- `tests/Feature/Broadcasting/ChannelAuthTest.php`
- `tests/Feature/Broadcasting/PostBroadcastTest.php`
- `tests/Feature/Broadcasting/NotificationBroadcastTest.php`

---

## Task 1: Install Reverb and npm packages

**Files:**
- Modify: `composer.json` (via artisan)
- Modify: `.env`
- Modify: `package.json` (via npm)

- [ ] **Step 1: Install Laravel Reverb**

```bash
composer require laravel/reverb
php artisan reverb:install
```

Expected output: confirmation that Reverb is installed, `.env` updated with `BROADCAST_CONNECTION=reverb` and `REVERB_*` keys, `config/reverb.php` published.

- [ ] **Step 2: Verify .env has all required keys — add any missing ones**

Open `.env` and confirm these keys exist (add them if `reverb:install` didn't):

```dotenv
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=my-app-id
REVERB_APP_KEY=my-app-key
REVERB_APP_SECRET=my-app-secret
REVERB_HOST="localhost"
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

- [ ] **Step 3: Install frontend packages**

```bash
npm install laravel-echo pusher-js
```

Expected: both packages added to `node_modules` and `package.json`.

- [ ] **Step 4: Commit**

```bash
git add composer.json composer.lock package.json package-lock.json config/reverb.php .env
git commit -m "feat: install laravel/reverb and echo+pusher-js"
```

---

## Task 2: Configure channel authorization

**Files:**
- Modify: `bootstrap/app.php`
- Create: `routes/channels.php`
- Create: `tests/Feature/Broadcasting/ChannelAuthTest.php`

- [ ] **Step 1: Write failing tests**

Create `tests/Feature/Broadcasting/ChannelAuthTest.php`:

```php
<?php

use App\Models\User;

test('user can subscribe to their own private channel', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->post('/broadcasting/auth', [
        'channel_name' => 'private-user.' . $user->id,
    ])->assertOk();
});

test('user cannot subscribe to another user private channel', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $this->actingAs($user);

    $this->post('/broadcasting/auth', [
        'channel_name' => 'private-user.' . $other->id,
    ])->assertForbidden();
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Feature/Broadcasting/ChannelAuthTest.php
```

Expected: both tests fail with 404 (route doesn't exist yet).

- [ ] **Step 3: Register broadcasting routes in bootstrap/app.php**

In `bootstrap/app.php`, add `->withBroadcasting(...)` inside the `Application::configure(...)` chain, after `->withRouting(...)`:

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
)
->withBroadcasting(
    __DIR__.'/../routes/channels.php',
)
```

- [ ] **Step 4: Create routes/channels.php**

```php
<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
php artisan test tests/Feature/Broadcasting/ChannelAuthTest.php
```

Expected: both tests pass.

- [ ] **Step 6: Commit**

```bash
git add bootstrap/app.php routes/channels.php tests/Feature/Broadcasting/ChannelAuthTest.php
git commit -m "feat: register broadcasting route and private user channel auth"
```

---

## Task 3: Create BroadcastsNotification trait

**Files:**
- Create: `app/Concerns/BroadcastsNotification.php`

The trait overrides `via()` (to capture `$notifiable` and add the `broadcast` channel), and provides `broadcastOn()`, `broadcastAs()`, and `toBroadcast()`. The payload from `toBroadcast()` matches the notification shape used in `NotificationsController::index()` so the frontend can reuse the same template.

> The order `['database', 'broadcast']` in `via()` is intentional — Laravel sets `$notification->id` before calling any channel, so `$this->id` is always available in `toBroadcast()`. The database record is also safely created before the broadcast fires.

- [ ] **Step 1: Create the trait**

```php
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
```

- [ ] **Step 2: Commit**

```bash
git add app/Concerns/BroadcastsNotification.php
git commit -m "feat: add BroadcastsNotification trait"
```

---

## Task 4: Apply BroadcastsNotification to social notification classes

**Files:**
- Modify: `app/Notifications/LikeNotification.php`
- Modify: `app/Notifications/FollowNotification.php`
- Modify: `app/Notifications/ReplyNotification.php`
- Modify: `app/Notifications/PostDeletedNotification.php`
- Modify: `app/Notifications/ProfanityStrikeNotification.php`
- Create: `tests/Feature/Broadcasting/NotificationBroadcastTest.php`

The change for each class is identical: add `use BroadcastsNotification;`, add `implements ShouldBroadcast`, remove the existing `via()` method.

- [ ] **Step 1: Write failing test**

Create `tests/Feature/Broadcasting/NotificationBroadcastTest.php`:

```php
<?php

use App\Models\Post;
use App\Models\User;
use App\Notifications\FollowNotification;
use App\Notifications\LikeNotification;
use App\Notifications\PostDeletedNotification;
use App\Notifications\ProfanityStrikeNotification;
use App\Notifications\ReplyNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('LikeNotification implements ShouldBroadcast', function () {
    expect(LikeNotification::class)->toImplement(\Illuminate\Contracts\Broadcasting\ShouldBroadcast::class);
});

test('FollowNotification implements ShouldBroadcast', function () {
    expect(FollowNotification::class)->toImplement(\Illuminate\Contracts\Broadcasting\ShouldBroadcast::class);
});

test('ReplyNotification implements ShouldBroadcast', function () {
    expect(ReplyNotification::class)->toImplement(\Illuminate\Contracts\Broadcasting\ShouldBroadcast::class);
});

test('PostDeletedNotification implements ShouldBroadcast', function () {
    expect(PostDeletedNotification::class)->toImplement(\Illuminate\Contracts\Broadcasting\ShouldBroadcast::class);
});

test('ProfanityStrikeNotification implements ShouldBroadcast', function () {
    expect(ProfanityStrikeNotification::class)->toImplement(\Illuminate\Contracts\Broadcasting\ShouldBroadcast::class);
});

test('LikeNotification is sent when a post is liked', function () {
    Notification::fake();

    $author = User::factory()->create();
    $post = Post::factory()->for($author)->create();

    $this->post("/posts/{$post->id}/like");

    Notification::assertSentTo($author, LikeNotification::class);
});

test('FollowNotification is sent when a user is followed', function () {
    Notification::fake();

    $target = User::factory()->create();

    $this->post("/users/{$target->id}/follow");

    Notification::assertSentTo($target, FollowNotification::class);
});
```

- [ ] **Step 2: Run test to confirm failures (classes don't implement ShouldBroadcast yet)**

```bash
php artisan test tests/Feature/Broadcasting/NotificationBroadcastTest.php
```

Expected: `ShouldBroadcast` assertion tests fail.

- [ ] **Step 3: Update LikeNotification**

Replace the full contents of `app/Notifications/LikeNotification.php` with:

```php
<?php

namespace App\Notifications;

use App\Concerns\BroadcastsNotification;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class LikeNotification extends Notification implements ShouldBroadcast, ShouldQueue
{
    use Queueable, BroadcastsNotification;

    public function __construct(
        public User $actor,
        public string $postExcerpt,
    ) {}

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'like',
            'actor_id' => $this->actor->id,
            'actor_name' => $this->actor->name,
            'actor_handle' => '@'.$this->actor->username,
            'actor_avatar' => $this->actor->avatar_url,
            'message' => 'liked your post',
            'post_excerpt' => $this->postExcerpt,
        ];
    }
}
```

- [ ] **Step 4: Update FollowNotification**

```php
<?php

namespace App\Notifications;

use App\Concerns\BroadcastsNotification;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class FollowNotification extends Notification implements ShouldBroadcast, ShouldQueue
{
    use Queueable, BroadcastsNotification;

    public function __construct(public User $actor) {}

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'follow',
            'actor_id' => $this->actor->id,
            'actor_name' => $this->actor->name,
            'actor_handle' => '@'.$this->actor->username,
            'actor_avatar' => $this->actor->avatar_url,
            'message' => 'followed you',
        ];
    }
}
```

- [ ] **Step 5: Update ReplyNotification**

```php
<?php

namespace App\Notifications;

use App\Concerns\BroadcastsNotification;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;

class ReplyNotification extends Notification implements ShouldBroadcast
{
    use Queueable, BroadcastsNotification;

    public function __construct(
        public User $actor,
        public string $replyExcerpt,
    ) {}

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'reply',
            'actor_id' => $this->actor->id,
            'actor_name' => $this->actor->name,
            'actor_handle' => '@'.$this->actor->username,
            'actor_avatar' => $this->actor->avatar_url,
            'message' => 'replied to your post',
            'post_excerpt' => $this->replyExcerpt,
        ];
    }
}
```

- [ ] **Step 6: Update PostDeletedNotification**

```php
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
```

- [ ] **Step 7: Update ProfanityStrikeNotification**

```php
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
```

- [ ] **Step 8: Run tests to verify they pass**

```bash
php artisan test tests/Feature/Broadcasting/NotificationBroadcastTest.php
```

Expected: all tests pass.

- [ ] **Step 9: Run full test suite to catch regressions**

```bash
php artisan test
```

Expected: all existing tests pass.

- [ ] **Step 10: Commit**

```bash
git add app/Concerns/BroadcastsNotification.php app/Notifications/LikeNotification.php app/Notifications/FollowNotification.php app/Notifications/ReplyNotification.php app/Notifications/PostDeletedNotification.php app/Notifications/ProfanityStrikeNotification.php tests/Feature/Broadcasting/NotificationBroadcastTest.php
git commit -m "feat: add ShouldBroadcast to social notification classes via BroadcastsNotification trait"
```

---

## Task 5: Create PostBroadcast event

**Files:**
- Create: `app/Events/PostBroadcast.php`
- Create: `tests/Feature/Broadcasting/PostBroadcastTest.php`

- [ ] **Step 1: Write failing test**

Create `tests/Feature/Broadcasting/PostBroadcastTest.php`:

```php
<?php

use App\Events\PostBroadcast;
use App\Models\User;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('PostBroadcast is dispatched to each follower when a post is created', function () {
    Event::fake([PostBroadcast::class]);

    $follower = User::factory()->create();
    $follower->followedUsers()->attach($this->user->id);

    $this->post('/posts', ['body' => 'Hello world']);

    Event::assertDispatched(PostBroadcast::class, function (PostBroadcast $event) use ($follower) {
        return $event->follower->id === $follower->id;
    });
});

test('PostBroadcast is not dispatched when the author has no followers', function () {
    Event::fake([PostBroadcast::class]);

    $this->post('/posts', ['body' => 'Hello world']);

    Event::assertNotDispatched(PostBroadcast::class);
});
```

- [ ] **Step 2: Run test to confirm it fails**

```bash
php artisan test tests/Feature/Broadcasting/PostBroadcastTest.php
```

Expected: fails with `PostBroadcast class not found`.

- [ ] **Step 3: Create the event**

Create `app/Events/PostBroadcast.php`:

```php
<?php

namespace App\Events;

use App\Models\Post;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PostBroadcast implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Post $post,
        public User $follower,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.'.$this->follower->id)];
    }

    public function broadcastAs(): string
    {
        return 'PostBroadcast';
    }

    public function broadcastWith(): array
    {
        $this->post->loadCount(['likes', 'replies']);
        $this->post->load('user');

        return [
            'post' => [
                'id' => $this->post->id,
                'body' => $this->post->body,
                'image_url' => $this->post->image_url,
                'likes_count' => $this->post->likes_count,
                'replies_count' => $this->post->replies_count,
                'repost_of_id' => $this->post->repost_of_id,
                'parent_post_id' => $this->post->parent_post_id,
                'liked_by_user' => false,
                'reposted_by_user' => false,
                'created_at' => $this->post->created_at->diffForHumans(),
                'user' => [
                    'id' => $this->post->user->id,
                    'name' => $this->post->user->name,
                    'username' => $this->post->user->username,
                    'avatar_url' => $this->post->user->avatar_url,
                ],
            ],
        ];
    }
}
```

- [ ] **Step 4: Run test to confirm it still fails (not dispatched from controller yet)**

```bash
php artisan test tests/Feature/Broadcasting/PostBroadcastTest.php
```

Expected: `PostBroadcast` class found but event not dispatched assertion fails. This is correct — the event isn't fired yet.

- [ ] **Step 5: Commit the event class only**

```bash
git add app/Events/PostBroadcast.php tests/Feature/Broadcasting/PostBroadcastTest.php
git commit -m "feat: add PostBroadcast event"
```

---

## Task 6: Create PostInteractionUpdated event

**Files:**
- Create: `app/Events/PostInteractionUpdated.php`

- [ ] **Step 1: Create the event**

```php
<?php

namespace App\Events;

use App\Models\Post;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PostInteractionUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Post $post) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.'.$this->post->user_id)];
    }

    public function broadcastAs(): string
    {
        return 'PostInteractionUpdated';
    }

    public function broadcastWith(): array
    {
        $this->post->loadCount(['likes', 'replies']);

        return [
            'post_id' => $this->post->id,
            'likes_count' => $this->post->likes_count,
            'replies_count' => $this->post->replies_count,
        ];
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Events/PostInteractionUpdated.php
git commit -m "feat: add PostInteractionUpdated event"
```

---

## Task 7: Fire broadcast events from PostController

**Files:**
- Modify: `app/Http/Controllers/PostController.php`

- [ ] **Step 1: Add PostBroadcast dispatch to store()**

In `PostController::store()`, after `$user->notify(new PostCreatedNotification($post));`, add:

```php
$user->followerUsers()->each(function (User $follower) use ($post) {
    broadcast(new PostBroadcast($post, $follower));
});
```

Also add these imports at the top of the file:

```php
use App\Events\PostBroadcast;
use App\Events\PostInteractionUpdated;
```

And add `User` to the existing import (it's already imported via the Notification classes' constructor types — verify `use App\Models\User;` is present).

- [ ] **Step 2: Add PostInteractionUpdated dispatch to like()**

In `PostController::like()`, after the `if (! empty($changes['attached'])...)` block, add:

```php
$post->loadCount(['likes', 'replies']);
broadcast(new PostInteractionUpdated($post));
```

- [ ] **Step 3: Add PostInteractionUpdated dispatch to reply()**

In `PostController::reply()`, after the existing `$post->user->notify(new ReplyNotification(...))` line, add:

```php
$post->loadCount(['likes', 'replies']);
broadcast(new PostInteractionUpdated($post));
```

- [ ] **Step 4: Run the broadcast tests**

```bash
php artisan test tests/Feature/Broadcasting/PostBroadcastTest.php
```

Expected: both tests pass.

- [ ] **Step 5: Run full test suite**

```bash
php artisan test
```

Expected: all tests pass.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/PostController.php
git commit -m "feat: broadcast PostBroadcast and PostInteractionUpdated from PostController"
```

---

## Task 8: Create echo.ts

**Files:**
- Create: `resources/js/lib/echo.ts`

- [ ] **Step 1: Create the Echo instance module**

```ts
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

(window as any).Pusher = Pusher;

export const echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY as string,
    wsHost: (import.meta.env.VITE_REVERB_HOST as string) ?? 'localhost',
    wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
    wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
    enabledTransports: ['ws', 'wss'],
});
```

- [ ] **Step 2: Verify Vite can resolve the imports by running a build**

```bash
npm run build 2>&1 | tail -20
```

Expected: build completes without `Cannot find module` errors for `laravel-echo` or `pusher-js`.

- [ ] **Step 3: Commit**

```bash
git add resources/js/lib/echo.ts
git commit -m "feat: add echo.ts with Reverb-configured Laravel Echo instance"
```

---

## Task 9: Create realtime.svelte.ts

**Files:**
- Create: `resources/js/lib/realtime.svelte.ts`

This module holds the reactive state that Echo event handlers write to, and that page components read from. It uses the same `.svelte.ts` pattern as the existing `notifications.svelte.ts`.

- [ ] **Step 1: Create the module**

```ts
import { echo } from '@/lib/echo';

type Post = Record<string, any>;
type Notification = {
    id: string;
    type: string;
    data: Record<string, any>;
    read: boolean;
    created_at: string;
    is_following_actor: boolean;
};

let newPosts = $state<Post[]>([]);
let postCounts = $state<Record<number, { likes_count: number; replies_count: number }>>({});
let liveUnreadIncrement = $state(0);
let incomingNotifications = $state<Notification[]>([]);

let activeChannel: ReturnType<typeof echo.private> | null = null;
let activeUserId: number | null = null;

function subscribeToUser(userId: number): void {
    if (activeChannel) return;

    activeUserId = userId;
    activeChannel = echo.private(`user.${userId}`)
        .listen('.PostBroadcast', (e: { post: Post }) => {
            newPosts = [e.post, ...newPosts];
        })
        .listen('.PostInteractionUpdated', (e: { post_id: number; likes_count: number; replies_count: number }) => {
            postCounts = { ...postCounts, [e.post_id]: { likes_count: e.likes_count, replies_count: e.replies_count } };
        })
        .listen('.NotificationSent', (e: Notification) => {
            incomingNotifications = [e, ...incomingNotifications];
            liveUnreadIncrement += 1;
        });
}

function unsubscribeFromUser(): void {
    if (activeUserId !== null) {
        echo.leave(`user.${activeUserId}`);
        activeChannel = null;
        activeUserId = null;
    }
    newPosts = [];
    postCounts = {};
    liveUnreadIncrement = 0;
    incomingNotifications = [];
}

function consumeNewPosts(): Post[] {
    const posts = [...newPosts];
    newPosts = [];
    return posts;
}

function consumeIncomingNotifications(): Notification[] {
    const notifications = [...incomingNotifications];
    incomingNotifications = [];
    return notifications;
}

function resetUnreadIncrement(): void {
    liveUnreadIncrement = 0;
}

export const realtimeStore = {
    get newPosts() { return newPosts; },
    get postCounts() { return postCounts; },
    get liveUnreadIncrement() { return liveUnreadIncrement; },
    get incomingNotifications() { return incomingNotifications; },
    subscribeToUser,
    unsubscribeFromUser,
    consumeNewPosts,
    consumeIncomingNotifications,
    resetUnreadIncrement,
};
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/lib/realtime.svelte.ts
git commit -m "feat: add realtime.svelte.ts reactive store for Reverb events"
```

---

## Task 10: Wire Echo subscription in app.ts

**Files:**
- Modify: `resources/js/app.ts`

Subscribe to the user's private channel once after Inertia boots. Use `router.on('navigate')` for subsequent page changes (e.g. login/logout). Read the initial page data from the DOM for the first load.

- [ ] **Step 1: Add subscription wiring after createInertiaApp**

At the top of `app.ts`, add these imports after the existing imports:

```ts
import { router } from '@inertiajs/svelte';
import { realtimeStore } from '@/lib/realtime.svelte';
```

After the `createInertiaApp({...}).then(dismissSplash);` call, append:

```ts
let currentUserId: number | null = null;

function syncRealtimeUser(props: Record<string, any>): void {
    const userId: number | null = props?.auth?.user?.id ?? null;
    if (userId === currentUserId) return;
    if (currentUserId !== null) realtimeStore.unsubscribeFromUser();
    currentUserId = userId;
    if (userId !== null) realtimeStore.subscribeToUser(userId);
}

// Initial page load — Inertia embeds page data in the #app element
const appEl = document.getElementById('app');
if (appEl?.dataset.page) {
    syncRealtimeUser(JSON.parse(appEl.dataset.page).props);
}

// Every subsequent Inertia navigation
router.on('navigate', (event) => {
    syncRealtimeUser((event.detail.page.props as Record<string, any>));
});
```

- [ ] **Step 2: Run dev build to check for type errors**

```bash
npm run build 2>&1 | grep -i error | head -20
```

Expected: no TypeScript errors.

- [ ] **Step 3: Commit**

```bash
git add resources/js/app.ts
git commit -m "feat: wire Echo user channel subscription in app.ts"
```

---

## Task 11: Update Dashboard.svelte — remove poll, add realtime feed + counts + bell badge

**Files:**
- Modify: `resources/js/pages/Dashboard.svelte`

Three changes: (1) remove `usePoll`, (2) prepend new posts from the realtime store, (3) update `unreadCount` to include live increment.

- [ ] **Step 1: Add realtimeStore import and remove usePoll**

In the `<script>` block of `Dashboard.svelte`:

Remove `usePoll` from the import:
```ts
// Before:
import { Deferred, page, router, usePoll } from '@inertiajs/svelte';
// After:
import { Deferred, page, router } from '@inertiajs/svelte';
```

Remove the `usePoll` call:
```ts
// Remove this line:
usePoll(30000, { only: ['posts'] });
```

Add the realtime store import after the existing imports:
```ts
import { realtimeStore } from '@/lib/realtime.svelte';
```

- [ ] **Step 2: Update unreadCount to include live increment**

Find:
```ts
const unreadCount = $derived((page.props as any).unread_notifications_count as number ?? 0);
```

Replace with:
```ts
const unreadCount = $derived(
    ((page.props as any).unread_notifications_count as number ?? 0) + realtimeStore.liveUnreadIncrement
);
```

- [ ] **Step 3: Add $effect to prepend new posts**

Add this `$effect` after the `allPosts` / `scrollPage` / `hasMore` state declarations (after the existing `$effect` that handles tab switches):

```ts
$effect(() => {
    const incoming = realtimeStore.newPosts;
    if (incoming.length > 0) {
        untrack(() => {
            allPosts = [...realtimeStore.consumeNewPosts(), ...allPosts];
        });
    }
});
```

- [ ] **Step 4: Update like/reply count rendering in the template to use live counts**

Find all occurrences of raw count references in the `{#each allPosts as post}` section:

```bash
grep -n "post\.likes_count\|post\.replies_count" resources/js/pages/Dashboard.svelte
```

For every matched line in the feed template (inside `{#each allPosts as post (post.id)}`), replace:

```svelte
{post.likes_count}
```
with:
```svelte
{realtimeStore.postCounts[post.id]?.likes_count ?? post.likes_count}
```

And replace:
```svelte
{post.replies_count}
```
with:
```svelte
{realtimeStore.postCounts[post.id]?.replies_count ?? post.replies_count}
```

Do NOT change occurrences inside Svelte `$derived` or script blocks — only the template interpolations.

- [ ] **Step 5: Run dev server and manually verify**

```bash
npm run dev &
php artisan reverb:start &
php artisan serve
```

Open two browser tabs logged in as different users. Create a post as one user while watching the dashboard of a follower — the post should appear instantly without a page refresh.

- [ ] **Step 6: Commit**

```bash
git add resources/js/pages/Dashboard.svelte
git commit -m "feat: replace Dashboard polling with Reverb real-time feed and count updates"
```

---

## Task 12: Update Notifications.svelte — remove poll, add live notifications

**Files:**
- Modify: `resources/js/pages/Notifications.svelte`

- [ ] **Step 1: Remove usePoll and add realtime import**

In `Notifications.svelte` `<script>` block:

Remove `usePoll` from the import:
```ts
// Before:
import { page, router, usePoll } from '@inertiajs/svelte';
// After:
import { page, router } from '@inertiajs/svelte';
```

Remove the `usePoll` call:
```ts
// Remove this line:
usePoll(30000, { only: ['notifications', 'unread_count'] });
```

Add the realtime store import:
```ts
import { realtimeStore } from '@/lib/realtime.svelte';
```

- [ ] **Step 2: Convert the server-side notifications prop to mutable local state**

The component currently receives `notifications` and `unread_count` as props from Inertia. Replace the `let { notifications, unread_count }` destructure with a local state array that starts from the prop:

Add after the existing prop destructure:
```ts
let { notifications: initialNotifications, unread_count }: { notifications: Notification[]; unread_count: number } = $props();
let allNotifications = $state<Notification[]>([...initialNotifications]);
```

Then in the template, replace every reference to `notifications` with `allNotifications`.

- [ ] **Step 3: Add $effect to prepend incoming notifications**

Add this `$effect` after the `allNotifications` declaration:

```ts
$effect(() => {
    const incoming = realtimeStore.incomingNotifications;
    if (incoming.length > 0) {
        untrack(() => {
            allNotifications = [...realtimeStore.consumeIncomingNotifications(), ...allNotifications];
        });
    }
});
```

Also add `untrack` to the imports from `svelte`:
```ts
import { untrack } from 'svelte';
```

- [ ] **Step 4: Reset live unread increment when the notifications page mounts**

Since visiting `/notifications` marks all notifications as read server-side, reset the live increment so the bell badge goes back to 0:

Add an `$effect` that runs once on mount:
```ts
$effect(() => {
    realtimeStore.resetUnreadIncrement();
});
```

- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/Notifications.svelte
git commit -m "feat: replace Notifications polling with Reverb real-time notification list"
```

---

## Task 13: Update DashboardSettingsLayout.svelte — live bell badge

**Files:**
- Modify: `resources/js/layouts/settings/DashboardSettingsLayout.svelte`

- [ ] **Step 1: Add realtimeStore import and update unreadCount**

In the `<script>` block, add:
```ts
import { realtimeStore } from '@/lib/realtime.svelte';
```

Find:
```ts
const unreadCount = $derived((page.props as any).unread_notifications_count as number ?? 0);
```

Replace with:
```ts
const unreadCount = $derived(
    ((page.props as any).unread_notifications_count as number ?? 0) + realtimeStore.liveUnreadIncrement
);
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/layouts/settings/DashboardSettingsLayout.svelte
git commit -m "feat: update settings layout bell badge with live unread count"
```

---

## Task 14: End-to-end verification

- [ ] **Step 1: Run the full test suite**

```bash
php artisan test
```

Expected: all tests pass.

- [ ] **Step 2: Start all three processes**

```bash
php artisan reverb:start --debug
```
(In a separate terminal:)
```bash
php artisan serve
```
(In a separate terminal:)
```bash
npm run dev
```

- [ ] **Step 3: Manual smoke tests**

Open two browser windows. Log in as User A in one, User B (who follows User A) in the other.

| Action | Expected result |
|--------|----------------|
| User A creates a post | Post appears instantly in User B's dashboard feed |
| User B likes User A's post | User A's bell badge increments; notification appears in User A's notification list if on /notifications page |
| User B replies to User A's post | Same as above with reply notification |
| User A visits /notifications | Bell badge resets to 0 |
| Admin deletes User A's post | User A receives real-time post-deleted notification |

- [ ] **Step 4: Final commit**

```bash
git add -A
git commit -m "feat: Laravel Reverb real-time integration complete"
```
