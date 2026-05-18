export type AppNotification = {
    id: string;
    type: 'success' | 'info' | 'warning' | 'error' | 'message';
    title: string;
    description?: string;
    time: string;
};

import { playNotificationSound } from '@/lib/notification-sounds';

let items = $state<AppNotification[]>([]);

function uid(): string {
    return Math.random().toString(36).slice(2) + Date.now().toString(36);
}

function add(notification: Omit<AppNotification, 'id' | 'time'>): void {
    const id = uid();
    items.unshift({ ...notification, id, time: 'just now' });
    if (items.length > 5) { items.splice(5); }
    playNotificationSound(notification.type);
    setTimeout(() => remove(id), 5000);
}

function remove(id: string): void {
    const idx = items.findIndex((n) => n.id === id);
    if (idx !== -1) { items.splice(idx, 1); }
}

export const notifications = {
    get items() { return items; },
    add,
    remove,
};
