<script lang="ts">
    import { onMount } from 'svelte';
    import { motion, AnimatePresence } from 'motion-sv';
    import { notifications } from '@/lib/notifications.svelte';
    import NotificationItem from './NotificationItem.svelte';

    let mounted = $state(false);
    onMount(() => { mounted = true; });
</script>

{#if mounted}
    <div class="fixed bottom-6 right-6 z-[200] flex flex-col-reverse gap-3 pointer-events-none">
        <AnimatePresence>
            {#each notifications.items as notification (notification.id)}
                <motion.div
                    layout
                    initial={{ opacity: 0, scale: 0.85, y: 20 }}
                    animate={{ opacity: 1, scale: 1, y: 0 }}
                    exit={{ opacity: 0, scale: 0.85, y: 20 }}
                    transition={{ type: 'spring', stiffness: 500, damping: 35 }}
                    class="pointer-events-auto"
                >
                    <NotificationItem {notification} />
                </motion.div>
            {/each}
        </AnimatePresence>
    </div>
{/if}
