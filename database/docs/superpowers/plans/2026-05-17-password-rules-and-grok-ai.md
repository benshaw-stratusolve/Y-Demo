# Password Rules & Grok AI Integration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Enforce 8-char/number/symbol password rules in all environments, and replace FlockAI's random placeholder responses with real Grok AI (xAI) responses.

**Architecture:** Password rules are centralised in `AppServiceProvider::Password::defaults()` so every form that uses `Password::default()` automatically inherits them. The Grok AI integration adds an `xai` service config entry and replaces the hardcoded array in `FlockAIController::chat()` with an `Http` call to `https://api.x.ai/v1`. The Svelte frontend is updated to send conversation history so Grok has context across turns.

**Tech Stack:** Laravel Http facade, xAI API (OpenAI-compatible), Svelte 5 `$state`, Pest 4

---

## File Map

| Action | File | Responsibility |
|--------|------|----------------|
| Modify | `app/Providers/AppServiceProvider.php` | Change `Password::defaults()` to apply in all envs |
| Modify | `config/services.php` | Add `xai.key` config entry |
| Modify | `.env.example` | Document `XAI_API_KEY` |
| Modify | `app/Http/Controllers/FlockAIController.php` | Call xAI API instead of returning random responses |
| Modify | `resources/js/pages/FlockAI.svelte` | Send message history in chat requests |
| Modify | `tests/Feature/Auth/RegistrationTest.php` | Use compliant password |
| Modify | `tests/Feature/Settings/SecurityTest.php` | Use compliant password |
| Create | `tests/Feature/FlockAIChatTest.php` | Test chat endpoint |

---

## Task 1: Update password validation rules

**Files:**
- Modify: `app/Providers/AppServiceProvider.php:58-65`

- [ ] **Step 1: Change Password::defaults() to enforce rules in every environment**

Replace lines 58-65 in `app/Providers/AppServiceProvider.php`:

```php
Password::defaults(fn (): Password => Password::min(8)
    ->numbers()
    ->symbols()
);
```

The previous code returned `null` in non-production (no rules at all). This change enforces 8+ chars, at least one number, and at least one symbol in every environment.

- [ ] **Step 2: Run pint to format**

```bash
vendor/bin/pint app/Providers/AppServiceProvider.php --format agent
```

---

## Task 2: Fix tests broken by the new password rules

Existing tests use passwords like `'password'` and `'new-password'` that don't satisfy the new rules. They must be updated.

**Files:**
- Modify: `tests/Feature/Auth/RegistrationTest.php`
- Modify: `tests/Feature/Settings/SecurityTest.php`

- [ ] **Step 1: Run affected tests to confirm they now fail**

```bash
php artisan test --compact --filter="new users can register|password can be updated"
```

Expected: both tests FAIL with validation errors.

- [ ] **Step 2: Update RegistrationTest**

In `tests/Feature/Auth/RegistrationTest.php`, change:

```php
test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'P@ssword1',
        'password_confirmation' => 'P@ssword1',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});
```

- [ ] **Step 3: Update SecurityTest passwords**

In `tests/Feature/Settings/SecurityTest.php`, change the `password can be updated` test:

```php
test('password can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'P@ssword1',
            'password_confirmation' => 'P@ssword1',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('security.edit'));

    expect(Hash::check('P@ssword1', $user->refresh()->password))->toBeTrue();
});
```

Also update `password cannot be updated to the same password` test — `'password'` is the user factory default (no number/symbol), so we need a user whose current password already complies, OR just test with a compliant "same password":

```php
test('password cannot be updated to the same password', function () {
    $user = User::factory()->create(['password' => Hash::make('P@ssword1')]);

    $response = $this
        ->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'P@ssword1',
            'password' => 'P@ssword1',
            'password_confirmation' => 'P@ssword1',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('security.edit'));
});
```

Also update `correct password must be provided to update password`:

