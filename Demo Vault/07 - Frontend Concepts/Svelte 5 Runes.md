# Svelte 5 Runes

> Svelte 5's new reactivity system — compiler-level signals that replace `let`, `$:`, and stores with explicit, predictable primitives.

---

## Concept Explained

Svelte 5 introduces "Runes" — special syntax that looks like function calls but is processed by the Svelte compiler. They make reactivity explicit rather than implicit. Instead of any `let` being reactive and `$:` labels marking derived state, you now declare intent with `$state()`, `$derived()`, `$props()`, and `$effect()`. This makes data flow easier to follow and reason about.

---

## How it's Used in Y

### `$props()` — receives data from the parent (or from Inertia)

```ts
// resources/js/pages/Dashboard.svelte
let {
    posts,
    trending,
    topAccounts = [],  // default value
    isDiscoveryFeed = false,
    activeTab = 'forYou',
}: {
    posts: { data: any[]; current_page: number; last_page: number; };
    // ...
} = $props();
```

This is how Inertia page props land in the component — `$props()` destructures the object Inertia passes.

### `$state()` — reactive local state

```ts
let postBody = $state('');
let postImage = $state<File | null>(null);
let searchOpen = $state(false);
let localLikes = $state<Record<number, { liked: boolean; count: number }>>({});
```

Any mutation to a `$state` variable triggers re-render of any template that reads it.

### `$derived()` — computed values that stay in sync

```ts
const auth = $derived(page.props.auth as any);
const unreadCount = $derived((page.props as any).unread_notifications_count as number ?? 0);

// AvatarUpload.svelte
const initials = $derived(
    userName.split(' ').map((n) => n[0]).join('').toUpperCase().slice(0, 2)
);
const displayUrl = $derived(previewUrl ?? avatarUrl ?? null);
```

`$derived()` re-computes automatically when any reactive value it reads changes. It cannot be assigned to — it's always computed.

### `$effect()` — side effects that re-run when dependencies change

Used for syncing external systems (DOM manipulation, localStorage) when reactive state changes. Less common in Y because most side effects happen in event handlers, but `theme.svelte.ts` uses it for the system theme listener.

---

## Key Code Snippet

```ts
// The four runes in one component:
let { notifications, unread_count } = $props();   // from Inertia / parent

let selectedNotif = $state<Notification | null>(null);  // local reactive state

const hasUnread = $derived(unread_count > 0);       // computed, always current

$effect(() => {
    document.title = hasUnread ? `(${unread_count}) Notifications` : 'Notifications';
});
```

---

## Why This Approach

The old Svelte 4 reactivity (`$:`) was magic — any variable `let x = 0` was reactive, and `$: y = x * 2` created implicit dependencies that were hard to trace. Runes make the contract explicit: if you see `$state()`, it's reactive; if you see a plain `let`, it's not. This predictability helps avoid subtle bugs where a variable becomes reactive accidentally or where a `$:` block runs at unexpected times.

---

## Related Notes

- [[Laravel + Inertia + Svelte]]
- [[Inertia Shared Props]]
- [[Inertia Form Component]]
