# Anime.js Animations Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.
>
> **Prerequisite:** The Direct Messages plan (`2026-05-25-direct-messages.md`) must be fully implemented before Tasks 6–7 (message bubble and typing indicator animations).

**Goal:** Add professional, spring-physics-driven animations throughout Y — post feed entrance, delete collapse, like pulse, notification badge spring, tab wipe, and DM message bubble entrance.

**Architecture:** All animation helpers are collected in `resources/js/lib/anime-utils.ts`. Components import only the helpers they need. Post entrance is driven by a Svelte action (`use:postEnter`) attached to each post element. Delete collapse is triggered by the existing `realtimeStore.deletedPostIds` effect — the post element is animated before being removed from state. All other animations are triggered imperatively via `$effect` or event handlers.

**Tech Stack:** Anime.js v4 (named exports), Svelte 5 actions, Tailwind v4, TypeScript.

---

## File Map

**Create:**
- `resources/js/lib/anime-utils.ts` — reusable animation functions

**Modify:**
- `package.json` — add animejs dependency
- `resources/js/pages/Dashboard.svelte` — post entrance, delete collapse, like pulse, badge spring, tab wipe
- `resources/js/pages/Notifications.svelte` — badge spring
- `resources/js/pages/Messages.svelte` — message bubble entrance, Anime.js typing dots

---

## Task 1: Install Anime.js

- [ ] **Step 1: Install the package**

```bash
npm install animejs
```

- [ ] **Step 2: Verify the import works**

Create a temporary check (delete after confirming):

```bash
node -e "const a = require('./node_modules/animejs/lib/anime.cjs.js'); console.log(typeof a.default === 'function' ? 'OK v3' : 'OK v4')"
```

If the output shows `OK v3`, Anime.js v3 is installed. Use this import in all files:

```typescript
import anime from 'animejs/lib/anime.es.js';
```

If the above fails, use this import (v4 named exports):

```typescript
import { animate, stagger, createSpring } from 'animejs';
```

The rest of this plan uses **v3 syntax**. If v4 is installed, replace `anime({...})` with `animate(target, props)` and `anime.spring(...)` with `createSpring(...)`.

- [ ] **Step 3: Commit**

```bash
git add package.json package-lock.json
git commit -m "chore: add animejs dependency"
```

---

## Task 2: Animation Utilities Library

**Files:**
- Create: `resources/js/lib/anime-utils.ts`

- [ ] **Step 1: Create the utilities file**

Create `resources/js/lib/anime-utils.ts`:

```typescript
import anime from 'animejs/lib/anime.es.js';

/**
 * Slide a new post in from above with spring physics.
 * Used for real-time Reverb post arrivals.
 */
export function animatePostIn(el: HTMLElement): void {
    anime({
        targets: el,
        translateY: [-24, 0],
        opacity: [0, 1],
        duration: 500,
        easing: 'spring(1, 80, 12, 0)',
    });
}

/**
 * Stagger-animate a batch of posts in on initial page load.
 * @param els — NodeList or array of post elements
 */
export function animatePostsStagger(els: HTMLElement[]): void {
    anime({
        targets: els,
        translateY: [-16, 0],
        opacity: [0, 1],
        duration: 500,
        delay: anime.stagger(60, { start: 0 }),
        easing: 'easeOutCubic',
    });
}

/**
 * Collapse a post's height to zero, then call onComplete.
 * Used before removing a deleted post from state.
 */
export function animatePostOut(el: HTMLElement, onComplete: () => void): void {
    const height = el.offsetHeight;
    anime({
        targets: el,
        height: [height, 0],
        opacity: [1, 0],
        paddingTop: [null, 0],
        paddingBottom: [null, 0],
        marginTop: [null, 0],
        marginBottom: [null, 0],
        duration: 280,
        easing: 'easeInCubic',
        complete: onComplete,
    });
}

/**
 * Spring-scale the like/heart button on click.
 */
export function animateLikePulse(el: HTMLElement): void {
    anime({
        targets: el,
        scale: [1, 1.45, 1],
        duration: 450,
        easing: 'spring(1, 80, 10, 0)',
    });
}

/**
 * Spring-bounce a badge element when its count increments.
 */
export function animateBadgeBounce(el: HTMLElement): void {
    anime({
        targets: el,
        scale: [1, 1.6, 1],
        duration: 500,
        easing: 'spring(1, 80, 10, 0)',
    });
}

/**
 * Slide the feed content out left / in from right when switching tabs,
 * or out right / in from left for the reverse direction.
 */
export function animateTabTransition(
    el: HTMLElement,
    direction: 'left' | 'right',
): void {
    const fromX = direction === 'left' ? 40 : -40;
    anime({
        targets: el,
        translateX: [fromX, 0],
        opacity: [0, 1],
        duration: 320,
        easing: 'easeOutCubic',
    });
}

/**
 * Slide a chat message bubble in from left (received) or right (sent).
 */
export function animateMessageBubble(el: HTMLElement, isMine: boolean): void {
    anime({
        targets: el,
        translateX: [isMine ? 20 : -20, 0],
        opacity: [0, 1],
        duration: 350,
        easing: 'spring(1, 90, 14, 0)',
    });
}

/**
 * Start a looping stagger bounce on three typing-indicator dots.
 * Returns a cancel function to stop the loop.
 */
export function startTypingDots(container: HTMLElement): () => void {
    const anim = anime({
        targets: container.querySelectorAll<HTMLElement>('.typing-dot'),
        translateY: [0, -7, 0],
        duration: 600,
        loop: true,
        delay: anime.stagger(120),
        easing: 'easeInOutSine',
    });
    return () => anim.pause();
}
```

