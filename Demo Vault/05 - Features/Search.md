# Search

> A live search overlay that queries users and posts, with LIKE injection protection and fuzzy spelling suggestions.

---

## Concept Explained

Search is a JSON API endpoint (`GET /search`) that returns users and posts matching a query string. It is not an Inertia page — it returns `JsonResponse` because it's called by a client-side overlay component that renders results in a slide-over panel without navigating. Results are scoped by type (profiles vs. posts), and a suggestion engine generates spelling-close alternatives.

---

## How it's Used in Y

File: `app/Http/Controllers/SearchController.php`

### Request validation

```php
$validated = $request->validate([
    'q'    => ['nullable', 'string', 'max:80'],
    'type' => ['nullable', 'string', Rule::in(['profiles', 'posts'])],
]);
```

Queries under 2 characters return empty results immediately (no DB hit).

### LIKE injection escaping

Raw user input in a LIKE pattern can exploit `%` and `_` wildcards:

```php
$escapedQuery = str_replace(
    ['\\', '%', '_'],
    ['\\\\', '\\%', '\\_'],
    $query
);
$likeQuery = "%{$escapedQuery}%";
```

The backslash is replaced first to avoid double-escaping.

### User search with relevance ordering

```php
User::query()
    ->where(fn ($b) => $b
        ->where('name', 'like', $likeQuery)
        ->orWhere('username', 'like', $likeQuery))
    ->orderByRaw('CASE WHEN username LIKE ? THEN 0 WHEN name LIKE ? THEN 1 ELSE 2 END',
        ["{$query}%", "{$query}%"])
    ->orderBy('name')
    ->limit(5)
    ->get(['id', 'name', 'username', 'avatar']);
```

Exact prefix matches rank first, partial matches second.

### Spelling suggestions

The `suggestionsFor()` method collects candidate strings (names/usernames for profile search, words extracted from recent post bodies for post search), then ranks them by Levenshtein distance to the query. Prefix matches get a +10 Levenshtein bonus to rank them lower than substring matches.

---

## Key Code Snippet

```php
private function suggestionDistance(string $query, string $candidate): int
{
    if (Str::contains($candidate, $query)) {
        return 0;          // exact substring match — best rank
    }
    if (Str::startsWith($candidate, mb_substr($query, 0, 1))) {
        return levenshtein($query, $candidate); // same first letter
    }
    return levenshtein($query, $candidate) + 10; // penalise distant starts
}
```

---

## Why This Approach

Returning JSON (not an Inertia response) allows the search overlay to fetch results without triggering a page navigation — the URL doesn't change and the user stays in context. LIKE escaping prevents a user typing `%` from matching every row in the database. The Levenshtein-based suggestions provide a forgiving search experience without needing a dedicated search engine.

---

## Related Notes

- [[Laravel + Inertia + Svelte]]
- [[Users]]
- [[Posts (replies + reposts)]]
