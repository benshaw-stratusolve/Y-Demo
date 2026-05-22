<script lang="ts">
    import { onMount, tick } from 'svelte';
    import { page } from '@inertiajs/svelte';
    import { fade, fly } from 'svelte/transition';
    import { Home, Bell, Sparkles, User, Send, ChevronDown } from 'lucide-svelte';
    import AnimatedGradientText from '@/components/AnimatedGradientText.svelte';
    import AnimatedThemeToggler from '@/components/animated-theme-toggler/AnimatedThemeToggler.svelte';
    import AnimatedGridPattern from '@/components/animated-grid-pattern/AnimatedGridPattern.svelte';
    import CardContainer from '@/components/3d-card/CardContainer.svelte';
    import CardBody from '@/components/3d-card/CardBody.svelte';
    import CardItem from '@/components/3d-card/CardItem.svelte';

    type Message = {
        id: number;
        role: 'user' | 'assistant';
        content: string;
    };

    const unreadCount = $derived((page.props as any).unread_notifications_count as number ?? 0);

    let ready = $state(false);
    let isDark = $state(false);
    let isMouseEntered = $state(false);
    let chatVisible = $state(false);
    let input = $state('');
    let messages = $state<Message[]>([
        {
            id: 0,
            role: 'assistant',
            content: "Hi! I'm FlockAI — your intelligent assistant on Y. Ask me anything, from content ideas to trending topics. What's on your mind?",
        },
    ]);
    let isTyping = $state(false);
    let messagesEnd: HTMLElement | null = $state(null);
    let chatSection: HTMLElement | null = $state(null);

    onMount(() => {
        isDark = document.documentElement.classList.contains('dark');
        ready = true;

        const observer = new MutationObserver(() => {
            isDark = document.documentElement.classList.contains('dark');
        });
        observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

        return () => observer.disconnect();
    });

    async function scrollToBottom() {
        await tick();
        messagesEnd?.scrollIntoView({ behavior: 'smooth' });
    }

    async function sendMessage() {
        const text = input.trim();
        if (!text || isTyping) return;

        input = '';
        const priorMessages = messages.slice();
        messages = [...messages, { id: Date.now(), role: 'user', content: text }];
        isTyping = true;
        await scrollToBottom();

        try {
            const res = await fetch('/flock-ai/chat', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    message: text,
                    history: priorMessages.map(m => ({ role: m.role, content: m.content })),
                }),
            });
            const data = await res.json();
            messages = [...messages, { id: Date.now() + 1, role: 'assistant', content: data.message }];
        } catch {
            messages = [...messages, { id: Date.now() + 1, role: 'assistant', content: 'Sorry, something went wrong. Please try again.' }];
        } finally {
            isTyping = false;
            await scrollToBottom();
        }
    }

    function handleKeydown(e: KeyboardEvent) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    }

    async function openChat() {
        chatVisible = true;
        await tick();
        chatSection?.scrollIntoView({ behavior: 'smooth' });
    }

    const navItems = [
        { label: 'Home', icon: Home, href: '/dashboard' },
        { label: 'Notifications', icon: Bell, href: '/notifications' },
        { label: 'Flok', icon: Sparkles, href: '/flock-ai' },
        { label: 'Profile', icon: User, href: '/settings/profile' },
    ];

    const logoSrc = $derived(isDark ? '/images/flock_white.png' : '/images/flock_black.png');
</script>

<svelte:head>
    <title>FlockAI — Y</title>
</svelte:head>