- [ ] **Step 2: Build to verify no type errors**

```bash
npm run build 2>&1 | grep -i error || echo "No errors"
```

Expected: `No errors`.

- [ ] **Step 3: Commit**

```bash
git add resources/js/lib/anime-utils.ts
git commit -m "feat: add anime-utils.ts with post, like, badge, tab, and DM animation helpers"
```

---

## Task 3: Dashboard — Post Entrance + Staggered Initial Load

**Files:**
- Modify: `resources/js/pages/Dashboard.svelte`

- [ ] **Step 1: Import animation utilities**

At the top of the `<script>` in `Dashboard.svelte`, add:

```typescript
import { animatePostIn, animatePostsStagger } from '@/lib/anime-utils';
```

- [ ] **Step 2: Mark real-time posts as new, stagger initial load**

In the existing `$effect` that handles `realtimeStore.newPosts`, mark incoming posts:

```typescript
$effect(() => {
    const incoming = realtimeStore.newPosts;
    if (incoming.length > 0) {
        untrack(() => {
            const fresh = realtimeStore.consumeNewPosts().map((p: any) => ({ ...p, _isNew: true }));
            allPosts = [...fresh, ...allPosts];
        });
    }
});
```

Add a `$effect` to stagger-animate posts on initial mount (runs once after DOM is available):

```typescript
let hasMounted = $state(false);

$effect(() => {
    if (hasMounted) return;
    hasMounted = true;
    const els = Array.from(
        document.querySelectorAll<HTMLElement>('[data-post-id]')
    );
    if (els.length > 0) animatePostsStagger(els);
});
```

- [ ] **Step 3: Add `data-post-id` attribute and entrance action to each post element**

Add a Svelte action before the `{#each allPosts as post}` block:

```typescript
function postEnter(node: HTMLElement, isNew: boolean) {
    if (isNew) animatePostIn(node);
    return {};
}
```

On the post wrapper `<div>` inside the `{#each allPosts as post}` loop, add:

```svelte
<div
    id="post-{post.id}"
    data-post-id={post.id}
    use:postEnter={post._isNew ?? false}
    role="link"
    ...existing classes...
>
```

(The existing `id` will also be needed by the delete animation in Task 4 — add it now.)

- [ ] **Step 4: Build and verify**

```bash
npm run build 2>&1 | grep -i error || echo "No errors"
```

- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/Dashboard.svelte
git commit -m "feat: animate real-time post entrance and stagger initial feed load"
```

---

## Task 4: Dashboard — Post Delete Collapse

**Files:**
- Modify: `resources/js/pages/Dashboard.svelte`

- [ ] **Step 1: Import animatePostOut**

Add `animatePostOut` to the existing import from `@/lib/anime-utils`:

```typescript
import { animatePostIn, animatePostsStagger, animatePostOut } from '@/lib/anime-utils';
```

- [ ] **Step 2: Replace the delete effect with an animated version**

Find the existing `$effect` that watches `realtimeStore.deletedPostIds`:

```typescript
// Replace this:
$effect(() => {
    const deleted = realtimeStore.deletedPostIds;
    if (deleted.size > 0) {
        untrack(() => {
            allPosts = allPosts.filter((p: any) => !deleted.has(p.id));
        });
    }
});
```

Replace with:

```typescript
$effect(() => {
    const deleted = realtimeStore.deletedPostIds;
    if (deleted.size > 0) {
        untrack(() => {
            for (const postId of deleted) {
                const el = document.getElementById(`post-${postId}`);
                if (el) {
                    // Prevent double-animation if effect fires again before removal
                    if (el.dataset.removing) continue;
                    el.dataset.removing = 'true';
                    animatePostOut(el, () => {
                        allPosts = allPosts.filter((p: any) => p.id !== postId);
                    });
                } else {
                    allPosts = allPosts.filter((p: any) => p.id !== postId);
                }
            }
        });
    }
});
```

- [ ] **Step 3: Build and verify**

```bash
npm run build 2>&1 | grep -i error || echo "No errors"
```

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/Dashboard.svelte
git commit -m "feat: animate post deletion with height collapse before DOM removal"
```

