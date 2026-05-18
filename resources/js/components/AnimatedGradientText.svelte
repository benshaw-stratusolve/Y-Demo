<script lang="ts">
    import { cn } from '@/lib/utils';
    import type { Snippet } from 'svelte';

    let {
        speed = 1,
        colorFrom = '#60a5fa',
        colorTo = '#a78bfa',
        children,
        class: className = '',
    }: {
        speed?: number;
        colorFrom?: string;
        colorTo?: string;
        children: Snippet;
        class?: string;
    } = $props();
</script>

<span
    style="--color-from:{colorFrom}; --color-to:{colorTo}; --bg-size:{500 * speed}%"
    class={cn(
        'animate-gradient inline bg-linear-to-r from-(--color-from) via-(--color-to) to-(--color-from) bg-clip-text text-transparent',
        'bg-[length:var(--bg-size)_100%]',
        className
    )}
>
    {@render children?.()}
</span>
