<script lang="ts">
    import { page, router } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { destroy as logout } from '@/actions/Laravel/Fortify/Http/Controllers/AuthenticatedSessionController';
    import AnimatedThemeToggler from '@/components/animated-theme-toggler/AnimatedThemeToggler.svelte';
    import { Home, Search, Bell, Mail, Sparkles, User, Feather } from 'lucide-svelte';
    import SearchOverlay from '@/components/search-overlay/SearchOverlay.svelte';
    import AnimatedNotificationList from '@/components/animated-notification/AnimatedNotificationList.svelte';
    import AnimatedGradientText from '@/components/AnimatedGradientText.svelte';

    let searchOpen = $state(false);

    let activeTab = $state('forYou');

    const tabs = [
        { id: 'forYou', label: 'For you' },
        { id: 'following', label: 'Following' },
    ];

    const posts = [
        {
            id: 1,
            author: 'Luke',
            handle: '@lukeshaw',
            time: '2h',
            content: 'Just deployed the new landing page. Really loving the pure OLED black aesthetic and mesh gradients!',
            likes: 142,
            reposts: 12,
            replies: 5,
        },
        {
            id: 2,
            author: 'AI Engineering Daily',
            handle: '@ai_eng',
            time: '5h',
            content: 'The future of frontend iteration is autonomous agents. Speed and architecture are scaling rapidly.',
            likes: 890,
            reposts: 145,
            replies: 34,
        },
    ];

    const auth = $derived(page.props.auth as any);
</script>