---

## Task 5: Dashboard — Like Pulse, Badge Spring, Tab Wipe

**Files:**
- Modify: `resources/js/pages/Dashboard.svelte`

- [ ] **Step 1: Import remaining utilities**

Update the import:

```typescript
import { animatePostIn, animatePostsStagger, animatePostOut, animateLikePulse, animateBadgeBounce, animateTabTransition } from '@/lib/anime-utils';
```

- [ ] **Step 2: Like pulse on click**

Find the `toggleLike` function and add a `heartEl` parameter for the button:

```typescript
function toggleLike(post: any, heartBtn?: HTMLElement) {
    if (heartBtn) animateLikePulse(heartBtn);
    // ... rest of existing toggleLike logic unchanged ...
    const liked = localLikes[post.id]?.liked ?? post.liked_by_user;
    const count = localLikes[post.id]?.count ?? post.likes_count;
    localLikes[post.id] = { liked: !liked, count: count + (liked ? -1 : 1) };
    router.post(likePost(post.id).url, {}, {
        preserveScroll: true,
        preserveState: true,
        onError: () => { delete localLikes[post.id]; },
    });
}
```

On the like button in the template, pass `event.currentTarget` as the element:

```svelte
<button
    type="button"
    class="..."
    onclick={(e) => toggleLike(post, e.currentTarget as HTMLElement)}
>
```

- [ ] **Step 3: Badge spring when unread count increments**

Add a `$state` ref for the badge element and a `$effect` that animates on change:

```typescript
let notifBadgeEl = $state<HTMLElement | null>(null);
let prevUnreadCount = unreadCount;

$effect(() => {
    const count = unreadCount;
    if (count > prevUnreadCount && notifBadgeEl) {
        animateBadgeBounce(notifBadgeEl);
    }
    prevUnreadCount = count;
});
```

On the notification badge `<span>` in the nav, bind the ref:

```svelte
<span
    bind:this={notifBadgeEl}
    class="absolute -top-1 -right-1 min-w-[16px] h-4 bg-blue-500 ..."
>
```

- [ ] **Step 4: Tab content wipe on switch**

Add a ref for the feed container:

```typescript
let feedContainerEl = $state<HTMLElement | null>(null);
```

Update the `switchTab` function to animate before/after switching:

```typescript
function switchTab(tab: string) {
    if (tab === activeTab) return;
    const direction = tabs.findIndex(t => t.id === tab) > tabs.findIndex(t => t.id === activeTab)
        ? 'left'
        : 'right';
    router.get('/dashboard', { tab }, {
        preserveScroll: true,
        replace: true,
        only: ['posts', 'activeTab'],
        onSuccess: () => {
            if (feedContainerEl) animateTabTransition(feedContainerEl, direction);
        },
    });
}
```

Bind the feed container in the template. Find the `<div>` that wraps `{#each allPosts}` and add:

```svelte
<div bind:this={feedContainerEl}>
    {#if isDiscoveryFeed}...{/if}
    {#each allPosts as post}...{/each}
</div>
```

- [ ] **Step 5: Build and verify**

```bash
npm run build 2>&1 | grep -i error || echo "No errors"
```

- [ ] **Step 6: Commit**

```bash
git add resources/js/pages/Dashboard.svelte
git commit -m "feat: add like pulse, notification badge spring, and tab content wipe animations"
```

---

## Task 6: Messages.svelte — Message Bubble Entrance

> Requires: Direct Messages plan fully implemented.

**Files:**
- Modify: `resources/js/pages/Messages.svelte`

- [ ] **Step 1: Import animation utilities**

