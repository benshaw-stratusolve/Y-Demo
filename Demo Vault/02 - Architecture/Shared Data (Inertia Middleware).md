# Shared Data (Inertia Middleware)

> How data that every page needs — the logged-in user, unread notification count — is injected automatically into every Inertia response.

---

## Concept Explained

`HandleInertiaRequests` extends Inertia's base middleware and overrides the `share()` method. Whatever array you return from `share()` is merged into every single page's props automatically — you never have to pass `auth.user` from each controller individually. On the frontend, this data is accessible via `page.props` from anywhere in the component tree.

---

## How it's Used in Y

File: `app/Http/Middleware/HandleInertiaRequests.php`

Four values are shared globally:

| Key | Value | Used by |
|---|---|---|
| `name` | App name from config | Page titles |
| `auth.user` | Authenticated User model (or null) | Avatar, username, ban check |
| `unread_notifications_count` | Count of unread DB notifications | Sidebar badge |
| `sidebarOpen` | Boolean from cookie | Sidebar open/closed state on load |

---

## Key Code Snippet

```php
// app/Http/Middleware/HandleInertiaRequests.php
public function share(Request $request): array
{
    return [
        ...parent::share($request),
        'name' => config('app.name'),
        'auth' => [
            'user' => $request->user(),
        ],
        'unread_notifications_count' => $request->user()
            ?->unreadNotifications()->count() ?? 0,
        'sidebarOpen' => ! $request->hasCookie('sidebar_state')
            || $request->cookie('sidebar_state') === 'true',
    ];
}
```

On the frontend, access it from any Svelte component:

```ts
import { page } from '@inertiajs/svelte';

const auth = $derived(page.props.auth as any);
const unreadCount = $derived((page.props as any).unread_notifications_count as number);
```

---

## Why This Approach

Without shared props, every controller would need to pass `auth()->user()` and notification counts manually. By centralising this in the middleware, the data is always current, always available, and controllers stay focused on their own concerns. The `unread_notifications_count` is particularly important — it powers the sidebar badge that appears on every authenticated page, and it updates on every navigation without any extra work.

---

## Related Notes

- [[Laravel + Inertia + Svelte]]
- [[Request Lifecycle]]
- [[Inertia Shared Props]]
- [[Notifications Feature]]
