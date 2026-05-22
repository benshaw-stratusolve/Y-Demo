<script lang="ts">

    import AppHead from '@/components/AppHead.svelte';
    import { destroy as logout } from '@/actions/Laravel/Fortify/Http/Controllers/AuthenticatedSessionController';
    import AnimatedThemeToggler from '@/components/animated-theme-toggler/AnimatedThemeToggler.svelte';
    import { Home, Search, Bell, Sparkles, User, Shield } from 'lucide-svelte';
    import SearchOverlay from '@/components/search-overlay/SearchOverlay.svelte';
    import AnimatedNotificationList from '@/components/animated-notification/AnimatedNotificationList.svelte';
    import AnimatedGradientText from '@/components/AnimatedGradientText.svelte';
    import UserAvatar from '@/components/UserAvatar.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Pagination, PaginationContent, PaginationItem, PaginationLink, PaginationPrevious, PaginationNext, PaginationEllipsis } from '@/components/ui/pagination';
    import CoolMode from '@/components/magic/cool-mode/cool-mode.svelte';
    import { notifications } from '@/lib/notifications.svelte';
    import { page, router } from '@inertiajs/svelte';
    import { destroy as destroyPost, like as likePost, reply as replyToPost } from '@/actions/App/Http/Controllers/PostController';
    import BanModal from '@/components/BanModal.svelte';

    let { post, isFollowing = false, authorPosts = [] }: { post: any; isFollowing: boolean; authorPosts: any[] } = $props();
    let openMenuId = $state<number | null>(null);
    let showAuthorPosts = $state(false);
    let replyText = $state('');
    let replyError = $state<string | null>(null);
    let submittingReply = $state(false);
    let composerOpen = $state(false);
    let searchOpen = $state(false);
    const AUTHOR_PER_PAGE = 5;
    let authorPostsPage = $state(1);
    const visibleAuthorPosts = $derived(
        authorPosts.slice((authorPostsPage - 1) * AUTHOR_PER_PAGE, authorPostsPage * AUTHOR_PER_PAGE)
    );

    const auth = $derived(page.props.auth as any);
    const unreadCount = $derived((page.props as any).unread_notifications_count as number ?? 0);

    function toggleLike(post: any) {
        router.post(likePost(post.id).url, {}, {
            preserveScroll: true,
            preserveState: true,
        });
    }

    function submitReply() {
        if (!replyText.trim() || submittingReply) { return; }
        submittingReply = true;
        replyError = null;
        router.post(replyToPost(post.id).url, { body: replyText }, {
            preserveScroll: true,
            onSuccess: () => {
                replyText = '';
                replyError = null;
                composerOpen = false;
                notifications.add({ type: 'success', title: 'Comment posted!', description: 'Your comment has been added.' });
            },
            onError: (errors: Record<string, string>) => { replyError = errors.body ?? null; },
            onFinish: () => { submittingReply = false; },
        });
    }

    function deleteReply(id: number) {
        openMenuId = null;
        router.delete(destroyPost(id).url, {
            preserveScroll: true,
            onSuccess: () => {
                notifications.add({ type: 'info', title: 'Comment deleted', description: 'Your comment has been removed.' });
            },
        });
    }

</script>

<BanModal />

