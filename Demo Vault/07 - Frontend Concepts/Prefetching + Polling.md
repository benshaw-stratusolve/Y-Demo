# Prefetching + Polling

> Two Inertia v3 performance techniques — prefetching loads the next page in the background before the user clicks; polling refreshes the current page's data on an interval.

---

## Concept Explained

**Prefetching** exploits the gap between hover and click — when a user hovers over a link for 75ms, Inertia silently fetches the next page's data. By the time they click, the data is cached and the navigation feels instant.

**Polling** repeatedly calls `router.reload()` at a set interval to keep the current page's props fresh — the Inertia equivalent of `setInterval(() => fetch(...))`, but integrated with the router and with automatic tab-background throttling.

---

## How it's Used in Y

### Prefetching — `resources/js/components/NavMain.svelte`

The main sidebar links (Dashboard, Notifications, FlockAI) use `prefetch={true}` on the Inertia `<Link>` component:

```svelte
<Link
    {...props}
    href={toUrl(item.href)}
    class={props.class}
    prefetch={true}
>
```

`prefetch={true}` triggers a background fetch after 75ms of hover. Data is cached for 30 seconds by default.

### Prefetching — `resources/js/layouts/settings/Layout.svelte`

The settings tab links (Profile, Security, Appearance) also prefetch:

```svelte
<Link href={toUrl(item.href)} class={props.class} prefetch={true}>
    {item.title}
</Link>
```

### Polling — replaced by Reverb

Polling (`usePoll`) was previously used on the Dashboard and Notifications pages:

```ts
// Removed — no longer used
usePoll(30000, { only: ['notifications', 'unread_count'] });
usePoll(30000, { only: ['posts'] });
```

Both have been replaced by Laravel Reverb WebSocket push. Notifications now arrive instantly via the `.NotificationSent` event; new posts appear in the Dashboard feed via `.PostBroadcast`. Polling fired requests every 30 seconds even when nothing had changed — WebSocket push only sends data when something actually happens.

---

## Key Code Snippet

```ts
// Three prefetch strategies available in Svelte (via use:inertia or <Link>):
prefetch={true}       // fires after hovering 75ms — used in Y
prefetch="click"      // fires on mousedown (just before click)
prefetch="mount"      // fires when element renders (aggressive)

// Cache options:
cacheFor="30s"        // default — evicted after 30 seconds
cacheFor={['30s', '1m']}  // stale-while-revalidate: serve stale for up to 1m, revalidate in background
```

---

## Why This Approach

**Prefetch placement:** The sidebar (NavMain) and settings tabs are always visible and have a small number of fixed routes — perfect candidates. Prefetching post links or user profile links would be wasteful: there are many of them, they're data-heavy, and the data changes frequently.

**Polling replaced by WebSockets:** Polling is easy to set up but fires requests even when nothing has changed. With Reverb, data is pushed only when an event actually occurs — more efficient and genuinely real-time (zero delay, not up-to-30-second delay).

---

## Related Notes

- [[Request Lifecycle]]
- [[Notifications Feature]]
- [[Laravel Reverb (WebSockets)]]
- [[Real-Time Store (realtime.svelte.ts)]]
- [[Inertia Shared Props]]
- [[Laravel + Inertia + Svelte]]
