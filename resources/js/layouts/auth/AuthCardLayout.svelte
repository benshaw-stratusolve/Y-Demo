<script lang="ts">
    import type { Snippet } from 'svelte';
    import MagicCard from '@/components/magic-card/MagicCard.svelte';
    import DashboardShell from '@/components/DashboardShell.svelte';

    let {
        title = '',
        description = '',
        children,
    }: {
        title?: string;
        description?: string;
        children?: Snippet;
    } = $props();
</script>

<div class="relative flex min-h-svh items-center justify-center bg-white dark:bg-black overflow-hidden">
    <!-- Blurred dashboard background -->
    <div class="absolute inset-0 z-0 overflow-hidden">
        <DashboardShell />
    </div>
    <div class="absolute inset-0 z-0 bg-white/60 dark:bg-black/50 backdrop-blur-md"></div>

    <div class="relative z-10 w-full max-w-xl p-6 md:p-10">
        <MagicCard
            class="rounded-xl"
            gradientFrom="#2563eb"
            gradientTo="#60a5fa"
            gradientColor="#1a1a2e"
            gradientOpacity={0.6}
        >
            <div class="px-14 pt-10 pb-0 text-center">
                <h1 class="text-2xl font-semibold tracking-tight">{title}</h1>
                <p class="text-sm text-muted-foreground mt-1">{description}</p>
            </div>
            <div class="px-14 py-10">
                {@render children?.()}
            </div>
        </MagicCard>
    </div>
</div>
