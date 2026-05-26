<script lang="ts">
    import { X, CheckCircle, Info, AlertTriangle, AlertCircle, MessageSquare } from 'lucide-svelte';
    import type { AppNotification } from '@/lib/notifications.svelte';
    import { notifications } from '@/lib/notifications.svelte';

    let { notification }: { notification: AppNotification } = $props();

    const icons = {
        success: CheckCircle,
        info: Info,
        warning: AlertTriangle,
        error: AlertCircle,
        message: MessageSquare,
    };

    const colors = {
        success: 'text-green-400',
        info: 'text-blue-400',
        warning: 'text-yellow-400',
        error: 'text-red-400',
        message: 'text-blue-400',
    };

    const Icon = $derived(icons[notification.type as keyof typeof icons]);
    const iconColor = $derived(colors[notification.type as keyof typeof colors]);
</script>

<div class="flex items-start gap-3 w-[360px] rounded-2xl border border-neutral-800 bg-neutral-950/95 backdrop-blur-sm px-4 py-3.5 shadow-2xl">
    <div class="mt-0.5 shrink-0">
        <Icon class="w-5 h-5 {iconColor}" />
    </div>
    <div class="flex-1 min-w-0">
        <p class="text-sm font-semibold text-white leading-snug">{notification.title}</p>
        {#if notification.description}
            <p class="text-sm text-neutral-400 mt-0.5 leading-snug">{notification.description}</p>
        {/if}
        <p class="text-xs text-neutral-600 mt-1">{notification.time}</p>
    </div>
    <button
        onclick={() => notifications.remove(notification.id)}
        class="shrink-0 text-neutral-600 hover:text-neutral-300 transition-colors -mr-1 -mt-0.5 p-1 rounded-full hover:bg-neutral-800"
        aria-label="Dismiss"
    >
        <X class="w-3.5 h-3.5" />
    </button>
</div>
