<script lang="ts">
    import { Deferred, page, router } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { destroy as logout } from '@/actions/Laravel/Fortify/Http/Controllers/AuthenticatedSessionController';
    import { destroy as destroyPost, like as likePost, reply as replyToPost, show as showPost, store as storePost } from '@/actions/App/Http/Controllers/PostController';
    import AnimatedThemeToggler from '@/components/animated-theme-toggler/AnimatedThemeToggler.svelte';
    import { Home, Search, Bell, BellOff, Sparkles, User, Feather, ImagePlus, X, Shield } from 'lucide-svelte';
    import SearchOverlay from '@/components/search-overlay/SearchOverlay.svelte';
    import { isSoundEnabled, setSoundEnabled } from '@/lib/notification-sounds';
    import AnimatedNotificationList from '@/components/animated-notification/AnimatedNotificationList.svelte';
    import AnimatedGradientText from '@/components/AnimatedGradientText.svelte';
    import UserAvatar from '@/components/UserAvatar.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Pagination, PaginationContent, PaginationItem, PaginationPrevious, PaginationNext, PaginationLink, PaginationEllipsis } from '@/components/ui/pagination';
    import CoolMode from '@/components/magic/cool-mode/cool-mode.svelte';
    import { notifications } from '@/lib/notifications.svelte';
    import BanModal from '@/components/BanModal.svelte';
    import { charCounterClass, showCharCounter } from '@/lib/char-counter';
    import { realtimeStore } from '@/lib/realtime.svelte';

    let searchOpen = $state(false);
    let soundEnabled = $state(isSoundEnabled());

    function toggleSound() {
        soundEnabled = !soundEnabled;
        setSoundEnabled(soundEnabled);
    }
    let openCommentId = $state<number | null>(null);
    let writingReplyId = $state<number | null>(null);
    let postModalOpen = $state(false);
    let postBody = $state('');
    let postImage = $state<File | null>(null);
    let postImagePreview = $state<string | null>(null);
    let postError = $state<string | null>(null);
    let replyTexts = $state<Record<number, string>>({});

    const MAX_IMAGE_BYTES = 5 * 1024 * 1024;

    function selectImage(e: Event) {
        const file = (e.target as HTMLInputElement).files?.[0] ?? null;
        if (postImagePreview) {
            URL.revokeObjectURL(postImagePreview);
        }
        if (file && file.size > MAX_IMAGE_BYTES) {
            postError = 'Image must be 5 MB or smaller.';
            postImage = null;
            postImagePreview = null;
            (e.target as HTMLInputElement).value = '';
            return;
        }
        postError = null;
        postImage = file;
        postImagePreview = file ? URL.createObjectURL(file) : null;
    }

    function clearImage() {
        if (postImagePreview) {
            URL.revokeObjectURL(postImagePreview);
        }
        postImage = null;
        postImagePreview = null;
    }

    const tabs = [
        { id: 'forYou', label: 'For you' },
        { id: 'following', label: 'Following' },
    ];

    let { posts, trending, topAccounts = [], following, isDiscoveryFeed = false, activeTab = 'forYou' }: {
        posts: { data: any[]; current_page: number; last_page: number; total: number; per_page: number };
        trending: any[];
        topAccounts: { id: number; name: string; username: string; avatar_url: string | null; is_following: boolean }[];
        following: any[];
        isDiscoveryFeed: boolean;
        activeTab: string;
    } = $props();

    // ── Infinite scroll ──────────────────────────────────────────────────────
    let allPosts = $state<any[]>([...posts.data]);
    let scrollPage = $state(1);
    let hasMore = $state(posts.last_page > 1);
    let loadingMore = $state(false);
    let sentinel = $state<HTMLElement | null>(null);
    let prevTab = activeTab;

    // Merge Inertia-driven updates (tab switch or poll refresh) into allPosts
    $effect(() => {
        const data = posts.data;
        const tab = activeTab;
        untrack(() => {
            if (tab !== prevTab) {
                prevTab = tab;
                allPosts = [...data];
                scrollPage = 1;
                hasMore = posts.last_page > 1;
            } else {
                // Poll refresh: merge fresh page-1 data at top, keep extras
                const freshIds = new Set(data.map((p: any) => p.id));
                const rest = allPosts.filter((p: any) => !freshIds.has(p.id));
                allPosts = [...data, ...rest];
            }
        });
    });

    $effect(() => {
        const incoming = realtimeStore.newPosts;
        if (incoming.length > 0) {
            untrack(() => {
                allPosts = [...realtimeStore.consumeNewPosts(), ...allPosts];
            });
        }
    });

    // IntersectionObserver — load next page when sentinel enters viewport
    $effect(() => {
        if (!sentinel) return;
        const obs = new IntersectionObserver(
            ([entry]) => { if (entry.isIntersecting) loadMore(); },
            { rootMargin: '400px' }
        );
        obs.observe(sentinel);
        return () => obs.disconnect();
    });

    async function loadMore() {
        if (loadingMore || !hasMore) return;
        loadingMore = true;
        const nextPage = scrollPage + 1;
        try {
            const res = await fetch(`/dashboard/posts.json?tab=${activeTab}&page=${nextPage}`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) throw new Error('fetch failed');
            const result = await res.json();
            const ids = new Set(allPosts.map((p: any) => p.id));
            allPosts = [...allPosts, ...result.data.filter((p: any) => !ids.has(p.id))];
            scrollPage = result.current_page;
            hasMore = result.current_page < result.last_page;
        } catch {
            // silently ignore — user can scroll up/down to retry
        } finally {
            loadingMore = false;
        }
    }
    // ─────────────────────────────────────────────────────────────────────────

    function switchTab(tab: string) {
        router.get('/dashboard', { tab }, { preserveScroll: true, replace: true, only: ['posts', 'activeTab'] });
    }

    const FOLLOWING_PER_PAGE = 10;
    let followingPage = $state(1);
    const followingVisible = $derived(
        following.slice((followingPage - 1) * FOLLOWING_PER_PAGE, followingPage * FOLLOWING_PER_PAGE)
    );

    const auth = $derived(page.props.auth as any);
    const unreadCount = $derived(
        ((page.props as any).unread_notifications_count as number ?? 0) + realtimeStore.liveUnreadIncrement
    );

    function submitPost() {
        if (!postBody.trim() && !postImage) { return; }
        postError = null;
        const data = new FormData();
        if (postBody.trim()) { data.append('body', postBody); }
        if (postImage) { data.append('image', postImage); }
        router.post(storePost().url, data, {
            preserveScroll: true,
            onSuccess: () => {
                postBody = ''; postModalOpen = false;
                clearImage();
                notifications.add({ type: 'success', title: 'Posted!', description: 'Your post has been uploaded!' });
            },
            onError: (errors) => { postError = errors.body ?? errors.image ?? null; },
        });
    }

    function toggleLike(post: any) {
        const liked = localLikes[post.id]?.liked ?? post.liked_by_user;
        const count = localLikes[post.id]?.count ?? post.likes_count;
        localLikes[post.id] = { liked: !liked, count: count + (liked ? -1 : 1) };
        router.post(likePost(post.id).url, {}, {
            preserveScroll: true,
            preserveState: true,
            onError: () => { delete localLikes[post.id]; },
        });
    }

    let localLikes = $state<Record<number, { liked: boolean; count: number }>>({});
    let localFollowing = $state<Record<number, boolean>>({});

    function toggleFollow(userId: number, currentlyFollowing: boolean) {
        localFollowing[userId] = !currentlyFollowing;
        router.post(`/users/${userId}/follow`, {}, {
            preserveScroll: true,
            preserveState: true,
            onError: () => { delete localFollowing[userId]; },
        });
    }

    let openMenuId = $state<number | null>(null);

    const MAX_CHARS = 280;
    const bodyCharsLeft = $derived(MAX_CHARS - postBody.length);
    function replyCharsLeft(id: number) { return MAX_CHARS - (replyTexts[id]?.length ?? 0); }

    function deletePost(post: any) {
        openMenuId = null;
        router.delete(destroyPost(post.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                notifications.add({ type: 'success', title: 'Post deleted', description: 'Your post has been removed.' });
            },
        });
    }

    function deleteReply(reply: any) {
        openMenuId = null;
        router.delete(destroyPost(reply.id).url, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                notifications.add({ type: 'info', title: 'Comment deleted', description: 'Your comment has been removed.' });
            },
        });
    }

    function submitReply(post: any) {
        const body = replyTexts[post.id] ?? '';
        if (!body.trim()) { return; }
        router.post(replyToPost(post.id).url, { body }, {
            preserveScroll: true,
            onSuccess: () => {
                replyTexts[post.id] = '';
                writingReplyId = null;
                notifications.add({ type: 'success', title: 'Comment posted!', description: 'Your comment has been added.' });
            },
            onError: (errors) => {
                if (errors.reply_limit) {
                    notifications.add({ type: 'warning', title: 'Slow down', description: errors.reply_limit });
                }
            },
        });
    }

    function isInteractiveClick(event: MouseEvent | KeyboardEvent): boolean {
        return event.target instanceof Element && Boolean(event.target.closest('a, button, input, textarea, select, [role="button"]'));
    }

    function visitPost(event: MouseEvent, post: any) {
        if (isInteractiveClick(event)) {
            return;
        }

        router.visit(showPost(post.id).url);
    }

    function visitPostFromKeyboard(event: KeyboardEvent, post: any) {
        if (isInteractiveClick(event) || (event.key !== 'Enter' && event.key !== ' ')) {
            return;
        }

        event.preventDefault();
        router.visit(showPost(post.id).url);
    }
