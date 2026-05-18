<script lang="ts">
    import { page, router } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { destroy as logout } from '@/actions/Laravel/Fortify/Http/Controllers/AuthenticatedSessionController';
    import { markAllRead, markRead } from '@/actions/App/Http/Controllers/NotificationsController';
    import AnimatedThemeToggler from '@/components/animated-theme-toggler/AnimatedThemeToggler.svelte';
    import AnimatedGradientText from '@/components/AnimatedGradientText.svelte';
    import { Home, Bell, Mail, Sparkles, User, Feather, Heart, UserPlus, AtSign, Settings } from 'lucide-svelte';

    type Notification = {
        id: string;
        type: string;
        data: Record<string, string>;
        read: boolean;
        created_at: string;
    };

    let { notifications, unread_count }: { notifications: Notification[]; unread_count: number } = $props();

    const auth = $derived(page.props.auth as any);

    let activeTab = $state<'all' | 'mentions'>('all');

    const filtered = $derived(
        activeTab === 'mentions'
            ? notifications.filter(n => n.type === 'mention')
            : notifications
    );

    function getInitials(name: string) {
        return name?.split(' ').map((n: string) => n[0]).join('').toUpperCase().slice(0, 2) ?? '?';
    }

    function handleMarkAllRead() {
        router.post(markAllRead().url);
    }

    function handleMarkRead(id: string) {
        router.post(markRead({ id }).url);
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
                    { label: 'Messages', icon: Mail, href: '/messages' },
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

            <button class="bg-blue-500 hover:bg-blue-600 text-white font-bold rounded-full py-3.5 px-8 w-[90%] mt-4 transition-colors hidden xl:block shadow-md">
                Post
            </button>
            <button class="bg-blue-500 hover:bg-blue-600 text-white rounded-full p-3 mt-4 transition-colors xl:hidden shadow-md" aria-label="Post">
                <Feather class="w-6 h-6" />
            </button>
        </div>

        <button
            onclick={() => router.post(logout().url)}
            class="flex items-center gap-3 p-3 rounded-full w-full transition-colors mb-4 text-gray-500 dark:text-neutral-400 hover:text-gray-900 hover:bg-neutral-100 dark:hover:text-white dark:hover:bg-neutral-900"
        >
            <div class="w-10 h-10 rounded-full flex-shrink-0 overflow-hidden bg-neutral-200 dark:bg-neutral-800 flex items-center justify-center">
                {#if auth?.user?.avatar_url}
                    <img src={auth.user.avatar_url} alt={auth.user.name} class="w-full h-full object-cover" />
                {:else}
                    <span class="text-xs font-bold text-neutral-500 dark:text-neutral-300">
                        {auth?.user?.name?.split(' ').map((n: string) => n[0]).join('').toUpperCase().slice(0, 2) ?? '?'}
                    </span>
                {/if}
            </div>
            <div class="flex-col items-start hidden xl:flex">
                <span class="font-bold text-sm">{auth?.user?.name ?? 'User'}</span>
                <span class="text-neutral-500 text-sm">@{auth?.user?.name?.toLowerCase().replace(' ', '') ?? 'username'}</span>
            </div>
            <div class="ml-auto hidden xl:block text-neutral-500 text-xs">Log out</div>
        </button>
    </header>

    <!-- Notifications feed -->
    <main class="w-full sm:w-[600px] border-x border-neutral-200 dark:border-neutral-800 min-h-screen">

        <!-- Header -->
        <div class="sticky top-0 bg-white/90 dark:bg-black/90 backdrop-blur-md z-10 border-b border-neutral-200 dark:border-neutral-800">
            <div class="flex items-center justify-between px-4 py-3">
                <h1 class="text-xl font-extrabold">Notifications</h1>
                {#if unread_count > 0}
                    <button
                        onclick={handleMarkAllRead}
                        class="text-blue-500 hover:text-blue-600 text-sm font-medium transition-colors"
                    >
                        Mark all as read
                    </button>
                {/if}
            </div>

            <!-- Tabs -->
            <div class="flex px-4 pb-0">
                {#each [{ id: 'all', label: 'All' }, { id: 'mentions', label: 'Mentions' }] as tab}
                    <button
                        class="flex-1 pb-3 transition-colors relative flex justify-center font-bold text-[15px] {activeTab !== tab.id ? 'text-neutral-500 hover:text-gray-900 dark:hover:text-white' : ''}"
                        onclick={() => activeTab = tab.id as 'all' | 'mentions'}
                    >
                        {tab.label}
                        {#if activeTab === tab.id}
                            <div class="absolute bottom-0 h-1 w-14 bg-blue-500 rounded-full"></div>
                        {/if}
                    </button>
                {/each}
            </div>
        </div>

        <!-- Notification list -->
        {#if filtered.length === 0}
            <div class="flex flex-col items-center justify-center py-24 px-8 text-center">
                <div class="w-16 h-16 rounded-full bg-neutral-100 dark:bg-neutral-900 flex items-center justify-center mb-4">
                    <Bell class="w-8 h-8 text-neutral-400" />
                </div>
                <h2 class="font-extrabold text-2xl mb-1">Nothing to see here</h2>
                <p class="text-neutral-500 text-[15px]">
                    {activeTab === 'mentions' ? "You haven't been mentioned yet." : "You have no notifications yet."}
                </p>
            </div>
        {:else}
            {#each filtered as notif (notif.id)}
                <button
                    onclick={() => !notif.read && handleMarkRead(notif.id)}
                    class="w-full flex items-start gap-4 px-4 py-4 border-b border-neutral-200 dark:border-neutral-800 transition-colors text-left hover:bg-neutral-50 dark:hover:bg-neutral-950 {!notif.read ? 'bg-blue-50/40 dark:bg-blue-950/20' : ''}"
                >
                    <!-- Type icon -->
                    <div class="shrink-0 mt-1">
                        {#if notif.type === 'follow'}
                            <div class="w-9 h-9 rounded-full bg-blue-500/10 flex items-center justify-center">
                                <UserPlus class="w-5 h-5 text-blue-500" />
                            </div>
                        {:else if notif.type === 'like'}
                            <div class="w-9 h-9 rounded-full bg-pink-500/10 flex items-center justify-center">
                                <Heart class="w-5 h-5 text-pink-500" />
                            </div>
                        {:else if notif.type === 'mention'}
                            <div class="w-9 h-9 rounded-full bg-green-500/10 flex items-center justify-center">
                                <AtSign class="w-5 h-5 text-green-500" />
                            </div>
                        {:else}
                            <div class="w-9 h-9 rounded-full bg-neutral-100 dark:bg-neutral-900 flex items-center justify-center">
                                <Bell class="w-5 h-5 text-neutral-400" />
                            </div>
                        {/if}
                    </div>

                    <div class="flex-1 min-w-0">
                        <!-- Actor avatar + unread dot -->
                        <div class="flex items-center gap-2 mb-2">
                            <div class="w-9 h-9 rounded-full bg-neutral-200 dark:bg-neutral-800 flex-shrink-0 flex items-center justify-center overflow-hidden">
                                {#if notif.data.actor_avatar}
                                    <img src={notif.data.actor_avatar} alt={notif.data.actor_name} class="w-full h-full object-cover" />
                                {:else}
                                    <span class="text-xs font-bold text-neutral-600 dark:text-neutral-300">{getInitials(notif.data.actor_name)}</span>
                                {/if}
                            </div>
                            {#if !notif.read}
                                <span class="w-2 h-2 rounded-full bg-blue-500 shrink-0"></span>
                            {/if}
                        </div>

                        <!-- Text -->
                        <p class="text-[15px] leading-snug">
                            <span class="font-bold">{notif.data.actor_name}</span>
                            <span class="text-neutral-600 dark:text-neutral-400"> {notif.data.message}</span>
                        </p>

                        {#if notif.data.post_excerpt}
                            <p class="text-neutral-500 text-[14px] mt-1 truncate">{notif.data.post_excerpt}</p>
                        {/if}

                        <p class="text-neutral-400 text-[13px] mt-1">{notif.created_at}</p>
                    </div>
                </button>
            {/each}
        {/if}
    </main>

    <!-- Right sidebar -->
    <aside class="w-[350px] pl-8 py-2 hidden lg:block">
        <div class="sticky top-4 bg-neutral-100 dark:bg-[#16181c] rounded-2xl p-4 border border-neutral-200 dark:border-neutral-800">
            <div class="flex items-center gap-2 mb-3">
                <Settings class="w-5 h-5 text-neutral-500" />
                <h2 class="font-extrabold">Notification filters</h2>
            </div>
            <p class="text-[14px] text-neutral-500 leading-snug">
                Choose which notifications appear in your feed. Mentions, follows, and likes are all tracked.
            </p>
        </div>
    </aside>

</div>

<style>
    :global(::-webkit-scrollbar) { width: 6px; }
    :global(::-webkit-scrollbar-track) { background: transparent; }
    :global(::-webkit-scrollbar-thumb) { background: #e5e7eb; border-radius: 4px; }
    :global(.dark ::-webkit-scrollbar-thumb) { background: #262626; }
</style>
