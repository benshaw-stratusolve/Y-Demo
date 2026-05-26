# Direct Messages Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add real-time 1-on-1 direct messaging to Y — inbox page, message threads, live delivery via Reverb, typing indicators, and unread badge in the sidebar nav.

**Architecture:** Conversations are stored with canonical `user1_id < user2_id` ordering so a pair can never have duplicate records. Messages belong to a conversation and carry `read_at` for unread tracking. New messages broadcast on the recipient's existing `user.{id}` Reverb channel — no new channel subscriptions required. The `/messages` and `/messages/{conversation}` routes share one Svelte page component, `Messages.svelte`, which conditionally renders the thread panel.

**Tech Stack:** Laravel 13, Inertia v3, Svelte 5 (runes), Laravel Reverb (ShouldBroadcastNow), Pest 4, Wayfinder for typed route helpers, Tailwind v4.

---

## File Map

**Create:**
- `database/migrations/[ts]_create_conversations_table.php`
- `database/migrations/[ts]_create_messages_table.php`
- `app/Models/Conversation.php`
- `app/Models/Message.php`
- `app/Http/Controllers/MessagesController.php`
- `app/Events/MessageSent.php`
- `app/Events/UserTyping.php`
- `resources/js/pages/Messages.svelte`
- `tests/Feature/MessagesTest.php`

**Modify:**
- `app/Models/User.php` — add `conversations()` and `unreadMessagesCount()` 
- `routes/web.php` — replace stub with real routes
- `routes/channels.php` — no changes needed (reuse `user.{id}`)
- `app/Http/Middleware/HandleInertiaRequests.php` — add `unread_messages_count`
- `resources/js/lib/realtime.svelte.ts` — add `MessageSent` / `UserTyping` listeners
- `resources/js/pages/Dashboard.svelte` — add Messages nav link + unread badge
- `resources/js/pages/Notifications.svelte` — add Messages nav link + unread badge
- `resources/js/pages/users/Show.svelte` — add "Message" button next to Follow

---

## Task 1: Database Migrations

**Files:**
- Create: `database/migrations/[ts]_create_conversations_table.php`
- Create: `database/migrations/[ts]_create_messages_table.php`

- [ ] **Step 1: Create conversations migration**

```bash
php artisan make:migration create_conversations_table --no-interaction
```

Open the generated file and replace `up()` with:

```php
public function up(): void
{
    Schema::create('conversations', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user1_id')->constrained('users')->cascadeOnDelete();
        $table->foreignId('user2_id')->constrained('users')->cascadeOnDelete();
        $table->timestamps();
        $table->unique(['user1_id', 'user2_id']);
    });
}

public function down(): void
{
    Schema::dropIfExists('conversations');
}
```

- [ ] **Step 2: Create messages migration**

```bash
php artisan make:migration create_messages_table --no-interaction
```

Open the generated file and replace `up()`:

```php
public function up(): void
{
    Schema::create('messages', function (Blueprint $table) {
        $table->id();
        $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
        $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
        $table->text('body');
        $table->timestamp('read_at')->nullable();
        $table->timestamps();
        $table->index(['conversation_id', 'created_at']);
    });
}

public function down(): void
{
    Schema::dropIfExists('messages');
}
```

- [ ] **Step 3: Run migrations**

```bash
php artisan migrate
```

Expected: both tables created with no errors.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/
git commit -m "feat: add conversations and messages migrations"
```

---

## Task 2: Models

**Files:**
- Create: `app/Models/Conversation.php`
- Create: `app/Models/Message.php`
- Modify: `app/Models/User.php`

- [ ] **Step 1: Write failing tests for Conversation model**

```bash
php artisan make:test --pest MessagesTest --no-interaction
```

Open `tests/Feature/MessagesTest.php` and replace contents:

```php
<?php

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;

it('creates a canonical conversation between two users', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $conv = Conversation::findOrCreateBetween($userA->id, $userB->id);

    expect($conv->user1_id)->toBe(min($userA->id, $userB->id));
    expect($conv->user2_id)->toBe(max($userA->id, $userB->id));
});

it('finds existing conversation instead of creating a duplicate', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    Conversation::findOrCreateBetween($userA->id, $userB->id);
    Conversation::findOrCreateBetween($userA->id, $userB->id);

    expect(Conversation::count())->toBe(1);
});

it('returns the other user in a conversation', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $conv = Conversation::findOrCreateBetween($userA->id, $userB->id);

    expect($conv->otherUser($userA->id)->id)->toBe($userB->id);
    expect($conv->otherUser($userB->id)->id)->toBe($userA->id);
});

