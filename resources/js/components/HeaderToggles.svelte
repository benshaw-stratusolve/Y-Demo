<script lang="ts">
    import AnimatedThemeToggler from '@/components/animated-theme-toggler/AnimatedThemeToggler.svelte';
    import { Bell, BellOff } from 'lucide-svelte';
    import { isSoundEnabled, setSoundEnabled } from '@/lib/notification-sounds';

    let soundEnabled = $state(isSoundEnabled());

    function toggleSound() {
        soundEnabled = !soundEnabled;
        setSoundEnabled(soundEnabled);
    }
</script>

<div class="flex items-center gap-1">
    <AnimatedThemeToggler class="p-2 rounded-full transition-colors text-gray-900 dark:text-white hover:bg-neutral-100 dark:hover:bg-neutral-900" />
    <button
        onclick={toggleSound}
        class="p-2 rounded-full transition-colors hover:bg-neutral-100 dark:hover:bg-neutral-900 {soundEnabled ? 'text-gray-900 dark:text-white' : 'text-neutral-400 dark:text-neutral-600'}"
        aria-label={soundEnabled ? 'Mute notification sounds' : 'Unmute notification sounds'}
        title={soundEnabled ? 'Mute notification sounds' : 'Unmute notification sounds'}
    >
        {#if soundEnabled}
            <Bell class="w-4 h-4" />
        {:else}
            <BellOff class="w-4 h-4" />
        {/if}
    </button>
</div>