Add to the `<script>` imports in `Messages.svelte`:

```typescript
import { animateMessageBubble } from '@/lib/anime-utils';
```

- [ ] **Step 2: Add a Svelte action for bubble entrance**

Inside the `<script>` block, add:

```typescript
function bubbleEnter(node: HTMLElement, isMine: boolean) {
    animateMessageBubble(node, isMine);
    return {};
}
```

- [ ] **Step 3: Apply the action to message bubble wrappers**

On the outer `<div>` of each message in the `{#each allMessages as msg}` loop, add `use:bubbleEnter`:

```svelte
{#each allMessages as msg (msg.id)}
    <div
        use:bubbleEnter={msg.is_mine}
        class="flex {msg.is_mine ? 'justify-end' : 'justify-start'} gap-2"
    >
```

Note: The action runs for every message on initial load. To only animate new messages (not history), pass a flag. In the `$effect` that appends incoming messages, mark them:

```typescript
const msgs = realtimeStore.consumeNewMessages(activeConversation!.id).map(m => ({ ...m, _isNew: true }));
allMessages = [...allMessages, ...msgs];
```

Then update the action:

```typescript
function bubbleEnter(node: HTMLElement, opts: { isMine: boolean; isNew?: boolean }) {
    if (opts.isNew) animateMessageBubble(node, opts.isMine);
    return {};
}
```

And update the usage:

```svelte
use:bubbleEnter={{ isMine: msg.is_mine, isNew: msg._isNew ?? false }}
```

- [ ] **Step 4: Build and verify**

```bash
npm run build 2>&1 | grep -i error || echo "No errors"
```

- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/Messages.svelte
git commit -m "feat: animate new DM message bubble entrance with spring physics"
```

---

## Task 7: Messages.svelte — Anime.js Typing Dots

> Replaces the CSS `animate-bounce` typing indicator with Anime.js staggered dots.

**Files:**
- Modify: `resources/js/pages/Messages.svelte`

- [ ] **Step 1: Import startTypingDots**

Add to the `<script>` imports:

```typescript
import { startTypingDots } from '@/lib/anime-utils';
```

- [ ] **Step 2: Add a Svelte action for the typing indicator container**

```typescript
function typingDots(node: HTMLElement) {
    const cancel = startTypingDots(node);
    return { destroy: cancel };
}
```

- [ ] **Step 3: Update the typing indicator markup**

Replace the existing typing indicator block in the template:

```svelte
{#if isTyping}
    <div class="flex justify-start gap-2">
        <UserAvatar user={activeConversation.other_user} size="xs" class="mt-1 shrink-0" />
        <div
            use:typingDots
            class="bg-neutral-100 dark:bg-neutral-800 rounded-2xl rounded-bl-sm px-4 py-3 flex gap-1.5 items-center h-10"
        >
            <span class="typing-dot w-2 h-2 bg-neutral-400 rounded-full"></span>
            <span class="typing-dot w-2 h-2 bg-neutral-400 rounded-full"></span>
            <span class="typing-dot w-2 h-2 bg-neutral-400 rounded-full"></span>
        </div>
    </div>
{/if}
```

The dots now use `typing-dot` class (no `animate-bounce`) and the animation is driven entirely by Anime.js with staggered timing.

- [ ] **Step 4: Build and verify**

```bash
npm run build 2>&1 | grep -i error || echo "No errors"
```

- [ ] **Step 5: Run full test suite**

```bash
php artisan test --compact
```

Expected: all tests pass (animations are frontend-only, no backend impact).

- [ ] **Step 6: Final commit**

```bash
git add resources/js/pages/Messages.svelte resources/js/lib/anime-utils.ts
git commit -m "feat: replace CSS typing dots with Anime.js staggered animation"
```

---

## Self-Review Checklist

- [x] **animatePostOut** uses `el.dataset.removing` guard to prevent double-animation if the effect fires twice before DOM removal
- [x] **Post entrance action** only runs `animatePostIn` for `_isNew` posts, not initial render (handled by the stagger effect instead)
- [x] **Tab wipe** animates the `feedContainerEl` wrapper *after* Inertia updates the DOM via `onSuccess`
- [x] **Badge spring** guards with `count > prevUnreadCount` to not animate on first load or on decrease
- [x] **Typing dots action** returns `{ destroy: cancel }` so the Anime.js loop is properly torn down when the indicator is hidden
- [x] **Message bubble action** only animates `_isNew` messages, not the full history on conversation open