it('counts unread messages correctly', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $conv = Conversation::findOrCreateBetween($userA->id, $userB->id);
    $conv->messages()->create(['sender_id' => $userA->id, 'body' => 'Hi']);
    $conv->messages()->create(['sender_id' => $userA->id, 'body' => 'Hey']);

    expect($conv->unreadCount($userB->id))->toBe(2);
    expect($conv->unreadCount($userA->id))->toBe(0);
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test --compact --filter=MessagesTest
```

Expected: FAIL — class `Conversation` not found.

- [ ] **Step 3: Create Conversation model**

```bash
php artisan make:model Conversation --no-interaction
```

Replace contents of `app/Models/Conversation.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = ['user1_id', 'user2_id'];

    public function user1(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user1_id');
    }

    public function user2(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user2_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function otherUser(int $userId): User
    {
        return $this->user1_id === $userId ? $this->user2 : $this->user1;
    }

    public function unreadCount(int $userId): int
    {
        return $this->messages()
            ->where('sender_id', '!=', $userId)
            ->whereNull('read_at')
            ->count();
    }

    public static function findOrCreateBetween(int $userIdA, int $userIdB): self
    {
        return static::firstOrCreate([
            'user1_id' => min($userIdA, $userIdB),
            'user2_id' => max($userIdA, $userIdB),
        ]);
    }
}
```

- [ ] **Step 4: Create Message model**

```bash
php artisan make:model Message --no-interaction
```

Replace contents of `app/Models/Message.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = ['conversation_id', 'sender_id', 'body'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
```

- [ ] **Step 5: Add `unreadMessagesCount` to User model**

In `app/Models/User.php`, add this import and method:

```php
// Add import at top
use App\Models\Message;

// Add method to class body
public function unreadMessagesCount(): int
{
    return Message::whereHas('conversation', function ($q) {
        $q->where('user1_id', $this->id)->orWhere('user2_id', $this->id);
    })->where('sender_id', '!=', $this->id)->whereNull('read_at')->count();
}
```

- [ ] **Step 6: Run tests to verify they pass**

```bash
php artisan test --compact --filter=MessagesTest
```

Expected: PASS (4 tests).

- [ ] **Step 7: Commit**

```bash
git add app/Models/Conversation.php app/Models/Message.php app/Models/User.php tests/Feature/MessagesTest.php
git commit -m "feat: add Conversation and Message models with unread tracking"
```

---

## Task 3: Broadcast Events

**Files:**
- Create: `app/Events/MessageSent.php`
- Create: `app/Events/UserTyping.php`

- [ ] **Step 1: Create MessageSent event**

```bash
php artisan make:event MessageSent --no-interaction
```

Replace `app/Events/MessageSent.php`:

```php
<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Message $message,
        public int $recipientId,
    ) {
        $message->load('sender');
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.'.$this->recipientId)];
    }

    public function broadcastAs(): string
    {
        return 'MessageSent';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => [
                'id' => $this->message->id,
                'conversation_id' => $this->message->conversation_id,
                'body' => $this->message->body,
                'sender_id' => $this->message->sender_id,
                'sender' => [
                    'id' => $this->message->sender->id,
                    'name' => $this->message->sender->name,
                    'username' => $this->message->sender->username,
                    'avatar_url' => $this->message->sender->avatar_url,
                ],
                'created_at' => $this->message->created_at->diffForHumans(),
                'is_mine' => false,
            ],
        ];
    }
}
```

- [ ] **Step 2: Create UserTyping event**

```bash
php artisan make:event UserTyping --no-interaction
```

Replace `app/Events/UserTyping.php`:

```php
<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserTyping implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $conversationId,
        public int $senderId,
        public int $recipientId,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.'.$this->recipientId)];
    }

    public function broadcastAs(): string
    {
        return 'UserTyping';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'sender_id' => $this->senderId,
        ];
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add app/Events/MessageSent.php app/Events/UserTyping.php
git commit -m "feat: add MessageSent and UserTyping broadcast events"
```

---

## Task 4: MessagesController

**Files:**
- Create: `app/Http/Controllers/MessagesController.php`

- [ ] **Step 1: Add HTTP tests to MessagesTest.php**

Append to `tests/Feature/MessagesTest.php`:

```php
it('redirects to messages page when starting a conversation', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $response = $this->actingAs($userA)
        ->post("/conversations/with/{$userB->id}");

    $response->assertRedirect();
    $this->assertDatabaseHas('conversations', [
        'user1_id' => min($userA->id, $userB->id),
        'user2_id' => max($userA->id, $userB->id),
    ]);
});

it('cannot start a conversation with yourself', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post("/conversations/with/{$user->id}")
        ->assertStatus(422);
});

it('can send a message in a conversation', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $conv = Conversation::findOrCreateBetween($userA->id, $userB->id);

    $this->actingAs($userA)
        ->post("/messages/{$conv->id}", ['body' => 'Hello!'])
        ->assertRedirect();

    $this->assertDatabaseHas('messages', [
        'conversation_id' => $conv->id,
        'sender_id' => $userA->id,
        'body' => 'Hello!',
    ]);
});

it('cannot send a message in a conversation they do not belong to', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $userC = User::factory()->create();
    $conv = Conversation::findOrCreateBetween($userA->id, $userB->id);

    $this->actingAs($userC)
        ->post("/messages/{$conv->id}", ['body' => 'Hack!'])
        ->assertStatus(403);
});

it('message body is required', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $conv = Conversation::findOrCreateBetween($userA->id, $userB->id);

    $this->actingAs($userA)
        ->post("/messages/{$conv->id}", ['body' => ''])
        ->assertInvalid(['body']);
});

