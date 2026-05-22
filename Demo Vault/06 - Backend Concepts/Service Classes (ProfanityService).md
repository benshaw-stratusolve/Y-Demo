# Service Classes (ProfanityService)

> A plain PHP class that encapsulates a single piece of business logic — checking text for banned words — and is injected wherever needed.

---

## Concept Explained

A service class is a plain PHP object that holds business logic that doesn't naturally belong in a model or controller. It has no parent class, no traits — just methods. Laravel's service container automatically injects it into any controller method or form request constructor that type-hints it.

---

## How it's Used in Y

File: `app/Services/ProfanityService.php`

### The class

```php
class ProfanityService
{
    private array $words = [
        'fuck', 'shit', 'bitch', 'asshole', ... // word list
    ];

    public function contains(string $text): bool
    {
        foreach ($this->words as $word) {
            if (preg_match('/\b'.preg_quote($word, '/').'\b/i', $text)) {
                return true;
            }
        }
        return false;
    }
}
```

The regex uses `\b` word boundaries so "assets" doesn't match "ass", and the `i` flag makes it case-insensitive. `preg_quote()` escapes any regex special characters in the word list.

### Injection points

**In `PostController::store()` and `PostController::reply()`:**
```php
public function store(Request $request, ProfanityService $profanity): RedirectResponse
{
    if ($request->filled('body') && $profanity->contains($request->body)) {
        return $this->handleStrike($user, 'Your post contains inappropriate language.');
    }
    // ...
}
```

**In `ProfileController::update()`:**
```php
public function update(ProfileUpdateRequest $request, ProfanityService $profanity): RedirectResponse
{
    if ($request->filled('bio') && $profanity->contains($request->bio)) {
        return back()->withErrors(['bio' => 'Your bio contains inappropriate language.'])->withInput();
    }
    // ...
}
```

Profile violations don't issue strikes — only posts and replies do.

---

## Key Code Snippet

```php
// The word-boundary regex prevents false positives like "assets" → "ass"
if (preg_match('/\b' . preg_quote($word, '/') . '\b/i', $text)) {
    return true;
}
```

---

## Why This Approach

Encapsulating this logic in a service class means it can be injected wherever needed (posts, replies, bios, and potentially comments in the future) without copying the word list or regex logic. Laravel's automatic dependency injection resolves the class from the container without any registration — plain PHP classes are resolved by reflection. Testing is straightforward: instantiate `ProfanityService` directly and call `contains()`.

---

## Related Notes

- [[Profanity Strike + Ban System]]
- [[Form Requests]]
- [[Posts (replies + reposts)]]
