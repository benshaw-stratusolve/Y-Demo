<script lang="ts">
	import { onMount } from 'svelte';
	import { Search, X } from 'lucide-svelte';
	import { cn } from '@/lib/utils';

	interface Props {
		open?: boolean;
	}

	let { open = $bindable(false) }: Props = $props();

	let inputRef: HTMLInputElement | null = $state(null);
	let query = $state('');

	$effect(() => {
		if (open && inputRef) {
			inputRef.focus();
		}
		if (!open) {
			query = '';
		}
	});

	const close = () => { open = false; };

	const handleKeydown = (e: KeyboardEvent) => {
		if (e.key === 'Escape') close();
	};

	onMount(() => {
		window.addEventListener('keydown', handleKeydown);
		return () => window.removeEventListener('keydown', handleKeydown);
	});
</script>

{#if open}
	<!-- Backdrop -->
	<div
		class="fixed inset-0 z-50 bg-black/40 dark:bg-black/60 backdrop-blur-sm"
		role="presentation"
		onclick={close}
	></div>

	<!-- Panel -->
	<div class="fixed inset-x-0 top-16 z-50 mx-auto max-w-2xl px-4">
		<div class="bg-white dark:bg-neutral-950 rounded-2xl shadow-2xl border border-neutral-200 dark:border-neutral-800 overflow-hidden">
			<!-- Input group -->
			<div class="flex items-center gap-3 px-4 py-3 border-b border-neutral-200 dark:border-neutral-800">
				<Search class="w-5 h-5 text-neutral-400 shrink-0" />
				<input
					bind:this={inputRef}
					bind:value={query}
					type="text"
					placeholder="Search Y"
					class="flex-1 bg-transparent text-[17px] outline-none placeholder-neutral-400 dark:placeholder-neutral-500"
				/>
				{#if query}
					<button onclick={() => query = ''} class="text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 transition-colors">
						<X class="w-4 h-4" />
					</button>
				{:else}
					<button onclick={close} class="text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 transition-colors text-sm">
						Esc
					</button>
				{/if}
			</div>

			<!-- Results area -->
			{#if query}
				<div class="px-4 py-3 text-[15px] text-neutral-500">
					Search for "<span class="text-gray-900 dark:text-white font-medium">{query}</span>"
				</div>
			{:else}
				<div class="px-4 py-4">
					<p class="text-sm text-neutral-500 font-medium mb-3">Recent searches</p>
					<p class="text-sm text-neutral-400">No recent searches</p>
				</div>
			{/if}
		</div>
	</div>
{/if}