it('marks messages as read when conversation is viewed', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $conv = Conversation::findOrCreateBetween($userA->id, $userB->id);
    $conv->messages()->create(['sender_id' => $userA->id, 'body' => 'Hi', 'read_at' => null]);

    $this->actingAs($userB)->get("/messages/{$conv->id}");

    expect($conv->messages()->whereNull('read_at')->count())->toBe(0);
});

it('cannot view a conversation they do not belong to', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $userC = User::factory()->create();
    $conv = Conversation::findOrCreateBetween($userA->id, $userB->id);

    $this->actingAs($userC)
        ->get("/messages/{$conv->id}")
        ->assertStatus(403);
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test --compact --filter=MessagesTest
```

Expected: FAIL — routes not defined.

- [ ] **Step 3: Create MessagesController**

```bash
php artisan make:controller MessagesController --no-interaction
```

Replace `app/Http/Controllers/MessagesController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Events\UserTyping;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MessagesController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Messages', [
            'conversations' => $this->conversationsList(),
            'activeConversation' => null,
            'messages' => null,
        ]);
    }

    public function show(Conversation $conversation): Response
    {
        $user = auth()->user();
        abort_if(
            $conversation->user1_id !== $user->id && $conversation->user2_id !== $user->id,
            403
        );

        $conversation->messages()
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $otherUser = $conversation->otherUser($user->id);

        $messages = $conversation->messages()
            ->with('sender')
            ->orderBy('created_at')
            ->get()
            ->map(fn (Message $msg) => [
                'id' => $msg->id,
                'conversation_id' => $msg->conversation_id,
                'body' => $msg->body,
                'sender_id' => $msg->sender_id,
                'sender' => [
                    'id' => $msg->sender->id,
                    'name' => $msg->sender->name,
                    'username' => $msg->sender->username,
                    'avatar_url' => $msg->sender->avatar_url,
                ],
                'read_at' => $msg->read_at,
                'created_at' => $msg->created_at->diffForHumans(),
                'is_mine' => $msg->sender_id === $user->id,
            ]);

        return Inertia::render('Messages', [
            'conversations' => $this->conversationsList(),
            'activeConversation' => [
                'id' => $conversation->id,
                'other_user' => [
                    'id' => $otherUser->id,
                    'name' => $otherUser->name,
                    'username' => $otherUser->username,
                    'avatar_url' => $otherUser->avatar_url,
                ],
            ],
            'messages' => $messages,
        ]);
    }

    public function findOrCreate(User $user): RedirectResponse
    {
        abort_if($user->id === auth()->id(), 422);

        $conversation = Conversation::findOrCreateBetween(auth()->id(), $user->id);

        return to_route('messages.show', $conversation);
    }

    public function store(Request $request, Conversation $conversation): RedirectResponse
    {
        $user = auth()->user();
        abort_if(
            $conversation->user1_id !== $user->id && $conversation->user2_id !== $user->id,
            403
        );

        $request->validate(['body' => 'required|string|max:1000']);

        $message = $conversation->messages()->create([
            'sender_id' => $user->id,
            'body' => $request->body,
        ]);

        $recipientId = $conversation->user1_id === $user->id
            ? $conversation->user2_id
            : $conversation->user1_id;

        try {
            broadcast(new MessageSent($message, $recipientId));
        } catch (\Throwable) {
            // Reverb unavailable — message saved, broadcast skipped
        }

        return back();
    }

    public function typing(Conversation $conversation): JsonResponse
    {
        $user = auth()->user();
        abort_if(
            $conversation->user1_id !== $user->id && $conversation->user2_id !== $user->id,
            403
        );

        $recipientId = $conversation->user1_id === $user->id
            ? $conversation->user2_id
            : $conversation->user1_id;

        try {
            broadcast(new UserTyping($conversation->id, $user->id, $recipientId));
        } catch (\Throwable) {
            // Reverb unavailable
        }

        return response()->json(['ok' => true]);
    }

    /** @return array<int, array<string, mixed>> */
    private function conversationsList(): array
    {
        $user = auth()->user();

        return Conversation::with(['user1', 'user2', 'latestMessage'])
            ->where('user1_id', $user->id)
            ->orWhere('user2_id', $user->id)
            ->orderByDesc(
                Message::select('created_at')
                    ->whereColumn('conversation_id', 'conversations.id')
                    ->latest()
                    ->limit(1)
            )
            ->get()
            ->map(fn (Conversation $conv) => [
                'id' => $conv->id,
                'other_user' => [
                    'id' => $conv->otherUser($user->id)->id,
                    'name' => $conv->otherUser($user->id)->name,
                    'username' => $conv->otherUser($user->id)->username,
                    'avatar_url' => $conv->otherUser($user->id)->avatar_url,
                ],
                'latest_message' => $conv->latestMessage?->body,
                'latest_message_at' => $conv->latestMessage?->created_at?->diffForHumans(),
                'unread_count' => $conv->unreadCount($user->id),
            ])
            ->all();
    }
}
```

- [ ] **Step 4: Register routes — replace the stub in `routes/web.php`**

In `routes/web.php`, add this import and replace the messages stub:

```php
// Add at top with other use statements:
use App\Http\Controllers\MessagesController;