{#if ready}
<div
    in:fade={{ duration: 400 }}
    class="{isDark ? 'dark bg-black text-white' : 'bg-gray-50 text-gray-900'} font-sans transition-colors duration-300"
>

    <!-- ───────────── HERO ───────────── -->
    <section class="relative min-h-screen flex flex-col overflow-hidden">

        <!-- Grid background -->
        <AnimatedGridPattern
            class="absolute inset-0 z-0 [mask-image:radial-gradient(ellipse_at_50%_40%,black_30%,transparent_80%)] {isDark ? 'text-white/5' : 'text-gray-900/8'}"
            width={60}
            height={60}
            numSquares={30}
            maxOpacity={0.4}
            duration={3}
        />

        <!-- Ambient glow -->
        <div class="absolute inset-0 z-0 flex items-center justify-center pointer-events-none">
            <div class="w-96 h-96 rounded-full {isDark ? 'bg-white-600/10' : 'bg-black-500/15'} blur-3xl"></div>
            <div class="absolute w-64 h-64 rounded-full {isDark ? 'bg-violet-600/10' : 'bg-violet-400/15'} blur-2xl"></div>
        </div>

        <!-- Top nav -->
        <header class="relative z-20 flex items-center justify-between px-6 py-4">
            <a href="/dashboard" class="flex items-center gap-2 opacity-80 hover:opacity-100 transition-opacity">
                <img
                    src="/images/Y-dark-remove.png"
                    alt="Y"
                    class="h-7 w-7 object-contain {isDark ? '' : 'invert'}"
                />
            </a>
            <AnimatedThemeToggler class="p-2 rounded-full transition-colors {isDark ? 'text-white' : 'text-gray-900'}" />
        </header>

        <!-- Hero content -->
        <div class="relative z-10 flex-1 flex flex-col items-center justify-center text-center px-6 pb-24">

            <!-- Logo with 3D cursor hover effect -->
            <div in:fly={{ y: 20, duration: 700, delay: 100 }} class="-mb-20">
                <CardContainer bind:isMouseEntered>
                    <CardBody class="w-auto h-auto bg-transparent">
                        <CardItem {isMouseEntered} translateZ={80} class="w-full">
                            <img
                                src={logoSrc}
                                alt="FlockAI"
                                class="h-72 w-72 sm:h-80 sm:w-80 object-contain cursor-pointer"
                                style="filter: drop-shadow(0 0 {isDark ? '28px rgba(255,255,255,0.45)' : '20px rgba(0,0,0,0.2)'});"
                            />
                        </CardItem>
                    </CardBody>
                </CardContainer>
            </div>

            <!-- Name -->
            <div in:fly={{ y: 20, duration: 700, delay: 200 }} class="mb-3">
                <AnimatedGradientText class="text-5xl sm:text-6xl font-extrabold tracking-tight">
                    FlockAI
                </AnimatedGradientText>
            </div>

            <!-- Tagline -->
            <p in:fly={{ y: 20, duration: 700, delay: 300 }} class="text-lg sm:text-xl {isDark ? 'text-white/60' : 'text-gray-500'} max-w-md mb-12">
                Your intelligent AI companion on Y. Ask anything, explore everything.
            </p>

            <!-- CTA button -->
            <button
                in:fly={{ y: 20, duration: 700, delay: 500 }}
                onclick={openChat}
                class="group relative inline-flex items-center gap-3 px-8 py-4 rounded-full text-base font-semibold transition-all duration-300 hover:scale-105 active:scale-95 {isDark ? 'text-white' : 'text-gray-900'}"
                style="background: linear-gradient(135deg, rgba(96,165,250,0.15), rgba(167,139,250,0.15)); border: 1px solid rgba(167,139,250,0.5); box-shadow: 0 0 30px rgba(96,165,250,0.15);"
                aria-label="Start chatting"
            >
                <span class="relative z-10">Start Chatting</span>
                <ChevronDown class="w-5 h-5 animate-bounce relative z-10" />
                <div class="absolute inset-0 rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-300"
                    style="background: linear-gradient(135deg, rgba(96,165,250,0.25), rgba(167,139,250,0.25)); box-shadow: 0 0 40px rgba(96,165,250,0.3);">
                </div>
            </button>
        </div>

        <!-- Left sidebar nav -->
        <aside class="fixed left-0 top-0 h-full w-[72px] xl:w-[275px] z-30 hidden sm:flex flex-col py-4 px-2 xl:px-4">
            <nav class="flex flex-col gap-1 mt-20">
                {#each navItems as item}
                    {@const Icon = item.icon}
                    <a
                        href={item.href}
                        class="flex items-center gap-4 p-3 rounded-full transition-colors
                            {item.label === 'Flok'
                                ? (isDark ? 'text-white' : 'text-gray-900')
                                : (isDark ? 'text-neutral-500 hover:text-white hover:bg-white/5' : 'text-neutral-400 hover:text-gray-900 hover:bg-gray-100')}"
                    >
                        {#if item.label === 'Flok'}
                            <svg width="0" height="0" style="position:absolute;overflow:hidden">
                                <defs>
                                    <linearGradient id="flok-grad-fai" x1="0%" y1="0%" x2="100%" y2="100%">
                                        <stop offset="0%" stop-color="#60a5fa" />
                                        <stop offset="100%" stop-color="#a78bfa" />
                                    </linearGradient>
                                </defs>
                            </svg>
                            <Icon class="w-6 h-6 shrink-0" style="stroke: url(#flok-grad-fai)" />
                            <AnimatedGradientText class="text-xl font-semibold hidden xl:inline">Flok AI</AnimatedGradientText>
                        {:else if item.label === 'Notifications'}
                            <div class="relative">
                                <Icon class="w-6 h-6 shrink-0" />
                                {#if unreadCount > 0}
                                    <span class="absolute -top-1 -right-1 min-w-[16px] h-4 bg-blue-500 rounded-full text-white text-[9px] font-bold flex items-center justify-center px-0.5">{unreadCount > 99 ? '99+' : unreadCount}</span>
                                {/if}
                            </div>
                            <span class="text-xl hidden xl:block">{item.label}</span>
                        {:else}
                            <Icon class="w-6 h-6 shrink-0" />
                            <span class="text-xl hidden xl:block">{item.label}</span>
                        {/if}
                    </a>
                {/each}
            </nav>
        </aside>
    </section>

    <!-- ───────────── CHAT ───────────── -->
    {#if chatVisible}
    <section
        in:fade={{ duration: 400 }}
        bind:this={chatSection}
        class="relative min-h-screen flex flex-col sm:pl-[72px] xl:pl-[275px] {isDark ? 'bg-black' : 'bg-gray-50'}"
    >
        <!-- Subtle grid -->
        <div
            class="absolute inset-0 z-0 pointer-events-none"
            style="background-image: linear-gradient({isDark ? 'rgba(255,255,255,0.03)' : 'rgba(0,0,0,0.05)'} 1px, transparent 1px), linear-gradient(to right, {isDark ? 'rgba(255,255,255,0.03)' : 'rgba(0,0,0,0.05)'} 1px, transparent 1px); background-size: 60px 60px;"
        ></div>

        <div class="relative z-10 flex flex-col h-screen max-w-3xl mx-auto w-full px-4 py-6">

            <!-- Chat header -->
            <div class="flex items-center gap-3 pb-4 mb-4 shrink-0 border-b {isDark ? 'border-white/10' : 'border-gray-200'}">
                <div class="relative">
                    <div class="h-9 w-9 rounded-full {isDark ? 'bg-white/5' : 'bg-white border border-gray-200'} p-1 flex items-center justify-center">
                        <img src={logoSrc} alt="FlockAI" class="w-full h-full object-contain" />
                    </div>
                    <span class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 bg-green-400 rounded-full border-2 {isDark ? 'border-black' : 'border-gray-50'}"></span>
                </div>
                <div>
                    <p class="font-semibold leading-tight {isDark ? 'text-white' : 'text-gray-900'}">FlockAI</p>
                    <p class="text-xs text-green-500">Online</p>
                </div>
            </div>

            <!-- Messages -->
            <div class="flex-1 overflow-y-auto flex flex-col gap-4 pb-4 scroll-smooth" style="scrollbar-width: thin;">
                {#each messages as msg (msg.id)}
                    <div in:fly={{ y: 10, duration: 300 }} class="flex gap-3 {msg.role === 'user' ? 'flex-row-reverse' : 'flex-row'}">

                        <!-- Avatar -->
                        {#if msg.role === 'assistant'}
                            <div class="shrink-0 w-8 h-8 rounded-full bg-gradient-to-br from-blue-500/20 to-violet-500/20 border {isDark ? 'border-white/10' : 'border-gray-200'} flex items-center justify-center p-1">
                                <img src={logoSrc} alt="FlockAI" class="w-full h-full object-contain" />
                            </div>
                        {:else}
                            <div class="shrink-0 w-8 h-8 rounded-full {isDark ? 'bg-white/10 border-white/10' : 'bg-gray-100 border-gray-200'} border flex items-center justify-center">
                                <User class="w-4 h-4 {isDark ? 'text-white/60' : 'text-gray-400'}" />
                            </div>
                        {/if}

                        <!-- Bubble -->
                        <div class="max-w-[75%] flex flex-col gap-1 {msg.role === 'user' ? 'items-end' : 'items-start'}">
                            <div class="px-4 py-2.5 rounded-2xl text-sm leading-relaxed
                                {msg.role === 'user'
                                    ? 'bg-blue-600 text-white rounded-tr-sm'
                                    : isDark
                                        ? 'bg-white/8 text-white/90 border border-white/10 rounded-tl-sm'
                                        : 'bg-white text-gray-800 border border-gray-200 rounded-tl-sm shadow-sm'}">
                                {msg.content}
                            </div>
                        </div>
                    </div>
                {/each}

                <!-- Typing indicator -->
                {#if isTyping}
                    <div in:fade={{ duration: 200 }} class="flex gap-3">
                        <div class="shrink-0 w-8 h-8 rounded-full bg-gradient-to-br from-blue-500/20 to-violet-500/20 border {isDark ? 'border-white/10' : 'border-gray-200'} flex items-center justify-center p-1">
                            <img src={logoSrc} alt="FlockAI" class="w-full h-full object-contain" />
                        </div>
                        <div class="px-4 py-3 rounded-2xl rounded-tl-sm {isDark ? 'bg-white/8 border-white/10' : 'bg-white border-gray-200 shadow-sm'} border flex items-center gap-1">
                            <span class="w-1.5 h-1.5 {isDark ? 'bg-white/40' : 'bg-gray-400'} rounded-full animate-bounce" style="animation-delay: 0ms"></span>
                            <span class="w-1.5 h-1.5 {isDark ? 'bg-white/40' : 'bg-gray-400'} rounded-full animate-bounce" style="animation-delay: 150ms"></span>
                            <span class="w-1.5 h-1.5 {isDark ? 'bg-white/40' : 'bg-gray-400'} rounded-full animate-bounce" style="animation-delay: 300ms"></span>
                        </div>
                    </div>
                {/if}

                <div bind:this={messagesEnd}></div>
            </div>

            <!-- Input bar -->
            <div class="shrink-0 mt-2">
                <div class="flex items-end gap-3 {isDark ? 'bg-white/5 border-white/10' : 'bg-white border-gray-200 shadow-sm'} border rounded-2xl px-4 py-3 focus-within:border-blue-500/60 transition-colors">
                    <textarea
                        bind:value={input}
                        onkeydown={handleKeydown}
                        placeholder="Ask FlockAI anything…"
                        rows={1}
                        class="flex-1 bg-transparent {isDark ? 'text-white placeholder-white/30' : 'text-gray-900 placeholder-gray-400'} text-sm resize-none outline-none leading-relaxed max-h-32"
                        style="field-sizing: content;"
                    ></textarea>
                    <button
                        onclick={sendMessage}
                        disabled={!input.trim() || isTyping}
                        class="shrink-0 w-8 h-8 rounded-xl flex items-center justify-center transition-all
                            {input.trim() && !isTyping
                                ? 'bg-blue-600 hover:bg-blue-500 text-white'
                                : isDark ? 'bg-white/5 text-white/20 cursor-not-allowed' : 'bg-gray-100 text-gray-300 cursor-not-allowed'}"
                        aria-label="Send message"
                    >
                        <Send class="w-4 h-4" />
                    </button>
                </div>
                <p class="text-center {isDark ? 'text-white/20' : 'text-gray-400'} text-xs mt-2">FlockAI can make mistakes. Consider verifying important information.</p>
            </div>
        </div>
    </section>
    {/if}

</div>
{/if}
