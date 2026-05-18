<script lang="ts">
	import { onMount } from 'svelte';
	import { cn } from '@/lib/utils';

	interface Word {
		text: string;
		className?: string;
	}

	interface Props {
		words: Word[];
		class?: string;
		cursorClass?: string;
		speed?: number;
	}

	let { words, class: className, cursorClass, speed = 80 }: Props = $props();

	const chars: { char: string; className?: string }[] = words.flatMap((word) => [
		...word.text.split('').map((char) => ({ char, className: word.className })),
		{ char: ' ', className: undefined },
	]);

	let visibleCount = $state(0);

	onMount(() => {
		const interval = setInterval(() => {
			visibleCount++;
			if (visibleCount >= chars.length) {
				clearInterval(interval);
			}
		}, speed);

		return () => clearInterval(interval);
	});
</script>

<span class={cn('inline', className)}>
	{#each chars.slice(0, visibleCount) as item (item)}
		<span class={item.className}>{item.char}</span>
	{/each}
	
</span>

