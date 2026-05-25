<script lang="ts">
    import { page, router } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import { useHttp } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import UserAvatar from '@/components/UserAvatar.svelte';
    import AnimatedThemeToggler from '@/components/animated-theme-toggler/AnimatedThemeToggler.svelte';
    import { realtimeStore } from '@/lib/realtime.svelte';
    import { animateMessageBubble, startTypingDots } from '@/lib/anime-utils';
    import { destroy as logout } from '@/actions/Laravel/Fortify/Http/Controllers/AuthenticatedSessionController';
    import { index as messagesIndex, show as showConversation, store as sendMessage, typing as sendTyping } from '@/actions/App/Http/Controllers/MessagesController';
    import { Home, Bell, Sparkles, User, Send, MessageSquare, ArrowLeft, SquarePen, Search, X } from 'lucide-svelte';
    import { findOrCreate as findOrCreateConversation } from '@/actions/App/Http/Controllers/MessagesController';
    import AnimatedGradientText from '@/components/AnimatedGradientText.svelte';
    import { Badge } from '@/components/ui/badge';

    type OtherUser = { id: number; name: string; username: string; avatar_url: string | null };
    type ConversationItem = {
        id: number;
        other_user: OtherUser;
        latest_message: string | null;
        latest_message_at: string | null;
        unread_count: number;
    };
    type MessageItem = {
        id: number;
        conversation_id: number;
        body: string;
        sender_id: number;
        sender: OtherUser;
        created_at: string;
        is_mine: boolean;
        _isNew?: boolean;
    };

    let {
        conversations,
        activeConversation = null,
        messages = null,
        followingUsers = [],
    }: {
        conversations: ConversationItem[];
        activeConversation: { id: number; other_user: OtherUser } | null;
        messages: MessageItem[] | null;
        followingUsers: OtherUser[];
    } = $props();

    const auth = $derived(page.props.auth as any);
    const unreadCount = $derived(
        ((page.props as any).unread_notifications_count as number ?? 0) + realtimeStore.liveUnreadIncrement
    );
    const unreadMessagesCount = $derived(
        ((page.props as any).unread_messages_count as number ?? 0) + realtimeStore.unreadMessagesIncrement
    );

    let allMessages = $state<MessageItem[]>(messages ? [...messages] : []);
    let messageBody = $state('');
    let messagesContainer = $state<HTMLElement | null>(null);
    let typingTimer: ReturnType<typeof setTimeout> | null = null;
    let optimisticIdCounter = 0;

    let composeOpen = $state(false);
    let composeSearch = $state('');

    const filteredFollowing = $derived(
        followingUsers.filter(u =>
            u.name.toLowerCase().includes(composeSearch.toLowerCase()) ||
            u.username.toLowerCase().includes(composeSearch.toLowerCase())
        )
    );

    function openCompose() {
        composeSearch = '';
        composeOpen = true;
    }

    function startConversation(userId: number) {
        composeOpen = false;
        router.post(findOrCreateConversation(userId).url);
    }

    $effect(() => {
        return () => {
            if (typingTimer) clearTimeout(typingTimer);
        };
    });

    const isTyping = $derived(
        activeConversation !== null &&
        realtimeStore.typingConversations.has(activeConversation.id)
    );

    const http = useHttp();

    function typingDots(node: HTMLElement) {
        const cancel = startTypingDots(node);
        return { destroy: cancel };
    }

    function bubbleEnter(node: HTMLElement, opts: { isMine: boolean; isNew?: boolean }) {
        if (opts.isNew) animateMessageBubble(node, opts.isMine);
        return {};
    }

    // Receive real-time messages
    $effect(() => {
        if (!activeConversation) return;
        const incoming = realtimeStore.newMessages[activeConversation.id];
        if (incoming?.length) {
            untrack(() => {
                const msgs = realtimeStore.consumeNewMessages(activeConversation!.id).map(m => ({ ...m, _isNew: true }));
                allMessages = [...allMessages, ...msgs];
                scrollToBottom();
            });
        }
    });

    // Reset messages when conversation changes
    $effect(() => {
        const msgs = messages;
        untrack(() => {
            allMessages = msgs ? [...msgs] : [];
            scrollToBottom();
        });
    });

    function scrollToBottom() {
        setTimeout(() => {
            if (messagesContainer) {
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }
        }, 50);
    }

    function handleKeydown(e: KeyboardEvent) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            submitMessage();
        }
    }

    function handleTypingInput() {
        if (!activeConversation) return;
        if (typingTimer) clearTimeout(typingTimer);
        typingTimer = setTimeout(() => {
            http.post(sendTyping(activeConversation!.id).url);
        }, 400);
    }

    function submitMessage() {
        if (!messageBody.trim() || !activeConversation) return;
        const body = messageBody;
        messageBody = '';

        const optimistic: MessageItem = {
            id: -(++optimisticIdCounter),
            conversation_id: activeConversation.id,
            body,
            sender_id: auth.user.id,
            sender: auth.user,
            created_at: 'just now',
            is_mine: true,
            _isNew: true,
        };
        allMessages = [...allMessages, optimistic];
        scrollToBottom();

        router.post(sendMessage(activeConversation.id).url, { body }, {
            preserveScroll: true,
            preserveState: true,
            only: [],
            onError: () => {
                allMessages = allMessages.filter((m) => m.id !== optimistic.id);
                messageBody = body;
            },
        });
    }
