# FlockAI Chat (Gemini)

> An in-app AI chat assistant powered by Google's Gemini API, with conversational memory via a client-side history array.

---

## Concept Explained

FlockAI is a simple wrapper around Google's Gemini `generateContent` REST API. The frontend maintains the conversation history in local state and sends the full history with every message — Gemini is stateless, so the conversation context must be re-sent each time. The system prompt establishes FlockAI's persona and purpose.

---

## How it's Used in Y

Files: `app/Http/Controllers/FlockAIController.php`, `resources/js/pages/FlockAI.svelte`

### Backend (`FlockAIController::chat`)

**Validation:**
```php
$request->validate([
    'message' => ['required', 'string', 'max:2000'],
    'history' => ['sometimes', 'array', 'max:20'],       // cap history length
    'history.*.role'    => ['required', 'string', 'in:user,assistant'],
    'history.*.content' => ['required', 'string', 'max:2000'],
]);
```

**History mapping:** Gemini uses `model` for the assistant role (not `assistant`):
```php
$contents = collect($request->input('history', []))
    ->map(fn (array $msg) => [
        'role'  => $msg['role'] === 'assistant' ? 'model' : 'user',
        'parts' => [['text' => $msg['content']]],
    ])
    ->push(['role' => 'user', 'parts' => [['text' => $request->input('message')]]])
    ->values()->all();
```

**API call:**
```php
$model = config('services.gemini.model');
$response = Http::post(
    "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=".config('services.gemini.key'),
    [
        'system_instruction' => ['parts' => [['text' => self::SYSTEM_PROMPT]]],
        'contents'           => $contents,
    ]
);
```

**System prompt:**
> "You are FlockAI, an intelligent AI assistant embedded in Y — a modern social platform. Help users with content ideas, conversation starters, community building, trend analysis..."

### Key config keys

- `services.gemini.key` — API key from `.env`
- `services.gemini.model` — Gemini model identifier (e.g. `gemini-2.0-flash`)

---

## Key Code Snippet

```php
return response()->json([
    'message' => $response->json('candidates.0.content.parts.0.text'),
]);
```

The Gemini response has a deeply nested path: `candidates[0].content.parts[0].text`. Laravel's `->json()` method accepts dot-notation paths to extract nested values.

If the API call fails, a user-friendly error message is returned with a 500 status — the frontend handles this gracefully.

---

## Why This Approach

Sending history with every request is simpler than maintaining server-side sessions and requires no database storage for conversations. Capping history at 20 messages (`max:20`) prevents unbounded token usage. Using Laravel's `Http` facade (instead of Guzzle directly) gives a clean fluent API with automatic JSON encoding/decoding.

---

## Related Notes

- [[Laravel + Inertia + Svelte]]
- [[Svelte 5 Runes]]