</script>

<AppHead title="Home" />
<BanModal />

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

            <button onclick={() => postModalOpen = true} class="bg-blue-500 hover:bg-blue-600 text-white font-bold rounded-full py-3.5 px-8 w-[90%] mt-4 transition-colors hidden xl:block shadow-md">
                Post
            </button>
            <button onclick={() => postModalOpen = true} class="bg-blue-500 hover:bg-blue-600 text-white rounded-full p-3 mt-4 transition-colors xl:hidden shadow-md" aria-label="Post">
                <Feather class="w-6 h-6" />
            </button>
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

    <!-- Main feed -->
    <main class="w-full sm:w-[600px] border-x border-neutral-200 dark:border-neutral-800 min-h-screen">

        <!-- Tabs -->
        <div class="sticky top-0 bg-white/80 dark:bg-black/80 backdrop-blur-md z-10 border-b border-neutral-200 dark:border-neutral-800">
            <div class="flex px-4 py-3 pb-0">
                {#each tabs as tab}
                    <button
                        class="flex-1 pb-3 transition-colors relative flex justify-center font-bold text-[15px] {activeTab !== tab.id ? 'text-neutral-500 hover:text-gray-900 dark:hover:text-white' : ''}"
                        onclick={() => switchTab(tab.id)}
                    >
                        {tab.label}
                        {#if activeTab === tab.id}
                            <div class="absolute bottom-0 h-1 w-14 bg-blue-500 rounded-full"></div>
                        {/if}
                    </button>
                {/each}
            </div>
        </div>

        {#if activeTab === 'following'}
            {#if following.length === 0}
                <div class="flex flex-col items-center justify-center py-20 px-8 text-center">
                    <div class="text-5xl mb-4">🤔</div>
                    <h2 class="font-extrabold text-xl mb-2">Hmmm, nothing to find here.</h2>
                    <p class="text-neutral-500 text-[15px] mb-1">Y's it so empty?</p>
                    <p class="text-neutral-400 text-[13px]">Start following people and their posts will show up here.</p>
                </div>
            {/if}
            {#each followingVisible as account}
                {@const isFollowing = localFollowing[account.id] ?? account.is_following}
                <div class="flex items-center gap-3 p-4 border-b border-neutral-200 dark:border-neutral-800 hover:bg-neutral-50 dark:hover:bg-neutral-950 transition-colors">
                    <a href="/users/{account.id}" class="flex items-center gap-3 flex-1 min-w-0">
                        <UserAvatar user={account} />
                        <div class="min-w-0">
                            <p class="font-bold truncate">{account.name}</p>
                            <p class="text-neutral-500 text-sm">@{account.username}</p>
                        </div>
                    </a>
                    <button
                        onclick={() => toggleFollow(account.id, isFollowing)}
                        class="shrink-0 rounded-full px-4 py-1.5 text-[13px] font-bold transition-colors {isFollowing ? 'border border-neutral-300 dark:border-neutral-700 hover:border-red-300 hover:text-red-500' : 'bg-gray-900 dark:bg-white text-white dark:text-black hover:bg-gray-700 dark:hover:bg-neutral-200'}"
                    >
                        {isFollowing ? 'Following' : 'Follow'}
                    </button>
                </div>
            {/each}
            {#if following.length > FOLLOWING_PER_PAGE}
                <div class="py-4 border-b border-neutral-200 dark:border-neutral-800">
                    <Pagination count={following.length} perPage={FOLLOWING_PER_PAGE} bind:page={followingPage}>
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
        {:else}

        <!-- Composer -->
        <div class="px-4 py-4 border-b border-neutral-200 dark:border-neutral-800 gap-4 hidden sm:flex">
            <UserAvatar user={auth?.user} />
            <div class="flex-1 flex flex-col">
                <textarea
                    bind:value={postBody}
                    placeholder="What is happening?!"
                    rows="2"
                    class="bg-transparent text-xl placeholder-neutral-400 dark:placeholder-neutral-500 outline-none py-2 pb-2 w-full resize-none"
                ></textarea>
                {#if postError}
                    <p class="text-red-500 text-sm mt-1">{postError}</p>
                {/if}
                {#if postImagePreview}
                    <div class="relative mt-2 rounded-2xl overflow-hidden border border-neutral-200 dark:border-neutral-800 shadow-sm w-fit">
                        <img src={postImagePreview} alt="Preview" class="max-h-48 rounded-2xl object-cover" />
                        <button onclick={clearImage} class="absolute top-2 right-2 bg-black/60 text-white rounded-full p-1 hover:bg-black/80 transition-colors">
                            <X class="w-3 h-3" />
                        </button>
                    </div>
                {/if}
                <div class="flex items-center justify-end gap-3 mt-3">
                    <label class="cursor-pointer text-blue-500 hover:text-blue-400 transition-colors p-1.5 rounded-full hover:bg-blue-500/10">
                        <ImagePlus class="w-5 h-5" />
                        <input type="file" accept="image/*" class="hidden" onchange={selectImage} />
                    </label>
                    {#if showCharCounter(bodyCharsLeft)}
                        <span class="text-xs font-semibold tabular-nums {charCounterClass(bodyCharsLeft)}">
                            {bodyCharsLeft}
                        </span>
                    {/if}
                    <CoolMode>
                        <button
                            onclick={submitPost}
                            disabled={(!postBody.trim() && !postImage) || bodyCharsLeft < 0}
                            class="bg-blue-500 text-white font-bold rounded-full py-1.5 px-4 text-sm transition-colors hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            Post
                        </button>
                    </CoolMode>
                </div>
            </div>
        </div>

        <!-- Posts -->
        <div>
            {#if isDiscoveryFeed}
                <div class="px-4 py-3 border-b border-neutral-200 dark:border-neutral-800 bg-blue-50/50 dark:bg-blue-950/20">
                    <p class="text-[13px] font-semibold text-blue-500">✦ Discover Y</p>
                    <p class="text-neutral-500 text-[13px] mt-0.5">Follow people to personalise your feed. Here's what's happening right now.</p>
                </div>
            {/if}
            {#each allPosts as post}
                <div
                    role="link"
                    tabindex="0"
                    onclick={(event) => visitPost(event, post)}
                    onkeydown={(event) => visitPostFromKeyboard(event, post)}
                    class="p-4 border-b border-neutral-200 dark:border-neutral-800 hover:bg-neutral-50 dark:hover:bg-neutral-950/50 transition-colors cursor-pointer flex gap-3"
                >
                    <UserAvatar user={post.user} class="mt-1" />
                    <div class="flex flex-col w-full">
                        <div class="flex items-center justify-between w-full">
                            <div class="flex items-center gap-1 text-[15px]">
                                <span class="font-bold hover:underline">{post.user.name}</span>
                                <span class="text-neutral-500">@{post.user.username} · {new Date(post.created_at).toLocaleDateString()}</span>
                            </div>
                            {#if post.user.id === auth?.user?.id}
                                <div class="relative">
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
                                                onclick={() => deletePost(post)}
                                                class="w-full text-left px-4 py-3 text-red-500 font-bold text-[14px] hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors"
                                            >
                                                Delete post
                                            </button>
                                        </div>
                                    {/if}
                                </div>
                            {/if}
                        </div>
                        {#if post.image_url}
                            <div class="mt-3 rounded-2xl overflow-hidden border border-neutral-200 dark:border-neutral-800 bg-neutral-950 shadow-sm flex items-center justify-center">
                                <img src={post.image_url} alt="Post image" class="max-h-[500px] w-full object-contain" loading="lazy" />
                            </div>
                        {/if}
                        {#if post.body}<p class="mt-2 text-[15px] leading-normal">{post.body}</p>{/if}
                        <div class="flex gap-6 text-neutral-500 mt-3 text-[13px]">
                            <button
                                type="button"
                                class="flex items-center gap-2 transition-colors group hover:text-blue-500"
                                onclick={() => openCommentId = openCommentId === post.id ? null : post.id}
                            >
                                <div class="p-2 group-hover:bg-blue-500/10 rounded-full -m-2 mr-0">💬</div>
                                {realtimeStore.postCounts[post.id]?.replies_count ?? post.replies_count ?? 0}
                            </button>
                            <button
                                type="button"
                                class="flex items-center gap-2 transition-colors group hover:text-pink-500 {(localLikes[post.id]?.liked ?? post.liked_by_user) ? 'text-pink-500' : ''}"
                                onclick={() => toggleLike(post)}
                            >
                                <div class="p-2 group-hover:bg-pink-500/10 rounded-full -m-2 mr-0">{(localLikes[post.id]?.liked ?? post.liked_by_user) ? '❤️' : '🤍'}</div>
                                {localLikes[post.id]?.count ?? realtimeStore.postCounts[post.id]?.likes_count ?? post.likes_count ?? 0}
                            </button>
                        </div>
                    </div>
                </div>
                {#if openCommentId === post.id}
                    <div class="border-b border-neutral-200 dark:border-neutral-800">
                        <!-- Existing replies -->
                        {#each post.replies ?? [] as reply}
                            <div class="relative px-4 py-3 flex gap-3 border-t border-neutral-100 dark:border-neutral-900">
                                <UserAvatar user={reply.user} size="xs" />
                                <div class="min-w-0 flex-1 pr-10">
                                    <div class="flex items-center gap-1 text-[13px]">
                                        <span class="font-bold">{reply.user.name}</span>
                                        <span class="text-neutral-500">@{reply.user.username} · {new Date(reply.created_at).toLocaleDateString()}</span>
                                    </div>
                                    <p class="text-[14px] leading-normal mt-0.5">{reply.body}</p>
                                </div>
                                {#if reply.user.id === auth?.user?.id}
                                    <div class="absolute top-2 right-3">
                                        <button
                                            type="button"
                                            onclick={() => openMenuId = openMenuId === reply.id ? null : reply.id}
                                            class="p-1 rounded-full text-neutral-400 hover:text-blue-500 hover:bg-blue-500/10 transition-colors text-lg leading-none"
                                        >···</button>
                                        {#if openMenuId === reply.id}
                                            <button type="button" class="fixed inset-0 z-10 cursor-default" aria-label="Close menu" onclick={() => openMenuId = null}></button>
                                            <div class="absolute right-0 top-7 z-20 bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 rounded-xl shadow-lg overflow-hidden w-40">
                                                <button
                                                    type="button"
                                                    onclick={() => deleteReply(reply)}
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

                        <!-- Write reply -->
                        {#if writingReplyId === post.id}
                            <div class="px-4 pt-3 pb-4 border-t border-neutral-100 dark:border-neutral-900">
                                <textarea
                                    bind:value={replyTexts[post.id]}
                                    rows="3"
                                    placeholder="Post your comment"
                                    class="w-full bg-transparent border border-neutral-200 dark:border-neutral-700 rounded-xl px-4 py-3 text-[15px] placeholder-neutral-400 outline-none focus:border-blue-500 resize-none transition-colors"
                                ></textarea>
                                <div class="flex items-center justify-end gap-3 mt-2">
                                    {#if showCharCounter(replyCharsLeft(post.id))}
                                        {@const left = replyCharsLeft(post.id)}
                                        <span class="text-xs font-semibold tabular-nums {charCounterClass(left)}">
                                            {left}
                                        </span>
                                    {/if}
                                    <button
                                        onclick={() => submitReply(post)}
                                        disabled={!(replyTexts[post.id] ?? '').trim() || replyCharsLeft(post.id) < 0}
                                        class="bg-blue-500 hover:bg-blue-600 text-white font-bold rounded-full py-1.5 px-4 text-sm transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        Comment
                                    </button>
                                </div>
                            </div>
                        {:else}
                            <div class="px-4 py-3 border-t border-neutral-100 dark:border-neutral-900">
                                <button
                                    onclick={() => writingReplyId = post.id}
                                    class="text-blue-500 hover:text-blue-400 text-[14px] font-semibold transition-colors"
                                >
                                    + Comment
                                </button>
                            </div>
                        {/if}
                    </div>
                {/if}
            {/each}
        </div>
        <!-- Infinite scroll sentinel + loading indicator -->
        <div bind:this={sentinel} class="h-1"></div>
        {#if loadingMore}
            <div class="py-6 flex justify-center">
                <div class="flex gap-1.5">
                    <span class="w-2 h-2 bg-neutral-400 dark:bg-neutral-600 rounded-full animate-bounce" style="animation-delay:0ms"></span>
                    <span class="w-2 h-2 bg-neutral-400 dark:bg-neutral-600 rounded-full animate-bounce" style="animation-delay:150ms"></span>
                    <span class="w-2 h-2 bg-neutral-400 dark:bg-neutral-600 rounded-full animate-bounce" style="animation-delay:300ms"></span>
                </div>
            </div>
        {:else if !hasMore && allPosts.length > 0}
            <p class="py-6 text-center text-neutral-400 text-sm">You're all caught up ✓</p>
        {/if}
        {/if}
    </main>

    <!-- Right sidebar -->
    <aside class="w-[350px] pl-8 py-2 hidden lg:block">
        <div class="sticky top-0 z-10 bg-white dark:bg-black pt-1 pb-2">
            <div class="flex items-center gap-2">
                <button
                    onclick={() => searchOpen = true}
                    class="flex-1 bg-neutral-100 dark:bg-neutral-900 hover:bg-neutral-200 dark:hover:bg-neutral-800 rounded-full flex items-center px-4 py-2.5 transition-colors text-left"
                >
                    <Search class="w-4 h-4 text-neutral-400 shrink-0" />
                    <span class="ml-4 text-[15px] text-neutral-400 dark:text-neutral-500">Search</span>
                </button>
                <AnimatedThemeToggler class="p-2.5 rounded-full transition-colors text-gray-900 dark:text-white hover:bg-neutral-100 dark:hover:bg-neutral-900 shrink-0" />
                <button
                    onclick={toggleSound}
                    class="p-2.5 rounded-full transition-colors hover:bg-neutral-100 dark:hover:bg-neutral-900 shrink-0 {soundEnabled ? 'text-gray-900 dark:text-white' : 'text-neutral-400 dark:text-neutral-600'}"
                    aria-label={soundEnabled ? 'Mute notification sounds' : 'Unmute notification sounds'}
                    title={soundEnabled ? 'Mute notification sounds' : 'Unmute notification sounds'}
                >
                    {#if soundEnabled}
                        <Bell class="w-5 h-5" />
                    {:else}
                        <BellOff class="w-5 h-5" />
                    {/if}
                </button>
            </div>
        </div>

        <Deferred data={['trending', 'topAccounts']}>
            {#snippet fallback()}
                <!-- Trending skeleton -->
                <div class="bg-neutral-100 dark:bg-[#16181c] rounded-2xl pt-4 mt-4 border border-neutral-200 dark:border-neutral-800">
                    <div class="h-7 w-36 bg-neutral-200 dark:bg-neutral-800 rounded-full mx-4 mb-3 animate-pulse"></div>
                    {#each [1, 2, 3, 4, 5] as _}
                        <div class="px-4 py-3 flex flex-col gap-1.5">
                            <div class="h-3 w-20 bg-neutral-200 dark:bg-neutral-800 rounded-full animate-pulse"></div>
                            <div class="h-4 w-full bg-neutral-200 dark:bg-neutral-800 rounded-full animate-pulse"></div>
                            <div class="h-3 w-16 bg-neutral-200 dark:bg-neutral-800 rounded-full animate-pulse"></div>
                        </div>
                    {/each}
                </div>
                <!-- Who to follow skeleton -->
                <div class="bg-neutral-100 dark:bg-[#16181c] rounded-2xl pt-4 mt-4 border border-neutral-200 dark:border-neutral-800">
                    <div class="h-7 w-28 bg-neutral-200 dark:bg-neutral-800 rounded-full mx-4 mb-3 animate-pulse"></div>
                    {#each [1, 2, 3, 4, 5] as _}
                        <div class="px-4 py-3 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full bg-neutral-200 dark:bg-neutral-800 animate-pulse shrink-0"></div>
                                <div class="flex flex-col gap-1.5">
                                    <div class="h-3.5 w-24 bg-neutral-200 dark:bg-neutral-800 rounded-full animate-pulse"></div>
                                    <div class="h-3 w-16 bg-neutral-200 dark:bg-neutral-800 rounded-full animate-pulse"></div>
                                </div>
                            </div>
                            <div class="h-7 w-20 bg-neutral-200 dark:bg-neutral-800 rounded-full animate-pulse shrink-0"></div>
                        </div>
                    {/each}
                </div>
            {/snippet}

            <div class="bg-neutral-100 dark:bg-[#16181c] rounded-2xl pt-4 mt-4 border border-neutral-200 dark:border-neutral-800">
                <h2 class="font-extrabold text-xl px-4 mb-3">Trending on Y!</h2>
                {#each trending as post}
                    <a href="/posts/{post.id}" class="px-4 py-3 hover:bg-black/5 dark:hover:bg-white/5 transition-colors flex justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-[13px] text-neutral-500">@{post.user.username}</div>
                            <div class="font-bold mt-0.5 text-[15px] truncate">{post.body}</div>
                            <div class="text-[13px] text-neutral-500 mt-1">{post.likes_count} likes</div>
                        </div>
                    </a>
                {/each}
            </div>

            <div class="bg-neutral-100 dark:bg-[#16181c] rounded-2xl pt-4 mt-4 border border-neutral-200 dark:border-neutral-800">
                <h2 class="font-extrabold text-xl px-4 mb-3">Who to follow</h2>
                {#each topAccounts as account}
                    <div class="px-4 py-3 hover:bg-black/5 dark:hover:bg-white/5 transition-colors flex items-center justify-between gap-3">
                        <a href="/users/{account.id}" class="flex items-center gap-3 min-w-0">
                            <UserAvatar user={account} />
                            <div class="min-w-0">
                                <p class="font-bold text-[14px] truncate">{account.name}</p>
                                <p class="text-neutral-500 text-[13px]">@{account.username}</p>
                            </div>
                        </a>
                        <button
                            onclick={() => router.post(`/users/${account.id}/follow`, {}, { preserveScroll: true })}
                            class="shrink-0 rounded-full px-4 py-1.5 text-[13px] font-bold transition-colors {account.is_following ? 'border border-neutral-300 dark:border-neutral-700 hover:border-red-300 hover:text-red-500' : 'bg-gray-900 dark:bg-white text-white dark:text-black hover:bg-gray-700 dark:hover:bg-neutral-200'}"
                        >
                            {account.is_following ? 'Following' : 'Follow'}
                        </button>
                    </div>
                {/each}
            </div>
        </Deferred>
    </aside>

</div>

{#if postModalOpen}
    <!-- Backdrop — separate z-index so backdrop-filter blurs the page behind it -->
    <button
        class="fixed inset-0 z-40 w-full bg-black/60 backdrop-blur-md cursor-default"
        aria-label="Close modal"
        onclick={() => postModalOpen = false}
    ></button>

    <!-- Modal -->
    <div
        class="fixed inset-0 z-50 flex items-start justify-center pt-16 px-4 pointer-events-none"
        role="dialog"
        aria-modal="true"
    >
        <div class="pointer-events-auto w-full max-w-[600px] bg-white dark:bg-black rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xl">
            <div class="px-4 py-4 flex gap-4">
                <UserAvatar user={auth?.user} />
                <div class="flex-1 flex flex-col">
                    <textarea
                        bind:value={postBody}
                        rows="4"
                        placeholder="What is happening?!"
                        class="bg-transparent text-xl placeholder-neutral-400 dark:placeholder-neutral-500 outline-none py-2 w-full resize-none"
                    ></textarea>
                    {#if postError}
                        <p class="text-red-500 text-sm mt-1">{postError}</p>
                    {/if}
                    {#if postImagePreview}
                        <div class="relative mt-2 rounded-2xl overflow-hidden border border-neutral-200 dark:border-neutral-800 shadow-sm w-fit">
                            <img src={postImagePreview} alt="Preview" class="max-h-48 rounded-2xl object-cover" />
                            <button onclick={clearImage} class="absolute top-2 right-2 bg-black/60 text-white rounded-full p-1 hover:bg-black/80 transition-colors">
                                <X class="w-3 h-3" />
                            </button>
                        </div>
                    {/if}
                    <div class="flex items-center justify-end gap-3 mt-3">
                        <label class="cursor-pointer text-blue-500 hover:text-blue-400 transition-colors p-1.5 rounded-full hover:bg-blue-500/10">
                            <ImagePlus class="w-5 h-5" />
                            <input type="file" accept="image/*" class="hidden" onchange={selectImage} />
                        </label>
                        {#if showCharCounter(bodyCharsLeft)}
                            <span class="text-xs font-semibold tabular-nums {charCounterClass(bodyCharsLeft)}">
                                {bodyCharsLeft}
                            </span>
                        {/if}
                        <button
                            onclick={submitPost}
                            disabled={(!postBody.trim() && !postImage) || bodyCharsLeft < 0}
                            class="bg-blue-500 hover:bg-blue-600 text-white font-bold rounded-full py-2 px-6 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            Post
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
{/if}

<SearchOverlay bind:open={searchOpen} />
<AnimatedNotificationList />

{#if auth?.user?.banned_at}
    <div class="fixed inset-0 z-[100] flex items-center justify-center p-4">
        <!-- Blurred backdrop -->
        <div class="absolute inset-0 bg-black/60 backdrop-blur-md"></div>

        <!-- Modal card -->
        <div class="relative z-10 w-full max-w-md bg-white dark:bg-neutral-950 border border-neutral-200 dark:border-neutral-800 rounded-3xl shadow-2xl p-8 text-center">

            <!-- Y logo -->
            <div class="flex items-center justify-center mb-6">
                <div class="w-16 h-16 rounded-full bg-black dark:bg-white flex items-center justify-center shadow-lg">
                    <img src="/images/Y-dark-remove.png" alt="Y" class="h-9 w-9 object-contain dark:invert invert-0" />
                </div>
            </div>

            <h1 class="text-2xl font-extrabold mb-2 text-gray-900 dark:text-white">Account Permanently Banned</h1>

            <p class="text-neutral-500 dark:text-neutral-400 text-[15px] leading-relaxed mb-2">
                You've been removed from Y — and honestly? That's on you.
            </p>
            <p class="text-neutral-500 dark:text-neutral-400 text-[15px] leading-relaxed mb-2">
                After three strikes of inappropriate language, your account has been permanently suspended. We take our community seriously, and that's exactly...
            </p>
            <p class="text-2xl font-black mb-6 text-gray-900 dark:text-white tracking-tight">
                That's <span class="italic">Y</span>.
            </p>

            <button
                onclick={() => router.post(logout().url)}
                class="w-full bg-black dark:bg-white text-white dark:text-black font-bold rounded-full py-3.5 text-[15px] hover:bg-neutral-800 dark:hover:bg-neutral-200 transition-colors"
            >
                Noted
            </button>
        </div>
    </div>
{/if}

<style>
    :global(::-webkit-scrollbar) { width: 8px; }
    :global(::-webkit-scrollbar-track) { background: transparent; }
    :global(::-webkit-scrollbar-thumb) { background: #333; border-radius: 4px; }
    :global(::-webkit-scrollbar-thumb:hover) { background: #555; }
</style>