// Replace: Route::inertia('messages', 'Messages')->name('messages');
// With:
Route::get('messages', [MessagesController::class, 'index'])->name('messages.index');
Route::get('messages/{conversation}', [MessagesController::class, 'show'])->name('messages.show');
Route::post('messages/{conversation}', [MessagesController::class, 'store'])->name('messages.store');
Route::post('messages/{conversation}/typing', [MessagesController::class, 'typing'])->name('messages.typing');
Route::post('conversations/with/{user}', [MessagesController::class, 'findOrCreate'])->name('conversations.find-or-create');
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
php artisan test --compact --filter=MessagesTest
```

Expected: PASS (all 11 tests).

- [ ] **Step 6: Run Pint**

```bash
vendor/bin/pint app/Http/Controllers/MessagesController.php app/Events/MessageSent.php app/Events/UserTyping.php app/Models/Conversation.php app/Models/Message.php app/Models/User.php --format agent
```

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/MessagesController.php app/Models/ app/Events/MessageSent.php app/Events/UserTyping.php routes/web.php tests/Feature/MessagesTest.php
git commit -m "feat: add MessagesController with conversation find-or-create, send, and typing"
```

---

## Task 5: Shared Unread Count + Wayfinder Rebuild

**Files:**
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`

- [ ] **Step 1: Add `unread_messages_count` to shared props**

In `app/Http/Middleware/HandleInertiaRequests.php`, add to the `share()` array:

```php
'unread_messages_count' => $request->user()?->unreadMessagesCount() ?? 0,
```

The full `share()` return should now look like:

```php
return [
    ...parent::share($request),
    'name' => config('app.name'),
    'auth' => [
        'user' => $request->user(),
    ],
    'unread_notifications_count' => $request->user()?->unreadNotifications()->count() ?? 0,
    'unread_messages_count' => $request->user()?->unreadMessagesCount() ?? 0,
    'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
];
```

- [ ] **Step 2: Rebuild Wayfinder actions**

```bash
npm run build
```

This regenerates `resources/js/actions/App/Http/Controllers/MessagesController.ts` so the typed route helpers are available.

- [ ] **Step 3: Run Pint + full test suite**

```bash
vendor/bin/pint app/Http/Middleware/HandleInertiaRequests.php --format agent
php artisan test --compact
```

Expected: all tests pass.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Middleware/HandleInertiaRequests.php
git commit -m "feat: share unread_messages_count as global Inertia prop"
```

---

## Task 6: Realtime Store — Message Listeners

**Files:**
- Modify: `resources/js/lib/realtime.svelte.ts`

- [ ] **Step 1: Add message state and listeners**

Open `resources/js/lib/realtime.svelte.ts` and apply these changes:

Add type at top:

```typescript
type MessageType = {
    id: number;
    conversation_id: number;
    body: string;
    sender_id: number;
    sender: { id: number; name: string; username: string; avatar_url: string | null };
    created_at: string;
    is_mine: boolean;
};
```

Add state variables (after the existing state declarations):

```typescript
let newMessages = $state<Record<number, MessageType[]>>({});
let typingConversations = $state<Set<number>>(new Set());
let unreadMessagesIncrement = $state(0);
```

Inside `subscribeToUser`, add two more `.listen()` calls after the `.PostDeletedBroadcast` listener:

```typescript
        .listen('.MessageSent', (e: { message: MessageType }) => {
            const convId = e.message.conversation_id;
            newMessages = {
                ...newMessages,
                [convId]: [...(newMessages[convId] ?? []), e.message],
            };
            unreadMessagesIncrement += 1;
        })
        .listen('.UserTyping', (e: { conversation_id: number }) => {
            typingConversations = new Set([...typingConversations, e.conversation_id]);
            setTimeout(() => {
                typingConversations = new Set(
                    [...typingConversations].filter((id) => id !== e.conversation_id)
                );
            }, 3000);
        });
```

Inside `unsubscribeFromUser`, reset the new state:

```typescript
    newMessages = {};
    typingConversations = new Set();
    unreadMessagesIncrement = 0;
```

Add helper functions after `resetUnreadIncrement`:

```typescript
function consumeNewMessages(conversationId: number): MessageType[] {
    const msgs = [...(newMessages[conversationId] ?? [])];
    const { [conversationId]: _, ...rest } = newMessages;
    newMessages = rest;
    return msgs;
}

function resetUnreadMessagesIncrement(): void {
    unreadMessagesIncrement = 0;
}
```

Add to the exported `realtimeStore` object:

```typescript
    get newMessages() { return newMessages; },
    get typingConversations() { return typingConversations; },
    get unreadMessagesIncrement() { return unreadMessagesIncrement; },
    consumeNewMessages,
    resetUnreadMessagesIncrement,
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/lib/realtime.svelte.ts
git commit -m "feat: add MessageSent and UserTyping listeners to realtime store"
```

---

## Task 7: Messages.svelte Page

**Files:**
- Create: `resources/js/pages/Messages.svelte`

- [ ] **Step 1: Create the Messages page**

Create `resources/js/pages/Messages.svelte` with the full implementation:

