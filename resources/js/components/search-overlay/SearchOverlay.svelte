<script lang="ts">
	import { onMount, tick } from 'svelte';
	import { Search, X } from 'lucide-svelte';
	import SearchController from '@/actions/App/Http/Controllers/SearchController';
	import UserAvatar from '@/components/UserAvatar.svelte';
	import { show as showUser } from '@/routes/users';

	type SearchUser = {
		id: number;
		name: string;
		username: string | null;
		avatar_url?: string | null;
	};

	type SearchResults = {
		query: string;
		users: SearchUser[];
		suggestions: string[];
	};

	interface Props {
		open?: boolean;
	}

	let { open = $bindable(false) }: Props = $props();

	let inputRef: HTMLInputElement | null = $state(null);
	let query = $state('');
	let results = $state<SearchResults | null>(null);
	let loading = $state(false);
	let error = $state('');
	let debounceTimer: ReturnType<typeof setTimeout> | undefined;
	let searchController: AbortController | null = null;
	let activeRequestId = 0;

	const trimmedQuery = $derived(query.trim());
	const hasResults = $derived((results?.users.length ?? 0) > 0);

	$effect(() => {
		clearTimeout(debounceTimer);

		// Access trimmedQuery synchronously so Svelte 5 tracks it as a dependency
		const currentQuery = trimmedQuery;

		if (!open) {
			searchController?.abort();
			loading = false;
			return;
		}

		loading = true;
		error = '';

		debounceTimer = setTimeout(async () => {
			const requestId = activeRequestId + 1;
			activeRequestId = requestId;
			searchController?.abort();
			searchController = new AbortController();

			try {
				const url = currentQuery.length >= 2
					? SearchController.index.url({ query: { q: currentQuery } })
					: SearchController.index.url({ query: { q: '' } });

				const response = await fetch(url, {
					headers: { Accept: 'application/json' },
					signal: searchController.signal,
				});

				if (!response.ok) {
					throw new Error('Search request failed.');
				}

				const data = (await response.json()) as SearchResults;

				if (requestId === activeRequestId) {
					results = data;
				}
			} catch (searchError) {
				if (searchError instanceof DOMException && searchError.name === 'AbortError') {
					return;
				}
				error = 'Search is unavailable right now.';
				results = null;
			} finally {
				if (requestId === activeRequestId) {
					loading = false;
				}
			}
		}, 250);
	});

	$effect(() => {
		if (open) {
			tick().then(() => inputRef?.focus());
		}
	});

	const close = () => {
		open = false;
		searchController?.abort();
		loading = false;
	};

	const useSuggestion = (suggestion: string) => {
		query = suggestion;
		inputRef?.focus();
	};

	const handleKeydown = (e: KeyboardEvent) => {
		if (e.key === 'Escape') close();
	};

	onMount(() => {
		window.addEventListener('keydown', handleKeydown);
		return () => window.removeEventListener('keydown', handleKeydown);
	});
</script>

{#if open}
	<div
		class="fixed inset-0 z-50 bg-black/40 dark:bg-black/60 backdrop-blur-sm"
		role="presentation"
		onclick={close}
	></div>

	<div class="fixed inset-x-0 top-0 z-50 mx-auto h-dvh w-full p-0 sm:top-12 sm:h-auto sm:max-w-2xl sm:px-4">
		<div class="flex h-full flex-col overflow-hidden bg-white shadow-2xl dark:bg-neutral-950 sm:max-h-[min(720px,calc(100vh-6rem))] sm:rounded-2xl sm:border sm:border-neutral-200 dark:sm:border-neutral-800">
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
					<button
						type="button"
						aria-label="Clear search"
						onclick={() => (query = '')}
						class="grid size-8 place-items-center rounded-full text-neutral-400 transition-colors hover:bg-neutral-100 hover:text-neutral-600 dark:hover:bg-neutral-900 dark:hover:text-neutral-300"
					>
						<X class="w-4 h-4" />
					</button>
				{:else}
					<button
						type="button"
						onclick={close}
						class="rounded-md px-2 py-1 text-sm text-neutral-400 transition-colors hover:bg-neutral-100 hover:text-neutral-600 dark:hover:bg-neutral-900 dark:hover:text-neutral-300"
					>
						Esc
					</button>
				{/if}
			</div>

			<div class="min-h-0 flex-1 overflow-y-auto">
				{#if loading && !results}
					<div class="space-y-3 px-4 py-5">
						<div class="h-4 w-1/2 animate-pulse rounded bg-neutral-200 dark:bg-neutral-800"></div>
						<div class="h-4 w-3/4 animate-pulse rounded bg-neutral-200 dark:bg-neutral-800"></div>
						<div class="h-4 w-2/3 animate-pulse rounded bg-neutral-200 dark:bg-neutral-800"></div>
					</div>
				{:else if error}
					<p class="px-4 py-5 text-sm text-red-600 dark:text-red-400">{error}</p>
				{:else if results}
					{#if hasResults}
						{#if results.users.length > 0}
							<p class="px-4 pt-4 pb-1 text-xs font-semibold uppercase tracking-wide text-neutral-500">People</p>
						{/if}
						{#each results.users as user}
							<a href={showUser(user.id).url} onclick={close} class="flex items-center gap-3 px-4 py-3 hover:bg-neutral-100 dark:hover:bg-neutral-900">
								<UserAvatar user={user} />
								<div class="min-w-0">
									<p class="truncate font-bold">{user.name}</p>
									{#if user.username}
										<p class="truncate text-sm text-neutral-500">@{user.username}</p>
									{/if}
								</div>
							</a>
						{/each}

					{:else if trimmedQuery.length >= 2}
						<div class="flex flex-col items-center justify-center px-4 py-10 text-center">
							<p class="text-3xl mb-3">🔍</p>
							<p class="font-bold text-[15px] mb-1">We searched the whole internet...</p>
							<p class="text-neutral-500 text-sm">just kidding. Nothing on Y matches "<span class="font-semibold">{results.query}</span>" though.</p>
						</div>
					{/if}

					{#if results.suggestions.length > 0}
						<div class="border-t border-neutral-100 px-4 py-3 dark:border-neutral-800">
							<p class="mb-2 text-xs font-semibold uppercase tracking-wide text-neutral-500">Similar searches</p>
							<div class="flex flex-wrap gap-2">
								{#each results.suggestions as suggestion}
									<button
										type="button"
										onclick={() => useSuggestion(suggestion)}
										class="rounded-full border border-neutral-200 px-3 py-1.5 text-sm text-neutral-700 transition-colors hover:bg-neutral-100 dark:border-neutral-800 dark:text-neutral-200 dark:hover:bg-neutral-900"
									>
										{suggestion}
									</button>
								{/each}
							</div>
						</div>
					{/if}
				{/if}
			</div>
		</div>
	</div>
{/if}
