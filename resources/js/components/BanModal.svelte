<script lang="ts">
    import { page } from '@inertiajs/svelte';

    let open = $state(false);
    let message = $state('');

    $effect(() => {
        const errors = page.props.errors as Record<string, string> | undefined;
        const banError = errors?.account_banned;
        if (banError) {
            message = banError;
            open = true;
        }
    });
</script>

{#if open}
    <!-- Backdrop -->
    <div class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm"></div>

    <!-- Modal -->
    <div class="fixed inset-0 z-50 flex items-center justify-center px-4">
        <div class="w-full max-w-md bg-white dark:bg-neutral-950 rounded-2xl border border-red-200 dark:border-red-900 shadow-2xl p-8 text-center">
            <div class="w-16 h-16 rounded-full bg-red-100 dark:bg-red-950 flex items-center justify-center mx-auto mb-4">
                <span class="text-3xl">🚫</span>
            </div>
            <h2 class="text-xl font-extrabold text-red-600 dark:text-red-400 mb-2">Account Banned</h2>
            <p class="text-neutral-700 dark:text-neutral-300 text-[15px] mb-6">{message}</p>
            <button
                onclick={() => (open = false)}
                class="w-full rounded-full bg-red-600 hover:bg-red-700 text-white font-bold py-2.5 transition-colors"
            >
                I understand
            </button>
        </div>
    </div>
{/if}