```svelte
<script lang="ts">
    import { page, router } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import { useHttp } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import UserAvatar from '@/components/UserAvatar.svelte';
    import AnimatedThemeToggler from '@/components/animated-theme-toggler/AnimatedThemeToggler.svelte';
    import { realtimeStore } from '@/lib/realtime.svelte';
    import { destroy as logout } from '@/actions/Laravel/Fortify/Http/Controllers/AuthenticatedSessionController';
    import { index as messagesIndex, show as showConversation, store as sendMessage, typing as sendTyping } from '@/actions/App/Http/Controllers/MessagesController';
    import { Home, Bell, Sparkles, User, Send, MessageSquare, ArrowLeft } from 'lucide-svelte';
    import AnimatedGradientText from '@/components/AnimatedGradientText.svelte';
    import { Badge } from '@/components/ui/badge';

    type OtherUser = { id: number; name: string; username: string; avatar_url: string | null };
    type ConversationItem = {
        id: number;
        other_user: OtherUser;
        latest_message: string | null;
        latest_message_at: string | null;
        unread_count: number;
    };
    type MessageItem = {
        id: number;
        conversation_id: number;
        body: string;
        sender_id: number;
        sender: OtherUser;
        created_at: string;
        is_mine: boolean;
    };

    let {
        conversations,
        activeConversation = null,
        messages = null,
    }: {
        conversations: ConversationItem[];
        activeConversation: { id: number; other_user: OtherUser } | null;
        messages: MessageItem[] | null;
    } = $props();

    const auth = $derived(page.props.auth as any);
    const unreadCount = $derived(
        ((page.props as any).unread_notifications_count as number ?? 0) + realtimeStore.liveUnreadIncrement
    );
    const unreadMessagesCount = $derived(
        ((page.props as any).unread_messages_count as number ?? 0) + realtimeStore.unreadMessagesIncrement
    );

    let allMessages = $state<MessageItem[]>(messages ? [...messages] : []);
    let messageBody = $state('');
    let messagesContainer = $state<HTMLElement | null>(null);
    let typingTimer: ReturnType<typeof setTimeout> | null = null;

    const isTyping = $derived(
        activeConversation !== null &&
        realtimeStore.typingConversations.has(activeConversation.id)
    );

    const http = useHttp();

    // Receive real-time messages
    $effect(() => {
        if (!activeConversation) return;
        const incoming = realtimeStore.newMessages[activeConversation.id];
        if (incoming?.length) {
            untrack(() => {
                const msgs = realtimeStore.consumeNewMessages(activeConversation!.id);
                allMessages = [...allMessages, ...msgs];
                scrollToBottom();
            });
        }
    });

    // Reset messages when conversation changes
    $effect(() => {
        const msgs = messages;
        untrack(() => {
            allMessages = msgs ? [...msgs] : [];
            scrollToBottom();
        });
    });

    function scrollToBottom() {
        setTimeout(() => {
            if (messagesContainer) {
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }
        }, 50);
    }

    function handleKeydown(e: KeyboardEvent) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            submitMessage();
        }
    }

    function handleTypingInput() {
        if (!activeConversation) return;
        if (typingTimer) clearTimeout(typingTimer);
        typingTimer = setTimeout(() => {
            http.post(sendTyping(activeConversation!.id).url);
        }, 400);
    }

    function submitMessage() {
        if (!messageBody.trim() || !activeConversation) return;
        const body = messageBody;
        messageBody = '';

        // Optimistic: add to local list immediately
        const optimistic: MessageItem = {
            id: Date.now(),
            conversation_id: activeConversation.id,
            body,
            sender_id: auth.user.id,
            sender: auth.user,
            created_at: 'just now',
            is_mine: true,
        };
        allMessages = [...allMessages, optimistic];
        scrollToBottom();

        router.post(sendMessage(activeConversation.id).url, { body }, {
            preserveScroll: true,
            preserveState: true,
            only: [],
            onError: () => {
                allMessages = allMessages.filter((m) => m.id !== optimistic.id);
                messageBody = body;
            },
        });
    }
</script>

<AppHead title="Messages" />

<div class="min-h-screen bg-white dark:bg-black text-gray-900 dark:text-gray-100 flex justify-center font-sans">

    <!-- Left nav sidebar -->
    <header class="w-[275px] flex-col justify-between py-2 px-4 h-screen sticky top-0 hidden sm:flex shrink-0">
        <div class="flex flex-col gap-2 w-full">
            <div class="flex items-center gap-2">
                <a href="/dashboard" class="p-5 rounded-full w-fit transition-colors" aria-label="Home">
                    <img src="/images/Y-dark-remove.png" alt="Y" class="h-9 w-9 object-contain dark:invert-0 invert" />
                </a>
            </div>

            <nav class="flex flex-col gap-1 w-full mt-2">
                {#each [
                    { label: 'Home', icon: Home, href: '/dashboard' },
                    { label: 'Notifications', icon: Bell, href: '/notifications' },
                    { label: 'Messages', icon: MessageSquare, href: messagesIndex().url, active: true },
                    { label: 'Flok', icon: Sparkles, href: '/flock-ai' },
                    { label: 'Profile', icon: User, href: `/users/${auth?.user?.id}` },
                ] as item}
                    {@const Icon = item.icon}
                    <a
                        href={item.href}
                        class="flex items-center gap-5 p-3 rounded-full w-fit transition-colors {item.active ? 'text-gray-900 dark:text-white font-bold' : 'text-gray-500 dark:text-neutral-300 hover:text-gray-900 hover:bg-neutral-100 dark:hover:text-white dark:hover:bg-neutral-900'}"
                    >
                        {#if item.label === 'Notifications'}
                            <div class="relative">
                                <Icon class="w-6 h-6" />
                                {#if unreadCount > 0}
                                    <span class="absolute -top-1 -right-1 min-w-[16px] h-4 bg-blue-500 rounded-full text-white text-[9px] font-bold flex items-center justify-center px-0.5">{unreadCount > 99 ? '99+' : unreadCount}</span>
                                {/if}
                            </div>
                        {:else if item.label === 'Messages'}
                            <div class="relative">
                                <Icon class="w-6 h-6" />
                                {#if unreadMessagesCount > 0}
                                    <span class="absolute -top-1 -right-1 min-w-[16px] h-4 bg-blue-500 rounded-full text-white text-[9px] font-bold flex items-center justify-center px-0.5">{unreadMessagesCount > 99 ? '99+' : unreadMessagesCount}</span>
                                {/if}
                            </div>
                        {:else if item.label === 'Flok'}
                            <svg width="0" height="0" style="position:absolute;overflow:hidden">
                                <defs>
                                    <linearGradient id="flok-grad-msg" x1="0%" y1="0%" x2="100%" y2="100%">
                                        <stop offset="0%" stop-color="#60a5fa" />
                                        <stop offset="100%" stop-color="#a78bfa" />
                                    </linearGradient>
                                </defs>
                            </svg>
                            <Icon class="w-6 h-6" style="stroke: url(#flok-grad-msg)" />
                        {:else}
                            <Icon class="w-6 h-6" />
                        {/if}
                        {#if item.label === 'Flok'}
                            <AnimatedGradientText class="text-xl font-semibold hidden xl:inline">Flok AI</AnimatedGradientText>
                        {:else}
                            <span class="text-xl hidden xl:block">{item.label}</span>
                        {/if}
                    </a>
                {/each}
            </nav>
        </div>

        <div class="flex items-center gap-1 mb-4 w-full">
            <a
                href="/users/{auth?.user?.id}"
                class="flex items-center gap-3 p-3 rounded-full flex-1 min-w-0 transition-colors text-gray-500 dark:text-neutral-400 hover:text-gray-900 hover:bg-neutral-100 dark:hover:text-white dark:hover:bg-neutral-900"
            >
                <UserAvatar user={auth?.user} />
                <div class="flex-col items-start hidden xl:flex min-w-0">
                    <span class="font-bold text-sm truncate">{auth?.user?.name ?? 'User'}</span>
                    <span class="text-neutral-500 text-sm">@{auth?.user?.username ?? 'username'}</span>
                </div>
            </a>
            <button
                onclick={() => router.post(logout().url)}
                class="hidden xl:flex ml-auto shrink-0 group"
                aria-label="Log out"
            >
                <Badge variant="destructive" class="group-hover:font-bold">Log out</Badge>
            </button>
        </div>
    </header>

    <!-- Conversation list -->
    <div class="w-full sm:w-[350px] border-x border-neutral-200 dark:border-neutral-800 min-h-screen flex flex-col {activeConversation ? 'hidden sm:flex' : 'flex'}">
        <div class="sticky top-0 bg-white/80 dark:bg-black/80 backdrop-blur-md z-10 border-b border-neutral-200 dark:border-neutral-800 px-4 py-3 flex items-center justify-between">
            <h1 class="font-extrabold text-xl">Messages</h1>
            <AnimatedThemeToggler class="p-2 rounded-full hover:bg-neutral-100 dark:hover:bg-neutral-900 transition-colors" />
        </div>

        {#if conversations.length === 0}
            <div class="flex flex-col items-center justify-center flex-1 px-8 text-center py-20">
                <MessageSquare class="w-12 h-12 text-neutral-300 dark:text-neutral-700 mb-4" />
                <p class="font-bold text-lg">No conversations yet</p>
                <p class="text-neutral-500 text-sm mt-1">Message someone from their profile page.</p>
            </div>
        {:else}
            {#each conversations as conv}
                {@const isActive = activeConversation?.id === conv.id}
                <a
                    href={showConversation(conv.id).url}
                    class="flex items-center gap-3 px-4 py-3 border-b border-neutral-200 dark:border-neutral-800 hover:bg-neutral-50 dark:hover:bg-neutral-950 transition-colors {isActive ? 'bg-neutral-100 dark:bg-neutral-900' : ''}"
                >
                    <div class="relative shrink-0">
                        <UserAvatar user={conv.other_user} />
                        {#if conv.unread_count > 0}
                            <span class="absolute -top-0.5 -right-0.5 w-3 h-3 bg-blue-500 rounded-full border-2 border-white dark:border-black"></span>
                        {/if}
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-[15px] truncate {conv.unread_count > 0 ? '' : 'font-semibold'}">{conv.other_user.name}</span>
                            {#if conv.latest_message_at}
                                <span class="text-neutral-400 text-xs shrink-0 ml-2">{conv.latest_message_at}</span>
                            {/if}
                        </div>
                        {#if conv.latest_message}
                            <p class="text-neutral-500 text-sm truncate {conv.unread_count > 0 ? 'font-semibold text-gray-900 dark:text-white' : ''}">{conv.latest_message}</p>
                        {/if}
                    </div>
                </a>
            {/each}
        {/if}
    </div>

    <!-- Thread panel -->
    {#if activeConversation}
        <div class="flex-1 border-r border-neutral-200 dark:border-neutral-800 flex flex-col min-h-screen max-h-screen">
            <!-- Thread header -->
            <div class="sticky top-0 bg-white/80 dark:bg-black/80 backdrop-blur-md z-10 border-b border-neutral-200 dark:border-neutral-800 px-4 py-3 flex items-center gap-3">
                <a href={messagesIndex().url} class="sm:hidden p-1 rounded-full hover:bg-neutral-100 dark:hover:bg-neutral-900 transition-colors">
                    <ArrowLeft class="w-5 h-5" />
                </a>
                <a href="/users/{activeConversation.other_user.id}" class="flex items-center gap-3 hover:opacity-80 transition-opacity">
                    <UserAvatar user={activeConversation.other_user} />
                    <div>
                        <p class="font-bold text-[15px]">{activeConversation.other_user.name}</p>
                        <p class="text-neutral-500 text-sm">@{activeConversation.other_user.username}</p>
                    </div>
                </a>
            </div>

            <!-- Messages -->
            <div
                bind:this={messagesContainer}
                class="flex-1 overflow-y-auto px-4 py-4 flex flex-col gap-2"
            >
                {#if allMessages.length === 0}
                    <div class="flex flex-col items-center justify-center flex-1 text-center py-12">
                        <p class="text-neutral-400 text-sm">Say hello to {activeConversation.other_user.name}!</p>
                    </div>
                {:else}
                    {#each allMessages as msg (msg.id)}
                        <div class="flex {msg.is_mine ? 'justify-end' : 'justify-start'} gap-2">
                            {#if !msg.is_mine}
                                <UserAvatar user={msg.sender} size="xs" class="mt-1 shrink-0" />
                            {/if}
                            <div
                                class="max-w-[70%] px-4 py-2.5 rounded-2xl text-[15px] leading-snug
                                    {msg.is_mine
                                        ? 'bg-blue-500 text-white rounded-br-sm'
                                        : 'bg-neutral-100 dark:bg-neutral-800 text-gray-900 dark:text-white rounded-bl-sm'}"
                            >
                                {msg.body}
                            </div>
                        </div>
                    {/each}

                    {#if isTyping}
                        <div class="flex justify-start gap-2">
                            <UserAvatar user={activeConversation.other_user} size="xs" class="mt-1 shrink-0" />
                            <div class="bg-neutral-100 dark:bg-neutral-800 rounded-2xl rounded-bl-sm px-4 py-3 flex gap-1 items-center">
                                <span class="w-2 h-2 bg-neutral-400 rounded-full animate-bounce" style="animation-delay:0ms"></span>
                                <span class="w-2 h-2 bg-neutral-400 rounded-full animate-bounce" style="animation-delay:150ms"></span>
                                <span class="w-2 h-2 bg-neutral-400 rounded-full animate-bounce" style="animation-delay:300ms"></span>
                            </div>
                        </div>
                    {/if}
                {/if}
            </div>

            <!-- Input -->
            <div class="border-t border-neutral-200 dark:border-neutral-800 px-4 py-3 flex gap-3 items-end bg-white dark:bg-black sticky bottom-0">
                <textarea
                    bind:value={messageBody}
                    onkeydown={handleKeydown}
                    oninput={handleTypingInput}
                    placeholder="Start a new message"
                    rows="1"
                    class="flex-1 bg-neutral-100 dark:bg-neutral-900 rounded-2xl px-4 py-2.5 text-[15px] placeholder-neutral-400 outline-none resize-none max-h-32 focus:ring-1 focus:ring-blue-500 transition-all"
                ></textarea>
                <button
                    onclick={submitMessage}
                    disabled={!messageBody.trim()}
                    class="bg-blue-500 hover:bg-blue-600 disabled:opacity-40 disabled:cursor-not-allowed text-white rounded-full p-2.5 transition-colors shrink-0"
                    aria-label="Send"
                >
                    <Send class="w-4 h-4" />
                </button>
            </div>
        </div>
    {:else}
        <!-- Empty state when no conversation selected (desktop only) -->
        <div class="flex-1 hidden sm:flex flex-col items-center justify-center text-center px-8 border-r border-neutral-200 dark:border-neutral-800">
            <MessageSquare class="w-16 h-16 text-neutral-200 dark:text-neutral-800 mb-4" />
            <p class="font-extrabold text-2xl mb-2">Select a message</p>
            <p class="text-neutral-500 text-[15px]">Choose from your existing conversations or start a new one from someone's profile.</p>
        </div>
    {/if}

</div>
```

