<script lang="ts">
    import { page, router } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { destroy as logout } from '@/actions/Laravel/Fortify/Http/Controllers/AuthenticatedSessionController';
    import AnimatedThemeToggler from '@/components/animated-theme-toggler/AnimatedThemeToggler.svelte';
    import AnimatedGradientText from '@/components/AnimatedGradientText.svelte';
    import { Home, Bell, Mail, Sparkles, User, Feather, Send, Phone, Video, Info, Search, Edit, ArrowLeft, MoreHorizontal } from 'lucide-svelte';

    const auth = $derived(page.props.auth as any);

    const conversations = [
        { id: 1, name: 'Luke Shaw', handle: '@lukeshaw', lastMessage: 'That looks amazing! Really loving the UI update you shipped today.', time: '2h', unread: 3 },
        { id: 2, name: 'AI Engineering Daily', handle: '@ai_eng', lastMessage: 'Check out this new post on autonomous agents...', time: '5h', unread: 0 },
        { id: 3, name: 'Sarah Chen', handle: '@sarachen', lastMessage: 'Just saw your tweet about the new feature!', time: '1d', unread: 1 },
        { id: 4, name: 'Dev Collective', handle: '@devcollective', lastMessage: 'Great discussion in the thread yesterday.', time: '2d', unread: 0 },
        { id: 5, name: 'Marco Rossi', handle: '@marco', lastMessage: 'When is the next update dropping?', time: '3d', unread: 0 },
    ];

    const chatMessages: Record<number, { id: number; sent: boolean; text: string; time: string }[]> = {
        1: [
            { id: 1, sent: false, text: 'Hey! Loved the new UI update 🔥', time: '10:42 AM' },
            { id: 2, sent: true, text: 'Thanks! We worked really hard on the OLED black aesthetic and mesh gradients', time: '10:43 AM' },
            { id: 3, sent: false, text: 'The animations are incredibly smooth. How did you handle the transitions?', time: '10:44 AM' },
            { id: 4, sent: true, text: 'Pure CSS + Svelte transitions. No heavy animation libraries needed', time: '10:45 AM' },
            { id: 5, sent: false, text: 'That looks amazing! Really loving the UI update you shipped today.', time: '10:46 AM' },
        ],
        2: [
            { id: 1, sent: false, text: 'The future of frontend iteration is autonomous agents', time: '9:00 AM' },
            { id: 2, sent: true, text: 'Completely agree. We\'re seeing 10x speed improvements already', time: '9:05 AM' },
            { id: 3, sent: false, text: 'Check out this new post on autonomous agents...', time: '9:10 AM' },
        ],
        3: [
            { id: 1, sent: false, text: 'Just saw your tweet about the new feature!', time: 'Yesterday' },
            { id: 2, sent: true, text: 'Yeah! Excited to share more soon', time: 'Yesterday' },
        ],
        4: [
            { id: 1, sent: false, text: 'Great discussion in the thread yesterday.', time: '2 days ago' },
            { id: 2, sent: true, text: 'Definitely! Always love the community engagement', time: '2 days ago' },
        ],
        5: [
            { id: 1, sent: false, text: 'When is the next update dropping?', time: '3 days ago' },
            { id: 2, sent: true, text: 'Soon! Keep an eye on the feed 👀', time: '3 days ago' },
        ],
    };

    let selectedConversation = $state(conversations[0]);
    let messageInput = $state('');
    let showChat = $state(true);

    function selectConversation(conv: typeof conversations[0]) {
        selectedConversation = conv;
        showChat = true;
    }

    function getInitials(name: string) {
        return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
    }

    function sendMessage() {
        if (!messageInput.trim()) return;
        messageInput = '';
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
                <AnimatedThemeToggler class="p-3 rounded-full transition-colors text-gray-900 dark:text-white" />
            </div>

            <nav class="flex flex-col gap-1 w-full mt-2">
                {#each [
                    { label: 'Home', icon: Home, href: '/dashboard' },
                    { label: 'Notifications', icon: Bell, href: '/notifications' },
                    { label: 'Messages', icon: Mail, href: '/messages', active: true },
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
                                    <linearGradient id="flok-icon-grad-msg" x1="0%" y1="0%" x2="100%" y2="100%">
                                        <stop offset="0%" stop-color="#60a5fa" />
                                        <stop offset="100%" stop-color="#a78bfa" />
                                    </linearGradient>
                                </defs>
                            </svg>
                            <Icon class="w-6 h-6" style="stroke: url(#flok-icon-grad-msg)" />
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

    <!-- Conversations list -->
    <div class="w-full sm:w-[350px] border-x border-neutral-200 dark:border-neutral-800 flex flex-col h-screen sticky top-0 {showChat ? 'hidden sm:flex' : 'flex'}">
        <!-- Header -->
        <div class="sticky top-0 bg-white/90 dark:bg-black/90 backdrop-blur-md z-10 border-b border-neutral-200 dark:border-neutral-800 px-4 py-3 flex items-center justify-between">
            <h1 class="text-xl font-extrabold">Messages</h1>
            <button class="p-2 hover:bg-neutral-100 dark:hover:bg-neutral-900 rounded-full transition-colors text-neutral-500 dark:text-neutral-400 hover:text-gray-900 dark:hover:text-white">
                <Edit class="w-5 h-5" />
            </button>
        </div>

        <!-- Search -->
        <div class="px-4 py-3 border-b border-neutral-200 dark:border-neutral-800">
            <div class="bg-neutral-100 dark:bg-neutral-900 rounded-full flex items-center px-4 py-2 border border-transparent focus-within:border-blue-500 focus-within:bg-white dark:focus-within:bg-black transition-colors">
                <Search class="w-4 h-4 text-neutral-400 shrink-0" />
                <input type="text" placeholder="Search Direct Messages" class="bg-transparent outline-none w-full ml-3 text-[14px] placeholder-neutral-400 dark:placeholder-neutral-500" />
            </div>
        </div>

        <!-- Conversation list -->
        <div class="overflow-y-auto flex-1">
            {#each conversations as conv}
                <button
                    onclick={() => selectConversation(conv)}
                    class="w-full flex items-start gap-3 px-4 py-3 transition-colors text-left hover:bg-neutral-50 dark:hover:bg-neutral-950 {selectedConversation?.id === conv.id ? 'bg-neutral-50 dark:bg-neutral-950' : ''}"
                >
                    <div class="w-12 h-12 rounded-full bg-neutral-200 dark:bg-neutral-800 flex-shrink-0 flex items-center justify-center">
                        <span class="text-sm font-bold text-neutral-600 dark:text-neutral-300">{getInitials(conv.name)}</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-bold text-[15px] truncate">{conv.name}</span>
                            <span class="text-neutral-500 text-xs shrink-0">{conv.time}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <p class="text-neutral-500 text-[14px] truncate flex-1">{conv.lastMessage}</p>
                            {#if conv.unread > 0}
                                <span class="bg-blue-500 text-white text-[11px] font-bold rounded-full w-5 h-5 flex items-center justify-center shrink-0">{conv.unread}</span>
                            {/if}
                        </div>
                    </div>
                </button>
            {/each}
        </div>
    </div>

    <!-- Chat window -->
    <div class="flex-1 border-r border-neutral-200 dark:border-neutral-800 flex flex-col h-screen sticky top-0 max-w-[600px] {showChat ? 'flex' : 'hidden sm:flex'}">
        {#if selectedConversation}
            <!-- Chat header -->
            <div class="sticky top-0 bg-white/90 dark:bg-black/90 backdrop-blur-md z-10 border-b border-neutral-200 dark:border-neutral-800 px-4 py-3 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <button
                        onclick={() => showChat = false}
                        class="sm:hidden p-2 -ml-2 hover:bg-neutral-100 dark:hover:bg-neutral-900 rounded-full transition-colors"
                    >
                        <ArrowLeft class="w-5 h-5" />
                    </button>
                    <div class="w-10 h-10 rounded-full bg-neutral-200 dark:bg-neutral-800 flex items-center justify-center shrink-0">
                        <span class="text-xs font-bold text-neutral-600 dark:text-neutral-300">{getInitials(selectedConversation.name)}</span>
                    </div>
                    <div>
                        <div class="font-bold text-[15px]">{selectedConversation.name}</div>
                        <div class="text-neutral-500 text-xs">{selectedConversation.handle}</div>
                    </div>
                </div>
                <div class="flex items-center gap-1">
                    <button class="p-2 hover:bg-neutral-100 dark:hover:bg-neutral-900 rounded-full transition-colors text-neutral-500 hover:text-gray-900 dark:hover:text-white">
                        <Phone class="w-5 h-5" />
                    </button>
                    <button class="p-2 hover:bg-neutral-100 dark:hover:bg-neutral-900 rounded-full transition-colors text-neutral-500 hover:text-gray-900 dark:hover:text-white">
                        <Video class="w-5 h-5" />
                    </button>
                    <button class="p-2 hover:bg-neutral-100 dark:hover:bg-neutral-900 rounded-full transition-colors text-neutral-500 hover:text-gray-900 dark:hover:text-white">
                        <Info class="w-5 h-5" />
                    </button>
                </div>
            </div>

            <!-- Messages area -->
            <div class="flex-1 overflow-y-auto px-4 py-6 flex flex-col gap-4">
                <!-- Date label -->
                <div class="flex justify-center">
                    <span class="text-xs text-neutral-400 bg-neutral-100 dark:bg-neutral-900 px-3 py-1 rounded-full">Today</span>
                </div>

                {#each (chatMessages[selectedConversation.id] ?? []) as msg (msg.id)}
                    <div class="flex {msg.sent ? 'justify-end' : 'justify-start'} items-end gap-2">
                        {#if !msg.sent}
                            <div class="w-8 h-8 rounded-full bg-neutral-200 dark:bg-neutral-800 flex-shrink-0 flex items-center justify-center mb-0.5">
                                <span class="text-[10px] font-bold text-neutral-600 dark:text-neutral-300">{getInitials(selectedConversation.name)}</span>
                            </div>
                        {/if}
                        <div class="flex flex-col {msg.sent ? 'items-end' : 'items-start'} max-w-xs lg:max-w-sm">
                            <div class="px-4 py-2.5 rounded-2xl text-[15px] leading-snug {msg.sent ? 'bg-blue-500 text-white rounded-br-sm' : 'bg-neutral-100 dark:bg-neutral-900 text-gray-900 dark:text-gray-100 rounded-bl-sm'}">
                                {msg.text}
                            </div>
                            <span class="text-[11px] text-neutral-400 mt-1 px-1">{msg.time}</span>
                        </div>
                    </div>
                {/each}
            </div>

            <!-- Message input -->
            <div class="border-t border-neutral-200 dark:border-neutral-800 px-4 py-3">
                <div class="flex items-center gap-3 bg-neutral-100 dark:bg-neutral-900 rounded-full px-4 py-2.5 border border-transparent focus-within:border-blue-500 focus-within:bg-white dark:focus-within:bg-black transition-colors">
                    <input
                        type="text"
                        bind:value={messageInput}
                        placeholder="Start a new message"
                        class="bg-transparent outline-none flex-1 text-[15px] placeholder-neutral-400 dark:placeholder-neutral-500"
                        onkeydown={(e) => e.key === 'Enter' && sendMessage()}
                    />
                    <button
                        onclick={sendMessage}
                        disabled={!messageInput.trim()}
                        class="text-blue-500 disabled:text-neutral-300 dark:disabled:text-neutral-600 transition-colors p-1"
                        aria-label="Send"
                    >
                        <Send class="w-5 h-5" />
                    </button>
                </div>
            </div>
        {:else}
            <!-- Empty state -->
            <div class="flex-1 flex flex-col items-center justify-center gap-4 text-center px-8">
                <div class="w-16 h-16 rounded-full bg-neutral-100 dark:bg-neutral-900 flex items-center justify-center">
                    <Mail class="w-8 h-8 text-neutral-400" />
                </div>
                <div>
                    <h2 class="font-extrabold text-2xl mb-1">Select a message</h2>
                    <p class="text-neutral-500 text-[15px]">Choose from your existing conversations or start a new one.</p>
                </div>
                <button class="bg-blue-500 hover:bg-blue-600 text-white font-bold rounded-full py-3 px-6 transition-colors mt-2">
                    New message
                </button>
            </div>
        {/if}
    </div>

</div>

<style>
    :global(::-webkit-scrollbar) { width: 6px; }
    :global(::-webkit-scrollbar-track) { background: transparent; }
    :global(::-webkit-scrollbar-thumb) { background: #e5e7eb; border-radius: 4px; }
    :global(.dark ::-webkit-scrollbar-thumb) { background: #262626; }
</style>
