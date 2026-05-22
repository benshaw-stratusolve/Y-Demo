# Laravel + Inertia + Svelte

> How the three layers of Y's architecture connect and hand data to each other.

---

## Concept Explained

Laravel handles routing and business logic. Instead of returning HTML (Blade) or raw JSON (REST API), controllers call `Inertia::render('PageName', $props)`. Inertia serialises those props as JSON and sends them to the browser, where the matching Svelte component receives them as typed props. On subsequent navigations, only the JSON props are exchanged — no full page reload, no separate API contract.

---

## How it's Used in Y

Every page follows the same pattern:

1. **Route** (`routes/web.php`) — maps a URL to a controller
2. **Controller** — fetches/builds data, calls `Inertia::render()`
3. **Svelte page** (`resources/js/pages/`) — receives data as `$props()`

The Svelte component name passed to `Inertia::render()` must match the file path under `resources/js/pages/`. For example:

| Controller call | Svelte file |
|---|---|
| `Inertia::render('Dashboard', [...])` | `resources/js/pages/Dashboard.svelte` |
| `Inertia::render('posts/Show', [...])` | `resources/js/pages/posts/Show.svelte` |
| `Inertia::render('auth/Login', [...])` | `resources/js/pages/auth/Login.svelte` |

---

## Key Code Snippet

```php
// app/Http/Controllers/DashboardController.php
public function index(Request $request): Response
{
    $posts = Post::with(['user', 'replies.user'])
        ->withCount(['likes', 'replies', 'reposts'])
        ->whereNull('parent_post_id')
        ->paginate(10);

    return Inertia::render('Dashboard', [
        'posts'   => $posts,
        'trending' => $trending,
    ]);
}
```

```svelte
<!-- resources/js/pages/Dashboard.svelte -->
<script lang="ts">
    let { posts, trending }: { posts: PaginatedPosts; trending: Post[] } = $props();
</script>
```

---

## Why This Approach

Inertia eliminates the need for a separate API layer. Auth, sessions, CSRF protection, and validation all work exactly as they do in a classic Laravel app — because they *are* a classic Laravel app under the hood. You never need to manually serialise models into JSON DTOs or write API endpoints just to serve your own frontend.

---

## Related Notes

- [[Request Lifecycle]]
- [[Shared Data (Inertia Middleware)]]
- [[Svelte 5 Runes]]
- [[Wayfinder (Type-Safe Routes)]]