<div class="min-h-screen bg-white dark:bg-black text-gray-900 dark:text-gray-100 flex justify-center font-sans">

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
            <button
                onclick={() => searchOpen = true}
                class="flex items-center gap-5 p-3 rounded-full w-fit transition-colors text-gray-500 dark:text-neutral-300 hover:text-gray-900 hover:bg-neutral-100 dark:hover:text-white dark:hover:bg-neutral-900"
            >
                <Search class="w-6 h-6" />
                <span class="text-xl hidden xl:block">Explore</span>
            </button>
            {#if auth?.user?.is_admin}
                <a
                    href="/admin"
                    class="flex items-center gap-5 p-3 rounded-full w-fit transition-colors text-indigo-500 hover:text-indigo-700 hover:bg-indigo-50 dark:hover:bg-indigo-950/40"
                >
                    <Shield class="w-6 h-6" />
                    <span class="text-xl hidden xl:block font-semibold">Admin Panel</span>
                </a>
            {/if}
        </nav>

    </div>

    <div class="flex items-center gap-1 mb-4 w-full">
        <a
            href="/settings/profile"
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

<main class="max-w-[600px] w-full border-x border-neutral-200 dark:border-neutral-800 min-h-screen">

    <!-- Back button -->
    <div class="sticky top-0 bg-white/80 dark:bg-black/80 backdrop-blur-md z-10 border-b border-neutral-200 dark:border-neutral-800 px-4 py-3 flex items-center gap-4">
        <button onclick={() => history.back()} class="text-neutral-500 hover:text-gray-900 dark:hover:text-white transition-colors">
            ←
        </button>
        <p class="font-bold text-[17px]">Post</p>
    </div>

    <!-- Author row -->
    <div class="flex items-center justify-between px-4 py-3 border-b border-neutral-200 dark:border-neutral-800">
        <div class="flex items-center gap-3">
            <UserAvatar user={post.user} />
            <div>
                <p class="font-bold text-sm">{post.user.name}</p>
                <p class="text-neutral-500 text-sm">@{post.user.username}</p>
            </div>
        </div>
        {#if post.user.id !== auth?.user?.id}
            <button onclick={() => router.post(`/users/${post.user.id}/follow`, {}, {
                preserveScroll: true,
                preserveState: true,
            })}
                class="bg-gray-900 dark:bg-white text-white dark:text-black font-bold rounded-full px-4 py-1.5 text-sm">
                {isFollowing ? 'Following' : 'Follow'}
            </button>
        {/if}
    </div>

    <!-- Author stats -->
    <div class="flex gap-6 px-4 py-3 border-b border-neutral-200 dark:border-neutral-800 text-sm">
        <span><strong>{post.user.followers_count ?? 0}</strong> <span class="text-neutral-500">Followers</span></span>
        <span><strong>{post.user.follows_count ?? 0}</strong> <span class="text-neutral-500">Following</span></span>
        <span><strong>{post.user.posts_count ?? 0}</strong> <span class="text-neutral-500">Posts</span></span>
    </div>

    <!-- Post body -->
    <div class="px-4 py-6 border-b border-neutral-200 dark:border-neutral-800">
        {#if post.body}<p class="text-xl">{post.body}</p>{/if}
        {#if post.image_url}
            <div class="mt-3 rounded-2xl overflow-hidden border border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-900 flex items-center justify-center">
                <img src={post.image_url} alt="Post image" class="max-h-[500px] w-full object-contain" loading="lazy" />
            </div>
        {/if}
    </div>

    <div class="flex justify-baseline gap-6 text-neutral-500 px-4 py-3 border-b border-neutral-200 dark:border-neutral-800 text-[15px]">
        <button
            onclick={() => { composerOpen = !composerOpen; }}
            class="flex items-center gap-2 transition-colors hover:text-blue-500 {composerOpen ? 'text-blue-500' : ''}"
        >
            <div class="p-2 hover:bg-blue-500/10 rounded-full -m-2 mr-0">💬</div>
            {post.replies_count ?? 0}
        </button>
        <button
            class="flex items-center gap-2 transition-colors group hover:text-pink-500 {post.liked_by_user ? 'text-pink-500' : ''}"
            onclick={() => toggleLike(post)}
        >
            <div class="p-2 group-hover:bg-pink-500/10 rounded-full -m-2 mr-0">{post.liked_by_user ? '❤️' : '🤍'}</div>
            {post.likes_count ?? 0}
        </button>
    </div>
    <!-- Comment composer -->
    {#if composerOpen}
        <div class="px-4 py-3 border-b border-neutral-200 dark:border-neutral-800">
            <textarea
                bind:value={replyText}
                placeholder="Post your comment"
                rows="3"
                maxlength="280"
                class="w-full resize-none bg-transparent text-[15px] placeholder-neutral-400 outline-none border border-neutral-200 dark:border-neutral-800 rounded-xl p-3"
                onkeydown={(e) => { if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) { submitReply(); } }}
            ></textarea>
            {#if replyError}
                <p class="text-red-500 text-sm mt-1">{replyError}</p>
            {/if}
            <div class="flex justify-end mt-2">
                <button
                    onclick={submitReply}
                    disabled={!replyText.trim() || submittingReply}
                    class="bg-blue-500 hover:bg-blue-600 text-white font-bold rounded-full py-1.5 px-4 text-sm transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    {submittingReply ? 'Posting…' : 'Comment'}
                </button>
            </div>
        </div>
    {/if}

    {#each post.replies as reply}
        <div class="relative flex gap-3 px-4 py-3 border-b border-neutral-200 dark:border-neutral-800">
            <UserAvatar user={reply.user} size="xs" />
            <div class="flex-1 min-w-0">
                <p class="font-bold text-sm">{reply.user.name} <span class="text-neutral-500 font-normal">@{reply.user.username}</span></p>
                <p class="text-sm mt-1">{reply.body}</p>
            </div>
            {#if reply.user.id === auth?.user?.id}
                <div class="absolute top-3 right-3">
                    <button
                        type="button"
                        onclick={() => openMenuId = openMenuId === reply.id ? null : reply.id}
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

    <!-- Author posts toggle -->
    {#if authorPosts.length > 0}
        <button
            onclick={() => showAuthorPosts = !showAuthorPosts}
            class="w-full px-4 py-4 text-blue-500 font-semibold text-sm hover:bg-neutral-50 dark:hover:bg-neutral-950 transition-colors text-left border-b border-neutral-200 dark:border-neutral-800"
        >
            {showAuthorPosts ? '↑ Reduce' : `↓ Show more posts by ${post.user.name}`}
        </button>
        {#if showAuthorPosts}
            {#each visibleAuthorPosts as ap}
                <a
                    href="/posts/{ap.id}"
                    class="flex flex-col gap-1 px-4 py-4 border-b border-neutral-200 dark:border-neutral-800 hover:bg-neutral-50 dark:hover:bg-neutral-950 transition-colors"
                >
                    <p class="text-[15px]">{ap.body}</p>
                    <div class="flex gap-5 text-neutral-500 text-[13px] mt-2">
                        <span>💬 {ap.replies_count ?? 0}</span>
                        <span>❤️ {ap.likes_count ?? 0}</span>
                    </div>
                </a>
            {/each}
            {#if authorPosts.length > AUTHOR_PER_PAGE}
                <div class="py-4">
                    <Pagination count={authorPosts.length} perPage={AUTHOR_PER_PAGE} bind:page={authorPostsPage}>
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
        {/if}
    {/if}

    </main>
</div>

<SearchOverlay bind:open={searchOpen} />
<AnimatedNotificationList />
