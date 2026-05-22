<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { destroy as logout } from '@/actions/Laravel/Fortify/Http/Controllers/AuthenticatedSessionController';
    import AnimatedThemeToggler from '@/components/animated-theme-toggler/AnimatedThemeToggler.svelte';
    import { Home, Search, Bell, Sparkles, User } from 'lucide-svelte';
    import AnimatedGradientText from '@/components/AnimatedGradientText.svelte';
    import UserAvatar from '@/components/UserAvatar.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Pagination, PaginationContent, PaginationItem, PaginationLink, PaginationPrevious, PaginationNext, PaginationEllipsis } from '@/components/ui/pagination';
    import { page, router } from '@inertiajs/svelte';
    import { destroy as destroyPost } from '@/actions/App/Http/Controllers/PostController';

    let { profileUser, posts, isFollowing = false, isOwnProfile = false, activeTab = 'posts' }: {
        profileUser: any;
        posts: { data: any[]; current_page: number; last_page: number; total: number; per_page: number };
        isFollowing: boolean;
        isOwnProfile: boolean;
        activeTab: string;
    } = $props();

    let openMenuId = $state<number | null>(null);

    function switchTab(tab: string) {
        router.get(`/users/${profileUser.id}`, { tab }, { preserveScroll: true, only: ['posts', 'activeTab'], replace: true });
    }

    function deleteReply(id: number) {
        openMenuId = null;
        router.delete(destroyPost(id).url, { preserveScroll: true });
    }

    const auth = $derived(page.props.auth as any);
    const unreadCount = $derived((page.props as any).unread_notifications_count as number ?? 0);
</script>