- [ ] **Step 2: Build assets to generate Wayfinder types**

```bash
npm run build
```

- [ ] **Step 3: Commit**

```bash
git add resources/js/pages/Messages.svelte
git commit -m "feat: add Messages.svelte full DM page with inbox, thread, and typing indicator"
```

---

## Task 8: Add Messages Nav to Existing Pages + Message Button on Profile

**Files:**
- Modify: `resources/js/pages/Dashboard.svelte`
- Modify: `resources/js/pages/Notifications.svelte`
- Modify: `resources/js/pages/users/Show.svelte`

- [ ] **Step 1: Add Messages nav item to Dashboard.svelte**

In `resources/js/pages/Dashboard.svelte`, add the `MessageSquare` import to the lucide import line:

```typescript
import { Home, Search, Bell, BellOff, Sparkles, User, Feather, ImagePlus, X, Shield, MessageSquare } from 'lucide-svelte';
```

Add the import for messagesIndex Wayfinder action at the top of the script:

```typescript
import { index as messagesIndex } from '@/actions/App/Http/Controllers/MessagesController';
```

Add the unread messages count derived value:

```typescript
const unreadMessagesCount = $derived(
    ((page.props as any).unread_messages_count as number ?? 0) + realtimeStore.unreadMessagesIncrement
);
```