```php
test('correct password must be provided to update password', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'P@ssword1',
            'password_confirmation' => 'P@ssword1',
        ]);

    $response
        ->assertSessionHasErrors('current_password')
        ->assertRedirect(route('security.edit'));
});
```

- [ ] **Step 4: Run tests to confirm they pass**

```bash
php artisan test --compact --filter="RegistrationTest|SecurityTest"
```

Expected: all green.

- [ ] **Step 5: Commit**

```bash
git add app/Providers/AppServiceProvider.php tests/Feature/Auth/RegistrationTest.php tests/Feature/Settings/SecurityTest.php
git commit -m "feat: enforce 8-char/number/symbol password rules in all environments"
```

---

## Task 3: Add Grok AI service config

**Files:**
- Modify: `config/services.php`
- Modify: `.env.example`

- [ ] **Step 1: Add xai entry to config/services.php**

Append to the array in `config/services.php`:

```php
'xai' => [
    'key' => env('XAI_API_KEY'),
    'model' => env('XAI_MODEL', 'grok-3'),
],
```

- [ ] **Step 2: Document in .env.example**

Add after the last existing key in `.env.example`:

```
XAI_API_KEY=
XAI_MODEL=grok-3
```

- [ ] **Step 3: Add your actual key to .env**

The user must add their xAI API key (from https://console.x.ai) to `.env`:

```
XAI_API_KEY=xai-xxxxxxxxxxxxxxxx
```

- [ ] **Step 4: Commit**

```bash
git add config/services.php .env.example
git commit -m "feat: add xAI service config for Grok AI"
```

---

## Task 4: Integrate Grok AI in FlockAIController

**Files:**
- Modify: `app/Http/Controllers/FlockAIController.php`
- Create: `tests/Feature/FlockAIChatTest.php`

- [ ] **Step 1: Write the failing test first**

```bash
php artisan make:test --pest FlockAIChatTest
```

Replace the generated file contents at `tests/Feature/FlockAIChatTest.php`:

```php
<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

test('chat endpoint requires authentication', function () {
    $this->post(route('flock-ai.chat'), ['message' => 'Hello'])
        ->assertRedirect(route('login'));
});

test('chat endpoint validates message', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('flock-ai.chat'), [])
        ->assertSessionHasErrors('message');
});

test('chat endpoint returns grok response', function () {
    Http::fake([
        'api.x.ai/*' => Http::response([
            'choices' => [
                ['message' => ['content' => 'Hello from Grok!']],
            ],
        ], 200),
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson(route('flock-ai.chat'), [
            'message' => 'Hello FlockAI',
            'history' => [],
        ]);

    $response->assertOk()->assertJsonPath('message', 'Hello from Grok!');

    Http::assertSent(fn ($request) =>
        str_contains($request->url(), 'api.x.ai') &&
        $request['model'] === config('services.xai.model')
    );
});

test('chat endpoint passes history to grok', function () {
    Http::fake([
        'api.x.ai/*' => Http::response([
            'choices' => [
                ['message' => ['content' => 'Got your history!']],
            ],
        ], 200),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('flock-ai.chat'), [
            'message' => 'Follow up question',
            'history' => [
                ['role' => 'assistant', 'content' => 'I said something earlier.'],
                ['role' => 'user', 'content' => 'First question'],
            ],
        ])
        ->assertOk();

    Http::assertSent(fn ($request) =>
        count($request['messages']) === 4 // system + 2 history + current user
    );
});

test('chat endpoint returns error message when xai api fails', function () {
    Http::fake([
        'api.x.ai/*' => Http::response([], 500),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('flock-ai.chat'), ['message' => 'Hello'])
        ->assertStatus(500)
        ->assertJsonPath('message', "Sorry, I'm having trouble connecting right now. Please try again.");
});
```

- [ ] **Step 2: Run tests to confirm they fail**

```bash
php artisan test --compact --filter="FlockAIChatTest"
```

Expected: tests for "returns grok response" and "passes history" fail (controller still returns random strings).

- [ ] **Step 3: Replace FlockAIController::chat() with Grok AI implementation**

Replace the full `app/Http/Controllers/FlockAIController.php`:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;

class FlockAIController extends Controller
{
    private const SYSTEM_PROMPT = "You are FlockAI, an intelligent AI assistant embedded in Y — a modern social platform. Help users with content ideas, conversation starters, community building, trend analysis, and general questions. Be concise, friendly, and direct. Keep responses focused unless the user asks for detail.";

    public function index(): Response
    {
        return Inertia::render('FlockAI');
    }

    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'history' => ['sometimes', 'array', 'max:20'],
            'history.*.role' => ['required', 'string', 'in:user,assistant'],
            'history.*.content' => ['required', 'string', 'max:2000'],
        ]);

        $messages = [
            ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
            ...$request->input('history', []),
            ['role' => 'user', 'content' => $request->input('message')],
        ];

        $response = Http::withToken(config('services.xai.key'))
            ->post('https://api.x.ai/v1/chat/completions', [
                'model' => config('services.xai.model'),
                'messages' => $messages,
                'max_tokens' => 500,
            ]);

        if ($response->failed()) {
            return response()->json(
                ['message' => "Sorry, I'm having trouble connecting right now. Please try again."],
                500
            );
        }

        return response()->json([
            'message' => $response->json('choices.0.message.content'),
        ]);
    }
}
```

- [ ] **Step 4: Run tests to confirm they pass**

```bash
php artisan test --compact --filter="FlockAIChatTest"
```

Expected: all green.

- [ ] **Step 5: Run pint**

```bash
vendor/bin/pint app/Http/Controllers/FlockAIController.php --format agent
```

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/FlockAIController.php tests/Feature/FlockAIChatTest.php
git commit -m "feat: integrate Grok AI into FlockAI chat endpoint"
```