<AppHead title="{profileUser.name} (@{profileUser.username})" />

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
                    { label: 'Flok', icon: Sparkles, href: '/flock-ai' },
                    { label: 'Profile', icon: User, href: `/users/${auth?.user?.id}` },
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
    <main class="max-w-[600px] w-full border-x border-neutral-200 dark:border-neutral-800 min-h-screen">

        <!-- Back -->
        <div class="sticky top-0 bg-white/80 dark:bg-black/80 backdrop-blur-md z-10 border-b border-neutral-200 dark:border-neutral-800 px-4 py-3 flex items-center gap-4">
            <button onclick={() => router.visit('/dashboard')} class="text-neutral-500 hover:text-gray-900 dark:hover:text-white transition-colors">
                ←
            </button>
            <div>
                <p class="font-bold text-[17px] leading-tight">{profileUser.name}</p>
                <p class="text-neutral-500 text-[13px]">{profileUser.posts_count ?? 0} posts</p>
            </div>
        </div>

        <!-- Profile header -->
        <div class="px-4 pt-6 pb-4 border-b border-neutral-200 dark:border-neutral-800">
            <div class="flex items-start justify-between mb-4">
                <!-- Avatar -->
                <UserAvatar user={profileUser} size="xl" />

                <!-- Action button -->
                {#if isOwnProfile}
                    <a
                        href="/settings/profile"
                        class="border border-neutral-300 dark:border-neutral-700 font-bold rounded-full px-4 py-1.5 text-sm hover:bg-neutral-100 dark:hover:bg-neutral-900 transition-colors"
                    >
                        Edit profile
                    </a>
                {:else}
                    <button
                        onclick={() => router.post(`/users/${profileUser.id}/follow`, {}, { preserveScroll: true, only: ['isFollowing'] })}
                        class="bg-gray-900 dark:bg-white text-white dark:text-black font-bold rounded-full px-4 py-1.5 text-sm hover:bg-gray-700 dark:hover:bg-neutral-200 transition-colors"
                    >
                        {isFollowing ? 'Following' : 'Follow'}
                    </button>
                {/if}
            </div>

            <!-- Name & username -->
            <p class="font-bold text-xl leading-tight">{profileUser.name}</p>
            <p class="text-neutral-500 text-[15px] mb-3">@{profileUser.username}</p>
            {#if profileUser.bio}
                <p class="text-[15px] mb-3">{profileUser.bio}</p>
            {/if}

            <!-- Stats -->
            <div class="flex gap-5 text-[15px]">
                <span><strong>{profileUser.followers_count ?? 0}</strong> <span class="text-neutral-500">Followers</span></span>
                <span><strong>{profileUser.follows_count ?? 0}</strong> <span class="text-neutral-500">Following</span></span>
            </div>
        </div>

        <!-- Tabs -->
        <div class="flex border-b border-neutral-200 dark:border-neutral-800">
            {#each [{ id: 'posts', label: 'Posts' }, { id: 'replies', label: 'Replies' }] as tab}
                <button
                    onclick={() => switchTab(tab.id)}
                    class="flex-1 py-3 text-[15px] font-bold relative transition-colors {activeTab === tab.id ? '' : 'text-neutral-500 hover:text-gray-900 dark:hover:text-white'}"
                >
                    {tab.label}
                    {#if activeTab === tab.id}
                        <div class="absolute bottom-0 left-1/2 -translate-x-1/2 h-1 w-14 bg-blue-500 rounded-full"></div>
                    {/if}
                </button>
            {/each}
        </div>

        <!-- Posts tab -->
        {#if activeTab === 'posts'}
            {#each posts.data as post}
                <div class="relative flex flex-col gap-1 border-b border-neutral-200 dark:border-neutral-800 hover:bg-neutral-50 dark:hover:bg-neutral-950 transition-colors">
                    <a href="/posts/{post.id}" class="flex flex-col gap-1 px-4 py-4 {isOwnProfile ? 'pr-12' : ''}">
                        {#if post.image_url}
                            <div class="rounded-2xl overflow-hidden border border-neutral-200 dark:border-neutral-800 bg-neutral-950 shadow-sm flex items-center justify-center mb-2">
                                <img src={post.image_url} alt="Post image" class="max-h-[500px] w-full object-contain" loading="lazy" />
                            </div>
                        {/if}
                        {#if post.body}<p class="text-[15px] leading-normal">{post.body}</p>{/if}
                        <div class="flex gap-5 text-neutral-500 text-[13px] mt-1">
                            <span>💬 {post.replies_count ?? 0}</span>
                            <span>❤️ {post.likes_count ?? 0}</span>
                            <span class="ml-auto">{new Date(post.created_at).toLocaleDateString()}</span>
                        </div>
                    </a>

                    {#if isOwnProfile}
                        <div class="absolute top-3 right-3">
                            <button
                                type="button"
                                onclick={(e) => { e.stopPropagation(); openMenuId = openMenuId === post.id ? null : post.id; }}
                                class="p-1 rounded-full text-neutral-400 hover:text-blue-500 hover:bg-blue-500/10 transition-colors text-lg leading-none"
                            >···</button>
                            {#if openMenuId === post.id}
                                <button type="button" class="fixed inset-0 z-10 cursor-default" aria-label="Close menu" onclick={() => openMenuId = null}></button>
                                <div class="absolute right-0 top-7 z-20 bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 rounded-xl shadow-lg overflow-hidden w-36">
                                    <button
                                        type="button"
                                        onclick={() => deleteReply(post.id)}
                                        class="w-full text-left px-4 py-3 text-red-500 font-bold text-[14px] hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors"
                                    >
                                        Delete post
                                    </button>
                                </div>
                            {/if}
                        </div>
                    {/if}
                </div>
            {/each}
            {#if posts.data.length === 0}
                <div class="flex flex-col items-center justify-center py-16 text-neutral-500">
                    <p class="text-4xl mb-3">🤷</p>
                    <p class="font-bold text-lg">Thoughts? Ideas? Opinions?</p>
                    <p class="text-sm mt-1 text-neutral-500">Apparently not. Yet.</p>
                </div>
            {/if}

        <!-- Replies tab -->
        {:else}
            {#each posts.data as reply}
                <div class="relative flex flex-col gap-1 px-4 py-4 border-b border-neutral-200 dark:border-neutral-800 hover:bg-neutral-50 dark:hover:bg-neutral-950 transition-colors">
                    <a href="/posts/{reply.parent_post_id}" class="flex flex-col gap-1">
                        {#if reply.parent}
                            <p class="text-neutral-500 text-[13px] truncate">
                                Replying to <span class="text-blue-500">@{reply.parent.user?.username}</span>
                                · {reply.parent.body?.slice(0, 60)}{(reply.parent.body?.length ?? 0) > 60 ? '…' : ''}
                            </p>
                        {/if}
                        <p class="text-[15px] leading-normal mt-1">{reply.body}</p>
                        <div class="flex gap-5 text-neutral-500 text-[13px] mt-1">
                            <span>❤️ {reply.likes_count ?? 0}</span>
                            <span class="ml-auto">{new Date(reply.created_at).toLocaleDateString()}</span>
                        </div>
                    </a>

                    {#if isOwnProfile}
                        <div class="absolute top-3 right-3">
                            <button
                                type="button"
                                onclick={(e) => { e.stopPropagation(); openMenuId = openMenuId === reply.id ? null : reply.id; }}
                                class="p-1 rounded-full text-neutral-400 hover:text-blue-500 hover:bg-blue-500/10 transition-colors text-lg leading-none"
                            >···</button>
                            {#if openMenuId === reply.id}
                                <button type="button" class="fixed inset-0 z-10 cursor-default" aria-label="Close menu" onclick={() => openMenuId = null}></button>
                                <div class="absolute right-0 top-7 z-20 bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 rounded-xl shadow-lg overflow-hidden w-36">
                                    <button
                                        type="button"
                                        onclick={() => deleteReply(reply.id)}
                                        class="w-full text-left px-4 py-3 text-red-500 font-bold text-[14px] hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors"
                                    >
                                        Delete comment
                                    </button>
                                </div>
                            {/if}
                        </div>
                    {/if}
                </div>
            {/each}
            {#if posts.data.length === 0}
                <div class="flex flex-col items-center justify-center py-16 text-neutral-500">
                    <p class="font-bold text-lg">No replies yet</p>
                    <p class="text-sm mt-1">{isOwnProfile ? "You haven't" : profileUser.name + " hasn't"} replied to any posts yet.</p>
                </div>
            {/if}
        {/if}

        {#if posts.last_page > 1}
            <div class="py-4">
                <Pagination
                    count={posts.total}
                    perPage={posts.per_page}
                    page={posts.current_page}
                    onPageChange={(p) => router.get(`/users/${profileUser.id}`, { page: p }, { preserveScroll: true, only: ['posts'], replace: true })}
                >
                    {#snippet children({ pages, currentPage: cp })}
                        <PaginationContent>
                            <PaginationItem><PaginationPrevious /></PaginationItem>
                            {#each pages as pg (pg.key)}
                                {#if pg.type === 'ellipsis'}
                                    <PaginationItem><PaginationEllipsis /></PaginationItem>
                                {:else}
                                    <PaginationItem><PaginationLink page={pg} isActive={cp === pg.value} /></PaginationItem>
                                {/if}
                            {/each}
                            <PaginationItem><PaginationNext /></PaginationItem>
                        </PaginationContent>
                    {/snippet}
                </Pagination>
            </div>
        {/if}

    </main>
</div>
