import { router } from '@inertiajs/svelte';
import type { FlashToast } from '@/types/ui';
import { notifications } from '@/lib/notifications.svelte';

export function initializeFlashToast(): void {
    router.on('flash', (event) => {
        const flash = (event as CustomEvent).detail?.flash;
        const data = flash?.toast as FlashToast | undefined;

        if (!data) {
            return;
        }

        notifications.add({
            type: data.type,
            title: data.title ?? data.message ?? '',
            description: data.description,
        });
    });


}
