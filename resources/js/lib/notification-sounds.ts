import type { AppNotification } from '@/lib/notifications.svelte';

const STORAGE_KEY = 'notification-sound-enabled';

export function isSoundEnabled(): boolean {
    if (typeof localStorage === 'undefined') return true;
    const stored = localStorage.getItem(STORAGE_KEY);
    return stored === null ? true : stored === 'true';
}

export function setSoundEnabled(enabled: boolean): void {
    localStorage.setItem(STORAGE_KEY, String(enabled));
}

const soundMap: Record<AppNotification['type'], string> = {
    success: '/sounds/success-noti.wav',
    info: '/sounds/success-noti.wav',
    warning: '/sounds/alert-noti.mp3',
    error: '/sounds/alert-noti.mp3',
    message: '/sounds/message-noti.wav',
};

const cache: Partial<Record<string, HTMLAudioElement>> = {};

function getAudio(src: string): HTMLAudioElement {
    if (!cache[src]) {
        const audio = new Audio(src);
        audio.volume = 0.5;
        cache[src] = audio;
    }
    return cache[src]!;
}

export function playNotificationSound(type: AppNotification['type']): void {
    if (typeof window === 'undefined' || typeof Audio === 'undefined') return;
    if (!isSoundEnabled()) return;
    try {
        const src = soundMap[type];
        const audio = getAudio(src);
        audio.currentTime = 0;
        audio.play().catch(() => {});
    } catch {
        // Never crash the notification system over a sound
    }
}
