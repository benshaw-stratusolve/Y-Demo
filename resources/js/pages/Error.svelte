<script lang="ts">
    import { router } from '@inertiajs/svelte';

    let { status }: { status: number } = $props();

    const messages: Record<number, { headline: string; sub: string }> = {
        400: {
            headline: "That request made no sense.",
            sub: "Even Y has standards. Whatever you sent us, it wasn't quite right.",
        },
        402: {
            headline: "This one costs extra.",
            sub: "Payment required. Y do the good things always cost money?",
        },
        401: {
            headline: "Who are you, exactly?",
            sub: "You need to be logged in to see this. That's Y we have accounts.",
        },
        403: {
            headline: "You can't sit here.",
            sub: "You don't have permission to view this. That's just the way it is — that's Y.",
        },
        404: {
            headline: "Y are you here?",
            sub: "This page doesn't exist. It never did. It never will.",
        },
        405: {
            headline: "That's not how this works.",
            sub: "You used the wrong method. Y would you do that?",
        },
        419: {
            headline: "Your session expired.",
            sub: "We lost track of Y you are. Try going back and doing that again.",
        },
        422: {
            headline: "We couldn't process that.",
            sub: "Something in your request didn't add up. Double-check what you sent — that's Y validation exists.",
        },
        429: {
            headline: "Slow down there.",
            sub: "You're moving too fast. Even Y needs a moment to breathe. Try again shortly.",
        },
        500: {
            headline: "Something went very wrong.",
            sub: "Our servers are having a moment. Y does this always happen at the worst time?",
        },
        502: {
            headline: "Bad gateway. Whatever that means.",
            sub: "Something between here and there broke. That's Y the internet is complicated.",
        },
        503: {
            headline: "Y is taking a nap.",
            sub: "We'll be back shortly. Even the best platforms need a breather.",
        },
    };

    const { headline, sub } = messages[status] ?? {
        headline: "Something went wrong.",
        sub: "We're not sure what happened, but that's Y we're looking into it.",
    };
</script>

<div class="min-h-screen bg-white dark:bg-black text-gray-900 dark:text-gray-100 flex items-center justify-center px-4">
    <div class="text-center max-w-md">
        <div class="flex justify-center mb-8">
            <img src="/images/Y-dark-remove.png" alt="Y" class="h-14 w-14 object-contain dark:invert-0 invert" />
        </div>

        <p class="text-8xl font-black text-neutral-900 dark:text-white mb-4 select-none">{status}</p>

        <h1 class="text-2xl font-extrabold mb-3">{headline}</h1>
        <p class="text-neutral-500 dark:text-neutral-400 text-[15px] leading-relaxed mb-8">{sub}</p>

        <button
            onclick={() => router.visit('/dashboard')}
            class="bg-black dark:bg-white text-white dark:text-black font-bold rounded-full px-8 py-3 text-[15px] hover:bg-neutral-800 dark:hover:bg-neutral-200 transition-colors"
        >
            Take me home
        </button>
    </div>
</div>