In the nav items array inside the template, add Messages after the Notifications entry:

```svelte
{ label: 'Messages', icon: MessageSquare, href: messagesIndex().url },
```

In the nav template where `item.label === 'Notifications'` is handled, add a Messages case:

```svelte
{:else if item.label === 'Messages'}
    <div class="relative">
        <Icon class="w-6 h-6" />
        {#if unreadMessagesCount > 0}
            <span class="absolute -top-1 -right-1 min-w-[16px] h-4 bg-blue-500 rounded-full text-white text-[9px] font-bold flex items-center justify-center px-0.5">{unreadMessagesCount > 99 ? '99+' : unreadMessagesCount}</span>
        {/if}
    </div>
```

- [ ] **Step 2: Add Messages nav item to Notifications.svelte**

In `resources/js/pages/Notifications.svelte`, add the same import:

```typescript
import { MessageSquare } from 'lucide-svelte'; // add to existing lucide import
import { index as messagesIndex } from '@/actions/App/Http/Controllers/MessagesController';
```

Add to the nav items array:

```svelte
{ label: 'Messages', icon: MessageSquare, href: messagesIndex().url },
```

Add unread messages count derived:

```typescript
const unreadMessagesCount = $derived(
    ((page.props as any).unread_messages_count as number ?? 0) + realtimeStore.unreadMessagesIncrement
);
```

