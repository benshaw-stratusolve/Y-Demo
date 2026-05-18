<script lang="ts">
    import { page, router, Link } from '@inertiajs/svelte';
    import { destroy as logout } from '@/actions/Laravel/Fortify/Http/Controllers/AuthenticatedSessionController';
    import type { Snippet } from 'svelte';
    import AnimatedThemeToggler from '@/components/animated-theme-toggler/AnimatedThemeToggler.svelte';
    import SearchOverlay from '@/components/search-overlay/SearchOverlay.svelte';
    import AnimatedNotificationList from '@/components/animated-notification/AnimatedNotificationList.svelte';
    import { Home, Search, Bell, Mail, Sparkles, User, Feather } from 'lucide-svelte';
    import AnimatedGradientText from '@/components/AnimatedGradientText.svelte';
    import { edit as editProfile } from '@/routes/profile';
    import { edit as editSecurity } from '@/routes/security';

    let { children }: { children?: Snippet } = $props();

    const auth = $derived(page.props.auth as any);
    const currentUrl = $derived(page.url);

    let searchOpen = $state(false);

    const navItems = [
        { label: 'Home', icon: Home, href: '/dashboard' },
        { label: 'Notifications', icon: Bell, href: '/notifications' },
        { label: 'Messages', icon: Mail, href: '/messages' },
        { label: 'Flok', icon: Sparkles, href: '#flok' },
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
                <AnimatedThemeToggler class="p-3 rounded-full transition-colors text-gray-900 dark:text-white" />
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
            <div class="w-10 h-10 rounded-full shrink-0 overflow-hidden bg-neutral-200 dark:bg-neutral-800 flex items-center justify-center">
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

    <!-- Main content -->
    <main class="w-full sm:w-[600px] border-x border-neutral-200 dark:border-neutral-800 min-h-screen">

        <!-- Settings header -->
        <div class="sticky top-0 bg-white/80 dark:bg-black/80 backdrop-blur-md z-10 border-b border-neutral-200 dark:border-neutral-800">
            <div class="px-4 pt-4 pb-0">
                <h1 class="text-xl font-bold mb-3">Settings</h1>
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

</div>

<SearchOverlay bind:open={searchOpen} />
<AnimatedNotificationList />
