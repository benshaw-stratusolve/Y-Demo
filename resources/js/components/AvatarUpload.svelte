<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { Camera, X } from 'lucide-svelte';
    import { updateAvatar, destroyAvatar } from '@/actions/App/Http/Controllers/Settings/ProfileController';
    import { notifications } from '@/lib/notifications.svelte';

    const MAX_BYTES = 5 * 1024 * 1024; // 5 MB
    const MAX_LABEL = '5 MB';

    interface Props {
        avatarUrl?: string | null;
        userName?: string;
    }

    let { avatarUrl = null, userName = '' }: Props = $props();

    let fileInput: HTMLInputElement | null = $state(null);
    let previewUrl: string | null = $state(null);
    let selectedFile: File | null = $state(null);
    let uploading = $state(false);

    const initials = $derived(
        userName
            .split(' ')
            .map((n) => n[0])
            .join('')
            .toUpperCase()
            .slice(0, 2)
    );

    const displayUrl = $derived(previewUrl ?? avatarUrl ?? null);

    const avatarColors = [
        'bg-red-400', 'bg-orange-400', 'bg-amber-400', 'bg-green-400',
        'bg-teal-400', 'bg-cyan-400', 'bg-blue-400', 'bg-indigo-400',
        'bg-violet-400', 'bg-purple-400', 'bg-pink-400', 'bg-rose-400',
    ];
    const avatarBg = $derived(
        avatarColors[userName.split('').reduce((acc, c) => acc + c.charCodeAt(0), 0) % avatarColors.length]
    );

    function handleFileChange(e: Event) {
        const file = (e.target as HTMLInputElement).files?.[0];
        if (!file) { return; }

        if (file.size > MAX_BYTES) {
            notifications.add({
                type: 'error',
                title: 'Image too large',
                description: `Your photo must be under ${MAX_LABEL}. Please choose a smaller file.`,
            });
            if (fileInput) { fileInput.value = ''; }
            return;
        }

        if (previewUrl) { URL.revokeObjectURL(previewUrl); }
        selectedFile = file;
        previewUrl = URL.createObjectURL(file);
    }

    function cancelPreview() {
        if (previewUrl) { URL.revokeObjectURL(previewUrl); }
        previewUrl = null;
        selectedFile = null;
        if (fileInput) { fileInput.value = ''; }
    }

    function saveAvatar() {
        if (!selectedFile) { return; }
        uploading = true;
        const formData = new FormData();
        formData.append('avatar', selectedFile);
        router.post(updateAvatar().url, formData, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                uploading = false;
                cancelPreview();
            },
        });
    }

    function removeAvatar() {
        router.delete(destroyAvatar().url, { preserveScroll: true });
    }
</script>

<div class="flex items-center gap-6">
    <!-- Avatar circle -->
    <div class="relative shrink-0">
        <button
            type="button"
            onclick={() => fileInput?.click()}
            class="relative w-20 h-20 rounded-full overflow-hidden group cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
            aria-label="Change photo"
            title="Click to change your profile photo"
        >
            <div class="w-20 h-20 rounded-full overflow-hidden flex items-center justify-center {displayUrl ? '' : avatarBg}">
                {#if displayUrl}
                    <img src={displayUrl} alt={userName} class="w-full h-full object-cover" />
                {:else}
                    <span class="text-xl font-bold text-white">{initials}</span>
                {/if}
            </div>
            <!-- Hover overlay -->
            <div class="absolute inset-0 rounded-full bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                <Camera class="w-6 h-6 text-white" />
            </div>
        </button>
        <!-- Camera badge -->
        <div class="absolute bottom-0 right-0 w-7 h-7 rounded-full bg-neutral-900 dark:bg-white border-2 border-white dark:border-black flex items-center justify-center pointer-events-none">
            <Camera class="w-3.5 h-3.5 text-white dark:text-black" />
        </div>
    </div>

    <!-- Actions -->
    <div class="flex flex-col gap-1.5">
        {#if previewUrl}
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    onclick={saveAvatar}
                    disabled={uploading}
                    class="text-sm font-semibold text-blue-500 hover:text-blue-400 disabled:opacity-50 transition-colors"
                >
                    {uploading ? 'Saving…' : 'Save photo'}
                </button>
                <button
                    type="button"
                    onclick={cancelPreview}
                    class="text-sm text-neutral-500 hover:text-neutral-700 dark:hover:text-neutral-300 transition-colors"
                >
                    Cancel
                </button>
            </div>
        {:else}
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    onclick={() => fileInput?.click()}
                    class="text-sm font-semibold text-neutral-900 dark:text-white hover:text-neutral-600 dark:hover:text-neutral-300 transition-colors"
                >
                    Change photo
                </button>
                {#if avatarUrl}
                    <button
                        type="button"
                        onclick={removeAvatar}
                        class="text-sm text-red-500 hover:text-red-400 transition-colors"
                    >
                        Remove
                    </button>
                {/if}
            </div>
            <p class="text-xs text-neutral-400">JPG, PNG or GIF · Max {MAX_LABEL}</p>
        {/if}
    </div>
</div>

<input
    bind:this={fileInput}
    type="file"
    accept="image/*"
    class="hidden"
    onchange={handleFileChange}
/>
