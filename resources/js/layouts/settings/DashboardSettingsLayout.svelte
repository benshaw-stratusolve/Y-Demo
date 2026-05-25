<script lang="ts">
    import { page, router, Link } from '@inertiajs/svelte';
    import { destroy as logout } from '@/actions/Laravel/Fortify/Http/Controllers/AuthenticatedSessionController';
    import type { Snippet } from 'svelte';
    import AnimatedThemeToggler from '@/components/animated-theme-toggler/AnimatedThemeToggler.svelte';
    import SearchOverlay from '@/components/search-overlay/SearchOverlay.svelte';
    import AnimatedNotificationList from '@/components/animated-notification/AnimatedNotificationList.svelte';
    import { Home, Search, Bell, Sparkles, User } from 'lucide-svelte';
    import HeaderToggles from '@/components/HeaderToggles.svelte';
    import AnimatedGradientText from '@/components/AnimatedGradientText.svelte';
    import { edit as editProfile } from '@/routes/profile';
    import { edit as editSecurity } from '@/routes/security';
    import { Badge } from '@/components/ui/badge';
    import UserAvatar from '@/components/UserAvatar.svelte';
    import { realtimeStore } from '@/lib/realtime.svelte';

    let { children }: { children?: Snippet } = $props();

    const auth = $derived(page.props.auth as any);
    const currentUrl = $derived(page.url);
    const unreadCount = $derived(
        ((page.props as any).unread_notifications_count as number ?? 0) + realtimeStore.liveUnreadIncrement
    );

    let searchOpen = $state(false);

    const navItems = [
        { label: 'Home', icon: Home, href: '/dashboard' },
        { label: 'Notifications', icon: Bell, href: '/notifications' },
        { label: 'Flok', icon: Sparkles, href: '/flock-ai' },
        { label: 'Profile', icon: User, href: editProfile().url },
    ];

    const settingsTabs = [
        { label: 'Profile', href: editProfile().url },
        { label: 'Security', href: editSecurity().url },
    ];
</script>

<div class="min-h-screen bg-white dark:bg-black text-gray-900 dark:text-gray-100 flex justify-center font-sans">

    <!-- Left sidebar -->
    <header class="w-[275px] flex-col justify-between py-2 px-4 h-screen sticky top-0 hidden sm:flex">
        <div class="flex flex-col gap-2 w-full">
            <div class="flex items-center gap-2">
                <a href="/dashboard" class="p-5 rounded-full w-fit transition-colors" aria-label="Home">
                    <img src="/images/Y-dark-remove.png" alt="Y" class="h-9 w-9 object-contain dark:invert-0 invert" />
                </a>
            </div>

            <nav class="flex flex-col gap-1 w-full mt-2">
                {#each navItems as item}
                    {@const Icon = item.icon}
                    {@const isActive = currentUrl.startsWith(item.href) && item.href !== '/dashboard'}
                    <a
                        href={item.href}
                        class="flex items-center gap-5 p-3 rounded-full w-fit transition-colors dark:text-neutral-300 dark:hover:text-white dark:hover:bg-neutral-900 hover:text-gray-900 hover:bg-neutral-100 {isActive ? 'font-bold dark:text-white text-gray-900' : 'text-gray-500'}"
                    >
                        {#if item.label === 'Flok'}
                            <svg width="0" height="0" style="position:absolute;overflow:hidden">
                                <defs>
                                    <linearGradient id="flok-icon-grad" x1="0%" y1="0%" x2="100%" y2="100%">
                                        <stop offset="0%" stop-color="#60a5fa" />
                                        <stop offset="100%" stop-color="#a78bfa" />
                                    </linearGradient>
                                </defs>
                            </svg>
                            <Icon class="w-6 h-6" style="stroke: url(#flok-icon-grad)" />
                        {:else if item.label === 'Notifications'}
                            <div class="relative">
                                <Icon class="w-6 h-6" />
                                {#if unreadCount > 0}
                                    <span class="absolute -top-1 -right-1 min-w-[16px] h-4 bg-blue-500 rounded-full text-white text-[9px] font-bold flex items-center justify-center px-0.5">{unreadCount > 99 ? '99+' : unreadCount}</span>
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
                <button
                    onclick={() => searchOpen = true}
                    class="flex items-center gap-5 p-3 rounded-full w-fit transition-colors text-gray-500 dark:text-neutral-300 hover:text-gray-900 hover:bg-neutral-100 dark:hover:text-white dark:hover:bg-neutral-900"
                >
                    <Search class="w-6 h-6" />
                    <span class="text-xl hidden xl:block">Explore</span>
                </button>
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

    <!-- Main content -->
    <main class="w-full sm:w-[600px] border-x border-neutral-200 dark:border-neutral-800 min-h-screen">

        <!-- Settings header -->
        <div class="sticky top-0 bg-white/80 dark:bg-black/80 backdrop-blur-md z-10 border-b border-neutral-200 dark:border-neutral-800">
            <div class="px-4 pt-4 pb-0">
                <div class="flex items-center gap-3 mb-3">
                    <a
                        href="/users/{auth?.user?.id}"
                        class="text-neutral-500 hover:text-gray-900 dark:hover:text-white transition-colors text-xl"
                    >←</a>
                    <h1 class="text-xl font-bold">Settings</h1>
                </div>
                <!-- Tab navigation -->
                <div class="flex">
                    {#each settingsTabs as tab}
                        {@const isActive = currentUrl === tab.href || currentUrl.startsWith(tab.href + '?')}
                        <Link
                            href={tab.href}
                            class="flex-1 pb-3 transition-colors relative flex justify-center font-bold text-[15px] {!isActive ? 'text-neutral-500 hover:text-gray-900 dark:hover:text-white' : ''}"
                        >
                            {tab.label}
                            {#if isActive}
                                <div class="absolute bottom-0 h-1 w-14 bg-blue-500 rounded-full"></div>
                            {/if}
                        </Link>
                    {/each}
                </div>
            </div>
        </div>

        <!-- Page content -->
        <div class="px-6 py-6">
            {@render children?.()}
        </div>
    </main>

    <!-- Right toggles -->
    <div class="hidden lg:block pt-3 pl-4">
        <div class="sticky top-3">
            <HeaderToggles />
        </div>
    </div>

</div>

<SearchOverlay bind:open={searchOpen} />
<AnimatedNotificationList />
