<script lang="ts">
    import { page, router, usePoll } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { destroy as logout } from '@/actions/Laravel/Fortify/Http/Controllers/AuthenticatedSessionController';
    import { markAllRead, markRead } from '@/actions/App/Http/Controllers/NotificationsController';
    import AnimatedThemeToggler from '@/components/animated-theme-toggler/AnimatedThemeToggler.svelte';
    import AnimatedGradientText from '@/components/AnimatedGradientText.svelte';
    import UserAvatar from '@/components/UserAvatar.svelte';
    import { Home, Bell, Sparkles, User } from 'lucide-svelte';
    import { clearAll } from '@/actions/App/Http/Controllers/NotificationsController';
    import { Badge } from '@/components/ui/badge';

    type Notification = {
        id: string;
        type: string;
        data: Record<string, any>;
        read: boolean;
        created_at: string;
        is_following_actor: boolean;
    };

    let { notifications, unread_count }: { notifications: Notification[]; unread_count: number } = $props();

    usePoll(30000, { only: ['notifications', 'unread_count'] });

    const auth = $derived(page.props.auth as any);

    let selectedNotif = $state<Notification | null>(null);

    function handleMarkAllRead() {
        router.post(markAllRead().url, {}, { preserveScroll: true });
    }

    function handleMarkRead(id: string) {
        router.post(markRead({ id }).url, {}, { preserveScroll: true, only: ['notifications', 'unread_count'] });
    }

    function openNotif(notif: Notification) {
        if (!notif.read) {
            handleMarkRead(notif.id);
        }
        const dest = notifDestination(notif);
        if (dest) {
            router.visit(dest);
        } else {
            selectedNotif = notif;
        }
    }

    function closeModal() {
        selectedNotif = null;
    }

    function notifDestination(notif: Notification): string | null {
        if (notif.type === 'like' || notif.type === 'reply') return null; // shown in modal
        if (notif.type === 'follow' && notif.data.actor_id) return `/users/${notif.data.actor_id}`;
        if ((notif.type === 'post_created' || notif.type === 'comment_created') && notif.data.post_id) return `/posts/${notif.data.post_id}`;
        return null;
    }
</script>

<AppHead title="Notifications" />

