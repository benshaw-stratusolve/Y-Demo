# Notification Sounds

> How Y plays a short audio clip when an in-app toast notification appears — with lazy audio object caching to avoid repeated DOM creation.

---

## Concept Explained

When `notifications.add()` is called (e.g. after a successful post, or when a flash toast arrives), `playNotificationSound()` is called with the notification type. It maps the type to a sound file, creates (or reuses) an `HTMLAudioElement` from a cache, and plays it. The cache avoids creating a new `Audio` object on every notification.

---

## How it's Used in Y

File: `resources/js/lib/notification-sounds.ts`

### Sound map

```ts
const soundMap: Record<AppNotification['type'], string> = {
    success: '/sounds/success-noti.wav',
    info:    '/sounds/success-noti.wav',
    warning: '/sounds/alert-noti.mp3',
    error:   '/sounds/alert-noti.mp3',
    message: '/sounds/message-noti.wav',
};
```

Three actual sound files. `success` and `info` share a file; `warning` and `error` share another.

### Audio object cache

```ts
const cache: Partial<Record<string, HTMLAudioElement>> = {};

function getAudio(src: string): HTMLAudioElement {
    if (!cache[src]) {
        const audio = new Audio(src);
        audio.volume = 0.5;  // 50% volume
        cache[src] = audio;
    }
    return cache[src]!;
}
```

The cache is module-level — it lives for the lifetime of the page. Creating `new Audio()` has a small cost; caching means only 3 objects are ever created regardless of how many notifications fire.

### Playing a sound

```ts
export function playNotificationSound(type: AppNotification['type']): void {
    if (typeof window === 'undefined' || typeof Audio === 'undefined') return;
    try {
        const audio = getAudio(soundMap[type]);
        audio.currentTime = 0;   // rewind to start (so rapid notifications replay)
        audio.play().catch(() => {});  // browsers block autoplay; ignore silently
    } catch {
        // Never crash the notification system over a sound
    }
}
```

`audio.currentTime = 0` is crucial — if a notification fires while a sound is still playing, resetting to 0 replays from the start instead of playing over the current position.

### Integration in `notifications.svelte.ts`

```ts
function add(notification: Omit<AppNotification, 'id' | 'time'>): void {
    const id = uid();
    items.unshift({ ...notification, id, time: 'just now' });
    if (items.length > 5) { items.splice(5); }
    playNotificationSound(notification.type);   // ← here
    setTimeout(() => remove(id), 5000);
}
```

Every call to `notifications.add()` — whether from `flash-toast.ts`, a post success callback, or an error handler — plays the appropriate sound automatically.

---

## Key Code Snippet

```ts
// Rewind + play pattern for rapid-fire notifications:
audio.currentTime = 0;
audio.play().catch(() => {}); // browsers may block without user interaction
```

---

## Why This Approach

The `try/catch` wrapping `.play()` is required because modern browsers block audio playback until the user has interacted with the page. On first load, a notification sound would throw a `NotAllowedError`. Swallowing this error silently is correct — a missing sound is not a problem; a crashed notification system would be. The module-level cache ensures the browser only parses each audio file once.

---

## Related Notes

- [[Login + Custom Response]]
- [[Svelte 5 Runes]]
