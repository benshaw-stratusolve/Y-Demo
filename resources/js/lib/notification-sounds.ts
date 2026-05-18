import type { AppNotification } from '@/lib/notifications.svelte';

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
    try {
        const src = soundMap[type];
        const audio = getAudio(src);
        audio.currentTime = 0;
        audio.play().catch(() => {});
    } catch {
        // Never crash the notification system over a sound
    }
}