<AppHead title="Home" />

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
                {#each [
                    { label: 'Home', icon: Home, href: '/dashboard' },
                    { label: 'Notifications', icon: Bell, href: '/notifications' },
                    { label: 'Messages', icon: Mail, href: '/messages' },
                    { label: 'Flok', icon: Sparkles, href: '/flock-ai' },
                    { label: 'Profile', icon: User, href: '/settings/profile' },
                ] as item}
                    {@const Icon = item.icon}
                    <a href={item.href} class="flex items-center gap-5 p-3 rounded-full w-fit transition-colors text-gray-500 dark:text-neutral-300 hover:text-gray-900 hover:bg-neutral-100 dark:hover:text-white dark:hover:bg-neutral-900">
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

    <!-- Main feed -->
    <main class="w-full sm:w-[600px] border-x border-neutral-200 dark:border-neutral-800 min-h-screen">

        <!-- Tabs -->
        <div class="sticky top-0 bg-white/80 dark:bg-black/80 backdrop-blur-md z-10 border-b border-neutral-200 dark:border-neutral-800">
            <div class="flex px-4 py-3 pb-0">
                {#each tabs as tab}
                    <button
                        class="flex-1 pb-3 transition-colors relative flex justify-center font-bold text-[15px] {activeTab !== tab.id ? 'text-neutral-500 hover:text-gray-900 dark:hover:text-white' : ''}"
                        onclick={() => activeTab = tab.id}
                    >
                        {tab.label}
                        {#if activeTab === tab.id}
                            <div class="absolute bottom-0 h-1 w-14 bg-blue-500 rounded-full"></div>
                        {/if}
                    </button>
                {/each}
            </div>
        </div>

        <!-- Composer -->
        <div class="px-4 py-4 border-b border-neutral-200 dark:border-neutral-800 gap-4 hidden sm:flex">
            <div class="w-10 h-10 bg-neutral-200 dark:bg-neutral-800 rounded-full shrink-0"></div>
            <div class="flex-1 flex flex-col">
                <input
                    type="text"
                    placeholder="What is happening?!"
                    class="bg-transparent text-xl placeholder-neutral-400 dark:placeholder-neutral-500 outline-none py-2 pb-6 border-b border-neutral-200 dark:border-neutral-800 focus:border-neutral-400 dark:focus:border-neutral-700 w-full"
                />
                <div class="flex justify-between items-center pt-3">
                    <div class="flex gap-1 text-blue-500">
                        {#each Array(5) as _}
                            <button class="p-2 hover:bg-blue-500/10 rounded-full transition-colors" aria-label="Attach">
                                <div class="w-5 h-5 border border-current rounded-sm"></div>
                            </button>
                        {/each}
                    </div>
                    <button class="bg-blue-500 text-white font-bold rounded-full py-1.5 px-4 opacity-50 cursor-not-allowed text-sm">
                        Post
                    </button>
                </div>
            </div>
        </div>

        <!-- Posts -->
        <div>
            {#each posts as post}
                <article class="p-4 border-b border-neutral-200 dark:border-neutral-800 hover:bg-neutral-50 dark:hover:bg-neutral-950/50 transition-colors cursor-pointer flex gap-3">
                    <div class="w-10 h-10 bg-neutral-200 dark:bg-neutral-800 rounded-full shrink-0 mt-1"></div>
                    <div class="flex flex-col w-full">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1 text-[15px]">
                                <span class="font-bold hover:underline">{post.author}</span>
                                <span class="text-neutral-500">{post.handle} · {post.time}</span>
                            </div>
                            <button class="text-neutral-500 hover:bg-blue-500/10 hover:text-blue-500 rounded-full px-2 py-0.5 transition-colors">···</button>
                        </div>
                        <p class="mt-0.5 text-[15px] leading-normal">{post.content}</p>
                        <div class="flex justify-between text-neutral-500 mt-3 max-w-md text-[13px]">
                            <button class="flex items-center gap-2 hover:text-blue-500 transition-colors group">
                                <div class="p-2 group-hover:bg-blue-500/10 rounded-full -m-2 mr-0">💬</div>
                                {post.replies}
                            </button>
                            <button class="flex items-center gap-2 hover:text-green-500 transition-colors group">
                                <div class="p-2 group-hover:bg-green-500/10 rounded-full -m-2 mr-0">🔁</div>
                                {post.reposts}
                            </button>
                            <button class="flex items-center gap-2 hover:text-pink-500 transition-colors group">
                                <div class="p-2 group-hover:bg-pink-500/10 rounded-full -m-2 mr-0">❤️</div>
                                {post.likes}
                            </button>
                            <button class="flex items-center gap-2 hover:text-blue-500 transition-colors group">
                                <div class="p-2 group-hover:bg-blue-500/10 rounded-full -m-2 mr-0">📊</div>
                                {Math.floor(post.likes * 12.5)}
                            </button>
                            <div class="flex gap-2">
                                <button class="p-2 hover:bg-blue-500/10 hover:text-blue-500 rounded-full -m-2 transition-colors">🔖</button>
                                <button class="p-2 hover:bg-blue-500/10 hover:text-blue-500 rounded-full -m-2 transition-colors">📤</button>
                            </div>
                        </div>
                    </div>
                </article>
            {/each}
        </div>
    </main>

    <!-- Right sidebar -->
    <aside class="w-[350px] pl-8 py-2 hidden lg:block">
        <div class="sticky top-0 z-10 bg-white dark:bg-black pt-1 pb-2">
            <div class="bg-neutral-100 dark:bg-neutral-900 rounded-full flex items-center px-4 py-2.5 border border-transparent focus-within:border-blue-500 focus-within:bg-white dark:focus-within:bg-black transition-colors">
                <Search class="w-4 h-4 text-neutral-400 shrink-0" />
                <input type="text" placeholder="Search" class="bg-transparent outline-none w-full ml-4 text-[15px] placeholder-neutral-400 dark:placeholder-neutral-500" />
            </div>
        </div>

        <div class="bg-neutral-100 dark:bg-[#16181c] rounded-2xl p-4 mt-3 border border-neutral-200 dark:border-neutral-800">
            <h2 class="font-extrabold mb-2">Subscribe to Premium</h2>
            <p class="text-[15px] mb-3 leading-snug">Subscribe to unlock new features and if eligible, receive a share of ads revenue.</p>
            <button class="bg-blue-500 hover:bg-blue-600 text-white font-bold rounded-full py-2 px-4 transition-colors text-[15px]">
                Subscribe
            </button>
        </div>

        <div class="bg-neutral-100 dark:bg-[#16181c] rounded-2xl pt-4 mt-4 border border-neutral-200 dark:border-neutral-800">
            <h2 class="font-extrabold text-xl px-4 mb-3">What's happening</h2>
            {#each [1, 2, 3] as _}
                <div class="px-4 py-3 hover:bg-black/5 dark:hover:bg-white/5 transition-colors cursor-pointer flex justify-between">
                    <div>
                        <div class="text-[13px] text-neutral-500">Technology · Trending</div>
                        <div class="font-bold mt-0.5 text-[15px]">#SvelteKit</div>
                        <div class="text-[13px] text-neutral-500 mt-1">24.5K posts</div>
                    </div>
                    <button class="text-neutral-500 hover:text-blue-500 transition-colors p-1 h-fit hover:bg-blue-500/10 rounded-full">···</button>
                </div>
            {/each}
            <button class="p-4 text-blue-500 hover:bg-black/5 dark:hover:bg-white/5 w-full text-left rounded-b-2xl transition-colors text-[15px]">
                Show more
            </button>
        </div>
    </aside>

</div>

<SearchOverlay bind:open={searchOpen} />
<AnimatedNotificationList />

<style>
    :global(::-webkit-scrollbar) { width: 8px; }
    :global(::-webkit-scrollbar-track) { background: transparent; }
    :global(::-webkit-scrollbar-thumb) { background: #333; border-radius: 4px; }
    :global(::-webkit-scrollbar-thumb:hover) { background: #555; }
</style>
