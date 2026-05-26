# Real-Time Store (realtime.svelte.ts)

> A Svelte 5 module-level reactive store that holds all incoming WebSocket data and distributes it to Dashboard, Notifications, and the bell badge.

---

## Concept Explained

A `.svelte.ts` file can use Svelte 5 `$state` runes at the module level. Because modules are singletons, that state is shared across the entire app — any component that imports it gets the same reactive values. This is Svelte 5's idiomatic replacement for a Svelte 4 custom store.

---

## What the Store Holds

```ts
// resources/js/lib/realtime.svelte.ts
let newPosts               = $state<any[]>([]);
let postCounts             = $state<Record<number, { likes_count: number; replies_count: number }>>({});
let liveUnreadIncrement    = $state(0);                           // bell badge delta
let incomingNotifications  = $state<Notification[]>([]);
let newMessages            = $state<Record<number, MessageType[]>>({});  // keyed by conversation_id
let typingConversations    = $state<Set<number>>(new Set());      // conversations where someone is typing
let unreadMessagesIncrement = $state(0);                          // Messages nav badge delta
```

---

## How It's Wired Up

### Subscription — `resources/js/app.ts`

The app subscribes once on boot and re-subscribes on every Inertia navigation (handles login/logout):

```ts
activeChannel = echo.private(`user.${userId}`)
    .listen('.PostBroadcast', (e) => {
        newPosts = [e.post, ...newPosts];
    })
    .listen('.PostInteractionUpdated', (e) => {
        postCounts = {
            ...postCounts,
            [e.post_id]: { likes_count: e.likes_count, replies_count: e.replies_count },
        };
    })
    .listen('.NotificationSent', (e) => {
        incomingNotifications = [e, ...incomingNotifications];
        liveUnreadIncrement += 1;
    })
    .listen('.MessageSent', (e) => {
        const convId = e.message.conversation_id;
        newMessages = { ...newMessages, [convId]: [...(newMessages[convId] ?? []), e.message] };
        unreadMessagesIncrement += 1;
    })
    .listen('.UserTyping', (e) => {
        typingConversations = new Set([...typingConversations, e.conversation_id]);
        // auto-clears after 3 s of no typing events
    });
```

### Dashboard — new posts

```ts
// resources/js/pages/Dashboard.svelte
$effect(() => {
    const incoming = realtimeStore.newPosts;
    if (incoming.length > 0) {
        untrack(() => {
            allPosts = [...realtimeStore.consumeNewPosts(), ...allPosts];
        });
    }
});
```

`consumeNewPosts()` returns the current array and resets it to `[]` — prevents double-prepend if the effect re-runs.

### Dashboard — live counts

```svelte
<!-- Replies: realtime count first, fall back to server prop -->
{realtimeStore.postCounts[post.id]?.replies_count ?? post.replies_count ?? 0}

<!-- Likes: optimistic update first, then realtime, then server prop -->
{localLikes[post.id]?.count ?? realtimeStore.postCounts[post.id]?.likes_count ?? post.likes_count ?? 0}
```

### Bell badge — `unreadCount`

Both `Dashboard.svelte` and `DashboardSettingsLayout.svelte` derive the badge count the same way:

```ts
const unreadCount = $derived(
    ((page.props as any).unread_notifications_count as number ?? 0) + realtimeStore.liveUnreadIncrement
);
```

The server prop is the authoritative count on page load; `liveUnreadIncrement` adds the real-time delta on top.

### Notifications page — incoming notifications

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

// Reset the bell badge when the user visits /notifications
$effect(() => {
    untrack(() => realtimeStore.resetUnreadIncrement());
});
```

---

## Public API

```ts
// Reactive getters
realtimeStore.newPosts                   // buffered posts for Dashboard
realtimeStore.postCounts                 // Record<postId, {likes_count, replies_count}>
realtimeStore.liveUnreadIncrement        // bell badge delta
realtimeStore.incomingNotifications      // buffered notifications
realtimeStore.newMessages                // Record<conversationId, MessageType[]>
realtimeStore.typingConversations        // Set<conversationId>
realtimeStore.unreadMessagesIncrement    // Messages nav badge delta

// Methods
realtimeStore.subscribeToUser(userId)    // called on login / navigate
realtimeStore.unsubscribeFromUser()      // called on logout / navigate away
realtimeStore.consumeNewPosts()          // returns posts array and resets to []
realtimeStore.consumeIncomingNotifications() // returns notifications array and resets to []
realtimeStore.resetUnreadIncrement()     // sets liveUnreadIncrement back to 0
realtimeStore.consumeNewMessages(convId) // returns messages for convId and removes from buffer
realtimeStore.resetUnreadMessagesIncrement() // sets unreadMessagesIncrement back to 0
```

---

## Why `untrack` in the $effects

`$effect` tracks every reactive read inside it. Without `untrack`, writing to `allPosts` inside the effect (which reads `realtimeStore.newPosts`) would trigger a loop: write → re-run effect → write again.

`untrack()` wraps the write so it doesn't register as a reactive dependency — the effect only re-runs when `realtimeStore.newPosts` changes, not when `allPosts` changes.

---

## Shared Notification Type

The `Notification` type is defined once in `resources/js/types/notifications.ts` and imported by both this store and `Notifications.svelte`:

```ts
export type Notification = {
    id: string;
    type: string;
    data: Record<string, any>;
    read: boolean;
    created_at: string;
    is_following_actor: boolean;
};
```

---

## Related Notes

- [[Laravel Reverb (WebSockets)]]
- [[Svelte 5 Runes]]
- [[Notifications Feature]]
- [[Direct Messages (DMs)]]
- [[Prefetching + Polling]]
