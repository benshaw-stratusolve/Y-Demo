# Inertia Shared Props

> How global data (logged-in user, unread count) is accessed in Svelte components using Inertia's `page` store.

---

## Concept Explained

Inertia exposes a reactive `page` object that holds all props for the current page — both the page-specific props from the controller and the shared props from `HandleInertiaRequests`. Any component in the tree can import and read `page.props` directly without prop drilling. In Svelte 5, `$derived()` is used to keep a local reference in sync with changes.

---

## How it's Used in Y

### Accessing shared props in any component

```ts
// In any .svelte file
import { page } from '@inertiajs/svelte';

const auth = $derived(page.props.auth as any);
const unreadCount = $derived((page.props as any).unread_notifications_count as number ?? 0);
```

`page` is a Svelte store/rune that updates whenever Inertia navigates. `$derived()` ensures `auth` and `unreadCount` automatically update when the underlying `page.props` changes — for example, after a partial reload that refreshes `unread_notifications_count`.

### Where it's used in Y

| Component | Shared prop accessed | Purpose |
|---|---|---|
| `Dashboard.svelte` | `auth.user` | Display username, avatar, ban check |
| `Dashboard.svelte` | `unread_notifications_count` | Bell badge count |
| `Notifications.svelte` | `auth.user` | Display logged-in user info |
| `NavUser.svelte` | `auth.user` | Sidebar user card |
| `settings/Profile.svelte` | `auth.user` | Pre-fill form with current values |

### Page-specific vs shared props

```ts
// Page-specific — only available on Dashboard:
let { posts, trending, topAccounts } = $props();

// Shared — available everywhere:
const auth = $derived(page.props.auth as any);
```

Page-specific props come through `$props()`. Shared props come through `page.props`. Never mix them up — `$props()` only has what the controller passed to `Inertia::render()`.

### Partial reloads update shared props too

```ts
// On the Notifications page — polls every 30s
usePoll(30000, { only: ['notifications', 'unread_count'] });
```

When Inertia does a partial reload with `only: [...]`, the unlisted shared props (`auth.user`, `unread_notifications_count`) are also re-evaluated by the middleware and merged — keeping the badge fresh.

---

## Key Code Snippet

```ts
import { page } from '@inertiajs/svelte';

// In script — reactive reference to shared auth data
const auth = $derived(page.props.auth as any);

// In template
// {auth?.user?.name} — safely access nested properties
```

---

## Why This Approach

Shared props avoid the alternative of threading `auth` and notification counts through every component as explicit props. The `page` store is global and reactive — any component that reads it stays in sync automatically. TypeScript casting (`as any`) is a current limitation because Inertia's Svelte types don't automatically know your app's shared prop shape; a typed `$page` declaration in `resources/js/types/index.d.ts` could improve this.

---

## Related Notes

- [[Shared Data (Inertia Middleware)]]
- [[Svelte 5 Runes]]
- [[Prefetching + Polling]]