---

## Task 5: Update FlockAI.svelte to send conversation history

**Files:**
- Modify: `resources/js/pages/FlockAI.svelte`

The Svelte frontend currently sends only `{ message: text }`. Grok needs prior turns to maintain context across a conversation.

- [ ] **Step 1: Update sendMessage() to include history**

In `resources/js/pages/FlockAI.svelte`, replace the `fetch` call body (around line 61-70):

```typescript
async function sendMessage() {
    const text = input.trim();
    if (!text || isTyping) return;

    input = '';
    const priorMessages = messages.slice(); // snapshot before adding current user msg
    messages = [...messages, { id: Date.now(), role: 'user', content: text }];
    isTyping = true;
    await scrollToBottom();

    try {
        const res = await fetch('/flock-ai/chat', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                message: text,
                history: priorMessages.map(m => ({ role: m.role, content: m.content })),
            }),
        });
        const data = await res.json();
        messages = [...messages, { id: Date.now() + 1, role: 'assistant', content: data.message }];
    } catch {
        messages = [...messages, { id: Date.now() + 1, role: 'assistant', content: 'Sorry, something went wrong. Please try again.' }];
    } finally {
        isTyping = false;
        await scrollToBottom();
    }
}
```

- [ ] **Step 2: Build and verify in browser**

```bash
npm run build
```

Then visit `/flock-ai`, click "Start Chatting", and send a message. Confirm:
- The reply comes from Grok AI (not a placeholder)
- A follow-up question gets a contextually aware reply
- The typing indicator shows while waiting

- [ ] **Step 3: Commit**

```bash
git add resources/js/pages/FlockAI.svelte
git commit -m "feat: send conversation history to Grok AI for contextual replies"
```

---

## Final verification

- [ ] Run the full auth and chat test suites:

```bash
php artisan test --compact --filter="RegistrationTest|SecurityTest|FlockAIChatTest"
```

Expected: all green.

- [ ] Verify the registration form rejects a password without a number/symbol (manual check or browser test).
