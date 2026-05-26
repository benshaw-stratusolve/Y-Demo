# Direct Messages (DMs)

> Real-time 1-to-1 private messaging — conversations, message threads, compose modal, typing indicators, and instant delivery via Laravel Reverb.

---

## Concept Explained

The DM system stores conversations (pairs of users) and messages (rows owned by a conversation). Every page load fetches messages from the server. New messages sent by the other party are pushed instantly via Laravel Reverb — no polling. Optimistic updates make the sender's own message appear before the server confirms it.

---

## Database Shape

```
conversations
  id, user1_id, user2_id, timestamps

messages
  id, conversation_id, sender_id, body, read_at, timestamps
```

`Conversation::findOrCreateBetween($a, $b)` ensures only one conversation exists per user pair (canonical ordering: lower ID = user1).

---

## How it's Used in Y

### Controller — `app/Http/Controllers/MessagesController.php`

| Method | Route | What it does |
|--------|-------|--------------|
| `index()` | GET `/messages` | Lists all conversations, no active thread |
| `show($conversation)` | GET `/messages/{id}` | Marks incoming messages read, loads thread |
| `findOrCreate($user)` | POST `/messages/find-or-create/{user}` | Gets or creates a conversation, redirects to thread |
| `store($conversation)` | POST `/messages/{id}` | Saves message, broadcasts via Reverb |
| `typing($conversation)` | POST `/messages/{id}/typing` | Broadcasts typing indicator to recipient |

### Marking messages read

```php
$conversation->messages()
    ->where('sender_id', '!=', $user->id)
    ->whereNull('read_at')
    ->update(['read_at' => now()]);
```

Bulk update — one query, no N+1.

### Broadcasting the message

```php
broadcast(new MessageSent($message, $recipientId));
```

`MessageSent` implements `ShouldBroadcastNow` (synchronous — no queue delay). It broadcasts to the recipient's private channel `user.{recipientId}` with `broadcastAs() = 'MessageSent'`.

```php
public function broadcastWith(): array
{
    return [
        'message' => [
            'id'              => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'body'            => $this->message->body,
            'sender_id'       => $this->message->sender_id,
            'sender'          => [...],
            'created_at'      => $this->message->created_at->diffForHumans(),
            'is_mine'         => false, // always false — only recipient receives this
        ],
    ];
}
```

---

## Frontend — `resources/js/pages/Messages.svelte`

### Local state

```ts
let allMessages = $state<MessageItem[]>(messages ? [...messages] : []);
```

Messages are kept in local `$state` (not read directly from the Inertia prop) so real-time arrivals and optimistic updates can be applied without a server round-trip.

### Optimistic send

```ts
const optimistic: MessageItem = {
    id: -(++optimisticIdCounter),   // negative ID prevents collision with real IDs
    body,
    is_mine: true,
    _isNew: true,
    ...
};
allMessages = [...allMessages, optimistic];

router.post(sendMessage(activeConversation.id).url, { body }, {
    preserveScroll: true,
    preserveState: true,
    only: [],
    onError: () => {
        allMessages = allMessages.filter((m) => m.id !== optimistic.id);
        messageBody = body;
    },
});
```

The negative counter (`-(++optimisticIdCounter)`) is used instead of `Date.now()` to avoid collision if two messages are sent within the same millisecond.

### Receiving real-time messages

Two `$effect`s manage message state, declared in this order so the reset runs before the real-time appender:

```ts
// 1. Reset when server data changes (conversation navigation)
$effect(() => {
    const msgs = messages;
    const convId = activeConversation?.id ?? null;
    untrack(() => {
        // Discard Reverb buffer — server data is already fresh
        if (convId !== null) realtimeStore.consumeNewMessages(convId);
        allMessages = msgs ? [...msgs] : [];
        realtimeStore.resetUnreadMessagesIncrement();
        scrollToBottom();
    });
});

// 2. Append real-time arrivals (fires only when Reverb pushes a new message)
$effect(() => {
    if (!activeConversation) return;
    const incoming = realtimeStore.newMessages[activeConversation.id];
    if (incoming?.length) {
        untrack(() => {
            const msgs = realtimeStore
                .consumeNewMessages(activeConversation!.id)
                .map(m => ({ ...m, _isNew: true }));
            allMessages = [...allMessages, ...msgs];
            scrollToBottom();
        });
    }
});
```

**Why this order matters:** On navigation to a conversation the server has already fetched the latest messages (including any that came via Reverb while you were away). If the real-time effect ran first, it would try to append those same messages before the reset cleared them — causing duplicates. Running the reset first and discarding the buffer avoids this.

### Typing indicator

The typing indicator is debounced — the POST fires 400 ms after the user stops typing:

```ts
function handleTypingInput() {
    if (typingTimer) clearTimeout(typingTimer);
    typingTimer = setTimeout(() => {
        http.post(sendTyping(activeConversation!.id).url);
    }, 400);
}
```

The typing indicator bubble uses Anime.js for animated dots via a Svelte action:

```ts
function typingDots(node: HTMLElement) {
    const cancel = startTypingDots(node);
    return { destroy: cancel };
}
```

---

## Compose Modal

The "New Message" button opens a modal listing all users you follow. A search input filters the list client-side:

```ts
const filteredFollowing = $derived(
    followingUsers.filter(u =>
        u.name.toLowerCase().includes(composeSearch.toLowerCase()) ||
        u.username.toLowerCase().includes(composeSearch.toLowerCase())
    )
);

function startConversation(userId: number) {
    composeOpen = false;
    router.post(findOrCreateConversation(userId).url);
}
```

`followingUsers` is passed as an Inertia prop from `MessagesController` (uses `followedUsers()` relationship, ordered by name).

---

## Unread Badge

The Messages nav badge shows server-side unread count plus the live Reverb delta:

```ts
const unreadMessagesCount = $derived(
    ((page.props as any).unread_messages_count as number ?? 0) + realtimeStore.unreadMessagesIncrement
);
```

`unreadMessagesIncrement` is reset to 0 whenever a conversation is opened (inside the reset `$effect`).

---

## Animations

Message bubbles slide in when they arrive via Reverb (or are sent optimistically). Each bubble uses a Svelte action:

```ts
function bubbleEnter(node: HTMLElement, opts: { isMine: boolean; isNew?: boolean }) {
    if (opts.isNew) animateMessageBubble(node, opts.isMine);
    return {};
}
```

`animateMessageBubble` translates from ±20px and fades in with an Anime.js spring.

---

## Message Button on Profile Pages

`users/Show.svelte` has a "Message" button next to Follow that calls `findOrCreate` for that user:

```svelte
<button onclick={() => router.post(findOrCreateConversation(user.id).url)}>
    Message
</button>
```

---

## Related Notes

- [[Laravel Reverb (WebSockets)]]
- [[Real-Time Store (realtime.svelte.ts)]]
- [[Notifications Feature]]
- [[Wayfinder (Type-Safe Routes)]]
- [[Svelte 5 Runes]]