<div class="min-h-screen bg-white dark:bg-black text-gray-900 dark:text-gray-100 flex justify-center font-sans">
    <!-- Left nav sidebar -->
    <header class="w-[275px] flex-col justify-between py-2 px-4 h-screen sticky top-0 hidden sm:flex shrink-0">
        <div class="flex flex-col gap-2 w-full">
            <div class="flex items-center gap-2">
                <a href="/dashboard" class="p-5 rounded-full w-fit transition-colors" aria-label="Home">
                    <img src="/images/Y-dark-remove.png" alt="Y" class="h-9 w-9 object-contain dark:invert-0 invert" />
                </a>
                <AnimatedThemeToggler class="p-3 rounded-full transition-colors text-gray-900 dark:text-white" />
            </div>

            <nav class="flex flex-col gap-1 w-full mt-2">
                {#each [
                    { label: 'Home', icon: Home, href: '/dashboard' },
                    { label: 'Notifications', icon: Bell, href: '/notifications', active: true },
                    { label: 'Flok', icon: Sparkles, href: '/flock-ai' },
                    { label: 'Profile', icon: User, href: '/settings/profile' },
                ] as item}
                    {@const Icon = item.icon}
                    <a
                        href={item.href}
                        class="flex items-center gap-5 p-3 rounded-full w-fit transition-colors {item.active ? 'text-gray-900 dark:text-white font-bold' : 'text-gray-500 dark:text-neutral-300 hover:text-gray-900 hover:bg-neutral-100 dark:hover:text-white dark:hover:bg-neutral-900'}"
                    >
                        {#if item.label === 'Flok'}
                            <svg width="0" height="0" style="position:absolute;overflow:hidden">
                                <defs>
                                    <linearGradient id="flok-icon-grad-notif" x1="0%" y1="0%" x2="100%" y2="100%">
                                        <stop offset="0%" stop-color="#60a5fa" />
                                        <stop offset="100%" stop-color="#a78bfa" />
                                    </linearGradient>
                                </defs>
                            </svg>
                            <Icon class="w-6 h-6" style="stroke: url(#flok-icon-grad-notif)" />
                        {:else if item.label === 'Notifications'}
                            <div class="relative">
                                <Icon class="w-6 h-6" />
                                {#if unread_count > 0}
                                    <span class="absolute -top-1 -right-1 bg-blue-500 text-white text-[10px] font-bold rounded-full w-4 h-4 flex items-center justify-center">{unread_count > 9 ? '9+' : unread_count}</span>
                                {/if}
                            </div>
                        {:else}
                            <Icon class="w-6 h-6" />
                        {/if}
                        {#if item.label === 'Flok'}
                            <AnimatedGradientText class="text-xl font-semibold hidden xl:inline">Flok AI</AnimatedGradientText>
                        {:else}
                            <span class="text-xl hidden xl:block">{item.label}</span>
                        {/if}
                    </a>
                {/each}
            </nav>

        </div>

        <div class="flex items-center gap-1 mb-4 w-full">
            <a
                href="/users/{auth?.user?.id}"
                class="flex items-center gap-3 p-3 rounded-full flex-1 min-w-0 transition-colors text-gray-500 dark:text-neutral-400 hover:text-gray-900 hover:bg-neutral-100 dark:hover:text-white dark:hover:bg-neutral-900"
            >
                <UserAvatar user={auth?.user} />
                <div class="flex-col items-start hidden xl:flex min-w-0">
                    <span class="font-bold text-sm truncate">{auth?.user?.name ?? 'User'}</span>
                    <span class="text-neutral-500 text-sm">@{auth?.user?.username ?? 'username'}</span>
                </div>
            </a>
            <button
                onclick={() => router.post(logout().url)}
                class="hidden xl:flex ml-auto shrink-0 group"
                aria-label="Log out"
            >
                <Badge variant="destructive" class="group-hover:font-bold">Log out</Badge>
            </button>
        </div>
    </header>

    <!-- Notifications feed -->
    <main class="w-full sm:w-[600px] border-x border-neutral-200 dark:border-neutral-800 min-h-screen">

        <!-- Header -->
        <div class="sticky top-0 bg-white/90 dark:bg-black/90 backdrop-blur-md z-10 border-b border-neutral-200 dark:border-neutral-800">
            <div class="relative flex items-center px-4 py-4">
                <a href="/dashboard" class="text-neutral-500 hover:text-gray-900 dark:hover:text-white transition-colors z-10">
                    ←
                </a>
                <h1 class="absolute left-0 right-0 text-center text-xl font-extrabold pointer-events-none">Notifications</h1>
                {#if notifications.length > 0}
                    <button
                        onclick={() => router.delete(clearAll().url, {}, { preserveScroll: true })}
                        class="ml-auto z-10 text-sm text-neutral-500 hover:text-red-500 transition-colors font-medium"
                    >
                        Clear all
                    </button>
                {/if}
            </div>
        </div>

        <!-- Notification list -->
        {#if notifications.length === 0}
            <div class="flex flex-col items-center justify-center py-24 px-8 text-center">
                <div class="w-16 h-16 rounded-full bg-neutral-100 dark:bg-neutral-900 flex items-center justify-center mb-4">
                    <Bell class="w-8 h-8 text-neutral-400" />
                </div>
                <p class="text-5xl mb-4">🦗</p>
                <h2 class="font-extrabold text-2xl mb-2">Crickets.</h2>
                <p class="text-neutral-500 text-[15px]">Nobody's talking about you yet —</p>
                <p class="text-neutral-500 text-[15px]">that's Y you need to post more.</p>
            </div>
        {:else}
            {#each notifications as notif (notif.id)}
                <div
                    role="button"
                    tabindex="0"
                    onclick={() => openNotif(notif)}
                    onkeydown={(e) => e.key === 'Enter' && openNotif(notif)}
                    class="w-full flex items-start gap-4 px-4 py-4 border-b border-neutral-200 dark:border-neutral-800 transition-colors text-left hover:bg-neutral-50 dark:hover:bg-neutral-950 cursor-pointer {!notif.read ? 'bg-blue-50/40 dark:bg-blue-950/20' : ''}"
                >
                    <div class="flex-1 min-w-0">
                        <!-- Actor avatar + unread dot -->
                        <div class="flex items-center gap-2 mb-2">
                            {#if notif.data.actor_id}
                                <a
                                    href="/users/{notif.data.actor_id}"
                                    onclick={(e) => e.stopPropagation()}
                                    class="hover:opacity-80 transition-opacity"
                                >
                                    <UserAvatar name={notif.data.actor_name} src={notif.data.actor_avatar} size="sm" />
                                </a>
                            {:else}
                                <div class="w-9 h-9 rounded-full bg-blue-500 shrink-0 flex items-center justify-center">
                                    <span class="text-white font-bold text-sm">Y</span>
                                </div>
                            {/if}
                            {#if !notif.read}
                                <span class="w-2 h-2 rounded-full bg-blue-500 shrink-0"></span>
                            {/if}
                        </div>

                        <!-- Text -->
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-[15px] leading-snug">
                                    {#if notif.data.actor_name}
                                        <span class="font-bold">{notif.data.actor_name}</span>
                                        <span class="text-neutral-600 dark:text-neutral-400"> {notif.data.message}</span>
                                    {:else}
                                        <span class="{notif.type === 'ban' ? 'text-red-500 font-bold' : 'text-neutral-800 dark:text-neutral-200'}">{notif.data.message}</span>
                                    {/if}
                                </p>

                                {#if notif.data.post_excerpt}
                                    <p class="text-neutral-500 text-[14px] mt-1 truncate">{notif.data.post_excerpt}</p>
                                {/if}

                                <p class="text-neutral-400 text-[13px] mt-1">{notif.created_at}</p>
                            </div>

                            {#if notif.type === 'follow' && notif.data.actor_id}
                                <button
                                    onclick={(e) => {
                                        e.stopPropagation();
                                        router.post(`/users/${notif.data.actor_id}/follow`, {}, { preserveScroll: true });
                                    }}
                                    class="shrink-0 rounded-full border px-4 py-1.5 text-sm font-bold transition-colors {notif.is_following_actor ? 'border-neutral-300 dark:border-neutral-700 text-neutral-700 dark:text-neutral-300 hover:border-red-300 hover:text-red-500' : 'bg-gray-900 dark:bg-white text-white dark:text-black border-transparent hover:bg-gray-700 dark:hover:bg-neutral-200'}"
                                >
                                    {notif.is_following_actor ? 'Following' : 'Follow back'}
                                </button>
                            {/if}
                        </div>
                    </div>
                </div>
            {/each}
        {/if}
    </main>
</div>

{#if selectedNotif}
    <!-- Backdrop -->
    <button
        class="fixed inset-0 z-40 bg-black/50 backdrop-blur-sm cursor-default"
        aria-label="Close"
        onclick={closeModal}
    ></button>

    <!-- Modal -->
    <div class="fixed inset-0 z-50 flex items-center justify-center px-4 pointer-events-none">
        <div class="pointer-events-auto w-full max-w-md bg-white dark:bg-neutral-950 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-2xl p-6">

            <!-- Actor row -->
            <div class="flex items-center gap-3 mb-4">
                {#if selectedNotif.data.actor_id}
                    <a
                        href="/users/{selectedNotif.data.actor_id}"
                        onclick={closeModal}
                        class="hover:opacity-80 transition-opacity"
                    >
                        <UserAvatar name={selectedNotif.data.actor_name} src={selectedNotif.data.actor_avatar} size="lg" />
                    </a>
                {:else}
                    <div class="w-12 h-12 rounded-full bg-blue-500 shrink-0 flex items-center justify-center">
                        <span class="text-white font-bold">Y</span>
                    </div>
                {/if}
                <div>
                    <p class="font-bold">{selectedNotif.data.actor_name ?? 'Y'}</p>
                    <p class="text-neutral-500 text-sm">{selectedNotif.created_at}</p>
                </div>
            </div>

            <!-- Message -->
            <p class="text-[16px] mb-3">
                {#if selectedNotif.data.actor_name}
                    <span class="font-bold">{selectedNotif.data.actor_name}</span>
                    <span class="text-neutral-600 dark:text-neutral-400"> {selectedNotif.data.message}</span>
                {:else}
                    <span class="{selectedNotif.type === 'ban' ? 'text-red-500 font-bold' : ''}">{selectedNotif.data.message}</span>
                {/if}
            </p>

            <!-- Post excerpt -->
            {#if selectedNotif.data.post_excerpt}
                <div class="border-l-4 border-neutral-200 dark:border-neutral-700 pl-3 mb-4">
                    <p class="text-neutral-500 text-[15px] italic">"{selectedNotif.data.post_excerpt}"</p>
                </div>
            {/if}

            <!-- Actions -->
            <div class="flex gap-3 mt-4">
                {#if selectedNotif.type === 'follow'}
                    <button
                        onclick={() => {
                            router.post(`/users/${selectedNotif!.data.actor_id}/follow`, {}, { preserveScroll: true });
                            closeModal();
                        }}
                        class="flex-1 rounded-full py-2 text-sm font-bold transition-colors {selectedNotif.is_following_actor ? 'border border-neutral-300 dark:border-neutral-700 hover:border-red-300 hover:text-red-500' : 'bg-gray-900 dark:bg-white text-white dark:text-black hover:bg-gray-700'}"
                    >
                        {selectedNotif.is_following_actor ? 'Following' : 'Follow back'}
                    </button>
                {/if}
                <button
                    onclick={closeModal}
                    class="flex-1 rounded-full border border-neutral-200 dark:border-neutral-700 py-2 text-sm font-bold hover:bg-neutral-100 dark:hover:bg-neutral-900 transition-colors"
                >
                    Close
                </button>
            </div>
        </div>
    </div>
{/if}

<style>
    :global(::-webkit-scrollbar) { width: 6px; }
    :global(::-webkit-scrollbar-track) { background: transparent; }
    :global(::-webkit-scrollbar-thumb) { background: #e5e7eb; border-radius: 4px; }
    :global(.dark ::-webkit-scrollbar-thumb) { background: #262626; }
</style>
