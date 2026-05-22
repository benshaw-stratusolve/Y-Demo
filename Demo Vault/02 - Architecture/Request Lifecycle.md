# Request Lifecycle

> What happens from URL entry to pixels on screen — for both first-load and subsequent Inertia navigations.

---

## Concept Explained

Inertia has two distinct request modes. The **first visit** (or a hard refresh) returns a full HTML document containing the Svelte app shell plus the serialised page props embedded in a `<div>` data attribute. Every **subsequent navigation** (clicking an Inertia `<Link>`) sends an XHR with the `X-Inertia: true` header and receives only JSON props for the new page — no HTML, no full reload.

---

## How it's Used in Y

### First request

1. Browser hits `https://y.test/dashboard`
2. Laravel middleware stack runs: `HandleAppearance` reads the appearance cookie and shares it with Blade; `HandleInertiaRequests` runs and merges shared props (auth user, unread count, sidebar state)
3. `DashboardController::index()` builds posts, trending, topAccounts data
4. `Inertia::render('Dashboard', $props)` is called
5. Laravel renders `resources/views/app.blade.php` — the root Blade template — with the page data embedded in the `#app` div's `data-page` attribute
6. Vite-built JS boots in the browser, `createInertiaApp()` reads the `data-page` attribute and mounts the `Dashboard.svelte` component with the props

### Subsequent navigation (e.g. clicking "Notifications")

1. Inertia intercepts the link click
2. Sends `GET /notifications` with header `X-Inertia: true` and `X-Inertia-Version: <asset-hash>`
3. Laravel middleware runs again (shared props are re-evaluated)
4. `NotificationsController::index()` returns `Inertia::render('Notifications', $props)`
5. Because the request has `X-Inertia: true`, Laravel returns **only JSON** — not full HTML
6. Inertia swaps out the current Svelte component for `Notifications.svelte`, updates the browser URL, and merges the new props

---

## Key Code Snippet

```blade
{{-- resources/views/app.blade.php --}}
<html @class(['dark' => ($appearance ?? 'system') == 'dark'])>
<head>
    @vite(['resources/css/app.css', 'resources/js/app.ts'])
    <x-inertia::head>
        <title>{{ config('app.name') }}</title>
    </x-inertia::head>
</head>
<body>
    <div id="splash-screen">...</div>
    @inertia
</body>
</html>
```

`@inertia` renders the `<div id="app" data-page="...">` that bootstraps the Svelte app.

---

## Why This Approach

This hybrid model gives you SSR-like first-load behaviour (the initial HTML is meaningful) with SPA-like subsequent navigation (only data crosses the wire). Asset versioning (`X-Inertia-Version`) ensures that when you deploy new frontend assets, Inertia triggers a full reload automatically instead of serving stale JS.

---

## Related Notes

- [[Laravel + Inertia + Svelte]]
- [[Shared Data (Inertia Middleware)]]
- [[Prefetching + Polling]]
