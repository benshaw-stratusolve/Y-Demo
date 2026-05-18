<script lang="ts">
	import type { Snippet } from 'svelte';
	import { cn } from '@/lib/utils';

	interface CardBodyProps {
		children?: Snippet;
		class?: string;
	}

	let { children, class: className }: CardBodyProps = $props();
</script>

<div class={cn('h-96 w-96 [transform-style:preserve-3d] [&>*]:[transform-style:preserve-3d]', className)}>
	{@render children?.()}
</div>