</script>

<AppHead title="Messages" />

<div class="min-h-screen bg-white dark:bg-black text-gray-900 dark:text-gray-100 flex justify-center font-sans">

    <!-- Left nav sidebar -->
    <header class="w-[275px] flex-col justify-between py-2 px-4 h-screen sticky top-0 hidden sm:flex shrink-0">
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
                    { label: 'Messages', icon: MessageSquare, href: messagesIndex().url, active: true },
                    { label: 'Flok', icon: Sparkles, href: '/flock-ai' },
                    { label: 'Profile', icon: User, href: `/users/${auth?.user?.id}` },
                ] as item}
                    {@const Icon = item.icon}
                    <a
                        href={item.href}
                        class="flex items-center gap-5 p-3 rounded-full w-fit transition-colors {item.active ? 'text-gray-900 dark:text-white font-bold' : 'text-gray-500 dark:text-neutral-300 hover:text-gray-900 hover:bg-neutral-100 dark:hover:text-white dark:hover:bg-neutral-900'}"
                    >
                        {#if item.label === 'Notifications'}
                            <div class="relative">
                                <Icon class="w-6 h-6" />
                                {#if unreadCount > 0}
                                    <span class="absolute -top-1 -right-1 min-w-[16px] h-4 bg-blue-500 rounded-full text-white text-[9px] font-bold flex items-center justify-center px-0.5">{unreadCount > 99 ? '99+' : unreadCount}</span>
                                {/if}
                            </div>
                        {:else if item.label === 'Messages'}
                            <div class="relative">
                                <Icon class="w-6 h-6" />
                                {#if unreadMessagesCount > 0}
                                    <span class="absolute -top-1 -right-1 min-w-[16px] h-4 bg-blue-500 rounded-full text-white text-[9px] font-bold flex items-center justify-center px-0.5">{unreadMessagesCount > 99 ? '99+' : unreadMessagesCount}</span>
                                {/if}
                            </div>
                        {:else if item.label === 'Flok'}
                            <svg width="0" height="0" style="position:absolute;overflow:hidden">
                                <defs>
                                    <linearGradient id="flok-grad-msg" x1="0%" y1="0%" x2="100%" y2="100%">
                                        <stop offset="0%" stop-color="#60a5fa" />
                                        <stop offset="100%" stop-color="#a78bfa" />
                                    </linearGradient>
                                </defs>
                            </svg>
                            <Icon class="w-6 h-6" style="stroke: url(#flok-grad-msg)" />
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

    <!-- Conversation list -->
    <div class="w-full sm:w-[350px] border-x border-neutral-200 dark:border-neutral-800 min-h-screen flex flex-col {activeConversation ? 'hidden sm:flex' : 'flex'}">
        <div class="sticky top-0 bg-white/80 dark:bg-black/80 backdrop-blur-md z-10 border-b border-neutral-200 dark:border-neutral-800 px-4 py-3 flex items-center justify-between">
            <h1 class="font-extrabold text-xl">Messages</h1>
            <div class="flex items-center gap-1">
                <button
                    onclick={openCompose}
                    class="p-2 rounded-full hover:bg-neutral-100 dark:hover:bg-neutral-900 transition-colors"
                    aria-label="New message"
                >
                    <SquarePen class="w-5 h-5" />
                </button>
                <AnimatedThemeToggler class="p-2 rounded-full hover:bg-neutral-100 dark:hover:bg-neutral-900 transition-colors" />
            </div>
        </div>

        {#if conversations.length === 0}
            <div class="flex flex-col items-center justify-center flex-1 px-8 text-center py-20">
                <MessageSquare class="w-12 h-12 text-neutral-300 dark:text-neutral-700 mb-4" />
                <p class="font-bold text-lg">No conversations yet</p>
                <p class="text-neutral-500 text-sm mt-1">Message someone from their profile page.</p>
            </div>
        {:else}
            {#each conversations as conv}
                {@const isActive = activeConversation?.id === conv.id}
                <a
                    href={showConversation(conv.id).url}
                    class="flex items-center gap-3 px-4 py-3 border-b border-neutral-200 dark:border-neutral-800 hover:bg-neutral-50 dark:hover:bg-neutral-950 transition-colors {isActive ? 'bg-neutral-100 dark:bg-neutral-900' : ''}"
                >
                    <div class="relative shrink-0">
                        <UserAvatar user={conv.other_user} />
                        {#if conv.unread_count > 0}
                            <span class="absolute -top-0.5 -right-0.5 w-3 h-3 bg-blue-500 rounded-full border-2 border-white dark:border-black"></span>
                        {/if}
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-[15px] truncate">{conv.other_user.name}</span>
                            {#if conv.latest_message_at}
                                <span class="text-neutral-400 text-xs shrink-0 ml-2">{conv.latest_message_at}</span>
                            {/if}
                        </div>
                        {#if conv.latest_message}
                            <p class="text-neutral-500 text-sm truncate {conv.unread_count > 0 ? 'font-semibold text-gray-900 dark:text-white' : ''}">{conv.latest_message}</p>
                        {/if}
                    </div>
                </a>
            {/each}
        {/if}
    </div>

    <!-- Thread panel -->
    {#if activeConversation}
        <div class="flex-1 border-r border-neutral-200 dark:border-neutral-800 flex flex-col min-h-screen max-h-screen">
            <!-- Thread header -->
            <div class="sticky top-0 bg-white/80 dark:bg-black/80 backdrop-blur-md z-10 border-b border-neutral-200 dark:border-neutral-800 px-4 py-3 flex items-center gap-3">
                <a href={messagesIndex().url} class="sm:hidden p-1 rounded-full hover:bg-neutral-100 dark:hover:bg-neutral-900 transition-colors">
                    <ArrowLeft class="w-5 h-5" />
                </a>
                <a href="/users/{activeConversation.other_user.id}" class="flex items-center gap-3 hover:opacity-80 transition-opacity">
                    <UserAvatar user={activeConversation.other_user} />
                    <div>
                        <p class="font-bold text-[15px]">{activeConversation.other_user.name}</p>
                        <p class="text-neutral-500 text-sm">@{activeConversation.other_user.username}</p>
                    </div>
                </a>
            </div>

            <!-- Messages -->
            <div
                bind:this={messagesContainer}
                class="flex-1 overflow-y-auto px-4 py-4 flex flex-col gap-2"
            >
                {#if allMessages.length === 0}
                    <div class="flex flex-col items-center justify-center flex-1 text-center py-12">
                        <p class="text-neutral-400 text-sm">Say hello to {activeConversation.other_user.name}!</p>
                    </div>
                {:else}
                    {#each allMessages as msg (msg.id)}
                        <div
                            use:bubbleEnter={{ isMine: msg.is_mine, isNew: msg._isNew ?? false }}
                            class="flex {msg.is_mine ? 'justify-end' : 'justify-start'} gap-2"
                        >
                            {#if !msg.is_mine}
                                <UserAvatar user={msg.sender} size="xs" class="mt-1 shrink-0" />
                            {/if}
                            <div
                                class="max-w-[70%] px-4 py-2.5 rounded-2xl text-[15px] leading-snug
                                    {msg.is_mine
                                        ? 'bg-blue-500 text-white rounded-br-sm'
                                        : 'bg-neutral-100 dark:bg-neutral-800 text-gray-900 dark:text-white rounded-bl-sm'}"
                            >
                                {msg.body}
                            </div>
                        </div>
                    {/each}

                    {#if isTyping}
                        <div class="flex justify-start gap-2">
                            <UserAvatar user={activeConversation.other_user} size="xs" class="mt-1 shrink-0" />
                            <div
                                use:typingDots
                                class="bg-neutral-100 dark:bg-neutral-800 rounded-2xl rounded-bl-sm px-4 py-3 flex gap-1.5 items-center h-10"
                            >
                                <span class="typing-dot w-2 h-2 bg-neutral-400 rounded-full"></span>
                                <span class="typing-dot w-2 h-2 bg-neutral-400 rounded-full"></span>
                                <span class="typing-dot w-2 h-2 bg-neutral-400 rounded-full"></span>
                            </div>
                        </div>
                    {/if}
                {/if}
            </div>

            <!-- Input -->
            <div class="border-t border-neutral-200 dark:border-neutral-800 px-4 py-3 flex gap-3 items-end bg-white dark:bg-black sticky bottom-0">
                <textarea
                    bind:value={messageBody}
                    onkeydown={handleKeydown}
                    oninput={handleTypingInput}
                    placeholder="Start a new message"
                    rows="1"
                    class="flex-1 bg-neutral-100 dark:bg-neutral-900 rounded-2xl px-4 py-2.5 text-[15px] placeholder-neutral-400 outline-none resize-none max-h-32 focus:ring-1 focus:ring-blue-500 transition-all"
                ></textarea>
                <button
                    onclick={submitMessage}
                    disabled={!messageBody.trim()}
                    class="bg-blue-500 hover:bg-blue-600 disabled:opacity-40 disabled:cursor-not-allowed text-white rounded-full p-2.5 transition-colors shrink-0"
                    aria-label="Send"
                >
                    <Send class="w-4 h-4" />
                </button>
            </div>
        </div>
    {:else}
        <!-- Empty state when no conversation selected (desktop only) -->
        <div class="flex-1 hidden sm:flex flex-col items-center justify-center text-center px-8 border-r border-neutral-200 dark:border-neutral-800">
            <MessageSquare class="w-16 h-16 text-neutral-200 dark:text-neutral-800 mb-4" />
            <p class="font-extrabold text-2xl mb-2">Select a message</p>
            <p class="text-neutral-500 text-[15px]">Choose from your existing conversations or start a new one from someone's profile.</p>
        </div>
    {/if}

</div>

<!-- Compose modal -->
{#if composeOpen}
    <!-- svelte-ignore a11y_click_outside -->
    <div
        class="fixed inset-0 z-50 flex items-start justify-center pt-16 px-4"
        role="dialog"
        aria-modal="true"
        aria-label="New message"
    >
        <!-- Backdrop -->
        <button
            class="absolute inset-0 bg-black/40 dark:bg-black/60"
            onclick={() => composeOpen = false}
            aria-label="Close"
            tabindex="-1"
        ></button>

        <!-- Modal -->
        <div class="relative bg-white dark:bg-neutral-900 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
            <!-- Header -->
            <div class="flex items-center justify-between px-5 py-4 border-b border-neutral-200 dark:border-neutral-800">
                <h2 class="font-extrabold text-lg">New Message</h2>
                <button
                    onclick={() => composeOpen = false}
                    class="p-1.5 rounded-full hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors"
                    aria-label="Close"
                >
                    <X class="w-5 h-5" />
                </button>
            </div>

            <!-- Search -->
            <div class="px-4 py-3 border-b border-neutral-200 dark:border-neutral-800">
                <div class="flex items-center gap-2 bg-neutral-100 dark:bg-neutral-800 rounded-full px-4 py-2">
                    <Search class="w-4 h-4 text-neutral-400 shrink-0" />
                    <input
                        type="text"
                        bind:value={composeSearch}
                        placeholder="Search people you follow"
                        class="flex-1 bg-transparent text-[15px] outline-none placeholder-neutral-400"
                        autofocus
                    />
                    {#if composeSearch}
                        <button onclick={() => composeSearch = ''} class="text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200">
                            <X class="w-3.5 h-3.5" />
                        </button>
                    {/if}
                </div>
            </div>

            <!-- User list -->
            <div class="overflow-y-auto max-h-80">
                {#if followingUsers.length === 0}
                    <div class="px-5 py-10 text-center text-neutral-500 text-sm">
                        You're not following anyone yet.
                    </div>
                {:else if filteredFollowing.length === 0}
                    <div class="px-5 py-10 text-center text-neutral-500 text-sm">
                        No results for "{composeSearch}"
                    </div>
                {:else}
                    {#each filteredFollowing as user (user.id)}
                        <button
                            onclick={() => startConversation(user.id)}
                            class="w-full flex items-center gap-3 px-5 py-3 hover:bg-neutral-50 dark:hover:bg-neutral-800 transition-colors text-left"
                        >
                            <UserAvatar {user} />
                            <div class="min-w-0">
                                <p class="font-bold text-[15px] truncate">{user.name}</p>
                                <p class="text-neutral-500 text-sm truncate">@{user.username}</p>
                            </div>
                        </button>
                    {/each}
                {/if}
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