Add the badge rendering in the nav (same pattern as the Notifications badge).

- [ ] **Step 3: Add "Message" button to users/Show.svelte**

In `resources/js/pages/users/Show.svelte`, add the import:

```typescript
import { findOrCreate as findOrCreateConversation } from '@/actions/App/Http/Controllers/MessagesController';
```

In the profile action buttons section (find `{:else}` after `{#if isOwnProfile}`), add a Message button alongside the Follow button:

```svelte
{:else}
    <div class="flex gap-2">
        <button
            onclick={() => router.post(findOrCreateConversation(profileUser.id).url)}
            class="border border-neutral-300 dark:border-neutral-700 font-bold rounded-full px-4 py-1.5 text-sm hover:bg-neutral-100 dark:hover:bg-neutral-900 transition-colors"
        >
            Message
        </button>
        <button
            onclick={() => router.post(`/users/${profileUser.id}/follow`, {}, { preserveScroll: true, only: ['isFollowing'] })}
            class="bg-gray-900 dark:bg-white text-white dark:text-black font-bold rounded-full px-4 py-1.5 text-sm hover:bg-gray-700 dark:hover:bg-neutral-200 transition-colors"
        >
            {isFollowing ? 'Following' : 'Follow'}
        </button>
    </div>
{/if}
```

- [ ] **Step 4: Run full test suite**

```bash
php artisan test --compact
```

Expected: all tests pass.

- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/Dashboard.svelte resources/js/pages/Notifications.svelte resources/js/pages/users/Show.svelte
git commit -m "feat: add Messages nav link with unread badge and Message button on profiles"
```

---

## Task 9: Run Pint + Final Verification

- [ ] **Step 1: Run Pint across all changed PHP files**

```bash
vendor/bin/pint app/ --format agent
```

- [ ] **Step 2: Run full test suite**

```bash
php artisan test --compact
```

Expected: all tests pass, no errors.

- [ ] **Step 3: Verify Reverb is running and test manually**
  - Start the dev server: `composer run dev` (now includes Reverb automatically)
  - Log in as User A, open `/messages`
  - Visit User B's profile, click "Message"
  - Send a message — it should appear immediately in the thread
  - In a separate browser tab as User B, open the conversation — the message badge should show

- [ ] **Step 4: Final commit**

```bash
git add -A
git commit -m "feat: complete direct messages with real-time delivery and typing indicators"
```
